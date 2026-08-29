<?php
/**
 * Recruiter Terminal - Candidate Response Page
 *
 * Shown when candidates click response buttons in emails.
 *
 * @package SennaFinanceCareer
 * @subpackage RecruiterTerminal
 *
 * Variables available:
 * - $target (object): Target/candidate data
 * - $response (string): Response type (interested, not_interested, maybe)
 */

if (!defined('ABSPATH')) {
    exit;
}

// Process the response if not already done
$already_responded = !empty($target->response_status) && $target->response_status !== 'none';
$is_new_response = !$already_responded;

if ($is_new_response || $response === 'unsubscribe') {
    // Map URL response to database response status
    $response_map = array(
        'interested'     => 'interested',
        'not_interested' => 'not_interested',
        'maybe'          => 'maybe',
        'unsubscribe'    => 'unsubscribed',
    );

    $db_response = isset($response_map[$response]) ? $response_map[$response] : 'interested';

    // Update the target
    $update_data = array(
        'response_status' => $db_response,
        'responded_at'    => current_time('mysql'),
        'email_status'    => 'responded',
    );

    // For unsubscribe, also mark as unsubscribed for future campaigns
    if ($response === 'unsubscribe') {
        $update_data['unsubscribed'] = 1;
    }

    Recruiter_Terminal_DB::update_target($target->id, $update_data);

    // Log activity
    Recruiter_Terminal_DB::log_activity(
        $target->campaign_id,
        $target->id,
        'response_' . $db_response,
        array()
    );
}

// Get campaign info for display
$campaign = Recruiter_Terminal_DB::get_campaign($target->campaign_id);
$recruiter = $campaign ? get_userdata($campaign->user_id) : null;

// Response display config
$response_config = array(
    'interested' => array(
        'icon'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/></svg>',
        'title'   => __("Thank you for your interest!", 'senna-finance'),
        'message' => __("We're excited to hear from you. The recruiter will be in touch shortly to discuss this opportunity further.", 'senna-finance'),
        'color'   => '#1e6b4a',
    ),
    'not_interested' => array(
        'icon'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3zm7-13h2.67A2.31 2.31 0 0 1 22 4v7a2.31 2.31 0 0 1-2.33 2H17"/></svg>',
        'title'   => __("Thank you for letting us know", 'senna-finance'),
        'message' => __("We appreciate your response. We've noted your preference and will respect your decision.", 'senna-finance'),
        'color'   => '#71717a',
    ),
    'maybe' => array(
        'icon'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
        'title'   => __("Thanks for considering", 'senna-finance'),
        'message' => __("We understand timing isn't always right. Feel free to reach out if your circumstances change.", 'senna-finance'),
        'color'   => '#f59e0b',
    ),
    'unsubscribe' => array(
        'icon'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>',
        'title'   => __("You've been unsubscribed", 'senna-finance'),
        'message' => __("You will no longer receive outreach emails from us. If you change your mind, you can always reach out directly.", 'senna-finance'),
        'color'   => '#71717a',
    ),
);

