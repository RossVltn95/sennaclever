<?php
/**
 * Centralized Error Handler
 * Provides consistent error handling across the plugin
 * 
 * @package SennaCareers
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Error_Handler {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Error log file
     */
    private $log_file;
    
    /**
     * Error types
     */
    const ERROR_API = 'api_error';
    const ERROR_DATABASE = 'db_error';
    const ERROR_VALIDATION = 'validation_error';
    const ERROR_PERMISSION = 'permission_error';
    const ERROR_NETWORK = 'network_error';
    const ERROR_TIMEOUT = 'timeout_error';
    const ERROR_RATE_LIMIT = 'rate_limit_error';
    const ERROR_GENERAL = 'general_error';
    
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
        $upload_dir = wp_upload_dir();
        $this->log_file = $upload_dir['basedir'] . '/sffc-error.log';
        
        // Set up error handling
        $this->setup_error_handling();
    }
    
    /**
     * Set up WordPress error handling
     */
    private function setup_error_handling() {
        // Add custom error handler for AJAX requests
        add_action('wp_ajax_sffc_log_error', array($this, 'ajax_log_error'));
        add_action('wp_ajax_nopriv_sffc_log_error', array($this, 'ajax_log_error'));
    }
    
    /**
     * Handle error based on type
     * 
     * @param string $error_type Error type constant
     * @param string $message Error message
     * @param array $context Additional context
     * @param bool $log_to_file Whether to log to file
     * @return array Formatted error response
     */
    public function handle_error($error_type, $message, $context = array(), $log_to_file = true) {
        // Get user-friendly message
        $user_message = $this->get_user_message($error_type, $message);
        
        // Log error if requested
        if ($log_to_file) {
            $this->log_error($error_type, $message, $context);
        }
        
        // Get recovery suggestion
        $recovery = $this->get_recovery_suggestion($error_type);
        
        // Format response
        $formatter = null;
        if (class_exists('SFFC_Response_Formatter')) {
            require_once SFFC_PLUGIN_DIR . 'includes/interfaces/interface-response-format.php';
            $formatter = SFFC_Response_Formatter::get_instance();
            return $formatter->format_error($user_message, $error_type, true);
        }
        
        // Fallback format if formatter not available
        return array(
            'success' => false,
            'error' => array(
                'code' => $error_type,
                'message' => $user_message,
                'technical_message' => $message,
                'recovery' => $recovery,
                'timestamp' => current_time('mysql')
            )
        );
    }
    
    /**
     * Get user-friendly error message
     * 
     * @param string $error_type Error type
     * @param string $technical_message Technical error message
     * @return string User-friendly message
     */
    private function get_user_message($error_type, $technical_message) {
        switch ($error_type) {
            case self::ERROR_API:
                return "I'm having trouble connecting to my AI service. I'll use cached insights while we reconnect.";
                
            case self::ERROR_DATABASE:
                return "There was an issue saving your conversation. Don't worry, you can continue chatting.";
                
            case self::ERROR_VALIDATION:
                return "I couldn't process that request. Could you try rephrasing it?";
                
            case self::ERROR_PERMISSION:
                return "You don't have permission for that action. Please contact your administrator.";
                
            case self::ERROR_NETWORK:
                return "Network connection issue detected. Please check your internet connection.";
                
            case self::ERROR_TIMEOUT:
                return "The request is taking longer than expected. Let me try a faster approach.";
                
            case self::ERROR_RATE_LIMIT:
                return "We're receiving too many requests. Please wait a moment and try again.";
                
            default:
                return "Something unexpected happened. Let me try a different approach.";
        }
    }
    
    /**
     * Get recovery suggestion based on error type
     * 
     * @param string $error_type Error type
     * @return string Recovery suggestion
     */
    private function get_recovery_suggestion($error_type) {
        switch ($error_type) {
            case self::ERROR_API:
                return "Try refreshing the page or waiting a few moments.";
                
            case self::ERROR_DATABASE:
                return "Your conversation is still active. Continue chatting normally.";
                
            case self::ERROR_VALIDATION:
                return "Make sure your message is clear and try again.";
                
            case self::ERROR_PERMISSION:
                return "Log in with appropriate credentials or contact support.";
                
            case self::ERROR_NETWORK:
                return "Check your internet connection and refresh the page.";
                
            case self::ERROR_TIMEOUT:
                return "The system will automatically use a faster method.";
                
            case self::ERROR_RATE_LIMIT:
                return "Wait 30 seconds before trying again.";
                
            default:
                return "Refresh the page or try again in a moment.";
        }
    }
    
    /**
     * Log error to file
     * 
     * @param string $error_type Error type
     * @param string $message Error message
     * @param array $context Additional context
     */
    private function log_error($error_type, $message, $context = array()) {
        if (!$this->should_log()) {
            return;
        }
        
        $log_entry = sprintf(
            "[%s] [%s] %s | Context: %s\n",
            current_time('mysql'),
            $error_type,
            $message,
            json_encode($context)
        );
        
        // Limit log file size (1MB)
        if (file_exists($this->log_file) && filesize($this->log_file) > 1048576) {
            $this->rotate_log();
        }
        
        // Write to log
        error_log($log_entry, 3, $this->log_file);
    }
    
    /**
     * Check if logging should be enabled
     * 
     * @return bool
     */
    private function should_log() {
        // Check if debug mode is enabled
        if (!empty($_ENV['WP_DEBUG'])) {
            return true;
        }
        
        // Check plugin-specific setting
        $debug_mode = get_option('sffc_debug_mode', false);
        return (bool) $debug_mode;
    }
    
    /**
     * Rotate log file when it gets too large
     */
    private function rotate_log() {
        if (file_exists($this->log_file)) {
            $backup = $this->log_file . '.' . date('Y-m-d-H-i-s');
            rename($this->log_file, $backup);
            
            // Keep only last 5 backups
            $this->cleanup_old_logs();
        }
    }
    
    /**
     * Clean up old log files
     */
    private function cleanup_old_logs() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'];
        $logs = glob($log_dir . '/sffc-error.log.*');
        
        if (count($logs) > 5) {
            // Sort by modification time
            usort($logs, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            // Remove oldest logs
            $to_remove = count($logs) - 5;
            for ($i = 0; $i < $to_remove; $i++) {
                unlink($logs[$i]);
            }
        }
    }
    
    /**
     * AJAX handler for frontend error logging
     */
    public function ajax_log_error() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sffc_frontend_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }
        
        $error_type = isset($_POST['error_type']) ? sanitize_text_field($_POST['error_type']) : self::ERROR_GENERAL;
        $message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : 'Unknown error';
        $context = isset($_POST['context']) ? json_decode(stripslashes($_POST['context']), true) : array();
        
        // Add frontend context
        $context['source'] = 'frontend';
        $context['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
        $context['url'] = wp_get_referer();
        
        // Log the error
        $this->log_error($error_type, $message, $context);
        
        wp_send_json_success(array(
            'message' => 'Error logged successfully'
        ));
    }
    
    /**
     * Handle API errors specifically
     * 
     * @param array $api_response API response
     * @return array Formatted error or fallback response
     */
    public function handle_api_error($api_response) {
        // Check for common API error patterns
        if (isset($api_response['error'])) {
            $error_code = $api_response['error']['code'] ?? 'unknown';
            $error_message = $api_response['error']['message'] ?? 'API error occurred';
            
            // Handle specific API errors
            if (strpos($error_code, 'rate_limit') !== false) {
                return $this->handle_error(self::ERROR_RATE_LIMIT, $error_message);
            }
            
            if (strpos($error_code, 'timeout') !== false) {
                return $this->handle_error(self::ERROR_TIMEOUT, $error_message);
            }
            
            if (strpos($error_code, 'authentication') !== false) {
                return $this->handle_error(self::ERROR_PERMISSION, $error_message);
            }
        }
        
        // Generic API error
        return $this->handle_error(self::ERROR_API, 'API request failed', $api_response);
    }
    
    /**
     * Get error statistics for monitoring
     * 
     * @param int $hours Number of hours to look back
     * @return array Error statistics
     */
    public function get_error_stats($hours = 24) {
        if (!file_exists($this->log_file)) {
            return array(
                'total_errors' => 0,
                'error_types' => array(),
                'error_rate' => 0
            );
        }
        
        $stats = array(
            'total_errors' => 0,
            'error_types' => array(),
            'errors_by_hour' => array()
        );
        
        $cutoff_time = strtotime("-{$hours} hours");
        $lines = file($this->log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Parse log entry
            if (preg_match('/\[([^\]]+)\] \[([^\]]+)\]/', $line, $matches)) {
                $timestamp = strtotime($matches[1]);
                $error_type = $matches[2];
                
                if ($timestamp >= $cutoff_time) {
                    $stats['total_errors']++;
                    
                    if (!isset($stats['error_types'][$error_type])) {
                        $stats['error_types'][$error_type] = 0;
                    }
                    $stats['error_types'][$error_type]++;
                    
                    $hour = date('Y-m-d H:00:00', $timestamp);
                    if (!isset($stats['errors_by_hour'][$hour])) {
                        $stats['errors_by_hour'][$hour] = 0;
                    }
                    $stats['errors_by_hour'][$hour]++;
                }
            }
        }
        
        $stats['error_rate'] = $stats['total_errors'] / $hours;
        
        return $stats;
    }
}