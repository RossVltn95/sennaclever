<?php
/**
 * Live Expert Messaging System - Fast Database Table Implementation
 * Bypasses WordPress posts for instant real-time messaging
 *
 * @package SennaCareers
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Live_Expert_Messaging {

    private static $instance = null;
    private $conversations_table;
    private $messages_table;
    private $tables_exist = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->conversations_table = $wpdb->prefix . 'sffc_live_conversations';
        $this->messages_table = $wpdb->prefix . 'sffc_live_messages';

    }

    /**
     * Check if tables exist
     */
    private function tables_exist() {
        if ($this->tables_exist !== null) {
            return $this->tables_exist;
        }

        global $wpdb;
        $conv_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->conversations_table}'") === $this->conversations_table;
        $msg_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->messages_table}'") === $this->messages_table;
        $this->tables_exist = $conv_exists && $msg_exists;
        return $this->tables_exist;
    }

    /**
     * Create custom database tables for fast messaging
     */
    public function maybe_create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Check if tables exist
        $conv_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->conversations_table}'") === $this->conversations_table;
        $msg_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->messages_table}'") === $this->messages_table;

        if ($conv_exists && $msg_exists) {
            return;
        }

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Conversations table
        $sql_conversations = "CREATE TABLE {$this->conversations_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id varchar(100) NOT NULL,
            user_id bigint(20) UNSIGNED DEFAULT 0,
            user_name varchar(255) DEFAULT '',
            user_email varchar(255) DEFAULT '',
            status varchar(20) DEFAULT 'active',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY session_id (session_id),
            KEY user_id (user_id),
            KEY updated_at (updated_at)
        ) $charset_collate;";

        // Messages table - optimized for speed
        $sql_messages = "CREATE TABLE {$this->messages_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            conversation_id bigint(20) UNSIGNED NOT NULL,
            sender varchar(20) NOT NULL,
            sender_name varchar(255) DEFAULT '',
            sender_email varchar(255) DEFAULT '',
            message text NOT NULL,
            created_at datetime NOT NULL,
            timestamp bigint(20) UNSIGNED NOT NULL,
            PRIMARY KEY  (id),
            KEY conversation_id (conversation_id),
            KEY timestamp (timestamp),
            KEY created_at (created_at)
        ) $charset_collate;";

        dbDelta($sql_conversations);
        dbDelta($sql_messages);
        $this->tables_exist = true;
    }

    /**
     * Send a message - INSTANT
     */
    public function send_message($conversation_id, $sender, $message, $sender_name = '', $sender_email = '') {
        global $wpdb;

        // Ensure tables exist
        if (!$this->tables_exist()) {
            return new WP_Error('tables_missing', 'Database tables do not exist');
        }

        $timestamp = time();
        $created_at = current_time('mysql');

        $result = $wpdb->insert(
            $this->messages_table,
            [
                'conversation_id' => $conversation_id,
                'sender' => sanitize_key($sender),
                'sender_name' => sanitize_text_field($sender_name),
                'sender_email' => sanitize_email($sender_email),
                'message' => wp_kses_post($message),
                'created_at' => $created_at,
                'timestamp' => $timestamp,
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%d']
        );

        if (false === $result) {
            $error_msg = $wpdb->last_error ?: 'Failed to insert message';
            return new WP_Error('insert_failed', $error_msg);
        }

        $message_id = $wpdb->insert_id;

        // Update conversation timestamp
        $wpdb->update(
            $this->conversations_table,
            ['updated_at' => $created_at],
            ['id' => $conversation_id],
            ['%s'],
            ['%d']
        );

        return [
            'id' => $message_id,
            'conversation_id' => $conversation_id,
            'sender' => $sender,
            'sender_name' => $sender_name,
            'sender_email' => $sender_email,
            'content' => $message,
            'timestamp' => $timestamp,
        ];
    }

    /**
     * Get or create conversation - INSTANT
     */
    public function ensure_conversation($session_id, $conversation_id = 0) {
        global $wpdb;

        // Check if tables exist first
        if (!$this->tables_exist()) {
            $this->maybe_create_tables();

            // Check again after creation attempt
            if (!$this->tables_exist()) {
                return new WP_Error('tables_missing', 'Database tables do not exist and could not be created');
            }
        }

        // Try to get existing conversation
        if ($conversation_id) {
            $conversation = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->conversations_table} WHERE id = %d",
                $conversation_id
            ), ARRAY_A);

            if ($conversation) {
                return $conversation;
            }
        }

        // Try by session ID
        if ($session_id) {
            $conversation = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->conversations_table} WHERE session_id = %s",
                $session_id
            ), ARRAY_A);

            if ($conversation) {
                return $conversation;
            }
        }

        // Cannot create conversation without a session_id
        if (empty($session_id)) {
            return new WP_Error('missing_session', 'Session ID is required to create a conversation');
        }

        // Create new conversation
        $user_id = get_current_user_id();
        $user = wp_get_current_user();
        $user_name = $user->exists() ? $user->display_name : 'Guest ' . substr($session_id, 0, 8);
        $user_email = $user->exists() ? $user->user_email : '';

        $created_at = current_time('mysql');

        $wpdb->insert(
            $this->conversations_table,
            [
                'session_id' => $session_id,
                'user_id' => $user_id,
                'user_name' => $user_name,
                'user_email' => $user_email,
                'status' => 'active',
                'created_at' => $created_at,
                'updated_at' => $created_at,
            ],
            ['%s', '%d', '%s', '%s', '%s', '%s', '%s']
        );

        $new_id = $wpdb->insert_id;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->conversations_table} WHERE id = %d",
            $new_id
        ), ARRAY_A);
    }

    /**
     * Fetch messages since timestamp - INSTANT
     */
    public function fetch_messages($conversation_id, $since_timestamp = 0) {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->messages_table}
            WHERE conversation_id = %d
            AND timestamp > %d
            ORDER BY timestamp ASC",
            $conversation_id,
            $since_timestamp
        );

        $messages = $wpdb->get_results($sql, ARRAY_A);

        return array_map(function($msg) {
            return [
                'id' => (int) $msg['id'],
                'sender' => $msg['sender'],
                'sender_name' => $msg['sender_name'],
                'sender_email' => $msg['sender_email'],
                'content' => $msg['message'],
                'timestamp' => (int) $msg['timestamp'],
            ];
        }, $messages);
    }

    /**
     * Get all messages for a conversation
     */
    public function get_all_messages($conversation_id) {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->messages_table}
            WHERE conversation_id = %d
            ORDER BY timestamp ASC",
            $conversation_id
        );

        $messages = $wpdb->get_results($sql, ARRAY_A);

        return array_map(function($msg) {
            return [
                'id' => (int) $msg['id'],
                'sender' => $msg['sender'],
                'sender_name' => $msg['sender_name'],
                'sender_email' => $msg['sender_email'],
                'content' => $msg['message'],
                'timestamp' => (int) $msg['timestamp'],
            ];
        }, $messages);
    }

    /**
     * Get recent conversations for admin
     */
    public function get_recent_conversations($limit = 10) {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->conversations_table}
            ORDER BY updated_at DESC
            LIMIT %d",
            $limit
        );

        return $wpdb->get_results($sql, ARRAY_A);
    }
}

// Initialize
SFFC_Live_Expert_Messaging::get_instance();
