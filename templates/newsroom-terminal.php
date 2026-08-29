<?php
/**
 * Newsroom Terminal Template
 * Premium two-panel news reader with sophisticated institutional styling
 *
 * Layout: [Top Bar] + [Story List (Left)] + [Story Content (Right)]
 *
 * @package SennaFinanceCareer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get filter icon SVG for newsroom terminal
 * Matches the PE newsroom filter icon style
 */
if (!function_exists('nrt_filter_icon')) :
function nrt_filter_icon($slug) {
    $icons = array(
        // Content types
        'all' => '<svg viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
        'news' => '<svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><polyline points="14 2 14 8 20 8" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><line x1="16" y1="13" x2="8" y2="13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><line x1="16" y1="17" x2="8" y2="17" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
        'deal' => '<svg viewBox="0 0 24 24"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'report' => '<svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><line x1="12" y1="20" x2="12" y2="4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><line x1="6" y1="20" x2="6" y2="14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
        'analysis' => '<svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'brief' => '<svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',

        // Sectors
        'all-sectors' => '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1" fill="none" stroke="currentColor" stroke-width="1.5"/><rect x="14" y="3" width="7" height="7" rx="1" fill="none" stroke="currentColor" stroke-width="1.5"/><rect x="3" y="14" width="7" height="7" rx="1" fill="none" stroke="currentColor" stroke-width="1.5"/><rect x="14" y="14" width="7" height="7" rx="1" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>',
        'technology' => '<svg viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="2" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M9 9h6M9 12h6M9 15h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
        'healthcare' => '<svg viewBox="0 0 24 24"><path d="M12 6v12M6 12h12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>',
        'financial-services' => '<svg viewBox="0 0 24 24"><path d="M3 21h18M4 18h16M9 18V8l6 4v6" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'energy' => '<svg viewBox="0 0 24 24"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'industrials' => '<svg viewBox="0 0 24 24"><path d="M2 20h20M5 20V8l5 4V8l5 4v8" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'consumer' => '<svg viewBox="0 0 24 24"><circle cx="9" cy="21" r="1" stroke="currentColor" stroke-width="1.5"/><circle cx="20" cy="21" r="1" stroke="currentColor" stroke-width="1.5"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'real-estate' => '<svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><polyline points="9 22 9 12 15 12 15 22" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'infrastructure' => '<svg viewBox="0 0 24 24"><path d="M4 21h16M4 21V11M20 21V11M2 11h20M8 11V6M16 11V6M12 11V3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg>',
        'buyout' => '<svg viewBox="0 0 24 24"><path d="M4 14l4 4 12-12" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'growth-equity' => '<svg viewBox="0 0 24 24"><path d="M4 12h4l3-8 4 16 3-8h4" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'venture-capital' => '<svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',

        // Regions - private equity focused
        'all-regions' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="1.5"/><line x1="2" y1="12" x2="22" y2="12" stroke="currentColor" stroke-width="1.5"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>',
        'private-equity' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M11 8l2 2-1 4 2 2-3 1" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>',
        'private_equity' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M11 8l2 2-1 4 2 2-3 1" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>',
        'uae' => '<svg viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="12" rx="1" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M3 10h18M3 14h18" stroke="currentColor" stroke-width="1.5"/></svg>',
        'saudi-arabia' => '<svg viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="12" rx="1" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M8 12h8" stroke="currentColor" stroke-width="1.5"/></svg>',
        'qatar' => '<svg viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="12" rx="1" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M9 6v12" stroke="currentColor" stroke-width="1.5"/></svg>',
        'bahrain' => '<svg viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="12" rx="1" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M8 6l3 3-3 3 3 3-3 3" stroke="currentColor" stroke-width="1.5" fill="none"/></svg>',
        'kuwait' => '<svg viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="12" rx="1" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M3 10h18M3 14h18" stroke="currentColor" stroke-width="1.5"/></svg>',
        'oman' => '<svg viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="12" rx="1" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M3 6h4v12H3" stroke="currentColor" stroke-width="1.5"/></svg>',
        'egypt' => '<svg viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="12" rx="1" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M3 10h18M3 14h18" stroke="currentColor" stroke-width="1.5"/></svg>',
        'jordan' => '<svg viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="12" rx="1" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M3 10h18M3 14h18" stroke="currentColor" stroke-width="1.5"/></svg>',
        'gcc' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M11 8l2 2-1 4 2 2-3 1" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>',
        'global' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="1.5"/><line x1="2" y1="12" x2="22" y2="12" stroke="currentColor" stroke-width="1.5"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>',
    );

    // Return matching icon or default
    return $icons[$slug] ?? '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>';
}
endif;

// Prevent function redefinition
if (!function_exists('sffc_render_newsroom_terminal')) :

/**
 * Render the newsroom terminal layout
 *
 * @param array $context Dashboard context with stories_feed
 */
