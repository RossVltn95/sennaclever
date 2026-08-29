<?php
/**
 * Relocation Pages - Programmatic SEO System
 *
 * Generates "Moving to [Location] from [Location]" pages for SEO.
 * Integrates with Claude for one-time location brief generation.
 *
 * @package SFFC_Careers
 * @since 11.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Relocation_Pages {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Custom post type name
     */
    const POST_TYPE = 'sffc_relocation';

    /**
     * Mobility data service instance
     */
    private $mobility_service = null;

    /**
     * Location data with countries and top cities
     */
    private $locations = array();

    /**
     * Common relocation routes (high-intent combinations)
     */
    private $popular_routes = array();

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
        $this->init_location_data();
        $this->init_popular_routes();

        // Register post type with high priority to ensure it loads early (like PE post types)
        add_action('init', array($this, 'register_post_type'), 0);
        add_action('init', array($this, 'register_rewrite_rules'), 0);
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_filter('template_include', array($this, 'load_relocation_template'));
        add_action('wp_ajax_sffc_generate_location_brief', array($this, 'ajax_generate_location_brief'));
        add_action('wp_ajax_sffc_bulk_generate_pages', array($this, 'ajax_bulk_generate_pages'));

        // Admin menu for managing relocation pages
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Newsletter subscription handler
        add_action('wp_ajax_sffc_newsletter_subscribe', array($this, 'ajax_newsletter_subscribe'));
        add_action('wp_ajax_nopriv_sffc_newsletter_subscribe', array($this, 'ajax_newsletter_subscribe'));

        // Fix page titles
        add_filter('get_the_archive_title', array($this, 'filter_archive_title'));
        add_filter('post_type_archive_title', array($this, 'filter_archive_title'));
        add_filter('pre_get_document_title', array($this, 'filter_document_title'), 20);
        add_filter('document_title_parts', array($this, 'filter_document_title_parts'), 20);
        add_filter('wp_title', array($this, 'filter_wp_title'), 20, 2);

        // Get mobility service
        if (class_exists('SFFC_Mobility_Data_Service')) {
            $this->mobility_service = SFFC_Mobility_Data_Service::get_instance();
        }
    }

    /**
     * Initialize location data with countries and their top 3 cities
     */
    private function init_location_data() {
        $this->locations = array(
            'united-kingdom' => array(
                'name' => 'United Kingdom',
                'display_name' => 'the UK',
                'code' => 'GB',
                'currency' => 'GBP',
                'cities' => array(
                    'london' => array('name' => 'London', 'slug' => 'london'),
                    'manchester' => array('name' => 'Manchester', 'slug' => 'manchester'),
                    'edinburgh' => array('name' => 'Edinburgh', 'slug' => 'edinburgh'),
                ),
            ),
            'united-arab-emirates' => array(
                'name' => 'United Arab Emirates',
                'display_name' => 'the UAE',
                'code' => 'AE',
                'currency' => 'AED',
                'aliases' => array('dubai', 'uae'), // Dubai is often used as country reference
                'cities' => array(
                    'dubai' => array('name' => 'Dubai', 'slug' => 'dubai', 'is_primary' => true),
                    'abu-dhabi' => array('name' => 'Abu Dhabi', 'slug' => 'abu-dhabi'),
                    'sharjah' => array('name' => 'Sharjah', 'slug' => 'sharjah'),
                ),
            ),
            'united-states' => array(
                'name' => 'United States',
                'display_name' => 'the US',
                'code' => 'US',
                'currency' => 'USD',
                'cities' => array(
                    'new-york' => array('name' => 'New York', 'slug' => 'new-york'),
                    'san-francisco' => array('name' => 'San Francisco', 'slug' => 'san-francisco'),
                    'chicago' => array('name' => 'Chicago', 'slug' => 'chicago'),
                ),
            ),
            'germany' => array(
                'name' => 'Germany',
                'display_name' => 'Germany',
                'code' => 'DE',
                'currency' => 'EUR',
                'cities' => array(
                    'frankfurt' => array('name' => 'Frankfurt', 'slug' => 'frankfurt'),
                    'munich' => array('name' => 'Munich', 'slug' => 'munich'),
                    'berlin' => array('name' => 'Berlin', 'slug' => 'berlin'),
                ),
            ),
            'france' => array(
                'name' => 'France',
                'display_name' => 'France',
                'code' => 'FR',
                'currency' => 'EUR',
                'cities' => array(
                    'paris' => array('name' => 'Paris', 'slug' => 'paris'),
                    'lyon' => array('name' => 'Lyon', 'slug' => 'lyon'),
                    'marseille' => array('name' => 'Marseille', 'slug' => 'marseille'),
                ),
            ),
            'italy' => array(
                'name' => 'Italy',
                'display_name' => 'Italy',
                'code' => 'IT',
                'currency' => 'EUR',
                'cities' => array(
                    'milan' => array('name' => 'Milan', 'slug' => 'milan'),
                    'rome' => array('name' => 'Rome', 'slug' => 'rome'),
                    'turin' => array('name' => 'Turin', 'slug' => 'turin'),
                ),
            ),
            'spain' => array(
                'name' => 'Spain',
                'display_name' => 'Spain',
                'code' => 'ES',
                'currency' => 'EUR',
                'cities' => array(
                    'madrid' => array('name' => 'Madrid', 'slug' => 'madrid'),
                    'barcelona' => array('name' => 'Barcelona', 'slug' => 'barcelona'),
                    'valencia' => array('name' => 'Valencia', 'slug' => 'valencia'),
                ),
            ),
            'netherlands' => array(
                'name' => 'Netherlands',
                'display_name' => 'the Netherlands',
                'code' => 'NL',
                'currency' => 'EUR',
                'cities' => array(
                    'amsterdam' => array('name' => 'Amsterdam', 'slug' => 'amsterdam'),
                    'rotterdam' => array('name' => 'Rotterdam', 'slug' => 'rotterdam'),
                    'the-hague' => array('name' => 'The Hague', 'slug' => 'the-hague'),
                ),
            ),
            'switzerland' => array(
                'name' => 'Switzerland',
                'display_name' => 'Switzerland',
                'code' => 'CH',
                'currency' => 'CHF',
                'cities' => array(
                    'zurich' => array('name' => 'Zurich', 'slug' => 'zurich'),
                    'geneva' => array('name' => 'Geneva', 'slug' => 'geneva'),
                    'basel' => array('name' => 'Basel', 'slug' => 'basel'),
                ),
            ),
            'singapore' => array(
                'name' => 'Singapore',
                'display_name' => 'Singapore',
                'code' => 'SG',
                'currency' => 'SGD',
                'is_city_state' => true,
                'cities' => array(
                    'singapore' => array('name' => 'Singapore', 'slug' => 'singapore', 'is_primary' => true),
                ),
            ),
            'hong-kong' => array(
                'name' => 'Hong Kong',
                'display_name' => 'Hong Kong',
                'code' => 'HK',
                'currency' => 'HKD',
                'is_city_state' => true,
                'cities' => array(
                    'hong-kong' => array('name' => 'Hong Kong', 'slug' => 'hong-kong', 'is_primary' => true),
                ),
            ),
            'australia' => array(
                'name' => 'Australia',
                'display_name' => 'Australia',
                'code' => 'AU',
                'currency' => 'AUD',
                'cities' => array(
                    'sydney' => array('name' => 'Sydney', 'slug' => 'sydney'),
                    'melbourne' => array('name' => 'Melbourne', 'slug' => 'melbourne'),
                    'brisbane' => array('name' => 'Brisbane', 'slug' => 'brisbane'),
                ),
            ),
            'canada' => array(
                'name' => 'Canada',
                'display_name' => 'Canada',
                'code' => 'CA',
                'currency' => 'CAD',
                'cities' => array(
                    'toronto' => array('name' => 'Toronto', 'slug' => 'toronto'),
                    'vancouver' => array('name' => 'Vancouver', 'slug' => 'vancouver'),
                    'montreal' => array('name' => 'Montreal', 'slug' => 'montreal'),
                ),
            ),
            'brazil' => array(
                'name' => 'Brazil',
                'display_name' => 'Brazil',
                'code' => 'BR',
                'currency' => 'BRL',
                'cities' => array(
                    'sao-paulo' => array('name' => 'São Paulo', 'slug' => 'sao-paulo'),
                    'rio-de-janeiro' => array('name' => 'Rio de Janeiro', 'slug' => 'rio-de-janeiro'),
                    'brasilia' => array('name' => 'Brasília', 'slug' => 'brasilia'),
                ),
            ),
            'south-africa' => array(
                'name' => 'South Africa',
                'display_name' => 'South Africa',
                'code' => 'ZA',
                'currency' => 'ZAR',
                'cities' => array(
                    'johannesburg' => array('name' => 'Johannesburg', 'slug' => 'johannesburg'),
                    'cape-town' => array('name' => 'Cape Town', 'slug' => 'cape-town'),
                    'durban' => array('name' => 'Durban', 'slug' => 'durban'),
                ),
            ),
            'india' => array(
                'name' => 'India',
                'display_name' => 'India',
                'code' => 'IN',
                'currency' => 'INR',
                'cities' => array(
                    'mumbai' => array('name' => 'Mumbai', 'slug' => 'mumbai'),
                    'bangalore' => array('name' => 'Bangalore', 'slug' => 'bangalore'),
                    'delhi' => array('name' => 'Delhi', 'slug' => 'delhi'),
                ),
            ),
            'ireland' => array(
                'name' => 'Ireland',
                'display_name' => 'Ireland',
                'code' => 'IE',
                'currency' => 'EUR',
                'cities' => array(
                    'dublin' => array('name' => 'Dublin', 'slug' => 'dublin'),
                    'cork' => array('name' => 'Cork', 'slug' => 'cork'),
                    'galway' => array('name' => 'Galway', 'slug' => 'galway'),
                ),
            ),
            'portugal' => array(
                'name' => 'Portugal',
                'display_name' => 'Portugal',
                'code' => 'PT',
                'currency' => 'EUR',
                'cities' => array(
                    'lisbon' => array('name' => 'Lisbon', 'slug' => 'lisbon'),
                    'porto' => array('name' => 'Porto', 'slug' => 'porto'),
                    'faro' => array('name' => 'Faro', 'slug' => 'faro'),
                ),
            ),
            'sweden' => array(
                'name' => 'Sweden',
                'display_name' => 'Sweden',
                'code' => 'SE',
                'currency' => 'SEK',
                'cities' => array(
                    'stockholm' => array('name' => 'Stockholm', 'slug' => 'stockholm'),
                    'gothenburg' => array('name' => 'Gothenburg', 'slug' => 'gothenburg'),
                    'malmo' => array('name' => 'Malmö', 'slug' => 'malmo'),
                ),
            ),
            'denmark' => array(
                'name' => 'Denmark',
                'display_name' => 'Denmark',
                'code' => 'DK',
                'currency' => 'DKK',
                'cities' => array(
                    'copenhagen' => array('name' => 'Copenhagen', 'slug' => 'copenhagen'),
                    'aarhus' => array('name' => 'Aarhus', 'slug' => 'aarhus'),
                    'odense' => array('name' => 'Odense', 'slug' => 'odense'),
                ),
            ),
            'norway' => array(
                'name' => 'Norway',
                'display_name' => 'Norway',
                'code' => 'NO',
                'currency' => 'NOK',
                'cities' => array(
                    'oslo' => array('name' => 'Oslo', 'slug' => 'oslo'),
                    'bergen' => array('name' => 'Bergen', 'slug' => 'bergen'),
                    'trondheim' => array('name' => 'Trondheim', 'slug' => 'trondheim'),
                ),
            ),
            'japan' => array(
                'name' => 'Japan',
                'display_name' => 'Japan',
                'code' => 'JP',
                'currency' => 'JPY',
                'cities' => array(
                    'tokyo' => array('name' => 'Tokyo', 'slug' => 'tokyo'),
                    'osaka' => array('name' => 'Osaka', 'slug' => 'osaka'),
                    'yokohama' => array('name' => 'Yokohama', 'slug' => 'yokohama'),
                ),
            ),
            'luxembourg' => array(
                'name' => 'Luxembourg',
                'display_name' => 'Luxembourg',
                'code' => 'LU',
                'currency' => 'EUR',
                'is_city_state' => true,
                'cities' => array(
                    'luxembourg' => array('name' => 'Luxembourg City', 'slug' => 'luxembourg', 'is_primary' => true),
                ),
            ),
        );
    }

    /**
     * Initialize popular relocation routes
     * These are high-intent, commonly searched combinations
     */
    private function init_popular_routes() {
        $this->popular_routes = array(
            // From UK
            array('from' => 'london', 'to' => 'dubai', 'type' => 'city'),
            array('from' => 'london', 'to' => 'singapore', 'type' => 'city'),
            array('from' => 'london', 'to' => 'hong-kong', 'type' => 'city'),
            array('from' => 'london', 'to' => 'new-york', 'type' => 'city'),
            array('from' => 'london', 'to' => 'zurich', 'type' => 'city'),
            array('from' => 'london', 'to' => 'amsterdam', 'type' => 'city'),
            array('from' => 'london', 'to' => 'paris', 'type' => 'city'),
            array('from' => 'united-kingdom', 'to' => 'united-arab-emirates', 'type' => 'country'),
            array('from' => 'united-kingdom', 'to' => 'singapore', 'type' => 'country'),
            array('from' => 'united-kingdom', 'to' => 'australia', 'type' => 'country'),

            // From Italy
            array('from' => 'milan', 'to' => 'london', 'type' => 'city'),
            array('from' => 'milan', 'to' => 'dubai', 'type' => 'city'),
            array('from' => 'milan', 'to' => 'zurich', 'type' => 'city'),
            array('from' => 'italy', 'to' => 'united-kingdom', 'type' => 'country'),
            array('from' => 'italy', 'to' => 'switzerland', 'type' => 'country'),

            // From Germany
            array('from' => 'frankfurt', 'to' => 'london', 'type' => 'city'),
            array('from' => 'frankfurt', 'to' => 'zurich', 'type' => 'city'),
            array('from' => 'munich', 'to' => 'london', 'type' => 'city'),
            array('from' => 'germany', 'to' => 'united-kingdom', 'type' => 'country'),
            array('from' => 'germany', 'to' => 'switzerland', 'type' => 'country'),

            // From France
            array('from' => 'paris', 'to' => 'london', 'type' => 'city'),
            array('from' => 'paris', 'to' => 'dubai', 'type' => 'city'),
            array('from' => 'paris', 'to' => 'singapore', 'type' => 'city'),
            array('from' => 'france', 'to' => 'united-kingdom', 'type' => 'country'),
            array('from' => 'france', 'to' => 'united-arab-emirates', 'type' => 'country'),

            // From South Africa
            array('from' => 'johannesburg', 'to' => 'dubai', 'type' => 'city'),
            array('from' => 'johannesburg', 'to' => 'london', 'type' => 'city'),
            array('from' => 'cape-town', 'to' => 'london', 'type' => 'city'),
            array('from' => 'south-africa', 'to' => 'united-arab-emirates', 'type' => 'country'),
            array('from' => 'south-africa', 'to' => 'united-kingdom', 'type' => 'country'),
            array('from' => 'south-africa', 'to' => 'australia', 'type' => 'country'),

            // From Brazil
            array('from' => 'sao-paulo', 'to' => 'london', 'type' => 'city'),
            array('from' => 'sao-paulo', 'to' => 'new-york', 'type' => 'city'),
            array('from' => 'sao-paulo', 'to' => 'miami', 'type' => 'city'),
            array('from' => 'rio-de-janeiro', 'to' => 'sao-paulo', 'type' => 'city'),
            array('from' => 'brazil', 'to' => 'united-states', 'type' => 'country'),
            array('from' => 'brazil', 'to' => 'portugal', 'type' => 'country'),

            // From India
            array('from' => 'mumbai', 'to' => 'dubai', 'type' => 'city'),
            array('from' => 'mumbai', 'to' => 'singapore', 'type' => 'city'),
            array('from' => 'bangalore', 'to' => 'london', 'type' => 'city'),
            array('from' => 'india', 'to' => 'united-arab-emirates', 'type' => 'country'),
            array('from' => 'india', 'to' => 'singapore', 'type' => 'country'),
            array('from' => 'india', 'to' => 'united-kingdom', 'type' => 'country'),

            // From Spain
            array('from' => 'madrid', 'to' => 'london', 'type' => 'city'),
            array('from' => 'barcelona', 'to' => 'london', 'type' => 'city'),
            array('from' => 'spain', 'to' => 'united-kingdom', 'type' => 'country'),

            // From Netherlands
            array('from' => 'amsterdam', 'to' => 'london', 'type' => 'city'),
            array('from' => 'amsterdam', 'to' => 'dubai', 'type' => 'city'),
            array('from' => 'netherlands', 'to' => 'united-kingdom', 'type' => 'country'),

            // From Australia
            array('from' => 'sydney', 'to' => 'london', 'type' => 'city'),
            array('from' => 'sydney', 'to' => 'singapore', 'type' => 'city'),
            array('from' => 'melbourne', 'to' => 'london', 'type' => 'city'),
            array('from' => 'australia', 'to' => 'united-kingdom', 'type' => 'country'),
            array('from' => 'australia', 'to' => 'singapore', 'type' => 'country'),

            // From Singapore/Hong Kong (inter-Asia)
            array('from' => 'singapore', 'to' => 'hong-kong', 'type' => 'city'),
            array('from' => 'hong-kong', 'to' => 'singapore', 'type' => 'city'),
            array('from' => 'singapore', 'to' => 'london', 'type' => 'city'),
            array('from' => 'hong-kong', 'to' => 'london', 'type' => 'city'),

            // From US
            array('from' => 'new-york', 'to' => 'london', 'type' => 'city'),
            array('from' => 'san-francisco', 'to' => 'london', 'type' => 'city'),
            array('from' => 'united-states', 'to' => 'united-kingdom', 'type' => 'country'),
            array('from' => 'united-states', 'to' => 'switzerland', 'type' => 'country'),

            // From Ireland
            array('from' => 'dublin', 'to' => 'london', 'type' => 'city'),
            array('from' => 'dublin', 'to' => 'amsterdam', 'type' => 'city'),
            array('from' => 'ireland', 'to' => 'united-kingdom', 'type' => 'country'),

            // From Portugal
            array('from' => 'lisbon', 'to' => 'london', 'type' => 'city'),
            array('from' => 'lisbon', 'to' => 'amsterdam', 'type' => 'city'),
            array('from' => 'portugal', 'to' => 'united-kingdom', 'type' => 'country'),
        );
    }

    /**
     * Register custom post type for relocation pages
     */
    public function register_post_type() {
        $labels = array(
            'name' => 'Relocation Pages',
            'singular_name' => 'Relocation Page',
            'menu_name' => 'Relocations',
            'add_new' => 'Add New',
            'add_new_item' => 'Add New Relocation Page',
            'edit_item' => 'Edit Relocation Page',
            'new_item' => 'New Relocation Page',
            'view_item' => 'View Relocation Page',
            'search_items' => 'Search Relocation Pages',
            'not_found' => 'No relocation pages found',
            'not_found_in_trash' => 'No relocation pages found in trash',
        );

        $args = array(
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => 'sffc-dashboard', // Add to SF Finance dashboard menu (like PE post types)
            'show_in_admin_bar' => true,
            'show_in_nav_menus' => true,
            'exclude_from_search' => false,
            'query_var' => true,
            'rewrite' => array('slug' => 'relocating', 'with_front' => false),
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => 33, // After PE Signals (32)
            'menu_icon' => 'dashicons-airplane',
            'supports' => array('title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'),
            'show_in_rest' => true,
        );

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Register rewrite rules for clean URLs
     */
    public function register_rewrite_rules() {
        // /relocating/london-to-dubai/
        add_rewrite_rule(
            '^relocating/([a-z0-9-]+)-to-([a-z0-9-]+)/?$',
            'index.php?post_type=' . self::POST_TYPE . '&sffc_from_location=$matches[1]&sffc_to_location=$matches[2]',
            'top'
        );

        // /relocating/ archive
        add_rewrite_rule(
            '^relocating/?$',
            'index.php?post_type=' . self::POST_TYPE,
            'top'
        );
    }

    /**
     * Add custom query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'sffc_from_location';
        $vars[] = 'sffc_to_location';
        return $vars;
    }

    /**
     * Load custom template for relocation pages
     *
     * SHORTCODE APPROACH (Recommended):
     * - Create a page in WordPress/Elementor with [sffc_relocation_page] shortcode
     * - Set that page as the "Relocation Template" in settings
     * - The shortcode auto-detects from/to from URL query vars
     *
     * FALLBACK APPROACH:
     * - If no shortcode page is set, uses legacy templates
     * - Theme templates take priority (single-sffc_relocation.php, archive-sffc_relocation.php)
     * - Plugin templates as last resort
     */
    /**
     * Find a page containing a specific shortcode (checks post_content and Elementor data)
     */
    private function find_page_with_shortcode($shortcode) {
        // Check transient cache first
        $cache_key = 'sffc_page_with_' . sanitize_key($shortcode);
        $cached_id = get_transient($cache_key);

        if ($cached_id !== false) {
            // Verify page still exists
            $page = get_post($cached_id);
            if ($page && $page->post_status === 'publish') {
                // Check if shortcode exists in content or Elementor data
                if ($this->page_has_shortcode($page->ID, $shortcode)) {
                    return $cached_id;
                }
            }
            // Cache invalid, delete it
            delete_transient($cache_key);
        }

        global $wpdb;

        // First, search in post_content
        $page_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = 'page'
             AND post_status = 'publish'
             AND post_content LIKE %s
             LIMIT 1",
            '%[' . $shortcode . '%'
        ));

        // If not found, search in Elementor data (stored in postmeta)
        if (!$page_id) {
            $page_id = $wpdb->get_var($wpdb->prepare(
                "SELECT p.ID FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 WHERE p.post_type = 'page'
                 AND p.post_status = 'publish'
                 AND pm.meta_key = '_elementor_data'
                 AND pm.meta_value LIKE %s
                 LIMIT 1",
                '%' . $shortcode . '%'
            ));
        }

        if ($page_id) {
            // Cache for 1 hour
            set_transient($cache_key, $page_id, HOUR_IN_SECONDS);
        }

        return $page_id;
    }

    /**
     * Check if a page has a shortcode in content or Elementor data
     */
    private function page_has_shortcode($page_id, $shortcode) {
        $page = get_post($page_id);
        if (!$page) {
            return false;
        }

        // Check post_content
        if (has_shortcode($page->post_content, $shortcode)) {
            return true;
        }

        // Check Elementor data
        $elementor_data = get_post_meta($page_id, '_elementor_data', true);
        if ($elementor_data && strpos($elementor_data, $shortcode) !== false) {
            return true;
        }

        return false;
    }

    public function load_relocation_template($template) {
        $from = get_query_var('sffc_from_location');
        $to = get_query_var('sffc_to_location');

        // Handle single relocation page
        if (!empty($from) && !empty($to)) {
            // Check if we have a stored page, if not generate dynamically
            $page = $this->get_relocation_page($from, $to);

            if ($page) {
                // Use stored content
                global $post;
                $post = $page;
                setup_postdata($post);
            }

            // Auto-detect page with [sffc_relocation_page] shortcode
            $shortcode_page_id = $this->find_page_with_shortcode('sffc_relocation_page');
            if ($shortcode_page_id) {
                global $post;
                $post = get_post($shortcode_page_id);
                if ($post) {
                    setup_postdata($post);
                    return get_page_template();
                }
            }

            // Look for custom template in theme (legacy fallback)
            $custom_template = locate_template('single-sffc_relocation.php');
            if ($custom_template) {
                return $custom_template;
            }

            // Use plugin template (legacy fallback)
            $plugin_template = SFFC_PLUGIN_DIR . 'templates/single-relocation.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }

        // Handle archive page (/relocating/)
        if (is_post_type_archive(self::POST_TYPE)) {
            // Auto-detect page with [sffc_relocation_archive] shortcode
            $archive_page_id = $this->find_page_with_shortcode('sffc_relocation_archive');
            if ($archive_page_id) {
                global $post;
                $post = get_post($archive_page_id);
                if ($post) {
                    setup_postdata($post);
                    return get_page_template();
                }
            }

            // Look for custom template in theme (legacy fallback)
            $custom_template = locate_template('archive-sffc_relocation.php');
            if ($custom_template) {
                return $custom_template;
            }

            // Use plugin template (legacy fallback)
            $plugin_template = SFFC_PLUGIN_DIR . 'templates/archive-relocation.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }

        return $template;
    }

    /**
     * Get or create a relocation page
     */
    public function get_relocation_page($from_slug, $to_slug) {
        $slug = $from_slug . '-to-' . $to_slug;

        // Check if page exists
        $existing = get_posts(array(
            'post_type' => self::POST_TYPE,
            'name' => $slug,
            'posts_per_page' => 1,
            'post_status' => 'publish',
        ));

        if (!empty($existing)) {
            return $existing[0];
        }

        return null;
    }

    /**
     * Get location info by slug (handles cities and countries)
     */
    public function get_location_info($slug) {
        // First check if it's a country
        if (isset($this->locations[$slug])) {
            return array(
                'type' => 'country',
                'slug' => $slug,
                'name' => $this->locations[$slug]['name'],
                'display_name' => $this->locations[$slug]['display_name'],
                'code' => $this->locations[$slug]['code'],
                'currency' => $this->locations[$slug]['currency'],
                'country_slug' => $slug,
                'country_name' => $this->locations[$slug]['name'],
            );
        }

        // Check if it's a city
        foreach ($this->locations as $country_slug => $country_data) {
            if (isset($country_data['cities'][$slug])) {
                return array(
                    'type' => 'city',
                    'slug' => $slug,
                    'name' => $country_data['cities'][$slug]['name'],
                    'display_name' => $country_data['cities'][$slug]['name'],
                    'code' => $country_data['code'],
                    'currency' => $country_data['currency'],
                    'country_slug' => $country_slug,
                    'country_name' => $country_data['name'],
                );
            }

            // Check aliases (e.g., dubai for UAE)
            if (isset($country_data['aliases']) && in_array($slug, $country_data['aliases'])) {
                // Return the primary city for this alias
                foreach ($country_data['cities'] as $city_slug => $city_data) {
                    if (!empty($city_data['is_primary'])) {
                        return array(
                            'type' => 'city',
                            'slug' => $city_slug,
                            'name' => $city_data['name'],
                            'display_name' => $city_data['name'],
                            'code' => $country_data['code'],
                            'currency' => $country_data['currency'],
                            'country_slug' => $country_slug,
                            'country_name' => $country_data['name'],
                        );
                    }
                }
            }
        }

        return null;
    }

    /**
     * Generate page title for SEO
     */
    public function generate_page_title($from_info, $to_info) {
        $from_name = $from_info['display_name'];
        $to_name = $to_info['display_name'];

        return "Moving to {$to_name} from {$from_name}: A Complete Guide";
    }

    /**
     * Filter archive title to remove "Archives:" prefix
     */
    public function filter_archive_title($title) {
        if (is_post_type_archive(self::POST_TYPE)) {
            return 'Relocation Guides for Finance Professionals';
        }
        return $title;
    }

    /**
     * Filter document title for relocation pages
     */
    public function filter_document_title($title) {
        // Single relocation page
        $from = get_query_var('sffc_from_location');
        $to = get_query_var('sffc_to_location');

        if (!empty($from) && !empty($to)) {
            $from_info = $this->get_location_info($from);
            $to_info = $this->get_location_info($to);
            if ($from_info && $to_info) {
                return $this->generate_page_title($from_info, $to_info);
            }
        }

        // Archive page
        if (is_post_type_archive(self::POST_TYPE)) {
            return 'Relocation Guides for Finance Professionals';
        }

        return $title;
    }

    /**
     * Filter document title parts
     */
    public function filter_document_title_parts($title_parts) {
        // Single relocation page
        $from = get_query_var('sffc_from_location');
        $to = get_query_var('sffc_to_location');

        if (!empty($from) && !empty($to)) {
            $from_info = $this->get_location_info($from);
            $to_info = $this->get_location_info($to);
            if ($from_info && $to_info) {
                $title_parts['title'] = $this->generate_page_title($from_info, $to_info);
            }
        }

        // Archive page
        if (is_post_type_archive(self::POST_TYPE)) {
            $title_parts['title'] = 'Relocation Guides for Finance Professionals';
        }

        return $title_parts;
    }

    /**
     * Filter wp_title for older themes
     */
    public function filter_wp_title($title, $sep) {
        // Single relocation page
        $from = get_query_var('sffc_from_location');
        $to = get_query_var('sffc_to_location');

        if (!empty($from) && !empty($to)) {
            $from_info = $this->get_location_info($from);
            $to_info = $this->get_location_info($to);
            if ($from_info && $to_info) {
                return $this->generate_page_title($from_info, $to_info) . " $sep " . get_bloginfo('name');
            }
        }

        // Archive page
        if (is_post_type_archive(self::POST_TYPE)) {
            return 'Relocation Guides for Finance Professionals' . " $sep " . get_bloginfo('name');
        }

        return $title;
    }

    /**
     * Generate meta description for SEO
     */
    public function generate_meta_description($from_info, $to_info) {
        $from_name = $from_info['display_name'];
        $to_name = $to_info['display_name'];

        return "Everything you need to know about relocating from {$from_name} to {$to_name}. Cost of living comparison, tax implications, visa requirements, job market insights, and salary expectations.";
    }

    /**
     * Get comparison data between two locations
     */
    public function get_comparison_data($from_slug, $to_slug) {
        $from_info = $this->get_location_info($from_slug);
        $to_info = $this->get_location_info($to_slug);

        if (!$from_info || !$to_info) {
            return null;
        }

        $comparison = array(
            'from' => $from_info,
            'to' => $to_info,
            'metrics' => array(),
        );

        // Use mobility service if available
        if ($this->mobility_service) {
            $from_location = array(
                'city' => $from_info['type'] === 'city' ? $from_info['name'] : '',
                'country' => $from_info['country_name'],
            );
            $to_location = array(
                'city' => $to_info['type'] === 'city' ? $to_info['name'] : '',
                'country' => $to_info['country_name'],
            );

            $mobility_comparison = $this->mobility_service->compare_locations($from_location, $to_location);
            $comparison['mobility'] = $mobility_comparison;
        }

        return $comparison;
    }

    /**
     * Get jobs for a specific location
     */
    public function get_location_jobs($location_slug, $limit = 10) {
        $location_info = $this->get_location_info($location_slug);

        if (!$location_info) {
            return array();
        }

        $search_terms = array($location_info['name']);
        if ($location_info['type'] === 'city') {
            $search_terms[] = $location_info['country_name'];
        }

        $args = array(
            'post_type' => 'sffc_job',
            'posts_per_page' => $limit,
            'post_status' => 'publish',
            'meta_query' => array(
                'relation' => 'OR',
            ),
        );

        // Search in location meta fields
        foreach ($search_terms as $term) {
            $args['meta_query'][] = array(
                'key' => 'sffc_location_city',
                'value' => $term,
                'compare' => 'LIKE',
            );
            $args['meta_query'][] = array(
                'key' => 'sffc_location_country',
                'value' => $term,
                'compare' => 'LIKE',
            );
            $args['meta_query'][] = array(
                'key' => 'sffc_job_location',
                'value' => $term,
                'compare' => 'LIKE',
            );
        }

        $jobs = get_posts($args);

        // If no results from meta, try searching content
        if (empty($jobs)) {
            $args = array(
                'post_type' => 'sffc_job',
                'posts_per_page' => $limit,
                'post_status' => 'publish',
                's' => $location_info['name'],
            );
            $jobs = get_posts($args);
        }

        return $jobs;
    }

    /**
     * Generate Claude prompt for location brief
     */
    private function get_claude_prompt($from_info, $to_info, $comparison_data) {
        $from_name = $from_info['display_name'];
        $to_name = $to_info['display_name'];

        $prompt = <<<PROMPT
Write a relocation guide for finance professionals moving from {$from_name} to {$to_name}.

CRITICAL: Start directly with the first <h2> heading. Do NOT include any introduction, preamble, or phrases like "Here is..." or "Below is..." - jump straight into the content.

Focus on finance, investment banking, private equity, and professional services careers.

Structure the content with these HTML sections:

<h2>Overview</h2>
(2-3 paragraphs on why professionals relocate, key considerations, timeline)

<h2>Cost of Living Comparison</h2>
(Housing, transportation, daily expenses, affordability)

<h2>Tax Implications</h2>
(Income tax comparison, capital gains, double taxation treaties, planning tips)

<h2>Visa & Work Permits</h2>
(Main visa categories, processing times, sponsorship, documents needed)

<h2>Job Market Insights</h2>
(Major employers, salary expectations, industry trends, networking)

<h2>Quality of Life</h2>
(Work-life balance, healthcare, expat community, cultural tips)

<h2>Practical Moving Tips</h2>
(Best timing, accommodation, banking, first 30 days checklist)

Requirements:
- Use factual, professional language with specific numbers where relevant
- Format with HTML tags: <h2>, <h3>, <p>, <ul>, <li>
- No emojis, no markdown, no preamble text
- Professional but approachable tone
- Start immediately with <h2>Overview</h2>
PROMPT;

        return $prompt;
    }

    /**
     * Check if test mode is enabled
     */
    public function is_test_mode() {
        return (bool) get_option('sffc_relocation_test_mode', false);
    }

    /**
     * Sanitize Claude API response to remove any preamble text
     */
    private function sanitize_claude_response($content) {
        // Remove common preamble patterns
        $patterns = array(
            '/^.*?(?=<h2)/is',  // Remove everything before first <h2>
            '/^Here is.*?:/i',
            '/^Below is.*?:/i',
            '/^I\'ve created.*?:/i',
            '/^This is.*?guide.*?:/i',
            '/^Here\'s.*?:/i',
        );

        // First, try to find and keep content starting from first <h2>
        if (preg_match('/<h2/i', $content)) {
            $content = preg_replace('/^.*?(<h2)/is', '$1', $content);
        }

        // Trim whitespace
        $content = trim($content);

        return $content;
    }

    /**
     * Generate location brief using Claude (or placeholder in test mode)
     */
    public function generate_location_brief($from_slug, $to_slug) {
        $from_info = $this->get_location_info($from_slug);
        $to_info = $this->get_location_info($to_slug);

        if (!$from_info || !$to_info) {
            return new WP_Error('invalid_location', 'Invalid location specified');
        }

        // Test mode - skip Claude API and use placeholder content
        if ($this->is_test_mode()) {
            return $this->generate_placeholder_content($from_info, $to_info);
        }

        $comparison_data = $this->get_comparison_data($from_slug, $to_slug);
        $prompt = $this->get_claude_prompt($from_info, $to_info, $comparison_data);

        // Check if Claude API manager exists
        if (!class_exists('SFFC_Claude_API_Manager')) {
            return new WP_Error('no_claude', 'Claude API manager not available');
        }

        $claude = SFFC_Claude_API_Manager::get_instance();

        // Check if API key is configured
        if (!$claude->is_available()) {
            error_log('[SFFC Relocation] Claude API key not configured, using placeholder content');
            return $this->generate_placeholder_content($from_info, $to_info);
        }

        // Use call_api method which accepts max_tokens parameter
        $response = $claude->call_api($prompt, array(
            'model' => 'claude-3-haiku-20240307',
            'max_tokens' => 4000,
            'temperature' => 0.7,
            'mode' => 'career',
        ));

        // Handle response - call_api returns array with 'content' key
        if (is_wp_error($response)) {
            error_log('[SFFC Relocation] Claude API error: ' . $response->get_error_message());
            return $response;
        }

        // Extract text from Claude API response format
        if (isset($response['content'][0]['text'])) {
            $content = $response['content'][0]['text'];

            // Strip any preamble text before the first <h2> tag
            $content = $this->sanitize_claude_response($content);

            return $content;
        }

        // Fallback if response format is unexpected
        error_log('[SFFC Relocation] Unexpected Claude response format');
        return $this->generate_placeholder_content($from_info, $to_info);
    }

    /**
     * Create or update a relocation page
     */
    public function create_relocation_page($from_slug, $to_slug, $content = null) {
        $from_info = $this->get_location_info($from_slug);
        $to_info = $this->get_location_info($to_slug);

        if (!$from_info || !$to_info) {
            return new WP_Error('invalid_location', 'Invalid location specified');
        }

        $slug = $from_slug . '-to-' . $to_slug;
        $title = $this->generate_page_title($from_info, $to_info);
        $excerpt = $this->generate_meta_description($from_info, $to_info);

        // Check if page already exists
        $existing = get_posts(array(
            'post_type' => self::POST_TYPE,
            'name' => $slug,
            'posts_per_page' => 1,
            'post_status' => array('publish', 'draft', 'pending'),
        ));

        // Generate content if not provided
        if (empty($content)) {
            $content = $this->generate_location_brief($from_slug, $to_slug);
            if (is_wp_error($content)) {
                // Use placeholder content
                $content = $this->generate_placeholder_content($from_info, $to_info);
            }
        }

        $post_data = array(
            'post_type' => self::POST_TYPE,
            'post_title' => $title,
            'post_name' => $slug,
            'post_content' => $content,
            'post_excerpt' => $excerpt,
            'post_status' => 'publish',
        );

        if (!empty($existing)) {
            $post_data['ID'] = $existing[0]->ID;
            $post_id = wp_update_post($post_data);
        } else {
            $post_id = wp_insert_post($post_data);
        }

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // Store metadata
        update_post_meta($post_id, '_sffc_from_location', $from_slug);
        update_post_meta($post_id, '_sffc_to_location', $to_slug);
        update_post_meta($post_id, '_sffc_from_type', $from_info['type']);
        update_post_meta($post_id, '_sffc_to_type', $to_info['type']);
        update_post_meta($post_id, '_sffc_generated_date', current_time('mysql'));

        // Store comparison data
        $comparison = $this->get_comparison_data($from_slug, $to_slug);
        if ($comparison) {
            update_post_meta($post_id, '_sffc_comparison_data', $comparison);
        }

        return $post_id;
    }

    /**
     * Generate placeholder content when Claude is not available
     */
    private function generate_placeholder_content($from_info, $to_info) {
        $from_name = $from_info['display_name'];
        $to_name = $to_info['display_name'];

        return <<<HTML
<h2>Moving to {$to_name} from {$from_name}</h2>

<p>This comprehensive guide covers everything professionals need to know about relocating from {$from_name} to {$to_name}. Whether you're pursuing new career opportunities in finance, private equity, or professional services, understanding the key differences between these locations is essential for a successful move.</p>

<h2>Cost of Living Comparison</h2>
<p>Understanding the cost of living differences between {$from_name} and {$to_name} is crucial for financial planning. This section will be updated with detailed cost comparisons including housing, transportation, and daily expenses.</p>

<h2>Tax Implications</h2>
<p>Tax considerations play a significant role in international relocation. This section covers income tax rates, capital gains implications, and double taxation treaties between these jurisdictions.</p>

<h2>Visa & Work Permits</h2>
<p>Navigating visa requirements is often the first step in international relocation. This section outlines the main visa categories available for professionals moving to {$to_name}.</p>

<h2>Job Market</h2>
<p>The job market in {$to_name} offers diverse opportunities for finance professionals. This section provides insights into major employers, salary expectations, and industry trends.</p>

<h2>Quality of Life</h2>
<p>Beyond career considerations, quality of life factors significantly impact relocation decisions. This section compares work-life balance, healthcare, and cultural aspects between both locations.</p>
HTML;
    }

    /**
     * AJAX handler for generating a single location brief
     */
    public function ajax_generate_location_brief() {
        check_ajax_referer('sffc_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $from = sanitize_text_field($_POST['from_location'] ?? '');
        $to = sanitize_text_field($_POST['to_location'] ?? '');

        if (empty($from) || empty($to)) {
            wp_send_json_error('Missing location parameters');
        }

        $post_id = $this->create_relocation_page($from, $to);

        if (is_wp_error($post_id)) {
            wp_send_json_error($post_id->get_error_message());
        }

        wp_send_json_success(array(
            'post_id' => $post_id,
            'edit_url' => get_edit_post_link($post_id, 'raw'),
            'view_url' => get_permalink($post_id),
        ));
    }

    /**
     * AJAX handler for bulk generating pages
     */
    public function ajax_bulk_generate_pages() {
        check_ajax_referer('sffc_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $type = sanitize_text_field($_POST['type'] ?? 'popular');
        $limit = intval($_POST['limit'] ?? 10);

        $routes = array();

        if ($type === 'popular') {
            $routes = array_slice($this->popular_routes, 0, $limit);
        } elseif ($type === 'all_countries') {
            $routes = $this->generate_all_country_routes();
            $routes = array_slice($routes, 0, $limit);
        } elseif ($type === 'all_cities') {
            $routes = $this->generate_all_city_routes();
            $routes = array_slice($routes, 0, $limit);
        }

        $results = array();
        foreach ($routes as $route) {
            $post_id = $this->create_relocation_page($route['from'], $route['to']);
            $results[] = array(
                'from' => $route['from'],
                'to' => $route['to'],
                'success' => !is_wp_error($post_id),
                'post_id' => is_wp_error($post_id) ? null : $post_id,
                'error' => is_wp_error($post_id) ? $post_id->get_error_message() : null,
            );
        }

        wp_send_json_success($results);
    }

    /**
     * AJAX handler for newsletter subscription
     */
    public function ajax_newsletter_subscribe() {
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $source = isset($_POST['source']) ? sanitize_text_field($_POST['source']) : 'unknown';

        if (empty($email) || !is_email($email)) {
            wp_send_json_error('Please enter a valid email address.');
        }

        // Store subscription in options or custom table
        $subscribers = get_option('sffc_newsletter_subscribers', array());

        // Check if already subscribed
        $existing = array_filter($subscribers, function($sub) use ($email) {
            return isset($sub['email']) && $sub['email'] === $email;
        });

        if (!empty($existing)) {
            wp_send_json_success(array('message' => 'You\'re already subscribed!'));
        }

        // Add new subscriber
        $subscribers[] = array(
            'email' => $email,
            'source' => $source,
            'date' => current_time('mysql'),
            'ip' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '',
        );

        update_option('sffc_newsletter_subscribers', $subscribers);

        wp_send_json_success(array('message' => 'Thanks for subscribing!'));
    }

    /**
     * Generate all country-to-country routes
     */
    private function generate_all_country_routes() {
        $routes = array();
        $country_slugs = array_keys($this->locations);

        foreach ($country_slugs as $from) {
            foreach ($country_slugs as $to) {
                if ($from !== $to) {
                    $routes[] = array(
                        'from' => $from,
                        'to' => $to,
                        'type' => 'country',
                    );
                }
            }
        }

        return $routes;
    }

    /**
     * Generate all city-to-city routes (limited to popular combinations)
     */
    private function generate_all_city_routes() {
        return array_filter($this->popular_routes, function($route) {
            return $route['type'] === 'city';
        });
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'sffc-dashboard', // Add to SF Finance dashboard menu (like PE post types)
            'Relocation Settings',
            'Relocation Settings',
            'manage_options',
            'sffc-relocation-pages',
            array($this, 'render_admin_page')
        );
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        $locations = $this->locations;
        $popular_routes = $this->popular_routes;

        include SFFC_PLUGIN_DIR . 'templates/admin-relocation-pages.php';
    }

    /**
     * Get all locations
     */
    public function get_all_locations() {
        return $this->locations;
    }

    /**
     * Get popular routes
     */
    public function get_popular_routes() {
        return $this->popular_routes;
    }

    /**
     * Get statistics for admin dashboard
     */
    public function get_statistics() {
        $total_pages = wp_count_posts(self::POST_TYPE);
        $total_routes = count($this->popular_routes);
        $total_countries = count($this->locations);

        $total_cities = 0;
        foreach ($this->locations as $country) {
            $total_cities += count($country['cities']);
        }

        return array(
            'published_pages' => $total_pages->publish ?? 0,
            'draft_pages' => $total_pages->draft ?? 0,
            'popular_routes' => $total_routes,
            'countries' => $total_countries,
            'cities' => $total_cities,
            'potential_pages' => ($total_countries * ($total_countries - 1)) + $total_routes,
        );
    }
}

// Initialize
add_action('plugins_loaded', function() {
    SFFC_Relocation_Pages::get_instance();
});

// Include shortcodes class
require_once dirname(__FILE__) . '/class-relocation-shortcodes.php';
