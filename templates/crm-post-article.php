<?php

/**
 * CRM Post Article Template
 *
 * Renders a CRM recruiter post with Application Pack flow.
 * Features: Job Details view (default), Application Pack tab with CV upload + products,
 * and Express Interest tab with upsell products.
 * Pulls data from CRM database tables instead of WordPress post meta.
 *
 * @package SennaCareers
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('inst_fetch_public_recruiter_label')) {
    /**
     * Fetch the latest public recruiter/firm aliases for a given recruiter ID.
     */
    function inst_fetch_public_recruiter_label($recruiter_id)
    {
        global $wpdb;

        if (!$recruiter_id || !$wpdb) {
            return [];
        }

        $table = $wpdb->prefix . 'sffc_crm_posts';
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT recruiter_display_name, recruiter_display_company
                 FROM {$table}
                 WHERE recruiter_id = %d
                   AND admin_approved = 1
                   AND (recruiter_display_name <> '' OR recruiter_display_company <> '')
                 ORDER BY posted_at DESC
                 LIMIT 1",
                $recruiter_id
            ),
            ARRAY_A
        );

        if (empty($row)) {
            return [];
        }

        return [
            'name' => $row['recruiter_display_name'] ?? '',
            'company' => $row['recruiter_display_company'] ?? '',
        ];
    }
}

if (!function_exists('inst_format_recruiter_initial_name')) {
    /**
     * Display recruiter name as "First L." for consistency across templates.
     */
    function inst_format_recruiter_initial_name($name)
    {
        $name = trim((string) $name);
        if ($name === '') {
            return '';
        }

        $parts = preg_split('/\s+/', $name);
        if (!$parts || count($parts) === 0) {
            return $name;
        }

        $first = array_shift($parts);
        $last = '';
        if (!empty($parts)) {
            $lastWord = array_pop($parts);
            $last = strtoupper(substr($lastWord, 0, 1));
        }

        if ($last) {
            return sprintf('%s %s.', $first, $last);
        }

        return $first;
    }
}

/**
 * Render the CRM post article
 *
 * @param array $args {
 *     @type int    $post_id        The CRM post ID (from sffc_crm_posts table)
 *     @type bool   $show_sidebar   Whether to show the sidebar
 *     @type bool   $user_has_access Whether user is logged in
 * }
 */