function sffc_render_newsroom_terminal($context) {
    if (empty($context) || empty($context['stories_feed'])) {
        return '<div class="nrt-empty">No stories available.</div>';
    }

    $stories = $context['stories_feed'];
    $jobs = $context['jobs_feed'] ?? array();
    $current_user = wp_get_current_user();
    $user_name = $current_user->ID ? $current_user->display_name : 'Guest';
    $user_avatar = $current_user->ID ? get_avatar_url($current_user->ID, ['size' => 32]) : '';
    $is_logged_in = is_user_logged_in();

    // Get subscription plans from context (same as sffc-plan-modal)
    $subscription_plans = $context['subscription_plans'] ?? array();

    // Get unique filters from stories
    $sectors = array_unique(array_filter(array_column($stories, 'sector')));
    $regions = array_unique(array_filter(array_column($stories, 'region')));
    $types = array_unique(array_column($stories, 'type'));

    // Get first story and first job for initial display
    $first_story = $stories[0] ?? null;
    $first_job = $jobs[0] ?? null;

    // Generate unique ID for this instance
    $terminal_id = 'nrt-' . wp_rand(1000, 9999);

    ob_start();
    ?>
    <script type="application/json" id="<?php echo esc_attr($terminal_id); ?>-data">
    <?php echo wp_json_encode(array(
        'stories' => $stories,
        'jobs' => $jobs
    ), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
    </script>
    <div class="nrt-terminal" data-terminal-id="<?php echo esc_attr($terminal_id); ?>-data" data-logged-in="<?php echo $is_logged_in ? 'true' : 'false'; ?>">

        <!-- Top Bar -->
        <header class="nrt-topbar">
            <div class="nrt-topbar-left">
                <div class="nrt-brand">
                    <svg class="nrt-brand-logo" viewBox="0 0 28 28" fill="none">
                        <path d="M5 9c0-3 2.5-5.5 5.5-5.5h5c2.5 0 4.5 2 4.5 4.5 0 2-1.3 3.6-3.1 4.2-.2.1-.2.4 0 .5 1.8.6 3.1 2.2 3.1 4.2 0 2.5-2 4.5-4.5 4.5h-5C7.5 21.5 5 19 5 16" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" fill="none"/>
                        <circle cx="24" cy="5" r="3" fill="currentColor"/>
                    </svg>
                    <span class="nrt-brand-name">MENA Careers Intelligence</span>
                </div>
                <?php
                // Default tab: recruiter-posts for logged-in users, contacts for guests
                $default_tab = $is_logged_in ? 'recruiter-posts' : 'contacts';
                ?>
                <nav class="nrt-nav">
                    <button type="button" class="nrt-nav-item <?php echo $default_tab === 'contacts' ? 'is-active' : ''; ?>" data-tab="contacts">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                        <span>HR Contacts</span>
                    </button>
                    <button type="button" class="nrt-nav-item <?php echo $default_tab === 'recruiter-posts' ? 'is-active' : ''; ?>" data-tab="recruiter-posts">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                            <line x1="16" y1="13" x2="8" y2="13"/>
                            <line x1="16" y1="17" x2="8" y2="17"/>
                            <polyline points="10 9 9 9 8 9"/>
                        </svg>
                        <span>Recruiter Posts</span>
                        <?php
                        // Show badge for new recruiter posts
                        if ($is_logged_in) {
                            $new_posts = apply_filters('nrt_new_recruiter_posts_count', 0, get_current_user_id());
                            if ($new_posts > 0) {
                                echo '<span class="nrt-nav-badge">' . esc_html($new_posts) . '</span>';
                            }
                        }
                        ?>
                    </button>
                    <button type="button" class="nrt-nav-item <?php echo $default_tab === 'replies' ? 'is-active' : ''; ?>" data-tab="replies">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                        </svg>
                        <span>Replies</span>
                        <?php
                        // Show badge for unread replies
                        if ($is_logged_in) {
                            $unread_replies = apply_filters('nrt_unread_replies_count', 0, get_current_user_id());
                            if ($unread_replies > 0) {
                                echo '<span class="nrt-nav-badge">' . esc_html($unread_replies) . '</span>';
                            }
                        }
                        ?>
                    </button>
                </nav>
            </div>
            <div class="nrt-topbar-right">
                <div class="nrt-search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" class="nrt-search-input" placeholder="Search stories..." id="nrt-search">
                </div>
                <button class="nrt-icon-btn" id="nrt-refresh" title="Refresh">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="23 4 23 10 17 10"/>
                        <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
                    </svg>
                </button>
                <?php if ($is_logged_in) : ?>
                <div class="nrt-user-wrapper">
                    <div class="nrt-user">
                        <?php if ($user_avatar) : ?>
                        <img src="<?php echo esc_url($user_avatar); ?>" alt="<?php echo esc_attr($user_name); ?>" class="nrt-user-avatar">
                        <?php else : ?>
                        <div class="nrt-user-initials"><?php echo esc_html(substr($user_name, 0, 1)); ?></div>
                        <?php endif; ?>
                        <span class="nrt-user-name"><?php echo esc_html($user_name); ?></span>
                        <svg class="nrt-user-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </div>
                    <div class="nrt-user-dropdown">
                        <button type="button" class="nrt-user-dropdown-item" data-tab="profile">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                            <span>Profile</span>
                        </button>
                        <button type="button" class="nrt-user-dropdown-item" data-tab="matches">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                            </svg>
                            <span>Matches</span>
                            <?php
                            if (class_exists('Recruiter_Terminal_DB')) {
                                $pending_count = Recruiter_Terminal_DB::get_pending_match_count(get_current_user_id());
                                if ($pending_count > 0) {
                                    echo '<span class="nrt-dropdown-badge">' . esc_html($pending_count) . '</span>';
                                }
                            }
                            ?>
                        </button>
                        <div class="nrt-user-dropdown-divider"></div>
                        <button type="button" class="nrt-user-dropdown-item" data-tab="database">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <ellipse cx="12" cy="5" rx="9" ry="3"/>
                                <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/>
                                <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>
                            </svg>
                            <span>Database</span>
                        </button>
                        <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="nrt-user-dropdown-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                                <polyline points="16 17 21 12 16 7"/>
                                <line x1="21" y1="12" x2="9" y2="12"/>
                            </svg>
                            <span>Sign Out</span>
                        </a>
                    </div>
                </div>
                <?php else : ?>
                <a href="/login-auth/" class="nrt-login-btn">Sign In</a>
                <?php endif; ?>
                <!-- Mobile Search Button -->
                <button type="button" class="nrt-icon-btn nrt-mobile-search-btn" id="nrt-mobile-search-open" title="Search" style="display:none;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                </button>
            </div>
        </header>

        <!-- Mobile Search Overlay -->
        <div class="nrt-mobile-search-overlay" id="nrt-mobile-search-overlay">
            <div class="nrt-mobile-search-header">
                <button type="button" class="nrt-mobile-search-close" id="nrt-mobile-search-close">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="19" y1="5" x2="5" y2="19"/>
                        <line x1="5" y1="5" x2="19" y2="19"/>
                    </svg>
                </button>
                <div class="nrt-mobile-search-input-wrap">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" class="nrt-mobile-search-input" placeholder="Search stories, deals, jobs..." id="nrt-mobile-search-input" autocomplete="off">
                </div>
            </div>
            <div class="nrt-mobile-search-results" id="nrt-mobile-search-results">
                <div class="nrt-mobile-search-empty">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <p>Search for stories, deals, or jobs</p>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="nrt-main">

            <!-- Left Panel -->
            <aside class="nrt-stories-panel">

                <!-- MATCHES TAB - Recruiter Opportunities -->
                <div class="nrt-tab-content nrt-tab-matches <?php echo $default_tab === 'matches' ? 'is-active' : ''; ?>" data-tab-content="matches">
                    <?php if (!$is_logged_in) : ?>
                        <div class="nrt-matches-login-prompt">
                            <div class="nrt-matches-login-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48">
                                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                                </svg>
                            </div>
                            <h3>Discover Your Matches</h3>
                            <p>Sign in to see personalized job opportunities from top recruiters matched to your skills and preferences.</p>
                            <a href="/login-auth/" class="nrt-matches-login-btn">Sign In to View Matches</a>
                        </div>
                    <?php else : ?>
                        <div class="nrt-matches-header">
                            <h3 class="nrt-matches-title">Your Matches</h3>
                            <p class="nrt-matches-subtitle">Opportunities matched to your skills and preferences</p>
                        </div>

                        <div class="nrt-matches-list" id="nrt-matches-list">
                            <!-- Matches will be loaded via AJAX -->
                            <div class="nrt-matches-loading">
                                <div class="nrt-loading-spinner"></div>
                                <span>Finding your matches...</span>
                            </div>
                        </div>

                        <div class="nrt-matches-empty" id="nrt-matches-empty" style="display: none;">
                            <div class="nrt-matches-empty-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48">
                                    <circle cx="11" cy="11" r="8"/>
                                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                </svg>
                            </div>
                            <h4>No Matches Yet</h4>
                            <p>Update your job preferences to start receiving matched opportunities from recruiters.</p>
                            <a href="#" class="nrt-update-prefs-btn" data-scroll-to="nrt-profile-prefs">Update Preferences</a>
                        </div>

                        <!-- Recruiter CTA -->
                        <div class="nrt-recruiter-cta">
                            <a href="/recruiter-terminal/" class="nrt-recruiter-cta-link">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/>
                                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                                </svg>
                                <span>Are you a recruiter? Post opportunities here</span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                                    <line x1="5" y1="12" x2="19" y2="12"/>
                                    <polyline points="12 5 19 12 12 19"/>
                                </svg>
                            </a>
                        </div>
                    <?php endif; ?>
                </div><!-- /.nrt-tab-matches -->

                <!-- RECRUITER POSTS TAB - Admin-Curated Recruiter Opportunities -->
                <div class="nrt-tab-content nrt-tab-recruiter-posts <?php echo $default_tab === 'recruiter-posts' ? 'is-active' : ''; ?>" data-tab-content="recruiter-posts">
                        <div class="nrt-recruiter-posts-dashboard">
                            <!-- Recruiter Posts Header -->
                            <div class="nrt-recruiter-posts-header">
                                <h3 class="nrt-recruiter-posts-title">Recruiter Posts</h3>
                                <p class="nrt-recruiter-posts-subtitle">Latest opportunities from top recruiters</p>
                                <div class="nrt-recruiter-posts-filter">
                                    <button type="button" class="nrt-rp-filter-btn is-active" data-rp-filter="all">All</button>
                                    <button type="button" class="nrt-rp-filter-btn" data-rp-filter="recent">Recent</button>
                                    <button type="button" class="nrt-rp-filter-btn" data-rp-filter="featured">Featured</button>
                                </div>
                            </div>

                            <!-- Recruiter Posts List -->
                            <div class="nrt-recruiter-posts-list" id="nrt-recruiter-posts-list">
                                <!-- Loading State -->
                                <div class="nrt-recruiter-posts-loading" id="nrt-recruiter-posts-loading">
                                    <div class="nrt-loading-spinner"></div>
                                    <span>Loading recruiter posts...</span>
                                </div>
                            </div>

                            <!-- Empty State -->
                            <div class="nrt-recruiter-posts-empty" id="nrt-recruiter-posts-empty" style="display: none;">
                                <div class="nrt-recruiter-posts-empty-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                        <polyline points="14 2 14 8 20 8"/>
                                        <line x1="16" y1="13" x2="8" y2="13"/>
                                        <line x1="16" y1="17" x2="8" y2="17"/>
                                    </svg>
                                </div>
                                <h4>No Recruiter Posts Yet</h4>
                                <p>Check back soon for new opportunities from recruiters.</p>
                            </div>
                        </div>
                </div><!-- /.nrt-tab-recruiter-posts -->

                <!-- REPLIES TAB - Conversations with Recruiters -->
                <div class="nrt-tab-content nrt-tab-replies <?php echo $default_tab === 'replies' ? 'is-active' : ''; ?>" data-tab-content="replies">
                        <div class="nrt-replies-dashboard">
                            <!-- Replies Header -->
                            <div class="nrt-replies-header">
                                <h3 class="nrt-replies-title">Replies</h3>
                                <p class="nrt-replies-subtitle">Conversations with recruiters</p>
                                <div class="nrt-replies-filter">
                                    <button type="button" class="nrt-reply-filter-btn is-active" data-reply-filter="all">All</button>
                                    <button type="button" class="nrt-reply-filter-btn" data-reply-filter="unread">Unread</button>
                                    <button type="button" class="nrt-reply-filter-btn" data-reply-filter="starred">Starred</button>
                                </div>
                            </div>

                            <!-- Replies List -->
                            <div class="nrt-replies-list" id="nrt-replies-list">
                                <!-- Loading State -->
                                <div class="nrt-replies-loading" id="nrt-replies-loading">
                                    <div class="nrt-loading-spinner"></div>
                                    <span>Loading conversations...</span>
                                </div>
                            </div>

                            <!-- Empty State -->
                            <div class="nrt-replies-empty" id="nrt-replies-empty" style="display: none;">
                                <div class="nrt-replies-empty-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48">
                                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                    </svg>
                                </div>
                                <h4>No Conversations Yet</h4>
                                <p>When recruiters respond to your profile, their messages will appear here.</p>
                            </div>
                        </div>
                </div><!-- /.nrt-tab-replies -->

                <!-- CONTACTS TAB - Hiring Manager Contacts -->
                <div class="nrt-tab-content nrt-tab-contacts <?php echo $default_tab === 'contacts' ? 'is-active' : ''; ?>" data-tab-content="contacts">
                        <!-- Contacts Filter Bar -->
                        <div class="nrt-contacts-filter-bar">
                            <div class="nrt-contacts-search">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="11" cy="11" r="8"/>
                                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                </svg>
                                <input type="text" id="nrt-contacts-search" placeholder="Search contacts..." autocomplete="off">
                            </div>
                            <div class="nrt-contacts-filters">
                                <select id="nrt-contacts-company" class="nrt-contacts-select">
                                    <option value="">All Companies</option>
                                </select>
                                <select id="nrt-contacts-country" class="nrt-contacts-select">
                                    <option value="">All Countries</option>
                                </select>
                                <select id="nrt-contacts-seniority" class="nrt-contacts-select">
                                    <option value="">All Levels</option>
                                </select>
                                <select id="nrt-contacts-industry" class="nrt-contacts-select">
                                    <option value="">All Industries</option>
                                </select>
                            </div>
                        </div>

                        <!-- Contact Detail Panel (slide-in) -->
                        <div class="nrt-contact-detail-panel" id="nrt-contact-detail-panel">
                            <div class="nrt-contact-detail-overlay" id="nrt-contact-detail-overlay"></div>
                            <div class="nrt-contact-detail-content">
                                <button type="button" class="nrt-contact-detail-close" id="nrt-contact-detail-close">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                                        <line x1="18" y1="6" x2="6" y2="18"/>
                                        <line x1="6" y1="6" x2="18" y2="18"/>
                                    </svg>
                                </button>
                                <div class="nrt-contact-detail-header">
                                    <div class="nrt-contact-detail-avatar" id="nrt-contact-detail-avatar">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                            <circle cx="12" cy="7" r="4"/>
                                        </svg>
                                    </div>
                                    <div class="nrt-contact-detail-info">
                                        <h3 class="nrt-contact-detail-name" id="nrt-contact-detail-name"></h3>
                                        <p class="nrt-contact-detail-title" id="nrt-contact-detail-title"></p>
                                        <p class="nrt-contact-detail-company" id="nrt-contact-detail-company"></p>
                                    </div>
                                </div>
                                <div class="nrt-contact-detail-body">
                                    <div class="nrt-contact-detail-section" id="nrt-contact-detail-bio-section" style="display: none;">
                                        <h4>About</h4>
                                        <p id="nrt-contact-detail-bio"></p>
                                    </div>
                                    <div class="nrt-contact-detail-section">
                                        <h4>Details</h4>
                                        <div class="nrt-contact-detail-meta">
                                            <div class="nrt-contact-detail-meta-item" id="nrt-contact-detail-email-row" style="display: none;">
                                                <span class="nrt-contact-detail-meta-label">Email</span>
                                                <span class="nrt-contact-detail-meta-value" id="nrt-contact-detail-email"></span>
                                            </div>
                                            <div class="nrt-contact-detail-meta-item" id="nrt-contact-detail-seniority-row" style="display: none;">
                                                <span class="nrt-contact-detail-meta-label">Seniority</span>
                                                <span class="nrt-contact-detail-meta-value" id="nrt-contact-detail-seniority"></span>
                                            </div>
                                            <div class="nrt-contact-detail-meta-item" id="nrt-contact-detail-industry-row" style="display: none;">
                                                <span class="nrt-contact-detail-meta-label">Industry</span>
                                                <span class="nrt-contact-detail-meta-value" id="nrt-contact-detail-industry"></span>
                                            </div>
                                            <div class="nrt-contact-detail-meta-item" id="nrt-contact-detail-location-row" style="display: none;">
                                                <span class="nrt-contact-detail-meta-label">Location</span>
                                                <span class="nrt-contact-detail-meta-value" id="nrt-contact-detail-location"></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="nrt-contact-detail-section" id="nrt-contact-detail-specialties-section" style="display: none;">
                                        <h4>Specialties</h4>
                                        <div class="nrt-contact-detail-tags" id="nrt-contact-detail-specialties"></div>
                                    </div>
                                </div>
                                <div class="nrt-contact-detail-actions">
                                    <a href="#" class="nrt-contact-detail-linkedin" id="nrt-contact-detail-linkedin" target="_blank" rel="noopener noreferrer" style="display: none;">
                                        <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16">
                                            <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                                        </svg>
                                        View LinkedIn Profile
                                    </a>
                                    <button type="button" class="nrt-contact-detail-intro-btn" id="nrt-contact-detail-intro-btn">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                            <polyline points="22,6 12,13 2,6"/>
                                        </svg>
                                        Express Interest
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Contacts List -->
                        <div class="nrt-contacts-list" id="nrt-contacts-list">
                            <div class="nrt-contacts-loading">
                                <div class="nrt-loading-spinner"></div>
                                <span>Loading contacts...</span>
                            </div>
                        </div>

                        <!-- Contacts Pagination -->
                        <div class="nrt-contacts-pagination" id="nrt-contacts-pagination" style="display: none;">
                            <button type="button" class="nrt-contacts-page-btn" id="nrt-contacts-prev" disabled>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                    <polyline points="15 18 9 12 15 6"/>
                                </svg>
                            </button>
                            <span class="nrt-contacts-page-info">
                                Page <span id="nrt-contacts-current-page">1</span> of <span id="nrt-contacts-total-pages">1</span>
                            </span>
                            <button type="button" class="nrt-contacts-page-btn" id="nrt-contacts-next">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                    <polyline points="9 18 15 12 9 6"/>
                                </svg>
                            </button>
                        </div>

                        <!-- Empty State -->
                        <div class="nrt-contacts-empty" id="nrt-contacts-empty" style="display: none;">
                            <div class="nrt-contacts-empty-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48">
                                    <circle cx="11" cy="11" r="8"/>
                                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                </svg>
                            </div>
                            <h4>No Contacts Found</h4>
                            <p>Try adjusting your search criteria or filters.</p>
                        </div>
                </div><!-- /.nrt-tab-contacts -->

                <!-- PROFILE TAB - Quick Links Sidebar -->
                <div class="nrt-tab-content nrt-tab-profile <?php echo $default_tab === 'profile' ? 'is-active' : ''; ?>" data-tab-content="profile">
                    <?php if ($is_logged_in) : ?>
                    <!-- Networking Stats -->
                    <div class="nrt-profile-stats-section">
                        <div class="nrt-profile-stat" data-stat="saved-contacts">
                            <span class="nrt-profile-stat-value" id="nrt-stat-saved-contacts">0</span>
                            <span class="nrt-profile-stat-label">Saved Contacts</span>
                        </div>
                        <div class="nrt-profile-stat" data-stat="target-companies">
                            <span class="nrt-profile-stat-value" id="nrt-stat-target-companies">0</span>
                            <span class="nrt-profile-stat-label">Target Companies</span>
                        </div>
                        <div class="nrt-profile-stat" data-stat="outreach">
                            <span class="nrt-profile-stat-value" id="nrt-stat-outreach">0</span>
                            <span class="nrt-profile-stat-label">Outreach</span>
                        </div>
                    </div>

                    <!-- Saved Contacts -->
                    <div class="nrt-profile-quick-section nrt-profile-networking-section">
                        <div class="nrt-profile-section-header">
                            <h4 class="nrt-profile-section-title">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="16" height="16">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                </svg>
                                Saved Contacts
                            </h4>
                        </div>
                        <div class="nrt-saved-contacts-list" id="nrt-saved-contacts-list">
                            <div class="nrt-profile-empty">
                                <p>Save contacts from the Contacts tab to track them here.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Target Companies -->
                    <div class="nrt-profile-quick-section nrt-profile-networking-section">
                        <div class="nrt-profile-section-header">
                            <h4 class="nrt-profile-section-title">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="16" height="16">
                                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                                    <polyline points="9 22 9 12 15 12 15 22"/>
                                </svg>
                                Target Companies
                            </h4>
                        </div>
                        <div class="nrt-target-companies-list" id="nrt-target-companies-list">
                            <div class="nrt-profile-empty">
                                <p>Add companies from the HR Contacts tab as targets.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Outreach Log -->
                    <div class="nrt-profile-quick-section nrt-profile-networking-section">
                        <div class="nrt-profile-section-header">
                            <h4 class="nrt-profile-section-title">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="16" height="16">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                    <polyline points="22 4 12 14.01 9 11.01"/>
                                </svg>
                                Outreach Log
                            </h4>
                        </div>
                        <div class="nrt-outreach-log-list" id="nrt-outreach-log-list">
                            <div class="nrt-profile-empty">
                                <p>Track contacts you've reached out to.</p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Account Settings -->
                    <div class="nrt-profile-quick-section">
                        <h4 class="nrt-profile-section-title">Account</h4>
                        <div class="nrt-profile-quick-links">
                            <a href="<?php echo esc_url(home_url('/account/')); ?>" class="nrt-profile-quick-link">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="20" height="20">
                                    <circle cx="12" cy="12" r="3"/>
                                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                                </svg>
                                <span>Settings</span>
                            </a>
                            <a href="<?php echo esc_url(home_url('/subscription/')); ?>" class="nrt-profile-quick-link">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="20" height="20">
                                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                                    <line x1="1" y1="10" x2="23" y2="10"/>
                                </svg>
                                <span>Subscription</span>
                            </a>
                            <a href="<?php echo esc_url(home_url('/notifications/')); ?>" class="nrt-profile-quick-link">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="20" height="20">
                                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                                    <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                                </svg>
                                <span>Notifications</span>
                            </a>
                        </div>
                    </div>

                    <!-- Resources -->
                    <div class="nrt-profile-quick-section">
                        <h4 class="nrt-profile-section-title">Resources</h4>
                        <div class="nrt-profile-quick-links">
                            <a href="<?php echo esc_url(home_url('/help/')); ?>" class="nrt-profile-quick-link">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="20" height="20">
                                    <circle cx="12" cy="12" r="10"/>
                                    <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                                </svg>
                                <span>Help Center</span>
                            </a>
                            <a href="<?php echo esc_url(home_url('/feedback/')); ?>" class="nrt-profile-quick-link">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="20" height="20">
                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                </svg>
                                <span>Send Feedback</span>
                            </a>
                            <a href="<?php echo esc_url(home_url('/about/')); ?>" class="nrt-profile-quick-link">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="20" height="20">
                                    <circle cx="12" cy="12" r="10"/>
                                    <line x1="12" y1="16" x2="12" y2="12"/>
                                    <line x1="12" y1="8" x2="12.01" y2="8"/>
                                </svg>
                                <span>About</span>
                            </a>
                        </div>
                    </div>

                    <?php if ($is_logged_in) : ?>
                    <!-- Logout -->
                    <div class="nrt-profile-quick-section nrt-profile-logout-section">
                        <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="nrt-profile-quick-link nrt-profile-logout">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="20" height="20">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                                <polyline points="16 17 21 12 16 7"/>
                                <line x1="21" y1="12" x2="9" y2="12"/>
                            </svg>
                            <span>Log Out</span>
                        </a>
                    </div>
                    <?php endif; ?>
                </div><!-- /.nrt-tab-profile -->

            </aside>

            <!-- Story Content Panel (Right) -->
            <main class="nrt-content-panel" id="nrt-content-panel">
                <!-- Mobile Back Navigation -->
                <div class="nrt-mobile-back" id="nrt-mobile-back">
                    <button type="button" class="nrt-mobile-back-btn" id="nrt-back-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="19" y1="12" x2="5" y2="12"/>
                            <polyline points="12 19 5 12 12 5"/>
                        </svg>
                    </button>
                    <span class="nrt-mobile-back-title" id="nrt-mobile-back-title">Article</span>
                    <button type="button" class="nrt-icon-btn nrt-mobile-share-btn" id="nrt-mobile-share" title="Share">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="18" cy="5" r="3"/>
                            <circle cx="6" cy="12" r="3"/>
                            <circle cx="18" cy="19" r="3"/>
                            <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/>
                            <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>
                        </svg>
                    </button>
                </div>
                <div class="nrt-content-inner" id="nrt-content-inner" style="<?php echo in_array($default_tab, ['profile', 'contacts', 'recruiter-posts', 'replies']) ? 'display: none;' : ''; ?>">
                <?php if (!in_array($default_tab, ['profile', 'contacts', 'recruiter-posts', 'replies']) && $first_story) : ?>
                    <?php echo sffc_render_story_content($first_story); ?>
                <?php endif; ?>
                </div>

                <!-- Guide Reader View (shows when a guide is selected) -->
                <div class="nrt-guide-view" id="nrt-guide-view" style="display: none;">
                    <header class="nrt-guide-header">
                        <div class="nrt-guide-header-top">
                            <h1 id="nrt-guide-title">Guide Title</h1>
                            <div class="nrt-guide-actions">
                                <button type="button" class="nrt-guide-btn" id="nrt-guide-bookmark">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
                                    </svg>
                                    Save
                                </button>
                                <button type="button" class="nrt-guide-btn nrt-guide-btn--primary" id="nrt-guide-pdf">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                        <polyline points="7 10 12 15 17 10"/>
                                        <line x1="12" y1="15" x2="12" y2="3"/>
                                    </svg>
                                    Save PDF
                                </button>
                            </div>
                        </div>
                        <div class="nrt-guide-header-meta">
                            <span id="nrt-guide-category-badge">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                                </svg>
                                <span id="nrt-guide-category-text">Valuation</span>
                            </span>
                            <span id="nrt-guide-level-badge">Beginner</span>
                            <span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12 6 12 12 16 14"/>
                                </svg>
                                <span id="nrt-guide-duration">15 min read</span>
                            </span>
                        </div>
                    </header>
                    <div class="nrt-guide-body" id="nrt-guide-content">
                        <!-- Guide content loaded dynamically -->
                    </div>
                </div>

                <?php if (!$is_logged_in) : ?>
                <!-- Welcome Feature Panel for Logged-Out Users -->
                <div class="nrt-welcome-panel" id="nrt-welcome-panel">
                    <div class="nrt-welcome-panel-inner">
                        <!-- Welcome Icon -->
                        <div class="nrt-welcome-panel-icon" id="nrt-welcome-panel-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48">
                                <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                                <path d="M2 17l10 5 10-5"/>
                                <path d="M2 12l10 5 10-5"/>
                            </svg>
                        </div>

                        <!-- Welcome Title -->
                        <h2 class="nrt-welcome-panel-title" id="nrt-welcome-panel-title">Your Career Command Center</h2>

                        <!-- Welcome Description -->
                        <p class="nrt-welcome-panel-desc" id="nrt-welcome-panel-desc">
                            Everything you need to land your next role, in one place. Browse contacts, explore opportunities, and connect with recruiters.
                        </p>

                        <!-- Feature Grid -->
                        <div class="nrt-welcome-panel-features" id="nrt-welcome-panel-features">
                            <div class="nrt-welcome-feature">
                                <div class="nrt-welcome-feature-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                        <circle cx="12" cy="7" r="4"/>
                                    </svg>
                                </div>
                                <div class="nrt-welcome-feature-text">
                                    <strong>HR Contacts</strong>
                                    <span>50,000+ verified contacts</span>
                                </div>
                            </div>
                            <div class="nrt-welcome-feature">
                                <div class="nrt-welcome-feature-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                        <polyline points="14 2 14 8 20 8"/>
                                        <line x1="16" y1="13" x2="8" y2="13"/>
                                        <line x1="16" y1="17" x2="8" y2="17"/>
                                    </svg>
                                </div>
                                <div class="nrt-welcome-feature-text">
                                    <strong>Recruiter Posts</strong>
                                    <span>Direct from hiring managers</span>
                                </div>
                            </div>
                            <div class="nrt-welcome-feature">
                                <div class="nrt-welcome-feature-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                    </svg>
                                </div>
                                <div class="nrt-welcome-feature-text">
                                    <strong>Direct Messaging</strong>
                                    <span>Connect with recruiters</span>
                                </div>
                            </div>
                            <div class="nrt-welcome-feature">
                                <div class="nrt-welcome-feature-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                        <polyline points="22 4 12 14.01 9 11.01"/>
                                    </svg>
                                </div>
                                <div class="nrt-welcome-feature-text">
                                    <strong>Application Toolkit</strong>
                                    <span>CV tailoring & prep</span>
                                </div>
                            </div>
                        </div>

                        <!-- Stats Row -->
                        <div class="nrt-welcome-panel-stats">
                            <div class="nrt-welcome-stat">
                                <span class="nrt-welcome-stat-number">50K+</span>
                                <span class="nrt-welcome-stat-label">HR Contacts</span>
                            </div>
                            <div class="nrt-welcome-stat">
                                <span class="nrt-welcome-stat-number">2K+</span>
                                <span class="nrt-welcome-stat-label">Companies</span>
                            </div>
                            <div class="nrt-welcome-stat">
                                <span class="nrt-welcome-stat-number">10K+</span>
                                <span class="nrt-welcome-stat-label">Placements</span>
                            </div>
                        </div>

                        <!-- CTA Buttons -->
                        <div class="nrt-welcome-panel-cta">
                            <a href="/register/" class="nrt-welcome-panel-btn nrt-welcome-panel-btn--primary">
                                Create Free Account
                            </a>
                            <a href="/login-auth/" class="nrt-welcome-panel-btn nrt-welcome-panel-btn--secondary">
                                Sign In
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Contact Detail View (shows when a contact is selected) -->
                <div class="nrt-contact-view" id="nrt-contact-view" style="<?php echo ($default_tab === 'contacts' && $is_logged_in) ? '' : 'display: none;'; ?>">
                    <header class="nrt-contact-header">
                        <div class="nrt-contact-header-top">
                            <div class="nrt-contact-avatar" id="nrt-contact-avatar">
                                <span class="nrt-contact-initials"></span>
                            </div>
                            <div class="nrt-contact-title-area">
                                <h1 id="nrt-contact-name">Contact Name</h1>
                                <p id="nrt-contact-title">Job Title</p>
                                <p id="nrt-contact-company" class="nrt-contact-company-name">Company Name</p>
                            </div>
                            <div class="nrt-contact-actions">
                                <a href="#" class="nrt-contact-btn nrt-contact-btn--primary" id="nrt-contact-linkedin" target="_blank">
                                    <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16">
                                        <path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/>
                                    </svg>
                                    LinkedIn
                                </a>
                                <a href="#" class="nrt-contact-btn" id="nrt-contact-email">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                        <polyline points="22,6 12,13 2,6"/>
                                    </svg>
                                    Email
                                </a>
                            </div>
                        </div>
                    </header>
                    <div class="nrt-contact-body">
                        <!-- Contact Info Section -->
                        <div class="nrt-contact-section">
                            <h3>Contact Information</h3>
                            <div class="nrt-contact-info-grid">
                                <div class="nrt-contact-info-item">
                                    <span class="nrt-contact-info-label">Email</span>
                                    <span class="nrt-contact-info-value" id="nrt-contact-email-value">-</span>
                                </div>
                                <div class="nrt-contact-info-item">
                                    <span class="nrt-contact-info-label">Phone</span>
                                    <span class="nrt-contact-info-value" id="nrt-contact-phone-value">-</span>
                                </div>
                                <div class="nrt-contact-info-item">
                                    <span class="nrt-contact-info-label">Seniority</span>
                                    <span class="nrt-contact-info-value" id="nrt-contact-seniority-value">-</span>
                                </div>
                                <div class="nrt-contact-info-item">
                                    <span class="nrt-contact-info-label">Department</span>
                                    <span class="nrt-contact-info-value" id="nrt-contact-department-value">-</span>
                                </div>
                                <div class="nrt-contact-info-item">
                                    <span class="nrt-contact-info-label">Location</span>
                                    <span class="nrt-contact-info-value" id="nrt-contact-location-value">-</span>
                                </div>
                            </div>
                        </div>

                        <!-- Company Info Section -->
                        <div class="nrt-contact-section">
                            <h3>Company Information</h3>
                            <div class="nrt-contact-info-grid">
                                <div class="nrt-contact-info-item nrt-contact-info-full">
                                    <span class="nrt-contact-info-label">Company</span>
                                    <span class="nrt-contact-info-value" id="nrt-contact-company-name-value">-</span>
                                </div>
                                <div class="nrt-contact-info-item">
                                    <span class="nrt-contact-info-label">Industry</span>
                                    <span class="nrt-contact-info-value" id="nrt-contact-industry-value">-</span>
                                </div>
                                <div class="nrt-contact-info-item">
                                    <span class="nrt-contact-info-label">Company Size</span>
                                    <span class="nrt-contact-info-value" id="nrt-contact-size-value">-</span>
                                </div>
                                <div class="nrt-contact-info-item">
                                    <span class="nrt-contact-info-label">Revenue</span>
                                    <span class="nrt-contact-info-value" id="nrt-contact-revenue-value">-</span>
                                </div>
                                <div class="nrt-contact-info-item">
                                    <span class="nrt-contact-info-label">Location</span>
                                    <span class="nrt-contact-info-value" id="nrt-contact-company-location-value">-</span>
                                </div>
                                <div class="nrt-contact-info-item nrt-contact-info-full" id="nrt-contact-company-desc-wrap">
                                    <span class="nrt-contact-info-label">About</span>
                                    <span class="nrt-contact-info-value nrt-contact-desc" id="nrt-contact-company-desc-value">-</span>
                                </div>
                            </div>
                            <div class="nrt-contact-company-links" id="nrt-contact-company-links">
                                <a href="#" class="nrt-contact-link" id="nrt-contact-company-website" target="_blank">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                                        <circle cx="12" cy="12" r="10"/>
                                        <line x1="2" y1="12" x2="22" y2="12"/>
                                        <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                                    </svg>
                                    Website
                                </a>
                                <a href="#" class="nrt-contact-link" id="nrt-contact-company-linkedin" target="_blank">
                                    <svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14">
                                        <path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/>
                                    </svg>
                                    Company LinkedIn
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recruiter Post Detail View (shows when a recruiter post is selected) -->
                <div class="nrt-recruiter-post-view" id="nrt-recruiter-post-view" style="<?php echo $default_tab === 'recruiter-posts' ? '' : 'display: none;'; ?>">
                    <?php if ($is_logged_in) : ?>
                    <div class="nrt-opportunity-detail">
                        <!-- Placeholder State -->
                        <div class="nrt-opportunity-placeholder" id="nrt-opportunity-placeholder">
                            <div class="nrt-opportunity-placeholder-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48">
                                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                                </svg>
                            </div>
                            <h4>Select an Opportunity</h4>
                            <p>Click on an opportunity from the list to view details</p>
                        </div>

                        <!-- Opportunity Content (populated by JS) -->
                        <div class="nrt-opportunity-content" id="nrt-opportunity-content" style="display: none;">
                            <!-- Job Header -->
                            <div class="nrt-opp-header">
                                <div class="nrt-opp-company-logo" id="nrt-opp-company-logo">
                                    <span class="nrt-opp-company-initial"></span>
                                </div>
                                <div class="nrt-opp-header-info">
                                    <h2 class="nrt-opp-title" id="nrt-opp-title"></h2>
                                    <p class="nrt-opp-company-location">
                                        <span id="nrt-opp-company"></span>
                                        <span class="nrt-opp-separator">•</span>
                                        <span id="nrt-opp-location"></span>
                                    </p>
                                    <p class="nrt-opp-salary" id="nrt-opp-salary"></p>
                                </div>
                                <span class="nrt-opp-badge" id="nrt-opp-badge">New</span>
                            </div>

                            <!-- Recruiter Section -->
                            <div class="nrt-opp-recruiter-section">
                                <h4 class="nrt-opp-section-title">Recruiter</h4>
                                <div class="nrt-opp-recruiter-card">
                                    <div class="nrt-opp-recruiter-avatar" id="nrt-opp-recruiter-avatar">
                                        <span class="nrt-opp-recruiter-initial"></span>
                                    </div>
                                    <div class="nrt-opp-recruiter-info">
                                        <span class="nrt-opp-recruiter-name" id="nrt-opp-recruiter-name"></span>
                                        <span class="nrt-opp-recruiter-title" id="nrt-opp-recruiter-title"></span>
                                        <span class="nrt-opp-recruiter-company" id="nrt-opp-recruiter-company"></span>
                                    </div>
                                    <span class="nrt-opp-recruiter-status" id="nrt-opp-recruiter-status">Interested in your profile</span>
                                </div>
                            </div>

                            <!-- Match Section -->
                            <div class="nrt-opp-match-section">
                                <h4 class="nrt-opp-section-title">Why You Match</h4>
                                <ul class="nrt-opp-match-list" id="nrt-opp-match-list">
                                    <!-- Match items populated by JS -->
                                </ul>
                            </div>

                            <!-- Actions -->
                            <div class="nrt-opp-actions">
                                <button type="button" class="nrt-opp-action-btn nrt-opp-action-btn--primary" data-action="start-chat">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                    </svg>
                                    Start Chat
                                </button>
                                <button type="button" class="nrt-opp-action-btn nrt-opp-action-btn--secondary" data-action="view-job">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                                        <polyline points="15 3 21 3 21 9"/>
                                        <line x1="10" y1="14" x2="21" y2="3"/>
                                    </svg>
                                    View Job
                                </button>
                            </div>

                            <div class="nrt-opp-secondary-actions">
                                <button type="button" class="nrt-opp-text-btn" data-action="save-opportunity">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                        <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
                                    </svg>
                                    Save for Later
                                </button>
                                <button type="button" class="nrt-opp-text-btn nrt-opp-text-btn--muted" data-action="not-interested">
                                    Not Interested
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Conversation View (shows when a conversation is selected in Replies tab) -->
                <div class="nrt-conversation-view" id="nrt-conversation-view" style="<?php echo $default_tab === 'replies' ? '' : 'display: none;'; ?>">
                    <?php if ($is_logged_in) : ?>
                    <div class="nrt-conversation-detail">
                        <!-- Placeholder State -->
                        <div class="nrt-conversation-placeholder" id="nrt-conversation-placeholder">
                            <div class="nrt-conversation-placeholder-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48">
                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                </svg>
                            </div>
                            <h4>Select a Conversation</h4>
                            <p>Click on a conversation from the list to view messages</p>
                        </div>

                        <!-- Conversation Content (populated by JS) -->
                        <div class="nrt-conversation-content" id="nrt-conversation-content" style="display: none;">
                            <!-- Conversation Header -->
                            <div class="nrt-conv-header">
                                <div class="nrt-conv-header-info">
                                    <div class="nrt-conv-avatar" id="nrt-conv-avatar">
                                        <span class="nrt-conv-avatar-initial"></span>
                                    </div>
                                    <div class="nrt-conv-contact">
                                        <span class="nrt-conv-name" id="nrt-conv-name"></span>
                                        <span class="nrt-conv-role" id="nrt-conv-role"></span>
                                    </div>
                                </div>
                                <div class="nrt-conv-actions">
                                    <button type="button" class="nrt-conv-action-btn" data-action="star-conversation" title="Star conversation">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                                        </svg>
                                    </button>
                                    <button type="button" class="nrt-conv-action-btn" data-action="view-profile" title="View profile">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                            <circle cx="12" cy="7" r="4"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Messages Area -->
                            <div class="nrt-conv-messages" id="nrt-conv-messages">
                                <!-- Messages populated by JS -->
                            </div>

                            <!-- Message Input -->
                            <div class="nrt-conv-input-area">
                                <div class="nrt-conv-input-wrapper">
                                    <textarea class="nrt-conv-input" id="nrt-conv-input" placeholder="Type your message..." rows="1"></textarea>
                                    <button type="button" class="nrt-conv-send-btn" id="nrt-conv-send" disabled>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                                            <line x1="22" y1="2" x2="11" y2="13"/>
                                            <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                                        </svg>
                                    </button>
                                </div>
                                <div class="nrt-conv-quick-actions">
                                    <button type="button" class="nrt-conv-quick-btn" data-action="schedule-call">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                            <line x1="16" y1="2" x2="16" y2="6"/>
                                            <line x1="8" y1="2" x2="8" y2="6"/>
                                            <line x1="3" y1="10" x2="21" y2="10"/>
                                        </svg>
                                        Schedule Call
                                    </button>
                                    <button type="button" class="nrt-conv-quick-btn" data-action="share-availability">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                            <circle cx="12" cy="12" r="10"/>
                                            <polyline points="12 6 12 12 16 14"/>
                                        </svg>
                                        Share Availability
                                    </button>
                                    <button type="button" class="nrt-conv-quick-btn" data-action="save-to-contacts">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                            <circle cx="8.5" cy="7" r="4"/>
                                            <line x1="20" y1="8" x2="20" y2="14"/>
                                            <line x1="23" y1="11" x2="17" y2="11"/>
                                        </svg>
                                        Save to Contacts
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Profile Dashboard View (shows when Profile tab is active) -->
                <div class="nrt-profile-view" id="nrt-profile-view" style="<?php echo $default_tab === 'profile' ? '' : 'display: none;'; ?>">
                    <?php if ($is_logged_in) :
                        // Get user profile data for visibility calculation
                        $user_headline = get_user_meta($current_user->ID, 'sffc_profile_headline', true);
                        $user_skills = get_user_meta($current_user->ID, 'sffc_skills', true);
                        $user_experience = get_user_meta($current_user->ID, 'sffc_experience_years', true);
                        $user_salary_min = get_user_meta($current_user->ID, 'sffc_salary_min', true);
                        $user_location = get_user_meta($current_user->ID, 'sffc_location', true);
                        $user_cv = get_user_meta($current_user->ID, 'sffc_cv_file', true);
                        $user_certifications = get_user_meta($current_user->ID, 'sffc_certifications', true);

                        // Calculate visibility score
                        $visibility_score = 0;
                        $visibility_items = array();

                        if ($user_avatar) { $visibility_score += 10; $visibility_items['photo'] = true; }
                        if (!empty($user_headline)) { $visibility_score += 10; $visibility_items['headline'] = true; }
                        if (!empty($user_skills) && is_array($user_skills) && count($user_skills) >= 3) { $visibility_score += 15; $visibility_items['skills'] = true; }
                        if (!empty($user_experience)) { $visibility_score += 10; $visibility_items['experience'] = true; }
                        if (!empty($user_salary_min)) { $visibility_score += 10; $visibility_items['salary'] = true; }
                        if (!empty($user_location)) { $visibility_score += 10; $visibility_items['location'] = true; }
                        if (!empty($user_cv)) { $visibility_score += 20; $visibility_items['cv'] = true; }
                        if (!empty($user_certifications)) { $visibility_score += 15; $visibility_items['certifications'] = true; }
                    ?>
                    <div class="nrt-profile-dashboard nrt-profile-dashboard--v2">
                        <!-- Two Column Layout -->
                        <div class="nrt-profile-columns">
                            <!-- Left Column: Profile Card -->
                            <div class="nrt-profile-left">
                                <div class="nrt-profile-card">
                                    <div class="nrt-profile-card-header">
                                        <div class="nrt-profile-avatar-large">
                                            <?php if ($user_avatar) : ?>
                                            <img src="<?php echo esc_url($user_avatar); ?>" alt="<?php echo esc_attr($user_name); ?>">
                                            <?php else : ?>
                                            <span class="nrt-profile-initials"><?php echo esc_html(strtoupper(substr($user_name, 0, 2))); ?></span>
                                            <?php endif; ?>
                                            <button type="button" class="nrt-profile-avatar-edit" data-action="edit-photo" title="Change photo">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                                                    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                                                    <circle cx="12" cy="13" r="4"/>
                                                </svg>
                                            </button>
                                        </div>
                                        <h2 class="nrt-profile-card-name"><?php echo esc_html($current_user->first_name ?: $user_name); ?> <?php echo esc_html($current_user->last_name); ?></h2>
                                        <p class="nrt-profile-card-headline"><?php echo esc_html($user_headline ?: 'Add a professional headline'); ?></p>
                                    </div>

                                    <div class="nrt-profile-card-details">
                                        <div class="nrt-profile-detail-item">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                                <circle cx="12" cy="10" r="3"/>
                                            </svg>
                                            <span><?php echo esc_html($user_location ?: 'Add location'); ?></span>
                                        </div>
                                        <div class="nrt-profile-detail-item">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/>
                                                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                                            </svg>
                                            <span><?php echo $user_experience ? esc_html($user_experience) . ' years experience' : 'Add experience'; ?></span>
                                        </div>
                                        <?php if (!empty($user_certifications)) : ?>
                                        <div class="nrt-profile-detail-item">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                                <circle cx="12" cy="8" r="7"/>
                                                <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/>
                                            </svg>
                                            <span><?php echo esc_html($user_certifications); ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!empty($user_skills) && is_array($user_skills)) : ?>
                                    <div class="nrt-profile-card-skills">
                                        <h4>Skills</h4>
                                        <div class="nrt-profile-skill-chips">
                                            <?php foreach (array_slice($user_skills, 0, 5) as $skill) : ?>
                                            <span class="nrt-skill-chip"><?php echo esc_html($skill); ?></span>
                                            <?php endforeach; ?>
                                            <?php if (count($user_skills) > 5) : ?>
                                            <span class="nrt-skill-chip nrt-skill-chip--more">+<?php echo count($user_skills) - 5; ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (!empty($user_salary_min)) : ?>
                                    <div class="nrt-profile-card-looking">
                                        <h4>Looking For</h4>
                                        <p>$<?php echo number_format($user_salary_min); ?>+ annual salary</p>
                                    </div>
                                    <?php endif; ?>

                                    <div class="nrt-profile-card-actions">
                                        <button type="button" class="nrt-profile-card-btn nrt-profile-card-btn--primary" data-action="edit-profile">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                            </svg>
                                            Edit Profile
                                        </button>
                                        <button type="button" class="nrt-profile-card-btn" data-action="view-as-recruiter">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                                <circle cx="12" cy="12" r="3"/>
                                            </svg>
                                            View as Recruiter
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Column: Score, Stats, Actions -->
                            <div class="nrt-profile-right">
                                <!-- Visibility Score -->
                                <div class="nrt-visibility-score-card">
                                    <div class="nrt-visibility-header">
                                        <h3>Recruiter Visibility</h3>
                                        <span class="nrt-visibility-percent"><?php echo $visibility_score; ?>%</span>
                                    </div>
                                    <p class="nrt-visibility-subtitle">How visible are you to recruiters?</p>
                                    <div class="nrt-visibility-bar">
                                        <div class="nrt-visibility-fill" style="width: <?php echo $visibility_score; ?>%;"></div>
                                    </div>
                                    <div class="nrt-visibility-tips">
                                        <?php if (empty($visibility_items['cv'])) : ?>
                                        <button type="button" class="nrt-visibility-tip" data-action="upload-cv">
                                            <span class="nrt-visibility-tip-plus">+20%</span>
                                            <span>Upload your CV</span>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                                <polyline points="9 18 15 12 9 6"/>
                                            </svg>
                                        </button>
                                        <?php endif; ?>
                                        <?php if (empty($visibility_items['skills'])) : ?>
                                        <button type="button" class="nrt-visibility-tip" data-action="add-skills">
                                            <span class="nrt-visibility-tip-plus">+15%</span>
                                            <span>Add 3+ skills</span>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                                <polyline points="9 18 15 12 9 6"/>
                                            </svg>
                                        </button>
                                        <?php endif; ?>
                                        <?php if (empty($visibility_items['photo'])) : ?>
                                        <button type="button" class="nrt-visibility-tip" data-action="add-photo">
                                            <span class="nrt-visibility-tip-plus">+10%</span>
                                            <span>Add a profile photo</span>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                                <polyline points="9 18 15 12 9 6"/>
                                            </svg>
                                        </button>
                                        <?php endif; ?>
                                        <?php if (empty($visibility_items['headline'])) : ?>
                                        <button type="button" class="nrt-visibility-tip" data-action="add-headline">
                                            <span class="nrt-visibility-tip-plus">+10%</span>
                                            <span>Add a headline</span>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                                <polyline points="9 18 15 12 9 6"/>
                                            </svg>
                                        </button>
                                        <?php endif; ?>
                                        <?php if ($visibility_score >= 100) : ?>
                                        <div class="nrt-visibility-complete">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                                <polyline points="22 4 12 14.01 9 11.01"/>
                                            </svg>
                                            <span>Profile complete! You're fully visible to recruiters.</span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Stats Dashboard -->
                                <div class="nrt-profile-stats-card">
                                    <h3>Your Stats</h3>
                                    <div class="nrt-profile-stats-grid">
                                        <div class="nrt-profile-stat">
                                            <span class="nrt-profile-stat-value" id="nrt-stat-intros">0</span>
                                            <span class="nrt-profile-stat-label">Interest Sent</span>
                                        </div>
                                        <div class="nrt-profile-stat">
                                            <span class="nrt-profile-stat-value" id="nrt-stat-opps">0</span>
                                            <span class="nrt-profile-stat-label">Opportunities</span>
                                        </div>
                                        <div class="nrt-profile-stat">
                                            <span class="nrt-profile-stat-value" id="nrt-stat-chats">0</span>
                                            <span class="nrt-profile-stat-label">Conversations</span>
                                        </div>
                                        <div class="nrt-profile-stat">
                                            <span class="nrt-profile-stat-value" id="nrt-stat-contacts">0</span>
                                            <span class="nrt-profile-stat-label">HR Contacts</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Quick Actions -->
                                <div class="nrt-profile-actions-card">
                                    <h3>Quick Actions</h3>
                                    <div class="nrt-profile-action-btns">
                                        <button type="button" class="nrt-profile-action-btn" data-tab="recruiter-posts">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                                <polyline points="14 2 14 8 20 8"/>
                                                <line x1="16" y1="13" x2="8" y2="13"/>
                                                <line x1="16" y1="17" x2="8" y2="17"/>
                                            </svg>
                                            Recruiter Posts
                                        </button>
                                        <button type="button" class="nrt-profile-action-btn" data-tab="replies">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                            </svg>
                                            Check Replies
                                        </button>
                                        <button type="button" class="nrt-profile-action-btn" data-tab="contacts">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                                <circle cx="9" cy="7" r="4"/>
                                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                            </svg>
                                            HR Contacts
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Activity (Full Width) -->
                        <div class="nrt-profile-activity-section">
                            <div class="nrt-profile-activity-header">
                                <h3>Recent Activity</h3>
                            </div>
                            <div class="nrt-profile-activity-list" id="nrt-profile-activity">
                                <div class="nrt-profile-activity-empty">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="32" height="32">
                                        <circle cx="12" cy="12" r="10"/>
                                        <polyline points="12 6 12 12 16 14"/>
                                    </svg>
                                    <p>No recent activity. Browse recruiter posts to start seeing activity here.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php else : ?>
                    <!-- Not Logged In State -->
                    <div class="nrt-profile-guest">
                        <div class="nrt-profile-guest-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="64" height="64">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                        </div>
                        <h2>Personalize Your Experience</h2>
                        <p>Sign in to customize your feed with topics, industries, and job preferences that matter to you.</p>
                        <div class="nrt-profile-guest-actions">
                            <a href="/login-auth/" class="nrt-profile-btn nrt-profile-btn--primary nrt-profile-btn--large">Sign In</a>
                            <a href="/register/" class="nrt-profile-btn nrt-profile-btn--large">Create Account</a>
                        </div>
                        <div class="nrt-profile-guest-features">
                            <div class="nrt-profile-guest-feature">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                                </svg>
                                <span>Personalized news feed</span>
                            </div>
                            <div class="nrt-profile-guest-feature">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/>
                                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                                </svg>
                                <span>Job matching & alerts</span>
                            </div>
                            <div class="nrt-profile-guest-feature">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                                    <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
                                </svg>
                                <span>Save articles for later</span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </main>

        </div>

        <!-- Mobile Bottom Navigation - LinkedIn Style -->
        <nav class="nrt-mobile-nav" id="nrt-mobile-nav">
            <button type="button" class="nrt-mobile-nav-item <?php echo $is_logged_in ? 'is-active' : ''; ?>" data-mobile-tab="recruiter-posts">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="16" y1="13" x2="8" y2="13"/>
                    <line x1="16" y1="17" x2="8" y2="17"/>
                </svg>
                <span>Posts</span>
            </button>
            <button type="button" class="nrt-mobile-nav-item" data-mobile-tab="replies">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
                <span>Replies</span>
            </button>
            <button type="button" class="nrt-mobile-nav-item" data-mobile-tab="contacts">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
                <span>Contacts</span>
            </button>
            <button type="button" class="nrt-mobile-nav-item" data-mobile-tab="more">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <circle cx="12" cy="12" r="1"/>
                    <circle cx="19" cy="12" r="1"/>
                    <circle cx="5" cy="12" r="1"/>
                </svg>
                <span>More</span>
            </button>
        </nav>

        <?php if ($is_logged_in) : ?>
        <!-- Feed Preferences Modal -->
        <div class="nrt-prefs-modal-overlay" id="nrt-prefs-modal-overlay">
            <div class="nrt-prefs-modal" id="nrt-prefs-modal">
                <div class="nrt-prefs-modal-header">
                    <h2>Customize Your Feed</h2>
                    <p>Select topics and preferences to personalize your intelligence feed</p>
                    <button type="button" class="nrt-prefs-modal-close" id="nrt-prefs-modal-close">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                            <line x1="18" y1="6" x2="6" y2="18"/>
                            <line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                </div>

                <div class="nrt-prefs-modal-body">
                    <!-- Topics Section -->
                    <div class="nrt-prefs-section">
                        <h3 class="nrt-prefs-section-title">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                            </svg>
                            Topics
                        </h3>
                        <p class="nrt-prefs-section-desc">Select the topics you want to follow</p>
                        <div class="nrt-prefs-chips" id="nrt-prefs-topics">
                            <button type="button" class="nrt-prefs-chip" data-topic="private-equity">Private Equity</button>
                            <button type="button" class="nrt-prefs-chip" data-topic="venture-capital">Venture Capital</button>
                            <button type="button" class="nrt-prefs-chip" data-topic="mergers-acquisitions">M&A</button>
                            <button type="button" class="nrt-prefs-chip" data-topic="private-credit">Private Credit</button>
                            <button type="button" class="nrt-prefs-chip" data-topic="real-estate">Real Estate</button>
                            <button type="button" class="nrt-prefs-chip" data-topic="infrastructure">Infrastructure</button>
                            <button type="button" class="nrt-prefs-chip" data-topic="credit">Credit & Debt</button>
                            <button type="button" class="nrt-prefs-chip" data-topic="fundraising">Fundraising</button>
                            <button type="button" class="nrt-prefs-chip" data-topic="exits">Exits & IPOs</button>
                            <button type="button" class="nrt-prefs-chip" data-topic="lp-relations">LP Relations</button>
                            <button type="button" class="nrt-prefs-chip" data-topic="esg">ESG & Impact</button>
                            <button type="button" class="nrt-prefs-chip" data-topic="regulation">Regulation</button>
                        </div>
                    </div>

                    <!-- Industries Section -->
                    <div class="nrt-prefs-section">
                        <h3 class="nrt-prefs-section-title">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                                <polyline points="9 22 9 12 15 12 15 22"/>
                            </svg>
                            Industries
                        </h3>
                        <p class="nrt-prefs-section-desc">Choose industries you're interested in</p>
                        <div class="nrt-prefs-chips" id="nrt-prefs-industries">
                            <button type="button" class="nrt-prefs-chip" data-industry="technology">Technology</button>
                            <button type="button" class="nrt-prefs-chip" data-industry="healthcare">Healthcare</button>
                            <button type="button" class="nrt-prefs-chip" data-industry="financial-services">Financial Services</button>
                            <button type="button" class="nrt-prefs-chip" data-industry="consumer">Consumer</button>
                            <button type="button" class="nrt-prefs-chip" data-industry="industrial">Industrial</button>
                            <button type="button" class="nrt-prefs-chip" data-industry="energy">Energy</button>
                            <button type="button" class="nrt-prefs-chip" data-industry="real-estate">Real Estate</button>
                            <button type="button" class="nrt-prefs-chip" data-industry="media">Media & Entertainment</button>
                            <button type="button" class="nrt-prefs-chip" data-industry="telecommunications">Telecommunications</button>
                            <button type="button" class="nrt-prefs-chip" data-industry="transportation">Transportation</button>
                        </div>
                    </div>

                    <!-- Strategy Section -->
                    <div class="nrt-prefs-section">
                        <h3 class="nrt-prefs-section-title">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="2" y1="12" x2="22" y2="12"/>
                                <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                            </svg>
                            Strategies
                        </h3>
                        <p class="nrt-prefs-section-desc">Select private equity strategies to focus on</p>
                        <div class="nrt-prefs-chips" id="nrt-prefs-regions">
                            <button type="button" class="nrt-prefs-chip is-active" data-region="private_equity">All private equity</button>
                            <button type="button" class="nrt-prefs-chip" data-region="buyout">Buyout</button>
                            <button type="button" class="nrt-prefs-chip" data-region="growth-equity">Growth Equity</button>
                            <button type="button" class="nrt-prefs-chip" data-region="private-credit">Private Credit</button>
                            <button type="button" class="nrt-prefs-chip" data-region="secondaries">Secondaries</button>
                            <button type="button" class="nrt-prefs-chip" data-region="infrastructure">Infrastructure</button>
                            <button type="button" class="nrt-prefs-chip" data-region="portfolio-ops">Portfolio Ops</button>
                            <button type="button" class="nrt-prefs-chip" data-region="investor-relations">Investor Relations</button>
                        </div>
                    </div>

                    <!-- Deal Types Section -->
                    <div class="nrt-prefs-section">
                        <h3 class="nrt-prefs-section-title">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                                <line x1="12" y1="1" x2="12" y2="23"/>
                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                            </svg>
                            Deal Types
                        </h3>
                        <p class="nrt-prefs-section-desc">What types of deals interest you?</p>
                        <div class="nrt-prefs-chips" id="nrt-prefs-deal-types">
                            <button type="button" class="nrt-prefs-chip" data-deal-type="buyout">Buyouts</button>
                            <button type="button" class="nrt-prefs-chip" data-deal-type="growth-equity">Growth Equity</button>
                            <button type="button" class="nrt-prefs-chip" data-deal-type="venture">Venture Deals</button>
                            <button type="button" class="nrt-prefs-chip" data-deal-type="secondaries">Secondaries</button>
                            <button type="button" class="nrt-prefs-chip" data-deal-type="fund-formation">Fund Formation</button>
                            <button type="button" class="nrt-prefs-chip" data-deal-type="recapitalization">Recapitalizations</button>
                            <button type="button" class="nrt-prefs-chip" data-deal-type="distressed">Distressed</button>
                            <button type="button" class="nrt-prefs-chip" data-deal-type="carve-out">Carve-outs</button>
                        </div>
                    </div>

                    <!-- Job Preferences Section -->
                    <div class="nrt-prefs-section">
                        <h3 class="nrt-prefs-section-title">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/>
                                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                            </svg>
                            Job Preferences
                        </h3>
                        <p class="nrt-prefs-section-desc">Filter jobs by level and type</p>
                        <div class="nrt-prefs-chips" id="nrt-prefs-job-levels">
                            <button type="button" class="nrt-prefs-chip" data-job-level="analyst">Manager</button>
                            <button type="button" class="nrt-prefs-chip" data-job-level="associate">Senior Manager</button>
                            <button type="button" class="nrt-prefs-chip" data-job-level="vp">Vice President</button>
                            <button type="button" class="nrt-prefs-chip" data-job-level="director">Director</button>
                            <button type="button" class="nrt-prefs-chip" data-job-level="principal">Principal</button>
                            <button type="button" class="nrt-prefs-chip" data-job-level="partner">Partner / MD</button>
                        </div>
                    </div>

                    <!-- Keywords Section -->
                    <div class="nrt-prefs-section">
                        <h3 class="nrt-prefs-section-title">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                                <circle cx="11" cy="11" r="8"/>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                            </svg>
                            Keywords
                        </h3>
                        <p class="nrt-prefs-section-desc">Add custom keywords to track specific firms, people, or topics</p>
                        <div class="nrt-prefs-keywords-input">
                            <input type="text" id="nrt-prefs-keyword-input" placeholder="Type a keyword and press Enter" maxlength="50">
                            <button type="button" id="nrt-prefs-keyword-add" class="nrt-prefs-keyword-add-btn">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                                    <line x1="12" y1="5" x2="12" y2="19"/>
                                    <line x1="5" y1="12" x2="19" y2="12"/>
                                </svg>
                            </button>
                        </div>
                        <div class="nrt-prefs-keywords-list" id="nrt-prefs-keywords-list">
                            <!-- Keywords will be rendered here -->
                        </div>
                    </div>
                </div>

                <div class="nrt-prefs-modal-footer">
                    <button type="button" class="nrt-prefs-btn nrt-prefs-btn-secondary" id="nrt-prefs-reset">
                        Reset to Default
                    </button>
                    <button type="button" class="nrt-prefs-btn nrt-prefs-btn-primary" id="nrt-prefs-save">
                        <span class="nrt-prefs-btn-text">Save Preferences</span>
                        <span class="nrt-prefs-btn-loading" style="display: none;">
                            <svg class="nrt-prefs-spinner" viewBox="0 0 24 24" width="18" height="18">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31.4 31.4" stroke-linecap="round"/>
                            </svg>
                            Saving...
                        </span>
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!$is_logged_in) : ?>
        <!-- Welcome Modal for Logged-Out Users -->
        <div class="nrt-welcome-modal-overlay" id="nrt-welcome-modal">
            <div class="nrt-welcome-modal">
                <button type="button" class="nrt-welcome-close" id="nrt-welcome-close">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
                <div class="nrt-welcome-icon" id="nrt-welcome-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="40" height="40">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
                <h3 class="nrt-welcome-title" id="nrt-welcome-title">Access HR Contacts</h3>
                <p class="nrt-welcome-desc" id="nrt-welcome-desc">Create a free account to browse 50,000+ verified HR contacts and decision makers.</p>
                <div class="nrt-welcome-benefits">
                    <div class="nrt-welcome-benefit">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        <span id="nrt-welcome-benefit-1">Access contact database</span>
                    </div>
                    <div class="nrt-welcome-benefit">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        <span id="nrt-welcome-benefit-2">Filter by company & seniority</span>
                    </div>
                    <div class="nrt-welcome-benefit">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        <span id="nrt-welcome-benefit-3">Request introductions</span>
                    </div>
                </div>
                <div class="nrt-welcome-actions">
                    <a href="/register/" class="nrt-welcome-btn nrt-welcome-btn--primary">Create Free Account</a>
                    <a href="/login-auth/" class="nrt-welcome-btn nrt-welcome-btn--secondary">Sign In</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!$is_logged_in && !empty($subscription_plans)) : ?>
        <!-- Subscription Box Template (for locked stories) - Dynamic plans from MemberPress -->
        <template id="nrt-subscription-template">
            <div class="nrt-subscription-box">
                <div class="nrt-subscription-header">
                    <div class="nrt-subscription-lock">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                    </div>
                    <h2 class="nrt-subscription-title" data-story-title></h2>
                    <p class="nrt-subscription-desc"><?php esc_html_e('Unlock full access to this story and all premium intelligence.', 'senna-finance'); ?></p>
                </div>
                <div class="nrt-subscription-body">
                    <h3><?php esc_html_e('Choose your membership', 'senna-finance'); ?></h3>
                    <p class="nrt-subscription-tagline"><?php esc_html_e('Pick the plan that fits your workflow. Cancel anytime.', 'senna-finance'); ?></p>
                    <div class="nrt-plan-grid">
                        <?php foreach ($subscription_plans as $plan_index => $plan) :
                            $features = $plan['features'] ?? array();
                            $is_featured = ($plan['slug'] ?? '') === 'elite';
                            $is_free = empty($plan['price_amount']) || floatval($plan['price_amount']) <= 0;
                        ?>
                        <article class="nrt-plan-card <?php echo $is_featured ? 'nrt-plan-card--featured' : ''; ?>" data-plan-card data-plan-slug="<?php echo esc_attr($plan['slug'] ?? ''); ?>">
                            <?php if ($is_featured) : ?>
                            <div class="nrt-plan-card__badge"><?php esc_html_e('Most Popular', 'senna-finance'); ?></div>
                            <?php endif; ?>
                            <div class="nrt-plan-card__head">
                                <h4><?php echo esc_html($plan['name'] ?? ''); ?></h4>
                                <?php
                                $plan_price_markup = '';
                                if (!empty($plan['price_amount']) && !empty($plan['price_currency']) && floatval($plan['price_amount']) > 0) {
                                    $price_shortcode = sprintf('[currency_price amount="%s" base_currency="%s" class="nrt-plan-card__price-value" show_code="false"]', esc_attr($plan['price_amount']), esc_attr($plan['price_currency']));
                                    $plan_price_markup = do_shortcode($price_shortcode);
                                    if (!empty($plan['billing_cycle'])) {
                                        $plan_price_markup .= ' <span class="nrt-plan-card__cycle">' . esc_html($plan['billing_cycle']) . '</span>';
                                    }
                                }
                                ?>
                                <?php if (!empty($plan_price_markup)) : ?>
                                    <p class="nrt-plan-card__price"><?php echo $plan_price_markup; ?></p>
                                <?php elseif (!empty($plan['price'])) : ?>
                                    <p class="nrt-plan-card__price"><?php echo esc_html($plan['price']); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($plan['tagline'])) : ?>
                                <p class="nrt-plan-card__tagline"><?php echo esc_html($plan['tagline']); ?></p>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($features)) : ?>
                            <ul class="nrt-plan-card__list">
                                <?php foreach ($features as $feature) : ?>
                                <li><?php echo esc_html($feature); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                            <?php if (!empty($plan['audience'])) : ?>
                            <p class="nrt-plan-card__audience"><?php echo esc_html($plan['audience']); ?></p>
                            <?php endif; ?>
                            <?php if ($is_free) : ?>
                            <a href="<?php echo esc_url(wp_registration_url()); ?>" class="nrt-plan-select"><?php esc_html_e('Sign up free', 'senna-finance'); ?></a>
                            <?php elseif (!empty($plan['mp_url'])) : ?>
                            <a href="<?php echo esc_url($plan['mp_url']); ?>" class="nrt-plan-select <?php echo $is_featured ? 'nrt-plan-select--primary' : ''; ?>"><?php esc_html_e('Choose plan', 'senna-finance'); ?></a>
                            <?php endif; ?>
                        </article>
                        <?php endforeach; ?>
                    </div>
                    <p class="nrt-subscription-login"><?php esc_html_e('Already a member?', 'senna-finance'); ?> <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>"><?php esc_html_e('Sign in', 'senna-finance'); ?></a></p>
                </div>
            </div>
        </template>
        <?php endif; ?>

    </div>
    <?php
    return ob_get_clean();
}

