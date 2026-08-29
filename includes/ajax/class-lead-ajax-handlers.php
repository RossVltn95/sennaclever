<?php

/**
 * Lead AJAX Handlers
 * Handles lead collection and user checking
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Lead_Ajax_Handlers
{

    private static $instance = null;

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
        // Save lead - allow both logged in and non-logged in users
        add_action('wp_ajax_sffc_save_lead', array($this, 'save_lead'));
        add_action('wp_ajax_nopriv_sffc_save_lead', array($this, 'save_lead'));

        // Check if user exists
        add_action('wp_ajax_sffc_check_user_exists', array($this, 'check_user_exists'));
        add_action('wp_ajax_nopriv_sffc_check_user_exists', array($this, 'check_user_exists'));

        // Get lead stats (admin only)
        add_action('wp_ajax_sffc_get_lead_stats', array($this, 'get_lead_stats'));

        // Export leads (admin only)
        add_action('wp_ajax_sffc_export_leads', array($this, 'export_leads'));

        // Secret signup from recruiter express interest
        add_action('wp_ajax_sffc_quick_register_candidate', array($this, 'quick_register_candidate'));
        add_action('wp_ajax_nopriv_sffc_quick_register_candidate', array($this, 'quick_register_candidate'));
    }

    /**
     * Save lead data
     */
    public function save_lead()
    {
        // Verify nonce (optional for lead collection to reduce friction)
        $nonce_valid = check_ajax_referer('sffc_frontend_nonce', 'nonce', false);

        // Get lead data
        $lead_data = isset($_POST['lead_data']) ? $_POST['lead_data'] : array();

        if (empty($lead_data)) {
            wp_send_json_error(array('message' => 'No lead data provided'));
            return;
        }

        // Load the leads database class
        if (!class_exists('SFFC_Application_Leads_DB')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-application-leads-db.php';
        }

        $leads_db = SFFC_Application_Leads_DB::get_instance();

        // Add user ID if logged in
        if (is_user_logged_in()) {
            $lead_data['user_id'] = get_current_user_id();
            $lead_data['is_registered'] = 1;
        }

        // Save the lead
        $result = $leads_db->save_lead($lead_data);

        if ($result) {
            // Check if this email is already registered
            $user_exists = false;
            if (!empty($lead_data['email'])) {
                $user_exists = email_exists($lead_data['email']);
            }

            wp_send_json_success(array(
                'message' => 'Lead saved successfully',
                'lead_id' => $result,
                'user_exists' => $user_exists
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to save lead'));
        }
    }

    /**
     * Check if user exists by email
     */
    public function check_user_exists()
    {
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

        if (empty($email)) {
            wp_send_json_error(array('message' => 'Email is required'));
            return;
        }

        // Check if email exists in WordPress users
        $user_exists = email_exists($email);

        // Load the leads database class
        if (!class_exists('SFFC_Application_Leads_DB')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-application-leads-db.php';
        }

        $leads_db = SFFC_Application_Leads_DB::get_instance();

        // Check if we have this lead
        $lead = $leads_db->get_lead_by_email($email);

        $response = array(
            'exists' => (bool) $user_exists,
            'user_id' => $user_exists,
            'has_lead' => !empty($lead),
            'lead_data' => null
        );

        // If user exists and is logged in, get their profile data
        if ($user_exists && is_user_logged_in() && get_current_user_id() == $user_exists) {
            $user = get_userdata($user_exists);
            $response['lead_data'] = array(
                'name' => $user->display_name,
                'email' => $user->user_email
            );
        }

        wp_send_json_success($response);
    }

    /**
     * Get lead statistics (admin only)
     */
    public function get_lead_stats()
    {
        // Check admin permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }

        // Verify nonce
        check_ajax_referer('sffc_admin_nonce', 'nonce');

        // Load the leads database class
        if (!class_exists('SFFC_Application_Leads_DB')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-application-leads-db.php';
        }

        $leads_db = SFFC_Application_Leads_DB::get_instance();
        $stats = $leads_db->get_stats();

        wp_send_json_success($stats);
    }

    /**
     * Export leads to CSV (admin only)
     */
    public function export_leads()
    {
        // Check admin permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }

        // Verify nonce
        check_ajax_referer('sffc_admin_nonce', 'nonce');

        // Load the leads database class
        if (!class_exists('SFFC_Application_Leads_DB')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-application-leads-db.php';
        }

        $leads_db = SFFC_Application_Leads_DB::get_instance();

        // Get filter parameters
        $args = array(
            'search' => isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '',
            'is_registered' => isset($_POST['is_registered']) ? intval($_POST['is_registered']) : null,
            'date_from' => isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : null,
            'date_to' => isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : null
        );

        $csv_data = $leads_db->export_to_csv($args);

        if (empty($csv_data)) {
            wp_send_json_error(array('message' => 'No leads to export'));
            return;
        }

        // Generate CSV content
        $csv_content = '';
        foreach ($csv_data as $row) {
            $csv_content .= implode(',', array_map(function ($field) {
                return '"' . str_replace('"', '""', $field) . '"';
            }, $row)) . "\n";
        }

        // Create filename
        $filename = 'application-leads-' . date('Y-m-d-His') . '.csv';

        // Return CSV data
        wp_send_json_success(array(
            'filename' => $filename,
            'content' => base64_encode($csv_content),
            'mime_type' => 'text/csv'
        ));
    }

    /**
     * Quick registration for recruiter express interest flow.
     */
    public function quick_register_candidate()
    {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
        if (!wp_verify_nonce($nonce, 'sffc_express_interest_nonce')) {
            wp_send_json_error(array('message' => 'Invalid request.'), 403);
        }

        if (is_user_logged_in()) {
            wp_send_json_success(array(
                'status'  => 'logged_in',
                'message' => __('You are already signed in.', 'senna-finance'),
            ));
        }

        $first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '';
        $last_name = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $redirect_to = isset($_POST['redirect_to']) ? esc_url_raw($_POST['redirect_to']) : home_url('/memberships/');

        if (empty($first_name) || empty($last_name) || empty($email)) {
            wp_send_json_error(array('message' => __('Please complete all fields before continuing.', 'senna-finance')));
        }

        if (!is_email($email)) {
            wp_send_json_error(array('message' => __('Enter a valid email address to continue.', 'senna-finance')));
        }

        if ($existing = get_user_by('email', $email)) {
            if (class_exists('SFFC_CRM_Shortcodes') && method_exists(SFFC_CRM_Shortcodes::get_instance(), 'stop_membership_signup_reminders')) {
                SFFC_CRM_Shortcodes::get_instance()->stop_membership_signup_reminders($email);
            }
            wp_send_json_success(array(
                'status'    => 'exists',
                'message'   => __('This email already has a MENA Careers account. Continue to memberships to choose your plan.', 'senna-finance'),
                'redirect'  => home_url('/memberships/'),
            ));
        }

        if (class_exists('SFFC_CRM_Shortcodes') && method_exists(SFFC_CRM_Shortcodes::get_instance(), 'capture_membership_signup_lead')) {
            SFFC_CRM_Shortcodes::get_instance()->capture_membership_signup_lead(array(
                'email' => $email,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'source' => 'quick_register_candidate',
            ));
        } else {
            update_option('sffc_membership_signup_lead_' . md5(strtolower($email)), array(
                'email' => $email,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'source' => 'quick_register_candidate',
                'created_at' => time(),
            ), false);
        }

        wp_send_json_success(array(
            'status'   => 'lead_captured',
            'message'  => __('Thanks. Continue to memberships to choose the plan that fits your search.', 'senna-finance'),
            'redirect' => home_url('/memberships/'),
        ));
    }
}

// Initialize
SFFC_Lead_Ajax_Handlers::get_instance();
