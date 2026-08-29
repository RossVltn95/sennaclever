<?php
/**
 * CRM Conversation Model
 *
 * Handles email thread/conversation operations.
 *
 * @package SennaCareers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_Conversation {

    /**
     * Database table names
     */
    private $table;
    private $messages_table;
    private $recruiters_table;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'sffc_crm_conversations';
        $this->messages_table = $wpdb->prefix . 'sffc_crm_messages';
        $this->recruiters_table = $wpdb->prefix . 'sffc_crm_recruiters';
    }

    /**
     * Get a single conversation
     *
     * @param int $id Conversation ID
     * @return array|null
     */
    public function get($id) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id),
            ARRAY_A
        );
    }

    /**
     * Get conversation with recruiter details
     *
     * @param int $id Conversation ID
     * @param int $user_id User ID for ownership check
     * @return array|null
     */
    public function get_with_details($id, $user_id) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT c.*,
                    r.name as recruiter_name,
                    r.email as recruiter_email,
                    r.photo_url as recruiter_photo,
                    r.firm as recruiter_firm,
                    r.title as recruiter_title,
                    r.linkedin_url as recruiter_linkedin
                FROM {$this->table} c
                LEFT JOIN {$this->recruiters_table} r ON c.recruiter_id = r.id
                WHERE c.id = %d AND c.user_id = %d",
                $id,
                $user_id
            ),
            ARRAY_A
        );
    }

    /**
     * Get user's conversations with filtering
     *
     * @param int   $user_id User ID
     * @param array $args    Query arguments
     * @return array
     */
    public function get_user_conversations($user_id, $args = []) {
        global $wpdb;

        $defaults = [
            'status'       => 'active',
            'channel'      => null,
            'is_read'      => null,
            'is_starred'   => null,
            'recruiter_id' => null,
            'search'       => null,
            'orderby'      => 'last_message_at',
            'order'        => 'DESC',
            'limit'        => 50,
            'offset'       => 0,
            'count_only'   => false
        ];

        $args = wp_parse_args($args, $defaults);

        // Build WHERE clause
        $where = ["c.user_id = %d"];
        $params = [$user_id];

        // Status filter
        if (!empty($args['status'])) {
            if ($args['status'] === 'all') {
                $where[] = "c.status != 'spam'";
            } else {
                $where[] = "c.status = %s";
                $params[] = $args['status'];
            }
        }

        // Channel filter
        if (!empty($args['channel'])) {
            $where[] = "c.channel = %s";
            $params[] = $args['channel'];
        }

        // Read status filter
        if ($args['is_read'] !== null) {
            $where[] = "c.is_read = %d";
            $params[] = $args['is_read'] ? 1 : 0;
        }

        // Starred filter
        if ($args['is_starred'] !== null) {
            $where[] = "c.is_starred = %d";
            $params[] = $args['is_starred'] ? 1 : 0;
        }

        // Recruiter filter
        if (!empty($args['recruiter_id'])) {
            $where[] = "c.recruiter_id = %d";
            $params[] = $args['recruiter_id'];
        }

        // Search filter
        if (!empty($args['search'])) {
            $search_like = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = "(c.subject LIKE %s OR r.name LIKE %s OR r.email LIKE %s)";
            $params[] = $search_like;
            $params[] = $search_like;
            $params[] = $search_like;
        }

        $where_clause = implode(' AND ', $where);

        // Count query
        if ($args['count_only']) {
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table} c
                    LEFT JOIN {$this->recruiters_table} r ON c.recruiter_id = r.id
                    WHERE {$where_clause}",
                    $params
                )
            );
            return intval($count);
        }

        // Validate orderby
        $valid_orderby = ['last_message_at', 'created_at', 'subject', 'unread_count'];
        $orderby = in_array($args['orderby'], $valid_orderby) ? 'c.' . $args['orderby'] : 'c.last_message_at';

        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        $limit = intval($args['limit']);
        $offset = intval($args['offset']);

        $conversations = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT c.*,
                    r.name as recruiter_name,
                    r.email as recruiter_email,
                    r.photo_url as recruiter_photo,
                    r.firm as recruiter_firm
                FROM {$this->table} c
                LEFT JOIN {$this->recruiters_table} r ON c.recruiter_id = r.id
                WHERE {$where_clause}
                ORDER BY {$orderby} {$order}
                LIMIT %d OFFSET %d",
                array_merge($params, [$limit, $offset])
            ),
            ARRAY_A
        );

        return $conversations;
    }

    /**
     * Get conversation counts by status
     *
     * @param int $user_id User ID
     * @return array
     */
    public function get_counts($user_id) {
        global $wpdb;

        $counts = [
            'total'    => 0,
            'unread'   => 0,
            'starred'  => 0,
            'archived' => 0
        ];

        // Total active
        $counts['total'] = intval($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE user_id = %d AND status = 'active'",
                $user_id
            )
        ));

        // Unread
        $counts['unread'] = intval($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE user_id = %d AND status = 'active' AND is_read = 0",
                $user_id
            )
        ));

        // Starred
        $counts['starred'] = intval($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE user_id = %d AND status = 'active' AND is_starred = 1",
                $user_id
            )
        ));

        // Archived
        $counts['archived'] = intval($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE user_id = %d AND status = 'archived'",
                $user_id
            )
        ));

        return $counts;
    }

    /**
     * Create a new conversation
     *
     * @param int   $user_id User ID
     * @param array $data    Conversation data
     * @return int|WP_Error Conversation ID or error
     */
    public function create($user_id, $data) {
        global $wpdb;

        $insert_data = [
            'user_id'                => $user_id,
            'recruiter_id'           => isset($data['recruiter_id']) ? intval($data['recruiter_id']) : null,
            'post_id'                => isset($data['post_id']) ? intval($data['post_id']) : null,
            'subject'                => isset($data['subject']) ? sanitize_text_field($data['subject']) : null,
            'channel'                => isset($data['channel']) ? $data['channel'] : 'email',
            'status'                 => 'active',
            'is_read'                => isset($data['is_read']) ? intval($data['is_read']) : 1,
            'last_message_at'        => isset($data['last_message_at']) ? $data['last_message_at'] : current_time('mysql'),
            'last_message_preview'   => isset($data['last_message_preview']) ? substr(sanitize_text_field($data['last_message_preview']), 0, 200) : null,
            'last_message_direction' => isset($data['direction']) ? $data['direction'] : 'outbound',
            'message_count'          => 1,
            'unread_count'           => isset($data['direction']) && $data['direction'] === 'inbound' ? 1 : 0,
            'thread_id'              => isset($data['thread_id']) ? sanitize_text_field($data['thread_id']) : null,
            'created_at'             => current_time('mysql')
        ];

        $result = $wpdb->insert($this->table, $insert_data);

        if ($result === false) {
            return new WP_Error('db_error', 'Failed to create conversation.');
        }

        return $wpdb->insert_id;
    }

    /**
     * Update conversation
     *
     * @param int   $id      Conversation ID
     * @param int   $user_id User ID
     * @param array $data    Updated data
     * @return bool|WP_Error
     */
    public function update($id, $user_id, $data) {
        global $wpdb;

        // Verify ownership
        $conversation = $this->get($id);
        if (!$conversation || $conversation['user_id'] != $user_id) {
            return new WP_Error('not_found', 'Conversation not found.');
        }

        $update_data = [];

        if (isset($data['subject'])) {
            $update_data['subject'] = sanitize_text_field($data['subject']);
        }

        if (isset($data['status'])) {
            $update_data['status'] = $data['status'];
        }

        if (isset($data['is_read'])) {
            $update_data['is_read'] = intval($data['is_read']);
            if ($data['is_read']) {
                $update_data['unread_count'] = 0;
            }
        }

        if (isset($data['is_starred'])) {
            $update_data['is_starred'] = intval($data['is_starred']);
        }

        if (isset($data['recruiter_id'])) {
            $update_data['recruiter_id'] = intval($data['recruiter_id']);
        }

        if (isset($data['labels'])) {
            $update_data['labels'] = sanitize_text_field($data['labels']);
        }

        if (empty($update_data)) {
            return true;
        }

        $result = $wpdb->update(
            $this->table,
            $update_data,
            ['id' => $id]
        );

        if ($result === false) {
            return new WP_Error('db_error', 'Failed to update conversation.');
        }

        return true;
    }

    /**
     * Mark conversation as read
     *
     * @param int $id      Conversation ID
     * @param int $user_id User ID
     * @return bool|WP_Error
     */
    public function mark_read($id, $user_id) {
        global $wpdb;

        // Verify ownership
        $conversation = $this->get($id);
        if (!$conversation || $conversation['user_id'] != $user_id) {
            return new WP_Error('not_found', 'Conversation not found.');
        }

        // Mark conversation as read
        $wpdb->update(
            $this->table,
            ['is_read' => 1, 'unread_count' => 0],
            ['id' => $id]
        );

        // Mark all messages in conversation as read
        $wpdb->update(
            $this->messages_table,
            ['is_read' => 1, 'read_at' => current_time('mysql')],
            ['conversation_id' => $id, 'is_read' => 0]
        );

        return true;
    }

    /**
     * Mark conversation as unread
     *
     * @param int $id      Conversation ID
     * @param int $user_id User ID
     * @return bool|WP_Error
     */
    public function mark_unread($id, $user_id) {
        return $this->update($id, $user_id, ['is_read' => 0]);
    }

    /**
     * Toggle starred status
     *
     * @param int $id      Conversation ID
     * @param int $user_id User ID
     * @return bool|WP_Error
     */
    public function toggle_starred($id, $user_id) {
        $conversation = $this->get($id);
        if (!$conversation || $conversation['user_id'] != $user_id) {
            return new WP_Error('not_found', 'Conversation not found.');
        }

        $new_starred = $conversation['is_starred'] ? 0 : 1;
        return $this->update($id, $user_id, ['is_starred' => $new_starred]);
    }

    /**
     * Archive conversation
     *
     * @param int $id      Conversation ID
     * @param int $user_id User ID
     * @return bool|WP_Error
     */
    public function archive($id, $user_id) {
        return $this->update($id, $user_id, ['status' => 'archived']);
    }

    /**
     * Unarchive conversation
     *
     * @param int $id      Conversation ID
     * @param int $user_id User ID
     * @return bool|WP_Error
     */
    public function unarchive($id, $user_id) {
        return $this->update($id, $user_id, ['status' => 'active']);
    }

    /**
     * Delete conversation and its messages
     *
     * @param int $id      Conversation ID
     * @param int $user_id User ID
     * @return bool|WP_Error
     */
    public function delete($id, $user_id) {
        global $wpdb;

        // Verify ownership
        $conversation = $this->get($id);
        if (!$conversation || $conversation['user_id'] != $user_id) {
            return new WP_Error('not_found', 'Conversation not found.');
        }

        // Delete messages first
        $wpdb->delete($this->messages_table, ['conversation_id' => $id]);

        // Delete conversation
        $result = $wpdb->delete($this->table, ['id' => $id]);

        if ($result === false) {
            return new WP_Error('db_error', 'Failed to delete conversation.');
        }

        return true;
    }

    /**
     * Update conversation stats after new message
     *
     * @param int    $id        Conversation ID
     * @param string $direction Message direction (inbound/outbound)
     * @param string $preview   Message preview
     */
    public function update_after_message($id, $direction, $preview) {
        global $wpdb;

        $update_data = [
            'last_message_at'        => current_time('mysql'),
            'last_message_preview'   => substr(sanitize_text_field($preview), 0, 200),
            'last_message_direction' => $direction
        ];

        // Update message count
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->table} SET
                    message_count = message_count + 1,
                    last_message_at = %s,
                    last_message_preview = %s,
                    last_message_direction = %s" .
                    ($direction === 'inbound' ? ", unread_count = unread_count + 1, is_read = 0" : "") .
                " WHERE id = %d",
                $update_data['last_message_at'],
                $update_data['last_message_preview'],
                $direction,
                $id
            )
        );
    }

    /**
     * Find or create conversation for a recruiter
     *
     * @param int    $user_id      User ID
     * @param int    $recruiter_id Recruiter ID
     * @param string $subject      Email subject (optional)
     * @param string $thread_id    External thread ID (optional)
     * @return int Conversation ID
     */
    public function find_or_create($user_id, $recruiter_id, $subject = null, $thread_id = null) {
        global $wpdb;

        // Try to find existing conversation by thread_id first
        if ($thread_id) {
            $existing = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$this->table} WHERE user_id = %d AND thread_id = %s",
                    $user_id,
                    $thread_id
                )
            );

            if ($existing) {
                return intval($existing);
            }
        }

        // Try to find by recruiter and similar subject
        if ($recruiter_id && $subject) {
            // Extract base subject (remove Re:, Fwd:, etc.)
            $base_subject = preg_replace('/^(Re:|Fwd:|FW:|RE:)\s*/i', '', $subject);
            $base_subject = trim($base_subject);

            $existing = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$this->table}
                    WHERE user_id = %d AND recruiter_id = %d
                    AND (subject = %s OR subject = %s OR subject LIKE %s)
                    AND status = 'active'
                    ORDER BY last_message_at DESC
                    LIMIT 1",
                    $user_id,
                    $recruiter_id,
                    $subject,
                    $base_subject,
                    '%' . $wpdb->esc_like($base_subject) . '%'
                )
            );

            if ($existing) {
                // Update thread_id if provided
                if ($thread_id) {
                    $wpdb->update(
                        $this->table,
                        ['thread_id' => $thread_id],
                        ['id' => $existing]
                    );
                }
                return intval($existing);
            }
        }

        // Create new conversation
        $conversation_id = $this->create($user_id, [
            'recruiter_id' => $recruiter_id,
            'subject'      => $subject,
            'thread_id'    => $thread_id,
            'channel'      => 'email'
        ]);

        if (is_wp_error($conversation_id)) {
            return 0;
        }

        return $conversation_id;
    }

    /**
     * Get conversations for a recruiter
     *
     * @param int $user_id      User ID
     * @param int $recruiter_id Recruiter ID
     * @return array
     */
    public function get_recruiter_conversations($user_id, $recruiter_id) {
        return $this->get_user_conversations($user_id, [
            'recruiter_id' => $recruiter_id,
            'status'       => 'all'
        ]);
    }

    /**
     * Link conversation to recruiter
     *
     * @param int $id           Conversation ID
     * @param int $user_id      User ID
     * @param int $recruiter_id Recruiter ID
     * @return bool|WP_Error
     */
    public function link_to_recruiter($id, $user_id, $recruiter_id) {
        return $this->update($id, $user_id, ['recruiter_id' => $recruiter_id]);
    }
}