endif; // End sffc_render_newsroom_terminal

// Prevent function redefinition
if (!function_exists('sffc_render_story_content')) :

if (!function_exists('sffc_nrt_get_cached_article_intel')) :
/**
 * Return article intelligence from persistent post meta, generating it once on demand.
 */
function sffc_nrt_get_cached_article_intel($post_id, $title, $content, $story = array()) {
    $post_id = absint($post_id);
    if (!$post_id) {
        return sffc_nrt_build_article_intel_fallback($title, $content, 'invalid_post');
    }

    $meta_key = '_sffc_article_intel_cache_v1';
    $cached = get_post_meta($post_id, $meta_key, true);
    if (is_array($cached) && !empty($cached['generated_at'])) {
        return $cached;
    }

    $lock_key = 'sffc_article_intel_lock_' . $post_id;
    if (get_transient($lock_key)) {
        return sffc_nrt_build_article_intel_fallback($title, $content, 'generation_pending');
    }

    set_transient($lock_key, 1, 5 * MINUTE_IN_SECONDS);

    $intel = sffc_nrt_generate_claude_article_intel($post_id, $title, $content, $story);
    if (empty($intel) || !is_array($intel)) {
        $intel = sffc_nrt_build_article_intel_fallback($title, $content, 'template_fallback');
    }

    $intel['generated_at'] = current_time('mysql');
    $intel['cache_version'] = 1;
    update_post_meta($post_id, $meta_key, $intel);
    delete_transient($lock_key);

    return $intel;
}
endif;

