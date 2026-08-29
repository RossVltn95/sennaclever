<?php
/**
 * CRM Premium Match Alerts
 *
 * Background matching and delivery for paid member job-search profiles.
 *
 * @package SennaCareers
 * @since 11.15.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_Premium_Match_Alerts {

    private static $instance = null;

    const SCAN_HOOK = 'sffc_crm_scan_premium_match_posts';
    const INSTANT_HOOK = 'sffc_crm_process_premium_match_instant_queue';
    const DIGEST_HOOK = 'sffc_crm_process_premium_match_daily_digest';
    const CRON_INTERVAL = 'sffc_crm_premium_match_every_five_minutes';
    const UK_TIMEZONE = 'Europe/London';
    const DIGEST_HOUR = 6;
    const DIGEST_MINUTE = 0;

    private $posts_table;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->posts_table = $wpdb->prefix . 'sffc_crm_posts';

        add_filter('cron_schedules', [$this, 'register_cron_schedule']);
        add_action('init', [$this, 'schedule_events']);
        add_action(self::SCAN_HOOK, [$this, 'scan_recent_posts']);
        add_action(self::INSTANT_HOOK, [$this, 'process_instant_queue']);
        add_action(self::DIGEST_HOOK, [$this, 'process_daily_digest']);
    }

    public function register_cron_schedule($schedules) {
        $schedules[self::CRON_INTERVAL] = [
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display' => __('Every 5 Minutes (Premium Match Alerts)', 'senna-finance'),
        ];

        return $schedules;
    }

    public function schedule_events() {
        if (!wp_next_scheduled(self::SCAN_HOOK)) {
            wp_schedule_event(time() + MINUTE_IN_SECONDS, self::CRON_INTERVAL, self::SCAN_HOOK);
        }

        if (!wp_next_scheduled(self::INSTANT_HOOK)) {
            wp_schedule_event(time() + (2 * MINUTE_IN_SECONDS), self::CRON_INTERVAL, self::INSTANT_HOOK);
        }

        if (!wp_next_scheduled(self::DIGEST_HOOK)) {
            wp_schedule_single_event($this->get_next_uk_timestamp(self::DIGEST_HOUR, self::DIGEST_MINUTE), self::DIGEST_HOOK);
        }
    }

    private function get_next_uk_timestamp($hour, $minute) {
        try {
            $timezone = new DateTimeZone(self::UK_TIMEZONE);
            $now = new DateTimeImmutable('now', $timezone);
            $next = $now->setTime((int) $hour, (int) $minute, 0);
            if ($next <= $now) {
                $next = $next->modify('+1 day');
            }

            return $next->getTimestamp();
        } catch (Exception $error) {
            return strtotime('tomorrow 06:00');
        }
    }

    private function get_instant_threshold() {
        $threshold = (int) get_option('sffc_crm_premium_match_instant_threshold', 85);
        return max(50, min(100, $threshold));
    }

    private function get_digest_threshold() {
        $threshold = (int) get_option('sffc_crm_premium_match_digest_threshold', 65);
        return max(30, min(100, $threshold));
    }

    private function get_scan_limit() {
        $limit = (int) get_option('sffc_crm_premium_match_scan_limit', 100);
        return max(10, min(500, $limit));
    }

    public function scan_recent_posts() {
        global $wpdb;

        $last_scanned_id = (int) get_option('sffc_crm_premium_match_last_scanned_post_id', 0);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT *
             FROM {$this->posts_table}
             WHERE id > %d
               AND is_active = 1
               AND admin_approved = 1
               AND (post_status IS NULL OR post_status = 'open')
             ORDER BY id ASC
             LIMIT %d",
            $last_scanned_id,
            $this->get_scan_limit()
        ), ARRAY_A);

        if (empty($rows)) {
            return [
                'scanned' => 0,
                'queued' => 0,
            ];
        }

        $queued = 0;
        $highest_id = $last_scanned_id;
        foreach ($rows as $row) {
            $highest_id = max($highest_id, (int) ($row['id'] ?? 0));
            $queued += $this->dispatch_post((int) ($row['id'] ?? 0), $row);
        }

        update_option('sffc_crm_premium_match_last_scanned_post_id', $highest_id, false);
        update_option('sffc_crm_premium_match_last_scan', [
            'scanned' => count($rows),
            'queued' => $queued,
            'last_post_id' => $highest_id,
            'ran_at' => current_time('mysql'),
        ], false);

        return [
            'scanned' => count($rows),
            'queued' => $queued,
        ];
    }

    public function dispatch_post($post_id, $post_row = null) {
        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            return 0;
        }

        $post_model = new SFFC_CRM_Post();
        $post = is_array($post_row) ? $post_row : $post_model->get($post_id);
        if (empty($post)) {
            return 0;
        }

        $post['post_group_ids'] = function_exists('sffc_crm_get_post_group_ids')
            ? sffc_crm_get_post_group_ids($post_id)
            : [];

        $criteria_model = new SFFC_CRM_User_Criteria();
        $users = get_users([
            'fields' => ['ID', 'user_email', 'display_name'],
            'meta_key' => 'sffc_crm_premium_search_profile',
        ]);

        if (empty($users)) {
            return 0;
        }

        $instant_threshold = $this->get_instant_threshold();
        $digest_threshold = $this->get_digest_threshold();
        $queued = 0;

        foreach ($users as $user) {
            $user_id = (int) $user->ID;
            if ($user_id <= 0 || !sffc_crm_user_has_paid_job_match_access($user_id)) {
                continue;
            }

            $profile = sffc_crm_get_premium_search_profile($user_id);
            if (empty($profile['enabled'])) {
                continue;
            }

            $criteria = $this->build_criteria_from_profile($profile);
            $analysis = $criteria_model->get_match_analysis($post, $criteria);
            $score = (int) ($analysis['score'] ?? 0);
            if ($score < $digest_threshold) {
                continue;
            }

            $delivery = $score >= $instant_threshold ? 'instant' : 'digest';
            if ($this->queue_match_for_user($user_id, $post, $score, $analysis, $delivery)) {
                $queued++;
            }
        }

        return $queued;
    }

    private function build_criteria_from_profile(array $profile) {
        return [
            'job_title' => array_values(array_filter(array_merge(
                (array) ($profile['target_roles'] ?? []),
                array_filter([(string) ($profile['last_role_title'] ?? '')])
            ))),
            'sector' => array_values(array_filter((array) ($profile['target_sectors'] ?? []))),
            'location' => array_values(array_filter(array_merge(
                (array) ($profile['target_locations'] ?? []),
                array_filter([(string) ($profile['preferred_location'] ?? '')])
            ))),
            'experience_level' => array_values(array_filter((array) ($profile['target_seniority'] ?? []))),
            'skills_keywords' => array_values(array_filter((array) ($profile['target_skills'] ?? []))),
        ];
    }

    private function get_queue_meta_key($delivery) {
        return $delivery === 'instant'
            ? 'sffc_crm_premium_match_instant_queue'
            : 'sffc_crm_premium_match_digest_queue';
    }

    private function get_history_meta_key($delivery) {
        return $delivery === 'instant'
            ? 'sffc_crm_premium_match_instant_history'
            : 'sffc_crm_premium_match_digest_history';
    }

    private function get_queue($user_id, $delivery) {
        $queue = get_user_meta($user_id, $this->get_queue_meta_key($delivery), true);
        if (!is_array($queue)) {
            return [];
        }

        $normalized = [];
        foreach ($queue as $item) {
            if (!is_array($item)) {
                continue;
            }

            $post_id = (int) ($item['post_id'] ?? 0);
            if ($post_id <= 0) {
                continue;
            }

            $normalized[$post_id] = [
                'post_id' => $post_id,
                'score' => max(0, min(100, (int) ($item['score'] ?? 0))),
                'queued_at' => sanitize_text_field((string) ($item['queued_at'] ?? '')),
                'reasons' => array_values(array_filter(array_map('sanitize_text_field', (array) ($item['reasons'] ?? [])))),
                'status' => sanitize_key((string) ($item['status'] ?? 'pending')) ?: 'pending',
            ];
        }

        return array_values($normalized);
    }

    private function save_queue($user_id, $delivery, array $queue) {
        update_user_meta($user_id, $this->get_queue_meta_key($delivery), array_values($queue));
    }

    private function get_history($user_id, $delivery) {
        $history = get_user_meta($user_id, $this->get_history_meta_key($delivery), true);
        return is_array($history) ? $history : [];
    }

    private function save_history($user_id, $delivery, array $history) {
        update_user_meta($user_id, $this->get_history_meta_key($delivery), $history);
    }

    private function queue_match_for_user($user_id, array $post, $score, array $analysis, $delivery) {
        $post_id = (int) ($post['id'] ?? 0);
        if ($post_id <= 0) {
            return false;
        }

        if ($delivery === 'instant') {
            $digest_queue = $this->get_queue($user_id, 'digest');
            $digest_queue = array_values(array_filter($digest_queue, static function ($item) use ($post_id) {
                return (int) ($item['post_id'] ?? 0) !== $post_id;
            }));
            $this->save_queue($user_id, 'digest', $digest_queue);
        }

        $history = $this->get_history($user_id, $delivery);
        if (!empty($history[$post_id])) {
            return false;
        }

        $queue = $this->get_queue($user_id, $delivery);
        $queued = false;
        foreach ($queue as &$item) {
            if ((int) ($item['post_id'] ?? 0) !== $post_id) {
                continue;
            }
            if ($score > (int) ($item['score'] ?? 0)) {
                $item['score'] = $score;
                $item['reasons'] = array_values(array_unique((array) ($analysis['reasons'] ?? [])));
                $item['queued_at'] = current_time('mysql');
                $this->save_queue($user_id, $delivery, $queue);
                $queued = true;
            }
            return $queued;
        }
        unset($item);

        $queue[] = [
            'post_id' => $post_id,
            'score' => (int) $score,
            'queued_at' => current_time('mysql'),
            'reasons' => array_values(array_unique((array) ($analysis['reasons'] ?? []))),
            'status' => 'pending',
        ];
        $this->save_queue($user_id, $delivery, $queue);

        return true;
    }

    public function process_instant_queue() {
        $users = get_users([
            'fields' => ['ID', 'user_email', 'display_name'],
            'meta_key' => 'sffc_crm_premium_search_profile',
        ]);

        if (empty($users)) {
            return ['processed' => 0, 'sent' => 0];
        }

        $processed = 0;
        $sent = 0;

        foreach ($users as $user) {
            $user_id = (int) $user->ID;
            if ($user_id <= 0 || !sffc_crm_user_has_paid_job_match_access($user_id)) {
                continue;
            }

            $queue = $this->get_queue($user_id, 'instant');
            if (empty($queue)) {
                continue;
            }

            usort($queue, static function ($a, $b) {
                return (int) ($b['score'] ?? 0) <=> (int) ($a['score'] ?? 0);
            });

            $profile = sffc_crm_get_premium_search_profile($user_id);
            $remaining = [];
            foreach ($queue as $item) {
                $processed++;
                $post = (new SFFC_CRM_Post())->get((int) ($item['post_id'] ?? 0));
                if (empty($post)) {
                    continue;
                }

                $result = $this->send_match_email($user, [$post], $profile, 'instant', (int) ($item['score'] ?? 0));
                if ($result) {
                    $history = $this->get_history($user_id, 'instant');
                    $history[(int) $item['post_id']] = [
                        'score' => (int) ($item['score'] ?? 0),
                        'sent_at' => current_time('mysql'),
                    ];
                    $this->save_history($user_id, 'instant', $history);
                    $sent++;
                    continue;
                }

                $remaining[] = $item;
            }

            $this->save_queue($user_id, 'instant', $remaining);
        }

        update_option('sffc_crm_premium_match_last_instant_run', [
            'processed' => $processed,
            'sent' => $sent,
            'ran_at' => current_time('mysql'),
        ], false);

        return ['processed' => $processed, 'sent' => $sent];
    }

    public function process_daily_digest() {
        wp_clear_scheduled_hook(self::DIGEST_HOOK);
        wp_schedule_single_event($this->get_next_uk_timestamp(self::DIGEST_HOUR, self::DIGEST_MINUTE), self::DIGEST_HOOK);

        $users = get_users([
            'fields' => ['ID', 'user_email', 'display_name'],
            'meta_key' => 'sffc_crm_premium_search_profile',
        ]);

        if (empty($users)) {
            return ['processed' => 0, 'sent' => 0];
        }

        $processed = 0;
        $sent = 0;

        foreach ($users as $user) {
            $user_id = (int) $user->ID;
            if ($user_id <= 0 || !sffc_crm_user_has_paid_job_match_access($user_id)) {
                continue;
            }

            $queue = $this->get_queue($user_id, 'digest');
            if (empty($queue)) {
                continue;
            }

            usort($queue, static function ($a, $b) {
                return (int) ($b['score'] ?? 0) <=> (int) ($a['score'] ?? 0);
            });

            $posts = [];
            $history = $this->get_history($user_id, 'digest');
            $instant_history = $this->get_history($user_id, 'instant');
            foreach ($queue as $item) {
                $post_id = (int) ($item['post_id'] ?? 0);
                if ($post_id <= 0 || !empty($history[$post_id]) || !empty($instant_history[$post_id])) {
                    continue;
                }

                $post = (new SFFC_CRM_Post())->get($post_id);
                if (empty($post)) {
                    continue;
                }
                $post['match_score'] = (int) ($item['score'] ?? 0);
                $posts[] = $post;
            }

            if (empty($posts)) {
                $this->save_queue($user_id, 'digest', []);
                continue;
            }

            $profile = sffc_crm_get_premium_search_profile($user_id);
            $processed++;
            if ($this->send_match_email($user, $posts, $profile, 'digest')) {
                foreach ($queue as $item) {
                    $history[(int) ($item['post_id'] ?? 0)] = [
                        'score' => (int) ($item['score'] ?? 0),
                        'sent_at' => current_time('mysql'),
                    ];
                }
                $this->save_history($user_id, 'digest', $history);
                $this->save_queue($user_id, 'digest', []);
                $sent++;
            }
        }

        update_option('sffc_crm_premium_match_last_digest_run', [
            'processed' => $processed,
            'sent' => $sent,
            'ran_at' => current_time('mysql'),
        ], false);

        return ['processed' => $processed, 'sent' => $sent];
    }

    private function send_match_email($user, array $posts, array $profile, $mode, $top_score = 0) {
        $recipient_email = sanitize_email(is_object($user) ? ($user->user_email ?? '') : ($user['user_email'] ?? ''));
        if ($recipient_email === '') {
            return false;
        }

        $payload = $this->build_email_payload($user, $posts, $profile, $mode, $top_score);
        if (empty($payload['subject']) || empty($payload['body'])) {
            return false;
        }

        $sendgrid = class_exists('SFFC_CRM_SendGrid_Service') ? SFFC_CRM_SendGrid_Service::get_instance() : null;
        if ($sendgrid && $sendgrid->is_configured()) {
            $result = $sendgrid->send_email(
                $payload['subject'],
                $payload['body'],
                $recipient_email,
                null,
                [
                    'user_id' => is_object($user) ? (int) $user->ID : (int) ($user['ID'] ?? 0),
                    'mode' => $mode,
                ],
                $payload['categories'],
                'internship_alerts'
            );

            return !is_wp_error($result);
        }

        return wp_mail($recipient_email, $payload['subject'], $payload['body'], $payload['headers']);
    }

    private function build_email_payload($user, array $posts, array $profile, $mode, $top_score = 0) {
        $first_name = '';
        if (is_object($user)) {
            $first_name = trim((string) get_user_meta((int) $user->ID, 'first_name', true));
            if ($first_name === '') {
                $first_name = trim((string) ($user->display_name ?? ''));
            }
        }
        if ($first_name === '') {
            $first_name = __('there', 'senna-finance');
        }

        $sender = function_exists('sffc_crm_get_email_sender') ? sffc_crm_get_email_sender('internship_alerts') : [];
        $sender_name = !empty($sender['from_name']) ? $sender['from_name'] : __('Emily @ MENA Careers', 'senna-finance');
        $from_email = !empty($sender['from_email']) ? sanitize_email($sender['from_email']) : sanitize_email(get_option('sffc_crm_sendgrid_alert_from_email', 'support.team@joinsenna.com'));
        $reply_to_name = !empty($sender['reply_to_name']) ? $sender['reply_to_name'] : $sender_name;
        $reply_to_email = !empty($sender['reply_to_email']) ? sanitize_email($sender['reply_to_email']) : $from_email;

        $primary = $posts[0] ?? [];
        $target_summary = implode(', ', array_slice((array) ($profile['target_roles'] ?? []), 0, 2));
        if ($target_summary === '') {
            $target_summary = implode(', ', array_slice((array) ($profile['target_sectors'] ?? []), 0, 2));
        }
        if ($target_summary === '') {
            $target_summary = __('your search criteria', 'senna-finance');
        }

        $subject = $mode === 'instant'
            ? sprintf(__('Strong match: %1$s in %2$s', 'senna-finance'), (string) ($primary['role_title'] ?? __('New role', 'senna-finance')), (string) ($primary['location'] ?? __('your target market', 'senna-finance')))
            : sprintf(__('Your MENA Careers match round-up for %s', 'senna-finance'), wp_date('j M'));

        $intro = $mode === 'instant'
            ? sprintf(__('I found a high-confidence role that fits %s and wanted to send it across straight away.', 'senna-finance'), esc_html($target_summary))
            : sprintf(__('I pulled together the strongest roles from the last cycle based on %s.', 'senna-finance'), esc_html($target_summary));

        $items_html = '';
        foreach (array_slice($posts, 0, $mode === 'instant' ? 1 : 8) as $post) {
            $role_title = esc_html((string) ($post['role_title'] ?? __('Role', 'senna-finance')));
            $company = esc_html((string) ($post['company'] ?? __('Company', 'senna-finance')));
            $location = esc_html((string) ($post['location'] ?? $post['location_city'] ?? $post['location_country'] ?? ''));
            $score = (int) ($post['match_score'] ?? $top_score);
            $apply_url = esc_url((string) ($post['application_url'] ?? home_url('/terminal/')));
            $reasons = array_slice((array) ($post['match_reasons'] ?? []), 0, 3);
            $reasons_html = '';
            if (!empty($reasons)) {
                $reasons_html .= '<ul style="margin:10px 0 0 18px;padding:0;color:#44525c;font-size:13px;line-height:1.5;">';
                foreach ($reasons as $reason) {
                    $reasons_html .= '<li>' . esc_html((string) $reason) . '</li>';
                }
                $reasons_html .= '</ul>';
            }

            $items_html .=
                '<div style="border:1px solid #e6eaef;border-radius:8px;padding:16px 18px;margin:0 0 14px;background:#ffffff;">' .
                    '<div style="font-size:17px;font-weight:700;color:#0d353e;margin:0 0 6px;">' . $role_title . '</div>' .
                    '<div style="font-size:14px;color:#202124;margin:0 0 4px;"><strong>' . $company . '</strong></div>' .
                    '<div style="font-size:13px;color:#5f6368;margin:0 0 10px;">' . esc_html($location) . ($score > 0 ? ' • ' . sprintf(esc_html__('Match %d%%', 'senna-finance'), $score) : '') . '</div>' .
                    '<a href="' . $apply_url . '" style="display:inline-block;background:#0d6955;color:#ffffff;text-decoration:none;padding:10px 16px;border-radius:6px;font-size:13px;font-weight:700;">' . esc_html__('View role', 'senna-finance') . '</a>' .
                    $reasons_html .
                '</div>';
        }

        $body =
            '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#f6f8fc;padding:32px 16px;margin:0;">' .
                '<tr><td align="center">' .
                    '<table role="presentation" cellpadding="0" cellspacing="0" width="640" style="width:100%;max-width:640px;background:#ffffff;border:1px solid #dadce0;border-radius:8px;overflow:hidden;font-family:Arial,Helvetica,sans-serif;color:#202124;">' .
                        '<tr><td style="padding:24px;border-bottom:1px solid #eceff1;">' .
                            '<div style="font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#0d6955;font-weight:700;margin:0 0 8px;">' . esc_html__('MENA Careers Match Update', 'senna-finance') . '</div>' .
                            '<div style="font-size:24px;line-height:1.3;font-weight:700;color:#0d353e;">' . esc_html($subject) . '</div>' .
                        '</td></tr>' .
                        '<tr><td style="padding:24px;">' .
                            '<p style="margin:0 0 14px;font-size:15px;line-height:1.7;">' . sprintf(esc_html__('Hi %s,', 'senna-finance'), esc_html($first_name)) . '</p>' .
                            '<p style="margin:0 0 18px;font-size:15px;line-height:1.7;">' . $intro . '</p>' .
                            $items_html .
                            '<p style="margin:18px 0 0;font-size:14px;line-height:1.7;color:#44525c;">' . esc_html__('I’m keeping this search active and will keep routing stronger roles as they appear.', 'senna-finance') . '</p>' .
                            '<p style="margin:18px 0 0;font-size:14px;line-height:1.7;color:#202124;">' . esc_html__('Best,', 'senna-finance') . '<br>' . esc_html($sender_name) . '</p>' .
                        '</td></tr>' .
                    '</table>' .
                '</td></tr>' .
            '</table>';

        return [
            'subject' => $subject,
            'body' => $body,
            'headers' => [
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . $sender_name . ' <' . $from_email . '>',
                'Reply-To: ' . $reply_to_name . ' <' . $reply_to_email . '>',
            ],
            'categories' => $mode === 'instant' ? ['premium_match_instant'] : ['premium_match_digest'],
        ];
    }
}
