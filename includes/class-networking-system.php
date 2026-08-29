<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Professional Networking System
 * Handles introduction requests, connections, and messaging integration
 */
class SFFC_Networking_System
{
    private static $instance = null;
    
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        add_action('init', array($this, 'init'));
    }

    public function init()
    {
        // Register AJAX handlers
        add_action('wp_ajax_sffc_request_introduction', array($this, 'handle_introduction_request'));
        add_action('wp_ajax_sffc_respond_to_introduction', array($this, 'handle_introduction_response'));
        add_action('wp_ajax_sffc_get_networking_requests', array($this, 'get_networking_requests'));

        // Schedule cleanup of old requests
        add_action('sffc_cleanup_old_requests', array($this, 'cleanup_old_requests'));

        // Only create database tables once (check version option)
        $db_version = get_option('sffc_networking_db_version', '0');
        if (version_compare($db_version, '1.0.0', '<')) {
            $this->create_networking_tables();
            update_option('sffc_networking_db_version', '1.0.0');
        }
    }

    /**
     * Create networking database tables
     */
    public function create_networking_tables()
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sffc_networking_requests';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            requester_id bigint(20) NOT NULL,
            target_id bigint(20) NOT NULL,
            request_message text,
            requester_name varchar(255),
            requester_position varchar(255),
            requester_company varchar(255),
            target_name varchar(255),
            target_position varchar(255),
            target_company varchar(255),
            status enum('pending', 'accepted', 'declined', 'expired') DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            responded_at datetime NULL,
            expires_at datetime NOT NULL,
            ip_address varchar(45),
            user_agent text,
            PRIMARY KEY (id),
            KEY idx_requester (requester_id),
            KEY idx_target (target_id),
            KEY idx_status (status),
            KEY idx_created (created_at),
            KEY idx_expires (expires_at),
            UNIQUE KEY unique_active_request (requester_id, target_id, status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Create connections table for successful introductions
        $connections_table = $wpdb->prefix . 'sffc_professional_connections';
        
        $connections_sql = "CREATE TABLE IF NOT EXISTS $connections_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_1_id bigint(20) NOT NULL,
            user_2_id bigint(20) NOT NULL,
            connection_type enum('introduction', 'direct', 'mutual') DEFAULT 'introduction',
            established_at datetime DEFAULT CURRENT_TIMESTAMP,
            request_id bigint(20) NULL,
            notes text,
            PRIMARY KEY (id),
            KEY idx_user1 (user_1_id),
            KEY idx_user2 (user_2_id),
            KEY idx_type (connection_type),
            KEY idx_established (established_at),
            UNIQUE KEY unique_connection (user_1_id, user_2_id)
        ) $charset_collate;";
        
        dbDelta($connections_sql);
    }

    /**
     * Handle introduction request via AJAX
     */
    public function handle_introduction_request()
    {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'], 'sffc_professional_profile')) {
            wp_send_json_error('Security verification failed');
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in to send introduction requests');
        }
        
        $requester_id = get_current_user_id();
        $target_id = intval($_POST['target_id']);
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        
        // Validation
        if (!$target_id || $target_id === $requester_id) {
            wp_send_json_error('Invalid target user');
        }
        
        // Check if target user exists
        $target_user = get_user_by('id', $target_id);
        if (!$target_user) {
            wp_send_json_error('Target user not found');
        }
        
        // Check for existing pending request
        global $wpdb;
        $existing_request = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sffc_networking_requests 
             WHERE requester_id = %d AND target_id = %d AND status = 'pending'",
            $requester_id, $target_id
        ));
        
        if ($existing_request) {
            wp_send_json_error('You already have a pending introduction request with this person');
        }
        
        // Check if users are already connected
        if ($this->are_users_connected($requester_id, $target_id)) {
            wp_send_json_error('You are already connected with this person');
        }
        
        // Rate limiting - max 5 requests per day
        $daily_requests = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sffc_networking_requests 
             WHERE requester_id = %d AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)",
            $requester_id
        ));
        
        if ($daily_requests >= 5) {
            wp_send_json_error('Daily request limit reached. Please try again tomorrow.');
        }
        
        // Get user information
        $requester_user = get_user_by('id', $requester_id);
        $requester_position = get_user_meta($requester_id, 'senna_current_position', true) ?: 'Professional';
        $requester_company = get_user_meta($requester_id, 'senna_current_company', true) ?: 'Finance Professional';
        
        $target_position = get_user_meta($target_id, 'senna_current_position', true) ?: 'Professional';
        $target_company = get_user_meta($target_id, 'senna_current_company', true) ?: 'Finance Professional';
        
        // Create introduction request
        $request_data = array(
            'requester_id' => $requester_id,
            'target_id' => $target_id,
            'request_message' => $message,
            'requester_name' => $requester_user->display_name,
            'requester_position' => $requester_position,
            'requester_company' => $requester_company,
            'target_name' => $target_user->display_name,
            'target_position' => $target_position,
            'target_company' => $target_company,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)
        );
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'sffc_networking_requests',
            $request_data,
            array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            wp_send_json_error('Failed to create introduction request');
        }
        
        $request_id = $wpdb->insert_id;
        
        // Send notifications
        $this->send_introduction_notification($request_id);
        $this->create_messaging_notification($request_id);
        
        // Log activity
        $this->log_networking_activity($requester_id, 'introduction_request_sent', array(
            'target_user' => $target_user->display_name,
            'request_id' => $request_id
        ));
        
        wp_send_json_success(array(
            'message' => 'Introduction request sent successfully',
            'request_id' => $request_id
        ));
    }

    /**
     * Handle introduction response (accept/decline)
     */
    public function handle_introduction_response()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'sffc_professional_profile')) {
            wp_send_json_error('Security verification failed');
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in');
        }
        
        $user_id = get_current_user_id();
        $request_id = intval($_POST['request_id']);
        $response = sanitize_text_field($_POST['response']); // 'accept' or 'decline'
        
        if (!in_array($response, array('accept', 'decline'))) {
            wp_send_json_error('Invalid response');
        }
        
        global $wpdb;
        
        // Get the request
        $request = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sffc_networking_requests 
             WHERE id = %d AND target_id = %d AND status = 'pending'",
            $request_id, $user_id
        ));
        
        if (!$request) {
            wp_send_json_error('Introduction request not found or already responded to');
        }
        
        // Check if request has expired
        if (strtotime($request->expires_at) < time()) {
            // Auto-expire the request
            $wpdb->update(
                $wpdb->prefix . 'sffc_networking_requests',
                array('status' => 'expired'),
                array('id' => $request_id),
                array('%s'),
                array('%d')
            );
            wp_send_json_error('This introduction request has expired');
        }
        
        $status = ($response === 'accept') ? 'accepted' : 'declined';
        
        // Update request status
        $update_result = $wpdb->update(
            $wpdb->prefix . 'sffc_networking_requests',
            array(
                'status' => $status,
                'responded_at' => current_time('mysql')
            ),
            array('id' => $request_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($update_result === false) {
            wp_send_json_error('Failed to update request status');
        }
        
        // If accepted, create the connection
        if ($status === 'accepted') {
            $this->create_professional_connection($request->requester_id, $request->target_id, $request_id);
            $this->notify_connection_established($request);
            
            // Create messaging thread for the new connection
            $this->create_messaging_thread($request->requester_id, $request->target_id, $request_id);
        }
        
        // Notify the requester about the response
        $this->send_response_notification($request, $status);
        
        // Log activity
        $this->log_networking_activity($user_id, 'introduction_response', array(
            'response' => $status,
            'requester' => $request->requester_name,
            'request_id' => $request_id
        ));
        
        $message = ($status === 'accepted') 
            ? 'Introduction accepted! You are now connected.' 
            : 'Introduction request declined.';
            
        wp_send_json_success(array(
            'message' => $message,
            'status' => $status
        ));
    }

    /**
     * Get networking requests for current user
     */
    public function get_networking_requests()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'sffc_professional_profile')) {
            wp_send_json_error('Security verification failed');
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in');
        }
        
        $user_id = get_current_user_id();
        global $wpdb;
        
        // Get pending requests TO this user
        $incoming_requests = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sffc_networking_requests 
             WHERE target_id = %d AND status = 'pending' AND expires_at > NOW()
             ORDER BY created_at DESC",
            $user_id
        ));
        
        // Get requests FROM this user
        $outgoing_requests = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sffc_networking_requests 
             WHERE requester_id = %d 
             ORDER BY created_at DESC 
             LIMIT 20",
            $user_id
        ));
        
        // Get connections
        $connections = $this->get_user_connections($user_id);
        
        wp_send_json_success(array(
            'incoming_requests' => $incoming_requests,
            'outgoing_requests' => $outgoing_requests,
            'connections' => $connections
        ));
    }

    /**
     * Create professional connection
     */
    private function create_professional_connection($user_1_id, $user_2_id, $request_id)
    {
        global $wpdb;
        
        // Ensure consistent ordering for unique constraint
        $lower_id = min($user_1_id, $user_2_id);
        $higher_id = max($user_1_id, $user_2_id);
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'sffc_professional_connections',
            array(
                'user_1_id' => $lower_id,
                'user_2_id' => $higher_id,
                'connection_type' => 'introduction',
                'request_id' => $request_id
            ),
            array('%d', '%d', '%s', '%d')
        );
        
        return $result !== false;
    }

    /**
     * Check if two users are already connected
     */
    private function are_users_connected($user_1_id, $user_2_id)
    {
        global $wpdb;
        
        $lower_id = min($user_1_id, $user_2_id);
        $higher_id = max($user_1_id, $user_2_id);
        
        $connection = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}sffc_professional_connections 
             WHERE user_1_id = %d AND user_2_id = %d",
            $lower_id, $higher_id
        ));
        
        return !empty($connection);
    }

    /**
     * Get user connections
     */
    private function get_user_connections($user_id)
    {
        global $wpdb;
        
        $connections = $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, 
                    CASE 
                        WHEN c.user_1_id = %d THEN c.user_2_id 
                        ELSE c.user_1_id 
                    END as connected_user_id,
                    c.established_at
             FROM {$wpdb->prefix}sffc_professional_connections c
             WHERE c.user_1_id = %d OR c.user_2_id = %d
             ORDER BY c.established_at DESC",
            $user_id, $user_id, $user_id
        ));
        
        // Enrich with user data
        foreach ($connections as &$connection) {
            $connected_user = get_user_by('id', $connection->connected_user_id);
            if ($connected_user) {
                $connection->connected_user_name = $connected_user->display_name;
                $connection->connected_user_position = get_user_meta($connection->connected_user_id, 'senna_current_position', true);
                $connection->connected_user_company = get_user_meta($connection->connected_user_id, 'senna_current_company', true);
                $connection->connected_user_picture = get_user_meta($connection->connected_user_id, 'senna_profile_picture', true);
            }
        }
        
        return $connections;
    }

    /**
     * Send email notification for introduction request
     */
    private function send_introduction_notification($request_id)
    {
        global $wpdb;
        
        $request = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sffc_networking_requests WHERE id = %d",
            $request_id
        ));
        
        if (!$request) return false;
        
        $target_user = get_user_by('id', $request->target_id);
        if (!$target_user) return false;
        
        $subject = sprintf('[MENA Careers Finance] %s would like to connect with you', $request->requester_name);
        
        $message = sprintf("
Hello %s,

%s (%s at %s) would like to connect with you on MENA Careers Finance.

%s

To respond to this introduction request, please visit your dashboard:
%s

This request will expire in 7 days.

Best regards,
The MENA Careers Finance Team
        ",
            $request->target_name,
            $request->requester_name,
            $request->requester_position,
            $request->requester_company,
            $request->request_message ? "Message: " . $request->request_message : "",
            home_url('/professional-profile/?settings=0#network')
        );
        
        return wp_mail($target_user->user_email, $subject, $message);
    }

    /**
     * Send response notification to requester
     */
    private function send_response_notification($request, $status)
    {
        $requester_user = get_user_by('id', $request->requester_id);
        if (!$requester_user) return false;
        
        $subject = sprintf('[MENA Careers Finance] %s %s your introduction request', 
            $request->target_name, 
            $status === 'accepted' ? 'accepted' : 'declined'
        );
        
        if ($status === 'accepted') {
            $message = sprintf("
Great news!

%s has accepted your introduction request. You are now connected on MENA Careers Finance.

You can now message each other through the platform.

View your connections:
%s

Best regards,
The MENA Careers Finance Team
            ",
                $request->target_name,
                home_url('/professional-profile/?settings=0#network')
            );
        } else {
            $message = sprintf("
Hello %s,

%s has declined your introduction request on MENA Careers Finance.

Don't worry - there are many other professionals to connect with on the platform.

Continue networking:
%s

Best regards,
The MENA Careers Finance Team
            ",
                $request->requester_name,
                $request->target_name,
                home_url('/professional-profile/?settings=0#network')
            );
        }
        
        return wp_mail($requester_user->user_email, $subject, $message);
    }

    /**
     * Create messaging notification (integrate with skill-farm-messaging-dashboard)
     */
    private function create_messaging_notification($request_id)
    {
        // Check if messaging system exists
        if (!class_exists('SFFC_Messaging_System')) {
            return false;
        }
        
        global $wpdb;
        
        $request = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sffc_networking_requests WHERE id = %d",
            $request_id
        ));
        
        if (!$request) return false;
        
        // Create a system message in the messaging system
        $messaging_system = SFFC_Messaging_System::get_instance();
        
        $notification_message = sprintf(
            "%s (%s) has sent you an introduction request. Check your Professional Network to respond.",
            $request->requester_name,
            $request->requester_position
        );
        
        return $messaging_system->create_system_notification(
            $request->target_id,
            'introduction_request',
            $notification_message,
            array('request_id' => $request_id)
        );
    }

    /**
     * Create messaging thread for connected users
     */
    private function create_messaging_thread($user_1_id, $user_2_id, $request_id)
    {
        if (!class_exists('SFFC_Messaging_System')) {
            return false;
        }
        
        $messaging_system = SFFC_Messaging_System::get_instance();
        
        $welcome_message = "You are now connected! Feel free to start a conversation.";
        
        return $messaging_system->create_conversation(
            array($user_1_id, $user_2_id),
            'professional_connection',
            $welcome_message,
            array('connection_request_id' => $request_id)
        );
    }

    /**
     * Log networking activity
     */
    private function log_networking_activity($user_id, $action, $details = array())
    {
        // Integration with existing activity logging system
        if (class_exists('SFFC_Professional_Profile_Database')) {
            $db = SFFC_Professional_Profile_Database::get_instance();
            $db->log_user_activity($user_id, $action, $details);
        }
    }

    /**
     * Get client IP address
     */
    private function get_client_ip()
    {
        $ip_fields = array('HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_fields as $field) {
            if (!empty($_SERVER[$field])) {
                $ip = $_SERVER[$field];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Cleanup old expired requests
     */
    public function cleanup_old_requests()
    {
        global $wpdb;
        
        $wpdb->update(
            $wpdb->prefix . 'sffc_networking_requests',
            array('status' => 'expired'),
            array('status' => 'pending'),
            array('%s'),
            array('%s'),
            "expires_at < NOW()"
        );
    }

    /**
     * Notify about established connection
     */
    private function notify_connection_established($request)
    {
        // This could integrate with push notifications, Slack, etc.
        do_action('sffc_connection_established', $request->requester_id, $request->target_id, $request);
    }
}

// Defer initialization until plugins_loaded to avoid early execution issues
add_action('plugins_loaded', function() {
    SFFC_Networking_System::get_instance();
}, 20);