if (!function_exists('sffc_nrt_generate_claude_article_intel')) :
/**
 * Generate one-off Claude intelligence for an article detail view.
 */
function sffc_nrt_generate_claude_article_intel($post_id, $title, $content, $story = array()) {
    if (!class_exists('SFFC_Claude_API_Manager')) {
        return array();
    }

    $claude = SFFC_Claude_API_Manager::get_instance();
    if (!$claude || !method_exists($claude, 'is_available') || !$claude->is_available()) {
        return array();
    }

    $source_url = get_post_meta($post_id, '_source_url', true);
    $source_name = get_post_meta($post_id, '_source_name', true);
    $article_text = wp_trim_words(wp_strip_all_tags((string) $content), 900, '...');

    $prompt = "Analyze this finance article for MENA Careers readers. Use only the supplied text and metadata. Return ONLY valid JSON with this structure:
{
  \"summary\": \"one concise paragraph\",
  \"why_it_matters\": \"one concise paragraph\",
  \"companies\": [\"company names explicitly mentioned\"],
  \"deal_type\": \"deal type or unknown\",
  \"sector\": \"sector or unknown\",
  \"hiring_signal\": \"what this may indicate for hiring, or unknown\",
  \"candidate_angle\": \"how a finance candidate should interpret this\",
  \"pe_relevance_score\": 0,
  \"key_metrics\": [{\"label\":\"Metric\",\"value\":\"Value\",\"sub\":\"optional note\"}],
  \"takeaways\": [\"takeaway 1\", \"takeaway 2\", \"takeaway 3\"],
  \"charts\": [
    {\"type\":\"bar\",\"title\":\"Chart title\",\"suffix\":\"\",\"data\":[{\"label\":\"Item\",\"value\":10}]},
    {\"type\":\"donut\",\"title\":\"Chart title\",\"data\":[{\"label\":\"Item\",\"value\":50}]}
  ]
}

Rules:
- Never invent company names, people, quotes, deal values, or financial figures.
- Charts are optional. Only include chart values if they can be reasonably derived from the article text.
- If there is not enough numeric data for charts, return an empty charts array.
- pe_relevance_score must be 0-100.

Headline: {$title}
Source: " . ($source_name ?: 'Unknown') . "
Source URL: " . ($source_url ?: 'Unknown') . "
Type: " . ($story['type'] ?? 'news') . "
Sector: " . ($story['sector'] ?? 'unknown') . "
Region: " . ($story['region'] ?? 'unknown') . "
Deal value: " . ($story['deal_value'] ?? 'unknown') . "

Article text:
{$article_text}";

    $result = $claude->send_message($prompt, array(), 'article_intel');
    if (empty($result['response']) || ($result['source'] ?? '') !== 'claude_api') {
        return array();
    }

    return sffc_nrt_parse_article_intel_response($result['response'], $title, $content);
}
endif;

if (!function_exists('sffc_nrt_parse_article_intel_response')) :
/**
 * Parse Claude JSON into the existing newsroom template data shape.
 */
function sffc_nrt_parse_article_intel_response($response, $title, $content) {
    $json_start = strpos($response, '{');
    $json_end = strrpos($response, '}');
    if ($json_start === false || $json_end === false || $json_end <= $json_start) {
        return array();
    }

    $json = substr($response, $json_start, $json_end - $json_start + 1);
    $data = json_decode($json, true);
    if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
        return array();
    }

    $intel = array(
        'charts' => array(),
        'key_metrics' => array(),
        'takeaways' => array(),
        'commentary' => sanitize_text_field($data['summary'] ?? $data['why_it_matters'] ?? ''),
        'article_intel' => array(
            'summary' => sanitize_text_field($data['summary'] ?? ''),
            'why_it_matters' => sanitize_text_field($data['why_it_matters'] ?? ''),
            'companies' => array_values(array_filter(array_map('sanitize_text_field', (array) ($data['companies'] ?? array())))),
            'deal_type' => sanitize_text_field($data['deal_type'] ?? ''),
            'sector' => sanitize_text_field($data['sector'] ?? ''),
            'hiring_signal' => sanitize_text_field($data['hiring_signal'] ?? ''),
            'candidate_angle' => sanitize_text_field($data['candidate_angle'] ?? ''),
            'pe_relevance_score' => max(0, min(100, (int) ($data['pe_relevance_score'] ?? 0))),
        ),
        'source' => 'claude_on_demand',
    );

    foreach (array_slice((array) ($data['key_metrics'] ?? array()), 0, 4) as $metric) {
        if (!is_array($metric) || empty($metric['label']) || empty($metric['value'])) {
            continue;
        }
        $intel['key_metrics'][] = array(
            'label' => sanitize_text_field($metric['label']),
            'value' => sanitize_text_field($metric['value']),
            'sub' => sanitize_text_field($metric['sub'] ?? ''),
        );
    }

    foreach (array_slice((array) ($data['takeaways'] ?? array()), 0, 4) as $takeaway) {
        $takeaway = sanitize_text_field($takeaway);
        if ($takeaway !== '') {
            $intel['takeaways'][] = $takeaway;
        }
    }

    foreach (array_slice((array) ($data['charts'] ?? array()), 0, 3) as $chart) {
        if (!is_array($chart) || empty($chart['title']) || empty($chart['data']) || !is_array($chart['data'])) {
            continue;
        }
        $type = in_array(($chart['type'] ?? 'bar'), array('bar', 'donut', 'pie'), true) ? $chart['type'] : 'bar';
        $items = array();
        foreach (array_slice($chart['data'], 0, 6) as $item) {
            if (!is_array($item) || empty($item['label']) || !isset($item['value']) || !is_numeric($item['value'])) {
                continue;
            }
            $items[] = array(
                'label' => sanitize_text_field($item['label']),
                'value' => (float) $item['value'],
            );
        }
        if (empty($items)) {
            continue;
        }
        $intel['charts'][] = array(
            'type' => $type === 'pie' ? 'donut' : $type,
            'title' => sanitize_text_field($chart['title']),
            'subtitle' => '',
            'data' => $type === 'bar'
                ? array('series' => $items, 'suffix' => sanitize_text_field($chart['suffix'] ?? ''))
                : array('slices' => $items, 'centerValue' => '', 'centerLabel' => 'Share'),
            'source' => 'Source: MENA Careers Intelligence',
        );
    }

    if (empty($intel['takeaways'])) {
        $intel['takeaways'] = sffc_nrt_extract_basic_takeaways($content);
    }

    return $intel;
}
endif;

