<?php
/**
 * Performance Monitoring System
 * Tracks and reports on plugin performance metrics
 * 
 * @package SennaCareers
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Performance_Monitor {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Performance data
     */
    private $metrics = array();
    private $start_times = array();
    
    /**
     * Metric types
     */
    const METRIC_API_CALL = 'api_call';
    const METRIC_DB_QUERY = 'db_query';
    const METRIC_VISUAL_RENDER = 'visual_render';
    const METRIC_CACHE_HIT = 'cache_hit';
    const METRIC_RESPONSE_TIME = 'response_time';
    const METRIC_MEMORY_USAGE = 'memory_usage';
    
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
        $this->init_monitoring();
    }
    
    /**
     * Initialize monitoring hooks
     */
    private function init_monitoring() {
        // Track AJAX response times
        add_action('wp_ajax_sffc_start_conversation', array($this, 'start_request_timer'), 1);
        add_action('wp_ajax_sffc_send_message', array($this, 'start_request_timer'), 1);
        add_action('wp_ajax_sffc_fetch_visual_card', array($this, 'start_request_timer'), 1);
        
        // Track completion times
        add_action('wp_ajax_sffc_start_conversation', array($this, 'end_request_timer'), 999);
        add_action('wp_ajax_sffc_send_message', array($this, 'end_request_timer'), 999);
        add_action('wp_ajax_sffc_fetch_visual_card', array($this, 'end_request_timer'), 999);
        
        // Monitor database queries
        add_filter('query', array($this, 'monitor_db_query'));
        
        // Add performance dashboard
        add_action('admin_menu', array($this, 'add_performance_menu'));
        
        // AJAX endpoint for metrics
        add_action('wp_ajax_sffc_get_performance_metrics', array($this, 'ajax_get_metrics'));
        
        // Schedule metric cleanup
        if (!wp_next_scheduled('sffc_cleanup_metrics')) {
            wp_schedule_event(time(), 'daily', 'sffc_cleanup_metrics');
        }
        add_action('sffc_cleanup_metrics', array($this, 'cleanup_old_metrics'));
    }
    
    /**
     * Start timing a request
     */
    public function start_request_timer() {
        $action = isset($_POST['action']) ? $_POST['action'] : 'unknown';
        $this->start_timer($action);
    }
    
    /**
     * End timing a request
     */
    public function end_request_timer() {
        $action = isset($_POST['action']) ? $_POST['action'] : 'unknown';
        $duration = $this->end_timer($action);
        
        if ($duration !== false) {
            $this->record_metric(self::METRIC_RESPONSE_TIME, $duration, array(
                'action' => $action,
                'user_id' => get_current_user_id(),
                'timestamp' => current_time('mysql')
            ));
        }
    }
    
    /**
     * Start a timer
     * 
     * @param string $key Timer key
     */
    public function start_timer($key) {
        $this->start_times[$key] = microtime(true);
    }
    
    /**
     * End a timer and get duration
     * 
     * @param string $key Timer key
     * @return float|false Duration in milliseconds or false
     */
    public function end_timer($key) {
        if (!isset($this->start_times[$key])) {
            return false;
        }
        
        $duration = (microtime(true) - $this->start_times[$key]) * 1000; // Convert to ms
        unset($this->start_times[$key]);
        
        return $duration;
    }
    
    /**
     * Record a metric
     * 
     * @param string $type Metric type
     * @param mixed $value Metric value
     * @param array $context Additional context
     */
    public function record_metric($type, $value, $context = array()) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sffc_performance_metrics';
        
        $data = array(
            'metric_type' => $type,
            'metric_value' => is_numeric($value) ? $value : 0,
            'context' => json_encode($context),
            'created_at' => current_time('mysql')
        );
        
        $wpdb->insert($table_name, $data);
        
        // Also store in memory for current request
        if (!isset($this->metrics[$type])) {
            $this->metrics[$type] = array();
        }
        $this->metrics[$type][] = $value;
        
        // Check for performance issues
        $this->check_performance_thresholds($type, $value);
    }
    
    /**
     * Monitor database queries
     * 
     * @param string $query SQL query
     * @return string Unchanged query
     */
    public function monitor_db_query($query) {
        // DISABLED - This implementation is broken and causes false performance warnings
        // The register_shutdown_function measures time until script end, not query duration
        // Proper implementation would require hooking into WordPress query execution
        return $query;
    }
    
    /**
     * Check performance thresholds and alert if exceeded
     * 
     * @param string $type Metric type
     * @param mixed $value Metric value
     */
    private function check_performance_thresholds($type, $value) {
        $thresholds = array(
            self::METRIC_RESPONSE_TIME => 3000,    // 3 seconds
            self::METRIC_DB_QUERY => 500,          // 500ms
            self::METRIC_API_CALL => 5000,         // 5 seconds
            self::METRIC_MEMORY_USAGE => 128       // 128MB
        );
        
        if (isset($thresholds[$type]) && $value > $thresholds[$type]) {
            // Log performance issue
            if (class_exists('SFFC_Error_Handler')) {
                $error_handler = SFFC_Error_Handler::get_instance();
                $error_handler->handle_error(
                    'performance_warning',
                    "Performance threshold exceeded for {$type}: {$value}",
                    array('type' => $type, 'value' => $value, 'threshold' => $thresholds[$type])
                );
            }
            
            // Trigger action for other systems to respond
            do_action('sffc_performance_threshold_exceeded', $type, $value);
        }
    }
    
    /**
     * Get performance statistics
     * 
     * @param string $type Metric type (optional)
     * @param int $hours Hours to look back (default 24)
     * @return array Performance statistics
     */
    public function get_stats($type = null, $hours = 24) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sffc_performance_metrics';
        $since = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
        
        $where = "created_at >= %s";
        $params = array($since);
        
        if ($type) {
            $where .= " AND metric_type = %s";
            $params[] = $type;
        }
        
        $query = $wpdb->prepare(
            "SELECT 
                metric_type,
                COUNT(*) as count,
                AVG(metric_value) as avg_value,
                MIN(metric_value) as min_value,
                MAX(metric_value) as max_value,
                STDDEV(metric_value) as std_dev
             FROM {$table_name}
             WHERE {$where}
             GROUP BY metric_type",
            $params
        );
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        // Calculate percentiles for response times
        if ($type === self::METRIC_RESPONSE_TIME || !$type) {
            foreach ($results as &$result) {
                if ($result['metric_type'] === self::METRIC_RESPONSE_TIME) {
                    $result['p50'] = $this->get_percentile($result['metric_type'], 50, $hours);
                    $result['p95'] = $this->get_percentile($result['metric_type'], 95, $hours);
                    $result['p99'] = $this->get_percentile($result['metric_type'], 99, $hours);
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Get percentile value for a metric
     * 
     * @param string $type Metric type
     * @param int $percentile Percentile (0-100)
     * @param int $hours Hours to look back
     * @return float Percentile value
     */
    private function get_percentile($type, $percentile, $hours = 24) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sffc_performance_metrics';
        $since = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
        
        // Get all values for percentile calculation
        $values = $wpdb->get_col($wpdb->prepare(
            "SELECT metric_value 
             FROM {$table_name}
             WHERE metric_type = %s AND created_at >= %s
             ORDER BY metric_value ASC",
            $type,
            $since
        ));
        
        if (empty($values)) {
            return 0;
        }
        
        $index = ceil(($percentile / 100) * count($values)) - 1;
        return $values[max(0, $index)];
    }
    
    /**
     * Get current memory usage
     * 
     * @return array Memory usage stats
     */
    public function get_memory_usage() {
        return array(
            'current' => round(memory_get_usage(true) / 1048576, 2), // MB
            'peak' => round(memory_get_peak_usage(true) / 1048576, 2), // MB
            'limit' => ini_get('memory_limit')
        );
    }
    
    /**
     * Add performance menu to admin
     */
    public function add_performance_menu() {
        add_submenu_page(
            'sffc-dashboard',
            'Performance Metrics',
            'Performance',
            'manage_options',
            'sffc-performance',
            array($this, 'render_performance_page')
        );
    }
    
    /**
     * Render performance dashboard
     */
    public function render_performance_page() {
        $stats = $this->get_stats(null, 24);
        $memory = $this->get_memory_usage();
        ?>
        <div class="wrap">
            <h1>senna Finance - Performance Metrics</h1>
            
            <div class="sffc-performance-dashboard">
                <div class="sffc-metrics-grid">
                    <?php foreach ($stats as $metric): ?>
                    <div class="metric-card">
                        <h3><?php echo esc_html($this->format_metric_name($metric['metric_type'])); ?></h3>
                        <div class="metric-stats">
                            <div class="stat">
                                <span class="label">Average:</span>
                                <span class="value"><?php echo number_format($metric['avg_value'], 2); ?>ms</span>
                            </div>
                            <div class="stat">
                                <span class="label">Min/Max:</span>
                                <span class="value"><?php echo number_format($metric['min_value'], 2); ?> / <?php echo number_format($metric['max_value'], 2); ?>ms</span>
                            </div>
                            <?php if (isset($metric['p95'])): ?>
                            <div class="stat">
                                <span class="label">P95:</span>
                                <span class="value"><?php echo number_format($metric['p95'], 2); ?>ms</span>
                            </div>
                            <?php endif; ?>
                            <div class="stat">
                                <span class="label">Count:</span>
                                <span class="value"><?php echo number_format($metric['count']); ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <div class="metric-card">
                        <h3>Memory Usage</h3>
                        <div class="metric-stats">
                            <div class="stat">
                                <span class="label">Current:</span>
                                <span class="value"><?php echo $memory['current']; ?> MB</span>
                            </div>
                            <div class="stat">
                                <span class="label">Peak:</span>
                                <span class="value"><?php echo $memory['peak']; ?> MB</span>
                            </div>
                            <div class="stat">
                                <span class="label">Limit:</span>
                                <span class="value"><?php echo $memory['limit']; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="performance-chart" id="sffc-performance-chart">
                    <!-- Chart will be rendered here via JavaScript -->
                </div>
            </div>
            
            <style>
            .sffc-metrics-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
                margin: 20px 0;
            }
            .metric-card {
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 5px;
                padding: 15px;
            }
            .metric-card h3 {
                margin-top: 0;
                color: #23282d;
            }
            .metric-stats .stat {
                display: flex;
                justify-content: space-between;
                margin: 5px 0;
            }
            .stat .label {
                color: #666;
            }
            .stat .value {
                font-weight: bold;
            }
            </style>
        </div>
        <?php
    }
    
    /**
     * Format metric name for display
     * 
     * @param string $type Metric type
     * @return string Formatted name
     */
    private function format_metric_name($type) {
        $names = array(
            self::METRIC_API_CALL => 'API Calls',
            self::METRIC_DB_QUERY => 'Database Queries',
            self::METRIC_VISUAL_RENDER => 'Visual Rendering',
            self::METRIC_CACHE_HIT => 'Cache Performance',
            self::METRIC_RESPONSE_TIME => 'Response Times',
            self::METRIC_MEMORY_USAGE => 'Memory Usage'
        );
        
        return isset($names[$type]) ? $names[$type] : ucfirst(str_replace('_', ' ', $type));
    }
    
    /**
     * AJAX handler for getting metrics
     */
    public function ajax_get_metrics() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : null;
        $hours = isset($_POST['hours']) ? intval($_POST['hours']) : 24;
        
        $stats = $this->get_stats($type, $hours);
        
        wp_send_json_success($stats);
    }
    
    /**
     * Clean up old metrics
     */
    public function cleanup_old_metrics() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sffc_performance_metrics';
        $cutoff = date('Y-m-d H:i:s', strtotime('-7 days'));
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name} WHERE created_at < %s",
            $cutoff
        ));
    }
    
    /**
     * Create performance metrics table
     */
    public static function create_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sffc_performance_metrics';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            metric_type varchar(50) NOT NULL,
            metric_value float NOT NULL,
            context text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY metric_type (metric_type),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}