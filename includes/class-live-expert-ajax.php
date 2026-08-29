<?php
/**
 * Live Expert AJAX Handlers - INSTANT Performance
 *
 * @package SennaCareers
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Live_Expert_Ajax {

    private static $instance = null;
    private $messaging;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->messaging = SFFC_Live_Expert_Messaging::get_instance();

        // FAST AJAX handlers
        add_action('wp_ajax_sffc_live_expert_send_fast', [$this, 'send_message_fast']);
        add_action('wp_ajax_nopriv_sffc_live_expert_send_fast', [$this, 'send_message_fast']);
        add_action('wp_ajax_sffc_live_expert_fetch_fast', [$this, 'fetch_messages_fast']);
        add_action('wp_ajax_nopriv_sffc_live_expert_fetch_fast', [$this, 'fetch_messages_fast']);
    }

    /**
     * Send message - INSTANT (no wp_insert_post overhead)
     */
    public function send_message_fast() {
        // Verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'sffc_public_nonce')) {
            wp_send_json_error(['message' => 'Invalid nonce'], 403);
            return;
        }

        $session_id = isset($_POST['session_id']) ? sanitize_text_field(wp_unslash($_POST['session_id'])) : '';
        $conversation_id = isset($_POST['conversation_id']) ? absint($_POST['conversation_id']) : 0;
        $sender = isset($_POST['sender']) ? sanitize_key(wp_unslash($_POST['sender'])) : 'user';
        $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';

        // Validate expert sender
        if ($sender === 'expert' && !current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
            return;
        }

        if (empty($message)) {
            wp_send_json_error(['message' => 'Message cannot be empty'], 400);
            return;
        }

        if ($sender === 'user' && !$this->is_private_equity_scoped_message($message)) {
            wp_send_json_error([
                'message' => __('Live Expert Chat is currently focused on finance careers and role-prep topics. Please ask about interviews, skills, CVs, applications, or finance career paths.', 'senna-finance'),
            ], 400);
            return;
        }

        // Get or create conversation - INSTANT
        $conversation = $this->messaging->ensure_conversation($session_id, $conversation_id);

        if (is_wp_error($conversation)) {
            wp_send_json_error(['message' => $conversation->get_error_message()], 400);
            return;
        }

        // Determine sender info
        if ($sender === 'expert') {
            $current_user = wp_get_current_user();
            $sender_name = $current_user->display_name ?: 'Live Expert';
            $sender_email = $current_user->user_email;
        } else {
            $sender_name = $conversation['user_name'] ?: 'Guest';
            $sender_email = $conversation['user_email'] ?: '';
        }

        // Send message - INSTANT (direct database insert)
        $result = $this->messaging->send_message(
            $conversation['id'],
            $sender,
            $message,
            $sender_name,
            $sender_email
        );

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()], 500);
            return;
        }

        // Send email notification to admin when user sends a message (async to avoid blocking)
        if ($sender === 'user') {
            // Schedule email to be sent after response is returned
            add_action('shutdown', function() use ($conversation, $message, $sender_name) {
                $this->send_admin_notification($conversation, $message, $sender_name);
            });
        }

        wp_send_json_success([
            'conversation_id' => $conversation['id'],
            'message' => $result,
        ]);
    }

    private function is_private_equity_scoped_message($message) {
        $message = strtolower(trim((string) $message));
        if ($message === '') {
            return false;
        }

        $finance_career_signals = [
            'private equity',
            'growth equity',
            'buyout',
            'lbo',
            'private credit',
            'direct lending',
            'investment banking',
            'asset management',
            'portfolio operations',
            'portfolio ops',
            'value creation',
            'investment committee',
            'operating partner',
            'deal team',
            'principal',
            'partner',
            ' ib',
            'ib ',
            'markets',
            'sales and trading',
            'equity research',
            'risk',
            'compliance',
            'corporate finance',
            'commercial banking',
            'wealth management',
            'audit',
            'tax',
            'sovereign wealth',
            'family office',
            'wealth fund',
            'regional bank',
            'finance manager',
            'senior analyst',
            'associate role',
            'senior associate',
            'vice president',
            'vp role',
            'director role',
            'managing director',
            'head of finance',
            'treasury manager',
            'fp&a manager',
            'portfolio finance',
            'interview',
            'assessment centre',
            'cover letter',
            'cv ',
            'resume',
            'recruiter',
            'networking',
            'application',
            'investment memo',
            'case study',
            'deal team',
            'portfolio company',
            'operating partner',
            'fundraising',
            'mid-market',
            'megafund',
            'headhunter',
        ];

        foreach ($finance_career_signals as $signal) {
            if (strpos($message, $signal) !== false) {
                return true;
            }
        }

        $off_topic_signals = [
            'football',
            'recipe',
            'holiday',
            'dating',
            'fitness plan',
            'movie recommendation',
            'weather',
            'video game',
        ];

        foreach ($off_topic_signals as $signal) {
            if (strpos($message, $signal) !== false) {
                return false;
            }
        }

        // Allow generic interview / CV / application questions because the widget context is finance-career specific.
        return true;
    }

    /**
     * Send email notification to admin when user sends a message
     */
    private function send_admin_notification($conversation, $message, $sender_name) {
        // Get admin email
        $admin_email = get_option('admin_email');
        if (!$admin_email) {
            return;
        }

        // Allow filtering the notification email address
        $admin_email = apply_filters('sffc_live_expert_notification_email', $admin_email);

        // Build subject
        $subject = sprintf(
            __('[%s] New Live Expert Chat Message from %s', 'senna-finance'),
            get_bloginfo('name'),
            $sender_name
        );

        // Get reply page URL - use filter to allow customization
        $reply_url = apply_filters('sffc_live_expert_reply_url', admin_url('admin.php?page=live-expert-chats'));

        // Build message body
        $message_body = sprintf(
            __("New message received in Live Expert Chat:\n\nFrom: %s\nEmail: %s\nSession ID: %s\n\nMessage:\n%s\n\nReply here: %s", 'senna-finance'),
            $sender_name,
            $conversation['user_email'] ?: __('Not provided', 'senna-finance'),
            $conversation['session_id'],
            $message,
            $reply_url
        );

        $headers = ['Content-Type: text/plain; charset=UTF-8'];

        // Send email (non-blocking - won't slow down response)
        wp_mail($admin_email, $subject, $message_body, $headers);
    }

    /**
     * Fetch messages - INSTANT (direct database query)
     */
    public function fetch_messages_fast() {
        // Verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'sffc_public_nonce')) {
            wp_send_json_error(['message' => 'Invalid nonce'], 403);
            return;
        }

        $session_id = isset($_POST['session_id']) ? sanitize_text_field(wp_unslash($_POST['session_id'])) : '';
        $conversation_id = isset($_POST['conversation_id']) ? absint($_POST['conversation_id']) : 0;
        $since = isset($_POST['since']) ? absint($_POST['since']) : 0;

        // Get conversation
        $conversation = $this->messaging->ensure_conversation($session_id, $conversation_id);

        if (is_wp_error($conversation)) {
            wp_send_json_error(['message' => $conversation->get_error_message()], 400);
            return;
        }

        // Fetch new messages - INSTANT (indexed query)
        $messages = $this->messaging->fetch_messages($conversation['id'], $since);

        wp_send_json_success([
            'conversation_id' => $conversation['id'],
            'messages' => $messages,
            'has_more' => count($messages) > 0,
        ]);
    }
}

// Initialize
SFFC_Live_Expert_Ajax::get_instance();
