<?php
/**
 * Recruiter Terminal Main Controller
 *
 * Handles shortcode registration, asset loading, and initialization.
 * This is the main entry point for the Recruiter Terminal feature.
 *
 * @package SennaFinanceCareer
 * @subpackage RecruiterTerminal
 */

if (!defined('ABSPATH')) {
    exit;
}

class Recruiter_Terminal {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Terminal version
     */
    const VERSION = '2.0.0';

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Load dependencies
        $this->load_dependencies();

        // Register shortcodes
        add_shortcode('senna_recruiter_terminal', array($this, 'render_terminal'));
        add_shortcode('senna_opportunity', array($this, 'render_opportunity'));

        // Register assets
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));

        // Initialize AJAX handlers
        if (class_exists('Recruiter_Terminal_Ajax')) {
            Recruiter_Terminal_Ajax::init();
        }

        // Initialize email system
        if (class_exists('Recruiter_Terminal_Email')) {
            Recruiter_Terminal_Email::init();
        }

        // Initialize admin (only in admin context)
        if (is_admin() && class_exists('Recruiter_Terminal_Admin')) {
            Recruiter_Terminal_Admin::init();
        }

        // Hook for brief response notifications
        add_action('rt_brief_response_received', array($this, 'send_response_notification'), 10, 3);

        // Match recalculation hooks
        add_action('rt_brief_approved', array($this, 'generate_matches_for_brief'), 10, 1);
        add_action('sffc_matching_preferences_updated', array($this, 'generate_matches_for_user'), 10, 1);

        // Register tracking endpoints
        add_action('init', array($this, 'register_tracking_endpoints'));

        // Handle tracking requests
        add_action('template_redirect', array($this, 'handle_tracking_request'));
    }

    /**
     * Load required dependency files
     */
    private function load_dependencies() {
        $dir = plugin_dir_path(__FILE__);

        // Core classes
        require_once $dir . 'class-recruiter-terminal-db.php';
        require_once $dir . 'class-recruiter-terminal-ajax.php';
        require_once $dir . 'class-recruiter-terminal-email.php';

        // Match scoring engine
        if (file_exists($dir . 'class-recruiter-terminal-matcher.php')) {
            require_once $dir . 'class-recruiter-terminal-matcher.php';
        }

        // Admin class (only when in admin)
        if (is_admin()) {
            $admin_file = SFFC_PLUGIN_DIR . 'admin/class-recruiter-terminal-admin.php';
            if (file_exists($admin_file)) {
                require_once $admin_file;
            }
        }
    }

    /**
     * Create database tables if they don't exist
     */
    public function maybe_create_tables() {
        if (!Recruiter_Terminal_DB::tables_exist() || Recruiter_Terminal_DB::needs_upgrade()) {
            Recruiter_Terminal_DB::create_tables();
        }
    }

    /**
     * Register CSS and JS assets
     */
    public function register_assets() {
        // Register styles
        wp_register_style(
            'recruiter-terminal',
            SFFC_PLUGIN_URL . 'assets/css/recruiter-terminal.css',
            array(),
            self::VERSION
        );

        // Register Google Fonts
        wp_register_style(
            'recruiter-terminal-fonts',
            'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Noto+Serif:ital,wght@0,400;0,500;0,600;1,400&display=swap',
            array(),
            null
        );

        // Register scripts
        wp_register_script(
            'recruiter-terminal',
            SFFC_PLUGIN_URL . 'assets/js/recruiter-terminal.js',
            array('jquery'),
            self::VERSION,
            true
        );

        // Localize script with data
        wp_localize_script('recruiter-terminal', 'recruiterTerminal', array(
            'ajaxUrl'   => admin_url('admin-ajax.php'),
            'nonce'     => wp_create_nonce('recruiter_terminal_nonce'),
            'userId'    => get_current_user_id(),
            'baseUrl'   => home_url(),
            'trackUrl'  => home_url('/rt-track/'),
            'respondUrl'=> home_url('/rt-respond/'),
            'strings'   => array(
                'savingDraft'     => __('Saving draft...', 'senna-finance'),
                'draftSaved'      => __('Draft saved', 'senna-finance'),
                'findingMatches'  => __('Finding matching candidates...', 'senna-finance'),
                'submitting'      => __('Submitting for review...', 'senna-finance'),
                'confirmDelete'   => __('Are you sure you want to delete this campaign? This cannot be undone.', 'senna-finance'),
                'confirmPause'    => __('Are you sure you want to pause this campaign?', 'senna-finance'),
                'selectCandidates'=> __('Please select at least one candidate.', 'senna-finance'),
                'enterTitle'      => __('Please enter a campaign title.', 'senna-finance'),
                'enterBrief'      => __('Please describe the candidate you are looking for.', 'senna-finance'),
                'noMatches'       => __('No matching candidates found. Try broadening your search criteria.', 'senna-finance'),
                'errorGeneric'    => __('An error occurred. Please try again.', 'senna-finance'),
                'scheduleRequired'=> __('Please select when the campaign should go live.', 'senna-finance'),
                'sendImmediately' => __('Send immediately', 'senna-finance'),
                'notScheduled'    => __('Not scheduled', 'senna-finance'),
            ),
            'statusLabels' => array(
                'draft'          => __('Draft', 'senna-finance'),
                'pending_review' => __('Pending Review', 'senna-finance'),
                'approved'       => __('Approved', 'senna-finance'),
                'active'         => __('Active', 'senna-finance'),
                'paused'         => __('Paused', 'senna-finance'),
                'completed'      => __('Completed', 'senna-finance'),
                'rejected'       => __('Rejected', 'senna-finance'),
            ),
        ));
    }

    /**
     * Register tracking URL endpoints
     */
    public function register_tracking_endpoints() {
        // Email open tracking pixel
        add_rewrite_rule(
            '^rt-track/open/([a-zA-Z0-9-]+)/?$',
            'index.php?rt_action=track_open&rt_tracking_id=$matches[1]',
            'top'
        );

        // Click tracking
        add_rewrite_rule(
            '^rt-track/click/([a-zA-Z0-9-]+)/?$',
            'index.php?rt_action=track_click&rt_tracking_id=$matches[1]',
            'top'
        );

        // Response page
        add_rewrite_rule(
            '^rt-respond/([a-zA-Z0-9-]+)/([a-z_]+)/?$',
            'index.php?rt_action=respond&rt_tracking_id=$matches[1]&rt_response=$matches[2]',
            'top'
        );

        // Register query vars
        add_filter('query_vars', function($vars) {
            $vars[] = 'rt_action';
            $vars[] = 'rt_tracking_id';
            $vars[] = 'rt_response';
            return $vars;
        });
    }

    /**
     * Handle tracking requests
     */
    public function handle_tracking_request() {
        $action = get_query_var('rt_action');

        if (empty($action)) {
            return;
        }

        $tracking_id = sanitize_text_field(get_query_var('rt_tracking_id'));

        if (empty($tracking_id)) {
            return;
        }

        switch ($action) {
            case 'track_open':
                $this->handle_open_tracking($tracking_id);
                break;

            case 'track_click':
                $this->handle_click_tracking($tracking_id);
                break;

            case 'respond':
                $response = sanitize_key(get_query_var('rt_response'));
                $this->handle_response($tracking_id, $response);
                break;
        }
    }

    /**
     * Handle email open tracking
     */
    private function handle_open_tracking($tracking_id) {
        $target = Recruiter_Terminal_DB::get_target_by_tracking_id($tracking_id);

        if ($target) {
            $update_data = array(
                'open_count' => $target->open_count + 1,
            );

            // Only set opened_at on first open
            if (empty($target->opened_at)) {
                $update_data['opened_at'] = current_time('mysql');
                $update_data['email_status'] = 'opened';

                // Log activity
                Recruiter_Terminal_DB::log_activity(
                    $target->campaign_id,
                    $target->id,
                    'email_opened',
                    array('first_open' => true)
                );
            }

            Recruiter_Terminal_DB::update_target($target->id, $update_data);
        }

        // Return 1x1 transparent pixel
        header('Content-Type: image/gif');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        // 1x1 transparent GIF
        echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        exit;
    }

    /**
     * Handle click tracking
     */
    private function handle_click_tracking($tracking_id) {
        $target = Recruiter_Terminal_DB::get_target_by_tracking_id($tracking_id);

        if ($target) {
            $update_data = array();

            // Only set clicked_at on first click
            if (empty($target->clicked_at)) {
                $update_data['clicked_at'] = current_time('mysql');
                $update_data['email_status'] = 'clicked';

                // Log activity
                Recruiter_Terminal_DB::log_activity(
                    $target->campaign_id,
                    $target->id,
                    'email_clicked',
                    array()
                );

                Recruiter_Terminal_DB::update_target($target->id, $update_data);
            }
        }

        // Redirect to main site (or custom URL)
        wp_redirect(home_url());
        exit;
    }

    /**
     * Handle candidate response
     */
    private function handle_response($tracking_id, $response) {
        $target = Recruiter_Terminal_DB::get_target_by_tracking_id($tracking_id);

        if (!$target) {
            wp_die(__('Invalid or expired link.', 'senna-finance'), 404);
        }

        // Load response template
        $this->render_response_page($target, $response);
        exit;
    }

    /**
     * Render the response page
     */
    private function render_response_page($target, $response) {
        // Enqueue minimal styles
        wp_enqueue_style('recruiter-terminal-fonts');

        $valid_responses = array('interested', 'not_interested', 'maybe', 'unsubscribe');

        if (!in_array($response, $valid_responses)) {
            $response = 'interested';
        }

        include SFFC_PLUGIN_DIR . 'templates/recruiter-terminal-response.php';
    }

    /**
     * Render the terminal shortcode
     */
    public function render_terminal($atts) {
        // Must be logged in
        if (!is_user_logged_in()) {
            return $this->render_login_prompt();
        }

        $atts = shortcode_atts(array(
            'view' => '',
        ), $atts, 'senna_recruiter_terminal');

        // Enqueue assets
        wp_enqueue_style('recruiter-terminal-fonts');
        wp_enqueue_style('recruiter-terminal');
        wp_enqueue_script('recruiter-terminal');

        // Get current user
        $current_user = wp_get_current_user();
        $user_id = $current_user->ID;

        // Determine view from URL or attribute
        $view = !empty($atts['view']) ? $atts['view'] : (isset($_GET['view']) ? sanitize_key($_GET['view']) : 'dashboard');
        $brief_id = isset($_GET['brief']) ? absint($_GET['brief']) : 0;

        // Get user's briefs for dashboard (v2.0 messaging-first system)
        $briefs = $this->get_user_briefs($user_id);

        // Get brief data if editing/viewing specific brief
        $brief = null;
        $responses = array();

        if ($brief_id > 0) {
            $brief = Recruiter_Terminal_DB::get_brief($brief_id);

            if (!$brief || $brief->user_id != $user_id) {
                // Brief not found or doesn't belong to user
                $view = 'dashboard';
                $brief_id = 0;
                $brief = null;
            } else {
                // Get responses for this brief
                $responses = $this->get_brief_responses($brief_id);
            }
        }

        // Calculate dashboard stats
        $total_briefs = count($briefs);
        $active_count = 0;
        $pending_count = 0;
        $draft_count = 0;
        $total_responses = 0;
        $unread_responses = 0;

        foreach ($briefs as $b) {
            if ($b->status === 'active') $active_count++;
            elseif ($b->status === 'pending_review') $pending_count++;
            elseif ($b->status === 'draft') $draft_count++;

            $total_responses += $b->response_count;
            $unread_responses += $b->unread_count;
        }

        // Build context for template (v2.0 format)
        $context = array(
            'view'             => $view,
            'brief_id'         => $brief_id,
            'briefs'           => $briefs,
            'brief'            => $brief,
            'responses'        => $responses,
            'current_user'     => $current_user,
            'user_avatar'      => get_avatar_url($user_id, array('size' => 48)),
            // Dashboard stats
            'total_briefs'     => $total_briefs,
            'active_count'     => $active_count,
            'pending_count'    => $pending_count,
            'draft_count'      => $draft_count,
            'total_responses'  => $total_responses,
            'unread_responses' => $unread_responses,
        );

        // Start output buffer
        ob_start();

        // Load template
        $template_file = SFFC_PLUGIN_DIR . 'templates/recruiter-terminal.php';

        if (file_exists($template_file)) {
            include $template_file;
        } else {
            echo '<div class="rt-error">Template not found. Please contact support.</div>';
        }

        return ob_get_clean();
    }

    /**
     * Get user's briefs with response counts
     *
     * @param int $user_id User ID
     * @return array Array of brief objects with response_count and unread_count
     */
    private function get_user_briefs($user_id) {
        global $wpdb;
        $tables = Recruiter_Terminal_DB::get_table_names();

        // Check if briefs table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$tables['briefs']}'") !== $tables['briefs']) {
            return array();
        }

        $briefs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$tables['briefs']} WHERE user_id = %d ORDER BY created_at DESC",
            $user_id
        ));

        // Check if responses table exists
        $responses_table_exists = ($wpdb->get_var("SHOW TABLES LIKE '{$tables['responses']}'") === $tables['responses']);

        // Get response counts for each brief
        foreach ($briefs as &$brief) {
            if ($responses_table_exists) {
                $brief->response_count = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$tables['responses']} WHERE brief_id = %d",
                    $brief->id
                ));
                $brief->unread_count = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$tables['responses']} WHERE brief_id = %d AND status = 'pending'",
                    $brief->id
                ));
            } else {
                $brief->response_count = 0;
                $brief->unread_count = 0;
            }
        }

        return $briefs;
    }

    /**
     * Get responses for a brief
     *
     * @param int $brief_id Brief ID
     * @return array Array of response objects
     */
    private function get_brief_responses($brief_id) {
        global $wpdb;
        $tables = Recruiter_Terminal_DB::get_table_names();

        // Check if responses table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$tables['responses']}'") !== $tables['responses']) {
            return array();
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$tables['responses']} WHERE brief_id = %d ORDER BY starred DESC, created_at DESC",
            $brief_id
        ));
    }

    /**
     * Render login prompt for non-authenticated users
     */
    private function render_login_prompt() {
        // Enqueue minimal styles
        wp_enqueue_style('recruiter-terminal-fonts');
        wp_enqueue_style('recruiter-terminal');

        ob_start();
        ?>
        <div class="rt-terminal rt-terminal--login">
            <div class="rt-login-prompt">
                <div class="rt-login-box">
                    <div class="rt-login-logo">
                        <svg viewBox="0 0 28 28" fill="none" width="48" height="48">
                            <path d="M5 9c0-3 2.5-5.5 5.5-5.5h5c2.5 0 4.5 2 4.5 4.5 0 2-1.3 3.6-3.1 4.2-.2.1-.2.4 0 .5 1.8.6 3.1 2.2 3.1 4.2 0 2.5-2 4.5-4.5 4.5h-5C7.5 21.5 5 19 5 16" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" fill="none"/>
                            <circle cx="24" cy="5" r="3" fill="currentColor"/>
                        </svg>
                    </div>
                    <h2><?php esc_html_e('Recruiter Terminal', 'senna-finance'); ?></h2>
                    <p><?php esc_html_e('Sign in to access the campaign management dashboard.', 'senna-finance'); ?></p>
                    <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" class="rt-btn rt-btn--primary rt-btn--large">
                        <?php esc_html_e('Sign In', 'senna-finance'); ?>
                    </a>
                    <p class="rt-login-help">
                        <?php esc_html_e("Don't have an account?", 'senna-finance'); ?>
                        <a href="<?php echo esc_url(wp_registration_url()); ?>"><?php esc_html_e('Register', 'senna-finance'); ?></a>
                    </p>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the opportunity shortcode (for candidates)
     * This is a public-facing shortcode that doesn't require login
     * URL: /opportunity/?t={tracking_id}
     */
    public function render_opportunity($atts) {
        $atts = shortcode_atts(array(
            'tracking_id' => '',
        ), $atts, 'senna_opportunity');

        // Get tracking ID from URL parameter or shortcode attribute
        $tracking_id = !empty($atts['tracking_id'])
            ? sanitize_text_field($atts['tracking_id'])
            : (isset($_GET['t']) ? sanitize_text_field($_GET['t']) : '');

        // Check for direct brief parameter (v2.0 - MENA Careers sends link with ?b={brief_id})
        $brief_id = isset($_GET['b']) ? absint($_GET['b']) : 0;

        // Enqueue assets
        wp_enqueue_style('recruiter-terminal-fonts');
        wp_enqueue_style('recruiter-terminal');
        wp_enqueue_script('recruiter-terminal');

        // Start output buffer
        ob_start();

        // v2.0 Flow: Direct brief access (no prior tracking_id)
        if ($brief_id > 0) {
            $brief = Recruiter_Terminal_DB::get_brief($brief_id);

            if ($brief && $brief->status === 'active') {
                // Generate a session tracking ID for this visit
                $session_tracking_id = !empty($tracking_id) ? $tracking_id : wp_generate_uuid4();

                // Record view
                Recruiter_Terminal_DB::log_analytics_event($brief_id, $session_tracking_id, 'view');

                // Get recruiter info
                $recruiter = get_userdata($brief->user_id);

                $context = array(
                    'brief'       => $brief,
                    'response'    => null,
                    'recruiter'   => $recruiter,
                    'tracking_id' => $session_tracking_id,
                    'mode'        => 'new_response',
                );

                $this->render_opportunity_template($context);
            } elseif ($brief && $brief->status !== 'active') {
                $this->render_opportunity_error('expired');
            } else {
                $this->render_opportunity_error('brief_not_found');
            }

            return ob_get_clean();
        }

        // If no tracking ID or brief ID, show error
        if (empty($tracking_id)) {
            $this->render_opportunity_error('missing_tracking');
            return ob_get_clean();
        }

        // Try to get response by tracking ID from responses table first
        $response = Recruiter_Terminal_DB::get_response_by_tracking($tracking_id);

        if ($response) {
            // Existing response - show confirmation/thank you
            $brief = Recruiter_Terminal_DB::get_brief($response->brief_id);
            if ($brief) {
                // Record view
                Recruiter_Terminal_DB::log_analytics_event($brief->id, $tracking_id, 'view_return');

                // Get recruiter info
                $recruiter = get_userdata($brief->user_id);

                $context = array(
                    'brief'       => $brief,
                    'response'    => $response,
                    'recruiter'   => $recruiter,
                    'tracking_id' => $tracking_id,
                    'mode'        => 'existing_response',
                );

                $this->render_opportunity_template($context);
            } else {
                $this->render_opportunity_error('brief_not_found');
            }
        } else {
            // Check legacy targets table
            $target = Recruiter_Terminal_DB::get_target_by_tracking_id($tracking_id);

            if ($target) {
                // Legacy campaign target - get the campaign/brief
                $campaign = Recruiter_Terminal_DB::get_campaign($target->campaign_id);

                if ($campaign && in_array($campaign->status, array('active', 'approved'))) {
                    // Record view in analytics
                    Recruiter_Terminal_DB::log_analytics_event($campaign->id, $tracking_id, 'view');

                    // Get recruiter info
                    $recruiter = get_userdata($campaign->user_id);

                    $context = array(
                        'brief'       => $campaign, // Using campaign as brief for legacy
                        'target'      => $target,
                        'recruiter'   => $recruiter,
                        'tracking_id' => $tracking_id,
                        'mode'        => 'new_response',
                    );

                    $this->render_opportunity_template($context);
                } else {
                    $this->render_opportunity_error('expired');
                }
            } else {
                $this->render_opportunity_error('invalid_link');
            }
        }

        return ob_get_clean();
    }

    /**
     * Render the opportunity template
     */
    private function render_opportunity_template($context) {
        // Load template
        $template_file = SFFC_PLUGIN_DIR . 'templates/opportunity.php';

        if (file_exists($template_file)) {
            extract($context);
            include $template_file;
        } else {
            // Fallback inline template
            $this->render_opportunity_fallback($context);
        }
    }

    /**
     * Render opportunity error
     */
    private function render_opportunity_error($error_type) {
        $errors = array(
            'missing_tracking' => array(
                'title'   => __('Invalid Link', 'senna-finance'),
                'message' => __('This link appears to be incomplete. Please check your email and try clicking the link again.', 'senna-finance'),
            ),
            'invalid_link' => array(
                'title'   => __('Link Not Found', 'senna-finance'),
                'message' => __('This opportunity link is no longer valid or has expired.', 'senna-finance'),
            ),
            'expired' => array(
                'title'   => __('Opportunity Expired', 'senna-finance'),
                'message' => __('This opportunity is no longer available.', 'senna-finance'),
            ),
            'brief_not_found' => array(
                'title'   => __('Not Available', 'senna-finance'),
                'message' => __('This opportunity is no longer available.', 'senna-finance'),
            ),
        );

        $error = isset($errors[$error_type]) ? $errors[$error_type] : $errors['invalid_link'];
        ?>
        <div class="rt-opportunity rt-opportunity--error">
            <div class="rt-opportunity-error">
                <div class="rt-opportunity-error-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                </div>
                <h2><?php echo esc_html($error['title']); ?></h2>
                <p><?php echo esc_html($error['message']); ?></p>
                <a href="<?php echo esc_url(home_url()); ?>" class="rt-btn rt-btn--secondary">
                    <?php esc_html_e('Go to Homepage', 'senna-finance'); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Fallback opportunity template (if template file doesn't exist)
     */
    private function render_opportunity_fallback($context) {
        extract($context);

        $recruiter_name = $recruiter ? $recruiter->display_name : __('Recruiter', 'senna-finance');
        $recruiter_avatar = $recruiter ? get_avatar_url($recruiter->ID, array('size' => 64)) : '';
        $recruiter_title = $recruiter ? get_user_meta($recruiter->ID, 'job_title', true) : '';
        $recruiter_company = $recruiter ? get_user_meta($recruiter->ID, 'company', true) : '';
        ?>
        <div class="rt-opportunity">
            <div class="rt-opportunity-container">
                <!-- Header -->
                <div class="rt-opportunity-header">
                    <div class="rt-opportunity-logo">
                        <span class="rt-logo-text">S.</span> MENA Careers
                    </div>
                </div>

                <!-- Brief Card -->
                <div class="rt-opportunity-brief">
                    <div class="rt-recruiter-card">
                        <?php if ($recruiter_avatar): ?>
                            <img src="<?php echo esc_url($recruiter_avatar); ?>" alt="" class="rt-recruiter-avatar">
                        <?php endif; ?>
                        <div class="rt-recruiter-info">
                            <div class="rt-recruiter-name"><?php echo esc_html($recruiter_name); ?></div>
                            <?php if ($recruiter_title || $recruiter_company): ?>
                                <div class="rt-recruiter-title">
                                    <?php echo esc_html($recruiter_title); ?>
                                    <?php if ($recruiter_title && $recruiter_company) echo ' @ '; ?>
                                    <?php echo esc_html($recruiter_company); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="rt-brief-date">
                            <?php echo esc_html(human_time_diff(strtotime($brief->created_at), current_time('timestamp')) . ' ago'); ?>
                        </div>
                    </div>

                    <h1 class="rt-brief-title"><?php echo esc_html($brief->title); ?></h1>

                    <?php if (!empty($brief->location) || !empty($brief->salary_range)): ?>
                        <div class="rt-brief-meta">
                            <?php if (!empty($brief->location)): ?>
                                <span class="rt-meta-item">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                        <circle cx="12" cy="10" r="3"></circle>
                                    </svg>
                                    <?php echo esc_html($brief->location); ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($brief->salary_range)): ?>
                                <span class="rt-meta-item">
                                    <?php echo esc_html($brief->salary_range); ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($brief->sector)): ?>
                                <span class="rt-meta-item">
                                    <?php echo esc_html($brief->sector); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="rt-brief-content">
                        <?php echo wp_kses_post(wpautop($brief->brief)); ?>
                    </div>

                    <p class="rt-brief-cta">
                        <?php esc_html_e('Share your preferred contact details to get started.', 'senna-finance'); ?>
                    </p>
                </div>

                <!-- Response Form -->
                <div class="rt-response-form-container">
                    <h3><?php esc_html_e('Respond to this opportunity', 'senna-finance'); ?></h3>

                    <form id="rt-opportunity-response-form" class="rt-response-form">
                        <input type="hidden" name="tracking_id" value="<?php echo esc_attr($tracking_id); ?>">
                        <input type="hidden" name="brief_id" value="<?php echo esc_attr($brief->id); ?>">

                        <div class="rt-form-row">
                            <div class="rt-form-group">
                                <label for="candidate_name"><?php esc_html_e('Your Name', 'senna-finance'); ?> *</label>
                                <input type="text" id="candidate_name" name="candidate_name" required>
                            </div>
                        </div>

                        <div class="rt-form-row rt-form-row--two">
                            <div class="rt-form-group">
                                <label for="candidate_email"><?php esc_html_e('Email', 'senna-finance'); ?> *</label>
                                <input type="email" id="candidate_email" name="candidate_email" required>
                            </div>
                            <div class="rt-form-group">
                                <label for="candidate_phone"><?php esc_html_e('Phone', 'senna-finance'); ?></label>
                                <input type="tel" id="candidate_phone" name="candidate_phone">
                            </div>
                        </div>

                        <div class="rt-form-row">
                            <div class="rt-form-group">
                                <label for="candidate_linkedin"><?php esc_html_e('LinkedIn URL', 'senna-finance'); ?></label>
                                <input type="url" id="candidate_linkedin" name="candidate_linkedin" placeholder="https://linkedin.com/in/...">
                            </div>
                        </div>

                        <div class="rt-form-row">
                            <div class="rt-form-group">
                                <label for="message"><?php esc_html_e('Message (optional)', 'senna-finance'); ?></label>
                                <textarea id="message" name="message" rows="4" placeholder="<?php esc_attr_e('Tell the recruiter a bit about yourself...', 'senna-finance'); ?>"></textarea>
                            </div>
                        </div>

                        <div class="rt-form-actions">
                            <button type="button" class="rt-btn rt-btn--secondary" id="rt-not-interested">
                                <?php esc_html_e('Not Interested', 'senna-finance'); ?>
                            </button>
                            <button type="submit" class="rt-btn rt-btn--primary">
                                <?php esc_html_e('Submit Response', 'senna-finance'); ?>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Login prompt for continued conversation -->
                <div class="rt-continue-chat">
                    <p><?php printf(
                        esc_html__('Want to chat directly with %s?', 'senna-finance'),
                        esc_html($recruiter_name)
                    ); ?></p>
                    <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>">
                        <?php esc_html_e('Log in', 'senna-finance'); ?>
                    </a>
                    <?php esc_html_e('or', 'senna-finance'); ?>
                    <a href="<?php echo esc_url(wp_registration_url()); ?>">
                        <?php esc_html_e('Create Account', 'senna-finance'); ?>
                    </a>
                    <?php esc_html_e('to continue the conversation', 'senna-finance'); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Send email notification to recruiter when candidate responds
     *
     * @param int $response_id Response ID
     * @param int $brief_id Brief ID
     * @param int $recruiter_id Recruiter user ID
     */
    public function send_response_notification($response_id, $brief_id, $recruiter_id) {
        // Get recruiter
        $recruiter = get_userdata($recruiter_id);
        if (!$recruiter) {
            return;
        }

        // Get response
        global $wpdb;
        $tables = Recruiter_Terminal_DB::get_table_names();
        $response = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tables['responses']} WHERE id = %d",
            $response_id
        ));

        if (!$response) {
            return;
        }

        // Get brief
        $brief = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tables['briefs']} WHERE id = %d",
            $brief_id
        ));

        if (!$brief) {
            return;
        }

        // Build terminal URL
        $terminal_page_id = get_option('rt_terminal_page_id', 0);
        $terminal_url = $terminal_page_id
            ? add_query_arg(array('view' => 'inbox', 'brief' => $brief_id), get_permalink($terminal_page_id))
            : admin_url();

        // Email subject
        $subject = sprintf(
            /* translators: 1: Candidate name, 2: Brief title */
            __('New Response: %1$s interested in %2$s', 'senna-finance'),
            $response->candidate_name,
            $brief->title
        );

        // Email body
        $body = sprintf(
            __("Hi %s,\n\nGreat news! You have a new candidate response for your role brief.\n\n", 'senna-finance'),
            $recruiter->display_name
        );

        $body .= sprintf(
            __("Brief: %s\n", 'senna-finance'),
            $brief->title
        );

        $body .= sprintf(
            __("Candidate: %s\n", 'senna-finance'),
            $response->candidate_name
        );

        $body .= sprintf(
            __("Email: %s\n", 'senna-finance'),
            $response->candidate_email
        );

        if (!empty($response->candidate_phone)) {
            $body .= sprintf(
                __("Phone: %s\n", 'senna-finance'),
                $response->candidate_phone
            );
        }

        if (!empty($response->candidate_linkedin)) {
            $body .= sprintf(
                __("LinkedIn: %s\n", 'senna-finance'),
                $response->candidate_linkedin
            );
        }

        if (!empty($response->message)) {
            $body .= sprintf(
                __("\nMessage from candidate:\n\"%s\"\n", 'senna-finance'),
                $response->message
            );
        }

        $body .= sprintf(
            __("\nView this response in your dashboard:\n%s\n\n", 'senna-finance'),
            $terminal_url
        );

        $body .= __("Best regards,\nThe MENA Careers Team", 'senna-finance');

        // Send email
        $headers = array('Content-Type: text/plain; charset=UTF-8');

        wp_mail($recruiter->user_email, $subject, $body, $headers);

        // Log the notification
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[Recruiter Terminal] Response notification sent to %s for brief #%d',
                $recruiter->user_email,
                $brief_id
            ));
        }
    }

    /**
     * Flush rewrite rules (call on activation)
     */
    public static function activate() {
        $instance = self::get_instance();
        $instance->register_tracking_endpoints();
        flush_rewrite_rules();

        // Create tables
        Recruiter_Terminal_DB::create_tables();
    }

    /**
     * Cleanup on deactivation
     */
    public static function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('rt_process_email_queue');

        flush_rewrite_rules();
    }

    /**
     * Generate matches for a brief when it's approved
     *
     * @param int $brief_id Brief ID
     */
    public function generate_matches_for_brief($brief_id) {
        if (!class_exists('Recruiter_Terminal_Matcher')) {
            return;
        }

        // First detect and save skills from the brief
        Recruiter_Terminal_Matcher::detect_and_save_brief_skills($brief_id);

        // Then generate matches for all users
        Recruiter_Terminal_Matcher::generate_matches_for_brief($brief_id);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[Recruiter Terminal] Matches generated for brief #%d', $brief_id));
        }
    }

    /**
     * Generate matches for a user when their preferences are updated
     *
     * @param int $user_id User ID
     */
    public function generate_matches_for_user($user_id) {
        if (!class_exists('Recruiter_Terminal_Matcher')) {
            return;
        }

        Recruiter_Terminal_Matcher::generate_matches_for_user($user_id);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[Recruiter Terminal] Matches regenerated for user #%d', $user_id));
        }
    }
}
