<?php
/**
 * CRM Pipeline Model
 * Handles opportunity pipeline/stages
 *
 * @package SennaCareers
 * @since 7.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_Pipeline {

    private $table;
    private $history_table;
    private $recruiters_table;
    private $posts_table;
    private $activity_table;

    /**
     * Pipeline stages with metadata
     */
    const STAGES = [
        'not_applied' => [
            'label' => 'Not Applied',
            'order' => 1,
            'color' => '#cfd8de',
        ],
        'interested' => [
            'label' => 'Interested',
            'order' => 2,
            'color' => '#6b7280',
        ],
        'application_submitted' => [
            'label' => 'Application Submitted',
            'order' => 3,
            'color' => '#0a66c2',
        ],
        'messaged' => [
            'label' => 'Messaged',
            'order' => 4,
            'color' => '#2563eb',
        ],
        'follow_up' => [
            'label' => 'Follow Up',
            'order' => 5,
            'color' => '#7c3aed',
        ],
        'online_assessment' => [
            'label' => 'Online Assessment',
            'order' => 6,
            'color' => '#b45309',
        ],
        'case_study' => [
            'label' => 'Case Study',
            'order' => 7,
            'color' => '#92400e',
        ],
        'hirevue' => [
            'label' => 'HireVue',
            'order' => 8,
            'color' => '#ea580c',
        ],
        'telephone_interview' => [
            'label' => 'Telephone Interview',
            'order' => 9,
            'color' => '#16a34a',
        ],
        'video_interview' => [
            'label' => 'Video Interview',
            'order' => 10,
            'color' => '#0d9488',
        ],
        'face_to_face_interview' => [
            'label' => 'Face-to-Face Interview',
            'order' => 11,
            'color' => '#0891b2',
        ],
        'assessment_centre' => [
            'label' => 'Assessment Centre',
            'order' => 12,
            'color' => '#6366f1',
        ],
        'offer_received' => [
            'label' => 'Offer Received',
            'order' => 13,
            'color' => '#059669',
        ],
        'rejected' => [
            'label' => 'Rejected',
            'order' => 14,
            'color' => '#dc2626',
        ],
        'not_interested' => [
            'label' => 'Not Interested',
            'order' => 15,
            'color' => '#94a3b8',
        ],
    ];

    const FINAL_STAGES = ['offer_received', 'rejected', 'not_interested'];

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'sffc_crm_pipeline';
        $this->history_table = $wpdb->prefix . 'sffc_crm_pipeline_history';
        $this->recruiters_table = $wpdb->prefix . 'sffc_crm_recruiters';
        $this->posts_table = $wpdb->prefix . 'sffc_crm_posts';
        $this->activity_table = $wpdb->prefix . 'sffc_crm_activity';
    }

    /**
     * Get pipeline item by ID
     */
    public function get($id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $id
        ), ARRAY_A);
    }

    /**
     * Get pipeline item with full details
     */
    public function get_with_details($id, $user_id) {
        global $wpdb;

        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT pl.*,
                    r.name as recruiter_name, r.firm as recruiter_firm,
                    r.photo_url as recruiter_photo, r.linkedin_url as recruiter_linkedin,
                    p.role_title as post_role_title, p.content_snippet as post_snippet
             FROM {$this->table} pl
             LEFT JOIN {$this->recruiters_table} r ON r.id = pl.recruiter_id
             LEFT JOIN {$this->posts_table} p ON p.id = pl.post_id
             WHERE pl.id = %d AND pl.user_id = %d",
            $id,
            $user_id
        ), ARRAY_A);

        if (!$item) {
            return null;
        }

        // Get stage history
        $item['history'] = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->history_table}
             WHERE pipeline_id = %d
             ORDER BY transitioned_at DESC",
            $id
        ), ARRAY_A);

        return $item;
    }

    /**
     * Valid lead sources
     */
    const SOURCES = [
        'platform' => 'MENA Careers Platform',
        'linkedin' => 'LinkedIn',
        'indeed' => 'Indeed',
        'referral' => 'Referral',
        'company_website' => 'Company Website',
        'other' => 'Other',
    ];

    /**
     * Add opportunity to pipeline
     * Supports both platform leads (with recruiter_id) and manual leads (without)
     */
    public function add_to_pipeline($user_id, $data) {
        global $wpdb;

        // All stages are available
        $allowed_stages = array_keys(self::STAGES);

        $stage = $data['stage'] ?? 'not_applied';
        if (!in_array($stage, $allowed_stages)) {
            return new WP_Error('invalid_stage', 'Invalid pipeline stage');
        }

        // Determine if this is a manual lead or platform lead
        $is_manual_lead = empty($data['recruiter_id']);
        $source = $data['source'] ?? ($is_manual_lead ? 'other' : 'platform');

        // Validate source
        if (!array_key_exists($source, self::SOURCES)) {
            $source = $is_manual_lead ? 'other' : 'platform';
        }

        // For manual leads, require role_title and company
        if ($is_manual_lead) {
            if (empty($data['role_title']) || empty($data['company'])) {
                return new WP_Error('missing_required', 'Role title and company are required for manual leads');
            }
        }

        // Check for existing item (only for platform leads with recruiter_id)
        if (!$is_manual_lead) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->table}
                 WHERE user_id = %d AND recruiter_id = %d AND (post_id = %d OR (post_id IS NULL AND %d IS NULL))",
                $user_id,
                $data['recruiter_id'],
                $data['post_id'] ?? null,
                $data['post_id'] ?? null
            ));

            if ($existing) {
                return new WP_Error('already_exists', 'This opportunity is already in your pipeline');
            }
        }

        // Get additional data from post if available
        if (!empty($data['post_id'])) {
            $post = $wpdb->get_row($wpdb->prepare(
                "SELECT role_title, company, location, salary_min, salary_max FROM {$this->posts_table} WHERE id = %d",
                $data['post_id']
            ), ARRAY_A);

            if ($post) {
                $data = array_merge($data, [
                    'role_title' => $data['role_title'] ?? $post['role_title'],
                    'company' => $data['company'] ?? $post['company'],
                    'location' => $data['location'] ?? $post['location'],
                    'salary_min' => $data['salary_min'] ?? $post['salary_min'],
                    'salary_max' => $data['salary_max'] ?? $post['salary_max'],
                ]);
            }
        }

        $insert_data = [
            'user_id' => $user_id,
            'recruiter_id' => !empty($data['recruiter_id']) ? $data['recruiter_id'] : null,
            'post_id' => $data['post_id'] ?? null,
            'stage' => $stage,
            'role_title' => $data['role_title'] ?? '',
            'company' => $data['company'] ?? '',
            'location' => $data['location'] ?? '',
            'salary_min' => $data['salary_min'] ?? null,
            'salary_max' => $data['salary_max'] ?? null,
            'notes' => $data['notes'] ?? '',
            'source' => $source,
            'external_url' => $data['external_url'] ?? null,
            'contact_name' => $data['contact_name'] ?? null,
            'contact_email' => $data['contact_email'] ?? null,
            'contact_linkedin' => $data['contact_linkedin'] ?? null,
        ];

        $result = $wpdb->insert($this->table, $insert_data);

        if ($result) {
            $pipeline_id = $wpdb->insert_id;

            // Record initial stage in history
            $this->record_stage_change($pipeline_id, null, $stage);

            // Log activity
            $this->log_activity($user_id, $data['recruiter_id'] ?? null, $data['post_id'] ?? null, $pipeline_id, 'pipeline_added', [
                'stage' => $stage,
                'source' => $source,
                'is_manual' => $is_manual_lead,
            ]);

            return $pipeline_id;
        }

        return false;
    }

    /**
     * Get available sources
     */
    public static function get_sources() {
        return self::SOURCES;
    }

    /**
     * Update pipeline stage
     */
    public function update_stage($pipeline_id, $user_id, $new_stage, $notes = null) {
        global $wpdb;

        // Verify ownership
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d AND user_id = %d",
            $pipeline_id,
            $user_id
        ), ARRAY_A);

        if (!$item) {
            return new WP_Error('not_found', 'Pipeline item not found');
        }

        // All stages are available
        $allowed_stages = array_keys(self::STAGES);

        if (!in_array($new_stage, $allowed_stages)) {
            return new WP_Error('invalid_stage', 'Invalid pipeline stage');
        }

        $old_stage = $item['stage'];

        if ($old_stage === $new_stage) {
            return true; // No change needed
        }

        $update_data = [
            'stage' => $new_stage,
            'stage_entered_at' => current_time('mysql'),
        ];

        // Track closing timestamps
        if (in_array($new_stage, self::FINAL_STAGES, true)) {
            $update_data['closed_at'] = current_time('mysql');
        }

        if ($notes !== null) {
            $update_data['notes'] = $notes;
        }

        $result = $wpdb->update(
            $this->table,
            $update_data,
            ['id' => $pipeline_id]
        );

        if ($result !== false) {
            // Record stage change
            $this->record_stage_change($pipeline_id, $old_stage, $new_stage, $notes);

            // Log activity
            $this->log_activity($user_id, $item['recruiter_id'], $item['post_id'], $pipeline_id, 'stage_changed', [
                'from' => $old_stage,
                'to' => $new_stage,
            ]);

            return true;
        }

        return false;
    }

    /**
     * Update pipeline item outcome (for closed stage)
     */
    public function set_outcome($pipeline_id, $user_id, $outcome, $reason = '') {
        global $wpdb;

        $valid_outcomes = ['won', 'lost', 'withdrawn'];
        if (!in_array($outcome, $valid_outcomes)) {
            return new WP_Error('invalid_outcome', 'Invalid outcome');
        }

        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d AND user_id = %d",
            $pipeline_id,
            $user_id
        ), ARRAY_A);

        if (!$item) {
            return new WP_Error('not_found', 'Pipeline item not found');
        }

        $outcome_stage_map = [
            'won' => 'offer_received',
            'lost' => 'rejected',
            'withdrawn' => 'not_interested',
        ];
        $new_stage = $outcome_stage_map[$outcome] ?? 'rejected';

        $result = $wpdb->update(
            $this->table,
            [
                'stage' => $new_stage,
                'outcome' => $outcome,
                'outcome_reason' => $reason,
                'stage_entered_at' => current_time('mysql'),
                'closed_at' => current_time('mysql'),
            ],
            ['id' => $pipeline_id]
        );

        if ($result !== false) {
            $this->log_activity($user_id, $item['recruiter_id'], $item['post_id'], $pipeline_id, 'outcome_set', [
                'outcome' => $outcome,
                'reason' => $reason,
            ]);

            return true;
        }

        return false;
    }

    /**
     * Get user's pipeline items
     */
    public function get_user_pipeline($user_id, $stage = null) {
        global $wpdb;

        $where = "pl.user_id = %d";
        $params = [$user_id];

        if ($stage) {
            $where .= " AND pl.stage = %s";
            $params[] = $stage;
        }

        $order_case = 'CASE pl.stage ';
        $order_index = 1;
        foreach (self::STAGES as $stage_key => $stage_data) {
            $safe_stage = esc_sql($stage_key);
            $order_case .= "WHEN '{$safe_stage}' THEN {$order_index} ";
            $order_index++;
        }
        $order_case .= 'ELSE 999 END';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT pl.*,
                    r.name as recruiter_name, r.firm as recruiter_firm,
                    r.photo_url as recruiter_photo,
                    p.content_snippet as post_snippet
             FROM {$this->table} pl
             LEFT JOIN {$this->recruiters_table} r ON r.id = pl.recruiter_id
             LEFT JOIN {$this->posts_table} p ON p.id = pl.post_id
             WHERE {$where}
             ORDER BY {$order_case},
                pl.stage_entered_at DESC",
            $params
        ), ARRAY_A);
    }

    /**
     * Get pipeline grouped by stage (for Kanban view)
     */
    public function get_pipeline_kanban($user_id) {
        $all_items = $this->get_user_pipeline($user_id);

        $kanban = [];
        foreach (self::STAGES as $stage_key => $stage_data) {
            $kanban[$stage_key] = [
                'label' => $stage_data['label'],
                'color' => $stage_data['color'],
                'items' => [],
            ];
        }

        foreach ($all_items as $item) {
            if (isset($kanban[$item['stage']])) {
                $kanban[$item['stage']]['items'][] = $item;
            }
        }

        return $kanban;
    }

    /**
     * Get pipeline stats
     */
    public function get_user_stats($user_id) {
        global $wpdb;

        $stats = [
            'total' => 0,
            'by_stage' => [],
            'by_outcome' => [
                'won' => 0,
                'lost' => 0,
                'withdrawn' => 0,
            ],
        ];

        foreach (self::STAGES as $stage_key => $stage_data) {
            $stats['by_stage'][$stage_key] = 0;
        }

        // Count by stage
        $stage_counts = $wpdb->get_results($wpdb->prepare(
            "SELECT stage, COUNT(*) as count
             FROM {$this->table}
             WHERE user_id = %d
             GROUP BY stage",
            $user_id
        ), ARRAY_A);

        foreach ($stage_counts as $row) {
            if (!isset($stats['by_stage'][$row['stage']])) {
                $stats['by_stage'][$row['stage']] = 0;
            }
            $stats['by_stage'][$row['stage']] += (int) $row['count'];
            $stats['total'] += (int) $row['count'];
        }

        // Count by outcome (for decision stages)
        $closing_stages = self::FINAL_STAGES;
        $closing_placeholders = implode(',', array_fill(0, count($closing_stages), '%s'));
        $outcome_params = array_merge([$user_id], $closing_stages);

        $outcome_counts = $wpdb->get_results($wpdb->prepare(
            "SELECT outcome, COUNT(*) as count
             FROM {$this->table}
             WHERE user_id = %d AND stage IN ($closing_placeholders) AND outcome IS NOT NULL
             GROUP BY outcome",
            $outcome_params
        ), ARRAY_A);

        foreach ($outcome_counts as $row) {
            $stats['by_outcome'][$row['outcome']] = (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Get conversion funnel stats
     */
    public function get_funnel_stats($user_id) {
        $stats = $this->get_user_stats($user_id);

        $funnel = [];
        $cumulative = 0;

        foreach (self::STAGES as $stage_key => $stage_data) {
            $count = $stats['by_stage'][$stage_key] ?? 0;
            $cumulative += $count;

            $funnel[$stage_key] = [
                'label' => $stage_data['label'],
                'count' => $count,
                'cumulative' => $cumulative,
            ];
        }

        // Calculate conversion rates
        $prev_count = null;
        foreach ($funnel as $stage_key => &$stage) {
            if ($prev_count !== null && $prev_count > 0) {
                $stage['conversion_rate'] = round(($stage['cumulative'] / $prev_count) * 100, 1);
            } else {
                $stage['conversion_rate'] = 100;
            }
            $prev_count = $stage['cumulative'];
        }

        return $funnel;
    }

    /**
     * Record stage change in history
     */
    private function record_stage_change($pipeline_id, $from_stage, $to_stage, $notes = null) {
        global $wpdb;

        $wpdb->insert($this->history_table, [
            'pipeline_id' => $pipeline_id,
            'from_stage' => $from_stage,
            'to_stage' => $to_stage,
            'notes' => $notes,
        ]);
    }

    /**
     * Log activity
     */
    private function log_activity($user_id, $recruiter_id, $post_id, $pipeline_id, $type, $data = []) {
        global $wpdb;

        $wpdb->insert($this->activity_table, [
            'user_id' => $user_id,
            'recruiter_id' => $recruiter_id,
            'post_id' => $post_id,
            'pipeline_id' => $pipeline_id,
            'activity_type' => $type,
            'activity_data' => json_encode($data),
        ]);
    }

    /**
     * Update next action
     */
    public function set_next_action($pipeline_id, $user_id, $action, $date = null) {
        global $wpdb;

        return $wpdb->update(
            $this->table,
            [
                'next_action' => $action,
                'next_action_date' => $date,
            ],
            [
                'id' => $pipeline_id,
                'user_id' => $user_id,
            ]
        );
    }

    /**
     * Get items with upcoming actions
     */
    public function get_upcoming_actions($user_id, $days = 7) {
        global $wpdb;

        $closed_stage_sql = "'" . implode("','", array_map('esc_sql', self::FINAL_STAGES)) . "'";

        return $wpdb->get_results($wpdb->prepare(
            "SELECT pl.*,
                    r.name as recruiter_name, r.firm as recruiter_firm,
                    r.photo_url as recruiter_photo
             FROM {$this->table} pl
             LEFT JOIN {$this->recruiters_table} r ON r.id = pl.recruiter_id
             WHERE pl.user_id = %d
               AND pl.next_action_date IS NOT NULL
               AND pl.next_action_date <= DATE_ADD(NOW(), INTERVAL %d DAY)
               AND pl.stage NOT IN ({$closed_stage_sql})
             ORDER BY pl.next_action_date ASC",
            $user_id,
            $days
        ), ARRAY_A);
    }

    /**
     * Get stage configuration
     */
    public static function get_stages() {
        return self::STAGES;
    }

    /**
     * Delete pipeline item
     */
    public function delete($pipeline_id, $user_id) {
        global $wpdb;

        // Verify ownership first
        $item = $this->get($pipeline_id);
        if (!$item || $item['user_id'] != $user_id) {
            return false;
        }

        // Delete history
        $wpdb->delete($this->history_table, ['pipeline_id' => $pipeline_id]);

        // Delete item
        return $wpdb->delete($this->table, ['id' => $pipeline_id]);
    }

    /**
     * Remove from pipeline by recruiter and post
     */
    public function remove_from_pipeline($user_id, $recruiter_id, $post_id = null) {
        global $wpdb;

        // Find the pipeline item
        $where = ['user_id' => $user_id, 'recruiter_id' => $recruiter_id];
        if ($post_id) {
            $where['post_id'] = $post_id;
        }

        // Get pipeline ID first for history cleanup
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$this->table} WHERE user_id = %d AND recruiter_id = %d" . ($post_id ? " AND post_id = %d" : ""),
            ...array_values($where)
        ), ARRAY_A);

        if ($item) {
            // Delete history
            $wpdb->delete($this->history_table, ['pipeline_id' => $item['id']]);
        }

        // Delete the item
        return $wpdb->delete($this->table, $where);
    }

    /**
     * Find pipeline item by user, recruiter and post
     */
    public function find_by_recruiter_and_post($user_id, $recruiter_id, $post_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table}
             WHERE user_id = %d AND recruiter_id = %d AND post_id = %d
             LIMIT 1",
            $user_id,
            $recruiter_id,
            $post_id
        ), ARRAY_A);
    }

    /**
     * Create pipeline item
     */
    public function create($data) {
        global $wpdb;

        $defaults = [
            'stage' => 'not_applied',
            'source' => 'platform',
            'notes' => '',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];

        $data = array_merge($defaults, $data);

        $wpdb->insert($this->table, $data);

        return $wpdb->insert_id;
    }
}
