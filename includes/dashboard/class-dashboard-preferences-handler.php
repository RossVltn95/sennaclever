<?php
/**
 * Dashboard Preferences Handler
 *
 * Handles user preferences for dashboard customization, notifications, and privacy settings.
 *
 * @package SkillFarm_Finance_Career
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SFFC_Dashboard_Preferences_Handler
 *
 * Manages all user preference operations for the career dashboard.
 */
class SFFC_Dashboard_Preferences_Handler {

    /**
     * Singleton instance
     *
     * @var SFFC_Dashboard_Preferences_Handler
     */
    private static $instance = null;

    /**
     * User meta key for preferences
     *
     * @var string
     */
    private $meta_key = 'sffc_dashboard_preferences';

    /**
     * Default section order
     *
     * @var array
     */
    private $default_sections = array(
        'career-pulse' => array(
            'id' => 'career-pulse',
            'name' => 'Career Pulse',
            'icon' => 'activity',
            'visible' => true,
            'order' => 1,
        ),
        'skill-matrix' => array(
            'id' => 'skill-matrix',
            'name' => 'Skill Matrix',
            'icon' => 'grid',
            'visible' => true,
            'order' => 2,
        ),
        'market-trends' => array(
            'id' => 'market-trends',
            'name' => 'Market Trends',
            'icon' => 'trending-up',
            'visible' => true,
            'order' => 3,
        ),
        'opportunities' => array(
            'id' => 'opportunities',
            'name' => 'Opportunities',
            'icon' => 'briefcase',
            'visible' => true,
            'order' => 4,
        ),
        'salary-intelligence' => array(
            'id' => 'salary-intelligence',
            'name' => 'Salary Intelligence',
            'icon' => 'dollar-sign',
            'visible' => true,
            'order' => 5,
        ),
        'news-intelligence' => array(
            'id' => 'news-intelligence',
            'name' => 'News & Intelligence',
            'icon' => 'newspaper',
            'visible' => true,
            'order' => 6,
        ),
        'learning-paths' => array(
            'id' => 'learning-paths',
            'name' => 'Learning Paths',
            'icon' => 'book-open',
            'visible' => true,
            'order' => 7,
        ),
        'certifications' => array(
            'id' => 'certifications',
            'name' => 'Certifications',
            'icon' => 'award',
            'visible' => true,
            'order' => 8,
        ),
    );

    /**
     * Default preferences structure
     *
     * @var array
     */
    private $default_preferences = array(
        'dashboard' => array(
            'theme' => 'light',
            'accent_color' => '#6366f1',
            'compact_mode' => false,
            'show_welcome' => true,
            'default_chart_type' => 'bar',
            'animation_enabled' => true,
            'auto_refresh' => true,
            'refresh_interval' => 300, // 5 minutes
        ),
        'sections' => array(), // Populated from default_sections
        'notifications' => array(
            'email_enabled' => true,
            'email_digest' => 'weekly', // daily, weekly, monthly, never
            'digest_day' => 'monday', // For weekly digest
            'digest_time' => '09:00',
            'alerts' => array(
                'new_jobs' => true,
                'salary_changes' => true,
                'skill_updates' => true,
                'market_alerts' => true,
                'news_digest' => true,
                'learning_reminders' => false,
                'certification_expiry' => true,
            ),
            'push_enabled' => false,
            'push_types' => array(
                'urgent_alerts' => true,
                'job_matches' => true,
                'weekly_summary' => false,
            ),
            'quiet_hours' => array(
                'enabled' => false,
                'start' => '22:00',
                'end' => '08:00',
                'timezone' => 'UTC',
            ),
        ),
        'privacy' => array(
            'profile_visibility' => 'private', // public, connections, private
            'show_in_directory' => false,
            'allow_recruiter_contact' => false,
            'share_anonymous_data' => true,
            'activity_tracking' => true,
            'personalized_recommendations' => true,
            'third_party_integrations' => array(
                'linkedin' => false,
                'google' => false,
            ),
        ),
        'accessibility' => array(
            'high_contrast' => false,
            'large_text' => false,
            'reduce_motion' => false,
            'screen_reader_hints' => true,
        ),
    );

