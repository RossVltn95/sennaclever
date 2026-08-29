<?php
/**
 * Chart AJAX Handlers
 * 
 * Centralized AJAX endpoints for all chart-related requests
 * Optimized for TradingView Lightweight Charts integration
 * 
 * @package SennaCareers
 * @subpackage MarketAnalysis
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Chart_Ajax_Handlers {
    
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
     * Constructor
     */
    private function __construct() {
        // Register all chart-related AJAX endpoints
        $this->register_ajax_handlers();
    }
    
    /**
     * Register AJAX handlers
     */
    private function register_ajax_handlers() {
        // Chart data endpoints
        add_action('wp_ajax_sffc_chart_data', array($this, 'get_chart_data'));
        add_action('wp_ajax_nopriv_sffc_chart_data', array($this, 'get_chart_data'));
        
        add_action('wp_ajax_sffc_chart_indicators', array($this, 'get_chart_indicators'));
        add_action('wp_ajax_nopriv_sffc_chart_indicators', array($this, 'get_chart_indicators'));
        
        add_action('wp_ajax_sffc_chart_compare', array($this, 'get_comparison_chart'));
        add_action('wp_ajax_nopriv_sffc_chart_compare', array($this, 'get_comparison_chart'));
        
        // Market data endpoints
        add_action('wp_ajax_sffc_market_overview', array($this, 'get_market_overview'));
        add_action('wp_ajax_nopriv_sffc_market_overview', array($this, 'get_market_overview'));
        
        add_action('wp_ajax_sffc_symbol_search', array($this, 'search_symbols'));
        add_action('wp_ajax_nopriv_sffc_symbol_search', array($this, 'search_symbols'));
        
        // Economic calendar endpoints
        add_action('wp_ajax_sffc_economic_events', array($this, 'get_economic_events'));
        add_action('wp_ajax_nopriv_sffc_economic_events', array($this, 'get_economic_events'));
        
        // Watchlist endpoints
        add_action('wp_ajax_sffc_watchlist_add', array($this, 'add_to_watchlist'));
        add_action('wp_ajax_sffc_watchlist_remove', array($this, 'remove_from_watchlist'));
        add_action('wp_ajax_sffc_watchlist_get', array($this, 'get_watchlist'));
        
        // Alert endpoints
        add_action('wp_ajax_sffc_price_alert_add', array($this, 'add_price_alert'));
        add_action('wp_ajax_sffc_price_alert_remove', array($this, 'remove_price_alert'));
    }
    
    /**
     * Get chart data
     */
    public function get_chart_data() {
        check_ajax_referer('sffc_ajax_nonce', 'nonce');
        
        $symbol = sanitize_text_field($_POST['symbol'] ?? '');
        $interval = sanitize_text_field($_POST['interval'] ?? '1d');
        $period = sanitize_text_field($_POST['period'] ?? '1m');
        $type = sanitize_text_field($_POST['type'] ?? 'candlestick');
        
        if (!$symbol) {
            wp_send_json_error(array(
                'message' => 'Symbol is required',
                'code' => 'MISSING_SYMBOL'
            ));
        }
        
        // Validate inputs
        $valid_intervals = array('1m', '5m', '15m', '30m', '1h', '4h', '1d', '1w', '1M');
        $valid_periods = array('1d', '5d', '1m', '3m', '6m', '1y', '5y');
        $valid_types = array('candlestick', 'line', 'area', 'bar');
        
        if (!in_array($interval, $valid_intervals)) {
            wp_send_json_error(array('message' => 'Invalid interval'));
        }
        
        if (!in_array($period, $valid_periods)) {
            wp_send_json_error(array('message' => 'Invalid period'));
        }
        
        if (!in_array($type, $valid_types)) {
            wp_send_json_error(array('message' => 'Invalid chart type'));
        }
        
        // Get chart renderer
        if (!class_exists('SFFC_Chart_Renderer')) {
            wp_send_json_error(array('message' => 'Chart renderer not available'));
        }
        
        $renderer = SFFC_Chart_Renderer::get_instance();
        $data = $renderer->get_chart_data($symbol, $interval, $period, $type);
        
        // Add metadata
        $response = array_merge($data, array(
            'meta' => array(
                'symbol' => $symbol,
                'interval' => $interval,
                'period' => $period,
                'type' => $type,
                'count' => count($data['data'] ?? array()),
                'generated_at' => current_time('c')
            )
        ));
        
        wp_send_json_success($response);
    }
    
    /**
     * Get chart indicators
     */
    public function get_chart_indicators() {
        check_ajax_referer('sffc_ajax_nonce', 'nonce');
        
        $symbol = sanitize_text_field($_POST['symbol'] ?? '');
        $indicators = array_map('sanitize_text_field', $_POST['indicators'] ?? array());
        
        if (!$symbol) {
            wp_send_json_error(array('message' => 'Symbol required'));
        }
        
        // Validate indicators
        $valid_indicators = array('sma20', 'sma50', 'sma200', 'ema12', 'ema26', 'bollinger', 'rsi', 'macd');
        $indicators = array_intersect($indicators, $valid_indicators);
        
        if (empty($indicators)) {
            $indicators = array('sma20', 'sma50'); // Default indicators
        }
        
        $renderer = SFFC_Chart_Renderer::get_instance();
        $data = $renderer->get_chart_indicators($symbol, $indicators);
        
        wp_send_json_success(array(
            'symbol' => $symbol,
            'indicators' => $data,
            'requested' => $indicators
        ));
    }
    
    /**
     * Get comparison chart
     */
    public function get_comparison_chart() {
        check_ajax_referer('sffc_ajax_nonce', 'nonce');
        
        $symbols = array_map('sanitize_text_field', $_POST['symbols'] ?? array());
        $period = sanitize_text_field($_POST['period'] ?? '1m');
        
        if (empty($symbols) || count($symbols) < 2) {
            wp_send_json_error(array('message' => 'At least 2 symbols required for comparison'));
        }
        
        if (count($symbols) > 5) {
            wp_send_json_error(array('message' => 'Maximum 5 symbols allowed'));
        }
        
        $renderer = SFFC_Chart_Renderer::get_instance();
        $data = $renderer->get_comparison_data($symbols, $period);
        
        wp_send_json_success(array(
            'type' => 'comparison',
            'period' => $period,
            'symbols' => $symbols,
            'data' => $data
        ));
    }
    
    /**
     * Get market overview
     */
    public function get_market_overview() {
        check_ajax_referer('sffc_ajax_nonce', 'nonce');
        
        $region = sanitize_text_field($_POST['region'] ?? 'europe');
        
        // Use market cache for overview
        global $wpdb;
        
        $overview = $wpdb->get_results($wpdb->prepare("
            SELECT 
                symbol,
                name,
                price,
                change_percent,
                volume,
                market_cap,
                sector
            FROM {$wpdb->prefix}sffc_market_cache
            WHERE region = %s
            AND last_updated >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ORDER BY market_cap DESC
            LIMIT 20
        ", $region));
        
        // Get sector performance
        $sector_data = array();
        if (class_exists('SFFC_Sector_Analyzer')) {
            $analyzer = SFFC_Sector_Analyzer::get_instance();
            $sector_data = $analyzer->get_sector_performance();
        }
        
        // Get market indices
        $indices = $wpdb->get_results($wpdb->prepare("
            SELECT 
                symbol,
                name,
                price,
                change_percent,
                volume
            FROM {$wpdb->prefix}sffc_market_cache
            WHERE region = %s
            AND symbol LIKE '^%'
            ORDER BY change_percent DESC
        ", $region));
        
        wp_send_json_success(array(
            'region' => $region,
            'indices' => $this->format_market_data($indices),
            'top_stocks' => $this->format_market_data($overview),
            'sectors' => $sector_data,
            'updated' => current_time('c')
        ));
    }
    
    /**
     * Search symbols
     */
    public function search_symbols() {
        check_ajax_referer('sffc_ajax_nonce', 'nonce');
        
        $query = sanitize_text_field($_POST['query'] ?? '');
        $limit = intval($_POST['limit'] ?? 10);
        
        if (strlen($query) < 2) {
            wp_send_json_error(array('message' => 'Query too short'));
        }
        
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT 
                symbol,
                name,
                sector,
                market_cap,
                price,
                change_percent
            FROM {$wpdb->prefix}sffc_market_cache
            WHERE (symbol LIKE %s OR name LIKE %s)
            AND last_updated >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY 
                CASE WHEN symbol LIKE %s THEN 1 ELSE 2 END,
                market_cap DESC
            LIMIT %d
        ", 
            $query . '%',
            '%' . $query . '%',
            $query . '%',
            $limit
        ));
        
        wp_send_json_success(array(
            'query' => $query,
            'results' => $this->format_search_results($results),
            'count' => count($results)
        ));
    }
    
    /**
     * Get economic events
     */
    public function get_economic_events() {
        check_ajax_referer('sffc_ajax_nonce', 'nonce');
        
        $period = sanitize_text_field($_POST['period'] ?? 'today');
        $currency = sanitize_text_field($_POST['currency'] ?? null);
        $impact = sanitize_text_field($_POST['impact'] ?? null);
        
        if (!class_exists('SFFC_Economic_Calendar')) {
            wp_send_json_error(array('message' => 'Economic calendar not available'));
        }
        
        $calendar = SFFC_Economic_Calendar::get_instance();
        $events = $calendar->get_events($period, $currency, $impact);
        $summary = $calendar->get_calendar_summary();
        
        wp_send_json_success(array(
            'period' => $period,
            'events' => $events,
            'summary' => $summary,
            'filters' => array(
                'currency' => $currency,
                'impact' => $impact
            )
        ));
    }
    
    /**
     * Add to watchlist
     */
    public function add_to_watchlist() {
        check_ajax_referer('sffc_ajax_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Login required'));
        }
        
        $symbol = sanitize_text_field($_POST['symbol'] ?? '');
        
        if (!$symbol) {
            wp_send_json_error(array('message' => 'Symbol required'));
        }
        
        $user_id = get_current_user_id();
        $watchlist = get_user_meta($user_id, 'sffc_watchlist', true) ?: array();
        
        if (!in_array($symbol, $watchlist)) {
            $watchlist[] = $symbol;
            update_user_meta($user_id, 'sffc_watchlist', $watchlist);
        }
        
        wp_send_json_success(array(
            'symbol' => $symbol,
            'watchlist' => $watchlist,
            'count' => count($watchlist)
        ));
    }
    
    /**
     * Remove from watchlist
     */
    public function remove_from_watchlist() {
        check_ajax_referer('sffc_ajax_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Login required'));
        }
        
        $symbol = sanitize_text_field($_POST['symbol'] ?? '');
        
        if (!$symbol) {
            wp_send_json_error(array('message' => 'Symbol required'));
        }
        
        $user_id = get_current_user_id();
        $watchlist = get_user_meta($user_id, 'sffc_watchlist', true) ?: array();
        
        $watchlist = array_diff($watchlist, array($symbol));
        update_user_meta($user_id, 'sffc_watchlist', array_values($watchlist));
        
        wp_send_json_success(array(
            'symbol' => $symbol,
            'watchlist' => $watchlist,
            'count' => count($watchlist)
        ));
    }
    
    /**
     * Get watchlist
     */
    public function get_watchlist() {
        check_ajax_referer('sffc_ajax_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Login required'));
        }
        
        $user_id = get_current_user_id();
        $watchlist = get_user_meta($user_id, 'sffc_watchlist', true) ?: array();
        
        if (empty($watchlist)) {
            wp_send_json_success(array(
                'symbols' => array(),
                'data' => array(),
                'count' => 0
            ));
        }
        
        // Get current data for watchlist symbols
        global $wpdb;
        
        $placeholders = implode(',', array_fill(0, count($watchlist), '%s'));
        
        $data = $wpdb->get_results($wpdb->prepare("
            SELECT 
                symbol,
                name,
                price,
                change_percent,
                volume,
                last_updated
            FROM {$wpdb->prefix}sffc_market_cache
            WHERE symbol IN ($placeholders)
            ORDER BY FIELD(symbol, " . $placeholders . ")
        ", array_merge($watchlist, $watchlist)));
        
        wp_send_json_success(array(
            'symbols' => $watchlist,
            'data' => $this->format_market_data($data),
            'count' => count($watchlist)
        ));
    }
    
    /**
     * Add price alert
     */
    public function add_price_alert() {
        check_ajax_referer('sffc_ajax_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Login required'));
        }
        
        $symbol = sanitize_text_field($_POST['symbol'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $type = sanitize_text_field($_POST['type'] ?? 'above'); // 'above' or 'below'
        
        if (!$symbol || !$price) {
            wp_send_json_error(array('message' => 'Symbol and price required'));
        }
        
        $user_id = get_current_user_id();
        
        global $wpdb;
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'sffc_price_alerts',
            array(
                'user_id' => $user_id,
                'symbol' => $symbol,
                'target_price' => $price,
                'alert_type' => $type,
                'is_active' => 1,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%f', '%s', '%d', '%s')
        );
        
        if ($result) {
            wp_send_json_success(array(
                'alert_id' => $wpdb->insert_id,
                'symbol' => $symbol,
                'price' => $price,
                'type' => $type
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to create alert'));
        }
    }
    
    /**
     * Remove price alert
     */
    public function remove_price_alert() {
        check_ajax_referer('sffc_ajax_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Login required'));
        }
        
        $alert_id = intval($_POST['alert_id'] ?? 0);
        
        if (!$alert_id) {
            wp_send_json_error(array('message' => 'Alert ID required'));
        }
        
        $user_id = get_current_user_id();
        
        global $wpdb;
        
        $result = $wpdb->delete(
            $wpdb->prefix . 'sffc_price_alerts',
            array(
                'id' => $alert_id,
                'user_id' => $user_id
            ),
            array('%d', '%d')
        );
        
        if ($result) {
            wp_send_json_success(array('alert_id' => $alert_id));
        } else {
            wp_send_json_error(array('message' => 'Alert not found or access denied'));
        }
    }
    
    /**
     * Format market data
     */
    private function format_market_data($data) {
        return array_map(function($item) {
            return array(
                'symbol' => $item->symbol,
                'name' => $item->name ?? $item->symbol,
                'price' => floatval($item->price),
                'change' => floatval($item->change_percent ?? 0),
                'volume' => intval($item->volume ?? 0),
                'market_cap' => isset($item->market_cap) ? intval($item->market_cap) : null,
                'sector' => $item->sector ?? null,
                'updated' => isset($item->last_updated) ? $item->last_updated : null
            );
        }, $data);
    }
    
    /**
     * Format search results
     */
    private function format_search_results($results) {
        return array_map(function($item) {
            return array(
                'symbol' => $item->symbol,
                'name' => $item->name,
                'sector' => $item->sector,
                'price' => floatval($item->price),
                'change' => floatval($item->change_percent),
                'market_cap' => intval($item->market_cap),
                'label' => $item->symbol . ' - ' . $item->name,
                'value' => $item->symbol
            );
        }, $results);
    }
}

// Initialize
SFFC_Chart_Ajax_Handlers::get_instance();
?>