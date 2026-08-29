<?php
/**
 * CRM Email Digest System
 * Sends daily/weekly summary emails to users
 *
 * @package SennaCareers
 * @since 7.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_Email_Digest {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function get_user_locale_code($user) {
        if ($user instanceof WP_User && function_exists('get_user_locale')) {
            return (string) get_user_locale($user);
        }

        return (string) get_locale();
    }

    private function is_arabic_locale($locale) {
        $locale = strtolower((string) $locale);
        return strpos($locale, 'ar') === 0;
    }

    private function localize_copy($english, $arabic, $locale) {
        return $this->is_arabic_locale($locale) ? $arabic : $english;
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Schedule cron events
        add_action('init', [$this, 'schedule_digest_events']);

        // Cron handlers
        add_action('sffc_crm_daily_digest', [$this, 'send_daily_digests']);
        add_action('sffc_crm_weekly_digest', [$this, 'send_weekly_digests']);

        // User preference handlers
        add_action('wp_ajax_sffc_crm_update_digest_preferences', [$this, 'ajax_update_preferences']);
        add_action('wp_ajax_sffc_crm_get_digest_preferences', [$this, 'ajax_get_preferences']);
        add_action('wp_ajax_sffc_crm_send_test_digest', [$this, 'ajax_send_test_digest']);
    }

    /**
     * Schedule cron events for digests
     */
    public function schedule_digest_events() {
        // Daily digest at 8 AM
        if (!wp_next_scheduled('sffc_crm_daily_digest')) {
            $time = strtotime('today 8:00:00');
            if ($time < time()) {
                $time = strtotime('tomorrow 8:00:00');
            }
            wp_schedule_event($time, 'daily', 'sffc_crm_daily_digest');
        }

        // Weekly digest on Monday at 8 AM
        if (!wp_next_scheduled('sffc_crm_weekly_digest')) {
            $time = strtotime('next monday 8:00:00');
            wp_schedule_event($time, 'weekly', 'sffc_crm_weekly_digest');
        }
    }

    /**
     * Send daily digests to subscribed users
     */
    public function send_daily_digests() {
        $users = $this->get_digest_subscribers('daily');

        foreach ($users as $user_id) {
            $this->send_digest($user_id, 'daily');
        }

        // Log execution
        update_option('sffc_crm_last_daily_digest', current_time('mysql'));
    }

    /**
     * Send weekly digests to subscribed users
     */
    public function send_weekly_digests() {
        $users = $this->get_digest_subscribers('weekly');

        foreach ($users as $user_id) {
            $this->send_digest($user_id, 'weekly');
        }

        // Log execution
        update_option('sffc_crm_last_weekly_digest', current_time('mysql'));
    }

    /**
     * Get users subscribed to digest
     */
    private function get_digest_subscribers($frequency) {
        global $wpdb;

        $meta_key = 'sffc_crm_digest_' . $frequency;

        $users = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta}
             WHERE meta_key = %s AND meta_value = '1'",
            $meta_key
        ));

        return $users ?: [];
    }

    /**
     * Send digest to specific user
     */
    public function send_digest($user_id, $type = 'daily') {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }

        $locale = $this->get_user_locale_code($user);

        // Get digest data
        $data = $this->get_digest_data($user_id, $type);

        // Skip if no activity
        if ($this->is_empty_digest($data)) {
            return false;
        }

        // Generate email content
        $subject = $this->get_email_subject($type, $data, $locale);
        $body = $this->generate_email_body($user, $data, $type, $locale);

        // Send email
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: MENA Careers <noreply@' . parse_url(home_url(), PHP_URL_HOST) . '>',
        ];

        $sent = wp_mail($user->user_email, $subject, $body, $headers);

        // Log
        if ($sent) {
            $this->log_digest_sent($user_id, $type);
        }

        return $sent;
    }

    /**
     * Get digest data for user
     */
    private function get_digest_data($user_id, $type) {
        $date_from = $type === 'daily'
            ? date('Y-m-d', strtotime('-1 day'))
            : date('Y-m-d', strtotime('-7 days'));

        $data = [
            'new_posts' => $this->get_new_posts($user_id, $date_from),
            'pending_tasks' => $this->get_pending_tasks($user_id),
            'pipeline_updates' => $this->get_pipeline_updates($user_id, $date_from),
            'outreach_stats' => $this->get_outreach_stats($user_id, $date_from),
            'responses' => $this->get_new_responses($user_id, $date_from),
            'alerts_triggered' => $this->get_triggered_alerts($user_id, $date_from),
        ];

        return $data;
    }

    /**
     * Get new posts matching user preferences
     */
    private function get_new_posts($user_id, $date_from) {
        global $wpdb;

        $table = $wpdb->prefix . 'sffc_crm_posts';

        // Get user's target sectors/locations from profile
        $target_sectors = get_user_meta($user_id, 'target_sectors', true) ?: [];
        $target_locations = get_user_meta($user_id, 'target_locations', true) ?: [];

        $query = "SELECT id, role_title, location, recruiter_name, recruiter_firm, posted_at
                  FROM {$table}
                  WHERE posted_at >= %s
                  ORDER BY posted_at DESC
                  LIMIT 10";

        $posts = $wpdb->get_results($wpdb->prepare($query, $date_from));

        return $posts ?: [];
    }

    /**
     * Get pending tasks for user
     */
    private function get_pending_tasks($user_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'sffc_crm_tasks';

        $tasks = $wpdb->get_results($wpdb->prepare(
            "SELECT id, title, type, due_date, recruiter_id
             FROM {$table}
             WHERE user_id = %d
             AND completed_at IS NULL
             AND due_date <= DATE_ADD(NOW(), INTERVAL 1 DAY)
             ORDER BY due_date ASC
             LIMIT 10",
            $user_id
        ));

        return $tasks ?: [];
    }

    /**
     * Get pipeline updates
     */
    private function get_pipeline_updates($user_id, $date_from) {
        global $wpdb;

        $table = $wpdb->prefix . 'sffc_crm_pipeline';

        $updates = $wpdb->get_results($wpdb->prepare(
            "SELECT id, stage, updated_at, recruiter_id
             FROM {$table}
             WHERE user_id = %d
             AND updated_at >= %s
             ORDER BY updated_at DESC
             LIMIT 10",
            $user_id,
            $date_from
        ));

        return $updates ?: [];
    }

    /**
     * Get outreach stats
     */
    private function get_outreach_stats($user_id, $date_from) {
        global $wpdb;

        $table = $wpdb->prefix . 'sffc_crm_outreach';

        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as total_sent,
                SUM(CASE WHEN status = 'opened' THEN 1 ELSE 0 END) as opened,
                SUM(CASE WHEN status = 'replied' THEN 1 ELSE 0 END) as replied
             FROM {$table}
             WHERE user_id = %d
             AND sent_at >= %s",
            $user_id,
            $date_from
        ));

        return $stats;
    }

    /**
     * Get new responses
     */
    private function get_new_responses($user_id, $date_from) {
        global $wpdb;

        $table = $wpdb->prefix . 'sffc_crm_conversations';

        $responses = $wpdb->get_results($wpdb->prepare(
            "SELECT c.id, c.recruiter_id, c.last_message_at, r.name as recruiter_name
             FROM {$table} c
             LEFT JOIN {$wpdb->prefix}sffc_crm_recruiters r ON c.recruiter_id = r.id
             WHERE c.user_id = %d
             AND c.updated_at >= %s
             AND c.last_message_from = 'recruiter'
             ORDER BY c.last_message_at DESC
             LIMIT 5",
            $user_id,
            $date_from
        ));

        return $responses ?: [];
    }

    /**
     * Get triggered alerts
     */
    private function get_triggered_alerts($user_id, $date_from) {
        global $wpdb;

        $table = $wpdb->prefix . 'sffc_crm_notifications';

        $alerts = $wpdb->get_results($wpdb->prepare(
            "SELECT id, title, message, type, created_at
             FROM {$table}
             WHERE user_id = %d
             AND created_at >= %s
             ORDER BY created_at DESC
             LIMIT 10",
            $user_id,
            $date_from
        ));

        return $alerts ?: [];
    }

    /**
     * Check if digest has no content
     */
    private function is_empty_digest($data) {
        return empty($data['new_posts'])
            && empty($data['pending_tasks'])
            && empty($data['responses'])
            && empty($data['pipeline_updates'])
            && (empty($data['outreach_stats']) || $data['outreach_stats']->total_sent == 0);
    }

    /**
     * Get email subject
     */
    private function get_email_subject($type, $data, $locale = '') {
        $response_count = count($data['responses'] ?? []);
        $task_count = count($data['pending_tasks'] ?? []);
        $is_arabic = $this->is_arabic_locale($locale);

        if ($response_count > 0) {
            if ($is_arabic) {
                return sprintf('لديك %d رد جديد من جهات التوظيف', $response_count);
            }
            $plural = $response_count === 1 ? 'response' : 'responses';
            return "You have {$response_count} new recruiter {$plural}!";
        }

        if ($task_count > 0) {
            if ($is_arabic) {
                return sprintf('لديك %d مهام مستحقة اليوم', $task_count);
            }
            $plural = $task_count === 1 ? 'task' : 'tasks';
            return "You have {$task_count} {$plural} due today";
        }

        $period = $type === 'daily'
            ? $this->localize_copy('Daily', 'اليومي', $locale)
            : $this->localize_copy('Weekly', 'الأسبوعي', $locale);

        return $this->localize_copy(
            "Your {$period} CRM Digest",
            sprintf('ملخص سنّا %s', $period),
            $locale
        );
    }

    /**
     * Generate email body HTML
     */
    private function generate_email_body($user, $data, $type, $locale = '') {
        $first_name = $user->first_name ?: $user->display_name;
        $crm_url = home_url('/senna-recruiter-outreach/');
        $period = $type === 'daily'
            ? $this->localize_copy('today', 'اليوم', $locale)
            : $this->localize_copy('this week', 'هذا الأسبوع', $locale);
        $is_arabic = $this->is_arabic_locale($locale);
        $dir = $is_arabic ? 'rtl' : 'ltr';
        $align = $is_arabic ? 'right' : 'left';
        $first_name = $first_name ?: $this->localize_copy('there', 'هناك', $locale);

        ob_start();
        ?>
        <!DOCTYPE html>
        <html dir="<?php echo esc_attr($dir); ?>">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo esc_html($this->localize_copy('Your CRM Digest', 'ملخص سنّا', $locale)); ?></title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Tahoma, Arial, sans-serif; line-height: 1.6; color: #1a1a1a; margin: 0; padding: 0; background: #f5f5f5; direction: <?php echo esc_html($dir); ?>; text-align: <?php echo esc_html($align); ?>; }
                .container { max-width: 600px; margin: 0 auto; background: #ffffff; }
                .header { background: #0D353E; color: #ffffff; padding: 32px 24px; text-align: center; }
                .header h1 { margin: 0; font-size: 24px; font-weight: 600; }
                .content { padding: 24px; }
                .greeting { font-size: 18px; margin-bottom: 24px; }
                .section { margin-bottom: 32px; }
                .section-title { font-size: 16px; font-weight: 600; color: #0D353E; margin: 0 0 16px 0; padding-bottom: 8px; border-bottom: 2px solid #0D353E; }
                .stat-grid { display: flex; gap: 16px; margin-bottom: 16px; }
                .stat-box { flex: 1; background: #fff5ee; padding: 16px; border-radius: 8px; text-align: center; }
                .stat-value { font-size: 28px; font-weight: 700; color: #0D353E; }
                .stat-label { font-size: 12px; color: #6b7280; text-transform: uppercase; }
                .item-list { list-style: none; padding: 0; margin: 0; }
                .item { display: flex; align-items: flex-start; gap: 12px; padding: 12px; background: #f9fafb; border-radius: 8px; margin-bottom: 8px; }
                .item-icon { width: 40px; height: 40px; background: #0D353E; color: #ffffff; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
                .item-content { flex: 1; }
                .item-title { font-weight: 500; color: #1a1a1a; margin-bottom: 2px; }
                .item-meta { font-size: 13px; color: #6b7280; }
                .response-item { background: #ecfdf5; border-left: 3px solid #059669; }
                .task-item { background: #fef3c7; border-left: 3px solid #d97706; }
                .cta-button { display: inline-block; background: #0D353E; color: #ffffff !important; padding: 14px 28px; border-radius: 8px; text-decoration: none; font-weight: 500; margin-top: 16px; }
                .footer { background: #f9fafb; padding: 24px; text-align: center; font-size: 13px; color: #6b7280; }
                .footer a { color: #0D353E; }
                .unsubscribe { margin-top: 16px; font-size: 12px; }
                @media (max-width: 600px) { .stat-grid { flex-direction: column; } }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php echo esc_html($this->localize_copy('MENA Careers CRM Digest', 'ملخص سنّا', $locale)); ?></h1>
                </div>

                <div class="content">
                    <p class="greeting"><?php echo esc_html($this->localize_copy("Hi {$first_name},", "مرحباً {$first_name}،", $locale)); ?></p>
                    <p><?php echo esc_html($this->localize_copy("Here is your recruitment activity summary for {$period}.", "هذا ملخص نشاطك المهني في سنّا {$period}.", $locale)); ?></p>

                    <?php if (!empty($data['outreach_stats']) && $data['outreach_stats']->total_sent > 0): ?>
                    <div class="section">
                        <h2 class="section-title"><?php echo esc_html($this->localize_copy('Outreach Overview', 'ملخص التواصل', $locale)); ?></h2>
                        <div class="stat-grid">
                            <div class="stat-box">
                                <div class="stat-value"><?php echo (int)$data['outreach_stats']->total_sent; ?></div>
                                <div class="stat-label"><?php echo esc_html($this->localize_copy('Messages Sent', 'رسائل مرسلة', $locale)); ?></div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-value"><?php echo (int)$data['outreach_stats']->opened; ?></div>
                                <div class="stat-label"><?php echo esc_html($this->localize_copy('Opened', 'تم فتحها', $locale)); ?></div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-value"><?php echo (int)$data['outreach_stats']->replied; ?></div>
                                <div class="stat-label"><?php echo esc_html($this->localize_copy('Replied', 'تم الرد', $locale)); ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($data['responses'])): ?>
                    <div class="section">
                        <h2 class="section-title"><?php echo esc_html($this->localize_copy('New Responses', 'ردود جديدة', $locale)); ?></h2>
                        <ul class="item-list">
                            <?php foreach ($data['responses'] as $response): ?>
                            <li class="item response-item">
                                <div class="item-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                                    </svg>
                                </div>
                                <div class="item-content">
                                    <div class="item-title"><?php echo esc_html($this->localize_copy($response->recruiter_name . ' replied', 'وردك رد من ' . $response->recruiter_name, $locale)); ?></div>
                                    <div class="item-meta"><?php echo esc_html($is_arabic ? 'منذ ' . human_time_diff(strtotime($response->last_message_at)) : human_time_diff(strtotime($response->last_message_at)) . ' ago'); ?></div>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($data['pending_tasks'])): ?>
                    <div class="section">
                        <h2 class="section-title"><?php echo esc_html($this->localize_copy('Tasks Due', 'مهام مستحقة', $locale)); ?></h2>
                        <ul class="item-list">
                            <?php foreach ($data['pending_tasks'] as $task): ?>
                            <li class="item task-item">
                                <div class="item-icon" style="background: #d97706;">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                    </svg>
                                </div>
                                <div class="item-content">
                                    <div class="item-title"><?php echo esc_html($task->title); ?></div>
                                    <div class="item-meta"><?php echo esc_html($this->localize_copy('Due: ', 'الموعد: ', $locale) . date('M j', strtotime($task->due_date))); ?></div>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($data['new_posts'])): ?>
                    <div class="section">
                        <h2 class="section-title"><?php echo esc_html($this->localize_copy('New Opportunities', 'فرص جديدة', $locale)); ?></h2>
                        <ul class="item-list">
                            <?php foreach (array_slice($data['new_posts'], 0, 5) as $post): ?>
                            <li class="item">
                                <div class="item-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                                        <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                                    </svg>
                                </div>
                                <div class="item-content">
                                    <div class="item-title"><?php echo esc_html($post->role_title); ?></div>
                                    <div class="item-meta"><?php echo esc_html($post->recruiter_name); ?> &bull; <?php echo esc_html($post->location); ?></div>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <div style="text-align: center; margin-top: 32px;">
                        <a href="<?php echo esc_url($crm_url); ?>" class="cta-button"><?php echo esc_html($this->localize_copy('Open Your CRM', 'افتح حسابك في سنّا', $locale)); ?></a>
                    </div>
                </div>

                <div class="footer">
                    <p><?php echo esc_html($this->localize_copy("You're receiving this because you subscribed to {$type} digests.", $type === 'daily' ? 'وصلك هذا البريد لأنك مشترك في الملخص اليومي.' : 'وصلك هذا البريد لأنك مشترك في الملخص الأسبوعي.', $locale)); ?></p>
                    <p class="unsubscribe">
                        <a href="<?php echo esc_url(add_query_arg('crm_digest_unsubscribe', wp_create_nonce('unsubscribe_' . $user->ID), home_url())); ?>"><?php echo esc_html($this->localize_copy('Unsubscribe', 'إلغاء الاشتراك', $locale)); ?></a> |
                        <a href="<?php echo esc_url($crm_url . '?tab=settings'); ?>"><?php echo esc_html($this->localize_copy('Update Preferences', 'تحديث التفضيلات', $locale)); ?></a>
                    </p>
                    <p style="margin-top: 16px;">
                        <strong>MENA Careers Private Equity</strong><br>
                        <?php echo esc_html($this->localize_copy('Your career intelligence platform', 'منصتك الذكية لمسارك المهني', $locale)); ?>
                    </p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Log digest sent
     */
    private function log_digest_sent($user_id, $type) {
        update_user_meta($user_id, 'sffc_crm_last_digest_sent', current_time('mysql'));
        update_user_meta($user_id, 'sffc_crm_last_digest_type', $type);
    }

    /**
     * AJAX: Update digest preferences
     */
    public function ajax_update_preferences() {
        check_ajax_referer('sffc_crm_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Not authenticated'], 401);
        }

        $user_id = get_current_user_id();
        $daily = isset($_POST['daily']) && $_POST['daily'] === 'true';
        $weekly = isset($_POST['weekly']) && $_POST['weekly'] === 'true';

        update_user_meta($user_id, 'sffc_crm_digest_daily', $daily ? '1' : '0');
        update_user_meta($user_id, 'sffc_crm_digest_weekly', $weekly ? '1' : '0');

        wp_send_json_success([
            'daily' => $daily,
            'weekly' => $weekly,
        ]);
    }

    /**
     * AJAX: Get digest preferences
     */
    public function ajax_get_preferences() {
        check_ajax_referer('sffc_crm_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Not authenticated'], 401);
        }

        $user_id = get_current_user_id();
        $locale = $this->get_user_locale_code(wp_get_current_user());

        wp_send_json_success([
            'daily' => get_user_meta($user_id, 'sffc_crm_digest_daily', true) === '1',
            'weekly' => get_user_meta($user_id, 'sffc_crm_digest_weekly', true) === '1',
            'last_sent' => get_user_meta($user_id, 'sffc_crm_last_digest_sent', true),
            'message' => $this->localize_copy('Preferences loaded.', 'تم تحميل التفضيلات.', $locale),
        ]);
    }

    /**
     * AJAX: Send test digest
     */
    public function ajax_send_test_digest() {
        check_ajax_referer('sffc_crm_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Not authenticated'], 401);
        }

        $user_id = get_current_user_id();
        $type = sanitize_text_field($_POST['type'] ?? 'daily');
        $locale = $this->get_user_locale_code(wp_get_current_user());

        $result = $this->send_digest($user_id, $type);

        if ($result) {
            wp_send_json_success(['message' => $this->localize_copy('Test digest sent.', 'تم إرسال الرسالة التجريبية.', $locale)]);
        } else {
            // Send anyway with sample data for testing
            $this->send_sample_digest($user_id, $type);
            wp_send_json_success(['message' => $this->localize_copy('Test digest sent (with sample data).', 'تم إرسال الرسالة التجريبية ببيانات نموذجية.', $locale)]);
        }
    }

    /**
     * Send sample digest for testing
     */
    private function send_sample_digest($user_id, $type) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }
        $locale = $this->get_user_locale_code($user);

        // Sample data
        $data = [
            'outreach_stats' => (object)[
                'total_sent' => 15,
                'opened' => 8,
                'replied' => 3,
            ],
            'responses' => [
                (object)[
                    'recruiter_name' => 'Sarah Mitchell',
                    'last_message_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                ],
                (object)[
                    'recruiter_name' => 'James Chen',
                    'last_message_at' => date('Y-m-d H:i:s', strtotime('-5 hours')),
                ],
            ],
            'pending_tasks' => [
                (object)[
                    'title' => 'Follow up with Heidrick & Struggles',
                    'type' => 'follow_up',
                    'due_date' => date('Y-m-d'),
                ],
            ],
            'new_posts' => [
                (object)[
                    'role_title' => 'VP Private Equity - Dubai',
                    'recruiter_name' => 'Sarah Mitchell',
                    'location' => 'Dubai, UAE',
                    'posted_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                ],
                (object)[
                    'role_title' => 'Fund Controller - London',
                    'recruiter_name' => 'Mush Ali',
                    'location' => 'London, UK',
                    'posted_at' => date('Y-m-d H:i:s', strtotime('-3 hours')),
                ],
            ],
            'pipeline_updates' => [],
            'alerts_triggered' => [],
        ];

        $subject = $this->localize_copy('[TEST] ', '[اختبار] ', $locale) . $this->get_email_subject($type, $data, $locale);
        $body = $this->generate_email_body($user, $data, $type, $locale);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: MENA Careers <noreply@' . parse_url(home_url(), PHP_URL_HOST) . '>',
        ];

        return wp_mail($user->user_email, $subject, $body, $headers);
    }

    /**
     * Handle unsubscribe
     */
    public static function handle_unsubscribe() {
        if (!isset($_GET['crm_digest_unsubscribe'])) {
            return;
        }

        $user = wp_get_current_user();
        if (!$user->ID) {
            wp_redirect(wp_login_url(add_query_arg('crm_digest_unsubscribe', $_GET['crm_digest_unsubscribe'], home_url())));
            exit;
        }

        $nonce = sanitize_text_field($_GET['crm_digest_unsubscribe']);
        if (!wp_verify_nonce($nonce, 'unsubscribe_' . $user->ID)) {
            wp_die('Invalid unsubscribe link');
        }

        update_user_meta($user->ID, 'sffc_crm_digest_daily', '0');
        update_user_meta($user->ID, 'sffc_crm_digest_weekly', '0');

        wp_redirect(add_query_arg('digest_unsubscribed', '1', home_url('/senna-recruiter-outreach/')));
        exit;
    }
}

// Handle unsubscribe early
add_action('template_redirect', ['SFFC_CRM_Email_Digest', 'handle_unsubscribe']);
