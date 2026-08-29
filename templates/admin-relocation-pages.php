<?php
/**
 * Admin template for managing relocation pages
 *
 * @package SFFC_Careers
 * @since 11.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get statistics
$stats = $this->get_statistics();

// Get all locations
$all_locations = $this->get_all_locations();

// Get popular routes
$popular_routes = $this->get_popular_routes();

// Get existing pages
$existing_pages = get_posts(array(
    'post_type' => SFFC_Relocation_Pages::POST_TYPE,
    'posts_per_page' => -1,
    'post_status' => array('publish', 'draft'),
));

$existing_slugs = array();
foreach ($existing_pages as $page) {
    $existing_slugs[] = $page->post_name;
}

// Get scheduler stats
$scheduler = class_exists('SFFC_Relocation_Scheduler') ? SFFC_Relocation_Scheduler::get_instance() : null;
$scheduler_stats = $scheduler ? $scheduler->get_statistics() : null;
$scheduler_log = $scheduler ? $scheduler->get_log(20) : array();
?>

<div class="wrap sffc-admin-relocation">
    <h1>Relocation Pages</h1>
    <p class="description">Generate programmatic SEO pages for "Moving to [Location] from [Location]" queries.</p>

    <!-- Statistics Cards -->
    <div class="sffc-stats-grid">
        <div class="sffc-stat-card">
            <span class="sffc-stat-number"><?php echo esc_html($stats['published_pages']); ?></span>
            <span class="sffc-stat-label">Published Pages</span>
        </div>
        <div class="sffc-stat-card">
            <span class="sffc-stat-number"><?php echo esc_html($stats['draft_pages']); ?></span>
            <span class="sffc-stat-label">Draft Pages</span>
        </div>
        <div class="sffc-stat-card">
            <span class="sffc-stat-number"><?php echo esc_html($stats['countries']); ?></span>
            <span class="sffc-stat-label">Countries</span>
        </div>
        <div class="sffc-stat-card">
            <span class="sffc-stat-number"><?php echo esc_html($stats['cities']); ?></span>
            <span class="sffc-stat-label">Cities</span>
        </div>
        <div class="sffc-stat-card">
            <span class="sffc-stat-number"><?php echo esc_html($stats['popular_routes']); ?></span>
            <span class="sffc-stat-label">Popular Routes</span>
        </div>
        <div class="sffc-stat-card">
            <span class="sffc-stat-number"><?php echo esc_html($stats['potential_pages']); ?></span>
            <span class="sffc-stat-label">Potential Pages</span>
        </div>
    </div>

    <!-- Tabs -->
    <nav class="nav-tab-wrapper">
        <a href="#scheduler" class="nav-tab nav-tab-active" data-tab="scheduler">Scheduler</a>
        <a href="#generate" class="nav-tab" data-tab="generate">Manual Generate</a>
        <a href="#existing" class="nav-tab" data-tab="existing">Existing Pages</a>
        <a href="#locations" class="nav-tab" data-tab="locations">Locations</a>
        <a href="#settings" class="nav-tab" data-tab="settings">Settings</a>
    </nav>

    <!-- Scheduler Tab -->
    <div id="tab-scheduler" class="sffc-tab-content active">
        <?php wp_nonce_field('sffc_admin_nonce', 'sffc_scheduler_nonce'); ?>
        <?php if ($scheduler_stats): ?>
        <div class="sffc-admin-section">
            <h2>Automated Generation Status</h2>

            <div class="sffc-scheduler-status">
                <div class="sffc-status-indicator <?php echo $scheduler_stats['scheduler_enabled'] ? 'sffc-status-active' : 'sffc-status-paused'; ?>">
                    <?php echo $scheduler_stats['scheduler_enabled'] ? 'Active' : 'Paused'; ?>
                </div>
            </div>

            <div class="sffc-stats-grid sffc-stats-grid-compact">
                <div class="sffc-stat-card">
                    <span class="sffc-stat-number"><?php echo esc_html($scheduler_stats['total_generated']); ?></span>
                    <span class="sffc-stat-label">Total Generated</span>
                </div>
                <div class="sffc-stat-card">
                    <span class="sffc-stat-number"><?php echo esc_html($scheduler_stats['generated_today']); ?></span>
                    <span class="sffc-stat-label">Generated Today</span>
                </div>
                <div class="sffc-stat-card">
                    <span class="sffc-stat-number"><?php echo esc_html($scheduler_stats['daily_limit']); ?></span>
                    <span class="sffc-stat-label">Daily Limit</span>
                </div>
                <div class="sffc-stat-card">
                    <span class="sffc-stat-number"><?php echo esc_html($scheduler_stats['queue_remaining']); ?></span>
                    <span class="sffc-stat-label">Queue Remaining</span>
                </div>
            </div>

            <h3>Queue Breakdown</h3>
            <table class="wp-list-table widefat fixed striped" style="max-width: 500px;">
                <thead>
                    <tr>
                        <th>Priority</th>
                        <th>Type</th>
                        <th>Remaining</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1 (First)</td>
                        <td>Popular Routes</td>
                        <td><?php echo esc_html($scheduler_stats['queue_by_priority'][1] ?? 0); ?></td>
                    </tr>
                    <tr>
                        <td>2</td>
                        <td>Major Hub Cities</td>
                        <td><?php echo esc_html($scheduler_stats['queue_by_priority'][2] ?? 0); ?></td>
                    </tr>
                    <tr>
                        <td>3</td>
                        <td>Country Routes</td>
                        <td><?php echo esc_html($scheduler_stats['queue_by_priority'][3] ?? 0); ?></td>
                    </tr>
                    <tr>
                        <td>4 (Last)</td>
                        <td>All City Routes</td>
                        <td><?php echo esc_html($scheduler_stats['queue_by_priority'][4] ?? 0); ?></td>
                    </tr>
                </tbody>
            </table>

            <?php if ($scheduler_stats['initial_burst_done']): ?>
                <p class="sffc-notice sffc-notice-success">Initial burst complete. Now generating <?php echo esc_html($scheduler_stats['daily_limit']); ?> pages/day.</p>
            <?php else: ?>
                <p class="sffc-notice sffc-notice-info">Initial burst mode: Will generate all popular routes first.</p>
            <?php endif; ?>

            <?php if ($scheduler_stats['next_scheduled']): ?>
                <p>Next scheduled run: <strong><?php echo esc_html(date('M j, Y g:i A', $scheduler_stats['next_scheduled'])); ?></strong></p>
            <?php endif; ?>

            <div class="sffc-scheduler-actions">
                <button type="button" id="sffc-trigger-generation" class="button button-primary">Run Generation Now</button>
                <button type="button" id="sffc-pause-scheduler" class="button">
                    <?php echo $scheduler_stats['scheduler_enabled'] ? 'Pause Scheduler' : 'Resume Scheduler'; ?>
                </button>
                <button type="button" id="sffc-reset-queue" class="button">Rebuild Queue</button>
            </div>
        </div>

        <div class="sffc-admin-section">
            <h2>Generation Log</h2>
            <div class="sffc-log-container">
                <?php if (!empty($scheduler_log)): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width: 150px;">Time</th>
                                <th>Message</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($scheduler_log as $entry): ?>
                                <tr>
                                    <td><?php echo esc_html($entry['time']); ?></td>
                                    <td><?php echo esc_html($entry['message']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No generation activity logged yet.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="sffc-admin-section">
            <p>Scheduler not available. Make sure the scheduler class is loaded.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Generate Pages Tab -->
    <div id="tab-generate" class="sffc-tab-content">
        <div class="sffc-admin-section">
            <h2>Generate Single Page</h2>
            <p>Create a single relocation guide page with Claude-generated content.</p>

            <form id="sffc-generate-single" class="sffc-generate-form">
                <?php wp_nonce_field('sffc_admin_nonce', 'sffc_nonce'); ?>

                <div class="sffc-form-row">
                    <div class="sffc-form-group">
                        <label for="from_location">From Location</label>
                        <select name="from_location" id="from_location" required>
                            <option value="">Select origin...</option>
                            <optgroup label="Countries">
                                <?php foreach ($all_locations as $slug => $data): ?>
                                    <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($data['name']); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="Cities">
                                <?php foreach ($all_locations as $country_slug => $country): ?>
                                    <?php foreach ($country['cities'] as $city_slug => $city): ?>
                                        <option value="<?php echo esc_attr($city_slug); ?>">
                                            <?php echo esc_html($city['name'] . ', ' . $country['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>

                    <div class="sffc-form-arrow">&rarr;</div>

                    <div class="sffc-form-group">
                        <label for="to_location">To Location</label>
                        <select name="to_location" id="to_location" required>
                            <option value="">Select destination...</option>
                            <optgroup label="Countries">
                                <?php foreach ($all_locations as $slug => $data): ?>
                                    <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($data['name']); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="Cities">
                                <?php foreach ($all_locations as $country_slug => $country): ?>
                                    <?php foreach ($country['cities'] as $city_slug => $city): ?>
                                        <option value="<?php echo esc_attr($city_slug); ?>">
                                            <?php echo esc_html($city['name'] . ', ' . $country['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>
                </div>

                <button type="submit" class="button button-primary">Generate Page</button>
                <span class="sffc-loading" style="display:none;">Generating content with Claude...</span>
            </form>

            <div id="sffc-generate-result" style="display:none;"></div>
        </div>

        <div class="sffc-admin-section">
            <h2>Bulk Generate Popular Routes</h2>
            <p>Generate multiple pages at once from our curated list of popular relocation routes.</p>

            <form id="sffc-generate-bulk" class="sffc-generate-form">
                <?php wp_nonce_field('sffc_admin_nonce', 'sffc_bulk_nonce'); ?>

                <div class="sffc-form-row">
                    <div class="sffc-form-group">
                        <label for="bulk_type">Route Type</label>
                        <select name="type" id="bulk_type">
                            <option value="popular">Popular Routes (<?php echo count($popular_routes); ?> routes)</option>
                            <option value="all_cities">All City Routes</option>
                            <option value="all_countries">All Country Routes</option>
                        </select>
                    </div>

                    <div class="sffc-form-group">
                        <label for="bulk_limit">Number of Pages</label>
                        <select name="limit" id="bulk_limit">
                            <option value="5">5 pages</option>
                            <option value="10" selected>10 pages</option>
                            <option value="25">25 pages</option>
                            <option value="50">50 pages</option>
                            <option value="100">100 pages</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="button button-primary">Start Bulk Generation</button>
                <span class="sffc-loading" style="display:none;">Generating pages...</span>
            </form>

            <div id="sffc-bulk-result" style="display:none;"></div>
        </div>

        <div class="sffc-admin-section">
            <h2>Popular Routes Preview</h2>
            <p>These are the pre-defined popular relocation routes with high search intent.</p>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>From</th>
                        <th>To</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($popular_routes, 0, 20) as $route): ?>
                        <?php
                        $from_info = $this->get_location_info($route['from']);
                        $to_info = $this->get_location_info($route['to']);
                        $slug = $route['from'] . '-to-' . $route['to'];
                        $exists = in_array($slug, $existing_slugs);
                        ?>
                        <tr>
                            <td><?php echo esc_html($from_info['display_name'] ?? $route['from']); ?></td>
                            <td><?php echo esc_html($to_info['display_name'] ?? $route['to']); ?></td>
                            <td><span class="sffc-badge sffc-badge-<?php echo esc_attr($route['type']); ?>"><?php echo ucfirst($route['type']); ?></span></td>
                            <td>
                                <?php if ($exists): ?>
                                    <span class="sffc-badge sffc-badge-success">Created</span>
                                <?php else: ?>
                                    <span class="sffc-badge sffc-badge-pending">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($exists): ?>
                                    <a href="<?php echo home_url('/relocating/' . $slug . '/'); ?>" target="_blank">View</a>
                                <?php else: ?>
                                    <button class="button sffc-quick-generate"
                                            data-from="<?php echo esc_attr($route['from']); ?>"
                                            data-to="<?php echo esc_attr($route['to']); ?>">
                                        Generate
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Existing Pages Tab -->
    <div id="tab-existing" class="sffc-tab-content">
        <div class="sffc-admin-section">
            <h2>Existing Relocation Pages</h2>

            <?php if (empty($existing_pages)): ?>
                <p>No relocation pages have been created yet. Go to the Generate tab to create some.</p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($existing_pages as $page): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($page->post_title); ?></strong>
                                    <br>
                                    <span class="sffc-url">/relocating/<?php echo esc_html($page->post_name); ?>/</span>
                                </td>
                                <td>
                                    <span class="sffc-badge sffc-badge-<?php echo $page->post_status === 'publish' ? 'success' : 'pending'; ?>">
                                        <?php echo ucfirst($page->post_status); ?>
                                    </span>
                                </td>
                                <td><?php echo get_the_date('M j, Y', $page); ?></td>
                                <td>
                                    <a href="<?php echo get_edit_post_link($page->ID); ?>">Edit</a> |
                                    <a href="<?php echo get_permalink($page->ID); ?>" target="_blank">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Locations Tab -->
    <div id="tab-locations" class="sffc-tab-content">
        <div class="sffc-admin-section">
            <h2>Available Locations</h2>
            <p>These are all the countries and cities available for relocation pages.</p>

            <div class="sffc-locations-grid">
                <?php foreach ($all_locations as $country_slug => $country): ?>
                    <div class="sffc-location-card">
                        <h3>
                            <?php echo esc_html($country['name']); ?>
                            <span class="sffc-country-code"><?php echo esc_html($country['code']); ?></span>
                        </h3>
                        <div class="sffc-location-meta">
                            <span>Currency: <?php echo esc_html($country['currency']); ?></span>
                            <?php if (!empty($country['is_city_state'])): ?>
                                <span class="sffc-badge sffc-badge-info">City-State</span>
                            <?php endif; ?>
                        </div>
                        <div class="sffc-cities-list">
                            <strong>Top Cities:</strong>
                            <ul>
                                <?php foreach ($country['cities'] as $city_slug => $city): ?>
                                    <li>
                                        <?php echo esc_html($city['name']); ?>
                                        <?php if (!empty($city['is_primary'])): ?>
                                            <span class="sffc-badge sffc-badge-primary">Primary</span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Settings Tab -->
    <div id="tab-settings" class="sffc-tab-content">
        <div class="sffc-admin-section">
            <h2>Scheduler Settings</h2>
            <form method="post" action="options.php">
                <?php settings_fields('sffc_relocation_settings'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="sffc_relocation_daily_limit">Daily Generation Limit</label>
                        </th>
                        <td>
                            <input type="number" id="sffc_relocation_daily_limit" name="sffc_relocation_daily_limit"
                                   value="<?php echo esc_attr(get_option('sffc_relocation_daily_limit', 100)); ?>"
                                   min="1" max="500" class="small-text">
                            <p class="description">Maximum pages to generate per day. Recommended: 100</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sffc_relocation_scheduler_enabled">Enable Scheduler</label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="sffc_relocation_scheduler_enabled" name="sffc_relocation_scheduler_enabled"
                                       value="1" <?php checked(get_option('sffc_relocation_scheduler_enabled', true)); ?>>
                                Automatically generate pages on schedule
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sffc_relocation_api_delay">API Delay (seconds)</label>
                        </th>
                        <td>
                            <input type="number" id="sffc_relocation_api_delay" name="sffc_relocation_api_delay"
                                   value="<?php echo esc_attr(get_option('sffc_relocation_api_delay', 5)); ?>"
                                   min="0" max="60" class="small-text">
                            <p class="description">Seconds to wait between Claude API calls. Prevents rate limiting.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sffc_relocation_test_mode">Test Mode</label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="sffc_relocation_test_mode" name="sffc_relocation_test_mode"
                                       value="1" <?php checked(get_option('sffc_relocation_test_mode', false)); ?>>
                                Use placeholder content instead of Claude API
                            </label>
                            <p class="description">Enable this to test page generation without using API credits. Pages will be created with placeholder content.</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Save Settings'); ?>
            </form>
        </div>

        <div class="sffc-admin-section">
            <h2>Shortcode Template Pages</h2>
            <p>Create WordPress pages with these shortcodes for Elementor compatibility:</p>

            <table class="form-table">
                <tr>
                    <th scope="row">Single Relocation Page</th>
                    <td>
                        <code>[sffc_relocation_page]</code>
                        <p class="description">Create a page with this shortcode. Set its ID in the option below.</p>
                        <input type="number" name="sffc_relocation_template_page"
                               value="<?php echo esc_attr(get_option('sffc_relocation_template_page', '')); ?>"
                               class="small-text" placeholder="Page ID">
                    </td>
                </tr>
                <tr>
                    <th scope="row">Archive/Landing Page</th>
                    <td>
                        <code>[sffc_relocation_archive]</code>
                        <p class="description">Create a page for /relocating/ landing. Set its ID below.</p>
                        <input type="number" name="sffc_relocation_archive_page"
                               value="<?php echo esc_attr(get_option('sffc_relocation_archive_page', '')); ?>"
                               class="small-text" placeholder="Page ID">
                    </td>
                </tr>
            </table>

            <h3>Available Shortcodes</h3>
            <ul style="list-style: disc; margin-left: 2rem;">
                <li><code>[sffc_relocation_page]</code> - Full relocation comparison page</li>
                <li><code>[sffc_relocation_archive]</code> - Landing page with route finder</li>
                <li><code>[sffc_relocation_comparison from="london" to="dubai"]</code> - Comparison dashboard only</li>
                <li><code>[sffc_relocation_route_finder]</code> - Route finder form</li>
                <li><code>[sffc_relocation_popular_routes limit="12"]</code> - Popular routes grid</li>
                <li><code>[sffc_relocation_regions]</code> - Destinations by region</li>
                <li><code>[sffc_relocation_jobs location="dubai" limit="6"]</code> - Jobs listing</li>
            </ul>
        </div>
    </div>
</div>

<style>
.sffc-admin-relocation {
    max-width: 1200px;
}

.sffc-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    margin: 1.5rem 0;
}

.sffc-stat-card {
    background: white;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 1rem;
    text-align: center;
}

.sffc-stat-number {
    display: block;
    font-size: 2rem;
    font-weight: 600;
    color: #1d2327;
}

.sffc-stat-label {
    display: block;
    font-size: 0.8125rem;
    color: #646970;
    margin-top: 0.25rem;
}

.sffc-admin-section {
    background: white;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 1.5rem;
    margin: 1.5rem 0;
}

.sffc-admin-section h2 {
    margin-top: 0;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #c3c4c7;
}

.sffc-generate-form {
    margin-top: 1rem;
}

.sffc-form-row {
    display: flex;
    align-items: flex-end;
    gap: 1rem;
    margin-bottom: 1rem;
}

.sffc-form-group {
    flex: 1;
}

.sffc-form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.sffc-form-group select {
    width: 100%;
    padding: 0.5rem;
}

.sffc-form-arrow {
    font-size: 1.5rem;
    color: #646970;
    padding-bottom: 0.5rem;
}

.sffc-loading {
    margin-left: 1rem;
    color: #646970;
    font-style: italic;
}

.sffc-badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 3px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.sffc-badge-city {
    background: #e7f5ff;
    color: #1971c2;
}

.sffc-badge-country {
    background: #fff3bf;
    color: #e67700;
}

.sffc-badge-success {
    background: #d3f9d8;
    color: #2b8a3e;
}

.sffc-badge-pending {
    background: #fff4e6;
    color: #e8590c;
}

.sffc-badge-info {
    background: #e7f5ff;
    color: #1971c2;
}

.sffc-badge-primary {
    background: #e5dbff;
    color: #7048e8;
}

.sffc-url {
    font-size: 0.8125rem;
    color: #646970;
}

.sffc-tab-content {
    display: none;
}

.sffc-tab-content.active {
    display: block;
}

.sffc-locations-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1rem;
}

.sffc-location-card {
    background: #f6f7f7;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 1rem;
}

.sffc-location-card h3 {
    margin: 0 0 0.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.sffc-country-code {
    font-size: 0.75rem;
    background: #dcdcde;
    padding: 0.25rem 0.5rem;
    border-radius: 3px;
    font-weight: normal;
}

.sffc-location-meta {
    font-size: 0.8125rem;
    color: #646970;
    margin-bottom: 0.75rem;
}

.sffc-location-meta span {
    margin-right: 0.75rem;
}

.sffc-cities-list {
    font-size: 0.875rem;
}

.sffc-cities-list ul {
    margin: 0.5rem 0 0 1rem;
    padding: 0;
}

.sffc-cities-list li {
    margin-bottom: 0.25rem;
}

#sffc-generate-result,
#sffc-bulk-result {
    margin-top: 1rem;
    padding: 1rem;
    border-radius: 4px;
}

.sffc-result-success {
    background: #d3f9d8;
    border: 1px solid #69db7c;
}

.sffc-result-error {
    background: #ffe3e3;
    border: 1px solid #ff8787;
}

/* Scheduler styles */
.sffc-scheduler-status {
    margin-bottom: 1.5rem;
}

