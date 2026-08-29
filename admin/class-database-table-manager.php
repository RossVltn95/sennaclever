<?php
/**
 * Database Table Manager - Manual table creation via admin
 * Avoids activation hook issues by allowing manual trigger
 * 
 * @package SennaCareers
 * @subpackage Admin
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Database_Table_Manager {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Table definitions
     */
    private $tables = array();
    
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
        $this->define_tables();
        $this->init_hooks();
    }
    
    /**
     * Define all tables
     */
    private function define_tables() {
        global $wpdb;
        
        // Core conversation tables
        $this->tables['conversations'] = array(
            'name' => $wpdb->prefix . 'sffc_conversations',
            'sql' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_conversations (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL,
                mode varchar(50) DEFAULT 'career',
                status varchar(20) DEFAULT 'active',
                context longtext,
                metadata longtext,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY mode (mode),
                KEY status (status),
                KEY created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'Stores conversation sessions with users'
        );
        
        $this->tables['messages'] = array(
            'name' => $wpdb->prefix . 'sffc_messages',
            'sql' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_messages (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                conversation_id bigint(20) unsigned NOT NULL,
                sender_type enum('user','assistant','expert','system') DEFAULT 'user',
                sender_id bigint(20) unsigned DEFAULT NULL,
                message longtext NOT NULL,
                metadata longtext,
                visual_data longtext,
                analysis_data longtext,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY conversation_id (conversation_id),
                KEY sender_type (sender_type),
                KEY created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'Stores individual messages within conversations'
        );
        
        // User profile and preferences
        $this->tables['user_profiles'] = array(
            'name' => $wpdb->prefix . 'sffc_user_profiles',
            'sql' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_user_profiles (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL,
                professional_data longtext,
                preferences longtext,
                career_goals longtext,
                skill_levels longtext,
                interaction_history longtext,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY user_id (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'Stores user professional profiles and preferences'
        );
        
        // Market data cache
        $this->tables['market_cache'] = array(
            'name' => $wpdb->prefix . 'sffc_market_cache',
            'sql' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_market_cache (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                source varchar(100) NOT NULL,
                data_type varchar(50) NOT NULL,
                content longtext,
                analysis longtext,
                visuals longtext,
                expires_at datetime DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY source (source),
                KEY data_type (data_type),
                KEY expires_at (expires_at),
                INDEX idx_source_type (source, data_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'Caches market data and analysis for performance'
        );
        
        // Market events tracking
        $this->tables['market_events'] = array(
            'name' => $wpdb->prefix . 'sffc_market_events',
            'sql' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_market_events (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                event_type varchar(50) NOT NULL,
                event_title varchar(500) NOT NULL,
                event_description longtext,
                source varchar(100),
                why_analysis longtext,
                causality_chain longtext,
                career_implications longtext,
                visual_components longtext,
                event_date datetime DEFAULT CURRENT_TIMESTAMP,
                analyzed_at datetime DEFAULT NULL,
                PRIMARY KEY (id),
                KEY event_type (event_type),
                KEY event_date (event_date),
                KEY analyzed_at (analyzed_at),
                FULLTEXT KEY event_search (event_title, event_description)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'Tracks and analyzes market events'
        );
        
        // Learning and knowledge tracking
        $this->tables['knowledge_tracking'] = array(
            'name' => $wpdb->prefix . 'sffc_knowledge_tracking',
            'sql' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_knowledge_tracking (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL,
                topic varchar(200) NOT NULL,
                concept varchar(500),
                understanding_level int(11) DEFAULT 0,
                quiz_scores longtext,
                learning_path longtext,
                last_reviewed datetime DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY topic (topic),
                KEY understanding_level (understanding_level),
                INDEX idx_user_topic (user_id, topic)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'Tracks user learning progress and knowledge'
        );
        
        // Template library
        $this->tables['message_templates'] = array(
            'name' => $wpdb->prefix . 'sffc_message_templates',
            'sql' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_message_templates (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                category varchar(50) NOT NULL,
                mode varchar(50) DEFAULT 'general',
                template_type varchar(50) NOT NULL,
                template_text longtext NOT NULL,
                variables longtext,
                context_rules longtext,
                personality_traits longtext,
                usage_count int(11) DEFAULT 0,
                last_used datetime DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY category (category),
                KEY mode (mode),
                KEY template_type (template_type),
                KEY usage_count (usage_count),
                INDEX idx_mode_type (mode, template_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'Stores response templates for MENA Careers personality'
        );
        
        // API usage tracking
        $this->tables['api_usage'] = array(
            'name' => $wpdb->prefix . 'sffc_api_usage',
            'sql' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_api_usage (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                api_type varchar(50) NOT NULL,
                endpoint varchar(200),
                tokens_used int(11) DEFAULT 0,
                cost_estimate decimal(10,4) DEFAULT 0.0000,
                response_time int(11) DEFAULT 0,
                status varchar(20) DEFAULT 'success',
                error_message text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY api_type (api_type),
                KEY status (status),
                KEY created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'Tracks API usage for monitoring and optimization'
        );
        
        // Opportunities tracking
        $this->tables['opportunities'] = array(
            'name' => $wpdb->prefix . 'sffc_opportunities',
            'sql' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_opportunities (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                title varchar(500) NOT NULL,
                company varchar(200) NOT NULL,
                location varchar(200),
                role_type varchar(100),
                seniority_level varchar(50),
                requirements longtext,
                description longtext,
                compensation_range varchar(100),
                metadata longtext,
                source varchar(100),
                source_url text,
                match_criteria longtext,
                expires_at datetime DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY company (company),
                KEY role_type (role_type),
                KEY seniority_level (seniority_level),
                KEY expires_at (expires_at),
                FULLTEXT KEY opportunity_search (title, company, description)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'Stores job opportunities and matches'
        );
        
        // File uploads
        $this->tables['file_uploads'] = array(
            'name' => $wpdb->prefix . 'sffc_file_uploads',
            'sql' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_file_uploads (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL,
                conversation_id bigint(20) unsigned DEFAULT NULL,
                file_name varchar(500) NOT NULL,
                file_type varchar(50) NOT NULL,
                file_size int(11) DEFAULT 0,
                file_path text NOT NULL,
                analysis_data longtext,
                extraction_data longtext,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY conversation_id (conversation_id),
                KEY file_type (file_type),
                KEY created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'Tracks uploaded files and their analysis'
        );

        // Dashboard Analytics Cache (Phase 1.3)
        $this->tables['dashboard_analytics_cache'] = array(
            'name' => $wpdb->prefix . 'sffc_dashboard_analytics_cache',
            'sql' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_dashboard_analytics_cache (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL,
                cache_key varchar(100) NOT NULL,
                cache_type enum('match_score','skills_demand','market_position','trends','salary','skills_analysis') NOT NULL,
                cache_data longtext NOT NULL,
                computed_at datetime NOT NULL,
                expires_at datetime NOT NULL,
                is_valid tinyint(1) DEFAULT 1,
                computation_time_ms int(11) DEFAULT 0,
                metadata longtext,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY user_cache_key (user_id, cache_key),
                KEY user_id (user_id),
                KEY cache_type (cache_type),
                KEY expires_at (expires_at),
                KEY is_valid (is_valid),
                KEY computed_at (computed_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'Caches pre-computed dashboard analytics for faster loading'
        );

        // User Skills table (for dashboard skills analysis)
        $this->tables['user_skills'] = array(
            'name' => $wpdb->prefix . 'sffc_user_skills',
            'sql' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_user_skills (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL,
                skill_name varchar(200) NOT NULL,
                skill_category varchar(100) DEFAULT NULL,
                proficiency_level enum('beginner','intermediate','advanced','expert') DEFAULT 'intermediate',
                years_experience decimal(4,1) DEFAULT 0,
                is_primary tinyint(1) DEFAULT 0,
                demand_score int(11) DEFAULT NULL,
                demand_trend enum('up','stable','down') DEFAULT NULL,
                last_demand_update datetime DEFAULT NULL,
                verified tinyint(1) DEFAULT 0,
                source varchar(50) DEFAULT 'user_input',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY user_skill (user_id, skill_name),
                KEY user_id (user_id),
                KEY skill_category (skill_category),
                KEY demand_score (demand_score),
                KEY is_primary (is_primary),
                FULLTEXT KEY skill_search (skill_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'Stores user skills with demand data for dashboard analysis'
        );

        // Saved Articles table (for market intelligence)
        $this->tables['saved_articles'] = array(
            'name' => $wpdb->prefix . 'sffc_saved_articles',
            'sql' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_saved_articles (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL,
                article_id bigint(20) unsigned NOT NULL,
                article_type enum('news','deal','research','job') NOT NULL DEFAULT 'news',
                article_title varchar(500) DEFAULT NULL,
                article_url text,
                notes text,
                tags varchar(500) DEFAULT NULL,
                is_pinned tinyint(1) DEFAULT 0,
                read_status tinyint(1) DEFAULT 0,
                saved_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY user_article (user_id, article_id, article_type),
                KEY user_id (user_id),
                KEY article_type (article_type),
                KEY is_pinned (is_pinned),
                KEY saved_at (saved_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'Tracks user saved articles and news items'
        );

        // News Source Preferences table (for pin/hide functionality)
        $this->tables['news_source_preferences'] = array(
            'name' => $wpdb->prefix . 'sffc_news_source_preferences',
            'sql' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_news_source_preferences (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL,
                source_id varchar(100) NOT NULL,
                source_name varchar(255) NOT NULL,
                preference enum('pinned','hidden','normal') DEFAULT 'normal',
                priority_score int(11) DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY user_source (user_id, source_id),
                KEY user_id (user_id),
                KEY preference (preference)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'Stores user preferences for news sources (pinned/hidden)'
        );

        // Custom Alert Keywords table
        $this->tables['alert_keywords'] = array(
            'name' => $wpdb->prefix . 'sffc_alert_keywords',
            'sql' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_alert_keywords (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL,
                keyword varchar(200) NOT NULL,
                keyword_type enum('company','topic','skill','location','person') DEFAULT 'topic',
                alert_enabled tinyint(1) DEFAULT 1,
                alert_frequency enum('instant','daily','weekly') DEFAULT 'daily',
                match_count int(11) DEFAULT 0,
                last_match_at datetime DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY user_keyword (user_id, keyword),
                KEY user_id (user_id),
                KEY keyword_type (keyword_type),
                KEY alert_enabled (alert_enabled),
                FULLTEXT KEY keyword_search (keyword)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'Custom alert keywords for personalized news monitoring'
        );

        // ============================================
        // CRM TABLES - Recruiter CRM System
        // ============================================

        $this->tables['crm_recruiters'] = array(
            'name' => $wpdb->prefix . 'sffc_crm_recruiters',
            'sql' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_crm_recruiters (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                name varchar(200) NOT NULL,
                first_name varchar(100),
                last_name varchar(100),
                photo_url varchar(500),
                title varchar(200),
                firm varchar(200),
                firm_type enum('search_firm','corporate','independent','agency') DEFAULT 'search_firm',
                email varchar(200),
                email_verified tinyint(1) DEFAULT 0,
                linkedin_url varchar(500),
                linkedin_id varchar(100),
                website varchar(500),
                phone varchar(50),
                location varchar(200),
                sectors longtext,
                seniority_levels longtext,
                regions longtext,
                bio text,
                response_rate decimal(5,2),
                avg_response_days int(11),
                total_posts int(11) DEFAULT 0,
                last_post_date datetime,
                is_verified tinyint(1) DEFAULT 0,
                is_active tinyint(1) DEFAULT 1,
                data_source varchar(50),
                source_url varchar(500),
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_firm (firm),
                KEY idx_location (location),
                KEY idx_active (is_active),
                KEY idx_last_post (last_post_date),
                KEY idx_linkedin (linkedin_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'CRM: Recruiters - People candidates reach out to'
        );

        $this->tables['crm_posts'] = array(
            'name' => $wpdb->prefix . 'sffc_crm_posts',
            'sql' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_crm_posts (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                recruiter_id bigint(20) NOT NULL,
                source enum('linkedin','website','manual','import') DEFAULT 'linkedin',
                source_url varchar(500),
                source_id varchar(100),
                role_title varchar(300),
                role_title_standardized varchar(300),
                company varchar(200),
                location varchar(200),
                location_country varchar(100),
                location_city varchar(100),
                salary_min int(11),
                salary_max int(11),
                salary_currency varchar(10) DEFAULT 'USD',
                salary_text varchar(100),
                seniority enum('analyst','associate','vp','director','md','partner','c_level','other'),
                sector varchar(100),
                content text NOT NULL,
                content_snippet varchar(500),
                requirements longtext,
                skills_mentioned longtext,
                experience_years varchar(50),
                is_remote tinyint(1) DEFAULT 0,
                is_hybrid tinyint(1) DEFAULT 0,
                engagement_count int(11) DEFAULT 0,
                posted_at datetime,
                expires_at datetime,
                is_active tinyint(1) DEFAULT 1,
                is_featured tinyint(1) DEFAULT 0,
                admin_approved tinyint(1) DEFAULT 1,
                admin_notes text,
                application_url varchar(500),
                wp_post_id bigint(20),
                jobs_post_id bigint(20),
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_recruiter (recruiter_id),
                KEY idx_posted (posted_at),
                KEY idx_active (is_active),
                KEY idx_approved (admin_approved),
                KEY idx_sector (sector),
                KEY idx_seniority (seniority)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'CRM: Recruiter posts/opportunities'
        );

        $this->tables['crm_saved_recruiters'] = array(
            'name' => $wpdb->prefix . 'sffc_crm_saved_recruiters',
            'sql' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_crm_saved_recruiters (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                recruiter_id bigint(20) NOT NULL,
                status enum('new','contacted','replied','in_conversation','dormant') DEFAULT 'new',
                notes text,
                tags varchar(500),
                priority enum('low','medium','high') DEFAULT 'medium',
                saved_at datetime DEFAULT CURRENT_TIMESTAMP,
                last_contacted_at datetime,
                last_reply_at datetime,
                next_followup_at datetime,
                PRIMARY KEY (id),
                KEY idx_user (user_id),
                KEY idx_recruiter (recruiter_id),
                KEY idx_status (status),
                KEY idx_followup (next_followup_at),
                UNIQUE KEY unique_user_recruiter (user_id, recruiter_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'CRM: User saved/bookmarked recruiters'
        );

        $this->tables['crm_saved_posts'] = array(
            'name' => $wpdb->prefix . 'sffc_crm_saved_posts',
            'sql' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_crm_saved_posts (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                post_id bigint(20) NOT NULL,
                recruiter_id bigint(20),
                folder varchar(100) DEFAULT 'default',
                notes text,
                saved_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_user (user_id),
                KEY idx_post (post_id),
                KEY idx_folder (folder),
                UNIQUE KEY unique_user_post (user_id, post_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'CRM: User saved opportunity posts'
        );

        $this->tables['crm_pipeline'] = array(
            'name' => $wpdb->prefix . 'sffc_crm_pipeline',
            'sql' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_crm_pipeline (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                recruiter_id bigint(20) NOT NULL,
                post_id bigint(20),
                stage enum('not_applied','interested','application_submitted','messaged','follow_up','online_assessment','case_study','hirevue','telephone_interview','video_interview','face_to_face_interview','assessment_centre','offer_received','rejected','not_interested') DEFAULT 'not_applied',
                outcome enum('won','lost','withdrawn') DEFAULT NULL,
                outcome_reason varchar(200),
                role_title varchar(300),
                company varchar(200),
                location varchar(200),
                salary_min int(11),
                salary_max int(11),
                notes text,
                next_action text,
                next_action_date datetime,
                cv_version_sent text,
                cover_letter_sent text,
                stage_entered_at datetime DEFAULT CURRENT_TIMESTAMP,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                closed_at datetime,
                PRIMARY KEY (id),
                KEY idx_user (user_id),
                KEY idx_recruiter (recruiter_id),
                KEY idx_stage (stage),
                KEY idx_outcome (outcome),
                KEY idx_next_action (next_action_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'CRM: Pipeline - Track opportunities through stages'
        );

        $this->tables['crm_pipeline_history'] = array(
            'name' => $wpdb->prefix . 'sffc_crm_pipeline_history',
            'sql' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_crm_pipeline_history (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                pipeline_id bigint(20) NOT NULL,
                from_stage varchar(50),
                to_stage varchar(50) NOT NULL,
                notes text,
                transitioned_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_pipeline (pipeline_id),
                KEY idx_date (transitioned_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'CRM: Pipeline stage transition history'
        );

        $this->tables['crm_outreach'] = array(
            'name' => $wpdb->prefix . 'sffc_crm_outreach',
            'sql' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_crm_outreach (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                recruiter_id bigint(20) NOT NULL,
                post_id bigint(20),
                pipeline_id bigint(20),
                sequence_id bigint(20),
                sequence_step int(11),
                channel enum('email','linkedin') NOT NULL,
                message_type enum('initial','followup','reply','connection_request') DEFAULT 'initial',
                subject varchar(500),
                content text NOT NULL,
                content_html text,
                template_id bigint(20),
                status enum('draft','scheduled','sent','opened','clicked','replied','bounced','failed') DEFAULT 'draft',
                scheduled_at datetime,
                sent_at datetime,
                opened_at datetime,
                clicked_at datetime,
                replied_at datetime,
                open_count int(11) DEFAULT 0,
                click_count int(11) DEFAULT 0,
                tracking_id varchar(100),
                error_message text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_user (user_id),
                KEY idx_recruiter (recruiter_id),
                KEY idx_status (status),
                KEY idx_channel (channel),
                KEY idx_sent (sent_at),
                KEY idx_sequence (sequence_id, sequence_step),
                KEY idx_tracking (tracking_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'CRM: Outreach messages sent to recruiters'
        );

        $this->tables['crm_templates'] = array(
            'name' => $wpdb->prefix . 'sffc_crm_templates',
            'sql' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_crm_templates (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20),
                name varchar(200) NOT NULL,
                description text,
                channel enum('email','linkedin','both') DEFAULT 'email',
                template_type enum('initial','followup','connection','thank_you','custom') DEFAULT 'initial',
                subject varchar(500),
                content text NOT NULL,
                variables_used longtext,
                is_system tinyint(1) DEFAULT 0,
                is_active tinyint(1) DEFAULT 1,
                usage_count int(11) DEFAULT 0,
                reply_rate decimal(5,2),
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_user (user_id),
                KEY idx_type (template_type),
                KEY idx_channel (channel),
                KEY idx_system (is_system)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'CRM: Reusable message templates'
        );

        $this->tables['crm_activity'] = array(
            'name' => $wpdb->prefix . 'sffc_crm_activity',
            'sql' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_crm_activity (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                recruiter_id bigint(20),
                post_id bigint(20),
                pipeline_id bigint(20),
                activity_type varchar(50) NOT NULL,
                activity_data longtext,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_user (user_id),
                KEY idx_recruiter (recruiter_id),
                KEY idx_type (activity_type),
                KEY idx_date (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'CRM: Activity log for all user actions'
        );

        $this->tables['crm_notes'] = array(
            'name' => $wpdb->prefix . 'sffc_crm_notes',
            'sql' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_crm_notes (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                entity_type enum('recruiter','post','pipeline') NOT NULL,
                entity_id bigint(20) NOT NULL,
                content text NOT NULL,
                is_pinned tinyint(1) DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_user (user_id),
                KEY idx_entity (entity_type, entity_id),
                KEY idx_pinned (is_pinned)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'CRM: Notes on recruiters, posts, pipeline items'
        );

        $this->tables['crm_usage'] = array(
            'name' => $wpdb->prefix . 'sffc_crm_usage',
            'sql' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_crm_usage (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                period_month varchar(7) NOT NULL,
                outreach_count int(11) DEFAULT 0,
                ai_generation_count int(11) DEFAULT 0,
                posts_viewed int(11) DEFAULT 0,
                recruiters_saved int(11) DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY unique_user_month (user_id, period_month)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'CRM: Usage tracking for limit enforcement'
        );

        $this->tables['crm_tags'] = array(
            'name' => $wpdb->prefix . 'sffc_crm_tags',
            'sql' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_crm_tags (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                name varchar(100) NOT NULL,
                color varchar(7) DEFAULT '#6b7280',
                usage_count int(11) DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_user (user_id),
                UNIQUE KEY unique_user_tag (user_id, name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'CRM: User-defined tags for organizing recruiters'
        );

        $this->tables['crm_recruiter_tags'] = array(
            'name' => $wpdb->prefix . 'sffc_crm_recruiter_tags',
            'sql' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_crm_recruiter_tags (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                recruiter_id bigint(20) NOT NULL,
                tag_id bigint(20) NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_user_recruiter (user_id, recruiter_id),
                KEY idx_tag (tag_id),
                UNIQUE KEY unique_recruiter_tag (user_id, recruiter_id, tag_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'CRM: Junction table for recruiter-tag relationships'
        );

        $this->tables['crm_tasks'] = array(
            'name' => $wpdb->prefix . 'sffc_crm_tasks',
            'sql' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_crm_tasks (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                recruiter_id bigint(20),
                post_id bigint(20),
                pipeline_id bigint(20),
                sequence_id bigint(20),
                enrollment_id bigint(20),
                step_index int(11),
                task_type enum('follow_up','send_email','linkedin_message','linkedin_connect','research','interview_prep','custom') DEFAULT 'custom',
                title varchar(300) NOT NULL,
                description text,
                pre_filled_subject varchar(500),
                pre_filled_content text,
                template_id bigint(20),
                due_date datetime,
                due_time time,
                completed_at datetime,
                skipped_at datetime,
                snoozed_until datetime,
                is_auto_generated tinyint(1) DEFAULT 0,
                priority enum('low','medium','high') DEFAULT 'medium',
                status enum('pending','completed','skipped','snoozed') DEFAULT 'pending',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_user (user_id),
                KEY idx_due (due_date),
                KEY idx_status (status),
                KEY idx_completed (completed_at),
                KEY idx_recruiter (recruiter_id),
                KEY idx_enrollment (enrollment_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'CRM: User tasks and reminders'
        );

        $this->tables['crm_sequences'] = array(
            'name' => $wpdb->prefix . 'sffc_crm_sequences',
            'sql' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_crm_sequences (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                name varchar(200) NOT NULL,
                description text,
                is_active tinyint(1) DEFAULT 1,
                is_system tinyint(1) DEFAULT 0,
                trigger_type enum('manual','on_save','on_outreach') DEFAULT 'manual',
                settings longtext,
                enrolled_count int(11) DEFAULT 0,
                completed_count int(11) DEFAULT 0,
                replied_count int(11) DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_user (user_id),
                KEY idx_active (is_active),
                KEY idx_system (is_system)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'CRM: Automated outreach sequences'
        );

        $this->tables['crm_sequence_steps'] = array(
            'name' => $wpdb->prefix . 'sffc_crm_sequence_steps',
            'sql' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_crm_sequence_steps (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                sequence_id bigint(20) NOT NULL,
                step_order int(11) NOT NULL DEFAULT 0,
                step_type enum('auto_email','manual_email','linkedin_message','linkedin_connect','wait','task') NOT NULL,
                delay_days int(11) DEFAULT 0,
                delay_hours int(11) DEFAULT 0,
                channel enum('email','linkedin') DEFAULT 'email',
                template_id bigint(20),
                subject varchar(500),
                content text,
                task_title varchar(300),
                task_description text,
                send_time_preference enum('morning','afternoon','evening','any') DEFAULT 'morning',
                skip_weekends tinyint(1) DEFAULT 1,
                is_active tinyint(1) DEFAULT 1,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_sequence (sequence_id),
                KEY idx_order (sequence_id, step_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'CRM: Individual steps within a sequence'
        );

        $this->tables['crm_sequence_enrollments'] = array(
            'name' => $wpdb->prefix . 'sffc_crm_sequence_enrollments',
            'sql' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_crm_sequence_enrollments (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                sequence_id bigint(20) NOT NULL,
                user_id bigint(20) NOT NULL,
                recruiter_id bigint(20) NOT NULL,
                post_id bigint(20),
                current_step_index int(11) DEFAULT 0,
                status enum('active','paused','completed','replied','bounced','unsubscribed') DEFAULT 'active',
                enrolled_at datetime DEFAULT CURRENT_TIMESTAMP,
                next_step_at datetime,
                last_step_at datetime,
                completed_at datetime,
                paused_at datetime,
                pause_reason varchar(200),
                replied_at datetime,
                personalization_data longtext,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_sequence (sequence_id),
                KEY idx_user (user_id),
                KEY idx_recruiter (recruiter_id),
                KEY idx_status (status),
                KEY idx_next_step (next_step_at),
                UNIQUE KEY unique_enrollment (sequence_id, user_id, recruiter_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'CRM: Recruiters enrolled in sequences'
        );

        $this->tables['crm_enrollment_history'] = array(
            'name' => $wpdb->prefix . 'sffc_crm_enrollment_history',
            'sql' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_crm_enrollment_history (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                enrollment_id bigint(20) NOT NULL,
                step_index int(11) NOT NULL,
                step_type varchar(50) NOT NULL,
                action_taken enum('executed','skipped','failed') NOT NULL,
                outreach_id bigint(20),
                task_id bigint(20),
                notes text,
                executed_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_enrollment (enrollment_id),
                KEY idx_step (enrollment_id, step_index)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'CRM: Step executions within enrollments'
        );

        $this->tables['crm_conversations'] = array(
            'name' => $wpdb->prefix . 'sffc_crm_conversations',
            'sql' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_crm_conversations (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                recruiter_id bigint(20),
                post_id bigint(20),
                subject varchar(500),
                channel enum('email','linkedin','manual') DEFAULT 'email',
                status enum('active','archived','spam') DEFAULT 'active',
                is_read tinyint(1) DEFAULT 0,
                is_starred tinyint(1) DEFAULT 0,
                last_message_at datetime,
                last_message_preview varchar(200),
                last_message_direction enum('inbound','outbound') DEFAULT 'outbound',
                message_count int(11) DEFAULT 0,
                unread_count int(11) DEFAULT 0,
                thread_id varchar(200),
                labels varchar(500),
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_user (user_id),
                KEY idx_recruiter (recruiter_id),
                KEY idx_status (status),
                KEY idx_read (is_read),
                KEY idx_last_message (last_message_at),
                KEY idx_thread (thread_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'CRM: Email threads/conversations with recruiters'
        );

        $this->tables['crm_messages'] = array(
            'name' => $wpdb->prefix . 'sffc_crm_messages',
            'sql' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_crm_messages (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                conversation_id bigint(20) NOT NULL,
                user_id bigint(20) NOT NULL,
                recruiter_id bigint(20),
                outreach_id bigint(20),
                direction enum('inbound','outbound') NOT NULL,
                channel enum('email','linkedin','manual') DEFAULT 'email',
                from_email varchar(200),
                from_name varchar(200),
                to_email varchar(200),
                to_name varchar(200),
                subject varchar(500),
                content text NOT NULL,
                content_html text,
                content_snippet varchar(200),
                is_read tinyint(1) DEFAULT 0,
                read_at datetime,
                external_id varchar(200),
                external_thread_id varchar(200),
                headers longtext,
                attachments longtext,
                sent_at datetime,
                received_at datetime,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_conversation (conversation_id),
                KEY idx_user (user_id),
                KEY idx_recruiter (recruiter_id),
                KEY idx_direction (direction),
                KEY idx_read (is_read),
                KEY idx_sent (sent_at),
                KEY idx_external (external_id),
                KEY idx_outreach (outreach_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'CRM: Individual messages within conversations'
        );

        $this->tables['crm_email_accounts'] = array(
            'name' => $wpdb->prefix . 'sffc_crm_email_accounts',
            'sql' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_crm_email_accounts (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                provider enum('gmail','outlook','other') NOT NULL,
                email_address varchar(200) NOT NULL,
                display_name varchar(200),
                access_token text,
                refresh_token text,
                token_expires_at datetime,
                sync_enabled tinyint(1) DEFAULT 1,
                last_sync_at datetime,
                last_sync_error text,
                sync_from_date datetime,
                settings longtext,
                is_primary tinyint(1) DEFAULT 0,
                is_active tinyint(1) DEFAULT 1,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_user (user_id),
                KEY idx_provider (provider),
                KEY idx_email (email_address),
                KEY idx_sync (sync_enabled, last_sync_at),
                UNIQUE KEY unique_user_email (user_id, email_address)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'CRM: Connected email accounts (Gmail/Outlook)'
        );

        $this->tables['crm_alerts'] = array(
            'name' => $wpdb->prefix . 'sffc_crm_alerts',
            'sql' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_crm_alerts (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                type varchar(50) NOT NULL,
                name varchar(200) NOT NULL,
                config longtext,
                is_active tinyint(1) DEFAULT 1,
                email_enabled tinyint(1) DEFAULT 1,
                push_enabled tinyint(1) DEFAULT 0,
                last_triggered_at datetime,
                trigger_count int DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_user (user_id),
                KEY idx_type (type),
                KEY idx_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'CRM: User-configured alerts for posts, keywords'
        );

        $this->tables['crm_notifications'] = array(
            'name' => $wpdb->prefix . 'sffc_crm_notifications',
            'sql' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_crm_notifications (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                alert_id bigint(20),
                type varchar(50) NOT NULL,
                title varchar(255) NOT NULL,
                message text,
                data longtext,
                is_read tinyint(1) DEFAULT 0,
                read_at datetime,
                action_url varchar(500),
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_user_read (user_id, is_read),
                KEY idx_alert (alert_id),
                KEY idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'CRM: Triggered notifications from alerts'
        );
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Admin AJAX for table creation
        add_action('wp_ajax_sffc_create_table', array($this, 'ajax_create_table'));
        add_action('wp_ajax_sffc_create_all_tables', array($this, 'ajax_create_all_tables'));
        add_action('wp_ajax_sffc_check_tables', array($this, 'ajax_check_tables'));
        add_action('wp_ajax_sffc_drop_table', array($this, 'ajax_drop_table'));
    }
    
    /**
     * Create a single table
     */
    public function create_table($table_key) {
        global $wpdb;
        
        if (!isset($this->tables[$table_key])) {
            return array(
                'success' => false,
                'message' => 'Table definition not found'
            );
        }
        
        $table = $this->tables[$table_key];
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Execute the SQL
        dbDelta($table['sql']);
        
        // Check if table was created
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table['name']}'") == $table['name']) {
            return array(
                'success' => true,
                'message' => "Table {$table['name']} created successfully",
                'table_name' => $table['name']
            );
        } else {
            return array(
                'success' => false,
                'message' => "Failed to create table {$table['name']}",
                'error' => $wpdb->last_error
            );
        }
    }
    
    /**
     * Create all tables
     */
    public function create_all_tables() {
        $results = array();
        
        foreach (array_keys($this->tables) as $table_key) {
            $results[$table_key] = $this->create_table($table_key);
        }
        
        return $results;
    }
    
    /**
     * Check which tables exist
     */
    public function check_tables() {
        global $wpdb;
        
        $status = array();
        
        foreach ($this->tables as $key => $table) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '{$table['name']}'") == $table['name'];
            
            if ($exists) {
                // Get row count
                $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table['name']}");
                $status[$key] = array(
                    'exists' => true,
                    'name' => $table['name'],
                    'row_count' => $count,
                    'description' => $table['description']
                );
            } else {
                $status[$key] = array(
                    'exists' => false,
                    'name' => $table['name'],
                    'description' => $table['description']
                );
            }
        }
        
        return $status;
    }
    
    /**
     * Drop a table (with confirmation)
     */
    public function drop_table($table_key, $confirm = false) {
        global $wpdb;
        
        if (!$confirm) {
            return array(
                'success' => false,
                'message' => 'Confirmation required to drop table'
            );
        }
        
        if (!isset($this->tables[$table_key])) {
            return array(
                'success' => false,
                'message' => 'Table definition not found'
            );
        }
        
        $table = $this->tables[$table_key];
        
        // Drop the table
        $wpdb->query("DROP TABLE IF EXISTS {$table['name']}");
        
        // Check if dropped
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table['name']}'") != $table['name']) {
            return array(
                'success' => true,
                'message' => "Table {$table['name']} dropped successfully"
            );
        } else {
            return array(
                'success' => false,
                'message' => "Failed to drop table {$table['name']}"
            );
        }
    }
    
    /**
     * AJAX handlers
     */
    public function ajax_create_table() {
        check_ajax_referer('sffc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $table_key = sanitize_text_field($_POST['table_key']);
        $result = $this->create_table($table_key);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    public function ajax_create_all_tables() {
        check_ajax_referer('sffc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $results = $this->create_all_tables();
        wp_send_json_success($results);
    }
    
    public function ajax_check_tables() {
        check_ajax_referer('sffc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $status = $this->check_tables();
        wp_send_json_success($status);
    }
    
    public function ajax_drop_table() {
        check_ajax_referer('sffc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $table_key = sanitize_text_field($_POST['table_key']);
        $confirm = isset($_POST['confirm']) && $_POST['confirm'] === 'true';
        
        $result = $this->drop_table($table_key, $confirm);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Render admin interface
     */
    public function render_admin_page() {
        $table_status = $this->check_tables();
        ?>
        <div class="wrap">
            <h1>Database Table Management</h1>
            
            <div class="sffc-admin-notice">
                <p><strong>Important:</strong> Create database tables manually here to avoid activation issues.</p>
            </div>
            
            <div class="sffc-table-actions" style="margin: 20px 0;">
                <button id="sffc-create-all-tables" class="button button-primary">
                    Create All Tables
                </button>
                <button id="sffc-check-tables" class="button">
                    Refresh Status
                </button>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Table</th>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Rows</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($table_status as $key => $table): ?>
                    <tr>
                        <td><strong><?php echo esc_html($key); ?></strong></td>
                        <td><code><?php echo esc_html($table['name']); ?></code></td>
                        <td>
                            <?php if ($table['exists']): ?>
                                <span style="color: green;">✓ Exists</span>
                            <?php else: ?>
                                <span style="color: red;">✗ Missing</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo $table['exists'] ? esc_html($table['row_count']) : '-'; ?>
                        </td>
                        <td><?php echo esc_html($table['description']); ?></td>
                        <td>
                            <?php if (!$table['exists']): ?>
                                <button class="button sffc-create-table" data-table="<?php echo esc_attr($key); ?>">
                                    Create
                                </button>
                            <?php else: ?>
                                <button class="button sffc-drop-table" data-table="<?php echo esc_attr($key); ?>" 
                                        style="color: red;" onclick="return confirm('Are you sure?');">
                                    Drop
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <style>
            .sffc-admin-notice {
                background: #fff;
                border-left: 4px solid #2271b1;
                padding: 12px;
                margin: 20px 0;
            }
            .sffc-table-actions button {
                margin-right: 10px;
            }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Create all tables
            $('#sffc-create-all-tables').on('click', function() {
                const button = $(this);
                button.prop('disabled', true).text('Creating...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sffc_create_all_tables',
                        nonce: '<?php echo wp_create_nonce('sffc_admin_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Tables created successfully!');
                            location.reload();
                        } else {
                            alert('Error creating tables');
                        }
                    },
                    complete: function() {
                        button.prop('disabled', false).text('Create All Tables');
                    }
                });
            });
            
            // Create single table
            $('.sffc-create-table').on('click', function() {
                const button = $(this);
                const table_key = button.data('table');
                
                button.prop('disabled', true).text('Creating...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sffc_create_table',
                        table_key: table_key,
                        nonce: '<?php echo wp_create_nonce('sffc_admin_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Table created successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + response.data.message);
                        }
                    },
                    complete: function() {
                        button.prop('disabled', false).text('Create');
                    }
                });
            });
            
            // Check tables
            $('#sffc-check-tables').on('click', function() {
                location.reload();
            });
        });
        </script>
        <?php
    }
}
