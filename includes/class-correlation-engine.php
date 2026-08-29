<?php
/**
 * Performance-Optimized Correlation Engine
 * 
 * Uses caching, batch processing, and background jobs to prevent performance impact
 * 
 * @package SennaCareers
 * @subpackage MarketAnalysis
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Correlation_Engine {
    
    /**
     * Cache duration in seconds
     */
    const CACHE_DURATION = 3600; // 1 hour
    
    /**
     * Batch size for processing
     */
    const BATCH_SIZE = 10;
    
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
        // Register background processing hooks
        add_action('sffc_calculate_correlations_batch', array($this, 'process_correlation_batch'));
        
        // Register AJAX endpoints for async loading
        add_action('wp_ajax_sffc_get_correlations', array($this, 'ajax_get_correlations'));
        add_action('wp_ajax_nopriv_sffc_get_correlations', array($this, 'ajax_get_correlations'));
    }
    
    /**
     * Get correlation data with caching
     * This is the main public method - always returns cached data for performance
     */
    public function get_correlations($asset1, $asset2, $period = '30d', $force_refresh = false) {
        // Generate cache key
        $cache_key = 'sffc_corr_' . md5($asset1 . '_' . $asset2 . '_' . $period);
        
        // Try to get from transient first (fastest)
        if (!$force_refresh) {
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }
        
        // If not in cache, schedule background calculation and return placeholder
        $this->schedule_correlation_calculation($asset1, $asset2, $period);
        
        // Return placeholder data immediately (non-blocking)
        return array(
            'status' => 'calculating',
            'message' => 'Correlation data is being calculated in the background',
            'correlation' => null,
            'cached' => false
        );
    }
    
    /**
     * Schedule background correlation calculation
     * Uses WP Cron to prevent blocking
     */
    private function schedule_correlation_calculation($asset1, $asset2, $period) {
        // Use WP's background processing
        wp_schedule_single_event(
            time() + 1, // Run 1 second from now
            'sffc_calculate_correlations_batch',
            array($asset1, $asset2, $period)
        );
    }
    
    /**
     * Process correlation batch in background
     * This runs via WP Cron, not during page load
     */
    public function process_correlation_batch($asset1 = null, $asset2 = null, $period = '30d') {
        // Set time limit for background process only
        @set_time_limit(30);
        
        if ($asset1 && $asset2) {
            // Calculate single correlation
            $correlation = $this->calculate_correlation_internal($asset1, $asset2, $period);
            
            // Cache the result
            $cache_key = 'sffc_corr_' . md5($asset1 . '_' . $asset2 . '_' . $period);
            set_transient($cache_key, $correlation, self::CACHE_DURATION);
            
            // Also store in database for persistence
            $this->store_correlation_db($asset1, $asset2, $period, $correlation);
        } else {
            // Process batch of correlations
            $this->process_correlation_matrix_batch();
        }
    }
    
    /**
     * Calculate correlation (internal method - resource intensive)
     * Only called in background processes
     */
    private function calculate_correlation_internal($asset1, $asset2, $period) {
        global $wpdb;
        
        // Get the period in days
        $days = $this->parse_period($period);
        
        // Use database aggregation for efficiency
        $query = $wpdb->prepare("
            SELECT 
                COALESCE(
                    (COUNT(*) * SUM(a1.price * a2.price) - SUM(a1.price) * SUM(a2.price)) /
                    SQRT(
                        (COUNT(*) * SUM(a1.price * a1.price) - SUM(a1.price) * SUM(a1.price)) *
                        (COUNT(*) * SUM(a2.price * a2.price) - SUM(a2.price) * SUM(a2.price))
                    ), 0
                ) as correlation
            FROM 
                (SELECT DATE(timestamp) as date, AVG(price) as price 
                 FROM {$wpdb->prefix}sffc_intraday_prices 
                 WHERE symbol = %s 
                 AND timestamp >= DATE_SUB(NOW(), INTERVAL %d DAY)
                 GROUP BY DATE(timestamp)) a1
            JOIN 
                (SELECT DATE(timestamp) as date, AVG(price) as price 
                 FROM {$wpdb->prefix}sffc_intraday_prices 
                 WHERE symbol = %s 
                 AND timestamp >= DATE_SUB(NOW(), INTERVAL %d DAY)
                 GROUP BY DATE(timestamp)) a2
            ON a1.date = a2.date
        ", $asset1, $days, $asset2, $days);
        
        $correlation = $wpdb->get_var($query);
        
        return array(
            'correlation' => round($correlation, 4),
            'period' => $period,
            'asset1' => $asset1,
            'asset2' => $asset2,
            'calculated_at' => current_time('mysql'),
            'data_points' => $days,
            'cached' => true
        );
    }
    
    /**
     * Process correlation matrix in batches
     * Prevents memory issues with large datasets
     */
    private function process_correlation_matrix_batch() {
        // Get list of tracked assets
        $assets = $this->get_tracked_assets_batch();
        
        if (empty($assets)) {
            return;
        }
        
        $processed = 0;
        
        // Process only BATCH_SIZE correlations at a time
        for ($i = 0; $i < count($assets) && $processed < self::BATCH_SIZE; $i++) {
            for ($j = $i + 1; $j < count($assets) && $processed < self::BATCH_SIZE; $j++) {
                $correlation = $this->calculate_correlation_internal(
                    $assets[$i],
                    $assets[$j],
                    '30d'
                );
                
                // Store in cache
                $cache_key = 'sffc_corr_' . md5($assets[$i] . '_' . $assets[$j] . '_30d');
                set_transient($cache_key, $correlation, self::CACHE_DURATION);
                
                $processed++;
            }
        }
        
        // Schedule next batch if needed
        if ($processed >= self::BATCH_SIZE) {
            wp_schedule_single_event(time() + 10, 'sffc_calculate_correlations_batch');
        }
    }
    
    /**
     * Get tracked assets in batches to prevent memory issues
     */
    private function get_tracked_assets_batch($limit = 20) {
        global $wpdb;
        
        // Get only the most active assets
        $assets = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT symbol 
            FROM {$wpdb->prefix}sffc_market_cache 
            WHERE last_updated >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY volume DESC
            LIMIT %d
        ", $limit));
        
        return $assets;
    }
    
    /**
     * Store correlation in database for persistence
     */
    private function store_correlation_db($asset1, $asset2, $period, $data) {
        global $wpdb;
        
        require_once SFFC_PLUGIN_DIR . 'includes/class-european-database.php';
        $eu_db = SFFC_European_Database::get_instance();
        $table = $eu_db->get_table('market_correlations');
        
        if (!$table) {
            return false;
        }
        
        // Use INSERT ... ON DUPLICATE KEY UPDATE for efficiency
        $wpdb->query($wpdb->prepare("
            INSERT INTO {$table} 
            (asset1_symbol, asset1_type, asset2_symbol, asset2_type, 
             correlation_period, correlation_coefficient, calculation_date, data_points)
            VALUES (%s, %s, %s, %s, %s, %f, %s, %d)
            ON DUPLICATE KEY UPDATE
            correlation_coefficient = VALUES(correlation_coefficient),
            calculation_date = VALUES(calculation_date)
        ",
            $asset1, 'index',
            $asset2, 'index',
            $period,
            $data['correlation'],
            $data['calculated_at'],
            $data['data_points']
        ));
    }
    
    /**
     * AJAX handler for async correlation requests
     */
    public function ajax_get_correlations() {
        // Verify nonce for security
        check_ajax_referer('sffc_ajax_nonce', 'nonce');
        
        $asset1 = sanitize_text_field($_POST['asset1'] ?? '');
        $asset2 = sanitize_text_field($_POST['asset2'] ?? '');
        $period = sanitize_text_field($_POST['period'] ?? '30d');
        
        // Get correlation (from cache or schedule calculation)
        $correlation = $this->get_correlations($asset1, $asset2, $period);
        
        wp_send_json_success($correlation);
    }
    
    /**
     * Get correlation matrix (cached version only)
     * Returns immediately with whatever is in cache
     */
    public function get_correlation_matrix($assets = array(), $period = '30d') {
        if (empty($assets)) {
            $assets = $this->get_tracked_assets_batch(10); // Limit to top 10
        }
        
        $matrix = array();
        
        foreach ($assets as $asset1) {
            $matrix[$asset1] = array();
            foreach ($assets as $asset2) {
                if ($asset1 === $asset2) {
                    $matrix[$asset1][$asset2] = 1.0;
                } else {
                    // Only get from cache, don't calculate
                    $cache_key = 'sffc_corr_' . md5($asset1 . '_' . $asset2 . '_' . $period);
                    $cached = get_transient($cache_key);
                    
                    if ($cached !== false && isset($cached['correlation'])) {
                        $matrix[$asset1][$asset2] = $cached['correlation'];
                    } else {
                        $matrix[$asset1][$asset2] = null; // Will be calculated in background
                    }
                }
            }
        }
        
        return $matrix;
    }
    
    /**
     * Parse period string to days
     */
    private function parse_period($period) {
        $periods = array(
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            '1y' => 365,
            '3y' => 1095,
            '5y' => 1825
        );
        
        return isset($periods[$period]) ? $periods[$period] : 30;
    }
    
    /**
     * Get top correlations from cache
     * Super fast - only reads from cache
     */
    public function get_top_correlations($limit = 10) {
        global $wpdb;
        
        require_once SFFC_PLUGIN_DIR . 'includes/class-european-database.php';
        $eu_db = SFFC_European_Database::get_instance();
        $table = $eu_db->get_table('market_correlations');
        
        if (!$table) {
            return array();
        }
        
        // Get from database cache
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT asset1_symbol, asset2_symbol, correlation_coefficient, calculation_date
            FROM {$table}
            WHERE correlation_period = '30d'
            AND calculation_date >= DATE_SUB(NOW(), INTERVAL 1 DAY)
            ORDER BY ABS(correlation_coefficient) DESC
            LIMIT %d
        ", $limit));
        
        return $results;
    }
    
    /**
     * Cleanup old correlation data
     * Runs in background via WP Cron
     */
    public function cleanup_old_correlations() {
        global $wpdb;
        
        require_once SFFC_PLUGIN_DIR . 'includes/class-european-database.php';
        $eu_db = SFFC_European_Database::get_instance();
        $table = $eu_db->get_table('market_correlations');
        
        if (!$table) {
            return;
        }
        
        // Delete correlations older than 30 days
        $wpdb->query("
            DELETE FROM {$table}
            WHERE calculation_date < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
    }
}

// Initialize the engine
SFFC_Correlation_Engine::get_instance();
?>