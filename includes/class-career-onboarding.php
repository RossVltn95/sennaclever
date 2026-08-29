<?php
/**
 * Career Onboarding System
 *
 * McKinsey-style sequenced onboarding flow for new users.
 * Collects career profile data and stores in sffc_user_profiles.
 *
 * @package SFFC_Careers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Career_Onboarding {

    private static $instance = null;

    /**
     * Onboarding steps configuration
     */
    private $steps = array(
        1 => array(
            'id' => 'welcome',
            'title' => 'Welcome to MENA Careers',
            'subtitle' => 'Let\'s build your career profile',
            'type' => 'intro'
        ),
        2 => array(
            'id' => 'identity',
            'title' => 'Tell us about yourself',
            'subtitle' => 'The basics to get started',
            'type' => 'form',
            'fields' => array('full_name', 'current_title', 'current_company', 'years_experience')
        ),
        3 => array(
            'id' => 'career_focus',
            'title' => 'Your career focus',
            'subtitle' => 'What industries and roles interest you?',
            'type' => 'form',
            'fields' => array('preferred_industries', 'target_role_level', 'career_stage')
        ),
        4 => array(
            'id' => 'location',
            'title' => 'Location preferences',
            'subtitle' => 'Where do you want to work?',
            'type' => 'form',
            'fields' => array('preferred_locations', 'open_to_relocation', 'preferred_work_environment')
        ),
        5 => array(
            'id' => 'skills',
            'title' => 'Your expertise',
            'subtitle' => 'What are your key skills?',
            'type' => 'skills',
            'fields' => array('skills')
        ),
        6 => array(
            'id' => 'preferences',
            'title' => 'Work preferences',
            'subtitle' => 'Help us find the right fit',
            'type' => 'form',
            'fields' => array('salary_target_min', 'salary_target_max', 'notice_period', 'availability_date')
        ),
        7 => array(
            'id' => 'complete',
            'title' => 'You\'re all set',
            'subtitle' => 'Your profile is ready',
            'type' => 'complete'
        )
    );

    /**
     * Industry options
     */
    private $industries = array(
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

    /**
     * Career stage options
     */
    private $career_stages = array(
        'student' => 'Student / Graduate',
        'analyst' => 'Analyst (0-2 years)',
        'associate' => 'Associate (2-4 years)',
        'senior_associate' => 'Senior Associate (4-6 years)',
        'vp' => 'Vice President (6-10 years)',
        'director' => 'Director / Principal',
        'md' => 'Managing Director / Partner',
        'c_level' => 'C-Level Executive'
    );

    /**
     * Target role levels
     */
    private $role_levels = array(
        'same_level' => 'Same Level',
        'one_up' => 'One Level Up',
        'two_up' => 'Two Levels Up',
        'lateral_move' => 'Lateral Move (Different Industry)',
        'flexible' => 'Open to Opportunities'
    );

    /**
     * Premium locations
     */
    private $locations = array(
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

    /**
     * Work environment options
     */
    private $work_environments = array(
        'office' => 'In-Office',
        'hybrid' => 'Hybrid',
        'remote' => 'Remote',
        'flexible' => 'Flexible / No Preference'
    );

    /**
     * Notice period options
     */
    private $notice_periods = array(
        'immediate' => 'Immediately Available',
        '2_weeks' => '2 Weeks',
        '1_month' => '1 Month',
        '2_months' => '2 Months',
        '3_months' => '3 Months',
        '3_plus' => '3+ Months',
        'not_looking' => 'Not Actively Looking'
    );

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
        // Register shortcode
        add_shortcode('sffc_career_onboarding', array($this, 'render_onboarding'));

        // AJAX handlers
        add_action('wp_ajax_sffc_save_onboarding_step', array($this, 'ajax_save_step'));
        add_action('wp_ajax_sffc_complete_onboarding', array($this, 'ajax_complete_onboarding'));
        add_action('wp_ajax_sffc_get_onboarding_progress', array($this, 'ajax_get_progress'));
        add_action('wp_ajax_sffc_skip_onboarding', array($this, 'ajax_skip_onboarding'));

        // Redirect new users to onboarding - DISABLED
        // add_action('wp_login', array($this, 'check_onboarding_redirect'), 10, 2);
        // add_action('template_redirect', array($this, 'maybe_redirect_to_onboarding'));

        // Enqueue assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * Enqueue CSS and JS
     */
    public function enqueue_assets() {
        global $post;

        if (is_admin() || !is_a($post, 'WP_Post')) {
            return;
        }

        $content = (string) ($post->post_content ?? '');
        $should_enqueue = has_shortcode($content, 'sffc_career_onboarding');

        if (!$should_enqueue && !empty($post->post_name)) {
            $should_enqueue = $post->post_name === 'career-onboarding';
        }

        if (!$should_enqueue) {
            return;
        }

        wp_enqueue_style(
            'sffc-career-onboarding',
            SFFC_PLUGIN_URL . 'assets/css/career-onboarding.css',
            array(),
            SFFC_VERSION
        );

        wp_enqueue_script(
            'sffc-career-onboarding',
            SFFC_PLUGIN_URL . 'assets/js/career-onboarding.js',
            array('jquery'),
            SFFC_VERSION,
            true
        );

        $user_id = get_current_user_id();

        // Get return URL if stored
        $return_url = get_transient('sffc_onboarding_return_url_' . $user_id);

        wp_localize_script('sffc-career-onboarding', 'sffcOnboarding', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sffc_onboarding_nonce'),
            'user_id' => $user_id,
            'dashboard_url' => $this->get_dashboard_url(),
            'return_url' => $return_url ? $return_url : '',
            'steps' => $this->steps,
            'total_steps' => count($this->steps)
        ));
    }

    /**
     * Check if user needs onboarding on login
     */
    public function check_onboarding_redirect($user_login, $user) {
        if (!$this->user_has_completed_onboarding($user->ID)) {
            // Store the intended destination if available
            $redirect_to = '';

            // Check for redirect_to parameter (from MemberPress or WordPress)
            if (isset($_REQUEST['redirect_to']) && !empty($_REQUEST['redirect_to'])) {
                $redirect_to = sanitize_text_field($_REQUEST['redirect_to']);
            }
            // Check for HTTP referer as fallback
            elseif (isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER'])) {
                $referer = esc_url_raw($_SERVER['HTTP_REFERER']);
                // Only store if it's from the same domain and not the login page
                $site_url = home_url();
                if (strpos($referer, $site_url) === 0 && strpos($referer, 'wp-login') === false) {
                    $redirect_to = $referer;
                }
            }

            // Set transient to trigger redirect after login completes
            set_transient('sffc_onboarding_redirect_' . $user->ID, true, 60);

            // Store the return URL if we have one
            if (!empty($redirect_to)) {
                set_transient('sffc_onboarding_return_url_' . $user->ID, $redirect_to, 600); // 10 minutes
            }
        }
    }

    /**
     * Redirect to onboarding if needed
     */
    public function maybe_redirect_to_onboarding() {
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();

        // Check for redirect transient
        $should_redirect = get_transient('sffc_onboarding_redirect_' . $user_id);

        if ($should_redirect) {
            delete_transient('sffc_onboarding_redirect_' . $user_id);

            // Don't redirect if already on onboarding page
            if ($this->is_onboarding_page()) {
                return;
            }

            // Don't redirect admins
            if (current_user_can('manage_options')) {
                return;
            }

            // Don't redirect if MemberPress has a redirect_to parameter
            // This allows MemberPress to handle its own redirects (e.g., to /crm)
            if (isset($_REQUEST['redirect_to']) && !empty($_REQUEST['redirect_to'])) {
                return;
            }

            // Don't redirect if user is trying to access a specific page
            // (like the CRM) - check if the current URL is NOT the home page or login page
            $current_url = home_url($_SERVER['REQUEST_URI'] ?? '');
            $home_url = trailingslashit(home_url());
            $login_url = wp_login_url();

            // Allow access to specific paths without onboarding redirect
            $allowed_paths = array('/crm', '/account', '/checkout', '/register', '/pricing');
            foreach ($allowed_paths as $path) {
                if (strpos($_SERVER['REQUEST_URI'] ?? '', $path) !== false) {
                    return;
                }
            }

            $onboarding_url = $this->get_onboarding_url();
            if ($onboarding_url) {
                wp_safe_redirect($onboarding_url);
                exit;
            }
        }
    }

    /**
     * Check if current page is onboarding
     */
    private function is_onboarding_page() {
        global $post;
        if ($post && has_shortcode($post->post_content, 'sffc_career_onboarding')) {
            return true;
        }
        return false;
    }

    /**
     * Get onboarding page URL
     */
    private function get_onboarding_url() {
        // Try to find page with shortcode
        $pages = get_posts(array(
            'post_type' => 'page',
            'posts_per_page' => 1,
            's' => '[sffc_career_onboarding]',
            'post_status' => 'publish'
        ));

        if (!empty($pages)) {
            return get_permalink($pages[0]->ID);
        }

        // Fallback to options
        $onboarding_page_id = get_option('sffc_onboarding_page_id');
        if ($onboarding_page_id) {
            return get_permalink($onboarding_page_id);
        }

        return home_url('/onboarding/');
    }

    /**
     * Get dashboard URL
     */
    private function get_dashboard_url() {
        $dashboard_page_id = get_option('sffc_dashboard_page_id');
        if ($dashboard_page_id) {
            return get_permalink($dashboard_page_id);
        }
        return home_url('/dashboard/');
    }

    /**
     * Check if user has completed onboarding
     */
    public function user_has_completed_onboarding($user_id) {
        $completed = get_user_meta($user_id, 'sffc_onboarding_completed', true);
        return !empty($completed);
    }

    /**
     * Get user's current onboarding step
     */
    public function get_user_onboarding_step($user_id) {
        $step = get_user_meta($user_id, 'sffc_onboarding_step', true);
        return $step ? intval($step) : 1;
    }

    /**
     * Render onboarding interface
     */
    public function render_onboarding($atts = array()) {
        if (!is_user_logged_in()) {
            return $this->render_login_prompt();
        }

        $user_id = get_current_user_id();
        $current_step = $this->get_user_onboarding_step($user_id);
        $profile_data = $this->get_user_profile_data($user_id);

        ob_start();
        ?>
        <div class="sffc-onboarding" id="sffc-onboarding" data-step="<?php echo esc_attr($current_step); ?>">
            <!-- Progress Bar -->
            <div class="sffc-onboarding-progress">
                <div class="sffc-onboarding-progress-bar">
                    <div class="sffc-onboarding-progress-fill" style="width: <?php echo esc_attr(($current_step / count($this->steps)) * 100); ?>%"></div>
                </div>
                <div class="sffc-onboarding-step-indicator">
                    Step <span id="sffc-current-step"><?php echo esc_html($current_step); ?></span> of <?php echo esc_html(count($this->steps)); ?>
                </div>
            </div>

            <!-- Steps Container -->
            <div class="sffc-onboarding-steps">
                <?php
                foreach ($this->steps as $step_num => $step) {
                    echo $this->render_step($step_num, $step, $profile_data, $current_step);
                }
                ?>
            </div>

            <!-- Navigation -->
            <div class="sffc-onboarding-nav">
                <button type="button" class="sffc-onboarding-btn sffc-onboarding-btn-secondary" id="sffc-onboarding-prev" style="<?php echo $current_step <= 1 ? 'visibility: hidden;' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    Back
                </button>
                <button type="button" class="sffc-onboarding-btn sffc-onboarding-btn-text" id="sffc-onboarding-skip">
                    Skip for now
                </button>
                <button type="button" class="sffc-onboarding-btn sffc-onboarding-btn-primary" id="sffc-onboarding-next">
                    Continue
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                </button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render login prompt for non-logged-in users
     */
    private function render_login_prompt() {
        ob_start();
        ?>
        <div class="sffc-onboarding-login">
            <div class="sffc-onboarding-login-content">
                <div class="sffc-onboarding-logo">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none">
                        <rect x="3" y="3" width="8" height="8" rx="2" fill="#2563eb"/>
                        <rect x="13" y="3" width="8" height="8" rx="2" fill="#0ea5e9" fill-opacity="0.6"/>
                        <rect x="3" y="13" width="8" height="8" rx="2" fill="#0ea5e9" fill-opacity="0.6"/>
                        <rect x="13" y="13" width="8" height="8" rx="2" fill="#2563eb"/>
                    </svg>
                </div>
                <h1>Welcome to MENA Careers</h1>
                <p>Please sign in to complete your career profile setup.</p>
                <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" class="sffc-onboarding-btn sffc-onboarding-btn-primary">
                    Sign In
                </a>
                <p class="sffc-onboarding-signup">
                    Don't have an account? <a href="<?php echo esc_url(wp_registration_url()); ?>">Create one</a>
                </p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render individual step
     */
    private function render_step($step_num, $step, $profile_data, $current_step) {
        $is_active = ($step_num === $current_step);
        $is_completed = ($step_num < $current_step);

        ob_start();
        ?>
        <div class="sffc-onboarding-step <?php echo $is_active ? 'active' : ''; ?> <?php echo $is_completed ? 'completed' : ''; ?>"
             data-step="<?php echo esc_attr($step_num); ?>"
             id="sffc-step-<?php echo esc_attr($step_num); ?>">

            <div class="sffc-onboarding-step-header">
                <span class="sffc-onboarding-step-number"><?php echo esc_html($step_num); ?></span>
                <div class="sffc-onboarding-step-titles">
                    <h2 class="sffc-onboarding-step-title"><?php echo esc_html($step['title']); ?></h2>
                    <p class="sffc-onboarding-step-subtitle"><?php echo esc_html($step['subtitle']); ?></p>
                </div>
            </div>

            <div class="sffc-onboarding-step-content">
                <?php
                switch ($step['type']) {
                    case 'intro':
                        echo $this->render_intro_step();
                        break;
                    case 'form':
                        echo $this->render_form_step($step, $profile_data);
                        break;
                    case 'skills':
                        echo $this->render_skills_step($profile_data);
                        break;
                    case 'complete':
                        echo $this->render_complete_step($profile_data);
                        break;
                }
                ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render intro step
     */
    private function render_intro_step() {
        ob_start();
        ?>
        <div class="sffc-onboarding-intro">
            <div class="sffc-onboarding-intro-icon">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none">
                    <rect x="3" y="3" width="8" height="8" rx="2" fill="#2563eb"/>
                    <rect x="13" y="3" width="8" height="8" rx="2" fill="#0ea5e9" fill-opacity="0.6"/>
                    <rect x="3" y="13" width="8" height="8" rx="2" fill="#0ea5e9" fill-opacity="0.6"/>
                    <rect x="13" y="13" width="8" height="8" rx="2" fill="#2563eb"/>
                </svg>
            </div>
            <div class="sffc-onboarding-intro-text">
                <p>We'll ask you a few questions to personalize your experience and help you discover the best opportunities in private equity, venture capital, and finance.</p>
                <p class="sffc-onboarding-intro-time">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                    Takes about 3 minutes
                </p>
            </div>
            <div class="sffc-onboarding-intro-benefits">
                <div class="sffc-onboarding-benefit">
                    <div class="sffc-onboarding-benefit-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                            <polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                    </div>
                    <div class="sffc-onboarding-benefit-text">
                        <strong>Personalized matches</strong>
                        <span>Jobs tailored to your experience</span>
                    </div>
                </div>
                <div class="sffc-onboarding-benefit">
                    <div class="sffc-onboarding-benefit-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                            <polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                    </div>
                    <div class="sffc-onboarding-benefit-text">
                        <strong>Market intelligence</strong>
                        <span>Salary data and industry trends</span>
                    </div>
                </div>
                <div class="sffc-onboarding-benefit">
                    <div class="sffc-onboarding-benefit-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                            <polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                    </div>
                    <div class="sffc-onboarding-benefit-text">
                        <strong>Smart applications</strong>
                        <span>One-click apply to opportunities</span>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render form step
     */
    private function render_form_step($step, $profile_data) {
        ob_start();
        ?>
        <div class="sffc-onboarding-form">
            <?php
            foreach ($step['fields'] as $field) {
                echo $this->render_field($field, $profile_data);
            }
            ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render individual field
     */
    private function render_field($field, $profile_data) {
        $value = isset($profile_data[$field]) ? $profile_data[$field] : '';

        ob_start();
        switch ($field) {
            case 'full_name':
                ?>
                <div class="sffc-onboarding-field">
                    <label for="sffc-field-full_name">Full Name</label>
                    <input type="text" id="sffc-field-full_name" name="full_name" value="<?php echo esc_attr($value); ?>" placeholder="Enter your full name" required>
                </div>
                <?php
                break;

            case 'current_title':
                ?>
                <div class="sffc-onboarding-field">
                    <label for="sffc-field-current_title">Current Job Title</label>
                    <input type="text" id="sffc-field-current_title" name="current_title" value="<?php echo esc_attr($value); ?>" placeholder="e.g., Associate, Vice President">
                </div>
                <?php
                break;

            case 'current_company':
                ?>
                <div class="sffc-onboarding-field">
                    <label for="sffc-field-current_company">Current Company</label>
                    <input type="text" id="sffc-field-current_company" name="current_company" value="<?php echo esc_attr($value); ?>" placeholder="e.g., Goldman Sachs, McKinsey">
                </div>
                <?php
                break;

            case 'years_experience':
                ?>
                <div class="sffc-onboarding-field">
                    <label for="sffc-field-years_experience">Years of Experience</label>
                    <select id="sffc-field-years_experience" name="years_experience">
                        <option value="">Select experience level</option>
                        <?php for ($i = 0; $i <= 20; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php selected($value, $i); ?>><?php echo $i === 0 ? 'Less than 1 year' : ($i === 20 ? '20+ years' : $i . ' year' . ($i > 1 ? 's' : '')); ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <?php
                break;

            case 'career_stage':
                ?>
                <div class="sffc-onboarding-field">
                    <label>Career Stage</label>
                    <div class="sffc-onboarding-options sffc-onboarding-options-grid">
                        <?php foreach ($this->career_stages as $key => $label): ?>
                            <label class="sffc-onboarding-option">
                                <input type="radio" name="career_stage" value="<?php echo esc_attr($key); ?>" <?php checked($value, $key); ?>>
                                <span class="sffc-onboarding-option-content">
                                    <?php echo esc_html($label); ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php
                break;

            case 'preferred_industries':
                $selected = is_array($value) ? $value : (is_string($value) ? json_decode($value, true) : array());
                if (!is_array($selected)) $selected = array();
                ?>
                <div class="sffc-onboarding-field">
                    <label>Industries of Interest <span class="sffc-field-hint">Select all that apply</span></label>
                    <div class="sffc-onboarding-options sffc-onboarding-options-multi">
                        <?php foreach ($this->industries as $key => $label): ?>
                            <label class="sffc-onboarding-option sffc-onboarding-option-checkbox">
                                <input type="checkbox" name="preferred_industries[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $selected)); ?>>
                                <span class="sffc-onboarding-option-content">
                                    <?php echo esc_html($label); ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php
                break;

            case 'target_role_level':
                ?>
                <div class="sffc-onboarding-field">
                    <label>Target Role Level</label>
                    <div class="sffc-onboarding-options">
                        <?php foreach ($this->role_levels as $key => $label): ?>
                            <label class="sffc-onboarding-option">
                                <input type="radio" name="target_role_level" value="<?php echo esc_attr($key); ?>" <?php checked($value, $key); ?>>
                                <span class="sffc-onboarding-option-content">
                                    <?php echo esc_html($label); ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php
                break;

            case 'preferred_locations':
                $selected = is_array($value) ? $value : (is_string($value) ? json_decode($value, true) : array());
                if (!is_array($selected)) $selected = array();
                ?>
                <div class="sffc-onboarding-field">
                    <label>Preferred Locations <span class="sffc-field-hint">Select up to 5</span></label>
                    <div class="sffc-onboarding-options sffc-onboarding-options-multi sffc-onboarding-options-locations">
                        <?php foreach ($this->locations as $key => $label): ?>
                            <label class="sffc-onboarding-option sffc-onboarding-option-checkbox">
                                <input type="checkbox" name="preferred_locations[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $selected)); ?>>
                                <span class="sffc-onboarding-option-content">
                                    <?php echo esc_html($label); ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php
                break;

            case 'open_to_relocation':
                ?>
                <div class="sffc-onboarding-field">
                    <label>Open to Relocation?</label>
                    <div class="sffc-onboarding-options sffc-onboarding-options-inline">
                        <label class="sffc-onboarding-option">
                            <input type="radio" name="open_to_relocation" value="1" <?php checked($value, '1'); ?>>
                            <span class="sffc-onboarding-option-content">Yes</span>
                        </label>
                        <label class="sffc-onboarding-option">
                            <input type="radio" name="open_to_relocation" value="0" <?php checked($value, '0'); ?>>
                            <span class="sffc-onboarding-option-content">No</span>
                        </label>
                        <label class="sffc-onboarding-option">
                            <input type="radio" name="open_to_relocation" value="maybe" <?php checked($value, 'maybe'); ?>>
                            <span class="sffc-onboarding-option-content">For the right role</span>
                        </label>
                    </div>
                </div>
                <?php
                break;

            case 'preferred_work_environment':
                ?>
                <div class="sffc-onboarding-field">
                    <label>Work Environment Preference</label>
                    <div class="sffc-onboarding-options sffc-onboarding-options-inline">
                        <?php foreach ($this->work_environments as $key => $label): ?>
                            <label class="sffc-onboarding-option">
                                <input type="radio" name="preferred_work_environment" value="<?php echo esc_attr($key); ?>" <?php checked($value, $key); ?>>
                                <span class="sffc-onboarding-option-content">
                                    <?php echo esc_html($label); ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php
                break;

            case 'salary_target_min':
                ?>
                <div class="sffc-onboarding-field sffc-onboarding-field-half">
                    <label for="sffc-field-salary_target_min">Target Salary (Minimum)</label>
                    <div class="sffc-onboarding-input-prefix">
                        <span>£</span>
                        <input type="number" id="sffc-field-salary_target_min" name="salary_target_min" value="<?php echo esc_attr($value); ?>" placeholder="e.g., 80000" step="5000">
                    </div>
                </div>
                <?php
                break;

            case 'salary_target_max':
                ?>
                <div class="sffc-onboarding-field sffc-onboarding-field-half">
                    <label for="sffc-field-salary_target_max">Target Salary (Maximum)</label>
                    <div class="sffc-onboarding-input-prefix">
                        <span>£</span>
                        <input type="number" id="sffc-field-salary_target_max" name="salary_target_max" value="<?php echo esc_attr($value); ?>" placeholder="e.g., 120000" step="5000">
                    </div>
                </div>
                <?php
                break;

            case 'notice_period':
                ?>
                <div class="sffc-onboarding-field">
                    <label>Notice Period / Availability</label>
                    <div class="sffc-onboarding-options sffc-onboarding-options-grid">
                        <?php foreach ($this->notice_periods as $key => $label): ?>
                            <label class="sffc-onboarding-option">
                                <input type="radio" name="notice_period" value="<?php echo esc_attr($key); ?>" <?php checked($value, $key); ?>>
                                <span class="sffc-onboarding-option-content">
                                    <?php echo esc_html($label); ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php
                break;

            case 'availability_date':
                ?>
                <div class="sffc-onboarding-field">
                    <label for="sffc-field-availability_date">Available From (if specific date)</label>
                    <input type="date" id="sffc-field-availability_date" name="availability_date" value="<?php echo esc_attr($value); ?>">
                </div>
                <?php
                break;
        }
        return ob_get_clean();
    }

    /**
     * Render skills step
     */
    private function render_skills_step($profile_data) {
        $skills = isset($profile_data['skills']) ? $profile_data['skills'] : array();

        // Common finance/PE skills
        $suggested_skills = array(
            'Financial Modeling', 'Valuation', 'Due Diligence', 'LBO Modeling',
            'DCF Analysis', 'M&A', 'Private Equity', 'Venture Capital',
            'Investment Analysis', 'Portfolio Management', 'Excel', 'PowerPoint',
            'Capital Markets', 'Debt Financing', 'Equity Research', 'Asset Management',
            'Risk Management', 'Compliance', 'Fund Administration', 'Investor Relations',
            'Deal Sourcing', 'Term Sheets', 'Cap Tables', 'Financial Reporting'
        );

        ob_start();
        ?>
        <div class="sffc-onboarding-skills">
            <div class="sffc-onboarding-skills-input">
                <label for="sffc-skill-input">Add your key skills</label>
                <div class="sffc-onboarding-skill-add">
                    <input type="text" id="sffc-skill-input" placeholder="Type a skill and press Enter">
                    <button type="button" id="sffc-add-skill-btn" class="sffc-onboarding-btn sffc-onboarding-btn-small">
                        Add
                    </button>
                </div>
            </div>

            <div class="sffc-onboarding-skills-selected" id="sffc-selected-skills">
                <?php foreach ($skills as $skill): ?>
                    <span class="sffc-onboarding-skill-tag" data-skill="<?php echo esc_attr($skill); ?>">
                        <?php echo esc_html($skill); ?>
                        <button type="button" class="sffc-remove-skill">&times;</button>
                    </span>
                <?php endforeach; ?>
            </div>

            <div class="sffc-onboarding-skills-suggested">
                <p class="sffc-onboarding-skills-suggested-label">Suggested skills (click to add):</p>
                <div class="sffc-onboarding-skills-suggested-list">
                    <?php foreach ($suggested_skills as $skill): ?>
                        <button type="button" class="sffc-onboarding-suggested-skill" data-skill="<?php echo esc_attr($skill); ?>">
                            <?php echo esc_html($skill); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <input type="hidden" name="skills" id="sffc-skills-hidden" value="<?php echo esc_attr(json_encode($skills)); ?>">
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render complete step
     */
    private function render_complete_step($profile_data) {
        $user = wp_get_current_user();
        $name = !empty($profile_data['full_name']) ? $profile_data['full_name'] : $user->display_name;

        ob_start();
        ?>
        <div class="sffc-onboarding-complete">
            <div class="sffc-onboarding-complete-icon">
                <svg width="72" height="72" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="10" stroke="#22c55e" stroke-width="2"/>
                    <path d="M8 12l2.5 2.5L16 9" stroke="#22c55e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h3>Excellent, <?php echo esc_html(explode(' ', $name)[0]); ?>!</h3>
            <p>Your career profile is ready. We'll now personalize your dashboard with relevant opportunities, market insights, and career intelligence.</p>

            <div class="sffc-onboarding-complete-summary">
                <div class="sffc-onboarding-summary-item">
                    <span class="sffc-onboarding-summary-label">Profile Status</span>
                    <span class="sffc-onboarding-summary-value sffc-onboarding-summary-success">Complete</span>
                </div>
                <?php if (!empty($profile_data['career_stage'])): ?>
                <div class="sffc-onboarding-summary-item">
                    <span class="sffc-onboarding-summary-label">Career Stage</span>
                    <span class="sffc-onboarding-summary-value"><?php echo esc_html($this->career_stages[$profile_data['career_stage']] ?? $profile_data['career_stage']); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($profile_data['preferred_industries'])):
                    $industries = is_array($profile_data['preferred_industries']) ? $profile_data['preferred_industries'] : json_decode($profile_data['preferred_industries'], true);
                    if (!empty($industries)):
                ?>
                <div class="sffc-onboarding-summary-item">
                    <span class="sffc-onboarding-summary-label">Target Industries</span>
                    <span class="sffc-onboarding-summary-value"><?php echo esc_html(count($industries)); ?> selected</span>
                </div>
                <?php endif; endif; ?>
                <?php if (!empty($profile_data['skills'])):
                    $skills = is_array($profile_data['skills']) ? $profile_data['skills'] : json_decode($profile_data['skills'], true);
                    if (!empty($skills)):
                ?>
                <div class="sffc-onboarding-summary-item">
                    <span class="sffc-onboarding-summary-label">Skills Added</span>
                    <span class="sffc-onboarding-summary-value"><?php echo esc_html(count($skills)); ?> skills</span>
                </div>
                <?php endif; endif; ?>
            </div>

            <button type="button" class="sffc-onboarding-btn sffc-onboarding-btn-primary sffc-onboarding-btn-large" id="sffc-go-to-dashboard">
                Go to Dashboard
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M5 12h14M12 5l7 7-7 7"/>
                </svg>
            </button>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get user profile data
     */
    private function get_user_profile_data($user_id) {
        global $wpdb;

        $profile_table = $wpdb->prefix . 'sffc_user_profiles';
        $skills_table = $wpdb->prefix . 'sffc_user_skills';

        // Get main profile
        $profile = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $profile_table WHERE user_id = %d",
            $user_id
        ), ARRAY_A);

        if (!$profile) {
            $profile = array();
        }

        // Get user meta for name
        $user = get_userdata($user_id);
        if (empty($profile['full_name'])) {
            $profile['full_name'] = $user->display_name;
        }

        // Decode JSON fields
        $json_fields = array('preferred_industries', 'preferred_locations', 'languages_spoken');
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
        $profile['skills'] = $skills ?: array();

        return $profile;
    }

    /**
     * AJAX: Save onboarding step
     */
    public function ajax_save_step() {
        check_ajax_referer('sffc_onboarding_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        $user_id = get_current_user_id();
        $step = isset($_POST['step']) ? intval($_POST['step']) : 1;
        $data = isset($_POST['data']) ? $_POST['data'] : array();

        // Save the data
        $result = $this->save_profile_data($user_id, $data);

        if ($result) {
            // Update current step
            update_user_meta($user_id, 'sffc_onboarding_step', $step + 1);

            wp_send_json_success(array(
                'message' => 'Step saved',
                'next_step' => $step + 1
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to save'));
        }
    }

    /**
     * Save profile data to database
     * Table structure uses JSON columns: professional_data, preferences, career_goals, skill_levels
     */
    private function save_profile_data($user_id, $data) {
        global $wpdb;

        $profile_table = $wpdb->prefix . 'sffc_user_profiles';
        $skills_table = $wpdb->prefix . 'sffc_user_skills';

        // Get existing profile data to merge with new data
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $profile_table WHERE user_id = %d",
            $user_id
        ), ARRAY_A);

        // Parse existing JSON data
        $professional_data = array();
        $preferences = array();
        $career_goals = array();

        if ($existing) {
            $professional_data = !empty($existing['professional_data']) ? json_decode($existing['professional_data'], true) : array();
            $preferences = !empty($existing['preferences']) ? json_decode($existing['preferences'], true) : array();
            $career_goals = !empty($existing['career_goals']) ? json_decode($existing['career_goals'], true) : array();
        }

        // Ensure arrays
        $professional_data = is_array($professional_data) ? $professional_data : array();
        $preferences = is_array($preferences) ? $preferences : array();
        $career_goals = is_array($career_goals) ? $career_goals : array();

        // Professional data fields
        $prof_fields = array('full_name', 'current_title', 'current_company', 'years_experience', 'career_stage', 'target_role_level');
        foreach ($prof_fields as $field) {
            if (isset($data[$field])) {
                $professional_data[$field] = sanitize_text_field($data[$field]);
            }
        }

        // Preferences fields
        $pref_fields = array('open_to_relocation', 'preferred_work_environment', 'salary_target_min', 'salary_target_max', 'notice_period', 'availability_date');
        foreach ($pref_fields as $field) {
            if (isset($data[$field])) {
                $preferences[$field] = sanitize_text_field($data[$field]);
            }
        }

        // Handle array fields for preferences
        $array_pref_fields = array('preferred_industries', 'preferred_locations');
        foreach ($array_pref_fields as $field) {
            if (isset($data[$field])) {
                $values = is_array($data[$field]) ? $data[$field] : array($data[$field]);
                $preferences[$field] = array_map('sanitize_text_field', $values);
            }
        }

        // Career goals
        if (isset($data['career_goals_text'])) {
            $career_goals['goals_text'] = sanitize_textarea_field($data['career_goals_text']);
        }

        // Update full name in WordPress user
        if (!empty($data['full_name'])) {
            wp_update_user(array(
                'ID' => $user_id,
                'display_name' => sanitize_text_field($data['full_name'])
            ));
        }

        // Prepare database row
        $db_data = array(
            'professional_data' => wp_json_encode($professional_data),
            'preferences' => wp_json_encode($preferences),
            'career_goals' => wp_json_encode($career_goals),
            'updated_at' => current_time('mysql')
        );

        // Insert or update
        if ($existing) {
            $wpdb->update($profile_table, $db_data, array('user_id' => $user_id));
        } else {
            $db_data['user_id'] = $user_id;
            $db_data['created_at'] = current_time('mysql');
            $wpdb->insert($profile_table, $db_data);
        }

        // Handle skills - skills table has individual columns which is correct
        if (isset($data['skills'])) {
            $skills = is_array($data['skills']) ? $data['skills'] : json_decode($data['skills'], true);

            if (is_array($skills) && !empty($skills)) {
                // Don't clear all skills, just add new ones (avoid removing skills from other sources)
                foreach ($skills as $skill) {
                    $skill_name = sanitize_text_field($skill);
                    if (!empty($skill_name)) {
                        // Check if skill already exists
                        $skill_exists = $wpdb->get_var($wpdb->prepare(
                            "SELECT id FROM $skills_table WHERE user_id = %d AND skill_name = %s",
                            $user_id,
                            $skill_name
                        ));

                        if (!$skill_exists) {
                            $wpdb->insert($skills_table, array(
                                'user_id' => $user_id,
                                'skill_name' => $skill_name,
                                'skill_category' => 'General',
                                'proficiency_level' => 'intermediate',
                                'source' => 'onboarding',
                                'created_at' => current_time('mysql')
                            ));
                        }
                    }
                }
            }
        }

        return true;
    }

    /**
     * Update profile completion percentage
     * Calculates based on JSON data in professional_data and preferences columns
     */
    private function update_profile_completion($user_id) {
        global $wpdb;

        $profile_table = $wpdb->prefix . 'sffc_user_profiles';
        $skills_table = $wpdb->prefix . 'sffc_user_skills';

        $profile = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $profile_table WHERE user_id = %d",
            $user_id
        ), ARRAY_A);

        if (!$profile) {
            update_user_meta($user_id, 'sffc_profile_completion', 0);
            return;
        }

        // Parse JSON columns
        $professional_data = !empty($profile['professional_data']) ? json_decode($profile['professional_data'], true) : array();
        $preferences = !empty($profile['preferences']) ? json_decode($profile['preferences'], true) : array();

        $professional_data = is_array($professional_data) ? $professional_data : array();
        $preferences = is_array($preferences) ? $preferences : array();

        $completion = 0;
        $total_weight = 100;

        // Core professional fields (50% weight)
        $core_fields = array(
            'current_title' => 15,
            'years_experience' => 15,
            'career_stage' => 10,
            'full_name' => 10
        );

        foreach ($core_fields as $field => $weight) {
            if (!empty($professional_data[$field])) {
                $completion += $weight;
            }
        }

        // Preference fields (30% weight)
        $pref_fields = array(
            'preferred_industries' => 10,
            'preferred_locations' => 10,
            'preferred_work_environment' => 5,
            'salary_target_min' => 5
        );

        foreach ($pref_fields as $field => $weight) {
            $value = $preferences[$field] ?? null;
            if (!empty($value) && (!is_array($value) || count($value) > 0)) {
                $completion += $weight;
            }
        }

        // Skills (20% weight)
        $skill_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $skills_table WHERE user_id = %d",
            $user_id
        ));
        if ($skill_count >= 5) {
            $completion += 20;
        } elseif ($skill_count >= 3) {
            $completion += 15;
        } elseif ($skill_count >= 1) {
            $completion += 10;
        }

        $percentage = min(100, $completion);

        // Store completion in user meta (table doesn't have this column)
        update_user_meta($user_id, 'sffc_profile_completion', $percentage);
    }

    /**
     * AJAX: Complete onboarding
     */
    public function ajax_complete_onboarding() {
        check_ajax_referer('sffc_onboarding_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        $user_id = get_current_user_id();

        // Mark onboarding as complete
        update_user_meta($user_id, 'sffc_onboarding_completed', current_time('mysql'));
        update_user_meta($user_id, 'sffc_onboarding_step', count($this->steps));

        // Calculate final profile completion
        $this->update_profile_completion($user_id);

        // Get the return URL if one was stored
        $redirect_url = get_transient('sffc_onboarding_return_url_' . $user_id);

        // Clean up the transient
        if ($redirect_url) {
            delete_transient('sffc_onboarding_return_url_' . $user_id);
        } else {
            // Fallback to dashboard
            $redirect_url = $this->get_dashboard_url();
        }

        wp_send_json_success(array(
            'message' => 'Onboarding complete',
            'redirect_url' => $redirect_url
        ));
    }

    /**
     * AJAX: Get onboarding progress
     */
    public function ajax_get_progress() {
        check_ajax_referer('sffc_onboarding_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        $user_id = get_current_user_id();
        $step = $this->get_user_onboarding_step($user_id);
        $completed = $this->user_has_completed_onboarding($user_id);
        $profile = $this->get_user_profile_data($user_id);

        wp_send_json_success(array(
            'current_step' => $step,
            'total_steps' => count($this->steps),
            'completed' => $completed,
            'profile' => $profile
        ));
    }

    /**
     * AJAX: Skip onboarding
     */
    public function ajax_skip_onboarding() {
        check_ajax_referer('sffc_onboarding_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        $user_id = get_current_user_id();

        // Mark as skipped (not completed)
        update_user_meta($user_id, 'sffc_onboarding_skipped', current_time('mysql'));

        // Get the return URL if one was stored
        $redirect_url = get_transient('sffc_onboarding_return_url_' . $user_id);

        // Clean up the transient
        if ($redirect_url) {
            delete_transient('sffc_onboarding_return_url_' . $user_id);
        } else {
            // Fallback to dashboard
            $redirect_url = $this->get_dashboard_url();
        }

        wp_send_json_success(array(
            'message' => 'Onboarding skipped',
            'redirect_url' => $redirect_url
        ));
    }
}

// Initialize
SFFC_Career_Onboarding::get_instance();
