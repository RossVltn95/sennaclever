<?php

/**
 * Profile AJAX Handlers
 * Handles profile data syncing between frontend and WordPress user meta
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Profile_Ajax_Handlers
{

    private static $instance = null;

    /**
     * Meta key for storing user profile data
     */
    const PROFILE_META_KEY = 'sffc_user_profile';

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
     * Constructor
     */
    private function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        // Get user profile
        add_action('wp_ajax_sffc_get_user_profile', array($this, 'get_user_profile'));
        add_action('wp_ajax_nopriv_sffc_get_user_profile', array($this, 'require_login'));

        // Update user profile
        add_action('wp_ajax_sffc_update_user_profile', array($this, 'update_user_profile'));
        add_action('wp_ajax_nopriv_sffc_update_user_profile', array($this, 'handle_guest_profile_update'));

        // Check authentication status
        add_action('wp_ajax_sffc_check_auth', array($this, 'check_auth'));
        add_action('wp_ajax_nopriv_sffc_check_auth', array($this, 'check_auth'));

        // Create guest session
        add_action('wp_ajax_sffc_create_guest_session', array($this, 'create_guest_session'));
        add_action('wp_ajax_nopriv_sffc_create_guest_session', array($this, 'create_guest_session'));

        // Validate session
        add_action('wp_ajax_sffc_validate_session', array($this, 'validate_session'));
        add_action('wp_ajax_nopriv_sffc_validate_session', array($this, 'validate_session'));

        // Professional Profile handlers
        add_action('wp_ajax_sffc_save_profile', array($this, 'save_professional_profile'));
        add_action('wp_ajax_nopriv_sffc_save_profile', array($this, 'require_login'));

        add_action('wp_ajax_sffc_save_profile_visibility', array($this, 'save_profile_visibility'));
        add_action('wp_ajax_nopriv_sffc_save_profile_visibility', array($this, 'require_login'));

        add_action('wp_ajax_sffc_update_skills', array($this, 'update_skills'));
        add_action('wp_ajax_nopriv_sffc_update_skills', array($this, 'require_login'));

        add_action('wp_ajax_sffc_save_profile_section', array($this, 'save_profile_section'));
        add_action('wp_ajax_nopriv_sffc_save_profile_section', array($this, 'require_login'));
    }

    /**
     * Get user profile data
     */
    public function get_user_profile()
    {
        // Verify nonce
        if (!check_ajax_referer('sffc_frontend_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Invalid security token'));
            return;
        }

        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Please log in to access your profile'));
            return;
        }

        $user_id = get_current_user_id();
        $user = get_userdata($user_id);

        // Get stored profile data
        $profile_data = get_user_meta($user_id, self::PROFILE_META_KEY, true);

        // If no profile data exists, create default from user info
        if (empty($profile_data)) {
            $profile_data = array(
                'name' => $user->display_name,
                'email' => $user->user_email,
                'first_name' => get_user_meta($user_id, 'first_name', true),
                'last_name' => get_user_meta($user_id, 'last_name', true),
                'phone' => get_user_meta($user_id, 'billing_phone', true), // WooCommerce phone
                'location' => $this->get_user_location($user_id),
                'linkedin_url' => get_user_meta($user_id, 'linkedin_url', true),
                'created_at' => date('Y-m-d H:i:s')
            );

            // Save initial profile
            update_user_meta($user_id, self::PROFILE_META_KEY, $profile_data);
        }

        // Add user ID for reference
        $profile_data['user_id'] = $user_id;
        $profile_data['is_authenticated'] = true;

        wp_send_json_success($profile_data);
    }

    /**
     * Update user profile data
     */
    public function update_user_profile()
    {
        // Verify nonce
        if (!check_ajax_referer('sffc_frontend_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Invalid security token'));
            return;
        }

        // User must be logged in for this endpoint
        if (!is_user_logged_in()) {
            wp_send_json_error(array(
                'message' => 'Authentication required. Please log in to update your profile.',
                'require_auth' => true,
                'login_url' => get_option('sffc_login_url', 'https://joinsenna.com/login-auth/')
            ));
            return;
        }

        $user_id = get_current_user_id();

        // Get existing profile
        $existing_profile = get_user_meta($user_id, self::PROFILE_META_KEY, true);
        if (!is_array($existing_profile)) {
            $existing_profile = array();
        }

        // Get updates from request
        $updates = isset($_POST['profile_data']) ? $_POST['profile_data'] : array();

        // Sanitize updates
        $sanitized_updates = $this->sanitize_profile_data($updates);

        // Merge with existing data
        $updated_profile = array_merge($existing_profile, $sanitized_updates);

        // Add last updated timestamp
        $updated_profile['last_updated'] = date('Y-m-d H:i:s');

        // Save to user meta
        $result = update_user_meta($user_id, self::PROFILE_META_KEY, $updated_profile);

        // Also update WordPress user fields if applicable
        $this->update_wordpress_user_fields($user_id, $sanitized_updates);

        if ($result !== false) {
            wp_send_json_success(array(
                'message' => 'Profile updated successfully',
                'profile' => $updated_profile
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to update profile'));
        }
    }

    /**
     * Check authentication status
     */
    public function check_auth()
    {
        $is_logged_in = is_user_logged_in();

        $response = array(
            'is_authenticated' => $is_logged_in,
            'login_url' => get_option('sffc_login_url', 'https://joinsenna.com/login-auth/')
        );

        if ($is_logged_in) {
            $user = wp_get_current_user();
            $response['user'] = array(
                'id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email
            );
        }

        wp_send_json_success($response);
    }

    /**
     * Require login response for non-authenticated requests
     */
    public function require_login()
    {
        wp_send_json_error(array(
            'message' => 'Please log in to continue',
            'login_url' => get_option('sffc_login_url', 'https://joinsenna.com/login-auth/'),
            'is_authenticated' => false
        ));
    }

    /**
     * Handle profile update for guest users with valid session
     */
    public function handle_guest_profile_update()
    {
        // Verify nonce
        if (!check_ajax_referer('sffc_frontend_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Invalid security token'));
            return;
        }

        // Check if guest session is valid
        $session_token = isset($_POST['session_token']) ? sanitize_text_field($_POST['session_token']) : '';

        if (!$this->validate_guest_session($session_token)) {
            wp_send_json_error(array(
                'message' => 'Session expired or invalid. Please complete the registration process.',
                'require_auth' => true,
                'login_url' => get_option('sffc_login_url', 'https://joinsenna.com/login-auth/')
            ));
            return;
        }

        // For guest users, only allow limited profile updates
        $allowed_fields = array('name', 'email', 'phone', 'location', 'current_role');
        $updates = isset($_POST['profile_data']) ? $_POST['profile_data'] : array();

        // Filter to only allowed fields
        $filtered_updates = array();
        foreach ($allowed_fields as $field) {
            if (isset($updates[$field])) {
                $filtered_updates[$field] = sanitize_text_field($updates[$field]);
            }
        }

        // Store in session data (temporary)
        wp_send_json_success(array(
            'message' => 'Profile data saved temporarily. Please register to save permanently.',
            'profile' => $filtered_updates,
            'is_temporary' => true
        ));
    }

    /**
     * Validate session endpoint
     */
    public function validate_session()
    {
        $session_token = isset($_POST['session_token']) ? sanitize_text_field($_POST['session_token']) : '';

        if (is_user_logged_in()) {
            wp_send_json_success(array(
                'valid' => true,
                'authenticated' => true,
                'user_id' => get_current_user_id()
            ));
            return;
        }

        if ($this->validate_guest_session($session_token)) {
            wp_send_json_success(array(
                'valid' => true,
                'authenticated' => false,
                'is_guest' => true
            ));
        } else {
            wp_send_json_error(array(
                'valid' => false,
                'message' => 'Invalid or expired session'
            ));
        }
    }

    /**
     * Sanitize profile data
     */
    private function sanitize_profile_data($data)
    {
        $sanitized = array();

        // Define field types and sanitization methods
        $fields = array(
            'name' => 'sanitize_text_field',
            'first_name' => 'sanitize_text_field',
            'last_name' => 'sanitize_text_field',
            'email' => 'sanitize_email',
            'phone' => 'sanitize_text_field',
            'location' => 'sanitize_text_field',
            'current_role' => 'sanitize_text_field',
            'years_experience' => 'sanitize_text_field',
            'work_history' => 'wp_kses_post',
            'technical_skills' => 'sanitize_textarea_field',
            'soft_skills' => 'sanitize_textarea_field',
            'certifications' => 'sanitize_textarea_field',
            'degree' => 'sanitize_text_field',
            'institution' => 'sanitize_text_field',
            'graduation_year' => 'sanitize_text_field',
            'resume' => 'sanitize_text_field',
            'cover_letter_template' => 'wp_kses_post',
            'linkedin_url' => 'esc_url_raw',
            'portfolio_url' => 'esc_url_raw',
            'github_url' => 'esc_url_raw'
        );

        foreach ($fields as $field => $sanitizer) {
            if (isset($data[$field])) {
                if (is_array($data[$field])) {
                    $sanitized[$field] = array_map($sanitizer, $data[$field]);
                } else {
                    $sanitized[$field] = call_user_func($sanitizer, $data[$field]);
                }
            }
        }

        return $sanitized;
    }

    /**
     * Update WordPress user fields
     */
    private function update_wordpress_user_fields($user_id, $profile_data)
    {
        $user_data = array('ID' => $user_id);
        $updated = false;

        // Update display name
        if (!empty($profile_data['name'])) {
            $user_data['display_name'] = $profile_data['name'];
            $updated = true;
        }

        // Update user if needed
        if ($updated) {
            wp_update_user($user_data);
        }

        // Update user meta fields
        if (!empty($profile_data['first_name'])) {
            update_user_meta($user_id, 'first_name', $profile_data['first_name']);
        }

        if (!empty($profile_data['last_name'])) {
            update_user_meta($user_id, 'last_name', $profile_data['last_name']);
        }

        if (!empty($profile_data['phone'])) {
            update_user_meta($user_id, 'billing_phone', $profile_data['phone']);
        }

        if (!empty($profile_data['linkedin_url'])) {
            update_user_meta($user_id, 'linkedin_url', $profile_data['linkedin_url']);
        }
    }

    /**
     * Get user location from various sources
     */
    private function get_user_location($user_id)
    {
        // Try WooCommerce billing city/state
        $city = get_user_meta($user_id, 'billing_city', true);
        $state = get_user_meta($user_id, 'billing_state', true);

        if ($city && $state) {
            return $city . ', ' . $state;
        }

        // Try custom location field
        $location = get_user_meta($user_id, 'location', true);
        if ($location) {
            return $location;
        }

        return '';
    }

    /**
     * Validate guest session token
     * @param string $session_token The session token to validate
     * @return bool Whether the session is valid
     */
    private function validate_guest_session($session_token)
    {
        if (empty($session_token)) {
            return false;
        }

        // Get stored sessions from database
        global $wpdb;
        $table_name = $wpdb->prefix . 'sffc_guest_sessions';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            // Table doesn't exist, create it
            $this->create_sessions_table();
            return false;
        }

        // Look up session
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE session_token = %s AND is_active = 1",
            $session_token
        ));

        if (!$session) {
            return false;
        }

        // Check if session is expired (30 minutes)
        $created = strtotime($session->created_at);
        $now = time();
        $age = $now - $created;

        if ($age > 1800) { // 30 minutes
            // Mark session as expired
            $wpdb->update(
                $table_name,
                array('is_active' => 0),
                array('id' => $session->id)
            );
            return false;
        }

        // Update last activity
        $wpdb->update(
            $table_name,
            array('last_activity' => current_time('mysql')),
            array('id' => $session->id)
        );

        return true;
    }

    /**
     * Create guest sessions table
     */
    private function create_sessions_table()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sffc_guest_sessions';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            session_token varchar(255) NOT NULL,
            email varchar(100),
            name varchar(100),
            ip_address varchar(45),
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            last_activity datetime DEFAULT CURRENT_TIMESTAMP,
            is_active tinyint(1) DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY session_token (session_token),
            KEY is_active (is_active),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create and store guest session
     */
    public function create_guest_session()
    {
        // Verify nonce
        if (!check_ajax_referer('sffc_frontend_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Invalid security token'));
            return;
        }

        $session_token = isset($_POST['session_token']) ? sanitize_text_field($_POST['session_token']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';

        if (empty($session_token) || empty($email) || empty($name)) {
            wp_send_json_error(array('message' => 'Missing required fields'));
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'sffc_guest_sessions';

        // Ensure table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $this->create_sessions_table();
        }

        // Store session
        $result = $wpdb->insert(
            $table_name,
            array(
                'session_token' => $session_token,
                'email' => $email,
                'name' => $name,
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'created_at' => current_time('mysql'),
                'last_activity' => current_time('mysql'),
                'is_active' => 1
            )
        );

        if ($result) {
            wp_send_json_success(array(
                'message' => 'Session created',
                'session_valid' => true
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to create session'));
        }
    }

    /**
     * Save professional profile data
     */
    public function save_professional_profile()
    {
        // Verify nonce - accept both dashboard nonce and frontend nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
        if (!wp_verify_nonce($nonce, 'sffc_dashboard_nonce') &&
            !wp_verify_nonce($nonce, 'sffc_frontend_nonce') &&
            !wp_verify_nonce($nonce, 'sffc_public_nonce')) {
            wp_send_json_error(array('message' => 'Invalid security token'));
            return;
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(array(
                'message' => 'Please log in to save your profile',
                'require_auth' => true
            ));
            return;
        }

        $user_id = get_current_user_id();

        // Get the section being saved
        $section = isset($_POST['section']) ? sanitize_text_field($_POST['section']) : 'all';

        // Collect and sanitize profile data based on section
        $profile_data = array(
            'last_updated' => current_time('mysql')
        );

        // Header section fields
        if ($section === 'all' || $section === 'header') {
            if (isset($_POST['first_name'])) {
                $profile_data['first_name'] = sanitize_text_field($_POST['first_name']);
            }
            if (isset($_POST['last_name'])) {
                $profile_data['last_name'] = sanitize_text_field($_POST['last_name']);
            }
            if (isset($_POST['display_name'])) {
                $profile_data['display_name'] = sanitize_text_field($_POST['display_name']);
            }
            if (isset($_POST['headline'])) {
                $profile_data['headline'] = sanitize_text_field($_POST['headline']);
            }
            if (isset($_POST['pronouns'])) {
                $profile_data['pronouns'] = sanitize_text_field($_POST['pronouns']);
            }
            if (isset($_POST['location'])) {
                $profile_data['location'] = sanitize_text_field($_POST['location']);
            }
        }

        // About section
        if ($section === 'all' || $section === 'about') {
            if (isset($_POST['bio'])) {
                $profile_data['bio'] = sanitize_textarea_field($_POST['bio']);
            }
        }

        // Preferences section
        if ($section === 'all' || $section === 'preferences') {
            if (isset($_POST['experience_years'])) {
                $profile_data['experience_years'] = absint($_POST['experience_years']);
            }
            if (isset($_POST['preferred_roles'])) {
                $profile_data['preferred_roles'] = sanitize_text_field($_POST['preferred_roles']);
            }
            if (isset($_POST['preferred_industries'])) {
                $profile_data['preferred_industries'] = sanitize_text_field($_POST['preferred_industries']);
            }
            if (isset($_POST['work_style'])) {
                $profile_data['work_style'] = sanitize_text_field($_POST['work_style']);
            }
            if (isset($_POST['salary_min'])) {
                $profile_data['salary_min'] = absint($_POST['salary_min']);
            }
            if (isset($_POST['salary_max'])) {
                $profile_data['salary_max'] = absint($_POST['salary_max']);
            }
            if (isset($_POST['job_alerts'])) {
                $profile_data['job_alerts'] = absint($_POST['job_alerts']);
            }
            if (isset($_POST['market_updates'])) {
                $profile_data['market_updates'] = absint($_POST['market_updates']);
            }
        }

        // Match preferences section (for job matching)
        if ($section === 'all' || $section === 'match-preferences') {
            if (isset($_POST['preferred_location'])) {
                $profile_data['preferred_location'] = sanitize_text_field($_POST['preferred_location']);
            }
            if (isset($_POST['experience_level'])) {
                $allowed_levels = array('junior', 'mid', 'senior', 'executive');
                $level = sanitize_text_field($_POST['experience_level']);
                if (in_array($level, $allowed_levels)) {
                    $profile_data['experience_level'] = $level;
                }
            }
            if (isset($_POST['role_type'])) {
                $allowed_types = array('front_office', 'back_office', 'operations', 'support');
                $type = sanitize_text_field($_POST['role_type']);
                if (in_array($type, $allowed_types)) {
                    $profile_data['role_type'] = $type;
                }
            }
            if (isset($_POST['preferred_next_role'])) {
                $profile_data['preferred_next_role'] = sanitize_text_field($_POST['preferred_next_role']);
            }
            if (isset($_POST['preferred_industries'])) {
                $profile_data['preferred_industries'] = sanitize_text_field($_POST['preferred_industries']);
            }
            if (isset($_POST['latest_experience_description'])) {
                $profile_data['latest_experience_description'] = sanitize_textarea_field($_POST['latest_experience_description']);
            }
        }

        // Career journey section (from senna-launcher intake)
        if ($section === 'career-journey') {
            $intake_data = get_user_meta($user_id, 'senna_intake_data', true);
            if (!is_array($intake_data)) {
                $intake_data = array();
            }

            if (isset($_POST['goal'])) {
                $allowed_goals = array('transition', 'advance', 'explore', 'pivot');
                $goal = sanitize_text_field($_POST['goal']);
                if (empty($goal) || in_array($goal, $allowed_goals)) {
                    $intake_data['goal'] = $goal;
                }
            }
            if (isset($_POST['situation'])) {
                $allowed_situations = array('student', 'analyst', 'associate', 'senior', 'between', 'other');
                $situation = sanitize_text_field($_POST['situation']);
                if (empty($situation) || in_array($situation, $allowed_situations)) {
                    $intake_data['situation'] = $situation;
                }
            }
            if (isset($_POST['timeline'])) {
                $allowed_timelines = array('immediate', '3months', '6months', 'year');
                $timeline = sanitize_text_field($_POST['timeline']);
                if (empty($timeline) || in_array($timeline, $allowed_timelines)) {
                    $intake_data['timeline'] = $timeline;
                }
            }
            if (isset($_POST['challenge'])) {
                $allowed_challenges = array('technical', 'network', 'experience', 'brand', 'clarity', 'interview');
                $challenge = sanitize_text_field($_POST['challenge']);
                if (empty($challenge) || in_array($challenge, $allowed_challenges)) {
                    $intake_data['challenge'] = $challenge;
                }
            }

            // Save intake data separately
            update_user_meta($user_id, 'senna_intake_data', $intake_data);

            wp_send_json_success(array(
                'message' => __('Career journey updated successfully', 'senna-finance'),
                'intake_data' => $intake_data
            ));
            return;
        }

        // Handle skills array
        if ($section === 'all' || $section === 'skills') {
            if (isset($_POST['skills']) && is_array($_POST['skills'])) {
                $skills = array();
                foreach ($_POST['skills'] as $skill) {
                    if (is_array($skill) && !empty($skill['name'])) {
                        $skills[] = array(
                            'name' => sanitize_text_field($skill['name']),
                            'level' => isset($skill['level']) ? sanitize_text_field($skill['level']) : 'intermediate'
                        );
                    } elseif (is_string($skill) && !empty($skill)) {
                        // Handle simple skill strings
                        $skills[] = array(
                            'name' => sanitize_text_field($skill),
                            'level' => 'intermediate'
                        );
                    }
                }
                $profile_data['skills'] = $skills;
            }
        }

        // Handle experience array
        if ($section === 'all' || $section === 'experience') {
            if (isset($_POST['experience']) && is_array($_POST['experience'])) {
                $experience = array();
                foreach ($_POST['experience'] as $exp) {
                    if (is_array($exp) && (!empty($exp['company']) || !empty($exp['title']))) {
                        $experience[] = array(
                            'company' => isset($exp['company']) ? sanitize_text_field($exp['company']) : '',
                            'title' => isset($exp['title']) ? sanitize_text_field($exp['title']) : '',
                            'location' => isset($exp['location']) ? sanitize_text_field($exp['location']) : '',
                            'start_date' => isset($exp['start_date']) ? sanitize_text_field($exp['start_date']) : '',
                            'end_date' => isset($exp['end_date']) ? sanitize_text_field($exp['end_date']) : '',
                            'is_current' => isset($exp['is_current']) ? (bool) $exp['is_current'] : false,
                            'description' => isset($exp['description']) ? sanitize_textarea_field($exp['description']) : ''
                        );
                    }
                }
                $profile_data['experience'] = $experience;
            }
        }

        // Handle education array
        if ($section === 'all' || $section === 'education') {
            if (isset($_POST['education']) && is_array($_POST['education'])) {
                $education = array();
                foreach ($_POST['education'] as $edu) {
                    if (is_array($edu) && (!empty($edu['school']) || !empty($edu['degree']))) {
                        $education[] = array(
                            'school' => isset($edu['school']) ? sanitize_text_field($edu['school']) : '',
                            'degree' => isset($edu['degree']) ? sanitize_text_field($edu['degree']) : '',
                            'field' => isset($edu['field']) ? sanitize_text_field($edu['field']) : '',
                            'start_date' => isset($edu['start_date']) ? sanitize_text_field($edu['start_date']) : '',
                            'end_date' => isset($edu['end_date']) ? sanitize_text_field($edu['end_date']) : '',
                            'description' => isset($edu['description']) ? sanitize_textarea_field($edu['description']) : ''
                        );
                    }
                }
                $profile_data['education'] = $education;
            }
        }

        // Get existing profile data
        $existing_profile = get_user_meta($user_id, 'sffc_professional_profile', true);
        if (!is_array($existing_profile)) {
            $existing_profile = array();
        }

        // Merge with existing data
        $updated_profile = array_merge($existing_profile, $profile_data);

        // Save to user meta
        update_user_meta($user_id, 'sffc_professional_profile', $updated_profile);

        // Also update WordPress user fields
        $wp_user_data = array('ID' => $user_id);
        $should_update_user = false;

        if (!empty($profile_data['display_name'])) {
            $wp_user_data['display_name'] = $profile_data['display_name'];
            $should_update_user = true;
        }
        if (!empty($profile_data['first_name'])) {
            $wp_user_data['first_name'] = $profile_data['first_name'];
            update_user_meta($user_id, 'first_name', $profile_data['first_name']);
            $should_update_user = true;
        }
        if (!empty($profile_data['last_name'])) {
            $wp_user_data['last_name'] = $profile_data['last_name'];
            update_user_meta($user_id, 'last_name', $profile_data['last_name']);
            $should_update_user = true;
        }

        if ($should_update_user) {
            wp_update_user($wp_user_data);
        }

        // Calculate profile completion percentage
        $completion = $this->calculate_profile_completion($updated_profile);
        $updated_profile['completion'] = $completion;

        wp_send_json_success(array_merge($updated_profile, array(
            'message' => 'Profile saved successfully',
            'completion' => $completion
        )));
    }

    /**
     * Save profile visibility setting
     */
    public function save_profile_visibility()
    {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
        if (!wp_verify_nonce($nonce, 'sffc_dashboard_nonce') &&
            !wp_verify_nonce($nonce, 'sffc_frontend_nonce') &&
            !wp_verify_nonce($nonce, 'sffc_public_nonce')) {
            wp_send_json_error(array('message' => 'Invalid security token'));
            return;
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Please log in'));
            return;
        }

        $user_id = get_current_user_id();
        $visibility = isset($_POST['visibility']) ? sanitize_text_field($_POST['visibility']) : 'hidden';

        // Validate visibility value
        $allowed_visibility = array('active', 'open', 'hidden');
        if (!in_array($visibility, $allowed_visibility)) {
            $visibility = 'hidden';
        }

        update_user_meta($user_id, 'sffc_profile_visibility', $visibility);

        wp_send_json_success(array(
            'message' => 'Visibility updated',
            'visibility' => $visibility
        ));
    }

    /**
     * Update skills
     */
    public function update_skills()
    {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
        if (!wp_verify_nonce($nonce, 'sffc_dashboard_nonce') &&
            !wp_verify_nonce($nonce, 'sffc_frontend_nonce') &&
            !wp_verify_nonce($nonce, 'sffc_public_nonce')) {
            wp_send_json_error(array('message' => 'Invalid security token'));
            return;
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Please log in'));
            return;
        }

        $user_id = get_current_user_id();

        // Get existing profile
        $profile = get_user_meta($user_id, 'sffc_professional_profile', true);
        if (!is_array($profile)) {
            $profile = array();
        }

        // Process skills
        $skills = array();
        if (isset($_POST['skills']) && is_array($_POST['skills'])) {
            foreach ($_POST['skills'] as $skill) {
                if (is_array($skill) && !empty($skill['name'])) {
                    $skills[] = array(
                        'name' => sanitize_text_field($skill['name']),
                        'level' => isset($skill['level']) ? sanitize_text_field($skill['level']) : 'intermediate'
                    );
                }
            }
        }

        $profile['skills'] = $skills;
        $profile['last_updated'] = current_time('mysql');

        update_user_meta($user_id, 'sffc_professional_profile', $profile);

        wp_send_json_success(array(
            'message' => 'Skills updated',
            'skills' => $skills
        ));
    }

    /**
     * Save profile section (inline editing)
     */
    public function save_profile_section()
    {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
        if (!wp_verify_nonce($nonce, 'sffc_dashboard_nonce') &&
            !wp_verify_nonce($nonce, 'sffc_frontend_nonce') &&
            !wp_verify_nonce($nonce, 'sffc_public_nonce')) {
            wp_send_json_error(array('message' => 'Invalid security token'));
            return;
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Please log in'));
            return;
        }

        $user_id = get_current_user_id();
        $section = isset($_POST['section']) ? sanitize_text_field($_POST['section']) : '';

        // Get existing profile
        $profile = get_user_meta($user_id, 'sffc_professional_profile', true);
        if (!is_array($profile)) {
            $profile = array();
        }

        // Update section-specific fields
        $updated_fields = array();
        foreach ($_POST as $key => $value) {
            if ($key !== 'action' && $key !== 'nonce' && $key !== 'section') {
                $sanitized_key = sanitize_key($key);
                $sanitized_value = is_array($value) ? array_map('sanitize_text_field', $value) : sanitize_text_field($value);
                $profile[$sanitized_key] = $sanitized_value;
                $updated_fields[$sanitized_key] = $sanitized_value;
            }
        }

        $profile['last_updated'] = current_time('mysql');
        update_user_meta($user_id, 'sffc_professional_profile', $profile);

        wp_send_json_success(array(
            'message' => 'Section updated',
            'section' => $section,
            'updated_fields' => $updated_fields
        ));
    }

    /**
     * Calculate profile completion percentage
     */
    private function calculate_profile_completion($profile)
    {
        $fields = array(
            'display_name' => 8,
            'headline' => 8,
            'bio' => 8,
            'location' => 4,
            'experience' => 15,
            'education' => 12,
            'skills' => 15,
            'preferred_roles' => 8,
            'preferred_industries' => 8,
            'work_style' => 6,
            'salary_min' => 4,
            'salary_max' => 4
        );

        $total = 0;
        $earned = 0;

        foreach ($fields as $field => $weight) {
            $total += $weight;
            if (!empty($profile[$field])) {
                // Handle array fields (skills, experience, education)
                if (in_array($field, array('skills', 'experience', 'education')) && is_array($profile[$field])) {
                    if (count($profile[$field]) > 0) {
                        $earned += $weight;
                    }
                } else {
                    $earned += $weight;
                }
            }
        }

        return $total > 0 ? round(($earned / $total) * 100) : 0;
    }
}

// Initialize
SFFC_Profile_Ajax_Handlers::get_instance();
