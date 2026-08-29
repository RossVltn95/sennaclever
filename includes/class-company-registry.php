<?php
/**
 * Company Registry and Entity Resolver
 *
 * Maintains a canonical registry of companies, aliases, and resolution audits.
 * Provides deterministic and fuzzy matching logic to keep feeds aligned to a
 * single company profile and avoid duplicate programmatic SEO pages.
 *
 * @package SennaCareers
 * @since 10.18.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Company_Registry {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * WordPress database handle
     */
    private $wpdb;

    /**
     * Registry table name
     */
    private $registry_table;

    /**
     * Alias table name
     */
    private $alias_table;

    /**
     * Resolution audit table name
     */
    private $audit_table;

    /**
     * Option key for registry backfill progress
     */
    private $backfill_option = 'sffc_registry_backfill_state';

    /**
     * Backfill batch size
     */
    private $backfill_batch_size = 50;

    /**
     * Cached generic suffixes we strip out during normalization
     */
    private $generic_suffixes = array(
        'inc', 'inc.', 'incorporated', 'llc', 'l.l.c', 'llp', 'l.l.p', 'lp', 'l.p',
        'limited', 'ltd', 'ltd.', 'plc', 'corp', 'corp.', 'corporation', 'co', 'co.'
    );

    /**
     * Get singleton instance
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
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->registry_table = $wpdb->prefix . 'sffc_companies_registry';
        $this->alias_table = $wpdb->prefix . 'sffc_company_aliases';
        $this->audit_table = $wpdb->prefix . 'sffc_company_resolution_audit';

        add_action('init', array($this, 'bootstrap'), 15);
        add_action('admin_init', array($this, 'maybe_backfill_registry'), 20);
    }

    /**
     * Ensure the registry is ready before use
     */
    public function bootstrap() {
        // Nothing to do yet, but left in place for future cache priming or warmups.
    }

    /**
     * Maybe backfill registry data from existing company posts (admin side only)
     */
    public function maybe_backfill_registry() {
        if (!is_admin()) {
            return;
        }

        $doing_ajax = function_exists('wp_doing_ajax') ? wp_doing_ajax() : (defined('DOING_AJAX') && DOING_AJAX);
        if ($doing_ajax) {
            return;
        }

        $state = get_option($this->backfill_option, array());
        $state = wp_parse_args($state, array(
            'offset' => 0,
            'total' => 0,
            'started_at' => '',
            'completed' => false,
            'completed_at' => ''
        ));

        if (!empty($state['completed'])) {
            return;
        }

        if (empty($state['started_at'])) {
            $state['started_at'] = current_time('mysql');
        }

        $counts = wp_count_posts('sffc_company');
        $state['total'] = $counts && isset($counts->publish) ? (int) $counts->publish : 0;

        if ($state['total'] === 0) {
            $state['completed'] = true;
            $state['completed_at'] = current_time('mysql');
            update_option($this->backfill_option, $state, false);
            return;
        }

        $posts = get_posts(array(
            'post_type' => 'sffc_company',
            'post_status' => 'publish',
            'orderby' => 'ID',
            'order' => 'ASC',
            'offset' => intval($state['offset']),
            'posts_per_page' => $this->backfill_batch_size,
            'fields' => 'ids',
            'no_found_rows' => true,
            'suppress_filters' => true,
        ));

        if (empty($posts)) {
            $state['completed'] = true;
            $state['completed_at'] = current_time('mysql');
            update_option($this->backfill_option, $state, false);
            return;
        }

        foreach ($posts as $company_id) {
            $this->record_company_creation($company_id, array(
                'source' => 'registry_backfill',
                'created_via' => 'backfill',
                'confidence' => 0.90,
            ));
        }

        $state['offset'] += count($posts);

        if ($state['offset'] >= $state['total']) {
            $state['completed'] = true;
            $state['completed_at'] = current_time('mysql');
        }

        update_option($this->backfill_option, $state, false);
    }

    /**
     * Resolve company data coming from a job feed
     *
     * @param array $job_data
     * @return array|null Details about the matched company or null if no match
     */
    public function resolve_company_from_job(array $job_data) {
        $raw_name = isset($job_data['company']) ? trim($job_data['company']) : '';

        if ('' === $raw_name || 'Confidential' === $raw_name) {
            return null;
        }

        $normalized = $this->normalize_term($raw_name);
        if ('' === $normalized) {
            return null;
        }

        // 1. Direct registry match by normalized name or slug
        $registry_match = $this->get_registry_match($raw_name, $normalized);
        if ($registry_match) {
            $this->record_alias_if_needed((int) $registry_match['id'], $raw_name, 'job', 0.85, $registry_match['matched_via']);
            $this->log_resolution('job', $job_data, $registry_match, $normalized, $registry_match['matched_via']);
            return $registry_match;
        }

        // 2. Try alias lookup
        $alias_match = $this->get_alias_match($normalized);
        if ($alias_match) {
            $registry = $this->get_registry_row((int) $alias_match['company_id']);
            if ($registry) {
                $this->record_alias_if_needed((int) $registry['id'], $raw_name, 'job', 0.75, 'alias_lookup');
                $registry['matched_via'] = 'alias_lookup';
                $registry['matched_alias_id'] = (int) $alias_match['id'];
                $this->log_resolution('job', $job_data, $registry, $normalized, 'alias_lookup', (int) $alias_match['id']);
                return $registry;
            }
        }

        // 3. Fallback to WP post lookup by slug / title
        $post_match = $this->find_company_post($raw_name, $job_data);
        if ($post_match) {
            $registry = $this->ensure_registry_entry($post_match, $raw_name, $normalized, $job_data);
            $registry['matched_via'] = 'post_lookup';
            $this->record_alias_if_needed((int) $registry['id'], $raw_name, 'job', 0.65, 'post_lookup');
            $this->log_resolution('job', $job_data, $registry, $normalized, 'post_lookup');
            return $registry;
        }

        // 4. No match found
        $this->log_resolution('job', $job_data, null, $normalized, 'unmatched');
        return null;
    }

    /**
     * Record registry entry when a new company profile is published
     *
     * @param int   $company_post_id
     * @param array $context
     */
    public function record_company_creation($company_post_id, array $context = array()) {
        $post = get_post($company_post_id);
        if (!$post) {
            return;
        }

        if (class_exists('SFFC_Company_Title_Helper')) {
            SFFC_Company_Title_Helper::ensure_canonical_meta($company_post_id);
            SFFC_Company_Title_Helper::ensure_seo_title($company_post_id);
            $canonical_name = SFFC_Company_Title_Helper::get_canonical_name($post);
        } else {
            $canonical_name = $post->post_title;
        }
        $normalized = $this->normalize_term($canonical_name);
        $slug = $post->post_name;

        $defaults = array(
            'hq_city' => get_post_meta($company_post_id, '_sffc_headquarters_city', true) ?: '',
            'hq_country' => get_post_meta($company_post_id, '_sffc_headquarters_country', true) ?: '',
            'website' => get_post_meta($company_post_id, '_sffc_website', true) ?: '',
            'linkedin' => get_post_meta($company_post_id, '_sffc_linkedin', true) ?: '',
            'primary_sector' => $this->resolve_primary_sector($company_post_id)
        );

        $context = array_merge($defaults, $context);

        $registry_id = $this->get_registry_id_by_post($company_post_id);

        $data = array(
            'company_post_id' => $company_post_id,
            'canonical_name' => $canonical_name,
            'normalized_name' => $normalized,
            'slug' => $slug,
            'preferred_alias' => $canonical_name,
            'website_domain' => $this->extract_domain($context['website']),
            'linkedin_url' => $context['linkedin'],
            'hq_city' => $context['hq_city'],
            'hq_country' => $context['hq_country'],
            'primary_sector' => $context['primary_sector'],
            'confidence_score' => isset($context['confidence']) ? floatval($context['confidence']) : 1.0,
            'metadata' => maybe_serialize(array(
                'source' => $context['source'] ?? 'job_ingest',
                'created_via' => $context['created_via'] ?? 'automatic'
            )),
            'last_enriched' => current_time('mysql')
        );

        $formats = array('%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%f','%s','%s');

        if ($registry_id) {
            $this->wpdb->update(
                $this->registry_table,
                $data,
                array('id' => $registry_id),
                $formats,
                array('%d')
            );
        } else {
            $insert_data = $data;
            $insert_data['first_seen'] = current_time('mysql');
            $insert_formats = $formats;
            $insert_formats[] = '%s';

            $this->wpdb->insert($this->registry_table, $insert_data, $insert_formats);
            $registry_id = (int) $this->wpdb->insert_id;
        }

        if ($registry_id) {
            $this->record_alias_if_needed($registry_id, $canonical_name, 'canonical', 1.0, 'post_creation', true);
        }
    }

    /**
     * Public helper to add aliases from external systems
     */
    public function add_alias($registry_id, $alias, $source = 'news', $confidence = 0.8, $strategy = 'news_linker', $is_primary = false) {
        if (!$registry_id || empty($alias)) {
            return null;
        }

        return $this->record_alias_if_needed($registry_id, $alias, $source, $confidence, $strategy, $is_primary);
    }

    /**
     * Expose normalization logic for other components
     */
    public function normalize_name($term) {
        return $this->normalize_term($term);
    }

    /**
     * Log news-driven resolution events into the audit trail
     */
    public function log_news_resolution($reference, $raw_name, $normalized, $registry_id = null, $alias_id = null, $confidence = 0.0, $strategy = 'news_linker', $notes = '') {
        $reference = substr((string) $reference, 0, 255);
        $raw_name = substr((string) $raw_name, 0, 255);
        $normalized = substr((string) $normalized, 0, 255);
        $strategy = substr((string) $strategy, 0, 120);
        $notes = substr((string) $notes, 0, 255);

        $this->wpdb->insert(
            $this->audit_table,
            array(
                'source_type' => 'news',
                'source_reference' => $reference !== '' ? $reference : null,
                'raw_company_name' => $raw_name,
                'normalized_company_name' => $normalized,
                'matched_company_id' => $registry_id ? (int) $registry_id : null,
                'matched_alias_id' => $alias_id ? (int) $alias_id : null,
                'confidence_score' => floatval($confidence),
                'match_strategy' => $strategy,
                'notes' => $notes
            ),
            array('%s','%s','%s','%s','%d','%d','%f','%s','%s')
        );
    }

    /**
     * Locate an existing registry entry by raw or normalized name
     */
    private function get_registry_match($raw_name, $normalized) {
        $slug = sanitize_title($raw_name);

        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->registry_table} WHERE normalized_name = %s OR slug = %s LIMIT 1",
            $normalized,
            $slug
        );

        $row = $this->wpdb->get_row($query, ARRAY_A);
        if ($row) {
            $row['matched_via'] = $row['normalized_name'] === $normalized ? 'normalized_name' : 'slug';
            return $row;
        }

        return null;
    }

    /**
     * Fetch specific registry row
     */
    private function get_registry_row($registry_id) {
        $query = $this->wpdb->prepare("SELECT * FROM {$this->registry_table} WHERE id = %d", $registry_id);
        return $this->wpdb->get_row($query, ARRAY_A);
    }

    /**
     * Attempt alias lookup
     */
    private function get_alias_match($normalized_alias) {
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->alias_table} WHERE normalized_alias = %s ORDER BY confidence_score DESC LIMIT 1",
            $normalized_alias
        );
        return $this->wpdb->get_row($query, ARRAY_A);
    }

    /**
     * Look up company post using slug/title fallbacks
     */
    private function find_company_post($raw_name, array $job_data) {
        $slug = sanitize_title($raw_name);
        $post = get_page_by_path($slug, OBJECT, 'sffc_company');
        if ($post) {
            return $post;
        }

        if (class_exists('SFFC_Company_Title_Helper')) {
            $candidates = array($raw_name);
            $stripped = SFFC_Company_Title_Helper::strip_seo_suffix($raw_name);
            if ($stripped && !in_array($stripped, $candidates, true)) {
                $candidates[] = $stripped;
            }

            $existing = get_posts(array(
                'post_type' => 'sffc_company',
                'posts_per_page' => 1,
                'post_status' => 'publish',
                'meta_query' => array(
                    array(
                        'key' => SFFC_Company_Title_Helper::META_CANONICAL_NAME,
                        'value' => $candidates,
                        'compare' => 'IN'
                    )
                )
            ));

            if (!empty($existing)) {
                return $existing[0];
            }

            $seo_title = SFFC_Company_Title_Helper::build_seo_title($raw_name);
            $existing = get_posts(array(
                'title' => $seo_title,
                'post_type' => 'sffc_company',
                'posts_per_page' => 1,
                'post_status' => 'publish'
            ));

            if (!empty($existing)) {
                return $existing[0];
            }
        } else {
            $existing = get_posts(array(
                'title' => $raw_name,
                'post_type' => 'sffc_company',
                'posts_per_page' => 1,
                'post_status' => 'publish'
            ));
            if (!empty($existing)) {
                return $existing[0];
            }
        }

        // Last resort: search for match via company website metadata
        $website = $job_data['company_url'] ?? $job_data['company_website'] ?? '';
        if (!empty($website)) {
            $domain = $this->extract_domain($website);
            if ($domain) {
                $meta_query = new WP_Query(array(
                    'post_type' => 'sffc_company',
                    'posts_per_page' => 1,
                    'meta_query' => array(
                        array(
                            'key' => '_sffc_website',
                            'value' => $domain,
                            'compare' => 'LIKE'
                        )
                    )
                ));
                if (!empty($meta_query->posts)) {
                    $post = $meta_query->posts[0];
                    wp_reset_postdata();
                    return $post;
                }
                wp_reset_postdata();
            }
        }

        return null;
    }

    /**
     * Ensure registry entry exists for a post
     */
    private function ensure_registry_entry($post, $raw_name, $normalized, array $context) {
        $registry_id = $this->get_registry_id_by_post($post->ID);
        if (!$registry_id) {
            $this->record_company_creation($post->ID, array_merge($context, array(
                'source' => $context['source_type'] ?? 'job_ingest',
                'created_via' => 'post_lookup',
                'confidence' => 0.65
            )));
            $registry_id = $this->get_registry_id_by_post($post->ID);
        }

        if ($registry_id) {
            $row = $this->get_registry_row($registry_id);
            if ($row) {
                $row['matched_via'] = 'post_lookup';
                return $row;
            }
        }

        // If we still cannot locate it, synthesize minimal record
        $canonical = class_exists('SFFC_Company_Title_Helper')
            ? SFFC_Company_Title_Helper::get_canonical_name($post)
            : $post->post_title;

        return array(
            'id' => 0,
            'company_post_id' => $post->ID,
            'canonical_name' => $canonical,
            'normalized_name' => $normalized,
            'slug' => $post->post_name,
            'matched_via' => 'post_lookup',
            'confidence_score' => 0.65
        );
    }

    /**
     * Get registry ID by linked post ID
     */
    private function get_registry_id_by_post($post_id) {
        return (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->registry_table} WHERE company_post_id = %d LIMIT 1",
                $post_id
            )
        );
    }

    /**
     * Create or update alias record when we encounter a new variation
     */
    private function record_alias_if_needed($registry_id, $alias, $source, $confidence, $strategy, $force_primary = false) {
        $normalized = $this->normalize_term($alias);
        if ('' === $normalized) {
            return null;
        }

        $existing_id = (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->alias_table} WHERE company_id = %d AND normalized_alias = %s LIMIT 1",
                $registry_id,
                $normalized
            )
        );

        $alias_type = $this->resolve_alias_type($source, $force_primary);

        $data = array(
            'company_id' => $registry_id,
            'alias' => $alias,
            'normalized_alias' => $normalized,
            'alias_type' => $alias_type,
            'source' => $source,
            'confidence_score' => floatval($confidence),
            'is_primary' => $force_primary ? 1 : 0
        );

        $format = array('%d','%s','%s','%s','%s','%f','%d');
        $alias_id = $existing_id;

        if ($existing_id) {
            $this->wpdb->update($this->alias_table, $data, array('id' => $existing_id), $format, array('%d'));
        } else {
            $this->wpdb->insert($this->alias_table, $data, $format);
            $alias_id = (int) $this->wpdb->insert_id;
        }

        return $alias_id;
    }

    private function resolve_alias_type($source, $force_primary) {
        if ($force_primary) {
            return 'canonical';
        }

        $source = strtolower((string) $source);

        switch ($source) {
            case 'news':
                return 'news';
            case 'manual':
                return 'manual';
            case 'portfolio':
                return 'portfolio';
            case 'executive':
                return 'executive';
            case 'legal':
                return 'legal';
            case 'short':
                return 'short';
            case 'job':
            case 'job_ingest':
            case 'registry_backfill':
            case 'job_import':
                return 'job';
            default:
                return 'default';
        }
    }

    /**
     * Persist resolution audit to help future tuning
     */
    private function log_resolution($source_type, array $job_data, $registry_match, $normalized, $strategy, $alias_id = null) {
        $this->wpdb->insert(
            $this->audit_table,
            array(
                'source_type' => $source_type,
                'source_reference' => isset($job_data['id']) ? (string) $job_data['id'] : null,
                'raw_company_name' => $job_data['company'] ?? '',
                'normalized_company_name' => $normalized,
                'matched_company_id' => $registry_match ? (int) ($registry_match['id'] ?? $registry_match['company_post_id'] ?? 0) : null,
                'matched_alias_id' => $alias_id,
                'confidence_score' => $registry_match ? floatval($registry_match['confidence_score'] ?? 0.0) : 0,
                'match_strategy' => $strategy,
                'notes' => isset($job_data['source_type']) ? sanitize_text_field($job_data['source_type']) : ''
            ),
            array('%s','%s','%s','%s','%d','%d','%f','%s','%s')
        );
    }

    /**
     * Normalize company names for deterministic comparisons
     */
    private function normalize_term($term) {
        $term = strtolower($term);
        $term = preg_replace('/[&\.]/', ' ', $term);
        $term = preg_replace('/[^a-z0-9\s]/', ' ', $term);
        $parts = array_filter(array_map('trim', explode(' ', $term)));
        $filtered = array();

        foreach ($parts as $part) {
            if (in_array($part, $this->generic_suffixes, true)) {
                continue;
            }
            $filtered[] = $part;
        }

        return trim(implode(' ', $filtered));
    }

    /**
     * Basic domain extractor used for registry lookups
     */
    private function extract_domain($url) {
        if (empty($url)) {
            return '';
        }

        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }

        $host = wp_parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return '';
        }

        return strtolower($host);
    }

    /**
     * Attempt to read sector taxonomy metadata
     */
    private function resolve_primary_sector($company_post_id) {
        $sectors = get_post_meta($company_post_id, '_sffc_sectors', true);
        if (empty($sectors)) {
            return '';
        }

        if (is_array($sectors)) {
            return reset($sectors) ?: '';
        }

        $parts = array_map('trim', explode(',', $sectors));
        return $parts ? reset($parts) : '';
    }
}

// Ensure singleton boots when the plugin loads
SFFC_Company_Registry::get_instance();
