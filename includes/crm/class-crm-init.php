<?php
/**
 * CRM Initialization
 * Main entry point for the Candidate-to-Recruiter CRM system
 *
 * @package SennaCareers
 * @since 7.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_Init {

    private static $instance = null;
    private $version = '1.0.0';
    private const PORTAL_PAGES_VERSION = '1';
    private const POSTS_RETENTION_HOOK = 'sffc_crm_cleanup_old_posts';
    private const POSTS_RETENTION_HOUR = 22;
    private const POSTS_RETENTION_MINUTE = 20;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required files
     */
    private function load_dependencies() {
        $crm_path = plugin_dir_path(__FILE__);

        // Database
        require_once $crm_path . 'class-crm-database-schema.php';

        // Core Models
        require_once $crm_path . 'models/class-crm-recruiter.php';
        require_once $crm_path . 'models/class-crm-post.php';
        require_once $crm_path . 'models/class-crm-job-draft.php';
        require_once $crm_path . 'models/class-crm-pipeline.php';
        require_once $crm_path . 'models/class-crm-applicant.php';
        require_once $crm_path . 'models/class-crm-cv.php';
        require_once $crm_path . 'models/class-crm-email-account.php';
        require_once $crm_path . 'models/class-crm-template.php';
        require_once $crm_path . 'models/class-crm-outreach.php';
        require_once $crm_path . 'models/class-crm-expert-outreach.php';
        require_once $crm_path . 'models/class-crm-hr-outreach.php';
        require_once $crm_path . 'models/class-crm-resource-library.php';

        // Keyword Extractor
        require_once $crm_path . 'class-crm-keyword-extractor.php';
        require_once dirname($crm_path) . '/class-crm-job-title-normalizer.php';

        // Job Preferences Helper Functions
        require_once $crm_path . 'functions-job-preferences.php';

        // Phase 4: Sequence Models
        require_once $crm_path . 'models/class-crm-sequence.php';
        require_once $crm_path . 'models/class-crm-sequence-enrollment.php';
        require_once $crm_path . 'models/class-crm-task.php';

        // Phase 5: Inbox/Conversations Models
        require_once $crm_path . 'models/class-crm-conversation.php';
        require_once $crm_path . 'models/class-crm-message.php';

        // Phase 6: Analytics & Intelligence Models
        require_once $crm_path . 'models/class-crm-analytics.php';
        require_once $crm_path . 'models/class-crm-alert.php';

        // Services
        require_once $crm_path . 'class-crm-memberpress-integration.php';
        require_once $crm_path . 'class-crm-sequence-processor.php';
        require_once $crm_path . 'class-crm-email-service.php';
        require_once $crm_path . 'class-crm-email-digest.php';
        require_once $crm_path . 'class-crm-free-alert-digest.php';
        require_once $crm_path . 'class-crm-premium-match-alerts.php';
        require_once $crm_path . 'class-crm-sendgrid-service.php';
        require_once $crm_path . 'class-crm-sendgrid-webhook.php';
        require_once $crm_path . 'class-crm-internship-alert-queue.php';
        require_once $crm_path . 'class-crm-rate-limiter.php';
        require_once $crm_path . 'class-crm-job-scanner.php';

        // Admin
        if (is_admin()) {
            require_once $crm_path . 'admin/class-crm-admin.php';
            require_once $crm_path . 'admin/class-crm-post-curation.php';
            require_once $crm_path . 'admin/class-crm-job-draft-admin.php';
            require_once $crm_path . 'admin/class-crm-signup-form-settings.php';
        }

        // AJAX handlers
        require_once $crm_path . 'ajax/class-crm-ajax-handlers.php';

        // Shortcodes
        require_once $crm_path . 'class-crm-shortcodes.php';

        // Shared helpers
        require_once $crm_path . 'functions-gap-analyzer.php';

        // Post Type (URL routing for viewable CRM posts)
        require_once $crm_path . 'class-crm-post-type.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Database setup on activation
        register_activation_hook(SFFC_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(SFFC_PLUGIN_FILE, [$this, 'deactivate']);

        // Initialize database on init
        add_action('init', [$this, 'init_database'], 5);

        // Enqueue assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // REST API
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        // Initialize email digest system
        add_action('init', [$this, 'init_email_digest'], 15);

        // Public endpoints for tracking + OAuth callbacks
        add_action('init', [$this, 'handle_email_tracking_requests'], 1);
        add_action('init', [$this, 'handle_email_oauth_redirect'], 20);
        add_action('init', [$this, 'maybe_ensure_crm_portal_pages'], 30);

        // Keep historical approved/open CRM posts available in job trackers.
        add_action('init', [$this, 'disable_old_posts_cleanup'], 12);
    }

    /**
     * Initialize email digest system
     */
    public function init_email_digest() {
        SFFC_CRM_Email_Digest::get_instance();
        SFFC_CRM_Free_Alert_Digest::get_instance();
        SFFC_CRM_Premium_Match_Alerts::get_instance();
        SFFC_CRM_SendGrid_Webhook::get_instance();
        SFFC_CRM_Internship_Alert_Queue::get_instance();
        SFFC_CRM_AJAX_Handlers::get_instance();
    }

    /**
     * Handle tracking pixels and CV views.
     */
    public function handle_email_tracking_requests() {
        if (empty($_GET['sffc_crm_track']) || empty($_GET['tid'])) {
            return;
        }

        $type = sanitize_key(wp_unslash($_GET['sffc_crm_track']));
        $tracking_id = sanitize_text_field(wp_unslash($_GET['tid']));
        $outreach_model = new SFFC_CRM_Outreach();

        if ($type === 'open') {
            $outreach_model->record_open($tracking_id);
            $this->output_tracking_pixel();
            exit;
        }

        if ($type === 'cv') {
            $cv_id = isset($_GET['cv']) ? sanitize_text_field(wp_unslash($_GET['cv'])) : '';
            $this->render_tracked_cv($outreach_model, $tracking_id, $cv_id);
            exit;
        }
    }

    private function output_tracking_pixel() {
        nocache_headers();
        header('Content-Type: image/gif');
        echo base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==');
    }

    private function render_tracked_cv($outreach_model, $tracking_id, $cv_id) {
        if (!$cv_id) {
            status_header(404);
            echo '<!doctype html><html><body><p>CV unavailable.</p></body></html>';
            return;
        }

        $outreach = $outreach_model->record_cv_view($tracking_id);
        if (!$outreach) {
            status_header(404);
            echo '<!doctype html><html><body><p>CV unavailable.</p></body></html>';
            return;
        }

        $cv_manager = new SFFC_CRM_CV_Manager();
        $cv_entry = $cv_manager->get_cv_by_id(intval($outreach['user_id']), $cv_id);
        if (!$cv_entry) {
            status_header(404);
            echo '<!doctype html><html><body><p>CV not found.</p></body></html>';
            return;
        }

        $user = get_user_by('id', $outreach['user_id']);
        $name = $user ? $user->display_name : 'MENA Careers Candidate';
        $content = nl2br(esc_html($cv_entry['content']));

        nocache_headers();
        status_header(200);
        echo '<!doctype html><html><head><meta charset="utf-8">';
        echo '<title>' . esc_html($name) . ' – CV</title>';
        echo '<style>body{font-family:Helvetica,Arial,sans-serif;background:#f6f8f7;margin:0;padding:40px;}';
        echo '.cv-card{max-width:720px;margin:0 auto;background:#fff;border-radius:20px;padding:32px;box-shadow:0 20px 60px rgba(13,53,62,0.1);}';
        echo '.cv-card h1{margin:0 0 10px;font-size:28px;color:#0d353e;}';
        echo '.cv-card .meta{color:#5e7a82;margin-bottom:24px;}';
        echo '.cv-card .content{line-height:1.7;color:#1b2f33;white-space:pre-line;}</style>';
        echo '</head><body><div class="cv-card">';
        echo '<h1>' . esc_html($name) . '</h1>';
        echo '<div class="meta">Shared via MENA Careers Recruiter Outreach</div>';
        echo '<div class="content">' . $content . '</div>';
        echo '</div></body></html>';
    }

    /**
     * Handle OAuth redirect window for email providers.
     */
    public function handle_email_oauth_redirect() {
        if (empty($_GET['sffc_crm_oauth'])) {
            return;
        }

        $provider = isset($_GET['provider']) ? sanitize_key(wp_unslash($_GET['provider'])) : '';
        $state = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';
        $code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';
        $error = isset($_GET['error']) ? sanitize_text_field(wp_unslash($_GET['error'])) : '';
        $description = isset($_GET['error_description']) ? sanitize_text_field(wp_unslash($_GET['error_description'])) : '';

        $service = SFFC_CRM_Email_Service::get_instance();
        if ($error) {
            $result = new WP_Error('oauth_error', $description ?: $error);
        } else {
            $result = $service->handle_oauth_callback($provider, $state, $code);
        }

        $this->render_oauth_response($result);
        exit;
    }

    public function maybe_ensure_crm_portal_pages() {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        if (get_option('sffc_crm_portal_pages_version') === self::PORTAL_PAGES_VERSION) {
            return;
        }

        $this->ensure_crm_portal_pages();
        update_option('sffc_crm_portal_pages_version', self::PORTAL_PAGES_VERSION, false);
    }

    public function ensure_crm_portal_pages() {
        if (!function_exists('get_page_by_path')) {
            return;
        }

        $this->maybe_create_crm_page('senna-recruiter-outreach', 'MENA Careers Recruiter Outreach', '[sffc_crm]');
        $this->maybe_create_crm_page('sffc-recruiter-outreach', 'Recruiter Outreach CRM', '[sffc_crm default_tab="recruiter-intros"]');
    }

    private function maybe_create_crm_page($slug, $title, $shortcode) {
        $existing = get_page_by_path($slug, OBJECT, 'page');
        if ($existing instanceof WP_Post) {
            return;
        }

        wp_insert_post([
            'post_title'   => $title,
            'post_name'    => $slug,
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_content' => $shortcode,
        ]);
    }

    private function render_oauth_response($result) {
        $status = is_wp_error($result) ? 'error' : 'success';
        $message = is_wp_error($result) ? $result->get_error_message() : __('Email connected successfully. You can close this window.', 'senna-finance');
        nocache_headers();
        echo '<!doctype html><html><head><meta charset="utf-8"><title>MENA Careers CRM</title><style>body{font-family:Helvetica,Arial,sans-serif;background:#0d353e;color:#fff;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}';
        echo '.panel{background:#fff;color:#0d353e;padding:32px;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,0.25);max-width:420px;text-align:center;}';
        echo '.panel h1{margin-top:0;font-size:24px;}';
        echo '.panel p{margin-bottom:24px;line-height:1.5;}';
        echo '.panel button{background:#0d353e;color:#fff;border:none;border-radius:999px;padding:10px 28px;font-size:15px;cursor:pointer;}';
        echo '</style></head><body><div class="panel">';
        echo '<h1>' . ($status === 'success' ? 'Email Connected' : 'Connection Error') . '</h1>';
        echo '<p>' . esc_html($message) . '</p>';
        echo '<button onclick="window.close()">Close Window</button>';
        echo '</div><script>if (window.opener){window.opener.postMessage({type:"sffc-crm-oauth",status:"' . esc_js($status) . '"}, window.location.origin);}';
        echo 'setTimeout(function(){window.close();}, 2500);</script></body></html>';
    }

    /**
     * Plugin activation
     */
    public function activate() {
        $db = SFFC_CRM_Database_Schema::get_instance();
        $db->create_tables();
        $this->disable_old_posts_cleanup();
    }

    public function deactivate() {
        wp_clear_scheduled_hook(self::POSTS_RETENTION_HOOK);
    }

    /**
     * Initialize database
     */
    public function init_database() {
        $db = SFFC_CRM_Database_Schema::get_instance();
        $db->check_db_version();
    }

    public function ensure_old_posts_cleanup_scheduled() {
        $scheduled = wp_next_scheduled(self::POSTS_RETENTION_HOOK);
        $desired_timestamp = $this->get_next_old_posts_cleanup_timestamp();

        if ($scheduled) {
            $scheduled_local = wp_date('H:i', $scheduled, wp_timezone());
            $desired_local = sprintf('%02d:%02d', self::POSTS_RETENTION_HOUR, self::POSTS_RETENTION_MINUTE);

            if ($scheduled_local !== $desired_local) {
                wp_clear_scheduled_hook(self::POSTS_RETENTION_HOOK);
                $scheduled = false;
            }
        }

        if (!$scheduled) {
            wp_schedule_event($desired_timestamp, 'daily', self::POSTS_RETENTION_HOOK);
        }
    }

    public function disable_old_posts_cleanup() {
        if (wp_next_scheduled(self::POSTS_RETENTION_HOOK)) {
            wp_clear_scheduled_hook(self::POSTS_RETENTION_HOOK);
        }
    }

    private function get_next_old_posts_cleanup_timestamp() {
        $now = current_datetime();
        $next_run = $now->setTime(self::POSTS_RETENTION_HOUR, self::POSTS_RETENTION_MINUTE, 0);

        if ($next_run <= $now) {
            $next_run = $next_run->modify('+1 day');
        }

        return $next_run->getTimestamp();
    }

    public function cleanup_old_crm_posts() {
        $this->disable_old_posts_cleanup();
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        global $post;

        $plugin_url = plugin_dir_url(dirname(dirname(__FILE__)));

        $linkedin_page = $this->is_linkedin_crm_page();

        // Check if carousel shortcode is present
        $has_carousel = false;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'sffc_recruiter_carousel')) {
            $has_carousel = true;
        }

        // Load carousel assets if shortcode present
        if ($has_carousel) {
            wp_enqueue_style(
                'sffc-crm-styles',
                $plugin_url . 'assets/css/crm/crm-main.css',
                [],
                $this->version
            );

            wp_enqueue_style(
                'sffc-recruiter-post-article',
                $plugin_url . 'assets/css/recruiter-post-article.css',
                [],
                $this->version
            );

            wp_enqueue_style(
                'sffc-recruiter-carousel',
                $plugin_url . 'assets/css/crm/recruiter-carousel.css',
                ['sffc-crm-styles', 'sffc-recruiter-post-article'],
                $this->version
            );

            wp_enqueue_script(
                'sffc-recruiter-carousel',
                $plugin_url . 'assets/js/crm/recruiter-carousel.js',
                ['jquery'],
                $this->version,
                true
            );
        }

        if ($linkedin_page) {
            $this->enqueue_linkedin_assets($plugin_url);
        }

        // Only load full CRM on CRM pages
        if (!$linkedin_page && !$this->is_crm_page()) {
            return;
        }

        if ($linkedin_page) {
            return;
        }

        wp_enqueue_style(
            'sffc-crm-styles',
            $plugin_url . 'assets/css/crm/crm-main.css',
            [],
            $this->version
        );

        // Gap analyzer dependencies (ensure modal experience always has assets + script globals)
        wp_enqueue_style(
            'inst-article',
            $plugin_url . 'assets/css/institutional-article.css',
            [],
            SFFC_VERSION
        );

        wp_enqueue_style(
            'sffc-gap-analyzer',
            $plugin_url . 'assets/css/gap-analyzer.css',
            ['inst-article'],
            SFFC_VERSION
        );

        wp_enqueue_script(
            'inst-article',
            $plugin_url . 'assets/js/institutional-article.js',
            [],
            SFFC_VERSION,
            true
        );

        wp_enqueue_script(
            'sffc-gap-analyzer',
            $plugin_url . 'assets/js/gap-analyzer.js',
            ['jquery', 'inst-article'],
            SFFC_VERSION,
            true
        );

        $gap_can_analyze = $this->user_has_premium_access();
        wp_localize_script('sffc-gap-analyzer', 'sffc_gap_analyzer', [
            'ajax_url'     => admin_url('admin-ajax.php'),
            'nonce'        => wp_create_nonce('sffc_gap_analyzer_nonce'),
            'can_analyze'  => $gap_can_analyze,
            'is_logged_in' => is_user_logged_in(),
            'membership_url'  => $this->get_membership_url(),
            'login_url'    => wp_login_url(),
        ]);

        wp_enqueue_script(
            'sffc-crm-app',
            $plugin_url . 'assets/js/crm/crm-app.js',
            ['jquery', 'jquery-ui-sortable'],
            $this->version,
            true
        );

        $default_guest_avatar = 'https://media.joinsenna.com/2025/05/bb-profile-avatar-buddyboss.webp?1748222731';
        $localized_user = wp_get_current_user();
        $avatar_initial_source = '';
        $avatar_initial = 'S';
        $custom_avatar_key = class_exists('SFFC_User_Preferences') ? SFFC_User_Preferences::META_PROFILE_AVATAR : 'sffc_profile_avatar';
        $custom_avatar_value = '';
        $avatar_url = $default_guest_avatar;

        if ($localized_user instanceof WP_User && $localized_user->ID) {
            $avatar_initial_source = $localized_user->first_name ?: ($localized_user->display_name ?: $localized_user->user_login);
            $custom_avatar_value = get_user_meta($localized_user->ID, $custom_avatar_key, true);
            $gravatar_data = get_avatar_data($localized_user->ID, ['size' => 256, 'default' => '404']);
            $gravatar_candidate = (!empty($gravatar_data['found_avatar']) && !empty($gravatar_data['url'])) ? $gravatar_data['url'] : '';
            if (!empty($custom_avatar_value)) {
                $avatar_url = $custom_avatar_value;
            } elseif (!empty($gravatar_candidate)) {
                $avatar_url = $gravatar_candidate;
            } else {
                $avatar_url = '';
            }
        }

        if ($avatar_initial_source) {
            $avatar_initial = strtoupper(function_exists('mb_substr') ? mb_substr($avatar_initial_source, 0, 1) : substr($avatar_initial_source, 0, 1));
        }
        if (!$avatar_initial) {
            $avatar_initial = 'S';
        }

        if (!$avatar_url) {
            $avatar_url = $default_guest_avatar;
        }

        wp_localize_script('sffc-crm-app', 'sffcCRM', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('sffc-crm/v1/'),
            'nonce' => wp_create_nonce('sffc_crm_nonce'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'userId' => get_current_user_id(),
            'isLoggedIn' => is_user_logged_in(),
            'isPremium' => $this->user_has_premium_access(),
            'emailMode' => $this->get_email_mode(),
            'membershipUrl' => $this->get_membership_url(),
            'loginUrl' => wp_login_url(),
            'gapAnalyzer' => [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sffc_gap_analyzer_nonce'),
                'is_logged_in' => is_user_logged_in(),
                'upgrade_url' => $this->get_membership_url(),
                'login_url' => wp_login_url(),
            ],
            'avatarUpload' => [
                'enabled' => is_user_logged_in(),
                'nonce' => wp_create_nonce('sffc_crm_avatar_nonce'),
                'maxSize' => 2 * 1024 * 1024,
                'maxSizeLabel' => '2MB',
                'allowedTypes' => ['image/jpeg', 'image/png', 'image/webp'],
                'defaultImage' => $default_guest_avatar,
                'currentImage' => $avatar_url,
                'initial' => $avatar_initial,
            ],
            'features' => $this->get_user_features(),
            'limits' => $this->get_user_limits(),
            'strings' => [
                'saveSuccess' => __('Saved successfully', 'senna-finance'),
                'saveError' => __('Failed to save', 'senna-finance'),
                'confirmDelete' => __('Are you sure you want to delete this?', 'senna-finance'),
                'loading' => __('Loading...', 'senna-finance'),
                'noResults' => __('No results found', 'senna-finance'),
            ]
        ]);
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on CRM admin pages
        if (strpos($hook, 'sffc-crm') === false) {
            return;
        }

        $plugin_url = trailingslashit(SFFC_PLUGIN_URL);
        $plugin_path = trailingslashit(SFFC_PLUGIN_DIR);

        $admin_css_path = $plugin_path . 'assets/css/crm/crm-admin.css';
        $admin_js_path = $plugin_path . 'assets/js/crm/crm-admin.js';

        $admin_css_version = $this->version;
        $admin_js_version = $this->version;

        if (file_exists($admin_css_path)) {
            $admin_css_version .= '-' . filemtime($admin_css_path);
        }

        if (file_exists($admin_js_path)) {
            $admin_js_version .= '-' . filemtime($admin_js_path);
        }

        wp_enqueue_style(
            'sffc-crm-admin-styles',
            $plugin_url . 'assets/css/crm/crm-admin.css',
            [],
            $admin_css_version
        );

        wp_enqueue_script(
            'sffc-crm-admin',
            $plugin_url . 'assets/js/crm/crm-admin.js',
            ['jquery', 'jquery-ui-sortable'],
            $admin_js_version,
            true
        );
    }

    private function enqueue_linkedin_assets($plugin_url) {
        wp_enqueue_style(
            'sffc-crm-linkedin',
            $plugin_url . 'assets/css/crm/crm-linkedin.css',
            [],
            $this->version . '-' . time()
        );

        // Learning Platform CSS
        wp_enqueue_style(
            'sffc-learning-platform',
            $plugin_url . 'assets/css/crm/learning-platform.css',
            ['sffc-crm-linkedin'],
            $this->version
        );

        // Course Viewer CSS
        wp_enqueue_style(
            'sffc-course-viewer',
            $plugin_url . 'assets/css/crm/course-viewer.css',
            ['sffc-crm-linkedin'],
            $this->version
        );

        // Lesson Viewer CSS
        wp_enqueue_style(
            'sffc-lesson-viewer',
            $plugin_url . 'assets/css/crm/lesson-viewer.css',
            ['sffc-crm-linkedin'],
            $this->version
        );

        // Mock Interview CSS
        wp_enqueue_style(
            'sffc-mock-interview',
            $plugin_url . 'assets/css/crm/mock-interview.css',
            ['sffc-crm-linkedin'],
            $this->version
        );

        // Profile Modals CSS
        wp_enqueue_style(
            'sffc-crm-profile-modals',
            $plugin_url . 'assets/css/crm/crm-profile-modals.css',
            ['sffc-crm-linkedin'],
            $this->version
        );

        // Prep Materials Modal CSS
        wp_enqueue_style(
            'sffc-crm-prep-modal',
            $plugin_url . 'assets/css/crm/crm-prep-modal.css',
            ['sffc-crm-linkedin'],
            $this->version
        );

        // Application Materials Modal CSS
        wp_enqueue_style(
            'sffc-crm-materials-modal',
            $plugin_url . 'assets/css/crm/crm-materials-modal.css',
            ['sffc-crm-linkedin'],
            $this->version
        );

        wp_enqueue_script(
            'sffc-crm-linkedin',
            $plugin_url . 'assets/js/crm/crm-linkedin.js',
            ['jquery'],
            $this->version . '-' . time(),
            true
        );

        // Profile Management JavaScript
        wp_enqueue_script(
            'sffc-crm-profile',
            $plugin_url . 'assets/js/crm/crm-profile.js',
            ['jquery', 'sffc-crm-linkedin'],
            $this->version,
            true
        );

        // Learning Platform JavaScript
        wp_enqueue_script(
            'sffc-learning-platform',
            $plugin_url . 'assets/js/crm/learning-platform.js',
            ['jquery', 'sffc-crm-linkedin'],
            $this->version,
            true
        );

        // Mock Interview JavaScript
        wp_enqueue_script(
            'sffc-mock-interview',
            $plugin_url . 'assets/js/crm/mock-interview.js',
            ['jquery', 'sffc-crm-linkedin', 'sffc-learning-platform'],
            $this->version,
            true
        );

        // HR Process Modal JavaScript
        wp_enqueue_script(
            'sffc-crm-hr-modal',
            $plugin_url . 'assets/js/crm/crm-hr-modal.js',
            [],
            $this->version,
            true
        );

        // Prep Materials Modal JavaScript
        wp_enqueue_script(
            'sffc-crm-prep-modal',
            $plugin_url . 'assets/js/crm/crm-prep-modal.js',
            ['jquery', 'sffc-crm-linkedin'],
            $this->version,
            true
        );

        wp_localize_script('sffc-crm-prep-modal', 'sffc_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sffc_ajax_nonce')
        ]);

        // Application Materials Modal JavaScript
        wp_enqueue_script(
            'sffc-crm-materials-modal',
            $plugin_url . 'assets/js/crm/crm-materials-modal.js',
            ['jquery', 'sffc-crm-linkedin'],
            $this->version,
            true
        );

        // Job Preferences & Matches
        wp_enqueue_script(
            'sffc-crm-job-preferences',
            get_template_directory_uri() . '/assets/js/crm-job-preferences.js',
            ['jquery'],
            filemtime(get_template_directory() . '/assets/js/crm-job-preferences.js'),
            true
        );

        wp_localize_script('sffc-crm-job-preferences', 'sffcCrm', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sffc_crm_nonce'),
            'isLoggedIn' => is_user_logged_in(),
        ]);

        $stage_meta = SFFC_CRM_Pipeline::get_stages();
        wp_localize_script('sffc-crm-linkedin', 'sffcCRMLinkedIn', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sffc_crm_nonce'),
            'signupNonce' => wp_create_nonce('sffc_crm_signup'),
            'avatarNonce' => wp_create_nonce('sffc_crm_avatar_nonce'),
            'isLoggedIn' => is_user_logged_in(),
            'isPremium' => $this->user_has_premium_access(),
            'currentUserName' => is_user_logged_in() ? wp_get_current_user()->display_name : '',
            'currentUserAvatar' => $avatar_url,
            'currentUserInitial' => $avatar_initial,
            'stages' => $stage_meta,
            'learningUrl' => home_url('/senna-ai/'),
            'loginUrl' => home_url('/join/'),
            'joinUrl' => home_url('/join/'),
            'membershipUrl' => $this->get_membership_url(),
            'upgradePlans' => $this->get_upgrade_plan_options(),
            'strings' => [
                'stageUpdated' => __('Status updated', 'senna-finance'),
                'stageError' => __('Unable to update status right now. Please try again.', 'senna-finance'),
                'hrLoading' => __('Loading contacts...', 'senna-finance'),
                'hrLoadError' => __('Unable to load more contacts right now. Please try again.', 'senna-finance'),
                'hrLoadAuth' => __('Please sign in to view these contacts.', 'senna-finance'),
                'hrLoadMore' => __('Load More Contacts', 'senna-finance'),
            ],
            'prep' => [
                'requestNonce' => wp_create_nonce('request_prep_materials'),
                'strings' => [
                    'success' => __('Thanks! Your request has been received.', 'senna-finance'),
                    'error' => __('Unable to send your request right now. Please try again.', 'senna-finance'),
                    'pendingLabel' => __('Pending approval', 'senna-finance'),
                    'pendingHelp' => __('Our prep team is packaging your kit.', 'senna-finance'),
                    'quickViewSuccess' => __('Request sent. We will email you shortly.', 'senna-finance'),
                    'quickViewError' => __('Unable to notify the prep team.', 'senna-finance'),
                    'quickViewCta' => __('Request Prep Materials', 'senna-finance'),
                ],
            ],
            'intros' => [
                'strings' => [
                    'success' => __('Request added to your intro queue.', 'senna-finance'),
                    'error' => __('Unable to request intro right now. Please try again.', 'senna-finance'),
                    'missingResume' => __('Upload a document and mark it as Resume/CV before requesting an intro.', 'senna-finance'),
                    'requested' => __('Requested', 'senna-finance'),
                    'cancelled' => __('Intro request cancelled.', 'senna-finance'),
                    'cancelError' => __('Unable to cancel this intro request right now.', 'senna-finance'),
                    'resumeMarked' => __('Document marked as Resume/CV.', 'senna-finance'),
                    'docTypeError' => __('Unable to update document type right now.', 'senna-finance'),
                ],
            ],
            'qa' => [
                'nonce' => wp_create_nonce('sffc_crm_submit_question'),
                'strings' => [
                    'success' => __('Thanks! One of our experts will reply shortly.', 'senna-finance'),
                    'error' => __('Unable to send your question right now. Please try again.', 'senna-finance'),
                    'pending' => __('Our in-house experts have been notified — answer coming shortly.', 'senna-finance'),
                    'pendingAnswer' => __('Our in-house experts have been notified — answer coming shortly.', 'senna-finance'),
                    'pendingExpert' => __('MENA Careers Expert Team', 'senna-finance'),
                    'pendingExpertTitle' => __('Awaiting response', 'senna-finance'),
                    'locked' => __('Sign in to view the full expert answer.', 'senna-finance'),
                    'justNow' => __('Just now', 'senna-finance'),
                    'verifiedLabel' => __('✓ Expert replied...', 'senna-finance'),
                ],
            ],
        ]);
    }

    /**
     * Check if current page is a CRM page
     */
    private function is_crm_page() {
        global $post;

        // Check for shortcode
        if ($post && has_shortcode($post->post_content, 'sffc_crm')) {
            return true;
        }

        if ($this->is_linkedin_crm_page()) {
            return true;
        }

        // Check for CRM page slugs
        $crm_slugs = ['senna-recruiter-outreach', 'sffc-recruiter-outreach', 'recruiter-crm', 'crm-feed', 'crm-pipeline', 'crm-inbox'];
        if (is_page($crm_slugs)) {
            return true;
        }

        return false;
    }

    private function is_linkedin_crm_page() {
        global $post;

        return $post && (
            has_shortcode($post->post_content, 'sffc_crm_linkedin')
        );
    }

    /**
     * Get user's available features based on membership
     */
    private function get_user_features() {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return [
                'ai_personalization' => false,
                'email_sending' => false,
                'email_tracking' => false,
                'sequences' => false,
                'email_sync' => false,
                'recruiter_intelligence' => false,
                'advanced_analytics' => false,
                'export' => false,
                'full_pipeline' => false,
            ];
        }

        return apply_filters('sffc_crm_features', [], $user_id);
    }

    /**
     * Get user's usage limits based on membership
     */
    private function get_user_limits() {
        $user_id = get_current_user_id();

        return [
            'outreach' => apply_filters('sffc_crm_outreach_limit', 5, $user_id),
            'sequences' => apply_filters('sffc_crm_sequence_limit', 0, $user_id),
            'recruiters' => apply_filters('sffc_crm_recruiter_limit', 20, $user_id),
            'posts_per_day' => apply_filters('sffc_crm_posts_limit', 50, $user_id),
        ];
    }

    private function user_has_premium_access() {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return false;
        }

        // Admin users always have premium access
        if (user_can($user_id, 'manage_options')) {
            return true;
        }

        // Prefer CRM tiering so "basic" or $0 plans do not unlock premium tools
        if (class_exists('SFFC_CRM_MemberPress_Integration')) {
            $crm_integration = SFFC_CRM_MemberPress_Integration::get_instance();
            $tier = $crm_integration->get_crm_tier($user_id);

            if (in_array($tier, ['insider', 'pro'], true)) {
                return true;
            }

            if ($tier === 'basic' || $tier === 'free') {
                return false;
            }
        }

        if (!class_exists('SFFC_MemberPress_Integration')) {
            return false;
        }

        $mepr = SFFC_MemberPress_Integration::get_instance();
        if ($mepr->has_premium_access($user_id, 'insider') || $mepr->has_premium_access($user_id, 'pro')) {
            return true;
        }

        return false;
    }

    private function get_membership_url() {
        return apply_filters('sffc_crm_membership_url', 'https://joinsenna.com/memberships/');
    }

    private function get_upgrade_plan_options() {
        $plans = get_option('sffc_dashboard_plans', []);
        $membership_url = untrailingslashit($this->get_membership_url());
        $currency = get_option('currency_detector_base_currency', 'USD');
        $options = [];

        foreach ((array) $plans as $plan) {
            $name = !empty($plan['name']) ? sanitize_text_field($plan['name']) : '';
            if ($name === '') {
                continue;
            }

            $amount = isset($plan['price_amount']) && $plan['price_amount'] !== ''
                ? (float) $plan['price_amount']
                : 0.0;

            if ($amount <= 0) {
                continue;
            }

            $billing_cycle = !empty($plan['billing_cycle']) ? sanitize_text_field($plan['billing_cycle']) : '';
            $slug_source = !empty($plan['slug']) ? $plan['slug'] : $name;
            $slug = sanitize_title($slug_source);
            $price = !empty($plan['price']) ? sanitize_text_field($plan['price']) : '';

            if ($price === '') {
                $currency_code = !empty($plan['price_currency']) ? sanitize_text_field($plan['price_currency']) : $currency;
                $amount_display = floor($amount) === $amount ? number_format_i18n($amount, 0) : number_format_i18n($amount, 2);
                $price = trim($currency_code . ' ' . $amount_display . ($billing_cycle !== '' ? ' / ' . $billing_cycle : ''));
            }

            $options[] = [
                'name' => $name,
                'slug' => $slug,
                'price' => $price,
                'description' => __('Includes tailored materials', 'senna-finance'),
                'url' => $membership_url . ($slug !== '' ? '#' . $slug : ''),
            ];
        }

        return array_values($options);
    }

    private function get_email_mode() {
        $mode = get_option('sffc_crm_email_mode', 'oauth');
        return in_array($mode, ['oauth', 'mailto'], true) ? $mode : 'oauth';
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        // Feed endpoints
        register_rest_route('sffc-crm/v1', '/posts', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_posts'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('sffc-crm/v1', '/posts/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_post'],
            'permission_callback' => '__return_true',
        ]);

        // Recruiter endpoints
        register_rest_route('sffc-crm/v1', '/recruiters', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_recruiters'],
            'permission_callback' => [$this, 'check_logged_in'],
        ]);

        register_rest_route('sffc-crm/v1', '/recruiters/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_recruiter'],
            'permission_callback' => [$this, 'check_logged_in'],
        ]);

        // Save endpoints
        register_rest_route('sffc-crm/v1', '/saved/posts', [
            'methods' => ['GET', 'POST', 'DELETE'],
            'callback' => [$this, 'rest_saved_posts'],
            'permission_callback' => [$this, 'check_logged_in'],
        ]);

        register_rest_route('sffc-crm/v1', '/saved/recruiters', [
            'methods' => ['GET', 'POST', 'DELETE'],
            'callback' => [$this, 'rest_saved_recruiters'],
            'permission_callback' => [$this, 'check_logged_in'],
        ]);

        // Pipeline endpoints
        register_rest_route('sffc-crm/v1', '/pipeline', [
            'methods' => ['GET', 'POST', 'PUT'],
            'callback' => [$this, 'rest_pipeline'],
            'permission_callback' => [$this, 'check_logged_in'],
        ]);

        register_rest_route('sffc-crm/v1', '/sendgrid/webhook', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_sendgrid_webhook'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Permission callback: check if user is logged in
     */
    public function check_logged_in() {
        return is_user_logged_in();
    }

    /**
     * REST: Get posts feed
     */
    public function rest_get_posts($request) {
        $post_model = new SFFC_CRM_Post();

        $args = [
            'page' => $request->get_param('page') ?: 1,
            'per_page' => $request->get_param('per_page') ?: 20,
            'sector' => $request->get_param('sector'),
            'seniority' => $request->get_param('seniority'),
            'location' => $request->get_param('location'),
            'search' => $request->get_param('search'),
            'recruiter_id' => $request->get_param('recruiter_id'),
        ];

        $posts = $post_model->get_feed($args);
        $total = $post_model->get_feed_count($args);

        return new WP_REST_Response([
            'posts' => $posts,
            'total' => $total,
            'page' => $args['page'],
            'per_page' => $args['per_page'],
            'total_pages' => ceil($total / $args['per_page']),
        ], 200);
    }

    /**
     * REST: Get single post
     */
    public function rest_get_post($request) {
        $post_model = new SFFC_CRM_Post();
        $post = $post_model->get($request->get_param('id'));

        if (!$post) {
            return new WP_Error('not_found', 'Post not found', ['status' => 404]);
        }

        return new WP_REST_Response($post, 200);
    }

    /**
     * REST: Get recruiters
     */
    public function rest_get_recruiters($request) {
        $recruiter_model = new SFFC_CRM_Recruiter();

        $args = [
            'page' => $request->get_param('page') ?: 1,
            'per_page' => $request->get_param('per_page') ?: 20,
            'search' => $request->get_param('search'),
            'firm' => $request->get_param('firm'),
            'status' => $request->get_param('status'),
        ];

        $user_id = get_current_user_id();
        $recruiters = $recruiter_model->get_user_recruiters($user_id, $args);
        $total = $recruiter_model->get_user_recruiters_count($user_id, $args);

        return new WP_REST_Response([
            'recruiters' => $recruiters,
            'total' => $total,
            'page' => $args['page'],
            'per_page' => $args['per_page'],
        ], 200);
    }

    /**
     * REST: Get single recruiter
     */
    public function rest_get_recruiter($request) {
        $recruiter_model = new SFFC_CRM_Recruiter();
        $recruiter = $recruiter_model->get_with_details($request->get_param('id'), get_current_user_id());

        if (!$recruiter) {
            return new WP_Error('not_found', 'Recruiter not found', ['status' => 404]);
        }

        return new WP_REST_Response($recruiter, 200);
    }

    /**
     * REST: Saved posts operations
     */
    public function rest_saved_posts($request) {
        $user_id = get_current_user_id();
        $post_model = new SFFC_CRM_Post();

        switch ($request->get_method()) {
            case 'GET':
                $saved = $post_model->get_saved_posts($user_id);
                return new WP_REST_Response($saved, 200);

            case 'POST':
                $post_id = $request->get_param('post_id');
                $result = $post_model->save_post($user_id, $post_id);
                return new WP_REST_Response(['success' => $result], $result ? 200 : 400);

            case 'DELETE':
                $post_id = $request->get_param('post_id');
                $result = $post_model->unsave_post($user_id, $post_id);
                return new WP_REST_Response(['success' => $result], $result ? 200 : 400);
        }
    }

    /**
     * REST: Saved recruiters operations
     */
    public function rest_saved_recruiters($request) {
        $user_id = get_current_user_id();
        $recruiter_model = new SFFC_CRM_Recruiter();

        switch ($request->get_method()) {
            case 'GET':
                $saved = $recruiter_model->get_saved_recruiters($user_id);
                return new WP_REST_Response($saved, 200);

            case 'POST':
                $recruiter_id = $request->get_param('recruiter_id');
                $result = $recruiter_model->save_recruiter($user_id, $recruiter_id);
                return new WP_REST_Response(['success' => $result], $result ? 200 : 400);

            case 'DELETE':
                $recruiter_id = $request->get_param('recruiter_id');
                $result = $recruiter_model->unsave_recruiter($user_id, $recruiter_id);
                return new WP_REST_Response(['success' => $result], $result ? 200 : 400);
        }
    }

    /**
     * REST: Pipeline operations
     */
    public function rest_pipeline($request) {
        $user_id = get_current_user_id();
        $pipeline_model = new SFFC_CRM_Pipeline();

        switch ($request->get_method()) {
            case 'GET':
                $stage = $request->get_param('stage');
                $items = $pipeline_model->get_user_pipeline($user_id, $stage);
                return new WP_REST_Response($items, 200);

            case 'POST':
                $data = [
                    'recruiter_id' => $request->get_param('recruiter_id'),
                    'post_id' => $request->get_param('post_id'),
                    'stage' => $request->get_param('stage') ?: 'interested',
                ];
                $result = $pipeline_model->add_to_pipeline($user_id, $data);
                return new WP_REST_Response(['success' => (bool)$result, 'id' => $result], $result ? 200 : 400);

            case 'PUT':
                $pipeline_id = $request->get_param('id');
                $new_stage = $request->get_param('stage');
                $result = $pipeline_model->update_stage($pipeline_id, $user_id, $new_stage);
                return new WP_REST_Response(['success' => $result], $result ? 200 : 400);
        }
    }

    public function rest_sendgrid_webhook($request) {
        return SFFC_CRM_SendGrid_Webhook::get_instance()->handle_webhook($request);
    }
}

// Initialize if plugin file constant exists
if (defined('SFFC_PLUGIN_FILE')) {
    SFFC_CRM_Init::get_instance();
}
