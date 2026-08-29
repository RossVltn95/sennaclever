<?php
/**
 * Candidate Conversations Database
 *
 * Creates and manages the conversation messages table
 *
 * @package SennaFinanceCareer
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Candidate_Conversations_Database {

    private static $instance = null;
    private static $table_name = 'sffc_conversation_messages';

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', [$this, 'maybe_create_table']);
    }

    /**
     * Get the full table name with prefix
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::$table_name;
    }

    /**
     * Create the messages table if it doesn't exist
     */
    public function maybe_create_table() {
        $db_version = get_option('sffc_conv_messages_db_version', '0');

        if (version_compare($db_version, '1.0.0', '<')) {
            $this->create_table();
            update_option('sffc_conv_messages_db_version', '1.0.0');
        }
    }

    /**
     * Create the conversation messages table
     */
    private function create_table() {
        global $wpdb;

        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            conversation_id bigint(20) NOT NULL,
            sender_type varchar(20) NOT NULL DEFAULT 'admin',
            sender_id bigint(20) DEFAULT NULL,
            message_text text NOT NULL,
            sent_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            is_read tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY conversation_id (conversation_id),
            KEY sender_type (sender_type),
            KEY sent_at (sent_at),
            KEY is_read (is_read)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Add a new message to a conversation
     *
     * @param int $conversation_id Post ID of the conversation
     * @param string $sender_type 'admin' or 'candidate'
     * @param int $sender_id User ID of sender
     * @param string $message_text Message content
     * @return int|false Message ID or false on failure
     */
    public static function add_message($conversation_id, $sender_type, $sender_id, $message_text) {
        global $wpdb;

        $table_name = self::get_table_name();

        $result = $wpdb->insert(
            $table_name,
            [
                'conversation_id' => $conversation_id,
                'sender_type' => $sender_type,
                'sender_id' => $sender_id,
                'message_text' => $message_text,
                'sent_at' => current_time('mysql'),
                'is_read' => ($sender_type === 'admin') ? 0 : 1 // Admin messages start unread for candidate
            ],
            ['%d', '%s', '%d', '%s', '%s', '%d']
        );

        if ($result) {
            $message_id = $wpdb->insert_id;

            // Update conversation last message info
            update_post_meta($conversation_id, '_last_message_date', current_time('mysql'));
            update_post_meta($conversation_id, '_last_message_preview', wp_trim_words($message_text, 15, '...'));

            // If admin sent, mark conversation as having unread messages for candidate
            if ($sender_type === 'admin') {
                update_post_meta($conversation_id, '_is_unread', 1);
            }

            // Fire action for notifications
            do_action('sffc_conversation_message_sent', $conversation_id, $message_id, [
                'sender_type' => $sender_type,
                'sender_id' => $sender_id,
                'message_text' => $message_text,
            ]);

            return $message_id;
        }

        return false;
    }

    /**
     * Get all messages for a conversation
     *
     * @param int $conversation_id Post ID of the conversation
     * @param int $limit Number of messages to retrieve (0 = all)
     * @return array Array of message objects
     */
    public static function get_messages($conversation_id, $limit = 0) {
        global $wpdb;

        $table_name = self::get_table_name();

        $sql = $wpdb->prepare(
            "SELECT * FROM $table_name WHERE conversation_id = %d ORDER BY sent_at ASC",
            $conversation_id
        );

        if ($limit > 0) {
            $sql .= $wpdb->prepare(" LIMIT %d", $limit);
        }

        return $wpdb->get_results($sql);
    }

    /**
     * Mark all messages in a conversation as read for candidate
     *
     * @param int $conversation_id Post ID of the conversation
     * @return int Number of messages marked as read
     */
    public static function mark_conversation_read($conversation_id) {
        global $wpdb;

        $table_name = self::get_table_name();

        $result = $wpdb->update(
            $table_name,
            ['is_read' => 1],
            [
                'conversation_id' => $conversation_id,
                'sender_type' => 'admin',
                'is_read' => 0
            ],
            ['%d'],
            ['%d', '%s', '%d']
        );

        // Update conversation unread status
        update_post_meta($conversation_id, '_is_unread', 0);

        return $result;
    }

    /**
     * Get unread message count for a conversation
     *
     * @param int $conversation_id Post ID of the conversation
     * @return int Number of unread messages
     */
    public static function get_unread_count($conversation_id) {
        global $wpdb;

        $table_name = self::get_table_name();

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE conversation_id = %d AND sender_type = 'admin' AND is_read = 0",
            $conversation_id
        ));
    }

    /**
     * Get total unread count for a user across all conversations
     *
     * @param int $user_id WordPress user ID
     * @return int Total unread messages
     */
    public static function get_total_unread_for_user($user_id) {
        global $wpdb;

        $table_name = self::get_table_name();

        // Get all conversation IDs for this user
        $conversation_ids = get_posts([
            'post_type' => 'sffc_candidate_conv',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => '_candidate_user_id',
                    'value' => $user_id,
                    'compare' => '='
                ]
            ]
        ]);

        if (empty($conversation_ids)) {
            return 0;
        }

        $ids_placeholder = implode(',', array_fill(0, count($conversation_ids), '%d'));

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE conversation_id IN ($ids_placeholder) AND sender_type = 'admin' AND is_read = 0",
            ...$conversation_ids
        ));
    }

    /**
     * Delete all messages for a conversation
     *
     * @param int $conversation_id Post ID of the conversation
     * @return int|false Number of deleted rows or false
     */
    public static function delete_conversation_messages($conversation_id) {
        global $wpdb;

        $table_name = self::get_table_name();

        return $wpdb->delete(
            $table_name,
            ['conversation_id' => $conversation_id],
            ['%d']
        );
    }
}

// Initialize
SFFC_Candidate_Conversations_Database::get_instance();
