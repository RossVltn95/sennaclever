<?php

/**
 * Token Manager for AutoFill Extension Authentication
 * 
 * Handles token generation, validation, and management for Chrome extension
 * 
 * @package MENA Careers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Token_Manager
{

    /**
     * Instance
     */
    private static $instance = null;

    /**
     * Table name
     */
    private $table_name;

    /**
     * Token expiry days
     */
    private $token_expiry_days = 30;

    /**
     * Maximum tokens per user
     */
    private $max_tokens_per_user = 5;

    /**
     * Constructor
     */
    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'sffc_autofill_tokens';

        $this->init_hooks();
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
     * Initialize hooks
     */
    private function init_hooks()
    {
        // AJAX endpoints
        add_action('wp_ajax_sffc_generate_token', [$this, 'ajax_generate_token']);
        add_action('wp_ajax_sffc_validate_token', [$this, 'ajax_validate_token']);
        add_action('wp_ajax_sffc_revoke_token', [$this, 'ajax_revoke_token']);
        add_action('wp_ajax_sffc_list_tokens', [$this, 'ajax_list_tokens']);
        add_action('wp_ajax_sffc_refresh_token', [$this, 'ajax_refresh_token']);

        // No-priv actions for extension validation
        add_action('wp_ajax_nopriv_sffc_validate_extension_token', [$this, 'ajax_validate_extension_token']);

        // Cleanup cron
        add_action('sffc_cleanup_expired_tokens', [$this, 'cleanup_expired_tokens']);

        // Schedule cleanup if not scheduled
        if (!wp_next_scheduled('sffc_cleanup_expired_tokens')) {
            wp_schedule_event(time(), 'daily', 'sffc_cleanup_expired_tokens');
        }
    }

    /**
     * Generate new token for user
     */
    public function generate_token($user_id, $device_info = [])
    {
        global $wpdb;

        // Check if user has reached token limit
        $active_tokens = $this->get_user_active_tokens($user_id);
        if (count($active_tokens) >= $this->max_tokens_per_user) {
            // Deactivate oldest token
            $this->deactivate_oldest_token($user_id);
        }

        // Generate unique token
        $token = $this->create_secure_token();

        // Prepare device info
        $device_id = isset($device_info['device_id']) ? sanitize_text_field($device_info['device_id']) : $this->generate_device_id();
        $device_name = isset($device_info['device_name']) ? sanitize_text_field($device_info['device_name']) : $this->detect_device_name();

        // Calculate expiry
        $expires_at = date('Y-m-d H:i:s', strtotime("+{$this->token_expiry_days} days"));

        // Insert token
        $inserted = $wpdb->insert(
            $this->table_name,
            [
                'user_id' => $user_id,
                'token' => $token,
                'device_id' => $device_id,
                'device_name' => $device_name,
                'created_at' => current_time('mysql'),
                'expires_at' => $expires_at,
                'is_active' => 1
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%d']
        );

        if (!$inserted) {
            return false;
        }

        // Log token generation
        $this->log_token_event($user_id, $token, 'generated');

        return [
            'token' => $token,
            'expires_at' => $expires_at,
            'device_id' => $device_id
        ];
    }

    /**
     * Validate token
     */
    public function validate_token($token, $update_last_used = true)
    {
        global $wpdb;

        // Get token record
        $token_record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE token = %s AND is_active = 1",
            $token
        ), ARRAY_A);

        if (!$token_record) {
            return ['valid' => false, 'error' => 'Token not found'];
        }

        // Check expiry
        if (strtotime($token_record['expires_at']) < time()) {
            // Mark as inactive
            $wpdb->update(
                $this->table_name,
                ['is_active' => 0],
                ['id' => $token_record['id']]
            );

            return ['valid' => false, 'error' => 'Token expired'];
        }

        // Update last used
        if ($update_last_used) {
            $wpdb->update(
                $this->table_name,
                ['last_used' => current_time('mysql')],
                ['id' => $token_record['id']]
            );
        }

        // Get user data
        $user = get_userdata($token_record['user_id']);
        if (!$user) {
            return ['valid' => false, 'error' => 'User not found'];
        }

        // Check if user has active subscription
        if (!$this->user_has_access($token_record['user_id'])) {
            return ['valid' => false, 'error' => 'Subscription inactive'];
        }

        return [
            'valid' => true,
            'user_id' => $token_record['user_id'],
            'user_email' => $user->user_email,
            'user_name' => $user->display_name,
            'device_id' => $token_record['device_id'],
            'expires_at' => $token_record['expires_at']
        ];
    }

    /**
     * Refresh token (extend expiry)
     */
    public function refresh_token($token)
    {
        global $wpdb;

        // Validate current token
        $validation = $this->validate_token($token, false);
        if (!$validation['valid']) {
            return false;
        }

        // Extend expiry
        $new_expiry = date('Y-m-d H:i:s', strtotime("+{$this->token_expiry_days} days"));

        $updated = $wpdb->update(
            $this->table_name,
            [
                'expires_at' => $new_expiry,
                'last_used' => current_time('mysql')
            ],
            ['token' => $token]
        );

        if ($updated) {
            $this->log_token_event($validation['user_id'], $token, 'refreshed');
            return ['expires_at' => $new_expiry];
        }

        return false;
    }

    /**
     * Revoke token
     */
    public function revoke_token($token, $user_id = null)
    {
        global $wpdb;

        $where = ['token' => $token];
        if ($user_id) {
            $where['user_id'] = $user_id;
        }

        $updated = $wpdb->update(
            $this->table_name,
            ['is_active' => 0],
            $where
        );

        if ($updated) {
            $this->log_token_event($user_id, $token, 'revoked');
            return true;
        }

        return false;
    }

    /**
     * Get user's active tokens
     */
    public function get_user_active_tokens($user_id)
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE user_id = %d AND is_active = 1 
             ORDER BY created_at DESC",
            $user_id
        ), ARRAY_A);
    }

    /**
     * Check if user has access to autofill feature
     */
    private function user_has_access($user_id)
    {
        // Check if user has uploaded CV
        global $wpdb;
        $has_cv = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sffc_parsed_profiles WHERE user_id = %d",
            $user_id
        ));

        if (!$has_cv) {
            return false;
        }

        // Check membership status if using MemberPress
        if (function_exists('mepr_user_has_access')) {
            // Check for specific membership or product
            $has_membership = mepr_user_has_access($user_id, 'autofill-access');
            if (!$has_membership) {
                // Check for any active membership
                $user = new MeprUser($user_id);
                $has_membership = !empty($user->active_product_subscriptions());
            }
            return $has_membership;
        }

        // Default: all logged-in users with CV have access
        return true;
    }

    /**
     * AJAX: Generate token
     */
    public function ajax_generate_token()
    {
        // Verify nonce
        if (!check_ajax_referer('sffc_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed']);
        }

        // Check user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Please log in']);
        }

        $user_id = get_current_user_id();

        // Check access
        if (!$this->user_has_access($user_id)) {
            wp_send_json_error(['message' => 'Please upload your CV first']);
        }

        // Get device info from request
        $device_info = [
            'device_id' => isset($_POST['device_id']) ? sanitize_text_field($_POST['device_id']) : '',
            'device_name' => isset($_POST['device_name']) ? sanitize_text_field($_POST['device_name']) : ''
        ];

        // Generate token
        $token_data = $this->generate_token($user_id, $device_info);

        if (!$token_data) {
            wp_send_json_error(['message' => 'Failed to generate token']);
        }

        wp_send_json_success($token_data);
    }

    /**
     * AJAX: Validate token (for logged-in users)
     */
    public function ajax_validate_token()
    {
        // Verify nonce
        if (!check_ajax_referer('sffc_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed']);
        }

        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';

        if (empty($token)) {
            wp_send_json_error(['message' => 'Token required']);
        }

        $validation = $this->validate_token($token);

        if ($validation['valid']) {
            wp_send_json_success($validation);
        } else {
            wp_send_json_error(['message' => $validation['error']]);
        }
    }

    /**
     * AJAX: Validate extension token (no-priv for Chrome extension)
     */
    public function ajax_validate_extension_token()
    {
        // Special validation for Chrome extension
        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
        $device_id = isset($_POST['device_id']) ? sanitize_text_field($_POST['device_id']) : '';

        if (empty($token)) {
            wp_send_json_error(['message' => 'Token required']);
        }

        $validation = $this->validate_token($token);

        if ($validation['valid']) {
            // Verify device ID if provided
            if ($device_id && $device_id !== $validation['device_id']) {
                // Log suspicious activity
                $this->log_token_event($validation['user_id'], $token, 'device_mismatch');

                // Allow but flag
                $validation['device_warning'] = true;
            }

            // Get parsed profile data
            global $wpdb;
            $profile = $wpdb->get_row($wpdb->prepare(
                "SELECT parsed_data, platform_formats FROM {$wpdb->prefix}sffc_parsed_profiles WHERE user_id = %d",
                $validation['user_id']
            ), ARRAY_A);

            if ($profile) {
                $validation['profile_data'] = json_decode($profile['parsed_data'], true);
                $validation['platform_formats'] = json_decode($profile['platform_formats'], true);
            }

            wp_send_json_success($validation);
        } else {
            wp_send_json_error(['message' => $validation['error']]);
        }
    }

    /**
     * AJAX: List user tokens
     */
    public function ajax_list_tokens()
    {
        // Verify nonce
        if (!check_ajax_referer('sffc_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed']);
        }

        // Check user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Please log in']);
        }

        $user_id = get_current_user_id();
        $tokens = $this->get_user_active_tokens($user_id);

        // Sanitize tokens for display
        foreach ($tokens as &$token) {
            // Mask token for security
            $token['token_masked'] = substr($token['token'], 0, 8) . '...' . substr($token['token'], -8);
            unset($token['token']); // Remove full token
        }

        wp_send_json_success(['tokens' => $tokens]);
    }

    /**
     * AJAX: Revoke token
     */
    public function ajax_revoke_token()
    {
        // Verify nonce
        if (!check_ajax_referer('sffc_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed']);
        }

        // Check user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Please log in']);
        }

        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
        $user_id = get_current_user_id();

        if (empty($token)) {
            wp_send_json_error(['message' => 'Token required']);
        }

        if ($this->revoke_token($token, $user_id)) {
            wp_send_json_success(['message' => 'Token revoked successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to revoke token']);
        }
    }

    /**
     * AJAX: Refresh token
     */
    public function ajax_refresh_token()
    {
        // Verify nonce
        if (!check_ajax_referer('sffc_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed']);
        }

        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';

        if (empty($token)) {
            wp_send_json_error(['message' => 'Token required']);
        }

        $result = $this->refresh_token($token);

        if ($result) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error(['message' => 'Failed to refresh token']);
        }
    }

    /**
     * Create secure token
     */
    private function create_secure_token()
    {
        // Generate cryptographically secure token
        $token = bin2hex(random_bytes(32));

        // Add prefix for identification
        $token = 'sffc_' . $token;

        // Ensure uniqueness
        global $wpdb;
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE token = %s",
            $token
        ));

        if ($exists) {
            // Recursively generate new token if collision
            return $this->create_secure_token();
        }

        return $token;
    }

    /**
     * Generate device ID
     */
    private function generate_device_id()
    {
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

        return md5($user_agent . $ip . time());
    }

    /**
     * Detect device name
     */
    private function detect_device_name()
    {
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';

        // Detect browser
        $browser = 'Unknown Browser';
        if (strpos($user_agent, 'Chrome') !== false) {
            $browser = 'Chrome';
        } elseif (strpos($user_agent, 'Firefox') !== false) {
            $browser = 'Firefox';
        } elseif (strpos($user_agent, 'Safari') !== false) {
            $browser = 'Safari';
        } elseif (strpos($user_agent, 'Edge') !== false) {
            $browser = 'Edge';
        }

        // Detect OS
        $os = 'Unknown OS';
        if (strpos($user_agent, 'Windows') !== false) {
            $os = 'Windows';
        } elseif (strpos($user_agent, 'Mac') !== false) {
            $os = 'macOS';
        } elseif (strpos($user_agent, 'Linux') !== false) {
            $os = 'Linux';
        } elseif (strpos($user_agent, 'Android') !== false) {
            $os = 'Android';
        } elseif (strpos($user_agent, 'iOS') !== false) {
            $os = 'iOS';
        }

        return $browser . ' on ' . $os;
    }

    /**
     * Deactivate oldest token
     */
    private function deactivate_oldest_token($user_id)
    {
        global $wpdb;

        $oldest = $wpdb->get_row($wpdb->prepare(
            "SELECT id, token FROM {$this->table_name} 
             WHERE user_id = %d AND is_active = 1 
             ORDER BY created_at ASC 
             LIMIT 1",
            $user_id
        ));

        if ($oldest) {
            $wpdb->update(
                $this->table_name,
                ['is_active' => 0],
                ['id' => $oldest->id]
            );

            $this->log_token_event($user_id, $oldest->token, 'auto_deactivated');
        }
    }

    /**
     * Clean up expired tokens
     */
    public function cleanup_expired_tokens()
    {
        global $wpdb;

        // Deactivate expired tokens
        $wpdb->query(
            "UPDATE {$this->table_name} 
             SET is_active = 0 
             WHERE is_active = 1 
             AND expires_at < NOW()"
        );

        // Delete very old inactive tokens (> 90 days)
        $wpdb->query(
            "DELETE FROM {$this->table_name} 
             WHERE is_active = 0 
             AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
        );
    }

    /**
     * Log token event
     */
    private function log_token_event($user_id, $token, $event)
    {
        // Log to custom table or WordPress log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'Token Event: User %d, Token %s..., Event: %s',
                $user_id,
                substr($token, 0, 12),
                $event
            ));
        }

        // Could also store in database for audit trail
        do_action('sffc_token_event', $user_id, $token, $event);
    }
}

// Initialize
SFFC_Token_Manager::get_instance();
