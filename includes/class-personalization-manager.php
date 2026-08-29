<?php
/**
 * Personalization Manager - Handles user name collection and personalization
 * 
 * @package SennaCareers
 * @since 5.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Personalization_Manager {
    
    /**
     * Instance
     */
    private static $instance = null;
    
    /**
     * Session key for storing user name
     */
    private $session_name_key = 'sffc_user_first_name';
    
    /**
     * Natural name request variations
     */
    private $name_requests = array();
    
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
        $this->init_name_requests();
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Store name when user provides it
        add_action('wp_ajax_sffc_store_user_name', array($this, 'handle_store_user_name'));
        add_action('wp_ajax_nopriv_sffc_store_user_name', array($this, 'handle_store_user_name'));
    }
    
    /**
     * Initialize natural name request variations
     */
    private function init_name_requests() {
        $this->name_requests = array(
            'interview_prep' => array(
                "I help hundreds of candidates prep for finance interviews. Before we start, what's your first name? I'll tailor my advice to your level.",
                "Let me customize your interview prep. I'm MENA Careers - what should I call you?",
                "Interview prep works best when it's personalized. Mind sharing your first name?"
            ),
            
            'career_advice' => array(
                "I provide personalized career strategies for finance professionals. What's your name? I'll remember it for our future conversations.",
                "Let's make this conversation more personal. I'm MENA Careers - and you are?",
                "I'd love to give you tailored career advice. What's your first name?"
            ),
            
            'market_research' => array(
                "I help professionals like you understand markets better. Quick intro - I'm MENA Careers. What's your name?",
                "Before we dive into the data, let me personalize this for you. What should I call you?",
                "I provide better insights when I know who I'm helping. Mind sharing your first name?"
            ),
            
            'networking' => array(
                "Networking is all about personal connections. I'm MENA Careers - your finance career advisor. What's your name?",
                "Let's start with introductions. I'm MENA Careers, and I help finance professionals succeed. You are?",
                "Building your network starts here. What's your first name? I'll remember it for our conversations."
            ),
            
            'general' => array(
                "I'm MENA Careers, your personal finance career advisor. What's your first name? I'll use it to personalize our conversation.",
                "Let me introduce myself - I'm MENA Careers. And you are? (Just your first name is fine)",
                "For a more personalized experience, may I have your first name?"
            )
        );
    }
    
    /**
     * Get user's first name from various sources
     */
    public function get_user_first_name() {
        // 1. Check if user is logged in
        $current_user = wp_get_current_user();
        if ($current_user && $current_user->ID > 0) {
            $first_name = get_user_meta($current_user->ID, 'first_name', true);
            if (!empty($first_name)) {
                return $first_name;
            }
            
            // Try display name
            $display_name = $current_user->display_name;
            if (!empty($display_name) && $display_name !== $current_user->user_login) {
                $name_parts = explode(' ', $display_name);
                return $name_parts[0];
            }
        }
        
        // 2. Check session storage
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION[$this->session_name_key])) {
            return $_SESSION[$this->session_name_key];
        }
        
        // 3. Check cookie (for returning users)
        if (isset($_COOKIE['sffc_user_name'])) {
            return sanitize_text_field($_COOKIE['sffc_user_name']);
        }
        
        return '';
    }
    
    /**
     * Get natural name request based on context
     */
    public function get_natural_name_request($context = 'general', $query = '') {
        $category = $this->determine_context_category($context, $query);
        
        if (isset($this->name_requests[$category])) {
            $requests = $this->name_requests[$category];
            return $requests[array_rand($requests)];
        }
        
        return $this->name_requests['general'][0];
    }
    
    /**
     * Determine context category for name request
     */
    private function determine_context_category($context, $query) {
        $query_lower = strtolower($query);
        
        // Interview related
        if (preg_match('/interview|prepare|prep|case|behavioral|technical/i', $query)) {
            return 'interview_prep';
        }
        
        // Career related
        if (preg_match('/career|job|role|opportunity|lateral|promotion|path/i', $query)) {
            return 'career_advice';
        }
        
        // Market/research related
        if (preg_match('/market|analysis|research|data|trend|outlook/i', $query)) {
            return 'market_research';
        }
        
        // Networking related
        if (preg_match('/network|connect|contact|reach|introduction/i', $query)) {
            return 'networking';
        }
        
        return 'general';
    }
    
    /**
     * Create personalized greeting
     */
    public function create_personalized_greeting($name = '', $time_based = true) {
        if (empty($name)) {
            return "Welcome";
        }
        
        if (!$time_based) {
            return "Hi {$name}";
        }
        
        // Time-based personalization
        $hour = intval(date('H'));
        
        if ($hour < 12) {
            return "Good morning, {$name}";
        } elseif ($hour < 17) {
            return "Good afternoon, {$name}";
        } else {
            return "Good evening, {$name}";
        }
    }
    
    /**
     * Handle store user name AJAX
     */
    public function handle_store_user_name() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sffc_frontend_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }
        
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        
        if (empty($name)) {
            wp_send_json_error(array('message' => 'Name cannot be empty'));
            return;
        }
        
        // Store in session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION[$this->session_name_key] = $name;
        
        // Store in cookie for 30 days
        setcookie('sffc_user_name', $name, time() + (30 * DAY_IN_SECONDS), COOKIEPATH, COOKIE_DOMAIN);
        
        // If user is logged in, update their meta
        $current_user = wp_get_current_user();
        if ($current_user && $current_user->ID > 0) {
            update_user_meta($current_user->ID, 'first_name', $name);
        }
        
        wp_send_json_success(array(
            'message' => "Thanks {$name}! I'll remember that for our conversation.",
            'greeting' => $this->create_personalized_greeting($name)
        ));
    }
    
    /**
     * Check if we should ask for name
     */
    public function should_ask_for_name($message_count = 0) {
        // Don't ask if we already have the name
        if (!empty($this->get_user_first_name())) {
            return false;
        }
        
        // Ask on first message or after 3 messages without a name
        return ($message_count === 0 || $message_count === 3);
    }
}