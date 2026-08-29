<?php
/**
 * CV AJAX Handlers
 * Handles saving and retrieving user CVs from database
 * 
 * @package SennaCareers
 * @since 10.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CV_Ajax_Handlers {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // AJAX actions for logged-in users
        add_action('wp_ajax_sffc_save_user_cv', array($this, 'save_user_cv'));
        add_action('wp_ajax_sffc_get_user_cv', array($this, 'get_user_cv'));
        add_action('wp_ajax_sffc_check_user_cv', array($this, 'check_user_cv'));
        add_action('wp_ajax_sffc_delete_user_cv', array($this, 'delete_user_cv'));
    }
    
    /**
     * Save user CV to database
     */
    public function save_user_cv() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sffc_ajax_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array(
                'message' => 'You must be logged in to save your CV.'
            ));
            return;
        }
        
        $user_id = get_current_user_id();
        
        // Get CV data from request
        $cv_content = isset($_POST['cv_content']) ? wp_kses_post($_POST['cv_content']) : '';
        $cv_parsed = isset($_POST['cv_parsed']) ? $_POST['cv_parsed'] : '';
        $file_name = isset($_POST['file_name']) ? sanitize_text_field($_POST['file_name']) : '';
        $file_type = isset($_POST['file_type']) ? sanitize_text_field($_POST['file_type']) : 'text';
        
        // Parse CV data if provided as JSON
        if (is_string($cv_parsed)) {
            $cv_parsed = json_decode(stripslashes($cv_parsed), true);
        }
        
        if (empty($cv_content)) {
            wp_send_json_error(array(
                'message' => 'CV content cannot be empty.'
            ));
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'sffc_user_cvs';
        
        // First, deactivate any existing active CVs for this user
        $wpdb->update(
            $table_name,
            array('is_active' => 0),
            array('user_id' => $user_id, 'is_active' => 1),
            array('%d'),
            array('%d', '%d')
        );
        
        // Insert new CV
        $result = $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'cv_content' => $cv_content,
                'cv_parsed' => json_encode($cv_parsed),
                'file_name' => $file_name,
                'file_type' => $file_type,
                'seniority' => isset($cv_parsed['seniority']) ? intval($cv_parsed['seniority']) : null,
                'location' => isset($cv_parsed['location']) ? $cv_parsed['location'] : '',
                'latest_role' => isset($cv_parsed['latestRole']) ? $cv_parsed['latestRole'] : '',
                'company' => isset($cv_parsed['company']) ? $cv_parsed['company'] : '',
                'skills' => isset($cv_parsed['skills']) ? json_encode($cv_parsed['skills']) : '',
                'uploaded_date' => current_time('mysql'),
                'last_used' => current_time('mysql'),
                'is_active' => 1
            ),
            array(
                '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d'
            )
        );
        
        if ($result === false) {
            wp_send_json_error(array(
                'message' => 'Failed to save CV to database.',
                'error' => $wpdb->last_error
            ));
            return;
        }
        
        // Return success with CV ID
        wp_send_json_success(array(
            'message' => 'CV saved successfully.',
            'cv_id' => $wpdb->insert_id,
            'user_id' => $user_id
        ));
    }
    
    /**
     * Get user's active CV from database
     */
    public function get_user_cv() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sffc_ajax_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array(
                'message' => 'You must be logged in to retrieve your CV.'
            ));
            return;
        }
        
        $user_id = get_current_user_id();
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'sffc_user_cvs';
        
        // Get active CV for user
        $cv = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d AND is_active = 1 ORDER BY uploaded_date DESC LIMIT 1",
            $user_id
        ));
        
        if (!$cv) {
            wp_send_json_error(array(
                'message' => 'No CV found for this user.',
                'has_cv' => false
            ));
            return;
        }
        
        // Update last_used timestamp
        $wpdb->update(
            $table_name,
            array('last_used' => current_time('mysql')),
            array('id' => $cv->id),
            array('%s'),
            array('%d')
        );
        
        // Parse the stored JSON data
        $cv_parsed = json_decode($cv->cv_parsed, true);
        $skills = json_decode($cv->skills, true);
        
        wp_send_json_success(array(
            'cv_id' => $cv->id,
            'cv_content' => $cv->cv_content,
            'cv_parsed' => $cv_parsed,
            'file_name' => $cv->file_name,
            'file_type' => $cv->file_type,
            'seniority' => $cv->seniority,
            'location' => $cv->location,
            'latest_role' => $cv->latest_role,
            'company' => $cv->company,
            'skills' => $skills,
            'uploaded_date' => $cv->uploaded_date,
            'has_cv' => true
        ));
    }
    
    /**
     * Check if user has an active CV
     */
    public function check_user_cv() {
        // This can be called without nonce for initial page load checks
        if (!is_user_logged_in()) {
            wp_send_json_success(array(
                'has_cv' => false,
                'logged_in' => false
            ));
            return;
        }
        
        $user_id = get_current_user_id();
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'sffc_user_cvs';
        
        // Check if user has an active CV
        $has_cv = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE user_id = %d AND is_active = 1",
            $user_id
        ));
        
        wp_send_json_success(array(
            'has_cv' => $has_cv > 0,
            'logged_in' => true,
            'user_id' => $user_id
        ));
    }
    
    /**
     * Delete/deactivate user's CV
     */
    public function delete_user_cv() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sffc_ajax_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array(
                'message' => 'You must be logged in to delete your CV.'
            ));
            return;
        }
        
        $user_id = get_current_user_id();
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'sffc_user_cvs';
        
        // Deactivate all CVs for this user
        $result = $wpdb->update(
            $table_name,
            array('is_active' => 0),
            array('user_id' => $user_id),
            array('%d'),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error(array(
                'message' => 'Failed to delete CV.',
                'error' => $wpdb->last_error
            ));
            return;
        }
        
        wp_send_json_success(array(
            'message' => 'CV deleted successfully.'
        ));
    }
    
    /**
     * Helper method to get user's CV data
     */
    public static function get_user_cv_data($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return false;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'sffc_user_cvs';
        
        $cv = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d AND is_active = 1 ORDER BY uploaded_date DESC LIMIT 1",
            $user_id
        ));
        
        if (!$cv) {
            return false;
        }
        
        // Parse stored data
        $cv->cv_parsed = json_decode($cv->cv_parsed, true);
        $cv->skills = json_decode($cv->skills, true);
        
        return $cv;
    }
}

// Initialize the class
SFFC_CV_Ajax_Handlers::get_instance();