if (!function_exists('sffc_nrt_build_article_intel_fallback')) :
/**
 * Cheap fallback used only when Claude is unavailable or another request is generating.
 */
function sffc_nrt_build_article_intel_fallback($title, $content, $source = 'template_fallback') {
    $metrics = array();
    preg_match_all('/\$?\s*(\d+(?:\.\d+)?)\s*(billion|million|bn|mn|%)/i', (string) $content, $matches, PREG_SET_ORDER);
    foreach (array_slice($matches, 0, 4) as $match) {
        $metrics[] = array(
            'label' => 'Reported figure',
            'value' => trim($match[0]),
            'sub' => 'Extracted from article text',
        );
    }

    return array(
        'charts' => array(),
        'key_metrics' => $metrics,
        'takeaways' => sffc_nrt_extract_basic_takeaways($content),
        'commentary' => wp_trim_words(wp_strip_all_tags((string) $content), 36, '...'),
        'article_intel' => array(
            'summary' => wp_trim_words(wp_strip_all_tags((string) $content), 42, '...'),
            'why_it_matters' => '',
            'companies' => array(),
            'deal_type' => '',
            'sector' => '',
            'hiring_signal' => '',
            'candidate_angle' => '',
            'pe_relevance_score' => 0,
        ),
        'source' => $source,
    );
}
endif;

