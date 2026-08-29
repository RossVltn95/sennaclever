<?php
/**
 * PE News Dashboard
 *
 * Provides a three-panel social-style news interface that merges
 * sffc_pe_news and sffc_pe_deal content with Claude-powered analytics.
 *
 * @package SennaCareers
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_PE_News_Dashboard
{
    /**
     * Singleton instance
     *
     * @var self|null
     */
    private static $instance = null;

    /**
     * Claude integration manager
     *
     * @var SFFC_Claude_API_Manager|null
     */
    private $claude = null;

    /**
     * Template library for fallbacks
     *
     * @var SFFC_Template_Library|null
     */
    private $template_library = null;

    /**
     * Temporary storage for messaging shortcode injection
     *
     * @var string|null
     */
    private $messaging_original_content = null;

    /**
     * Tracks whether we injected the messaging shortcode placeholder
     *
     * @var bool
     */
    private $messaging_placeholder_added = false;

    /**
     * Get instance
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->claude = class_exists('SFFC_Claude_API_Manager') ? SFFC_Claude_API_Manager::get_instance() : null;
        $this->template_library = class_exists('SFFC_Template_Library') ? new SFFC_Template_Library() : null;

        add_shortcode('sffc_pe_newsroom', [$this, 'render_dashboard']);
        add_shortcode('sffc_newsroom_terminal', [$this, 'render_newsroom_terminal']);
        add_shortcode('sffc_ask_senna', [$this, 'render_ask_senna_shortcode']);
        add_shortcode('sffc_live_expert_chat', [$this, 'render_live_expert_chat_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'prime_messaging_assets'], 5);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_enqueue_scripts', [$this, 'conditionally_enqueue_chat_assets'], 15);
        add_action('wp_enqueue_scripts', [$this, 'restore_messaging_content'], 20);

        add_action('wp_ajax_sffc_fetch_newsroom_analytics', [$this, 'ajax_fetch_analytics']);
        add_action('wp_ajax_nopriv_sffc_fetch_newsroom_analytics', [$this, 'ajax_fetch_analytics']);
        add_action('wp_ajax_sffc_dashboard_quick_assist', [$this, 'ajax_quick_assist']);
        add_action('wp_ajax_nopriv_sffc_dashboard_quick_assist', [$this, 'ajax_quick_assist']);
        add_action('wp_ajax_sffc_toggle_saved_item', [$this, 'ajax_toggle_saved_item']);
        add_action('wp_ajax_nopriv_sffc_toggle_saved_item', [$this, 'ajax_toggle_saved_item']);
        add_action('wp_ajax_sffc_dashboard_filter', [$this, 'ajax_dashboard_filter']);
        add_action('wp_ajax_nopriv_sffc_dashboard_filter', [$this, 'ajax_dashboard_filter']);
        add_action('wp_ajax_sffc_load_more_posts', [$this, 'ajax_load_more_posts']);
        add_action('wp_ajax_nopriv_sffc_load_more_posts', [$this, 'ajax_load_more_posts']);

        // Newsroom Terminal AJAX handlers
        add_action('wp_ajax_nrt_load_story', [$this, 'ajax_nrt_load_story']);
        add_action('wp_ajax_nopriv_nrt_load_story', [$this, 'ajax_nrt_load_story']);
        add_action('wp_ajax_nrt_load_job', [$this, 'ajax_nrt_load_job']);
        add_action('wp_ajax_nopriv_nrt_load_job', [$this, 'ajax_nrt_load_job']);
        add_action('wp_ajax_nrt_load_more_stories', [$this, 'ajax_nrt_load_more_stories']);
        add_action('wp_ajax_nopriv_nrt_load_more_stories', [$this, 'ajax_nrt_load_more_stories']);
        add_action('wp_ajax_nrt_get_firm_content', [$this, 'ajax_nrt_get_firm_content']);
        add_action('wp_ajax_nopriv_nrt_get_firm_content', [$this, 'ajax_nrt_get_firm_content']);

        // NRT Matches Tab AJAX handlers
        add_action('wp_ajax_nrt_load_matches', [$this, 'ajax_nrt_load_matches']);
        add_action('wp_ajax_nrt_load_match_detail', [$this, 'ajax_nrt_load_match_detail']);
        add_action('wp_ajax_nrt_update_match_status', [$this, 'ajax_nrt_update_match_status']);

        // NRT Contacts Tab AJAX handlers
        add_action('wp_ajax_nrt_load_contacts', [$this, 'ajax_nrt_load_contacts']);
        add_action('wp_ajax_nopriv_nrt_load_contacts', [$this, 'ajax_nrt_load_contacts']);
        add_action('wp_ajax_nrt_load_contact_detail', [$this, 'ajax_nrt_load_contact_detail']);
        add_action('wp_ajax_nopriv_nrt_load_contact_detail', [$this, 'ajax_nrt_load_contact_detail']);
        add_action('wp_ajax_nrt_get_contact_filters', [$this, 'ajax_nrt_get_contact_filters']);
        add_action('wp_ajax_nopriv_nrt_get_contact_filters', [$this, 'ajax_nrt_get_contact_filters']);

        // NRT Companies Tab AJAX handlers
        add_action('wp_ajax_nrt_load_companies', [$this, 'ajax_nrt_load_companies']);
        add_action('wp_ajax_nrt_load_company_detail', [$this, 'ajax_nrt_load_company_detail']);
        add_action('wp_ajax_nrt_get_company_filters', [$this, 'ajax_nrt_get_company_filters']);

        // NRT Profile Networking AJAX handlers
        add_action('wp_ajax_nrt_save_contact', [$this, 'ajax_nrt_save_contact']);
        add_action('wp_ajax_nrt_unsave_contact', [$this, 'ajax_nrt_unsave_contact']);
        add_action('wp_ajax_nrt_get_saved_contacts', [$this, 'ajax_nrt_get_saved_contacts']);
        add_action('wp_ajax_nrt_save_target_company', [$this, 'ajax_nrt_save_target_company']);
        add_action('wp_ajax_nrt_remove_target_company', [$this, 'ajax_nrt_remove_target_company']);
        add_action('wp_ajax_nrt_get_target_companies', [$this, 'ajax_nrt_get_target_companies']);
        add_action('wp_ajax_nrt_log_outreach', [$this, 'ajax_nrt_log_outreach']);
        add_action('wp_ajax_nrt_remove_outreach', [$this, 'ajax_nrt_remove_outreach']);
        add_action('wp_ajax_nrt_get_outreach_log', [$this, 'ajax_nrt_get_outreach_log']);
        add_action('wp_ajax_nrt_get_networking_stats', [$this, 'ajax_nrt_get_networking_stats']);

        // NRT Candidate Opportunities AJAX handlers (legacy - kept for backwards compatibility)
        add_action('wp_ajax_nrt_load_opportunities', [$this, 'ajax_nrt_load_opportunities']);
        add_action('wp_ajax_nrt_load_opportunity_detail', [$this, 'ajax_nrt_load_opportunity_detail']);
        add_action('wp_ajax_nrt_save_opportunity', [$this, 'ajax_nrt_save_opportunity']);
        add_action('wp_ajax_nrt_dismiss_opportunity', [$this, 'ajax_nrt_dismiss_opportunity']);

        // NRT Recruiter Posts AJAX handlers (public posts managed by admin)
        add_action('wp_ajax_nrt_load_recruiter_posts', [$this, 'ajax_nrt_load_recruiter_posts']);
        add_action('wp_ajax_nopriv_nrt_load_recruiter_posts', [$this, 'ajax_nrt_load_recruiter_posts']);
        add_action('wp_ajax_nrt_load_recruiter_post_detail', [$this, 'ajax_nrt_load_recruiter_post_detail']);
        add_action('wp_ajax_nopriv_nrt_load_recruiter_post_detail', [$this, 'ajax_nrt_load_recruiter_post_detail']);

        // NRT Candidate Conversations AJAX handlers
        add_action('wp_ajax_nrt_load_conversations', [$this, 'ajax_nrt_load_conversations']);
        add_action('wp_ajax_nrt_load_conversation_messages', [$this, 'ajax_nrt_load_conversation_messages']);
        add_action('wp_ajax_nrt_send_message', [$this, 'ajax_nrt_send_message']);
        add_action('wp_ajax_nrt_mark_conversation_read', [$this, 'ajax_nrt_mark_conversation_read']);
        add_action('wp_ajax_nrt_toggle_conversation_star', [$this, 'ajax_nrt_toggle_conversation_star']);

        // Cache invalidation when relevant posts are updated
        add_action('save_post', [$this, 'invalidate_terminal_cache'], 10, 2);
        add_action('delete_post', [$this, 'invalidate_terminal_cache_on_delete']);
    }

    /**
     * Invalidate newsroom terminal cache when posts are saved
     */
    public function invalidate_terminal_cache($post_id, $post)
    {
        // Only invalidate for relevant post types
        $relevant_types = array('sffc_pe_news', 'sffc_pe_deal', 'sffc_pe_signal', 'sffc_job', 'sffc_research', 'insights', 'post');
        if (!in_array($post->post_type, $relevant_types)) {
            return;
        }

        // Delete all newsroom terminal transients
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_nrt_context_%' OR option_name LIKE '_transient_timeout_nrt_context_%'");
    }

    /**
     * Invalidate cache on post deletion
     */
    public function invalidate_terminal_cache_on_delete($post_id)
    {
        $post = get_post($post_id);
        if ($post) {
            $this->invalidate_terminal_cache($post_id, $post);
        }
    }

    /**
     * Determine if assets should load on the current request
     */
    private function should_enqueue_assets()
    {
        global $post;

        if (!is_a($post, 'WP_Post')) {
            return false;
        }

        return has_shortcode($post->post_content, 'sffc_pe_newsroom');
    }

    /**
     * Temporarily inject the messaging shortcode so the messaging plugin
     * enqueues its assets even when rendered inside our dashboard template.
     */
    public function prime_messaging_assets()
    {
        if (!shortcode_exists('senna_messaging')) {
            return;
        }

        if (!$this->should_enqueue_assets()) {
            return;
        }

        global $post;
        if (!is_a($post, 'WP_Post')) {
            return;
        }

        if ($this->messaging_placeholder_added || strpos($post->post_content, '[senna_messaging') !== false) {
            return;
        }

        $this->messaging_placeholder_added = true;
        $this->messaging_original_content = $post->post_content;
        $post->post_content .= "\n\n[senna_messaging]";
    }

    /**
     * Restore the global post content once enqueueing is finished.
     */
    public function restore_messaging_content()
    {
        if (!$this->messaging_placeholder_added) {
            return;
        }

        global $post;
        if (is_a($post, 'WP_Post')) {
            $post->post_content = $this->messaging_original_content;
        }

        $this->messaging_placeholder_added = false;
        $this->messaging_original_content = null;
    }

    /**
     * Enqueue CSS/JS for the dashboard
     */
    public function enqueue_assets()
    {
        if (!$this->should_enqueue_assets()) {
            return;
        }

        $this->enqueue_core_assets();
    }

    public function enqueue_assets_forced()
    {
        $this->enqueue_core_assets();
    }

    private function enqueue_core_assets()
    {
        $this->enqueue_chat_assets();

        if (!wp_style_is('sffc-pe-news-dashboard', 'enqueued')) {
            wp_enqueue_style(
                'sffc-pe-news-dashboard',
                SFFC_PLUGIN_URL . 'assets/css/pe-news-dashboard.css',
                array(),
                defined('SFFC_VERSION') ? SFFC_VERSION : filemtime(SFFC_PLUGIN_DIR . 'assets/css/pe-news-dashboard.css')
            );
        }

        if (!wp_script_is('sffc-pe-news-dashboard', 'enqueued')) {
            wp_enqueue_script(
                'sffc-pe-news-dashboard',
                SFFC_PLUGIN_URL . 'assets/js/pe-news-dashboard.js',
                array('jquery'),
                defined('SFFC_VERSION') ? SFFC_VERSION : filemtime(SFFC_PLUGIN_DIR . 'assets/js/pe-news-dashboard.js'),
                true
            );
        }

        wp_localize_script('sffc-pe-news-dashboard', 'sffcNewsDashboard', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'ajaxUrl' => admin_url('admin-ajax.php'), // Alternative naming
            'nonce' => wp_create_nonce('sffc_public_nonce'),
            'isLoggedIn' => is_user_logged_in(),
            'strings' => array(
                'refreshing' => __('Refreshing analytics…', 'senna-finance'),
                'error' => __('Unable to refresh analytics right now. Please try again shortly.', 'senna-finance'),
                'login_required' => __('Please sign in to save stories and jobs.', 'senna-finance'),
                'save_error' => __('Unable to update your saved list. Please try again.', 'senna-finance'),
                'save_label' => __('Save', 'senna-finance'),
                'saved_label' => __('Saved', 'senna-finance'),
                'trending_insights_title' => __('Trending Today', 'senna-finance'),
                'trending_jobs_title' => __('Trending Roles', 'senna-finance'),
                'plan_prompt' => __('Select a plan to continue.', 'senna-finance'),
                'plan_loading' => __('Loading secure checkout…', 'senna-finance'),
                'plan_ready' => __('Checkout ready.', 'senna-finance'),
                'loading' => __('Loading...', 'senna-finance'),
                'load_more' => __('Load more', 'senna-finance'),
                'no_more' => __('No more items', 'senna-finance'),
                'searching' => __('Searching...', 'senna-finance'),
                'clear_search' => __('Clear Search', 'senna-finance'),
                'no_results' => __('No results found', 'senna-finance')
            )
        ));
    }

    public function conditionally_enqueue_chat_assets()
    {
        global $post;

        if (is_admin()) {
            return;
        }

        $contains_shortcode = false;
        if (is_a($post, 'WP_Post')) {
            $contains_shortcode = has_shortcode($post->post_content, 'sffc_ask_senna');
        }

        if ($contains_shortcode) {
            $this->enqueue_chat_assets();
        }
    }

    private function enqueue_chat_assets()
    {
        if (!wp_style_is('sffc-pe-news-dashboard', 'enqueued')) {
            wp_enqueue_style(
                'sffc-pe-news-dashboard',
                SFFC_PLUGIN_URL . 'assets/css/pe-news-dashboard.css',
                array(),
                defined('SFFC_VERSION') ? SFFC_VERSION : filemtime(SFFC_PLUGIN_DIR . 'assets/css/pe-news-dashboard.css')
            );
        }

        if (!wp_script_is('sffc-ask-senna-widget', 'enqueued')) {
            wp_enqueue_script(
                'sffc-ask-senna-widget',
                SFFC_PLUGIN_URL . 'assets/js/ask-senna-widget.js',
                array('jquery'),
                defined('SFFC_VERSION') ? SFFC_VERSION : filemtime(SFFC_PLUGIN_DIR . 'assets/js/ask-senna-widget.js'),
                true
            );

            wp_localize_script('sffc-ask-senna-widget', 'sffcAskSenna', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sffc_public_nonce'),
                'avatar' => SFFC_PLUGIN_URL . 'senna.jpeg',
                'pluginUrl' => SFFC_PLUGIN_URL,
                'greetingText' => __('I can surface hidden roles, recruiter intros, and resume intel—what do you need?', 'senna-finance'),
                'greetingDelay' => 5000,
                'isLoggedIn' => is_user_logged_in(),
                'placeholder' => __('Ask about hidden roles, recruiter messaging, or resume reviews…', 'senna-finance'),
                'errorText' => __('I can help with hidden roles, recruiter introductions, messaging, and resume analysis—please try again in a moment.', 'senna-finance'),
                'jobsIntroText' => __('Here are hidden or premium roles aligned with that request:', 'senna-finance'),
                'newsLabels' => array(
                    'research' => __('Research Briefs', 'senna-finance'),
                    'deals' => __('Deal Flow', 'senna-finance')
                )
            ));
        }
    }

    public function render_ask_senna_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'floating' => 'true',
            'class' => '',
        ), $atts, 'sffc_ask_senna');

        $floating = !in_array(strtolower((string) $atts['floating']), array('false', '0', 'no'), true);
        $extra_classes = is_array($atts['class']) ? $atts['class'] : preg_split('/\s+/', (string) $atts['class'], -1, PREG_SPLIT_NO_EMPTY);

        $this->enqueue_chat_assets();

        return $this->get_ask_senna_markup(array(
            'floating' => $floating,
            'class' => $extra_classes,
        ));
    }

    public function get_ask_senna_markup($args = array())
    {
        $args = wp_parse_args($args, array(
            'floating' => false,
            'class' => array(),
        ));

        $wrapper_classes = array('sffc-ask-senna-widget');

        if (!empty($args['floating'])) {
            $wrapper_classes[] = 'sffc-ask-senna-widget--floating';
        }

        $extra_classes = is_array($args['class']) ? $args['class'] : preg_split('/\s+/', (string) $args['class'], -1, PREG_SPLIT_NO_EMPTY);

        foreach ($extra_classes as $extra_class) {
            $sanitized = sanitize_html_class($extra_class);
            if (!empty($sanitized)) {
                $wrapper_classes[] = $sanitized;
            }
        }

        $wrapper_classes = array_unique(array_filter($wrapper_classes));

        ob_start();
        ?>
        <div class="<?php echo esc_attr(implode(' ', $wrapper_classes)); ?>" data-ask-senna>
            <button type="button" class="sffc-ask-senna-toggle" data-role="toggle">
                <div class="sffc-ask-senna-toggle-inner">
                    <span class="sffc-ask-senna-toggle-icon">S</span>
                    <div>
                        <p class="sffc-ask-senna-toggle-label"><?php esc_html_e('Ask MENA Careers', 'senna-finance'); ?></p>
                    </div>
                </div>
            </button>
            <div class="sffc-ask-senna-panel" data-role="panel">
                <div class="sffc-ask-senna-header">
                    <div class="sffc-ask-senna-avatar">
                        <img src="<?php echo esc_url(SFFC_PLUGIN_URL . 'senna.jpeg'); ?>" alt="MENA Careers">
                    </div>
                    <div>
                        <p class="text-eyebrow2"><?php esc_html_e('Ask MENA Careers', 'senna-finance'); ?></p>
                    </div>
                    <button type="button" class="sffc-ask-senna-close" aria-label="<?php esc_attr_e('Close chat', 'senna-finance'); ?>" data-role="close">×</button>
                </div>
                <div class="sffc-ask-senna-messages" data-role="messages"></div>
                <div class="sffc-ask-senna-templates" data-role="templates">
                    <button type="button" data-template="Can you show me hidden private equity roles that match my background?" data-fallback="hidden-roles">
                        <?php esc_html_e('Find Hidden Roles', 'senna-finance'); ?>
                    </button>
                    <button type="button" data-template="Introduce me to recruiters actively hiring investment banking associates." data-fallback="recruiter-intros">
                        <?php esc_html_e('Recruiter Introductions', 'senna-finance'); ?>
                    </button>
                    <button type="button" data-template="Draft a recruiter message that explains why I'm a strong fit for this infrastructure private equity role." data-fallback="recruiter-messaging">
                        <?php esc_html_e('Message Recruiters', 'senna-finance'); ?>
                    </button>
                    <button type="button" data-template="Analyze my resume against the attached job description and highlight gaps to fix." data-fallback="resume-analysis">
                        <?php esc_html_e('Analyze My Resume', 'senna-finance'); ?>
                    </button>
                    <button type="button" data-template="Show me which recruiters are responding fastest right now and how to reach them." data-fallback="high-response">
                        <?php esc_html_e('High-Response Recruiters', 'senna-finance'); ?>
                    </button>
                </div>
                <form class="sffc-ask-senna-form" data-role="form">
                    <textarea rows="1" data-role="input" placeholder="<?php esc_attr_e('Ask about hidden roles, recruiter intros, messaging, or resume checks…', 'senna-finance'); ?>" aria-label="<?php esc_attr_e('Message MENA Careers', 'senna-finance'); ?>"></textarea>
                    <button type="submit" class="sffc-ask-senna-send" aria-label="<?php esc_attr_e('Send message', 'senna-finance'); ?>">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4l16 8-16 8v-6l9-2-9-2V4z" fill="currentColor"/></svg>
                    </button>
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render Live Expert Chat shortcode
     */
    public function render_live_expert_chat_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'class' => '',
        ), $atts, 'sffc_live_expert_chat');

        $extra_classes = is_array($atts['class']) ? $atts['class'] : preg_split('/\s+/', (string) $atts['class'], -1, PREG_SPLIT_NO_EMPTY);

        $this->enqueue_chat_assets();
        $this->enqueue_live_expert_assets();

        return $this->get_live_expert_chat_markup(array(
            'class' => $extra_classes,
        ));
    }

    /**
     * Enqueue Live Expert Chat specific assets
     */
    private function enqueue_live_expert_assets()
    {
        // Enqueue CSS
        wp_enqueue_style(
            'sffc-live-expert-chat',
            SFFC_PLUGIN_URL . 'assets/css/live-expert-chat.css',
            array(),
            defined('SFFC_VERSION') ? SFFC_VERSION : filemtime(SFFC_PLUGIN_DIR . 'assets/css/live-expert-chat.css')
        );

        // Enqueue JS
        wp_enqueue_script(
            'sffc-live-expert-chat',
            SFFC_PLUGIN_URL . 'assets/js/live-expert-chat.js',
            array('jquery'),
            defined('SFFC_VERSION') ? SFFC_VERSION : filemtime(SFFC_PLUGIN_DIR . 'assets/js/live-expert-chat.js'),
            true
        );

        // Localize script
        $current_user = wp_get_current_user();
        $user_id = get_current_user_id();
        $memberpress = class_exists('SFFC_MemberPress_Integration') ? SFFC_MemberPress_Integration::get_instance() : null;
        $has_membership = ($user_id && $memberpress) ? $memberpress->has_premium_access($user_id) : false;
        $membership_url = get_option('sffc_registration_url', home_url('/memberships/'));
        wp_localize_script('sffc-live-expert-chat', 'sffcLiveExpertChat', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sffc_public_nonce'),
            'userEmail' => $current_user->user_email ?? '',
            'userName' => $current_user->display_name ?? '',
            'isLoggedIn' => is_user_logged_in(),
            'hasMembership' => (bool) $has_membership,
            'joinUrl' => esc_url($membership_url),
            'membershipUrl' => esc_url($membership_url),
        ));
    }

    /**
     * Get Live Expert Chat markup
     */
    public function get_live_expert_chat_markup($args = array())
    {
        $args = wp_parse_args($args, array(
            'class' => array(),
        ));

        $wrapper_classes = array('sffc-live-expert-widget', 'sffc-live-expert-widget--floating');

        $extra_classes = is_array($args['class']) ? $args['class'] : preg_split('/\s+/', (string) $args['class'], -1, PREG_SPLIT_NO_EMPTY);

        foreach ($extra_classes as $extra_class) {
            $sanitized = sanitize_html_class($extra_class);
            if (!empty($sanitized)) {
                $wrapper_classes[] = $sanitized;
            }
        }

        $wrapper_classes = array_unique(array_filter($wrapper_classes));

        $user_id = get_current_user_id();
        $memberpress = class_exists('SFFC_MemberPress_Integration') ? SFFC_MemberPress_Integration::get_instance() : null;
        $has_membership = ($user_id && $memberpress) ? $memberpress->has_premium_access($user_id) : false;
        $membership_url = get_option('sffc_registration_url', home_url('/memberships/'));
        $plan_entries = !$has_membership ? $this->get_subscription_plans() : array();
        $plans_with_shortcodes = array_filter($plan_entries, function ($plan) {
            return !empty($plan['shortcode']);
        });

        ob_start();
        ?>
        <div class="<?php echo esc_attr(implode(' ', $wrapper_classes)); ?>" data-live-expert-chat data-has-membership="<?php echo $has_membership ? '1' : '0'; ?>">
            <button type="button" class="sffc-live-expert-toggle" data-role="toggle">
                <div class="sffc-live-expert-toggle-inner">
                    <span class="sffc-live-expert-toggle-icon" aria-hidden="true">
                        <img src="<?php echo esc_url(SFFC_PLUGIN_URL . 'senna.jpeg'); ?>" alt="<?php esc_attr_e('Live expert avatar', 'senna-finance'); ?>">
                        <span class="sffc-live-expert-status-dot"></span>
                        <?php if (!$has_membership): ?>
                            <span class="sffc-live-expert-notification-badge" aria-label="<?php esc_attr_e('2 new messages', 'senna-finance'); ?>">2</span>
                        <?php endif; ?>
                    </span>
                    <div class="sffc-live-expert-toggle-copy">
                            <p class="sffc-live-expert-toggle-label"><?php esc_html_e('Live Expert Chat', 'senna-finance'); ?></p>
                            <p class="sffc-live-expert-toggle-meta"><?php esc_html_e('Replies in minutes', 'senna-finance'); ?></p>
                    </div>
                </div>
            </button>
            <div class="sffc-live-expert-panel" data-role="panel">
                <div class="sffc-live-expert-header">
                    <div class="sffc-live-expert-header-main">
                        <div class="sffc-live-expert-avatar">
                            <img src="<?php echo esc_url(SFFC_PLUGIN_URL . 'senna.jpeg'); ?>" alt="<?php esc_attr_e('MENA Careers Expert Team', 'senna-finance'); ?>">
                            <span class="sffc-live-expert-status-dot" aria-hidden="true"></span>
                        </div>
                        <div class="sffc-live-expert-header-copy">
                            <p class="sffc-live-expert-title text-eyebrow2"><?php esc_html_e('MENA Careers Live Experts', 'senna-finance'); ?></p>
                            <p class="sffc-live-expert-subtitle"><?php esc_html_e('private equity mentors for candidates targeting investing and buy-side roles', 'senna-finance'); ?></p>
                            <p class="sffc-live-expert-status">
                                <span class="sffc-live-expert-status-dot" aria-hidden="true"></span>
                                <?php esc_html_e('Online now • Avg. reply under 3 min', 'senna-finance'); ?>
                            </p>
                        </div>
                    </div>
                    <button type="button" class="sffc-live-expert-close" aria-label="<?php esc_attr_e('Close chat', 'senna-finance'); ?>" data-role="close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="sffc-live-expert-messages" data-role="messages">
                    <?php if (!$has_membership): ?>
                        <!-- Default greeting messages for logged out users -->
                        <div class="sffc-live-expert-message expert">
                            <div class="sffc-live-expert-message-bubble">
                                <p class="sffc-live-expert-message-text"><?php esc_html_e('Hey there! 👋', 'senna-finance'); ?></p>
                            </div>
                        </div>
                        <div class="sffc-live-expert-message expert sffc-live-expert-default-question">
                            <div class="sffc-live-expert-message-bubble">
                                <p class="sffc-live-expert-message-text"></p>
                            </div>
                        </div>
                        <div class="sffc-live-expert-message expert sffc-live-expert-queue-status">
                            <div class="sffc-live-expert-message-bubble">
                                <p class="sffc-live-expert-message-text"></p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="sffc-live-expert-greeting">
                            <p><?php esc_html_e('Hi there. Tell us what you need help with across private equity recruiting, interviews, modelling, or portfolio work and we will route you to the right expert.', 'senna-finance'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="sffc-live-expert-templates" data-role="templates">
                    <button type="button" class="sffc-live-expert-template-btn" data-template="I need help preparing for private equity interviews">
                        <?php esc_html_e('Private Equity Interview Prep', 'senna-finance'); ?>
                    </button>
                    <button type="button" class="sffc-live-expert-template-btn" data-template="Can you run a private equity HireVue-style practice session with me?">
                        <?php esc_html_e('HireVue Practice', 'senna-finance'); ?>
                    </button>
                    <button type="button" class="sffc-live-expert-template-btn" data-template="I need help tightening my private equity application materials">
                        <?php esc_html_e('Application Help', 'senna-finance'); ?>
                    </button>
                    <button type="button" class="sffc-live-expert-template-btn" data-template="I need help preparing for a finance case study or technical interview">
                        <?php esc_html_e('Case Study Prep', 'senna-finance'); ?>
                    </button>
                </div>
                <form class="sffc-live-expert-form" data-role="form">
                    <textarea rows="1" data-role="input" placeholder="<?php esc_attr_e('Ask about private equity interviews, applications, case studies, or recruiting…', 'senna-finance'); ?>" aria-label="<?php esc_attr_e('Message', 'senna-finance'); ?>"></textarea>
                    <button type="submit" class="sffc-live-expert-send" aria-label="<?php esc_attr_e('Send message', 'senna-finance'); ?>">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4l16 8-16 8v-6l9-2-9-2V4z" fill="currentColor"/></svg>
                    </button>
                </form>
            </div>
            <?php if (is_user_logged_in() && !$has_membership): ?>
                <div class="sffc-live-expert-modal" data-role="membership-modal" aria-hidden="true">
                    <div class="sffc-live-expert-modal-overlay" data-live-expert-modal-close></div>
                    <div class="sffc-live-expert-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="sffc-live-expert-modal-title">
                        <button type="button" class="sffc-live-expert-modal-close" aria-label="<?php esc_attr_e('Close membership options', 'senna-finance'); ?>" data-live-expert-modal-close>×</button>
                        <div class="sffc-live-expert-modal-header">
                            <p class="sffc-live-expert-modal-eyebrow"><?php esc_html_e('Membership required', 'senna-finance'); ?></p>
                            <h3 id="sffc-live-expert-modal-title"><?php esc_html_e('Join MENA Careers to access private equity expert chat', 'senna-finance'); ?></h3>
                            <p><?php esc_html_e('Live Expert Chat is reserved for active members and focused on interviews, application materials, case studies, and recruiting support for private equity and adjacent buy-side roles.', 'senna-finance'); ?></p>
                        </div>
                        <div class="sffc-live-expert-modal-plans">
                            <?php foreach ($plan_entries as $plan):
                                $plan_slug = sanitize_title($plan['slug'] ?? $plan['name']);
                                $has_shortcode = !empty($plan['shortcode']);
                                $plan_url = !empty($plan['mp_url']) ? $plan['mp_url'] : $membership_url;
                                $features = isset($plan['features']) && is_array($plan['features']) ? $plan['features'] : array();
                            ?>
                                <article class="sffc-live-expert-plan-card" data-plan-card>
                                    <div class="sffc-live-expert-plan-header">
                                        <p class="sffc-live-expert-plan-name"><?php echo esc_html($plan['name']); ?></p>
                                        <?php if (!empty($plan['tagline'])): ?>
                                            <span class="sffc-live-expert-plan-tagline"><?php echo esc_html($plan['tagline']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="sffc-live-expert-plan-price">
                                        <span><?php echo esc_html($plan['price']); ?></span>
                                        <?php if (!empty($plan['billing_cycle'])): ?>
                                            <small><?php echo esc_html($plan['billing_cycle']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($features)): ?>
                                        <ul class="sffc-live-expert-plan-features">
                                            <?php foreach ($features as $feature): ?>
                                                <li><?php echo esc_html($feature); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                    <button type="button"
                                        class="sffc-live-expert-plan-select"
                                        data-plan-slug="<?php echo esc_attr($plan_slug); ?>"
                                        data-plan-url="<?php echo esc_url($plan_url); ?>"
                                        data-plan-shortcode="<?php echo $has_shortcode ? '1' : '0'; ?>">
                                        <?php esc_html_e('Join this plan', 'senna-finance'); ?>
                                    </button>
                                </article>
                            <?php endforeach; ?>
                        </div>
                        <?php if (!empty($plans_with_shortcodes)): ?>
                            <div class="sffc-live-expert-modal-form" data-role="membership-form" hidden>
                                <?php foreach ($plans_with_shortcodes as $plan):
                                    $plan_slug = sanitize_title($plan['slug'] ?? $plan['name']);
                                ?>
                                    <div class="sffc-live-expert-plan-form" data-plan-form="<?php echo esc_attr($plan_slug); ?>" hidden>
                                        <?php echo do_shortcode($plan['shortcode']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <div class="sffc-live-expert-modal-footer">
                            <a href="<?php echo esc_url($membership_url); ?>" target="_blank" rel="noopener" class="sffc-live-expert-modal-link">
                                <?php esc_html_e('View all membership benefits', 'senna-finance'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render shortcode output
     */
    public function render_dashboard($atts)
    {
        $context = $this->get_dashboard_context($atts);

        if (empty($context) || empty($context['stories_feed'])) {
            return '<div class="sffc-news-dashboard-empty">' . esc_html__('We are preparing your private equity news feed. Please check back in a moment.', 'senna-finance') . '</div>';
        }

        return $this->render_dashboard_markup($context);
    }

    /**
     * Render the new Newsroom Terminal layout
     * Premium two-panel design with sophisticated styling
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_newsroom_terminal($atts)
    {
        $atts = shortcode_atts(array(
            'news_count' => 12,
            'deal_count' => 12,
        ), $atts, 'sffc_newsroom_terminal');

        // Try to get cached context first (5 minute cache)
        $cache_key = 'nrt_context_' . md5(serialize($atts));
        $context = get_transient($cache_key);

        if (false === $context) {
            // Get context using existing method
            $context = $this->get_dashboard_context($atts);

            // Cache for 5 minutes (stories don't need real-time updates)
            if (!empty($context)) {
                set_transient($cache_key, $context, 5 * MINUTE_IN_SECONDS);
            }
        }

        if (empty($context) || empty($context['stories_feed'])) {
            return '<div class="nrt-empty">No stories available at this time.</div>';
        }

        // Enqueue terminal-specific assets
        wp_enqueue_style(
            'newsroom-terminal',
            SFFC_PLUGIN_URL . 'assets/css/newsroom-terminal.css',
            array(),
            SFFC_VERSION
        );

        wp_enqueue_script(
            'newsroom-terminal',
            SFFC_PLUGIN_URL . 'assets/js/newsroom-terminal.js',
            array(),
            SFFC_VERSION,
            true
        );

        // Localize script
        wp_localize_script('newsroom-terminal', 'sffc_frontend', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sffc_public_nonce'),
        ));

        // Localize REST API nonce for profile/preferences functionality
        wp_localize_script('newsroom-terminal', 'nrtData', array(
            'nonce' => wp_create_nonce('wp_rest'),
            'restUrl' => rest_url('sffc/v1/'),
            'isLoggedIn' => is_user_logged_in(),
        ));

        // Include and render the template
        if (!function_exists('sffc_render_newsroom_terminal')) {
            require_once SFFC_PLUGIN_DIR . 'templates/newsroom-terminal.php';
        }

        return sffc_render_newsroom_terminal($context);
    }

    public function get_dashboard_context($atts = array(), $overrides = array())
    {
        $atts = shortcode_atts(array(
            'news_count' => 6,
            'deal_count' => 6,
            'alerts' => 4
        ), $atts, 'sffc_pe_newsroom');

        $news_posts = $this->get_latest_posts('sffc_pe_news', (int) $atts['news_count']);
        $deal_posts = $this->get_latest_posts('sffc_pe_deal', (int) $atts['deal_count']);
        $signal_posts = $this->get_latest_posts('sffc_pe_signal', 6); // Get signals for high-ranking content

        $feed_items = array_merge(
            $this->format_feed_items($news_posts, 'news'),
            $this->format_feed_items($deal_posts, 'deal'),
            $this->format_feed_items($signal_posts, 'signal')
        );

        if (empty($feed_items)) {
            return array();
        }

        usort($feed_items, function ($a, $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });

        $post_ids = array_column($feed_items, 'id');
        $stories_feed = array();
        foreach ($feed_items as $item) {
            $keywords = array($item['type']);
            // Add meta-based keywords if available
            if (!empty($item['sector'])) {
                $keywords[] = sanitize_title($item['sector']);
            }
            if (!empty($item['region'])) {
                $keywords[] = sanitize_title($item['region']);
            }
            if (!empty($item['deal_type'])) {
                $keywords[] = sanitize_title($item['deal_type']);
            }
            // Merge content-analyzed keywords (these match filter slugs directly)
            if (!empty($item['content_keywords']) && is_array($item['content_keywords'])) {
                $keywords = array_merge($keywords, $item['content_keywords']);
            }
            $item['keywords'] = array_unique(array_filter($keywords));
            $stories_feed[] = $item;
        }

        $job_posts = $this->get_latest_posts('sffc_job', 8);
        $jobs_feed = $this->format_job_items($job_posts);

        // Get research posts from both sffc_research and insights post types
        $research_posts = get_posts(array(
            'post_type' => array('sffc_research', 'insights'),
            'posts_per_page' => 6,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC'
        ));

        $research_feed = array();
        foreach ($research_posts as $research_post) {
            $research_feed[] = array(
                'id' => $research_post->ID,
                'title' => get_the_title($research_post),
                'link' => get_permalink($research_post),
                'excerpt' => $research_post->post_excerpt ? wp_trim_words($research_post->post_excerpt, 28) : wp_trim_words(wp_strip_all_tags($research_post->post_content), 32),
                'type' => 'research',
                'company' => '',
                'sector' => '',
                'region' => '',
                'deal_value' => '',
                'relative_time' => human_time_diff(get_post_time('U', true, $research_post), current_time('timestamp')),
                'keywords' => array('research'),
                'post_type' => $research_post->post_type // Track original post type
            );
        }

        $insight_filters = array(
            'deal_types' => array(
                array('slug' => 'all', 'label' => __('All Stories', 'senna-finance')),
                array('slug' => 'fund-raises', 'label' => __('Fund Raises', 'senna-finance')),
                array('slug' => 'ma', 'label' => __('M&A', 'senna-finance')),
                array('slug' => 'exits', 'label' => __('Exits', 'senna-finance')),
                array('slug' => 'regulatory', 'label' => __('Regulatory', 'senna-finance')),
                array('slug' => 'personnel', 'label' => __('Personnel', 'senna-finance')),
                array('slug' => 'secondaries', 'label' => __('Secondaries', 'senna-finance')),
                array('slug' => 'distressed', 'label' => __('Distressed', 'senna-finance'))
            ),
            'regions' => array(
                array('slug' => 'north-america', 'label' => __('North America', 'senna-finance')),
                array('slug' => 'europe', 'label' => __('Europe', 'senna-finance')),
                array('slug' => 'asia-pacific', 'label' => __('Asia Pacific', 'senna-finance')),
                array('slug' => 'private-equity', 'label' => __('private equity', 'senna-finance')),
                array('slug' => 'latam', 'label' => __('Latin America', 'senna-finance')),
                array('slug' => 'global', 'label' => __('Global', 'senna-finance'))
            ),
            'sectors' => array(
                array('slug' => 'buyout', 'label' => __('Buyout', 'senna-finance')),
                array('slug' => 'growth-equity', 'label' => __('Growth Equity', 'senna-finance')),
                array('slug' => 'venture-capital', 'label' => __('Venture Capital', 'senna-finance')),
                array('slug' => 'real-estate', 'label' => __('Real Estate', 'senna-finance')),
                array('slug' => 'infrastructure', 'label' => __('Infrastructure', 'senna-finance')),
                array('slug' => 'credit', 'label' => __('Private Credit', 'senna-finance')),
                array('slug' => 'healthcare', 'label' => __('Healthcare', 'senna-finance')),
                array('slug' => 'technology', 'label' => __('Technology', 'senna-finance')),
                array('slug' => 'energy', 'label' => __('Energy', 'senna-finance'))
            )
        );

        $job_filters = array(
            'job_functions' => array(
                array('slug' => 'all', 'label' => __('All Roles', 'senna-finance')),
                array('slug' => 'private-equity', 'label' => __('Private Equity', 'senna-finance')),
                array('slug' => 'investment-banking', 'label' => __('Investment Banking', 'senna-finance')),
                array('slug' => 'asset-management', 'label' => __('Asset Management', 'senna-finance')),
                array('slug' => 'corporate-development', 'label' => __('Corporate Development', 'senna-finance')),
                array('slug' => 'strategy-research', 'label' => __('Strategy & Research', 'senna-finance')),
                array('slug' => 'hedge-fund', 'label' => __('Hedge Fund', 'senna-finance')),
                array('slug' => 'venture-capital', 'label' => __('Venture Capital', 'senna-finance')),
                array('slug' => 'real-estate', 'label' => __('Real Estate', 'senna-finance'))
            ),
            'job_regions' => array(
                array('slug' => 'north-america', 'label' => __('North America', 'senna-finance')),
                array('slug' => 'europe', 'label' => __('Europe', 'senna-finance')),
                array('slug' => 'asia-pacific', 'label' => __('Asia Pacific', 'senna-finance')),
                array('slug' => 'private-equity', 'label' => __('private equity', 'senna-finance')),
                array('slug' => 'remote', 'label' => __('Remote', 'senna-finance'))
            ),
            'job_levels' => array(
                array('slug' => 'analyst', 'label' => __('Analyst', 'senna-finance')),
                array('slug' => 'associate', 'label' => __('Associate', 'senna-finance')),
                array('slug' => 'vice-president', 'label' => __('Vice President', 'senna-finance')),
                array('slug' => 'director', 'label' => __('Director', 'senna-finance')),
                array('slug' => 'partner', 'label' => __('Partner / MD', 'senna-finance'))
            )
        );

        $filter_sets = array(
            'insights' => $insight_filters,
            'jobs' => $job_filters
        );

        $trending_posts = get_posts(array(
            'post_type' => array('sffc_pe_news', 'sffc_pe_deal', 'sffc_pe_signal'),
            'post_status' => 'publish',
            'posts_per_page' => 5,
            'meta_key' => 'sffc_visit_count',
            'orderby' => 'meta_value_num',
            'order' => 'DESC'
        ));

        if (empty($trending_posts)) {
            $trending_posts = array_slice($stories_feed, 0, 5);
        }

        $trending_jobs_feed = array();
        $trending_jobs = get_posts(array(
            'post_type' => 'sffc_job',
            'post_status' => 'publish',
            'posts_per_page' => 5,
            'meta_key' => 'sffc_job_interest',
            'orderby' => 'meta_value_num',
            'order' => 'DESC'
        ));

        if (!empty($trending_jobs)) {
            $trending_jobs_feed = $this->format_job_items($trending_jobs);
        }

        if (empty($trending_jobs_feed)) {
            $trending_jobs_feed = array_slice($jobs_feed, 0, 5);
        }

        $messages_feed = array();
        $messaging_portal_url = apply_filters('sffc_messaging_portal_url', site_url('/messaging/?view=messages'));
        if (is_user_logged_in() && class_exists('SkillFarm_Messaging_Messages')) {
            $messages_api = SkillFarm_Messaging_Messages::get_instance();
            $raw_messages = $messages_api->get_messages(get_current_user_id(), array(
                'limit' => 6,
                'order' => 'DESC'
            ));

            foreach ($raw_messages as $message) {
                $timestamp = !empty($message->created_at) ? strtotime($message->created_at) : current_time('timestamp');
                $messages_feed[] = array(
                    'id' => 'message-' . $message->id,
                    'title' => $message->subject ?: __('New Message', 'senna-finance'),
                    'excerpt' => wp_trim_words(wp_strip_all_tags($message->content), 32),
                    'type' => 'message',
                    'sender' => $message->sender_name ?: __('Confidential', 'senna-finance'),
                    'recipient' => $message->recipient_name ?: '',
                    'message_category' => $message->category ?: 'all',
                    'status' => $message->status ?: 'unread',
                    'link' => $messaging_portal_url ? add_query_arg('message', $message->id, $messaging_portal_url) : '#',
                    'relative_time' => human_time_diff($timestamp, current_time('timestamp')),
                    'keywords' => array('message', sanitize_title($message->category ?: 'all'))
                );
            }
        } else {
            // Create demo messages for non-logged-in users
            $messages_feed = $this->build_demo_messages();
        }

        $analytics = $this->build_template_insights($stories_feed);
        $subscription_plans = $this->get_subscription_plans();
        $user = wp_get_current_user();
        $user_name = $user && $user->exists() ? $user->display_name : __('Guest Analyst', 'senna-finance');
        $nonce = wp_create_nonce('sffc_dashboard_nonce');
        $current_user_id = get_current_user_id();
        $saved_post_ids = $current_user_id ? $this->get_user_saved_post_ids($current_user_id) : array();
        $saved_feed_items = !empty($saved_post_ids) ? $this->build_saved_feed_items($saved_post_ids) : array();

        $context = array(
            'stories_feed' => $stories_feed,
            'jobs_feed' => $jobs_feed,
            'research_feed' => $research_feed,
            'messages_feed' => $messages_feed,
            'trending_posts' => $trending_posts,
            'trending_jobs_feed' => $trending_jobs_feed,
            'filter_sets' => $filter_sets,
            'analytics' => $analytics,
            'subscription_plans' => $subscription_plans,
            'user_name' => $user_name,
            'messaging_portal_url' => $messaging_portal_url,
            'saved_post_ids' => $saved_post_ids,
            'saved_feed_items' => $saved_feed_items,
            'post_ids' => $post_ids,
            'nonce' => $nonce,
        );

        if (!empty($overrides)) {
            foreach ($overrides as $key => $value) {
                $context[$key] = $value;
            }
        }

        return $context;
    }

    public function render_dashboard_markup($context)
    {
        if (empty($context) || empty($context['stories_feed'])) {
            return '';
        }

        extract($context);

        ob_start();
        include SFFC_PLUGIN_DIR . 'templates/pe-news-dashboard.php';
        $output = ob_get_clean();
        if (function_exists('shortcode_unautop')) {
            $output = shortcode_unautop($output);
        }
        return trim($output);
    }

    /**
     * AJAX: Fetch Claude analytics for current feed
     */
    public function ajax_fetch_analytics()
    {
        check_ajax_referer('sffc_dashboard_nonce', 'nonce');

        $post_ids = array();
        if (!empty($_POST['post_ids'])) {
            if (is_array($_POST['post_ids'])) {
                $post_ids = array_map('absint', $_POST['post_ids']);
            } else {
                $post_ids = array_map('absint', array_filter(array_map('trim', explode(',', sanitize_text_field(wp_unslash($_POST['post_ids']))))));
            }
        }

        if (empty($post_ids)) {
            $post_ids = wp_list_pluck($this->get_latest_posts(array('sffc_pe_news', 'sffc_pe_deal'), 8), 'ID');
        }

        $posts = $this->get_posts_by_ids($post_ids);
        $feed_items = $this->format_feed_items($posts);

        if (empty($feed_items)) {
            wp_send_json_error(array('message' => __('No recent activity detected.', 'senna-finance')));
        }

        usort($feed_items, function ($a, $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });

        $analysis = $this->generate_analytics_payload($feed_items);

        if (empty($analysis)) {
            wp_send_json_error(array('message' => __('Unable to create analytics.', 'senna-finance')));
        }

        wp_send_json_success($analysis);
    }

    /**
     * AJAX handler for loading story content in newsroom terminal
     */
    public function ajax_nrt_load_story()
    {
        $story_id = isset($_POST['story_id']) ? absint($_POST['story_id']) : 0;

        if (!$story_id) {
            wp_send_json_error(array('message' => 'Invalid story ID'));
        }

        $post = get_post($story_id);
        if (!$post) {
            wp_send_json_error(array('message' => 'Story not found'));
        }

        // Only allow published posts for non-authenticated users
        if ($post->post_status !== 'publish' && !current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Access denied'));
        }

        // Load the template if not already loaded
        if (!function_exists('sffc_render_story_content')) {
            $template_path = SFFC_PLUGIN_DIR . 'templates/newsroom-terminal.php';
            if (file_exists($template_path)) {
                require_once $template_path;
            }
        }

        if (!function_exists('sffc_render_story_content')) {
            wp_send_json_error(array('message' => 'Template not available'));
        }

        // Build story array from post
        $story = array(
            'id' => $post->ID,
            'title' => get_the_title($post),
            'link' => get_permalink($post),
            'excerpt' => $post->post_excerpt ?: wp_trim_words(wp_strip_all_tags($post->post_content), 32),
            'type' => str_replace('sffc_pe_', '', $post->post_type),
            'company' => get_post_meta($post->ID, 'news_company', true) ?: get_post_meta($post->ID, '_companies_involved', true),
            'sector' => get_post_meta($post->ID, 'news_sector', true) ?: get_post_meta($post->ID, '_sector', true),
            'region' => get_post_meta($post->ID, 'news_region', true) ?: get_post_meta($post->ID, '_region', true),
            'deal_type' => get_post_meta($post->ID, 'news_deal_type', true) ?: get_post_meta($post->ID, 'deal_category', true),
            'deal_value' => get_post_meta($post->ID, '_deal_value', true) ?: get_post_meta($post->ID, 'deal_value', true),
            'relative_time' => human_time_diff(get_post_time('U', true, $post), current_time('timestamp')),
        );

        $html = sffc_render_story_content($story);

        wp_send_json_success(array('html' => $html));
    }

    /**
     * AJAX handler for loading job content in newsroom terminal
     */
    public function ajax_nrt_load_job()
    {
        $job_id = isset($_POST['job_id']) ? absint($_POST['job_id']) : 0;

        if (!$job_id) {
            wp_send_json_error(array('message' => 'Invalid job ID'));
        }

        $post = get_post($job_id);
        if (!$post) {
            wp_send_json_error(array('message' => 'Job not found'));
        }

        // Only allow published posts for non-authenticated users
        if ($post->post_status !== 'publish' && !current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Access denied'));
        }

        // Load the template if not already loaded
        if (!function_exists('sffc_render_job_content')) {
            $template_path = SFFC_PLUGIN_DIR . 'templates/newsroom-terminal.php';
            if (file_exists($template_path)) {
                require_once $template_path;
            }
        }

        if (!function_exists('sffc_render_job_content')) {
            wp_send_json_error(array('message' => 'Template not available'));
        }

        // Get job meta
        $meta = array();
        if (class_exists('SFFC_Job_Insight_Helper')) {
            $meta = SFFC_Job_Insight_Helper::get_job_meta($job_id);
        }

        $city = $meta['sffc_location_city'] ?? '';
        $country = $meta['sffc_location_country'] ?? '';
        $location = trim($city . ', ' . $country, ' ,');
        if (empty($location)) {
            $location = $meta['sffc_location'] ?? '';
        }

        // Build job array from post
        $job = array(
            'id' => $post->ID,
            'title' => get_the_title($post),
            'link' => get_permalink($post),
            'excerpt' => $post->post_excerpt ?: wp_trim_words(wp_strip_all_tags($post->post_content), 32),
            'company' => $meta['sffc_actual_company'] ?? $meta['sffc_company_name'] ?? '',
            'location' => $location,
            'salary' => $meta['sffc_salary_display'] ?? $meta['sffc_estimated_salary'] ?? '',
            'job_family' => $meta['sffc_job_family'] ?? '',
            'job_level' => $meta['sffc_job_level'] ?? '',
            'region' => $this->map_job_region_slug($country) ?: $this->detect_region_from_location($location),
            'timestamp' => get_post_time('U', true, $post),
            'relative_time' => human_time_diff(get_post_time('U', true, $post), current_time('timestamp')),
        );

        $html = sffc_render_job_content($job);

        wp_send_json_success(array('html' => $html));
    }

    /**
     * AJAX handler for loading more stories in newsroom terminal
     */
    public function ajax_nrt_load_more_stories()
    {
        $offset = isset($_POST['offset']) ? absint($_POST['offset']) : 0;
        $limit = isset($_POST['limit']) ? absint($_POST['limit']) : 12;
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'all';

        // Cap the limit to prevent abuse
        $limit = min($limit, 24);

        // Build query based on type
        $post_types = array('sffc_pe_news', 'sffc_pe_deal');
        if ($type === 'news') {
            $post_types = array('sffc_pe_news');
        } elseif ($type === 'deal') {
            $post_types = array('sffc_pe_deal');
        }

        $args = array(
            'post_type' => $post_types,
            'posts_per_page' => $limit,
            'offset' => $offset,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => false, // We need total count
            'update_post_meta_cache' => true,
            'update_post_term_cache' => false,
        );

        $query = new WP_Query($args);
        $posts = $query->posts;
        $total = $query->found_posts;
        $has_more = ($offset + $limit) < $total;

        if (empty($posts)) {
            wp_send_json_success(array(
                'stories' => array(),
                'has_more' => false,
                'total' => $total
            ));
        }

        // Format the posts
        $stories = $this->format_feed_items($posts, '', true); // Skip content analysis for speed

        wp_send_json_success(array(
            'stories' => $stories,
            'has_more' => $has_more,
            'total' => $total,
            'loaded' => count($stories)
        ));
    }

    /**
     * AJAX handler to get related jobs and news for a PE firm
     */
    public function ajax_nrt_get_firm_content()
    {
        $firm_name = isset($_POST['firm_name']) ? sanitize_text_field(wp_unslash($_POST['firm_name'])) : '';

        if (empty($firm_name)) {
            wp_send_json_error(array('message' => 'Firm name required'));
        }

        $response = array(
            'jobs' => array(),
            'news' => array(),
            'deals' => array()
        );

        // Search for related jobs
        // Jobs use multiple company meta fields: sffc_actual_company, sffc_company_name, sffc_source_name
        $job_args = array(
            'post_type' => 'sffc_job',
            'post_status' => 'publish',
            'posts_per_page' => 5,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => 'sffc_actual_company',
                    'value' => $firm_name,
                    'compare' => 'LIKE'
                ),
                array(
                    'key' => 'sffc_company_name',
                    'value' => $firm_name,
                    'compare' => 'LIKE'
                ),
                array(
                    'key' => 'sffc_source_name',
                    'value' => $firm_name,
                    'compare' => 'LIKE'
                )
            )
        );

        $job_query = new WP_Query($job_args);
        if ($job_query->have_posts()) {
            foreach ($job_query->posts as $job) {
                $location = get_post_meta($job->ID, 'sffc_location_city', true);
                if (!$location) {
                    $location = get_post_meta($job->ID, 'sffc_location', true);
                }
                $company = get_post_meta($job->ID, 'sffc_actual_company', true) ?: get_post_meta($job->ID, 'sffc_company_name', true);
                $response['jobs'][] = array(
                    'id' => $job->ID,
                    'title' => get_the_title($job),
                    'link' => get_permalink($job),
                    'company' => $company,
                    'location' => $location,
                    'date' => human_time_diff(get_post_time('U', true, $job), current_time('timestamp')) . ' ago'
                );
            }
        }

        // Search for related news using meta fields and title search
        // News uses: news_company, _companies_involved, _sffc_news_company
        $news_args = array(
            'post_type' => array('sffc_pe_news', 'sffc_news_article'),
            'post_status' => 'publish',
            'posts_per_page' => 5,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => 'news_company',
                    'value' => $firm_name,
                    'compare' => 'LIKE'
                ),
                array(
                    'key' => '_companies_involved',
                    'value' => $firm_name,
                    'compare' => 'LIKE'
                ),
                array(
                    'key' => '_sffc_news_company',
                    'value' => $firm_name,
                    'compare' => 'LIKE'
                )
            )
        );

        $news_query = new WP_Query($news_args);

        // If no results from meta query, try title/content search
        if (!$news_query->have_posts()) {
            $news_args = array(
                'post_type' => array('sffc_pe_news', 'sffc_news_article'),
                'post_status' => 'publish',
                'posts_per_page' => 5,
                'orderby' => 'date',
                'order' => 'DESC',
                's' => $firm_name,
            );
            $news_query = new WP_Query($news_args);
        }

        if ($news_query->have_posts()) {
            foreach ($news_query->posts as $news) {
                $company = get_post_meta($news->ID, 'news_company', true) ?: get_post_meta($news->ID, '_companies_involved', true);
                $response['news'][] = array(
                    'id' => $news->ID,
                    'title' => get_the_title($news),
                    'link' => get_permalink($news),
                    'company' => $company,
                    'excerpt' => wp_trim_words(wp_strip_all_tags($news->post_content), 20),
                    'date' => human_time_diff(get_post_time('U', true, $news), current_time('timestamp')) . ' ago'
                );
            }
        }

        // Search for related deals using meta fields
        // Deals use: _companies_involved, deal_company, _acquirer, _target
        $deal_args = array(
            'post_type' => 'sffc_pe_deal',
            'post_status' => 'publish',
            'posts_per_page' => 5,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_companies_involved',
                    'value' => $firm_name,
                    'compare' => 'LIKE'
                ),
                array(
                    'key' => 'deal_company',
                    'value' => $firm_name,
                    'compare' => 'LIKE'
                ),
                array(
                    'key' => '_acquirer',
                    'value' => $firm_name,
                    'compare' => 'LIKE'
                ),
                array(
                    'key' => '_target',
                    'value' => $firm_name,
                    'compare' => 'LIKE'
                ),
                array(
                    'key' => '_deal_company',
                    'value' => $firm_name,
                    'compare' => 'LIKE'
                )
            )
        );

        $deal_query = new WP_Query($deal_args);

        // If no results from meta query, try title/content search
        if (!$deal_query->have_posts()) {
            $deal_args = array(
                'post_type' => 'sffc_pe_deal',
                'post_status' => 'publish',
                'posts_per_page' => 5,
                'orderby' => 'date',
                'order' => 'DESC',
                's' => $firm_name,
            );
            $deal_query = new WP_Query($deal_args);
        }

        if ($deal_query->have_posts()) {
            foreach ($deal_query->posts as $deal) {
                $deal_value = get_post_meta($deal->ID, 'deal_value', true) ?: get_post_meta($deal->ID, '_deal_value', true);
                $deal_type = get_post_meta($deal->ID, '_deal_type', true);
                $companies = get_post_meta($deal->ID, '_companies_involved', true);
                $acquirer = get_post_meta($deal->ID, '_acquirer', true);
                $target = get_post_meta($deal->ID, '_target', true);

                $response['deals'][] = array(
                    'id' => $deal->ID,
                    'title' => get_the_title($deal),
                    'link' => get_permalink($deal),
                    'value' => $deal_value,
                    'type' => $deal_type,
                    'acquirer' => $acquirer,
                    'target' => $target,
                    'companies' => $companies,
                    'date' => human_time_diff(get_post_time('U', true, $deal), current_time('timestamp')) . ' ago'
                );
            }
        }

        // Calculate totals
        $response['totals'] = array(
            'jobs' => count($response['jobs']),
            'news' => count($response['news']),
            'deals' => count($response['deals'])
        );

        wp_send_json_success($response);
    }

    public function ajax_quick_assist()
    {
        check_ajax_referer('sffc_public_nonce', 'nonce');

        $query = isset($_POST['query']) ? trim(wp_unslash($_POST['query'])) : '';
        $intent = isset($_POST['intent']) ? sanitize_text_field(wp_unslash($_POST['intent'])) : '';

        if ('' === $query) {
            wp_send_json_error(array('message' => __('Please provide a prompt.', 'senna-finance')));
        }

        if ($this->is_job_intent($query, $intent)) {
            $filters = $this->extract_job_filters($query);
            $jobs = $this->get_job_recommendations($filters);

            if (!empty($jobs)) {
                $label_parts = array();
                if (!empty($filters['level_label'])) {
                    $label_parts[] = $filters['level_label'];
                }
                if (!empty($filters['location'])) {
                    $label_parts[] = ucwords($filters['location']);
                }
                $audience = !empty($label_parts) ? implode(' · ', $label_parts) : __('your profile', 'senna-finance');
                $message = sprintf(
                    __('Here are %1$d live roles that match %2$s. Let me know if you want me to refine further.', 'senna-finance'),
                    count($jobs),
                    esc_html($audience)
                );

                wp_send_json_success(array(
                    'handled' => true,
                    'type' => 'jobs',
                    'message' => $message,
                    'jobs' => $jobs
                ));
            }
        }

        if ($this->is_news_intent($query, $intent)) {
            $digest = $this->collect_latest_news();
            if (!empty($digest['pe_news']) || !empty($digest['deals'])) {
                wp_send_json_success(array(
                    'handled' => true,
                    'type' => 'news',
                    'message' => __('Here is the latest research flow and deal tape.', 'senna-finance'),
                    'news' => $digest
                ));
            }
        }

        if ($this->is_glossary_intent($query, $intent)) {
            $term = $this->sanitize_glossary_term($query);
            if (!empty($term)) {
                $definition = $this->lookup_glossary_entry($term);
                if (!empty($definition)) {
                    wp_send_json_success(array(
                        'handled' => true,
                        'type' => 'glossary',
                        'message' => $definition
                    ));
                }
            }
        }

        wp_send_json_success(array('handled' => false));
    }

    public function ajax_toggle_saved_item()
    {
        check_ajax_referer('sffc_dashboard_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array(
                'message' => __('Please sign in to save stories.', 'senna-finance'),
                'requires_login' => true
            ), 403);
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        if (!$post_id) {
            wp_send_json_error(array('message' => __('Invalid item.', 'senna-finance')));
        }

        $save_raw = isset($_POST['save']) ? wp_unslash($_POST['save']) : '1';
        $save_value = strtolower(sanitize_text_field($save_raw));
        $should_save = !in_array($save_value, array('0', 'false', 'no'), true);

        $post = get_post($post_id);
        if (!$post || 'publish' !== $post->post_status || !in_array($post->post_type, array('sffc_pe_news', 'sffc_pe_deal', 'sffc_pe_signal', 'sffc_job'), true)) {
            wp_send_json_error(array('message' => __('This item cannot be saved.', 'senna-finance')));
        }

        $saved_ids = $this->get_user_saved_post_ids($user_id);

        if ($should_save) {
            if (!in_array($post_id, $saved_ids, true)) {
                $saved_ids[] = $post_id;
            }
        } else {
            $saved_ids = array_values(array_diff($saved_ids, array($post_id)));
        }

        $this->set_user_saved_post_ids($user_id, $saved_ids);

        wp_send_json_success(array(
            'saved' => $should_save,
            'saved_ids' => $saved_ids
        ));
    }

    private function is_job_intent($query, $intent)
    {
        if ('jobs' === $intent) {
            return true;
        }

        $normalized = strtolower($query);
        $keywords = array('job', 'jobs', 'role', 'roles', 'position', 'positions', 'hiring', 'opportunity', 'openings', 'vacancy');
        foreach ($keywords as $keyword) {
            if (false !== strpos($normalized, $keyword)) {
                return true;
            }
        }

        $level_keywords = array('intern', 'analyst', 'associate', 'vp', 'vice president', 'director', 'senior', 'partner', 'md');
        foreach ($level_keywords as $level_keyword) {
            if (false !== strpos($normalized, $level_keyword)) {
                return true;
            }
        }

        return false;
    }

    private function extract_job_filters($query)
    {
        $filters = array(
            'location' => '',
            'level' => '',
            'level_label' => ''
        );

        $normalized = strtolower($query);
        $levels = array(
            'intern' => __('Early career roles', 'senna-finance'),
            'analyst' => __('Analyst roles', 'senna-finance'),
            'associate' => __('Associate seats', 'senna-finance'),
            'vice-president' => __('VP positions', 'senna-finance'),
            'director' => __('Director opportunities', 'senna-finance'),
            'partner' => __('Senior/Partner roles', 'senna-finance')
        );

        foreach ($levels as $slug => $label) {
            $needle = str_replace('-', ' ', $slug);
            if (false !== strpos($normalized, $needle) || false !== strpos($normalized, $slug)) {
                $filters['level'] = $slug;
                $filters['level_label'] = $label;
                break;
            }
        }

        if (empty($filters['level'])) {
            if (false !== strpos($normalized, 'senior')) {
                $filters['level'] = 'director';
                $filters['level_label'] = __('Senior roles', 'senna-finance');
            } elseif (false !== strpos($normalized, 'vp')) {
                $filters['level'] = 'vice-president';
                $filters['level_label'] = __('VP roles', 'senna-finance');
            }
        }

        if (preg_match('/\b(?:in|within|across)\s+([a-z\s]+)/i', $query, $matches)) {
            $filters['location'] = sanitize_text_field(trim($matches[1]));
            if (!empty($filters['location'])) {
                $filters['location'] = preg_replace('/\b(for|at|as|to|within)\b.*$/i', '', $filters['location']);
                $filters['location'] = trim($filters['location'], " ,.");
            }
        }

        return $filters;
    }

    private function get_job_recommendations($filters, $limit = 5)
    {
        $args = array(
            'post_type' => 'sffc_job',
            'post_status' => 'publish',
            'posts_per_page' => 20,
            'orderby' => 'date',
            'order' => 'DESC'
        );

        if (!empty($filters['level'])) {
            $args['meta_query'] = array(
                array(
                    'key' => 'sffc_job_level',
                    'value' => $filters['level'],
                    'compare' => 'LIKE'
                )
            );
        }

        $jobs = array();
        $posts = get_posts($args);
        $location_filter = strtolower($filters['location']);

        foreach ($posts as $post) {
            $company = get_post_meta($post->ID, 'sffc_actual_company', true) ?: get_post_meta($post->ID, 'sffc_company_name', true);
            $city = get_post_meta($post->ID, 'sffc_location_city', true);
            $country = get_post_meta($post->ID, 'sffc_location_country', true);
            $combined_location = trim($city . (empty($city) || empty($country) ? '' : ', ') . $country);
            if (empty($combined_location)) {
                $combined_location = get_post_meta($post->ID, 'sffc_location', true);
            }

            if (!empty($location_filter)) {
                $haystack = strtolower($combined_location . ' ' . $city . ' ' . $country);
                if (false === strpos($haystack, $location_filter)) {
                    continue;
                }
            }

            $jobs[] = array(
                'title' => get_the_title($post),
                'link' => get_permalink($post),
                'company' => $company,
                'location' => $combined_location ?: __('Global', 'senna-finance')
            );

            if (count($jobs) >= $limit) {
                break;
            }
        }

        return $jobs;
    }

    private function is_news_intent($query, $intent)
    {
        if ('news' === $intent) {
            return true;
        }

        $normalized = strtolower($query);
        $keywords = array('news', 'headline', 'update', 'brief', 'breaking', 'deal');
        foreach ($keywords as $keyword) {
            if (false !== strpos($normalized, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function collect_latest_news()
    {
        $payload = array(
            'pe_news' => array(),
            'deals' => array()
        );

        $news_posts = get_posts(array(
            'post_type' => 'sffc_pe_news',
            'post_status' => 'publish',
            'posts_per_page' => 5,
            'orderby' => 'date',
            'order' => 'DESC'
        ));

        $deal_posts = get_posts(array(
            'post_type' => 'sffc_pe_deal',
            'post_status' => 'publish',
            'posts_per_page' => 5,
            'orderby' => 'date',
            'order' => 'DESC'
        ));

        foreach ($news_posts as $item) {
            $payload['pe_news'][] = array(
                'title' => get_the_title($item),
                'link' => get_permalink($item),
                'timestamp' => human_time_diff(get_post_time('U', true, $item), current_time('timestamp')) . ' ' . __('ago', 'senna-finance'),
                'source' => __('Research', 'senna-finance')
            );
        }

        foreach ($deal_posts as $item) {
            $payload['deals'][] = array(
                'title' => get_the_title($item),
                'link' => get_permalink($item),
                'timestamp' => human_time_diff(get_post_time('U', true, $item), current_time('timestamp')) . ' ' . __('ago', 'senna-finance'),
                'source' => __('Deal flow', 'senna-finance')
            );
        }

        return $payload;
    }

    private function is_glossary_intent($query, $intent)
    {
        if ('glossary' === $intent) {
            return true;
        }

        $normalized = strtolower($query);
        $keywords = array('what is', 'meaning', 'define', 'definition', 'who is', 'explain');
        foreach ($keywords as $keyword) {
            if (false !== strpos($normalized, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function sanitize_glossary_term($query)
    {
        $term = preg_replace('/\b(what\s+is|meaning\s+of|define|definition\s+of|who\s+is|explain)\b/i', '', $query);
        $term = trim($term, " ?!.");
        return sanitize_text_field($term);
    }

    private function lookup_glossary_entry($term)
    {
        if (empty($term)) {
            return '';
        }

        $posts = get_posts(array(
            'post_type' => 'glossary',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            's' => $term
        ));

        if (empty($posts)) {
            return '';
        }

        $entry = reset($posts);
        $content = $entry->post_excerpt ? $entry->post_excerpt : wp_strip_all_tags($entry->post_content);
        return wp_trim_words($content, 80, '…');
    }

    /**
     * Query helper for latest posts
     *
     * @param string|array $post_type
     * @param int          $limit
     *
     * @return WP_Post[]
     */
    private function get_latest_posts($post_type, $limit, $region = 'global')
    {
        if ($region === 'private-equity') {
            $private_equity_keywords = $this->get_private_equity_keywords();
            $filtered_posts = $this->get_posts_by_keywords($post_type, $limit * 3, $private_equity_keywords);

            if (!empty($filtered_posts)) {
                return array_slice($filtered_posts, 0, $limit);
            }
        }

        $args = array(
            'post_type' => $post_type,
            'posts_per_page' => $limit,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
            'ignore_sticky_posts' => true,
            'update_post_meta_cache' => true,
            'update_post_term_cache' => false,
            'cache_results' => true,
        );

        return get_posts($args);
    }

    /**
     * Get comprehensive list of private equity keywords for filtering
     */
    private function get_private_equity_keywords() {
        return array(
            // ==========================================
            // COUNTRIES & DEMONYMS
            // ==========================================
            'UAE', 'U.A.E.', 'United Arab Emirates', 'Emirati', 'Emiratis',
            'Saudi Arabia', 'Saudi', 'Saudis', 'KSA', 'Kingdom of Saudi Arabia',
            'Qatar', 'Qatari', 'Qataris', 'State of Qatar',
            'Bahrain', 'Bahraini', 'Bahrainis', 'Kingdom of Bahrain',
            'Kuwait', 'Kuwaiti', 'Kuwaitis', 'State of Kuwait',
            'Oman', 'Omani', 'Omanis', 'Sultanate of Oman',
            'Egypt', 'Egyptian', 'Egyptians', 'Arab Republic of Egypt',
            'Jordan', 'Jordanian', 'Jordanians', 'Hashemite Kingdom',
            'Lebanon', 'Lebanese', 'Beiruti',
            'Iraq', 'Iraqi', 'Iraqis',
            'Iran', 'Iranian', 'Iranians', 'Persian',
            'Israel', 'Israeli', 'Israelis',
            'Palestine', 'Palestinian', 'Palestinians', 'Gaza', 'West Bank',
            'Syria', 'Syrian', 'Syrians',
            'Yemen', 'Yemeni', 'Yemenis',
            'Libya', 'Libyan', 'Libyans',
            'Tunisia', 'Tunisian', 'Tunisians',
            'Morocco', 'Moroccan', 'Moroccans',
            'Algeria', 'Algerian', 'Algerians',
            'Sudan', 'Sudanese',
            'Turkey', 'Turkish', 'Türkiye',
            'Cyprus', 'Cypriot',
            'Pakistan', 'Pakistani', // Major ME investment corridor

            // ==========================================
            // CITIES & REGIONS
            // ==========================================
            // UAE Cities
            'Dubai', 'Abu Dhabi', 'Sharjah', 'Ajman', 'Fujairah',
            'Ras Al Khaimah', 'RAK', 'Umm Al Quwain', 'Al Ain',
            'Dubai Marina', 'Downtown Dubai', 'DIFC', 'Business Bay',
            'Dubai Silicon Oasis', 'Dubai South', 'Expo City',
            'Saadiyat', 'Yas Island', 'Masdar City',

            // Saudi Cities & Projects
            'Riyadh', 'Jeddah', 'Dammam', 'Khobar', 'Dhahran',
            'Mecca', 'Makkah', 'Medina', 'Madinah', 'Tabuk', 'Abha',
            'Jubail', 'Yanbu', 'NEOM', 'The Line', 'Oxagon', 'Trojena',
            'KAEC', 'King Abdullah Economic City', 'Qiddiya', 'Diriyah',
            'Red Sea Project', 'Amaala', 'AlUla',

            // Other GCC Cities
            'Doha', 'Lusail', 'West Bay', 'Pearl Qatar', 'Education City',
            'Manama', 'Seef', 'Bahrain Bay', 'Riffa',
            'Kuwait City', 'Salmiya', 'Hawalli', 'Jahra',
            'Muscat', 'Salalah', 'Sohar', 'Duqm',

            // Other private equity Cities
            'Cairo', 'Alexandria', 'Giza', 'New Cairo', 'New Alamein',
            'Amman', 'Aqaba', 'Irbid',
            'Beirut', 'Tripoli Lebanon',
            'Tel Aviv', 'Jerusalem', 'Haifa', 'Herzliya',
            'Baghdad', 'Erbil', 'Basra', 'Sulaymaniyah',
            'Tehran', 'Isfahan', 'Shiraz', 'Mashhad', 'Tabriz',
            'Casablanca', 'Rabat', 'Marrakech', 'Tangier',
            'Algiers', 'Oran',
            'Tunis', 'Sfax',
            'Tripoli Libya', 'Benghazi',
            'Khartoum',
            'Istanbul', 'Ankara', 'Izmir',

            // ==========================================
            // REGIONAL TERMS & GROUPINGS
            // ==========================================
            'private equity', 'private equity', 'private equity',
            'private equity', 'private equity', 'private equity',
            'GCC', 'G.C.C.', 'Gulf Cooperation Council',
            'Gulf', 'Gulf States', 'Gulf Region', 'Arabian Gulf', 'Persian Gulf',
            'Arabian', 'Arabian Peninsula', 'Arab World', 'Arab States',
            'Levant', 'Levantine', 'Mashreq', 'Mashriq',
            'North Africa', 'North African', 'Maghreb', 'Maghrebi',
            'OPEC', 'OAPEC', 'Arab League',

            // ==========================================
            // SOVEREIGN WEALTH FUNDS & GOVERNMENT INVESTMENT
            // ==========================================
            // UAE SWFs
            'ADIA', 'Abu Dhabi Investment Authority',
            'Mubadala', 'Mubadala Investment Company', 'Mubadala Capital',
            'ADIC', 'Abu Dhabi Investment Council',
            'ADQ', 'Abu Dhabi Developmental Holding',
            'EIA', 'Emirates Investment Authority',
            'Investment Corporation of Dubai', 'ICD',
            'Dubai Holding', 'Dubai World', 'Dubai Investment Group',
            'Nakheel', 'DP World', 'Ports Dubai',
            'Istithmar', 'Dubai International Capital', 'DIC',

            // Saudi SWFs & Government Investors
            'PIF', 'Public Investment Fund', 'Saudi PIF',
            'SAMA', 'Saudi Arabian Monetary Authority', 'Saudi Central Bank',
            'Sanabil Investments', 'Sanabil',
            'Hassana Investment Company', 'Hassana',
            'Saudi Aramco', 'Aramco', 'Aramco Ventures',
            'GOSI', 'General Organization for Social Insurance',
            'Saudi National Development Fund',

            // Qatar SWFs
            'QIA', 'Qatar Investment Authority',
            'Qatar Holding', 'Qatari Diar', 'Qatar Sports Investments', 'QSI',
            'Nebras Power', 'Hassad Food',

            // Kuwait SWFs
            'KIA', 'Kuwait Investment Authority',
            'Kuwait Investment Office', 'KIO',
            'Kuwait Finance House', 'KFH',

            // Other SWFs
            'Mumtalakat', 'Bahrain Mumtalakat',
            'OIA', 'Oman Investment Authority', 'State General Reserve Fund',
            'Oman Investment Fund', 'OIF',
            'ADIC Oman', 'Omantel',
            'EGX', 'Egyptian Sovereign Fund', 'Sovereign Fund of Egypt',
            'Ithmar Capital', 'Morocco SWF',

            // ==========================================
            // BANKS & FINANCIAL INSTITUTIONS
            // ==========================================
            // UAE Banks
            'First Abu Dhabi Bank', 'FAB', 'NBAD',
            'Emirates NBD', 'ENBD', 'Emirates Bank',
            'ADCB', 'Abu Dhabi Commercial Bank',
            'Dubai Islamic Bank', 'DIB',
            'Mashreq', 'Mashreqbank',
            'Commercial Bank of Dubai', 'CBD',
            'Union National Bank', 'UNB',
            'ADIB', 'Abu Dhabi Islamic Bank',
            'Noor Bank', 'RAKBank', 'Sharjah Islamic Bank',
            'Invest Bank', 'National Bank of Fujairah',
            'Emirates Development Bank',

            // Saudi Banks
            'Al Rajhi Bank', 'Al Rajhi', 'Rajhi',
            'SNB', 'Saudi National Bank', 'NCB', 'National Commercial Bank',
            'Samba', 'Samba Financial Group',
            'Riyad Bank', 'Riyadh Bank',
            'Banque Saudi Fransi', 'BSF',
            'Arab National Bank', 'ANB',
            'Alinma Bank', 'Bank AlJazira', 'BAJ',
            'Saudi British Bank', 'SABB',
            'Bank Albilad', 'Saudi Investment Bank', 'SAIB',

            // Qatar Banks
            'QNB', 'Qatar National Bank', 'QNB Group',
            'Commercial Bank Qatar', 'CBQ',
            'Doha Bank', 'Qatar Islamic Bank', 'QIB',
            'Masraf Al Rayan', 'Al Khaliji', 'QIIB',
            'Ahli Bank Qatar', 'Dukhan Bank',

            // Kuwait Banks
            'NBK', 'National Bank of Kuwait',
            'Kuwait Finance House', 'KFH',
            'Burgan Bank', 'Gulf Bank Kuwait',
            'Ahli United Bank Kuwait', 'Warba Bank', 'Boubyan Bank',

            // Bahrain Banks
            'Ahli United Bank', 'AUB',
            'Bank of Bahrain and Kuwait', 'BBK',
            'National Bank of Bahrain', 'NBB',
            'Bahrain Islamic Bank', 'BisB',
            'Al Baraka Banking Group', 'Al Baraka',
            'Ithmaar Bank', 'GFH Financial Group', 'GFH',

            // Oman Banks
            'Bank Muscat', 'BankMuscat',
            'National Bank of Oman', 'NBO',
            'Bank Dhofar', 'Oman Arab Bank', 'OAB',
            'Sohar International', 'Ahli Bank Oman',

            // Egypt Banks
            'National Bank of Egypt', 'NBE',
            'Banque Misr', 'Commercial International Bank', 'CIB Egypt',
            'QNB Alahli', 'Arab African International Bank', 'AAIB',
            'Banque du Caire', 'Faisal Islamic Bank Egypt',

            // Regional/Pan-Arab Banks
            'Arab Bank', 'Arab Banking Corporation', 'ABC Bank',
            'Gulf International Bank', 'GIB',
            'BLOM Bank', 'Byblos Bank', 'Bank Audi',
            'Arab African International Bank',

            // ==========================================
            // PRIVATE EQUITY & INVESTMENT FIRMS
            // ==========================================
            // Major Regional PE
            'Investcorp', 'Gulf Capital', 'Arcapita', 'Abraaj', 'Abraaj Group',
            'Fajr Capital', 'NBK Capital', 'NBK Capital Partners',
            'Waha Capital', 'Al Mal Capital', 'Shuaa Capital', 'SHUAA',
            'Arqaam Capital', 'Jadwa Investment', 'Jadwa',
            'Amwal Al Ghad', 'Amwal', 'Swicorp',
            'Gulf Related', 'Algebra Capital',

            // Saudi PE/VC
            'Riyad Capital', 'Saudi Venture Capital', 'SVC',
            'Raed Ventures', 'Impact46', 'Elm Company',
            'STV', 'Saudi Technology Ventures',
            'Vision Ventures', 'Wa\'ed', 'Aramco Ventures',
            'Derayah Financial', 'Malaz Capital',

            // UAE PE/VC
            'Shorooq Partners', 'Shorooq', 'BECO Capital', 'Beco',
            'Global Ventures', 'Nuwa Capital', 'Wamda Capital',
            'private equity Venture Partners', 'MEVP',
            '500 Global private equity', '500 Startups private equity', 'Flat6Labs',
            'VentureSouq', 'DSOA Ventures', 'Dtec Ventures',
            'Emirates Capital', 'Crescent Enterprises',
            'Abu Dhabi Investment Office', 'ADIO',

            // Qatar PE/VC
            'Qatar Science & Technology Park', 'QSTP',
            'Qatar Development Bank', 'QDB',
            'Ooredoo Ventures',

            // Kuwait PE/VC
            'KIPCO', 'Kuwait Projects Company',
            'Agility', 'Agility Ventures',
            'Faith Capital', 'Kuwait & private equity Financial Investment',

            // Egypt PE/VC
            'Sawari Ventures', 'Algebra Ventures', 'A15',
            'Endure Capital', 'Disruptech', 'Egypt Ventures',
            'Ezdehar Management', 'Development Partners International',
            'Lorax Capital', 'Acumen', 'Delta Partners',

            // International PE Active in ME
            'CVC Capital', 'Blackstone private equity', 'KKR private equity',
            'Carlyle private equity', 'TPG private equity', 'Warburg Pincus private equity',
            'General Atlantic private equity', 'Brookfield private equity',
            'Colony Capital', 'Apollo private equity', 'Advent private equity',

            // ==========================================
            // REAL ESTATE & DEVELOPERS
            // ==========================================
            // UAE Developers
            'Emaar', 'Emaar Properties', 'Emaar Development',
            'DAMAC', 'DAMAC Properties', 'Hussain Sajwani',
            'Aldar', 'Aldar Properties', 'Aldar Investment',
            'Nakheel', 'Meraas', 'Dubai Properties', 'Dubai Holding',
            'Deyaar', 'Union Properties', 'Azizi', 'Azizi Developments',
            'Sobha Realty', 'MAG Property Development', 'Binghatti',
            'Omniyat', 'Select Group', 'Ellington Properties',
            'RAK Properties', 'Bloom Holding', 'Eagle Hills',
            'Reportage Properties', 'Arada', 'Tilal Properties',

            // Saudi Developers
            'Dar Al Arkan', 'Jabal Omar', 'SEDCO', 'SEDCO Holding',
            'Ewaan Global', 'Knowledge Economic City',
            'Roshn', 'ROSHN Group', 'Saudi Real Estate Company',
            'Retal Urban Development', 'Sumou Real Estate',

            // Other Regional Developers
            'Qatari Diar', 'United Development Company', 'UDC',
            'Barwa Real Estate', 'Msheireb Properties', 'Katara Hospitality',
            'GFH', 'Tameer', 'OQYANA', 'Solidarity Bahrain',
            'Al Mazaya Holding', 'United Real Estate', 'URC Kuwait',
            'Omran', 'Al Mouj Muscat', 'Muriya',
            'Palm Hills', 'Talaat Moustafa Group', 'TMG',
            'SODIC', 'Orascom Development', 'Madinet Masr',
            'Mountain View', 'Hyde Park',

            // ==========================================
            // ENERGY & UTILITIES
            // ==========================================
            // Oil & Gas
            'Saudi Aramco', 'Aramco', 'SABIC',
            'ADNOC', 'Abu Dhabi National Oil Company',
            'QatarEnergy', 'Qatar Petroleum', 'RasGas', 'Qatargas',
            'Kuwait Petroleum Corporation', 'KPC', 'KNPC',
            'Petroleum Development Oman', 'PDO', 'OQ',
            'ENOC', 'Emirates National Oil Company',
            'Dragon Oil', 'Dana Gas', 'Crescent Petroleum',
            'BAPCO', 'Bahrain Petroleum', 'Tatweer Petroleum',
            'EGPC', 'Egyptian General Petroleum',
            'ENI Egypt', 'BP Egypt', 'Shell Egypt',
            'SONATRACH', 'Sonangol',

            // Renewable Energy & Utilities
            'ACWA Power', 'Masdar', 'Masdar Clean Energy',
            'TAQA', 'Abu Dhabi National Energy',
            'DEWA', 'Dubai Electricity and Water', 'FEWA', 'SEWA',
            'EWEC', 'Emirates Water and Electricity',
            'SEC', 'Saudi Electricity Company', 'SWCC',
            'Kahramaa', 'Qatar General Electricity',
            'Nebras Power', 'Siraj Power', 'Yellow Door Energy',
            'Phanes Group', 'Enerwhere', 'Desert Technologies',
            'AMEA Power', 'Alcazar Energy', 'Access Power',

            // ==========================================
            // TELECOM & TECHNOLOGY
            // ==========================================
            // Telecom
            'Etisalat', 'e&', 'Emirates Telecom', 'du', 'EITC',
            'STC', 'Saudi Telecom', 'stc', 'Mobily', 'Zain KSA',
            'Zain', 'Zain Group', 'Zain Kuwait',
            'Ooredoo', 'Ooredoo Group', 'Ooredoo Qatar', 'Ooredoo Kuwait',
            'Batelco', 'Bahrain Telecom',
            'Omantel', 'Vodafone Egypt', 'Orange Egypt', 'WE Egypt',
            'Maroc Telecom', 'Inwi', 'Orange Morocco',
            'Djezzy', 'Mobilis', 'Ooredoo Algeria',

            // Tech Companies
            'Careem', 'Souq.com', 'Noon', 'Noon.com',
            'Talabat', 'Deliveroo private equity', 'Uber private equity',
            'Swvl', 'Kitopi', 'Tabby', 'Tamara', 'Postpay',
            'Fetchr', 'Trukker', 'Yassir', 'InstaShop',
            'Anghami', 'Shahid', 'StarzPlay', 'OSN',
            'Bayut', 'Property Finder', 'Dubizzle', 'OLX private equity',
            'Mumzworld', 'Namshi', 'Ounass', 'Sivvi',
            'PayTabs', 'Telr', 'Network International', 'Magnati',
            'Sarwa', 'Wahed Invest', 'Ziina', 'Pemo',
            'Pure Harvest', 'Nana', 'Floward', 'Zywa',
            'Emerging Markets Payments', 'EMP',

            // ==========================================
            // CONGLOMERATES & FAMILY GROUPS
            // ==========================================
            // UAE Family Groups
            'Al Futtaim', 'Majid Al Futtaim', 'MAF',
            'Al Habtoor', 'Al Habtoor Group', 'Khalaf Al Habtoor',
            'Al Ghurair', 'Al Ghurair Group', 'Mashreq Al Ghurair',
            'Al Tayer', 'Al Tayer Group',
            'Lulu Group', 'Lulu International', 'Yusuff Ali',
            'BinHendi', 'Al Rostamani', 'Al Naboodah',
            'Al Fahim', 'Al Masaood', 'Galadari',
            'Juma Al Majid', 'Al Sayegh', 'Al Ansari',
            'Al Shirawi', 'Al Serkal',

            // Saudi Family Groups
            'Kingdom Holding', 'Prince Alwaleed', 'Al Waleed',
            'Olayan', 'Olayan Group', 'Olayan Financing',
            'Al Muhaidib', 'Zahid Group', 'Al Zamil Group',
            'Bin Laden Group', 'Saudi Binladin',
            'Dallah Al Baraka', 'Al Rajhi Holding',
            'Al Subeaei', 'ALHOKAIR', 'Al Faisaliah Group',
            'Alfanar', 'SEDCO Holding', 'Xenel Industries',
            'Abdul Latif Jameel', 'ALJ', 'Jameel',
            'Savola Group', 'Panda Retail', 'Almarai',
            'Mobily', 'SACO', 'Extra', 'Jarir Bookstore',

            // Kuwait Family Groups
            'Al Kharafi', 'Kharafi Group', 'MA Kharafi',
            'Al Sagar', 'Al Ghanim', 'Alghanim Industries',
            'Al Shaya', 'MH Alshaya', 'Alshaya Group',
            'Al Bahar', 'Mohammed Abdulmohsin Al Bahar',
            'Al Mulla', 'Al Mulla Group',

            // Other Regional Groups
            'Chalhoub Group', 'Chalhoub', 'Landmark Group',
            'Centurion', 'Al Meera', 'Spar Qatar',
            'Al Fardan', 'Ali Bin Ali', 'Mannai Corporation',
            'Kanoo', 'Yusuf bin Ahmed Kanoo',
            'Al Zayani', 'Al Moayyed', 'BMMI',

            // ==========================================
            // AVIATION & LOGISTICS
            // ==========================================
            'Emirates', 'Emirates Airline', 'flydubai', 'Air Arabia',
            'Etihad', 'Etihad Airways', 'Etihad Aviation Group',
            'Qatar Airways', 'QR', 'Hamad International Airport',
            'Saudia', 'Saudi Arabian Airlines', 'Riyadh Air', 'flynas', 'flyadeal',
            'Gulf Air', 'Kuwait Airways', 'Oman Air', 'SalamAir',
            'EgyptAir', 'Royal Jordanian', 'MEA', 'private equity Airlines',
            'DP World', 'Jafza', 'Jebel Ali Free Zone',
            'Abu Dhabi Ports', 'AD Ports', 'Mawani',
            'Aramex', 'Agility Logistics', 'Tristar',
            'Al Futtaim Logistics', 'GAC', 'RSA Logistics',

            // ==========================================
            // HOSPITALITY & TOURISM
            // ==========================================
            'Jumeirah', 'Jumeirah Group', 'Burj Al Arab',
            'Emaar Hospitality', 'Address Hotels', 'Vida Hotels',
            'Rotana', 'Rotana Hotels', 'FIVE Hotels',
            'Atlantis', 'Kerzner', 'Anantara',
            'Katara Hospitality', 'Accor private equity',
            'Marriott private equity', 'Hilton private equity', 'IHG private equity',
            'NEOM Hotels', 'Red Sea Global', 'RSG',
            'Diriyah Gate', 'MDL Beast', 'Riyadh Season',
            'Expo 2020', 'Expo City Dubai', 'Dubai Expo',
            'Department of Culture and Tourism', 'DCT Abu Dhabi',
            'Saudi Tourism Authority', 'Visit Saudi',

            // ==========================================
            // EXCHANGES & FINANCIAL INFRASTRUCTURE
            // ==========================================
            'Tadawul', 'Saudi Exchange', 'Saudi Stock Exchange',
            'ADX', 'Abu Dhabi Securities Exchange',
            'DFM', 'Dubai Financial Market', 'Nasdaq Dubai',
            'QSE', 'Qatar Stock Exchange',
            'Boursa Kuwait', 'Kuwait Stock Exchange',
            'Bahrain Bourse', 'BHB',
            'MSM', 'Muscat Securities Market', 'Muscat Stock Exchange',
            'EGX', 'Egyptian Exchange', 'Cairo Stock Exchange',
            'ASE', 'Amman Stock Exchange',
            'TASE', 'Tel Aviv Stock Exchange',
            'Casablanca Stock Exchange', 'Morocco Stock Exchange',
            'DIFC', 'Dubai International Financial Centre',
            'ADGM', 'Abu Dhabi Global Market',
            'QFC', 'Qatar Financial Centre',
            'DFSA', 'Dubai Financial Services Authority',
            'CMA', 'Capital Markets Authority', 'SCA',

            // ==========================================
            // ECONOMIC ZONES & FREE ZONES
            // ==========================================
            'DMCC', 'Dubai Multi Commodities Centre',
            'DAFZA', 'Dubai Airport Free Zone',
            'Jafza', 'Jebel Ali Free Zone',
            'RAKEZ', 'RAK Economic Zone', 'RAK Free Zone',
            'Sharjah Media City', 'Shams', 'SAIF Zone',
            'Fujairah Free Zone', 'Ajman Free Zone',
            'Masdar City', 'Hub71', 'twofour54',
            'Dubai Silicon Oasis', 'DSO', 'DIC', 'Dubai Internet City',
            'Dubai Media City', 'DMC', 'Knowledge Village',
            'Dubai Healthcare City', 'DHCC',
            'KAEC', 'King Abdullah Economic City',
            'Jazan Economic City', 'Ras Al Khair',
            'QFZ', 'Qatar Free Zones',
            'Bahrain Investment Wharf', 'BIIP',
            'Duqm Special Economic Zone', 'SEZAD',
            'Sohar Free Zone', 'Salalah Free Zone',
            'New Suez Canal Zone', 'Ain Sokhna',

            // ==========================================
            // GOVERNMENT ENTITIES & REGULATORS
            // ==========================================
            'CBUAE', 'Central Bank of the UAE',
            'SAMA', 'Saudi Central Bank',
            'QCB', 'Qatar Central Bank',
            'CBB', 'Central Bank of Bahrain',
            'CBK', 'Central Bank of Kuwait',
            'CBO', 'Central Bank of Oman',
            'CBE', 'Central Bank of Egypt',
            'Ministry of Finance UAE', 'Ministry of Economy UAE',
            'Ministry of Investment Saudi', 'MISA',
            'Royal Commission for Riyadh', 'RCR',
            'Saudi Authority for Industrial Cities', 'MODON',
            'Dubai Economy', 'DED', 'Department of Economic Development',
            'Abu Dhabi Department of Economic Development', 'ADDED',

            // ==========================================
            // CONSULTING & ADVISORY (REGIONAL OFFICES)
            // ==========================================
            'McKinsey private equity', 'BCG private equity', 'Bain private equity',
            'PwC private equity', 'Deloitte private equity', 'EY private equity', 'KPMG private equity',
            'Oliver Wyman private equity', 'Roland Berger private equity',
            'Strategy& private equity', 'Kearney private equity', 'Arthur D Little private equity',
            'Alvarez & Marsal private equity', 'FTI Consulting private equity',

            // ==========================================
            // LAW FIRMS (REGIONAL)
            // ==========================================
            'Al Tamimi', 'Al Tamimi & Company', 'Hadef & Partners',
            'Clyde & Co Dubai', 'Clifford Chance DIFC', 'Allen & Overy private equity',
            'Linklaters private equity', 'Freshfields private equity', 'White & Case private equity',
            'DLA Piper private equity', 'Baker McKenzie Dubai',
            'Dentons private equity', 'Norton Rose private equity', 'King & Spalding Dubai',
            'Latham & Watkins Dubai', 'Gibson Dunn Dubai',
            'BSA Ahmad Bin Hezeem', 'Afridi & Angell',

            // ==========================================
            // ISLAMIC FINANCE
            // ==========================================
            'Sukuk', 'Islamic Bond', 'Green Sukuk',
            'Islamic Finance', 'Islamic Banking', 'Islamic Insurance',
            'Sharia', 'Shariah', 'Sharia-compliant', 'Shariah-compliant',
            'Halal', 'Halal Investment', 'Halal Fund',
            'Murabaha', 'Mudaraba', 'Musharaka', 'Ijara', 'Ijarah',
            'Takaful', 'Islamic Takaful', 'Retakaful',
            'Wakala', 'Istisna', 'Salam',
            'AAOIFI', 'Accounting and Auditing Organization',
            'IIFM', 'International Islamic Financial Market',
            'Islamic Development Bank', 'IsDB', 'IDB',

            // ==========================================
            // NATIONAL TRANSFORMATION & VISIONS
            // ==========================================
            'Saudi Vision 2030', 'Vision 2030', 'Vision Realization',
            'National Transformation Program', 'NTP',
            'We the UAE 2031', 'UAE Centennial 2071',
            'Dubai 2040', 'Dubai Urban Master Plan',
            'Abu Dhabi Economic Vision 2030',
            'New Kuwait 2035', 'Kuwait Vision 2035',
            'Oman Vision 2040', 'Oman 2040',
            'Qatar National Vision 2030', 'QNV 2030',
            'Bahrain Economic Vision 2030',
            'Egypt Vision 2030', 'New Republic',
            'Jordan 2025',

            // ==========================================
            // IPO & CAPITAL MARKETS TERMS
            // ==========================================
            'Tadawul IPO', 'ADX IPO', 'DFM IPO',
            'Saudi IPO', 'Dubai IPO', 'Abu Dhabi IPO',
            'GCC IPO', 'private equity IPO', 'private equity IPO',
            'Regional listing', 'Dual listing private equity',
            'SPAC private equity', 'SPAC GCC',

            // ==========================================
            // SECTORS & INDUSTRIES
            // ==========================================
            'GCC Healthcare', 'private equity Fintech', 'Gulf Real Estate',
            'Saudi Entertainment', 'Dubai Retail', 'Abu Dhabi Tech',
            'GCC E-commerce', 'private equity Logistics', 'Gulf Aviation',
            'Saudi Giga Projects', 'UAE PropTech',
        );
    }

    /**
     * Get posts filtered by keywords in title and content
     */
    private function get_posts_by_keywords($post_type, $limit, $keywords) {
        global $wpdb;

        // Build the keyword matching conditions
        $keyword_conditions = array();
        foreach ($keywords as $keyword) {
            $escaped_keyword = $wpdb->esc_like($keyword);
            $keyword_conditions[] = $wpdb->prepare(
                "(p.post_title LIKE %s OR p.post_content LIKE %s)",
                '%' . $escaped_keyword . '%',
                '%' . $escaped_keyword . '%'
            );
        }

        if (empty($keyword_conditions)) {
            return array();
        }

        // Handle post type (single or array)
        if (is_array($post_type)) {
            $post_type_placeholders = implode(', ', array_fill(0, count($post_type), '%s'));
            $post_type_sql = $wpdb->prepare("p.post_type IN ($post_type_placeholders)", $post_type);
        } else {
            $post_type_sql = $wpdb->prepare("p.post_type = %s", $post_type);
        }

        $keyword_sql = '(' . implode(' OR ', $keyword_conditions) . ')';

        $query = "
            SELECT DISTINCT p.ID
            FROM {$wpdb->posts} p
            WHERE {$post_type_sql}
            AND p.post_status = 'publish'
            AND {$keyword_sql}
            ORDER BY p.post_date DESC
            LIMIT %d
        ";

        $post_ids = $wpdb->get_col($wpdb->prepare($query, $limit));

        if (empty($post_ids)) {
            return array();
        }

        // Get full post objects while preserving order
        return get_posts(array(
            'post_type' => $post_type,
            'post__in' => $post_ids,
            'posts_per_page' => count($post_ids),
            'orderby' => 'post__in',
            'post_status' => 'publish',
            'no_found_rows' => true,
        ));
    }

    /**
     * Retrieve posts by IDs while preserving order
     */
    private function get_posts_by_ids($post_ids)
    {
        if (empty($post_ids)) {
            return array();
        }

        $posts = get_posts(array(
            'post_type' => array('sffc_pe_news', 'sffc_pe_deal', 'sffc_pe_signal'),
            'post__in' => $post_ids,
            'post_status' => 'publish',
            'posts_per_page' => count($post_ids),
            'orderby' => 'post__in',
            'no_found_rows' => true
        ));

        return $posts;
    }

    private function get_user_saved_post_ids($user_id)
    {
        if (!$user_id) {
            return array();
        }

        $stored = get_user_meta($user_id, 'sffc_saved_feed_items', true);
        if (!is_array($stored)) {
            return array();
        }

        $stored = array_map('absint', $stored);
        $stored = array_filter($stored);
        return array_values(array_unique($stored));
    }

    private function set_user_saved_post_ids($user_id, $ids)
    {
        if (!$user_id) {
            return;
        }

        $sanitized = array_map('absint', (array) $ids);
        $sanitized = array_filter($sanitized);
        update_user_meta($user_id, 'sffc_saved_feed_items', array_values(array_unique($sanitized)));
    }

    private function build_saved_feed_items($post_ids)
    {
        if (empty($post_ids)) {
            return array();
        }

        $posts = get_posts(array(
            'post_type' => array('sffc_pe_news', 'sffc_pe_deal', 'sffc_pe_signal', 'sffc_job'),
            'post__in' => $post_ids,
            'post_status' => 'publish',
            'posts_per_page' => count($post_ids),
            'orderby' => 'post__in',
            'no_found_rows' => true
        ));

        if (empty($posts)) {
            return array();
        }

        $stories = array();
        $jobs = array();
        foreach ($posts as $post) {
            if (!is_a($post, 'WP_Post')) {
                continue;
            }
            if ('sffc_job' === $post->post_type) {
                $jobs[] = $post;
            } else {
                $stories[] = $post;
            }
        }

        $formatted = array();
        foreach ($this->format_feed_items($stories) as $item) {
            $formatted[$item['id']] = $item;
        }
        foreach ($this->format_job_items($jobs) as $item) {
            $formatted[$item['id']] = $item;
        }

        $ordered = array();
        foreach ($post_ids as $id) {
            if (isset($formatted[$id])) {
                $ordered[] = $formatted[$id];
            }
        }

        return $ordered;
    }


    private function build_donut_segments($stories, $jobs, $saved)
    {
        $counts = array(
            'insights' => max(1, count($stories)),
            'jobs' => max(1, count($jobs)),
            'saved' => max(1, count($saved))
        );
        $palette = array(
            'insights' => '#0d353e',
            'jobs' => '#0e6e6c',
            'saved' => '#c75643'
        );
        $segments = array();
        foreach ($counts as $key => $value) {
            $value = max(1, $value + rand(-2, 3));
            $label = ('insights' === $key) ? __('Insights', 'senna-finance') : (('jobs' === $key) ? __('Jobs', 'senna-finance') : __('Saved', 'senna-finance'));
            $segments[] = array(
                'tab' => $key,
                'label' => $label,
                'value' => $value,
                'color' => $palette[$key]
            );
        }
        $total = max(1, array_sum(array_column($segments, 'value')));
        $gradient = array();
        $running = 0;
        foreach ($segments as $index => $segment) {
            $start = ($running / $total) * 100;
            $running += $segment['value'];
            $end = ($running / $total) * 100;
            $segments[$index]['percentage'] = round(($segment['value'] / $total) * 100);
            $gradient[] = $segment['color'] . ' ' . $start . '% ' . $end . '%';
        }

        return array(
            'segments' => $segments,
            'gradient' => implode(', ', $gradient)
        );
    }

    private function extract_trending_keyword($title)
    {
        if (empty($title)) {
            return '';
        }

        $normalized = strtolower($title);
        $normalized = preg_replace('/[^a-z0-9\s]/', ' ', $normalized);
        $words = preg_split('/\s+/', $normalized);
        $words = array_filter($words);
        $stopwords = array('and', 'the', 'for', 'from', 'with', 'into', 'over', 'under', 'after', 'before', 'amid', 'at', 'by', 'in', 'of', 'on', 'to', 'vs', 'via', 'a', 'an');

        foreach ($words as $word) {
            if (strlen($word) < 3) {
                continue;
            }
            if (in_array($word, $stopwords, true)) {
                continue;
            }
            return ucwords($word);
        }

        return '';
    }

    /**
     * Analyze content to extract filter keywords (deal types, regions, sectors)
     * This enables filtering to work based on post content rather than just meta fields
     */
    private function analyze_content_for_keywords($title, $excerpt, $content = '')
    {
        $text = strtolower($title . ' ' . $excerpt . ' ' . $content);
        $keywords = array();

        // Deal Types detection
        $deal_type_patterns = array(
            'fund-raises' => array('fund raise', 'fundraise', 'fund-raise', 'raised', 'raises', 'capital raise', 'fundraising', 'closes fund', 'new fund', 'fund close', 'committed', 'lp commit', 'first close', 'final close'),
            'ma' => array('m&a', 'merger', 'acquisition', 'acquires', 'acquired', 'to acquire', 'merges', 'merged', 'takeover', 'take over', 'buyout deal', 'buys', 'bought', 'purchase', 'carve-out', 'carveout'),
            'exits' => array('exit', 'exits', 'ipo', 'initial public offering', 'goes public', 'listing', 'listed', 'divest', 'divestiture', 'sells stake', 'sold stake', 'secondary sale', 'sponsor exit', 'trade sale'),
            'regulatory' => array('regulatory', 'regulation', 'compliance', 'sec ', 'ftc ', 'antitrust', 'investigation', 'enforcement', 'fine', 'penalty', 'settlement', 'lawsuit', 'legal', 'doj', 'fca'),
            'personnel' => array('personnel', 'hire', 'hires', 'hired', 'appoint', 'appointed', 'appointment', 'promotion', 'promoted', 'joins', 'joined', 'departs', 'departure', 'leaves', 'resigns', 'ceo', 'cfo', 'cio', 'partner', 'managing director', 'head of', 'names'),
            'secondaries' => array('secondary', 'secondaries', 'gp-led', 'lp-led', 'continuation fund', 'continuation vehicle', 'stapled secondary', 'tender offer', 'secondary transaction'),
            'distressed' => array('distressed', 'restructuring', 'bankruptcy', 'chapter 11', 'turnaround', 'special situations', 'stressed', 'workout', 'creditor', 'default')
        );

        foreach ($deal_type_patterns as $slug => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($text, $pattern) !== false) {
                    $keywords[] = $slug;
                    break;
                }
            }
        }

        // Regions detection
        $region_patterns = array(
            'north-america' => array('north america', 'united states', 'u.s.', 'us ', 'usa', 'american', 'canada', 'canadian', 'mexico', 'new york', 'california', 'texas', 'florida', 'chicago', 'boston', 'san francisco', 'los angeles', 'toronto', 'vancouver', 'seattle', 'miami', 'denver', 'atlanta'),
            'europe' => array('europe', 'european', 'uk ', 'u.k.', 'united kingdom', 'britain', 'british', 'london', 'germany', 'german', 'france', 'french', 'paris', 'italy', 'italian', 'spain', 'spanish', 'netherlands', 'dutch', 'sweden', 'nordic', 'switzerland', 'swiss', 'benelux', 'dach'),
            'asia-pacific' => array('asia', 'asian', 'apac', 'asia-pacific', 'asia pacific', 'china', 'chinese', 'japan', 'japanese', 'india', 'indian', 'singapore', 'hong kong', 'australia', 'australian', 'korea', 'korean', 'southeast asia', 'taiwan', 'vietnam', 'indonesia'),
            'private-equity' => array('private equity', 'private_equity', 'gulf', 'gcc', 'saudi', 'uae', 'dubai', 'abu dhabi', 'qatar', 'kuwait', 'bahrain', 'oman', 'israel', 'turkish', 'turkey'),
            'latam' => array('latin america', 'latam', 'brazil', 'brazilian', 'mexico', 'mexican', 'chile', 'chilean', 'colombia', 'colombian', 'argentina', 'peru', 'south america'),
            'global' => array('global', 'worldwide', 'international', 'cross-border', 'multi-region', 'pan-european', 'pan-asian')
        );

        foreach ($region_patterns as $slug => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($text, $pattern) !== false) {
                    $keywords[] = $slug;
                    break;
                }
            }
        }

        // Sectors detection
        $sector_patterns = array(
            'buyout' => array('buyout', 'buy-out', 'lbo', 'leveraged buyout', 'private equity buyout', 'pe buyout', 'control investment', 'majority stake', 'take-private', 'going private'),
            'growth-equity' => array('growth equity', 'growth capital', 'growth investment', 'growth-stage', 'expansion capital', 'minority investment', 'minority stake', 'growth fund'),
            'venture-capital' => array('venture capital', 'venture', 'vc ', 'seed', 'series a', 'series b', 'series c', 'series d', 'early-stage', 'startup', 'start-up', 'tech investment'),
            'real-estate' => array('real estate', 'property', 'reit', 'real-estate', 'commercial property', 'residential', 'office building', 'retail property', 'industrial property', 'logistics property', 'multifamily'),
            'infrastructure' => array('infrastructure', 'infra ', 'energy transition', 'renewable', 'solar', 'wind', 'utilities', 'toll road', 'airport', 'port', 'telecom tower', 'data center', 'fibre'),
            'credit' => array('private credit', 'direct lending', 'private debt', 'credit fund', 'mezzanine', 'unitranche', 'senior debt', 'subordinated', 'leveraged loan', 'clo'),
            'healthcare' => array('healthcare', 'health care', 'pharma', 'biotech', 'life science', 'medtech', 'hospital', 'clinical', 'medical device', 'diagnostics', 'drug'),
            'technology' => array('technology', 'tech ', 'software', 'saas', 'fintech', 'cybersecurity', 'ai ', 'artificial intelligence', 'cloud', 'digital', 'e-commerce', 'it services'),
            'energy' => array('energy', 'oil', 'gas', 'upstream', 'midstream', 'downstream', 'pipeline', 'refinery', 'lng', 'power generation', 'utilities')
        );

        foreach ($sector_patterns as $slug => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($text, $pattern) !== false) {
                    $keywords[] = $slug;
                    break;
                }
            }
        }

        return array_unique($keywords);
    }

    /**
     * Analyze job content to extract filter keywords (job functions, regions, seniority)
     * This enables filtering to work based on job content rather than just meta fields
     */
    private function analyze_job_content_for_keywords($title, $company, $job_family, $job_level, $location)
    {
        $text = strtolower($title . ' ' . $company . ' ' . $job_family . ' ' . $location);
        $keywords = array();

        // Job Functions detection
        $function_patterns = array(
            'private-equity' => array('private equity', 'pe ', 'pe-', 'buyout', 'lbo', 'portfolio', 'deal team', 'investment team', 'fund'),
            'investment-banking' => array('investment bank', 'ib ', 'm&a', 'capital markets', 'advisory', 'underwriting', 'dcm', 'ecm', 'leveraged finance'),
            'asset-management' => array('asset management', 'wealth management', 'portfolio manager', 'fund manager', 'mutual fund', 'aum', 'institutional'),
            'corporate-development' => array('corporate development', 'corp dev', 'strategic', 'business development', 'corporate strategy', 'bd '),
            'strategy-research' => array('strategy', 'research', 'equity research', 'credit research', 'market research', 'consulting'),
            'hedge-fund' => array('hedge fund', 'hedgefund', 'quant', 'quantitative', 'systematic', 'macro', 'long/short', 'event driven'),
            'venture-capital' => array('venture capital', 'vc ', 'venture', 'seed', 'early stage', 'series a', 'startup'),
            'real-estate' => array('real estate', 'property', 'reit', 'acquisitions', 'development')
        );

        foreach ($function_patterns as $slug => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($text, $pattern) !== false) {
                    $keywords[] = $slug;
                    break;
                }
            }
        }

        // Region detection from location
        $location_lower = strtolower($location);
        $region_patterns = array(
            'north-america' => array('new york', 'boston', 'chicago', 'san francisco', 'los angeles', 'houston', 'dallas', 'miami', 'atlanta', 'seattle', 'denver', 'washington', 'toronto', 'vancouver', 'montreal', 'usa', 'united states', 'canada'),
            'europe' => array('london', 'paris', 'frankfurt', 'munich', 'berlin', 'zurich', 'geneva', 'amsterdam', 'dublin', 'milan', 'madrid', 'barcelona', 'stockholm', 'copenhagen', 'uk', 'united kingdom'),
            'asia-pacific' => array('singapore', 'hong kong', 'shanghai', 'beijing', 'tokyo', 'sydney', 'melbourne', 'mumbai', 'bangalore', 'delhi', 'seoul'),
            'private-equity' => array('dubai', 'abu dhabi', 'riyadh', 'doha', 'tel aviv', 'israel'),
            'remote' => array('remote', 'work from home', 'wfh', 'hybrid', 'flexible location')
        );

        foreach ($region_patterns as $slug => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($location_lower, $pattern) !== false) {
                    $keywords[] = $slug;
                    break;
                }
            }
        }

        // Seniority/Level detection from title
        $title_lower = strtolower($title . ' ' . $job_level);
        $level_patterns = array(
            'analyst' => array('analyst', 'junior', 'entry level', 'graduate'),
            'associate' => array('associate', 'senior analyst'),
            'vice-president' => array('vice president', 'vp', 'senior associate', 'principal'),
            'director' => array('director', 'senior vice president', 'svp', 'executive director'),
            'partner' => array('partner', 'managing director', 'md', 'head of', 'chief', 'ceo', 'cfo', 'cio', 'founder')
        );

        foreach ($level_patterns as $slug => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($title_lower, $pattern) !== false) {
                    $keywords[] = $slug;
                    break;
                }
            }
        }

        return array_unique($keywords);
    }

    /**
     * Normalize post data for the dashboard feed
     */
    private function format_feed_items($posts, $type = '', $skip_content_analysis = false)
    {
        $items = array();

        if (empty($posts)) {
            return $items;
        }

        // Prime the post meta cache for all posts at once (single query)
        $post_ids = wp_list_pluck($posts, 'ID');
        update_meta_cache('post', $post_ids);

        foreach ($posts as $post) {
            if (!is_a($post, 'WP_Post')) {
                continue;
            }

            $post_type = $type ?: ($post->post_type === 'sffc_pe_deal' ? 'deal' : ($post->post_type === 'sffc_pe_signal' ? 'signal' : 'news'));
            $timestamp = get_post_time('U', true, $post);
            $company = $this->get_first_meta_value($post->ID, array('news_company', '_companies_involved', 'deal_company'));
            $sector = $this->get_first_meta_value($post->ID, array('news_sector', '_sector', 'deal_sector', 'company_sector'));
            $region = $this->get_first_meta_value($post->ID, array('news_region', 'deal_region', '_region'));
            $deal_type = $this->get_first_meta_value($post->ID, array('news_deal_type', 'deal_category', '_deal_category'));
            $value_meta = $this->get_first_meta_value($post->ID, array('_deal_value', 'deal_value'));
            $value_numeric = $this->parse_deal_value($value_meta);

            // Get title and excerpt
            $title = get_the_title($post);
            $excerpt = $post->post_excerpt ? wp_trim_words($post->post_excerpt, 28) : wp_trim_words(wp_strip_all_tags($post->post_content), 32);

            // Only analyze content for keywords if not skipped (heavy operation)
            // Use cached keywords if available, otherwise analyze only title/excerpt (faster)
            $content_keywords = array();
            if (!$skip_content_analysis) {
                $cached_keywords = get_post_meta($post->ID, '_sffc_content_keywords', true);
                if (!empty($cached_keywords) && is_array($cached_keywords)) {
                    $content_keywords = $cached_keywords;
                } else {
                    // Analyze only title and excerpt for speed (skip full content)
                    $content_keywords = $this->analyze_content_for_keywords($title, $excerpt, '');
                }
            }

            $items[] = array(
                'id' => $post->ID,
                'title' => $title,
                'link' => get_permalink($post),
                'excerpt' => $excerpt,
                'type' => $post_type,
                'company' => $company,
                'sector' => $sector,
                'region' => $region,
                'deal_type' => $deal_type,
                'deal_value' => $value_meta,
                'deal_value_numeric' => $value_numeric,
                'timestamp' => $timestamp,
                'relative_time' => human_time_diff($timestamp, current_time('timestamp')),
                'is_priority' => $this->is_priority_item($post_type, $value_numeric, $sector),
                'content_keywords' => $content_keywords
            );
        }

        return $items;
    }

    /**
     * Normalize job postings for the Jobs tab
     */
    private function format_job_items($posts)
    {
        $items = array();

        if (empty($posts)) {
            return $items;
        }

        // Prime the post meta cache for all posts at once (single query)
        $post_ids = wp_list_pluck($posts, 'ID');
        update_meta_cache('post', $post_ids);

        foreach ($posts as $post) {
            if (!is_a($post, 'WP_Post')) {
                continue;
            }

            $company = get_post_meta($post->ID, 'sffc_actual_company', true);
            if (empty($company)) {
                $company = get_post_meta($post->ID, 'sffc_company_name', true);
            }

            $city = get_post_meta($post->ID, 'sffc_location_city', true);
            $country = get_post_meta($post->ID, 'sffc_location_country', true);
            $location = trim($city . ', ' . $country, ' ,');
            if (empty($location)) {
                $location = get_post_meta($post->ID, 'sffc_location', true);
            }

            $job_family = get_post_meta($post->ID, 'sffc_job_family', true);
            $job_level = get_post_meta($post->ID, 'sffc_job_level', true);
            $job_type = get_post_meta($post->ID, 'sffc_time_type', true);
            if (empty($job_type)) {
                $job_type = get_post_meta($post->ID, 'sffc_job_type', true);
            }

            $salary = get_post_meta($post->ID, 'sffc_salary_display', true);
            if (empty($salary)) {
                $salary = get_post_meta($post->ID, 'sffc_estimated_salary', true);
            }

            $summary = get_post_meta($post->ID, 'sffc_job_summary', true);
            if (empty($summary)) {
                $summary = $post->post_excerpt ?: wp_trim_words(wp_strip_all_tags($post->post_content), 36);
            }

            $timestamp = get_post_time('U', true, $post);
            $region_slug = $this->map_job_region_slug($country);

            // Also analyze location text for region
            if (empty($region_slug)) {
                $region_slug = $this->detect_region_from_location($location);
            }

            $job_function_slug = sanitize_title($job_family);
            $job_level_slug = $this->normalize_job_level_slug($job_level);

            $title = get_the_title($post);

            // Start with base keywords
            $keywords = array('job');
            foreach (array($job_function_slug, $region_slug, $job_level_slug) as $token) {
                if (!empty($token)) {
                    $keywords[] = $token;
                }
            }

            // Analyze job content for additional filter keywords
            $content_keywords = $this->analyze_job_content_for_keywords($title, $company, $job_family, $job_level, $location);
            $keywords = array_merge($keywords, $content_keywords);

            $items[] = array(
                'id' => $post->ID,
                'title' => $title,
                'link' => get_permalink($post),
                'excerpt' => wp_trim_words(wp_strip_all_tags($summary), 36),
                'type' => 'job',
                'company' => $company,
                'sector' => $job_family,
                'region' => $region_slug,
                'job_level' => $job_level,
                'job_type' => $job_type,
                'location' => $location,
                'compensation' => $salary,
                'relative_time' => human_time_diff($timestamp, current_time('timestamp')),
                'keywords' => array_unique(array_filter($keywords))
            );
        }

        return $items;
    }

    /**
     * Detect region from location string
     */
    private function detect_region_from_location($location)
    {
        if (empty($location)) {
            return '';
        }

        $location = strtolower($location);

        // North America cities/states
        $north_america = array('new york', 'boston', 'chicago', 'san francisco', 'los angeles', 'houston', 'dallas', 'miami', 'atlanta', 'seattle', 'denver', 'washington', 'toronto', 'vancouver', 'montreal', 'california', 'texas', 'florida', 'illinois', 'massachusetts', 'connecticut', 'new jersey', 'pennsylvania');

        // Europe cities
        $europe = array('london', 'paris', 'frankfurt', 'munich', 'berlin', 'zurich', 'geneva', 'amsterdam', 'dublin', 'milan', 'madrid', 'barcelona', 'stockholm', 'copenhagen', 'oslo', 'brussels', 'luxembourg', 'edinburgh', 'manchester', 'birmingham');

        // Asia Pacific cities
        $asia_pacific = array('singapore', 'hong kong', 'shanghai', 'beijing', 'tokyo', 'sydney', 'melbourne', 'mumbai', 'bangalore', 'delhi', 'seoul', 'taipei', 'jakarta', 'bangkok', 'kuala lumpur', 'auckland', 'dubai', 'abu dhabi');

        foreach ($north_america as $city) {
            if (strpos($location, $city) !== false) {
                return 'north-america';
            }
        }

        foreach ($europe as $city) {
            if (strpos($location, $city) !== false) {
                return 'europe';
            }
        }

        foreach ($asia_pacific as $city) {
            if (strpos($location, $city) !== false) {
                return 'asia-pacific';
            }
        }

        return '';
    }

    /**
     * Map a country to a broader hiring region slug
     */
    private function map_job_region_slug($country)
    {
        if (empty($country)) {
            return '';
        }

        $country = strtolower(trim($country));

        $north_america = array('united states', 'united states of america', 'usa', 'canada');
        $europe = array('united kingdom', 'uk', 'england', 'scotland', 'germany', 'france', 'switzerland', 'spain', 'italy', 'belgium', 'ireland', 'netherlands', 'luxembourg', 'sweden', 'denmark', 'norway', 'finland');
        $asia_pacific = array('singapore', 'hong kong', 'china', 'japan', 'australia', 'new zealand', 'india', 'south korea', 'united arab emirates');

        if (in_array($country, $north_america, true)) {
            return 'north-america';
        }

        if (in_array($country, $europe, true)) {
            return 'europe';
        }

        if (in_array($country, $asia_pacific, true)) {
            return 'asia-pacific';
        }

        return '';
    }

    /**
     * Map job level labels to a consistent slug
     */
    private function normalize_job_level_slug($job_level)
    {
        if (empty($job_level)) {
            return '';
        }

        $job_level = strtolower($job_level);

        if (false !== strpos($job_level, 'associate')) {
            return 'associate';
        }

        if (false !== strpos($job_level, 'vice') || false !== strpos($job_level, 'vp')) {
            return 'vice-president';
        }

        if (false !== strpos($job_level, 'director')) {
            return 'director';
        }

        if (false !== strpos($job_level, 'partner') || false !== strpos($job_level, 'managing')) {
            return 'partner';
        }

        return sanitize_title($job_level);
    }

    /**
     * Determine if an item should be flagged as priority
     */
    private function is_priority_item($type, $value_numeric, $sector)
    {
        if ('deal' === $type && $value_numeric > 500000000) {
            return true;
        }

        $hot_sectors = array('technology', 'infrastructure', 'healthcare');
        if (!empty($sector)) {
            $sector_slug = sanitize_title($sector);
            if (in_array($sector_slug, $hot_sectors, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build template-based insights for initial render
     */
    private function build_template_insights($items)
    {
        $total = count($items);
        $deal_count = count(array_filter($items, function ($item) {
            return $item['type'] === 'deal';
        }));
        $news_count = $total - $deal_count;

        $sector_counts = array();
        $values = array();

        foreach ($items as $item) {
            if (!empty($item['sector'])) {
                $label = $item['sector'];
                $sector_counts[$label] = isset($sector_counts[$label]) ? $sector_counts[$label] + 1 : 1;
            }

            if (!empty($item['deal_value_numeric'])) {
                $values[] = (float) $item['deal_value_numeric'];
            }
        }

        arsort($sector_counts);
        $top_sector = key($sector_counts);
        $avg_value = !empty($values) ? array_sum($values) / count($values) : 0;

        $context = array(
            'sector' => $top_sector ?: __('private equity', 'senna-finance'),
            'percentage' => $total ? max(5, round(($deal_count / max($total, 1)) * 100)) : 0,
            'number' => (string) $total
        );

        $summary = $this->template_library
            ? $this->template_library->get_template('market', 'market_update', $context)
            : __('Live coverage updated across private equity.', 'senna-finance');

        $metrics = array(
            array(
                'label' => __('Live Headlines', 'senna-finance'),
                'value' => $total
            ),
            array(
                'label' => __('Deal Flow', 'senna-finance'),
                'value' => $deal_count
            ),
            array(
                'label' => __('News Momentum', 'senna-finance'),
                'value' => $news_count
            ),
            array(
                'label' => __('Avg Deal Size', 'senna-finance'),
                'value' => $avg_value ? $this->format_deal_value_display($avg_value) : __('Undisclosed', 'senna-finance')
            )
        );

        $highlights = array();
        if ($top_sector) {
            $highlights[] = sprintf(__('Sector momentum: %s dominates the chatter.', 'senna-finance'), $top_sector);
        }
        if ($avg_value) {
            $highlights[] = sprintf(__('Average disclosed deal lands near %s.', 'senna-finance'), $this->format_deal_value_display($avg_value));
        }
        if ($deal_count > $news_count) {
            $highlights[] = __('Deal flow is outrunning narrative coverage.', 'senna-finance');
        } else {
            $highlights[] = __('Editorial coverage is setting the tone on today’s feed.', 'senna-finance');
        }

        return array(
            'summary' => $summary,
            'metrics' => $metrics,
            'highlights' => $highlights,
            'timestamp' => current_time('timestamp'),
            'source' => 'templates'
        );
    }

    /**
     * Generate analytics payload using Claude when available
     */
    private function generate_analytics_payload($items)
    {
        $template_payload = $this->build_template_insights($items);

        if (!$this->claude || !$this->claude->is_available()) {
            return $template_payload;
        }

        $prompt = $this->build_claude_prompt($items);
        if (!$prompt) {
            return $template_payload;
        }

        $response = $this->claude->send_message($prompt, array(), 'market');
        if (empty($response['response'])) {
            return $template_payload;
        }

        $structured = $this->structure_claude_response($response['response']);
        if (empty($structured)) {
            return $template_payload;
        }

        return array(
            'summary' => $structured['summary'],
            'metrics' => $template_payload['metrics'],
            'highlights' => !empty($structured['highlights']) ? $structured['highlights'] : $template_payload['highlights'],
            'timestamp' => current_time('timestamp'),
            'source' => 'claude'
        );
    }

    /**
     * Create Claude prompt from feed items
     */
    private function build_claude_prompt($items)
    {
        if (empty($items)) {
            return '';
        }

        $lines = array();
        $slice = array_slice($items, 0, 8);
        foreach ($slice as $item) {
            $lines[] = sprintf(
                '%s | Type: %s | Sector: %s | Region: %s | Value: %s | Summary: %s',
                $item['company'] ?: $item['title'],
                ucfirst($item['type']),
                $item['sector'] ?: 'General',
                $item['region'] ?: 'Global',
                $item['deal_value'] ?: 'Undisclosed',
                $item['excerpt']
            );
        }

        $prompt = "You are a senior private equity analyst summarizing a social-style news feed for private equity professionals, investment teams, operating teams, associates, VPs, directors, principals, partners, and adjacent buy-side decision-makers."
            . "\nReview the updates below and respond with a crisp market pulse."
            . "\n\nFormat your response exactly as:\n"
            . "SUMMARY: <one professional sentence without emojis>\n"
            . "HIGHLIGHTS:\n- <insight 1>\n- <insight 2>\n- <insight 3>\n"
            . "RECOMMENDATION: <short imperative action>\n"
            . "All insights must reference sectors, capital flows, hiring signals, strategic risk, or operating implications within the feed."
            . "\nWrite for experienced professionals who care about capital allocation, team priorities, market timing, strategic positioning, and regional hiring context."
            . "\nDo not add any extra sections or headings.";

        $prompt .= "\n\nFeed items:\n- " . implode("\n- ", $lines);

        return $prompt;
    }

    /**
     * Parse Claude output into structured data
     */
    private function structure_claude_response($text)
    {
        if (empty($text)) {
            return array();
        }

        $summary = '';
        $highlights = array();
        $recommendation = '';

        $lines = preg_split('/\r?\n/', trim($text));
        foreach ($lines as $line) {
            $normalized = trim($line);
            if (stripos($normalized, 'SUMMARY:') === 0) {
                $summary = trim(substr($normalized, 8));
            } elseif (stripos($normalized, 'RECOMMENDATION:') === 0) {
                $recommendation = trim(substr($normalized, 15));
            } elseif (strpos($normalized, '-') === 0) {
                $highlights[] = trim(ltrim($normalized, '-')); 
            }
        }

        if ($recommendation) {
            $summary = $summary ? $summary . ' ' . $recommendation : $recommendation;
        }

        if (!$summary && !empty($lines[0])) {
            $summary = trim($lines[0]);
        }

        return array(
            'summary' => $summary,
            'highlights' => $highlights
        );
    }

    /**
     * Utility: fetch first non-empty meta value
     */
    private function get_first_meta_value($post_id, $keys)
    {
        foreach ($keys as $key) {
            $value = get_post_meta($post_id, $key, true);
            if (!empty($value)) {
                return $value;
            }
        }

        return '';
    }

    /**
     * Convert various formats into numeric deal value
     */
    private function parse_deal_value($value)
    {
        if (empty($value)) {
            return 0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $clean = preg_replace('/[,\s]/', '', $value);
        if (preg_match('/([0-9]*\.?[0-9]+)(bn|billion|b)/i', $clean, $matches)) {
            return (float) $matches[1] * 1000000000;
        }
        if (preg_match('/([0-9]*\.?[0-9]+)(m|million)/i', $clean, $matches)) {
            return (float) $matches[1] * 1000000;
        }

        if (preg_match('/([0-9]*\.?[0-9]+)/', $clean, $matches)) {
            return (float) $matches[1];
        }

        return 0;
    }

    /**
     * Format numeric value into human-friendly text
     */
    private function format_deal_value_display($value)
    {
        if (empty($value)) {
            return '';
        }

        if ($value >= 1000000000) {
            return '$' . round($value / 1000000000, 1) . 'B';
        }

        if ($value >= 1000000) {
            return '$' . round($value / 1000000, 1) . 'M';
        }

        return '$' . number_format((float) $value, 0);
    }

    /**
     * Retrieve configured user menu entries for the dashboard dropdown
     */
    public function get_user_menu_items($is_logged_in, $context = array())
    {
        $stored = get_option('sffc_dashboard_user_menu_items', array());
        if (empty($stored) || !is_array($stored)) {
            $stored = $this->get_default_user_menu_items();
        }

        $context = wp_parse_args($context, array(
            'profile_url' => '',
            'login_url' => '',
            'logout_url' => '',
            'join_url' => '',
            'dashboard_url' => home_url('/'),
            'saved_url' => '',
            'messages_url' => '',
            'home_url' => home_url('/')
        ));

        $items = $this->map_user_menu_items($stored, $is_logged_in, $context);

        if (empty($items)) {
            $items = $this->map_user_menu_items($this->get_default_user_menu_items(), $is_logged_in, $context);
        }

        return apply_filters('sffc_dashboard_user_menu_items', $items, $is_logged_in, $context);
    }

    /**
     * Default menu blueprint
     */
    public function get_default_user_menu_items()
    {
        return array(
            array(
                'label' => __('Profile', 'senna-finance'),
                'url' => '{{profile_url}}',
                'visibility' => 'logged_in',
                'target' => '_self'
            ),
            array(
                'label' => __('Saved Intelligence', 'senna-finance'),
                'url' => '{{dashboard_url}}#saved',
                'visibility' => 'logged_in',
                'target' => '_self'
            ),
            array(
                'label' => __('Messaging Workspace', 'senna-finance'),
                'url' => '{{messages_url}}',
                'visibility' => 'logged_in',
                'target' => '_blank'
            ),
            array(
                'label' => __('Membership', 'senna-finance'),
                'url' => '{{join_url}}',
                'visibility' => 'logged_in',
                'target' => '_blank'
            ),
            array(
                'label' => __('Join MENA Careers', 'senna-finance'),
                'url' => '{{join_url}}',
                'visibility' => 'logged_out',
                'target' => '_blank'
            ),
            array(
                'label' => __('Sign in', 'senna-finance'),
                'url' => '{{login_url}}',
                'visibility' => 'logged_out',
                'target' => '_self'
            ),
            array(
                'label' => __('Sign out', 'senna-finance'),
                'url' => '{{logout_url}}',
                'visibility' => 'logged_in',
                'target' => '_self'
            )
        );
    }

    private function map_user_menu_items($items, $is_logged_in, $context)
    {
        $mapped = array();

        foreach ((array) $items as $item) {
            if (empty($item['label']) || empty($item['url'])) {
                continue;
            }

            $visibility = isset($item['visibility']) ? $item['visibility'] : 'both';
            if ('logged_in' === $visibility && !$is_logged_in) {
                continue;
            }
            if ('logged_out' === $visibility && $is_logged_in) {
                continue;
            }

            $url = $this->resolve_user_menu_url($item['url'], $context);
            if (empty($url)) {
                continue;
            }

            $mapped[] = array(
                'label' => sanitize_text_field($item['label']),
                'url' => $url,
                'target' => (isset($item['target']) && '_blank' === $item['target']) ? '_blank' : '_self'
            );
        }

        return $mapped;
    }

    private function resolve_user_menu_url($url, $context)
    {
        $url = trim((string) $url);
        if ('' === $url) {
            return '';
        }

        $token_map = array();
        foreach ($context as $key => $value) {
            $token_map['{{' . $key . '}}'] = $value;
        }
        if (!empty($token_map)) {
            $url = strtr($url, $token_map);
        }

        $url = trim($url);
        if ('' === $url) {
            return '';
        }

        if (0 === strpos($url, '/')) {
            $url = home_url($url);
        }

        if (preg_match('/^(mailto|tel):/i', $url)) {
            return esc_url_raw($url, array('mailto', 'tel'));
        }

        if (0 === strpos($url, '#')) {
            return $url;
        }

        return esc_url_raw($url);
    }

    public function get_subscription_plans()
    {
        $stored = get_option('sffc_dashboard_plans', array());
        if (empty($stored) || !is_array($stored)) {
            $stored = $this->get_default_subscription_plans();
        }

        $plans = array();
        foreach ($stored as $plan) {
            if (empty($plan['name'])) {
                continue;
            }
            $plan_entry = array(
                'name' => sanitize_text_field($plan['name']),
                'price' => sanitize_text_field($plan['price'] ?? ''),
                'price_amount' => isset($plan['price_amount']) ? floatval($plan['price_amount']) : '',
                'annual_price' => sanitize_text_field($plan['annual_price'] ?? ''),
                'annual_price_amount' => isset($plan['annual_price_amount']) ? floatval($plan['annual_price_amount']) : '',
                'price_currency' => isset($plan['price_currency']) ? strtoupper(sanitize_text_field($plan['price_currency'])) : get_option('currency_detector_base_currency', 'USD'),
                'matches_per_week' => isset($plan['matches_per_week']) ? max(0, intval($plan['matches_per_week'])) : 0,
                'billing_cycle' => sanitize_text_field($plan['billing_cycle'] ?? ''),
                'annual_billing_cycle' => sanitize_text_field($plan['annual_billing_cycle'] ?? ''),
                'tagline' => sanitize_text_field($plan['tagline'] ?? ''),
                'audience' => sanitize_text_field($plan['audience'] ?? ''),
                'slug' => sanitize_title($plan['slug'] ?? $plan['name']),
                'mp_url' => esc_url_raw($plan['mp_url'] ?? ''),
                'shortcode' => isset($plan['shortcode']) ? wp_kses_post($plan['shortcode']) : '',
                'annual_mp_url' => esc_url_raw($plan['annual_mp_url'] ?? ''),
                'annual_shortcode' => isset($plan['annual_shortcode']) ? wp_kses_post($plan['annual_shortcode']) : '',
                'features' => array(),
                'featured_signup' => !empty($plan['featured_signup']) ? 1 : 0,
                'is_annual' => !empty($plan['is_annual']) ? 1 : 0,
                'recruiter_contact_pricing' => !empty($plan['recruiter_contact_pricing']) ? 1 : 0,
                'signup_path' => (function ($raw_signup_path) {
                    $raw_signup_path = strtolower(trim((string) $raw_signup_path));
                    $signup_path_key = sanitize_key($raw_signup_path);
                    if (in_array($signup_path_key, ['platform', 'mentorship', 'all_access', 'one_contact', 'extra_contacts', 'ongoing_contacts'], true)) {
                        return $signup_path_key;
                    }
                    if (preg_match('/\b(one contact|single contact|one recruiter contact)\b/', $raw_signup_path)) {
                        return 'one_contact';
                    }
                    if (preg_match('/\b(extra contacts|multiple contacts|more contacts)\b/', $raw_signup_path)) {
                        return 'extra_contacts';
                    }
                    if (preg_match('/\b(ongoing contacts|ongoing recruiter contacts|recruiter alerts|ongoing access)\b/', $raw_signup_path)) {
                        return 'ongoing_contacts';
                    }
                    if (
                        preg_match('/\b(all access|premium|full access|everything)\b/', $raw_signup_path)
                        || (
                            preg_match('/\b(recruiter|contact|intro|platform|access)\b/', $raw_signup_path)
                            && preg_match('/\b(cv|resume|linkedin|profile|review|mentorship|support)\b/', $raw_signup_path)
                        )
                    ) {
                        return 'all_access';
                    }
                    if (preg_match('/\b(profile positioning|cv positioning|cv|resume|linkedin|profile|review|mentorship|support)\b/', $raw_signup_path)) {
                        return 'mentorship';
                    }
                    if (preg_match('/\b(recruiter contacts?|recruiter|contacts?|intros?|platform|access only|basic)\b/', $raw_signup_path)) {
                        return 'platform';
                    }
                    return 'platform';
                })($plan['signup_path'] ?? 'platform'),
                'signup_path_configured' => array_key_exists('signup_path', (array) $plan) ? 1 : 0,
                'hero_eyebrow' => sanitize_text_field($plan['hero_eyebrow'] ?? ''),
                'hero_title' => sanitize_text_field($plan['hero_title'] ?? ''),
                'hero_copy' => sanitize_textarea_field($plan['hero_copy'] ?? ''),
                'hero_image_url' => esc_url_raw($plan['hero_image_url'] ?? ''),
                'hero_image_alt' => sanitize_text_field($plan['hero_image_alt'] ?? ''),
                'hero_cta_label' => sanitize_text_field($plan['hero_cta_label'] ?? ''),
                'authority_title' => sanitize_text_field($plan['authority_title'] ?? ''),
                'authority_copy' => sanitize_textarea_field($plan['authority_copy'] ?? ''),
                'social_title' => sanitize_text_field($plan['social_title'] ?? ''),
                'social_copy' => sanitize_textarea_field($plan['social_copy'] ?? ''),
                'social_review_score' => isset($plan['social_review_score']) ? max(0, min(5, floatval($plan['social_review_score']))) : '',
                'social_review_count' => isset($plan['social_review_count']) ? max(0, intval($plan['social_review_count'])) : '',
                'social_reviews' => array(),
                'free_title' => sanitize_text_field($plan['free_title'] ?? ''),
                'free_copy' => sanitize_textarea_field($plan['free_copy'] ?? ''),
                'category_title' => sanitize_text_field($plan['category_title'] ?? ''),
                'category_copy' => sanitize_textarea_field($plan['category_copy'] ?? ''),
                'scarcity_title' => sanitize_text_field($plan['scarcity_title'] ?? ''),
                'scarcity_copy' => sanitize_textarea_field($plan['scarcity_copy'] ?? ''),
                'now_title' => sanitize_text_field($plan['now_title'] ?? ''),
                'now_copy' => sanitize_textarea_field($plan['now_copy'] ?? ''),
                'other_plans_label' => sanitize_text_field($plan['other_plans_label'] ?? ''),
                'back_label' => sanitize_text_field($plan['back_label'] ?? ''),
                'onboarding_help_needs' => $this->normalize_guided_plan_tag_list($plan['onboarding_help_needs'] ?? []),
                'onboarding_commitment_modes' => $this->normalize_guided_plan_tag_list($plan['onboarding_commitment_modes'] ?? []),
                'onboarding_pitch' => sanitize_textarea_field($plan['onboarding_pitch'] ?? ''),
            );
            $plan_entry['onboarding_help_needs'] = $this->resolve_guided_plan_help_needs($plan_entry);
            $plan_entry['onboarding_commitment_modes'] = $this->resolve_guided_plan_commitment_modes($plan_entry);
            if ($plan_entry['onboarding_pitch'] === '') {
                $plan_entry['onboarding_pitch'] = $plan_entry['tagline'] ?: $plan_entry['audience'];
            }

            $features = $plan['features'] ?? array();
            if (is_string($features)) {
                $features = preg_split("/\r?\n/", $features);
            }
            if (is_array($features)) {
                foreach ($features as $feature) {
                    $feature = trim(wp_strip_all_tags($feature));
                    if ($feature !== '') {
                        $plan_entry['features'][] = $feature;
                    }
                }
            }

            $social_reviews = $plan['social_reviews'] ?? array();
            if (is_string($social_reviews)) {
                $social_reviews = preg_split("/\r?\n/", $social_reviews);
            }
            if (is_array($social_reviews)) {
                foreach ($social_reviews as $review) {
                    if (is_array($review)) {
                        $text = trim(wp_strip_all_tags($review['text'] ?? ''));
                        if ($text === '') {
                            continue;
                        }

                        $plan_entry['social_reviews'][] = array(
                            'text' => $text,
                            'first_name' => sanitize_text_field($review['first_name'] ?? ''),
                            'last_name' => sanitize_text_field($review['last_name'] ?? ''),
                            'rating' => isset($review['rating']) ? max(0, min(5, floatval($review['rating']))) : 5,
                        );
                        continue;
                    }

                    $review = trim(wp_strip_all_tags($review));
                    if ($review === '') {
                        continue;
                    }

                    $parts = array_map('trim', explode('|', $review));
                    $plan_entry['social_reviews'][] = array(
                        'text' => isset($parts[0]) ? $parts[0] : '',
                        'first_name' => isset($parts[1]) ? sanitize_text_field($parts[1]) : '',
                        'last_name' => isset($parts[2]) ? sanitize_text_field($parts[2]) : '',
                        'rating' => isset($parts[3]) ? max(0, min(5, floatval($parts[3]))) : 5,
                    );
                }
            }

            $legacy_annual_only = !empty($plan_entry['is_annual']);
            $has_annual_variant = ((float) $plan_entry['annual_price_amount'] > 0)
                || trim((string) $plan_entry['annual_price']) !== ''
                || trim((string) $plan_entry['annual_shortcode']) !== ''
                || trim((string) $plan_entry['annual_mp_url']) !== '';

            if (!$legacy_annual_only || $has_annual_variant) {
                $monthly_entry = $plan_entry;
                $monthly_entry['is_annual'] = 0;
                $plans[] = $this->hydrate_subscription_plan_copy($monthly_entry);
            }

            if ($has_annual_variant || $legacy_annual_only) {
                $annual_entry = $plan_entry;
                $annual_entry['is_annual'] = 1;
                $annual_entry['slug'] = sanitize_title(($plan['slug'] ?? $plan['name']) . '-annual');
                $annual_entry['price'] = $has_annual_variant ? $plan_entry['annual_price'] : $plan_entry['price'];
                $annual_entry['price_amount'] = $has_annual_variant && (float) $plan_entry['annual_price_amount'] > 0 ? (float) $plan_entry['annual_price_amount'] : (float) $plan_entry['price_amount'];
                $annual_entry['billing_cycle'] = $has_annual_variant && trim((string) $plan_entry['annual_billing_cycle']) !== '' ? $plan_entry['annual_billing_cycle'] : ($plan_entry['billing_cycle'] ?: __('per year', 'senna-finance'));
                $annual_entry['shortcode'] = $has_annual_variant && trim((string) $plan_entry['annual_shortcode']) !== '' ? $plan_entry['annual_shortcode'] : $plan_entry['shortcode'];
                $annual_entry['mp_url'] = $has_annual_variant && trim((string) $plan_entry['annual_mp_url']) !== '' ? $plan_entry['annual_mp_url'] : $plan_entry['mp_url'];
                $plans[] = $this->hydrate_subscription_plan_copy($annual_entry);
            }
        }

        if (empty($plans)) {
            $plans = $this->get_default_subscription_plans();
        }

        foreach ($plans as $index => $plan_entry) {
            $plans[$index] = $this->hydrate_subscription_plan_copy($plan_entry);
        }

        return $plans;
    }

    private function hydrate_subscription_plan_copy($plan_entry)
    {
        $price_label = $plan_entry['price'] ?: '';
        $tagline = $plan_entry['tagline'] ?: '';
        $audience = $plan_entry['audience'] ?: '';
        $first_feature = !empty($plan_entry['features'][0]) ? $plan_entry['features'][0] : '';
        $second_feature = !empty($plan_entry['features'][1]) ? $plan_entry['features'][1] : '';

        $plan_entry['hero_eyebrow'] = $plan_entry['hero_eyebrow'] ?: __('MENA Careers Membership', 'senna-finance');
        $plan_entry['hero_title'] = $plan_entry['hero_title'] ?: $plan_entry['name'];
        $plan_entry['hero_copy'] = $plan_entry['hero_copy'] ?: ($tagline ?: __('Choose your membership and continue to secure signup.', 'senna-finance'));
        $plan_entry['hero_image_alt'] = $plan_entry['hero_image_alt'] ?: $plan_entry['name'];
        $plan_entry['hero_cta_label'] = $plan_entry['hero_cta_label'] ?: __('Join now', 'senna-finance');
        $plan_entry['authority_title'] = $plan_entry['authority_title'] ?: __('Authority-backed access', 'senna-finance');
        $plan_entry['authority_copy'] = $plan_entry['authority_copy'] ?: ($first_feature ?: __('Recruiter-posted roles and finance-specific career tools.', 'senna-finance'));
        $plan_entry['social_title'] = $plan_entry['social_title'] ?: __('Trusted by ambitious candidates', 'senna-finance');
        $plan_entry['social_copy'] = $plan_entry['social_copy'] ?: __('Built for candidates using MENA Careers to move faster in finance hiring.', 'senna-finance');
        $plan_entry['social_review_score'] = $plan_entry['social_review_score'] !== '' ? $plan_entry['social_review_score'] : '';
        $plan_entry['social_review_count'] = $plan_entry['social_review_count'] !== '' ? $plan_entry['social_review_count'] : '';
        $plan_entry['social_reviews'] = !empty($plan_entry['social_reviews']) ? $plan_entry['social_reviews'] : array();
        $plan_entry['free_title'] = $plan_entry['free_title'] ?: __('Free application materials', 'senna-finance');
        $plan_entry['free_copy'] = $plan_entry['free_copy'] ?: ($second_feature ?: __('Use this space to describe the free item included with this plan.', 'senna-finance'));
        $plan_entry['category_title'] = $plan_entry['category_title'] ?: __('All-in-one membership', 'senna-finance');
        $plan_entry['category_copy'] = $plan_entry['category_copy'] ?: ($audience ?: __('Roles, applications, support, and career tools in one package.', 'senna-finance'));
        $plan_entry['scarcity_title'] = $plan_entry['scarcity_title'] ?: __('Current pricing is available now', 'senna-finance');
        $plan_entry['scarcity_copy'] = $plan_entry['scarcity_copy'] ?: __('Use this tile for a real deadline, bonus window, or limited pricing message.', 'senna-finance');
        $plan_entry['now_title'] = $plan_entry['now_title'] ?: __('Start immediately', 'senna-finance');
        $plan_entry['now_copy'] = $plan_entry['now_copy'] ?: ($price_label ? sprintf(__('Choose this plan and continue with %s.', 'senna-finance'), $price_label) : __('Choose this plan and continue to secure signup.', 'senna-finance'));
        $plan_entry['other_plans_label'] = $plan_entry['other_plans_label'] ?: __('Other plans', 'senna-finance');
        $plan_entry['back_label'] = $plan_entry['back_label'] ?: __('Back to plans', 'senna-finance');

        return $plan_entry;
    }

    private function normalize_guided_plan_tag_list($value)
    {
        $values = [];

        if (is_string($value)) {
            $values = preg_split('/[\r\n,|]+/', $value);
        } elseif (is_array($value)) {
            $values = $value;
        }

        $normalized = array();
        foreach ((array) $values as $entry) {
            $entry = sanitize_key((string) $entry);
            if ($entry === '') {
                continue;
            }
            $normalized[$entry] = $entry;
        }

        return array_values($normalized);
    }

    private function resolve_guided_plan_help_needs(array $plan_entry)
    {
        $tags = $this->normalize_guided_plan_tag_list($plan_entry['onboarding_help_needs'] ?? []);
        if (!empty($tags)) {
            return $tags;
        }

        $text = strtolower(
            trim(
                implode(
                    ' ',
                    array_filter([
                        (string) ($plan_entry['name'] ?? ''),
                        (string) ($plan_entry['tagline'] ?? ''),
                        (string) ($plan_entry['audience'] ?? ''),
                        implode(' ', isset($plan_entry['features']) && is_array($plan_entry['features']) ? $plan_entry['features'] : []),
                    ])
                )
            )
        );

        $resolved = array();
        if (preg_match('/\b(cv|cover letter|interview|application pack|hiring guide|application materials|tailor)\b/i', $text)) {
            $resolved[] = 'fix_cv_before_apply';
            $resolved[] = 'land_interviews';
        }
        if (preg_match('/\b(role matching|live roles|role fit|search|opportunities|roles)\b/i', $text)) {
            $resolved[] = 'attract_more_roles';
        }
        if (preg_match('/\b(recruiter|outreach|network|referral|introductions?)\b/i', $text)) {
            $resolved[] = 'get_recruiter_attention';
            $resolved[] = 'attract_more_roles';
            $resolved[] = 'land_interviews';
        }
        if (preg_match('/\b(ats|cv review|scan|keyword)\b/i', $text)) {
            $resolved[] = 'fix_cv_before_apply';
        }

        if (empty($resolved)) {
            $resolved[] = 'land_interviews';
        }

        return array_values(array_unique($resolved));
    }

    private function resolve_guided_plan_commitment_modes(array $plan_entry)
    {
        $tags = $this->normalize_guided_plan_tag_list($plan_entry['onboarding_commitment_modes'] ?? []);
        if (!empty($tags)) {
            return $tags;
        }

        $text = strtolower(
            trim(
                implode(
                    ' ',
                    array_filter([
                        (string) ($plan_entry['name'] ?? ''),
                        (string) ($plan_entry['tagline'] ?? ''),
                        (string) ($plan_entry['audience'] ?? ''),
                        implode(' ', isset($plan_entry['features']) && is_array($plan_entry['features']) ? $plan_entry['features'] : []),
                    ])
                )
            )
        );

        $resolved = array();
        if (preg_match('/\b(monthly|annual|membership|live roles|outreach|search strategy|tracking|career report|ongoing)\b/i', $text)) {
            $resolved[] = 'ongoing';
        }
        if (preg_match('/\b(application pack|cv template|cover letter|interview questions|hiring guide|one time|one-off|single)\b/i', $text)) {
            $resolved[] = 'one_time';
        }

        if (empty($resolved)) {
            $resolved[] = !empty($plan_entry['matches_per_week']) || (!empty($plan_entry['price_amount']) && (float) $plan_entry['price_amount'] > 25)
                ? 'ongoing'
                : 'one_time';
        }

        return array_values(array_unique($resolved));
    }

    public function get_default_subscription_plans()
    {
        return array(
            array(
                'name' => __('Insights Membership', 'senna-finance'),
                'price' => __('£29.99 / month', 'senna-finance'),
                'price_amount' => 29.99,
                'price_currency' => 'GBP',
                'matches_per_week' => 10,
                'billing_cycle' => __('per month', 'senna-finance'),
                'tagline' => __('Premium analysis and investment intelligence without career add-ons.', 'senna-finance'),
                'audience' => __('For professionals who come for the content, not the coaching.', 'senna-finance'),
                'slug' => 'insights',
                'mp_url' => 'https://joinsenna.com/memberships/insights/',
                'features' => array(
                    __('Daily expert-curated insights across PE, HF, and AM.', 'senna-finance'),
                    __('Deal flow coverage, trend breakdowns, and templates for analysis.', 'senna-finance'),
                    __('Real-time alerts plus full access to the insights archive.', 'senna-finance')
                ),
                'shortcode' => '[mepr-membership-registration-form id="101"]'
            ),
            array(
                'name' => __('Career Membership', 'senna-finance'),
                'price' => __('£49.99 / month', 'senna-finance'),
                'price_amount' => 49.99,
                'price_currency' => 'GBP',
                'matches_per_week' => 15,
                'billing_cycle' => __('per month', 'senna-finance'),
                'tagline' => __('All the tools to break into or advance in private equity.', 'senna-finance'),
                'audience' => __('For professionals who want the career advantage, not just the news.', 'senna-finance'),
                'slug' => 'career',
                'mp_url' => 'https://joinsenna.com/memberships/career/',
                'features' => array(
                    __('Smart CV analysis with tailored guidance.', 'senna-finance'),
                    __('Personalised job matching & AI interview preparation.', 'senna-finance'),
                    __('Skill-gap detection plus premium templates and guides.', 'senna-finance')
                ),
                'shortcode' => '[mepr-membership-registration-form id="102"]'
            ),
            array(
                'name' => __('Elite Membership', 'senna-finance'),
                'price' => __('£69.99 / month', 'senna-finance'),
                'price_amount' => 69.99,
                'price_currency' => 'GBP',
                'matches_per_week' => 20,
                'billing_cycle' => __('per month', 'senna-finance'),
                'tagline' => __('Insights + career tools + expert coaching in one membership.', 'senna-finance'),
                'audience' => __('For leaders who want everything: research, career acceleration, and coaching.', 'senna-finance'),
                'slug' => 'elite',
                'mp_url' => 'https://joinsenna.com/memberships/elite/',
                'features' => array(
                    __('Includes Insights + Career benefits.', 'senna-finance'),
                    __('Monthly 1:1 executive coaching and recruiter outreach.', 'senna-finance'),
                    __('Priority support, unlimited interview practice, and roadmap planning.', 'senna-finance')
                ),
                'shortcode' => '[mepr-membership-registration-form id="103"]'
            )
        );
    }

    /**
     * SVG icon helper for template usage
     */
    public static function get_icon($name)
    {
        $icons = array(
            'bell' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a7 7 0 00-7 7v3.58l-1.31 2.62A1 1 0 004.6 16H19.4a1 1 0 00.91-1.45L19 12.58V9a7 7 0 00-7-7zm0 20a3 3 0 002.83-2H9.17A3 3 0 0012 22z"/></svg>',
            'spark' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l1.76 5.41H19l-4.53 3.29 1.73 5.3L12 13.94l-4.2 2.99 1.73-5.3L5 7.41h5.24z" fill="currentColor"/></svg>',
            'pulse' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 13h3l2-6 4 12 3-9 2 3h4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'chevron' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 10l4 4 4-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'share' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 5a3 3 0 10-2.83 2.99l-6.16 3.3a3 3 0 10.41 1.5l5.75-3.08A3 3 0 1018 5z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'bookmark' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 4a2 2 0 012-2h8a2 2 0 012 2v18l-7-4-7 4z"/></svg>'
        );

        return isset($icons[$name]) ? $icons[$name] : '';
    }

    /**
     * Build demo messages for non-logged-in users
     */
    private function build_demo_messages()
    {
        $plugin_url = plugin_dir_url(dirname(__FILE__));
        
        // Get today's date dynamically
        $today = date('j F Y');
        $yesterday = date('j F Y', strtotime('-1 day'));
        $two_days_ago = date('j F Y', strtotime('-2 days'));
        
        // Create a comprehensive library of messages
        $all_messages = array(
            array(
                'id' => 'demo-msg-1',
                'type' => 'message',
                'title' => __('Market News: Daily PE Brief - 21 November 2025', 'senna-finance'),
                'excerpt' => __('Apollo completes $15B fund raise, KKR targets Asian growth markets, Blackstone exits retail portfolio...', 'senna-finance'),
                'sender' => 'Amy B.',
                'sender_full' => 'Amy Bradford',
                'avatar' => $plugin_url . 'assets/images/avatar1.png',
                'relative_time' => '2 hours',
                'message_category' => 'insights',
                'category' => 'insights',
                'status' => 'unread',
                'link' => '#',
                'company' => '',
                'sector' => '',
                'region' => '',
                'keywords' => array('message', 'market-news')
            ),
            array(
                'id' => 'demo-msg-2',
                'type' => 'message',
                'title' => __('Research Report Released: Private Equity Moves to Watch', 'senna-finance'),
                'excerpt' => __('Our latest research identifies 12 upcoming deals in the tech and healthcare sectors with combined value exceeding $45B...', 'senna-finance'),
                'sender' => 'Michael K.',
                'sender_full' => 'Michael Kingston',
                'avatar' => $plugin_url . 'assets/images/avatar5.png',
                'relative_time' => '5 hours',
                'message_category' => 'research',
                'category' => 'research',
                'status' => 'unread',
                'link' => '#',
                'company' => '',
                'sector' => '',
                'region' => '',
                'keywords' => array('message', 'research')
            ),
            array(
                'id' => 'demo-msg-3',
                'type' => 'message',
                'title' => __('Opportunities: Latest Roles in PE - Senior Associate & VP Positions', 'senna-finance'),
                'excerpt' => __('3 new positions matching your profile: Carlyle Group (London), Vista Equity (NYC), Silver Lake (Menlo Park)...', 'senna-finance'),
                'sender' => 'Sarah L.',
                'sender_full' => 'Sarah Lancaster',
                'avatar' => $plugin_url . 'assets/images/avatar6.png',
                'relative_time' => '8 hours',
                'message_category' => 'jobs',
                'category' => 'jobs',
                'status' => 'read',
                'link' => '#',
                'company' => '',
                'sector' => '',
                'region' => '',
                'keywords' => array('message', 'opportunities')
            ),
            array(
                'id' => 'demo-msg-4',
                'type' => 'message',
                'title' => __('Events: Private Equity Summit London - December 2025', 'senna-finance'),
                'excerpt' => __('Join 500+ PE professionals at the annual summit. Keynotes from Blackstone CEO, Apollo President. Early bird ends Friday...', 'senna-finance'),
                'sender' => 'David M.',
                'sender_full' => 'David Mitchell',
                'avatar' => $plugin_url . 'assets/images/avatar7.png',
                'relative_time' => '1 day',
                'message_category' => 'events',
                'category' => 'events',
                'status' => 'read',
                'link' => '#',
                'company' => '',
                'sector' => '',
                'region' => '',
                'keywords' => array('message', 'events')
            ),
            array(
                'id' => 'demo-msg-5',
                'type' => 'message',
                'title' => __('Deal Alert: TPG in Advanced Talks for $8B Healthcare Platform', 'senna-finance'),
                'excerpt' => __('Exclusive: TPG Capital nearing agreement to acquire MedTech Solutions at 14x EBITDA multiple. Due diligence entering final phase...', 'senna-finance'),
                'sender' => 'James W.',
                'sender_full' => 'James Wilson',
                'avatar' => $plugin_url . 'assets/images/avatar8.png',
                'relative_time' => '1 day',
                'message_category' => 'deals',
                'category' => 'deals',
                'status' => 'read',
                'link' => '#',
                'company' => '',
                'sector' => '',
                'region' => '',
                'keywords' => array('message', 'deal-alert')
            ),
            array(
                'id' => 'demo-msg-6',
                'type' => 'message',
                'title' => __('Interview Prep: Goldman Sachs PE Division - Your Session Tomorrow', 'senna-finance'),
                'excerpt' => __('Reminder: Your mock interview session is scheduled for 3 PM EST. We\'ve prepared case studies on recent GS portfolio companies...', 'senna-finance'),
                'sender' => 'Emma R.',
                'sender_full' => 'Emma Richardson',
                'avatar' => $plugin_url . 'assets/images/avatar9.png',
                'relative_time' => '2 days',
                'message_category' => 'coaching',
                'category' => 'coaching',
                'status' => 'read',
                'link' => '#',
                'company' => '',
                'sector' => '',
                'region' => '',
                'keywords' => array('message', 'coaching')
            ),
            array(
                'id' => 'demo-msg-7',
                'type' => 'message',
                'title' => __('Portfolio Update: Q3 Performance Review & Outlook', 'senna-finance'),
                'excerpt' => __('Your tracked portfolio companies showed 23% average revenue growth. Top performers: Datadog, Snowflake, Stripe. Full report attached...', 'senna-finance'),
                'sender' => 'Robert C.',
                'sender_full' => 'Robert Chen',
                'avatar' => $plugin_url . 'assets/images/avatar10.png',
                'relative_time' => '3 days',
                'message_category' => 'portfolio',
                'category' => 'portfolio',
                'status' => 'read',
                'link' => '#',
                'company' => '',
                'sector' => '',
                'region' => '',
                'keywords' => array('message', 'portfolio')
            ),
            array(
                'id' => 'demo-msg-8',
                'type' => 'message',
                'title' => __('Networking: Connect with PE Partners from Top Firms', 'senna-finance'),
                'excerpt' => __('5 Partners have viewed your profile this week. Premium members can see who and connect directly. Upgrade to unlock...', 'senna-finance'),
                'sender' => 'Lisa T.',
                'sender_full' => 'Lisa Thompson',
                'avatar' => $plugin_url . 'assets/images/avatarChris.png',
                'relative_time' => '4 days',
                'message_category' => 'network',
                'category' => 'network',
                'status' => 'read',
                'link' => '#',
                'company' => '',
                'sector' => '',
                'region' => '',
                'keywords' => array('message', 'networking')
            )
        );
        
        // Shuffle all messages for randomization
        shuffle($all_messages);
        
        // Select 8 random messages to display
        $selected_messages = array_slice($all_messages, 0, 8);
        
        // Sort by time to maintain realistic order (newest first)
        usort($selected_messages, function($a, $b) {
            // Convert time strings to timestamps for comparison
            $times = array(
                'minutes ago' => 1,
                'hour ago' => 2,
                'hours ago' => 3,
                'day ago' => 4,
                'days ago' => 5,
                'week ago' => 6
            );
            
            $a_weight = 3; // default for hours
            $b_weight = 3;
            
            foreach ($times as $pattern => $weight) {
                if (strpos($a['time'], $pattern) !== false) $a_weight = $weight;
                if (strpos($b['time'], $pattern) !== false) $b_weight = $weight;
            }
            
            return $a_weight - $b_weight;
        });
        
        // Return the formatted messages for template use
        return $selected_messages;
    }
    
    /**
     * Handle dashboard filter AJAX requests
     */
    public function ajax_dashboard_filter()
    {
        if (!check_ajax_referer('sffc_public_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'senna-finance')]);
        }

        $tab = isset($_POST['tab']) ? sanitize_text_field($_POST['tab']) : 'insights';
        $filters = isset($_POST['filters']) ? $_POST['filters'] : [];
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = 20;
        
        // Sanitize filters
        $clean_filters = [];
        if (is_array($filters)) {
            foreach ($filters as $key => $value) {
                $clean_filters[sanitize_text_field($key)] = sanitize_text_field($value);
            }
        }
        
        $args = [
            'posts_per_page' => $per_page,
            'paged' => $page,
            'post_status' => 'publish',
            'meta_query' => [],
            'orderby' => 'date',
            'order' => 'DESC'
        ];
        
        // Configure query based on tab
        switch ($tab) {
            case 'insights':
                $args['post_type'] = ['sffc_pe_news', 'sffc_pe_deal', 'post'];
                break;
            case 'jobs':
                $args['post_type'] = ['sffc_pe_job', 'job_listing'];
                break;
            case 'research':
                $args['post_type'] = ['sffc_pe_news', 'post'];
                $args['meta_query'][] = [
                    'key' => '_category',
                    'value' => 'research',
                    'compare' => 'LIKE'
                ];
                break;
            case 'alerts':
                $args['post_type'] = ['sffc_pe_news', 'sffc_pe_deal'];
                $args['meta_query'][] = [
                    'key' => '_priority',
                    'value' => 'high',
                    'compare' => '='
                ];
                break;
        }
        
        // Build content search patterns for filters (matching analyze_content_for_keywords logic)
        $filter_content_patterns = [
            'deal_types' => [
                'fund-raises' => 'fund raise|fundraise|raised|raises|capital raise|fundraising|closes fund|new fund|fund close|committed|lp commit',
                'ma' => 'm&a|merger|acquisition|acquires|acquired|merges|merged|takeover|buyout deal|buys|bought|purchase',
                'exits' => 'exit|exits|ipo|initial public offering|goes public|listing|listed|divest|divestiture|sells stake|sold stake|secondary sale|sponsor exit',
                'regulatory' => 'regulatory|regulation|compliance|sec |ftc |antitrust|investigation|enforcement|fine|penalty|settlement|lawsuit|legal',
                'personnel' => 'personnel|hire|hires|hired|appoint|appointed|appointment|promotion|promoted|joins|joined|departs|departure|leaves|resigns|ceo|cfo|cio|partner|managing director|head of'
            ],
            'regions' => [
                'north-america' => 'north america|united states|u\\.s\\.|usa|american|canada|canadian|mexico|new york|california|texas|florida|chicago|boston|san francisco|los angeles|toronto|vancouver',
                'europe' => 'europe|european|uk |u\\.k\\.|united kingdom|britain|british|london|germany|german|france|french|paris|italy|italian|spain|spanish|netherlands|dutch|sweden|nordic|switzerland|swiss',
                'asia-pacific' => 'asia|asian|apac|asia-pacific|asia pacific|china|chinese|japan|japanese|india|indian|singapore|hong kong|australia|australian|korea|korean|southeast asia|taiwan|vietnam'
            ],
            'sectors' => [
                'buyout' => 'buyout|buy-out|lbo|leveraged buyout|private equity buyout|pe buyout|control investment|majority stake|take-private|going private',
                'growth-equity' => 'growth equity|growth capital|growth investment|growth-stage|expansion capital|minority investment|minority stake|growth fund',
                'venture-capital' => 'venture capital|venture|vc |seed|series a|series b|series c|series d|early-stage|startup|start-up|tech investment',
                'real-estate' => 'real estate|property|reit|real-estate|commercial property|residential|office building|retail property|industrial property|logistics property'
            ]
        ];

        // Apply content-based filters via posts_where
        $content_filters = [];
        if (!empty($clean_filters)) {
            foreach ($clean_filters as $filter_key => $filter_value) {
                if (empty($filter_value)) continue;

                // Check if we have content patterns for this filter
                if (isset($filter_content_patterns[$filter_key][$filter_value])) {
                    $content_filters[] = $filter_content_patterns[$filter_key][$filter_value];
                }

                // Also search in meta fields as fallback
                $args['meta_query'][] = [
                    'relation' => 'OR',
                    [
                        'key' => '_' . $filter_key,
                        'value' => $filter_value,
                        'compare' => 'LIKE'
                    ],
                    [
                        'key' => '_category',
                        'value' => $filter_value,
                        'compare' => 'LIKE'
                    ],
                    [
                        'key' => '_tags',
                        'value' => $filter_value,
                        'compare' => 'LIKE'
                    ],
                    [
                        'key' => '_keywords',
                        'value' => $filter_value,
                        'compare' => 'LIKE'
                    ],
                    [
                        'key' => 'news_deal_type',
                        'value' => $filter_value,
                        'compare' => 'LIKE'
                    ],
                    [
                        'key' => 'deal_category',
                        'value' => $filter_value,
                        'compare' => 'LIKE'
                    ],
                    [
                        'key' => 'news_region',
                        'value' => $filter_value,
                        'compare' => 'LIKE'
                    ],
                    [
                        'key' => 'deal_region',
                        'value' => $filter_value,
                        'compare' => 'LIKE'
                    ],
                    [
                        'key' => 'news_sector',
                        'value' => $filter_value,
                        'compare' => 'LIKE'
                    ],
                    [
                        'key' => 'deal_sector',
                        'value' => $filter_value,
                        'compare' => 'LIKE'
                    ]
                ];
            }
        }

        // If we have content filters, use OR logic to search in content
        if (!empty($content_filters) && empty($args['meta_query'])) {
            // Clear meta query if no matches expected, rely on content search
            $args['meta_query'] = ['relation' => 'OR'];
        }
        
        // Enhanced search and content-based filtering
        if (!empty($search) || !empty($content_filters)) {
            // Remove default search to use custom query
            unset($args['s']);

            // Add custom search and content filter logic
            add_filter('posts_where', function($where) use ($search, $content_filters) {
                global $wpdb;
                $conditions = [];

                // Text search conditions
                if (!empty($search)) {
                    $search_escaped = esc_sql($wpdb->esc_like($search));
                    $conditions[] = "(
                        {$wpdb->posts}.post_title LIKE '%{$search_escaped}%'
                        OR {$wpdb->posts}.post_content LIKE '%{$search_escaped}%'
                        OR {$wpdb->posts}.post_excerpt LIKE '%{$search_escaped}%'
                        OR EXISTS (
                            SELECT * FROM {$wpdb->postmeta}
                            WHERE {$wpdb->postmeta}.post_id = {$wpdb->posts}.ID
                            AND {$wpdb->postmeta}.meta_value LIKE '%{$search_escaped}%'
                        )
                    )";
                }

                // Content filter conditions (search in title, content, excerpt for filter patterns)
                if (!empty($content_filters)) {
                    $filter_conditions = [];
                    foreach ($content_filters as $pattern) {
                        // Convert pipe-separated patterns to individual LIKE conditions
                        $terms = explode('|', $pattern);
                        $term_conditions = [];
                        foreach ($terms as $term) {
                            $term = trim($term);
                            if (empty($term)) continue;
                            $term_escaped = esc_sql($wpdb->esc_like($term));
                            $term_conditions[] = "{$wpdb->posts}.post_title LIKE '%{$term_escaped}%'";
                            $term_conditions[] = "{$wpdb->posts}.post_content LIKE '%{$term_escaped}%'";
                            $term_conditions[] = "{$wpdb->posts}.post_excerpt LIKE '%{$term_escaped}%'";
                        }
                        if (!empty($term_conditions)) {
                            $filter_conditions[] = '(' . implode(' OR ', $term_conditions) . ')';
                        }
                    }
                    if (!empty($filter_conditions)) {
                        // All filter groups must match (AND between filter categories)
                        $conditions[] = '(' . implode(' AND ', $filter_conditions) . ')';
                    }
                }

                if (!empty($conditions)) {
                    $where .= ' AND (' . implode(' AND ', $conditions) . ')';
                }
                return $where;
            }, 10, 1);
        }
        
        $query = new WP_Query($args);
        $results = [];
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                
                // Build result data
                $results[] = [
                    'id' => $post_id,
                    'title' => get_the_title(),
                    'excerpt' => wp_trim_words(get_the_excerpt(), 20),
                    'url' => get_permalink(),
                    'type' => get_post_type(),
                    'date' => get_the_date(),
                    'category' => get_post_meta($post_id, '_category', true),
                    'keywords' => get_post_meta($post_id, '_keywords', true)
                ];
            }
            wp_reset_postdata();
        }
        
        // Remove filter if we added it
        if (!empty($search) || !empty($content_filters)) {
            remove_all_filters('posts_where');
        }
        
        wp_send_json_success([
            'results' => $results,
            'total' => $query->found_posts,
            'page' => $page,
            'total_pages' => $query->max_num_pages,
            'tab' => $tab,
            'query_args' => $args // For debugging
        ]);
    }

    /**
     * Handle load more posts AJAX request
     */
    public function ajax_load_more_posts()
    {
        if (!check_ajax_referer('sffc_public_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'senna-finance')]);
        }

        $tab = isset($_POST['tab']) ? sanitize_text_field($_POST['tab']) : 'insights';
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 6;
        $filters = isset($_POST['filters']) ? $_POST['filters'] : [];
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

        // Sanitize filters
        $clean_filters = [];
        if (is_array($filters)) {
            foreach ($filters as $key => $value) {
                if (!empty($value)) {
                    $clean_filters[sanitize_text_field($key)] = sanitize_text_field($value);
                }
            }
        }

        $args = [
            'posts_per_page' => $per_page,
            'paged' => $page,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC'
        ];

        // Configure query based on tab
        switch ($tab) {
            case 'insights':
                $args['post_type'] = ['sffc_pe_news', 'sffc_pe_deal', 'post'];
                break;
            case 'jobs':
                $args['post_type'] = ['sffc_pe_job', 'sffc_job', 'job_listing'];
                break;
            case 'research':
                $args['post_type'] = ['sffc_pe_news', 'post'];
                $args['meta_query'] = [
                    [
                        'key' => '_category',
                        'value' => 'research',
                        'compare' => 'LIKE'
                    ]
                ];
                break;
            case 'signals':
                $args['post_type'] = ['sffc_pe_news', 'sffc_pe_deal'];
                break;
            default:
                $args['post_type'] = ['sffc_pe_news', 'sffc_pe_deal', 'post'];
        }

        // Apply search if provided
        if (!empty($search)) {
            $args['s'] = $search;
        }

        $query = new WP_Query($args);
        $cards_html = '';
        $results = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $post_type = get_post_type();

                // Generate card HTML based on tab/type
                $card_html = $this->render_feed_card_html($post_id, $tab);
                $cards_html .= $card_html;

                $results[] = [
                    'id' => $post_id,
                    'title' => get_the_title(),
                    'type' => $post_type
                ];
            }
            wp_reset_postdata();
        }

        wp_send_json_success([
            'html' => $cards_html,
            'results' => $results,
            'count' => count($results),
            'total' => $query->found_posts,
            'page' => $page,
            'has_more' => ($page * $per_page) < $query->found_posts,
            'total_pages' => $query->max_num_pages
        ]);
    }

    /**
     * Render a single feed card HTML
     */
    private function render_feed_card_html($post_id, $tab = 'insights')
    {
        $post = get_post($post_id);
        if (!$post) {
            return '';
        }

        $post_type = get_post_type($post_id);
        $title = get_the_title($post_id);
        $excerpt = wp_trim_words(get_the_excerpt($post_id) ?: $post->post_content, 25);
        $permalink = get_permalink($post_id);
        $date = get_the_date('', $post_id);
        $relative_time = human_time_diff(get_the_time('U', $post_id), current_time('timestamp')) . ' ago';

        // Get metadata
        $company = get_post_meta($post_id, 'company', true) ?: get_post_meta($post_id, 'company_name', true);
        $sector = get_post_meta($post_id, 'sector', true) ?: get_post_meta($post_id, '_sector', true);
        $region = get_post_meta($post_id, 'region', true) ?: get_post_meta($post_id, '_region', true);
        $keywords = get_post_meta($post_id, '_keywords', true);
        $source = get_post_meta($post_id, 'source', true);
        $source_url = get_post_meta($post_id, 'source_url', true);

        // Build keywords string for filtering
        $keyword_parts = [];
        if ($sector) $keyword_parts[] = sanitize_title($sector);
        if ($region) $keyword_parts[] = sanitize_title($region);
        if ($keywords) $keyword_parts[] = $keywords;
        $keywords_str = implode(' ', $keyword_parts);

        // Determine card type and styling
        $type_label = 'Insight';
        $type_class = 'news';

        if ($post_type === 'sffc_pe_deal') {
            $type_label = 'Deal';
            $type_class = 'deal';
        } elseif ($post_type === 'sffc_pe_job' || $post_type === 'sffc_job' || $post_type === 'job_listing') {
            $type_label = 'Job';
            $type_class = 'job';
            $company = get_post_meta($post_id, 'company_name', true) ?: get_post_meta($post_id, '_company_name', true);
            $location = get_post_meta($post_id, 'location', true) ?: get_post_meta($post_id, '_job_location', true);
        }

        // Build search index
        $search_index = strtolower($title . ' ' . $excerpt . ' ' . $company . ' ' . $sector);

        ob_start();
        ?>
        <article class="sffc-feed-card"
                 data-post-id="<?php echo esc_attr($post_id); ?>"
                 data-type="<?php echo esc_attr($type_class); ?>"
                 data-keywords="<?php echo esc_attr($keywords_str); ?>"
                 data-search-index="<?php echo esc_attr($search_index); ?>">
            <div class="sffc-feed-card__header">
                <div class="sffc-feed-card__left">
                    <span class="sffc-feed-pill sffc-feed-pill--<?php echo esc_attr($type_class); ?>"><?php echo esc_html($type_label); ?></span>
                    <?php if ($sector) : ?>
                        <span class="sffc-meta-label"><?php echo esc_html($sector); ?></span>
                    <?php endif; ?>
                </div>
                <div class="sffc-feed-card__right">
                    <span class="sffc-meta-label"><?php echo esc_html($relative_time); ?></span>
                </div>
            </div>

            <div class="sffc-feed-card__body">
                <h3 class="sffc-feed-title">
                    <?php if ($source_url) : ?>
                        <a href="<?php echo esc_url($source_url); ?>" target="_blank" rel="noopener"><?php echo esc_html($title); ?></a>
                    <?php else : ?>
                        <?php echo esc_html($title); ?>
                    <?php endif; ?>
                </h3>
                <p class="sffc-feed-excerpt"><?php echo esc_html($excerpt); ?></p>

                <?php if ($company || (isset($location) && $location)) : ?>
                    <div class="sffc-feed-meta">
                        <?php if ($company) : ?>
                            <span class="sffc-meta-item"><?php echo esc_html($company); ?></span>
                        <?php endif; ?>
                        <?php if (isset($location) && $location) : ?>
                            <span class="sffc-meta-item"><?php echo esc_html($location); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="sffc-feed-card__footer">
                <button class="sffc-save-btn" data-post-id="<?php echo esc_attr($post_id); ?>">
                    <span><?php esc_html_e('Save', 'senna-finance'); ?></span>
                </button>
                <?php if ($type_class === 'job') : ?>
                    <button class="sffc-tailor-btn" data-job-id="<?php echo esc_attr($post_id); ?>">
                        <span><?php esc_html_e('Tailor CV', 'senna-finance'); ?></span>
                    </button>
                <?php endif; ?>
                <?php if ($source) : ?>
                    <span class="sffc-source-label"><?php echo esc_html($source); ?></span>
                <?php endif; ?>
            </div>
        </article>
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX: Load user matches for the Matches tab
     */
    public function ajax_nrt_load_matches() {
        // Verify user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Please log in to view matches.']);
        }

        $user_id = get_current_user_id();
        $status = isset($_POST['status']) ? sanitize_key($_POST['status']) : 'pending';
        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;

        // Check if DB class exists
        if (!class_exists('Recruiter_Terminal_DB')) {
            wp_send_json_error(['message' => 'Matches system not available.']);
        }

        // Get matches with brief data
        $matches = Recruiter_Terminal_DB::get_user_matches_with_briefs($user_id, array(
            'status' => $status,
            'limit'  => $per_page,
            'offset' => $offset,
        ));
        $total_count = Recruiter_Terminal_DB::count_user_matches($user_id, $status);

        if (empty($matches)) {
            wp_send_json_success([
                'html' => '',
                'matches' => [],
                'total' => 0,
                'has_more' => false
            ]);
        }

        // Build HTML for match cards
        ob_start();
        foreach ($matches as $match) {
            $this->render_match_card($match);
        }
        $html = ob_get_clean();

        wp_send_json_success([
            'html' => $html,
            'matches' => $matches,
            'total' => $total_count,
            'has_more' => ($offset + count($matches)) < $total_count
        ]);
    }

    /**
     * Render a single match card
     */
    private function render_match_card($match) {
        // The match object contains joined brief data directly (not nested)
        // Fields: id, user_id, brief_id, match_score, score_breakdown, status, calculated_at, status_changed_at
        // Plus brief fields: title, brief, location, sector, salary_range, is_external, external_recruiter_id, detected_skills, recruiter_user_id

        if (empty($match->brief_id)) {
            return;
        }

        // Get recruiter info
        $recruiter_name = '';
        $recruiter_company = '';
        $recruiter_photo = '';
        $recruiter_rating = 0;

        if (!empty($match->is_external) && !empty($match->external_recruiter_id)) {
            // External recruiter
            $external = Recruiter_Terminal_DB::get_external_recruiter($match->external_recruiter_id);
            if ($external) {
                $recruiter_name = $external->name;
                $recruiter_company = $external->company;
                $recruiter_photo = $external->photo_url;
                $recruiter_rating = $external->rating;
            }
        } else {
            // Internal recruiter (WP user)
            $recruiter_user_id = $match->recruiter_user_id ?? 0;
            if ($recruiter_user_id) {
                $user = get_userdata($recruiter_user_id);
                if ($user) {
                    $recruiter_name = $user->display_name;
                    $recruiter_company = get_user_meta($recruiter_user_id, 'company', true);
                    $recruiter_photo = get_avatar_url($recruiter_user_id, ['size' => 80]);
                }
            }
        }

        // Fallback recruiter name
        if (empty($recruiter_name)) {
            $recruiter_name = 'Recruiter';
        }

        $score = round($match->match_score);
        $score_class = $score >= 80 ? 'excellent' : ($score >= 60 ? 'good' : ($score >= 40 ? 'fair' : 'low'));

        ?>
        <article class="nrt-match-card" data-match-id="<?php echo esc_attr($match->id); ?>" data-brief-id="<?php echo esc_attr($match->brief_id); ?>">
            <div class="nrt-match-card__header">
                <div class="nrt-match-recruiter">
                    <?php if ($recruiter_photo) : ?>
                        <img src="<?php echo esc_url($recruiter_photo); ?>" alt="<?php echo esc_attr($recruiter_name); ?>" class="nrt-match-avatar">
                    <?php else : ?>
                        <div class="nrt-match-avatar nrt-match-avatar--initials">
                            <?php echo esc_html(strtoupper(substr($recruiter_name, 0, 1))); ?>
                        </div>
                    <?php endif; ?>
                    <div class="nrt-match-recruiter-info">
                        <span class="nrt-match-recruiter-name"><?php echo esc_html($recruiter_name); ?></span>
                        <?php if ($recruiter_company) : ?>
                            <span class="nrt-match-recruiter-company"><?php echo esc_html($recruiter_company); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="nrt-match-score nrt-match-score--<?php echo esc_attr($score_class); ?>">
                    <svg viewBox="0 0 36 36" class="nrt-match-score-ring">
                        <path class="nrt-match-score-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                        <path class="nrt-match-score-fill" stroke-dasharray="<?php echo esc_attr($score); ?>, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                    </svg>
                    <span class="nrt-match-score-value"><?php echo esc_html($score); ?></span>
                </div>
            </div>

            <div class="nrt-match-card__body">
                <h4 class="nrt-match-title"><?php echo esc_html($match->title); ?></h4>
                <?php if (!empty($match->location)) : ?>
                    <div class="nrt-match-meta">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                            <circle cx="12" cy="10" r="3"/>
                        </svg>
                        <span><?php echo esc_html($match->location); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($match->salary_range)) : ?>
                    <div class="nrt-match-meta">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                            <line x1="12" y1="1" x2="12" y2="23"/>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                        </svg>
                        <span><?php echo esc_html($match->salary_range); ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="nrt-match-card__footer">
                <button type="button" class="nrt-match-action nrt-match-action--skip" data-action="skip" title="Not Interested">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
                <button type="button" class="nrt-match-action nrt-match-action--view" data-action="view" title="View Details">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                </button>
                <button type="button" class="nrt-match-action nrt-match-action--interested" data-action="interested" title="I'm Interested">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                    </svg>
                </button>
            </div>
        </article>
        <?php
    }

    /**
     * AJAX: Load match detail (for right panel)
     */
    public function ajax_nrt_load_match_detail() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Please log in.']);
        }

        $user_id = get_current_user_id();
        $brief_id = isset($_POST['brief_id']) ? absint($_POST['brief_id']) : 0;

        if (!$brief_id) {
            wp_send_json_error(['message' => 'Invalid brief ID.']);
        }

        if (!class_exists('Recruiter_Terminal_DB')) {
            wp_send_json_error(['message' => 'System not available.']);
        }

        // Verify user has a match for this brief
        $match = Recruiter_Terminal_DB::get_user_match($user_id, $brief_id);
        if (!$match) {
            wp_send_json_error(['message' => 'Match not found.']);
        }

        // Get full brief data
        $brief = Recruiter_Terminal_DB::get_brief($brief_id);
        if (!$brief) {
            wp_send_json_error(['message' => 'Brief not found.']);
        }

        // Get recruiter info
        $recruiter = null;
        $is_external = false;

        if (!empty($brief->is_external) && !empty($brief->external_recruiter_id)) {
            $is_external = true;
            $recruiter = Recruiter_Terminal_DB::get_external_recruiter($brief->external_recruiter_id);
        } else {
            $user = get_userdata($brief->user_id);
            if ($user) {
                $recruiter = (object) [
                    'name' => $user->display_name,
                    'company' => get_user_meta($brief->user_id, 'company', true),
                    'title' => get_user_meta($brief->user_id, 'job_title', true),
                    'photo_url' => get_avatar_url($brief->user_id, ['size' => 120]),
                    'email' => '',
                    'rating' => 0,
                    'review_count' => 0,
                    'bio' => get_user_meta($brief->user_id, 'description', true),
                ];
            }
        }

        // Fallback recruiter if none found
        if (!$recruiter) {
            $recruiter = (object) [
                'name' => 'Recruiter',
                'company' => '',
                'title' => '',
                'photo_url' => '',
                'email' => '',
                'rating' => 0,
                'review_count' => 0,
                'bio' => '',
            ];
        }

        // Parse score breakdown if available
        $score_breakdown = [];
        if (!empty($match->score_breakdown)) {
            $score_breakdown = is_string($match->score_breakdown)
                ? json_decode($match->score_breakdown, true)
                : (array) $match->score_breakdown;
        }

        // Build detail HTML using template
        ob_start();
        $template_path = SENNA_CAREERS_PATH . 'templates/recruiter-terminal-parts/match-detail.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            // Fallback to inline render
            $this->render_match_detail($brief, $recruiter, $match, $score_breakdown, $is_external);
        }
        $html = ob_get_clean();

        wp_send_json_success([
            'html' => $html,
            'brief' => $brief,
            'recruiter' => $recruiter,
            'match' => $match,
            'score_breakdown' => $score_breakdown
        ]);
    }

    /**
     * Render match detail panel
     */
    private function render_match_detail($brief, $recruiter, $match, $score_breakdown, $is_external) {
        $score = round($match->match_score);
        $score_class = $score >= 80 ? 'excellent' : ($score >= 60 ? 'good' : ($score >= 40 ? 'fair' : 'low'));

        // Parse criteria if available
        $criteria = [];
        if (!empty($brief->parsed_criteria)) {
            $criteria = is_string($brief->parsed_criteria) ? json_decode($brief->parsed_criteria, true) : (array) $brief->parsed_criteria;
        }

        // Parse detected skills
        $skills = [];
        if (!empty($brief->detected_skills)) {
            $skills = is_string($brief->detected_skills) ? json_decode($brief->detected_skills, true) : (array) $brief->detected_skills;
        }
        ?>
        <div class="nrt-match-detail">
            <div class="nrt-match-detail__recruiter">
                <div class="nrt-match-detail__recruiter-header">
                    <?php if (!empty($recruiter->photo_url)) : ?>
                        <img src="<?php echo esc_url($recruiter->photo_url); ?>" alt="" class="nrt-match-detail__avatar">
                    <?php else : ?>
                        <div class="nrt-match-detail__avatar nrt-match-detail__avatar--initials">
                            <?php echo esc_html(strtoupper(substr($recruiter->name ?? 'R', 0, 1))); ?>
                        </div>
                    <?php endif; ?>
                    <div class="nrt-match-detail__recruiter-info">
                        <h3 class="nrt-match-detail__recruiter-name"><?php echo esc_html($recruiter->name ?? 'Recruiter'); ?></h3>
                        <?php if (!empty($recruiter->title)) : ?>
                            <span class="nrt-match-detail__recruiter-title"><?php echo esc_html($recruiter->title); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($recruiter->company)) : ?>
                            <span class="nrt-match-detail__recruiter-company"><?php echo esc_html($recruiter->company); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($is_external) : ?>
                        <span class="nrt-match-detail__badge nrt-match-detail__badge--external">External</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="nrt-match-detail__score-section">
                <div class="nrt-match-detail__score nrt-match-detail__score--<?php echo esc_attr($score_class); ?>">
                    <svg viewBox="0 0 36 36" class="nrt-match-detail__score-ring">
                        <path class="nrt-match-detail__score-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                        <path class="nrt-match-detail__score-fill" stroke-dasharray="<?php echo esc_attr($score); ?>, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                    </svg>
                    <div class="nrt-match-detail__score-text">
                        <span class="nrt-match-detail__score-value"><?php echo esc_html($score); ?>%</span>
                        <span class="nrt-match-detail__score-label">Match</span>
                    </div>
                </div>
                <?php if (!empty($score_breakdown)) : ?>
                    <div class="nrt-match-detail__breakdown">
                        <?php foreach ($score_breakdown as $key => $value) :
                            $label = ucwords(str_replace('_', ' ', $key));
                        ?>
                            <div class="nrt-match-detail__breakdown-item">
                                <span class="nrt-match-detail__breakdown-label"><?php echo esc_html($label); ?></span>
                                <div class="nrt-match-detail__breakdown-bar">
                                    <div class="nrt-match-detail__breakdown-fill" style="width: <?php echo esc_attr(min(100, $value)); ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="nrt-match-detail__brief">
                <h2 class="nrt-match-detail__title"><?php echo esc_html($brief->title); ?></h2>

                <div class="nrt-match-detail__meta">
                    <?php if (!empty($brief->location)) : ?>
                        <div class="nrt-match-detail__meta-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                <circle cx="12" cy="10" r="3"/>
                            </svg>
                            <span><?php echo esc_html($brief->location); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($brief->salary_range)) : ?>
                        <div class="nrt-match-detail__meta-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                <line x1="12" y1="1" x2="12" y2="23"/>
                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                            </svg>
                            <span><?php echo esc_html($brief->salary_range); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($brief->brief)) : ?>
                    <div class="nrt-match-detail__description">
                        <h4>About the Role</h4>
                        <div class="nrt-match-detail__content">
                            <?php echo wp_kses_post(wpautop($brief->brief)); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($skills)) : ?>
                    <div class="nrt-match-detail__skills">
                        <h4>Key Skills</h4>
                        <div class="nrt-match-detail__skills-list">
                            <?php foreach (array_slice($skills, 0, 10) as $skill) : ?>
                                <span class="nrt-match-detail__skill"><?php echo esc_html(ucwords(str_replace('_', ' ', $skill))); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="nrt-match-detail__actions">
                <button type="button" class="nrt-match-detail__action nrt-match-detail__action--skip" data-action="skip" data-brief-id="<?php echo esc_attr($brief->id); ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                    <span>Not Interested</span>
                </button>
                <button type="button" class="nrt-match-detail__action nrt-match-detail__action--interested" data-action="interested" data-brief-id="<?php echo esc_attr($brief->id); ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                    </svg>
                    <span>I'm Interested</span>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Update match status (interested/skipped)
     */
    public function ajax_nrt_update_match_status() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Please log in.']);
        }

        $user_id = get_current_user_id();
        $brief_id = isset($_POST['brief_id']) ? absint($_POST['brief_id']) : 0;
        $status = isset($_POST['status']) ? sanitize_key($_POST['status']) : '';

        if (!$brief_id || !in_array($status, ['interested', 'skipped', 'pending'])) {
            wp_send_json_error(['message' => 'Invalid request.']);
        }

        if (!class_exists('Recruiter_Terminal_DB')) {
            wp_send_json_error(['message' => 'System not available.']);
        }

        // Update match status
        $result = Recruiter_Terminal_DB::update_user_match($user_id, $brief_id, [
            'status' => $status,
            'status_changed_at' => current_time('mysql')
        ]);

        if (!$result) {
            wp_send_json_error(['message' => 'Failed to update match status.']);
        }

        // If interested, trigger action hook for potential notification
        if ($status === 'interested') {
            do_action('nrt_user_interested_in_brief', $user_id, $brief_id);
        }

        $messages = [
            'interested' => 'Interest registered!',
            'skipped' => 'Match skipped.',
            'pending' => 'Match restored.'
        ];

        wp_send_json_success([
            'message' => $messages[$status] ?? 'Status updated.',
            'status' => $status
        ]);
    }

    /**
     * Mask a name for logged-out users
     *
     * @param string $name The name to mask
     * @param string $type 'partial' shows first and last letter (H***a), 'full' shows only first letter (H****)
     * @return string The masked name
     */
    private function mask_name($name, $type = 'partial') {
        if (empty($name)) {
            return '';
        }

        $words = explode(' ', trim($name));
        $masked_words = [];

        foreach ($words as $word) {
            $len = mb_strlen($word);
            if ($len <= 1) {
                $masked_words[] = $word;
                continue;
            }

            $first_char = mb_strtoupper(mb_substr($word, 0, 1));

            if ($type === 'partial' && $len > 2) {
                // Show first and last letter: "Hamza" → "H***a"
                $last_char = mb_strtolower(mb_substr($word, -1, 1));
                $middle_len = max(1, $len - 2);
                $masked_words[] = $first_char . str_repeat('*', $middle_len) . $last_char;
            } else {
                // Show only first letter: "Hamza" → "H****"
                $masked_words[] = $first_char . str_repeat('*', max(1, $len - 1));
            }
        }

        return implode(' ', $masked_words);
    }

    /**
     * AJAX handler for loading contacts list
     */
    public function ajax_nrt_load_contacts() {
        check_ajax_referer('sffc_public_nonce', 'nonce');

        if (!class_exists('SFFC_Contacts_Database')) {
            wp_send_json_error('Contacts module not available.');
        }

        $is_logged_in = is_user_logged_in();
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $company = isset($_POST['company']) ? sanitize_text_field($_POST['company']) : '';
        $country = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '';
        $seniority = isset($_POST['seniority']) ? sanitize_text_field($_POST['seniority']) : '';
        $industry = isset($_POST['industry']) ? sanitize_text_field($_POST['industry']) : '';

        $result = SFFC_Contacts_Database::get_contacts([
            'page' => $page,
            'per_page' => 20,
            'search' => $search,
            'company' => $company,
            'country' => $country,
            'seniority' => $seniority,
            'industry' => $industry,
            'region' => ''
        ]);

        $contacts_html = '';
        foreach ($result['contacts'] as $index => $contact) {
            $first_name = $contact->first_name;
            $last_name = $contact->last_name;
            $full_name = trim($first_name . ' ' . $last_name);
            $initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));
            $location = array_filter([$contact->city, $contact->country]);

            // Lock all cards except first one for logged-out users
            $is_locked = !$is_logged_in && $index > 0;
            $is_first_card_logged_out = !$is_logged_in && $index === 0;

            // Mask names for logged-out users
            if ($is_first_card_logged_out) {
                // First card: partial masking (show first and last letter)
                $display_name = $this->mask_name($full_name, 'partial');
            } elseif ($is_locked) {
                // Locked cards: full masking (only first letter visible)
                $display_name = $this->mask_name($full_name, 'full');
            } else {
                // Logged-in users: show full name
                $display_name = $full_name;
            }

            $contacts_html .= '<div class="nrt-contact-card' . ($is_locked ? ' is-locked' : '') . '" data-contact-id="' . esc_attr($contact->id) . '"' . ($is_locked ? ' data-locked="true"' : '') . '>';
            $contacts_html .= '<div class="nrt-contact-card-avatar">' . esc_html($initials) . '</div>';
            $contacts_html .= '<div class="nrt-contact-card-info">';
            $contacts_html .= '<p class="nrt-contact-card-name">' . esc_html($display_name) . '</p>';
            $contacts_html .= '<p class="nrt-contact-card-title">' . esc_html($contact->job_title ?: 'Position not specified') . '</p>';
            $contacts_html .= '<p class="nrt-contact-card-company">' . esc_html($contact->company_name ?: 'Company not specified') . '</p>';

            if (!empty($location) || !empty($contact->seniority)) {
                $contacts_html .= '<div class="nrt-contact-card-meta">';
                if (!empty($contact->seniority)) {
                    $contacts_html .= '<span class="nrt-contact-card-tag">' . esc_html(ucfirst($contact->seniority)) . '</span>';
                }
                if (!empty($location)) {
                    $contacts_html .= '<span class="nrt-contact-card-tag">' . esc_html(implode(', ', $location)) . '</span>';
                }
                $contacts_html .= '</div>';
            }

            // Add lock icon for locked cards
            if ($is_locked) {
                $contacts_html .= '<div class="nrt-story-lock">';
                $contacts_html .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">';
                $contacts_html .= '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>';
                $contacts_html .= '<path d="M7 11V7a5 5 0 0 1 10 0v4"/>';
                $contacts_html .= '</svg></div>';
            }

            $contacts_html .= '</div></div>';
        }

        wp_send_json_success([
            'html' => $contacts_html,
            'total' => $result['total'],
            'pages' => $is_logged_in ? $result['pages'] : 1,
            'page' => $result['page'],
            'has_contacts' => count($result['contacts']) > 0
        ]);
    }

    /**
     * AJAX handler for loading contact detail
     */
    public function ajax_nrt_load_contact_detail() {
        check_ajax_referer('sffc_public_nonce', 'nonce');

        if (!class_exists('SFFC_Contacts_Database')) {
            wp_send_json_error('Contacts module not available.');
        }

        $contact_id = isset($_POST['contact_id']) ? intval($_POST['contact_id']) : 0;

        if (!$contact_id) {
            wp_send_json_error('Invalid contact ID.');
        }

        $contact = SFFC_Contacts_Database::get_contact($contact_id);

        if (!$contact) {
            wp_send_json_error('Contact not found.');
        }

        // Build location strings
        $contact_location = array_filter([$contact->city, $contact->state, $contact->country]);
        $company_location = array_filter([$contact->company_city, $contact->company_country]);

        $is_logged_in = is_user_logged_in();

        // For logged-out users, show limited contact info with masked name
        if (!$is_logged_in) {
            $full_name = trim($contact->first_name . ' ' . $contact->last_name);
            $masked_name = $this->mask_name($full_name, 'partial');
            $masked_first = $this->mask_name($contact->first_name, 'partial');
            $masked_last = $this->mask_name($contact->last_name, 'partial');

            wp_send_json_success([
                'id' => $contact->id,
                'first_name' => $masked_first,
                'last_name' => $masked_last,
                'full_name' => $masked_name,
                'initials' => strtoupper(substr($contact->first_name, 0, 1) . substr($contact->last_name, 0, 1)),
                'job_title' => $contact->job_title,
                'seniority' => $contact->seniority,
                'departments' => $contact->departments,
                'linkedin_url' => '', // Hide LinkedIn for logged-out users
                'location' => !empty($contact_location) ? implode(', ', $contact_location) : '',
                'company_name' => $contact->company_name,
                'main_industry' => $contact->main_industry,
                'company_location' => !empty($company_location) ? implode(', ', $company_location) : '',
                // Hide sensitive fields for logged-out users
                'email' => '',
                'phone' => '',
                'company_website' => '',
                'company_linkedin' => '',
                'company_description' => '',
                'sub_industry' => '',
                'num_employees' => '',
                'revenue' => '',
                'is_masked' => true
            ]);
        }

        // Full details for logged-in users
        wp_send_json_success([
            'id' => $contact->id,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'full_name' => trim($contact->first_name . ' ' . $contact->last_name),
            'initials' => strtoupper(substr($contact->first_name, 0, 1) . substr($contact->last_name, 0, 1)),
            'email' => $contact->email,
            'phone' => $contact->phone_1,
            'job_title' => $contact->job_title,
            'seniority' => $contact->seniority,
            'departments' => $contact->departments,
            'linkedin_url' => $contact->linkedin_url,
            'location' => !empty($contact_location) ? implode(', ', $contact_location) : '',
            'company_name' => $contact->company_name,
            'company_website' => $contact->company_website,
            'company_linkedin' => $contact->company_linkedin,
            'company_description' => $contact->company_description,
            'main_industry' => $contact->main_industry,
            'sub_industry' => $contact->sub_industry,
            'num_employees' => $contact->num_employees,
            'revenue' => $contact->revenue,
            'company_location' => !empty($company_location) ? implode(', ', $company_location) : ''
        ]);
    }

    /**
     * AJAX handler for getting contact filter options
     */
    public function ajax_nrt_get_contact_filters() {
        check_ajax_referer('sffc_public_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('Please sign in.');
        }

        if (!class_exists('SFFC_Contacts_Database')) {
            wp_send_json_error('Contacts module not available.');
        }

        $filters = SFFC_Contacts_Database::get_filter_options();

        wp_send_json_success($filters);
    }

    /**
     * AJAX handler for loading companies
     */
    public function ajax_nrt_load_companies() {
        check_ajax_referer('sffc_public_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('Please sign in to view companies.');
        }

        if (!class_exists('SFFC_Contacts_Database')) {
            wp_send_json_error('Companies module not available.');
        }

        $args = [
            'page' => isset($_POST['page']) ? absint($_POST['page']) : 1,
            'per_page' => 50,
            'search' => isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '',
            'industry' => isset($_POST['industry']) ? sanitize_text_field($_POST['industry']) : '',
            'country' => isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '',
            'has_contacts' => isset($_POST['has_contacts']) && $_POST['has_contacts'] === 'true'
        ];

        $result = SFFC_Contacts_Database::get_companies($args);

        // Render HTML for companies list
        ob_start();
        if (!empty($result['companies'])) {
            foreach ($result['companies'] as $company) {
                $this->render_company_card($company);
            }
        }
        $html = ob_get_clean();

        wp_send_json_success([
            'html' => $html,
            'total' => $result['total'],
            'pages' => $result['pages'],
            'page' => $result['page']
        ]);
    }

    /**
     * Render a company card
     */
    private function render_company_card($company) {
        $contact_count = (int) $company->contact_count;
        $location = trim(($company->city ? $company->city . ', ' : '') . ($company->country ?: ''));
        ?>
        <article class="nrt-company-card" data-company-id="<?php echo esc_attr($company->id); ?>">
            <div class="nrt-company-card-header">
                <h3 class="nrt-company-name"><?php echo esc_html($company->name); ?></h3>
                <?php if ($contact_count > 0) : ?>
                <span class="nrt-company-contact-count">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                    </svg>
                    <?php echo esc_html($contact_count); ?>
                </span>
                <?php endif; ?>
            </div>
            <?php if (!empty($company->main_industry)) : ?>
            <p class="nrt-company-industry"><?php echo esc_html($company->main_industry); ?></p>
            <?php endif; ?>
            <div class="nrt-company-meta">
                <?php if (!empty($location)) : ?>
                <span class="nrt-company-location">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                        <circle cx="12" cy="10" r="3"/>
                    </svg>
                    <?php echo esc_html($location); ?>
                </span>
                <?php endif; ?>
                <?php if (!empty($company->num_employees)) : ?>
                <span class="nrt-company-size"><?php echo esc_html($company->num_employees); ?> employees</span>
                <?php endif; ?>
            </div>
        </article>
        <?php
    }

    /**
     * AJAX handler for loading company detail (contacts at that company + jobs)
     */
    public function ajax_nrt_load_company_detail() {
        check_ajax_referer('sffc_public_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('Please sign in.');
        }

        if (!class_exists('SFFC_Contacts_Database')) {
            wp_send_json_error('Companies module not available.');
        }

        $company_id = isset($_POST['company_id']) ? absint($_POST['company_id']) : 0;
        if (!$company_id) {
            wp_send_json_error('Invalid company ID.');
        }

        // Get company info
        $company = SFFC_Contacts_Database::get_company($company_id);
        if (!$company) {
            wp_send_json_error('Company not found.');
        }

        // Get contacts at this company
        $contacts_result = SFFC_Contacts_Database::get_company_contacts($company_id, ['per_page' => 50]);

        // Get open jobs at this company
        $jobs = $this->get_jobs_at_company($company->name);

        // Render the detail view
        ob_start();
        $this->render_company_detail($company, $contacts_result['contacts'], $jobs);
        $html = ob_get_clean();

        wp_send_json_success(['html' => $html]);
    }

    /**
     * Render company detail view
     */
    private function render_company_detail($company, $contacts, $jobs = []) {
        $location = trim(($company->city ? $company->city . ', ' : '') . ($company->country ?: ''));
        ?>
        <div class="nrt-company-detail">
            <div class="nrt-company-detail-header">
                <h2 class="nrt-company-detail-name"><?php echo esc_html($company->name); ?></h2>
                <?php if (!empty($company->main_industry)) : ?>
                <span class="nrt-company-detail-industry"><?php echo esc_html($company->main_industry); ?></span>
                <?php endif; ?>
            </div>

            <div class="nrt-company-detail-meta">
                <?php if (!empty($location)) : ?>
                <div class="nrt-company-detail-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                        <circle cx="12" cy="10" r="3"/>
                    </svg>
                    <span><?php echo esc_html($location); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($company->num_employees)) : ?>
                <div class="nrt-company-detail-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                    <span><?php echo esc_html($company->num_employees); ?> employees</span>
                </div>
                <?php endif; ?>
                <?php if (!empty($company->website)) : ?>
                <div class="nrt-company-detail-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="2" y1="12" x2="22" y2="12"/>
                        <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                    </svg>
                    <a href="<?php echo esc_url($company->website); ?>" target="_blank" rel="noopener"><?php echo esc_html(preg_replace('#^https?://(www\.)?#', '', $company->website)); ?></a>
                </div>
                <?php endif; ?>
                <?php if (!empty($company->linkedin_url)) : ?>
                <div class="nrt-company-detail-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                        <path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/>
                        <rect x="2" y="9" width="4" height="12"/>
                        <circle cx="4" cy="4" r="2"/>
                    </svg>
                    <a href="<?php echo esc_url($company->linkedin_url); ?>" target="_blank" rel="noopener">LinkedIn</a>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($company->description)) : ?>
            <div class="nrt-company-detail-description">
                <p><?php echo esc_html($company->description); ?></p>
            </div>
            <?php endif; ?>

            <div class="nrt-company-contacts-section">
                <h3 class="nrt-company-contacts-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                    Contacts at <?php echo esc_html($company->name); ?>
                    <span class="nrt-company-contacts-count"><?php echo count($contacts); ?></span>
                </h3>

                <?php if (!empty($contacts)) : ?>
                <div class="nrt-company-contacts-list">
                    <?php foreach ($contacts as $contact) : ?>
                    <div class="nrt-company-contact-item" data-contact-id="<?php echo esc_attr($contact->id); ?>">
                        <div class="nrt-company-contact-avatar">
                            <?php echo esc_html(substr($contact->first_name, 0, 1) . substr($contact->last_name ?: '', 0, 1)); ?>
                        </div>
                        <div class="nrt-company-contact-info">
                            <span class="nrt-company-contact-name"><?php echo esc_html($contact->first_name . ' ' . $contact->last_name); ?></span>
                            <?php if (!empty($contact->job_title)) : ?>
                            <span class="nrt-company-contact-title"><?php echo esc_html($contact->job_title); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($contact->linkedin_url)) : ?>
                        <a href="<?php echo esc_url($contact->linkedin_url); ?>" class="nrt-company-contact-linkedin" target="_blank" rel="noopener" title="View LinkedIn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                <path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/>
                                <rect x="2" y="9" width="4" height="12"/>
                                <circle cx="4" cy="4" r="2"/>
                            </svg>
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else : ?>
                <div class="nrt-company-contacts-empty">
                    <p>No contacts found at this company.</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Open Jobs at This Company -->
            <div class="nrt-company-jobs-section">
                <h3 class="nrt-company-jobs-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                        <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/>
                        <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                    </svg>
                    Open Jobs
                    <span class="nrt-company-jobs-count"><?php echo count($jobs); ?></span>
                </h3>

                <?php if (!empty($jobs)) : ?>
                <div class="nrt-company-jobs-list">
                    <?php foreach ($jobs as $job) : ?>
                    <div class="nrt-company-job-item">
                        <div class="nrt-company-job-info">
                            <a href="<?php echo esc_url(get_permalink($job['id'])); ?>" class="nrt-company-job-title" target="_blank">
                                <?php echo esc_html($job['title']); ?>
                            </a>
                            <div class="nrt-company-job-meta">
                                <?php if (!empty($job['location'])) : ?>
                                <span class="nrt-company-job-location">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12">
                                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                        <circle cx="12" cy="10" r="3"/>
                                    </svg>
                                    <?php echo esc_html($job['location']); ?>
                                </span>
                                <?php endif; ?>
                                <?php if (!empty($job['posted'])) : ?>
                                <span class="nrt-company-job-posted"><?php echo esc_html($job['posted']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (!empty($job['apply_url'])) : ?>
                        <a href="<?php echo esc_url($job['apply_url']); ?>" class="nrt-company-job-apply" target="_blank" rel="noopener">
                            Apply
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                                <line x1="7" y1="17" x2="17" y2="7"/>
                                <polyline points="7 7 17 7 17 17"/>
                            </svg>
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else : ?>
                <div class="nrt-company-jobs-empty">
                    <p>No open positions found at this company.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Get jobs at a company by name
     */
    private function get_jobs_at_company($company_name, $limit = 10) {
        if (empty($company_name)) {
            return [];
        }

        $args = [
            'post_type'      => 'sffc_job',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'     => 'sffc_company_name',
                    'value'   => $company_name,
                    'compare' => 'LIKE'
                ],
                [
                    'key'     => 'sffc_actual_company',
                    'value'   => $company_name,
                    'compare' => 'LIKE'
                ],
                [
                    'key'     => 'sffc_company',
                    'value'   => $company_name,
                    'compare' => 'LIKE'
                ]
            ]
        ];

        $query = new \WP_Query($args);
        $jobs = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $job_id = get_the_ID();
                $meta = get_post_meta($job_id);

                // Get location
                $location = $meta['sffc_location'][0] ?? '';
                if (empty($location)) {
                    $location = $meta['sffc_city'][0] ?? '';
                }

                // Get apply URL
                $apply_url = $meta['_sffc_job_application_url'][0]
                    ?? $meta['sffc_application_url'][0]
                    ?? $meta['sffc_url'][0]
                    ?? get_permalink($job_id);

                // Calculate posted date
                $posted_date = get_the_date('Y-m-d', $job_id);
                $days_ago = floor((time() - strtotime($posted_date)) / DAY_IN_SECONDS);
                $posted = $days_ago === 0 ? 'Today' : ($days_ago === 1 ? 'Yesterday' : $days_ago . ' days ago');

                $jobs[] = [
                    'id'        => $job_id,
                    'title'     => get_the_title(),
                    'location'  => $location,
                    'posted'    => $posted,
                    'apply_url' => $apply_url
                ];
            }
            wp_reset_postdata();
        }

        return $jobs;
    }

    /**
     * AJAX handler for getting company filter options
     */
    public function ajax_nrt_get_company_filters() {
        check_ajax_referer('sffc_public_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('Please sign in.');
        }

        if (!class_exists('SFFC_Contacts_Database')) {
            wp_send_json_error('Companies module not available.');
        }

        $filters = SFFC_Contacts_Database::get_company_filter_options();

        wp_send_json_success($filters);
    }

    // ============================================
    // NETWORKING PROFILE AJAX HANDLERS
    // ============================================

    /**
     * AJAX: Save a contact
     */
    public function ajax_nrt_save_contact() {
        check_ajax_referer('sffc_public_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('Please sign in.');
        }

        $contact_id = isset($_POST['contact_id']) ? absint($_POST['contact_id']) : 0;
        if (!$contact_id) {
            wp_send_json_error('Invalid contact ID.');
        }

        SFFC_Contacts_Database::save_contact(get_current_user_id(), $contact_id);
        wp_send_json_success(['saved' => true]);
    }

    /**
     * AJAX: Unsave a contact
     */
    public function ajax_nrt_unsave_contact() {
        check_ajax_referer('sffc_public_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('Please sign in.');
        }

        $contact_id = isset($_POST['contact_id']) ? absint($_POST['contact_id']) : 0;
        if (!$contact_id) {
            wp_send_json_error('Invalid contact ID.');
        }

        SFFC_Contacts_Database::unsave_contact(get_current_user_id(), $contact_id);
        wp_send_json_success(['saved' => false]);
    }

    /**
     * AJAX: Get saved contacts
     */
    public function ajax_nrt_get_saved_contacts() {
        check_ajax_referer('sffc_public_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('Please sign in.');
        }

        $contacts = SFFC_Contacts_Database::get_saved_contacts(get_current_user_id());

        ob_start();
        if (!empty($contacts)) {
            foreach ($contacts as $contact) {
                $this->render_saved_contact_card($contact);
            }
        }
        $html = ob_get_clean();

        wp_send_json_success([
            'html' => $html,
            'count' => count($contacts)
        ]);
    }

    /**
     * Render a saved contact card
     */
    private function render_saved_contact_card($contact) {
        $outreach = SFFC_Contacts_Database::get_contact_outreach(get_current_user_id(), $contact->id);
        ?>
        <div class="nrt-saved-contact-card" data-contact-id="<?php echo esc_attr($contact->id); ?>">
            <div class="nrt-saved-contact-avatar">
                <?php echo esc_html(strtoupper(substr($contact->first_name, 0, 1) . substr($contact->last_name ?: '', 0, 1))); ?>
            </div>
            <div class="nrt-saved-contact-info">
                <span class="nrt-saved-contact-name"><?php echo esc_html($contact->first_name . ' ' . ($contact->last_name ?: '')); ?></span>
                <?php if (!empty($contact->job_title)) : ?>
                <span class="nrt-saved-contact-title"><?php echo esc_html($contact->job_title); ?></span>
                <?php endif; ?>
                <?php if (!empty($contact->company_name)) : ?>
                <span class="nrt-saved-contact-company"><?php echo esc_html($contact->company_name); ?></span>
                <?php endif; ?>
            </div>
            <div class="nrt-saved-contact-actions">
                <?php if ($outreach) : ?>
                <span class="nrt-contacted-badge" title="Contacted <?php echo esc_attr(human_time_diff(strtotime($outreach['contacted_at']), current_time('timestamp'))); ?> ago">
                    <svg viewBox="0 0 24 24" fill="currentColor" width="12" height="12"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                    Contacted
                </span>
                <?php endif; ?>
                <?php if (!empty($contact->linkedin_url)) : ?>
                <a href="<?php echo esc_url($contact->linkedin_url); ?>" class="nrt-saved-contact-linkedin" target="_blank" rel="noopener">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                        <path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/>
                        <rect x="2" y="9" width="4" height="12"/>
                        <circle cx="4" cy="4" r="2"/>
                    </svg>
                </a>
                <?php endif; ?>
                <button type="button" class="nrt-unsave-contact-btn" data-contact-id="<?php echo esc_attr($contact->id); ?>" title="Remove">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Save target company
     */
    public function ajax_nrt_save_target_company() {
        check_ajax_referer('sffc_public_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('Please sign in.');
        }

        $company_id = isset($_POST['company_id']) ? absint($_POST['company_id']) : 0;
        if (!$company_id) {
            wp_send_json_error('Invalid company ID.');
        }

        SFFC_Contacts_Database::save_target_company(get_current_user_id(), $company_id);
        wp_send_json_success(['saved' => true]);
    }

    /**
     * AJAX: Remove target company
     */
    public function ajax_nrt_remove_target_company() {
        check_ajax_referer('sffc_public_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('Please sign in.');
        }

        $company_id = isset($_POST['company_id']) ? absint($_POST['company_id']) : 0;
        if (!$company_id) {
            wp_send_json_error('Invalid company ID.');
        }

        SFFC_Contacts_Database::remove_target_company(get_current_user_id(), $company_id);
        wp_send_json_success(['saved' => false]);
    }

    /**
     * AJAX: Get target companies
     */
    public function ajax_nrt_get_target_companies() {
        check_ajax_referer('sffc_public_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('Please sign in.');
        }

        $companies = SFFC_Contacts_Database::get_target_companies(get_current_user_id());

        ob_start();
        if (!empty($companies)) {
            foreach ($companies as $company) {
                $this->render_target_company_card($company);
            }
        }
        $html = ob_get_clean();

        wp_send_json_success([
            'html' => $html,
            'count' => count($companies)
        ]);
    }

    /**
     * Render a target company card
     */
    private function render_target_company_card($company) {
        ?>
        <div class="nrt-target-company-card" data-company-id="<?php echo esc_attr($company->id); ?>">
            <div class="nrt-target-company-info">
                <span class="nrt-target-company-name"><?php echo esc_html($company->name); ?></span>
                <?php if (!empty($company->main_industry)) : ?>
                <span class="nrt-target-company-industry"><?php echo esc_html($company->main_industry); ?></span>
                <?php endif; ?>
            </div>
            <div class="nrt-target-company-meta">
                <?php if ($company->contact_count > 0) : ?>
                <span class="nrt-target-company-contacts">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                    </svg>
                    <?php echo esc_html($company->contact_count); ?>
                </span>
                <?php endif; ?>
                <button type="button" class="nrt-remove-target-btn" data-company-id="<?php echo esc_attr($company->id); ?>" title="Remove">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Log outreach
     */
    public function ajax_nrt_log_outreach() {
        check_ajax_referer('sffc_public_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('Please sign in.');
        }

        $contact_id = isset($_POST['contact_id']) ? absint($_POST['contact_id']) : 0;
        $notes = isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : '';

        if (!$contact_id) {
            wp_send_json_error('Invalid contact ID.');
        }

        SFFC_Contacts_Database::log_outreach(get_current_user_id(), $contact_id, $notes);
        wp_send_json_success(['logged' => true]);
    }

    /**
     * AJAX: Remove outreach
     */
    public function ajax_nrt_remove_outreach() {
        check_ajax_referer('sffc_public_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('Please sign in.');
        }

        $contact_id = isset($_POST['contact_id']) ? absint($_POST['contact_id']) : 0;
        if (!$contact_id) {
            wp_send_json_error('Invalid contact ID.');
        }

        SFFC_Contacts_Database::remove_outreach(get_current_user_id(), $contact_id);
        wp_send_json_success(['removed' => true]);
    }

    /**
     * AJAX: Get outreach log
     */
    public function ajax_nrt_get_outreach_log() {
        check_ajax_referer('sffc_public_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('Please sign in.');
        }

        $contacts = SFFC_Contacts_Database::get_outreach_log(get_current_user_id());

        ob_start();
        if (!empty($contacts)) {
            foreach ($contacts as $contact) {
                $this->render_outreach_card($contact);
            }
        }
        $html = ob_get_clean();

        wp_send_json_success([
            'html' => $html,
            'count' => count($contacts)
        ]);
    }

    /**
     * Render an outreach log card
     */
    private function render_outreach_card($contact) {
        $contacted_at = $contact->outreach['contacted_at'] ?? '';
        $notes = $contact->outreach['notes'] ?? '';
        ?>
        <div class="nrt-outreach-card" data-contact-id="<?php echo esc_attr($contact->id); ?>">
            <div class="nrt-outreach-avatar">
                <?php echo esc_html(strtoupper(substr($contact->first_name, 0, 1) . substr($contact->last_name ?: '', 0, 1))); ?>
            </div>
            <div class="nrt-outreach-info">
                <span class="nrt-outreach-name"><?php echo esc_html($contact->first_name . ' ' . ($contact->last_name ?: '')); ?></span>
                <?php if (!empty($contact->company_name)) : ?>
                <span class="nrt-outreach-company"><?php echo esc_html($contact->company_name); ?></span>
                <?php endif; ?>
                <span class="nrt-outreach-date">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                    <?php echo esc_html(human_time_diff(strtotime($contacted_at), current_time('timestamp'))); ?> ago
                </span>
            </div>
            <div class="nrt-outreach-actions">
                <?php if (!empty($contact->linkedin_url)) : ?>
                <a href="<?php echo esc_url($contact->linkedin_url); ?>" class="nrt-outreach-linkedin" target="_blank" rel="noopener" title="View LinkedIn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                        <path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/>
                        <rect x="2" y="9" width="4" height="12"/>
                        <circle cx="4" cy="4" r="2"/>
                    </svg>
                </a>
                <?php endif; ?>
                <button type="button" class="nrt-remove-outreach-btn" data-contact-id="<?php echo esc_attr($contact->id); ?>" title="Remove">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Get networking stats for profile
     */
    public function ajax_nrt_get_networking_stats() {
        check_ajax_referer('sffc_public_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('Please sign in.');
        }

        $user_id = get_current_user_id();

        $saved_contacts = get_user_meta($user_id, 'sffc_saved_contacts', true) ?: [];
        $target_companies = get_user_meta($user_id, 'sffc_target_companies', true) ?: [];
        $outreach_log = get_user_meta($user_id, 'sffc_outreach_log', true) ?: [];

        wp_send_json_success([
            'saved_contacts' => count($saved_contacts),
            'target_companies' => count($target_companies),
            'outreach_count' => count($outreach_log)
        ]);
    }

    /**
     * =====================================================
     * CANDIDATE OPPORTUNITIES AJAX HANDLERS
     * =====================================================
     */

    /**
     * AJAX: Load opportunities for current user
     */
    public function ajax_nrt_load_opportunities() {
        check_ajax_referer('sffc_public_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_success([]); // Return empty array for logged-out users
        }

        $user_id = get_current_user_id();

        $opportunities = get_posts([
            'post_type' => 'sffc_candidate_opp',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => '_candidate_user_id',
                    'value' => $user_id,
                    'compare' => '='
                ]
            ],
            'orderby' => 'date',
            'order' => 'DESC'
        ]);

        $data = [];
        foreach ($opportunities as $opp) {
            $is_new = get_post_meta($opp->ID, '_is_new', true);
            $source = get_post_meta($opp->ID, '_source', true) ?: 'recruiter_interest';

            $data[] = [
                'id' => $opp->ID,
                'title' => $opp->post_title,
                'company' => get_post_meta($opp->ID, '_company', true),
                'companyLogo' => null, // Can be added to admin later if needed
                'location' => get_post_meta($opp->ID, '_location', true),
                'salary' => get_post_meta($opp->ID, '_salary', true),
                'isNew' => ($is_new === '1'),
                'isSaved' => (bool) get_post_meta($opp->ID, '_is_saved', true),
                'recruiter' => [
                    'name' => get_post_meta($opp->ID, '_recruiter_name', true),
                    'title' => get_post_meta($opp->ID, '_recruiter_title', true),
                    'company' => get_post_meta($opp->ID, '_recruiter_company', true),
                    'avatar' => null,
                    'status' => get_post_meta($opp->ID, '_recruiter_status', true) ?: $this->get_opportunity_source_label($source)
                ],
                'matchReasons' => get_post_meta($opp->ID, '_match_reasons', true) ?: [],
                'source' => $source,
                'time' => human_time_diff(get_post_time('U', false, $opp->ID), current_time('timestamp')) . ' ago'
            ];
        }

        wp_send_json_success($data);
    }

    /**
     * Get human-readable label for opportunity source
     */
    private function get_opportunity_source_label($source) {
        $labels = [
            'recruiter_interest' => 'Interested in your profile',
            'profile_view' => 'Viewed your profile',
            'campaign_match' => 'Campaign match'
        ];
        return $labels[$source] ?? 'Recruiter interest';
    }

    /**
     * AJAX: Load single opportunity detail
     */
    public function ajax_nrt_load_opportunity_detail() {
        check_ajax_referer('sffc_public_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('Please sign in to view opportunity details.');
        }

        $opp_id = isset($_POST['opportunity_id']) ? intval($_POST['opportunity_id']) : 0;
        if (!$opp_id) {
            wp_send_json_error('Invalid opportunity ID.');
        }

        $opp = get_post($opp_id);
        if (!$opp || $opp->post_type !== 'sffc_candidate_opp') {
            wp_send_json_error('Opportunity not found.');
        }

        // Verify user owns this opportunity
        $candidate_id = get_post_meta($opp_id, '_candidate_user_id', true);
        if ((int) $candidate_id !== get_current_user_id()) {
            wp_send_json_error('Access denied.');
        }

        $source = get_post_meta($opp_id, '_source', true) ?: 'recruiter_interest';
        $is_new = get_post_meta($opp_id, '_is_new', true);

        wp_send_json_success([
            'id' => $opp->ID,
            'title' => $opp->post_title,
            'company' => get_post_meta($opp_id, '_company', true),
            'companyLogo' => null,
            'location' => get_post_meta($opp_id, '_location', true),
            'salary' => get_post_meta($opp_id, '_salary', true),
            'isNew' => ($is_new === '1'),
            'isSaved' => (bool) get_post_meta($opp_id, '_is_saved', true),
            'recruiter' => [
                'name' => get_post_meta($opp_id, '_recruiter_name', true),
                'title' => get_post_meta($opp_id, '_recruiter_title', true),
                'company' => get_post_meta($opp_id, '_recruiter_company', true),
                'avatar' => null,
                'status' => get_post_meta($opp_id, '_recruiter_status', true) ?: $this->get_opportunity_source_label($source)
            ],
            'matchReasons' => get_post_meta($opp_id, '_match_reasons', true) ?: [],
            'source' => $source,
            'notes' => $opp->post_content
        ]);
    }

    /**
     * AJAX: Toggle opportunity saved status
     */
    public function ajax_nrt_save_opportunity() {
        check_ajax_referer('sffc_public_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('Please sign in.');
        }

        $opp_id = isset($_POST['opportunity_id']) ? intval($_POST['opportunity_id']) : 0;
        if (!$opp_id) {
            wp_send_json_error('Invalid opportunity ID.');
        }

        // Verify user owns this opportunity
        $candidate_id = get_post_meta($opp_id, '_candidate_user_id', true);
        if ((int) $candidate_id !== get_current_user_id()) {
            wp_send_json_error('Access denied.');
        }

        $current = (bool) get_post_meta($opp_id, '_is_saved', true);
        update_post_meta($opp_id, '_is_saved', !$current);

        wp_send_json_success([
            'saved' => !$current
        ]);
    }

    /**
     * AJAX: Dismiss an opportunity
     */
    public function ajax_nrt_dismiss_opportunity() {
        check_ajax_referer('sffc_public_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('Please sign in.');
        }

        $opp_id = isset($_POST['opportunity_id']) ? intval($_POST['opportunity_id']) : 0;
        if (!$opp_id) {
            wp_send_json_error('Invalid opportunity ID.');
        }

        // Verify user owns this opportunity
        $candidate_id = get_post_meta($opp_id, '_candidate_user_id', true);
        if ((int) $candidate_id !== get_current_user_id()) {
            wp_send_json_error('Access denied.');
        }

        // Mark as dismissed (archived)
        update_post_meta($opp_id, '_status', 'dismissed');
        wp_update_post([
            'ID' => $opp_id,
            'post_status' => 'draft'
        ]);

        wp_send_json_success(['dismissed' => true]);
    }

    /**
     * =====================================================
     * RECRUITER POSTS AJAX HANDLERS
     * Public posts curated by admin on behalf of recruiters
     * =====================================================
     */

    /**
     * AJAX: Load recruiter posts (public - no login required)
     */
    public function ajax_nrt_load_recruiter_posts() {
        check_ajax_referer('sffc_public_nonce', 'nonce');

        $filter = isset($_POST['filter']) ? sanitize_text_field($_POST['filter']) : 'all';
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 20;

        // Build query args
        $args = [
            'posts_per_page' => $limit,
        ];

        // Filter logic
        if ($filter === 'featured') {
            $args['meta_query'] = [
                [
                    'key' => '_is_featured',
                    'value' => '1',
                    'compare' => '='
                ]
            ];
        } elseif ($filter === 'recent') {
            $args['date_query'] = [
                [
                    'after' => '1 week ago'
                ]
            ];
        }

        // Use the CPT class to get posts
        if (class_exists('SFFC_Recruiter_Posts')) {
            $posts = SFFC_Recruiter_Posts::get_posts($args);
        } else {
            $posts = [];
        }

        // Format for frontend
        $data = [];
        foreach ($posts as $post) {
            // Format salary string
            $salary_str = '';
            if (!empty($post['salary_min']) || !empty($post['salary_max'])) {
                $currency = $post['salary_currency'] ?: 'AED';
                if (!empty($post['salary_min']) && !empty($post['salary_max'])) {
                    $salary_str = $currency . ' ' . number_format($post['salary_min']) . ' - ' . number_format($post['salary_max']);
                } elseif (!empty($post['salary_min'])) {
                    $salary_str = 'From ' . $currency . ' ' . number_format($post['salary_min']);
                } elseif (!empty($post['salary_max'])) {
                    $salary_str = 'Up to ' . $currency . ' ' . number_format($post['salary_max']);
                }
            }

            // Calculate time ago
            $post_date = strtotime($post['date']);
            $time_ago = human_time_diff($post_date, current_time('timestamp')) . ' ago';

            $data[] = [
                'id' => $post['id'],
                'title' => $post['title'],
                'jobTitle' => $post['job_title'] ?: $post['title'],
                'company' => $post['company_name'] ?: 'Confidential',
                'location' => $post['job_location'],
                'salary' => $salary_str,
                'experience' => $post['experience_years'],
                'isNew' => (strtotime($post['date']) > strtotime('-3 days')),
                'isFeatured' => $post['is_featured'],
                'isUrgent' => $post['is_urgent'],
                'recruiter' => [
                    'name' => $post['recruiter_name'],
                    'title' => $post['recruiter_title'],
                    'company' => $post['recruiter_company'],
                    'linkedin' => $post['recruiter_linkedin'],
                ],
                'industries' => $post['industries'],
                'postType' => $post['post_type'],
                'time' => $time_ago
            ];
        }

        wp_send_json_success($data);
    }

    /**
     * AJAX: Load single recruiter post detail
     */
    public function ajax_nrt_load_recruiter_post_detail() {
        check_ajax_referer('sffc_public_nonce', 'nonce');

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if (!$post_id) {
            wp_send_json_error('Invalid post ID.');
        }

        // Use the CPT class to get post
        if (!class_exists('SFFC_Recruiter_Posts')) {
            wp_send_json_error('Recruiter posts not available.');
        }

        $post = SFFC_Recruiter_Posts::get_post($post_id);
        if (!$post) {
            wp_send_json_error('Post not found.');
        }

        // Format salary string
        $salary_str = '';
        if (!empty($post['salary_min']) || !empty($post['salary_max'])) {
            $currency = $post['salary_currency'] ?: 'AED';
            if (!empty($post['salary_min']) && !empty($post['salary_max'])) {
                $salary_str = $currency . ' ' . number_format($post['salary_min']) . ' - ' . number_format($post['salary_max']);
            } elseif (!empty($post['salary_min'])) {
                $salary_str = 'From ' . $currency . ' ' . number_format($post['salary_min']);
            } elseif (!empty($post['salary_max'])) {
                $salary_str = 'Up to ' . $currency . ' ' . number_format($post['salary_max']);
            }
        }

        // Parse key requirements into array
        $requirements = [];
        if (!empty($post['key_requirements'])) {
            $requirements = array_filter(array_map('trim', explode("\n", $post['key_requirements'])));
        }

        // Calculate time ago
        $post_date = strtotime($post['date']);
        $time_ago = human_time_diff($post_date, current_time('timestamp')) . ' ago';

        wp_send_json_success([
            'id' => $post['id'],
            'title' => $post['title'],
            'content' => wpautop($post['content']),
            'jobTitle' => $post['job_title'] ?: $post['title'],
            'company' => $post['company_name'] ?: 'Confidential',
            'location' => $post['job_location'],
            'salary' => $salary_str,
            'experience' => $post['experience_years'],
            'requirements' => $requirements,
            'idealBackground' => $post['ideal_background'],
            'isNew' => (strtotime($post['date']) > strtotime('-3 days')),
            'isFeatured' => $post['is_featured'],
            'isUrgent' => $post['is_urgent'],
            'recruiter' => [
                'name' => $post['recruiter_name'],
                'title' => $post['recruiter_title'],
                'company' => $post['recruiter_company'],
                'email' => is_user_logged_in() ? $post['recruiter_email'] : '',
                'linkedin' => $post['recruiter_linkedin'],
            ],
            'industries' => $post['industries'],
            'postType' => $post['post_type'],
            'time' => $time_ago
        ]);
    }

    /**
     * =====================================================
     * CANDIDATE CONVERSATIONS AJAX HANDLERS
     * =====================================================
     */

    /**
     * AJAX: Load conversations for current user
     */
    public function ajax_nrt_load_conversations() {
        check_ajax_referer('sffc_public_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_success([]); // Return empty array for logged-out users
        }

        $user_id = get_current_user_id();

        $conversations = get_posts([
            'post_type' => 'sffc_candidate_conv',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => '_candidate_user_id',
                    'value' => $user_id,
                    'compare' => '='
                ]
            ],
            'orderby' => 'meta_value',
            'meta_key' => '_last_message_date',
            'order' => 'DESC'
        ]);

        $data = [];
        foreach ($conversations as $conv) {
            // Get messages for this conversation
            $messages = [];
            if (class_exists('SFFC_Candidate_Conversations_Database')) {
                $db_messages = SFFC_Candidate_Conversations_Database::get_messages($conv->ID);
                foreach ($db_messages as $msg) {
                    $messages[] = [
                        'id' => $msg->id,
                        'from' => ($msg->sender_type === 'candidate') ? 'me' : 'them',
                        'text' => $msg->message_text,
                        'time' => human_time_diff(strtotime($msg->sent_at), current_time('timestamp')) . ' ago'
                    ];
                }
            }

            $last_message_date = get_post_meta($conv->ID, '_last_message_date', true);
            $time_display = $last_message_date ? human_time_diff(strtotime($last_message_date), current_time('timestamp')) . ' ago' : '';

            $data[] = [
                'id' => $conv->ID,
                'contact' => [
                    'name' => get_post_meta($conv->ID, '_contact_name', true),
                    'title' => get_post_meta($conv->ID, '_contact_title', true),
                    'company' => get_post_meta($conv->ID, '_contact_company', true),
                    'avatar' => null
                ],
                'lastMessage' => get_post_meta($conv->ID, '_last_message_preview', true),
                'time' => $time_display,
                'isUnread' => (bool) get_post_meta($conv->ID, '_is_unread', true),
                'isStarred' => (bool) get_post_meta($conv->ID, '_is_starred', true),
                'messages' => $messages
            ];
        }

        wp_send_json_success($data);
    }

    /**
     * AJAX: Load messages for a specific conversation
     */
    public function ajax_nrt_load_conversation_messages() {
        check_ajax_referer('sffc_public_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('Please sign in.');
        }

        $conv_id = isset($_POST['conversation_id']) ? intval($_POST['conversation_id']) : 0;
        if (!$conv_id) {
            wp_send_json_error('Invalid conversation ID.');
        }

        // Verify user owns this conversation
        $candidate_id = get_post_meta($conv_id, '_candidate_user_id', true);
        if ((int) $candidate_id !== get_current_user_id()) {
            wp_send_json_error('Access denied.');
        }

        $messages = [];
        if (class_exists('SFFC_Candidate_Conversations_Database')) {
            $db_messages = SFFC_Candidate_Conversations_Database::get_messages($conv_id);
            foreach ($db_messages as $msg) {
                $messages[] = [
                    'id' => $msg->id,
                    'from' => ($msg->sender_type === 'candidate') ? 'me' : 'them',
                    'text' => $msg->message_text,
                    'time' => date('M j, g:i A', strtotime($msg->sent_at))
                ];
            }

            // Mark as read
            SFFC_Candidate_Conversations_Database::mark_conversation_read($conv_id);
        }

        wp_send_json_success($messages);
    }

    /**
     * AJAX: Send a message in a conversation
     */
    public function ajax_nrt_send_message() {
        check_ajax_referer('sffc_public_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('Please sign in.');
        }

        $conv_id = isset($_POST['conversation_id']) ? intval($_POST['conversation_id']) : 0;
        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';

        if (!$conv_id || empty($message)) {
            wp_send_json_error('Invalid request.');
        }

        // Verify user owns this conversation
        $candidate_id = get_post_meta($conv_id, '_candidate_user_id', true);
        $user_id = get_current_user_id();
        if ((int) $candidate_id !== $user_id) {
            wp_send_json_error('Access denied.');
        }

        if (!class_exists('SFFC_Candidate_Conversations_Database')) {
            wp_send_json_error('Messaging system unavailable.');
        }

        $message_id = SFFC_Candidate_Conversations_Database::add_message(
            $conv_id,
            'candidate',
            $user_id,
            $message
        );

        if ($message_id) {
            wp_send_json_success([
                'id' => $message_id,
                'from' => 'me',
                'text' => $message,
                'time' => 'Just now'
            ]);
        } else {
            wp_send_json_error('Failed to send message.');
        }
    }

    /**
     * AJAX: Mark conversation as read
     */
    public function ajax_nrt_mark_conversation_read() {
        check_ajax_referer('sffc_public_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('Please sign in.');
        }

        $conv_id = isset($_POST['conversation_id']) ? intval($_POST['conversation_id']) : 0;
        if (!$conv_id) {
            wp_send_json_error('Invalid conversation ID.');
        }

        // Verify user owns this conversation
        $candidate_id = get_post_meta($conv_id, '_candidate_user_id', true);
        if ((int) $candidate_id !== get_current_user_id()) {
            wp_send_json_error('Access denied.');
        }

        if (class_exists('SFFC_Candidate_Conversations_Database')) {
            SFFC_Candidate_Conversations_Database::mark_conversation_read($conv_id);
        }

        update_post_meta($conv_id, '_is_unread', 0);

        wp_send_json_success(['read' => true]);
    }

    /**
     * AJAX: Toggle conversation starred status
     */
    public function ajax_nrt_toggle_conversation_star() {
        check_ajax_referer('sffc_public_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('Please sign in.');
        }

        $conv_id = isset($_POST['conversation_id']) ? intval($_POST['conversation_id']) : 0;
        if (!$conv_id) {
            wp_send_json_error('Invalid conversation ID.');
        }

        // Verify user owns this conversation
        $candidate_id = get_post_meta($conv_id, '_candidate_user_id', true);
        if ((int) $candidate_id !== get_current_user_id()) {
            wp_send_json_error('Access denied.');
        }

        $current = (bool) get_post_meta($conv_id, '_is_starred', true);
        update_post_meta($conv_id, '_is_starred', !$current);

        wp_send_json_success([
            'starred' => !$current
        ]);
    }
}

// Disabled at bootstrap.
