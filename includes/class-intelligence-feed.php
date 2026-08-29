<?php
/**
 * Intelligence Feed – multi-lane editorial board
 *
 * Provides a Feedly/Bloomberg style experience with analytics rails
 * that reuse existing MENA Careers PE + news data while staying compliant
 * with Google News markup expectations.
 *
 * @package SennaCareers
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Intelligence_Feed
{
    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Lane configuration cache
     */
    private $lanes = array();

    /**
     * Activity cache per lane
     */
    private $lane_activity = array();

    /**
     * Sector heat cache per lane
     */
    private $lane_sector_tally = array();

    /**
     * Cached company profiles to avoid repeated lookups
     */
    private $company_profile_cache = array();

    /**
     * Get singleton
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->lanes = array(
            'private_equity' => array(
                'label' => __('Private Equity', 'senna-finance'),
                'post_types' => array('sffc_pe_news', 'sffc_pe_deal'),
                'description' => __('Deals, portfolio moves, secondaries', 'senna-finance')
            ),
            'asset_management' => array(
                'label' => __('Asset Management', 'senna-finance'),
                'post_types' => array('sffc_pe_news', 'sffc_news_article'),
                'description' => __('Flows, fund launches, strategy pivots', 'senna-finance')
            ),
            'investment_banking' => array(
                'label' => __('Investment Banking', 'senna-finance'),
                'post_types' => array('sffc_pe_news', 'sffc_deal'),
                'description' => __('Mandates, league tables, ECM/DCM', 'senna-finance')
            )
        );

        add_shortcode('sffc_intelligence_feed', array($this, 'render_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'maybe_enqueue_assets'));
    }

    /**
     * Conditionally enqueue assets
     */
    public function maybe_enqueue_assets()
    {
        if (is_admin()) {
            return;
        }

        $should_enqueue = false;
        global $post;

        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'sffc_intelligence_feed')) {
            $should_enqueue = true;
        }

        if ($should_enqueue) {
            wp_enqueue_style(
                'sffc-intelligence-feed',
                SFFC_PLUGIN_URL . 'assets/css/intelligence-feed.css',
                array(),
                SFFC_VERSION
            );
        }
    }

    /**
     * Shortcode renderer
     */
    public function render_shortcode($atts = array())
    {
        $atts = shortcode_atts(array(
            'lane' => '',
            'posts_per_lane' => 2,
            'min_value' => '$500M',
            'regions' => 'EU, UK, US'
        ), $atts, 'sffc_intelligence_feed');

        $active_lane = $this->sanitize_lane(isset($_GET['lane']) ? wp_unslash($_GET['lane']) : $atts['lane']);
        $per_lane = max(1, intval($atts['posts_per_lane']));

        wp_enqueue_style(
            'sffc-intelligence-feed',
            SFFC_PLUGIN_URL . 'assets/css/intelligence-feed.css',
            array(),
            SFFC_VERSION
        );

        $lanes_to_render = empty($active_lane) ? $this->lanes : array($active_lane => $this->lanes[$active_lane]);
        $lane_posts = array();

        foreach ($lanes_to_render as $lane_key => $lane_config) {
            $lane_posts[$lane_key] = $this->query_lane_posts($lane_key, $lane_config, $per_lane);
        }

        $board_stats = $this->build_board_stats($active_lane);

        ob_start();
        $this->render_board($lane_posts, $active_lane, $board_stats, $atts);
        return ob_get_clean();
    }

    /**
     * Render board markup
     */
    private function render_board($lane_posts, $active_lane, $board_stats, $atts)
    {
        $filter_url_all = remove_query_arg('lane');
        ?>
        <section class="sffc-intel-board-wrapper" aria-label="MENA Careers Finance Intelligence Feed">
            <div class="sffc-intel-header">
                <h2><?php esc_html_e('MENA Careers Intelligence Desk', 'senna-finance'); ?></h2>
                <div class="sffc-intel-lane-switch" role="tablist">
                    <a class="sffc-intel-lane-btn <?php echo empty($active_lane) ? 'is-active' : ''; ?>" href="<?php echo esc_url($filter_url_all); ?>" role="tab" aria-selected="<?php echo empty($active_lane) ? 'true' : 'false'; ?>"><?php esc_html_e('All Lanes', 'senna-finance'); ?></a>
                    <?php foreach ($this->lanes as $lane_key => $lane_config):
                        $url = add_query_arg('lane', $lane_key);
                        $is_active = ($lane_key === $active_lane);
                        ?>
                        <a class="sffc-intel-lane-btn <?php echo $is_active ? 'is-active' : ''; ?>" href="<?php echo esc_url($url); ?>" role="tab" aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>">
                            <?php echo esc_html($lane_config['label']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="sffc-intel-board">
                <div class="sffc-intel-feed">
                    <?php
                    $has_items = false;
                    foreach ($lane_posts as $lane_key => $posts) {
                        foreach ($posts as $item) {
                            $has_items = true;
                            $this->render_card($item);
                        }
                    }

                    if (!$has_items) {
                        echo '<p>' . esc_html__('No recent intelligence items found for this lane.', 'senna-finance') . '</p>';
                    }
                    ?>
                </div>

                <aside class="sffc-intel-sidebar" aria-label="Board insights">
                    <?php $this->render_sidebar_filters($active_lane, $atts); ?>
                    <?php $this->render_sidebar_activity($board_stats); ?>
                    <?php $this->render_sidebar_watchlist($board_stats); ?>
                </aside>
            </div>
        </section>
        <?php
    }

    /**
     * Render individual card
     */
    private function render_card($item)
    {
        ?>
        <article class="sffc-intel-card" itemscope itemtype="https://schema.org/NewsArticle">
            <meta itemprop="mainEntityOfPage" content="<?php echo esc_url($item['permalink']); ?>">
            <main>
                <div class="sffc-intel-meta">
                    <span class="sffc-intel-badge"><?php echo esc_html($item['type_label']); ?></span>
                    <span><?php echo esc_html($item['lane_label']); ?></span>
                    <?php if ($item['source']): ?>
                        <span><?php echo esc_html($item['source']); ?></span>
                    <?php endif; ?>
                    <time datetime="<?php echo esc_attr($item['time_iso']); ?>" itemprop="datePublished"><?php echo esc_html($item['time_label']); ?></time>
                </div>
                <h3 itemprop="headline"><?php echo esc_html($item['title']); ?></h3>
                <p class="sffc-intel-summary" itemprop="description"><?php echo esc_html($item['summary']); ?></p>
                <div class="sffc-intel-actions">
                    <a class="sffc-intel-btn primary" itemprop="url" href="<?php echo esc_url($item['permalink']); ?>" rel="bookmark">
                        <?php esc_html_e('Read Insight', 'senna-finance'); ?>
                    </a>
                    <?php if (!empty($item['actions'])):
                        foreach ($item['actions'] as $action): ?>
                            <a class="sffc-intel-btn" href="<?php echo esc_url($action['url']); ?>" target="_blank" rel="noopener noreferrer">
                                <?php echo esc_html($action['label']); ?>
                            </a>
                        <?php endforeach;
                    endif; ?>
                </div>
            </main>
            <aside class="sffc-intel-rail" aria-label="Analytics rail">
                <div class="sffc-intel-rail-card">
                    <h4><?php esc_html_e('Sentiment Pulse', 'senna-finance'); ?></h4>
                    <div class="sffc-intel-sentiment">
                        <strong><?php echo esc_html($item['sentiment']['score']); ?></strong>
                        <span><?php echo esc_html($item['sentiment']['label']); ?></span>
                    </div>
                    <div class="sffc-intel-stat-grid">
                        <div>
                            <span><?php esc_html_e('Velocity (7d)', 'senna-finance'); ?></span>
                            <strong><?php echo esc_html($item['velocity']['value']); ?></strong>
                        </div>
                        <div>
                            <span><?php esc_html_e('Change', 'senna-finance'); ?></span>
                            <strong><?php echo esc_html($item['velocity']['change']); ?></strong>
                        </div>
                    </div>
                </div>

                <div class="sffc-intel-rail-card">
                    <h4><?php esc_html_e('Snapshot', 'senna-finance'); ?></h4>
                    <table class="sffc-intel-table">
                        <?php foreach ($item['snapshot'] as $row): ?>
                            <tr>
                                <td><?php echo esc_html($row['label']); ?></td>
                                <td><?php echo esc_html($row['value']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                    <?php if ($item['alert']): ?>
                        <span class="sffc-intel-chip warning"><?php echo esc_html($item['alert']); ?></span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($item['comparables'])): ?>
                    <div class="sffc-intel-rail-card">
                        <h4><?php esc_html_e('Comparables', 'senna-finance'); ?></h4>
                        <div class="sffc-intel-bars">
                            <?php foreach ($item['comparables'] as $comp): ?>
                                <div>
                                    <div class="sffc-intel-bar-label">
                                        <span><?php echo esc_html($comp['label']); ?></span>
                                        <span><?php echo esc_html($comp['value']); ?></span>
                                    </div>
                                    <div class="sffc-intel-bar-track">
                                        <div class="sffc-intel-bar-fill" style="width: <?php echo esc_attr($comp['ratio']); ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($item['heat'])): ?>
                    <div class="sffc-intel-rail-card">
                        <h4><?php esc_html_e('Sector Heat', 'senna-finance'); ?></h4>
                        <div class="sffc-intel-heat-grid">
                            <?php foreach ($item['heat'] as $cell): ?>
                                <div class="sffc-intel-heat-cell">
                                    <strong><?php echo esc_html($cell['count']); ?></strong>
                                    <span><?php echo esc_html($cell['label']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php $this->render_lane_modules($item); ?>
            </aside>
        </article>
        <?php
    }

    /**
     * Sidebar filter summary
     */
    private function render_sidebar_filters($active_lane, $atts)
    {
        $lane_label = empty($active_lane) ? __('All Lanes', 'senna-finance') : $this->lanes[$active_lane]['label'];
        ?>
        <div class="sffc-intel-sidebar-card">
            <h4><?php esc_html_e('Board Filters', 'senna-finance'); ?></h4>
            <table class="sffc-intel-table">
                <tr><td><?php esc_html_e('Lane', 'senna-finance'); ?></td><td><?php echo esc_html($lane_label); ?></td></tr>
                <tr><td><?php esc_html_e('Deal Size', 'senna-finance'); ?></td><td><?php echo esc_html($atts['min_value']); ?></td></tr>
                <tr><td><?php esc_html_e('Regions', 'senna-finance'); ?></td><td><?php echo esc_html($atts['regions']); ?></td></tr>
            </table>
        </div>
        <?php
    }

    /**
     * Sidebar activity timeline
     */
    private function render_sidebar_activity($board_stats)
    {
        ?>
        <div class="sffc-intel-sidebar-card">
            <h4><?php esc_html_e('Activity Timeline', 'senna-finance'); ?></h4>
            <div class="sffc-intel-timeline">
                <?php foreach ($board_stats['timeline'] as $bucket): ?>
                    <span>
                        <strong><?php echo esc_html($bucket['value']); ?></strong>
                        <?php echo esc_html($bucket['label']); ?>
                    </span>
                <?php endforeach; ?>
            </div>
            <p class="sffc-intel-summary">
                <?php
                printf(
                    esc_html__('%d new items in the last 24h · %s is the busiest lane.', 'senna-finance'),
                    intval($board_stats['last_24h']),
                    esc_html($board_stats['busiest_lane'])
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Sidebar watchlist summary
     */
    private function render_sidebar_watchlist($board_stats)
    {
        ?>
        <div class="sffc-intel-sidebar-card">
            <h4><?php esc_html_e('Lane Overview', 'senna-finance'); ?></h4>
            <table class="sffc-intel-table">
                <?php foreach ($board_stats['lanes'] as $lane_key => $stats): ?>
                    <tr>
                        <td><?php echo esc_html($this->lanes[$lane_key]['label']); ?></td>
                        <td><?php printf(esc_html__('%d this week', 'senna-finance'), intval($stats['weekly_total'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php
    }

    /**
     * Build activity stats + timeline
     */
    private function build_board_stats($active_lane)
    {
        $stats = array('lanes' => array(), 'timeline' => array(), 'last_24h' => 0, 'busiest_lane' => __('None', 'senna-finance'));
        $lane_keys = empty($active_lane) ? array_keys($this->lanes) : array($active_lane);

        foreach ($lane_keys as $lane_key) {
            $lane_stats = $this->get_lane_activity($lane_key);
            $stats['lanes'][$lane_key] = $lane_stats;
            $stats['last_24h'] += $lane_stats['daily_total'];
            if (!isset($stats['lanes'][$stats['busiest_lane_key'] ?? '']) || $lane_stats['weekly_total'] > $stats['lanes'][$stats['busiest_lane_key'] ?? $lane_key]['weekly_total']) {
                $stats['busiest_lane_key'] = $lane_key;
            }
        }

        $stats['busiest_lane'] = isset($stats['busiest_lane_key']) ? $this->lanes[$stats['busiest_lane_key']]['label'] : __('None', 'senna-finance');
        $stats['timeline'] = $this->build_timeline($lane_keys);

        return $stats;
    }

    /**
     * Render timeline buckets
     */
    private function build_timeline($lane_keys)
    {
        $aggregate = array();
        foreach ($lane_keys as $lane_key) {
            $counts = $this->fetch_grouped_counts($this->lanes[$lane_key]['post_types'], 7);
            foreach ($counts as $day => $total) {
                $aggregate[$day] = ($aggregate[$day] ?? 0) + $total;
            }
        }

        $timeline = array();
        for ($i = 4; $i >= 0; $i--) {
            $date = date('Y-m-d', current_time('timestamp') - ($i * DAY_IN_SECONDS));
            $timeline[] = array(
                'label' => date_i18n('D', strtotime($date)),
                'value' => intval($aggregate[$date] ?? 0)
            );
        }

        return $timeline;
    }

    /**
     * Query posts for a lane
     */
    private function query_lane_posts($lane_key, $lane_config, $limit)
    {
        $args = array(
            'post_type' => $lane_config['post_types'],
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
        );

        $query = new WP_Query($args);
        $items = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $items[] = $this->format_post(get_post(), $lane_key);
            }
            wp_reset_postdata();
        }

        return $items;
    }

    /**
     * Build normalized payload for a post
     */
    private function format_post($post, $lane_key)
    {
        $post_id = $post->ID;
        $source = $this->get_meta_first($post_id, array('_source_name', 'news_source', '_news_source'));
        $published = $this->get_meta_first($post_id, array('_published_date'));
        $time_iso = $published ? mysql2date('c', $published) : get_post_time('c', true, $post);
        $time_label = sprintf(__('Published %s ago', 'senna-finance'), human_time_diff(get_post_time('U', true, $post), current_time('timestamp')));
        $summary = $post->post_excerpt ? wp_strip_all_tags($post->post_excerpt) : wp_strip_all_tags(wp_trim_words($post->post_content, 34));
        $companies = $this->extract_companies($post_id);
        $sector = $this->get_meta_first($post_id, array('_sector', 'news_sector', 'company_sector'));
        if ($sector) {
            $this->lane_sector_tally[$lane_key][$sector] = ($this->lane_sector_tally[$lane_key][$sector] ?? 0) + 1;
        }

        $item = array(
            'id' => $post_id,
            'title' => get_the_title($post),
            'summary' => $summary,
            'permalink' => get_permalink($post),
            'source' => $source,
            'time_iso' => $time_iso,
            'time_label' => $time_label,
            'lane_key' => $lane_key,
            'lane_label' => $this->lanes[$lane_key]['label'],
            'post_type' => get_post_type($post),
            'companies' => $companies,
            'type_label' => $this->determine_type_label($post),
            'sentiment' => $this->build_sentiment_payload($post, $companies),
            'velocity' => $this->build_velocity_payload($lane_key),
            'snapshot' => $this->build_snapshot_rows($post, $sector, $companies),
            'alert' => $this->build_alert_flag($post),
            'comparables' => $this->build_comparable_rows($post),
            'heat' => $this->build_heat_cells($lane_key),
            'actions' => $this->build_actions($post, $companies),
            'lane_modules' => $this->build_lane_modules($post, $lane_key, $companies)
        );

        return $item;
    }

    /**
     * Determine display type label
     */
    private function determine_type_label($post)
    {
        $post_type = get_post_type($post);
        switch ($post_type) {
            case 'sffc_pe_deal':
                return __('Deal Brief', 'senna-finance');
            case 'sffc_deal':
                return __('Mandate Update', 'senna-finance');
            case 'sffc_news_article':
                return __('Market Note', 'senna-finance');
            default:
                return __('News', 'senna-finance');
        }
    }

    /**
     * Build action buttons
     */
    private function build_actions($post, $companies)
    {
        $actions = array();
        if (!empty($companies)) {
            $company = reset($companies);
            $actions[] = array(
                'label' => sprintf(__('Track %s', 'senna-finance'), $company),
                'url' => add_query_arg(array('company' => rawurlencode($company)), get_permalink($post))
            );
        }

        $source_url = $this->get_meta_first($post->ID, array('_source_url'));
        if ($source_url) {
            $actions[] = array(
                'label' => __('View Source', 'senna-finance'),
                'url' => $source_url
            );
        }

        return $actions;
    }

    /**
     * Prepare lane-specific module data
     */
    private function build_lane_modules($post, $lane_key, $companies)
    {
        $modules = array();

        if ('asset_management' === $lane_key) {
            $data = $this->collect_asset_management_lane($post, $companies);
            if ($data) {
                $modules[] = array('type' => 'asset_management', 'data' => $data);
            }
        }

        if ('investment_banking' === $lane_key) {
            $data = $this->collect_investment_banking_lane($post, $companies);
            if ($data) {
                $modules[] = array('type' => 'investment_banking', 'data' => $data);
            }
        }

        return $modules;
    }

    /**
     * Render lane-specific modules
     */
    private function render_lane_modules($item)
    {
        if (empty($item['lane_modules'])) {
            return;
        }

        foreach ($item['lane_modules'] as $module) {
            switch ($module['type']) {
                case 'asset_management':
                    $this->render_asset_management_cards($module['data']);
                    break;
                case 'investment_banking':
                    $this->render_investment_banking_cards($module['data']);
                    break;
            }
        }
    }

    /**
     * Gather data for asset management lane
     */
    private function collect_asset_management_lane($post, $companies)
    {
        $company = $companies[0] ?? '';
        if (empty($company)) {
            return null;
        }

        $profile = $this->get_company_profile_by_name($company);
        if (!$profile) {
            return null;
        }

        $series = $this->fetch_news_series($company, 5, 'fundraising');
        if (empty($series)) {
            $series = $this->fetch_news_series($company, 5);
        }

        $delta = 0;
        if (count($series) >= 2) {
            $latest = end($series);
            $prev = prev($series);
            $prev_val = $prev['value'] ?: 1;
            $delta = round((($latest['value'] - $prev_val) / $prev_val) * 100);
        }

        return array(
            'company' => $company,
            'aum' => $profile['aum_display'],
            'strategies' => $profile['sectors'],
            'regions' => $profile['regions'],
            'funds_active' => $profile['funds_active'],
            'series' => $series,
            'series_delta' => $delta
        );
    }

    private function render_asset_management_cards($data)
    {
        ?>
        <div class="sffc-intel-rail-card">
            <h4><?php esc_html_e('Fund Profile', 'senna-finance'); ?></h4>
            <table class="sffc-intel-table">
                <tr><td><?php esc_html_e('Lead firm', 'senna-finance'); ?></td><td><?php echo esc_html($data['company']); ?></td></tr>
                <tr><td><?php esc_html_e('AUM', 'senna-finance'); ?></td><td><?php echo esc_html($data['aum']); ?></td></tr>
                <tr><td><?php esc_html_e('Strategies', 'senna-finance'); ?></td><td><?php echo esc_html(!empty($data['strategies']) ? implode(' · ', array_slice($data['strategies'], 0, 2)) : __('Multi-asset', 'senna-finance')); ?></td></tr>
                <tr><td><?php esc_html_e('Regions', 'senna-finance'); ?></td><td><?php echo esc_html(!empty($data['regions']) ? implode(' · ', array_slice($data['regions'], 0, 2)) : __('Global', 'senna-finance')); ?></td></tr>
            </table>
            <?php if ($data['funds_active'] > 0): ?>
                <span class="sffc-intel-chip">
                    <?php printf(esc_html__('%d active fund%s', 'senna-finance'), intval($data['funds_active']), $data['funds_active'] > 1 ? 's' : ''); ?>
                </span>
            <?php endif; ?>
        </div>
        <?php if (!empty($data['series'])): ?>
            <div class="sffc-intel-rail-card">
                <h4><?php esc_html_e('Flow Signals', 'senna-finance'); ?></h4>
                <div class="sffc-intel-flow">
                    <?php
                    $max = max(array_column($data['series'], 'value')) ?: 1;
                    foreach ($data['series'] as $point):
                        $height = max(14, intval(($point['value'] / $max) * 60));
                        ?>
                        <div class="sffc-intel-flow-bar" style="height: <?php echo esc_attr($height); ?>px;" aria-label="<?php echo esc_attr($point['label'] . ': ' . $point['value']); ?>"></div>
                    <?php endforeach; ?>
                </div>
                <p class="sffc-intel-summary">
                    <?php
                    printf(
                        esc_html__('%s fundraising mentions · %s vs prior day', 'senna-finance'),
                        esc_html(end($data['series'])['value']),
                        ($data['series_delta'] >= 0 ? '+' : '') . $data['series_delta'] . '%'
                    );
                    ?>
                </p>
            </div>
        <?php endif; ?>
        <?php
    }

    /**
     * Gather data for investment banking lane
     */
    private function collect_investment_banking_lane($post, $companies)
    {
        $post_id = $post->ID;
        $deal_value_raw = $this->get_meta_first($post_id, array('_deal_value', 'deal_value'));
        $stage = $this->get_meta_first($post_id, array('_deal_stage', 'deal_stage')) ?: __('Mandate', 'senna-finance');
        $type = $this->get_meta_first($post_id, array('_deal_type', 'deal_type')) ?: __('Advisory', 'senna-finance');
        $bookrunner = $companies[0] ?? '';
        $league = $bookrunner ? $this->count_recent_deals_for_company($bookrunner, 90) : null;

        return array(
            'stage' => $stage,
            'type' => $type,
            'value_raw' => $deal_value_raw,
            'value_display' => $this->format_currency($deal_value_raw),
            'fee_pool' => $this->estimate_fee_pool($deal_value_raw),
            'bookrunner' => $bookrunner,
            'league' => $league,
            'confidence' => $this->map_stage_to_confidence($stage)
        );
    }

    private function render_investment_banking_cards($data)
    {
        ?>
        <div class="sffc-intel-rail-card">
            <h4><?php esc_html_e('Mandate Desk', 'senna-finance'); ?></h4>
            <table class="sffc-intel-table">
                <tr><td><?php esc_html_e('Stage', 'senna-finance'); ?></td><td><?php echo esc_html($data['stage']); ?></td></tr>
                <tr><td><?php esc_html_e('Type', 'senna-finance'); ?></td><td><?php echo esc_html($data['type']); ?></td></tr>
                <tr><td><?php esc_html_e('Deal value', 'senna-finance'); ?></td><td><?php echo esc_html($data['value_display']); ?></td></tr>
                <tr><td><?php esc_html_e('Est. fee pool', 'senna-finance'); ?></td><td><?php echo esc_html($data['fee_pool']); ?></td></tr>
            </table>
            <span class="sffc-intel-chip positive">
                <?php printf(esc_html__('Confidence: %s%%', 'senna-finance'), intval($data['confidence'])); ?>
            </span>
        </div>
        <?php if ($data['bookrunner']): ?>
            <div class="sffc-intel-rail-card">
                <h4><?php esc_html_e('League Momentum', 'senna-finance'); ?></h4>
                <p class="sffc-intel-summary">
                    <?php
                    if ($data['league']) {
                        printf(
                            esc_html__('%1$s on %2$d mandates in last 90d · %3$s aggregate value', 'senna-finance'),
                            esc_html($data['bookrunner']),
                            intval($data['league']['total']),
                            esc_html($data['league']['total_value'])
                        );
                    } else {
                        printf(esc_html__('%s activity will populate as mandates book', 'senna-finance'), esc_html($data['bookrunner']));
                    }
                    ?>
                </p>
            </div>
        <?php endif; ?>
        <?php
    }

    private function get_company_profile_by_name($name)
    {
        if (empty($name)) {
            return null;
        }

        $cache_key = md5(strtolower($name));
        if (array_key_exists($cache_key, $this->company_profile_cache)) {
            return $this->company_profile_cache[$cache_key];
        }

        $company = get_page_by_title($name, OBJECT, 'sffc_company');

        if (!$company && class_exists('SFFC_Company_Title_Helper')) {
            $seo_title = SFFC_Company_Title_Helper::build_seo_title($name);
            $company = get_page_by_title($seo_title, OBJECT, 'sffc_company');
        }

        if (!$company) {
            $this->company_profile_cache[$cache_key] = null;
            return null;
        }

        $profile = array(
            'id' => $company->ID,
            'aum_display' => $this->format_currency($this->parse_numeric_value(get_post_meta($company->ID, '_sffc_aum', true)) ?: get_post_meta($company->ID, '_sffc_aum', true)),
            'regions' => $this->parse_meta_list(get_post_meta($company->ID, '_sffc_regions', true)),
            'sectors' => $this->parse_meta_list(get_post_meta($company->ID, '_sffc_sectors', true)),
            'funds_active' => $this->count_active_funds($company->ID)
        );

        $this->company_profile_cache[$cache_key] = $profile;
        return $profile;
    }

    private function parse_meta_list($value)
    {
        if (empty($value)) {
            return array();
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $value = $decoded;
            } else {
                $value = explode(',', $value);
            }
        }

        if (!is_array($value)) {
            return array();
        }

        return array_values(array_filter(array_map('trim', $value)));
    }

    private function count_active_funds($company_id)
    {
        $funds = get_post_meta($company_id, '_sffc_active_funds', true);
        if (empty($funds)) {
            return 0;
        }

        if (is_string($funds)) {
            $decoded = json_decode($funds, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $funds = $decoded;
            } else {
                $funds = array_filter(array_map('trim', explode(',', $funds)));
            }
        }

        return is_array($funds) ? count($funds) : 0;
    }

    private function fetch_news_series($term, $days = 5, $category = '')
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sffc_news_cache';
        if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table))) {
            return array();
        }

        $sql = "SELECT DATE(published_date) as day, COUNT(*) as total
                FROM {$table}
                WHERE published_date >= DATE_SUB(%s, INTERVAL %d DAY)
                AND (headline LIKE %s OR summary LIKE %s)";
        $params = array(current_time('mysql'), intval($days), '%' . $wpdb->esc_like($term) . '%', '%' . $wpdb->esc_like($term) . '%');
        if (!empty($category)) {
            $sql .= ' AND category = %s';
            $params[] = $category;
        }
        $sql .= ' GROUP BY DATE(published_date)';

        $prepared = $wpdb->prepare($sql, $params);
        $rows = $wpdb->get_results($prepared);

        $counts = array();
        if ($rows) {
            foreach ($rows as $row) {
                $counts[$row->day] = intval($row->total);
            }
        }

        $series = array();
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', current_time('timestamp') - ($i * DAY_IN_SECONDS));
            $series[] = array(
                'label' => date_i18n('D', strtotime($date)),
                'value' => intval($counts[$date] ?? 0)
            );
        }

        return $series;
    }

    private function count_recent_deals_for_company($company, $days = 90)
    {
        if (empty($company)) {
            return null;
        }

        global $wpdb;
        $like = '%' . $wpdb->esc_like($company) . '%';
        $sql = $wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID) as total,
                    SUM(CASE WHEN pm_value.meta_value REGEXP '^[0-9]+$' THEN pm_value.meta_value ELSE 0 END) as total_value
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm_company
                ON pm_company.post_id = p.ID
                AND pm_company.meta_key IN ('_companies_involved', '_news_company', 'news_company', 'deal_company')
             LEFT JOIN {$wpdb->postmeta} pm_value
                ON pm_value.post_id = p.ID
                AND pm_value.meta_key IN ('_deal_value', 'deal_value')
             WHERE p.post_status = 'publish'
               AND p.post_type IN ('sffc_pe_deal', 'sffc_deal')
               AND p.post_date >= DATE_SUB(%s, INTERVAL %d DAY)
               AND (pm_company.meta_value LIKE %s OR p.post_title LIKE %s)",
            current_time('mysql'),
            intval($days),
            $like,
            $like
        );

        $row = $wpdb->get_row($sql);
        if (!$row) {
            return null;
        }

        $total_value = floatval($row->total_value ?: 0);
        return array(
            'total' => intval($row->total),
            'total_value' => $total_value > 0 ? $this->format_currency($total_value) : __('Undisclosed', 'senna-finance')
        );
    }

    private function estimate_fee_pool($value)
    {
        $numeric = $this->parse_numeric_value($value);
        if (!$numeric) {
            return __('n/a', 'senna-finance');
        }
        $fees = $numeric * 0.009; // 90 bps typical ECM/DCM fee pool estimate
        return $this->format_currency($fees);
    }

    private function parse_numeric_value($value)
    {
        if (is_numeric($value)) {
            return floatval($value);
        }

        if (!is_string($value)) {
            return null;
        }

        $clean = strtolower(str_replace(',', '', $value));
        $multiplier = 1;
        if (strpos($clean, 'b') !== false) {
            $multiplier = 1000000000;
        } elseif (strpos($clean, 'm') !== false) {
            $multiplier = 1000000;
        } elseif (strpos($clean, 'k') !== false) {
            $multiplier = 1000;
        }

        $number = floatval(preg_replace('/[^0-9.]/', '', $clean));
        if ($number <= 0) {
            return null;
        }

        return $number * $multiplier;
    }

    private function map_stage_to_confidence($stage)
    {
        $stage = strtolower($stage);
        if (strpos($stage, 'closed') !== false || strpos($stage, 'completed') !== false) {
            return 92;
        }
        if (strpos($stage, 'signed') !== false || strpos($stage, 'pricing') !== false) {
            return 84;
        }
        if (strpos($stage, 'filing') !== false || strpos($stage, 'marketing') !== false) {
            return 72;
        }
        return 60;
    }

    /**
     * Sentiment payload
     */
    private function build_sentiment_payload($post, $companies)
    {
        $post_id = $post->ID;
        $score = floatval($this->get_meta_first($post_id, array('_sentiment_score', 'sentiment_score')));
        $label = $this->get_meta_first($post_id, array('_sentiment', 'sentiment'));

        $news_signal = $this->fetch_related_news_sentiment($post, $companies);
        if ($news_signal) {
            $score = $news_signal['score'];
            $label = $news_signal['label'];
        }

        if (!$score) {
            $score = $this->map_sentiment_label_to_score($label ?: 'neutral');
        }

        if (empty($label)) {
            $label = $this->map_score_to_label($score);
        }

        return array(
            'score' => intval(round($score)),
            'label' => ucfirst($label)
        );
    }

    private function fetch_related_news_sentiment($post, $companies)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sffc_news_cache';
        if (!$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table))) {
            return null;
        }

        $term = '';
        if (!empty($companies)) {
            $term = $companies[0];
        } else {
            $term = wp_strip_all_tags(get_the_title($post));
        }

        $term = trim($term);
        if ('' === $term) {
            return null;
        }

        $like = '%' . $wpdb->esc_like($term) . '%';
        $sql = $wpdb->prepare(
            "SELECT AVG(sentiment_score) as avg_sentiment, COUNT(*) as mentions
             FROM {$table}
             WHERE published_date >= DATE_SUB(%s, INTERVAL 7 DAY)
             AND (headline LIKE %s OR summary LIKE %s)",
            current_time('mysql'),
            $like,
            $like
        );

        $row = $wpdb->get_row($sql);
        if ($row && $row->mentions > 0 && $row->avg_sentiment !== null) {
            // sentiment_score is stored on -1..1 scale; normalize to 0-100
            $normalized = intval(round((floatval($row->avg_sentiment) + 1) / 2 * 100));
            $label = $this->map_score_to_label($normalized);
            return array('score' => $normalized, 'label' => $label);
        }

        return null;
    }

    private function map_sentiment_label_to_score($label)
    {
        switch (strtolower($label)) {
            case 'positive':
                return 72;
            case 'negative':
                return 38;
            case 'very positive':
                return 85;
            case 'very negative':
                return 22;
            default:
                return 55;
        }
    }

    private function map_score_to_label($score)
    {
        // REVISED: Use neutral, factual labels instead of risk-based terminology
        // Previous labels like "risk-on" and "watchlist" created false impressions
        if ($score >= 70) {
            return __('Market Update', 'senna-finance');
        }
        if ($score <= 40) {
            return __('Industry News', 'senna-finance');
        }
        return __('News', 'senna-finance');
    }

    /**
     * Velocity data from lane activity
     */
    private function build_velocity_payload($lane_key)
    {
        $lane_stats = $this->get_lane_activity($lane_key);
        $change = $lane_stats['prev_day'] > 0 ? round((($lane_stats['daily_total'] - $lane_stats['prev_day']) / $lane_stats['prev_day']) * 100) : ($lane_stats['daily_total'] > 0 ? 100 : 0);
        $change_label = ($change >= 0 ? '+' : '') . $change . '%';

        return array(
            'value' => $lane_stats['weekly_total'],
            'change' => $change_label
        );
    }

    /**
     * Snapshot rows vary by post type
     */
    private function build_snapshot_rows($post, $sector, $companies)
    {
        $rows = array();
        $post_id = $post->ID;
        $post_type = get_post_type($post);

        if ('sffc_pe_deal' === $post_type || 'sffc_deal' === $post_type) {
            $rows[] = array('label' => __('Stage', 'senna-finance'), 'value' => $this->get_meta_first($post_id, array('_deal_stage', 'deal_stage')) ?: __('Signing', 'senna-finance'));
            $rows[] = array('label' => __('Value', 'senna-finance'), 'value' => $this->format_currency($this->get_meta_first($post_id, array('_deal_value', 'deal_value'))));
            $rows[] = array('label' => __('Multiple', 'senna-finance'), 'value' => $this->get_meta_first($post_id, array('_deal_multiple', 'deal_multiple')) ?: __('n/a', 'senna-finance'));
        } else {
            $rows[] = array('label' => __('Focus', 'senna-finance'), 'value' => $sector ?: __('Multi-sector', 'senna-finance'));
            $rows[] = array('label' => __('Lead firm', 'senna-finance'), 'value' => !empty($companies) ? implode(', ', array_slice($companies, 0, 2)) : __('Market composite', 'senna-finance'));
            $rows[] = array('label' => __('Category', 'senna-finance'), 'value' => $this->get_meta_first($post_id, array('_news_category', 'news_category')) ?: __('Market', 'senna-finance'));
        }

        $rows[] = array('label' => __('Region', 'senna-finance'), 'value' => $this->get_meta_first($post_id, array('_region', 'news_region')) ?: __('Global', 'senna-finance'));

        return $rows;
    }

    /**
     * Alert/flag text - REVISED to use factual, non-alarmist language
     */
    private function build_alert_flag($post)
    {
        // Only show flags for verified regulatory status, not speculation
        $deal_stage = $this->get_meta_first($post->ID, array('_deal_stage'));

        if (get_post_type($post) === 'sffc_pe_deal' && $deal_stage === 'Regulatory') {
            return __('Regulatory review pending', 'senna-finance');
        }

        // REMOVED: Generic risk flags that were often false positives
        // Only return empty string - we don't fabricate warnings
        return '';
    }

    /**
     * Build comparable rows for PE deals
     */
    private function build_comparable_rows($post)
    {
        if ('sffc_pe_deal' !== get_post_type($post)) {
            return array();
        }

        $sector = $this->get_meta_first($post->ID, array('_sector'));
        $args = array(
            'post_type' => 'sffc_pe_deal',
            'post_status' => 'publish',
            'posts_per_page' => 3,
            'post__not_in' => array($post->ID),
            'no_found_rows' => true,
        );

        if ($sector) {
            $args['meta_query'] = array(
                array(
                    'key' => '_sector',
                    'value' => $sector,
                    'compare' => 'LIKE'
                )
            );
        }

        $query = new WP_Query($args);
        $rows = array();
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $value = $this->format_currency(get_post_meta(get_the_ID(), '_deal_value', true));
                $multiple = get_post_meta(get_the_ID(), '_deal_multiple', true);
                $rows[] = array(
                    'label' => get_the_title(),
                    'value' => $multiple ? $multiple : $value,
                    'ratio' => min(100, max(40, intval($multiple ? floatval($multiple) * 6 : 70)))
                );
            }
            wp_reset_postdata();
        }

        return $rows;
    }

    /**
     * Heat cells from lane-level tallies
     */
    private function build_heat_cells($lane_key)
    {
        if (empty($this->lane_sector_tally[$lane_key])) {
            return array();
        }

        arsort($this->lane_sector_tally[$lane_key]);
        $top = array_slice($this->lane_sector_tally[$lane_key], 0, 6, true);
        $cells = array();
        foreach ($top as $label => $count) {
            $cells[] = array('label' => $label, 'count' => intval($count));
        }
        return $cells;
    }

    /**
     * Lane activity helper
     */
    private function get_lane_activity($lane_key)
    {
        if (isset($this->lane_activity[$lane_key])) {
            return $this->lane_activity[$lane_key];
        }

        $counts = $this->fetch_grouped_counts($this->lanes[$lane_key]['post_types'], 8);
        $today = date('Y-m-d', current_time('timestamp'));
        $yesterday = date('Y-m-d', current_time('timestamp') - DAY_IN_SECONDS);
        $week_total = array_sum(array_values($counts));

        $this->lane_activity[$lane_key] = array(
            'weekly_total' => $week_total,
            'daily_total' => intval($counts[$today] ?? 0),
            'prev_day' => intval($counts[$yesterday] ?? 0)
        );

        return $this->lane_activity[$lane_key];
    }

    /**
     * Fetch grouped counts for date buckets
     */
    private function fetch_grouped_counts($post_types, $days)
    {
        if (empty($post_types)) {
            return array();
        }

        global $wpdb;
        $placeholders = implode(', ', array_fill(0, count($post_types), '%s'));
        $query = "SELECT DATE(post_date) as day, COUNT(ID) as total
                  FROM {$wpdb->posts}
                  WHERE post_status = 'publish'
                  AND post_type IN ($placeholders)
                  AND post_date >= DATE_SUB(%s, INTERVAL %d DAY)
                  GROUP BY DATE(post_date)
                  ORDER BY day DESC";

        $params = array_merge(array($query), $post_types, array(current_time('mysql'), intval($days)));
        $prepared = call_user_func_array(array($wpdb, 'prepare'), $params);
        $rows = $wpdb->get_results($prepared);

        $counts = array();
        if ($rows) {
            foreach ($rows as $row) {
                $counts[$row->day] = intval($row->total);
            }
        }

        return $counts;
    }

    /**
     * Extract companies list
     */
    private function extract_companies($post_id)
    {
        $raw = $this->get_meta_first($post_id, array('_companies_involved', '_companies_mentioned', 'news_company'));
        if (empty($raw)) {
            return array();
        }

        if (is_string($raw) && $this->is_json($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_values(array_filter(array_map('trim', $decoded)));
            }
        }

        if (is_string($raw)) {
            return array_map('trim', explode(',', $raw));
        }

        return (array) $raw;
    }

    /**
     * Get first non-empty meta
     */
    private function get_meta_first($post_id, $keys)
    {
        foreach ($keys as $key) {
            $value = get_post_meta($post_id, $key, true);
            if (!empty($value)) {
                return is_string($value) ? trim($value) : $value;
            }
        }
        return '';
    }

    private function format_currency($value)
    {
        if (empty($value)) {
            return __('Undisclosed', 'senna-finance');
        }

        if (is_numeric($value)) {
            if ($value >= 1000000000) {
                return sprintf('$%sb', round($value / 1000000000, 1));
            }
            if ($value >= 1000000) {
                return sprintf('$%sm', round($value / 1000000, 1));
            }
            return '$' . number_format_i18n($value);
        }

        return $value;
    }

    private function sanitize_lane($lane)
    {
        $lane = sanitize_key($lane);
        return isset($this->lanes[$lane]) ? $lane : '';
    }

    private function is_json($string)
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}

SFFC_Intelligence_Feed::get_instance();
