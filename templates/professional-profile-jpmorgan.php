<?php
if (!defined('ABSPATH')) {
    exit;
}

$current_user = wp_get_current_user();
$user_name = $current_user->display_name;
$user_initial = strtoupper(substr(trim($user_name), 0, 1) ?: 'P');
$profile_data = isset($profile_data) ? $profile_data : array();
$profile = $profile_data['profile'] ?? array();
$expertise = $profile_data['expertise'] ?? array();

// Get user profile picture
$profile_picture = get_user_meta(get_current_user_id(), 'senna_profile_picture', true);

// Get real data from senna-finance-career
global $wpdb;

// Get trending investment topics
$investment_intelligence = $wpdb->get_results(
    "SELECT p.post_title, p.post_excerpt, p.post_date, 
            pm1.meta_value as company,
            pm2.meta_value as sector,
            pm3.meta_value as excerpt_custom
     FROM {$wpdb->prefix}posts p 
     LEFT JOIN {$wpdb->prefix}postmeta pm1 ON p.ID = pm1.post_id AND pm1.meta_key = 'company'
     LEFT JOIN {$wpdb->prefix}postmeta pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'sector'
     LEFT JOIN {$wpdb->prefix}postmeta pm3 ON p.ID = pm3.post_id AND pm3.meta_key = 'excerpt'
     WHERE p.post_type IN ('sffc_pe_news', 'sffc_pe_signal') 
     AND p.post_status = 'publish'
     AND p.post_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     ORDER BY p.post_date DESC 
     LIMIT 6"
);

// Get market intelligence updates
$market_intelligence = $wpdb->get_results(
    "SELECT p.post_title, p.post_excerpt, p.post_date,
            pm1.meta_value as company,
            pm2.meta_value as sector
     FROM {$wpdb->prefix}posts p 
     LEFT JOIN {$wpdb->prefix}postmeta pm1 ON p.ID = pm1.post_id AND pm1.meta_key = 'company'
     LEFT JOIN {$wpdb->prefix}postmeta pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'sector'
     WHERE p.post_type = 'sffc_pe_signal' 
     AND p.post_status = 'publish'
     AND p.post_date >= DATE_SUB(NOW(), INTERVAL 14 DAY)
     ORDER BY p.coment_count DESC, p.post_date DESC
     LIMIT 4"
);

// Get actual platform professionals for networking
$professionals = $wpdb->get_results(
    "SELECT u.ID, u.display_name, 
            pm1.meta_value as current_position,
            pm2.meta_value as current_company,
            pm3.meta_value as profile_picture
     FROM {$wpdb->prefix}users u
     LEFT JOIN {$wpdb->prefix}usermeta pm1 ON u.ID = pm1.user_id AND pm1.meta_key = 'senna_current_position'
     LEFT JOIN {$wpdb->prefix}usermeta pm2 ON u.ID = pm2.user_id AND pm2.meta_key = 'senna_current_company' 
     LEFT JOIN {$wpdb->prefix}usermeta pm3 ON u.ID = pm3.user_id AND pm3.meta_key = 'senna_profile_picture'
     WHERE u.ID != %d 
     AND (pm1.meta_value IS NOT NULL OR pm2.meta_value IS NOT NULL)
     ORDER BY u.user_registered DESC
     LIMIT 8",
    get_current_user_id()
);

$professional_position = $profile['current_position'] ?: 'Investment Professional';
$professional_company = $profile['current_company'] ?: 'Leading Investment Bank';
?>

