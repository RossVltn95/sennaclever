<?php

/**
 * PE Content Manager
 * Manages custom post types for PE News and Deals from RSS feeds
 * 
 * @package SennaCareers
 * @since 10.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_PE_Content_Manager
{

    /**
     * Singleton instance
     */
    private static $instance = null;
    private $article_enhancer = null;
    private $dynamic_builder = null;
    private $headline_optimizer = null;
    private $auto_cleanup_enabled = false;

    /**
     * Get instance
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
        // Load new dynamic article builder (preferred)
        if (file_exists(plugin_dir_path(__FILE__) . 'class-dynamic-article-builder.php')) {
            require_once plugin_dir_path(__FILE__) . 'class-dynamic-article-builder.php';
            if (class_exists('SFFC_Dynamic_Article_Builder')) {
                $this->dynamic_builder = SFFC_Dynamic_Article_Builder::get_instance();
            }
        }

        // Legacy article enhancer (fallback)
        if (class_exists('SFFC_PE_Article_Enhancer')) {
            $this->article_enhancer = SFFC_PE_Article_Enhancer::get_instance();
        }

        // Load headline optimizer for unique, high-CTR titles
        if (file_exists(plugin_dir_path(__FILE__) . 'class-headline-optimizer.php')) {
            require_once plugin_dir_path(__FILE__) . 'class-headline-optimizer.php';
            if (class_exists('SFFC_Headline_Optimizer')) {
                $this->headline_optimizer = SFFC_Headline_Optimizer::get_instance();
            }
        }

        // Allow developers to override this, but default to disabled per request
        $this->auto_cleanup_enabled = (bool) apply_filters('sffc_pe_auto_cleanup_enabled', false);

        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        // Register custom post types with high priority to ensure they load early
        add_action('init', array($this, 'register_post_types'), 0);

        // Process feeds into custom posts
        add_action('sffc_process_pe_content', array($this, 'process_feed_content'));

        // Schedule hourly feed processing
        if (!wp_next_scheduled('sffc_process_pe_content')) {
            wp_schedule_event(time(), 'hourly', 'sffc_process_pe_content');
        }

        // Self-healing cron check - if last run was more than 2 hours ago, run now
        add_action('init', array($this, 'maybe_run_missed_cron'), 20);

        // Cleanup hook always available for manual runs
        add_action('sffc_cleanup_old_pe_content', array($this, 'cleanup_old_content'));

        if ($this->auto_cleanup_enabled) {
            // Schedule weekly cleanup of old content (Sundays at 3 AM)
            if (!wp_next_scheduled('sffc_cleanup_old_pe_content')) {
                $next_sunday = strtotime('next Sunday 03:00:00');
                wp_schedule_event($next_sunday, 'weekly', 'sffc_cleanup_old_pe_content');
            }
        } else {
            // Remove any previously scheduled automatic cleanup events
            wp_clear_scheduled_hook('sffc_cleanup_old_pe_content');
        }

        // AJAX for manual processing
        add_action('wp_ajax_sffc_process_pe_feeds_now', array($this, 'ajax_process_feeds'));

        // AJAX for manual cleanup
        add_action('wp_ajax_sffc_cleanup_pe_content', array($this, 'ajax_cleanup_content'));
    }

    /**
     * Register PE-specific custom post types
     */
    public function register_post_types()
    {
        // PE News post type
        register_post_type('sffc_pe_news', array(
            'labels' => array(
                'name' => 'PE News',
                'singular_name' => 'PE News Item',
                'add_new' => 'Add News',
                'add_new_item' => 'Add New PE News',
                'edit_item' => 'Edit PE News',
                'new_item' => 'New PE News',
                'view_item' => 'View PE News',
                'search_items' => 'Search PE News',
                'not_found' => 'No PE news found',
                'all_items' => 'All PE News',
                'menu_name' => 'PE News'
            ),
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => 'sffc-dashboard',  // Add to SF Finance menu
            'show_in_admin_bar' => true,
            'show_in_nav_menus' => true,
            'publicly_queryable' => true,
            'exclude_from_search' => false,
            'has_archive' => true,
            'hierarchical' => false,
            'rewrite' => array('slug' => 'pe-news'),
            'capability_type' => 'post',
            'supports' => array('title', 'editor', 'excerpt', 'custom-fields', 'thumbnail'),
            'menu_position' => 30,
            'menu_icon' => 'dashicons-analytics'
        ));

        // PE Deals post type
        register_post_type('sffc_pe_deal', array(
            'labels' => array(
                'name' => 'PE Deals',
                'singular_name' => 'PE Deal',
                'add_new' => 'Add Deal',
                'add_new_item' => 'Add New PE Deal',
                'edit_item' => 'Edit PE Deal',
                'new_item' => 'New PE Deal',
                'view_item' => 'View PE Deal',
                'search_items' => 'Search PE Deals',
                'not_found' => 'No PE deals found',
                'all_items' => 'All PE Deals',
                'menu_name' => 'PE Deals'
            ),
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => 'sffc-dashboard',  // Add to SF Finance menu
            'show_in_admin_bar' => true,
            'show_in_nav_menus' => true,
            'publicly_queryable' => true,
            'exclude_from_search' => false,
            'has_archive' => true,
            'hierarchical' => false,
            'rewrite' => array('slug' => 'pe-deals'),
            'capability_type' => 'post',
            'supports' => array('title', 'editor', 'excerpt', 'custom-fields', 'thumbnail'),
            'menu_position' => 31,
            'menu_icon' => 'dashicons-chart-area'
        ));

        // PE Signals post type (High-ranking potential content)
        register_post_type('sffc_pe_signal', array(
            'labels' => array(
                'name' => 'PE Signals',
                'singular_name' => 'PE Signal',
                'add_new' => 'Add Signal',
                'add_new_item' => 'Add New PE Signal',
                'edit_item' => 'Edit PE Signal',
                'new_item' => 'New PE Signal',
                'view_item' => 'View PE Signal',
                'search_items' => 'Search PE Signals',
                'not_found' => 'No PE signals found',
                'all_items' => 'All PE Signals',
                'menu_name' => 'PE Signals'
            ),
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => 'sffc-dashboard',
            'show_in_admin_bar' => true,
            'show_in_nav_menus' => true,
            'publicly_queryable' => true,
            'exclude_from_search' => false,
            'has_archive' => true,
            'hierarchical' => false,
            'rewrite' => array('slug' => 'pe-signals'),
            'capability_type' => 'post',
            'supports' => array('title', 'editor', 'excerpt', 'custom-fields', 'thumbnail'),
            'menu_position' => 30,
            'menu_icon' => 'dashicons-warning'
        ));

        // Market Intelligence post type
        register_post_type('sffc_market_intel', array(
            'labels' => array(
                'name' => 'Market Intelligence',
                'singular_name' => 'Market Intel',
                'add_new' => 'Add Intel',
                'add_new_item' => 'Add New Market Intel',
                'edit_item' => 'Edit Market Intel',
                'new_item' => 'New Market Intel',
                'view_item' => 'View Market Intel',
                'search_items' => 'Search Market Intel',
                'not_found' => 'No market intel found',
                'all_items' => 'All Market Intel',
                'menu_name' => 'Market Intel'
            ),
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => 'sffc-dashboard',  // Add to SF Finance menu
            'show_in_admin_bar' => true,
            'show_in_nav_menus' => true,
            'publicly_queryable' => true,
            'exclude_from_search' => false,
            'has_archive' => true,
            'hierarchical' => false,
            'rewrite' => array('slug' => 'market-intel'),
            'capability_type' => 'post',
            'supports' => array('title', 'editor', 'excerpt', 'custom-fields', 'thumbnail'),
            'menu_position' => 32,
            'menu_icon' => 'dashicons-chart-line'
        ));

        // Key Events post type (roadshows, earnings, regulatory dates, etc.)
        register_post_type('sffc_key_event', array(
            'labels' => array(
                'name' => 'Key Events',
                'singular_name' => 'Key Event',
                'add_new' => 'Add Event',
                'add_new_item' => 'Add New Key Event',
                'edit_item' => 'Edit Key Event',
                'new_item' => 'New Key Event',
                'view_item' => 'View Key Event',
                'search_items' => 'Search Key Events',
                'not_found' => 'No key events found',
                'all_items' => 'All Key Events',
                'menu_name' => 'Key Events'
            ),
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => 'sffc-dashboard',
            'show_in_admin_bar' => true,
            'show_in_nav_menus' => true,
            'publicly_queryable' => true,
            'exclude_from_search' => false,
            'has_archive' => true,
            'hierarchical' => false,
            'rewrite' => array('slug' => 'key-events'),
            'capability_type' => 'post',
            'supports' => array('title', 'editor', 'excerpt', 'custom-fields', 'thumbnail'),
            'menu_position' => 33,
            'menu_icon' => 'dashicons-calendar-alt'
        ));

        $this->register_key_event_meta();
    }

    /**
     * Register meta fields for key events (date, location, type, related link).
     */
    private function register_key_event_meta()
    {
        $meta_fields = array(
            'event_date' => array(
                'type' => 'string',
                'description' => 'ISO 8601 date or datetime string for the event.',
            ),
            'event_location' => array(
                'type' => 'string',
                'description' => 'Location or virtual details for the event.',
            ),
            'event_type' => array(
                'type' => 'string',
                'description' => 'Classification (earnings call, regulatory deadline, investor day, etc.).',
            ),
            'event_url' => array(
                'type' => 'string',
                'description' => 'Reference link or registration URL.',
            ),
        );

        foreach ($meta_fields as $key => $args) {
            register_post_meta('sffc_key_event', $key, array(
                'type' => $args['type'],
                'description' => $args['description'],
                'single' => true,
                'default' => '',
                'show_in_rest' => true,
                'sanitize_callback' => 'sanitize_text_field',
                'auth_callback' => function () {
                    return current_user_can('edit_posts');
                }
            ));
        }
    }

    /**
     * Self-healing cron - runs feed processing if missed for more than 2 hours
     * This ensures feeds are processed even if WP cron is unreliable
     */
    public function maybe_run_missed_cron()
    {
        // Only run on frontend page loads (not AJAX, not admin to avoid slowdowns)
        if (wp_doing_ajax() || (is_admin() && !wp_doing_cron())) {
            return;
        }

        // Check when feeds were last processed
        $last_run = get_option('sffc_pe_feeds_last_run', '');

        if (empty($last_run)) {
            // Never run - schedule it and run now
            $this->process_feed_content();
            return;
        }

        $last_run_time = strtotime($last_run);
        $hours_since_last_run = (time() - $last_run_time) / 3600;

        // If more than 2 hours since last run, process now
        if ($hours_since_last_run > 2) {
            // Use a transient lock to prevent multiple simultaneous runs
            $lock_key = 'sffc_pe_feed_processing_lock';
            if (get_transient($lock_key)) {
                return; // Already processing
            }

            // Set lock for 10 minutes
            set_transient($lock_key, true, 600);

            // Process feeds
            $this->process_feed_content();

            // Release lock
            delete_transient($lock_key);
        }
    }

    /**
     * Process feed content into custom posts
     */
    public function process_feed_content()
    {
        $company_engine = SFFC_Company_Intelligence_Engine::get_instance();

        $counts = $this->process_feeds_from_settings($company_engine);
        $total_created = array_sum($counts);

        if ($total_created === 0) {
            if (!class_exists('SFFC_XML_Feed_Processor')) {
                return $counts;
            }

            $feed_processor = SFFC_XML_Feed_Processor::get_instance();

            // Fallback to legacy processors if settings-based feeds returned nothing
            $this->process_pe_deals($feed_processor, $company_engine);
            $this->process_market_news($feed_processor, $company_engine);
            $this->process_company_news($feed_processor, $company_engine);
        }

        update_option('sffc_pe_feeds_last_run', current_time('mysql'));

        return $counts;
    }

    /**
     * Process a single feed defined in sffc-settings XML feed management
     */
    public function process_single_feed_content($feed_id)
    {
        $feed_id = (int) $feed_id;
        $counts = array(
            'deals' => 0,
            'news' => 0,
            'market' => 0,
            'signals' => 0
        );

        if ($feed_id <= 0) {
            return new WP_Error('invalid_feed', __('Invalid feed ID.', 'senna-finance'));
        }

        if (!class_exists('SFFC_Database')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-database.php';
        }

        $db = SFFC_Database::get_instance();
        $table_name = $db->get_table('xml_feeds');
        if (!$table_name) {
            return new WP_Error('missing_table', __('Feed table not found.', 'senna-finance'));
        }

        global $wpdb;
        $feed = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $feed_id
        ));

        if (!$feed) {
            return new WP_Error('feed_not_found', __('Feed not found.', 'senna-finance'));
        }

        $company_engine = SFFC_Company_Intelligence_Engine::get_instance();
        $post_type_key = $this->determine_feed_post_type($feed->feed_category);

        if (!$post_type_key) {
            $this->update_feed_error_status($feed->id, __('This feed category is not eligible for PE/news imports.', 'senna-finance'));
            return new WP_Error('unsupported_feed', __('This feed category is not eligible for PE/news imports.', 'senna-finance'));
        }

        $items = $this->fetch_feed_items($feed, 30);
        if (empty($items)) {
            return new WP_Error('no_items', __('Feed returned no items.', 'senna-finance'));
        }

        $created_for_feed = 0;

        foreach ($items as $item) {
            $headline = $this->build_headline_from_feed_item($item);
            if (empty($headline['link']) || $this->feed_item_exists($headline['link'])) {
                continue;
            }

            $content_type = $this->determine_content_post_type($headline);

            switch ($post_type_key) {
                case 'deals':
                    $ranking_score = $this->calculate_ranking_potential($headline);
                    if ($ranking_score >= 85) {
                        $created_id = $this->create_pe_signal_post($headline, $company_engine, $ranking_score);
                        if ($created_id) {
                            $counts['signals']++;
                            $created_for_feed++;
                        }
                    } else {
                        $created_id = $this->create_pe_deal_post($headline, $company_engine);
                        if ($created_id) {
                            $counts['deals']++;
                            $created_for_feed++;
                        }
                    }
                    break;

                case 'market':
                    if ($this->has_pe_relevance($headline)) {
                        $created_id = $this->create_market_intel_post($headline, $company_engine);
                        if ($created_id) {
                            $counts['market']++;
                            $created_for_feed++;
                        }
                    }
                    break;

                case 'news':
                    if ($content_type === 'deals') {
                        $created_id = $this->create_pe_deal_post($headline, $company_engine);
                        if ($created_id) {
                            $counts['deals']++;
                            $created_for_feed++;
                        }
                    } else {
                        $ranking_score = $this->calculate_ranking_potential($headline);
                        if ($ranking_score >= 85) {
                            $created_id = $this->create_pe_signal_post($headline, $company_engine, $ranking_score);
                            if ($created_id) {
                                $counts['signals']++;
                                $created_for_feed++;
                            }
                        } else {
                            $created_id = $this->create_pe_news_post($headline, $company_engine);
                            if ($created_id) {
                                $counts['news']++;
                                $created_for_feed++;
                            }
                        }
                    }
                    break;

                case 'filtered_news':
                default:
                    if (!$this->has_pe_relevance($headline)) {
                        continue 2;
                    }

                    if ($content_type === 'deals') {
                        $created_id = $this->create_pe_deal_post($headline, $company_engine);
                        if ($created_id) {
                            $counts['deals']++;
                            $created_for_feed++;
                        }
                    } else {
                        $ranking_score = $this->calculate_ranking_potential($headline);
                        if ($ranking_score >= 85) {
                            $created_id = $this->create_pe_signal_post($headline, $company_engine, $ranking_score);
                            if ($created_id) {
                                $counts['signals']++;
                                $created_for_feed++;
                            }
                        } else {
                            $created_id = $this->create_pe_news_post($headline, $company_engine);
                            if ($created_id) {
                                $counts['news']++;
                                $created_for_feed++;
                            }
                        }
                    }
                    break;
            }
        }

        if ($created_for_feed > 0) {
            $this->update_feed_success_status($feed->id);
            update_option('sffc_pe_feeds_last_run', current_time('mysql'));

            return array(
                'created' => $created_for_feed,
                'counts' => $counts,
                'feed_name' => $feed->feed_name,
            );
        }

        $this->update_feed_success_status($feed->id);
        update_option('sffc_pe_feeds_last_run', current_time('mysql'));

        return array(
            'created' => 0,
            'counts' => $counts,
            'feed_name' => $feed->feed_name,
        );
    }

    /**
     * Process feeds defined in sffc-settings XML feed management
     */
    private function process_feeds_from_settings($company_engine)
    {
        $feeds = $this->get_active_pe_feeds();
        $counts = array(
            'deals' => 0,
            'news' => 0,
            'market' => 0,
            'signals' => 0
        );

        if (empty($feeds)) {
            return $counts;
        }

        foreach ($feeds as $feed) {
            $post_type_key = $this->determine_feed_post_type($feed->feed_category);
            if (!$post_type_key) {
                continue;
            }

            $items = $this->fetch_feed_items($feed, 30);
            if (empty($items)) {
                $this->update_feed_error_status($feed->id, __('Feed returned no items.', 'senna-finance'));
                continue;
            }

            $created_for_feed = 0;

            foreach ($items as $item) {
                $headline = $this->build_headline_from_feed_item($item);
                if (empty($headline['link']) || $this->feed_item_exists($headline['link'])) {
                    continue;
                }

                // First, check if the CONTENT indicates this is a deal (regardless of feed category)
                $content_type = $this->determine_content_post_type($headline);

                switch ($post_type_key) {
	                    case 'deals':
	                        // Feed is specifically for deals - trust it
	                        $ranking_score = $this->calculate_ranking_potential($headline);
	                        if ($ranking_score >= 85) {
	                            $created_id = $this->create_pe_signal_post($headline, $company_engine, $ranking_score);
	                            if ($created_id) {
	                                $counts['signals']++;
	                                $created_for_feed++;
	                            }
	                        } else {
	                            $created_id = $this->create_pe_deal_post($headline, $company_engine);
	                            if ($created_id) {
	                                $counts['deals']++;
	                                $created_for_feed++;
	                            }
	                        }
	                        break;

                    case 'market':
	                        // Market intelligence - check PE relevance first
	                        if ($this->has_pe_relevance($headline)) {
	                            $created_id = $this->create_market_intel_post($headline, $company_engine);
	                            if ($created_id) {
	                                $counts['market']++;
	                                $created_for_feed++;
	                            }
	                        }
	                        break;

                    case 'news':
	                        // Core PE/VC feed - trust it, but route deals correctly
	                        if ($content_type === 'deals') {
	                            $created_id = $this->create_pe_deal_post($headline, $company_engine);
	                            if ($created_id) {
	                                $counts['deals']++;
	                                $created_for_feed++;
	                            }
	                        } else {
	                            $ranking_score = $this->calculate_ranking_potential($headline);
	                            if ($ranking_score >= 85) {
	                                $created_id = $this->create_pe_signal_post($headline, $company_engine, $ranking_score);
	                                if ($created_id) {
	                                    $counts['signals']++;
	                                    $created_for_feed++;
	                                }
	                            } else {
	                                $created_id = $this->create_pe_news_post($headline, $company_engine);
	                                if ($created_id) {
	                                    $counts['news']++;
	                                    $created_for_feed++;
	                                }
	                            }
	                        }
	                        break;

                    case 'filtered_news':
                    default:
                        // General feeds - REQUIRE PE relevance check
                        if (!$this->has_pe_relevance($headline)) {
                            // Skip non-PE content from general feeds
                            continue 2; // Continue to next item in foreach
                        }

	                        // Content is PE-relevant - now route appropriately
	                        if ($content_type === 'deals') {
	                            // Deal content from general feed
	                            $created_id = $this->create_pe_deal_post($headline, $company_engine);
	                            if ($created_id) {
	                                $counts['deals']++;
	                                $created_for_feed++;
	                            }
	                        } else {
	                            $ranking_score = $this->calculate_ranking_potential($headline);
	                            if ($ranking_score >= 85) {
	                                $created_id = $this->create_pe_signal_post($headline, $company_engine, $ranking_score);
	                                if ($created_id) {
	                                    $counts['signals']++;
	                                    $created_for_feed++;
	                                }
	                            } else {
	                                $created_id = $this->create_pe_news_post($headline, $company_engine);
	                                if ($created_id) {
	                                    $counts['news']++;
	                                    $created_for_feed++;
	                                }
	                            }
	                        }
	                        break;
                }
            }

            $this->update_feed_success_status($feed->id);
        }

        return $counts;
    }

    /**
     * Retrieve active XML feeds relevant for private markets
     */
    private function get_active_pe_feeds()
    {
        if (!class_exists('SFFC_Database')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-database.php';
        }

        $db = SFFC_Database::get_instance();
        $table_name = $db->get_table('xml_feeds');
        if (!$table_name) {
            return array();
        }

        global $wpdb;
        $feeds = $wpdb->get_results("SELECT * FROM {$table_name} WHERE is_active = 1 ORDER BY priority ASC, feed_name ASC");
        if (empty($feeds)) {
            return array();
        }

        $filtered = array();
        foreach ($feeds as $feed) {
            if ($this->determine_feed_post_type($feed->feed_category)) {
                $filtered[] = $feed;
            }
        }

        return $filtered;
    }

    /**
     * Fetch feed items for a given feed row
     */
    private function fetch_feed_items($feed, $limit = 25)
    {
        libxml_use_internal_errors(true);

        $response = wp_remote_get($feed->feed_url, array(
            'timeout' => 30,
            'redirection' => 5,
            'user-agent' => $this->get_feed_user_agent(),
            'headers' => array(
                'Accept' => 'application/rss+xml, application/atom+xml, application/xml, text/xml, */*;q=0.8',
            ),
        ));

        if (!is_wp_error($response)) {
            $code = (int) wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            if ($code >= 200 && $code < 300 && !empty($body)) {
                $items = $this->parse_xml_feed_body($body, $feed, $limit);
                if (!empty($items)) {
                    return $items;
                }
            } elseif ($code > 0) {
                $this->update_feed_error_status($feed->id, sprintf(__('HTTP Error %d.', 'senna-finance'), $code));
                return array();
            }
        }

        if (!class_exists('SimplePie')) {
            require_once ABSPATH . WPINC . '/class-simplepie.php';
        }

        $simplepie = new SimplePie();
        $simplepie->set_feed_url($feed->feed_url);
        $simplepie->set_cache_location(WP_CONTENT_DIR . '/cache');
        $simplepie->set_cache_duration(900);
        $simplepie->enable_cache(false);
        $simplepie->force_feed(true); // Force parsing even if not a proper feed
        $simplepie->set_timeout(30); // Increase timeout for slow feeds
        $simplepie->init();

        if ($simplepie->error()) {
            $message = is_wp_error($response)
                ? $response->get_error_message()
                : $simplepie->error();
            $this->update_feed_error_status($feed->id, $message);
            return array();
        }

        $raw_items = $simplepie->get_items(0, $limit);
        if (empty($raw_items)) {
            return array();
        }

        $items = array();
        foreach ($raw_items as $item) {
            $title = $item->get_title();
            $link = $item->get_permalink();

            // Skip items without title or link
            if (empty($title) || empty($link)) {
                continue;
            }

            $description = $item->get_description() ?: '';
            $content = $item->get_content() ?: '';

            $items[] = array(
                'title' => wp_strip_all_tags($title),
                'link' => esc_url_raw($link),
                'description' => wp_strip_all_tags($description),
                'content' => $content,
                'content:encoded' => $content,
                'pubDate' => $item->get_date('Y-m-d H:i:s') ?: current_time('mysql'),
                'source' => $feed->feed_name ?: parse_url($feed->feed_url, PHP_URL_HOST),
                'category' => $feed->feed_category
            );
        }

        return $items;
    }

    /**
     * Use a browser-like agent; several publisher CDNs reject generic PHP agents.
     */
    private function get_feed_user_agent()
    {
        return 'Mozilla/5.0 (compatible; SennaCareersFeedBot/1.0; +https://joinsenna.com/)';
    }

    /**
     * Parse RSS, Atom, RDF and sitemap XML into the common item shape.
     */
    private function parse_xml_feed_body($body, $feed, $limit = 25)
    {
        $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
        if (!$xml) {
            return array();
        }

        $items = array();
        $nodes = array();

        if (isset($xml->channel->item)) {
            foreach ($xml->channel->item as $item) {
                $nodes[] = array(
                    'title' => (string) $item->title,
                    'link' => (string) $item->link,
                    'description' => (string) $item->description,
                    'content' => (string) $item->children('content', true)->encoded,
                    'date' => (string) $item->pubDate,
                );
            }
        } elseif (isset($xml->entry)) {
            foreach ($xml->entry as $entry) {
                $link = '';
                foreach ($entry->link as $entry_link) {
                    $attrs = $entry_link->attributes();
                    if (!$link || (isset($attrs['rel']) && (string) $attrs['rel'] === 'alternate')) {
                        $link = isset($attrs['href']) ? (string) $attrs['href'] : (string) $entry_link;
                    }
                }

                $nodes[] = array(
                    'title' => (string) $entry->title,
                    'link' => $link,
                    'description' => (string) ($entry->summary ?: $entry->content),
                    'content' => (string) $entry->content,
                    'date' => (string) ($entry->updated ?: $entry->published),
                );
            }
        } elseif (isset($xml->item)) {
            foreach ($xml->item as $item) {
                $nodes[] = array(
                    'title' => (string) $item->title,
                    'link' => (string) $item->link,
                    'description' => (string) $item->description,
                    'content' => '',
                    'date' => (string) $item->date,
                );
            }
        } else {
            $url_nodes = $xml->xpath('//*[local-name()="url"]') ?: array();
            foreach ($url_nodes as $url_node) {
                $loc = $this->get_xml_child_value($url_node, 'loc');
                if (!$loc) {
                    continue;
                }

                $nodes[] = array(
                    'title' => $this->title_from_url($loc),
                    'link' => $loc,
                    'description' => '',
                    'content' => '',
                    'date' => $this->get_xml_child_value($url_node, 'lastmod'),
                );
            }
        }

        foreach ($nodes as $node) {
            if (count($items) >= $limit) {
                break;
            }

            $title = wp_strip_all_tags($node['title']);
            $link = esc_url_raw($node['link']);
            if (empty($title) || empty($link)) {
                continue;
            }

            $items[] = array(
                'title' => $title,
                'link' => $link,
                'description' => wp_strip_all_tags($node['description']),
                'content' => $node['content'],
                'content:encoded' => $node['content'],
                'pubDate' => $this->normalize_feed_date($node['date']),
                'source' => $feed->feed_name ?: parse_url($feed->feed_url, PHP_URL_HOST),
                'category' => $feed->feed_category
            );
        }

        return $items;
    }

    private function get_xml_child_value($node, $name)
    {
        $matches = $node->xpath('./*[local-name()="' . $name . '"]');
        return !empty($matches) ? trim((string) $matches[0]) : '';
    }

    private function normalize_feed_date($date)
    {
        if (empty($date)) {
            return current_time('mysql');
        }

        $timestamp = strtotime($date);
        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : current_time('mysql');
    }

    private function title_from_url($url)
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $segment = $path ? basename($path) : parse_url($url, PHP_URL_HOST);
        $segment = preg_replace('/\.[a-z0-9]+$/i', '', $segment);
        $segment = str_replace(array('-', '_'), ' ', $segment);

        return ucwords(trim($segment)) ?: $url;
    }

    /**
     * Determine which post type a feed should populate with tiered filtering
     *
     * REVISED: Balanced approach - PE-specific feeds pass through unfiltered,
     * general feeds require PE relevance check.
     */
    private function determine_feed_post_type($category)
    {
        $slug = sanitize_title($category);

        // TIER 1 - EXCLUDE (Specific non-PE categories)
        $exclude_patterns = array(
            '/^hr-jobs$/',
            '/^recruitment-only$/',
            '/^accounting-careers$/',
            '/^insurance-careers$/',
            '/^actuarial-jobs$/',
            '/^data-science-careers$/',
            '/^consulting-careers$/',
            '/^property-management-jobs$/',
            '/^penny-stocks$/',
            '/^credit-cards$/',
            '/^personal-finance$/',
            '/^mortgages$/',
            '/^savings$/',
        );

        foreach ($exclude_patterns as $pattern) {
            if (preg_match($pattern, $slug)) {
                return null; // Discard
            }
        }

        // Curated Middle East finance/business feeds are already pre-screened at
        // install time. Let them enter the news pipeline so valid regional
        // stories are not blocked by the PE-only keyword filter.
        if (strpos($slug, 'middle-east-') === 0) {
            return 'news';
        }

        // TIER 2 - DEALS (Feed is specifically about deals)
        if (preg_match('/\bdeal|\btransaction|\bbuyout|\bacquisition|m&a|\bmerger|\btakeover/', $slug)) {
            return 'deals';
        }

        // TIER 3 - CORE PE/VC (Direct pass-through, no filtering needed)
        if (preg_match('/private.*equity|\bpe\b|venture.*capital|\bvc\b|secondaries|infrastructure|debt.*fund|alternatives|growth.*equity|distressed|mezzanine|\blbo\b|leveraged|buyout/', $slug)) {
            return 'news';
        }

        // TIER 4 - MARKET INTELLIGENCE
        if (preg_match('/intel|analysis|insight|macro|economic|outlook|research/', $slug)) {
            return 'market';
        }

        // TIER 5 - GENERAL FINANCIAL (Requires PE relevance check)
        // These feeds may contain PE content but also lots of irrelevant content
        if (preg_match('/business|finance|market|banking|investment|capital|corporate|company|stock|equit/', $slug)) {
            return 'filtered_news'; // Will require PE relevance check
        }

        // DEFAULT: Require PE relevance check for unknown categories
        return 'filtered_news';
    }

    /**
     * Determine post type based on article CONTENT (not just feed category)
     * This allows deal articles from general feeds to be routed correctly
     */
    private function determine_content_post_type($headline)
    {
        $text = strtolower($headline['title'] . ' ' . ($headline['description'] ?? ''));

        // Check if this is deal content based on headline
        $deal_patterns = array(
            'acquires', 'to acquire', 'acquisition of', 'has acquired',
            'buys', 'to buy', 'buying', 'purchased', 'to purchase',
            'merger', 'merges with', 'to merge',
            'takeover', 'takes over', 'take over',
            'buyout', 'bought out', 'buy out',
            'sells', 'to sell', 'sale of', 'divests', 'divestiture',
            'exits', 'exit from', 'exiting',
            'ipo', 'goes public', 'initial public offering', 'public listing',
            'raises', 'fundraise', 'closes fund', 'fund close',
            'invests', 'investment in', 'backs', 'backed by',
            'deal', 'transaction', 'agreement to',
        );

        foreach ($deal_patterns as $pattern) {
            if (strpos($text, $pattern) !== false) {
                return 'deals';
            }
        }

        return null; // Not clearly a deal - use feed category routing
    }

    /**
     * Process PE deals from feeds
     */
    private function process_pe_deals($feed_processor, $company_engine)
    {
        // Get PE firms for filtering
        $pe_entities = array(
            'companies' => $this->get_pe_companies()
        );

        // Process PE deals category
        $pe_deals_data = $feed_processor->get_live_data('pe_deals', $pe_entities);

        if (!empty($pe_deals_data['headlines'])) {
            foreach ($pe_deals_data['headlines'] as $headline) {
                $this->create_pe_deal_post($headline, $company_engine);
            }
        } else {
            $this->process_deal_feeds_fallback($company_engine);
        }
    }

    /**
     * Process market news from feeds
     */
    private function process_market_news($feed_processor, $company_engine)
    {
        $pe_entities = array(
            'companies' => $this->get_pe_companies()
        );

        // Process market data
        $market_data = $feed_processor->get_live_data('market_analysis', $pe_entities);

        if (!empty($market_data['headlines'])) {
            foreach ($market_data['headlines'] as $headline) {
                $this->create_pe_news_post($headline, $company_engine);
            }
        } else {
            $this->process_market_news_fallback($company_engine);
        }
    }

    /**
     * Process company news from feeds
     */
    private function process_company_news($feed_processor, $company_engine)
    {
        $pe_entities = array(
            'companies' => $this->get_pe_companies()
        );

        // Process company news
        $company_news = $feed_processor->get_live_data('company_news', $pe_entities);

        if (!empty($company_news['headlines'])) {
            foreach ($company_news['headlines'] as $headline) {
                $this->create_market_intel_post($headline, $company_engine);
            }
        } else {
            $this->process_company_news_fallback($company_engine);
        }
    }

    /**
     * Load fallback items for PE deals when primary feeds fail
     */
    private function process_deal_feeds_fallback($company_engine)
    {
        $items = $this->get_feed_items_from_aggregator(array('private-equity', 'pe-firms', 'secondaries', 'venture-capital', 'infrastructure'), 40);
        if (empty($items)) {
            return;
        }

        foreach ($items as $item) {
            $headline = $this->build_headline_from_feed_item($item);
            if (empty($headline['link'])) {
                continue;
            }
            if ($this->feed_item_exists($headline['link'])) {
                continue;
            }
            $this->create_pe_deal_post($headline, $company_engine);
        }
    }

    /**
     * Load fallback items for market news when primary feeds fail
     */
    private function process_market_news_fallback($company_engine)
    {
        $items = $this->get_feed_items_from_aggregator(array('private-equity', 'venture-capital', 'pe-firms', 'alternatives', 'infrastructure'), 60);
        if (empty($items)) {
            return;
        }

        foreach ($items as $item) {
            $headline = $this->build_headline_from_feed_item($item);
            if (empty($headline['link'])) {
                continue;
            }
            if ($this->feed_item_exists($headline['link'])) {
                continue;
            }
            $this->create_pe_news_post($headline, $company_engine);
        }
    }

    /**
     * Load fallback items for company intel when primary feeds fail
     */
    private function process_company_news_fallback($company_engine)
    {
        $items = $this->get_feed_items_from_aggregator(array('private-equity', 'venture-capital', 'pe-firms', 'company-updates'), 60);
        if (empty($items)) {
            return;
        }

        foreach ($items as $item) {
            $headline = $this->build_headline_from_feed_item($item);
            if (empty($headline['link'])) {
                continue;
            }
            if ($this->feed_item_exists($headline['link'])) {
                continue;
            }
            $this->create_market_intel_post($headline, $company_engine);
        }
    }

    /**
     * Fetch feed items via XML feed aggregator filtered by allowed categories
     */
    private function get_feed_items_from_aggregator($allowed_slugs, $limit = 40)
    {
        if (!class_exists('SFFC_XML_Feed_Aggregator')) {
            $aggregator_path = SFFC_PLUGIN_DIR . 'includes/services/class-xml-feed-aggregator.php';
            if (file_exists($aggregator_path)) {
                require_once $aggregator_path;
            }
        }

        if (!class_exists('SFFC_XML_Feed_Aggregator')) {
            return array();
        }

        $aggregator = SFFC_XML_Feed_Aggregator::get_instance();
        $items = $aggregator->aggregate_feeds('', $limit);
        if (empty($items)) {
            return array();
        }

        $allowed_lookup = array();
        foreach ($allowed_slugs as $slug) {
            $allowed_lookup[sanitize_title($slug)] = true;
        }

        $filtered = array();
        foreach ($items as $item) {
            $slug = isset($item['category']) ? sanitize_title($item['category']) : '';
            if (!$slug) {
                continue;
            }

            $matched = !empty($allowed_lookup[$slug]);
            if (!$matched) {
                foreach ($allowed_lookup as $allowed_slug => $unused) {
                    if (false !== strpos($slug, $allowed_slug) || false !== strpos($allowed_slug, $slug)) {
                        $matched = true;
                        break;
                    }
                }
            }

            if (!$matched) {
                continue;
            }

            $filtered[] = $item;
        }

        return $filtered;
    }

    /**
     * Normalize aggregator item into headline structure expected by creators
     */
    private function build_headline_from_feed_item($item)
    {
        return array(
            'title' => isset($item['title']) ? wp_strip_all_tags($item['title']) : '',
            'link' => isset($item['link']) ? esc_url_raw($item['link']) : '',
            'description' => isset($item['description']) ? $this->sanitize_feed_content($item['description']) : '',
            'content' => isset($item['content']) ? $this->sanitize_feed_content($item['content']) : '',
            'content:encoded' => isset($item['content:encoded']) ? $this->sanitize_feed_content($item['content:encoded']) : '',
            'source' => isset($item['source']) ? wp_strip_all_tags($item['source']) : __('Market Feed', 'senna-finance'),
            'time' => isset($item['pubDate']) ? date('Y-m-d H:i:s', strtotime($item['pubDate'])) : current_time('mysql')
        );
    }

    private function get_headline_body_source(array $headline)
    {
        $candidates = array(
            $headline['content'] ?? '',
            $headline['content:encoded'] ?? '',
            $headline['description'] ?? '',
            $headline['title'] ?? '',
        );

        foreach ($candidates as $candidate) {
            $candidate = $this->sanitize_feed_content((string) $candidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }

    private function has_meaningful_article_content($content, $minimum_length = 220)
    {
        $plain = trim(wp_strip_all_tags((string) $content));
        if ($plain === '') {
            return false;
        }

        $plain = preg_replace('/\s+/', ' ', $plain);
        $quality_plain = preg_replace('/For complete details,?\s+please refer to the original article\.?/i', '', $plain);
        $quality_plain = preg_replace('/For complete details,?\s+please refer to the/i', '', $quality_plain);
        $quality_plain = trim(preg_replace('/\s+/', ' ', $quality_plain));
        $word_count = str_word_count($quality_plain);

        return strlen($quality_plain) >= (int) $minimum_length && $word_count >= 35;
    }

    /**
     * Sanitize feed content - remove CSS, JavaScript, and other non-content elements
     */
    private function sanitize_feed_content($content)
    {
        if (empty($content)) {
            return '';
        }

        // Remove script tags and their content
        $content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $content);

        // Remove style tags and their content
        $content = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $content);

        // Remove noscript tags
        $content = preg_replace('/<noscript\b[^>]*>(.*?)<\/noscript>/is', '', $content);

        // Remove iframe tags
        $content = preg_replace('/<iframe\b[^>]*>(.*?)<\/iframe>/is', '', $content);

        // Remove inline CSS (style attributes)
        $content = preg_replace('/\s*style\s*=\s*["\'][^"\']*["\']/i', '', $content);

        // Remove CSS blocks that start with selectors (like .class-name { ... })
        $content = preg_replace('/\.[a-zA-Z0-9_-]+\s*\{[^}]*\}/s', '', $content);
        $content = preg_replace('/#[a-zA-Z0-9_-]+\s*\{[^}]*\}/s', '', $content);

        // Remove CSS variable declarations
        $content = preg_replace('/--[a-zA-Z0-9_-]+\s*:[^;]+;/s', '', $content);

        // Remove @media queries and @keyframes
        $content = preg_replace('/@media[^{]*\{([^{}]*\{[^{}]*\})*[^{}]*\}/s', '', $content);
        $content = preg_replace('/@keyframes[^{]*\{([^{}]*\{[^{}]*\})*[^{}]*\}/s', '', $content);

        // Remove JavaScript patterns
        $content = preg_replace('/\(function\s*\(\)\s*\{.*?\}\)\s*\(\s*\)\s*;?/s', '', $content);
        $content = preg_replace('/window\.[a-zA-Z0-9_]+\s*=\s*[^;]+;/s', '', $content);
        $content = preg_replace('/if\s*\(\s*!window\.[^)]+\)[^;]+;/s', '', $content);
        $content = preg_replace('/document\.[a-zA-Z]+[^;]*;/s', '', $content);

        // Remove common paywall/subscription elements
        $content = preg_replace('/(Create an account|Sign in|Subscribe|Register|Log in)[^.]*\./i', '', $content);
        $content = preg_replace('/Continue reading[^.]*\./i', '', $content);

        // Remove common tracking/analytics patterns
        $content = preg_replace('/\{[^}]*featureLabel[^}]*\}/s', '', $content);
        $content = preg_replace('/\{[^}]*outcomeId[^}]*\}/s', '', $content);

        // Strip remaining HTML tags
        $content = wp_strip_all_tags($content);

        // Clean up whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);

        // Remove any remaining CSS-like patterns (property: value;)
        $content = preg_replace('/[a-z-]+\s*:\s*[^;]+;\s*/i', '', $content);

        // Final cleanup
        $content = trim($content);

        return $content;
    }

    /**
     * Check if a feed item already exists based on source URL
     * Only checks recent posts (last 5 days) to allow re-importing older content
     */
    private function feed_item_exists($url)
    {
        if (empty($url)) {
            return false;
        }

        $existing = get_posts(array(
            'post_type' => array('sffc_pe_news', 'sffc_pe_deal', 'sffc_market_intel', 'sffc_pe_signal'),
            'posts_per_page' => 1,
            'meta_key' => '_source_url',
            'meta_value' => esc_url_raw($url),
            'date_query' => array(
                array(
                    'after' => '5 days ago'
                )
            )
        ));

        if (empty($existing)) {
            return false;
        }

        return $this->has_meaningful_article_content($existing[0]->post_content ?? '');
    }

    /**
     * Update feed success status in XML feeds table
     */
    private function update_feed_success_status($feed_id)
    {
        if (!$feed_id) {
            return;
        }

        if (!class_exists('SFFC_Database')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-database.php';
        }

        $db = SFFC_Database::get_instance();
        $table_name = $db->get_table('xml_feeds');
        if (!$table_name) {
            return;
        }

        global $wpdb;
        $current_success = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT success_count FROM {$table_name} WHERE id = %d",
            $feed_id
        ));

        $wpdb->update(
            $table_name,
            array(
                'last_fetched' => current_time('mysql'),
                'success_count' => $current_success + 1,
                'error_count' => 0,
                'last_error' => null,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $feed_id),
            array('%s', '%d', '%d', '%s', '%s'),
            array('%d')
        );
    }

    /**
     * Update feed error status in XML feeds table
     */
    private function update_feed_error_status($feed_id, $message)
    {
        if (!$feed_id) {
            return;
        }

        if (!class_exists('SFFC_Database')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-database.php';
        }

        $db = SFFC_Database::get_instance();
        $table_name = $db->get_table('xml_feeds');
        if (!$table_name) {
            return;
        }

        global $wpdb;
        $current_error = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT error_count FROM {$table_name} WHERE id = %d",
            $feed_id
        ));

        $wpdb->update(
            $table_name,
            array(
                'last_error' => $message,
                'error_count' => $current_error + 1,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $feed_id),
            array('%s', '%d', '%s'),
            array('%d')
        );
    }

    /**
     * Create PE deal post
     */
    private function create_pe_deal_post($headline, $company_engine)
    {
        if (!$this->is_publishable_pe_headline($headline)) {
            return 0;
        }

        $existing_post_id = 0;

        // Check if already exists (but allow re-creation if old one was cleaned up)
        $existing = get_posts(array(
            'post_type' => 'sffc_pe_deal',
            'meta_key' => '_source_url',
            'meta_value' => $headline['link'],
            'posts_per_page' => 1,
            'date_query' => array(
                array(
                    'after' => '5 days ago'  // Only check recent posts
                )
            )
        ));

        if (!empty($existing)) {
            if ($this->has_meaningful_article_content($existing[0]->post_content ?? '')) {
                return $existing[0]->ID;
            }
            $existing_post_id = (int) $existing[0]->ID;
        }

        // Check daily post limit
        $cost_manager = SFFC_API_Cost_Manager::get_instance();
        if ($existing_post_id <= 0 && !$cost_manager->is_within_daily_post_limit()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[SFFC] Daily post limit reached - skipping PE deal creation');
            }
            return 0;
        }

        // Extract deal info from title/content
        $deal_info = $this->extract_deal_info($headline);
        $article_payload = $this->build_enhanced_article('deal', $headline, $deal_info);
        if (!is_array($article_payload)) {
            $article_payload = array();
        }

        $original_title = wp_strip_all_tags((string) ($headline['title'] ?? ''));
        $raw_content = !empty($article_payload['content']) ? $article_payload['content'] : $deal_info['description'];
        $post_content = $this->sanitize_feed_content($raw_content);
        if (!$this->has_meaningful_article_content($post_content)) {
            return 0;
        }
        $post_excerpt = !empty($article_payload['excerpt']) ? wp_strip_all_tags($article_payload['excerpt']) : wp_trim_words($post_content, 30);

        // Optimize headline for CTR while maintaining accuracy
        $post_title = $this->optimize_headline($original_title, $post_content, array(
            'value' => $deal_info['value'],
            'sector' => $deal_info['sector'],
            'region' => $deal_info['region'],
            'companies' => $deal_info['companies'],
            'type' => 'deal',
        ));

        $meta_input = array(
            '_source_url' => $headline['link'],
            '_source_name' => $headline['source'],
            '_original_title' => $original_title, // Store original for reference
            '_deal_value' => $deal_info['value'],
            '_deal_type' => $deal_info['type'],
            '_companies_involved' => json_encode($deal_info['companies']),
            '_region' => $deal_info['region'],
            '_sector' => $deal_info['sector'],
            '_published_date' => $headline['time']
        );

        $meta_input = array_merge($meta_input, $this->build_article_meta($article_payload));

        $post_data = array(
            'post_type' => 'sffc_pe_deal',
            'post_title' => $post_title,
            'post_content' => $post_content,
            'post_excerpt' => $post_excerpt,
            'post_status' => 'publish',
            'meta_input' => $meta_input
        );

        if ($existing_post_id > 0) {
            $post_data['ID'] = $existing_post_id;
            $post_id = wp_update_post($post_data);
        } else {
            $post_id = wp_insert_post($post_data);
        }

        if ($post_id && !is_wp_error($post_id)) {
            // Increment daily post counter
            if ($existing_post_id <= 0) {
                $cost_manager->increment_daily_post_count('sffc_pe_deal');
            }

            // Link to companies
            $this->link_to_companies($post_id, $deal_info['companies'], 'deal');

            // Extract and store financial metrics for deal dashboard
            $this->extract_and_store_deal_financials($post_id, $post_content, $post_title);
        }

        return $post_id;
    }

    /**
     * Extract and store deal financials for an article
     *
     * @param int    $post_id      The post ID
     * @param string $content      The article content
     * @param string $title        The article title
     */
    private function extract_and_store_deal_financials($post_id, $content, $title)
    {
        // Check if Deal Intelligence Processor is available
        if (!class_exists('SFFC_Deal_Intelligence_Processor')) {
            return;
        }

        try {
            $processor = SFFC_Deal_Intelligence_Processor::get_instance();

            // First, extract financial data without AI (fast, no API cost)
            $deal_financials = $processor->build_deal_financials_meta($content, $title, false);

            // Only proceed if we found meaningful financial data
            if (empty($deal_financials) ||
                ($deal_financials['data_quality']['overall_score'] ?? 0) <= 0) {
                return;
            }

            // Check if AI enhancement is enabled (admin setting)
            $ai_enhancement_enabled = get_option('sffc_enable_ai_deal_analysis', false);

            // Only enhance with AI for high-quality deals (score >= 40) to save API costs
            $quality_threshold = 40;
            $should_enhance = $ai_enhancement_enabled &&
                              ($deal_financials['data_quality']['overall_score'] ?? 0) >= $quality_threshold;

            if ($should_enhance) {
                // Enhance with Claude AI (scenarios, risks, comparables)
                $deal_financials = $processor->enhance_with_ai($deal_financials, $content, $title);
            }

            update_post_meta($post_id, '_sffc_deal_financials', $deal_financials);

        } catch (Exception $e) {
            // Log error but don't break article creation
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('SFFC Deal Financials extraction error: ' . $e->getMessage());
            }
        }
    }

    /**
     * Create PE Signal post (high-ranking potential content)
     */
    private function create_pe_signal_post($headline, $company_engine, $ranking_score)
    {
        if (!$this->is_publishable_pe_headline($headline)) {
            return 0;
        }

        $existing_post_id = 0;

        // Check if already exists
        $existing = get_posts(array(
            'post_type' => 'sffc_pe_signal',
            'meta_key' => '_source_url',
            'meta_value' => $headline['link'],
            'posts_per_page' => 1,
            'date_query' => array(
                array(
                    'after' => '5 days ago'
                )
            )
        ));

        if (!empty($existing)) {
            if ($this->has_meaningful_article_content($existing[0]->post_content ?? '')) {
                return $existing[0]->ID;
            }
            $existing_post_id = (int) $existing[0]->ID;
        }

        // Check daily post limit
        $cost_manager = SFFC_API_Cost_Manager::get_instance();
        if ($existing_post_id <= 0 && !$cost_manager->is_within_daily_post_limit()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[SFFC] Daily post limit reached - skipping PE signal creation');
            }
            return 0;
        }

        // Extract signal info
        $signal_info = $this->extract_signal_info($headline, $ranking_score);
        $article_payload = $this->build_enhanced_article('signal', $headline, $signal_info);
        if (!is_array($article_payload)) {
            $article_payload = array();
        }

        $original_title = wp_strip_all_tags((string) ($headline['title'] ?? ''));
        $raw_content = !empty($article_payload['content']) ? $article_payload['content'] : $signal_info['content'];
        $post_content = $this->sanitize_feed_content($raw_content);
        if (!$this->has_meaningful_article_content($post_content)) {
            return 0;
        }
        $post_excerpt = !empty($article_payload['excerpt']) ? wp_strip_all_tags($article_payload['excerpt']) : wp_trim_words($post_content, 30);

        // Optimize headline for maximum CTR - signals are high-priority content
        $post_title = $this->optimize_headline($original_title, $post_content, array(
            'signal_type' => $signal_info['signal_type'],
            'brands' => $signal_info['brands'],
            'financial_impact' => $signal_info['financial_impact'],
            'controversy_level' => $signal_info['controversy_level'],
            'type' => 'signal',
        ));

        $meta_input = array(
            '_source_url' => $headline['link'],
            '_source_name' => $headline['source'],
            '_original_title' => $original_title, // Store original for reference
            '_ranking_score' => $ranking_score,
            '_signal_type' => $signal_info['signal_type'],
            '_controversy_level' => $signal_info['controversy_level'],
            '_brand_mentions' => json_encode($signal_info['brands']),
            '_financial_impact' => $signal_info['financial_impact'],
            '_notable_keywords' => json_encode($signal_info['notable_keywords'] ?? array()),
            '_published_date' => $headline['time'],
            '_priority_level' => $this->get_priority_level($ranking_score)
        );

        $meta_input = array_merge($meta_input, $this->build_article_meta($article_payload));

        $post_data = array(
            'post_type' => 'sffc_pe_signal',
            'post_title' => $post_title,
            'post_content' => $post_content,
            'post_excerpt' => $post_excerpt,
            'post_status' => 'publish',
            'meta_input' => $meta_input
        );

        if ($existing_post_id > 0) {
            $post_data['ID'] = $existing_post_id;
            $post_id = wp_update_post($post_data);
        } else {
            $post_id = wp_insert_post($post_data);
        }

        if ($post_id && !is_wp_error($post_id)) {
            // Increment daily post counter
            if ($existing_post_id <= 0) {
                $cost_manager->increment_daily_post_count('sffc_pe_signal');
            }

            // Link to companies
            $this->link_to_companies($post_id, $signal_info['companies'], 'signal');

            // Extract and store financial metrics for deal dashboard
            $this->extract_and_store_deal_financials($post_id, $post_content, $post_title);
        }

        return $post_id;
    }

    /**
     * Calculate ranking potential based on verified newsworthy patterns
     *
     * IMPORTANT: This function now uses conservative scoring to avoid false positives.
     * Content is only flagged as high-priority when there's clear, verified significance.
     */
    private function calculate_ranking_potential($headline)
    {
        $title = strtolower($headline['title']);
        $description = isset($headline['description']) ? strtolower($headline['description']) : '';
        $text = $title . ' ' . $description;
        $score = 0;

        // EXCLUSION PATTERNS - If these appear, reduce likelihood of false flagging
        // These indicate neutral business context, not actual negative news
        $neutral_context_patterns = array(
            'risk management', 'risk assessment', 'risk officer', 'chief risk',
            'manages risk', 'risk committee', 'risk appetite', 'risk-adjusted',
            'job losses', 'weight loss', 'loss prevention', 'profit and loss',
            'hit targets', 'hit milestones', 'hit goals', 'hit record',
            'facing opportunity', 'facing growth', 'facing demand',
            'problem solving', 'problem-solving', 'addresses problem',
            'fine-tuning', 'fine dining', 'fine print'
        );

        $has_neutral_context = false;
        foreach ($neutral_context_patterns as $pattern) {
            if (strpos($text, $pattern) !== false) {
                $has_neutral_context = true;
                break;
            }
        }

        // If neutral context detected, return low score - this is normal business news
        if ($has_neutral_context) {
            return 20;
        }

        // VERIFIED NEGATIVE NEWS - Only flag when clearly newsworthy negative events
        // Requires EXPLICIT negative context, not just keyword presence
        // Score threshold raised: only 85+ creates a signal
        $verified_negative_patterns = array(
            'charged with fraud' => 85,
            'indicted' => 85,
            'files for bankruptcy' => 90,
            'declares bankruptcy' => 90,
            'sec charges' => 85,
            'doj investigation' => 85,
            'criminal investigation' => 90,
            'ponzi scheme' => 95,
            'securities fraud' => 90,
            'accounting scandal' => 90,
            'embezzlement' => 90,
            'market manipulation' => 85,
            'insider trading' => 85,
            'massive layoffs' => 70,
            'company collapses' => 85,
            'firm collapses' => 85,
            'fund collapses' => 85,
        );

        foreach ($verified_negative_patterns as $pattern => $points) {
            if (strpos($text, $pattern) !== false) {
                $score += $points;
                break; // Only count one negative pattern
            }
        }

        // MAJOR DEAL NEWS - Only for significant, verified transactions
        // Reduced scoring - deals are normal business, not signals
        if (preg_match('/\$([0-9,]+\.?[0-9]*)\s*(billion|bn)/i', $text, $matches)) {
            $amount = (float)str_replace(',', '', $matches[1]);
            if ($amount >= 10) $score += 30; // $10B+ deals are notable
            elseif ($amount >= 5) $score += 20;
            // Smaller deals don't add to signal score
        }

        // BREAKING/EXCLUSIVE - Only if from tier-1 source AND significant content
        $source = strtolower($headline['source'] ?? '');
        $tier1_sources = array('reuters', 'bloomberg', 'financial times', 'wall street journal', 'ft.com', 'wsj.com');
        $is_tier1 = false;
        foreach ($tier1_sources as $tier1) {
            if (strpos($source, $tier1) !== false) {
                $is_tier1 = true;
                break;
            }
        }

        if ($is_tier1 && (strpos($text, 'breaking') !== false || strpos($text, 'exclusive') !== false)) {
            $score += 15;
        }

        // Cap at 100, but threshold for signal creation is now 85 (checked in calling code)
        return min($score, 100);
    }

    /**
     * Extract signal-specific information
     *
     * IMPORTANT: Uses context-aware matching to avoid false classifications.
     * Only classifies as negative when there's clear, verified context.
     */
    private function extract_signal_info($headline, $ranking_score)
    {
        $title = $headline['title'];
        $body_source = $this->get_headline_body_source($headline);
        $text = strtolower($title . ' ' . $body_source);

        $info = array(
            'content' => $body_source,
            'signal_type' => 'market_update', // Default to neutral type
            'controversy_level' => 'neutral',  // Default to neutral, not 'low'
            'brands' => array(),
            'companies' => array(),
            'financial_impact' => null,
            'notable_keywords' => array() // Renamed from 'viral_keywords' - we report facts, not virality
        );

        // CONTEXT-AWARE EXCLUSIONS - Don't flag these as negative
        $neutral_contexts = array(
            'risk management', 'risk officer', 'risk assessment', 'risk-adjusted',
            'job losses due to', 'weight loss', 'loss prevention',
            'profit and loss', 'stop-loss', 'loss ratio',
            'fine-tune', 'fine print', 'fine dining',
            'problem solving', 'addresses problem', 'solves problem'
        );

        $has_neutral_context = false;
        foreach ($neutral_contexts as $context) {
            if (strpos($text, $context) !== false) {
                $has_neutral_context = true;
                break;
            }
        }

        // Only classify as negative if we have VERIFIED negative patterns (not just keywords)
        if (!$has_neutral_context) {
            // Verified scandal/legal patterns - require explicit context
            if (preg_match('/charged with fraud|securities fraud|accounting scandal|ponzi scheme|indicted|sec charges|doj investigation|criminal investigation/', $text)) {
                $info['signal_type'] = 'regulatory_action';
                $info['controversy_level'] = 'significant';
            }
            // Verified bankruptcy - require explicit filing language
            elseif (preg_match('/files for bankruptcy|declares bankruptcy|chapter 11|chapter 7 filing|bankruptcy protection/', $text)) {
                $info['signal_type'] = 'bankruptcy_filing';
                $info['controversy_level'] = 'significant';
            }
            // Verified collapse - require explicit company/firm collapse
            elseif (preg_match('/(company|firm|fund|bank) collapses/', $text)) {
                $info['signal_type'] = 'business_failure';
                $info['controversy_level'] = 'significant';
            }
            // Major deal activity - this is notable but NOT negative
            elseif (preg_match('/merger|acquisition|buyout|takeover/', $text)) {
                $info['signal_type'] = 'deal_activity';
                $info['controversy_level'] = 'neutral';
            }
            // Market activity - this is notable but NOT negative
            elseif (preg_match('/ipo|exits|fundraising|fund close/', $text)) {
                $info['signal_type'] = 'market_activity';
                $info['controversy_level'] = 'neutral';
            }
        }

        // Extract financial amounts - factual data only
        if (preg_match('/\$([0-9,]+\.?[0-9]*)\s*(billion|million|bn|mn)/i', $text, $matches)) {
            $info['financial_impact'] = $matches[0];
        }

        // Extract factual keywords only - not "viral" keywords
        $factual_patterns = array('bankruptcy', 'sec charges', 'investigation', 'merger', 'acquisition', 'ipo');
        foreach ($factual_patterns as $pattern) {
            if (strpos($text, $pattern) !== false) {
                $info['notable_keywords'][] = $pattern;
            }
        }

        return $info;
    }

    /**
     * Check if content has PE relevance for filtered feeds
     *
     * REVISED: Expanded list of firms and keywords for better coverage
     */
    private function has_pe_relevance($headline)
    {
        $text = strtolower($headline['title'] . ' ' . (isset($headline['description']) ? $headline['description'] : ''));

        // PE/VC firm mentions - comprehensive list
        $pe_firms = array(
            // Mega-cap PE
            'blackstone', 'kkr', 'apollo', 'carlyle', 'tpg', 'warburg pincus',
            'bain capital', 'advent', 'cvc', 'cvc capital', 'eqt', 'eqt partners',
            // Large PE
            'permira', 'cinven', 'bc partners', 'pai partners', 'apax', 'apax partners',
            'silver lake', 'thoma bravo', 'vista equity', 'hellman friedman',
            'general atlantic', 'insight partners', 'ta associates', 'gtcr',
            // Credit/Alternatives
            'ares', 'ares management', 'blue owl', 'owl rock', 'golub capital',
            'hig capital', 'h.i.g.', 'oaktree', 'cerberus', 'apollo global',
            // Infrastructure
            'brookfield', 'macquarie', 'global infrastructure', 'stonepeak',
            'arclight', 'energy capital', 'i squared',
            // Growth equity
            'summit partners', 'providence equity', 'francisco partners',
            'accel-kkr', 'battery ventures', 'norwest', 'spectrum equity',
            // European PE
            'triton', 'nordic capital', 'montagu', 'bridgepoint', 'ardian',
            'antin', 'astorg', 'ik partners', 'equistone', 'waterland',
            'gilde', 'parcom', 'egeria', 'gimv', 'capman', 'vaaka',
            // VC firms
            'sequoia', 'andreessen', 'a16z', 'accel', 'benchmark', 'greylock',
            'kleiner perkins', 'lightspeed', 'bessemer', 'index ventures',
            'atomico', 'balderton', 'creandum', 'northzone', 'lakestar',
            // Secondary specialists
            'lexington', 'ardian secondary', 'alpinvest', 'hamilton lane',
            'coller capital', 'pantheon', 'partners group', 'harbourvest',
            // Asset managers with PE
            'blackrock', 'goldman sachs', 'morgan stanley', 'jpmorgan',
            'ubs', 'credit suisse', 'deutsche bank', 'barclays',
        );

        foreach ($pe_firms as $firm) {
            if (strpos($text, $firm) !== false) {
                return true;
            }
        }

        // PE-related keywords - expanded list
        $pe_keywords = array(
            // Core PE terms
            'private equity', 'venture capital', 'buyout', 'leveraged buyout', 'lbo',
            'management buyout', 'mbo', 'growth equity', 'growth capital',
            // Fund terms
            'portfolio company', 'portco', 'limited partner', 'general partner',
            'lp commit', 'gp stake', 'dry powder', 'fund raising', 'fundraise',
            'fund close', 'first close', 'final close', 'capital call',
            // Deal terms
            'acquisition', 'merger', 'takeover', 'exit', 'ipo', 'trade sale',
            'secondary sale', 'sponsor-to-sponsor', 'club deal', 'co-invest',
            'bolt-on', 'add-on', 'platform', 'carve-out', 'spin-off',
            // Valuation/financial
            'ebitda', 'multiple', 'valuation', 'enterprise value', 'ev',
            'irr', 'moic', 'dpi', 'tvpi', 'j-curve',
            // Structure terms
            'unitranche', 'mezzanine', 'senior debt', 'junior debt',
            'preferred equity', 'common equity', 'rollover', 'management equity',
            // Market terms
            'deal flow', 'pipeline', 'proprietary deal', 'auction process',
            'due diligence', 'exclusivity', 'indicative bid', 'binding offer',
            // Fund types
            'buyout fund', 'growth fund', 'venture fund', 'credit fund',
            'infrastructure fund', 'real estate fund', 'secondaries fund',
            'fund of funds', 'continuation fund', 'gp-led',
        );

        foreach ($pe_keywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get priority level based on ranking score
     */
    private function get_priority_level($score)
    {
        if ($score >= 90) return 'critical';
        if ($score >= 80) return 'high';
        if ($score >= 70) return 'medium';
        return 'low';
    }

    /**
     * Create PE news post
     */
    private function create_pe_news_post($headline, $company_engine)
    {
        if (!$this->is_publishable_pe_headline($headline)) {
            return 0;
        }

        $existing_post_id = 0;

        // Check if already exists (but allow re-creation if old one was cleaned up)
        $existing = get_posts(array(
            'post_type' => 'sffc_pe_news',
            'meta_key' => '_source_url',
            'meta_value' => $headline['link'],
            'posts_per_page' => 1,
            'date_query' => array(
                array(
                    'after' => '5 days ago'  // Only check recent posts
                )
            )
        ));

        if (!empty($existing)) {
            if ($this->has_meaningful_article_content($existing[0]->post_content ?? '')) {
                return $existing[0]->ID;
            }
            $existing_post_id = (int) $existing[0]->ID;
        }

        // Check daily post limit
        $cost_manager = SFFC_API_Cost_Manager::get_instance();
        if ($existing_post_id <= 0 && !$cost_manager->is_within_daily_post_limit()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[SFFC] Daily post limit reached - skipping PE news creation');
            }
            return 0;
        }

        // Extract news info
        $news_info = $this->extract_news_info($headline);
        $article_payload = $this->build_enhanced_article('news', $headline, $news_info);
        if (!is_array($article_payload)) {
            $article_payload = array();
        }

        $original_title = wp_strip_all_tags((string) ($headline['title'] ?? ''));
        $raw_content = !empty($article_payload['content']) ? $article_payload['content'] : $news_info['content'];
        $post_content = $this->sanitize_feed_content($raw_content);
        if (!$this->has_meaningful_article_content($post_content)) {
            return 0;
        }
        $post_excerpt = !empty($article_payload['excerpt']) ? wp_strip_all_tags($article_payload['excerpt']) : wp_trim_words($post_content, 30);

        // Optimize headline for CTR
        $post_title = $this->optimize_headline($original_title, $post_content, array(
            'category' => $news_info['category'],
            'companies' => $news_info['companies'],
            'region' => $news_info['region'],
            'type' => 'news',
        ));

        $meta_input = array(
            '_source_url' => $headline['link'],
            '_source_name' => $headline['source'],
            '_original_title' => $original_title, // Store original for reference
            '_news_category' => $news_info['category'],
            '_companies_mentioned' => json_encode($news_info['companies']),
            '_region' => $news_info['region'],
            '_metrics' => json_encode($news_info['metrics']),
            '_published_date' => $headline['time']
        );

        $meta_input = array_merge($meta_input, $this->build_article_meta($article_payload));

        $post_data = array(
            'post_type' => 'sffc_pe_news',
            'post_title' => $post_title,
            'post_content' => $post_content,
            'post_excerpt' => $post_excerpt,
            'post_status' => 'publish',
            'meta_input' => $meta_input
        );

        if ($existing_post_id > 0) {
            $post_data['ID'] = $existing_post_id;
            $post_id = wp_update_post($post_data);
        } else {
            $post_id = wp_insert_post($post_data);
        }

        if ($post_id && !is_wp_error($post_id)) {
            // Increment daily post counter
            if ($existing_post_id <= 0) {
                $cost_manager->increment_daily_post_count('sffc_pe_news');
            }

            // Link to companies
            $this->link_to_companies($post_id, $news_info['companies'], 'news');

            // Extract and store financial metrics for deal dashboard
            $this->extract_and_store_deal_financials($post_id, $post_content, $post_title);
        }

        return $post_id;
    }

    /**
     * Create market intelligence post
     */
    private function create_market_intel_post($headline, $company_engine)
    {
        if (!$this->is_publishable_pe_headline($headline)) {
            return 0;
        }

        // Check if already exists (but allow re-creation if old one was cleaned up)
        $existing = get_posts(array(
            'post_type' => 'sffc_market_intel',
            'meta_key' => '_source_url',
            'meta_value' => $headline['link'],
            'posts_per_page' => 1,
            'date_query' => array(
                array(
                    'after' => '5 days ago'  // Only check recent posts
                )
            )
        ));

        if (!empty($existing)) {
            return $existing[0]->ID;
        }

        // Check daily post limit
        $cost_manager = SFFC_API_Cost_Manager::get_instance();
        if (!$cost_manager->is_within_daily_post_limit()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[SFFC] Daily post limit reached - skipping market intel creation');
            }
            return 0;
        }

        // Extract market info
        $market_info = $this->extract_market_info($headline);
        $sanitized_content = $this->sanitize_feed_content($market_info['content']);

        // Create post
        $post_id = wp_insert_post(array(
            'post_type' => 'sffc_market_intel',
            'post_title' => wp_strip_all_tags($headline['title']),
            'post_content' => $sanitized_content,
            'post_excerpt' => wp_trim_words($sanitized_content, 30),
            'post_status' => 'publish',
            'meta_input' => array(
                '_source_url' => $headline['link'],
                '_source_name' => $headline['source'],
                '_intel_type' => $market_info['type'],
                '_market_impact' => $market_info['impact'],
                '_sectors_affected' => json_encode($market_info['sectors']),
                '_data_points' => json_encode($market_info['data_points']),
                '_published_date' => $headline['time']
            )
        ));

        if ($post_id && !is_wp_error($post_id)) {
            // Increment daily post counter
            $cost_manager->increment_daily_post_count('sffc_market_intel');
        }

        return $post_id;
    }

    private function build_enhanced_article($type, $headline, $context)
    {
        // Try new dynamic builder first (Fact + Context model with web research)
        if ($this->dynamic_builder) {
            try {
                $payload = $this->dynamic_builder->build($headline, (array) $context);

                if (!empty($payload) && !empty($payload['content'])) {
                    // Add content type info to payload
                    $payload['generation_method'] = 'dynamic_research';
                    return $payload;
                }
            } catch (Throwable $e) {
                error_log('SFFC Dynamic Article Builder failure: ' . $e->getMessage());
                // Fall through to legacy enhancer
            }
        }

        // Fallback to legacy article enhancer
        if ($this->article_enhancer) {
            try {
                $payload = $this->article_enhancer->build($type, $headline, (array) $context);

                if (!empty($payload) && !empty($payload['content'])) {
                    $payload['generation_method'] = 'legacy_enhancer';
                    return $payload;
                }
            } catch (Throwable $e) {
                error_log('SFFC PE Article Enhancer failure: ' . $e->getMessage());
            }
        }

        return null;
    }

    private function build_article_meta($payload)
    {
        if (empty($payload) || !is_array($payload)) {
            return array();
        }

        $meta = array();

        // Generation method (dynamic_research, legacy_enhancer, etc.)
        if (!empty($payload['method'])) {
            $meta['_article_generation_method'] = $payload['method'];
        }
        if (!empty($payload['generation_method'])) {
            $meta['_article_generation_method'] = $payload['generation_method'];
        }

        // Content type from dynamic builder (investment, acquisition, earnings, etc.)
        if (!empty($payload['content_type'])) {
            $meta['_content_type'] = $payload['content_type'];
        }
        if (!empty($payload['content_type_label'])) {
            $meta['_content_type_label'] = $payload['content_type_label'];
        }

        // Entities extracted (companies, people, amounts)
        if (!empty($payload['entities'])) {
            $meta['_article_entities'] = wp_json_encode($payload['entities']);
        }

        if (isset($payload['uniqueness_score'])) {
            $meta['_uniqueness_score'] = $payload['uniqueness_score'];
        }

        // Sections structure for dynamic rendering
        if (!empty($payload['sections'])) {
            $meta['_article_sections'] = wp_json_encode($payload['sections']);
        }

        if (!empty($payload['focus_keywords'])) {
            $keywords = is_array($payload['focus_keywords'])
                ? implode(', ', array_filter($payload['focus_keywords']))
                : $payload['focus_keywords'];
            $meta['_focus_keywords'] = $keywords;
        }

        if (!empty($payload['meta_description'])) {
            $meta['_meta_description'] = $payload['meta_description'];
        }

        if (!empty($payload['excerpt'])) {
            $meta['_generated_excerpt'] = $payload['excerpt'];
        }

        return $meta;
    }

    /**
     * Optimize headline for maximum CTR while maintaining accuracy
     * Uses psychological triggers proven to drive engagement
     *
     * @param string $original_title The source/syndicated title
     * @param string $content The article content
     * @param array $metadata Deal/signal metadata
     * @return string Optimized title
     */
    private function optimize_headline($original_title, $content = '', $metadata = array()) {
        return wp_strip_all_tags((string) $original_title);
    }

    /**
     * Only publish real, source-backed headlines.
     */
    private function is_publishable_pe_headline($headline)
    {
        $title = trim(wp_strip_all_tags((string) ($headline['title'] ?? '')));
        $source = strtolower(trim((string) ($headline['source'] ?? '')));
        $link = trim((string) ($headline['link'] ?? ''));

        if ($title === '' || $link === '') {
            return false;
        }

        $invalid_patterns = array(
            '/\b(sample data|synthetic|mock|demo)\b/i',
            '/\bexits\s+.+\s+investment\s+at\s*$/i',
            '/\bfiles\s+for\s+ipo\s*$/i',
            '/\bleading\s+[£$€]?\d+(?:\.\d+)?[mb]?\s+investment\s+round\s+in\s*$/i',
            '/\bat\s*$/i',
            '/\bfor\s*$/i',
            '/\bwith\s*$/i',
            '/\s{2,}/'
        );

        foreach ($invalid_patterns as $pattern) {
            if (preg_match($pattern, $title)) {
                return false;
            }
        }

        if ($source !== '' && preg_match('/\b(sample data|synthetic|mock|demo)\b/i', $source)) {
            return false;
        }

        return true;
    }

    /**
     * Extract deal information from headline - ENHANCED VERSION
     */
    private function extract_deal_info($headline)
    {
        $body_source = $this->get_headline_body_source($headline);
        $info = array(
            'description' => $body_source,
            'value' => null,
            'type' => 'acquisition',
            'companies' => array(),
            'region' => 'Global',
            'sector' => 'General',
            'deal_stage' => 'completed'
        );

        $text = strtolower($headline['title'] . ' ' . $body_source);
        $original_text = $headline['title'] . ' ' . $body_source;

        // Enhanced value extraction with multiple currencies
        if (preg_match('/[\$£€¥]?\s*([0-9,]+\.?[0-9]*)\s*(billion|million|bn|mn|b|m)/i', $text, $matches)) {
            $info['value'] = $matches[0];
        }

        // Advanced deal type detection with priority keywords
        $deal_patterns = array(
            'acquisition' => array('acquires', 'acquired', 'acquisition', 'buys', 'bought', 'purchase', 'purchased'),
            'buyout' => array('buyout', 'take-private', 'management buyout', 'mbo', 'lbo', 'leveraged buyout'),
            'merger' => array('merger', 'merges', 'merged', 'combine', 'combined', 'consolidation'),
            'investment' => array('invests', 'investment', 'funding', 'backs', 'backed', 'leads round', 'participates'),
            'exit' => array('exit', 'exits', 'sells', 'sold', 'divests', 'divestiture', 'sale to'),
            'ipo' => array('ipo', 'public offering', 'goes public', 'listing', 'float'),
            'stake' => array('majority stake', 'minority stake', 'controlling stake', 'stake in', 'percent stake'),
            'recapitalization' => array('recap', 'recapitalization', 'refinancing', 'dividend recap'),
            'growth' => array('growth equity', 'growth investment', 'growth capital', 'series'),
            're-acquisition' => array('re-acquires', 'reacquires', 'buys back')
        );

        foreach ($deal_patterns as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($text, $keyword) !== false) {
                    $info['type'] = $type;
                    break 2;
                }
            }
        }

        // Deal stage detection (completed, pending, exploring)
        if (
            strpos($text, 'weighs') !== false || strpos($text, 'weighing') !== false ||
            strpos($text, 'considers') !== false || strpos($text, 'exploring') !== false
        ) {
            $info['deal_stage'] = 'exploring';
        } elseif (
            strpos($text, 'agrees') !== false || strpos($text, 'nears') !== false ||
            strpos($text, 'close to') !== false
        ) {
            $info['deal_stage'] = 'pending';
        } elseif (
            strpos($text, 'completes') !== false || strpos($text, 'closes') !== false ||
            strpos($text, 'finalizes') !== false
        ) {
            $info['deal_stage'] = 'completed';
        }

        // Extract companies using comprehensive firm list
        $company_intelligence = SFFC_Company_Intelligence_Engine::get_instance();
        $firms_data = $company_intelligence->get_all_firms();

        foreach ($firms_data as $firm) {
            // Check main name
            if (stripos($text, strtolower($firm['name'])) !== false) {
                $info['companies'][] = $firm['name'];
            }
            // Check aliases
            if (!empty($firm['aliases'])) {
                foreach ($firm['aliases'] as $alias) {
                    if (stripos($text, strtolower($alias)) !== false && !in_array($firm['name'], $info['companies'])) {
                        $info['companies'][] = $firm['name'];
                        break;
                    }
                }
            }
        }

        // Also try to extract target company from common patterns
        $target_patterns = array(
            '/([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*)\s+(?:acquires|buys|invests in|backs)\s+([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*)/i',
            '/([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*)\s+to\s+(?:acquire|buy)\s+([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*)/i',
            '/([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*)\s+(?:sells|exits)\s+([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*)\s+to\s+([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*)/i'
        );

        foreach ($target_patterns as $pattern) {
            if (preg_match($pattern, $original_text, $matches)) {
                for ($i = 1; $i < count($matches); $i++) {
                    if (!in_array($matches[$i], $info['companies'])) {
                        $info['companies'][] = $matches[$i];
                    }
                }
            }
        }

        // Enhanced region extraction
        $regions = array(
            'UK' => array('uk', 'united kingdom', 'britain', 'london', 'british'),
            'EU' => array('europe', 'european', 'eu', 'germany', 'france', 'netherlands', 'spain', 'italy'),
            'US' => array('us', 'u.s.', 'united states', 'america', 'american', 'new york', 'silicon valley'),
            'APAC' => array('asia', 'apac', 'asia-pacific', 'singapore', 'hong kong', 'japan', 'australia'),
            'China' => array('china', 'chinese', 'beijing', 'shanghai', 'shenzhen'),
            'India' => array('india', 'indian', 'mumbai', 'bangalore', 'delhi'),
            'private equity' => array('private equity', 'private_equity', 'dubai', 'saudi', 'uae', 'israel'),
            'LATAM' => array('latin america', 'brazil', 'mexico', 'argentina', 'chile'),
            'Canada' => array('canada', 'canadian', 'toronto', 'montreal')
        );

        foreach ($regions as $region => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($text, $keyword) !== false) {
                    $info['region'] = $region;
                    break 2;
                }
            }
        }

        // Enhanced sector extraction
        $sectors = array(
            'Technology' => array('tech', 'software', 'saas', 'digital', 'cloud', 'ai', 'artificial intelligence', 'cybersecurity', 'data'),
            'Healthcare' => array('health', 'medical', 'pharma', 'biotech', 'hospital', 'clinical', 'therapeutics', 'diagnostics'),
            'Financial Services' => array('financial', 'fintech', 'banking', 'insurance', 'payments', 'lending', 'wealth management'),
            'Consumer' => array('retail', 'consumer', 'ecommerce', 'brands', 'cpg', 'food', 'beverage', 'restaurant'),
            'Infrastructure' => array('infrastructure', 'utilities', 'telecom', 'transportation', 'logistics'),
            'Energy' => array('energy', 'oil', 'gas', 'renewable', 'solar', 'wind', 'power', 'clean energy'),
            'Real Estate' => array('real estate', 'property', 'reit', 'commercial real estate', 'residential'),
            'Industrial' => array('industrial', 'manufacturing', 'aerospace', 'defense', 'chemicals', 'machinery'),
            'Media & Entertainment' => array('media', 'entertainment', 'gaming', 'content', 'streaming', 'advertising'),
            'Education' => array('education', 'edtech', 'learning', 'university', 'training', 'school')
        );

        foreach ($sectors as $sector => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($text, $keyword) !== false) {
                    $info['sector'] = $sector;
                    break 2;
                }
            }
        }

        return $info;
    }

    /**
     * Extract news information - ENHANCED VERSION
     */
    private function extract_news_info($headline)
    {
        $body_source = $this->get_headline_body_source($headline);
        $info = array(
            'content' => $body_source,
            'category' => 'market',
            'companies' => array(),
            'region' => 'Global',
            'metrics' => array(),
            'sentiment' => 'neutral'
        );

        $text = strtolower($headline['title'] . ' ' . $body_source);
        $original_text = $headline['title'] . ' ' . $body_source;

        // Enhanced news categorization
        $categories = array(
            'fundraising' => array('fund', 'raise', 'raising', 'closes fund', 'capital raise', 'fundraise', 'closes on'),
            'people' => array('appoint', 'hire', 'hires', 'join', 'joins', 'promote', 'departure', 'leaves', 'names', 'taps'),
            'regulatory' => array('regulation', 'sec', 'ftc', 'doj', 'compliance', 'antitrust', 'investigation', 'probe'),
            'market' => array('market', 'economy', 'recession', 'inflation', 'rates', 'fed', 'economic'),
            'portfolio' => array('portfolio company', 'portco', 'platform', 'add-on', 'bolt-on'),
            'analysis' => array('outlook', 'forecast', 'trend', 'report', 'survey', 'study'),
            'performance' => array('returns', 'performance', 'irr', 'multiple', 'valuation', 'markup')
        );

        foreach ($categories as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($text, $keyword) !== false) {
                    $info['category'] = $category;
                    break 2;
                }
            }
        }

        // Extract companies using comprehensive firm list
        $company_intelligence = SFFC_Company_Intelligence_Engine::get_instance();
        $firms_data = $company_intelligence->get_all_firms();

        foreach ($firms_data as $firm) {
            // Check main name
            if (stripos($text, strtolower($firm['name'])) !== false) {
                $info['companies'][] = $firm['name'];
            }
            // Check aliases
            if (!empty($firm['aliases'])) {
                foreach ($firm['aliases'] as $alias) {
                    if (stripos($text, strtolower($alias)) !== false && !in_array($firm['name'], $info['companies'])) {
                        $info['companies'][] = $firm['name'];
                        break;
                    }
                }
            }
        }

        // Enhanced metrics extraction
        // Percentages
        if (preg_match_all('/([+-]?[0-9]+\.?[0-9]*)\s*%/', $text, $matches)) {
            foreach ($matches[1] as $percent) {
                $info['metrics'][] = array('type' => 'percentage', 'value' => $percent . '%');
            }
        }

        // Dollar amounts
        if (preg_match_all('/[\$£€]([0-9,]+\.?[0-9]*)\s*(billion|million|bn|mn|b|m)?/i', $text, $matches)) {
            for ($i = 0; $i < count($matches[0]); $i++) {
                $info['metrics'][] = array(
                    'type' => 'currency',
                    'value' => $matches[0][$i]
                );
            }
        }

        // Multiples (e.g., 3x, 10x)
        if (preg_match_all('/([0-9]+\.?[0-9]*)[xX]/', $text, $matches)) {
            foreach ($matches[1] as $multiple) {
                $info['metrics'][] = array('type' => 'multiple', 'value' => $multiple . 'x');
            }
        }

        // Sentiment analysis
        $positive_words = array('surge', 'soar', 'rise', 'gain', 'growth', 'record', 'strong', 'outperform', 'beat');
        $negative_words = array('fall', 'drop', 'decline', 'loss', 'weak', 'concern', 'risk', 'challenge', 'miss');

        $positive_count = 0;
        $negative_count = 0;

        foreach ($positive_words as $word) {
            if (strpos($text, $word) !== false) $positive_count++;
        }

        foreach ($negative_words as $word) {
            if (strpos($text, $word) !== false) $negative_count++;
        }

        if ($positive_count > $negative_count) {
            $info['sentiment'] = 'positive';
        } elseif ($negative_count > $positive_count) {
            $info['sentiment'] = 'negative';
        }

        return $info;
    }

    /**
     * Extract market information
     */
    private function extract_market_info($headline)
    {
        $info = array(
            'content' => isset($headline['title']) ? $headline['title'] : '',
            'type' => 'general',
            'impact' => 'medium',
            'sectors' => array(),
            'data_points' => array()
        );

        $text = strtolower($headline['title']);

        // Determine type
        if (strpos($text, 'earnings') !== false) {
            $info['type'] = 'earnings';
        } elseif (strpos($text, 'economic') !== false) {
            $info['type'] = 'economic';
        } elseif (strpos($text, 'sector') !== false) {
            $info['type'] = 'sector_analysis';
        }

        // Assess impact
        if (strpos($text, 'surge') !== false || strpos($text, 'soar') !== false || strpos($text, 'plunge') !== false) {
            $info['impact'] = 'high';
        } elseif (strpos($text, 'slight') !== false || strpos($text, 'modest') !== false) {
            $info['impact'] = 'low';
        }

        return $info;
    }

    /**
     * Link content to companies
     */
    private function link_to_companies($post_id, $companies, $type)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sffc_company_news_links';

        foreach ($companies as $company_name) {
            // Find company post
            $query_args = array(
                'post_type' => 'sffc_company',
                'posts_per_page' => 1
            );

            if (class_exists('SFFC_Company_Title_Helper')) {
                $candidates = array($company_name);
                $stripped = SFFC_Company_Title_Helper::strip_seo_suffix($company_name);
                if ($stripped && !in_array($stripped, $candidates, true)) {
                    $candidates[] = $stripped;
                }

                $query_args['meta_query'] = array(
                    array(
                        'key' => SFFC_Company_Title_Helper::META_CANONICAL_NAME,
                        'value' => $candidates,
                        'compare' => 'IN'
                    )
                );
            } else {
                $query_args['title'] = $company_name;
            }

            $company_posts = get_posts($query_args);

            if (!empty($company_posts)) {
                $wpdb->insert(
                    $table_name,
                    array(
                        'company_id' => $company_posts[0]->ID,
                        'news_item_id' => $post_id,
                        'relevance_score' => 100,
                        'matched_terms' => json_encode(array($company_name))
                    ),
                    array('%d', '%d', '%f', '%s')
                );
            }
        }
    }

    /**
     * Get PE companies list
     */
    private function get_pe_companies()
    {
        $companies = array();

        // Get from Company Intelligence Engine comprehensive list
        if (class_exists('SFFC_Company_Intelligence_Engine')) {
            $company_intelligence = SFFC_Company_Intelligence_Engine::get_instance();
            $firms_data = $company_intelligence->get_all_firms();

            foreach ($firms_data as $firm_slug => $firm) {
                $companies[] = array(
                    'name' => $firm['name'],
                    'ticker' => '' // PE firms typically don't have tickers unless public
                );
            }
        }

        // Fallback to posts if needed
        if (empty($companies)) {
            $posts = get_posts(array(
                'post_type' => 'sffc_company',
                'posts_per_page' => -1
            ));

            foreach ($posts as $post) {
                $companies[] = array(
                    'name' => $post->post_title,
                    'ticker' => get_post_meta($post->ID, '_sffc_ticker', true)
                );
            }
        }

        return $companies;
    }

    /**
     * AJAX: Process feeds manually
     */
    public function ajax_process_feeds()
    {
        check_ajax_referer('sffc_intelligence_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $this->process_feed_content();

        // Get counts
        $pe_news_count = wp_count_posts('sffc_pe_news')->publish;
        $pe_deals_count = wp_count_posts('sffc_pe_deal')->publish;
        $market_intel_count = wp_count_posts('sffc_market_intel')->publish;
        $pe_signals_count = wp_count_posts('sffc_pe_signal')->publish;

        wp_send_json_success(array(
            'message' => 'PE feeds processed successfully',
            'stats' => array(
                'pe_news' => $pe_news_count,
                'pe_deals' => $pe_deals_count,
                'market_intel' => $market_intel_count,
                'pe_signals' => $pe_signals_count,
                'timestamp' => current_time('mysql')
            )
        ));
    }

    /**
     * Get recent PE content for cards - PRIORITIZES FRESH CONTENT
     */
    public function get_pe_content_cards($limit = 12, $type = 'all', $filters = array())
    {
        $cards = array();

        // Determine post types to query
        $post_types = array();
        if ($type === 'all' || $type === 'news') {
            $post_types[] = 'sffc_pe_news';
        }
        if ($type === 'all' || $type === 'deals') {
            $post_types[] = 'sffc_pe_deal';
        }
        if ($type === 'all' || $type === 'market') {
            $post_types[] = 'sffc_market_intel';
        }
        if ($type === 'all' || $type === 'signals') {
            $post_types[] = 'sffc_pe_signal';
        }

        // Query posts - PRIORITIZE FRESH CONTENT (last 48 hours)
        // First try to get fresh content from the last 48 hours
        $fresh_posts = get_posts(array(
            'post_type' => $post_types,
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
            'date_query' => array(
                array(
                    'after' => '48 hours ago'
                )
            )
        ));

        $cards = array();
        foreach ($fresh_posts as $post) {
            $cards[] = $this->format_post_as_card($post);
        }

        // If we don't have enough fresh content, get older content to fill
        if (count($cards) < $limit) {
            $older_posts = get_posts(array(
                'post_type' => $post_types,
                'posts_per_page' => $limit - count($cards),
                'orderby' => 'date',
                'order' => 'DESC',
                'date_query' => array(
                    array(
                        'before' => '48 hours ago',
                        'after' => '7 days ago'
                    )
                )
            ));

            foreach ($older_posts as $post) {
                $card = $this->format_post_as_card($post);
                // Mark older content
                $card['is_older'] = true;
                $cards[] = $card;
            }
        }

        return $cards;
    }

    /**
     * Format post as card data
     */
    private function format_post_as_card($post)
    {
        $card = array(
            'id' => $post->post_type . '-' . $post->ID,
            'type' => $this->get_card_type($post->post_type),
            'title' => $post->post_title,
            'summary' => wp_trim_words($post->post_content, 30),
            'source' => get_post_meta($post->ID, '_source_name', true) ?: 'Market Intelligence',
            'source_url' => get_post_meta($post->ID, '_source_url', true) ?: '#',
            'time_ago' => human_time_diff(get_post_time('U', true, $post), current_time('timestamp')) . ' ago',
            'timestamp' => get_post_time('U', true, $post)
        );

        // Add type-specific data
        if ($post->post_type === 'sffc_pe_deal') {
            $card['company'] = $this->extract_company_from_title($post->post_title);
            $card['region'] = get_post_meta($post->ID, '_region', true) ?: 'Global';
            $card['metrics'] = array(
                array('label' => 'Deal Value', 'value' => get_post_meta($post->ID, '_deal_value', true) ?: 'Undisclosed', 'highlight' => true),
                array('label' => 'Type', 'value' => ucfirst(get_post_meta($post->ID, '_deal_type', true) ?: 'acquisition'), 'highlight' => false),
                array('label' => 'Sector', 'value' => get_post_meta($post->ID, '_sector', true) ?: 'General', 'highlight' => false)
            );
            $card['ai_insight'] = 'Strategic ' . get_post_meta($post->ID, '_deal_type', true) . ' in ' . get_post_meta($post->ID, '_sector', true) . ' sector';
        } elseif ($post->post_type === 'sffc_pe_news') {
            $companies = json_decode(get_post_meta($post->ID, '_companies_mentioned', true), true);
            $card['company'] = !empty($companies) ? $companies[0] : 'Market Update';
            $card['region'] = get_post_meta($post->ID, '_region', true) ?: 'Global';
            $metrics = json_decode(get_post_meta($post->ID, '_metrics', true), true);
            $card['metrics'] = !empty($metrics) ? $metrics : array();
            $card['ai_insight'] = $this->generate_news_insight($post);
        } elseif ($post->post_type === 'sffc_market_intel') {
            $card['company'] = 'Market Analysis';
            $card['region'] = 'Global';
            $card['metrics'] = array(
                array('label' => 'Impact', 'value' => ucfirst(get_post_meta($post->ID, '_market_impact', true) ?: 'medium'), 'highlight' => true)
            );
            $card['ai_insight'] = 'Market intelligence update';
        } elseif ($post->post_type === 'sffc_pe_signal') {
            $card['company'] = 'Market News';
            $card['region'] = 'Global';
            $signal_type = get_post_meta($post->ID, '_signal_type', true);
            // REVISED: Use factual labels, not sensational terms
            $type_labels = array(
                'regulatory_action' => 'Regulatory',
                'bankruptcy_filing' => 'Bankruptcy',
                'business_failure' => 'Business Update',
                'deal_activity' => 'Deal Activity',
                'market_activity' => 'Market Activity',
                'market_update' => 'Market Update',
            );
            $type_label = isset($type_labels[$signal_type]) ? $type_labels[$signal_type] : 'News';

            $card['metrics'] = array(
                array('label' => 'Category', 'value' => $type_label, 'highlight' => false),
                array('label' => 'Source', 'value' => get_post_meta($post->ID, '_source_name', true) ?: 'Market Feed', 'highlight' => false)
            );
            // REMOVED: "High-ranking potential" and "impact" language - this was sensationalized
            $card['ai_insight'] = $type_label . ' - see source for full details';
            $card['priority'] = false; // REMOVED: Priority flagging created false urgency
        }

        return $card;
    }

    /**
     * Get card type from post type
     */
    private function get_card_type($post_type)
    {
        switch ($post_type) {
            case 'sffc_pe_deal':
                return 'deal';
            case 'sffc_pe_news':
                return 'news';
            case 'sffc_market_intel':
                return 'market';
            case 'sffc_pe_signal':
                return 'signal';
            default:
                return 'general';
        }
    }

    /**
     * Extract company from title
     */
    private function extract_company_from_title($title)
    {
        $pe_firms = array('KKR', 'Blackstone', 'Apollo', 'Carlyle', 'TPG', 'Warburg Pincus', 'Bain Capital', 'Advent');

        foreach ($pe_firms as $firm) {
            if (stripos($title, $firm) !== false) {
                return $firm;
            }
        }

        return 'Private Equity';
    }

    /**
     * Generate news insight
     */
    private function generate_news_insight($post)
    {
        $category = get_post_meta($post->ID, '_news_category', true);

        $insights = array(
            'fundraising' => 'New capital deployment opportunities ahead',
            'people' => 'Leadership changes signal strategic shifts',
            'regulatory' => 'Regulatory developments may impact deal flow',
            'market' => 'Market conditions affecting investment strategies'
        );

        return isset($insights[$category]) ? $insights[$category] : 'Significant development in PE sector';
    }

    /**
     * Cleanup old PE content - removes posts older than 5 days
     * Scheduled to run on Sundays at 3 AM
     */
    public function cleanup_old_content()
    {
        $cleanup_date = date('Y-m-d H:i:s', strtotime('-5 days'));
        $deleted_count = 0;

        // Define post types to clean
        $post_types = array('sffc_pe_news', 'sffc_pe_deal', 'sffc_market_intel', 'sffc_pe_signal');

        foreach ($post_types as $post_type) {
            // Get old posts
            $old_posts = get_posts(array(
                'post_type' => $post_type,
                'posts_per_page' => -1,
                'post_status' => 'any',
                'date_query' => array(
                    array(
                        'before' => $cleanup_date,
                        'inclusive' => false
                    )
                )
            ));

            // Delete old posts
            foreach ($old_posts as $post) {
                // Skip if it's linked to important company data
                $companies = json_decode(get_post_meta($post->ID, '_companies_mentioned', true), true);
                $is_important = false;

                // Keep posts about top firms for a bit longer (7 days)
                $top_firms = array('KKR', 'Blackstone', 'Apollo', 'Carlyle');
                if (!empty($companies)) {
                    foreach ($top_firms as $firm) {
                        if (in_array(strtolower($firm), array_map('strtolower', $companies))) {
                            $post_age = (time() - get_post_time('U', true, $post)) / DAY_IN_SECONDS;
                            if ($post_age < 7) {
                                $is_important = true;
                                break;
                            }
                        }
                    }
                }

                if (!$is_important) {
                    wp_delete_post($post->ID, true);
                    $deleted_count++;
                }
            }
        }

        // Clean up orphaned company news links
        $this->cleanup_orphaned_links();

        // Log cleanup
        update_option('sffc_last_pe_cleanup', array(
            'time' => current_time('mysql'),
            'deleted' => $deleted_count
        ));

        return $deleted_count;
    }

    /**
     * Clean up orphaned company news links
     */
    private function cleanup_orphaned_links()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sffc_company_news_links';

        // Delete links where the news post no longer exists
        $wpdb->query(
            "DELETE cnl FROM $table_name cnl
            LEFT JOIN {$wpdb->posts} p ON cnl.news_item_id = p.ID
            WHERE p.ID IS NULL"
        );

        // Delete old links (older than 5 days)
        $cleanup_date = date('Y-m-d H:i:s', strtotime('-5 days'));
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE created_at < %s",
            $cleanup_date
        ));
    }

    /**
     * AJAX handler for manual cleanup
     */
    public function ajax_cleanup_content()
    {
        check_ajax_referer('sffc_intelligence_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $deleted = $this->cleanup_old_content();

        wp_send_json_success(array(
            'message' => sprintf('Cleanup complete: %d old posts removed', $deleted),
            'deleted' => $deleted,
            'timestamp' => current_time('mysql')
        ));
    }

    /**
     * Get cleanup status
     */
    public function get_cleanup_status()
    {
        $last_cleanup = get_option('sffc_last_pe_cleanup', array());
        $next_cleanup = wp_next_scheduled('sffc_cleanup_old_pe_content');

        $next_run = 'Auto cleanup disabled';
        if ($this->auto_cleanup_enabled) {
            $next_run = $next_cleanup ? date('Y-m-d H:i:s', $next_cleanup) : 'Not scheduled';
        }

        return array(
            'last_run' => isset($last_cleanup['time']) ? $last_cleanup['time'] : 'Never',
            'last_deleted' => isset($last_cleanup['deleted']) ? $last_cleanup['deleted'] : 0,
            'next_run' => $next_run,
            'retention_days' => 5,
            'auto_cleanup_enabled' => $this->auto_cleanup_enabled
        );
    }

    /**
     * Helper to expose whether automatic cleanup is active
     */
    public function is_auto_cleanup_enabled()
    {
        return $this->auto_cleanup_enabled;
    }
}

// Disabled at bootstrap.
