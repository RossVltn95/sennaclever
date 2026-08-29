<?php
/**
 * Intro Submission Tracker
 *
 * Handles database tables and tracking for manual recruiter outreach.
 * Allows admin to log submissions and candidates to see who received their profile.
 *
 * @package SennaCareers
 * @since 12.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Intro_Submission_Tracker {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Database version
     */
    private $db_version = '1.0.0';

    /**
     * Get singleton instance
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
        add_action('init', [$this, 'maybe_create_tables']);

        // AJAX handlers for candidate-facing views
        add_action('wp_ajax_sffc_get_submissions', [$this, 'ajax_get_submissions']);
        add_action('wp_ajax_sffc_get_submission_detail', [$this, 'ajax_get_submission_detail']);
    }

    /**
     * Create tables if needed
     */
    public function maybe_create_tables() {
        $current_version = get_option('sffc_intro_submission_db_version', '0');

        if (version_compare($current_version, $this->db_version, '<')) {
            $this->create_tables();
            update_option('sffc_intro_submission_db_version', $this->db_version);
        }
    }

    /**
     * Create all required tables
     */
    public function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Create submissions table
        $this->create_submissions_table($charset_collate);

        // Create campaigns table
        $this->create_campaigns_table($charset_collate);

        // Create opportunities table
        $this->create_opportunities_table($charset_collate);

        // Extend recruiter_contacts table
        $this->extend_recruiter_contacts_table();
    }

    /**
     * Create submissions tracking table
     */
    private function create_submissions_table($charset_collate) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'sffc_intro_submissions';

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            campaign_id bigint(20) DEFAULT NULL,
            recruiter_id bigint(20) NOT NULL,
            status varchar(50) DEFAULT 'sent',
            sent_at datetime DEFAULT CURRENT_TIMESTAMP,
            viewed_at datetime DEFAULT NULL,
            responded_at datetime DEFAULT NULL,
            admin_notes text,
            created_by bigint(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY campaign_id (campaign_id),
            KEY recruiter_id (recruiter_id),
            KEY status (status),
            KEY sent_at (sent_at)
        ) $charset_collate;";

        dbDelta($sql);
    }

    /**
     * Create campaigns table
     */
    private function create_campaigns_table($charset_collate) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'sffc_intro_campaigns';

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            status varchar(50) DEFAULT 'active',
            target_roles longtext,
            target_industries longtext,
            target_locations longtext,
            salary_min int(11) DEFAULT NULL,
            salary_max int(11) DEFAULT NULL,
            availability varchar(100) DEFAULT NULL,
            pitch text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        dbDelta($sql);
    }

    /**
     * Create opportunities table
     */
    private function create_opportunities_table($charset_collate) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'sffc_opportunities';

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            campaign_id bigint(20) DEFAULT NULL,
            submission_id bigint(20) DEFAULT NULL,
            recruiter_id bigint(20) NOT NULL,
            job_id bigint(20) DEFAULT NULL,
            status varchar(50) DEFAULT 'new',
            opportunity_type varchar(50) DEFAULT 'general_interest',
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY campaign_id (campaign_id),
            KEY submission_id (submission_id),
            KEY recruiter_id (recruiter_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        dbDelta($sql);
    }

    /**
     * Extend recruiter_contacts table with new columns
     */
    private function extend_recruiter_contacts_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'sffc_recruiter_contacts';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            return; // Table doesn't exist yet
        }

        // Add new columns if they don't exist
        $columns_to_add = [
            'photo_url' => "VARCHAR(500) DEFAULT NULL",
            'linkedin_url' => "VARCHAR(500) DEFAULT NULL",
            'company_logo_url' => "VARCHAR(500) DEFAULT NULL",
            'specialties' => "LONGTEXT DEFAULT NULL",
            'typical_roles' => "LONGTEXT DEFAULT NULL",
            'bio' => "TEXT DEFAULT NULL"
        ];

        foreach ($columns_to_add as $column => $definition) {
            $column_exists = $wpdb->get_results($wpdb->prepare(
                "SHOW COLUMNS FROM $table_name LIKE %s",
                $column
            ));

            if (empty($column_exists)) {
                $wpdb->query("ALTER TABLE $table_name ADD COLUMN $column $definition");
            }
        }
    }

    /**
     * Get submissions for a user's campaign
     */
    public function get_user_submissions($user_id, $campaign_id = null, $args = []) {
        global $wpdb;

        $submissions_table = $wpdb->prefix . 'sffc_intro_submissions';
        $recruiters_table = $wpdb->prefix . 'sffc_recruiter_contacts';

        $defaults = [
            'limit' => 50,
            'offset' => 0,
            'status' => null,
            'order' => 'DESC'
        ];

        $args = wp_parse_args($args, $defaults);

        $where = ["s.user_id = %d"];
        $params = [$user_id];

        if ($campaign_id) {
            $where[] = "s.campaign_id = %d";
            $params[] = $campaign_id;
        }

        if ($args['status']) {
            $where[] = "s.status = %s";
            $params[] = $args['status'];
        }

        $where_clause = implode(' AND ', $where);
        $order = $args['order'] === 'ASC' ? 'ASC' : 'DESC';

        $query = $wpdb->prepare(
            "SELECT
                s.*,
                r.contact_name,
                r.contact_email,
                r.company_name as company,
                r.contact_role as recruiter_title,
                r.photo_url,
                COALESCE(r.linkedin_url, r.contact_linkedin) as linkedin_url,
                r.company_logo_url,
                r.specialties,
                r.typical_roles,
                r.bio
            FROM $submissions_table s
            LEFT JOIN $recruiters_table r ON s.recruiter_id = r.id
            WHERE $where_clause
            ORDER BY s.sent_at $order
            LIMIT %d OFFSET %d",
            array_merge($params, [$args['limit'], $args['offset']])
        );

        return $wpdb->get_results($query);
    }

    /**
     * Get submission counts by status for a user
     */
    public function get_submission_stats($user_id, $campaign_id = null) {
        global $wpdb;

        $table = $wpdb->prefix . 'sffc_intro_submissions';

        $where = "user_id = %d";
        $params = [$user_id];

        if ($campaign_id) {
            $where .= " AND campaign_id = %d";
            $params[] = $campaign_id;
        }

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT status, COUNT(*) as count
            FROM $table
            WHERE $where
            GROUP BY status",
            $params
        ));

        $stats = [
            'total' => 0,
            'sent' => 0,
            'viewed' => 0,
            'responded' => 0,
            'no_response' => 0,
            'declined' => 0
        ];

        foreach ($results as $row) {
            $stats[$row->status] = (int) $row->count;
            $stats['total'] += (int) $row->count;
        }

        return $stats;
    }

    /**
     * Get single submission with full recruiter details
     */
    public function get_submission($submission_id, $user_id = null) {
        global $wpdb;

        $submissions_table = $wpdb->prefix . 'sffc_intro_submissions';
        $recruiters_table = $wpdb->prefix . 'sffc_recruiter_contacts';

        $where = "s.id = %d";
        $params = [$submission_id];

        // Optionally verify user ownership
        if ($user_id) {
            $where .= " AND s.user_id = %d";
            $params[] = $user_id;
        }

        return $wpdb->get_row($wpdb->prepare(
            "SELECT
                s.*,
                r.contact_name,
                r.contact_email,
                r.company_name as company,
                r.contact_role as recruiter_title,
                r.photo_url,
                COALESCE(r.linkedin_url, r.contact_linkedin) as linkedin_url,
                r.company_logo_url,
                r.specialties,
                r.typical_roles,
                r.bio
            FROM $submissions_table s
            LEFT JOIN $recruiters_table r ON s.recruiter_id = r.id
            WHERE $where",
            $params
        ));
    }

    /**
     * AJAX: Get submissions for current user
     */
    public function ajax_get_submissions() {
        check_ajax_referer('sffc_public_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Not logged in']);
            return;
        }

        $user_id = get_current_user_id();
        $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : null;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : null;
        $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
        $per_page = 20;

        $submissions = $this->get_user_submissions($user_id, $campaign_id, [
            'status' => $status,
            'limit' => $per_page,
            'offset' => ($page - 1) * $per_page
        ]);

        $stats = $this->get_submission_stats($user_id, $campaign_id);

        // Format for frontend
        $formatted = array_map(function($sub) {
            return [
                'id' => $sub->id,
                'recruiter' => [
                    'id' => $sub->recruiter_id,
                    'name' => $sub->contact_name,
                    'title' => $sub->recruiter_title,
                    'company' => $sub->company,
                    'photo' => $sub->photo_url,
                    'linkedin' => $sub->linkedin_url,
                    'logo' => $sub->company_logo_url,
                    'specialties' => $sub->specialties ? json_decode($sub->specialties, true) : [],
                    'typical_roles' => $sub->typical_roles ? json_decode($sub->typical_roles, true) : []
                ],
                'status' => $sub->status,
                'sent_at' => $sub->sent_at,
                'viewed_at' => $sub->viewed_at,
                'responded_at' => $sub->responded_at,
                'time_ago' => human_time_diff(strtotime($sub->sent_at), current_time('timestamp')) . ' ago'
            ];
        }, $submissions);

        wp_send_json_success([
            'submissions' => $formatted,
            'stats' => $stats,
            'page' => $page,
            'has_more' => count($submissions) === $per_page
        ]);
    }

    /**
     * AJAX: Get single submission detail
     */
    public function ajax_get_submission_detail() {
        check_ajax_referer('sffc_public_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Not logged in']);
            return;
        }

        $submission_id = isset($_POST['submission_id']) ? intval($_POST['submission_id']) : 0;

        if (!$submission_id) {
            wp_send_json_error(['message' => 'Invalid submission ID']);
            return;
        }

        $user_id = get_current_user_id();
        $submission = $this->get_submission($submission_id, $user_id);

        if (!$submission) {
            wp_send_json_error(['message' => 'Submission not found']);
            return;
        }

        wp_send_json_success([
            'submission' => [
                'id' => $submission->id,
                'recruiter' => [
                    'id' => $submission->recruiter_id,
                    'name' => $submission->contact_name,
                    'title' => $submission->recruiter_title,
                    'company' => $submission->company,
                    'photo' => $submission->photo_url,
                    'linkedin' => $submission->linkedin_url,
                    'logo' => $submission->company_logo_url,
                    'specialties' => $submission->specialties ? json_decode($submission->specialties, true) : [],
                    'typical_roles' => $submission->typical_roles ? json_decode($submission->typical_roles, true) : [],
                    'bio' => $submission->bio
                ],
                'status' => $submission->status,
                'sent_at' => $submission->sent_at,
                'viewed_at' => $submission->viewed_at,
                'responded_at' => $submission->responded_at
            ]
        ]);
    }

    /**
     * Create a submission (admin use)
     */
    public function create_submission($data) {
        global $wpdb;

        $table = $wpdb->prefix . 'sffc_intro_submissions';

        $insert_data = [
            'user_id' => intval($data['user_id']),
            'campaign_id' => isset($data['campaign_id']) ? intval($data['campaign_id']) : null,
            'recruiter_id' => intval($data['recruiter_id']),
            'status' => 'sent',
            'sent_at' => current_time('mysql'),
            'admin_notes' => isset($data['notes']) ? sanitize_textarea_field($data['notes']) : null,
            'created_by' => get_current_user_id()
        ];

        $result = $wpdb->insert($table, $insert_data);

        if ($result === false) {
            return new WP_Error('db_error', 'Failed to create submission');
        }

        return $wpdb->insert_id;
    }

    /**
     * Update submission status (admin use)
     */
    public function update_submission_status($submission_id, $status, $notes = null) {
        global $wpdb;

        $table = $wpdb->prefix . 'sffc_intro_submissions';

        $update_data = ['status' => $status];

        // Set timestamp based on status
        if ($status === 'viewed') {
            $update_data['viewed_at'] = current_time('mysql');
        } elseif ($status === 'responded') {
            $update_data['responded_at'] = current_time('mysql');
        }

        if ($notes !== null) {
            $update_data['admin_notes'] = sanitize_textarea_field($notes);
        }

        return $wpdb->update(
            $table,
            $update_data,
            ['id' => $submission_id]
        );
    }
}

// Initialize
SFFC_Intro_Submission_Tracker::get_instance();
