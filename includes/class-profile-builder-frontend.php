<?php

/**
 * Profile Builder Frontend - Premium Sequenced Interface
 * MENA Careers-guided career profile creation with caring recruiter approach
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Profile_Builder_Frontend
{

    private static $instance = null;

    // Premium city locations only
    private static $premium_cities = [
        'london' => 'London',
        'milan' => 'Milan',
        'barcelona' => 'Barcelona',
        'madrid' => 'Madrid',
        'sao-paulo' => 'São Paulo',
        'dubai' => 'Dubai',
        'doha' => 'Doha',
        'jeddah' => 'Jeddah',
        'abu-dhabi' => 'Abu Dhabi',
        'johannesburg' => 'Johannesburg',
        'paris' => 'Paris',
        'zurich' => 'Zurich',
        'luxembourg' => 'Luxembourg',
        'dublin' => 'Dublin',
        'montevideo' => 'Montevideo',
        'santiago' => 'Santiago',
        'buenos-aires' => 'Buenos Aires'
    ];

    // Company size options
    private static $company_sizes = [
        'startup' => 'Startup (1-50 employees)',
        'small' => 'Small (51-200 employees)',
        'medium' => 'Medium (201-1000 employees)',
        'large' => 'Large (1001-5000 employees)',
        'enterprise' => 'Enterprise (5000+ employees)',
        'boutique' => 'Boutique/Elite Firms',
        'flexible' => 'Flexible - Open to all'
    ];

    // Professional certifications
    private static $certifications = [
        'cfa' => 'CFA (Chartered Financial Analyst)',
        'cfa_l1' => 'CFA Level I Candidate',
        'cfa_l2' => 'CFA Level II Candidate',
        'caia' => 'CAIA (Alternative Investments)',
        'frm' => 'FRM (Financial Risk Manager)',
        'cpa' => 'CPA (Certified Public Accountant)',
        'acca' => 'ACCA',
        'aca' => 'ACA',
        'cima' => 'CIMA',
        'cfp' => 'CFP (Certified Financial Planner)',
        'mba' => 'MBA',
        'phd' => 'PhD/Doctorate',
        'series_7' => 'Series 7',
        'series_63' => 'Series 63',
        'none' => 'None yet'
    ];

    // Education levels
    private static $education_levels = [
        'high_school' => 'High School',
        'bachelors' => "Bachelor's Degree",
        'masters' => "Master's Degree",
        'mba' => 'MBA',
        'phd' => 'PhD/Doctorate',
        'professional' => 'Professional Degree (JD, MD)'
    ];

    // Seniority levels
    private static $seniority_levels = [
        'intern' => 'Early Career',
        'analyst' => 'Analyst',
        'associate' => 'Associate',
        'senior_associate' => 'Senior Associate',
        'vp' => 'Vice President',
        'director' => 'Director',
        'md' => 'Managing Director',
        'partner' => 'Partner/Principal',
        'c_level' => 'C-Level Executive'
    ];

    // Languages
    private static $languages = [
        'en' => 'English',
        'es' => 'Spanish',
        'fr' => 'French',
        'de' => 'German',
        'it' => 'Italian',
        'pt' => 'Portuguese',
        'zh' => 'Mandarin Chinese',
        'ja' => 'Japanese',
        'ar' => 'Arabic',
        'ru' => 'Russian',
        'ko' => 'Korean',
        'nl' => 'Dutch',
        'other' => 'Other'
    ];

    // Availability options
    private static $availability_options = [
        'immediate' => 'Immediately available',
        '2_weeks' => '2 weeks notice',
        '1_month' => '1 month notice',
        '2_months' => '2 months notice',
        '3_months' => '3+ months notice',
        'not_looking' => 'Not actively looking'
    ];

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Register shortcodes
        add_shortcode('sffc_profile_builder', [$this, 'render_profile_builder']);
        add_shortcode('sffc_profile_completion', [$this, 'render_completion_widget']);
        add_shortcode('sffc_career_profile', [$this, 'render_career_profile_page']);

        // Register AJAX handlers
        add_action('wp_ajax_nopriv_sffc_save_profile_answer', [$this, 'ajax_save_profile_answer']);
        add_action('wp_ajax_sffc_save_profile_answer', [$this, 'ajax_save_profile_answer']);

        add_action('wp_ajax_nopriv_sffc_get_user_profile', [$this, 'ajax_get_user_profile']);
        add_action('wp_ajax_sffc_get_user_profile', [$this, 'ajax_get_user_profile']);

        add_action('wp_ajax_nopriv_sffc_complete_profile', [$this, 'ajax_complete_profile']);
        add_action('wp_ajax_sffc_complete_profile', [$this, 'ajax_complete_profile']);

        // Enqueue assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Enqueue CSS and JS for profile builder
     */
    public function enqueue_assets()
    {
        if (is_admin()) {
            return;
        }

        global $post;
        $should_enqueue = false;

        if (is_a($post, 'WP_Post')) {
            $should_enqueue =
                has_shortcode($post->post_content, 'sffc_profile_builder') ||
                has_shortcode($post->post_content, 'sffc_profile_completion') ||
                has_shortcode($post->post_content, 'sffc_career_profile');
        }

        if (!$should_enqueue) {
            return;
        }

        // Google Fonts for Playfair Display
        wp_enqueue_style(
            'google-fonts-playfair',
            'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&display=swap',
            [],
            null
        );

        // CSS - Original for compatibility
        wp_enqueue_style(
            'sffc-profile-builder',
            SFFC_PLUGIN_URL . 'assets/css/profile-builder.css',
            [],
            SFFC_VERSION
        );

        // CSS - Premium Sequenced Interface
        wp_enqueue_style(
            'sffc-profile-builder-premium',
            SFFC_PLUGIN_URL . 'assets/css/profile-builder-premium.css',
            ['sffc-profile-builder'],
            SFFC_VERSION
        );

        // JavaScript
        wp_enqueue_script(
            'sffc-profile-builder',
            SFFC_PLUGIN_URL . 'assets/js/profile-builder.js',
            ['jquery'],
            SFFC_VERSION,
            true
        );

        // Localize script
        wp_localize_script('sffc-profile-builder', 'sffc_profile', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sffc_profile_nonce'),
            'user_id' => get_current_user_id(),
            'session_id' => $this->get_session_id(),
            'is_logged_in' => is_user_logged_in(),
            'senna_avatar' => SFFC_PLUGIN_URL . 'assets/images/senna.jpeg'
        ]);
    }

    /**
     * Render the premium sequenced profile builder
     */
    public function render_profile_builder($atts = [])
    {
        $atts = shortcode_atts([
            'mode' => 'fullscreen',  // fullscreen or inline
            'auto_open' => 'false'   // auto open on page load
        ], $atts);

        $is_inline = ($atts['mode'] === 'inline');
        $auto_open = ($atts['auto_open'] === 'true' || $atts['auto_open'] === '1');
        $profile = $this->get_user_profile();

        // Inline styles - show immediately for inline mode, hide for fullscreen/modal
        if ($is_inline) {
            $container_style = 'display: flex;';
            $container_class = 'sffc-profile-builder inline';
        } else {
            $container_style = 'display: none;';
            $container_class = 'sffc-profile-builder fullscreen';
        }

        ob_start();
?>
        <div class="<?php echo esc_attr($container_class); ?>" id="profile-builder" style="<?php echo esc_attr($container_style); ?>">
            <!-- Premium Background -->
            <div class="sffc-pb-background">
                <div class="sffc-pb-gradient-overlay"></div>
                <div class="sffc-pb-pattern-overlay"></div>
            </div>

            <!-- Main Container -->
            <div class="sffc-pb-container">
                <!-- Close Button -->
                <button class="sffc-pb-close" onclick="closeProfileBuilder()">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>

                <!-- MENA Careers Avatar Section -->
                <div class="sffc-pb-senna-section">
                    <div class="sffc-pb-senna-avatar">
                        <img src="<?php echo SFFC_PLUGIN_URL; ?>assets/images/senna.jpeg" alt="MENA Careers - Your Career Consultant">
                        <div class="sffc-pb-senna-status">
                            <span class="status-dot"></span>
                            <span class="status-text">Active</span>
                        </div>
                    </div>
                    <div class="sffc-pb-senna-intro">
                        <h3 class="sffc-pb-senna-name">MENA Careers</h3>
                        <p class="sffc-pb-senna-title">Your Premium Career Consultant</p>
                    </div>
                </div>

                <!-- Question Display Area -->
                <div class="sffc-pb-question-area">
                    <div class="sffc-pb-question-text" id="question-text">
                        <!-- Typing effect will display here -->
                    </div>
                </div>

                <!-- Answer Area -->
                <div class="sffc-pb-answer-area" id="answer-area">
                    <!-- Dynamic answer options will appear here -->
                </div>

                <!-- Progress Indicator -->
                <div class="sffc-pb-progress">
                    <div class="sffc-pb-progress-bar">
                        <div class="sffc-pb-progress-fill" id="progress-fill"></div>
                    </div>
                    <div class="sffc-pb-progress-steps">
                        <span class="current-step" id="current-step">1</span>
                        <span class="step-separator">/</span>
                        <span class="total-steps">17</span>
                    </div>
                </div>

                <!-- Skip/Back Navigation -->
                <div class="sffc-pb-navigation">
                    <button class="sffc-pb-nav-back" id="nav-back" style="display: none;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M19 12H5M5 12L12 19M5 12L12 5" />
                        </svg>
                        <span>Previous</span>
                    </button>
                    <button class="sffc-pb-nav-skip" id="nav-skip">
                        <span>Skip this question</span>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M5 12H19M19 12L12 5M19 12L12 19" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Hidden Profile Data -->
            <div id="profile-data" style="display: none;"><?php echo json_encode($profile); ?></div>
        </div>

        <!-- Profile Questions Configuration -->
        <script>
            window.profileQuestions = [{
                    id: 'greeting',
                    question: "Hello! I'm MENA Careers, your personal career consultant. I'm here to help you find your perfect role. What's your name?",
                    type: 'text',
                    field: 'full_name',
                    placeholder: 'Enter your full name'
                },
                {
                    id: 'experience',
                    question: "Nice to meet you, {name}! Let's start with your experience. How many years have you been working professionally?",
                    type: 'choice',
                    field: 'years_experience',
                    options: [{
                            value: '0-2',
                            label: 'Early Career (0-2 years)'
                        },
                        {
                            value: '3-5',
                            label: 'Developing Professional (3-5 years)'
                        },
                        {
                            value: '6-10',
                            label: 'Experienced (6-10 years)'
                        },
                        {
                            value: '11-15',
                            label: 'Senior Level (11-15 years)'
                        },
                        {
                            value: '16+',
                            label: 'Executive (16+ years)'
                        }
                    ]
                },
                {
                    id: 'current_role',
                    question: "What best describes your current or most recent role?",
                    type: 'text',
                    field: 'current_role',
                    placeholder: 'e.g., Senior Financial Analyst, Product Manager'
                },
                {
                    id: 'industries',
                    question: "Which industries excite you the most? This helps me find companies that match your interests.",
                    type: 'multi-choice',
                    field: 'preferred_industries',
                    limit: 3,
                    options: [{
                            value: 'investment-banking',
                            label: 'Investment Banking'
                        },
                        {
                            value: 'private-equity',
                            label: 'Private Equity'
                        },
                        {
                            value: 'asset-management',
                            label: 'Asset Management'
                        },
                        {
                            value: 'venture-capital',
                            label: 'Venture Capital'
                        },
                        {
                            value: 'consulting',
                            label: 'Management Consulting'
                        },
                        {
                            value: 'technology',
                            label: 'Technology'
                        },
                        {
                            value: 'fintech',
                            label: 'FinTech'
                        },
                        {
                            value: 'real-estate',
                            label: 'Real Estate'
                        },
                        {
                            value: 'healthcare',
                            label: 'Healthcare'
                        },
                        {
                            value: 'energy',
                            label: 'Energy & Infrastructure'
                        }
                    ]
                },
                {
                    id: 'company_size',
                    question: "What size company culture suits you best? Every environment has its unique advantages.",
                    type: 'choice',
                    field: 'preferred_company_size',
                    options: [{
                            value: 'startup',
                            label: 'Startup',
                            description: '1-50 employees'
                        },
                        {
                            value: 'small',
                            label: 'Small Company',
                            description: '51-200 employees'
                        },
                        {
                            value: 'medium',
                            label: 'Medium Company',
                            description: '201-1000 employees'
                        },
                        {
                            value: 'large',
                            label: 'Large Corporation',
                            description: '1001-5000 employees'
                        },
                        {
                            value: 'enterprise',
                            label: 'Enterprise',
                            description: '5000+ employees'
                        },
                        {
                            value: 'boutique',
                            label: 'Boutique/Elite Firms',
                            description: 'Premium specialized'
                        },
                        {
                            value: 'flexible',
                            label: 'Flexible',
                            description: 'Open to all sizes'
                        }
                    ]
                },
                {
                    id: 'locations',
                    question: "Where would you love to work? Choose up to 3 cities that excite you.",
                    type: 'location-select',
                    field: 'preferred_locations',
                    limit: 3,
                    options: <?php echo json_encode(self::$premium_cities); ?>
                },
                {
                    id: 'work_style',
                    question: "How do you prefer to work? The future of work is flexible.",
                    type: 'choice',
                    field: 'work_preference',
                    options: [{
                            value: 'office',
                            label: 'In Office',
                            description: 'I thrive in collaborative spaces'
                        },
                        {
                            value: 'hybrid',
                            label: 'Hybrid',
                            description: 'Best of both worlds'
                        },
                        {
                            value: 'remote',
                            label: 'Fully Remote',
                            description: 'Location independence'
                        },
                        {
                            value: 'flexible',
                            label: 'Flexible',
                            description: 'Open to any arrangement'
                        }
                    ]
                },
                {
                    id: 'salary',
                    question: "Let's talk compensation. What salary range aligns with your experience and goals?",
                    type: 'salary-range',
                    field: 'salary_expectations',
                    currency: '$',
                    ranges: [{
                            min: 60000,
                            max: 80000,
                            label: '$60K - $80K'
                        },
                        {
                            min: 80000,
                            max: 100000,
                            label: '$80K - $100K'
                        },
                        {
                            min: 100000,
                            max: 130000,
                            label: '$100K - $130K'
                        },
                        {
                            min: 130000,
                            max: 160000,
                            label: '$130K - $160K'
                        },
                        {
                            min: 160000,
                            max: 200000,
                            label: '$160K - $200K'
                        },
                        {
                            min: 200000,
                            max: 250000,
                            label: '$200K - $250K'
                        },
                        {
                            min: 250000,
                            max: 350000,
                            label: '$250K - $350K'
                        },
                        {
                            min: 350000,
                            max: 500000,
                            label: '$350K - $500K'
                        },
                        {
                            min: 500000,
                            max: 999999,
                            label: '$500K+'
                        }
                    ]
                },
                {
                    id: 'key_skills',
                    question: "What are your top professional skills? Share 3-5 that best represent your expertise.",
                    type: 'skill-input',
                    field: 'skills',
                    min: 3,
                    max: 5,
                    suggestions: [
                        'Financial Modeling', 'Data Analysis', 'Project Management',
                        'Strategic Planning', 'Business Development', 'Python',
                        'SQL', 'Excel', 'Leadership', 'M&A', 'Valuation',
                        'Risk Management', 'Portfolio Management', 'Client Relations'
                    ]
                },
                {
                    id: 'career_goals',
                    question: "What's most important to you in your next role? This helps me find meaningful matches.",
                    type: 'multi-choice',
                    field: 'career_priorities',
                    limit: 3,
                    options: [{
                            value: 'growth',
                            label: 'Career Growth'
                        },
                        {
                            value: 'compensation',
                            label: 'Competitive Compensation'
                        },
                        {
                            value: 'impact',
                            label: 'Making an Impact'
                        },
                        {
                            value: 'learning',
                            label: 'Learning & Development'
                        },
                        {
                            value: 'culture',
                            label: 'Company Culture'
                        },
                        {
                            value: 'flexibility',
                            label: 'Work-Life Balance'
                        },
                        {
                            value: 'leadership',
                            label: 'Leadership Opportunities'
                        },
                        {
                            value: 'innovation',
                            label: 'Innovation & Creativity'
                        }
                    ]
                },
                {
                    id: 'ideal_role',
                    question: "Describe your ideal role in a few words. What would make you excited to start work every Monday?",
                    type: 'text',
                    field: 'ideal_role_description',
                    placeholder: 'e.g., Leading strategic initiatives in a fast-growing fintech'
                },
                {
                    id: 'education',
                    question: "What's your highest level of education? This helps match you with roles that value your academic background.",
                    type: 'choice',
                    field: 'education_level',
                    options: <?php echo json_encode(array_map(function($key, $label) {
                        return ['value' => $key, 'label' => $label];
                    }, array_keys(self::$education_levels), array_values(self::$education_levels))); ?>
                },
                {
                    id: 'seniority',
                    question: "What seniority level are you targeting in your next role?",
                    type: 'choice',
                    field: 'target_seniority',
                    options: <?php echo json_encode(array_map(function($key, $label) {
                        return ['value' => $key, 'label' => $label];
                    }, array_keys(self::$seniority_levels), array_values(self::$seniority_levels))); ?>
                },
                {
                    id: 'certifications',
                    question: "Do you hold any professional certifications? Select all that apply.",
                    type: 'multi-choice',
                    field: 'certifications',
                    limit: 5,
                    options: <?php echo json_encode(array_map(function($key, $label) {
                        return ['value' => $key, 'label' => $label];
                    }, array_keys(self::$certifications), array_values(self::$certifications))); ?>
                },
                {
                    id: 'languages',
                    question: "Which languages do you speak professionally? This opens doors to international opportunities.",
                    type: 'multi-choice',
                    field: 'languages_spoken',
                    limit: 5,
                    options: <?php echo json_encode(array_map(function($key, $label) {
                        return ['value' => $key, 'label' => $label];
                    }, array_keys(self::$languages), array_values(self::$languages))); ?>
                },
                {
                    id: 'availability',
                    question: "When could you start a new role?",
                    type: 'choice',
                    field: 'availability',
                    options: <?php echo json_encode(array_map(function($key, $label) {
                        return ['value' => $key, 'label' => $label];
                    }, array_keys(self::$availability_options), array_values(self::$availability_options))); ?>
                },
                {
                    id: 'match_threshold',
                    question: "Last question! What's your minimum match score for job recommendations? Jobs below this threshold will be shown but marked differently.",
                    type: 'threshold-slider',
                    field: 'match_threshold',
                    min: 40,
                    max: 90,
                    default: 60,
                    step: 5,
                    labels: {
                        40: 'Show more jobs',
                        60: 'Balanced (recommended)',
                        90: 'Only top matches'
                    }
                },
                {
                    id: 'completion',
                    question: "Excellent, {name}! Your career profile is complete. I'll now show you personalized match scores for every job. Ready to see your matches?",
                    type: 'completion',
                    showSummary: true
                }
            ];
        </script>
<?php
        // Auto-open script for inline mode or auto_open attribute
        if ($is_inline || $auto_open) {
?>
        <script>
            jQuery(document).ready(function($) {
                <?php if ($is_inline): ?>
                // Inline mode - start sequence immediately
                if (window.profileSequence) {
                    window.profileSequence.start();
                }
                <?php else: ?>
                // Auto-open fullscreen mode
                if (typeof window.openProfileBuilder === 'function') {
                    setTimeout(function() {
                        window.openProfileBuilder();
                    }, 500);
                }
                <?php endif; ?>
            });
        </script>
<?php
        }

        return ob_get_clean();
    }

    /**
     * Render a dedicated career profile page
     * Usage: [sffc_career_profile]
     * Displays fullscreen profile builder that auto-opens (same as application audit)
     */
    public function render_career_profile_page($atts = [])
    {
        $profile = $this->get_user_profile();
        $is_logged_in = is_user_logged_in();

        ob_start();

        // Render the fullscreen profile builder
        echo $this->render_profile_builder(['mode' => 'fullscreen', 'auto_open' => 'false']);
?>
        <script>
            jQuery(document).ready(function($) {
                // Auto-open the fullscreen profile builder immediately
                setTimeout(function() {
                    if (typeof window.openProfileBuilder === 'function') {
                        window.openProfileBuilder();
                    } else if (window.profileSequence) {
                        $('#profile-builder').addClass('active').fadeIn(300).css('display', 'flex');
                        window.profileSequence.start();
                    }
                }, 100);
            });
        </script>
<?php
        return ob_get_clean();
    }

    /**
     * Get or create session ID
     */
    private function get_session_id()
    {
        if (isset($_COOKIE['sffc_session_id'])) {
            return sanitize_text_field($_COOKIE['sffc_session_id']);
        }

        $session_id = 'sess_' . wp_generate_uuid4();
        setcookie('sffc_session_id', $session_id, time() + (30 * DAY_IN_SECONDS), '/');

        return $session_id;
    }

    /**
     * Get user profile
     */
    private function get_user_profile()
    {
        global $wpdb;

        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $profile_table = $wpdb->prefix . 'sffc_user_profiles';

            $profile = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $profile_table WHERE user_id = %d",
                $user_id
            ), ARRAY_A);

            if ($profile) {
                $skills_table = $wpdb->prefix . 'sffc_user_skills';
                $skills = $wpdb->get_results($wpdb->prepare(
                    "SELECT skill_name, proficiency_level FROM $skills_table WHERE user_id = %d",
                    $user_id
                ), ARRAY_A);

                $profile['skills'] = $skills;
                $profile['completion'] = $this->calculate_completion($profile);

                return $profile;
            }
        } else {
            // Check session for anonymous users
            $session_id = $this->get_session_id();
            $profile = get_transient('sffc_profile_' . $session_id);

            if ($profile) {
                $profile['completion'] = $this->calculate_completion($profile);
                return $profile;
            }
        }

        return [
            'completion' => 0,
            'skills' => [],
            'preferred_locations' => [],
            'preferred_industries' => []
        ];
    }

    /**
     * Calculate profile completion percentage
     * Core fields are required, optional fields add bonus completion
     */
    private function calculate_completion($profile)
    {
        // Core required fields (must have for matching)
        $core_fields = [
            'full_name',
            'years_experience',
            'current_role',
            'preferred_industries',
            'preferred_locations',
            'work_preference',
            'skills'
        ];

        // Optional fields (improve matching quality)
        $optional_fields = [
            'preferred_company_size',
            'salary_expectations',
            'career_priorities',
            'ideal_role_description',
            'education_level',
            'target_seniority',
            'certifications',
            'languages_spoken',
            'availability',
            'match_threshold'
        ];

        $core_completed = 0;
        foreach ($core_fields as $field) {
            if (!empty($profile[$field])) {
                $core_completed++;
            }
        }

        $optional_completed = 0;
        foreach ($optional_fields as $field) {
            if (!empty($profile[$field])) {
                $optional_completed++;
            }
        }

        // Core fields are worth 70%, optional fields are worth 30%
        $core_weight = 70;
        $optional_weight = 30;

        $core_percentage = ($core_completed / count($core_fields)) * $core_weight;
        $optional_percentage = ($optional_completed / count($optional_fields)) * $optional_weight;

        return round($core_percentage + $optional_percentage);
    }

    /**
     * Check if profile has minimum required fields for matching
     */
    public function is_profile_match_ready($profile)
    {
        $minimum_fields = ['full_name', 'years_experience', 'skills'];

        foreach ($minimum_fields as $field) {
            if (empty($profile[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get user's match threshold (default 60)
     */
    public function get_user_match_threshold($user_id = null)
    {
        if (!$user_id && is_user_logged_in()) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return 60; // Default threshold
        }

        global $wpdb;
        $profile_table = $wpdb->prefix . 'sffc_user_profiles';

        $threshold = $wpdb->get_var($wpdb->prepare(
            "SELECT match_threshold FROM $profile_table WHERE user_id = %d",
            $user_id
        ));

        return $threshold ? intval($threshold) : 60;
    }

    /**
     * AJAX: Save profile answer
     */
    public function ajax_save_profile_answer()
    {
        check_ajax_referer('sffc_profile_nonce', 'nonce');

        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error([
                'message' => 'Please log in to save your profile',
                'login_url' => get_option('sffc_login_url', 'https://joinsenna.com/login-auth/'),
                'require_auth' => true
            ]);
            return;
        }

        $field = sanitize_text_field($_POST['field'] ?? '');
        $value = $_POST['value'] ?? '';
        $question_id = sanitize_text_field($_POST['question_id'] ?? '');

        if (empty($field)) {
            wp_send_json_error(['message' => 'Invalid field']);
            return;
        }

        // Sanitize value based on field type
        if (is_array($value)) {
            $value = array_map('sanitize_text_field', $value);
        } else {
            $value = sanitize_text_field($value);
        }

        global $wpdb;

        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $profile_table = $wpdb->prefix . 'sffc_user_profiles';

            // Check if profile exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT user_id FROM $profile_table WHERE user_id = %d",
                $user_id
            ));

            if ($exists) {
                // Update existing profile
                $wpdb->update(
                    $profile_table,
                    [
                        $field => is_array($value) ? serialize($value) : $value,
                        'updated_at' => current_time('mysql')
                    ],
                    ['user_id' => $user_id]
                );
            } else {
                // Create new profile
                $wpdb->insert(
                    $profile_table,
                    [
                        'user_id' => $user_id,
                        $field => is_array($value) ? serialize($value) : $value,
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    ]
                );
            }

            // Handle skills separately
            if ($field === 'skills' && is_array($value)) {
                $skills_table = $wpdb->prefix . 'sffc_user_skills';

                // Clear existing skills
                $wpdb->delete($skills_table, ['user_id' => $user_id]);

                // Add new skills
                foreach ($value as $skill) {
                    $wpdb->insert(
                        $skills_table,
                        [
                            'user_id' => $user_id,
                            'skill_name' => $skill,
                            'proficiency_level' => 'Advanced',
                            'created_at' => current_time('mysql')
                        ]
                    );
                }
            }
        } else {
            // Save to session for anonymous users
            $session_id = $this->get_session_id();
            $profile = get_transient('sffc_profile_' . $session_id) ?: [];

            $profile[$field] = $value;
            $profile['updated_at'] = current_time('mysql');

            set_transient('sffc_profile_' . $session_id, $profile, DAY_IN_SECONDS);
        }

        // Trigger profile update event
        do_action('sffc_profile_updated', $field, $value);

        wp_send_json_success([
            'message' => 'Answer saved',
            'field' => $field,
            'value' => $value
        ]);
    }

    /**
     * AJAX: Get user profile
     */
    public function ajax_get_user_profile()
    {
        check_ajax_referer('sffc_profile_nonce', 'nonce');

        $profile = $this->get_user_profile();

        wp_send_json_success(['profile' => $profile]);
    }

    /**
     * AJAX: Complete profile and trigger match calculation
     */
    public function ajax_complete_profile()
    {
        check_ajax_referer('sffc_profile_nonce', 'nonce');

        $profile = $this->get_user_profile();

        // Trigger event for match recalculation
        do_action('sffc_profile_completed', $profile);

        wp_send_json_success([
            'message' => 'Profile completed',
            'redirect' => get_permalink(get_page_by_path('opportunities')),
            'completion' => $profile['completion']
        ]);
    }
}

// Initialize
SFFC_Profile_Builder_Frontend::get_instance();
