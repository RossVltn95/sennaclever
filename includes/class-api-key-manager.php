<?php
/**
 * Centralized API Key Manager
 * Single source of truth for API key management
 * 
 * @package SennaCareers
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_API_Key_Manager {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Cached API key
     */
    private $api_key = null;
    
    /**
     * API key validation status
     */
    private $is_validated = false;
    
    /**
     * Validation result cache
     */
    private $validation_result = null;
    
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
     * Encryption salt
     */
    private $encryption_salt = null;
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_encryption();
        $this->load_api_key();
    }
    
    /**
     * Initialize encryption
     */
    private function init_encryption() {
        // Use WordPress salts for encryption
        if (defined('AUTH_KEY') && defined('AUTH_SALT')) {
            $this->encryption_salt = substr(md5(AUTH_KEY . AUTH_SALT), 0, 16);
        } else {
            // Generate and store a unique salt
            $stored_salt = get_option('sffc_encryption_salt');
            if (!$stored_salt) {
                $stored_salt = wp_generate_password(32, true, true);
                update_option('sffc_encryption_salt', $stored_salt);
            }
            $this->encryption_salt = substr(md5($stored_salt), 0, 16);
        }
    }
    
    /**
     * Load API key from database
     */
    private function load_api_key() {
        // Try encrypted key first
        $encrypted = get_option('sffc_encrypted_api_key', '');
        if ($encrypted) {
            $decrypted = $this->decrypt_key($encrypted);
            // Only use decrypted key if it looks valid (not empty/corrupted)
            if (!empty($decrypted) && strlen($decrypted) > 10) {
                $this->api_key = $decrypted;
                return;
            }
            // Decryption failed - log and try fallback
            error_log('SFFC API Key Manager: Decryption failed, trying fallback');
        }

        // Fall back to unencrypted key
        $unencrypted = get_option('sffc_api_key', '');
        if ($unencrypted) {
            $this->api_key = $unencrypted;
            // Note: We no longer auto-migrate and delete.
            // The unencrypted key stays as a reliable fallback.
        }
    }
    
    /**
     * Encrypt a key
     */
    private function encrypt_key($key) {
        if (empty($key)) {
            return '';
        }
        
        // Use openssl if available
        if (function_exists('openssl_encrypt')) {
            $method = 'AES-256-CBC';
            $key_hash = hash('sha256', $this->encryption_salt);
            $iv = substr(hash('sha256', $this->encryption_salt . 'iv'), 0, 16);
            
            $encrypted = openssl_encrypt($key, $method, $key_hash, 0, $iv);
            return base64_encode($encrypted);
        }
        
        // Fallback to base64 encoding with obfuscation
        return base64_encode(str_rot13($key));
    }
    
    /**
     * Decrypt a key
     */
    private function decrypt_key($encrypted) {
        if (empty($encrypted)) {
            return '';
        }
        
        // Try openssl decryption
        if (function_exists('openssl_decrypt')) {
            $method = 'AES-256-CBC';
            $key_hash = hash('sha256', $this->encryption_salt);
            $iv = substr(hash('sha256', $this->encryption_salt . 'iv'), 0, 16);
            
            $decoded = base64_decode($encrypted);
            $decrypted = openssl_decrypt($decoded, $method, $key_hash, 0, $iv);
            if ($decrypted !== false) {
                return $decrypted;
            }
        }
        
        // Try simple obfuscation reversal
        $decoded = base64_decode($encrypted);
        return str_rot13($decoded);
    }
    
    /**
     * Get API key
     * 
     * @return string API key or empty string
     */
    public function get_api_key() {
        if ($this->api_key === null) {
            $this->load_api_key();
        }
        return $this->api_key;
    }
    
    /**
     * Check if API key is configured
     * 
     * @return bool True if API key exists
     */
    public function has_api_key() {
        return !empty($this->get_api_key());
    }
    
    /**
     * Validate API key format
     * 
     * @param string $api_key Optional key to validate
     * @return bool True if valid format
     */
    public function validate_format($api_key = null) {
        if ($api_key === null) {
            $api_key = $this->get_api_key();
        }
        
        // Claude API keys should start with 'sk-ant-' 
        return !empty($api_key) && (
            strpos($api_key, 'sk-ant-') === 0 ||
            strlen($api_key) > 20 // Legacy format support
        );
    }
    
    /**
     * Test API key with Claude
     * 
     * @param string $api_key Optional key to test
     * @return array Test result
     */
    public function test_api_key($api_key = null) {
        if ($api_key === null) {
            $api_key = $this->get_api_key();
        }
        
        if (empty($api_key)) {
            return array(
                'success' => false,
                'message' => 'No API key configured',
                'code' => 'no_key'
            );
        }
        
        // Cache validation for 5 minutes
        if ($this->is_validated && $this->validation_result) {
            $cache_time = isset($this->validation_result['timestamp']) ? $this->validation_result['timestamp'] : 0;
            if (time() - $cache_time < 300) {
                return $this->validation_result;
            }
        }
        
        // Test with Claude API
        $test_response = wp_remote_post('https://api.anthropic.com/v1/messages', array(
            'headers' => array(
                'x-api-key' => $api_key,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json'
            ),
            'body' => json_encode(array(
                'model' => 'claude-3-haiku-20240307',
                'max_tokens' => 10,
                'messages' => array(
                    array(
                        'role' => 'user',
                        'content' => 'Test'
                    )
                )
            )),
            'timeout' => 10
        ));
        
        if (is_wp_error($test_response)) {
            $result = array(
                'success' => false,
                'message' => 'Connection error: ' . $test_response->get_error_message(),
                'code' => 'connection_error'
            );
        } else {
            $code = wp_remote_retrieve_response_code($test_response);
            if ($code === 200 || $code === 201) {
                $result = array(
                    'success' => true,
                    'message' => 'API key is valid',
                    'code' => 'valid'
                );
            } elseif ($code === 401) {
                $result = array(
                    'success' => false,
                    'message' => 'Invalid API key',
                    'code' => 'invalid_key'
                );
            } elseif ($code === 429) {
                $result = array(
                    'success' => false,
                    'message' => 'Rate limit exceeded',
                    'code' => 'rate_limit'
                );
            } else {
                $result = array(
                    'success' => false,
                    'message' => 'API error (Code: ' . $code . ')',
                    'code' => 'api_error'
                );
            }
        }
        
        // Cache result
        $result['timestamp'] = time();
        $this->validation_result = $result;
        $this->is_validated = true;
        
        return $result;
    }
    
    /**
     * Save API key (encrypted)
     *
     * @param string $api_key API key to save
     * @return bool Success
     */
    public function save_api_key($api_key) {
        $api_key = sanitize_text_field($api_key);

        // Validate format before saving
        if (!empty($api_key) && !$this->validate_format($api_key)) {
            return false;
        }

        // Save to both encrypted AND unencrypted for reliability
        // The unencrypted key serves as a reliable fallback
        update_option('sffc_api_key', $api_key);

        // Also save encrypted version
        $encrypted = $this->encrypt_key($api_key);
        $result = update_option('sffc_encrypted_api_key', $encrypted);

        if ($result) {
            $this->api_key = $api_key;
            $this->is_validated = false;
            $this->validation_result = null;
        }

        return $result;
    }
    
    /**
     * Get immediate fallback response when no API key
     * 
     * @return array Fallback response
     */
    public function get_no_key_fallback() {
        return array(
            'success' => false,
            'message' => 'Claude API integration requires an API key. Please configure it in the admin settings.',
            'fallback' => true,
            'code' => 'no_api_key'
        );
    }
    
    /**
     * Check if should use fallback immediately
     * 
     * @return bool True if should skip API call
     */
    public function should_use_fallback() {
        return !$this->has_api_key();
    }
    
    /**
     * Clear validation cache
     */
    public function clear_cache() {
        $this->is_validated = false;
        $this->validation_result = null;
        $this->api_key = null;
        $this->load_api_key();
    }
}

// Initialize the manager
SFFC_API_Key_Manager::get_instance();