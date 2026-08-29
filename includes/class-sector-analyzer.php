<?php
/**
 * Lightweight Sector Analyzer
 * 
 * Uses cached data and lazy loading for optimal performance
 * All heavy calculations done in background
 * 
 * @package SennaCareers
 * @subpackage MarketAnalysis
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Sector_Analyzer {
    
    /**
     * Cache duration
     */
    const CACHE_DURATION = 1800; // 30 minutes
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Sectors to track
     */
    private $sectors = array(
        'technology' => 'Technology',
        'financials' => 'Financials',
        'healthcare' => 'Healthcare',
        'energy' => 'Energy',
        'consumer' => 'Consumer',
        'industrials' => 'Industrials',
        'materials' => 'Materials',
        'utilities' => 'Utilities',
        'realestate' => 'Real Estate',
        'telecom' => 'Telecommunications'
    );
    
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
        // Register AJAX handlers for async loading
        add_action('wp_ajax_sffc_get_sector_data', array($this, 'ajax_get_sector_data'));
        add_action('wp_ajax_nopriv_sffc_get_sector_data', array($this, 'ajax_get_sector_data'));
        
        // Register background processing
        add_action('sffc_analyze_sectors', array($this, 'analyze_sectors_background'));
    }
    
    /**
     * Get sector performance (cached only - never calculates on request)
     */
    public function get_sector_performance($period = 'today') {
        $cache_key = 'sffc_sector_perf_' . $period;
        
        // Always return from cache
        $cached = get_transient($cache_key);
        
        if ($cached === false) {
            // Schedule background calculation
            wp_schedule_single_event(time() + 1, 'sffc_analyze_sectors', array($period));
            
            // Return placeholder
            return array(
                'status' => 'loading',
                'message' => 'Sector data is being calculated',
                'data' => array()
            );
        }
        
        return $cached;
    }
    
    /**
     * Get sector rotation signals (cached)
     */
    public function get_rotation_signals() {
        $cache_key = 'sffc_rotation_signals';
        
        $cached = get_transient($cache_key);
        
        if ($cached === false) {
            // Return empty array instead of calculating
            return array();
        }
        
        return $cached;
    }
    
    /**
     * Analyze sectors in background
     */
    public function analyze_sectors_background($period = 'today') {
        global $wpdb;
        
        // Get time range
        $time_range = $this->get_time_range($period);
        
        // Use efficient database query with aggregation
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT 
                sector,
                COUNT(*) as count,
                AVG(change_percent) as avg_change,
                MAX(change_percent) as max_gainer,
                MIN(change_percent) as max_loser,
                SUM(volume * price) as total_value,
                AVG(volatility_30d) as avg_volatility
            FROM {$wpdb->prefix}sffc_market_cache
            WHERE last_updated >= %s
            AND sector IS NOT NULL
            GROUP BY sector
            ORDER BY avg_change DESC
        ", $time_range));
        
        // Calculate relative strength
        $performance = array();
        $total_avg = 0;
        
        foreach ($results as $sector) {
            $performance[$sector->sector] = array(
                'name' => $this->sectors[$sector->sector] ?? $sector->sector,
                'change' => round($sector->avg_change, 2),
                'count' => $sector->count,
                'volume' => $this->format_volume($sector->total_value),
                'volatility' => round($sector->avg_volatility, 2),
                'strength' => 0 // Will calculate below
            );
            
            $total_avg += $sector->avg_change;
        }
        
        // Calculate relative strength
        if (count($results) > 0) {
            $market_avg = $total_avg / count($results);
            
            foreach ($performance as &$sector) {
                $sector['strength'] = round($sector['change'] - $market_avg, 2);
                $sector['signal'] = $this->determine_signal($sector);
            }
        }
        
        // Cache results
        set_transient('sffc_sector_perf_' . $period, $performance, self::CACHE_DURATION);
        
        // Also calculate rotation signals
        $this->calculate_rotation_signals($performance);
        
        return $performance;
    }
    
    /**
     * Calculate rotation signals
     */
    private function calculate_rotation_signals($current_performance) {
        // Get previous performance
        $yesterday = get_transient('sffc_sector_perf_yesterday');
        
        if (!$yesterday) {
            return;
        }
        
        $signals = array();
        
        foreach ($current_performance as $sector => $data) {
            if (isset($yesterday[$sector])) {
                $momentum = $data['strength'] - $yesterday[$sector]['strength'];
                
                if ($momentum > 2) {
                    $signals[] = array(
                        'sector' => $sector,
                        'signal' => 'rotating_in',
                        'strength' => $momentum,
                        'message' => "Money rotating into {$data['name']}"
                    );
                } elseif ($momentum < -2) {
                    $signals[] = array(
                        'sector' => $sector,
                        'signal' => 'rotating_out',
                        'strength' => $momentum,
                        'message' => "Money rotating out of {$data['name']}"
                    );
                }
            }
        }
        
        // Sort by strength
        usort($signals, function($a, $b) {
            return abs($b['strength']) - abs($a['strength']);
        });
        
        // Cache top 5 signals
        set_transient('sffc_rotation_signals', array_slice($signals, 0, 5), self::CACHE_DURATION);
    }
    
    /**
     * Get sector leaders and laggards (cached)
     */
    public function get_leaders_laggards($limit = 3) {
        $performance = $this->get_sector_performance();
        
        if ($performance['status'] === 'loading' || empty($performance)) {
            return array('leaders' => array(), 'laggards' => array());
        }
        
        // Sort by performance
        uasort($performance, function($a, $b) {
            return $b['change'] <=> $a['change'];
        });
        
        return array(
            'leaders' => array_slice($performance, 0, $limit, true),
            'laggards' => array_slice($performance, -$limit, $limit, true)
        );
    }
    
    /**
     * Get sector momentum (cached)
     */
    public function get_sector_momentum($sector, $periods = array('1d', '5d', '1m')) {
        $cache_key = 'sffc_momentum_' . $sector;
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // Return placeholder - will be calculated in background
        return array(
            'sector' => $sector,
            'momentum' => array(),
            'trend' => 'calculating'
        );
    }
    
    /**
     * Calculate money flow (lightweight version)
     */
    public function get_money_flow() {
        global $wpdb;
        
        // Use cached result if available
        $cache_key = 'sffc_money_flow';
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // Quick calculation using latest data only
        $flows = $wpdb->get_results("
            SELECT 
                sector,
                SUM(CASE WHEN change_percent > 0 THEN volume * price ELSE 0 END) as inflow,
                SUM(CASE WHEN change_percent < 0 THEN volume * price ELSE 0 END) as outflow
            FROM {$wpdb->prefix}sffc_market_cache
            WHERE last_updated >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            AND sector IS NOT NULL
            GROUP BY sector
            LIMIT 10
        ");
        
        $result = array();
        foreach ($flows as $flow) {
            $net = $flow->inflow - abs($flow->outflow);
            $result[$flow->sector] = array(
                'inflow' => $this->format_volume($flow->inflow),
                'outflow' => $this->format_volume(abs($flow->outflow)),
                'net' => $this->format_volume($net),
                'sentiment' => $net > 0 ? 'bullish' : 'bearish'
            );
        }
        
        // Cache for 15 minutes
        set_transient($cache_key, $result, 900);
        
        return $result;
    }
    
    /**
     * AJAX handler for sector data
     */
    public function ajax_get_sector_data() {
        check_ajax_referer('sffc_ajax_nonce', 'nonce');
        
        $type = sanitize_text_field($_POST['type'] ?? 'performance');
        $period = sanitize_text_field($_POST['period'] ?? 'today');
        
        $data = array();
        
        switch ($type) {
            case 'performance':
                $data = $this->get_sector_performance($period);
                break;
            case 'rotation':
                $data = $this->get_rotation_signals();
                break;
            case 'leaders':
                $data = $this->get_leaders_laggards();
                break;
            case 'flow':
                $data = $this->get_money_flow();
                break;
        }
        
        wp_send_json_success($data);
    }
    
    /**
     * Determine signal based on performance
     */
    private function determine_signal($sector_data) {
        if ($sector_data['strength'] > 3) {
            return 'strong_buy';
        } elseif ($sector_data['strength'] > 1) {
            return 'buy';
        } elseif ($sector_data['strength'] < -3) {
            return 'strong_sell';
        } elseif ($sector_data['strength'] < -1) {
            return 'sell';
        }
        
        return 'neutral';
    }
    
    /**
     * Get time range for period
     */
    private function get_time_range($period) {
        switch ($period) {
            case 'today':
                return date('Y-m-d 00:00:00');
            case 'week':
                return date('Y-m-d H:i:s', strtotime('-7 days'));
            case 'month':
                return date('Y-m-d H:i:s', strtotime('-30 days'));
            default:
                return date('Y-m-d 00:00:00');
        }
    }
    
    /**
     * Format volume for display
     */
    private function format_volume($volume) {
        if ($volume > 1000000000) {
            return round($volume / 1000000000, 2) . 'B';
        } elseif ($volume > 1000000) {
            return round($volume / 1000000, 2) . 'M';
        } elseif ($volume > 1000) {
            return round($volume / 1000, 2) . 'K';
        }
        
        return $volume;
    }
}

// Initialize
SFFC_Sector_Analyzer::get_instance();
?>