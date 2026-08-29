<?php
/**
 * Edge Case Handler
 * Handles boundary conditions and unusual scenarios
 * 
 * @package SennaCareers
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Edge_Case_Handler {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Configuration for limits and thresholds
     */
    private $limits = array(
        'max_query_length' => 5000,
        'max_message_length' => 10000,
        'max_conversation_messages' => 500,
        'max_visual_cards' => 10,
        'max_retries' => 3,
        'max_file_size' => 10485760, // 10MB
        'min_query_length' => 2,
        'rate_limit_requests' => 30,
        'rate_limit_window' => 60 // seconds
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
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Pre-process all AJAX requests
        add_filter('sffc_pre_process_request', array($this, 'handle_request_edge_cases'), 10, 2);
        
        // Pre-process responses
        add_filter('sffc_pre_send_response', array($this, 'handle_response_edge_cases'), 10, 2);
        
        // Handle file uploads
        add_filter('sffc_validate_file_upload', array($this, 'validate_file_upload'), 10, 2);
        
        // Handle rate limiting
        add_action('init', array($this, 'init_rate_limiting'));
    }
    
    /**
     * Handle request edge cases
     * 
     * @param array $request Request data
     * @param string $action Action being performed
     * @return array|WP_Error Modified request or WP_Error
     */
    public function handle_request_edge_cases($request, $action) {
        // Check for empty request
        if (empty($request)) {
            return new WP_Error('empty_request', 'Request cannot be empty');
        }
        
        // Handle query/message length
        if (isset($request['query'])) {
            $request['query'] = $this->handle_query_length($request['query']);
        }
        
        if (isset($request['message'])) {
            $request['message'] = $this->handle_message_length($request['message']);
        }
        
        // Handle special characters and encoding
        $request = $this->sanitize_request($request);
        
        // Check rate limiting
        if ($this->is_rate_limited()) {
            return new WP_Error('rate_limited', 'Too many requests. Please wait a moment.');
        }
        
        // Handle conversation overflow
        if (isset($request['conversation_id'])) {
            $request = $this->check_conversation_limits($request);
        }
        
        return $request;
    }
    
    /**
     * Handle query length edge cases
     * 
     * @param string $query Original query
     * @return string Processed query
     */
    private function handle_query_length($query) {
        // Handle empty query
        if (empty(trim($query))) {
            return 'How can I help you today?';
        }
        
        // Handle very short query
        if (strlen($query) < $this->limits['min_query_length']) {
            return $query . ' (please provide more details)';
        }
        
        // Handle very long query
        if (strlen($query) > $this->limits['max_query_length']) {
            // Truncate but keep complete sentences
            $query = $this->truncate_to_sentence($query, $this->limits['max_query_length']);
            
            // Log truncation
            if (class_exists('SFFC_Error_Handler')) {
                SFFC_Error_Handler::get_instance()->handle_error(
                    'query_truncated',
                    'Query exceeded maximum length and was truncated',
                    array('original_length' => strlen($query))
                );
            }
        }
        
        return $query;
    }
    
    /**
     * Handle message length edge cases
     * 
     * @param string $message Original message
     * @return string Processed message
     */
    private function handle_message_length($message) {
        // Similar to query but with different limits
        if (strlen($message) > $this->limits['max_message_length']) {
            $message = $this->truncate_to_sentence($message, $this->limits['max_message_length']);
        }
        
        return $message;
    }
    
    /**
     * Truncate text to complete sentence
     * 
     * @param string $text Text to truncate
     * @param int $max_length Maximum length
     * @return string Truncated text
     */
    private function truncate_to_sentence($text, $max_length) {
        if (strlen($text) <= $max_length) {
            return $text;
        }
        
        // Cut at max length
        $text = substr($text, 0, $max_length);
        
        // Find last complete sentence
        $last_period = strrpos($text, '.');
        $last_question = strrpos($text, '?');
        $last_exclamation = strrpos($text, '!');
        
        $last_sentence = max($last_period, $last_question, $last_exclamation);
        
        if ($last_sentence !== false) {
            $text = substr($text, 0, $last_sentence + 1);
        } else {
            // No sentence ending found, cut at last space
            $last_space = strrpos($text, ' ');
            if ($last_space !== false) {
                $text = substr($text, 0, $last_space) . '...';
            }
        }
        
        return $text;
    }
    
    /**
     * Sanitize request data
     * 
     * @param array $request Request data
     * @return array Sanitized request
     */
    private function sanitize_request($request) {
        foreach ($request as $key => $value) {
            if (is_string($value)) {
                // Remove null bytes
                $value = str_replace(chr(0), '', $value);
                
                // Handle different encodings
                if (!mb_check_encoding($value, 'UTF-8')) {
                    $value = mb_convert_encoding($value, 'UTF-8', 'auto');
                }
                
                // Remove control characters except newlines and tabs
                $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
                
                // Normalize line endings
                $value = str_replace(array("\r\n", "\r"), "\n", $value);
                
                $request[$key] = $value;
            } elseif (is_array($value)) {
                $request[$key] = $this->sanitize_request($value);
            }
        }
        
        return $request;
    }
    
    /**
     * Check if user is rate limited
     * 
     * @return bool True if rate limited
     */
    private function is_rate_limited() {
        $user_id = get_current_user_id();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $key = 'sffc_rate_limit_' . md5($user_id . '_' . $ip);
        
        $requests = get_transient($key);
        
        if ($requests === false) {
            // First request
            set_transient($key, 1, $this->limits['rate_limit_window']);
            return false;
        }
        
        if ($requests >= $this->limits['rate_limit_requests']) {
            return true;
        }
        
        // Increment counter
        set_transient($key, $requests + 1, $this->limits['rate_limit_window']);
        
        return false;
    }
    
    /**
     * Check conversation limits
     * 
     * @param array $request Request data
     * @return array Modified request
     */
    private function check_conversation_limits($request) {
        global $wpdb;
        
        $conversation_id = intval($request['conversation_id']);
        
        // Check message count
        $table = $wpdb->prefix . 'sffc_messages';
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE conversation_id = %d",
            $conversation_id
        ));
        
        if ($count >= $this->limits['max_conversation_messages']) {
            // Start a new conversation
            $request['start_new_conversation'] = true;
            $request['reason'] = 'Conversation limit reached';
        }
        
        return $request;
    }
    
    /**
     * Handle response edge cases
     * 
     * @param array $response Response data
     * @param string $action Action being performed
     * @return array Modified response
     */
    public function handle_response_edge_cases($response, $action) {
        // Handle empty response
        if (empty($response)) {
            $response = array(
                'success' => false,
                'message' => 'No response generated. Please try again.'
            );
        }
        
        // Handle missing success flag
        if (!isset($response['success'])) {
            $response['success'] = !empty($response['message']) || !empty($response['html']);
        }
        
        // Handle overly large responses
        if (isset($response['message']) && strlen($response['message']) > 50000) {
            $response['message'] = $this->truncate_to_sentence($response['message'], 50000);
            $response['truncated'] = true;
        }
        
        // Handle too many visual cards
        if (isset($response['visuals']) && count($response['visuals']) > $this->limits['max_visual_cards']) {
            $response['visuals'] = array_slice($response['visuals'], 0, $this->limits['max_visual_cards']);
            $response['visuals_truncated'] = true;
        }
        
        // Ensure proper encoding
        $response = $this->ensure_utf8_encoding($response);
        
        return $response;
    }
    
    /**
     * Ensure UTF-8 encoding for response
     * 
     * @param mixed $data Data to encode
     * @return mixed UTF-8 encoded data
     */
    private function ensure_utf8_encoding($data) {
        if (is_string($data)) {
            if (!mb_check_encoding($data, 'UTF-8')) {
                return mb_convert_encoding($data, 'UTF-8', 'auto');
            }
            return $data;
        } elseif (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->ensure_utf8_encoding($value);
            }
            return $data;
        }
        
        return $data;
    }
    
    /**
     * Validate file upload
     * 
     * @param array $file File data
     * @param string $type Expected file type
     * @return bool|WP_Error
     */
    public function validate_file_upload($file, $type = 'document') {
        // Check file size
        if ($file['size'] > $this->limits['max_file_size']) {
            return new WP_Error('file_too_large', 
                sprintf('File size exceeds maximum of %s MB', 
                    $this->limits['max_file_size'] / 1048576));
        }
        
        // Check file type
        $allowed_types = array(
            'document' => array('pdf', 'doc', 'docx', 'txt'),
            'image' => array('jpg', 'jpeg', 'png', 'gif'),
            'spreadsheet' => array('xls', 'xlsx', 'csv')
        );
        
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($extension, $allowed_types[$type])) {
            return new WP_Error('invalid_file_type', 
                'File type not allowed. Allowed types: ' . implode(', ', $allowed_types[$type]));
        }
        
        // Check for malicious content
        if ($this->contains_malicious_content($file['tmp_name'])) {
            return new WP_Error('malicious_content', 'File contains potentially malicious content');
        }
        
        return true;
    }
    
    /**
     * Check for malicious content in file
     * 
     * @param string $filepath Path to file
     * @return bool True if malicious content detected
     */
    private function contains_malicious_content($filepath) {
        $content = file_get_contents($filepath, false, null, 0, 1024); // Check first 1KB
        
        // Check for PHP tags
        if (strpos($content, '<?php') !== false) {
            return true;
        }
        
        // Check for script tags
        if (preg_match('/<script[^>]*>/i', $content)) {
            return true;
        }
        
        // Check for common web shells
        $suspicious_patterns = array(
            'eval\s*\(',
            'base64_decode',
            'system\s*\(',
            'exec\s*\(',
            'shell_exec'
        );
        
        foreach ($suspicious_patterns as $pattern) {
            if (preg_match('/' . $pattern . '/i', $content)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Initialize rate limiting cleanup
     */
    public function init_rate_limiting() {
        if (!wp_next_scheduled('sffc_cleanup_rate_limits')) {
            wp_schedule_event(time(), 'hourly', 'sffc_cleanup_rate_limits');
        }
        
        add_action('sffc_cleanup_rate_limits', array($this, 'cleanup_expired_rate_limits'));
    }
    
    /**
     * Clean up expired rate limit transients
     */
    public function cleanup_expired_rate_limits() {
        global $wpdb;
        
        // Clean up expired transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_timeout_sffc_rate_limit_%' 
             AND option_value < UNIX_TIMESTAMP()"
        );
    }
    
    /**
     * Handle database connection errors
     * 
     * @return bool True if database is available
     */
    public function check_database_connection() {
        global $wpdb;
        
        // Check if we can perform a simple query
        $result = $wpdb->get_var("SELECT 1");
        
        if ($result !== '1') {
            // Log error and use fallback
            if (class_exists('SFFC_Error_Handler')) {
                SFFC_Error_Handler::get_instance()->handle_error(
                    'database_connection_error',
                    'Database connection test failed',
                    array('wpdb_error' => $wpdb->last_error)
                );
            }
            
            return false;
        }
        
        return true;
    }
    
    /**
     * Handle memory limit issues
     * 
     * @return bool True if memory is adequate
     */
    public function check_memory_usage() {
        $memory_limit = ini_get('memory_limit');
        $memory_limit_bytes = wp_convert_hr_to_bytes($memory_limit);
        $memory_usage = memory_get_usage(true);
        
        // If we're using more than 80% of memory, trigger cleanup
        if ($memory_usage > ($memory_limit_bytes * 0.8)) {
            // Clear caches
            if (class_exists('SFFC_Cache_Manager')) {
                SFFC_Cache_Manager::get_instance()->clear_all_cache();
            }
            
            // Trigger garbage collection
            gc_collect_cycles();
            
            // Check again
            $memory_usage = memory_get_usage(true);
            
            if ($memory_usage > ($memory_limit_bytes * 0.9)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get current limits configuration
     * 
     * @return array Current limits
     */
    public function get_limits() {
        return apply_filters('sffc_edge_case_limits', $this->limits);
    }
    
    /**
     * Update a specific limit
     * 
     * @param string $key Limit key
     * @param mixed $value New value
     */
    public function update_limit($key, $value) {
        if (isset($this->limits[$key])) {
            $this->limits[$key] = $value;
        }
    }
}