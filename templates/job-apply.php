<?php

/**
 * Job Apply Template
 *
 * Clean Skyscanner-inspired layout for job application options.
 *
 * @package SennaCareers
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the job apply page
 *
 * @param array $args {
 *     @type int $post_id The job post ID
 * }
 */
function sffc_render_job_apply($args)
{
    $post_id = $args['post_id'] ?? 0;

    if (!$post_id) {
        echo '<!-- job apply: no post id -->';
        return;
    }

    $post = get_post($post_id);
    if (!$post) {
        echo '<!-- job apply: post not found -->';
        return;
    }

    // Enqueue CSS
    if (!wp_style_is('sffc-job-apply', 'enqueued')) {
        $css_path = SFFC_PLUGIN_DIR . 'assets/css/job-apply.css';
        $css_version = defined('SFFC_VERSION') ? SFFC_VERSION : '1.0.0';
        if (file_exists($css_path)) {
            $css_version .= '.' . filemtime($css_path);
        }
        wp_enqueue_style(
            'sffc-job-apply',
            SFFC_PLUGIN_URL . 'assets/css/job-apply.css',
            array(),
            $css_version
        );
    }

    $user_id = get_current_user_id();
    $is_logged_in = $user_id > 0;
    $current_user = $is_logged_in ? wp_get_current_user() : null;
    $prefill_first = $current_user ? ($current_user->first_name ?: '') : '';
    $prefill_last = $current_user ? ($current_user->last_name ?: '') : '';
    $prefill_email = $current_user ? $current_user->user_email : '';

    $membership_cta_url = home_url('/join-senna/');
    $crm_base_url = home_url('/terminal/');

    $has_membership = false;
    if ($is_logged_in && class_exists('SFFC_MemberPress_Integration')) {
        $mp_integration = SFFC_MemberPress_Integration::get_instance();
        $has_membership = $mp_integration->has_insider_access($user_id) || $mp_integration->has_premium_access($user_id);
    }

    $membership_plans = [];
    if (class_exists('SFFC_PE_News_Dashboard')) {
        $dashboard_instance = SFFC_PE_News_Dashboard::get_instance();
        if ($dashboard_instance && method_exists($dashboard_instance, 'get_subscription_plans')) {
            $membership_plans = $dashboard_instance->get_subscription_plans();
        }
    }

    if (empty($membership_plans)) {
        $stored_plans = get_option('sffc_dashboard_plans', []);
        if (!empty($stored_plans) && is_array($stored_plans)) {
            foreach ($stored_plans as $plan) {
                if (empty($plan['name'])) {
                    continue;
                }

                $features = $plan['features'] ?? [];
                if (is_string($features)) {
                    $features = preg_split("/\r?\n/", $features);
                }
                $features = is_array($features) ? array_filter(array_map('trim', $features)) : [];

                $membership_plans[] = [
                    'name' => sanitize_text_field($plan['name']),
                    'slug' => sanitize_title($plan['slug'] ?? $plan['name']),
                    'price' => sanitize_text_field($plan['price'] ?? ''),
                    'price_amount' => isset($plan['price_amount']) ? floatval($plan['price_amount']) : '',
                    'price_currency' => strtoupper(sanitize_text_field($plan['price_currency'] ?? get_option('currency_detector_base_currency', 'USD'))),
                    'billing_cycle' => sanitize_text_field($plan['billing_cycle'] ?? ''),
                    'tagline' => sanitize_text_field($plan['tagline'] ?? ''),
                    'audience' => sanitize_text_field($plan['audience'] ?? ''),
                    'mp_url' => esc_url_raw($plan['mp_url'] ?? ''),
                    'shortcode' => isset($plan['shortcode']) ? wp_kses_post($plan['shortcode']) : '',
                    'features' => $features,
                ];
            }
        }
    }

    // Get job metadata
    $job_title = get_the_title($post_id);
    $job_location = get_post_meta($post_id, '_job_location', true);

    // Check taxonomy for location if meta field is empty
    $locations = wp_get_post_terms($post_id, 'recruiter_post_location', ['fields' => 'names']);
    $location_label = !empty($locations) ? $locations[0] : $job_location;

    $job_content = apply_filters('the_content', $post->post_content);

    // Get recruiter info
    $recruiter_email = get_post_meta($post_id, '_recruiter_email', true);
    $recruiter_name = get_post_meta($post_id, '_recruiter_name', true);
    $company_name = get_post_meta($post_id, '_recruiter_company', true);
    if (!$company_name) {
        $company_name = get_post_meta($post_id, '_company_name', true);
    }

    // Get application data & knockout configuration
    global $wpdb;
    $application_url = '';
    $recruiter_id = 0;
    $knockout_questions = [];

    $crm_post_id = (int) get_post_meta($post_id, '_crm_post_id', true);
    if (!$crm_post_id && isset($wpdb)) {
        $crm_post_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}sffc_crm_posts WHERE wp_post_id = %d LIMIT 1",
            $post_id
        ));
    }

    if ($crm_post_id && isset($wpdb)) {
        $crm_row = $wpdb->get_row($wpdb->prepare(
            "SELECT recruiter_id, application_url, knockout_questions
             FROM {$wpdb->prefix}sffc_crm_posts WHERE id = %d LIMIT 1",
            $crm_post_id
        ), ARRAY_A);

        if ($crm_row) {
            $application_url = $crm_row['application_url'] ?? '';
            $recruiter_id = isset($crm_row['recruiter_id']) ? (int) $crm_row['recruiter_id'] : 0;

            if (!empty($crm_row['knockout_questions'])) {
                $decoded_questions = json_decode($crm_row['knockout_questions'], true);
                if (is_array($decoded_questions)) {
                    foreach ($decoded_questions as $index => $question) {
                        $prompt = trim($question['prompt'] ?? ($question['question'] ?? ''));
                        if (!$prompt) {
                            continue;
                        }

                        $allowed_types = ['yes_no', 'single_choice', 'number', 'text'];
                        $type = $question['type'] ?? 'text';
                        if (!in_array($type, $allowed_types, true)) {
                            $type = 'text';
                        }

                        $options = [];
                        if (!empty($question['options'])) {
                            if (is_array($question['options'])) {
                                $options = array_values(array_filter(array_map('sanitize_text_field', $question['options'])));
                            } elseif (is_string($question['options'])) {
                                $option_parts = array_map('trim', explode(',', $question['options']));
                                $options = array_values(array_filter(array_map('sanitize_text_field', $option_parts)));
                            }
                        }

                        $ideal_answer = isset($question['ideal_answer']) ? $question['ideal_answer'] : ($question['desired_response'] ?? '');
                        $helper_text = isset($question['helper_text']) ? $question['helper_text'] : '';
                        $weight = isset($question['weight']) ? (int) $question['weight'] : 1;
                        $weight = max(1, min(5, $weight));
                        $question_id = sanitize_key($question['id'] ?? ('ko_' . $post_id . '_' . ($index + 1)));

                        $question_modes = $question['modes'] ?? ['recruiter'];
                        if (!is_array($question_modes)) {
                            $question_modes = [$question_modes];
                        }
                        $question_modes = array_map('sanitize_key', $question_modes);
                        $question_modes = array_values(array_intersect($question_modes, ['recruiter', 'smart_apply']));
                        if (empty($question_modes)) {
                            $question_modes = ['recruiter'];
                        }

                        $knockout_questions[] = [
                            'id' => $question_id,
                            'prompt' => $prompt,
                            'type' => $type,
                            'ideal_answer' => $ideal_answer,
                            'helper_text' => $helper_text,
                            'weight' => $weight,
                            'options' => $options,
                            'modes' => $question_modes,
                        ];
                    }
                }
            }
        }
    }

    $mode_question_map = [
        'recruiter' => [],
        'smart_apply' => [],
    ];
    foreach ($knockout_questions as $question_item) {
        $question_modes = $question_item['modes'] ?? ['recruiter'];
        foreach ($question_modes as $mode_key) {
            if (!isset($mode_question_map[$mode_key])) {
                $mode_question_map[$mode_key] = [];
            }
            $mode_question_map[$mode_key][] = $question_item;
        }
    }

    $has_recruiter_questions = !empty($mode_question_map['recruiter']);
    $has_smart_apply_questions = !empty($mode_question_map['smart_apply']);
    $default_mode = $has_recruiter_questions ? 'recruiter' : ($has_smart_apply_questions ? 'smart_apply' : 'recruiter');

    $review_step_number = ($has_recruiter_questions || $has_smart_apply_questions) ? 3 : 2;
    $progress_labels = [
        ['step' => 1, 'label' => __('Express Interest', 'senna-finance')],
    ];
    if ($has_recruiter_questions || $has_smart_apply_questions) {
        $progress_labels[] = ['step' => 2, 'label' => __('Match analysis', 'senna-finance')];
    }
    $progress_labels[] = ['step' => $review_step_number, 'label' => __('Review & action', 'senna-finance')];

    $render_knockout_question = function ($question, $position, $is_required = false, $mode = 'recruiter') {
        if (!$question || empty($question['prompt'])) {
            return '';
        }

        $question_id = sanitize_key($question['id'] ?? ('ko_' . $position));
        $prompt = $question['prompt'];
        $ideal = isset($question['ideal_answer']) ? $question['ideal_answer'] : '';
        $helper = isset($question['helper_text']) ? $question['helper_text'] : '';
        $type = isset($question['type']) ? $question['type'] : 'text';
        $weight = isset($question['weight']) ? (int) $question['weight'] : 1;
        $weight = max(1, min(5, $weight));
        $options = isset($question['options']) && is_array($question['options']) ? $question['options'] : [];
        $options = array_values(array_filter(array_map('sanitize_text_field', $options)));
        $required_attr = $is_required ? 'required' : '';

        ob_start();
?>
        <div class="sffc-job-apply__question"
            data-question-id="<?php echo esc_attr($question_id); ?>"
            data-question-type="<?php echo esc_attr($type); ?>"
            data-question-weight="<?php echo esc_attr($weight); ?>"
            data-question-desired="<?php echo esc_attr($ideal); ?>"
            data-question-label="<?php echo esc_attr($prompt); ?>"
            data-question-mode="<?php echo esc_attr($mode); ?>">
            <div class="sffc-job-apply__question-head">
                <span class="sffc-job-apply__question-pill">Q<?php echo esc_html($position); ?></span>
                <div>
                    <p class="sffc-job-apply__question-title"><?php echo esc_html($prompt); ?></p>
                    <?php if ($helper): ?>
                        <p class="sffc-job-apply__question-helper"><?php echo esc_html($helper); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="sffc-job-apply__question-field">
                <?php if ($type === 'yes_no'): ?>
                    <div class="sffc-job-apply__choice-group">
                        <?php $yes_id = $question_id . '_yes';
                        $no_id = $question_id . '_no'; ?>
                        <label class="sffc-job-apply__choice" for="<?php echo esc_attr($yes_id); ?>">
                            <input type="radio"
                                id="<?php echo esc_attr($yes_id); ?>"
                                name="knockout_answers[<?php echo esc_attr($question_id); ?>]"
                                value="Yes"
                                data-step-field="true"
                                data-question-input="true"
                                <?php echo $required_attr; ?>>
                            <span><?php esc_html_e('Yes', 'senna-finance'); ?></span>
                        </label>
                        <label class="sffc-job-apply__choice" for="<?php echo esc_attr($no_id); ?>">
                            <input type="radio"
                                id="<?php echo esc_attr($no_id); ?>"
                                name="knockout_answers[<?php echo esc_attr($question_id); ?>]"
                                value="No"
                                data-step-field="true"
                                data-question-input="true">
                            <span><?php esc_html_e('No', 'senna-finance'); ?></span>
                        </label>
                    </div>
                <?php elseif ($type === 'single_choice' && !empty($options)): ?>
                    <div class="sffc-job-apply__choice-list">
                        <?php foreach ($options as $idx => $option_label):
                            $option_id = $question_id . '_opt_' . $idx;
                        ?>
                            <label class="sffc-job-apply__choice" for="<?php echo esc_attr($option_id); ?>">
                                <input type="radio"
                                    id="<?php echo esc_attr($option_id); ?>"
                                    name="knockout_answers[<?php echo esc_attr($question_id); ?>]"
                                    value="<?php echo esc_attr($option_label); ?>"
                                    data-step-field="true"
                                    data-question-input="true"
                                    <?php echo $idx === 0 ? $required_attr : ''; ?>>
                                <span><?php echo esc_html($option_label); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($type === 'number'): ?>
                    <input type="number"
                        class="sffc-job-apply__input"
                        name="knockout_answers[<?php echo esc_attr($question_id); ?>]"
                        data-question-input="true"
                        data-step-field="true"
                        inputmode="decimal"
                        <?php echo $required_attr; ?>
                        placeholder="<?php echo esc_attr($ideal ? sprintf(__('Aim for %s', 'senna-finance'), $ideal) : __('Enter a number', 'senna-finance')); ?>">
                <?php else: ?>
                    <textarea class="sffc-job-apply__textarea"
                        name="knockout_answers[<?php echo esc_attr($question_id); ?>]"
                        rows="3"
                        data-question-input="true"
                        data-step-field="true"
                        <?php echo $required_attr; ?>
                        placeholder="<?php esc_attr_e('Share context so recruiters understand your fit.', 'senna-finance'); ?>"></textarea>
                <?php endif; ?>
            </div>
        </div>
    <?php
        return ob_get_clean();
    };

    $job_apply_config = [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'expressNonce' => wp_create_nonce('sffc_express_interest_nonce'),
        'crmNonce' => wp_create_nonce('sffc_crm_nonce'),
        'jobId' => $post_id,
        'crmPostId' => $crm_post_id,
        'recruiterId' => $recruiter_id,
        'recruiterEmail' => $recruiter_email,
        'roleTitle' => $job_title,
        'companyName' => $company_name,
        'location' => $location_label,
        'crmUrl' => $crm_base_url,
        'heroCtaUrl' => $crm_base_url,
        'introUrl' => $crm_base_url,
        'externalUrl' => $application_url,
        'knockoutQuestions' => $knockout_questions,
        'modeQuestions' => [
            'recruiter' => $mode_question_map['recruiter'],
            'smart_apply' => $mode_question_map['smart_apply'],
        ],
        'defaultMode' => $default_mode,
        'smartApplyUrl' => $crm_base_url,
        'membership' => [
            'isLoggedIn' => $is_logged_in,
            'hasMembership' => $has_membership,
            'loginUrl' => wp_login_url(get_permalink($post_id)),
            'upgradeUrl' => $membership_cta_url,
            'joinUrl' => $membership_cta_url,
        ],
        'user' => [
            'firstName' => $prefill_first,
            'lastName' => $prefill_last,
            'email' => $prefill_email,
        ],
    ];

    ?>
    <section class="sffc-job-apply__hero">
        <div class="sffc-job-apply__hero-content">
            <div class="sffc-job-apply__hero-pill">Express Interest Access</div>
            <h1>Get closer to private equity recruiters across London, Europe, and Dubai in 1 click.</h1>
            <p class="sffc-job-apply__hero-subtext">
                Instead of mass applying, MENA Careers pairs you with recruiters who are actively hiring for private equity and finance roles across London, Paris, Milan, Amsterdam, Dubai, and other high-signal markets. Every outreach is targeted, tracked, and transparent.
            </p>
            <ul class="sffc-job-apply__hero-highlights">
                <li><strong>Express Interest:</strong> 1 of 5 curated candidates, tracked inside the CRM.</li>
                <li><strong>Smart message:</strong> 50+ targeted submissions with AI-tailored materials.</li>
                <li><strong>Transparency:</strong> Live metrics show who we contacted and when they reply.</li>
            </ul>
            <div class="sffc-job-apply__hero-actions">
                <a href="#apply-options" class="sffc-job-apply__hero-cta" data-scroll-to="apply-options">Join MENA Careers</a>
                <span class="sffc-job-apply__hero-rating">★★★★★ Loved by 1,640 private equity and finance candidates across London, Europe, and Dubai</span>
            </div>
        </div>
        <div class="sffc-job-apply__hero-role">
            <div class="sffc-job-apply__hero-role-head">Live mandate</div>
            <div class="sffc-job-apply__hero-role-main">
                <h3><?php echo esc_html($job_title); ?></h3>
                <p><?php echo esc_html($company_name ?: 'Confidential Firm'); ?> · <?php echo esc_html($location_label ?: 'Global'); ?></p>
            </div>
            <ul class="sffc-job-apply__hero-role-list">
                <li class="sffc-job-apply__hero-role-option is-winner">
                    <div class="sffc-job-apply__hero-role-label">
                        Express Interest
                        <span class="sffc-job-apply__hero-role-winner">Most Popular</span>
                    </div>
                    <p>Become <span>1 of 5</span> curated candidates and follow every reply inside the CRM live.</p>
                </li>
                <li class="sffc-job-apply__hero-role-option">
                    <div class="sffc-job-apply__hero-role-label">Smart message (50+ roles)</div>
                    <p><span>Targeted applications</span> to 50+ matching mandates with AI-tailored materials.</p>
                </li>
            </ul>
            <div class="sffc-job-apply__hero-recruiter">
                <div class="sffc-job-apply__hero-recruiter-avatar">
                    <img src="https://media.joinsenna.com/2025/11/242769510-smile-portrait-and-face-young--scaled.jpeg?1764020340" alt="Cristina M" />
                </div>
                <div class="sffc-job-apply__hero-recruiter-info">
                    <strong>Cristina M.</strong>
                    <span>Private Equity & Finance Recruiter</span>
                </div>
                <a href="#apply-options" class="sffc-job-apply__hero-role-btn" data-scroll-to="apply-options">Express Interest</a>
            </div>
            <form class="sffc-job-apply__hero-capture" autocomplete="off" data-scroll-to="apply-options">
                <input type="email" placeholder="you@company.com" required>
                <button type="submit">Get Started</button>
                <small>We'll take you straight into the wizard.</small>
            </form>
        </div>
    </section>

    <div class="sffc-job-apply">
        <!-- Header Section -->
        <header class="sffc-job-apply__header">
            <div class="sffc-job-apply__branding">
                <div class="sffc-job-apply__logo">MENA Careers</div>
                <div class="sffc-job-apply__tagline">Finance Career Guidance</div>
            </div>
            <div class="sffc-job-apply__job-info">
                <h1 class="sffc-job-apply__title"><?php echo esc_html($job_title); ?></h1>
                <?php if ($location_label): ?>
                    <div class="sffc-job-apply__location"><?php echo esc_html($location_label); ?></div>
                <?php endif; ?>
            </div>
            <div class="sffc-job-apply__header-actions">
                <a href="#apply-options" class="sffc-job-apply__apply-btn" data-scroll-to="apply-options">
                    Smart message
                </a>
            </div>
        </header>

        <!-- Job Description Body -->
        <div class="sffc-job-apply__body">
            <div class="sffc-job-apply__content">
                <?php echo $job_content; ?>
            </div>
        </div>

        <section id="apply-options" class="sffc-job-apply__application">
            <div class="sffc-job-apply__application-head">
                <div>
                    <h2>
                        <span class="sffc-job-apply__application-h2-lead">Get Introduced to Recruiters.</span>
                        <span class="sffc-job-apply__application-h2-accent">Let's Start.</span>
                    </h2>
                    <p>
                        Complete a two-step fit check so we can calculate your match score, set expectations, and open the door to private equity and finance recruiters who already trust MENA Careers.
                    </p>
                </div>
            </div>

            <div class="sffc-job-apply__wizard" id="sffcJobApplyWizard">
                <div class="sffc-job-apply__progress">
                    <div class="sffc-job-apply__progress-track">
                        <div class="sffc-job-apply__progress-bar" style="width:0%;"></div>
                    </div>
                    <div class="sffc-job-apply__progress-labels">
                        <?php foreach ($progress_labels as $label): ?>
                            <span data-progress-step="<?php echo esc_attr($label['step']); ?>">
                                <?php echo esc_html($label['label']); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <form class="sffc-job-apply__wizard-form" id="sffcJobApplyForm" autocomplete="off">
                    <div class="sffc-job-apply__wizard-step is-active" data-step="1" aria-live="polite">
                        <header class="sffc-job-apply__wizard-step-head">
                            <span class="sffc-job-apply__wizard-step-pill">Step 1</span>
                            <div>
                                <h3>Share your details</h3>
                                <p>We preload your CRM workspace so express-interest notes can go out quickly.</p>
                            </div>
                        </header>
                        <div class="sffc-job-apply__fields-grid">
                            <label>
                                <span><?php esc_html_e('First name', 'senna-finance'); ?></span>
                                <input type="text" id="jobApplyWizardFirstName" name="job_apply_first_name" value="<?php echo esc_attr($prefill_first); ?>" required data-step-field="true" autocomplete="given-name">
                            </label>
                            <label>
                                <span><?php esc_html_e('Last name', 'senna-finance'); ?></span>
                                <input type="text" id="jobApplyWizardLastName" name="job_apply_last_name" value="<?php echo esc_attr($prefill_last); ?>" required data-step-field="true" autocomplete="family-name">
                            </label>
                            <label>
                                <span><?php esc_html_e('Email', 'senna-finance'); ?></span>
                                <input type="email" id="jobApplyWizardEmail" name="job_apply_email" value="<?php echo esc_attr($prefill_email); ?>" required data-step-field="true" autocomplete="email">
                            </label>
                            <label>
                                <span><?php esc_html_e('LinkedIn (optional)', 'senna-finance'); ?></span>
                                <input type="url" id="jobApplyWizardLinkedIn" name="job_apply_linkedin" placeholder="https://www.linkedin.com/in/you" data-step-field="true">
                            </label>
                        </div>
                        <div class="sffc-job-apply__mode-choice" role="radiogroup">
                            <label class="sffc-job-apply__mode-card <?php echo $default_mode === 'recruiter' ? 'is-active' : ''; ?>">
                                <input type="radio"
                                    name="job_apply_mode"
                                    value="recruiter"
                                    data-step-field="true"
                                    required
                                    <?php checked($default_mode, 'recruiter'); ?>
                                    class="sffc-job-apply__mode-input" />
                                <div class="sffc-job-apply__mode-card-head">
                                    <span class="sffc-job-apply__mode-card-label"><?php esc_html_e('Express Interest', 'senna-finance'); ?></span>
                                    <span class="sffc-job-apply__mode-card-pill"><?php esc_html_e('Handpicked recruiters', 'senna-finance'); ?></span>
                                </div>
                                <p><?php esc_html_e('We match you to live mandates, express interest with concierge copy, and log every outreach in your CRM.', 'senna-finance'); ?></p>
                                <ul>
                                    <li><?php esc_html_e('1 of 5 curated candidates', 'senna-finance'); ?></li>
                                    <li><?php esc_html_e('Live visibility when recruiters reply', 'senna-finance'); ?></li>
                                </ul>
                            </label>
                            <label class="sffc-job-apply__mode-card <?php echo $default_mode === 'smart_apply' ? 'is-active' : ''; ?>">
                                <input type="radio"
                                    name="job_apply_mode"
                                    value="smart_apply"
                                    data-step-field="true"
                                    required
                                    <?php checked($default_mode, 'smart_apply'); ?>
                                    class="sffc-job-apply__mode-input" />
                                <div class="sffc-job-apply__mode-card-head">
                                    <span class="sffc-job-apply__mode-card-label"><?php esc_html_e('Smart message (50+ Roles)', 'senna-finance'); ?></span>
                                    <span class="sffc-job-apply__mode-card-pill"><?php esc_html_e('Targeted applications', 'senna-finance'); ?></span>
                                </div>
                                <p><?php esc_html_e('We shortlist 50+ matching roles, tailor your materials, and MENA Careers submits on your behalf — no spray and pray.', 'senna-finance'); ?></p>
                                <ul>
                                    <li><?php esc_html_e('High matching roles + tailored CV & cover letter', 'senna-finance'); ?></li>
                                    <li><?php esc_html_e('Auto-tracks every submission on the MENA Careers Dashboard', 'senna-finance'); ?></li>
                                </ul>
                            </label>
                        </div>
                        <div class="sffc-job-apply__wizard-actions">
                            <button type="button" class="sffc-job-apply__wizard-btn sffc-job-apply__wizard-btn--primary" data-step-next>
                                <?php esc_html_e('Continue', 'senna-finance'); ?>
                            </button>
                        </div>
                    </div>

                    <?php if ($has_recruiter_questions || $has_smart_apply_questions): ?>
                        <div class="sffc-job-apply__wizard-step" data-step="2" aria-live="polite">
                            <header class="sffc-job-apply__wizard-step-head">
                                <span class="sffc-job-apply__wizard-step-pill">Step 2</span>
                                <div>
                                    <h3 data-mode-step-title
                                        data-copy-recruiter="<?php echo esc_attr__('Express Interest brief', 'senna-finance'); ?>"
                                        data-copy-smart="<?php echo esc_attr__('Smart message brief', 'senna-finance'); ?>">
                                        <?php echo $default_mode === 'smart_apply' ? esc_html__('Smart message brief', 'senna-finance') : esc_html__('Express Interest brief', 'senna-finance'); ?>
                                    </h3>
                                    <p data-mode-step-desc
                                        data-copy-recruiter="<?php echo esc_attr__('Answer honestly so we can express your interest with full context.', 'senna-finance'); ?>"
                                        data-copy-smart="<?php echo esc_attr__('Tell us how to target your Smart message run so we don\'t spray and pray.', 'senna-finance'); ?>">
                                        <?php echo $default_mode === 'smart_apply'
                                            ? esc_html__('Tell us how to target your Smart message run so we don\'t spray and pray.', 'senna-finance')
                                            : esc_html__('Answer honestly so we can express your interest with full context.', 'senna-finance'); ?>
                                    </p>
                                </div>
                            </header>
                            <div class="sffc-job-apply__mode-questions <?php echo $default_mode === 'recruiter' ? 'is-active' : ''; ?>" data-mode-panel="recruiter">
                                <?php if ($has_recruiter_questions): ?>
                                    <div class="sffc-job-apply__question-stack">
                                        <?php $recruiter_counter = 1; ?>
                                        <?php foreach ($mode_question_map['recruiter'] as $question): ?>
                                            <?php echo $render_knockout_question($question, $recruiter_counter, $recruiter_counter === 1, 'recruiter'); ?>
                                            <?php $recruiter_counter++; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="sffc-job-apply__no-question">
                                        <?php esc_html_e('No express-interest questions yet — we will still review your note manually.', 'senna-finance'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="sffc-job-apply__mode-questions <?php echo $default_mode === 'smart_apply' ? 'is-active' : ''; ?>" data-mode-panel="smart_apply">
                                <?php if ($has_smart_apply_questions): ?>
                                    <div class="sffc-job-apply__question-stack">
                                        <?php $smart_counter = 1; ?>
                                        <?php foreach ($mode_question_map['smart_apply'] as $question): ?>
                                            <?php echo $render_knockout_question($question, $smart_counter, $smart_counter === 1, 'smart_apply'); ?>
                                            <?php $smart_counter++; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="sffc-job-apply__no-question">
                                        <?php esc_html_e('Smart message questions have not been set for this role yet.', 'senna-finance'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="sffc-job-apply__wizard-actions">
                                <button type="button" class="sffc-job-apply__wizard-btn" data-step-prev>
                                    <?php esc_html_e('Back', 'senna-finance'); ?>
                                </button>
                                <button type="button" class="sffc-job-apply__wizard-btn sffc-job-apply__wizard-btn--primary" data-step-next>
                                    <?php esc_html_e('Review match', 'senna-finance'); ?>
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="sffc-job-apply__wizard-step" data-step="<?php echo esc_attr($review_step_number); ?>" aria-live="polite">
                        <header class="sffc-job-apply__wizard-step-head">
                            <span class="sffc-job-apply__wizard-step-pill">Step <?php echo esc_html($review_step_number); ?></span>
                            <div>
                                <h3>Review & choose your path</h3>
                                <p>We translate your answers into a transparent match score so you know what happens next.</p>
                            </div>
                        </header>
                        <div class="sffc-job-apply__result-card">
                            <div class="sffc-job-apply__result-score" data-match-score-wrapper>
                                <span class="sffc-job-apply__result-value" data-match-score>--%</span>
                                <span class="sffc-job-apply__result-state" data-match-state><?php esc_html_e('Complete the steps to see your match.', 'senna-finance'); ?></span>
                            </div>
                            <div class="sffc-job-apply__result-meter">
                                <span class="sffc-job-apply__result-meter-bar" data-match-meter style="width:0%;"></span>
                            </div>
                            <p class="sffc-job-apply__result-message" data-match-message>
                                <?php esc_html_e('We will still advocate for you even if the score is lighter — this just sets expectations for response times.', 'senna-finance'); ?>
                            </p>
                        </div>
                        <div class="sffc-job-apply__breakdown" data-match-breakdown>
                            <p class="sffc-job-apply__breakdown-placeholder"><?php esc_html_e('Answer the questions above to see a transparent breakdown.', 'senna-finance'); ?></p>
                        </div>
                        <div class="sffc-job-apply__cta">
                            <button type="button"
                                class="sffc-job-apply__cta-btn sffc-job-apply__cta-btn--primary"
                                data-submit-primary
                                data-text-recruiter="<?php echo esc_attr__('Express Interest', 'senna-finance'); ?>"
                                data-text-smart="<?php echo esc_attr__('Start Smart message (50+ Roles)', 'senna-finance'); ?>">
                                <?php echo $default_mode === 'smart_apply'
                                    ? esc_html__('Start Smart message (50+ Roles)', 'senna-finance')
                                    : esc_html__('Express Interest', 'senna-finance'); ?>
                            </button>
                            <?php if ($application_url): ?>
                                <button type="button" class="sffc-job-apply__cta-btn sffc-job-apply__cta-btn--ghost"
                                    data-submit-external
                                    data-text-recruiter="<?php echo esc_attr__('Continue without Express Interest', 'senna-finance'); ?>"
                                    data-text-smart="<?php echo esc_attr__('Continue without Smart message', 'senna-finance'); ?>"
                                    data-external-url="<?php echo esc_url($application_url); ?>">
                                    <?php esc_html_e('Continue without Express Interest', 'senna-finance'); ?>
                                </button>
                            <?php else: ?>
                                <button type="button" class="sffc-job-apply__cta-btn sffc-job-apply__cta-btn--ghost is-disabled" disabled>
                                    <?php esc_html_e('External apply unavailable', 'senna-finance'); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                        <p class="sffc-job-apply__cta-note"
                            data-copy-recruiter="<?php echo esc_attr__('Prefer to go solo? Continuing without Express Interest stays free—MENA Careers increases your odds of getting seen by 5x.', 'senna-finance'); ?>"
                            data-copy-smart="<?php echo esc_attr__('Smart message is optional — you can keep applying manually for free, we simply add the 50+ targeted run.', 'senna-finance'); ?>">
                            <?php esc_html_e('Prefer to go solo? Continuing without Express Interest stays free—MENA Careers increases your odds of getting seen by 5x.', 'senna-finance'); ?>
                        </p>
                        <div class="sffc-job-apply__flash" data-wizard-feedback style="display:none;"></div>
                    </div>
                </form>
            </div>
        </section>

        <?php if (!empty($membership_plans)) : ?>
            <div class="sffc-job-plan-modal" id="jobApplyMembershipModal" aria-hidden="true">
                <div class="sffc-job-plan-modal__overlay" data-membership-close></div>
                <div class="sffc-job-plan-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="jobPlanModalTitle">
                    <button type="button" class="sffc-job-plan-modal__close" data-membership-close aria-label="<?php esc_attr_e('Close membership options', 'senna-finance'); ?>">&times;</button>
                    <div class="sffc-job-plan-modal__header">
                        <p class="sffc-job-plan-modal__eyebrow"><?php esc_html_e('Premium access required', 'senna-finance'); ?></p>
                        <h3 id="jobPlanModalTitle">
                            <span data-plan-first-name><?php echo esc_html($prefill_first ?: __('You', 'senna-finance')); ?></span><?php esc_html_e(', you are one step away from landing interviews.', 'senna-finance'); ?>
                        </h3>
                        <p class="sffc-job-plan-modal__subtext"
                            data-plan-mode-line
                            data-copy-recruiter="<?php esc_attr_e('Unlock concierge Express Interest submissions, live CRM tracking, and recruiter-facing messaging support.', 'senna-finance'); ?>"
                            data-copy-smart="<?php esc_attr_e('Let MENA Careers run Smart message so 50+ high-match roles receive tailored outreach on your behalf.', 'senna-finance'); ?>">
                            <?php esc_html_e('Unlock concierge Express Interest submissions, live CRM tracking, and recruiter-facing messaging support.', 'senna-finance'); ?>
                        </p>
                        <div class="sffc-job-plan-modal__chips">
                            <span class="sffc-job-plan-modal__chip"
                                data-plan-mode-chip
                                data-copy-recruiter="<?php esc_attr_e('Express Interest', 'senna-finance'); ?>"
                                data-copy-smart="<?php esc_attr_e('Smart message', 'senna-finance'); ?>">
                                <?php esc_html_e('Express Interest', 'senna-finance'); ?>
                            </span>
                            <span class="sffc-job-plan-modal__rating">★★★★★ <?php esc_html_e('Loved by 1,640 finance professionals', 'senna-finance'); ?></span>
                        </div>
                    </div>
                    <div class="sffc-job-plan-modal__body">
                        <div class="sffc-job-plan-modal__plans">
                            <?php foreach ($membership_plans as $index => $plan):
                                $plan_slug = sanitize_title($plan['slug'] ?? $plan['name']);
                                $plan_price = $plan['price'] ?? '';
                                $plan_url = !empty($plan['mp_url']) ? $plan['mp_url'] : $membership_cta_url;
                                $is_featured = ($index === count($membership_plans) - 1);
                                $features = isset($plan['features']) && is_array($plan['features']) ? $plan['features'] : [];
                                $plan_message = sprintf(__('Complete checkout to unlock %s.', 'senna-finance'), $plan['name']);
                                $has_shortcode = !empty($plan['shortcode']);
                            ?>
                                <article class="sffc-job-plan-card <?php echo $is_featured ? 'sffc-job-plan-card--highlight' : ''; ?>" data-membership-plan="<?php echo esc_attr($plan_slug); ?>">
                                    <div class="sffc-job-plan-card__head">
                                        <div>
                                            <span class="sffc-job-plan-card__name"><?php echo esc_html($plan['name']); ?></span>
                                            <?php if (!empty($plan['tagline'])) : ?>
                                                <p class="sffc-job-plan-card__tagline"><?php echo esc_html($plan['tagline']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($plan['audience'])) : ?>
                                            <span class="sffc-job-plan-card__audience"><?php echo esc_html($plan['audience']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($plan_price)) : ?>
                                        <p class="sffc-job-plan-card__price">
                                            <strong><?php echo esc_html($plan_price); ?></strong>
                                            <?php if (!empty($plan['billing_cycle'])) : ?>
                                                <span><?php echo esc_html($plan['billing_cycle']); ?></span>
                                            <?php endif; ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if (!empty($features)) : ?>
                                        <ul class="sffc-job-plan-card__features">
                                            <?php foreach ($features as $feature) : ?>
                                                <li>
                                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                                        <polyline points="20 6 9 17 4 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                                    </svg>
                                                    <span><?php echo esc_html($feature); ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                    <button type="button"
                                        class="sffc-job-plan-card__cta"
                                        data-plan-select
                                        data-plan-slug="<?php echo esc_attr($plan_slug); ?>"
                                        data-plan-name="<?php echo esc_attr($plan['name']); ?>"
                                        data-plan-price="<?php echo esc_attr($plan_price); ?>"
                                        data-plan-tagline="<?php echo esc_attr($plan['tagline'] ?? ''); ?>"
                                        data-plan-message="<?php echo esc_attr($plan_message); ?>"
                                        data-plan-url="<?php echo esc_url($plan_url); ?>"
                                        data-has-shortcode="<?php echo $has_shortcode ? 'true' : 'false'; ?>">
                                        <?php esc_html_e('Choose this plan', 'senna-finance'); ?>
                                    </button>
                                </article>
                            <?php endforeach; ?>
                        </div>
                        <div class="sffc-job-plan-modal__checkout" data-plan-checkout hidden>
                            <p data-plan-message><?php esc_html_e('Pick a plan to unlock the secure checkout.', 'senna-finance'); ?></p>
                            <?php foreach ($membership_plans as $plan):
                                if (empty($plan['shortcode'])) {
                                    continue;
                                }
                                $plan_slug = sanitize_title($plan['slug'] ?? $plan['name']);
                            ?>
                                <div class="sffc-job-plan-modal__form" data-plan-form="<?php echo esc_attr($plan_slug); ?>" hidden>
                                    <?php echo do_shortcode($plan['shortcode']); ?>
                                </div>
                            <?php endforeach; ?>
                            <div class="sffc-job-plan-modal__external" data-plan-external hidden>
                                <a href="#" target="_blank" rel="noopener noreferrer" data-plan-external-link><?php esc_html_e('Complete secure checkout in a new tab', 'senna-finance'); ?></a>
                            </div>
                        </div>
                    </div>
                    <div class="sffc-job-plan-modal__footer">
                        <p><?php esc_html_e('Need help deciding? Email concierge@joinsenna.com and we will get you set up.', 'senna-finance'); ?></p>
                    </div>
                </div>
            </div>

            <?php
            /*
         * MemberPress init decoy.
         *
         * MemberPress bootstraps its checkout JS on page load by scanning the live DOM
         * for forms with name="mepr_signup_form" / class="mp_wrapper". Every plan form
         * above lives inside a `hidden` div, which the browser excludes from that scan,
         * so the form outputs unstyled HTML with no JS events.
         *
         * Rendering one shortcode here — outside any `hidden` container — forces
         * MemberPress to register its scripts and initialise its handlers. The decoy is
         * made visually invisible via CSS (.sffc-mepr-init-decoy) using position/clip so
         * the browser still treats it as a live, rendered node.
         */
            $mepr_decoy_plan = null;
            foreach ($membership_plans as $_mp) {
                if (! empty($_mp['shortcode'])) {
                    $mepr_decoy_plan = $_mp;
                    break;
                }
            }
            if ($mepr_decoy_plan) : ?>
                <div class="sffc-mepr-init-decoy" aria-hidden="true" tabindex="-1">
                    <?php echo do_shortcode($mepr_decoy_plan['shortcode']); ?>
                </div>
            <?php endif; ?>

    </div>
<?php endif; ?>

<script>
    const jobApplyConfig = <?php echo wp_json_encode($job_apply_config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    (function() {
        const ajaxUrl = jobApplyConfig.ajaxUrl;
        const expressNonce = jobApplyConfig.expressNonce;
        const scrollTriggers = document.querySelectorAll('[data-scroll-to="apply-options"]');
        if (scrollTriggers.length) {
            scrollTriggers.forEach(function(trigger) {
                if (trigger.tagName === 'FORM') {
                    trigger.addEventListener('submit', function(event) {
                        event.preventDefault();
                        scrollToWizard();
                    });
                } else {
                    trigger.addEventListener('click', function(event) {
                        event.preventDefault();
                        scrollToWizard();
                    });
                }
            });
        }

        const wizard = document.getElementById('sffcJobApplyWizard');
        const form = document.getElementById('sffcJobApplyForm');
        if (!wizard || !form) {
            return;
        }

        const steps = Array.from(wizard.querySelectorAll('.sffc-job-apply__wizard-step'));
        const progressBar = wizard.querySelector('.sffc-job-apply__progress-bar');
        const progressLabels = wizard.querySelectorAll('[data-progress-step]');
        const scoreWrapper = wizard.querySelector('[data-match-score-wrapper]');
        const scoreValue = wizard.querySelector('[data-match-score]');
        const scoreState = wizard.querySelector('[data-match-state]');
        const scoreMeter = wizard.querySelector('[data-match-meter]');
        const breakdownEl = wizard.querySelector('[data-match-breakdown]');
        const feedbackEl = wizard.querySelector('[data-wizard-feedback]');
        const primaryButton = wizard.querySelector('[data-submit-primary]');
        const externalButton = wizard.querySelector('[data-submit-external]');
        const firstNameInput = document.getElementById('jobApplyWizardFirstName');
        const lastNameInput = document.getElementById('jobApplyWizardLastName');
        const emailInput = document.getElementById('jobApplyWizardEmail');
        const linkedinInput = document.getElementById('jobApplyWizardLinkedIn');
        const modeCards = wizard.querySelectorAll('.sffc-job-apply__mode-card');
        const modeInputs = wizard.querySelectorAll('.sffc-job-apply__mode-input');
        const questionPanels = wizard.querySelectorAll('[data-mode-panel]');
        const modeTitleEl = wizard.querySelector('[data-mode-step-title]');
        const modeDescEl = wizard.querySelector('[data-mode-step-desc]');
        const ctaNote = document.querySelector('.sffc-job-apply__cta-note');
        const membership = jobApplyConfig.membership || {};
        const userDefaults = jobApplyConfig.user || {};
        const modeQuestions = jobApplyConfig.modeQuestions || {};
        let selectedMode = jobApplyConfig.defaultMode || 'recruiter';
        const membershipModal = document.getElementById('jobApplyMembershipModal');
        const membershipNameEl = membershipModal ? membershipModal.querySelector('[data-plan-first-name]') : null;
        const membershipModeLine = membershipModal ? membershipModal.querySelector('[data-plan-mode-line]') : null;
        const membershipModeChip = membershipModal ? membershipModal.querySelector('[data-plan-mode-chip]') : null;
        const membershipPlanCards = membershipModal ? membershipModal.querySelectorAll('[data-membership-plan]') : [];
        const membershipPlanButtons = membershipModal ? membershipModal.querySelectorAll('[data-plan-select]') : [];
        const planCheckoutSection = membershipModal ? membershipModal.querySelector('[data-plan-checkout]') : null;
        const planMessageEl = planCheckoutSection ? planCheckoutSection.querySelector('[data-plan-message]') : null;
        const planForms = membershipModal ? membershipModal.querySelectorAll('[data-plan-form]') : [];
        const planExternalBlock = membershipModal ? membershipModal.querySelector('[data-plan-external]') : null;
        const planExternalLink = planExternalBlock ? planExternalBlock.querySelector('[data-plan-external-link]') : null;

        function scrollToWizard() {
            const target = document.getElementById('apply-options');
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        }

        const state = {
            currentStep: 0,
            questions: cloneQuestions(selectedMode),
            answers: {},
            score: 0,
            bucket: 'pending'
        };

        let isSubmitting = false;

        setMode(selectedMode, true);
        setStep(0);
        wizard.querySelectorAll('.sffc-job-apply__question').forEach(function(questionEl) {
            captureQuestion(questionEl);
        });

        wizard.addEventListener('click', function(event) {
            const nextBtn = event.target.closest('[data-step-next]');
            if (nextBtn) {
                event.preventDefault();
                handleStepChange(1);
                return;
            }
            const prevBtn = event.target.closest('[data-step-prev]');
            if (prevBtn) {
                event.preventDefault();
                handleStepChange(-1);
            }
        });

        form.addEventListener('input', handleQuestionChange, true);
        form.addEventListener('change', handleQuestionChange, true);

        if (modeInputs.length) {
            modeInputs.forEach(function(input) {
                input.addEventListener('change', function() {
                    if (!input.checked) {
                        return;
                    }
                    setMode(input.value);
                });
            });
        }

        if (membershipModal) {
            membershipModal.querySelectorAll('[data-membership-close]').forEach(function(closeEl) {
                closeEl.addEventListener('click', function(event) {
                    event.preventDefault();
                    closeMembershipModal();
                });
            });
        }

        if (membershipPlanButtons.length) {
            membershipPlanButtons.forEach(function(btn) {
                btn.addEventListener('click', function(event) {
                    event.preventDefault();
                    handlePlanSelection(btn);
                });
            });
        }

        if (primaryButton) {
            primaryButton.addEventListener('click', function(event) {
                event.preventDefault();
                if (!membership.hasMembership) {
                    openMembershipModal(selectedMode);
                    return;
                }
                const action = selectedMode === 'smart_apply' ? 'smart_apply' : 'intro';
                submitApplication(action);
            });
        }

        if (externalButton && !externalButton.disabled) {
            externalButton.addEventListener('click', function(event) {
                event.preventDefault();
                submitApplication('external');
            });
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && membershipModal && membershipModal.classList.contains('is-open')) {
                closeMembershipModal();
            }
        });

        function handleStepChange(delta) {
            const targetIndex = state.currentStep + delta;
            if (delta > 0 && !validateStep(state.currentStep)) {
                return;
            }
            if (targetIndex < 0 || targetIndex >= steps.length) {
                return;
            }
            setStep(targetIndex);
        }

        function setStep(index) {
            state.currentStep = index;
            steps.forEach(function(stepEl, idx) {
                stepEl.classList.toggle('is-active', idx === index);
            });
            updateProgress();
            if (index === steps.length - 1) {
                calculateMatchScore();
            }
        }

        function updateProgress() {
            if (progressBar) {
                if (steps.length > 1) {
                    const ratio = Math.max(0, Math.min(1, state.currentStep / (steps.length - 1)));
                    progressBar.style.width = (ratio * 100).toFixed(2) + '%';
                } else {
                    progressBar.style.width = '100%';
                }
            }
            progressLabels.forEach(function(label) {
                const labelStep = parseInt(label.dataset.progressStep || '0', 10) - 1;
                label.classList.toggle('is-active', labelStep === state.currentStep);
                label.classList.toggle('is-complete', labelStep < state.currentStep);
            });
        }

        function validateStep(index) {
            const stepEl = steps[index];
            if (!stepEl) {
                return true;
            }
            const fields = stepEl.querySelectorAll('[data-step-field]');
            for (const field of fields) {
                if (field.disabled) {
                    continue;
                }
                if (field.type === 'radio') {
                    if (!field.required) {
                        continue;
                    }
                    const groupName = field.name;
                    if (!groupName) {
                        continue;
                    }
                    const selector = "input[name=\"" + escapeSelector(groupName) + "\"]:checked";
                    if (!stepEl.querySelector(selector)) {
                        field.reportValidity();
                        return false;
                    }
                    continue;
                }
                if (!field.checkValidity()) {
                    field.reportValidity();
                    return false;
                }
            }
            return true;
        }

        function validateAllSteps() {
            for (let i = 0; i < steps.length; i += 1) {
                if (!validateStep(i)) {
                    setStep(i);
                    return false;
                }
            }
            return true;
        }

        function handleQuestionChange(event) {
            const questionEl = event.target.closest('.sffc-job-apply__question');
            if (!questionEl) {
                return;
            }
            captureQuestion(questionEl);
            if (state.currentStep === steps.length - 1) {
                calculateMatchScore();
            }
        }

        function captureQuestion(questionEl) {
            if (!questionEl) {
                return;
            }
            const questionId = questionEl.dataset.questionId;
            if (!questionId) {
                return;
            }
            const type = questionEl.dataset.questionType || 'text';
            const desired = questionEl.dataset.questionDesired || '';
            const weight = parseFloat(questionEl.dataset.questionWeight || '1') || 1;
            let value = '';
            if (type === 'yes_no' || type === 'single_choice') {
                const checked = questionEl.querySelector('input[type="radio"]:checked');
                value = checked ? checked.value : '';
            } else {
                const field = questionEl.querySelector('[data-question-input]');
                if (field && field.disabled) {
                    return;
                }
                value = field ? field.value : '';
            }
            state.answers[questionId] = {
                value: value ? value.toString().trim() : '',
                label: questionEl.dataset.questionLabel || '',
                type: type,
                desired: desired,
                weight: weight
            };
        }

        function calculateMatchScore() {
            if (!state.questions.length) {
                state.score = 100;
                state.bucket = 'manual';
                updateScoreUI([], getEmptyQuestionsMessage(selectedMode));
                return;
            }
            let totalWeight = 0;
            let earnedWeight = 0;
            const breakdown = [];

            state.questions.forEach(function(question) {
                const answer = state.answers[question.id] || {};
                const weight = Math.max(1, parseFloat(question.weight) || 1);
                totalWeight += weight;
                const matched = evaluateAnswer(question, answer.value);
                if (matched) {
                    earnedWeight += weight;
                }
                breakdown.push({
                    label: answer.label || question.prompt || '',
                    value: answer.value || '',
                    desired: answer.desired || question.ideal_answer || '',
                    matched: matched
                });
            });

            const score = totalWeight ? Math.round((earnedWeight / totalWeight) * 100) : 0;
            state.score = Math.max(0, Math.min(100, score));
            state.bucket = getScoreBucket(state.score);
            updateScoreUI(breakdown);
        }

        function updateScoreUI(breakdown, fallbackMessage) {
            if (scoreValue) {
                scoreValue.textContent = typeof state.score === 'number' ? state.score + '% match' : '--%';
            }
            if (scoreMeter) {
                scoreMeter.style.width = Math.max(5, Math.min(100, state.score)) + '%';
            }
            const message = fallbackMessage || getScoreMessage(state.bucket);
            if (scoreState) {
                scoreState.textContent = message;
            }
            if (scoreWrapper) {
                scoreWrapper.classList.remove('is-strong', 'is-balanced', 'is-light', 'is-context', 'is-manual');
                scoreWrapper.classList.add('is-' + state.bucket);
            }
            if (breakdownEl) {
                breakdownEl.innerHTML = buildBreakdownMarkup(breakdown);
            }
        }

        function buildBreakdownMarkup(items) {
            if (!items || !items.length) {
                return '<p class="sffc-job-apply__breakdown-placeholder">' +
                    '<?php echo esc_js(__('Complete the fit questions to see personalised notes.', 'senna-finance')); ?>' +
                    '</p>';
            }
            return '<ul class="sffc-job-apply__breakdown-list">' +
                items.map(function(item) {
                    const statusClass = item.matched ? 'is-match' : 'is-gap';
                    const valueLabel = item.value ? escapeHtml(item.value) : '<?php echo esc_js(__('Not provided', 'senna-finance')); ?>';
                    const desired = item.matched ? '<?php echo esc_js(__('Aligned with expectation', 'senna-finance')); ?>' : '<?php echo esc_js(__('Ideal:', 'senna-finance')); ?> ' + escapeHtml(item.desired || '<?php echo esc_js(__('See role brief', 'senna-finance')); ?>');
                    return '<li class="' + statusClass + '">' +
                        '<strong>' + escapeHtml(item.label) + '</strong>' +
                        '<span>' + valueLabel + '</span>' +
                        '<small>' + desired + '</small>' +
                        '</li>';
                }).join('') +
                '</ul>';
        }

        function submitApplication(actionType) {
            if (isSubmitting) {
                return;
            }
            if (!validateAllSteps()) {
                return;
            }
            calculateMatchScore();
            const applicant = collectApplicant();
            if (!applicant.firstName || !applicant.lastName || !applicant.email) {
                showFeedback('error', '<?php echo esc_js(__('Please complete your contact details first.', 'senna-finance')); ?>');
                setStep(0);
                return;
            }

            isSubmitting = true;
            setLoading(actionType, true);

            const payload = new URLSearchParams({
                action: 'sffc_save_applicant',
                nonce: expressNonce,
                post_id: jobApplyConfig.jobId || '',
                crm_post_id: jobApplyConfig.crmPostId || '',
                recruiter_id: jobApplyConfig.recruiterId || '',
                job_title: jobApplyConfig.roleTitle || '',
                company_name: jobApplyConfig.companyName || '',
                first_name: applicant.firstName,
                last_name: applicant.lastName,
                email: applicant.email,
                source: getSubmissionSource(actionType),
                match_score: state.score,
                match_bucket: state.bucket,
                action_taken: actionType,
                linkedin_profile: applicant.linkedin || '',
                summary_copy: scoreState ? scoreState.textContent : ''
            });

            payload.append('selected_mode', selectedMode);
            payload.append('materials[]', actionType === 'intro' ? 'wizard_intro' : (actionType === 'smart_apply' ? 'wizard_smart_apply' : 'wizard_external'));

            state.questions.forEach(function(question) {
                const answer = state.answers[question.id] || {};
                payload.append('knockout_answers[' + question.id + '][prompt]', question.prompt || '');
                payload.append('knockout_answers[' + question.id + '][answer]', answer.value || '');
                payload.append('knockout_answers[' + question.id + '][expected]', question.ideal_answer || '');
                payload.append('knockout_answers[' + question.id + '][weight]', question.weight || 1);
            });

            fetch(ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: payload
                })
                .then(function(response) {
                    return response.json();
                })
                .then(function(json) {
                    if (!json || !json.success) {
                        throw new Error((json && json.data && json.data.message) || '<?php echo esc_js(__('Unable to save your request. Please try again.', 'senna-finance')); ?>');
                    }
                    let targetUrl;
                    if (actionType === 'smart_apply') {
                        targetUrl = jobApplyConfig.smartApplyUrl || jobApplyConfig.crmUrl;
                    } else if (actionType === 'intro') {
                        targetUrl = jobApplyConfig.introUrl || jobApplyConfig.crmUrl;
                    } else {
                        targetUrl = jobApplyConfig.externalUrl || jobApplyConfig.crmUrl;
                    }
                    return ensureAccount(applicant, targetUrl);
                })
                .then(function(result) {
                    isSubmitting = false;
                    setLoading(actionType, false);
                    if (!result) {
                        showFeedback('error', '<?php echo esc_js(__('Unable to continue. Please try again.', 'senna-finance')); ?>');
                        return;
                    }
                    if (result.loginUrl) {
                        window.location.href = result.loginUrl;
                        return;
                    }
                    const redirectTarget = result.redirect || (actionType === 'external' ? jobApplyConfig.externalUrl : (actionType === 'smart_apply' ? jobApplyConfig.smartApplyUrl : jobApplyConfig.introUrl)) || jobApplyConfig.crmUrl;
                    window.location.href = redirectTarget;
                })
                .catch(function(error) {
                    console.error(error);
                    isSubmitting = false;
                    setLoading(actionType, false);
                    showFeedback('error', error.message || '<?php echo esc_js(__('Something went wrong. Please try again.', 'senna-finance')); ?>');
                });
        }

        function ensureAccount(applicant, targetUrl) {
            if (membership.isLoggedIn) {
                return Promise.resolve({
                    redirect: targetUrl
                });
            }
            const payload = new URLSearchParams({
                action: 'sffc_quick_register_candidate',
                nonce: expressNonce,
                first_name: applicant.firstName,
                last_name: applicant.lastName,
                email: applicant.email,
                redirect_to: targetUrl || jobApplyConfig.crmUrl
            });
            return fetch(ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: payload
                })
                .then(function(response) {
                    return response.json();
                })
                .then(function(json) {
                    if (!json) {
                        throw new Error('<?php echo esc_js(__('Unable to create your account right now.', 'senna-finance')); ?>');
                    }
                    if (!json.success) {
                        throw new Error((json.data && json.data.message) || '<?php echo esc_js(__('Unable to create your account right now.', 'senna-finance')); ?>');
                    }
                    const data = json.data || {};
                    if (data.status === 'exists' && data.login_url) {
                        return {
                            loginUrl: data.login_url
                        };
                    }
                    return {
                        redirect: data.redirect || targetUrl
                    };
                });
        }

        function collectApplicant() {
            const applicant = {
                firstName: firstNameInput ? firstNameInput.value.trim() : '',
                lastName: lastNameInput ? lastNameInput.value.trim() : '',
                email: emailInput ? emailInput.value.trim() : '',
                linkedin: linkedinInput ? linkedinInput.value.trim() : ''
            };
            jobApplyConfig.user = applicant;
            return applicant;
        }

        function showFeedback(type, message) {
            if (!feedbackEl) {
                alert(message);
                return;
            }
            feedbackEl.textContent = message;
            feedbackEl.classList.remove('is-error', 'is-success');
            feedbackEl.classList.add(type === 'error' ? 'is-error' : 'is-success');
            feedbackEl.style.display = 'block';
        }

        function setLoading(actionType, isLoading) {
            const button = actionType === 'intro' || actionType === 'smart_apply' ? primaryButton : externalButton;
            if (!button) {
                return;
            }
            if (isLoading) {
                if (!button.dataset.originalText) {
                    button.dataset.originalText = button.textContent;
                }
                button.disabled = true;
                button.classList.add('is-loading');
                if (actionType === 'intro') {
                    button.textContent = '<?php echo esc_js(__('Sending…', 'senna-finance')); ?>';
                } else if (actionType === 'smart_apply') {
                    button.textContent = '<?php echo esc_js(__('Preparing your Smart message run…', 'senna-finance')); ?>';
                } else {
                    button.textContent = '<?php echo esc_js(__('Connecting…', 'senna-finance')); ?>';
                }
            } else {
                button.disabled = false;
                button.classList.remove('is-loading');
                if (button.dataset.originalText) {
                    button.textContent = button.dataset.originalText;
                }
            }
        }

        function evaluateAnswer(question, value) {
            if (!value) {
                return false;
            }
            const desired = (question.ideal_answer || '').toString().toLowerCase();
            const normalizedValue = value.toString().toLowerCase();
            switch (question.type) {
                case 'yes_no':
                case 'single_choice':
                    return desired ? normalizedValue === desired : !!value;
                case 'number': {
                    const expected = parseFloat(question.ideal_answer);
                    const actual = parseFloat(value);
                    if (Number.isNaN(expected) || Number.isNaN(actual)) {
                        return actual > 0;
                    }
                    return actual >= expected;
                }
                default:
                    if (!desired) {
                        return value.trim().length > 2;
                    }
                    return normalizedValue.indexOf(desired) !== -1;
            }
        }

        function getScoreBucket(score) {
            if (score >= 85) {
                return 'strong';
            }
            if (score >= 60) {
                return 'balanced';
            }
            if (score >= 35) {
                return 'light';
            }
            return 'context';
        }

        function getScoreMessage(bucket) {
            switch (bucket) {
                case 'strong':
                    return '<?php echo esc_js(__('Great fit — your interest will be prioritised with recruiters.', 'senna-finance')); ?>';
                case 'balanced':
                    return '<?php echo esc_js(__('Promising fit — we will highlight your edge before sending.', 'senna-finance')); ?>';
                case 'light':
                    return '<?php echo esc_js(__('Mixed fit — we will add context so recruiters understand the nuances.', 'senna-finance')); ?>';
                case 'context':
                    return '<?php echo esc_js(__('Light fit — expect longer timelines, but we will still share your profile.', 'senna-finance')); ?>';
                default:
                    return '<?php echo esc_js(__('We will review your interest manually.', 'senna-finance')); ?>';
            }
        }

        function getEmptyQuestionsMessage(mode) {
            if (mode === 'smart_apply') {
                return '<?php echo esc_js(__('Smart message questions are coming soon — we will still run a targeted batch manually.', 'senna-finance')); ?>';
            }
            return '<?php echo esc_js(__('No knockout questions were provided. We will review your interest manually.', 'senna-finance')); ?>';
        }

        function cloneQuestions(mode) {
            const list = modeQuestions[mode] || [];
            return list.map(function(question) {
                return Object.assign({}, question);
            });
        }

        function setMode(mode, skipScore) {
            if (!mode) {
                return;
            }
            selectedMode = mode;
            state.questions = cloneQuestions(mode);
            togglePanels();
            updateModeCopy();
            if (!skipScore) {
                if (!state.questions.length) {
                    updateScoreUI([], getEmptyQuestionsMessage(selectedMode));
                } else {
                    calculateMatchScore();
                }
            }
        }

        function togglePanels() {
            modeCards.forEach(function(card) {
                const input = card.querySelector('.sffc-job-apply__mode-input');
                const isActive = input && input.value === selectedMode;
                card.classList.toggle('is-active', isActive);
                if (input) {
                    input.checked = isActive;
                }
            });
            questionPanels.forEach(function(panel) {
                const isActive = panel.dataset.modePanel === selectedMode;
                panel.classList.toggle('is-active', isActive);
                panel.querySelectorAll('[data-step-field]').forEach(function(field) {
                    field.disabled = !isActive;
                });
            });
            updateModeCopy();
        }

        function openMembershipModal(mode) {
            if (!membershipModal) {
                const fallbackUrl = membership.joinUrl || membership.upgradeUrl || membership.loginUrl;
                if (fallbackUrl) {
                    window.location.href = fallbackUrl;
                }
                return;
            }
            if (mode && mode !== selectedMode) {
                setMode(mode);
            } else {
                updateModeCopy();
            }
            const firstName = (firstNameInput && firstNameInput.value.trim()) || userDefaults.firstName || '';
            if (membershipNameEl) {
                membershipNameEl.textContent = firstName || '<?php echo esc_js(__('You', 'senna-finance')); ?>';
            }
            if (membershipModeLine) {
                const copy = selectedMode === 'smart_apply' ?
                    membershipModeLine.dataset.copySmart :
                    membershipModeLine.dataset.copyRecruiter;
                if (copy) {
                    membershipModeLine.textContent = copy;
                }
            }
            if (membershipModeChip) {
                const chipText = selectedMode === 'smart_apply' ?
                    membershipModeChip.dataset.copySmart :
                    membershipModeChip.dataset.copyRecruiter;
                if (chipText) {
                    membershipModeChip.textContent = chipText;
                }
            }
            if (planCheckoutSection) {
                planCheckoutSection.hidden = true;
                if (planMessageEl) {
                    planMessageEl.textContent = '<?php echo esc_js(__('Pick a plan to unlock the secure checkout.', 'senna-finance')); ?>';
                }
                if (planExternalBlock) {
                    planExternalBlock.hidden = true;
                }
                planForms.forEach(function(form) {
                    form.hidden = true;
                });
            }
            membershipPlanCards.forEach(function(card) {
                card.classList.remove('is-selected');
            });
            membershipModal.classList.add('is-open');
            membershipModal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('sffc-job-plan-modal-open');
        }

        function closeMembershipModal() {
            if (!membershipModal) {
                return;
            }
            membershipModal.classList.remove('is-open');
            membershipModal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('sffc-job-plan-modal-open');
        }

        function handlePlanSelection(button) {
            const card = button.closest('[data-membership-plan]');
            const slug = button.dataset.planSlug || '';
            const hasShortcode = button.dataset.hasShortcode === 'true';
            const planMessage = button.dataset.planMessage || '';
            const planUrl = button.dataset.planUrl || '';
            membershipPlanCards.forEach(function(cardEl) {
                cardEl.classList.toggle('is-selected', cardEl === card);
            });
            if (!planCheckoutSection) {
                if (planUrl) {
                    window.open(planUrl, '_blank', 'noopener');
                }
                return;
            }
            planCheckoutSection.hidden = false;
            if (planMessageEl && planMessage) {
                planMessageEl.textContent = planMessage;
            }
            if (hasShortcode) {
                planForms.forEach(function(form) {
                    form.hidden = form.dataset.planForm !== slug;
                });
                if (planExternalBlock) {
                    planExternalBlock.hidden = true;
                }
            } else {
                planForms.forEach(function(form) {
                    form.hidden = true;
                });
                if (planExternalBlock) {
                    planExternalBlock.hidden = false;
                }
                if (planExternalLink) {
                    planExternalLink.href = planUrl || '#';
                }
            }
        }

        function updateModeCopy() {
            if (modeTitleEl) {
                const text = selectedMode === 'smart_apply' ?
                    modeTitleEl.dataset.copySmart :
                    modeTitleEl.dataset.copyRecruiter;
                if (text) {
                    modeTitleEl.textContent = text;
                }
            }
            if (modeDescEl) {
                const desc = selectedMode === 'smart_apply' ?
                    modeDescEl.dataset.copySmart :
                    modeDescEl.dataset.copyRecruiter;
                if (desc) {
                    modeDescEl.textContent = desc;
                }
            }
            if (primaryButton) {
                const btnText = selectedMode === 'smart_apply' ?
                    primaryButton.dataset.textSmart :
                    primaryButton.dataset.textRecruiter;
                if (btnText) {
                    primaryButton.textContent = btnText;
                    primaryButton.dataset.originalText = btnText;
                }
            }
            if (externalButton) {
                const alt = selectedMode === 'smart_apply' ?
                    externalButton.dataset.textSmart :
                    externalButton.dataset.textRecruiter;
                if (alt) {
                    externalButton.textContent = alt;
                }
            }
            if (ctaNote) {
                const noteCopy = selectedMode === 'smart_apply' ?
                    ctaNote.dataset.copySmart :
                    ctaNote.dataset.copyRecruiter;
                if (noteCopy) {
                    ctaNote.textContent = noteCopy;
                }
            }
        }

        function getSubmissionSource(actionType) {
            if (actionType === 'smart_apply') {
                return 'job_apply_wizard_smart_apply';
            }
            if (actionType === 'intro') {
                return 'job_apply_wizard_intro';
            }
            return 'job_apply_wizard_external';
        }

        function collectApplicant() {
            const applicant = {
                firstName: firstNameInput ? firstNameInput.value.trim() : '',
                lastName: lastNameInput ? lastNameInput.value.trim() : '',
                email: emailInput ? emailInput.value.trim() : '',
                linkedin: linkedinInput ? linkedinInput.value.trim() : ''
            };
            jobApplyConfig.user = applicant;
            return applicant;
        }

        function showFeedback(type, message) {
            if (!feedbackEl) {
                alert(message);
                return;
            }
            feedbackEl.textContent = message;
            feedbackEl.classList.remove('is-error', 'is-success');
            feedbackEl.classList.add(type === 'error' ? 'is-error' : 'is-success');
            feedbackEl.style.display = 'block';
        }

        function escapeHtml(str) {
            return (str || '').toString().replace(/[&<>"']/g, function(char) {
                return ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                })[char];
            });
        }

        function escapeSelector(value) {
            if (window.CSS && typeof window.CSS.escape === 'function') {
                return window.CSS.escape(value);
            }
            return value.replace(/([\:])/g, '\$1');
        }
    })();
</script>
<?php
}
