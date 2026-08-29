<?php
/**
 * Matcher Database Setup
 * Creates and manages database tables for the matcher-messaging integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Matcher_Database_Setup {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
    }
    
    /**
     * Create all required tables
     */
    public function create_all_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Table 1: Track sent matches to prevent duplicates
        $this->create_sent_matches_table($charset_collate);
        
        // Table 2: Job title taxonomy for flexible matching
        $this->create_job_title_taxonomy_table($charset_collate);
        
        // Table 3: CV extraction cache for performance
        $this->create_cv_extraction_cache_table($charset_collate);
        
        // Table 4: User match preferences and feedback
        $this->create_user_match_preferences_table($charset_collate);
        
        // Table 5: Match analytics and tracking
        $this->create_match_analytics_table($charset_collate);
        
        // Table 6: Title match feedback for learning
        $this->create_title_match_feedback_table($charset_collate);
        
        // Populate initial data
        $this->populate_initial_data();
        
        // Update version
        update_option('sffc_matcher_db_version', '1.0.0');
    }
    
    /**
     * Create sent matches tracking table
     */
    private function create_sent_matches_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sffc_sent_matches';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            job_id bigint(20) NOT NULL,
            match_score decimal(5,2) NOT NULL,
            message_id bigint(20) DEFAULT NULL,
            match_details longtext,
            sent_at datetime DEFAULT CURRENT_TIMESTAMP,
            user_response varchar(50) DEFAULT 'pending',
            response_date datetime DEFAULT NULL,
            clicked tinyint(1) DEFAULT 0,
            applied tinyint(1) DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY user_job (user_id, job_id),
            KEY user_id (user_id),
            KEY job_id (job_id),
            KEY sent_at (sent_at),
            KEY match_score (match_score),
            KEY user_response (user_response)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create job title taxonomy table
     */
    private function create_job_title_taxonomy_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sffc_job_title_taxonomy';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            base_title varchar(255) NOT NULL,
            variant_title varchar(255) NOT NULL,
            similarity_score decimal(3,2) DEFAULT 0.90,
            category varchar(100) DEFAULT 'general',
            industry varchar(100) DEFAULT 'finance',
            confidence_level varchar(20) DEFAULT 'high',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            is_active tinyint(1) DEFAULT 1,
            PRIMARY KEY (id),
            KEY base_title (base_title),
            KEY variant_title (variant_title),
            KEY category (category),
            KEY industry (industry),
            KEY similarity_score (similarity_score),
            UNIQUE KEY title_pair (base_title, variant_title)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create CV extraction cache table
     */
    private function create_cv_extraction_cache_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sffc_cv_extraction_cache';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            cv_hash varchar(64) NOT NULL,
            extracted_data longtext,
            extraction_confidence decimal(3,2) DEFAULT 0.80,
            extraction_method varchar(50) DEFAULT 'ai_parser',
            extracted_at datetime DEFAULT CURRENT_TIMESTAMP,
            last_used datetime DEFAULT CURRENT_TIMESTAMP,
            use_count int(10) DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY user_cv (user_id, cv_hash),
            KEY user_id (user_id),
            KEY extraction_confidence (extraction_confidence),
            KEY extracted_at (extracted_at)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create user match preferences table
     */
    private function create_user_match_preferences_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sffc_user_match_preferences';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            min_match_score decimal(3,2) DEFAULT 0.70,
            max_daily_matches int(2) DEFAULT 5,
            preferred_match_categories longtext,
            excluded_companies longtext,
            excluded_locations longtext,
            notification_frequency varchar(20) DEFAULT 'immediate',
            include_stretch_opportunities tinyint(1) DEFAULT 1,
            auto_apply_threshold decimal(3,2) DEFAULT 0.95,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id),
            KEY min_match_score (min_match_score),
            KEY notification_frequency (notification_frequency)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create match analytics table
     */
    private function create_match_analytics_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sffc_match_analytics';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            job_id bigint(20) NOT NULL,
            calculation_method varchar(50) NOT NULL,
            base_score decimal(5,2) NOT NULL,
            title_score decimal(5,2) NOT NULL,
            skills_score decimal(5,2) NOT NULL,
            location_score decimal(5,2) NOT NULL,
            experience_score decimal(5,2) NOT NULL,
            final_score decimal(5,2) NOT NULL,
            processing_time_ms int(10) DEFAULT 0,
            match_factors longtext,
            calculated_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY job_id (job_id),
            KEY final_score (final_score),
            KEY calculated_at (calculated_at),
            KEY calculation_method (calculation_method)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create title match feedback table
     */
    private function create_title_match_feedback_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sffc_title_match_feedback';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) DEFAULT NULL,
            user_title varchar(255) NOT NULL,
            job_title varchar(255) NOT NULL,
            calculated_score decimal(5,2) NOT NULL,
            user_rating int(2) NOT NULL,
            feedback_type varchar(20) DEFAULT 'manual',
            comments text DEFAULT NULL,
            feedback_date datetime DEFAULT CURRENT_TIMESTAMP,
            ip_address varchar(45) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY user_title (user_title),
            KEY job_title (job_title),
            KEY calculated_score (calculated_score),
            KEY user_rating (user_rating),
            KEY feedback_date (feedback_date)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Populate initial data
     */
    private function populate_initial_data() {
        $this->populate_job_title_taxonomy();
        $this->create_default_user_preferences();
    }
    
    /**
     * Populate job title taxonomy with comprehensive finance industry mappings
     */
    private function populate_job_title_taxonomy() {
        global $wpdb;
        
        $taxonomy_table = $wpdb->prefix . 'sffc_job_title_taxonomy';
        
        // Check if already populated
        $existing = $wpdb->get_var("SELECT COUNT(*) FROM $taxonomy_table");
        if ($existing > 50) { // Only populate if less than 50 entries
            return;
        }
        
        $job_title_mappings = [
            // Investment Analyst variations
            ['Investment Analyst', 'Investment Analyst - Mega Fund', 0.95, 'investment', 'private_equity'],
            ['Investment Analyst', 'Investment Analyst (Hedge Fund)', 0.90, 'investment', 'hedge_funds'],
            ['Investment Analyst', 'Junior Investment Analyst', 0.85, 'investment', 'asset_management'],
            ['Investment Analyst', 'Senior Investment Analyst', 0.95, 'investment', 'asset_management'],
            ['Investment Analyst', 'Investment Research Analyst', 0.90, 'investment', 'research'],
            ['Investment Analyst', 'Equity Research Analyst', 0.85, 'investment', 'research'],
            ['Investment Analyst', 'Buy-Side Analyst', 0.90, 'investment', 'buy_side'],
            ['Investment Analyst', 'Sell-Side Analyst', 0.85, 'investment', 'sell_side'],
            
            // Portfolio Manager variations
            ['Portfolio Manager', 'Senior Portfolio Manager', 0.95, 'portfolio', 'asset_management'],
            ['Portfolio Manager', 'Fund Manager', 0.90, 'portfolio', 'fund_management'],
            ['Portfolio Manager', 'Asset Manager', 0.85, 'portfolio', 'asset_management'],
            ['Portfolio Manager', 'Investment Manager', 0.90, 'portfolio', 'investment'],
            ['Portfolio Manager', 'Equity Portfolio Manager', 0.92, 'portfolio', 'equity'],
            ['Portfolio Manager', 'Fixed Income Portfolio Manager', 0.92, 'portfolio', 'fixed_income'],
            ['Portfolio Manager', 'Multi-Asset Portfolio Manager', 0.93, 'portfolio', 'multi_asset'],
            
            // Private Equity variations
            ['Private Equity Associate', 'PE Associate', 0.95, 'private_equity', 'private_equity'],
            ['Private Equity Associate', 'Private Equity Analyst', 0.85, 'private_equity', 'private_equity'],
            ['Private Equity Associate', 'Growth Equity Associate', 0.90, 'private_equity', 'growth_equity'],
            ['Private Equity Associate', 'Buyout Associate', 0.90, 'private_equity', 'buyout'],
            ['Private Equity Associate', 'PE Investment Associate', 0.93, 'private_equity', 'private_equity'],
            ['Private Equity Principal', 'PE Principal', 0.95, 'private_equity', 'private_equity'],
            ['Private Equity Principal', 'Investment Principal', 0.88, 'private_equity', 'investment'],
            
            // Investment Banking variations
            ['Investment Banking Analyst', 'IB Analyst', 0.95, 'investment_banking', 'investment_banking'],
            ['Investment Banking Analyst', 'Corporate Finance Analyst', 0.85, 'investment_banking', 'corporate_finance'],
            ['Investment Banking Analyst', 'M&A Analyst', 0.90, 'investment_banking', 'ma'],
            ['Investment Banking Associate', 'IB Associate', 0.95, 'investment_banking', 'investment_banking'],
            ['Investment Banking Associate', 'Corporate Finance Associate', 0.85, 'investment_banking', 'corporate_finance'],
            ['Investment Banking Associate', 'M&A Associate', 0.90, 'investment_banking', 'ma'],
            ['Investment Banking VP', 'IB Vice President', 0.95, 'investment_banking', 'investment_banking'],
            ['Investment Banking VP', 'Corporate Finance VP', 0.88, 'investment_banking', 'corporate_finance'],
            
            // Hedge Fund variations
            ['Hedge Fund Analyst', 'Fund Analyst', 0.90, 'hedge_fund', 'hedge_funds'],
            ['Hedge Fund Analyst', 'Quantitative Analyst', 0.80, 'hedge_fund', 'quantitative'],
            ['Hedge Fund Analyst', 'Research Analyst', 0.80, 'hedge_fund', 'research'],
            ['Hedge Fund Analyst', 'Long/Short Equity Analyst', 0.88, 'hedge_fund', 'long_short'],
            ['Hedge Fund Manager', 'Fund Manager', 0.90, 'hedge_fund', 'fund_management'],
            ['Hedge Fund Manager', 'Portfolio Manager', 0.85, 'hedge_fund', 'portfolio'],
            
            // Risk Management variations
            ['Risk Manager', 'Senior Risk Manager', 0.95, 'risk', 'risk_management'],
            ['Risk Manager', 'Risk Analyst', 0.80, 'risk', 'risk_management'],
            ['Risk Manager', 'Credit Risk Manager', 0.90, 'risk', 'credit_risk'],
            ['Risk Manager', 'Market Risk Manager', 0.90, 'risk', 'market_risk'],
            ['Risk Manager', 'Operational Risk Manager', 0.90, 'risk', 'operational_risk'],
            ['Risk Manager', 'Risk Officer', 0.88, 'risk', 'risk_management'],
            
            // Compliance variations
            ['Compliance Officer', 'Senior Compliance Officer', 0.95, 'compliance', 'compliance'],
            ['Compliance Officer', 'Compliance Manager', 0.90, 'compliance', 'compliance'],
            ['Compliance Officer', 'Regulatory Affairs Officer', 0.85, 'compliance', 'regulatory'],
            ['Compliance Officer', 'AML Officer', 0.82, 'compliance', 'aml'],
            ['Compliance Officer', 'KYC Officer', 0.80, 'compliance', 'kyc'],
            
            // Operations variations
            ['Operations Manager', 'Fund Operations Manager', 0.90, 'operations', 'fund_operations'],
            ['Operations Manager', 'Investment Operations Manager', 0.90, 'operations', 'investment_operations'],
            ['Operations Manager', 'Senior Operations Manager', 0.95, 'operations', 'operations'],
            ['Operations Manager', 'Trade Operations Manager', 0.88, 'operations', 'trade_operations'],
            
            // Research variations
            ['Research Analyst', 'Equity Research Analyst', 0.90, 'research', 'equity_research'],
            ['Research Analyst', 'Credit Research Analyst', 0.88, 'research', 'credit_research'],
            ['Research Analyst', 'Macro Research Analyst', 0.85, 'research', 'macro_research'],
            ['Research Analyst', 'Quantitative Research Analyst', 0.87, 'research', 'quantitative_research'],
            
            // Sales & Trading variations
            ['Sales Trader', 'Equity Sales Trader', 0.92, 'sales_trading', 'equity_sales'],
            ['Sales Trader', 'Fixed Income Sales Trader', 0.92, 'sales_trading', 'fixed_income_sales'],
            ['Sales Trader', 'Institutional Sales', 0.85, 'sales_trading', 'institutional_sales'],
            ['Trader', 'Equity Trader', 0.90, 'trading', 'equity_trading'],
            ['Trader', 'Fixed Income Trader', 0.90, 'trading', 'fixed_income_trading'],
            ['Trader', 'Derivatives Trader', 0.88, 'trading', 'derivatives_trading'],
            
            // Venture Capital variations
            ['Venture Capital Associate', 'VC Associate', 0.95, 'venture_capital', 'venture_capital'],
            ['Venture Capital Associate', 'Investment Associate', 0.80, 'venture_capital', 'investment'],
            ['Venture Capital Principal', 'VC Principal', 0.95, 'venture_capital', 'venture_capital'],
            ['Venture Capital Principal', 'Investment Principal', 0.82, 'venture_capital', 'investment'],
            
            // Corporate Development variations
            ['Corporate Development Manager', 'Corp Dev Manager', 0.95, 'corporate_development', 'corporate_development'],
            ['Corporate Development Manager', 'Business Development Manager', 0.80, 'corporate_development', 'business_development'],
            ['Corporate Development Associate', 'Corp Dev Associate', 0.95, 'corporate_development', 'corporate_development'],
            ['Corporate Development Associate', 'Strategic Planning Associate', 0.82, 'corporate_development', 'strategic_planning'],
            
            // Credit variations
            ['Credit Analyst', 'Corporate Credit Analyst', 0.90, 'credit', 'corporate_credit'],
            ['Credit Analyst', 'Consumer Credit Analyst', 0.88, 'credit', 'consumer_credit'],
            ['Credit Analyst', 'Structured Credit Analyst', 0.85, 'credit', 'structured_credit'],
            ['Credit Manager', 'Senior Credit Analyst', 0.88, 'credit', 'credit_analysis'],
            
            // Wealth Management variations
            ['Wealth Manager', 'Private Wealth Manager', 0.92, 'wealth_management', 'private_wealth'],
            ['Wealth Manager', 'Relationship Manager', 0.80, 'wealth_management', 'relationship_management'],
            ['Wealth Manager', 'Investment Advisor', 0.75, 'wealth_management', 'investment_advisory'],
            ['Wealth Manager', 'Portfolio Manager', 0.70, 'wealth_management', 'portfolio'],
        ];
        
        foreach ($job_title_mappings as $mapping) {
            $wpdb->replace(
                $taxonomy_table,
                [
                    'base_title' => $mapping[0],
                    'variant_title' => $mapping[1],
                    'similarity_score' => $mapping[2],
                    'category' => $mapping[3],
                    'industry' => $mapping[4]
                ],
                ['%s', '%s', '%f', '%s', '%s']
            );
        }
        
        error_log("Populated job title taxonomy with " . count($job_title_mappings) . " mappings");
    }
    
    /**
     * Create default user preferences for existing users
     */
    private function create_default_user_preferences() {
        global $wpdb;
        
        $preferences_table = $wpdb->prefix . 'sffc_user_match_preferences';
        $profiles_table = $wpdb->prefix . 'sffc_user_profiles';
        
        // Get users with profiles but no preferences
        $users_without_prefs = $wpdb->get_col("
            SELECT p.user_id 
            FROM $profiles_table p 
            LEFT JOIN $preferences_table pr ON p.user_id = pr.user_id 
            WHERE pr.user_id IS NULL
        ");
        
        foreach ($users_without_prefs as $user_id) {
            $wpdb->insert(
                $preferences_table,
                [
                    'user_id' => $user_id,
                    'min_match_score' => 0.70,
                    'max_daily_matches' => 5,
                    'notification_frequency' => 'immediate',
                    'include_stretch_opportunities' => 1
                ],
                ['%d', '%f', '%d', '%s', '%d']
            );
        }
    }
    
    /**
     * Check and update tables if needed
     */
    public function check_and_update_tables() {
        $current_version = get_option('sffc_matcher_db_version', '0.0.0');
        
        if (version_compare($current_version, '1.0.0', '<')) {
            $this->create_all_tables();
        }
    }
    
    /**
     * Get database statistics for monitoring
     */
    public function get_database_stats() {
        global $wpdb;
        
        $stats = [];
        
        $tables = [
            'sent_matches' => $wpdb->prefix . 'sffc_sent_matches',
            'job_title_taxonomy' => $wpdb->prefix . 'sffc_job_title_taxonomy',
            'cv_extraction_cache' => $wpdb->prefix . 'sffc_cv_extraction_cache',
            'user_match_preferences' => $wpdb->prefix . 'sffc_user_match_preferences',
            'match_analytics' => $wpdb->prefix . 'sffc_match_analytics',
            'title_match_feedback' => $wpdb->prefix . 'sffc_title_match_feedback',
        ];
        
        foreach ($tables as $name => $table) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
            $stats[$name] = [
                'count' => $count,
                'table' => $table
            ];
        }
        
        return $stats;
    }
    
    /**
     * Clean up old data
     */
    public function cleanup_old_data() {
        global $wpdb;
        
        // Clean up old match analytics (keep last 90 days)
        $analytics_table = $wpdb->prefix . 'sffc_match_analytics';
        $wpdb->query("DELETE FROM $analytics_table WHERE calculated_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
        
        // Clean up old CV cache (keep last 30 days)
        $cache_table = $wpdb->prefix . 'sffc_cv_extraction_cache';
        $wpdb->query("DELETE FROM $cache_table WHERE last_used < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        
        // Clean up old sent matches (keep last 60 days)
        $sent_table = $wpdb->prefix . 'sffc_sent_matches';
        $wpdb->query("DELETE FROM $sent_table WHERE sent_at < DATE_SUB(NOW(), INTERVAL 60 DAY)");
        
        error_log("Cleaned up old matcher database data");
    }
}

// Initialize
SFFC_Matcher_Database_Setup::get_instance();

// Schedule daily cleanup
if (!wp_next_scheduled('sffc_matcher_cleanup')) {
    wp_schedule_event(time(), 'daily', 'sffc_matcher_cleanup');
}

add_action('sffc_matcher_cleanup', function() {
    SFFC_Matcher_Database_Setup::get_instance()->cleanup_old_data();
});
