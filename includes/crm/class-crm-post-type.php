<?php
/**
 * CRM Post Type - Virtual Post Type for CRM Posts
 *
 * Registers URL routing for CRM posts stored in custom database tables.
 * Allows CRM posts to be viewable at /crm-opportunity/{id}/ URLs.
 *
 * @package SennaCareers
 * @since 7.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_Post_Type {

    private static $instance = null;

    /**
     * Post type slug
     */
    const POST_TYPE = 'sffc_crm_post';

    /**
     * URL slug for frontend
     */
    const URL_SLUG = 'crm-opportunity';

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', [$this, 'register_post_type'], 5);
        add_action('init', [$this, 'add_rewrite_rules'], 10);
        add_filter('query_vars', [$this, 'add_query_vars']);
        add_action('template_redirect', [$this, 'handle_crm_post_template']);
        add_filter('the_title', [$this, 'filter_crm_post_title'], 10, 2);
        add_filter('document_title_parts', [$this, 'filter_document_title']);

        // Flush rewrite rules on activation
        register_activation_hook(SFFC_PLUGIN_FILE, [$this, 'flush_rewrite_rules']);
    }

    /**
     * Register the CRM Post custom post type
     * This is a virtual post type - posts are stored in custom tables, not wp_posts
     */
    public function register_post_type() {
        $labels = [
            'name'               => __('CRM Opportunities', 'senna-finance'),
            'singular_name'      => __('CRM Opportunity', 'senna-finance'),
            'menu_name'          => __('CRM Posts', 'senna-finance'),
            'all_items'          => __('All Opportunities', 'senna-finance'),
            'view_item'          => __('View Opportunity', 'senna-finance'),
            'search_items'       => __('Search Opportunities', 'senna-finance'),
            'not_found'          => __('No opportunities found', 'senna-finance'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => false, // Hide from admin - managed via CRM
            'show_in_menu'       => false,
            'show_in_nav_menus'  => false,
            'show_in_rest'       => true,
            'query_var'          => true,
            'rewrite'            => [
                'slug'       => self::URL_SLUG,
                'with_front' => false,
            ],
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'supports'           => ['title', 'custom-fields'],
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Add custom rewrite rules for CRM posts
     */
    public function add_rewrite_rules() {
        // Match /crm-opportunity/{id}/
        add_rewrite_rule(
            '^' . self::URL_SLUG . '/([0-9]+)/?$',
            'index.php?sffc_crm_post_id=$matches[1]',
            'top'
        );

        // Match /crm-opportunity/{id}/{slug}/
        add_rewrite_rule(
            '^' . self::URL_SLUG . '/([0-9]+)/([^/]+)/?$',
            'index.php?sffc_crm_post_id=$matches[1]&sffc_crm_post_slug=$matches[2]',
            'top'
        );
    }

    /**
     * Add query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'sffc_crm_post_id';
        $vars[] = 'sffc_crm_post_slug';
        return $vars;
    }

    /**
     * Handle template redirect for CRM posts
     */
    public function handle_crm_post_template() {
        $crm_post_id = get_query_var('sffc_crm_post_id');

        if (!$crm_post_id) {
            return;
        }

        // Get the CRM post data
        $post_model = new SFFC_CRM_Post();
        $crm_post = $post_model->get_full_detail((int) $crm_post_id);

        if (!$crm_post) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            return;
        }

        // Store post data globally for template access
        $GLOBALS['sffc_crm_current_post'] = $crm_post;

        // Enqueue required assets
        $this->enqueue_crm_post_assets();

        // Load template
        $this->load_crm_post_template($crm_post);
        exit;
    }

    /**
     * Enqueue assets for CRM post view
     */
    private function enqueue_crm_post_assets() {
        $plugin_url = plugin_dir_url(dirname(dirname(__FILE__)));

        // Gap analyzer styles (same as recruiter post article)
        wp_enqueue_style(
            'sffc-gap-analyzer',
            $plugin_url . 'assets/css/gap-analyzer.css',
            [],
            SFFC_VERSION
        );

        wp_enqueue_style(
            'sffc-recruiter-post-article',
            $plugin_url . 'assets/css/recruiter-post-article.css',
            ['sffc-gap-analyzer'],
            SFFC_VERSION
        );

        // Gap analyzer script
        wp_enqueue_script(
            'sffc-gap-analyzer',
            $plugin_url . 'assets/js/gap-analyzer.js',
            ['jquery'],
            SFFC_VERSION,
            true
        );

        wp_localize_script('sffc-gap-analyzer', 'sffcGapAnalyzer', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sffc_gap_analyzer'),
            'isLoggedIn' => is_user_logged_in(),
        ]);
    }

    /**
     * Load CRM post template
     */
    private function load_crm_post_template($crm_post) {
        // Check for theme override
        $template = locate_template('sffc/crm-post-single.php');

        if (!$template) {
            $template = plugin_dir_path(dirname(dirname(__FILE__))) . 'templates/crm-post-single.php';
        }

        if (file_exists($template)) {
            include $template;
        } else {
            // Fallback: use page template with shortcode
            $this->render_fallback_template($crm_post);
        }
    }

    /**
     * Render fallback template
     */
    private function render_fallback_template($crm_post) {
        get_header();

        echo '<div class="sffc-crm-post-single-wrapper">';
        echo do_shortcode('[sffc_crm_post_article post_id="' . esc_attr($crm_post->id) . '"]');
        echo '</div>';

        get_footer();
    }

    /**
     * Filter page title for CRM posts
     */
    public function filter_crm_post_title($title, $post_id = null) {
        if (isset($GLOBALS['sffc_crm_current_post']) && in_the_loop()) {
            $crm_post = $GLOBALS['sffc_crm_current_post'];
            return esc_html($crm_post->role_title . ' at ' . $crm_post->company);
        }
        return $title;
    }

    /**
     * Filter document title for CRM posts
     */
    public function filter_document_title($title_parts) {
        if (isset($GLOBALS['sffc_crm_current_post'])) {
            $crm_post = $GLOBALS['sffc_crm_current_post'];
            $title_parts['title'] = esc_html($crm_post->role_title . ' at ' . $crm_post->company);
        }
        return $title_parts;
    }

    /**
     * Get the URL for a CRM post
     *
     * @param int $post_id CRM post ID
     * @param string $slug Optional URL slug
     * @return string Full URL
     */
    public static function get_post_url($post_id, $slug = '') {
        $url = home_url('/' . self::URL_SLUG . '/' . $post_id . '/');

        if ($slug) {
            $url = home_url('/' . self::URL_SLUG . '/' . $post_id . '/' . sanitize_title($slug) . '/');
        }

        return $url;
    }

    /**
     * Generate SEO-friendly slug from post data
     *
     * @param object $post CRM post object
     * @return string Sanitized slug
     */
    public static function generate_slug($post) {
        $parts = [];

        if (!empty($post->role_title)) {
            $parts[] = $post->role_title;
        }

        if (!empty($post->company)) {
            $parts[] = $post->company;
        }

        if (!empty($post->location)) {
            $parts[] = $post->location;
        }

        return sanitize_title(implode('-', $parts));
    }

    /**
     * Flush rewrite rules
     */
    public function flush_rewrite_rules() {
        $this->register_post_type();
        $this->add_rewrite_rules();
        flush_rewrite_rules();
    }
}

// Initialize
SFFC_CRM_Post_Type::get_instance();
