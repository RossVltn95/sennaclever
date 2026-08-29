<?php
/**
 * Archive template for relocation pages
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

get_header();

// Get relocation pages instance
$relocation_pages = SFFC_Relocation_Pages::get_instance();
$locations = $relocation_pages->get_all_locations();
$popular_routes = $relocation_pages->get_popular_routes();
$stats = $relocation_pages->get_statistics();

// Page title and meta
$page_title = 'Relocation Guides for Finance Professionals | Moving Abroad for Work';
$meta_description = 'Comprehensive relocation guides for finance and investment banking professionals. Compare cost of living, tax rates, visa requirements, and job opportunities across major financial hubs.';

// Set document title
add_filter('pre_get_document_title', function() use ($page_title) {
    return $page_title;
});

// Add meta description
add_action('wp_head', function() use ($meta_description, $page_title) {
    echo '<meta name="description" content="' . esc_attr($meta_description) . '">' . "\n";
    echo '<meta property="og:title" content="' . esc_attr($page_title) . '">' . "\n";
    echo '<meta property="og:description" content="' . esc_attr($meta_description) . '">' . "\n";
    echo '<meta property="og:type" content="website">' . "\n";
});
?>

<div class="sffc-relocation-archive">
    <!-- Hero Section -->
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

    <div class="sffc-archive-content">
        <!-- Quick Route Finder -->
        <section class="sffc-route-finder">
            <h2>Find Your Route</h2>
            <form class="sffc-route-form" action="" method="get">
                <div class="sffc-form-row">
                    <div class="sffc-form-group">
                        <label for="route-from">I'm moving from</label>
                        <select id="route-from" required>
                            <option value="">Select origin...</option>
                            <optgroup label="Popular Cities">
                                <?php
                                $popular_cities = array('london', 'new-york', 'dubai', 'singapore', 'hong-kong', 'zurich', 'paris', 'frankfurt');
                                foreach ($popular_cities as $city_slug):
                                    $city_info = $relocation_pages->get_location_info($city_slug);
                                    if ($city_info):
                                ?>
                                    <option value="<?php echo esc_attr($city_slug); ?>"><?php echo esc_html($city_info['name']); ?></option>
                                <?php
                                    endif;
                                endforeach;
                                ?>
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
                        <label for="route-to">To</label>
                        <select id="route-to" required>
                            <option value="">Select destination...</option>
                            <optgroup label="Popular Cities">
                                <?php
                                foreach ($popular_cities as $city_slug):
                                    $city_info = $relocation_pages->get_location_info($city_slug);
                                    if ($city_info):
                                ?>
                                    <option value="<?php echo esc_attr($city_slug); ?>"><?php echo esc_html($city_info['name']); ?></option>
                                <?php
                                    endif;
                                endforeach;
                                ?>
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

        <!-- Popular Routes -->
        <section class="sffc-popular-routes">
            <h2>Popular Relocation Routes</h2>
            <p class="sffc-section-desc">The most common career moves in finance and professional services.</p>

            <div class="sffc-routes-grid">
                <?php
                $displayed = 0;
                foreach ($popular_routes as $route):
                    if ($displayed >= 12) break;

                    $from_info = $relocation_pages->get_location_info($route['from']);
                    $to_info = $relocation_pages->get_location_info($route['to']);

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

        <!-- Destinations by Region -->
        <section class="sffc-regions">
            <h2>Explore by Region</h2>

            <div class="sffc-regions-grid">
                <!-- Europe -->
                <div class="sffc-region-card">
                    <h3>Europe</h3>
                    <ul class="sffc-destination-list">
                        <?php
                        $europe = array('united-kingdom', 'germany', 'france', 'switzerland', 'netherlands', 'ireland', 'spain', 'italy');
                        foreach ($europe as $country_slug):
                            if (isset($locations[$country_slug])):
                        ?>
                            <li>
                                <strong><?php echo esc_html($locations[$country_slug]['name']); ?></strong>
                                <span class="sffc-cities">
                                    <?php echo implode(', ', array_column($locations[$country_slug]['cities'], 'name')); ?>
                                </span>
                            </li>
                        <?php
                            endif;
                        endforeach;
                        ?>
                    </ul>
                </div>

                <!-- Asia Pacific -->
                <div class="sffc-region-card">
                    <h3>Asia Pacific</h3>
                    <ul class="sffc-destination-list">
                        <?php
                        $apac = array('singapore', 'hong-kong', 'japan', 'australia', 'india');
                        foreach ($apac as $country_slug):
                            if (isset($locations[$country_slug])):
                        ?>
                            <li>
                                <strong><?php echo esc_html($locations[$country_slug]['name']); ?></strong>
                                <span class="sffc-cities">
                                    <?php echo implode(', ', array_column($locations[$country_slug]['cities'], 'name')); ?>
                                </span>
                            </li>
                        <?php
                            endif;
                        endforeach;
                        ?>
                    </ul>
                </div>

                <!-- private equity -->
                <div class="sffc-region-card">
                    <h3>private equity</h3>
                    <ul class="sffc-destination-list">
                        <?php
                        $me = array('united-arab-emirates');
                        foreach ($me as $country_slug):
                            if (isset($locations[$country_slug])):
                        ?>
                            <li>
                                <strong><?php echo esc_html($locations[$country_slug]['name']); ?></strong>
                                <span class="sffc-cities">
                                    <?php echo implode(', ', array_column($locations[$country_slug]['cities'], 'name')); ?>
                                </span>
                            </li>
                        <?php
                            endif;
                        endforeach;
                        ?>
                    </ul>
                </div>

                <!-- Americas -->
                <div class="sffc-region-card">
                    <h3>Americas</h3>
                    <ul class="sffc-destination-list">
                        <?php
                        $americas = array('united-states', 'canada', 'brazil');
                        foreach ($americas as $country_slug):
                            if (isset($locations[$country_slug])):
                        ?>
                            <li>
                                <strong><?php echo esc_html($locations[$country_slug]['name']); ?></strong>
                                <span class="sffc-cities">
                                    <?php echo implode(', ', array_column($locations[$country_slug]['cities'], 'name')); ?>
                                </span>
                            </li>
                        <?php
                            endif;
                        endforeach;
                        ?>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Tax Haven Moves -->
        <section class="sffc-featured-routes">
            <h2>Popular Tax-Efficient Moves</h2>
            <p class="sffc-section-desc">Routes favored for their tax advantages.</p>

            <div class="sffc-featured-grid">
                <?php
                $tax_routes = array(
                    array('from' => 'london', 'to' => 'dubai', 'benefit' => '0% income tax'),
                    array('from' => 'paris', 'to' => 'singapore', 'benefit' => '22% max tax'),
                    array('from' => 'new-york', 'to' => 'hong-kong', 'benefit' => '17% max tax'),
                    array('from' => 'milan', 'to' => 'zurich', 'benefit' => 'Favorable rates'),
                );

                foreach ($tax_routes as $route):
                    $from_info = $relocation_pages->get_location_info($route['from']);
                    $to_info = $relocation_pages->get_location_info($route['to']);
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

        <!-- CTA Section -->
        <section class="sffc-archive-cta">
            <h2>Need Personalized Advice?</h2>
            <p>Get tailored relocation guidance based on your career goals and circumstances.</p>
            <a href="<?php echo home_url('/chat/'); ?>" class="sffc-btn sffc-btn-primary sffc-btn-large">Talk to MENA Careers</a>
        </section>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var form = document.querySelector('.sffc-route-form');
    var fromSelect = document.getElementById('route-from');
    var toSelect = document.getElementById('route-to');

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        var from = fromSelect.value;
        var to = toSelect.value;

        if (from && to && from !== to) {
            window.location.href = '<?php echo home_url('/relocating/'); ?>' + from + '-to-' + to + '/';
        } else if (from === to) {
            alert('Please select different locations for origin and destination.');
        }
    });
});
</script>

<?php get_footer(); ?>
