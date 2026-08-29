<?php
/**
 * Search Query Processor
 * Handles search queries and returns ranked results
 * 
 * @package SennaCareers
 * @since 10.23.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Search_Query {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Current search parameters
     */
    private $query_text = '';
    private $search_mode = 'jobs';
    private $page = 1;
    private $per_page = 20;
    private $filters = array();
    
    /**
     * Search performance tracking
     */
    private $search_start_time = 0;
    private $search_results = array();
    
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
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // AJAX search handlers
        add_action('wp_ajax_sffc_pe_search', array($this, 'ajax_search'));
        add_action('wp_ajax_nopriv_sffc_pe_search', array($this, 'ajax_search'));
        
        // Autocomplete handler
        add_action('wp_ajax_sffc_autocomplete', array($this, 'ajax_autocomplete'));
        add_action('wp_ajax_nopriv_sffc_autocomplete', array($this, 'ajax_autocomplete'));
    }
    
    /**
     * Main search function
     */
    public function search($query_text, $mode = 'jobs', $page = 1, $filters = array()) {
        $this->search_start_time = microtime(true);
        
        // Set search parameters
        $this->query_text = sanitize_text_field($query_text);
        $this->search_mode = sanitize_text_field($mode);
        $this->page = max(1, intval($page));
        $this->filters = $this->sanitize_filters($filters);
        
        // Process the query
        $processed_query = $this->process_query($this->query_text);
        
        // Execute search
        $results = $this->execute_search($processed_query);

        // Store results so analytics logging has accurate counts
        $this->search_results = $results['results'] ?? array();

        // Log search query for analytics
        $this->log_search_query();

        return $results;
    }
    
    /**
     * Process and enhance the search query
     */
    private function process_query($query_text) {
        $processed = array(
            'original' => $query_text,
            'cleaned' => $this->clean_query($query_text),
            'entities' => $this->extract_query_entities($query_text),
            'keywords' => $this->extract_query_keywords($query_text),
            'operators' => $this->parse_search_operators($query_text),
            'synonyms' => $this->expand_synonyms($query_text)
        );
        
        return $processed;
    }
    
    /**
     * Clean and normalize query text
     */
    private function clean_query($query_text) {
        // Remove extra whitespace
        $cleaned = preg_replace('/\s+/', ' ', trim($query_text));
        
        // Handle quotes for exact phrases
        $cleaned = str_replace(array('"', '"', '"'), '"', $cleaned);
        
        // Remove special characters except operators
        $cleaned = preg_replace('/[^\w\s\-"£$+()]/', ' ', $cleaned);
        
        return $cleaned;
    }
    
    /**
     * Extract entities from query (companies, locations, salaries)
     */
    private function extract_query_entities($query_text) {
        $entities = array();
        $text = strtolower($query_text);
        
        // Company names
        $companies = array(
            'blackstone', 'kkr', 'carlyle', 'apollo', 'tpg', 'bain capital',
            'warburg pincus', 'silver lake', 'vista equity', 'advent',
            'cvc', 'apax', 'permira', 'bc partners', 'cinven', 'eqt'
        );
        
        foreach ($companies as $company) {
            if (strpos($text, $company) !== false) {
                $entities['companies'][] = $company;
            }
        }
        
        // Location patterns
        $locations = array(
            'london', 'new york', 'nyc', 'san francisco', 'boston', 'chicago',
            'los angeles', 'hong kong', 'singapore', 'tokyo', 'frankfurt',
            'paris', 'milan', 'madrid', 'amsterdam', 'zurich', 'remote'
        );
        
        foreach ($locations as $location) {
            if (strpos($text, $location) !== false) {
                $entities['locations'][] = $location;
            }
        }
        
        // Salary patterns
        if (preg_match_all('/£?(\d{1,3}(?:,\d{3})*|\d+)k?(?:\s*-\s*£?(\d{1,3}(?:,\d{3})*|\d+)k?)?/i', $query_text, $matches)) {
            foreach ($matches[0] as $salary_match) {
                $entities['salaries'][] = $salary_match;
            }
        }
        
        // Experience levels
        $experience_patterns = array(
            'intern', 'internship', 'graduate', 'entry level', 'junior',
            'associate', 'senior', 'principal', 'director', 'vp', 'vice president',
            'managing director', 'md', 'partner'
        );
        
        foreach ($experience_patterns as $level) {
            if (strpos($text, $level) !== false) {
                $entities['experience'][] = $level;
            }
        }
        
        return $entities;
    }
    
    /**
     * Extract relevant keywords
     */
    private function extract_query_keywords($query_text) {
        $keywords = array();
        $text = strtolower($query_text);
        
        // PE-specific terms
        $pe_terms = array(
            'private equity', 'pe', 'buyout', 'growth', 'venture',
            'm&a', 'mergers', 'acquisitions', 'lbo', 'leveraged buyout',
            'due diligence', 'portfolio', 'fund', 'aum', 'irr', 'ebitda'
        );
        
        foreach ($pe_terms as $term) {
            if (strpos($text, $term) !== false) {
                $keywords[] = $term;
            }
        }
        
        // Job-specific terms
        if ($this->search_mode === 'jobs') {
            $job_terms = array(
                'analyst', 'associate', 'vice president', 'director',
                'principal', 'partner', 'investment', 'finance', 'financial'
            );
            
            foreach ($job_terms as $term) {
                if (strpos($text, $term) !== false) {
                    $keywords[] = $term;
                }
            }
        }
        
        return array_unique($keywords);
    }
    
    /**
     * Parse search operators (+, -, "", etc.)
     */
    private function parse_search_operators($query_text) {
        $operators = array(
            'required' => array(),
            'excluded' => array(),
            'exact_phrases' => array(),
            'optional' => array()
        );
        
        // Extract quoted phrases
        if (preg_match_all('/"([^"]+)"/', $query_text, $matches)) {
            $operators['exact_phrases'] = $matches[1];
            $query_text = preg_replace('/"[^"]+"/', '', $query_text);
        }
        
        // Extract required terms (+term)
        if (preg_match_all('/\+(\w+)/', $query_text, $matches)) {
            $operators['required'] = $matches[1];
            $query_text = preg_replace('/\+\w+/', '', $query_text);
        }
        
        // Extract excluded terms (-term)
        if (preg_match_all('/-(\w+)/', $query_text, $matches)) {
            $operators['excluded'] = $matches[1];
            $query_text = preg_replace('/-\w+/', '', $query_text);
        }
        
        // Remaining terms are optional
        $remaining_words = array_filter(explode(' ', trim($query_text)));
        $operators['optional'] = $remaining_words;
        
        return $operators;
    }
    
    /**
     * Expand synonyms for better matching
     */
    private function expand_synonyms($query_text) {
        $synonyms = array(
            'pe' => array('private equity'),
            'vc' => array('venture capital'),
            'ma' => array('mergers and acquisitions', 'm&a'),
            'lbo' => array('leveraged buyout'),
            'vp' => array('vice president'),
            'md' => array('managing director'),
            'aum' => array('assets under management'),
            'london' => array('uk', 'united kingdom', 'england'),
            'new york' => array('nyc', 'ny'),
            'san francisco' => array('sf', 'silicon valley')
        );
        
        $expanded = array($query_text);
        $text = strtolower($query_text);
        
        foreach ($synonyms as $term => $alternatives) {
            if (strpos($text, $term) !== false) {
                foreach ($alternatives as $alternative) {
                    $expanded[] = str_ireplace($term, $alternative, $query_text);
                }
            }
        }
        
        return array_unique($expanded);
    }
    
    /**
     * Execute the actual database search
     */
    private function execute_search($processed_query) {
        global $wpdb;
        
        $index_table = $wpdb->prefix . 'sffc_search_index';
        $entities_table = $wpdb->prefix . 'sffc_search_entities';
        
        // Build the SQL query
        $sql_parts = $this->build_search_sql($processed_query);
        
        // Get total count
        $count_sql = "SELECT COUNT(DISTINCT si.post_id) FROM {$index_table} si " . $sql_parts['joins'] . " WHERE " . $sql_parts['where'];
        $total_results = $wpdb->get_var($count_sql);
        
        // Get paginated results
        $offset = ($this->page - 1) * $this->per_page;
        $results_sql = "SELECT DISTINCT si.*, " . $sql_parts['score_calculation'] . " as final_score 
                       FROM {$index_table} si " . 
                       $sql_parts['joins'] . " 
                       WHERE " . $sql_parts['where'] . " 
                       ORDER BY final_score DESC, si.modified_date DESC 
                       LIMIT {$this->per_page} OFFSET {$offset}";
        
        $raw_results = $wpdb->get_results($results_sql);

        if (($raw_results === null || $raw_results === false || empty($raw_results)) && intval($total_results) > 0) {
            if (!empty($wpdb->last_error)) {
                error_log('SFFC Search: Primary query failed - ' . $wpdb->last_error);
            }

            $fallback_sql = "SELECT DISTINCT si.*, COALESCE(si.total_score, 0) as final_score 
                       FROM {$index_table} si " .
                       $sql_parts['joins'] . " 
                       WHERE " . $sql_parts['where'] . " 
                       ORDER BY si.modified_date DESC 
                       LIMIT {$this->per_page} OFFSET {$offset}";

            $fallback_results = $wpdb->get_results($fallback_sql);
            if (!empty($fallback_results)) {
                $raw_results = $fallback_results;
            } elseif ($raw_results === null || $raw_results === false) {
                $raw_results = array();
            }
        }

        if (!is_array($raw_results)) {
            $raw_results = array();
        }

        // Process and format results
        $formatted_results = array();
        foreach ($raw_results as $result) {
            $formatted_results[] = $this->format_search_result($result);
        }
        
        $search_time = microtime(true) - $this->search_start_time;
        
        return array(
            'query' => $this->query_text,
            'mode' => $this->search_mode,
            'total' => intval($total_results),
            'results' => $formatted_results,
            'page' => $this->page,
            'per_page' => $this->per_page,
            'total_pages' => ceil($total_results / $this->per_page),
            'search_time' => round($search_time, 2),
            'filters_applied' => $this->filters
        );
    }
    
    /**
     * Build SQL query components
     */
    private function build_search_sql($processed_query) {
        global $wpdb;
        
        $where_conditions = array();
        $joins = '';
        $score_parts = array();
        
        // Post type filter based on search mode
        $post_types = $this->get_post_types_for_mode($this->search_mode);
        $post_type_placeholders = implode(',', array_fill(0, count($post_types), '%s'));
        $where_conditions[] = $wpdb->prepare("si.post_type IN ($post_type_placeholders)", $post_types);
        
        // Full-text search conditions
        if (!empty($processed_query['cleaned'])) {
            $search_term = esc_sql($processed_query['cleaned']);
            
            // Title match (highest weight)
            $score_parts[] = "CASE WHEN MATCH(si.title) AGAINST('$search_term' IN NATURAL LANGUAGE MODE) THEN 3.0 ELSE 0 END";
            
            // Content match (medium weight)
            $score_parts[] = "CASE WHEN MATCH(si.content, si.excerpt) AGAINST('$search_term' IN NATURAL LANGUAGE MODE) THEN 2.0 ELSE 0 END";
            
            // Keywords match (low weight)
            $score_parts[] = "CASE WHEN MATCH(si.keywords) AGAINST('$search_term' IN NATURAL LANGUAGE MODE) THEN 1.0 ELSE 0 END";
            
            // Boolean search condition - use FULLTEXT with LIKE fallback
            $where_conditions[] = "(MATCH(si.title, si.content, si.excerpt, si.keywords) AGAINST('$search_term' IN NATURAL LANGUAGE MODE) 
                                 OR si.title LIKE '%$search_term%' 
                                 OR si.content LIKE '%$search_term%' 
                                 OR si.excerpt LIKE '%$search_term%'
                                 OR si.keywords LIKE '%$search_term%')";
        }
        
        // Entity-based filtering
        if (!empty($processed_query['entities']['companies'])) {
            $company_terms = implode(' ', $processed_query['entities']['companies']);
            $company_term = esc_sql($company_terms);
            $score_parts[] = "CASE WHEN si.entities LIKE '%$company_term%' THEN 1.5 ELSE 0 END";
        }
        
        // Apply filters
        $filter_conditions = $this->build_filter_conditions();
        $where_conditions = array_merge($where_conditions, $filter_conditions);
        
        // Calculate final score
        if (!empty($score_parts)) {
            $base_score = implode(' + ', $score_parts);
            $base_score_expr = 'COALESCE((' . $base_score . '), 0)';
        } else {
            $base_score_expr = '0';
        }

        $score_calculation = '(' . $base_score_expr . ' + COALESCE(si.relevance_score, 0) + COALESCE(si.freshness_score, 0) + COALESCE(si.authority_score, 0))';
        
        return array(
            'joins' => $joins,
            'where' => implode(' AND ', $where_conditions),
            'score_calculation' => $score_calculation
        );
    }
    
    /**
     * Get post types for search mode
     */
    private function get_post_types_for_mode($mode) {
        $post_type_map = array(
            'jobs' => array('sffc_job'),
            'insights' => array('sffc_pe_news', 'sffc_news_article', 'sffc_deal'),
            'news' => array('sffc_pe_news', 'sffc_news_article', 'sffc_deal'),
            'recruiters' => array('sffc_recruiter'),
            'companies' => array('sffc_company'),
            'templates' => array('sffc_salary_guide'),
            'all' => array('sffc_job', 'sffc_pe_news', 'sffc_news_article', 'sffc_recruiter', 'sffc_company', 'sffc_deal', 'sffc_salary_guide')
        );

        return isset($post_type_map[$mode]) ? $post_type_map[$mode] : $post_type_map['all'];
    }
    
    /**
     * Build filter conditions with proper SQL injection protection
     */
    private function build_filter_conditions() {
        global $wpdb;
        $conditions = array();
        
        // Location filter - FIXED: Proper SQL escaping
        if (!empty($this->filters['location'])) {
            $location = sanitize_text_field($this->filters['location']);
            $conditions[] = $wpdb->prepare("si.meta_data LIKE %s", '%"location":"' . $wpdb->esc_like($location) . '%"%');
        }
        
        // Salary range filter - FIXED: Use prepared statements
        if (!empty($this->filters['salary_min'])) {
            $salary_min = intval($this->filters['salary_min']);
            $conditions[] = $wpdb->prepare("CAST(JSON_UNQUOTE(JSON_EXTRACT(si.meta_data, '$.salary_min')) AS UNSIGNED) >= %d", $salary_min);
        }
        
        if (!empty($this->filters['salary_max'])) {
            $salary_max = intval($this->filters['salary_max']);
            $conditions[] = $wpdb->prepare("CAST(JSON_UNQUOTE(JSON_EXTRACT(si.meta_data, '$.salary_max')) AS UNSIGNED) <= %d", $salary_max);
        }
        
        // Date range filter - Already properly prepared
        if (!empty($this->filters['date_from'])) {
            $date_from = sanitize_text_field($this->filters['date_from']);
            $conditions[] = $wpdb->prepare("si.indexed_date >= %s", $date_from);
        }
        
        // Experience level filter - FIXED: Proper SQL escaping
        if (!empty($this->filters['experience'])) {
            $experience = sanitize_text_field($this->filters['experience']);
            $conditions[] = $wpdb->prepare("si.meta_data LIKE %s", '%"experience_level":"' . $wpdb->esc_like($experience) . '%"%');
        }
        
        return $conditions;
    }
    
    /**
     * Format search result for output
     */
    private function format_search_result($result) {
        $meta_data = json_decode($result->meta_data, true) ?: array();
        $entities = json_decode($result->entities, true) ?: array();
        
        // Generate excerpt with highlighting
        $excerpt = $this->generate_highlighted_excerpt($result->content, $this->query_text);
        
        return array(
            'id' => $result->post_id,
            'type' => $result->post_type,
            'title' => $this->highlight_matches($result->title, $this->query_text),
            'content' => $result->content,
            'excerpt' => $excerpt,
            'url' => get_permalink($result->post_id),
            'meta' => $meta_data,
            'entities' => $entities,
            'score' => floatval($result->final_score),
            'date' => $result->modified_date
        );
    }
    
    /**
     * Generate highlighted excerpt
     */
    private function generate_highlighted_excerpt($content, $query) {
        $content = wp_strip_all_tags($content);
        
        // Find the best snippet around query matches
        $query_words = explode(' ', strtolower($query));
        $content_lower = strtolower($content);
        
        $best_position = 0;
        $max_matches = 0;
        
        // Find position with most query word matches
        for ($i = 0; $i < strlen($content) - 200; $i += 50) {
            $snippet = substr($content_lower, $i, 200);
            $matches = 0;
            
            foreach ($query_words as $word) {
                if (strlen($word) > 2 && strpos($snippet, $word) !== false) {
                    $matches++;
                }
            }
            
            if ($matches > $max_matches) {
                $max_matches = $matches;
                $best_position = $i;
            }
        }
        
        // Extract snippet
        $snippet = substr($content, $best_position, 160);
        if ($best_position > 0) {
            $snippet = '...' . $snippet;
        }
        if (strlen($content) > $best_position + 160) {
            $snippet .= '...';
        }
        
        return $this->highlight_matches($snippet, $query);
    }
    
    /**
     * Highlight query matches in text
     */
    private function highlight_matches($text, $query) {
        $query_words = explode(' ', $query);
        
        foreach ($query_words as $word) {
            $word = trim($word);
            if (strlen($word) > 2) {
                $text = preg_replace(
                    '/\b(' . preg_quote($word, '/') . ')\b/i',
                    '<strong class="sffc-highlight">$1</strong>',
                    $text
                );
            }
        }
        
        return $text;
    }
    
    /**
     * Sanitize filters array
     */
    private function sanitize_filters($filters) {
        $sanitized = array();
        
        if (isset($filters['location'])) {
            $sanitized['location'] = sanitize_text_field($filters['location']);
        }
        
        if (isset($filters['salary_min'])) {
            $sanitized['salary_min'] = intval($filters['salary_min']);
        }
        
        if (isset($filters['salary_max'])) {
            $sanitized['salary_max'] = intval($filters['salary_max']);
        }
        
        if (isset($filters['experience'])) {
            $sanitized['experience'] = sanitize_text_field($filters['experience']);
        }
        
        if (isset($filters['date_from'])) {
            $sanitized['date_from'] = sanitize_text_field($filters['date_from']);
        }
        
        return $sanitized;
    }
    
    /**
     * Get autocomplete suggestions
     */
    public function get_autocomplete_suggestions($query, $mode = 'jobs', $limit = 10) {
        global $wpdb;
        
        $suggestions = array();
        $query = strtolower(trim($query));
        
        if (strlen($query) < 2) {
            return $suggestions;
        }
        
        // Get entity suggestions
        $entities_table = $wpdb->prefix . 'sffc_search_entities';
        $entity_results = $wpdb->get_results($wpdb->prepare(
            "SELECT entity_name, entity_type, popularity_score 
             FROM $entities_table 
             WHERE LOWER(entity_name) LIKE %s 
             ORDER BY popularity_score DESC 
             LIMIT %d",
            '%' . esc_sql($query) . '%',
            $limit
        ));
        
        foreach ($entity_results as $entity) {
            $suggestions[] = array(
                'text' => $entity->entity_name,
                'type' => $entity->entity_type,
                'score' => $entity->popularity_score
            );
        }
        
        // Get popular search queries
        $queries_table = $wpdb->prefix . 'sffc_search_queries';
        $query_results = $wpdb->get_results($wpdb->prepare(
            "SELECT query_text, COUNT(*) as frequency 
             FROM $queries_table 
             WHERE LOWER(query_text) LIKE %s 
               AND search_mode = %s 
               AND created_date > DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY query_text 
             ORDER BY frequency DESC 
             LIMIT %d",
            '%' . esc_sql($query) . '%',
            $mode,
            5
        ));
        
        foreach ($query_results as $popular_query) {
            $suggestions[] = array(
                'text' => $popular_query->query_text,
                'type' => 'popular_search',
                'score' => $popular_query->frequency
            );
        }
        
        // Sort by relevance and limit
        usort($suggestions, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        return array_slice($suggestions, 0, $limit);
    }
    
    /**
     * Log search query for analytics
     */
    private function log_search_query() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'sffc_search_queries';
        
        $wpdb->insert($table, array(
            'query_text' => $this->query_text,
            'search_mode' => $this->search_mode,
            'results_count' => count($this->search_results),
            'user_id' => get_current_user_id(),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            'session_id' => session_id() ?: wp_generate_uuid4()
        ));
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_headers = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR');
        
        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                return trim($ip);
            }
        }
        
        return '';
    }
    
    /**
     * AJAX search handler with proper security
     */
    public function ajax_search() {
        // FIXED: Proper nonce verification - always required
        check_ajax_referer('sffc_search_nonce', 'nonce');
        
        $query = sanitize_text_field($_POST['q'] ?? '');
        $mode = sanitize_text_field($_POST['mode'] ?? 'jobs');
        $page = intval($_POST['page'] ?? 1);
        $filters = $_POST['filters'] ?? array();
        
        if (empty($query)) {
            wp_send_json_error('Empty query');
            return;
        }
        
        $results = $this->search($query, $mode, $page, $filters);
        wp_send_json_success($results);
    }
    
    /**
     * AJAX autocomplete handler with proper security
     */
    public function ajax_autocomplete() {
        // FIXED: Add nonce verification for autocomplete
        check_ajax_referer('sffc_search_nonce', 'nonce');
        
        $query = sanitize_text_field($_GET['q'] ?? '');
        $mode = sanitize_text_field($_GET['mode'] ?? 'jobs');
        
        if (empty($query)) {
            wp_send_json_success(array());
            return;
        }
        
        $suggestions = $this->get_autocomplete_suggestions($query, $mode);
        wp_send_json_success($suggestions);
    }
}

// Initialize
SFFC_Search_Query::get_instance();
