<?php
/**
 * Internship Alert Queue
 *
 * Queues and processes internship alert emails in background batches.
 *
 * @package SennaCareers
 * @since 7.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_Internship_Alert_Queue {

    private static $instance = null;

    const CRON_HOOK = 'sffc_crm_process_internship_alert_queue';
    const CRON_INTERVAL = 'sffc_crm_every_five_minutes';
    const LOCK_OPTION = 'sffc_crm_internship_alert_queue_lock';
    const LOCK_TTL = 240;
    const DEFAULT_BATCH_SIZE = 25;
    const DEFAULT_SPACING_MINUTES = 5;
    const DEFAULT_INITIAL_DELAY_MINUTES = 0;
    const MAX_ATTEMPTS = 5;

    private $queue_table;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->queue_table = $wpdb->prefix . 'sffc_crm_internship_alert_queue';

        $this->init_hooks();
    }

    private function init_hooks() {
        add_filter('cron_schedules', [$this, 'register_cron_schedule']);
        add_action('init', [$this, 'schedule_cron']);
        add_action(self::CRON_HOOK, [$this, 'process_queue']);
    }

    public function register_cron_schedule($schedules) {
        $intervals = [
            1  => __('Every Minute', 'senna-finance'),
            5  => __('Every 5 Minutes', 'senna-finance'),
            10 => __('Every 10 Minutes', 'senna-finance'),
            15 => __('Every 15 Minutes', 'senna-finance'),
        ];

        foreach ($intervals as $minutes => $label) {
            $schedules[$this->get_interval_key($minutes)] = [
                'interval' => $minutes * MINUTE_IN_SECONDS,
                'display'  => $label,
            ];
        }

        return $schedules;
    }

    public function schedule_cron() {
        if (function_exists('sffc_crm_internship_alerts_disabled') && sffc_crm_internship_alerts_disabled()) {
            wp_clear_scheduled_hook(self::CRON_HOOK);
            return;
        }

        if ($this->is_paused() || $this->get_transport() !== 'wp_mail') {
            wp_clear_scheduled_hook(self::CRON_HOOK);
            return;
        }

        $desired_recurrence = $this->get_interval_key($this->get_batch_spacing_minutes());
        $existing_event = function_exists('wp_get_scheduled_event') ? wp_get_scheduled_event(self::CRON_HOOK) : false;

        if ($existing_event && !empty($existing_event->schedule) && $existing_event->schedule !== $desired_recurrence) {
            wp_clear_scheduled_hook(self::CRON_HOOK);
            $existing_event = false;
        }

        if (!$existing_event && !wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + MINUTE_IN_SECONDS, $desired_recurrence, self::CRON_HOOK);
        }
    }

    public function enqueue_post_alerts($post_id) {
        $post_id = (int) $post_id;
        if (function_exists('sffc_crm_internship_alerts_disabled') && sffc_crm_internship_alerts_disabled()) {
            return [
                'queued' => 0,
                'matched' => 0,
                'digest_matched' => 0,
                'disabled' => true,
            ];
        }

        if ($post_id <= 0) {
            return [
                'queued' => 0,
                'matched' => 0,
                'digest_matched' => 0,
            ];
        }

        $post_model = new SFFC_CRM_Post();
        $post = $post_model->get($post_id);

        if (!$post || !sffc_crm_alert_post_is_internship($post)) {
            return [
                'queued' => 0,
                'matched' => 0,
                'digest_matched' => 0,
            ];
        }

        $post['post_group_ids'] = sffc_crm_get_post_group_ids($post_id);

        $users = get_users([
            'fields'     => ['ID', 'display_name', 'user_email'],
        ]);

        if (empty($users)) {
            return [
                'queued' => 0,
                'matched' => 0,
                'digest_matched' => 0,
            ];
        }

        $matched = 0;
        $queued = 0;
        $digest_matched = 0;

        foreach ($users as $user) {
            if (function_exists('sffc_crm_user_is_excluded') && sffc_crm_user_is_excluded($user->ID)) {
                continue;
            }

            $prefs = sffc_crm_get_alert_preferences($user->ID);
            if (!sffc_crm_alert_matches_post($prefs, $post)) {
                continue;
            }

            $matched++;

            if (sffc_crm_user_has_instant_alert_access($user->ID)) {
                if ($this->enqueue_recipient($post_id, $user->ID)) {
                    $queued++;
                }
            } else {
                $digest_matched++;
            }
        }

        if ($queued > 0 && !wp_next_scheduled(self::CRON_HOOK)) {
            if ($this->get_transport() === 'sendgrid_api') {
                $this->schedule_pending_batches($post_id);
            } else {
                $this->schedule_cron();
            }
        }

        if ($digest_matched > 0 && class_exists('SFFC_CRM_Free_Alert_Digest')) {
            SFFC_CRM_Free_Alert_Digest::get_instance()->schedule_event();
        }

        update_option('sffc_crm_last_internship_alert_queue_run', [
            'post_id'    => $post_id,
            'matched'    => $matched,
            'queued'     => $queued,
            'digest_matched' => $digest_matched,
            'queued_at'  => current_time('mysql'),
        ], false);

        return [
            'queued' => $queued,
            'matched' => $matched,
            'digest_matched' => $digest_matched,
        ];
    }

    private function enqueue_recipient($post_id, $user_id) {
        global $wpdb;

        $now = current_time('mysql');
        $result = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$this->queue_table}
                (post_id, user_id, alert_type, status, delivery_transport, attempts, max_attempts, next_attempt_at, created_at, updated_at)
             VALUES (%d, %d, %s, %s, %s, %d, %d, %s, %s, %s)
             ON DUPLICATE KEY UPDATE id = id",
            $post_id,
            $user_id,
            'internship',
            'pending',
            $this->get_transport(),
            0,
            $this->get_max_attempts(),
            $now,
            $now,
            $now
        ));

        return $result === 1;
    }

    public function process_queue() {
        if (
            (function_exists('sffc_crm_alert_email_sending_disabled') && sffc_crm_alert_email_sending_disabled())
            || (function_exists('sffc_crm_internship_alerts_disabled') && sffc_crm_internship_alerts_disabled())
        ) {
            return;
        }

        if ($this->is_paused()) {
            return;
        }

        if ($this->get_transport() !== 'wp_mail') {
            return;
        }

        if (!$this->acquire_lock()) {
            return;
        }

        try {
            $jobs = $this->claim_batch($this->get_batch_size());

            if (empty($jobs)) {
                return;
            }

            foreach ($jobs as $job) {
                $this->process_job($job);
            }

            update_option('sffc_crm_last_internship_alert_processed_at', current_time('mysql'), false);
        } finally {
            $this->release_lock();
        }
    }

    private function get_batch_size() {
        $batch_size = (int) get_option('sffc_crm_internship_alert_batch_size', self::DEFAULT_BATCH_SIZE);
        $batch_size = (int) apply_filters('sffc_crm_internship_alert_batch_size', $batch_size);
        return max(1, min(1000, $batch_size));
    }

    private function get_batch_spacing_minutes() {
        $minutes = (int) get_option('sffc_crm_internship_alert_spacing_minutes', self::DEFAULT_SPACING_MINUTES);
        if (!in_array($minutes, [1, 5, 10, 15], true)) {
            $minutes = self::DEFAULT_SPACING_MINUTES;
        }

        return $minutes;
    }

    private function get_initial_delay_minutes() {
        $minutes = (int) get_option('sffc_crm_internship_alert_initial_delay_minutes', self::DEFAULT_INITIAL_DELAY_MINUTES);
        return max(0, min(1440, $minutes));
    }

    private function get_max_attempts() {
        $max_attempts = (int) get_option('sffc_crm_internship_alert_max_attempts', self::MAX_ATTEMPTS);
        return max(1, min(10, $max_attempts));
    }

    private function is_paused() {
        return (bool) get_option('sffc_crm_internship_alert_queue_paused', false);
    }

    private function get_interval_key($minutes) {
        return self::CRON_INTERVAL . '_' . (int) $minutes;
    }

    private function get_transport() {
        $transport = sanitize_key(get_option('sffc_crm_internship_alert_transport', 'sendgrid_api'));
        if (!in_array($transport, ['sendgrid_api', 'wp_mail'], true)) {
            $transport = 'sendgrid_api';
        }

        return $transport;
    }

    public function schedule_pending_batches($post_id = null) {
        if (
            (function_exists('sffc_crm_alert_email_sending_disabled') && sffc_crm_alert_email_sending_disabled())
            || (function_exists('sffc_crm_internship_alerts_disabled') && sffc_crm_internship_alerts_disabled())
        ) {
            return [
                'submitted' => 0,
                'batches' => 0,
                'disabled' => true,
            ];
        }

        if ($this->is_paused() || $this->get_transport() !== 'sendgrid_api') {
            return [
                'submitted' => 0,
                'batches' => 0,
            ];
        }

        $sendgrid = class_exists('SFFC_CRM_SendGrid_Service') ? SFFC_CRM_SendGrid_Service::get_instance() : null;
        if (!$sendgrid || !$sendgrid->is_configured()) {
            return [
                'submitted' => 0,
                'batches' => 0,
            ];
        }

        global $wpdb;

        $where = "status IN ('pending', 'retry')";
        $params = [];
        if ($post_id !== null) {
            $where .= " AND post_id = %d";
            $params[] = (int) $post_id;
        }

        $sql = "SELECT * FROM {$this->queue_table} WHERE {$where} ORDER BY post_id ASC, created_at ASC, id ASC";
        $jobs = !empty($params)
            ? $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A)
            : $wpdb->get_results($sql, ARRAY_A);

        if (empty($jobs)) {
            return [
                'submitted' => 0,
                'batches' => 0,
            ];
        }

        $jobs_by_post = [];
        foreach ($jobs as $job) {
            $jobs_by_post[(int) $job['post_id']][] = $job;
        }

        $submitted = 0;
        $batches = 0;

        foreach ($jobs_by_post as $queued_post_id => $post_jobs) {
            $post_model = new SFFC_CRM_Post();
            $post = $post_model->get($queued_post_id);
            if (!$post || !sffc_crm_alert_post_is_internship($post)) {
                foreach ($post_jobs as $job) {
                    $this->mark_skipped((int) $job['id'], 'Post missing or no longer qualifies for internship alerts.');
                }
                continue;
            }

            $post['post_group_ids'] = sffc_crm_get_post_group_ids($queued_post_id);
            $valid_jobs = [];
            foreach ($post_jobs as $job) {
                $validation = $this->validate_job_for_delivery($job, $post);
                if (is_wp_error($validation)) {
                    $code = $validation->get_error_code();
                    if ($code === 'invalid_queue_payload') {
                        $this->mark_failed((int) ($job['id'] ?? 0), (int) ($job['attempts'] ?? 0), $validation->get_error_message());
                    } else {
                        $this->mark_skipped((int) $job['id'], $validation->get_error_message());
                    }
                    continue;
                }

                $valid_jobs[] = $validation;
            }

            if (empty($valid_jobs)) {
                continue;
            }

            $chunks = array_chunk($valid_jobs, $this->get_batch_size());
            $base_timestamp = current_time('timestamp') + ($this->get_initial_delay_minutes() * MINUTE_IN_SECONDS);
            $spacing_seconds = $this->get_batch_spacing_minutes() * MINUTE_IN_SECONDS;

            foreach ($chunks as $index => $chunk) {
                $send_at = $base_timestamp + ($index * $spacing_seconds);
                $first_payload = $chunk[0]['payload'];

                $recipients = array_map(function ($entry) use ($queued_post_id) {
                    return [
                        'email'    => $entry['payload']['recipient'],
                        'user_id'  => $entry['user']->ID,
                        'post_id'  => $queued_post_id,
                        'queue_id' => (int) $entry['job']['id'],
                    ];
                }, $chunk);

                $result = $sendgrid->send_scheduled_batch(
                    $first_payload['subject'],
                    $first_payload['body'],
                    $recipients,
                    $send_at
                );

                if (is_wp_error($result)) {
                    foreach ($chunk as $entry) {
                        $this->mark_failed((int) $entry['job']['id'], (int) ($entry['job']['attempts'] ?? 0), $result->get_error_message());
                    }
                    continue;
                }

                $batches++;
                $submitted += count($chunk);
                foreach ($chunk as $entry) {
                    $this->mark_scheduled(
                        (int) $entry['job']['id'],
                        date('Y-m-d H:i:s', $send_at),
                        $result['provider_reference'] ?? '',
                        'sendgrid_api'
                    );
                }
            }
        }

        update_option('sffc_crm_last_internship_alert_submission', [
            'submitted'    => $submitted,
            'batches'      => $batches,
            'submitted_at' => current_time('mysql'),
            'transport'    => 'sendgrid_api',
        ], false);

        return [
            'submitted' => $submitted,
            'batches'   => $batches,
        ];
    }

    private function claim_batch($limit) {
        global $wpdb;

        $now = current_time('mysql');
        $stale_cutoff = date('Y-m-d H:i:s', current_time('timestamp') - self::LOCK_TTL);

        $job_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id
             FROM {$this->queue_table}
             WHERE (
                    status = 'pending'
                    OR (status = 'processing' AND locked_at IS NOT NULL AND locked_at < %s)
                    OR (status = 'retry' AND next_attempt_at IS NOT NULL AND next_attempt_at <= %s)
                  )
             ORDER BY created_at ASC, id ASC
             LIMIT %d",
            $stale_cutoff,
            $now,
            $limit
        ));

        if (empty($job_ids)) {
            return [];
        }

        $job_ids = array_map('intval', $job_ids);
        $id_sql = implode(',', $job_ids);

        $updated = $wpdb->query(
            "UPDATE {$this->queue_table}
             SET status = 'processing', locked_at = '" . esc_sql($now) . "', updated_at = '" . esc_sql($now) . "'
             WHERE id IN ({$id_sql})
               AND (
                    status = 'pending'
                    OR status = 'retry'
                    OR (status = 'processing' AND locked_at IS NOT NULL AND locked_at < '" . esc_sql($stale_cutoff) . "')
               )"
        );

        if ($updated === false) {
            error_log('SFFC CRM Internship Alert Queue: failed to claim jobs: ' . $wpdb->last_error);
            return [];
        }

        return $wpdb->get_results(
            "SELECT * FROM {$this->queue_table}
             WHERE id IN ({$id_sql}) AND status = 'processing'
             ORDER BY created_at ASC, id ASC",
            ARRAY_A
        ) ?: [];
    }

    private function process_job($job) {
        $job_id = (int) ($job['id'] ?? 0);
        $post_id = (int) ($job['post_id'] ?? 0);
        $post_model = new SFFC_CRM_Post();
        $post = $post_model->get($post_id);
        if (!$post || !sffc_crm_alert_post_is_internship($post)) {
            $this->mark_skipped($job_id, 'Post missing or no longer qualifies for internship alerts.');
            return;
        }

        $post['post_group_ids'] = sffc_crm_get_post_group_ids($post_id);
        $validation = $this->validate_job_for_delivery($job, $post);
        if (is_wp_error($validation)) {
            $code = $validation->get_error_code();
            if ($code === 'invalid_queue_payload') {
                $this->mark_failed($job_id, (int) ($job['attempts'] ?? 0), $validation->get_error_message());
            } else {
                $this->mark_skipped($job_id, $validation->get_error_message());
            }
            return;
        }

        $sent = wp_mail(
            $validation['payload']['recipient'],
            $validation['payload']['subject'],
            $validation['payload']['body'],
            $validation['payload']['headers']
        );

        if ($sent) {
            $this->mark_sent($job_id);
            return;
        }

        $this->mark_failed($job_id, (int) ($job['attempts'] ?? 0), 'wp_mail returned false.');
    }

    private function mark_sent($job_id) {
        global $wpdb;

        $now = current_time('mysql');
        $wpdb->update(
            $this->queue_table,
            [
                'status'          => 'sent',
                'sent_at'         => $now,
                'submitted_at'    => $now,
                'last_error'      => null,
                'locked_at'       => null,
                'next_attempt_at' => null,
                'updated_at'      => $now,
            ],
            ['id' => $job_id],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );
    }

    private function mark_scheduled($job_id, $scheduled_for, $provider_reference, $transport) {
        global $wpdb;

        $now = current_time('mysql');
        $wpdb->update(
            $this->queue_table,
            [
                'status'             => 'scheduled',
                'delivery_transport' => sanitize_text_field($transport),
                'delivery_status'    => 'scheduled',
                'scheduled_for'      => $scheduled_for,
                'submitted_at'       => $now,
                'provider_reference' => sanitize_text_field($provider_reference),
                'last_error'         => null,
                'locked_at'          => null,
                'updated_at'         => $now,
            ],
            ['id' => $job_id],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );
    }

    private function mark_skipped($job_id, $reason) {
        global $wpdb;

        $now = current_time('mysql');
        $wpdb->update(
            $this->queue_table,
            [
                'status'     => 'skipped',
                'last_error' => wp_strip_all_tags((string) $reason),
                'locked_at'  => null,
                'updated_at' => $now,
            ],
            ['id' => $job_id],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );
    }

    private function mark_failed($job_id, $current_attempts, $reason) {
        global $wpdb;

        $attempts = max(0, $current_attempts) + 1;
        $status = $attempts >= $this->get_max_attempts() ? 'failed' : 'retry';
        $delay_minutes = min(60, (int) pow(2, max(0, $attempts - 1)) * 5);
        $next_attempt_at = $status === 'retry'
            ? date('Y-m-d H:i:s', current_time('timestamp') + ($delay_minutes * MINUTE_IN_SECONDS))
            : null;
        $now = current_time('mysql');

        $wpdb->update(
            $this->queue_table,
            [
                'status'          => $status,
                'delivery_transport' => $this->get_transport(),
                'attempts'        => $attempts,
                'last_error'      => wp_strip_all_tags((string) $reason),
                'next_attempt_at' => $next_attempt_at,
                'locked_at'       => null,
                'updated_at'      => $now,
            ],
            ['id' => $job_id],
            ['%s', '%s', '%d', '%s', '%s', '%s', '%s'],
            ['%d']
        );

        error_log(sprintf(
            'SFFC CRM Internship Alert Queue: job %d failed on attempt %d (%s)',
            $job_id,
            $attempts,
            (string) $reason
        ));
    }

    private function acquire_lock() {
        $now = time();
        if (add_option(self::LOCK_OPTION, $now, '', false)) {
            return true;
        }

        $existing = (int) get_option(self::LOCK_OPTION, 0);
        if ($existing > 0 && ($existing + self::LOCK_TTL) > $now) {
            return false;
        }

        update_option(self::LOCK_OPTION, $now, false);
        return true;
    }

    private function release_lock() {
        delete_option(self::LOCK_OPTION);
    }

    public function apply_sendgrid_event($queue_id, $event) {
        global $wpdb;

        $queue_id = (int) $queue_id;
        if ($queue_id <= 0) {
            return false;
        }

        $event_type = sanitize_key($event['event'] ?? 'unknown');
        $event_time = !empty($event['timestamp']) ? date('Y-m-d H:i:s', intval($event['timestamp'])) : current_time('mysql');
        $reason = sanitize_text_field($event['reason'] ?? '');

        $update_data = [
            'last_event_type'   => $event_type,
            'last_event_at'     => $event_time,
            'last_event_reason' => $reason ?: null,
            'updated_at'        => current_time('mysql'),
        ];
        $format = ['%s', '%s', '%s', '%s'];

        switch ($event_type) {
            case 'processed':
                $update_data['delivery_status'] = 'processed';
                $update_data['processed_at'] = $event_time;
                $update_data['status'] = 'sent';
                $format[] = '%s';
                $format[] = '%s';
                $format[] = '%s';
                break;

            case 'delivered':
                $update_data['delivery_status'] = 'delivered';
                $update_data['delivered_at'] = $event_time;
                $update_data['sent_at'] = $event_time;
                $update_data['status'] = 'sent';
                $format[] = '%s';
                $format[] = '%s';
                $format[] = '%s';
                $format[] = '%s';
                break;

            case 'deferred':
                $update_data['delivery_status'] = 'deferred';
                $update_data['deferred_at'] = $event_time;
                $format[] = '%s';
                $format[] = '%s';
                break;

            case 'open':
                $update_data['delivery_status'] = 'opened';
                $update_data['opened_at'] = $event_time;
                $update_data['status'] = 'sent';
                $format[] = '%s';
                $format[] = '%s';
                $format[] = '%s';
                break;

            case 'click':
                $update_data['delivery_status'] = 'clicked';
                $update_data['clicked_at'] = $event_time;
                $update_data['status'] = 'sent';
                $format[] = '%s';
                $format[] = '%s';
                $format[] = '%s';
                break;

            case 'bounce':
                $update_data['delivery_status'] = 'bounced';
                $update_data['bounced_at'] = $event_time;
                $update_data['status'] = 'failed';
                $format[] = '%s';
                $format[] = '%s';
                $format[] = '%s';
                break;

            case 'dropped':
                $update_data['delivery_status'] = 'dropped';
                $update_data['dropped_at'] = $event_time;
                $update_data['status'] = 'failed';
                $format[] = '%s';
                $format[] = '%s';
                $format[] = '%s';
                break;

            case 'spamreport':
                $update_data['delivery_status'] = 'spamreport';
                $update_data['spamreport_at'] = $event_time;
                $update_data['status'] = 'failed';
                $format[] = '%s';
                $format[] = '%s';
                $format[] = '%s';
                break;

            case 'unsubscribe':
            case 'group_unsubscribe':
                $update_data['delivery_status'] = 'unsubscribed';
                $update_data['status'] = 'failed';
                $format[] = '%s';
                $format[] = '%s';
                break;

            default:
                $update_data['delivery_status'] = $event_type;
                $format[] = '%s';
                break;
        }

        $updated = $wpdb->update(
            $this->queue_table,
            $update_data,
            ['id' => $queue_id],
            $format,
            ['%d']
        );

        if ($updated !== false) {
            update_option('sffc_crm_last_sendgrid_delivery_event', [
                'queue_id'    => $queue_id,
                'event'       => $event_type,
                'event_at'    => $event_time,
                'processed_at'=> current_time('mysql'),
            ], false);
        }

        return $updated !== false;
    }

    private function validate_job_for_delivery($job, $post) {
        $job_id = (int) ($job['id'] ?? 0);
        $user_id = (int) ($job['user_id'] ?? 0);
        $post_id = (int) ($job['post_id'] ?? 0);

        if ($job_id <= 0 || $user_id <= 0 || $post_id <= 0) {
            return new WP_Error('invalid_queue_payload', 'Invalid queue payload.');
        }

        $user = get_user_by('id', $user_id);
        if (!$user || empty($user->user_email)) {
            return new WP_Error('missing_user', 'User missing or email unavailable.');
        }

        if (function_exists('sffc_crm_user_is_excluded') && sffc_crm_user_is_excluded($user_id)) {
            return new WP_Error('excluded_user', 'User is excluded from CRM alerts.');
        }

        $prefs = sffc_crm_get_alert_preferences($user_id);
        if (!sffc_crm_alert_matches_post($prefs, $post)) {
            return new WP_Error('preferences_mismatch', 'User preferences no longer match.');
        }

        $manage_url = apply_filters('sffc_crm_internship_alert_manage_url', home_url('/?tab=profile'), $user_id, $post_id);
        $payload = sffc_crm_build_alert_email_payload($user, $post, [
            'manage_url' => $manage_url,
        ]);
        if (!$payload) {
            return new WP_Error('payload_failed', 'Unable to build alert email payload.');
        }

        return [
            'job'     => $job,
            'user'    => $user,
            'payload' => $payload,
        ];
    }
}

SFFC_CRM_Internship_Alert_Queue::get_instance();