if (!function_exists('sffc_nrt_extract_basic_takeaways')) :
function sffc_nrt_extract_basic_takeaways($content) {
    $sentences = preg_split('/(?<=[.!?])\s+/', wp_strip_all_tags((string) $content));
    $takeaways = array();
    foreach ((array) $sentences as $sentence) {
        $sentence = trim($sentence);
        if (strlen($sentence) < 55) {
            continue;
        }
        $takeaways[] = wp_trim_words($sentence, 26, '...');
        if (count($takeaways) >= 3) {
            break;
        }
    }
    return $takeaways;
}
endif;

/**
 * Render individual story content for the right panel
 * Well-formatted article with integrated charts and data
 */
function sffc_render_story_content($story) {
    if (empty($story)) {
        return '<div class="nrt-content-empty">Select a story to read</div>';
    }

    $post_id = $story['id'];
    $post = get_post($post_id);

    if (!$post) {
        return '<div class="nrt-content-empty">Story not found</div>';
    }

    $enhanced_data = sffc_nrt_get_cached_article_intel($post_id, $story['title'], $post->post_content, $story);

    $charts = $enhanced_data['charts'] ?? array();
    $key_metrics = $enhanced_data['key_metrics'] ?? array();
    $takeaways = $enhanced_data['takeaways'] ?? array();
    $reading_time = ceil(str_word_count(wp_strip_all_tags($post->post_content)) / 200);

    // Parse content into sections for better formatting
    $raw_content = $post->post_content;
    $sections = sffc_nrt_get_content_sections($post_id, $raw_content);

    // Get source info
    $source_url = get_post_meta($post_id, '_source_url', true);
    $source_name = get_post_meta($post_id, '_source_name', true);

    // Get author/analyst data
    $author = sffc_nrt_get_author_data($post_id);

    // Get content type label for methodology badge
    $content_type_label = sffc_nrt_get_content_type_label($story);

    // Get related research
    $related_research = sffc_nrt_get_related_research($post_id, $story['sector'] ?? '', 3);

    // Get publication date
    $date_published = get_the_date('M j, Y', $post_id);
    $date_modified = get_the_modified_date('M j, Y', $post_id);

    // Generate unique ID for chart data
    $chart_data_id = 'nrt-charts-' . $post_id . '-' . wp_rand(100, 999);

    ob_start();
    ?>
    <?php if (!empty($charts)) : ?>
    <script type="application/json" id="<?php echo esc_attr($chart_data_id); ?>">
    <?php echo wp_json_encode($charts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
    </script>
    <?php endif; ?>

    <article class="nrt-article" data-story-id="<?php echo esc_attr($post_id); ?>" data-charts-id="<?php echo esc_attr($chart_data_id); ?>">

        <!-- Article Header -->
        <header class="nrt-article-header">
            <div class="nrt-article-meta-row">
                <span class="nrt-methodology-badge">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                    </svg>
                    <?php echo esc_html($content_type_label); ?>
                </span>
                <span class="nrt-article-type nrt-type-<?php echo esc_attr($story['type']); ?>">
                    <?php echo esc_html(ucfirst($story['type'])); ?>
                </span>
                <?php if (!empty($story['sector'])) : ?>
                <span class="nrt-article-sector"><?php echo esc_html($story['sector']); ?></span>
                <?php endif; ?>
            </div>

            <h1 class="nrt-article-title"><?php echo esc_html($story['title']); ?></h1>

            <?php if (!empty($story['company']) || !empty($story['deal_value'])) : ?>
            <div class="nrt-article-badges">
                <?php if (!empty($story['company'])) : ?>
                <span class="nrt-company-badge">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                        <path d="M3 21h18M9 8h1M9 12h1M9 16h1M14 8h1M14 12h1M14 16h1M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"/>
                    </svg>
                    <?php echo esc_html($story['company']); ?>
                </span>
                <?php endif; ?>
                <?php if (!empty($story['deal_value'])) : ?>
                <span class="nrt-deal-badge">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                        <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                    <?php echo esc_html($story['deal_value']); ?>
                </span>
                <?php endif; ?>
                <?php if (!empty($story['region'])) : ?>
                <span class="nrt-region-badge">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="2" y1="12" x2="22" y2="12"/>
                        <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                    </svg>
                    <?php echo esc_html($story['region']); ?>
                </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Author/Analyst Byline - Google News Compatible -->
            <div class="nrt-article-byline" itemscope itemtype="https://schema.org/Person">
                <?php if (!empty($author['avatar'])) : ?>
                <img class="nrt-author-avatar"
                     src="<?php echo esc_url($author['avatar']); ?>"
                     alt="<?php echo esc_attr($author['name']); ?>"
                     itemprop="image"
                     width="48"
                     height="48"
                     loading="lazy">
                <?php else : ?>
                <div class="nrt-author-avatar-placeholder">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                </div>
                <?php endif; ?>
                <div class="nrt-author-info">
                    <a href="<?php echo esc_url($author['url']); ?>" class="nrt-author-name" itemprop="name" rel="author"><?php echo esc_html($author['name']); ?></a>
                    <div class="nrt-author-meta">
                        <span class="nrt-author-title" itemprop="jobTitle"><?php echo esc_html($author['title']); ?></span>
                        <span class="nrt-meta-dot"></span>
                        <time datetime="<?php echo esc_attr(get_the_date('c', $post_id)); ?>" itemprop="datePublished"><?php echo esc_html($date_published); ?></time>
                        <span class="nrt-meta-dot"></span>
                        <span><?php echo esc_html($reading_time); ?> min read</span>
                    </div>
                </div>
                <meta itemprop="url" content="<?php echo esc_url($author['url']); ?>">
                <div class="nrt-article-actions-top">
                    <button class="nrt-pdf-btn" id="nrt-pdf-download" title="Download PDF">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                            <line x1="12" y1="18" x2="12" y2="12"/>
                            <polyline points="9 15 12 18 15 15"/>
                        </svg>
                        <span>PDF</span>
                    </button>
                </div>
            </div>
        </header>

        <!-- View Toggle -->
        <div class="nrt-view-toggle-container">
            <div class="nrt-view-toggle" role="tablist" aria-label="View mode">
                <button type="button"
                        class="nrt-view-toggle-btn is-active"
                        data-view="report"
                        role="tab"
                        aria-selected="true"
                        aria-controls="nrt-report-view">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                    </svg>
                    <span>Report</span>
                </button>
                <button type="button"
                        class="nrt-view-toggle-btn"
                        data-view="data"
                        role="tab"
                        aria-selected="false"
                        aria-controls="nrt-data-view">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7"/>
                        <rect x="14" y="3" width="7" height="7"/>
                        <rect x="14" y="14" width="7" height="7"/>
                        <rect x="3" y="14" width="7" height="7"/>
                    </svg>
                    <span>Data</span>
                </button>
            </div>
        </div>

        <!-- REPORT VIEW (Default) -->
        <div class="nrt-panel-view nrt-report-view is-active" id="nrt-report-view" role="tabpanel">

        <!-- Executive Summary - Key Summary Box -->
        <?php
        $commentary = $enhanced_data['commentary'] ?? '';
        $has_summary_content = !empty($key_metrics) || !empty($takeaways) || !empty($commentary);
        ?>
        <?php if ($has_summary_content) : ?>
        <div class="nrt-executive-summary">
            <div class="nrt-exec-header">
                <h2 class="nrt-exec-label">Key Summary</h2>
                <span class="nrt-exec-badge">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12">
                        <path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm0 18a8 8 0 1 1 8-8 8 8 0 0 1-8 8z"/>
                        <path d="M12 6v6l4 2"/>
                    </svg>
                    AI Analysis
                </span>
            </div>

            <?php if (!empty($commentary)) : ?>
            <p class="nrt-exec-thesis"><?php echo esc_html($commentary); ?></p>
            <?php endif; ?>

            <?php if (!empty($key_metrics)) : ?>
            <div class="nrt-exec-metrics">
                <?php foreach (array_slice($key_metrics, 0, 4) as $i => $metric) :
                    $is_primary = $i === 0;
                ?>
                <div class="nrt-exec-metric">
                    <span class="nrt-exec-metric-value<?php echo $is_primary ? ' is-highlight' : ''; ?>"><?php echo esc_html($metric['value']); ?></span>
                    <span class="nrt-exec-metric-label"><?php echo esc_html($metric['label']); ?></span>
                    <?php if (!empty($metric['sub'])) : ?>
                    <span class="nrt-exec-metric-sub"><?php echo esc_html($metric['sub']); ?></span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($takeaways)) : ?>
            <div class="nrt-exec-takeaways">
                <h3 class="nrt-exec-takeaways-title">Key Takeaways</h3>
                <ul class="nrt-exec-takeaways-list">
                    <?php foreach ($takeaways as $index => $takeaway) : ?>
                    <li>
                        <span class="nrt-takeaway-num"><?php echo $index + 1; ?></span>
                        <span class="nrt-takeaway-text"><?php echo wp_kses($takeaway, array('strong' => array(), 'b' => array(), 'em' => array())); ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Main Article Content with Integrated Charts -->
        <div class="nrt-article-flow">

            <?php
            // Distribute charts throughout content
            $chart_count = count($charts);
            $section_count = count($sections);
            $charts_per_section = $chart_count > 0 && $section_count > 1 ? ceil($chart_count / max(1, $section_count - 1)) : 0;
            $chart_index = 0;

            foreach ($sections as $i => $section) :
                $section_title = $section['title'] ?? '';
                $section_content = $section['content'] ?? '';
                $section_type = $section['type'] ?? 'content';

                if (empty(trim(wp_strip_all_tags($section_content)))) continue;
            ?>

            <section class="nrt-content-section nrt-section-<?php echo esc_attr($section_type); ?>">
                <?php if (!empty($section_title)) : ?>
                <h2 class="nrt-section-title">
                    <span class="nrt-section-icon">
                        <?php echo sffc_nrt_get_section_icon($section_title); ?>
                    </span>
                    <?php echo esc_html($section_title); ?>
                </h2>
                <?php endif; ?>

                <div class="nrt-section-content">
                    <?php echo wp_kses_post($section_content); ?>
                </div>

                <?php
                // Insert chart after every section to ensure charts are well-distributed
                // Aim for minimum 4 charts through content
                if ($chart_index < $chart_count && ($i > 0 || $section_count <= 2)) :
                    $chart = $charts[$chart_index];
                    $chart_narrative = sffc_nrt_generate_chart_narrative($chart, $chart_index);
                    $chart_index++;
                ?>
                <div class="nrt-inline-chart">
                    <div class="nrt-chart-card">
                        <div class="nrt-chart-header">
                            <div class="nrt-chart-icon">
                                <?php echo sffc_nrt_get_chart_icon($chart['type'] ?? 'bar'); ?>
                            </div>
                            <div class="nrt-chart-titles">
                                <h4 class="nrt-chart-title"><?php echo esc_html($chart['title']); ?></h4>
                                <?php if (!empty($chart['subtitle'])) : ?>
                                <p class="nrt-chart-subtitle"><?php echo esc_html($chart['subtitle']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="nrt-chart-body"
                             data-chart="<?php echo esc_attr($chart['type'] ?? 'bar'); ?>"
                             data-chart-data='<?php echo esc_attr(wp_json_encode($chart['data'] ?? $chart, JSON_HEX_APOS | JSON_HEX_QUOT)); ?>'>
                        </div>
                        <?php if (!empty($chart_narrative['points'])) : ?>
                        <div class="nrt-chart-narrative">
                            <div class="nrt-chart-narrative-header">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/>
                                    <line x1="16" y1="13" x2="8" y2="13"/>
                                    <line x1="16" y1="17" x2="8" y2="17"/>
                                </svg>
                                <span>Chart Analysis</span>
                            </div>
                            <ul class="nrt-chart-narrative-points">
                                <?php foreach ($chart_narrative['points'] as $point) : ?>
                                <li><?php echo esc_html($point); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                        <div class="nrt-chart-footer">
                            <span class="nrt-chart-source"><?php echo esc_html($chart['source'] ?? 'Source: MENA Careers Research'); ?></span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </section>

            <?php endforeach; ?>

            <?php
            // Render remaining charts at the end
            if ($chart_index < $chart_count) :
            ?>
            <section class="nrt-content-section nrt-charts-section">
                <h2 class="nrt-section-title">
                    <span class="nrt-section-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="20" x2="18" y2="10"/>
                            <line x1="12" y1="20" x2="12" y2="4"/>
                            <line x1="6" y1="20" x2="6" y2="14"/>
                        </svg>
                    </span>
                    Data & Analysis
                </h2>

                <div class="nrt-charts-grid">
                    <?php for ($j = $chart_index; $j < $chart_count; $j++) :
                        $chart = $charts[$j];
                        $chart_narrative = sffc_nrt_generate_chart_narrative($chart, $j);
                    ?>
                    <div class="nrt-chart-card">
                        <div class="nrt-chart-header">
                            <div class="nrt-chart-icon">
                                <?php echo sffc_nrt_get_chart_icon($chart['type'] ?? 'bar'); ?>
                            </div>
                            <div class="nrt-chart-titles">
                                <h4 class="nrt-chart-title"><?php echo esc_html($chart['title']); ?></h4>
                                <?php if (!empty($chart['subtitle'])) : ?>
                                <p class="nrt-chart-subtitle"><?php echo esc_html($chart['subtitle']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="nrt-chart-body"
                             data-chart="<?php echo esc_attr($chart['type'] ?? 'bar'); ?>"
                             data-chart-data='<?php echo esc_attr(wp_json_encode($chart['data'] ?? $chart, JSON_HEX_APOS | JSON_HEX_QUOT)); ?>'>
                        </div>
                        <?php if (!empty($chart_narrative['points'])) : ?>
                        <div class="nrt-chart-narrative">
                            <div class="nrt-chart-narrative-header">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/>
                                    <line x1="16" y1="13" x2="8" y2="13"/>
                                    <line x1="16" y1="17" x2="8" y2="17"/>
                                </svg>
                                <span>Chart Analysis</span>
                            </div>
                            <ul class="nrt-chart-narrative-points">
                                <?php foreach ($chart_narrative['points'] as $point) : ?>
                                <li><?php echo esc_html($point); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                        <div class="nrt-chart-footer">
                            <span class="nrt-chart-source"><?php echo esc_html($chart['source'] ?? 'Source: MENA Careers Research'); ?></span>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </section>
            <?php endif; ?>

        </div>

        <!-- Source Attribution -->
        <?php if ($source_url || $source_name) : ?>
        <div class="nrt-source-attribution">
            <div class="nrt-source-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                    <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
                </svg>
            </div>
            <div class="nrt-source-text">
                <span class="nrt-source-label">Based on reporting from</span>
                <?php if ($source_url) : ?>
                <a href="<?php echo esc_url($source_url); ?>" class="nrt-source-link" target="_blank" rel="noopener">
                    <?php echo esc_html($source_name ?: parse_url($source_url, PHP_URL_HOST)); ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12">
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                        <polyline points="15 3 21 3 21 9"/>
                        <line x1="10" y1="14" x2="21" y2="3"/>
                    </svg>
                </a>
                <?php else : ?>
                <span class="nrt-source-name"><?php echo esc_html($source_name); ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Related Research -->
        <?php if (!empty($related_research)) : ?>
        <div class="nrt-related-research">
            <div class="nrt-related-header">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                </svg>
                <h3 class="nrt-related-title">Related Research</h3>
            </div>
            <div class="nrt-related-list">
                <?php foreach ($related_research as $related) : ?>
                <a href="<?php echo esc_url($related['link']); ?>" class="nrt-related-item">
                    <div class="nrt-related-item-content">
                        <span class="nrt-related-item-type"><?php echo esc_html(ucfirst(str_replace('sffc_pe_', '', $related['type']))); ?></span>
                        <h4 class="nrt-related-item-title"><?php echo esc_html($related['title']); ?></h4>
                        <span class="nrt-related-item-date"><?php echo esc_html($related['date']); ?></span>
                    </div>
                    <svg class="nrt-related-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                        <polyline points="9 18 15 12 9 6"/>
                    </svg>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        </div><!-- END REPORT VIEW -->

        <!-- DATA VIEW (Spreadsheet-style) -->
        <div class="nrt-panel-view nrt-data-view" id="nrt-data-view" role="tabpanel" style="display: none;">
            <div class="nrt-spreadsheet">
                <div class="nrt-spreadsheet-header">
                    <div class="nrt-spreadsheet-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                            <line x1="16" y1="13" x2="8" y2="13"/>
                            <line x1="16" y1="17" x2="8" y2="17"/>
                        </svg>
                        <span>Data Extract</span>
                    </div>
                    <button type="button" class="nrt-excel-btn" id="nrt-excel-download" title="Download as Excel">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                            <line x1="12" y1="18" x2="12" y2="12"/>
                            <polyline points="9 15 12 18 15 15"/>
                        </svg>
                        <span>Excel</span>
                    </button>
                </div>

                <table class="nrt-data-table">
                    <thead>
                        <tr>
                            <th class="nrt-col-row"></th>
                            <th class="nrt-col-a">A</th>
                            <th class="nrt-col-b">B</th>
                            <th class="nrt-col-c">C</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $row_num = 1;

                        // ========================================
                        // SECTION: Article Info
                        // ========================================
                        ?>
                        <tr class="nrt-row-header">
                            <td class="nrt-row-num"><?php echo $row_num++; ?></td>
                            <td class="nrt-cell nrt-cell-header" colspan="3"><?php echo esc_html($story['title']); ?></td>
                        </tr>
                        <tr>
                            <td class="nrt-row-num"><?php echo $row_num++; ?></td>
                            <td class="nrt-cell">Type</td>
                            <td class="nrt-cell nrt-cell-input"><?php echo esc_html($content_type_label); ?></td>
                            <td class="nrt-cell nrt-cell-type">Classification</td>
                        </tr>
                        <tr>
                            <td class="nrt-row-num"><?php echo $row_num++; ?></td>
                            <td class="nrt-cell">Published</td>
                            <td class="nrt-cell nrt-cell-input"><?php echo esc_html($date_published); ?></td>
                            <td class="nrt-cell nrt-cell-type">Date</td>
                        </tr>
                        <?php if (!empty($story['sector'])) : ?>
                        <tr>
                            <td class="nrt-row-num"><?php echo $row_num++; ?></td>
                            <td class="nrt-cell">Sector</td>
                            <td class="nrt-cell nrt-cell-input"><?php echo esc_html($story['sector']); ?></td>
                            <td class="nrt-cell nrt-cell-type">Category</td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($story['region'])) : ?>
                        <tr>
                            <td class="nrt-row-num"><?php echo $row_num++; ?></td>
                            <td class="nrt-cell">Region</td>
                            <td class="nrt-cell nrt-cell-input"><?php echo esc_html($story['region']); ?></td>
                            <td class="nrt-cell nrt-cell-type">Geography</td>
                        </tr>
                        <?php endif; ?>
                        <tr class="nrt-row-spacer">
                            <td class="nrt-row-num"><?php echo $row_num++; ?></td>
                            <td class="nrt-cell" colspan="3"></td>
                        </tr>

                        <?php
                        // ========================================
                        // SECTION: Key Metrics
                        // ========================================
                        if (!empty($key_metrics)) :
                        ?>
                        <tr class="nrt-row-header">
                            <td class="nrt-row-num"><?php echo $row_num++; ?></td>
                            <td class="nrt-cell nrt-cell-header" colspan="3">Key Metrics</td>
                        </tr>
                        <tr class="nrt-row-subheader">
                            <td class="nrt-row-num"><?php echo $row_num++; ?></td>
                            <td class="nrt-cell nrt-cell-label">Metric</td>
                            <td class="nrt-cell nrt-cell-label">Value</td>
                            <td class="nrt-cell nrt-cell-label">Note</td>
                        </tr>
                        <?php foreach ($key_metrics as $metric) : ?>
                        <tr>
                            <td class="nrt-row-num"><?php echo $row_num++; ?></td>
                            <td class="nrt-cell"><?php echo esc_html($metric['label'] ?? ''); ?></td>
                            <td class="nrt-cell nrt-cell-input"><?php echo esc_html($metric['value'] ?? ''); ?></td>
                            <td class="nrt-cell nrt-cell-type"><?php echo esc_html($metric['sub'] ?? ''); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="nrt-row-spacer">
                            <td class="nrt-row-num"><?php echo $row_num++; ?></td>
                            <td class="nrt-cell" colspan="3"></td>
                        </tr>
                        <?php endif; ?>

                        <?php
                        // ========================================
                        // SECTION: Chart Data
                        // ========================================
                        if (!empty($charts)) :
                            foreach ($charts as $c_index => $chart) :
                                $chart_title = $chart['title'] ?? 'Data Set ' . ($c_index + 1);
                                $chart_type = $chart['type'] ?? 'bar';
                                $chart_data = $chart['data'] ?? $chart;
                                $chart_source = $chart['source'] ?? 'MENA Careers Research';

                                // Get the data array based on chart type
                                $data_items = array();
                                $value_suffix = '';

                                if ($chart_type === 'bar' && !empty($chart_data['series'])) {
                                    $data_items = $chart_data['series'];
                                    $value_suffix = $chart_data['suffix'] ?? '';
                                } elseif (($chart_type === 'donut' || $chart_type === 'pie') && !empty($chart_data['slices'])) {
                                    $data_items = $chart_data['slices'];
                                    $value_suffix = '%';
                                } elseif ($chart_type === 'line' && !empty($chart_data['points'])) {
                                    $data_items = $chart_data['points'];
                                    $value_suffix = $chart_data['suffix'] ?? '';
                                }

                                if (empty($data_items)) continue;
                        ?>
                        <tr class="nrt-row-header">
                            <td class="nrt-row-num"><?php echo $row_num++; ?></td>
                            <td class="nrt-cell nrt-cell-header" colspan="3"><?php echo esc_html($chart_title); ?></td>
                        </tr>
                        <tr class="nrt-row-subheader">
                            <td class="nrt-row-num"><?php echo $row_num++; ?></td>
                            <td class="nrt-cell nrt-cell-label">Item</td>
                            <td class="nrt-cell nrt-cell-label">Value</td>
                            <td class="nrt-cell nrt-cell-label">Type</td>
                        </tr>
                        <?php
                        foreach ($data_items as $item) :
                            $item_label = $item['label'] ?? $item['name'] ?? '';
                            $item_value = $item['value'] ?? '';

                            // Format value with suffix
                            $formatted_value = $item_value;
                            if (is_numeric($item_value)) {
                                if ($value_suffix === '%') {
                                    $formatted_value = $item_value . '%';
                                } elseif ($value_suffix) {
                                    $formatted_value = $item_value . ' ' . $value_suffix;
                                }
                            }

                            // Determine type label
                            $type_label = 'Data';
                            if ($chart_type === 'donut' || $chart_type === 'pie') {
                                $type_label = 'Share';
                            }
                        ?>
                        <tr>
                            <td class="nrt-row-num"><?php echo $row_num++; ?></td>
                            <td class="nrt-cell"><?php echo esc_html($item_label); ?></td>
                            <td class="nrt-cell nrt-cell-input"><?php echo esc_html($formatted_value); ?></td>
                            <td class="nrt-cell nrt-cell-type"><?php echo esc_html($type_label); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="nrt-row-source">
                            <td class="nrt-row-num"><?php echo $row_num++; ?></td>
                            <td class="nrt-cell nrt-cell-label">Source</td>
                            <td class="nrt-cell nrt-cell-external" colspan="2"><?php echo esc_html(str_replace('Source: ', '', $chart_source)); ?></td>
                        </tr>
                        <tr class="nrt-row-spacer">
                            <td class="nrt-row-num"><?php echo $row_num++; ?></td>
                            <td class="nrt-cell" colspan="3"></td>
                        </tr>
                        <?php
                            endforeach;
                        endif;
                        ?>

                        <?php
                        // ========================================
                        // SECTION: Takeaways
                        // ========================================
                        if (!empty($takeaways)) :
                        ?>
                        <tr class="nrt-row-header">
                            <td class="nrt-row-num"><?php echo $row_num++; ?></td>
                            <td class="nrt-cell nrt-cell-header" colspan="3">Key Takeaways</td>
                        </tr>
                        <?php foreach ($takeaways as $t_index => $takeaway) : ?>
                        <tr>
                            <td class="nrt-row-num"><?php echo $row_num++; ?></td>
                            <td class="nrt-cell"><?php echo ($t_index + 1); ?></td>
                            <td class="nrt-cell" colspan="2"><?php echo esc_html(wp_strip_all_tags($takeaway)); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div class="nrt-spreadsheet-footer">
                    <div class="nrt-sheet-tabs">
                        <span class="nrt-sheet-tab is-active">All Data</span>
                    </div>
                    <span class="nrt-spreadsheet-note"><?php echo $row_num - 1; ?> rows extracted</span>
                </div>
            </div>
        </div><!-- END DATA VIEW -->

        <!-- Author Bio Box - Google News Compatible -->
        <div class="nrt-author-bio-box" itemscope itemtype="https://schema.org/Person">
            <div class="nrt-author-bio-header">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
                <span>About the Author</span>
            </div>
            <div class="nrt-author-bio-content">
                <div class="nrt-author-bio-avatar">
                    <?php if (!empty($author['avatar'])) : ?>
                    <img src="<?php echo esc_url($author['avatar']); ?>"
                         alt="<?php echo esc_attr($author['name']); ?>"
                         itemprop="image"
                         width="80"
                         height="80"
                         loading="lazy">
                    <?php else : ?>
                    <div class="nrt-author-bio-avatar-placeholder">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="nrt-author-bio-details">
                    <a href="<?php echo esc_url($author['url']); ?>" class="nrt-author-bio-name" itemprop="name" rel="author">
                        <?php echo esc_html($author['name']); ?>
                    </a>
                    <?php if (!empty($author['title'])) : ?>
                    <span class="nrt-author-bio-title" itemprop="jobTitle"><?php echo esc_html($author['title']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($author['bio'])) : ?>
                    <p class="nrt-author-bio-description" itemprop="description">
                        <?php echo esc_html($author['bio']); ?>
                    </p>
                    <?php endif; ?>
                    <?php if (!empty($author['social_profiles'])) : ?>
                    <div class="nrt-author-bio-social">
                        <?php foreach ($author['social_profiles'] as $profile) :
                            $icon = '';
                            $label = '';
                            if (strpos($profile, 'linkedin') !== false) {
                                $label = 'LinkedIn';
                                $icon = '<svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>';
                            } elseif (strpos($profile, 'twitter') !== false || strpos($profile, 'x.com') !== false) {
                                $label = 'X (Twitter)';
                                $icon = '<svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>';
                            }
                        ?>
                        <a href="<?php echo esc_url($profile); ?>"
                           class="nrt-author-bio-social-link"
                           target="_blank"
                           rel="noopener"
                           title="<?php echo esc_attr($label); ?>"
                           itemprop="sameAs">
                            <?php echo $icon; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <meta itemprop="url" content="<?php echo esc_url($author['url']); ?>">
            </div>
        </div>

        <!-- Article Footer -->
        <footer class="nrt-article-footer">
            <div class="nrt-article-actions">
                <a href="<?php echo esc_url($story['link']); ?>" class="nrt-action-btn nrt-action-primary" target="_blank">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                        <polyline points="15 3 21 3 21 9"/>
                        <line x1="10" y1="14" x2="21" y2="3"/>
                    </svg>
                    Open Full Article
                </a>
                <button class="nrt-action-btn" data-action="save">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
                    </svg>
                    Save
                </button>
                <button class="nrt-action-btn" data-action="share">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="18" cy="5" r="3"/>
                        <circle cx="6" cy="12" r="3"/>
                        <circle cx="18" cy="19" r="3"/>
                        <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/>
                        <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>
                    </svg>
                    Share
                </button>
            </div>
            <div class="nrt-article-tags">
                <?php if (!empty($story['sector'])) : ?>
                <span class="nrt-tag"><?php echo esc_html($story['sector']); ?></span>
                <?php endif; ?>
                <?php if (!empty($story['region'])) : ?>
                <span class="nrt-tag"><?php echo esc_html($story['region']); ?></span>
                <?php endif; ?>
                <?php if (!empty($story['deal_type'])) : ?>
                <span class="nrt-tag"><?php echo esc_html($story['deal_type']); ?></span>
                <?php endif; ?>
            </div>
        </footer>
    </article>
    <?php
    return ob_get_clean();
}

endif; // End sffc_render_story_content

// Prevent function redefinition
if (!function_exists('sffc_render_job_content')) :

/**
 * Render individual job content for the right panel
 * Uses the advanced job opportunities class for rich job intelligence
 */
function sffc_render_job_content($job) {
    if (empty($job)) {
        return '<div class="nrt-content-empty">Select a job to view details</div>';
    }

    $job_id = $job['id'] ?? 0;

    if (!$job_id || get_post_type($job_id) !== 'sffc_job') {
        return '<div class="nrt-content-empty">Job not found</div>';
    }

    // Use the advanced job opportunities class for rich content
    if (class_exists('SFFC_Job_Opportunities_Advanced')) {
        $job_advanced = SFFC_Job_Opportunities_Advanced::get_instance();
        return $job_advanced->render_for_newsroom($job_id);
    }

    // Fallback to basic content if class not available
    $post = get_post($job_id);
    if (!$post) {
        return '<div class="nrt-content-empty">Job not found</div>';
    }

    ob_start();
    ?>
    <article class="nrt-article nrt-job-article" data-job-id="<?php echo esc_attr($job_id); ?>">
        <header class="nrt-article-header">
            <h1 class="nrt-article-title"><?php echo esc_html($post->post_title); ?></h1>
        </header>
        <div class="nrt-article-body">
            <div class="nrt-description-content">
                <?php echo wp_kses_post($post->post_content); ?>
            </div>
        </div>
    </article>
    <?php
    return ob_get_clean();
}

endif; // End sffc_render_job_content

/**
 * Get content sections - prioritizes AI-generated sections from post meta
 * Falls back to parsing content by headings if no structured sections exist
 */
if (!function_exists('sffc_nrt_get_content_sections')) :
function sffc_nrt_get_content_sections($post_id, $content) {
    // First try to get AI-generated sections from post meta (same as institutional article)
    $sections_json = get_post_meta($post_id, '_article_sections', true);

    if (!empty($sections_json)) {
        $article_sections = json_decode($sections_json, true);

        if (!empty($article_sections) && is_array($article_sections)) {
            // Verify sections have real content
            $valid_sections = array();
            foreach ($article_sections as $section) {
                $section_content = wp_strip_all_tags($section['content'] ?? '');
                // Only include sections with meaningful content
                if (strlen($section_content) > 30 && stripos($section_content, 'pending') === false) {
                    $valid_sections[] = array(
                        'title' => $section['title'] ?? '',
                        'content' => $section['content'] ?? '',
                        'type' => $section['type'] ?? 'content'
                    );
                }
            }

            if (!empty($valid_sections)) {
                return $valid_sections;
            }
        }
    }

    // Fallback: parse content by existing headings (h2, h3)
    return sffc_nrt_parse_content_by_headings($content);
}
endif;

/**
 * Parse content into sections based on existing headings in the content
 * Does NOT create fake section names - only uses actual headings from the content
 */
if (!function_exists('sffc_nrt_parse_content_by_headings')) :
function sffc_nrt_parse_content_by_headings($content) {
    $sections = array();

    // Match h2 or h3 headings
    $pattern = '/<h[23][^>]*>(.*?)<\/h[23]>/i';

    if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
        $last_pos = 0;

        foreach ($matches[0] as $i => $match) {
            $heading_full = $match[0];
            $heading_pos = $match[1];
            $heading_text = trim(strip_tags($matches[1][$i][0]));

            // Get content before first heading (intro paragraph)
            if ($heading_pos > $last_pos && empty($sections)) {
                $before_content = substr($content, $last_pos, $heading_pos - $last_pos);
                if (!empty(trim(wp_strip_all_tags($before_content)))) {
                    $sections[] = array(
                        'title' => '',  // No fake title - just intro content
                        'content' => $before_content,
                        'type' => 'intro'
                    );
                }
            }

            // Find next heading or end of content
            $next_pos = isset($matches[0][$i + 1]) ? $matches[0][$i + 1][1] : strlen($content);
            $section_content = substr($content, $heading_pos + strlen($heading_full), $next_pos - $heading_pos - strlen($heading_full));

            if (!empty(trim(wp_strip_all_tags($section_content)))) {
                $sections[] = array(
                    'title' => $heading_text,  // Use actual heading from content
                    'content' => $section_content,
                    'type' => 'content'
                );
            }

            $last_pos = $next_pos;
        }
    }

    // If no headings found, return content as single section without fake title
    if (empty($sections)) {
        $sections[] = array(
            'title' => '',  // No fake title
            'content' => $content,
            'type' => 'content'
        );
    }

    return $sections;
}
endif;

