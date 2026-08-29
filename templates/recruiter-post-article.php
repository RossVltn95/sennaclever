<?php

/**
 * Recruiter Post Article Template
 *
 * Renders a recruiter post with Application Pack flow.
 * Features: Job Details view (default), Application Pack tab with CV upload + products,
 * and Express Interest tab with upsell products.
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
     * Format a recruiter name as "First L." to maintain privacy in public views.
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
 * Render the recruiter post article
 *
 * @param array $args {
 *     @type int    $post_id        The recruiter post ID
 *     @type bool   $show_sidebar   Whether to show the sidebar
 *     @type bool   $user_has_access Whether user is logged in
 * }
 */
function sffc_render_recruiter_post_article($args)
{
    $post_id = $args['post_id'] ?? 0;
    $show_sidebar = $args['show_sidebar'] ?? true;
    $user_has_access = $args['user_has_access'] ?? false;

    if (!$post_id) {
        echo '<!-- recruiter post article: no post id -->';
        return;
    }

    $post = get_post($post_id);
    if (!$post) {
        echo '<!-- recruiter post article: post not found -->';
        return;
    }

    global $wpdb;

    // Get post metadata
    $recruiter_name = get_post_meta($post_id, '_recruiter_name', true);
    $recruiter_title = get_post_meta($post_id, '_recruiter_title', true);
    $recruiter_company = get_post_meta($post_id, '_recruiter_company', true);
    $recruiter_email = get_post_meta($post_id, '_recruiter_email', true);
    $recruiter_linkedin = get_post_meta($post_id, '_recruiter_linkedin', true);
    $recruiter_image_id = get_post_meta($post_id, '_recruiter_image_id', true);
    $recruiter_image_url = $recruiter_image_id ? wp_get_attachment_image_url($recruiter_image_id, 'thumbnail') : '';
    if (!$recruiter_image_url) {
        $recruiter_image_url = get_post_meta($post_id, '_recruiter_image_url', true);
    }

    $crm_post_id = (int) get_post_meta($post_id, '_crm_post_id', true);
    if (!$crm_post_id && isset($wpdb)) {
        $crm_post_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}sffc_crm_posts WHERE wp_post_id = %d LIMIT 1",
            $post_id
        ));
    }

    if (!function_exists('inst_find_crm_recruiter_id')) {
        function inst_find_crm_recruiter_id($email = '', $name = '', $company = '')
        {
            global $wpdb;

            if (!$wpdb) {
                return 0;
            }

            $table = $wpdb->prefix . 'sffc_crm_recruiters';

            if ($email) {
                $id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$table} WHERE email = %s LIMIT 1",
                    $email
                ));
                if ($id) {
                    return (int) $id;
                }
            }

            if ($name) {
                if ($company) {
                    $id = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$table} WHERE name = %s AND firm = %s LIMIT 1",
                        $name,
                        $company
                    ));
                    if ($id) {
                        return (int) $id;
                    }
                }

                $id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$table} WHERE name = %s LIMIT 1",
                    $name
                ));
                if ($id) {
                    return (int) $id;
                }
            }

            return 0;
        }
    }

    $crm_recruiter_id = (int) get_post_meta($post_id, '_crm_recruiter_id', true);
    if (!$crm_recruiter_id) {
        $crm_recruiter_id = inst_find_crm_recruiter_id($recruiter_email, $recruiter_name, $recruiter_company);
    }

    $job_title = get_post_meta($post_id, '_job_title', true) ?: $post->post_title;
    $company_name = get_post_meta($post_id, '_company_name', true);
    $job_location = get_post_meta($post_id, '_job_location', true);

    $salary_min = get_post_meta($post_id, '_salary_min', true);
    $salary_max = get_post_meta($post_id, '_salary_max', true);
    $salary_currency = get_post_meta($post_id, '_salary_currency', true) ?: 'AED';
    $experience_years = get_post_meta($post_id, '_experience_years', true);
    $login_url = 'https://joinsenna.com/login-auth/';
    $key_requirements = get_post_meta($post_id, '_key_requirements', true);
    $ideal_background = get_post_meta($post_id, '_ideal_background', true);

    $is_featured = get_post_meta($post_id, '_is_featured', true);
    $is_urgent = get_post_meta($post_id, '_is_urgent', true);

    // Get taxonomies
    $post_types = wp_get_post_terms($post_id, 'recruiter_post_type', ['fields' => 'names']);
    $industries = wp_get_post_terms($post_id, 'recruiter_post_industry', ['fields' => 'names']);
    $locations = wp_get_post_terms($post_id, 'recruiter_post_location', ['fields' => 'names']);

    $post_type_label = !empty($post_types) ? $post_types[0] : 'Active Role';
    $industry_label = !empty($industries) ? implode(', ', $industries) : '';
    $location_label = !empty($locations) ? $locations[0] : $job_location;

    // Build the full JD text for analysis
    $jd_content = $post->post_content;
    $jd_full_text = sffc_build_jd_text_for_analysis($post_id, $post);

    // Format salary
    $salary_display = '';
    if ($salary_min && $salary_max) {
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
    sffc_enqueue_recruiter_article_assets(
        $post_id,
        $jd_full_text,
        $recruiter_email,
        $job_title,
        $company_name,
        [
            'crm_post_id'   => $crm_post_id,
            'recruiter_id'  => $crm_recruiter_id,
        ]
    );
?>
    <div class="inst-terminal inst-express-interest-flow" data-component="recruiter-post-analyzer" data-post-id="<?php echo esc_attr($post_id); ?>" data-is-premium="<?php echo $is_premium ? 'true' : 'false'; ?>" data-is-logged-in="<?php echo $is_logged_in ? 'true' : 'false'; ?>">

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
                    <div class="preloader-percentage" id="preloader-percentage">0%</div>
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

            <div class="inst-mobile-panel-toggle" id="instMobilePanelToggle" aria-label="Toggle between recruiter list and role details">
                <div class="inst-mobile-toggle-group">
                    <button type="button" class="inst-mobile-panel-btn is-active" data-panel="role" aria-pressed="true" aria-controls="instRoleDetailColumn">
                        <svg class="inst-mobile-nav-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7" />
                            <rect x="14" y="3" width="7" height="7" />
                            <rect x="14" y="14" width="7" height="7" />
                            <rect x="3" y="14" width="7" height="7" />
                        </svg>
                        <span class="inst-mobile-nav-label">Role</span>
                    </button>
                    <button type="button" class="inst-mobile-panel-btn" data-panel="recruiters" aria-pressed="false" aria-controls="instRecruiterListColumn">
                        <svg class="inst-mobile-nav-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                            <circle cx="9" cy="7" r="4" />
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                        </svg>
                        <span class="inst-mobile-nav-label">Recruiters</span>
                    </button>
                </div>
                <a href="https://joinsenna.com/memberships/" target="_blank" rel="noopener" class="inst-mobile-join-btn">Join</a>
            </div>

            <div class="inst-mobile-role-summary" id="instMobileRoleSummary">
                <h2 id="instMobileRoleTitle"><?php echo esc_html($job_title); ?></h2>
                <p id="instMobileRoleSubtitle"><?php echo esc_html($company_name ?: 'Confidential'); ?><?php if ($location_label) : ?> • <?php echo esc_html($location_label); ?><?php endif; ?></p>
            </div>

            <div class="inst-linkedin-layout" id="instLinkedinLayout">
                <div class="inst-linkedin-column inst-linkedin-column--left" id="instRecruiterListColumn">

                    <!-- ========================================
                         RECRUITER SHORTLIST (LinkedIn-style list)
                         ======================================== -->
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
                                $job_location = get_post_meta($post_id, '_job_location', true);
                                $job_description = get_post_meta($post_id, '_job_description', true);
                                if (!$job_description) {
                                    $job_description = get_post_field('post_content', $post_id);
                                }

                                $inst_recruiter_role_cache[$cache_key] = [
                                    'title' => $job_title,
                                    'company' => $company_name,
                                    'location' => $job_location,
                                    'description' => wp_strip_all_tags($job_description),
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

                    $primary_outreach_card = null;
                    if ($recruiter_name || $recruiter_company || $recruiter_email) {
                        $primary_outreach_card = [
                            'id'        => $crm_recruiter_id ?: 0,
                            'name'      => $display_recruiter_name ?: ($public_recruiter_label ?: 'Recruiter'),
                            'company'   => $display_recruiter_meta_company ?: ($company_name ?: ''),
                            'email'     => $recruiter_email,
                            'title'     => $job_title ?: ($display_recruiter_meta_title ?: $inst_outreach_role),
                            'location'  => $location_label ?: $job_location,
                            'description' => $jd_full_text,
                            'photo'     => $recruiter_image_url,
                            'initial'   => substr(($display_recruiter_name ?: $public_recruiter_label ?: 'R'), 0, 1)
                        ];

                        if (!empty($inst_featured_recruiters)) {
                            $primary_id = (int) $primary_outreach_card['id'];
                            $primary_email = strtolower($primary_outreach_card['email'] ?? '');
                            $inst_featured_recruiters = array_values(array_filter($inst_featured_recruiters, function ($rec) use ($primary_id, $primary_email) {
                                $rec_id = isset($rec['id']) ? (int) $rec['id'] : 0;
                                $rec_email = isset($rec['email']) ? strtolower($rec['email']) : '';
                                if ($primary_id && $rec_id && $rec_id === $primary_id) {
                                    return false;
                                }
                                if ($primary_email && $rec_email && $rec_email === $primary_email) {
                                    return false;
                                }
                                return true;
                            }));
                        }
                    }

                    $inst_outreach_role = $job_title ? wp_strip_all_tags($job_title) : 'This Role';
                    $inst_outreach_cta = sprintf('Reach More Recruiters Hiring for %s', $inst_outreach_role);
                    ?>

                    <button type="button" class="inst-join-senna-btn" onclick="window.open('https://joinsenna.com/memberships/','_blank');">Join MENA Careers</button>

                    <div class="inst-express-section inst-recruiter-outreach" id="instRecruiterOutreachSection">
                        <h3><?php echo esc_html($inst_outreach_cta); ?></h3>
                        <p class="inst-section-description">Select 3-6 recruiters and MENA Careers will prep a mini LinkedIn campaign.</p>

                        <div class="inst-outreach-grid" id="instRecruiterOutreachGrid">
                            <?php if ($primary_outreach_card) :
                                $primary_initial = strtoupper(substr($primary_outreach_card['initial'] ?: 'R', 0, 1));
                                $primary_name = $primary_outreach_card['name'];
                                $primary_company = $primary_outreach_card['company'];
                                $primary_photo = $primary_outreach_card['photo'];
                                $primary_role_title = $primary_outreach_card['title'];
                                $primary_role_company = $company_name ?: $primary_company;
                                $primary_location = $primary_outreach_card['location'];
                            ?>
                                <label class="inst-outreach-card inst-outreach-card--primary"
                                    data-recruiter-id="<?php echo esc_attr($primary_outreach_card['id']); ?>"
                                    data-recruiter-name="<?php echo esc_attr($primary_name); ?>"
                                    data-recruiter-company="<?php echo esc_attr($primary_company); ?>"
                                    data-recruiter-email="<?php echo esc_attr($primary_outreach_card['email']); ?>"
                                    data-role-title="<?php echo esc_attr($primary_role_title); ?>"
                                    data-role-company="<?php echo esc_attr($primary_role_company); ?>"
                                    data-role-location="<?php echo esc_attr($primary_location); ?>"
                                    data-role-description="<?php echo esc_attr(wp_strip_all_tags($primary_outreach_card['description'])); ?>"
                                    data-recruiter-initial="<?php echo esc_attr($primary_initial); ?>"
                                    data-recruiter-photo="<?php echo esc_url($primary_photo); ?>">
                                    <input type="checkbox"
                                        class="inst-outreach-checkbox"
                                        value="<?php echo esc_attr($primary_outreach_card['id']); ?>">
                                    <div class="inst-outreach-card-inner">
                                        <span class="inst-outreach-check-icon" aria-hidden="true"></span>
                                        <div class="inst-outreach-avatar inst-recruiter-avatar<?php echo $primary_photo ? ' inst-recruiter-avatar--has-image' : ''; ?>" data-avatar-initial="<?php echo esc_attr($primary_initial); ?>">
                                            <?php if ($primary_photo) : ?>
                                                <img src="<?php echo esc_url($primary_photo); ?>" alt="<?php echo esc_attr($primary_name); ?>">
                                            <?php else : ?>
                                                <span><?php echo esc_html($primary_initial); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="inst-outreach-info">
                                            <span class="inst-outreach-name"><?php echo esc_html($primary_name); ?></span>
                                            <?php if ($primary_company) : ?>
                                                <span class="inst-outreach-firm"><?php echo esc_html($primary_company); ?></span>
                                            <?php endif; ?>
                                            <span class="inst-outreach-role">
                                                Hiring for: <?php echo esc_html($primary_role_title ?: $inst_outreach_role); ?>
                                                <?php if (!empty($primary_role_company)) : ?>
                                                    <span class="inst-outreach-role-company">@ <?php echo esc_html($primary_role_company); ?></span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </div>
                                </label>
                            <?php endif; ?>

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
                                    $role_location = $role_context['location'] ?? '';
                                    $role_description = $role_context['description'] ?? $jd_full_text;
                                    $initial = strtoupper(substr(($featured['name'] ?: 'R'), 0, 1));
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
                                    <label class="inst-outreach-card"
                                        data-recruiter-id="<?php echo esc_attr($featured['id']); ?>"
                                        data-recruiter-name="<?php echo esc_attr($display_name); ?>"
                                        data-recruiter-company="<?php echo esc_attr($display_firm); ?>"
                                        data-recruiter-email="<?php echo esc_attr($featured['email']); ?>"
                                        data-role-title="<?php echo esc_attr($primary_focus); ?>"
                                        data-role-company="<?php echo esc_attr($role_company); ?>"
                                        data-role-location="<?php echo esc_attr($role_location); ?>"
                                        data-role-description="<?php echo esc_attr(wp_strip_all_tags($role_description)); ?>"
                                        data-recruiter-initial="<?php echo esc_attr($initial); ?>"
                                        data-recruiter-photo="<?php echo esc_url($featured['photo_url']); ?>">
                                        <input type="checkbox"
                                            class="inst-outreach-checkbox"
                                            value="<?php echo esc_attr($featured['id']); ?>">
                                        <div class="inst-outreach-card-inner">
                                            <span class="inst-outreach-check-icon" aria-hidden="true"></span>
                                            <div class="inst-outreach-avatar inst-recruiter-avatar<?php echo $has_photo ? ' inst-recruiter-avatar--has-image' : ''; ?>" data-avatar-initial="<?php echo esc_attr($initial); ?>">
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

                        <?php if (!$is_logged_in) : ?>
                            <div class="inst-outreach-login-cta">
                                <a href="<?php echo esc_url($login_url); ?>" class="inst-login-reveal-btn inst-login-reveal-btn--block">Log in to reveal recruiter details</a>
                            </div>
                        <?php endif; ?>

                        <div class="inst-outreach-actions">
                            <div class="inst-outreach-summary">
                                <span id="instOutreachCount">0</span>/6 selected — choose at least 3 recruiters.
                            </div>
                            <div class="inst-outreach-buttons">
                                <button type="button" class="inst-outreach-btn inst-outreach-btn--primary" id="instBulkReachOutBtn" disabled>Send CV</button>
                                <button type="button" class="inst-outreach-btn inst-outreach-btn--secondary" id="instBulkAddBtn" disabled>Add to List</button>
                                <?php if (!$is_logged_in) : ?>
                                    <a href="<?php echo esc_url($login_url); ?>" class="inst-login-reveal-btn">Log in to unlock outreach tools</a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="inst-outreach-floating-actions" id="instOutreachFloatingActions" aria-live="polite">
                            <button type="button" class="inst-floating-btn inst-floating-btn--primary" id="instFloatingMessageBtn" disabled>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v10c0 1.1-.9 2-2 2H7l-5 4V6c0-1.1.9-2 2-2z" />
                                </svg>
                                <span>Message Selected</span>
                            </button>
                            <button type="button" class="inst-floating-btn inst-floating-btn--secondary" id="instFloatingIntroduceBtn">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <polyline points="20 6 9 17 4 12" />
                                </svg>
                                <span>Introduce Me</span>
                            </button>
                        </div>
                    </div>


                </div>
                <div class="inst-linkedin-column inst-linkedin-column--right" id="instRoleDetailColumn">

                    <!-- Header -->
                    <div class="inst-analysis-header" id="instRoleSummary">
                        <div class="inst-analysis-header-left">
                            <div class="inst-analysis-pills">
                                <span class="inst-analysis-pill is-live">Live recruiter brief</span>
                                <?php if ($is_urgent) : ?>
                                    <span class="inst-analysis-pill is-urgent">Urgent search</span>
                                <?php endif; ?>
                                <?php if ($is_featured) : ?>
                                    <span class="inst-analysis-pill is-featured">Featured</span>
                                <?php endif; ?>
                            </div>
                            <div class="inst-analysis-header-info">
                                <div class="inst-analysis-header-text">
                                    <h1 class="inst-analysis-header-title" id="instHeaderTitle"><?php echo esc_html($job_title); ?></h1>
                                    <p class="inst-analysis-header-subtitle" id="instHeaderSubtitle"><?php echo esc_html($company_name ?: 'Confidential'); ?><?php if ($location_label) : ?> &bull; <?php echo esc_html($location_label); ?><?php endif; ?></p>
                                </div>
                                <p class="inst-analysis-description">Build the recruiter-ready kit for this role: benchmark the JD, tailor your CV bullets, and send a polished outreach note.</p>
                            </div>
                            <ul class="inst-analysis-meta">
                                <?php if ($location_label) : ?>
                                    <li>
                                        <span>Location</span>
                                        <strong id="instJobMetaLocation"><?php echo esc_html($location_label); ?></strong>
                                    </li>
                                <?php endif; ?>
                                <?php if ($experience_years) : ?>
                                    <li>
                                        <span>Experience</span>
                                        <strong><?php echo esc_html($experience_years); ?></strong>
                                    </li>
                                <?php endif; ?>
                                <?php if ($salary_display) : ?>
                                    <li>
                                        <span>Compensation</span>
                                        <strong><?php echo esc_html($salary_display); ?></strong>
                                    </li>
                                <?php endif; ?>
                                <?php
                                $analysis_recruiter_label = $condensed_recruiter_name ?: $display_recruiter_name ?: '';
                                $analysis_recruiter_company = $recruiter_company ?: $company_name;
                                ?>
                                <?php if ($analysis_recruiter_label || $analysis_recruiter_company) : ?>
                                    <li>
                                        <span>Recruiter</span>
                                        <strong>
                                            <?php echo esc_html($analysis_recruiter_label ?: 'Private'); ?>
                                            <?php if ($analysis_recruiter_company) : ?>
                                                <em>@ <?php echo esc_html($analysis_recruiter_company); ?></em>
                                            <?php endif; ?>
                                        </strong>
                                    </li>
                                <?php endif; ?>
                            </ul>
                            <button type="button"
                                class="inst-expert-btn inst-expert-btn--primary"
                                id="instSpeakExpertBtn">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M5 12h14" />
                                    <polyline points="12 5 19 12 12 19" />
                                    <rect x="3" y="4" width="6" height="16" rx="2" />
                                </svg>
                                <span>Apply</span>
                            </button>
                        </div>
                        <div class="inst-analysis-header-right">
                            <div class="inst-analysis-stat-card">
                                <span class="inst-analysis-stat-label">Build your application kit</span>
                                <span class="inst-analysis-stat-value">Increase Your Chances</span>
                                <p>Members preview the cover letter, CV gap analysis, and LinkedIn outreach copy before pasting their resume.</p>
                                <button type="button" class="inst-stat-card-btn" data-scroll-target="#instKitPreview">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                        <polyline points="7 10 12 15 17 10" />
                                        <line x1="12" y1="3" x2="12" y2="15" />
                                    </svg>
                                    <span>Download application kit</span>
                                </button>
                            </div>
                            <div class="inst-analysis-cta-stack">
                                <?php if ($recruiter_email) : ?>
                                    <button type="button" class="inst-header-message-btn" id="instHeaderMessageBtn" data-navigate="express-interest">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                                            <polyline points="22,6 12,13 2,6" />
                                        </svg>
                                        <span>Message Recruiter</span>
                                    </button>
                                <?php endif; ?>
                                <button type="button"
                                    class="inst-add-pipeline-btn"
                                    id="instAddPipelineBtn"
                                    data-introduce-trigger="primary"
                                    data-recruiter-id="<?php echo esc_attr($crm_recruiter_id); ?>"
                                    data-crm-post-id="<?php echo esc_attr($crm_post_id ?: 0); ?>"
                                    data-recruiter-name="<?php echo esc_attr($condensed_recruiter_name ?: $recruiter_name ?: $public_recruiter_label); ?>"
                                    data-recruiter-company="<?php echo esc_attr($recruiter_company ?: $company_name); ?>"
                                    data-role-title="<?php echo esc_attr($job_title); ?>"
                                    data-company="<?php echo esc_attr($company_name); ?>">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 7l-9 9-4-4" />
                                        <path d="M3 12l3 3 4-4 4 4 7-7" />
                                    </svg>
                                    <span>Introduce Me</span>
                                </button>
                                <p class="inst-analysis-helper">Have MENA Careers send the first note or take the toolkit and message the recruiter yourself.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Tab Views Container -->
                    <div class="inst-tab-views">

                        <!-- ========================================
                     JOB DETAILS TAB (DEFAULT - Visible)
                     ======================================== -->
                        <div class="inst-tab-view is-active" id="inst-job-details-view" role="tabpanel">
                            <div class="inst-job-details-content">

                                <!-- Key Requirements -->
                                <?php if ($key_requirements) : ?>
                                    <div class="inst-key-requirements">
                                        <h4>Key Requirements</h4>
                                        <ul>
                                            <?php
                                            $requirements = array_filter(array_map('trim', explode("\n", $key_requirements)));
                                            foreach ($requirements as $req) :
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
                                $inline_avatar_initial = strtoupper(substr(($recruiter_name ?: $public_recruiter_label ?: 'R'), 0, 1));
                                ?>
                                <?php if ($recruiter_name || $recruiter_company) : ?>
                                    <div class="inst-recruiter-card" data-recruiter-id="<?php echo esc_attr($crm_recruiter_id); ?>" data-recruiter-name="<?php echo esc_attr($recruiter_name); ?>" data-recruiter-company="<?php echo esc_attr($recruiter_company); ?>" data-recruiter-email="<?php echo esc_attr($recruiter_email); ?>">
                                        <div class="inst-recruiter-avatar<?php echo $recruiter_image_url ? ' inst-recruiter-avatar--has-image' : ''; ?>" id="instInlineRecruiterAvatar" data-avatar-initial="<?php echo esc_attr($inline_avatar_initial); ?>">
                                            <?php if ($recruiter_image_url) : ?>
                                                <img src="<?php echo esc_url($recruiter_image_url); ?>" alt="<?php echo esc_attr($display_recruiter_name ?: $recruiter_name); ?>">
                                            <?php else : ?>
                                                <?php echo esc_html($inline_avatar_initial); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="inst-recruiter-info">
                                            <span class="inst-recruiter-label">Posted by</span>
                                            <?php if ($condensed_recruiter_name) : ?>
                                                <span class="inst-recruiter-name" id="instInlineRecruiterName"><?php echo esc_html($condensed_recruiter_name); ?></span>
                                            <?php endif; ?>
                                            <?php if ($meta_title && $meta_company) : ?>
                                                <span class="inst-recruiter-meta" id="instInlineRecruiterMeta"><?php echo esc_html($meta_title . ' at ' . $meta_company); ?></span>
                                            <?php elseif ($meta_company) : ?>
                                                <span class="inst-recruiter-meta" id="instInlineRecruiterMeta"><?php echo esc_html($meta_company); ?></span>
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
                                            <button type="button" class="inst-add-to-list-btn" id="instAddToListBtn" title="Message recruiter">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                                                    <polyline points="22,6 12,13 2,6" />
                                                </svg>
                                                <span>Message Recruiter</span>
                                            </button>
                                            <?php if (!$is_logged_in) : ?>
                                                <a href="<?php echo esc_url($login_url); ?>" class="inst-login-reveal-btn">Log in to reveal details</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Job description removed per new flow -->
                            </div>
                        </div>

                        <!-- ========================================
                     APPLICATION PACK TAB (Formerly Analysis)
                     ======================================== -->
                        <div class="inst-tab-view" id="inst-application-pack-view" role="tabpanel">
                            <div class="inst-application-steps">

                                <div class="inst-panel-view inst-report-view is-active inst-step-flow" id="instApplicationSteps">

                                    <!-- Step 1: Job Description Review -->
                                    <div class="inst-chart-card toolkit-card inst-step-card" data-step="1">
                                        <div class="inst-step-header">
                                            <div class="inst-step-pill">Step 1</div>
                                            <div>
                                                <h3 class="inst-step-title">Review the job description</h3>
                                                <p class="inst-step-subtitle">We pasted the full brief below. Make light edits if the recruiter sent you an updated version.</p>
                                            </div>
                                        </div>
                                        <div class="inst-step-body">
                                            <div class="inst-selected-recruiter-brief" id="instSelectedRecruiterBrief">
                                                Currently viewing the recruiter-provided description.
                                            </div>
                                            <label for="instStepJobDescription" class="screen-reader-text">Job description</label>
                                            <textarea id="instStepJobDescription" class="inst-step-textarea" rows="12" spellcheck="false"><?php echo esc_textarea($jd_full_text); ?></textarea>
                                            <div class="inst-step-meta-row">
                                                <span>Key requirements from this JD will power every recommendation.</span>
                                                <button type="button" class="inst-step-link" data-step-target="2">
                                                    Next: Paste your CV
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M5 12h14M12 5l7 7-7 7" />
                                                    </svg>
                                                </button>
                                            </div>
                                            <div class="inst-step-next">
                                                <button type="button" class="inst-step-action-btn inst-step-action-btn--secondary" data-step-target="2">
                                                    Start Step 2: Paste your CV
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <polyline points="9 18 15 12 9 6" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="inst-kit-preview" id="instKitPreview">
                                        <div class="inst-kit-preview-header">
                                            <span class="inst-step-intro-label">Application Workflow</span>
                                            <h3>Preview the application kit</h3>
                                            <p>See the kinds of materials MENA Careers assembles before you paste anything.</p>
                                        </div>
                                        <div class="inst-kit-preview-grid">
                                            <article class="inst-kit-preview-card">
                                                <h4>Cover Letter</h4>
                                                <p class="inst-kit-preview-snippet">“I'm excited to bring my experience leading <?php echo esc_html($job_title ?: 'this role'); ?> programs<?php if ($company_name) : ?> at <?php echo esc_html($company_name); ?><?php endif; ?> to accelerate this mandate. Recent wins include modernizing reporting cadence, boosting forecast accuracy to 98%, and unlocking $12M in savings.”</p>
                                                <span class="inst-kit-preview-meta">Opening hook + quantified proof points.</span>
                                            </article>
                                            <article class="inst-kit-preview-card">
                                                <h4>CV Gap Analysis</h4>
                                                <ul class="inst-kit-preview-list">
                                                    <li>Highlight the product + finance language missing from your resume.</li>
                                                    <li>Surface leadership signals the recruiter emphasized.</li>
                                                    <li>Flag redundant bullets to replace with impact metrics.</li>
                                                </ul>
                                                <span class="inst-kit-preview-meta">Prioritized edits mapped to the JD.</span>
                                            </article>
                                            <article class="inst-kit-preview-card">
                                                <h4>LinkedIn Networking</h4>
                                                <p class="inst-kit-preview-snippet">“Hi <?php echo esc_html($recruiter_name ?: 'there'); ?> – thanks for sharing the <?php echo esc_html($job_title ?: 'role'); ?> search. I've led similar mandates across North America and can share the playbook that produced a 4x pipeline lift in two quarters.”</p>
                                                <span class="inst-kit-preview-meta">Personalized outreach referencing the JD + recruiter.</span>
                                            </article>
                                        </div>
                                        <button type="button" class="inst-step-action-btn inst-kit-preview-download" id="instKitPreviewDownload">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                                <polyline points="7 10 12 15 17 10" />
                                                <line x1="12" y1="3" x2="12" y2="15" />
                                            </svg>
                                            Download the kit to get started
                                        </button>
                                    </div>

                                    <!-- Step 2: CV Paste -->
                                    <div class="inst-chart-card toolkit-card inst-step-card is-current is-hidden" data-step="2" aria-hidden="true">
                                        <div class="inst-step-header">
                                            <div class="inst-step-pill">Step 2</div>
                                            <div>
                                                <h3 class="inst-step-title">Paste your CV / resume</h3>
                                                <p class="inst-step-subtitle">MENA Careers needs your most recent experience to tailor the materials. We never store the text without your permission.</p>
                                            </div>
                                        </div>
                                        <div class="inst-step-body">
                                            <label for="instCvPasteInput" class="screen-reader-text">Paste CV</label>
                                            <textarea id="instCvPasteInput" class="inst-step-textarea" rows="12" placeholder="Paste your full CV/resume text here&#10;&#10;Tips:&#10;• Include your headline summary and latest roles&#10;• Keep formatting simple for best parsing" spellcheck="false"></textarea>
                                            <div class="inst-step-actions">
                                                <button type="button" class="inst-step-action-btn" id="instRunAnalysisBtn">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
                                                    </svg>
                                                    Analyze &amp; build materials
                                                </button>
                                                <span class="inst-step-helper">MENA Careers compares both documents and assembles your recruiter pack.</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Step 3: Application Kit Output -->
                                    <div class="inst-chart-card toolkit-card inst-step-card" data-step="3">
                                        <div class="inst-step-header">
                                            <div class="inst-step-pill">Step 3</div>
                                            <div>
                                                <h3 class="inst-step-title">Unlock your application kit</h3>
                                                <p class="inst-step-subtitle">MENA Careers renders every material—summary, scorecards, improvements, cover letter, interview prompts, and outreach copy—once the analysis completes.</p>
                                            </div>
                                        </div>

                                        <div class="inst-step-status" id="instStepStatus">
                                            <span class="inst-step-status-dot"></span>
                                            <span id="instStepStatusText">Paste your CV to unlock tailored recommendations.</span>
                                        </div>

                                        <div class="inst-step-summary">
                                            <div class="inst-executive-summary inst-step-executive">
                                                <div class="inst-exec-header">
                                                    <div class="inst-exec-header-text">
                                                        <span class="inst-exec-methodology" id="instStepSummaryRecommendation">Ready to analyze</span>
                                                        <h3 class="inst-exec-title" id="instStepSummaryVerdict">Paste both documents to run the MENA Careers gap analyzer.</h3>
                                                    </div>
                                                    <div class="inst-score-circle">
                                                        <span class="inst-score-value" id="instStepSummaryScore">0%</span>
                                                        <span class="inst-score-label">Match</span>
                                                    </div>
                                                </div>
                                                <p class="inst-exec-thesis" id="instStepSummaryInsight">Your personalized recommendations appear here the moment MENA Careers finishes comparing your CV to the JD.</p>
                                            </div>

                                            <div class="inst-score-cards">
                                                <div class="inst-score-card" data-step-score="skills">
                                                    <span class="inst-score-card-value" data-score-value>0%</span>
                                                    <span class="inst-score-card-label">Skills Match</span>
                                                    <div class="inst-score-card-heat"><span></span></div>
                                                </div>
                                                <div class="inst-score-card" data-step-score="experience">
                                                    <span class="inst-score-card-value" data-score-value>0%</span>
                                                    <span class="inst-score-card-label">Experience</span>
                                                    <div class="inst-score-card-heat"><span></span></div>
                                                </div>
                                                <div class="inst-score-card" data-step-score="keywords">
                                                    <span class="inst-score-card-value" data-score-value>0%</span>
                                                    <span class="inst-score-card-label">Keywords</span>
                                                    <div class="inst-score-card-heat"><span></span></div>
                                                </div>
                                                <div class="inst-score-card" data-step-score="readiness">
                                                    <span class="inst-score-card-value" data-score-value>0%</span>
                                                    <span class="inst-score-card-label">Interview Ready</span>
                                                    <div class="inst-score-card-heat"><span></span></div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="inst-materials-section">
                                            <div class="inst-materials-header">
                                                <h4>Your Materials</h4>
                                                <p>Select the items you want MENA Careers to include.</p>
                                            </div>
                                            <div class="inst-materials-grid">
                                                <label class="inst-material-option">
                                                    <input type="checkbox" class="inst-material-checkbox" value="cover_letter" checked>
                                                    <span class="inst-material-label">Tailored Cover Letter</span>
                                                    <span class="inst-material-description">MENA Careers writes a recruiter-ready letter referencing your CV and this JD.</span>
                                                </label>
                                                <label class="inst-material-option">
                                                    <input type="checkbox" class="inst-material-checkbox" value="recruiter_contacts" checked>
                                                    <span class="inst-material-label">Recruiter Contact List</span>
                                                    <span class="inst-material-description">Curated list of recruiters hiring for this role plus outreach prompts.</span>
                                                </label>
                                                <label class="inst-material-option">
                                                    <input type="checkbox" class="inst-material-checkbox" value="tailored_cv" checked>
                                                    <span class="inst-material-label">Optimized CV (Word &amp; PDF)</span>
                                                    <span class="inst-material-description">Word/PDF versions with quantified bullet rewrites and ATS keywords.</span>
                                                </label>
                                                <label class="inst-material-option">
                                                    <input type="checkbox" class="inst-material-checkbox" value="speak_expert">
                                                    <span class="inst-material-label">Speak to an Expert</span>
                                                    <span class="inst-material-description">Get a MENA Careers coach to review your pack before you reach out.</span>
                                                </label>
                                            </div>
                                            <div class="inst-materials-cta">
                                                <button type="button" class="inst-step-action-btn" id="instGetPackBtn">
                                                    Get Application Pack
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <polyline points="5 12 12 19 19 12" />
                                                        <line x1="12" y1="5" x2="12" y2="19" />
                                                    </svg>
                                                </button>
                                                <p>Members unlock every recruiter-ready asset. <a href="https://joinsenna.com/memberships/" target="_blank" rel="noopener">Join MENA Careers</a>.</p>
                                            </div>
                                        </div>

                                        <div class="inst-gap-panels-grid">
                                            <div class="inst-gap-panel" id="instStepImprovementPanel">
                                                <div class="inst-gap-panel-header">
                                                    <h4>Where to Improve</h4>
                                                    <span class="inst-gap-panel-subtitle">Focused fixes ranked by impact</span>
                                                </div>
                                                <div class="inst-gap-panel-body" id="instStepImprovementList">
                                                    <p class="inst-gap-empty">Run the analysis to see quantified gaps and suggested rewrites.</p>
                                                </div>
                                            </div>
                                            <div class="inst-gap-panel" id="instStepStrengthPanel">
                                                <div class="inst-gap-panel-header">
                                                    <h4>Strengths to Emphasize</h4>
                                                    <span class="inst-gap-panel-subtitle">Use these in your cover letter &amp; interview</span>
                                                </div>
                                                <div class="inst-gap-panel-body" id="instStepStrengthList">
                                                    <p class="inst-gap-empty">We'll surface the most recruiter-relevant wins once MENA Careers completes.</p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="inst-gap-panels-grid inst-gap-panels-grid--wide">
                                            <div class="inst-gap-panel inst-gap-panel--cover">
                                                <div class="inst-gap-panel-header">
                                                    <h4>Cover Letter Draft</h4>
                                                    <span class="inst-gap-panel-subtitle">Structured specifically for <?php echo esc_html($job_title); ?></span>
                                                </div>
                                                <div class="inst-gap-panel-body" id="instStepCoverPanel">
                                                    <div class="inst-step-placeholder" data-step-placeholder="cover">
                                                        <p>Your refined cover letter will appear here once the analysis is ready.</p>
                                                    </div>
                                                    <div class="inst-step-output-content" id="instStepCoverLetter"></div>
                                                    <div class="inst-step-output-actions">
                                                        <button type="button" class="inst-step-action-btn inst-step-action-btn--ghost" data-copy-source="cover-letter">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2" />
                                                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
                                                            </svg>
                                                            Copy Letter
                                                        </button>
                                                        <button type="button" class="inst-step-action-btn inst-step-action-btn--ghost" data-download="cover-word">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                                                <polyline points="14 2 14 8 20 8" />
                                                                <polyline points="9 15 12 18 15 15" />
                                                                <line x1="12" y1="12" x2="12" y2="18" />
                                                            </svg>
                                                            Download Word
                                                        </button>
                                                    </div>
                                                    <div class="inst-membership-note" data-membership-section="cover">
                                                        <h5>Unlock the tailored cover letter</h5>
                                                        <p>Members get a recruiter-ready draft written directly from your CV and this JD.</p>
                                                        <a href="https://joinsenna.com/memberships/" target="_blank" rel="noopener" class="inst-membership-cta">See membership options</a>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="inst-gap-panel inst-gap-panel--interview">
                                                <div class="inst-gap-panel-header">
                                                    <h4>Interview Questions</h4>
                                                    <span class="inst-gap-panel-subtitle">Likely prompts &amp; response angles</span>
                                                </div>
                                                <div class="inst-gap-panel-body" id="instStepInterviewPanel">
                                                    <div class="inst-step-placeholder" data-step-placeholder="interviews">
                                                        <p>Expect at least five priority questions once MENA Careers finishes its pass.</p>
                                                    </div>
                                                    <div class="inst-step-output-content" id="instStepInterviewQuestions"></div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="inst-gap-panels-grid inst-gap-panels-grid--wide">
                                            <div class="inst-gap-panel inst-gap-panel--keywords">
                                                <div class="inst-gap-panel-header">
                                                    <h4>Keywords &amp; ATS Language</h4>
                                                    <span class="inst-gap-panel-subtitle">What the recruiter expects to see</span>
                                                </div>
                                                <div class="inst-gap-panel-body" id="instStepKeywordsPanel">
                                                    <div class="inst-step-placeholder" data-step-placeholder="keywords">
                                                        <p>We'll highlight which phrases to add and which ones already resonate.</p>
                                                    </div>
                                                    <div class="inst-step-output-content" id="instStepKeywordsList"></div>
                                                    <div class="inst-membership-note" data-membership-section="keywords">
                                                        <h5>Keyword Intelligence</h5>
                                                        <p>Pinpoint the exact ATS terms to weave into your CV and cover letter.</p>
                                                        <a href="https://joinsenna.com/memberships/" target="_blank" rel="noopener" class="inst-membership-cta">Unlock with membership</a>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="inst-gap-panel inst-gap-panel--linkedin">
                                                <div class="inst-gap-panel-header">
                                                    <h4>LinkedIn Outreach</h4>
                                                    <span class="inst-gap-panel-subtitle" id="instStepLinkedinMeta">Draft tailored to <?php echo esc_html($recruiter_name ?: 'the recruiter'); ?></span>
                                                    <button type="button" class="inst-step-copy-btn" data-copy-source="linkedin" aria-label="Copy LinkedIn message">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2" />
                                                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
                                                        </svg>
                                                        Copy
                                                    </button>
                                                </div>
                                                <div class="inst-gap-panel-body" id="instStepLinkedinPanel">
                                                    <div class="inst-step-placeholder" data-step-placeholder="linkedin">
                                                        <p>This note references your actual experience and the recruiter's mandate.</p>
                                                    </div>
                                                    <div class="inst-step-output-content" id="instStepLinkedinMessage"></div>
                                                    <div class="inst-membership-note" data-membership-section="linkedin">
                                                        <h5>Instant LinkedIn outreach</h5>
                                                        <p>Send the perfect note in seconds—crafted from your CV + the role’s requirements.</p>
                                                        <a href="https://joinsenna.com/memberships/" target="_blank" rel="noopener" class="inst-membership-cta">Unlock recruiter outreach</a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="inst-step-actions inst-step-actions--final">
                                            <button type="button" class="inst-step-action-btn" id="instDownloadMaterialsBtn" disabled>
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                                    <polyline points="7 10 12 15 17 10" />
                                                    <line x1="12" y1="3" x2="12" y2="15" />
                                                </svg>
                                                Download materials
                                            </button>
                                            <button type="button" class="inst-step-action-btn inst-step-action-btn--secondary" id="instApplyWithoutBtn" data-navigate="express-interest">
                                                Skip &amp; continue to apply
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- ========================================
                     EXPRESS INTEREST TAB
                     ======================================== -->
                        <div class="inst-tab-view" id="inst-express-interest-view" role="tabpanel">

                            <div class="inst-express-form">

                                <!-- Form Header -->
                                <div class="inst-express-header">
                                    <h2>Express Your Interest</h2>
                                    <p>Send a personalized message to the recruiter</p>
                                </div>

                                <div class="inst-introduce-confirmation" id="instIntroduceConfirmation" aria-live="polite" aria-hidden="true">
                                    <div class="inst-introduce-confirmation-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="20 6 9 17 4 12" />
                                        </svg>
                                    </div>
                                    <div class="inst-introduce-confirmation-copy">
                                        <strong>Intro ready to send</strong>
                                        <p>
                                            We'll queue the email to <span id="instIntroduceConfirmationRecruiter"><?php echo esc_html($condensed_recruiter_name ?: $display_recruiter_name ?: 'this recruiter'); ?></span>
                                            once you review your note below.
                                        </p>
                                    </div>
                                    <button type="button" class="inst-introduce-confirmation-dismiss" id="instIntroduceDismiss" aria-label="Dismiss">
                                        &times;
                                    </button>
                                </div>

                                <!-- Recruiter Card in Express Interest -->
                                <?php
                                $express_avatar_initial = strtoupper(substr(($recruiter_name ?: $public_recruiter_label ?: 'R'), 0, 1));
                                ?>
                                <?php if ($recruiter_name || $recruiter_company) : ?>
                                    <div class="inst-express-recruiter">
                                        <div class="inst-recruiter-card inst-recruiter-card--compact" data-recruiter-id="<?php echo esc_attr($crm_recruiter_id); ?>" data-recruiter-name="<?php echo esc_attr($recruiter_name); ?>" data-recruiter-company="<?php echo esc_attr($recruiter_company); ?>" data-recruiter-email="<?php echo esc_attr($recruiter_email); ?>">
                                            <div class="inst-recruiter-compact-layout">
                                                <div class="inst-recruiter-compact-main">
                                                    <div class="inst-recruiter-compact-id">
                                                        <div class="inst-recruiter-avatar<?php echo $recruiter_image_url ? ' inst-recruiter-avatar--has-image' : ''; ?>" id="instExpressRecruiterAvatar" data-avatar-initial="<?php echo esc_attr($express_avatar_initial); ?>">
                                                            <?php if ($recruiter_image_url) : ?>
                                                                <img src="<?php echo esc_url($recruiter_image_url); ?>" alt="<?php echo esc_attr($display_recruiter_name ?: $recruiter_name); ?>">
                                                            <?php else : ?>
                                                                <?php echo esc_html($express_avatar_initial); ?>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="inst-recruiter-compact-text">
                                                            <span class="inst-recruiter-label">Reaching out to</span>
                                                            <?php if ($condensed_recruiter_name) : ?>
                                                                <h4 class="inst-recruiter-name" id="instExpressRecruiterName"><?php echo esc_html($condensed_recruiter_name); ?></h4>
                                                            <?php endif; ?>
                                                            <?php if ($meta_title && $meta_company) : ?>
                                                                <p class="inst-recruiter-meta" id="instExpressRecruiterMeta"><?php echo esc_html($meta_title . ' at ' . $meta_company); ?></p>
                                                            <?php elseif ($meta_company) : ?>
                                                                <p class="inst-recruiter-meta" id="instExpressRecruiterMeta"><?php echo esc_html($meta_company); ?></p>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <ul class="inst-recruiter-compact-meta">
                                                        <li>
                                                            <span>Mandate</span>
                                                            <strong><?php echo esc_html($job_title); ?></strong>
                                                        </li>
                                                        <?php if ($company_name) : ?>
                                                            <li>
                                                                <span>Company</span>
                                                                <strong><?php echo esc_html($company_name); ?></strong>
                                                            </li>
                                                        <?php endif; ?>
                                                        <?php if ($location_label) : ?>
                                                            <li>
                                                                <span>Location</span>
                                                                <strong><?php echo esc_html($location_label); ?></strong>
                                                            </li>
                                                        <?php endif; ?>
                                                        <?php if ($recruiter_company) : ?>
                                                            <li>
                                                                <span>Recruiter firm</span>
                                                                <strong><?php echo esc_html($recruiter_company); ?></strong>
                                                            </li>
                                                        <?php endif; ?>
                                                    </ul>
                                                </div>
                                                <div class="inst-recruiter-compact-side">
                                                    <div class="inst-recruiter-compact-highlight">
                                                        <span>Concierge intro</span>
                                                        <strong>72% reply</strong>
                                                        <p>MENA Careers nudges this recruiter with your tailored CV + kit.</p>
                                                    </div>
                                                    <div class="inst-recruiter-compact-actions">
                                                        <div class="inst-recruiter-compact-buttons">
                                                            <?php if ($recruiter_linkedin && $is_logged_in) : ?>
                                                                <a href="<?php echo esc_url($recruiter_linkedin); ?>" target="_blank" class="inst-recruiter-link inst-recruiter-link--small" title="View LinkedIn">
                                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                        <path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z" />
                                                                        <rect x="2" y="9" width="4" height="12" />
                                                                        <circle cx="4" cy="4" r="2" />
                                                                    </svg>
                                                                </a>
                                                            <?php endif; ?>
                                                            <button type="button" class="inst-add-to-list-btn inst-add-to-list-btn--small" title="Message recruiter" data-scroll-target=".inst-express-section">
                                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                                                                    <polyline points="22,6 12,13 2,6" />
                                                                </svg>
                                                            </button>
                                                            <button type="button"
                                                                class="inst-introduce-btn inst-introduce-btn--compact"
                                                                id="instExpressIntroduceBtn"
                                                                data-introduce-trigger="inline"
                                                                data-recruiter-name="<?php echo esc_attr($condensed_recruiter_name ?: $recruiter_name ?: $public_recruiter_label); ?>"
                                                                data-recruiter-company="<?php echo esc_attr($recruiter_company ?: $company_name); ?>"
                                                                data-role-title="<?php echo esc_attr($job_title); ?>">
                                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                    <path d="M21 7l-9 9-4-4" />
                                                                    <path d="M3 12l3 3 4-4 4 4 7-7" />
                                                                </svg>
                                                                <span>Introduce Me</span>
                                                            </button>
                                                        </div>
                                                        <div class="inst-recruiter-compact-footnote">
                                                            <p class="inst-intro-cta-note inst-intro-cta-note--inline">We'll email <?php echo esc_html($condensed_recruiter_name ?: $display_recruiter_name ?: 'the recruiter'); ?> once you finish your message.</p>
                                                            <?php if (!$is_logged_in) : ?>
                                                                <a href="<?php echo esc_url($login_url); ?>" class="inst-login-reveal-btn inst-login-reveal-btn--small">Log in to reveal details</a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
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
                                        <span id="instMessageRecruiterLabel">Message Recruiter</span>
                                    </button>
                                </div>

                            </div>
                        </div>

                        <?php if (false) : ?>
                            <!-- ========================================
                     SIMILAR POSTS TAB (disabled)
                     ======================================== -->
                            <div class="inst-tab-view" id="inst-similar-posts-view" role="tabpanel">
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
                                    if (!empty($industries)) {
                                        $similar_args['tax_query'] = [
                                            [
                                                'taxonomy' => 'recruiter_post_industry',
                                                'field' => 'name',
                                                'terms' => $industries,
                                            ]
                                        ];
                                    }

                                    $similar_posts = new WP_Query($similar_args);

                                    // Fallback: If no matches, get recent posts from same industry
                                    if (!$similar_posts->have_posts() && !empty($industries)) {
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
                                                    'terms' => $industries,
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
                            <h3>Get Introduced to Recruiters Hiring</h3>
                            <p>Choose a single tailored intro or launch a mini campaign so MENA Careers can line up multiple replies.</p>
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
                                    <li>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="20 6 9 17 4 12" />
                                        </svg>
                                        MENA Careers confirms your intro was seen and nudges for replies
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
                                    <li>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="20 6 9 17 4 12" />
                                        </svg>
                                        Concierge follow-ups secure recruiter responses
                                    </li>
                                </ul>
                                <button type="button" class="inst-likelihood-btn inst-likelihood-btn--secondary inst-likelihood-btn--reach" id="instLikelihoodMultiBtn">
                                    Reach More Recruiters Hiring
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="5" y1="12" x2="15" y2="12" />
                                        <polyline points="10 7 15 12 10 17" />
                                        <line x1="9" y1="7" x2="9" y2="17" />
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
                            <p>Finish the basics below so your outreach includes a name and direct contact.</p>
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
                            <p>Premium members meet with a dedicated coach who reviews your materials, hand-picks recruiters, and keeps you accountable.</p>
                        </div>
                        <ul class="inst-expert-benefits">
                            <li>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12" />
                                </svg>
                                1:1 coaching session tailored to your seniority and target sector
                            </li>
                            <li>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12" />
                                </svg>
                                Access to MENA Careers's recruiter bench and Express Interest flows
                            </li>
                            <li>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12" />
                                </svg>
                                MENA Careers-generated CVs, cover letters, and outreach scripts reviewed by a human expert
                            </li>
                        </ul>
                        <div class="inst-expert-footer">
                            <button type="button" class="inst-expert-cta" id="instExpertJoinBtn">Unlock coaching with membership</button>
                        </div>
                    </div>
                </div>

                <div class="inst-email-preview-modal" id="instEmailPreviewModal" style="display: none;">
                    <div class="inst-email-preview-overlay"></div>
                    <div class="inst-email-preview-content">
                        <button type="button" class="inst-email-preview-close" id="instEmailPreviewClose">&times;</button>
                        <div class="inst-email-preview-header">
                            <span class="inst-email-preview-pill">Email Preview</span>
                            <h3>Email ready to send</h3>
                            <p>If your default email app didn’t open, copy the note below and send it from your inbox.</p>
                        </div>
                        <div class="inst-email-preview-body">
                            <label>Subject</label>
                            <div class="inst-email-preview-field">
                                <span id="instEmailPreviewSubject">Enquiry: Opportunity</span>
                                <button type="button" class="inst-email-preview-copy" data-copy-type="subject">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2" />
                                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
                                    </svg>
                                    Copy subject
                                </button>
                            </div>
                            <label>Message</label>
                            <div class="inst-email-preview-message" id="instEmailPreviewBody">
                                <p>Hi there — message preview will appear here.</p>
                            </div>
                        </div>
                        <div class="inst-email-preview-footer">
                            <button type="button" class="inst-email-preview-btn" id="instEmailPreviewCopyBody">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2" />
                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
                                </svg>
                                Copy message
                            </button>
                            <button type="button" class="inst-email-preview-btn inst-email-preview-btn--primary" id="instEmailPreviewContinue">
                                Looks good
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="5 12 9 16 19 6" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="inst-ready-modal" id="instReadyModal" style="display: none;">
                    <div class="inst-ready-overlay"></div>
                    <div class="inst-ready-content">
                        <button type="button" class="inst-ready-close" id="instReadyClose">&times;</button>
                        <div class="inst-ready-header">
                            <h3>Ready to Join MENA Careers?</h3>
                            <p>Premium members unlock the full workflow—concierge support, recruiter intros, and tailored materials.</p>
                        </div>
                        <ul class="inst-ready-benefits">
                            <li>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12" />
                                </svg>
                                Dedicated career coach who keeps you accountable every week
                            </li>
                            <li>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12" />
                                </svg>
                                Direct access to vetted recruiters and hidden mandates
                            </li>
                            <li>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12" />
                                </svg>
                                MENA Careers-generated CVs, cover letters, and outreach scripts
                            </li>
                        </ul>
                        <div class="inst-ready-footer">
                            <button type="button" class="inst-ready-cta" id="instReadyJoinBtn">See membership options</button>
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
                            <p>See every recruiter conversation, follow-up, and outcome in one dashboard.</p>
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
                                    <h4>Visual Pipeline</h4>
                                    <p>Drag-and-drop every opportunity from "Interested" to "Offer".</p>
                                </div>
                            </div>
                            <div class="inst-crm-feature">
                                <div class="inst-crm-feature-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M4 4h16c1.1 0 2 .9 2 2v3H2V6c0-1.1.9-2 2-2z" />
                                        <path d="M2 9h20v9a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2z" />
                                    </svg>
                                </div>
                                <div class="inst-crm-feature-text">
                                    <h4>Follow-up Reminders</h4>
                                    <p>Automatic nudges so no recruiter conversation slips.</p>
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
                                    <h4>Outreach Intelligence</h4>
                                    <p>Know which recruiters responded and who needs another touch.</p>
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

                <div class="inst-crm-modal" id="instIntroduceModal" style="display: none;">
                    <div class="inst-crm-modal-overlay"></div>
                    <div class="inst-crm-modal-content">
                        <button type="button" class="inst-crm-modal-close" id="instIntroduceClose">&times;</button>
                        <div class="inst-crm-modal-header">
                            <div class="inst-crm-modal-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M18 8a6 6 0 0 1-12 0" />
                                    <path d="M12 14v7" />
                                    <path d="M8 21h8" />
                                    <path d="M5 5h14" />
                                </svg>
                            </div>
                            <h3>Warm Introductions are Premium</h3>
                            <p>We'll brief the recruiter and send a concierge intro on your behalf for <strong id="instIntroduceRecruiterName"><?php echo esc_html($condensed_recruiter_name ?: $recruiter_name ?: $public_recruiter_label ?: 'this recruiter'); ?></strong>.</p>
                            <p class="inst-crm-modal-detail">Role:&nbsp;<span id="instIntroduceRoleTitle"><?php echo esc_html($job_title ?: 'this role'); ?></span> at <span id="instIntroduceRoleCompany"><?php echo esc_html($company_name ?: 'their company'); ?></span></p>
                        </div>
                        <div class="inst-intro-preview">
                            <div class="inst-intro-preview-message">
                                <h4>What we send</h4>
                                <pre id="instIntroducePreviewMessage"><?php
                                                                        $intro_preview_name = $condensed_recruiter_name ?: $recruiter_name ?: $public_recruiter_label ?: 'there';
                                                                        $intro_preview_role = $job_title ?: 'this role';
                                                                        $intro_preview_company = $company_name ? ' at ' . $company_name : '';
                                                                        $intro_preview_lines = [
                                                                            "Hi {$intro_preview_name},",
                                                                            '',
                                                                            "I'm sharing a MENA Careers member for the {$intro_preview_role}{$intro_preview_company}.",
                                                                            "They've been delivering the same mix of projects outlined in your search.",
                                                                            '',
                                                                            'Key skills: commercial finance, GTM analytics, stakeholder comms.',
                                                                            '',
                                                                            "I'll include their tailored CV and send availability once you give me the nod.",
                                                                            '',
                                                                            'Best,',
                                                                            'MENA Careers'
                                                                        ];
                                                                        echo esc_html(implode("\n", $intro_preview_lines));
                                                                        ?></pre>
                            </div>
                            <div class="inst-intro-preview-side">
                                <div class="inst-intro-stat">
                                    <span class="inst-intro-stat-value" id="instIntroduceReplyStat">72%</span>
                                    <span class="inst-intro-stat-label">avg. reply rate</span>
                                </div>
                                <ul class="inst-intro-reasons" id="instIntroducePreviewReasons">
                                    <li>Highlights the role and why you match it.</li>
                                    <li>Uses the same keywords the recruiter wrote in the brief.</li>
                                    <li>Attaches your MENA Careers CV and recent metrics.</li>
                                </ul>
                            </div>
                        </div>
                        <div class="inst-intro-faq">
                            <h4>What happens next?</h4>
                            <ul>
                                <li>Finish your message below and tap Introduce Me.</li>
                                <li>MENA Careers emails the recruiter with your CV, strengths, and next steps.</li>
                                <li>We follow up if they haven’t replied within two business days.</li>
                            </ul>
                        </div>
                        <div class="inst-crm-modal-actions">
                            <button type="button" class="inst-crm-modal-cta" id="instIntroduceJoinBtn">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 5v14" />
                                    <path d="M5 12h14" />
                                </svg>
                                Join MENA Careers to Unlock Introductions
                            </button>
                            <p class="inst-crm-modal-note">Introduce Me lives inside MENA Careers Pro (plans start at £99/month). <a href="https://joinsenna.com/memberships/" target="_blank" rel="noopener">See membership options</a>.</p>
                        </div>
                    </div>
                </div>

                <div class="inst-crm-modal" id="instIntroduceGateModal" style="display: none;">
                    <div class="inst-crm-modal-overlay"></div>
                    <div class="inst-crm-modal-content">
                        <button type="button" class="inst-crm-modal-close" id="instIntroduceGateClose">&times;</button>
                        <div class="inst-crm-modal-header">
                            <div class="inst-crm-modal-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M5 12h14" />
                                    <polyline points="12 5 19 12 12 19" />
                                </svg>
                            </div>
                            <h3>Introduce multiple recruiters</h3>
                            <p>Premium members ask MENA Careers to send intro emails to their selected recruiters.</p>
                        </div>
                        <div class="inst-crm-modal-features">
                            <div class="inst-crm-feature">
                                <div class="inst-crm-feature-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="20 6 9 17 4 12" />
                                    </svg>
                                </div>
                                <div class="inst-crm-feature-text">
                                    <h4>One click intros</h4>
                                    <p>We email each recruiter with your tailored CV and talking points.</p>
                                </div>
                            </div>
                            <div class="inst-crm-feature">
                                <div class="inst-crm-feature-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M12 20l9-5-9-5-9 5 9 5z" />
                                    </svg>
                                </div>
                                <div class="inst-crm-feature-text">
                                    <h4>Follow-up tracking</h4>
                                    <p>See who replied and who needs another nudge inside MENA Careers.</p>
                                </div>
                            </div>
                        </div>
                        <div class="inst-crm-modal-actions">
                            <button type="button" class="inst-crm-modal-cta" id="instIntroduceGateJoinBtn">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="12" y1="5" x2="12" y2="19" />
                                    <line x1="5" y1="12" x2="19" y2="12" />
                                </svg>
                                Join MENA Careers to Unlock Express Interest
                            </button>
                            <p class="inst-crm-modal-note">Visit joinsenna.com/memberships to compare plans.</p>
                        </div>
                    </div>
                </div>

                <div class="inst-apply-modal" id="instApplyModal" style="display: none;">
                    <div class="inst-apply-modal-overlay"></div>
                    <div class="inst-apply-modal-content">
                        <button type="button" class="inst-apply-modal-close" id="instApplyModalClose" aria-label="Close">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18" />
                                <line x1="6" y1="6" x2="18" y2="18" />
                            </svg>
                        </button>
                        <div class="inst-apply-modal-header">
                            <h3>With or without the MENA Careers application pack?</h3>
                            <p>Members unlock tailored CVs, cover letters, and recruiter-ready outreach. Non-members send a basic note.</p>
                        </div>
                        <div class="inst-apply-comparison">
                            <div class="inst-apply-option inst-apply-option--with">
                                <span class="inst-apply-badge">With Materials</span>
                                <h4>4× higher reply rate</h4>
                                <ul>
                                    <li>Tailored cover letter + JD-aligned CV</li>
                                    <li>Recruiter-specific LinkedIn intro</li>
                                    <li>Interview prep & ATS keywords</li>
                                </ul>
                                <button type="button" class="inst-apply-upgrade" data-apply-action="upgrade">Unlock full pack</button>
                            </div>
                            <div class="inst-apply-option inst-apply-option--without">
                                <span class="inst-apply-badge">Without Materials</span>
                                <h4>Higher rejection risk</h4>
                                <ul>
                                    <li>Generic email only</li>
                                    <li>No supporting talking points</li>
                                    <li>No ATS optimization</li>
                                </ul>
                                <button type="button" class="inst-apply-continue" data-apply-action="continue">Continue without materials</button>
                            </div>
                        </div>
                        <div class="inst-apply-modal-footer">
                            <p>Want the recruiter-ready version? <a href="https://joinsenna.com/memberships/" target="_blank" rel="noopener">Join MENA Careers membership</a>.</p>
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
                        'is_premium' => $is_premium,
                        'is_logged_in' => $is_logged_in,
                        'membership_url' => 'https://joinsenna.com/memberships/',
                    ]); ?>
                </script>

            </div>
        <?php
    }

    /**
     * Build the full JD text for analysis
     */
    function sffc_build_jd_text_for_analysis($post_id, $post)
    {
        $job_title = get_post_meta($post_id, '_job_title', true) ?: $post->post_title;
        $company_name = get_post_meta($post_id, '_company_name', true);
        $job_location = get_post_meta($post_id, '_job_location', true);
        $experience_years = get_post_meta($post_id, '_experience_years', true);
        $key_requirements = get_post_meta($post_id, '_key_requirements', true);
        $ideal_background = get_post_meta($post_id, '_ideal_background', true);

        $industries = wp_get_post_terms($post_id, 'recruiter_post_industry', ['fields' => 'names']);
        $locations = wp_get_post_terms($post_id, 'recruiter_post_location', ['fields' => 'names']);

        $jd_text = "Job Title: {$job_title}\n";

        if ($company_name) {
            $jd_text .= "Company: {$company_name}\n";
        }

        if ($job_location || !empty($locations)) {
            $loc = $job_location ?: implode(', ', $locations);
            $jd_text .= "Location: {$loc}\n";
        }

        if (!empty($industries)) {
            $jd_text .= "Industry: " . implode(', ', $industries) . "\n";
        }

        if ($experience_years) {
            $jd_text .= "Experience Required: {$experience_years}\n";
        }

        $jd_text .= "\n";

        if ($key_requirements) {
            $jd_text .= "Key Requirements:\n{$key_requirements}\n\n";
        }

        if ($ideal_background) {
            $jd_text .= "Ideal Background:\n{$ideal_background}\n\n";
        }

        $jd_text .= "Job Description:\n" . wp_strip_all_tags($post->post_content);

        return $jd_text;
    }

    /**
     * Enqueue assets for recruiter post article (Express Interest flow)
     */
    function sffc_enqueue_recruiter_article_assets($post_id, $jd_text, $recruiter_email, $job_title, $company_name, $context = [])
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

        // Get recruiter name for JS
        $recruiter_name = get_post_meta($post_id, '_recruiter_name', true);

        $gap_analyzer_nonce = wp_create_nonce('sffc_gap_analyzer_nonce');
        $express_interest_nonce = wp_create_nonce('sffc_express_interest_nonce');
        $crm_nonce = wp_create_nonce('sffc_crm_nonce');
        $pipeline_url = add_query_arg('tab', 'pipeline', home_url('/senna-recruiter-outreach/'));

        $crm_post_id = isset($context['crm_post_id']) ? (int) $context['crm_post_id'] : 0;
        $crm_recruiter_id = isset($context['recruiter_id']) ? (int) $context['recruiter_id'] : 0;

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
            'crm_nonce'       => $crm_nonce,
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
            'crm_post_id'     => $crm_post_id,
            'recruiter_id'    => $crm_recruiter_id,
            'pipeline_url'    => $pipeline_url,
            'crm_url'         => home_url('/terminal/'),
            'source'          => 'wp',
        ]);
    }
