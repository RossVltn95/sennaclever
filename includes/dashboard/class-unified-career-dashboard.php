<?php
/**
 * Unified Career Intelligence Dashboard
 *
 * The central hub for career intelligence - combining profile management,
 * market insights, skills analysis, and salary intelligence in one dashboard.
 *
 * @package SFFC_Careers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Unified_Career_Dashboard {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Analytics engine instance
     */
    private $analytics_engine = null;

    /**
     * User profile data cache
     */
    private $user_profile = null;

    /**
     * Dashboard preferences cache
     */
    private $dashboard_prefs = null;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register shortcodes
        add_shortcode('sffc_career_dashboard', array($this, 'render_dashboard'));
        add_shortcode('sffc_unified_profile', array($this, 'render_unified_profile'));

        // Enqueue assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));

        // AJAX endpoints
        add_action('wp_ajax_sffc_dashboard_get_stats', array($this, 'ajax_get_stats'));
        add_action('wp_ajax_sffc_dashboard_get_trends', array($this, 'ajax_get_trends'));
        add_action('wp_ajax_sffc_dashboard_get_skills', array($this, 'ajax_get_skills_analysis'));
        add_action('wp_ajax_sffc_dashboard_get_market_intel', array($this, 'ajax_get_market_intel'));
        add_action('wp_ajax_sffc_dashboard_get_salary_data', array($this, 'ajax_get_salary_data'));
        add_action('wp_ajax_sffc_dashboard_save_preferences', array($this, 'ajax_save_preferences'));
        add_action('wp_ajax_sffc_dashboard_update_profile', array($this, 'ajax_update_profile'));
        add_action('wp_ajax_sffc_dashboard_save_article', array($this, 'ajax_save_article'));
        add_action('wp_ajax_sffc_dashboard_save_profile', array($this, 'ajax_save_profile'));
        add_action('wp_ajax_sffc_dashboard_save_preference', array($this, 'ajax_save_single_preference'));
        add_action('wp_ajax_sffc_dashboard_save_source_preferences', array($this, 'ajax_save_source_preferences'));
        add_action('wp_ajax_sffc_dashboard_add_alert_keyword', array($this, 'ajax_add_alert_keyword'));
        add_action('wp_ajax_sffc_dashboard_toggle_alert_keyword', array($this, 'ajax_toggle_alert_keyword'));
        add_action('wp_ajax_sffc_dashboard_delete_alert_keyword', array($this, 'ajax_delete_alert_keyword'));

        // Job application tracking endpoints
        add_action('wp_ajax_sffc_save_job_stage', array($this, 'ajax_save_job_stage'));
        add_action('wp_ajax_sffc_toggle_saved_job', array($this, 'ajax_toggle_saved_job'));
        add_action('wp_ajax_sffc_load_more_jobs', array($this, 'ajax_load_more_jobs'));
        add_action('wp_ajax_sffc_get_dashboard_stats', array($this, 'ajax_get_dashboard_stats'));
        add_action('wp_ajax_sffc_log_interaction', array($this, 'ajax_log_interaction'));
        add_action('wp_ajax_sffc_track_job_apply', array($this, 'ajax_track_job_apply'));
        add_action('wp_ajax_nopriv_sffc_track_job_apply', array($this, 'ajax_track_job_apply'));

        // Unified profile endpoints
        add_action('wp_ajax_sffc_save_audit_profile', array($this, 'ajax_save_audit_profile'));
        add_action('wp_ajax_sffc_get_audit_profile', array($this, 'ajax_get_audit_profile'));

        // Contact management endpoints (networking & recruiters)
        add_action('wp_ajax_sffc_add_contact', array($this, 'ajax_add_contact'));
        add_action('wp_ajax_sffc_update_contact', array($this, 'ajax_update_contact'));
        add_action('wp_ajax_sffc_delete_contact', array($this, 'ajax_delete_contact'));
        add_action('wp_ajax_sffc_get_contact', array($this, 'ajax_get_contact'));
        add_action('wp_ajax_sffc_reload_section', array($this, 'ajax_reload_section'));

        // Initialize analytics engine
        add_action('init', array($this, 'init_analytics_engine'));

        // Initialize data manager
        add_action('init', array($this, 'init_data_manager'));

        // Check and create database tables if needed
        add_action('admin_init', array($this, 'maybe_create_tables'));
    }

    /**
     * Check and create database tables if needed
     */
    public function maybe_create_tables() {
        // Only run for admins
        if (!current_user_can('manage_options')) {
            return;
        }

        // Check if tables need to be created/updated
        require_once plugin_dir_path(dirname(__FILE__)) . 'class-database-schema.php';
        $schema = SFFC_Database_Schema::get_instance();
        $schema->check_db_version();
    }

    /**
     * Data manager instance
     */
    private $data_manager = null;

    /**
     * Initialize data manager
     */
    public function init_data_manager() {
        require_once plugin_dir_path(__FILE__) . 'class-dashboard-data-manager.php';
        $this->data_manager = SFFC_Dashboard_Data_Manager::get_instance();
    }

    /**
     * Get data manager instance
     */
    public function get_data_manager() {
        if (!$this->data_manager) {
            $this->init_data_manager();
        }
        return $this->data_manager;
    }

    /**
     * Initialize analytics engine
     */
    public function init_analytics_engine() {
        if (class_exists('SFFC_Dashboard_Analytics_Engine')) {
            $this->analytics_engine = SFFC_Dashboard_Analytics_Engine::get_instance();
        }
    }

    /**
     * Enqueue dashboard assets
     */
    public function enqueue_assets() {
        global $post;

        // Only load on pages with our shortcode
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'sffc_career_dashboard')) {
            return;
        }

        // Chart.js for visualizations
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
            array(),
            '4.4.1',
            true
        );

        // Dashboard CSS
        wp_enqueue_style(
            'sffc-career-dashboard',
            plugins_url('assets/css/career-dashboard.css', dirname(dirname(__FILE__))),
            array(),
            filemtime(plugin_dir_path(dirname(dirname(__FILE__))) . 'assets/css/career-dashboard.css')
        );

        // Dashboard Charts JS (reusable chart components)
        wp_enqueue_script(
            'sffc-dashboard-charts',
            plugins_url('assets/js/dashboard-charts.js', dirname(dirname(__FILE__))),
            array('jquery', 'chartjs'),
            filemtime(plugin_dir_path(dirname(dirname(__FILE__))) . 'assets/js/dashboard-charts.js'),
            true
        );

        // Dashboard JS
        wp_enqueue_script(
            'sffc-career-dashboard',
            plugins_url('assets/js/career-dashboard.js', dirname(dirname(__FILE__))),
            array('jquery', 'chartjs', 'sffc-dashboard-charts'),
            filemtime(plugin_dir_path(dirname(dirname(__FILE__))) . 'assets/js/career-dashboard.js'),
            true
        );

        // Localize script with data
        wp_localize_script('sffc-career-dashboard', 'sffcDashboard', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sffc_dashboard_nonce'),
            'isLoggedIn' => is_user_logged_in(),
            'userId' => get_current_user_id(),
            'membershipLevel' => $this->get_membership_level(),
            'i18n' => array(
                'loading' => __('Loading...', 'flavor-careers'),
                'error' => __('An error occurred', 'flavor-careers'),
                'noData' => __('No data available', 'flavor-careers'),
                'save' => __('Save', 'flavor-careers'),
                'saved' => __('Saved!', 'flavor-careers'),
            )
        ));
    }

    /**
     * Main dashboard render function
     */
    public function render_dashboard($atts = array()) {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return $this->render_guest_prompt();
        }

        // Get user data
        $user_id = get_current_user_id();
        $this->user_profile = $this->get_user_profile($user_id);
        $this->dashboard_prefs = $this->get_dashboard_preferences($user_id);
        $membership = $this->get_membership_data($user_id);

        // Get active section from preferences or default to overview
        $active_section = isset($this->dashboard_prefs['active_section']) ? $this->dashboard_prefs['active_section'] : 'overview';

        ob_start();
        ?>
        <div class="sffc-career-dashboard sffc-dashboard-with-sidebar" data-user-id="<?php echo esc_attr($user_id); ?>" data-active-section="<?php echo esc_attr($active_section); ?>">

            <!-- Mobile Header - App-like top bar -->
            <header class="sffc-mobile-header">
                <button class="sffc-mobile-menu-toggle" aria-label="Open menu">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>
                <span class="sffc-mobile-header-title">Career Intelligence</span>
                <div class="sffc-mobile-header-actions">
                    <button class="sffc-mobile-header-btn" id="sffc-mobile-refresh" aria-label="Refresh">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="23 4 23 10 17 10"></polyline>
                            <polyline points="1 20 1 14 7 14"></polyline>
                            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                        </svg>
                    </button>
                    <button class="sffc-mobile-header-btn" id="sffc-mobile-settings" aria-label="Settings">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="3"></circle>
                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                        </svg>
                    </button>
                </div>
            </header>

            <!-- Sidebar Overlay (for mobile) -->
            <div class="sffc-sidebar-overlay"></div>

            <!-- Sidebar Navigation -->
            <?php echo $this->render_sidebar_navigation($active_section); ?>

            <!-- Main Content Area -->
            <main class="sffc-dashboard-main">

                <!-- Dashboard Header -->
                <?php echo $this->render_header($membership); ?>

                <!-- Section Content Container -->
                <div class="sffc-sections-container">

                    <!-- Overview Section -->
                    <section class="sffc-section-panel <?php echo $active_section === 'overview' ? 'active' : ''; ?>" data-section="overview">
                        <!-- Stats Cards Row (Overview Only) -->
                        <div class="sffc-dashboard-stats-row">
                            <?php echo $this->render_stat_cards(); ?>
                        </div>

                        <div class="sffc-dashboard-section sffc-matching-roles-section">
                            <?php echo $this->render_matching_roles_carousel($user_id); ?>
                        </div>
                    </section>

                    <!-- Jobs Section -->
                    <section class="sffc-section-panel <?php echo $active_section === 'jobs' ? 'active' : ''; ?>" data-section="jobs">
                        <div class="sffc-dashboard-section sffc-jobs-section">
                            <?php echo $this->render_jobs_section($user_id); ?>
                        </div>
                    </section>

                    <!-- Trends Section -->
                    <section class="sffc-section-panel <?php echo $active_section === 'trends' ? 'active' : ''; ?>" data-section="trends">
                        <div class="sffc-dashboard-section sffc-trends-section">
                            <?php echo $this->render_trends_chart(); ?>
                        </div>
                    </section>

                    <!-- Skills Section -->
                    <section class="sffc-section-panel <?php echo $active_section === 'skills' ? 'active' : ''; ?>" data-section="skills">
                        <div class="sffc-dashboard-section sffc-skills-section">
                            <?php echo $this->render_skills_section(); ?>
                        </div>
                    </section>

                    <!-- Market Section -->
                    <section class="sffc-section-panel <?php echo $active_section === 'market' ? 'active' : ''; ?>" data-section="market">
                        <div class="sffc-dashboard-section sffc-market-section">
                            <?php echo $this->render_market_intelligence(); ?>
                        </div>
                    </section>

                    <!-- Salary Section -->
                    <section class="sffc-section-panel <?php echo $active_section === 'salary' ? 'active' : ''; ?>" data-section="salary">
                        <div class="sffc-dashboard-section sffc-salary-section">
                            <?php echo $this->render_salary_section(); ?>
                        </div>
                    </section>

                    <!-- Networking Section -->
                    <section class="sffc-section-panel <?php echo $active_section === 'networking' ? 'active' : ''; ?>" data-section="networking">
                        <div class="sffc-dashboard-section sffc-networking-section">
                            <?php echo $this->render_networking_section($user_id); ?>
                        </div>
                    </section>

                    <!-- Recruiters Section -->
                    <section class="sffc-section-panel <?php echo $active_section === 'recruiters' ? 'active' : ''; ?>" data-section="recruiters">
                        <div class="sffc-dashboard-section sffc-recruiters-section">
                            <?php echo $this->render_recruiters_section($user_id); ?>
                        </div>
                    </section>

                    <!-- My Profile Section -->
                    <section class="sffc-section-panel <?php echo $active_section === 'my_profile' ? 'active' : ''; ?>" data-section="my_profile">
                        <div class="sffc-dashboard-section sffc-profile-section">
                            <?php echo $this->render_my_profile_section($user_id); ?>
                        </div>
                    </section>

                    <!-- Settings Section -->
                    <section class="sffc-section-panel <?php echo $active_section === 'settings' ? 'active' : ''; ?>" data-section="settings">
                        <div class="sffc-dashboard-section sffc-settings-section">
                            <?php echo $this->render_settings_section(); ?>
                        </div>
                    </section>

                </div>

            </main>

            <!-- Mobile Bottom Navigation -->
            <?php echo $this->render_mobile_navigation($active_section); ?>

            <!-- Membership Modal -->
            <?php echo $this->render_membership_modal($membership); ?>

            <!-- Upgrade Prompt (shown contextually) -->
            <?php echo $this->render_upgrade_prompt_template(); ?>

            <!-- Profile Quick-Edit Modal -->
            <?php echo $this->render_profile_quick_edit_modal(); ?>

            <!-- Missing Fields Indicator -->
            <?php echo $this->render_missing_fields_indicator(); ?>

            <!-- Onboarding Tooltips -->
            <?php echo $this->render_onboarding_tooltips(); ?>

            <!-- News Sources Modal -->
            <?php echo $this->render_news_sources_modal(); ?>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render sidebar navigation
     */
    private function render_sidebar_navigation($active_section) {
        // Sleek nav items with refined icons (thinner stroke)
        $nav_items = array(
            'overview' => array(
                'label' => 'Overview',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>'
            ),
            'jobs' => array(
                'label' => 'Jobs',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16"/></svg>'
            ),
            'trends' => array(
                'label' => 'Trends',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M18 9l-5 5-4-4-3 3"/></svg>'
            ),
            'skills' => array(
                'label' => 'Skills',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>'
            ),
            'market' => array(
                'label' => 'News & Insights',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>'
            ),
            'salary' => array(
                'label' => 'Salary',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>'
            ),
            'networking' => array(
                'label' => 'Networking',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>'
            ),
            'recruiters' => array(
                'label' => 'Recruiters',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>'
            ),
            'my_profile' => array(
                'label' => 'My Profile',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>'
            ),
            'settings' => array(
                'label' => 'Settings',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/></svg>'
            ),
        );

        $current_user = wp_get_current_user();
        $user_name = $current_user->display_name;
        $avatar_url = get_avatar_url($current_user->ID, array('size' => 64));
        $user_id = $current_user->ID;

        // Get profile completion and job counts for badges
        $profile_completion = $this->calculate_profile_completion();
        $data_manager = $this->get_data_manager();
        $matching_jobs = $this->get_matching_jobs_for_carousel($user_id, 100);
        $jobs_count = count($matching_jobs);
        $pipeline_stats = $data_manager->get_pipeline_stats($user_id);
        $attention_count = isset($pipeline_stats['waiting']) ? $pipeline_stats['waiting'] : 0;

        // Get user's role/title from profile
        $user_role = get_user_meta($user_id, 'sffc_current_role', true);
        if (empty($user_role)) {
            $user_role = get_user_meta($user_id, 'sffc_job_title', true);
        }
        if (empty($user_role)) {
            $user_role = 'Career Professional';
        }

        ob_start();
        ?>
        <aside class="sffc-dashboard-sidebar" id="sffc-sidebar">
            <!-- Sidebar Header - McKinsey Style -->
            <div class="sffc-sidebar-header">
                <div class="sffc-sidebar-logo">
                    <div class="sffc-logo-mark">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                            <rect x="3" y="3" width="8" height="8" rx="2" fill="#3b82f6"/>
                            <rect x="13" y="3" width="8" height="8" rx="2" fill="#0ea5e9" fill-opacity="0.6"/>
                            <rect x="3" y="13" width="8" height="8" rx="2" fill="#0ea5e9" fill-opacity="0.6"/>
                            <rect x="13" y="13" width="8" height="8" rx="2" fill="#3b82f6"/>
                        </svg>
                    </div>
                    <span class="sffc-sidebar-logo-text">MENA Careers</span>
                </div>
                <!-- Desktop: Collapse toggle -->
                <button class="sffc-sidebar-toggle" id="sffc-sidebar-toggle" aria-label="Collapse sidebar">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M11 17l-5-5 5-5M18 17l-5-5 5-5"/>
                    </svg>
                </button>
                <!-- Mobile: Close button -->
                <button class="sffc-sidebar-close" id="sffc-sidebar-close" aria-label="Close menu">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 6L6 18M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Profile Card - McKinsey Style -->
            <div class="sffc-sidebar-profile-card">
                <div class="sffc-profile-avatar-wrap">
                    <img src="<?php echo esc_url($avatar_url); ?>" alt="" class="sffc-profile-avatar">
                    <?php if ($profile_completion >= 80) : ?>
                    <span class="sffc-profile-verified">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
                        </svg>
                    </span>
                    <?php endif; ?>
                </div>
                <div class="sffc-profile-info">
                    <span class="sffc-profile-name"><?php echo esc_html($user_name); ?></span>
                    <span class="sffc-profile-role"><?php echo esc_html($user_role); ?></span>
                </div>
                <div class="sffc-profile-progress">
                    <div class="sffc-profile-progress-header">
                        <span class="sffc-profile-progress-label">Profile Score</span>
                        <span class="sffc-profile-progress-value"><?php echo esc_html($profile_completion); ?>%</span>
                    </div>
                    <div class="sffc-profile-progress-bar">
                        <div class="sffc-profile-progress-fill" style="width: <?php echo esc_attr($profile_completion); ?>%"></div>
                    </div>
                </div>
            </div>

            <!-- Main Navigation -->
            <nav class="sffc-sidebar-nav">
                <div class="sffc-nav-section">
                    <span class="sffc-nav-section-title">Dashboard</span>
                    <?php
                    // Define badges for nav items
                    $nav_badges = array(
                        'overview' => $attention_count > 0 ? $attention_count : null,
                        'jobs' => $jobs_count > 0 ? $jobs_count : null,
                    );

                    $main_items = array('overview', 'jobs', 'trends');
                    foreach ($main_items as $section_id) :
                        if (isset($nav_items[$section_id])) :
                            $item = $nav_items[$section_id];
                            $badge = isset($nav_badges[$section_id]) ? $nav_badges[$section_id] : null;
                    ?>
                        <button class="sffc-nav-item <?php echo $active_section === $section_id ? 'active' : ''; ?>"
                                data-section="<?php echo esc_attr($section_id); ?>"
                                data-tooltip="<?php echo esc_attr($item['label']); ?>">
                            <span class="sffc-nav-icon"><?php echo $item['icon']; ?></span>
                            <span class="sffc-nav-label"><?php echo esc_html($item['label']); ?></span>
                            <?php if ($badge !== null) : ?>
                            <span class="sffc-nav-badge" data-badge="<?php echo esc_attr($section_id); ?>"><?php echo esc_html($badge); ?></span>
                            <?php endif; ?>
                        </button>
                    <?php
                        endif;
                    endforeach;
                    ?>
                </div>

                <div class="sffc-nav-section">
                    <span class="sffc-nav-section-title">Intelligence</span>
                    <?php
                    $intel_items = array('skills', 'market', 'salary');
                    foreach ($intel_items as $section_id) :
                        if (isset($nav_items[$section_id])) :
                            $item = $nav_items[$section_id];
                    ?>
                        <button class="sffc-nav-item <?php echo $active_section === $section_id ? 'active' : ''; ?>"
                                data-section="<?php echo esc_attr($section_id); ?>"
                                data-tooltip="<?php echo esc_attr($item['label']); ?>">
                            <span class="sffc-nav-icon"><?php echo $item['icon']; ?></span>
                            <span class="sffc-nav-label"><?php echo esc_html($item['label']); ?></span>
                        </button>
                    <?php
                        endif;
                    endforeach;
                    ?>
                </div>

                <div class="sffc-nav-section">
                    <span class="sffc-nav-section-title">Connections</span>
                    <?php
                    $connection_items = array('networking', 'recruiters');
                    foreach ($connection_items as $section_id) :
                        if (isset($nav_items[$section_id])) :
                            $item = $nav_items[$section_id];
                    ?>
                        <button class="sffc-nav-item <?php echo $active_section === $section_id ? 'active' : ''; ?>"
                                data-section="<?php echo esc_attr($section_id); ?>"
                                data-tooltip="<?php echo esc_attr($item['label']); ?>">
                            <span class="sffc-nav-icon"><?php echo $item['icon']; ?></span>
                            <span class="sffc-nav-label"><?php echo esc_html($item['label']); ?></span>
                        </button>
                    <?php
                        endif;
                    endforeach;
                    ?>
                </div>

                <div class="sffc-nav-section">
                    <span class="sffc-nav-section-title">Account</span>
                    <button class="sffc-nav-item <?php echo $active_section === 'my_profile' ? 'active' : ''; ?>"
                            data-section="my_profile"
                            data-tooltip="My Profile">
                        <span class="sffc-nav-icon"><?php echo $nav_items['my_profile']['icon']; ?></span>
                        <span class="sffc-nav-label">My Profile</span>
                    </button>
                    <button class="sffc-nav-item <?php echo $active_section === 'settings' ? 'active' : ''; ?>"
                            data-section="settings"
                            data-tooltip="Settings">
                        <span class="sffc-nav-icon"><?php echo $nav_items['settings']['icon']; ?></span>
                        <span class="sffc-nav-label">Settings</span>
                    </button>
                </div>
            </nav>

            <!-- Sidebar Footer -->
            <div class="sffc-sidebar-footer">
                <div class="sffc-sidebar-user" data-tooltip="<?php echo esc_attr($user_name); ?>">
                    <img src="<?php echo esc_url($avatar_url); ?>" alt="" class="sffc-sidebar-avatar">
                    <div class="sffc-sidebar-user-info">
                        <span class="sffc-sidebar-user-name"><?php echo esc_html($user_name); ?></span>
                        <span class="sffc-sidebar-user-tier">Professional</span>
                    </div>
                    <svg class="sffc-sidebar-user-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 18l6-6-6-6"/>
                    </svg>
                </div>
            </div>
        </aside>
        <?php
        return ob_get_clean();
    }

    /**
     * Render mobile bottom navigation
     */
    private function render_mobile_navigation($active_section) {
        $mobile_nav_items = array(
            'overview' => array(
                'label' => 'Home',
                'icon' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>'
            ),
            'jobs' => array(
                'label' => 'Jobs',
                'icon' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16"/></svg>'
            ),
            'trends' => array(
                'label' => 'Trends',
                'icon' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>'
            ),
            'market' => array(
                'label' => 'News',
                'icon' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>'
            ),
            'profile' => array(
                'label' => 'Profile',
                'icon' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>'
            ),
        );

        ob_start();
        ?>
        <nav class="sffc-mobile-nav" id="sffc-mobile-nav">
            <?php foreach ($mobile_nav_items as $section_id => $item) : ?>
                <button class="sffc-mobile-nav-item <?php echo $active_section === $section_id ? 'active' : ''; ?>"
                        data-section="<?php echo esc_attr($section_id); ?>">
                    <span class="sffc-mobile-nav-icon"><?php echo $item['icon']; ?></span>
                    <span class="sffc-mobile-nav-label"><?php echo esc_html($item['label']); ?></span>
                </button>
            <?php endforeach; ?>
        </nav>
        <?php
        return ob_get_clean();
    }

    /**
     * Render Jobs section (full job matches list)
     */
    private function render_jobs_section($user_id) {
        $matching_jobs = $this->get_matching_jobs_for_carousel($user_id, 20);
        $data_manager = $this->get_data_manager();
        $saved_count = $data_manager->get_saved_jobs_count($user_id);
        $pipeline_stats = $data_manager->get_pipeline_stats($user_id);
        $excellent_count = count(array_filter($matching_jobs, function($j) { return $j['match_score'] >= 80; }));

        ob_start();
        ?>
        <!-- Jobs Section - Institutional Design -->
        <div class="sffc-inst-section">
            <div class="sffc-inst-header">
                <div class="sffc-inst-title-block">
                    <h2 class="sffc-inst-title">Job Matches</h2>
                    <p class="sffc-inst-subtitle">Opportunities matching your profile</p>
                </div>
                <div class="sffc-inst-controls">
                    <select id="sffc-jobs-sort" class="sffc-filter-select">
                        <option value="match">Best Match</option>
                        <option value="date">Most Recent</option>
                        <option value="salary">Highest Salary</option>
                    </select>
                    <select id="sffc-jobs-location" class="sffc-filter-select">
                        <option value="">All Locations</option>
                        <option value="london">London</option>
                        <option value="new-york">New York</option>
                        <option value="singapore">Singapore</option>
                        <option value="hong-kong">Hong Kong</option>
                    </select>
                </div>
            </div>

            <!-- Jobs Stats Banner -->
            <div class="sffc-jobs-banner">
                <div class="sffc-jobs-stat-item">
                    <span class="sffc-stat-value"><?php echo count($matching_jobs); ?></span>
                    <span class="sffc-stat-label">Matches</span>
                </div>
                <div class="sffc-jobs-stat-item sffc-stat-accent">
                    <span class="sffc-stat-value"><?php echo $excellent_count; ?></span>
                    <span class="sffc-stat-label">Excellent</span>
                </div>
                <div class="sffc-jobs-stat-item">
                    <span class="sffc-stat-value" id="sffc-saved-jobs-count"><?php echo esc_html($saved_count); ?></span>
                    <span class="sffc-stat-label">Saved</span>
                </div>
                <div class="sffc-jobs-stat-item">
                    <span class="sffc-stat-value"><?php echo esc_html($pipeline_stats['total']); ?></span>
                    <span class="sffc-stat-label">Applied</span>
                </div>
            </div>
        </div>

        <div class="sffc-jobs-grid" id="sffc-jobs-grid">
            <?php if (!empty($matching_jobs)) : ?>
                <?php foreach ($matching_jobs as $job) : ?>
                    <?php echo $this->render_job_match_card($job); ?>
                <?php endforeach; ?>
            <?php else : ?>
                <div class="sffc-empty-state">
                    <div class="sffc-empty-icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/>
                            <path d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16"/>
                        </svg>
                    </div>
                    <h3>No job matches yet</h3>
                    <p>Complete your profile to see personalized job matches</p>
                    <button class="sffc-btn sffc-btn-primary sffc-quick-edit-trigger">Complete Profile</button>
                </div>
            <?php endif; ?>
        </div>

        <?php if (count($matching_jobs) > 12) : ?>
        <div class="sffc-jobs-load-more">
            <button class="sffc-btn sffc-btn-outline" id="sffc-load-more-jobs">
                Load More Jobs
            </button>
        </div>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }

    /**
     * Render membership modal
     */
    private function render_membership_modal($membership) {
        $level = $membership['level'] ?? 'free';

        // Get plan comparison data
        $comparison = array();
        if (class_exists('SFFC_Dashboard_Membership_Handler')) {
            $handler = SFFC_Dashboard_Membership_Handler::get_instance();
            $comparison = $handler->get_plan_comparison();
            $full_data = $handler->get_membership_data();
        } else {
            $full_data = $membership;
        }

        ob_start();
        ?>
        <div class="sffc-modal-overlay" id="sffc-membership-modal" style="display: none;">
            <div class="sffc-modal sffc-membership-modal">
                <button class="sffc-modal-close" id="sffc-close-membership-modal">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>

                <div class="sffc-modal-header">
                    <h2>Your Membership</h2>
                    <p>Unlock powerful career intelligence features</p>
                </div>

                <div class="sffc-modal-body">
                    <!-- Current Plan Summary -->
                    <div class="sffc-current-plan-summary">
                        <div class="sffc-plan-icon sffc-plan-<?php echo esc_attr($level); ?>">
                            <?php if ($level === 'executive'): ?>
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2z"/></svg>
                            <?php elseif ($level === 'professional'): ?>
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                            <?php else: ?>
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>
                            <?php endif; ?>
                        </div>
                        <div class="sffc-plan-details">
                            <h3><?php echo esc_html(ucfirst($level)); ?> Plan</h3>
                            <?php if (!empty($full_data['subscription']['expires'])): ?>
                                <p class="sffc-plan-expires">Renews: <?php echo esc_html(date('M j, Y', strtotime($full_data['subscription']['expires']))); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php if ($level !== 'executive'): ?>
                            <a href="#sffc-plan-comparison" class="sffc-btn sffc-btn-primary sffc-btn-sm">View Plans</a>
                        <?php endif; ?>
                    </div>

                    <!-- Usage Stats -->
                    <div class="sffc-usage-stats-grid">
                        <h4>Your Usage This Period</h4>
                        <div class="sffc-usage-items" id="sffc-usage-items">
                            <!-- Populated by JS -->
                            <div class="sffc-usage-item">
                                <span class="sffc-usage-label">Job Matches</span>
                                <div class="sffc-usage-progress">
                                    <div class="sffc-usage-progress-bar" style="width: 0%"></div>
                                </div>
                                <span class="sffc-usage-count">--/--</span>
                            </div>
                            <div class="sffc-usage-item">
                                <span class="sffc-usage-label">Saved Articles</span>
                                <div class="sffc-usage-progress">
                                    <div class="sffc-usage-progress-bar" style="width: 0%"></div>
                                </div>
                                <span class="sffc-usage-count">--/--</span>
                            </div>
                            <div class="sffc-usage-item">
                                <span class="sffc-usage-label">Profile Views</span>
                                <div class="sffc-usage-progress">
                                    <div class="sffc-usage-progress-bar" style="width: 0%"></div>
                                </div>
                                <span class="sffc-usage-count">--/--</span>
                            </div>
                        </div>
                    </div>

                    <!-- Plan Comparison -->
                    <div class="sffc-plan-comparison" id="sffc-plan-comparison">
                        <h4>Compare Plans</h4>
                        <div class="sffc-plans-grid">
                            <!-- Free Plan -->
                            <div class="sffc-plan-card <?php echo $level === 'free' ? 'sffc-current' : ''; ?>">
                                <div class="sffc-plan-header">
                                    <h5>Free</h5>
                                    <div class="sffc-plan-price">
                                        <span class="sffc-price-amount">£0</span>
                                    </div>
                                </div>
                                <ul class="sffc-plan-features">
                                    <li>Weekly stats refresh</li>
                                    <li>30-day trend history</li>
                                    <li>Top 5 skills analysis</li>
                                    <li>5 news articles/day</li>
                                    <li>1 salary location</li>
                                </ul>
                                <?php if ($level === 'free'): ?>
                                    <span class="sffc-plan-current-badge">Current Plan</span>
                                <?php endif; ?>
                            </div>

                            <!-- Professional Plan -->
                            <div class="sffc-plan-card <?php echo $level === 'professional' ? 'sffc-current' : ''; ?>">
                                <div class="sffc-plan-header">
                                    <h5>Professional</h5>
                                    <div class="sffc-plan-price">
                                        <span class="sffc-price-amount">£49.99</span>
                                        <span class="sffc-price-period">/month</span>
                                    </div>
                                </div>
                                <ul class="sffc-plan-features">
                                    <li>Daily stats refresh</li>
                                    <li>12-month trend history</li>
                                    <li>Full skills analysis</li>
                                    <li>20 news articles/day</li>
                                    <li>3 salary locations</li>
                                    <li>Export reports</li>
                                </ul>
                                <?php if ($level === 'professional'): ?>
                                    <span class="sffc-plan-current-badge">Current Plan</span>
                                <?php elseif ($level === 'free'): ?>
                                    <a href="/membership?plan=professional" class="sffc-btn sffc-btn-outline sffc-btn-block">Upgrade</a>
                                <?php endif; ?>
                            </div>

                            <!-- Executive Plan -->
                            <div class="sffc-plan-card sffc-plan-featured <?php echo $level === 'executive' ? 'sffc-current' : ''; ?>">
                                <span class="sffc-plan-popular">Most Popular</span>
                                <div class="sffc-plan-header">
                                    <h5>Executive</h5>
                                    <div class="sffc-plan-price">
                                        <span class="sffc-price-amount">£69.99</span>
                                        <span class="sffc-price-period">/month</span>
                                    </div>
                                </div>
                                <ul class="sffc-plan-features">
                                    <li>Real-time stats</li>
                                    <li>Unlimited trend history</li>
                                    <li>AI-powered insights</li>
                                    <li>Unlimited news access</li>
                                    <li>Unlimited salary locations</li>
                                    <li>Export all data</li>
                                    <li>Priority refresh</li>
                                </ul>
                                <?php if ($level === 'executive'): ?>
                                    <span class="sffc-plan-current-badge">Current Plan</span>
                                <?php else: ?>
                                    <a href="/membership?plan=executive" class="sffc-btn sffc-btn-primary sffc-btn-block">Upgrade</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Subscription Details -->
                    <?php if ($level !== 'free'): ?>
                    <div class="sffc-subscription-details">
                        <h4>Subscription Details</h4>
                        <div class="sffc-subscription-info">
                            <div class="sffc-subscription-row">
                                <span>Status</span>
                                <span class="sffc-status-badge sffc-status-active">Active</span>
                            </div>
                            <?php if (!empty($full_data['subscription']['payment_method'])): ?>
                            <div class="sffc-subscription-row">
                                <span>Payment Method</span>
                                <span><?php echo esc_html($full_data['subscription']['payment_method']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($full_data['subscription']['next_billing'])): ?>
                            <div class="sffc-subscription-row">
                                <span>Next Billing</span>
                                <span><?php echo esc_html(date('M j, Y', strtotime($full_data['subscription']['next_billing']))); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="sffc-subscription-actions">
                            <a href="/account/subscriptions" class="sffc-btn sffc-btn-outline sffc-btn-sm">Manage Subscription</a>
                            <a href="/account/billing" class="sffc-btn sffc-btn-outline sffc-btn-sm">Billing History</a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render upgrade prompt template (for contextual display)
     */
    private function render_upgrade_prompt_template() {
        ob_start();
        ?>
        <div class="sffc-upgrade-prompt-overlay" id="sffc-upgrade-prompt" style="display: none;">
            <div class="sffc-upgrade-prompt">
                <button class="sffc-prompt-close" id="sffc-close-upgrade-prompt">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
                <div class="sffc-prompt-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M12 2L15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2z"/>
                    </svg>
                </div>
                <h3 id="sffc-prompt-title">Unlock This Feature</h3>
                <p id="sffc-prompt-description">Upgrade to access this premium feature and boost your career intelligence.</p>
                <div class="sffc-prompt-comparison">
                    <div class="sffc-prompt-current">
                        <span class="sffc-prompt-label">Current</span>
                        <span class="sffc-prompt-value" id="sffc-prompt-current-value">--</span>
                    </div>
                    <div class="sffc-prompt-arrow">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 12h14M12 5l7 7-7 7"/>
                        </svg>
                    </div>
                    <div class="sffc-prompt-upgrade">
                        <span class="sffc-prompt-label">With Upgrade</span>
                        <span class="sffc-prompt-value" id="sffc-prompt-upgrade-value">--</span>
                    </div>
                </div>
                <div class="sffc-prompt-actions">
                    <a href="/memberships/" class="sffc-btn sffc-btn-primary" id="sffc-prompt-upgrade-btn">Upgrade Now</a>
                    <button class="sffc-btn sffc-btn-outline" id="sffc-prompt-dismiss">Maybe Later</button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render guest prompt for non-logged-in users
     */
    private function render_guest_prompt() {
        ob_start();
        ?>
        <div class="sffc-career-dashboard sffc-dashboard-guest">
            <div class="sffc-guest-prompt">
                <div class="sffc-guest-icon">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"/>
                        <path d="M12 6v6l4 2"/>
                    </svg>
                </div>
                <h2>Your Career Intelligence Dashboard</h2>
                <p>Get personalized insights on your career trajectory, skills demand, salary benchmarks, and market trends.</p>

                <div class="sffc-guest-features">
                    <div class="sffc-guest-feature">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                        </svg>
                        <span>Real-time market trends</span>
                    </div>
                    <div class="sffc-guest-feature">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                        </svg>
                        <span>Skills gap analysis</span>
                    </div>
                    <div class="sffc-guest-feature">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                        </svg>
                        <span>Salary benchmarking</span>
                    </div>
                    <div class="sffc-guest-feature">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                        </svg>
                        <span>AI-powered recommendations</span>
                    </div>
                </div>

                <div class="sffc-guest-actions">
                    <a href="/login-auth/" class="sffc-btn sffc-btn-primary sffc-btn-lg">
                        Sign In to Access
                    </a>
                    <a href="/register/" class="sffc-btn sffc-btn-outline sffc-btn-lg">
                        Create Free Account
                    </a>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render dashboard header
     */
    private function render_header($membership) {
        $user = wp_get_current_user();
        $first_name = $user->first_name ?: $user->display_name;
        $profile_completion = $this->calculate_profile_completion();
        $membership_badge = $this->get_membership_badge($membership);

        ob_start();
        ?>
        <div class="sffc-dashboard-header">
            <div class="sffc-header-welcome">
                <h1>Welcome back, <?php echo esc_html($first_name); ?></h1>
                <p class="sffc-header-subtitle">Your career intelligence at a glance</p>
                <div class="sffc-data-freshness">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                    <span class="sffc-freshness-text">Data updated <time class="sffc-relative-time" datetime="<?php echo esc_attr(current_time('c')); ?>"><?php echo esc_html(current_time('g:i A')); ?></time></span>
                    <span class="sffc-freshness-dot"></span>
                </div>
            </div>

            <div class="sffc-header-meta">
                <!-- Profile Completion Ring -->
                <?php
                $completion_level = 'low';
                if ($profile_completion >= 70) {
                    $completion_level = 'high';
                } elseif ($profile_completion >= 40) {
                    $completion_level = 'medium';
                }
                ?>
                <div class="sffc-profile-completion-ring" data-completion="<?php echo esc_attr($completion_level); ?>" title="Profile <?php echo esc_attr($profile_completion); ?>% complete">
                    <svg viewBox="0 0 36 36" class="sffc-circular-chart">
                        <path class="sffc-circle-bg"
                            d="M18 2.0845
                                a 15.9155 15.9155 0 0 1 0 31.831
                                a 15.9155 15.9155 0 0 1 0 -31.831"
                        />
                        <path class="sffc-circle-progress"
                            stroke-dasharray="<?php echo esc_attr($profile_completion); ?>, 100"
                            d="M18 2.0845
                                a 15.9155 15.9155 0 0 1 0 31.831
                                a 15.9155 15.9155 0 0 1 0 -31.831"
                        />
                    </svg>
                    <div class="sffc-completion-text">
                        <span class="sffc-completion-value"><?php echo esc_html($profile_completion); ?>%</span>
                        <span class="sffc-completion-label">Profile</span>
                    </div>
                </div>

                <!-- Membership Badge -->
                <?php echo $membership_badge; ?>

                <!-- Quick Actions -->
                <div class="sffc-header-actions">
                    <button class="sffc-btn sffc-btn-icon" id="sffc-refresh-dashboard" title="Refresh Data">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M23 4v6h-6M1 20v-6h6"/>
                            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
                        </svg>
                    </button>
                    <button class="sffc-btn sffc-btn-icon" id="sffc-dashboard-settings" title="Settings">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="3"/>
                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the three stat cards
     */
    private function render_stat_cards() {
        $user_id = get_current_user_id();
        $data_manager = $this->get_data_manager();
        $stats = $data_manager->get_overview_stats($user_id);

        // Extract stats with defaults
        $total_apps = isset($stats['total_applications']) ? $stats['total_applications'] : 0;
        $high_matches = isset($stats['high_matches']) ? $stats['high_matches'] : 0;
        $networking = isset($stats['networking_intros']) ? $stats['networking_intros'] : 0;
        $recruiter = isset($stats['recruiter_intros']) ? $stats['recruiter_intros'] : 0;

        // Pipeline stats
        $pipeline = isset($stats['pipeline']) ? $stats['pipeline'] : array();
        $waiting = isset($pipeline['waiting']) ? $pipeline['waiting'] : 0;
        $moved_on = isset($pipeline['moved-on']) ? $pipeline['moved-on'] : 0;
        $first_interview = isset($pipeline['first-interview']) ? $pipeline['first-interview'] : 0;
        $further_interview = isset($pipeline['further-interview']) ? $pipeline['further-interview'] : 0;
        $secured = isset($pipeline['secured']) ? $pipeline['secured'] : 0;
        $applied = isset($pipeline['applied']) ? $pipeline['applied'] : 0;

        // Calculate percentages for progress bars
        $total_pipeline = $waiting + $moved_on + $first_interview + $further_interview + $secured + $applied;
        $waiting_pct = $total_pipeline > 0 ? round(($waiting / $total_pipeline) * 100) : 0;
        $moved_on_pct = $total_pipeline > 0 ? round(($moved_on / $total_pipeline) * 100) : 0;
        $first_interview_pct = $total_pipeline > 0 ? round(($first_interview / $total_pipeline) * 100) : 0;
        $further_interview_pct = $total_pipeline > 0 ? round(($further_interview / $total_pipeline) * 100) : 0;
        $secured_pct = $total_pipeline > 0 ? round(($secured / $total_pipeline) * 100) : 0;

        // Get comparison data
        $comparisons = isset($stats['comparisons']) ? $stats['comparisons'] : array();
        $apps_change = isset($comparisons['applications']['change']) ? $comparisons['applications']['change'] : 0;

        // Get sparkline data (last 14 days)
        $sparkline_data = $data_manager->get_sparkline_data($user_id, 'total_applications', 14);
        $sparkline_json = json_encode($sparkline_data);

        // Calculate success rate
        $success_rate = $total_pipeline > 0 ? round(($secured / $total_pipeline) * 100, 1) : 0;

        ob_start();
        ?>
        <!-- Institutional KPI Stats Banner -->
        <div class="sffc-institutional-kpis">
            <div class="sffc-kpi-grid">
                <div class="sffc-kpi-item">
                    <div class="sffc-kpi-value-lg" data-value="total-applications"><?php echo esc_html($total_apps); ?></div>
                    <div class="sffc-kpi-label-sm">Applications</div>
                    <div class="sffc-kpi-meta">
                        <span class="sffc-kpi-tag">Last 30 days</span>
                        <?php if ($apps_change != 0) : ?>
                        <span class="sffc-kpi-change <?php echo $apps_change >= 0 ? 'sffc-change-up' : 'sffc-change-down'; ?>">
                            <?php echo $apps_change >= 0 ? '+' : ''; ?><?php echo $apps_change; ?>%
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="sffc-kpi-item sffc-kpi-accent">
                    <div class="sffc-kpi-value-lg" data-value="high-matches"><?php echo esc_html($high_matches); ?></div>
                    <div class="sffc-kpi-label-sm">High Matches</div>
                    <div class="sffc-kpi-meta">
                        <span class="sffc-kpi-tag sffc-tag-accent">80%+ fit</span>
                    </div>
                </div>
                <div class="sffc-kpi-item">
                    <div class="sffc-kpi-value-lg" data-value="networking-intros"><?php echo esc_html($networking); ?></div>
                    <div class="sffc-kpi-label-sm">Networking</div>
                    <div class="sffc-kpi-meta">
                        <span class="sffc-kpi-hint">Connections</span>
                    </div>
                </div>
                <div class="sffc-kpi-item">
                    <div class="sffc-kpi-value-lg" data-value="recruiter-intros"><?php echo esc_html($recruiter); ?></div>
                    <div class="sffc-kpi-label-sm">Recruiters</div>
                    <div class="sffc-kpi-meta">
                        <span class="sffc-kpi-hint">Interest sent</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success Rate Indicator -->
        <div class="sffc-success-indicator">
            <div class="sffc-success-header">
                <span class="sffc-success-label">Success Rate</span>
                <span class="sffc-success-pct"><?php echo esc_html($success_rate); ?>%</span>
            </div>
            <div class="sffc-progress-track">
                <div class="sffc-progress-fill" style="width: <?php echo esc_attr(min($success_rate * 10, 100)); ?>%"></div>
            </div>
            <span class="sffc-success-detail"><?php echo esc_html($secured); ?> secured from <?php echo esc_html($total_pipeline); ?> total</span>
        </div>

        <!-- Institutional Charts Grid -->
        <div class="sffc-charts-row">
            <!-- Applications by Industry - Donut Chart -->
            <div class="sffc-chart-card">
                <div class="sffc-chart-header">
                    <h3 class="sffc-chart-title">Industry Distribution</h3>
                    <span class="sffc-chart-subtitle">Applications across sectors</span>
                </div>
                <div class="sffc-chart-body">
                    <div class="sffc-donut-wrapper">
                        <canvas id="sffc-industry-donut-chart"></canvas>
                    </div>
                    <div class="sffc-chart-legend" id="sffc-industry-legend">
                        <!-- Legend populated by JS -->
                    </div>
                </div>
            </div>

            <!-- Applications by Seniority -->
            <div class="sffc-chart-card">
                <div class="sffc-chart-header">
                    <h3 class="sffc-chart-title">Seniority Breakdown</h3>
                    <span class="sffc-chart-subtitle">Role level distribution</span>
                </div>
                <div class="sffc-chart-body">
                    <div class="sffc-bar-wrapper">
                        <canvas id="sffc-seniority-chart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pipeline Status - Institutional Style -->
        <div class="sffc-pipeline-card">
            <div class="sffc-chart-header">
                <h3 class="sffc-chart-title">Application Pipeline</h3>
                <span class="sffc-chart-subtitle">Current status breakdown</span>
            </div>
            <div class="sffc-pipeline-stages">
                <div class="sffc-stage-row" data-stage="waiting">
                    <div class="sffc-stage-left">
                        <span class="sffc-stage-name">Waiting</span>
                        <span class="sffc-stage-desc">Awaiting response</span>
                    </div>
                    <div class="sffc-stage-center">
                        <div class="sffc-stage-track">
                            <div class="sffc-stage-bar sffc-bar-waiting" style="width: <?php echo esc_attr($waiting_pct); ?>%"></div>
                        </div>
                    </div>
                    <div class="sffc-stage-right">
                        <span class="sffc-stage-num" data-count="waiting"><?php echo esc_html($waiting); ?></span>
                        <span class="sffc-stage-pct"><?php echo esc_html($waiting_pct); ?>%</span>
                    </div>
                </div>
                <div class="sffc-stage-row" data-stage="moved">
                    <div class="sffc-stage-left">
                        <span class="sffc-stage-name">Moved On</span>
                        <span class="sffc-stage-desc">No longer active</span>
                    </div>
                    <div class="sffc-stage-center">
                        <div class="sffc-stage-track">
                            <div class="sffc-stage-bar sffc-bar-moved" style="width: <?php echo esc_attr($moved_on_pct); ?>%"></div>
                        </div>
                    </div>
                    <div class="sffc-stage-right">
                        <span class="sffc-stage-num" data-count="moved-on"><?php echo esc_html($moved_on); ?></span>
                        <span class="sffc-stage-pct"><?php echo esc_html($moved_on_pct); ?>%</span>
                    </div>
                </div>
                <div class="sffc-stage-row" data-stage="first">
                    <div class="sffc-stage-left">
                        <span class="sffc-stage-name">1st Interview</span>
                        <span class="sffc-stage-desc">Initial stage</span>
                    </div>
                    <div class="sffc-stage-center">
                        <div class="sffc-stage-track">
                            <div class="sffc-stage-bar sffc-bar-interview" style="width: <?php echo esc_attr($first_interview_pct); ?>%"></div>
                        </div>
                    </div>
                    <div class="sffc-stage-right">
                        <span class="sffc-stage-num" data-count="first-interview"><?php echo esc_html($first_interview); ?></span>
                        <span class="sffc-stage-pct"><?php echo esc_html($first_interview_pct); ?>%</span>
                    </div>
                </div>
                <div class="sffc-stage-row" data-stage="further">
                    <div class="sffc-stage-left">
                        <span class="sffc-stage-name">Further Interview</span>
                        <span class="sffc-stage-desc">Advanced stage</span>
                    </div>
                    <div class="sffc-stage-center">
                        <div class="sffc-stage-track">
                            <div class="sffc-stage-bar sffc-bar-further" style="width: <?php echo esc_attr($further_interview_pct); ?>%"></div>
                        </div>
                    </div>
                    <div class="sffc-stage-right">
                        <span class="sffc-stage-num" data-count="further-interview"><?php echo esc_html($further_interview); ?></span>
                        <span class="sffc-stage-pct"><?php echo esc_html($further_interview_pct); ?>%</span>
                    </div>
                </div>
                <div class="sffc-stage-row sffc-stage-highlight" data-stage="secured">
                    <div class="sffc-stage-left">
                        <span class="sffc-stage-name">Secured</span>
                        <span class="sffc-stage-desc">Offer accepted</span>
                    </div>
                    <div class="sffc-stage-center">
                        <div class="sffc-stage-track">
                            <div class="sffc-stage-bar sffc-bar-secured" style="width: <?php echo esc_attr($secured_pct); ?>%"></div>
                        </div>
                    </div>
                    <div class="sffc-stage-right">
                        <span class="sffc-stage-num" data-count="secured"><?php echo esc_html($secured); ?></span>
                        <span class="sffc-stage-pct"><?php echo esc_html($secured_pct); ?>%</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Applications by Location - Map-style table with chart -->
        <?php
        // Get location distribution from database
        $location_data = $data_manager->get_location_distribution($user_id);
        $country_flags = array(
            'GB' => '🇬🇧', 'US' => '🇺🇸', 'AE' => '🇦🇪', 'IT' => '🇮🇹', 'EG' => '🇪🇬',
            'DE' => '🇩🇪', 'FR' => '🇫🇷', 'ES' => '🇪🇸', 'NL' => '🇳🇱', 'CH' => '🇨🇭',
            'SG' => '🇸🇬', 'HK' => '🇭🇰', 'AU' => '🇦🇺', 'CA' => '🇨🇦', 'IE' => '🇮🇪',
            'LU' => '🇱🇺', 'XX' => '🌍'
        );
        ?>
        <div class="sffc-analytics-card sffc-location-card sffc-full-width">
            <h3 class="sffc-card-title">Applications by Location</h3>
            <div class="sffc-location-content">
                <div class="sffc-location-chart">
                    <canvas id="sffc-location-bar-chart" data-locations='<?php echo esc_attr(json_encode($location_data)); ?>'></canvas>
                </div>
                <div class="sffc-location-table-wrapper">
                    <table class="sffc-location-table sffc-database">
                        <thead>
                            <tr>
                                <th>Location</th>
                                <th>Applications</th>
                                <th>Share</th>
                                <th>High Matches</th>
                            </tr>
                        </thead>
                        <tbody id="sffc-location-table-body">
                            <?php if (!empty($location_data)) : ?>
                                <?php foreach ($location_data as $loc) :
                                    $flag = isset($country_flags[$loc['country_code']]) ? $country_flags[$loc['country_code']] : '🌍';
                                ?>
                                <tr data-location="<?php echo esc_attr($loc['location']); ?>">
                                    <td><span class="sffc-country-flag"><?php echo $flag; ?></span> <?php echo esc_html($loc['country_code']); ?></td>
                                    <td><span class="sffc-table-value"><?php echo esc_html($loc['count']); ?></span></td>
                                    <td><?php echo esc_html($loc['share']); ?>%</td>
                                    <td><span class="sffc-table-value"><?php echo esc_html($loc['high_matches']); ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="4" class="sffc-no-data">No application data yet. Apply to jobs to see location insights.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the Applied Jobs section (replaces Latest Matching Roles)
     */
    private function render_matching_roles_carousel($user_id) {
        ob_start();

        // Get applied jobs for the user from the database
        $data_manager = $this->get_data_manager();
        $applied_jobs = $data_manager->get_user_applications($user_id, array('limit' => 10, 'order_by' => 'stage_updated'));
        ?>
        <div class="sffc-section-header">
            <h2>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
                Applied
            </h2>
            <div class="sffc-section-controls">
                <a href="<?php echo esc_url(home_url('/opportunities/')); ?>" class="sffc-view-all-link">
                    Browse Jobs
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>
        </div>

        <div class="sffc-matching-roles-list sffc-applied-jobs-list" id="sffc-roles-list">
            <?php if (!empty($applied_jobs)) : ?>
                <?php foreach ($applied_jobs as $app) : ?>
                    <?php echo $this->render_applied_job_card($app); ?>
                <?php endforeach; ?>
            <?php else : ?>
                <div class="sffc-no-matches-message">
                    <div class="sffc-no-matches-icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/>
                            <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                        </svg>
                    </div>
                    <h3>No applications yet</h3>
                    <p>Start applying to jobs to track your progress here</p>
                    <a href="<?php echo esc_url(home_url('/opportunities/')); ?>" class="sffc-btn sffc-btn-primary">Browse Jobs</a>
                </div>
            <?php endif; ?>
        </div>

        <?php
        return ob_get_clean();
    }

    /**
     * Render an applied job card
     */
    private function render_applied_job_card($application) {
        $job_id = isset($application['job_id']) ? intval($application['job_id']) : 0;
        $job = get_post($job_id);

        // Get job details
        $job_title = isset($application['job_title']) ? $application['job_title'] : ($job ? get_the_title($job_id) : 'Job Position');
        $company = isset($application['company_name']) ? $application['company_name'] : get_post_meta($job_id, '_sffc_company_name', true);
        $location = isset($application['location']) ? $application['location'] : $this->get_job_location($job_id);
        $stage = isset($application['stage']) ? $application['stage'] : 'applied';
        $applied_date = isset($application['applied_date']) ? $application['applied_date'] : '';

        // Get stage info
        $stage_label = $this->get_stage_label($stage);
        $stage_class = 'sffc-stage-' . sanitize_html_class($stage);

        // Calculate days since applied
        $days_ago = '';
        if (!empty($applied_date)) {
            $diff = human_time_diff(strtotime($applied_date), current_time('timestamp'));
            $days_ago = $diff . ' ago';
        }

        // Get company logo or initials
        $company_logo = $this->get_company_logo($job_id);
        $company_initials = $this->get_company_initials($company);

        ob_start();
        ?>
        <div class="sffc-applied-job-card" data-job-id="<?php echo esc_attr($job_id); ?>" data-stage="<?php echo esc_attr($stage); ?>">
            <div class="sffc-applied-job-logo">
                <?php if (!empty($company_logo)) : ?>
                    <img src="<?php echo esc_url($company_logo); ?>" alt="<?php echo esc_attr($company); ?>">
                <?php else : ?>
                    <span class="sffc-company-initials"><?php echo esc_html($company_initials); ?></span>
                <?php endif; ?>
            </div>

            <div class="sffc-applied-job-info">
                <h4 class="sffc-applied-job-title">
                    <?php if ($job) : ?>
                        <a href="<?php echo esc_url(get_permalink($job_id)); ?>"><?php echo esc_html($job_title); ?></a>
                    <?php else : ?>
                        <?php echo esc_html($job_title); ?>
                    <?php endif; ?>
                </h4>
                <div class="sffc-applied-job-meta">
                    <span class="sffc-applied-job-company"><?php echo esc_html($company ?: 'Company'); ?></span>
                    <?php if (!empty($location)) : ?>
                        <span class="sffc-meta-separator">•</span>
                        <span class="sffc-applied-job-location"><?php echo esc_html($location); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($days_ago)) : ?>
                        <span class="sffc-meta-separator">•</span>
                        <span class="sffc-applied-job-date"><?php echo esc_html($days_ago); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="sffc-applied-job-stage">
                <div class="sffc-stage-dropdown">
                    <button type="button" class="sffc-stage-trigger has-stage" data-job-id="<?php echo esc_attr($job_id); ?>">
                        <span class="sffc-stage-indicator" data-stage="<?php echo esc_attr($stage); ?>"></span>
                        <span class="sffc-stage-text"><?php echo esc_html($stage_label); ?></span>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </button>
                    <div class="sffc-stage-menu">
                        <button type="button" class="sffc-stage-option" data-stage="applied">
                            <span class="sffc-stage-dot" data-stage="applied"></span>
                            Applied
                        </button>
                        <button type="button" class="sffc-stage-option" data-stage="waiting">
                            <span class="sffc-stage-dot" data-stage="waiting"></span>
                            Waiting to Hear Back
                        </button>
                        <button type="button" class="sffc-stage-option" data-stage="first-interview">
                            <span class="sffc-stage-dot" data-stage="first-interview"></span>
                            First Interview
                        </button>
                        <button type="button" class="sffc-stage-option" data-stage="further-interview">
                            <span class="sffc-stage-dot" data-stage="further-interview"></span>
                            Further Interview
                        </button>
                        <button type="button" class="sffc-stage-option" data-stage="secured">
                            <span class="sffc-stage-dot" data-stage="secured"></span>
                            Secured
                        </button>
                        <button type="button" class="sffc-stage-option" data-stage="moved-on">
                            <span class="sffc-stage-dot" data-stage="moved-on"></span>
                            Moved On
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get matching jobs for the carousel
     */
    private function get_matching_jobs_for_carousel($user_id, $limit = 10) {
        // Use job matcher if available
        if (class_exists('SFFC_Job_Matcher')) {
            $matcher = SFFC_Job_Matcher::get_instance();
            $matches = $matcher->calculate_user_job_matches($user_id, $limit);

            // Transform the data for our carousel
            $jobs = array();
            foreach ($matches as $match) {
                $job_id = $match['job_id'];
                $jobs[] = array(
                    'id' => $job_id,
                    'title' => $match['job_title'],
                    'company' => $match['company'] ?: get_post_meta($job_id, '_sffc_company_name', true),
                    'location' => $match['location'] ?: $this->get_job_location($job_id),
                    'match_score' => round($match['match_data']['overall_score']),
                    'match_strength' => $match['match_data']['match_strength'],
                    'salary' => $this->get_job_salary($job_id),
                    'posted_date' => get_the_date('', $job_id),
                    'job_type' => $this->get_job_type($job_id),
                    'permalink' => get_permalink($job_id),
                    'logo' => $this->get_company_logo($job_id),
                );
            }
            return $jobs;
        }

        // Fallback: Get recent jobs with basic profile-based match calculation
        $args = array(
            'post_type' => 'sffc_job',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
        );

        // Get user profile data for matching
        $user_profile = $this->get_user_profile_for_matching($user_id);

        $jobs_query = new WP_Query($args);
        $jobs = array();

        if ($jobs_query->have_posts()) {
            while ($jobs_query->have_posts()) {
                $jobs_query->the_post();
                $job_id = get_the_ID();

                // Calculate basic match score based on profile
                $match_data = $this->calculate_basic_match_score($user_profile, $job_id);

                $jobs[] = array(
                    'id' => $job_id,
                    'title' => get_the_title(),
                    'company' => get_post_meta($job_id, 'company', true) ?: get_post_meta($job_id, '_sffc_company_name', true),
                    'location' => $this->get_job_location($job_id),
                    'match_score' => $match_data['score'],
                    'match_strength' => $match_data['strength'],
                    'match_breakdown' => $match_data['breakdown'],
                    'salary' => $this->get_job_salary($job_id),
                    'posted_date' => get_the_date(),
                    'job_type' => $this->get_job_type($job_id),
                    'permalink' => get_permalink(),
                    'logo' => $this->get_company_logo($job_id),
                    'skills' => $this->get_job_skills($job_id),
                );
            }
            wp_reset_postdata();
        }

        // Sort by match score
        usort($jobs, function($a, $b) {
            return $b['match_score'] <=> $a['match_score'];
        });

        return $jobs;
    }

    /**
     * Get user profile data for matching
     */
    private function get_user_profile_for_matching($user_id) {
        $profile = array(
            'skills' => array(),
            'experience_years' => 0,
            'industries' => array(),
            'locations' => array(),
            'seniority' => '',
        );

        // Try to get from profile manager
        if (class_exists('SFFC_User_Profile_Manager')) {
            $manager = SFFC_User_Profile_Manager::get_instance();
            $full_profile = $manager->get_user_profile($user_id);

            if ($full_profile) {
                $profile['skills'] = $full_profile['skills'] ?? array();
                $profile['experience_years'] = intval($full_profile['years_experience'] ?? 0);
                $profile['industries'] = $full_profile['preferred_industries'] ?? array();
                $profile['locations'] = $full_profile['preferred_locations'] ?? array();
                $profile['seniority'] = $full_profile['seniority_level'] ?? '';
            }
        }

        // Fallback to user meta
        if (empty($profile['skills'])) {
            $skills_meta = get_user_meta($user_id, 'sffc_skills', true);
            if (is_array($skills_meta)) {
                $profile['skills'] = $skills_meta;
            }
        }

        if (empty($profile['experience_years'])) {
            $profile['experience_years'] = intval(get_user_meta($user_id, 'sffc_years_experience', true));
        }

        return $profile;
    }

    /**
     * Calculate basic match score based on profile and job
     */
    private function calculate_basic_match_score($user_profile, $job_id) {
        $score = 50; // Base score
        $breakdown = array(
            'skills' => 0,
            'experience' => 0,
            'location' => 0,
            'industry' => 0,
        );

        // Skills matching (40% weight)
        $job_skills = $this->get_job_skills($job_id);
        $user_skills = array_map(function($s) {
            return is_array($s) ? strtolower($s['skill_name'] ?? $s['name'] ?? '') : strtolower($s);
        }, $user_profile['skills']);

        if (!empty($job_skills) && !empty($user_skills)) {
            $matched = 0;
            foreach ($job_skills as $skill) {
                $skill_lower = strtolower($skill);
                foreach ($user_skills as $user_skill) {
                    if (strpos($user_skill, $skill_lower) !== false || strpos($skill_lower, $user_skill) !== false) {
                        $matched++;
                        break;
                    }
                }
            }
            $skills_score = count($job_skills) > 0 ? ($matched / count($job_skills)) * 100 : 50;
            $breakdown['skills'] = round($skills_score);
            $score += ($skills_score - 50) * 0.4;
        }

        // Experience matching (30% weight)
        $job_experience = get_post_meta($job_id, '_sffc_experience_level', true) ?: get_post_meta($job_id, 'experience_level', true);
        if ($user_profile['experience_years'] > 0) {
            $exp_score = 50;
            if ($job_experience) {
                $job_years = $this->parse_experience_years($job_experience);
                if ($user_profile['experience_years'] >= $job_years) {
                    $exp_score = min(100, 70 + ($user_profile['experience_years'] - $job_years) * 5);
                } else {
                    $exp_score = max(20, 70 - ($job_years - $user_profile['experience_years']) * 10);
                }
            } else {
                $exp_score = 60 + min(20, $user_profile['experience_years'] * 2);
            }
            $breakdown['experience'] = round($exp_score);
            $score += ($exp_score - 50) * 0.3;
        }

        // Location matching (15% weight)
        $job_location = $this->get_job_location($job_id);
        if (!empty($user_profile['locations']) && $job_location) {
            $loc_score = 50;
            foreach ($user_profile['locations'] as $pref_loc) {
                if (stripos($job_location, $pref_loc) !== false || stripos($pref_loc, $job_location) !== false) {
                    $loc_score = 100;
                    break;
                }
            }
            $breakdown['location'] = round($loc_score);
            $score += ($loc_score - 50) * 0.15;
        }

        // Industry matching (15% weight)
        $job_industry = get_post_meta($job_id, '_sffc_industry', true) ?: get_post_meta($job_id, 'industry', true);
        if (!empty($user_profile['industries']) && $job_industry) {
            $ind_score = 50;
            foreach ($user_profile['industries'] as $pref_ind) {
                if (stripos($job_industry, $pref_ind) !== false || stripos($pref_ind, $job_industry) !== false) {
                    $ind_score = 100;
                    break;
                }
            }
            $breakdown['industry'] = round($ind_score);
            $score += ($ind_score - 50) * 0.15;
        }

        // Clamp score between 0 and 100
        $score = max(0, min(100, round($score)));

        // Determine strength
        $strength = 'Fair';
        if ($score >= 80) {
            $strength = 'Excellent';
        } elseif ($score >= 60) {
            $strength = 'Good';
        }

        return array(
            'score' => $score,
            'strength' => $strength,
            'breakdown' => $breakdown,
        );
    }

    /**
     * Parse experience level to years
     */
    private function parse_experience_years($level) {
        $level_lower = strtolower($level);
        if (strpos($level_lower, 'entry') !== false || strpos($level_lower, 'junior') !== false) {
            return 1;
        } elseif (strpos($level_lower, 'mid') !== false || strpos($level_lower, 'associate') !== false) {
            return 3;
        } elseif (strpos($level_lower, 'senior') !== false) {
            return 5;
        } elseif (strpos($level_lower, 'lead') !== false || strpos($level_lower, 'principal') !== false) {
            return 8;
        } elseif (strpos($level_lower, 'director') !== false || strpos($level_lower, 'head') !== false) {
            return 10;
        } elseif (strpos($level_lower, 'vp') !== false || strpos($level_lower, 'executive') !== false) {
            return 15;
        }
        // Try to extract number
        preg_match('/(\d+)/', $level, $matches);
        return isset($matches[1]) ? intval($matches[1]) : 3;
    }

    /**
     * Get job skills from meta or taxonomy
     */
    private function get_job_skills($job_id) {
        // Try taxonomy first
        $terms = wp_get_post_terms($job_id, 'job_skill', array('fields' => 'names'));
        if (!empty($terms) && !is_wp_error($terms)) {
            return $terms;
        }

        // Try meta
        $skills = get_post_meta($job_id, '_sffc_skills', true);
        if (!empty($skills)) {
            return is_array($skills) ? $skills : explode(',', $skills);
        }

        $skills = get_post_meta($job_id, 'required_skills', true);
        if (!empty($skills)) {
            return is_array($skills) ? $skills : explode(',', $skills);
        }

        return array();
    }

    /**
     * Get job location from taxonomy or meta
     */
    private function get_job_location($job_id) {
        // Try taxonomy first
        $locations = wp_get_post_terms($job_id, 'job_location', array('fields' => 'names'));
        if (!empty($locations) && !is_wp_error($locations)) {
            return $locations[0];
        }

        // Fallback to meta
        $location = get_post_meta($job_id, 'location', true);
        if ($location) {
            return $location;
        }

        $location = get_post_meta($job_id, '_sffc_location', true);
        return $location ?: 'Location TBD';
    }

    /**
     * Get job salary from meta
     */
    private function get_job_salary($job_id) {
        $salary = get_post_meta($job_id, 'salary_range', true);
        if ($salary) {
            return $salary;
        }

        $salary_min = get_post_meta($job_id, '_sffc_salary_min', true);
        $salary_max = get_post_meta($job_id, '_sffc_salary_max', true);

        if ($salary_min && $salary_max) {
            return '£' . number_format($salary_min / 1000) . 'k - £' . number_format($salary_max / 1000) . 'k';
        }

        return 'Competitive';
    }

    /**
     * Get job type from taxonomy or meta
     */
    private function get_job_type($job_id) {
        $types = wp_get_post_terms($job_id, 'job_type', array('fields' => 'names'));
        if (!empty($types) && !is_wp_error($types)) {
            return $types[0];
        }

        return get_post_meta($job_id, 'job_type', true) ?: 'Full-time';
    }

    /**
     * Get company logo for job
     */
    private function get_company_logo($job_id) {
        // Check for company logo in meta
        $logo = get_post_meta($job_id, '_sffc_company_logo', true);
        if ($logo) {
            return $logo;
        }

        // Check if there's a linked company post
        $company_id = get_post_meta($job_id, '_sffc_company_id', true);
        if ($company_id) {
            $logo = get_the_post_thumbnail_url($company_id, 'thumbnail');
            if ($logo) {
                return $logo;
            }
        }

        // Try to get company name and use Clearbit Logo API
        $company_name = get_post_meta($job_id, '_sffc_company_name', true);
        if (empty($company_name)) {
            $company_name = get_post_meta($job_id, 'company_name', true);
        }
        if (empty($company_name)) {
            // Try to get from job title taxonomy
            $companies = wp_get_post_terms($job_id, 'company', array('fields' => 'names'));
            if (!empty($companies) && !is_wp_error($companies)) {
                $company_name = $companies[0];
            }
        }

        if (!empty($company_name)) {
            // Generate Clearbit logo URL
            $domain = $this->company_to_domain($company_name);
            if ($domain) {
                return 'https://logo.clearbit.com/' . $domain;
            }
        }

        // Return empty for placeholder
        return '';
    }

    /**
     * Convert company name to domain for logo lookup
     */
    private function company_to_domain($company_name) {
        // Common company domain mappings
        $known_companies = array(
            'goldman sachs' => 'goldmansachs.com',
            'jp morgan' => 'jpmorgan.com',
            'jpmorgan' => 'jpmorgan.com',
            'morgan stanley' => 'morganstanley.com',
            'blackstone' => 'blackstone.com',
            'kkr' => 'kkr.com',
            'carlyle' => 'carlyle.com',
            'apollo' => 'apollo.com',
            'tpg' => 'tpg.com',
            'warburg pincus' => 'warburgpincus.com',
            'bain capital' => 'baincapital.com',
            'advent' => 'adventinternational.com',
            'cvc' => 'cvc.com',
            'permira' => 'permira.com',
            'ares' => 'aresmgmt.com',
            'silver lake' => 'silverlake.com',
            'hellman & friedman' => 'hfriedman.com',
            'vista equity' => 'vistaequitypartners.com',
            'thoma bravo' => 'thomabravo.com',
            'general atlantic' => 'generalatlantic.com',
            'sequoia' => 'sequoiacap.com',
            'accel' => 'accel.com',
            'andreessen horowitz' => 'a16z.com',
            'a16z' => 'a16z.com',
            'benchmark' => 'benchmark.com',
            'greylock' => 'greylock.com',
            'bessemer' => 'bvp.com',
            'index ventures' => 'indexventures.com',
            'lightspeed' => 'lsvp.com',
            'insight partners' => 'insightpartners.com',
            'tiger global' => 'tigerglobal.com',
            'softbank' => 'softbank.com',
            'bridgepoint' => 'bridgepoint.eu',
            'cinven' => 'cinven.com',
            'eqt' => 'eqtgroup.com',
            'pai partners' => 'paipartners.com',
            'bc partners' => 'bcpartners.com',
            'ardian' => 'ardian.com',
            'apax' => 'apax.com',
            'hg capital' => 'hgcapital.com',
            'montagu' => 'montagu.com',
            'charterhouse' => 'charterhouse.co.uk',
            'oakley capital' => 'oakleycapital.com',
            'graphite capital' => 'graphitecapital.com',
            'livingbridge' => 'livingbridge.com',
            'bowmark' => 'bowmark.com',
            'deloitte' => 'deloitte.com',
            'kpmg' => 'kpmg.com',
            'pwc' => 'pwc.com',
            'ey' => 'ey.com',
            'ernst & young' => 'ey.com',
            'mckinsey' => 'mckinsey.com',
            'bcg' => 'bcg.com',
            'boston consulting' => 'bcg.com',
            'bain' => 'bain.com',
            'bain & company' => 'bain.com',
            'oliver wyman' => 'oliverwyman.com',
            'roland berger' => 'rolandberger.com',
            'strategy&' => 'strategyand.pwc.com',
            'accenture' => 'accenture.com',
            'lazard' => 'lazard.com',
            'rothschild' => 'rothschildandco.com',
            'evercore' => 'evercore.com',
            'moelis' => 'moelis.com',
            'pjt partners' => 'pjtpartners.com',
            'centerview' => 'centerviewpartners.com',
            'perella weinberg' => 'pwpartners.com',
            'greenhill' => 'greenhill.com',
            'houlihan lokey' => 'hl.com',
            'jefferies' => 'jefferies.com',
            'barclays' => 'barclays.com',
            'hsbc' => 'hsbc.com',
            'citi' => 'citi.com',
            'citigroup' => 'citi.com',
            'deutsche bank' => 'db.com',
            'ubs' => 'ubs.com',
            'credit suisse' => 'credit-suisse.com',
            'bnp paribas' => 'bnpparibas.com',
            'societe generale' => 'societegenerale.com',
            'natwest' => 'natwestgroup.com',
            'lloyds' => 'lloydsbankinggroup.com',
            'standard chartered' => 'sc.com',
            'nomura' => 'nomura.com',
            'macquarie' => 'macquarie.com',
            'rbc' => 'rbc.com',
            'td bank' => 'td.com',
            'bank of america' => 'bankofamerica.com',
            'wells fargo' => 'wellsfargo.com',
            'amazon' => 'amazon.com',
            'google' => 'google.com',
            'microsoft' => 'microsoft.com',
            'apple' => 'apple.com',
            'meta' => 'meta.com',
            'facebook' => 'meta.com',
            'netflix' => 'netflix.com',
            'tesla' => 'tesla.com',
            'uber' => 'uber.com',
            'airbnb' => 'airbnb.com',
            'stripe' => 'stripe.com',
            'revolut' => 'revolut.com',
            'monzo' => 'monzo.com',
            'wise' => 'wise.com',
            'transferwise' => 'wise.com',
            'klarna' => 'klarna.com',
            'checkout.com' => 'checkout.com',
        );

        $company_lower = strtolower(trim($company_name));

        // Check known companies first
        if (isset($known_companies[$company_lower])) {
            return $known_companies[$company_lower];
        }

        // Try to generate domain from company name
        // Remove common suffixes
        $company_clean = preg_replace('/\s*(ltd|limited|llc|inc|plc|corp|corporation|group|holdings|partners|capital|management|ventures|investment|advisory)\s*\.?$/i', '', $company_lower);
        $company_clean = preg_replace('/[^a-z0-9]/', '', $company_clean);

        if (strlen($company_clean) >= 3) {
            return $company_clean . '.com';
        }

        return null;
    }

    /**
     * Render a single job match card
     */
    private function render_job_match_card($job) {
        $match_class = 'sffc-match-good';
        if ($job['match_score'] >= 80) {
            $match_class = 'sffc-match-excellent';
        } elseif ($job['match_score'] < 60) {
            $match_class = 'sffc-match-fair';
        }

        // Get application stage from database
        $user_id = get_current_user_id();
        $data_manager = $this->get_data_manager();
        $application_stage = $data_manager->get_job_stage($user_id, $job['id']);
        $has_applied = !empty($application_stage);
        $is_saved = $data_manager->is_job_saved($user_id, $job['id']);

        // Check if job is new (posted within 24 hours)
        $is_new = false;
        if (!empty($job['posted_date'])) {
            $posted_timestamp = strtotime($job['posted_date']);
            if ($posted_timestamp !== false) {
                $is_new = (time() - $posted_timestamp) < 86400; // 24 hours
            }
        }

        // Build CSS classes for job state
        $state_classes = array($match_class);
        if ($has_applied) {
            $state_classes[] = 'sffc-job-applied';
            $state_classes[] = 'sffc-stage-' . $application_stage;
        }
        if ($is_saved) {
            $state_classes[] = 'sffc-job-saved';
        }
        if ($is_new) {
            $state_classes[] = 'sffc-job-new';
        }
        if ($job['match_score'] >= 90) {
            $state_classes[] = 'sffc-job-hot-match';
        }

        ob_start();
        ?>
        <article class="sffc-job-row <?php echo esc_attr(implode(' ', $state_classes)); ?>" data-job-id="<?php echo esc_attr($job['id']); ?>" data-stage="<?php echo esc_attr($application_stage); ?>">
            <?php if ($is_new) : ?>
            <span class="sffc-job-ribbon sffc-ribbon-new">New</span>
            <?php endif; ?>
            <?php if ($job['match_score'] >= 90) : ?>
            <span class="sffc-job-ribbon sffc-ribbon-hot">Hot Match</span>
            <?php endif; ?>
            <!-- Left: Company Logo -->
            <div class="sffc-job-row-logo">
                <?php if (!empty($job['logo'])) : ?>
                    <img src="<?php echo esc_url($job['logo']); ?>" alt="<?php echo esc_attr($job['company']); ?>" />
                <?php else : ?>
                    <div class="sffc-logo-placeholder">
                        <?php echo esc_html(strtoupper(substr($job['company'] ?: 'C', 0, 1))); ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Main: Job Info -->
            <div class="sffc-job-row-main">
                <div class="sffc-job-row-header">
                    <span class="sffc-job-row-company"><?php echo esc_html($job['company'] ?: 'Company'); ?></span>
                    <span class="sffc-job-row-posted"><?php echo esc_html($job['posted_date']); ?></span>
                </div>
                <h3 class="sffc-job-row-title">
                    <a href="<?php echo esc_url($job['permalink']); ?>"><?php echo esc_html($job['title']); ?></a>
                </h3>
                <div class="sffc-job-row-meta">
                    <span class="sffc-job-row-location">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                        </svg>
                        <?php echo esc_html($job['location']); ?>
                    </span>
                    <?php if (!empty($job['salary'])) : ?>
                    <span class="sffc-job-row-salary"><?php echo esc_html($job['salary']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($job['job_type'])) : ?>
                    <span class="sffc-job-row-type"><?php echo esc_html($job['job_type']); ?></span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($job['skills'])) : ?>
                <div class="sffc-job-row-skills">
                    <?php foreach (array_slice($job['skills'], 0, 4) as $skill) : ?>
                    <span class="sffc-skill-tag"><?php echo esc_html($skill); ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right: Match Score + Actions -->
            <div class="sffc-job-row-actions">
                <!-- Match Score Badge with Breakdown -->
                <?php
                $breakdown = isset($job['match_breakdown']) ? $job['match_breakdown'] : array();
                $has_breakdown = !empty($breakdown);
                ?>
                <div class="sffc-match-badge <?php echo esc_attr($match_class); ?>" <?php if ($has_breakdown) : ?>data-breakdown="<?php echo esc_attr(json_encode($breakdown)); ?>"<?php endif; ?>>
                    <span class="sffc-match-value"><?php echo esc_html($job['match_score']); ?>%</span>
                    <span class="sffc-match-label">Match</span>
                    <div class="sffc-match-progress">
                        <div class="sffc-match-progress-fill" style="width: <?php echo esc_attr($job['match_score']); ?>%"></div>
                    </div>
                    <?php if ($has_breakdown) : ?>
                    <!-- Match Breakdown Tooltip -->
                    <div class="sffc-match-tooltip">
                        <div class="sffc-match-tooltip-header">Match Analysis</div>
                        <div class="sffc-match-tooltip-rows">
                            <?php if (isset($breakdown['skills'])) : ?>
                            <div class="sffc-tooltip-row">
                                <span class="sffc-tooltip-label">Skills</span>
                                <div class="sffc-tooltip-bar">
                                    <div class="sffc-tooltip-fill" style="width: <?php echo esc_attr($breakdown['skills']); ?>%"></div>
                                </div>
                                <span class="sffc-tooltip-value"><?php echo esc_html($breakdown['skills']); ?>%</span>
                            </div>
                            <?php endif; ?>
                            <?php if (isset($breakdown['experience'])) : ?>
                            <div class="sffc-tooltip-row">
                                <span class="sffc-tooltip-label">Experience</span>
                                <div class="sffc-tooltip-bar">
                                    <div class="sffc-tooltip-fill" style="width: <?php echo esc_attr($breakdown['experience']); ?>%"></div>
                                </div>
                                <span class="sffc-tooltip-value"><?php echo esc_html($breakdown['experience']); ?>%</span>
                            </div>
                            <?php endif; ?>
                            <?php if (isset($breakdown['location'])) : ?>
                            <div class="sffc-tooltip-row">
                                <span class="sffc-tooltip-label">Location</span>
                                <div class="sffc-tooltip-bar">
                                    <div class="sffc-tooltip-fill" style="width: <?php echo esc_attr($breakdown['location']); ?>%"></div>
                                </div>
                                <span class="sffc-tooltip-value"><?php echo esc_html($breakdown['location']); ?>%</span>
                            </div>
                            <?php endif; ?>
                            <?php if (isset($breakdown['industry'])) : ?>
                            <div class="sffc-tooltip-row">
                                <span class="sffc-tooltip-label">Industry</span>
                                <div class="sffc-tooltip-bar">
                                    <div class="sffc-tooltip-fill" style="width: <?php echo esc_attr($breakdown['industry']); ?>%"></div>
                                </div>
                                <span class="sffc-tooltip-value"><?php echo esc_html($breakdown['industry']); ?>%</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="sffc-tooltip-footer">Based on your profile</div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Application Stage Dropdown -->
                <div class="sffc-stage-dropdown">
                    <button type="button" class="sffc-stage-trigger <?php echo $has_applied ? 'has-stage' : ''; ?>"
                            data-job-id="<?php echo esc_attr($job['id']); ?>">
                        <?php if ($has_applied) : ?>
                            <span class="sffc-stage-indicator" data-stage="<?php echo esc_attr($application_stage); ?>"></span>
                            <span class="sffc-stage-text"><?php echo esc_html($this->get_stage_label($application_stage)); ?></span>
                        <?php else : ?>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="12" y1="8" x2="12" y2="16"/>
                                <line x1="8" y1="12" x2="16" y2="12"/>
                            </svg>
                            <span class="sffc-stage-text">Track</span>
                        <?php endif; ?>
                        <svg class="sffc-dropdown-arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </button>
                    <div class="sffc-stage-menu">
                        <button type="button" class="sffc-stage-option" data-stage="applied">
                            <span class="sffc-stage-dot" data-stage="applied"></span>
                            Applied
                        </button>
                        <button type="button" class="sffc-stage-option" data-stage="waiting">
                            <span class="sffc-stage-dot" data-stage="waiting"></span>
                            Waiting for Response
                        </button>
                        <button type="button" class="sffc-stage-option" data-stage="first-interview">
                            <span class="sffc-stage-dot" data-stage="first-interview"></span>
                            1st Stage Interview
                        </button>
                        <button type="button" class="sffc-stage-option" data-stage="further-interview">
                            <span class="sffc-stage-dot" data-stage="further-interview"></span>
                            Further Interview
                        </button>
                        <button type="button" class="sffc-stage-option" data-stage="secured">
                            <span class="sffc-stage-dot" data-stage="secured"></span>
                            Secured
                        </button>
                        <button type="button" class="sffc-stage-option" data-stage="moved-on">
                            <span class="sffc-stage-dot" data-stage="moved-on"></span>
                            Moved On
                        </button>
                        <?php if ($has_applied) : ?>
                        <div class="sffc-stage-divider"></div>
                        <button type="button" class="sffc-stage-option sffc-stage-remove" data-stage="">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"/>
                                <line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                            Remove Tracking
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Apply Button -->
                <a href="<?php echo esc_url($job['permalink']); ?>" class="sffc-apply-btn" target="_blank">
                    Apply
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                        <polyline points="15 3 21 3 21 9"/>
                        <line x1="10" y1="14" x2="21" y2="3"/>
                    </svg>
                </a>
            </div>
        </article>
        <?php
        return ob_get_clean();
    }

    /**
     * Get human-readable stage label
     */
    private function get_stage_label($stage) {
        $labels = array(
            'applied' => 'Applied',
            'waiting' => 'Waiting',
            'first-interview' => '1st Interview',
            'further-interview' => 'Further Interview',
            'secured' => 'Secured',
            'moved-on' => 'Moved On'
        );
        return isset($labels[$stage]) ? $labels[$stage] : 'Track';
    }

    /**
     * Render main trends chart section
     */
    private function render_trends_chart() {
        ob_start();
        ?>
        <!-- Trends Section - Institutional Design -->
        <div class="sffc-inst-section">
            <div class="sffc-inst-header">
                <div class="sffc-inst-title-block">
                    <h2 class="sffc-inst-title">Career Trends</h2>
                    <p class="sffc-inst-subtitle">Application activity over time</p>
                </div>
                <div class="sffc-inst-controls">
                    <div class="sffc-pill-group">
                        <button class="sffc-pill active" data-series="locations">Locations</button>
                        <button class="sffc-pill" data-series="industries">Industries</button>
                        <button class="sffc-pill" data-series="roles">Roles</button>
                    </div>
                    <div class="sffc-range-pills">
                        <button class="sffc-range-pill" data-range="3m">3M</button>
                        <button class="sffc-range-pill active" data-range="6m">6M</button>
                        <button class="sffc-range-pill" data-range="12m">12M</button>
                    </div>
                </div>
            </div>

            <div class="sffc-chart-container">
                <canvas id="sffc-trends-chart"></canvas>
                <div class="sffc-chart-loading">
                    <div class="sffc-loading-spinner"></div>
                </div>
            </div>

            <div class="sffc-chart-footer">
                <p class="sffc-chart-source">Source: Your application data</p>
            </div>
        </div>

        <!-- Insight Box -->
        <div class="sffc-insight-box">
            <div class="sffc-insight-marker"></div>
            <div class="sffc-insight-body">
                <span class="sffc-insight-label">AI Insight</span>
                <p class="sffc-insight-text" id="sffc-ai-insight">Analyzing market trends...</p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render skills analysis section
     */
    private function render_skills_section() {
        ob_start();
        ?>
        <!-- Skills Section - Institutional Design -->
        <div class="sffc-inst-section">
            <div class="sffc-inst-header">
                <div class="sffc-inst-title-block">
                    <h2 class="sffc-inst-title">Skills Analysis</h2>
                    <p class="sffc-inst-subtitle">Your skills vs market demand</p>
                </div>
            </div>

            <!-- Skills Summary Banner -->
            <div class="sffc-skills-banner">
                <div class="sffc-skills-stat">
                    <span class="sffc-stat-value" data-summary="total">--</span>
                    <span class="sffc-stat-label">Total Skills</span>
                </div>
                <div class="sffc-skills-stat sffc-stat-accent">
                    <span class="sffc-stat-value" data-summary="high-demand">--</span>
                    <span class="sffc-stat-label">High Demand</span>
                </div>
                <div class="sffc-skills-stat">
                    <span class="sffc-stat-value" data-summary="trending">--</span>
                    <span class="sffc-stat-label">Trending</span>
                </div>
                <div class="sffc-skills-stat">
                    <span class="sffc-stat-value" data-summary="salary-premium">--</span>
                    <span class="sffc-stat-label">Premium</span>
                </div>
            </div>

            <!-- Skills Charts -->
            <div class="sffc-skills-grid">
                <div class="sffc-chart-card">
                    <div class="sffc-chart-header">
                        <h3 class="sffc-chart-title">Skills Demand</h3>
                        <span class="sffc-chart-subtitle">Market demand score</span>
                    </div>
                    <div class="sffc-chart-body">
                        <div class="sffc-bar-wrapper">
                            <canvas id="sffc-skills-chart"></canvas>
                        </div>
                        <div class="sffc-demand-legend">
                            <span class="sffc-legend-item"><span class="sffc-dot sffc-dot-high"></span>High (70+)</span>
                            <span class="sffc-legend-item"><span class="sffc-dot sffc-dot-med"></span>Moderate</span>
                            <span class="sffc-legend-item"><span class="sffc-dot sffc-dot-low"></span>Low (&lt;40)</span>
                        </div>
                    </div>
                </div>

                <div class="sffc-chart-card">
                    <div class="sffc-chart-header">
                        <h3 class="sffc-chart-title">Category Profile</h3>
                        <span class="sffc-chart-subtitle">Skills by category</span>
                    </div>
                    <div class="sffc-chart-body">
                        <div class="sffc-radar-wrapper">
                            <canvas id="sffc-skills-radar"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Skills Gap Analysis -->
        <div class="sffc-gap-card">
            <div class="sffc-gap-header">
                <h3 class="sffc-gap-title">Skills Gap Analysis</h3>
                <p class="sffc-gap-subtitle">High-demand skills to boost your profile</p>
            </div>
            <div class="sffc-gap-list" id="sffc-skills-gap-list">
                <div class="sffc-skeleton-row"></div>
            </div>
        </div>

        <!-- Upskilling Recommendations -->
        <div class="sffc-recs-card">
            <div class="sffc-recs-header">
                <h3 class="sffc-recs-title">Upskilling Recommendations</h3>
            </div>
            <div class="sffc-recs-list" id="sffc-upskill-list">
                <div class="sffc-skeleton-row"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render market intelligence section
     */
    private function render_market_intelligence() {
        ob_start();
        ?>
        <!-- News & Insights - Institutional Design -->
        <div class="sffc-inst-section">
            <div class="sffc-inst-header">
                <div class="sffc-inst-title-block">
                    <h2 class="sffc-inst-title">News & Insights</h2>
                    <p class="sffc-inst-subtitle">PE/VC news and deal flow</p>
                </div>
                <div class="sffc-inst-controls">
                    <div class="sffc-pill-group">
                        <button class="sffc-pill active" data-feed="all">All</button>
                        <button class="sffc-pill" data-feed="news">News</button>
                        <button class="sffc-pill" data-feed="deals">Deals</button>
                    </div>
                </div>
            </div>

            <!-- Trending Topics -->
            <div class="sffc-trending-bar" id="sffc-trending-topics">
                <span class="sffc-trending-label">Trending:</span>
                <div class="sffc-trending-pills">
                    <span class="sffc-skeleton-pill"></span>
                    <span class="sffc-skeleton-pill"></span>
                    <span class="sffc-skeleton-pill"></span>
                </div>
            </div>
        </div>

        <!-- News & Deals Content Grid -->
        <div class="sffc-market-grid">
            <div class="sffc-market-main" id="sffc-news-deals-feed" data-source="sffc_pe_news,sffc_pe_deal">
                <div class="sffc-feed-container" id="sffc-market-feed">
                    <div class="sffc-skeleton-card"></div>
                    <div class="sffc-skeleton-card"></div>
                </div>
            </div>

            <!-- Market Sidebar -->
            <div class="sffc-market-side">
                <div class="sffc-side-card">
                    <h3 class="sffc-side-title">Market Signals</h3>
                    <div class="sffc-side-content" id="sffc-signals-list">
                        <div class="sffc-skeleton-row"></div>
                    </div>
                </div>

                <div class="sffc-side-card">
                    <h3 class="sffc-side-title">Saved Articles</h3>
                    <div class="sffc-side-content" id="sffc-saved-list">
                        <p class="sffc-empty-state">No saved articles</p>
                    </div>
                </div>

                <div class="sffc-side-card">
                    <div class="sffc-side-header">
                        <h3 class="sffc-side-title">News Sources</h3>
                        <button type="button" class="sffc-icon-btn" id="sffc-manage-sources" title="Manage">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="3"/>
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                            </svg>
                        </button>
                    </div>
                    <div class="sffc-side-content" id="sffc-sources-list">
                        <?php echo $this->render_news_sources_list(); ?>
                    </div>
                </div>

                <div class="sffc-side-card">
                    <div class="sffc-side-header">
                        <h3 class="sffc-side-title">Alert Keywords</h3>
                        <button type="button" class="sffc-icon-btn" id="sffc-add-keyword" title="Add">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="5" x2="12" y2="19"/>
                                <line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                        </button>
                    </div>
                    <div class="sffc-side-content" id="sffc-keywords-list">
                        <?php echo $this->render_alert_keywords_list(); ?>
                    </div>
                    <div class="sffc-keyword-form" id="sffc-add-keyword-form" style="display: none;">
                        <input type="text" id="sffc-new-keyword" class="sffc-form-input" placeholder="Enter keyword" maxlength="200">
                        <select id="sffc-keyword-type" class="sffc-form-select">
                            <option value="topic">Topic</option>
                            <option value="company">Company</option>
                            <option value="skill">Skill</option>
                        </select>
                        <div class="sffc-form-actions">
                            <button type="button" class="sffc-btn-sm sffc-btn-primary" id="sffc-save-keyword">Add</button>
                            <button type="button" class="sffc-btn-sm sffc-btn-ghost" id="sffc-cancel-keyword">Cancel</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- News Sources Modal -->
        <?php echo $this->render_news_sources_modal(); ?>

        <?php
        return ob_get_clean();
    }

    /**
     * Render news sources list
     */
    private function render_news_sources_list() {
        $user_id = get_current_user_id();
        $sources = $this->get_user_news_sources($user_id);

        if (empty($sources)) {
            return '<p class="sffc-no-sources">Manage your news sources to prioritize content</p>';
        }

        $html = '';
        foreach ($sources as $source) {
            $preference_class = '';
            $preference_icon = '';

            if ($source['preference'] === 'pinned') {
                $preference_class = 'sffc-source-pinned';
                $preference_icon = '<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2z"/></svg>';
            } elseif ($source['preference'] === 'hidden') {
                $preference_class = 'sffc-source-hidden';
                $preference_icon = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
            }

            $html .= sprintf(
                '<div class="sffc-source-item %s" data-source-id="%s">
                    <span class="sffc-source-name">%s</span>
                    <span class="sffc-source-preference">%s</span>
                </div>',
                esc_attr($preference_class),
                esc_attr($source['source_id']),
                esc_html($source['source_name']),
                $preference_icon
            );
        }

        return $html;
    }

    /**
     * Get user news sources with preferences
     */
    private function get_user_news_sources($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'sffc_news_source_preferences';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            // Return default sources if table doesn't exist
            return $this->get_default_news_sources();
        }

        $sources = $wpdb->get_results($wpdb->prepare(
            "SELECT source_id, source_name, preference, priority_score
             FROM $table
             WHERE user_id = %d
             ORDER BY
                 CASE preference
                     WHEN 'pinned' THEN 1
                     WHEN 'normal' THEN 2
                     WHEN 'hidden' THEN 3
                 END,
                 priority_score DESC",
            $user_id
        ), ARRAY_A);

        return !empty($sources) ? $sources : $this->get_default_news_sources();
    }

    /**
     * Get default news sources
     */
    private function get_default_news_sources() {
        return array(
            array('source_id' => 'financial-times', 'source_name' => 'Financial Times', 'preference' => 'normal'),
            array('source_id' => 'wsj', 'source_name' => 'Wall Street Journal', 'preference' => 'normal'),
            array('source_id' => 'bloomberg', 'source_name' => 'Bloomberg', 'preference' => 'normal'),
            array('source_id' => 'reuters', 'source_name' => 'Reuters', 'preference' => 'normal'),
            array('source_id' => 'city-am', 'source_name' => 'City A.M.', 'preference' => 'normal'),
        );
    }

    /**
     * Render alert keywords list
     */
    private function render_alert_keywords_list() {
        $user_id = get_current_user_id();
        $keywords = $this->get_user_alert_keywords($user_id);

        if (empty($keywords)) {
            return '<p class="sffc-no-keywords">Add keywords to get alerts when they appear in news</p>';
        }

        $html = '';
        $type_icons = array(
            'company' => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 3v18M21 9H3M21 15H3"/></svg>',
            'topic' => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 11a9 9 0 0 1 9 9M4 4a16 16 0 0 1 16 16"/><circle cx="5" cy="19" r="1"/></svg>',
            'skill' => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
            'location' => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>',
            'person' => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
        );

        foreach ($keywords as $keyword) {
            $icon = $type_icons[$keyword['keyword_type']] ?? $type_icons['topic'];
            $enabled_class = $keyword['alert_enabled'] ? 'sffc-keyword-active' : 'sffc-keyword-paused';
            $match_badge = $keyword['match_count'] > 0 ? '<span class="sffc-keyword-matches">' . intval($keyword['match_count']) . '</span>' : '';

            $html .= sprintf(
                '<div class="sffc-keyword-item %s" data-keyword-id="%d">
                    <span class="sffc-keyword-type" title="%s">%s</span>
                    <span class="sffc-keyword-text">%s</span>
                    %s
                    <div class="sffc-keyword-controls">
                        <button type="button" class="sffc-keyword-toggle" data-action="%s" title="%s">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                %s
                            </svg>
                        </button>
                        <button type="button" class="sffc-keyword-delete" title="Remove keyword">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"/>
                                <line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                        </button>
                    </div>
                </div>',
                esc_attr($enabled_class),
                intval($keyword['id']),
                esc_attr(ucfirst($keyword['keyword_type'])),
                $icon,
                esc_html($keyword['keyword']),
                $match_badge,
                $keyword['alert_enabled'] ? 'pause' : 'enable',
                $keyword['alert_enabled'] ? 'Pause alerts' : 'Enable alerts',
                $keyword['alert_enabled']
                    ? '<path d="M6 4h4v16H6z"/><path d="M14 4h4v16h-4z"/>'
                    : '<polygon points="5 3 19 12 5 21 5 3"/>'
            );
        }

        return $html;
    }

    /**
     * Get user alert keywords
     */
    private function get_user_alert_keywords($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'sffc_alert_keywords';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return array();
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, keyword, keyword_type, alert_enabled, alert_frequency, match_count, last_match_at
             FROM $table
             WHERE user_id = %d
             ORDER BY created_at DESC",
            $user_id
        ), ARRAY_A);
    }

    /**
     * Render news sources modal
     */
    private function render_news_sources_modal() {
        $user_id = get_current_user_id();
        $all_sources = $this->get_all_news_sources_with_preferences($user_id);

        ob_start();
        ?>
        <div class="sffc-modal-overlay" id="sffc-sources-modal" style="display: none;">
            <div class="sffc-modal sffc-sources-modal">
                <button class="sffc-modal-close" id="sffc-close-sources-modal">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>

                <div class="sffc-modal-header">
                    <h2>
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 20H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v1m2 13a2 2 0 0 1-2-2V7m2 13a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-2"/>
                        </svg>
                        Manage News Sources
                    </h2>
                    <p>Pin sources you want to see first, or hide sources you're not interested in</p>
                </div>

                <div class="sffc-modal-body">
                    <div class="sffc-sources-grid">
                        <?php foreach ($all_sources as $source): ?>
                        <div class="sffc-source-card" data-source-id="<?php echo esc_attr($source['source_id']); ?>">
                            <div class="sffc-source-info">
                                <span class="sffc-source-name"><?php echo esc_html($source['source_name']); ?></span>
                                <?php if (!empty($source['article_count'])): ?>
                                <span class="sffc-source-count"><?php echo intval($source['article_count']); ?> articles</span>
                                <?php endif; ?>
                            </div>
                            <div class="sffc-source-actions">
                                <button type="button" class="sffc-source-btn sffc-source-pin <?php echo $source['preference'] === 'pinned' ? 'active' : ''; ?>"
                                        data-action="pin" title="Pin to top">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="<?php echo $source['preference'] === 'pinned' ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2">
                                        <path d="M12 2L15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2z"/>
                                    </svg>
                                </button>
                                <button type="button" class="sffc-source-btn sffc-source-hide <?php echo $source['preference'] === 'hidden' ? 'active' : ''; ?>"
                                        data-action="hide" title="Hide source">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                                        <line x1="1" y1="1" x2="23" y2="23"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="sffc-modal-footer">
                    <button type="button" class="sffc-btn sffc-btn-outline" id="sffc-reset-sources">Reset to Default</button>
                    <button type="button" class="sffc-btn sffc-btn-primary" id="sffc-save-sources">Save Preferences</button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get all news sources with user preferences
     */
    private function get_all_news_sources_with_preferences($user_id) {
        // Default available sources
        $default_sources = array(
            array('source_id' => 'financial-times', 'source_name' => 'Financial Times'),
            array('source_id' => 'wsj', 'source_name' => 'Wall Street Journal'),
            array('source_id' => 'bloomberg', 'source_name' => 'Bloomberg'),
            array('source_id' => 'reuters', 'source_name' => 'Reuters'),
            array('source_id' => 'city-am', 'source_name' => 'City A.M.'),
            array('source_id' => 'efinancialcareers', 'source_name' => 'eFinancialCareers'),
            array('source_id' => 'dealbreaker', 'source_name' => 'Dealbreaker'),
            array('source_id' => 'ft-alphaville', 'source_name' => 'FT Alphaville'),
            array('source_id' => 'economist', 'source_name' => 'The Economist'),
            array('source_id' => 'barrons', 'source_name' => "Barron's"),
            array('source_id' => 'marketwatch', 'source_name' => 'MarketWatch'),
            array('source_id' => 'cnbc', 'source_name' => 'CNBC'),
        );

        // Get user preferences
        $preferences = $this->get_user_news_sources($user_id);
        $pref_map = array();
        foreach ($preferences as $pref) {
            $pref_map[$pref['source_id']] = $pref['preference'];
        }

        // Merge preferences with defaults
        foreach ($default_sources as &$source) {
            $source['preference'] = isset($pref_map[$source['source_id']]) ? $pref_map[$source['source_id']] : 'normal';
        }

        return $default_sources;
    }

    /**
     * Render salary intelligence section
     */
    private function render_salary_section() {
        ob_start();
        ?>
        <div class="sffc-section-header">
            <h2>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="1" x2="12" y2="23"/>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                </svg>
                Salary Intelligence
            </h2>
            <div class="sffc-section-controls">
                <button class="sffc-btn sffc-btn-sm sffc-btn-outline" id="sffc-export-salary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                        <polyline points="7 10 12 15 17 10"/>
                        <line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                    Export
                </button>
            </div>
        </div>

        <!-- Top Row: Estimate + Total Comp -->
        <div class="sffc-salary-top-row">
            <!-- Your Estimate Card -->
            <div class="sffc-salary-card sffc-salary-estimate">
                <h3>Base Salary Range</h3>
                <div class="sffc-salary-range">
                    <span class="sffc-salary-min" data-value="salary-min">--</span>
                    <span class="sffc-salary-separator">-</span>
                    <span class="sffc-salary-max" data-value="salary-max">--</span>
                </div>
                <p class="sffc-salary-context">Based on your profile, location, and experience</p>
                <div class="sffc-salary-percentile">
                    <div class="sffc-percentile-bar">
                        <div class="sffc-percentile-marker" data-position="50"></div>
                    </div>
                    <div class="sffc-percentile-labels">
                        <span>10th</span>
                        <span>Median</span>
                        <span>90th</span>
                    </div>
                </div>
            </div>

            <!-- Total Compensation Card -->
            <div class="sffc-salary-card sffc-total-comp-card">
                <h3>Total Compensation</h3>
                <div class="sffc-total-comp">
                    <div class="sffc-comp-row">
                        <span class="sffc-comp-label">Base Salary</span>
                        <span class="sffc-comp-value" id="sffc-base-salary-range">--</span>
                    </div>
                    <div class="sffc-comp-row sffc-comp-bonus">
                        <span class="sffc-comp-label">Typical Bonus</span>
                        <span class="sffc-comp-value" id="sffc-bonus-typical">--</span>
                    </div>
                    <div class="sffc-comp-divider"></div>
                    <div class="sffc-comp-row sffc-comp-total">
                        <span class="sffc-comp-label">Total (Typical)</span>
                        <span class="sffc-comp-value" id="sffc-total-typical">--</span>
                    </div>
                </div>
                <div class="sffc-bonus-structure">
                    <span class="sffc-bonus-label">Bonus Range:</span>
                    <span class="sffc-bonus-range" id="sffc-bonus-range">--</span>
                </div>
                <p class="sffc-bonus-note" id="sffc-bonus-note"></p>
            </div>

            <!-- Salary Factors Card -->
            <div class="sffc-salary-card sffc-salary-factors-card">
                <h3>Salary Factors</h3>
                <div class="sffc-factors-list" id="sffc-salary-factors">
                    <!-- Populated by JS -->
                </div>
            </div>
        </div>

        <!-- Location Comparison Section -->
        <div class="sffc-salary-comparison-section">
            <div class="sffc-comparison-header">
                <h3>Location Comparison</h3>
                <div class="sffc-location-selectors">
                    <select id="sffc-location-1" class="sffc-select">
                        <option value="london">London</option>
                        <option value="new-york">New York</option>
                        <option value="san-francisco">San Francisco</option>
                        <option value="hong-kong">Hong Kong</option>
                        <option value="singapore">Singapore</option>
                        <option value="dubai">Dubai</option>
                        <option value="zurich">Zurich</option>
                        <option value="paris">Paris</option>
                        <option value="frankfurt">Frankfurt</option>
                        <option value="sydney">Sydney</option>
                        <option value="toronto">Toronto</option>
                    </select>
                    <span class="sffc-vs">vs</span>
                    <select id="sffc-location-2" class="sffc-select">
                        <option value="new-york">New York</option>
                        <option value="london">London</option>
                        <option value="san-francisco">San Francisco</option>
                        <option value="hong-kong">Hong Kong</option>
                        <option value="singapore">Singapore</option>
                        <option value="dubai">Dubai</option>
                        <option value="zurich">Zurich</option>
                        <option value="paris">Paris</option>
                        <option value="frankfurt">Frankfurt</option>
                        <option value="sydney">Sydney</option>
                        <option value="toronto">Toronto</option>
                    </select>
                </div>
            </div>

            <div class="sffc-comparison-grid">
                <!-- Location 1 Details -->
                <div class="sffc-location-card" id="sffc-location-1-card">
                    <h4 class="sffc-location-name" id="sffc-loc1-name">London</h4>
                    <div class="sffc-location-salary">
                        <div class="sffc-salary-type">
                            <span class="sffc-salary-type-label">Gross</span>
                            <span class="sffc-salary-type-value" id="sffc-loc1-gross">--</span>
                        </div>
                        <div class="sffc-salary-type sffc-salary-net">
                            <span class="sffc-salary-type-label">Net (after tax)</span>
                            <span class="sffc-salary-type-value" id="sffc-loc1-net">--</span>
                        </div>
                    </div>
                    <div class="sffc-location-metrics">
                        <div class="sffc-metric">
                            <span class="sffc-metric-label">Tax Rate</span>
                            <span class="sffc-metric-value" id="sffc-loc1-tax">--</span>
                        </div>
                        <div class="sffc-metric">
                            <span class="sffc-metric-label">Cost of Living</span>
                            <span class="sffc-metric-value" id="sffc-loc1-col">--</span>
                        </div>
                        <div class="sffc-metric">
                            <span class="sffc-metric-label">Purchasing Power</span>
                            <span class="sffc-metric-value" id="sffc-loc1-pp">--</span>
                        </div>
                    </div>
                    <div class="sffc-qol-section">
                        <h5>Quality of Life</h5>
                        <div class="sffc-qol-metrics" id="sffc-loc1-qol">
                            <!-- Populated by JS -->
                        </div>
                    </div>
                </div>

                <!-- Comparison Summary -->
                <div class="sffc-comparison-summary" id="sffc-comparison-summary">
                    <div class="sffc-comparison-stat">
                        <span class="sffc-comparison-label">Gross Salary</span>
                        <span class="sffc-comparison-diff" id="sffc-diff-gross">--</span>
                    </div>
                    <div class="sffc-comparison-stat">
                        <span class="sffc-comparison-label">Net Salary</span>
                        <span class="sffc-comparison-diff" id="sffc-diff-net">--</span>
                    </div>
                    <div class="sffc-comparison-stat sffc-comparison-highlight">
                        <span class="sffc-comparison-label">Purchasing Power</span>
                        <span class="sffc-comparison-diff" id="sffc-diff-pp">--</span>
                    </div>
                    <p class="sffc-comparison-insight" id="sffc-comparison-insight"></p>
                </div>

                <!-- Location 2 Details -->
                <div class="sffc-location-card" id="sffc-location-2-card">
                    <h4 class="sffc-location-name" id="sffc-loc2-name">New York</h4>
                    <div class="sffc-location-salary">
                        <div class="sffc-salary-type">
                            <span class="sffc-salary-type-label">Gross</span>
                            <span class="sffc-salary-type-value" id="sffc-loc2-gross">--</span>
                        </div>
                        <div class="sffc-salary-type sffc-salary-net">
                            <span class="sffc-salary-type-label">Net (after tax)</span>
                            <span class="sffc-salary-type-value" id="sffc-loc2-net">--</span>
                        </div>
                    </div>
                    <div class="sffc-location-metrics">
                        <div class="sffc-metric">
                            <span class="sffc-metric-label">Tax Rate</span>
                            <span class="sffc-metric-value" id="sffc-loc2-tax">--</span>
                        </div>
                        <div class="sffc-metric">
                            <span class="sffc-metric-label">Cost of Living</span>
                            <span class="sffc-metric-value" id="sffc-loc2-col">--</span>
                        </div>
                        <div class="sffc-metric">
                            <span class="sffc-metric-label">Purchasing Power</span>
                            <span class="sffc-metric-value" id="sffc-loc2-pp">--</span>
                        </div>
                    </div>
                    <div class="sffc-qol-section">
                        <h5>Quality of Life</h5>
                        <div class="sffc-qol-metrics" id="sffc-loc2-qol">
                            <!-- Populated by JS -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom Row: Industry + Trends + Tips -->
        <div class="sffc-salary-bottom-row">
            <!-- Industry Comparison -->
            <div class="sffc-salary-card sffc-salary-industries">
                <h3>By Industry</h3>
                <div class="sffc-industry-chart-container">
                    <canvas id="sffc-industry-salary-chart"></canvas>
                </div>
            </div>

            <!-- Salary Trends -->
            <div class="sffc-salary-card sffc-salary-trends-card">
                <h3>Salary Trends</h3>
                <div class="sffc-trends-outlook" id="sffc-trends-outlook">
                    <span class="sffc-outlook-badge">--</span>
                    <span class="sffc-outlook-industry" id="sffc-trends-industry">--</span>
                </div>
                <div class="sffc-trends-projections" id="sffc-trends-projections">
                    <!-- Populated by JS -->
                </div>
                <p class="sffc-trends-insight" id="sffc-trends-insight"></p>
            </div>

            <!-- Top Quartile Tips -->
            <div class="sffc-salary-card sffc-tips-card">
                <h3>Reach Top Quartile</h3>
                <div class="sffc-tips-list" id="sffc-top-quartile-tips">
                    <!-- Populated by JS -->
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render networking section
     */
    private function render_networking_section($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sffc_interactions';

        // Get networking contacts
        $contacts = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name
             WHERE user_id = %d AND interaction_type = 'networking'
             ORDER BY interaction_date DESC
             LIMIT 50",
            $user_id
        ), ARRAY_A);

        // Get stats
        $total_contacts = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE user_id = %d AND interaction_type = 'networking'",
            $user_id
        ));
        $pending_followups = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name
             WHERE user_id = %d AND interaction_type = 'networking'
             AND follow_up_date <= CURDATE() AND status != 'completed'",
            $user_id
        ));
        $this_month = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name
             WHERE user_id = %d AND interaction_type = 'networking'
             AND MONTH(interaction_date) = MONTH(CURDATE())
             AND YEAR(interaction_date) = YEAR(CURDATE())",
            $user_id
        ));

        ob_start();
        ?>
        <div class="sffc-section-header">
            <h2>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
                Professional Network
            </h2>
            <div class="sffc-section-controls">
                <button class="sffc-btn sffc-btn-primary" id="sffc-add-contact-btn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Add Contact
                </button>
            </div>
        </div>

        <!-- Network Stats -->
        <div class="sffc-network-stats">
            <div class="sffc-network-stat-card">
                <div class="sffc-network-stat-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
                <div class="sffc-network-stat-content">
                    <span class="sffc-network-stat-value"><?php echo intval($total_contacts); ?></span>
                    <span class="sffc-network-stat-label">Total Contacts</span>
                </div>
            </div>
            <div class="sffc-network-stat-card sffc-stat-warning">
                <div class="sffc-network-stat-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                </div>
                <div class="sffc-network-stat-content">
                    <span class="sffc-network-stat-value"><?php echo intval($pending_followups); ?></span>
                    <span class="sffc-network-stat-label">Pending Follow-ups</span>
                </div>
            </div>
            <div class="sffc-network-stat-card sffc-stat-success">
                <div class="sffc-network-stat-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                </div>
                <div class="sffc-network-stat-content">
                    <span class="sffc-network-stat-value"><?php echo intval($this_month); ?></span>
                    <span class="sffc-network-stat-label">This Month</span>
                </div>
            </div>
        </div>

        <!-- Contacts List -->
        <div class="sffc-contacts-container">
            <?php if (empty($contacts)) : ?>
                <div class="sffc-empty-state">
                    <div class="sffc-empty-icon">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                    </div>
                    <h3>Build Your Network</h3>
                    <p>Start adding professional contacts to grow your network and track your interactions.</p>
                    <button class="sffc-btn sffc-btn-primary" id="sffc-add-first-contact">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Add Your First Contact
                    </button>
                </div>
            <?php else : ?>
                <div class="sffc-contacts-list">
                    <?php foreach ($contacts as $contact) : ?>
                        <?php echo $this->render_contact_card($contact, 'networking'); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Add Contact Modal -->
        <?php echo $this->render_add_contact_modal('networking'); ?>

        <?php
        return ob_get_clean();
    }

    /**
     * Render recruiters section
     */
    private function render_recruiters_section($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sffc_interactions';

        // Get recruiter contacts
        $recruiters = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name
             WHERE user_id = %d AND interaction_type = 'recruiter'
             ORDER BY interaction_date DESC
             LIMIT 50",
            $user_id
        ), ARRAY_A);

        // Get stats
        $total_recruiters = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE user_id = %d AND interaction_type = 'recruiter'",
            $user_id
        ));
        $active_conversations = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name
             WHERE user_id = %d AND interaction_type = 'recruiter'
             AND status IN ('pending', 'in_progress')",
            $user_id
        ));
        $successful_intros = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name
             WHERE user_id = %d AND interaction_type = 'recruiter'
             AND status = 'completed'",
            $user_id
        ));

        ob_start();
        ?>
        <div class="sffc-section-header">
            <h2>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
                </svg>
                Express Interest
            </h2>
            <div class="sffc-section-controls">
                <button class="sffc-btn sffc-btn-primary" id="sffc-add-recruiter-btn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Add Recruiter
                </button>
            </div>
        </div>

        <!-- Recruiter Stats -->
        <div class="sffc-network-stats">
            <div class="sffc-network-stat-card">
                <div class="sffc-network-stat-icon sffc-icon-recruiter">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
                    </svg>
                </div>
                <div class="sffc-network-stat-content">
                    <span class="sffc-network-stat-value"><?php echo intval($total_recruiters); ?></span>
                    <span class="sffc-network-stat-label">Total Recruiters</span>
                </div>
            </div>
            <div class="sffc-network-stat-card sffc-stat-info">
                <div class="sffc-network-stat-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                </div>
                <div class="sffc-network-stat-content">
                    <span class="sffc-network-stat-value"><?php echo intval($active_conversations); ?></span>
                    <span class="sffc-network-stat-label">Active Conversations</span>
                </div>
            </div>
            <div class="sffc-network-stat-card sffc-stat-success">
                <div class="sffc-network-stat-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                </div>
                <div class="sffc-network-stat-content">
                    <span class="sffc-network-stat-value"><?php echo intval($successful_intros); ?></span>
                    <span class="sffc-network-stat-label">Successful Express Interest</span>
                </div>
            </div>
        </div>

        <!-- Recruiters List -->
        <div class="sffc-contacts-container">
            <?php if (empty($recruiters)) : ?>
                <div class="sffc-empty-state">
                    <div class="sffc-empty-icon">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
                        </svg>
                    </div>
                    <h3>Track Recruiter Conversations</h3>
                    <p>Add recruiters you've connected with to track your conversations and follow-ups.</p>
                    <button class="sffc-btn sffc-btn-primary" id="sffc-add-first-recruiter">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Add Your First Recruiter
                    </button>
                </div>
            <?php else : ?>
                <div class="sffc-contacts-list">
                    <?php foreach ($recruiters as $recruiter) : ?>
                        <?php echo $this->render_contact_card($recruiter, 'recruiter'); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Add Recruiter Modal -->
        <?php echo $this->render_add_contact_modal('recruiter'); ?>

        <?php
        return ob_get_clean();
    }

    /**
     * Render contact card
     */
    private function render_contact_card($contact, $type = 'networking') {
        $status_classes = array(
            'pending' => 'sffc-status-pending',
            'in_progress' => 'sffc-status-progress',
            'completed' => 'sffc-status-completed',
            'follow_up' => 'sffc-status-followup'
        );

        $status_labels = array(
            'pending' => 'Pending',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'follow_up' => 'Follow Up'
        );

        $status = $contact['status'] ?? 'pending';
        $status_class = $status_classes[$status] ?? 'sffc-status-pending';
        $status_label = $status_labels[$status] ?? 'Pending';

        $contact_name = esc_html($contact['contact_name'] ?? 'Unknown');
        $company = esc_html($contact['company'] ?? '');
        $email = esc_html($contact['contact_email'] ?? '');
        $notes = esc_html($contact['notes'] ?? '');
        $follow_up = $contact['follow_up_date'] ?? '';
        $interaction_date = $contact['interaction_date'] ?? '';

        // Generate initials for avatar
        $name_parts = explode(' ', $contact_name);
        $initials = strtoupper(substr($name_parts[0], 0, 1));
        if (count($name_parts) > 1) {
            $initials .= strtoupper(substr(end($name_parts), 0, 1));
        }

        ob_start();
        ?>
        <div class="sffc-contact-card" data-contact-id="<?php echo intval($contact['id']); ?>" data-type="<?php echo esc_attr($type); ?>">
            <div class="sffc-contact-avatar">
                <span class="sffc-contact-initials"><?php echo $initials; ?></span>
            </div>
            <div class="sffc-contact-info">
                <div class="sffc-contact-header">
                    <h4 class="sffc-contact-name"><?php echo $contact_name; ?></h4>
                    <span class="sffc-contact-status <?php echo $status_class; ?>"><?php echo $status_label; ?></span>
                </div>
                <?php if ($company) : ?>
                    <p class="sffc-contact-company"><?php echo $company; ?></p>
                <?php endif; ?>
                <?php if ($email) : ?>
                    <p class="sffc-contact-email">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                            <polyline points="22,6 12,13 2,6"/>
                        </svg>
                        <?php echo $email; ?>
                    </p>
                <?php endif; ?>
                <?php if ($follow_up && $status !== 'completed') : ?>
                    <p class="sffc-contact-followup <?php echo strtotime($follow_up) <= time() ? 'sffc-overdue' : ''; ?>">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <polyline points="12 6 12 12 16 14"/>
                        </svg>
                        Follow up: <?php echo date('M j, Y', strtotime($follow_up)); ?>
                    </p>
                <?php endif; ?>
            </div>
            <div class="sffc-contact-actions">
                <button class="sffc-contact-action-btn sffc-edit-contact" title="Edit">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                </button>
                <button class="sffc-contact-action-btn sffc-delete-contact" title="Delete">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"/>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                    </svg>
                </button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render add contact modal
     */
    private function render_add_contact_modal($type = 'networking') {
        $title = $type === 'recruiter' ? 'Add Recruiter' : 'Add Contact';
        $modal_id = $type === 'recruiter' ? 'sffc-add-recruiter-modal' : 'sffc-add-contact-modal';

        ob_start();
        ?>
        <div class="sffc-modal" id="<?php echo $modal_id; ?>">
            <div class="sffc-modal-overlay"></div>
            <div class="sffc-modal-content">
                <div class="sffc-modal-header">
                    <h3><?php echo $title; ?></h3>
                    <button class="sffc-modal-close">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"/>
                            <line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                </div>
                <form class="sffc-contact-form" data-type="<?php echo esc_attr($type); ?>">
                    <input type="hidden" name="interaction_type" value="<?php echo esc_attr($type); ?>">

                    <div class="sffc-form-group">
                        <label for="contact_name_<?php echo $type; ?>">Name *</label>
                        <input type="text" id="contact_name_<?php echo $type; ?>" name="contact_name" required placeholder="Full name">
                    </div>

                    <div class="sffc-form-group">
                        <label for="company_<?php echo $type; ?>">Company</label>
                        <input type="text" id="company_<?php echo $type; ?>" name="company" placeholder="Company name">
                    </div>

                    <div class="sffc-form-group">
                        <label for="contact_email_<?php echo $type; ?>">Email</label>
                        <input type="email" id="contact_email_<?php echo $type; ?>" name="contact_email" placeholder="email@example.com">
                    </div>

                    <div class="sffc-form-row">
                        <div class="sffc-form-group">
                            <label for="status_<?php echo $type; ?>">Status</label>
                            <select id="status_<?php echo $type; ?>" name="status">
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="follow_up">Follow Up</option>
                            </select>
                        </div>
                        <div class="sffc-form-group">
                            <label for="follow_up_date_<?php echo $type; ?>">Follow-up Date</label>
                            <input type="date" id="follow_up_date_<?php echo $type; ?>" name="follow_up_date">
                        </div>
                    </div>

                    <div class="sffc-form-group">
                        <label for="notes_<?php echo $type; ?>">Notes</label>
                        <textarea id="notes_<?php echo $type; ?>" name="notes" rows="3" placeholder="Add any notes about this contact..."></textarea>
                    </div>

                    <div class="sffc-modal-actions">
                        <button type="button" class="sffc-btn sffc-btn-outline sffc-modal-cancel">Cancel</button>
                        <button type="submit" class="sffc-btn sffc-btn-primary">Save Contact</button>
                    </div>
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render settings section
     */
    private function render_settings_section() {
        $profile = $this->user_profile;

        // Get preferences from handler
        $preferences = array();
        $theme_settings = array();
        $sections = array();

        if (class_exists('SFFC_Dashboard_Preferences_Handler')) {
            $handler = SFFC_Dashboard_Preferences_Handler::get_instance();
            $preferences = $handler->get_preferences();
            $theme_settings = $handler->get_theme_settings();
            $sections = $handler->get_section_order();
        }

        $dashboard_prefs = $preferences['dashboard'] ?? array();
        $notification_prefs = $preferences['notifications'] ?? array();
        $privacy_prefs = $preferences['privacy'] ?? array();
        $accessibility_prefs = $preferences['accessibility'] ?? array();

        ob_start();
        ?>
        <div class="sffc-section-header">
            <h2>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="3"/>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                </svg>
                Settings & Preferences
            </h2>
            <div class="sffc-settings-actions">
                <button class="sffc-btn sffc-btn-outline" id="sffc-reset-preferences">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 4v6h6"/>
                        <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/>
                    </svg>
                    Reset to Defaults
                </button>
            </div>
        </div>

        <!-- Settings Tabs -->
        <div class="sffc-settings-tabs">
            <button class="sffc-settings-tab active" data-tab="dashboard">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"/>
                    <rect x="14" y="3" width="7" height="7"/>
                    <rect x="14" y="14" width="7" height="7"/>
                    <rect x="3" y="14" width="7" height="7"/>
                </svg>
                Dashboard
            </button>
            <button class="sffc-settings-tab" data-tab="notifications">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                </svg>
                Notifications
            </button>
            <button class="sffc-settings-tab" data-tab="privacy">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
                Privacy
            </button>
            <button class="sffc-settings-tab" data-tab="profile">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
                Profile
            </button>
        </div>

        <!-- Dashboard Settings Tab -->
        <div class="sffc-settings-content sffc-tab-content active" data-tab="dashboard">
            <div class="sffc-settings-grid">
                <!-- Theme Settings -->
                <div class="sffc-settings-card">
                    <h3>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="5"/>
                            <line x1="12" y1="1" x2="12" y2="3"/>
                            <line x1="12" y1="21" x2="12" y2="23"/>
                            <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>
                            <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
                            <line x1="1" y1="12" x2="3" y2="12"/>
                            <line x1="21" y1="12" x2="23" y2="12"/>
                            <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/>
                            <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                        </svg>
                        Theme & Appearance
                    </h3>
                    <div class="sffc-theme-selector">
                        <label class="sffc-theme-option <?php echo ($dashboard_prefs['theme'] ?? 'light') === 'light' ? 'active' : ''; ?>" data-theme="light">
                            <input type="radio" name="theme" value="light" <?php checked(($dashboard_prefs['theme'] ?? 'light'), 'light'); ?>>
                            <div class="sffc-theme-preview sffc-theme-light">
                                <div class="sffc-theme-preview-header"></div>
                                <div class="sffc-theme-preview-content">
                                    <div class="sffc-theme-preview-card"></div>
                                    <div class="sffc-theme-preview-card"></div>
                                </div>
                            </div>
                            <span>Light</span>
                        </label>
                        <label class="sffc-theme-option <?php echo ($dashboard_prefs['theme'] ?? 'light') === 'dark' ? 'active' : ''; ?>" data-theme="dark">
                            <input type="radio" name="theme" value="dark" <?php checked(($dashboard_prefs['theme'] ?? 'light'), 'dark'); ?>>
                            <div class="sffc-theme-preview sffc-theme-dark">
                                <div class="sffc-theme-preview-header"></div>
                                <div class="sffc-theme-preview-content">
                                    <div class="sffc-theme-preview-card"></div>
                                    <div class="sffc-theme-preview-card"></div>
                                </div>
                            </div>
                            <span>Dark</span>
                        </label>
                        <label class="sffc-theme-option <?php echo ($dashboard_prefs['theme'] ?? 'light') === 'auto' ? 'active' : ''; ?>" data-theme="auto">
                            <input type="radio" name="theme" value="auto" <?php checked(($dashboard_prefs['theme'] ?? 'light'), 'auto'); ?>>
                            <div class="sffc-theme-preview sffc-theme-auto">
                                <div class="sffc-theme-preview-header"></div>
                                <div class="sffc-theme-preview-content">
                                    <div class="sffc-theme-preview-card"></div>
                                    <div class="sffc-theme-preview-card"></div>
                                </div>
                            </div>
                            <span>System</span>
                        </label>
                    </div>

                    <div class="sffc-accent-colors">
                        <label>Accent Color</label>
                        <div class="sffc-color-options">
                            <?php
                            $accent_colors = array(
                                '#6366f1' => 'Indigo',
                                '#8b5cf6' => 'Violet',
                                '#06b6d4' => 'Cyan',
                                '#10b981' => 'Emerald',
                                '#f59e0b' => 'Amber',
                                '#ef4444' => 'Red',
                                '#ec4899' => 'Pink',
                                '#3b82f6' => 'Blue',
                            );
                            foreach ($accent_colors as $color => $name):
                            ?>
                            <button class="sffc-color-btn <?php echo ($dashboard_prefs['accent_color'] ?? '#6366f1') === $color ? 'active' : ''; ?>"
                                    data-color="<?php echo esc_attr($color); ?>"
                                    style="background-color: <?php echo esc_attr($color); ?>;"
                                    title="<?php echo esc_attr($name); ?>">
                                <?php if (($dashboard_prefs['accent_color'] ?? '#6366f1') === $color): ?>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                                <?php endif; ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Section Visibility -->
                <div class="sffc-settings-card">
                    <h3>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                        Section Visibility
                    </h3>
                    <p class="sffc-settings-description">Choose which sections appear on your dashboard</p>
                    <div class="sffc-section-toggles" id="sffc-section-toggles">
                        <?php foreach ($sections as $section_id => $section): ?>
                        <label class="sffc-toggle-option">
                            <input type="checkbox"
                                   name="section_<?php echo esc_attr($section_id); ?>"
                                   data-section="<?php echo esc_attr($section_id); ?>"
                                   <?php checked($section['visible'] ?? true); ?>>
                            <span class="sffc-toggle-slider"></span>
                            <span class="sffc-toggle-label"><?php echo esc_html($section['name']); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Section Order (Drag & Drop) -->
                <div class="sffc-settings-card sffc-full-width">
                    <h3>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="8" y1="6" x2="21" y2="6"/>
                            <line x1="8" y1="12" x2="21" y2="12"/>
                            <line x1="8" y1="18" x2="21" y2="18"/>
                            <line x1="3" y1="6" x2="3.01" y2="6"/>
                            <line x1="3" y1="12" x2="3.01" y2="12"/>
                            <line x1="3" y1="18" x2="3.01" y2="18"/>
                        </svg>
                        Section Order
                    </h3>
                    <p class="sffc-settings-description">Drag sections to reorder them on your dashboard</p>
                    <ul class="sffc-sortable-sections" id="sffc-sortable-sections">
                        <?php foreach ($sections as $section_id => $section): ?>
                        <li class="sffc-sortable-item" data-section="<?php echo esc_attr($section_id); ?>">
                            <span class="sffc-drag-handle">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="9" cy="5" r="1"/>
                                    <circle cx="9" cy="12" r="1"/>
                                    <circle cx="9" cy="19" r="1"/>
                                    <circle cx="15" cy="5" r="1"/>
                                    <circle cx="15" cy="12" r="1"/>
                                    <circle cx="15" cy="19" r="1"/>
                                </svg>
                            </span>
                            <span class="sffc-section-name"><?php echo esc_html($section['name']); ?></span>
                            <span class="sffc-visibility-indicator <?php echo !empty($section['visible']) ? 'visible' : 'hidden'; ?>">
                                <?php if (!empty($section['visible'])): ?>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                                <?php else: ?>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                                    <line x1="1" y1="1" x2="23" y2="23"/>
                                </svg>
                                <?php endif; ?>
                            </span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Display Options -->
                <div class="sffc-settings-card">
                    <h3>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                            <line x1="8" y1="21" x2="16" y2="21"/>
                            <line x1="12" y1="17" x2="12" y2="21"/>
                        </svg>
                        Display Options
                    </h3>
                    <div class="sffc-display-options">
                        <label class="sffc-toggle-option">
                            <input type="checkbox" name="compact_mode" <?php checked($dashboard_prefs['compact_mode'] ?? false); ?>>
                            <span class="sffc-toggle-slider"></span>
                            <span class="sffc-toggle-label">Compact mode</span>
                        </label>
                        <label class="sffc-toggle-option">
                            <input type="checkbox" name="animation_enabled" <?php checked($dashboard_prefs['animation_enabled'] ?? true); ?>>
                            <span class="sffc-toggle-slider"></span>
                            <span class="sffc-toggle-label">Enable animations</span>
                        </label>
                        <label class="sffc-toggle-option">
                            <input type="checkbox" name="auto_refresh" <?php checked($dashboard_prefs['auto_refresh'] ?? true); ?>>
                            <span class="sffc-toggle-slider"></span>
                            <span class="sffc-toggle-label">Auto-refresh data</span>
                        </label>
                        <label class="sffc-toggle-option">
                            <input type="checkbox" name="show_welcome" <?php checked($dashboard_prefs['show_welcome'] ?? true); ?>>
                            <span class="sffc-toggle-slider"></span>
                            <span class="sffc-toggle-label">Show welcome message</span>
                        </label>
                    </div>

                    <div class="sffc-chart-preference">
                        <label>Default Chart Type</label>
                        <select name="default_chart_type" class="sffc-select">
                            <option value="bar" <?php selected($dashboard_prefs['default_chart_type'] ?? 'bar', 'bar'); ?>>Bar Chart</option>
                            <option value="line" <?php selected($dashboard_prefs['default_chart_type'] ?? 'bar', 'line'); ?>>Line Chart</option>
                            <option value="doughnut" <?php selected($dashboard_prefs['default_chart_type'] ?? 'bar', 'doughnut'); ?>>Doughnut Chart</option>
                            <option value="radar" <?php selected($dashboard_prefs['default_chart_type'] ?? 'bar', 'radar'); ?>>Radar Chart</option>
                        </select>
                    </div>
                </div>

                <!-- Accessibility -->
                <div class="sffc-settings-card">
                    <h3>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M12 8v4"/>
                            <path d="M12 16h.01"/>
                        </svg>
                        Accessibility
                    </h3>
                    <div class="sffc-accessibility-options">
                        <label class="sffc-toggle-option">
                            <input type="checkbox" name="high_contrast" <?php checked($accessibility_prefs['high_contrast'] ?? false); ?>>
                            <span class="sffc-toggle-slider"></span>
                            <span class="sffc-toggle-label">High contrast mode</span>
                        </label>
                        <label class="sffc-toggle-option">
                            <input type="checkbox" name="large_text" <?php checked($accessibility_prefs['large_text'] ?? false); ?>>
                            <span class="sffc-toggle-slider"></span>
                            <span class="sffc-toggle-label">Larger text</span>
                        </label>
                        <label class="sffc-toggle-option">
                            <input type="checkbox" name="reduce_motion" <?php checked($accessibility_prefs['reduce_motion'] ?? false); ?>>
                            <span class="sffc-toggle-slider"></span>
                            <span class="sffc-toggle-label">Reduce motion</span>
                        </label>
                        <label class="sffc-toggle-option">
                            <input type="checkbox" name="screen_reader_hints" <?php checked($accessibility_prefs['screen_reader_hints'] ?? true); ?>>
                            <span class="sffc-toggle-slider"></span>
                            <span class="sffc-toggle-label">Screen reader hints</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notifications Tab -->
        <div class="sffc-settings-content sffc-tab-content" data-tab="notifications">
            <div class="sffc-settings-grid">
                <!-- Email Notifications -->
                <div class="sffc-settings-card">
                    <h3>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                            <polyline points="22,6 12,13 2,6"/>
                        </svg>
                        Email Preferences
                    </h3>
                    <label class="sffc-toggle-option sffc-toggle-main">
                        <input type="checkbox" name="email_enabled" <?php checked($notification_prefs['email_enabled'] ?? true); ?>>
                        <span class="sffc-toggle-slider"></span>
                        <span class="sffc-toggle-label">Enable email notifications</span>
                    </label>

                    <div class="sffc-digest-settings <?php echo empty($notification_prefs['email_enabled']) ? 'disabled' : ''; ?>">
                        <label>Email Digest Frequency</label>
                        <div class="sffc-radio-group">
                            <label class="sffc-radio-option">
                                <input type="radio" name="email_digest" value="daily" <?php checked($notification_prefs['email_digest'] ?? 'weekly', 'daily'); ?>>
                                <span class="sffc-radio-custom"></span>
                                <span>Daily</span>
                            </label>
                            <label class="sffc-radio-option">
                                <input type="radio" name="email_digest" value="weekly" <?php checked($notification_prefs['email_digest'] ?? 'weekly', 'weekly'); ?>>
                                <span class="sffc-radio-custom"></span>
                                <span>Weekly</span>
                            </label>
                            <label class="sffc-radio-option">
                                <input type="radio" name="email_digest" value="monthly" <?php checked($notification_prefs['email_digest'] ?? 'weekly', 'monthly'); ?>>
                                <span class="sffc-radio-custom"></span>
                                <span>Monthly</span>
                            </label>
                            <label class="sffc-radio-option">
                                <input type="radio" name="email_digest" value="never" <?php checked($notification_prefs['email_digest'] ?? 'weekly', 'never'); ?>>
                                <span class="sffc-radio-custom"></span>
                                <span>Never</span>
                            </label>
                        </div>

                        <div class="sffc-digest-time">
                            <label>Preferred Day</label>
                            <select name="digest_day" class="sffc-select">
                                <?php
                                $days = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');
                                foreach ($days as $day):
                                ?>
                                <option value="<?php echo $day; ?>" <?php selected($notification_prefs['digest_day'] ?? 'monday', $day); ?>>
                                    <?php echo ucfirst($day); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>

                            <label>Preferred Time</label>
                            <select name="digest_time" class="sffc-select">
                                <?php for ($h = 0; $h < 24; $h++): ?>
                                <option value="<?php echo sprintf('%02d:00', $h); ?>" <?php selected($notification_prefs['digest_time'] ?? '09:00', sprintf('%02d:00', $h)); ?>>
                                    <?php echo sprintf('%02d:00', $h); ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Alert Types -->
                <div class="sffc-settings-card">
                    <h3>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                        </svg>
                        Alert Types
                    </h3>
                    <p class="sffc-settings-description">Choose which alerts you want to receive</p>
                    <div class="sffc-alert-options">
                        <?php
                        $alerts = $notification_prefs['alerts'] ?? array();
                        $alert_labels = array(
                            'new_jobs' => 'New job matches',
                            'salary_changes' => 'Salary trend changes',
                            'skill_updates' => 'Skill demand updates',
                            'market_alerts' => 'Market movement alerts',
                            'news_digest' => 'Industry news digest',
                            'learning_reminders' => 'Learning path reminders',
                            'certification_expiry' => 'Certification expiry warnings',
                        );
                        foreach ($alert_labels as $key => $label):
                        ?>
                        <label class="sffc-toggle-option">
                            <input type="checkbox" name="alert_<?php echo esc_attr($key); ?>" <?php checked($alerts[$key] ?? false); ?>>
                            <span class="sffc-toggle-slider"></span>
                            <span class="sffc-toggle-label"><?php echo esc_html($label); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Quiet Hours -->
                <div class="sffc-settings-card">
                    <h3>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                        </svg>
                        Quiet Hours
                    </h3>
                    <p class="sffc-settings-description">Pause notifications during specific hours</p>
                    <?php $quiet = $notification_prefs['quiet_hours'] ?? array(); ?>
                    <label class="sffc-toggle-option sffc-toggle-main">
                        <input type="checkbox" name="quiet_hours_enabled" <?php checked($quiet['enabled'] ?? false); ?>>
                        <span class="sffc-toggle-slider"></span>
                        <span class="sffc-toggle-label">Enable quiet hours</span>
                    </label>

                    <div class="sffc-quiet-hours-config <?php echo empty($quiet['enabled']) ? 'disabled' : ''; ?>">
                        <div class="sffc-time-range">
                            <div class="sffc-time-input">
                                <label>Start</label>
                                <select name="quiet_start" class="sffc-select">
                                    <?php for ($h = 0; $h < 24; $h++): ?>
                                    <option value="<?php echo sprintf('%02d:00', $h); ?>" <?php selected($quiet['start'] ?? '22:00', sprintf('%02d:00', $h)); ?>>
                                        <?php echo sprintf('%02d:00', $h); ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <span class="sffc-time-separator">to</span>
                            <div class="sffc-time-input">
                                <label>End</label>
                                <select name="quiet_end" class="sffc-select">
                                    <?php for ($h = 0; $h < 24; $h++): ?>
                                    <option value="<?php echo sprintf('%02d:00', $h); ?>" <?php selected($quiet['end'] ?? '08:00', sprintf('%02d:00', $h)); ?>>
                                        <?php echo sprintf('%02d:00', $h); ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>

                        <div class="sffc-timezone-select">
                            <label>Timezone</label>
                            <select name="quiet_timezone" class="sffc-select">
                                <?php
                                $timezones = array(
                                    'UTC' => 'UTC',
                                    'America/New_York' => 'Eastern Time (ET)',
                                    'America/Chicago' => 'Central Time (CT)',
                                    'America/Denver' => 'Mountain Time (MT)',
                                    'America/Los_Angeles' => 'Pacific Time (PT)',
                                    'Europe/London' => 'London (GMT/BST)',
                                    'Europe/Paris' => 'Paris (CET)',
                                    'Asia/Dubai' => 'Dubai (GST)',
                                    'Asia/Singapore' => 'Singapore (SGT)',
                                    'Asia/Hong_Kong' => 'Hong Kong (HKT)',
                                    'Asia/Tokyo' => 'Tokyo (JST)',
                                    'Australia/Sydney' => 'Sydney (AEST)',
                                );
                                foreach ($timezones as $tz => $label):
                                ?>
                                <option value="<?php echo esc_attr($tz); ?>" <?php selected($quiet['timezone'] ?? 'UTC', $tz); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Privacy Tab -->
        <div class="sffc-settings-content sffc-tab-content" data-tab="privacy">
            <div class="sffc-settings-grid">
                <!-- Profile Visibility -->
                <div class="sffc-settings-card">
                    <h3>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                        Profile Visibility
                    </h3>
                    <p class="sffc-settings-description">Control who can see your profile</p>
                    <div class="sffc-radio-group sffc-visibility-options">
                        <label class="sffc-radio-option sffc-radio-card">
                            <input type="radio" name="profile_visibility" value="public" <?php checked($privacy_prefs['profile_visibility'] ?? 'private', 'public'); ?>>
                            <span class="sffc-radio-custom"></span>
                            <div class="sffc-radio-content">
                                <span class="sffc-radio-title">Public</span>
                                <span class="sffc-radio-desc">Anyone can view your profile</span>
                            </div>
                        </label>
                        <label class="sffc-radio-option sffc-radio-card">
                            <input type="radio" name="profile_visibility" value="connections" <?php checked($privacy_prefs['profile_visibility'] ?? 'private', 'connections'); ?>>
                            <span class="sffc-radio-custom"></span>
                            <div class="sffc-radio-content">
                                <span class="sffc-radio-title">Connections Only</span>
                                <span class="sffc-radio-desc">Only your connections can view</span>
                            </div>
                        </label>
                        <label class="sffc-radio-option sffc-radio-card">
                            <input type="radio" name="profile_visibility" value="private" <?php checked($privacy_prefs['profile_visibility'] ?? 'private', 'private'); ?>>
                            <span class="sffc-radio-custom"></span>
                            <div class="sffc-radio-content">
                                <span class="sffc-radio-title">Private</span>
                                <span class="sffc-radio-desc">Only you can view your profile</span>
                            </div>
                        </label>
                    </div>

                    <div class="sffc-privacy-toggles">
                        <label class="sffc-toggle-option">
                            <input type="checkbox" name="show_in_directory" <?php checked($privacy_prefs['show_in_directory'] ?? false); ?>>
                            <span class="sffc-toggle-slider"></span>
                            <span class="sffc-toggle-label">Show in member directory</span>
                        </label>
                        <label class="sffc-toggle-option">
                            <input type="checkbox" name="allow_recruiter_contact" <?php checked($privacy_prefs['allow_recruiter_contact'] ?? false); ?>>
                            <span class="sffc-toggle-slider"></span>
                            <span class="sffc-toggle-label">Allow recruiters to contact me</span>
                        </label>
                    </div>
                </div>

                <!-- Data & Analytics -->
                <div class="sffc-settings-card">
                    <h3>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21.21 15.89A10 10 0 1 1 8 2.83"/>
                            <path d="M22 12A10 10 0 0 0 12 2v10z"/>
                        </svg>
                        Data & Analytics
                    </h3>
                    <p class="sffc-settings-description">Control how your data is used</p>
                    <div class="sffc-data-toggles">
                        <label class="sffc-toggle-option">
                            <input type="checkbox" name="share_anonymous_data" <?php checked($privacy_prefs['share_anonymous_data'] ?? true); ?>>
                            <span class="sffc-toggle-slider"></span>
                            <div class="sffc-toggle-details">
                                <span class="sffc-toggle-label">Share anonymous usage data</span>
                                <span class="sffc-toggle-hint">Helps improve the platform</span>
                            </div>
                        </label>
                        <label class="sffc-toggle-option">
                            <input type="checkbox" name="activity_tracking" <?php checked($privacy_prefs['activity_tracking'] ?? true); ?>>
                            <span class="sffc-toggle-slider"></span>
                            <div class="sffc-toggle-details">
                                <span class="sffc-toggle-label">Activity tracking</span>
                                <span class="sffc-toggle-hint">Track your dashboard usage</span>
                            </div>
                        </label>
                        <label class="sffc-toggle-option">
                            <input type="checkbox" name="personalized_recommendations" <?php checked($privacy_prefs['personalized_recommendations'] ?? true); ?>>
                            <span class="sffc-toggle-slider"></span>
                            <div class="sffc-toggle-details">
                                <span class="sffc-toggle-label">Personalized recommendations</span>
                                <span class="sffc-toggle-hint">Get tailored job and learning suggestions</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Data Export & Deletion -->
                <div class="sffc-settings-card sffc-data-actions">
                    <h3>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="7 10 12 15 17 10"/>
                            <line x1="12" y1="15" x2="12" y2="3"/>
                        </svg>
                        Your Data
                    </h3>
                    <p class="sffc-settings-description">Export or delete your personal data</p>

                    <div class="sffc-data-action-buttons">
                        <button class="sffc-btn sffc-btn-outline" id="sffc-export-data">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                <polyline points="7 10 12 15 17 10"/>
                                <line x1="12" y1="15" x2="12" y2="3"/>
                            </svg>
                            Export My Data
                        </button>
                        <button class="sffc-btn sffc-btn-danger" id="sffc-delete-data">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="3 6 5 6 21 6"/>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                <line x1="10" y1="11" x2="10" y2="17"/>
                                <line x1="14" y1="11" x2="14" y2="17"/>
                            </svg>
                            Delete All My Data
                        </button>
                    </div>

                    <div class="sffc-data-warning">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="8" x2="12" y2="12"/>
                            <line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                        <span>Deleting your data is permanent and cannot be undone.</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profile Tab -->
        <div class="sffc-settings-content sffc-tab-content" data-tab="profile">
            <div class="sffc-settings-grid">
                <!-- Profile Summary -->
                <div class="sffc-settings-card sffc-profile-summary">
                    <h3>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                        Profile Summary
                    </h3>
                    <button class="sffc-btn sffc-btn-sm sffc-btn-outline" id="sffc-edit-profile">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                        </svg>
                        Edit
                    </button>
                    <div class="sffc-profile-fields">
                        <div class="sffc-field-row">
                            <span class="sffc-field-label">Current Role</span>
                            <span class="sffc-field-value" data-field="current_role"><?php echo esc_html($profile['current_role'] ?? 'Not set'); ?></span>
                        </div>
                        <div class="sffc-field-row">
                            <span class="sffc-field-label">Experience</span>
                            <span class="sffc-field-value" data-field="years_experience"><?php echo esc_html($profile['years_experience'] ?? 'Not set'); ?></span>
                        </div>
                        <div class="sffc-field-row">
                            <span class="sffc-field-label">Target Industries</span>
                            <span class="sffc-field-value" data-field="preferred_industries"><?php echo esc_html($this->format_array_field($profile['preferred_industries'] ?? array())); ?></span>
                        </div>
                        <div class="sffc-field-row">
                            <span class="sffc-field-label">Preferred Locations</span>
                            <span class="sffc-field-value" data-field="preferred_locations"><?php echo esc_html($this->format_array_field($profile['preferred_locations'] ?? array())); ?></span>
                        </div>
                        <div class="sffc-field-row">
                            <span class="sffc-field-label">Skills</span>
                            <span class="sffc-field-value sffc-field-skills" data-field="skills"><?php echo esc_html($this->format_array_field($profile['skills'] ?? array())); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Membership Info -->
                <div class="sffc-settings-card sffc-membership-info">
                    <h3>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                            <path d="M2 17l10 5 10-5"/>
                            <path d="M2 12l10 5 10-5"/>
                        </svg>
                        Membership
                    </h3>
                    <?php echo $this->render_membership_details(); ?>
                </div>
            </div>
        </div>

        <!-- Delete Data Confirmation Modal -->
        <div class="sffc-modal" id="sffc-delete-data-modal">
            <div class="sffc-modal-overlay"></div>
            <div class="sffc-modal-content sffc-modal-danger">
                <div class="sffc-modal-header">
                    <h3>
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                            <line x1="12" y1="9" x2="12" y2="13"/>
                            <line x1="12" y1="17" x2="12.01" y2="17"/>
                        </svg>
                        Delete All Data
                    </h3>
                    <button class="sffc-modal-close">&times;</button>
                </div>
                <div class="sffc-modal-body">
                    <p>This action will permanently delete:</p>
                    <ul>
                        <li>Your career profile</li>
                        <li>Dashboard preferences</li>
                        <li>Saved jobs and articles</li>
                        <li>Skill assessments</li>
                        <li>Learning progress</li>
                        <li>All usage data</li>
                    </ul>
                    <p class="sffc-warning-text">This action cannot be undone.</p>
                    <div class="sffc-confirm-input">
                        <label>Type <strong>DELETE</strong> to confirm:</label>
                        <input type="text" id="sffc-delete-confirm-input" placeholder="Type DELETE">
                    </div>
                </div>
                <div class="sffc-modal-footer">
                    <button class="sffc-btn sffc-btn-outline sffc-modal-cancel">Cancel</button>
                    <button class="sffc-btn sffc-btn-danger" id="sffc-confirm-delete" disabled>Delete Everything</button>
                </div>
            </div>
        </div>

        <!-- Save Settings Button (floating) -->
        <div class="sffc-settings-save-bar" id="sffc-settings-save-bar" style="display: none;">
            <span class="sffc-save-message">You have unsaved changes</span>
            <div class="sffc-save-actions">
                <button class="sffc-btn sffc-btn-outline" id="sffc-discard-changes">Discard</button>
                <button class="sffc-btn sffc-btn-primary" id="sffc-save-settings">Save Changes</button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render membership details
     */
    private function render_membership_details() {
        $membership = $this->get_membership_data(get_current_user_id());
        $level = $membership['level'] ?? 'free';
        $expires = $membership['expires'] ?? null;

        $plans = array(
            'free' => array(
                'name' => 'Free',
                'features' => array('20 job matches', 'Basic trends', '30-day history'),
                'upgrade_text' => 'Upgrade for full access'
            ),
            'professional' => array(
                'name' => 'Professional',
                'features' => array('100 job matches', 'Full trends', 'Skills analysis', 'Salary comparison'),
                'upgrade_text' => 'Upgrade to Executive'
            ),
            'executive' => array(
                'name' => 'Executive',
                'features' => array('Unlimited matches', 'AI insights', 'Priority support', 'API access'),
                'upgrade_text' => null
            )
        );

        $plan = $plans[$level] ?? $plans['free'];

        ob_start();
        ?>
        <div class="sffc-membership-current">
            <span class="sffc-membership-badge sffc-badge-<?php echo esc_attr($level); ?>">
                <?php echo esc_html($plan['name']); ?>
            </span>
            <?php if ($expires): ?>
            <span class="sffc-membership-expires">
                Renews: <?php echo esc_html(date('M j, Y', strtotime($expires))); ?>
            </span>
            <?php endif; ?>
        </div>

        <ul class="sffc-membership-features">
            <?php foreach ($plan['features'] as $feature): ?>
            <li>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                </svg>
                <?php echo esc_html($feature); ?>
            </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($plan['upgrade_text']): ?>
        <a href="/pricing/" class="sffc-btn sffc-btn-upgrade">
            <?php echo esc_html($plan['upgrade_text']); ?>
        </a>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }

    /**
     * Get membership badge HTML with usage meters and upgrade option
     */
    private function get_membership_badge($membership) {
        $level = $membership['level'] ?? 'free';
        $labels = array(
            'free' => 'Free',
            'professional' => 'Professional',
            'executive' => 'Executive'
        );
        $icons = array(
            'free' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>',
            'professional' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>',
            'executive' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2z"/></svg>',
        );

        $label = $labels[$level] ?? 'Free';
        $icon = $icons[$level] ?? $icons['free'];

        // Get usage data if membership handler available
        $usage_html = '';
        $upgrade_html = '';

        if (class_exists('SFFC_Dashboard_Membership_Handler')) {
            $handler = SFFC_Dashboard_Membership_Handler::get_instance();
            $usage = $handler->get_usage_stats();

            // Show usage meter for free/professional
            if ($level !== 'executive' && !empty($usage)) {
                $job_matches = $usage['job_matches'] ?? array('percentage' => 0, 'remaining' => 0, 'unlimited' => false);
                if (!$job_matches['unlimited']) {
                    $usage_html = sprintf(
                        '<div class="sffc-usage-meter" title="%s remaining">
                            <div class="sffc-usage-bar">
                                <div class="sffc-usage-fill" style="width: %d%%"></div>
                            </div>
                            <span class="sffc-usage-text">%d/%d</span>
                        </div>',
                        esc_attr($job_matches['remaining'] . ' job matches'),
                        esc_attr($job_matches['percentage']),
                        esc_attr($job_matches['current'] ?? 0),
                        esc_attr($job_matches['limit'] ?? 0)
                    );
                }
            }

            // Upgrade button for non-executive
            if ($level !== 'executive') {
                $next_tier = $handler->get_next_tier($level);
                if ($next_tier) {
                    $upgrade_html = sprintf(
                        '<button class="sffc-upgrade-btn" id="sffc-upgrade-trigger" data-tier="%s" title="Upgrade to %s">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 19V5M5 12l7-7 7 7"/>
                            </svg>
                            Upgrade
                        </button>',
                        esc_attr($next_tier['key']),
                        esc_attr($next_tier['name'])
                    );
                }
            }
        }

        ob_start();
        ?>
        <div class="sffc-membership-widget">
            <div class="sffc-membership-badge sffc-badge-<?php echo esc_attr($level); ?>" id="sffc-membership-badge" title="Click for plan details" tabindex="0" role="button" aria-label="View membership details">
                <?php echo $icon; ?>
                <span><?php echo esc_html($label); ?></span>
            </div>
            <?php echo $usage_html; ?>
            <?php echo $upgrade_html; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Calculate profile completion percentage
     */
    private function calculate_profile_completion() {
        if (empty($this->user_profile)) {
            return 0;
        }

        $core_fields = array('full_name', 'years_experience', 'current_role', 'preferred_industries', 'preferred_locations', 'work_preference', 'skills');
        $optional_fields = array('company_size', 'salary_expectations', 'career_priorities', 'ideal_role', 'education_level', 'target_seniority', 'certifications', 'languages_spoken', 'availability');

        $core_weight = 70;
        $optional_weight = 30;

        $core_filled = 0;
        foreach ($core_fields as $field) {
            if (!empty($this->user_profile[$field])) {
                $core_filled++;
            }
        }

        $optional_filled = 0;
        foreach ($optional_fields as $field) {
            if (!empty($this->user_profile[$field])) {
                $optional_filled++;
            }
        }

        $core_score = count($core_fields) > 0 ? ($core_filled / count($core_fields)) * $core_weight : 0;
        $optional_score = count($optional_fields) > 0 ? ($optional_filled / count($optional_fields)) * $optional_weight : 0;

        return round($core_score + $optional_score);
    }

    /**
     * Get user profile data
     */
    private function get_user_profile($user_id) {
        global $wpdb;

        $profile_table = $wpdb->prefix . 'sffc_user_profiles';
        $skills_table = $wpdb->prefix . 'sffc_user_skills';

        $profile = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $profile_table WHERE user_id = %d",
            $user_id
        ), ARRAY_A);

        if (!$profile) {
            return array();
        }

        // Decode JSON fields
        $json_fields = array('preferred_industries', 'preferred_locations', 'company_size', 'career_priorities', 'certifications', 'languages_spoken');
        foreach ($json_fields as $field) {
            if (!empty($profile[$field]) && is_string($profile[$field])) {
                $decoded = json_decode($profile[$field], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $profile[$field] = $decoded;
                }
            }
        }

        // Get skills
        $skills = $wpdb->get_col($wpdb->prepare(
            "SELECT skill_name FROM $skills_table WHERE user_id = %d",
            $user_id
        ));

        $profile['skills'] = $skills;

        return $profile;
    }

    /**
     * Get dashboard preferences
     */
    private function get_dashboard_preferences($user_id) {
        $defaults = array(
            'notify_job_matches' => true,
            'notify_market_updates' => true,
            'notify_salary_changes' => false,
            'notify_weekly_digest' => true,
            'dashboard_layout' => 'default',
            'default_chart_range' => '6m',
        );

        $prefs = get_user_meta($user_id, 'sffc_dashboard_preferences', true);

        if (!is_array($prefs)) {
            $prefs = array();
        }

        return array_merge($defaults, $prefs);
    }

    /**
     * Get membership data
     */
    private function get_membership_data($user_id) {
        $level = 'free';
        $expires = null;

        // Check MemberPress if available
        if (function_exists('mepr_get_current_user_subscription_status')) {
            // MemberPress integration
            $status = mepr_get_current_user_subscription_status();
            if ($status === 'active') {
                $level = $this->get_memberpress_level($user_id);
            }
        }

        // Check user meta as fallback
        $meta_level = get_user_meta($user_id, 'sffc_membership_level', true);
        if (!empty($meta_level)) {
            $level = $meta_level;
        }

        $meta_expires = get_user_meta($user_id, 'sffc_subscription_expires', true);
        if (!empty($meta_expires)) {
            $expires = $meta_expires;
        }

        return array(
            'level' => $level,
            'expires' => $expires,
            'status' => $level !== 'free' ? 'active' : 'none'
        );
    }

    /**
     * Get MemberPress membership level
     */
    private function get_memberpress_level($user_id) {
        if (!class_exists('MeprUser')) {
            return 'free';
        }

        $mepr_user = new MeprUser($user_id);
        $active_subs = $mepr_user->active_product_subscriptions();

        if (empty($active_subs)) {
            return 'free';
        }

        // Check product IDs against our tiers
        $executive_products = get_option('sffc_executive_product_ids', array());
        $professional_products = get_option('sffc_professional_product_ids', array());

        foreach ($active_subs as $product_id) {
            if (in_array($product_id, (array)$executive_products)) {
                return 'executive';
            }
            if (in_array($product_id, (array)$professional_products)) {
                return 'professional';
            }
        }

        return 'free';
    }

    /**
     * Get membership level for JS
     */
    private function get_membership_level() {
        if (!is_user_logged_in()) {
            return 'guest';
        }
        $data = $this->get_membership_data(get_current_user_id());
        return $data['level'];
    }

    /**
     * Format array field for display
     */
    private function format_array_field($value) {
        if (empty($value)) {
            return 'Not set';
        }

        if (is_array($value)) {
            return implode(', ', array_slice($value, 0, 3)) . (count($value) > 3 ? '...' : '');
        }

        return $value;
    }

    // =========================================
    // AJAX Handlers
    // =========================================

    /**
     * AJAX: Get dashboard stats
     */
    public function ajax_get_stats() {
        check_ajax_referer('sffc_dashboard_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not authenticated'));
        }

        $user_id = get_current_user_id();
        $data_manager = $this->get_data_manager();

        // Get overview stats from data manager (real database values)
        $overview_stats = $data_manager->get_overview_stats($user_id);

        // Get stats from analytics engine or calculate
        $stats = array(
            'match_score' => $this->calculate_match_score($user_id),
            'skills_demand' => $this->calculate_skills_demand($user_id),
            'market_position' => $this->calculate_market_position($user_id),
            'opportunities_count' => $this->count_matching_opportunities($user_id),
            'trending_skills_count' => $this->count_trending_skills($user_id),
            'percentile' => $this->calculate_percentile($user_id),
            // KPI values from database
            'total_applications' => isset($overview_stats['total_applications']) ? $overview_stats['total_applications'] : 0,
            'high_matches' => isset($overview_stats['high_matches']) ? $overview_stats['high_matches'] : 0,
            'networking_intros' => isset($overview_stats['networking_intros']) ? $overview_stats['networking_intros'] : 0,
            'recruiter_intros' => isset($overview_stats['recruiter_intros']) ? $overview_stats['recruiter_intros'] : 0,
        );

        // Add industry distribution data
        $industry_raw = $data_manager->get_industry_distribution($user_id);
        $industry_total = array_sum($industry_raw);
        $industry_data = array('labels' => array(), 'values' => array());

        if ($industry_total > 0) {
            foreach ($industry_raw as $industry => $count) {
                $industry_data['labels'][] = $industry;
                $industry_data['values'][] = round(($count / $industry_total) * 100);
            }
        } else {
            // Default data when no applications
            $industry_data = array(
                'labels' => array('No Data Yet'),
                'values' => array(100)
            );
        }
        $stats['industry_data'] = $industry_data;

        // Add seniority distribution data
        $seniority_raw = $data_manager->get_seniority_distribution($user_id);
        $seniority_total = array_sum($seniority_raw);
        $seniority_data = array('labels' => array(), 'values' => array());

        if ($seniority_total > 0) {
            foreach ($seniority_raw as $level => $count) {
                if ($count > 0) {
                    $seniority_data['labels'][] = $level;
                    $seniority_data['values'][] = $count;
                }
            }
        } else {
            // Default data when no applications
            $seniority_data = array(
                'labels' => array('No Data Yet'),
                'values' => array(0)
            );
        }
        $stats['seniority_data'] = $seniority_data;

        // Add location distribution data
        $location_raw = $data_manager->get_location_distribution($user_id);
        $location_data = array('labels' => array(), 'values' => array());

        if (!empty($location_raw)) {
            foreach ($location_raw as $loc) {
                $location_data['labels'][] = $loc['country_code'];
                $location_data['values'][] = $loc['count'];
            }
        } else {
            // Try to get from all jobs if user has no applications
            $all_jobs_dist = $data_manager->get_all_job_locations_distribution(5);
            if (!empty($all_jobs_dist)) {
                foreach ($all_jobs_dist as $loc) {
                    $location_data['labels'][] = $loc['country_code'];
                    $location_data['values'][] = $loc['count'];
                }
            } else {
                // Default data when no data available
                $location_data = array(
                    'labels' => array('GB', 'US', 'AE'),
                    'values' => array(0, 0, 0)
                );
            }
        }
        $stats['location_data'] = $location_data;

        wp_send_json_success($stats);
    }

    /**
     * AJAX: Get trends data
     */
    public function ajax_get_trends() {
        check_ajax_referer('sffc_dashboard_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not authenticated'));
        }

        $range = isset($_POST['range']) ? sanitize_text_field($_POST['range']) : '6m';
        $series = isset($_POST['series']) ? sanitize_text_field($_POST['series']) : 'locations';

        $trends = $this->get_trends_data($range, $series);

        wp_send_json_success($trends);
    }

    /**
     * AJAX: Get skills analysis
     */
    public function ajax_get_skills_analysis() {
        check_ajax_referer('sffc_dashboard_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not authenticated'));
        }

        $user_id = get_current_user_id();
        $analysis = $this->analyze_user_skills($user_id);

        wp_send_json_success($analysis);
    }

    /**
     * AJAX: Get market intelligence
     */
    public function ajax_get_market_intel() {
        check_ajax_referer('sffc_dashboard_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not authenticated'));
        }

        $filter = isset($_POST['filter']) ? sanitize_text_field($_POST['filter']) : 'all';
        $user_id = get_current_user_id();

        $intel = $this->get_market_intelligence($user_id, $filter);

        wp_send_json_success($intel);
    }

    /**
     * AJAX: Get salary data
     */
    public function ajax_get_salary_data() {
        check_ajax_referer('sffc_dashboard_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not authenticated'));
        }

        $user_id = get_current_user_id();
        $location1 = isset($_POST['location1']) ? sanitize_text_field($_POST['location1']) : 'london';
        $location2 = isset($_POST['location2']) ? sanitize_text_field($_POST['location2']) : 'new-york';

        $salary_data = $this->get_salary_intelligence($user_id, $location1, $location2);

        wp_send_json_success($salary_data);
    }

    /**
     * AJAX: Save preferences
     */
    public function ajax_save_preferences() {
        check_ajax_referer('sffc_dashboard_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not authenticated'));
        }

        $user_id = get_current_user_id();
        $prefs = isset($_POST['preferences']) ? $_POST['preferences'] : array();

        // Sanitize preferences
        $sanitized = array();
        $allowed_keys = array('notify_job_matches', 'notify_market_updates', 'notify_salary_changes', 'notify_weekly_digest', 'dashboard_layout', 'default_chart_range');

        foreach ($allowed_keys as $key) {
            if (isset($prefs[$key])) {
                $sanitized[$key] = is_bool($prefs[$key]) ? $prefs[$key] : sanitize_text_field($prefs[$key]);
            }
        }

        update_user_meta($user_id, 'sffc_dashboard_preferences', $sanitized);

        wp_send_json_success(array('message' => 'Preferences saved'));
    }

    /**
     * AJAX: Update profile
     */
    public function ajax_update_profile() {
        check_ajax_referer('sffc_dashboard_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not authenticated'));
        }

        $user_id = get_current_user_id();
        $field = isset($_POST['field']) ? sanitize_text_field($_POST['field']) : '';
        $value = isset($_POST['value']) ? $_POST['value'] : '';

        if (empty($field)) {
            wp_send_json_error(array('message' => 'Invalid field'));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sffc_user_profiles';

        // Sanitize value based on field type
        $array_fields = array('preferred_industries', 'preferred_locations', 'company_size', 'career_priorities', 'certifications', 'languages_spoken', 'skills');

        if (in_array($field, $array_fields)) {
            $value = is_array($value) ? $value : array($value);
            $value = array_map('sanitize_text_field', $value);
            $value = wp_json_encode($value);
        } else {
            $value = sanitize_text_field($value);
        }

        $updated = $wpdb->update(
            $table,
            array($field => $value, 'updated_at' => current_time('mysql')),
            array('user_id' => $user_id),
            array('%s', '%s'),
            array('%d')
        );

        if ($updated === false) {
            wp_send_json_error(array('message' => 'Update failed'));
        }

        wp_send_json_success(array('message' => 'Profile updated'));
    }

    /**
     * AJAX: Save/unsave article
     */
    public function ajax_save_article() {
        check_ajax_referer('sffc_dashboard_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not authenticated'));
        }

        $user_id = get_current_user_id();
        $article_id = isset($_POST['article_id']) ? intval($_POST['article_id']) : 0;
        $article_type = isset($_POST['article_type']) ? sanitize_text_field($_POST['article_type']) : 'news';
        $action_type = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : 'save';

        if (!$article_id) {
            wp_send_json_error(array('message' => 'Invalid article ID'));
        }

        // Use Market Intelligence class if available
        if (class_exists('SFFC_Dashboard_Market_Intelligence')) {
            $market_intel = SFFC_Dashboard_Market_Intelligence::get_instance();

            if ($action_type === 'save') {
                $result = $market_intel->save_article($user_id, $article_id);
            } else {
                $result = $market_intel->unsave_article($user_id, $article_id);
            }

            if ($result) {
                wp_send_json_success(array('message' => $action_type === 'save' ? 'Article saved' : 'Article removed'));
            } else {
                wp_send_json_error(array('message' => 'Operation failed'));
            }
        } else {
            // Fallback: use user meta
            $saved_articles = get_user_meta($user_id, 'sffc_saved_articles', true);
            if (!is_array($saved_articles)) {
                $saved_articles = array();
            }

            $article_key = $article_type . '_' . $article_id;

            if ($action_type === 'save') {
                if (!in_array($article_key, $saved_articles)) {
                    $saved_articles[] = $article_key;
                }
            } else {
                $saved_articles = array_diff($saved_articles, array($article_key));
            }

            update_user_meta($user_id, 'sffc_saved_articles', $saved_articles);
            wp_send_json_success(array('message' => $action_type === 'save' ? 'Article saved' : 'Article removed'));
        }
    }

    /**
     * AJAX: Save complete profile from quick edit modal
     */
    public function ajax_save_profile() {
        // Verify nonce with explicit error handling
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
        if (!wp_verify_nonce($nonce, 'sffc_dashboard_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed. Please refresh the page and try again.'));
            return;
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not authenticated'));
            return;
        }

        $user_id = get_current_user_id();
        $profile_data = isset($_POST['profile_data']) ? $_POST['profile_data'] : array();

        if (empty($profile_data) || !is_array($profile_data)) {
            wp_send_json_error(array('message' => 'No profile data provided'));
            return;
        }

        // Sanitize and save each field
        $allowed_fields = array(
            'current_role', 'years_experience', 'education_level',
            'target_industries', 'target_roles', 'career_goals',
            'preferred_locations', 'work_style', 'salary_expectation',
            'skills'
        );

        foreach ($profile_data as $field => $value) {
            if (!in_array($field, $allowed_fields)) {
                continue;
            }

            // Handle arrays (multi-select fields)
            if (is_array($value)) {
                $value = array_map('sanitize_text_field', $value);
            } elseif ($field === 'skills') {
                // Skills might be JSON encoded
                $decoded = json_decode(stripslashes($value), true);
                if (is_array($decoded)) {
                    $value = array_map('sanitize_text_field', $decoded);
                } else {
                    $value = sanitize_text_field($value);
                }
            } else {
                $value = sanitize_text_field($value);
            }

            update_user_meta($user_id, 'sffc_' . $field, $value);
        }

        // Sync skills to dedicated table if skills were updated
        if (isset($profile_data['skills'])) {
            $this->sync_skills_to_table($user_id, $profile_data['skills']);
        }

        // Calculate new profile completion
        $completion = $this->calculate_profile_completion_for_user($user_id);

        // Clear any cached analytics
        if ($this->analytics_engine) {
            $this->analytics_engine->clear_user_cache($user_id);
        }

        wp_send_json_success(array(
            'message' => 'Profile saved successfully',
            'completion' => $completion
        ));
    }

    /**
     * Sync user skills to the dedicated skills table
     */
    private function sync_skills_to_table($user_id, $skills_data) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'sffc_user_skills';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            return;
        }

        // Handle JSON encoded string
        if (is_string($skills_data)) {
            $skills_data = json_decode(stripslashes($skills_data), true);
        }

        if (!is_array($skills_data)) {
            return;
        }

        // Clear existing skills for user
        $wpdb->delete($table_name, array('user_id' => $user_id), array('%d'));

        // Insert new skills
        foreach ($skills_data as $skill) {
            $skill_name = is_array($skill) ? $skill['name'] ?? $skill : sanitize_text_field($skill);

            if (empty($skill_name)) {
                continue;
            }

            $wpdb->insert(
                $table_name,
                array(
                    'user_id' => $user_id,
                    'skill_name' => $skill_name,
                    'skill_category' => is_array($skill) ? ($skill['category'] ?? null) : null,
                    'proficiency_level' => is_array($skill) ? ($skill['level'] ?? 'intermediate') : 'intermediate'
                ),
                array('%d', '%s', '%s', '%s')
            );
        }
    }

    /**
     * Calculate profile completion percentage for a specific user (used by AJAX)
     */
    private function calculate_profile_completion_for_user($user_id) {
        $fields = $this->get_profile_field_definitions();
        $total_fields = 0;
        $completed_fields = 0;

        foreach ($fields as $field_id => $field) {
            if (!isset($field['required']) || !$field['required']) {
                continue;
            }

            $total_fields++;
            $value = get_user_meta($user_id, 'sffc_' . $field_id, true);

            if (!empty($value)) {
                $completed_fields++;
            }
        }

        return $total_fields > 0 ? round(($completed_fields / $total_fields) * 100) : 100;
    }

    /**
     * AJAX: Save single preference
     */
    public function ajax_save_single_preference() {
        // Verify nonce with explicit error handling
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
        if (!wp_verify_nonce($nonce, 'sffc_dashboard_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed. Please refresh the page and try again.'));
            return;
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not authenticated'));
            return;
        }

        $user_id = get_current_user_id();
        $preference = isset($_POST['preference']) ? sanitize_text_field($_POST['preference']) : '';
        $value = isset($_POST['value']) ? $_POST['value'] : null;

        if (empty($preference)) {
            wp_send_json_error(array('message' => 'No preference specified'));
            return;
        }

        // Allowed preferences
        $allowed_prefs = array(
            'onboarding_complete', 'theme', 'accent_color', 'compact_mode',
            'show_welcome', 'email_digest', 'notify_job_matches',
            'notify_market_signals', 'notify_skill_trends'
        );

        if (!in_array($preference, $allowed_prefs)) {
            wp_send_json_error(array('message' => 'Invalid preference'));
            return;
        }

        // Sanitize value based on preference type
        if (is_bool($value) || $value === 'true' || $value === 'false') {
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        } else {
            $value = sanitize_text_field($value);
        }

        update_user_meta($user_id, 'sffc_pref_' . $preference, $value);

        wp_send_json_success(array('message' => 'Preference saved'));
    }

    /**
     * AJAX: Save news source preferences
     */
    public function ajax_save_source_preferences() {
        // Verify nonce with explicit error handling
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
        if (!wp_verify_nonce($nonce, 'sffc_dashboard_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed. Please refresh the page and try again.'));
            return;
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not authenticated'));
            return;
        }

        $user_id = get_current_user_id();
        $preferences = isset($_POST['preferences']) ? $_POST['preferences'] : array();

        if (empty($preferences) || !is_array($preferences)) {
            wp_send_json_error(array('message' => 'No preferences provided'));
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'sffc_news_source_preferences';

        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

        if ($table_exists) {
            // Clear existing preferences
            $wpdb->delete($table_name, array('user_id' => $user_id), array('%d'));

            // Insert new preferences
            foreach ($preferences as $pref) {
                if (!isset($pref['source_id'])) {
                    continue;
                }

                $wpdb->insert(
                    $table_name,
                    array(
                        'user_id' => $user_id,
                        'source_id' => sanitize_text_field($pref['source_id']),
                        'is_pinned' => intval($pref['is_pinned'] ?? 0),
                        'is_hidden' => intval($pref['is_hidden'] ?? 0)
                    ),
                    array('%d', '%s', '%d', '%d')
                );
            }
        } else {
            // Fallback: use user meta
            $prefs_array = array();
            foreach ($preferences as $pref) {
                if (!isset($pref['source_id'])) {
                    continue;
                }
                $prefs_array[$pref['source_id']] = array(
                    'is_pinned' => intval($pref['is_pinned'] ?? 0),
                    'is_hidden' => intval($pref['is_hidden'] ?? 0)
                );
            }
            update_user_meta($user_id, 'sffc_news_source_preferences', $prefs_array);
        }

        wp_send_json_success(array('message' => 'Source preferences saved'));
    }

    /**
     * AJAX: Add alert keyword
     */
    public function ajax_add_alert_keyword() {
        // Verify nonce with explicit error handling
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
        if (!wp_verify_nonce($nonce, 'sffc_dashboard_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed. Please refresh the page and try again.'));
            return;
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not authenticated'));
            return;
        }

        $user_id = get_current_user_id();
        $keyword = isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : '';
        $keyword_type = isset($_POST['keyword_type']) ? sanitize_text_field($_POST['keyword_type']) : 'general';

        if (empty($keyword)) {
            wp_send_json_error(array('message' => 'Keyword is required'));
            return;
        }

        // Validate keyword type
        $valid_types = array('company', 'topic', 'skill', 'location', 'general');
        if (!in_array($keyword_type, $valid_types)) {
            $keyword_type = 'general';
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'sffc_alert_keywords';

        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

        if ($table_exists) {
            // Check for duplicate
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name WHERE user_id = %d AND keyword = %s",
                $user_id, $keyword
            ));

            if ($existing) {
                wp_send_json_error(array('message' => 'Keyword already exists'));
            }

            // Insert keyword
            $result = $wpdb->insert(
                $table_name,
                array(
                    'user_id' => $user_id,
                    'keyword' => $keyword,
                    'keyword_type' => $keyword_type,
                    'is_active' => 1
                ),
                array('%d', '%s', '%s', '%d')
            );

            if ($result) {
                wp_send_json_success(array(
                    'id' => $wpdb->insert_id,
                    'keyword' => $keyword,
                    'type' => $keyword_type
                ));
            } else {
                wp_send_json_error(array('message' => 'Failed to save keyword'));
            }
        } else {
            // Fallback: use user meta
            $keywords = get_user_meta($user_id, 'sffc_alert_keywords', true);
            if (!is_array($keywords)) {
                $keywords = array();
            }

            // Check duplicate
            foreach ($keywords as $kw) {
                if (strtolower($kw['keyword']) === strtolower($keyword)) {
                    wp_send_json_error(array('message' => 'Keyword already exists'));
                }
            }

            // Add keyword with unique ID
            $new_id = uniqid('kw_');
            $keywords[] = array(
                'id' => $new_id,
                'keyword' => $keyword,
                'type' => $keyword_type,
                'is_active' => true
            );

            update_user_meta($user_id, 'sffc_alert_keywords', $keywords);

            wp_send_json_success(array(
                'id' => $new_id,
                'keyword' => $keyword,
                'type' => $keyword_type
            ));
        }
    }

    /**
     * AJAX: Toggle alert keyword active status
     */
    public function ajax_toggle_alert_keyword() {
        // Verify nonce with explicit error handling
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
        if (!wp_verify_nonce($nonce, 'sffc_dashboard_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed. Please refresh the page and try again.'));
            return;
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not authenticated'));
            return;
        }

        $user_id = get_current_user_id();
        $keyword_id = isset($_POST['keyword_id']) ? $_POST['keyword_id'] : '';
        $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 0;

        if (empty($keyword_id)) {
            wp_send_json_error(array('message' => 'Keyword ID is required'));
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'sffc_alert_keywords';

        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

        if ($table_exists && is_numeric($keyword_id)) {
            $result = $wpdb->update(
                $table_name,
                array('is_active' => $is_active),
                array('id' => intval($keyword_id), 'user_id' => $user_id),
                array('%d'),
                array('%d', '%d')
            );

            if ($result !== false) {
                wp_send_json_success(array('message' => 'Keyword updated'));
            } else {
                wp_send_json_error(array('message' => 'Failed to update keyword'));
            }
        } else {
            // Fallback: use user meta
            $keywords = get_user_meta($user_id, 'sffc_alert_keywords', true);
            if (!is_array($keywords)) {
                wp_send_json_error(array('message' => 'Keyword not found'));
            }

            $found = false;
            foreach ($keywords as $key => $kw) {
                if ($kw['id'] === $keyword_id) {
                    $keywords[$key]['is_active'] = (bool) $is_active;
                    $found = true;
                    break;
                }
            }

            if ($found) {
                update_user_meta($user_id, 'sffc_alert_keywords', $keywords);
                wp_send_json_success(array('message' => 'Keyword updated'));
            } else {
                wp_send_json_error(array('message' => 'Keyword not found'));
            }
        }
    }

    /**
     * AJAX: Delete alert keyword
     */
    public function ajax_delete_alert_keyword() {
        // Verify nonce with explicit error handling
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
        if (!wp_verify_nonce($nonce, 'sffc_dashboard_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed. Please refresh the page and try again.'));
            return;
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not authenticated'));
            return;
        }

        $user_id = get_current_user_id();
        $keyword_id = isset($_POST['keyword_id']) ? $_POST['keyword_id'] : '';

        if (empty($keyword_id)) {
            wp_send_json_error(array('message' => 'Keyword ID is required'));
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'sffc_alert_keywords';

        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

        if ($table_exists && is_numeric($keyword_id)) {
            $result = $wpdb->delete(
                $table_name,
                array('id' => intval($keyword_id), 'user_id' => $user_id),
                array('%d', '%d')
            );

            if ($result) {
                wp_send_json_success(array('message' => 'Keyword deleted'));
            } else {
                wp_send_json_error(array('message' => 'Failed to delete keyword'));
            }
        } else {
            // Fallback: use user meta
            $keywords = get_user_meta($user_id, 'sffc_alert_keywords', true);
            if (!is_array($keywords)) {
                wp_send_json_error(array('message' => 'Keyword not found'));
            }

            $found = false;
            foreach ($keywords as $key => $kw) {
                if ($kw['id'] === $keyword_id) {
                    unset($keywords[$key]);
                    $found = true;
                    break;
                }
            }

            if ($found) {
                update_user_meta($user_id, 'sffc_alert_keywords', array_values($keywords));
                wp_send_json_success(array('message' => 'Keyword deleted'));
            } else {
                wp_send_json_error(array('message' => 'Keyword not found'));
            }
        }
    }

    // =========================================
    // Data Calculation Methods (Using Analytics Engine)
    // =========================================

    private function calculate_match_score($user_id) {
        if ($this->analytics_engine) {
            return $this->analytics_engine->calculate_match_score($user_id);
        }
        return 50; // Default fallback
    }

    private function calculate_skills_demand($user_id) {
        if ($this->analytics_engine) {
            return $this->analytics_engine->calculate_skills_demand_index($user_id);
        }
        return 50; // Default fallback
    }

    private function calculate_market_position($user_id) {
        if ($this->analytics_engine) {
            return $this->analytics_engine->calculate_market_position($user_id);
        }
        return 50; // Default fallback
    }

    private function count_matching_opportunities($user_id) {
        if ($this->analytics_engine) {
            return $this->analytics_engine->count_matching_opportunities($user_id);
        }
        return 0; // Default fallback
    }

    private function count_trending_skills($user_id) {
        if ($this->analytics_engine) {
            return $this->analytics_engine->count_user_trending_skills($user_id);
        }
        return 0; // Default fallback
    }

    private function calculate_percentile($user_id) {
        if ($this->analytics_engine) {
            return $this->analytics_engine->calculate_user_percentile($user_id);
        }
        return 50; // Default fallback
    }

    private function get_trends_data($range, $series) {
        // Use analytics engine for real trends data
        if ($this->analytics_engine) {
            $user_id = get_current_user_id();
            return $this->analytics_engine->get_trends_data($range, $series, $user_id);
        }

        // Fallback data
        return array(
            'labels' => array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'),
            'datasets' => array(
                array(
                    'label' => 'London',
                    'data' => array(65, 70, 72, 68, 75, 80),
                    'borderColor' => '#1e3a5f',
                    'tension' => 0.3
                ),
                array(
                    'label' => 'New York',
                    'data' => array(60, 62, 65, 70, 72, 75),
                    'borderColor' => '#059669',
                    'tension' => 0.3
                )
            ),
            'insight' => 'London shows strong growth in investment banking roles, up 15% from last quarter.'
        );
    }

    private function analyze_user_skills($user_id) {
        // TODO: Implement in Phase 4
        // Use skills analyzer if available
        if (class_exists('SFFC_Dashboard_Skills_Analyzer')) {
            $skills_analyzer = SFFC_Dashboard_Skills_Analyzer::get_instance();
            return $skills_analyzer->get_skills_analysis($user_id);
        }

        // Fallback
        return array(
            'skills' => array(
                array('name' => 'Financial Modeling', 'demand' => 85, 'trend' => 'up'),
                array('name' => 'Excel', 'demand' => 90, 'trend' => 'stable'),
                array('name' => 'Python', 'demand' => 78, 'trend' => 'up'),
            ),
            'recommendations' => array(
                array('skill' => 'SQL', 'importance' => 'High', 'courses' => array('DataCamp SQL')),
                array('skill' => 'Power BI', 'importance' => 'Medium', 'courses' => array('Microsoft Learn')),
            ),
            'gaps' => array(),
            'radar_data' => array(),
            'summary' => array(),
        );
    }

    private function get_market_intelligence($user_id, $filter) {
        // Use market intelligence class if available
        if (class_exists('SFFC_Dashboard_Market_Intelligence')) {
            $market_intel = SFFC_Dashboard_Market_Intelligence::get_instance();
            return $market_intel->get_market_data($user_id, $filter);
        }

        // Fallback
        return array(
            'news' => array(),
            'deals' => array(),
            'signals' => array(
                array('type' => 'positive', 'text' => 'JP Morgan expanding London operations - IB hiring expected to increase')
            ),
            'trending_topics' => array(),
            'saved_articles' => array(),
        );
    }

    private function get_salary_intelligence($user_id, $location1, $location2) {
        // Use real salary analyzer if available
        if (class_exists('SFFC_Dashboard_Salary_Analyzer')) {
            $salary_analyzer = SFFC_Dashboard_Salary_Analyzer::get_instance();
            return $salary_analyzer->get_salary_data($user_id, $location1, $location2);
        }

        // Fallback to defaults
        return array(
            'estimate' => array(
                'min' => 85000,
                'max' => 120000,
                'currency' => 'GBP',
                'symbol' => '£',
                'formatted_min' => '£85k',
                'formatted_max' => '£120k'
            ),
            'location_comparison' => array(
                $location1 => array('min' => 85000, 'max' => 120000, 'currency' => 'GBP', 'symbol' => '£'),
                $location2 => array('min' => 95000, 'max' => 140000, 'currency' => 'USD', 'symbol' => '$')
            ),
            'industry_data' => array(
                array('industry' => 'Investment Banking', 'median' => 110000, 'currency' => 'GBP'),
                array('industry' => 'Private Equity', 'median' => 130000, 'currency' => 'GBP'),
                array('industry' => 'Asset Management', 'median' => 95000, 'currency' => 'GBP')
            ),
            'percentile' => 50,
            'factors' => array()
        );
    }

    // =========================================
    // Profile Quick-Edit Modal (Phase 2)
    // =========================================

    /**
     * Render profile quick-edit modal
     */
    private function render_profile_quick_edit_modal() {
        $profile = $this->user_profile;

        // Define editable fields with their types and options
        $fields = $this->get_profile_field_definitions();

        ob_start();
        ?>
        <div class="sffc-modal-overlay" id="sffc-quick-edit-modal" style="display: none;">
            <div class="sffc-modal sffc-quick-edit-modal">
                <button class="sffc-modal-close" id="sffc-close-quick-edit">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>

                <div class="sffc-modal-header">
                    <h2>
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                        </svg>
                        Edit Profile
                    </h2>
                    <p>Update your career profile information</p>
                </div>

                <div class="sffc-modal-body">
                    <form id="sffc-quick-edit-form" class="sffc-quick-edit-form">
                        <!-- Field tabs for organization -->
                        <div class="sffc-edit-tabs">
                            <button type="button" class="sffc-edit-tab active" data-tab="basic">Basic Info</button>
                            <button type="button" class="sffc-edit-tab" data-tab="career">Career</button>
                            <button type="button" class="sffc-edit-tab" data-tab="preferences">Preferences</button>
                            <button type="button" class="sffc-edit-tab" data-tab="skills">Skills</button>
                        </div>

                        <!-- Basic Info Tab -->
                        <div class="sffc-edit-tab-content active" data-tab="basic">
                            <div class="sffc-form-row">
                                <label for="sffc-edit-full_name">Full Name</label>
                                <input type="text" id="sffc-edit-full_name" name="full_name"
                                       value="<?php echo esc_attr($profile['full_name'] ?? ''); ?>"
                                       placeholder="Enter your full name">
                            </div>

                            <div class="sffc-form-row">
                                <label for="sffc-edit-years_experience">Years of Experience</label>
                                <select id="sffc-edit-years_experience" name="years_experience">
                                    <option value="">Select experience level</option>
                                    <?php
                                    $exp_options = array('0-1', '1-2', '2-3', '3-5', '5-7', '7-10', '10-15', '15+');
                                    foreach ($exp_options as $exp):
                                    ?>
                                    <option value="<?php echo esc_attr($exp); ?>" <?php selected($profile['years_experience'] ?? '', $exp); ?>>
                                        <?php echo esc_html($exp); ?> years
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="sffc-form-row">
                                <label for="sffc-edit-education_level">Education Level</label>
                                <select id="sffc-edit-education_level" name="education_level">
                                    <option value="">Select education level</option>
                                    <?php
                                    $edu_options = array(
                                        'high_school' => 'High School',
                                        'bachelors' => "Bachelor's Degree",
                                        'masters' => "Master's Degree",
                                        'mba' => 'MBA',
                                        'phd' => 'PhD',
                                        'professional' => 'Professional Qualification'
                                    );
                                    foreach ($edu_options as $val => $label):
                                    ?>
                                    <option value="<?php echo esc_attr($val); ?>" <?php selected($profile['education_level'] ?? '', $val); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Career Tab -->
                        <div class="sffc-edit-tab-content" data-tab="career">
                            <div class="sffc-form-row">
                                <label for="sffc-edit-current_role">Current Role</label>
                                <input type="text" id="sffc-edit-current_role" name="current_role"
                                       value="<?php echo esc_attr($profile['current_role'] ?? ''); ?>"
                                       placeholder="e.g., Analyst, Associate, VP">
                            </div>

                            <div class="sffc-form-row">
                                <label for="sffc-edit-target_seniority">Target Seniority</label>
                                <select id="sffc-edit-target_seniority" name="target_seniority">
                                    <option value="">Select target level</option>
                                    <?php
                                    $seniority = array(
                                        'analyst' => 'Analyst',
                                        'associate' => 'Associate',
                                        'vp' => 'Vice President',
                                        'director' => 'Director',
                                        'md' => 'Managing Director',
                                        'partner' => 'Partner',
                                        'c-suite' => 'C-Suite'
                                    );
                                    foreach ($seniority as $val => $label):
                                    ?>
                                    <option value="<?php echo esc_attr($val); ?>" <?php selected($profile['target_seniority'] ?? '', $val); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="sffc-form-row">
                                <label>Target Industries</label>
                                <div class="sffc-checkbox-grid" id="sffc-edit-industries">
                                    <?php
                                    $industries = array(
                                        'investment_banking' => 'Investment Banking',
                                        'private_equity' => 'Private Equity',
                                        'venture_capital' => 'Venture Capital',
                                        'hedge_funds' => 'Hedge Funds',
                                        'asset_management' => 'Asset Management',
                                        'corporate_finance' => 'Corporate Finance',
                                        'consulting' => 'Management Consulting',
                                        'fintech' => 'FinTech'
                                    );
                                    $selected_industries = $profile['preferred_industries'] ?? array();
                                    foreach ($industries as $val => $label):
                                    ?>
                                    <label class="sffc-checkbox-item">
                                        <input type="checkbox" name="preferred_industries[]" value="<?php echo esc_attr($val); ?>"
                                               <?php checked(is_array($selected_industries) && in_array($val, $selected_industries)); ?>>
                                        <span class="sffc-checkbox-custom"></span>
                                        <span><?php echo esc_html($label); ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Preferences Tab -->
                        <div class="sffc-edit-tab-content" data-tab="preferences">
                            <div class="sffc-form-row">
                                <label>Preferred Locations</label>
                                <div class="sffc-checkbox-grid" id="sffc-edit-locations">
                                    <?php
                                    $locations = array(
                                        'london' => 'London',
                                        'new_york' => 'New York',
                                        'hong_kong' => 'Hong Kong',
                                        'singapore' => 'Singapore',
                                        'dubai' => 'Dubai',
                                        'san_francisco' => 'San Francisco',
                                        'chicago' => 'Chicago',
                                        'frankfurt' => 'Frankfurt',
                                        'paris' => 'Paris',
                                        'zurich' => 'Zurich',
                                        'sydney' => 'Sydney',
                                        'toronto' => 'Toronto'
                                    );
                                    $selected_locations = $profile['preferred_locations'] ?? array();
                                    foreach ($locations as $val => $label):
                                    ?>
                                    <label class="sffc-checkbox-item">
                                        <input type="checkbox" name="preferred_locations[]" value="<?php echo esc_attr($val); ?>"
                                               <?php checked(is_array($selected_locations) && in_array($val, $selected_locations)); ?>>
                                        <span class="sffc-checkbox-custom"></span>
                                        <span><?php echo esc_html($label); ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="sffc-form-row">
                                <label for="sffc-edit-work_preference">Work Preference</label>
                                <select id="sffc-edit-work_preference" name="work_preference">
                                    <option value="">Select work preference</option>
                                    <?php
                                    $work_prefs = array(
                                        'remote' => 'Fully Remote',
                                        'hybrid' => 'Hybrid',
                                        'office' => 'In Office',
                                        'flexible' => 'Flexible / No Preference'
                                    );
                                    foreach ($work_prefs as $val => $label):
                                    ?>
                                    <option value="<?php echo esc_attr($val); ?>" <?php selected($profile['work_preference'] ?? '', $val); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="sffc-form-row">
                                <label for="sffc-edit-salary_expectations">Salary Expectations</label>
                                <div class="sffc-salary-range-inputs">
                                    <select id="sffc-edit-salary_currency" name="salary_currency">
                                        <option value="GBP" <?php selected(($profile['salary_currency'] ?? 'GBP'), 'GBP'); ?>>GBP (£)</option>
                                        <option value="USD" <?php selected(($profile['salary_currency'] ?? 'GBP'), 'USD'); ?>>USD ($)</option>
                                        <option value="EUR" <?php selected(($profile['salary_currency'] ?? 'GBP'), 'EUR'); ?>>EUR (€)</option>
                                    </select>
                                    <input type="number" id="sffc-edit-salary_min" name="salary_min"
                                           value="<?php echo esc_attr($profile['salary_min'] ?? ''); ?>"
                                           placeholder="Min">
                                    <span>to</span>
                                    <input type="number" id="sffc-edit-salary_max" name="salary_max"
                                           value="<?php echo esc_attr($profile['salary_max'] ?? ''); ?>"
                                           placeholder="Max">
                                </div>
                            </div>

                            <div class="sffc-form-row">
                                <label for="sffc-edit-availability">Availability</label>
                                <select id="sffc-edit-availability" name="availability">
                                    <option value="">Select availability</option>
                                    <?php
                                    $availability = array(
                                        'immediate' => 'Immediately',
                                        '2_weeks' => '2 Weeks Notice',
                                        '1_month' => '1 Month Notice',
                                        '2_months' => '2 Months Notice',
                                        '3_months' => '3+ Months Notice',
                                        'not_looking' => 'Not Currently Looking'
                                    );
                                    foreach ($availability as $val => $label):
                                    ?>
                                    <option value="<?php echo esc_attr($val); ?>" <?php selected($profile['availability'] ?? '', $val); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Skills Tab -->
                        <div class="sffc-edit-tab-content" data-tab="skills">
                            <div class="sffc-form-row">
                                <label>Your Skills</label>
                                <p class="sffc-form-hint">Add skills that match your expertise. Higher demand skills improve your match score.</p>
                                <div class="sffc-skills-input-container">
                                    <div class="sffc-skills-tags" id="sffc-edit-skills-tags">
                                        <?php
                                        $skills = $profile['skills'] ?? array();
                                        if (is_array($skills)):
                                            foreach ($skills as $skill):
                                        ?>
                                        <span class="sffc-skill-tag" data-skill="<?php echo esc_attr($skill); ?>">
                                            <?php echo esc_html($skill); ?>
                                            <button type="button" class="sffc-remove-skill">&times;</button>
                                        </span>
                                        <?php
                                            endforeach;
                                        endif;
                                        ?>
                                    </div>
                                    <input type="text" id="sffc-skill-input" placeholder="Type a skill and press Enter">
                                    <input type="hidden" name="skills" id="sffc-skills-hidden"
                                           value="<?php echo esc_attr(is_array($skills) ? implode(',', $skills) : ''); ?>">
                                </div>
                            </div>

                            <div class="sffc-suggested-skills">
                                <h4>Suggested High-Demand Skills</h4>
                                <div class="sffc-skill-suggestions" id="sffc-skill-suggestions">
                                    <?php
                                    $suggested = array('Financial Modeling', 'Excel', 'Python', 'SQL', 'Power BI', 'Tableau', 'VBA', 'Bloomberg Terminal', 'DCF Analysis', 'LBO Modeling');
                                    foreach ($suggested as $skill):
                                    ?>
                                    <button type="button" class="sffc-suggested-skill" data-skill="<?php echo esc_attr($skill); ?>">
                                        + <?php echo esc_html($skill); ?>
                                    </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="sffc-form-row">
                                <label>Certifications</label>
                                <div class="sffc-checkbox-grid sffc-checkbox-grid-sm" id="sffc-edit-certifications">
                                    <?php
                                    $certs = array(
                                        'cfa_1' => 'CFA Level I',
                                        'cfa_2' => 'CFA Level II',
                                        'cfa_3' => 'CFA Level III',
                                        'frm' => 'FRM',
                                        'cpa' => 'CPA',
                                        'caia' => 'CAIA',
                                        'acca' => 'ACCA',
                                        'series_7' => 'Series 7',
                                        'series_63' => 'Series 63'
                                    );
                                    $selected_certs = $profile['certifications'] ?? array();
                                    foreach ($certs as $val => $label):
                                    ?>
                                    <label class="sffc-checkbox-item">
                                        <input type="checkbox" name="certifications[]" value="<?php echo esc_attr($val); ?>"
                                               <?php checked(is_array($selected_certs) && in_array($val, $selected_certs)); ?>>
                                        <span class="sffc-checkbox-custom"></span>
                                        <span><?php echo esc_html($label); ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="sffc-modal-footer">
                    <button type="button" class="sffc-btn sffc-btn-outline" id="sffc-cancel-quick-edit">Cancel</button>
                    <button type="button" class="sffc-btn sffc-btn-primary" id="sffc-save-quick-edit">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                            <polyline points="17 21 17 13 7 13 7 21"/>
                            <polyline points="7 3 7 8 15 8"/>
                        </svg>
                        Save Changes
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get profile field definitions
     */
    private function get_profile_field_definitions() {
        return array(
            'full_name' => array(
                'label' => 'Full Name',
                'type' => 'text',
                'required' => true,
                'group' => 'basic'
            ),
            'years_experience' => array(
                'label' => 'Years of Experience',
                'type' => 'select',
                'required' => true,
                'group' => 'basic'
            ),
            'current_role' => array(
                'label' => 'Current Role',
                'type' => 'text',
                'required' => true,
                'group' => 'career'
            ),
            'preferred_industries' => array(
                'label' => 'Target Industries',
                'type' => 'multiselect',
                'required' => true,
                'group' => 'career'
            ),
            'preferred_locations' => array(
                'label' => 'Preferred Locations',
                'type' => 'multiselect',
                'required' => true,
                'group' => 'preferences'
            ),
            'work_preference' => array(
                'label' => 'Work Preference',
                'type' => 'select',
                'required' => true,
                'group' => 'preferences'
            ),
            'skills' => array(
                'label' => 'Skills',
                'type' => 'tags',
                'required' => true,
                'group' => 'skills'
            ),
            'education_level' => array(
                'label' => 'Education Level',
                'type' => 'select',
                'required' => false,
                'group' => 'basic'
            ),
            'target_seniority' => array(
                'label' => 'Target Seniority',
                'type' => 'select',
                'required' => false,
                'group' => 'career'
            ),
            'certifications' => array(
                'label' => 'Certifications',
                'type' => 'multiselect',
                'required' => false,
                'group' => 'skills'
            ),
            'salary_expectations' => array(
                'label' => 'Salary Expectations',
                'type' => 'range',
                'required' => false,
                'group' => 'preferences'
            ),
            'availability' => array(
                'label' => 'Availability',
                'type' => 'select',
                'required' => false,
                'group' => 'preferences'
            )
        );
    }

    // =========================================
    // Missing Fields Indicator (Phase 2)
    // =========================================

    /**
     * Render missing fields indicator
     */
    private function render_missing_fields_indicator() {
        $profile = $this->user_profile;
        $fields = $this->get_profile_field_definitions();

        // Find missing required fields
        $missing_fields = array();
        foreach ($fields as $field_id => $field_config) {
            if (!empty($field_config['required']) && empty($profile[$field_id])) {
                $missing_fields[$field_id] = $field_config;
            }
        }

        if (empty($missing_fields)) {
            return ''; // Profile is complete
        }

        ob_start();
        ?>
        <div class="sffc-missing-fields-indicator" id="sffc-missing-fields">
            <div class="sffc-missing-fields-header">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <line x1="19" y1="8" x2="19" y2="14"/>
                    <line x1="22" y1="11" x2="16" y2="11"/>
                </svg>
                <span class="sffc-missing-count">Complete Your Profile</span>
                <button type="button" class="sffc-missing-toggle" id="sffc-toggle-missing" aria-expanded="false">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="6 9 12 15 18 9"/>
                    </svg>
                </button>
            </div>

            <div class="sffc-missing-fields-list" id="sffc-missing-fields-list" style="display: none;">
                <?php
                $profile_url = home_url('/career-profile/');
                ?>
                <?php foreach ($missing_fields as $field_id => $field_config): ?>
                <div class="sffc-missing-field-item" data-field="<?php echo esc_attr($field_id); ?>">
                    <span class="sffc-missing-field-name"><?php echo esc_html($field_config['label']); ?></span>
                    <a href="<?php echo esc_url($profile_url . '#sffc-' . $field_config['group']); ?>" class="sffc-btn sffc-btn-sm sffc-btn-primary sffc-add-field-btn">
                        Add
                    </a>
                </div>
                <?php endforeach; ?>

                <div class="sffc-missing-fields-footer">
                    <a href="<?php echo esc_url($profile_url); ?>" class="sffc-btn" id="sffc-complete-profile-btn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                            <polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                        Start Profile Setup
                    </a>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // =========================================
    // Onboarding Tooltips (Phase 10)
    // =========================================

    /**
     * Render onboarding tooltips
     */
    private function render_onboarding_tooltips() {
        // Check if user has seen onboarding
        $user_id = get_current_user_id();
        $onboarding_complete = get_user_meta($user_id, 'sffc_dashboard_onboarding_complete', true);

        if ($onboarding_complete) {
            return ''; // User has completed onboarding
        }

        ob_start();
        ?>
        <div class="sffc-onboarding-overlay" id="sffc-onboarding" style="display: none;">
            <div class="sffc-onboarding-backdrop"></div>

            <!-- Step 1: Welcome -->
            <div class="sffc-onboarding-step" data-step="1">
                <div class="sffc-onboarding-tooltip sffc-tooltip-center">
                    <div class="sffc-tooltip-content">
                        <h3>Welcome to Your Career Dashboard</h3>
                        <p>This is your personal career intelligence hub. Let us show you around!</p>
                        <div class="sffc-tooltip-actions">
                            <button class="sffc-btn sffc-btn-outline sffc-btn-sm" id="sffc-skip-onboarding">Skip Tour</button>
                            <button class="sffc-btn sffc-btn-primary sffc-btn-sm" data-next="2">Get Started</button>
                        </div>
                    </div>
                    <div class="sffc-tooltip-progress">
                        <span class="sffc-progress-dot active"></span>
                        <span class="sffc-progress-dot"></span>
                        <span class="sffc-progress-dot"></span>
                        <span class="sffc-progress-dot"></span>
                        <span class="sffc-progress-dot"></span>
                    </div>
                </div>
            </div>

            <!-- Step 2: Stats Cards -->
            <div class="sffc-onboarding-step" data-step="2" style="display: none;">
                <div class="sffc-onboarding-tooltip sffc-tooltip-stats" data-target=".sffc-dashboard-stats-row">
                    <div class="sffc-tooltip-pointer"></div>
                    <div class="sffc-tooltip-content">
                        <h3>Your Career Metrics</h3>
                        <p>These cards show your Match Score, Skills Demand Index, and Market Position. They update based on your profile and market data.</p>
                        <div class="sffc-tooltip-actions">
                            <button class="sffc-btn sffc-btn-outline sffc-btn-sm" data-prev="1">Back</button>
                            <button class="sffc-btn sffc-btn-primary sffc-btn-sm" data-next="3">Next</button>
                        </div>
                    </div>
                    <div class="sffc-tooltip-progress">
                        <span class="sffc-progress-dot"></span>
                        <span class="sffc-progress-dot active"></span>
                        <span class="sffc-progress-dot"></span>
                        <span class="sffc-progress-dot"></span>
                        <span class="sffc-progress-dot"></span>
                    </div>
                </div>
            </div>

            <!-- Step 3: Trends Chart -->
            <div class="sffc-onboarding-step" data-step="3" style="display: none;">
                <div class="sffc-onboarding-tooltip sffc-tooltip-trends" data-target=".sffc-trends-section">
                    <div class="sffc-tooltip-pointer"></div>
                    <div class="sffc-tooltip-content">
                        <h3>Career Trends</h3>
                        <p>Track demand trends for locations, industries, and roles. Toggle between different views and time ranges.</p>
                        <div class="sffc-tooltip-actions">
                            <button class="sffc-btn sffc-btn-outline sffc-btn-sm" data-prev="2">Back</button>
                            <button class="sffc-btn sffc-btn-primary sffc-btn-sm" data-next="4">Next</button>
                        </div>
                    </div>
                    <div class="sffc-tooltip-progress">
                        <span class="sffc-progress-dot"></span>
                        <span class="sffc-progress-dot"></span>
                        <span class="sffc-progress-dot active"></span>
                        <span class="sffc-progress-dot"></span>
                        <span class="sffc-progress-dot"></span>
                    </div>
                </div>
            </div>

            <!-- Step 4: Skills & Market -->
            <div class="sffc-onboarding-step" data-step="4" style="display: none;">
                <div class="sffc-onboarding-tooltip sffc-tooltip-skills" data-target=".sffc-dashboard-two-col">
                    <div class="sffc-tooltip-pointer"></div>
                    <div class="sffc-tooltip-content">
                        <h3>Skills & Market Intelligence</h3>
                        <p>See which of your skills are in demand and get upskilling recommendations. Stay informed with personalized market news.</p>
                        <div class="sffc-tooltip-actions">
                            <button class="sffc-btn sffc-btn-outline sffc-btn-sm" data-prev="3">Back</button>
                            <button class="sffc-btn sffc-btn-primary sffc-btn-sm" data-next="5">Next</button>
                        </div>
                    </div>
                    <div class="sffc-tooltip-progress">
                        <span class="sffc-progress-dot"></span>
                        <span class="sffc-progress-dot"></span>
                        <span class="sffc-progress-dot"></span>
                        <span class="sffc-progress-dot active"></span>
                        <span class="sffc-progress-dot"></span>
                    </div>
                </div>
            </div>

            <!-- Step 5: Salary Intelligence -->
            <div class="sffc-onboarding-step" data-step="5" style="display: none;">
                <div class="sffc-onboarding-tooltip sffc-tooltip-salary" data-target=".sffc-salary-section">
                    <div class="sffc-tooltip-pointer"></div>
                    <div class="sffc-tooltip-content">
                        <h3>Salary Intelligence</h3>
                        <p>Compare salaries across locations and industries. Get personalized salary estimates based on your profile.</p>
                        <div class="sffc-tooltip-actions">
                            <button class="sffc-btn sffc-btn-outline sffc-btn-sm" data-prev="4">Back</button>
                            <button class="sffc-btn sffc-btn-primary sffc-btn-sm" id="sffc-complete-onboarding">Done</button>
                        </div>
                    </div>
                    <div class="sffc-tooltip-progress">
                        <span class="sffc-progress-dot"></span>
                        <span class="sffc-progress-dot"></span>
                        <span class="sffc-progress-dot"></span>
                        <span class="sffc-progress-dot"></span>
                        <span class="sffc-progress-dot active"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Restart Tour Button (always visible in settings) -->
        <button type="button" class="sffc-restart-tour-btn" id="sffc-restart-tour" title="Restart Dashboard Tour">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
        </button>
        <?php
        return ob_get_clean();
    }

    // =========================================================================
    // AJAX HANDLERS - Job Application Tracking
    // =========================================================================

    /**
     * AJAX: Save job application stage
     */
    public function ajax_save_job_stage() {
        check_ajax_referer('sffc_dashboard_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        $user_id = get_current_user_id();
        $job_id = isset($_POST['job_id']) ? intval($_POST['job_id']) : 0;
        $stage = isset($_POST['stage']) ? sanitize_text_field($_POST['stage']) : '';
        $previous_stage = isset($_POST['previous_stage']) ? sanitize_text_field($_POST['previous_stage']) : '';

        if (!$job_id) {
            wp_send_json_error(array('message' => 'Invalid job ID'));
        }

        $data_manager = $this->get_data_manager();
        $result = $data_manager->save_job_stage($user_id, $job_id, $stage);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Toggle saved job
     */
    public function ajax_toggle_saved_job() {
        check_ajax_referer('sffc_dashboard_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        $user_id = get_current_user_id();
        $job_id = isset($_POST['job_id']) ? intval($_POST['job_id']) : 0;

        if (!$job_id) {
            wp_send_json_error(array('message' => 'Invalid job ID'));
        }

        $data_manager = $this->get_data_manager();

        if ($data_manager->is_job_saved($user_id, $job_id)) {
            $result = $data_manager->unsave_job($user_id, $job_id);
            $saved = false;
        } else {
            $result = $data_manager->save_job($user_id, $job_id);
            $saved = true;
        }

        if ($result) {
            wp_send_json_success(array(
                'saved' => $saved,
                'count' => $data_manager->get_saved_jobs_count($user_id)
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to update saved status'));
        }
    }

    /**
     * AJAX: Load more jobs for the jobs grid
     */
    public function ajax_load_more_jobs() {
        check_ajax_referer('sffc_dashboard_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        $user_id = get_current_user_id();
        $page = isset($_POST['page']) ? intval($_POST['page']) : 2;
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 12;

        // Get matching jobs with pagination
        $offset = ($page - 1) * $per_page;
        $matching_jobs = $this->get_matching_jobs_for_carousel($user_id, 100);

        // Slice for pagination
        $paged_jobs = array_slice($matching_jobs, $offset, $per_page);
        $has_more = count($matching_jobs) > ($offset + $per_page);

        // Render job cards
        $job_cards = array();
        foreach ($paged_jobs as $job) {
            $job_cards[] = $this->render_job_match_card($job);
        }

        wp_send_json_success(array(
            'jobs' => $job_cards,
            'has_more' => $has_more,
            'total' => count($matching_jobs),
            'page' => $page
        ));
    }

    /**
     * AJAX: Get dashboard stats
     */
    public function ajax_get_dashboard_stats() {
        check_ajax_referer('sffc_dashboard_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        $user_id = get_current_user_id();
        $data_manager = $this->get_data_manager();

        $stats = $data_manager->get_overview_stats($user_id);

        wp_send_json_success($stats);
    }

    /**
     * AJAX: Log interaction (networking/recruiter)
     */
    public function ajax_log_interaction() {
        check_ajax_referer('sffc_dashboard_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        $user_id = get_current_user_id();
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';

        if (!in_array($type, array('networking', 'recruiter', 'referral'))) {
            wp_send_json_error(array('message' => 'Invalid interaction type'));
        }

        $data = array(
            'contact_name' => isset($_POST['contact_name']) ? sanitize_text_field($_POST['contact_name']) : '',
            'contact_email' => isset($_POST['contact_email']) ? sanitize_email($_POST['contact_email']) : '',
            'company' => isset($_POST['company']) ? sanitize_text_field($_POST['company']) : '',
            'job_id' => isset($_POST['job_id']) ? intval($_POST['job_id']) : null,
            'notes' => isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : '',
            'follow_up_date' => isset($_POST['follow_up_date']) ? sanitize_text_field($_POST['follow_up_date']) : null
        );

        $data_manager = $this->get_data_manager();
        $result = $data_manager->log_interaction($user_id, $type, $data);

        if ($result) {
            $counts = $data_manager->get_interaction_counts($user_id);
            wp_send_json_success(array(
                'id' => $result,
                'counts' => $counts
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to log interaction'));
        }
    }

    /**
     * AJAX: Track job apply click from job board
     * Creates an application record when user clicks apply button
     */
    public function ajax_track_job_apply() {
        // Allow both logged-in and non-logged-in users to trigger, but only track for logged-in
        if (!is_user_logged_in()) {
            wp_send_json_success(array('tracked' => false, 'reason' => 'not_logged_in'));
            return;
        }

        $user_id = get_current_user_id();
        $job_id = isset($_POST['job_id']) ? intval($_POST['job_id']) : 0;

        if (!$job_id) {
            wp_send_json_error(array('message' => 'No job ID provided'));
            return;
        }

        $data_manager = $this->get_data_manager();

        // Check if already tracked
        $existing = $data_manager->get_job_stage($user_id, $job_id);

        if ($existing) {
            // Already tracked, don't duplicate
            wp_send_json_success(array('tracked' => false, 'reason' => 'already_tracked'));
            return;
        }

        // Save with 'applied' stage
        $extra_data = array(
            'source' => 'job_board_click',
            'company_name' => isset($_POST['company']) ? sanitize_text_field($_POST['company']) : '',
            'job_title' => isset($_POST['job_title']) ? sanitize_text_field($_POST['job_title']) : '',
            'location' => isset($_POST['location']) ? sanitize_text_field($_POST['location']) : ''
        );

        $result = $data_manager->save_job_stage($user_id, $job_id, 'applied', $extra_data);

        if ($result['success']) {
            wp_send_json_success(array('tracked' => true));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }

    // =========================================
    // Unified Profile System
    // =========================================

    /**
     * Render the unified profile page
     * This combines missing fields and audit mode into one seamless experience
     */
    public function render_unified_profile($atts = array()) {
        if (!is_user_logged_in()) {
            return '<div class="sffc-login-required">
                <p>Please <a href="' . wp_login_url(get_permalink()) . '">log in</a> to access your profile.</p>
            </div>';
        }

        $user_id = get_current_user_id();
        $user = wp_get_current_user();
        $profile_data = $this->get_audit_profile($user_id);

        // Enqueue necessary scripts
        wp_enqueue_script('sffc-unified-profile', plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/js/unified-profile.js', array('jquery'), '1.0.0', true);
        wp_localize_script('sffc-unified-profile', 'sffc_profile', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sffc_dashboard_nonce'),
            'user_id' => $user_id
        ));

        ob_start();
        ?>
        <div class="sffc-unified-profile" data-user-id="<?php echo esc_attr($user_id); ?>">
            <!-- Profile Header -->
            <div class="sffc-profile-header">
                <div class="sffc-profile-avatar">
                    <?php echo get_avatar($user_id, 80); ?>
                </div>
                <div class="sffc-profile-intro">
                    <h1>Your Career Profile</h1>
                    <p>Complete your profile to get better job matches and personalized recommendations.</p>
                </div>
                <div class="sffc-profile-completion">
                    <div class="sffc-completion-ring">
                        <svg viewBox="0 0 36 36">
                            <path class="sffc-ring-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                            <path class="sffc-ring-fill" stroke-dasharray="<?php echo intval($profile_data['completion_percentage']); ?>, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                        </svg>
                        <span class="sffc-completion-text"><?php echo intval($profile_data['completion_percentage']); ?>%</span>
                    </div>
                    <span class="sffc-completion-label">Complete</span>
                </div>
            </div>

            <!-- Basic Information Section -->
            <div class="sffc-profile-section" id="sffc-basic-info">
                <div class="sffc-section-header">
                    <h2>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                        Basic Information
                    </h2>
                    <button type="button" class="sffc-edit-section-btn" data-section="basic">Edit</button>
                </div>
                <div class="sffc-section-content">
                    <div class="sffc-profile-field">
                        <label>Full Name</label>
                        <input type="text" name="full_name" value="<?php echo esc_attr($profile_data['full_name'] ?: $user->display_name); ?>" class="sffc-profile-input" data-field="full_name"/>
                    </div>
                    <div class="sffc-profile-field">
                        <label>Current Role</label>
                        <input type="text" name="current_role" value="<?php echo esc_attr($profile_data['current_role']); ?>" placeholder="e.g., Associate, Analyst, VP" class="sffc-profile-input" data-field="current_role"/>
                    </div>
                    <div class="sffc-profile-field">
                        <label>Years of Experience</label>
                        <select name="years_experience" class="sffc-profile-select" data-field="years_experience">
                            <option value="">Select...</option>
                            <option value="0-1" <?php selected($profile_data['years_experience'], '0-1'); ?>>Less than 1 year</option>
                            <option value="1-2" <?php selected($profile_data['years_experience'], '1-2'); ?>>1-2 years</option>
                            <option value="2-3" <?php selected($profile_data['years_experience'], '2-3'); ?>>2-3 years</option>
                            <option value="3-5" <?php selected($profile_data['years_experience'], '3-5'); ?>>3-5 years</option>
                            <option value="5-7" <?php selected($profile_data['years_experience'], '5-7'); ?>>5-7 years</option>
                            <option value="7-10" <?php selected($profile_data['years_experience'], '7-10'); ?>>7-10 years</option>
                            <option value="10+" <?php selected($profile_data['years_experience'], '10+'); ?>>10+ years</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Work Preferences Section -->
            <div class="sffc-profile-section" id="sffc-work-prefs">
                <div class="sffc-section-header">
                    <h2>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/>
                            <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                        </svg>
                        Work Preferences
                    </h2>
                </div>
                <div class="sffc-section-content">
                    <div class="sffc-profile-field">
                        <label>Work Preference</label>
                        <div class="sffc-option-buttons" data-field="work_preference">
                            <button type="button" class="sffc-option-btn <?php echo $profile_data['work_preference'] === 'remote' ? 'active' : ''; ?>" data-value="remote">Remote</button>
                            <button type="button" class="sffc-option-btn <?php echo $profile_data['work_preference'] === 'hybrid' ? 'active' : ''; ?>" data-value="hybrid">Hybrid</button>
                            <button type="button" class="sffc-option-btn <?php echo $profile_data['work_preference'] === 'onsite' ? 'active' : ''; ?>" data-value="onsite">On-site</button>
                            <button type="button" class="sffc-option-btn <?php echo $profile_data['work_preference'] === 'flexible' ? 'active' : ''; ?>" data-value="flexible">Flexible</button>
                        </div>
                    </div>
                    <div class="sffc-profile-field">
                        <label>Relocation Preference</label>
                        <div class="sffc-option-buttons" data-field="relocation_preference">
                            <button type="button" class="sffc-option-btn <?php echo $profile_data['relocation_preference'] === 'yes' ? 'active' : ''; ?>" data-value="yes">Open to Relocating</button>
                            <button type="button" class="sffc-option-btn <?php echo $profile_data['relocation_preference'] === 'maybe' ? 'active' : ''; ?>" data-value="maybe">For the Right Role</button>
                            <button type="button" class="sffc-option-btn <?php echo $profile_data['relocation_preference'] === 'no' ? 'active' : ''; ?>" data-value="no">Not Relocating</button>
                        </div>
                    </div>
                    <div class="sffc-profile-field">
                        <label>Target Locations</label>
                        <input type="text" name="target_locations" value="<?php echo esc_attr(is_array($profile_data['target_locations']) ? implode(', ', $profile_data['target_locations']) : $profile_data['target_locations']); ?>" placeholder="e.g., London, New York, Dubai" class="sffc-profile-input" data-field="target_locations"/>
                        <span class="sffc-field-hint">Separate multiple locations with commas</span>
                    </div>
                </div>
            </div>

            <!-- Career Goals Section -->
            <div class="sffc-profile-section" id="sffc-career-goals">
                <div class="sffc-section-header">
                    <h2>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <circle cx="12" cy="12" r="6"/>
                            <circle cx="12" cy="12" r="2"/>
                        </svg>
                        Career Goals
                    </h2>
                </div>
                <div class="sffc-section-content">
                    <div class="sffc-profile-field">
                        <label>Target Industries</label>
                        <div class="sffc-checkbox-group" data-field="target_industries">
                            <?php
                            $industries = array('Private Equity', 'Investment Banking', 'Hedge Funds', 'Asset Management', 'Venture Capital', 'Consulting', 'Corporate Finance', 'Real Estate', 'Infrastructure', 'Credit');
                            $selected_industries = is_array($profile_data['target_industries']) ? $profile_data['target_industries'] : array();
                            foreach ($industries as $industry):
                            ?>
                            <label class="sffc-checkbox-label">
                                <input type="checkbox" name="target_industries[]" value="<?php echo esc_attr($industry); ?>" <?php checked(in_array($industry, $selected_industries)); ?>/>
                                <span><?php echo esc_html($industry); ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="sffc-profile-field">
                        <label>Career Goals</label>
                        <textarea name="career_goals" class="sffc-profile-textarea" data-field="career_goals" placeholder="What are you looking for in your next role?"><?php echo esc_textarea($profile_data['career_goals']); ?></textarea>
                    </div>
                    <div class="sffc-profile-field">
                        <label>Salary Expectation</label>
                        <select name="salary_expectation" class="sffc-profile-select" data-field="salary_expectation">
                            <option value="">Select range...</option>
                            <option value="50-75k" <?php selected($profile_data['salary_expectation'], '50-75k'); ?>>£50,000 - £75,000</option>
                            <option value="75-100k" <?php selected($profile_data['salary_expectation'], '75-100k'); ?>>£75,000 - £100,000</option>
                            <option value="100-150k" <?php selected($profile_data['salary_expectation'], '100-150k'); ?>>£100,000 - £150,000</option>
                            <option value="150-200k" <?php selected($profile_data['salary_expectation'], '150-200k'); ?>>£150,000 - £200,000</option>
                            <option value="200-300k" <?php selected($profile_data['salary_expectation'], '200-300k'); ?>>£200,000 - £300,000</option>
                            <option value="300k+" <?php selected($profile_data['salary_expectation'], '300k+'); ?>>£300,000+</option>
                        </select>
                    </div>
                    <div class="sffc-profile-field">
                        <label>Availability</label>
                        <select name="availability" class="sffc-profile-select" data-field="availability">
                            <option value="">Select...</option>
                            <option value="immediately" <?php selected($profile_data['availability'], 'immediately'); ?>>Immediately</option>
                            <option value="2-weeks" <?php selected($profile_data['availability'], '2-weeks'); ?>>2 weeks notice</option>
                            <option value="1-month" <?php selected($profile_data['availability'], '1-month'); ?>>1 month notice</option>
                            <option value="2-months" <?php selected($profile_data['availability'], '2-months'); ?>>2 months notice</option>
                            <option value="3-months" <?php selected($profile_data['availability'], '3-months'); ?>>3 months notice</option>
                            <option value="not-looking" <?php selected($profile_data['availability'], 'not-looking'); ?>>Not actively looking</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Skills Assessment Section (From Audit) -->
            <div class="sffc-profile-section" id="sffc-skills-section">
                <div class="sffc-section-header">
                    <h2>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                        </svg>
                        Skills Assessment
                    </h2>
                    <a href="<?php echo esc_url(home_url('/smart-apply/')); ?>" class="sffc-start-audit-btn">
                        <?php echo empty($profile_data['skills_proficiency']) ? 'Take Assessment' : 'Retake Assessment'; ?>
                    </a>
                </div>
                <div class="sffc-section-content">
                    <?php if (!empty($profile_data['skills_proficiency']) && is_array($profile_data['skills_proficiency'])): ?>
                    <div class="sffc-skills-grid">
                        <?php foreach ($profile_data['skills_proficiency'] as $skill => $level): ?>
                        <div class="sffc-skill-item">
                            <span class="sffc-skill-name"><?php echo esc_html($skill); ?></span>
                            <span class="sffc-skill-level sffc-level-<?php echo esc_attr(strtolower($level)); ?>"><?php echo esc_html($level); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="sffc-empty-state">
                        <p>Complete a Smart message assessment to see your skills profile here.</p>
                        <a href="<?php echo esc_url(home_url('/jobs/')); ?>" class="sffc-btn sffc-btn-primary">Browse Jobs & Assess Fit</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Save Button -->
            <div class="sffc-profile-actions">
                <button type="button" class="sffc-btn sffc-btn-primary sffc-save-profile-btn" id="sffc-save-profile">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                        <polyline points="17 21 17 13 7 13 7 21"/>
                        <polyline points="7 3 7 8 15 8"/>
                    </svg>
                    Save Profile
                </button>
                <a href="<?php echo esc_url(home_url('/career-dashboard/')); ?>" class="sffc-btn sffc-btn-secondary">
                    Back to Dashboard
                </a>
            </div>
        </div>

        <style>
        /* Unified Profile Styles */
        .sffc-unified-profile {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .sffc-profile-header {
            display: flex;
            align-items: center;
            gap: 24px;
            padding: 32px;
            background: linear-gradient(135deg, #0d6efd 0%, #1e3a5f 100%);
            border-radius: 16px;
            color: #ffffff;
            margin-bottom: 32px;
        }

        .sffc-profile-avatar img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 3px solid rgba(255,255,255,0.3);
        }

        .sffc-profile-intro {
            flex: 1;
        }

        .sffc-profile-intro h1 {
            margin: 0 0 8px;
            font-size: 24px;
            font-weight: 600;
        }

        .sffc-profile-intro p {
            margin: 0;
            opacity: 0.9;
            font-size: 14px;
        }

        .sffc-profile-completion {
            text-align: center;
        }

        .sffc-completion-ring {
            width: 64px;
            height: 64px;
            position: relative;
        }

        .sffc-completion-ring svg {
            transform: rotate(-90deg);
        }

        .sffc-ring-bg {
            fill: none;
            stroke: rgba(255,255,255,0.2);
            stroke-width: 3;
        }

        .sffc-ring-fill {
            fill: none;
            stroke: #10b981;
            stroke-width: 3;
            stroke-linecap: round;
        }

        .sffc-completion-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 14px;
            font-weight: 600;
        }

        .sffc-completion-label {
            font-size: 12px;
            opacity: 0.8;
        }

        .sffc-profile-section {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            margin-bottom: 24px;
            overflow: hidden;
        }

        .sffc-section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 24px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }

        .sffc-section-header h2 {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
        }

        .sffc-section-header h2 svg {
            color: #0d6efd;
        }

        .sffc-edit-section-btn,
        .sffc-start-audit-btn {
            padding: 8px 16px;
            background: #0d6efd;
            color: #ffffff;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.2s;
        }

        .sffc-edit-section-btn:hover,
        .sffc-start-audit-btn:hover {
            background: #0b5ed7;
        }

        .sffc-section-content {
            padding: 24px;
        }

        .sffc-profile-field {
            margin-bottom: 20px;
        }

        .sffc-profile-field:last-child {
            margin-bottom: 0;
        }

        .sffc-profile-field label {
            display: block;
            margin-bottom: 8px;
            font-size: 13px;
            font-weight: 600;
            color: #475569;
        }

        .sffc-profile-input,
        .sffc-profile-select,
        .sffc-profile-textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            color: #1e293b;
            transition: border-color 0.2s, box-shadow 0.2s;
            box-sizing: border-box;
        }

        .sffc-profile-input:focus,
        .sffc-profile-select:focus,
        .sffc-profile-textarea:focus {
            outline: none;
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1);
        }

        .sffc-profile-textarea {
            min-height: 100px;
            resize: vertical;
        }

        .sffc-field-hint {
            display: block;
            margin-top: 6px;
            font-size: 12px;
            color: #64748b;
        }

        .sffc-option-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .sffc-option-btn {
            padding: 10px 20px;
            background: #f1f5f9;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            color: #475569;
            cursor: pointer;
            transition: all 0.2s;
        }

        .sffc-option-btn:hover {
            background: #e2e8f0;
            border-color: #cbd5e1;
        }

        .sffc-option-btn.active {
            background: #0d6efd;
            border-color: #0d6efd;
            color: #ffffff;
        }

        .sffc-checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 12px;
        }

        .sffc-checkbox-label {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .sffc-checkbox-label:hover {
            background: #f1f5f9;
        }

        .sffc-checkbox-label input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #0d6efd;
        }

        .sffc-checkbox-label span {
            font-size: 13px;
            color: #475569;
        }

        .sffc-skills-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
        }

        .sffc-skill-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            background: #f8fafc;
            border-radius: 8px;
        }

        .sffc-skill-name {
            font-size: 14px;
            font-weight: 500;
            color: #1e293b;
        }

        .sffc-skill-level {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .sffc-skill-level.sffc-level-expert { background: #dcfce7; color: #166534; }
        .sffc-skill-level.sffc-level-advanced { background: #dbeafe; color: #1e40af; }
        .sffc-skill-level.sffc-level-intermediate { background: #fef3c7; color: #92400e; }
        .sffc-skill-level.sffc-level-basic { background: #f3f4f6; color: #6b7280; }

        .sffc-empty-state {
            text-align: center;
            padding: 32px;
            color: #64748b;
        }

        .sffc-empty-state p {
            margin-bottom: 16px;
        }

        .sffc-profile-actions {
            display: flex;
            gap: 16px;
            justify-content: center;
            padding: 24px 0;
        }

        .sffc-save-profile-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 32px;
            background: #0d6efd;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .sffc-save-profile-btn:hover {
            background: #0b5ed7;
        }

        .sffc-btn-secondary {
            padding: 14px 32px;
            background: #f1f5f9;
            color: #475569;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 500;
            text-decoration: none;
            transition: background 0.2s;
        }

        .sffc-btn-secondary:hover {
            background: #e2e8f0;
        }

        @media (max-width: 640px) {
            .sffc-profile-header {
                flex-direction: column;
                text-align: center;
            }

            .sffc-option-buttons {
                flex-direction: column;
            }

            .sffc-checkbox-group {
                grid-template-columns: 1fr;
            }

            .sffc-profile-actions {
                flex-direction: column;
            }
        }
        </style>
        <?php
        return ob_get_clean();
    }

    /**
     * Get audit profile data for a user
     */
    private function get_audit_profile($user_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'sffc_user_audit_profile';
        $profile = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d",
            $user_id
        ), ARRAY_A);

        // Default structure if no profile exists
        $defaults = array(
            'user_id' => $user_id,
            'full_name' => '',
            'current_role' => '',
            'years_experience' => '',
            'work_preference' => '',
            'target_industries' => array(),
            'target_locations' => array(),
            'skills_proficiency' => array(),
            'qualifications' => array(),
            'career_goals' => '',
            'salary_expectation' => '',
            'availability' => '',
            'relocation_preference' => '',
            'remote_preference' => '',
            'audit_responses' => array(),
            'profile_completed' => 0,
            'completion_percentage' => 0
        );

        if (!$profile) {
            return $defaults;
        }

        // Decode JSON fields
        $json_fields = array('target_industries', 'target_locations', 'skills_proficiency', 'qualifications', 'audit_responses');
        foreach ($json_fields as $field) {
            if (!empty($profile[$field])) {
                $decoded = json_decode($profile[$field], true);
                $profile[$field] = is_array($decoded) ? $decoded : array();
            } else {
                $profile[$field] = array();
            }
        }

        return array_merge($defaults, $profile);
    }

    /**
     * Calculate profile completion percentage
     */
    private function calculate_completion_percentage($profile_data) {
        $fields = array(
            'full_name' => 10,
            'current_role' => 10,
            'years_experience' => 10,
            'work_preference' => 10,
            'target_industries' => 15,
            'target_locations' => 10,
            'career_goals' => 10,
            'salary_expectation' => 5,
            'availability' => 5,
            'relocation_preference' => 5,
            'skills_proficiency' => 10
        );

        $total = 0;
        foreach ($fields as $field => $weight) {
            $value = $profile_data[$field] ?? '';
            if (is_array($value)) {
                if (!empty($value)) {
                    $total += $weight;
                }
            } else {
                if (!empty($value)) {
                    $total += $weight;
                }
            }
        }

        return min(100, $total);
    }

    /**
     * AJAX: Save audit profile
     */
    public function ajax_save_audit_profile() {
        check_ajax_referer('sffc_dashboard_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not authenticated'));
            return;
        }

        $user_id = get_current_user_id();

        // Collect and sanitize profile data
        $profile_data = array(
            'full_name' => isset($_POST['full_name']) ? sanitize_text_field($_POST['full_name']) : '',
            'current_role' => isset($_POST['current_role']) ? sanitize_text_field($_POST['current_role']) : '',
            'years_experience' => isset($_POST['years_experience']) ? sanitize_text_field($_POST['years_experience']) : '',
            'work_preference' => isset($_POST['work_preference']) ? sanitize_text_field($_POST['work_preference']) : '',
            'target_industries' => isset($_POST['target_industries']) ? array_map('sanitize_text_field', (array)$_POST['target_industries']) : array(),
            'target_locations' => isset($_POST['target_locations']) ? sanitize_text_field($_POST['target_locations']) : '',
            'career_goals' => isset($_POST['career_goals']) ? sanitize_textarea_field($_POST['career_goals']) : '',
            'salary_expectation' => isset($_POST['salary_expectation']) ? sanitize_text_field($_POST['salary_expectation']) : '',
            'availability' => isset($_POST['availability']) ? sanitize_text_field($_POST['availability']) : '',
            'relocation_preference' => isset($_POST['relocation_preference']) ? sanitize_text_field($_POST['relocation_preference']) : '',
            'remote_preference' => isset($_POST['remote_preference']) ? sanitize_text_field($_POST['remote_preference']) : '',
        );

        // Handle skills_proficiency if provided (from audit)
        if (isset($_POST['skills_proficiency'])) {
            $skills = $_POST['skills_proficiency'];
            if (is_string($skills)) {
                $skills = json_decode(stripslashes($skills), true);
            }
            $profile_data['skills_proficiency'] = is_array($skills) ? $skills : array();
        }

        // Handle audit_responses if provided
        if (isset($_POST['audit_responses'])) {
            $responses = $_POST['audit_responses'];
            if (is_string($responses)) {
                $responses = json_decode(stripslashes($responses), true);
            }
            $profile_data['audit_responses'] = is_array($responses) ? $responses : array();
        }

        // Handle job_id from audit
        if (isset($_POST['job_id'])) {
            $profile_data['last_audit_job_id'] = intval($_POST['job_id']);
        }

        // Calculate completion
        $profile_data['completion_percentage'] = $this->calculate_completion_percentage($profile_data);
        $profile_data['profile_completed'] = $profile_data['completion_percentage'] >= 80 ? 1 : 0;

        // Prepare data for database
        global $wpdb;
        $table = $wpdb->prefix . 'sffc_user_audit_profile';

        // Encode JSON fields
        $json_fields = array('target_industries', 'skills_proficiency', 'audit_responses');
        $db_data = $profile_data;
        foreach ($json_fields as $field) {
            if (isset($db_data[$field]) && is_array($db_data[$field])) {
                $db_data[$field] = json_encode($db_data[$field]);
            }
        }

        // Handle target_locations - convert string to JSON array
        if (!empty($db_data['target_locations']) && !is_array($db_data['target_locations'])) {
            $locations = array_map('trim', explode(',', $db_data['target_locations']));
            $db_data['target_locations'] = json_encode(array_filter($locations));
        }

        // Check if profile exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE user_id = %d",
            $user_id
        ));

        if ($existing) {
            // Update
            $result = $wpdb->update(
                $table,
                $db_data,
                array('user_id' => $user_id)
            );
        } else {
            // Insert
            $db_data['user_id'] = $user_id;
            $result = $wpdb->insert($table, $db_data);
        }

        if ($result !== false) {
            wp_send_json_success(array(
                'message' => 'Profile saved successfully',
                'completion_percentage' => $profile_data['completion_percentage']
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to save profile: ' . $wpdb->last_error));
        }
    }

    /**
     * AJAX: Get audit profile
     */
    public function ajax_get_audit_profile() {
        check_ajax_referer('sffc_dashboard_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not authenticated'));
            return;
        }

        $user_id = get_current_user_id();
        $profile = $this->get_audit_profile($user_id);

        wp_send_json_success($profile);
    }

    /**
     * AJAX: Add contact (networking or recruiter)
     */
    public function ajax_add_contact() {
        check_ajax_referer('sffc_dashboard_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not authenticated'));
            return;
        }

        $user_id = get_current_user_id();

        $data = array(
            'user_id' => $user_id,
            'interaction_type' => sanitize_text_field($_POST['interaction_type'] ?? 'networking'),
            'contact_name' => sanitize_text_field($_POST['contact_name'] ?? ''),
            'contact_email' => sanitize_email($_POST['contact_email'] ?? ''),
            'company' => sanitize_text_field($_POST['company'] ?? ''),
            'status' => sanitize_text_field($_POST['status'] ?? 'pending'),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
            'follow_up_date' => sanitize_text_field($_POST['follow_up_date'] ?? null),
            'interaction_date' => current_time('mysql')
        );

        // Validate required fields
        if (empty($data['contact_name'])) {
            wp_send_json_error(array('message' => 'Contact name is required'));
            return;
        }

        // Validate interaction type
        if (!in_array($data['interaction_type'], array('networking', 'recruiter', 'referral'))) {
            wp_send_json_error(array('message' => 'Invalid interaction type'));
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'sffc_interactions';

        $result = $wpdb->insert($table_name, $data);

        if ($result) {
            wp_send_json_success(array(
                'message' => 'Contact added successfully',
                'contact_id' => $wpdb->insert_id
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to add contact'));
        }
    }

    /**
     * AJAX: Update contact
     */
    public function ajax_update_contact() {
        check_ajax_referer('sffc_dashboard_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not authenticated'));
            return;
        }

        $user_id = get_current_user_id();
        $contact_id = intval($_POST['contact_id'] ?? 0);

        if (!$contact_id) {
            wp_send_json_error(array('message' => 'Invalid contact ID'));
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'sffc_interactions';

        // Verify ownership
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d AND user_id = %d",
            $contact_id,
            $user_id
        ));

        if (!$existing) {
            wp_send_json_error(array('message' => 'Contact not found'));
            return;
        }

        $data = array(
            'contact_name' => sanitize_text_field($_POST['contact_name'] ?? ''),
            'contact_email' => sanitize_email($_POST['contact_email'] ?? ''),
            'company' => sanitize_text_field($_POST['company'] ?? ''),
            'status' => sanitize_text_field($_POST['status'] ?? 'pending'),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
            'follow_up_date' => sanitize_text_field($_POST['follow_up_date'] ?? null)
        );

        // Handle empty follow_up_date
        if (empty($data['follow_up_date'])) {
            $data['follow_up_date'] = null;
        }

        $result = $wpdb->update(
            $table_name,
            $data,
            array('id' => $contact_id, 'user_id' => $user_id)
        );

        if ($result !== false) {
            wp_send_json_success(array('message' => 'Contact updated successfully'));
        } else {
            wp_send_json_error(array('message' => 'Failed to update contact'));
        }
    }

    /**
     * AJAX: Delete contact
     */
    public function ajax_delete_contact() {
        check_ajax_referer('sffc_dashboard_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not authenticated'));
            return;
        }

        $user_id = get_current_user_id();
        $contact_id = intval($_POST['contact_id'] ?? 0);

        if (!$contact_id) {
            wp_send_json_error(array('message' => 'Invalid contact ID'));
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'sffc_interactions';

        // Delete only if owned by user
        $result = $wpdb->delete(
            $table_name,
            array('id' => $contact_id, 'user_id' => $user_id)
        );

        if ($result) {
            wp_send_json_success(array('message' => 'Contact deleted successfully'));
        } else {
            wp_send_json_error(array('message' => 'Failed to delete contact'));
        }
    }

    /**
     * AJAX: Get single contact details
     */
    public function ajax_get_contact() {
        check_ajax_referer('sffc_dashboard_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not authenticated'));
            return;
        }

        $user_id = get_current_user_id();
        $contact_id = intval($_POST['contact_id'] ?? 0);

        if (!$contact_id) {
            wp_send_json_error(array('message' => 'Invalid contact ID'));
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'sffc_interactions';

        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d AND user_id = %d",
            $contact_id,
            $user_id
        ), ARRAY_A);

        if ($contact) {
            wp_send_json_success($contact);
        } else {
            wp_send_json_error(array('message' => 'Contact not found'));
        }
    }

    /**
     * AJAX: Reload dashboard section
     */
    public function ajax_reload_section() {
        check_ajax_referer('sffc_dashboard_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not authenticated'));
            return;
        }

        $user_id = get_current_user_id();
        $section = sanitize_text_field($_POST['section'] ?? '');

        $html = '';

        switch ($section) {
            case 'networking':
                $html = $this->render_networking_section($user_id);
                break;
            case 'recruiters':
                $html = $this->render_recruiters_section($user_id);
                break;
            case 'my_profile':
                $html = $this->render_my_profile_section($user_id);
                break;
            default:
                wp_send_json_error(array('message' => 'Invalid section'));
                return;
        }

        wp_send_json_success(array('html' => $html));
    }

    /**
     * Render the My Profile section
     * Displays user's career profile from onboarding data
     * Table uses JSON columns: professional_data, preferences, career_goals
     */
    private function render_my_profile_section($user_id) {
        global $wpdb;

        $user = get_userdata($user_id);
        $profile_table = $wpdb->prefix . 'sffc_user_profiles';
        $skills_table = $wpdb->prefix . 'sffc_user_skills';

        // Get raw profile row from database
        $profile_row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $profile_table WHERE user_id = %d",
            $user_id
        ), ARRAY_A);

        // Parse JSON columns into usable arrays
        $professional_data = array();
        $preferences = array();
        $career_goals_data = array();

        if ($profile_row) {
            $professional_data = !empty($profile_row['professional_data'])
                ? json_decode($profile_row['professional_data'], true)
                : array();
            $preferences = !empty($profile_row['preferences'])
                ? json_decode($profile_row['preferences'], true)
                : array();
            $career_goals_data = !empty($profile_row['career_goals'])
                ? json_decode($profile_row['career_goals'], true)
                : array();
        }

        // Ensure arrays
        $professional_data = is_array($professional_data) ? $professional_data : array();
        $preferences = is_array($preferences) ? $preferences : array();
        $career_goals_data = is_array($career_goals_data) ? $career_goals_data : array();

        // Get skills
        $skills = $wpdb->get_results($wpdb->prepare(
            "SELECT skill_name, proficiency_level, skill_category, source FROM $skills_table WHERE user_id = %d ORDER BY created_at DESC",
            $user_id
        ), ARRAY_A);

        // Also check audit profile for additional data (fallback)
        $audit_profile = $this->get_audit_profile($user_id);

        // Calculate completion from user meta (set by onboarding)
        $completion = get_user_meta($user_id, 'sffc_profile_completion', true);
        if (empty($completion)) {
            $completion = $this->calculate_my_profile_completion($professional_data, $preferences, $skills, $audit_profile);
        }

        // Check if onboarding was completed
        $onboarding_complete = get_user_meta($user_id, 'sffc_onboarding_completed', true);

        // Get display name - prioritize profile full_name, then WordPress display name
        $display_name = !empty($professional_data['full_name']) ? $professional_data['full_name'] :
                       (!empty($audit_profile['full_name']) ? $audit_profile['full_name'] : $user->display_name);

        // Get current role/title
        $current_title = !empty($professional_data['current_title']) ? $professional_data['current_title'] :
                        (!empty($audit_profile['current_role']) ? $audit_profile['current_role'] : '');

        // Get company
        $current_company = !empty($professional_data['current_company']) ? $professional_data['current_company'] : '';

        // Get experience
        $experience = !empty($professional_data['years_experience']) ? $professional_data['years_experience'] :
                     (!empty($audit_profile['years_experience']) ? $audit_profile['years_experience'] : '');

        // Get career stage
        $career_stage = !empty($professional_data['career_stage']) ? $professional_data['career_stage'] : '';

        // Get locations - from preferences or audit fallback
        $locations = !empty($preferences['preferred_locations']) ? $preferences['preferred_locations'] :
                    (!empty($audit_profile['target_locations']) ? $audit_profile['target_locations'] : array());
        if (is_string($locations)) {
            $locations = json_decode($locations, true) ?: array();
        }

        // Get industries - from preferences or audit fallback
        $industries = !empty($preferences['preferred_industries']) ? $preferences['preferred_industries'] :
                     (!empty($audit_profile['target_industries']) ? $audit_profile['target_industries'] : array());
        if (is_string($industries)) {
            $industries = json_decode($industries, true) ?: array();
        }

        // Get work environment preference
        $work_environment = !empty($preferences['preferred_work_environment']) ? $preferences['preferred_work_environment'] :
                           (!empty($audit_profile['work_preference']) ? $audit_profile['work_preference'] : '');

        // Get relocation preference
        $relocation = !empty($preferences['open_to_relocation']) ? $preferences['open_to_relocation'] :
                     (!empty($audit_profile['relocation_preference']) ? $audit_profile['relocation_preference'] : '');

        // Get notice period/availability
        $notice_period = !empty($preferences['notice_period']) ? $preferences['notice_period'] :
                        (!empty($audit_profile['availability']) ? $audit_profile['availability'] : '');

        // Get salary expectations
        $salary_min = !empty($preferences['salary_target_min']) ? $preferences['salary_target_min'] : '';
        $salary_max = !empty($preferences['salary_target_max']) ? $preferences['salary_target_max'] : '';
        $salary_audit = !empty($audit_profile['salary_expectation']) ? $audit_profile['salary_expectation'] : '';

        // Get target role level
        $target_role = !empty($professional_data['target_role_level']) ? $professional_data['target_role_level'] : '';

        // Get career goals text
        $career_goals_text = !empty($career_goals_data['goals_text']) ? $career_goals_data['goals_text'] :
                            (!empty($audit_profile['career_goals']) ? $audit_profile['career_goals'] : '');

        ob_start();
        ?>
        <div class="sffc-my-profile-section">
            <!-- Profile Header -->
            <div class="sffc-profile-hero">
                <div class="sffc-profile-hero-content">
                    <div class="sffc-profile-avatar-large">
                        <?php echo get_avatar($user_id, 120); ?>
                        <div class="sffc-avatar-edit-overlay">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                                <circle cx="12" cy="13" r="4"/>
                            </svg>
                        </div>
                    </div>
                    <div class="sffc-profile-hero-info">
                        <h1 class="sffc-profile-name"><?php echo esc_html($display_name); ?></h1>
                        <?php if (!empty($current_title)): ?>
                        <p class="sffc-profile-title"><?php echo esc_html($current_title); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($current_company)): ?>
                        <p class="sffc-profile-company">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                                <polyline points="9 22 9 12 15 12 15 22"/>
                            </svg>
                            <?php echo esc_html($current_company); ?>
                        </p>
                        <?php endif; ?>
                        <?php if (!empty($locations)): ?>
                        <p class="sffc-profile-location">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                <circle cx="12" cy="10" r="3"/>
                            </svg>
                            <?php echo esc_html(is_array($locations) ? implode(', ', array_map(array($this, 'format_location_name'), $locations)) : $locations); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    <div class="sffc-profile-completion-ring">
                        <svg viewBox="0 0 36 36" class="sffc-circular-chart">
                            <path class="sffc-circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                            <path class="sffc-circle-fill" stroke-dasharray="<?php echo esc_attr($completion); ?>, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                            <text x="18" y="20.35" class="sffc-percentage"><?php echo esc_html($completion); ?>%</text>
                        </svg>
                        <span class="sffc-completion-label">Profile Complete</span>
                    </div>
                </div>
                <?php if (!$onboarding_complete || $completion < 100): ?>
                <div class="sffc-profile-cta">
                    <a href="<?php echo esc_url(home_url('/career-onboarding/')); ?>" class="sffc-complete-profile-btn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                        </svg>
                        <?php echo $onboarding_complete ? 'Update Profile' : 'Complete Profile Setup'; ?>
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Career Details Section -->
            <div class="sffc-profile-card">
                <div class="sffc-profile-card-header">
                    <h2>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/>
                            <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                        </svg>
                        Career Details
                    </h2>
                </div>
                <div class="sffc-profile-card-body">
                    <div class="sffc-profile-grid">
                        <div class="sffc-profile-field-group">
                            <label>Current Role</label>
                            <span><?php echo !empty($current_title) ? esc_html($current_title) : '<em class="sffc-not-set">Not set</em>'; ?></span>
                        </div>
                        <div class="sffc-profile-field-group">
                            <label>Company</label>
                            <span><?php echo !empty($current_company) ? esc_html($current_company) : '<em class="sffc-not-set">Not set</em>'; ?></span>
                        </div>
                        <div class="sffc-profile-field-group">
                            <label>Experience</label>
                            <span><?php echo !empty($experience) ? esc_html($this->format_experience($experience)) : '<em class="sffc-not-set">Not set</em>'; ?></span>
                        </div>
                        <div class="sffc-profile-field-group">
                            <label>Career Stage</label>
                            <span><?php echo !empty($career_stage) ? esc_html($this->format_career_stage($career_stage)) : '<em class="sffc-not-set">Not set</em>'; ?></span>
                        </div>
                        <?php if (!empty($target_role)): ?>
                        <div class="sffc-profile-field-group">
                            <label>Target Level</label>
                            <span><?php echo esc_html($this->format_target_role($target_role)); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Target Industries -->
            <div class="sffc-profile-card">
                <div class="sffc-profile-card-header">
                    <h2>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <circle cx="12" cy="12" r="6"/>
                            <circle cx="12" cy="12" r="2"/>
                        </svg>
                        Target Industries
                    </h2>
                </div>
                <div class="sffc-profile-card-body">
                    <?php if (!empty($industries)): ?>
                    <div class="sffc-tag-list">
                        <?php foreach ($industries as $industry): ?>
                        <span class="sffc-profile-tag"><?php echo esc_html($this->format_industry_name($industry)); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p class="sffc-empty-state">No target industries set. <a href="<?php echo esc_url(home_url('/career-onboarding/')); ?>">Add industries</a></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Work Preferences -->
            <div class="sffc-profile-card">
                <div class="sffc-profile-card-header">
                    <h2>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        </svg>
                        Work Preferences
                    </h2>
                </div>
                <div class="sffc-profile-card-body">
                    <div class="sffc-profile-grid">
                        <div class="sffc-profile-field-group">
                            <label>Work Style</label>
                            <span class="sffc-preference-badge"><?php echo !empty($work_environment) ? esc_html($this->format_work_environment($work_environment)) : '<em class="sffc-not-set">Not set</em>'; ?></span>
                        </div>
                        <div class="sffc-profile-field-group">
                            <label>Relocation</label>
                            <span class="sffc-preference-badge"><?php echo !empty($relocation) ? esc_html($this->format_relocation($relocation)) : '<em class="sffc-not-set">Not set</em>'; ?></span>
                        </div>
                        <div class="sffc-profile-field-group">
                            <label>Notice Period</label>
                            <span class="sffc-preference-badge"><?php echo !empty($notice_period) ? esc_html($this->format_notice_period($notice_period)) : '<em class="sffc-not-set">Not set</em>'; ?></span>
                        </div>
                        <div class="sffc-profile-field-group">
                            <label>Salary Expectation</label>
                            <span class="sffc-preference-badge">
                                <?php
                                if (!empty($salary_min) && !empty($salary_max)) {
                                    echo esc_html($this->format_salary_range($salary_min, $salary_max));
                                } elseif (!empty($salary_audit)) {
                                    echo esc_html($this->format_salary($salary_audit));
                                } else {
                                    echo '<em class="sffc-not-set">Not set</em>';
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Skills Section -->
            <div class="sffc-profile-card">
                <div class="sffc-profile-card-header">
                    <h2>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                        </svg>
                        Skills
                    </h2>
                    <span class="sffc-skill-count"><?php echo count($skills); ?> skills</span>
                </div>
                <div class="sffc-profile-card-body">
                    <?php if (!empty($skills)): ?>
                    <div class="sffc-skills-display">
                        <?php foreach ($skills as $skill): ?>
                        <div class="sffc-skill-item">
                            <span class="sffc-skill-name"><?php echo esc_html($skill['skill_name']); ?></span>
                            <?php if (!empty($skill['proficiency_level'])): ?>
                            <span class="sffc-skill-level sffc-level-<?php echo esc_attr(strtolower($skill['proficiency_level'])); ?>">
                                <?php echo esc_html($skill['proficiency_level']); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p class="sffc-empty-state">No skills added yet. <a href="<?php echo esc_url(home_url('/career-onboarding/')); ?>">Add your skills</a></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Career Goals -->
            <?php if (!empty($career_goals_text)): ?>
            <div class="sffc-profile-card">
                <div class="sffc-profile-card-header">
                    <h2>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                            <polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                        Career Goals
                    </h2>
                </div>
                <div class="sffc-profile-card-body">
                    <p class="sffc-career-goals-text"><?php echo esc_html($career_goals_text); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Profile Actions -->
            <div class="sffc-profile-actions">
                <a href="<?php echo esc_url(home_url('/career-onboarding/')); ?>" class="sffc-action-btn sffc-btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                    Edit Profile
                </a>
                <button type="button" class="sffc-action-btn sffc-btn-secondary" id="sffc-download-profile">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="7 10 12 15 17 10"/>
                        <line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                    Export Profile
                </button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Calculate my profile completion percentage for the My Profile section
     * Uses parsed JSON data from professional_data and preferences columns
     */
    private function calculate_my_profile_completion($professional_data, $preferences, $skills, $audit_profile) {
        $total = 0;

        // Basic info (30%)
        if (!empty($professional_data['full_name']) || !empty($audit_profile['full_name'])) $total += 10;
        if (!empty($professional_data['current_title']) || !empty($audit_profile['current_role'])) $total += 10;
        if (!empty($professional_data['years_experience']) || !empty($audit_profile['years_experience'])) $total += 10;

        // Preferences (30%)
        if (!empty($preferences['preferred_work_environment']) || !empty($audit_profile['work_preference'])) $total += 10;
        $industries = $preferences['preferred_industries'] ?? $audit_profile['target_industries'] ?? array();
        if (!empty($industries) && (is_array($industries) ? count($industries) > 0 : true)) $total += 10;
        $locations = $preferences['preferred_locations'] ?? $audit_profile['target_locations'] ?? array();
        if (!empty($locations) && (is_array($locations) ? count($locations) > 0 : true)) $total += 10;

        // Skills (25%)
        if (!empty($skills)) {
            $skill_score = min(25, count($skills) * 5);
            $total += $skill_score;
        }

        // Goals/Compensation (15%)
        if (!empty($professional_data['career_stage'])) $total += 5;
        if (!empty($preferences['salary_target_min']) || !empty($audit_profile['salary_expectation'])) $total += 5;
        if (!empty($preferences['notice_period']) || !empty($audit_profile['availability'])) $total += 5;

        return min(100, $total);
    }

    /**
     * Format experience years for display
     */
    private function format_experience($exp) {
        if (empty($exp)) return 'Not specified';

        $labels = array(
            '0-1' => 'Less than 1 year',
            '1-2' => '1-2 years',
            '2-3' => '2-3 years',
            '3-5' => '3-5 years',
            '5-7' => '5-7 years',
            '7-10' => '7-10 years',
            '10+' => '10+ years'
        );

        return $labels[$exp] ?? $exp . ' years';
    }

    /**
     * Format relocation preference for display
     */
    private function format_relocation($pref) {
        if (empty($pref)) return 'Not specified';

        $labels = array(
            'yes' => 'Open to relocating',
            'maybe' => 'For the right role',
            'no' => 'Not relocating'
        );

        return $labels[$pref] ?? ucfirst($pref);
    }

    /**
     * Format availability for display
     */
    private function format_availability($avail) {
        if (empty($avail)) return 'Not specified';

        $labels = array(
            'immediately' => 'Available immediately',
            '2-weeks' => '2 weeks notice',
            '1-month' => '1 month notice',
            '2-months' => '2 months notice',
            '3-months' => '3 months notice',
            'not-looking' => 'Not actively looking'
        );

        return $labels[$avail] ?? $avail;
    }

    /**
     * Format salary expectation for display
     */
    private function format_salary($salary) {
        if (empty($salary)) return 'Not specified';

        $labels = array(
            '50-75k' => '£50,000 - £75,000',
            '75-100k' => '£75,000 - £100,000',
            '100-150k' => '£100,000 - £150,000',
            '150-200k' => '£150,000 - £200,000',
            '200-300k' => '£200,000 - £300,000',
            '300k+' => '£300,000+'
        );

        return $labels[$salary] ?? $salary;
    }

    /**
     * Format salary range for display
     */
    private function format_salary_range($min, $max) {
        if (empty($min) && empty($max)) return 'Not specified';

        $format_number = function($num) {
            $num = intval($num);
            if ($num >= 1000000) {
                return '£' . number_format($num / 1000000, 1) . 'M';
            } elseif ($num >= 1000) {
                return '£' . number_format($num / 1000) . 'k';
            }
            return '£' . number_format($num);
        };

        if (!empty($min) && !empty($max)) {
            return $format_number($min) . ' - ' . $format_number($max);
        } elseif (!empty($min)) {
            return $format_number($min) . '+';
        } else {
            return 'Up to ' . $format_number($max);
        }
    }

    /**
     * Format career stage for display
     */
    private function format_career_stage($stage) {
        if (empty($stage)) return 'Not specified';

        $labels = array(
            'student' => 'Student / Graduate',
            'analyst' => 'Analyst (0-2 years)',
            'associate' => 'Associate (2-4 years)',
            'senior_associate' => 'Senior Associate (4-6 years)',
            'vp' => 'Vice President (6-10 years)',
            'director' => 'Director / Principal',
            'md' => 'Managing Director / Partner',
            'c_level' => 'C-Level Executive'
        );

        return $labels[$stage] ?? ucwords(str_replace('_', ' ', $stage));
    }

    /**
     * Format target role level for display
     */
    private function format_target_role($role) {
        if (empty($role)) return 'Not specified';

        $labels = array(
            'same_level' => 'Same Level',
            'one_up' => 'One Level Up',
            'two_up' => 'Two Levels Up',
            'lateral_move' => 'Lateral Move',
            'flexible' => 'Open to Opportunities'
        );

        return $labels[$role] ?? ucwords(str_replace('_', ' ', $role));
    }

    /**
     * Format industry name for display (converts slug to readable name)
     */
    private function format_industry_name($industry) {
        if (empty($industry)) return '';

        $labels = array(
            'private_equity' => 'Private Equity',
            'venture_capital' => 'Venture Capital',
            'investment_banking' => 'Investment Banking',
            'hedge_funds' => 'Hedge Funds',
            'asset_management' => 'Asset Management',
            'corporate_finance' => 'Corporate Finance',
            'management_consulting' => 'Management Consulting',
            'strategy_consulting' => 'Strategy Consulting',
            'real_estate_pe' => 'Real Estate PE',
            'infrastructure' => 'Infrastructure',
            'credit_debt' => 'Credit & Debt',
            'family_office' => 'Family Office',
            'sovereign_wealth' => 'Sovereign Wealth',
            'fintech' => 'FinTech',
            'corporate_development' => 'Corporate Development'
        );

        return $labels[$industry] ?? ucwords(str_replace('_', ' ', $industry));
    }

    /**
     * Format location name for display (converts slug to readable name)
     */
    private function format_location_name($location) {
        if (empty($location)) return '';

        $labels = array(
            'london' => 'London',
            'new_york' => 'New York',
            'hong_kong' => 'Hong Kong',
            'singapore' => 'Singapore',
            'dubai' => 'Dubai',
            'paris' => 'Paris',
            'frankfurt' => 'Frankfurt',
            'zurich' => 'Zurich',
            'milan' => 'Milan',
            'amsterdam' => 'Amsterdam',
            'madrid' => 'Madrid',
            'sydney' => 'Sydney',
            'tokyo' => 'Tokyo',
            'mumbai' => 'Mumbai',
            'sao_paulo' => 'São Paulo',
            'toronto' => 'Toronto',
            'chicago' => 'Chicago',
            'los_angeles' => 'Los Angeles',
            'boston' => 'Boston',
            'san_francisco' => 'San Francisco'
        );

        return $labels[$location] ?? ucwords(str_replace('_', ' ', $location));
    }

    /**
     * Format work environment for display
     */
    private function format_work_environment($env) {
        if (empty($env)) return 'Not specified';

        $labels = array(
            'office' => 'In-Office',
            'hybrid' => 'Hybrid',
            'remote' => 'Remote',
            'flexible' => 'Flexible'
        );

        return $labels[$env] ?? ucfirst($env);
    }

    /**
     * Format notice period for display
     */
    private function format_notice_period($period) {
        if (empty($period)) return 'Not specified';

        $labels = array(
            'immediately' => 'Available Immediately',
            'immediate' => 'Available Immediately',
            '1_week' => '1 Week',
            '2_weeks' => '2 Weeks',
            '1_month' => '1 Month',
            '2_months' => '2 Months',
            '3_months' => '3 Months',
            '6_months' => '6 Months',
            'negotiable' => 'Negotiable'
        );

        return $labels[$period] ?? ucwords(str_replace('_', ' ', $period));
    }
}

// Initialize the dashboard
SFFC_Unified_Career_Dashboard::get_instance();
