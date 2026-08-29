<?php
/**
 * Candidate Notifications System
 *
 * Sends email notifications when:
 * - A new opportunity is created for a candidate
 * - A new message is received in a conversation
 *
 * @package SennaFinanceCareer
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Candidate_Notifications {

    private static $instance = null;

    /**
     * Email configuration
     */
    private $sender_name = 'MENA Careers';
    private $sender_email = 'support.team@skillfarm.co';

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize notification hooks
     */
    private function init_hooks() {
        // Notify when opportunity is published
        add_action('transition_post_status', [$this, 'notify_new_opportunity'], 10, 3);

        // Notify when admin sends a message
        add_action('sffc_conversation_message_sent', [$this, 'notify_new_message'], 10, 3);
    }

    /**
     * Send notification when a new opportunity is created
     */
    public function notify_new_opportunity($new_status, $old_status, $post) {
        // Only for our opportunity CPT
        if ($post->post_type !== 'sffc_candidate_opp') {
            return;
        }

        // Only when publishing (new or from draft)
        if ($new_status !== 'publish' || $old_status === 'publish') {
            return;
        }

        // Get candidate user
        $candidate_id = get_post_meta($post->ID, '_candidate_user_id', true);
        if (!$candidate_id) {
            return;
        }

        $candidate = get_userdata($candidate_id);
        if (!$candidate) {
            return;
        }

        // Check if user wants notifications
        if ($this->user_disabled_notifications($candidate_id)) {
            return;
        }

        // Get opportunity details
        $job_title = $post->post_title;
        $company = get_post_meta($post->ID, '_company', true);
        $location = get_post_meta($post->ID, '_location', true);
        $salary = get_post_meta($post->ID, '_salary', true);
        $recruiter_name = get_post_meta($post->ID, '_recruiter_name', true);
        $recruiter_company = get_post_meta($post->ID, '_recruiter_company', true);
        $recruiter_status = get_post_meta($post->ID, '_recruiter_status', true);
        $match_reasons = get_post_meta($post->ID, '_match_reasons', true);

        // Build email
        $subject = sprintf('New Career Opportunity: %s at %s', $job_title, $company);

        $email_content = $this->get_opportunity_email_template([
            'recipient' => $candidate,
            'job_title' => $job_title,
            'company' => $company,
            'location' => $location,
            'salary' => $salary,
            'recruiter_name' => $recruiter_name,
            'recruiter_company' => $recruiter_company,
            'recruiter_status' => $recruiter_status,
            'match_reasons' => is_array($match_reasons) ? $match_reasons : [],
        ]);

        $this->send_email($candidate->user_email, $subject, $email_content);
    }

    /**
     * Send notification when admin sends a message
     *
     * @param int $conversation_id Post ID of conversation
     * @param int $message_id Message ID in database
     * @param array $message_data Message details
     */
    public function notify_new_message($conversation_id, $message_id, $message_data) {
        // Only notify for admin messages to candidates
        if ($message_data['sender_type'] !== 'admin') {
            return;
        }

        // Get candidate user
        $candidate_id = get_post_meta($conversation_id, '_candidate_user_id', true);
        if (!$candidate_id) {
            return;
        }

        $candidate = get_userdata($candidate_id);
        if (!$candidate) {
            return;
        }

        // Check if user wants notifications
        if ($this->user_disabled_notifications($candidate_id)) {
            return;
        }

        // Get contact info
        $contact_name = get_post_meta($conversation_id, '_contact_name', true) ?: 'Your Career Advisor';
        $contact_company = get_post_meta($conversation_id, '_contact_company', true);

        // Build email
        $subject = sprintf('New message from %s', $contact_name);

        $email_content = $this->get_message_email_template([
            'recipient' => $candidate,
            'contact_name' => $contact_name,
            'contact_company' => $contact_company,
            'message_preview' => $message_data['message_text'],
            'conversation_id' => $conversation_id,
        ]);

        $this->send_email($candidate->user_email, $subject, $email_content);
    }

    /**
     * Check if user has disabled email notifications
     */
    private function user_disabled_notifications($user_id) {
        $setting = get_user_meta($user_id, 'senna_email_notifications', true);
        return $setting === 'no';
    }

    /**
     * Send HTML email
     */
    private function send_email($to, $subject, $html_content) {
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            sprintf('From: %s <%s>', $this->sender_name, $this->sender_email),
        ];

        wp_mail($to, $subject, $html_content, $headers);
    }

    /**
     * Get opportunity notification email template
     */
    private function get_opportunity_email_template($data) {
        $dashboard_url = home_url('/newsroom-terminal/');
        $current_date = date('F j, Y');
        $recipient = $data['recipient'];

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8" />
            <title>New Career Opportunity</title>
            <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        </head>
        <body style="margin: 0; padding: 0; background-color: #faf6ef">
            <!-- Preheader -->
            <div style="display: none; font-size: 1px; color: #faf6ef; line-height: 1px; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden;">
                A recruiter is interested in your profile for <?php echo esc_html($data['job_title']); ?> at <?php echo esc_html($data['company']); ?>
            </div>

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #faf6ef">
                <tr>
                    <td align="center" style="padding: 20px 10px">
                        <table role="presentation" cellpadding="0" cellspacing="0" width="100%"
                               style="max-width: 600px; background-color: #ffffff; border-radius: 6px; overflow: hidden; border: 1px solid #eee;">

                            <!-- HEADER -->
                            <tr>
                                <td style="padding: 16px 24px; background-color: #0d353e; font-family: Arial, sans-serif;">
                                    <table width="100%">
                                        <tr>
                                            <td style="font-size: 20px; font-weight: bold; color: #ffffff">
                                                MENA Careers
                                            </td>
                                            <td align="right" style="font-size: 12px; color: #97ffd5">
                                                <?php echo esc_html($current_date); ?>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>

                            <!-- NOTIFICATION BAR -->
                            <tr>
                                <td style="padding: 24px; font-family: Arial, sans-serif; border-bottom: 1px solid #eee;">
                                    <div style="font-size: 12px; letter-spacing: 1px; text-transform: uppercase; color: #000; background-color: #97ffd5; display: inline-block; padding: 3px 6px; border-radius: 3px; margin-bottom: 16px;">
                                        New Opportunity
                                    </div>

                                    <h1 style="margin: 0 0 12px; font-size: 22px; color: #0d353e; font-family: Arial, sans-serif;">
                                        <?php echo esc_html($data['job_title']); ?>
                                    </h1>

                                    <p style="margin: 0 0 8px; font-size: 16px; color: #333;">
                                        <strong><?php echo esc_html($data['company']); ?></strong>
                                    </p>

                                    <?php if (!empty($data['location'])): ?>
                                    <p style="margin: 0 0 8px; font-size: 14px; color: #666;">
                                        <?php echo esc_html($data['location']); ?>
                                    </p>
                                    <?php endif; ?>

                                    <?php if (!empty($data['salary'])): ?>
                                    <p style="margin: 0; font-size: 14px; color: #666;">
                                        <?php echo esc_html($data['salary']); ?>
                                    </p>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <!-- RECRUITER INFO -->
                            <?php if (!empty($data['recruiter_name'])): ?>
                            <tr>
                                <td style="padding: 24px; font-family: Arial, sans-serif; background-color: #faf8f3;">
                                    <table width="100%">
                                        <tr>
                                            <td style="width: 50px; vertical-align: top;">
                                                <div style="width: 44px; height: 44px; background-color: #0d353e; border-radius: 6px; color: #ffffff; font-weight: bold; font-size: 18px; text-align: center; line-height: 44px;">
                                                    <?php echo esc_html(strtoupper(substr($data['recruiter_name'], 0, 1))); ?>
                                                </div>
                                            </td>
                                            <td style="padding-left: 16px; vertical-align: top;">
                                                <div style="font-size: 16px; font-weight: bold; color: #0d353e; margin-bottom: 4px;">
                                                    <?php echo esc_html($data['recruiter_name']); ?>
                                                </div>
                                                <?php if (!empty($data['recruiter_company'])): ?>
                                                <div style="font-size: 13px; color: #666; margin-bottom: 4px;">
                                                    <?php echo esc_html($data['recruiter_company']); ?>
                                                </div>
                                                <?php endif; ?>
                                                <?php if (!empty($data['recruiter_status'])): ?>
                                                <div style="font-size: 13px; color: #0d353e; font-style: italic;">
                                                    <?php echo esc_html($data['recruiter_status']); ?>
                                                </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <?php endif; ?>

                            <!-- MATCH REASONS -->
                            <?php if (!empty($data['match_reasons'])): ?>
                            <tr>
                                <td style="padding: 24px; font-family: Arial, sans-serif;">
                                    <h3 style="margin: 0 0 12px; font-size: 14px; color: #0d353e; text-transform: uppercase; letter-spacing: 0.5px;">
                                        Why You're a Great Match
                                    </h3>
                                    <ul style="margin: 0; padding-left: 18px; font-size: 14px; line-height: 1.8; color: #333;">
                                        <?php foreach ($data['match_reasons'] as $reason): ?>
                                        <li style="margin-bottom: 4px;"><?php echo esc_html($reason); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </td>
                            </tr>
                            <?php endif; ?>

                            <!-- CTA -->
                            <tr>
                                <td style="padding: 24px; text-align: center;">
                                    <a href="<?php echo esc_url($dashboard_url); ?>"
                                       style="display: inline-block; padding: 14px 28px; border-radius: 4px; background-color: #0d353e; color: #ffffff; font-size: 14px; font-weight: 600; text-decoration: none; font-family: Arial, sans-serif;">
                                        View Opportunity Details
                                    </a>
                                </td>
                            </tr>

                            <!-- FOOTER -->
                            <tr>
                                <td style="padding: 16px 24px 24px; font-family: Arial, sans-serif; font-size: 11px; line-height: 1.6; color: #666; text-align: center; background-color: #faf6ef;">
                                    You're receiving this because you're a MENA Careers member.<br />
                                    <a href="<?php echo esc_url(home_url('/dashboard/settings/')); ?>" style="color: #0d353e">
                                        Manage email preferences
                                    </a><br /><br />
                                    &copy; <?php echo date('Y'); ?> MENA Careers — Connecting exceptional talent with extraordinary opportunities.
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Get message notification email template
     */
    private function get_message_email_template($data) {
        $dashboard_url = home_url('/newsroom-terminal/');
        $current_date = date('F j, Y');
        $recipient = $data['recipient'];

        // Truncate message preview
        $message_preview = wp_strip_all_tags($data['message_preview']);
        if (strlen($message_preview) > 200) {
            $message_preview = substr($message_preview, 0, 200) . '...';
        }

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8" />
            <title>New Message from <?php echo esc_html($data['contact_name']); ?></title>
            <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        </head>
        <body style="margin: 0; padding: 0; background-color: #faf6ef">
            <!-- Preheader -->
            <div style="display: none; font-size: 1px; color: #faf6ef; line-height: 1px; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden;">
                <?php echo esc_html($data['contact_name']); ?> sent you a message. View and reply in your dashboard.
            </div>

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #faf6ef">
                <tr>
                    <td align="center" style="padding: 20px 10px">
                        <table role="presentation" cellpadding="0" cellspacing="0" width="100%"
                               style="max-width: 600px; background-color: #ffffff; border-radius: 6px; overflow: hidden; border: 1px solid #eee;">

                            <!-- HEADER -->
                            <tr>
                                <td style="padding: 16px 24px; background-color: #0d353e; font-family: Arial, sans-serif;">
                                    <table width="100%">
                                        <tr>
                                            <td style="font-size: 20px; font-weight: bold; color: #ffffff">
                                                MENA Careers
                                            </td>
                                            <td align="right" style="font-size: 12px; color: #97ffd5">
                                                <?php echo esc_html($current_date); ?>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>

                            <!-- NOTIFICATION -->
                            <tr>
                                <td style="padding: 24px; font-family: Arial, sans-serif; border-bottom: 1px solid #eee;">
                                    <div style="font-size: 12px; letter-spacing: 1px; text-transform: uppercase; color: #000; background-color: #97ffd5; display: inline-block; padding: 3px 6px; border-radius: 3px; margin-bottom: 16px;">
                                        New Message
                                    </div>

                                    <h1 style="margin: 0 0 12px; font-size: 22px; color: #0d353e; font-family: Arial, sans-serif;">
                                        Message from <?php echo esc_html($data['contact_name']); ?>
                                    </h1>

                                    <p style="margin: 0; font-size: 14px; line-height: 1.6; color: #666;">
                                        Hello <?php echo esc_html($recipient->display_name); ?>, you have a new message in your inbox.
                                    </p>
                                </td>
                            </tr>

                            <!-- MESSAGE CONTENT -->
                            <tr>
                                <td style="padding: 24px; font-family: Arial, sans-serif; background-color: #faf8f3;">
                                    <!-- Sender Info -->
                                    <table width="100%" style="margin-bottom: 20px;">
                                        <tr>
                                            <td style="width: 50px; vertical-align: top;">
                                                <div style="width: 44px; height: 44px; background-color: #0d353e; border-radius: 6px; color: #ffffff; font-weight: bold; font-size: 18px; text-align: center; line-height: 44px;">
                                                    <?php echo esc_html(strtoupper(substr($data['contact_name'], 0, 1))); ?>
                                                </div>
                                            </td>
                                            <td style="padding-left: 16px; vertical-align: top;">
                                                <div style="font-size: 16px; font-weight: bold; color: #0d353e; margin-bottom: 4px;">
                                                    <?php echo esc_html($data['contact_name']); ?>
                                                </div>
                                                <?php if (!empty($data['contact_company'])): ?>
                                                <div style="font-size: 13px; color: #666;">
                                                    <?php echo esc_html($data['contact_company']); ?>
                                                </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    </table>

                                    <!-- Message Preview -->
                                    <div style="background-color: #ffffff; padding: 20px; border-radius: 6px; border: 1px solid #e0e0e0; margin-bottom: 20px;">
                                        <p style="margin: 0; font-size: 15px; line-height: 1.6; color: #333;">
                                            <?php echo esc_html($message_preview); ?>
                                        </p>
                                    </div>

                                    <!-- CTA Button -->
                                    <div style="text-align: center;">
                                        <a href="<?php echo esc_url($dashboard_url); ?>"
                                           style="display: inline-block; padding: 14px 28px; border-radius: 4px; background-color: #0d353e; color: #ffffff; font-size: 14px; font-weight: 600; text-decoration: none; font-family: Arial, sans-serif;">
                                            View Message & Reply
                                        </a>
                                    </div>
                                </td>
                            </tr>

                            <!-- FOOTER -->
                            <tr>
                                <td style="padding: 16px 24px 24px; font-family: Arial, sans-serif; font-size: 11px; line-height: 1.6; color: #666; text-align: center; background-color: #faf6ef;">
                                    You're receiving this because you're a MENA Careers member.<br />
                                    <a href="<?php echo esc_url(home_url('/dashboard/settings/')); ?>" style="color: #0d353e">
                                        Manage email preferences
                                    </a><br /><br />
                                    &copy; <?php echo date('Y'); ?> MENA Careers — Connecting exceptional talent with extraordinary opportunities.
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}

// Initialize
SFFC_Candidate_Notifications::get_instance();
