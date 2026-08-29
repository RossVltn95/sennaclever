<?php
/**
 * CRM Message Model
 *
 * Handles individual messages within conversations.
 * Column names match database schema in class-crm-database-schema.php
 *
 * @package SennaCareers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_Message {

    /**
     * Database table name
     */
    private $table_name;

    /**
     * Conversations table name
     */
    private $conversations_table;

    /**
     * Valid directions
     */
    private $valid_directions = array('inbound', 'outbound');

    /**
     * Valid channels
     */
    private $valid_channels = array('email', 'linkedin', 'manual');

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'sffc_crm_messages';
        $this->conversations_table = $wpdb->prefix . 'sffc_crm_conversations';
    }

    /**
     * Get a single message by ID
     *
     * @param int $message_id Message ID
     * @return array|null Message data or null if not found
     */
    public function get($message_id) {
        global $wpdb;

        $message = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $message_id
            ),
            ARRAY_A
        );

        if ($message) {
            $message = $this->parse_message($message);
        }

        return $message;
    }

    /**
     * Get messages for a conversation
     *
     * @param int   $conversation_id Conversation ID
     * @param array $args            Query arguments
     * @return array Messages
     */
    public function get_conversation_messages($conversation_id, $args = array()) {
        global $wpdb;

        $defaults = array(
            'orderby' => 'sent_at',
            'order'   => 'ASC',
            'limit'   => 100,
            'offset'  => 0,
        );

        $args = wp_parse_args($args, $defaults);

        // Sanitize orderby
        $allowed_orderby = array('id', 'sent_at', 'created_at', 'direction');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'sent_at';
        $order = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table_name}
            WHERE conversation_id = %d
            ORDER BY {$orderby} {$order}
            LIMIT %d OFFSET %d",
            $conversation_id,
            $args['limit'],
            $args['offset']
        );

        $messages = $wpdb->get_results($sql, ARRAY_A);

        return array_map(array($this, 'parse_message'), $messages);
    }

    /**
     * Get messages for a conversation strictly after a known message id.
     *
     * @param int   $conversation_id Conversation ID
     * @param int   $after_id        Lower-bound message ID (exclusive)
     * @param array $args            Query arguments
     * @return array Messages
     */
    public function get_conversation_messages_after($conversation_id, $after_id, $args = array()) {
        global $wpdb;

        $defaults = array(
            'orderby' => 'id',
            'order'   => 'ASC',
            'limit'   => 100,
        );

        $args = wp_parse_args($args, $defaults);

        $allowed_orderby = array('id', 'sent_at', 'created_at', 'direction');
        $orderby = in_array($args['orderby'], $allowed_orderby, true) ? $args['orderby'] : 'id';
        $order = strtoupper((string) $args['order']) === 'DESC' ? 'DESC' : 'ASC';

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table_name}
             WHERE conversation_id = %d
               AND id > %d
             ORDER BY {$orderby} {$order}
             LIMIT %d",
            $conversation_id,
            $after_id,
            $args['limit']
        );

        $messages = $wpdb->get_results($sql, ARRAY_A);

        return array_map(array($this, 'parse_message'), $messages);
    }

    /**
     * Get message count for a conversation
     *
     * @param int $conversation_id Conversation ID
     * @return int Message count
     */
    public function get_conversation_message_count($conversation_id) {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE conversation_id = %d",
                $conversation_id
            )
        );
    }

    /**
     * Create a new message
     *
     * Database columns: id, conversation_id, user_id, recruiter_id, outreach_id,
     * direction, channel, from_email, from_name, to_email, to_name, subject,
     * content, content_html, content_snippet, is_read, read_at, external_id,
     * external_thread_id, headers, attachments, sent_at, received_at, created_at
     *
     * @param int   $conversation_id Conversation ID
     * @param array $data            Message data
     * @return int|WP_Error Message ID on success, WP_Error on failure
     */
    public function create($conversation_id, $data) {
        global $wpdb;

        // Validate conversation exists and get user_id, recruiter_id
        $conversation = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, user_id, recruiter_id FROM {$this->conversations_table} WHERE id = %d",
                $conversation_id
            ),
            ARRAY_A
        );

        if (!$conversation) {
            return new WP_Error('invalid_conversation', 'Conversation not found.');
        }

        // Validate direction
        $direction = isset($data['direction']) ? $data['direction'] : 'outbound';
        if (!in_array($direction, $this->valid_directions)) {
            return new WP_Error('invalid_direction', 'Invalid message direction.');
        }

        // Validate content - accept either content or body_text/body_html for backwards compat
        $content = '';
        $content_html = '';

        if (!empty($data['content'])) {
            $content = $data['content'];
        } elseif (!empty($data['body_text'])) {
            $content = $data['body_text'];
        }

        if (!empty($data['content_html'])) {
            $content_html = $data['content_html'];
        } elseif (!empty($data['body_html'])) {
            $content_html = $data['body_html'];
        }

        if (empty($content) && empty($content_html)) {
            return new WP_Error('empty_content', 'Message content is required.');
        }

        // Generate snippet
        $snippet_text = $content ?: wp_strip_all_tags($content_html);
        $snippet_text = preg_replace('/\s+/', ' ', trim($snippet_text));
        $content_snippet = strlen($snippet_text) > 200 ? substr($snippet_text, 0, 197) . '...' : $snippet_text;

        // Validate channel
        $channel = isset($data['channel']) ? $data['channel'] : 'email';
        if (!in_array($channel, $this->valid_channels)) {
            $channel = 'email';
        }

        $insert_data = array(
            'conversation_id'    => $conversation_id,
            'user_id'            => $conversation['user_id'],
            'recruiter_id'       => $conversation['recruiter_id'],
            'outreach_id'        => isset($data['outreach_id']) ? intval($data['outreach_id']) : null,
            'direction'          => $direction,
            'channel'            => $channel,
            'from_email'         => isset($data['from_email']) ? sanitize_email($data['from_email']) : null,
            'from_name'          => isset($data['from_name']) ? sanitize_text_field($data['from_name']) : null,
            'to_email'           => isset($data['to_email']) ? sanitize_email($data['to_email']) : null,
            'to_name'            => isset($data['to_name']) ? sanitize_text_field($data['to_name']) : null,
            'subject'            => isset($data['subject']) ? sanitize_text_field($data['subject']) : null,
            'content'            => sanitize_textarea_field($content),
            'content_html'       => wp_kses_post($content_html),
            'content_snippet'    => $content_snippet,
            'is_read'            => $direction === 'outbound' ? 1 : 0,
            'external_id'        => isset($data['external_id']) ? sanitize_text_field($data['external_id']) : null,
            'external_thread_id' => isset($data['external_thread_id']) ? sanitize_text_field($data['external_thread_id']) : null,
            'headers'            => isset($data['headers']) ? wp_json_encode($data['headers']) : null,
            'attachments'        => isset($data['attachments']) ? wp_json_encode($data['attachments']) : null,
            'sent_at'            => $direction === 'outbound' ? (isset($data['sent_at']) ? $data['sent_at'] : current_time('mysql')) : null,
            'received_at'        => $direction === 'inbound' ? (isset($data['received_at']) ? $data['received_at'] : current_time('mysql')) : null,
            'created_at'         => current_time('mysql'),
        );

        $result = $wpdb->insert($this->table_name, $insert_data);

        if ($result === false) {
            return new WP_Error('db_error', 'Failed to create message: ' . $wpdb->last_error);
        }

        $message_id = $wpdb->insert_id;

        // Update conversation's last_message_at and preview
        $this->update_conversation_after_message($conversation_id, $content_snippet, $direction);

        // If inbound message, mark conversation as unread and increment unread count
        if ($direction === 'inbound') {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$this->conversations_table}
                    SET is_read = 0, unread_count = unread_count + 1
                    WHERE id = %d",
                    $conversation_id
                )
            );
        }

        // Increment message count
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->conversations_table}
                SET message_count = message_count + 1
                WHERE id = %d",
                $conversation_id
            )
        );

        return $message_id;
    }

    /**
     * Update a message
     *
     * @param int   $message_id Message ID
     * @param array $data       Update data
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function update($message_id, $data) {
        global $wpdb;

        $message = $this->get($message_id);
        if (!$message) {
            return new WP_Error('not_found', 'Message not found.');
        }

        $update_data = array();

        // Updateable fields
        if (isset($data['subject'])) {
            $update_data['subject'] = sanitize_text_field($data['subject']);
        }

        if (isset($data['content']) || isset($data['body_text'])) {
            $content = isset($data['content']) ? $data['content'] : $data['body_text'];
            $update_data['content'] = sanitize_textarea_field($content);
            // Update snippet
            $snippet_text = preg_replace('/\s+/', ' ', trim($content));
            $update_data['content_snippet'] = strlen($snippet_text) > 200 ? substr($snippet_text, 0, 197) . '...' : $snippet_text;
        }

        if (isset($data['content_html']) || isset($data['body_html'])) {
            $update_data['content_html'] = wp_kses_post(isset($data['content_html']) ? $data['content_html'] : $data['body_html']);
        }

        if (isset($data['is_read'])) {
            $update_data['is_read'] = intval($data['is_read']);
            if ($data['is_read']) {
                $update_data['read_at'] = current_time('mysql');
            }
        }

        if (empty($update_data)) {
            return true; // Nothing to update
        }

        $result = $wpdb->update(
            $this->table_name,
            $update_data,
            array('id' => $message_id),
            null,
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('db_error', 'Failed to update message: ' . $wpdb->last_error);
        }

        return true;
    }

    /**
     * Mark message as read
     *
     * @param int $message_id Message ID
     * @return bool|WP_Error
     */
    public function mark_read($message_id) {
        global $wpdb;

        $message = $this->get($message_id);
        if (!$message) {
            return new WP_Error('not_found', 'Message not found.');
        }

        if ($message['is_read']) {
            return true; // Already read
        }

        $result = $wpdb->update(
            $this->table_name,
            array('is_read' => 1, 'read_at' => current_time('mysql')),
            array('id' => $message_id),
            array('%d', '%s'),
            array('%d')
        );

        // Decrement unread count on conversation
        if ($result !== false && $message['direction'] === 'inbound') {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$this->conversations_table}
                    SET unread_count = GREATEST(unread_count - 1, 0)
                    WHERE id = %d",
                    $message['conversation_id']
                )
            );
        }

        return $result !== false;
    }

    /**
     * Delete a message
     *
     * @param int $message_id Message ID
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function delete($message_id) {
        global $wpdb;

        $message = $this->get($message_id);
        if (!$message) {
            return new WP_Error('not_found', 'Message not found.');
        }

        $conversation_id = $message['conversation_id'];

        $result = $wpdb->delete(
            $this->table_name,
            array('id' => $message_id),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('db_error', 'Failed to delete message.');
        }

        // Decrement message count and unread count if applicable
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->conversations_table}
                SET message_count = GREATEST(message_count - 1, 0)
                WHERE id = %d",
                $conversation_id
            )
        );

        if ($message['direction'] === 'inbound' && !$message['is_read']) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$this->conversations_table}
                    SET unread_count = GREATEST(unread_count - 1, 0)
                    WHERE id = %d",
                    $conversation_id
                )
            );
        }

        // Update last_message info
        $latest = $this->get_latest($conversation_id);
        if ($latest) {
            $this->update_conversation_after_message(
                $conversation_id,
                $latest['content_snippet'],
                $latest['direction']
            );
        }

        return true;
    }

    /**
     * Get the latest message in a conversation
     *
     * @param int $conversation_id Conversation ID
     * @return array|null Latest message or null
     */
    public function get_latest($conversation_id) {
        global $wpdb;

        $message = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name}
                WHERE conversation_id = %d
                ORDER BY COALESCE(sent_at, received_at, created_at) DESC
                LIMIT 1",
                $conversation_id
            ),
            ARRAY_A
        );

        if ($message) {
            $message = $this->parse_message($message);
        }

        return $message;
    }

    /**
     * Search messages
     *
     * @param int    $user_id User ID
     * @param string $query   Search query
     * @param array  $args    Additional arguments
     * @return array Search results
     */
    public function search($user_id, $query, $args = array()) {
        global $wpdb;

        $defaults = array(
            'limit' => 20,
        );

        $args = wp_parse_args($args, $defaults);

        $search_term = '%' . $wpdb->esc_like($query) . '%';

        $sql = $wpdb->prepare(
            "SELECT m.*, c.recruiter_id, c.subject as conversation_subject
            FROM {$this->table_name} m
            INNER JOIN {$this->conversations_table} c ON m.conversation_id = c.id
            WHERE c.user_id = %d
            AND (m.subject LIKE %s OR m.content LIKE %s OR m.from_name LIKE %s)
            ORDER BY COALESCE(m.sent_at, m.received_at, m.created_at) DESC
            LIMIT %d",
            $user_id,
            $search_term,
            $search_term,
            $search_term,
            $args['limit']
        );

        $messages = $wpdb->get_results($sql, ARRAY_A);

        return array_map(array($this, 'parse_message'), $messages);
    }

    /**
     * Get messages by external ID (for deduplication during sync)
     *
     * @param string $external_id External message ID
     * @return array|null Message or null
     */
    public function get_by_external_id($external_id) {
        global $wpdb;

        $message = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE external_id = %s",
                $external_id
            ),
            ARRAY_A
        );

        if ($message) {
            $message = $this->parse_message($message);
        }

        return $message;
    }

    /**
     * Create outbound message (sent by candidate)
     *
     * @param int   $conversation_id Conversation ID
     * @param array $data            Message data
     * @return int|WP_Error Message ID on success
     */
    public function create_outbound($conversation_id, $data) {
        $data['direction'] = 'outbound';
        return $this->create($conversation_id, $data);
    }

    /**
     * Create inbound message (received from recruiter)
     *
     * @param int   $conversation_id Conversation ID
     * @param array $data            Message data
     * @return int|WP_Error Message ID on success
     */
    public function create_inbound($conversation_id, $data) {
        $data['direction'] = 'inbound';
        return $this->create($conversation_id, $data);
    }

    /**
     * Get message snippet
     *
     * @param int $message_id Message ID
     * @param int $length     Snippet length
     * @return string Snippet
     */
    public function get_snippet($message_id, $length = 100) {
        $message = $this->get($message_id);
        if (!$message) {
            return '';
        }

        if (!empty($message['content_snippet'])) {
            return strlen($message['content_snippet']) > $length
                ? substr($message['content_snippet'], 0, $length) . '...'
                : $message['content_snippet'];
        }

        $text = $message['content'] ?: wp_strip_all_tags($message['content_html']);
        $text = preg_replace('/\s+/', ' ', trim($text));

        if (strlen($text) > $length) {
            $text = substr($text, 0, $length) . '...';
        }

        return $text;
    }

    /**
     * Update conversation after a new message
     *
     * @param int    $conversation_id Conversation ID
     * @param string $preview         Message preview
     * @param string $direction       Message direction
     */
    private function update_conversation_after_message($conversation_id, $preview, $direction) {
        global $wpdb;

        $timestamp = current_time('mysql');

        $wpdb->update(
            $this->conversations_table,
            array(
                'last_message_at'        => $timestamp,
                'last_message_preview'   => substr($preview, 0, 200),
                'last_message_direction' => $direction,
            ),
            array('id' => $conversation_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
    }

    /**
     * Parse message data (decode JSON fields, add computed fields)
     *
     * @param array $message Raw message data
     * @return array Parsed message data
     */
    private function parse_message($message) {
        if (isset($message['headers']) && is_string($message['headers'])) {
            $message['headers'] = json_decode($message['headers'], true);
        }

        if (isset($message['attachments']) && is_string($message['attachments'])) {
            $message['attachments'] = json_decode($message['attachments'], true);
        }

        // Add snippet alias for JS compatibility
        if (!isset($message['snippet']) && isset($message['content_snippet'])) {
            $message['snippet'] = $message['content_snippet'];
        }

        // Add body_text/body_html aliases for backwards compatibility
        if (!isset($message['body_text']) && isset($message['content'])) {
            $message['body_text'] = $message['content'];
        }
        if (!isset($message['body_html']) && isset($message['content_html'])) {
            $message['body_html'] = $message['content_html'];
        }

        return $message;
    }

    /**
     * Bulk import messages (for email sync)
     *
     * @param int   $conversation_id Conversation ID
     * @param array $messages        Array of message data
     * @return array Results with created and skipped counts
     */
    public function bulk_import($conversation_id, $messages) {
        $results = array(
            'created' => 0,
            'skipped' => 0,
            'errors'  => 0,
        );

        foreach ($messages as $message_data) {
            // Check if already exists by external_id
            if (!empty($message_data['external_id'])) {
                $existing = $this->get_by_external_id($message_data['external_id']);
                if ($existing) {
                    $results['skipped']++;
                    continue;
                }
            }

            $result = $this->create($conversation_id, $message_data);

            if (is_wp_error($result)) {
                $results['errors']++;
            } else {
                $results['created']++;
            }
        }

        return $results;
    }
}