/**
 * Get icon for section title
 */
if (!function_exists('sffc_nrt_get_section_icon')) :
function sffc_nrt_get_section_icon($title) {
    $title_lower = strtolower($title);

    if (strpos($title_lower, 'background') !== false || strpos($title_lower, 'overview') !== false) {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>';
    }
    if (strpos($title_lower, 'key') !== false || strpos($title_lower, 'player') !== false) {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>';
    }
    if (strpos($title_lower, 'market') !== false || strpos($title_lower, 'context') !== false) {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>';
    }
    if (strpos($title_lower, 'look') !== false || strpos($title_lower, 'ahead') !== false || strpos($title_lower, 'future') !== false) {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
    }
    if (strpos($title_lower, 'analysis') !== false) {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>';
    }
    if (strpos($title_lower, 'news') !== false || strpos($title_lower, 'story') !== false) {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>';
    }

    // Default icon
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>';
}
endif;

/**
 * Get icon for chart type
 */
if (!function_exists('sffc_nrt_get_chart_icon')) :
function sffc_nrt_get_chart_icon($type) {
    switch ($type) {
        case 'donut':
        case 'pie':
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>';
        case 'line':
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>';
        case 'bar':
        default:
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>';
    }
}
endif;

/**
 * Note: AJAX handler for nrt_load_story is registered in SFFC_PE_News_Dashboard class
 * See: includes/class-pe-news-dashboard.php -> ajax_nrt_load_story()
 */

