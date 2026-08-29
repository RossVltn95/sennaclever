<?php
/**
 * Chart Data Renderer
 * 
 * Prepares and caches chart data for TradingView Lightweight Charts
 * All data preparation done in background, frontend only receives JSON
 * 
 * @package SennaCareers
 * @subpackage MarketAnalysis
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Chart_Renderer {
    
    /**
     * Cache durations
     */
    const CACHE_INTRADAY = 300;     // 5 minutes for intraday
    const CACHE_DAILY = 3600;       // 1 hour for daily
    const CACHE_WEEKLY = 86400;     // 24 hours for weekly
    
    /**
     * Chart types supported
     */
    const CHART_TYPES = array(
        'candlestick',
        'line',
        'area',
        'bar',
        'histogram',
        'baseline'
    );
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Processing flag
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
        // AJAX endpoints for chart data
        add_action('wp_ajax_sffc_get_chart_data', array($this, 'ajax_get_chart_data'));
        add_action('wp_ajax_nopriv_sffc_get_chart_data', array($this, 'ajax_get_chart_data'));
        
        add_action('wp_ajax_sffc_get_chart_indicators', array($this, 'ajax_get_chart_indicators'));
        add_action('wp_ajax_nopriv_sffc_get_chart_indicators', array($this, 'ajax_get_chart_indicators'));
        
        // Background processing
        add_action('sffc_prepare_chart_data', array($this, 'prepare_chart_data_batch'));
        
        // Schedule chart data preparation
        if (!wp_next_scheduled('sffc_prepare_chart_data')) {
            wp_schedule_event(time(), 'hourly', 'sffc_prepare_chart_data');
        }
    }
    
    /**
     * Get chart data (cached only)
     */
    public function get_chart_data($symbol, $interval = '1d', $period = '1m', $type = 'candlestick') {
        $cache_key = 'sffc_chart_' . md5($symbol . '_' . $interval . '_' . $period . '_' . $type);
        $cached = get_transient($cache_key);
        
        if ($cached === false) {
            // Schedule background preparation
            wp_schedule_single_event(time() + 1, 'sffc_prepare_chart_data', array($symbol, $interval, $period));
            
            // Return placeholder data
            return array(
                'status' => 'preparing',
                'symbol' => $symbol,
                'data' => array()
            );
        }
        
        return $cached;
    }
    
    /**
     * Prepare chart data in background
     */
    public function prepare_chart_data_batch($symbol = null, $interval = '1d', $period = '1m') {
        // Prevent overlapping
        if (self::$processing) {
            return;
        }
        
        $lock_key = 'sffc_chart_prep_lock';
        if (get_transient($lock_key)) {
            return;
        }
        set_transient($lock_key, true, 300);
        self::$processing = true;
        
        global $wpdb;
        
        // Get symbols to prepare
        if ($symbol) {
            $symbols = array($symbol);
        } else {
            // Get top active symbols
            $symbols = $wpdb->get_col(
                "SELECT DISTINCT symbol 
                 FROM {$wpdb->prefix}sffc_intraday_prices
                 WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 1 DAY)
                 GROUP BY symbol
                 ORDER BY COUNT(*) DESC
                 LIMIT 10"
            );
        }
        
        foreach ($symbols as $sym) {
            // Prepare different chart types
            $this->prepare_candlestick_data($sym, $interval, $period);
            $this->prepare_line_data($sym, $interval, $period);
            $this->prepare_volume_data($sym, $interval, $period);
            
            // Small delay
            usleep(100000);
        }
        
        // Clear lock
        delete_transient($lock_key);
        self::$processing = false;
    }
    
    /**
     * Prepare candlestick data
     */
    private function prepare_candlestick_data($symbol, $interval, $period) {
        global $wpdb;
        
        // Determine date range
        $date_range = $this->get_date_range($period);
        
        // Get data based on interval
        if ($interval === '1m' || $interval === '5m' || $interval === '15m') {
            // Intraday data
            $data = $this->get_intraday_candles($symbol, $interval, $date_range);
        } else {
            // Daily/weekly/monthly data
            $data = $this->get_daily_candles($symbol, $interval, $date_range);
        }
        
        // Format for TradingView
        $formatted = $this->format_candlestick_data($data);
        
        // Cache the data
        $cache_key = 'sffc_chart_' . md5($symbol . '_' . $interval . '_' . $period . '_candlestick');
        $cache_duration = $this->get_cache_duration($interval);
        
        set_transient($cache_key, array(
            'status' => 'ready',
            'symbol' => $symbol,
            'interval' => $interval,
            'period' => $period,
            'type' => 'candlestick',
            'data' => $formatted,
            'updated' => current_time('mysql')
        ), $cache_duration);
    }
    
    /**
     * Get intraday candles
     */
    private function get_intraday_candles($symbol, $interval, $date_range) {
        global $wpdb;
        
        // Convert interval to minutes
        $minutes = $this->interval_to_minutes($interval);
        
        $query = $wpdb->prepare("
            SELECT 
                UNIX_TIMESTAMP(
                    FROM_UNIXTIME(
                        FLOOR(UNIX_TIMESTAMP(timestamp) / %d) * %d
                    )
                ) as time,
                MIN(price) as low,
                MAX(price) as high,
                SUBSTRING_INDEX(GROUP_CONCAT(price ORDER BY timestamp ASC), ',', 1) as open,
                SUBSTRING_INDEX(GROUP_CONCAT(price ORDER BY timestamp DESC), ',', 1) as close,
                SUM(volume) as volume
            FROM {$wpdb->prefix}sffc_intraday_prices
            WHERE symbol = %s
            AND timestamp >= %s
            GROUP BY FLOOR(UNIX_TIMESTAMP(timestamp) / %d)
            ORDER BY time ASC
        ", $minutes * 60, $minutes * 60, $symbol, $date_range['start'], $minutes * 60);
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Get daily candles
     */
    private function get_daily_candles($symbol, $interval, $date_range) {
        global $wpdb;
        
        $group_by = $this->get_group_by_clause($interval);
        
        $query = $wpdb->prepare("
            SELECT 
                UNIX_TIMESTAMP(DATE(price_date)) as time,
                open_price as open,
                high_price as high,
                low_price as low,
                close_price as close,
                volume
            FROM {$wpdb->prefix}sffc_historical_prices
            WHERE symbol = %s
            AND price_date >= %s
            $group_by
            ORDER BY time ASC
        ", $symbol, $date_range['start']);
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Prepare line chart data
     */
    private function prepare_line_data($symbol, $interval, $period) {
        global $wpdb;
        
        $date_range = $this->get_date_range($period);
        
        // Get closing prices
        if ($this->is_intraday($interval)) {
            $query = $wpdb->prepare("
                SELECT 
                    UNIX_TIMESTAMP(timestamp) as time,
                    price as value
                FROM {$wpdb->prefix}sffc_intraday_prices
                WHERE symbol = %s
                AND timestamp >= %s
                ORDER BY timestamp ASC
            ", $symbol, $date_range['start']);
        } else {
            $query = $wpdb->prepare("
                SELECT 
                    UNIX_TIMESTAMP(price_date) as time,
                    close_price as value
                FROM {$wpdb->prefix}sffc_historical_prices
                WHERE symbol = %s
                AND price_date >= %s
                ORDER BY price_date ASC
            ", $symbol, $date_range['start']);
        }
        
        $data = $wpdb->get_results($query);
        
        // Format for TradingView
        $formatted = array_map(function($point) {
            return array(
                'time' => intval($point->time),
                'value' => floatval($point->value)
            );
        }, $data);
        
        // Cache
        $cache_key = 'sffc_chart_' . md5($symbol . '_' . $interval . '_' . $period . '_line');
        set_transient($cache_key, array(
            'status' => 'ready',
            'symbol' => $symbol,
            'data' => $formatted
        ), $this->get_cache_duration($interval));
    }
    
    /**
     * Prepare volume data
     */
    private function prepare_volume_data($symbol, $interval, $period) {
        global $wpdb;
        
        $date_range = $this->get_date_range($period);
        
        if ($this->is_intraday($interval)) {
            $minutes = $this->interval_to_minutes($interval);
            
            $query = $wpdb->prepare("
                SELECT 
                    UNIX_TIMESTAMP(
                        FROM_UNIXTIME(
                            FLOOR(UNIX_TIMESTAMP(timestamp) / %d) * %d
                        )
                    ) as time,
                    SUM(volume) as value,
                    CASE 
                        WHEN MAX(price) > MIN(price) THEN 'rgba(0, 150, 136, 0.8)'
                        ELSE 'rgba(255, 82, 82, 0.8)'
                    END as color
                FROM {$wpdb->prefix}sffc_intraday_prices
                WHERE symbol = %s
                AND timestamp >= %s
                GROUP BY FLOOR(UNIX_TIMESTAMP(timestamp) / %d)
                ORDER BY time ASC
            ", $minutes * 60, $minutes * 60, $symbol, $date_range['start'], $minutes * 60);
        } else {
            $query = $wpdb->prepare("
                SELECT 
                    UNIX_TIMESTAMP(price_date) as time,
                    volume as value,
                    CASE 
                        WHEN close_price > open_price THEN 'rgba(0, 150, 136, 0.8)'
                        ELSE 'rgba(255, 82, 82, 0.8)'
                    END as color
                FROM {$wpdb->prefix}sffc_historical_prices
                WHERE symbol = %s
                AND price_date >= %s
                ORDER BY price_date ASC
            ", $symbol, $date_range['start']);
        }
        
        $data = $wpdb->get_results($query);
        
        // Format for histogram
        $formatted = array_map(function($bar) {
            return array(
                'time' => intval($bar->time),
                'value' => floatval($bar->value),
                'color' => $bar->color
            );
        }, $data);
        
        // Cache
        $cache_key = 'sffc_chart_' . md5($symbol . '_' . $interval . '_' . $period . '_volume');
        set_transient($cache_key, array(
            'status' => 'ready',
            'symbol' => $symbol,
            'type' => 'histogram',
            'data' => $formatted
        ), $this->get_cache_duration($interval));
    }
    
    /**
     * Get chart indicators overlay
     */
    public function get_chart_indicators($symbol, $indicators = array('sma20', 'sma50', 'bollinger')) {
        $cache_key = 'sffc_chart_ind_' . md5($symbol . '_' . implode('_', $indicators));
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // Get indicator data from Technical Indicators class
        if (!class_exists('SFFC_Technical_Indicators')) {
            return array();
        }
        
        $tech_indicators = SFFC_Technical_Indicators::get_instance();
        $indicator_data = $tech_indicators->get_indicators($symbol);
        
        if ($indicator_data['status'] !== 'ready') {
            return array('status' => 'calculating');
        }
        
        // Prepare overlay data
        $overlays = array();
        
        foreach ($indicators as $indicator) {
            switch ($indicator) {
                case 'sma20':
                    $overlays['sma20'] = $this->prepare_ma_overlay($symbol, 20, 'SMA');
                    break;
                case 'sma50':
                    $overlays['sma50'] = $this->prepare_ma_overlay($symbol, 50, 'SMA');
                    break;
                case 'ema12':
                    $overlays['ema12'] = $this->prepare_ma_overlay($symbol, 12, 'EMA');
                    break;
                case 'bollinger':
                    $overlays['bollinger'] = $this->prepare_bollinger_overlay($symbol);
                    break;
            }
        }
        
        // Cache for 15 minutes
        set_transient($cache_key, $overlays, 900);
        
        return $overlays;
    }
    
    /**
     * Prepare moving average overlay
     */
    private function prepare_ma_overlay($symbol, $period, $type = 'SMA') {
        global $wpdb;
        
        // Get price data
        $prices = $wpdb->get_results($wpdb->prepare("
            SELECT 
                UNIX_TIMESTAMP(price_date) as time,
                close_price as price
            FROM {$wpdb->prefix}sffc_historical_prices
            WHERE symbol = %s
            AND price_date >= DATE_SUB(NOW(), INTERVAL %d DAY)
            ORDER BY price_date ASC
        ", $symbol, $period + 30));
        
        if (count($prices) < $period) {
            return array();
        }
        
        // Calculate MA values
        $ma_data = array();
        for ($i = $period - 1; $i < count($prices); $i++) {
            $sum = 0;
            for ($j = $i - $period + 1; $j <= $i; $j++) {
                $sum += $prices[$j]->price;
            }
            
            $ma_data[] = array(
                'time' => intval($prices[$i]->time),
                'value' => round($sum / $period, 2)
            );
        }
        
        return array(
            'type' => 'line',
            'name' => $type . $period,
            'color' => $this->get_indicator_color($type . $period),
            'data' => $ma_data
        );
    }
    
    /**
     * Prepare Bollinger Bands overlay
     */
    private function prepare_bollinger_overlay($symbol) {
        // Get from technical indicators
        $tech = SFFC_Technical_Indicators::get_instance();
        $indicators = $tech->get_indicators($symbol);
        
        if (!isset($indicators['indicators']['bollinger'])) {
            return array();
        }
        
        $bollinger = $indicators['indicators']['bollinger'];
        
        // Get recent price data for time axis
        global $wpdb;
        $times = $wpdb->get_col($wpdb->prepare("
            SELECT UNIX_TIMESTAMP(price_date) as time
            FROM {$wpdb->prefix}sffc_historical_prices
            WHERE symbol = %s
            AND price_date >= DATE_SUB(NOW(), INTERVAL 20 DAY)
            ORDER BY price_date DESC
            LIMIT 1
        ", $symbol));
        
        $current_time = $times[0] ?? time();
        
        return array(
            'type' => 'bands',
            'name' => 'Bollinger Bands',
            'data' => array(
                'upper' => array(
                    array('time' => $current_time, 'value' => $bollinger['upper'])
                ),
                'middle' => array(
                    array('time' => $current_time, 'value' => $bollinger['middle'])
                ),
                'lower' => array(
                    array('time' => $current_time, 'value' => $bollinger['lower'])
                )
            )
        );
    }
    
    /**
     * AJAX handler for chart data
     */
    public function ajax_get_chart_data() {
        check_ajax_referer('sffc_ajax_nonce', 'nonce');
        
        $symbol = sanitize_text_field($_POST['symbol'] ?? '');
        $interval = sanitize_text_field($_POST['interval'] ?? '1d');
        $period = sanitize_text_field($_POST['period'] ?? '1m');
        $type = sanitize_text_field($_POST['type'] ?? 'candlestick');
        
        if (!$symbol) {
            wp_send_json_error(array('message' => 'Symbol required'));
        }
        
        $data = $this->get_chart_data($symbol, $interval, $period, $type);
        
        wp_send_json_success($data);
    }
    
    /**
     * AJAX handler for indicators
     */
    public function ajax_get_chart_indicators() {
        check_ajax_referer('sffc_ajax_nonce', 'nonce');
        
        $symbol = sanitize_text_field($_POST['symbol'] ?? '');
        $indicators = array_map('sanitize_text_field', $_POST['indicators'] ?? array('sma20'));
        
        if (!$symbol) {
            wp_send_json_error(array('message' => 'Symbol required'));
        }
        
        $data = $this->get_chart_indicators($symbol, $indicators);
        
        wp_send_json_success($data);
    }
    
    /**
     * Format candlestick data for TradingView
     */
    private function format_candlestick_data($data) {
        return array_map(function($candle) {
            return array(
                'time' => intval($candle->time),
                'open' => floatval($candle->open),
                'high' => floatval($candle->high),
                'low' => floatval($candle->low),
                'close' => floatval($candle->close)
            );
        }, $data);
    }
    
    /**
     * Get date range for period
     */
    private function get_date_range($period) {
        $end = date('Y-m-d H:i:s');
        
        switch ($period) {
            case '1d':
                $start = date('Y-m-d H:i:s', strtotime('-1 day'));
                break;
            case '5d':
                $start = date('Y-m-d H:i:s', strtotime('-5 days'));
                break;
            case '1m':
                $start = date('Y-m-d H:i:s', strtotime('-1 month'));
                break;
            case '3m':
                $start = date('Y-m-d H:i:s', strtotime('-3 months'));
                break;
            case '6m':
                $start = date('Y-m-d H:i:s', strtotime('-6 months'));
                break;
            case '1y':
                $start = date('Y-m-d H:i:s', strtotime('-1 year'));
                break;
            case '5y':
                $start = date('Y-m-d H:i:s', strtotime('-5 years'));
                break;
            default:
                $start = date('Y-m-d H:i:s', strtotime('-1 month'));
        }
        
        return array('start' => $start, 'end' => $end);
    }
    
    /**
     * Convert interval to minutes
     */
    private function interval_to_minutes($interval) {
        switch ($interval) {
            case '1m': return 1;
            case '5m': return 5;
            case '15m': return 15;
            case '30m': return 30;
            case '1h': return 60;
            case '4h': return 240;
            default: return 60;
        }
    }
    
    /**
     * Check if interval is intraday
     */
    private function is_intraday($interval) {
        return in_array($interval, array('1m', '5m', '15m', '30m', '1h', '4h'));
    }
    
    /**
     * Get cache duration for interval
     */
    private function get_cache_duration($interval) {
        if ($this->is_intraday($interval)) {
            return self::CACHE_INTRADAY;
        } elseif ($interval === '1d') {
            return self::CACHE_DAILY;
        } else {
            return self::CACHE_WEEKLY;
        }
    }
    
    /**
     * Get GROUP BY clause for interval
     */
    private function get_group_by_clause($interval) {
        switch ($interval) {
            case '1w':
                return "GROUP BY YEARWEEK(price_date)";
            case '1M':
                return "GROUP BY YEAR(price_date), MONTH(price_date)";
            default:
                return "";
        }
    }
    
    /**
     * Get indicator color
     */
    private function get_indicator_color($indicator) {
        $colors = array(
            'SMA20' => '#2962FF',
            'SMA50' => '#FF6D00',
            'SMA200' => '#00C853',
            'EMA12' => '#AA00FF',
            'EMA26' => '#FFD600'
        );
        
        return $colors[$indicator] ?? '#757575';
    }
    
    /**
     * Get comparison chart data
     */
    public function get_comparison_data($symbols = array(), $period = '1m') {
        $cache_key = 'sffc_compare_' . md5(implode('_', $symbols) . '_' . $period);
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $comparison_data = array();
        $base_values = array();
        
        foreach ($symbols as $symbol) {
            $data = $this->get_chart_data($symbol, '1d', $period, 'line');
            
            if ($data['status'] === 'ready' && !empty($data['data'])) {
                // Normalize to percentage change from start
                $first_value = $data['data'][0]['value'];
                $normalized = array_map(function($point) use ($first_value) {
                    return array(
                        'time' => $point['time'],
                        'value' => (($point['value'] - $first_value) / $first_value) * 100
                    );
                }, $data['data']);
                
                $comparison_data[] = array(
                    'symbol' => $symbol,
                    'data' => $normalized,
                    'color' => $this->get_symbol_color($symbol)
                );
            }
        }
        
        // Cache for 15 minutes
        set_transient($cache_key, $comparison_data, 900);
        
        return $comparison_data;
    }
    
    /**
     * Get symbol color for comparison charts
     */
    private function get_symbol_color($symbol) {
        // Hash symbol to get consistent color
        $hash = md5($symbol);
        $hue = hexdec(substr($hash, 0, 3)) % 360;
        
        return "hsl($hue, 70%, 50%)";
    }
}

// Initialize
SFFC_Chart_Renderer::get_instance();
?>