    /**
     * Theme configurations
     *
     * @var array
     */
    private $themes = array(
        'light' => array(
            'name' => 'Light',
            'icon' => 'sun',
            'colors' => array(
                'background' => '#ffffff',
                'surface' => '#f8fafc',
                'text' => '#1e293b',
                'text_secondary' => '#64748b',
                'border' => '#e2e8f0',
            ),
        ),
        'dark' => array(
            'name' => 'Dark',
            'icon' => 'moon',
            'colors' => array(
                'background' => '#0f172a',
                'surface' => '#1e293b',
                'text' => '#f8fafc',
                'text_secondary' => '#94a3b8',
                'border' => '#334155',
            ),
        ),
        'auto' => array(
            'name' => 'Auto (System)',
            'icon' => 'monitor',
            'colors' => array(), // Uses system preference
        ),
    );

    /**
     * Accent color options
     *
     * @var array
     */
    private $accent_colors = array(
        '#6366f1' => 'Indigo',
        '#8b5cf6' => 'Violet',
        '#06b6d4' => 'Cyan',
        '#10b981' => 'Emerald',
        '#f59e0b' => 'Amber',
        '#ef4444' => 'Red',
        '#ec4899' => 'Pink',
        '#3b82f6' => 'Blue',
    );

    /**
     * Get singleton instance
     *
     * @return SFFC_Dashboard_Preferences_Handler
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
        $this->default_preferences['sections'] = $this->default_sections;
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_sffc_get_preferences', array($this, 'ajax_get_preferences'));
        add_action('wp_ajax_sffc_save_preferences', array($this, 'ajax_save_preferences'));
        add_action('wp_ajax_sffc_reset_preferences', array($this, 'ajax_reset_preferences'));
        add_action('wp_ajax_sffc_save_section_order', array($this, 'ajax_save_section_order'));
        add_action('wp_ajax_sffc_toggle_section', array($this, 'ajax_toggle_section'));
        add_action('wp_ajax_sffc_export_user_data', array($this, 'ajax_export_user_data'));
        add_action('wp_ajax_sffc_delete_user_data', array($this, 'ajax_delete_user_data'));
    }

    /**
     * Get user preferences
     *
     * @param int|null $user_id User ID (defaults to current user)
     * @return array
     */
    public function get_preferences($user_id = null) {
        if (null === $user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return $this->default_preferences;
        }

        $saved = get_user_meta($user_id, $this->meta_key, true);

        if (empty($saved) || !is_array($saved)) {
            return $this->default_preferences;
        }

        // Merge with defaults to ensure all keys exist
        return $this->merge_preferences($this->default_preferences, $saved);
    }

