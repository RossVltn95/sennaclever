<?php
/**
 * CRM Alert Model
 *
 * Phase 6: Intelligence & Analytics
 * Handles alerts for new posts, keywords, reminders, and notifications.
 *
 * @package SennaCareers
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_Alert {

    private $table_name;
    private $notifications_table;

    /**
     * Alert types
     */
    const TYPE_KEYWORD = 'keyword';
    const TYPE_RECRUITER_POST = 'recruiter_post';
    const TYPE_FOLLOW_UP = 'follow_up';
    const TYPE_TASK_DUE = 'task_due';
    const TYPE_REPLY_RECEIVED = 'reply_received';
    const TYPE_SEQUENCE_COMPLETE = 'sequence_complete';

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'sffc_crm_alerts';
        $this->notifications_table = $wpdb->prefix . 'sffc_crm_notifications';
    }

    /**
     * Create alerts table if not exists
     */
    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Alerts configuration table
        $table_alerts = $wpdb->prefix . 'sffc_crm_alerts';
        $sql_alerts = "CREATE TABLE IF NOT EXISTS $table_alerts (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            type varchar(50) NOT NULL,
            name varchar(200) NOT NULL,
            config longtext,
            is_active tinyint(1) DEFAULT 1,
            email_enabled tinyint(1) DEFAULT 1,
            push_enabled tinyint(1) DEFAULT 0,
            last_triggered_at datetime,
            trigger_count int DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user (user_id),
            KEY idx_type (type),
            KEY idx_active (is_active)
        ) $charset_collate;";

        // Notifications table (triggered alerts/notifications)
        $table_notifications = $wpdb->prefix . 'sffc_crm_notifications';
        $sql_notifications = "CREATE TABLE IF NOT EXISTS $table_notifications (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            alert_id bigint(20),
            type varchar(50) NOT NULL,
            title varchar(255) NOT NULL,
            message text,
            data longtext,
            is_read tinyint(1) DEFAULT 0,
            read_at datetime,
            action_url varchar(500),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_read (user_id, is_read),
            KEY idx_alert (alert_id),
            KEY idx_created (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_alerts);
        dbDelta($sql_notifications);
    }

    // ============================================
    // ALERT CRUD
    // ============================================

    /**
     * Create a new alert
     *
     * @param int   $user_id User ID
     * @param array $data    Alert data
     * @return int|WP_Error Alert ID on success
     */
    public function create($user_id, $data) {
        global $wpdb;

        $type = sanitize_text_field($data['type'] ?? '');
        $name = sanitize_text_field($data['name'] ?? '');

        if (empty($type) || empty($name)) {
            return new WP_Error('missing_required', 'Type and name are required.');
        }

        $config = isset($data['config']) ? $data['config'] : [];
        if (is_array($config)) {
            $config = wp_json_encode($config);
        }

        $result = $wpdb->insert(
            $this->table_name,
            [
                'user_id'       => $user_id,
                'type'          => $type,
                'name'          => $name,
                'config'        => $config,
                'is_active'     => isset($data['is_active']) ? (int) $data['is_active'] : 1,
                'email_enabled' => isset($data['email_enabled']) ? (int) $data['email_enabled'] : 1,
                'push_enabled'  => isset($data['push_enabled']) ? (int) $data['push_enabled'] : 0,
            ],
            ['%d', '%s', '%s', '%s', '%d', '%d', '%d']
        );

        if ($result === false) {
            return new WP_Error('db_error', 'Failed to create alert.');
        }

        return $wpdb->insert_id;
    }

    /**
     * Get alert by ID
     *
     * @param int $id Alert ID
     * @return array|null Alert data
     */
    public function get($id) {
        global $wpdb;

        $alert = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        if ($alert) {
            $alert = $this->parse_alert($alert);
        }

        return $alert;
    }

    /**
     * Get user's alerts
     *
     * @param int   $user_id User ID
     * @param array $args    Query arguments
     * @return array Alerts
     */
    public function get_user_alerts($user_id, $args = []) {
        global $wpdb;

        $defaults = [
            'type'      => null,
            'is_active' => null,
            'limit'     => 50,
            'offset'    => 0,
        ];

        $args = wp_parse_args($args, $defaults);

        $where = ['user_id = %d'];
        $params = [$user_id];

        if ($args['type']) {
            $where[] = 'type = %s';
            $params[] = $args['type'];
        }

        if ($args['is_active'] !== null) {
            $where[] = 'is_active = %d';
            $params[] = (int) $args['is_active'];
        }

        $where_clause = implode(' AND ', $where);
        $params[] = $args['limit'];
        $params[] = $args['offset'];

        $alerts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name}
                WHERE {$where_clause}
                ORDER BY created_at DESC
                LIMIT %d OFFSET %d",
                ...$params
            ),
            ARRAY_A
        );

        return array_map([$this, 'parse_alert'], $alerts);
    }

    /**
     * Update alert
     *
     * @param int   $id      Alert ID
     * @param int   $user_id User ID (for verification)
     * @param array $data    Data to update
     * @return bool|WP_Error
     */
    public function update($id, $user_id, $data) {
        global $wpdb;

        // Verify ownership
        $alert = $this->get($id);
        if (!$alert || $alert['user_id'] != $user_id) {
            return new WP_Error('not_found', 'Alert not found.');
        }

        $update_data = [];
        $format = [];

        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
            $format[] = '%s';
        }

        if (isset($data['config'])) {
            $config = is_array($data['config']) ? wp_json_encode($data['config']) : $data['config'];
            $update_data['config'] = $config;
            $format[] = '%s';
        }

        if (isset($data['is_active'])) {
            $update_data['is_active'] = (int) $data['is_active'];
            $format[] = '%d';
        }

        if (isset($data['email_enabled'])) {
            $update_data['email_enabled'] = (int) $data['email_enabled'];
            $format[] = '%d';
        }

        if (isset($data['push_enabled'])) {
            $update_data['push_enabled'] = (int) $data['push_enabled'];
            $format[] = '%d';
        }

        if (empty($update_data)) {
            return true;
        }

        $result = $wpdb->update(
            $this->table_name,
            $update_data,
            ['id' => $id],
            $format,
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Delete alert
     *
     * @param int $id      Alert ID
     * @param int $user_id User ID (for verification)
     * @return bool|WP_Error
     */
    public function delete($id, $user_id) {
        global $wpdb;

        // Verify ownership
        $alert = $this->get($id);
        if (!$alert || $alert['user_id'] != $user_id) {
            return new WP_Error('not_found', 'Alert not found.');
        }

        // Delete related notifications
        $wpdb->delete($this->notifications_table, ['alert_id' => $id]);

        // Delete alert
        $result = $wpdb->delete($this->table_name, ['id' => $id]);

        return $result !== false;
    }

    /**
     * Toggle alert active status
     *
     * @param int $id      Alert ID
     * @param int $user_id User ID
     * @return bool|WP_Error New status
     */
    public function toggle_active($id, $user_id) {
        $alert = $this->get($id);
        if (!$alert || $alert['user_id'] != $user_id) {
            return new WP_Error('not_found', 'Alert not found.');
        }

        $new_status = $alert['is_active'] ? 0 : 1;
        $this->update($id, $user_id, ['is_active' => $new_status]);

        return (bool) $new_status;
    }

    // ============================================
    // NOTIFICATIONS
    // ============================================

    /**
     * Create a notification
     *
     * @param int   $user_id  User ID
     * @param array $data     Notification data
     * @param int   $alert_id Optional alert ID that triggered this
     * @return int|WP_Error Notification ID
     */
    public function create_notification($user_id, $data, $alert_id = null) {
        global $wpdb;

        $notification_data = isset($data['data']) ? $data['data'] : [];
        if (is_array($notification_data)) {
            $notification_data = wp_json_encode($notification_data);
        }

        $result = $wpdb->insert(
            $this->notifications_table,
            [
                'user_id'    => $user_id,
                'alert_id'   => $alert_id,
                'type'       => sanitize_text_field($data['type'] ?? 'general'),
                'title'      => sanitize_text_field($data['title'] ?? ''),
                'message'    => wp_kses_post($data['message'] ?? ''),
                'data'       => $notification_data,
                'action_url' => esc_url_raw($data['action_url'] ?? ''),
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%s']
        );

        if ($result === false) {
            return new WP_Error('db_error', 'Failed to create notification.');
        }

        // Update alert trigger count
        if ($alert_id) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$this->table_name}
                SET trigger_count = trigger_count + 1, last_triggered_at = NOW()
                WHERE id = %d",
                $alert_id
            ));
        }

        return $wpdb->insert_id;
    }

    /**
     * Get user's notifications
     *
     * @param int   $user_id User ID
     * @param array $args    Query arguments
     * @return array Notifications
     */
    public function get_notifications($user_id, $args = []) {
        global $wpdb;

        $defaults = [
            'is_read' => null,
            'type'    => null,
            'limit'   => 50,
            'offset'  => 0,
        ];

        $args = wp_parse_args($args, $defaults);

        $where = ['user_id = %d'];
        $params = [$user_id];

        if ($args['is_read'] !== null) {
            $where[] = 'is_read = %d';
            $params[] = (int) $args['is_read'];
        }

        if ($args['type']) {
            $where[] = 'type = %s';
            $params[] = $args['type'];
        }

        $where_clause = implode(' AND ', $where);
        $params[] = $args['limit'];
        $params[] = $args['offset'];

        $notifications = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->notifications_table}
                WHERE {$where_clause}
                ORDER BY created_at DESC
                LIMIT %d OFFSET %d",
                ...$params
            ),
            ARRAY_A
        );

        return array_map([$this, 'parse_notification'], $notifications);
    }

    /**
     * Get unread notification count
     *
     * @param int $user_id User ID
     * @return int Count
     */
    public function get_unread_count($user_id) {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->notifications_table}
            WHERE user_id = %d AND is_read = 0",
            $user_id
        ));
    }

    /**
     * Mark notification as read
     *
     * @param int $id      Notification ID
     * @param int $user_id User ID
     * @return bool
     */
    public function mark_read($id, $user_id) {
        global $wpdb;

        return $wpdb->update(
            $this->notifications_table,
            ['is_read' => 1, 'read_at' => current_time('mysql')],
            ['id' => $id, 'user_id' => $user_id],
            ['%d', '%s'],
            ['%d', '%d']
        ) !== false;
    }

    /**
     * Mark all notifications as read
     *
     * @param int $user_id User ID
     * @return bool
     */
    public function mark_all_read($user_id) {
        global $wpdb;

        return $wpdb->query($wpdb->prepare(
            "UPDATE {$this->notifications_table}
            SET is_read = 1, read_at = NOW()
            WHERE user_id = %d AND is_read = 0",
            $user_id
        )) !== false;
    }

    /**
     * Delete old notifications (cleanup)
     *
     * @param int $days_old Days to keep
     * @return int Number deleted
     */
    public function cleanup_old_notifications($days_old = 30) {
        global $wpdb;

        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->notifications_table}
            WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)
            AND is_read = 1",
            $days_old
        ));
    }

    // ============================================
    // ALERT TRIGGERS
    // ============================================

    /**
     * Check keyword alerts against new posts
     *
     * @param array $post Post data
     * @return array Triggered alerts
     */
    public function check_keyword_alerts($post) {
        global $wpdb;

        $triggered = [];

        // Get all active keyword alerts
        $alerts = $wpdb->get_results(
            "SELECT * FROM {$this->table_name}
            WHERE type = 'keyword' AND is_active = 1",
            ARRAY_A
        );

        foreach ($alerts as $alert) {
            $alert = $this->parse_alert($alert);
            $config = $alert['config'];

            if (empty($config['keywords'])) {
                continue;
            }

            $keywords = array_map('strtolower', (array) $config['keywords']);
            $content = strtolower($post['content'] ?? '');
            $title = strtolower($post['role_title'] ?? '');

            foreach ($keywords as $keyword) {
                if (strpos($content, $keyword) !== false || strpos($title, $keyword) !== false) {
                    // Create notification
                    $this->create_notification(
                        $alert['user_id'],
                        [
                            'type'       => self::TYPE_KEYWORD,
                            'title'      => "New post matches: {$keyword}",
                            'message'    => "A new post mentioning \"{$keyword}\" was found: {$post['role_title']}",
                            'data'       => ['post_id' => $post['id'], 'keyword' => $keyword],
                            'action_url' => home_url("/crm/?view=post&id={$post['id']}"),
                        ],
                        $alert['id']
                    );

                    $triggered[] = [
                        'alert_id' => $alert['id'],
                        'keyword'  => $keyword,
                        'post_id'  => $post['id'],
                    ];

                    break; // Only trigger once per alert per post
                }
            }
        }

        return $triggered;
    }

    /**
     * Check recruiter post alerts
     *
     * @param array $post        Post data
     * @param int   $recruiter_id Recruiter ID
     * @return array Triggered alerts
     */
    public function check_recruiter_alerts($post, $recruiter_id) {
        global $wpdb;

        $triggered = [];

        // Get users who have saved this recruiter and have alerts enabled
        $saved_table = $wpdb->prefix . 'sffc_crm_saved_recruiters';

        $alerts = $wpdb->get_results($wpdb->prepare(
            "SELECT a.* FROM {$this->table_name} a
            INNER JOIN {$saved_table} sr ON a.user_id = sr.user_id
            WHERE a.type = 'recruiter_post'
            AND a.is_active = 1
            AND sr.recruiter_id = %d",
            $recruiter_id
        ), ARRAY_A);

        foreach ($alerts as $alert) {
            $alert = $this->parse_alert($alert);

            // Check if this recruiter is in the alert's watched list
            $config = $alert['config'];
            $watch_all = $config['watch_all_saved'] ?? true;
            $specific_ids = $config['recruiter_ids'] ?? [];

            if (!$watch_all && !in_array($recruiter_id, $specific_ids)) {
                continue;
            }

            // Create notification
            $recruiter_name = $post['recruiter_name'] ?? 'A recruiter';
            $this->create_notification(
                $alert['user_id'],
                [
                    'type'       => self::TYPE_RECRUITER_POST,
                    'title'      => "New post from {$recruiter_name}",
                    'message'    => "{$recruiter_name} posted a new opportunity: {$post['role_title']}",
                    'data'       => ['post_id' => $post['id'], 'recruiter_id' => $recruiter_id],
                    'action_url' => home_url("/crm/?view=post&id={$post['id']}"),
                ],
                $alert['id']
            );

            $triggered[] = [
                'alert_id'     => $alert['id'],
                'recruiter_id' => $recruiter_id,
                'post_id'      => $post['id'],
            ];
        }

        return $triggered;
    }

    /**
     * Create follow-up reminder notifications
     *
     * @return int Number of notifications created
     */
    public function create_follow_up_reminders() {
        global $wpdb;

        $outreach_table = $wpdb->prefix . 'sffc_crm_outreach';
        $recruiters_table = $wpdb->prefix . 'sffc_crm_recruiters';

        // Find outreach without reply after 5 days
        $stale_outreach = $wpdb->get_results(
            "SELECT o.*, r.name as recruiter_name, r.firm
            FROM {$outreach_table} o
            INNER JOIN {$recruiters_table} r ON o.recruiter_id = r.id
            WHERE o.status = 'sent'
            AND o.sent_at < DATE_SUB(NOW(), INTERVAL 5 DAY)
            AND o.sent_at > DATE_SUB(NOW(), INTERVAL 6 DAY)",
            ARRAY_A
        );

        $count = 0;

        foreach ($stale_outreach as $outreach) {
            // Check if we already sent a reminder for this
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->notifications_table}
                WHERE user_id = %d
                AND type = 'follow_up'
                AND JSON_EXTRACT(data, '$.outreach_id') = %d",
                $outreach['user_id'],
                $outreach['id']
            ));

            if ($existing > 0) {
                continue;
            }

            $this->create_notification(
                $outreach['user_id'],
                [
                    'type'       => self::TYPE_FOLLOW_UP,
                    'title'      => "Follow up with {$outreach['recruiter_name']}",
                    'message'    => "No response in 5 days. Consider sending a follow-up message.",
                    'data'       => [
                        'outreach_id'  => $outreach['id'],
                        'recruiter_id' => $outreach['recruiter_id'],
                    ],
                    'action_url' => home_url("/crm/?view=recruiter&id={$outreach['recruiter_id']}"),
                ]
            );

            $count++;
        }

        return $count;
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    /**
     * Parse alert data
     *
     * @param array $alert Raw alert data
     * @return array Parsed alert
     */
    private function parse_alert($alert) {
        if (isset($alert['config']) && is_string($alert['config'])) {
            $alert['config'] = json_decode($alert['config'], true) ?: [];
        }

        $alert['is_active'] = (bool) ($alert['is_active'] ?? false);
        $alert['email_enabled'] = (bool) ($alert['email_enabled'] ?? false);
        $alert['push_enabled'] = (bool) ($alert['push_enabled'] ?? false);
        $alert['trigger_count'] = (int) ($alert['trigger_count'] ?? 0);

        return $alert;
    }

    /**
     * Parse notification data
     *
     * @param array $notification Raw notification
     * @return array Parsed notification
     */
    private function parse_notification($notification) {
        if (isset($notification['data']) && is_string($notification['data'])) {
            $notification['data'] = json_decode($notification['data'], true) ?: [];
        }

        $notification['is_read'] = (bool) ($notification['is_read'] ?? false);

        return $notification;
    }

    /**
     * Get alert type labels
     *
     * @return array Type => Label mapping
     */
    public static function get_type_labels() {
        return [
            self::TYPE_KEYWORD          => 'Keyword Alert',
            self::TYPE_RECRUITER_POST   => 'Recruiter Post Alert',
            self::TYPE_FOLLOW_UP        => 'Follow-up Reminder',
            self::TYPE_TASK_DUE         => 'Task Due',
            self::TYPE_REPLY_RECEIVED   => 'Reply Received',
            self::TYPE_SEQUENCE_COMPLETE => 'Sequence Complete',
        ];
    }
}
