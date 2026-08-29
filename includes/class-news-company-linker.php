<?php

/**
 * News Company Linker
 * Automatically links news items to relevant PE firms using NLP and pattern matching
 * 
 * @package SennaCareers  
 * @since 10.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_News_Company_Linker
{

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Company patterns for matching
     */
    private $company_patterns = array();

    /**
     * Registry helper
     */
    private $registry = null;

    /**
     * Map of registry IDs to company post IDs
     */
    private $registry_map = array();

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
        if (class_exists('SFFC_Company_Registry')) {
            $this->registry = SFFC_Company_Registry::get_instance();
        }

        $this->load_company_patterns();
        $this->init_hooks();
    }

    /**
     * Load company patterns for matching
     */
    private function load_company_patterns()
    {
        $this->company_patterns = array();
        $this->registry_map = array();

        if ($this->registry && $this->load_patterns_from_registry()) {
            return;
        }

        $companies = get_posts(array(
            'post_type' => 'sffc_company',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));

        foreach ($companies as $company) {
            $primary = $company->post_title;

            if (class_exists('SFFC_Company_Title_Helper')) {
                $canonical = SFFC_Company_Title_Helper::get_canonical_name($company);
                if ($canonical !== '') {
                    $primary = $canonical;
                }
            }
            $alias_list = $this->sanitize_alias_list($this->get_company_aliases($company->ID));
            $patterns = array(
                'primary' => $primary,
                'aliases' => $alias_list,
                'executives' => $this->get_company_executives($company->ID),
                'portfolio' => $this->get_portfolio_companies($company->ID),
                'registry_id' => null,
                'normalized_primary' => $this->normalize_for_registry($primary)
            );

            $this->company_patterns[$company->ID] = $patterns;
        }
    }

    /**
     * Load patterns directly from the company registry when available
     */
    private function load_patterns_from_registry()
    {
        global $wpdb;

        $registry_table = $wpdb->prefix . 'sffc_companies_registry';
        $alias_table = $wpdb->prefix . 'sffc_company_aliases';

        $registry_rows = $wpdb->get_results(
            "SELECT id, company_post_id, canonical_name, preferred_alias FROM $registry_table WHERE status = 'active' AND company_post_id IS NOT NULL",
            ARRAY_A
        );

        if (empty($registry_rows)) {
            return false;
        }

        $alias_map = array();
        $registry_ids = array();

        foreach ($registry_rows as $row) {
            $company_id = intval($row['company_post_id']);
            if (!$company_id) {
                continue;
            }
            $registry_ids[] = intval($row['id']);
        }

        if (!empty($registry_ids)) {
            $ids_sql = implode(',', array_map('intval', $registry_ids));
            $alias_rows = $wpdb->get_results(
                "SELECT company_id, alias FROM $alias_table WHERE company_id IN ($ids_sql) AND alias <> ''",
                ARRAY_A
            );

            foreach ($alias_rows as $alias_row) {
                $company_key = intval($alias_row['company_id']);
                if (!isset($alias_map[$company_key])) {
                    $alias_map[$company_key] = array();
                }
                $alias_map[$company_key][] = $alias_row['alias'];
            }
        }

        foreach ($registry_rows as $row) {
            $registry_id = intval($row['id']);
            $company_id = intval($row['company_post_id']);
            $primary = $row['canonical_name'];

            if (!$company_id || empty($primary)) {
                continue;
            }

            $aliases = array();
            if (!empty($primary)) {
                $aliases[] = $primary;
            }
            if (!empty($row['preferred_alias'])) {
                $aliases[] = $row['preferred_alias'];
            }
            if (!empty($alias_map[$registry_id])) {
                $aliases = array_merge($aliases, $alias_map[$registry_id]);
            }

            // Include legacy aliases stored on the post
            $aliases = array_merge($aliases, $this->get_company_aliases($company_id));

            $patterns = array(
                'primary' => $primary,
                'aliases' => $this->sanitize_alias_list($aliases),
                'executives' => $this->get_company_executives($company_id),
                'portfolio' => $this->get_portfolio_companies($company_id),
                'registry_id' => $registry_id,
                'normalized_primary' => $this->normalize_for_registry($primary)
            );

            $this->company_patterns[$company_id] = $patterns;
            $this->registry_map[$registry_id] = $company_id;
        }

        return !empty($this->company_patterns);
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        // Hook into feed processing
        add_action('sffc_process_feed_item', array($this, 'process_news_item'), 10, 2);

        // Hook into manual news creation
        add_action('save_post', array($this, 'process_manual_news'), 10, 2);

        // Cron job for batch processing
        add_action('sffc_process_unlinked_news', array($this, 'batch_process_unlinked_news'));

        if (!wp_next_scheduled('sffc_process_unlinked_news')) {
            wp_schedule_event(time(), 'hourly', 'sffc_process_unlinked_news');
        }

        add_action('sffc_prune_news_articles', array($this, 'prune_old_news_articles'));

        if (!wp_next_scheduled('sffc_prune_news_articles')) {
            wp_schedule_event(time(), 'daily', 'sffc_prune_news_articles');
        }

        // AJAX endpoints
        add_action('wp_ajax_sffc_relink_news', array($this, 'ajax_relink_news'));
        add_action('wp_ajax_sffc_get_news_companies', array($this, 'ajax_get_news_companies'));
    }

    /**
     * Process news item and link to companies
     */
    public function process_news_item($news_data, $source)
    {
        // Extract text for analysis
        $text = $this->prepare_text_for_analysis($news_data);

        // Find matching companies
        $matched_companies = $this->find_matching_companies($text);

        // Store the relationships
        if (!empty($matched_companies)) {
            $this->store_news_company_links($news_data, $matched_companies);
        }

        return $news_data;
    }

    public function ensure_news_article($news_data)
    {
        return $this->get_or_create_news_item($news_data);
    }

    /**
     * Diagnose matches for a news payload without storing it.
     */
    public function diagnose_news_item(array $news_data)
    {
        $text = $this->prepare_text_for_analysis($news_data);
        $matches = $this->find_matching_companies($text);

        return array(
            'matches' => $matches,
            'text' => $text,
        );
    }

    /**
     * Prepare text for analysis
     */
    private function prepare_text_for_analysis($news_data)
    {
        $text = '';

        if (isset($news_data['title'])) {
            $text .= ' ' . $news_data['title'];
        }

        if (isset($news_data['description'])) {
            $text .= ' ' . $news_data['description'];
        }

        if (isset($news_data['content'])) {
            $text .= ' ' . $news_data['content'];
        }

        // Clean and normalize
        $text = strip_tags($text);
        $text = html_entity_decode($text);

        return $text;
    }

    /**
     * Find matching companies in text
     */
    private function find_matching_companies($text)
    {
        $matches = array();
        $text_lower = strtolower($text);

        foreach ($this->company_patterns as $company_id => $patterns) {
            $relevance_score = 0;
            $matched_terms = array();
            $match_context = array();
            $matched_sources = array();

            $primary_name = $patterns['primary'];
            $normalized_primary = isset($patterns['normalized_primary'])
                ? $patterns['normalized_primary']
                : $this->normalize_for_registry($primary_name);

            if ($this->text_contains_term($text_lower, $primary_name)) {
                $relevance_score += 20;
                $matched_terms[] = $primary_name;
                $match_context[] = $this->get_match_context($text, $primary_name);
                $matched_sources[] = 'primary';
            }

            foreach ($patterns['aliases'] as $alias) {
                if ($this->text_contains_term($text_lower, $alias)) {
                    $relevance_score += 15;
                    $matched_terms[] = $alias;
                    $match_context[] = $this->get_match_context($text, $alias);
                    $matched_sources[] = 'alias';
                }
            }

            foreach ($patterns['executives'] as $executive) {
                if ($this->text_contains_term($text_lower, $executive)) {
                    $relevance_score += 10;
                    $matched_terms[] = $executive;
                    $match_context[] = $this->get_match_context($text, $executive);
                    $matched_sources[] = 'executive';
                }
            }

            foreach ($patterns['portfolio'] as $portfolio_company) {
                if ($this->text_contains_term($text_lower, $portfolio_company)) {
                    $relevance_score += 5;
                    $matched_terms[] = $portfolio_company;
                    $match_context[] = $this->get_match_context($text, $portfolio_company);
                    $matched_sources[] = 'portfolio';
                }
            }

            if ($relevance_score > 0) {
                $matches[] = array(
                    'company_id' => intval($company_id),
                    'registry_id' => isset($patterns['registry_id']) ? intval($patterns['registry_id']) : null,
                    'primary_name' => $primary_name,
                    'normalized_name' => $normalized_primary,
                    'relevance_score' => $relevance_score,
                    'matched_terms' => array_values(array_unique($matched_terms)),
                    'match_context' => array_values(array_unique($match_context)),
                    'matched_via' => array_values(array_unique($matched_sources)),
                    'confidence' => $this->calculate_confidence($relevance_score)
                );
            }
        }

        usort($matches, function ($a, $b) {
            return $b['relevance_score'] - $a['relevance_score'];
        });

        return $matches;
    }

    /**
     * Normalize an array of alias values
     */
    private function sanitize_alias_list($aliases)
    {
        if (empty($aliases)) {
            return array();
        }

        if (!is_array($aliases)) {
            $aliases = array($aliases);
        }

        $aliases = array_map(function ($alias) {
            return is_string($alias) ? trim($alias) : '';
        }, $aliases);

        $aliases = array_filter($aliases, function ($alias) {
            return $alias !== '';
        });

        return array_values(array_unique($aliases));
    }

    /**
     * Normalize company names the same way the registry does
     */
    private function normalize_for_registry($term)
    {
        if ($this->registry && method_exists($this->registry, 'normalize_name')) {
            return $this->registry->normalize_name($term);
        }

        if (!is_string($term) || $term === '') {
            return '';
        }

        $term = strtolower($term);
        $term = preg_replace('/[&\.]/', ' ', $term);
        $term = preg_replace('/[^a-z0-9\s]/', ' ', $term);
        $parts = array_filter(array_map('trim', explode(' ', $term)));

        if (empty($parts)) {
            return '';
        }

        $suffixes = array('inc', 'incorporated', 'llc', 'llp', 'lp', 'limited', 'ltd', 'plc', 'corp', 'corporation', 'co');
        $filtered = array();

        foreach ($parts as $part) {
            if (in_array($part, $suffixes, true)) {
                continue;
            }
            $filtered[] = $part;
        }

        return implode(' ', $filtered);
    }

    /**
     * Push resolved news matches back into the registry for auditing
     */
    private function sync_registry_from_news($match, $news_id, $news_data)
    {
        if (!$this->registry) {
            return;
        }

        $registry_id = isset($match['registry_id']) ? intval($match['registry_id']) : 0;
        if (!$registry_id) {
            return;
        }

        $relevance = isset($match['relevance_score']) ? floatval($match['relevance_score']) : 0.0;
        $confidence = max(0.5, min(0.99, $relevance / 40));

        $alias_id = null;
        $matched_terms = isset($match['matched_terms']) ? (array) $match['matched_terms'] : array();
        foreach ($matched_terms as $alias_term) {
            if (!is_string($alias_term) || $alias_term === '') {
                continue;
            }
            $result = $this->registry->add_alias($registry_id, $alias_term, 'news', $confidence);
            if ($result) {
                $alias_id = $result;
            }
        }

        $reference = '';
        if (!empty($news_data['id'])) {
            $reference = (string) $news_data['id'];
        } elseif (!empty($news_data['link'])) {
            $reference = (string) $news_data['link'];
        } else {
            $reference = isset($news_data['title']) ? (string) $news_data['title'] : 'news:' . $news_id;
        }
        $reference = substr($reference, 0, 255);

        $strategy = isset($match['matched_via']) && !empty($match['matched_via'])
            ? implode(',', (array) $match['matched_via'])
            : 'news_linker';

        $notes = '';
        if (!empty($news_data['source'])) {
            $notes = substr((string) $news_data['source'], 0, 255);
        }

        if (method_exists($this->registry, 'log_news_resolution')) {
            $this->registry->log_news_resolution(
                $reference,
                isset($match['primary_name']) ? $match['primary_name'] : '',
                isset($match['normalized_name']) ? $match['normalized_name'] : $this->normalize_for_registry(isset($match['primary_name']) ? $match['primary_name'] : ''),
                $registry_id,
                $alias_id,
                $confidence,
                substr($strategy, 0, 120),
                $notes
            );
        }
    }

    /**
     * Check if text contains term (with word boundaries)
     */
    private function text_contains_term($text, $term)
    {
        if (empty($term)) return false;

        $term_lower = strtolower($term);

        // Check for exact match with word boundaries
        $pattern = '/\b' . preg_quote($term_lower, '/') . '\b/i';
        return preg_match($pattern, $text);
    }

    /**
     * Get context around match
     */
    private function get_match_context($text, $term, $context_length = 100)
    {
        $pos = stripos($text, $term);
        if ($pos === false) return '';

        $start = max(0, $pos - $context_length);
        $end = min(strlen($text), $pos + strlen($term) + $context_length);

        $context = substr($text, $start, $end - $start);

        // Add ellipsis if truncated
        if ($start > 0) $context = '...' . $context;
        if ($end < strlen($text)) $context = $context . '...';

        return $context;
    }

    /**
     * Calculate confidence score
     */
    private function calculate_confidence($relevance_score)
    {
        if ($relevance_score >= 30) return 'high';
        if ($relevance_score >= 15) return 'medium';
        return 'low';
    }

    /**
     * Store news company links
     */
    private function store_news_company_links($news_data, $matched_companies)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'sffc_company_news_links';

        // Get or create news item ID
        $news_id = $this->get_or_create_news_item($news_data);

        if (!$news_id) {
            return;
        }

        foreach ($matched_companies as $match) {
            $company_id = isset($match['company_id']) ? intval($match['company_id']) : 0;
            if (!$company_id) {
                continue;
            }

            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name 
                WHERE company_id = %d AND news_item_id = %d",
                $company_id,
                $news_id
            ));

            if ($existing) {
                continue;
            }

            $wpdb->insert(
                $table_name,
                array(
                    'company_id' => $company_id,
                    'news_item_id' => $news_id,
                    'relevance_score' => isset($match['relevance_score']) ? floatval($match['relevance_score']) : 0,
                    'matched_terms' => json_encode($match['matched_terms'])
                ),
                array('%d', '%d', '%f', '%s')
            );

            $this->update_company_news_count($company_id);
            $this->sync_registry_from_news($match, $news_id, $news_data);

            if (class_exists('SFFC_Company_Profile_Aggregator')) {
                SFFC_Company_Profile_Aggregator::clear_profile_cache($company_id);
            }
        }

        $this->sync_news_article_companies($news_id, $matched_companies);
    }

    /**
     * Get or create news item
     */
    private function get_or_create_news_item($news_data)
    {
        $link = isset($news_data['link']) ? trim((string) $news_data['link']) : '';
        $link_hash = $link !== '' ? md5(strtolower($link)) : '';

        $existing = array();

        if ($link !== '') {
            $existing = get_posts(array(
                'post_type' => array('sffc_news_article', 'post'),
                'posts_per_page' => 1,
                'meta_query' => array(
                    'relation' => 'OR',
                    array(
                        'key' => '_sffc_news_link_hash',
                        'value' => $link_hash,
                        'compare' => '='
                    ),
                    array(
                        'key' => '_news_source_url',
                        'value' => $link,
                        'compare' => '='
                    )
                ),
                'fields' => 'all'
            ));
        }

        if (!empty($existing)) {
            $post = $existing[0];
            $post_id = $post->ID;

            if ($post->post_type !== 'sffc_news_article') {
                wp_update_post(array(
                    'ID' => $post_id,
                    'post_type' => 'sffc_news_article'
                ));
            }

            $this->sync_news_article_meta($post_id, $news_data, $link_hash);
            return $post_id;
        }

        $title = isset($news_data['title']) ? sanitize_text_field($news_data['title']) : __('Untitled News', 'senna-finance');
        $description = isset($news_data['description']) ? wp_kses_post($news_data['description']) : '';
        $excerpt = $description !== '' ? wp_trim_words(wp_strip_all_tags($description), 40) : '';

        $timestamp = isset($news_data['pubDate']) && is_numeric($news_data['pubDate'])
            ? intval($news_data['pubDate'])
            : current_time('timestamp');

        $post_date_gmt = gmdate('Y-m-d H:i:s', $timestamp);
        $post_date = get_date_from_gmt($post_date_gmt);

        $post_id = wp_insert_post(array(
            'post_type' => 'sffc_news_article',
            'post_status' => 'publish',
            'post_title' => $title,
            'post_content' => $description,
            'post_excerpt' => $excerpt,
            'post_date' => $post_date,
            'post_date_gmt' => $post_date_gmt
        ), true);

        if (is_wp_error($post_id)) {
            if (WP_DEBUG_LOG) {
                error_log('SFFC: Failed to create news article - ' . $post_id->get_error_message());
            }
            return false;
        }

        $this->sync_news_article_meta($post_id, $news_data, $link_hash);

        return $post_id;
    }

    private function sync_news_article_meta($post_id, $news_data, $link_hash)
    {
        $source = isset($news_data['source']) ? sanitize_text_field($news_data['source']) : '';
        $link = isset($news_data['link']) ? esc_url_raw($news_data['link']) : '';
        $timestamp = isset($news_data['pubDate']) && is_numeric($news_data['pubDate'])
            ? intval($news_data['pubDate'])
            : current_time('timestamp');

        if ($link_hash !== '') {
            update_post_meta($post_id, '_sffc_news_link_hash', $link_hash);
        }

        if ($link !== '') {
            update_post_meta($post_id, '_sffc_news_source_url', $link);
            update_post_meta($post_id, '_news_source_url', $link); // Legacy support
        }

        if ($source !== '') {
            update_post_meta($post_id, '_sffc_news_source', $source);
            update_post_meta($post_id, '_news_source', $source); // Legacy support
        }

        update_post_meta($post_id, '_sffc_news_pub_date', $timestamp);
        update_post_meta($post_id, '_news_pub_date', $timestamp); // Legacy support
    }

    private function sync_news_article_companies($post_id, array $matched_companies)
    {
        if (empty($matched_companies)) {
            return;
        }

        $existing_meta = get_post_meta($post_id, '_sffc_news_company');
        $existing_ids = array_map('intval', $existing_meta);

        $company_ids = array();
        $match_snapshot = array();

        foreach ($matched_companies as $match) {
            $company_id = isset($match['company_id']) ? intval($match['company_id']) : 0;
            if (!$company_id) {
                continue;
            }

            $company_ids[$company_id] = true;
            $match_snapshot[] = array(
                'company_id' => $company_id,
                'relevance' => isset($match['relevance_score']) ? floatval($match['relevance_score']) : 0,
                'confidence' => isset($match['confidence']) ? $match['confidence'] : '',
                'matched_terms' => isset($match['matched_terms']) ? array_values(array_unique((array) $match['matched_terms'])) : array()
            );
        }

        if (!empty($company_ids)) {
            // Remove stale associations
            foreach ($existing_ids as $existing_id) {
                if (!isset($company_ids[$existing_id])) {
                    delete_post_meta($post_id, '_sffc_news_company', $existing_id);
                }
            }

            foreach (array_keys($company_ids) as $company_id) {
                if (!in_array($company_id, $existing_ids, true)) {
                    add_post_meta($post_id, '_sffc_news_company', $company_id);
                }
            }
        }

        if (!empty($match_snapshot)) {
            update_post_meta($post_id, '_sffc_news_match_snapshot', wp_json_encode($match_snapshot));
        }
    }

    public function prune_old_news_articles()
    {
        $batch_size = 200;

        do {
            $old_articles = get_posts(array(
                'post_type' => 'sffc_news_article',
                'post_status' => 'publish',
                'fields' => 'ids',
                'posts_per_page' => $batch_size,
                'date_query' => array(
                    array(
                        'column' => 'post_date',
                        'before' => '45 days ago',
                        'inclusive' => false,
                    ),
                ),
            ));

            foreach ($old_articles as $article_id) {
                wp_trash_post($article_id);
            }
        } while (!empty($old_articles) && count($old_articles) === $batch_size);
    }

    /**
     * Get news category ID
     */
    private function get_news_category_id()
    {
        $category = get_category_by_slug('market-news');

        if (!$category) {
            $cat_id = wp_create_category('Market News');
            return $cat_id;
        }

        return $category->term_id;
    }

    /**
     * Update company news count
     */
    private function update_company_news_count($company_id)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'sffc_company_news_links';

        // Count today's news
        $today_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
            WHERE company_id = %d 
            AND DATE(created_at) = CURDATE()",
            $company_id
        ));

        // Count this week's news
        $week_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
            WHERE company_id = %d 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            $company_id
        ));

        update_post_meta($company_id, '_sffc_news_count_today', $today_count);
        update_post_meta($company_id, '_sffc_news_count_week', $week_count);
    }

    /**
     * Get company aliases
     */
    private function get_company_aliases($company_id)
    {
        $aliases = get_post_meta($company_id, '_sffc_aliases', true);

        if (empty($aliases)) {
            return array();
        }

        if (is_string($aliases)) {
            return array_map('trim', explode(',', $aliases));
        }

        return $aliases;
    }

    /**
     * Get company executives
     */
    private function get_company_executives($company_id)
    {
        $executives = get_post_meta($company_id, '_sffc_executives', true);

        if (empty($executives)) {
            return array();
        }

        if (is_string($executives)) {
            return array_map('trim', explode(',', $executives));
        }

        return $executives;
    }

    /**
     * Get portfolio companies
     */
    private function get_portfolio_companies($company_id)
    {
        $portfolio = get_post_meta($company_id, '_sffc_portfolio_list', true);

        if (empty($portfolio)) {
            return array();
        }

        if (is_string($portfolio)) {
            $portfolio = json_decode($portfolio, true);
        }

        $company_names = array();
        if (is_array($portfolio)) {
            foreach ($portfolio as $company) {
                if (isset($company['name'])) {
                    $company_names[] = $company['name'];
                }
            }
        }

        return $company_names;
    }

    /**
     * Process manual news (when saving post)
     */
    public function process_manual_news($post_id, $post)
    {
        // Only process published posts
        if ($post->post_status !== 'publish') {
            return;
        }

        // Only process posts in news category
        if (!in_category('market-news', $post_id)) {
            return;
        }

        // Skip if already processed
        if (get_post_meta($post_id, '_sffc_companies_linked', true)) {
            return;
        }

        // Process the news item
        $news_data = array(
            'title' => $post->post_title,
            'description' => $post->post_content,
            'link' => get_permalink($post_id)
        );

        $text = $this->prepare_text_for_analysis($news_data);
        $matched_companies = $this->find_matching_companies($text);

        if (!empty($matched_companies)) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'sffc_company_news_links';

            foreach ($matched_companies as $match) {
                $wpdb->insert(
                    $table_name,
                    array(
                        'company_id' => $match['company_id'],
                        'news_item_id' => $post_id,
                        'relevance_score' => $match['relevance_score'],
                        'matched_terms' => json_encode($match['matched_terms'])
                    ),
                    array('%d', '%d', '%f', '%s')
                );

                $this->update_company_news_count($match['company_id']);
            }
        }

        // Mark as processed
        update_post_meta($post_id, '_sffc_companies_linked', true);
        update_post_meta($post_id, '_sffc_linked_companies', $matched_companies);
    }

    /**
     * Batch process unlinked news
     */
    public function batch_process_unlinked_news()
    {
        $unlinked_posts = get_posts(array(
            'post_type' => 'post',
            'category_name' => 'market-news',
            'posts_per_page' => 20,
            'meta_query' => array(
                array(
                    'key' => '_sffc_companies_linked',
                    'compare' => 'NOT EXISTS'
                )
            )
        ));

        foreach ($unlinked_posts as $post) {
            $this->process_manual_news($post->ID, $post);
        }
    }

    /**
     * AJAX handler for relinking news
     */
    public function ajax_relink_news()
    {
        $news_id = isset($_POST['news_id']) ? intval($_POST['news_id']) : 0;

        if (!$news_id) {
            wp_send_json_error('Invalid news ID');
        }

        // Clear existing links
        global $wpdb;
        $table_name = $wpdb->prefix . 'sffc_company_news_links';
        $wpdb->delete($table_name, array('news_item_id' => $news_id));

        // Clear meta
        delete_post_meta($news_id, '_sffc_companies_linked');
        delete_post_meta($news_id, '_sffc_linked_companies');

        // Reprocess
        $post = get_post($news_id);
        if ($post) {
            $this->process_manual_news($news_id, $post);
        }

        wp_send_json_success('News relinked successfully');
    }

    /**
     * AJAX handler for getting news companies
     */
    public function ajax_get_news_companies()
    {
        $news_id = isset($_POST['news_id']) ? intval($_POST['news_id']) : 0;

        if (!$news_id) {
            wp_send_json_error('Invalid news ID');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'sffc_company_news_links';

        $companies = $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, cnl.relevance_score, cnl.matched_terms 
            FROM $table_name cnl 
            JOIN {$wpdb->posts} c ON cnl.copany_id = c.ID 
            WHERE cnl.news_item_id = %d 
            ORDER BY cnl.relevance_score DESC",
            $news_id
        ));

        wp_send_json_success($companies);
    }
}

// Initialize
SFFC_News_Company_Linker::get_instance();
