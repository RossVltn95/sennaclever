<?php
/**
 * CRM Rate Limiter
 * Prevents abuse and ensures fair usage
 *
 * @package SennaCareers
 * @since 7.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_Rate_Limiter {

    private static $instance = null;

    /**
     * Rate limits configuration
     * Format: action => [limit, window_seconds]
     */
    private $limits = [
        'outreach_send'       => [10, 60],      // 10 per minute
        'outreach_generate'   => [20, 60],      // 20 AI generations per minute
        'search'              => [60, 60],      // 60 searches per minute
        'export'              => [5, 300],      // 5 exports per 5 minutes
        'api_request'         => [100, 60],     // 100 API requests per minute
        'save_recruiter'      => [30, 60],      // 30 saves per minute
        'sequence_enroll'     => [20, 60],      // 20 enrollments per minute
        'compose_message'     => [30, 60],      // 30 compose requests per minute
    ];

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Clean up old rate limit data periodically
        add_action('sffc_crm_cleanup_rate_limits', [$this, 'cleanup_expired']);

        // Schedule cleanup if not already scheduled
        if (!wp_next_scheduled('sffc_crm_cleanup_rate_limits')) {
            wp_schedule_event(time(), 'hourly', 'sffc_crm_cleanup_rate_limits');
        }
    }

    /**
     * Check if action is allowed for user
     *
     * @param string $action Action identifier
     * @param int|null $user_id User ID (null for current user)
     * @return bool|WP_Error True if allowed, WP_Error if rate limited
     */
    public function check($action, $user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        // Admins bypass rate limiting
        if (user_can($user_id, 'manage_options')) {
            return true;
        }

        if (!isset($this->limits[$action])) {
            return true; // Unknown action, allow by default
        }

        list($limit, $window) = $this->limits[$action];

        // Get current count
        $count = $this->get_count($user_id, $action, $window);

        if ($count >= $limit) {
            $retry_after = $this->get_retry_after($user_id, $action, $window);

            return new WP_Error(
                'rate_limited',
                sprintf(
                    __('Rate limit exceeded. Please try again in %d seconds.', 'senna-finance'),
                    $retry_after
                ),
                [
                    'status' => 429,
                    'retry_after' => $retry_after,
                    'limit' => $limit,
                    'window' => $window,
                ]
            );
        }

        return true;
    }

    /**
     * Record an action (increment counter)
     *
     * @param string $action Action identifier
     * @param int|null $user_id User ID
     * @return bool Success
     */
    public function record($action, $user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        $key = $this->get_transient_key($user_id, $action);
        $window = $this->limits[$action][1] ?? 60;

        $data = get_transient($key);

        if ($data === false) {
            $data = [
                'count' => 1,
                'first_request' => time(),
            ];
        } else {
            $data['count']++;
        }

        set_transient($key, $data, $window);

        return true;
    }

    /**
     * Check and record in one call
     *
     * @param string $action Action identifier
     * @param int|null $user_id User ID
     * @return bool|WP_Error True if allowed and recorded, WP_Error if rate limited
     */
    public function throttle($action, $user_id = null) {
        $check = $this->check($action, $user_id);

        if (is_wp_error($check)) {
            return $check;
        }

        $this->record($action, $user_id);
        return true;
    }

    /**
     * Get current count for action
     *
     * @param int $user_id User ID
     * @param string $action Action identifier
     * @param int $window Time window in seconds
     * @return int Current count
     */
    private function get_count($user_id, $action, $window) {
        $key = $this->get_transient_key($user_id, $action);
        $data = get_transient($key);

        if ($data === false) {
            return 0;
        }

        // Check if window has passed
        if (time() - $data['first_request'] > $window) {
            delete_transient($key);
            return 0;
        }

        return $data['count'];
    }

    /**
     * Get seconds until rate limit resets
     *
     * @param int $user_id User ID
     * @param string $action Action identifier
     * @param int $window Time window in seconds
     * @return int Seconds until reset
     */
    private function get_retry_after($user_id, $action, $window) {
        $key = $this->get_transient_key($user_id, $action);
        $data = get_transient($key);

        if ($data === false) {
            return 0;
        }

        $elapsed = time() - $data['first_request'];
        return max(0, $window - $elapsed);
    }

    /**
     * Generate transient key
     *
     * @param int $user_id User ID
     * @param string $action Action identifier
     * @return string Transient key
     */
    private function get_transient_key($user_id, $action) {
        return 'sffc_crm_rate_' . $user_id . '_' . $action;
    }

    /**
     * Get remaining requests for action
     *
     * @param string $action Action identifier
     * @param int|null $user_id User ID
     * @return array ['remaining' => int, 'limit' => int, 'reset' => int]
     */
    public function get_remaining($action, $user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        if (!isset($this->limits[$action])) {
            return [
                'remaining' => -1,
                'limit' => -1,
                'reset' => 0,
            ];
        }

        list($limit, $window) = $this->limits[$action];
        $count = $this->get_count($user_id, $action, $window);
        $reset = $this->get_retry_after($user_id, $action, $window);

        return [
            'remaining' => max(0, $limit - $count),
            'limit' => $limit,
            'reset' => $reset,
        ];
    }

    /**
     * Add rate limit headers to response
     *
     * @param string $action Action identifier
     * @param int|null $user_id User ID
     */
    public function add_headers($action, $user_id = null) {
        $info = $this->get_remaining($action, $user_id);

        if ($info['limit'] > 0) {
            header('X-RateLimit-Limit: ' . $info['limit']);
            header('X-RateLimit-Remaining: ' . $info['remaining']);
            header('X-RateLimit-Reset: ' . (time() + $info['reset']));
        }
    }

    /**
     * Reset rate limit for user and action
     *
     * @param string $action Action identifier
     * @param int $user_id User ID
     * @return bool Success
     */
    public function reset($action, $user_id) {
        $key = $this->get_transient_key($user_id, $action);
        return delete_transient($key);
    }

    /**
     * Reset all rate limits for user
     *
     * @param int $user_id User ID
     * @return int Number of limits reset
     */
    public function reset_all($user_id) {
        $count = 0;

        foreach (array_keys($this->limits) as $action) {
            if ($this->reset($action, $user_id)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Cleanup expired rate limit transients
     */
    public function cleanup_expired() {
        global $wpdb;

        // Delete expired transients related to rate limiting
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_sffc_crm_rate_%'
             AND option_name NOT LIKE '_transient_timeout_sffc_crm_rate_%'"
        );

        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_timeout_sffc_crm_rate_%'
             AND option_value < " . time()
        );
    }

    /**
     * Get all limits configuration
     *
     * @return array Limits configuration
     */
    public function get_limits() {
        return $this->limits;
    }

    /**
     * Set custom limit for an action
     *
     * @param string $action Action identifier
     * @param int $limit Max requests
     * @param int $window Time window in seconds
     */
    public function set_limit($action, $limit, $window) {
        $this->limits[$action] = [$limit, $window];
    }
}

// Initialize
SFFC_CRM_Rate_Limiter::get_instance();
