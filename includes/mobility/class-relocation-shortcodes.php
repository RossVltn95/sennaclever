<?php
/**
 * Relocation Shortcodes
 *
 * Provides shortcodes for relocation content that work with any theme/Elementor.
 *
 * Shortcodes:
 * - [sffc_relocation_page] - Full single relocation page (auto-detects from URL or uses attributes)
 * - [sffc_relocation_archive] - Full archive/landing page
 * - [sffc_relocation_comparison from="x" to="y"] - Comparison dashboard only
 * - [sffc_relocation_route_finder] - Route finder form only
 * - [sffc_relocation_popular_routes limit="12"] - Popular routes grid
 * - [sffc_relocation_regions] - Regions/destinations grid
 *
 * @package SFFC_Careers
 * @since 11.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Relocation_Shortcodes {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Relocation pages instance
     */
    private $pages = null;

    /**
     * Charts instance
     */
    private $charts = null;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action('init', array($this, 'register_shortcodes'));
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
    }

    /**
     * Register all shortcodes
     */
    public function register_shortcodes() {
        add_shortcode('sffc_relocation_page', array($this, 'render_relocation_page'));
        add_shortcode('sffc_relocation_archive', array($this, 'render_relocation_archive'));
        add_shortcode('sffc_relocation_comparison', array($this, 'render_comparison'));
        add_shortcode('sffc_relocation_route_finder', array($this, 'render_route_finder'));
        add_shortcode('sffc_relocation_popular_routes', array($this, 'render_popular_routes'));
        add_shortcode('sffc_relocation_regions', array($this, 'render_regions'));
        add_shortcode('sffc_relocation_jobs', array($this, 'render_location_jobs'));
    }

    /**
     * Register and enqueue assets
     */
    public function register_assets() {
        wp_register_style(
            'sffc-relocation-shortcodes',
            SFFC_PLUGIN_URL . 'assets/css/relocation-shortcodes.css',
            array(),
            SFFC_VERSION
        );

        wp_register_script(
            'sffc-relocation-shortcodes',
            SFFC_PLUGIN_URL . 'assets/js/relocation-shortcodes.js',
            array(),
            SFFC_VERSION,
            true
        );

        wp_localize_script('sffc-relocation-shortcodes', 'sffcRelocation', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'baseUrl' => home_url('/relocating/'),
        ));
    }

    /**
     * Enqueue assets when shortcode is used
     */
    private function enqueue_assets() {
        wp_enqueue_style('sffc-relocation-shortcodes');
        wp_enqueue_style('sffc-relocation-charts');
        wp_enqueue_script('sffc-relocation-shortcodes');
    }

    /**
     * Get pages instance
     */
    private function get_pages() {
        if ($this->pages === null) {
            $this->pages = SFFC_Relocation_Pages::get_instance();
        }
        return $this->pages;
    }

    /**
     * Get charts instance
     */
    private function get_charts() {
        if ($this->charts === null) {
            if (class_exists('SFFC_Relocation_Charts')) {
                $this->charts = SFFC_Relocation_Charts::get_instance();
            }
        }
        return $this->charts;
    }

    /**
     * Render full relocation page
     * [sffc_relocation_page from="london" to="dubai"]
     * If no attributes, reads from URL query vars
     */
    public function render_relocation_page($atts = array()) {
        $this->enqueue_assets();

        $atts = shortcode_atts(array(
            'from' => '',
            'to' => '',
        ), $atts);

        $pages = $this->get_pages();
        $charts = $this->get_charts();

        // Get from URL if not specified in attributes
        $from_slug = !empty($atts['from']) ? $atts['from'] : get_query_var('sffc_from_location');
        $to_slug = !empty($atts['to']) ? $atts['to'] : get_query_var('sffc_to_location');

        // Validate locations
        $from_info = $pages->get_location_info($from_slug);
        $to_info = $pages->get_location_info($to_slug);

        if (!$from_info || !$to_info) {
            return $this->render_error('Invalid relocation route. Please check the locations specified.');
        }

        // Get data
        $comparison_data = $pages->get_comparison_data($from_slug, $to_slug);
        $destination_jobs = $pages->get_location_jobs($to_slug, 6);
        $existing_post = $pages->get_relocation_page($from_slug, $to_slug);

        ob_start();
        ?>
        <div class="sffc-relocation-page">
            <?php echo $this->render_hero($from_info, $to_info, $comparison_data, $destination_jobs); ?>

            <div class="sffc-relocation-content">
                <div class="sffc-relocation-main">
                    <?php echo $this->render_quick_nav(); ?>

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
                    <?php echo $this->render_cost_of_living_section($from_info, $to_info, $comparison_data, $charts); ?>

                    <!-- Tax Comparison -->
                    <?php echo $this->render_tax_section($from_info, $to_info, $comparison_data, $charts); ?>

                    <!-- Visa Requirements -->
                    <?php echo $this->render_visa_section($to_info, $comparison_data, $charts); ?>

                    <!-- Jobs Section -->
                    <section id="sffc-jobs" class="sffc-section">
                        <h2>Jobs in <?php echo esc_html($to_info['display_name']); ?></h2>
                        <?php echo $this->render_jobs_grid($destination_jobs, $to_info); ?>
                    </section>

                    <!-- Related Routes -->
                    <?php echo $this->render_related_routes($from_slug, $to_slug); ?>
                </div>

                <?php echo $this->render_sidebar($to_info); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render hero section
     */
    private function render_hero($from_info, $to_info, $comparison_data, $destination_jobs) {
        ob_start();
        ?>
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
        <?php
        return ob_get_clean();
    }

    /**
     * Render quick navigation
     */
    private function render_quick_nav() {
        ob_start();
        ?>
        <nav class="sffc-quick-nav">
            <a href="#sffc-overview">Overview</a>
            <a href="#sffc-comparison">Comparison</a>
            <a href="#sffc-cost-of-living">Cost of Living</a>
            <a href="#sffc-taxes">Taxes</a>
            <a href="#sffc-visa">Visa</a>
            <a href="#sffc-jobs">Jobs</a>
        </nav>
        <?php
        return ob_get_clean();
    }

    /**
     * Render cost of living section
     */
    private function render_cost_of_living_section($from_info, $to_info, $comparison_data, $charts) {
        ob_start();
        ?>
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
        <?php
        return ob_get_clean();
    }

    /**
     * Render tax section
     */
    private function render_tax_section($from_info, $to_info, $comparison_data, $charts) {
        ob_start();
        ?>
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
        <?php
        return ob_get_clean();
    }

    /**
     * Render visa section
     */
    private function render_visa_section($to_info, $comparison_data, $charts) {
        ob_start();
        ?>
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
        <?php
        return ob_get_clean();
    }

    /**
     * Render jobs grid
     */
    private function render_jobs_grid($jobs, $to_info) {
        ob_start();

        if (!empty($jobs)):
        ?>
            <div class="sffc-jobs-grid">
                <?php foreach ($jobs as $job): ?>
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
        <?php
        endif;

        return ob_get_clean();
    }

    /**
     * Render related routes
     */
    private function render_related_routes($from_slug, $to_slug) {
        $pages = $this->get_pages();
        $popular_routes = $pages->get_popular_routes();

        ob_start();
        ?>
        <section class="sffc-section sffc-related-routes">
            <h2>Related Relocation Guides</h2>
            <div class="sffc-routes-grid">
                <?php
                $related_count = 0;

                foreach ($popular_routes as $route) {
                    if ($related_count >= 4) break;

                    if ($route['from'] === $from_slug && $route['to'] === $to_slug) continue;

                    if ($route['from'] === $from_slug || $route['to'] === $to_slug ||
                        $route['from'] === $to_slug || $route['to'] === $from_slug) {

                        $route_from = $pages->get_location_info($route['from']);
                        $route_to = $pages->get_location_info($route['to']);

                        if ($route_from && $route_to) {
                            $related_count++;
                            $route_url = home_url('/relocating/' . $route['from'] . '-to-' . $route['to'] . '/');
                            ?>
                            <a href="<?php echo esc_url($route_url); ?>" class="sffc-route-card">
                                <span class="sffc-route-from"><?php echo esc_html($route_from['display_name']); ?></span>
                                <span class="sffc-route-arrow">&rarr;</span>
                                <span class="sffc-route-to"><?php echo esc_html($route_to['display_name']); ?></span>
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
        <?php
        return ob_get_clean();
    }

    /**
     * Render sidebar
     */
    private function render_sidebar($to_info) {
        ob_start();
        ?>
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
        <?php
        return ob_get_clean();
    }

    /**
     * Render archive/landing page
     * [sffc_relocation_archive]
     */
    public function render_relocation_archive($atts = array()) {
        $this->enqueue_assets();

        $pages = $this->get_pages();
        $locations = $pages->get_all_locations();
        $popular_routes = $pages->get_popular_routes();
        $stats = $pages->get_statistics();

        ob_start();
        ?>
        <div class="sffc-relocation-archive">
            <?php echo $this->render_archive_hero($stats); ?>

            <div class="sffc-archive-content">
                <?php echo $this->render_route_finder(); ?>
                <?php echo $this->render_popular_routes(array('limit' => 12)); ?>
                <?php echo $this->render_regions(); ?>
                <?php echo $this->render_featured_routes(); ?>
                <?php echo $this->render_archive_cta(); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render archive hero
     */
    private function render_archive_hero($stats) {
        ob_start();
        ?>
        <header class="sffc-hero sffc-hero-archive">
            <div class="sffc-hero-inner">
                <h1 class="sffc-hero-title">Relocation Guides</h1>
                <p class="sffc-hero-subtitle">
                    Everything you need to know about relocating for your finance career.
                    Compare locations, understand visa requirements, and find opportunities.
                </p>

                <div class="sffc-hero-stats">
                    <div class="sffc-hero-stat">
                        <span class="sffc-stat-value"><?php echo esc_html($stats['countries']); ?></span>
                        <span class="sffc-stat-label">Countries</span>
                    </div>
                    <div class="sffc-hero-stat">
                        <span class="sffc-stat-value"><?php echo esc_html($stats['cities']); ?></span>
                        <span class="sffc-stat-label">Cities</span>
                    </div>
                    <div class="sffc-hero-stat">
                        <span class="sffc-stat-value"><?php echo esc_html($stats['popular_routes']); ?>+</span>
                        <span class="sffc-stat-label">Routes</span>
                    </div>
                </div>
            </div>
        </header>
        <?php
        return ob_get_clean();
    }

    /**
     * Render route finder form
     * [sffc_relocation_route_finder]
     */
    public function render_route_finder($atts = array()) {
        $this->enqueue_assets();

        $pages = $this->get_pages();
        $locations = $pages->get_all_locations();

        $popular_cities = array('london', 'new-york', 'dubai', 'singapore', 'hong-kong', 'zurich', 'paris', 'frankfurt');

        ob_start();
        ?>
        <section class="sffc-route-finder">
            <h2>Find Your Route</h2>
            <form class="sffc-route-form" data-sffc-route-finder>
                <div class="sffc-form-row">
                    <div class="sffc-form-group">
                        <label for="sffc-route-from">I'm moving from</label>
                        <select id="sffc-route-from" name="from" required>
                            <option value="">Select origin...</option>
                            <optgroup label="Popular Cities">
                                <?php foreach ($popular_cities as $city_slug):
                                    $city_info = $pages->get_location_info($city_slug);
                                    if ($city_info):
                                ?>
                                    <option value="<?php echo esc_attr($city_slug); ?>"><?php echo esc_html($city_info['name']); ?></option>
                                <?php endif; endforeach; ?>
                            </optgroup>
                            <optgroup label="All Countries">
                                <?php foreach ($locations as $slug => $data): ?>
                                    <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($data['name']); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>

                    <div class="sffc-form-arrow">&rarr;</div>

                    <div class="sffc-form-group">
                        <label for="sffc-route-to">To</label>
                        <select id="sffc-route-to" name="to" required>
                            <option value="">Select destination...</option>
                            <optgroup label="Popular Cities">
                                <?php foreach ($popular_cities as $city_slug):
                                    $city_info = $pages->get_location_info($city_slug);
                                    if ($city_info):
                                ?>
                                    <option value="<?php echo esc_attr($city_slug); ?>"><?php echo esc_html($city_info['name']); ?></option>
                                <?php endif; endforeach; ?>
                            </optgroup>
                            <optgroup label="All Countries">
                                <?php foreach ($locations as $slug => $data): ?>
                                    <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($data['name']); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>

                    <button type="submit" class="sffc-btn sffc-btn-primary">View Guide</button>
                </div>
            </form>
        </section>
        <?php
        return ob_get_clean();
    }

    /**
     * Render popular routes grid
     * [sffc_relocation_popular_routes limit="12"]
     */
    public function render_popular_routes($atts = array()) {
        $this->enqueue_assets();

        $atts = shortcode_atts(array(
            'limit' => 12,
        ), $atts);

        $pages = $this->get_pages();
        $popular_routes = $pages->get_popular_routes();

        ob_start();
        ?>
        <section class="sffc-popular-routes">
            <h2>Popular Relocation Routes</h2>
            <p class="sffc-section-desc">The most common career moves in finance and professional services.</p>

            <div class="sffc-routes-grid">
                <?php
                $displayed = 0;
                foreach ($popular_routes as $route):
                    if ($displayed >= $atts['limit']) break;

                    $from_info = $pages->get_location_info($route['from']);
                    $to_info = $pages->get_location_info($route['to']);

                    if (!$from_info || !$to_info) continue;

                    $route_url = home_url('/relocating/' . $route['from'] . '-to-' . $route['to'] . '/');
                    $displayed++;
                ?>
                    <a href="<?php echo esc_url($route_url); ?>" class="sffc-route-card">
                        <div class="sffc-route-locations">
                            <span class="sffc-route-from"><?php echo esc_html($from_info['display_name']); ?></span>
                            <span class="sffc-route-arrow">&rarr;</span>
                            <span class="sffc-route-to"><?php echo esc_html($to_info['display_name']); ?></span>
                        </div>
                        <div class="sffc-route-type"><?php echo ucfirst($route['type']); ?> to <?php echo ucfirst($route['type']); ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    /**
     * Render regions grid
     * [sffc_relocation_regions]
     */
    public function render_regions($atts = array()) {
        $this->enqueue_assets();

        $pages = $this->get_pages();
        $locations = $pages->get_all_locations();

        $regions = array(
            'Europe' => array('united-kingdom', 'germany', 'france', 'switzerland', 'netherlands', 'ireland', 'spain', 'italy', 'portugal', 'sweden', 'denmark', 'norway', 'luxembourg'),
            'Asia Pacific' => array('singapore', 'hong-kong', 'japan', 'australia', 'india'),
            'private equity' => array('united-arab-emirates'),
            'Americas' => array('united-states', 'canada', 'brazil'),
            'Africa' => array('south-africa'),
        );

        ob_start();
        ?>
        <section class="sffc-regions">
            <h2>Explore by Region</h2>

            <div class="sffc-regions-grid">
                <?php foreach ($regions as $region_name => $country_slugs): ?>
                    <div class="sffc-region-card">
                        <h3><?php echo esc_html($region_name); ?></h3>
                        <ul class="sffc-destination-list">
                            <?php foreach ($country_slugs as $country_slug):
                                if (isset($locations[$country_slug])):
                            ?>
                                <li>
                                    <strong><?php echo esc_html($locations[$country_slug]['name']); ?></strong>
                                    <span class="sffc-cities">
                                        <?php echo implode(', ', array_column($locations[$country_slug]['cities'], 'name')); ?>
                                    </span>
                                </li>
                            <?php endif; endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    /**
     * Render featured/tax-efficient routes
     */
    private function render_featured_routes() {
        $pages = $this->get_pages();

        $tax_routes = array(
            array('from' => 'london', 'to' => 'dubai', 'benefit' => '0% income tax'),
            array('from' => 'paris', 'to' => 'singapore', 'benefit' => '22% max tax'),
            array('from' => 'new-york', 'to' => 'hong-kong', 'benefit' => '17% max tax'),
            array('from' => 'milan', 'to' => 'zurich', 'benefit' => 'Favorable rates'),
        );

        ob_start();
        ?>
        <section class="sffc-featured-routes">
            <h2>Popular Tax-Efficient Moves</h2>
            <p class="sffc-section-desc">Routes favored for their tax advantages.</p>

            <div class="sffc-featured-grid">
                <?php foreach ($tax_routes as $route):
                    $from_info = $pages->get_location_info($route['from']);
                    $to_info = $pages->get_location_info($route['to']);
                    if (!$from_info || !$to_info) continue;
                    $route_url = home_url('/relocating/' . $route['from'] . '-to-' . $route['to'] . '/');
                ?>
                    <a href="<?php echo esc_url($route_url); ?>" class="sffc-featured-card">
                        <div class="sffc-featured-route">
                            <?php echo esc_html($from_info['display_name']); ?> &rarr; <?php echo esc_html($to_info['display_name']); ?>
                        </div>
                        <div class="sffc-featured-benefit"><?php echo esc_html($route['benefit']); ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    /**
     * Render archive CTA
     */
    private function render_archive_cta() {
        ob_start();
        ?>
        <section class="sffc-archive-cta">
            <h2>Need Personalized Advice?</h2>
            <p>Get tailored relocation guidance based on your career goals and circumstances.</p>
            <a href="<?php echo home_url('/chat/'); ?>" class="sffc-btn sffc-btn-primary sffc-btn-large">Talk to MENA Careers</a>
        </section>
        <?php
        return ob_get_clean();
    }

    /**
     * Render comparison dashboard only
     * [sffc_relocation_comparison from="london" to="dubai"]
     */
    public function render_comparison($atts = array()) {
        $this->enqueue_assets();

        $atts = shortcode_atts(array(
            'from' => '',
            'to' => '',
        ), $atts);

        $pages = $this->get_pages();
        $charts = $this->get_charts();

        $from_slug = !empty($atts['from']) ? $atts['from'] : get_query_var('sffc_from_location');
        $to_slug = !empty($atts['to']) ? $atts['to'] : get_query_var('sffc_to_location');

        $from_info = $pages->get_location_info($from_slug);
        $to_info = $pages->get_location_info($to_slug);

        if (!$from_info || !$to_info) {
            return $this->render_error('Invalid locations specified for comparison.');
        }

        $comparison_data = $pages->get_comparison_data($from_slug, $to_slug);

        if (!$comparison_data) {
            return $this->render_error('No comparison data available for this route.');
        }

        if (!$charts) {
            return $this->render_error('Charts component is not available.');
        }

        return '<div class="sffc-comparison-wrapper">' . $charts->render_comparison_dashboard($comparison_data) . '</div>';
    }

    /**
     * Render location jobs
     * [sffc_relocation_jobs location="dubai" limit="6"]
     */
    public function render_location_jobs($atts = array()) {
        $this->enqueue_assets();

        $atts = shortcode_atts(array(
            'location' => '',
            'limit' => 6,
        ), $atts);

        $pages = $this->get_pages();

        $location_slug = !empty($atts['location']) ? $atts['location'] : get_query_var('sffc_to_location');

        if (empty($location_slug)) {
            return $this->render_error('No location specified for jobs.');
        }

        $location_info = $pages->get_location_info($location_slug);
        if (!$location_info) {
            return $this->render_error('Invalid location specified.');
        }

        $jobs = $pages->get_location_jobs($location_slug, intval($atts['limit']));

        ob_start();
        ?>
        <div class="sffc-jobs-section">
            <h2>Jobs in <?php echo esc_html($location_info['display_name']); ?></h2>
            <?php echo $this->render_jobs_grid($jobs, $location_info); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render error message
     */
    private function render_error($message) {
        return '<div class="sffc-error"><p>' . esc_html($message) . '</p></div>';
    }
}

// Initialize
add_action('plugins_loaded', function() {
    SFFC_Relocation_Shortcodes::get_instance();
});