<div class="jp-professional-profile">
    <!-- Sophisticated Header -->
    <header class="jp-executive-header">
        <div class="jp-container">
            <div class="jp-header-brand">
                <div class="jp-brand-pill">MENA CAREERS</div>
                <span class="jp-brand-label">Professional</span>
            </div>
            <div class="jp-header-actions">
                <a href="#overview" class="jp-nav-link jp-nav-link-accent">Overview</a>
                <a href="#intelligence" class="jp-nav-link">Intelligence</a>
                <a href="#network" class="jp-nav-link">Network</a>
                <a href="#insights" class="jp-nav-link">Insights</a>
                <a href="?settings=1" class="jp-nav-link">Settings</a>
            </div>
        </div>
    </header>

    <!-- Executive Hero Section -->
    <section class="jp-executive-hero">
        <div class="jp-container">
            <div class="jp-hero-grid">
                <div class="jp-identity-panel">
                    <div class="jp-avatar-shell" id="jp-avatar-display">
                        <?php if ($profile_picture): ?>
                            <img src="<?php echo esc_url($profile_picture); ?>" alt="<?php echo esc_attr($user_name); ?>" />
                        <?php else: ?>
                            <div class="jp-avatar-initials"><?php echo esc_html($user_initial); ?></div>
                        <?php endif; ?>
                        <input type="file" class="jp-avatar-upload" id="jp-avatar-upload" accept="image/*" />
                    </div>
                    
                    <div class="jp-identity-body">
                        <div class="jp-identity-meta">Executive Profile</div>
                        <h1 class="jp-hero-name"><?php echo esc_html($user_name); ?></h1>
                        <div class="jp-hero-role"><?php echo esc_html($professional_position); ?></div>
                        <div class="jp-identity-summary"><?php echo esc_html($professional_company); ?></div>
                        
                        <div class="jp-expertise-grid">
                            <?php if (!empty($expertise)): ?>
                                <?php foreach (array_slice($expertise, 0, 4) as $expert_area): ?>
                                    <span class="jp-expertise-badge" data-expertise="<?php echo esc_attr($expert_area['expertise_title']); ?>">
                                        <?php echo esc_html($expert_area['expertise_title']); ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="jp-expertise-badge">Investment Banking</span>
                                <span class="jp-expertise-badge">Financial Modeling</span>
                                <span class="jp-expertise-badge">M&A Advisory</span>
                                <span class="jp-expertise-badge">Capital Markets</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="jp-hero-actions">
                            <button class="jp-btn jp-btn-primary" data-open-intro>Express Interest</button>
                            <button class="jp-btn jp-btn-secondary">Download CV</button>
                        </div>
                    </div>
                </div>
                
                <div class="jp-intelligence-panel">
                    <div class="jp-scorecard">
                        <div class="jp-score-label">Profile Completion</div>
                        <span class="jp-score-value"><?php echo isset($profile_data['completion_score']) ? $profile_data['completion_score'] : '75'; ?>%</span>
                        <div class="jp-score-note">Professional Level</div>
                    </div>
                    
                    <div class="jp-scorecard">
                        <div class="jp-score-label">Network Status</div>
                        <div class="jp-scorecard-stack">
                            <div><span>Connections</span><strong><?php echo isset($profile_data['networking_stats']['total_connections']) ? $profile_data['networking_stats']['total_connections'] : '0'; ?></strong></div>
                            <div><span>Introductions</span><strong><?php echo isset($profile_data['networking_stats']['pending_introductions']) ? $profile_data['networking_stats']['pending_introductions'] : '0'; ?></strong></div>
                            <div><span>This Month</span><strong><?php echo isset($profile_data['networking_stats']['monthly_networking_score']) ? round($profile_data['networking_stats']['monthly_networking_score']/10) : '0'; ?></strong></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content Panels -->
    <div class="jp-dossier">
        <div class="jp-container">
            <div class="jp-dossier-header">
                <div>
                    <div class="jp-overline">Professional Intelligence</div>
                    <h2>Investment Dashboard</h2>
                </div>
                <div class="jp-dossier-metrics">
                    <span>Profile Views<strong><?php echo isset($profile_data['analytics']['profile_views']) ? $profile_data['analytics']['profile_views'] : '0'; ?></strong></span>
                    <span>Interactions<strong><?php echo isset($profile_data['analytics']['senna_interactions']) ? $profile_data['analytics']['senna_interactions'] : '0'; ?></strong></span>
                </div>
            </div>
        </div>
    </div>

    <div class="jp-panels">
        <div class="jp-container">
            <div class="jp-panel-controls">
                <button class="jp-panel-trigger active" data-panel="intelligence">Intelligence</button>
                <button class="jp-panel-trigger" data-panel="network">Network</button>
                <button class="jp-panel-trigger" data-panel="activity">Activity</button>
                <button class="jp-panel-trigger jp-panel-trigger--upgrade" data-panel="subscription">Upgrade</button>
            </div>
            
            <!-- Investment Intelligence Panel -->
            <div class="jp-panel active" data-panel="intelligence">
                <div class="jp-intel-grid">
                    <?php if (!empty($investment_intelligence)): ?>
                        <?php foreach (array_slice($investment_intelligence, 0, 6) as $index => $intel): ?>
                            <article class="jp-intel-card">
                                <div class="jp-card-category">
                                    <?php 
                                    $categories = array('M&A ADVISORY', 'CAPITAL MARKETS', 'STRATEGIC FINANCE', 'INVESTMENT BANKING', 'PRIVATE EQUITY', 'DEBT MARKETS');
                                    echo esc_html($intel->sector ?: $categories[$index % count($categories)]);
                                    ?>
                                </div>
                                <h3 class="jp-card-title">
                                    <?php echo esc_html($intel->post_title); ?>
                                </h3>
                                <p class="jp-card-excerpt">
                                    <?php 
                                    $excerpt = $intel->excerpt_custom ?: $intel->post_excerpt ?: wp_trim_words(strip_tags($intel->post_content), 30);
                                    echo esc_html($excerpt);
                                    ?>
                                </p>
                                <a href="<?php echo get_permalink($intel->ID); ?>" class="jp-read-more">
                                    Read Full Analysis →
                                </a>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <article class="jp-intel-card">
                            <div class="jp-card-category">M&A ADVISORY</div>
                            <h3 class="jp-card-title">Global M&A Activity Surges in Q4 2024</h3>
                            <p class="jp-card-excerpt">
                                Cross-border merger and acquisition activity reaches new highs as companies pursue strategic consolidation amid favorable financing conditions and regulatory clarity.
                            </p>
                            <a href="#" class="jp-read-more">Read Full Analysis →</a>
                        </article>
                        
                        <article class="jp-intel-card">
                            <div class="jp-card-category">CAPITAL MARKETS</div>
                            <h3 class="jp-card-title">Fixed Income Opportunities in Rising Rate Environment</h3>
                            <p class="jp-card-excerpt">
                                Strategic positioning in credit markets presents compelling risk-adjusted returns as central banks navigate monetary policy normalization across major economies.
                            </p>
                            <a href="#" class="jp-read-more">Read Full Analysis →</a>
                        </article>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Professional Network Panel -->
            <div class="jp-panel" data-panel="network">
                <div class="jp-network-container">
                    <!-- Networking Sections -->
                    <div class="jp-network-sections">
                        <!-- Pending Requests -->
                        <div class="jp-network-section">
                            <h4>Introduction Requests</h4>
                            <div id="jp-pending-requests" class="jp-request-list">
                                <!-- Loaded via AJAX -->
                            </div>
                        </div>
                        
                        <!-- Your Connections -->
                        <div class="jp-network-section">
                            <h4>Your Professional Network</h4>
                            <div id="jp-connections" class="jp-network-grid">
                                <!-- Loaded via AJAX -->
                            </div>
                        </div>
                        
                        <!-- Sent Requests -->
                        <div class="jp-network-section">
                            <h4>Sent Requests</h4>
                            <div id="jp-sent-requests" class="jp-request-list">
                                <!-- Loaded via AJAX -->
                            </div>
                        </div>
                        
                        <!-- Discover Professionals -->
                        <div class="jp-network-section">
                            <h4>Discover Professionals</h4>
                            <div class="jp-network-grid">
                                <?php if (!empty($professionals)): ?>
                                    <?php foreach ($professionals as $professional): ?>
                                        <div class="jp-professional-card" data-user-id="<?php echo esc_attr($professional->ID); ?>">
                                            <div class="jp-professional-avatar">
                                                <?php if ($professional->profile_picture): ?>
                                                    <img src="<?php echo esc_url($professional->profile_picture); ?>" alt="<?php echo esc_attr($professional->display_name); ?>" />
                                                <?php else: ?>
                                                    <?php echo esc_html(strtoupper(substr($professional->display_name, 0, 1))); ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="jp-professional-info">
                                                <h5 class="jp-professional-name"><?php echo esc_html($professional->display_name); ?></h5>
                                                <div class="jp-professional-role">
                                                    <?php 
                                                    $role = $professional->current_position ?: 'Investment Professional';
                                                    $company = $professional->current_company;
                                                    echo esc_html($role);
                                                    if ($company) {
                                                        echo ', ' . esc_html($company);
                                                    }
                                                    ?>
                                                </div>
                                                <button class="jp-btn jp-btn-primary jp-btn-small jp-request-intro" 
                                                        data-user-id="<?php echo esc_attr($professional->ID); ?>"
                                                        data-user-name="<?php echo esc_attr($professional->display_name); ?>"
                                                        data-user-role="<?php echo esc_attr($role); ?>">
                                                    Express Interest
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="jp-professional-card">
                                        <div class="jp-professional-avatar">MS</div>
                                        <div class="jp-professional-info">
                                            <h5 class="jp-professional-name">Michael Sterling</h5>
                                            <div class="jp-professional-role">Managing Director, Goldman Sachs</div>
                                            <button class="jp-btn jp-btn-primary jp-btn-small jp-request-intro" 
                                                    data-user-id="demo" data-user-name="Michael Sterling" data-user-role="Managing Director">
                                                Express Interest
                                            </button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Activity Panel -->
            <div class="jp-panel" data-panel="activity">
                <div class="jp-activity-grid">
                    <?php
                    // Get real activity data
                    $recent_activity = $wpdb->get_results($wpdb->prepare(
                        "SELECT activity_type, activity_details, created_at
                         FROM {$wpdb->prefix}sffc_user_activity
                         WHERE user_id = %d
                         ORDER BY created_at DESC
                         LIMIT 5",
                        get_current_user_id()
                    ));
                    ?>
                    <div class="jp-activity-card">
                        <h4>Recent Activity</h4>
                        <ul>
                            <?php if (!empty($recent_activity)): ?>
                                <?php foreach ($recent_activity as $activity): ?>
                                    <li>
                                        <strong><?php echo esc_html(ucwords(str_replace('_', ' ', $activity->activity_type))); ?></strong>
                                        <span><?php echo esc_html(human_time_diff(strtotime($activity->created_at), current_time('timestamp')) . ' ago'); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li><strong>Profile Created</strong><span>Just now</span></li>
                                <li class="jp-activity-empty">Complete your profile to see more activity</li>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <div class="jp-activity-card">
                        <h4>Market Engagement</h4>
                        <ul>
                            <li><strong>Intelligence Views</strong><span><?php echo isset($profile_data['analytics']['profile_views']) ? $profile_data['analytics']['profile_views'] : '0'; ?> this week</span></li>
                            <li><strong>Reports Downloaded</strong><span><?php echo isset($profile_data['analytics']['reports_downloaded']) ? $profile_data['analytics']['reports_downloaded'] : '0'; ?> this month</span></li>
                            <li><strong>Expertise Searches</strong><span><?php echo isset($profile_data['analytics']['search_appearances']) ? $profile_data['analytics']['search_appearances'] : '0'; ?> times found</span></li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Subscription/Upgrade Panel -->
            <div class="jp-panel" data-panel="subscription">
                <div class="jp-subscription-section">
                    <div class="jp-subscription-header">
                        <h3><?php esc_html_e('Upgrade Your Profile', 'senna-finance'); ?></h3>
                        <p><?php esc_html_e('Unlock premium features to accelerate your career in private equity', 'senna-finance'); ?></p>
                    </div>

                    <div class="jp-plans-grid">
                        <?php if (!empty($subscription_plans)) : ?>
                            <?php foreach ($subscription_plans as $plan) :
                                $is_free = ($plan['slug'] === 'free');
                                $is_popular = ($plan['slug'] === 'career');
                                $features = $plan['features'] ?? array();
                            ?>
                                <div class="jp-plan-card<?php echo $is_free ? ' jp-plan-current' : ''; ?><?php echo $is_popular ? ' jp-plan-premium' : ''; ?>">
                                    <?php if ($is_free) : ?>
                                        <div class="jp-plan-badge"><?php esc_html_e('Current Plan', 'senna-finance'); ?></div>
                                    <?php elseif ($is_popular) : ?>
                                        <div class="jp-plan-badge jp-plan-badge--premium"><?php esc_html_e('Most Popular', 'senna-finance'); ?></div>
                                    <?php endif; ?>
                                    <h4 class="jp-plan-name"><?php echo esc_html($plan['name']); ?></h4>
                                    <div class="jp-plan-price">
                                        <span class="jp-price"><?php echo esc_html($plan['price']); ?></span>
                                        <?php if (!empty($plan['billing_cycle'])) : ?>
                                            <span class="jp-period"><?php echo esc_html($plan['billing_cycle']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($features)) : ?>
                                        <ul class="jp-plan-features">
                                            <?php foreach ($features as $feature) : ?>
                                                <li><span class="jp-check">✓</span> <?php echo esc_html($feature); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                    <?php if (!$is_free) : ?>
                                        <button class="jp-btn jp-btn-primary jp-btn-full" data-action="open-plan-modal"><?php esc_html_e('Upgrade Now', 'senna-finance'); ?></button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Market Feed Sidebar -->
            <div class="jp-market-feed">
                <h4>Market Intelligence</h4>
                <ul>
                    <?php if (!empty($market_intelligence)): ?>
                        <?php foreach ($market_intelligence as $insight): ?>
                            <li>
                                <div>
                                    <h4 class="jp-market-title"><?php echo esc_html($insight->post_title); ?></h4>
                                    <span><?php echo esc_html($insight->sector ?: 'Market Intel'); ?></span>
                                </div>
                                <a href="<?php echo get_permalink($insight->ID); ?>">→</a>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li>
                            <div>
                                <h4 class="jp-market-title">Latest PE Market Updates</h4>
                                <span>Strategic Insight</span>
                            </div>
                            <a href="/pe-news/">→</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
    </div>

    <!-- Introduction Request Modal -->
    <div class="jp-modal jp-intro-modal-template" id="jp-intro-modal-template" style="display: none;">
        <div class="jp-modal-content">
            <h3 class="jp-modal-title">Express Interest</h3>
            <p class="jp-modal-text"></p>
            <div class="jp-intro-form">
                <textarea class="jp-intro-message" placeholder="Introduce yourself and explain why you'd like to connect..." maxlength="500"></textarea>
                <div class="jp-char-count">0/500 characters</div>
            </div>
            <div class="jp-modal-actions">
                <button class="jp-btn jp-btn-primary" id="jp-send-intro-btn">Send Request</button>
                <button class="jp-btn jp-btn-secondary" id="jp-cancel-intro-btn">Cancel</button>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($subscription_plans)) : ?>
    <div class="sffc-plan-modal" data-plan-modal aria-hidden="true">
        <div class="sffc-plan-modal__overlay" data-plan-close></div>
        <div class="sffc-plan-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="sffc-plan-modal-title">
            <div class="sffc-plan-modal__header">
                <div>
                    <h3 id="sffc-plan-modal-title"><?php esc_html_e('Choose your membership', 'senna-finance'); ?></h3>
                    <p><?php esc_html_e('Unlock premium features to accelerate your career.', 'senna-finance'); ?></p>
                </div>
                <button type="button" class="sffc-plan-close" data-plan-close aria-label="<?php esc_attr_e('Close plans', 'senna-finance'); ?>">&times;</button>
            </div>
            <div class="sffc-plan-modal__body">
                <div class="sffc-plan-grid">
                    <?php foreach ($subscription_plans as $plan) :
                        $features = $plan['features'] ?? array();
                        $is_free = ($plan['slug'] === 'free');
                        ?>
                        <article class="sffc-plan-card<?php echo $is_free ? ' sffc-plan-card--current' : ''; ?>" data-plan-card data-plan-slug="<?php echo esc_attr($plan['slug']); ?>">
                            <?php if ($is_free) : ?>
                                <div class="sffc-plan-badge sffc-plan-badge--current"><?php esc_html_e('Current Plan', 'senna-finance'); ?></div>
                            <?php elseif ($plan['slug'] === 'career') : ?>
                                <div class="sffc-plan-badge sffc-plan-badge--popular"><?php esc_html_e('Most Popular', 'senna-finance'); ?></div>
                            <?php endif; ?>
                            <div class="sffc-plan-card__head">
                                <h4><?php echo esc_html($plan['name']); ?></h4>
                                <p class="sffc-plan-card__price">
                                    <?php echo esc_html($plan['price']); ?>
                                    <?php if (!empty($plan['billing_cycle'])) : ?>
                                        <span class="sffc-plan-card__cycle"><?php echo esc_html($plan['billing_cycle']); ?></span>
                                    <?php endif; ?>
                                </p>
                                <?php if (!empty($plan['tagline'])) : ?><p class="sffc-plan-card__tagline"><?php echo esc_html($plan['tagline']); ?></p><?php endif; ?>
                            </div>
                            <?php if (!empty($features)) : ?>
                                <ul class="sffc-plan-card__list">
                                    <?php foreach ($features as $feature) : ?>
                                        <li><?php echo esc_html($feature); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <?php if (!$is_free) : ?>
                                <button type="button" class="sffc-plan-select" data-plan-select data-plan-url="<?php echo esc_url($plan['mp_url']); ?>" data-plan-slug="<?php echo esc_attr($plan['slug']); ?>">
                                    <?php esc_html_e('Upgrade Now', 'senna-finance'); ?>
                                </button>
                            <?php else : ?>
                                <div class="sffc-plan-current-label"><?php esc_html_e('Your current plan', 'senna-finance'); ?></div>
                            <?php endif; ?>
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
                            <?php echo do_shortcode($plan['shortcode']); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
