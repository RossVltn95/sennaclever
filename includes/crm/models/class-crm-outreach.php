<?php
/**
 * CRM Outreach Model
 * Handles outreach messages and tracking
 *
 * @package SennaCareers
 * @since 7.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_Outreach {

    private $table;
    private $usage_table;
    private $activity_table;
    private $saved_recruiters_table;
    private $pipeline_table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'sffc_crm_outreach';
        $this->usage_table = $wpdb->prefix . 'sffc_crm_usage';
        $this->activity_table = $wpdb->prefix . 'sffc_crm_activity';
        $this->saved_recruiters_table = $wpdb->prefix . 'sffc_crm_saved_recruiters';
        $this->pipeline_table = $wpdb->prefix . 'sffc_crm_pipeline';
    }

    /**
     * Get outreach by ID
     */
    public function get($id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $id
        ), ARRAY_A);
    }

    /**
     * Get outreach by tracking identifier
     */
    public function get_by_tracking($tracking_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE tracking_id = %s",
            $tracking_id
        ), ARRAY_A);
    }

    /**
     * Get outreach with recruiter details
     */
    public function get_with_details($id, $user_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT o.*, r.name as recruiter_name, r.firm as recruiter_firm,
                    r.photo_url as recruiter_photo, r.email as recruiter_email,
                    p.role_title, p.company
             FROM {$this->table} o
             LEFT JOIN {$wpdb->prefix}sffc_crm_recruiters r ON r.id = o.recruiter_id
             LEFT JOIN {$wpdb->prefix}sffc_crm_posts p ON p.id = o.post_id
             WHERE o.id = %d AND o.user_id = %d",
            $id,
            $user_id
        ), ARRAY_A);
    }

    /**
     * Create outreach record
     */
    public function create($user_id, $data) {
        global $wpdb;

        // Check usage limits
        if (!$this->check_usage_limit($user_id)) {
            return new WP_Error('limit_reached', 'Monthly outreach limit reached');
        }

        // Generate tracking ID for email tracking
        $tracking_id = wp_generate_uuid4();

        $result = $wpdb->insert($this->table, [
            'user_id' => $user_id,
            'recruiter_id' => intval($data['recruiter_id']),
            'post_id' => isset($data['post_id']) ? intval($data['post_id']) : null,
            'pipeline_id' => isset($data['pipeline_id']) ? intval($data['pipeline_id']) : null,
            'sequence_id' => isset($data['sequence_id']) ? intval($data['sequence_id']) : null,
            'sequence_step' => isset($data['sequence_step']) ? intval($data['sequence_step']) : null,
            'channel' => in_array($data['channel'], ['email', 'linkedin']) ? $data['channel'] : 'email',
            'message_type' => in_array($data['message_type'], ['initial', 'followup', 'reply', 'connection_request']) ? $data['message_type'] : 'initial',
            'subject' => isset($data['subject']) ? sanitize_text_field($data['subject']) : '',
            'content' => wp_kses_post($data['content']),
            'content_html' => isset($data['content_html']) ? wp_kses_post($data['content_html']) : null,
            'template_id' => isset($data['template_id']) ? intval($data['template_id']) : null,
            'status' => 'draft',
            'tracking_id' => $tracking_id,
        ]);

        if ($result) {
            $outreach_id = $wpdb->insert_id;

            // Log activity
            $this->log_activity($user_id, $data['recruiter_id'], isset($data['post_id']) ? $data['post_id'] : null, 'outreach_created', [
                'outreach_id' => $outreach_id,
                'channel' => $data['channel'],
            ]);

            return $outreach_id;
        }

        return false;
    }

    /**
     * Send outreach (mark as sent)
     */
    public function send($outreach_id, $user_id) {
        global $wpdb;

        $outreach = $this->get($outreach_id);
        if (!$outreach || $outreach['user_id'] != $user_id) {
            return new WP_Error('not_found', 'Outreach not found');
        }

        if ($outreach['status'] !== 'draft' && $outreach['status'] !== 'scheduled') {
            return new WP_Error('already_sent', 'Outreach already sent');
        }

        // Check usage limits
        if (!$this->check_usage_limit($user_id)) {
            return new WP_Error('limit_reached', 'Monthly outreach limit reached');
        }

        // Update status
        $result = $wpdb->update(
            $this->table,
            [
                'status' => 'sent',
                'sent_at' => current_time('mysql'),
            ],
            ['id' => $outreach_id]
        );

        if ($result !== false) {
            // Increment usage
            $this->increment_usage($user_id);

            // Update recruiter status
            $this->update_recruiter_status($user_id, $outreach['recruiter_id']);

            // Update pipeline stage if applicable
            if ($outreach['pipeline_id']) {
                $this->update_pipeline_stage($outreach['pipeline_id'], $user_id);
            }

            // Update template usage
            if ($outreach['template_id']) {
                $template_model = new SFFC_CRM_Template();
                $template_model->increment_usage($outreach['template_id']);
            }

            // Log activity
            $this->log_activity($user_id, $outreach['recruiter_id'], $outreach['post_id'], 'outreach_sent', [
                'outreach_id' => $outreach_id,
                'channel' => $outreach['channel'],
            ]);

            return true;
        }

        return false;
    }

    /**
     * Mark outreach as copied (for LinkedIn)
     */
    public function mark_copied($outreach_id, $user_id) {
        global $wpdb;

        $outreach = $this->get($outreach_id);
        if (!$outreach || $outreach['user_id'] != $user_id) {
            return false;
        }

        // For LinkedIn, marking as copied is essentially marking as sent
        if ($outreach['channel'] === 'linkedin') {
            return $this->send($outreach_id, $user_id);
        }

        return false;
    }

    /**
     * Record email open
     */
    public function record_open($tracking_id) {
        global $wpdb;

        $outreach = $this->get_by_tracking($tracking_id);

        if (!$outreach) {
            return false;
        }

        $update_data = ['open_count' => $outreach['open_count'] + 1];

        // Only update opened_at on first open
        if (!$outreach['opened_at']) {
            $update_data['opened_at'] = current_time('mysql');
            $update_data['status'] = 'opened';
        }

        $wpdb->update($this->table, $update_data, ['id' => $outreach['id']]);

        // Log activity on first open
        if (!$outreach['opened_at']) {
            $this->log_activity($outreach['user_id'], $outreach['recruiter_id'], $outreach['post_id'], 'email_opened', [
                'outreach_id' => $outreach['id'],
            ]);
        }

        return true;
    }

    /**
     * Record email click
     */
    public function record_click($tracking_id) {
        global $wpdb;

        $outreach = $this->get_by_tracking($tracking_id);

        if (!$outreach) {
            return false;
        }

        $update_data = ['click_count' => $outreach['click_count'] + 1];

        // Only update clicked_at on first click
        if (!$outreach['clicked_at']) {
            $update_data['clicked_at'] = current_time('mysql');
            $update_data['status'] = 'clicked';
        }

        $wpdb->update($this->table, $update_data, ['id' => $outreach['id']]);

        // Log activity on first click
        if (!$outreach['clicked_at']) {
            $this->log_activity($outreach['user_id'], $outreach['recruiter_id'], $outreach['post_id'], 'email_clicked', [
                'outreach_id' => $outreach['id'],
            ]);
        }

        return true;
    }

    /**
     * Record tracked CV view
     */
    public function record_cv_view($tracking_id) {
        $outreach = $this->get_by_tracking($tracking_id);
        if (!$outreach) {
            return false;
        }

        $this->record_click($tracking_id);

        $this->log_activity($outreach['user_id'], $outreach['recruiter_id'], $outreach['post_id'], 'cv_viewed', [
            'outreach_id' => $outreach['id'],
        ]);

        return $outreach;
    }

    /**
     * Mark as replied
     */
    public function mark_replied($outreach_id, $user_id) {
        global $wpdb;

        $outreach = $this->get($outreach_id);
        if (!$outreach || $outreach['user_id'] != $user_id) {
            return false;
        }

        $wpdb->update(
            $this->table,
            [
                'status' => 'replied',
                'replied_at' => current_time('mysql'),
            ],
            ['id' => $outreach_id]
        );

        // Update recruiter status
        $wpdb->update(
            $this->saved_recruiters_table,
            [
                'status' => 'replied',
                'last_reply_at' => current_time('mysql'),
            ],
            ['user_id' => $user_id, 'recruiter_id' => $outreach['recruiter_id']]
        );

        // Update pipeline stage
        if ($outreach['pipeline_id']) {
            $wpdb->update(
                $this->pipeline_table,
                ['stage' => 'follow_up'],
                ['id' => $outreach['pipeline_id'], 'user_id' => $user_id]
            );
        }

        // Update template reply rate
        if ($outreach['template_id']) {
            $template_model = new SFFC_CRM_Template();
            $template_model->update_reply_rate($outreach['template_id']);
        }

        // Log activity
        $this->log_activity($user_id, $outreach['recruiter_id'], $outreach['post_id'], 'outreach_replied', [
            'outreach_id' => $outreach_id,
        ]);

        return true;
    }

    /**
     * Get user's outreach history
     */
    public function get_user_outreach($user_id, $args = []) {
        global $wpdb;

        $defaults = [
            'page' => 1,
            'per_page' => 20,
            'recruiter_id' => null,
            'status' => null,
            'channel' => null,
            'date_from' => null,
            'date_to' => null,
        ];

        $args = wp_parse_args($args, $defaults);

        $where = ["o.user_id = %d"];
        $params = [$user_id];

        if ($args['recruiter_id']) {
            $where[] = "o.recruiter_id = %d";
            $params[] = $args['recruiter_id'];
        }

        if ($args['status']) {
            $where[] = "o.status = %s";
            $params[] = $args['status'];
        }

        if ($args['channel']) {
            $where[] = "o.channel = %s";
            $params[] = $args['channel'];
        }

        if ($args['date_from']) {
            $where[] = "o.sent_at >= %s";
            $params[] = $args['date_from'];
        }

        if ($args['date_to']) {
            $where[] = "o.sent_at <= %s";
            $params[] = $args['date_to'];
        }

        $where_clause = implode(' AND ', $where);
        $offset = ($args['page'] - 1) * $args['per_page'];

        $sql = "SELECT o.*, r.name as recruiter_name, r.firm as recruiter_firm,
                       r.photo_url as recruiter_photo, p.role_title, p.company
                FROM {$this->table} o
                LEFT JOIN {$wpdb->prefix}sffc_crm_recruiters r ON r.id = o.recruiter_id
                LEFT JOIN {$wpdb->prefix}sffc_crm_posts p ON p.id = o.post_id
                WHERE $where_clause
                ORDER BY o.created_at DESC
                LIMIT %d OFFSET %d";

        $params[] = $args['per_page'];
        $params[] = $offset;

        return $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
    }

    /**
     * Get outreach count for user
     */
    public function get_user_outreach_count($user_id, $args = []) {
        global $wpdb;

        $where = ["user_id = %d"];
        $params = [$user_id];

        if (isset($args['status'])) {
            $where[] = "status = %s";
            $params[] = $args['status'];
        }

        if (isset($args['channel'])) {
            $where[] = "channel = %s";
            $params[] = $args['channel'];
        }

        $where_clause = implode(' AND ', $where);

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE $where_clause",
            ...$params
        ));
    }

    /**
     * Get outreach for a specific recruiter
     */
    public function get_recruiter_outreach($user_id, $recruiter_id, $limit = 10) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table}
             WHERE user_id = %d AND recruiter_id = %d
             ORDER BY created_at DESC
             LIMIT %d",
            $user_id,
            $recruiter_id,
            $limit
        ), ARRAY_A);
    }

    /**
     * Get user's outreach stats
     */
    public function get_user_stats($user_id, $period = 'month') {
        global $wpdb;

        $date_condition = "";
        if ($period === 'week') {
            $date_condition = "AND sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        } elseif ($period === 'month') {
            $date_condition = "AND sent_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        }

        return $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as total_sent,
                COUNT(CASE WHEN channel = 'email' THEN 1 END) as email_sent,
                COUNT(CASE WHEN channel = 'linkedin' THEN 1 END) as linkedin_sent,
                COUNT(CASE WHEN status = 'opened' OR status = 'clicked' OR status = 'replied' THEN 1 END) as opened,
                COUNT(CASE WHEN status = 'clicked' OR status = 'replied' THEN 1 END) as clicked,
                COUNT(CASE WHEN status = 'replied' THEN 1 END) as replied,
                ROUND(COUNT(CASE WHEN status = 'replied' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0), 1) as reply_rate
             FROM {$this->table}
             WHERE user_id = %d AND status NOT IN ('draft', 'scheduled') $date_condition",
            $user_id
        ), ARRAY_A);
    }

    /**
     * Check if user is within usage limits
     */
    public function check_usage_limit($user_id) {
        $limit = apply_filters('sffc_crm_outreach_limit', 5, $user_id);

        // -1 means unlimited
        if ($limit === -1) {
            return true;
        }

        $current_usage = $this->get_current_month_usage($user_id);

        return $current_usage < $limit;
    }

    /**
     * Get current month usage
     */
    public function get_current_month_usage($user_id) {
        global $wpdb;

        $period = date('Y-m');

        $usage = $wpdb->get_var($wpdb->prepare(
            "SELECT outreach_count FROM {$this->usage_table}
             WHERE user_id = %d AND period_month = %s",
            $user_id,
            $period
        ));

        return (int) $usage;
    }

    /**
     * Get remaining outreach for user
     */
    public function get_remaining($user_id) {
        $limit = apply_filters('sffc_crm_outreach_limit', 5, $user_id);

        if ($limit === -1) {
            return -1; // Unlimited
        }

        $used = $this->get_current_month_usage($user_id);

        return max(0, $limit - $used);
    }

    /**
     * Increment monthly usage
     */
    private function increment_usage($user_id) {
        global $wpdb;

        $period = date('Y-m');

        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$this->usage_table} (user_id, period_month, outreach_count)
             VALUES (%d, %s, 1)
             ON DUPLICATE KEY UPDATE outreach_count = outreach_count + 1",
            $user_id,
            $period
        ));
    }

    /**
     * Update recruiter status after sending outreach
     */
    private function update_recruiter_status($user_id, $recruiter_id) {
        global $wpdb;

        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$this->saved_recruiters_table} (user_id, recruiter_id, status, last_contacted_at)
             VALUES (%d, %d, 'contacted', NOW())
             ON DUPLICATE KEY UPDATE status = 'contacted', last_contacted_at = NOW()",
            $user_id,
            $recruiter_id
        ));
    }

    /**
     * Update pipeline stage after sending outreach
     */
    private function update_pipeline_stage($pipeline_id, $user_id) {
        global $wpdb;

        // Only update if currently at 'interested' stage
        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->pipeline_table}
             SET stage = 'messaged', stage_entered_at = NOW()
             WHERE id = %d AND user_id = %d AND stage IN ('not_applied','interested')",
            $pipeline_id,
            $user_id
        ));
    }

    /**
     * Log activity
     */
    private function log_activity($user_id, $recruiter_id, $post_id, $type, $data = []) {
        global $wpdb;

        $wpdb->insert($this->activity_table, [
            'user_id' => $user_id,
            'recruiter_id' => $recruiter_id,
            'post_id' => $post_id,
            'activity_type' => $type,
            'activity_data' => json_encode($data),
        ]);
    }

    /**
     * Delete outreach (draft only)
     */
    public function delete($outreach_id, $user_id) {
        global $wpdb;

        return $wpdb->delete($this->table, [
            'id' => $outreach_id,
            'user_id' => $user_id,
            'status' => 'draft',
        ]) !== false;
    }

    /**
     * Schedule outreach for later
     */
    public function schedule($outreach_id, $user_id, $scheduled_at) {
        global $wpdb;

        $outreach = $this->get($outreach_id);
        if (!$outreach || $outreach['user_id'] != $user_id || $outreach['status'] !== 'draft') {
            return false;
        }

        return $wpdb->update(
            $this->table,
            [
                'status' => 'scheduled',
                'scheduled_at' => $scheduled_at,
            ],
            ['id' => $outreach_id]
        ) !== false;
    }

    /**
     * Get scheduled outreach ready to send
     */
    public function get_ready_to_send() {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT * FROM {$this->table}
             WHERE status = 'scheduled' AND scheduled_at <= NOW()
             ORDER BY scheduled_at ASC
             LIMIT 50",
            ARRAY_A
        );
    }
}
