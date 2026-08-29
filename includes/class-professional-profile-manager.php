<?php

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Professional_Profile_Manager
{
    private static $instance = null;
    private $db;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        $this->db = SFFC_Professional_Profile_Database::get_instance();
    }

    public function init()
    {
        add_action('wp_ajax_sffc_update_professional_profile', array($this, 'handle_profile_update'));
        add_action('wp_ajax_sffc_add_expertise', array($this, 'handle_add_expertise'));
        add_action('wp_ajax_sffc_get_profile_data', array($this, 'handle_get_profile_data'));
        add_action('wp_ajax_sffc_request_introduction', array($this, 'handle_introduction_request'));
        add_action('wp_ajax_sffc_track_profile_interaction', array($this, 'handle_track_interaction'));
        add_action('wp_ajax_sffc_upload_profile_picture', array($this, 'handle_profile_picture_upload'));
        add_action('wp_ajax_sffc_save_career_preferences', array($this, 'handle_save_career_preferences'));
        add_action('wp_ajax_sffc_save_user_preferences', array($this, 'handle_save_user_preferences'));
        add_action('wp_ajax_sffc_remove_profile_picture', array($this, 'handle_remove_profile_picture'));
        
        // Add action to localize script for AJAX
        add_action('wp_enqueue_scripts', array($this, 'localize_profile_scripts'));
        
        add_shortcode('sffc_professional_profile', array($this, 'render_professional_profile'));
        add_shortcode('sffc_profile_dashboard', array($this, 'render_profile_dashboard'));
        add_shortcode('sffc_profile_settings', array($this, 'render_profile_settings'));
    }

    public function render_professional_profile($atts = array())
    {
        if (!is_user_logged_in()) {
            return $this->render_login_prompt();
        }

        // Check if settings are requested
        if (isset($_GET['settings']) && $_GET['settings'] == '1') {
            return $this->render_profile_settings($atts);
        }

        $current_user_id = get_current_user_id();
        $profile_data = $this->get_complete_profile_data($current_user_id);

        // Get subscription plans for upgrade modal
        $subscription_plans = $this->get_subscription_plans();

        ob_start();
        include(plugin_dir_path(__FILE__) . '../templates/professional-profile-jpmorgan.php');
        return ob_get_clean();
    }

    public function render_profile_dashboard($atts = array())
    {
        if (!is_user_logged_in()) {
            return $this->render_login_prompt();
        }

        // Check if settings are requested
        if (isset($_GET['settings']) && $_GET['settings'] == '1') {
            return $this->render_profile_settings($atts);
        }

        $current_user_id = get_current_user_id();
        $profile_data = $this->get_complete_profile_data($current_user_id);
        
        ob_start();
        include(plugin_dir_path(__FILE__) . '../templates/professional-profile-jpmorgan.php');
        return ob_get_clean();
    }

    public function render_profile_settings($atts = array())
    {
        if (!is_user_logged_in()) {
            return $this->render_login_prompt();
        }

        $current_user_id = get_current_user_id();
        $profile_data = $this->get_complete_profile_data($current_user_id);
        
        // Enqueue settings assets
        wp_enqueue_style('senna-profile-settings', plugin_dir_url(__FILE__) . '../assets/css/professional-profile-settings.css', array(), '1.0.0');
        wp_enqueue_script('senna-profile-settings-js', plugin_dir_url(__FILE__) . '../assets/js/professional-profile-settings.js', array('jquery'), '1.0.0', true);
        
        ob_start();
        include(plugin_dir_path(__FILE__) . '../templates/professional-profile-settings.php');
        return ob_get_clean();
    }

    public function get_complete_profile_data($user_id)
    {
        $profile = $this->db->get_user_profile($user_id);
        $expertise = $this->db->get_user_expertise($user_id);
        $analytics = $this->db->get_user_analytics($user_id, 30);
        
        return array(
            'profile' => $profile,
            'expertise' => $expertise,
            'analytics' => $this->process_analytics_data($analytics),
            'completion_score' => $this->calculate_profile_completion($profile, $expertise)
        );
    }

    public function get_dashboard_data($user_id)
    {
        $profile_data = $this->get_complete_profile_data($user_id);
        
        return array_merge($profile_data, array(
            'recent_activity' => $this->get_recent_professional_activity($user_id),
            'networking_stats' => $this->get_networking_statistics($user_id),
            'subscription_info' => $this->get_subscription_details($user_id),
            'upcoming_events' => $this->get_upcoming_events($user_id),
            'introduction_requests' => $this->get_pending_introductions($user_id)
        ));
    }

    public function handle_profile_update()
    {
        check_ajax_referer('sffc_professional_profile', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('User not authenticated');
        }

        $user_id = get_current_user_id();
        $profile_data = array();

        $allowed_fields = array(
            'profile_title', 'professional_summary', 'current_position', 
            'current_company', 'years_experience', 'industry_focus',
            'linkedin_url', 'company_website', 'profile_visibility',
            'open_to_introductions', 'mentor_available', 'seeking_mentor',
            'introduction_bio'
        );

        foreach ($allowed_fields as $field) {
            if (isset($_POST[$field])) {
                $profile_data[$field] = sanitize_text_field($_POST[$field]);
            }
        }

        if (isset($_POST['expertise_areas']) && is_array($_POST['expertise_areas'])) {
            $profile_data['expertise_areas'] = array_map('sanitize_text_field', $_POST['expertise_areas']);
        }

        $result = $this->db->update_user_profile($user_id, $profile_data);
        
        if ($result !== false) {
            $this->db->log_user_activity($user_id, 'profile_update', 'Profile updated successfully');
            wp_send_json_success('Profile updated successfully');
        } else {
            wp_send_json_error('Failed to update profile');
        }
    }

    public function handle_add_expertise()
    {
        check_ajax_referer('sffc_professional_profile', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('User not authenticated');
        }

        $user_id = get_current_user_id();
        
        $expertise_data = array(
            'expertise_title' => sanitize_text_field($_POST['expertise_title']),
            'expertise_level' => sanitize_text_field($_POST['expertise_level'] ?? 'Expert'),
            'years_experience' => intval($_POST['years_experience'] ?? 0),
            'display_order' => intval($_POST['display_order'] ?? 0)
        );

        $result = $this->db->add_user_expertise($user_id, $expertise_data);
        
        if ($result) {
            $this->db->log_user_activity($user_id, 'expertise_added', $expertise_data['expertise_title']);
            wp_send_json_success('Expertise added successfully');
        } else {
            wp_send_json_error('Failed to add expertise');
        }
    }

    public function handle_get_profile_data()
    {
        check_ajax_referer('sffc_professional_profile', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('User not authenticated');
        }

        $user_id = get_current_user_id();
        $data = $this->get_complete_profile_data($user_id);
        
        wp_send_json_success($data);
    }

    public function handle_introduction_request()
    {
        check_ajax_referer('sffc_professional_profile', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('User not authenticated');
        }

        $requester_id = get_current_user_id();
        $target_id = intval($_POST['target_id']);
        $introduction_context = sanitize_textarea_field($_POST['introduction_context']);
        $introduction_reason = sanitize_textarea_field($_POST['introduction_reason'] ?? '');
        $mutual_interest = sanitize_text_field($_POST['mutual_interest'] ?? '');

        if (!$target_id || !$introduction_context) {
            wp_send_json_error('Missing required fields');
        }

        global $wpdb;
        $result = $wpdb->insert(
            $wpdb->prefix . 'sffc_professional_introductions',
            array(
                'requester_id' => $requester_id,
                'target_id' => $target_id,
                'introduction_context' => $introduction_context,
                'introduction_reason' => $introduction_reason,
                'mutual_interest' => $mutual_interest,
                'introduction_status' => 'pending'
            )
        );

        if ($result) {
            $this->db->log_user_activity($requester_id, 'introduction_request', "Request sent to user {$target_id}");
            wp_send_json_success('Introduction request sent successfully');
        } else {
            wp_send_json_error('Failed to send introduction request');
        }
    }

    public function handle_track_interaction()
    {
        check_ajax_referer('sffc_professional_profile', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('User not authenticated');
        }

        $user_id = get_current_user_id();
        $action = sanitize_text_field($_POST['interaction_action'] ?? '');
        $details = $_POST['details'] ?? array();

        if (!$action) {
            wp_send_json_error('Missing action');
        }

        $this->db->log_user_activity($user_id, $action, $details);
        wp_send_json_success('Interaction tracked');
    }

    public function handle_profile_picture_upload()
    {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sffc_professional_profile')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error('User not authenticated');
        }

        if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error('No file uploaded or upload error occurred');
        }

        $user_id = get_current_user_id();
        $file = $_FILES['profile_picture'];

        // Validate file
        $allowed_types = array('image/jpeg', 'image/jpg', 'image/png', 'image/gif');
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detected_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($detected_type, $allowed_types)) {
            wp_send_json_error('Invalid file type. Please upload a JPEG, PNG, or GIF image.');
        }

        if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
            wp_send_json_error('File too large. Please upload an image smaller than 5MB.');
        }

        // Handle upload
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        $upload_overrides = array(
            'test_form' => false,
            'unique_filename_callback' => function($dir, $name, $ext) use ($user_id) {
                return 'profile_' . $user_id . '_' . time() . $ext;
            }
        );

        $uploaded_file = wp_handle_upload($file, $upload_overrides);

        if ($uploaded_file && !isset($uploaded_file['error'])) {
            // Save URL to user meta
            update_user_meta($user_id, 'senna_profile_picture', $uploaded_file['url']);
            
            // Log activity
            $this->db->log_user_activity($user_id, 'profile_picture_upload', 'Profile picture updated');
            
            wp_send_json_success(array(
                'url' => $uploaded_file['url'],
                'message' => 'Profile picture uploaded successfully'
            ));
        } else {
            wp_send_json_error('Upload failed: ' . ($uploaded_file['error'] ?? 'Unknown error'));
        }
    }

    public function handle_save_career_preferences()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'sffc_professional_profile')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error('User not authenticated');
        }

        $user_id = get_current_user_id();
        
        $preferences = array(
            'preferred_locations' => sanitize_text_field($_POST['preferred_locations'] ?? ''),
            'salary_expectation' => sanitize_text_field($_POST['salary_expectation'] ?? ''),
            'job_alerts' => sanitize_text_field($_POST['job_alerts'] ?? 'weekly')
        );

        foreach ($preferences as $key => $value) {
            update_user_meta($user_id, 'senna_' . $key, $value);
        }

        $this->db->log_user_activity($user_id, 'career_preferences_saved', 'Career preferences updated');
        wp_send_json_success('Career preferences saved successfully');
    }

    public function handle_save_user_preferences()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'sffc_professional_profile')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error('User not authenticated');
        }

        $user_id = get_current_user_id();
        
        $preferences = array(
            'email_notifications' => intval($_POST['email_notifications'] ?? 1),
            'introduction_notifications' => intval($_POST['introduction_notifications'] ?? 1),
            'market_updates' => intval($_POST['market_updates'] ?? 1),
            'profile_visibility' => sanitize_text_field($_POST['profile_visibility'] ?? 'public')
        );

        foreach ($preferences as $key => $value) {
            update_user_meta($user_id, 'senna_' . $key, $value);
        }

        $this->db->log_user_activity($user_id, 'user_preferences_saved', 'User preferences updated');
        wp_send_json_success('Preferences saved successfully');
    }

    public function handle_remove_profile_picture()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'sffc_professional_profile')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error('User not authenticated');
        }

        $user_id = get_current_user_id();
        
        // Remove the profile picture URL from user meta
        delete_user_meta($user_id, 'senna_profile_picture');
        
        $this->db->log_user_activity($user_id, 'profile_picture_removed', 'Profile picture removed');
        wp_send_json_success('Profile picture removed successfully');
    }

    private function calculate_profile_completion($profile, $expertise)
    {
        $completion_items = array(
            'profile_title' => !empty($profile['profile_title']),
            'professional_summary' => !empty($profile['professional_summary']),
            'current_position' => !empty($profile['current_position']),
            'industry_focus' => !empty($profile['industry_focus']),
            'expertise_areas' => !empty($expertise),
            'introduction_bio' => !empty($profile['introduction_bio'])
        );

        $completed = array_sum($completion_items);
        $total = count($completion_items);
        
        return round(($completed / $total) * 100);
    }

    private function process_analytics_data($raw_analytics)
    {
        $processed = array(
            'senna_interactions' => 0,
            'profile_views' => 0,
            'introduction_requests' => 0,
            'networking_activity' => 0
        );

        foreach ($raw_analytics as $metric) {
            switch ($metric['metric_type']) {
                case 'senna_chat':
                    $processed['senna_interactions']++;
                    break;
                case 'profile_view':
                    $processed['profile_views']++;
                    break;
                case 'introduction_request':
                    $processed['introduction_requests']++;
                    break;
                case 'networking_activity':
                    $processed['networking_activity']++;
                    break;
            }
        }

        return $processed;
    }

    private function get_recent_professional_activity($user_id)
    {
        return array(
            array(
                'type' => 'expertise_verification',
                'title' => 'Financial Modeling expertise verified',
                'timestamp' => '2 hours ago'
            ),
            array(
                'type' => 'introduction_request',
                'title' => 'Introduction request from Sarah Chen',
                'timestamp' => '1 day ago'
            )
        );
    }

    private function get_networking_statistics($user_id)
    {
        global $wpdb;
        
        $connections = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}sffc_professional_networking 
                 WHERE user_id = %d AND connection_status = 'accepted'",
                $user_id
            )
        );

        return array(
            'total_connections' => intval($connections),
            'pending_introductions' => 3,
            'monthly_networking_score' => 85
        );
    }

    private function get_subscription_details($user_id)
    {
        return array(
            'plan_name' => 'MENA Careers Professional',
            'status' => 'active',
            'usage_percentage' => 68,
            'renewal_date' => '2024-02-15'
        );
    }

    private function get_upcoming_events($user_id)
    {
        return array(
            array(
                'title' => 'Private Equity Networking Summit',
                'date' => '2024-01-25',
                'location' => 'London'
            )
        );
    }

    private function get_pending_introductions($user_id)
    {
        return array(
            array(
                'requester_name' => 'Michael Torres',
                'context' => 'Seeking expertise in ESG investing',
                'mutual_connections' => 2
            )
        );
    }

    public function localize_profile_scripts()
    {
        if (wp_script_is('senna-jpmorgan-profile-js', 'registered')) {
            wp_localize_script('senna-jpmorgan-profile-js', 'sffc_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sffc_professional_profile')
            ));
        }
        
        if (wp_script_is('sffc-professional-profile-jpmorgan', 'registered')) {
            wp_localize_script('sffc-professional-profile-jpmorgan', 'sffc_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sffc_professional_profile')
            ));
        }
        
        if (wp_script_is('senna-profile-settings-js', 'registered')) {
            wp_localize_script('senna-profile-settings-js', 'sffc_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sffc_professional_profile')
            ));
        }
    }
    
    private function render_login_prompt()
    {
        return '<div class="sffc-login-prompt">
            <p>Please log in to access your professional profile.</p>
            <a href="' . wp_login_url() . '" class="sffc-login-button">Login</a>
        </div>';
    }

    /**
     * Get subscription plans for the modal
     * Includes Free tier for new users
     */
    public function get_subscription_plans()
    {
        return array(
            array(
                'name' => __('Professional', 'senna-finance'),
                'price' => __('Free', 'senna-finance'),
                'price_amount' => 0,
                'price_currency' => 'GBP',
                'billing_cycle' => '',
                'tagline' => __('Get started with core networking features.', 'senna-finance'),
                'audience' => __('For professionals exploring the platform.', 'senna-finance'),
                'slug' => 'free',
                'mp_url' => '',
                'features' => array(
                    __('Basic profile and visibility', 'senna-finance'),
                    __('3 introduction requests per month', 'senna-finance'),
                    __('Market intelligence feed', 'senna-finance'),
                    __('Job alerts', 'senna-finance')
                ),
                'shortcode' => ''
            ),
            array(
                'name' => __('Executive', 'senna-finance'),
                'price' => __('£49.99 / month', 'senna-finance'),
                'price_amount' => 49.99,
                'price_currency' => 'GBP',
                'billing_cycle' => __('per month', 'senna-finance'),
                'tagline' => __('Full access to career tools and networking.', 'senna-finance'),
                'audience' => __('For professionals serious about career advancement.', 'senna-finance'),
                'slug' => 'career',
                'mp_url' => 'https://joinsenna.com/memberships/career/',
                'features' => array(
                    __('Unlimited introduction requests', 'senna-finance'),
                    __('Priority visibility to recruiters', 'senna-finance'),
                    __('AI-powered CV tailoring', 'senna-finance'),
                    __('Deal intelligence alerts', 'senna-finance'),
                    __('Exclusive PE events access', 'senna-finance')
                ),
                'shortcode' => '[mepr-membership-registration-form id="102"]'
            ),
            array(
                'name' => __('Elite', 'senna-finance'),
                'price' => __('£69.99 / month', 'senna-finance'),
                'price_amount' => 69.99,
                'price_currency' => 'GBP',
                'billing_cycle' => __('per month', 'senna-finance'),
                'tagline' => __('Premium access with executive coaching.', 'senna-finance'),
                'audience' => __('For leaders who want the complete package.', 'senna-finance'),
                'slug' => 'elite',
                'mp_url' => 'https://joinsenna.com/memberships/elite/',
                'features' => array(
                    __('Everything in Executive', 'senna-finance'),
                    __('Monthly 1:1 executive coaching', 'senna-finance'),
                    __('Recruiter outreach on your behalf', 'senna-finance'),
                    __('Priority support', 'senna-finance'),
                    __('Unlimited interview practice', 'senna-finance')
                ),
                'shortcode' => '[mepr-membership-registration-form id="103"]'
            )
        );
    }
}

SFFC_Professional_Profile_Manager::get_instance()->init();
