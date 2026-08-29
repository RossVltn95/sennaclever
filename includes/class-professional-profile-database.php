<?php

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Professional_Profile_Database
{
    private static $instance = null;
    
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init()
    {
        // Only create tables if not already created (check version option)
        $db_version = get_option('sffc_professional_profile_db_version', '0');
        if (version_compare($db_version, '1.0.0', '<')) {
            add_action('wp_loaded', array($this, 'create_tables'));
        }
    }

    public function create_tables()
    {
        global $wpdb;

        // Double-check we haven't already created tables (race condition protection)
        $db_version = get_option('sffc_professional_profile_db_version', '0');
        if (version_compare($db_version, '1.0.0', '>=')) {
            return;
        }

        $charset_collate = $wpdb->get_charset_collate();

        $tables = array(
            'professional_profiles' => "
                CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_professional_profiles (
                    id bigint(20) NOT NULL AUTO_INCREMENT,
                    user_id bigint(20) NOT NULL,
                    profile_title varchar(255) DEFAULT NULL,
                    professional_summary longtext DEFAULT NULL,
                    current_position varchar(255) DEFAULT NULL,
                    current_company varchar(255) DEFAULT NULL,
                    years_experience int(3) DEFAULT NULL,
                    expertise_areas longtext DEFAULT NULL,
                    professional_certifications longtext DEFAULT NULL,
                    industry_focus varchar(255) DEFAULT NULL,
                    networking_preferences longtext DEFAULT NULL,
                    introduction_bio longtext DEFAULT NULL,
                    linkedin_url varchar(500) DEFAULT NULL,
                    company_website varchar(500) DEFAULT NULL,
                    profile_visibility varchar(20) DEFAULT 'private',
                    open_to_introductions tinyint(1) DEFAULT 1,
                    mentor_available tinyint(1) DEFAULT 0,
                    seeking_mentor tinyint(1) DEFAULT 0,
                    profile_completed tinyint(1) DEFAULT 0,
                    last_active datetime DEFAULT NULL,
                    created_at datetime DEFAULT CURRENT_TIMESTAMP,
                    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY user_id (user_id),
                    KEY industry_focus (industry_focus),
                    KEY profile_visibility (profile_visibility),
                    KEY open_to_introductions (open_to_introductions)
                ) $charset_collate;",

            'professional_expertise' => "
                CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_professional_expertise (
                    id bigint(20) NOT NULL AUTO_INCREMENT,
                    user_id bigint(20) NOT NULL,
                    expertise_title varchar(255) NOT NULL,
                    expertise_level varchar(50) DEFAULT 'Expert',
                    years_experience int(3) DEFAULT NULL,
                    certification_proof varchar(500) DEFAULT NULL,
                    verification_status varchar(20) DEFAULT 'pending',
                    verified_by bigint(20) DEFAULT NULL,
                    verified_at datetime DEFAULT NULL,
                    display_order int(3) DEFAULT 0,
                    created_at datetime DEFAULT CURRENT_TIMESTAMP,
                    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY user_id (user_id),
                    KEY verification_status (verification_status),
                    KEY display_order (display_order)
                ) $charset_collate;",

            'professional_analytics' => "
                CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_professional_analytics (
                    id bigint(20) NOT NULL AUTO_INCREMENT,
                    user_id bigint(20) NOT NULL,
                    metric_type varchar(50) NOT NULL,
                    metric_value longtext DEFAULT NULL,
                    date_recorded date NOT NULL,
                    month_year varchar(7) NOT NULL,
                    created_at datetime DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY user_id (user_id),
                    KEY metric_type (metric_type),
                    KEY date_recorded (date_recorded),
                    KEY month_year (month_year)
                ) $charset_collate;",

            'professional_subscriptions' => "
                CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_professional_subscriptions (
                    id bigint(20) NOT NULL AUTO_INCREMENT,
                    user_id bigint(20) NOT NULL,
                    subscription_type varchar(100) NOT NULL,
                    subscription_status varchar(20) NOT NULL,
                    start_date datetime NOT NULL,
                    end_date datetime DEFAULT NULL,
                    usage_limit int(10) DEFAULT NULL,
                    usage_current int(10) DEFAULT 0,
                    last_usage_reset datetime DEFAULT NULL,
                    subscription_data longtext DEFAULT NULL,
                    created_at datetime DEFAULT CURRENT_TIMESTAMP,
                    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY user_id (user_id),
                    KEY subscription_status (subscription_status),
                    KEY end_date (end_date)
                ) $charset_collate;",

            'professional_networking' => "
                CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_professional_networking (
                    id bigint(20) NOT NULL AUTO_INCREMENT,
                    user_id bigint(20) NOT NULL,
                    connection_user_id bigint(20) NOT NULL,
                    connection_type varchar(50) DEFAULT 'professional',
                    connection_status varchar(20) DEFAULT 'pending',
                    introduction_context longtext DEFAULT NULL,
                    introduced_by bigint(20) DEFAULT NULL,
                    connection_strength int(2) DEFAULT 1,
                    last_interaction datetime DEFAULT NULL,
                    mutual_connections int(5) DEFAULT 0,
                    created_at datetime DEFAULT CURRENT_TIMESTAMP,
                    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY user_id (user_id),
                    KEY connection_user_id (connection_user_id),
                    KEY connection_status (connection_status),
                    KEY introduced_by (introduced_by),
                    UNIQUE KEY unique_connection (user_id, connection_user_id)
                ) $charset_collate;",

            'professional_introductions' => "
                CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_professional_introductions (
                    id bigint(20) NOT NULL AUTO_INCREMENT,
                    requester_id bigint(20) NOT NULL,
                    target_id bigint(20) NOT NULL,
                    introducer_id bigint(20) DEFAULT NULL,
                    introduction_context longtext NOT NULL,
                    introduction_reason longtext DEFAULT NULL,
                    mutual_interest varchar(255) DEFAULT NULL,
                    introduction_status varchar(20) DEFAULT 'pending',
                    response_message longtext DEFAULT NULL,
                    scheduled_intro_date datetime DEFAULT NULL,
                    followup_required tinyint(1) DEFAULT 1,
                    success_rating int(1) DEFAULT NULL,
                    created_at datetime DEFAULT CURRENT_TIMESTAMP,
                    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY requester_id (requester_id),
                    KEY target_id (target_id),
                    KEY introducer_id (introducer_id),
                    KEY introduction_status (introduction_status),
                    KEY scheduled_intro_date (scheduled_intro_date)
                ) $charset_collate;",

            'professional_events' => "
                CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_professional_events (
                    id bigint(20) NOT NULL AUTO_INCREMENT,
                    user_id bigint(20) NOT NULL,
                    event_title varchar(255) NOT NULL,
                    event_type varchar(100) DEFAULT 'networking',
                    event_description longtext DEFAULT NULL,
                    event_date datetime NOT NULL,
                    event_location varchar(255) DEFAULT NULL,
                    event_url varchar(500) DEFAULT NULL,
                    attendance_status varchar(20) DEFAULT 'interested',
                    professional_credits int(2) DEFAULT 0,
                    networking_opportunities longtext DEFAULT NULL,
                    event_notes longtext DEFAULT NULL,
                    created_at datetime DEFAULT CURRENT_TIMESTAMP,
                    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY user_id (user_id),
                    KEY event_type (event_type),
                    KEY event_date (event_date),
                    KEY attendance_status (attendance_status)
                ) $charset_collate;"
        );

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        foreach ($tables as $table_name => $sql) {
            dbDelta($sql);
        }

        update_option('sffc_professional_profile_db_version', '1.0.0');
    }

    public function get_user_profile($user_id)
    {
        global $wpdb;
        
        $profile = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}sffc_professional_profiles WHERE user_id = %d",
                $user_id
            ),
            ARRAY_A
        );

        if (!$profile) {
            return $this->create_default_profile($user_id);
        }

        $profile['expertise_areas'] = json_decode($profile['expertise_areas'], true) ?: array();
        $profile['networking_preferences'] = json_decode($profile['networking_preferences'], true) ?: array();

        return $profile;
    }

    public function update_user_profile($user_id, $data)
    {
        global $wpdb;

        if (isset($data['expertise_areas']) && is_array($data['expertise_areas'])) {
            $data['expertise_areas'] = json_encode($data['expertise_areas']);
        }

        if (isset($data['networking_preferences']) && is_array($data['networking_preferences'])) {
            $data['networking_preferences'] = json_encode($data['networking_preferences']);
        }

        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}sffc_professional_profiles WHERE user_id = %d",
                $user_id
            )
        );

        if ($existing) {
            return $wpdb->update(
                $wpdb->prefix . 'sffc_professional_profiles',
                $data,
                array('user_id' => $user_id)
            );
        } else {
            $data['user_id'] = $user_id;
            return $wpdb->insert(
                $wpdb->prefix . 'sffc_professional_profiles',
                $data
            );
        }
    }

    public function get_user_expertise($user_id)
    {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}sffc_professional_expertise 
                 WHERE user_id = %d 
                 ORDER BY display_order ASC, created_at DESC",
                $user_id
            ),
            ARRAY_A
        );
    }

    public function add_user_expertise($user_id, $expertise_data)
    {
        global $wpdb;

        $expertise_data['user_id'] = $user_id;
        
        return $wpdb->insert(
            $wpdb->prefix . 'sffc_professional_expertise',
            $expertise_data
        );
    }

    public function get_user_analytics($user_id, $date_range = 30)
    {
        global $wpdb;

        $start_date = date('Y-m-d', strtotime("-{$date_range} days"));

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}sffc_professional_analytics 
                 WHERE user_id = %d AND date_recorded >= %s 
                 ORDER BY date_recorded DESC",
                $user_id,
                $start_date
            ),
            ARRAY_A
        );
    }

    public function log_user_activity($user_id, $metric_type, $metric_value)
    {
        global $wpdb;

        $today = date('Y-m-d');
        $month_year = date('Y-m');

        return $wpdb->insert(
            $wpdb->prefix . 'sffc_professional_analytics',
            array(
                'user_id' => $user_id,
                'metric_type' => $metric_type,
                'metric_value' => is_array($metric_value) ? json_encode($metric_value) : $metric_value,
                'date_recorded' => $today,
                'month_year' => $month_year
            )
        );
    }

    private function create_default_profile($user_id)
    {
        $user = get_user_by('id', $user_id);
        
        $default_data = array(
            'user_id' => $user_id,
            'profile_title' => '',
            'professional_summary' => '',
            'expertise_areas' => json_encode(array()),
            'networking_preferences' => json_encode(array(
                'open_to_introductions' => true,
                'preferred_meeting_type' => 'virtual',
                'industry_interests' => array()
            )),
            'profile_visibility' => 'private',
            'profile_completed' => 0
        );

        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'sffc_professional_profiles',
            $default_data
        );

        return $this->get_user_profile($user_id);
    }
}

// Defer initialization until plugins_loaded to avoid early execution issues
add_action('plugins_loaded', function() {
    SFFC_Professional_Profile_Database::get_instance()->init();
}, 20);