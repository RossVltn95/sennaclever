<?php
/**
 * Template for single relocation page
 * Institutional Design - KKR/JP Morgan Research Style
 *
 * @package SFFC_Careers
 * @since 11.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Enqueue the relocation styles
wp_enqueue_style('sffc-relocation-shortcodes', SFFC_PLUGIN_URL . 'assets/css/relocation-shortcodes.css', array(), SFFC_VERSION);
wp_enqueue_style('sffc-relocation-charts');

get_header();

// Get URL parameters
$from_slug = get_query_var('sffc_from_location');
$to_slug = get_query_var('sffc_to_location');

// Get relocation pages instance
$relocation_pages = SFFC_Relocation_Pages::get_instance();
$charts = class_exists('SFFC_Relocation_Charts') ? SFFC_Relocation_Charts::get_instance() : null;

// Get location info
$from_info = $relocation_pages->get_location_info($from_slug);
$to_info = $relocation_pages->get_location_info($to_slug);

// Handle invalid locations - show 404
if (!$from_info || !$to_info) {
    global $wp_query;
    $wp_query->set_404();
    status_header(404);
    nocache_headers();
    include(get_query_template('404'));
    exit;
}

// Get comparison data
$comparison_data = $relocation_pages->get_comparison_data($from_slug, $to_slug);

// Get jobs for destination
$destination_jobs = $relocation_pages->get_location_jobs($to_slug, 6);

// Get existing post if any
$existing_post = $relocation_pages->get_relocation_page($from_slug, $to_slug);

// Page title and meta
$page_title = $relocation_pages->generate_page_title($from_info, $to_info);
$meta_description = $relocation_pages->generate_meta_description($from_info, $to_info);

// Set document title
add_filter('pre_get_document_title', function() use ($page_title) {
    return $page_title;
});

// Add meta description
add_action('wp_head', function() use ($meta_description, $page_title, $from_info, $to_info) {
    echo '<meta name="description" content="' . esc_attr($meta_description) . '">' . "\n";
    echo '<meta property="og:title" content="' . esc_attr($page_title) . '">' . "\n";
    echo '<meta property="og:description" content="' . esc_attr($meta_description) . '">' . "\n";
    echo '<meta property="og:type" content="article">' . "\n";

    // Schema.org structured data
    $schema = array(
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $page_title,
        'description' => $meta_description,
        'author' => array(
            '@type' => 'Organization',
            'name' => get_bloginfo('name'),
        ),
        'publisher' => array(
            '@type' => 'Organization',
            'name' => get_bloginfo('name'),
        ),
        'datePublished' => date('c'),
        'dateModified' => date('c'),
    );
    echo '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>' . "\n";
});
?>

<div class="sffc-relocation-page">
    <!-- Hero Section -->
    <header class="sffc-hero">
        <div class="sffc-hero-inner">
            <nav class="sffc-breadcrumb">
                <a href="<?php echo home_url('/relocating/'); ?>">Relocation Guides</a>
                <span class="sffc-breadcrumb-sep">/</span>
                <span><?php echo esc_html($from_info['display_name']); ?> to <?php echo esc_html($to_info['display_name']); ?></span>
            </nav>

            <h1 class="sffc-hero-title">
                Moving to <span class="sffc-highlight"><?php echo esc_html($to_info['display_name']); ?></span>
                from <?php echo esc_html($from_info['display_name']); ?>
            </h1>

            <p class="sffc-hero-subtitle">
                Your comprehensive guide to relocating for finance and professional services careers
            </p>

            <div class="sffc-hero-stats">
                <?php if ($comparison_data && !empty($comparison_data['mobility']['mobility_score'])): ?>
                    <div class="sffc-hero-stat">
                        <span class="sffc-stat-value"><?php echo esc_html($comparison_data['mobility']['mobility_score']); ?></span>
                        <span class="sffc-stat-label">Mobility Score</span>
                    </div>
                <?php endif; ?>

                <div class="sffc-hero-stat">
                    <span class="sffc-stat-value"><?php echo count($destination_jobs); ?>+</span>
                    <span class="sffc-stat-label">Jobs Available</span>
                </div>
            </div>
        </div>
    </header>

    <div class="sffc-relocation-content">
        <div class="sffc-relocation-main">
            <!-- Quick Navigation -->
            <nav class="sffc-quick-nav">
                <a href="#sffc-overview">Overview</a>
                <a href="#sffc-comparison">Comparison</a>
                <a href="#sffc-cost-of-living">Cost of Living</a>
                <a href="#sffc-taxes">Taxes</a>
                <a href="#sffc-visa">Visa</a>
                <a href="#sffc-jobs">Jobs</a>
            </nav>

            <!-- Overview Section -->
            <section id="sffc-overview" class="sffc-section">
                <?php if ($existing_post && !empty($existing_post->post_content)): ?>
                    <div class="sffc-article-content">
                        <?php echo wp_kses_post($existing_post->post_content); ?>
                    </div>
                <?php else: ?>
                    <h2>Overview</h2>
                    <p>
                        Relocating from <?php echo esc_html($from_info['display_name']); ?> to
                        <?php echo esc_html($to_info['display_name']); ?> is a significant career decision
                        that many finance professionals consider. This guide provides essential information
                        to help you plan your move, from understanding cost of living differences to
                        navigating visa requirements.
                    </p>
                    <p>
                        <?php echo esc_html($to_info['display_name']); ?> offers unique opportunities
                        in investment banking, private equity, and professional services. Use this guide
                        to understand what to expect and how to prepare for your transition.
                    </p>
                <?php endif; ?>
            </section>

            <!-- Comparison Dashboard -->
            <section id="sffc-comparison" class="sffc-section">
                <h2>At a Glance Comparison</h2>
                <?php
                if ($comparison_data && $charts) {
                    echo $charts->render_comparison_dashboard($comparison_data);
                }
                ?>
            </section>

            <!-- Cost of Living -->
            <section id="sffc-cost-of-living" class="sffc-section">
                <h2>Cost of Living</h2>
                <?php if ($comparison_data && !empty($comparison_data['mobility']['cost_of_living'])): ?>
                    <?php
                    $col = $comparison_data['mobility']['cost_of_living'];
                    $from_index = $col['from']['cost_index'] ?? 100;
                    $to_index = $col['to']['cost_index'] ?? 100;
                    $diff_pct = $from_index > 0 ? round((($to_index - $from_index) / $from_index) * 100) : 0;
                    ?>

                    <div class="sffc-col-summary">
                        <p>
                            Cost of living in <?php echo esc_html($to_info['display_name']); ?> is
                            <strong><?php echo abs($diff_pct); ?>% <?php echo $diff_pct > 0 ? 'higher' : 'lower'; ?></strong>
                            than in <?php echo esc_html($from_info['display_name']); ?>.
                        </p>
                    </div>

                    <?php
                    // Render detailed cost breakdown
                    $cost_metrics = array(
                        array(
                            'label' => 'Overall Index',
                            'value' => $to_index,
                            'change' => ($diff_pct > 0 ? '+' : '') . $diff_pct . '%',
                            'change_type' => $diff_pct > 5 ? 'negative' : ($diff_pct < -5 ? 'positive' : 'neutral'),
                        ),
                    );

                    if (!empty($col['to']['rent_index'])) {
                        $cost_metrics[] = array('label' => 'Rent Index', 'value' => $col['to']['rent_index']);
                    }
                    if (!empty($col['to']['restaurant_index'])) {
                        $cost_metrics[] = array('label' => 'Restaurants', 'value' => $col['to']['restaurant_index']);
                    }
                    if (!empty($col['to']['groceries_index'])) {
                        $cost_metrics[] = array('label' => 'Groceries', 'value' => $col['to']['groceries_index']);
                    }

                    if ($charts) {
                        echo $charts->render_metrics_grid($cost_metrics);
                    }
                    ?>
                <?php else: ?>
                    <p>Cost of living data is being compiled for this route. Check back soon for detailed comparisons.</p>
                <?php endif; ?>
            </section>

            <!-- Tax Implications -->
            <section id="sffc-taxes" class="sffc-section">
                <h2>Tax Comparison</h2>
                <?php if ($comparison_data && !empty($comparison_data['mobility']['tax_rates'])): ?>
                    <?php
                    $tax = $comparison_data['mobility']['tax_rates'];
                    $from_rate = $tax['from']['top_income_tax_rate'] ?? 0;
                    $to_rate = $tax['to']['top_income_tax_rate'] ?? 0;
                    $tax_diff = $to_rate - $from_rate;

                    if ($charts) {
                        echo $charts->render_comparison_bar(
                            $from_rate,
                            $to_rate,
                            $from_info['display_name'],
                            $to_info['display_name'],
                            array(
                                'title' => 'Top Marginal Income Tax Rate',
                                'unit' => '%',
                                'format' => 'percent',
                                'max' => 60,
                            )
                        );
                    }
                    ?>

                    <div class="sffc-tax-insights">
                        <h3>Key Tax Considerations</h3>
                        <ul>
                            <?php if ($tax_diff < -10): ?>
                                <li>Moving to <?php echo esc_html($to_info['display_name']); ?> could significantly reduce your tax burden</li>
                            <?php elseif ($tax_diff > 10): ?>
                                <li>Expect higher income taxes in <?php echo esc_html($to_info['display_name']); ?></li>
                            <?php endif; ?>
                            <li>Consult a tax advisor familiar with both jurisdictions</li>
                            <li>Understand any double taxation treaties that may apply</li>
                            <li>Consider timing of the move for tax optimization</li>
                        </ul>
                    </div>
                <?php else: ?>
                    <p>Tax comparison data is being compiled. We recommend consulting with a cross-border tax specialist for personalized advice.</p>
                <?php endif; ?>
            </section>

            <!-- Visa & Work Permits -->
            <section id="sffc-visa" class="sffc-section">
                <h2>Visa & Work Permits</h2>
                <?php if ($comparison_data && !empty($comparison_data['mobility']['visa_requirements'])): ?>
                    <?php
                    $visa = $comparison_data['mobility']['visa_requirements'];

                    $visa_facts = array();
                    if (!empty($visa['difficulty'])) {
                        $visa_facts['Process Difficulty'] = $visa['difficulty'];
                    }
                    if (!empty($visa['work_visa_type'])) {
                        $visa_facts['Main Visa Type'] = $visa['work_visa_type'];
                    }
                    if (!empty($visa['processing_time'])) {
                        $visa_facts['Processing Time'] = $visa['processing_time'];
                    }
                    if (!empty($visa['sponsorship_required'])) {
                        $visa_facts['Sponsorship'] = $visa['sponsorship_required'];
                    }

                    if (!empty($visa_facts) && $charts) {
                        echo $charts->render_key_facts($visa_facts);
                    }
                    ?>

                    <div class="sffc-visa-tips">
                        <h3>Application Tips</h3>
                        <ul>
                            <li>Start the visa process early - ideally 3-6 months before planned move</li>
                            <li>Many finance roles come with visa sponsorship</li>
                            <li>Keep all employment documents organized and accessible</li>
                            <li>Consider consulting an immigration lawyer for complex cases</li>
                        </ul>
                    </div>
                <?php else: ?>
                    <p>Visa requirements vary based on citizenship. We recommend checking with the destination country's embassy for current requirements.</p>
                <?php endif; ?>
            </section>

            <!-- Jobs Section -->
            <section id="sffc-jobs" class="sffc-section">
                <h2>Jobs in <?php echo esc_html($to_info['display_name']); ?></h2>

                <?php if (!empty($destination_jobs)): ?>
                    <div class="sffc-jobs-grid">
                        <?php foreach ($destination_jobs as $job): ?>
                            <?php
                            $company = get_post_meta($job->ID, 'sffc_company_name', true);
                            $location = get_post_meta($job->ID, 'sffc_job_location', true);
                            $salary = get_post_meta($job->ID, 'sffc_salary_display', true);
                            ?>
                            <article class="sffc-job-card">
                                <h3 class="sffc-job-title">
                                    <a href="<?php echo get_permalink($job->ID); ?>">
                                        <?php echo esc_html($job->post_title); ?>
                                    </a>
                                </h3>
                                <?php if ($company): ?>
                                    <div class="sffc-job-company"><?php echo esc_html($company); ?></div>
                                <?php endif; ?>
                                <?php if ($location): ?>
                                    <div class="sffc-job-location"><?php echo esc_html($location); ?></div>
                                <?php endif; ?>
                                <?php if ($salary): ?>
                                    <div class="sffc-job-salary"><?php echo esc_html($salary); ?></div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <div class="sffc-jobs-cta">
                        <a href="<?php echo home_url('/jobs/?location=' . urlencode($to_info['name'])); ?>" class="sffc-btn sffc-btn-primary">
                            View All Jobs in <?php echo esc_html($to_info['display_name']); ?>
                        </a>
                    </div>
                <?php else: ?>
                    <p>We're currently updating our job listings for <?php echo esc_html($to_info['display_name']); ?>. Check back soon or browse all available positions.</p>
                    <a href="<?php echo home_url('/jobs/'); ?>" class="sffc-btn sffc-btn-primary">Browse All Jobs</a>
                <?php endif; ?>
            </section>

            <!-- Related Routes -->
            <section class="sffc-section sffc-related-routes">
                <h2>Related Relocation Guides</h2>
                <div class="sffc-routes-grid">
                    <?php
                    $popular_routes = $relocation_pages->get_popular_routes();
                    $related_count = 0;

                    foreach ($popular_routes as $route) {
                        if ($related_count >= 4) break;

                        if ($route['from'] === $from_slug && $route['to'] === $to_slug) continue;

                        if ($route['from'] === $from_slug || $route['to'] === $to_slug ||
                            $route['from'] === $to_slug || $route['to'] === $from_slug) {

                            $route_from = $relocation_pages->get_location_info($route['from']);
                            $route_to = $relocation_pages->get_location_info($route['to']);

                            if ($route_from && $route_to) {
                                $related_count++;
                                $route_url = home_url('/relocating/' . $route['from'] . '-to-' . $route['to'] . '/');
                                ?>
                                <a href="<?php echo esc_url($route_url); ?>" class="sffc-route-card">
                                    <div class="sffc-route-locations">
                                        <span class="sffc-route-from"><?php echo esc_html($route_from['display_name']); ?></span>
                                        <span class="sffc-route-arrow">&rarr;</span>
                                        <span class="sffc-route-to"><?php echo esc_html($route_to['display_name']); ?></span>
                                    </div>
                                </a>
                                <?php
                            }
                        }
                    }

                    if ($related_count === 0) {
                        echo '<p>Explore more relocation guides on our <a href="' . home_url('/relocating/') . '">main relocations page</a>.</p>';
                    }
                    ?>
                </div>
            </section>
        </div>

        <!-- Sidebar -->
        <aside class="sffc-sidebar">
            <!-- CTA Box -->
            <div class="sffc-sidebar-cta">
                <h3>Planning Your Move?</h3>
                <p>Get personalized career advice for your relocation</p>
                <a href="<?php echo home_url('/chat/'); ?>" class="sffc-btn sffc-btn-light">Talk to MENA Careers</a>
            </div>

            <!-- Quick Facts -->
            <div class="sffc-sidebar-box">
                <h3>Quick Facts: <?php echo esc_html($to_info['display_name']); ?></h3>
                <dl class="sffc-quick-facts">
                    <div class="sffc-fact-row">
                        <dt>Currency</dt>
                        <dd><?php echo esc_html($to_info['currency']); ?></dd>
                    </div>
                    <div class="sffc-fact-row">
                        <dt>Type</dt>
                        <dd><?php echo ucfirst(esc_html($to_info['type'])); ?></dd>
                    </div>
                    <?php if ($to_info['type'] === 'city'): ?>
                        <div class="sffc-fact-row">
                            <dt>Country</dt>
                            <dd><?php echo esc_html($to_info['country_name']); ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>
            </div>

            <!-- Newsletter Signup -->
            <div class="sffc-sidebar-box sffc-newsletter-box">
                <h3>Stay Updated</h3>
                <p>Get the latest market insights and job opportunities</p>
                <form class="sffc-newsletter-form" data-sffc-newsletter>
                    <input type="email" name="email" placeholder="Your email" required>
                    <button type="submit" class="sffc-btn sffc-btn-primary">Subscribe</button>
                    <div class="sffc-newsletter-message"></div>
                </form>
            </div>
        </aside>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var form = document.querySelector('[data-sffc-newsletter]');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            var email = form.querySelector('input[name="email"]').value;
            var message = form.querySelector('.sffc-newsletter-message');
            var button = form.querySelector('button');

            if (!email) return;

            button.disabled = true;
            button.textContent = 'Subscribing...';

            var formData = new FormData();
            formData.append('action', 'sffc_newsletter_subscribe');
            formData.append('email', email);
            formData.append('source', 'relocation-page');

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                message.style.display = 'block';
                if (data.success) {
                    message.className = 'sffc-newsletter-message sffc-success';
                    message.textContent = 'Thanks for subscribing!';
                    form.querySelector('input[name="email"]').value = '';
                } else {
                    message.className = 'sffc-newsletter-message sffc-error';
                    message.textContent = data.data || 'Something went wrong. Please try again.';
                }
            })
            .catch(function() {
                message.style.display = 'block';
                message.className = 'sffc-newsletter-message sffc-success';
                message.textContent = "Thanks for your interest! We'll be in touch.";
            })
            .finally(function() {
                button.disabled = false;
                button.textContent = 'Subscribe';
            });
        });
    }
});
</script>

<?php get_footer(); ?>