    /**
     * Deep merge preferences with defaults
     *
     * @param array $defaults Default preferences
     * @param array $saved Saved preferences
     * @return array
     */
    private function merge_preferences($defaults, $saved) {
        $merged = $defaults;

        foreach ($saved as $key => $value) {
            if (is_array($value) && isset($defaults[$key]) && is_array($defaults[$key])) {
                $merged[$key] = $this->merge_preferences($defaults[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /**
     * Save user preferences
     *
     * @param array $preferences Preferences to save
     * @param int|null $user_id User ID (defaults to current user)
     * @return bool
     */
    public function save_preferences($preferences, $user_id = null) {
        if (null === $user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        // Sanitize preferences
        $sanitized = $this->sanitize_preferences($preferences);

        // Add timestamp
        $sanitized['last_updated'] = current_time('mysql');
        $sanitized['updated_from'] = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';

        return update_user_meta($user_id, $this->meta_key, $sanitized);
    }

    /**
     * Sanitize preferences data
     *
     * @param array $preferences Raw preferences
     * @return array
     */
    private function sanitize_preferences($preferences) {
        $sanitized = array();

        // Dashboard settings
        if (isset($preferences['dashboard'])) {
            $sanitized['dashboard'] = array(
                'theme' => isset($preferences['dashboard']['theme']) && array_key_exists($preferences['dashboard']['theme'], $this->themes)
                    ? $preferences['dashboard']['theme']
                    : 'light',
                'accent_color' => isset($preferences['dashboard']['accent_color']) && array_key_exists($preferences['dashboard']['accent_color'], $this->accent_colors)
                    ? $preferences['dashboard']['accent_color']
                    : '#6366f1',
                'compact_mode' => !empty($preferences['dashboard']['compact_mode']),
                'show_welcome' => isset($preferences['dashboard']['show_welcome']) ? (bool) $preferences['dashboard']['show_welcome'] : true,
                'default_chart_type' => in_array($preferences['dashboard']['default_chart_type'] ?? '', array('bar', 'line', 'doughnut', 'radar'), true)
                    ? $preferences['dashboard']['default_chart_type']
                    : 'bar',
                'animation_enabled' => isset($preferences['dashboard']['animation_enabled']) ? (bool) $preferences['dashboard']['animation_enabled'] : true,
                'auto_refresh' => isset($preferences['dashboard']['auto_refresh']) ? (bool) $preferences['dashboard']['auto_refresh'] : true,
                'refresh_interval' => isset($preferences['dashboard']['refresh_interval'])
                    ? max(60, min(3600, intval($preferences['dashboard']['refresh_interval'])))
                    : 300,
            );
        }

        // Section settings
        if (isset($preferences['sections']) && is_array($preferences['sections'])) {
            $sanitized['sections'] = array();
            foreach ($preferences['sections'] as $section_id => $section) {
                if (array_key_exists($section_id, $this->default_sections)) {
                    $sanitized['sections'][$section_id] = array(
                        'id' => sanitize_key($section_id),
                        'name' => $this->default_sections[$section_id]['name'],
                        'icon' => $this->default_sections[$section_id]['icon'],
                        'visible' => isset($section['visible']) ? (bool) $section['visible'] : true,
                        'order' => isset($section['order']) ? intval($section['order']) : $this->default_sections[$section_id]['order'],
                    );
                }
            }
        }

        // Notification settings
        if (isset($preferences['notifications'])) {
            $sanitized['notifications'] = array(
                'email_enabled' => !empty($preferences['notifications']['email_enabled']),
                'email_digest' => in_array($preferences['notifications']['email_digest'] ?? '', array('daily', 'weekly', 'monthly', 'never'), true)
                    ? $preferences['notifications']['email_digest']
                    : 'weekly',
                'digest_day' => in_array($preferences['notifications']['digest_day'] ?? '', array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'), true)
                    ? $preferences['notifications']['digest_day']
                    : 'monday',
                'digest_time' => isset($preferences['notifications']['digest_time'])
                    ? sanitize_text_field($preferences['notifications']['digest_time'])
                    : '09:00',
                'alerts' => array(
                    'new_jobs' => !empty($preferences['notifications']['alerts']['new_jobs'] ?? true),
                    'salary_changes' => !empty($preferences['notifications']['alerts']['salary_changes'] ?? true),
                    'skill_updates' => !empty($preferences['notifications']['alerts']['skill_updates'] ?? true),
                    'market_alerts' => !empty($preferences['notifications']['alerts']['market_alerts'] ?? true),
                    'news_digest' => !empty($preferences['notifications']['alerts']['news_digest'] ?? true),
                    'learning_reminders' => !empty($preferences['notifications']['alerts']['learning_reminders'] ?? false),
                    'certification_expiry' => !empty($preferences['notifications']['alerts']['certification_expiry'] ?? true),
                ),
                'push_enabled' => !empty($preferences['notifications']['push_enabled']),
                'push_types' => array(
                    'urgent_alerts' => !empty($preferences['notifications']['push_types']['urgent_alerts'] ?? true),
                    'job_matches' => !empty($preferences['notifications']['push_types']['job_matches'] ?? true),
                    'weekly_summary' => !empty($preferences['notifications']['push_types']['weekly_summary'] ?? false),
                ),
                'quiet_hours' => array(
                    'enabled' => !empty($preferences['notifications']['quiet_hours']['enabled']),
                    'start' => isset($preferences['notifications']['quiet_hours']['start'])
                        ? sanitize_text_field($preferences['notifications']['quiet_hours']['start'])
                        : '22:00',
                    'end' => isset($preferences['notifications']['quiet_hours']['end'])
                        ? sanitize_text_field($preferences['notifications']['quiet_hours']['end'])
                        : '08:00',
                    'timezone' => isset($preferences['notifications']['quiet_hours']['timezone'])
                        ? sanitize_text_field($preferences['notifications']['quiet_hours']['timezone'])
                        : 'UTC',
                ),
            );
        }

        // Privacy settings
        if (isset($preferences['privacy'])) {
            $sanitized['privacy'] = array(
                'profile_visibility' => in_array($preferences['privacy']['profile_visibility'] ?? '', array('public', 'connections', 'private'), true)
                    ? $preferences['privacy']['profile_visibility']
                    : 'private',
                'show_in_directory' => !empty($preferences['privacy']['show_in_directory']),
                'allow_recruiter_contact' => !empty($preferences['privacy']['allow_recruiter_contact']),
                'share_anonymous_data' => isset($preferences['privacy']['share_anonymous_data']) ? (bool) $preferences['privacy']['share_anonymous_data'] : true,
                'activity_tracking' => isset($preferences['privacy']['activity_tracking']) ? (bool) $preferences['privacy']['activity_tracking'] : true,
                'personalized_recommendations' => isset($preferences['privacy']['personalized_recommendations']) ? (bool) $preferences['privacy']['personalized_recommendations'] : true,
                'third_party_integrations' => array(
                    'linkedin' => !empty($preferences['privacy']['third_party_integrations']['linkedin']),
                    'google' => !empty($preferences['privacy']['third_party_integrations']['google']),
                ),
            );
        }

        // Accessibility settings
        if (isset($preferences['accessibility'])) {
            $sanitized['accessibility'] = array(
                'high_contrast' => !empty($preferences['accessibility']['high_contrast']),
                'large_text' => !empty($preferences['accessibility']['large_text']),
                'reduce_motion' => !empty($preferences['accessibility']['reduce_motion']),
                'screen_reader_hints' => isset($preferences['accessibility']['screen_reader_hints']) ? (bool) $preferences['accessibility']['screen_reader_hints'] : true,
            );
        }

        return $sanitized;
    }

    /**
     * Reset preferences to defaults
     *
     * @param int|null $user_id User ID
     * @return bool
     */
    public function reset_preferences($user_id = null) {
        if (null === $user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        return delete_user_meta($user_id, $this->meta_key);
    }

    /**
     * Get section order
     *
     * @param int|null $user_id User ID
     * @return array
     */
    public function get_section_order($user_id = null) {
        $preferences = $this->get_preferences($user_id);
        $sections = $preferences['sections'] ?? $this->default_sections;

        // Sort by order
        uasort($sections, function($a, $b) {
            return ($a['order'] ?? 0) - ($b['order'] ?? 0);
        });

        return $sections;
    }

    /**
     * Get visible sections
     *
     * @param int|null $user_id User ID
     * @return array
     */
    public function get_visible_sections($user_id = null) {
        $sections = $this->get_section_order($user_id);
        return array_filter($sections, function($section) {
            return !empty($section['visible']);
        });
    }

    /**
     * Update section visibility
     *
     * @param string $section_id Section ID
     * @param bool $visible Visibility state
     * @param int|null $user_id User ID
     * @return bool
     */
    public function update_section_visibility($section_id, $visible, $user_id = null) {
        $preferences = $this->get_preferences($user_id);

        if (!isset($preferences['sections'][$section_id])) {
            $preferences['sections'][$section_id] = $this->default_sections[$section_id] ?? array();
        }

        $preferences['sections'][$section_id]['visible'] = (bool) $visible;

        return $this->save_preferences($preferences, $user_id);
    }

    /**
     * Update section order
     *
     * @param array $order Array of section IDs in order
     * @param int|null $user_id User ID
     * @return bool
     */
    public function update_section_order($order, $user_id = null) {
        $preferences = $this->get_preferences($user_id);

        foreach ($order as $index => $section_id) {
            if (isset($preferences['sections'][$section_id])) {
                $preferences['sections'][$section_id]['order'] = $index + 1;
            }
        }

        return $this->save_preferences($preferences, $user_id);
    }

    /**
     * Get theme settings
     *
     * @param int|null $user_id User ID
     * @return array
     */
    public function get_theme_settings($user_id = null) {
        $preferences = $this->get_preferences($user_id);
        $theme_id = $preferences['dashboard']['theme'] ?? 'light';
        $accent = $preferences['dashboard']['accent_color'] ?? '#6366f1';

        return array(
            'theme' => $theme_id,
            'theme_config' => $this->themes[$theme_id] ?? $this->themes['light'],
            'accent_color' => $accent,
            'accent_name' => $this->accent_colors[$accent] ?? 'Indigo',
            'available_themes' => $this->themes,
            'available_accents' => $this->accent_colors,
        );
    }

    /**
     * Get notification settings
     *
     * @param int|null $user_id User ID
     * @return array
     */
    public function get_notification_settings($user_id = null) {
        $preferences = $this->get_preferences($user_id);
        return $preferences['notifications'] ?? $this->default_preferences['notifications'];
    }

    /**
     * Get privacy settings
     *
     * @param int|null $user_id User ID
     * @return array
     */
    public function get_privacy_settings($user_id = null) {
        $preferences = $this->get_preferences($user_id);
        return $preferences['privacy'] ?? $this->default_preferences['privacy'];
    }

    /**
     * Export user data for GDPR compliance
     *
     * @param int|null $user_id User ID
     * @return array
     */
    public function export_user_data($user_id = null) {
        if (null === $user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return array();
        }

        $user = get_userdata($user_id);
        $preferences = $this->get_preferences($user_id);

        // Gather all user data related to the dashboard
        $export_data = array(
            'export_date' => current_time('mysql'),
            'user_info' => array(
                'id' => $user_id,
                'email' => $user->user_email,
                'display_name' => $user->display_name,
                'registered' => $user->user_registered,
            ),
            'dashboard_preferences' => $preferences,
            'career_profile' => get_user_meta($user_id, 'sffc_career_profile', true),
            'skill_assessments' => get_user_meta($user_id, 'sffc_skill_assessments', true),
            'saved_jobs' => get_user_meta($user_id, 'sffc_saved_jobs', true),
            'saved_articles' => get_user_meta($user_id, 'sffc_saved_articles', true),
            'learning_progress' => get_user_meta($user_id, 'sffc_learning_progress', true),
            'usage_data' => get_user_meta($user_id, 'sffc_usage_tracking', true),
            'activity_log' => $this->get_user_activity_log($user_id),
        );

        return $export_data;
    }

    /**
     * Get user activity log
     *
     * @param int $user_id User ID
     * @return array
     */
    private function get_user_activity_log($user_id) {
        global $wpdb;

        // Get recent activity from transients or custom table if exists
        $activity_key = 'sffc_activity_' . $user_id;
        $activity = get_transient($activity_key);

        return is_array($activity) ? $activity : array();
    }

    /**
     * Delete all user data
     *
     * @param int|null $user_id User ID
     * @return bool
     */
    public function delete_user_data($user_id = null) {
        if (null === $user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        // Delete all SFFC related user meta
        $meta_keys = array(
            'sffc_dashboard_preferences',
            'sffc_career_profile',
            'sffc_skill_assessments',
            'sffc_saved_jobs',
            'sffc_saved_articles',
            'sffc_learning_progress',
            'sffc_usage_tracking',
            'sffc_daily_usage',
        );

        foreach ($meta_keys as $key) {
            delete_user_meta($user_id, $key);
        }

        // Delete transients
        delete_transient('sffc_activity_' . $user_id);
        delete_transient('sffc_daily_usage_' . $user_id);

        // Log deletion for compliance
        $this->log_data_deletion($user_id);

        return true;
    }

    /**
     * Log data deletion for compliance
     *
     * @param int $user_id User ID
     */
    private function log_data_deletion($user_id) {
        $log = array(
            'user_id' => $user_id,
            'deleted_at' => current_time('mysql'),
            'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '',
        );

        // Store deletion log (anonymized)
        $deletion_logs = get_option('sffc_deletion_logs', array());
        $deletion_logs[] = $log;

        // Keep only last 1000 logs
        if (count($deletion_logs) > 1000) {
            $deletion_logs = array_slice($deletion_logs, -1000);
        }

        update_option('sffc_deletion_logs', $deletion_logs);
    }

    /**
     * AJAX: Get preferences
     */
    public function ajax_get_preferences() {
        check_ajax_referer('sffc_dashboard_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        $preferences = $this->get_preferences();
        $theme_settings = $this->get_theme_settings();

        wp_send_json_success(array(
            'preferences' => $preferences,
            'theme_settings' => $theme_settings,
            'sections' => $this->get_section_order(),
        ));
    }

    /**
     * AJAX: Save preferences
     */
    public function ajax_save_preferences() {
        check_ajax_referer('sffc_dashboard_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        $preferences = isset($_POST['preferences']) ? json_decode(stripslashes($_POST['preferences']), true) : array();

        if (empty($preferences)) {
            wp_send_json_error(array('message' => 'No preferences provided'));
        }

        $result = $this->save_preferences($preferences);

        if ($result) {
            wp_send_json_success(array(
                'message' => 'Preferences saved successfully',
                'preferences' => $this->get_preferences(),
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to save preferences'));
        }
    }

    /**
     * AJAX: Reset preferences
     */
    public function ajax_reset_preferences() {
        check_ajax_referer('sffc_dashboard_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        $result = $this->reset_preferences();

        wp_send_json_success(array(
            'message' => 'Preferences reset to defaults',
            'preferences' => $this->default_preferences,
        ));
    }

    /**
     * AJAX: Save section order
     */
    public function ajax_save_section_order() {
        check_ajax_referer('sffc_dashboard_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        $order = isset($_POST['order']) ? array_map('sanitize_key', (array) $_POST['order']) : array();

        if (empty($order)) {
            wp_send_json_error(array('message' => 'No order provided'));
        }

        $result = $this->update_section_order($order);

        if ($result) {
            wp_send_json_success(array(
                'message' => 'Section order saved',
                'sections' => $this->get_section_order(),
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to save section order'));
        }
    }

    /**
     * AJAX: Toggle section visibility
     */
    public function ajax_toggle_section() {
        check_ajax_referer('sffc_dashboard_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        $section_id = isset($_POST['section_id']) ? sanitize_key($_POST['section_id']) : '';
        $visible = isset($_POST['visible']) ? filter_var($_POST['visible'], FILTER_VALIDATE_BOOLEAN) : true;

        if (empty($section_id)) {
            wp_send_json_error(array('message' => 'No section ID provided'));
        }

        $result = $this->update_section_visibility($section_id, $visible);

        if ($result) {
            wp_send_json_success(array(
                'message' => 'Section visibility updated',
                'section_id' => $section_id,
                'visible' => $visible,
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to update section visibility'));
        }
    }

    /**
     * AJAX: Export user data
     */
    public function ajax_export_user_data() {
        check_ajax_referer('sffc_dashboard_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        $export_data = $this->export_user_data();

        wp_send_json_success(array(
            'data' => $export_data,
            'filename' => 'career-dashboard-export-' . date('Y-m-d') . '.json',
        ));
    }

    /**
     * AJAX: Delete user data
     */
    public function ajax_delete_user_data() {
        check_ajax_referer('sffc_dashboard_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        // Require confirmation
        $confirm = isset($_POST['confirm']) ? sanitize_text_field($_POST['confirm']) : '';

        if ($confirm !== 'DELETE') {
            wp_send_json_error(array('message' => 'Please confirm deletion by typing DELETE'));
        }

        $result = $this->delete_user_data();

        if ($result) {
            wp_send_json_success(array(
                'message' => 'All your data has been deleted',
                'redirect' => home_url(),
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to delete data'));
        }
    }

    /**
     * Get available timezones
     *
     * @return array
     */
    public function get_timezones() {
        return array(
            'UTC' => 'UTC',
            'America/New_York' => 'Eastern Time (ET)',
            'America/Chicago' => 'Central Time (CT)',
            'America/Denver' => 'Mountain Time (MT)',
            'America/Los_Angeles' => 'Pacific Time (PT)',
            'Europe/London' => 'London (GMT/BST)',
            'Europe/Paris' => 'Paris (CET)',
            'Europe/Berlin' => 'Berlin (CET)',
            'Asia/Dubai' => 'Dubai (GST)',
            'Asia/Singapore' => 'Singapore (SGT)',
            'Asia/Hong_Kong' => 'Hong Kong (HKT)',
            'Asia/Tokyo' => 'Tokyo (JST)',
            'Australia/Sydney' => 'Sydney (AEST)',
        );
    }
}

// Initialize the handler
SFFC_Dashboard_Preferences_Handler::get_instance();
