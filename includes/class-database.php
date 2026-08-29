<?php
/**
 * Database handler class
 * 
 * @package SennaCareers
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Database {
    
    /**
     * Database version
     */
    const DB_VERSION = '1.0.0';
    
    /**
     * Table names
     */
    private $tables = array();
    
    /**
     * Singleton instance
     */
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
     * Phase 16: Add database indexes for optimization
     */
    public function add_performance_indexes() {
        global $wpdb;
        
        // Add indexes for frequently queried columns
        $indexes = array(
            array($this->tables['conversations'], 'user_id', 'idx_user_id'),
            array($this->tables['conversations'], 'created_at', 'idx_created_at'),
            array($this->tables['messages'], 'conversation_id', 'idx_conversation_id'),
            array($this->tables['messages'], 'created_at', 'idx_msg_created_at'),
            array($this->tables['user_profiles'], 'user_id', 'idx_profile_user_id')
        );
        
        foreach ($indexes as $index) {
            // Validate index array has all required elements
            if (!is_array($index) || count($index) < 3) {
                error_log('SFFC: Invalid index configuration, skipping');
                continue;
            }
            
            list($table, $column, $index_name) = $index;
            
            // Validate all parameters are non-empty strings
            if (empty($table) || empty($column) || empty($index_name)) {
                error_log('SFFC: Empty index parameters - table: ' . $table . ', column: ' . $column . ', index: ' . $index_name);
                continue;
            }
            
            // Check if table exists first
            $table_exists = $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table
            ));
            
            if (!$table_exists) {
                error_log('SFFC: Table does not exist for index: ' . $table);
                continue;
            }
            
            // Check if column exists in the table
            $column_exists = $wpdb->get_var($wpdb->prepare(
                "SHOW COLUMNS FROM `{$table}` LIKE %s",
                $column
            ));
            
            if (!$column_exists) {
                error_log('SFFC: Column ' . $column . ' does not exist in table ' . $table);
                continue;
            }
            
            // Check if index already exists
            $index_exists = $wpdb->get_var($wpdb->prepare(
                "SHOW INDEX FROM `{$table}` WHERE Key_name = %s",
                $index_name
            ));
            
            if (!$index_exists) {
                // Safe table and column names - validated from our known list
                $safe_table = esc_sql($table);
                $safe_index = esc_sql($index_name);
                $safe_column = esc_sql($column);
                
                // Only proceed if all values are non-empty after escaping
                if ($safe_table && $safe_index && $safe_column) {
                    $query = "ALTER TABLE `{$safe_table}` ADD INDEX `{$safe_index}` (`{$safe_column}`)";
                    $result = $wpdb->query($query);
                    
                    if ($result === false) {
                        error_log('SFFC: Failed to add index ' . $index_name . ' to table ' . $table . ': ' . $wpdb->last_error);
                    }
                } else {
                    error_log('SFFC: Invalid escaped values for index creation');
                }
            }
        }
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        global $wpdb;
        
        // Define table names (as per IMPLEMENTATION-PLAN.md)
        $this->tables = array(
            'conversations' => $wpdb->prefix . 'sffc_conversations',
            'messages' => $wpdb->prefix . 'sffc_messages',
            'user_profiles' => $wpdb->prefix . 'sffc_user_profiles',
            'expert_availability' => $wpdb->prefix . 'sffc_expert_availability',
            'opportunities' => $wpdb->prefix . 'sffc_opportunities',
            'file_uploads' => $wpdb->prefix . 'sffc_file_uploads',
            'message_templates' => $wpdb->prefix . 'sffc_message_templates',
            'xml_feeds' => $wpdb->prefix . 'sffc_xml_feeds',
            // CV Tailoring tables
            'cv_master' => $wpdb->prefix . 'sffc_cv_master',
            'cv_versions' => $wpdb->prefix . 'sffc_cv_versions',
            'job_analysis' => $wpdb->prefix . 'sffc_job_analysis',
            'cv_analytics' => $wpdb->prefix . 'sffc_cv_analytics',
            // CV Upload Handler tables
            'cv_uploads' => $wpdb->prefix . 'sffc_cv_uploads',
            'parsed_profiles' => $wpdb->prefix . 'sffc_parsed_profiles',
            'autofill_tokens' => $wpdb->prefix . 'sffc_autofill_tokens',
            'platform_patterns' => $wpdb->prefix . 'sffc_platform_patterns',
            'autofill_applications' => $wpdb->prefix . 'sffc_autofill_applications'
        );
    }
    
    /**
     * Get table name
     */
    public function get_table($name) {
        return isset($this->tables[$name]) ? $this->tables[$name] : false;
    }
    
    /**
     * Create all database tables
     */
    public function create_tables() {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $results = array();
        
        // Create each table
        $results['conversations'] = $this->create_conversations_table();
        $results['messages'] = $this->create_messages_table();
        $results['user_profiles'] = $this->create_user_profiles_table();
        $results['expert_availability'] = $this->create_expert_availability_table();
        $results['opportunities'] = $this->create_opportunities_table();
        $results['file_uploads'] = $this->create_file_uploads_table();
        $results['message_templates'] = $this->create_message_templates_table();
        $results['xml_feeds'] = $this->create_xml_feeds_table();
        
        // Create CV Tailoring tables
        $results['cv_master'] = $this->create_cv_master_table();
        $results['cv_versions'] = $this->create_cv_versions_table();
        $results['job_analysis'] = $this->create_job_analysis_table();
        $results['cv_analytics'] = $this->create_cv_analytics_table();
        
        // Create CV Upload Handler tables
        $results['cv_uploads'] = $this->create_cv_uploads_table();
        $results['parsed_profiles'] = $this->create_parsed_profiles_table();
        $results['autofill_tokens'] = $this->create_autofill_tokens_table();
        $results['platform_patterns'] = $this->create_platform_patterns_table();
        $results['autofill_applications'] = $this->create_autofill_applications_table();
        

        if (class_exists('SFFC_Company_Database_Setup')) {
            SFFC_Company_Database_Setup::create_tables();
            $company_tables = SFFC_Company_Database_Setup::get_table_status();
            foreach ($company_tables as $table => $exists) {
                $key = str_replace($wpdb->prefix, '', $table);
                $results[$key] = $exists ? 'created' : 'missing';
            }
        }

        // Update database version
        update_option('sffc_db_version', self::DB_VERSION);
        
        return $results;
    }
    
    /**
     * Create conversations table
     */
    private function create_conversations_table() {
        global $wpdb;
        
        $table_name = $this->tables['conversations'];
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            mode varchar(50) NOT NULL DEFAULT 'career',
            status varchar(20) NOT NULL DEFAULT 'active',
            metadata longtext,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX user_id (user_id),
            INDEX mode (mode),
            INDEX status (status),
            INDEX created_at (created_at)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        return $this->table_exists('conversations');
    }
    
    /**
     * Create messages table
     */
    private function create_messages_table() {
        global $wpdb;
        
        $table_name = $this->tables['messages'];
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            conversation_id bigint(20) UNSIGNED NOT NULL,
            sender_type varchar(20) NOT NULL,
            sender_id bigint(20) UNSIGNED,
            message longtext NOT NULL,
            metadata longtext,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX conversation_id (conversation_id),
            INDEX sender_type (sender_type),
            INDEX created_at (created_at)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        return $this->table_exists('messages');
    }
    
    /**
     * Create user profiles table
     */
    private function create_user_profiles_table() {
        global $wpdb;
        
        $table_name = $this->tables['user_profiles'];
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            professional_data longtext,
            preferences longtext,
            cv_data longtext,
            last_mode varchar(50),
            total_conversations int(11) DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id),
            INDEX updated_at (updated_at)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        return $this->table_exists('user_profiles');
    }
    
    /**
     * Create expert availability table
     */
    private function create_expert_availability_table() {
        global $wpdb;
        
        $table_name = $this->tables['expert_availability'];
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            expert_id bigint(20) UNSIGNED NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'offline',
            queue_count int(11) DEFAULT 0,
            last_active datetime,
            metadata longtext,
            PRIMARY KEY (id),
            UNIQUE KEY expert_id (expert_id),
            INDEX status (status),
            INDEX last_active (last_active)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        return $this->table_exists('expert_availability');
    }
    
    /**
     * Create opportunities table
     */
    private function create_opportunities_table() {
        global $wpdb;
        
        $table_name = $this->tables['opportunities'];
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            company varchar(255) NOT NULL,
            location varchar(255),
            requirements longtext,
            description longtext,
            salary_range varchar(100),
            job_type varchar(50),
            experience_level varchar(50),
            metadata longtext,
            source varchar(100),
            source_url text,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime,
            PRIMARY KEY (id),
            INDEX is_active (is_active),
            INDEX company (company),
            INDEX location (location),
            INDEX job_type (job_type),
            INDEX created_at (created_at),
            FULLTEXT KEY search (title, company, description, requirements)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        return $this->table_exists('opportunities');
    }
    
    /**
     * Create file uploads table
     */
    private function create_file_uploads_table() {
        global $wpdb;
        
        $table_name = $this->tables['file_uploads'];
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            conversation_id bigint(20) UNSIGNED,
            file_name varchar(255) NOT NULL,
            file_type varchar(50) NOT NULL,
            file_path text NOT NULL,
            file_size bigint(20),
            analysis_data longtext,
            metadata longtext,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX user_id (user_id),
            INDEX conversation_id (conversation_id),
            INDEX file_type (file_type),
            INDEX created_at (created_at)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        return $this->table_exists('file_uploads');
    }
    
    /**
     * Create message templates table
     */
    private function create_message_templates_table() {
        global $wpdb;
        
        $table_name = $this->tables['message_templates'];
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            category varchar(50) NOT NULL,
            mode varchar(50) NOT NULL,
            template_text text NOT NULL,
            variables text,
            usage_count int(11) DEFAULT 0,
            last_used datetime,
            is_active tinyint(1) DEFAULT 1,
            PRIMARY KEY (id),
            INDEX category (category),
            INDEX mode (mode),
            INDEX is_active (is_active),
            INDEX usage_count (usage_count)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        return $this->table_exists('message_templates');
    }
    
    /**
     * Create XML feeds table for custom feed management
     */
    private function create_xml_feeds_table() {
        global $wpdb;
        
        $table_name = $this->tables['xml_feeds'];
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            feed_name varchar(255) NOT NULL,
            feed_url varchar(500) NOT NULL,
            feed_category varchar(100) DEFAULT 'general',
            is_active tinyint(1) DEFAULT 1,
            priority int(11) DEFAULT 10,
            last_fetched datetime,
            last_error text,
            error_count int(11) DEFAULT 0,
            success_count int(11) DEFAULT 0,
            added_by bigint(20) UNSIGNED,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY feed_url (feed_url),
            INDEX feed_category (feed_category),
            INDEX is_active (is_active),
            INDEX priority (priority),
            INDEX last_fetched (last_fetched)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Insert default feeds if table is empty
        $this->insert_default_feeds();
        
        return $this->table_exists('xml_feeds');
    }
    
    /**
     * Insert default feeds if none exist
     */
    private function insert_default_feeds() {
        global $wpdb;
        
        $table_name = $this->tables['xml_feeds'];
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        if ($count == 0) {
            $default_feeds = array(
                array('MarketWatch', 'https://feeds.marketwatch.com/marketwatch/realtimeheadlines/', 'markets', 1, 10),
                array('Reuters Business', 'https://feeds.reuters.com/reuters/businessNews', 'business', 1, 10),
                array('FT Markets', 'https://www.ft.com/rss/markets', 'markets', 1, 10),
                array('Seeking Alpha', 'https://seekingalpha.com/market_currents.xml', 'markets', 1, 10),
                array('WSJ Markets', 'https://feeds.a.dj.com/rss/RSSMarketsMain.xml', 'markets', 1, 10),
                array('Private Equity Wire', 'https://www.privateequitywire.co.uk/rss.xml', 'private-equity', 1, 15),
                array('PitchBook News', 'https://pitchbook.com/feeds/news', 'venture-capital', 1, 15),
                array('PE Hub', 'https://www.pehub.com/feed/', 'private-equity', 1, 15),
                array('Alt Assets', 'https://www.altassets.net/feed', 'alternatives', 1, 15)
            );
            
            foreach ($default_feeds as $feed) {
                $wpdb->insert(
                    $table_name,
                    array(
                        'feed_name' => $feed[0],
                        'feed_url' => $feed[1],
                        'feed_category' => $feed[2],
                        'is_active' => $feed[3],
                        'priority' => $feed[4],
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    ),
                    array('%s', '%s', '%s', '%d', '%d', '%s', '%s')
                );
            }
        }
    }
    
    /**
     * Create CV Master table
     */
    private function create_cv_master_table() {
        global $wpdb;
        
        $table_name = $this->tables['cv_master'];
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            cv_name VARCHAR(255) NOT NULL,
            original_file_path VARCHAR(500),
            parsed_data LONGTEXT,
            extracted_text LONGTEXT,
            skills LONGTEXT,
            experience LONGTEXT,
            education LONGTEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            is_primary BOOLEAN DEFAULT FALSE,
            INDEX idx_user (user_id),
            INDEX idx_created (created_at)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        return $this->table_exists('cv_master');
    }
    
    /**
     * Create CV Versions table
     */
    private function create_cv_versions_table() {
        global $wpdb;
        
        $table_name = $this->tables['cv_versions'];
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            master_cv_id BIGINT UNSIGNED NOT NULL,
            job_id VARCHAR(100),
            job_title VARCHAR(255),
            company_name VARCHAR(255),
            job_description LONGTEXT,
            tailored_content LONGTEXT,
            modifications LONGTEXT,
            match_score INT,
            match_breakdown LONGTEXT,
            keywords_added LONGTEXT,
            ats_score INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            exported_count INT DEFAULT 0,
            last_exported TIMESTAMP NULL,
            INDEX idx_master (master_cv_id),
            INDEX idx_job (job_id),
            INDEX idx_score (match_score)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        return $this->table_exists('cv_versions');
    }
    
    /**
     * Create Job Analysis table
     */
    private function create_job_analysis_table() {
        global $wpdb;
        
        $table_name = $this->tables['job_analysis'];
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            job_id VARCHAR(100) UNIQUE,
            job_title VARCHAR(255),
            company_name VARCHAR(255),
            job_description LONGTEXT,
            analysis_data LONGTEXT,
            required_skills LONGTEXT,
            preferred_skills LONGTEXT,
            keywords LONGTEXT,
            responsibilities LONGTEXT,
            requirements LONGTEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_job_id (job_id),
            INDEX idx_created (created_at)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        return $this->table_exists('job_analysis');
    }
    
    /**
     * Create CV Analytics table
     */
    private function create_cv_analytics_table() {
        global $wpdb;
        
        $table_name = $this->tables['cv_analytics'];
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            action_type ENUM('upload', 'analyze', 'tailor', 'export_pdf', 'export_word', 'export_ats'),
            cv_version_id BIGINT UNSIGNED,
            job_id VARCHAR(100),
            match_score INT,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            metadata LONGTEXT,
            INDEX idx_user (user_id),
            INDEX idx_action (action_type),
            INDEX idx_timestamp (timestamp)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        return $this->table_exists('cv_analytics');
    }
    
    /**
     * Create CV Uploads table
     */
    private function create_cv_uploads_table() {
        global $wpdb;
        
        $table_name = $this->tables['cv_uploads'];
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(100) NOT NULL,
            file_name VARCHAR(255),
            file_path VARCHAR(500),
            file_type VARCHAR(50),
            file_size INT,
            upload_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            parse_status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
            error_message TEXT,
            INDEX idx_user_id (user_id),
            INDEX idx_status (parse_status)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        return $this->table_exists('cv_uploads');
    }
    
    /**
     * Create Parsed Profiles table
     */
    private function create_parsed_profiles_table() {
        global $wpdb;
        
        $table_name = $this->tables['parsed_profiles'];
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(100) NOT NULL UNIQUE,
            cv_upload_id INT,
            parsed_data LONGTEXT,
            platform_formats LONGTEXT,
            extraction_confidence DECIMAL(3,2),
            last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            version INT DEFAULT 1,
            INDEX idx_user_id (user_id)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        return $this->table_exists('parsed_profiles');
    }
    
    /**
     * Create Autofill Tokens table
     */
    private function create_autofill_tokens_table() {
        global $wpdb;
        
        $table_name = $this->tables['autofill_tokens'];
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(100) NOT NULL,
            token VARCHAR(64) UNIQUE,
            device_id VARCHAR(255),
            device_name VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME,
            last_used DATETIME,
            is_active BOOLEAN DEFAULT TRUE,
            INDEX idx_user_token (user_id, token),
            INDEX idx_expires (expires_at)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        return $this->table_exists('autofill_tokens');
    }
    
    /**
     * Create Platform Patterns table
     */
    private function create_platform_patterns_table() {
        global $wpdb;
        
        $table_name = $this->tables['platform_patterns'];
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id INT AUTO_INCREMENT PRIMARY KEY,
            platform_name VARCHAR(100),
            url_pattern VARCHAR(500),
            dom_selectors LONGTEXT,
            field_mappings LONGTEXT,
            priority INT DEFAULT 0,
            is_active BOOLEAN DEFAULT TRUE,
            last_verified DATETIME,
            success_rate DECIMAL(5,2),
            INDEX idx_platform (platform_name),
            INDEX idx_active (is_active)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Insert default platform patterns if table is empty
        $this->insert_default_platform_patterns();
        
        return $this->table_exists('platform_patterns');
    }
    
    /**
     * Create Autofill Applications table
     */
    private function create_autofill_applications_table() {
        global $wpdb;
        
        $table_name = $this->tables['autofill_applications'];
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(100) NOT NULL,
            platform VARCHAR(100),
            company_name VARCHAR(255),
            position_title VARCHAR(255),
            application_url TEXT,
            autofill_success BOOLEAN,
            fields_filled INT,
            fields_failed INT,
            error_log LONGTEXT,
            time_spent INT,
            applied_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_apps (user_id),
            INDEX idx_platform (platform),
            INDEX idx_date (applied_at)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        return $this->table_exists('autofill_applications');
    }
    
    /**
     * Insert default platform patterns
     */
    private function insert_default_platform_patterns() {
        global $wpdb;
        
        $table_name = $this->tables['platform_patterns'];
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        if ($count == 0) {
            $platforms = array(
                array(
                    'platform_name' => 'workday',
                    'url_pattern' => '%.myworkdayjobs.co%',
                    'dom_selectors' => json_encode(array(
                        'name' => array('#legalName', '[name="legalName"]', '.legal-name-field'),
                        'email' => array('#email', '[type="email"]', '.email-field'),
                        'phone' => array('#phone', '[type="tel"]', '.phone-field')
                    )),
                    'field_mappings' => json_encode(array(
                        'full_name' => 'legalName',
                        'email' => 'email',
                        'phone' => 'phone'
                    )),
                    'priority' => 10,
                    'is_active' => 1
                ),
                array(
                    'platform_name' => 'greenhouse',
                    'url_pattern' => '%greenhouse.io%',
                    'dom_selectors' => json_encode(array(
                        'name' => array('#first_name', '#last_name', '[name*="name"]'),
                        'email' => array('#email', '[name="email"]'),
                        'phone' => array('#phone', '[name="phone"]')
                    )),
                    'field_mappings' => json_encode(array(
                        'first_name' => 'first_name',
                        'last_name' => 'last_name',
                        'email' => 'email'
                    )),
                    'priority' => 9,
                    'is_active' => 1
                ),
                array(
                    'platform_name' => 'lever',
                    'url_pattern' => '%lever.co%',
                    'dom_selectors' => json_encode(array(
                        'name' => array('[name="name"]', '.application-name'),
                        'email' => array('[name="email"]', '.application-email'),
                        'phone' => array('[name="phone"]', '.application-phone')
                    )),
                    'field_mappings' => json_encode(array(
                        'full_name' => 'name',
                        'email' => 'email',
                        'phone' => 'phone'
                    )),
                    'priority' => 8,
                    'is_active' => 1
                )
            );
            
            foreach ($platforms as $platform) {
                $wpdb->insert($table_name, $platform);
            }
        }
    }
    
    /**
     * Check if table exists
     */
    public function table_exists($table_key) {
        global $wpdb;
        
        if (!isset($this->tables[$table_key])) {
            return false;
        }
        
        $table_name = $this->tables[$table_key];
        $query = $wpdb->prepare("SHOW TABLES LIKE %s", $table_name);
        
        return $wpdb->get_var($query) === $table_name;
    }
    
    /**
     * Get table status
     */
    public function get_tables_status() {
        $status = array();
        
        foreach ($this->tables as $key => $table_name) {
            $status[$key] = array(
                'name' => $table_name,
                'exists' => $this->table_exists($key),
                'row_count' => $this->get_table_row_count($key)
            );
        }
        
        return $status;
    }
    
    /**
     * Get table row count
     */
    public function get_table_row_count($table_key) {
        global $wpdb;
        
        if (!$this->table_exists($table_key)) {
            return 0;
        }
        
        $table_name = $this->tables[$table_key];
        // Use prepared statement to prevent SQL injection
        $sql = $wpdb->prepare("SELECT COUNT(*) FROM `%s`", $table_name);
        // Since we can't use prepare for table names, validate it's from our whitelist
        if (!in_array($table_name, $this->tables)) {
            return 0;
        }
        // Safe to use since it's from our validated list
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM `" . esc_sql($table_name) . "`");
    }
    
    /**
     * Drop all tables
     */
    public function drop_tables() {
        global $wpdb;
        
        $results = array();
        
        foreach ($this->tables as $key => $table_name) {
            // Validate table name is from our whitelist
            if (!in_array($table_name, $this->tables)) {
                $results[$key] = false;
                continue;
            }
            // Safe to use since it's from our validated list
            $sql = "DROP TABLE IF EXISTS `" . esc_sql($table_name) . "`";
            $results[$key] = $wpdb->query($sql) !== false;
        }
        
        return $results;
    }
    
    /**
     * Insert conversation
     */
    public function insert_conversation($user_id, $mode = 'career', $metadata = array()) {
        global $wpdb;
        
        // Verify table exists before attempting insert
        if (!$this->table_exists('conversations')) {
            error_log('SFFC Error: Conversations table does not exist. Attempting to create...');
            $this->create_conversations_table();
            
            // Check again
            if (!$this->table_exists('conversations')) {
                error_log('SFFC Critical: Could not create conversations table');
                return false;
            }
        }
        
        $data = array(
            'user_id' => $user_id,
            'mode' => $mode,
            'status' => 'active',
            'metadata' => wp_json_encode($metadata)
        );
        
        $result = $wpdb->insert($this->tables['conversations'], $data);
        
        if ($result === false) {
            error_log('SFFC Database Error: ' . $wpdb->last_error);
            return false;
        }
        
        if ($result) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Insert message
     */
    public function insert_message($conversation_id, $sender_type, $message, $sender_id = null, $metadata = array()) {
        global $wpdb;
        
        // Verify table exists before attempting insert
        if (!$this->table_exists('messages')) {
            error_log('SFFC Error: Messages table does not exist. Attempting to create...');
            $this->create_messages_table();
            
            // Check again
            if (!$this->table_exists('messages')) {
                error_log('SFFC Critical: Could not create messages table');
                return false;
            }
        }
        
        $data = array(
            'conversation_id' => $conversation_id,
            'sender_type' => $sender_type,
            'sender_id' => $sender_id,
            'message' => $message,
            'metadata' => wp_json_encode($metadata)
        );
        
        $result = $wpdb->insert($this->tables['messages'], $data);
        
        if ($result === false) {
            error_log('SFFC Database Error inserting message: ' . $wpdb->last_error);
            return false;
        }
        
        if ($result) {
            // Update conversation updated_at
            $wpdb->update(
                $this->tables['conversations'],
                array('updated_at' => current_time('mysql')),
                array('id' => $conversation_id)
            );
            
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Get conversation messages
     */
    public function get_conversation_messages($conversation_id, $limit = 50) {
        global $wpdb;
        
        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->tables['messages']} 
             WHERE conversation_id = %d 
             ORDER BY created_at ASC 
             LIMIT %d",
            $conversation_id,
            $limit
        );
        
        $results = $wpdb->get_results($sql);
        
        // Decode metadata
        foreach ($results as &$result) {
            if (!empty($result->metadata)) {
                $result->metadata = json_decode($result->metadata, true);
            }
        }
        
        return $results;
    }
    
    /**
     * Get user conversations
     */
    public function get_user_conversations($user_id, $limit = 10) {
        global $wpdb;
        
        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->tables['conversations']} 
             WHERE user_id = %d 
             ORDER BY updated_at DESC 
             LIMIT %d",
            $user_id,
            $limit
        );
        
        $results = $wpdb->get_results($sql);
        
        // Decode metadata
        foreach ($results as &$result) {
            if (!empty($result->metadata)) {
                $result->metadata = json_decode($result->metadata, true);
            }
        }
        
        return $results;
    }
    
    /**
     * Update user profile
     */
    public function update_user_profile($user_id, $data) {
        global $wpdb;
        
        // Check if profile exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->tables['user_profiles']} WHERE user_id = %d",
            $user_id
        ));
        
        if ($exists) {
            // Update existing profile
            return $wpdb->update(
                $this->tables['user_profiles'],
                $data,
                array('user_id' => $user_id)
            );
        } else {
            // Insert new profile
            $data['user_id'] = $user_id;
            return $wpdb->insert($this->tables['user_profiles'], $data);
        }
    }
    
    /**
     * Get user profile
     */
    public function get_user_profile($user_id) {
        global $wpdb;
        
        $profile = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tables['user_profiles']} WHERE user_id = %d",
            $user_id
        ));
        
        if ($profile) {
            // Decode JSON fields
            if (!empty($profile->professional_data)) {
                $profile->professional_data = json_decode($profile->professional_data, true);
            }
            if (!empty($profile->preferences)) {
                $profile->preferences = json_decode($profile->preferences, true);
            }
            if (!empty($profile->cv_data)) {
                $profile->cv_data = json_decode($profile->cv_data, true);
            }
        }
        
        return $profile;
    }
}