function sffc_render_crm_post_article($args)
{
    $post_id = $args['post_id'] ?? 0;
    $show_sidebar = $args['show_sidebar'] ?? true;
    $user_has_access = $args['user_has_access'] ?? false;

    if (!$post_id) {
        echo '<!-- crm post article: no post id -->';
        return;
    }

    // Get post data from CRM database
    $post_model = new SFFC_CRM_Post();
    $post = $post_model->get_full_detail($post_id, get_current_user_id());

    if (!$post) {
        echo '<!-- crm post article: post not found -->';
        return;
    }

    // Map CRM fields to template variables
    $recruiter_name = $post['recruiter_name'] ?? '';
    $recruiter_title = $post['recruiter_title'] ?? '';
    $recruiter_company = $post['recruiter_firm'] ?? '';
    $recruiter_email = $post['recruiter_email'] ?? '';
    $recruiter_linkedin = $post['recruiter_linkedin'] ?? '';
    $recruiter_photo = $post['recruiter_photo'] ?? '';
    $crm_post_id = (int) ($post['id'] ?? 0);
    $crm_recruiter_id = (int) ($post['recruiter_id'] ?? 0);

    $job_title = $post['role_title'] ?? 'Untitled Position';
    $company_name = $post['company'] ?? '';
    $job_location = $post['location'] ?? '';

    $salary_min = $post['salary_min'] ?? '';
    $salary_max = $post['salary_max'] ?? '';
    $salary_currency = $post['salary_currency'] ?? 'USD';
    $salary_text = $post['salary_text'] ?? '';

    // Map seniority to experience display
    $seniority = $post['seniority'] ?? '';
    $seniority_labels = [
        'analyst' => '0-2 years',
        'associate' => '2-4 years',
        'vp' => '4-7 years',
        'director' => '7-10 years',
        'md' => '10+ years',
        'partner' => '15+ years',
        'c_level' => '15+ years',
    ];
    $experience_years = $seniority_labels[$seniority] ?? $seniority;

    // Requirements - decode JSON if needed
    $requirements = $post['requirements'] ?? [];
    if (is_string($requirements)) {
        $requirements = json_decode($requirements, true) ?: [];
    }
    $key_requirements = is_array($requirements) ? implode("\n", $requirements) : '';

    // Skills mentioned as ideal background
    $skills = $post['skills_mentioned'] ?? [];
    if (is_string($skills)) {
        $skills = json_decode($skills, true) ?: [];
    }
    $ideal_background = is_array($skills) ? implode(', ', $skills) : '';

    $is_featured = !empty($post['is_featured']);
    $is_remote = !empty($post['is_remote']);
    $is_hybrid = !empty($post['is_hybrid']);

    // Content/Description
    $jd_content = $post['content'] ?? '';

    // Use sector, seniority, location_country as taxonomy equivalents
    $post_type_label = ucfirst($seniority ?: 'Active Role');
    $industry_label = $post['sector'] ?? '';
    $location_label = $post['location_country'] ?? $job_location;

    // Build the full JD text for analysis
    $jd_full_text = sffc_build_crm_jd_text_for_analysis($post);

    // Format salary
    $salary_display = '';
    if ($salary_text) {
        $salary_display = $salary_text;
    } elseif ($salary_min && $salary_max) {
        $salary_display = $salary_currency . ' ' . number_format($salary_min) . ' - ' . number_format($salary_max);
    } elseif ($salary_min) {
        $salary_display = $salary_currency . ' ' . number_format($salary_min) . '+';
    }

    // Get current user info
    $current_user = wp_get_current_user();
    $user_first_name = $current_user->ID > 0 ? ($current_user->first_name ?: '') : '';
    $user_last_name = $current_user->ID > 0 ? ($current_user->last_name ?: '') : '';
    $user_email = $current_user->ID > 0 ? $current_user->user_email : '';
    $is_logged_in = is_user_logged_in();

    // Check premium access
    $is_premium = false;
    if ($current_user->ID && class_exists('SFFC_MemberPress_Integration')) {
        $mepr = SFFC_MemberPress_Integration::get_instance();
        $is_premium = $mepr->has_premium_access($current_user->ID);
    }

    $public_recruiter_label = '';
    $public_recruiter_company = '';
    if (!$is_logged_in && $crm_recruiter_id) {
        $public_labels = inst_fetch_public_recruiter_label($crm_recruiter_id);
        if (!empty($public_labels)) {
            $public_recruiter_label = $public_labels['name'] ?? '';
            $public_recruiter_company = $public_labels['company'] ?? '';
        }
    }

    $display_recruiter_name = (!$is_logged_in && $public_recruiter_label) ? $public_recruiter_label : $recruiter_name;
    $display_recruiter_meta_title = (!$is_logged_in && $public_recruiter_label) ? $public_recruiter_label : $recruiter_title;
    $display_recruiter_meta_company = (!$is_logged_in && $public_recruiter_company) ? $public_recruiter_company : $recruiter_company;
    $meta_title = $display_recruiter_meta_title;
    $meta_company = $display_recruiter_meta_company;
    $condensed_recruiter_name = '';
    if ($recruiter_name) {
        $condensed_recruiter_name = inst_format_recruiter_initial_name($recruiter_name);
    } elseif ($display_recruiter_name) {
        $condensed_recruiter_name = inst_format_recruiter_initial_name($display_recruiter_name);
    }

    // Enqueue assets
    sffc_enqueue_crm_article_assets($post_id, $jd_full_text, $recruiter_email, $job_title, $company_name, $recruiter_name, $crm_recruiter_id);
?>
    <div class="inst-terminal inst-express-interest-flow" data-component="recruiter-post-analyzer" data-post-id="<?php echo esc_attr($post_id); ?>" data-source="crm" data-is-premium="<?php echo $is_premium ? 'true' : 'false'; ?>" data-is-logged-in="<?php echo $is_logged_in ? 'true' : 'false'; ?>">

        <!-- ========================================
             MAIN CONTAINER (Visible by default - no welcome page)
             ======================================== -->
        <div class="inst-analysis-container" id="inst-analysis-container">

            <!-- Premium Preloader (Hidden, shown during generation) -->
            <div class="inst-premium-preloader" id="inst-premium-preloader" style="display: none;">
                <div class="preloader-grain"></div>
                <div class="preloader-content">
                    <div class="preloader-brand-mark">
                        <span class="brand-letter-s">S</span>
                        <span class="brand-dot"></span>
                    </div>
                    <div class="preloader-brand-name">
                        <h1 class="preloader-brand-text">MENA CAREERS</h1>
                    </div>
                    <div class="preloader-tagline" id="preloader-tagline">
                        Generating Your Pack
                    </div>
                    <div class="premium-loader">
                        <div class="loader-track">
                            <div class="loader-progress" id="loader-progress"></div>
                            <div class="loader-shimmer"></div>
                        </div>
                    </div>
                    <div class="preloader-analysis-steps">
                        <div class="preloader-step inst-preloader-step" data-step="cv">
                            <span class="preloader-step-icon inst-preloader-step-icon"></span>
                            <span>Tailoring CV</span>
                        </div>
                        <div class="preloader-step inst-preloader-step" data-step="cover">
                            <span class="preloader-step-icon inst-preloader-step-icon"></span>
                            <span>Writing Cover Letter</span>
                        </div>
                        <div class="preloader-step inst-preloader-step" data-step="interview">
                            <span class="preloader-step-icon inst-preloader-step-icon"></span>
                            <span>Preparing Interview Questions</span>
                        </div>
                        <div class="preloader-step inst-preloader-step" data-step="ats">
                            <span class="preloader-step-icon inst-preloader-step-icon"></span>
                            <span>Optimizing for ATS</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Header -->
            <div class="inst-analysis-header">
                <div class="inst-analysis-header-left">
                    <div class="inst-analysis-header-info">
                        <div class="inst-analysis-header-text">
                            <h1 class="inst-analysis-header-title"><?php echo esc_html($job_title); ?></h1>
                            <p class="inst-analysis-header-subtitle"><?php echo esc_html($company_name ?: 'Confidential'); ?><?php if ($location_label) : ?> &bull; <?php echo esc_html($location_label); ?><?php endif; ?></p>
                        </div>
                        <?php if ($recruiter_email) : ?>
                            <div class="inst-analysis-header-cta">
                                <button type="button" class="inst-header-message-btn" data-navigate="express-interest">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                                        <polyline points="22,6 12,13 2,6" />
                                    </svg>
                                    <span>Message Recruiter</span>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="inst-analysis-header-right">
                    <button type="button"
                        class="inst-add-pipeline-btn"
                        id="instAddPipelineBtn"
                        data-recruiter-id="<?php echo esc_attr($crm_recruiter_id); ?>"
                        data-crm-post-id="<?php echo esc_attr($crm_post_id); ?>"
                        data-role-title="<?php echo esc_attr($job_title); ?>"
                        data-company="<?php echo esc_attr($company_name); ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 12h14" />
                            <polyline points="13 6 19 12 13 18" />
                            <rect x="3" y="4" width="6" height="16" rx="2" />
                        </svg>
                        <span>Apply</span>
                    </button>
                    <button type="button" class="inst-expert-btn" id="instSpeakExpertBtn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M2 21v-2a4 4 0 0 1 4-4h4" />
                            <circle cx="12" cy="7" r="4" />
                            <path d="M16 11h2a4 4 0 0 1 4 4v2" />
                            <circle cx="19" cy="5" r="2" />
                        </svg>
                        <span>Speak to an Expert</span>
                    </button>
                </div>
            </div>

            <!-- Tab Toggle Container -->
            <div class="inst-view-toggle-container">
                <div class="inst-view-toggle" role="tablist" aria-label="View options">

                    <!-- Job Details Tab (DEFAULT) -->
                    <button type="button"
                        class="inst-view-toggle-btn is-active"
                        data-view="job-details"
                        role="tab"
                        aria-selected="true"
                        aria-controls="inst-job-details-view">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="2" y="7" width="20" height="14" rx="2" ry="2" />
                            <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" />
                        </svg>
                        <span>Job Details</span>
                    </button>

                    <!-- Application Pack Tab (formerly Analysis) -->
                    <button type="button"
                        class="inst-view-toggle-btn"
                        data-view="application-pack"
                        role="tab"
                        aria-selected="false"
                        aria-controls="inst-application-pack-view">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                            <polyline points="3.27 6.96 12 12.01 20.73 6.96" />
                            <line x1="12" y1="22.08" x2="12" y2="12" />
                        </svg>
                        <span>Application Pack</span>
                    </button>

                    <!-- Express Interest Tab -->
                    <button type="button"
                        class="inst-view-toggle-btn"
                        data-view="express-interest"
                        role="tab"
                        aria-selected="false"
                        aria-controls="inst-express-interest-view">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                            <polyline points="22,6 12,13 2,6" />
                        </svg>
                        <span>Express Interest</span>
                    </button>

                    <!-- Similar Posts Tab -->
                    <button type="button"
                        class="inst-view-toggle-btn"
                        data-view="similar-posts"
                        role="tab"
                        aria-selected="false"
                        aria-controls="inst-similar-posts-view">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="7" height="7" />
                            <rect x="14" y="3" width="7" height="7" />
                            <rect x="14" y="14" width="7" height="7" />
                            <rect x="3" y="14" width="7" height="7" />
                        </svg>
                        <span>Similar Recruiter Posts</span>
                    </button>

                </div>
            </div>

            <!-- Tab Views Container -->
            <div class="inst-tab-views">

                <!-- ========================================
                     JOB DETAILS TAB (DEFAULT - Visible)
                     ======================================== -->
                <div class="inst-tab-view is-active" id="inst-job-details-view" role="tabpanel">
                    <div class="inst-job-details-content">

                        <!-- Job Meta Cards -->
                        <div class="inst-job-meta-grid">
                            <?php if ($location_label) : ?>
                                <div class="inst-job-meta-card">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                                        <circle cx="12" cy="10" r="3" />
                                    </svg>
                                    <div>
                                        <span class="inst-job-meta-label">Location</span>
                                        <span class="inst-job-meta-value"><?php echo esc_html($location_label); ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($experience_years) : ?>
                                <div class="inst-job-meta-card">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="2" y="7" width="20" height="14" rx="2" ry="2" />
                                        <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" />
                                    </svg>
                                    <div>
                                        <span class="inst-job-meta-label">Experience</span>
                                        <span class="inst-job-meta-value"><?php echo esc_html($experience_years); ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($salary_display) : ?>
                                <div class="inst-job-meta-card">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="12" y1="1" x2="12" y2="23" />
                                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                                    </svg>
                                    <div>
                                        <span class="inst-job-meta-label">Salary</span>
                                        <span class="inst-job-meta-value"><?php echo esc_html($salary_display); ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($industry_label) : ?>
                                <div class="inst-job-meta-card">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                                        <polyline points="9 22 9 12 15 12 15 22" />
                                    </svg>
                                    <div>
                                        <span class="inst-job-meta-label">Industry</span>
                                        <span class="inst-job-meta-value"><?php echo esc_html($industry_label); ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($post_type_label) : ?>
                                <div class="inst-job-meta-card">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4" />
                                        <path d="M3 21a9 9 0 0 1 18 0" />
                                    </svg>
                                    <div>
                                        <span class="inst-job-meta-label">Seniority</span>
                                        <span class="inst-job-meta-value"><?php echo esc_html($post_type_label); ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Key Requirements -->
                        <?php if ($key_requirements) : ?>
                            <div class="inst-key-requirements">
                                <h4>Key Requirements</h4>
                                <ul>
                                    <?php
                                    $reqs = array_filter(array_map('trim', explode("\n", $key_requirements)));
                                    foreach ($reqs as $req) :
                                    ?>
                                        <li><?php echo esc_html($req); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <!-- Ideal Background -->
                        <?php if ($ideal_background) : ?>
                            <div class="inst-ideal-background">
                                <h4>Ideal Background</h4>
                                <p><?php echo esc_html($ideal_background); ?></p>
                            </div>
                        <?php endif; ?>

                        <!-- Recruiter Info (moved below ideal background) -->
                        <?php
                        $crm_avatar_initial = strtoupper(substr(($recruiter_name ?: $public_recruiter_label ?: 'R'), 0, 1));
                        ?>
                        <?php if ($recruiter_name || $recruiter_company) : ?>
                            <div class="inst-recruiter-card" data-recruiter-id="<?php echo esc_attr($crm_recruiter_id); ?>" data-recruiter-name="<?php echo esc_attr($recruiter_name); ?>" data-recruiter-company="<?php echo esc_attr($recruiter_company); ?>" data-recruiter-email="<?php echo esc_attr($recruiter_email); ?>">
                                <div class="inst-recruiter-avatar<?php echo $recruiter_photo ? ' inst-recruiter-avatar--has-image' : ''; ?>" data-avatar-initial="<?php echo esc_attr($crm_avatar_initial); ?>">
                                    <?php if ($recruiter_photo) : ?>
                                        <img src="<?php echo esc_url($recruiter_photo); ?>" alt="<?php echo esc_attr($display_recruiter_name ?: $recruiter_name); ?>">
                                    <?php else : ?>
                                        <?php echo esc_html($crm_avatar_initial); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="inst-recruiter-info">
                                    <span class="inst-recruiter-label">Posted by</span>
                                    <?php if ($condensed_recruiter_name) : ?>
                                        <span class="inst-recruiter-name"><?php echo esc_html($condensed_recruiter_name); ?></span>
                                    <?php endif; ?>
                                    <?php if ($meta_title && $meta_company) : ?>
                                        <span class="inst-recruiter-meta"><?php echo esc_html($meta_title . ' at ' . $meta_company); ?></span>
                                    <?php elseif ($meta_company) : ?>
                                        <span class="inst-recruiter-meta"><?php echo esc_html($meta_company); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="inst-recruiter-actions">
                                    <?php if ($recruiter_linkedin && $is_logged_in) : ?>
                                        <a href="<?php echo esc_url($recruiter_linkedin); ?>" target="_blank" class="inst-recruiter-link" title="View LinkedIn">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z" />
                                                <rect x="2" y="9" width="4" height="12" />
                                                <circle cx="4" cy="4" r="2" />
                                            </svg>
                                        </a>
                                    <?php endif; ?>
                                    <button type="button" class="inst-add-to-list-btn" id="instAddToListBtn" title="Message recruiter" data-behavior="message">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                                            <polyline points="22,6 12,13 2,6" />
                                        </svg>
                                        <span>Message Recruiter</span>
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Full Job Description with Tailor CV Button -->
                        <div class="inst-job-description">
                            <div class="inst-job-description-header">
                                <h4>Full Description</h4>
                                <button class="inst-tailor-cv-btn" data-navigate="application-pack">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                        <polyline points="14 2 14 8 20 8" />
                                        <path d="M12 18v-6" />
                                        <path d="M9 15l3-3 3 3" />
                                    </svg>
                                    Tailor CV to Job Description
                                </button>
                            </div>
                            <div class="inst-job-description-content">
                                <?php echo wp_kses_post(wpautop($jd_content)); ?>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- ========================================
                     APPLICATION PACK TAB (Formerly Analysis)
                     ======================================== -->
                <div class="inst-tab-view" id="inst-application-pack-view" role="tabpanel" style="display: none;">

                    <!-- CV Paste Section - Only show to logged in users -->
                    <?php if (is_user_logged_in()) : ?>
                        <div class="inst-pack-section inst-cv-section">
                            <div class="inst-pack-section-header">
                                <h3>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                        <polyline points="14 2 14 8 20 8" />
                                    </svg>
                                    Your CV
                                </h3>
                                <p>Paste your CV to generate tailored application materials</p>
                            </div>

                            <div class="inst-cv-intake">
                                <textarea class="inst-cv-paste-input query-input"
                                    id="instCvPasteInput"
                                    placeholder="Paste your CV text here...

Include:
- Work experience with dates
- Education and qualifications
- Skills and certifications
- Achievements and accomplishments"
                                    rows="6"></textarea>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Products Section with Floating Sidebar -->
                    <div class="inst-pack-layout">
                        <div class="inst-pack-section inst-products-section">
                            <div class="inst-pack-section-header">
                                <h3>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                                    </svg>
                                    Build Your Application Pack
                                </h3>
                                <p>Select the materials you want to generate</p>
                            </div>

                            <div class="inst-products-grid">

                                <!-- Tailored CV Product -->
                                <div class="inst-product-card" data-product="tailored-cv">
                                    <div class="inst-product-header">
                                        <div class="inst-product-icon">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                                <polyline points="14 2 14 8 20 8" />
                                                <line x1="16" y1="13" x2="8" y2="13" />
                                                <line x1="16" y1="17" x2="8" y2="17" />
                                            </svg>
                                        </div>
                                        <div class="inst-product-meta">
                                            <div class="inst-product-title-row">
                                                <h4 class="inst-product-title">Tailored CV</h4>
                                                <button type="button" class="inst-product-action-btn" data-product="tailored-cv">
                                                    Tailor CV
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                                                        <path d="M5 12h14M12 5l7 7-7 7" />
                                                    </svg>
                                                </button>
                                            </div>
                                            <?php if (!$is_premium) : ?>
                                                <span class="inst-product-badge inst-product-badge--premium">Premium</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="inst-product-preview" id="preview-tailored-cv">
                                        <div class="inst-preview-window">
                                            <div class="inst-preview-titlebar">
                                                <span class="inst-preview-dot"></span>
                                                <span class="inst-preview-dot"></span>
                                                <span class="inst-preview-dot"></span>
                                                <span class="inst-preview-filename">cv_tailored_<?php echo esc_attr(sanitize_title($job_title)); ?>.pdf</span>
                                            </div>
                                            <div class="inst-preview-content inst-preview-cv">
                                                <!-- CV Header -->
                                                <div class="inst-cv-header">
                                                    <div class="inst-cv-name" id="cv-preview-name"><?php echo esc_html($user_first_name ?: __('Candidate', 'senna-finance')); ?></div>
                                                    <div class="inst-cv-title"><?php echo esc_html($job_title); ?> Candidate</div>
                                                    <div class="inst-cv-contact">
                                                        <span class="inst-cv-contact-item">email@example.com</span>
                                                        <span class="inst-cv-contact-divider">|</span>
                                                        <span class="inst-cv-contact-item">LinkedIn</span>
                                                    </div>
                                                </div>

                                                <!-- Professional Summary -->
                                                <div class="inst-cv-section">
                                                    <div class="inst-cv-section-title">Professional Summary</div>
                                                    <div class="inst-cv-summary">
                                                        <span class="inst-cv-placeholder"></span>
                                                        <span class="inst-cv-placeholder inst-cv-placeholder--short"></span>
                                                        <span class="inst-cv-highlight" id="cv-summary-highlight">Tailored for <?php echo esc_html($job_title); ?></span>
                                                    </div>
                                                </div>

                                                <!-- Key Skills -->
                                                <div class="inst-cv-section">
                                                    <div class="inst-cv-section-title">Key Skills</div>
                                                    <div class="inst-cv-skills-grid" id="cv-skills">
                                                        <!-- Populated by JS -->
                                                    </div>
                                                </div>

                                                <!-- Experience -->
                                                <div class="inst-cv-section">
                                                    <div class="inst-cv-section-title">Experience</div>
                                                    <div class="inst-cv-experience">
                                                        <div class="inst-cv-exp-header">
                                                            <span class="inst-cv-placeholder inst-cv-placeholder--title"></span>
                                                            <span class="inst-cv-placeholder inst-cv-placeholder--date"></span>
                                                        </div>
                                                        <div class="inst-cv-exp-bullets">
                                                            <div class="inst-cv-bullet">
                                                                <span class="inst-cv-placeholder"></span>
                                                                <span class="inst-cv-keyword-inline" id="cv-keyword-1"></span>
                                                            </div>
                                                            <div class="inst-cv-bullet">
                                                                <span class="inst-cv-placeholder inst-cv-placeholder--medium"></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Optimized Keywords Badge -->
                                                <div class="inst-cv-keywords-badge">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12">
                                                        <polyline points="20 6 9 17 4 12" />
                                                    </svg>
                                                    <span id="cv-keywords-count">0</span> keywords optimized for this role
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <button class="inst-add-to-pack-btn" data-product="tailored-cv">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="12" y1="5" x2="12" y2="19" />
                                            <line x1="5" y1="12" x2="19" y2="12" />
                                        </svg>
                                        Add to Pack
                                    </button>
                                </div>

                                <!-- Tailored Cover Letter Product -->
                                <div class="inst-product-card" data-product="cover-letter">
                                    <div class="inst-product-header">
                                        <div class="inst-product-icon">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                                                <polyline points="22,6 12,13 2,6" />
                                            </svg>
                                        </div>
                                        <div class="inst-product-meta">
                                            <div class="inst-product-title-row">
                                                <h4 class="inst-product-title">Cover Letter</h4>
                                                <button type="button" class="inst-product-action-btn" data-product="cover-letter">
                                                    Generate Letter
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                                                        <path d="M5 12h14M12 5l7 7-7 7" />
                                                    </svg>
                                                </button>
                                            </div>
                                            <?php if (!$is_premium) : ?>
                                                <span class="inst-product-badge inst-product-badge--premium">Premium</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="inst-product-preview" id="preview-cover-letter">
                                        <div class="inst-preview-window">
                                            <div class="inst-preview-titlebar">
                                                <span class="inst-preview-dot"></span>
                                                <span class="inst-preview-dot"></span>
                                                <span class="inst-preview-dot"></span>
                                                <span class="inst-preview-filename">cover_letter.pdf</span>
                                            </div>
                                            <div class="inst-preview-content inst-preview-letter">
                                                <p class="inst-preview-greeting">Dear <?php echo esc_html($recruiter_name ?: 'Hiring Manager'); ?>,</p>
                                                <p class="inst-preview-line">I am writing to express my interest in the <strong><?php echo esc_html($job_title); ?></strong> position<?php if ($company_name) : ?> at <strong><?php echo esc_html($company_name); ?></strong><?php endif; ?>.</p>
                                                <p class="inst-preview-line inst-preview-fade">Key points I will address:</p>
                                                <ul class="inst-preview-points" id="cover-points">
                                                    <!-- Populated by JS -->
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    <button class="inst-add-to-pack-btn" data-product="cover-letter">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="12" y1="5" x2="12" y2="19" />
                                            <line x1="5" y1="12" x2="19" y2="12" />
                                        </svg>
                                        Add to Pack
                                    </button>
                                </div>

                                <!-- Interview Questions Product -->
                                <div class="inst-product-card" data-product="interview-questions">
                                    <div class="inst-product-header">
                                        <div class="inst-product-icon">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <circle cx="12" cy="12" r="10" />
                                                <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3" />
                                                <line x1="12" y1="17" x2="12.01" y2="17" />
                                            </svg>
                                        </div>
                                        <div class="inst-product-meta">
                                            <div class="inst-product-title-row">
                                                <h4 class="inst-product-title">Interview Prep</h4>
                                                <button type="button" class="inst-product-action-btn" data-product="interview-questions">
                                                    Get Questions
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                                                        <path d="M5 12h14M12 5l7 7-7 7" />
                                                    </svg>
                                                </button>
                                            </div>
                                            <?php if (!$is_premium) : ?>
                                                <span class="inst-product-badge inst-product-badge--premium">Premium</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="inst-product-preview" id="preview-interview-questions">
                                        <div class="inst-preview-window">
                                            <div class="inst-preview-titlebar">
                                                <span class="inst-preview-dot"></span>
                                                <span class="inst-preview-dot"></span>
                                                <span class="inst-preview-dot"></span>
                                                <span class="inst-preview-filename">interview_prep.pdf</span>
                                            </div>
                                            <div class="inst-preview-content">
                                                <div class="inst-preview-label">Likely questions:</div>
                                                <ul class="inst-preview-questions" id="interview-questions-list">
                                                    <!-- Populated by JS -->
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    <button class="inst-add-to-pack-btn" data-product="interview-questions">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="12" y1="5" x2="12" y2="19" />
                                            <line x1="5" y1="12" x2="19" y2="12" />
                                        </svg>
                                        Add to Pack
                                    </button>
                                </div>

                                <!-- ATS Optimisation Product -->
                                <div class="inst-product-card" data-product="ats-optimisation">
                                    <div class="inst-product-header">
                                        <div class="inst-product-icon">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
                                            </svg>
                                        </div>
                                        <div class="inst-product-meta">
                                            <div class="inst-product-title-row">
                                                <h4 class="inst-product-title">ATS Optimised</h4>
                                                <button type="button" class="inst-product-action-btn" data-product="ats-optimisation">
                                                    Optimise for ATS
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                                                        <path d="M5 12h14M12 5l7 7-7 7" />
                                                    </svg>
                                                </button>
                                            </div>
                                            <?php if (!$is_premium) : ?>
                                                <span class="inst-product-badge inst-product-badge--premium">Premium</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="inst-product-preview" id="preview-ats-optimisation">
                                        <div class="inst-preview-window">
                                            <div class="inst-preview-titlebar">
                                                <span class="inst-preview-dot"></span>
                                                <span class="inst-preview-dot"></span>
                                                <span class="inst-preview-dot"></span>
                                                <span class="inst-preview-filename">ats_report.pdf</span>
                                            </div>
                                            <div class="inst-preview-content">
                                                <div class="inst-preview-label">ATS Keywords Required:</div>
                                                <div class="inst-preview-ats-keywords" id="ats-keywords">
                                                    <!-- Populated by JS -->
                                                </div>
                                                <div class="inst-preview-ats-score">
                                                    <span class="inst-ats-label">Match potential:</span>
                                                    <span class="inst-ats-value" id="ats-match">--</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <button class="inst-add-to-pack-btn" data-product="ats-optimisation">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="12" y1="5" x2="12" y2="19" />
                                            <line x1="5" y1="12" x2="19" y2="12" />
                                        </svg>
                                        Add to Pack
                                    </button>
                                </div>

                            </div>
                        </div>

                        <!-- Floating Mini Checkout Sidebar -->
                        <div class="inst-pack-sidebar" id="instPackSidebar">
                            <div class="inst-pack-sidebar-inner">
                                <div class="inst-pack-summary-header">
                                    <div class="inst-pack-summary-icon">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" />
                                            <line x1="3" y1="6" x2="21" y2="6" />
                                            <path d="M16 10a4 4 0 0 1-8 0" />
                                        </svg>
                                    </div>
                                    <div class="inst-pack-summary-title">
                                        <h4>Your Pack</h4>
                                        <span class="inst-pack-count"><span id="instPackCount">0</span> items</span>
                                    </div>
                                </div>

                                <div class="inst-pack-items" id="instPackItems">
                                    <div class="inst-pack-empty">
                                        <p>Add items to your pack</p>
                                    </div>
                                </div>

                                <div class="inst-pack-sidebar-footer">
                                    <button class="inst-generate-pack-btn" id="instGeneratePackBtn" disabled>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                            <polyline points="7 10 12 15 17 10" />
                                            <line x1="12" y1="15" x2="12" y2="3" />
                                        </svg>
                                        Download Pack
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- ========================================
                     EXPRESS INTEREST TAB
                     ======================================== -->
                <div class="inst-tab-view" id="inst-express-interest-view" role="tabpanel" style="display: none;">

                    <div class="inst-express-form">

                        <!-- Form Header -->
                        <div class="inst-express-header">
                            <h2>Express Your Interest</h2>
                            <p>Send a personalized message to the recruiter</p>
                        </div>

                        <!-- Recruiter Card in Express Interest -->
                        <?php if ($recruiter_name || $recruiter_company) : ?>
                            <div class="inst-express-recruiter">
                                <div class="inst-recruiter-card inst-recruiter-card--compact" data-recruiter-id="<?php echo esc_attr($crm_recruiter_id); ?>" data-recruiter-name="<?php echo esc_attr($recruiter_name); ?>" data-recruiter-company="<?php echo esc_attr($recruiter_company); ?>" data-recruiter-email="<?php echo esc_attr($recruiter_email); ?>">
                                    <div class="inst-recruiter-avatar<?php echo $recruiter_photo ? ' inst-recruiter-avatar--has-image' : ''; ?>" data-avatar-initial="<?php echo esc_attr($crm_avatar_initial); ?>">
                                        <?php if ($recruiter_photo) : ?>
                                            <img src="<?php echo esc_url($recruiter_photo); ?>" alt="<?php echo esc_attr($display_recruiter_name ?: $recruiter_name); ?>">
                                        <?php else : ?>
                                            <?php echo esc_html($crm_avatar_initial); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="inst-recruiter-info">
                                        <span class="inst-recruiter-label">Reaching out to</span>
                                        <?php if ($condensed_recruiter_name) : ?>
                                            <span class="inst-recruiter-name"><?php echo esc_html($condensed_recruiter_name); ?></span>
                                        <?php endif; ?>
                                        <?php if ($meta_title && $meta_company) : ?>
                                            <span class="inst-recruiter-meta"><?php echo esc_html($meta_title . ' at ' . $meta_company); ?></span>
                                        <?php elseif ($meta_company) : ?>
                                            <span class="inst-recruiter-meta"><?php echo esc_html($meta_company); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="inst-recruiter-actions">
                                        <?php if ($recruiter_linkedin && $is_logged_in) : ?>
                                            <a href="<?php echo esc_url($recruiter_linkedin); ?>" target="_blank" class="inst-recruiter-link inst-recruiter-link--small" title="View LinkedIn">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z" />
                                                    <rect x="2" y="9" width="4" height="12" />
                                                    <circle cx="4" cy="4" r="2" />
                                                </svg>
                                            </a>
                                        <?php endif; ?>
                                        <button type="button" class="inst-add-to-list-btn inst-add-to-list-btn--small" title="Message recruiter" data-behavior="message" data-scroll-target=".inst-express-section">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                                                <polyline points="22,6 12,13 2,6" />
                                            </svg>
                                            <span class="screen-reader-text">Message recruiter</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Personal Information -->
                        <div class="inst-express-section">
                            <h3>Your Details</h3>
                            <div class="inst-form-row">
                                <div class="inst-form-field">
                                    <label for="instFirstName">First Name</label>
                                    <input type="text" id="instFirstName" placeholder="Enter your first name" value="<?php echo esc_attr($user_first_name); ?>" required>
                                </div>
                                <div class="inst-form-field">
                                    <label for="instLastName">Last Name</label>
                                    <input type="text" id="instLastName" placeholder="Enter your last name" value="<?php echo esc_attr($user_last_name); ?>" required>
                                </div>
                            </div>
                            <div class="inst-form-row">
                                <div class="inst-form-field inst-form-field--full">
                                    <label for="instEmail">Email Address</label>
                                    <input type="email" id="instEmail" placeholder="Enter your email address" value="<?php echo esc_attr($user_email); ?>" required>
                                </div>
                            </div>
                        </div>

                        <?php
                        if (!function_exists('inst_get_recruiter_random_role')) {
                            function inst_get_recruiter_random_role($recruiter)
                            {
                                static $inst_recruiter_role_cache = [];

                                if (empty($recruiter) || !is_array($recruiter)) {
                                    return null;
                                }

                                $cache_key = md5(
                                    ($recruiter['id'] ?? '') . '|' .
                                        strtolower($recruiter['email'] ?? '') . '|' .
                                        strtolower($recruiter['name'] ?? '')
                                );

                                if (isset($inst_recruiter_role_cache[$cache_key])) {
                                    return $inst_recruiter_role_cache[$cache_key];
                                }

                                $meta_query = [];

                                if (!empty($recruiter['id'])) {
                                    $meta_query[] = [
                                        'key' => '_crm_recruiter_id',
                                        'value' => (int) $recruiter['id'],
                                        'compare' => '=',
                                        'type' => 'NUMERIC',
                                    ];
                                }

                                $email = !empty($recruiter['email']) ? sanitize_email($recruiter['email']) : '';
                                if ($email) {
                                    $meta_query[] = [
                                        'key' => '_recruiter_email',
                                        'value' => $email,
                                        'compare' => '=',
                                    ];
                                }

                                $name = !empty($recruiter['name']) ? sanitize_text_field($recruiter['name']) : '';
                                if ($name) {
                                    $meta_query[] = [
                                        'key' => '_recruiter_name',
                                        'value' => $name,
                                        'compare' => '=',
                                    ];
                                }

                                if (empty($meta_query)) {
                                    $inst_recruiter_role_cache[$cache_key] = null;
                                    return null;
                                }

                                if (count($meta_query) > 1) {
                                    $meta_query = array_merge([
                                        'relation' => 'OR',
                                    ], $meta_query);
                                }

                                $matching_posts = get_posts([
                                    'post_type' => 'sffc_recruiter_post',
                                    'post_status' => 'publish',
                                    'posts_per_page' => 1,
                                    'orderby' => 'rand',
                                    'meta_query' => $meta_query,
                                    'fields' => 'ids',
                                    'no_found_rows' => true,
                                ]);

                                if (!empty($matching_posts)) {
                                    $post_id = (int) $matching_posts[0];
                                    $job_title = get_post_meta($post_id, '_job_title', true);
                                    if (!$job_title) {
                                        $job_title = get_the_title($post_id);
                                    }
                                    $company_name = get_post_meta($post_id, '_company_name', true);

                                    $inst_recruiter_role_cache[$cache_key] = [
                                        'title' => $job_title,
                                        'company' => $company_name,
                                    ];

                                    return $inst_recruiter_role_cache[$cache_key];
                                }

                                $inst_recruiter_role_cache[$cache_key] = null;
                                return null;
                            }
                        }

                        if (!function_exists('inst_get_recruiter_public_labels')) {
                            function inst_get_recruiter_public_labels($recruiter_ids)
                            {
                                global $wpdb;

                                if (empty($recruiter_ids) || !is_array($recruiter_ids)) {
                                    return [];
                                }

                                $ids = array_values(array_filter(array_map('intval', $recruiter_ids)));
                                if (empty($ids)) {
                                    return [];
                                }

                                $table = $wpdb->prefix . 'sffc_crm_posts';
                                $ids_sql = implode(',', $ids);
                                $rows = $wpdb->get_results(
                                    "SELECT recruiter_id, recruiter_display_name, recruiter_display_company
                                     FROM {$table}
                                     WHERE recruiter_id IN ({$ids_sql})
                                       AND admin_approved = 1
                                       AND (recruiter_display_name <> '' OR recruiter_display_company <> '')
                                     ORDER BY posted_at DESC",
                                    ARRAY_A
                                );

                                $labels = [];
                                if ($rows) {
                                    foreach ($rows as $row) {
                                        $rid = (int) ($row['recruiter_id'] ?? 0);
                                        if (!$rid || isset($labels[$rid])) {
                                            continue;
                                        }
                                        $labels[$rid] = [
                                            'name' => $row['recruiter_display_name'] ?? '',
                                            'company' => $row['recruiter_display_company'] ?? '',
                                        ];
                                    }
                                }

                                return $labels;
                            }
                        }

                        $inst_featured_recruiters = [];
                        if (class_exists('SFFC_CRM_Recruiter')) {
                            global $wpdb;
                            $recruiter_table = $wpdb->prefix . 'sffc_crm_recruiters';
                            $inst_featured_recruiters = $wpdb->get_results(
                                "SELECT id, name, title, firm, email, photo_url, sectors, total_posts
                                 FROM {$recruiter_table}
                                 WHERE is_active = 1
                                 ORDER BY RAND()
                                 LIMIT 6",
                                ARRAY_A
                            );
                        }
                        $inst_recruiter_display_labels = [];
                        if (!empty($inst_featured_recruiters)) {
                            $recruiter_ids = wp_list_pluck($inst_featured_recruiters, 'id');
                            $inst_recruiter_display_labels = inst_get_recruiter_public_labels($recruiter_ids);
                        }

                        $inst_outreach_role = $job_title ? wp_strip_all_tags($job_title) : 'This Role';
                        $inst_outreach_cta = sprintf('Reach More Recruiters Hiring for %s', $inst_outreach_role);
                        ?>

                        <div class="inst-express-section inst-recruiter-outreach" id="instRecruiterOutreachSection">
                            <h3><?php echo esc_html($inst_outreach_cta); ?></h3>
                            <p class="inst-section-description">Select 3-6 recruiters and MENA Careers will prep a mini LinkedIn campaign.</p>

                            <div class="inst-outreach-grid" id="instRecruiterOutreachGrid">
                                <?php if (!empty($inst_featured_recruiters)) : ?>
                                    <?php foreach ($inst_featured_recruiters as $featured) :
                                        $recruiter_id = (int) ($featured['id'] ?? 0);
                                        $sectors = [];
                                        if (!empty($featured['sectors'])) {
                                            $decoded = json_decode($featured['sectors'], true);
                                            if (is_array($decoded)) {
                                                $sectors = array_filter(array_map('trim', $decoded));
                                            }
                                        }
                                        $role_context = inst_get_recruiter_random_role($featured);
                                        if (!empty($role_context['title'])) {
                                            $primary_focus = $role_context['title'];
                                            $role_company = $role_context['company'] ?? '';
                                        } else {
                                            $role_company = '';
                                            $primary_focus = !empty($sectors) ? ucwords(str_replace('_', ' ', $sectors[0])) : ($featured['title'] ?: 'Finance Roles');
                                        }
                                        $initial = $featured['name'] ? substr($featured['name'], 0, 1) : 'R';
                                        $has_photo = !empty($featured['photo_url']);

                                        $display_name = $featured['name'] ?: 'Recruiter';
                                        $display_firm = $featured['firm'] ?: '';

                                        if (!$is_logged_in) {
                                            $public_label = $inst_recruiter_display_labels[$recruiter_id]['name'] ?? '';
                                            $public_company = $inst_recruiter_display_labels[$recruiter_id]['company'] ?? '';

                                            if ($public_label) {
                                                $display_name = $public_label;
                                            } elseif (!empty($sectors[0])) {
                                                $display_name = sprintf('%s Recruiter', ucwords(str_replace('_', ' ', $sectors[0])));
                                            } else {
                                                $display_name = 'Specialist Recruiter';
                                            }

                                            if ($public_company) {
                                                $display_firm = $public_company;
                                            } elseif (!$display_firm) {
                                                $display_firm = 'Global Search Firm';
                                            }
                                        }
                                    ?>
                                        <label class="inst-outreach-card">
                                            <input type="checkbox"
                                                class="inst-outreach-checkbox"
                                                value="<?php echo esc_attr($featured['id']); ?>"
                                                data-recruiter-id="<?php echo esc_attr($featured['id']); ?>"
                                                data-recruiter-name="<?php echo esc_attr($featured['name']); ?>"
                                                data-recruiter-company="<?php echo esc_attr($featured['firm']); ?>"
                                                data-recruiter-email="<?php echo esc_attr($featured['email']); ?>"
                                                data-recruiter-role="<?php echo esc_attr($primary_focus); ?>">
                                            <div class="inst-outreach-card-inner">
                                                <span class="inst-outreach-check-icon" aria-hidden="true"></span>
                                                <div class="inst-outreach-avatar inst-recruiter-avatar<?php echo $has_photo ? ' inst-recruiter-avatar--has-image' : ''; ?>">
                                                    <?php if ($has_photo) : ?>
                                                        <img src="<?php echo esc_url($featured['photo_url']); ?>" alt="<?php echo esc_attr($featured['name']); ?>">
                                                    <?php else : ?>
                                                        <span><?php echo esc_html($initial); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="inst-outreach-info">
                                                    <span class="inst-outreach-name"><?php echo esc_html($display_name); ?></span>
                                                    <span class="inst-outreach-firm"><?php echo esc_html($display_firm); ?></span>
                                                    <span class="inst-outreach-role">
                                                        Hiring for: <?php echo esc_html($primary_focus); ?>
                                                        <?php if (!empty($role_company)) : ?>
                                                            <span class="inst-outreach-role-company">@ <?php echo esc_html($role_company); ?></span>
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <p class="inst-outreach-empty">We're curating recommended recruiters for this role.</p>
                                <?php endif; ?>
                            </div>

                            <div class="inst-outreach-actions">
                                <div class="inst-outreach-summary">
                                    <span id="instOutreachCount">0</span>/6 selected — choose at least 3 recruiters.
                                </div>
                                <div class="inst-outreach-buttons">
                                    <button type="button" class="inst-outreach-btn inst-outreach-btn--primary" id="instBulkReachOutBtn" disabled>Send CV</button>
                                    <button type="button" class="inst-outreach-btn inst-outreach-btn--secondary" id="instBulkAddBtn" disabled>Add to List</button>
                                </div>
                            </div>
                        </div>
                        <!-- CRM Tracking Option (Only shown if logged in) -->
                        <?php if (is_user_logged_in()) : ?>
                            <div class="inst-express-section inst-crm-tracking">
                                <label class="inst-crm-checkbox">
                                    <input type="checkbox" id="instTrackApplication" checked>
                                    <span class="inst-checkbox-switch"></span>
                                    <span class="inst-checkbox-text">
                                        <strong>Track this application</strong>
                                        <span>Keep all your applications organized in your Career CRM</span>
                                    </span>
                                </label>
                            </div>
                        <?php endif; ?>

                        <!-- Submit Button -->
                        <div class="inst-express-actions">
                            <button class="inst-message-recruiter-btn" id="instMessageRecruiterBtn">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="22" y1="2" x2="11" y2="13" />
                                    <polygon points="22 2 15 22 11 13 2 9 22 2" />
                                </svg>
                                Message Recruiter
                            </button>
                        </div>

                    </div>
                </div>

                <?php if (false) : ?>
                    <!-- ========================================
                     SIMILAR POSTS TAB (disabled)
                     ======================================== -->
                    <div class="inst-tab-view" id="inst-similar-posts-view" role="tabpanel" style="display: none;">
                        <div id="instSimilarPostsRegion" data-current-post="<?php echo esc_attr($post_id); ?>" data-job-title="<?php echo esc_attr($job_title); ?>" data-is-premium="<?php echo $is_premium ? 'true' : 'false'; ?>">
                            <?php
                            // Query similar posts by job title keywords
                            $title_keywords = array_filter(
                                preg_split('/[\s,\-\/]+/', strtolower($job_title)),
                                function ($word) {
                                    return strlen($word) > 3 && !in_array($word, ['senior', 'junior', 'lead', 'head', 'manager', 'director', 'with', 'from', 'the', 'and', 'for']);
                                }
                            );

                            $similar_args = [
                                'post_type' => 'sffc_recruiter_post',
                                'posts_per_page' => $is_premium ? 9 : 3,
                                'post_status' => 'publish',
                                'post__not_in' => [$post_id],
                                'orderby' => 'date',
                                'order' => 'DESC',
                            ];

                            // Build meta query for job title similarity
                            if (!empty($title_keywords)) {
                                $meta_query = ['relation' => 'OR'];
                                foreach (array_slice($title_keywords, 0, 3) as $keyword) {
                                    $meta_query[] = [
                                        'key' => '_job_title',
                                        'value' => $keyword,
                                        'compare' => 'LIKE'
                                    ];
                                }
                                $similar_args['meta_query'] = $meta_query;
                            }

                            // Also try matching industry
                            if (!empty($industry_label)) {
                                $similar_args['tax_query'] = [
                                    [
                                        'taxonomy' => 'recruiter_post_industry',
                                        'field' => 'name',
                                        'terms' => [$industry_label],
                                    ]
                                ];
                            }

                            $similar_posts = new WP_Query($similar_args);

                            // Fallback: If no matches, get recent posts from same industry
                            if (!$similar_posts->have_posts() && !empty($industry_label)) {
                                $similar_args = [
                                    'post_type' => 'sffc_recruiter_post',
                                    'posts_per_page' => $is_premium ? 9 : 3,
                                    'post_status' => 'publish',
                                    'post__not_in' => [$post_id],
                                    'orderby' => 'date',
                                    'order' => 'DESC',
                                    'tax_query' => [
                                        [
                                            'taxonomy' => 'recruiter_post_industry',
                                            'field' => 'name',
                                            'terms' => [$industry_label],
                                        ]
                                    ]
                                ];
                                $similar_posts = new WP_Query($similar_args);
                            }

                            // Final fallback: recent posts
                            if (!$similar_posts->have_posts()) {
                                $similar_args = [
                                    'post_type' => 'sffc_recruiter_post',
                                    'posts_per_page' => $is_premium ? 9 : 3,
                                    'post_status' => 'publish',
                                    'post__not_in' => [$post_id],
                                    'orderby' => 'date',
                                    'order' => 'DESC',
                                ];
                                $similar_posts = new WP_Query($similar_args);
                            }

                            $total_similar = $similar_posts->found_posts;
                            $has_more = $is_premium && $total_similar > 9;
                            $has_posts = $similar_posts->have_posts();
                            ?>

                            <div class="inst-similar-posts-header">
                                <div class="inst-similar-posts-header-left">
                                    <h2>Similar Recruiter Posts</h2>
                                    <?php if ($is_premium) : ?>
                                        <p>Select recruiters to reach out with personalized AI messages</p>
                                    <?php elseif ($is_logged_in) : ?>
                                        <p>Upgrade to premium to unlock AI-powered outreach to similar recruiters</p>
                                    <?php else : ?>
                                        <p>Join MENA Careers to access similar recruiter posts and AI-powered outreach</p>
                                    <?php endif; ?>
                                </div>
                                <?php if ($is_premium && $has_posts) : ?>
                                    <div class="inst-similar-posts-header-right">
                                        <label class="inst-similar-select-all">
                                            <input type="checkbox" id="instSelectAllSimilar">
                                            <span>Select All</span>
                                        </label>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($is_premium) : ?>
                                <!-- Bulk Actions Bar (Premium Only) -->
                                <div class="inst-similar-bulk-bar" id="instSimilarBulkBar" style="display: none;">
                                    <div class="inst-similar-bulk-info">
                                        <span class="inst-similar-bulk-count">0</span> recruiters selected
                                    </div>
                                    <div class="inst-similar-bulk-actions">
                                        <button type="button" class="inst-similar-bulk-btn inst-similar-bulk-btn--list" id="instBulkAddToList">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <line x1="12" y1="5" x2="12" y2="19" />
                                                <line x1="5" y1="12" x2="19" y2="12" />
                                            </svg>
                                            Add to List
                                        </button>
                                        <button type="button" class="inst-similar-bulk-btn inst-similar-bulk-btn--outreach" id="instBulkOutreach">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <line x1="22" y1="2" x2="11" y2="13" />
                                                <polygon points="22 2 15 22 11 13 2 9 22 2" />
                                            </svg>
                                            Outreach with AI
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($has_posts) : ?>
                                <div class="inst-similar-posts-grid<?php echo !$is_premium ? ' inst-similar-posts-grid--preview' : ''; ?>" id="instSimilarPostsGrid">
                                    <?php
                                    $post_count = 0;
                                    while ($similar_posts->have_posts()) : $similar_posts->the_post();
                                        $post_count++;
                                        $sim_post_id = get_the_ID();
                                        $sim_job_title = get_post_meta($sim_post_id, '_job_title', true) ?: get_the_title();
                                        $sim_company = get_post_meta($sim_post_id, '_company_name', true);
                                        $sim_location = get_post_meta($sim_post_id, '_job_location', true);
                                        $sim_salary_min = get_post_meta($sim_post_id, '_salary_min', true);
                                        $sim_salary_max = get_post_meta($sim_post_id, '_salary_max', true);
                                        $sim_salary_currency = get_post_meta($sim_post_id, '_salary_currency', true) ?: 'AED';
                                        $sim_recruiter_name = get_post_meta($sim_post_id, '_recruiter_name', true);
                                        $sim_recruiter_company = get_post_meta($sim_post_id, '_recruiter_company', true);
                                        $sim_recruiter_email = get_post_meta($sim_post_id, '_recruiter_email', true);
                                        $sim_locations = wp_get_post_terms($sim_post_id, 'recruiter_post_location', ['fields' => 'names']);
                                        $sim_location_label = !empty($sim_locations) ? $sim_locations[0] : $sim_location;

                                        // Get industry and post type
                                        $sim_industries = wp_get_post_terms($sim_post_id, 'recruiter_post_industry', ['fields' => 'names']);
                                        $sim_post_types = wp_get_post_terms($sim_post_id, 'recruiter_post_type', ['fields' => 'names']);
                                        $sim_industry_label = !empty($sim_industries) ? $sim_industries[0] : '';
                                        $sim_post_type_label = !empty($sim_post_types) ? $sim_post_types[0] : '';

                                        // Get recruiter image
                                        $sim_recruiter_image_id = get_post_meta($sim_post_id, '_recruiter_image_id', true);
                                        $sim_recruiter_image_url = $sim_recruiter_image_id ? wp_get_attachment_image_url($sim_recruiter_image_id, 'thumbnail') : '';
                                        if (!$sim_recruiter_image_url) {
                                            $sim_recruiter_image_url = get_post_meta($sim_post_id, '_recruiter_image_url', true);
                                        }

                                        $sim_salary_display = '';
                                        if ($sim_salary_min && $sim_salary_max) {
                                            $sim_salary_display = $sim_salary_currency . ' ' . number_format($sim_salary_min) . ' - ' . number_format($sim_salary_max);
                                        } elseif ($sim_salary_min) {
                                            $sim_salary_display = $sim_salary_currency . ' ' . number_format($sim_salary_min) . '+';
                                        }
                                    ?>
                                        <div class="inst-similar-post-card<?php echo (!$is_premium && $post_count > 1) ? ' inst-similar-post-card--blurred' : ''; ?>"
                                            data-post-id="<?php echo esc_attr($sim_post_id); ?>"
                                            data-recruiter-name="<?php echo esc_attr($sim_recruiter_name); ?>"
                                            data-recruiter-company="<?php echo esc_attr($sim_recruiter_company); ?>"
                                            data-recruiter-email="<?php echo esc_attr($sim_recruiter_email); ?>"
                                            data-job-title="<?php echo esc_attr($sim_job_title); ?>">

                                            <?php if ($is_premium) : ?>
                                                <!-- Selection Checkbox (Premium Only) -->
                                                <label class="inst-similar-post-checkbox">
                                                    <input type="checkbox" class="inst-similar-post-select" value="<?php echo esc_attr($sim_post_id); ?>">
                                                    <span class="inst-similar-checkbox-mark">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                                            <polyline points="20 6 9 17 4 12" />
                                                        </svg>
                                                    </span>
                                                </label>
                                            <?php endif; ?>

                                            <a href="<?php echo $is_premium ? esc_url(get_permalink()) : '#'; ?>" class="inst-similar-post-link<?php echo !$is_premium ? ' inst-similar-post-link--disabled' : ''; ?>" <?php echo !$is_premium ? ' onclick="return false;"' : ''; ?>>
                                                <?php if ($sim_post_type_label || $sim_industry_label) : ?>
                                                    <div class="inst-similar-post-badges">
                                                        <?php if ($sim_post_type_label) : ?>
                                                            <span class="inst-similar-post-badge inst-similar-post-badge--type"><?php echo esc_html($sim_post_type_label); ?></span>
                                                        <?php endif; ?>
                                                        <?php if ($sim_industry_label) : ?>
                                                            <span class="inst-similar-post-badge inst-similar-post-badge--industry"><?php echo esc_html($sim_industry_label); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="inst-similar-post-header">
                                                    <h3 class="inst-similar-post-title"><?php echo esc_html($sim_job_title); ?></h3>
                                                    <?php if ($sim_company) : ?>
                                                        <span class="inst-similar-post-company"><?php echo esc_html($sim_company); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="inst-similar-post-meta">
                                                    <?php if ($sim_location_label) : ?>
                                                        <span class="inst-similar-post-location">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                                                                <circle cx="12" cy="10" r="3" />
                                                            </svg>
                                                            <?php echo esc_html($sim_location_label); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if ($sim_salary_display) : ?>
                                                        <span class="inst-similar-post-salary">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                <line x1="12" y1="1" x2="12" y2="23" />
                                                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                                                            </svg>
                                                            <?php echo esc_html($sim_salary_display); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </a>

                                            <?php if ($sim_recruiter_name) : ?>
                                                <div class="inst-similar-post-recruiter">
                                                    <div class="inst-similar-recruiter-avatar<?php echo $sim_recruiter_image_url ? ' inst-similar-recruiter-avatar--has-image' : ''; ?>">
                                                        <?php if ($sim_recruiter_image_url) : ?>
                                                            <img src="<?php echo esc_url($sim_recruiter_image_url); ?>" alt="<?php echo esc_attr($sim_recruiter_name); ?>">
                                                        <?php else : ?>
                                                            <?php echo esc_html(substr($sim_recruiter_name, 0, 1)); ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="inst-similar-recruiter-info">
                                                        <span class="inst-similar-recruiter-name"><?php echo esc_html($sim_recruiter_name); ?></span>
                                                        <?php if ($sim_recruiter_company) : ?>
                                                            <span class="inst-similar-recruiter-company"><?php echo esc_html($sim_recruiter_company); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if ($is_premium) : ?>
                                                        <button type="button" class="inst-similar-add-to-list" title="Add to outreach list" data-post-id="<?php echo esc_attr($sim_post_id); ?>">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                <line x1="12" y1="5" x2="12" y2="19" />
                                                                <line x1="5" y1="12" x2="19" y2="12" />
                                                            </svg>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php
                                    endwhile;
                                    wp_reset_postdata();
                                    ?>
                                </div>

                                <?php if (!$is_premium) : ?>
                                    <!-- Upgrade/Sign Up CTA Overlay -->
                                    <div class="inst-similar-posts-upgrade">
                                        <div class="inst-similar-posts-upgrade-content">
                                            <div class="inst-similar-posts-upgrade-icon">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                                                    <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                                                </svg>
                                            </div>
                                            <?php if ($is_logged_in) : ?>
                                                <h3>Unlock <?php echo esc_html($total_similar); ?>+ Similar Recruiter Posts</h3>
                                                <p>Upgrade to premium to access all similar posts, AI-powered outreach, and recruiter lists</p>
                                                <a href="https://joinsenna.com/memberships/" class="inst-similar-posts-upgrade-btn">
                                                    Upgrade to Premium
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <line x1="5" y1="12" x2="19" y2="12" />
                                                        <polyline points="12 5 19 12 12 19" />
                                                    </svg>
                                                </a>
                                            <?php else : ?>
                                                <h3>Access <?php echo esc_html($total_similar); ?>+ Similar Recruiter Posts</h3>
                                                <p>Join MENA Careers to discover recruiters hiring for similar roles and reach out with AI-powered messages</p>
                                                <a href="https://joinsenna.com/memberships/" class="inst-similar-posts-upgrade-btn">
                                                    Become a Member
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <line x1="5" y1="12" x2="19" y2="12" />
                                                        <polyline points="12 5 19 12 12 19" />
                                                    </svg>
                                                </a>
                                                <p class="inst-similar-posts-login">Already a member? <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>">Log in</a></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($has_more) : ?>
                                    <div class="inst-similar-load-more-container">
                                        <button type="button" class="inst-similar-load-more" id="instLoadMoreSimilar" data-page="1" data-total="<?php echo esc_attr($total_similar); ?>">
                                            <span>Load More Posts</span>
                                            <span class="inst-similar-load-count">(<?php echo esc_html($total_similar - 9); ?> more)</span>
                                        </button>
                                    </div>
                                <?php endif; ?>

                                <!-- Can't Find What You're Looking For? (When posts exist) -->
                                <div class="inst-cant-find-section" id="instCantFindSection">
                                    <button type="button" class="inst-cant-find-trigger" id="instCantFindTrigger">
                                        <div class="inst-cant-find-trigger-content">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <circle cx="11" cy="11" r="8" />
                                                <line x1="21" y1="21" x2="16.65" y2="16.65" />
                                                <line x1="8" y1="11" x2="14" y2="11" />
                                            </svg>
                                            <span>Can't find what you're looking for?</span>
                                        </div>
                                        <svg class="inst-cant-find-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="6 9 12 15 18 9" />
                                        </svg>
                                    </button>

                                    <div class="inst-cant-find-form-wrapper" id="instCantFindFormWrapper">
                                        <div class="inst-scan-request-intro">
                                            <h4>Request a Personalized Recruiter Scan</h4>
                                            <p>Tell us what you're looking for and our team will manually search for recruiters matching your criteria. We'll notify you when we find matches!</p>
                                        </div>

                                        <div class="inst-scan-request-form" id="instScanRequestFormInline">
                                            <div class="inst-scan-request-row">
                                                <div class="inst-scan-request-field">
                                                    <label for="instScanRoleInline">Target Role / Job Title <span class="required">*</span></label>
                                                    <input type="text" id="instScanRoleInline" placeholder="e.g., Investment Associate, Vice President, Principal" value="<?php echo esc_attr($job_title); ?>">
                                                </div>
                                                <div class="inst-scan-request-field">
                                                    <label for="instScanLocationInline">Preferred Location(s) <span class="required">*</span></label>
                                                    <input type="text" id="instScanLocationInline" placeholder="e.g., Dubai, Abu Dhabi, Riyadh, Cairo">
                                                </div>
                                            </div>

                                            <div class="inst-scan-request-row">
                                                <div class="inst-scan-request-field">
                                                    <label for="instScanIndustryInline">Industry / Sector</label>
                                                    <input type="text" id="instScanIndustryInline" placeholder="e.g., Private Equity, Investment Banking, Asset Management">
                                                </div>
                                                <div class="inst-scan-request-field">
                                                    <label for="instScanSalaryInline">Expected Salary Range</label>
                                                    <input type="text" id="instScanSalaryInline" placeholder="e.g., $150k - $200k, AED 50k+/month">
                                                </div>
                                            </div>

                                            <div class="inst-scan-request-field inst-scan-request-field--full">
                                                <label for="instScanExperienceInline">Years of Experience</label>
                                                <select id="instScanExperienceInline">
                                                    <option value="">Select experience level</option>
                                                    <option value="0-2">0-2 years (Entry Level)</option>
                                                    <option value="3-5">3-5 years (Mid Level)</option>
                                                    <option value="6-10">6-10 years (Senior)</option>
                                                    <option value="10+">10+ years (Executive/Director)</option>
                                                </select>
                                            </div>

                                            <div class="inst-scan-request-field inst-scan-request-field--full">
                                                <label for="instScanNotesInline">Additional Requirements</label>
                                                <textarea id="instScanNotesInline" rows="3" placeholder="Any specific skills, certifications, company preferences, or other requirements..."></textarea>
                                            </div>

                                            <div class="inst-scan-request-actions">
                                                <button type="button" class="inst-scan-request-btn" id="instScanRequestBtnInline">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <line x1="22" y1="2" x2="11" y2="13" />
                                                        <polygon points="22 2 15 22 11 13 2 9 22 2" />
                                                    </svg>
                                                    Submit Scan Request
                                                </button>
                                                <button type="button" class="inst-scan-request-cancel" id="instScanRequestCancel">Cancel</button>
                                            </div>

                                            <p class="inst-scan-request-note">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <circle cx="12" cy="12" r="10" />
                                                    <line x1="12" y1="16" x2="12" y2="12" />
                                                    <line x1="12" y1="8" x2="12.01" y2="8" />
                                                </svg>
                                                We typically respond within 1-2 business days with curated recruiter matches.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                            <?php else : ?>
                                <!-- No Posts Found - Request Scan (Full Form) -->
                                <div class="inst-similar-posts-empty" id="instSimilarPostsEmpty">
                                    <div class="inst-similar-empty-header">
                                        <div class="inst-similar-empty-icon">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <circle cx="11" cy="11" r="8" />
                                                <line x1="21" y1="21" x2="16.65" y2="16.65" />
                                            </svg>
                                        </div>
                                        <h3>No Similar Recruiter Posts Found</h3>
                                        <p>We couldn't find recruiters with similar roles in our database, but don't worry - our team can help you find the right connections!</p>
                                    </div>

                                    <div class="inst-scan-request-card">
                                        <div class="inst-scan-request-card-header">
                                            <div class="inst-scan-request-card-icon">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                                                    <polyline points="22 4 12 14.01 9 11.01" />
                                                </svg>
                                            </div>
                                            <div class="inst-scan-request-card-title">
                                                <h4>Request a Personalized Recruiter Scan</h4>
                                                <p>Tell us exactly what you're looking for</p>
                                            </div>
                                        </div>

                                        <div class="inst-scan-request-form" id="instScanRequestForm">
                                            <div class="inst-scan-request-row">
                                                <div class="inst-scan-request-field">
                                                    <label for="instScanRequestRole">Target Role / Job Title <span class="required">*</span></label>
                                                    <input type="text" id="instScanRequestRole" placeholder="e.g., Investment Associate, Vice President, Principal" value="<?php echo esc_attr($job_title); ?>">
                                                    <span class="inst-field-hint">Be specific about the role you're targeting</span>
                                                </div>
                                                <div class="inst-scan-request-field">
                                                    <label for="instScanRequestLocation">Preferred Location(s) <span class="required">*</span></label>
                                                    <input type="text" id="instScanRequestLocation" placeholder="e.g., Dubai, Abu Dhabi, Riyadh, Cairo">
                                                    <span class="inst-field-hint">You can list multiple locations</span>
                                                </div>
                                            </div>

                                            <div class="inst-scan-request-row">
                                                <div class="inst-scan-request-field">
                                                    <label for="instScanRequestIndustry">Industry / Sector</label>
                                                    <input type="text" id="instScanRequestIndustry" placeholder="e.g., Private Equity, Investment Banking, Asset Management">
                                                </div>
                                                <div class="inst-scan-request-field">
                                                    <label for="instScanRequestSalary">Expected Salary Range</label>
                                                    <input type="text" id="instScanRequestSalary" placeholder="e.g., $150k - $200k, AED 50k+/month">
                                                </div>
                                            </div>

                                            <div class="inst-scan-request-field inst-scan-request-field--full">
                                                <label for="instScanRequestExperience">Years of Experience</label>
                                                <select id="instScanRequestExperience">
                                                    <option value="">Select your experience level</option>
                                                    <option value="0-2">0-2 years (Entry Level)</option>
                                                    <option value="3-5">3-5 years (Mid Level)</option>
                                                    <option value="6-10">6-10 years (Senior)</option>
                                                    <option value="10+">10+ years (Executive/Director)</option>
                                                </select>
                                            </div>

                                            <div class="inst-scan-request-field inst-scan-request-field--full">
                                                <label for="instScanRequestNotes">Additional Requirements</label>
                                                <textarea id="instScanRequestNotes" rows="4" placeholder="Describe any specific skills, certifications, company preferences, or other requirements that are important to you..."></textarea>
                                            </div>

                                            <button type="button" class="inst-scan-request-btn inst-scan-request-btn--large" id="instScanRequestBtn">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <line x1="22" y1="2" x2="11" y2="13" />
                                                    <polygon points="22 2 15 22 11 13 2 9 22 2" />
                                                </svg>
                                                Submit Recruiter Scan Request
                                            </button>

                                            <div class="inst-scan-request-features">
                                                <div class="inst-scan-request-feature">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <polyline points="20 6 9 17 4 12" />
                                                    </svg>
                                                    <span>Manual search by our recruitment experts</span>
                                                </div>
                                                <div class="inst-scan-request-feature">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <polyline points="20 6 9 17 4 12" />
                                                    </svg>
                                                    <span>Curated list of relevant recruiters</span>
                                                </div>
                                                <div class="inst-scan-request-feature">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <polyline points="20 6 9 17 4 12" />
                                                    </svg>
                                                    <span>Email notification when matches are found</span>
                                                </div>
                                                <div class="inst-scan-request-feature">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <polyline points="20 6 9 17 4 12" />
                                                    </svg>
                                                    <span>Response within 1-2 business days</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            </div><!-- End Tab Views Container -->

            <!-- ========================================
                 MEMBERSHIP MODAL (Logged Out Users)
                 ======================================== -->
            <div class="inst-membership-modal" id="instMembershipModal" style="display: none;">
                <div class="inst-membership-overlay"></div>
                <div class="inst-membership-content">
                    <button type="button" class="inst-membership-close" id="instMembershipClose">&times;</button>

                    <div class="inst-membership-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                            <circle cx="9" cy="7" r="4" />
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                        </svg>
                    </div>

                    <h3>Unlock Smart Outreach</h3>
                    <p>Join MENA Careers to access powerful career tools that help you stand out</p>

                    <ul class="inst-membership-benefits">
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12" />
                            </svg>
                            AI-powered personalized outreach messages
                        </li>
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12" />
                            </svg>
                            Save recruiters to custom outreach lists
                        </li>
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12" />
                            </svg>
                            Track all your applications in one place
                        </li>
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12" />
                            </svg>
                            Get tailored CV and cover letter for each role
                        </li>
                    </ul>

                    <a href="https://joinsenna.com/memberships/" class="inst-membership-cta">
                        Become a Member
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="5" y1="12" x2="19" y2="12" />
                            <polyline points="12 5 19 12 12 19" />
                        </svg>
                    </a>

                    <p class="inst-membership-login">Already a member? <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>">Log in</a></p>
                </div>
            </div>

            <!-- ========================================
                 CREATE LIST MODAL
                 ======================================== -->
            <div class="inst-create-list-modal" id="instCreateListModal" style="display: none;">
                <div class="inst-create-list-overlay"></div>
                <div class="inst-create-list-content">
                    <button type="button" class="inst-create-list-close" id="instCreateListClose">&times;</button>

                    <div class="inst-create-list-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                            <polyline points="14 2 14 8 20 8" />
                            <line x1="12" y1="18" x2="12" y2="12" />
                            <line x1="9" y1="15" x2="15" y2="15" />
                        </svg>
                    </div>

                    <h3>Create Outreach List</h3>
                    <p>Organize your recruiters into lists for targeted outreach</p>

                    <div class="inst-create-list-form">
                        <div class="inst-create-list-field">
                            <label for="instNewListName">List Name</label>
                            <input type="text" id="instNewListName" placeholder="e.g., Finance Recruiters, Tech Startups">
                        </div>
                        <button type="button" class="inst-create-list-btn" id="instCreateListBtn">
                            Create List
                        </button>
                    </div>

                    <div class="inst-existing-lists" id="instExistingLists" style="display: none;">
                        <h4>Or add to existing list:</h4>
                        <div class="inst-existing-lists-container"></div>
                    </div>
                </div>
            </div>

            <!-- ========================================
                 BULK OUTREACH MODAL
                 ======================================== -->
            <div class="inst-bulk-outreach-modal" id="instBulkOutreachModal" style="display: none;">
                <div class="inst-bulk-outreach-overlay"></div>
                <div class="inst-bulk-outreach-content">
                    <button type="button" class="inst-bulk-outreach-close" id="instBulkOutreachClose">&times;</button>

                    <div class="inst-bulk-outreach-header">
                        <h3>AI-Powered Outreach</h3>
                        <p>Each recruiter will receive a unique, personalized message</p>
                    </div>

                    <div class="inst-bulk-outreach-progress" id="instBulkOutreachProgress" style="display: none;">
                        <div class="inst-bulk-progress-bar">
                            <div class="inst-bulk-progress-fill" id="instBulkProgressFill"></div>
                        </div>
                        <p class="inst-bulk-progress-text">Generating message <span id="instBulkProgressCurrent">1</span> of <span id="instBulkProgressTotal">0</span>...</p>
                    </div>

                    <div class="inst-bulk-outreach-list" id="instBulkOutreachList">
                        <!-- Populated dynamically -->
                    </div>

                    <div class="inst-bulk-outreach-cv" id="instBulkOutreachCv">
                        <h4>Your Background (for personalization)</h4>
                        <p>Paste your CV or describe your experience so AI can craft relevant messages</p>
                        <textarea id="instBulkCvContext" rows="4" placeholder="Paste your CV or describe your professional background..."></textarea>
                        <button type="button" class="inst-bulk-save-cv" id="instBulkSaveCv">Save for Future</button>
                    </div>

                    <div class="inst-bulk-outreach-actions">
                        <button type="button" class="inst-bulk-generate-btn" id="instBulkGenerateBtn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" />
                            </svg>
                            Generate All Messages
                        </button>
                    </div>

                    <div class="inst-bulk-outreach-results" id="instBulkOutreachResults" style="display: none;">
                        <!-- Generated messages will appear here -->
                    </div>
                </div>
            </div>

        </div><!-- End Analysis Container -->

        <!-- ========================================
             SUCCESS STATE
             ======================================== -->
        <div class="inst-success-container" id="inst-success-container" style="display: none;">
            <div class="inst-success-content">
                <div class="inst-success-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                        <polyline points="22 4 12 14.01 9 11.01" />
                    </svg>
                </div>
                <h2>Application Sent!</h2>
                <p>Your application for <strong><?php echo esc_html($job_title); ?></strong><?php if ($company_name) : ?> at <strong><?php echo esc_html($company_name); ?></strong><?php endif; ?> has been sent successfully.</p>
                <p class="inst-success-note">Check your email for confirmation and next steps.</p>
                <?php if (is_user_logged_in()) : ?>
                    <a href="<?php echo esc_url(home_url('/career-crm/')); ?>" class="inst-success-cta">
                        Track Your Applications
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="5" y1="12" x2="19" y2="12" />
                            <polyline points="12 5 19 12 12 19" />
                        </svg>
                    </a>
                <?php endif; ?>
                <button class="inst-success-secondary" id="instNewApplication">
                    View Another Role
                </button>
            </div>
        </div>

        <!-- ========================================
             RESPONSE LIKELIHOOD MODAL
             ======================================== -->
        <div class="inst-likelihood-modal" id="instLikelihoodModal" style="display: none;">
            <div class="inst-likelihood-overlay"></div>
            <div class="inst-likelihood-content">
                <button type="button" class="inst-likelihood-close" id="instLikelihoodClose">&times;</button>

                <div class="inst-likelihood-header">
                    <h3>Plan Your Outreach</h3>
                    <p>Choose whether to message one recruiter or launch a mini campaign.</p>
                </div>

                <div class="inst-likelihood-comparison">
                    <div class="inst-likelihood-option inst-likelihood-option--with">
                        <div class="inst-likelihood-score">
                            <span class="inst-score-value inst-score-high">1</span>
                            <span class="inst-score-label">Recruiter at a time</span>
                        </div>
                        <h4>Targeted Message</h4>
                        <ul class="inst-likelihood-benefits">
                            <li>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12" />
                                </svg>
                                Personalized note tailored to this recruiter
                            </li>
                            <li>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12" />
                                </svg>
                                Ideal when you already have a primary contact
                            </li>
                            <li>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12" />
                                </svg>
                                Follow-up tracking inside MENA Careers CRM
                            </li>
                        </ul>
                        <button type="button" class="inst-likelihood-btn inst-likelihood-btn--primary" id="instLikelihoodSingleBtn">
                            Send CV to One Recruiter
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="5" y1="12" x2="19" y2="12" />
                                <polyline points="12 5 19 12 12 19" />
                            </svg>
                        </button>
                    </div>

                    <div class="inst-likelihood-option inst-likelihood-option--without">
                        <div class="inst-likelihood-score">
                            <span class="inst-score-value inst-score-high">20+</span>
                            <span class="inst-score-label">Recruiters in a campaign</span>
                        </div>
                        <h4>Parallel Outreach</h4>
                        <ul class="inst-likelihood-benefits">
                            <li>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12" />
                                </svg>
                                Select a squad of recommended recruiters at once
                            </li>
                            <li>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12" />
                                </svg>
                                MENA Careers drafts LinkedIn-style intros for each recruiter
                            </li>
                            <li>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12" />
                                </svg>
                                Great for uncovering hidden roles quickly
                            </li>
                        </ul>
                        <button type="button" class="inst-likelihood-btn inst-likelihood-btn--secondary" id="instLikelihoodMultiBtn">
                            <?php echo esc_html($inst_outreach_cta); ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="inst-details-modal" id="instDetailsMissingModal" style="display: none;">
            <div class="inst-details-overlay"></div>
            <div class="inst-details-content">
                <button type="button" class="inst-details-close" id="instDetailsClose">&times;</button>
                <div class="inst-details-header">
                    <h3>Almost ready to message the recruiter</h3>
                    <p>Share the basics below so recruiters know who's reaching out.</p>
                </div>
                <ul class="inst-details-list" id="instMissingFieldsList"></ul>
                <div class="inst-details-footer">
                    <button type="button" class="inst-details-btn" id="instDetailsUpdateBtn">
                        Update my details
                    </button>
                </div>
            </div>
        </div>

        <div class="inst-expert-modal" id="instExpertModal" style="display: none;">
            <div class="inst-expert-overlay"></div>
            <div class="inst-expert-content">
                <button type="button" class="inst-expert-close" id="instExpertClose">&times;</button>
                <div class="inst-expert-header">
                    <span class="inst-expert-badge">Members</span>
                    <h3>Speak to a MENA Careers Career Expert</h3>
                    <p>Premium members partner with a dedicated coach who reviews materials, lines up recruiters, and provides accountability.</p>
                </div>
                <ul class="inst-expert-benefits">
                    <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20 6 9 17 4 12" />
                        </svg>
                        1:1 coaching session tailored to your target roles
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20 6 9 17 4 12" />
                        </svg>
                        Direct access to MENA Careers recruiters and search partners
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20 6 9 17 4 12" />
                        </svg>
                        Tailored CVs, cover letters, and outreach scripts reviewed by experts
                    </li>
                </ul>
                <div class="inst-expert-footer">
                    <button type="button" class="inst-expert-cta" id="instExpertJoinBtn">Unlock coaching with membership</button>
                </div>
            </div>
        </div>

        <div class="inst-ready-modal" id="instReadyModal" style="display: none;">
            <div class="inst-ready-overlay"></div>
            <div class="inst-ready-content">
                <button type="button" class="inst-ready-close" id="instReadyClose">&times;</button>
                <div class="inst-ready-header">
                    <h3>Ready to Join MENA Careers?</h3>
                    <p>Premium members get concierge coaching, recruiter access, and tailored materials for every outreach.</p>
                </div>
                <ul class="inst-ready-benefits">
                    <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20 6 9 17 4 12" />
                        </svg>
                        Dedicated coach who reviews your pack before you hit send
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20 6 9 17 4 12" />
                        </svg>
                        Warm introductions to MENA Careers recruiters and hiring partners
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20 6 9 17 4 12" />
                        </svg>
                        MENA Careers-generated CVs, cover letters, and LinkedIn outreach messages
                    </li>
                </ul>
                <div class="inst-ready-footer">
                    <button type="button" class="inst-ready-cta" id="instReadyJoinBtn">Join MENA Careers</button>
                </div>
            </div>
        </div>

        <!-- ========================================
             DOWNLOAD PACK MODAL (for non-members)
             ======================================== -->
        <div class="inst-pack-modal" id="instPackModal" style="display: none;">
            <div class="inst-pack-modal-overlay"></div>
            <div class="inst-pack-modal-content">
                <button type="button" class="inst-pack-modal-close" id="instPackModalClose">&times;</button>

                <!-- Modal Header -->
                <div class="inst-pack-modal-header">
                    <div class="inst-pack-modal-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                            <polyline points="3.27 6.96 12 12.01 20.73 6.96" />
                            <line x1="12" y1="22.08" x2="12" y2="12" />
                        </svg>
                    </div>
                    <h3>Your Application Pack is Ready</h3>
                    <p>Unlock your personalized materials for <strong id="packModalJobTitle"><?php echo esc_html($job_title); ?></strong></p>
                </div>

                <!-- Pack Items Summary -->
                <div class="inst-pack-modal-items" id="instPackModalItems">
                    <!-- Populated by JavaScript -->
                </div>

                <!-- Benefits -->
                <div class="inst-pack-modal-benefits">
                    <div class="inst-pack-benefit">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20 6 9 17 4 12" />
                        </svg>
                        <span>ATS-optimized to pass screening systems</span>
                    </div>
                    <div class="inst-pack-benefit">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20 6 9 17 4 12" />
                        </svg>
                        <span>Tailored to match job requirements</span>
                    </div>
                    <div class="inst-pack-benefit">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20 6 9 17 4 12" />
                        </svg>
                        <span>Ready to send in minutes, not hours</span>
                    </div>
                </div>

                <!-- Social Proof -->
                <div class="inst-pack-modal-social">
                    <div class="inst-pack-social-avatars">
                        <span class="inst-pack-avatar">J</span>
                        <span class="inst-pack-avatar">S</span>
                        <span class="inst-pack-avatar">M</span>
                        <span class="inst-pack-avatar">+</span>
                    </div>
                    <p>Join <strong>2,000+</strong> candidates who landed interviews with MENA Careers</p>
                </div>

                <!-- CTA -->
                <div class="inst-pack-modal-actions">
                    <button type="button" class="inst-pack-modal-cta" id="instUnlockPackBtn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                        </svg>
                        Unlock Your Pack
                    </button>
                    <p class="inst-pack-modal-note">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                        </svg>
                        Exclusive to MENA Careers members
                    </p>
                </div>

            </div>
        </div>

        <!-- ========================================
             CRM EXPLAINER MODAL (Add to List)
             ======================================== -->
        <div class="inst-crm-modal" id="instCrmModal" style="display: none;">
            <div class="inst-crm-modal-overlay"></div>
            <div class="inst-crm-modal-content">
                <button type="button" class="inst-crm-modal-close" id="instCrmModalClose">&times;</button>

                <!-- Modal Header -->
                <div class="inst-crm-modal-header">
                    <div class="inst-crm-modal-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                            <circle cx="9" cy="7" r="4" />
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                        </svg>
                    </div>
                    <h3>Save to Your Recruiter CRM</h3>
                    <p>We'll save <strong id="crmModalRecruiterName"><?php echo esc_html($recruiter_name ?: 'this recruiter'); ?></strong> to your personal outreach list</p>
                </div>

                <!-- CRM Features -->
                <div class="inst-crm-modal-features">
                    <div class="inst-crm-feature">
                        <div class="inst-crm-feature-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z" />
                            </svg>
                        </div>
                        <div class="inst-crm-feature-text">
                            <h4>Save Recruiters</h4>
                            <p>Build your personal database of recruiters you want to connect with</p>
                        </div>
                    </div>
                    <div class="inst-crm-feature">
                        <div class="inst-crm-feature-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                                <polyline points="22,6 12,13 2,6" />
                            </svg>
                        </div>
                        <div class="inst-crm-feature-text">
                            <h4>Smart Outreach</h4>
                            <p>Message multiple recruiters with personalized, AI-crafted emails</p>
                        </div>
                    </div>
                    <div class="inst-crm-feature">
                        <div class="inst-crm-feature-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
                            </svg>
                        </div>
                        <div class="inst-crm-feature-text">
                            <h4>Track Progress</h4>
                            <p>Monitor responses, follow-ups, and your entire job search pipeline</p>
                        </div>
                    </div>
                </div>

                <!-- Social Proof -->
                <div class="inst-crm-modal-social">
                    <div class="inst-crm-social-stat">
                        <span class="inst-crm-stat-number">3.2x</span>
                        <span class="inst-crm-stat-label">more responses with organized outreach</span>
                    </div>
                </div>

                <!-- CTA -->
                <div class="inst-crm-modal-actions">
                    <button type="button" class="inst-crm-modal-cta" id="instSaveRecruiterBtn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z" />
                        </svg>
                        Save Recruiter & Join MENA Careers
                    </button>
                    <p class="inst-crm-modal-note">
                        Your saved recruiters will be waiting for you
                    </p>
                </div>

            </div>
        </div>

        <div class="inst-crm-modal" id="instPipelineModal" style="display: none;">
            <div class="inst-crm-modal-overlay"></div>
            <div class="inst-crm-modal-content">
                <button type="button" class="inst-crm-modal-close" id="instPipelineClose">&times;</button>
                <div class="inst-crm-modal-header">
                    <div class="inst-crm-modal-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
                        </svg>
                    </div>
                    <h3>Track Roles in MENA Careers Pipeline</h3>
                    <p>Every recruiter outreach, follow-up, and offer in one visual board.</p>
                </div>
                <div class="inst-crm-modal-features">
                    <div class="inst-crm-feature">
                        <div class="inst-crm-feature-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
                                <line x1="3" y1="9" x2="21" y2="9" />
                                <line x1="9" y1="21" x2="9" y2="9" />
                            </svg>
                        </div>
                        <div class="inst-crm-feature-text">
                            <h4>Kanban Pipeline</h4>
                            <p>Drag opportunities from Interested to Offer and spot gaps instantly.</p>
                        </div>
                    </div>
                    <div class="inst-crm-feature">
                        <div class="inst-crm-feature-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 4h16v4H4z" />
                                <path d="M2 8h20v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2z" />
                            </svg>
                        </div>
                        <div class="inst-crm-feature-text">
                            <h4>Follow-up Reminders</h4>
                            <p>Automatic tasks so no recruiter conversation goes cold.</p>
                        </div>
                    </div>
                    <div class="inst-crm-feature">
                        <div class="inst-crm-feature-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 20l9-5-9-5-9 5 9 5z" />
                                <path d="M12 12l9-5-9-5-9 5 9 5z" />
                            </svg>
                        </div>
                        <div class="inst-crm-feature-text">
                            <h4>Response Intelligence</h4>
                            <p>See who replied, who needs nudging, and which roles are heating up.</p>
                        </div>
                    </div>
                </div>
                <div class="inst-crm-modal-actions">
                    <button type="button" class="inst-crm-modal-cta" id="instPipelineJoinBtn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19" />
                            <line x1="5" y1="12" x2="19" y2="12" />
                        </svg>
                        Join MENA Careers to Unlock Pipeline
                    </button>
                </div>
            </div>
        </div>

        <!-- Hidden Data for JS -->
        <script type="application/json" id="inst-job-data">
            <?php echo json_encode([
                'post_id' => $post_id,
                'job_title' => $job_title,
                'company_name' => $company_name,
                'recruiter_email' => $recruiter_email,
                'recruiter_name' => $recruiter_name,
                'jd_text' => $jd_full_text,
                'source' => 'crm',
                'is_premium' => $is_premium,
                'is_logged_in' => $is_logged_in,
                'membership_url' => 'https://joinsenna.com/memberships/',
            ]); ?>
        </script>

    </div>
<?php
}

