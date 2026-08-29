<?php
/**
 * CRM Sequence Processor
 *
 * Background processor for sequence steps using WP Cron.
 * Processes due enrollments and creates tasks for manual steps.
 *
 * @package SennaCareers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_Sequence_Processor {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Cron hook name
     */
    const CRON_HOOK = 'sffc_crm_process_sequences';

    /**
     * Cron interval name
     */
    const CRON_INTERVAL = 'sffc_crm_fifteen_minutes';

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Add custom cron interval
        add_filter('cron_schedules', [$this, 'add_cron_interval']);

        // Register cron hook
        add_action(self::CRON_HOOK, [$this, 'process_due_enrollments']);

        // Schedule cron on plugin activation
        register_activation_hook(SFFC_PLUGIN_FILE, [$this, 'schedule_cron']);

        // Unschedule cron on plugin deactivation
        register_deactivation_hook(SFFC_PLUGIN_FILE, [$this, 'unschedule_cron']);

        // Ensure cron is scheduled
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            $this->schedule_cron();
        }
    }

    /**
     * Add custom cron interval (15 minutes)
     */
    public function add_cron_interval($schedules) {
        $schedules[self::CRON_INTERVAL] = [
            'interval' => 15 * MINUTE_IN_SECONDS,
            'display' => __('Every 15 Minutes', 'senna-careers'),
        ];
        return $schedules;
    }

    /**
     * Schedule cron job
     */
    public function schedule_cron() {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), self::CRON_INTERVAL, self::CRON_HOOK);
        }
    }

    /**
     * Unschedule cron job
     */
    public function unschedule_cron() {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }

    /**
     * Process due enrollments
     *
     * Main cron job that processes all due sequence steps.
     */
    public function process_due_enrollments() {
        global $wpdb;

        // Get due enrollments
        $enrollment_model = new SFFC_CRM_Sequence_Enrollment();
        $enrollments = $enrollment_model->get_due_enrollments(50);

        if (empty($enrollments)) {
            return;
        }

        $processed = 0;
        $errors = 0;

        foreach ($enrollments as $enrollment) {
            try {
                $result = $this->process_enrollment_step($enrollment);
                if ($result) {
                    $processed++;
                } else {
                    $errors++;
                }
            } catch (Exception $e) {
                error_log('CRM Sequence Processor Error: ' . $e->getMessage());
                $errors++;
            }
        }

        // Log processing results
        if ($processed > 0 || $errors > 0) {
            error_log(sprintf(
                'CRM Sequence Processor: Processed %d enrollments, %d errors',
                $processed,
                $errors
            ));
        }
    }

    /**
     * Process a single enrollment step
     *
     * @param array $enrollment Enrollment data
     * @return bool Success status
     */
    private function process_enrollment_step($enrollment) {
        global $wpdb;

        $enrollment_id = $enrollment['id'];
        $sequence_id = $enrollment['sequence_id'];
        $current_step_index = $enrollment['current_step_index'];
        $user_id = $enrollment['user_id'];
        $recruiter_id = $enrollment['recruiter_id'];

        // Get sequence steps
        $sequence_model = new SFFC_CRM_Sequence();
        $steps = $sequence_model->get_steps($sequence_id);

        if (empty($steps) || $current_step_index >= count($steps)) {
            // No more steps, mark as completed
            $enrollment_model = new SFFC_CRM_Sequence_Enrollment();
            $enrollment_model->complete($enrollment_id);
            return true;
        }

        $step = $steps[$current_step_index];

        // Process based on step type
        switch ($step['step_type']) {
            case 'manual_email':
            case 'linkedin_message':
            case 'linkedin_connect':
                // Create a task for the user
                return $this->create_step_task($enrollment, $step);

            case 'task':
                // Create a custom task
                return $this->create_custom_task($enrollment, $step);

            case 'wait':
                // Just advance to next step
                return $this->advance_enrollment($enrollment_id);

            case 'auto_email':
                // Not supported (we don't auto-send emails)
                // Treat as manual email
                return $this->create_step_task($enrollment, $step);

            default:
                return $this->advance_enrollment($enrollment_id);
        }
    }

    /**
     * Create a task for a sequence step
     *
     * @param array $enrollment Enrollment data
     * @param array $step Step data
     * @return bool Success status
     */
    private function create_step_task($enrollment, $step) {
        $task_model = new SFFC_CRM_Task();
        $template_model = new SFFC_CRM_Template();

        // Get recruiter info
        global $wpdb;
        $recruiters_table = $wpdb->prefix . 'sffc_crm_recruiters';
        $recruiter = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$recruiters_table} WHERE id = %d", $enrollment['recruiter_id']),
            ARRAY_A
        );

        if (!$recruiter) {
            return false;
        }

        // Determine task type based on step type (must match database enum)
        $type_map = [
            'manual_email'     => 'send_email',
            'linkedin_message' => 'linkedin_message',
            'linkedin_connect' => 'linkedin_connect',
            'auto_email'       => 'send_email',
        ];
        $task_type = isset($type_map[$step['step_type']]) ? $type_map[$step['step_type']] : 'follow_up';

        // Build task title
        $step_number = $step['step_order'] + 1;
        $recruiter_name = $recruiter['name'];

        $title_templates = [
            'send_email'       => "Step {$step_number}: Send email to {$recruiter_name}",
            'linkedin_message' => "Step {$step_number}: Message {$recruiter_name} on LinkedIn",
            'linkedin_connect' => "Step {$step_number}: Connect with {$recruiter_name} on LinkedIn",
        ];
        $title = isset($title_templates[$task_type]) ? $title_templates[$task_type] : "Step {$step_number}: Follow up with {$recruiter_name}";

        // Build pre-filled content
        $subject = $step['subject'] ?: '';
        $content = $step['content'] ?: '';

        // If template_id provided, render the template
        if (!empty($step['template_id'])) {
            // Get personalization data
            $personalization = !empty($enrollment['personalization_data'])
                ? json_decode($enrollment['personalization_data'], true)
                : [];

            // Build variables
            $user = get_userdata($enrollment['user_id']);
            $user_meta = get_user_meta($enrollment['user_id']);

            $variables = array_merge($personalization, [
                'recruiter_name' => $recruiter['name'],
                'recruiter_first_name' => $recruiter['first_name'] ?: explode(' ', $recruiter['name'])[0],
                'recruiter_firm' => $recruiter['firm'] ?: '',
                'recruiter_company' => $recruiter['company'] ?: '',
                'recruiter_title' => $recruiter['title'] ?: '',
                'candidate_name' => $user->display_name,
                'candidate_first_name' => $user->first_name ?: explode(' ', $user->display_name)[0],
                'candidate_email' => $user->user_email,
                'candidate_current_role' => isset($user_meta['current_role'][0]) ? $user_meta['current_role'][0] : '',
                'candidate_company' => isset($user_meta['current_company'][0]) ? $user_meta['current_company'][0] : '',
            ]);

            // Add post variables if available
            if (!empty($enrollment['post_id'])) {
                $post_model = new SFFC_CRM_Post();
                $post = $post_model->get($enrollment['post_id']);
                if ($post) {
                    $variables['role_title'] = $post['role_title'] ?: '';
                    $variables['company'] = $post['company'] ?: '';
                    $variables['location'] = $post['location'] ?: '';
                }
            }

            $rendered = $template_model->render($step['template_id'], $variables);
            if ($rendered) {
                $subject = $rendered['subject'] ?: $subject;
                $content = $rendered['content'] ?: $content;
            }
        }

        // Create the task
        $task_data = [
            'recruiter_id'       => $enrollment['recruiter_id'],
            'post_id'            => $enrollment['post_id'],
            'enrollment_id'      => $enrollment['id'],
            'step_index'         => $enrollment['current_step_index'],
            'type'               => $task_type,
            'title'              => $title,
            'description'        => $step['task_description'] ?: "Complete this step in your outreach sequence.",
            'pre_filled_subject' => $subject,
            'pre_filled_content' => $content,
            'due_date'           => current_time('mysql'),
            'priority'           => 'medium',
        ];

        $task_id = $task_model->create($enrollment['user_id'], $task_data);

        if (is_wp_error($task_id)) {
            error_log('Failed to create sequence task: ' . $task_id->get_error_message());
            return false;
        }

        // Record in enrollment history
        $this->record_step_action($enrollment['id'], $enrollment['current_step_index'], $step['step_type'], 'task_created', $task_id);

        // Advance to next step
        return $this->advance_enrollment($enrollment['id']);
    }

    /**
     * Create a custom task from a task step
     *
     * @param array $enrollment Enrollment data
     * @param array $step Step data
     * @return bool Success status
     */
    private function create_custom_task($enrollment, $step) {
        $task_model = new SFFC_CRM_Task();

        // Get recruiter info
        global $wpdb;
        $recruiters_table = $wpdb->prefix . 'sffc_crm_recruiters';
        $recruiter = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$recruiters_table} WHERE id = %d", $enrollment['recruiter_id']),
            ARRAY_A
        );

        $recruiter_name = $recruiter ? $recruiter['name'] : 'Recruiter';

        $task_data = [
            'recruiter_id'  => $enrollment['recruiter_id'],
            'post_id'       => $enrollment['post_id'],
            'enrollment_id' => $enrollment['id'],
            'step_index'    => $enrollment['current_step_index'],
            'type'          => 'other',
            'title'         => $step['task_title'] ?: "Task for {$recruiter_name}",
            'description'   => $step['task_description'] ?: '',
            'due_date'      => current_time('mysql'),
            'priority'      => 'medium',
        ];

        $task_id = $task_model->create($enrollment['user_id'], $task_data);

        if (is_wp_error($task_id)) {
            error_log('Failed to create custom task: ' . $task_id->get_error_message());
            return false;
        }

        // Record in enrollment history
        $this->record_step_action($enrollment['id'], $enrollment['current_step_index'], 'task', 'task_created', $task_id);

        // Advance to next step
        return $this->advance_enrollment($enrollment['id']);
    }

    /**
     * Advance enrollment to next step
     *
     * @param int $enrollment_id Enrollment ID
     * @return bool Success status
     */
    private function advance_enrollment($enrollment_id) {
        $enrollment_model = new SFFC_CRM_Sequence_Enrollment();
        $result = $enrollment_model->advance_to_next_step($enrollment_id);
        return !is_wp_error($result);
    }

    /**
     * Record step action in enrollment history
     *
     * @param int    $enrollment_id Enrollment ID
     * @param int    $step_index Step index
     * @param string $step_type Step type
     * @param string $action Action taken
     * @param int    $task_id Task ID (optional)
     */
    private function record_step_action($enrollment_id, $step_index, $step_type, $action, $task_id = null) {
        global $wpdb;

        $history_table = $wpdb->prefix . 'sffc_crm_enrollment_history';

        $wpdb->insert($history_table, [
            'enrollment_id' => $enrollment_id,
            'step_index'    => $step_index,
            'step_type'     => $step_type,
            'action_taken'  => $action,
            'task_id'       => $task_id,
            'executed_at'   => current_time('mysql'),
        ]);
    }

    /**
     * Manually trigger processing (for testing or immediate execution)
     *
     * @return array Processing results
     */
    public function trigger_manual_processing() {
        $enrollment_model = new SFFC_CRM_Sequence_Enrollment();
        $enrollments = $enrollment_model->get_due_enrollments(50);

        $results = [
            'total' => count($enrollments),
            'processed' => 0,
            'errors' => 0,
        ];

        foreach ($enrollments as $enrollment) {
            try {
                if ($this->process_enrollment_step($enrollment)) {
                    $results['processed']++;
                } else {
                    $results['errors']++;
                }
            } catch (Exception $e) {
                $results['errors']++;
            }
        }

        return $results;
    }

    /**
     * Get processing status and statistics
     *
     * @return array Status information
     */
    public function get_status() {
        global $wpdb;

        $enrollments_table = $wpdb->prefix . 'sffc_crm_sequence_enrollments';

        // Count active enrollments
        $active_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$enrollments_table} WHERE status = 'active'"
        );

        // Count due enrollments
        $due_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$enrollments_table}
                WHERE status = 'active' AND next_step_at <= %s",
                current_time('mysql')
            )
        );

        // Get next scheduled run
        $next_run = wp_next_scheduled(self::CRON_HOOK);

        return [
            'active_enrollments' => intval($active_count),
            'due_enrollments' => intval($due_count),
            'next_scheduled_run' => $next_run ? date('Y-m-d H:i:s', $next_run) : null,
            'cron_scheduled' => $next_run !== false,
        ];
    }

    /**
     * Get user's pending tasks from sequences
     *
     * @param int $user_id User ID
     * @return array Pending tasks
     */
    public function get_user_pending_sequence_tasks($user_id) {
        $task_model = new SFFC_CRM_Task();

        return $task_model->get_user_tasks($user_id, [
            'status' => ['pending', 'in_progress'],
            'orderby' => 'due_date',
            'order' => 'ASC',
            'limit' => 20,
        ]);
    }
}

// Initialize
SFFC_CRM_Sequence_Processor::get_instance();
