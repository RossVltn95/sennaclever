<?php
/**
 * Recruiter Terminal Email System
 *
 * Handles email sending, tracking, and queue processing.
 *
 * @package SennaFinanceCareer
 * @subpackage RecruiterTerminal
 */

if (!defined('ABSPATH')) {
    exit;
}

class Recruiter_Terminal_Email {

    /**
     * Cron hook name
     */
    const CRON_HOOK = 'rt_process_email_queue';

    /**
     * Emails to send per cron run
     */
    const BATCH_SIZE = 20;

    /**
     * Initialize email system
     */
    public static function init() {
        // Register cron schedule
        add_filter('cron_schedules', array(__CLASS__, 'add_cron_schedule'));

        // Schedule cron if not already scheduled
        add_action('init', array(__CLASS__, 'schedule_cron'));

        // Process email queue
        add_action(self::CRON_HOOK, array(__CLASS__, 'process_queue'));

        // Add HTML content type filter for emails
        add_filter('wp_mail_content_type', array(__CLASS__, 'set_html_content_type'));
    }

    /**
     * Add custom cron schedule
     */
    public static function add_cron_schedule($schedules) {
        if (!isset($schedules['every_five_minutes'])) {
            $schedules['every_five_minutes'] = array(
                'interval' => 300, // 5 minutes
                'display'  => __('Every 5 Minutes', 'senna-finance'),
            );
        }
        return $schedules;
    }

