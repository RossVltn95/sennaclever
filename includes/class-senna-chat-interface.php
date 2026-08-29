<?php

/**
 * MENA Careers AI Chat Interface
 * 
 * Provides the chat interface for MENA Careers AI career advisor
 * Integrates with profile and job data for contextual responses
 * 
 * @package MENA Careers
 * @since 5.3.2
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Senna_Chat_Interface
{

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Register AJAX handlers - PUBLIC ACCESS
        add_action('wp_ajax_nopriv_sffc_senna_chat', [$this, 'ajax_handle_chat']);
        add_action('wp_ajax_sffc_senna_chat', [$this, 'ajax_handle_chat']);

        add_action('wp_ajax_nopriv_sffc_senna_get_context', [$this, 'ajax_get_context']);
        add_action('wp_ajax_sffc_senna_get_context', [$this, 'ajax_get_context']);

        add_action('wp_ajax_nopriv_sffc_senna_quick_prompts', [$this, 'ajax_get_quick_prompts']);
        add_action('wp_ajax_sffc_senna_quick_prompts', [$this, 'ajax_get_quick_prompts']);
        add_action('wp_ajax_nopriv_sffc_editorial_floating_chat_seen', [$this, 'ajax_mark_editorial_floating_chat_seen']);
        add_action('wp_ajax_sffc_editorial_floating_chat_seen', [$this, 'ajax_mark_editorial_floating_chat_seen']);
        add_action('wp_ajax_nopriv_sffc_editorial_floating_chat_live_boot', [$this, 'ajax_editorial_floating_chat_live_boot']);
        add_action('wp_ajax_sffc_editorial_floating_chat_live_boot', [$this, 'ajax_editorial_floating_chat_live_boot']);
        add_action('wp_ajax_nopriv_sffc_editorial_floating_chat_live_send', [$this, 'ajax_editorial_floating_chat_live_send']);
        add_action('wp_ajax_sffc_editorial_floating_chat_live_send', [$this, 'ajax_editorial_floating_chat_live_send']);
        add_action('wp_ajax_nopriv_sffc_editorial_floating_chat_live_fetch', [$this, 'ajax_editorial_floating_chat_live_fetch']);
        add_action('wp_ajax_sffc_editorial_floating_chat_live_fetch', [$this, 'ajax_editorial_floating_chat_live_fetch']);
        add_action('wp_ajax_nopriv_sffc_editorial_floating_chat_job_help', [$this, 'ajax_editorial_floating_chat_job_help']);
        add_action('wp_ajax_sffc_editorial_floating_chat_job_help', [$this, 'ajax_editorial_floating_chat_job_help']);
        add_action('wp_ajax_nopriv_sffc_editorial_floating_chat_cv_review', [$this, 'ajax_editorial_floating_chat_cv_review']);
        add_action('wp_ajax_sffc_editorial_floating_chat_cv_review', [$this, 'ajax_editorial_floating_chat_cv_review']);
        add_action('wp_ajax_nopriv_sffc_editorial_floating_chat_call_request', [$this, 'ajax_editorial_floating_chat_call_request']);
        add_action('wp_ajax_sffc_editorial_floating_chat_call_request', [$this, 'ajax_editorial_floating_chat_call_request']);

        // Article-specific chat handler
        add_action('wp_ajax_nopriv_sffc_article_chat', [$this, 'ajax_handle_article_chat']);
        add_action('wp_ajax_sffc_article_chat', [$this, 'ajax_handle_article_chat']);

        // Add shortcode for chat interface
        add_shortcode('senna_chat', [$this, 'render_chat_interface']);
        add_shortcode('sffc_editorial_floating_chat', [$this, 'render_editorial_floating_chat']);
        add_shortcode('sffc_editorial_standalone_chat', [$this, 'render_editorial_standalone_chat']);
        add_shortcode('sffc_editorial_fullscreen_chat', [$this, 'render_editorial_standalone_chat']);
    }

    /**
     * Render the chat interface HTML
     */
    public function render_chat_interface($atts = [])
    {
        $atts = shortcode_atts([
            'mode' => 'overlay', // overlay or embedded
            'theme' => 'premium' // premium or minimal
        ], $atts);

        ob_start();
?>
        <div id="senna-chat-container"
            class="sffc-senna-chat <?php echo esc_attr($atts['mode']); ?> <?php echo esc_attr($atts['theme']); ?>"
            data-mode="<?php echo esc_attr($atts['mode']); ?>">

            <!-- Chat Trigger Button DISABLED - Using Ultimate Interface -->
            <?php if (false && $atts['mode'] === 'overlay'): ?>
                <button id="senna-chat-trigger" class="sffc-senna-trigger">
                    <span class="sffc-senna-icon">◉</span>
                    <span class="sffc-senna-label">Ask MENA Careers</span>
                    <span class="sffc-senna-badge" style="display: none;">1</span>
                </button>
            <?php endif; ?>

            <!-- Main Chat Interface DISABLED - Using senna-conversational.js instead -->
            <?php if (false): // Completely disabled to prevent duplicate interfaces 
            ?>
                <div id="senna-chat-interface" class="sffc-senna-interface" style="display: none;">
                    <div class="sffc-chat-container">
                        <!-- Chat Header -->
                        <div class="sffc-senna-header">
                            <div class="sffc-senna-avatar">
                                <span class="sffc-avatar-initial">S</span>
                            </div>
                            <div class="sffc-senna-info">
                                <h3 class="sffc-senna-name">MENA Careers</h3>
                                <p class="sffc-senna-status">Your AI Career Strategist</p>
                            </div>
                            <button class="sffc-senna-close" aria-label="Close chat">
                                <span>×</span>
                            </button>
                        </div>

                        <!-- Chat Messages -->
                        <div class="sffc-senna-messages" id="senna-messages">
                            <!-- Welcome message removed - handled by JavaScript -->
                        </div>

                        <!-- Quick Prompts -->
                        <div class="sffc-senna-prompts" id="senna-prompts">
                            <!-- Dynamically loaded based on context -->
                        </div>

                    </div> <!-- Close chat-container -->
                </div>
            <?php endif; ?>
        </div>
<?php
        return ob_get_clean();
    }

    /**
     * Render isolated editorial floating chat widget.
     */
    public function render_editorial_floating_chat($atts = [])
    {
        $atts = shortcode_atts([
            'title' => __('MENA Careers Support', 'senna-finance'),
            'subtitle' => __('Chat with the team', 'senna-finance'),
        ], $atts, 'sffc_editorial_floating_chat');

        $css_version = file_exists(SFFC_PLUGIN_DIR . 'assets/css/editorial-floating-chat.css')
            ? (string) filemtime(SFFC_PLUGIN_DIR . 'assets/css/editorial-floating-chat.css')
            : (defined('SFFC_VERSION') ? SFFC_VERSION : '1.0.0');
        $js_version = file_exists(SFFC_PLUGIN_DIR . 'assets/js/editorial-floating-chat.js')
            ? (string) filemtime(SFFC_PLUGIN_DIR . 'assets/js/editorial-floating-chat.js')
            : (defined('SFFC_VERSION') ? SFFC_VERSION : '1.0.0');
        wp_enqueue_style(
            'sffc-editorial-floating-chat',
            SFFC_PLUGIN_URL . 'assets/css/editorial-floating-chat.css',
            [],
            $css_version
        );

        wp_enqueue_script(
            'sffc-editorial-floating-chat',
            SFFC_PLUGIN_URL . 'assets/js/editorial-floating-chat.js',
            [],
            $js_version,
            true
        );

        $user_id = get_current_user_id();
        $first_name = __('there', 'senna-finance');
        $user_email = '';

        if ($user_id > 0) {
            $current_user = wp_get_current_user();
            if ($current_user instanceof WP_User) {
                $first_name = trim((string) $current_user->first_name);
                if ($first_name === '') {
                    $first_name = trim((string) $current_user->display_name);
                }
                if ($first_name === '') {
                    $first_name = trim((string) $current_user->user_login);
                }
                $user_email = sanitize_email((string) $current_user->user_email);
            }
        }

        $meta_key = 'sffc_editorial_floating_chat_welcome_seen';
        $show_welcome = true;
        if ($user_id > 0) {
            $show_welcome = !((bool) get_user_meta($user_id, $meta_key, true));
        }

        $avatar_url = 'https://joinsenna.com/wp-content/uploads/2024/04/266217121-designer-woman-portrait-and-ha.jpeg';
        $can_upload_files = true;
        $availability = $this->get_editorial_floating_chat_availability();

        $instance_id = wp_unique_id('sffc-editorial-float-chat-');
        $config = [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sffc_public_nonce'),
            'isLoggedIn' => is_user_logged_in(),
            'userId' => $user_id,
            'firstName' => $first_name,
            'showWelcome' => $show_welcome,
            'userEmail' => $user_email,
            'canUploadFiles' => $can_upload_files,
            'title' => (string) $atts['title'],
            'subtitle' => (string) $atts['subtitle'],
            'avatarUrl' => $avatar_url,
            'isOnline' => (bool) $availability['is_online'],
            'offlineMessage' => (string) $availability['message'],
            'timezoneLabel' => 'Dubai time',
            'storageKey' => 'sffc_editorial_floating_chat_seen_' . ($user_id > 0 ? (string) $user_id : 'guest'),
            'labels' => [
                'sendError' => __('Unable to send your message right now. Please try again shortly.', 'senna-finance'),
                'onlineReply' => __('Thanks. Your message is with the MENA Careers team. If someone is online, they can reply here shortly.', 'senna-finance'),
                'offlineReply' => __('Thanks. The team is currently offline. We will pick this up from 10 AM Dubai time.', 'senna-finance'),
            ],
        ];

        wp_add_inline_script(
            'sffc-editorial-floating-chat',
            'window.SFFCEditorialFloatingChat = window.SFFCEditorialFloatingChat || {}; window.SFFCEditorialFloatingChat[' . wp_json_encode($instance_id) . '] = ' . wp_json_encode($config) . ';',
            'before'
        );

        ob_start();
        ?>
        <div
            id="<?php echo esc_attr($instance_id); ?>"
            class="sffc-editorial-floating-chat has-pro-access"
            data-sffc-editorial-floating-chat
            data-is-logged-in="<?php echo is_user_logged_in() ? '1' : '0'; ?>"
            data-has-premium-access="1"
            data-advisor-photo="<?php echo esc_url($avatar_url); ?>"
            data-instance-id="<?php echo esc_attr($instance_id); ?>">
            <button
                type="button"
                class="sffc-editorial-floating-chat__trigger"
                data-sffc-efc-trigger
                aria-expanded="false"
                aria-controls="<?php echo esc_attr($instance_id . '-panel'); ?>">
                <span class="sffc-editorial-floating-chat__trigger-avatar" aria-hidden="true">
                    <img src="<?php echo esc_url($avatar_url); ?>" alt="">
                </span>
                <span class="sffc-editorial-floating-chat__trigger-copy">
                    <strong>
                        <span><?php esc_html_e('Get Hired Quicker', 'senna-finance'); ?></span>
                        <em class="sffc-editorial-floating-chat__pro-badge"><?php esc_html_e('Live Chat', 'senna-finance'); ?></em>
                    </strong>
                    <span class="sffc-editorial-floating-chat__trigger-status">
                        <span><?php esc_html_e('Get career support', 'senna-finance'); ?></span>
                    </span>
                </span>
                <span class="sffc-editorial-floating-chat__trigger-icons" aria-hidden="true">
                    <span class="sffc-editorial-floating-chat__icon-dot"></span>
                    <span class="sffc-editorial-floating-chat__icon-dot"></span>
                    <span class="sffc-editorial-floating-chat__icon-chevron">⌃</span>
                </span>
                <span class="sffc-editorial-floating-chat__trigger-badge<?php echo $show_welcome ? ' is-visible' : ''; ?>" data-sffc-efc-badge><?php echo $show_welcome ? '1' : ''; ?></span>
            </button>

            <section
                id="<?php echo esc_attr($instance_id . '-panel'); ?>"
                class="sffc-editorial-floating-chat__panel sffc-editorial-floating-chat__panel--mini"
                data-sffc-efc-panel
                hidden
                aria-hidden="true">
                <header class="sffc-editorial-floating-chat__header">
                    <div class="sffc-editorial-floating-chat__header-main">
                        <span class="sffc-editorial-floating-chat__header-avatar" aria-hidden="true">
                            <img src="<?php echo esc_url($avatar_url); ?>" alt="">
                        </span>
                        <div class="sffc-editorial-floating-chat__header-copy">
                            <strong><?php esc_html_e('Emily B.', 'senna-finance'); ?> <em class="sffc-editorial-floating-chat__pro-badge"><?php esc_html_e('Live Chat', 'senna-finance'); ?></em></strong>
                            <span data-sffc-efc-status-text><?php echo esc_html((string) $availability['message']); ?></span>
                        </div>
                        <button type="button" class="sffc-editorial-floating-chat__header-end" data-sffc-efc-end-chat hidden><?php esc_html_e('End chat', 'senna-finance'); ?></button>
                    </div>
                    <div class="sffc-editorial-floating-chat__header-actions">
                        <button type="button" class="sffc-editorial-floating-chat__header-action" data-sffc-efc-close aria-label="<?php esc_attr_e('Close', 'senna-finance'); ?>">×</button>
                    </div>
                </header>

                <div class="sffc-editorial-floating-chat__screen sffc-editorial-floating-chat__screen--live">
                    <div class="sffc-editorial-floating-chat__live-menu" data-sffc-efc-live-menu>
                        <div class="sffc-editorial-floating-chat__live-intro">
                            <span class="sffc-editorial-floating-chat__live-eyebrow"><?php esc_html_e('MENA Careers', 'senna-finance'); ?></span>
                            <h2><?php esc_html_e('What do you need help with?', 'senna-finance'); ?></h2>
                            <p><?php esc_html_e('Pick one option and Emily will route the chat to the right person.', 'senna-finance'); ?></p>
                        </div>
                        <div class="sffc-editorial-floating-chat__support-options">
                            <button type="button" class="sffc-editorial-floating-chat__support-option" data-sffc-efc-live-topic="expert">
                                <span class="sffc-editorial-floating-chat__support-option-icon is-avatar" aria-hidden="true">
                                    <img src="<?php echo esc_url($avatar_url); ?>" alt="">
                                </span>
                                <span class="sffc-editorial-floating-chat__support-option-copy">
                                    <strong><?php esc_html_e('Talk to a real expert', 'senna-finance'); ?></strong>
                                    <span><?php esc_html_e('Ask about your career goals, target roles, or next steps.', 'senna-finance'); ?></span>
                                </span>
                                <span class="sffc-editorial-floating-chat__support-option-arrow" aria-hidden="true">›</span>
                            </button>
                            <button type="button" class="sffc-editorial-floating-chat__support-option" data-sffc-efc-live-topic="job_search">
                                <span class="sffc-editorial-floating-chat__support-option-icon" aria-hidden="true">J</span>
                                <span class="sffc-editorial-floating-chat__support-option-copy">
                                    <strong><?php esc_html_e('Help me apply for jobs', 'senna-finance'); ?></strong>
                                    <span><?php esc_html_e('Get help with target roles, applications, and follow-up.', 'senna-finance'); ?></span>
                                </span>
                                <span class="sffc-editorial-floating-chat__support-option-arrow" aria-hidden="true">›</span>
                            </button>
                            <button type="button" class="sffc-editorial-floating-chat__support-option" data-sffc-efc-live-topic="cv_review">
                                <span class="sffc-editorial-floating-chat__support-option-icon" aria-hidden="true">CV</span>
                                <span class="sffc-editorial-floating-chat__support-option-copy">
                                    <strong><?php esc_html_e('Review my CV', 'senna-finance'); ?></strong>
                                    <span><?php esc_html_e('Attach your CV and get help with positioning and fit.', 'senna-finance'); ?></span>
                                </span>
                                <span class="sffc-editorial-floating-chat__support-option-arrow" aria-hidden="true">›</span>
                            </button>
                            <button type="button" class="sffc-editorial-floating-chat__support-option" data-sffc-efc-live-topic="recruiter_outreach">
                                <span class="sffc-editorial-floating-chat__support-option-icon" aria-hidden="true">R</span>
                                <span class="sffc-editorial-floating-chat__support-option-copy">
                                    <strong><?php esc_html_e('Recruiter outreach', 'senna-finance'); ?></strong>
                                    <span><?php esc_html_e('Get help identifying recruiters and shaping outreach.', 'senna-finance'); ?></span>
                                </span>
                                <span class="sffc-editorial-floating-chat__support-option-arrow" aria-hidden="true">›</span>
                            </button>
                        </div>
                    </div>
                    <div class="sffc-editorial-floating-chat__live-chat" data-sffc-efc-chat-screen hidden>
                        <div class="sffc-editorial-floating-chat__live-messages" data-sffc-efc-messages></div>
                        <form class="sffc-editorial-floating-chat__live-composer" data-sffc-efc-live-form>
                            <div class="sffc-editorial-floating-chat__live-fields">
                                <input type="text" name="candidate_name" autocomplete="name" value="<?php echo esc_attr($first_name !== __('there', 'senna-finance') ? $first_name : ''); ?>" placeholder="<?php esc_attr_e('Name', 'senna-finance'); ?>">
                                <input type="email" name="email" autocomplete="email" value="<?php echo esc_attr($user_email); ?>" placeholder="<?php esc_attr_e('Email', 'senna-finance'); ?>" required>
                            </div>
                            <input type="hidden" name="topic" data-sffc-efc-topic-value value="expert">
                            <div class="sffc-editorial-floating-chat__composer-shell">
                                <button type="button" class="sffc-editorial-floating-chat__attach" data-sffc-efc-attachment-button aria-label="<?php esc_attr_e('Attach file', 'senna-finance'); ?>">
                                    <svg aria-hidden="true" viewBox="0 0 24 24" focusable="false">
                                        <path d="M21.4 11.6 12 21a6 6 0 0 1-8.5-8.5l9.8-9.8a4.2 4.2 0 0 1 5.9 5.9l-9.8 9.8a2.4 2.4 0 0 1-3.4-3.4l8.9-8.9" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                                    </svg>
                                </button>
                                <input class="sffc-editorial-floating-chat__attachment-input" type="file" name="attachments[]" data-sffc-efc-attachment-input multiple accept=".pdf,.doc,.docx,.txt,.png,.jpg,.jpeg,.xls,.xlsx">
                                <textarea class="sffc-editorial-floating-chat__input" name="message" data-sffc-efc-input rows="1" placeholder="<?php esc_attr_e('Write a message...', 'senna-finance'); ?>"></textarea>
                                <button type="submit" class="sffc-editorial-floating-chat__send" data-sffc-efc-send aria-label="<?php esc_attr_e('Send message', 'senna-finance'); ?>">↗</button>
                            </div>
                            <p class="sffc-editorial-floating-chat__attachment-preview" data-sffc-efc-attachment-preview hidden></p>
                            <p class="sffc-editorial-floating-chat__live-notice" data-sffc-efc-live-notice hidden></p>
                        </form>
                    </div>
                </div>
            </section>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * Render a fullscreen standalone live chat intake.
     *
     * This intentionally does not reuse the floating chat DOM classes. It shares
     * only the live chat AJAX endpoints so the standalone page cannot interfere
     * with the site-wide floating widget.
     */
    public function render_editorial_standalone_chat($atts = [])
    {
        $atts = shortcode_atts([
            'title' => __('Speak to Emily about your job search', 'senna-finance'),
            'subtitle' => __('Upload your CV, tell us what you are targeting, and we will hand this to a real Senna career expert.', 'senna-finance'),
        ], $atts, 'sffc_editorial_standalone_chat');

        $css_version = file_exists(SFFC_PLUGIN_DIR . 'assets/css/editorial-standalone-chat.css')
            ? (string) filemtime(SFFC_PLUGIN_DIR . 'assets/css/editorial-standalone-chat.css')
            : (defined('SFFC_VERSION') ? SFFC_VERSION : '1.0.0');
        $js_version = file_exists(SFFC_PLUGIN_DIR . 'assets/js/editorial-standalone-chat.js')
            ? (string) filemtime(SFFC_PLUGIN_DIR . 'assets/js/editorial-standalone-chat.js')
            : (defined('SFFC_VERSION') ? SFFC_VERSION : '1.0.0');

        wp_enqueue_style(
            'sffc-editorial-standalone-chat',
            SFFC_PLUGIN_URL . 'assets/css/editorial-standalone-chat.css',
            [],
            $css_version
        );

        wp_enqueue_script(
            'sffc-editorial-standalone-chat',
            SFFC_PLUGIN_URL . 'assets/js/editorial-standalone-chat.js',
            [],
            $js_version,
            true
        );

        $user_id = get_current_user_id();
        $first_name = '';
        $user_email = '';
        if ($user_id > 0) {
            $current_user = wp_get_current_user();
            if ($current_user instanceof WP_User) {
                $first_name = trim((string) $current_user->first_name);
                if ($first_name === '') {
                    $first_name = trim((string) $current_user->display_name);
                }
                $user_email = sanitize_email((string) $current_user->user_email);
            }
        }

        $avatar_url = 'https://media.joinsenna.com/2026/01/emilybradshaw-1.jpg';
        $availability = $this->get_editorial_floating_chat_availability();
        $instance_id = wp_unique_id('sffc-editorial-standalone-chat-');
        $config = [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sffc_public_nonce'),
            'isLoggedIn' => is_user_logged_in(),
            'userId' => $user_id,
            'firstName' => $first_name,
            'userEmail' => $user_email,
            'avatarUrl' => $avatar_url,
            'isOnline' => (bool) $availability['is_online'],
            'offlineMessage' => (string) $availability['message'],
            'sessionKey' => 'sffc_editorial_standalone_live_chat_session',
            'labels' => [
                'sendError' => __('Unable to send your message right now. Please try again shortly.', 'senna-finance'),
                'handover' => __('Thanks. I have sent your CV and job-search goal to a real Senna career expert. They will review your background and reply here with next steps.', 'senna-finance'),
            ],
        ];

        wp_add_inline_script(
            'sffc-editorial-standalone-chat',
            'window.SFFCEditorialStandaloneChat = window.SFFCEditorialStandaloneChat || {}; window.SFFCEditorialStandaloneChat[' . wp_json_encode($instance_id) . '] = ' . wp_json_encode($config) . ';',
            'before'
        );

        ob_start();
        ?>
        <section
            id="<?php echo esc_attr($instance_id); ?>"
            class="sffc-editorial-standalone-chat"
            data-sffc-editorial-standalone-chat
            data-instance-id="<?php echo esc_attr($instance_id); ?>">
            <div class="sffc-editorial-standalone-chat__shell">
                <header class="sffc-editorial-standalone-chat__topbar" aria-label="<?php esc_attr_e('Live job search chat', 'senna-finance'); ?>">
                    <a class="sffc-editorial-standalone-chat__brand" href="<?php echo esc_url(home_url('/')); ?>">
                        <span class="sffc-editorial-standalone-chat__mark">S</span>
                        <span><?php esc_html_e('Senna', 'senna-finance'); ?></span>
                    </a>
                    <div class="sffc-editorial-standalone-chat__toplinks" aria-hidden="true">
                        <span><?php esc_html_e('Recruiter outreach', 'senna-finance'); ?></span>
                        <span><?php esc_html_e('Tailored applications', 'senna-finance'); ?></span>
                        <span><?php esc_html_e('Hiring manager visibility', 'senna-finance'); ?></span>
                    </div>
                </header>

                <div class="sffc-editorial-standalone-chat__hero">
                    <p class="sffc-editorial-standalone-chat__eyebrow"><?php esc_html_e('Live job search support', 'senna-finance'); ?></p>
                    <h1><?php echo esc_html((string) $atts['title']); ?></h1>
                    <p><?php echo esc_html((string) $atts['subtitle']); ?></p>
                </div>

                <div class="sffc-editorial-standalone-chat__layout">
                    <aside class="sffc-editorial-standalone-chat__side">
                        <div class="sffc-editorial-standalone-chat__advisor">
                            <img src="<?php echo esc_url($avatar_url); ?>" alt="">
                            <div>
                                <strong><?php esc_html_e('Emily B.', 'senna-finance'); ?></strong>
                                <span><?php esc_html_e('Job search assistant', 'senna-finance'); ?></span>
                            </div>
                        </div>
                        <div class="sffc-editorial-standalone-chat__stat">
                            <strong><?php esc_html_e('1,500+', 'senna-finance'); ?></strong>
                            <span><?php esc_html_e('professionals use Senna for MENA finance roles', 'senna-finance'); ?></span>
                        </div>
                        <ul class="sffc-editorial-standalone-chat__checks">
                            <li><?php esc_html_e('Upload your CV', 'senna-finance'); ?></li>
                            <li><?php esc_html_e('Confirm target roles and locations', 'senna-finance'); ?></li>
                            <li><?php esc_html_e('Get handed to a real career expert', 'senna-finance'); ?></li>
                        </ul>
                    </aside>

                    <div class="sffc-editorial-standalone-chat__chat" data-sffc-esc-chat>
                        <header class="sffc-editorial-standalone-chat__chat-header">
                            <div class="sffc-editorial-standalone-chat__advisor is-small">
                                <img src="<?php echo esc_url($avatar_url); ?>" alt="">
                                <div>
                                    <strong><?php esc_html_e('Emily', 'senna-finance'); ?></strong>
                                    <span data-sffc-esc-status><?php echo esc_html((string) $availability['message']); ?></span>
                                </div>
                            </div>
                            <span class="sffc-editorial-standalone-chat__live-pill"><?php esc_html_e('Live handover', 'senna-finance'); ?></span>
                        </header>

                        <div class="sffc-editorial-standalone-chat__messages" data-sffc-esc-messages aria-live="polite"></div>

                        <div class="sffc-editorial-standalone-chat__quick" data-sffc-esc-quick-actions>
                            <button type="button" data-sffc-esc-language="English"><?php esc_html_e('English', 'senna-finance'); ?></button>
                            <button type="button" data-sffc-esc-language="Arabic">العربية</button>
                        </div>

                        <form class="sffc-editorial-standalone-chat__composer" data-sffc-esc-form>
                            <div class="sffc-editorial-standalone-chat__identity">
                                <input type="text" name="candidate_name" autocomplete="name" value="<?php echo esc_attr($first_name); ?>" placeholder="<?php esc_attr_e('Name', 'senna-finance'); ?>">
                                <input type="email" name="email" autocomplete="email" value="<?php echo esc_attr($user_email); ?>" placeholder="<?php esc_attr_e('Email', 'senna-finance'); ?>" required>
                            </div>
                            <input type="hidden" name="topic" value="job_search" data-sffc-esc-topic>
                            <div class="sffc-editorial-standalone-chat__input-row">
                                <button type="button" class="sffc-editorial-standalone-chat__attach" data-sffc-esc-attachment-button aria-label="<?php esc_attr_e('Attach CV', 'senna-finance'); ?>">
                                    <svg aria-hidden="true" viewBox="0 0 24 24" focusable="false">
                                        <path d="M21.4 11.6 12 21a6 6 0 0 1-8.5-8.5l9.8-9.8a4.2 4.2 0 0 1 5.9 5.9l-9.8 9.8a2.4 2.4 0 0 1-3.4-3.4l8.9-8.9" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                                    </svg>
                                </button>
                                <input type="file" name="attachments[]" data-sffc-esc-attachment-input accept=".pdf,.doc,.docx,.txt,.png,.jpg,.jpeg" hidden>
                                <textarea name="message" rows="1" data-sffc-esc-input placeholder="<?php esc_attr_e('Tell Emily what roles you want, or upload your CV...', 'senna-finance'); ?>"></textarea>
                                <button type="submit" data-sffc-esc-send><?php esc_html_e('Send', 'senna-finance'); ?></button>
                            </div>
                            <div class="sffc-editorial-standalone-chat__attachment-preview" data-sffc-esc-attachment-preview hidden></div>
                            <p class="sffc-editorial-standalone-chat__notice" data-sffc-esc-notice hidden></p>
                        </form>
                    </div>
                </div>
            </div>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * Mark the editorial floating chat welcome as seen for logged-in users.
     */
    public function ajax_mark_editorial_floating_chat_seen()
    {
        check_ajax_referer('sffc_public_nonce', 'nonce');

        if (is_user_logged_in()) {
            update_user_meta(get_current_user_id(), 'sffc_editorial_floating_chat_welcome_seen', current_time('mysql'));
        }

        wp_send_json_success(['seen' => true]);
    }

    private function get_editorial_floating_chat_availability()
    {
        try {
            $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Dubai'));
            $hour = (int) $now->format('G');
            $is_online = $hour >= 10 && $hour < 22;
        } catch (Exception $e) {
            $is_online = false;
        }

        return [
            'is_online' => $is_online,
            'message' => $is_online
                ? __('Online now. A team member can reply if available.', 'senna-finance')
                : __('Currently offline until 10 AM Dubai time.', 'senna-finance'),
        ];
    }

    private function ensure_editorial_floating_chat_crm_models()
    {
        if (!class_exists('SFFC_CRM_Conversation')) {
            $conversation_path = SFFC_PLUGIN_DIR . 'includes/crm/models/class-crm-conversation.php';
            if (file_exists($conversation_path)) {
                require_once $conversation_path;
            }
        }
        if (!class_exists('SFFC_CRM_Message')) {
            $message_path = SFFC_PLUGIN_DIR . 'includes/crm/models/class-crm-message.php';
            if (file_exists($message_path)) {
                require_once $message_path;
            }
        }

        return class_exists('SFFC_CRM_Conversation') && class_exists('SFFC_CRM_Message');
    }

    private function normalize_editorial_floating_chat_session_token($token)
    {
        $token = strtolower(trim((string) $token));
        $token = preg_replace('/[^a-z0-9_-]+/', '', $token);
        if ($token === '') {
            $token = wp_generate_password(32, false, false);
        }

        return substr($token, 0, 80);
    }

    private function get_editorial_floating_chat_thread_id($session_token)
    {
        return 'apply_chat:editorial_live:' . $this->normalize_editorial_floating_chat_session_token($session_token);
    }

    private function get_editorial_floating_chat_conversation_by_thread_id($thread_id)
    {
        global $wpdb;

        if ($thread_id === '') {
            return null;
        }

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}sffc_crm_conversations WHERE thread_id = %s LIMIT 1",
                $thread_id
            ),
            ARRAY_A
        );
    }

    private function set_editorial_floating_chat_conversation_labels($conversation_id)
    {
        global $wpdb;

        $conversation_id = (int) $conversation_id;
        if ($conversation_id <= 0) {
            return false;
        }

        return false !== $wpdb->update(
            $wpdb->prefix . 'sffc_crm_conversations',
            ['labels' => 'apply_chat,editorial_live_chat,priority_now'],
            ['id' => $conversation_id],
            ['%s'],
            ['%d']
        );
    }

    private function touch_editorial_floating_chat_presence($conversation_id, $page_url = '')
    {
        $conversation_id = (int) $conversation_id;
        if ($conversation_id <= 0) {
            return;
        }

        set_transient('sffc_apply_chat_live_' . $conversation_id, [
            'last_seen_at' => current_time('mysql'),
            'page_visible' => true,
            'page_url' => esc_url_raw((string) $page_url),
            'active_path' => 'editorial_live_chat',
            'prompt_state' => 'live_chat',
        ], 10 * MINUTE_IN_SECONDS);
    }

    private function find_or_create_editorial_floating_chat_conversation($session_token, $topic = 'expert', $page_url = '')
    {
        if (!$this->ensure_editorial_floating_chat_crm_models()) {
            return 0;
        }

        $session_token = $this->normalize_editorial_floating_chat_session_token($session_token);
        $thread_id = $this->get_editorial_floating_chat_thread_id($session_token);
        $existing = $this->get_editorial_floating_chat_conversation_by_thread_id($thread_id);
        if (!empty($existing['id'])) {
            $this->touch_editorial_floating_chat_presence((int) $existing['id'], $page_url);
            $this->set_editorial_floating_chat_conversation_labels((int) $existing['id']);
            return (int) $existing['id'];
        }

        $topic_label = $this->get_editorial_floating_chat_topic_label($topic);
        $conversation_model = new SFFC_CRM_Conversation();
        $conversation_id = $conversation_model->create(is_user_logged_in() ? get_current_user_id() : 0, [
            'post_id' => 0,
            'subject' => sprintf(__('Live chat · %s', 'senna-finance'), $topic_label),
            'channel' => 'manual',
            'thread_id' => $thread_id,
            'is_read' => 1,
            'last_message_preview' => '',
            'direction' => 'outbound',
        ]);

        if (is_wp_error($conversation_id)) {
            return 0;
        }

        $this->set_editorial_floating_chat_conversation_labels((int) $conversation_id);
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'sffc_crm_conversations',
            [
                'message_count' => 0,
                'unread_count' => 0,
                'last_message_preview' => '',
            ],
            ['id' => (int) $conversation_id],
            ['%d', '%d', '%s'],
            ['%d']
        );
        $this->touch_editorial_floating_chat_presence((int) $conversation_id, $page_url);

        return (int) $conversation_id;
    }

    private function get_editorial_floating_chat_topic_label($topic)
    {
        switch (sanitize_key((string) $topic)) {
            case 'team':
                return __('Contact the team', 'senna-finance');
            case 'job_search':
                return __('Job search assistance', 'senna-finance');
            case 'cv_review':
                return __('Career Assessment', 'senna-finance');
            case 'recruiter_outreach':
                return __('Recruiter outreach', 'senna-finance');
            case 'expert':
            default:
                return __('Get Hired Quicker', 'senna-finance');
        }
    }

    private function create_editorial_floating_chat_message($conversation_id, $speaker, $content, array $headers = [], array $attachments = [])
    {
        if (!$this->ensure_editorial_floating_chat_crm_models()) {
            return new WP_Error('missing_models', __('CRM messaging is not available.', 'senna-finance'));
        }

        $speaker = sanitize_key((string) $speaker);
        $message_model = new SFFC_CRM_Message();
        $from_name = $speaker === 'user'
            ? sanitize_text_field((string) ($headers['candidate_name'] ?? __('Visitor', 'senna-finance')))
            : __('MENA Careers team', 'senna-finance');
        return $message_model->create((int) $conversation_id, [
            'direction' => $speaker === 'user' ? 'inbound' : 'outbound',
            'channel' => 'manual',
            'subject' => __('Live chat', 'senna-finance'),
            'body_text' => sanitize_textarea_field((string) $content),
            'from_name' => $from_name,
            'attachments' => $attachments,
            'headers' => array_merge([
                'speaker' => $speaker,
                'source' => 'editorial_floating_live_chat',
            ], $headers),
        ]);
    }

    private function normalize_editorial_floating_chat_uploaded_files($files)
    {
        if (empty($files) || !is_array($files) || empty($files['name'])) {
            return [];
        }

        if (!is_array($files['name'])) {
            return [$files];
        }

        $normalized = [];
        foreach ($files['name'] as $index => $name) {
            if ($name === '') {
                continue;
            }
            $normalized[] = [
                'name' => $name,
                'type' => $files['type'][$index] ?? '',
                'tmp_name' => $files['tmp_name'][$index] ?? '',
                'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$index] ?? 0,
            ];
        }

        return $normalized;
    }

    private function upload_editorial_floating_chat_attachments($files)
    {
        $uploads = $this->normalize_editorial_floating_chat_uploaded_files($files);
        if (empty($uploads)) {
            return [];
        }

        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $attachments = [];
        $mimes = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'txt' => 'text/plain',
            'png' => 'image/png',
            'jpg|jpeg' => 'image/jpeg',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];

        foreach ($uploads as $file) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                return new WP_Error('upload_failed', __('One of the attachments could not be uploaded.', 'senna-finance'));
            }

            $uploaded = wp_handle_upload($file, [
                'test_form' => false,
                'mimes' => $mimes,
            ]);

            if (!empty($uploaded['error'])) {
                return new WP_Error('upload_failed', sanitize_text_field((string) $uploaded['error']));
            }

            $attachments[] = [
                'name' => sanitize_file_name((string) ($file['name'] ?? basename((string) ($uploaded['file'] ?? 'attachment')))),
                'url' => esc_url_raw((string) ($uploaded['url'] ?? '')),
                'type' => sanitize_text_field((string) ($uploaded['type'] ?? '')),
            ];
        }

        return $attachments;
    }

    private function maybe_send_editorial_floating_chat_online_email($conversation_id, $session_token, $topic = 'expert', $page_url = '', $email = '', $name = '')
    {
        $availability = $this->get_editorial_floating_chat_availability();
        if (empty($availability['is_online'])) {
            return;
        }

        $session_token = $this->normalize_editorial_floating_chat_session_token($session_token);
        $notice_key = 'sffc_efc_online_notice_' . md5($session_token);
        if (get_transient($notice_key)) {
            return;
        }

        $admin_email = sanitize_email((string) apply_filters('sffc_editorial_floating_chat_live_admin_email', get_option('admin_email')));
        if ($admin_email === '' || !is_email($admin_email)) {
            return;
        }

        $topic_label = $this->get_editorial_floating_chat_topic_label($topic);
        $subject = sprintf(__('Live chat visitor online · %s', 'senna-finance'), $topic_label);
        $body = '<div style="font-family:Arial,sans-serif;background:#f3f6f8;padding:24px;color:#1f2937;">'
            . '<div style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #d9e2ec;border-radius:10px;padding:22px;">'
            . '<p style="margin:0 0 8px;color:#0a66c2;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">MENA Careers Live Chat</p>'
            . '<h1 style="margin:0 0 12px;font-size:24px;line-height:1.2;">A visitor is online now</h1>'
            . '<p style="margin:0 0 16px;font-size:15px;line-height:1.6;">A user opened the floating live chat and can receive replies in the widget.</p>'
            . '<table style="width:100%;border-collapse:collapse;font-size:14px;">'
            . '<tr><td style="padding:8px;border-top:1px solid #e5e7eb;font-weight:700;">Topic</td><td style="padding:8px;border-top:1px solid #e5e7eb;">' . esc_html($topic_label) . '</td></tr>'
            . '<tr><td style="padding:8px;border-top:1px solid #e5e7eb;font-weight:700;">Name</td><td style="padding:8px;border-top:1px solid #e5e7eb;">' . esc_html($name !== '' ? $name : __('Not provided', 'senna-finance')) . '</td></tr>'
            . '<tr><td style="padding:8px;border-top:1px solid #e5e7eb;font-weight:700;">Email</td><td style="padding:8px;border-top:1px solid #e5e7eb;">' . esc_html($email !== '' ? $email : __('Not provided', 'senna-finance')) . '</td></tr>'
            . '<tr><td style="padding:8px;border-top:1px solid #e5e7eb;font-weight:700;">Conversation ID</td><td style="padding:8px;border-top:1px solid #e5e7eb;">' . esc_html((string) $conversation_id) . '</td></tr>'
            . '<tr><td style="padding:8px;border-top:1px solid #e5e7eb;font-weight:700;">Page</td><td style="padding:8px;border-top:1px solid #e5e7eb;">' . ($page_url !== '' ? '<a href="' . esc_url($page_url) . '">' . esc_html($page_url) . '</a>' : esc_html__('Unknown', 'senna-finance')) . '</td></tr>'
            . '</table>'
            . '<p style="margin:18px 0 0;font-size:14px;color:#4b5563;">Open [sffc_crm_apply_chat_monitor] to reply.</p>'
            . '</div></div>';

        wp_mail($admin_email, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
        set_transient($notice_key, 1, HOUR_IN_SECONDS);
    }

    public function ajax_editorial_floating_chat_live_boot()
    {
        check_ajax_referer('sffc_public_nonce', 'nonce');

        $session_token = isset($_POST['session_token']) ? sanitize_text_field(wp_unslash((string) $_POST['session_token'])) : '';
        $topic = isset($_POST['topic']) ? sanitize_key(wp_unslash((string) $_POST['topic'])) : 'expert';
        $page_url = isset($_POST['page_url']) ? esc_url_raw(wp_unslash((string) $_POST['page_url'])) : '';
        $conversation_id = $this->find_or_create_editorial_floating_chat_conversation($session_token, $topic, $page_url);

        if ($conversation_id <= 0) {
            wp_send_json_error(['message' => __('Could not start the live chat.', 'senna-finance')], 500);
        }

        $this->maybe_send_editorial_floating_chat_online_email($conversation_id, $session_token, $topic, $page_url);

        wp_send_json_success([
            'conversation_id' => $conversation_id,
            'availability' => $this->get_editorial_floating_chat_availability(),
            'topic_label' => $this->get_editorial_floating_chat_topic_label($topic),
        ]);
    }

    public function ajax_editorial_floating_chat_live_send()
    {
        check_ajax_referer('sffc_public_nonce', 'nonce');

        $session_token = isset($_POST['session_token']) ? sanitize_text_field(wp_unslash((string) $_POST['session_token'])) : '';
        $conversation_id = absint($_POST['conversation_id'] ?? 0);
        $topic = isset($_POST['topic']) ? sanitize_key(wp_unslash((string) $_POST['topic'])) : 'expert';
        $page_url = isset($_POST['page_url']) ? esc_url_raw(wp_unslash((string) $_POST['page_url'])) : '';
        $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash((string) $_POST['message'])) : '';
        $name = isset($_POST['candidate_name']) ? sanitize_text_field(wp_unslash((string) $_POST['candidate_name'])) : '';
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash((string) $_POST['email'])) : '';
        $attachments = $this->upload_editorial_floating_chat_attachments($_FILES['attachments'] ?? []);

        if (is_wp_error($attachments)) {
            wp_send_json_error(['message' => $attachments->get_error_message()], 422);
        }

        if ($message === '' && empty($attachments)) {
            wp_send_json_error(['message' => __('Please write a message or attach a file before sending.', 'senna-finance')], 422);
        }
        if ($email === '' || !is_email($email)) {
            wp_send_json_error(['message' => __('Please add a valid email so the team can follow up.', 'senna-finance')], 422);
        }

        if ($conversation_id <= 0) {
            $conversation_id = $this->find_or_create_editorial_floating_chat_conversation($session_token, $topic, $page_url);
        } else {
            $this->touch_editorial_floating_chat_presence($conversation_id, $page_url);
            $this->set_editorial_floating_chat_conversation_labels($conversation_id);
        }
        if ($conversation_id <= 0) {
            wp_send_json_error(['message' => __('Could not attach that message to the live chat.', 'senna-finance')], 500);
        }

        $stored_message = $message;
        if (!empty($attachments)) {
            $attachment_lines = array_map(static function ($attachment) {
                $name = isset($attachment['name']) ? (string) $attachment['name'] : __('Attachment', 'senna-finance');
                $url = isset($attachment['url']) ? (string) $attachment['url'] : '';
                return trim($name . ($url !== '' ? ': ' . $url : ''));
            }, $attachments);
            $stored_message = trim($stored_message . "\n\n" . __('Attachments:', 'senna-finance') . "\n- " . implode("\n- ", $attachment_lines));
        }

        $message_id = $this->create_editorial_floating_chat_message($conversation_id, 'user', $stored_message, [
            'topic' => $topic,
            'topic_label' => $this->get_editorial_floating_chat_topic_label($topic),
            'candidate_name' => $name,
            'candidate_email' => $email,
            'page_url' => $page_url,
        ], $attachments);

        if (is_wp_error($message_id)) {
            wp_send_json_error(['message' => $message_id->get_error_message()], 500);
        }

        $this->maybe_send_editorial_floating_chat_online_email($conversation_id, $session_token, $topic, $page_url, $email, $name);
        $availability = $this->get_editorial_floating_chat_availability();

        wp_send_json_success([
            'conversation_id' => $conversation_id,
            'message_id' => (int) $message_id,
            'reply' => !empty($availability['is_online'])
                ? __('Got it. I have sent this to the MENA Careers team. Keep this chat open if you want to add your CV or more context.', 'senna-finance')
                : __('Got it. Your message is saved and the MENA Careers team will follow up when they are back online.', 'senna-finance'),
            'availability' => $availability,
        ]);
    }

    public function ajax_editorial_floating_chat_live_fetch()
    {
        check_ajax_referer('sffc_public_nonce', 'nonce');

        $conversation_id = absint($_POST['conversation_id'] ?? 0);
        $last_message_id = absint($_POST['last_message_id'] ?? 0);
        $page_url = isset($_POST['page_url']) ? esc_url_raw(wp_unslash((string) $_POST['page_url'])) : '';
        if ($conversation_id <= 0 || !$this->ensure_editorial_floating_chat_crm_models()) {
            wp_send_json_success(['messages' => [], 'last_message_id' => $last_message_id]);
        }

        $this->touch_editorial_floating_chat_presence($conversation_id, $page_url);
        $message_model = new SFFC_CRM_Message();
        $messages = (array) $message_model->get_conversation_messages_after($conversation_id, $last_message_id, [
            'limit' => 80,
            'order' => 'ASC',
        ]);

        $reply_messages = [];
        $max_message_id = $last_message_id;
        foreach ($messages as $message) {
            $message_id = (int) ($message['id'] ?? 0);
            $max_message_id = max($max_message_id, $message_id);
            $headers = (array) ($message['headers'] ?? []);
            $speaker = sanitize_key((string) ($headers['speaker'] ?? ''));
            if ($speaker !== 'admin' || !empty($headers['internal_note'])) {
                continue;
            }
            $reply_messages[] = [
                'id' => $message_id,
                'content' => (string) ($message['content'] ?? ''),
                'content_html' => (string) ($message['content_html'] ?? ''),
                'from_name' => (string) ($message['from_name'] ?? __('MENA Careers team', 'senna-finance')),
            ];
        }

        wp_send_json_success([
            'messages' => $reply_messages,
            'last_message_id' => $max_message_id,
            'availability' => $this->get_editorial_floating_chat_availability(),
        ]);
    }

    private function get_editorial_floating_chat_identity()
    {
        $current_user = wp_get_current_user();
        $user_label = __('Guest visitor', 'senna-finance');
        $user_email = '';

        if ($current_user instanceof WP_User && $current_user->exists()) {
            $user_label = trim((string) $current_user->display_name);
            if ($user_label === '') {
                $user_label = trim((string) $current_user->user_login);
            }
            $user_email = sanitize_email((string) $current_user->user_email);
        }

        return [
            'label' => $user_label,
            'email' => $user_email,
        ];
    }

    private function current_user_can_upload_editorial_floating_chat_files()
    {
        return true;
    }

    private function send_editorial_floating_chat_mail($to, $subject, $html, $reply_to = '', $attachments = [])
    {
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        if ($reply_to !== '') {
            $headers[] = 'Reply-To: ' . $reply_to;
        }

        return wp_mail($to, $subject, $html, $headers, $attachments);
    }

    private function build_editorial_floating_chat_email($args = [])
    {
        $defaults = [
            'eyebrow' => __('MENA Careers Support', 'senna-finance'),
            'title' => '',
            'intro' => '',
            'recipient_label' => '',
            'details' => [],
            'footnote' => '',
        ];
        $args = wp_parse_args($args, $defaults);

        $detail_rows = '';
        foreach ((array) $args['details'] as $label => $value) {
            $detail_rows .= '<tr>'
                . '<td style="padding:10px 14px;border-bottom:1px solid #e5e7eb;width:34%;font:700 12px/1.4 Arial,sans-serif;color:#8a5a44;text-transform:uppercase;letter-spacing:.08em;">' . esc_html((string) $label) . '</td>'
                . '<td style="padding:10px 14px;border-bottom:1px solid #e5e7eb;font:400 14px/1.6 Arial,sans-serif;color:#111111;">' . nl2br(esc_html((string) $value)) . '</td>'
                . '</tr>';
        }

        $recipient_line = $args['recipient_label'] !== ''
            ? '<p style="margin:0 0 18px;font:400 14px/1.6 Arial,sans-serif;color:#475467;">' . esc_html((string) $args['recipient_label']) . '</p>'
            : '';

        $footnote = $args['footnote'] !== ''
            ? '<p style="margin:20px 0 0;font:400 13px/1.65 Arial,sans-serif;color:#667085;">' . nl2br(esc_html((string) $args['footnote'])) . '</p>'
            : '';

        return '<!doctype html><html><body style="margin:0;padding:24px;background:#f4f4f5;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:680px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:18px;overflow:hidden;">'
            . '<tr><td style="padding:28px 32px;background:#212529;text-align:center;">'
            . '<div style="font:700 11px/1 Arial,sans-serif;letter-spacing:.14em;text-transform:uppercase;color:#f6d7bd;margin-bottom:12px;">' . esc_html((string) $args['eyebrow']) . '</div>'
            . '<div style="font:400 34px/1.12 Georgia,serif;color:#fffdf9;">' . esc_html((string) $args['title']) . '</div>'
            . '</td></tr>'
            . '<tr><td style="padding:30px 32px 18px;">'
            . $recipient_line
            . '<p style="margin:0;font:400 15px/1.75 Arial,sans-serif;color:#1f2937;">' . nl2br(esc_html((string) $args['intro'])) . '</p>'
            . '</td></tr>'
            . ($detail_rows !== '' ? '<tr><td style="padding:0 32px 8px;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;background:#ffffff;">' . $detail_rows . '</table></td></tr>' : '')
            . '<tr><td style="padding:0 32px 32px;">' . $footnote . '<p style="margin:24px 0 0;font:700 13px/1.5 Arial,sans-serif;color:#111111;">MENA Careers</p></td></tr>'
            . '</table></body></html>';
    }

    /**
     * Send recruiter outreach discovery answers to the admin email.
     */
    public function ajax_editorial_floating_chat_recruiter_outreach()
    {
        check_ajax_referer('sffc_public_nonce', 'nonce');

        $raw_answers = wp_unslash($_POST['answers'] ?? '{}');
        $answers = json_decode((string) $raw_answers, true);
        if (!is_array($answers)) {
            $answers = [];
        }

        $fields = [
            'candidate_name' => __('Name', 'senna-finance'),
            'target_role' => __('Target role', 'senna-finance'),
            'seniority' => __('Seniority', 'senna-finance'),
            'target_locations' => __('Target locations', 'senna-finance'),
            'target_sectors' => __('Target sectors', 'senna-finance'),
            'recruiter_type' => __('Recruiter target type', 'senna-finance'),
            'target_firms' => __('Target firms / recruiters', 'senna-finance'),
            'exclusions' => __('Avoid / conflicts', 'senna-finance'),
            'current_status' => __('Current status', 'senna-finance'),
            'timeline' => __('Timeline', 'senna-finance'),
            'message_goal' => __('Desired outcome', 'senna-finance'),
            'notes' => __('Additional notes', 'senna-finance'),
        ];

        $clean = [];
        foreach ($fields as $key => $label) {
            $clean[$key] = sanitize_textarea_field((string) ($answers[$key] ?? ''));
        }

        $identity = $this->get_editorial_floating_chat_identity();
        $user_label = $identity['label'];
        $user_email = $identity['email'];
        if (!is_user_logged_in() && $clean['candidate_name'] !== '') {
            $user_label = $clean['candidate_name'];
        }
        if ($user_email === '') {
            $user_email = sanitize_email((string) ($answers['email'] ?? ''));
        }

        if ($clean['target_role'] === '' || $clean['target_locations'] === '') {
            wp_send_json_error([
                'message' => __('Please share the target role and location before sending the recruiter outreach brief.', 'senna-finance'),
            ], 400);
        }

        if ($user_email === '' || !is_email($user_email)) {
            wp_send_json_error([
                'message' => __('Please add a valid email so we can follow up on the recruiter outreach brief.', 'senna-finance'),
            ], 400);
        }

        $admin_email = sanitize_email((string) get_option('admin_email'));
        if ($admin_email === '') {
            wp_send_json_error([
                'message' => __('Admin email is not configured.', 'senna-finance'),
            ], 500);
        }

        $transcript = '';
        $raw_transcript = json_decode((string) wp_unslash($_POST['transcript'] ?? '[]'), true);
        if (is_array($raw_transcript)) {
            foreach ($raw_transcript as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $sender = sanitize_text_field((string) ($entry['sender'] ?? $entry['kind'] ?? __('Message', 'senna-finance')));
                $content = sanitize_textarea_field((string) ($entry['content'] ?? ''));
                if ($content !== '') {
                    $transcript .= $sender . ': ' . $content . "\n\n";
                }
            }
        }

        $details = [];
        foreach ($fields as $key => $label) {
            $details[$label] = $clean[$key] !== '' ? $clean[$key] : __('Not provided', 'senna-finance');
        }
        $details[__('Submitted by', 'senna-finance')] = $user_label;
        $details[__('Email', 'senna-finance')] = $user_email;
        $details[__('User ID', 'senna-finance')] = is_user_logged_in() ? (string) get_current_user_id() : __('Guest', 'senna-finance');
        $details[__('Access', 'senna-finance')] = __('Free live chat access', 'senna-finance');
        $details[__('Submitted at', 'senna-finance')] = current_time('mysql');
        if ($transcript !== '') {
            $details[__('Chat transcript', 'senna-finance')] = $transcript;
        }

        $subject = sprintf(
            __('New Recruiter Outreach Discovery: %s', 'senna-finance'),
            $clean['target_role']
        );

        $admin_html = $this->build_editorial_floating_chat_email([
            'eyebrow' => __('Recruiter Outreach Discovery', 'senna-finance'),
            'title' => __('New Recruiter Outreach Brief', 'senna-finance'),
            'intro' => __('A user completed the recruiter outreach discovery chat. Review the brief below before deciding the outreach path.', 'senna-finance'),
            'details' => $details,
            'footnote' => home_url('/terminal/'),
        ]);

        $user_html = $this->build_editorial_floating_chat_email([
            'eyebrow' => __('Recruiter Outreach', 'senna-finance'),
            'title' => __('We Received Your Outreach Brief', 'senna-finance'),
            'recipient_label' => sprintf(__('Hi %s,', 'senna-finance'), $user_label !== '' ? $user_label : __('there', 'senna-finance')),
            'intro' => __('We received your recruiter outreach brief. The MENA Careers team will review your profile context and come back by email with the next step.', 'senna-finance'),
            'details' => [
                __('Target role', 'senna-finance') => $clean['target_role'],
                __('Target locations', 'senna-finance') => $clean['target_locations'],
                __('Desired outcome', 'senna-finance') => $clean['message_goal'] !== '' ? $clean['message_goal'] : __('Recruiter outreach support', 'senna-finance'),
            ],
            'footnote' => __('If you need to add anything else, reply to this email and the team will pick it up.', 'senna-finance'),
        ]);

        $reply_to = $user_label . ' <' . $user_email . '>';
        $admin_sent = $this->send_editorial_floating_chat_mail($admin_email, $subject, $admin_html, $reply_to);
        $user_sent = $this->send_editorial_floating_chat_mail($user_email, __('MENA Careers Recruiter Outreach Brief Received', 'senna-finance'), $user_html);

        if (!$admin_sent || !$user_sent) {
            wp_send_json_error([
                'message' => __('Unable to send the recruiter outreach brief right now. Please try again shortly.', 'senna-finance'),
            ], 500);
        }

        wp_send_json_success([
            'message' => __('Your recruiter outreach brief has been sent. We will review it and come back by email.', 'senna-finance'),
        ]);
    }

    /**
     * Send job help request to the admin email.
     */
    public function ajax_editorial_floating_chat_job_help()
    {
        check_ajax_referer('sffc_public_nonce', 'nonce');

        $target_role = sanitize_text_field(wp_unslash($_POST['target_role'] ?? ''));
        $seniority = sanitize_text_field(wp_unslash($_POST['seniority'] ?? ''));
        $location = sanitize_text_field(wp_unslash($_POST['location'] ?? ''));
        $comments = sanitize_textarea_field(wp_unslash($_POST['comments'] ?? ''));
        $identity = $this->get_editorial_floating_chat_identity();
        $user_label = $identity['label'];
        $user_email = $identity['email'];

        if ($target_role === '' || $seniority === '' || $location === '') {
            wp_send_json_error([
                'message' => __('Please complete the role, seniority, and location fields.', 'senna-finance'),
            ], 400);
        }

        if ($user_email === '') {
            $user_email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        }

        if ($user_email === '' || !is_email($user_email)) {
            wp_send_json_error([
                'message' => __('A valid email address is required so we can confirm your request.', 'senna-finance'),
            ], 400);
        }

        $admin_email = sanitize_email((string) get_option('admin_email'));
        if ($admin_email === '') {
            wp_send_json_error([
                'message' => __('Admin email is not configured.', 'senna-finance'),
            ], 500);
        }

        $subject = sprintf(
            __('New MENA Careers Job Help Request: %s', 'senna-finance'),
            $target_role
        );

        $admin_html = $this->build_editorial_floating_chat_email([
            'eyebrow' => __('Admin Alert', 'senna-finance'),
            'title' => __('New Job Help Request', 'senna-finance'),
            'intro' => __('A new Help me find a job request was submitted from the editorial floating chat.', 'senna-finance'),
            'details' => [
                __('Target role', 'senna-finance') => $target_role,
                __('Seniority', 'senna-finance') => $seniority,
                __('Location', 'senna-finance') => $location,
                __('Additional comments', 'senna-finance') => $comments !== '' ? $comments : __('None provided', 'senna-finance'),
                __('Submitted by', 'senna-finance') => $user_label,
                __('Email', 'senna-finance') => $user_email,
                __('User ID', 'senna-finance') => is_user_logged_in() ? (string) get_current_user_id() : 'Guest',
                __('Submitted at', 'senna-finance') => current_time('mysql'),
            ],
            'footnote' => home_url('/'),
        ]);
        $user_html = $this->build_editorial_floating_chat_email([
            'eyebrow' => __('Request Received', 'senna-finance'),
            'title' => __('We Have Your Job Search Request', 'senna-finance'),
            'recipient_label' => sprintf(__('Hi %s,', 'senna-finance'), $user_label !== '' ? $user_label : __('there', 'senna-finance')),
            'intro' => __('We have received your Help me find a job request. Our team will review your target role and come back to you shortly via email.', 'senna-finance'),
            'details' => [
                __('Target role', 'senna-finance') => $target_role,
                __('Seniority', 'senna-finance') => $seniority,
                __('Location', 'senna-finance') => $location,
            ],
            'footnote' => __('If you need to add more context, just reply to this email and the MENA Careers team will pick it up.', 'senna-finance'),
        ]);

        $admin_sent = $this->send_editorial_floating_chat_mail($admin_email, $subject, $admin_html, $user_label . ' <' . $user_email . '>');
        $user_sent = $this->send_editorial_floating_chat_mail($user_email, __('MENA Careers Job Search Request Received', 'senna-finance'), $user_html);

        if (!$admin_sent || !$user_sent) {
            wp_send_json_error([
                'message' => __('Unable to send your request right now. Please try again shortly.', 'senna-finance'),
            ], 500);
        }

        wp_send_json_success([
            'message' => __('We will be in touch shortly via email.', 'senna-finance'),
        ]);
    }

    /**
     * Send Career Assessment request to admin and confirmation to the user.
     */
    public function ajax_editorial_floating_chat_cv_review()
    {
        check_ajax_referer('sffc_public_nonce', 'nonce');

        $target_role = sanitize_text_field(wp_unslash($_POST['target_role'] ?? ''));
        $target_location = sanitize_text_field(wp_unslash($_POST['target_location'] ?? ''));
        $identity = $this->get_editorial_floating_chat_identity();
        $user_label = $identity['label'];
        $user_email = $identity['email'];

        if ($user_email === '') {
            $user_email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        }

        if ($target_role === '' || $target_location === '') {
            wp_send_json_error(['message' => __('Please complete the role and location fields.', 'senna-finance')], 400);
        }

        if ($user_email === '' || !is_email($user_email)) {
            wp_send_json_error(['message' => __('A valid email address is required so we can confirm your Career Assessment.', 'senna-finance')], 400);
        }

        if (empty($_FILES['cv_file']) || !is_array($_FILES['cv_file'])) {
            wp_send_json_error(['message' => __('Please upload your CV before submitting.', 'senna-finance')], 400);
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $uploaded = wp_handle_upload($_FILES['cv_file'], ['test_form' => false, 'mimes' => [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]]);

        if (!empty($uploaded['error'])) {
            wp_send_json_error(['message' => __('Unable to upload that CV file. Please use PDF, DOC, or DOCX.', 'senna-finance')], 400);
        }

        $attachment_path = (string) ($uploaded['file'] ?? '');
        if ($attachment_path === '' || !file_exists($attachment_path)) {
            wp_send_json_error(['message' => __('The uploaded CV could not be processed.', 'senna-finance')], 500);
        }

        $admin_email = sanitize_email((string) get_option('admin_email'));
        if ($admin_email === '') {
            @unlink($attachment_path);
            wp_send_json_error(['message' => __('Admin email is not configured.', 'senna-finance')], 500);
        }

        $subject_admin = sprintf(__('New MENA Careers Career Assessment Request: %s', 'senna-finance'), $target_role);
        $admin_html = $this->build_editorial_floating_chat_email([
            'eyebrow' => __('Admin Alert', 'senna-finance'),
            'title' => __('New Career Assessment Request', 'senna-finance'),
            'intro' => __('A new Career Assessment request was submitted from the editorial floating chat.', 'senna-finance'),
            'details' => [
                __('Target role', 'senna-finance') => $target_role,
                __('Target location', 'senna-finance') => $target_location,
                __('Submitted by', 'senna-finance') => $user_label,
                __('Email', 'senna-finance') => $user_email,
                __('User ID', 'senna-finance') => is_user_logged_in() ? (string) get_current_user_id() : 'Guest',
                __('Submitted at', 'senna-finance') => current_time('mysql'),
            ],
            'footnote' => __('The uploaded CV is attached to this email.', 'senna-finance'),
        ]);
        $user_html = $this->build_editorial_floating_chat_email([
            'eyebrow' => __('Request Received', 'senna-finance'),
            'title' => __('Your Career Assessment Request Is In', 'senna-finance'),
            'recipient_label' => sprintf(__('Hi %s,', 'senna-finance'), $user_label !== '' ? $user_label : __('there', 'senna-finance')),
            'intro' => __('We have received your Career Assessment request and our team will review it shortly. We will be in touch shortly via email.', 'senna-finance'),
            'details' => [
                __('Target role', 'senna-finance') => $target_role,
                __('Target location', 'senna-finance') => $target_location,
            ],
            'footnote' => __('If you need to send an updated CV, reply to this email and we will attach it to your Career Assessment request.', 'senna-finance'),
        ]);

        $admin_sent = $this->send_editorial_floating_chat_mail($admin_email, $subject_admin, $admin_html, $user_label . ' <' . $user_email . '>', [$attachment_path]);
        $user_sent = $this->send_editorial_floating_chat_mail($user_email, __('MENA Careers Career Assessment Request Received', 'senna-finance'), $user_html);

        @unlink($attachment_path);

        if (!$admin_sent || !$user_sent) {
            wp_send_json_error(['message' => __('Unable to submit your CV right now. Please try again shortly.', 'senna-finance')], 500);
        }

        wp_send_json_success([
            'message' => __('Your CV has been received. We will be in touch shortly via email.', 'senna-finance'),
        ]);
    }

    public function ajax_editorial_floating_chat_call_request()
    {
        check_ajax_referer('sffc_public_nonce', 'nonce');

        $availability_date = sanitize_text_field(wp_unslash($_POST['availability_date'] ?? ''));
        $availability_window = sanitize_text_field(wp_unslash($_POST['availability_window'] ?? ''));
        $comments = sanitize_textarea_field(wp_unslash($_POST['comments'] ?? ''));
        $identity = $this->get_editorial_floating_chat_identity();
        $user_label = $identity['label'];
        $user_email = $identity['email'];

        if ($user_email === '') {
            $user_email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        }

        if ($availability_date === '' || $availability_window === '') {
            wp_send_json_error(['message' => __('Please select your availability date and preferred time window.', 'senna-finance')], 400);
        }

        if ($user_email === '' || !is_email($user_email)) {
            wp_send_json_error(['message' => __('A valid email address is required so we can confirm your call request.', 'senna-finance')], 400);
        }

        $min_timestamp = strtotime('+2 days', current_time('timestamp'));
        $submitted_timestamp = strtotime($availability_date);
        if (!$submitted_timestamp || $submitted_timestamp < strtotime(wp_date('Y-m-d', $min_timestamp))) {
            wp_send_json_error(['message' => __('Please choose a date that is at least two days in advance.', 'senna-finance')], 400);
        }

        $admin_email = sanitize_email((string) get_option('admin_email'));
        if ($admin_email === '') {
            wp_send_json_error(['message' => __('Admin email is not configured.', 'senna-finance')], 500);
        }

        $admin_html = $this->build_editorial_floating_chat_email([
            'eyebrow' => __('Admin Alert', 'senna-finance'),
            'title' => __('New Call Request', 'senna-finance'),
            'intro' => __('A new Request A Call submission was sent from the editorial floating chat.', 'senna-finance'),
            'details' => [
                __('Availability date', 'senna-finance') => $availability_date,
                __('Preferred window', 'senna-finance') => $availability_window,
                __('Comments', 'senna-finance') => $comments !== '' ? $comments : __('None provided', 'senna-finance'),
                __('Submitted by', 'senna-finance') => $user_label,
                __('Email', 'senna-finance') => $user_email,
                __('User ID', 'senna-finance') => is_user_logged_in() ? (string) get_current_user_id() : 'Guest',
                __('Submitted at', 'senna-finance') => current_time('mysql'),
            ],
            'footnote' => home_url('/'),
        ]);
        $user_html = $this->build_editorial_floating_chat_email([
            'eyebrow' => __('Request Received', 'senna-finance'),
            'title' => __('Your Call Request Is Confirmed', 'senna-finance'),
            'recipient_label' => sprintf(__('Hi %s,', 'senna-finance'), $user_label !== '' ? $user_label : __('there', 'senna-finance')),
            'intro' => __('We have received your call request. Our team will review your preferred availability and contact you shortly via email.', 'senna-finance'),
            'details' => [
                __('Availability date', 'senna-finance') => $availability_date,
                __('Preferred window', 'senna-finance') => $availability_window,
            ],
            'footnote' => __('If your availability changes, reply to this email and we will update your request.', 'senna-finance'),
        ]);

        $admin_sent = $this->send_editorial_floating_chat_mail($admin_email, __('New MENA Careers Call Request', 'senna-finance'), $admin_html, $user_label . ' <' . $user_email . '>');
        $user_sent = $this->send_editorial_floating_chat_mail($user_email, __('MENA Careers Call Request Received', 'senna-finance'), $user_html);

        if (!$admin_sent || !$user_sent) {
            wp_send_json_error(['message' => __('Unable to submit your call request right now. Please try again shortly.', 'senna-finance')], 500);
        }

        wp_send_json_success([
            'message' => __('Your call request has been received. We will be in touch shortly via email.', 'senna-finance'),
        ]);
    }

    /**
     * Handle chat messages via AJAX
     */
    public function ajax_handle_chat()
    {
        // Simple nonce check like CREW version
        check_ajax_referer('sffc_public_nonce', 'nonce');

        $message = sanitize_text_field($_POST['message'] ?? '');
        $context = json_decode(stripslashes($_POST['context'] ?? '{}'), true);
        $conversation_id = sanitize_text_field($_POST['conversation_id'] ?? '');

        if (empty($message)) {
            wp_send_json_error(['message' => 'Message cannot be empty']);
            return;
        }

        // Get or create conversation ID
        if (empty($conversation_id)) {
            $conversation_id = wp_generate_uuid4();
        }

        // Get user context
        $user_context = $this->get_user_context();

        // Merge with provided context
        $full_context = array_merge($user_context, $context);

        if (!empty($full_context['source']) && in_array($full_context['source'], ['editorial_floating_chat', 'editorial_floating_chat_recruiter_outreach'], true)) {
            $editorial_response = $this->handle_editorial_floating_chat_message($message, $full_context, $conversation_id);
            wp_send_json_success([
                'response' => $editorial_response,
                'conversation_id' => $conversation_id,
                'timestamp' => current_time('mysql')
            ]);
        }

        // Generate response - simplified like CREW version
        $response = $this->generate_response($message, $full_context, $conversation_id);

        if ($response) {
            wp_send_json_success([
                'response' => $response,
                'conversation_id' => $conversation_id,
                'timestamp' => current_time('mysql')
            ]);
        } else {
            error_log('SFFC MENA Careers Chat Error: No response generated');
            wp_send_json_error(['message' => 'Sorry, I encountered an error. Please try again.']);
        }
    }

    /**
     * Handle article-specific chat from institutional article template
     */
    public function ajax_handle_article_chat()
    {
        $message = sanitize_text_field($_POST['message'] ?? '');
        $post_id = intval($_POST['post_id'] ?? 0);
        $article_title = sanitize_text_field($_POST['article_title'] ?? '');
        $article_excerpt = sanitize_textarea_field($_POST['article_excerpt'] ?? '');

        if (empty($message)) {
            wp_send_json_error(['message' => 'Message cannot be empty']);
            return;
        }

        // Build article context for AI
        $context = [
            'type' => 'article_analysis',
            'post_id' => $post_id,
            'article_title' => $article_title,
            'article_excerpt' => $article_excerpt,
        ];

        // Generate response using existing infrastructure
        $response = $this->generate_article_response($message, $context);

        if ($response) {
            wp_send_json_success([
                'response' => $response,
                'timestamp' => current_time('mysql')
            ]);
        } else {
            wp_send_json_error(['message' => 'Unable to generate response. Please try again.']);
        }
    }

    /**
     * Generate AI response for article questions
     */
    private function generate_article_response($message, $context)
    {
        // Check if Claude API is available
        if (!class_exists('SFFC_Claude_API_Manager')) {
            return $this->generate_fallback_article_response($message, $context);
        }

        $claude = SFFC_Claude_API_Manager::get_instance();
        if (!$claude->is_available()) {
            return $this->generate_fallback_article_response($message, $context);
        }

        $article_title = $context['article_title'] ?? 'this article';
        $article_excerpt = $context['article_excerpt'] ?? '';

        $prompt = "You are MENA Careers, an AI financial analyst assistant. A user is reading an article and has a question.

Article Title: {$article_title}

Article Excerpt: {$article_excerpt}

User Question: {$message}

Provide a helpful, concise response that:
1. Directly addresses the user's question
2. Uses information from the article context when relevant
3. Adds your knowledge to provide additional insight
4. Keeps the response under 200 words
5. Uses a professional, friendly tone

Response:";

        try {
            $result = $claude->send_message($prompt, [], 'analysis');
            if (!empty($result['success']) && !empty($result['response'])) {
                return '<p>' . nl2br(esc_html($result['response'])) . '</p>';
            }
        } catch (Exception $e) {
            error_log('SFFC Article Chat Error: ' . $e->getMessage());
        }

        return $this->generate_fallback_article_response($message, $context);
    }

    /**
     * Fallback response when AI is unavailable
     */
    private function generate_fallback_article_response($message, $context)
    {
        $title = esc_html($context['article_title'] ?? 'this article');

        $responses = [
            'explain' => "<p>This article about <strong>{$title}</strong> covers important financial developments. The AI analysis feature is currently being configured. Please check back shortly for detailed explanations.</p>",
            'summarize' => "<p>Summary of <strong>{$title}</strong>: The article discusses significant market developments. Full AI-powered summaries will be available once the service is fully configured.</p>",
            'implications' => "<p>Regarding <strong>{$title}</strong>: Understanding market implications requires careful analysis. Our AI service is being set up to provide detailed insights.</p>",
        ];

        $message_lower = strtolower($message);

        if (strpos($message_lower, 'explain') !== false) {
            return $responses['explain'];
        } elseif (strpos($message_lower, 'summar') !== false) {
            return $responses['summarize'];
        } elseif (strpos($message_lower, 'implic') !== false || strpos($message_lower, 'mean') !== false) {
            return $responses['implications'];
        }

        return "<p>Thank you for your question about <strong>{$title}</strong>. Our AI assistant is being configured to provide detailed analysis. Please try again shortly.</p>";
    }

    /**
     * Get user context for personalized responses
     */
    private function get_user_context()
    {
        $context = [
            'user_id' => get_current_user_id(),
            'session_id' => $_COOKIE['sffc_session'] ?? '',
            'recent_views' => [],
            'preferences' => []
        ];


        // Get user preferences if logged in
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();

            // Get user profile data
            if (class_exists('SFFC_User_Profile_Manager')) {
                $profile_manager = SFFC_User_Profile_Manager::get_instance();
                $profile = $profile_manager->get_user_profile($user_id);
                $context['profile'] = $profile;
            }

            // Get career journey/intake data from launcher
            $intake_data = get_user_meta($user_id, 'senna_intake_data', true);
            if (!empty($intake_data) && is_array($intake_data)) {
                // Map to human-readable labels for AI context
                $goal_labels = [
                    'transition' => 'career transition',
                    'advance' => 'advancing in their current path',
                    'explore' => 'exploring career options',
                    'pivot' => 'pivoting to a new industry'
                ];
                $situation_labels = [
                    'student' => 'student or recent graduate',
                    'analyst' => 'analyst level professional',
                    'associate' => 'associate level professional',
                    'senior' => 'senior professional',
                    'between' => 'currently between roles',
                    'other' => 'other situation'
                ];
                $timeline_labels = [
                    'immediate' => 'ready to move immediately',
                    '3months' => 'looking to move within 3 months',
                    '6months' => 'planning to move within 6 months',
                    'year' => 'considering a move within a year'
                ];
                $challenge_labels = [
                    'technical' => 'building technical skills',
                    'network' => 'building their professional network',
                    'experience' => 'gaining relevant experience',
                    'brand' => 'developing their personal brand',
                    'clarity' => 'getting career clarity',
                    'interview' => 'preparing for interviews'
                ];

                $context['career_journey'] = [
                    'goal' => $intake_data['goal'] ?? '',
                    'goal_description' => $goal_labels[$intake_data['goal'] ?? ''] ?? '',
                    'situation' => $intake_data['situation'] ?? '',
                    'situation_description' => $situation_labels[$intake_data['situation'] ?? ''] ?? '',
                    'timeline' => $intake_data['timeline'] ?? '',
                    'timeline_description' => $timeline_labels[$intake_data['timeline'] ?? ''] ?? '',
                    'challenge' => $intake_data['challenge'] ?? '',
                    'challenge_description' => $challenge_labels[$intake_data['challenge'] ?? ''] ?? ''
                ];
            }
        }

        // Get learned preferences
        if (!empty($context['session_id']) && class_exists('SFFC_Job_Preference_Tracker')) {
            $tracker = SFFC_Job_Preference_Tracker::get_instance();
            $context['preferences'] = $tracker->get_user_preferences($context['session_id']);
        }

        return $context;
    }

    private function handle_editorial_floating_chat_message($message, array $context, $conversation_id)
    {
        $normalized_message = $this->normalize_editorial_floating_chat_message((string) $message);
        $source = (string) ($context['source'] ?? '');
        $mode = (string) ($context['mode'] ?? '');

        if ($source === 'editorial_floating_chat_recruiter_outreach' || $mode === 'recruiter_outreach_discovery') {
            return [
                'type' => 'text',
                'content' => $this->get_editorial_floating_chat_recruiter_outreach_response($normalized_message, (string) $message, $context),
                'cards' => [],
                'actions' => [],
                'suggestions' => []
            ];
        }

        $detected_response = $this->get_editorial_floating_chat_detected_response($normalized_message);

        if (!empty($detected_response['content'])) {
            return [
                'type' => 'text',
                'content' => (string) $detected_response['content'],
                'cards' => [],
                'actions' => $detected_response['actions'] ?? [],
                'suggestions' => []
            ];
        }

        $this->send_editorial_floating_chat_support_followup_email((string) $message, $context, (string) $conversation_id);

        return [
            'type' => 'text',
            'content' => __("Ok, I'll have to get back to you on this one, I'll send you an email with more info.", 'senna-finance'),
            'cards' => [],
            'actions' => [],
            'suggestions' => []
        ];
    }

    private function get_editorial_floating_chat_recruiter_outreach_response($normalized_message, $message, array $context)
    {
        $answers = is_array($context['answers'] ?? null) ? $context['answers'] : [];
        $current_step = (string) ($context['current_step'] ?? '');
        $role = trim((string) ($answers['target_role'] ?? ''));
        $locations = trim((string) ($answers['target_locations'] ?? ''));
        $target_hint = trim($role . ($locations !== '' ? ' in ' . $locations : ''));

        if ($this->editorial_floating_chat_contains_any($normalized_message, [
            'what happens', 'next', 'process', 'how does this work', 'how it works', 'what do you do'
        ])) {
            return __(
                "This is a recruiter outreach discovery chat. I’ll collect the target role, seniority, locations, sectors, recruiter types, firms to target, firms to avoid, timeline, and the outcome you want. The team can then review the brief and decide the best recruiter outreach path.",
                'senna-finance'
            );
        }

        if ($this->editorial_floating_chat_contains_any($normalized_message, [
            'confidential', 'quiet', 'private', 'current employer', 'avoid', 'discreet', 'discrete'
        ])) {
            return __(
                "Yes. I’ll ask who to avoid, including your current employer or sensitive contacts. That becomes part of the outreach brief so the team knows where not to send anything.",
                'senna-finance'
            );
        }

        if ($this->editorial_floating_chat_contains_any($normalized_message, [
            'who', 'which recruiter', 'recruiters', 'headhunter', 'talent', 'firms', 'contacts'
        ])) {
            return __(
                "We can target agency recruiters, specialist headhunters, in-house talent teams, or warm-intro routes. The right mix depends on the role, seniority, sector, and location you give me in this brief.",
                'senna-finance'
            );
        }

        if ($this->editorial_floating_chat_contains_any($normalized_message, [
            'cv', 'resume', 'profile', 'linkedin', 'need from me', 'information', 'details'
        ])) {
            return __(
                "The useful details are your target role, seniority, target locations, sectors, preferred recruiter type, firms to target, firms to avoid, current status, timeline, and what outcome you want from recruiters. A Career Assessment can help later, but this chat is mainly for building the recruiter outreach brief.",
                'senna-finance'
            );
        }

        if ($this->editorial_floating_chat_contains_any($normalized_message, [
            'cost', 'price', 'paid', 'premium', 'member', 'membership', 'subscribe'
        ])) {
            return __(
                "Recruiter outreach is a premium workflow because it needs a reviewed brief, targeting decisions, and follow-up handling. I can still collect the brief here so the team knows exactly what you want to target.",
                'senna-finance'
            );
        }

        if ($this->editorial_floating_chat_contains_any($normalized_message, [
            'how long', 'when', 'timeline', 'asap', 'quick', 'fast'
        ])) {
            return __(
                "The timeline depends on how specific the target is and whether your profile is ready. I’ll capture your preferred timing, then the team can prioritise the outreach route around that.",
                'senna-finance'
            );
        }

        if ($this->editorial_floating_chat_contains_any($normalized_message, [
            'hi', 'hello', 'hey', 'salam', 'salaam'
        ])) {
            return __(
                "Hi, I’m Emily. I can answer questions about recruiter outreach, then we can continue building the brief from the same point.",
                'senna-finance'
            );
        }

        if ($this->editorial_floating_chat_contains_any($normalized_message, [
            'can i ask', 'could i ask', 'may i ask', 'quick question', 'i have a question'
        ])) {
            return __(
                "Of course. Ask me anything about the recruiter outreach process and I’ll answer it before we continue the brief.",
                'senna-finance'
            );
        }

        if ($target_hint !== '') {
            return sprintf(
                /* translators: %s: current target role/location hint */
                __('Of course. Ask me anything about how recruiter outreach will work for %s. I’ll answer it, then we can continue the brief from the same point.', 'senna-finance'),
                $target_hint
            );
        }

        return __(
            "Of course. Ask me anything about recruiter outreach. I’ll answer it, then we can continue the brief from the same point.",
            'senna-finance'
        );
    }

    private function normalize_editorial_floating_chat_message($message)
    {
        $normalized = strtolower(trim(wp_strip_all_tags((string) $message)));
        $normalized = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $normalized);
        $normalized = preg_replace('/\s+/u', ' ', (string) $normalized);

        return trim((string) $normalized);
    }

    private function editorial_floating_chat_contains_any($haystack, array $needles)
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && strpos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function get_editorial_floating_chat_detected_response($normalized_message)
    {
        $responses = [
            'find relevant roles' => __(
                "I can help you narrow the market into roles that are actually worth your time.\n\nThe best starting point is your target role, preferred location, seniority, and any hard constraints such as visa status, sector preference, or remote flexibility. MENA Careers can then prioritise roles where your background has a stronger chance of being noticed rather than showing you a generic job board.\n\nQuestion: should we start by matching you to live roles or by tightening your target criteria first?\n\nShort answer: start with target criteria first, then match roles against it so the feed stays relevant.",
                'senna-finance'
            ),
            'improve my cv' => __(
                "I can help you make your CV sharper for finance recruiters and hiring managers.\n\nThe biggest wins usually come from tightening the opening profile, making achievements measurable, matching keywords to the roles you want, and removing anything that makes the document feel generic. For analyst, associate, and investment roles, the CV needs to show judgement, modelling exposure, transaction or commercial context, and evidence that you can operate with precision.\n\nQuestion: should we review the CV for structure first or tailor it to a specific role?\n\nShort answer: tailor it to a specific role first, because that makes every line easier to judge.",
                'senna-finance'
            ),
            'help with networking' => __(
                "I can help you approach networking in a way that feels targeted rather than random.\n\nThe cleanest approach is to identify recruiters, hiring managers, alumni, and operators linked to your target sector, then send short messages with a clear reason for reaching out. The goal is not to ask for a job immediately. It is to create a credible first touch that can turn into a referral, recruiter intro, or useful market signal.\n\nQuestion: should we focus on recruiters first or hiring managers first?\n\nShort answer: start with recruiters if you need active roles quickly, and hiring managers if you want warmer long-term opportunities.",
                'senna-finance'
            ),
        ];

        if (isset($responses[$normalized_message])) {
            return [
                'content' => (string) $responses[$normalized_message],
                'actions' => []
            ];
        }

        $signup_action = [
            [
                'type' => 'signup_modal',
                'label' => __('Create a free MENA Careers account', 'senna-finance'),
                'fallback_url' => home_url('/terminal/')
            ]
        ];

        if ($this->editorial_floating_chat_contains_any($normalized_message, [
            'hi', 'hello', 'hey', 'good morning', 'good afternoon', 'good evening',
            'salam', 'salaam', 'السلام', 'مرحبا', 'اهلا', 'أهلا', 'هلا', 'سلام', 'صباح الخير', 'مساء الخير'
        ])) {
            return [
                'content' => __(
                    "Hi, I can help with roles, CV improvements, recruiter intros, networking, and how to use MENA Careers.\n\nQuestion: are you trying to find a role, improve your CV, or speak to recruiters?\n\nShort answer: if you are actively looking, start with roles first, then use CV and networking support to improve your response rate.",
                    'senna-finance'
                ),
                'actions' => []
            ];
        }

        if ($this->editorial_floating_chat_contains_any($normalized_message, [
            'get a job', 'find a job', 'help me get a job', 'looking for a job', 'need a job',
            'job search', 'roles', 'vacancy', 'vacancies', 'opportunities', 'hiring', 'work',
            'وظيفة', 'وظائف', 'عمل', 'فرصة', 'فرص', 'ابحث عن وظيفة', 'أبحث عن وظيفة'
        ])) {
            return [
                'content' => __(
                    "The fastest way to use MENA Careers is to create an account, add your target role and location, then let the platform surface relevant roles and recruiter contacts around that profile.\n\nQuestion: should you create an account before searching?\n\nShort answer: yes. Creating a MENA Careers account gives us enough context to show better roles, save your preferences, and help you follow up properly.",
                    'senna-finance'
                ),
                'actions' => $signup_action
            ];
        }

        if ($this->editorial_floating_chat_contains_any($normalized_message, [
            'apply', 'application', 'how do i apply', 'where do i apply', 'send application',
            'easy apply', 'auto apply', 'smart apply', 'submit my cv', 'submit resume',
            'تقديم', 'أقدم', 'اقدم', 'طلب', 'سيرة', 'cv'
        ])) {
            return [
                'content' => __(
                    "To apply through MENA Careers, create a free account first so your profile, CV context, and target role are attached to the application. After that, you can use Smart Apply, recruiter intros, or direct application links depending on the role.\n\nQuestion: is account creation required before MENA Careers can manage an application?\n\nShort answer: yes, because the application needs to be tied to your profile and email so we can track it properly.",
                    'senna-finance'
                ),
                'actions' => $signup_action
            ];
        }

        if ($this->editorial_floating_chat_contains_any($normalized_message, [
            'cv', 'resume', 'cover letter', 'ats', 'linkedin profile', 'improve my cv',
            'rewrite', 'review my cv', 'review my resume', 'profile review', 'سيرة ذاتية', 'لينكدان'
        ])) {
            return [
                'content' => __(
                    "MENA Careers can help you improve the CV by matching it against the type of roles you want, checking keywords, tightening the positioning, and identifying what a recruiter may miss.\n\nQuestion: should you improve the CV generally or against a specific role?\n\nShort answer: use a specific role whenever possible. It makes the feedback sharper and helps us improve the parts that actually affect applications.",
                    'senna-finance'
                ),
                'actions' => $signup_action
            ];
        }

        if ($this->editorial_floating_chat_contains_any($normalized_message, [
            'network', 'networking', 'recruiter', 'recruiters', 'hiring manager', 'intro', 'introduction',
            'message recruiter', 'contact recruiter', 'reach out', 'referral', 'تواصل', 'تعرف', 'مقدمة', 'مسؤول توظيف'
        ])) {
            return [
                'content' => __(
                    "For networking, the best route is targeted outreach rather than sending generic messages. MENA Careers can help identify relevant recruiters and hiring managers, then shape a cleaner first-touch intro using your profile.\n\nQuestion: should you message recruiters directly or ask MENA Careers to prepare the intro?\n\nShort answer: use MENA Careers when you want the message to be more tailored and easier to track.",
                    'senna-finance'
                ),
                'actions' => $signup_action
            ];
        }

        if ($this->editorial_floating_chat_contains_any($normalized_message, [
            'account', 'sign up', 'signup', 'register', 'join', 'membership', 'member', 'login',
            'price', 'pricing', 'cost', 'subscription', 'اشتراك', 'تسجيل', 'حساب', 'عضوية'
        ])) {
            return [
                'content' => __(
                    "You can start by creating a MENA Careers account. That gives you access to your profile workspace, role tracking, and the routes we use for applications, CV support, and recruiter intros.\n\nQuestion: can you start free and decide later?\n\nShort answer: yes. Create the account first, then choose the level of support once you know what you need.",
                    'senna-finance'
                ),
                'actions' => $signup_action
            ];
        }

        if ($this->editorial_floating_chat_contains_any($normalized_message, [
            'شكرا', 'شكرًا', 'مشكور', 'thank you', 'thanks', 'cheers'
        ])) {
            return [
                'content' => __(
                    "You are welcome. I can help with roles, CV positioning, applications, or recruiter outreach from here.\n\nQuestion: what should we focus on next?\n\nShort answer: if you want momentum quickly, start with one target role and one target location.",
                    'senna-finance'
                ),
                'actions' => []
            ];
        }

        return [
            'content' => '',
            'actions' => []
        ];
    }

    private function send_editorial_floating_chat_support_followup_email($message, array $context, $conversation_id)
    {
        $support_email = 'support.team@joinsenna.com';
        $identity = $this->get_editorial_floating_chat_identity();
        $user_id = is_user_logged_in() ? get_current_user_id() : 0;
        $subject = __('Floating chat follow-up needed', 'senna-finance');
        $html = $this->build_editorial_floating_chat_email([
            'eyebrow' => __('Support Follow-Up', 'senna-finance'),
            'title' => __('Floating Chat Message Needs Review', 'senna-finance'),
            'intro' => __('A user asked a question in the editorial floating chat that needs a manual follow-up.', 'senna-finance'),
            'details' => [
                __('Message', 'senna-finance') => $message,
                __('Submitted by', 'senna-finance') => (string) ($identity['label'] ?? __('Guest visitor', 'senna-finance')),
                __('Email', 'senna-finance') => (string) ($identity['email'] ?? ''),
                __('User ID', 'senna-finance') => $user_id > 0 ? (string) $user_id : __('Guest', 'senna-finance'),
                __('Conversation ID', 'senna-finance') => $conversation_id,
                __('Source', 'senna-finance') => (string) ($context['source'] ?? 'editorial_floating_chat'),
                __('Submitted at', 'senna-finance') => current_time('mysql'),
            ],
            'footnote' => home_url('/'),
        ]);

        $reply_to = '';
        if (!empty($identity['email']) && is_email((string) $identity['email'])) {
            $reply_to = trim((string) ($identity['label'] ?? __('MENA Careers user', 'senna-finance'))) . ' <' . sanitize_email((string) $identity['email']) . '>';
        }

        $this->send_editorial_floating_chat_mail($support_email, $subject, $html, $reply_to);
    }

    /**
     * Generate AI response
     */
    private function generate_response($message, $context, $conversation_id)
    {
        $requested_mode = 'career_strategy';
        if (!empty($context['mode'])) {
            $requested_mode = sanitize_key($context['mode']);
            if (empty($requested_mode)) {
                $requested_mode = 'career_strategy';
            }
        }

        // Check if we have the MENA Careers Integration Helper
        if (class_exists('SFFC_Senna_Integration_Helper')) {
            $senna = SFFC_Senna_Integration_Helper::get_instance();

            // Prepare the query
            $query_data = [
                'message' => $message,
                'context' => $context,
                'conversation_id' => $conversation_id,
                'mode' => $requested_mode
            ];

            // Get response from MENA Careers
            $response = $senna->process_career_query($query_data);

            // Format the response
            return $this->format_response($response);
        }

        // Fallback response system
        return $this->generate_fallback_response($message, $context);
    }

    /**
     * Format AI response for display
     */
    private function format_response($response)
    {
        if (is_array($response)) {
            return $response; // Already formatted
        }

        // Parse response for special formats
        $formatted = [
            'type' => 'text',
            'content' => $response,
            'cards' => [],
            'actions' => [],
            'suggestions' => []
        ];

        // Check for analysis cards
        if (strpos($response, '[CARD:') !== false) {
            $formatted = $this->extract_cards($response);
        }

        // Check for action buttons
        if (strpos($response, '[ACTION:') !== false) {
            $formatted['actions'] = $this->extract_actions($response);
        }

        // Generate follow-up suggestions
        $formatted['suggestions'] = $this->generate_suggestions($response);

        return $formatted;
    }

    /**
     * Generate fallback response when AI is unavailable
     */
    private function generate_fallback_response($message, $context)
    {
        $message_lower = strtolower($message);

        // Analyze message intent
        $intent = $this->detect_intent($message_lower);

        // Generate appropriate response
        switch ($intent) {
            case 'greeting':
                return [
                    'type' => 'text',
                    'content' => "Hello! I'm here to help you navigate your career journey. What would you like to explore today?",
                    'suggestions' => [
                        "Help me identify my ideal role",
                        "Review salary expectations",
                        "Suggest career development steps"
                    ]
                ];


            case 'salary':
                return $this->salary_benchmark_response($context);

            case 'career_path':
                return $this->career_path_response($context);

            case 'job_search':
                return $this->job_search_response($context);

            default:
                return [
                    'type' => 'text',
                    'content' => "I can help you with career strategy, opportunity analysis, salary insights, and application planning. What specific aspect would you like to explore?",
                    'suggestions' => [
                        "Compare opportunities",
                        "Salary benchmarking",
                        "Interview preparation"
                    ]
                ];
        }
    }

    /**
     * Detect user intent from message
     */
    private function detect_intent($message)
    {
        $intents = [
            'greeting' => ['hello', 'hi', 'hey', 'good morning', 'good afternoon'],
            'salary' => ['salary', 'compensation', 'pay', 'money', 'worth', 'negotiate'],
            'career_path' => ['career path', 'progression', 'next step', 'future', 'growth'],
            'job_search' => ['find', 'search', 'looking for', 'opportunities', 'roles']
        ];

        foreach ($intents as $intent => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($message, $keyword) !== false) {
                    return $intent;
                }
            }
        }

        return 'general';
    }


    /**
     * Generate salary benchmark response
     */
    private function salary_benchmark_response($context)
    {
        return [
            'type' => 'analysis',
            'content' => "Based on your profile and market data, here's your salary benchmark analysis:",
            'cards' => [
                [
                    'type' => 'salary',
                    'title' => 'Salary Insights',
                    'data' => [
                        'market_range' => '$90k - $140k',
                        'your_target' => '$120k',
                        'percentile' => '75th',
                        'factors' => [
                            'Experience' => '+15%',
                            'Skills' => '+10%',
                            'Location' => '-5%'
                        ]
                    ]
                ]
            ],
            'suggestions' => [
                "Negotiation tactics",
                "Skill gaps to address",
                "Regional variations"
            ]
        ];
    }

    /**
     * Generate career path response
     */
    private function career_path_response($context)
    {
        return [
            'type' => 'visualization',
            'content' => "Here's your potential career progression based on current market trends:",
            'cards' => [
                [
                    'type' => 'path',
                    'title' => 'Career Trajectory',
                    'stages' => [
                        ['role' => 'Current Position', 'timeline' => 'Now'],
                        ['role' => 'Senior Analyst', 'timeline' => '1-2 years'],
                        ['role' => 'Manager', 'timeline' => '3-5 years'],
                        ['role' => 'Director', 'timeline' => '5-8 years']
                    ]
                ]
            ],
            'actions' => [
                ['label' => 'Skill Development Plan', 'action' => 'skills'],
                ['label' => 'Referral Strategy', 'action' => 'network']
            ]
        ];
    }

    /**
     * Generate job search response
     */
    private function job_search_response($context)
    {
        return [
            'type' => 'search',
            'content' => "I'll help you find the right opportunities. Let me understand your preferences better:",
            'cards' => [
                [
                    'type' => 'preferences',
                    'title' => 'Quick Preferences',
                    'options' => [
                        'Industries' => ['Finance', 'Tech', 'Consulting'],
                        'Locations' => ['London', 'New York', 'Remote'],
                        'Company Size' => ['Startup', 'Mid-size', 'Enterprise']
                    ]
                ]
            ],
            'actions' => [
                ['label' => 'Refine Search', 'action' => 'refine'],
                [
                    'label' => 'See All Matches',
                    'action' => 'browse',
                    'prompts' => [
                        'Show me available job opportunities I can browse through',
                        'I want to browse current job opportunities',
                        'Display all available positions for me to explore',
                        'Let me see the job opportunities you have',
                        'Show me roles I might be interested in',
                        'Browse through potential career opportunities'
                    ]
                ]
            ]
        ];
    }

    /**
     * Extract cards from response text
     */
    private function extract_cards($response)
    {
        // Parse special card syntax
        // [CARD:type:data]
        $cards = [];

        preg_match_all('/\[CARD:([^:]+):([^\]]+)\]/', $response, $matches);

        foreach ($matches[0] as $index => $full_match) {
            $type = $matches[1][$index];
            $data = json_decode($matches[2][$index], true) ?: $matches[2][$index];

            $cards[] = [
                'type' => $type,
                'data' => $data
            ];

            // Remove card syntax from response
            $response = str_replace($full_match, '', $response);
        }

        return [
            'type' => 'mixed',
            'content' => trim($response),
            'cards' => $cards
        ];
    }

    /**
     * Extract actions from response
     */
    private function extract_actions($response)
    {
        $actions = [];

        preg_match_all('/\[ACTION:([^:]+):([^\]]+)\]/', $response, $matches);

        foreach ($matches[0] as $index => $full_match) {
            $actions[] = [
                'label' => $matches[1][$index],
                'action' => $matches[2][$index]
            ];
        }

        return $actions;
    }

    /**
     * Generate follow-up suggestions
     */
    private function generate_suggestions($response)
    {
        // Analyze response content to generate relevant suggestions
        $suggestions = [];

        if (strpos($response, 'salary') !== false) {
            $suggestions[] = "How do I negotiate effectively?";
            $suggestions[] = "What benefits should I prioritize?";
        }

        if (strpos($response, 'opportunity') !== false || strpos($response, 'job') !== false) {
            $suggestions[] = "Compare with similar roles";
            $suggestions[] = "Analyze company culture";
        }

        if (strpos($response, 'skill') !== false) {
            $suggestions[] = "Which certifications are valuable?";
            $suggestions[] = "Create a learning plan";
        }

        // Default suggestions if none generated
        if (empty($suggestions)) {
            $suggestions = [
                "Tell me more",
                "What are my options?",
                "How can I improve?",
                "Next steps?"
            ];
        }

        return array_slice($suggestions, 0, 4); // Limit to 4 suggestions
    }

    /**
     * Get contextual quick prompts
     */
    public function ajax_get_quick_prompts()
    {
        check_ajax_referer('sffc_public_nonce', 'nonce');

        $context = json_decode(stripslashes($_POST['context'] ?? '{}'), true);

        $prompts = $this->generate_quick_prompts($context);

        wp_send_json_success(['prompts' => $prompts]);
    }

    /**
     * Generate quick prompts based on context
     */
    private function generate_quick_prompts($context)
    {
        $prompts = [];

        // Default career prompts
        $prompts[] = [
            'icon' => '▸',
            'text' => 'Help me find opportunities',
            'action' => 'find_opportunities'
        ];

        $prompts[] = [
            'icon' => '►',
            'text' => 'Create application strategy',
            'action' => 'application_strategy'
        ];

        $prompts[] = [
            'icon' => '◆',
            'text' => 'Compare opportunities',
            'action' => 'compare_jobs'
        ];

        // Based on time of day
        $hour = date('G');
        if ($hour < 12) {
            $prompts[] = [
                'icon' => '◉',
                'text' => "What's trending in finance today?",
                'action' => 'daily_insights'
            ];
        } elseif ($hour < 17) {
            $prompts[] = [
                'icon' => '◈',
                'text' => 'Afternoon market update',
                'action' => 'market_update'
            ];
        } else {
            $prompts[] = [
                'icon' => '★',
                'text' => 'Career reflection exercise',
                'action' => 'career_reflection'
            ];
        }

        // Contextual based on page/recent activity
        if (isset($context['recent_view']) && !empty($context['recent_view'])) {
            $prompts[] = [
                'icon' => '◦',
                'text' => 'Tell me about ' . $context['recent_view'],
                'action' => 'job_details'
            ];
        }

        // Always available strategic prompts
        $strategic_prompts = [
            [
                'icon' => '■',
                'text' => 'Salary negotiation tactics',
                'action' => 'salary_negotiation'
            ],
            [
                'icon' => '▫',
                'text' => 'Interview preparation',
                'action' => 'interview_prep'
            ],
            [
                'icon' => '▹',
                'text' => 'Career path planning',
                'action' => 'career_path'
            ]
        ];

        // Add 1-2 strategic prompts randomly
        shuffle($strategic_prompts);
        $prompts = array_merge($prompts, array_slice($strategic_prompts, 0, 2));

        // Limit to 6 prompts max
        return array_slice($prompts, 0, 6);
    }

    /**
     * Get current context for frontend
     */
    public function ajax_get_context()
    {
        check_ajax_referer('sffc_public_nonce', 'nonce');

        $context = $this->get_user_context();

        wp_send_json_success(['context' => $context]);
    }
}

// Initialize
SFFC_Senna_Chat_Interface::get_instance();
