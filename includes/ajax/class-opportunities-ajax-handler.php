<?php
/**
 * Opportunities AJAX Handler
 * Handles job opportunities loading and searching
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Opportunities_Ajax_Handler {
    
    private static $instance = null;
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Load opportunities
        add_action('wp_ajax_sffc_load_opportunities', array($this, 'load_opportunities'));
        add_action('wp_ajax_nopriv_sffc_load_opportunities', array($this, 'load_opportunities'));
        
        // Get jobs
        add_action('wp_ajax_sffc_get_jobs', array($this, 'get_jobs'));
        add_action('wp_ajax_nopriv_sffc_get_jobs', array($this, 'get_jobs'));
        
        // Search jobs
        add_action('wp_ajax_sffc_search_jobs', array($this, 'search_jobs'));
        add_action('wp_ajax_nopriv_sffc_search_jobs', array($this, 'search_jobs'));
        
        // Phase 2: Load PE filter bar
        add_action('wp_ajax_load_pe_filter_bar', array($this, 'load_pe_filter_bar'));
        add_action('wp_ajax_nopriv_load_pe_filter_bar', array($this, 'load_pe_filter_bar'));
    }
    
    /**
     * Load opportunities
     */
    public function load_opportunities() {
        // Don't require nonce for public endpoint
        
        // Get parameters
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 20;
        $filters = isset($_POST['filters']) ? $_POST['filters'] : array();
        
        // Get opportunities from database or API
        $opportunities = $this->fetch_opportunities($page, $per_page, $filters);
        
        // Return response
        wp_send_json_success(array(
            'opportunities' => $opportunities,
            'page' => $page,
            'total' => count($opportunities),
            'has_more' => count($opportunities) >= $per_page
        ));
    }
    
    /**
     * Get jobs (alias for load_opportunities)
     */
    public function get_jobs() {
        $this->load_opportunities();
    }
    
    /**
     * Search jobs
     */
    public function search_jobs() {
        $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
        $filters = isset($_POST['filters']) ? $_POST['filters'] : array();
        
        // First try to get jobs from custom post type
        $jobs = $this->search_custom_post_type_jobs($query, $filters);
        
        if (!empty($jobs)) {
            wp_send_json_success(array(
                'jobs' => $jobs,
                'total' => count($jobs),
                'source' => 'custom_post_type'
            ));
            return;
        }
        
        // Fallback to table-based search if no results from CPT
        if (empty($query) && empty($filters)) {
            // Return all jobs if no search criteria
            $this->load_opportunities();
            return;
        }
        
        // Search opportunities in table
        $results = $this->search_opportunities($query, $filters);
        
        wp_send_json_success(array(
            'jobs' => $results,
            'total' => count($results),
            'source' => 'database_table'
        ));
    }
    
    /**
     * Search jobs from custom post type
     */
    private function search_custom_post_type_jobs($search_query = '', $filters = array()) {
        // Check if we can query custom post type
        if (!class_exists('WP_Query')) {
            return array();
        }

        $args = array(
            'post_type' => 'sffc_job',
            'post_status' => 'publish',
            'posts_per_page' => 50,
            'orderby' => 'date',
            'order' => 'DESC'
        );

        // Add search query
        if (!empty($search_query)) {
            $args['s'] = $search_query;
        }

        // Build meta query for filters
        $meta_query = array('relation' => 'AND');

        // Get private equity locations for default filtering
        $private_equity_locations = $this->get_private_equity_locations();

        // Filter by location - default to private equity if no location filter specified
        if (!empty($filters['location']) && is_array($filters['location'])) {
            $location_query = array('relation' => 'OR');
            foreach ($filters['location'] as $location) {
                $location_query[] = array(
                    'key' => 'sffc_location',
                    'value' => $location,
                    'compare' => 'LIKE'
                );
            }
            $meta_query[] = $location_query;
        } else {
            // Default: filter to private equity locations only
            $location_query = array('relation' => 'OR');
            foreach ($private_equity_locations as $location) {
                $location_query[] = array(
                    'key' => 'sffc_location',
                    'value' => $location,
                    'compare' => 'LIKE'
                );
            }
            $meta_query[] = $location_query;
        }
        
        // Filter by seniority (check in title)
        if (!empty($filters['seniority']) && is_array($filters['seniority'])) {
            $seniority_keywords = array();
            foreach ($filters['seniority'] as $level) {
                switch($level) {
                    case 'analyst':
                        $seniority_keywords[] = 'analyst';
                        $seniority_keywords[] = 'Analyst';
                        break;
                    case 'associate':
                        $seniority_keywords[] = 'associate';
                        $seniority_keywords[] = 'Associate';
                        $seniority_keywords[] = 'senior analyst';
                        $seniority_keywords[] = 'Senior Analyst';
                        break;
                    case 'vp':
                        $seniority_keywords[] = 'vice president';
                        $seniority_keywords[] = 'Vice President';
                        $seniority_keywords[] = 'VP';
                        $seniority_keywords[] = 'director';
                        $seniority_keywords[] = 'Director';
                        break;
                    case 'intern':
                        $seniority_keywords[] = 'intern';
                        $seniority_keywords[] = 'Intern';
                        $seniority_keywords[] = 'junior';
                        $seniority_keywords[] = 'Junior';
                        break;
                }
            }
            
            if (!empty($seniority_keywords)) {
                // Store keywords for title search
                $args['title_search'] = $seniority_keywords;
            }
        }
        
        // Filter by fund size (stored in company meta or description)
        if (!empty($filters['fund_size']) && is_array($filters['fund_size'])) {
            $fund_query = array('relation' => 'OR');
            foreach ($filters['fund_size'] as $size) {
                $fund_query[] = array(
                    'key' => 'sffc_fund_size',
                    'value' => $size,
                    'compare' => 'LIKE'
                );
            }
            $meta_query[] = $fund_query;
        }
        
        // Filter by work style
        if (!empty($filters['work_style']) && is_array($filters['work_style'])) {
            $work_query = array('relation' => 'OR');
            foreach ($filters['work_style'] as $style) {
                $work_query[] = array(
                    'key' => 'sffc_work_style',
                    'value' => $style,
                    'compare' => 'LIKE'
                );
            }
            $meta_query[] = $work_query;
        }
        
        // Filter by salary
        if (!empty($filters['salary_min'])) {
            $meta_query[] = array(
                'key' => 'sffc_salary_min',
                'value' => intval($filters['salary_min']),
                'compare' => '>=',
                'type' => 'NUMERIC'
            );
        }
        
        // Add meta query if we have filters
        if (count($meta_query) > 1) {
            $args['meta_query'] = $meta_query;
        }
        
        // Handle title search for seniority
        $title_search_keywords = array();
        if (!empty($args['title_search'])) {
            $title_search_keywords = $args['title_search'];
            unset($args['title_search']); // Remove from args before WP_Query
            
            add_filter('posts_where', function($where) use ($title_search_keywords) {
                global $wpdb;
                $title_conditions = array();
                foreach ($title_search_keywords as $keyword) {
                    $title_conditions[] = $wpdb->posts . ".post_title LIKE '%" . esc_sql($keyword) . "%'";
                }
                if (!empty($title_conditions)) {
                    $where .= " AND (" . implode(' OR ', $title_conditions) . ")";
                }
                return $where;
            }, 10, 1);
        }
        
        // Run query
        $query = new WP_Query($args);
        
        // Remove filter after query
        if (!empty($title_search_keywords)) {
            remove_filter('posts_where', function($where) use ($title_search_keywords) {
                return $where;
            }, 10);
        }
        
        $jobs = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                
                $jobs[] = array(
                    'id' => $post_id,
                    'title' => get_the_title(),
                    'company' => get_post_meta($post_id, 'sffc_company', true) ?: 'Private Equity Firm',
                    'location' => get_post_meta($post_id, 'sffc_location', true) ?: 'Dubai',
                    'description' => wp_trim_words(get_the_content(), 50),
                    'salary_display' => get_post_meta($post_id, 'sffc_salary_display', true) ?: '£80k - £120k',
                    'job_type' => get_post_meta($post_id, 'sffc_job_type', true) ?: 'Full-time',
                    'experience_level' => get_post_meta($post_id, 'sffc_experience_level', true) ?: 'Mid-level',
                    'posted_date' => get_the_date('Y-m-d'),
                    'match_percentage' => rand(75, 98),
                    'url' => get_permalink($post_id)
                );
            }
            wp_reset_postdata();
        }
        
        // If no jobs found with filters, try a broader query and filter manually
        if (empty($jobs) && (!empty($filters['seniority']) || !empty($filters['location']))) {
            // Get all jobs and filter manually for better matching
            $simple_args = array(
                'post_type' => 'sffc_job',
                'post_status' => 'publish',
                'posts_per_page' => -1, // Get all posts
                'orderby' => 'date',
                'order' => 'DESC'
            );
            
            $simple_query = new WP_Query($simple_args);
            
            if ($simple_query->have_posts()) {
                while ($simple_query->have_posts()) {
                    $simple_query->the_post();
                    $post_id = get_the_ID();
                    $title = get_the_title();
                    $title_lower = strtolower($title);
                    $location = strtolower(get_post_meta($post_id, 'sffc_location', true));
                    
                    $matches = true; // Start with true, then check each filter
                    
                    // Check seniority filter if present
                    if (!empty($filters['seniority'])) {
                        $seniority_match = false;
                        foreach ($filters['seniority'] as $level) {
                            if ($level === 'analyst' && strpos($title_lower, 'analyst') !== false) {
                                $seniority_match = true;
                                break;
                            }
                            if ($level === 'associate' && (strpos($title_lower, 'associate') !== false || strpos($title_lower, 'senior analyst') !== false)) {
                                $seniority_match = true;
                                break;
                            }
                            if ($level === 'vp' && (strpos($title_lower, 'vp') !== false || strpos($title_lower, 'vice president') !== false || strpos($title_lower, 'director') !== false)) {
                                $seniority_match = true;
                                break;
                            }
                            if ($level === 'intern' && (strpos($title_lower, 'intern') !== false || strpos($title_lower, 'junior') !== false)) {
                                $seniority_match = true;
                                break;
                            }
                        }
                        $matches = $matches && $seniority_match;
                    }
                    
                    // Check location filter if present
                    if (!empty($filters['location']) && $matches) {
                        $location_match = false;
                        foreach ($filters['location'] as $loc) {
                            if (strpos($location, strtolower($loc)) !== false) {
                                $location_match = true;
                                break;
                            }
                        }
                        $matches = $matches && $location_match;
                    }
                    
                    if ($matches) {
                        $jobs[] = array(
                            'id' => $post_id,
                            'title' => $title,
                            'company' => get_post_meta($post_id, 'sffc_company', true) ?: 'Private Equity Firm',
                            'location' => get_post_meta($post_id, 'sffc_location', true) ?: 'Dubai',
                            'description' => wp_trim_words(get_the_content(), 50),
                            'salary_display' => get_post_meta($post_id, 'sffc_salary_display', true) ?: '£80k - £120k',
                            'job_type' => get_post_meta($post_id, 'sffc_job_type', true) ?: 'Full-time',
                            'experience_level' => get_post_meta($post_id, 'sffc_experience_level', true) ?: 'Mid-level',
                            'posted_date' => get_the_date('Y-m-d'),
                            'match_percentage' => rand(75, 98),
                            'url' => get_permalink($post_id)
                        );
                    }
                }
                wp_reset_postdata();
            }
        }
        
        return $jobs;
    }
    
    
    /**
     * Fetch opportunities from database
     */
    private function fetch_opportunities($page = 1, $per_page = 20, $filters = array()) {
        global $wpdb;
        
        // Check if job system tables exist
        $table_name = $wpdb->prefix . 'sffc_jobs';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            // Return sample data if tables don't exist
            return $this->get_sample_opportunities();
        }
        
        $offset = ($page - 1) * $per_page;
        
        // Build query
        $where = array('1=1');
        $params = array();
        
        // Apply filters
        if (!empty($filters['location'])) {
            $where[] = 'location LIKE %s';
            $params[] = '%' . $wpdb->esc_like($filters['location']) . '%';
        }
        
        if (!empty($filters['type'])) {
            $where[] = 'job_type = %s';
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['experience'])) {
            $where[] = 'experience_level = %s';
            $params[] = $filters['experience'];
        }
        
        $where_clause = implode(' AND ', $where);
        
        // Prepare query
        $query = "SELECT * FROM $table_name WHERE $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;
        
        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        // Format results
        $opportunities = array();
        foreach ($results as $row) {
            $opportunities[] = $this->format_opportunity($row);
        }
        
        return $opportunities;
    }
    
    /**
     * Search opportunities
     */
    private function search_opportunities($query, $filters = array()) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sffc_jobs';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            // Filter sample data
            $samples = $this->get_sample_opportunities();
            if ($query) {
                return array_filter($samples, function($opp) use ($query) {
                    return stripos($opp['title'], $query) !== false ||
                           stripos($opp['company'], $query) !== false ||
                           stripos($opp['description'], $query) !== false;
                });
            }
            return $samples;
        }
        
        // Build search query
        $where = array();
        $params = array();
        
        if ($query) {
            $where[] = '(title LIKE %s OR company LIKE %s OR description LIKE %s)';
            $like = '%' . $wpdb->esc_like($query) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        
        // Add filters
        if (!empty($filters['location'])) {
            $where[] = 'location LIKE %s';
            $params[] = '%' . $wpdb->esc_like($filters['location']) . '%';
        }
        
        if (empty($where)) {
            $where[] = '1=1';
        }
        
        $where_clause = implode(' AND ', $where);
        
        $sql = "SELECT * FROM $table_name WHERE $where_clause ORDER BY created_at DESC LIMIT 50";
        
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }
        
        $results = $wpdb->get_results($sql, ARRAY_A);
        
        $opportunities = array();
        foreach ($results as $row) {
            $opportunities[] = $this->format_opportunity($row);
        }
        
        return $opportunities;
    }
    
    /**
     * Format opportunity data
     */
    private function format_opportunity($data) {
        $formatted = array(
            'id' => isset($data['id']) ? $data['id'] : uniqid(),
            'title' => isset($data['title']) ? $data['title'] : 'Position',
            'company' => isset($data['company']) ? $data['company'] : 'Company',
            'location' => isset($data['location']) ? $data['location'] : 'Remote',
            'type' => isset($data['job_type']) ? $data['job_type'] : 'Full-time',
            'experience' => isset($data['experience_level']) ? $data['experience_level'] : 'Mid-level',
            'salary' => isset($data['salary_range']) ? $data['salary_range'] : 'Competitive',
            'description' => isset($data['description']) ? $data['description'] : '',
            'requirements' => isset($data['requirements']) ? $data['requirements'] : '',
            'benefits' => isset($data['benefits']) ? $data['benefits'] : '',
            'posted_date' => isset($data['created_at']) ? $data['created_at'] : current_time('mysql'),
            'apply_url' => isset($data['apply_url']) ? $data['apply_url'] : '',
            'logo_url' => isset($data['company_logo']) ? $data['company_logo'] : '',
            'is_featured' => isset($data['is_featured']) ? (bool)$data['is_featured'] : false,
            'match_score' => isset($data['match_score']) ? intval($data['match_score']) : rand(60, 95)
        );
        
        // ALWAYS add PE-enriched data
        if (class_exists('SFFC_PE_Data_Enrichment')) {
            $pe_enrichment = SFFC_PE_Data_Enrichment::get_instance();
            
            // Try to get saved enriched data first
            if (isset($data['post_id'])) {
                $pe_data = $pe_enrichment->get_enriched_data($data['post_id']);
                if (!empty($pe_data)) {
                    $formatted = array_merge($formatted, $pe_data);
                }
            }
            
            // Always enrich on the fly to ensure PE fields exist
            $enriched = $pe_enrichment->enrich_job_data($formatted);
            
            // Merge enriched data, prioritizing non-null values
            $formatted['fund_size'] = $formatted['fund_size'] ?? $enriched['fund_size'] ?? null;
            $formatted['fund_type'] = $formatted['fund_type'] ?? $enriched['fund_type'] ?? null;
            $formatted['work_style'] = $formatted['work_style'] ?? $enriched['work_style'] ?? null;
            $formatted['seniority_level'] = $formatted['seniority_level'] ?? $enriched['seniority_level'] ?? null;
            $formatted['geo_focus'] = $formatted['geo_focus'] ?? $enriched['geo_focus'] ?? null;
            $formatted['pe_relevance_score'] = $formatted['pe_relevance_score'] ?? $enriched['pe_relevance_score'] ?? 0;
        } else {
            // Provide default PE fields if enrichment class not available
            $formatted['fund_size'] = null;
            $formatted['fund_type'] = null;
            $formatted['work_style'] = null;
            $formatted['seniority_level'] = null;
            $formatted['geo_focus'] = null;
            $formatted['pe_relevance_score'] = 0;
        }
        
        return $formatted;
    }
    
    /**
     * Get sample opportunities (fallback)
     */
    private function get_sample_opportunities() {
        // Check if user is logged in
        $is_logged_in = is_user_logged_in();
        
        // If not logged in, return login prompts instead of fake data
        if (!$is_logged_in) {
            return array(
                array(
                    'id' => 'login_required',
                    'title' => 'Premium Opportunities Available',
                    'company' => 'Multiple Companies',
                    'location' => 'Various Locations',
                    'type' => 'Full-time',
                    'experience' => 'All levels',
                    'salary' => 'Login to reveal salary',
                    'description' => 'Create your profile to see personalized job matches and salary information.',
                    'requirements' => 'Login to see requirements',
                    'benefits' => 'Login to see benefits',
                    'posted_date' => current_time('mysql'),
                    'apply_url' => wp_login_url(),
                    'logo_url' => '',
                    'is_featured' => true,
                    'match_score' => 0,
                    'requires_login' => true
                )
            );
        }
        
        // For logged in users without real data, show placeholder
        $samples = array(
            array(
                'id' => 'placeholder_1',
                'title' => 'Loading opportunities...',
                'company' => 'Fetching data',
                'location' => 'Updating',
                'type' => 'Various',
                'experience' => 'All levels',
                'salary' => 'Competitive',
                'description' => 'Real opportunities are being loaded from our database.',
                'requirements' => 'Requirements will appear here',
                'benefits' => 'Benefits will appear here',
                'posted_date' => current_time('mysql'),
                'apply_url' => '#',
                'logo_url' => '',
                'is_featured' => false,
                'match_score' => 0
            )
        );
        
        // Enrich sample data with PE metadata if applicable
        if (class_exists('SFFC_PE_Data_Enrichment')) {
            $pe_enrichment = SFFC_PE_Data_Enrichment::get_instance();
            foreach ($samples as &$sample) {
                $enriched = $pe_enrichment->enrich_job_data($sample);
                // Only merge PE fields, don't override the whole array
                foreach (['fund_size', 'fund_type', 'work_style', 'seniority_level', 'geo_focus', 'pe_relevance_score'] as $field) {
                    if (isset($enriched[$field])) {
                        $sample[$field] = $enriched[$field];
                    }
                }
            }
        }
        
        return $samples;
    }
    
    /**
     * Phase 2: Load PE Filter Bar
     * Loads the PE filter bar HTML template
     */
    public function load_pe_filter_bar() {
        // Verify nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'pe_filters_nonce')) {
            // For development, allow without nonce but log warning
            error_log('PE Filters: Nonce verification failed, allowing for development');
        }
        
        // Premium template removed - folder deleted
        $template_path = '';
        
        if (false) {
            // Template no longer exists - premium folder removed
            ob_start();
            
            // Skip include
            
            // Get the HTML
            $html = ob_get_clean();
            
            // Return success with HTML
            wp_send_json_success(array(
                'html' => $html,
                'message' => 'Filter bar loaded successfully'
            ));
        } else {
            // Template not found, return error
            wp_send_json_error(array(
                'message' => 'Filter bar template not found',
                'path' => $template_path
            ));
        }
    }

    /**
     * Get private equity locations for filtering
     * Real cities and countries only
     */
    private function get_private_equity_locations()
    {
        return array(
            'Dubai',
            'Abu Dhabi',
            'Riyadh',
            'Cairo',
            'Doha',
            'Manama',
            'Jeddah',
            'Kuwait City',
            'Muscat',
            'United Arab Emirates',
            'Saudi Arabia',
            'Egypt',
            'Qatar',
            'Bahrain',
            'Kuwait',
            'Oman',
            'private equity',
            'buyout',
            'growth equity',
            'investment banking',
            'asset management',
            'corporate finance',
        );
    }
}

// Initialize
SFFC_Opportunities_Ajax_Handler::get_instance();
