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

// Get real data from senna-finance-career
global $wpdb;

// Get trending topics from PE news/signals
$trending_topics = $wpdb->get_results(
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

// Get market takeaways
$market_takeaways = $wpdb->get_results(
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
     LIMIT 3"
);

// Get recent introductions (mock data with professional names)
$recent_intros = array(
    array('name' => 'Thomas Sterling', 'role' => 'Managing Director, Blackstone', 'initial' => 'T'),
    array('name' => 'Lara Martinez', 'role' => 'Partner, KKR', 'initial' => 'L'),
    array('name' => 'Lucrezia Santos', 'role' => 'VP, Apollo Global', 'initial' => 'L'),
    array('name' => 'Daniel Thompson', 'role' => 'Director, Carlyle Group', 'initial' => 'D')
);

$professional_position = $profile['current_position'] ?: 'Investment Professional';
$professional_company = $profile['current_company'] ?: 'Leading PE Firm';
$professional_summary = $profile['professional_summary'] ?: 'Seasoned investment professional with expertise in private equity, mergers & acquisitions, and strategic financial analysis across multiple sectors.';
?>

<div class="senna-magazine-profile">
    <!-- Header Navigation -->
    <header class="senna-profile-header">
        <div class="senna-header-content">
            <div class="senna-logo">MENA Careers Professional</div>
            <nav class="senna-nav">
                <a href="#overview" class="senna-nav-item">Overview</a>
                <a href="#insights" class="senna-nav-item">Market Insights</a>
                <a href="#network" class="senna-nav-item">Network</a>
                <a href="#settings" class="senna-nav-item">Settings</a>
            </nav>
        </div>
    </header>

    <!-- Hero Banner -->
    <section class="senna-hero-banner">
        <div class="senna-profile-container">
            <div class="senna-hero-content">
                <div class="senna-hero-text">
                    <h1 class="senna-hero-name"><?php echo esc_html($user_name); ?></h1>
                    <div class="senna-hero-position"><?php echo esc_html($professional_position); ?></div>
                    <div class="senna-hero-company"><?php echo esc_html($professional_company); ?></div>
                    
                    <div class="senna-expertise-tags">
                        <?php if (!empty($expertise)): ?>
                            <?php foreach (array_slice($expertise, 0, 3) as $expert_area): ?>
                                <span class="senna-expertise-tag">
                                    <?php echo esc_html($expert_area['expertise_title']); ?>
                                </span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="senna-expertise-tag">Financial Modeling</span>
                            <span class="senna-expertise-tag">Investment Analysis</span>
                            <span class="senna-expertise-tag">Due Diligence</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="senna-hero-avatar">
                    <?php echo esc_html($user_initial); ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Section Navigation -->
    <nav class="senna-section-nav">
        <div class="senna-profile-container">
            <div class="senna-nav-carousel">
                <button class="senna-nav-button active" data-section="trending">Trending Topics</button>
                <button class="senna-nav-button" data-section="economy">Economy & Markets</button>
                <button class="senna-nav-button" data-section="intros">Recent Introductions</button>
                <button class="senna-nav-button" data-section="takeaways">Market Takeaways</button>
            </div>
        </div>
    </nav>

    <!-- Content Sections -->
    <div class="senna-content-wrapper">
        <div class="senna-profile-container">
            
            <!-- Trending Topics Section -->
            <section id="trending" class="senna-magazine-section visible">
                <div class="senna-section-header">
                    <h2 class="senna-section-title">Trending Topics</h2>
                    <p class="senna-section-subtitle">See what clients are reading</p>
                </div>
                
                <div class="senna-trending-grid stagger-children">
                    <?php if (!empty($trending_topics)): ?>
                        <?php foreach (array_slice($trending_topics, 0, 6) as $index => $topic): ?>
                            <article class="senna-trending-card">
                                <div class="senna-card-category">
                                    <?php 
                                    $categories = array('Investment Strategy', 'Market Analysis', 'Deal Flow', 'Economic Outlook', 'Sector Focus', 'Risk Assessment');
                                    echo esc_html($topic->sector ?: $categories[$index % count($categories)]);
                                    ?>
                                </div>
                                <h3 class="senna-card-title">
                                    <?php echo esc_html($topic->post_title); ?>
                                </h3>
                                <p class="senna-card-excerpt">
                                    <?php 
                                    $excerpt = $topic->excerpt_custom ?: $topic->post_excerpt ?: wp_trim_words(strip_tags($topic->post_content), 25);
                                    echo esc_html($excerpt);
                                    ?>
                                </p>
                                <a href="<?php echo get_permalink($topic->ID); ?>" class="senna-read-more underline-animation">
                                    Read Analysis →
                                </a>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <article class="senna-trending-card">
                            <div class="senna-card-category">Investment Strategy</div>
                            <h3 class="senna-card-title">2026 Outlook: Promise and Pressure</h3>
                            <p class="senna-card-excerpt">
                                Navigate evolving market dynamics as institutional investors balance growth opportunities with emerging risk factors in a complex global landscape.
                            </p>
                            <a href="#" class="senna-read-more underline-animation">Read Analysis →</a>
                        </article>
                        <article class="senna-trending-card">
                            <div class="senna-card-category">Market Analysis</div>
                            <h3 class="senna-card-title">Market Thoughts: More to Cheer Than Jeer</h3>
                            <p class="senna-card-excerpt">
                                Despite headline volatility, underlying fundamentals suggest robust opportunities for discerning investors in select market segments.
                            </p>
                            <a href="#" class="senna-read-more underline-animation">Read Analysis →</a>
                        </article>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Economy & Markets Section -->
            <section id="economy" class="senna-magazine-section">
                <div class="senna-section-header">
                    <h2 class="senna-section-title">Economy & Markets</h2>
                    <p class="senna-section-subtitle">Latest analysis on market-moving developments</p>
                </div>
                
                <div class="senna-trending-grid stagger-children">
                    <article class="senna-trending-card">
                        <div class="senna-card-category">Technology Disruption</div>
                        <h3 class="senna-card-title">
                            The Winter of Our Discontent: Generative AI Disrupts Entertainment Industry Content Moats
                        </h3>
                        <p class="senna-card-excerpt">
                            Traditional media companies face unprecedented challenges as AI-generated content threatens established competitive advantages and revenue models.
                        </p>
                        <a href="#" class="senna-read-more underline-animation">Read Analysis →</a>
                    </article>
                    
                    <?php if (!empty($trending_topics)): ?>
                        <?php foreach (array_slice($trending_topics, 1, 3) as $topic): ?>
                            <article class="senna-trending-card">
                                <div class="senna-card-category">Economic Analysis</div>
                                <h3 class="senna-card-title">
                                    <?php echo esc_html($topic->post_title); ?>
                                </h3>
                                <p class="senna-card-excerpt">
                                    <?php 
                                    $excerpt = $topic->excerpt_custom ?: $topic->post_excerpt ?: wp_trim_words(strip_tags($topic->post_content), 25);
                                    echo esc_html($excerpt);
                                    ?>
                                </p>
                                <a href="<?php echo get_permalink($topic->ID); ?>" class="senna-read-more underline-animation">
                                    Read Analysis →
                                </a>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Recent Introductions Section -->
            <section id="intros" class="senna-magazine-section">
                <div class="senna-intros-section">
                    <div class="senna-intros-header">
                        <h2 class="senna-intros-title underline-animation">Recent Introductions</h2>
                    </div>
                    
                    <div class="senna-intros-grid">
                        <?php foreach ($recent_intros as $intro): ?>
                            <div class="senna-intro-card">
                                <div class="senna-intro-avatar">
                                    <?php echo esc_html($intro['initial']); ?>
                                </div>
                                <div class="senna-intro-name"><?php echo esc_html($intro['name']); ?></div>
                                <div class="senna-intro-role"><?php echo esc_html($intro['role']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <!-- Market Takeaways Section -->
            <section id="takeaways" class="senna-magazine-section">
                <div class="senna-takeaways-section">
                    <div class="senna-takeaways-header">
                        <h2 class="senna-takeaways-title">Top Market Takeaways</h2>
                        <p class="senna-takeaways-subtitle">Weekly update on market-moving events</p>
                    </div>
                    
                    <div class="senna-takeaways-grid">
                        <?php if (!empty($market_takeaways)): ?>
                            <?php foreach ($market_takeaways as $takeaway): ?>
                                <article class="senna-takeaway-card">
                                    <div class="senna-takeaway-meta">
                                        <?php echo esc_html($takeaway->sector ?: 'Investment Strategy'); ?>
                                    </div>
                                    <h3 class="senna-takeaway-title">
                                        <?php echo esc_html($takeaway->post_title); ?>
                                    </h3>
                                    <p class="senna-takeaway-excerpt">
                                        <?php 
                                        $excerpt = $takeaway->post_excerpt ?: wp_trim_words(strip_tags($takeaway->post_content), 20);
                                        echo esc_html($excerpt);
                                        ?>
                                    </p>
                                    <a href="<?php echo get_permalink($takeaway->ID); ?>" class="senna-takeaway-link">
                                        Read Full Analysis →
                                    </a>
                                </article>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <article class="senna-takeaway-card">
                                <div class="senna-takeaway-meta">Investment Strategy</div>
                                <h3 class="senna-takeaway-title">Are Markets Defying the Economy?</h3>
                                <p class="senna-takeaway-excerpt">
                                    Stocks keep climbing, but Main Street isn't feeling the same lift. We break down three reasons behind the divide.
                                </p>
                                <a href="#" class="senna-takeaway-link">Read Full Analysis →</a>
                            </article>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>