<?php
/**
 * CRM Sequence Enrollment Model
 * Handles enrollment of recruiters in sequences
 *
 * @package SennaCareers
 * @since 7.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_Sequence_Enrollment {

    private $table;
    private $history_table;
    private $sequences_table;
    private $steps_table;
    private $tasks_table;
    private $recruiters_table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'sffc_crm_sequence_enrollments';
        $this->history_table = $wpdb->prefix . 'sffc_crm_enrollment_history';
        $this->sequences_table = $wpdb->prefix . 'sffc_crm_sequences';
        $this->steps_table = $wpdb->prefix . 'sffc_crm_sequence_steps';
        $this->tasks_table = $wpdb->prefix . 'sffc_crm_tasks';
        $this->recruiters_table = $wpdb->prefix . 'sffc_crm_recruiters';
    }

    /**
     * Get enrollment by ID
     */
    public function get($id) {
        global $wpdb;

        $enrollment = $wpdb->get_row($wpdb->prepare(
            "SELECT e.*, s.name as sequence_name, r.name as recruiter_name, r.email as recruiter_email
             FROM {$this->table} e
             JOIN {$this->sequences_table} s ON e.sequence_id = s.id
             JOIN {$this->recruiters_table} r ON e.recruiter_id = r.id
             WHERE e.id = %d",
            $id
        ), ARRAY_A);

        if ($enrollment) {
            $enrollment['personalization_data'] = json_decode($enrollment['personalization_data'], true) ?: [];
        }

        return $enrollment;
    }

    /**
     * Get enrollment with full details
     */
    public function get_with_details($id, $user_id) {
        global $wpdb;

        $enrollment = $this->get($id);

        if (!$enrollment || $enrollment['user_id'] != $user_id) {
            return null;
        }

        // Get sequence steps
        $enrollment['steps'] = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->steps_table} WHERE sequence_id = %d ORDER BY step_order",
            $enrollment['sequence_id']
        ), ARRAY_A);

        // Get execution history
        $enrollment['history'] = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->history_table} WHERE enrollment_id = %d ORDER BY executed_at",
            $id
        ), ARRAY_A);

        return $enrollment;
    }

    /**
     * Get enrollments for a user
     */
    public function get_user_enrollments($user_id, $args = []) {
        global $wpdb;

        $defaults = [
            'sequence_id' => null,
            'status' => null,
            'page' => 1,
            'per_page' => 20,
        ];

        $args = wp_parse_args($args, $defaults);

        $where = ["e.user_id = %d"];
        $params = [$user_id];

        if ($args['sequence_id']) {
            $where[] = "e.sequence_id = %d";
            $params[] = $args['sequence_id'];
        }

        if ($args['status']) {
            if (is_array($args['status'])) {
                $placeholders = implode(',', array_fill(0, count($args['status']), '%s'));
                $where[] = "e.status IN ($placeholders)";
                $params = array_merge($params, $args['status']);
            } else {
                $where[] = "e.status = %s";
                $params[] = $args['status'];
            }
        }

        $where_clause = implode(' AND ', $where);
        $offset = ($args['page'] - 1) * $args['per_page'];

        $params[] = $args['per_page'];
        $params[] = $offset;

        $sql = "SELECT e.*, s.name as sequence_name, r.name as recruiter_name, r.firm as recruiter_firm, r.photo_url,
                    (SELECT COUNT(*) FROM {$this->steps_table} st WHERE st.sequence_id = e.sequence_id AND st.is_active = 1) as total_steps
                FROM {$this->table} e
                JOIN {$this->sequences_table} s ON e.sequence_id = s.id
                JOIN {$this->recruiters_table} r ON e.recruiter_id = r.id
                WHERE $where_clause
                ORDER BY e.next_step_at ASC, e.enrolled_at DESC
                LIMIT %d OFFSET %d";

        $enrollments = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        foreach ($enrollments as &$e) {
            $e['personalization_data'] = json_decode($e['personalization_data'], true) ?: [];
        }

        return $enrollments;
    }

    /**
     * Get enrollment count for a user
     */
    public function get_user_enrollment_count($user_id, $status = null) {
        global $wpdb;

        if ($status) {
            return $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE user_id = %d AND status = %s",
                $user_id, $status
            ));
        }

        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE user_id = %d",
            $user_id
        ));
    }

    /**
     * Get enrollments for a recruiter
     */
    public function get_recruiter_enrollments($user_id, $recruiter_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT e.*, s.name as sequence_name
             FROM {$this->table} e
             JOIN {$this->sequences_table} s ON e.sequence_id = s.id
             WHERE e.user_id = %d AND e.recruiter_id = %d
             ORDER BY e.enrolled_at DESC",
            $user_id, $recruiter_id
        ), ARRAY_A);
    }

    /**
     * Get active enrollments for a recruiter (for recruiter detail display)
     */
    public function get_recruiter_active_enrollments($recruiter_id, $user_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT e.id, e.sequence_id, e.current_step_index, e.status, e.enrolled_at, e.next_step_at,
                    s.name as sequence_name,
                    (SELECT COUNT(*) FROM {$this->steps_table} st WHERE st.sequence_id = e.sequence_id AND st.is_active = 1) as total_steps
             FROM {$this->table} e
             JOIN {$this->sequences_table} s ON e.sequence_id = s.id
             WHERE e.recruiter_id = %d AND e.user_id = %d AND e.status IN ('active', 'paused')
             ORDER BY e.enrolled_at DESC",
            $recruiter_id, $user_id
        ), ARRAY_A);
    }

    /**
     * Check if recruiter is enrolled in a sequence
     */
    public function is_enrolled($sequence_id, $user_id, $recruiter_id) {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table}
             WHERE sequence_id = %d AND user_id = %d AND recruiter_id = %d AND status IN ('active', 'paused')",
            $sequence_id, $user_id, $recruiter_id
        ));
    }

    /**
     * Enroll a recruiter in a sequence
     */
    public function enroll($sequence_id, $user_id, $recruiter_id, $post_id = null, $personalization = []) {
        global $wpdb;

        // Check if already enrolled
        if ($this->is_enrolled($sequence_id, $user_id, $recruiter_id)) {
            return false;
        }

        // Get first step to calculate next_step_at
        $first_step = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->steps_table} WHERE sequence_id = %d AND is_active = 1 ORDER BY step_order LIMIT 1",
            $sequence_id
        ), ARRAY_A);

        $next_step_at = $this->calculate_next_step_time($first_step);

        $result = $wpdb->insert($this->table, [
            'sequence_id' => $sequence_id,
            'user_id' => $user_id,
            'recruiter_id' => $recruiter_id,
            'post_id' => $post_id,
            'current_step_index' => 0,
            'status' => 'active',
            'next_step_at' => $next_step_at,
            'personalization_data' => json_encode($personalization),
        ]);

        if ($result) {
            $enrollment_id = $wpdb->insert_id;

            // Update sequence enrolled count
            $this->update_sequence_counts($sequence_id);

            // Log activity
            $this->log_activity($user_id, $recruiter_id, 'sequence_enrolled', [
                'sequence_id' => $sequence_id,
                'enrollment_id' => $enrollment_id,
            ]);

            // Create task for first step if it's not auto
            if ($first_step && !in_array($first_step['step_type'], ['wait'])) {
                $this->create_step_task($enrollment_id, $first_step, 0);
            }

            return $enrollment_id;
        }

        return false;
    }

    /**
     * Bulk enroll recruiters in a sequence
     */
    public function bulk_enroll($sequence_id, $user_id, $recruiter_ids, $post_id = null) {
        $results = [
            'enrolled' => [],
            'skipped' => [],
            'failed' => [],
        ];

        foreach ($recruiter_ids as $recruiter_id) {
            if ($this->is_enrolled($sequence_id, $user_id, $recruiter_id)) {
                $results['skipped'][] = $recruiter_id;
                continue;
            }

            $enrollment_id = $this->enroll($sequence_id, $user_id, $recruiter_id, $post_id);
            if ($enrollment_id) {
                $results['enrolled'][] = [
                    'recruiter_id' => $recruiter_id,
                    'enrollment_id' => $enrollment_id,
                ];
            } else {
                $results['failed'][] = $recruiter_id;
            }
        }

        return $results;
    }

    /**
     * Pause an enrollment
     */
    public function pause($enrollment_id, $user_id, $reason = '') {
        global $wpdb;

        $enrollment = $this->get($enrollment_id);
        if (!$enrollment || $enrollment['user_id'] != $user_id) {
            return false;
        }

        if ($enrollment['status'] !== 'active') {
            return false;
        }

        $result = $wpdb->update($this->table, [
            'status' => 'paused',
            'paused_at' => current_time('mysql'),
            'pause_reason' => sanitize_text_field($reason),
        ], ['id' => $enrollment_id]);

        if ($result !== false) {
            $this->update_sequence_counts($enrollment['sequence_id']);
        }

        return $result !== false;
    }

    /**
     * Resume a paused enrollment
     */
    public function resume($enrollment_id, $user_id) {
        global $wpdb;

        $enrollment = $this->get($enrollment_id);
        if (!$enrollment || $enrollment['user_id'] != $user_id) {
            return false;
        }

        if ($enrollment['status'] !== 'paused') {
            return false;
        }

        // Get current step to recalculate next_step_at
        $current_step = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->steps_table}
             WHERE sequence_id = %d AND step_order = %d AND is_active = 1",
            $enrollment['sequence_id'], $enrollment['current_step_index']
        ), ARRAY_A);

        $next_step_at = $current_step ? $this->calculate_next_step_time($current_step) : null;

        $result = $wpdb->update($this->table, [
            'status' => 'active',
            'paused_at' => null,
            'pause_reason' => null,
            'next_step_at' => $next_step_at,
        ], ['id' => $enrollment_id]);

        if ($result !== false) {
            $this->update_sequence_counts($enrollment['sequence_id']);
        }

        return $result !== false;
    }

    /**
     * Mark enrollment as replied (auto-pause)
     */
    public function mark_replied($enrollment_id, $user_id) {
        global $wpdb;

        $enrollment = $this->get($enrollment_id);
        if (!$enrollment || $enrollment['user_id'] != $user_id) {
            return false;
        }

        $result = $wpdb->update($this->table, [
            'status' => 'replied',
            'replied_at' => current_time('mysql'),
        ], ['id' => $enrollment_id]);

        if ($result !== false) {
            $this->update_sequence_counts($enrollment['sequence_id']);

            // Cancel pending tasks for this enrollment
            $wpdb->update($this->tasks_table, [
                'status' => 'skipped',
                'skipped_at' => current_time('mysql'),
            ], [
                'enrollment_id' => $enrollment_id,
                'status' => 'pending',
            ]);
        }

        return $result !== false;
    }

    /**
     * Complete an enrollment
     */
    public function complete($enrollment_id) {
        global $wpdb;

        $enrollment = $this->get($enrollment_id);
        if (!$enrollment) {
            return false;
        }

        $result = $wpdb->update($this->table, [
            'status' => 'completed',
            'completed_at' => current_time('mysql'),
            'next_step_at' => null,
        ], ['id' => $enrollment_id]);

        if ($result !== false) {
            $this->update_sequence_counts($enrollment['sequence_id']);
        }

        return $result !== false;
    }

    /**
     * Remove enrollment
     */
    public function remove($enrollment_id, $user_id) {
        global $wpdb;

        $enrollment = $this->get($enrollment_id);
        if (!$enrollment || $enrollment['user_id'] != $user_id) {
            return false;
        }

        // Delete pending tasks
        $wpdb->delete($this->tasks_table, [
            'enrollment_id' => $enrollment_id,
            'status' => 'pending',
        ]);

        // Delete history
        $wpdb->delete($this->history_table, ['enrollment_id' => $enrollment_id]);

        // Delete enrollment
        $result = $wpdb->delete($this->table, ['id' => $enrollment_id]);

        if ($result) {
            $this->update_sequence_counts($enrollment['sequence_id']);
        }

        return $result !== false;
    }

    /**
     * Skip current step and advance
     */
    public function skip_step($enrollment_id, $user_id) {
        global $wpdb;

        $enrollment = $this->get_with_details($enrollment_id, $user_id);
        if (!$enrollment || $enrollment['status'] !== 'active') {
            return false;
        }

        // Log the skip
        $this->log_step_execution($enrollment_id, $enrollment['current_step_index'], 'skipped');

        // Cancel pending task for this step
        $wpdb->update($this->tasks_table, [
            'status' => 'skipped',
            'skipped_at' => current_time('mysql'),
        ], [
            'enrollment_id' => $enrollment_id,
            'step_index' => $enrollment['current_step_index'],
            'status' => 'pending',
        ]);

        // Advance to next step
        return $this->advance_to_next_step($enrollment_id);
    }

    /**
     * Advance to the next step
     */
    public function advance_to_next_step($enrollment_id) {
        global $wpdb;

        $enrollment = $this->get($enrollment_id);
        if (!$enrollment) {
            return false;
        }

        $next_step_index = $enrollment['current_step_index'] + 1;

        // Get next step
        $next_step = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->steps_table}
             WHERE sequence_id = %d AND step_order = %d AND is_active = 1",
            $enrollment['sequence_id'], $next_step_index
        ), ARRAY_A);

        // No more steps - complete the sequence
        if (!$next_step) {
            return $this->complete($enrollment_id);
        }

        $next_step_at = $this->calculate_next_step_time($next_step);

        $wpdb->update($this->table, [
            'current_step_index' => $next_step_index,
            'next_step_at' => $next_step_at,
            'last_step_at' => current_time('mysql'),
        ], ['id' => $enrollment_id]);

        // Create task for the step
        if (!in_array($next_step['step_type'], ['wait'])) {
            $this->create_step_task($enrollment_id, $next_step, $next_step_index);
        }

        return true;
    }

    /**
     * Calculate when the next step should execute
     */
    private function calculate_next_step_time($step) {
        if (!$step) {
            return null;
        }

        $delay_days = intval($step['delay_days']);
        $delay_hours = intval($step['delay_hours']);

        // Start from now
        $next_time = new DateTime('now', new DateTimeZone('UTC'));

        // Add delay
        if ($delay_days > 0) {
            $next_time->modify("+{$delay_days} days");
        }
        if ($delay_hours > 0) {
            $next_time->modify("+{$delay_hours} hours");
        }

        // Apply time preference
        $preference = $step['send_time_preference'] ?? 'morning';
        switch ($preference) {
            case 'morning':
                $next_time->setTime(9, 0);
                break;
            case 'afternoon':
                $next_time->setTime(14, 0);
                break;
            case 'evening':
                $next_time->setTime(18, 0);
                break;
            // 'any' - keep current time
        }

        // Skip weekends if configured
        if (!empty($step['skip_weekends'])) {
            $day_of_week = intval($next_time->format('N'));
            if ($day_of_week === 6) { // Saturday
                $next_time->modify('+2 days');
            } elseif ($day_of_week === 7) { // Sunday
                $next_time->modify('+1 day');
            }
        }

        return $next_time->format('Y-m-d H:i:s');
    }

    /**
     * Create a task for a sequence step
     */
    private function create_step_task($enrollment_id, $step, $step_index) {
        global $wpdb;

        $enrollment = $this->get($enrollment_id);
        if (!$enrollment) {
            return false;
        }

        // Determine task type
        $task_type_map = [
            'auto_email' => 'send_email',
            'manual_email' => 'send_email',
            'linkedin_message' => 'linkedin_message',
            'linkedin_connect' => 'linkedin_connect',
            'task' => 'custom',
        ];

        $task_type = $task_type_map[$step['step_type']] ?? 'custom';

        // Build task title
        $title_map = [
            'send_email' => 'Send email to ' . $enrollment['recruiter_name'],
            'linkedin_message' => 'Send LinkedIn message to ' . $enrollment['recruiter_name'],
            'linkedin_connect' => 'Connect with ' . $enrollment['recruiter_name'] . ' on LinkedIn',
            'custom' => $step['task_title'] ?: 'Task for ' . $enrollment['recruiter_name'],
        ];

        $title = $title_map[$task_type];

        // Parse due date from next_step_at
        $due_date = $enrollment['next_step_at'] ?: current_time('mysql');

        $result = $wpdb->insert($this->tasks_table, [
            'user_id' => $enrollment['user_id'],
            'recruiter_id' => $enrollment['recruiter_id'],
            'post_id' => $enrollment['post_id'],
            'sequence_id' => $enrollment['sequence_id'],
            'enrollment_id' => $enrollment_id,
            'step_index' => $step_index,
            'task_type' => $task_type,
            'title' => $title,
            'description' => $step['task_description'] ?: 'Step ' . ($step_index + 1) . ' of sequence: ' . $enrollment['sequence_name'],
            'pre_filled_subject' => $step['subject'] ?: '',
            'pre_filled_content' => $step['content'] ?: '',
            'template_id' => $step['template_id'],
            'due_date' => $due_date,
            'is_auto_generated' => 1,
            'priority' => 'medium',
            'status' => 'pending',
        ]);

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Log step execution
     */
    private function log_step_execution($enrollment_id, $step_index, $action, $outreach_id = null, $task_id = null, $notes = '') {
        global $wpdb;

        $enrollment = $this->get($enrollment_id);
        if (!$enrollment) {
            return false;
        }

        // Get step type
        $step = $wpdb->get_row($wpdb->prepare(
            "SELECT step_type FROM {$this->steps_table}
             WHERE sequence_id = %d AND step_order = %d",
            $enrollment['sequence_id'], $step_index
        ), ARRAY_A);

        return $wpdb->insert($this->history_table, [
            'enrollment_id' => $enrollment_id,
            'step_index' => $step_index,
            'step_type' => $step ? $step['step_type'] : 'unknown',
            'action_taken' => $action,
            'outreach_id' => $outreach_id,
            'task_id' => $task_id,
            'notes' => $notes,
        ]);
    }

    /**
     * Update sequence enrollment counts
     */
    private function update_sequence_counts($sequence_id) {
        $sequence_model = new SFFC_CRM_Sequence();
        $sequence_model->update_counts($sequence_id);
    }

    /**
     * Log activity
     */
    private function log_activity($user_id, $recruiter_id, $type, $data = []) {
        global $wpdb;

        $activity_table = $wpdb->prefix . 'sffc_crm_activity';

        $wpdb->insert($activity_table, [
            'user_id' => $user_id,
            'recruiter_id' => $recruiter_id,
            'activity_type' => $type,
            'activity_data' => json_encode($data),
        ]);
    }

    // ============================================
    // PROCESSOR METHODS (for background jobs)
    // ============================================

    /**
     * Get enrollments due for processing
     */
    public function get_due_enrollments($limit = 50) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT e.*, s.settings as sequence_settings
             FROM {$this->table} e
             JOIN {$this->sequences_table} s ON e.sequence_id = s.id
             WHERE e.status = 'active'
               AND e.next_step_at IS NOT NULL
               AND e.next_step_at <= %s
               AND s.is_active = 1
             ORDER BY e.next_step_at ASC
             LIMIT %d",
            current_time('mysql'), $limit
        ), ARRAY_A);
    }

    /**
     * Process a due enrollment step
     */
    public function process_step($enrollment_id) {
        global $wpdb;

        $enrollment = $this->get($enrollment_id);
        if (!$enrollment || $enrollment['status'] !== 'active') {
            return false;
        }

        // Get current step
        $step = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->steps_table}
             WHERE sequence_id = %d AND step_order = %d AND is_active = 1",
            $enrollment['sequence_id'], $enrollment['current_step_index']
        ), ARRAY_A);

        if (!$step) {
            // No valid step found, complete the sequence
            return $this->complete($enrollment_id);
        }

        // For 'wait' steps, just advance
        if ($step['step_type'] === 'wait') {
            $this->log_step_execution($enrollment_id, $enrollment['current_step_index'], 'executed');
            return $this->advance_to_next_step($enrollment_id);
        }

        // For other step types, the task should already exist
        // Just log execution and advance
        $this->log_step_execution($enrollment_id, $enrollment['current_step_index'], 'executed');

        return $this->advance_to_next_step($enrollment_id);
    }
}
