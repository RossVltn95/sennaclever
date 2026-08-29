<?php
/**
 * AJAX Error Handler
 * Handles common AJAX errors and provides better error messages
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Ajax_Error_Handler {
    
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
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Add nonce verification bypass for public endpoints
        add_filter('sffc_ajax_public_actions', array($this, 'get_public_actions'));
        
        // Add error handling
        add_action('wp_ajax_nopriv_sffc_handle_error', array($this, 'handle_frontend_error'));
        add_action('wp_ajax_sffc_handle_error', array($this, 'handle_frontend_error'));
        
        // Fix common AJAX issues
        add_action('init', array($this, 'fix_ajax_issues'), 1);
    }
    
    /**
     * Get list of public AJAX actions that don't require nonce
     */
    public function get_public_actions($actions) {
        $public_actions = array(
            'sffc_load_opportunities',
            'sffc_get_jobs',
            'sffc_search_jobs',
            'sffc_start_conversation',
            'sffc_send_message',
            'sffc_get_user_profile', // Allow profile fetching
            'sffc_check_auth_status',
            'sffc_get_applications' // Allow application fetching
        );
        
        return array_merge($actions, $public_actions);
    }
    
    /**
     * Fix common AJAX issues
     */
    public function fix_ajax_issues() {
        // Ensure AJAX URL is available
        if (!defined('ADMIN_AJAX_URL')) {
            define('ADMIN_AJAX_URL', admin_url('admin-ajax.php'));
        }
        
        // Fix nonce issues for logged-out users
        if (!is_user_logged_in()) {
            // Generate a public nonce for non-authenticated requests
            add_action('wp_enqueue_scripts', function() {
                wp_localize_script('jquery', 'sffc_public_ajax', array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('sffc_public_nonce')
                ));
            }, 5);
        }
    }
    
    /**
     * Handle frontend error reports
     */
    public function handle_frontend_error() {
        // Don't require nonce for error logging
        $error_type = isset($_POST['error_type']) ? sanitize_text_field($_POST['error_type']) : 'unknown';
        $message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : '';
        $context = isset($_POST['context']) ? $_POST['context'] : array();
        
        // Log the error
        error_log(sprintf(
            'SFFC Frontend Error [%s]: %s | Context: %s',
            $error_type,
            $message,
            json_encode($context)
        ));
        
        // Return success to prevent cascading errors
        wp_send_json_success(array(
            'logged' => true,
            'message' => 'Error logged successfully'
        ));
    }
    
    /**
     * Verify nonce with fallback
     */
    public static function verify_nonce_with_fallback($action = 'sffc_frontend_nonce') {
        // Check multiple possible nonce locations
        $nonce = null;
        
        // Check $_REQUEST first
        if (isset($_REQUEST['nonce'])) {
            $nonce = $_REQUEST['nonce'];
        } elseif (isset($_REQUEST['_ajax_nonce'])) {
            $nonce = $_REQUEST['_ajax_nonce'];
        } elseif (isset($_REQUEST['security'])) {
            $nonce = $_REQUEST['security'];
        }
        
        // If no nonce found, check if this is a public action
        $public_actions = apply_filters('sffc_ajax_public_actions', array());
        $current_action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
        
        if (in_array($current_action, $public_actions)) {
            return true; // Allow public actions without nonce
        }
        
        // If nonce exists, verify it
        if ($nonce) {
            // Try multiple nonce actions
            $nonce_actions = array(
                $action,
                'sffc_frontend_nonce',
                'sffc_public_nonce',
                'wp_rest'
            );
            
            foreach ($nonce_actions as $nonce_action) {
                if (wp_verify_nonce($nonce, $nonce_action)) {
                    return true;
                }
            }
        }
        
        // For logged-in users, be more lenient
        if (is_user_logged_in()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Send error response with proper status
     */
    public static function send_error($message = 'An error occurred', $data = null, $status_code = 500) {
        status_header($status_code);
        
        $response = array(
            'success' => false,
            'message' => $message
        );
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        wp_send_json($response);
    }
    
    /**
     * Send success response
     */
    public static function send_success($data = null, $message = '') {
        $response = array(
            'success' => true
        );
        
        if ($message) {
            $response['message'] = $message;
        }
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        wp_send_json_success($data);
    }
    
    /**
     * Check if request is AJAX
     */
    public static function is_ajax_request() {
        return defined('DOING_AJAX') && DOING_AJAX;
    }
    
    /**
     * Get safe request parameter
     */
    public static function get_request_param($key, $default = null, $sanitize = 'sanitize_text_field') {
        if (isset($_REQUEST[$key])) {
            if (is_callable($sanitize)) {
                return call_user_func($sanitize, $_REQUEST[$key]);
            }
            return $_REQUEST[$key];
        }
        return $default;
    }
}

// Initialize
SFFC_Ajax_Error_Handler::get_instance();