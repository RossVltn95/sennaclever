<?php

/**
 * European Markets Activator
 * 
 * Handles activation and deactivation of European market features
 * 
 * @package SennaCareers
 * @subpackage EuropeanMarkets
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_European_Activator
{

    /**
     * Activate European Markets features
     */
    public static function activate()
    {
        // Create tables
        self::create_tables();

        // Set default options
        self::set_default_options();

        // Schedule cron jobs
        self::schedule_cron_jobs();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Deactivate European Markets features
     */
    public static function deactivate()
    {
        // Clear scheduled cron jobs
        self::clear_cron_jobs();
    }

    /**
     * Create all European market tables
     */
    public static function create_tables()
    {
        require_once SFFC_PLUGIN_DIR . 'includes/class-european-database.php';

        $eu_db = SFFC_European_Database::get_instance();
        $results = $eu_db->create_tables();

        // Log results
        foreach ($results as $table => $success) {
            if ($success) {
                error_log("SFFC European Activator: Successfully created table '{$table}'");
            } else {
                error_log("SFFC European Activator: Failed to create table '{$table}'");
            }
        }

        return $results;
    }

    /**
     * Set default options
     */
    private static function set_default_options()
    {
        // Auto-create tables option
        add_option('sffc_eu_auto_create_tables', true);

        // Market data fetch frequency (in minutes)
        add_option('sffc_eu_market_fetch_frequency', 15);

        // Enable real-time updates
        add_option('sffc_eu_enable_realtime', false);

        // Default currency
        add_option('sffc_eu_default_currency', 'EUR');

        // API keys placeholder
        add_option('sffc_alpha_vantage_api_key', '');
        add_option('sffc_yahoo_finance_enabled', true);

        // PE data settings
        add_option('sffc_eu_pe_tracking_enabled', true);
        add_option('sffc_eu_pe_data_source', 'feeds');

        // Cache settings
        add_option('sffc_eu_cache_duration', 300); // 5 minutes
        add_option('sffc_eu_enable_cache', true);

        // Installation timestamp
        add_option('sffc_eu_installed_at', current_time('mysql'));

        // Version tracking
        add_option('sffc_eu_version', '2.0.0');
    }

    /**
     * Schedule cron jobs
     */
    private static function schedule_cron_jobs()
    {
        // Schedule market data fetch
        if (!wp_next_scheduled('sffc_fetch_european_markets')) {
            wp_schedule_event(time(), 'sffc_fifteen_minutes', 'sffc_fetch_european_markets');
        }

        // Schedule exchange rate updates
        if (!wp_next_scheduled('sffc_fetch_exchange_rates')) {
            wp_schedule_event(time(), 'daily', 'sffc_fetch_exchange_rates');
        }

        // Schedule PE data aggregation
        if (!wp_next_scheduled('sffc_aggregate_pe_data')) {
            wp_schedule_event(time(), 'hourly', 'sffc_aggregate_pe_data');
        }

        // Add custom cron schedule
        add_filter('cron_schedules', array(__CLASS__, 'add_cron_schedules'));
    }

    /**
     * Clear cron jobs
     */
    private static function clear_cron_jobs()
    {
        wp_clear_scheduled_hook('sffc_fetch_european_markets');
        wp_clear_scheduled_hook('sffc_fetch_exchange_rates');
        wp_clear_scheduled_hook('sffc_aggregate_pe_data');
    }

    /**
     * Add custom cron schedules
     */
    public static function add_cron_schedules($schedules)
    {
        // Add 15 minute schedule
        $schedules['sffc_fifteen_minutes'] = array(
            'interval' => 900,
            'display' => __('Every 15 minutes', 'senna-finance')
        );

        return $schedules;
    }

    /**
     * Check if tables need creation/update
     */
    public static function check_tables()
    {
        require_once SFFC_PLUGIN_DIR . 'includes/class-european-database.php';

        $eu_db = SFFC_European_Database::get_instance();
        $status = $eu_db->get_tables_status();

        $missing = array();
        foreach ($status as $key => $table) {
            if (!$table['exists']) {
                $missing[] = $key;
            }
        }

        return array(
            'all_exist' => empty($missing),
            'missing' => $missing,
            'status' => $status
        );
    }

    /**
     * Manual table creation
     */
    public static function create_missing_tables()
    {
        $check = self::check_tables();

        if ($check['all_exist']) {
            return array(
                'success' => true,
                'message' => 'All tables already exist'
            );
        }

        require_once SFFC_PLUGIN_DIR . 'includes/class-european-database.php';
        $eu_db = SFFC_European_Database::get_instance();

        $results = array();
        foreach ($check['missing'] as $table_key) {
            $results[$table_key] = $eu_db->create_missing_table($table_key);
        }

        return array(
            'success' => !in_array(false, $results),
            'results' => $results,
            'message' => 'Tables creation completed'
        );
    }

    /**
     * Uninstall European Markets features
     */
    public static function uninstall()
    {
        // Only run if explicitly confirmed
        if (!defined('SFFC_UNINSTALL_EUROPEAN_MARKETS')) {
            return;
        }

        // Remove options
        delete_option('sffc_eu_auto_create_tables');
        delete_option('sffc_eu_market_fetch_frequency');
        delete_option('sffc_eu_enable_realtime');
        delete_option('sffc_eu_default_currency');
        delete_option('sffc_alpha_vantage_api_key');
        delete_option('sffc_yahoo_finance_enabled');
        delete_option('sffc_eu_pe_tracking_enabled');
        delete_option('sffc_eu_pe_data_source');
        delete_option('sffc_eu_cache_duration');
        delete_option('sffc_eu_enable_cache');
        delete_option('sffc_eu_installed_at');
        delete_option('sffc_eu_version');
        delete_option('sffc_eu_db_version');
        delete_option('sffc_european_markets_last_fetch');

        // Clear cron jobs
        self::clear_cron_jobs();

        // Drop tables if requested
        if (defined('SFFC_DROP_EUROPEAN_TABLES') && SFFC_DROP_EUROPEAN_TABLES) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-european-database.php';
            $eu_db = SFFC_European_Database::get_instance();

            global $wpdb;
            foreach ($eu_db->eu_tables as $table_name) {
                $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
            }
        }
    }

    /**
     * Run database upgrade if needed
     */
    public static function maybe_upgrade()
    {
        $current_version = get_option('sffc_eu_version', '1.0.0');

        if (version_compare($current_version, '2.0.0', '<')) {
            // Run upgrade routines
            self::upgrade_to_2_0_0();
        }
    }

    /**
     * Upgrade to version 2.0.0
     */
    private static function upgrade_to_2_0_0()
    {
        // Create any new tables
        self::create_tables();

        // Update version
        update_option('sffc_eu_version', '2.0.0');

        error_log('SFFC European Markets upgraded to version 2.0.0');
    }
}
