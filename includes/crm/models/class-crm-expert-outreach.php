<?php
/**
 * CRM Expert Outreach Model
 * Handles expert reach out requests and auto outreach settings
 *
 * @package SennaCareers
 * @since 7.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_Expert_Outreach {

    private static $instance = null;
    private $table_outreach;
    private $table_settings;
    private $table_recruiters;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table_outreach = $wpdb->prefix . 'sffc_crm_expert_outreach';
        $this->table_settings = $wpdb->prefix . 'sffc_crm_auto_outreach_settings';
        $this->table_recruiters = $wpdb->prefix . 'sffc_crm_recruiters';
    }

    /**
     * Create a new expert outreach request
     *
     * @param int $user_id
     * @param array $data
     * @return int|false Request ID or false on failure
     */
    public function create_request($user_id, $data) {
        global $wpdb;

        $insert_data = [
            'user_id' => $user_id,
            'recruiter_id' => isset($data['recruiter_id']) ? absint($data['recruiter_id']) : null,
            'contact_id' => isset($data['contact_id']) ? absint($data['contact_id']) : null,
            'request_type' => isset($data['request_type']) ? $data['request_type'] : 'manual',
            'status' => 'pending',
            'message_tone' => isset($data['message_tone']) ? $data['message_tone'] : 'professional',
            'custom_notes' => isset($data['custom_notes']) ? sanitize_textarea_field($data['custom_notes']) : '',
            'include_cv' => isset($data['include_cv']) ? (int) $data['include_cv'] : 1,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];

        $result = $wpdb->insert($this->table_outreach, $insert_data);

        if ($result) {
            $request_id = $wpdb->insert_id;

            // Log activity
            $this->log_activity($user_id, 'expert_outreach_requested', [
                'request_id' => $request_id,
                'recruiter_id' => $insert_data['recruiter_id'],
                'request_type' => $insert_data['request_type']
            ]);

            return $request_id;
        }

        return false;
    }

    /**
     * Get user's outreach requests
     *
     * @param int $user_id
     * @param array $args
     * @return array
     */
    public function get_user_requests($user_id, $args = []) {
        global $wpdb;

        $defaults = [
            'status' => '',
            'request_type' => '',
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        ];

        $args = wp_parse_args($args, $defaults);

        $where = ['eo.user_id = %d'];
        $params = [$user_id];

        if (!empty($args['status'])) {
            $where[] = 'eo.status = %s';
            $params[] = $args['status'];
        }

        if (!empty($args['request_type'])) {
            $where[] = 'eo.request_type = %s';
            $params[] = $args['request_type'];
        }

        $where_clause = implode(' AND ', $where);
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']) ?: 'created_at DESC';

        $sql = $wpdb->prepare(
            "SELECT eo.*,
                    r.name as recruiter_name,
                    r.title as recruiter_title,
                    r.firm as recruiter_firm,
                    r.photo_url as recruiter_photo
             FROM {$this->table_outreach} eo
             LEFT JOIN {$this->table_recruiters} r ON eo.recruiter_id = r.id
             WHERE $where_clause
             ORDER BY $orderby
             LIMIT %d OFFSET %d",
            array_merge($params, [$args['limit'], $args['offset']])
        );

        return $wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Get a single request
     *
     * @param int $id
     * @param int $user_id Optional - verify ownership
     * @return array|null
     */
    public function get_request($id, $user_id = null) {
        global $wpdb;

        $sql = "SELECT eo.*,
                       r.name as recruiter_name,
                       r.title as recruiter_title,
                       r.firm as recruiter_firm,
                       r.photo_url as recruiter_photo,
                       r.email as recruiter_email
                FROM {$this->table_outreach} eo
                LEFT JOIN {$this->table_recruiters} r ON eo.recruiter_id = r.id
                WHERE eo.id = %d";

        $params = [$id];

        if ($user_id) {
            $sql .= " AND eo.user_id = %d";
            $params[] = $user_id;
        }

        return $wpdb->get_row($wpdb->prepare($sql, $params), ARRAY_A);
    }

    /**
     * Update request status
     *
     * @param int $id
     * @param string $status
     * @param string $admin_notes Optional
     * @return bool
     */
    public function update_status($id, $status, $admin_notes = '') {
        global $wpdb;

        $update_data = [
            'status' => $status,
            'updated_at' => current_time('mysql')
        ];

        if ($status === 'sent') {
            $update_data['sent_at'] = current_time('mysql');
        } elseif ($status === 'replied') {
            $update_data['replied_at'] = current_time('mysql');
        }

        if (!empty($admin_notes)) {
            $update_data['admin_notes'] = sanitize_textarea_field($admin_notes);
        }

        return $wpdb->update(
            $this->table_outreach,
            $update_data,
            ['id' => $id]
        ) !== false;
    }

    /**
     * Cancel a request
     *
     * @param int $id
     * @param int $user_id
     * @return bool
     */
    public function cancel_request($id, $user_id) {
        global $wpdb;

        return $wpdb->update(
            $this->table_outreach,
            [
                'status' => 'cancelled',
                'updated_at' => current_time('mysql')
            ],
            [
                'id' => $id,
                'user_id' => $user_id,
                'status' => 'pending'
            ]
        ) !== false;
    }

    /**
     * Get outreach statistics for a user
     *
     * @param int $user_id
     * @return array
     */
    public function get_stats($user_id) {
        global $wpdb;

        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'replied' THEN 1 ELSE 0 END) as replied,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN request_type = 'auto' THEN 1 ELSE 0 END) as auto_requests,
                SUM(CASE WHEN request_type = 'manual' THEN 1 ELSE 0 END) as manual_requests
             FROM {$this->table_outreach}
             WHERE user_id = %d",
            $user_id
        ), ARRAY_A);

        // Calculate reply rate
        $sent_count = (int) $stats['sent'] + (int) $stats['replied'];
        $stats['reply_rate'] = $sent_count > 0
            ? round(((int) $stats['replied'] / $sent_count) * 100, 1)
            : 0;

        return $stats;
    }

    /**
     * Get auto outreach settings for a user
     *
     * @param int $user_id
     * @return array
     */
    public function get_auto_settings($user_id) {
        global $wpdb;

        $settings = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_settings} WHERE user_id = %d",
            $user_id
        ), ARRAY_A);

        if (!$settings) {
            // Return defaults
            return [
                'user_id' => $user_id,
                'is_enabled' => 0,
                'target_sectors' => [],
                'target_seniority' => [],
                'target_locations' => [],
                'target_firm_types' => [],
                'weekly_limit' => 10,
                'message_tone' => 'professional',
                'include_cv' => 1,
                'custom_intro' => ''
            ];
        }

        // Decode JSON fields
        $settings['target_sectors'] = json_decode($settings['target_sectors'] ?: '[]', true);
        $settings['target_seniority'] = json_decode($settings['target_seniority'] ?: '[]', true);
        $settings['target_locations'] = json_decode($settings['target_locations'] ?: '[]', true);
        $settings['target_firm_types'] = json_decode($settings['target_firm_types'] ?: '[]', true);

        return $settings;
    }

    /**
     * Update auto outreach settings
     *
     * @param int $user_id
     * @param array $settings
     * @return bool
     */
    public function update_auto_settings($user_id, $settings) {
        global $wpdb;

        $data = [
            'user_id' => $user_id,
            'is_enabled' => isset($settings['is_enabled']) ? (int) $settings['is_enabled'] : 0,
            'target_sectors' => json_encode(isset($settings['target_sectors']) ? (array) $settings['target_sectors'] : []),
            'target_seniority' => json_encode(isset($settings['target_seniority']) ? (array) $settings['target_seniority'] : []),
            'target_locations' => json_encode(isset($settings['target_locations']) ? (array) $settings['target_locations'] : []),
            'target_firm_types' => json_encode(isset($settings['target_firm_types']) ? (array) $settings['target_firm_types'] : []),
            'weekly_limit' => isset($settings['weekly_limit']) ? absint($settings['weekly_limit']) : 10,
            'message_tone' => isset($settings['message_tone']) ? $settings['message_tone'] : 'professional',
            'include_cv' => isset($settings['include_cv']) ? (int) $settings['include_cv'] : 1,
            'custom_intro' => isset($settings['custom_intro']) ? sanitize_textarea_field($settings['custom_intro']) : '',
            'updated_at' => current_time('mysql')
        ];

        // Check if settings exist
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_settings} WHERE user_id = %d",
            $user_id
        ));

        if ($existing) {
            return $wpdb->update(
                $this->table_settings,
                $data,
                ['user_id' => $user_id]
            ) !== false;
        } else {
            $data['created_at'] = current_time('mysql');
            return $wpdb->insert($this->table_settings, $data) !== false;
        }
    }

    /**
     * Check if user has pending request for a recruiter
     *
     * @param int $user_id
     * @param int $recruiter_id
     * @return bool
     */
    public function has_pending_request($user_id, $recruiter_id) {
        global $wpdb;

        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_outreach}
             WHERE user_id = %d AND recruiter_id = %d AND status IN ('pending', 'in_progress')
             LIMIT 1",
            $user_id,
            $recruiter_id
        ));
    }

    /**
     * Get weekly auto outreach count
     *
     * @param int $user_id
     * @return int
     */
    public function get_weekly_auto_count($user_id) {
        global $wpdb;

        $week_start = date('Y-m-d 00:00:00', strtotime('monday this week'));

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_outreach}
             WHERE user_id = %d AND request_type = 'auto' AND created_at >= %s",
            $user_id,
            $week_start
        ));
    }

    /**
     * Log activity
     *
     * @param int $user_id
     * @param string $type
     * @param array $data
     */
    private function log_activity($user_id, $type, $data) {
        global $wpdb;

        $table_activity = $wpdb->prefix . 'sffc_crm_activity';

        $wpdb->insert($table_activity, [
            'user_id' => $user_id,
            'recruiter_id' => isset($data['recruiter_id']) ? $data['recruiter_id'] : null,
            'activity_type' => $type,
            'activity_data' => json_encode($data),
            'created_at' => current_time('mysql')
        ]);
    }

    /**
     * Get available sectors for targeting
     *
     * @return array
     */
    public function get_available_sectors() {
        return [
            'private_equity' => 'Private Equity',
            'venture_capital' => 'Venture Capital',
            'hedge_funds' => 'Hedge Funds',
            'investment_banking' => 'Investment Banking',
            'asset_management' => 'Asset Management',
            'corporate_finance' => 'Corporate Finance',
            'consulting' => 'Consulting',
            'real_estate' => 'Real Estate',
            'infrastructure' => 'Infrastructure',
            'credit' => 'Credit / Debt',
            'other' => 'Other'
        ];
    }

    /**
     * Get available seniority levels for targeting
     *
     * @return array
     */
    public function get_available_seniority() {
        return [
            'analyst' => 'Analyst',
            'associate' => 'Associate',
            'vp' => 'VP / Senior Associate',
            'director' => 'Director / Principal',
            'md' => 'Managing Director',
            'partner' => 'Partner',
            'c_level' => 'C-Level'
        ];
    }

    /**
     * Get available firm types for targeting
     *
     * @return array
     */
    public function get_available_firm_types() {
        return [
            'search_firm' => 'Executive Search Firm',
            'corporate' => 'Corporate / In-House',
            'agency' => 'Recruitment Agency',
            'independent' => 'Independent Recruiter'
        ];
    }
}
