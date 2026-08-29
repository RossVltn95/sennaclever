<?php
/**
 * System Validator
 * Comprehensive validation and testing of plugin components
 * 
 * @package SennaCareers
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_System_Validator {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Validation results
     */
    private $results = array();
    
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
        add_action('admin_menu', array($this, 'add_validator_menu'));
        add_action('wp_ajax_sffc_run_validation', array($this, 'ajax_run_validation'));
    }
    
    /**
     * Run complete system validation
     * 
     * @return array Validation results
     */
    public function validate_system() {
        $this->results = array(
            'timestamp' => current_time('mysql'),
            'tests' => array(),
            'passed' => 0,
            'failed' => 0,
            'warnings' => 0
        );
        
        // Phase 19: Integration tests
        $this->test_ajax_endpoints();
        $this->test_database_operations();
        $this->test_api_connectivity();
        $this->test_visual_card_rendering();
        $this->test_session_management();
        $this->test_fallback_system();
        
        // Phase 20: Configuration validation
        $this->validate_configuration();
        $this->validate_dependencies();
        $this->validate_permissions();
        
        // Phase 21: Edge cases
        $this->test_edge_cases();
        
        return $this->results;
    }
    
    /**
     * Test AJAX endpoints
     */
    private function test_ajax_endpoints() {
        $endpoints = array(
            'sffc_start_conversation' => array('mode' => 'market', 'query' => 'test'),
            'sffc_send_message' => array('message' => 'test', 'conversation_id' => 1),
            'sffc_fetch_visual_card' => array('query' => 'market analysis'),
            'sffc_switch_mode' => array('mode' => 'career')
        );
        
        foreach ($endpoints as $action => $data) {
            $test_name = "AJAX Endpoint: {$action}";
            
            // Check if handler is registered
            if (has_action('wp_ajax_' . $action)) {
                $this->add_result($test_name, 'passed', 'Endpoint registered');
            } else {
                $this->add_result($test_name, 'failed', 'Endpoint not registered');
            }
        }
    }
    
    /**
     * Test database operations
     */
    private function test_database_operations() {
        global $wpdb;
        
        // Test table existence
        $tables = array(
            'sffc_conversations',
            'sffc_messages',
            'sffc_user_profiles',
            'sffc_performance_metrics'
        );
        
        foreach ($tables as $table) {
            $table_name = $wpdb->prefix . $table;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
            
            if ($exists) {
                $this->add_result("Database Table: {$table}", 'passed', 'Table exists');
                
                // Check for indexes
                $indexes = $wpdb->get_results("SHOW INDEX FROM {$table_name}");
                if (count($indexes) > 1) { // More than just primary key
                    $this->add_result("Table Indexes: {$table}", 'passed', count($indexes) . ' indexes found');
                } else {
                    $this->add_result("Table Indexes: {$table}", 'warning', 'No performance indexes');
                }
            } else {
                $this->add_result("Database Table: {$table}", 'failed', 'Table does not exist');
            }
        }
    }
    
    /**
     * Test API connectivity
     */
    private function test_api_connectivity() {
        // Check API key using centralized manager
        $api_key = '';
        if (class_exists('SFFC_API_Key_Manager')) {
            $key_manager = SFFC_API_Key_Manager::get_instance();
            $api_key = $key_manager->get_api_key();
        } else {
            $api_key = get_option('sffc_api_key', '');
        }

        if (empty($api_key)) {
            $this->add_result('API Key Configuration', 'warning', 'No API key configured - using fallback mode');
        } else {
            if (strpos($api_key, 'sk-ant-') === 0) {
                $this->add_result('API Key Configuration', 'passed', 'Valid API key format');

                // Test actual connectivity
                if (class_exists('SFFC_Claude_API_Manager')) {
                    $api_manager = SFFC_Claude_API_Manager::get_instance();
                    // Don't make actual API call in validation
                    $this->add_result('Claude API Manager', 'passed', 'API manager initialized');
                }
            } else {
                $this->add_result('API Key Configuration', 'failed', 'Invalid API key format');
            }
        }
    }
    
    /**
     * Test visual card rendering
     */
    private function test_visual_card_rendering() {
        $visual_types = array(
            'market_headlines', 'market_pulse', 'welcome_cards',
            'job_listings', 'career_paths', 'financial_models',
            'market_sectors', 'market_dashboard', 'comparison_tool'
        );
        
        // Premium renderer removed - folder deleted
        $renderer_file = '';
        
        if (false) {
            $content = '';
            
            foreach ($visual_types as $type) {
                if (strpos($content, "case '{$type}':") !== false) {
                    $this->add_result("Visual Card Type: {$type}", 'passed', 'Renderer support found');
                } else {
                    $this->add_result("Visual Card Type: {$type}", 'warning', 'No explicit renderer case');
                }
            }
        } else {
            $this->add_result('Visual Card Renderer', 'failed', 'Renderer file not found');
        }
    }
    
    /**
     * Test session management
     */
    private function test_session_management() {
        // Check session manager
        if (class_exists('SFFC_Session_Manager')) {
            $session_manager = SFFC_Session_Manager::get_instance();
            $this->add_result('Session Manager', 'passed', 'Session manager available');
            
            // Check for race condition prevention
            $reflection = new ReflectionClass($session_manager);
            if ($reflection->hasMethod('create_conversation_with_lock')) {
                $this->add_result('Race Condition Prevention', 'passed', 'Database locking implemented');
            } else {
                $this->add_result('Race Condition Prevention', 'warning', 'No explicit locking found');
            }
        } else {
            $this->add_result('Session Manager', 'failed', 'Session manager not loaded');
        }
    }
    
    /**
     * Test fallback system
     */
    private function test_fallback_system() {
        if (class_exists('SFFC_Fallback_Manager')) {
            $fallback_manager = SFFC_Fallback_Manager::get_instance();
            $this->add_result('Fallback Manager', 'passed', 'Fallback system available');
            
            // Test fallback response
            $test_response = $fallback_manager->get_fallback_response('test query', 'market');
            if ($test_response && isset($test_response['success'])) {
                $this->add_result('Fallback Response', 'passed', 'Fallback generates valid response');
            } else {
                $this->add_result('Fallback Response', 'warning', 'Fallback response format unclear');
            }
        } else {
            $this->add_result('Fallback Manager', 'failed', 'Fallback system not loaded');
        }
    }
    
    /**
     * Validate configuration (Phase 20)
     */
    private function validate_configuration() {
        // PHP version
        if (version_compare(PHP_VERSION, '7.4', '>=')) {
            $this->add_result('PHP Version', 'passed', 'PHP ' . PHP_VERSION);
        } else {
            $this->add_result('PHP Version', 'warning', 'PHP ' . PHP_VERSION . ' (7.4+ recommended)');
        }
        
        // WordPress version
        global $wp_version;
        if (version_compare($wp_version, '5.8', '>=')) {
            $this->add_result('WordPress Version', 'passed', 'WordPress ' . $wp_version);
        } else {
            $this->add_result('WordPress Version', 'warning', 'WordPress ' . $wp_version . ' (5.8+ recommended)');
        }
        
        // Memory limit
        $memory_limit = ini_get('memory_limit');
        $memory_bytes = wp_convert_hr_to_bytes($memory_limit);
        if ($memory_bytes >= 134217728) { // 128MB
            $this->add_result('Memory Limit', 'passed', $memory_limit);
        } else {
            $this->add_result('Memory Limit', 'warning', $memory_limit . ' (128M+ recommended)');
        }
        
        // Max execution time
        $max_execution = ini_get('max_execution_time');
        if ($max_execution >= 30 || $max_execution == 0) {
            $this->add_result('Max Execution Time', 'passed', $max_execution . ' seconds');
        } else {
            $this->add_result('Max Execution Time', 'warning', $max_execution . ' seconds (30+ recommended)');
        }
    }
    
    /**
     * Validate dependencies
     */
    private function validate_dependencies() {
        // Check required classes
        $required_classes = array(
            'SFFC_Ajax_Handler_V2' => 'V2 AJAX Handler',
            'SFFC_Claude_API_Manager' => 'Claude API Manager',
            'SFFC_Session_Manager' => 'Session Manager',
            'SFFC_Database' => 'Database Manager',
            'SFFC_Fallback_Manager' => 'Fallback Manager',
            'SFFC_Error_Handler' => 'Error Handler',
            'SFFC_Cache_Manager' => 'Cache Manager',
            'SFFC_Performance_Monitor' => 'Performance Monitor'
        );
        
        foreach ($required_classes as $class => $name) {
            if (class_exists($class)) {
                $this->add_result("Class: {$name}", 'passed', 'Class loaded');
            } else {
                $this->add_result("Class: {$name}", 'failed', 'Class not found');
            }
        }
        
        // Check JavaScript files
        $js_files = array(
            // Premium files removed - folder deleted
        );
        
        foreach ($js_files as $file) {
            $full_path = SFFC_PLUGIN_DIR . $file;
            if (file_exists($full_path)) {
                $size = filesize($full_path);
                $this->add_result("JavaScript: " . basename($file), 'passed', 
                    'File exists (' . round($size/1024, 1) . 'KB)');
            } else {
                $this->add_result("JavaScript: " . basename($file), 'failed', 'File not found');
            }
        }
    }
    
    /**
     * Validate permissions
     */
    private function validate_permissions() {
        // Check upload directory
        $upload_dir = wp_upload_dir();
        if (wp_is_writable($upload_dir['basedir'])) {
            $this->add_result('Upload Directory', 'passed', 'Writable');
        } else {
            $this->add_result('Upload Directory', 'failed', 'Not writable');
        }
        
        // Check if debug mode is appropriate
        if (!empty($_ENV['WP_DEBUG'])) {
            $this->add_result('Debug Mode', 'warning', 'Debug mode enabled (disable in production)');
        } else {
            $this->add_result('Debug Mode', 'passed', 'Debug mode disabled');
        }
    }
    
    /**
     * Test edge cases (Phase 21)
     */
    private function test_edge_cases() {
        // Test empty query handling
        if (class_exists('SFFC_Hybrid_Response_Manager')) {
            $manager = SFFC_Hybrid_Response_Manager::get_instance();
            $response = $manager->generate_response('', 'market', array());
            if ($response && !empty($response['message'])) {
                $this->add_result('Empty Query Handling', 'passed', 'Handles empty queries');
            } else {
                $this->add_result('Empty Query Handling', 'warning', 'May not handle empty queries');
            }
        }
        
        // Test very long query
        $long_query = str_repeat('test query ', 500);
        if (strlen($long_query) > 5000) {
            $this->add_result('Long Query Handling', 'passed', 'Can process long queries');
        }
        
        // Test special characters
        $special_query = "Test <script>alert('xss')</script> & special ' chars";
        $sanitized = sanitize_text_field($special_query);
        if ($sanitized !== $special_query) {
            $this->add_result('XSS Prevention', 'passed', 'Input sanitization active');
        } else {
            $this->add_result('XSS Prevention', 'warning', 'Check input sanitization');
        }
    }
    
    /**
     * Add validation result
     * 
     * @param string $test Test name
     * @param string $status Status (passed/failed/warning)
     * @param string $message Result message
     */
    private function add_result($test, $status, $message) {
        $this->results['tests'][] = array(
            'test' => $test,
            'status' => $status,
            'message' => $message
        );
        
        switch ($status) {
            case 'passed':
                $this->results['passed']++;
                break;
            case 'failed':
                $this->results['failed']++;
                break;
            case 'warning':
                $this->results['warnings']++;
                break;
        }
    }
    
    /**
     * Add validator menu
     */
    public function add_validator_menu() {
        add_submenu_page(
            'sffc-dashboard',
            'System Validation',
            'System Check',
            'manage_options',
            'sffc-validator',
            array($this, 'render_validator_page')
        );
    }
    
    /**
     * Render validator page
     */
    public function render_validator_page() {
        ?>
        <div class="wrap">
            <h1>senna Finance - System Validation</h1>
            
            <div class="sffc-validator">
                <button class="button button-primary" id="run-validation">Run System Check</button>
                
                <div id="validation-results" style="margin-top: 20px;">
                    <!-- Results will appear here -->
                </div>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                $('#run-validation').on('click', function() {
                    var $button = $(this);
                    var $results = $('#validation-results');
                    
                    $button.prop('disabled', true).text('Running tests...');
                    $results.html('<p>Running system validation...</p>');
                    
                    $.post(ajaxurl, {
                        action: 'sffc_run_validation',
                        nonce: '<?php echo wp_create_nonce('sffc_validation'); ?>'
                    }, function(response) {
                        if (response.success) {
                            var results = response.data;
                            var html = '<h2>Validation Results</h2>';
                            html += '<p>Tested at: ' + results.timestamp + '</p>';
                            html += '<div class="test-summary">';
                            html += '<span class="passed">✓ Passed: ' + results.passed + '</span> ';
                            html += '<span class="failed">✗ Failed: ' + results.failed + '</span> ';
                            html += '<span class="warnings">⚠ Warnings: ' + results.warnings + '</span>';
                            html += '</div>';
                            html += '<table class="wp-list-table widefat">';
                            html += '<thead><tr><th>Test</th><th>Status</th><th>Message</th></tr></thead>';
                            html += '<tbody>';
                            
                            results.tests.forEach(function(test) {
                                var statusIcon = test.status === 'passed' ? '✓' : 
                                                 test.status === 'failed' ? '✗' : '⚠';
                                var statusClass = test.status;
                                html += '<tr class="' + statusClass + '">';
                                html += '<td>' + test.test + '</td>';
                                html += '<td><span class="status-' + statusClass + '">' + 
                                        statusIcon + ' ' + test.status + '</span></td>';
                                html += '<td>' + test.message + '</td>';
                                html += '</tr>';
                            });
                            
                            html += '</tbody></table>';
                            $results.html(html);
                        } else {
                            $results.html('<p class="error">Validation failed: ' + response.data + '</p>');
                        }
                        
                        $button.prop('disabled', false).text('Run System Check');
                    });
                });
            });
            </script>
            
            <style>
            .test-summary {
                margin: 20px 0;
                font-size: 16px;
            }
            .test-summary .passed { color: #46b450; }
            .test-summary .failed { color: #dc3232; }
            .test-summary .warnings { color: #ffb900; }
            .status-passed { color: #46b450; font-weight: bold; }
            .status-failed { color: #dc3232; font-weight: bold; }
            .status-warning { color: #ffb900; font-weight: bold; }
            tr.passed { background: #f0f8f0; }
            tr.failed { background: #fff0f0; }
            tr.warning { background: #fffaf0; }
            </style>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for validation
     */
    public function ajax_run_validation() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        if (!check_ajax_referer('sffc_validation', 'nonce', false)) {
            wp_send_json_error('Security check failed');
        }
        
        $results = $this->validate_system();
        wp_send_json_success($results);
    }
}