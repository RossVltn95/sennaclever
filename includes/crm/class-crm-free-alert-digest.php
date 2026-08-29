<?php
/**
 * CRM Free Alert Digest
 *
 * Queues and submits grouped internship digests for non-paying users.
 *
 * @package SennaCareers
 * @since 7.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_Free_Alert_Digest {

    private static $instance = null;

    const BUILD_HOOK = 'sffc_crm_queue_free_alert_digests';
    const BUILD_START_HOOK = 'sffc_crm_start_free_alert_digest_build';
    const BUILD_PROCESS_HOOK = 'sffc_crm_process_free_alert_digest_build';
    const PROCESS_START_HOOK = 'sffc_crm_start_free_alert_digest_processing';
    const PROCESS_HOOK = 'sffc_crm_process_free_alert_digest_queue';
    const CRON_INTERVAL = 'sffc_crm_free_alert_every_five_minutes';
    const LOCK_OPTION = 'sffc_crm_free_alert_digest_queue_lock';
    const BUILD_LOCK_OPTION = 'sffc_crm_free_alert_digest_build_lock';
    const BUILD_STATE_OPTION = 'sffc_crm_free_alert_digest_build_state';
    const PROCESS_STATE_OPTION = 'sffc_crm_free_alert_digest_process_state';
    const LOCK_TTL = 240;
    const DEFAULT_BATCH_SIZE = 1000;
    const DEFAULT_BUILD_CHUNK_SIZE = 250;
    const DIGEST_INTERVAL = DAY_IN_SECONDS;
    const MAX_ATTEMPTS = 3;
    const UK_TIMEZONE = 'Europe/London';
    const BUILD_HOUR = 3;
    const BUILD_MINUTE = 30;
    const PROCESS_HOUR = 7;
    const PROCESS_MINUTE = 30;

    private $queue_table;
    private $queue_schema_checked = false;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->queue_table = $wpdb->prefix . 'sffc_crm_free_alert_digest_queue';

        add_filter('cron_schedules', [$this, 'register_cron_schedule']);
        add_action('init', [$this, 'schedule_events']);
        add_action(self::BUILD_HOOK, [$this, 'queue_due_users']);
        add_action(self::BUILD_START_HOOK, [$this, 'run_scheduled_build_start']);
        add_action(self::BUILD_PROCESS_HOOK, [$this, 'process_build_queue']);
        add_action(self::PROCESS_START_HOOK, [$this, 'run_scheduled_processing_start']);
        add_action(self::PROCESS_HOOK, [$this, 'process_queue']);
    }

    public function register_cron_schedule($schedules) {
        $schedules[self::CRON_INTERVAL] = [
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display' => __('Every 5 Minutes (Free Alert Digest Queue)', 'senna-finance'),
        ];

        return $schedules;
    }

    public function schedule_event() {
        $this->schedule_events();
    }

    public function schedule_events() {
        if (function_exists('sffc_crm_internship_alerts_disabled') && sffc_crm_internship_alerts_disabled()) {
            $this->clear_scheduled_events();
            return;
        }

        wp_clear_scheduled_hook(self::BUILD_HOOK);

        if (!wp_next_scheduled(self::BUILD_START_HOOK)) {
            wp_schedule_single_event($this->get_next_uk_timestamp(self::BUILD_HOUR, self::BUILD_MINUTE), self::BUILD_START_HOOK);
        }

        if (!wp_next_scheduled(self::PROCESS_START_HOOK)) {
            wp_schedule_single_event($this->get_next_uk_timestamp(self::PROCESS_HOUR, self::PROCESS_MINUTE), self::PROCESS_START_HOOK);
        }

        if (!wp_next_scheduled(self::BUILD_PROCESS_HOOK)) {
            wp_schedule_event(time() + MINUTE_IN_SECONDS, self::CRON_INTERVAL, self::BUILD_PROCESS_HOOK);
        }

        if (!wp_next_scheduled(self::PROCESS_HOOK)) {
            wp_schedule_event(time() + MINUTE_IN_SECONDS, self::CRON_INTERVAL, self::PROCESS_HOOK);
        }
    }

    public function run_scheduled_build_start() {
        if (function_exists('sffc_crm_internship_alerts_disabled') && sffc_crm_internship_alerts_disabled()) {
            return [
                'checked' => 0,
                'queued' => 0,
                'disabled' => true,
            ];
        }

        $this->schedule_next_build_start();
        $state = $this->start_background_build(true);
        if (!empty($state['status']) && $state['status'] === 'running') {
            $this->process_build_queue();
        }
        return $state;
    }

    public function run_scheduled_processing_start() {
        if (function_exists('sffc_crm_internship_alerts_disabled') && sffc_crm_internship_alerts_disabled()) {
            return [
                'processed' => 0,
                'sent' => 0,
                'failed' => 0,
                'disabled' => true,
            ];
        }

        $this->schedule_next_processing_start();
        $state = $this->start_processing(true);
        $this->process_queue();
        return $state;
    }

    public function queue_due_users() {
        if (function_exists('sffc_crm_internship_alerts_disabled') && sffc_crm_internship_alerts_disabled()) {
            return [
                'checked' => 0,
                'queued' => 0,
                'disabled' => true,
            ];
        }

        return $this->start_background_build();
    }

    public function start_background_build($force_restart = false, $target_queue_count = 0) {
        if (function_exists('sffc_crm_internship_alerts_disabled') && sffc_crm_internship_alerts_disabled()) {
            return [
                'checked' => 0,
                'queued' => 0,
                'disabled' => true,
            ];
        }

        global $wpdb;

        $this->ensure_queue_schema();

        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $this->queue_table)) !== $this->queue_table) {
            return [
                'checked' => 0,
                'queued' => 0,
                'cycle_key' => '',
                'missing_table' => true,
            ];
        }

        $state = $this->get_build_state();
        if (!$force_restart && !empty($state['status']) && $state['status'] === 'running') {
            $state['already_running'] = true;
            return $state;
        }
        if (!$force_restart && !empty($state['status']) && $state['status'] === 'paused') {
            $state['paused'] = true;
            return $state;
        }

        $cycle_key = $this->get_current_cycle_key();
        $count_users = count_users();
        $total_users = (int) ($count_users['total_users'] ?? 0);
        $target_queue_count = max(0, (int) $target_queue_count);
        $candidate_user_ids = $target_queue_count > 0 ? $this->get_random_build_candidate_ids($target_queue_count, $total_users) : [];
        if (!empty($candidate_user_ids)) {
            $total_users = count($candidate_user_ids);
        }

        $state = [
            'status' => 'running',
            'cycle_key' => $cycle_key,
            'offset' => 0,
            'checked' => 0,
            'queued' => 0,
            'total_users' => $total_users,
            'chunk_size' => $this->get_build_chunk_size(),
            'started_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
            'completed_at' => '',
            'target_queue_count' => $target_queue_count,
            'candidate_user_ids' => $candidate_user_ids,
        ];

        update_option(self::BUILD_STATE_OPTION, $state, false);

        return $state + ['started' => true];
    }

    public function pause_build() {
        $state = $this->get_build_state();
        if (empty($state['status']) || $state['status'] !== 'running') {
            return $state + ['paused' => false];
        }

        $state['status'] = 'paused';
        $state['updated_at'] = current_time('mysql');
        update_option(self::BUILD_STATE_OPTION, $state, false);

        return $state + ['paused' => true];
    }

    public function resume_build() {
        $state = $this->get_build_state();
        if (empty($state['status']) || $state['status'] !== 'paused') {
            return $state + ['resumed' => false];
        }

        $state['status'] = 'running';
        $state['updated_at'] = current_time('mysql');
        update_option(self::BUILD_STATE_OPTION, $state, false);

        return $state + ['resumed' => true];
    }

    public function process_build_queue() {
        global $wpdb;

        if (function_exists('sffc_crm_internship_alerts_disabled') && sffc_crm_internship_alerts_disabled()) {
            return [
                'checked' => 0,
                'queued' => 0,
                'disabled' => true,
            ];
        }

        $this->ensure_queue_schema();

        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $this->queue_table)) !== $this->queue_table) {
            return [
                'checked' => 0,
                'queued' => 0,
                'missing_table' => true,
            ];
        }

        $state = $this->get_build_state();
        if (empty($state['status']) || $state['status'] !== 'running') {
            return [
                'checked' => 0,
                'queued' => 0,
                'paused' => (!empty($state['status']) && $state['status'] === 'paused'),
                'idle' => true,
            ];
        }

        if (!$this->acquire_build_lock()) {
            return [
                'checked' => 0,
                'queued' => 0,
                'locked' => true,
            ];
        }

        try {
            $offset = max(0, (int) ($state['offset'] ?? 0));
            $chunk_size = max(1, (int) ($state['chunk_size'] ?? $this->get_build_chunk_size()));
            $target_queue_count = max(0, (int) ($state['target_queue_count'] ?? 0));
            $candidate_user_ids = array_values(array_filter(array_map('intval', (array) ($state['candidate_user_ids'] ?? []))));

            if (!empty($candidate_user_ids)) {
                $user_ids = array_slice($candidate_user_ids, $offset, $chunk_size);
                $users = empty($user_ids) ? [] : get_users([
                    'fields' => ['ID', 'display_name', 'user_email'],
                    'include' => $user_ids,
                    'orderby' => 'include',
                ]);
            } else {
                $users = get_users([
                    'fields' => ['ID', 'display_name', 'user_email'],
                    'number' => $chunk_size,
                    'offset' => $offset,
                    'orderby' => 'ID',
                    'order' => 'ASC',
                ]);
            }

            if (empty($users)) {
                return $this->complete_build_state($state);
            }

            $checked = 0;
            $queued = 0;
            foreach ($users as $user) {
                $checked++;

                if (!$this->user_is_due($user->ID)) {
                    continue;
                }

                if ($this->job_exists_for_cycle($user->ID, $state['cycle_key'])) {
                    continue;
                }

                $prefs = sffc_crm_get_alert_preferences($user->ID);
                $last_sent_at = get_user_meta($user->ID, 'sffc_crm_free_alert_digest_last_sent_at', true);
                $matches = sffc_crm_get_matching_internship_posts_for_alerts($prefs, [
                    'limit' => 24,
                    'scan_limit' => 400,
                    'user_id' => (int) $user->ID,
                    'last_sent_at' => $last_sent_at,
                    'max_digest_repeats' => 2,
                ]);

                if (empty($matches['posts'])) {
                    continue;
                }

                if ($this->enqueue_user_digest($user->ID, $state['cycle_key'], (int) ($matches['total'] ?? count($matches['posts'])))) {
                    $queued++;

                    if ($target_queue_count > 0 && ((int) ($state['queued'] ?? 0) + $queued) >= $target_queue_count) {
                        break;
                    }
                }
            }

            $state['offset'] = $offset + $checked;
            $state['checked'] = (int) ($state['checked'] ?? 0) + $checked;
            $state['queued'] = (int) ($state['queued'] ?? 0) + $queued;
            $state['updated_at'] = current_time('mysql');

            $is_complete = count($users) < $chunk_size || $state['offset'] >= (int) ($state['total_users'] ?? 0);
            if ($target_queue_count > 0 && (int) ($state['queued'] ?? 0) >= $target_queue_count) {
                $is_complete = true;
            }

            if ($is_complete) {
                return $this->complete_build_state($state);
            }

            update_option(self::BUILD_STATE_OPTION, $state, false);

            return $state + [
                'chunk_checked' => $checked,
                'chunk_queued' => $queued,
            ];
        } finally {
            $this->release_build_lock();
        }
    }

    public function process_queue() {
        global $wpdb;

        $this->ensure_queue_schema();

        if (
            (function_exists('sffc_crm_alert_email_sending_disabled') && sffc_crm_alert_email_sending_disabled())
            || (function_exists('sffc_crm_internship_alerts_disabled') && sffc_crm_internship_alerts_disabled())
        ) {
            $this->pause_processing();
            return [
                'processed' => 0,
                'sent' => 0,
                'failed' => 0,
                'disabled' => true,
            ];
        }

        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $this->queue_table)) !== $this->queue_table) {
            return [
                'processed' => 0,
                'sent' => 0,
                'failed' => 0,
                'missing_table' => true,
            ];
        }

        if (!$this->acquire_lock()) {
            return [
                'processed' => 0,
                'sent' => 0,
                'failed' => 0,
                'locked' => true,
            ];
        }

        $processed = 0;
        $sent = 0;
        $failed = 0;

        try {
            $jobs = $this->claim_batch($this->get_batch_size());
            if (empty($jobs)) {
                $this->maybe_complete_processing_state();
                return [
                    'processed' => 0,
                    'sent' => 0,
                    'failed' => 0,
                    'empty' => true,
                ];
            }

            $prepared_groups = [];
            $sendgrid = class_exists('SFFC_CRM_SendGrid_Service') ? SFFC_CRM_SendGrid_Service::get_instance() : null;
            $use_batched_sendgrid = $sendgrid && $sendgrid->is_configured();

            foreach ($jobs as $job) {
                $processed++;
                $prepared = $this->prepare_job_payload($job);
                if (is_wp_error($prepared)) {
                    $code = $prepared->get_error_code();
                    if ($code === 'skipped') {
                        continue;
                    }

                    $this->mark_retry_or_failed($job, $prepared->get_error_message());
                    $failed++;
                    continue;
                }

                if (!$use_batched_sendgrid) {
                    $result = wp_mail(
                        $prepared['payload']['recipient'],
                        $prepared['payload']['subject'],
                        $prepared['payload']['body'],
                        $prepared['payload']['headers']
                    );

                    if ($result) {
                        $this->mark_sent((int) $job['id'], '');
                        update_user_meta($prepared['user']->ID, 'sffc_crm_free_alert_digest_last_sent_at', current_time('mysql'));
                        update_user_meta($prepared['user']->ID, 'sffc_crm_free_alert_digest_last_cycle_key', sanitize_text_field($prepared['job']['cycle_key'] ?? ''));
                        sffc_crm_record_free_alert_digest_posts($prepared['user']->ID, $prepared['posts'] ?? []);
                        $sent++;
                    } else {
                        $this->mark_retry_or_failed($job, __('Failed to send the free alert digest email.', 'senna-finance'));
                        $failed++;
                    }
                    continue;
                }

                $payload_key = md5($prepared['payload']['subject'] . '|' . $prepared['payload']['body']);
                if (!isset($prepared_groups[$payload_key])) {
                    $prepared_groups[$payload_key] = [
                        'subject' => $prepared['payload']['subject'],
                        'body' => $prepared['payload']['body'],
                        'categories' => $prepared['payload']['categories'] ?? ['internship_alert_digest'],
                        'jobs' => [],
                    ];
                }

                $prepared_groups[$payload_key]['jobs'][] = $prepared;
            }

            if ($use_batched_sendgrid) {
                foreach ($prepared_groups as $group) {
                    $recipients = [];
                    foreach ($group['jobs'] as $prepared) {
                        $recipients[] = [
                            'email' => $prepared['payload']['recipient'],
                            'user_id' => (int) $prepared['user']->ID,
                            'queue_id' => (int) $prepared['job']['id'],
                            'post_id' => (int) (($prepared['posts'][0]['id'] ?? 0)),
                        ];
                    }

                    $result = $sendgrid->send_scheduled_batch(
                        $group['subject'],
                        $group['body'],
                        $recipients,
                        current_time('timestamp')
                    );

                    if (is_wp_error($result)) {
                        foreach ($group['jobs'] as $prepared) {
                            $this->mark_retry_or_failed($prepared['job'], $result->get_error_message());
                            $failed++;
                        }
                        continue;
                    }

                    $provider_reference = sanitize_text_field($result['provider_reference'] ?? '');
                    foreach ($group['jobs'] as $prepared) {
                        $this->mark_sent((int) $prepared['job']['id'], $provider_reference);
                        update_user_meta($prepared['user']->ID, 'sffc_crm_free_alert_digest_last_sent_at', current_time('mysql'));
                        update_user_meta($prepared['user']->ID, 'sffc_crm_free_alert_digest_last_cycle_key', sanitize_text_field($prepared['job']['cycle_key'] ?? ''));
                        sffc_crm_record_free_alert_digest_posts($prepared['user']->ID, $prepared['posts'] ?? []);
                        $sent++;
                    }
                }
            }

            update_option('sffc_crm_last_free_alert_digest_run', [
                'processed' => $processed,
                'sent' => $sent,
                'failed' => $failed,
                'ran_at' => current_time('mysql'),
            ], false);

            $this->record_processing_progress($processed, $sent, $failed);

            return [
                'processed' => $processed,
                'sent' => $sent,
                'failed' => $failed,
            ];
        } finally {
            $this->release_lock();
        }
    }

    private function get_batch_size() {
        $batch_size = (int) get_option('sffc_crm_free_alert_digest_batch_size', self::DEFAULT_BATCH_SIZE);
        return max(1, min(5000, $batch_size));
    }

    private function clear_scheduled_events() {
        wp_clear_scheduled_hook(self::BUILD_HOOK);
        wp_clear_scheduled_hook(self::BUILD_START_HOOK);
        wp_clear_scheduled_hook(self::BUILD_PROCESS_HOOK);
        wp_clear_scheduled_hook(self::PROCESS_START_HOOK);
        wp_clear_scheduled_hook(self::PROCESS_HOOK);
    }

    private function get_build_chunk_size() {
        $chunk_size = (int) get_option('sffc_crm_free_alert_digest_build_chunk_size', self::DEFAULT_BUILD_CHUNK_SIZE);
        return max(25, min(1000, $chunk_size));
    }

    private function get_max_attempts() {
        return (int) apply_filters('sffc_crm_free_alert_digest_max_attempts', self::MAX_ATTEMPTS);
    }

    private function get_current_cycle_key() {
        $tz = new DateTimeZone(self::UK_TIMEZONE);
        $now = new DateTimeImmutable('now', $tz);
        return $now->format('Ymd');
    }

    private function user_is_due($user_id) {
        $user_id = (int) $user_id;
        if ($user_id <= 0 || sffc_crm_user_has_instant_alert_access($user_id)) {
            return false;
        }

        if (function_exists('sffc_crm_user_is_excluded') && sffc_crm_user_is_excluded($user_id)) {
            return false;
        }

        $prefs = sffc_crm_get_alert_preferences($user_id);
        if (empty($prefs['enabled'])) {
            return false;
        }

        $current_cycle_key = $this->get_current_cycle_key();
        $last_cycle_key = sanitize_text_field((string) get_user_meta($user_id, 'sffc_crm_free_alert_digest_last_cycle_key', true));
        if ($last_cycle_key !== '') {
            return $last_cycle_key !== $current_cycle_key;
        }

        $last_sent_at = get_user_meta($user_id, 'sffc_crm_free_alert_digest_last_sent_at', true);
        if (!$last_sent_at) {
            return true;
        }

        return $this->get_cycle_key_for_local_datetime((string) $last_sent_at) !== $current_cycle_key;
    }

    private function job_exists_for_cycle($user_id, $cycle_key) {
        global $wpdb;

        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->queue_table} WHERE user_id = %d AND cycle_key = %s LIMIT 1",
            (int) $user_id,
            $cycle_key
        ));
    }

    private function enqueue_user_digest($user_id, $cycle_key, $match_count, $selected_post_id = 0) {
        global $wpdb;

        $now = current_time('mysql');
        $release_at = $this->get_release_time_for_cycle_key($cycle_key);
        $result = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$this->queue_table}
                (user_id, cycle_key, status, attempts, max_attempts, match_count, selected_post_id, next_attempt_at, created_at, updated_at)
             VALUES (%d, %s, %s, %d, %d, %d, %d, %s, %s, %s)
             ON DUPLICATE KEY UPDATE id = id",
            (int) $user_id,
            $cycle_key,
            'pending',
            0,
            $this->get_max_attempts(),
            (int) $match_count,
            (int) $selected_post_id,
            $release_at,
            $now,
            $now
        ));

        return $result === 1;
    }

    private function claim_batch($limit) {
        global $wpdb;

        $limit = max(1, (int) $limit);
        $now = current_time('mysql');
        $jobs = $wpdb->get_results($wpdb->prepare(
            "SELECT *
             FROM {$this->queue_table}
             WHERE status IN ('pending', 'retry')
               AND (next_attempt_at IS NULL OR next_attempt_at <= %s)
             ORDER BY created_at ASC, id ASC
             LIMIT %d",
            $now,
            $limit
        ), ARRAY_A);

        if (empty($jobs)) {
            return [];
        }

        $claimed = [];
        foreach ($jobs as $job) {
            $updated = $wpdb->update(
                $this->queue_table,
                [
                    'status' => 'processing',
                    'locked_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'id' => (int) $job['id'],
                    'status' => $job['status'],
                ],
                ['%s', '%s', '%s'],
                ['%d', '%s']
            );

            if ($updated !== false && $updated > 0) {
                $job['status'] = 'processing';
                $job['locked_at'] = $now;
                $claimed[] = $job;
            }
        }

        return $claimed;
    }

    private function prepare_job_payload($job) {
        $job_id = (int) ($job['id'] ?? 0);
        $user_id = (int) ($job['user_id'] ?? 0);
        if ($job_id <= 0 || $user_id <= 0) {
            return new WP_Error('invalid_job', __('Invalid free alert digest queue job.', 'senna-finance'));
        }

        $user = get_user_by('id', $user_id);
        if (!$user || sffc_crm_user_has_instant_alert_access($user_id)) {
            $this->mark_skipped($job_id, __('User no longer qualifies for the free alert digest.', 'senna-finance'));
            return new WP_Error('skipped', __('User no longer qualifies for the free alert digest.', 'senna-finance'));
        }

        if (function_exists('sffc_crm_user_is_excluded') && sffc_crm_user_is_excluded($user_id)) {
            $this->mark_skipped($job_id, __('User is excluded from CRM feeds and alerts.', 'senna-finance'));
            return new WP_Error('skipped', __('User is excluded from CRM feeds and alerts.', 'senna-finance'));
        }

        $prefs = sffc_crm_get_alert_preferences($user_id);
        if (empty($prefs['enabled'])) {
            $this->mark_skipped($job_id, __('Alert preferences are disabled for this user.', 'senna-finance'));
            return new WP_Error('skipped', __('Alert preferences are disabled for this user.', 'senna-finance'));
        }

        $last_sent_at = get_user_meta($user_id, 'sffc_crm_free_alert_digest_last_sent_at', true);
        $matches = sffc_crm_get_matching_internship_posts_for_alerts($prefs, [
            'limit' => 24,
            'scan_limit' => 400,
            'user_id' => $user_id,
            'last_sent_at' => $last_sent_at,
            'max_digest_repeats' => 2,
        ]);

        if (empty($matches['posts'])) {
            $this->mark_skipped($job_id, __('No active digest matches remain for this user.', 'senna-finance'));
            return new WP_Error('skipped', __('No active digest matches remain for this user.', 'senna-finance'));
        }

        $digest_posts = array_values(array_slice((array) $matches['posts'], 0, 3));
        if (empty($digest_posts)) {
            $this->mark_skipped($job_id, __('No active digest matches remain for this user.', 'senna-finance'));
            return new WP_Error('skipped', __('No active digest matches remain for this user.', 'senna-finance'));
        }

        $payload = sffc_crm_build_free_alert_digest_email_payload($user, $digest_posts, $prefs, [
            'total_matches' => (int) ($matches['total'] ?? count($matches['posts'])),
            'last_sent_at' => $last_sent_at,
            'destination_url' => sffc_crm_get_alert_digest_destination_url(),
            'upgrade_url' => sffc_crm_get_alert_membership_upgrade_url(),
            'manage_url' => 'https://joinsenna.com/terminal/',
        ]);

        if (!$payload) {
            return new WP_Error('payload_build_failed', __('Unable to build the free alert digest email payload.', 'senna-finance'));
        }

        return [
            'job' => $job,
            'user' => $user,
            'payload' => $payload,
            'matches' => $matches,
            'posts' => $digest_posts,
        ];
    }

    private function ensure_queue_schema() {
        if ($this->queue_schema_checked) {
            return;
        }

        global $wpdb;
        $this->queue_schema_checked = true;

        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $this->queue_table)) !== $this->queue_table) {
            return;
        }

        $column = $wpdb->get_var($wpdb->prepare(
            "SHOW COLUMNS FROM {$this->queue_table} LIKE %s",
            'selected_post_id'
        ));

        if (!$column) {
            $wpdb->query("ALTER TABLE {$this->queue_table} ADD COLUMN selected_post_id bigint(20) DEFAULT 0 AFTER match_count");
            $wpdb->query("ALTER TABLE {$this->queue_table} ADD KEY idx_selected_post (selected_post_id)");
        }
    }

    private function get_random_build_candidate_ids($target_queue_count, $total_users) {
        $target_queue_count = max(1, (int) $target_queue_count);
        $total_users = max(0, (int) $total_users);
        if ($total_users <= 0) {
            return [];
        }

        $sample_size = min($total_users, max($target_queue_count + 250, $target_queue_count * 5));
        $sample_size = min(10000, $sample_size);

        $ids = get_users([
            'fields' => 'ID',
            'number' => $sample_size,
            'orderby' => 'rand',
        ]);

        return array_values(array_filter(array_map('intval', (array) $ids)));
    }

    private function mark_retry_or_failed($job, $message) {
        global $wpdb;

        $job_id = (int) ($job['id'] ?? 0);
        if ($job_id <= 0) {
            return;
        }

        $attempts = ((int) ($job['attempts'] ?? 0)) + 1;
        $max_attempts = max(1, (int) ($job['max_attempts'] ?? $this->get_max_attempts()));

        if ($attempts >= $max_attempts) {
            $wpdb->update(
                $this->queue_table,
                [
                    'status' => 'failed',
                    'attempts' => $attempts,
                    'locked_at' => null,
                    'last_error' => $message,
                    'updated_at' => current_time('mysql'),
                ],
                ['id' => $job_id],
                ['%s', '%d', '%s', '%s', '%s'],
                ['%d']
            );
            return;
        }

        $delay_minutes = min(60, max(5, $attempts * 10));
        $next_attempt_at = gmdate('Y-m-d H:i:s', current_time('timestamp', true) + ($delay_minutes * MINUTE_IN_SECONDS));

        $wpdb->update(
            $this->queue_table,
            [
                'status' => 'retry',
                'attempts' => $attempts,
                'next_attempt_at' => $next_attempt_at,
                'locked_at' => null,
                'last_error' => $message,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $job_id],
            ['%s', '%d', '%s', '%s', '%s', '%s'],
            ['%d']
        );
    }

    private function mark_sent($job_id, $provider_reference = '') {
        global $wpdb;

        $now = current_time('mysql');
        $wpdb->update(
            $this->queue_table,
            [
                'status' => 'sent',
                'submitted_at' => $now,
                'sent_at' => $now,
                'provider_reference' => $provider_reference,
                'locked_at' => null,
                'updated_at' => $now,
            ],
            ['id' => (int) $job_id],
            ['%s', '%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );
    }

    private function mark_skipped($job_id, $reason) {
        global $wpdb;

        $wpdb->update(
            $this->queue_table,
            [
                'status' => 'skipped',
                'locked_at' => null,
                'last_error' => $reason,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => (int) $job_id],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );
    }

    private function acquire_lock() {
        $lock = get_option(self::LOCK_OPTION, []);
        if (!empty($lock['expires']) && (int) $lock['expires'] > time()) {
            return false;
        }

        update_option(self::LOCK_OPTION, [
            'expires' => time() + self::LOCK_TTL,
            'set_at' => current_time('mysql'),
        ], false);

        return true;
    }

    private function release_lock() {
        delete_option(self::LOCK_OPTION);
    }

    private function acquire_build_lock() {
        $lock = get_option(self::BUILD_LOCK_OPTION);
        if (is_array($lock) && !empty($lock['timestamp'])) {
            $age = current_time('timestamp') - (int) $lock['timestamp'];
            if ($age < self::LOCK_TTL) {
                return false;
            }
        }

        update_option(self::BUILD_LOCK_OPTION, [
            'timestamp' => current_time('timestamp'),
        ], false);

        return true;
    }

    private function release_build_lock() {
        delete_option(self::BUILD_LOCK_OPTION);
    }

    private function complete_build_state($state) {
        $state['status'] = 'completed';
        $state['completed_at'] = current_time('mysql');
        $state['updated_at'] = current_time('mysql');

        update_option(self::BUILD_STATE_OPTION, $state, false);
        update_option('sffc_crm_last_free_alert_digest_build', [
            'cycle_key' => $state['cycle_key'] ?? '',
            'checked' => (int) ($state['checked'] ?? 0),
            'queued' => (int) ($state['queued'] ?? 0),
            'queued_at' => current_time('mysql'),
            'completed_at' => $state['completed_at'],
        ], false);

        return $state + ['completed' => true];
    }

    public function get_build_state() {
        $state = get_option(self::BUILD_STATE_OPTION, []);
        return is_array($state) ? $state : [];
    }

    public function reset_build_progress($clear_pending_jobs = true) {
        $removed_jobs = 0;
        $preserved_processing_jobs = 0;

        delete_option(self::BUILD_STATE_OPTION);
        delete_option(self::BUILD_LOCK_OPTION);
        delete_option(self::PROCESS_STATE_OPTION);
        delete_option(self::LOCK_OPTION);

        return [
            'reset' => true,
            'removed_jobs' => max(0, $removed_jobs),
            'preserved_processing_jobs' => max(0, $preserved_processing_jobs),
        ];
    }

    public function start_processing($force_restart = false) {
        global $wpdb;

        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $this->queue_table)) !== $this->queue_table) {
            return [
                'missing_table' => true,
                'status' => 'idle',
            ];
        }

        $state = $this->get_processing_state();
        if (!$force_restart && !empty($state['status']) && $state['status'] === 'running') {
            return $state + ['already_running' => true];
        }
        if (!$force_restart && !empty($state['status']) && $state['status'] === 'paused') {
            return $state + ['paused' => true];
        }

        $total_jobs = $this->count_ready_processing_jobs();

        $state = [
            'status' => $total_jobs > 0 ? 'running' : 'completed',
            'total_jobs' => $total_jobs,
            'processed' => 0,
            'sent' => 0,
            'failed' => 0,
            'started_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
            'completed_at' => $total_jobs > 0 ? '' : current_time('mysql'),
        ];

        update_option(self::PROCESS_STATE_OPTION, $state, false);
        return $state + ['started' => true];
    }

    public function pause_processing() {
        $state = $this->get_processing_state();
        if (empty($state['status']) || $state['status'] !== 'running') {
            return $state + ['paused' => false];
        }

        $state['status'] = 'paused';
        $state['updated_at'] = current_time('mysql');
        update_option(self::PROCESS_STATE_OPTION, $state, false);

        return $state + ['paused' => true];
    }

    public function resume_processing() {
        $state = $this->get_processing_state();
        if (empty($state['status']) || $state['status'] !== 'paused') {
            return $state + ['resumed' => false];
        }

        $remaining = $this->count_ready_processing_jobs();
        $state['status'] = $remaining > 0 ? 'running' : 'completed';
        $state['updated_at'] = current_time('mysql');
        if ($remaining === 0) {
            $state['completed_at'] = current_time('mysql');
        }
        update_option(self::PROCESS_STATE_OPTION, $state, false);

        return $state + ['resumed' => true];
    }

    public function get_processing_state() {
        $state = get_option(self::PROCESS_STATE_OPTION, []);
        return is_array($state) ? $state : [];
    }

    private function record_processing_progress($processed, $sent, $failed) {
        $state = $this->get_processing_state();
        if (empty($state['status']) || !in_array($state['status'], ['running', 'paused'], true)) {
            return;
        }

        $state['processed'] = (int) ($state['processed'] ?? 0) + (int) $processed;
        $state['sent'] = (int) ($state['sent'] ?? 0) + (int) $sent;
        $state['failed'] = (int) ($state['failed'] ?? 0) + (int) $failed;
        $state['updated_at'] = current_time('mysql');

        $remaining = $this->count_ready_processing_jobs();
        if ($remaining <= 0) {
            $state['status'] = 'completed';
            $state['completed_at'] = current_time('mysql');
        }

        update_option(self::PROCESS_STATE_OPTION, $state, false);
    }

    private function maybe_complete_processing_state() {
        $state = $this->get_processing_state();
        if (empty($state['status']) || !in_array($state['status'], ['running', 'paused'], true)) {
            return;
        }

        if ($this->count_ready_processing_jobs() > 0) {
            return;
        }

        $state['status'] = 'completed';
        $state['completed_at'] = current_time('mysql');
        $state['updated_at'] = current_time('mysql');
        update_option(self::PROCESS_STATE_OPTION, $state, false);
    }

    private function count_pending_processing_jobs() {
        global $wpdb;

        return (int) $wpdb->get_var(
            "SELECT COUNT(*)
             FROM {$this->queue_table}
             WHERE status IN ('pending', 'retry')"
        );
    }

    private function count_ready_processing_jobs() {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$this->queue_table}
             WHERE status IN ('pending', 'retry')
               AND (next_attempt_at IS NULL OR next_attempt_at <= %s)",
            current_time('mysql')
        ));
    }

    private function get_cycle_key_for_local_datetime($datetime_string) {
        $datetime_string = trim((string) $datetime_string);
        if ($datetime_string === '') {
            return '';
        }

        try {
            $site_tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
            $dt = new DateTimeImmutable($datetime_string, $site_tz);
            return $dt->setTimezone(new DateTimeZone(self::UK_TIMEZONE))->format('Ymd');
        } catch (Exception $e) {
            return '';
        }
    }

    private function get_release_time_for_cycle_key($cycle_key) {
        $cycle_key = preg_replace('/[^0-9]/', '', (string) $cycle_key);
        if (strlen($cycle_key) !== 8) {
            $cycle_key = $this->get_current_cycle_key();
        }

        try {
            $tz = new DateTimeZone(self::UK_TIMEZONE);
            $release = DateTimeImmutable::createFromFormat(
                'Ymd H:i',
                $cycle_key . ' ' . sprintf('%02d:%02d', self::PROCESS_HOUR, self::PROCESS_MINUTE),
                $tz
            );
            if ($release instanceof DateTimeImmutable) {
                $site_tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
                return $release->setTimezone($site_tz)->format('Y-m-d H:i:s');
            }
        } catch (Exception $e) {
        }

        return current_time('mysql');
    }

    private function get_next_uk_timestamp($hour, $minute) {
        $tz = new DateTimeZone(self::UK_TIMEZONE);
        $now = new DateTimeImmutable('now', $tz);
        $target = $now->setTime((int) $hour, (int) $minute, 0);

        if ($target <= $now) {
            $target = $target->modify('+1 day');
        }

        return $target->getTimestamp();
    }

    private function schedule_next_build_start() {
        if (!wp_next_scheduled(self::BUILD_START_HOOK)) {
            wp_schedule_single_event($this->get_next_uk_timestamp(self::BUILD_HOUR, self::BUILD_MINUTE), self::BUILD_START_HOOK);
        }
    }

    private function schedule_next_processing_start() {
        if (!wp_next_scheduled(self::PROCESS_START_HOOK)) {
            wp_schedule_single_event($this->get_next_uk_timestamp(self::PROCESS_HOUR, self::PROCESS_MINUTE), self::PROCESS_START_HOOK);
        }
    }
}
