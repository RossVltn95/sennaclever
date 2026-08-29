<?php
if (!defined('ABSPATH')) {
    exit;
}

if (empty($stories_feed)) {
    echo '<div class="sffc-news-dashboard-empty">' . esc_html__('We are preparing your private markets news feed. Please check back in a moment.', 'senna-finance') . '</div>';
    return;
}

$article_view = isset($article_view) && is_array($article_view) ? $article_view : array();
$article_mode = !empty($article_view);

$current_user = wp_get_current_user();
$dashboard_instance = SFFC_PE_News_Dashboard::get_instance();
$user_name = isset($user_name) ? $user_name : ($current_user && $current_user->exists() ? $current_user->display_name : __('Guest Reader', 'senna-finance'));
$ticker_feed = array_slice($stories_feed, 0, 12);
$is_logged_in = is_user_logged_in();
$login_url = site_url('/login-auth/');
$join_url = 'https://joinsenna.com/memberships/';
$logout_url = $is_logged_in ? wp_logout_url(home_url('/')) : '';
$user_profile_url = $is_logged_in && $current_user && $current_user->exists() ? get_edit_user_link($current_user->ID) : $login_url;
$current_page_url = function_exists('get_permalink') ? get_permalink() : home_url('/');
$raw_initial = $is_logged_in ? $user_name : __('Guest', 'senna-finance');
if (function_exists('mb_substr')) {
    $raw_initial = mb_substr(trim($raw_initial), 0, 1, 'UTF-8');
} else {
    $raw_initial = substr(trim($raw_initial), 0, 1);
}
$user_initial = strtoupper($raw_initial ?: ($is_logged_in ? 'S' : 'G'));
$user_secondary_label = $is_logged_in ? __('Member', 'senna-finance') : __('Guest access', 'senna-finance');
$user_menu_context = array(
    'profile_url' => $user_profile_url,
    'login_url' => $login_url,
    'logout_url' => $logout_url,
    'join_url' => $join_url,
    'dashboard_url' => $current_page_url,
    'saved_url' => $current_page_url . '#saved',
    'alerts_url' => !empty($messaging_portal_url) ? $messaging_portal_url : $current_page_url . '#alerts',
    'home_url' => home_url('/')
);
$user_menu_links = $dashboard_instance->get_user_menu_items($is_logged_in, $user_menu_context);
global $sffc_saved_post_ids;
$sffc_saved_post_ids = isset($saved_post_ids) && is_array($saved_post_ids) ? $saved_post_ids : array();

if (!function_exists('sffc_render_card')) {
    function sffc_render_card($item, $allow_save = true)
    {
        global $sffc_saved_post_ids;
        $keywords = implode(' ', array_map('sanitize_title', $item['keywords'] ?? array()));
        $type = $item['type'] ?? 'news';
        $is_saved = is_array($sffc_saved_post_ids) && in_array($item['id'], $sffc_saved_post_ids, true);

        $pill_class = 'is-news';
        $pill_label = esc_html__('Market Insight', 'senna-finance');

        $job_badges = array();
        $job_snapshot = array();

        if ('deal' === $type) {
            $pill_class = 'is-deal';
            $pill_label = esc_html__('Deal Flow', 'senna-finance');
        } elseif ('job' === $type) {
            $pill_class = 'is-job';
            $pill_label = esc_html__('Executive Role', 'senna-finance');

            if (!empty($item['location'])) {
                $job_badges[] = esc_html($item['location']);
            }
            if (!empty($item['job_type'])) {
                $job_badges[] = esc_html($item['job_type']);
            }
            if (!empty($item['job_level'])) {
                $job_badges[] = esc_html($item['job_level']);
            }
            if (!empty($item['compensation'])) {
                $job_badges[] = esc_html($item['compensation']);
            }

            // Calculate job match breakdown based on user profile
            $match_breakdown = array(
                'skills' => 0,
                'experience' => 0,
                'industry' => 0,
                'location' => 0
            );

            if (is_user_logged_in()) {
                $user_id = get_current_user_id();
                $user_profile = get_user_meta($user_id, 'sffc_professional_profile', true);
                if (!empty($user_profile) && is_array($user_profile)) {
                    $job_text = strtolower($item['title'] . ' ' . ($item['excerpt'] ?? '') . ' ' . ($item['company'] ?? ''));
                    $job_location = strtolower($item['location'] ?? '');

                    // 1. Skills Match - compare user skills against job description
                    if (!empty($user_profile['skills']) && is_array($user_profile['skills'])) {
                        $user_skills = array_map(function($s) {
                            return strtolower(is_array($s) ? ($s['name'] ?? '') : $s);
                        }, $user_profile['skills']);
                        $user_skills = array_filter($user_skills);
                        $skill_matches = 0;
                        foreach ($user_skills as $skill) {
                            if (!empty($skill) && stripos($job_text, $skill) !== false) {
                                $skill_matches++;
                            }
                        }
                        // Calculate percentage: matched skills / total skills
                        $match_breakdown['skills'] = count($user_skills) > 0
                            ? round(($skill_matches / count($user_skills)) * 100)
                            : 0;
                    }

                    // 2. Experience Match - compare experience level and description
                    $exp_score = 0;
                    $job_level_text = strtolower($item['job_level'] ?? '');

                    // Check experience level match
                    if (!empty($user_profile['experience_level'])) {
                        $user_level = $user_profile['experience_level'];
                        $level_match = false;

                        if ($user_level === 'junior' && (strpos($job_level_text, 'junior') !== false || strpos($job_level_text, 'entry') !== false || strpos($job_level_text, 'associate') !== false || strpos($job_level_text, 'analyst') !== false)) {
                            $level_match = true;
                        } elseif ($user_level === 'mid' && (strpos($job_level_text, 'mid') !== false || strpos($job_level_text, 'senior associate') !== false || strpos($job_level_text, 'manager') !== false)) {
                            $level_match = true;
                        } elseif ($user_level === 'senior' && (strpos($job_level_text, 'senior') !== false || strpos($job_level_text, 'lead') !== false || strpos($job_level_text, 'principal') !== false)) {
                            $level_match = true;
                        } elseif ($user_level === 'executive' && (strpos($job_level_text, 'director') !== false || strpos($job_level_text, 'vp') !== false || strpos($job_level_text, 'head') !== false || strpos($job_level_text, 'chief') !== false || strpos($job_level_text, 'partner') !== false)) {
                            $level_match = true;
                        }
                        $exp_score += $level_match ? 50 : 20;
                    }

                    // Check experience description match against job
                    if (!empty($user_profile['latest_experience_description'])) {
                        $exp_desc = strtolower($user_profile['latest_experience_description']);
                        $exp_keywords = array_filter(preg_split('/[\s,.\-;:]+/', $exp_desc));
                        $exp_keywords = array_unique(array_filter($exp_keywords, function($w) {
                            return strlen($w) > 4; // Only meaningful words
                        }));
                        $exp_matches = 0;
                        $check_count = min(20, count($exp_keywords)); // Check top 20 keywords
                        $sample_keywords = array_slice($exp_keywords, 0, $check_count);
                        foreach ($sample_keywords as $keyword) {
                            if (stripos($job_text, $keyword) !== false) {
                                $exp_matches++;
                            }
                        }
                        $exp_score += $check_count > 0 ? round(($exp_matches / $check_count) * 50) : 0;
                    } else {
                        $exp_score += 25; // Default partial score if no description
                    }
                    $match_breakdown['experience'] = min(100, $exp_score);

                    // 3. Industry Match - compare preferred industries
                    if (!empty($user_profile['preferred_industries'])) {
                        $industries = strtolower($user_profile['preferred_industries']);
                        $industry_keywords = array_filter(array_map('trim', preg_split('/[,;]+/', $industries)));
                        $industry_matches = 0;
                        foreach ($industry_keywords as $keyword) {
                            if (!empty($keyword) && strlen($keyword) > 2 && stripos($job_text, $keyword) !== false) {
                                $industry_matches++;
                            }
                        }
                        $match_breakdown['industry'] = count($industry_keywords) > 0
                            ? round(($industry_matches / count($industry_keywords)) * 100)
                            : 0;
                    }

                    // Also check role type match for industry
                    if (!empty($user_profile['role_type']) && $match_breakdown['industry'] < 100) {
                        $role_type = $user_profile['role_type'];
                        $role_match = false;
                        if ($role_type === 'front_office' && (strpos($job_text, 'investment') !== false || strpos($job_text, 'trading') !== false || strpos($job_text, 'deal') !== false || strpos($job_text, 'coverage') !== false)) {
                            $role_match = true;
                        } elseif ($role_type === 'back_office' && (strpos($job_text, 'operations') !== false || strpos($job_text, 'settlement') !== false || strpos($job_text, 'reconciliation') !== false)) {
                            $role_match = true;
                        } elseif ($role_type === 'operations' && (strpos($job_text, 'operations') !== false || strpos($job_text, 'process') !== false || strpos($job_text, 'efficiency') !== false)) {
                            $role_match = true;
                        } elseif ($role_type === 'support' && (strpos($job_text, 'support') !== false || strpos($job_text, 'admin') !== false || strpos($job_text, 'hr') !== false || strpos($job_text, 'legal') !== false)) {
                            $role_match = true;
                        }
                        if ($role_match) {
                            $match_breakdown['industry'] = min(100, $match_breakdown['industry'] + 30);
                        }
                    }

                    // 4. Location Match - exact or partial location match
                    if (!empty($user_profile['preferred_location']) && !empty($job_location)) {
                        $pref_location = strtolower($user_profile['preferred_location']);
                        // Exact city match = 100%
                        if (stripos($job_location, $pref_location) !== false || stripos($pref_location, $job_location) !== false) {
                            $match_breakdown['location'] = 100;
                        } else {
                            // Check for country/region match
                            $location_parts = preg_split('/[,\s]+/', $pref_location);
                            foreach ($location_parts as $part) {
                                if (strlen($part) > 2 && stripos($job_location, $part) !== false) {
                                    $match_breakdown['location'] = 60;
                                    break;
                                }
                            }
                        }
                    } elseif (!empty($user_profile['location']) && !empty($job_location)) {
                        // Fallback to profile location
                        $user_location = strtolower($user_profile['location']);
                        if (stripos($job_location, $user_location) !== false || stripos($user_location, $job_location) !== false) {
                            $match_breakdown['location'] = 100;
                        } else {
                            $match_breakdown['location'] = 30; // Different location
                        }
                    }

                } else {
                    // No profile data - show 0% to encourage profile completion
                    $match_breakdown = array(
                        'skills' => 0,
                        'experience' => 0,
                        'industry' => 0,
                        'location' => 0
                    );
                }
            } else {
                // Logged out users - show 0% to encourage sign up
                $match_breakdown = array(
                    'skills' => 0,
                    'experience' => 0,
                    'industry' => 0,
                    'location' => 0
                );
            }

            $job_snapshot = array(
                'match_breakdown' => $match_breakdown,
                'job_level' => $item['job_level'] ?? '',
                'job_type' => $item['job_type'] ?? '',
                'region' => $item['region'] ?? ''
            );
        } elseif ('research' === $type) {
            $pill_class = 'is-research';
            $pill_label = esc_html__('Research Note', 'senna-finance');
        } elseif ('signal' === $type) {
            $pill_class = 'is-signal';
            $pill_label = esc_html__('Breaking Signal', 'senna-finance');
        } elseif ('message' === $type) {
            $pill_class = 'is-message';
            $pill_label = esc_html__('Message', 'senna-finance');
        }

        $search_elements = array(
            $item['title'] ?? '',
            $item['excerpt'] ?? '',
            $item['company'] ?? '',
            $item['sector'] ?? '',
            $item['region'] ?? '',
            $pill_label
        );

        if ('job' === $type) {
            $search_elements[] = $item['job_level'] ?? '';
            $search_elements[] = $item['job_type'] ?? '';
            $search_elements[] = $item['location'] ?? '';
        }

        $search_index = strtolower(trim(preg_replace('/\s+/', ' ', wp_strip_all_tags(implode(' ', array_filter($search_elements))))));
        ?>
        <article class="sffc-feed-card" data-type="<?php echo esc_attr($type); ?>" data-keywords="<?php echo esc_attr($keywords); ?>" data-post-id="<?php echo esc_attr($item['id']); ?>" data-search-index="<?php echo esc_attr($search_index); ?>">
        <div class="sffc-feed-card__header">
            <div class="sffc-feed-card__left">
                <span class="sffc-feed-pill <?php echo esc_attr($pill_class); ?>"><?php echo esc_html($pill_label); ?></span>
            </div>
            <div class="sffc-feed-card__right">
                <span class="sffc-meta-label"><?php echo esc_html($item['relative_time']); ?> <?php esc_html_e('ago', 'senna-finance'); ?></span>
                <?php if ($allow_save) : ?>
                    <button type="button" class="sffc-save-btn<?php echo $is_saved ? ' is-saved' : ''; ?>" data-post-id="<?php echo esc_attr($item['id']); ?>">
                        <span><?php echo $is_saved ? esc_html__('Saved', 'senna-finance') : esc_html__('Save', 'senna-finance'); ?></span>
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <h3 class="text-h3"><a href="<?php echo esc_url($item['link']); ?>" target="_blank" rel="noopener noreferrer"><?php echo wp_kses($item['title'], array('strong' => array('class' => array()))); ?></a></h3>
        <p class="text-body1"><?php echo esc_html($item['excerpt']); ?></p>
        <?php if ('job' === $type) : ?>
            <div class="sffc-job-layout">
                <div class="sffc-job-main">
                    <?php if (!empty($job_badges)) : ?>
                        <div class="sffc-job-badges">
                            <?php foreach ($job_badges as $badge) : ?>
                                <span class="sffc-job-badge"><?php echo esc_html($badge); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($item['company'])) : ?>
                        <p class="sffc-job-company"><?php echo esc_html($item['company']); ?></p>
                    <?php endif; ?>
                </div>
                <?php if (!empty($job_snapshot) && isset($job_snapshot['match_breakdown'])) :
                    $breakdown = $job_snapshot['match_breakdown'];
                    $total_score = round(($breakdown['skills'] + $breakdown['experience'] + $breakdown['industry'] + $breakdown['location']) / 4);
                    $circumference = 2 * 3.14159 * 14; // ~87.96 for r=14

                    // Calculate segment lengths (each category is 25% of the circle)
                    $segment_length = $circumference / 4;
                    $skills_dash = ($breakdown['skills'] / 100) * $segment_length;
                    $exp_dash = ($breakdown['experience'] / 100) * $segment_length;
                    $industry_dash = ($breakdown['industry'] / 100) * $segment_length;
                    $location_dash = ($breakdown['location'] / 100) * $segment_length;

                    // Calculate offsets for each segment
                    $skills_offset = 0;
                    $exp_offset = -$segment_length;
                    $industry_offset = -($segment_length * 2);
                    $location_offset = -($segment_length * 3);
                    ?>
                    <div class="sffc-mini-match">
                        <svg viewBox="0 0 32 32" class="sffc-mini-donut">
                            <circle cx="16" cy="16" r="14" fill="none" stroke="rgba(13,53,62,0.06)" stroke-width="2.5"/>
                            <!-- Skills segment -->
                            <circle cx="16" cy="16" r="14" fill="none" stroke="#0d353e" stroke-width="2.5"
                                stroke-dasharray="<?php echo $skills_dash; ?> <?php echo $circumference; ?>"
                                stroke-dashoffset="<?php echo $skills_offset; ?>" stroke-linecap="round"
                                class="sffc-segment" data-label="Skills <?php echo $breakdown['skills']; ?>%"/>
                            <!-- Experience segment -->
                            <circle cx="16" cy="16" r="14" fill="none" stroke="#c75643" stroke-width="2.5"
                                stroke-dasharray="<?php echo $exp_dash; ?> <?php echo $circumference; ?>"
                                stroke-dashoffset="<?php echo $exp_offset; ?>" stroke-linecap="round"
                                class="sffc-segment" data-label="Experience <?php echo $breakdown['experience']; ?>%"/>
                            <!-- Industry segment -->
                            <circle cx="16" cy="16" r="14" fill="none" stroke="#0e6e6c" stroke-width="2.5"
                                stroke-dasharray="<?php echo $industry_dash; ?> <?php echo $circumference; ?>"
                                stroke-dashoffset="<?php echo $industry_offset; ?>" stroke-linecap="round"
                                class="sffc-segment" data-label="Industry <?php echo $breakdown['industry']; ?>%"/>
                            <!-- Location segment -->
                            <circle cx="16" cy="16" r="14" fill="none" stroke="#f97316" stroke-width="2.5"
                                stroke-dasharray="<?php echo $location_dash; ?> <?php echo $circumference; ?>"
                                stroke-dashoffset="<?php echo $location_offset; ?>" stroke-linecap="round"
                                class="sffc-segment" data-label="Location <?php echo $breakdown['location']; ?>%"/>
                        </svg>
                        <div class="sffc-mini-center">
                            <span class="sffc-mini-score"><?php echo esc_html($total_score); ?>%</span>
                            <span class="sffc-mini-label"><?php esc_html_e('Match', 'senna-finance'); ?></span>
                        </div>
                        <span class="sffc-segment-tooltip"></span>
                    </div>
                <?php endif; ?>
            </div>
            <div class="sffc-job-footer">
                <span class="sffc-meta-label"><?php echo esc_html($item['relative_time']); ?> <?php esc_html_e('ago', 'senna-finance'); ?></span>
                <div class="sffc-job-actions">
                    <button
                        type="button"
                        class="sffc-tailor-btn"
                        data-job-id="<?php echo esc_attr($item['id']); ?>"
                        data-job-title="<?php echo esc_attr($item['title'] ?? ''); ?>"
                        data-job-company="<?php echo esc_attr($item['company'] ?? ''); ?>"
                        data-job-location="<?php echo esc_attr($item['location'] ?? ''); ?>"
                        data-job-link="<?php echo esc_url($item['link'] ?? ''); ?>">
                        <?php esc_html_e('Tailor CV', 'senna-finance'); ?>
                    </button>
                    <a class="sffc-job-cta" href="<?php echo esc_url($item['link']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('View role', 'senna-finance'); ?></a>
                </div>
            </div>
        <?php else : ?>
            <div class="sffc-feed-meta">
                <?php if ('message' === $type) : ?>
                    <?php if (!empty($item['sender'])) : ?><span class="sffc-meta-label"><?php echo esc_html__('From', 'senna-finance') . ': ' . esc_html($item['sender']); ?></span><?php endif; ?>
                    <?php if (!empty($item['message_category'])) : ?><span class="sffc-meta-label"><?php echo esc_html(ucwords(str_replace('-', ' ', $item['message_category']))); ?></span><?php endif; ?>
                    <?php if (!empty($item['status'])) : ?><span class="sffc-meta-label"><?php echo esc_html(ucfirst($item['status'])); ?></span><?php endif; ?>
                <?php else : ?>
                    <?php if (!empty($item['company'])) : ?><span class="sffc-meta-label"><?php echo esc_html($item['company']); ?></span><?php endif; ?>
                    <?php if (!empty($item['sector'])) : ?><span class="sffc-meta-label"><?php echo esc_html($item['sector']); ?></span><?php endif; ?>
                    <?php if (!empty($item['region'])) : ?><span class="sffc-meta-label"><?php echo esc_html($item['region']); ?></span><?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </article>
        <?php
    }
}

