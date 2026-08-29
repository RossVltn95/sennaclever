<?php
/**
 * Session Manager Class
 * 
 * @package SennaCareers
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Session_Manager {
    
    /**
     * Current session data
     */
    private $session_data = array();
    
    /**
     * Session key
     */
    private $session_key = 'sffc_session';
    
    /**
     * Database instance
     */
    private $database;
    
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
        // Initialize database on first use
        $this->database = null;
        
        // Initialize session
        add_action('init', array($this, 'init_session'), 1);
        
        // Clean up expired sessions
        add_action('sffc_cleanup_sessions', array($this, 'cleanup_expired_sessions'));
        
        // Schedule cleanup if not scheduled
        if (!wp_next_scheduled('sffc_cleanup_sessions')) {
            wp_schedule_event(time(), 'hourly', 'sffc_cleanup_sessions');
        }
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
     * Initialize session
     */
    public function init_session() {
        if (!session_id() && !headers_sent()) {
            session_start();
        }
        
        $this->load_session_data();
    }
    
    /**
     * Get session ID
     */
    public function get_session_id() {
        if (session_id()) {
            return session_id();
        }
        // Generate a unique ID if no session
        if (!isset($this->session_data['session_id'])) {
            $this->session_data['session_id'] = uniqid('sffc_', true);
        }
        return $this->session_data['session_id'];
    }
    
    /**
     * Load session data
     */
    private function load_session_data() {
        if (isset($_SESSION[$this->session_key])) {
            $this->session_data = $_SESSION[$this->session_key];
        } else {
            $this->session_data = $this->get_default_session_data();
            $this->save_session_data();
        }
    }
    
    /**
     * Get default session data
     */
    private function get_default_session_data() {
        return array(
            'conversation_id' => null,
            'mode' => 'career',
            'user_id' => get_current_user_id(),
            'started_at' => current_time('mysql'),
            'messages_count' => 0,
            'context' => array(),
            'temp_data' => array()
        );
    }
    
    /**
     * Save session data
     */
    private function save_session_data() {
        $_SESSION[$this->session_key] = $this->session_data;
    }
    
    /**
     * Get session value
     */
    public function get($key, $default = null) {
        return isset($this->session_data[$key]) ? $this->session_data[$key] : $default;
    }
    
    /**
     * Set session value
     */
    public function set($key, $value) {
        $this->session_data[$key] = $value;
        $this->save_session_data();
    }
    
    /**
     * Start new conversation with database locking to prevent race conditions
     */
    public function start_conversation($mode = 'career') {
        $user_id = get_current_user_id();
        
        // If no user ID, use session ID
        if (!$user_id) {
            $user_id = $this->get_guest_user_id();
        }
        
        // Use database locking to prevent race conditions
        $conversation_id = $this->create_conversation_with_lock($user_id, $mode);
        
        if ($conversation_id) {
            $this->set('conversation_id', $conversation_id);
            $this->set('mode', $mode);
            $this->set('user_id', $user_id);
            $this->set('started_at', current_time('mysql'));
            $this->set('messages_count', 0);
            
            return $conversation_id;
        }
        
        return false;
    }
    
    /**
     * Create conversation with database locking to prevent duplicates
     * 
     * @param int $user_id User ID
     * @param string $mode Conversation mode
     * @return int|false Conversation ID or false on failure
     */
    private function create_conversation_with_lock($user_id, $mode) {
        global $wpdb;
        
        // Create unique lock name for this user
        $lock_name = "sffc_conv_creation_{$user_id}";
        $lock_name_escaped = esc_sql($lock_name);
        
        // Try to acquire lock (timeout after 3 seconds for better performance)
        $lock_result = $wpdb->get_var("SELECT GET_LOCK('$lock_name_escaped', 3)");
        
        if ($lock_result != 1) {
            error_log('SFFC: Could not acquire conversation creation lock for user ' . $user_id);
            
            // Try to get existing conversation first
            // Try to get existing conversation from database
            $table = $wpdb->prefix . 'sffc_conversations';
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE user_id = %s AND mode = %s ORDER BY created_at DESC LIMIT 1",
                $user_id, $mode
            ));
            if ($existing) {
                error_log('SFFC: Using existing conversation instead');
                return $existing;
            }
            
            // If no existing conversation and can't get lock, create without lock as last resort
            error_log('SFFC: Creating conversation without lock as emergency fallback');
            return $this->get_database()->insert_conversation($user_id, $mode);
        }
        
        try {
            // Create conversation safely with lock held
            $conversation_id = $this->get_database()->insert_conversation($user_id, $mode);
            
            // Release lock
            $wpdb->query("SELECT RELEASE_LOCK('$lock_name_escaped')");
            
            return $conversation_id;
            
        } catch (Exception $e) {
            // Make sure to release lock even on error
            $wpdb->query("SELECT RELEASE_LOCK('$lock_name_escaped')");
            error_log('SFFC: Error creating conversation: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get or create guest user ID
     */
    private function get_guest_user_id() {
        $guest_id = $this->get('guest_id');
        
        if (!$guest_id) {
            $guest_id = 'guest_' . wp_generate_password(12, false);
            $this->set('guest_id', $guest_id);
        }
        
        return $guest_id;
    }
    
    /**
     * Switch mode
     */
    public function switch_mode($mode) {
        $valid_modes = array('career', 'market', 'skills', 'opportunities', 'expert');
        
        if (!in_array($mode, $valid_modes)) {
            return false;
        }
        
        $this->set('mode', $mode);
        
        // Update conversation mode in database if active
        $conversation_id = $this->get('conversation_id');
        if ($conversation_id) {
            global $wpdb;
            $table = $this->get_database()->get_table('conversations');
            
            $wpdb->update(
                $table,
                array('mode' => $mode),
                array('id' => $conversation_id)
            );
        }
        
        return true;
    }
    
    /**
     * Add message to conversation
     */
    public function add_message($sender_type, $message, $metadata = array()) {
        $conversation_id = $this->get('conversation_id');
        
        if (!$conversation_id) {
            $conversation_id = $this->start_conversation($this->get('mode', 'career'));
        }
        
        $user_id = $this->get('user_id');
        $sender_id = ($sender_type === 'user') ? $user_id : null;
        
        $message_id = $this->get_database()->insert_message(
            $conversation_id,
            $sender_type,
            $message,
            $sender_id,
            $metadata
        );
        
        if ($message_id) {
            $count = $this->get('messages_count', 0);
            $this->set('messages_count', $count + 1);
            
            return $message_id;
        }
        
        return false;
    }
    
    /**
     * Get conversation messages
     */
    public function get_messages($limit = 50) {
        $conversation_id = $this->get('conversation_id');
        
        if (!$conversation_id) {
            return array();
        }
        
        return $this->get_database()->get_conversation_messages($conversation_id, $limit);
    }
    
    /**
     * Update context
     */
    public function update_context($key, $value) {
        $context = $this->get('context', array());
        $context[$key] = $value;
        $this->set('context', $context);
    }
    
    /**
     * Get context
     */
    public function get_context($key = null, $default = null) {
        $context = $this->get('context', array());
        
        if ($key === null) {
            return $context;
        }
        
        return isset($context[$key]) ? $context[$key] : $default;
    }
    
    /**
     * Set temporary data
     */
    public function set_temp($key, $value) {
        $temp_data = $this->get('temp_data', array());
        $temp_data[$key] = array(
            'value' => $value,
            'expires' => time() + 3600 // 1 hour expiry
        );
        $this->set('temp_data', $temp_data);
    }
    
    /**
     * Get temporary data
     */
    public function get_temp($key, $default = null) {
        $temp_data = $this->get('temp_data', array());
        
        if (!isset($temp_data[$key])) {
            return $default;
        }
        
        // Check if expired
        if ($temp_data[$key]['expires'] < time()) {
            unset($temp_data[$key]);
            $this->set('temp_data', $temp_data);
            return $default;
        }
        
        return $temp_data[$key]['value'];
    }
    
    /**
     * Clear session
     */
    public function clear() {
        $this->session_data = $this->get_default_session_data();
        $this->save_session_data();
    }
    
    /**
     * End conversation
     */
    public function end_conversation() {
        $conversation_id = $this->get('conversation_id');
        
        if ($conversation_id) {
            global $wpdb;
            $table = $this->get_database()->get_table('conversations');
            
            $wpdb->update(
                $table,
                array(
                    'status' => 'completed',
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $conversation_id)
            );
        }
        
        $this->clear();
    }
    
    /**
     * Check if conversation is active
     */
    public function is_active() {
        $conversation_id = $this->get('conversation_id');
        $started_at = $this->get('started_at');
        
        if (!$conversation_id || !$started_at) {
            return false;
        }
        
        // Check if conversation is older than 2 hours
        $started_timestamp = strtotime($started_at);
        $current_timestamp = current_time('timestamp');
        
        if (($current_timestamp - $started_timestamp) > 7200) {
            $this->end_conversation();
            return false;
        }
        
        return true;
    }
    
    /**
     * Clean up expired sessions
     */
    public function cleanup_expired_sessions() {
        global $wpdb;
        $table = $this->get_database()->get_table('conversations');
        
        // Mark conversations older than 24 hours as expired
        $wpdb->query($wpdb->prepare(
            "UPDATE $table 
             SET status = 'expired' 
             WHERE status = 'active' 
             AND updated_at < DATE_SUB(NOW(), INTERVAL %d HOUR)",
            24
        ));
    }
    
    /**
     * Get session statistics
     */
    public function get_statistics() {
        return array(
            'conversation_id' => $this->get('conversation_id'),
            'mode' => $this->get('mode'),
            'messages_count' => $this->get('messages_count', 0),
            'started_at' => $this->get('started_at'),
            'duration' => $this->get_duration(),
            'is_active' => $this->is_active()
        );
    }
    
    /**
     * Get session duration in seconds
     */
    private function get_duration() {
        $started_at = $this->get('started_at');
        
        if (!$started_at) {
            return 0;
        }
        
        $started_timestamp = strtotime($started_at);
        $current_timestamp = current_time('timestamp');
        
        return $current_timestamp - $started_timestamp;
    }
}