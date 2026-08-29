<?php
/**
 * Database Schema for Pattern Recognition Engine
 * Phase 1: Real-time data foundation
 * 
 * @package SennaCareers
 * @since 6.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Database_Schema {
    
    private static $instance = null;
    private $db_version = '1.3.0'; // Updated for learning platform tables
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Create all database tables
     */
    public function create_tables() {
        // Check if already created to avoid running on every page load
        $installed_version = get_option('sffc_db_version', '0');
        if (version_compare($installed_version, $this->db_version, '>=')) {
            return; // Already up to date
        }

        global $wpdb;

        // Suppress errors during table creation
        $suppress = $wpdb->suppress_errors(true);
        $show_errors = $wpdb->show_errors(false);

        $charset_collate = $wpdb->get_charset_collate();
        
        // Market data cache (refreshed every 15 minutes)
        $table_market_cache = $wpdb->prefix . 'sffc_market_cache';
        $sql_market = "CREATE TABLE $table_market_cache (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            data_type varchar(50) NOT NULL,
            symbol varchar(20) NOT NULL,
            name varchar(100),
            value decimal(15,2),
            previous_close decimal(15,2),
            change_amount decimal(15,2),
            change_percent decimal(8,4),
            volume bigint(20),
            high_day decimal(15,2),
            low_day decimal(15,2),
            timestamp_updated datetime DEFAULT CURRENT_TIMESTAMP,
            source varchar(50),
            PRIMARY KEY (id),
            KEY idx_symbol (symbol),
            KEY idx_type (data_type),
            KEY idx_timestamp (timestamp_updated),
            UNIQUE KEY unique_symbol_type (symbol, data_type)
        ) $charset_collate;";
        
        // News and headlines cache
        $table_news_cache = $wpdb->prefix . 'sffc_news_cache';
        $sql_news = "CREATE TABLE $table_news_cache (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            headline varchar(500) NOT NULL,
            summary text,
            source varchar(100),
            url varchar(500),
            published_date datetime,
            category varchar(50),
            entities longtext,
            sentiment_score decimal(3,2),
            importance_score int(11),
            region varchar(50),
            PRIMARY KEY (id),
            KEY idx_category (category),
            KEY idx_published (published_date),
            KEY idx_sentiment (sentiment_score),
            KEY idx_importance (importance_score)
        ) $charset_collate;";
        
        // PE/Finance specific intelligence
        $table_finance_intel = $wpdb->prefix . 'sffc_finance_intelligence';
        $sql_finance = "CREATE TABLE $table_finance_intel (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            intel_type varchar(50) NOT NULL,
            firm_name varchar(200),
            headline varchar(500),
            details text,
            value_amount decimal(15,2),
            value_currency varchar(10),
            sector varchar(100),
            region varchar(100),
            date_announced date,
            source varchar(100),
            relevance_score int(11),
            PRIMARY KEY (id),
            KEY idx_firm (firm_name),
            KEY idx_type (intel_type),
            KEY idx_date (date_announced),
            KEY idx_sector (sector)
        ) $charset_collate;";
        
        // Pre-computed analysis cache (refreshed every 4 hours)
        $table_analysis_cache = $wpdb->prefix . 'sffc_analysis_cache';
        $sql_analysis = "CREATE TABLE $table_analysis_cache (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            analysis_type varchar(50) NOT NULL,
            analysis_key varchar(100) NOT NULL,
            analysis_content text,
            supporting_data longtext,
            confidence_score decimal(3,2),
            generated_at datetime DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime,
            PRIMARY KEY (id),
            KEY idx_type_key (analysis_type, analysis_key),
            KEY idx_expires (expires_at),
            UNIQUE KEY unique_type_key (analysis_type, analysis_key)
        ) $charset_collate;";
        
        // Pattern matching history for learning
        $table_pattern_history = $wpdb->prefix . 'sffc_pattern_history';
        $sql_pattern = "CREATE TABLE $table_pattern_history (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_query text,
            detected_pattern varchar(100),
            entities_extracted longtext,
            response_template_used varchar(100),
            response_source varchar(50),
            user_session varchar(100),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_pattern (detected_pattern),
            KEY idx_session (user_session),
            KEY idx_created (created_at)
        ) $charset_collate;";
        
        // XML feed status tracking
        $table_feed_status = $wpdb->prefix . 'sffc_feed_status';
        $sql_feed_status = "CREATE TABLE $table_feed_status (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            feed_name varchar(100) NOT NULL,
            feed_url varchar(500),
            last_fetch datetime,
            next_fetch datetime,
            fetch_status varchar(20),
            error_message text,
            items_processed int(11),
            priority int(11) DEFAULT 5,
            PRIMARY KEY (id),
            KEY idx_next_fetch (next_fetch),
            KEY idx_status (fetch_status),
            UNIQUE KEY unique_feed (feed_name)
        ) $charset_collate;";
        
        // User CVs storage table
        $table_user_cvs = $wpdb->prefix . 'sffc_user_cvs';
        $sql_user_cvs = "CREATE TABLE $table_user_cvs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            cv_content longtext NOT NULL,
            cv_parsed longtext,
            file_name varchar(255),
            file_type varchar(50),
            seniority int(11),
            location varchar(255),
            latest_role varchar(255),
            company varchar(255),
            skills text,
            uploaded_date datetime DEFAULT CURRENT_TIMESTAMP,
            last_used datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            is_active tinyint(1) DEFAULT 1,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_active (is_active),
            KEY idx_uploaded (uploaded_date),
            UNIQUE KEY unique_user_active (user_id, is_active)
        ) $charset_collate;";

        // Job Applications Tracking - tracks user applications and their stages
        $table_job_applications = $wpdb->prefix . 'sffc_job_applications';
        $sql_job_applications = "CREATE TABLE $table_job_applications (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            job_id bigint(20) NOT NULL,
            stage varchar(50) NOT NULL DEFAULT 'applied',
            applied_date datetime DEFAULT CURRENT_TIMESTAMP,
            stage_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            notes text,
            source varchar(100),
            company_name varchar(200),
            job_title varchar(200),
            location varchar(200),
            salary_min int(11),
            salary_max int(11),
            PRIMARY KEY (id),
            KEY idx_user (user_id),
            KEY idx_job (job_id),
            KEY idx_stage (stage),
            KEY idx_date (applied_date),
            UNIQUE KEY unique_user_job (user_id, job_id)
        ) $charset_collate;";

        // Saved/Bookmarked Jobs
        $table_saved_jobs = $wpdb->prefix . 'sffc_saved_jobs';
        $sql_saved_jobs = "CREATE TABLE $table_saved_jobs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            job_id bigint(20) NOT NULL,
            saved_date datetime DEFAULT CURRENT_TIMESTAMP,
            folder varchar(100) DEFAULT 'default',
            PRIMARY KEY (id),
            KEY idx_user (user_id),
            KEY idx_job (job_id),
            UNIQUE KEY unique_user_job (user_id, job_id)
        ) $charset_collate;";

        // User Activity Log - for analytics and historical tracking
        $table_user_activity = $wpdb->prefix . 'sffc_user_activity';
        $sql_user_activity = "CREATE TABLE $table_user_activity (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            activity_type varchar(50) NOT NULL,
            activity_data longtext,
            job_id bigint(20),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user (user_id),
            KEY idx_type (activity_type),
            KEY idx_date (created_at),
            KEY idx_job (job_id)
        ) $charset_collate;";

        // Dashboard Metrics Snapshots - for historical trends and sparklines
        $table_dashboard_snapshots = $wpdb->prefix . 'sffc_dashboard_snapshots';
        $sql_dashboard_snapshots = "CREATE TABLE $table_dashboard_snapshots (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            snapshot_date date NOT NULL,
            total_applications int(11) DEFAULT 0,
            stage_applied int(11) DEFAULT 0,
            stage_waiting int(11) DEFAULT 0,
            stage_first_interview int(11) DEFAULT 0,
            stage_further_interview int(11) DEFAULT 0,
            stage_secured int(11) DEFAULT 0,
            stage_moved_on int(11) DEFAULT 0,
            high_matches int(11) DEFAULT 0,
            saved_jobs int(11) DEFAULT 0,
            metrics_json longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user (user_id),
            KEY idx_date (snapshot_date),
            UNIQUE KEY unique_user_date (user_id, snapshot_date)
        ) $charset_collate;";

        // Networking/Recruiter Interactions
        $table_interactions = $wpdb->prefix . 'sffc_interactions';
        $sql_interactions = "CREATE TABLE $table_interactions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            interaction_type enum('networking', 'recruiter', 'referral') NOT NULL,
            contact_name varchar(200),
            contact_email varchar(200),
            company varchar(200),
            job_id bigint(20),
            status varchar(50) DEFAULT 'pending',
            notes text,
            interaction_date datetime DEFAULT CURRENT_TIMESTAMP,
            follow_up_date date,
            PRIMARY KEY (id),
            KEY idx_user (user_id),
            KEY idx_type (interaction_type),
            KEY idx_date (interaction_date),
            KEY idx_status (status)
        ) $charset_collate;";

        // User Audit Profile - Stores selections from Smart message audit mode
        $table_user_audit_profile = $wpdb->prefix . 'sffc_user_audit_profile';
        $sql_user_audit_profile = "CREATE TABLE $table_user_audit_profile (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            full_name varchar(255) DEFAULT '',
            current_role varchar(255) DEFAULT '',
            years_experience varchar(50) DEFAULT '',
            work_preference varchar(100) DEFAULT '',
            target_industries longtext,
            target_locations longtext,
            skills_proficiency longtext,
            qualifications longtext,
            career_goals longtext,
            salary_expectation varchar(100) DEFAULT '',
            availability varchar(100) DEFAULT '',
            relocation_preference varchar(100) DEFAULT '',
            remote_preference varchar(100) DEFAULT '',
            audit_responses longtext,
            profile_completed tinyint(1) DEFAULT 0,
            completion_percentage int(3) DEFAULT 0,
            last_audit_job_id bigint(20),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_user (user_id),
            KEY idx_completed (profile_completed),
            KEY idx_updated (updated_at)
        ) $charset_collate;";

        // ============================================================
        // LEARNING PLATFORM TABLES
        // ============================================================

        // Learning Progress - Track user progress through courses
        $table_learning_progress = $wpdb->prefix . 'sffc_learning_progress';
        $sql_learning_progress = "CREATE TABLE $table_learning_progress (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            course_id bigint(20) NOT NULL,
            module_id int(11),
            lesson_id int(11),
            progress_percentage decimal(5,2) DEFAULT 0.00,
            time_spent_seconds int(11) DEFAULT 0,
            last_accessed datetime,
            completed tinyint(1) DEFAULT 0,
            completed_at datetime,
            PRIMARY KEY (id),
            KEY idx_user_course (user_id, course_id),
            KEY idx_user_progress (user_id, completed),
            KEY idx_course (course_id)
        ) $charset_collate;";

        // Quiz Results - Store quiz submissions and scores
        $table_quiz_results = $wpdb->prefix . 'sffc_quiz_results';
        $sql_quiz_results = "CREATE TABLE $table_quiz_results (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            course_id bigint(20) NOT NULL,
            quiz_id int(11) NOT NULL,
            lesson_id int(11),
            score decimal(5,2),
            max_score decimal(5,2),
            percentage decimal(5,2),
            passed tinyint(1) DEFAULT 0,
            attempt_number int(11) DEFAULT 1,
            time_taken_seconds int(11),
            answers longtext,
            feedback longtext,
            submitted_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_quiz (user_id, quiz_id),
            KEY idx_user_course (user_id, course_id),
            KEY idx_course_quiz (course_id, quiz_id)
        ) $charset_collate;";

        // Mock Interviews - Store mock interview sessions and AI feedback
        $table_mock_interviews = $wpdb->prefix . 'sffc_mock_interviews';
        $sql_mock_interviews = "CREATE TABLE $table_mock_interviews (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            interview_type varchar(100),
            difficulty_level varchar(50),
            questions longtext,
            user_responses longtext,
            ai_feedback longtext,
            overall_score decimal(5,2),
            technical_score decimal(5,2),
            behavioral_score decimal(5,2),
            case_score decimal(5,2),
            strengths longtext,
            areas_for_improvement longtext,
            recommended_courses longtext,
            conducted_at datetime DEFAULT CURRENT_TIMESTAMP,
            duration_seconds int(11),
            PRIMARY KEY (id),
            KEY idx_user_interviews (user_id, conducted_at),
            KEY idx_interview_type (interview_type)
        ) $charset_collate;";

        // Certificates - Issue and track certificates
        $table_certificates = $wpdb->prefix . 'sffc_certificates';
        $sql_certificates = "CREATE TABLE $table_certificates (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            course_id bigint(20) NOT NULL,
            certificate_number varchar(50) UNIQUE,
            issued_at datetime DEFAULT CURRENT_TIMESTAMP,
            credential_url varchar(255),
            verification_code varchar(100),
            pdf_url varchar(255),
            skills_certified longtext,
            PRIMARY KEY (id),
            KEY idx_user_certs (user_id),
            KEY idx_cert_number (certificate_number),
            KEY idx_course (course_id)
        ) $charset_collate;";

        // Learning Paths - Personalized learning roadmaps
        $table_learning_paths = $wpdb->prefix . 'sffc_learning_paths';
        $sql_learning_paths = "CREATE TABLE $table_learning_paths (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            path_name varchar(255),
            target_role varchar(255),
            course_sequence longtext,
            current_step int(11) DEFAULT 1,
            total_steps int(11),
            estimated_weeks int(11),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            completed tinyint(1) DEFAULT 0,
            PRIMARY KEY (id),
            KEY idx_user_paths (user_id),
            KEY idx_completed (completed)
        ) $charset_collate;";

        // Execute table creation
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Suppress output from dbDelta
        @dbDelta($sql_market);
        @dbDelta($sql_news);
        @dbDelta($sql_finance);
        @dbDelta($sql_analysis);
        @dbDelta($sql_pattern);
        @dbDelta($sql_feed_status);
        @dbDelta($sql_user_cvs);
        @dbDelta($sql_job_applications);
        @dbDelta($sql_saved_jobs);
        @dbDelta($sql_user_activity);
        @dbDelta($sql_dashboard_snapshots);
        @dbDelta($sql_interactions);
        @dbDelta($sql_user_audit_profile);

        // Learning Platform Tables
        @dbDelta($sql_learning_progress);
        @dbDelta($sql_quiz_results);
        @dbDelta($sql_mock_interviews);
        @dbDelta($sql_certificates);
        @dbDelta($sql_learning_paths);

        // Restore error handling
        $wpdb->suppress_errors($suppress);
        $wpdb->show_errors($show_errors);

        // Store database version
        update_option('sffc_db_version', $this->db_version);

        // Initialize default feed sources
        $this->initialize_feed_sources();
    }
    
    /**
     * Initialize default feed sources
     */
    private function initialize_feed_sources() {
        global $wpdb;
        
        $table_feed_status = $wpdb->prefix . 'sffc_feed_status';
        
        $default_feeds = array(
            array(
                'feed_name' => 'bloomberg_markets',
                'feed_url' => 'https://feeds.bloomberg.com/markets/news.rss',
                'priority' => 1,
                'fetch_status' => 'pending'
            ),
            array(
                'feed_name' => 'reuters_business',
                'feed_url' => 'https://feeds.reuters.com/reuters/businessNews',
                'priority' => 1,
                'fetch_status' => 'pending'
            ),
            array(
                'feed_name' => 'wsj_markets',
                'feed_url' => 'https://feeds.a.dj.com/rss/RSSMarketsMain.xml',
                'priority' => 1,
                'fetch_status' => 'pending'
            ),
            array(
                'feed_name' => 'ft_companies',
                'feed_url' => 'https://www.ft.com/companies?format=rss',
                'priority' => 2,
                'fetch_status' => 'pending'
            ),
            array(
                'feed_name' => 'yahoo_finance',
                'feed_url' => 'https://finance.yahoo.com/rss/',
                'priority' => 2,
                'fetch_status' => 'pending'
            ),
            array(
                'feed_name' => 'marketwatch_top',
                'feed_url' => 'http://feeds.marketwatch.com/marketwatch/topstories',
                'priority' => 2,
                'fetch_status' => 'pending'
            ),
            array(
                'feed_name' => 'cnbc_top',
                'feed_url' => 'https://www.cnbc.com/id/100003114/device/rss/rss.html',
                'priority' => 2,
                'fetch_status' => 'pending'
            ),
            array(
                'feed_name' => 'seeking_alpha',
                'feed_url' => 'https://seekingalpha.com/feed.xml',
                'priority' => 3,
                'fetch_status' => 'pending'
            )
        );
        
        // Use INSERT IGNORE to avoid duplicate errors
        foreach ($default_feeds as $feed) {
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO $table_feed_status
                (feed_name, feed_url, priority, fetch_status, next_fetch)
                VALUES (%s, %s, %d, %s, %s)",
                $feed['feed_name'],
                $feed['feed_url'],
                $feed['priority'],
                $feed['fetch_status'],
                current_time('mysql')
            ));
        }
    }
    
    /**
     * Drop all tables
     */
    public function drop_tables() {
        global $wpdb;

        $tables = array(
            $wpdb->prefix . 'sffc_market_cache',
            $wpdb->prefix . 'sffc_news_cache',
            $wpdb->prefix . 'sffc_finance_intelligence',
            $wpdb->prefix . 'sffc_analysis_cache',
            $wpdb->prefix . 'sffc_pattern_history',
            $wpdb->prefix . 'sffc_feed_status',
            $wpdb->prefix . 'sffc_user_cvs',
            $wpdb->prefix . 'sffc_job_applications',
            $wpdb->prefix . 'sffc_saved_jobs',
            $wpdb->prefix . 'sffc_user_activity',
            $wpdb->prefix . 'sffc_dashboard_snapshots',
            $wpdb->prefix . 'sffc_interactions',
            $wpdb->prefix . 'sffc_user_audit_profile',
            // Learning Platform Tables
            $wpdb->prefix . 'sffc_learning_progress',
            $wpdb->prefix . 'sffc_quiz_results',
            $wpdb->prefix . 'sffc_mock_interviews',
            $wpdb->prefix . 'sffc_certificates',
            $wpdb->prefix . 'sffc_learning_paths'
        );

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }

        delete_option('sffc_db_version');
    }
    
    /**
     * Check if tables need update
     */
    public function check_db_version() {
        $installed_version = get_option('sffc_db_version');
        
        if ($installed_version != $this->db_version) {
            $this->create_tables();
        }
    }
}