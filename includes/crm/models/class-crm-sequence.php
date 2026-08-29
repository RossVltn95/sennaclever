<?php
/**
 * CRM Sequence Model
 * Handles sequence management for automated outreach
 *
 * @package SennaCareers
 * @since 7.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_Sequence {

    private $table;
    private $steps_table;
    private $enrollments_table;
    private $history_table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'sffc_crm_sequences';
        $this->steps_table = $wpdb->prefix . 'sffc_crm_sequence_steps';
        $this->enrollments_table = $wpdb->prefix . 'sffc_crm_sequence_enrollments';
        $this->history_table = $wpdb->prefix . 'sffc_crm_enrollment_history';
    }

    /**
     * Get sequence by ID
     */
    public function get($id) {
        global $wpdb;

        $sequence = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $id
        ), ARRAY_A);

        if ($sequence) {
            $sequence['settings'] = json_decode($sequence['settings'], true) ?: [];
            $sequence['steps'] = $this->get_steps($id);
        }

        return $sequence;
    }

    /**
     * Get sequence with stats
     */
    public function get_with_stats($id, $user_id) {
        global $wpdb;

        $sequence = $this->get($id);

        if (!$sequence || $sequence['user_id'] != $user_id) {
            return null;
        }

        // Get enrollment stats
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as total_enrolled,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active,
                COUNT(CASE WHEN status = 'paused' THEN 1 END) as paused,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                COUNT(CASE WHEN status = 'replied' THEN 1 END) as replied
             FROM {$this->enrollments_table}
             WHERE sequence_id = %d AND user_id = %d",
            $id, $user_id
        ), ARRAY_A);

        $sequence['stats'] = $stats;

        return $sequence;
    }

    /**
     * Get all sequences for a user
     */
    public function get_user_sequences($user_id, $args = []) {
        global $wpdb;

        $defaults = [
            'include_system' => true,
            'active_only' => false,
            'page' => 1,
            'per_page' => 20,
        ];

        $args = wp_parse_args($args, $defaults);

        $where = [];
        $params = [];

        // User sequences (or system sequences)
        if ($args['include_system']) {
            $where[] = "(user_id = %d OR is_system = 1)";
            $params[] = $user_id;
        } else {
            $where[] = "user_id = %d";
            $params[] = $user_id;
        }

        if ($args['active_only']) {
            $where[] = "is_active = 1";
        }

        $where_clause = implode(' AND ', $where);
        $offset = ($args['page'] - 1) * $args['per_page'];

        $sql = "SELECT s.*,
                    (SELECT COUNT(*) FROM {$this->enrollments_table} e WHERE e.sequence_id = s.id AND e.user_id = %d AND e.status = 'active') as active_count,
                    (SELECT COUNT(*) FROM {$this->steps_table} st WHERE st.sequence_id = s.id AND st.is_active = 1) as step_count
                FROM {$this->table} s
                WHERE $where_clause
                ORDER BY s.is_system DESC, s.created_at DESC
                LIMIT %d OFFSET %d";

        $params[] = $user_id;
        $params[] = $args['per_page'];
        $params[] = $offset;

        $sequences = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        // Parse settings for each
        foreach ($sequences as &$seq) {
            $seq['settings'] = json_decode($seq['settings'], true) ?: [];
        }

        return $sequences;
    }

    /**
     * Get sequence count for user
     */
    public function get_user_sequence_count($user_id, $include_system = true) {
        global $wpdb;

        if ($include_system) {
            return $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE user_id = %d OR is_system = 1",
                $user_id
            ));
        }

        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE user_id = %d",
            $user_id
        ));
    }

    /**
     * Create a new sequence
     */
    public function create($user_id, $data) {
        global $wpdb;

        $settings = isset($data['settings']) ? $data['settings'] : [
            'pause_on_reply' => true,
            'skip_weekends' => true,
            'working_hours_only' => false,
            'timezone' => 'UTC',
        ];

        $result = $wpdb->insert($this->table, [
            'user_id' => $user_id,
            'name' => sanitize_text_field($data['name']),
            'description' => isset($data['description']) ? sanitize_textarea_field($data['description']) : '',
            'trigger_type' => isset($data['trigger_type']) && in_array($data['trigger_type'], ['manual', 'on_save', 'on_outreach'])
                ? $data['trigger_type'] : 'manual',
            'settings' => json_encode($settings),
            'is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
        ]);

        if ($result) {
            $sequence_id = $wpdb->insert_id;

            // Add steps if provided
            if (!empty($data['steps'])) {
                foreach ($data['steps'] as $index => $step) {
                    $this->add_step($sequence_id, $step, $index);
                }
            }

            return $sequence_id;
        }

        return false;
    }

    /**
     * Update a sequence
     */
    public function update($sequence_id, $user_id, $data) {
        global $wpdb;

        // Verify ownership
        $sequence = $this->get($sequence_id);
        if (!$sequence || ($sequence['user_id'] != $user_id && !$sequence['is_system'])) {
            return false;
        }

        $update_data = [];

        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
        }

        if (isset($data['description'])) {
            $update_data['description'] = sanitize_textarea_field($data['description']);
        }

        if (isset($data['trigger_type'])) {
            $update_data['trigger_type'] = in_array($data['trigger_type'], ['manual', 'on_save', 'on_outreach'])
                ? $data['trigger_type'] : 'manual';
        }

        if (isset($data['settings'])) {
            $update_data['settings'] = json_encode($data['settings']);
        }

        if (isset($data['is_active'])) {
            $update_data['is_active'] = (int)$data['is_active'];
        }

        if (empty($update_data)) {
            return true;
        }

        return $wpdb->update(
            $this->table,
            $update_data,
            ['id' => $sequence_id]
        ) !== false;
    }

    /**
     * Delete a sequence
     */
    public function delete($sequence_id, $user_id) {
        global $wpdb;

        // Verify ownership
        $sequence = $this->get($sequence_id);
        if (!$sequence || $sequence['user_id'] != $user_id || $sequence['is_system']) {
            return false;
        }

        // Check for active enrollments
        $active_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->enrollments_table} WHERE sequence_id = %d AND status = 'active'",
            $sequence_id
        ));

        if ($active_count > 0) {
            return false; // Can't delete sequence with active enrollments
        }

        // Delete steps
        $wpdb->delete($this->steps_table, ['sequence_id' => $sequence_id]);

        // Delete enrollments and history
        $enrollment_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$this->enrollments_table} WHERE sequence_id = %d",
            $sequence_id
        ));

        if (!empty($enrollment_ids)) {
            $ids_placeholder = implode(',', array_fill(0, count($enrollment_ids), '%d'));
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$this->history_table} WHERE enrollment_id IN ($ids_placeholder)",
                ...$enrollment_ids
            ));
        }

        $wpdb->delete($this->enrollments_table, ['sequence_id' => $sequence_id]);

        // Delete sequence
        return $wpdb->delete($this->table, ['id' => $sequence_id]) !== false;
    }

    /**
     * Duplicate a sequence
     */
    public function duplicate($sequence_id, $user_id) {
        $sequence = $this->get($sequence_id);
        if (!$sequence) {
            return false;
        }

        $new_data = [
            'name' => $sequence['name'] . ' (Copy)',
            'description' => $sequence['description'],
            'trigger_type' => 'manual', // Always manual for duplicates
            'settings' => $sequence['settings'],
            'steps' => $sequence['steps'],
        ];

        return $this->create($user_id, $new_data);
    }

    // ============================================
    // STEPS MANAGEMENT
    // ============================================

    /**
     * Get steps for a sequence
     */
    public function get_steps($sequence_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->steps_table}
             WHERE sequence_id = %d
             ORDER BY step_order ASC",
            $sequence_id
        ), ARRAY_A);
    }

    /**
     * Get a single step
     */
    public function get_step($step_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->steps_table} WHERE id = %d",
            $step_id
        ), ARRAY_A);
    }

    /**
     * Add a step to a sequence
     */
    public function add_step($sequence_id, $data, $order = null) {
        global $wpdb;

        // Get max order if not specified
        if ($order === null) {
            $max_order = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(step_order) FROM {$this->steps_table} WHERE sequence_id = %d",
                $sequence_id
            ));
            $order = ($max_order !== null) ? $max_order + 1 : 0;
        }

        $step_type = $data['step_type'] ?? 'manual_email';
        $valid_types = ['auto_email', 'manual_email', 'linkedin_message', 'linkedin_connect', 'wait', 'task'];
        if (!in_array($step_type, $valid_types)) {
            $step_type = 'manual_email';
        }

        $result = $wpdb->insert($this->steps_table, [
            'sequence_id' => $sequence_id,
            'step_order' => $order,
            'step_type' => $step_type,
            'delay_days' => intval($data['delay_days'] ?? 0),
            'delay_hours' => intval($data['delay_hours'] ?? 0),
            'channel' => in_array($step_type, ['linkedin_message', 'linkedin_connect']) ? 'linkedin' : 'email',
            'template_id' => !empty($data['template_id']) ? intval($data['template_id']) : null,
            'subject' => isset($data['subject']) ? sanitize_text_field($data['subject']) : '',
            'content' => isset($data['content']) ? wp_kses_post($data['content']) : '',
            'task_title' => isset($data['task_title']) ? sanitize_text_field($data['task_title']) : '',
            'task_description' => isset($data['task_description']) ? sanitize_textarea_field($data['task_description']) : '',
            'send_time_preference' => isset($data['send_time_preference']) && in_array($data['send_time_preference'], ['morning', 'afternoon', 'evening', 'any'])
                ? $data['send_time_preference'] : 'morning',
            'skip_weekends' => isset($data['skip_weekends']) ? (int)$data['skip_weekends'] : 1,
            'is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
        ]);

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update a step
     */
    public function update_step($step_id, $data) {
        global $wpdb;

        $update_data = [];

        if (isset($data['step_type'])) {
            $valid_types = ['auto_email', 'manual_email', 'linkedin_message', 'linkedin_connect', 'wait', 'task'];
            if (in_array($data['step_type'], $valid_types)) {
                $update_data['step_type'] = $data['step_type'];
                $update_data['channel'] = in_array($data['step_type'], ['linkedin_message', 'linkedin_connect']) ? 'linkedin' : 'email';
            }
        }

        if (isset($data['delay_days'])) {
            $update_data['delay_days'] = intval($data['delay_days']);
        }

        if (isset($data['delay_hours'])) {
            $update_data['delay_hours'] = intval($data['delay_hours']);
        }

        if (isset($data['template_id'])) {
            $update_data['template_id'] = !empty($data['template_id']) ? intval($data['template_id']) : null;
        }

        if (isset($data['subject'])) {
            $update_data['subject'] = sanitize_text_field($data['subject']);
        }

        if (isset($data['content'])) {
            $update_data['content'] = wp_kses_post($data['content']);
        }

        if (isset($data['task_title'])) {
            $update_data['task_title'] = sanitize_text_field($data['task_title']);
        }

        if (isset($data['task_description'])) {
            $update_data['task_description'] = sanitize_textarea_field($data['task_description']);
        }

        if (isset($data['send_time_preference'])) {
            $update_data['send_time_preference'] = in_array($data['send_time_preference'], ['morning', 'afternoon', 'evening', 'any'])
                ? $data['send_time_preference'] : 'morning';
        }

        if (isset($data['skip_weekends'])) {
            $update_data['skip_weekends'] = (int)$data['skip_weekends'];
        }

        if (isset($data['is_active'])) {
            $update_data['is_active'] = (int)$data['is_active'];
        }

        if (empty($update_data)) {
            return true;
        }

        return $wpdb->update($this->steps_table, $update_data, ['id' => $step_id]) !== false;
    }

    /**
     * Delete a step
     */
    public function delete_step($step_id) {
        global $wpdb;

        $step = $this->get_step($step_id);
        if (!$step) {
            return false;
        }

        // Delete the step
        $result = $wpdb->delete($this->steps_table, ['id' => $step_id]);

        // Reorder remaining steps
        if ($result) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$this->steps_table}
                 SET step_order = step_order - 1
                 WHERE sequence_id = %d AND step_order > %d",
                $step['sequence_id'], $step['step_order']
            ));
        }

        return $result !== false;
    }

    /**
     * Reorder steps
     */
    public function reorder_steps($sequence_id, $step_ids) {
        global $wpdb;

        foreach ($step_ids as $index => $step_id) {
            $wpdb->update(
                $this->steps_table,
                ['step_order' => $index],
                ['id' => $step_id, 'sequence_id' => $sequence_id]
            );
        }

        return true;
    }

    // ============================================
    // SYSTEM TEMPLATES
    // ============================================

    /**
     * Get or create system sequence templates
     */
    public function get_system_sequences() {
        global $wpdb;

        $sequences = $wpdb->get_results(
            "SELECT * FROM {$this->table} WHERE is_system = 1 AND is_active = 1",
            ARRAY_A
        );

        // Create default if none exist
        if (empty($sequences)) {
            $this->create_default_sequences();
            $sequences = $wpdb->get_results(
                "SELECT * FROM {$this->table} WHERE is_system = 1 AND is_active = 1",
                ARRAY_A
            );
        }

        foreach ($sequences as &$seq) {
            $seq['settings'] = json_decode($seq['settings'], true) ?: [];
            $seq['steps'] = $this->get_steps($seq['id']);
        }

        return $sequences;
    }

    /**
     * Create default system sequences
     */
    private function create_default_sequences() {
        global $wpdb;

        // Standard Outreach Sequence
        $wpdb->insert($this->table, [
            'user_id' => 0,
            'name' => 'Standard Outreach',
            'description' => 'A 4-step sequence: Initial email, LinkedIn connection, follow-up, final touch.',
            'is_system' => 1,
            'is_active' => 1,
            'trigger_type' => 'manual',
            'settings' => json_encode([
                'pause_on_reply' => true,
                'skip_weekends' => true,
            ]),
        ]);
        $seq_id = $wpdb->insert_id;

        // Add steps for Standard Outreach
        $standard_steps = [
            [
                'step_order' => 0,
                'step_type' => 'manual_email',
                'delay_days' => 0,
                'subject' => '{role_title} opportunity',
                'content' => "Hi {recruiter_first_name},\n\nI saw your post about the {role_title} role and wanted to reach out directly.\n\nWith my background in {candidate_experience}, I believe I could be a strong fit for what you're looking for.\n\nWould you be open to a brief conversation?\n\nBest regards,\n{candidate_name}",
            ],
            [
                'step_order' => 1,
                'step_type' => 'linkedin_connect',
                'delay_days' => 2,
                'content' => "Hi {recruiter_first_name}, I recently emailed you about the {role_title} role. Would love to connect here as well.",
            ],
            [
                'step_order' => 2,
                'step_type' => 'manual_email',
                'delay_days' => 5,
                'subject' => 'Re: {role_title} opportunity',
                'content' => "Hi {recruiter_first_name},\n\nI wanted to follow up on my previous message regarding the {role_title} position.\n\nI remain very interested and would welcome the chance to discuss how I could contribute.\n\nBest regards,\n{candidate_name}",
            ],
            [
                'step_order' => 3,
                'step_type' => 'manual_email',
                'delay_days' => 7,
                'subject' => 'Final follow-up: {role_title}',
                'content' => "Hi {recruiter_first_name},\n\nI wanted to send one final note regarding the {role_title} opportunity.\n\nIf the role is still open and you think there might be a fit, I'd love to hear from you. If not, no worries at all.\n\nWishing you all the best.\n\n{candidate_name}",
            ],
        ];

        foreach ($standard_steps as $step) {
            $wpdb->insert($this->steps_table, array_merge($step, [
                'sequence_id' => $seq_id,
                'channel' => strpos($step['step_type'], 'linkedin') !== false ? 'linkedin' : 'email',
                'send_time_preference' => 'morning',
                'skip_weekends' => 1,
                'is_active' => 1,
            ]));
        }

        // Quick Follow-up Sequence
        $wpdb->insert($this->table, [
            'user_id' => 0,
            'name' => 'Quick Follow-up',
            'description' => 'Simple 2-step email sequence for time-sensitive opportunities.',
            'is_system' => 1,
            'is_active' => 1,
            'trigger_type' => 'manual',
            'settings' => json_encode([
                'pause_on_reply' => true,
                'skip_weekends' => false,
            ]),
        ]);
        $seq_id = $wpdb->insert_id;

        $quick_steps = [
            [
                'step_order' => 0,
                'step_type' => 'manual_email',
                'delay_days' => 0,
                'subject' => 'Quick note: {role_title}',
                'content' => "Hi {recruiter_first_name},\n\nI noticed your post about the {role_title} role and wanted to express my interest.\n\nI have {candidate_years} years of experience in this space and would love to discuss.\n\nBest,\n{candidate_name}",
            ],
            [
                'step_order' => 1,
                'step_type' => 'manual_email',
                'delay_days' => 3,
                'subject' => 'Re: {role_title}',
                'content' => "Hi {recruiter_first_name},\n\nJust bumping this to the top of your inbox. Would love to connect if you have a moment.\n\nBest,\n{candidate_name}",
            ],
        ];

        foreach ($quick_steps as $step) {
            $wpdb->insert($this->steps_table, array_merge($step, [
                'sequence_id' => $seq_id,
                'channel' => 'email',
                'send_time_preference' => 'morning',
                'skip_weekends' => 0,
                'is_active' => 1,
            ]));
        }

        // LinkedIn-First Sequence
        $wpdb->insert($this->table, [
            'user_id' => 0,
            'name' => 'LinkedIn First',
            'description' => 'Start with LinkedIn connection, then move to email.',
            'is_system' => 1,
            'is_active' => 1,
            'trigger_type' => 'manual',
            'settings' => json_encode([
                'pause_on_reply' => true,
                'skip_weekends' => true,
            ]),
        ]);
        $seq_id = $wpdb->insert_id;

        $linkedin_steps = [
            [
                'step_order' => 0,
                'step_type' => 'linkedin_connect',
                'delay_days' => 0,
                'content' => "Hi {recruiter_first_name}, I came across your post about the {role_title} role. Would love to connect and learn more.",
            ],
            [
                'step_order' => 1,
                'step_type' => 'linkedin_message',
                'delay_days' => 3,
                'content' => "Thanks for connecting, {recruiter_first_name}! I'm very interested in the {role_title} opportunity. Would you have a few minutes to chat this week?",
            ],
            [
                'step_order' => 2,
                'step_type' => 'manual_email',
                'delay_days' => 5,
                'subject' => 'Following up: {role_title}',
                'content' => "Hi {recruiter_first_name},\n\nWe connected on LinkedIn recently regarding the {role_title} role. I wanted to follow up via email as well.\n\nI'd love to schedule a brief call to discuss. Let me know what works for you.\n\nBest,\n{candidate_name}",
            ],
        ];

        foreach ($linkedin_steps as $step) {
            $wpdb->insert($this->steps_table, array_merge($step, [
                'sequence_id' => $seq_id,
                'channel' => strpos($step['step_type'], 'linkedin') !== false ? 'linkedin' : 'email',
                'send_time_preference' => 'morning',
                'skip_weekends' => 1,
                'is_active' => 1,
            ]));
        }
    }

    // ============================================
    // UTILITY METHODS
    // ============================================

    /**
     * Update sequence counts
     */
    public function update_counts($sequence_id) {
        global $wpdb;

        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as enrolled,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                COUNT(CASE WHEN status = 'replied' THEN 1 END) as replied
             FROM {$this->enrollments_table}
             WHERE sequence_id = %d",
            $sequence_id
        ), ARRAY_A);

        return $wpdb->update($this->table, [
            'enrolled_count' => $stats['enrolled'],
            'completed_count' => $stats['completed'],
            'replied_count' => $stats['replied'],
        ], ['id' => $sequence_id]);
    }

    /**
     * Check if user can create more sequences
     */
    public function can_create_sequence($user_id) {
        // All users can create sequences (no Pro restriction)
        return [
            'allowed' => true,
            'limit' => -1,
            'current' => $this->get_user_sequence_count($user_id, false),
        ];
    }

    /**
     * Get remaining sequence slots
     */
    public function get_remaining_slots($user_id) {
        // Unlimited for all users
        return -1;
    }
}
