<?php
/**
 * Matcher Messaging Integration
 * Main integration class that coordinates the CV-based matching and messaging system
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Matcher_Messaging_Integration {
    
    private static $instance = null;
    private $components_loaded = false;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', [$this, 'initialize_integration'], 5);
        add_action('wp_loaded', [$this, 'verify_integration']);
    }
    
    /**
     * Initialize the integration system
     */
    public function initialize_integration() {
        try {
            // Load all required components
            $this->load_components();
            
            // Set up integration hooks
            $this->setup_integration_hooks();
            
            // Initialize database
            $this->initialize_database();
            
            $this->components_loaded = true;
            
            error_log("Matcher-Messaging Integration initialized successfully");
            
        } catch (Exception $e) {
            error_log("Error initializing Matcher-Messaging Integration: " . $e->getMessage());
        }
    }
    
    /**
     * Load all required components
     */
    private function load_components() {
        $component_files = [
            'class-matcher-database-setup.php',
            'class-cv-match-processor.php',
            'class-automatic-message-sender.php',
            'class-career-insights-sequence.php',
            'class-claude-insights-generator.php'
        ];
        
        // Load admin components
        if (is_admin()) {
            $component_files[] = '../admin/class-matcher-messaging-admin.php';
        }
        
        foreach ($component_files as $file) {
            $file_path = dirname(__FILE__) . '/' . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            } else {
                throw new Exception("Required component file not found: $file");
            }
        }
        
        // Verify classes are loaded
        $required_classes = [
            'SFFC_Matcher_Database_Setup',
            'SFFC_CV_Match_Processor',
            'SFFC_Automatic_Message_Sender'
        ];
        
        foreach ($required_classes as $class) {
            if (!class_exists($class)) {
                throw new Exception("Required class not loaded: $class");
            }
        }
    }
    
    /**
     * Setup integration hooks between components
     */
    private function setup_integration_hooks() {
        // Hook CV processing to trigger matching
        add_action('sffc_cv_uploaded', [$this, 'handle_cv_uploaded'], 10, 2);
        add_action('sffc_cv_parsed', [$this, 'handle_cv_parsed'], 10, 2);

        // Background processing hooks
        add_action('sffc_process_user_matches', [$this, 'process_user_matches_background']);
        
        // Admin hooks for monitoring
        add_action('admin_init', [$this, 'admin_integration_checks']);
        
        // AJAX hooks for frontend integration
        add_action('wp_ajax_sffc_get_match_status', [$this, 'ajax_get_match_status']);
        add_action('wp_ajax_sffc_test_matching', [$this, 'ajax_test_matching']);
        
        // Cleanup hooks
        add_action('sffc_daily_integration_cleanup', [$this, 'daily_cleanup']);
        
        // Schedule daily cleanup if not already scheduled
        if (!wp_next_scheduled('sffc_daily_integration_cleanup')) {
            wp_schedule_event(time(), 'daily', 'sffc_daily_integration_cleanup');
        }
    }
    
    /**
     * Initialize database components
     */
    private function initialize_database() {
        // Database setup handles its own initialization
        SFFC_Matcher_Database_Setup::get_instance();
    }
    
    /**
     * Handle CV uploaded event
     */
    public function handle_cv_uploaded($user_id, $cv_data) {
        try {
            error_log("Integration: Handling CV uploaded for user $user_id");
            
            // Process CV data for matching
            $cv_processor = SFFC_CV_Match_Processor::get_instance();
            $cv_processor->process_cv_for_matching($user_id, $cv_data);
            
        } catch (Exception $e) {
            error_log("Error handling CV uploaded: " . $e->getMessage());
        }
    }
    
    /**
     * Handle CV parsed event
     */
    public function handle_cv_parsed($user_id, $cv_data) {
        try {
            error_log("Integration: Handling CV parsed for user $user_id");
            
            // Trigger automatic matching
            $cv_processor = SFFC_CV_Match_Processor::get_instance();
            $cv_processor->trigger_automatic_matching($user_id, $cv_data);
            
        } catch (Exception $e) {
            error_log("Error handling CV parsed: " . $e->getMessage());
        }
    }
    
    /**
     * Process user matches in background
     */
    public function process_user_matches_background($user_id) {
        try {
            error_log("Integration: Processing user matches in background for user $user_id");
            
            $message_sender = SFFC_Automatic_Message_Sender::get_instance();
            $message_sender->process_user_matches($user_id);
            
        } catch (Exception $e) {
            error_log("Error processing user matches: " . $e->getMessage());
        }
    }

    /**
     * Admin integration checks
     */
    public function admin_integration_checks() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Check if components are working
        $this->verify_component_health();
    }
    
    /**
     * Verify integration is working properly
     */
    public function verify_integration() {
        if (!$this->components_loaded) {
            error_log("Matcher-Messaging Integration: Components not loaded properly");
            return false;
        }
        
        // Verify database tables exist
        if (!$this->verify_database_tables()) {
            error_log("Matcher-Messaging Integration: Database tables missing");
            return false;
        }
        
        // Verify messaging system is available
        if (!$this->verify_messaging_system()) {
            error_log("Matcher-Messaging Integration: Messaging system not available");
            return false;
        }
        
        return true;
    }
    
    /**
     * Verify database tables exist
     */
    private function verify_database_tables() {
        global $wpdb;
        
        $required_tables = [
            $wpdb->prefix . 'sffc_sent_matches',
            $wpdb->prefix . 'sffc_cv_extraction_cache',
            $wpdb->prefix . 'senna_messages' // From messaging dashboard
        ];
        
        foreach ($required_tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
                error_log("Missing required table: $table");
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Verify messaging system is available
     */
    private function verify_messaging_system() {
        // Check if messaging dashboard is active
        if (!class_exists('SkillFarm_Messaging_Messages')) {
            return false;
        }
        
        // Check if Emily Bradshaw user exists
        $emily = get_user_by('id', 28134);
        if (!$emily) {
            error_log("Emily Bradshaw user (ID: 28134) not found");
            return false;
        }
        
        return true;
    }
    
    /**
     * Verify component health
     */
    private function verify_component_health() {
        $health_status = [];
        
        // Check CV processor
        try {
            $cv_processor = SFFC_CV_Match_Processor::get_instance();
            $health_status['cv_processor'] = 'healthy';
        } catch (Exception $e) {
            $health_status['cv_processor'] = 'error: ' . $e->getMessage();
        }
        
        // Title matcher removed - sffc_job system deprecated
        $health_status['title_matcher'] = 'deprecated';
        
        // Check message sender
        try {
            $message_sender = SFFC_Automatic_Message_Sender::get_instance();
            $health_status['message_sender'] = 'healthy';
        } catch (Exception $e) {
            $health_status['message_sender'] = 'error: ' . $e->getMessage();
        }
        
        // Check database
        try {
            $db_setup = SFFC_Matcher_Database_Setup::get_instance();
            $stats = $db_setup->get_database_stats();
            $health_status['database'] = 'healthy - ' . count($stats) . ' tables';
        } catch (Exception $e) {
            $health_status['database'] = 'error: ' . $e->getMessage();
        }
        
        // Store health status for admin display
        update_option('sffc_integration_health', $health_status);
        
        return $health_status;
    }
    
    /**
     * AJAX: Get match status for user
     */
    public function ajax_get_match_status() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Not authenticated']);
        }
        
        $user_id = get_current_user_id();
        
        global $wpdb;
        $sent_table = $wpdb->prefix . 'sffc_sent_matches';
        
        // Get recent matches
        $recent_matches = $wpdb->get_results($wpdb->prepare(
            "SELECT sm.*, p.post_title as job_title 
             FROM $sent_table sm 
             LEFT JOIN {$wpdb->posts} p ON sm.job_id = p.ID 
             WHERE sm.user_id = %d 
             ORDER BY sm.sent_at DESC 
             LIMIT 10",
            $user_id
        ));
        
        // Get match statistics
        $stats = [
            'total_matches' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $sent_table WHERE user_id = %d",
                $user_id
            )),
            'avg_score' => $wpdb->get_var($wpdb->prepare(
                "SELECT AVG(match_score) FROM $sent_table WHERE user_id = %d",
                $user_id
            )),
            'this_week' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $sent_table WHERE user_id = %d AND sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
                $user_id
            ))
        ];
        
        wp_send_json_success([
            'recent_matches' => $recent_matches,
            'statistics' => $stats,
            'integration_status' => $this->verify_integration()
        ]);
    }
    
    /**
     * AJAX: Test matching system
     */
    public function ajax_test_matching() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        // Job title matching system deprecated - sffc_job removed
        wp_send_json_error(['message' => 'Job matching system has been deprecated']);
    }
    
    /**
     * Daily cleanup routine
     */
    public function daily_cleanup() {
        try {
            // Run database cleanup
            $db_setup = SFFC_Matcher_Database_Setup::get_instance();
            $db_setup->cleanup_old_data();
            
            // Run message sender cleanup
            $message_sender = SFFC_Automatic_Message_Sender::get_instance();
            $message_sender->cleanup_old_matches();
            
            // Update health status
            $this->verify_component_health();
            
            error_log("Integration daily cleanup completed");
            
        } catch (Exception $e) {
            error_log("Error during daily cleanup: " . $e->getMessage());
        }
    }
    
    /**
     * Get integration statistics for monitoring
     */
    public function get_integration_statistics() {
        global $wpdb;
        
        $stats = [];
        
        // Get database statistics
        $db_setup = SFFC_Matcher_Database_Setup::get_instance();
        $stats['database'] = $db_setup->get_database_stats();
        
        // Get matching statistics
        $sent_table = $wpdb->prefix . 'sffc_sent_matches';
        $stats['matching'] = [
            'total_matches_sent' => $wpdb->get_var("SELECT COUNT(*) FROM $sent_table"),
            'matches_today' => $wpdb->get_var("SELECT COUNT(*) FROM $sent_table WHERE DATE(sent_at) = CURDATE()"),
            'matches_this_week' => $wpdb->get_var("SELECT COUNT(*) FROM $sent_table WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"),
            'avg_match_score' => $wpdb->get_var("SELECT AVG(match_score) FROM $sent_table"),
            'response_rate' => $wpdb->get_var("SELECT (COUNT(CASE WHEN user_response != 'pending' THEN 1 END) / COUNT(*)) * 100 FROM $sent_table")
        ];
        
        // Get CV processing statistics
        $cache_table = $wpdb->prefix . 'sffc_cv_extraction_cache';
        $stats['cv_processing'] = [
            'total_cvs_processed' => $wpdb->get_var("SELECT COUNT(*) FROM $cache_table"),
            'avg_confidence' => $wpdb->get_var("SELECT AVG(extraction_confidence) FROM $cache_table"),
            'processed_today' => $wpdb->get_var("SELECT COUNT(*) FROM $cache_table WHERE DATE(extracted_at) = CURDATE()")
        ];
        
        return $stats;
    }
    
    /**
     * Manual trigger for testing (admin use)
     */
    public function manual_trigger_matching($user_id) {
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        try {
            // Trigger user matching
            $this->process_user_matches_background($user_id);
            return true;
        } catch (Exception $e) {
            error_log("Error in manual trigger: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if integration is properly configured
     */
    public function is_properly_configured() {
        // Check all required components
        $required_checks = [
            $this->components_loaded,
            $this->verify_database_tables(),
            $this->verify_messaging_system(),
            class_exists('SFFC_Job_Matcher'), // Original job matcher
            class_exists('SFFC_User_Profile_Manager') // User profiles
        ];
        
        return !in_array(false, $required_checks);
    }
    
    /**
     * Get configuration status for admin
     */
    public function get_configuration_status() {
        return [
            'components_loaded' => $this->components_loaded,
            'database_tables' => $this->verify_database_tables(),
            'messaging_system' => $this->verify_messaging_system(),
            'job_matcher' => class_exists('SFFC_Job_Matcher'),
            'user_profiles' => class_exists('SFFC_User_Profile_Manager'),
            'integration_health' => get_option('sffc_integration_health', []),
            'overall_status' => $this->is_properly_configured()
        ];
    }
}

// Initialize the integration
SFFC_Matcher_Messaging_Integration::get_instance();