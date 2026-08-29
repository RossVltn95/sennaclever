<?php
/**
 * Standard Response Format Interface
 * Ensures consistent response structure across all API handlers
 * 
 * @package SennaCareers
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

interface SFFC_Response_Format {
    
    /**
     * Format a successful response
     * 
     * @param string $message Response message
     * @param array|null $visual Visual card data
     * @param array $metadata Additional metadata
     * @return array Standardized response
     */
    public function format_success($message, $visual = null, $metadata = array());
    
    /**
     * Format an error response
     * 
     * @param string $error_message Error message
     * @param string $error_code Error code
     * @param bool $use_fallback Whether to use fallback
     * @return array Standardized error response
     */
    public function format_error($error_message, $error_code = 'error', $use_fallback = false);
}

/**
 * Standard Response Formatter Implementation
 */
class SFFC_Response_Formatter implements SFFC_Response_Format {
    
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
            'data' => array(
                'message' => $message,
                'visual' => $visual,
                'metadata' => wp_parse_args($metadata, array(
                    'typing_delay' => $this->calculate_typing_delay($message),
                    'follow_up' => null,
                    'mode' => 'general',
                    'timestamp' => current_time('mysql')
                ))
            ),
            'error' => null
        );
        
        return $response;
    }
    
    /**
     * Format an error response
     * 
     * @param string $error_message Error message
     * @param string $error_code Error code
     * @param bool $use_fallback Whether to use fallback
     * @return array Standardized error response
     */
    public function format_error($error_message, $error_code = 'error', $use_fallback = false) {
        return array(
            'success' => false,
            'data' => null,
            'error' => array(
                'message' => $error_message,
                'code' => $error_code,
                'fallback' => $use_fallback,
                'timestamp' => current_time('mysql')
            )
        );
    }
    
    /**
     * Format a fallback response
     * 
     * @param string $message Fallback message
     * @param array|null $visual Visual card data
     * @return array Standardized fallback response
     */
    public function format_fallback($message, $visual = null) {
        return array(
            'success' => true,
            'data' => array(
                'message' => $message,
                'visual' => $visual,
                'metadata' => array(
                    'typing_delay' => $this->calculate_typing_delay($message),
                    'is_fallback' => true,
                    'timestamp' => current_time('mysql')
                )
            ),
            'error' => null
        );
    }
    
    /**
     * Convert legacy response format to standard format
     * 
     * @param array $legacy_response Legacy response
     * @return array Standardized response
     */
    public function convert_legacy_format($legacy_response) {
        // Handle different legacy formats
        if (isset($legacy_response['success'])) {
            if ($legacy_response['success']) {
                // Legacy success format
                return $this->format_success(
                    $legacy_response['message'] ?? '',
                    $legacy_response['visual'] ?? null,
                    array(
                        'typing_delay' => $legacy_response['typing_delay'] ?? null,
                        'mode' => $legacy_response['mode'] ?? 'general'
                    )
                );
            } else {
                // Legacy error format
                return $this->format_error(
                    $legacy_response['message'] ?? 'Unknown error',
                    $legacy_response['code'] ?? 'error',
                    $legacy_response['fallback'] ?? false
                );
            }
        }
        
        // Handle Claude Market API format
        if (isset($legacy_response['message']) && !isset($legacy_response['success'])) {
            return $this->format_success(
                $legacy_response['message'],
                $legacy_response['visual'] ?? null,
                array(
                    'typing_delay' => $legacy_response['typing_delay'] ?? null
                )
            );
        }
        
        // Unknown format - treat as error
        return $this->format_error('Invalid response format', 'format_error');
    }
    
    /**
     * Calculate typing delay based on message length
     * 
     * @param string $message Message text
     * @return int Typing delay in milliseconds
     */
    private function calculate_typing_delay($message) {
        if (empty($message)) {
            return 0;
        }
        
        $word_count = str_word_count($message);
        // 50ms per word, min 500ms, max 3000ms
        $delay = max(500, min(3000, $word_count * 50));
        
        return $delay;
    }
    
    /**
     * Validate response format
     * 
     * @param array $response Response to validate
     * @return bool True if valid format
     */
    public function validate_format($response) {
        // Check required top-level keys
        if (!isset($response['success']) || !is_bool($response['success'])) {
            return false;
        }
        
        if ($response['success']) {
            // Success response must have data
            if (!isset($response['data']) || !is_array($response['data'])) {
                return false;
            }
            
            // Data must have message
            if (!isset($response['data']['message'])) {
                return false;
            }
        } else {
            // Error response must have error
            if (!isset($response['error']) || !is_array($response['error'])) {
                return false;
            }
            
            // Error must have message and code
            if (!isset($response['error']['message']) || !isset($response['error']['code'])) {
                return false;
            }
        }
        
        return true;
    }
}