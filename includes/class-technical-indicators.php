<?php
/**
 * Cached Technical Indicators
 * 
 * Pre-calculates all indicators in background
 * Frontend only reads from cache - zero calculation on page load
 * 
 * @package SennaCareers
 * @subpackage MarketAnalysis
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Technical_Indicators {
    
    /**
     * Cache duration
     */
    const CACHE_SHORT = 900;    // 15 minutes for intraday
    const CACHE_MEDIUM = 3600;  // 1 hour for daily
    const CACHE_LONG = 86400;   // 24 hours for weekly
    
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
        // Register background calculator
        add_action('sffc_calculate_indicators', array($this, 'calculate_all_indicators'));
        
        // AJAX endpoints for lazy loading
        add_action('wp_ajax_sffc_get_indicators', array($this, 'ajax_get_indicators'));
        add_action('wp_ajax_nopriv_sffc_get_indicators', array($this, 'ajax_get_indicators'));
        
        // Schedule indicator calculations
        if (!wp_next_scheduled('sffc_calculate_indicators')) {
            wp_schedule_event(time(), 'hourly', 'sffc_calculate_indicators');
        }
    }
    
    /**
     * Get indicators (cached only - never calculates)
     */
    public function get_indicators($symbol, $type = 'all') {
        $cache_key = 'sffc_indicators_' . md5($symbol . '_' . $type);
        $cached = get_transient($cache_key);
        
        if ($cached === false) {
            // Schedule calculation if not available
            wp_schedule_single_event(time() + 1, 'sffc_calculate_indicators', array($symbol));
            
            // Return empty data
            return array(
                'status' => 'calculating',
                'symbol' => $symbol,
                'indicators' => array()
            );
        }
        
        return $cached;
    }
    
    /**
     * Calculate all indicators in background
     */
    public function calculate_all_indicators($specific_symbol = null) {
        // This runs via cron - not during page loads
        set_time_limit(60);
        
        global $wpdb;
        
        // Get symbols to calculate
        if ($specific_symbol) {
            $symbols = array($specific_symbol);
        } else {
            // Get top 10 most active symbols
            $symbols = $wpdb->get_col("
                SELECT DISTINCT symbol 
                FROM {$wpdb->prefix}sffc_market_cache
                WHERE last_updated >= DATE_SUB(NOW(), INTERVAL 1 DAY)
                ORDER BY volume DESC
                LIMIT 10
            ");
        }
        
        foreach ($symbols as $symbol) {
            $this->calculate_symbol_indicators($symbol);
            
            // Small delay between symbols
            usleep(100000); // 0.1 seconds
        }
    }
    
    /**
     * Calculate indicators for a single symbol
     */
    private function calculate_symbol_indicators($symbol) {
        global $wpdb;
        
        // Get price data (last 200 points for accuracy)
        $prices = $wpdb->get_results($wpdb->prepare("
            SELECT DATE(timestamp) as date, 
                   AVG(price) as close,
                   MAX(price) as high,
                   MIN(price) as low,
                   SUM(volume) as volume
            FROM {$wpdb->prefix}sffc_intraday_prices
            WHERE symbol = %s
            AND timestamp >= DATE_SUB(NOW(), INTERVAL 200 DAY)
            GROUP BY DATE(timestamp)
            ORDER BY date DESC
            LIMIT 200
        ", $symbol));
        
        if (count($prices) < 20) {
            return; // Not enough data
        }
        
        // Reverse for chronological order
        $prices = array_reverse($prices);
        
        // Extract price array
        $close_prices = array_map(function($p) { return $p->close; }, $prices);
        
        // Calculate indicators
        $indicators = array(
            'sma_20' => $this->calculate_sma($close_prices, 20),
            'sma_50' => $this->calculate_sma($close_prices, 50),
            'sma_200' => $this->calculate_sma($close_prices, 200),
            'ema_12' => $this->calculate_ema($close_prices, 12),
            'ema_26' => $this->calculate_ema($close_prices, 26),
            'rsi' => $this->calculate_rsi($close_prices, 14),
            'macd' => $this->calculate_macd($close_prices),
            'bollinger' => $this->calculate_bollinger($close_prices, 20),
            'volume_avg' => $this->calculate_volume_average($prices),
            'support_resistance' => $this->calculate_support_resistance($prices),
            'trend' => $this->determine_trend($close_prices),
            'signal' => $this->generate_signal($close_prices)
        );
        
        // Cache results
        $cache_key = 'sffc_indicators_' . md5($symbol . '_all');
        set_transient($cache_key, array(
            'status' => 'ready',
            'symbol' => $symbol,
            'indicators' => $indicators,
            'calculated_at' => current_time('mysql'),
            'latest_price' => end($close_prices)
        ), self::CACHE_MEDIUM);
        
        // Also cache individual indicators for faster access
        foreach ($indicators as $key => $value) {
            $individual_key = 'sffc_ind_' . $symbol . '_' . $key;
            set_transient($individual_key, $value, self::CACHE_MEDIUM);
        }
    }
    
    /**
     * Calculate Simple Moving Average
     */
    private function calculate_sma($prices, $period) {
        if (count($prices) < $period) {
            return null;
        }
        
        $recent = array_slice($prices, -$period);
        return round(array_sum($recent) / $period, 2);
    }
    
    /**
     * Calculate Exponential Moving Average
     */
    private function calculate_ema($prices, $period) {
        if (count($prices) < $period) {
            return null;
        }
        
        $multiplier = 2 / ($period + 1);
        $ema = array_sum(array_slice($prices, 0, $period)) / $period;
        
        for ($i = $period; $i < count($prices); $i++) {
            $ema = ($prices[$i] - $ema) * $multiplier + $ema;
        }
        
        return round($ema, 2);
    }
    
    /**
     * Calculate RSI
     */
    private function calculate_rsi($prices, $period = 14) {
        if (count($prices) < $period + 1) {
            return null;
        }
        
        $gains = array();
        $losses = array();
        
        for ($i = 1; $i < count($prices); $i++) {
            $change = $prices[$i] - $prices[$i - 1];
            if ($change > 0) {
                $gains[] = $change;
                $losses[] = 0;
            } else {
                $gains[] = 0;
                $losses[] = abs($change);
            }
        }
        
        $avg_gain = array_sum(array_slice($gains, -$period)) / $period;
        $avg_loss = array_sum(array_slice($losses, -$period)) / $period;
        
        if ($avg_loss == 0) {
            return 100;
        }
        
        $rs = $avg_gain / $avg_loss;
        $rsi = 100 - (100 / (1 + $rs));
        
        return round($rsi, 2);
    }
    
    /**
     * Calculate MACD
     */
    private function calculate_macd($prices) {
        $ema12 = $this->calculate_ema($prices, 12);
        $ema26 = $this->calculate_ema($prices, 26);
        
        if (!$ema12 || !$ema26) {
            return null;
        }
        
        $macd_line = $ema12 - $ema26;
        
        return array(
            'macd' => round($macd_line, 2),
            'signal' => round($macd_line * 0.9, 2), // Simplified signal line
            'histogram' => round($macd_line * 0.1, 2)
        );
    }
    
    /**
     * Calculate Bollinger Bands
     */
    private function calculate_bollinger($prices, $period = 20) {
        if (count($prices) < $period) {
            return null;
        }
        
        $sma = $this->calculate_sma($prices, $period);
        $recent = array_slice($prices, -$period);
        
        // Calculate standard deviation
        $variance = 0;
        foreach ($recent as $price) {
            $variance += pow($price - $sma, 2);
        }
        $std_dev = sqrt($variance / $period);
        
        return array(
            'upper' => round($sma + (2 * $std_dev), 2),
            'middle' => $sma,
            'lower' => round($sma - (2 * $std_dev), 2),
            'width' => round(4 * $std_dev, 2)
        );
    }
    
    /**
     * Calculate volume average
     */
    private function calculate_volume_average($price_data) {
        $volumes = array_map(function($p) { return $p->volume; }, $price_data);
        $recent_20 = array_slice($volumes, -20);
        
        return array(
            'current' => end($volumes),
            'avg_20' => round(array_sum($recent_20) / count($recent_20)),
            'ratio' => round(end($volumes) / (array_sum($recent_20) / count($recent_20)), 2)
        );
    }
    
    /**
     * Calculate support and resistance
     */
    private function calculate_support_resistance($price_data) {
        $highs = array_map(function($p) { return $p->high; }, $price_data);
        $lows = array_map(function($p) { return $p->low; }, $price_data);
        
        // Simple method: use recent highs/lows
        $recent_highs = array_slice($highs, -20);
        $recent_lows = array_slice($lows, -20);
        
        return array(
            'resistance' => round(max($recent_highs), 2),
            'support' => round(min($recent_lows), 2),
            'pivot' => round((max($recent_highs) + min($recent_lows) + end($price_data)->close) / 3, 2)
        );
    }
    
    /**
     * Determine trend
     */
    private function determine_trend($prices) {
        $sma20 = $this->calculate_sma($prices, 20);
        $sma50 = $this->calculate_sma($prices, 50);
        $current = end($prices);
        
        if ($current > $sma20 && $sma20 > $sma50) {
            return 'bullish';
        } elseif ($current < $sma20 && $sma20 < $sma50) {
            return 'bearish';
        }
        
        return 'neutral';
    }
    
    /**
     * Generate trading signal
     */
    private function generate_signal($prices) {
        $rsi = $this->calculate_rsi($prices);
        $macd = $this->calculate_macd($prices);
        $trend = $this->determine_trend($prices);
        
        $score = 0;
        
        // RSI signals
        if ($rsi < 30) $score += 2;  // Oversold
        elseif ($rsi > 70) $score -= 2;  // Overbought
        
        // MACD signals
        if ($macd && $macd['macd'] > $macd['signal']) $score += 1;
        elseif ($macd && $macd['macd'] < $macd['signal']) $score -= 1;
        
        // Trend alignment
        if ($trend === 'bullish') $score += 1;
        elseif ($trend === 'bearish') $score -= 1;
        
        if ($score >= 2) return 'strong_buy';
        elseif ($score >= 1) return 'buy';
        elseif ($score <= -2) return 'strong_sell';
        elseif ($score <= -1) return 'sell';
        
        return 'hold';
    }
    
    /**
     * AJAX handler
     */
    public function ajax_get_indicators() {
        check_ajax_referer('sffc_ajax_nonce', 'nonce');
        
        $symbol = sanitize_text_field($_POST['symbol'] ?? '');
        $type = sanitize_text_field($_POST['type'] ?? 'all');
        
        $indicators = $this->get_indicators($symbol, $type);
        
        wp_send_json_success($indicators);
    }
    
    /**
     * Get quick summary (ultra-fast)
     */
    public function get_quick_summary($symbol) {
        // Use individual cached indicators for speed
        $rsi = get_transient('sffc_ind_' . $symbol . '_rsi');
        $trend = get_transient('sffc_ind_' . $symbol . '_trend');
        $signal = get_transient('sffc_ind_' . $symbol . '_signal');
        
        return array(
            'rsi' => $rsi ?: 'N/A',
            'trend' => $trend ?: 'calculating',
            'signal' => $signal ?: 'hold'
        );
    }
}

// Initialize
SFFC_Technical_Indicators::get_instance();
?>