/**
 * Generate detailed chart narrative for analysis
 * Ported from institutional-article.php for research article format
 */
if (!function_exists('sffc_nrt_generate_chart_narrative')) :
function sffc_nrt_generate_chart_narrative($chart, $chart_index = 1) {
    $type = $chart['type'] ?? 'bar';
    $title = $chart['title'] ?? '';
    $data = $chart['data'] ?? $chart;

    // Get data items
    $items = array();
    if ($type === 'bar' && !empty($data['series'])) {
        $items = $data['series'];
    } elseif (($type === 'donut' || $type === 'pie') && !empty($data['slices'])) {
        $items = $data['slices'];
    } elseif ($type === 'line' && !empty($data['points'])) {
        $items = $data['points'];
    }

    if (empty($items) || count($items) < 2) {
        return array();
    }

    $narrative = array(
        'title' => 'Analysis: ' . $title,
        'points' => array(),
    );

    // Calculate statistics
    $values = array_map(function($item) {
        return floatval($item['value'] ?? 0);
    }, $items);

    $max_val = max($values);
    $min_val = min($values);
    $avg_val = array_sum($values) / count($values);
    $total = array_sum($values);

    // Find max and min items
    $max_item = null;
    $min_item = null;
    foreach ($items as $item) {
        $val = floatval($item['value'] ?? 0);
        if ($val == $max_val) $max_item = $item;
        if ($val == $min_val) $min_item = $item;
    }

    $max_label = $max_item['label'] ?? $max_item['name'] ?? '';
    $min_label = $min_item['label'] ?? $min_item['name'] ?? '';
    $suffix = $data['suffix'] ?? '';

    // Generate narrative points based on chart type
    if ($type === 'donut' || $type === 'pie') {
        $narrative['points'][] = sprintf(
            '%s dominates with %s%% market share, representing the largest segment.',
            $max_label,
            number_format($max_val, 1)
        );

        if ($max_val > 50) {
            $narrative['points'][] = sprintf(
                'This concentration indicates %s holds a majority position (>50%%).',
                $max_label
            );
        }

        // Find runner up
        $sorted_values = $values;
        rsort($sorted_values);
        if (isset($sorted_values[1])) {
            $runner_up_val = $sorted_values[1];
            foreach ($items as $item) {
                if (floatval($item['value'] ?? 0) == $runner_up_val && ($item['label'] ?? '') !== $max_label) {
                    $narrative['points'][] = sprintf(
                        'Second largest segment: %s at %s%%, trailing by %s points.',
                        $item['label'] ?? '',
                        number_format($runner_up_val, 1),
                        number_format($max_val - $runner_up_val, 1)
                    );
                    break;
                }
            }
        }

    } elseif ($type === 'line') {
        $first = reset($items);
        $last = end($items);
        $first_val = floatval($first['value'] ?? 0);
        $last_val = floatval($last['value'] ?? 0);

        if ($last_val > $first_val && $first_val > 0) {
            $change = (($last_val - $first_val) / $first_val) * 100;
            $narrative['points'][] = sprintf(
                'Upward trend: values increased from %s%s to %s%s (+%s%%).',
                number_format($first_val, 1),
                $suffix ? ' ' . $suffix : '',
                number_format($last_val, 1),
                $suffix ? ' ' . $suffix : '',
                number_format($change, 1)
            );
        } elseif ($last_val < $first_val && $first_val > 0) {
            $change = (($first_val - $last_val) / $first_val) * 100;
            $narrative['points'][] = sprintf(
                'Downward trend: values decreased from %s%s to %s%s (-%s%%).',
                number_format($first_val, 1),
                $suffix ? ' ' . $suffix : '',
                number_format($last_val, 1),
                $suffix ? ' ' . $suffix : '',
                number_format($change, 1)
            );
        } else {
            $narrative['points'][] = 'Values remained stable over the period analyzed.';
        }

        $narrative['points'][] = sprintf(
            'Peak value of %s%s reached at %s.',
            number_format($max_val, 1),
            $suffix ? ' ' . $suffix : '',
            $max_label
        );

    } else {
        // Bar chart
        $narrative['points'][] = sprintf(
            '%s leads with %s%s, highest across all %d categories.',
            $max_label,
            number_format($max_val, is_float($max_val) && $max_val < 100 ? 1 : 0),
            $suffix ? ' ' . $suffix : '',
            count($items)
        );

        if ($max_val > 0 && $min_val > 0) {
            $gap = (($max_val - $min_val) / $max_val) * 100;
            $narrative['points'][] = sprintf(
                '%s trails at %s%s, a %s%% gap from the leader.',
                $min_label,
                number_format($min_val, is_float($min_val) && $min_val < 100 ? 1 : 0),
                $suffix ? ' ' . $suffix : '',
                number_format($gap, 0)
            );
        }

        $narrative['points'][] = sprintf(
            'Average across categories: %s%s.',
            number_format($avg_val, is_float($avg_val) && $avg_val < 100 ? 1 : 0),
            $suffix ? ' ' . $suffix : ''
        );
    }

    return $narrative;
}
endif;

/**
 * Get author/analyst information for the article
 * Enhanced for Google News compatibility with bio, image, and social profiles
 */
if (!function_exists('sffc_nrt_get_author_data')) :
function sffc_nrt_get_author_data($post_id) {
    // Use Google News class if available for enhanced data
    if (function_exists('sffc_get_google_news_author')) {
        return sffc_get_google_news_author($post_id);
    }

    $post = get_post($post_id);

    // Default author data - Ropa Ushe (verified author for Google News)
    $default_author = array(
        'id' => 0,
        'name' => 'Ropa Ushe',
        'title' => 'Senior Finance Markets Editor',
        'bio' => 'Ropa Ushe is a Senior Finance Markets Editor at MENA Careers Intelligence, specializing in finance career intelligence, fund strategy, deal activity, recruiter signals, and market trends.',
        'avatar' => 'https://joinsenna.com/wp-content/uploads/2024/08/RopaUshe-1.jpg',
        'url' => 'https://joinsenna.com/author/ropa-ushe',
        'social_profiles' => array(
            'https://www.linkedin.com/in/ropa-ushe-269188117/',
            'https://twitter.com/RopaUshe',
        ),
    );

    if (!$post) {
        return $default_author;
    }

    $author_id = $post->post_author;
    $author = get_userdata($author_id);

    if (!$author) {
        return $default_author;
    }

    // Get author bio
    $bio = get_user_meta($author_id, 'description', true);
    if (empty($bio)) {
        $bio = $default_author['bio'];
    }

    // Get avatar URL (larger size for better display)
    $avatar = get_avatar_url($author_id, array('size' => 200));

    // Get social profiles
    $social_profiles = array();
    $linkedin = get_user_meta($author_id, 'linkedin', true);
    $twitter = get_user_meta($author_id, 'twitter', true);
    if (!empty($linkedin)) {
        $social_profiles[] = $linkedin;
    }
    if (!empty($twitter)) {
        $social_profiles[] = 'https://twitter.com/' . ltrim($twitter, '@');
    }

    return array(
        'id' => $author_id,
        'name' => $author->display_name ?: $default_author['name'],
        'title' => get_user_meta($author_id, 'job_title', true) ?: $default_author['title'],
        'bio' => $bio,
        'avatar' => $avatar,
        'url' => get_author_posts_url($author_id),
        'social_profiles' => $social_profiles,
    );
}
endif;

/**
 * Get related research articles
 */
if (!function_exists('sffc_nrt_get_related_research')) :
function sffc_nrt_get_related_research($post_id, $sector = '', $limit = 3) {
    $args = array(
        'post_type' => array('sffc_pe_news', 'sffc_pe_deal', 'post'),
        'posts_per_page' => $limit,
        'post__not_in' => array($post_id),
        'orderby' => 'date',
        'order' => 'DESC',
    );

    // Filter by sector if available
    if (!empty($sector)) {
        $args['meta_query'] = array(
            array(
                'key' => '_sector',
                'value' => $sector,
                'compare' => 'LIKE',
            ),
        );
    }

    $query = new WP_Query($args);
    $related = array();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $related[] = array(
                'id' => get_the_ID(),
                'title' => get_the_title(),
                'link' => get_permalink(),
                'date' => get_the_date('M j, Y'),
                'type' => get_post_type(),
            );
        }
        wp_reset_postdata();
    }

    return $related;
}
endif;

/**
 * Get content type label for methodology badge
 */
if (!function_exists('sffc_nrt_get_content_type_label')) :
function sffc_nrt_get_content_type_label($story) {
    $type = $story['type'] ?? '';
    $labels = array(
        'news' => 'News Analysis',
        'deal' => 'Deal Intelligence',
        'report' => 'Research Report',
        'analysis' => 'Market Analysis',
        'brief' => 'Intelligence Brief',
    );
    return $labels[$type] ?? 'Research';
}
endif;

/**
 * Note: Chart data comes from Claude via sffc_get_enhanced_article_data()
 * or SFFC_Dynamic_Insights_Generator. No hardcoded/sample data.
 * If Claude is unavailable or returns no charts, no charts are shown.
 */
