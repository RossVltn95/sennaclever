<?php

/**
 * Error Recovery System for senna AutoFill
 * 
 * Comprehensive error handling, recovery mechanisms, and fallback strategies
 * 
 * @package MENA Careers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Error_Recovery_System
{

    /**
     * Instance
     */
    private static $instance = null;

    /**
     * Error log table
     */
    private $error_log_table;

    /**
     * Recovery strategies
     */
    private $recovery_strategies = [];

    /**
     * Maximum retry attempts
     */
    private $max_retries = 3;

    /**
     * Retry delay (seconds)
     */
    private $retry_delay = 2;

    /**
     * Constructor
     */
    public function __construct()
    {
        global $wpdb;
        $this->error_log_table = $wpdb->prefix . 'sffc_error_logs';

        $this->init();
    }

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
     * Initialize system
     */
    private function init()
    {
        $this->register_recovery_strategies();
        $this->init_hooks();
    }

    /**
     * Create error log table
     */
    private function create_error_log_table()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->error_log_table} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            error_code VARCHAR(50),
            error_type VARCHAR(50),
            error_message TEXT,
            error_context LONGTEXT,
            user_id INT,
            platform VARCHAR(100),
            recovery_attempted BOOLEAN DEFAULT 0,
            recovery_successful BOOLEAN DEFAULT 0,
            recovery_method VARCHAR(100),
            occurred_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_error_code (error_code),
            INDEX idx_user_id (user_id),
            INDEX idx_occurred_at (occurred_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        // AJAX handlers for error reporting
        add_action('wp_ajax_sffc_report_error', [$this, 'ajax_report_error']);
        add_action('wp_ajax_nopriv_sffc_report_error', [$this, 'ajax_report_error']);

        // Recovery endpoints
        add_action('wp_ajax_sffc_attempt_recovery', [$this, 'ajax_attempt_recovery']);
        add_action('wp_ajax_sffc_get_fallback_solution', [$this, 'ajax_get_fallback_solution']);

        // Cleanup old logs
        add_action('sffc_cleanup_error_logs', [$this, 'cleanup_old_logs']);

        if (!wp_next_scheduled('sffc_cleanup_error_logs')) {
            wp_schedule_event(time(), 'weekly', 'sffc_cleanup_error_logs');
        }
    }

    /**
     * Register recovery strategies
     */
    private function register_recovery_strategies()
    {
        $this->recovery_strategies = [
            'CV_PARSE_FAILED' => [$this, 'recover_cv_parse_failure'],
            'PLATFORM_NOT_DETECTED' => [$this, 'recover_platform_detection'],
            'FIELD_NOT_FOUND' => [$this, 'recover_field_not_found'],
            'TOKEN_INVALID' => [$this, 'recover_token_invalid'],
            'NETWORK_ERROR' => [$this, 'recover_network_error'],
            'FILE_UPLOAD_FAILED' => [$this, 'recover_file_upload'],
            'API_LIMIT_EXCEEDED' => [$this, 'recover_api_limit'],
            'SESSION_EXPIRED' => [$this, 'recover_session_expired'],
            'FORM_VALIDATION_ERROR' => [$this, 'recover_form_validation'],
            'BROWSER_INCOMPATIBLE' => [$this, 'recover_browser_issue']
        ];
    }

    /**
     * Handle error with recovery attempt
     */
    public function handle_error($error_code, $error_message, $context = [])
    {
        // Log the error
        $error_id = $this->log_error($error_code, $error_message, $context);

        // Attempt recovery if strategy exists
        if (isset($this->recovery_strategies[$error_code])) {
            $recovery_result = $this->attempt_recovery($error_code, $context);

            if ($recovery_result['success']) {
                $this->mark_recovery_successful($error_id, $recovery_result['method']);
                return $recovery_result;
            }
        }

        // Return fallback solution
        return $this->get_fallback_solution($error_code, $context);
    }

    /**
     * Log error to database
     */
    private function log_error($error_code, $error_message, $context)
    {
        global $wpdb;

        $user_id = isset($context['user_id']) ? $context['user_id'] : get_current_user_id();
        $platform = isset($context['platform']) ? $context['platform'] : 'unknown';

        $wpdb->insert(
            $this->error_log_table,
            [
                'error_code' => $error_code,
                'error_type' => $this->get_error_type($error_code),
                'error_message' => $error_message,
                'error_context' => json_encode($context),
                'user_id' => $user_id,
                'platform' => $platform
            ]
        );

        return $wpdb->insert_id;
    }

    /**
     * Attempt recovery
     */
    private function attempt_recovery($error_code, $context)
    {
        if (!isset($this->recovery_strategies[$error_code])) {
            return ['success' => false];
        }

        $strategy = $this->recovery_strategies[$error_code];
        $attempts = 0;

        while ($attempts < $this->max_retries) {
            $attempts++;

            try {
                $result = call_user_func($strategy, $context);

                if ($result['success']) {
                    return $result;
                }
            } catch (Exception $e) {
                error_log('Recovery attempt failed: ' . $e->getMessage());
            }

            // Wait before retry
            if ($attempts < $this->max_retries) {
                sleep($this->retry_delay);
            }
        }

        return ['success' => false];
    }

    /**
     * Recovery: CV Parse Failure
     */
    private function recover_cv_parse_failure($context)
    {
        $recovery_methods = [];

        // Method 1: Try alternative parser
        if (isset($context['file_path'])) {
            // Try server-side parsing if client-side failed
            if (!class_exists('SFFC_Document_Parser')) {
                require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-document-parser.php';
            }

            $parser = SFFC_Document_Parser::get_instance();
            $text = '';

            if (isset($context['file_type'])) {
                switch ($context['file_type']) {
                    case 'pdf':
                        $text = $parser->extract_pdf_text($context['file_path']);
                        break;
                    case 'docx':
                        $text = $parser->extract_docx_text($context['file_path']);
                        break;
                }
            }

            if (!empty($text)) {
                // Try basic parsing
                if (!class_exists('SFFC_AI_Parser_Service')) {
                    require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-ai-parser-service.php';
                }

                $ai_parser = SFFC_AI_Parser_Service::get_instance();
                $parsed = $ai_parser->parse_cv_text($text);

                if ($parsed && $parsed['confidence'] > 0.3) {
                    return [
                        'success' => true,
                        'method' => 'alternative_parser',
                        'data' => $parsed
                    ];
                }
            }
        }

        // Method 2: Return manual entry interface
        return [
            'success' => true,
            'method' => 'manual_entry',
            'action' => 'show_manual_cv_form',
            'message' => 'CV parsing failed. Please enter your information manually.'
        ];
    }

    /**
     * Recovery: Platform Not Detected
     */
    private function recover_platform_detection($context)
    {
        // Method 1: Try generic selectors
        $generic_patterns = [
            'name' => ['[name*="name"]', '[placeholder*="name"]', 'input[aria-label*="name"]'],
            'email' => ['[type="email"]', '[name*="email"]', '[placeholder*="email"]'],
            'phone' => ['[type="tel"]', '[name*="phone"]', '[placeholder*="phone"]'],
            'resume' => ['[type="file"]', '[name*="resume"]', '[name*="cv"]']
        ];

        return [
            'success' => true,
            'method' => 'generic_detection',
            'data' => [
                'platform' => 'generic',
                'selectors' => $generic_patterns,
                'action' => 'show_manual_mapper'
            ]
        ];
    }

    /**
     * Recovery: Field Not Found
     */
    private function recover_field_not_found($context)
    {
        $field_name = isset($context['field_name']) ? $context['field_name'] : 'unknown';

        // Method 1: Try alternative selectors
        $alternative_selectors = $this->get_alternative_selectors($field_name);

        if (!empty($alternative_selectors)) {
            return [
                'success' => true,
                'method' => 'alternative_selectors',
                'data' => [
                    'selectors' => $alternative_selectors,
                    'action' => 'retry_with_selectors'
                ]
            ];
        }

        // Method 2: Manual field mapping
        return [
            'success' => true,
            'method' => 'manual_mapping',
            'action' => 'highlight_field_for_manual_selection',
            'message' => "Please click on the {$field_name} field"
        ];
    }

    /**
     * Recovery: Token Invalid
     */
    private function recover_token_invalid($context)
    {
        // Method 1: Try token refresh
        if (isset($context['token'])) {
            if (!class_exists('SFFC_Token_Manager')) {
                require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-token-manager.php';
            }

            $token_manager = SFFC_Token_Manager::get_instance();
            $refreshed = $token_manager->refresh_token($context['token']);

            if ($refreshed) {
                return [
                    'success' => true,
                    'method' => 'token_refreshed',
                    'data' => $refreshed
                ];
            }
        }

        // Method 2: Request new token
        return [
            'success' => true,
            'method' => 'request_new_token',
            'action' => 'redirect_to_token_generation',
            'url' => '/dashboard/autofill-settings'
        ];
    }

    /**
     * Recovery: Network Error
     */
    private function recover_network_error($context)
    {
        // Method 1: Use cached data if available
        if (isset($context['user_id'])) {
            $cached_data = get_transient('sffc_user_profile_' . $context['user_id']);

            if ($cached_data) {
                return [
                    'success' => true,
                    'method' => 'cached_data',
                    'data' => $cached_data
                ];
            }
        }

        // Method 2: Offline mode
        return [
            'success' => true,
            'method' => 'offline_mode',
            'action' => 'enable_offline_mode',
            'message' => 'Network error detected. Working in offline mode.'
        ];
    }

    /**
     * Recovery: File Upload Failed
     */
    private function recover_file_upload($context)
    {
        $suggestions = [];

        // Check file size
        if (isset($context['file_size']) && $context['file_size'] > 10485760) {
            $suggestions[] = 'Reduce file size (max 10MB)';
        }

        // Check file type
        if (isset($context['file_type'])) {
            $allowed = ['pdf', 'doc', 'docx'];
            if (!in_array(strtolower($context['file_type']), $allowed)) {
                $suggestions[] = 'Use PDF, DOC, or DOCX format';
            }
        }

        return [
            'success' => true,
            'method' => 'upload_guidance',
            'action' => 'show_upload_help',
            'suggestions' => $suggestions,
            'alternative' => 'paste_text_content'
        ];
    }

    /**
     * Recovery: API Limit Exceeded
     */
    private function recover_api_limit($context)
    {
        // Method 1: Use fallback parsing
        return [
            'success' => true,
            'method' => 'fallback_parsing',
            'action' => 'use_pattern_matching',
            'message' => 'Using alternative parsing method'
        ];
    }

    /**
     * Recovery: Session Expired
     */
    private function recover_session_expired($context)
    {
        // Save current state
        if (isset($context['form_data'])) {
            set_transient(
                'sffc_saved_form_' . get_current_user_id(),
                $context['form_data'],
                3600
            );
        }

        return [
            'success' => true,
            'method' => 'session_recovery',
            'action' => 'restore_session',
            'saved_data' => true,
            'message' => 'Session restored. Your data has been saved.'
        ];
    }

    /**
     * Recovery: Form Validation Error
     */
    private function recover_form_validation($context)
    {
        $field_errors = isset($context['field_errors']) ? $context['field_errors'] : [];
        $corrections = [];

        foreach ($field_errors as $field => $error) {
            // Suggest corrections based on common issues
            if (strpos($error, 'email') !== false) {
                $corrections[$field] = 'Check email format (e.g., user@example.co)';
            } elseif (strpos($error, 'phone') !== false) {
                $corrections[$field] = 'Use format: (555) 123-4567';
            } elseif (strpos($error, 'required') !== false) {
                $corrections[$field] = 'This field is required';
            }
        }

        return [
            'success' => true,
            'method' => 'validation_guidance',
            'corrections' => $corrections,
            'action' => 'highlight_errors'
        ];
    }

    /**
     * Recovery: Browser Incompatible
     */
    private function recover_browser_issue($context)
    {
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $recommendations = [];

        // Check browser version
        if (strpos($user_agent, 'Chrome') !== false) {
            $recommendations[] = 'Update Chrome to latest version';
        } else {
            $recommendations[] = 'Use Google Chrome for best compatibility';
        }

        return [
            'success' => true,
            'method' => 'browser_recommendation',
            'recommendations' => $recommendations,
            'download_links' => [
                'chrome' => 'https://www.google.com/chrome/',
                'firefox' => 'https://www.mozilla.org/firefox/',
                'edge' => 'https://www.microsoft.com/edge'
            ]
        ];
    }

    /**
     * Get fallback solution
     */
    private function get_fallback_solution($error_code, $context)
    {
        $fallback_solutions = [
            'CV_PARSE_FAILED' => [
                'action' => 'manual_profile_entry',
                'message' => 'Please enter your profile information manually',
                'url' => '/dashboard/profile-builder'
            ],
            'PLATFORM_NOT_DETECTED' => [
                'action' => 'manual_field_mapping',
                'message' => 'Please map fields manually using the field mapper',
                'show_mapper' => true
            ],
            'FIELD_NOT_FOUND' => [
                'action' => 'skip_field',
                'message' => 'Field could not be filled automatically',
                'highlight' => true
            ],
            'TOKEN_INVALID' => [
                'action' => 'regenerate_token',
                'message' => 'Please generate a new access token',
                'url' => '/dashboard/autofill-settings'
            ],
            'NETWORK_ERROR' => [
                'action' => 'retry_later',
                'message' => 'Network issue detected. Please try again later',
                'retry_in' => 60
            ],
            'default' => [
                'action' => 'contact_support',
                'message' => 'An error occurred. Please contact support if the issue persists',
                'error_id' => uniqid('err_')
            ]
        ];

        $solution = isset($fallback_solutions[$error_code])
            ? $fallback_solutions[$error_code]
            : $fallback_solutions['default'];

        $solution['success'] = false;
        $solution['is_fallback'] = true;

        return $solution;
    }

    /**
     * Get alternative selectors for field
     */
    private function get_alternative_selectors($field_name)
    {
        $selector_map = [
            'name' => [
                '[name*="full_name"]',
                '[name*="fullname"]',
                '[placeholder*="full name"]',
                '[aria-label*="name"]',
                '.name-field',
                '#name'
            ],
            'email' => [
                '[type="email"]',
                '[name*="email"]',
                '[placeholder*="email"]',
                '[aria-label*="email"]',
                '.email-field',
                '#email'
            ],
            'phone' => [
                '[type="tel"]',
                '[name*="phone"]',
                '[name*="mobile"]',
                '[placeholder*="phone"]',
                '[aria-label*="phone"]',
                '.phone-field',
                '#phone'
            ],
            'resume' => [
                '[type="file"]',
                '[name*="resume"]',
                '[name*="cv"]',
                '[accept*="pdf"]',
                '.resume-upload',
                '#resume'
            ]
        ];

        return isset($selector_map[$field_name]) ? $selector_map[$field_name] : [];
    }

    /**
     * Get error type from code
     */
    private function get_error_type($error_code)
    {
        $type_map = [
            'CV_PARSE_FAILED' => 'parsing',
            'PLATFORM_NOT_DETECTED' => 'detection',
            'FIELD_NOT_FOUND' => 'field_mapping',
            'TOKEN_INVALID' => 'authentication',
            'NETWORK_ERROR' => 'network',
            'FILE_UPLOAD_FAILED' => 'upload',
            'API_LIMIT_EXCEEDED' => 'api',
            'SESSION_EXPIRED' => 'session',
            'FORM_VALIDATION_ERROR' => 'validation',
            'BROWSER_INCOMPATIBLE' => 'compatibility'
        ];

        return isset($type_map[$error_code]) ? $type_map[$error_code] : 'unknown';
    }

    /**
     * Mark recovery as successful
     */
    private function mark_recovery_successful($error_id, $method)
    {
        global $wpdb;

        $wpdb->update(
            $this->error_log_table,
            [
                'recovery_attempted' => 1,
                'recovery_successful' => 1,
                'recovery_method' => $method
            ],
            ['id' => $error_id]
        );
    }

    /**
     * AJAX: Report error
     */
    public function ajax_report_error()
    {
        $error_code = isset($_POST['error_code']) ? sanitize_text_field($_POST['error_code']) : 'UNKNOWN';
        $error_message = isset($_POST['error_message']) ? sanitize_text_field($_POST['error_message']) : '';
        $context = isset($_POST['context']) ? json_decode(stripslashes($_POST['context']), true) : [];

        $result = $this->handle_error($error_code, $error_message, $context);

        wp_send_json_success($result);
    }

    /**
     * AJAX: Attempt recovery
     */
    public function ajax_attempt_recovery()
    {
        $error_code = isset($_POST['error_code']) ? sanitize_text_field($_POST['error_code']) : '';
        $context = isset($_POST['context']) ? json_decode(stripslashes($_POST['context']), true) : [];

        if (empty($error_code)) {
            wp_send_json_error(['message' => 'Error code required']);
        }

        $result = $this->attempt_recovery($error_code, $context);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            $fallback = $this->get_fallback_solution($error_code, $context);
            wp_send_json_success($fallback);
        }
    }

    /**
     * AJAX: Get fallback solution
     */
    public function ajax_get_fallback_solution()
    {
        $error_code = isset($_POST['error_code']) ? sanitize_text_field($_POST['error_code']) : '';
        $context = isset($_POST['context']) ? json_decode(stripslashes($_POST['context']), true) : [];

        $solution = $this->get_fallback_solution($error_code, $context);

        wp_send_json_success($solution);
    }

    /**
     * Clean up old error logs
     */
    public function cleanup_old_logs()
    {
        global $wpdb;

        // Delete logs older than 30 days
        $wpdb->query(
            "DELETE FROM {$this->error_log_table} 
             WHERE occurred_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
    }

    /**
     * Get error statistics
     */
    public function get_error_stats($days = 7)
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                error_code,
                error_type,
                COUNT(*) as count,
                SUM(recovery_successful) as recovered,
                AVG(recovery_successful) * 100 as recovery_rate
             FROM {$this->error_log_table}
             WHERE occurred_at > DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY error_code, error_type
             ORDER BY count DESC",
            $days
        ), ARRAY_A);
    }
}

// Initialize
SFFC_Error_Recovery_System::get_instance();
