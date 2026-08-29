<?php
/**
 * User Manager Class
 * 
 * @package SennaCareers
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_User_Manager {
    
    /**
     * Database instance
     */
    private $database;
    
    /**
     * Session manager instance
     */
    private $session;
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
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
     * Constructor
     */
    private function __construct() {
        $this->database = null;
        $this->session = null;
        
        // Hook into user registration
        add_action('user_register', array($this, 'create_user_profile'));
        
        // Hook into user login
        add_action('wp_login', array($this, 'update_user_login'), 10, 2);
    }
    
    /**
     * Get database instance
     */
    private function get_database() {
        if ($this->database === null) {
            $this->database = SFFC_Database::get_instance();
        }
        return $this->database;
    }
    
    /**
     * Get session instance
     */
    private function get_session() {
        if ($this->session === null) {
            $this->session = SFFC_Session_Manager::get_instance();
        }
        return $this->session;
    }
    
    /**
     * Get current user ID (including guest)
     */
    public function get_current_user_id() {
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            // Get or create guest ID from session
            $user_id = $this->get_session()->get('guest_id');
            
            if (!$user_id) {
                $user_id = $this->create_guest_user();
            }
        }
        
        return $user_id;
    }
    
    /**
     * Create guest user identifier
     */
    private function create_guest_user() {
        $guest_id = 'guest_' . wp_generate_uuid4();
        $this->get_session()->set('guest_id', $guest_id);
        
        // Create profile for guest
        $this->get_database()->update_user_profile($guest_id, array(
            'professional_data' => wp_json_encode(array('type' => 'guest')),
            'preferences' => wp_json_encode(array()),
            'created_at' => current_time('mysql')
        ));
        
        return $guest_id;
    }
    
    /**
     * Create user profile on registration
     */
    public function create_user_profile($user_id) {
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return;
        }
        
        $profile_data = array(
            'professional_data' => wp_json_encode(array(
                'name' => $user->display_name,
                'email' => $user->user_email
            )),
            'preferences' => wp_json_encode(array(
                'default_mode' => 'career',
                'email_notifications' => true
            )),
            'created_at' => current_time('mysql')
        );
        
        $this->get_database()->update_user_profile($user_id, $profile_data);
    }
    
    /**
     * Update user on login
     */
    public function update_user_login($user_login, $user) {
        $profile = $this->get_database()->get_user_profile($user->ID);
        
        if (!$profile) {
            $this->create_user_profile($user->ID);
        }
        
        // Transfer guest session to user if applicable
        $guest_id = $this->get_session()->get('guest_id');
        if ($guest_id) {
            $this->transfer_guest_data($guest_id, $user->ID);
            $this->get_session()->set('guest_id', null);
        }
        
        // Update session with user ID
        $this->get_session()->set('user_id', $user->ID);
    }
    
    /**
     * Transfer guest data to registered user
     */
    private function transfer_guest_data($guest_id, $user_id) {
        global $wpdb;
        
        // Transfer conversations
        $conversations_table = $this->get_database()->get_table('conversations');
        $wpdb->update(
            $conversations_table,
            array('user_id' => $user_id),
            array('user_id' => $guest_id)
        );
        
        // Transfer file uploads
        $files_table = $this->get_database()->get_table('file_uploads');
        $wpdb->update(
            $files_table,
            array('user_id' => $user_id),
            array('user_id' => $guest_id)
        );
    }
    
    /**
     * Get user profile
     */
    public function get_profile($user_id = null) {
        if ($user_id === null) {
            $user_id = $this->get_current_user_id();
        }
        
        $profile = $this->get_database()->get_user_profile($user_id);
        
        if (!$profile) {
            // Create default profile
            $this->create_default_profile($user_id);
            $profile = $this->get_database()->get_user_profile($user_id);
        }
        
        return $profile;
    }
    
    /**
     * Create default profile
     */
    private function create_default_profile($user_id) {
        $profile_data = array(
            'professional_data' => wp_json_encode(array()),
            'preferences' => wp_json_encode(array(
                'default_mode' => 'career',
                'typing_speed' => 'normal',
                'notifications' => true
            )),
            'created_at' => current_time('mysql')
        );
        
        // Add WordPress user data if available
        if (is_numeric($user_id)) {
            $user = get_user_by('id', $user_id);
            if ($user) {
                $professional_data = array(
                    'name' => $user->display_name,
                    'email' => $user->user_email
                );
                $profile_data['professional_data'] = wp_json_encode($professional_data);
            }
        }
        
        $this->get_database()->update_user_profile($user_id, $profile_data);
    }
    
    /**
     * Update user profile
     */
    public function update_profile($data, $user_id = null) {
        if ($user_id === null) {
            $user_id = $this->get_current_user_id();
        }
        
        // Ensure JSON encoding for complex fields
        if (isset($data['professional_data']) && is_array($data['professional_data'])) {
            $data['professional_data'] = wp_json_encode($data['professional_data']);
        }
        
        if (isset($data['preferences']) && is_array($data['preferences'])) {
            $data['preferences'] = wp_json_encode($data['preferences']);
        }
        
        if (isset($data['cv_data']) && is_array($data['cv_data'])) {
            $data['cv_data'] = wp_json_encode($data['cv_data']);
        }
        
        return $this->get_database()->update_user_profile($user_id, $data);
    }
    
    /**
     * Get user conversations
     */
    public function get_conversations($user_id = null, $limit = 10) {
        if ($user_id === null) {
            $user_id = $this->get_current_user_id();
        }
        
        return $this->get_database()->get_user_conversations($user_id, $limit);
    }
    
    /**
     * Get user statistics
     */
    public function get_statistics($user_id = null) {
        if ($user_id === null) {
            $user_id = $this->get_current_user_id();
        }
        
        global $wpdb;
        
        // Get conversation stats
        $conversations_table = $this->get_database()->get_table('conversations');
        $total_conversations = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $conversations_table WHERE user_id = %s",
            $user_id
        ));
        
        // Get message stats
        $messages_table = $this->get_database()->get_table('messages');
        $total_messages = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $messages_table m
             JOIN $conversations_table c ON m.conversation_id = c.id
             WHERE c.user_id = %s",
            $user_id
        ));
        
        // Get mode usage
        $mode_usage = $wpdb->get_results($wpdb->prepare(
            "SELECT mode, COUNT(*) as count 
             FROM $conversations_table 
             WHERE user_id = %s 
             GROUP BY mode",
            $user_id
        ), 'OBJECT_K');
        
        // Get file uploads
        $files_table = $this->get_database()->get_table('file_uploads');
        $total_files = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $files_table WHERE user_id = %s",
            $user_id
        ));
        
        return array(
            'total_conversations' => (int) $total_conversations,
            'total_messages' => (int) $total_messages,
            'total_files' => (int) $total_files,
            'mode_usage' => $mode_usage,
            'member_since' => $this->get_member_since($user_id)
        );
    }
    
    /**
     * Get member since date
     */
    private function get_member_since($user_id) {
        $profile = $this->get_profile($user_id);
        
        if ($profile && isset($profile->created_at)) {
            return $profile->created_at;
        }
        
        // For WordPress users
        if (is_numeric($user_id)) {
            $user = get_user_by('id', $user_id);
            if ($user) {
                return $user->user_registered;
            }
        }
        
        return current_time('mysql');
    }
    
    /**
     * Check if user can access mode
     */
    public function can_access_mode($mode, $user_id = null) {
        // DISABLED FOR TESTING - ALL USERS HAVE ACCESS TO ALL MODES
        return true;
    }
    
    /**
     * Get user preferences
     */
    public function get_preferences($user_id = null) {
        $profile = $this->get_profile($user_id);
        
        if ($profile && isset($profile->preferences)) {
            return $profile->preferences;
        }
        
        return array(
            'default_mode' => 'career',
            'typing_speed' => 'normal',
            'notifications' => true
        );
    }
    
    /**
     * Update user preferences
     */
    public function update_preferences($preferences, $user_id = null) {
        $current_preferences = $this->get_preferences($user_id);
        $merged_preferences = array_merge($current_preferences, $preferences);
        
        return $this->update_profile(array(
            'preferences' => $merged_preferences
        ), $user_id);
    }
}