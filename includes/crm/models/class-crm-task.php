<?php
/**
 * CRM Task Model
 *
 * Handles all task operations including CRUD, filtering, and sequence integration.
 *
 * @package SennaCareers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_Task {

    /**
     * Database table name
     */
    private $table;

    /**
     * Valid task types (must match database enum)
     */
    private $valid_types = array(
        'follow_up',
        'send_email',
        'linkedin_message',
        'linkedin_connect',
        'research',
        'interview_prep',
        'custom'
    );

    /**
     * Valid task statuses
     */
    private $valid_statuses = array(
        'pending',
        'in_progress',
        'completed',
        'skipped'
    );

    /**
     * Valid priorities
     */
    private $valid_priorities = array(
        'low',
        'medium',
        'high',
        'urgent'
    );

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'sffc_crm_tasks';
    }

    /**
     * Get a single task
     *
     * @param int $id Task ID
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
     * Get task with related data (recruiter info, enrollment info)
     *
     * @param int $id Task ID
     * @param int $user_id User ID for ownership check
     * @return array|null
     */
    public function get_with_details($id, $user_id) {
        global $wpdb;

        $recruiters_table = $wpdb->prefix . 'sffc_crm_recruiters';
        $enrollments_table = $wpdb->prefix . 'sffc_crm_sequence_enrollments';
        $sequences_table = $wpdb->prefix . 'sffc_crm_sequences';

        $task = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT t.*,
                    r.name as recruiter_name,
                    r.email as recruiter_email,
                    r.linkedin_url as recruiter_linkedin,
                    r.company as recruiter_company,
                    r.title as recruiter_title,
                    e.status as enrollment_status,
                    e.current_step_index as enrollment_step,
                    s.name as sequence_name
                FROM {$this->table} t
                LEFT JOIN {$recruiters_table} r ON t.recruiter_id = r.id
                LEFT JOIN {$enrollments_table} e ON t.enrollment_id = e.id
                LEFT JOIN {$sequences_table} s ON e.sequence_id = s.id
                WHERE t.id = %d AND t.user_id = %d",
                $id,
                $user_id
            ),
            ARRAY_A
        );

        return $task;
    }

    /**
     * Get user's tasks with filtering and pagination
     *
     * @param int   $user_id User ID
     * @param array $args    Query arguments
     * @return array
     */
    public function get_user_tasks($user_id, $args = array()) {
        global $wpdb;

        $defaults = array(
            'status'       => null,
            'type'         => null,
            'priority'     => null,
            'recruiter_id' => null,
            'post_id'      => null,
            'enrollment_id'=> null,
            'due_from'     => null,
            'due_to'       => null,
            'overdue_only' => false,
            'today_only'   => false,
            'upcoming_days'=> null,
            'search'       => null,
            'orderby'      => 'due_date',
            'order'        => 'ASC',
            'limit'        => 50,
            'offset'       => 0,
            'count_only'   => false
        );

        $args = wp_parse_args($args, $defaults);

        $recruiters_table = $wpdb->prefix . 'sffc_crm_recruiters';

        // Build WHERE clause
        $where = array("t.user_id = %d");
        $params = array($user_id);

        // Status filter (can be array)
        if (!empty($args['status'])) {
            if (is_array($args['status'])) {
                $placeholders = implode(',', array_fill(0, count($args['status']), '%s'));
                $where[] = "t.status IN ($placeholders)";
                $params = array_merge($params, $args['status']);
            } else {
                $where[] = "t.status = %s";
                $params[] = $args['status'];
            }
        }

        // Type filter
        if (!empty($args['type'])) {
            if (is_array($args['type'])) {
                $placeholders = implode(',', array_fill(0, count($args['type']), '%s'));
                $where[] = "t.task_type IN ($placeholders)";
                $params = array_merge($params, $args['type']);
            } else {
                $where[] = "t.task_type = %s";
                $params[] = $args['type'];
            }
        }

        // Priority filter
        if (!empty($args['priority'])) {
            $where[] = "t.priority = %s";
            $params[] = $args['priority'];
        }

        // Recruiter filter
        if (!empty($args['recruiter_id'])) {
            $where[] = "t.recruiter_id = %d";
            $params[] = $args['recruiter_id'];
        }

        // Post filter
        if (!empty($args['post_id'])) {
            $where[] = "t.post_id = %d";
            $params[] = $args['post_id'];
        }

        // Enrollment filter
        if (!empty($args['enrollment_id'])) {
            $where[] = "t.enrollment_id = %d";
            $params[] = $args['enrollment_id'];
        }

        // Date range filters
        if (!empty($args['due_from'])) {
            $where[] = "t.due_date >= %s";
            $params[] = $args['due_from'];
        }

        if (!empty($args['due_to'])) {
            $where[] = "t.due_date <= %s";
            $params[] = $args['due_to'];
        }

        // Overdue filter
        if ($args['overdue_only']) {
            $where[] = "t.due_date < %s";
            $where[] = "t.status IN ('pending', 'in_progress')";
            $params[] = current_time('mysql');
        }

        // Today only filter
        if ($args['today_only']) {
            $today_start = date('Y-m-d 00:00:00');
            $today_end = date('Y-m-d 23:59:59');
            $where[] = "t.due_date >= %s AND t.due_date <= %s";
            $params[] = $today_start;
            $params[] = $today_end;
        }

        // Upcoming days filter
        if (!empty($args['upcoming_days'])) {
            $now = current_time('mysql');
            $future = date('Y-m-d 23:59:59', strtotime('+' . intval($args['upcoming_days']) . ' days'));
            $where[] = "t.due_date >= %s AND t.due_date <= %s";
            $params[] = $now;
            $params[] = $future;
        }

        // Search filter
        if (!empty($args['search'])) {
            $search_like = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = "(t.title LIKE %s OR t.description LIKE %s OR r.name LIKE %s OR r.company LIKE %s)";
            $params[] = $search_like;
            $params[] = $search_like;
            $params[] = $search_like;
            $params[] = $search_like;
        }

        $where_clause = implode(' AND ', $where);

        // Count query
        if ($args['count_only']) {
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table} t
                    LEFT JOIN {$recruiters_table} r ON t.recruiter_id = r.id
                    WHERE {$where_clause}",
                    $params
                )
            );
            return intval($count);
        }

        // Validate orderby
        $valid_orderby = array('due_date', 'priority', 'created_at', 'title', 'task_type', 'status');
        $orderby = in_array($args['orderby'], $valid_orderby) ? $args['orderby'] : 'due_date';

        // Priority needs special ordering
        if ($orderby === 'priority') {
            $orderby = "FIELD(t.priority, 'urgent', 'high', 'medium', 'low')";
        } else {
            $orderby = 't.' . $orderby;
        }

        $order = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';
        $limit = intval($args['limit']);
        $offset = intval($args['offset']);

        $tasks = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t.*,
                    r.name as recruiter_name,
                    r.email as recruiter_email,
                    r.linkedin_url as recruiter_linkedin,
                    r.company as recruiter_company
                FROM {$this->table} t
                LEFT JOIN {$recruiters_table} r ON t.recruiter_id = r.id
                WHERE {$where_clause}
                ORDER BY {$orderby} {$order}
                LIMIT %d OFFSET %d",
                array_merge($params, array($limit, $offset))
            ),
            ARRAY_A
        );

        // Add computed fields
        foreach ($tasks as &$task) {
            $task['is_overdue'] = $this->is_overdue($task);
            $task['due_label'] = $this->get_due_label($task['due_date']);
        }

        return $tasks;
    }

    /**
     * Create a new task
     *
     * @param int   $user_id User ID
     * @param array $data    Task data
     * @return int|WP_Error Task ID or error
     */
    public function create($user_id, $data) {
        global $wpdb;

        // Validate required fields
        if (empty($data['title'])) {
            return new WP_Error('missing_title', 'Task title is required.');
        }

        // Validate type
        $type = isset($data['type']) ? $data['type'] : 'custom';
        if (!in_array($type, $this->valid_types)) {
            $type = 'custom';
        }

        // Validate priority
        $priority = isset($data['priority']) ? $data['priority'] : 'medium';
        if (!in_array($priority, $this->valid_priorities)) {
            $priority = 'medium';
        }

        // Validate status
        $status = isset($data['status']) ? $data['status'] : 'pending';
        if (!in_array($status, $this->valid_statuses)) {
            $status = 'pending';
        }

        // Prepare due date
        $due_date = null;
        if (!empty($data['due_date'])) {
            $due_date = date('Y-m-d H:i:s', strtotime($data['due_date']));
        }

        $insert_data = array(
            'user_id'            => $user_id,
            'recruiter_id'       => isset($data['recruiter_id']) ? intval($data['recruiter_id']) : null,
            'post_id'            => isset($data['post_id']) ? intval($data['post_id']) : null,
            'enrollment_id'      => isset($data['enrollment_id']) ? intval($data['enrollment_id']) : null,
            'step_index'         => isset($data['step_index']) ? intval($data['step_index']) : null,
            'task_type'          => $type,
            'title'              => sanitize_text_field($data['title']),
            'description'        => isset($data['description']) ? sanitize_textarea_field($data['description']) : null,
            'pre_filled_subject' => isset($data['pre_filled_subject']) ? sanitize_text_field($data['pre_filled_subject']) : null,
            'pre_filled_content' => isset($data['pre_filled_content']) ? wp_kses_post($data['pre_filled_content']) : null,
            'due_date'           => $due_date,
            'priority'           => $priority,
            'status'             => $status,
            'created_at'         => current_time('mysql')
        );

        $result = $wpdb->insert($this->table, $insert_data);

        if ($result === false) {
            return new WP_Error('db_error', 'Failed to create task.');
        }

        return $wpdb->insert_id;
    }

    /**
     * Update a task
     *
     * @param int   $task_id Task ID
     * @param int   $user_id User ID for ownership check
     * @param array $data    Updated data
     * @return bool|WP_Error
     */
    public function update($task_id, $user_id, $data) {
        global $wpdb;

        // Verify ownership
        $task = $this->get($task_id);
        if (!$task || $task['user_id'] != $user_id) {
            return new WP_Error('not_found', 'Task not found.');
        }

        $update_data = array();

        if (isset($data['title'])) {
            $update_data['title'] = sanitize_text_field($data['title']);
        }

        if (isset($data['description'])) {
            $update_data['description'] = sanitize_textarea_field($data['description']);
        }

        if (isset($data['type']) && in_array($data['type'], $this->valid_types)) {
            $update_data['task_type'] = $data['type'];
        }

        if (isset($data['priority']) && in_array($data['priority'], $this->valid_priorities)) {
            $update_data['priority'] = $data['priority'];
        }

        if (isset($data['status']) && in_array($data['status'], $this->valid_statuses)) {
            $update_data['status'] = $data['status'];

            // Set completed_at if completing
            if ($data['status'] === 'completed' && $task['status'] !== 'completed') {
                $update_data['completed_at'] = current_time('mysql');
            }
        }

        if (isset($data['due_date'])) {
            $update_data['due_date'] = !empty($data['due_date'])
                ? date('Y-m-d H:i:s', strtotime($data['due_date']))
                : null;
        }

        if (isset($data['pre_filled_subject'])) {
            $update_data['pre_filled_subject'] = sanitize_text_field($data['pre_filled_subject']);
        }

        if (isset($data['pre_filled_content'])) {
            $update_data['pre_filled_content'] = wp_kses_post($data['pre_filled_content']);
        }

        if (empty($update_data)) {
            return true; // Nothing to update
        }

        $result = $wpdb->update(
            $this->table,
            $update_data,
            array('id' => $task_id)
        );

        if ($result === false) {
            return new WP_Error('db_error', 'Failed to update task.');
        }

        return true;
    }

    /**
     * Delete a task
     *
     * @param int $task_id Task ID
     * @param int $user_id User ID for ownership check
     * @return bool|WP_Error
     */
    public function delete($task_id, $user_id) {
        global $wpdb;

        // Verify ownership
        $task = $this->get($task_id);
        if (!$task || $task['user_id'] != $user_id) {
            return new WP_Error('not_found', 'Task not found.');
        }

        $result = $wpdb->delete($this->table, array('id' => $task_id));

        if ($result === false) {
            return new WP_Error('db_error', 'Failed to delete task.');
        }

        return true;
    }

    /**
     * Complete a task
     *
     * @param int    $task_id Task ID
     * @param int    $user_id User ID
     * @param string $notes   Optional completion notes
     * @return bool|WP_Error
     */
    public function complete($task_id, $user_id, $notes = '') {
        global $wpdb;

        // Verify ownership
        $task = $this->get($task_id);
        if (!$task || $task['user_id'] != $user_id) {
            return new WP_Error('not_found', 'Task not found.');
        }

        if ($task['status'] === 'completed') {
            return new WP_Error('already_completed', 'Task is already completed.');
        }

        $result = $wpdb->update(
            $this->table,
            array(
                'status'       => 'completed',
                'completed_at' => current_time('mysql')
            ),
            array('id' => $task_id)
        );

        if ($result === false) {
            return new WP_Error('db_error', 'Failed to complete task.');
        }

        // If task is part of a sequence, record in enrollment history
        if (!empty($task['enrollment_id'])) {
            $this->record_enrollment_action($task, 'executed', $notes);
        }

        return true;
    }

    /**
     * Skip a task
     *
     * @param int    $task_id Task ID
     * @param int    $user_id User ID
     * @param string $reason  Optional skip reason
     * @return bool|WP_Error
     */
    public function skip($task_id, $user_id, $reason = '') {
        global $wpdb;

        // Verify ownership
        $task = $this->get($task_id);
        if (!$task || $task['user_id'] != $user_id) {
            return new WP_Error('not_found', 'Task not found.');
        }

        if ($task['status'] === 'completed' || $task['status'] === 'skipped') {
            return new WP_Error('invalid_status', 'Task cannot be skipped.');
        }

        $result = $wpdb->update(
            $this->table,
            array(
                'status'       => 'skipped',
                'completed_at' => current_time('mysql')
            ),
            array('id' => $task_id)
        );

        if ($result === false) {
            return new WP_Error('db_error', 'Failed to skip task.');
        }

        // If task is part of a sequence, record in enrollment history
        if (!empty($task['enrollment_id'])) {
            $this->record_enrollment_action($task, 'skipped', $reason);
        }

        return true;
    }

    /**
     * Snooze a task (push due date forward)
     *
     * @param int    $task_id   Task ID
     * @param int    $user_id   User ID
     * @param string $snooze_to New due date or relative string (e.g., '+1 day', 'tomorrow')
     * @return bool|WP_Error
     */
    public function snooze($task_id, $user_id, $snooze_to) {
        global $wpdb;

        // Verify ownership
        $task = $this->get($task_id);
        if (!$task || $task['user_id'] != $user_id) {
            return new WP_Error('not_found', 'Task not found.');
        }

        if ($task['status'] === 'completed' || $task['status'] === 'skipped') {
            return new WP_Error('invalid_status', 'Completed/skipped tasks cannot be snoozed.');
        }

        // Parse snooze time
        $new_due = strtotime($snooze_to);
        if (!$new_due) {
            return new WP_Error('invalid_date', 'Invalid snooze time.');
        }

        $result = $wpdb->update(
            $this->table,
            array('due_date' => date('Y-m-d H:i:s', $new_due)),
            array('id' => $task_id)
        );

        if ($result === false) {
            return new WP_Error('db_error', 'Failed to snooze task.');
        }

        return true;
    }

    /**
     * Get task counts by status
     *
     * @param int $user_id User ID
     * @return array
     */
    public function get_status_counts($user_id) {
        global $wpdb;

        $counts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT status, COUNT(*) as count
                FROM {$this->table}
                WHERE user_id = %d
                GROUP BY status",
                $user_id
            ),
            ARRAY_A
        );

        $result = array(
            'pending'     => 0,
            'in_progress' => 0,
            'completed'   => 0,
            'skipped'     => 0,
            'total'       => 0
        );

        foreach ($counts as $count) {
            $result[$count['status']] = intval($count['count']);
            $result['total'] += intval($count['count']);
        }

        // Get overdue count
        $result['overdue'] = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table}
                WHERE user_id = %d
                AND status IN ('pending', 'in_progress')
                AND due_date < %s",
                $user_id,
                current_time('mysql')
            )
        );

        // Get due today count
        $result['due_today'] = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table}
                WHERE user_id = %d
                AND status IN ('pending', 'in_progress')
                AND DATE(due_date) = %s",
                $user_id,
                date('Y-m-d')
            )
        );

        return $result;
    }

    /**
     * Get tasks for a specific recruiter
     *
     * @param int   $user_id      User ID
     * @param int   $recruiter_id Recruiter ID
     * @param array $args         Additional filters
     * @return array
     */
    public function get_recruiter_tasks($user_id, $recruiter_id, $args = array()) {
        $args['recruiter_id'] = $recruiter_id;
        return $this->get_user_tasks($user_id, $args);
    }

    /**
     * Get tasks for a specific enrollment
     *
     * @param int $enrollment_id Enrollment ID
     * @return array
     */
    public function get_enrollment_tasks($enrollment_id) {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table}
                WHERE enrollment_id = %d
                ORDER BY step_index ASC, created_at ASC",
                $enrollment_id
            ),
            ARRAY_A
        );
    }

    /**
     * Bulk update task statuses
     *
     * @param array  $task_ids Task IDs
     * @param int    $user_id  User ID
     * @param string $status   New status
     * @return array Results
     */
    public function bulk_update_status($task_ids, $user_id, $status) {
        if (!in_array($status, $this->valid_statuses)) {
            return array('success' => 0, 'failed' => count($task_ids));
        }

        $success = 0;
        $failed = 0;

        foreach ($task_ids as $task_id) {
            $result = $this->update($task_id, $user_id, array('status' => $status));
            if (is_wp_error($result)) {
                $failed++;
            } else {
                $success++;
            }
        }

        return array('success' => $success, 'failed' => $failed);
    }

    /**
     * Delete all tasks for a recruiter
     *
     * @param int $user_id      User ID
     * @param int $recruiter_id Recruiter ID
     * @return int Number deleted
     */
    public function delete_recruiter_tasks($user_id, $recruiter_id) {
        global $wpdb;

        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table} WHERE user_id = %d AND recruiter_id = %d",
                $user_id,
                $recruiter_id
            )
        );
    }

    /**
     * Delete tasks for an enrollment
     *
     * @param int $enrollment_id Enrollment ID
     * @return int Number deleted
     */
    public function delete_enrollment_tasks($enrollment_id) {
        global $wpdb;

        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table} WHERE enrollment_id = %d",
                $enrollment_id
            )
        );
    }

    /**
     * Check if a task is overdue
     *
     * @param array $task Task data
     * @return bool
     */
    private function is_overdue($task) {
        if (empty($task['due_date'])) {
            return false;
        }

        if (in_array($task['status'], array('completed', 'skipped'))) {
            return false;
        }

        return strtotime($task['due_date']) < time();
    }

    /**
     * Get human-readable due date label
     *
     * @param string $due_date Due date
     * @return string
     */
    private function get_due_label($due_date) {
        if (empty($due_date)) {
            return 'No due date';
        }

        $due_timestamp = strtotime($due_date);
        $now = time();
        $today_start = strtotime('today');
        $today_end = strtotime('tomorrow') - 1;
        $tomorrow_end = strtotime('+2 days') - 1;

        if ($due_timestamp < $now) {
            $diff = human_time_diff($due_timestamp, $now);
            return 'Overdue by ' . $diff;
        }

        if ($due_timestamp >= $today_start && $due_timestamp <= $today_end) {
            return 'Due today at ' . date('g:i A', $due_timestamp);
        }

        if ($due_timestamp > $today_end && $due_timestamp <= $tomorrow_end) {
            return 'Due tomorrow at ' . date('g:i A', $due_timestamp);
        }

        if ($due_timestamp <= strtotime('+7 days')) {
            return 'Due ' . date('l', $due_timestamp) . ' at ' . date('g:i A', $due_timestamp);
        }

        return 'Due ' . date('M j, Y', $due_timestamp);
    }

    /**
     * Record action in enrollment history
     *
     * @param array  $task   Task data
     * @param string $action Action taken
     * @param string $notes  Optional notes
     */
    private function record_enrollment_action($task, $action, $notes = '') {
        global $wpdb;

        $history_table = $wpdb->prefix . 'sffc_crm_enrollment_history';

        $wpdb->insert(
            $history_table,
            array(
                'enrollment_id' => $task['enrollment_id'],
                'step_index'    => $task['step_index'],
                'step_type'     => $task['task_type'],
                'action_taken'  => $action,
                'task_id'       => $task['id'],
                'notes'         => $notes,
                'executed_at'   => current_time('mysql')
            )
        );
    }

    /**
     * Create a quick task (shortcut for common task types)
     *
     * @param int    $user_id      User ID
     * @param int    $recruiter_id Recruiter ID
     * @param string $type         Task type
     * @param string $due_when     When due (today, tomorrow, +3 days, etc.)
     * @return int|WP_Error
     */
    public function create_quick_task($user_id, $recruiter_id, $type, $due_when = 'tomorrow') {
        // Get recruiter name for title
        global $wpdb;
        $recruiters_table = $wpdb->prefix . 'sffc_crm_recruiters';
        $recruiter = $wpdb->get_row(
            $wpdb->prepare("SELECT name, company FROM {$recruiters_table} WHERE id = %d", $recruiter_id),
            ARRAY_A
        );

        $recruiter_label = $recruiter ? $recruiter['name'] : 'Recruiter';

        // Type-specific titles (matching database enum types)
        $titles = array(
            'follow_up'        => "Follow up with {$recruiter_label}",
            'send_email'       => "Send email to {$recruiter_label}",
            'linkedin_message' => "Send LinkedIn message to {$recruiter_label}",
            'linkedin_connect' => "Connect with {$recruiter_label} on LinkedIn",
            'research'         => "Research {$recruiter_label}" . ($recruiter ? " at {$recruiter['company']}" : ''),
            'interview_prep'   => "Prepare for interview with {$recruiter_label}",
            'custom'           => "Task for {$recruiter_label}"
        );

        $title = isset($titles[$type]) ? $titles[$type] : $titles['custom'];

        // Parse due date
        $due_date = date('Y-m-d 09:00:00', strtotime($due_when));

        return $this->create($user_id, array(
            'recruiter_id' => $recruiter_id,
            'type'         => $type,
            'title'        => $title,
            'due_date'     => $due_date,
            'priority'     => 'medium'
        ));
    }

    /**
     * Get valid task types (matching database enum)
     *
     * @return array
     */
    public function get_task_types() {
        return array(
            'follow_up'        => array('label' => 'Follow Up', 'icon' => 'refresh'),
            'send_email'       => array('label' => 'Send Email', 'icon' => 'email'),
            'linkedin_message' => array('label' => 'LinkedIn Message', 'icon' => 'linkedin'),
            'linkedin_connect' => array('label' => 'LinkedIn Connect', 'icon' => 'linkedin'),
            'research'         => array('label' => 'Research', 'icon' => 'search'),
            'interview_prep'   => array('label' => 'Interview Prep', 'icon' => 'calendar'),
            'custom'           => array('label' => 'Other', 'icon' => 'marker')
        );
    }

    /**
     * Get valid priorities
     *
     * @return array
     */
    public function get_priorities() {
        return array(
            'low'    => array('label' => 'Low', 'color' => '#64748b'),
            'medium' => array('label' => 'Medium', 'color' => '#3b82f6'),
            'high'   => array('label' => 'High', 'color' => '#f59e0b'),
            'urgent' => array('label' => 'Urgent', 'color' => '#ef4444')
        );
    }
}