/**
 * Build the full JD text for analysis (CRM version)
 */
/**
 * Enqueue assets for CRM post article (Express Interest flow)
 */
function sffc_enqueue_crm_article_assets($post_id, $jd_text, $recruiter_email, $job_title, $company_name, $recruiter_name = '', $recruiter_id = 0)
{
    // Base institutional article CSS
    wp_enqueue_style('inst-article', SFFC_PLUGIN_URL . 'assets/css/institutional-article.css', [], SFFC_VERSION);

    // Gap analyzer core styles
    $gap_analyzer_css_path = defined('SFFC_PLUGIN_DIR') ? SFFC_PLUGIN_DIR . 'assets/css/gap-analyzer.css' : '';
    $gap_analyzer_js_path = defined('SFFC_PLUGIN_DIR') ? SFFC_PLUGIN_DIR . 'assets/js/gap-analyzer.js' : '';
    $gap_analyzer_css_version = $gap_analyzer_css_path && file_exists($gap_analyzer_css_path) ? (string) filemtime($gap_analyzer_css_path) : SFFC_VERSION;
    $gap_analyzer_js_version = $gap_analyzer_js_path && file_exists($gap_analyzer_js_path) ? (string) filemtime($gap_analyzer_js_path) : SFFC_VERSION;

    wp_enqueue_style('sffc-gap-analyzer', SFFC_PLUGIN_URL . 'assets/css/gap-analyzer.css', ['inst-article'], $gap_analyzer_css_version);

    // Recruiter post article CSS (Express Interest styles)
    wp_enqueue_style('sffc-recruiter-post-article', SFFC_PLUGIN_URL . 'assets/css/recruiter-post-article.css', ['inst-article'], SFFC_VERSION);

    // Base institutional article JS
    wp_enqueue_script('inst-article', SFFC_PLUGIN_URL . 'assets/js/institutional-article.js', [], SFFC_VERSION, true);

    // Gap analyzer core logic
    wp_enqueue_script('sffc-gap-analyzer', SFFC_PLUGIN_URL . 'assets/js/gap-analyzer.js', ['jquery', 'inst-article'], $gap_analyzer_js_version, true);

    // Recruiter post article JS (Express Interest controller)
    wp_enqueue_script('sffc-recruiter-post-article', SFFC_PLUGIN_URL . 'assets/js/recruiter-post-article.js', ['jquery', 'inst-article'], SFFC_VERSION, true);

    // Check premium access via MemberPress integration
    $is_premium = false;
    $upgrade_url = 'https://joinsenna.com/memberships/';
    $user_id = get_current_user_id();
    $is_logged_in = is_user_logged_in();

    if ($user_id && class_exists('SFFC_MemberPress_Integration')) {
        $mepr = SFFC_MemberPress_Integration::get_instance();
        $is_premium = $mepr->has_premium_access($user_id);
    }

    $gap_analyzer_nonce = wp_create_nonce('sffc_gap_analyzer_nonce');
    $express_interest_nonce = wp_create_nonce('sffc_express_interest_nonce');
    $crm_nonce = wp_create_nonce('sffc_crm_nonce');
    $login_url = wp_login_url(get_permalink($post_id));
    $pipeline_url = add_query_arg('tab', 'pipeline', home_url('/senna-recruiter-outreach/'));

    wp_localize_script('sffc-gap-analyzer', 'sffc_gap_analyzer', [
        'ajax_url'     => admin_url('admin-ajax.php'),
        'nonce'        => $gap_analyzer_nonce,
        'is_premium'   => $is_premium,
        'is_logged_in' => $is_logged_in,
        'upgrade_url'  => $upgrade_url,
        'login_url'    => $login_url,
    ]);

    // Localize script with job data
    wp_localize_script('sffc-recruiter-post-article', 'sffc_recruiter_post', [
        'ajax_url'        => admin_url('admin-ajax.php'),
        'nonce'           => $express_interest_nonce,
        'analysis_nonce'  => $gap_analyzer_nonce,
        'post_id'         => $post_id,
        'jd_text'         => $jd_text,
        'recruiter_email' => $recruiter_email,
        'recruiter_name'  => $recruiter_name,
        'job_title'       => $job_title,
        'company_name'    => $company_name,
        'is_premium'      => $is_premium,
        'is_logged_in'    => $is_logged_in,
        'upgrade_url'     => $upgrade_url,
        'membership_url'  => 'https://joinsenna.com/memberships/',
        'login_url'       => $login_url,
        'crm_nonce'       => $crm_nonce,
        'crm_post_id'     => $post_id,
        'recruiter_id'    => (int) $recruiter_id,
        'pipeline_url'    => $pipeline_url,
        'crm_url'         => home_url('/terminal/'),
        'source'          => 'crm',
    ]);
}
