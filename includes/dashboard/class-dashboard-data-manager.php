<?php
/**
 * Dashboard Data Manager
 *
 * Handles all database operations for the career dashboard including
 * job applications, saved jobs, activity tracking, and metrics.
 *
 * @package SFFC_Careers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Dashboard_Data_Manager {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Database table names
     */
    private $table_applications;
    private $table_saved_jobs;
    private $table_activity;
    private $table_snapshots;
    private $table_interactions;

    /**
     * Valid application stages
     */
    const STAGES = array(
        'applied' => 'Applied',
        'waiting' => 'Waiting for Response',
        'first-interview' => '1st Stage Interview',
        'further-interview' => 'Further Interview',
        'secured' => 'Secured',
        'moved-on' => 'Moved On'
    );

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        global $wpdb;

        $this->table_applications = $wpdb->prefix . 'sffc_job_applications';
        $this->table_saved_jobs = $wpdb->prefix . 'sffc_saved_jobs';
        $this->table_activity = $wpdb->prefix . 'sffc_user_activity';
        $this->table_snapshots = $wpdb->prefix . 'sffc_dashboard_snapshots';
        $this->table_interactions = $wpdb->prefix . 'sffc_interactions';
    }

    // =========================================================================
    // JOB APPLICATIONS
    // =========================================================================

    /**
     * Save or update job application stage
     *
     * @param int $user_id User ID
     * @param int $job_id Job post ID
     * @param string $stage Application stage
     * @param array $extra_data Optional extra data (notes, source, etc.)
     * @return array Result with success status
     */
    public function save_job_stage($user_id, $job_id, $stage, $extra_data = array()) {
        global $wpdb;

        // Validate stage
        if (!array_key_exists($stage, self::STAGES) && $stage !== '') {
            return array('success' => false, 'message' => 'Invalid stage');
        }

        // If stage is empty, remove the application tracking
        if ($stage === '') {
            return $this->remove_application($user_id, $job_id);
        }

        // Get job details for denormalized storage
        $job_data = $this->get_job_details($job_id);

        // Check if application exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, stage FROM {$this->table_applications} WHERE user_id = %d AND job_id = %d",
            $user_id,
            $job_id
        ));

        $previous_stage = $existing ? $existing->stage : null;

        if ($existing) {
            // Update existing
            $result = $wpdb->update(
                $this->table_applications,
                array(
                    'stage' => $stage,
                    'stage_updated' => current_time('mysql'),
                    'notes' => isset($extra_data['notes']) ? $extra_data['notes'] : null
                ),
                array('id' => $existing->id),
                array('%s', '%s', '%s'),
                array('%d')
            );
        } else {
            // Insert new
            $result = $wpdb->insert(
                $this->table_applications,
                array(
                    'user_id' => $user_id,
                    'job_id' => $job_id,
                    'stage' => $stage,
                    'applied_date' => current_time('mysql'),
                    'stage_updated' => current_time('mysql'),
                    'source' => isset($extra_data['source']) ? $extra_data['source'] : 'dashboard',
                    'notes' => isset($extra_data['notes']) ? $extra_data['notes'] : null,
                    'company_name' => $job_data['company'],
                    'job_title' => $job_data['title'],
                    'location' => $job_data['location'],
                    'salary_min' => $job_data['salary_min'],
                    'salary_max' => $job_data['salary_max']
                ),
                array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d')
            );
        }

        if ($result !== false) {
            // Log activity
            $this->log_activity($user_id, 'stage_change', array(
                'job_id' => $job_id,
                'previous_stage' => $previous_stage,
                'new_stage' => $stage
            ), $job_id);

            return array(
                'success' => true,
                'message' => 'Stage updated',
                'previous_stage' => $previous_stage,
                'new_stage' => $stage,
                'stats' => $this->get_pipeline_stats($user_id)
            );
        }

        return array('success' => false, 'message' => 'Database error');
    }

    /**
     * Remove application tracking
     */
    public function remove_application($user_id, $job_id) {
        global $wpdb;

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT stage FROM {$this->table_applications} WHERE user_id = %d AND job_id = %d",
            $user_id,
            $job_id
        ));

        $previous_stage = $existing ? $existing->stage : null;

        $result = $wpdb->delete(
            $this->table_applications,
            array('user_id' => $user_id, 'job_id' => $job_id),
            array('%d', '%d')
        );

        if ($result) {
            $this->log_activity($user_id, 'application_removed', array(
                'job_id' => $job_id,
                'previous_stage' => $previous_stage
            ), $job_id);
        }

        return array(
            'success' => $result !== false,
            'previous_stage' => $previous_stage,
            'stats' => $this->get_pipeline_stats($user_id)
        );
    }

    /**
     * Get application stage for a specific job
     */
    public function get_job_stage($user_id, $job_id) {
        global $wpdb;

        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT stage FROM {$this->table_applications} WHERE user_id = %d AND job_id = %d",
            $user_id,
            $job_id
        ));

        return $result ?: '';
    }

    /**
     * Get all applications for a user
     */
    public function get_user_applications($user_id, $filters = array()) {
        global $wpdb;

        $where = array("user_id = %d");
        $params = array($user_id);

        if (!empty($filters['stage'])) {
            $where[] = "stage = %s";
            $params[] = $filters['stage'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "applied_date >= %s";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "applied_date <= %s";
            $params[] = $filters['date_to'];
        }

        $order_by = isset($filters['order_by']) ? $filters['order_by'] : 'stage_updated';
        $order = isset($filters['order']) && strtoupper($filters['order']) === 'ASC' ? 'ASC' : 'DESC';
        $limit = isset($filters['limit']) ? intval($filters['limit']) : 100;

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table_applications}
             WHERE " . implode(' AND ', $where) . "
             ORDER BY {$order_by} {$order}
             LIMIT %d",
            array_merge($params, array($limit))
        );

        return $wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Get application counts by stage (for pipeline)
     */
    public function get_pipeline_stats($user_id) {
        global $wpdb;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT stage, COUNT(*) as count
             FROM {$this->table_applications}
             WHERE user_id = %d
             GROUP BY stage",
            $user_id
        ), ARRAY_A);

        // Initialize all stages with 0
        $stats = array();
        foreach (self::STAGES as $key => $label) {
            $stats[$key] = 0;
        }

        // Fill in actual counts
        foreach ($results as $row) {
            if (isset($stats[$row['stage']])) {
                $stats[$row['stage']] = intval($row['count']);
            }
        }

        // Calculate total
        $stats['total'] = array_sum($stats);

        return $stats;
    }

    // =========================================================================
    // SAVED JOBS
    // =========================================================================

    /**
     * Save a job
     */
    public function save_job($user_id, $job_id, $folder = 'default') {
        global $wpdb;

        $result = $wpdb->replace(
            $this->table_saved_jobs,
            array(
                'user_id' => $user_id,
                'job_id' => $job_id,
                'saved_date' => current_time('mysql'),
                'folder' => $folder
            ),
            array('%d', '%d', '%s', '%s')
        );

        if ($result) {
            $this->log_activity($user_id, 'job_saved', array('folder' => $folder), $job_id);
        }

        return $result !== false;
    }

    /**
     * Unsave a job
     */
    public function unsave_job($user_id, $job_id) {
        global $wpdb;

        $result = $wpdb->delete(
            $this->table_saved_jobs,
            array('user_id' => $user_id, 'job_id' => $job_id),
            array('%d', '%d')
        );

        if ($result) {
            $this->log_activity($user_id, 'job_unsaved', array(), $job_id);
        }

        return $result !== false;
    }

    /**
     * Check if job is saved
     */
    public function is_job_saved($user_id, $job_id) {
        global $wpdb;

        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_saved_jobs} WHERE user_id = %d AND job_id = %d",
            $user_id,
            $job_id
        ));
    }

    /**
     * Get saved jobs count
     */
    public function get_saved_jobs_count($user_id) {
        global $wpdb;

        return intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_saved_jobs} WHERE user_id = %d",
            $user_id
        )));
    }

    /**
     * Get all saved jobs
     */
    public function get_saved_jobs($user_id, $folder = null) {
        global $wpdb;

        $sql = "SELECT * FROM {$this->table_saved_jobs} WHERE user_id = %d";
        $params = array($user_id);

        if ($folder) {
            $sql .= " AND folder = %s";
            $params[] = $folder;
        }

        $sql .= " ORDER BY saved_date DESC";

        return $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
    }

    // =========================================================================
    // INTERACTIONS (Networking/Recruiter)
    // =========================================================================

    /**
     * Log an interaction
     */
    public function log_interaction($user_id, $type, $data) {
        global $wpdb;

        $result = $wpdb->insert(
            $this->table_interactions,
            array(
                'user_id' => $user_id,
                'interaction_type' => $type,
                'contact_name' => isset($data['contact_name']) ? $data['contact_name'] : null,
                'contact_email' => isset($data['contact_email']) ? $data['contact_email'] : null,
                'company' => isset($data['company']) ? $data['company'] : null,
                'job_id' => isset($data['job_id']) ? $data['job_id'] : null,
                'status' => isset($data['status']) ? $data['status'] : 'pending',
                'notes' => isset($data['notes']) ? $data['notes'] : null,
                'follow_up_date' => isset($data['follow_up_date']) ? $data['follow_up_date'] : null
            ),
            array('%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s')
        );

        return $result !== false ? $wpdb->insert_id : false;
    }

    /**
     * Get interaction counts by type
     */
    public function get_interaction_counts($user_id) {
        global $wpdb;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT interaction_type, COUNT(*) as count
             FROM {$this->table_interactions}
             WHERE user_id = %d
             GROUP BY interaction_type",
            $user_id
        ), ARRAY_A);

        $counts = array(
            'networking' => 0,
            'recruiter' => 0,
            'referral' => 0
        );

        foreach ($results as $row) {
            $counts[$row['interaction_type']] = intval($row['count']);
        }

        return $counts;
    }

    // =========================================================================
    // OVERVIEW STATISTICS
    // =========================================================================

    /**
     * Get all overview stats for dashboard
     */
    public function get_overview_stats($user_id) {
        $pipeline = $this->get_pipeline_stats($user_id);
        $interactions = $this->get_interaction_counts($user_id);
        $saved_count = $this->get_saved_jobs_count($user_id);

        // Get high matches (jobs with 80%+ match score that user hasn't applied to)
        $high_matches = $this->get_high_match_count($user_id);

        // Get period comparisons (last 30 days vs previous 30 days)
        $comparisons = $this->get_period_comparisons($user_id, 30);

        return array(
            'total_applications' => $pipeline['total'],
            'high_matches' => $high_matches,
            'networking_intros' => $interactions['networking'],
            'recruiter_intros' => $interactions['recruiter'],
            'referrals' => $interactions['referral'],
            'saved_jobs' => $saved_count,
            'pipeline' => $pipeline,
            'comparisons' => $comparisons
        );
    }

    /**
     * Get count of high-match jobs (80%+)
     */
    private function get_high_match_count($user_id) {
        // This would integrate with your job matching system
        // For now, return count from matching jobs that score >= 80
        global $wpdb;

        // Check if we have a job matches table or need to calculate
        // This is a placeholder - integrate with your actual matching logic
        $jobs_table = $wpdb->prefix . 'posts';

        // Count jobs with high match scores from user profile
        // This needs to be connected to your actual job matching algorithm
        return 0; // Will be populated once connected to job matcher
    }

    /**
     * Get period comparisons for trend indicators
     */
    public function get_period_comparisons($user_id, $days = 30) {
        global $wpdb;

        $current_start = date('Y-m-d', strtotime("-{$days} days"));
        $previous_start = date('Y-m-d', strtotime("-" . ($days * 2) . " days"));
        $previous_end = date('Y-m-d', strtotime("-{$days} days"));

        // Current period applications
        $current_apps = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_applications}
             WHERE user_id = %d AND applied_date >= %s",
            $user_id,
            $current_start
        )));

        // Previous period applications
        $previous_apps = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_applications}
             WHERE user_id = %d AND applied_date >= %s AND applied_date < %s",
            $user_id,
            $previous_start,
            $previous_end
        )));

        // Calculate percentage change
        $apps_change = $previous_apps > 0
            ? round((($current_apps - $previous_apps) / $previous_apps) * 100)
            : ($current_apps > 0 ? 100 : 0);

        // Similar for interactions
        $current_interactions = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_interactions}
             WHERE user_id = %d AND interaction_date >= %s",
            $user_id,
            $current_start
        )));

        $previous_interactions = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_interactions}
             WHERE user_id = %d AND interaction_date >= %s AND interaction_date < %s",
            $user_id,
            $previous_start,
            $previous_end
        )));

        $interactions_change = $previous_interactions > 0
            ? round((($current_interactions - $previous_interactions) / $previous_interactions) * 100)
            : ($current_interactions > 0 ? 100 : 0);

        return array(
            'applications' => array(
                'current' => $current_apps,
                'previous' => $previous_apps,
                'change' => $apps_change
            ),
            'interactions' => array(
                'current' => $current_interactions,
                'previous' => $previous_interactions,
                'change' => $interactions_change
            )
        );
    }

    /**
     * Get industry distribution from applications
     */
    public function get_industry_distribution($user_id) {
        global $wpdb;

        // This requires job posts to have industry meta
        // Return placeholder structure for now
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT ja.job_id, p.ID
             FROM {$this->table_applications} ja
             LEFT JOIN {$wpdb->posts} p ON ja.job_id = p.ID
             WHERE ja.user_id = %d",
            $user_id
        ), ARRAY_A);

        $industries = array();

        foreach ($results as $row) {
            $industry = get_post_meta($row['job_id'], 'sffc_industry', true);
            if (!$industry) {
                $industry = 'Other';
            }

            if (!isset($industries[$industry])) {
                $industries[$industry] = 0;
            }
            $industries[$industry]++;
        }

        // Sort by count descending
        arsort($industries);

        return $industries;
    }

    /**
     * Get location distribution from applications
     * Falls back to job post meta if application location is empty
     */
    public function get_location_distribution($user_id) {
        global $wpdb;

        // First, get applications with their job IDs
        $applications = $wpdb->get_results($wpdb->prepare(
            "SELECT job_id, location, stage FROM {$this->table_applications} WHERE user_id = %d",
            $user_id
        ), ARRAY_A);

        $location_counts = array();
        $location_high_matches = array();

        foreach ($applications as $app) {
            $location = $app['location'];

            // If location is empty in applications table, try to get from job post meta
            if (empty($location) && !empty($app['job_id'])) {
                $location = $this->get_job_location($app['job_id']);
            }

            if (empty($location)) {
                continue;
            }

            // Extract country code
            $country_code = $this->extract_country_code($location);

            if (!isset($location_counts[$country_code])) {
                $location_counts[$country_code] = 0;
                $location_high_matches[$country_code] = 0;
            }

            $location_counts[$country_code]++;

            // Count high matches (interviews and secured)
            if (in_array($app['stage'], array('first-interview', 'further-interview', 'secured'))) {
                $location_high_matches[$country_code]++;
            }
        }

        // Sort by count descending
        arsort($location_counts);

        // Calculate total and build distribution array
        $total = array_sum($location_counts);
        $distribution = array();

        foreach (array_slice($location_counts, 0, 10, true) as $country_code => $count) {
            $distribution[] = array(
                'location' => $this->get_country_name($country_code),
                'country_code' => $country_code,
                'count' => $count,
                'share' => $total > 0 ? round(($count / $total) * 100, 1) : 0,
                'high_matches' => isset($location_high_matches[$country_code]) ? $location_high_matches[$country_code] : 0
            );
        }

        return $distribution;
    }

    /**
     * Get location from job post meta fields
     * Checks sffc_location, sffc_location_city, sffc_location_country
     */
    private function get_job_location($job_id) {
        // Try different location meta fields
        $location_fields = array(
            'sffc_location',
            'sffc_location_city',
            'sffc_location_country',
            '_sffc_location',
            'sffc_job_location',
            'location'
        );

        foreach ($location_fields as $field) {
            $value = get_post_meta($job_id, $field, true);
            if (!empty($value)) {
                return $value;
            }
        }

        return '';
    }

    /**
     * Get country name from country code
     */
    private function get_country_name($code) {
        $countries = array(
            'US' => 'United States',
            'GB' => 'United Kingdom',
            'AE' => 'UAE',
            'SA' => 'Saudi Arabia',
            'QA' => 'Qatar',
            'KW' => 'Kuwait',
            'BH' => 'Bahrain',
            'OM' => 'Oman',
            'ZA' => 'South Africa',
            'DE' => 'Germany',
            'FR' => 'France',
            'ES' => 'Spain',
            'IT' => 'Italy',
            'PT' => 'Portugal',
            'NL' => 'Netherlands',
            'BE' => 'Belgium',
            'CH' => 'Switzerland',
            'IE' => 'Ireland',
            'LU' => 'Luxembourg',
            'SG' => 'Singapore',
            'HK' => 'Hong Kong',
            'AU' => 'Australia',
            'CA' => 'Canada',
            'EG' => 'Egypt',
            'NG' => 'Nigeria',
            'KE' => 'Kenya',
            'IN' => 'India',
            'JP' => 'Japan',
            'CN' => 'China',
            'XX' => 'Other'
        );

        return isset($countries[$code]) ? $countries[$code] : $code;
    }

    /**
     * Get all job locations from sffc_job posts for chart display
     * This scans the job post meta fields to build location distribution
     */
    public function get_all_job_locations_distribution($limit = 10) {
        global $wpdb;

        // Get all published sffc_job posts with location meta
        $jobs = $wpdb->get_results(
            "SELECT p.ID,
                    pm1.meta_value as sffc_location,
                    pm2.meta_value as sffc_location_city,
                    pm3.meta_value as sffc_location_country
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = 'sffc_location'
             LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'sffc_location_city'
             LEFT JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = 'sffc_location_country'
             WHERE p.post_type = 'sffc_job' AND p.post_status = 'publish'",
            ARRAY_A
        );

        $location_counts = array();

        foreach ($jobs as $job) {
            // Try to get location from available fields
            $location = '';
            if (!empty($job['sffc_location'])) {
                $location = $job['sffc_location'];
            } elseif (!empty($job['sffc_location_city'])) {
                $location = $job['sffc_location_city'];
                if (!empty($job['sffc_location_country'])) {
                    $location .= ', ' . $job['sffc_location_country'];
                }
            } elseif (!empty($job['sffc_location_country'])) {
                $location = $job['sffc_location_country'];
            }

            if (empty($location)) {
                continue;
            }

            $country_code = $this->extract_country_code($location);

            if (!isset($location_counts[$country_code])) {
                $location_counts[$country_code] = 0;
            }
            $location_counts[$country_code]++;
        }

        // Sort by count descending
        arsort($location_counts);

        // Calculate total and build distribution
        $total = array_sum($location_counts);
        $distribution = array();

        foreach (array_slice($location_counts, 0, $limit, true) as $country_code => $count) {
            $distribution[] = array(
                'location' => $this->get_country_name($country_code),
                'country_code' => $country_code,
                'count' => $count,
                'share' => $total > 0 ? round(($count / $total) * 100, 1) : 0
            );
        }

        return $distribution;
    }

    /**
     * Extract country code from location string
     * Comprehensive mapping of cities, regions, and countries to ISO country codes
     */
    private function extract_country_code($location) {
        if (empty($location)) {
            return 'XX';
        }

        $location = strtolower(trim($location));

        // United States - States and major cities
        $us_locations = array(
            // Country/Region names
            'united states', 'usa', 'u.s.a', 'u.s.', 'america',
            // States
            'alabama', 'alaska', 'arizona', 'arkansas', 'california', 'colorado',
            'connecticut', 'delaware', 'florida', 'georgia', 'hawaii', 'idaho',
            'illinois', 'indiana', 'iowa', 'kansas', 'kentucky', 'louisiana',
            'maine', 'maryland', 'massachusetts', 'michigan', 'minnesota',
            'mississippi', 'missouri', 'montana', 'nebraska', 'nevada',
            'new hampshire', 'new jersey', 'new mexico', 'new york', 'north carolina',
            'north dakota', 'ohio', 'oklahoma', 'oregon', 'pennsylvania',
            'rhode island', 'south carolina', 'south dakota', 'tennessee', 'texas',
            'utah', 'vermont', 'virginia', 'washington', 'west virginia',
            'wisconsin', 'wyoming',
            // State abbreviations
            'al', 'ak', 'az', 'ar', 'ca', 'co', 'ct', 'de', 'fl', 'ga', 'hi', 'id',
            'il', 'in', 'ia', 'ks', 'ky', 'la', 'me', 'md', 'ma', 'mi', 'mn', 'ms',
            'mo', 'mt', 'ne', 'nv', 'nh', 'nj', 'nm', 'ny', 'nc', 'nd', 'oh', 'ok',
            'or', 'pa', 'ri', 'sc', 'sd', 'tn', 'tx', 'ut', 'vt', 'va', 'wa', 'wv',
            'wi', 'wy',
            // Major cities
            'new york city', 'nyc', 'manhattan', 'brooklyn', 'queens',
            'los angeles', 'la', 'hollywood', 'beverly hills',
            'chicago', 'houston', 'phoenix', 'philadelphia', 'san antonio',
            'san diego', 'dallas', 'san jose', 'austin', 'jacksonville',
            'fort worth', 'columbus', 'charlotte', 'san francisco', 'sf',
            'indianapolis', 'seattle', 'denver', 'boston', 'nashville',
            'detroit', 'portland', 'las vegas', 'memphis', 'louisville',
            'baltimore', 'milwaukee', 'albuquerque', 'tucson', 'fresno',
            'sacramento', 'atlanta', 'miami', 'oakland', 'minneapolis',
            'stamford', 'greenwich', 'jersey city', 'hoboken', 'silicon valley',
            'palo alto', 'menlo park', 'mountain view', 'cupertino'
        );

        // United Kingdom - Cities and regions
        $uk_locations = array(
            // Country/Region names
            'united kingdom', 'uk', 'u.k.', 'britain', 'great britain', 'england',
            'scotland', 'wales', 'northern ireland',
            // Major cities
            'london', 'birmingham', 'leeds', 'glasgow', 'sheffield', 'manchester',
            'edinburgh', 'liverpool', 'bristol', 'cardiff', 'belfast', 'leicester',
            'wakefield', 'bradford', 'nottingham', 'newcastle', 'kingston upon hull',
            'stoke-on-trent', 'southampton', 'derby', 'portsmouth', 'brighton',
            'plymouth', 'northampton', 'reading', 'luton', 'wolverhampton',
            'bolton', 'aberdeen', 'bournemouth', 'norwich', 'swindon', 'oxford',
            'cambridge', 'york', 'peterborough', 'dundee', 'coventry',
            // Financial centers
            'canary wharf', 'city of london', 'mayfair', 'bank'
        );

        // UAE and private equity
        $ae_locations = array(
            'uae', 'u.a.e.', 'united arab emirates', 'emirates',
            'dubai', 'abu dhabi', 'sharjah', 'ajman', 'fujairah', 'ras al khaimah',
            'umm al quwain', 'difc', 'jebel ali'
        );

        $sa_locations = array(
            'saudi arabia', 'ksa', 'saudi', 'riyadh', 'jeddah', 'mecca', 'medina',
            'dammam', 'khobar', 'dhahran', 'jeddah'
        );

        $qa_locations = array(
            'qatar', 'doha', 'lusail', 'west bay'
        );

        $kw_locations = array(
            'kuwait', 'kuwait city'
        );

        $bh_locations = array(
            'bahrain', 'manama'
        );

        $om_locations = array(
            'oman', 'muscat'
        );

        // South Africa
        $za_locations = array(
            'south africa', 'sa', 'rsa',
            'johannesburg', 'joburg', 'jozi', 'cape town', 'durban', 'pretoria',
            'port elizabeth', 'bloemfontein', 'sandton', 'centurion', 'midrand'
        );

        // Germany
        $de_locations = array(
            'germany', 'deutschland',
            'berlin', 'hamburg', 'munich', 'münchen', 'cologne', 'köln',
            'frankfurt', 'stuttgart', 'düsseldorf', 'dusseldorf', 'dortmund',
            'essen', 'leipzig', 'bremen', 'dresden', 'hanover', 'nuremberg'
        );

        // France
        $fr_locations = array(
            'france', 'paris', 'marseille', 'lyon', 'toulouse', 'nice', 'nantes',
            'strasbourg', 'montpellier', 'bordeaux', 'lille', 'la défense', 'la defense'
        );

        // Spain
        $es_locations = array(
            'spain', 'españa', 'espana',
            'madrid', 'barcelona', 'valencia', 'seville', 'sevilla', 'malaga',
            'bilbao', 'alicante', 'zaragoza', 'murcia', 'palma'
        );

        // Italy
        $it_locations = array(
            'italy', 'italia',
            'rome', 'roma', 'milan', 'milano', 'naples', 'napoli', 'turin', 'torino',
            'palermo', 'genoa', 'genova', 'bologna', 'florence', 'firenze', 'venice', 'venezia'
        );

        // Portugal
        $pt_locations = array(
            'portugal', 'lisbon', 'lisboa', 'porto', 'braga', 'faro', 'coimbra'
        );

        // Netherlands
        $nl_locations = array(
            'netherlands', 'holland', 'the netherlands',
            'amsterdam', 'rotterdam', 'the hague', 'den haag', 'utrecht', 'eindhoven',
            'tilburg', 'groningen', 'almere', 'breda'
        );

        // Belgium
        $be_locations = array(
            'belgium', 'brussels', 'bruxelles', 'antwerp', 'antwerpen', 'ghent', 'gent',
            'charleroi', 'liege', 'bruges'
        );

        // Switzerland
        $ch_locations = array(
            'switzerland', 'swiss', 'suisse', 'schweiz',
            'zurich', 'zürich', 'geneva', 'genève', 'geneve', 'basel', 'bern',
            'lausanne', 'winterthur', 'lucerne', 'lugano', 'zug'
        );

        // Ireland
        $ie_locations = array(
            'ireland', 'eire', 'éire',
            'dublin', 'cork', 'limerick', 'galway', 'waterford'
        );

        // Luxembourg
        $lu_locations = array(
            'luxembourg', 'luxemburg'
        );

        // Singapore
        $sg_locations = array(
            'singapore', 'sg'
        );

        // Hong Kong
        $hk_locations = array(
            'hong kong', 'hk', 'kowloon', 'central'
        );

        // Australia
        $au_locations = array(
            'australia', 'aus',
            'sydney', 'melbourne', 'brisbane', 'perth', 'adelaide', 'gold coast',
            'canberra', 'newcastle', 'hobart', 'darwin'
        );

        // Canada
        $ca_locations = array(
            'canada',
            'toronto', 'montreal', 'vancouver', 'calgary', 'edmonton', 'ottawa',
            'winnipeg', 'quebec city', 'hamilton', 'kitchener'
        );

        // Egypt
        $eg_locations = array(
            'egypt', 'cairo', 'alexandria', 'giza'
        );

        // Nigeria
        $ng_locations = array(
            'nigeria', 'lagos', 'abuja', 'port harcourt', 'ibadan'
        );

        // Kenya
        $ke_locations = array(
            'kenya', 'nairobi', 'mombasa'
        );

        // India
        $in_locations = array(
            'india',
            'mumbai', 'bombay', 'delhi', 'new delhi', 'bangalore', 'bengaluru',
            'hyderabad', 'chennai', 'madras', 'kolkata', 'calcutta', 'pune',
            'ahmedabad', 'gurgaon', 'gurugram', 'noida'
        );

        // Japan
        $jp_locations = array(
            'japan', 'tokyo', 'osaka', 'yokohama', 'nagoya', 'kyoto', 'fukuoka'
        );

        // China
        $cn_locations = array(
            'china', 'prc',
            'beijing', 'shanghai', 'shenzhen', 'guangzhou', 'hangzhou', 'chengdu'
        );

        // Check each country's locations
        $country_checks = array(
            'US' => $us_locations,
            'GB' => $uk_locations,
            'AE' => $ae_locations,
            'SA' => $sa_locations,
            'QA' => $qa_locations,
            'KW' => $kw_locations,
            'BH' => $bh_locations,
            'OM' => $om_locations,
            'ZA' => $za_locations,
            'DE' => $de_locations,
            'FR' => $fr_locations,
            'ES' => $es_locations,
            'IT' => $it_locations,
            'PT' => $pt_locations,
            'NL' => $nl_locations,
            'BE' => $be_locations,
            'CH' => $ch_locations,
            'IE' => $ie_locations,
            'LU' => $lu_locations,
            'SG' => $sg_locations,
            'HK' => $hk_locations,
            'AU' => $au_locations,
            'CA' => $ca_locations,
            'EG' => $eg_locations,
            'NG' => $ng_locations,
            'KE' => $ke_locations,
            'IN' => $in_locations,
            'JP' => $jp_locations,
            'CN' => $cn_locations
        );

        foreach ($country_checks as $code => $locations) {
            foreach ($locations as $loc) {
                // Check for exact word match to avoid false positives
                if (preg_match('/\b' . preg_quote($loc, '/') . '\b/i', $location)) {
                    return $code;
                }
            }
        }

        return 'XX'; // Unknown
    }

    /**
     * Get seniority distribution from applications
     */
    public function get_seniority_distribution($user_id) {
        global $wpdb;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT job_id FROM {$this->table_applications} WHERE user_id = %d",
            $user_id
        ), ARRAY_A);

        $seniority = array(
            'Entry Level' => 0,
            'Mid Level' => 0,
            'Senior' => 0,
            'Manager' => 0,
            'Director' => 0,
            'VP+' => 0
        );

        foreach ($results as $row) {
            $level = get_post_meta($row['job_id'], 'sffc_seniority', true);
            if ($level && isset($seniority[$level])) {
                $seniority[$level]++;
            }
        }

        return $seniority;
    }

    // =========================================================================
    // ACTIVITY LOGGING
    // =========================================================================

    /**
     * Log user activity
     */
    public function log_activity($user_id, $type, $data = array(), $job_id = null) {
        global $wpdb;

        return $wpdb->insert(
            $this->table_activity,
            array(
                'user_id' => $user_id,
                'activity_type' => $type,
                'activity_data' => json_encode($data),
                'job_id' => $job_id,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%d', '%s')
        );
    }

    /**
     * Get recent activity
     */
    public function get_recent_activity($user_id, $limit = 20) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_activity}
             WHERE user_id = %d
             ORDER BY created_at DESC
             LIMIT %d",
            $user_id,
            $limit
        ), ARRAY_A);
    }

    // =========================================================================
    // SNAPSHOTS (Historical Data)
    // =========================================================================

    /**
     * Save daily snapshot for a user
     */
    public function save_daily_snapshot($user_id) {
        global $wpdb;

        $today = date('Y-m-d');
        $pipeline = $this->get_pipeline_stats($user_id);
        $saved_count = $this->get_saved_jobs_count($user_id);
        $high_matches = $this->get_high_match_count($user_id);

        return $wpdb->replace(
            $this->table_snapshots,
            array(
                'user_id' => $user_id,
                'snapshot_date' => $today,
                'total_applications' => $pipeline['total'],
                'stage_applied' => isset($pipeline['applied']) ? $pipeline['applied'] : 0,
                'stage_waiting' => isset($pipeline['waiting']) ? $pipeline['waiting'] : 0,
                'stage_first_interview' => isset($pipeline['first-interview']) ? $pipeline['first-interview'] : 0,
                'stage_further_interview' => isset($pipeline['further-interview']) ? $pipeline['further-interview'] : 0,
                'stage_secured' => isset($pipeline['secured']) ? $pipeline['secured'] : 0,
                'stage_moved_on' => isset($pipeline['moved-on']) ? $pipeline['moved-on'] : 0,
                'high_matches' => $high_matches,
                'saved_jobs' => $saved_count,
                'metrics_json' => json_encode(array(
                    'pipeline' => $pipeline,
                    'interactions' => $this->get_interaction_counts($user_id)
                ))
            ),
            array('%d', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s')
        );
    }

    /**
     * Get historical snapshots for sparklines
     */
    public function get_historical_snapshots($user_id, $days = 30) {
        global $wpdb;

        $start_date = date('Y-m-d', strtotime("-{$days} days"));

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_snapshots}
             WHERE user_id = %d AND snapshot_date >= %s
             ORDER BY snapshot_date ASC",
            $user_id,
            $start_date
        ), ARRAY_A);
    }

    /**
     * Get sparkline data for a specific metric
     */
    public function get_sparkline_data($user_id, $metric = 'total_applications', $days = 14) {
        $snapshots = $this->get_historical_snapshots($user_id, $days);

        $data = array();
        foreach ($snapshots as $snapshot) {
            $data[] = isset($snapshot[$metric]) ? intval($snapshot[$metric]) : 0;
        }

        // Pad with zeros if not enough data
        while (count($data) < $days) {
            array_unshift($data, 0);
        }

        return $data;
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Get job details for denormalized storage
     */
    private function get_job_details($job_id) {
        $post = get_post($job_id);

        if (!$post) {
            return array(
                'title' => '',
                'company' => '',
                'location' => '',
                'salary_min' => null,
                'salary_max' => null
            );
        }

        $meta = get_post_meta($job_id);

        return array(
            'title' => $post->post_title,
            'company' => isset($meta['sffc_company'][0]) ? $meta['sffc_company'][0] :
                        (isset($meta['_sffc_company_name'][0]) ? $meta['_sffc_company_name'][0] : ''),
            'location' => isset($meta['sffc_location'][0]) ? $meta['sffc_location'][0] : '',
            'salary_min' => isset($meta['sffc_salary_min'][0]) ? intval($meta['sffc_salary_min'][0]) : null,
            'salary_max' => isset($meta['sffc_salary_max'][0]) ? intval($meta['sffc_salary_max'][0]) : null
        );
    }

    /**
     * Get valid stages
     */
    public static function get_stages() {
        return self::STAGES;
    }

    /**
     * Get stage label
     */
    public static function get_stage_label($stage) {
        return isset(self::STAGES[$stage]) ? self::STAGES[$stage] : 'Unknown';
    }
}
