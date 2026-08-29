<?php
/**
 * Response Formatter Implementation
 * Implements the SFFC_Response_Format interface for consistent response formatting
 * 
 * @package SennaCareers
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Include the interface
if (file_exists(SFFC_PLUGIN_DIR . 'includes/interfaces/interface-response-format.php')) {
    require_once SFFC_PLUGIN_DIR . 'includes/interfaces/interface-response-format.php';
}

class SFFC_Response_Formatter implements SFFC_Response_Format {
    
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor
     */
    private function __construct() {
        // Initialize formatter
    }
    
    /**
     * Format a successful response
     * 
     * @param string $message Response message
     * @param array|null $visual Visual card data
     * @param array $metadata Additional metadata
     * @return array Standardized response
     */
    public function format_success($message, $visual = null, $metadata = array()) {
        $response = array(
            'success' => true,
            'message' => $message,
            'response' => $message, // Include both for compatibility
            'timestamp' => current_time('mysql')
        );
        
        if (!empty($visual)) {
            $response['visual'] = $visual;
            $response['visual_cards'] = is_array($visual) ? array($visual) : array();
        }
        
        if (!empty($metadata)) {
            $response['metadata'] = $metadata;
        }
        
        // Add default metadata if not present
        if (!isset($response['metadata']['typing_delay'])) {
            $response['metadata']['typing_delay'] = 100;
        }
        
        if (!isset($response['metadata']['source'])) {
            $response['metadata']['source'] = 'formatter';
        }
        
        return $response;
    }
    
    /**
     * Format an error response
     * 
     * @param string $error_message Error message
     * @param string $error_code Error code
     * @param bool $user_friendly Whether to show user-friendly message
     * @return array Standardized error response
     */
    public function format_error($error_message, $error_code = 'generic_error', $user_friendly = true) {
        $response = array(
            'success' => false,
            'error' => true,
            'error_code' => $error_code,
            'timestamp' => current_time('mysql')
        );
        
        if ($user_friendly) {
            // Provide user-friendly error messages
            $friendly_messages = array(
                'api_error' => 'Our AI service is temporarily unavailable. Please try again in a moment.',
                'database_error' => 'We encountered a technical issue. Please refresh and try again.',
                'validation_error' => 'Please check your input and try again.',
                'session_error' => 'Your session has expired. Please refresh the page.',
                'generic_error' => 'Something went wrong. Please try again.'
            );
            
            $response['message'] = isset($friendly_messages[$error_code]) 
                ? $friendly_messages[$error_code] 
                : $friendly_messages['generic_error'];
            
            // Log the actual error for debugging
            error_log('SFFC Error [' . $error_code . ']: ' . $error_message);
        } else {
            $response['message'] = $error_message;
        }
        
        // Always include both message and response for compatibility
        $response['response'] = $response['message'];
        
        return $response;
    }
    
    /**
     * Format a response with visual cards
     * 
     * @param string $message Response message
     * @param array $cards Array of visual cards
     * @param array $metadata Additional metadata
     * @return array Formatted response with visual cards
     */
    public function format_with_cards($message, $cards = array(), $metadata = array()) {
        $response = $this->format_success($message, null, $metadata);
        
        if (!empty($cards)) {
            $response['visual_cards'] = $cards;
        }
        
        return $response;
    }
    
    /**
     * Format a template response
     * 
     * @param string $template_key Template identifier
     * @param array $variables Variables to inject into template
     * @param string $mode Current mode
     * @return array Formatted template response
     */
    public function format_template($template_key, $variables = array(), $mode = 'career') {
        // Get template content (simplified for now)
        $templates = $this->get_templates();
        
        $message = isset($templates[$template_key]) 
            ? $templates[$template_key] 
            : 'I understand your question. Let me help you with that.';
        
        // Replace variables in template
        foreach ($variables as $key => $value) {
            $message = str_replace('{{' . $key . '}}', $value, $message);
        }
        
        return $this->format_success($message, null, array(
            'source' => 'template',
            'template_key' => $template_key,
            'mode' => $mode
        ));
    }
    
    /**
     * Get response templates
     * 
     * @return array Templates
     */
    private function get_templates() {
        return array(
            'greeting' => 'Hello! How can I assist you today?',
            'acknowledgment' => 'Understood. What would you like to explore next?',
            'help_request' => 'I\'m here to help! You can ask me about finance careers, market insights, or investment opportunities.',
            'market_overview' => 'Let me provide you with the current market overview.',
            'career_guidance' => 'I can help guide you through your finance career journey.',
            'fallback' => 'I\'m processing your request. Please note that complex queries may take a moment.'
        );
    }
}