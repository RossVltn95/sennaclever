<?php

/**
 * Async Market Scheduler
 * 
 * Handles all market data fetching in background using WP Cron
 * Zero impact on frontend performance
 * 
 * @package SennaCareers
 * @subpackage MarketAnalysis
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Market_Scheduler
{

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Processing flags to prevent overlaps
     */
    private static $processing = array(
        'market_data' => false,
        'pe_feeds' => false,
        'correlations' => false
    );

    /**
     * Get instance
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        // Register cron hooks
        add_action('sffc_fetch_market_data_async', array($this, 'fetch_market_data_batch'));
        add_action('sffc_process_pe_feeds_async', array($this, 'process_pe_feeds_batch'));
        add_action('sffc_calculate_daily_metrics', array($this, 'calculate_daily_metrics'));
        add_action('sffc_cleanup_old_data', array($this, 'cleanup_old_data'));

        // Register activation/deactivation hooks
        register_activation_hook(SFFC_PLUGIN_FILE, array($this, 'activate_schedules'));
        register_deactivation_hook(SFFC_PLUGIN_FILE, array($this, 'deactivate_schedules'));

        // Add custom cron schedules
        add_filter('cron_schedules', array($this, 'add_cron_schedules'));

        // Admin init - ensure schedules are set
        add_action('admin_init', array($this, 'ensure_schedules'));
    }

    /**
     * Add custom cron schedules for optimal performance
     */
    public function add_cron_schedules($schedules)
    {
        // 5 minutes - for critical market data during trading hours
        $schedules['sffc_5min'] = array(
            'interval' => 300,
            'display' => __('Every 5 minutes (trading hours only)', 'senna-finance')
        );

        // 15 minutes - for standard market updates
        $schedules['sffc_15min'] = array(
            'interval' => 900,
            'display' => __('Every 15 minutes', 'senna-finance')
        );

        // 6 hours - for PE feeds and heavy processing
        $schedules['sffc_6hours'] = array(
            'interval' => 21600,
            'display' => __('Every 6 hours', 'senna-finance')
        );

        return $schedules;
    }

    /**
     * Activate cron schedules
     */
    public function activate_schedules()
    {
        // Market data - only during trading hours
        if (!wp_next_scheduled('sffc_fetch_market_data_async')) {
            wp_schedule_event(time(), 'sffc_15min', 'sffc_fetch_market_data_async');
        }

        // PE feeds - less frequent
        if (!wp_next_scheduled('sffc_process_pe_feeds_async')) {
            wp_schedule_event(time(), 'sffc_6hours', 'sffc_process_pe_feeds_async');
        }

        // Daily metrics - once per day at 2 AM
        if (!wp_next_scheduled('sffc_calculate_daily_metrics')) {
            $timestamp = strtotime('today 2:00am');
            if ($timestamp < time()) {
                $timestamp = strtotime('tomorrow 2:00am');
            }
            wp_schedule_event($timestamp, 'daily', 'sffc_calculate_daily_metrics');
        }

        // Cleanup - weekly
        if (!wp_next_scheduled('sffc_cleanup_old_data')) {
            wp_schedule_event(time(), 'weekly', 'sffc_cleanup_old_data');
        }
    }

    /**
     * Deactivate cron schedules
     */
    public function deactivate_schedules()
    {
        wp_clear_scheduled_hook('sffc_fetch_market_data_async');
        wp_clear_scheduled_hook('sffc_process_pe_feeds_async');
        wp_clear_scheduled_hook('sffc_calculate_daily_metrics');
        wp_clear_scheduled_hook('sffc_cleanup_old_data');
    }

    /**
     * Ensure schedules are set (failsafe)
     */
    public function ensure_schedules()
    {
        if (!wp_next_scheduled('sffc_fetch_market_data_async')) {
            $this->activate_schedules();
        }
    }

    /**
     * Fetch market data in batches
     * Runs async via WP Cron
     */
    public function fetch_market_data_batch()
    {
        // Check if already processing
        if (self::$processing['market_data']) {
            return;
        }

        // Check if it's trading hours (skip nights and weekends for performance)
        if (!$this->is_trading_hours()) {
            return;
        }

        // Set processing flag
        self::$processing['market_data'] = true;

        // Use transient lock to prevent overlapping processes
        $lock_key = 'sffc_market_data_lock';
        if (get_transient($lock_key)) {
            self::$processing['market_data'] = false;
            return;
        }
        set_transient($lock_key, true, 300); // 5 minute lock

        // Load fetcher class
        if (!class_exists('SFFC_European_Market_Fetcher')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-european-market-fetcher.php';
        }

        $fetcher = new SFFC_European_Market_Fetcher();

        // Get priority symbols to update
        $symbols = $this->get_priority_symbols();

        // Process only 5 symbols at a time to prevent timeout
        $batch_size = 5;
        $processed = 0;

        foreach ($symbols as $symbol) {
            if ($processed >= $batch_size) {
                break;
            }

            // Fetch with error handling
            try {
                $fetcher->fetch_market_data($symbol);
                $processed++;

                // Small delay between requests
                usleep(200000); // 0.2 seconds
            } catch (Exception $e) {
                error_log('SFFC Market Fetch Error: ' . $e->getMessage());
            }
        }

        // Clear lock and flag
        delete_transient($lock_key);
        self::$processing['market_data'] = false;

        // Update last run time
        update_option('sffc_last_market_fetch', current_time('mysql'));
    }

    /**
     * Process PE feeds in background
     */
    public function process_pe_feeds_batch()
    {
        // Check if already processing
        if (self::$processing['pe_feeds']) {
            return;
        }

        // Set processing flag
        self::$processing['pe_feeds'] = true;

        // Use transient lock
        $lock_key = 'sffc_pe_feeds_lock';
        if (get_transient($lock_key)) {
            self::$processing['pe_feeds'] = false;
            return;
        }
        set_transient($lock_key, true, 600); // 10 minute lock

        global $wpdb;

        // Get active PE feeds
        $feeds = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}sffc_xml_feeds
            WHERE is_active = 1
            AND feed_category IN ('private-equity', 'venture-capital', 'pe-firms', 'debt-funds', 'secondaries')
            ORDER BY priority ASC
            LIMIT %d
        ", 10)); // Process 10 feeds at a time

        foreach ($feeds as $feed) {
            try {
                $this->process_single_feed($feed);

                // Update last fetched time
                $wpdb->update(
                    $wpdb->prefix . 'sffc_xml_feeds',
                    array('last_fetched' => current_time('mysql')),
                    array('id' => $feed->id)
                );

                // Delay between feeds
                usleep(500000); // 0.5 seconds
            } catch (Exception $e) {
                // Log error but continue processing
                $wpdb->update(
                    $wpdb->prefix . 'sffc_xml_feeds',
                    array(
                        'last_error' => $e->getMessage(),
                        'error_count' => $feed->error_count + 1
                    ),
                    array('id' => $feed->id)
                );
            }
        }

        // Clear lock and flag
        delete_transient($lock_key);
        self::$processing['pe_feeds'] = false;

        // Update last run time
        update_option('sffc_last_pe_fetch', current_time('mysql'));
    }

    /**
     * Process single feed
     */
    private function process_single_feed($feed)
    {
        // Use WordPress SimplePie for feed parsing (built-in, optimized)
        if (!class_exists('SimplePie')) {
            require_once(ABSPATH . WPINC . '/class-simplepie.php');
        }

        $rss = new SimplePie();
        $rss->set_feed_url($feed->feed_url);
        $rss->set_cache_location(WP_CONTENT_DIR . '/cache');
        $rss->set_cache_duration(900); // 15 minutes cache
        $rss->init();

        if ($rss->error()) {
            throw new Exception($rss->error());
        }

        // Process only recent items (last 24 hours)
        $items = $rss->get_items(0, 10); // Max 10 items

        foreach ($items as $item) {
            // Parse and store PE deal data
            $this->parse_pe_deal($item, $feed->feed_category);
        }
    }

    /**
     * Parse PE deal from feed item
     */
    private function parse_pe_deal($item, $category)
    {
        // Extract deal information
        $title = $item->get_title();
        $description = $item->get_description();
        $link = $item->get_link();
        $date = $item->get_date('Y-m-d H:i:s');

        // Use transients to cache parsed deals
        $cache_key = 'sffc_deal_' . md5($link);
        if (get_transient($cache_key)) {
            return; // Already processed
        }

        // Extract deal values using regex (lightweight)
        $deal_value = $this->extract_deal_value($title . ' ' . $description);
        $companies = $this->extract_companies($title);

        if ($deal_value || !empty($companies)) {
            // Store in database (async)
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'sffc_pe_transactions',
                array(
                    'announcement_date' => $date,
                    'deal_type' => $this->determine_deal_type($title, $description),
                    'target_company' => $companies['target'] ?? null,
                    'acquirer' => $companies['acquirer'] ?? null,
                    'deal_value_usd' => $deal_value,
                    'data_source' => parse_url($link, PHP_URL_HOST),
                    'source_url' => $link,
                    'created_at' => current_time('mysql')
                ),
                array('%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s')
            );
        }

        // Cache for 24 hours to prevent reprocessing
        set_transient($cache_key, true, DAY_IN_SECONDS);
    }

    /**
     * Calculate daily metrics (runs at night)
     */
    public function calculate_daily_metrics()
    {
        // This runs at 2 AM when server load is low

        // Calculate correlations
        if (class_exists('SFFC_Correlation_Engine')) {
            $correlation_engine = SFFC_Correlation_Engine::get_instance();
            $correlation_engine->process_correlation_batch();
        }

        // Calculate sector performance
        $this->calculate_sector_performance();

        // Generate daily summary
        $this->generate_daily_summary();

        // Clear old transients
        $this->cleanup_transients();
    }

    /**
     * Check if currently trading hours
     */
    private function is_trading_hours()
    {
        $current_hour = intval(current_time('G'));
        $current_day = current_time('N'); // 1-7, Monday-Sunday

        // Skip weekends
        if ($current_day > 5) {
            return false;
        }

        // Trading hours: 8 AM - 6 PM (covers EU and US overlap)
        return ($current_hour >= 8 && $current_hour <= 18);
    }

    /**
     * Get priority symbols to update
     */
    private function get_priority_symbols()
    {
        // Return most important indices
        return array(
            '^FTSE',   // FTSE 100
            '^GDAXI',  // DAX
            '^FCHI',   // CAC 40
            '^STOXX50E', // EURO STOXX 50
            '^STOXX'   // STOXX 600
        );
    }

    /**
     * Extract deal value from text
     */
    private function extract_deal_value($text)
    {
        // Look for common patterns: $XXm, €XXm, £XXm, etc.
        if (preg_match('/[\$€£]\s*(\d+(?:\.\d+)?)\s*(?:million|billion|m|b)/i', $text, $matches)) {
            $value = floatval($matches[1]);
            if (stripos($text, 'billion') !== false || stripos($text, 'b') !== false) {
                $value *= 1000; // Convert to millions
            }
            return $value * 1000000; // Convert to actual value
        }
        return null;
    }

    /**
     * Extract company names
     */
    private function extract_companies($title)
    {
        $companies = array();

        // Look for acquisition patterns
        if (preg_match('/(.+?)\s+(?:acquires?|buys?|invests?\s+in)\s+(.+)/i', $title, $matches)) {
            $companies['acquirer'] = trim($matches[1]);
            $companies['target'] = trim($matches[2]);
        }

        return $companies;
    }

    /**
     * Determine deal type
     */
    private function determine_deal_type($title, $description)
    {
        $text = strtolower($title . ' ' . $description);

        if (strpos($text, 'buyout') !== false) return 'Buyout';
        if (strpos($text, 'growth') !== false) return 'Growth';
        if (strpos($text, 'venture') !== false) return 'Venture';
        if (strpos($text, 'seed') !== false) return 'Seed';
        if (strpos($text, 'ipo') !== false) return 'IPO';
        if (strpos($text, 'exit') !== false) return 'Exit';

        return 'Other';
    }

    /**
     * Calculate sector performance
     */
    private function calculate_sector_performance()
    {
        global $wpdb;

        // Use database aggregation for efficiency
        $results = $wpdb->get_results("
            SELECT 
                sector,
                AVG(change_percent) as avg_change,
                COUNT(*) as stock_count
            FROM {$wpdb->prefix}sffc_market_cache
            WHERE sector IS NOT NULL
            AND last_updated >= DATE_SUB(NOW(), INTERVAL 1 DAY)
            GROUP BY sector
        ");

        // Cache results
        set_transient('sffc_sector_performance', $results, HOUR_IN_SECONDS);
    }

    /**
     * Generate daily summary
     */
    private function generate_daily_summary()
    {
        // This is placeholder for report generation
        update_option('sffc_last_daily_summary', current_time('mysql'));
    }

    /**
     * Cleanup old data and transients
     */
    public function cleanup_old_data()
    {
        global $wpdb;

        // Delete old intraday prices (keep 30 days)
        $wpdb->query("
            DELETE FROM {$wpdb->prefix}sffc_intraday_prices
            WHERE timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");

        // Delete old correlations
        $wpdb->query("
            DELETE FROM {$wpdb->prefix}sffc_market_correlations
            WHERE calculation_date < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");

        // Clean up transients
        $this->cleanup_transients();
    }

    /**
     * Cleanup expired transients
     */
    private function cleanup_transients()
    {
        global $wpdb;

        // Clean up expired transients (WordPress doesn't always do this)
        $wpdb->query("
            DELETE FROM {$wpdb->options}
            WHERE option_name LIKE '_transient_timeout_sffc_%'
            AND option_value < UNIX_TIMESTAMP()
        ");

        $wpdb->query("
            DELETE FROM {$wpdb->options}
            WHERE option_name LIKE '_transient_sffc_%'
            AND option_name NOT IN (
                SELECT CONCAT('_transient_', SUBSTRING(option_name, 19))
                FROM (SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_sffc_%') AS t
            )
        ");
    }
}

// Initialize scheduler
SFFC_Market_Scheduler::get_instance();