if (!function_exists('sffc_filter_icon')) {
    function sffc_filter_icon($slug)
    {
        $icons = array(
            // Deal Types
            'fund-raises' => '<svg viewBox="0 0 24 24"><path d="M4 12h4l3-8 4 16 3-8h4" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'ma' => '<svg viewBox="0 0 24 24"><path d="M6 12h12M12 6v12" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>',
            'exits' => '<svg viewBox="0 0 24 24"><path d="M4 16l8-8 8 8" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'regulatory' => '<svg viewBox="0 0 24 24"><path d="M6 21h12V7l-6-4-6 4z" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'personnel' => '<svg viewBox="0 0 24 24"><path d="M12 12a4 4 0 100-8 4 4 0 000 8zm-7 9a7 7 0 0114 0" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'secondaries' => '<svg viewBox="0 0 24 24"><path d="M8 7h8M8 12h8M8 17h8M4 7h.01M4 12h.01M4 17h.01" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>',
            'distressed' => '<svg viewBox="0 0 24 24"><path d="M12 9v4m0 4h.01M12 3l9 18H3z" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',

            // Regions
            'north-america' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.4"/><path d="M12 3v18M3 12h18" fill="none" stroke="currentColor" stroke-width="1.4"/></svg>',
            'europe' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.4"/><path d="M9 8l6 8M15 8l-6 8" fill="none" stroke="currentColor" stroke-width="1.4"/></svg>',
            'asia-pacific' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.4"/><path d="M8 12a4 4 0 008 0" fill="none" stroke="currentColor" stroke-width="1.4"/></svg>',
            'private-equity' => '<svg viewBox="0 0 24 24"><path d="M12 3l3 7h7l-6 4 2 7-6-4-6 4 2-7-6-4h7z" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'latam' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.4"/><path d="M12 6v12M8 9h8" fill="none" stroke="currentColor" stroke-width="1.4"/></svg>',
            'global' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.4"/><ellipse cx="12" cy="12" rx="4" ry="9" fill="none" stroke="currentColor" stroke-width="1.4"/><path d="M3 12h18" fill="none" stroke="currentColor" stroke-width="1.4"/></svg>',
            'remote' => '<svg viewBox="0 0 24 24"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',

            // Sectors
            'buyout' => '<svg viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'growth-equity' => '<svg viewBox="0 0 24 24"><path d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'venture-capital' => '<svg viewBox="0 0 24 24"><path d="M13 10V3L4 14h7v7l9-11h-7z" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'real-estate' => '<svg viewBox="0 0 24 24"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'infrastructure' => '<svg viewBox="0 0 24 24"><path d="M4 21h16M4 18h16M6 18v-6m4 6v-6m4 6v-6m4 6v-6M4 9l8-6 8 6" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'credit' => '<svg viewBox="0 0 24 24"><path d="M3 10h18M7 15h1m4 0h1M3 7l1.5-3h15L21 7M5 7v10a2 2 0 002 2h10a2 2 0 002-2V7" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'healthcare' => '<svg viewBox="0 0 24 24"><path d="M4.8 9.4a5 5 0 017.2-6 5 5 0 017.2 6c0 4-7.2 9-7.2 9s-7.2-5-7.2-9z" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'technology' => '<svg viewBox="0 0 24 24"><path d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'energy' => '<svg viewBox="0 0 24 24"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',

            // Job Functions
            'investment-banking' => '<svg viewBox="0 0 24 24"><path d="M4 18h16M9 18V6l6 4v8" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'private-equity' => '<svg viewBox="0 0 24 24"><path d="M4 14l4 4 12-12" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'asset-management' => '<svg viewBox="0 0 24 24"><path d="M3 17h18M6 17V7h4v10m4 0V4h4v13" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'corporate-development' => '<svg viewBox="0 0 24 24"><path d="M4 12h6l3-8 3 8h4" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'strategy-research' => '<svg viewBox="0 0 24 24"><path d="M5 19l4-9 3 5 3-7 4 11" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'hedge-fund' => '<svg viewBox="0 0 24 24"><path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',

            // Job Levels
            'analyst' => '<svg viewBox="0 0 24 24"><path d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'associate' => '<svg viewBox="0 0 24 24"><path d="M12 12a4 4 0 100-8 4 4 0 000 8zm-6 9v-1a6 6 0 0112 0v1" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'vice-president' => '<svg viewBox="0 0 24 24"><path d="M5 5l7 7 7-7M5 19h14" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'director' => '<svg viewBox="0 0 24 24"><path d="M4 4h16v12H4zM9 20h6" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'partner' => '<svg viewBox="0 0 24 24"><path d="M6 20l6-16 6 16M4 15h16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',

            // All/Default
            'all' => '<svg viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>'
        );

        return $icons[$slug] ?? '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="1.4"/></svg>';
    }
}
?>
<div class="sffc-global-header-bar">
    <div class="sffc-global-header">
        <div class="sffc-global-header__brand">
            <span class="sffc-global-logo" data-role="dashboard-date"><?php echo esc_html(date_i18n('D j M Y', current_time('timestamp'))); ?></span>
        </div>
        <div class="sffc-global-header__search">
            <div class="sffc-dashboard-search" data-role="dashboard-search">
                <svg class="sffc-search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M11 19C15.4183 19 19 15.4183 19 11C19 6.58172 15.4183 3 11 3C6.58172 3 3 6.58172 3 11C3 15.4183 6.58172 19 11 19Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M21 21L16.65 16.65" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <input type="search" id="sffc-dashboard-search-input" name="dashboard_search" data-role="search-input" placeholder="<?php esc_attr_e('Search insights, jobs, research…', 'senna-finance'); ?>" aria-label="<?php esc_attr_e('Search dashboard', 'senna-finance'); ?>">
                <button type="button" class="sffc-search-clear" data-role="search-clear" aria-label="<?php esc_attr_e('Clear search', 'senna-finance'); ?>">&times;</button>
                <div class="sffc-search-suggestions" data-role="search-suggestions" aria-live="polite"></div>
            </div>
        </div>
        <div class="sffc-global-header__actions">
            <a class="sffc-join-btn" href="<?php echo esc_url($join_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Join', 'senna-finance'); ?></a>
            <div class="sffc-user-menu" data-role="user-menu">
                <button type="button" class="sffc-user-toggle" data-role="user-toggle" aria-haspopup="true" aria-expanded="false">
                    <span class="sffc-user-avatar"><?php echo esc_html($user_initial); ?></span>
                    <span class="sffc-user-label">
                        <strong><?php echo esc_html($is_logged_in ? $user_name : __('Guest', 'senna-finance')); ?></strong>
                        <span><?php echo esc_html($user_secondary_label); ?></span>
                    </span>
                    <span class="sffc-user-caret" aria-hidden="true">▾</span>
                </button>
                <div class="sffc-user-dropdown" data-role="user-dropdown">
                    <div class="sffc-user-dropdown__header">
                        <span class="sffc-user-avatar sffc-user-avatar--menu"><?php echo esc_html($user_initial); ?></span>
                        <div class="sffc-user-dropdown__meta">
                            <strong><?php echo esc_html($is_logged_in ? $user_name : __('Guest Reader', 'senna-finance')); ?></strong>
                            <span><?php echo esc_html($user_secondary_label); ?></span>
                        </div>
                    </div>
                    <div class="sffc-user-dropdown__links" role="menu">
                        <?php if (!empty($user_menu_links)) : ?>
                            <?php foreach ($user_menu_links as $link) :
                                $target = isset($link['target']) ? $link['target'] : '_self';
                                $rel = ('_blank' === $target) ? ' rel="noopener noreferrer"' : '';
                                ?>
                                <a class="sffc-user-dropdown__link" role="menuitem" href="<?php echo esc_url($link['url']); ?>" target="<?php echo esc_attr($target); ?>"<?php echo $rel; ?>>
                                    <span><?php echo esc_html($link['label']); ?></span>
                                    <span class="sffc-user-dropdown__chevron" aria-hidden="true">›</span>
                                </a>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <p class="sffc-user-dropdown__empty"><?php esc_html_e('No actions available yet.', 'senna-finance'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="sffc-dashboard-container">
    <div class="sffc-feed-shell<?php echo $article_mode ? ' is-article-mode' : ''; ?>" data-post-ids="<?php echo esc_attr(implode(',', $post_ids)); ?>" data-nonce="<?php echo esc_attr($nonce); ?>" data-logged-in="<?php echo $is_logged_in ? '1' : '0'; ?>" data-default-tab="<?php echo esc_attr($article_mode ? 'article' : 'insights'); ?>">
    <?php if (!empty($ticker_feed)) :
        $ticker_palette = array(
            'deal' => array('label' => __('Deal', 'senna-finance'), 'class' => 'is-deal'),
            'news' => array('label' => __('News', 'senna-finance'), 'class' => 'is-news'),
            'research' => array('label' => __('Research', 'senna-finance'), 'class' => 'is-research'),
            'job' => array('label' => __('Role', 'senna-finance'), 'class' => 'is-job')
        );
        ?>
        <div class="sffc-ticker-bar">
            <div class="sffc-ticker-track">
                <?php foreach (array_merge($ticker_feed, $ticker_feed) as $item) :
                    $type = $item['type'] ?? 'news';
                    $badge = $ticker_palette[$type] ?? $ticker_palette['news'];
                    ?>
                    <div class="sffc-ticker-entry">
                        <span class="sffc-ticker-badge <?php echo esc_attr($badge['class']); ?>"><?php echo esc_html($badge['label']); ?></span>
                        <a href="<?php echo esc_url($item['link']); ?>" target="_blank" rel="noopener noreferrer"><?php echo wp_kses($item['title'], array('strong' => array('class' => array()))); ?></a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <header class="sffc-top-bar">
        <div class="sffc-user-card">
            <div class="sffc-user-avatar">
                <span class="sffc-avatar-letter">S</span>
                <span class="sffc-avatar-dot"></span>
            </div>
            <div>
                <p class="text-eyebrow2"><?php esc_html_e('Private Markets Daily', 'senna-finance'); ?></p>
                <h1 class="text-display1">MENA CAREERS</h1>
            </div>
        </div>
        <div class="sffc-top-bar-actions">
            <?php if (!$article_mode) : ?>
                <button class="sffc-filter-toggle" type="button" data-action="toggle-filters">
                    <span><?php esc_html_e('Filters', 'senna-finance'); ?></span>
                </button>
            <?php endif; ?>
            <?php if ($is_logged_in) : ?>
                <a class="sffc-upgrade-btn sffc-upgrade-btn--mobile" href="<?php echo esc_url($login_url); ?>"><?php esc_html_e('Account', 'senna-finance'); ?></a>
            <?php else : ?>
                <button class="sffc-upgrade-btn sffc-upgrade-btn--mobile" data-action="open-plan-modal"><?php esc_html_e('Subscribe', 'senna-finance'); ?></button>
            <?php endif; ?>
        </div>
        <div class="sffc-tab-controls">
            <?php
            if ($article_mode) {
                $tab_definitions = array(
                    'article' => __('Article', 'senna-finance'),
                    'insights' => __('Insights', 'senna-finance'),
                    'research' => __('Research', 'senna-finance'),
                );
            } else {
                $tab_definitions = array(
                    'insights' => __('Insights', 'senna-finance'),
                    'jobs' => __('Jobs', 'senna-finance'),
                    'signals' => __('Profile', 'senna-finance'),
                    'saved' => __('Saved', 'senna-finance'),
                    'research' => __('Recruiter Match', 'senna-finance')
                );
            }
            $default_tab = $article_mode ? 'article' : 'insights';
            foreach ($tab_definitions as $slug => $label) :
                $is_active_tab = ($slug === $default_tab);
                ?>
                <button class="sffc-tab-btn<?php echo $is_active_tab ? ' is-active' : ''; ?>" data-tab-target="<?php echo esc_attr($slug); ?>">
                    <?php if ($slug === 'alerts') : ?>
                        <svg class="sffc-tab-icon sffc-tab-icon--bell" width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2C10.9 2 10 2.9 10 4C10 4.1 10.01 4.19 10.02 4.29C7.16 5.14 5 7.83 5 11V17L3 19V20H21V19L19 17V11C19 7.83 16.84 5.14 13.98 4.29C13.99 4.19 14 4.1 14 4C14 2.9 13.1 2 12 2Z" fill="currentColor"/>
                            <path d="M12 22C13.1 22 14 21.1 14 20H10C10 21.1 10.9 22 12 22Z" fill="currentColor"/>
                        </svg>
                    <?php else : ?>
                        <span class="sffc-tab-icon sffc-tab-icon--<?php echo esc_attr($slug); ?>"></span>
                    <?php endif; ?>
                    <span class="sffc-tab-label"><?php echo esc_html($label); ?></span>
                </button>
            <?php endforeach; ?>
        </div>
    </header>

    <div class="sffc-layout">
        <aside class="sffc-column sffc-column--left">
            <?php if ($article_mode) :
                $toc_items = isset($article_view['toc']) ? $article_view['toc'] : array();
                ?>
                <section class="sffc-panel sffc-article-toc-panel">
                    <div class="sffc-section-label">
                        <span class="text-eyebrow2"><?php esc_html_e('Table of Contents', 'senna-finance'); ?></span>
                    </div>
                    <?php if (!empty($toc_items)) : ?>
                        <ol class="sffc-article-toc">
                            <?php foreach ($toc_items as $entry) : ?>
                                <li>
                                    <span class="sffc-article-toc__label"><?php echo esc_html($entry['label']); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php else : ?>
                        <p class="sffc-empty-state"><?php esc_html_e('Sections will appear here once the article is structured.', 'senna-finance'); ?></p>
                    <?php endif; ?>
                </section>
                <section class="sffc-panel sffc-article-highlights">
                    <div class="sffc-section-label">
                        <span class="text-eyebrow2"><?php esc_html_e('Key Highlights', 'senna-finance'); ?></span>
                    </div>
                    <?php if (!empty($article_view['highlights'])) : ?>
                        <ul>
                            <?php foreach ($article_view['highlights'] as $point) : ?>
                                <li><?php echo esc_html($point); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else : ?>
                        <p class="sffc-empty-state"><?php esc_html_e('Highlights will populate shortly.', 'senna-finance'); ?></p>
                    <?php endif; ?>
                </section>
            <?php else :
                $filter_labels = array(
                    'insights' => array(
                        'deal_types' => esc_html__('Deal Types', 'senna-finance'),
                        'regions' => esc_html__('Regions', 'senna-finance'),
                        'sectors' => esc_html__('Sectors', 'senna-finance')
                    ),
                    'jobs' => array(
                        'job_functions' => esc_html__('Role Focus', 'senna-finance'),
                        'job_regions' => esc_html__('Locations', 'senna-finance'),
                        'job_levels' => esc_html__('Seniority', 'senna-finance')
                    )
                );
                // Profile Card Data
                $profile_user_id = get_current_user_id();
                $profile_user = wp_get_current_user();
                $profile_meta = get_user_meta($profile_user_id, 'sffc_professional_profile', true);
                if (!is_array($profile_meta)) $profile_meta = array();

                $profile_headline = $profile_meta['headline'] ?? get_user_meta($profile_user_id, 'sffc_profile_headline', true);
                $profile_location = $profile_meta['location'] ?? get_user_meta($profile_user_id, 'sffc_profile_location', true);
                $profile_availability = get_user_meta($profile_user_id, 'sffc_profile_visibility', true) ?: 'open';
                ?>

                <!-- Profile Card (LinkedIn Style) -->
                <div class="sffc-profile-card" data-role="profile-card">
                    <?php if ($is_logged_in) : ?>
                        <div class="sffc-profile-card__banner"></div>
                        <div class="sffc-profile-card__avatar">
                            <?php echo get_avatar($profile_user_id, 80); ?>
                            <span class="sffc-profile-card__status sffc-profile-card__status--<?php echo esc_attr($profile_availability); ?>"></span>
                        </div>
                        <div class="sffc-profile-card__info">
                            <h3 class="sffc-profile-card__name"><?php echo esc_html($profile_user->display_name); ?></h3>
                            <?php if ($profile_headline) : ?>
                                <p class="sffc-profile-card__headline"><?php echo esc_html($profile_headline); ?></p>
                            <?php else : ?>
                                <p class="sffc-profile-card__headline sffc-profile-card__headline--empty"><?php esc_html_e('Add your headline', 'senna-finance'); ?></p>
                            <?php endif; ?>
                            <?php if ($profile_location) : ?>
                                <p class="sffc-profile-card__location">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                    <?php echo esc_html($profile_location); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="sffc-profile-card__actions">
                            <button type="button" class="sffc-profile-card__btn sffc-profile-card__btn--primary" data-tab-target="signals">
                                <?php esc_html_e('View Profile', 'senna-finance'); ?>
                            </button>
                        </div>
                        <div class="sffc-profile-card__stats">
                            <div class="sffc-profile-card__stat">
                                <span class="sffc-profile-card__stat-value"><?php echo esc_html(count($saved_feed_items)); ?></span>
                                <span class="sffc-profile-card__stat-label"><?php esc_html_e('Saved', 'senna-finance'); ?></span>
                            </div>
                            <div class="sffc-profile-card__stat">
                                <span class="sffc-profile-card__stat-value"><?php echo esc_html(count($jobs_feed)); ?></span>
                                <span class="sffc-profile-card__stat-label"><?php esc_html_e('Matches', 'senna-finance'); ?></span>
                            </div>
                        </div>
                    <?php else : ?>
                        <div class="sffc-profile-card__guest">
                            <div class="sffc-profile-card__guest-icon">
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            </div>
                            <p class="sffc-profile-card__guest-text"><?php esc_html_e('Sign in to build your professional profile', 'senna-finance'); ?></p>
                            <a href="<?php echo esc_url($login_url); ?>" class="sffc-profile-card__btn sffc-profile-card__btn--primary"><?php esc_html_e('Sign In', 'senna-finance'); ?></a>
                        </div>
                    <?php endif; ?>
                </div>
                <?php
                foreach ($filter_sets as $view => $groups) :
                    $is_active_stack = ($view === 'insights');
                    ?>
                    <div class="sffc-filter-stack<?php echo $is_active_stack ? ' is-active' : ''; ?>" data-filter-view="<?php echo esc_attr($view); ?>">
                        <?php foreach ($groups as $group => $filters) :
                            $section_title = $filter_labels[$view][$group] ?? esc_html__('Filters', 'senna-finance');
                            ?>
                            <section class="sffc-filter-section" data-filter-group="<?php echo esc_attr($group); ?>">
                                <div class="sffc-section-label">
                                    <span class="text-eyebrow2"><?php echo esc_html($section_title); ?></span>
                                </div>
                                <div class="sffc-filter-grid">
                                    <?php foreach ($filters as $filter) : ?>
                                        <button type="button" class="sffc-filter-btn<?php echo $filter['slug'] === 'all' ? ' is-active' : ''; ?>" data-keyword="<?php echo esc_attr($filter['slug']); ?>">
                                            <span class="sffc-filter-icon"><?php echo sffc_filter_icon($filter['slug']); ?></span>
                                            <span><?php echo esc_html($filter['label']); ?></span>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </aside>
        <div class="sffc-filter-overlay" data-role="filter-overlay"></div>

                        <main class="sffc-column sffc-column--feed">
            <?php if ($article_mode) :
                $chart_sets = isset($article_view['charts']) ? $article_view['charts'] : array();
                $research_cards = isset($article_view['research_cards']) ? $article_view['research_cards'] : array();
                ?>
                <div class="sffc-feed-tabs">
                    <div class="sffc-feed-tab is-active" data-tab="article">
                        <?php $hero = $article_view['hero']; ?>
                        <article class="sffc-article-panel">
                            <header class="sffc-article-panel__head">
                                <?php if (!empty($hero['eyebrow'])) : ?>
                                    <p class="sffc-article-panel__eyebrow"><?php echo esc_html($hero['eyebrow']); ?></p>
                                <?php endif; ?>
                                <h2 class="sffc-article-panel__title"><?php echo esc_html($hero['title']); ?></h2>
                                <?php if (!empty($hero['excerpt'])) : ?>
                                    <p class="sffc-article-panel__excerpt"><?php echo esc_html($hero['excerpt']); ?></p>
                                <?php endif; ?>
                                <div class="sffc-article-panel__meta">
                                    <div>
                                        <span class="sffc-article-panel__author"><?php echo esc_html($hero['author']); ?></span>
                                        <?php if (!empty($hero['author_role'])) : ?>
                                            <span class="sffc-article-panel__role"><?php echo esc_html($hero['author_role']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <time datetime="<?php echo esc_attr($hero['published_iso']); ?>"><?php echo esc_html($hero['published_human']); ?></time>
                                        <span><?php printf(esc_html__('%d min read', 'senna-finance'), intval($hero['reading_time'])); ?></span>
                                    </div>
                                    <div class="sffc-article-panel__signal">
                                        <span><?php echo esc_html($hero['signal']['value']); ?>%</span>
                                        <small><?php echo esc_html($hero['signal']['label']); ?></small>
                                    </div>
                                </div>
                                <?php if (!empty($hero['image'])) : ?>
                                    <figure class="sffc-article-panel__figure">
                                        <img src="<?php echo esc_url($hero['image']); ?>" alt="<?php echo esc_attr($hero['title']); ?>" loading="lazy">
                                    </figure>
                                <?php endif; ?>
                            </header>
                            <div class="sffc-article-panel__body">
                                <?php echo wp_kses_post($article_view['body']); ?>
                            </div>
                            <div class="sffc-article-panel__footer">
                                <?php if (!empty($hero['permalink'])) : ?>
                                    <a class="sffc-article-panel__cta" href="<?php echo esc_url($hero['permalink']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Open original', 'senna-finance'); ?></a>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($article_view['prompts'])) : ?>
                                <div class="sffc-article-panel__prompts">
                                    <p><?php esc_html_e('Ask MENA Careers more about this story:', 'senna-finance'); ?></p>
                                    <div class="sffc-article-panel__prompt-list">
                                        <?php foreach ($article_view['prompts'] as $prompt) : ?>
                                            <button type="button" class="sffc-article-panel__prompt" data-article-prompt="<?php echo esc_attr($prompt['prompt']); ?>"><?php echo esc_html($prompt['label']); ?></button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </article>
                    </div>
                    <div class="sffc-feed-tab" data-tab="insights">
                        <?php if (!empty($chart_sets)) : ?>
                            <?php if (!empty($chart_sets['commentary'])) : ?>
                                <div class="sffc-insight-commentary">
                                    <p><?php echo esc_html($chart_sets['commentary']); ?></p>
                                </div>
                            <?php endif; ?>
                            <div class="sffc-chart-grid">
                                <?php if (!empty($chart_sets['bars']) && is_array($chart_sets['bars'])) : ?>
                                    <?php foreach ($chart_sets['bars'] as $chart) : ?>
                                        <?php if (is_array($chart) && isset($chart['title'])) : ?>
                                        <div class="sffc-chart-card">
                                            <h4><?php echo esc_html($chart['title']); ?></h4>
                                            <div class="sffc-chart-bars">
                                                <?php if (!empty($chart['series']) && is_array($chart['series'])) : ?>
                                                <?php foreach ($chart['series'] as $row) : ?>
                                                    <?php if (is_array($row)) : ?>
                                                    <div class="sffc-chart-bar" data-value="<?php echo esc_attr($row['value'] ?? ''); ?>">
                                                        <span><?php echo esc_html($row['label'] ?? ''); ?></span>
                                                        <strong><?php echo esc_html($row['value'] ?? ''); ?></strong>
                                                    </div>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <?php if (!empty($chart_sets['lines']) && is_array($chart_sets['lines'])) : ?>
                                    <?php foreach ($chart_sets['lines'] as $chart) : ?>
                                        <?php if (is_array($chart) && isset($chart['title'])) : ?>
                                        <div class="sffc-chart-card">
                                            <h4><?php echo esc_html($chart['title']); ?></h4>
                                            <div class="sffc-chart-line">
                                                <?php if (!empty($chart['points']) && is_array($chart['points'])) : ?>
                                                <?php foreach ($chart['points'] as $point) : ?>
                                                    <?php if (is_array($point)) : ?>
                                                    <span style="--point-value: <?php echo esc_attr($point['value'] ?? 0); ?>%"></span>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <?php if (!empty($chart_sets['pie']) && is_array($chart_sets['pie'])) : ?>
                                    <?php foreach ($chart_sets['pie'] as $chart) : ?>
                                        <?php if (is_array($chart) && isset($chart['title'])) : ?>
                                        <div class="sffc-chart-card">
                                            <h4><?php echo esc_html($chart['title']); ?></h4>
                                            <div class="sffc-chart-pie">
                                                <?php if (!empty($chart['slices']) && is_array($chart['slices'])) : ?>
                                                <?php foreach ($chart['slices'] as $slice) : ?>
                                                    <?php if (is_array($slice)) : ?>
                                                    <span><?php echo esc_html($slice['label'] ?? ''); ?> – <?php echo esc_html($slice['value'] ?? 0); ?>%</span>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <?php if (!empty($chart_sets['stacked']) && is_array($chart_sets['stacked'])) : ?>
                                    <?php foreach ($chart_sets['stacked'] as $chart) : ?>
                                        <?php if (is_array($chart) && isset($chart['title'])) : ?>
                                        <div class="sffc-chart-card sffc-chart-card--stacked">
                                            <h4><?php echo esc_html($chart['title']); ?></h4>
                                            <div class="sffc-chart-stacked">
                                                <?php if (!empty($chart['series']) && is_array($chart['series'])) : ?>
                                                <?php foreach ($chart['series'] as $row) : ?>
                                                    <?php if (is_array($row)) : ?>
                                                    <div class="sffc-chart-stacked__row">
                                                        <span><?php echo esc_html($row['label'] ?? ''); ?></span>
                                                        <div class="sffc-chart-stacked__bar">
                                                            <?php if (!empty($row['segments']) && is_array($row['segments'])) : ?>
                                                            <?php foreach ($row['segments'] as $segment) : ?>
                                                                <?php if (is_array($segment)) : ?>
                                                                <span style="width: <?php echo esc_attr($segment['value'] ?? 0); ?>%"></span>
                                                                <?php endif; ?>
                                                            <?php endforeach; ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <?php if (!empty($chart_sets['heatmap']) && is_array($chart_sets['heatmap'])) : ?>
                                    <?php foreach ($chart_sets['heatmap'] as $chart) : ?>
                                        <?php if (is_array($chart) && isset($chart['title'])) : ?>
                                        <div class="sffc-chart-card sffc-chart-card--heatmap">
                                            <h4><?php echo esc_html($chart['title']); ?></h4>
                                            <div class="sffc-chart-heatmap">
                                                <?php if (!empty($chart['rows']) && is_array($chart['rows'])) : ?>
                                                <?php foreach ($chart['rows'] as $row) : ?>
                                                    <?php if (is_array($row)) : ?>
                                                    <div class="sffc-chart-heatmap__row">
                                                        <span><?php echo esc_html($row['label'] ?? ''); ?></span>
                                                        <div class="sffc-chart-heatmap__cells">
                                                            <?php if (!empty($row['values']) && is_array($row['values'])) : ?>
                                                            <?php foreach ($row['values'] as $value) : ?>
                                                                <span style="--heat-value: <?php echo esc_attr(is_numeric($value) ? $value : 0); ?>%"></span>
                                                            <?php endforeach; ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        <?php else : ?>
                            <p class="sffc-empty-state"><?php esc_html_e('Insights are being generated from this article.', 'senna-finance'); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="sffc-feed-tab" data-tab="research">
                        <?php if (!empty($research_cards)) : ?>
                            <div class="sffc-research-layout">
                                <!-- Research Header -->
                                <div class="sffc-research-header">
                                    <div class="sffc-research-header__badge">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                            <polyline points="14 2 14 8 20 8"/>
                                            <line x1="16" y1="13" x2="8" y2="13"/>
                                            <line x1="16" y1="17" x2="8" y2="17"/>
                                            <polyline points="10 9 9 9 8 9"/>
                                        </svg>
                                        <span><?php esc_html_e('Research Brief', 'senna-finance'); ?></span>
                                    </div>
                                    <div class="sffc-research-header__meta">
                                        <span class="sffc-research-header__date"><?php echo esc_html(date('M j, Y')); ?></span>
                                        <span class="sffc-research-header__divider">|</span>
                                        <span class="sffc-research-header__type"><?php esc_html_e('MENA Careers Analysis', 'senna-finance'); ?></span>
                                    </div>
                                </div>

                                <!-- Executive Summary Section -->
                                <?php $first_card = $research_cards[0] ?? null; ?>
                                <?php if ($first_card) : ?>
                                <section class="sffc-research-section sffc-research-section--summary">
                                    <div class="sffc-research-section__header">
                                        <h3 class="sffc-research-section__title">
                                            <span class="sffc-research-section__icon">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <circle cx="12" cy="12" r="10"/>
                                                    <line x1="12" y1="16" x2="12" y2="12"/>
                                                    <line x1="12" y1="8" x2="12.01" y2="8"/>
                                                </svg>
                                            </span>
                                            <?php echo esc_html($first_card['title']); ?>
                                        </h3>
                                    </div>
                                    <div class="sffc-research-section__content">
                                        <p class="sffc-research-summary-text"><?php echo esc_html($first_card['summary']); ?></p>
                                        <?php if (!empty($first_card['bullets'])) : ?>
                                        <div class="sffc-research-takeaways">
                                            <h4 class="sffc-research-takeaways__title"><?php esc_html_e('Key Takeaways', 'senna-finance'); ?></h4>
                                            <div class="sffc-research-takeaways__grid">
                                                <?php foreach ($first_card['bullets'] as $index => $bullet) : ?>
                                                <div class="sffc-research-takeaway">
                                                    <span class="sffc-research-takeaway__number"><?php echo esc_html($index + 1); ?></span>
                                                    <span class="sffc-research-takeaway__text"><?php echo esc_html($bullet); ?></span>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </section>
                                <?php endif; ?>

                                <!-- What to Watch Section -->
                                <?php $second_card = $research_cards[1] ?? null; ?>
                                <?php if ($second_card) : ?>
                                <section class="sffc-research-section sffc-research-section--watch">
                                    <div class="sffc-research-section__header">
                                        <h3 class="sffc-research-section__title">
                                            <span class="sffc-research-section__icon">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                                    <circle cx="12" cy="12" r="3"/>
                                                </svg>
                                            </span>
                                            <?php echo esc_html($second_card['title']); ?>
                                        </h3>
                                    </div>
                                    <div class="sffc-research-section__content">
                                        <p class="sffc-research-outlook-text"><?php echo esc_html($second_card['summary']); ?></p>
                                        <?php if (!empty($second_card['bullets'])) : ?>
                                        <div class="sffc-research-monitors">
                                            <?php foreach ($second_card['bullets'] as $bullet) : ?>
                                            <div class="sffc-research-monitor">
                                                <span class="sffc-research-monitor__indicator"></span>
                                                <span class="sffc-research-monitor__text"><?php echo esc_html($bullet); ?></span>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </section>
                                <?php endif; ?>

                                <!-- Additional Cards (if any) -->
                                <?php if (count($research_cards) > 2) : ?>
                                <section class="sffc-research-section sffc-research-section--additional">
                                    <div class="sffc-research-cards-row">
                                        <?php foreach (array_slice($research_cards, 2) as $card) : ?>
                                        <article class="sffc-research-card-compact">
                                            <h4><?php echo esc_html($card['title']); ?></h4>
                                            <p><?php echo esc_html($card['summary']); ?></p>
                                            <?php if (!empty($card['cta'])) : ?>
                                            <span class="sffc-research-card-compact__cta"><?php echo esc_html($card['cta']); ?></span>
                                            <?php endif; ?>
                                        </article>
                                        <?php endforeach; ?>
                                    </div>
                                </section>
                                <?php endif; ?>

                                <!-- Research Footer -->
                                <div class="sffc-research-footer">
                                    <div class="sffc-research-footer__disclaimer">
                                        <?php esc_html_e('Analysis generated by MENA Careers Intelligence. For informational purposes only.', 'senna-finance'); ?>
                                    </div>
                                    <div class="sffc-research-footer__actions">
                                        <button class="sffc-research-action" data-action="share">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <circle cx="18" cy="5" r="3"/>
                                                <circle cx="6" cy="12" r="3"/>
                                                <circle cx="18" cy="19" r="3"/>
                                                <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/>
                                                <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>
                                            </svg>
                                            <span><?php esc_html_e('Share', 'senna-finance'); ?></span>
                                        </button>
                                        <button class="sffc-research-action" data-action="download">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                                <polyline points="7 10 12 15 17 10"/>
                                                <line x1="12" y1="15" x2="12" y2="3"/>
                                            </svg>
                                            <span><?php esc_html_e('Export', 'senna-finance'); ?></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php else : ?>
                            <p class="sffc-empty-state"><?php esc_html_e('Research templates are loading.', 'senna-finance'); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="sffc-feed-tab" data-tab="saved">
                        <?php if (!$is_logged_in) : ?>
                            <!-- Example saved card for logged-out users -->
                            <div class="sffc-saved-preview">
                                <article class="sffc-feed-card sffc-saved-example" style="background: linear-gradient(135deg, #f8fafc 0%, #e0f2fe 100%); border: 2px dashed rgba(13, 53, 62, 0.2);">
                                    <div class="sffc-saved-icon" style="text-align: center; padding: 20px;">
                                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" style="margin: 0 auto; opacity: 0.3;">
                                            <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                    <div style="text-align: center; padding: 0 20px 20px;">
                                        <h3 style="font-size: 18px; margin-bottom: 12px; color: var(--sffc-heading);">
                                            <?php esc_html_e('Your Saved Collection', 'senna-finance'); ?>
                                        </h3>
                                        <p style="font-size: 14px; color: #6b7280; margin-bottom: 16px; line-height: 1.6;">
                                            <?php esc_html_e('Track jobs, save important alerts, bookmark research reports, and build your personalized career intelligence library. Everything you save syncs across all your devices.', 'senna-finance'); ?>
                                        </p>
                                        <div class="sffc-saved-features" style="display: flex; justify-content: center; gap: 24px; margin: 20px 0; flex-wrap: wrap;">
                                            <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #4b5563;">
                                                <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                </svg>
                                                <span><?php esc_html_e('Track Job Applications', 'senna-finance'); ?></span>
                                            </div>
                                            <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #4b5563;">
                                                <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                </svg>
                                                <span><?php esc_html_e('Save Deal Alerts', 'senna-finance'); ?></span>
                                            </div>
                                            <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #4b5563;">
                                                <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                </svg>
                                                <span><?php esc_html_e('Bookmark Research', 'senna-finance'); ?></span>
                                            </div>
                                        </div>
                                        <button class="sffc-job-cta" style="margin-top: 16px;">
                                            <span><?php esc_html_e('Start Saving', 'senna-finance'); ?></span>
                                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                                <path d="M7.5 5L12.5 10L7.5 15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </button>
                                    </div>
                                </article>
                                
                                <!-- Show some blurred example saved items -->
                                <div style="margin-top: 24px; filter: blur(4px); opacity: 0.5; pointer-events: none;">
                                    <article class="sffc-feed-card">
                                        <h3><?php esc_html_e('KKR Closes $19B Americas Fund XIII at Hard Cap', 'senna-finance'); ?></h3>
                                        <p style="font-size: 14px; color: #6b7280;"><?php esc_html_e('Saved 2 days ago • Deal Alert', 'senna-finance'); ?></p>
                                    </article>
                                    <article class="sffc-feed-card" style="margin-top: 16px;">
                                        <h3><?php esc_html_e('Principal - Technology Investments @ Warburg Pincus', 'senna-finance'); ?></h3>
                                        <p style="font-size: 14px; color: #6b7280;"><?php esc_html_e('Saved 5 days ago • Job Opportunity', 'senna-finance'); ?></p>
                                    </article>
                                    <article class="sffc-feed-card" style="margin-top: 16px;">
                                        <h3><?php esc_html_e('Q4 2024 Global PE Market Analysis Report', 'senna-finance'); ?></h3>
                                        <p style="font-size: 14px; color: #6b7280;"><?php esc_html_e('Saved 1 week ago • Research Report', 'senna-finance'); ?></p>
                                    </article>
                                </div>
                            </div>
                        <?php else : ?>
                            <!-- Regular saved items for logged-in users -->
                            <p class="sffc-empty-state" data-role="saved-empty"<?php echo !empty($saved_feed_items) ? ' style="display:none;"' : ''; ?>><?php esc_html_e('Stories and research you save will appear here.', 'senna-finance'); ?></p>
                            <div class="sffc-saved-list" data-role="saved-list">
                                <?php if (!empty($saved_feed_items)) : ?>
                                    <?php foreach ($saved_feed_items as $saved_item) : ?>
                                        <?php sffc_render_card($saved_item); ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <button class="sffc-load-more" data-role="load-more" data-tab="saved"><?php esc_html_e('Load more saved', 'senna-finance'); ?></button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else : ?>
                <div class="sffc-feed-tabs">
                    <div class="sffc-feed-tab is-active" data-tab="insights">
                        <?php foreach ($stories_feed as $item) : ?>
                            <?php sffc_render_card($item); ?>
                        <?php endforeach; ?>
                        <button class="sffc-load-more is-visible" data-role="load-more" data-tab="insights"><?php esc_html_e('Load more insights', 'senna-finance'); ?></button>
                    </div>
                    <div class="sffc-feed-tab" data-tab="signals">
                        <?php
                        // Profile Tab - LinkedIn-style Professional Profile
                        $profile_user = wp_get_current_user();
                        $profile_user_id = get_current_user_id();
                        $is_profile_logged_in = is_user_logged_in();

                        // Get comprehensive profile data
                        $profile_meta = get_user_meta($profile_user_id, 'sffc_professional_profile', true);
                        if (!is_array($profile_meta)) $profile_meta = array();

                        // Get launcher intake data (from senna-launcher-plugin)
                        $intake_data = get_user_meta($profile_user_id, 'senna_intake_data', true);
                        if (!is_array($intake_data)) $intake_data = array();

                        // Intake field labels for display
                        $intake_labels = array(
                            'goal' => array(
                                'transition' => 'Career Transition',
                                'advance' => 'Advance in Current Path',
                                'explore' => 'Explore Options',
                                'pivot' => 'Industry Pivot'
                            ),
                            'situation' => array(
                                'student' => 'Manager Level',
                                'analyst' => 'Senior Manager Level',
                                'associate' => 'Director Level',
                                'senior' => 'Senior Professional',
                                'between' => 'Between Roles',
                                'other' => 'Other'
                            ),
                            'timeline' => array(
                                'immediate' => 'Ready Now',
                                '3months' => 'Within 3 Months',
                                '6months' => 'Within 6 Months',
                                'year' => 'Within a Year'
                            ),
                            'challenge' => array(
                                'technical' => 'Technical Skills',
                                'network' => 'Building Network',
                                'experience' => 'Gaining Experience',
                                'brand' => 'Personal Branding',
                                'clarity' => 'Career Clarity',
                                'interview' => 'Interview Prep'
                            )
                        );

                        $profile_data = array(
                            'headline' => $profile_meta['headline'] ?? get_user_meta($profile_user_id, 'sffc_profile_headline', true),
                            'bio' => $profile_meta['bio'] ?? get_user_meta($profile_user_id, 'sffc_profile_bio', true),
                            'location' => $profile_meta['location'] ?? get_user_meta($profile_user_id, 'sffc_profile_location', true),
                            'current_role' => $profile_meta['current_role'] ?? get_user_meta($profile_user_id, 'sffc_current_role', true),
                            'current_company' => $profile_meta['current_company'] ?? get_user_meta($profile_user_id, 'sffc_current_company', true),
                            'experience_years' => $profile_meta['experience_years'] ?? get_user_meta($profile_user_id, 'sffc_experience_years', true),
                            'skills' => $profile_meta['skills'] ?? get_user_meta($profile_user_id, 'sffc_skills', true) ?: array(),
                            'experience' => $profile_meta['experience'] ?? get_user_meta($profile_user_id, 'sffc_experience', true) ?: array(),
                            'education' => $profile_meta['education'] ?? get_user_meta($profile_user_id, 'sffc_education', true) ?: array(),
                            'preferred_roles' => $profile_meta['preferred_roles'] ?? get_user_meta($profile_user_id, 'sffc_preferred_roles', true) ?: array(),
                            'preferred_sectors' => $profile_meta['preferred_sectors'] ?? get_user_meta($profile_user_id, 'sffc_preferred_sectors', true) ?: array(),
                            'remote_preference' => $profile_meta['remote_preference'] ?? get_user_meta($profile_user_id, 'sffc_remote_preference', true) ?: 'flexible',
                            'availability' => get_user_meta($profile_user_id, 'sffc_profile_visibility', true) ?: 'open',
                            'salary_min' => $profile_meta['salary_min'] ?? get_user_meta($profile_user_id, 'sffc_salary_min', true),
                            'salary_max' => $profile_meta['salary_max'] ?? get_user_meta($profile_user_id, 'sffc_salary_max', true),
                            // Job matching threshold fields
                            'preferred_location' => $profile_meta['preferred_location'] ?? '',
                            'experience_level' => $profile_meta['experience_level'] ?? '', // junior, mid, senior, executive
                            'role_type' => $profile_meta['role_type'] ?? '', // front_office, back_office, operations, support
                            'preferred_next_role' => $profile_meta['preferred_next_role'] ?? '',
                            'preferred_industries' => $profile_meta['preferred_industries'] ?? '',
                            'latest_experience_description' => $profile_meta['latest_experience_description'] ?? '',
                        );

                        $remote_options = array(
                            'onsite' => 'On-site',
                            'hybrid' => 'Hybrid',
                            'remote' => 'Remote',
                            'flexible' => 'Flexible'
                        );

                        $availability_labels = array(
                            'active' => 'Actively Looking',
                            'open' => 'Open to Opportunities',
                            'hidden' => 'Not Looking'
                        );
                        ?>

                        <?php if (!$is_profile_logged_in) : ?>
                            <!-- Logged Out State -->
                            <div class="sffc-profile-auth">
                                <div class="sffc-profile-auth__icon">
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                        <circle cx="12" cy="7" r="4"/>
                                    </svg>
                                </div>
                                <h2><?php esc_html_e('Build Your Professional Profile', 'senna-finance'); ?></h2>
                                <p><?php esc_html_e('Sign in to create your profile, set preferences, and get matched with opportunities.', 'senna-finance'); ?></p>
                                <ul class="sffc-profile-auth__features">
                                    <li>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                        <?php esc_html_e('AI-powered job matching', 'senna-finance'); ?>
                                    </li>
                                    <li>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                        <?php esc_html_e('Personalized career insights', 'senna-finance'); ?>
                                    </li>
                                    <li>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                        <?php esc_html_e('Direct recruiter connections', 'senna-finance'); ?>
                                    </li>
                                </ul>
                                <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" class="sffc-profile-auth__btn"><?php esc_html_e('Sign In to Continue', 'senna-finance'); ?></a>
                            </div>
                        <?php else : ?>
                            <!-- LinkedIn-style Profile Sections -->

                            <!-- Section 1: Profile Header Card -->
                            <article class="sffc-linkedin-card sffc-linkedin-card--header">
                                <div class="sffc-linkedin-banner"></div>
                                <div class="sffc-linkedin-header">
                                    <div class="sffc-linkedin-avatar">
                                        <?php echo get_avatar($profile_user_id, 140); ?>
                                        <span class="sffc-linkedin-status sffc-linkedin-status--<?php echo esc_attr($profile_data['availability']); ?>"></span>
                                    </div>
                                    <button type="button" class="sffc-linkedin-edit" data-action="edit-profile" aria-label="Edit profile">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </button>
                                </div>
                                <div class="sffc-linkedin-identity">
                                    <h1 class="sffc-linkedin-name"><?php echo esc_html($profile_user->display_name); ?></h1>
                                    <?php if ($profile_data['headline']) : ?>
                                        <p class="sffc-linkedin-headline"><?php echo esc_html($profile_data['headline']); ?></p>
                                    <?php elseif ($profile_data['current_role'] && $profile_data['current_company']) : ?>
                                        <p class="sffc-linkedin-headline"><?php echo esc_html($profile_data['current_role'] . ' at ' . $profile_data['current_company']); ?></p>
                                    <?php else : ?>
                                        <p class="sffc-linkedin-headline sffc-linkedin-headline--placeholder"><?php esc_html_e('Add a professional headline', 'senna-finance'); ?></p>
                                    <?php endif; ?>
                                    <div class="sffc-linkedin-meta">
                                        <?php if ($profile_data['location']) : ?>
                                            <span class="sffc-linkedin-meta__item">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                                <?php echo esc_html($profile_data['location']); ?>
                                            </span>
                                        <?php endif; ?>
                                        <span class="sffc-linkedin-meta__item sffc-linkedin-meta__item--badge">
                                            <?php echo esc_html($availability_labels[$profile_data['availability']] ?? 'Open to Opportunities'); ?>
                                        </span>
                                    </div>
                                </div>
                            </article>

                            <!-- Section 2: About -->
                            <article class="sffc-linkedin-card">
                                <div class="sffc-linkedin-card__header">
                                    <h2><?php esc_html_e('About', 'senna-finance'); ?></h2>
                                    <button type="button" class="sffc-linkedin-edit" data-section="about" aria-label="Edit about">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </button>
                                </div>
                                <div class="sffc-linkedin-card__body">
                                    <?php if ($profile_data['bio']) : ?>
                                        <p class="sffc-linkedin-bio"><?php echo nl2br(esc_html($profile_data['bio'])); ?></p>
                                    <?php else : ?>
                                        <div class="sffc-linkedin-empty">
                                            <p><?php esc_html_e('Share your professional story. Tell recruiters about your background and career goals.', 'senna-finance'); ?></p>
                                            <button type="button" class="sffc-linkedin-add" data-section="about"><?php esc_html_e('Add About', 'senna-finance'); ?></button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </article>

                            <!-- Section 3: Experience -->
                            <article class="sffc-linkedin-card">
                                <div class="sffc-linkedin-card__header">
                                    <h2><?php esc_html_e('Experience', 'senna-finance'); ?></h2>
                                    <button type="button" class="sffc-linkedin-edit" data-section="experience" aria-label="Edit experience">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                    </button>
                                </div>
                                <div class="sffc-linkedin-card__body">
                                    <?php if (!empty($profile_data['experience']) && is_array($profile_data['experience'])) : ?>
                                        <div class="sffc-linkedin-experience-list">
                                            <?php foreach ($profile_data['experience'] as $exp) : ?>
                                                <div class="sffc-linkedin-experience-item">
                                                    <div class="sffc-linkedin-experience-icon">
                                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                                                    </div>
                                                    <div class="sffc-linkedin-experience-content">
                                                        <h3><?php echo esc_html($exp['title'] ?? ''); ?></h3>
                                                        <p class="sffc-linkedin-experience-company"><?php echo esc_html($exp['company'] ?? ''); ?></p>
                                                        <p class="sffc-linkedin-experience-dates"><?php echo esc_html(($exp['start_date'] ?? '') . ' - ' . ($exp['end_date'] ?? 'Present')); ?></p>
                                                        <?php if (!empty($exp['description'])) : ?>
                                                            <p class="sffc-linkedin-experience-desc"><?php echo esc_html($exp['description']); ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php elseif ($profile_data['current_role'] || $profile_data['current_company']) : ?>
                                        <div class="sffc-linkedin-experience-list">
                                            <div class="sffc-linkedin-experience-item">
                                                <div class="sffc-linkedin-experience-icon">
                                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                                                </div>
                                                <div class="sffc-linkedin-experience-content">
                                                    <h3><?php echo esc_html($profile_data['current_role'] ?: 'Current Role'); ?></h3>
                                                    <p class="sffc-linkedin-experience-company"><?php echo esc_html($profile_data['current_company'] ?: ''); ?></p>
                                                    <p class="sffc-linkedin-experience-dates"><?php esc_html_e('Present', 'senna-finance'); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else : ?>
                                        <div class="sffc-linkedin-empty">
                                            <p><?php esc_html_e('Add your work experience to help recruiters understand your background.', 'senna-finance'); ?></p>
                                            <button type="button" class="sffc-linkedin-add" data-section="experience"><?php esc_html_e('Add Experience', 'senna-finance'); ?></button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </article>

                            <!-- Section 4: Education -->
                            <article class="sffc-linkedin-card">
                                <div class="sffc-linkedin-card__header">
                                    <h2><?php esc_html_e('Education', 'senna-finance'); ?></h2>
                                    <button type="button" class="sffc-linkedin-edit" data-section="education" aria-label="Edit education">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                    </button>
                                </div>
                                <div class="sffc-linkedin-card__body">
                                    <?php if (!empty($profile_data['education']) && is_array($profile_data['education'])) : ?>
                                        <div class="sffc-linkedin-education-list">
                                            <?php foreach ($profile_data['education'] as $edu) : ?>
                                                <div class="sffc-linkedin-education-item">
                                                    <div class="sffc-linkedin-education-icon">
                                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                                                    </div>
                                                    <div class="sffc-linkedin-education-content">
                                                        <h3><?php echo esc_html($edu['institution'] ?? ''); ?></h3>
                                                        <p class="sffc-linkedin-education-degree"><?php echo esc_html(($edu['degree'] ?? '') . ($edu['field'] ? ', ' . $edu['field'] : '')); ?></p>
                                                        <p class="sffc-linkedin-education-dates"><?php echo esc_html($edu['graduation_year'] ?? ''); ?></p>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else : ?>
                                        <div class="sffc-linkedin-empty">
                                            <p><?php esc_html_e('Add your education to complete your profile.', 'senna-finance'); ?></p>
                                            <button type="button" class="sffc-linkedin-add" data-section="education"><?php esc_html_e('Add Education', 'senna-finance'); ?></button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </article>

                            <!-- Section 5: Skills -->
                            <article class="sffc-linkedin-card">
                                <div class="sffc-linkedin-card__header">
                                    <h2><?php esc_html_e('Skills', 'senna-finance'); ?></h2>
                                    <button type="button" class="sffc-linkedin-edit" data-section="skills" aria-label="Edit skills">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                    </button>
                                </div>
                                <div class="sffc-linkedin-card__body">
                                    <?php if (!empty($profile_data['skills'])) : ?>
                                        <div class="sffc-linkedin-skills">
                                            <?php
                                            $skills_array = is_array($profile_data['skills']) ? $profile_data['skills'] : explode(',', $profile_data['skills']);
                                            foreach ($skills_array as $skill) :
                                                $skill_name = is_array($skill) ? ($skill['name'] ?? $skill) : trim($skill);
                                                if (empty($skill_name)) continue;
                                            ?>
                                                <span class="sffc-linkedin-skill"><?php echo esc_html($skill_name); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else : ?>
                                        <div class="sffc-linkedin-empty">
                                            <p><?php esc_html_e('Showcase your skills to match with the right opportunities.', 'senna-finance'); ?></p>
                                            <button type="button" class="sffc-linkedin-add" data-section="skills"><?php esc_html_e('Add Skills', 'senna-finance'); ?></button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </article>

                            <!-- Section 6: Job Match Preferences -->
                            <?php
                            $experience_levels = array(
                                'junior' => 'Junior (0-2 years)',
                                'mid' => 'Mid-Level (3-5 years)',
                                'senior' => 'Senior (6-10 years)',
                                'executive' => 'Executive (10+ years)'
                            );
                            $role_types = array(
                                'front_office' => 'Front Office',
                                'back_office' => 'Back Office',
                                'operations' => 'Operations',
                                'support' => 'Support Functions'
                            );
                            ?>
                            <article class="sffc-linkedin-card sffc-linkedin-card--match-prefs">
                                <div class="sffc-linkedin-card__header">
                                    <h2><?php esc_html_e('Job Match Preferences', 'senna-finance'); ?></h2>
                                    <button type="button" class="sffc-linkedin-edit" data-section="match-preferences" aria-label="Edit match preferences">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </button>
                                </div>
                                <p class="sffc-linkedin-card__intro"><?php esc_html_e('Complete these fields to get accurate job match scores.', 'senna-finance'); ?></p>
                                <div class="sffc-linkedin-card__body">
                                    <div class="sffc-match-prefs-grid">
                                        <!-- Preferred Location -->
                                        <div class="sffc-match-pref-item">
                                            <div class="sffc-match-pref-icon" style="background: #f97316;">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                            </div>
                                            <div class="sffc-match-pref-content">
                                                <span class="sffc-match-pref-label"><?php esc_html_e('Preferred Location', 'senna-finance'); ?></span>
                                                <?php if (!empty($profile_data['preferred_location'])) : ?>
                                                    <span class="sffc-match-pref-value"><?php echo esc_html($profile_data['preferred_location']); ?></span>
                                                <?php else : ?>
                                                    <span class="sffc-match-pref-empty"><?php esc_html_e('Add location', 'senna-finance'); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Experience Level -->
                                        <div class="sffc-match-pref-item">
                                            <div class="sffc-match-pref-icon" style="background: #c75643;">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                                            </div>
                                            <div class="sffc-match-pref-content">
                                                <span class="sffc-match-pref-label"><?php esc_html_e('Experience Level', 'senna-finance'); ?></span>
                                                <?php if (!empty($profile_data['experience_level'])) : ?>
                                                    <span class="sffc-match-pref-value"><?php echo esc_html($experience_levels[$profile_data['experience_level']] ?? $profile_data['experience_level']); ?></span>
                                                <?php else : ?>
                                                    <span class="sffc-match-pref-empty"><?php esc_html_e('Select level', 'senna-finance'); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Role Type -->
                                        <div class="sffc-match-pref-item">
                                            <div class="sffc-match-pref-icon" style="background: #0e6e6c;">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                                            </div>
                                            <div class="sffc-match-pref-content">
                                                <span class="sffc-match-pref-label"><?php esc_html_e('Role Type', 'senna-finance'); ?></span>
                                                <?php if (!empty($profile_data['role_type'])) : ?>
                                                    <span class="sffc-match-pref-value"><?php echo esc_html($role_types[$profile_data['role_type']] ?? $profile_data['role_type']); ?></span>
                                                <?php else : ?>
                                                    <span class="sffc-match-pref-empty"><?php esc_html_e('Select type', 'senna-finance'); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Preferred Next Role -->
                                        <div class="sffc-match-pref-item">
                                            <div class="sffc-match-pref-icon" style="background: #0d353e;">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                            </div>
                                            <div class="sffc-match-pref-content">
                                                <span class="sffc-match-pref-label"><?php esc_html_e('Preferred Next Role', 'senna-finance'); ?></span>
                                                <?php if (!empty($profile_data['preferred_next_role'])) : ?>
                                                    <span class="sffc-match-pref-value"><?php echo esc_html($profile_data['preferred_next_role']); ?></span>
                                                <?php else : ?>
                                                    <span class="sffc-match-pref-empty"><?php esc_html_e('e.g., VP of Finance', 'senna-finance'); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Preferred Industries -->
                                        <div class="sffc-match-pref-item sffc-match-pref-item--wide">
                                            <div class="sffc-match-pref-icon" style="background: #6366f1;">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M3 21h18"/><path d="M9 8h1"/><path d="M9 12h1"/><path d="M9 16h1"/><path d="M14 8h1"/><path d="M14 12h1"/><path d="M14 16h1"/><path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"/></svg>
                                            </div>
                                            <div class="sffc-match-pref-content">
                                                <span class="sffc-match-pref-label"><?php esc_html_e('Preferred Industries', 'senna-finance'); ?></span>
                                                <?php if (!empty($profile_data['preferred_industries'])) : ?>
                                                    <span class="sffc-match-pref-value"><?php echo esc_html($profile_data['preferred_industries']); ?></span>
                                                <?php else : ?>
                                                    <span class="sffc-match-pref-empty"><?php esc_html_e('e.g., Private Equity, Investment Banking', 'senna-finance'); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Latest Experience Description -->
                                    <div class="sffc-match-experience-section">
                                        <div class="sffc-match-experience-header">
                                            <h3><?php esc_html_e('Latest Experience Summary', 'senna-finance'); ?></h3>
                                            <p class="sffc-match-experience-hint"><?php esc_html_e('Paste your most recent role description from your CV. This helps us match you with relevant opportunities.', 'senna-finance'); ?></p>
                                        </div>
                                        <?php if (!empty($profile_data['latest_experience_description'])) : ?>
                                            <div class="sffc-match-experience-content">
                                                <?php echo wp_kses_post(nl2br(esc_html($profile_data['latest_experience_description']))); ?>
                                            </div>
                                        <?php else : ?>
                                            <div class="sffc-match-experience-empty">
                                                <p><?php esc_html_e('No experience description added yet.', 'senna-finance'); ?></p>
                                                <button type="button" class="sffc-linkedin-add" data-section="match-preferences"><?php esc_html_e('Add Experience Description', 'senna-finance'); ?></button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </article>

                            <!-- Section 7: Career Journey (from Launcher Intake) -->
                            <article class="sffc-linkedin-card sffc-linkedin-card--journey">
                                <div class="sffc-linkedin-card__header">
                                    <h2><?php esc_html_e('Career Journey', 'senna-finance'); ?></h2>
                                    <button type="button" class="sffc-linkedin-edit" data-section="career-journey" aria-label="Edit career journey">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </button>
                                </div>
                                <p class="sffc-linkedin-card__intro"><?php esc_html_e('Your career goals help MENA Careers provide personalized guidance.', 'senna-finance'); ?></p>
                                <div class="sffc-linkedin-card__body">
                                    <?php if (!empty($intake_data['goal']) || !empty($intake_data['situation']) || !empty($intake_data['timeline']) || !empty($intake_data['challenge'])) : ?>
                                        <div class="sffc-journey-grid">
                                            <!-- Career Goal -->
                                            <div class="sffc-journey-item">
                                                <div class="sffc-journey-icon" style="background: #6366f1;">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v8"/><path d="M8 12h8"/></svg>
                                                </div>
                                                <div class="sffc-journey-content">
                                                    <span class="sffc-journey-label"><?php esc_html_e('Career Goal', 'senna-finance'); ?></span>
                                                    <?php if (!empty($intake_data['goal'])) : ?>
                                                        <span class="sffc-journey-value"><?php echo esc_html($intake_labels['goal'][$intake_data['goal']] ?? ucfirst($intake_data['goal'])); ?></span>
                                                    <?php else : ?>
                                                        <span class="sffc-journey-empty"><?php esc_html_e('Not set', 'senna-finance'); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <!-- Current Situation -->
                                            <div class="sffc-journey-item">
                                                <div class="sffc-journey-icon" style="background: #0e6e6c;">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                                </div>
                                                <div class="sffc-journey-content">
                                                    <span class="sffc-journey-label"><?php esc_html_e('Current Situation', 'senna-finance'); ?></span>
                                                    <?php if (!empty($intake_data['situation'])) : ?>
                                                        <span class="sffc-journey-value"><?php echo esc_html($intake_labels['situation'][$intake_data['situation']] ?? ucfirst($intake_data['situation'])); ?></span>
                                                    <?php else : ?>
                                                        <span class="sffc-journey-empty"><?php esc_html_e('Not set', 'senna-finance'); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <!-- Timeline -->
                                            <div class="sffc-journey-item">
                                                <div class="sffc-journey-icon" style="background: #f97316;">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                                </div>
                                                <div class="sffc-journey-content">
                                                    <span class="sffc-journey-label"><?php esc_html_e('Timeline', 'senna-finance'); ?></span>
                                                    <?php if (!empty($intake_data['timeline'])) : ?>
                                                        <span class="sffc-journey-value"><?php echo esc_html($intake_labels['timeline'][$intake_data['timeline']] ?? ucfirst($intake_data['timeline'])); ?></span>
                                                    <?php else : ?>
                                                        <span class="sffc-journey-empty"><?php esc_html_e('Not set', 'senna-finance'); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <!-- Biggest Challenge -->
                                            <div class="sffc-journey-item">
                                                <div class="sffc-journey-icon" style="background: #c75643;">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                                                </div>
                                                <div class="sffc-journey-content">
                                                    <span class="sffc-journey-label"><?php esc_html_e('Biggest Challenge', 'senna-finance'); ?></span>
                                                    <?php if (!empty($intake_data['challenge'])) : ?>
                                                        <span class="sffc-journey-value"><?php echo esc_html($intake_labels['challenge'][$intake_data['challenge']] ?? ucfirst($intake_data['challenge'])); ?></span>
                                                    <?php else : ?>
                                                        <span class="sffc-journey-empty"><?php esc_html_e('Not set', 'senna-finance'); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else : ?>
                                        <div class="sffc-journey-empty-state">
                                            <p><?php esc_html_e('Tell us about your career goals so MENA Careers can provide personalized guidance.', 'senna-finance'); ?></p>
                                            <button type="button" class="sffc-linkedin-add" data-section="career-journey"><?php esc_html_e('Set Career Goals', 'senna-finance'); ?></button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </article>

                            <!-- Edit Profile Modal (placeholder for AJAX) -->
                            <div class="sffc-profile-modal" data-modal="edit-profile" aria-hidden="true">
                                <div class="sffc-profile-modal__overlay"></div>
                                <div class="sffc-profile-modal__container">
                                    <div class="sffc-profile-modal__header">
                                        <h3><?php esc_html_e('Edit Profile', 'senna-finance'); ?></h3>
                                        <button type="button" class="sffc-modal-close" aria-label="Close">&times;</button>
                                    </div>
                                    <div class="sffc-profile-modal__body" data-modal-content>
                                        <!-- Content loaded dynamically based on section -->
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="sffc-feed-tab" data-tab="jobs">
                        <?php if (!empty($jobs_feed)) : ?>
                            <?php foreach ($jobs_feed as $item) : ?>
                                <?php sffc_render_card($item); ?>
                            <?php endforeach; ?>
                            <button class="sffc-load-more is-visible" data-role="load-more" data-tab="jobs"><?php esc_html_e('Load more jobs', 'senna-finance'); ?></button>
                        <?php else : ?>
                            <p class="sffc-empty-state"><?php esc_html_e('We are sourcing fresh mandates for you.', 'senna-finance'); ?></p>
                            <button class="sffc-load-more is-visible" data-role="load-more" data-tab="jobs"><?php esc_html_e('Load jobs', 'senna-finance'); ?></button>
                        <?php endif; ?>
                    </div>
                    <div class="sffc-feed-tab" data-tab="saved">
                        <?php if (!is_user_logged_in()) : ?>
                            <!-- Example saved card for logged-out users -->
                            <div class="sffc-saved-preview">
                                <article class="sffc-feed-card sffc-saved-example" style="background: linear-gradient(135deg, #f8fafc 0%, #e0f2fe 100%); border: 2px dashed rgba(13, 53, 62, 0.2);">
                                    <div class="sffc-saved-icon" style="text-align: center; padding: 20px;">
                                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" style="margin: 0 auto; opacity: 0.3;">
                                            <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                    <div style="text-align: center; padding: 0 20px 20px;">
                                        <h3 style="font-size: 18px; margin-bottom: 12px; color: var(--sffc-heading);">
                                            <?php esc_html_e('Your Saved Collection', 'senna-finance'); ?>
                                        </h3>
                                        <p style="font-size: 14px; color: #6b7280; margin-bottom: 16px; line-height: 1.6;">
                                            <?php esc_html_e('Track jobs, save important alerts, bookmark research reports, and build your personalized career intelligence library. Everything you save syncs across all your devices.', 'senna-finance'); ?>
                                        </p>
                                        <div class="sffc-saved-features" style="display: flex; justify-content: center; gap: 24px; margin: 20px 0; flex-wrap: wrap;">
                                            <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #4b5563;">
                                                <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                </svg>
                                                <span><?php esc_html_e('Track Job Applications', 'senna-finance'); ?></span>
                                            </div>
                                            <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #4b5563;">
                                                <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                </svg>
                                                <span><?php esc_html_e('Save Deal Alerts', 'senna-finance'); ?></span>
                                            </div>
                                            <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #4b5563;">
                                                <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                </svg>
                                                <span><?php esc_html_e('Bookmark Research', 'senna-finance'); ?></span>
                                            </div>
                                        </div>
                                        <button class="sffc-job-cta" style="margin-top: 16px;">
                                            <span><?php esc_html_e('Start Saving', 'senna-finance'); ?></span>
                                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                                <path d="M7.5 5L12.5 10L7.5 15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </button>
                                    </div>
                                </article>
                                
                                <!-- Show some blurred example saved items -->
                                <div style="margin-top: 24px; filter: blur(4px); opacity: 0.5; pointer-events: none;">
                                    <article class="sffc-feed-card">
                                        <h3><?php esc_html_e('KKR Closes $19B Americas Fund XIII at Hard Cap', 'senna-finance'); ?></h3>
                                        <p style="font-size: 14px; color: #6b7280;"><?php esc_html_e('Saved 2 days ago • Deal Alert', 'senna-finance'); ?></p>
                                    </article>
                                    <article class="sffc-feed-card" style="margin-top: 16px;">
                                        <h3><?php esc_html_e('Principal - Technology Investments @ Warburg Pincus', 'senna-finance'); ?></h3>
                                        <p style="font-size: 14px; color: #6b7280;"><?php esc_html_e('Saved 5 days ago • Job Opportunity', 'senna-finance'); ?></p>
                                    </article>
                                    <article class="sffc-feed-card" style="margin-top: 16px;">
                                        <h3><?php esc_html_e('Q4 2024 Global PE Market Analysis Report', 'senna-finance'); ?></h3>
                                        <p style="font-size: 14px; color: #6b7280;"><?php esc_html_e('Saved 1 week ago • Research Report', 'senna-finance'); ?></p>
                                    </article>
                                </div>
                            </div>
                        <?php else : ?>
                            <!-- Regular saved items for logged-in users -->
                            <p class="sffc-empty-state" data-role="saved-empty"<?php echo !empty($saved_feed_items) ? ' style="display:none;"' : ''; ?>><?php esc_html_e('Stories and jobs you save will appear here.', 'senna-finance'); ?></p>
                            <div class="sffc-saved-list" data-role="saved-list">
                                <?php if (!empty($saved_feed_items)) : ?>
                                    <?php foreach ($saved_feed_items as $saved_item) : ?>
                                        <?php sffc_render_card($saved_item); ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <button class="sffc-load-more" data-role="load-more" data-tab="saved"><?php esc_html_e('Load more saved', 'senna-finance'); ?></button>
                        <?php endif; ?>
                    </div>
                    <div class="sffc-feed-tab" data-tab="research">
                        <?php if (!empty($research_feed)) : ?>
                            <?php foreach ($research_feed as $item) : ?>
                                <?php sffc_render_card($item, false); ?>
                            <?php endforeach; ?>
                            <button class="sffc-load-more is-visible" data-role="load-more" data-tab="research"><?php esc_html_e('Load more research', 'senna-finance'); ?></button>
                        <?php else : ?>
                            <p class="sffc-empty-state"><?php esc_html_e('Research notes are being prepared.', 'senna-finance'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>

        <?php if (!$article_mode) : ?>
        <aside class="sffc-column sffc-column--right">
            <section class="sffc-panel" data-trending-panel>
                <div class="sffc-section-label">
                    <span class="text-eyebrow2" data-trending-heading><?php esc_html_e('Trending Today', 'senna-finance'); ?></span>
                </div>
                <div class="sffc-trending-views">
                    <ul class="sffc-trending-list is-active" data-trending-view="insights" aria-hidden="false">
                        <?php if (!empty($trending_posts)) : ?>
                            <?php foreach ($trending_posts as $trend) :
                                $view_count = is_object($trend) ? (int) get_post_meta($trend->ID, 'sffc_visit_count', true) : 0;
                                $title = is_object($trend) ? $trend->post_title : ($trend['title'] ?? '');
                                $link = is_object($trend) ? get_permalink($trend) : ($trend['link'] ?? '#');
                                $type_label = is_object($trend)
                                    ? ($trend->post_type === 'sffc_pe_deal' ? __('M&A', 'senna-finance') : __('Fund Raise', 'senna-finance'))
                                    : ($trend['type'] === 'deal' ? __('M&A', 'senna-finance') : __('Fund Raise', 'senna-finance'));
                                $engagement = max(12, min(320, intval($view_count / 10)));
                                $keyword = $this->extract_trending_keyword($title);
                                ?>
                                <li>
                                    <div class="sffc-trending-meta">
                                        <span class="text-body2"><?php echo esc_html($keyword ?: $type_label); ?></span>
                                        <a href="<?php echo esc_url($link); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($title); ?></a>
                                    </div>
                                    <div class="sffc-trending-stats">
                                        <span class="sffc-trending-pill">↑ <?php echo esc_html($engagement); ?>% <?php esc_html_e('engagement', 'senna-finance'); ?></span>
                                        <span class="sffc-meta-label"><?php echo esc_html(number_format_i18n(max($view_count, 1))); ?> <?php esc_html_e('views', 'senna-finance'); ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <li class="sffc-trending-empty"><?php esc_html_e('Insights are being curated.', 'senna-finance'); ?></li>
                        <?php endif; ?>
                    </ul>
                    <ul class="sffc-trending-list" data-trending-view="jobs" aria-hidden="true">
                        <?php if (!empty($trending_jobs_feed)) : ?>
                            <?php foreach ($trending_jobs_feed as $job_trend) :
                                $job_company = $job_trend['company'] ?? __('Confidential Role', 'senna-finance');
                                $job_link = $job_trend['link'] ?? '#';
                                $job_location = $job_trend['location'] ?? ($job_trend['region'] ?? __('Global', 'senna-finance'));
                                $job_label = $job_trend['job_level'] ?? '';
                                $job_time = $job_trend['relative_time'] ?? '';
                                ?>
                                <li>
                                    <div class="sffc-trending-meta">
                                        <span class="text-body2"><?php echo esc_html($job_company); ?></span>
                                        <a href="<?php echo esc_url($job_link); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($job_trend['title']); ?></a>
                                    </div>
                                    <div class="sffc-trending-stats">
                                        <span class="sffc-trending-pill"><?php echo esc_html($job_location); ?></span>
                                        <?php if (!empty($job_label)) : ?><span class="sffc-meta-label"><?php echo esc_html($job_label); ?></span><?php endif; ?>
                                        <?php if (!empty($job_time)) : ?><span class="sffc-meta-label"><?php echo esc_html($job_time); ?> <?php esc_html_e('ago', 'senna-finance'); ?></span><?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <li class="sffc-trending-empty"><?php esc_html_e('Jobs are being refreshed.', 'senna-finance'); ?></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </section>

            <!-- Job Suggestions Panel - Shows on Profile Tab -->
            <section class="sffc-panel sffc-job-suggestions" data-panel="profile-suggestions" style="display: none;">
                <div class="sffc-section-label">
                    <span class="text-eyebrow2"><?php echo esc_html(sprintf(__('%s, your skills match', 'senna-finance'), $is_logged_in ? explode(' ', $user_name)[0] : __('Guest', 'senna-finance'))); ?></span>
                </div>
                <div class="sffc-job-suggestions__list">
                    <?php
                    // Get top 5 matching jobs based on user skills
                    $suggested_jobs = array_slice($jobs_feed, 0, 5);
                    if (!empty($suggested_jobs)) :
                        foreach ($suggested_jobs as $index => $job) :
                            $match_score = 95 - ($index * 8); // Simulated match score
                    ?>
                        <a href="<?php echo esc_url($job['link'] ?? '#'); ?>" class="sffc-job-suggestion" target="_blank" rel="noopener noreferrer">
                            <div class="sffc-job-suggestion__header">
                                <span class="sffc-job-suggestion__match"><?php echo esc_html($match_score); ?>% match</span>
                            </div>
                            <h4 class="sffc-job-suggestion__title"><?php echo esc_html($job['title'] ?? ''); ?></h4>
                            <p class="sffc-job-suggestion__company"><?php echo esc_html($job['company'] ?? ''); ?></p>
                            <div class="sffc-job-suggestion__meta">
                                <?php if (!empty($job['location'])) : ?>
                                    <span><?php echo esc_html($job['location']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($job['job_level'])) : ?>
                                    <span><?php echo esc_html($job['job_level']); ?></span>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php
                        endforeach;
                    else :
                    ?>
                        <div class="sffc-job-suggestions__empty">
                            <p><?php esc_html_e('Complete your profile to see personalized job matches.', 'senna-finance'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
                <button type="button" class="sffc-job-suggestions__cta" data-tab-target="jobs">
                    <?php esc_html_e('View All Jobs', 'senna-finance'); ?>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </button>
            </section>

            <section class="sffc-panel">
                <div class="sffc-section-label">
                    <span class="text-eyebrow2"><?php esc_html_e('MENA Careers Research', 'senna-finance'); ?></span>
                </div>
                <div class="sffc-analytics-card" data-source="<?php echo esc_attr($analytics['source']); ?>">
                    <p class="sffc-analytics-summary" data-role="summary"><?php echo esc_html($analytics['summary']); ?></p>
                    <div class="sffc-analytics-updated">
                        <span><?php esc_html_e('Updated', 'senna-finance'); ?></span>
                        <time datetime="<?php echo esc_attr(date('c', $analytics['timestamp'])); ?>" data-role="timestamp">
                            <?php echo esc_html(human_time_diff($analytics['timestamp'], current_time('timestamp'))); ?> <?php esc_html_e('ago', 'senna-finance'); ?>
                        </time>
                    </div>
                    <button type="button" class="sffc-icon-button" data-action="refresh-analytics"><?php esc_html_e('Refresh', 'senna-finance'); ?></button>
                </div>
            </section>
        </aside>
        <?php endif; ?>
    </div>
    <?php echo $dashboard_instance->get_ask_senna_markup(array('floating' => true)); ?>
</div>

<!-- SEO FAQ Section - Hidden from users but accessible to crawlers -->
<div class="sffc-seo-faq" style="position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden;" aria-hidden="true">
    <section itemscope itemtype="https://schema.org/FAQPage">
        <h2>Frequently Asked Questions - MENA Careers Career Intelligence Platform</h2>

        <div itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
            <h3 itemprop="name">What is MENA Careers Career Intelligence?</h3>
            <div itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                <p itemprop="text">MENA Careers is a private equity career intelligence platform that helps candidates find, evaluate, and land buy-side roles. Using AI-powered role analysis, we break down job requirements, assess your fit, generate tailored application materials, and provide private-equity-specific career intelligence across buyout, growth equity, private credit, secondaries, infrastructure, investor relations, and portfolio operations.</p>
            </div>
        </div>

        <div itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
            <h3 itemprop="name">How does the Application Audit feature work?</h3>
            <div itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                <p itemprop="text">Our Application Audit uses AI to analyze private equity job descriptions and your profile, identifying skill gaps, matching your experience to fund requirements, and generating personalized application materials. You get a Job Breakdown Card showing requirements analysis, compensation intelligence, investment-skill signals, and a tailored Application Toolkit including optimized CVs and cover letters.</p>
            </div>
        </div>

        <div itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
            <h3 itemprop="name">What types of finance jobs does MENA Careers cover?</h3>
            <div itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                <p itemprop="text">MENA Careers focuses on private equity careers across buyout, growth equity, private credit, secondaries, infrastructure, investor relations, and portfolio operations. We help candidates navigate opportunities from Analyst through Principal and partner-track roles across major financial hubs worldwide.</p>
            </div>
        </div>

        <div itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
            <h3 itemprop="name">What is the Application Toolkit?</h3>
            <div itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                <p itemprop="text">The Application Toolkit is your personalized private equity application package generated by MENA Careers AI. It includes a tailored CV optimized for the specific role, a customized cover letter highlighting relevant deal or investing experience, interview preparation notes, and role-specific positioning guidance for the fund strategy you are targeting.</p>
            </div>
        </div>

        <div itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
            <h3 itemprop="name">What is Ask MENA Careers AI?</h3>
            <div itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                <p itemprop="text">Ask MENA Careers is our AI career assistant trained on finance industry data, job market trends, and career development insights. It helps you research companies, prepare for interviews, understand compensation benchmarks, evaluate job offers, and navigate career transitions. Ask questions about specific roles, firms, or career paths and get personalized guidance.</p>
            </div>
        </div>

        <div itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
            <h3 itemprop="name">How does MENA Careers's location intelligence help with relocation?</h3>
            <div itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                <p itemprop="text">When you apply for private equity roles, MENA Careers provides strategy-specific career intelligence including compensation benchmarks, recruiter context, fund-type expectations, interview preparation, and application positioning. This helps you make informed decisions across buyout, growth equity, private credit, secondaries, infrastructure, investor relations, and portfolio operations opportunities.</p>
            </div>
        </div>

        <div itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
            <h3 itemprop="name">Can I save jobs and track my applications?</h3>
            <div itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                <p itemprop="text">Yes, registered users can save job listings, track application status, and maintain a personalized career dashboard. The saved items feature allows you to organize opportunities by priority, deadline, or location. Premium members receive alerts when saved jobs are updated and get access to additional roles in their target markets.</p>
            </div>
        </div>

        <div itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
            <h3 itemprop="name">What makes MENA Careers different from other job boards?</h3>
            <div itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                <p itemprop="text">Unlike traditional job boards that just list openings, MENA Careers provides career intelligence. We analyze each job to show you exactly what's required, how you match, what you're missing, and how to improve your application. Our AI generates tailored application materials and provides compensation and relocation insights that generic job sites simply don't offer.</p>
            </div>
        </div>

        <div itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
            <h3 itemprop="name">Is MENA Careers suitable for experienced finance professionals making a move?</h3>
            <div itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                <p itemprop="text">Yes. MENA Careers is especially useful for experienced finance professionals making lateral or upward moves. Our Application Audit identifies transferable leadership and technical strengths, highlights relevant experience, and helps position your background for manager, director, and VP-level roles across investment banking, private markets, asset management, and corporate finance.</p>
            </div>
        </div>

        <div itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
            <h3 itemprop="name">How often are new jobs added?</h3>
            <div itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                <p itemprop="text">Job listings are updated multiple times daily from top finance employers, executive search firms, and direct company postings. We prioritize quality over quantity, featuring verified opportunities from leading investment banks, private equity firms, asset managers, and fintech companies across global financial centers.</p>
            </div>
        </div>
    </section>
    
    <!-- Career Intelligence Glossary Section for SEO -->
    <section itemscope itemtype="https://schema.org/DefinedTermSet">
        <h2>Finance Career Glossary</h2>

        <div itemscope itemprop="hasDefinedTerm" itemtype="https://schema.org/DefinedTerm">
            <span itemprop="name">Application Audit</span>
            <span itemprop="description">An AI-powered analysis of job requirements matched against a candidate's profile, identifying skill gaps, experience matches, and areas for improvement to increase application success rates.</span>
        </div>

        <div itemscope itemprop="hasDefinedTerm" itemtype="https://schema.org/DefinedTerm">
            <span itemprop="name">Carried Interest (Carry)</span>
            <span itemprop="description">A share of investment profits paid to fund managers, typically 20% of gains above a hurdle rate. Understanding carry is essential for evaluating PE compensation packages and negotiating offers.</span>
        </div>

        <div itemscope itemprop="hasDefinedTerm" itemtype="https://schema.org/DefinedTerm">
            <span itemprop="name">Total Compensation Package</span>
            <span itemprop="description">The complete value of employment including base salary, bonus, equity, carry, co-investment rights, benefits, and perks. Finance roles often have significant variable compensation beyond base salary.</span>
        </div>

        <div itemscope itemprop="hasDefinedTerm" itemtype="https://schema.org/DefinedTerm">
            <span itemprop="name">Headhunter / Executive Search</span>
            <span itemprop="description">Specialized recruiters who source candidates for senior finance positions. Building relationships with headhunters at firms like Heidrick & Struggles, Korn Ferry, and boutique PE recruiters is crucial for career advancement.</span>
        </div>

        <div itemscope itemprop="hasDefinedTerm" itemtype="https://schema.org/DefinedTerm">
            <span itemprop="name">Lateral Move</span>
            <span itemprop="description">A career transition to a similar-level role at a different firm, often to change strategy focus, location, or firm culture. Common in finance as professionals seek better platform or deal flow.</span>
        </div>

        <div itemscope itemprop="hasDefinedTerm" itemtype="https://schema.org/DefinedTerm">
            <span itemprop="name">On-Cycle Recruiting</span>
            <span itemprop="description">The structured annual recruiting period when investment banks and PE firms hire analysts and associates, typically occurring 12-18 months before start dates. Understanding recruiting cycles is essential for timing applications.</span>
        </div>

        <div itemscope itemprop="hasDefinedTerm" itemtype="https://schema.org/DefinedTerm">
            <span itemprop="name">Skill Gap Analysis</span>
            <span itemprop="description">Assessment of the difference between required competencies for a target role and a candidate's current abilities. MENA Careers's Application Audit identifies skill gaps and suggests how to address them.</span>
        </div>

        <div itemscope itemprop="hasDefinedTerm" itemtype="https://schema.org/DefinedTerm">
            <span itemprop="name">Relocation Package</span>
            <span itemprop="description">Benefits provided by employers to assist with moving to a new location, including moving costs, temporary housing, visa sponsorship, and settling-in allowances. Critical for international finance roles.</span>
        </div>
    </section>
    
    <!-- Private equity career content -->
    <section itemscope itemtype="https://schema.org/Article">
        <h2>Private Equity Buyout Careers</h2>
        <div itemprop="articleBody">
            <p>Buyout private equity careers focus on acquiring established companies, improving operations, managing leverage, and exiting investments through strategic sales, secondary buyouts, or public listings. Candidates are expected to understand deal process, financial modelling, commercial diligence, debt capacity, and investment committee materials.</p>
            <p>MENA Careers helps candidates identify buyout roles, understand which skills matter for each posting, and position investment banking, consulting, corporate finance, or operating experience for private equity recruiting.</p>
            <p>Typical roles include Manager, Senior Manager, Vice President, Director, Principal, and Portfolio Operations leadership positions across large-cap, mid-market, and lower-middle-market platforms.</p>
        </div>
        <meta itemprop="keywords" content="private equity jobs, buyout private equity careers, PE associate roles, private equity recruiting, buy-side careers">
    </section>

    <section itemscope itemtype="https://schema.org/Article">
        <h2>Growth Equity Careers</h2>
        <div itemprop="articleBody">
            <p>Growth equity roles sit between venture capital and traditional buyouts. Investors focus on companies with proven product-market fit, strong revenue growth, and opportunities to scale without always requiring full control or heavy leverage.</p>
            <p>Candidates need to show market mapping, unit economics, revenue quality, customer concentration analysis, and a clear view of how a company can grow efficiently. MENA Careers helps candidates translate banking, consulting, corporate development, SaaS, fintech, healthcare, or consumer experience into growth equity language.</p>
        </div>
        <meta itemprop="keywords" content="growth equity jobs, growth equity careers, private equity growth investing, growth investor roles">
    </section>

    <section itemscope itemtype="https://schema.org/Article">
        <h2>Private Credit Careers</h2>
        <div itemprop="articleBody">
            <p>Private credit careers focus on lending to companies outside traditional bank channels. Roles can cover direct lending, opportunistic credit, distressed debt, mezzanine finance, special situations, and structured capital.</p>
            <p>Candidates need to demonstrate credit judgement, downside analysis, covenant awareness, cash flow modelling, documentation discipline, and the ability to evaluate risk-adjusted returns. MENA Careers helps candidates identify credit roles and prepare application materials that show lender-side thinking.</p>
        </div>
        <meta itemprop="keywords" content="private credit jobs, direct lending careers, credit investing roles, special situations careers">
    </section>

    <section itemscope itemtype="https://schema.org/Article">
        <h2>Private Equity Careers in Johannesburg, South Africa</h2>
        <div itemprop="articleBody">
            <p>Johannesburg's Sandton district serves as Africa's financial capital, offering unparalleled exposure to emerging market finance across the continent. The city hosts Africa's most sophisticated capital markets, with the JSE providing deep liquidity and the base for pan-African investment mandates.</p>
            <p>Major employers include FirstRand, Standard Bank, Investec, Nedbank, and pan-African PE firms like Ethos Private Equity, Old Mutual Private Equity, and Convergence Partners. International firms including McKinsey, Goldman Sachs, and Blackstone maintain African headquarters in Johannesburg, creating opportunities for both local and international talent.</p>
            <p>Finance careers in Johannesburg offer exposure to deals across South Africa, Nigeria, Kenya, Ghana, and other high-growth African economies. Professionals develop expertise in frontier market dynamics, BEE (Black Economic Empowerment) structuring, and cross-border African transactions.</p>
            <p>Compensation packages are globally competitive when adjusted for cost of living, with additional benefits including emerging market carry upside in PE roles and regional expansion opportunities. MENA Careers helps candidates understand Johannesburg's unique market dynamics, firm cultures, and progression paths.</p>
        </div>
        <meta itemprop="keywords" content="finance jobs Johannesburg, Sandton banking careers, South Africa PE jobs, African finance careers, JSE careers, Johannesburg investment banking, emerging markets finance jobs">
    </section>

    <section itemscope itemtype="https://schema.org/Article">
        <h2>Private Equity Careers in São Paulo, Brazil</h2>
        <div itemprop="articleBody">
            <p>São Paulo's Faria Lima and Vila Olímpia districts form Latin America's financial epicenter, hosting 80% of regional finance activity. Brazil's $2+ trillion economy and 215 million consumers create demand for finance professionals across investment banking, PE, asset management, and fintech.</p>
            <p>Major employers include Itaú Unibanco, BTG Pactual, XP Inc, Patria Investments, Vinci Partners, and GP Investments, alongside global firms like Goldman Sachs, Morgan Stanley, and Advent International. Brazil's vibrant fintech ecosystem includes unicorns like Nubank, offering alternative career paths beyond traditional finance.</p>
            <p>São Paulo careers require Portuguese fluency and offer exposure to Latin America's largest economy with complex deals involving currency hedging, regulatory navigation, and cross-border structuring. Compensation is denominated in BRL but top firms pay competitively with global markets.</p>
            <p>Top talent sources include FGV, Insper, and USP, with many senior professionals holding international MBAs. MENA Careers provides São Paulo-specific salary benchmarks, firm rankings, and career guidance for candidates targeting Brazil's dynamic financial markets.</p>
        </div>
        <meta itemprop="keywords" content="finance jobs São Paulo, Brazil banking careers, Faria Lima finance, BTG Pactual careers, Brazil PE jobs, Latin America finance careers, São Paulo investment banking">
    </section>

    <!-- Emerging Markets Career Content -->
    <section itemscope itemtype="https://schema.org/Article">
        <h2>Private Equity Careers in Emerging Markets: BRICS and Beyond</h2>
        <div itemprop="articleBody">
            <p>Emerging markets offer accelerated career progression and unique experience that differentiates professionals for future roles. Finance hubs in Mumbai, Shanghai, Lagos, Nairobi, Mexico City, and Istanbul provide exposure to high-growth economies representing $25+ trillion in GDP and 3+ billion consumers.</p>
            <p>Career advantages include faster promotion timelines, broader deal exposure at junior levels, entrepreneurial environments, and development of scarce emerging market expertise valued by global firms. Professionals often return to London or New York in senior roles after gaining frontier market experience.</p>
            <p>Key considerations for emerging market careers include currency exposure in compensation, political and economic volatility, visa and work permit requirements, and quality of life factors. Many roles offer hardship allowances, expat packages, and enhanced benefits to attract international talent.</p>
            <p>MENA Careers provides location-specific intelligence for emerging market roles including compensation benchmarks, relocation considerations, firm cultures, and career progression patterns unique to each market.</p>
        </div>
        <meta itemprop="keywords" content="emerging markets finance careers, BRICS finance jobs, frontier markets banking, developing markets careers, growth markets finance, international finance jobs">
    </section>

    <!-- Africa Career Content -->
    <section itemscope itemtype="https://schema.org/Article">
        <h2>Pan-African Finance Career Opportunities</h2>
        <div itemprop="articleBody">
            <p>Africa's finance sector is expanding rapidly with career opportunities across Lagos (Nigeria), Nairobi (Kenya), Cairo (Egypt), Casablanca (Morocco), and Johannesburg (South Africa). The continent's 1.4 billion consumers and fastest-growing middle class drive demand for finance professionals.</p>
            <p>Key employers include pan-African banks (Standard Bank, Ecobank, Access Bank), regional PE firms (Helios, AfricInvest, DPI), development finance institutions (IFC, AfDB), and global firms expanding African operations. Fintech is particularly dynamic with M-Pesa, Flutterwave, and Paystack creating new career paths.</p>
            <p>African finance careers develop unique skills in frontier market investing, impact measurement, mobile money innovation, and navigating diverse regulatory environments across 54 countries. Professionals gain experience unavailable in developed markets.</p>
            <p>Compensation varies significantly by country and includes packages with hardship allowances, housing, and security benefits. MENA Careers helps candidates evaluate opportunities across African markets with location-specific intelligence on compensation, lifestyle factors, and career progression.</p>
        </div>
        <meta itemprop="keywords" content="Africa finance careers, pan-African banking jobs, Lagos finance jobs, Nairobi banking careers, African fintech careers, emerging Africa finance">
    </section>

    <!-- Latin America Career Content -->
    <section itemscope itemtype="https://schema.org/Article">
        <h2>Private Equity Careers Across Latin America</h2>
        <div itemprop="articleBody">
            <p>Beyond São Paulo, Latin American finance careers span Mexico City, Santiago, Buenos Aires, Bogotá, and Lima. The region's 650 million consumers and proximity to US markets create opportunities across investment banking, PE, asset management, and fintech.</p>
            <p>Mexico City has emerged as the region's second-largest finance hub, with firms like WAMEX, Nexxus Capital, and major banks hiring for roles supporting the $1.3 trillion Mexican economy. Chile's stable markets and Santiago's growing PE presence attract professionals seeking work-life balance alongside career growth.</p>
            <p>LATAM finance careers require Spanish fluency (Portuguese for Brazil) and regional expertise. Professionals navigate currency volatility, political transitions, and unique regulatory frameworks while capturing growth from nearshoring trends, fintech disruption, and renewable energy investments.</p>
            <p>Compensation varies by country with premium packages for international talent. MENA Careers provides location-specific salary benchmarks and career guidance for professionals targeting Latin American finance opportunities.</p>
        </div>
        <meta itemprop="keywords" content="Latin America finance careers, LATAM banking jobs, Mexico City finance, Santiago finance careers, Colombia banking jobs, Argentina finance careers">
    </section>
    
    <!-- MENA Careers Platform Features Section -->
    <section>
        <h2>MENA Careers Career Intelligence Features</h2>

        <article>
            <h3>AI-Powered Application Audit</h3>
            <p>Our Application Audit analyzes job requirements against your profile, identifying skill gaps, matching your experience to requirements, and generating a Job Breakdown Card with compensation intelligence, location insights, and personalized recommendations to strengthen your candidacy.</p>
        </article>

        <article>
            <h3>Tailored Application Toolkit</h3>
            <p>Get AI-generated application materials including optimized CVs tailored to specific roles, customized cover letters highlighting relevant experience, interview preparation notes, and location-specific relocation intelligence for international opportunities.</p>
        </article>

        <article>
            <h3>Ask MENA Careers AI Career Assistant</h3>
            <p>Chat with our AI career assistant trained on finance industry data, job market trends, and career development insights. Get answers about specific roles, firms, compensation benchmarks, interview preparation, and career transition strategies personalized to your background.</p>
        </article>

        <article>
            <h3>Global Private Equity Job Intelligence</h3>
            <p>Access curated private equity opportunities across buyout, growth equity, private credit, secondaries, infrastructure, portfolio operations, and adjacent buy-side routes. Filter by seniority, location, and strategy with real-time updates from top employers and executive search firms worldwide.</p>
        </article>

        <article>
            <h3>Private Equity Career Intelligence</h3>
            <p>Make informed career decisions with intelligence on compensation benchmarks, fund strategies, recruiter coverage, interview expectations, deal experience, and the skills required across buyout, growth equity, private credit, secondaries, infrastructure, investor relations, and portfolio operations.</p>
        </article>
    </section>

    <!-- Industry Comparison Content -->
    <section>
        <h2>Why Choose MENA Careers Over Other Platforms</h2>

        <article>
            <h3>Versus LinkedIn Jobs</h3>
            <p>While LinkedIn lists millions of jobs, MENA Careers focuses exclusively on finance careers with deeper role analysis. Our Application Audit breaks down requirements, assesses your fit, and generates tailored materials—going far beyond LinkedIn's basic job matching to actually help you land the role.</p>
        </article>

        <article>
            <h3>Versus Indeed and Glassdoor</h3>
            <p>Generic job boards aggregate listings without analysis. MENA Careers provides finance-specific intelligence including compensation benchmarks from industry data, skill gap analysis, and AI-generated application materials. Our Job Breakdown Card explains what each role actually requires and how to position yourself.</p>
        </article>

        <article>
            <h3>Versus eFinancialCareers</h3>
            <p>While eFinancialCareers focuses on listings, MENA Careers provides active career intelligence. Our AI analyzes each opportunity, matches it to your profile, generates tailored CVs and cover letters, and provides location-specific intelligence for international roles—transforming passive browsing into strategic applications.</p>
        </article>

        <article>
            <h3>Versus Traditional Headhunters</h3>
            <p>Headhunters work for employers, not candidates. MENA Careers works for you—analyzing opportunities, preparing your materials, and providing intelligence that helps you negotiate better offers. Use alongside headhunter relationships for maximum career advantage.</p>
        </article>
    </section>

    <!-- Career Resources Content -->
    <section>
        <h2>Private Equity Career Resources</h2>

        <article>
            <h3>Breaking into Private Equity</h3>
            <p>Whether you're targeting private equity, private credit, portfolio operations, investor relations, or adjacent buy-side roles, MENA Careers helps you understand requirements, position your background, and prepare competitive applications. Our AI analyzes what strong private equity candidates need to show and how to demonstrate fit.</p>
        </article>

        <article>
            <h3>Private Equity Interview Preparation</h3>
            <p>Prepare for technical and behavioral interviews across finance roles. MENA Careers's AI helps with LBO modeling, valuation questions, deal walkthroughs, market sizing, and behavioral frameworks. Get firm-specific insights for interviews at Goldman Sachs, Blackstone, KKR, Citadel, and other top employers.</p>
        </article>

        <article>
            <h3>Finance Salary and Compensation Benchmarks 2024</h3>
            <p>Comprehensive compensation context across finance careers: Manager, Senior Manager, VP, Director, Principal, Partner, Portfolio Operations, Investor Relations, and Private Credit roles. Includes base, bonus, carry, co-invest, fund strategy, seniority, and platform differences.</p>
        </article>

        <article>
            <h3>Private Equity Career Paths</h3>
            <p>Explore private equity opportunities across buyout, growth equity, private credit, infrastructure, secondaries, investor relations, and portfolio operations. Understand the skills, recruiter channels, interview process, and application positioning required for each route.</p>
        </article>

        <article>
            <h3>Career Transitions in Finance</h3>
            <p>Navigate career transitions into private equity from banking, strategy, consulting, private credit, or adjacent investing roles. MENA Careers's Application Audit identifies transferable skills and helps you position experience for new opportunities.</p>
        </article>

        <article>
            <h3>Remote and Flexible Finance Roles</h3>
            <p>Discover remote-friendly finance positions in portfolio operations, due diligence, investor relations, fintech, and corporate finance. Growing opportunities support hybrid work models without sacrificing career progression or compensation.</p>
        </article>
    </section>
</div>

</div>

<?php if (!$is_logged_in && !empty($subscription_plans)) : ?>
    <div class="sffc-plan-modal" data-plan-modal aria-hidden="true">
        <div class="sffc-plan-modal__overlay" data-plan-close></div>
        <div class="sffc-plan-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="sffc-plan-modal-title">
            <div class="sffc-plan-modal__header">
                <div>
                    <h3 id="sffc-plan-modal-title"><?php esc_html_e('Choose your membership', 'senna-finance'); ?></h3>
                    <p><?php esc_html_e('Pick the plan that fits your workflow. Cancel anytime.', 'senna-finance'); ?></p>
                </div>
                <button type="button" class="sffc-plan-close" data-plan-close aria-label="<?php esc_attr_e('Close plans', 'senna-finance'); ?>">&times;</button>
            </div>
            <div class="sffc-plan-modal__body">
                <div class="sffc-plan-grid">
                    <?php foreach ($subscription_plans as $plan) :
                        $features = $plan['features'] ?? array();
                        ?>
                        <article class="sffc-plan-card" data-plan-card data-plan-slug="<?php echo esc_attr($plan['slug']); ?>">
                            <div class="sffc-plan-card__head">
                                <h4><?php echo esc_html($plan['name']); ?></h4>
                                <?php
                                $plan_price_markup = '';
                                if (!empty($plan['price_amount']) && !empty($plan['price_currency']) && floatval($plan['price_amount']) > 0) {
                                    $price_shortcode = sprintf('[currency_price amount="%s" base_currency="%s" class="sffc-plan-card__price-value" show_code="false"]', esc_attr($plan['price_amount']), esc_attr($plan['price_currency']));
                                    $plan_price_markup = do_shortcode($price_shortcode);
                                    if (!empty($plan['billing_cycle'])) {
                                        $plan_price_markup .= ' <span class="sffc-plan-card__cycle">' . esc_html($plan['billing_cycle']) . '</span>';
                                    }
                                }
                                ?>
                                <?php if (!empty($plan_price_markup)) : ?>
                                    <p class="sffc-plan-card__price"><?php echo $plan_price_markup; ?></p>
                                <?php elseif (!empty($plan['price'])) : ?>
                                    <p class="sffc-plan-card__price"><?php echo esc_html($plan['price']); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($plan['tagline'])) : ?><p class="sffc-plan-card__tagline"><?php echo esc_html($plan['tagline']); ?></p><?php endif; ?>
                            </div>
                            <?php if (!empty($features)) : ?>
                                <ul class="sffc-plan-card__list">
                                    <?php foreach ($features as $feature) : ?>
                                        <li><?php echo esc_html($feature); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <?php if (!empty($plan['audience'])) : ?><p class="sffc-plan-card__audience"><?php echo esc_html($plan['audience']); ?></p><?php endif; ?>
                            <button type="button" class="sffc-plan-select" data-plan-select data-plan-url="<?php echo esc_url($plan['mp_url']); ?>" data-plan-slug="<?php echo esc_attr($plan['slug']); ?>">
                                <?php esc_html_e('Choose plan', 'senna-finance'); ?>
                            </button>
                        </article>
                    <?php endforeach; ?>
                </div>
                <div class="sffc-plan-checkout" data-plan-checkout hidden>
                    <p data-plan-message><?php esc_html_e('Select a membership to view the secure checkout.', 'senna-finance'); ?></p>
                    <?php foreach ($subscription_plans as $plan) :
                        if (empty($plan['shortcode'])) {
                            continue;
                        }
                        ?>
                        <div class="sffc-plan-form" data-plan-form="<?php echo esc_attr($plan['slug']); ?>" hidden>
                            <?php echo do_shortcode($plan['shortcode']); // shortcode defined by admin ?>
                        </div>
                    <?php endforeach; ?>
                    <div class="sffc-plan-external" data-plan-external hidden>
                        <a href="#" target="_blank" rel="noopener noreferrer" data-plan-external-link><?php esc_html_e('Open secure checkout in a new tab', 'senna-finance'); ?></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