.sffc-status-indicator {
    display: inline-block;
    padding: 0.5rem 1rem;
    border-radius: 4px;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.875rem;
}

.sffc-status-active {
    background: #d3f9d8;
    color: #2b8a3e;
}

.sffc-status-paused {
    background: #fff4e6;
    color: #e8590c;
}

.sffc-stats-grid-compact {
    margin: 1rem 0;
}

.sffc-stats-grid-compact .sffc-stat-card {
    padding: 0.75rem;
}

.sffc-stats-grid-compact .sffc-stat-number {
    font-size: 1.5rem;
}

.sffc-scheduler-actions {
    margin-top: 1.5rem;
    display: flex;
    gap: 0.5rem;
}

.sffc-notice {
    padding: 0.75rem 1rem;
    border-radius: 4px;
    margin: 1rem 0;
}

.sffc-notice-success {
    background: #d3f9d8;
    color: #2b8a3e;
}

.sffc-notice-info {
    background: #e7f5ff;
    color: #1971c2;
}

.sffc-log-container {
    max-height: 400px;
    overflow-y: auto;
}

.sffc-log-container table {
    font-size: 0.8125rem;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Tab navigation
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        var tab = $(this).data('tab');

        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        $('.sffc-tab-content').removeClass('active');
        $('#tab-' + tab).addClass('active');
    });

    // Single page generation
    $('#sffc-generate-single').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $loading = $form.find('.sffc-loading');
        var $result = $('#sffc-generate-result');
        var $btn = $form.find('button[type="submit"]');

        $btn.prop('disabled', true);
        $loading.show();
        $result.hide();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sffc_generate_location_brief',
                nonce: $('#sffc_nonce').val(),
                from_location: $('#from_location').val(),
                to_location: $('#to_location').val()
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<p><strong>Page created successfully!</strong></p>' +
                        '<p><a href="' + response.data.view_url + '" target="_blank">View Page</a> | ' +
                        '<a href="' + response.data.edit_url + '">Edit Page</a></p>')
                        .removeClass('sffc-result-error')
                        .addClass('sffc-result-success')
                        .show();
                } else {
                    $result.html('<p><strong>Error:</strong> ' + response.data + '</p>')
                        .removeClass('sffc-result-success')
                        .addClass('sffc-result-error')
                        .show();
                }
            },
            error: function() {
                $result.html('<p><strong>Error:</strong> Request failed. Please try again.</p>')
                    .removeClass('sffc-result-success')
                    .addClass('sffc-result-error')
                    .show();
            },
            complete: function() {
                $btn.prop('disabled', false);
                $loading.hide();
            }
        });
    });

    // Bulk generation
    $('#sffc-generate-bulk').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $loading = $form.find('.sffc-loading');
        var $result = $('#sffc-bulk-result');
        var $btn = $form.find('button[type="submit"]');

        $btn.prop('disabled', true);
        $loading.show();
        $result.hide();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sffc_bulk_generate_pages',
                nonce: $('#sffc_bulk_nonce').val(),
                type: $('#bulk_type').val(),
                limit: $('#bulk_limit').val()
            },
            success: function(response) {
                if (response.success) {
                    var results = response.data;
                    var successCount = results.filter(function(r) { return r.success; }).length;
                    var html = '<p><strong>Bulk generation complete!</strong></p>' +
                        '<p>' + successCount + ' of ' + results.length + ' pages created successfully.</p>';

                    if (successCount < results.length) {
                        html += '<details><summary>View errors</summary><ul>';
                        results.forEach(function(r) {
                            if (!r.success) {
                                html += '<li>' + r.from + ' to ' + r.to + ': ' + r.error + '</li>';
                            }
                        });
                        html += '</ul></details>';
                    }

                    $result.html(html)
                        .removeClass('sffc-result-error')
                        .addClass('sffc-result-success')
                        .show();
                } else {
                    $result.html('<p><strong>Error:</strong> ' + response.data + '</p>')
                        .removeClass('sffc-result-success')
                        .addClass('sffc-result-error')
                        .show();
                }
            },
            error: function() {
                $result.html('<p><strong>Error:</strong> Request failed. Please try again.</p>')
                    .removeClass('sffc-result-success')
                    .addClass('sffc-result-error')
                    .show();
            },
            complete: function() {
                $btn.prop('disabled', false);
                $loading.hide();
            }
        });
    });

    // Quick generate buttons
    $('.sffc-quick-generate').on('click', function() {
        var $btn = $(this);
        var from = $btn.data('from');
        var to = $btn.data('to');

        $btn.prop('disabled', true).text('Generating...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sffc_generate_location_brief',
                nonce: $('#sffc_nonce').val(),
                from_location: from,
                to_location: to
            },
            success: function(response) {
                if (response.success) {
                    $btn.replaceWith('<a href="' + response.data.view_url + '" target="_blank">View</a>');
                    $btn.closest('tr').find('.sffc-badge-pending')
                        .removeClass('sffc-badge-pending')
                        .addClass('sffc-badge-success')
                        .text('Created');
                } else {
                    $btn.prop('disabled', false).text('Retry');
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                $btn.prop('disabled', false).text('Retry');
                alert('Request failed. Please try again.');
            }
        });
    });

    // Scheduler: Trigger generation now
    $('#sffc-trigger-generation').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Running...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sffc_trigger_generation',
                nonce: $('#sffc_scheduler_nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    alert('Generation run completed! ' + response.data.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Request failed. Please try again.');
            },
            complete: function() {
                $btn.prop('disabled', false).text('Run Generation Now');
            }
        });
    });

    // Scheduler: Pause/Resume
    $('#sffc-pause-scheduler').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sffc_pause_scheduler',
                nonce: $('#sffc_scheduler_nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Request failed. Please try again.');
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });

    // Scheduler: Reset queue
    $('#sffc-reset-queue').on('click', function() {
        if (!confirm('This will rebuild the generation queue. Continue?')) {
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text('Rebuilding...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sffc_reset_queue',
                nonce: $('#sffc_scheduler_nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Request failed. Please try again.');
            },
            complete: function() {
                $btn.prop('disabled', false).text('Rebuild Queue');
            }
        });
    });
});
</script>
