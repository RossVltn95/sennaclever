<?php
/**
 * Recruiter Terminal Database Schema
 *
 * Handles table creation and database operations for the messaging-first
 * recruiter brief system.
 *
 * @package SennaFinanceCareer
 * @subpackage RecruiterTerminal
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Recruiter_Terminal_DB {

    const DB_VERSION = '2.1.0'; // Added external recruiters and user matches tables
    const DB_VERSION_OPTION = 'rt_db_version';
    private static $tables_exist_cache = null;

    /**
     * Get table names with prefix
     */
    public static function get_table_names() {
        global $wpdb;
        return array(
            // Core tables
            'briefs'              => $wpdb->prefix . 'rt_briefs',
            'responses'           => $wpdb->prefix . 'rt_responses',
            'notes'               => $wpdb->prefix . 'rt_notes',
            'analytics'           => $wpdb->prefix . 'rt_analytics',
            // v2.1: External recruiters and user matches
            'external_recruiters' => $wpdb->prefix . 'rt_external_recruiters',
            'user_matches'        => $wpdb->prefix . 'rt_user_matches',
            // Legacy tables (kept for backwards compatibility)
            'campaigns'           => $wpdb->prefix . 'rt_campaigns',
            'targets'             => $wpdb->prefix . 'rt_campaign_targets',
            'activity'            => $wpdb->prefix . 'rt_activity_log',
            'templates'           => $wpdb->prefix . 'rt_email_templates',
        );
    }

    /**
     * Create all tables
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $tables = self::get_table_names();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // =====================================================================
        // BRIEFS TABLE (replaces campaigns conceptually)
        // Stores recruiter job briefs that are sent to candidates
        // =====================================================================
        $sql_briefs = "CREATE TABLE {$tables['briefs']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            title varchar(255) NOT NULL,
            brief text NOT NULL,
            location varchar(255) DEFAULT '',
            sector varchar(255) DEFAULT '',
            salary_range varchar(255) DEFAULT '',
            parsed_criteria longtext,
            status varchar(20) NOT NULL DEFAULT 'draft',
            expires_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            submitted_at datetime DEFAULT NULL,
            approved_at datetime DEFAULT NULL,
            approved_by bigint(20) UNSIGNED DEFAULT NULL,
            rejection_reason text DEFAULT NULL,
            is_external tinyint(1) DEFAULT 0,
            external_recruiter_id bigint(20) UNSIGNED DEFAULT NULL,
            detected_skills text DEFAULT NULL,
            visibility varchar(20) DEFAULT 'matched',
            match_weight int(11) DEFAULT 50,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status),
            KEY expires_at (expires_at),
            KEY created_at (created_at),
            KEY is_external (is_external),
            KEY external_recruiter_id (external_recruiter_id),
            KEY visibility (visibility)
        ) $charset_collate;";

        // =====================================================================
        // RESPONSES TABLE
        // Stores candidate responses to briefs (no login required)
        // =====================================================================
        $sql_responses = "CREATE TABLE {$tables['responses']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            brief_id bigint(20) UNSIGNED NOT NULL,
            tracking_id varchar(64) NOT NULL,
            candidate_name varchar(255) NOT NULL,
            candidate_email varchar(255) NOT NULL,
            candidate_phone varchar(50) DEFAULT '',
            candidate_linkedin varchar(500) DEFAULT '',
            message text,
            status varchar(20) NOT NULL DEFAULT 'pending',
            starred tinyint(1) DEFAULT 0,
            archived tinyint(1) DEFAULT 0,
            viewed_at datetime DEFAULT NULL,
            responded_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY tracking_id (tracking_id),
            KEY brief_id (brief_id),
            KEY candidate_email (candidate_email),
            KEY status (status),
            KEY starred (starred),
            KEY created_at (created_at)
        ) $charset_collate;";

        // =====================================================================
        // NOTES TABLE
        // Stores recruiter private notes on candidate responses
        // =====================================================================
        $sql_notes = "CREATE TABLE {$tables['notes']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            response_id bigint(20) UNSIGNED NOT NULL,
            recruiter_id bigint(20) UNSIGNED NOT NULL,
            note text NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY response_id (response_id),
            KEY recruiter_id (recruiter_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        // =====================================================================
        // ANALYTICS TABLE
        // Tracks brief views and response events for stats
        // =====================================================================
        $sql_analytics = "CREATE TABLE {$tables['analytics']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            brief_id bigint(20) UNSIGNED NOT NULL,
            tracking_id varchar(64) NOT NULL,
            event_type varchar(30) NOT NULL,
            ip_address varchar(45) DEFAULT '',
            user_agent varchar(500) DEFAULT '',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY brief_id (brief_id),
            KEY tracking_id (tracking_id),
            KEY event_type (event_type),
            KEY created_at (created_at)
        ) $charset_collate;";

        // =====================================================================
        // EXTERNAL RECRUITERS TABLE (v2.1)
        // Stores external recruiters not registered on the platform
        // =====================================================================
        $sql_external_recruiters = "CREATE TABLE {$tables['external_recruiters']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            company varchar(255) NOT NULL,
            title varchar(255) DEFAULT '',
            email varchar(255) DEFAULT '',
            phone varchar(50) DEFAULT '',
            website varchar(255) DEFAULT '',
            location varchar(255) DEFAULT '',
            photo_url varchar(500) DEFAULT '',
            rating decimal(2,1) DEFAULT 0,
            review_count int(11) DEFAULT 0,
            bio text DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY is_active (is_active),
            KEY company (company),
            KEY created_at (created_at)
        ) $charset_collate;";

        // =====================================================================
        // USER MATCHES TABLE (v2.1)
        // Stores match scores between users and briefs
        // =====================================================================
        $sql_user_matches = "CREATE TABLE {$tables['user_matches']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            brief_id bigint(20) UNSIGNED NOT NULL,
            match_score int(11) DEFAULT 0,
            score_breakdown text DEFAULT NULL,
            status varchar(20) DEFAULT 'pending',
            calculated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            status_changed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY user_brief (user_id, brief_id),
            KEY user_id (user_id),
            KEY brief_id (brief_id),
            KEY user_status (user_id, status),
            KEY match_score (match_score)
        ) $charset_collate;";

        // =====================================================================
        // LEGACY: CAMPAIGNS TABLE (kept for backwards compatibility)
        // Will migrate data to briefs table
        // =====================================================================
        $sql_campaigns = "CREATE TABLE {$tables['campaigns']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            title varchar(255) NOT NULL,
            brief text NOT NULL,
            location varchar(255) DEFAULT '',
            sector varchar(255) DEFAULT '',
            salary_range varchar(255) DEFAULT '',
            parsed_criteria longtext,
            outreach_subject varchar(255) DEFAULT '',
            outreach_message text,
            scheduled_at datetime DEFAULT NULL,
            expires_at datetime DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'draft',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            submitted_at datetime DEFAULT NULL,
            approved_at datetime DEFAULT NULL,
            approved_by bigint(20) UNSIGNED DEFAULT NULL,
            rejection_reason text DEFAULT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status),
            KEY scheduled_at (scheduled_at),
            KEY expires_at (expires_at),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Targets table (legacy)
        $sql_targets = "CREATE TABLE {$tables['targets']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) UNSIGNED NOT NULL,
            candidate_user_id bigint(20) UNSIGNED DEFAULT NULL,
            candidate_email varchar(255) NOT NULL,
            candidate_name varchar(255) NOT NULL,
            candidate_title varchar(255) DEFAULT '',
            candidate_company varchar(255) DEFAULT '',
            candidate_location varchar(255) DEFAULT '',
            match_score int(11) DEFAULT 0,
            email_status varchar(20) NOT NULL DEFAULT 'pending',
            response_status varchar(20) DEFAULT 'none',
            response_message text,
            tracking_id varchar(64) NOT NULL,
            sent_at datetime DEFAULT NULL,
            delivered_at datetime DEFAULT NULL,
            opened_at datetime DEFAULT NULL,
            open_count int(11) DEFAULT 0,
            clicked_at datetime DEFAULT NULL,
            responded_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY tracking_id (tracking_id),
            KEY campaign_id (campaign_id),
            KEY email_status (email_status),
            KEY response_status (response_status),
            KEY candidate_email (candidate_email)
        ) $charset_collate;";

        // Activity log (legacy)
        $sql_activity = "CREATE TABLE {$tables['activity']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) UNSIGNED DEFAULT NULL,
            target_id bigint(20) UNSIGNED DEFAULT NULL,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            action varchar(50) NOT NULL,
            details longtext,
            ip_address varchar(45) DEFAULT '',
            user_agent varchar(255) DEFAULT '',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY target_id (target_id),
            KEY action (action),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Email templates (legacy)
        $sql_templates = "CREATE TABLE {$tables['templates']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            name varchar(255) NOT NULL,
            subject varchar(255) NOT NULL,
            body text NOT NULL,
            is_default tinyint(1) DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY is_default (is_default)
        ) $charset_collate;";

        // Execute all table creations
        dbDelta($sql_briefs);
        dbDelta($sql_responses);
        dbDelta($sql_notes);
        dbDelta($sql_analytics);
        dbDelta($sql_external_recruiters);
        dbDelta($sql_user_matches);
        dbDelta($sql_campaigns);
        dbDelta($sql_targets);
        dbDelta($sql_activity);
        dbDelta($sql_templates);

        // Insert default template
        self::insert_default_templates();

        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
        self::$tables_exist_cache = true;
    }

    /**
     * Insert default email templates
     */
    private static function insert_default_templates() {
        global $wpdb;
        $tables = self::get_table_names();

        $exists = $wpdb->get_var("SELECT id FROM {$tables['templates']} WHERE is_default = 1");

        if (!$exists) {
            $wpdb->insert(
                $tables['templates'],
                array(
                    'name'       => 'Default Outreach',
                    'subject'    => '{{role_title}} — Confidential Opportunity',
                    'body'       => self::get_default_template_body(),
                    'is_default' => 1,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ),
                array('%s', '%s', '%s', '%d', '%s', '%s')
            );
        }
    }

    /**
     * Default email template body
     */
    private static function get_default_template_body() {
        return 'Hi {{candidate_name}},

{{recruiter_name}} from {{recruiter_company}} has an opportunity that matches your profile.

{{role_title}}
{{location}} | {{salary_range}}

{{brief_content}}

Share your preferred contact details to get started.

{{response_link}}

---
You are receiving this because your profile matches this opportunity.
MENA Careers Recruiting Platform';
    }

    /**
     * Check if all core tables exist
     */
    public static function tables_exist() {
        if (self::$tables_exist_cache !== null) {
            return self::$tables_exist_cache;
        }

        global $wpdb;
        $tables = self::get_table_names();

        // Check core new tables (including v2.1 tables)
        $core_tables = array('briefs', 'responses', 'notes', 'analytics', 'external_recruiters', 'user_matches');

        foreach ($core_tables as $key) {
            $table = $tables[$key];
            $result = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
            if ($result !== $table) {
                self::$tables_exist_cache = false;
                return false;
            }
        }

        self::$tables_exist_cache = true;
        return true;
    }

    /**
     * Check individual table existence
     */
    public static function table_exists($table_key) {
        global $wpdb;
        $tables = self::get_table_names();

        if (!isset($tables[$table_key])) {
            return false;
        }

        $table = $tables[$table_key];
        $result = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        return $result === $table;
    }

    /**
     * Get status of all tables
     */
    public static function get_tables_status() {
        global $wpdb;
        $tables = self::get_table_names();
        $status = array();

        foreach ($tables as $key => $table) {
            $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table;
            $row_count = 0;

            if ($exists) {
                $row_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
            }

            $status[$key] = array(
                'name'   => $table,
                'exists' => $exists,
                'rows'   => (int) $row_count,
            );
        }

        return $status;
    }

    /**
     * Check if upgrade is needed
     */
    public static function needs_upgrade() {
        $current_version = get_option(self::DB_VERSION_OPTION, '0');
        return version_compare($current_version, self::DB_VERSION, '<');
    }

    /**
     * Drop all tables (for uninstall)
     */
    public static function drop_tables() {
        global $wpdb;
        $tables = self::get_table_names();

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }

        delete_option(self::DB_VERSION_OPTION);
    }

    // =========================================================================
    // BRIEF CRUD OPERATIONS (NEW)
    // =========================================================================

    /**
     * Create a new brief
     */
    public static function create_brief($data) {
        global $wpdb;
        $tables = self::get_table_names();

        $defaults = array(
            'user_id'               => get_current_user_id(),
            'title'                 => '',
            'brief'                 => '',
            'location'              => '',
            'sector'                => '',
            'salary_range'          => '',
            'parsed_criteria'       => null,
            'status'                => 'draft',
            'expires_at'            => null,
            'is_external'           => 0,
            'external_recruiter_id' => null,
            'detected_skills'       => null,
            'visibility'            => 'matched',
            'match_weight'          => 50,
            'created_at'            => current_time('mysql'),
            'updated_at'            => current_time('mysql'),
        );

        $data = wp_parse_args($data, $defaults);

        // JSON encode parsed_criteria if array
        if (is_array($data['parsed_criteria'])) {
            $data['parsed_criteria'] = wp_json_encode($data['parsed_criteria']);
        }

        // JSON encode detected_skills if array
        if (is_array($data['detected_skills'])) {
            $data['detected_skills'] = wp_json_encode($data['detected_skills']);
        }

        $result = $wpdb->insert(
            $tables['briefs'],
            $data,
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            return new WP_Error('db_error', 'Failed to create brief: ' . $wpdb->last_error);
        }

        $brief_id = $wpdb->insert_id;

        // Log activity
        self::log_activity(null, null, 'brief_created', array(
            'brief_id' => $brief_id,
            'title'    => $data['title'],
        ));

        return $brief_id;
    }

    /**
     * Get brief by ID
     */
    public static function get_brief($brief_id, $user_id = null) {
        global $wpdb;
        $tables = self::get_table_names();

        $sql = "SELECT * FROM {$tables['briefs']} WHERE id = %d";
        $params = array($brief_id);

        if ($user_id !== null) {
            $sql .= " AND user_id = %d";
            $params[] = $user_id;
        }

        return $wpdb->get_row($wpdb->prepare($sql, $params));
    }

    /**
     * Update brief
     */
    public static function update_brief($brief_id, $data) {
        global $wpdb;
        $tables = self::get_table_names();

        $data['updated_at'] = current_time('mysql');

        if (isset($data['parsed_criteria']) && is_array($data['parsed_criteria'])) {
            $data['parsed_criteria'] = wp_json_encode($data['parsed_criteria']);
        }

        if (isset($data['detected_skills']) && is_array($data['detected_skills'])) {
            $data['detected_skills'] = wp_json_encode($data['detected_skills']);
        }

        $result = $wpdb->update(
            $tables['briefs'],
            $data,
            array('id' => $brief_id),
            null,
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('db_error', 'Failed to update brief: ' . $wpdb->last_error);
        }

        return true;
    }

    /**
     * Delete brief and all related data
     */
    public static function delete_brief($brief_id) {
        global $wpdb;
        $tables = self::get_table_names();

        // Delete responses
        $wpdb->delete($tables['responses'], array('brief_id' => $brief_id), array('%d'));

        // Delete analytics
        $wpdb->delete($tables['analytics'], array('brief_id' => $brief_id), array('%d'));

        // Delete brief
        $result = $wpdb->delete($tables['briefs'], array('id' => $brief_id), array('%d'));

        return $result !== false;
    }

    /**
     * Get briefs for user
     */
    public static function get_user_briefs($user_id, $status = null, $limit = 50, $offset = 0) {
        global $wpdb;
        $tables = self::get_table_names();

        $sql = "SELECT b.*,
                    COUNT(r.id) as response_count,
                    SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                    SUM(CASE WHEN r.starred = 1 THEN 1 ELSE 0 END) as starred_count
                FROM {$tables['briefs']} b
                LEFT JOIN {$tables['responses']} r ON b.id = r.brief_id AND r.archived = 0
                WHERE b.user_id = %d";

        $params = array($user_id);

        if ($status !== null && $status !== 'all') {
            $sql .= " AND b.status = %s";
            $params[] = $status;
        }

        $sql .= " GROUP BY b.id ORDER BY b.updated_at DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    /**
     * Get briefs pending admin review
     */
    public static function get_pending_briefs($limit = 50) {
        global $wpdb;
        $tables = self::get_table_names();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, u.display_name as recruiter_name
             FROM {$tables['briefs']} b
             LEFT JOIN {$wpdb->users} u ON b.user_id = u.ID
             WHERE b.status = 'pending_review'
             ORDER BY b.submitted_at ASC
             LIMIT %d",
            $limit
        ));
    }

    // =========================================================================
    // RESPONSE CRUD OPERATIONS (NEW)
    // =========================================================================

    /**
     * Create a candidate response
     */
    public static function create_response($data) {
        global $wpdb;
        $tables = self::get_table_names();

        // Generate tracking ID if not provided
        if (empty($data['tracking_id'])) {
            $data['tracking_id'] = self::generate_tracking_id();
        }

        $defaults = array(
            'brief_id'           => 0,
            'tracking_id'        => '',
            'candidate_name'     => '',
            'candidate_email'    => '',
            'candidate_phone'    => '',
            'candidate_linkedin' => '',
            'message'            => '',
            'status'             => 'pending',
            'starred'            => 0,
            'archived'           => 0,
            'responded_at'       => current_time('mysql'),
            'created_at'         => current_time('mysql'),
        );

        $data = wp_parse_args($data, $defaults);

        $result = $wpdb->insert(
            $tables['responses'],
            $data,
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s')
        );

        if ($result === false) {
            return new WP_Error('db_error', 'Failed to create response: ' . $wpdb->last_error);
        }

        $response_id = $wpdb->insert_id;

        // Log analytics event
        self::log_analytics_event($data['brief_id'], $data['tracking_id'], 'response_submitted');

        return $response_id;
    }

    /**
     * Get response by ID
     */
    public static function get_response($response_id) {
        global $wpdb;
        $tables = self::get_table_names();

        return $wpdb->get_row($wpdb->prepare(
            "SELECT r.*, b.title as brief_title, b.user_id as recruiter_id
             FROM {$tables['responses']} r
             INNER JOIN {$tables['briefs']} b ON r.brief_id = b.id
             WHERE r.id = %d",
            $response_id
        ));
    }

    /**
     * Get response by tracking ID
     */
    public static function get_response_by_tracking($tracking_id) {
        global $wpdb;
        $tables = self::get_table_names();

        return $wpdb->get_row($wpdb->prepare(
            "SELECT r.*, b.title as brief_title, b.brief as brief_content,
                    b.location, b.sector, b.salary_range, b.user_id as recruiter_id
             FROM {$tables['responses']} r
             INNER JOIN {$tables['briefs']} b ON r.brief_id = b.id
             WHERE r.tracking_id = %s",
            $tracking_id
        ));
    }

    /**
     * Update response
     */
    public static function update_response($response_id, $data) {
        global $wpdb;
        $tables = self::get_table_names();

        $result = $wpdb->update(
            $tables['responses'],
            $data,
            array('id' => $response_id),
            null,
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('db_error', 'Failed to update response: ' . $wpdb->last_error);
        }

        return true;
    }

    /**
     * Get responses for brief
     */
    public static function get_brief_responses($brief_id, $filters = array()) {
        global $wpdb;
        $tables = self::get_table_names();

        $sql = "SELECT r.*,
                    (SELECT COUNT(*) FROM {$tables['notes']} n WHERE n.response_id = r.id) as note_count
                FROM {$tables['responses']} r
                WHERE r.brief_id = %d";

        $params = array($brief_id);

        // Apply filters
        if (!empty($filters['status'])) {
            $sql .= " AND r.status = %s";
            $params[] = $filters['status'];
        }

        if (isset($filters['starred']) && $filters['starred']) {
            $sql .= " AND r.starred = 1";
        }

        if (isset($filters['archived'])) {
            $sql .= " AND r.archived = %d";
            $params[] = $filters['archived'] ? 1 : 0;
        } else {
            // Default: exclude archived
            $sql .= " AND r.archived = 0";
        }

        $sql .= " ORDER BY r.created_at DESC";

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    /**
     * Get all responses for recruiter (across all briefs)
     */
    public static function get_recruiter_responses($user_id, $filters = array(), $limit = 50, $offset = 0) {
        global $wpdb;
        $tables = self::get_table_names();

        $sql = "SELECT r.*, b.title as brief_title,
                    (SELECT COUNT(*) FROM {$tables['notes']} n WHERE n.response_id = r.id) as note_count
                FROM {$tables['responses']} r
                INNER JOIN {$tables['briefs']} b ON r.brief_id = b.id
                WHERE b.user_id = %d";

        $params = array($user_id);

        // Apply filters
        if (!empty($filters['status'])) {
            $sql .= " AND r.status = %s";
            $params[] = $filters['status'];
        }

        if (isset($filters['starred']) && $filters['starred']) {
            $sql .= " AND r.starred = 1";
        }

        if (isset($filters['archived'])) {
            $sql .= " AND r.archived = %d";
            $params[] = $filters['archived'] ? 1 : 0;
        } else {
            $sql .= " AND r.archived = 0";
        }

        if (!empty($filters['brief_id'])) {
            $sql .= " AND r.brief_id = %d";
            $params[] = $filters['brief_id'];
        }

        $sql .= " ORDER BY r.created_at DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    // =========================================================================
    // NOTES OPERATIONS (NEW)
    // =========================================================================

    /**
     * Add note to response
     */
    public static function add_note($response_id, $recruiter_id, $note) {
        global $wpdb;
        $tables = self::get_table_names();

        $result = $wpdb->insert(
            $tables['notes'],
            array(
                'response_id'  => $response_id,
                'recruiter_id' => $recruiter_id,
                'note'         => $note,
                'created_at'   => current_time('mysql'),
            ),
            array('%d', '%d', '%s', '%s')
        );

        if ($result === false) {
            return new WP_Error('db_error', 'Failed to add note: ' . $wpdb->last_error);
        }

        return $wpdb->insert_id;
    }

    /**
     * Get notes for response
     */
    public static function get_response_notes($response_id) {
        global $wpdb;
        $tables = self::get_table_names();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT n.*, u.display_name as recruiter_name
             FROM {$tables['notes']} n
             LEFT JOIN {$wpdb->users} u ON n.recruiter_id = u.ID
             WHERE n.response_id = %d
             ORDER BY n.created_at DESC",
            $response_id
        ));
    }

    /**
     * Delete note
     */
    public static function delete_note($note_id, $recruiter_id) {
        global $wpdb;
        $tables = self::get_table_names();

        return $wpdb->delete(
            $tables['notes'],
            array('id' => $note_id, 'recruiter_id' => $recruiter_id),
            array('%d', '%d')
        );
    }

    // =========================================================================
    // ANALYTICS OPERATIONS (NEW)
    // =========================================================================

    /**
     * Log analytics event
     */
    public static function log_analytics_event($brief_id, $tracking_id, $event_type) {
        global $wpdb;
        $tables = self::get_table_names();

        return $wpdb->insert(
            $tables['analytics'],
            array(
                'brief_id'    => $brief_id,
                'tracking_id' => $tracking_id,
                'event_type'  => $event_type,
                'ip_address'  => self::get_client_ip(),
                'user_agent'  => isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 500) : '',
                'created_at'  => current_time('mysql'),
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );
    }

    /**
     * Get brief analytics
     */
    public static function get_brief_analytics($brief_id) {
        global $wpdb;
        $tables = self::get_table_names();

        return $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(DISTINCT CASE WHEN event_type = 'view' THEN tracking_id END) as views,
                COUNT(DISTINCT CASE WHEN event_type = 'response_submitted' THEN tracking_id END) as responses,
                COUNT(DISTINCT CASE WHEN event_type = 'not_interested' THEN tracking_id END) as not_interested
             FROM {$tables['analytics']}
             WHERE brief_id = %d",
            $brief_id
        ));
    }

    /**
     * Record brief view
     */
    public static function record_brief_view($brief_id, $tracking_id) {
        // Check if already viewed recently (within 1 hour) to avoid duplicates
        global $wpdb;
        $tables = self::get_table_names();

        $recent = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$tables['analytics']}
             WHERE brief_id = %d AND tracking_id = %s AND event_type = 'view'
             AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
            $brief_id,
            $tracking_id
        ));

        if (!$recent) {
            self::log_analytics_event($brief_id, $tracking_id, 'view');
        }
    }

    // =========================================================================
    // LEGACY CAMPAIGN OPERATIONS (kept for backwards compatibility)
    // =========================================================================

    /**
     * Create a new campaign (legacy)
     */
    public static function create_campaign($data) {
        global $wpdb;
        $tables = self::get_table_names();

        $defaults = array(
            'user_id'          => get_current_user_id(),
            'title'            => '',
            'brief'            => '',
            'location'         => '',
            'sector'           => '',
            'salary_range'     => '',
            'parsed_criteria'  => null,
            'outreach_subject' => '',
            'outreach_message' => '',
            'scheduled_at'     => null,
            'expires_at'       => null,
            'status'           => 'draft',
            'created_at'       => current_time('mysql'),
            'updated_at'       => current_time('mysql'),
        );

        $data = wp_parse_args($data, $defaults);

        if (is_array($data['parsed_criteria'])) {
            $data['parsed_criteria'] = wp_json_encode($data['parsed_criteria']);
        }

        $result = $wpdb->insert(
            $tables['campaigns'],
            $data,
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            return new WP_Error('db_error', 'Failed to create campaign: ' . $wpdb->last_error);
        }

        $campaign_id = $wpdb->insert_id;

        self::log_activity($campaign_id, null, 'campaign_created', array(
            'title' => $data['title'],
        ));

        return $campaign_id;
    }

    /**
     * Get campaign by ID (legacy)
     */
    public static function get_campaign($campaign_id, $user_id = null) {
        global $wpdb;
        $tables = self::get_table_names();

        $sql = "SELECT * FROM {$tables['campaigns']} WHERE id = %d";
        $params = array($campaign_id);

        if ($user_id !== null) {
            $sql .= " AND user_id = %d";
            $params[] = $user_id;
        }

        return $wpdb->get_row($wpdb->prepare($sql, $params));
    }

    /**
     * Update campaign (legacy)
     */
    public static function update_campaign($campaign_id, $data) {
        global $wpdb;
        $tables = self::get_table_names();

        $data['updated_at'] = current_time('mysql');

        if (isset($data['parsed_criteria']) && is_array($data['parsed_criteria'])) {
            $data['parsed_criteria'] = wp_json_encode($data['parsed_criteria']);
        }

        $result = $wpdb->update(
            $tables['campaigns'],
            $data,
            array('id' => $campaign_id),
            null,
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('db_error', 'Failed to update campaign: ' . $wpdb->last_error);
        }

        return true;
    }

    /**
     * Delete campaign and all related data (legacy)
     */
    public static function delete_campaign($campaign_id) {
        global $wpdb;
        $tables = self::get_table_names();

        $wpdb->delete($tables['targets'], array('campaign_id' => $campaign_id), array('%d'));
        $wpdb->delete($tables['activity'], array('campaign_id' => $campaign_id), array('%d'));
        $result = $wpdb->delete($tables['campaigns'], array('id' => $campaign_id), array('%d'));

        return $result !== false;
    }

    /**
     * Get campaigns for user (legacy)
     */
    public static function get_user_campaigns($user_id, $status = null, $limit = 50, $offset = 0) {
        global $wpdb;
        $tables = self::get_table_names();

        $sql = "SELECT c.*,
                    COUNT(t.id) as target_count,
                    SUM(CASE WHEN t.email_status IN ('sent', 'delivered', 'opened', 'clicked') THEN 1 ELSE 0 END) as sent_count,
                    SUM(CASE WHEN t.opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened_count,
                    SUM(CASE WHEN t.response_status != 'none' THEN 1 ELSE 0 END) as responded_count
                FROM {$tables['campaigns']} c
                LEFT JOIN {$tables['targets']} t ON c.id = t.campaign_id
                WHERE c.user_id = %d";

        $params = array($user_id);

        if ($status !== null && $status !== 'all') {
            $sql .= " AND c.status = %s";
            $params[] = $status;
        }

        $sql .= " GROUP BY c.id ORDER BY c.updated_at DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    // =========================================================================
    // TARGET OPERATIONS (legacy)
    // =========================================================================

    /**
     * Add targets to campaign
     */
    public static function add_targets($campaign_id, $candidates) {
        global $wpdb;
        $tables = self::get_table_names();

        $added = 0;
        $skipped = 0;

        foreach ($candidates as $candidate) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$tables['targets']}
                 WHERE campaign_id = %d AND candidate_email = %s",
                $campaign_id,
                $candidate['email']
            ));

            if ($exists) {
                $skipped++;
                continue;
            }

            $tracking_id = self::generate_tracking_id();

            $result = $wpdb->insert(
                $tables['targets'],
                array(
                    'campaign_id'       => $campaign_id,
                    'candidate_user_id' => isset($candidate['user_id']) ? $candidate['user_id'] : null,
                    'candidate_email'   => sanitize_email($candidate['email']),
                    'candidate_name'    => sanitize_text_field($candidate['name']),
                    'candidate_title'   => sanitize_text_field($candidate['title'] ?? ''),
                    'candidate_company' => sanitize_text_field($candidate['company'] ?? ''),
                    'candidate_location'=> sanitize_text_field($candidate['location'] ?? ''),
                    'match_score'       => intval($candidate['match_score'] ?? 0),
                    'email_status'      => 'pending',
                    'response_status'   => 'none',
                    'tracking_id'       => $tracking_id,
                    'created_at'        => current_time('mysql'),
                ),
                array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s')
            );

            if ($result) {
                $added++;
            }
        }

        return array(
            'added'   => $added,
            'skipped' => $skipped,
        );
    }

    /**
     * Get targets for campaign
     */
    public static function get_campaign_targets($campaign_id, $status = null) {
        global $wpdb;
        $tables = self::get_table_names();

        $sql = "SELECT * FROM {$tables['targets']} WHERE campaign_id = %d";
        $params = array($campaign_id);

        if ($status !== null) {
            $sql .= " AND email_status = %s";
            $params[] = $status;
        }

        $sql .= " ORDER BY match_score DESC, candidate_name ASC";

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    /**
     * Update target status
     */
    public static function update_target($target_id, $data) {
        global $wpdb;
        $tables = self::get_table_names();

        return $wpdb->update(
            $tables['targets'],
            $data,
            array('id' => $target_id),
            null,
            array('%d')
        );
    }

    /**
     * Get target by tracking ID
     */
    public static function get_target_by_tracking_id($tracking_id) {
        global $wpdb;
        $tables = self::get_table_names();

        return $wpdb->get_row($wpdb->prepare(
            "SELECT t.*, c.title as campaign_title, c.user_id as recruiter_id
             FROM {$tables['targets']} t
             INNER JOIN {$tables['campaigns']} c ON t.campaign_id = c.id
             WHERE t.tracking_id = %s",
            $tracking_id
        ));
    }

    /**
     * Remove target from campaign
     */
    public static function remove_target($target_id, $campaign_id) {
        global $wpdb;
        $tables = self::get_table_names();

        return $wpdb->delete(
            $tables['targets'],
            array('id' => $target_id, 'campaign_id' => $campaign_id),
            array('%d', '%d')
        );
    }

    /**
     * Get campaign statistics
     */
    public static function get_campaign_stats($campaign_id) {
        global $wpdb;
        $tables = self::get_table_names();

        return $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN email_status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN email_status = 'queued' THEN 1 ELSE 0 END) as queued,
                SUM(CASE WHEN email_status IN ('sent', 'delivered', 'opened', 'clicked') THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN email_status = 'bounced' THEN 1 ELSE 0 END) as bounced,
                SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened,
                SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicked,
                SUM(CASE WHEN response_status = 'interested' THEN 1 ELSE 0 END) as interested,
                SUM(CASE WHEN response_status = 'not_interested' THEN 1 ELSE 0 END) as not_interested,
                SUM(CASE WHEN response_status = 'maybe' THEN 1 ELSE 0 END) as maybe,
                SUM(CASE WHEN response_status != 'none' THEN 1 ELSE 0 END) as responded
             FROM {$tables['targets']}
             WHERE campaign_id = %d",
            $campaign_id
        ));
    }

    // =========================================================================
    // ACTIVITY LOG OPERATIONS
    // =========================================================================

    /**
     * Log activity
     */
    public static function log_activity($campaign_id, $target_id, $action, $details = array()) {
        global $wpdb;
        $tables = self::get_table_names();

        return $wpdb->insert(
            $tables['activity'],
            array(
                'campaign_id' => $campaign_id,
                'target_id'   => $target_id,
                'user_id'     => get_current_user_id() ?: null,
                'action'      => $action,
                'details'     => wp_json_encode($details),
                'ip_address'  => self::get_client_ip(),
                'user_agent'  => isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : '',
                'created_at'  => current_time('mysql'),
            ),
            array('%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s')
        );
    }

    /**
     * Get activity feed for campaign
     */
    public static function get_campaign_activity($campaign_id, $limit = 50) {
        return self::get_activity_feed($campaign_id, $limit, 0);
    }

    /**
     * Get activity feed for campaign
     */
    public static function get_activity_feed($campaign_id, $limit = 50, $since_id = 0) {
        global $wpdb;
        $tables = self::get_table_names();

        $sql = "SELECT a.*, t.candidate_name, t.candidate_email, t.candidate_title, t.candidate_company
                FROM {$tables['activity']} a
                LEFT JOIN {$tables['targets']} t ON a.target_id = t.id
                WHERE a.campaign_id = %d";

        $params = array($campaign_id);

        if ($since_id > 0) {
            $sql .= " AND a.id > %d";
            $params[] = $since_id;
        }

        $sql .= " ORDER BY a.created_at DESC LIMIT %d";
        $params[] = $limit;

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    // =========================================================================
    // EMAIL TEMPLATE OPERATIONS
    // =========================================================================

    /**
     * Get email templates for user
     */
    public static function get_templates($user_id = null) {
        global $wpdb;
        $tables = self::get_table_names();

        $sql = "SELECT * FROM {$tables['templates']} WHERE user_id IS NULL";
        $params = array();

        if ($user_id !== null) {
            $sql .= " OR user_id = %d";
            $params[] = $user_id;
        }

        $sql .= " ORDER BY is_default DESC, name ASC";

        if (!empty($params)) {
            return $wpdb->get_results($wpdb->prepare($sql, $params));
        }

        return $wpdb->get_results($sql);
    }

    /**
     * Get default template
     */
    public static function get_default_template() {
        global $wpdb;
        $tables = self::get_table_names();

        return $wpdb->get_row("SELECT * FROM {$tables['templates']} WHERE is_default = 1 LIMIT 1");
    }

    // =========================================================================
    // UTILITY METHODS
    // =========================================================================

    /**
     * Generate unique tracking ID
     */
    public static function generate_tracking_id() {
        return wp_generate_uuid4();
    }

    /**
     * Get client IP address
     */
    private static function get_client_ip() {
        $ip_keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR');

        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Get campaigns ready to send
     */
    public static function get_campaigns_ready_to_send() {
        global $wpdb;
        $tables = self::get_table_names();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$tables['campaigns']}
             WHERE status = 'approved'
             AND scheduled_at <= %s
             ORDER BY scheduled_at ASC",
            current_time('mysql')
        ));
    }

    /**
     * Get pending targets for campaign
     */
    public static function get_pending_targets($campaign_id, $limit = 50) {
        global $wpdb;
        $tables = self::get_table_names();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$tables['targets']}
             WHERE campaign_id = %d AND email_status = 'pending'
             ORDER BY match_score DESC
             LIMIT %d",
            $campaign_id,
            $limit
        ));
    }

    // =========================================================================
    // EXTERNAL RECRUITERS CRUD OPERATIONS (v2.1)
    // =========================================================================

    /**
     * Create external recruiter
     */
    public static function create_external_recruiter($data) {
        global $wpdb;
        $tables = self::get_table_names();

        $defaults = array(
            'name'         => '',
            'company'      => '',
            'title'        => '',
            'email'        => '',
            'phone'        => '',
            'website'      => '',
            'location'     => '',
            'photo_url'    => '',
            'rating'       => 0,
            'review_count' => 0,
            'bio'          => '',
            'is_active'    => 1,
            'created_at'   => current_time('mysql'),
            'updated_at'   => current_time('mysql'),
        );

        $data = wp_parse_args($data, $defaults);

        $result = $wpdb->insert(
            $tables['external_recruiters'],
            $data,
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%d', '%s', '%d', '%s', '%s')
        );

        if ($result === false) {
            return new WP_Error('db_error', 'Failed to create external recruiter: ' . $wpdb->last_error);
        }

        return $wpdb->insert_id;
    }

    /**
     * Get external recruiter by ID
     */
    public static function get_external_recruiter($id) {
        global $wpdb;
        $tables = self::get_table_names();

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tables['external_recruiters']} WHERE id = %d",
            $id
        ));
    }

    /**
     * Update external recruiter
     */
    public static function update_external_recruiter($id, $data) {
        global $wpdb;
        $tables = self::get_table_names();

        $data['updated_at'] = current_time('mysql');

        $result = $wpdb->update(
            $tables['external_recruiters'],
            $data,
            array('id' => $id),
            null,
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('db_error', 'Failed to update external recruiter: ' . $wpdb->last_error);
        }

        return true;
    }

    /**
     * Delete external recruiter
     */
    public static function delete_external_recruiter($id) {
        global $wpdb;
        $tables = self::get_table_names();

        // Check if recruiter has briefs
        $brief_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tables['briefs']} WHERE external_recruiter_id = %d",
            $id
        ));

        if ($brief_count > 0) {
            return new WP_Error('has_briefs', 'Cannot delete recruiter with existing briefs');
        }

        $result = $wpdb->delete(
            $tables['external_recruiters'],
            array('id' => $id),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Get all external recruiters
     */
    public static function get_all_external_recruiters($args = array()) {
        global $wpdb;
        $tables = self::get_table_names();

        $defaults = array(
            'is_active' => null,
            'orderby'   => 'name',
            'order'     => 'ASC',
            'limit'     => 100,
            'offset'    => 0,
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $params = array();

        if ($args['is_active'] !== null) {
            $where[] = 'is_active = %d';
            $params[] = $args['is_active'];
        }

        $where_sql = implode(' AND ', $where);
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']) ?: 'name ASC';

        $sql = "SELECT * FROM {$tables['external_recruiters']} WHERE {$where_sql} ORDER BY {$orderby} LIMIT %d OFFSET %d";
        $params[] = $args['limit'];
        $params[] = $args['offset'];

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    /**
     * Count external recruiters
     */
    public static function count_external_recruiters($is_active = null) {
        global $wpdb;
        $tables = self::get_table_names();

        if ($is_active !== null) {
            return $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$tables['external_recruiters']} WHERE is_active = %d",
                $is_active
            ));
        }

        return $wpdb->get_var("SELECT COUNT(*) FROM {$tables['external_recruiters']}");
    }

    // =========================================================================
    // USER MATCHES CRUD OPERATIONS (v2.1)
    // =========================================================================

    /**
     * Create user match
     */
    public static function create_user_match($data) {
        global $wpdb;
        $tables = self::get_table_names();

        $defaults = array(
            'user_id'           => 0,
            'brief_id'          => 0,
            'match_score'       => 0,
            'score_breakdown'   => null,
            'status'            => 'pending',
            'calculated_at'     => current_time('mysql'),
            'status_changed_at' => null,
        );

        $data = wp_parse_args($data, $defaults);

        // JSON encode breakdown if array
        if (is_array($data['score_breakdown'])) {
            $data['score_breakdown'] = wp_json_encode($data['score_breakdown']);
        }

        $result = $wpdb->insert(
            $tables['user_matches'],
            $data,
            array('%d', '%d', '%d', '%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            return new WP_Error('db_error', 'Failed to create user match: ' . $wpdb->last_error);
        }

        return $wpdb->insert_id;
    }

    /**
     * Get user match by user_id and brief_id
     */
    public static function get_user_match($user_id, $brief_id) {
        global $wpdb;
        $tables = self::get_table_names();

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tables['user_matches']} WHERE user_id = %d AND brief_id = %d",
            $user_id,
            $brief_id
        ));
    }

    /**
     * Update user match
     */
    public static function update_user_match($user_id, $brief_id, $data) {
        global $wpdb;
        $tables = self::get_table_names();

        // JSON encode breakdown if array
        if (isset($data['score_breakdown']) && is_array($data['score_breakdown'])) {
            $data['score_breakdown'] = wp_json_encode($data['score_breakdown']);
        }

        // Track status change
        if (isset($data['status'])) {
            $data['status_changed_at'] = current_time('mysql');
        }

        $result = $wpdb->update(
            $tables['user_matches'],
            $data,
            array('user_id' => $user_id, 'brief_id' => $brief_id),
            null,
            array('%d', '%d')
        );

        if ($result === false) {
            return new WP_Error('db_error', 'Failed to update user match: ' . $wpdb->last_error);
        }

        return true;
    }

    /**
     * Delete user match
     */
    public static function delete_user_match($user_id, $brief_id) {
        global $wpdb;
        $tables = self::get_table_names();

        return $wpdb->delete(
            $tables['user_matches'],
            array('user_id' => $user_id, 'brief_id' => $brief_id),
            array('%d', '%d')
        );
    }

    /**
     * Delete all matches for a user
     */
    public static function delete_user_matches($user_id) {
        global $wpdb;
        $tables = self::get_table_names();

        return $wpdb->delete(
            $tables['user_matches'],
            array('user_id' => $user_id),
            array('%d')
        );
    }

    /**
     * Delete all matches for a brief
     */
    public static function delete_brief_matches($brief_id) {
        global $wpdb;
        $tables = self::get_table_names();

        return $wpdb->delete(
            $tables['user_matches'],
            array('brief_id' => $brief_id),
            array('%d')
        );
    }

    /**
     * Get user matches with brief data
     */
    public static function get_user_matches_with_briefs($user_id, $args = array()) {
        global $wpdb;
        $tables = self::get_table_names();

        $defaults = array(
            'status'       => null,
            'min_score'    => 0,
            'orderby'      => 'match_score',
            'order'        => 'DESC',
            'limit'        => 50,
            'offset'       => 0,
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('m.user_id = %d', 'b.status = %s', 'm.match_score >= %d');
        $params = array($user_id, 'active', $args['min_score']);

        if ($args['status'] !== null) {
            $where[] = 'm.status = %s';
            $params[] = $args['status'];
        }

        $where_sql = implode(' AND ', $where);

        // Handle ordering
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        $orderby_map = array(
            'match_score'   => 'm.match_score',
            'calculated_at' => 'm.calculated_at',
            'title'         => 'b.title',
        );
        $orderby = isset($orderby_map[$args['orderby']]) ? $orderby_map[$args['orderby']] : 'm.match_score';

        $sql = "SELECT m.*, b.title, b.brief, b.location, b.sector, b.salary_range,
                       b.is_external, b.external_recruiter_id, b.detected_skills, b.user_id as recruiter_user_id
                FROM {$tables['user_matches']} m
                JOIN {$tables['briefs']} b ON m.brief_id = b.id
                WHERE {$where_sql}
                ORDER BY {$orderby} {$order}
                LIMIT %d OFFSET %d";

        $params[] = $args['limit'];
        $params[] = $args['offset'];

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    /**
     * Count user matches
     */
    public static function count_user_matches($user_id, $status = null) {
        global $wpdb;
        $tables = self::get_table_names();

        if ($status !== null) {
            return $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$tables['user_matches']} m
                 JOIN {$tables['briefs']} b ON m.brief_id = b.id
                 WHERE m.user_id = %d AND m.status = %s AND b.status = 'active'",
                $user_id,
                $status
            ));
        }

        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tables['user_matches']} m
             JOIN {$tables['briefs']} b ON m.brief_id = b.id
             WHERE m.user_id = %d AND b.status = 'active'",
            $user_id
        ));
    }

    /**
     * Get pending match count for user (for badge display)
     */
    public static function get_pending_match_count($user_id) {
        return self::count_user_matches($user_id, 'pending');
    }

    /**
     * Bulk insert/update matches
     */
    public static function upsert_user_match($user_id, $brief_id, $match_score, $breakdown = null) {
        global $wpdb;
        $tables = self::get_table_names();

        $breakdown_json = is_array($breakdown) ? wp_json_encode($breakdown) : $breakdown;

        // Check if exists
        $existing = self::get_user_match($user_id, $brief_id);

        if ($existing) {
            // Update score but preserve status
            return self::update_user_match($user_id, $brief_id, array(
                'match_score'     => $match_score,
                'score_breakdown' => $breakdown_json,
                'calculated_at'   => current_time('mysql'),
            ));
        }

        // Create new
        return self::create_user_match(array(
            'user_id'         => $user_id,
            'brief_id'        => $brief_id,
            'match_score'     => $match_score,
            'score_breakdown' => $breakdown_json,
        ));
    }
}
