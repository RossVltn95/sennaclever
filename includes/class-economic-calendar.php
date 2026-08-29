<?php
/**
 * Economic Calendar Integration
 * 
 * Fetches and caches economic events from multiple sources
 * Optimized for zero frontend impact using background processing
 * 
 * @package SennaCareers
 * @subpackage MarketAnalysis
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Economic_Calendar {
    
    /**
     * API endpoints for economic data
     */
    const ALPHA_VANTAGE_ENDPOINT = 'https://www.alphavantage.com/query';
    const FXSTREET_RSS = 'https://www.fxstreet.com/rss/event-calendar';
    
    /**
     * Cache durations
     */
    const CACHE_SHORT = 900;    // 15 minutes for upcoming events
    const CACHE_MEDIUM = 3600;  // 1 hour for today's events
    const CACHE_LONG = 86400;   // 24 hours for weekly view
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Processing lock
     */
    private static $processing = false;
    
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
        // Register cron hooks for background fetching
        add_action('sffc_fetch_economic_events', array($this, 'fetch_events_batch'));
        add_action('sffc_process_event_impact', array($this, 'analyze_event_impact'));
        
        // AJAX endpoints
        add_action('wp_ajax_sffc_get_economic_events', array($this, 'ajax_get_events'));
        add_action('wp_ajax_nopriv_sffc_get_economic_events', array($this, 'ajax_get_events'));
        
        // Schedule fetching if not already scheduled
        if (!wp_next_scheduled('sffc_fetch_economic_events')) {
            wp_schedule_event(time(), 'hourly', 'sffc_fetch_economic_events');
        }
        
    }
    
    /**
     * Create macro_events table if needed
     */
    public function maybe_create_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sffc_macro_events';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            $this->create_events_table();
        }
    }
    
    /**
     * Create events table
     */
    private function create_events_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sffc_macro_events';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            event_date datetime NOT NULL,
            event_time varchar(10),
            currency varchar(10),
            impact enum('low','medium','high') DEFAULT 'medium',
            event_name varchar(255) NOT NULL,
            actual_value varchar(50),
            forecast_value varchar(50),
            previous_value varchar(50),
            description text,
            source varchar(50),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_event_date (event_date),
            KEY idx_currency (currency),
            KEY idx_impact (impact)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Get economic events (cached only)
     */
    public function get_events($period = 'today', $currency = null, $impact = null) {
        $cache_key = 'sffc_eco_events_' . md5($period . '_' . $currency . '_' . $impact);
        $cached = get_transient($cache_key);
        
        if ($cached === false) {
            // Schedule background fetch if not available
            wp_schedule_single_event(time() + 1, 'sffc_fetch_economic_events', array($period));
            
            // Return from database if available
            return $this->get_events_from_db($period, $currency, $impact);
        }
        
        return $cached;
    }
    
    /**
     * Get events from database
     */
    private function get_events_from_db($period, $currency = null, $impact = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sffc_macro_events';
        
        // Build date range
        $where = $this->build_date_where($period);
        
        // Add currency filter
        if ($currency) {
            $where .= $wpdb->prepare(" AND currency = %s", $currency);
        }
        
        // Add impact filter  
        if ($impact) {
            $where .= $wpdb->prepare(" AND impact = %s", $impact);
        }
        
        $results = $wpdb->get_results(
            "SELECT * FROM $table_name 
             WHERE 1=1 $where 
             ORDER BY event_date ASC 
             LIMIT 50"
        );
        
        return $this->format_events($results);
    }
    
    /**
     * Fetch events in background (via cron)
     */
    public function fetch_events_batch($period = 'week') {
        // Prevent overlapping
        if (self::$processing) {
            return;
        }
        
        // Set lock
        $lock_key = 'sffc_eco_fetch_lock';
        if (get_transient($lock_key)) {
            return;
        }
        set_transient($lock_key, true, 300);
        self::$processing = true;
        
        try {
            // Try multiple sources
            $events = array();
            
            // 1. Try Alpha Vantage (if API key available)
            $av_key = get_option('sffc_alpha_vantage_key');
            if ($av_key) {
                $av_events = $this->fetch_from_alpha_vantage($av_key);
                $events = array_merge($events, $av_events);
            }
            
            // 2. Try FXStreet RSS
            $fx_events = $this->fetch_from_fxstreet();
            $events = array_merge($events, $fx_events);
            
            // 3. Try stored manual events
            $manual_events = $this->get_manual_events($period);
            $events = array_merge($events, $manual_events);
            
            // Store events in database
            $this->store_events($events);
            
            // Cache processed events
            $this->cache_events_by_period($events);
            
        } catch (Exception $e) {
            error_log('SFFC Economic Calendar Error: ' . $e->getMessage());
        }
        
        // Clear lock
        delete_transient($lock_key);
        self::$processing = false;
        
        // Update last fetch time
        update_option('sffc_last_eco_fetch', current_time('mysql'));
    }
    
    /**
     * Fetch from Alpha Vantage
     */
    private function fetch_from_alpha_vantage($api_key) {
        $events = array();
        
        // Alpha Vantage doesn't have direct economic calendar
        // We'll fetch major indicators instead
        $indicators = array(
            'REAL_GDP' => 'GDP Growth Rate',
            'INFLATION' => 'Inflation Rate', 
            'UNEMPLOYMENT' => 'Unemployment Rate',
            'FEDERAL_FUNDS_RATE' => 'Federal Funds Rate',
            'CPI' => 'Consumer Price Index',
            'RETAIL_SALES' => 'Retail Sales'
        );
        
        foreach ($indicators as $function => $name) {
            $url = self::ALPHA_VANTAGE_ENDPOINT . '?' . http_build_query(array(
                'function' => $function,
                'interval' => 'monthly',
                'apikey' => $api_key
            ));
            
            $response = wp_remote_get($url, array(
                'timeout' => 10,
                'headers' => array('Accept' => 'application/json')
            ));
            
            if (!is_wp_error($response)) {
                $data = json_decode(wp_remote_retrieve_body($response), true);
                
                if (isset($data['data']) && is_array($data['data'])) {
                    // Get latest data point
                    $latest = reset($data['data']);
                    if ($latest) {
                        $events[] = array(
                            'event_date' => $latest['date'] ?? date('Y-m-d'),
                            'event_name' => $name,
                            'actual_value' => $latest['value'] ?? null,
                            'currency' => 'USD',
                            'impact' => $this->determine_impact($function),
                            'source' => 'Alpha Vantage'
                        );
                    }
                }
            }
            
            // Rate limiting
            usleep(200000); // 0.2 seconds between requests
        }
        
        return $events;
    }
    
    /**
     * Fetch from FXStreet RSS
     */
    private function fetch_from_fxstreet() {
        $events = array();
        
        // Use SimplePie for RSS parsing
        if (!class_exists('SimplePie')) {
            require_once(ABSPATH . WPINC . '/class-simplepie.php');
        }
        
        $rss = new SimplePie();
        $rss->set_feed_url(self::FXSTREET_RSS);
        $rss->set_cache_location(WP_CONTENT_DIR . '/cache');
        $rss->set_cache_duration(900); // 15 minutes
        $rss->init();
        
        if (!$rss->error()) {
            $items = $rss->get_items(0, 20); // Get latest 20 events
            
            foreach ($items as $item) {
                $title = $item->get_title();
                $description = $item->get_description();
                $date = $item->get_date('Y-m-d H:i:s');
                
                // Parse event details from description
                $parsed = $this->parse_fx_event($title, $description);
                
                if ($parsed) {
                    $events[] = array_merge($parsed, array(
                        'event_date' => $date,
                        'source' => 'FXStreet'
                    ));
                }
            }
        }
        
        return $events;
    }
    
    /**
     * Parse FXStreet event
     */
    private function parse_fx_event($title, $description) {
        // Extract currency (usually first 3 letters)
        $currency = null;
        if (preg_match('/^([A-Z]{3})\s/', $title, $matches)) {
            $currency = $matches[1];
        }
        
        // Determine impact based on keywords
        $impact = 'medium';
        if (stripos($title, 'GDP') !== false || stripos($title, 'NFP') !== false) {
            $impact = 'high';
        } elseif (stripos($title, 'speech') !== false || stripos($title, 'minutes') !== false) {
            $impact = 'low';
        }
        
        // Extract values from description
        $actual = $forecast = $previous = null;
        if (preg_match('/Actual:\s*([\d\.\-\+]+)/', $description, $matches)) {
            $actual = $matches[1];
        }
        if (preg_match('/Forecast:\s*([\d\.\-\+]+)/', $description, $matches)) {
            $forecast = $matches[1];
        }
        if (preg_match('/Previous:\s*([\d\.\-\+]+)/', $description, $matches)) {
            $previous = $matches[1];
        }
        
        return array(
            'event_name' => $title,
            'currency' => $currency,
            'impact' => $impact,
            'actual_value' => $actual,
            'forecast_value' => $forecast,
            'previous_value' => $previous,
            'description' => strip_tags($description)
        );
    }
    
    /**
     * Get manual/predefined events
     */
    private function get_manual_events($period) {
        // Major recurring events
        $events = array();
        
        // ECB meetings (usually Thursday every 6 weeks)
        $events[] = array(
            'event_date' => $this->get_next_ecb_meeting(),
            'event_name' => 'ECB Interest Rate Decision',
            'currency' => 'EUR',
            'impact' => 'high',
            'source' => 'Manual'
        );
        
        // Bank of England meetings (8 times per year)
        $events[] = array(
            'event_date' => $this->get_next_boe_meeting(),
            'event_name' => 'BoE Interest Rate Decision',
            'currency' => 'GBP',
            'impact' => 'high',
            'source' => 'Manual'
        );
        
        // NFP (first Friday of each month)
        $events[] = array(
            'event_date' => $this->get_next_nfp_date(),
            'event_name' => 'US Non-Farm Payrolls',
            'currency' => 'USD',
            'impact' => 'high',
            'source' => 'Manual'
        );
        
        return $events;
    }
    
    /**
     * Store events in database
     */
    private function store_events($events) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sffc_macro_events';
        
        foreach ($events as $event) {
            // Check if event already exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name 
                 WHERE event_name = %s 
                 AND event_date = %s",
                $event['event_name'],
                $event['event_date']
            ));
            
            if (!$exists) {
                $wpdb->insert(
                    $table_name,
                    array(
                        'event_date' => $event['event_date'],
                        'event_time' => $event['event_time'] ?? null,
                        'currency' => $event['currency'] ?? null,
                        'impact' => $event['impact'] ?? 'medium',
                        'event_name' => $event['event_name'],
                        'actual_value' => $event['actual_value'] ?? null,
                        'forecast_value' => $event['forecast_value'] ?? null,
                        'previous_value' => $event['previous_value'] ?? null,
                        'description' => $event['description'] ?? null,
                        'source' => $event['source'] ?? 'Unknown'
                    ),
                    array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
                );
            } else {
                // Update if actual value is available
                if (isset($event['actual_value']) && $event['actual_value']) {
                    $wpdb->update(
                        $table_name,
                        array('actual_value' => $event['actual_value']),
                        array('id' => $exists)
                    );
                }
            }
        }
    }
    
    /**
     * Cache events by period
     */
    private function cache_events_by_period($events) {
        // Group by period
        $today = array();
        $week = array();
        $high_impact = array();
        
        $today_date = date('Y-m-d');
        $week_end = date('Y-m-d', strtotime('+7 days'));
        
        foreach ($events as $event) {
            $event_date = date('Y-m-d', strtotime($event['event_date']));
            
            if ($event_date == $today_date) {
                $today[] = $event;
            }
            
            if ($event_date <= $week_end) {
                $week[] = $event;
            }
            
            if ($event['impact'] === 'high') {
                $high_impact[] = $event;
            }
        }
        
        // Cache different views
        set_transient('sffc_eco_events_today', $today, self::CACHE_SHORT);
        set_transient('sffc_eco_events_week', $week, self::CACHE_MEDIUM);
        set_transient('sffc_eco_events_high', $high_impact, self::CACHE_MEDIUM);
    }
    
    /**
     * Analyze event impact on markets
     */
    public function analyze_event_impact() {
        global $wpdb;
        
        // Get recent high-impact events
        $events = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}sffc_macro_events
             WHERE impact = 'high'
             AND event_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             AND actual_value IS NOT NULL"
        );
        
        foreach ($events as $event) {
            // Check if actual vs forecast deviation is significant
            if ($event->forecast_value && $event->actual_value) {
                $deviation = (floatval($event->actual_value) - floatval($event->forecast_value)) / floatval($event->forecast_value);
                
                if (abs($deviation) > 0.1) { // More than 10% deviation
                    // Store alert
                    $this->create_market_alert($event, $deviation);
                }
            }
        }
    }
    
    /**
     * Create market alert
     */
    private function create_market_alert($event, $deviation) {
        $alert = array(
            'type' => 'economic_event',
            'severity' => abs($deviation) > 0.2 ? 'high' : 'medium',
            'title' => sprintf('%s: Significant Deviation', $event->event_name),
            'message' => sprintf(
                '%s came in at %s vs forecast of %s (%.1f%% deviation)',
                $event->event_name,
                $event->actual_value,
                $event->forecast_value,
                $deviation * 100
            ),
            'currency' => $event->currency,
            'timestamp' => current_time('mysql')
        );
        
        // Cache alert
        $alerts = get_transient('sffc_market_alerts') ?: array();
        array_unshift($alerts, $alert);
        $alerts = array_slice($alerts, 0, 10); // Keep only 10 latest
        set_transient('sffc_market_alerts', $alerts, HOUR_IN_SECONDS);
    }
    
    /**
     * AJAX handler
     */
    public function ajax_get_events() {
        check_ajax_referer('sffc_ajax_nonce', 'nonce');
        
        $period = sanitize_text_field($_POST['period'] ?? 'today');
        $currency = sanitize_text_field($_POST['currency'] ?? null);
        $impact = sanitize_text_field($_POST['impact'] ?? null);
        
        $events = $this->get_events($period, $currency, $impact);
        
        wp_send_json_success(array(
            'events' => $events,
            'alerts' => get_transient('sffc_market_alerts') ?: array()
        ));
    }
    
    /**
     * Build date WHERE clause
     */
    private function build_date_where($period) {
        global $wpdb;
        
        switch ($period) {
            case 'today':
                return $wpdb->prepare(" AND DATE(event_date) = %s", date('Y-m-d'));
            case 'tomorrow':
                return $wpdb->prepare(" AND DATE(event_date) = %s", date('Y-m-d', strtotime('+1 day')));
            case 'week':
                return $wpdb->prepare(" AND event_date BETWEEN %s AND %s", 
                    date('Y-m-d'), 
                    date('Y-m-d', strtotime('+7 days'))
                );
            case 'month':
                return $wpdb->prepare(" AND event_date BETWEEN %s AND %s",
                    date('Y-m-d'),
                    date('Y-m-d', strtotime('+30 days'))
                );
            default:
                return " AND event_date >= CURDATE()";
        }
    }
    
    /**
     * Format events for display
     */
    private function format_events($events) {
        $formatted = array();
        
        foreach ($events as $event) {
            $formatted[] = array(
                'id' => $event->id,
                'date' => date('Y-m-d', strtotime($event->event_date)),
                'time' => $event->event_time ?: date('H:i', strtotime($event->event_date)),
                'currency' => $event->currency,
                'impact' => $event->impact,
                'impact_class' => 'impact-' . $event->impact,
                'name' => $event->event_name,
                'actual' => $event->actual_value,
                'forecast' => $event->forecast_value,
                'previous' => $event->previous_value,
                'description' => $event->description,
                'source' => $event->source,
                'is_past' => strtotime($event->event_date) < time(),
                'is_today' => date('Y-m-d', strtotime($event->event_date)) == date('Y-m-d'),
                'relative_time' => $this->get_relative_time($event->event_date)
            );
        }
        
        return $formatted;
    }
    
    /**
     * Get relative time
     */
    private function get_relative_time($datetime) {
        $timestamp = strtotime($datetime);
        $diff = $timestamp - time();
        
        if ($diff < 0) {
            return 'Past';
        } elseif ($diff < 3600) {
            return 'In ' . round($diff / 60) . ' minutes';
        } elseif ($diff < 86400) {
            return 'In ' . round($diff / 3600) . ' hours';
        } else {
            return 'In ' . round($diff / 86400) . ' days';
        }
    }
    
    /**
     * Determine impact level
     */
    private function determine_impact($indicator) {
        $high_impact = array('REAL_GDP', 'UNEMPLOYMENT', 'FEDERAL_FUNDS_RATE', 'NFP');
        $low_impact = array('CONSUMER_SENTIMENT', 'TREASURY_YIELD');
        
        if (in_array($indicator, $high_impact)) {
            return 'high';
        } elseif (in_array($indicator, $low_impact)) {
            return 'low';
        }
        
        return 'medium';
    }
    
    /**
     * Get next ECB meeting date
     */
    private function get_next_ecb_meeting() {
        // ECB meets roughly every 6 weeks on Thursday
        // This is simplified - in production would use actual calendar
        $next_thursday = strtotime('next thursday');
        return date('Y-m-d 13:45:00', $next_thursday);
    }
    
    /**
     * Get next BoE meeting date  
     */
    private function get_next_boe_meeting() {
        // BoE meets 8 times per year
        // Simplified calculation
        $next_thursday = strtotime('next thursday');
        return date('Y-m-d 12:00:00', $next_thursday);
    }
    
    /**
     * Get next NFP date
     */
    private function get_next_nfp_date() {
        // First Friday of the month at 8:30 AM EST
        $first_friday = strtotime('first friday of next month');
        return date('Y-m-d 13:30:00', $first_friday); // Adjusted for UTC
    }
    
    /**
     * Get calendar summary
     */
    public function get_calendar_summary() {
        $cache_key = 'sffc_calendar_summary';
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        global $wpdb;
        
        $summary = array(
            'today_count' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}sffc_macro_events 
                 WHERE DATE(event_date) = CURDATE()"
            ),
            'week_count' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}sffc_macro_events 
                 WHERE event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)"
            ),
            'high_impact_count' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}sffc_macro_events 
                 WHERE impact = 'high' 
                 AND event_date >= CURDATE()"
            ),
            'next_high_impact' => $wpdb->get_row(
                "SELECT * FROM {$wpdb->prefix}sffc_macro_events 
                 WHERE impact = 'high' 
                 AND event_date >= NOW() 
                 ORDER BY event_date ASC 
                 LIMIT 1"
            )
        );
        
        set_transient($cache_key, $summary, self::CACHE_SHORT);
        
        return $summary;
    }
}

// Initialize
SFFC_Economic_Calendar::get_instance();
?>
