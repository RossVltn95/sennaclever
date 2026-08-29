<?php
/**
 * Company Profile Aggregator
 * Aggregates and organizes all data related to PE firms
 * 
 * @package SennaCareers
 * @since 10.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Company_Profile_Aggregator {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Cache duration in seconds
     */
    private $cache_duration = 3600; // 1 hour

    /**
     * Style handle for profile assets
     */
    private $style_handle = 'sffc-company-profile';

    /**
     * Explorer asset handles
     */
    private $explorer_style_handle = 'sffc-company-explorer';
    private $explorer_script_handle = 'sffc-company-explorer-js';
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // AJAX endpoints
        add_action('wp_ajax_sffc_get_company_profile', array($this, 'ajax_get_company_profile'));
        add_action('wp_ajax_nopriv_sffc_get_company_profile', array($this, 'ajax_get_company_profile'));
        
        add_action('wp_ajax_sffc_get_company_cards', array($this, 'ajax_get_company_cards'));
        add_action('wp_ajax_nopriv_sffc_get_company_cards', array($this, 'ajax_get_company_cards'));
        
        // Profile shortcodes
        add_shortcode('pe_company_profile', array($this, 'render_company_profile_shortcode'));
        add_shortcode('pe_company_cards', array($this, 'render_company_cards_shortcode'));
        add_shortcode('pe_company_explorer', array($this, 'render_company_explorer_shortcode'));

        // Template override
        add_filter('template_include', array($this, 'maybe_use_template'), 99);

        // Handle frontend submissions
        add_action('template_redirect', array($this, 'handle_frontend_submission'), 1);

        // Assets
        add_action('template_redirect', array($this, 'prepare_frontend_assets'), 5);
    }
    
    /**
     * Handle front-end portfolio submissions
     */
    public function handle_frontend_submission() {
        if (is_admin() || !is_singular('sffc_company')) {
            return;
        }

        if (empty($_POST['sffc_add_portfolio_company'])) {
            return;
        }

        $company_id = isset($_POST['sffc_company_id']) ? intval($_POST['sffc_company_id']) : 0;

        if (!$company_id || !current_user_can('edit_post', $company_id)) {
            return;
        }

        if (!isset($_POST['sffc_portfolio_nonce']) || !wp_verify_nonce($_POST['sffc_portfolio_nonce'], 'sffc_add_portfolio_company')) {
            return;
        }

        $entry = array(
            'name'   => sanitize_text_field($_POST['portfolio_name'] ?? ''),
            'sector' => sanitize_text_field($_POST['portfolio_sector'] ?? ''),
            'region' => sanitize_text_field($_POST['portfolio_region'] ?? ''),
            'status' => sanitize_text_field($_POST['portfolio_status'] ?? ''),
            'url'    => esc_url_raw($_POST['portfolio_url'] ?? ''),
            'notes'  => sanitize_textarea_field($_POST['portfolio_notes'] ?? '')
        );

        if ($entry['name'] === '') {
            return;
        }

        $portfolio = get_post_meta($company_id, '_sffc_portfolio_list', true);
        if ($portfolio) {
            if (is_string($portfolio)) {
                $decoded = json_decode($portfolio, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $portfolio = $decoded;
                } else {
                    $portfolio = array();
                }
            }
        } else {
            $portfolio = array();
        }

        if (!is_array($portfolio)) {
            $portfolio = array();
        }

        $portfolio[] = $entry;
        update_post_meta($company_id, '_sffc_portfolio_list', wp_json_encode($portfolio));
        self::clear_profile_cache($company_id);

        wp_safe_redirect(add_query_arg('portfolio_status', 'added', get_permalink($company_id)));
        exit;
    }

    /**
     * Prepare frontend assets once the main query is ready
     */
    public function prepare_frontend_assets() {
        if (is_admin()) {
            return;
        }

        if (is_singular('sffc_company')) {
            add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        }
    }



    /**
     * Maybe use custom template for company profiles
     */
    public function maybe_use_template($template) {
        if (is_singular('sffc_company')) {
            return SFFC_PLUGIN_DIR . 'templates/company-profile-page.php';
        }
        return $template;
    }

    /**
     * Enqueue front-end assets for company profiles
     */
    public function enqueue_assets() {
        $css_path = SFFC_PLUGIN_DIR . 'assets/css/company-profile.css';
        $css_url  = SFFC_PLUGIN_URL . 'assets/css/company-profile.css';
        $version  = defined('SFFC_VERSION') ? SFFC_VERSION : (file_exists($css_path) ? filemtime($css_path) : false);

        wp_enqueue_style($this->style_handle, $css_url, array(), $version ?: null);
    }

    private function enqueue_explorer_assets(array $localize = array()) {
        $css_path = SFFC_PLUGIN_DIR . 'assets/css/company-explorer.css';
        $css_url  = SFFC_PLUGIN_URL . 'assets/css/company-explorer.css';
        $css_version = file_exists($css_path) ? filemtime($css_path) : false;

        wp_enqueue_style($this->explorer_style_handle, $css_url, array(), $css_version ?: null);

        $js_path = SFFC_PLUGIN_DIR . 'assets/js/company-explorer.js';
        $js_url  = SFFC_PLUGIN_URL . 'assets/js/company-explorer.js';
        $js_version = file_exists($js_path) ? filemtime($js_path) : false;

        wp_enqueue_script($this->explorer_script_handle, $js_url, array(), $js_version ?: null, true);

        $defaults = array(
            'perPage' => isset($localize['perPage']) ? (int) $localize['perPage'] : 12,
            'sort' => isset($localize['sort']) ? sanitize_text_field($localize['sort']) : 'aum_desc',
        );

        unset($localize['perPage'], $localize['sort']);

        $payload = array_merge(
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sffc_company_filter'),
                'defaults' => $defaults,
            ),
            $localize
        );

        wp_localize_script($this->explorer_script_handle, 'SFFCCompanyExplorerData', $payload);
    }

    /**
     * Clear cached profile payload
     */
    public static function clear_profile_cache($company_id) {
        if (!$company_id) {
            return;
        }
        delete_transient('sffc_company_profile_' . $company_id);
    }

    /**
     * Render company profile markup
     */
    public function render_profile($company_id) {
        if (!$company_id) {
            return '';
        }

        $profile = $this->get_company_profile($company_id);

        if (empty($profile) || empty($profile['basic_info'])) {
            return '';
        }

        ob_start();
        include SFFC_PLUGIN_DIR . 'templates/company-profile.php';
        return ob_get_clean();
    }

    /**
     * Get complete company profile
     */
    public function get_company_profile($company_id) {
        // Check cache first
        $cache_key = 'sffc_company_profile_' . $company_id;
        $cached_profile = get_transient($cache_key);
        
        if ($cached_profile !== false) {
            return $cached_profile;
        }
        
        // Build profile
        $profile = array(
            'basic_info' => $this->get_basic_info($company_id),
            'metrics' => $this->get_current_metrics($company_id),
            'recent_news' => $this->get_recent_news($company_id),
            'portfolio' => $this->get_portfolio_companies($company_id),
            'team' => $this->get_team_members($company_id),
            'jobs' => $this->get_open_positions($company_id),
            'active_funds' => $this->get_active_funds($company_id),
            'market_activity' => $this->get_market_activity($company_id)
        );
        
        // Cache the profile
        set_transient($cache_key, $profile, $this->cache_duration);
        
        return $profile;
    }
    
    /**
     * Get basic company information
     */
    private function get_basic_info($company_id) {
        $post = get_post($company_id);
        
        if (!$post) {
            return null;
        }
        
        $canonical_name = class_exists('SFFC_Company_Title_Helper')
            ? SFFC_Company_Title_Helper::get_canonical_name($post)
            : $post->post_title;

        $info = array(
            'id' => $company_id,
            'name' => $post->post_title,
            'canonical_name' => $canonical_name,
            'slug' => $post->post_name,
            'description' => $post->post_content,
            'logo' => get_the_post_thumbnail_url($company_id, 'medium'),
            'aum' => $this->format_aum(get_post_meta($company_id, '_sffc_aum', true)),
            'founded' => get_post_meta($company_id, '_sffc_founded', true),
            'headquarters' => get_post_meta($company_id, '_sffc_headquarters', true),
            'regions' => $this->parse_list(get_post_meta($company_id, '_sffc_regions', true)),
            'sectors' => $this->parse_list(get_post_meta($company_id, '_sffc_sectors', true)),
            'website' => get_post_meta($company_id, '_sffc_website', true),
            'linkedin' => get_post_meta($company_id, '_sffc_linkedin', true)
        );
        
        return $info;
    }
    
    /**
     * Get current metrics
     */
    private function get_current_metrics($company_id) {
        global $wpdb;
        
        $metrics = array(
            'portfolio_companies' => get_post_meta($company_id, '_sffc_portfolio_companies', true) ?: 0,
            'news_today' => get_post_meta($company_id, '_sffc_news_count_today', true) ?: 0,
            'news_week' => get_post_meta($company_id, '_sffc_news_count_week', true) ?: 0,
            'active_deals' => get_post_meta($company_id, '_sffc_active_deals', true) ?: 0,
            'total_exits' => $this->get_exit_count($company_id),
            'avg_hold_period' => $this->get_avg_hold_period($company_id),
            'irr' => get_post_meta($company_id, '_sffc_avg_irr', true) ?: 'N/A'
        );
        
        return $metrics;
    }
    
    /**
     * Get recent news
     */
    private function get_recent_news($company_id, $limit = 10) {
        global $wpdb;

        $news_items = array();
        $table_name = $wpdb->prefix . 'sffc_company_news_links';

        $news_links = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
            WHERE company_id = %d 
            ORDER BY created_at DESC 
            LIMIT %d",
            $company_id,
            $limit
        ));

        foreach ($news_links as $link) {
            $news_data = $this->get_news_item_data($link->news_item_id);
            if ($news_data) {
                $news_data['relevance_score'] = $link->relevance_score;
                $news_data['matched_terms'] = json_decode($link->matched_terms);
                $news_data['time_ago'] = $this->time_ago($link->created_at);
                $news_items[] = $news_data;
            }
        }

        if (!empty($news_items)) {
            return $news_items;
        }

        $company = get_post($company_id);
        if (!$company || !class_exists('SFFC_XML_Feed_Processor')) {
            return array();
        }

        $feed_processor = SFFC_XML_Feed_Processor::get_instance();
        $company_name = class_exists('SFFC_Company_Title_Helper')
            ? SFFC_Company_Title_Helper::get_canonical_name($company)
            : $company->post_title;

        $headlines = $feed_processor->get_company_news_from_feeds($company_name);

        if (empty($headlines)) {
            return array();
        }

        foreach (array_slice($headlines, 0, $limit) as $headline) {
            $news_items[] = array(
                'id' => md5(($headline['link'] ?? '') . ($headline['title'] ?? '')),
                'title' => $headline['title'] ?? '',
                'description' => $headline['description'] ?? '',
                'link' => $headline['link'] ?? '',
                'source' => $headline['source'] ?? '',
                'time_ago' => $headline['time'] ?? '',
                'relevance_score' => null,
                'matched_terms' => array(),
            );
        }

        return $news_items;
    }
    
    /**
     * Get news item data
     */
    private function get_news_item_data($news_id) {
        // Check if it's stored as a post
        $post = get_post($news_id);
        if ($post) {
            $source_url = get_post_meta($news_id, '_sffc_news_source_url', true);
            if (empty($source_url)) {
                $source_url = get_post_meta($news_id, '_news_source_url', true);
            }

            $source = get_post_meta($news_id, '_sffc_news_source', true);
            if (empty($source)) {
                $source = get_post_meta($news_id, '_news_source', true);
            }

            $published_ts = intval(get_post_meta($news_id, '_sffc_news_pub_date', true));
            if ($published_ts <= 0) {
                $published_ts = strtotime($post->post_date_gmt . ' GMT');
            }

            $description = $post->post_excerpt ? $post->post_excerpt : wp_trim_words(wp_strip_all_tags($post->post_content), 50);

            $time_reference = $published_ts ? gmdate('Y-m-d H:i:s', $published_ts) : $post->post_date;

            return array(
                'id' => $news_id,
                'title' => get_the_title($news_id),
                'description' => $description,
                'link' => $source_url ? $source_url : get_permalink($news_id),
                'source' => $source,
                'date' => $time_reference,
                'time_ago' => $this->time_ago($time_reference)
            );
        }
        
        // Otherwise check transient cache for feed items
        $cached_news = get_transient('sffc_news_item_' . $news_id);
        if ($cached_news) {
            return $cached_news;
        }
        
        return null;
    }
    
    /**
     * Get portfolio companies
     */
    private function get_portfolio_companies($company_id) {
        // This would typically pull from a portfolio companies table
        // For now, return mock data structure
        $portfolio = get_post_meta($company_id, '_sffc_portfolio_list', true);
        
        if (empty($portfolio)) {
            return array();
        }
        
        return json_decode($portfolio, true);
    }
    
    /**
     * Get active funds
     */
    private function get_active_funds($company_id) {
        $funds = get_post_meta($company_id, '_sffc_active_funds', true);

        if (empty($funds)) {
            return array();
        }

        if (is_string($funds)) {
            $decoded = json_decode($funds, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $funds = $decoded;
            } else {
                return array();
            }
        }

        return is_array($funds) ? $funds : array();
    }

    /**
     * Get team members
     */
    private function get_team_members($company_id) {
        // Query for team members associated with company
        $team = get_post_meta($company_id, '_sffc_team_members', true);
        
        if (empty($team)) {
            return array();
        }
        
        return json_decode($team, true);
    }
    
    /**
     * Get open positions
     */
    private function get_open_positions($company_id) {
        $company_name = get_the_title($company_id);

        $jobs = get_posts(array(
            'post_type' => 'sffc_job',
            'posts_per_page' => 10,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_sffc_company_id',
                    'value' => $company_id,
                    'compare' => '='
                ),
                array(
                    'key' => 'sffc_company',
                    'value' => $company_name,
                    'compare' => 'LIKE'
                ),
                array(
                    'key' => 'sffc_company_name',
                    'value' => $company_name,
                    'compare' => 'LIKE'
                )
            )
        ));

        $positions = array();

        foreach ($jobs as $job) {
            $location = get_post_meta($job->ID, 'sffc_location', true);
            if (empty($location)) {
                $location = get_post_meta($job->ID, 'sffc_location_city', true);
            }

            $type = get_post_meta($job->ID, 'sffc_time_type', true);
            if (empty($type)) {
                $type = get_post_meta($job->ID, 'sffc_employment_type', true);
            }

            $posted = get_post_meta($job->ID, 'sffc_posted_date_display', true);
            if (empty($posted)) {
                $posted = $this->time_ago($job->post_date);
            }

            $positions[] = array(
                'id' => $job->ID,
                'title' => $job->post_title,
                'location' => $location,
                'type' => $type,
                'posted' => $posted,
                'url' => get_permalink($job->ID)
            );
        }

        return $positions;
    }
    
    /**
     * Get market activity
     */
    private function get_market_activity($company_id) {
        // Calculate market activity score based on news, deals, etc.
        $news_count = get_post_meta($company_id, '_sffc_news_count_week', true) ?: 0;
        $deal_count = get_post_meta($company_id, '_sffc_active_deals', true) ?: 0;
        
        $activity_score = ($news_count * 2) + ($deal_count * 10);
        
        return array(
            'score' => $activity_score,
            'level' => $this->get_activity_level($activity_score),
            'trend' => $this->get_activity_trend($company_id)
        );
    }
    
    /**
     * Get activity level
     */
    private function get_activity_level($score) {
        if ($score >= 50) return 'Very High';
        if ($score >= 30) return 'High';
        if ($score >= 15) return 'Moderate';
        if ($score >= 5) return 'Low';
        return 'Very Low';
    }
    
    /**
     * Get activity trend
     */
    private function get_activity_trend($company_id) {
        $current_week = get_post_meta($company_id, '_sffc_news_count_week', true) ?: 0;
        $last_week = get_post_meta($company_id, '_sffc_news_count_last_week', true) ?: 0;
        
        if ($current_week > $last_week) return 'up';
        if ($current_week < $last_week) return 'down';
        return 'stable';
    }
    
    /**
     * Get exit count
     */
    private function get_exit_count($company_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sffc_deal_tracking';
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
            WHERE company_id = %d 
            AND deal_type = 'exit'",
            $company_id
        )) ?: 0;
    }
    
    /**
     * Get average hold period
     */
    private function get_avg_hold_period($company_id) {
        // This would calculate based on deal data
        // For now return placeholder
        return '4.5 years';
    }
    
    /**
     * Format AUM
     */
    private function format_aum($aum) {
        if (empty($aum)) return 'N/A';
        
        $aum = floatval($aum);
        
        if ($aum >= 1000000000000) {
            return '$' . round($aum / 1000000000000, 1) . 'T';
        } elseif ($aum >= 1000000000) {
            return '$' . round($aum / 1000000000, 1) . 'B';
        } elseif ($aum >= 1000000) {
            return '$' . round($aum / 1000000, 1) . 'M';
        }
        
        return '$' . number_format($aum);
    }
    
    /**
     * Format deal size
     */
    private function format_deal_size($size) {
        if (empty($size)) return 'Undisclosed';
        
        if (is_numeric($size)) {
            return $this->format_aum($size);
        }
        
        return $size;
    }

    private function normalize_financial_value($value) {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (!is_string($value) || $value === '') {
            return null;
        }

        $clean = preg_replace('/[^0-9.]/', '', $value);
        if ($clean === '' || !is_numeric($clean)) {
            return null;
        }

        return (float) $clean;
    }
    
    /**
     * Parse comma-separated list
     */
    private function parse_list($string) {
        if (empty($string)) return array();
        
        return array_map('trim', explode(',', $string));
    }
    
    /**
     * Time ago format
     */
    private function time_ago($datetime) {
        $time = strtotime($datetime);
        $current_time = current_time('timestamp');
        $diff = $current_time - $time;
        
        if ($diff < 60) {
            return 'just now';
        } elseif ($diff < 3600) {
            $mins = floor($diff / 60);
            return $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } else {
            return date('M j, Y', $time);
        }
    }
    
    /**
     * Get company cards for display with filter support
     */
    public function get_company_cards($args = array()) {
        $defaults = array(
            'per_page' => 12,
            'page' => 1,
            'orderby' => 'aum_desc',
            'search' => '',
            'tags' => array(),
        );

        $args = wp_parse_args($args, $defaults);

        $per_page = max(1, min(48, intval($args['per_page'])));
        $page     = max(1, intval($args['page']));

        $allowed_sorts = array('aum_desc', 'aum_asc', 'name_az', 'name_za', 'latest');
        $orderby = in_array($args['orderby'], $allowed_sorts, true) ? $args['orderby'] : 'aum_desc';

        $query_args = array(
            'post_type' => 'sffc_company',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $page,
            's' => sanitize_text_field($args['search']),
            'ignore_sticky_posts' => true,
        );

        $tags = array_filter(array_map('intval', (array) $args['tags']));
        if (!empty($tags)) {
            $query_args['tax_query'] = array(
                array(
                    'taxonomy' => 'sffc_company_tag',
                    'field' => 'term_id',
                    'terms' => $tags,
                    'operator' => 'AND',
                ),
            );
        }

        switch ($orderby) {
            case 'aum_asc':
                $query_args['meta_key'] = '_sffc_aum';
                $query_args['orderby'] = 'meta_value_num';
                $query_args['order'] = 'ASC';
                break;
            case 'name_az':
                $query_args['orderby'] = 'title';
                $query_args['order'] = 'ASC';
                break;
            case 'name_za':
                $query_args['orderby'] = 'title';
                $query_args['order'] = 'DESC';
                break;
            case 'latest':
                $query_args['orderby'] = 'date';
                $query_args['order'] = 'DESC';
                break;
            case 'aum_desc':
            default:
                $query_args['meta_key'] = '_sffc_aum';
                $query_args['orderby'] = 'meta_value_num';
                $query_args['order'] = 'DESC';
                break;
        }

        $query = new WP_Query($query_args);

        $items = array();

        if ($query->have_posts()) {
            foreach ($query->posts as $post) {
                $company_id = $post->ID;

                $aum_raw = get_post_meta($company_id, '_sffc_aum', true);
                $aum_value = $this->normalize_financial_value($aum_raw);
                $aum_display = $aum_value ? $this->format_aum($aum_value) : ($aum_raw ?: 'N/A');

                $portfolio_count = intval(get_post_meta($company_id, '_sffc_portfolio_companies', true));
                $news_week = intval(get_post_meta($company_id, '_sffc_news_count_week', true));
                $regions = $this->parse_list(get_post_meta($company_id, '_sffc_regions', true));
                $sectors = $this->parse_list(get_post_meta($company_id, '_sffc_sectors', true));
                $headquarters = get_post_meta($company_id, '_sffc_headquarters', true);

                $tags_for_card = array();
                $term_objects = wp_get_post_terms($company_id, 'sffc_company_tag');
                if (!is_wp_error($term_objects)) {
                    foreach ($term_objects as $term) {
                        $tags_for_card[] = array(
                            'id' => $term->term_id,
                            'name' => $term->name,
                            'slug' => $term->slug,
                        );
                    }
                }

                $excerpt = has_excerpt($company_id)
                    ? get_the_excerpt($company_id)
                    : wp_trim_words(wp_strip_all_tags($post->post_content), 24);

                $items[] = array(
                    'id' => $company_id,
                    'name' => get_the_title($company_id),
                    'permalink' => get_permalink($company_id),
                    'logo' => get_the_post_thumbnail_url($company_id, 'medium'),
                    'aum' => $aum_display,
                    'aum_raw' => $aum_value,
                    'portfolio_count' => $portfolio_count,
                    'news_week' => $news_week,
                    'regions' => $regions,
                    'sectors' => $sectors,
                    'headquarters' => $headquarters,
                    'excerpt' => $excerpt,
                    'tags' => $tags_for_card,
                );
            }
        }

        return array(
            'items' => $items,
            'pagination' => array(
                'page' => $page,
                'per_page' => $per_page,
                'total_pages' => (int) $query->max_num_pages,
                'total_items' => (int) $query->found_posts,
            ),
        );
    }

    public function get_company_filter_groups() {
        $taxonomy = 'sffc_company_tag';

        if (!taxonomy_exists($taxonomy)) {
            return array();
        }

        $parents = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'parent' => 0,
            'orderby' => 'name',
            'order' => 'ASC',
        ));

        if (is_wp_error($parents)) {
            return array();
        }

        $groups = array();

        foreach ($parents as $parent) {
            $children = get_terms(array(
                'taxonomy' => $taxonomy,
                'hide_empty' => false,
                'parent' => $parent->term_id,
                'orderby' => 'name',
                'order' => 'ASC',
            ));

            if (is_wp_error($children)) {
                $children = array();
            }

            $options = array();

            if (!empty($children)) {
                $options[] = array(
                    'id' => $parent->term_id,
                    'name' => $parent->name,
                    'slug' => $parent->slug,
                    'is_parent' => true,
                );

                foreach ($children as $child) {
                    $options[] = array(
                        'id' => $child->term_id,
                        'name' => $child->name,
                        'slug' => $child->slug,
                        'is_parent' => false,
                    );
                }
            } else {
                $options[] = array(
                    'id' => $parent->term_id,
                    'name' => $parent->name,
                    'slug' => $parent->slug,
                    'is_parent' => false,
                );
            }

            $groups[] = array(
                'id' => $parent->term_id,
                'name' => $parent->name,
                'slug' => $parent->slug,
                'options' => $options,
            );
        }

        return $groups;
    }
    
    /**
     * AJAX handler for company profile
     */
    public function ajax_get_company_profile() {
        $company_id = isset($_POST['company_id']) ? intval($_POST['company_id']) : 0;
        
        if (!$company_id) {
            wp_send_json_error('Invalid company ID');
        }
        
        $profile = $this->get_company_profile($company_id);
        
        if (!$profile) {
            wp_send_json_error('Company not found');
        }
        
        wp_send_json_success($profile);
    }
    
    /**
     * AJAX handler for company cards
     */
    public function ajax_get_company_cards() {
        check_ajax_referer('sffc_company_filter', 'nonce');

        $payload = wp_unslash($_POST);

        $per_page = isset($payload['perPage']) ? intval($payload['perPage']) : 12;
        $page = isset($payload['page']) ? intval($payload['page']) : 1;
        $sort = isset($payload['sort']) ? sanitize_text_field($payload['sort']) : 'aum_desc';
        $search = isset($payload['search']) ? sanitize_text_field($payload['search']) : '';
        $tags = array();

        if (isset($payload['tags'])) {
            $tags = array_filter(array_map('intval', (array) $payload['tags']));
        }

        $cards = $this->get_company_cards(array(
            'per_page' => $per_page,
            'page' => $page,
            'orderby' => $sort,
            'search' => $search,
            'tags' => $tags,
        ));

        wp_send_json_success($cards);
    }

    /**
     * Render company profile shortcode
     */
    public function render_company_profile_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'slug' => ''
        ), $atts);

        $company_id = $atts['id'];

        if (empty($company_id) && !empty($atts['slug'])) {
            $company = get_page_by_path($atts['slug'], OBJECT, 'sffc_company');
            if ($company) {
                $company_id = $company->ID;
            }
        }

        if (!$company_id) {
            return '<p>Company not found</p>';
        }

        $this->enqueue_assets();

        $output = $this->render_profile($company_id);

        return $output !== '' ? $output : '<p>Company profile unavailable.</p>';
    }

    
    /**
     * Render company cards shortcode
     */
    public function render_company_cards_shortcode($atts) {
        $atts = shortcode_atts(array(
            'per_page' => 12,
            'page' => 1,
            'sort' => 'aum_desc',
            'tags' => '',
            'search' => '',
        ), $atts);

        $tags = array();
        if (!empty($atts['tags'])) {
            $provided = array_filter(array_map('trim', explode(',', $atts['tags'])));
            foreach ($provided as $tag) {
                if ($tag === '') {
                    continue;
                }

                if (is_numeric($tag)) {
                    $tags[] = (int) $tag;
                    continue;
                }

                $term = get_term_by('slug', sanitize_title($tag), 'sffc_company_tag');
                if ($term && !is_wp_error($term)) {
                    $tags[] = $term->term_id;
                }
            }
        }

        $cards = $this->get_company_cards(array(
            'per_page' => $atts['per_page'],
            'page' => $atts['page'],
            'orderby' => $atts['sort'],
            'search' => $atts['search'],
            'tags' => $tags,
        ));

        $card_items = $cards['items'];
        $pagination = $cards['pagination'];

        ob_start();
        include SFFC_PLUGIN_DIR . 'templates/company-cards.php';
        return ob_get_clean();
    }

    public function render_company_explorer_shortcode($atts) {
        $atts = shortcode_atts(array(
            'per_page' => 12,
            'sort' => 'aum_desc',
        ), $atts);

        $per_page = max(1, min(48, intval($atts['per_page'])));
        $sort = sanitize_text_field($atts['sort']);

        $filters = $this->get_company_filter_groups();
        $cards = $this->get_company_cards(array(
            'per_page' => $per_page,
            'page' => 1,
            'orderby' => $sort,
        ));

        $this->enqueue_explorer_assets(array(
            'perPage' => $per_page,
            'sort' => $sort,
            'filters' => $filters,
            'initialQuery' => array(
                'page' => 1,
                'perPage' => $per_page,
                'sort' => $sort,
                'search' => '',
                'tags' => array(),
            ),
        ));

        $card_items = $cards['items'];
        $pagination = $cards['pagination'];

        ob_start();
        include SFFC_PLUGIN_DIR . 'templates/company-explorer.php';
        return ob_get_clean();
    }
}

// Initialize
SFFC_Company_Profile_Aggregator::get_instance();
