<?php
/**
 * MemberPress Campaigns Initialization
 * 
 * Main initialization file for the MemberPress Campaigns system
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_MemberPress_Campaigns_Init {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Only proceed if WordPress is loaded enough
        if (!defined('ABSPATH')) {
            return;
        }
        
        // Load dependencies
        $this->load_dependencies();
        
        // Initialize components - use init instead of plugins_loaded for better compatibility
        add_action('init', [$this, 'init_components'], 10);
        
        // Register activation/deactivation hooks only if SFFC_PLUGIN_FILE is defined
        if (defined('SFFC_PLUGIN_FILE')) {
            register_activation_hook(SFFC_PLUGIN_FILE, [$this, 'activate']);
            register_deactivation_hook(SFFC_PLUGIN_FILE, [$this, 'deactivate']);
        }
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        if (defined('SFFC_PLUGIN_DIR')) {
            $plugin_dir = trailingslashit(SFFC_PLUGIN_DIR);
        } else {
            $plugin_dir = trailingslashit(dirname(__DIR__));
        }
        
        // Core classes - Load these first as they're dependencies
        if (file_exists($plugin_dir . 'includes/class-memberpress-campaigns-schema.php')) {
            require_once $plugin_dir . 'includes/class-memberpress-campaigns-schema.php';
        }
        
        if (file_exists($plugin_dir . 'includes/class-memberpress-campaigns-manager.php')) {
            require_once $plugin_dir . 'includes/class-memberpress-campaigns-manager.php';
        }
        
        // Admin classes (only in admin) - Load after core classes
        if (is_admin()) {
            if (file_exists($plugin_dir . 'admin/class-memberpress-campaigns-admin.php')) {
                require_once $plugin_dir . 'admin/class-memberpress-campaigns-admin.php';
            }
        }
    }
    
    /**
     * Initialize components
     */
    public function init_components() {
        // Check if MemberPress is active
        if (!class_exists('MeprUser')) {
            add_action('admin_notices', [$this, 'memberpress_missing_notice']);
            return;
        }
        
        // Initialize campaign manager
        SFFC_MemberPress_Campaigns_Manager::get_instance();
        
        // Initialize admin if in admin area
        if (is_admin()) {
            SFFC_MemberPress_Campaigns_Admin::get_instance();
        }
        
        // Add AJAX handlers for frontend
        $this->register_ajax_handlers();
        
        // Add tracking pixel handler
        add_action('init', [$this, 'handle_tracking']);
    }
    
    /**
     * Register AJAX handlers
     */
    private function register_ajax_handlers() {
        // Public AJAX handlers for email tracking
        add_action('wp_ajax_nopriv_mp_track_email_open', [$this, 'track_email_open']);
        add_action('wp_ajax_nopriv_mp_track_email_click', [$this, 'track_email_click']);
        add_action('wp_ajax_nopriv_mp_unsubscribe', [$this, 'handle_unsubscribe']);
        
        // Conversion tracking
        add_action('wp_ajax_mp_track_conversion', [$this, 'track_conversion']);
        add_action('wp_ajax_nopriv_mp_track_conversion', [$this, 'track_conversion']);
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        SFFC_MemberPress_Campaigns_Schema::create_tables();
        
        // Schedule cron jobs
        if (!wp_next_scheduled('mp_campaigns_process_queue')) {
            wp_schedule_event(time(), 'every_5_minutes', 'mp_campaigns_process_queue');
        }
        
        if (!wp_next_scheduled('mp_campaigns_update_stats')) {
            wp_schedule_event(time(), 'hourly', 'mp_campaigns_update_stats');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('mp_campaigns_process_queue');
        wp_clear_scheduled_hook('mp_campaigns_update_stats');
    }
    
    /**
     * Handle email tracking
     */
    public function handle_tracking() {
        // Handle tracking pixel for email opens
        if (isset($_GET['mp_track']) && $_GET['mp_track'] === 'open') {
            $this->track_email_open();
        }
        
        // Handle click tracking
        if (isset($_GET['mp_track']) && $_GET['mp_track'] === 'click') {
            $this->track_email_click();
        }
        
        // Handle unsubscribe
        if (isset($_GET['action']) && $_GET['action'] === 'unsubscribe') {
            $this->handle_unsubscribe_link();
        }
    }
    
    /**
     * Track email open
     */
    public function track_email_open() {
        $tracking_id = sanitize_text_field($_GET['tracking_id'] ?? '');
        
        if ($tracking_id) {
            global $wpdb;
            
            // Update email record
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}mp_campaign_emails 
                 SET opened_at = CASE WHEN opened_at IS NULL THEN NOW() ELSE opened_at END,
                     open_count = open_count + 1
                 WHERE tracking_id = %s",
                $tracking_id
            ));
        }
        
        // Return 1x1 transparent pixel
        header('Content-Type: image/gif');
        echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        exit;
    }
    
    /**
     * Track email click
     */
    public function track_email_click() {
        $tracking_id = sanitize_text_field($_GET['tracking_id'] ?? '');
        $url = esc_url_raw($_GET['url'] ?? '');
        
        if ($tracking_id) {
            global $wpdb;
            
            // Update email record
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}mp_campaign_emails 
                 SET clicked_at = CASE WHEN clicked_at IS NULL THEN NOW() ELSE clicked_at END,
                     click_count = click_count + 1
                 WHERE tracking_id = %s",
                $tracking_id
            ));
            
            // Log click activity
            $email = $wpdb->get_row($wpdb->prepare(
                "SELECT campaign_id, campaign_user_id 
                 FROM {$wpdb->prefix}mp_campaign_emails 
                 WHERE tracking_id = %s",
                $tracking_id
            ));
            
            if ($email) {
                $wpdb->insert(
                    "{$wpdb->prefix}mp_campaign_activity",
                    [
                        'campaign_id' => $email->campaign_id,
                        'action' => 'email_clicked',
                        'details' => 'Clicked link: ' . $url,
                        'result' => 'success'
                    ]
                );
            }
        }
        
        // Redirect to target URL
        if ($url) {
            wp_redirect($url);
            exit;
        }
    }
    
    /**
     * Handle unsubscribe
     */
    public function handle_unsubscribe_link() {
        $campaign_id = intval($_GET['campaign'] ?? 0);
        $user_id = intval($_GET['user'] ?? 0);
        
        if ($campaign_id && $user_id) {
            global $wpdb;
            
            // Update user status
            $wpdb->update(
                "{$wpdb->prefix}mp_campaign_users",
                ['unsubscribed' => 1],
                [
                    'campaign_id' => $campaign_id,
                    'id' => $user_id
                ]
            );
            
            // Show unsubscribe confirmation page
            wp_die('You have been successfully unsubscribed from this campaign.', 'Unsubscribed');
        }
    }
    
    /**
     * Track conversion
     */
    public function track_conversion() {
        $campaign_id = intval($_POST['campaign_id'] ?? 0);
        $user_id = intval($_POST['user_id'] ?? 0);
        $subscription_id = intval($_POST['subscription_id'] ?? 0);
        $amount = floatval($_POST['amount'] ?? 0);
        
        if ($campaign_id && $user_id) {
            global $wpdb;
            
            // Record conversion
            $wpdb->insert(
                "{$wpdb->prefix}mp_campaign_conversions",
                [
                    'campaign_id' => $campaign_id,
                    'campaign_user_id' => $user_id,
                    'subscription_id' => $subscription_id,
                    'conversion_value' => $amount,
                    'conversion_type' => 'subscription'
                ]
            );
            
            // Update campaign user status
            $wpdb->update(
                "{$wpdb->prefix}mp_campaign_users",
                [
                    'status' => 'converted',
                    'converted_at' => current_time('mysql'),
                    'conversion_value' => $amount
                ],
                ['id' => $user_id]
            );
            
            wp_send_json_success(['message' => 'Conversion tracked']);
        }
        
        wp_send_json_error('Invalid parameters');
    }
    
    /**
     * MemberPress missing notice
     */
    public function memberpress_missing_notice() {
        ?>
        <div class="notice notice-warning">
            <p><?php _e('MemberPress Campaigns requires MemberPress to be installed and activated.', 'sffc'); ?></p>
        </div>
        <?php
    }
}

// Initialize the campaigns system
SFFC_MemberPress_Campaigns_Init::get_instance();
