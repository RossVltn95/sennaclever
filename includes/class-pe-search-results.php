<?php

/**
 * Private Equity Search Results
 * Google-style results page with PE-specific enhancements
 * 
 * @package SennaCareers
 * @since 10.23.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_PE_Search_Results
{

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Results per page
     */
    private $results_per_page = 20;

    /**
     * Query parameter used for pagination
     */
    private $pagination_query_var = 'sffc_page';

    /**
     * Current search query
     */
    private $current_query = '';

    /**
     * Current search mode
     */
    private $current_mode = 'jobs';

    /**
     * Search start time for performance metrics
     */
    private $search_start_time = 0;

    /**
     * Active advanced filters from URL/UI
     */
    private $active_filters = array();

    /**
     * Get singleton instance
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
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        // Register shortcode for results page
        add_shortcode('sffc_pe_search_results', array($this, 'render_search_results'));

        // Enqueue assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));

        // AJAX handlers for results
        add_action('wp_ajax_sffc_load_more_results', array($this, 'ajax_load_more_results'));
        add_action('wp_ajax_nopriv_sffc_load_more_results', array($this, 'ajax_load_more_results'));

        // AJAX handler for top-level filters
        add_action('wp_ajax_sffc_load_filtered_results', array($this, 'ajax_load_filtered_results'));
        add_action('wp_ajax_nopriv_sffc_load_filtered_results', array($this, 'ajax_load_filtered_results'));

        // AJAX handler for quick actions
        add_action('wp_ajax_sffc_quick_action', array($this, 'ajax_quick_action'));
        add_action('wp_ajax_nopriv_sffc_quick_action', array($this, 'ajax_quick_action'));

        // Block search results URLs with query params from being indexed
        add_action('wp_head', array($this, 'add_search_noindex_tags'), 1);
        add_action('template_redirect', array($this, 'redirect_search_query_params'), 1);
    }

    /**
     * Add noindex/nofollow meta tags and canonical for search results with query params
     * This prevents Google from indexing URLs like /search-results/?q=...
     */
    public function add_search_noindex_tags()
    {
        // Check if we're on the search results page with query parameters
        if (!is_page() || !has_shortcode(get_post()->post_content ?? '', 'sffc_pe_search_results')) {
            return;
        }

        // If there are any search-related query params, add noindex
        if (!empty($_GET['q']) || !empty($_GET['mode']) || !empty($_GET['sffc_page']) || !empty($_GET['filter'])) {
            echo '<meta name="robots" content="noindex, nofollow">' . "\n";
            // Add canonical pointing to the clean URL
            echo '<link rel="canonical" href="' . esc_url(get_permalink()) . '">' . "\n";
        }
    }

    /**
     * Redirect search results URLs with query params to clean URL
     * This tells Google the old URLs are no longer valid
     */
    public function redirect_search_query_params()
    {
        // Check if we're on the search results page
        if (!is_page() || !has_shortcode(get_post()->post_content ?? '', 'sffc_pe_search_results')) {
            return;
        }

        // If this is a bot/crawler accessing a URL with query params, redirect to clean URL
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $is_bot = preg_match('/bot|crawl|spider|slurp|googlebot|bingbot|yandex|baidu/i', $user_agent);

        // Redirect bots accessing URLs with ?q= params to the clean page
        if ($is_bot && (!empty($_GET['q']) || !empty($_GET['mode']) || !empty($_GET['sffc_page']))) {
            wp_redirect(get_permalink(), 301);
            exit;
        }
    }

    /**
     * Extract search parameters from both SEO-friendly URLs and traditional query parameters
     */
    private function extract_search_parameters($atts = array()) {
        $query = '';
        $mode = 'jobs';
        
        // Method 1: Check for SEO-friendly URL parameters (from rewrite rules)
        if (function_exists('get_query_var')) {
            $seo_query = get_query_var('search_query');
            $seo_mode = get_query_var('mode');
            
            if (!empty($seo_query) && class_exists('SFFC_SEO_Permalinks')) {
                // Convert slug back to readable query
                $query = SFFC_SEO_Permalinks::slug_to_query($seo_query);
            }
            
            if (!empty($seo_mode)) {
                $mode = $seo_mode;
            }
        }
        
        // Method 2: Fallback to traditional query parameters
        if (empty($query)) {
            $query = sanitize_text_field($_GET['q'] ?? $atts['pre_filter'] ?? '');
        }
        
        if (empty($mode) || $mode === 'jobs') {
            $mode = sanitize_text_field($_GET['mode'] ?? ($atts['mode'] ?: 'jobs'));
        }
        
        return array(
            'query' => $query,
            'mode' => $mode
        );
    }

    /**
     * Generate SEO-friendly pagination URL
     */
    private function generate_pagination_url($page = 1) {
        // Use SEO-friendly URLs if available
        if (class_exists('SFFC_SEO_Permalinks') && !empty($this->current_query)) {
            return SFFC_SEO_Permalinks::generate_search_url($this->current_query, $this->current_mode, $page > 1 ? $page : null);
        }
        
        // Fallback to traditional query parameters
        $current_url = get_permalink();
        if (!$current_url) {
            $current_url = home_url(add_query_arg(array()));
        }
        
        // Remove existing pagination parameter
        if (function_exists('remove_query_arg')) {
            $current_url = remove_query_arg(array('page', $this->pagination_query_var), $current_url);
        }
        
        // Prepare args
        $args = array();
        
        // Add search query
        if (!empty($this->current_query)) {
            $args['q'] = $this->current_query;
        }
        
        // Add mode
        if (!empty($this->current_mode)) {
            $args['mode'] = $this->current_mode;
        }
        
        // Add pagination
        if ($page > 1) {
            $args[$this->pagination_query_var] = $page;
        }
        
        // Preserve any active filters from URL
        $filter_params = array('filter', 'location', 'salary_min', 'salary_max', 'experience', 'date_from', 'date_to', 'company', 'industry');
        foreach ($filter_params as $param) {
            if (!empty($_GET[$param])) {
                $args[$param] = sanitize_text_field($_GET[$param]);
            }
        }
        
        return add_query_arg($args, $current_url);
    }

    /**
     * Render search results shortcode
     */
    public function render_search_results($atts = array())
    {
        $atts = shortcode_atts(array(
            'show_search_bar' => 'true',
            'results_per_page' => 20,
            'pre_filter' => '',           // Pre-filter search query
            'location' => '',             // Pre-filter location
            'category' => '',             // Pre-filter category
            'mode' => '',                 // Pre-set search mode (jobs/insights)
            'salary_min' => '',           // Pre-filter minimum salary
            'salary_max' => '',           // Pre-filter maximum salary
            'experience' => '',           // Pre-filter experience level
            'company' => '',              // Pre-filter company
            'filter' => 'all'             // Pre-set primary filter
        ), $atts);

        $this->results_per_page = max(1, intval($atts['results_per_page']));

        // Get search parameters from both SEO-friendly URLs and traditional query parameters
        $search_params = $this->extract_search_parameters($atts);
        $this->current_query = $search_params['query'];
        $this->current_mode = $search_params['mode'];
        if ($this->current_mode === 'news') {
            $this->current_mode = 'insights';
        }
        $page = $this->get_requested_page();
        $active_filter = sanitize_text_field($_GET['filter'] ?? $atts['filter']);

        $this->active_filters = $this->collect_active_filters_from_request();
        $this->active_filters['primary'] = $active_filter ?: 'all';

        // Apply shortcode attributes to filter parameters (only if not already set in URL)
        $this->apply_shortcode_filters($atts);

        // Start performance timer
        $this->search_start_time = microtime(true);

        // Handle empty query case properly
        if (empty($this->current_query)) {
            $search_results = array(
                'results' => array(),
                'total' => 0,
                'page' => 1,
                'per_page' => $this->results_per_page,
                'search_time' => 0,
                'query' => '',
                'mode' => $this->current_mode
            );
        } else {
            // Ensure search tables exist before searching
            $this->ensure_search_tables_exist();

            // Perform search using Phase 3.1 backend
        $search_results = $this->perform_search_with_backend($this->current_query, $this->current_mode, $page);
    }

        $search_results = $this->apply_active_filters_to_results($search_results);

        // Calculate search time
        $search_time = round((microtime(true) - $this->search_start_time) * 1000) / 1000;

        // Generate unique ID for this results instance
        $results_id = 'sffc-results-' . wp_generate_uuid4();
        $analysis = $this->build_results_intelligence($search_results);
        $filters = $this->get_primary_filters($analysis);
        $sidebar_html = $this->render_results_sidebar($analysis);
        $has_sidebar = trim($sidebar_html) !== '';

        // Start output buffering
        ob_start();
?>

        <div class="sffc-pe-results-container" id="<?php echo esc_attr($results_id); ?>" data-total-results="<?php echo esc_attr($analysis['total']); ?>">
            <header class="sffc-results-header">
                <div class="sffc-results-header-inner">
                    <div class="sffc-results-search-bar">
                        <?php echo $this->render_compact_search_bar($analysis); ?>
                    </div>
                    <?php echo $this->render_results_header_cta($analysis); ?>
                </div>
            </header>

            <section class="sffc-search-info-bar" data-search-time="<?php echo esc_attr($search_time); ?>">
                <div class="sffc-search-stats">
                    <span class="sffc-results-count"><?php esc_html_e('About', 'senna-finance'); ?> <strong><?php echo number_format($analysis['total']); ?></strong> <?php esc_html_e('results', 'senna-finance'); ?></span>
                    <span class="sffc-search-time">(<strong><?php echo esc_html($search_time); ?></strong> <?php esc_html_e('seconds', 'senna-finance'); ?>)</span>
                    <?php if (!empty($analysis['query'])): ?>
                        <span class="sffc-search-query"><?php esc_html_e('for', 'senna-finance'); ?> "<strong><?php echo esc_html($analysis['query']); ?></strong>"</span>
                    <?php endif; ?>
                    <?php echo $this->render_search_summary_badges($analysis); ?>
                </div>

                <?php echo $this->render_advanced_filters_bar(); ?>

                <button class="sffc-mobile-filter-toggle" type="button" aria-expanded="false" aria-controls="sffc-results-main">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="4" y1="12" x2="20" y2="12"></line>
                        <line x1="12" y1="4" x2="12" y2="20"></line>
                    </svg>
                    <span><?php esc_html_e('Filters', 'senna-finance'); ?></span>
                </button>
            </section>

            <div class="sffc-results-body">
                <main class="sffc-results-main" id="sffc-results-main" role="main">
                    <?php if ($analysis['has_results']): ?>
                        <div class="sffc-results-list">
                            <?php echo $this->build_results_list_html($analysis); ?>
                        </div>

                        <?php if ($analysis['total'] > $analysis['per_page']): ?>
                            <div class="sffc-pagination">
                                <?php echo $this->render_pagination($analysis['total'], $analysis['page']); ?>
                            </div>
                        <?php endif; ?>

                        <?php echo $this->render_people_also_ask_section($analysis); ?>
                        <?php echo $this->render_related_searches_section($analysis); ?>
                    <?php else: ?>
                        <?php echo $this->render_no_results_suggestions($analysis); ?>
                    <?php endif; ?>
                </main>

                <?php if ($has_sidebar): ?>
                    <aside class="sffc-results-rail" aria-label="<?php esc_attr_e('Search intelligence', 'senna-finance'); ?>">
                        <?php echo $sidebar_html; ?>
                    </aside>
                <?php endif; ?>
            </div>

            <?php echo $this->render_mobile_mode_nav($analysis); ?>
        </div>

    <?php
        return ob_get_clean();
    }

    public function ajax_load_filtered_results()
    {
        if (!check_ajax_referer('sffc_results_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed. Please refresh and try again.', 'senna-finance')), 403);
        }

        $query = isset($_POST['query']) ? sanitize_text_field(wp_unslash($_POST['query'])) : '';
        $mode = isset($_POST['mode']) ? sanitize_text_field(wp_unslash($_POST['mode'])) : 'jobs';
        $filter = isset($_POST['filter']) ? sanitize_key($_POST['filter']) : 'all';
        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        if ($page < 1) {
            $page = 1;
        }
        $extra_filters = array();
        if (!empty($_POST['filters'])) {
            $decoded = json_decode(wp_unslash($_POST['filters']), true);
            if (is_array($decoded)) {
                $extra_filters = $decoded;
            }
        }

        if (empty($query)) {
            wp_send_json_error(array('message' => __('Missing search query.', 'senna-finance')));
        }

        $this->current_query = $query;
        $this->current_mode = $mode ? strtolower($mode) : 'jobs';
        if ($this->current_mode === 'news') {
            $this->current_mode = 'insights';
        }

        $this->ensure_search_tables_exist();

        $override_filters = $this->normalize_filter_array(array_merge(array('primary' => $filter ?: 'all'), $extra_filters));
        $this->active_filters = $override_filters;

        $search_results = $this->perform_search_with_backend(
            $this->current_query,
            $this->current_mode,
            $page,
            array_merge(array('primary' => $filter ?: 'all'), $override_filters)
        );

        $search_results = $this->apply_active_filters_to_results($search_results);
        $analysis = $this->build_results_intelligence($search_results);

        // Build results HTML including pagination
        $html = '';
        if ($analysis['has_results']) {
            $html .= '<div class="sffc-results-list">';
            $html .= $this->build_results_list_html($analysis);
            $html .= '</div>';
            if ($analysis['total'] > $analysis['per_page']) {
                $html .= '<div class="sffc-pagination">';
                $html .= $this->render_pagination($analysis['total'], $page);
                $html .= '</div>';
            }
        } else {
            $html = $this->render_no_results_suggestions($analysis);
        }

        wp_send_json_success(array('html' => $html));
    }

    /**
     * Determine the requested pagination page from query vars.
     */
    private function get_requested_page()
    {
        $query_var = $this->pagination_query_var;

        // Check for SEO-friendly URL page parameter first
        $page = 0;
        if (function_exists('get_query_var')) {
            $seo_page = get_query_var($query_var);
            if (!empty($seo_page)) {
                $page = intval($seo_page);
            }
        }
        
        // Fallback to traditional query parameter
        if ($page <= 0) {
            $page = isset($_GET[$query_var]) ? intval($_GET[$query_var]) : 0;
        }

        if ($page < 1 && isset($_GET['page'])) {
            $page = intval($_GET['page']);
        }

        if ($page < 1 && function_exists('get_query_var')) {
            $alt = get_query_var($query_var);
            if ($alt) {
                $page = intval($alt);
            }

            if ($page < 1) {
                $alt = get_query_var('page');
                if ($alt) {
                    $page = intval($alt);
                }
            }

            if ($page < 1) {
                $alt = get_query_var('paged');
                if ($alt) {
                    $page = intval($alt);
                }
            }
        }

        if ($page < 1) {
            $page = 1;
        }

        return $page;
    }

    /**
     * Render individual result item - Google style with PE enhancements
     */
    private function render_result_item($result)
    {
        $normalized_type = $this->normalize_result_type($result['type'] ?? '');
        $logo = $this->get_result_logo($result);
        $source_url = $this->get_source_url($result);
        $meta = $result['meta'] ?? array();
        $source_name = $this->get_source_name($result, $meta);
        $breadcrumb = $this->generate_breadcrumb($result);
        $excerpt = $this->generate_smart_excerpt($result, $this->current_query);
        $quick_actions = $this->get_quick_actions($result);
        $pe_insights = $this->generate_pe_insights($result);
        $title_text = $result['title'] ?? '';
        $title_display = ($normalized_type === 'jobs' || $normalized_type === 'recruiters')
            ? esc_html($title_text)
            : $this->highlight_keywords($title_text, $this->current_query);

        $company = $this->extract_meta_value($meta, array('company', 'sffc_company_name', 'company_name')) ?: ($result['company'] ?? '');
        $location = $this->extract_meta_value($meta, array('location', 'sffc_location', 'headquarters')) ?: ($result['location'] ?? '');
        $salary_min = $this->normalize_salary_value($this->extract_meta_value($meta, array('salary_min', 'sffc_salary_min', 'salary')));
        $salary_max = $this->normalize_salary_value($this->extract_meta_value($meta, array('salary_max', 'sffc_salary_max')));
        $salary_band = $this->format_salary_band($salary_min, $salary_max);
        $experience = $this->extract_meta_value($meta, array('experience_level', 'experience', 'experience_years'));
        $posted = $this->format_posted_date($result['date'] ?? $this->extract_meta_value($meta, array('date', 'published_at', 'news_date')));
        $score = isset($result['score']) ? floatval($result['score']) : null;
        $badges = $this->render_result_badges($result, $score);
        $tags = $this->build_result_tags($result, $company, $meta);

        if ($normalized_type === 'insights' && $posted) {
            $source_url = trim($source_url);
            $source_url = $source_url ? $source_url . ' • ' . $posted : $posted;
            $posted = '';
        }

        $data_attrs = array(
            'data-result-id="' . esc_attr($result['id']) . '"',
            'data-result-type="' . esc_attr($result['type']) . '"',
            'data-result-kind="' . esc_attr($normalized_type) . '"'
        );

        if (!empty($company)) {
            $data_attrs[] = 'data-company="' . esc_attr($company) . '"';
        }
        if (!empty($location)) {
            $data_attrs[] = 'data-location="' . esc_attr($location) . '"';
        }
        if ($salary_band) {
            $data_attrs[] = 'data-salary-band="' . esc_attr($salary_band) . '"';
        }
        if ($score !== null) {
            $data_attrs[] = 'data-score="' . esc_attr(number_format($score, 2)) . '"';
        }

        ob_start();
    ?>

        <article class="sffc-result-item" <?php echo implode(' ', $data_attrs); ?>>

            <?php if ($normalized_type === 'recruiters'): ?>

                <?php echo $this->render_recruiter_result_content(
                    $result,
                    $meta,
                    $logo,
                    $source_name,
                    $source_url,
                    $breadcrumb,
                    $excerpt,
                    $badges,
                    $tags,
                    $quick_actions,
                    $pe_insights,
                    $company,
                    $location
                ); ?>

                </article>
                <?php return ob_get_clean(); ?>

            <?php elseif ($normalized_type === 'jobs'): ?>

                <?php echo $this->render_job_result_content(
                    $result,
                    $meta,
                    $logo,
                    $company,
                    $location,
                    $salary_band,
                    $experience,
                    $posted,
                    $badges,
                    $tags,
                    $quick_actions,
                    $pe_insights
                ); ?>

                </article>
                <?php return ob_get_clean(); ?>

            <?php else: ?>

            <div class="sffc-result-header">
                <div class="sffc-result-logo">
                    <?php echo $logo; ?>
                </div>

                <div class="sffc-result-meta">
                    <div class="sffc-result-source">
                        <span class="sffc-source-name"><?php echo esc_html($source_name); ?></span>
                        <span class="sffc-source-url"><?php echo esc_html($source_url); ?></span>
                    </div>
                    <div class="sffc-result-breadcrumb"><?php echo $breadcrumb; ?></div>
                </div>

                <div class="sffc-result-actions">
                    <?php if (!empty($badges)): ?>
                        <div class="sffc-result-badges"><?php echo $badges; ?></div>
                    <?php endif; ?>
                    <button class="sffc-action-btn sffc-bookmark-btn" title="<?php esc_attr_e('Bookmark', 'senna-finance'); ?>">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                        </svg>
                    </button>
                    <button class="sffc-action-btn sffc-share-btn" title="<?php esc_attr_e('Share', 'senna-finance'); ?>">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="18" cy="5" r="3"></circle>
                            <circle cx="6" cy="12" r="3"></circle>
                            <circle cx="18" cy="19" r="3"></circle>
                            <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                            <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                        </svg>
                    </button>
                    <button class="sffc-action-btn sffc-more-btn" title="<?php esc_attr_e('More options', 'senna-finance'); ?>">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="1"></circle>
                            <circle cx="19" cy="12" r="1"></circle>
                            <circle cx="5" cy="12" r="1"></circle>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="sffc-result-content">
                <h3 class="sffc-result-title">
                    <a href="<?php echo esc_url($result['url']); ?>" class="sffc-result-link" target="_blank" rel="noopener">
                        <?php echo $title_display; ?>
                    </a>
                </h3>

                <div class="sffc-result-excerpt">
                    <?php echo $excerpt; ?>
                </div>

                <?php if ($location || $salary_band || $experience || $posted): ?>
                    <div class="sffc-result-metadata">
                        <?php if ($location): ?>
                            <span class="sffc-meta-chip">
                                <svg aria-hidden="true" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="10" r="3"></circle>
                                    <path d="M12 21.7c5-4.7 8-9.7 8-11.7 0-4.4-3.6-8-8-8s-8 3.6-8 8c0 2 3 7 8 11.7z"></path>
                                </svg>
                                <?php echo esc_html($location); ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($salary_band): ?>
                            <span class="sffc-meta-chip">
                                <svg aria-hidden="true" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 1v22"></path>
                                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                                </svg>
                                <?php echo esc_html($salary_band); ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($experience): ?>
                            <span class="sffc-meta-chip">
                                <svg aria-hidden="true" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                </svg>
                                <?php echo esc_html($experience); ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($posted): ?>
                            <span class="sffc-meta-chip">
                                <svg aria-hidden="true" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <polyline points="12,6 12,12 16,14"></polyline>
                                </svg>
                                <?php echo esc_html($posted); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($pe_insights)): ?>
                <div class="sffc-pe-insights">
                    <?php echo $pe_insights; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($tags)): ?>
                <div class="sffc-result-tags">
                    <?php foreach ($tags as $tag): ?>
                        <span class="sffc-tag"><?php echo esc_html($tag); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($quick_actions)): ?>
                <div class="sffc-quick-actions-bar">
                    <?php foreach ($quick_actions as $action): ?>
                        <button class="sffc-quick-action-btn <?php echo esc_attr($action['class'] ?? ''); ?>"
                            data-action="<?php echo esc_attr($action['type']); ?>"
                            data-result-id="<?php echo esc_attr($result['id']); ?>"
                            data-job-id="<?php echo esc_attr($result['id']); ?>"
                            data-result-type="<?php echo esc_attr($result['type']); ?>">
                            <?php echo $action['icon']; ?>
                            <span><?php echo esc_html($action['label']); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php endif; ?>

        </article>

    <?php
        return ob_get_clean();
    }

    private function build_results_list_html($analysis)
    {
        if (empty($analysis['has_results']) || empty($analysis['results'])) {
            return '';
        }

        ob_start();
        $result_index = 0;
        foreach ($analysis['results'] as $result) {
            echo $this->render_result_item($result);

            if ($result_index === 0) {
                echo $this->render_search_spotlight_block($analysis);
            }

            if ($result_index === 3) {
                echo $this->render_trending_block($analysis);
            }

            $result_index++;
        }

        return ob_get_clean();
    }

    /**
     * Get result logo/favicon with letter fallback
     */
    private function get_result_logo($result)
    {
        // Try to get company logo first
        $logo_url = '';

        if (!empty($result['company_logo'])) {
            $logo_url = $result['company_logo'];
        } elseif (!empty($result['company'])) {
            // Try to find company logo from our database
            $logo_url = $this->get_company_logo($result['company']);
        }

        if ($logo_url) {
            return sprintf(
                '<img src="%s" alt="%s" class="sffc-result-favicon" loading="lazy">',
                esc_url($logo_url),
                esc_attr($result['company'] ?? 'Company')
            );
        }

        // Fallback to letter icon
        $letter = strtoupper(substr($result['company'] ?? $result['title'] ?? 'J', 0, 1));
        $colors = array('#1e293b', '#166534', '#1e40af', '#b91c1c', '#c2410c');
        $color = $colors[ord($letter) % count($colors)];

        return sprintf(
            '<div class="sffc-result-letter-icon" style="background: %s;">%s</div>',
            $color,
            $letter
        );
    }

    /**
     * Render recruiter specific result layout
     */
    private function render_recruiter_result_content($result, $meta, $logo, $source_name, $source_url, $breadcrumb, $excerpt, $badges, $tags, $quick_actions, $pe_insights, $company, $location)
    {
        $post_id = intval($result['id'] ?? 0);
        $profile_url = !empty($result['url']) ? $result['url'] : ($post_id ? get_permalink($post_id) : '#');

        $tagline = $this->fetch_recruiter_meta_value($post_id, $meta, array('tagline', 'sffc_recruiter_tagline', '_sffc_recruiter_tagline'));
        $rating = $this->fetch_recruiter_meta_value($post_id, $meta, array('rating', 'sffc_recruiter_rating', '_sffc_recruiter_rating'));
        $review_count = intval($this->fetch_recruiter_meta_value($post_id, $meta, array('review_count', 'sffc_recruiter_review_count', '_sffc_recruiter_review_count'), 0));
        if (!$review_count && $post_id) {
            $review_count = $this->count_recruiter_reviews($post_id);
        }
        $industries = $this->get_recruiter_term_names($post_id, 'sffc_recruiter_industry');
        $services = $this->get_recruiter_services_list($post_id, $meta);

        $founded = $this->fetch_recruiter_meta_value($post_id, $meta, array('founded', 'sffc_recruiter_founded', '_sffc_recruiter_founded'));
        $team_size = $this->fetch_recruiter_meta_value($post_id, $meta, array('size', 'sffc_recruiter_size', '_sffc_recruiter_size'));
        $role_focus = $this->fetch_recruiter_meta_value($post_id, $meta, array('role_focus', 'sffc_recruiter_role_focus', '_sffc_recruiter_role_focus'));
        $seniority = $this->fetch_recruiter_meta_value($post_id, $meta, array('candidate_seniority', 'sffc_recruiter_candidate_seniority', '_sffc_recruiter_candidate_seniority'));
        $placements = $this->fetch_recruiter_meta_value($post_id, $meta, array('metric_placements', 'sffc_recruiter_metric_placements', '_sffc_recruiter_metric_placements'));
        $mandates = $this->fetch_recruiter_meta_value($post_id, $meta, array('metric_mandates', 'sffc_recruiter_metric_mandates', '_sffc_recruiter_metric_mandates'));
        $nps = $this->fetch_recruiter_meta_value($post_id, $meta, array('metric_nps', 'sffc_recruiter_metric_nps', '_sffc_recruiter_metric_nps'));
        $time_to_place = $this->fetch_recruiter_meta_value($post_id, $meta, array('metric_time', 'sffc_recruiter_metric_time', '_sffc_recruiter_metric_time'));

        $overview_stats = array_filter(array(
            array('label' => __('Location', 'senna-finance'), 'value' => $location ?: $this->fetch_recruiter_meta_value($post_id, $meta, array('location', 'sffc_recruiter_location', '_sffc_recruiter_location'))),
            array('label' => __('Founded', 'senna-finance'), 'value' => $founded),
            array('label' => __('Team Size', 'senna-finance'), 'value' => $team_size),
            array('label' => __('Active Mandates', 'senna-finance'), 'value' => $mandates),
            array('label' => __('Placements', 'senna-finance'), 'value' => $placements),
            array('label' => __('NPS', 'senna-finance'), 'value' => $nps ? sprintf('%s / 100', $nps) : ''),
            array('label' => __('Time to Hire', 'senna-finance'), 'value' => $time_to_place ? sprintf('%s days', $time_to_place) : ''),
            array('label' => __('Role Focus', 'senna-finance'), 'value' => $role_focus),
            array('label' => __('Candidate Seniority', 'senna-finance'), 'value' => $seniority),
        ), function ($stat) {
            return !empty($stat['value']);
        });

        $breadcrumb_text = trim($breadcrumb);

        $rating_value = $rating !== '' ? floatval($rating) : null;

        $contact_cta = $this->fetch_recruiter_meta_value($post_id, $meta, array('cta_contact', 'sffc_recruiter_cta_contact', '_sffc_recruiter_cta_contact'));
        $cv_cta = $this->fetch_recruiter_meta_value($post_id, $meta, array('cta_cv', 'sffc_recruiter_cta_cv', '_sffc_recruiter_cta_cv'));

        ob_start();
        ?>
        <div class="sffc-recruiter-result">
            <div class="sffc-recruiter-top">
                <div class="sffc-recruiter-brand">
                    <div class="sffc-result-logo"><?php echo $logo; ?></div>
                    <div class="sffc-recruiter-brand-meta">
                        <span class="sffc-recruiter-eyebrow"><?php esc_html_e('Service Provider', 'senna-finance'); ?></span>
                        <h3 class="sffc-recruiter-name">
                            <a href="<?php echo esc_url($profile_url); ?>" class="sffc-result-link" target="_blank" rel="noopener">
                                <?php echo esc_html($result['title'] ?? $source_name); ?>
                            </a>
                        </h3>
                        <?php if (!empty($tagline)): ?>
                            <p class="sffc-recruiter-tagline"><?php echo esc_html($tagline); ?></p>
                        <?php endif; ?>
                        <p class="sffc-recruiter-byline">
                            <?php printf(esc_html__('By %s', 'senna-finance'), esc_html($source_name ?: $company ?: __('Verified Partner', 'senna-finance'))); ?>
                            <?php if ($location): ?>
                                <span class="sffc-divider">•</span>
                                <?php echo esc_html($location); ?>
                            <?php endif; ?>
                            <?php if ($source_url): ?>
                                <span class="sffc-divider">•</span>
                                <?php echo esc_html($source_url); ?>
                            <?php endif; ?>
                        </p>
                        <?php if ($breadcrumb_text): ?>
                            <p class="sffc-recruiter-breadcrumb"><?php echo esc_html($breadcrumb_text); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="sffc-recruiter-top-meta">
                    <?php if ($rating_value): ?>
                        <div class="sffc-recruiter-rating">
                            <span class="sffc-recruiter-rating-score"><?php echo esc_html(number_format_i18n($rating_value, 1)); ?></span>
                            <span class="sffc-recruiter-rating-text"><?php esc_html_e('out of 5', 'senna-finance'); ?></span>
                            <?php if ($review_count): ?>
                                <span class="sffc-recruiter-rating-count"><?php printf(esc_html__('(%s reviews)', 'senna-finance'), number_format_i18n($review_count)); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($badges)): ?>
                        <div class="sffc-recruiter-badges"><?php echo $badges; ?></div>
                    <?php endif; ?>
                    <div class="sffc-recruiter-actions">
                        <button class="sffc-action-pill sffc-bookmark-btn" title="<?php esc_attr_e('Save to My Lists', 'senna-finance'); ?>">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path></svg>
                            <?php esc_html_e('Save to My Lists', 'senna-finance'); ?>
                        </button>
                        <button class="sffc-action-pill sffc-share-btn" title="<?php esc_attr_e('Share provider', 'senna-finance'); ?>">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg>
                            <?php esc_html_e('Share', 'senna-finance'); ?>
                        </button>
                        <a href="<?php echo esc_url($profile_url); ?>" class="sffc-primary-link" target="_blank" rel="noopener"><?php esc_html_e('View Provider', 'senna-finance'); ?></a>
                    </div>
                </div>
            </div>

            <div class="sffc-recruiter-description">
                <h4><?php esc_html_e('Provider Description', 'senna-finance'); ?></h4>
                <p><?php echo wp_kses_post($excerpt); ?></p>
            </div>

            <div class="sffc-recruiter-grid">
                <?php if (!empty($overview_stats)): ?>
                    <div class="sffc-recruiter-panel">
                        <h5><?php esc_html_e('Overview', 'senna-finance'); ?></h5>
                        <ul>
                            <?php foreach ($overview_stats as $stat): ?>
                                <li><strong><?php echo esc_html($stat['label']); ?>:</strong> <span><?php echo esc_html($stat['value']); ?></span></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($industries)): ?>
                    <div class="sffc-recruiter-panel">
                        <h5><?php esc_html_e('Industries Serviced', 'senna-finance'); ?></h5>
                        <div class="sffc-recruiter-pills">
                            <?php foreach ($industries as $industry): ?>
                                <span class="sffc-recruiter-pill"><?php echo esc_html($industry); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($services)): ?>
                    <div class="sffc-recruiter-panel">
                        <h5><?php esc_html_e('Services Offered', 'senna-finance'); ?></h5>
                        <ul>
                            <?php foreach ($services as $service): ?>
                                <li><?php echo esc_html($service); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($pe_insights)): ?>
                <div class="sffc-pe-insights sffc-pe-insights--recruiter"><?php echo $pe_insights; ?></div>
            <?php endif; ?>

            <div class="sffc-recruiter-footer">
                <?php if (!empty($tags)): ?>
                    <div class="sffc-recruiter-footer-tags">
                        <?php foreach ($tags as $tag): ?>
                            <span class="sffc-tag"><?php echo esc_html($tag); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="sffc-recruiter-footer-links">
                    <?php if ($review_count && $profile_url): ?>
                        <a class="sffc-link-btn" href="<?php echo esc_url($profile_url . '#reviews'); ?>" target="_blank" rel="noopener">
                            <?php esc_html_e('Read Reviews', 'senna-finance'); ?>
                        </a>
                    <?php endif; ?>
                    <?php if ($contact_cta): ?>
                        <a class="sffc-link-btn" href="<?php echo esc_url($contact_cta); ?>" target="_blank" rel="noopener"><?php esc_html_e('Brief This Firm', 'senna-finance'); ?></a>
                    <?php endif; ?>
                    <?php if ($cv_cta): ?>
                        <a class="sffc-link-btn" href="<?php echo esc_url($cv_cta); ?>" target="_blank" rel="noopener"><?php esc_html_e('Submit CV', 'senna-finance'); ?></a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($quick_actions)): ?>
                <div class="sffc-quick-actions-bar">
                    <?php foreach ($quick_actions as $action): ?>
                        <button class="sffc-quick-action-btn <?php echo esc_attr($action['class'] ?? ''); ?>"
                            data-action="<?php echo esc_attr($action['type']); ?>"
                            data-result-id="<?php echo esc_attr($result['id']); ?>"
                            data-job-id="<?php echo esc_attr($result['id']); ?>"
                            data-result-type="<?php echo esc_attr($result['type']); ?>">
                            <?php echo $action['icon']; ?>
                            <span><?php echo esc_html($action['label']); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function fetch_recruiter_meta_value($post_id, $meta, $keys, $default = '')
    {
        $keys = (array) $keys;
        $value = $this->extract_meta_value($meta, $keys);
        if ($value !== '') {
            return $value;
        }

        if (!$post_id) {
            return $default;
        }

        foreach ($keys as $key) {
            $candidates = array($key);
            if ($key && $key[0] !== '_') {
                $candidates[] = '_' . $key;
            } elseif ($key) {
                $candidates[] = ltrim($key, '_');
            }

            foreach ($candidates as $candidate) {
                if (!$candidate) {
                    continue;
                }
                $stored = get_post_meta($post_id, $candidate, true);
                if ($stored !== '' && $stored !== null) {
                    return is_string($stored) ? trim($stored) : $stored;
                }
            }
        }

        return $default;
    }

    private function get_recruiter_term_names($post_id, $taxonomy)
    {
        if (!$post_id || !taxonomy_exists($taxonomy)) {
            return array();
        }

        $terms = get_the_terms($post_id, $taxonomy);
        if (empty($terms) || is_wp_error($terms)) {
            return array();
        }

        $names = array();
        foreach ($terms as $term) {
            if (!empty($term->name)) {
                $names[] = $term->name;
            }
        }

        return array_slice(array_unique($names), 0, 6);
    }

    private function get_recruiter_services_list($post_id, $meta)
    {
        $services = array();

        if (!empty($meta['services'])) {
            if (is_array($meta['services'])) {
                foreach ($meta['services'] as $service) {
                    if (is_array($service) && !empty($service['title'])) {
                        $services[] = trim($service['title']);
                    } elseif (is_string($service)) {
                        $services[] = trim($service);
                    }
                }
            } elseif (is_string($meta['services'])) {
                $services = array_map('trim', explode(',', $meta['services']));
            }
        }

        if (empty($services) && $post_id) {
            $stored = get_post_meta($post_id, '_sffc_recruiter_services', true);
            if (!empty($stored)) {
                if (is_string($stored)) {
                    $decoded = json_decode($stored, true);
                } else {
                    $decoded = $stored;
                }
                if (is_array($decoded)) {
                    foreach ($decoded as $service) {
                        if (is_array($service) && !empty($service['title'])) {
                            $services[] = trim($service['title']);
                        } elseif (is_string($service)) {
                            $services[] = trim($service);
                        }
                    }
                }
            }
        }

        $services = array_filter(array_unique($services));
        return array_slice($services, 0, 4);
    }

    private function count_recruiter_reviews($post_id)
    {
        if (!$post_id) {
            return 0;
        }

        $raw = get_post_meta($post_id, '_sffc_recruiter_testimonials', true);
        if (empty($raw)) {
            return 0;
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
        } else {
            $decoded = $raw;
        }

        if (!is_array($decoded)) {
            return 0;
        }

        $count = 0;
        foreach ($decoded as $entry) {
            if (is_array($entry) && (!empty($entry['quote']) || !empty($entry['name']))) {
                $count++;
            }
        }

        return $count;
    }

    private function render_job_result_content($result, $meta, $logo, $company, $location, $salary_band, $experience, $posted, $badges, $tags, $quick_actions, $pe_insights)
    {
        $job_url = !empty($result['url']) ? $result['url'] : '#';
        $title = $result['title'] ?? $company;
        $job_type = $this->extract_meta_value($meta, array('time_type', 'job_type', 'employment_type'));
        $remote = $this->extract_meta_value($meta, array('work_model', 'remote_type'));
        $highlights = $this->extract_job_highlights($meta, $result);
        $skills = $this->extract_job_skills($meta);
        $summary = $this->extract_meta_value($meta, array('intro', 'sffc_summary_markdown', 'summary'));
        $cta_url = $this->extract_meta_value($meta, array('_sffc_job_application_url', 'sffc_application_url', 'sffc_apply_url', 'apply_url', 'application_url'));        
        $cta_url = $cta_url ?: $job_url;
        $job_family = $this->extract_meta_value($meta, array('job_family', 'sffc_job_family'));
        $role_focus = $this->extract_meta_value($meta, array('role_focus', 'sffc_role_focus', 'team_focus'));
        $recruiter = $this->extract_meta_value($meta, array('recruiter', 'sffc_recruiter_name'));
        $education = $this->extract_meta_value($meta, array('education_requirements', 'sffc_education_requirements'));
        $qualifications = $this->extract_meta_value($meta, array('qualifications', 'sffc_qualifications'));
        $pe_years = $this->extract_meta_value($meta, array('pe_years_experience', 'sffc_pe_years_experience'));
        $view_count = $this->extract_meta_value($meta, array('view_count', 'sffc_view_count'));

        $snapshot = array_filter(array(
            array('label' => __('Job Family', 'senna-finance'), 'value' => $job_family),
            array('label' => __('Focus', 'senna-finance'), 'value' => $role_focus),
            array('label' => __('Recruiter', 'senna-finance'), 'value' => $recruiter),
            array('label' => __('Experience', 'senna-finance'), 'value' => $experience),
            array('label' => __('PE Years', 'senna-finance'), 'value' => $pe_years),
            array('label' => __('Visibility', 'senna-finance'), 'value' => $view_count ? sprintf(__('%s views', 'senna-finance'), number_format_i18n($view_count)) : ''),
        ), function ($row) {
            return !empty($row['value']);
        });

        ob_start();
        ?>
        <div class="sffc-job-result">
            <div class="sffc-job-top">
                <div class="sffc-job-brand">
                    <div class="sffc-result-logo"><?php echo $logo; ?></div>
                    <div>
                        <h3 class="sffc-job-title">
                            <a href="<?php echo esc_url($job_url); ?>" class="sffc-result-link" target="_blank" rel="noopener">
                                <?php echo esc_html($title); ?>
                            </a>
                        </h3>
                        <?php if ($company): ?>
                            <p class="sffc-job-company"><?php echo esc_html($company); ?></p>
                        <?php endif; ?>
                        <div class="sffc-job-submeta">
                            <?php if ($location): ?>
                                <span><?php echo esc_html($location); ?></span>
                            <?php endif; ?>
                            <?php if ($posted): ?>
                                <span>• <?php echo esc_html($posted); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="sffc-job-actions">
                    <?php if (!empty($badges)): ?>
                        <div class="sffc-job-badges"><?php echo $badges; ?></div>
                    <?php endif; ?>
                    <a class="sffc-primary-link" href="<?php echo esc_url($cta_url); ?>" target="_blank" rel="noopener">
                        <?php esc_html_e('View role', 'senna-finance'); ?>
                    </a>
                </div>
            </div>

            <div class="sffc-job-meta-chips">
                <?php if ($salary_band): ?>
                    <span><?php echo esc_html($salary_band); ?></span>
                <?php endif; ?>
                <?php if ($experience): ?>
                    <span><?php echo esc_html($experience); ?></span>
                <?php endif; ?>
                <?php if ($job_type): ?>
                    <span><?php echo esc_html($job_type); ?></span>
                <?php endif; ?>
                <?php if ($remote): ?>
                    <span><?php echo esc_html($remote); ?></span>
                <?php endif; ?>
            </div>

            <?php if (!empty($summary)): ?>
                <div class="sffc-job-summary">
                    <?php echo wp_kses_post(wpautop(wp_trim_words($summary, 40))); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($snapshot)): ?>
                <h5 class="sffc-job-section-label"><?php esc_html_e('Opportunity Snapshot', 'senna-finance'); ?></h5>
                <ul class="sffc-job-snapshot">
                    <?php foreach ($snapshot as $row): ?>
                        <li>
                            <strong><?php echo esc_html($row['label']); ?></strong>
                            <span><?php echo esc_html($row['value']); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if (!empty($highlights)): ?>
                <div class="sffc-job-highlights">
                    <h5><?php esc_html_e('Core Takeaways', 'senna-finance'); ?></h5>
                    <ul>
                        <?php foreach ($highlights as $highlight): ?>
                            <li><?php echo esc_html($highlight); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($education) || !empty($qualifications)): ?>
                <div class="sffc-job-qualifications">
                    <?php if (!empty($education)): ?>
                        <div>
                            <h5><?php esc_html_e('Education', 'senna-finance'); ?></h5>
                            <p><?php echo esc_html($education); ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($qualifications)): ?>
                        <div>
                            <h5><?php esc_html_e('Qualifications', 'senna-finance'); ?></h5>
                            <p><?php echo esc_html($qualifications); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($skills)): ?>
                <div class="sffc-job-skills">
                    <h5><?php esc_html_e('Key Skills', 'senna-finance'); ?></h5>
                    <div class="sffc-job-skill-list">
                        <?php foreach ($skills as $skill): ?>
                            <span><?php echo esc_html($skill); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($pe_insights)): ?>
                <div class="sffc-pe-insights sffc-pe-insights--job"><?php echo $pe_insights; ?></div>
            <?php endif; ?>

            <div class="sffc-job-footer">
                <?php if (!empty($tags)): ?>
                    <div class="sffc-recruiter-footer-tags">
                        <?php foreach ($tags as $tag): ?>
                            <span class="sffc-tag"><?php echo esc_html($tag); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($quick_actions)): ?>
                    <div class="sffc-quick-actions-bar">
                        <?php foreach ($quick_actions as $action): ?>
                            <button class="sffc-quick-action-btn <?php echo esc_attr($action['class'] ?? ''); ?>"
                                data-action="<?php echo esc_attr($action['type']); ?>"
                                data-result-id="<?php echo esc_attr($result['id']); ?>"
                                data-job-id="<?php echo esc_attr($result['id']); ?>"
                                data-result-type="<?php echo esc_attr($result['type']); ?>">
                                <?php echo $action['icon']; ?>
                                <span><?php echo esc_html($action['label']); ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function collect_active_filters_from_request()
    {
        $filters = array();
        $filters['location'] = isset($_GET['location']) ? sanitize_text_field(wp_unslash($_GET['location'])) : '';
        $filters['salary_min'] = isset($_GET['salary_min']) ? intval($_GET['salary_min']) : '';
        $filters['salary_max'] = isset($_GET['salary_max']) ? intval($_GET['salary_max']) : '';
        $filters['industries'] = $this->sanitize_multi_value($_GET['industries'] ?? array());
        $filters['roles'] = $this->sanitize_multi_value($_GET['roles'] ?? array());
        $filters['functions'] = $this->sanitize_multi_value($_GET['functions'] ?? array());
        $filters['regions'] = $this->sanitize_multi_value($_GET['regions'] ?? array());

        return $this->normalize_filter_array($filters);
    }

    private function normalize_filter_array($filters)
    {
        $normalized = array();
        foreach ($filters as $key => $value) {
            if (in_array($key, array('industries', 'roles', 'functions', 'regions'), true)) {
                $normalized[$key] = is_array($value) ? array_values(array_unique(array_filter($value))) : array();
                continue;
            }

            if ($key === 'salary_min' || $key === 'salary_max') {
                $normalized[$key] = $value !== '' ? intval($value) : '';
                continue;
            }

            $normalized[$key] = is_string($value) ? trim($value) : $value;
        }

        return $normalized;
    }

    private function sanitize_multi_value($value)
    {
        if (empty($value)) {
            return array();
        }

        if (is_string($value)) {
            $value = array_map('trim', explode(',', $value));
        }

        if (!is_array($value)) {
            return array();
        }

        $clean = array();
        foreach ($value as $item) {
            if (is_array($item)) {
                if (isset($item['title'])) {
                    $item = $item['title'];
                } elseif (isset($item['name'])) {
                    $item = $item['name'];
                } else {
                    $item = implode(' ', $item);
                }
            }

            $item = sanitize_text_field(wp_unslash($item));
            if ($item !== '') {
                $clean[] = $item;
            }
        }

        return array_values(array_unique($clean));
    }

    private function render_advanced_filters_bar()
    {
        $location_value = $this->active_filters['location'] ?? '';
        $salary_min = $this->active_filters['salary_min'] ?? '';
        $salary_max = $this->active_filters['salary_max'] ?? '';
        $selected_industries = $this->active_filters['industries'] ?? array();
        $selected_roles = $this->active_filters['roles'] ?? array();
        $selected_functions = $this->active_filters['functions'] ?? array();
        $selected_regions = $this->active_filters['regions'] ?? array();

        $industries = $this->get_filter_term_options('sffc_recruiter_industry');
        $roles = $this->get_filter_term_options('sffc_recruiter_role');
        $functions = $this->get_filter_term_options('sffc_recruiter_function');
        $regions = $this->get_filter_term_options('sffc_recruiter_region');

        ob_start();
        ?>
        <div class="sffc-advanced-filters">
            <div class="sffc-advanced-filter" data-filter-key="location">
                <button type="button" class="sffc-advanced-filter-toggle" aria-expanded="false">
                    <span><?php esc_html_e('Location', 'senna-finance'); ?></span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </button>
                <div class="sffc-advanced-filter-panel" hidden>
                    <form class="sffc-advanced-filter-form" data-filter-form="location">
                        <label><?php esc_html_e('City or region', 'senna-finance'); ?></label>
                        <input type="text" name="location" value="<?php echo esc_attr($location_value); ?>" placeholder="<?php esc_attr_e('e.g. London, New York', 'senna-finance'); ?>">
                        <div class="sffc-advanced-filter-actions">
                            <button type="button" class="sffc-advanced-filter-clear" data-filter="location"><?php esc_html_e('Clear', 'senna-finance'); ?></button>
                            <button type="button" class="sffc-advanced-filter-apply" data-filter="location"><?php esc_html_e('Apply', 'senna-finance'); ?></button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="sffc-advanced-filter" data-filter-key="salary">
                <button type="button" class="sffc-advanced-filter-toggle" aria-expanded="false">
                    <span><?php esc_html_e('Salary', 'senna-finance'); ?></span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </button>
                <div class="sffc-advanced-filter-panel" hidden>
                    <form class="sffc-advanced-filter-form" data-filter-form="salary">
                        <div class="sffc-advanced-input-row">
                            <label><?php esc_html_e('Min (£)', 'senna-finance'); ?></label>
                            <input type="number" name="salary_min" value="<?php echo esc_attr($salary_min); ?>" min="0" step="1000">
                        </div>
                        <div class="sffc-advanced-input-row">
                            <label><?php esc_html_e('Max (£)', 'senna-finance'); ?></label>
                            <input type="number" name="salary_max" value="<?php echo esc_attr($salary_max); ?>" min="0" step="1000">
                        </div>
                        <div class="sffc-advanced-filter-actions">
                            <button type="button" class="sffc-advanced-filter-clear" data-filter="salary"><?php esc_html_e('Clear', 'senna-finance'); ?></button>
                            <button type="button" class="sffc-advanced-filter-apply" data-filter="salary"><?php esc_html_e('Apply', 'senna-finance'); ?></button>
                        </div>
                    </form>
                </div>
            </div>

            <?php echo $this->render_filter_checklist('industries', __('Industries', 'senna-finance'), $industries, $selected_industries); ?>
            <?php echo $this->render_filter_checklist('roles', __('Roles', 'senna-finance'), $roles, $selected_roles); ?>
            <?php echo $this->render_filter_checklist('functions', __('Functions', 'senna-finance'), $functions, $selected_functions); ?>
            <?php echo $this->render_filter_checklist('regions', __('Regions', 'senna-finance'), $regions, $selected_regions); ?>

            <button type="button" class="sffc-advanced-clear-all"><?php esc_html_e('Clear all filters', 'senna-finance'); ?></button>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_filter_checklist($key, $label, $options, $selected)
    {
        if (empty($options)) {
            return '';
        }

        ob_start();
        ?>
        <div class="sffc-advanced-filter" data-filter-key="<?php echo esc_attr($key); ?>">
            <button type="button" class="sffc-advanced-filter-toggle" aria-expanded="false">
                <span><?php echo esc_html($label); ?></span>
                <?php if (!empty($selected)): ?>
                    <span class="sffc-advanced-selected-count"><?php echo count($selected); ?></span>
                <?php endif; ?>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
            </button>
            <div class="sffc-advanced-filter-panel" hidden>
                <form class="sffc-advanced-filter-form" data-filter-form="<?php echo esc_attr($key); ?>">
                    <div class="sffc-advanced-checkbox-grid">
                        <?php foreach ($options as $option): ?>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($key); ?>[]" value="<?php echo esc_attr($option); ?>" <?php checked(in_array($option, $selected, true)); ?>>
                                <?php echo esc_html($option); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="sffc-advanced-filter-actions">
                        <button type="button" class="sffc-advanced-filter-clear" data-filter="<?php echo esc_attr($key); ?>"><?php esc_html_e('Clear', 'senna-finance'); ?></button>
                        <button type="button" class="sffc-advanced-filter-apply" data-filter="<?php echo esc_attr($key); ?>"><?php esc_html_e('Apply', 'senna-finance'); ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function get_filter_term_options($taxonomy)
    {
        if (!taxonomy_exists($taxonomy)) {
            return array();
        }

        $terms = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'number' => 50,
            'orderby' => 'name',
            'order' => 'ASC'
        ));

        if (is_wp_error($terms) || empty($terms)) {
            return array();
        }

        $options = array();
        foreach ($terms as $term) {
            if (!empty($term->name)) {
                $options[] = $term->name;
            }
        }

        return $options;
    }

    private function apply_active_filters_to_results($search_results)
    {
        $filters = $this->active_filters;
        if (empty($filters)) {
            return $search_results;
        }

        $hasAdvanced = false;
        foreach (array('location', 'salary_min', 'salary_max', 'industries', 'roles', 'functions', 'regions') as $key) {
            if (!empty($filters[$key])) {
                $hasAdvanced = true;
                break;
            }
        }

        if (!$hasAdvanced) {
            return $search_results;
        }

        $filtered = array();
        foreach ($search_results['results'] as $result) {
            if ($this->result_matches_filters($result, $filters)) {
                $filtered[] = $result;
            }
        }

        $search_results['results'] = $filtered;
        $search_results['total'] = count($filtered);
        $search_results['has_results'] = !empty($filtered);

        return $search_results;
    }

    private function result_matches_filters($result, $filters)
    {
        $meta = $result['meta'] ?? array();

        if (!empty($filters['location'])) {
            $needle = strtolower($filters['location']);
            $haystack = strtolower($this->extract_meta_value($meta, array('location', 'sffc_location', 'region')) ?: ($result['location'] ?? ''));
            if (strpos($haystack, $needle) === false) {
                return false;
            }
        }

        $bounds = $this->get_result_salary_bounds($meta, $result);
        if (!empty($filters['salary_min']) && $bounds['max'] && $bounds['max'] < intval($filters['salary_min'])) {
            return false;
        }
        if (!empty($filters['salary_max']) && $bounds['min'] && $bounds['min'] > intval($filters['salary_max'])) {
            return false;
        }

        $checks = array(
            'industries' => array('industries', 'industry', 'sector', 'sffc_recruiter_industry'),
            'roles' => array('role_focus', 'roles', 'sffc_recruiter_role'),
            'functions' => array('function', 'functions', 'sffc_recruiter_function'),
            'regions' => array('regions', 'region', 'locations', 'sffc_recruiter_region')
        );

        foreach ($checks as $filter_key => $keys) {
            if (empty($filters[$filter_key])) {
                continue;
            }

            $haystack_values = $this->collect_terms_from_result($result, $meta, $keys);
            $haystack = strtolower(implode(' | ', $haystack_values));
            $matched = false;
            foreach ($filters[$filter_key] as $value) {
                if ($haystack && strpos($haystack, strtolower($value)) !== false) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                return false;
            }
        }

        return true;
    }

    private function collect_terms_from_result($result, $meta, $keys)
    {
        $values = array();
        foreach ($keys as $key) {
            if (isset($meta[$key])) {
                $values = array_merge($values, $this->sanitize_multi_value($meta[$key]));
            }
            if (isset($result[$key])) {
                $values = array_merge($values, $this->sanitize_multi_value($result[$key]));
            }
        }

        return array_values(array_unique(array_filter($values)));
    }

    private function get_result_salary_bounds($meta, $result)
    {
        $min = $this->normalize_salary_value($this->extract_meta_value($meta, array('salary_min', 'sffc_salary_min')));
        $max = $this->normalize_salary_value($this->extract_meta_value($meta, array('salary_max', 'sffc_salary_max')));

        if (!$min && !empty($result['salary_min'])) {
            $min = intval($result['salary_min']);
        }
        if (!$max && !empty($result['salary_max'])) {
            $max = intval($result['salary_max']);
        }

        return array('min' => $min, 'max' => $max);
    }

    private function extract_job_highlights($meta, $result)
    {
        $sources = array(
            $meta['highlights'] ?? '',
            $meta['responsibilities'] ?? '',
            $meta['sffc_responsibilities'] ?? '',
            $result['description'] ?? ''
        );

        foreach ($sources as $source) {
            if (empty($source)) {
                continue;
            }
            $sentences = $this->split_into_sentences($source);
            if (!empty($sentences)) {
                return array_slice($sentences, 0, 3);
            }
        }

        return array();
    }

    private function extract_job_skills($meta)
    {
        $skills = array();
        $candidates = array('skills', 'sffc_skills', 'skills_list', 'sffc_skills_list');
        foreach ($candidates as $key) {
            if (!empty($meta[$key])) {
                $skills = $this->sanitize_multi_value($meta[$key]);
                if (!empty($skills)) {
                    break;
                }
            }
        }

        return array_slice($skills, 0, 6);
    }

    private function split_into_sentences($text)
    {
        $text = wp_strip_all_tags($text);
        if ($text === '') {
            return array();
        }

        $sentences = preg_split('/(?<=[.!?])\s+/', $text);
        $sentences = array_map('trim', $sentences);
        return array_filter($sentences);
    }

    /**
     * Generate smart excerpt with keyword highlighting
     */
    private function generate_smart_excerpt($result, $query)
    {
        $content = $result['content'] ?? $result['description'] ?? '';
        $keywords = explode(' ', strtolower($query));

        // Find the best snippet around keywords
        $best_snippet = $this->find_best_snippet($content, $keywords);

        // Highlight keywords
        $highlighted = $this->highlight_keywords($best_snippet, $query);

        return $highlighted;
    }

    /**
     * Find best content snippet around keywords
     */
    private function find_best_snippet($content, $keywords, $length = 160)
    {
        $content = strip_tags($content);
        $words = explode(' ', $content);

        if (count($words) <= 25) {
            return $content;
        }

        $best_score = 0;
        $best_start = 0;
        $snippet_words = 25;

        // Score each potential snippet
        for ($i = 0; $i <= count($words) - $snippet_words; $i++) {
            $snippet = array_slice($words, $i, $snippet_words);
            $snippet_text = strtolower(implode(' ', $snippet));

            $score = 0;
            foreach ($keywords as $keyword) {
                if (strlen($keyword) > 2) {
                    $score += substr_count($snippet_text, strtolower($keyword)) * strlen($keyword);
                }
            }

            if ($score > $best_score) {
                $best_score = $score;
                $best_start = $i;
            }
        }

        $snippet_words_array = array_slice($words, $best_start, $snippet_words);
        $snippet = implode(' ', $snippet_words_array);

        // Add ellipsis if needed
        if ($best_start > 0) $snippet = '...' . $snippet;
        if ($best_start + $snippet_words < count($words)) $snippet .= '...';

        return $snippet;
    }

    /**
     * Highlight keywords in text
     */
    private function highlight_keywords($text, $query)
    {
        $keywords = explode(' ', $query);

        foreach ($keywords as $keyword) {
            $keyword = trim($keyword);
            if (strlen($keyword) > 2) {
                $pattern = '/\b(' . preg_quote($keyword, '/') . ')\b/i';
                $text = preg_replace($pattern, '<strong class="sffc-highlight">$1</strong>', $text);
            }
        }

        return $text;
    }

    /**
     * Generate PE-specific insights - THE GAME CHANGER
     */
    private function generate_pe_insights($result)
    {
        $insights = array();

        $normalized_type = $this->normalize_result_type($result['type'] ?? '');

        switch ($normalized_type) {
            case 'jobs':
                $insights = $this->generate_job_insights($result);
                break;
            case 'insights':
                $insights = $this->generate_news_insights($result);
                break;
            case 'companies':
                $insights = $this->generate_company_insights($result);
                break;
            case 'deals':
                $insights = $this->generate_deal_insights($result);
                break;
        }

        if (empty($insights)) return '';

        ob_start();
    ?>
        <div class="sffc-insights-container">
            <?php foreach ($insights as $insight): ?>
                <div class="sffc-insight-item" data-insight-type="<?php echo esc_attr($insight['type']); ?>">
                    <div class="sffc-insight-icon"><?php echo $insight['icon']; ?></div>
                    <div class="sffc-insight-content">
                        <span class="sffc-insight-label"><?php echo esc_html($insight['label']); ?></span>
                        <span class="sffc-insight-value"><?php echo esc_html($insight['value']); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php
        return ob_get_clean();
    }

    /**
     * Generate job-specific insights
     */
    private function generate_job_insights($result)
    {
        $insights = array();

        // Salary insight
        if (!empty($result['salary_min']) && !empty($result['salary_max'])) {
            $insights[] = array(
                'type' => 'salary',
                'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v6m0 6v6"></path><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>',
                'label' => 'Salary Range',
                'value' => '£' . number_format($result['salary_min']) . ' - £' . number_format($result['salary_max'])
            );
        }

        // Experience level
        if (!empty($result['experience_level'])) {
            $insights[] = array(
                'type' => 'experience',
                'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M12 1v6m0 6v6"></path></svg>',
                'label' => 'Experience',
                'value' => $result['experience_level']
            );
        }

        // Similar roles count
        $similar_count = $this->get_similar_jobs_count($result);
        if ($similar_count > 0) {
            $insights[] = array(
                'type' => 'similar',
                'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',
                'label' => 'Similar roles',
                'value' => $similar_count . ' available'
            );
        }

        return $insights;
    }

    /**
     * Generate news-specific insights for both sffc_pe_news and sffc_pe_deal
     */
    private function generate_news_insights($result)
    {
        $insights = array();

        // Determine if this is a news article or deal
        $post_type = $result['post_type'] ?? $result['type'] ?? '';
        $is_deal = (strpos($post_type, 'deal') !== false);

        // Publication date insight
        if (!empty($result['date'])) {
            $date = new DateTime($result['date']);
            $now = new DateTime();
            $diff = $now->diff($date);

            $time_ago = '';
            if ($diff->days == 0) {
                $time_ago = 'Today';
            } elseif ($diff->days == 1) {
                $time_ago = 'Yesterday';
            } elseif ($diff->days < 7) {
                $time_ago = $diff->days . ' days ago';
            } elseif ($diff->days < 30) {
                $weeks = floor($diff->days / 7);
                $time_ago = $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
            } else {
                $time_ago = $date->format('M j, Y');
            }

            $insights[] = array(
                'type' => 'date',
                'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>',
                'label' => 'Published',
                'value' => $time_ago
            );
        }

        if ($is_deal) {
            // Deal-specific insights
            $meta = $result['meta'] ?? array();

            // Deal value
            if (!empty($meta['deal_value'])) {
                $insights[] = array(
                    'type' => 'value',
                    'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v6m0 6v6"></path><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>',
                    'label' => 'Deal Value',
                    'value' => $meta['deal_value']
                );
            }

            // Deal stage
            if (!empty($meta['deal_stage'])) {
                $insights[] = array(
                    'type' => 'stage',
                    'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>',
                    'label' => 'Stage',
                    'value' => $meta['deal_stage']
                );
            }

            // Industry
            if (!empty($meta['industry'])) {
                $insights[] = array(
                    'type' => 'industry',
                    'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg>',
                    'label' => 'Industry',
                    'value' => $meta['industry']
                );
            }
        } else {
            // News-specific insights
            $meta = $result['meta'] ?? array();

            // News source
            if (!empty($meta['source'])) {
                $insights[] = array(
                    'type' => 'source',
                    'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>',
                    'label' => 'Source',
                    'value' => $meta['source']
                );
            }

            // Category
            if (!empty($meta['category'])) {
                $insights[] = array(
                    'type' => 'category',
                    'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect></svg>',
                    'label' => 'Category',
                    'value' => $meta['category']
                );
            }
        }

        // Reading time for longer content
        if (!empty($result['content'])) {
            $word_count = str_word_count(strip_tags($result['content']));
            if ($word_count > 100) {
                $reading_time = ceil($word_count / 200); // Average reading speed
                $insights[] = array(
                    'type' => 'reading',
                    'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12,6 12,12 16,14"></polyline></svg>',
                    'label' => 'Read time',
                    'value' => $reading_time . ' min'
                );
            }
        }

        return $insights;
    }

    /**
     * Generate company-specific insights
     */
    private function generate_company_insights($result)
    {
        $insights = array();
        $meta = $result['meta'] ?? array();

        // Company size
        if (!empty($meta['company_size'])) {
            $insights[] = array(
                'type' => 'size',
                'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',
                'label' => 'Company Size',
                'value' => $meta['company_size']
            );
        }

        // Assets under management
        if (!empty($meta['aum'])) {
            $insights[] = array(
                'type' => 'aum',
                'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>',
                'label' => 'AUM',
                'value' => $meta['aum']
            );
        }

        // Founded year
        if (!empty($meta['founded'])) {
            $insights[] = array(
                'type' => 'founded',
                'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>',
                'label' => 'Founded',
                'value' => $meta['founded']
            );
        }

        // Open positions count
        $open_positions = $this->get_company_open_positions_count($result);
        if ($open_positions > 0) {
            $insights[] = array(
                'type' => 'jobs',
                'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>',
                'label' => 'Open Positions',
                'value' => $open_positions . ' available'
            );
        }

        return $insights;
    }

    /**
     * Generate deal-specific insights
     */
    private function generate_deal_insights($result)
    {
        $insights = array();
        $meta = $result['meta'] ?? array();

        // Deal value
        if (!empty($meta['deal_value'])) {
            $insights[] = array(
                'type' => 'value',
                'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v6m0 6v6"></path><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>',
                'label' => 'Deal Value',
                'value' => $meta['deal_value']
            );
        }

        // Deal type
        if (!empty($meta['deal_type'])) {
            $insights[] = array(
                'type' => 'type',
                'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path></svg>',
                'label' => 'Deal Type',
                'value' => $meta['deal_type']
            );
        }

        // Industry
        if (!empty($meta['industry'])) {
            $insights[] = array(
                'type' => 'industry',
                'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg>',
                'label' => 'Industry',
                'value' => $meta['industry']
            );
        }

        // Deal stage
        if (!empty($meta['deal_stage'])) {
            $insights[] = array(
                'type' => 'stage',
                'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>',
                'label' => 'Stage',
                'value' => $meta['deal_stage']
            );
        }

        return $insights;
    }

    /**
     * Get count of similar jobs for job insights
     */
    private function get_similar_jobs_count($result)
    {
        global $wpdb;

        $company = $result['company'] ?? '';
        $location = $result['location'] ?? '';

        if (empty($company) && empty($location)) {
            return 0;
        }

        $search_table = $wpdb->prefix . 'sffc_search_index';
        $where_conditions = array("post_type = 'sffc_job'", "id != %d");
        $params = array($result['id']);

        if (!empty($company)) {
            $where_conditions[] = "content LIKE %s";
            $params[] = '%' . $wpdb->esc_like($company) . '%';
        }

        if (!empty($location)) {
            $where_conditions[] = "content LIKE %s";
            $params[] = '%' . $wpdb->esc_like($location) . '%';
        }

        $query = "SELECT COUNT(*) FROM $search_table WHERE " . implode(' AND ', $where_conditions);
        $prepared_query = $wpdb->prepare($query, $params);

        return (int) $wpdb->get_var($prepared_query);
    }

    /**
     * Get count of open positions at a company
     */
    private function get_company_open_positions_count($result)
    {
        global $wpdb;

        $company_name = $result['title'] ?? $result['company'] ?? '';

        if (empty($company_name)) {
            return 0;
        }

        $search_table = $wpdb->prefix . 'sffc_search_index';
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM $search_table WHERE post_type = 'sffc_job' AND content LIKE %s",
            '%' . $wpdb->esc_like($company_name) . '%'
        );

        return (int) $wpdb->get_var($query);
    }

    /**
     * Get quick actions for result type
     */
    private function get_quick_actions($result)
    {
        $actions = array();

        switch ($result['type']) {
            case 'job':
                // Send CV button - opens job application
                $actions[] = array(
                    'type' => 'send_cv',
                    'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/></svg>',
                    'label' => 'Send CV',
                    'class' => 'sffc-btn-primary'
                );

                // Introduce Me button with premium S logo
                $actions[] = array(
                    'type' => 'introduce_me',
                    'icon' => '<div class="sffc-premium-logo">S</div>',
                    'label' => 'Introduce Me',
                    'class' => 'sffc-btn-premium'
                );
                break;

            case 'company':
                $actions[] = array(
                    'type' => 'jobs',
                    'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>',
                    'label' => 'View Jobs'
                );
                $actions[] = array(
                    'type' => 'analysis',
                    'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"></path><path d="M18.7 8l-5.1 5.2-2.8-2.7L7 14.3"></path></svg>',
                    'label' => 'Company Analysis'
                );
                break;
        }

        return $actions;
    }

    /**
     * Render compact search bar for results page
     */
    private function render_compact_search_bar($analysis = array())
    {
        $search_interface = SFFC_PE_Search_Interface::get_instance();
        $search_modes = $search_interface->get_search_modes();
        $current_mode = $this->current_mode;
        $current_mode_config = $search_modes[$current_mode] ?? reset($search_modes);
        $placeholder = $current_mode_config['placeholder'] ?? __('Search private equity opportunities...', 'senna-finance');

        ob_start();
    ?>
        <div class="sffc-compact-search-container" data-active-mode="<?php echo esc_attr($current_mode); ?>">
            <div class="sffc-compact-search-bar">
                <div class="sffc-search-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="m21 21-4.35-4.35"></path>
                    </svg>
                </div>

                <input type="search"
                    class="sffc-search-input"
                    placeholder="<?php echo esc_attr($placeholder); ?>"
                    value="<?php echo esc_attr($this->current_query); ?>"
                    aria-label="<?php esc_attr_e('Search private equity opportunities', 'senna-finance'); ?>"
                    data-mode="<?php echo esc_attr($current_mode); ?>">

                <div class="sffc-search-actions">
                    <button type="button" class="sffc-search-clear" aria-label="<?php esc_attr_e('Clear search', 'senna-finance'); ?>" <?php echo $this->current_query ? '' : 'hidden'; ?>>
                        <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                    <button type="button" class="sffc-voice-search" aria-label="<?php esc_attr_e('Voice search', 'senna-finance'); ?>">
                        <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path>
                            <path d="M19 10v2a7 7 0 0 1-14 0v-2"></path>
                            <line x1="12" y1="19" x2="12" y2="23"></line>
                            <line x1="8" y1="23" x2="16" y2="23"></line>
                        </svg>
                    </button>
                    <button type="button" class="sffc-search-btn sffc-search-submit"><?php esc_html_e('Search', 'senna-finance'); ?></button>
                </div>
            </div>

            <div class="sffc-compact-modes" role="tablist" aria-label="<?php esc_attr_e('Result categories', 'senna-finance'); ?>">
                <?php foreach ($search_modes as $mode_key => $mode_config):
                    $is_active = ($mode_key === $current_mode);
                ?>
                    <button type="button"
                        class="sffc-mode-tab <?php echo $is_active ? 'active' : ''; ?>"
                        data-mode="<?php echo esc_attr($mode_key); ?>"
                        data-placeholder="<?php echo esc_attr($mode_config['placeholder']); ?>"
                        aria-pressed="<?php echo $is_active ? 'true' : 'false'; ?>">
                        <span class="sffc-mode-icon"><?php echo $mode_config['icon']; ?></span>
                        <span class="sffc-mode-label"><?php echo esc_html($mode_config['label']); ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    <?php
        return ob_get_clean();
    }

    /**
     * Build intelligence summary for results rendering
     */
    private function build_results_intelligence($search_results)
    {
        $results = $search_results['results'] ?? array();

        $analysis = array(
            'query' => $this->current_query,
            'mode' => $this->current_mode,
            'total' => intval($search_results['total'] ?? 0),
            'page' => intval($search_results['page'] ?? 1),
            'per_page' => intval($search_results['per_page'] ?? $this->results_per_page),
            'search_time' => $search_results['search_time'] ?? 0,
            'results' => $results,
            'has_results' => !empty($results),
            'companies' => array(),
            'locations' => array(),
            'salary_rows' => array(),
            'experience_levels' => array(),
            'result_types' => array(),
            'entities' => array(),
            'first_result' => null,
        );

        foreach ($results as $index => $result) {
            if ($index === 0) {
                $analysis['first_result'] = $result;
            }

            $type_key = $this->normalize_result_type($result['type'] ?? '');
            if (!empty($type_key)) {
                $analysis['result_types'][$type_key] = ($analysis['result_types'][$type_key] ?? 0) + 1;
            }

            $meta = $result['meta'] ?? array();

            $company = $this->extract_meta_value($meta, array('company', 'sffc_company_name', 'company_name'));
            if (empty($company) && !empty($result['company'])) {
                $company = $result['company'];
            }

            if ($company) {
                if (!isset($analysis['companies'][$company])) {
                    $analysis['companies'][$company] = array(
                        'count' => 0,
                        'locations' => array(),
                        'roles' => array(),
                        'latest_date' => null,
                        'salary_min' => null,
                        'salary_max' => null,
                    );
                }

                $analysis['companies'][$company]['count']++;

                $role_title = wp_strip_all_tags($result['title'] ?? '');
                if ($role_title) {
                    $analysis['companies'][$company]['roles'][] = $role_title;
                }

                $date = $result['date'] ?? '';
                if (!empty($date)) {
                    $current_latest = $analysis['companies'][$company]['latest_date'];
                    if (empty($current_latest) || strtotime($date) > strtotime($current_latest)) {
                        $analysis['companies'][$company]['latest_date'] = $date;
                    }
                }
            }

            $location = $this->extract_meta_value($meta, array('location', 'sffc_location', 'headquarters'));
            if (empty($location) && !empty($result['location'])) {
                $location = $result['location'];
            }

            if ($location) {
                $location_key = trim($location);
                if ($location_key !== '') {
                    $analysis['locations'][$location_key] = ($analysis['locations'][$location_key] ?? 0) + 1;
                    if ($company) {
                        $analysis['companies'][$company]['locations'][$location_key] = true;
                    }
                }
            }

            $salary_min = $this->normalize_salary_value($this->extract_meta_value($meta, array('salary_min', 'sffc_salary_min', 'salary')));
            $salary_max = $this->normalize_salary_value($this->extract_meta_value($meta, array('salary_max', 'sffc_salary_max')));

            if ($salary_min !== null || $salary_max !== null) {
                $analysis['salary_rows'][] = array('min' => $salary_min, 'max' => $salary_max);

                if ($company) {
                    if ($salary_min !== null && ($analysis['companies'][$company]['salary_min'] === null || $salary_min < $analysis['companies'][$company]['salary_min'])) {
                        $analysis['companies'][$company]['salary_min'] = $salary_min;
                    }
                    if ($salary_max !== null && ($analysis['companies'][$company]['salary_max'] === null || $salary_max > $analysis['companies'][$company]['salary_max'])) {
                        $analysis['companies'][$company]['salary_max'] = $salary_max;
                    }
                }
            }

            $experience = $this->extract_meta_value($meta, array('experience_level', 'experience', 'experience_years'));
            if ($experience) {
                $formatted_experience = is_numeric($experience) ? sprintf('%s+ years', $experience) : $experience;
                $analysis['experience_levels'][$formatted_experience] = ($analysis['experience_levels'][$formatted_experience] ?? 0) + 1;
            }

            if (!empty($result['entities']) && is_array($result['entities'])) {
                foreach ($result['entities'] as $entity) {
                    if (!is_array($entity)) {
                        continue;
                    }
                    $entity_type = $entity['type'] ?? '';
                    $entity_name = $entity['name'] ?? '';
                    if (!$entity_type || !$entity_name) {
                        continue;
                    }
                    if (!isset($analysis['entities'][$entity_type])) {
                        $analysis['entities'][$entity_type] = array();
                    }
                    $analysis['entities'][$entity_type][$entity_name] = ($analysis['entities'][$entity_type][$entity_name] ?? 0) + 1;
                }
            }
        }

        $analysis['salary_summary'] = $this->compute_salary_summary($analysis['salary_rows']);
        $analysis['top_company'] = $this->determine_top_company($analysis['companies']);
        $analysis['top_locations'] = $this->determine_top_locations($analysis['locations']);
        $analysis['people_also_ask'] = $this->build_people_also_ask($analysis);
        $analysis['trending_queries'] = $this->build_trending_queries($analysis);

        return $analysis;
    }

    /**
     * Extract first non-empty meta value by keys
     */
    private function extract_meta_value($meta, $keys)
    {
        foreach ($keys as $key) {
            if (isset($meta[$key]) && $meta[$key] !== '' && $meta[$key] !== null) {
                return is_string($meta[$key]) ? trim($meta[$key]) : $meta[$key];
            }
        }
        return '';
    }

    /**
     * Normalise salary string/number into integer GBP equivalent
     */
    private function normalize_salary_value($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return intval($value);
        }

        $clean = preg_replace('/[^0-9\.]/', '', (string) $value);
        if ($clean === '') {
            return null;
        }

        // Handle values like "120k"
        if (stripos($value, 'k') !== false && $clean < 1000) {
            $clean = $clean * 1000;
        }

        return intval($clean);
    }

    /**
     * Compute salary summary stats from rows
     */
    private function compute_salary_summary($salary_rows)
    {
        if (empty($salary_rows)) {
            return array(
                'count' => 0,
                'min' => null,
                'max' => null,
                'average' => null
            );
        }

        $min = null;
        $max = null;
        $total = 0;
        $count = 0;

        foreach ($salary_rows as $row) {
            $row_min = $row['min'];
            $row_max = $row['max'];

            if ($row_min !== null) {
                $min = ($min === null) ? $row_min : min($min, $row_min);
                $total += $row_min;
                $count++;
            }

            if ($row_max !== null) {
                $max = ($max === null) ? $row_max : max($max, $row_max);
            }
        }

        $average = $count > 0 ? intval(round($total / $count)) : null;

        return array(
            'count' => count($salary_rows),
            'min' => $min,
            'max' => $max,
            'average' => $average
        );
    }

    /**
     * Determine top company from aggregated data
     */
    private function determine_top_company($companies)
    {
        if (empty($companies)) {
            return null;
        }

        uasort($companies, function ($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        $name = key($companies);
        $data = current($companies);

        $locations = array_keys($data['locations']);
        sort($locations);

        return array(
            'name' => $name,
            'count' => $data['count'],
            'locations' => $locations,
            'roles' => array_slice(array_unique($data['roles']), 0, 3),
            'latest_date' => $data['latest_date'],
            'salary_min' => $data['salary_min'],
            'salary_max' => $data['salary_max']
        );
    }

    /**
     * Determine top locations
     */
    private function determine_top_locations($locations)
    {
        if (empty($locations)) {
            return array();
        }

        arsort($locations);
        $top = array_slice($locations, 0, 4, true);

        $formatted = array();
        foreach ($top as $location => $count) {
            $formatted[] = array('location' => $location, 'count' => $count);
        }

        return $formatted;
    }

    /**
     * Generate "People also ask" data
     */
    private function build_people_also_ask($analysis)
    {
        if (!$analysis['has_results']) {
            return array();
        }

        $questions = array();
        $query = $analysis['query'];
        $salary = $analysis['salary_summary'];
        $top_company = $analysis['top_company'];
        $top_locations = $analysis['top_locations'];

        if (!empty($salary['count']) && ($salary['min'] !== null || $salary['max'] !== null)) {
            $range_parts = array();
            if ($salary['min'] !== null) {
                $range_parts[] = '£' . number_format($salary['min']);
            }
            if ($salary['max'] !== null) {
                $range_parts[] = '£' . number_format($salary['max']);
            }
            $range = implode(' - ', $range_parts);
            $questions[] = array(
                'question' => sprintf(__('What salary can I expect for %s roles?', 'senna-finance'), esc_html($query)),
                'answer' => sprintf(__('Current live roles show compensation around %s, with an average package of %s for top performers.', 'senna-finance'), $range, $salary['average'] ? '£' . number_format($salary['average']) : __('six figures', 'senna-finance')),
                'tag' => __('Compensation insight', 'senna-finance')
            );
        }

        if (!empty($top_company['name'])) {
            $questions[] = array(
                'question' => sprintf(__('Who is hiring for %s right now?', 'senna-finance'), esc_html($query)),
                'answer' => sprintf(__('%s currently has %d live opportunities across %s. Roles span %s.', 'senna-finance'), $top_company['name'], $top_company['count'], $top_company['locations'] ? implode(', ', $top_company['locations']) : __('multiple hubs', 'senna-finance'), $top_company['roles'] ? implode(', ', $top_company['roles']) : __('core investment functions', 'senna-finance')),
                'tag' => __('Hiring spotlight', 'senna-finance')
            );
        }

        if (!empty($top_locations)) {
            $top_location = $top_locations[0];
            $secondary_locations = array_slice($top_locations, 1, 2);
            $secondary = !empty($secondary_locations)
                ? implode(', ', array_map(function ($item) {
                    return $item['location'];
                }, $secondary_locations))
                : __('other key hubs', 'senna-finance');
            $questions[] = array(
                'question' => __('Where are the opportunities concentrated?', 'senna-finance'),
                'answer' => sprintf(__('The hottest market right now is %s with %d live mandates, followed by %s.', 'senna-finance'), $top_location['location'], $top_location['count'], $secondary),
                'tag' => __('Geo focus', 'senna-finance')
            );
        }

        if (empty($questions)) {
            $questions[] = array(
                'question' => __('How do I stand out for these roles?', 'senna-finance'),
                'answer' => __('Successful candidates showcase deal execution credentials, advanced financial modelling, and a clear sector thesis. Highlight a marquee transaction in your CV and quantify the impact.', 'senna-finance'),
                'tag' => __('Career coaching', 'senna-finance')
            );
        }

        return $questions;
    }

    /**
     * Build trending queries list
     */
    private function build_trending_queries($analysis)
    {
        $query = $analysis['query'];
        $trending = array();

        if ($query) {
            $trending[] = $query . ' compensation';
            $trending[] = 'Top ' . $query . ' firms';
        }

        if (!empty($analysis['top_company']['name'])) {
            $trending[] = $analysis['top_company']['name'] . ' interview process';
            $trending[] = 'Work-life balance ' . $analysis['top_company']['name'];
        }

        if (!empty($analysis['top_locations'])) {
            $trending[] = 'Private equity jobs ' . $analysis['top_locations'][0]['location'];
        }

        $trending[] = 'Fundraising insights 2025';
        $trending[] = 'Private equity headhunter list';

        return array_unique($trending);
    }


    /**
     * Define primary filter pills for the results view
     */
    private function get_primary_filters($analysis = array())
    {
        $mode = $analysis['mode'] ?? $this->current_mode;

        $filters = array(
            array(
                'key' => 'all',
                'label' => __('All results', 'senna-finance'),
                'icon' => '<svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18l-2 13H5L3 6z"></path><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>'
            ),
            array(
                'key' => 'recent',
                'label' => __('Recent', 'senna-finance'),
                'icon' => '<svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12,6 12,12 16,14"></polyline></svg>'
            ),
            array(
                'key' => 'relevant',
                'label' => ($mode === 'insights') ? __('Top stories', 'senna-finance') : __('Most relevant', 'senna-finance'),
                'icon' => '<svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26"></polygon></svg>'
            ),
            array(
                'key' => 'location',
                'label' => __('Location', 'senna-finance'),
                'icon' => '<svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="10" r="3"></circle><path d="M12 21.7c5-4.7 8-9.7 8-11.7 0-4.4-3.6-8-8-8s-8 3.6-8 8c0 2 3 7 8 11.7z"></path></svg>'
            ),
            array(
                'key' => 'salary',
                'label' => __('Salary', 'senna-finance'),
                'icon' => '<svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v6m0 6v6"></path><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>'
            )
        );

        if ($mode === 'insights') {
            $filters[] = array(
                'key' => 'deals',
                'label' => __('Deals', 'senna-finance'),
                'icon' => '<svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"></path><path d="M2 17l10 5 10-5"></path><path d="M2 12l10 5 10-5"></path></svg>'
            );
        }

        return $filters;
    }

    /**
     * Render premium membership call-to-action in header
     */
    private function render_results_header_cta($analysis)
    {
        $total = intval($analysis['total']);
        $message = $total > 0
            ? sprintf(__('Curated results across %s premium opportunities.', 'senna-finance'), number_format($total))
            : __('Unlock MENA Careers Premium to receive bespoke mandates ahead of the market.', 'senna-finance');

        ob_start();
    ?>
        <div class="sffc-membership-cta" data-track="membership">
            <div class="sffc-membership-copy">
                <span class="sffc-membership-badge"><?php esc_html_e('Pro+', 'senna-finance'); ?></span>
                <p><?php echo esc_html($message); ?></p>
            </div>
            <a class="sffc-join-btn" href="<?php echo esc_url(apply_filters('sffc_search_membership_url', '#join-senna')); ?>" data-action="join-senna">
                <span><?php esc_html_e('Join MENA Careers', 'senna-finance'); ?></span>
                <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                    <polyline points="12,5 19,12 12,19"></polyline>
                </svg>
            </a>
        </div>
    <?php
        return ob_get_clean();
    }

    /**
     * Render contextual summary badges under search stats
     */
    private function render_search_summary_badges($analysis)
    {
        if (!$analysis['has_results']) {
            return '';
        }

        $badges = array();

        if (!empty($analysis['result_types'])) {
            $types = $analysis['result_types'];
            arsort($types);
            $top_type_key = key($types);
            $top_type_count = current($types);
            $percentage = $analysis['total'] > 0 ? intval(round(($top_type_count / $analysis['total']) * 100)) : 0;
            $badges[] = array(
                'icon' => '<svg aria-hidden="true" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>',
                'text' => sprintf(__('%s%% %s', 'senna-finance'), $percentage, ucfirst($top_type_key))
            );
        }

        if (!empty($analysis['top_locations'])) {
            $top_location = $analysis['top_locations'][0];
            $badges[] = array(
                'icon' => '<svg aria-hidden="true" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="10" r="3"></circle><path d="M12 21.7c5-4.7 8-9.7 8-11.7 0-4.4-3.6-8-8-8s-8 3.6-8 8c0 2 3 7 8 11.7z"></path></svg>',
                'text' => sprintf(__('%s hotspot', 'senna-finance'), $top_location['location'])
            );
        }

        if (!empty($analysis['experience_levels'])) {
            $levels = $analysis['experience_levels'];
            arsort($levels);
            $top_experience = key($levels);
            $badges[] = array(
                'icon' => '<svg aria-hidden="true" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 6v6l4 2"></path><circle cx="12" cy="12" r="10"></circle></svg>',
                'text' => sprintf(__('Focus on %s', 'senna-finance'), $top_experience)
            );
        }

        if (empty($badges)) {
            return '';
        }

        ob_start();
    ?>
        <span class="sffc-search-badges">
            <?php foreach ($badges as $badge): ?>
                <span class="sffc-search-badge">
                    <?php echo $badge['icon']; ?>
                    <span><?php echo esc_html($badge['text']); ?></span>
                </span>
            <?php endforeach; ?>
        </span>
    <?php
        return ob_get_clean();
    }

    /**
     * Render People Also Ask section
     */
    private function render_people_also_ask_section($analysis)
    {
        $items = $analysis['people_also_ask'] ?? array();
        if (empty($items)) {
            return '';
        }

        $section_id = 'sffc-ask-' . wp_generate_uuid4();

        ob_start();
    ?>
        <section class="sffc-people-ask" aria-labelledby="<?php echo esc_attr($section_id); ?>">
            <h4 class="sffc-section-title" id="<?php echo esc_attr($section_id); ?>"><?php esc_html_e('People also ask', 'senna-finance'); ?></h4>
            <div class="sffc-ask-list">
                <?php foreach ($items as $index => $item):
                    $question_id = $section_id . '-q' . $index;
                    $answer_id = $section_id . '-a' . $index;
                ?>
                    <article class="sffc-ask-item">
                        <button class="sffc-ask-question" type="button" aria-expanded="false" aria-controls="<?php echo esc_attr($answer_id); ?>" id="<?php echo esc_attr($question_id); ?>">
                            <span><?php echo esc_html($item['question']); ?></span>
                            <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </button>
                        <div class="sffc-ask-answer" id="<?php echo esc_attr($answer_id); ?>" role="region" aria-labelledby="<?php echo esc_attr($question_id); ?>" hidden>
                            <p><?php echo esc_html($item['answer']); ?></p>
                            <?php if (!empty($item['tag'])): ?>
                                <span class="sffc-ask-tag"><?php echo esc_html($item['tag']); ?></span>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php
        return ob_get_clean();
    }

    /**
     * Render related searches & trending queries
     */
    private function render_related_searches_section($analysis)
    {
        $related_html = '';
        if (!empty($analysis['query'])) {
            $related_html = $this->generate_related_searches($analysis['query'], $analysis['mode']);
        }

        if (!trim($related_html)) {
            return '';
        }

        $section_id = 'sffc-related-' . wp_generate_uuid4();

        ob_start();
    ?>
        <section class="sffc-related-searches" aria-labelledby="<?php echo esc_attr($section_id); ?>">
            <h4 class="sffc-section-title" id="<?php echo esc_attr($section_id); ?>"><?php esc_html_e('People also search for', 'senna-finance'); ?></h4>
            <div class="sffc-related-links">
                <?php echo $related_html; ?>
            </div>
        </section>
    <?php
        return ob_get_clean();
    }

    /**
     * Render inline trending block within results list
     */
    private function render_trending_block($analysis)
    {
        $trending = $analysis['trending_queries'] ?? array();
        if (empty($trending)) {
            return '';
        }

        $section_id = 'sffc-trending-' . wp_generate_uuid4();

        ob_start();
    ?>
        <section class="sffc-related-trending sffc-related-trending--inline" aria-labelledby="<?php echo esc_attr($section_id); ?>">
            <h5 id="<?php echo esc_attr($section_id); ?>"><?php esc_html_e('Trending in private markets', 'senna-finance'); ?></h5>
            <ul>
                <?php foreach (array_slice($trending, 0, 6) as $trend): ?>
                    <li>
                        <button type="button" class="sffc-related-link" data-query="<?php echo esc_attr($trend); ?>" data-mode="<?php echo esc_attr($analysis['mode']); ?>">
                            <?php echo esc_html($trend); ?>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php
        return ob_get_clean();
    }

    /**
     * Render spotlight block highlighting top company / trend
     */
    private function render_search_spotlight_block($analysis)
    {
        $company = $analysis['top_company'] ?? null;
        $trend = $analysis['trending_queries'][0] ?? '';

        if (empty($company) && empty($trend)) {
            return '';
        }

        $headline = !empty($company['name']) ? $company['name'] : $trend;
        $locations = !empty($company['locations']) ? implode(' • ', $company['locations']) : '';
        $roles = !empty($company['roles']) ? implode(' · ', $company['roles']) : '';
        $salary = '';
        if (!empty($company['salary_min']) || !empty($company['salary_max'])) {
            $salary = $this->format_salary_band($company['salary_min'], $company['salary_max']);
        }

        $meta_items = array();
        if (!empty($company['count'])) {
            $meta_items[] = sprintf(_n('%d live role', '%d live roles', intval($company['count']), 'senna-finance'), intval($company['count']));
        }
        if ($locations) {
            $meta_items[] = $locations;
        }
        if ($roles) {
            $meta_items[] = $roles;
        }
        if ($salary) {
            $meta_items[] = $salary;
        }

        ob_start();
    ?>
        <aside class="sffc-search-spotlight sffc-search-spotlight-card" role="complementary" aria-label="<?php esc_attr_e('Spotlight insights', 'senna-finance'); ?>">
            <div class="sffc-spotlight-header">
                <span class="sffc-spotlight-badge"><?php esc_html_e('Spotlight', 'senna-finance'); ?></span>
                <h4><?php echo esc_html($headline); ?></h4>
            </div>
            <?php if (!empty($meta_items)): ?>
                <ul class="sffc-spotlight-meta">
                    <?php foreach ($meta_items as $item): ?><li><?php echo esc_html($item); ?></li><?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ($trend): ?>
                <p class="sffc-spotlight-trendline"><?php echo esc_html(sprintf(__('Trending query: %s', 'senna-finance'), $trend)); ?></p>
            <?php endif; ?>
            <div class="sffc-spotlight-actions">
                <?php if (!empty($company['name'])): ?>
                    <button type="button" class="sffc-spotlight-link sffc-related-link" data-query="<?php echo esc_attr($company['name']); ?>" data-mode="jobs">
                        <?php esc_html_e('View live roles', 'senna-finance'); ?>
                    </button>
                <?php endif; ?>
                <button type="button" class="sffc-spotlight-link sffc-related-link" data-query="<?php echo esc_attr($analysis['query']); ?>" data-mode="<?php echo esc_attr($analysis['mode']); ?>" data-filter="recent">
                    <?php esc_html_e('See fresh matches', 'senna-finance'); ?>
                </button>
            </div>
        </aside>
    <?php
        return ob_get_clean();
    }

    /**
     * Render no results state with suggested queries
     */
    private function render_no_results_suggestions($analysis)
    {
        $trending = $analysis['trending_queries'] ?? array();

        ob_start();
    ?>
        <div class="sffc-no-results">
            <div class="sffc-no-results-icon">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.35-4.35"></path>
                </svg>
            </div>
            <h3 class="sffc-no-results-title"><?php esc_html_e('We didn\'t find matching results', 'senna-finance'); ?></h3>
            <p class="sffc-no-results-text"><?php esc_html_e('Try broadening your query, adjusting filters, or exploring our curated private equity intelligence below.', 'senna-finance'); ?></p>

            <div class="sffc-no-results-actions">
                <button type="button" class="sffc-filter-pill sffc-no-results-reset" data-action="reset-filters">
                    <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 4h16"></path>
                        <path d="M4 11h10"></path>
                        <path d="M4 18h7"></path>
                    </svg>
                    <?php esc_html_e('Reset filters', 'senna-finance'); ?>
                </button>
                <button type="button" class="sffc-filter-pill sffc-no-results-alert" data-action="create-alert">
                    <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    </svg>
                    <?php esc_html_e('Create alert', 'senna-finance'); ?>
                </button>
            </div>

            <?php if (!empty($trending)): ?>
                <div class="sffc-no-results-trending">
                    <h4><?php esc_html_e('Try these private equity searches', 'senna-finance'); ?></h4>
                    <div class="sffc-pill-grid">
                        <?php foreach (array_slice($trending, 0, 6) as $trend): ?>
                            <button type="button" class="sffc-filter-pill sffc-related-link" data-query="<?php echo esc_attr($trend); ?>" data-mode="<?php echo esc_attr($analysis['mode']); ?>"><?php echo esc_html($trend); ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php
        return ob_get_clean();
    }

    /**
     * Render right-rail intelligence cards
     */
    private function render_results_sidebar($analysis)
    {
        $cards = array(
            $this->render_sidebar_company_card($analysis),
            $this->render_sidebar_salary_card($analysis),
            $this->render_sidebar_locations_card($analysis)
        );

        $cards = array_filter($cards);

        if (empty($cards)) {
            return '';
        }

        ob_start();
    ?>
        <div class="sffc-rail-stack">
            <?php foreach ($cards as $card): ?>
                <?php echo $card; ?>
            <?php endforeach; ?>
        </div>
    <?php
        return ob_get_clean();
    }

    /**
     * Render bottom mobile navigation for quick mode switching
     */
    private function render_mobile_mode_nav($analysis)
    {
        $search_interface = SFFC_PE_Search_Interface::get_instance();
        $search_modes = $search_interface->get_search_modes();
        if (empty($search_modes)) {
            return '';
        }

        $current_mode = $analysis['mode'] ?? $this->current_mode;

        ob_start();
    ?>
        <nav class="sffc-mobile-mode-nav" aria-label="<?php esc_attr_e('Search navigation', 'senna-finance'); ?>">
            <?php foreach ($search_modes as $mode_key => $mode_config):
                $is_active = ($mode_key === $current_mode);
            ?>
                <button type="button"
                    class="sffc-mobile-mode-btn <?php echo $is_active ? 'active' : ''; ?>"
                    data-mode="<?php echo esc_attr($mode_key); ?>"
                    data-placeholder="<?php echo esc_attr($mode_config['placeholder']); ?>"
                    aria-pressed="<?php echo $is_active ? 'true' : 'false'; ?>">
                    <span class="sffc-mobile-mode-icon"><?php echo $mode_config['icon']; ?></span>
                    <span class="sffc-mobile-mode-label"><?php echo esc_html($mode_config['label']); ?></span>
                </button>
            <?php endforeach; ?>
        </nav>
    <?php
        return ob_get_clean();
    }

    /**
     * Featured company card
     */
    private function render_sidebar_company_card($analysis)
    {
        $company = $analysis['top_company'] ?? null;
        if (empty($company['name'])) {
            return '';
        }

        $locations = !empty($company['locations']) ? implode(' • ', $company['locations']) : __('Multiple hubs', 'senna-finance');
        $roles = !empty($company['roles']) ? implode(' · ', $company['roles']) : __('Investment team openings', 'senna-finance');
        $updated = '';
        if (!empty($company['latest_date'])) {
            $updated = human_time_diff(strtotime($company['latest_date']), current_time('timestamp'));
            $updated = sprintf(__('Updated %s ago', 'senna-finance'), $updated);
        }
        $salary = $this->format_salary_band($company['salary_min'], $company['salary_max']);

        ob_start();
    ?>
        <div class="sffc-rail-card sffc-rail-card--company">
            <span class="sffc-rail-badge"><?php esc_html_e('Featured firm', 'senna-finance'); ?></span>
            <h3><?php echo esc_html($company['name']); ?></h3>
            <p class="sffc-rail-subtext"><?php echo esc_html($locations); ?></p>
            <ul class="sffc-rail-list">
                <li>
                    <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M6 3h12l3 7H3z"></path>
                        <path d="M6 10v10"></path>
                        <path d="M18 10v10"></path>
                        <path d="M9 14h6"></path>
                    </svg>
                    <span><?php echo esc_html($roles); ?></span>
                </li>
                <?php if ($salary): ?>
                    <li>
                        <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 1v22"></path>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                        </svg>
                        <span><?php echo esc_html($salary); ?></span>
                    </li>
                <?php endif; ?>
                <?php if ($updated): ?>
                    <li>
                        <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12,6 12,12 16,14"></polyline>
                        </svg>
                        <span><?php echo esc_html($updated); ?></span>
                    </li>
                <?php endif; ?>
            </ul>
            <button type="button" class="sffc-rail-cta sffc-related-link" data-query="<?php echo esc_attr($company['name']); ?>" data-mode="jobs">
                <?php esc_html_e('View live roles', 'senna-finance'); ?>
                <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                    <polyline points="12,5 19,12 12,19"></polyline>
                </svg>
            </button>
        </div>
    <?php
        return ob_get_clean();
    }

    /**
     * Salary insight card
     */
    private function render_sidebar_salary_card($analysis)
    {
        $summary = $analysis['salary_summary'] ?? array();
        if (empty($summary['count']) || ($summary['min'] === null && $summary['max'] === null)) {
            return '';
        }

        $band = $this->format_salary_band($summary['min'], $summary['max']);
        $average = !empty($summary['average']) ? '£' . number_format($summary['average']) : __('Six-figure packages', 'senna-finance');

        ob_start();
    ?>
        <div class="sffc-rail-card sffc-rail-card--salary">
            <span class="sffc-rail-badge"><?php esc_html_e('Compensation radar', 'senna-finance'); ?></span>
            <h3><?php esc_html_e('Market range', 'senna-finance'); ?></h3>
            <p class="sffc-rail-highlight"><?php echo esc_html($band); ?></p>
            <ul class="sffc-rail-list">
                <li>
                    <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 1v22"></path>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                    </svg>
                    <span><?php echo esc_html(sprintf(__('Average package %s', 'senna-finance'), $average)); ?></span>
                </li>
                <li>
                    <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 3h18"></path>
                        <path d="M3 12h18"></path>
                        <path d="M3 21h18"></path>
                    </svg>
                    <span><?php echo esc_html(sprintf(__('Based on %d live salary signals', 'senna-finance'), intval($summary['count']))); ?></span>
                </li>
            </ul>
        </div>
    <?php
        return ob_get_clean();
    }

    /**
     * Locations highlight card
     */
    private function render_sidebar_locations_card($analysis)
    {
        $locations = $analysis['top_locations'] ?? array();
        if (empty($locations)) {
            return '';
        }

        ob_start();
    ?>
        <div class="sffc-rail-card sffc-rail-card--locations">
            <span class="sffc-rail-badge"><?php esc_html_e('Hot locations', 'senna-finance'); ?></span>
            <h3><?php esc_html_e('Where hiring is peaking', 'senna-finance'); ?></h3>
            <ul class="sffc-rail-pill-list">
                <?php foreach ($locations as $location): ?>
                    <li>
                        <span class="sffc-pill">
                            <svg aria-hidden="true" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="10" r="3"></circle>
                                <path d="M12 21.7c5-4.7 8-9.7 8-11.7 0-4.4-3.6-8-8-8s-8 3.6-8 8c0 2 3 7 8 11.7z"></path>
                            </svg>
                            <?php echo esc_html($location['location']); ?>
                            <span class="sffc-pill-count"><?php echo intval($location['count']); ?></span>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
<?php
        return ob_get_clean();
    }

    /**
     * Helper to format salary band display
     */
    private function format_salary_band($min, $max)
    {
        if ($min === null && $max === null) {
            return '';
        }

        if ($min !== null && $max !== null) {
            return '£' . number_format($min) . ' - £' . number_format($max);
        }

        if ($min !== null) {
            return sprintf(__('From £%s', 'senna-finance'), number_format($min));
        }

        return sprintf(__('Up to £%s', 'senna-finance'), number_format($max));
    }

    /**
     * Render badges displayed beside each result title
     */
    private function render_result_badges($result, $score = null)
    {
        $badges = array();

        if ($this->is_recent_result($result['date'] ?? '')) {
            $badges[] = '<span class="sffc-result-badge sffc-result-badge--new">' . esc_html__('New', 'senna-finance') . '</span>';
        }

        if ($score !== null) {
            $badges[] = '<span class="sffc-result-badge sffc-result-badge--score">' . esc_html(sprintf(__('Score %s', 'senna-finance'), number_format($score, 1))) . '</span>';
        }

        return implode('', $badges);
    }

    /**
     * Determine if a result is recent (within 7 days)
     */
    private function is_recent_result($date)
    {
        if (empty($date)) {
            return false;
        }

        $timestamp = strtotime($date);
        if (!$timestamp) {
            return false;
        }

        $threshold = current_time('timestamp') - (DAY_IN_SECONDS * 7);
        return $timestamp >= $threshold;
    }

    /**
     * Format posted date as human readable string
     */
    private function format_posted_date($date)
    {
        if (empty($date)) {
            return '';
        }

        $timestamp = strtotime($date);
        if (!$timestamp) {
            return '';
        }

        $diff = human_time_diff($timestamp, current_time('timestamp'));
        return sprintf(__('Posted %s ago', 'senna-finance'), $diff);
    }

    /**
     * Build contextual tags for result footer
     */
    private function build_result_tags($result, $company, $meta)
    {
        $tags = array();

        if (!empty($company)) {
            $tags[] = $company;
        }

        $sector = $this->extract_meta_value($meta, array('sector', 'news_sector', 'deal_sector', 'company_sector'));
        if ($sector) {
            $tags[] = $sector;
        }

        $deal_type = $this->extract_meta_value($meta, array('deal_type', 'news_deal_type'));
        if ($deal_type) {
            $tags[] = $deal_type;
        }

        if (!empty($result['entities']) && is_array($result['entities'])) {
            foreach ($result['entities'] as $entity) {
                if (!is_array($entity)) {
                    continue;
                }
                if (!empty($entity['type']) && $entity['type'] === 'location' && !empty($entity['name'])) {
                    $tags[] = $entity['name'];
                }
            }
        }

        $tags = array_unique(array_filter($tags));
        return array_slice($tags, 0, 4);
    }

    /**
     * Normalise result type for analytics badges
     */
    private function normalize_result_type($type)
    {
        if (!$type) {
            return '';
        }

        $type = strtolower($type);
        $type = str_replace('sffc_', '', $type);

        switch ($type) {
            case 'job':
            case 'jobs':
                return 'jobs';
            case 'recruiter':
            case 'recruiters':
                return 'recruiters';
            case 'company':
            case 'companies':
                return 'companies';
            case 'deal':
            case 'deals':
                return 'deals';
            case 'news':
            case 'pe_news':
            case 'news_article':
                return 'insights';
            case 'salary_guide':
                return 'templates';
            default:
                return $type;
        }
    }

    /**
     * Ensure search database tables exist
     */
    private function ensure_search_tables_exist()
    {
        if (class_exists('SFFC_Search_Indexer')) {
            $indexer = SFFC_Search_Indexer::get_instance();
            $indexer->maybe_create_tables();
        }
    }

    /**
     * Perform search using Phase 3.1 backend search processor
     */
    private function perform_search_with_backend($query, $mode, $page = 1, $override_filters = array())
    {
        // Use the new search query processor if available
        if (class_exists('SFFC_Search_Query')) {
            $search_processor = SFFC_Search_Query::get_instance();

            // Get any filters from URL parameters
            $filters = array();
            if (isset($_GET['location'])) {
                $filters['location'] = sanitize_text_field($_GET['location']);
            }
            if (isset($_GET['salary_min'])) {
                $filters['salary_min'] = intval($_GET['salary_min']);
            }
            if (isset($_GET['salary_max'])) {
                $filters['salary_max'] = intval($_GET['salary_max']);
            }
            if (isset($_GET['experience'])) {
                $filters['experience'] = sanitize_text_field($_GET['experience']);
            }
            if (isset($_GET['date_from'])) {
                $filters['date_from'] = sanitize_text_field($_GET['date_from']);
            }
            if (isset($_GET['filter'])) {
                $filters['primary'] = sanitize_text_field($_GET['filter']);
            }

            if (!empty($override_filters)) {
                $filters = array_merge($filters, $override_filters);
            }

            // Execute search with backend
            $backend_results = $search_processor->search($query, $mode, $page, $filters);

            // Format results for frontend compatibility
            $formatted_results = array();
            if (isset($backend_results['results']) && is_array($backend_results['results'])) {
                foreach ($backend_results['results'] as $result) {
                    $formatted_result = $this->format_backend_result($result);
                    if ($formatted_result) {
                        $formatted_results[] = $formatted_result;
                    }
                }
            }

            $backend_total = intval($backend_results['total'] ?? 0);
            $backend_page = intval($backend_results['page'] ?? $page);
            $backend_per_page = intval($backend_results['per_page'] ?? $this->results_per_page);
            $backend_time = $backend_results['search_time'] ?? 0;
            $backend_filters = $backend_results['filters_applied'] ?? array();

            if (empty($formatted_results) && $backend_total > 0) {
                error_log('SFFC Search: Backend count mismatch detected, falling back to legacy search renderer.');
                $legacy_results = $this->perform_search($query, $mode, $page, $override_filters);

                return array(
                    'results' => $legacy_results['results'],
                    'total' => $legacy_results['total'],
                    'page' => $legacy_results['page'],
                    'per_page' => $legacy_results['per_page'],
                    'search_time' => $backend_time,
                    'filters_applied' => $backend_filters,
                    'fallback' => 'legacy'
                );
            }

            return array(
                'results' => $formatted_results,
                'total' => $backend_total,
                'page' => $backend_page,
                'per_page' => $backend_per_page,
                'search_time' => $backend_time,
                'filters_applied' => $backend_filters
            );
        }

        // Fallback to legacy search if backend not available
        return $this->perform_search($query, $mode, $page, $override_filters);
    }

    /**
     * Format backend search result for frontend display
     */
    private function format_backend_result($result)
    {
        // Ensure result is valid before returning
        if (empty($result) || !is_array($result)) {
            return false;
        }

        // Check for any valid ID field (try both 'id' and 'post_id')
        if (!isset($result['id']) && !isset($result['post_id'])) {
            return false;
        }

        // Check for title
        if (!isset($result['title']) || empty($result['title'])) {
            return false;
        }

        // Normalize the result format
        if (isset($result['post_id']) && !isset($result['id'])) {
            $result['id'] = $result['post_id'];
        }

        // Backend already returns properly formatted arrays, just return as-is
        return $result;
    }

    /**
     * Perform the actual search (Legacy method)
     */
    private function perform_search($query, $mode, $page = 1, $filters = array())
    {
        global $wpdb;

        $offset = ($page - 1) * $this->results_per_page;
        $limit = $this->results_per_page;

        // Get search interface instance for mode configuration
        $search_interface = SFFC_PE_Search_Interface::get_instance();
        $search_modes = $search_interface->get_search_modes();

        if (!isset($search_modes[$mode])) {
            return array('results' => array(), 'total' => 0);
        }

        $post_type = $search_modes[$mode]['post_type'];

        // Build search query
        $search_sql = $wpdb->prepare(
            "
            SELECT DISTINCT p.ID, p.post_title, p.post_content, p.post_excerpt, p.post_date
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = %s
            AND p.post_status = 'publish'
            AND (
                p.post_title LIKE %s
                OR p.post_content LIKE %s
                OR p.post_excerpt LIKE %s
                OR pm.meta_value LIKE %s
            )
            ORDER BY p.post_date DESC
            LIMIT %d OFFSET %d
        ",
            $post_type,
            '%' . $wpdb->esc_like($query) . '%',
            '%' . $wpdb->esc_like($query) . '%',
            '%' . $wpdb->esc_like($query) . '%',
            '%' . $wpdb->esc_like($query) . '%',
            $limit,
            $offset
        );

        $posts = $wpdb->get_results($search_sql);

        // Count total results
        $count_sql = str_replace(
            array('SELECT DISTINCT p.ID, p.post_title, p.post_content, p.post_excerpt, p.post_date', 'ORDER BY p.post_date DESC', 'LIMIT %d OFFSET %d'),
            array('SELECT COUNT(DISTINCT p.ID)', '', ''),
            $search_sql
        );
        $count_sql = $wpdb->prepare(
            $count_sql,
            $post_type,
            '%' . $wpdb->esc_like($query) . '%',
            '%' . $wpdb->esc_like($query) . '%',
            '%' . $wpdb->esc_like($query) . '%',
            '%' . $wpdb->esc_like($query) . '%'
        );

        $total = $wpdb->get_var($count_sql);

        // Format results
        $results = array();
        foreach ($posts as $post) {
            $result = $this->format_search_result($post, $mode);
            if ($result) {
                $results[] = $result;
            }
        }

        return array(
            'results' => $results,
            'total' => intval($total),
            'page' => $page,
            'per_page' => $this->results_per_page
        );
    }

    /**
     * Format individual search result
     */
    private function format_search_result($post, $mode)
    {
        $result = array(
            'id' => $post->ID,
            'type' => str_replace('sffc_', '', get_post_type($post->ID)),
            'title' => $post->post_title,
            'content' => $post->post_content,
            'description' => $post->post_excerpt,
            'url' => get_permalink($post->ID),
            'date' => $post->post_date
        );

        // Add type-specific data
        switch ($mode) {
            case 'jobs':
                $result['company'] = get_post_meta($post->ID, 'sffc_company_name', true);
                $result['location'] = get_post_meta($post->ID, 'sffc_location', true);
                $result['salary_min'] = get_post_meta($post->ID, 'sffc_salary_min', true);
                $result['salary_max'] = get_post_meta($post->ID, 'sffc_salary_max', true);
                $result['experience_level'] = get_post_meta($post->ID, 'sffc_experience_level', true);
                break;

            case 'companies':
                $result['company'] = $post->post_title;
                $result['sector'] = get_post_meta($post->ID, 'company_sector', true);
                $result['size'] = get_post_meta($post->ID, 'company_size', true);
                break;

            case 'insights':
            case 'news':
                $result['company'] = get_post_meta($post->ID, 'news_company', true);
                $result['deal_value'] = get_post_meta($post->ID, 'deal_value', true);
                $result['deal_type'] = get_post_meta($post->ID, 'deal_type', true);
                $result['source'] = get_post_meta($post->ID, 'news_source', true);
                $result['date'] = get_post_meta($post->ID, 'news_date', true) ?: $result['date'];
                break;

            case 'templates':
                $result['region'] = get_post_meta($post->ID, 'salary_region', true);
                $result['experience_level'] = get_post_meta($post->ID, 'salary_experience_level', true);
                $result['salary_min'] = get_post_meta($post->ID, 'salary_min', true);
                $result['salary_max'] = get_post_meta($post->ID, 'salary_max', true);
                break;
        }

        return $result;
    }

    /**
     * Get company logo URL
     */
    private function get_company_logo($company_name)
    {
        // This would integrate with your company database
        // For now, return empty to use letter fallback
        return '';
    }

    /**
     * Generate related searches
     * Uses buttons with data attributes to avoid creating indexable URLs
     */
    private function generate_related_searches($query, $mode)
    {
        $related = array();

        // Generate contextual related searches based on mode and query
        switch ($mode) {
            case 'jobs':
                $related = array(
                    $query . ' london',
                    $query . ' senior',
                    $query . ' director',
                    'private equity ' . explode(' ', $query)[0],
                    $query . ' hedge fund'
                );
                break;

            case 'news':
                $related = array(
                    $query . ' acquisition',
                    $query . ' fundraising',
                    $query . ' exit',
                    $query . ' portfolio',
                    $query . ' investment'
                );
                break;
        }

        $html = '';
        foreach (array_slice($related, 0, 5) as $search) {
            $html .= sprintf(
                '<button type="button" class="sffc-related-link" data-query="%s" data-mode="%s">%s</button>',
                esc_attr($search),
                esc_attr($mode),
                esc_html($search)
            );
        }

        return $html;
    }

    /**
     * Get source URL for display
     */
    private function get_source_url($result)
    {
        $url = $result['url'] ?? '';
        if (empty($url)) return '';

        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';

        // Clean up common prefixes
        $host = preg_replace('/^www\./', '', $host);

        return $host;
    }

    /**
     * Get source name for display
     */
    private function get_source_name($result, $meta = array())
    {
        // Premium source name mapping
        $premium_sources = array(
            'blackstone.com' => 'Blackstone',
            'kkr.com' => 'KKR & Co.',
            'apolloglobal.com' => 'Apollo Global Management',
            'carlyle.com' => 'The Carlyle Group',
            'tpg.com' => 'TPG Inc.',
            'warburg.com' => 'Warburg Pincus',
            'ares.com' => 'Ares Management'
        );

        $type = $this->normalize_result_type($result['type'] ?? '');

        if ($type === 'jobs') {
            $company = $this->extract_meta_value($meta, array('company', 'sffc_company_name', 'company_name')) ?: ($result['company'] ?? '');
            if ($company) {
                return $company;
            }
        }

        if ($type === 'recruiters') {
            $recruiter_company = $this->extract_meta_value($meta, array('company_name', 'company', 'sffc_company_name')) ?: ($result['company'] ?? '');
            if ($recruiter_company) {
                return $recruiter_company;
            }
        }

        if ($type === 'insights') {
            $source = $result['source'] ?? '';
            if (!$source) {
                $source = $this->extract_meta_value($meta, array('news_source', 'source', 'publication'));
            }
            if ($source) {
                return $source;
            }
        }

        if ($type === 'companies') {
            $company_name = $result['company'] ?? ($result['title'] ?? '');
            if ($company_name) {
                return $company_name;
            }
        }

        if ($type === 'templates') {
            $template_category = $this->extract_meta_value($meta, array('category', 'template_type'));
            if ($template_category) {
                return $template_category;
            }
        }

        if (!empty($result['company'])) {
            return $result['company'];
        }

        if (!empty($result['source'])) {
            return $result['source'];
        }

        // Extract from URL and check premium sources
        $url = $result['url'] ?? '';
        if (!empty($url)) {
            $parsed = parse_url($url);
            $host = $parsed['host'] ?? '';
            $clean_host = str_replace('www.', '', $host);

            if (isset($premium_sources[$clean_host])) {
                return $premium_sources[$clean_host];
            }

            // Convert domain to readable name
            $name_parts = explode('.', $clean_host);
            if (count($name_parts) > 1) {
                return ucfirst($name_parts[0]);
            }

            return $clean_host;
        }

        return 'PE Source';
    }

    /**
     * Generate sophisticated breadcrumb for result
     */
    private function generate_breadcrumb($result)
    {
        $breadcrumb_parts = array();

        $type = $this->normalize_result_type($result['type'] ?? '');

        switch ($type) {
            case 'jobs':
                $breadcrumb_parts[] = 'Careers';
                if (!empty($result['company'])) {
                    $breadcrumb_parts[] = $result['company'];
                }
                if (!empty($result['location'])) {
                    $breadcrumb_parts[] = $result['location'];
                }
                if (!empty($result['experience_level'])) {
                    $breadcrumb_parts[] = $result['experience_level'];
                }
                break;

            case 'insights':
                $breadcrumb_parts[] = 'News & Insights';
                if (!empty($result['company'])) {
                    $breadcrumb_parts[] = $result['company'];
                }
                if (!empty($result['deal_type'])) {
                    $breadcrumb_parts[] = $result['deal_type'];
                }
                break;

            case 'companies':
                $breadcrumb_parts[] = 'Company Intelligence';
                if (!empty($result['sector'])) {
                    $breadcrumb_parts[] = $result['sector'];
                }
                if (!empty($result['size'])) {
                    $breadcrumb_parts[] = $result['size'];
                }
                break;

            case 'deals':
                $breadcrumb_parts[] = 'Transaction Data';
                if (!empty($result['deal_type'])) {
                    $breadcrumb_parts[] = $result['deal_type'];
                }
                if (!empty($result['sector'])) {
                    $breadcrumb_parts[] = $result['sector'];
                }
                break;

            case 'recruiters':
                $breadcrumb_parts[] = 'Executive Search';
                if (!empty($result['specialization'])) {
                    $breadcrumb_parts[] = $result['specialization'];
                }
                if (!empty($result['location'])) {
                    $breadcrumb_parts[] = $result['location'];
                }
                break;

            case 'templates':
                $breadcrumb_parts[] = 'Playbooks';
                if (!empty($result['meta']) && !empty($result['meta']['region'])) {
                    $breadcrumb_parts[] = $result['meta']['region'];
                }
                break;

            default:
                $breadcrumb_parts[] = 'PE Intelligence';
        }

        return implode(' › ', $breadcrumb_parts);
    }

    /**
     * Render pagination
     * Uses JavaScript-based pagination to avoid generating indexable URLs
     */
    private function render_pagination($total, $current_page)
    {
        $total_pages = ceil($total / $this->results_per_page);
        if ($total_pages <= 1) return '';

        $html = '<div class="sffc-pagination-container">';
        $html .= '<nav class="sffc-pagination" role="navigation" aria-label="' . esc_attr__('Search results pagination', 'senna-finance-career') . '">';

        // Previous button - use button with data-page instead of anchor with href
        if ($current_page > 1) {
            $html .= sprintf(
                '<button type="button" class="sffc-page-btn sffc-prev-btn" data-page="%d" aria-label="%s">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="15,18 9,12 15,6"></polyline>
                    </svg>
                    %s
                </button>',
                $current_page - 1,
                esc_attr__('Previous page', 'senna-finance-career'),
                esc_html__('Previous', 'senna-finance-career')
            );
        } else {
            $html .= '<span class="sffc-page-btn sffc-prev-btn sffc-disabled" aria-disabled="true">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15,18 9,12 15,6"></polyline>
                </svg>
                ' . esc_html__('Previous', 'senna-finance-career') . '
            </span>';
        }

        // First page + ellipsis logic
        if ($current_page > 4) {
            $html .= sprintf('<button type="button" class="sffc-page-btn" data-page="1" aria-label="%s">1</button>', esc_attr__('Go to page 1', 'senna-finance-career'));

            if ($current_page > 5) {
                $html .= '<span class="sffc-page-ellipsis" aria-hidden="true">…</span>';
            }
        }

        // Page numbers around current page
        $start = max(1, $current_page - 2);
        $end = min($total_pages, $current_page + 2);

        // Adjust range to show first page if we're close to it
        if ($current_page <= 4) {
            $start = 1;
            $end = min($total_pages, 5);
        }

        // Adjust range to show last page if we're close to it
        if ($current_page > $total_pages - 4) {
            $start = max(1, $total_pages - 4);
            $end = $total_pages;
        }

        for ($i = $start; $i <= $end; $i++) {
            if ($i === $current_page) {
                $html .= sprintf(
                    '<span class="sffc-page-btn sffc-active" aria-current="page" aria-label="%s">%d</span>',
                    esc_attr(sprintf(__('Current page %d', 'senna-finance-career'), $i)),
                    $i
                );
            } else {
                $html .= sprintf(
                    '<button type="button" class="sffc-page-btn" data-page="%d" aria-label="%s">%d</button>',
                    $i,
                    esc_attr(sprintf(__('Go to page %d', 'senna-finance-career'), $i)),
                    $i
                );
            }
        }

        // Last page + ellipsis logic
        if ($current_page < $total_pages - 3) {
            if ($current_page < $total_pages - 4) {
                $html .= '<span class="sffc-page-ellipsis" aria-hidden="true">…</span>';
            }

            $html .= sprintf(
                '<button type="button" class="sffc-page-btn" data-page="%d" aria-label="%s">%d</button>',
                $total_pages,
                esc_attr(sprintf(__('Go to page %d', 'senna-finance-career'), $total_pages)),
                $total_pages
            );
        }

        // Next button
        if ($current_page < $total_pages) {
            $html .= sprintf(
                '<button type="button" class="sffc-page-btn sffc-next-btn" data-page="%d" aria-label="%s">
                    %s
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9,18 15,12 9,6"></polyline>
                    </svg>
                </button>',
                $current_page + 1,
                esc_attr__('Next page', 'senna-finance-career'),
                esc_html__('Next', 'senna-finance-career')
            );
        } else {
            $html .= '<span class="sffc-page-btn sffc-next-btn sffc-disabled" aria-disabled="true">
                ' . esc_html__('Next', 'senna-finance-career') . '
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9,18 15,12 9,6"></polyline>
                </svg>
            </span>';
        }

        $html .= '</nav>';

        // Add pagination info
        $start_result = (($current_page - 1) * $this->results_per_page) + 1;
        $end_result = min($current_page * $this->results_per_page, $total);

        $html .= '<div class="sffc-pagination-info">';
        $html .= sprintf(
            esc_html__('Showing %1$s-%2$s of %3$s results', 'senna-finance-career'),
            '<strong>' . number_format($start_result) . '</strong>',
            '<strong>' . number_format($end_result) . '</strong>',
            '<strong>' . number_format($total) . '</strong>'
        );
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Apply shortcode attributes as filter parameters
     * Only sets parameters if they're not already present in URL
     */
    private function apply_shortcode_filters($atts)
    {
        // Filter parameters that can be pre-set via shortcode
        $filter_mappings = array(
            'location' => 'location',
            'category' => 'category', 
            'salary_min' => 'salary_min',
            'salary_max' => 'salary_max',
            'experience' => 'experience',
            'company' => 'company'
        );

        // Apply each filter if not already set in URL and shortcode provides a value
        foreach ($filter_mappings as $att_key => $param_key) {
            if (!empty($atts[$att_key]) && !isset($_GET[$param_key])) {
                $_GET[$param_key] = sanitize_text_field($atts[$att_key]);
            }
        }
        
        // Map category to taxonomy terms for better job filtering
        if (!empty($atts['category']) && $this->current_mode === 'jobs') {
            $this->apply_taxonomy_filters($atts['category']);
        }

        // Handle special mappings for category based on current mode
        if (!empty($atts['category']) && !isset($_GET['filter'])) {
            $_GET['filter'] = $this->map_category_to_filter($atts['category'], $this->current_mode);
        }
    }

    /**
     * Map category shortcode attributes to filter values
     * Different mappings based on search mode
     */
    private function map_category_to_filter($category, $mode = 'jobs')
    {
        // For insights mode, map categories to appropriate insight filters
        if ($mode === 'insights') {
            $insights_mappings = array(
                'investment-banking' => 'relevant',
                'private-equity' => 'deals',
                'hedge-funds' => 'relevant',
                'asset-management' => 'relevant',
                'wealth-management' => 'relevant',
                'trading' => 'recent',
                'research' => 'recent',
                'compliance' => 'relevant',
                'risk' => 'relevant',
                'technology' => 'recent',
                'operations' => 'relevant',
                'deals' => 'deals',
                'markets' => 'recent',
                'news' => 'recent'
            );
            return $insights_mappings[$category] ?? 'relevant';
        }
        
        // For jobs mode, map categories to job-relevant filters
        $jobs_mappings = array(
            'investment-banking' => 'relevant',
            'private-equity' => 'relevant',
            'hedge-funds' => 'relevant', 
            'asset-management' => 'relevant',
            'wealth-management' => 'relevant',
            'trading' => 'recent',
            'research' => 'relevant',
            'compliance' => 'relevant',
            'risk' => 'relevant',
            'technology' => 'relevant',
            'operations' => 'relevant'
        );

        return $jobs_mappings[$category] ?? 'relevant';
    }

    /**
     * Apply taxonomy filters for job-specific categories
     */
    private function apply_taxonomy_filters($category)
    {
        $taxonomy_mappings = array(
            // Map shortcode categories to taxonomy terms
            'investment-banking' => array(
                'taxonomy' => 'job_industry',
                'term' => 'Investment Banking'
            ),
            'private-equity' => array(
                'taxonomy' => 'job_industry', 
                'term' => 'Private Equity'
            ),
            'hedge-funds' => array(
                'taxonomy' => 'job_industry',
                'term' => 'Hedge Funds'
            ),
            'asset-management' => array(
                'taxonomy' => 'job_industry',
                'term' => 'Asset Management'
            ),
            'wealth-management' => array(
                'taxonomy' => 'job_industry',
                'term' => 'Wealth Management'
            ),
            'trading' => array(
                'taxonomy' => 'job_skills',
                'term' => 'Trading'
            ),
            'research' => array(
                'taxonomy' => 'job_skills', 
                'term' => 'Research'
            ),
            'compliance' => array(
                'taxonomy' => 'job_skills',
                'term' => 'Compliance'
            ),
            'risk' => array(
                'taxonomy' => 'job_skills',
                'term' => 'Risk Management'
            ),
            'technology' => array(
                'taxonomy' => 'job_skills',
                'term' => 'Technology'
            ),
            'operations' => array(
                'taxonomy' => 'job_skills',
                'term' => 'Operations'
            )
        );

        if (isset($taxonomy_mappings[$category])) {
            $mapping = $taxonomy_mappings[$category];
            
            // Set taxonomy filter parameter for search system
            $_GET['taxonomy_' . $mapping['taxonomy']] = $mapping['term'];
            
            // Also set as generic industry if it's an industry category
            if ($mapping['taxonomy'] === 'job_industry') {
                $_GET['industry'] = $mapping['term'];
            }
        }
    }

    /**
     * Enqueue assets
     */
    public function enqueue_assets()
    {
        global $post;
        if (!$post || !has_shortcode($post->post_content, 'sffc_pe_search_results')) {
            return;
        }

        wp_enqueue_style(
            'sffc-pe-search-results',
            SFFC_PLUGIN_URL . 'assets/css/pe-search-results.css',
            array(),
            SFFC_VERSION . '.' . time()
        );

        wp_enqueue_script(
            'sffc-pe-search-results',
            SFFC_PLUGIN_URL . 'assets/js/pe-search-results.js',
            array('jquery'),
            SFFC_VERSION . '.' . time(),
            true
        );

        wp_localize_script('sffc-pe-search-results', 'sffc_results', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sffc_results_nonce')
        ));
    }
}

// Initialize
SFFC_PE_Search_Results::get_instance();
