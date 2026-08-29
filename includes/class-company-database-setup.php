<?php
/**
 * Company Database Setup
 * Creates and manages database tables for company intelligence system
 * 
 * @package SennaCareers
 * @since 10.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Company_Database_Setup {
    
    /**
     * Database version
     */
    private static $db_version = '1.1.0';
    
    /**
     * Create all required tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Company news relationships table
        $table_news = $wpdb->prefix . 'sffc_company_news_links';
        $sql_news = "CREATE TABLE $table_news (
            id int(11) NOT NULL AUTO_INCREMENT,
            company_id int(11) NOT NULL,
            news_item_id int(11) NOT NULL,
            relevance_score float DEFAULT 0,
            matched_terms text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY company_id (company_id),
            KEY news_item_id (news_item_id),
            KEY relevance_score (relevance_score),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Company metrics table
        $table_metrics = $wpdb->prefix . 'sffc_company_metrics';
        $sql_metrics = "CREATE TABLE $table_metrics (
            id int(11) NOT NULL AUTO_INCREMENT,
            company_id int(11) NOT NULL,
            metric_type varchar(50) NOT NULL,
            metric_value text,
            metric_date date,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY company_id (company_id),
            KEY metric_type (metric_type),
            KEY metric_date (metric_date)
        ) $charset_collate;";
        
        // Deal tracking table
        $table_deals = $wpdb->prefix . 'sffc_deal_tracking';
        $sql_deals = "CREATE TABLE $table_deals (
            id int(11) NOT NULL AUTO_INCREMENT,
            company_id int(11) NOT NULL,
            deal_type varchar(50),
            deal_size varchar(50),
            deal_date date,
            target_company varchar(255),
            sector varchar(100),
            region varchar(50),
            status varchar(50),
            details text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY company_id (company_id),
            KEY deal_date (deal_date),
            KEY sector (sector),
            KEY region (region),
            KEY status (status)
        ) $charset_collate;";
        
        // Company relationships table
        $table_relationships = $wpdb->prefix . 'sffc_company_relationships';
        $sql_relationships = "CREATE TABLE $table_relationships (
            id int(11) NOT NULL AUTO_INCREMENT,
            company_id int(11) NOT NULL,
            related_company_id int(11) NOT NULL,
            relationship_type varchar(50),
            details text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY company_id (company_id),
            KEY related_company_id (related_company_id),
            KEY relationship_type (relationship_type)
        ) $charset_collate;";
        
        // Team members table
        $table_team = $wpdb->prefix . 'sffc_company_team';
        $sql_team = "CREATE TABLE $table_team (
            id int(11) NOT NULL AUTO_INCREMENT,
            company_id int(11) NOT NULL,
            person_name varchar(255),
            position varchar(255),
            bio text,
            linkedin_url varchar(255),
            start_date date,
            end_date date,
            is_current tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY company_id (company_id),
            KEY person_name (person_name),
            KEY is_current (is_current)
        ) $charset_collate;";
        
        // Portfolio companies table
        $table_portfolio = $wpdb->prefix . 'sffc_portfolio_companies';
        $sql_portfolio = "CREATE TABLE $table_portfolio (
            id int(11) NOT NULL AUTO_INCREMENT,
            parent_company_id int(11) NOT NULL,
            portfolio_company_name varchar(255),
            acquisition_date date,
            exit_date date,
            sector varchar(100),
            investment_size varchar(50),
            status varchar(50),
            details text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY parent_company_id (parent_company_id),
            KEY portfolio_company_name (portfolio_company_name),
            KEY status (status),
            KEY sector (sector)
        ) $charset_collate;";
        
        // Canonical company registry table
        $table_registry = $wpdb->prefix . 'sffc_companies_registry';
        $sql_registry = "CREATE TABLE $table_registry (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            company_post_id bigint(20) unsigned DEFAULT NULL,
            canonical_name varchar(255) NOT NULL,
            normalized_name varchar(255) NOT NULL,
            slug varchar(200) NOT NULL,
            preferred_alias varchar(255) DEFAULT NULL,
            website_domain varchar(255) DEFAULT NULL,
            linkedin_url varchar(255) DEFAULT NULL,
            hq_city varchar(120) DEFAULT NULL,
            hq_country varchar(120) DEFAULT NULL,
            primary_sector varchar(120) DEFAULT NULL,
            confidence_score decimal(5,2) DEFAULT 0.00,
            status enum('active','pending','merged','suppressed') DEFAULT 'active',
            merged_into bigint(20) unsigned DEFAULT NULL,
            metadata longtext,
            first_seen datetime DEFAULT CURRENT_TIMESTAMP,
            last_enriched datetime DEFAULT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_slug (slug),
            UNIQUE KEY uniq_normalized_name (normalized_name),
            KEY idx_company_post (company_post_id),
            KEY idx_status (status),
            KEY idx_domain (website_domain(191))
        ) $charset_collate;";

        // Alias table stores all variations encountered across feeds
        $table_aliases = $wpdb->prefix . 'sffc_company_aliases';
        $sql_aliases = "CREATE TABLE $table_aliases (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            company_id bigint(20) unsigned NOT NULL,
            alias varchar(255) NOT NULL,
            normalized_alias varchar(255) NOT NULL,
            alias_type enum('default','legal','short','news','job','manual','portfolio','executive','canonical') DEFAULT 'default',
            source varchar(120) DEFAULT NULL,
            confidence_score decimal(5,2) DEFAULT 0.00,
            is_primary tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_company_alias (company_id, normalized_alias),
            KEY idx_alias (normalized_alias),
            KEY idx_alias_type (alias_type)
        ) $charset_collate;";

        // Resolution audit trail gives observability into automated matching decisions
        $table_audit = $wpdb->prefix . 'sffc_company_resolution_audit';
        $sql_audit = "CREATE TABLE $table_audit (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            source_type enum('job','news','manual','system') NOT NULL,
            source_reference varchar(255) DEFAULT NULL,
            raw_company_name varchar(255) NOT NULL,
            normalized_company_name varchar(255) NOT NULL,
            matched_company_id bigint(20) unsigned DEFAULT NULL,
            matched_alias_id bigint(20) unsigned DEFAULT NULL,
            confidence_score decimal(5,2) DEFAULT 0.00,
            match_strategy varchar(120) DEFAULT NULL,
            notes text,
            resolved_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_source_type (source_type),
            KEY idx_resolved_at (resolved_at),
            KEY idx_matched_company (matched_company_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_news);
        dbDelta($sql_metrics);
        dbDelta($sql_deals);
        dbDelta($sql_relationships);
        dbDelta($sql_team);
        dbDelta($sql_portfolio);
        dbDelta($sql_registry);
        dbDelta($sql_aliases);
        dbDelta($sql_audit);
        
        // Store database version
        update_option('sffc_company_db_version', self::$db_version);
    }
    
    /**
     * Check if tables need updating
     */
    public static function maybe_update_tables() {
        $installed_version = get_option('sffc_company_db_version');
        
        if ($installed_version != self::$db_version) {
            self::create_tables();
        }
    }
    
    /**
     * Drop all tables (for uninstall)
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'sffc_company_news_links',
            $wpdb->prefix . 'sffc_company_metrics',
            $wpdb->prefix . 'sffc_deal_tracking',
            $wpdb->prefix . 'sffc_company_relationships',
            $wpdb->prefix . 'sffc_company_team',
            $wpdb->prefix . 'sffc_portfolio_companies',
            $wpdb->prefix . 'sffc_companies_registry',
            $wpdb->prefix . 'sffc_company_aliases',
            $wpdb->prefix . 'sffc_company_resolution_audit'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        delete_option('sffc_company_db_version');
    }

    /**
     * Get current table status
     */
    public static function get_table_status() {
        global $wpdb;

        $status = array();
        $tables = self::get_tables_list();

        foreach ($tables as $table) {
            $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
            $status[$table] = (bool) $exists;
        }

        return $status;
    }

    /**
     * Get list of expected tables
     */
    public static function get_tables_list() {
        global $wpdb;

        return array(
            $wpdb->prefix . 'sffc_company_news_links',
            $wpdb->prefix . 'sffc_company_metrics',
            $wpdb->prefix . 'sffc_deal_tracking',
            $wpdb->prefix . 'sffc_company_relationships',
            $wpdb->prefix . 'sffc_company_team',
            $wpdb->prefix . 'sffc_portfolio_companies',
            $wpdb->prefix . 'sffc_companies_registry',
            $wpdb->prefix . 'sffc_company_aliases',
            $wpdb->prefix . 'sffc_company_resolution_audit'
        );
    }
    
    /**
     * Insert sample data for testing
     */
    public static function insert_sample_data() {
        global $wpdb;
        
        // Create sample companies if they don't exist
        $companies = array(
            'KKR' => array('aum' => '524000000000', 'founded' => '1976'),
            'Blackstone' => array('aum' => '1000000000000', 'founded' => '1985'),
            'Apollo' => array('aum' => '631000000000', 'founded' => '1990'),
            'Carlyle' => array('aum' => '426000000000', 'founded' => '1987'),
            'TPG' => array('aum' => '137000000000', 'founded' => '1992')
        );
        
        foreach ($companies as $name => $data) {
            if (class_exists('SFFC_Company_Title_Helper')) {
                $existing_posts = get_posts(array(
                    'post_type' => 'sffc_company',
                    'meta_key' => SFFC_Company_Title_Helper::META_CANONICAL_NAME,
                    'meta_value' => $name,
                    'posts_per_page' => 1
                ));
                $existing = !empty($existing_posts) ? $existing_posts[0] : null;
            } else {
                $existing = get_page_by_title($name, OBJECT, 'sffc_company');
            }

            if (!$existing) {
                $company_args = array(
                    'post_title' => class_exists('SFFC_Company_Title_Helper')
                        ? SFFC_Company_Title_Helper::build_seo_title($name)
                        : $name,
                    'post_type' => 'sffc_company',
                    'post_status' => 'publish'
                );

                if (class_exists('SFFC_Company_Title_Helper')) {
                    $company_args['post_name'] = sanitize_title($name);
                }

                $company_id = wp_insert_post($company_args);

                if ($company_id) {
                    if (class_exists('SFFC_Company_Title_Helper')) {
                        SFFC_Company_Title_Helper::ensure_canonical_meta($company_id, $name);
                    }
                    update_post_meta($company_id, '_sffc_aum', $data['aum']);
                    update_post_meta($company_id, '_sffc_founded', $data['founded']);
                    
                    // Add sample deals
                    $wpdb->insert(
                        $wpdb->prefix . 'sffc_deal_tracking',
                        array(
                            'company_id' => $company_id,
                            'deal_type' => 'acquisition',
                            'deal_size' => rand(100, 500) . '000000',
                            'deal_date' => date('Y-m-d', strtotime('-' . rand(1, 30) . ' days')),
                            'target_company' => 'Sample Portfolio Co',
                            'sector' => 'Technology',
                            'region' => 'US',
                            'status' => 'active'
                        )
                    );
                }
            }
        }
    }
}

// Hook into activation
register_activation_hook(SFFC_PLUGIN_FILE, array('SFFC_Company_Database_Setup', 'create_tables'));

// Check for updates on admin init
add_action('admin_init', array('SFFC_Company_Database_Setup', 'maybe_update_tables'));
