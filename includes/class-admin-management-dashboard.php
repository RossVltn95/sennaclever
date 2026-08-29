<?php
/**
 * Admin Management Dashboard
 * 
 * Administrative interface for monitoring and managing the template intelligence system
 * Provides status monitoring, manual controls, and system health checks
 * 
 * @package SennaFinanceCareer
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Admin_Management_Dashboard {
    
    private static $instance = null;
    private $template_intelligence = null;
    private $pe_insights = null;
    private $claude_enhancer = null;
    private $deal_processor = null;
    
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
        // Initialize dependencies
        if (class_exists('SFFC_Template_Intelligence_System')) {
            $this->template_intelligence = SFFC_Template_Intelligence_System::get_instance();
        }
        
        if (class_exists('SFFC_PE_Insights_Fetcher')) {
            $this->pe_insights = SFFC_PE_Insights_Fetcher::get_instance();
        }
        
        if (class_exists('SFFC_Claude_Template_Enhancer')) {
            $this->claude_enhancer = SFFC_Claude_Template_Enhancer::get_instance();
        }
        
        if (class_exists('SFFC_Deal_Intelligence_Processor')) {
            $this->deal_processor = SFFC_Deal_Intelligence_Processor::get_instance();
        }
        
        // Admin menu hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // AJAX handlers
        add_action('wp_ajax_sffc_refresh_templates', array($this, 'ajax_refresh_templates'));
        add_action('wp_ajax_sffc_clear_cache', array($this, 'ajax_clear_cache'));
        add_action('wp_ajax_sffc_test_claude', array($this, 'ajax_test_claude'));
        add_action('wp_ajax_sffc_system_health_check', array($this, 'ajax_system_health_check'));
        
        // Admin styles and scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=sffc_pe_news',
            'Template Intelligence System',
            'Template Intelligence',
            'manage_options',
            'sffc-template-intelligence',
            array($this, 'render_dashboard_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('sffc_template_settings', 'sffc_template_intelligence_enabled');
        register_setting('sffc_template_settings', 'sffc_claude_enhancement_enabled');
        register_setting('sffc_template_settings', 'sffc_pe_insights_enabled');
        register_setting('sffc_template_settings', 'sffc_auto_refresh_interval');
        register_setting('sffc_template_settings', 'sffc_daily_post_limit', array(
            'type' => 'integer',
            'default' => 50,
            'sanitize_callback' => 'absint'
        ));
        register_setting('sffc_template_settings', 'sffc_daily_api_budget', array(
            'type' => 'integer',
            'default' => 500,
            'sanitize_callback' => 'absint'
        ));
    }
    
    /**
     * Render main dashboard page
     */
    public function render_dashboard_page() {
        // Get system status data
        $system_status = $this->get_system_status();
        $pe_insights_status = $this->pe_insights ? $this->pe_insights->get_fetch_status() : array();
        $template_status = $this->template_intelligence ? $this->template_intelligence->get_system_status() : array();
        $claude_status = $this->claude_enhancer ? $this->claude_enhancer->get_enhancement_status() : array();
        
        ?>
        <div class="wrap">
            <h1>Template Intelligence System</h1>
            <p>Monitor and manage the automated template enhancement system for authentic PE industry insights.</p>
            
            <!-- System Health Overview -->
            <div class="sffc-dashboard-section">
                <h2>System Health Overview</h2>
                <div class="sffc-status-grid">
                    
                    <!-- PE Insights Status -->
                    <div class="sffc-status-card <?php echo $this->get_status_class($pe_insights_status['status'] ?? 'never'); ?>">
                        <h3>PE Insights Data</h3>
                        <div class="status-indicator">
                            <?php echo $this->get_status_icon($pe_insights_status['status'] ?? 'never'); ?>
                            <span><?php echo ucfirst($pe_insights_status['status'] ?? 'Not Available'); ?></span>
                        </div>
                        <div class="status-details">
                            <p>Total Deals: <strong><?php echo $pe_insights_status['total_deals'] ?? 0; ?></strong></p>
                            <p>Last Fetch: <?php echo $this->format_timestamp($pe_insights_status['timestamp'] ?? 0); ?></p>
                            <?php if (!empty($pe_insights_status['errors'])): ?>
                                <p class="error">Errors: <?php echo count($pe_insights_status['errors']); ?> issues detected</p>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="button button-secondary" id="refresh-pe-insights">Refresh Now</button>
                    </div>
                    
                    <!-- Claude Enhancement Status -->
                    <div class="sffc-status-card <?php echo $this->get_status_class($claude_status['claude_available'] ?? false ? 'success' : 'warning'); ?>">
                        <h3>Claude Enhancement</h3>
                        <div class="status-indicator">
                            <?php echo $this->get_status_icon($claude_status['claude_available'] ?? false ? 'success' : 'warning'); ?>
                            <span><?php echo ($claude_status['claude_available'] ?? false) ? 'Available' : 'Unavailable'; ?></span>
                        </div>
                        <div class="status-details">
                            <p>Enhanced Templates: <strong><?php echo $claude_status['enhanced_templates'] ?? 0; ?>/<?php echo $claude_status['total_categories'] ?? 6; ?></strong></p>
                            <p>Last Run: <?php echo $this->format_timestamp($claude_status['last_run'] ?? 0); ?></p>
                            <?php if (!empty($claude_status['errors'])): ?>
                                <p class="error">Errors: <?php echo count($claude_status['errors']); ?> issues</p>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="button button-secondary" id="test-claude">Test Connection</button>
                    </div>
                    
                    <!-- Template Intelligence Status -->
                    <div class="sffc-status-card <?php echo $this->get_status_class($template_status['status'] ?? 'unknown'); ?>">
                        <h3>Template Intelligence</h3>
                        <div class="status-indicator">
                            <?php echo $this->get_status_icon($template_status['status'] ?? 'unknown'); ?>
                            <span><?php echo ucfirst($template_status['status'] ?? 'Unknown'); ?></span>
                        </div>
                        <div class="status-details">
                            <p>Active Templates: <strong><?php echo $template_status['active_templates'] ?? 0; ?></strong></p>
                            <p>Cache Health: <?php echo $template_status['cache_health'] ?? 'Unknown'; ?></p>
                            <p>Last Refresh: <?php echo $this->format_timestamp($template_status['last_refresh'] ?? 0); ?></p>
                        </div>
                        <button type="button" class="button button-secondary" id="refresh-templates">Refresh Templates</button>
                    </div>
                    
                    <!-- System Performance -->
                    <div class="sffc-status-card success">
                        <h3>System Performance</h3>
                        <div class="status-indicator">
                            <?php echo $this->get_status_icon('success'); ?>
                            <span>Operational</span>
                        </div>
                        <div class="status-details">
                            <p>Cache Usage: <strong><?php echo $system_status['cache_usage']; ?>%</strong></p>
                            <p>API Calls Today: <strong><?php echo $system_status['api_calls_today']; ?></strong></p>
                            <p>Error Rate: <strong><?php echo $system_status['error_rate']; ?>%</strong></p>
                        </div>
                        <button type="button" class="button button-secondary" id="system-health-check">Run Health Check</button>
                    </div>
                    
                </div>
            </div>
            
            <!-- Recent Activity Log -->
            <div class="sffc-dashboard-section">
                <h2>Recent Activity</h2>
                <div class="sffc-activity-log">
                    <?php $this->render_activity_log(); ?>
                </div>
            </div>
            
            <!-- System Configuration -->
            <div class="sffc-dashboard-section">
                <h2>System Configuration</h2>
                <form method="post" action="options.php">
                    <?php settings_fields('sffc_template_settings'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Template Intelligence</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="sffc_template_intelligence_enabled" value="1" 
                                           <?php checked(get_option('sffc_template_intelligence_enabled', 1)); ?> />
                                    Enable automated template intelligence system
                                </label>
                                <p class="description">Automatically analyze articles and generate appropriate templates</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Claude Enhancement</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="sffc_claude_enhancement_enabled" value="1" 
                                           <?php checked(get_option('sffc_claude_enhancement_enabled', 1)); ?> />
                                    Enable Claude AI template enhancement
                                </label>
                                <p class="description">Use Claude AI for intelligent market analysis and insights</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">PE Insights Data</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="sffc_pe_insights_enabled" value="1" 
                                           <?php checked(get_option('sffc_pe_insights_enabled', 1)); ?> />
                                    Enable real market data from PE Insights
                                </label>
                                <p class="description">Fetch real deal data for authentic market intelligence</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Auto Refresh Interval</th>
                            <td>
                                <select name="sffc_auto_refresh_interval">
                                    <option value="daily" <?php selected(get_option('sffc_auto_refresh_interval', 'weekly'), 'daily'); ?>>Daily</option>
                                    <option value="weekly" <?php selected(get_option('sffc_auto_refresh_interval', 'weekly'), 'weekly'); ?>>Weekly</option>
                                    <option value="monthly" <?php selected(get_option('sffc_auto_refresh_interval', 'weekly'), 'monthly'); ?>>Monthly</option>
                                </select>
                                <p class="description">How often to automatically refresh templates and data</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Daily Post Limit</th>
                            <td>
                                <input type="number" name="sffc_daily_post_limit" min="1" max="500"
                                       value="<?php echo esc_attr(get_option('sffc_daily_post_limit', 50)); ?>"
                                       class="small-text" />
                                <p class="description">Maximum number of posts (news, deals, signals) to create per day. This controls API costs by limiting content generation. <strong>Current: <?php
                                    $today = date('Y-m-d');
                                    $created = (int) get_option('sffc_daily_posts_created_' . $today, 0);
                                    $limit = (int) get_option('sffc_daily_post_limit', 50);
                                    echo $created . '/' . $limit . ' posts created today';
                                ?></strong></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Daily API Budget (cents)</th>
                            <td>
                                <input type="number" name="sffc_daily_api_budget" min="100" max="10000"
                                       value="<?php echo esc_attr(get_option('sffc_daily_api_budget', 500)); ?>"
                                       class="small-text" />
                                <p class="description">Maximum API spending per day in cents (500 = $5.00). <strong>Current: $<?php
                                    $spent = (float) get_option('sffc_daily_api_cost_' . $today, 0);
                                    $budget = (float) get_option('sffc_daily_api_budget', 500);
                                    echo number_format($spent / 100, 2) . ' / $' . number_format($budget / 100, 2) . ' spent today';
                                ?></strong></p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(); ?>
                </form>
            </div>
            
            <!-- Manual Controls -->
            <div class="sffc-dashboard-section">
                <h2>Manual Controls</h2>
                <div class="sffc-manual-controls">
                    <button type="button" class="button button-primary" id="refresh-all-data">Refresh All Data</button>
                    <button type="button" class="button button-secondary" id="clear-all-cache">Clear All Cache</button>
                    <button type="button" class="button button-secondary" id="export-system-log">Export System Log</button>
                    <button type="button" class="button button-secondary" id="reset-system">Reset System</button>
                </div>
                <p class="description">Use these controls to manually manage the template intelligence system.</p>
            </div>
            
            <!-- Debug Information -->
            <div class="sffc-dashboard-section">
                <h2>Debug Information</h2>
                <div class="sffc-debug-info">
                    <textarea readonly rows="10" cols="80"><?php echo $this->get_debug_info(); ?></textarea>
                </div>
                <p class="description">System configuration and debug information for troubleshooting.</p>
            </div>
            
        </div>
        
        <style>
        .sffc-status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .sffc-status-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 20px;
            border-left: 4px solid #ddd;
        }
        
        .sffc-status-card.success {
            border-left-color: #46b450;
        }
        
        .sffc-status-card.warning {
            border-left-color: #ffb900;
        }
        
        .sffc-status-card.error {
            border-left-color: #dc3232;
        }
        
        .status-indicator {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .status-indicator .dashicons {
            margin-right: 5px;
        }
        
        .status-details p {
            margin: 5px 0;
            font-size: 13px;
        }
        
        .status-details .error {
            color: #dc3232;
        }
        
        .sffc-dashboard-section {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .sffc-activity-log {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 10px;
            background: #f9f9f9;
        }
        
        .activity-entry {
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
        
        .activity-entry:last-child {
            border-bottom: none;
        }
        
        .activity-timestamp {
            color: #666;
            font-size: 11px;
        }
        
        .sffc-manual-controls button {
            margin-right: 10px;
            margin-bottom: 5px;
        }
        
        .sffc-debug-info textarea {
            width: 100%;
            font-family: monospace;
            font-size: 11px;
            background: #f9f9f9;
        }
        </style>
        <?php
    }
    
    /**
     * Get system status data
     */
    private function get_system_status() {
        // Calculate cache usage
        $cache_size = $this->calculate_cache_usage();
        
        // Get today's API calls
        $api_calls = get_option('sffc_daily_api_calls_' . date('Y-m-d'), 0);
        
        // Calculate error rate from recent logs
        $error_rate = $this->calculate_error_rate();
        
        return array(
            'cache_usage' => $cache_size,
            'api_calls_today' => $api_calls,
            'error_rate' => $error_rate
        );
    }
    
    /**
     * Calculate cache usage percentage
     */
    private function calculate_cache_usage() {
        $cache_keys = array(
            'sffc_pe_insights_deals',
            'sffc_template_intelligence_cache',
            'sffc_enhanced_template_fundraise',
            'sffc_enhanced_template_acquisition',
            'sffc_enhanced_template_exit',
            'sffc_enhanced_template_esg'
        );
        
        $active_cache = 0;
        foreach ($cache_keys as $key) {
            if (get_transient($key) !== false) {
                $active_cache++;
            }
        }
        
        return round(($active_cache / count($cache_keys)) * 100);
    }
    
    /**
     * Calculate error rate from recent activity
     */
    private function calculate_error_rate() {
        $activity_log = get_option('sffc_activity_log', array());
        $recent_entries = array_slice($activity_log, -20); // Last 20 entries
        
        if (empty($recent_entries)) return 0;
        
        $errors = array_filter($recent_entries, function($entry) {
            return $entry['type'] === 'error';
        });
        
        return round((count($errors) / count($recent_entries)) * 100);
    }
    
    /**
     * Render activity log
     */
    private function render_activity_log() {
        $activity_log = get_option('sffc_activity_log', array());
        $recent_activity = array_slice(array_reverse($activity_log), 0, 20);
        
        if (empty($recent_activity)) {
            echo '<p>No recent activity recorded.</p>';
            return;
        }
        
        foreach ($recent_activity as $entry) {
            $icon_class = $entry['type'] === 'error' ? 'error' : 'success';
            echo '<div class="activity-entry">';
            echo '<div class="activity-timestamp">' . date('Y-m-d H:i:s', $entry['timestamp']) . '</div>';
            echo '<div class="activity-message ' . $icon_class . '">' . esc_html($entry['message']) . '</div>';
            echo '</div>';
        }
    }
    
    /**
     * Get debug information
     */
    private function get_debug_info() {
        $debug_info = array();
        $debug_info[] = "Template Intelligence System Debug Info";
        $debug_info[] = "Generated: " . date('Y-m-d H:i:s');
        $debug_info[] = "";
        
        // WordPress info
        $debug_info[] = "WordPress Version: " . get_bloginfo('version');
        $debug_info[] = "PHP Version: " . PHP_VERSION;
        $debug_info[] = "Plugin Version: " . (defined('SFFC_VERSION') ? SFFC_VERSION : 'Unknown');
        $debug_info[] = "";
        
        // Class availability
        $debug_info[] = "Class Availability:";
        $debug_info[] = "- SFFC_Template_Intelligence_System: " . (class_exists('SFFC_Template_Intelligence_System') ? 'Available' : 'Missing');
        $debug_info[] = "- SFFC_PE_Insights_Fetcher: " . (class_exists('SFFC_PE_Insights_Fetcher') ? 'Available' : 'Missing');
        $debug_info[] = "- SFFC_Claude_Template_Enhancer: " . (class_exists('SFFC_Claude_Template_Enhancer') ? 'Available' : 'Missing');
        $debug_info[] = "- SFFC_Deal_Intelligence_Processor: " . (class_exists('SFFC_Deal_Intelligence_Processor') ? 'Available' : 'Missing');
        $debug_info[] = "";
        
        // Settings
        $debug_info[] = "Settings:";
        $debug_info[] = "- Template Intelligence Enabled: " . (get_option('sffc_template_intelligence_enabled', 1) ? 'Yes' : 'No');
        $debug_info[] = "- Claude Enhancement Enabled: " . (get_option('sffc_claude_enhancement_enabled', 1) ? 'Yes' : 'No');
        $debug_info[] = "- PE Insights Enabled: " . (get_option('sffc_pe_insights_enabled', 1) ? 'Yes' : 'No');
        $debug_info[] = "- Auto Refresh Interval: " . get_option('sffc_auto_refresh_interval', 'weekly');
        $debug_info[] = "";
        
        // Cron jobs
        $debug_info[] = "Scheduled Tasks:";
        $debug_info[] = "- PE Insights Fetch: " . (wp_next_scheduled('sffc_pe_insights_fetch') ? 'Scheduled' : 'Not Scheduled');
        $debug_info[] = "- Template Refresh: " . (wp_next_scheduled('sffc_refresh_templates') ? 'Scheduled' : 'Not Scheduled');
        $debug_info[] = "- Claude Enhancement: " . (wp_next_scheduled('sffc_enhance_templates') ? 'Scheduled' : 'Not Scheduled');
        
        return implode("\n", $debug_info);
    }
    
    /**
     * Get status CSS class
     */
    private function get_status_class($status) {
        switch ($status) {
            case 'success':
            case 'operational':
                return 'success';
            case 'partial':
            case 'warning':
            case 'fallback':
                return 'warning';
            case 'error':
            case 'failed':
                return 'error';
            default:
                return '';
        }
    }
    
    /**
     * Get status icon
     */
    private function get_status_icon($status) {
        switch ($status) {
            case 'success':
            case 'operational':
            case true:
                return '<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>';
            case 'partial':
            case 'warning':
            case 'fallback':
                return '<span class="dashicons dashicons-warning" style="color: #ffb900;"></span>';
            case 'error':
            case 'failed':
            case false:
                return '<span class="dashicons dashicons-dismiss" style="color: #dc3232;"></span>';
            default:
                return '<span class="dashicons dashicons-minus" style="color: #666;"></span>';
        }
    }
    
    /**
     * Format timestamp for display
     */
    private function format_timestamp($timestamp) {
        if ($timestamp === 0) {
            return 'Never';
        }
        
        $diff = time() - $timestamp;
        
        if ($diff < 60) {
            return 'Just now';
        } elseif ($diff < 3600) {
            return floor($diff / 60) . ' minutes ago';
        } elseif ($diff < 86400) {
            return floor($diff / 3600) . ' hours ago';
        } else {
            return date('M j, Y g:i A', $timestamp);
        }
    }
    
    /**
     * AJAX: Refresh templates
     */
    public function ajax_refresh_templates() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'sffc_admin_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
            return;
        }
        
        try {
            if ($this->template_intelligence) {
                $result = $this->template_intelligence->refresh_all_templates();
                $this->log_activity('success', 'Templates refreshed manually by admin');
                wp_send_json_success($result);
            } else {
                wp_send_json_error('Template Intelligence system not available');
            }
        } catch (Exception $e) {
            $this->log_activity('error', 'Manual template refresh failed: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Clear cache
     */
    public function ajax_clear_cache() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'sffc_admin_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
            return;
        }
        
        try {
            $this->clear_all_cache();
            $this->log_activity('success', 'All cache cleared manually by admin');
            wp_send_json_success('Cache cleared successfully');
        } catch (Exception $e) {
            $this->log_activity('error', 'Cache clear failed: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Test Claude connection
     */
    public function ajax_test_claude() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'sffc_admin_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
            return;
        }
        
        try {
            if ($this->claude_enhancer && $this->claude_enhancer->is_claude_available()) {
                wp_send_json_success('Claude API connection successful');
            } else {
                wp_send_json_error('Claude API not available');
            }
        } catch (Exception $e) {
            wp_send_json_error('Claude test failed: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX: System health check
     */
    public function ajax_system_health_check() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'sffc_admin_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
            return;
        }
        
        $health_check = $this->run_system_health_check();
        wp_send_json_success($health_check);
    }
    
    /**
     * Run comprehensive system health check
     */
    private function run_system_health_check() {
        $results = array();
        
        // Check database connectivity
        $results['database'] = $this->check_database_health();
        
        // Check external API availability
        $results['pe_insights'] = $this->check_pe_insights_health();
        
        // Check Claude API
        $results['claude'] = $this->check_claude_health();
        
        // Check cache functionality
        $results['cache'] = $this->check_cache_health();
        
        // Check scheduled tasks
        $results['cron'] = $this->check_cron_health();
        
        return $results;
    }
    
    /**
     * Check database health
     */
    private function check_database_health() {
        global $wpdb;
        
        try {
            $result = $wpdb->get_var("SELECT 1");
            return $result === '1' ? 'healthy' : 'unhealthy';
        } catch (Exception $e) {
            return 'error: ' . $e->getMessage();
        }
    }
    
    /**
     * Check PE Insights API health
     */
    private function check_pe_insights_health() {
        if (!$this->pe_insights) {
            return 'unavailable';
        }
        
        try {
            $response = wp_remote_head('https://pe-insights.com/wp-sitemap-posts-post-5.xml', array('timeout' => 10));
            return is_wp_error($response) ? 'unreachable' : 'healthy';
        } catch (Exception $e) {
            return 'error: ' . $e->getMessage();
        }
    }
    
    /**
     * Check Claude API health
     */
    private function check_claude_health() {
        if (!$this->claude_enhancer) {
            return 'unavailable';
        }
        
        return $this->claude_enhancer->is_claude_available() ? 'healthy' : 'unavailable';
    }
    
    /**
     * Check cache health
     */
    private function check_cache_health() {
        $test_key = 'sffc_health_check_' . time();
        $test_value = 'test_' . rand(1000, 9999);
        
        set_transient($test_key, $test_value, 60);
        $retrieved = get_transient($test_key);
        delete_transient($test_key);
        
        return $retrieved === $test_value ? 'healthy' : 'unhealthy';
    }
    
    /**
     * Check cron health
     */
    private function check_cron_health() {
        $schedules = array(
            'pe_insights' => wp_next_scheduled('sffc_pe_insights_fetch'),
            'templates' => wp_next_scheduled('sffc_refresh_templates'),
            'claude' => wp_next_scheduled('sffc_enhance_templates')
        );
        
        $healthy = true;
        foreach ($schedules as $name => $next_run) {
            if (!$next_run) {
                $healthy = false;
                break;
            }
        }
        
        return $healthy ? 'healthy' : 'missing_schedules';
    }
    
    /**
     * Clear all system cache
     */
    private function clear_all_cache() {
        $cache_keys = array(
            'sffc_pe_insights_deals',
            'sffc_template_intelligence_cache',
            'sffc_enhanced_template_fundraise',
            'sffc_enhanced_template_acquisition',
            'sffc_enhanced_template_exit',
            'sffc_enhanced_template_esg',
            'sffc_enhanced_template_regulation',
            'sffc_enhanced_template_people_moves'
        );
        
        foreach ($cache_keys as $key) {
            delete_transient($key);
        }
        
        // Clear PE Insights cache
        if ($this->pe_insights) {
            $this->pe_insights->clear_cache();
        }
    }
    
    /**
     * Log activity for admin tracking
     */
    private function log_activity($type, $message) {
        $activity_log = get_option('sffc_activity_log', array());
        
        $activity_log[] = array(
            'timestamp' => time(),
            'type' => $type,
            'message' => $message
        );
        
        // Keep only last 100 entries
        $activity_log = array_slice($activity_log, -100);
        
        update_option('sffc_activity_log', $activity_log);
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'sffc-template-intelligence') === false) {
            return;
        }
        
        wp_enqueue_script('sffc-admin-dashboard', plugin_dir_url(__FILE__) . '../assets/js/admin-dashboard.js', array('jquery'), '1.0', true);
        wp_localize_script('sffc-admin-dashboard', 'sffcAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sffc_admin_nonce')
        ));
    }
}

// Initialize the admin dashboard
SFFC_Admin_Management_Dashboard::get_instance();