    /**
     * Schedule cron job
     */
    public static function schedule_cron() {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'every_five_minutes', self::CRON_HOOK);
        }
    }

    /**
     * Set HTML content type for wp_mail
     */
    public static function set_html_content_type() {
        return 'text/html';
    }

    /**
     * Process email queue
     */
    public static function process_queue() {
        // Get campaigns ready to send
        $campaigns = Recruiter_Terminal_DB::get_campaigns_ready_to_send();

        if (empty($campaigns)) {
            return;
        }

        foreach ($campaigns as $campaign) {
            // Update campaign to active
            if ($campaign->status === 'approved') {
                Recruiter_Terminal_DB::update_campaign($campaign->id, array(
                    'status' => 'active',
                ));

                Recruiter_Terminal_DB::log_activity($campaign->id, null, 'campaign_activated', array());
            }

            // Get pending targets
            $targets = Recruiter_Terminal_DB::get_pending_targets($campaign->id, self::BATCH_SIZE);

            if (empty($targets)) {
                // Check if campaign is complete
                self::check_campaign_completion($campaign->id);
                continue;
            }

            // Get recruiter info
            $recruiter = get_userdata($campaign->user_id);

            foreach ($targets as $target) {
                // Send email
                $sent = self::send_email($campaign, $target, $recruiter);

                if ($sent) {
                    // Update target status
                    Recruiter_Terminal_DB::update_target($target->id, array(
                        'email_status' => 'sent',
                        'sent_at'      => current_time('mysql'),
                    ));

                    // Log activity
                    Recruiter_Terminal_DB::log_activity(
                        $campaign->id,
                        $target->id,
                        'email_sent',
                        array('email' => $target->candidate_email)
                    );
                } else {
                    // Mark as failed/bounced
                    Recruiter_Terminal_DB::update_target($target->id, array(
                        'email_status' => 'bounced',
                    ));

                    Recruiter_Terminal_DB::log_activity(
                        $campaign->id,
                        $target->id,
                        'email_failed',
                        array('email' => $target->candidate_email)
                    );
                }

                // Small delay between emails
                usleep(100000); // 0.1 second
            }
        }
    }

    /**
     * Send email to candidate
     */
    public static function send_email($campaign, $target, $recruiter) {
        // Get email template
        $template = Recruiter_Terminal_DB::get_default_template();

        // Determine subject and body
        $subject = !empty($campaign->outreach_subject) ? $campaign->outreach_subject : $template->subject;
        $body = !empty($campaign->outreach_message) ? $campaign->outreach_message : $template->body;

        // Build tracking URLs
        $track_url = home_url('/rt-track/');
        $respond_url = home_url('/rt-respond/');

        // Response buttons HTML
        $response_buttons = sprintf(
            '<table cellpadding="0" cellspacing="0" border="0" style="margin: 20px 0;">
                <tr>
                    <td style="padding-right: 10px;">
                        <a href="%s" style="display: inline-block; padding: 12px 24px; background-color: #1e6b4a; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 500;">Yes, I\'m interested</a>
                    </td>
                    <td>
                        <a href="%s" style="display: inline-block; padding: 12px 24px; background-color: #f4f4f5; color: #3f3f46; text-decoration: none; border-radius: 6px; font-weight: 500;">No thanks</a>
                    </td>
                </tr>
            </table>',
            esc_url($respond_url . $target->tracking_id . '/interested/'),
            esc_url($respond_url . $target->tracking_id . '/not_interested/')
        );

        // Unsubscribe link
        $unsubscribe_link = sprintf(
            '<a href="%s" style="color: #71717a; font-size: 12px;">Unsubscribe from future emails</a>',
            esc_url($respond_url . $target->tracking_id . '/unsubscribe/')
        );

        // Variable replacements
        $variables = array(
            '{{candidate_name}}'    => $target->candidate_name,
            '{{candidate_title}}'   => $target->candidate_title,
            '{{candidate_company}}' => $target->candidate_company,
            '{{role_title}}'        => $campaign->title,
            '{{recruiter_name}}'    => $recruiter->display_name,
            '{{response_buttons}}'  => $response_buttons,
            '{{unsubscribe_link}}'  => $unsubscribe_link,
        );

        // Replace variables
        foreach ($variables as $key => $value) {
            $subject = str_replace($key, $value, $subject);
            $body = str_replace($key, $value, $body);
        }

        // Build HTML email
        $html_body = self::build_html_email($body, $target->tracking_id);

        // Set headers
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $recruiter->display_name . ' via MENA Careers <noreply@' . parse_url(home_url(), PHP_URL_HOST) . '>',
            'Reply-To: ' . $recruiter->user_email,
        );

        // Send email
        $sent = wp_mail(
            $target->candidate_email,
            $subject,
            $html_body,
            $headers
        );

        return $sent;
    }

    /**
     * Build HTML email wrapper
     */
    private static function build_html_email($body, $tracking_id) {
        $track_pixel = home_url('/rt-track/open/' . $tracking_id . '/');

        // The body already contains HTML (response buttons, unsubscribe link)
        // Process to escape plain text but preserve the HTML
        $body_html = self::process_email_body($body);

        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Opportunity</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 16px;
            line-height: 1.6;
            color: #18181b;
            background-color: #f4f4f5;
        }
        .email-wrapper {
            max-width: 600px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        .email-content {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 40px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        .email-body {
            margin-bottom: 30px;
        }
        .email-body p {
            margin: 0 0 16px 0;
        }
        .email-footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e4e4e7;
            font-size: 12px;
            color: #71717a;
            text-align: center;
        }
        a {
            color: #2563eb;
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-content">
            <div class="email-body">
                ' . $body_html . '
            </div>
        </div>
        <div class="email-footer">
            <p>Sent via MENA Careers Intelligence</p>
        </div>
    </div>
    <img src="' . esc_url($track_pixel) . '" width="1" height="1" alt="" style="display:none;" />
</body>
</html>';

        return $html;
    }

    /**
     * Process email body - escape plain text, preserve HTML blocks
     */
    private static function process_email_body($body) {
        // Known HTML patterns that should be preserved
        $html_patterns = array(
            '/<table[^>]*>.*?<\/table>/is',  // Response buttons table
            '/<a [^>]*>.*?<\/a>/is',          // Links including unsubscribe
        );

        // Extract and replace HTML blocks with placeholders
        $placeholders = array();
        $counter = 0;

        foreach ($html_patterns as $pattern) {
            preg_match_all($pattern, $body, $matches);
            foreach ($matches[0] as $match) {
                $placeholder = '{{HTML_BLOCK_' . $counter . '}}';
                $placeholders[$placeholder] = $match;
                $body = str_replace($match, $placeholder, $body);
                $counter++;
            }
        }

        // Escape plain text and convert newlines
        $body = nl2br(esc_html($body));

        // Restore HTML blocks
        foreach ($placeholders as $placeholder => $html) {
            $body = str_replace(esc_html($placeholder), $html, $body);
        }

        return $body;
    }

    /**
     * Check if campaign is complete
     */
    private static function check_campaign_completion($campaign_id) {
        $stats = Recruiter_Terminal_DB::get_campaign_stats($campaign_id);

        // If all emails sent (none pending)
        if ($stats->pending == 0 && $stats->queued == 0) {
            $campaign = Recruiter_Terminal_DB::get_campaign($campaign_id);

            // Only mark complete if it was active
            if ($campaign && $campaign->status === 'active') {
                Recruiter_Terminal_DB::update_campaign($campaign_id, array(
                    'status' => 'completed',
                ));

                Recruiter_Terminal_DB::log_activity($campaign_id, null, 'campaign_completed', array(
                    'total_sent' => $stats->sent,
                    'total_opened' => $stats->opened,
                    'total_responded' => $stats->responded,
                ));

                // Notify recruiter
                do_action('rt_campaign_completed', $campaign_id);
            }
        }
    }

    /**
     * Send test email
     */
    public static function send_test_email($campaign_id, $test_email) {
        $campaign = Recruiter_Terminal_DB::get_campaign($campaign_id);

        if (!$campaign) {
            return new WP_Error('not_found', 'Campaign not found');
        }

        $recruiter = get_userdata($campaign->user_id);

        // Create mock target
        $target = (object) array(
            'id'                => 0,
            'candidate_email'   => $test_email,
            'candidate_name'    => 'Test Recipient',
            'candidate_title'   => 'Test Title',
            'candidate_company' => 'Test Company',
            'tracking_id'       => 'test-' . wp_generate_uuid4(),
        );

        $sent = self::send_email($campaign, $target, $recruiter);

        return $sent;
    }

    /**
     * Get email preview
     */
    public static function get_preview($campaign) {
        $template = Recruiter_Terminal_DB::get_default_template();
        $recruiter = get_userdata($campaign->user_id);

        $subject = !empty($campaign->outreach_subject) ? $campaign->outreach_subject : $template->subject;
        $body = !empty($campaign->outreach_message) ? $campaign->outreach_message : $template->body;

        // Sample variables
        $variables = array(
            '{{candidate_name}}'    => 'John Smith',
            '{{candidate_title}}'   => 'Senior Manager',
            '{{candidate_company}}' => 'Example Corp',
            '{{role_title}}'        => $campaign->title,
            '{{recruiter_name}}'    => $recruiter ? $recruiter->display_name : 'Recruiter',
            '{{response_buttons}}'  => '[Yes, I\'m interested] [No thanks]',
            '{{unsubscribe_link}}'  => '[Unsubscribe]',
        );

        foreach ($variables as $key => $value) {
            $subject = str_replace($key, $value, $subject);
            $body = str_replace($key, $value, $body);
        }

        return array(
            'subject' => $subject,
            'body'    => $body,
        );
    }

    /**
     * Clear scheduled cron
     */
    public static function unschedule_cron() {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }
}