$config = isset($response_config[$response]) ? $response_config[$response] : $response_config['interested'];
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo esc_html($config['title']); ?> - <?php bloginfo('name'); ?></title>
    <?php wp_head(); ?>
    <style>
        :root {
            --response-color: <?php echo esc_attr($config['color']); ?>;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #0a1628 0%, #1a3a5c 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .response-container {
            max-width: 520px;
            width: 100%;
            text-align: center;
        }

        .response-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 48px 40px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            margin-bottom: 24px;
        }

        .response-icon {
            width: 72px;
            height: 72px;
            margin: 0 auto 24px;
            background: <?php echo esc_attr($config['color']); ?>15;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .response-icon svg {
            width: 36px;
            height: 36px;
            color: var(--response-color);
        }

        .response-title {
            font-size: 24px;
            font-weight: 600;
            color: #0f2137;
            margin: 0 0 12px 0;
        }

        .response-message {
            font-size: 15px;
            line-height: 1.6;
            color: #52525b;
            margin: 0 0 32px 0;
        }

        .response-divider {
            height: 1px;
            background: #e4e4e7;
            margin: 32px 0;
        }

        .feedback-section {
            text-align: left;
        }

        .feedback-label {
            font-size: 13px;
            font-weight: 600;
            color: #3f3f46;
            margin-bottom: 12px;
            display: block;
        }

        .feedback-textarea {
            width: 100%;
            padding: 14px 16px;
            font-family: inherit;
            font-size: 14px;
            color: #18181b;
            background: #faf8f5;
            border: 1px solid #e4e4e7;
            border-radius: 8px;
            resize: vertical;
            min-height: 100px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .feedback-textarea:focus {
            outline: none;
            border-color: var(--response-color);
            box-shadow: 0 0 0 3px <?php echo esc_attr($config['color']); ?>20;
        }

        .feedback-textarea::placeholder {
            color: #a1a1aa;
        }

        .feedback-submit {
            margin-top: 16px;
            width: 100%;
            padding: 14px 24px;
            font-family: inherit;
            font-size: 15px;
            font-weight: 500;
            color: #ffffff;
            background: var(--response-color);
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: opacity 0.2s;
        }

        .feedback-submit:hover {
            opacity: 0.9;
        }

        .feedback-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .feedback-success {
            display: none;
            padding: 14px;
            background: #dcfce7;
            color: #0f5132;
            border-radius: 8px;
            font-size: 14px;
            margin-top: 16px;
        }

        .response-footer {
            color: rgba(255, 255, 255, 0.6);
            font-size: 13px;
        }

        .response-footer a {
            color: #c8a882;
            text-decoration: none;
        }

        .response-footer a:hover {
            text-decoration: underline;
        }

        .already-responded {
            padding: 16px;
            background: #f4f4f5;
            border-radius: 8px;
            font-size: 14px;
            color: #52525b;
        }

        @media (max-width: 600px) {
            .response-card {
                padding: 32px 24px;
            }

            .response-title {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="response-container">
        <div class="response-card">
            <div class="response-icon">
                <?php echo $config['icon']; ?>
            </div>

            <h1 class="response-title"><?php echo esc_html($config['title']); ?></h1>
            <p class="response-message"><?php echo esc_html($config['message']); ?></p>

            <?php if ($already_responded && !$is_new_response) : ?>
                <div class="already-responded">
                    <?php esc_html_e("You've already responded to this opportunity. Thank you!", 'senna-finance'); ?>
                </div>
            <?php elseif ($response === 'interested') : ?>
                <div class="response-divider"></div>

                <div class="feedback-section">
                    <label class="feedback-label" for="response-message">
                        <?php esc_html_e('Want to add a message for the recruiter? (Optional)', 'senna-finance'); ?>
                    </label>
                    <textarea
                        id="response-message"
                        class="feedback-textarea"
                        placeholder="<?php esc_attr_e('Share your availability, questions, or any additional information...', 'senna-finance'); ?>"
                    ></textarea>
                    <button type="button" class="feedback-submit" id="submit-message">
                        <?php esc_html_e('Send Message', 'senna-finance'); ?>
                    </button>
                    <div class="feedback-success" id="feedback-success">
                        <?php esc_html_e('Message sent successfully!', 'senna-finance'); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="response-footer">
            <p>
                <?php esc_html_e('Powered by', 'senna-finance'); ?>
                <a href="<?php echo esc_url(home_url()); ?>"><?php bloginfo('name'); ?></a>
            </p>
        </div>
    </div>

    <?php if ($response === 'interested' && $is_new_response) : ?>
    <script>
        document.getElementById('submit-message').addEventListener('click', function() {
            var message = document.getElementById('response-message').value.trim();
            var btn = this;

            if (!message) {
                return;
            }

            btn.disabled = true;
            btn.textContent = '<?php echo esc_js(__('Sending...', 'senna-finance')); ?>';

            var xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo esc_url(admin_url('admin-ajax.php')); ?>', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

            xhr.onload = function() {
                if (xhr.status === 200) {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        document.getElementById('feedback-success').style.display = 'block';
                        document.getElementById('response-message').disabled = true;
                        btn.style.display = 'none';
                    } else {
                        btn.disabled = false;
                        btn.textContent = '<?php echo esc_js(__('Send Message', 'senna-finance')); ?>';
                        alert('<?php echo esc_js(__('Failed to send message. Please try again.', 'senna-finance')); ?>');
                    }
                }
            };

            xhr.onerror = function() {
                btn.disabled = false;
                btn.textContent = '<?php echo esc_js(__('Send Message', 'senna-finance')); ?>';
                alert('<?php echo esc_js(__('Failed to send message. Please try again.', 'senna-finance')); ?>');
            };

            var data = 'action=rt_save_response_message&target_id=<?php echo esc_js($target->id); ?>&message=' + encodeURIComponent(message) + '&nonce=<?php echo esc_js(wp_create_nonce('rt_response_message')); ?>';
            xhr.send(data);
        });
    </script>
    <?php endif; ?>

    <?php wp_footer(); ?>
</body>
</html>
