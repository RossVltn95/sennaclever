<?php
/**
 * Search Index Builder
 * Builds and maintains full-text search index for all post types
 * 
 * @package SennaCareers
 * @since 10.23.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Search_Indexer {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    private $index_table_exists = null;
    
    /**
     * Post types to index
     */
    private $indexed_post_types = array(
        'sffc_job',
        'sffc_pe_news', 
        'sffc_recruiter',
        'sffc_company',
        'sffc_deal'
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
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Update index when posts are saved
        add_action('save_post', array($this, 'update_post_index'), 10, 2);
        add_action('delete_post', array($this, 'remove_from_index'));
        
        // Bulk rebuild hooks
        add_action('wp_ajax_sffc_rebuild_search_index', array($this, 'ajax_rebuild_index'));
        
        // Create tables on activation
        add_action('init', array($this, 'maybe_create_tables'));
    }
    
    /**
     * Create search index tables
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Main search index table
        $index_table = $wpdb->prefix . 'sffc_search_index';
        $sql_index = "CREATE TABLE $index_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            post_type varchar(20) NOT NULL,
            title text NOT NULL,
            content longtext NOT NULL,
            excerpt text,
            meta_data longtext,
            keywords text,
            entities text,
            relevance_score float DEFAULT 1.0,
            freshness_score float DEFAULT 1.0,
            authority_score float DEFAULT 1.0,
            total_score float DEFAULT 1.0,
            indexed_date datetime DEFAULT CURRENT_TIMESTAMP,
            modified_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY post_unique (post_id),
            KEY post_type_idx (post_type),
            KEY score_idx (total_score),
            KEY post_type_score_idx (post_type, total_score),
            KEY modified_date_idx (modified_date),
            KEY post_type_modified_idx (post_type, modified_date),
            FULLTEXT KEY content_ft (title, content, excerpt, keywords),
            FULLTEXT KEY title_ft (title),
            FULLTEXT KEY entities_ft (entities)
        ) $charset_collate;";
        
        // Search entities table
        $entities_table = $wpdb->prefix . 'sffc_search_entities';
        $sql_entities = "CREATE TABLE $entities_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            entity_name varchar(255) NOT NULL,
            entity_type varchar(50) NOT NULL,
            entity_value text,
            post_count int DEFAULT 1,
            popularity_score float DEFAULT 1.0,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY entity_unique (entity_name, entity_type),
            KEY entity_type_idx (entity_type),
            KEY popularity_idx (popularity_score)
        ) $charset_collate;";
        
        // Search analytics table
        $analytics_table = $wpdb->prefix . 'sffc_search_queries';
        $sql_analytics = "CREATE TABLE $analytics_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            query_text varchar(500) NOT NULL,
            search_mode varchar(50) NOT NULL,
            results_count int DEFAULT 0,
            user_id bigint(20) DEFAULT NULL,
            ip_address varchar(45),
            user_agent text,
            clicked_result_id bigint(20) DEFAULT NULL,
            clicked_position int DEFAULT NULL,
            session_id varchar(100),
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY query_idx (query_text(255)),
            KEY mode_idx (search_mode),
            KEY date_idx (created_date),
            KEY user_idx (user_id)
        ) $charset_collate;";
        
        // Search clicks tracking table
        $clicks_table = $wpdb->prefix . 'sffc_search_clicks';
        $sql_clicks = "CREATE TABLE $clicks_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            query_id bigint(20),
            query_text varchar(500) NOT NULL,
            clicked_result_id bigint(20) NOT NULL,
            clicked_result_type varchar(50),
            clicked_position int DEFAULT NULL,
            result_url text,
            result_title varchar(500),
            user_id bigint(20) DEFAULT NULL,
            ip_address varchar(45),
            user_agent text,
            session_id varchar(100),
            search_mode varchar(50) DEFAULT 'jobs',
            time_to_click int DEFAULT NULL,
            clicked_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY query_idx (query_text(255)),
            KEY result_idx (clicked_result_id),
            KEY position_idx (clicked_position),
            KEY date_idx (clicked_date),
            KEY user_idx (user_id),
            KEY session_idx (session_id),
            FOREIGN KEY (query_id) REFERENCES $analytics_table(id) ON DELETE SET NULL
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_index);
        dbDelta($sql_entities);
        dbDelta($sql_analytics);
        dbDelta($sql_clicks);
        
        // Set table creation flag
        update_option('sffc_search_index_tables_created', time());
    }
    
    /**
     * Maybe create tables if they don't exist
     */
    public function maybe_create_tables() {
        if (!get_option('sffc_search_index_tables_created')) {
            $this->create_tables();
        }
    }
    
    /**
     * Update post in search index
     */
    public function update_post_index($post_id, $post) {
        // Skip if not our post type
        if (!in_array($post->post_type, $this->indexed_post_types)) {
            return;
        }
        
        // Skip if not published
        if ($post->post_status !== 'publish') {
            $this->remove_from_index($post_id);
            return;
        }
        
        // Extract content and metadata
        $index_data = $this->extract_post_data($post);
        
        // Calculate scores
        $scores = $this->calculate_scores($post, $index_data);
        
        // Insert or update index
        $this->save_to_index($post_id, $index_data, $scores);
        
        // Update entities
        $this->update_entities($index_data['entities']);
    }
    
    /**
     * Extract indexable data from post
     */
    private function extract_post_data($post) {
        $data = array(
            'post_type' => $post->post_type,
            'title' => $post->post_title,
            'content' => wp_strip_all_tags($post->post_content),
            'excerpt' => $post->post_excerpt ?: wp_trim_words($post->post_content, 30),
            'meta_data' => $this->extract_meta_data($post->ID, $post->post_type),
            'keywords' => $this->extract_keywords($post),
            'entities' => $this->extract_entities($post)
        );
        
        return $data;
    }
    
    /**
     * Extract metadata based on post type
     */
    private function extract_meta_data($post_id, $post_type) {
        $meta_fields = array();
        
        switch ($post_type) {
            case 'sffc_job':
                $meta_fields = array(
                    'company' => get_post_meta($post_id, 'sffc_company_name', true),
                    'location' => get_post_meta($post_id, 'sffc_location', true),
                    'salary_min' => get_post_meta($post_id, 'sffc_salary_min', true),
                    'salary_max' => get_post_meta($post_id, 'sffc_salary_max', true),
                    'experience_level' => get_post_meta($post_id, 'sffc_experience_level', true),
                    'job_category' => get_post_meta($post_id, 'sffc_job_category', true),
                    'employment_type' => get_post_meta($post_id, 'sffc_employment_type', true)
                );
                break;
                
            case 'sffc_pe_news':
                $meta_fields = array(
                    'company' => get_post_meta($post_id, 'news_company', true),
                    'sector' => get_post_meta($post_id, 'news_sector', true),
                    'deal_type' => get_post_meta($post_id, 'news_deal_type', true),
                    'deal_value' => get_post_meta($post_id, 'deal_value', true),
                    'source' => get_post_meta($post_id, 'news_source', true)
                );
                break;

            case 'sffc_news_article':
                $meta_fields = array(
                    'company' => get_post_meta($post_id, 'news_company', true),
                    'sector' => get_post_meta($post_id, 'news_sector', true),
                    'deal_type' => get_post_meta($post_id, 'news_deal_type', true),
                    'deal_value' => get_post_meta($post_id, 'deal_value', true),
                    'source' => get_post_meta($post_id, 'news_source', true),
                    'author' => get_post_meta($post_id, 'news_author', true)
                );
                break;

            case 'sffc_recruiter':
                $meta_fields = array(
                    'company' => get_post_meta($post_id, 'company_name', true),
                    'specialization' => get_post_meta($post_id, 'specialization', true),
                    'location' => get_post_meta($post_id, 'location', true),
                    'experience_years' => get_post_meta($post_id, 'experience_years', true)
                );
                break;

            case 'sffc_company':
                $meta_fields = array(
                    'sector' => get_post_meta($post_id, 'company_sector', true),
                    'type' => get_post_meta($post_id, 'company_type', true),
                    'fund_size' => get_post_meta($post_id, 'fund_size', true),
                    'aum' => get_post_meta($post_id, 'assets_under_management', true),
                    'headquarters' => get_post_meta($post_id, 'headquarters', true)
                );
                break;

            case 'sffc_deal':
                $meta_fields = array(
                    'company' => get_post_meta($post_id, 'deal_company', true),
                    'sector' => get_post_meta($post_id, 'deal_sector', true),
                    'type' => get_post_meta($post_id, 'deal_type', true),
                    'value' => get_post_meta($post_id, 'deal_value', true),
                    'date' => get_post_meta($post_id, 'deal_date', true)
                );
                break;

            case 'sffc_salary_guide':
                $meta_fields = array(
                    'role' => get_post_meta($post_id, 'salary_role', true),
                    'region' => get_post_meta($post_id, 'salary_region', true),
                    'salary_min' => get_post_meta($post_id, 'salary_min', true),
                    'salary_max' => get_post_meta($post_id, 'salary_max', true),
                    'experience_level' => get_post_meta($post_id, 'salary_experience_level', true)
                );
                break;
        }
        
        // Remove empty values and return as JSON
        $meta_fields = array_filter($meta_fields);
        return json_encode($meta_fields);
    }
    
    /**
     * Extract keywords from post content
     */
    private function extract_keywords($post) {
        $text = $post->post_title . ' ' . $post->post_content . ' ' . $post->post_excerpt;
        $text = wp_strip_all_tags($text);
        $text = strtolower($text);
        
        // PE-specific keyword patterns
        $pe_keywords = array();
        
        // Company names pattern
        if (preg_match_all('/\b(blackstone|kkr|carlyle|apollo|tpg|bain capital|warburg pincus|silver lake|vista equity|advent|cvc|apax|permira|bc partners|cinven|eurazeo|pai partners|nordic capital|eqt|bridgepoint)\b/i', $text, $matches)) {
            $pe_keywords = array_merge($pe_keywords, $matches[0]);
        }
        
        // Job titles pattern
        if (preg_match_all('/\b(associate|analyst|vice president|vp|director|principal|partner|managing director|md|investment|private equity|pe|buyout|growth|venture|m&a|mergers|acquisitions)\b/i', $text, $matches)) {
            $pe_keywords = array_merge($pe_keywords, $matches[0]);
        }
        
        // Financial terms
        if (preg_match_all('/\b(ebitda|irr|multiple|valuation|lbo|leveraged buyout|due diligence|portfolio|fund|aum|assets under management|exit|ipo|strategic sale|dividend|recapitalization)\b/i', $text, $matches)) {
            $pe_keywords = array_merge($pe_keywords, $matches[0]);
        }
        
        // Salary patterns
        if (preg_match_all('/£?\$?([0-9,]+)k?(?:\s*-\s*£?\$?([0-9,]+)k?)?/i', $text, $matches)) {
            $pe_keywords = array_merge($pe_keywords, $matches[0]);
        }
        
        // Remove duplicates and return
        $pe_keywords = array_unique($pe_keywords);
        return implode(' ', $pe_keywords);
    }
    
    /**
     * Extract entities (companies, people, locations)
     */
    private function extract_entities($post) {
        $text = $post->post_title . ' ' . $post->post_content;
        $entities = array();
        
        // Company entities
        $companies = array(
            'Blackstone', 'KKR', 'Carlyle', 'Apollo', 'TPG', 'Bain Capital', 
            'Warburg Pincus', 'Silver Lake', 'Vista Equity', 'Advent',
            'CVC', 'Apax', 'Permira', 'BC Partners', 'Cinven', 'EQT'
        );
        
        foreach ($companies as $company) {
            if (stripos($text, $company) !== false) {
                $entities[] = array(
                    'name' => $company,
                    'type' => 'company'
                );
            }
        }
        
        // Location entities
        $locations = array(
            'London', 'New York', 'San Francisco', 'Boston', 'Chicago',
            'Los Angeles', 'Hong Kong', 'Singapore', 'Tokyo', 'Frankfurt',
            'Paris', 'Milan', 'Madrid', 'Amsterdam', 'Zurich'
        );
        
        foreach ($locations as $location) {
            if (stripos($text, $location) !== false) {
                $entities[] = array(
                    'name' => $location,
                    'type' => 'location'
                );
            }
        }
        
        return json_encode($entities);
    }
    
    /**
     * Calculate relevance, freshness, and authority scores
     */
    private function calculate_scores($post, $index_data) {
        $scores = array(
            'relevance_score' => $this->calculate_relevance_score($post, $index_data),
            'freshness_score' => $this->calculate_freshness_score($post),
            'authority_score' => $this->calculate_authority_score($post),
            'total_score' => 1.0
        );
        
        // Calculate weighted total score
        $scores['total_score'] = (
            $scores['relevance_score'] * 0.5 +
            $scores['freshness_score'] * 0.3 +
            $scores['authority_score'] * 0.2
        );
        
        return $scores;
    }
    
    /**
     * Calculate relevance score based on content quality
     */
    private function calculate_relevance_score($post, $index_data) {
        $score = 1.0;
        
        // Title length (optimal 40-60 chars)
        $title_length = strlen($post->post_title);
        if ($title_length >= 40 && $title_length <= 60) {
            $score += 0.2;
        }
        
        // Content length (longer content generally better)
        $content_length = strlen($index_data['content']);
        if ($content_length > 500) {
            $score += 0.3;
        }
        if ($content_length > 1000) {
            $score += 0.2;
        }
        
        // Has excerpt
        if (!empty($index_data['excerpt'])) {
            $score += 0.1;
        }
        
        // Has metadata
        $meta_data = json_decode($index_data['meta_data'], true);
        if (!empty($meta_data)) {
            $score += 0.2;
        }
        
        // Has entities
        $entities = json_decode($index_data['entities'], true);
        if (!empty($entities)) {
            $score += 0.2;
        }
        
        return min($score, 2.0); // Cap at 2.0
    }
    
    /**
     * Calculate freshness score (newer content ranks higher)
     */
    private function calculate_freshness_score($post) {
        $post_date = strtotime($post->post_date);
        $now = time();
        $age_days = ($now - $post_date) / DAY_IN_SECONDS;
        
        if ($age_days <= 7) {
            return 2.0; // Very fresh
        } elseif ($age_days <= 30) {
            return 1.5; // Fresh
        } elseif ($age_days <= 90) {
            return 1.2; // Moderately fresh
        } elseif ($age_days <= 365) {
            return 1.0; // Standard
        } else {
            return 0.8; // Older content
        }
    }
    
    /**
     * Calculate authority score based on source quality
     */
    private function calculate_authority_score($post) {
        $score = 1.0;
        
        // High-authority companies
        $authority_companies = array('blackstone', 'kkr', 'carlyle', 'apollo', 'tpg');
        $content = strtolower($post->post_content . ' ' . $post->post_title);
        
        foreach ($authority_companies as $company) {
            if (strpos($content, $company) !== false) {
                $score += 0.3;
                break;
            }
        }
        
        // Post type authority
        switch ($post->post_type) {
            case 'sffc_job':
                $score += 0.2; // Jobs are high priority
                break;
            case 'sffc_pe_news':
                $score += 0.1; // News is important
                break;
        }
        
        return min($score, 2.0); // Cap at 2.0
    }
    
    /**
     * Save data to search index with comprehensive error handling
     */
    private function save_to_index($post_id, $index_data, $scores) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'sffc_search_index';
        
        $data = array_merge($index_data, $scores);
        $data['post_id'] = $post_id;
        
        // Check if exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE post_id = %d",
            $post_id
        ));
        
        // FIXED: Add comprehensive error handling
        if ($wpdb->last_error) {
            error_log('SFFC Search Index Query Error: ' . $wpdb->last_error);
            return false;
        }
        
        if ($exists) {
            $result = $wpdb->update($table, $data, array('post_id' => $post_id));
        } else {
            $result = $wpdb->insert($table, $data);
        }
        
        // FIXED: Check for update/insert errors
        if ($result === false) {
            error_log('SFFC Search Index Save Failed for post ' . $post_id . ': ' . $wpdb->last_error);
            return false;
        }
        
        return true;
    }
    
    /**
     * Update entities table
     */
    private function update_entities($entities_json) {
        $entities = json_decode($entities_json, true);
        if (empty($entities)) {
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'sffc_search_entities';
        
        foreach ($entities as $entity) {
            $entity_name = $entity['name'];
            $entity_type = $entity['type'];
            
            // Check if exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE entity_name = %s AND entity_type = %s",
                $entity_name, $entity_type
            ));
            
            if ($exists) {
                // Increment post count and update popularity
                $wpdb->query($wpdb->prepare(
                    "UPDATE $table SET post_count = post_count + 1, popularity_score = post_count * 0.1 WHERE entity_name = %s AND entity_type = %s",
                    $entity_name, $entity_type
                ));
            } else {
                // Insert new entity
                $wpdb->insert($table, array(
                    'entity_name' => $entity_name,
                    'entity_type' => $entity_type,
                    'entity_value' => '',
                    'post_count' => 1,
                    'popularity_score' => 0.1
                ));
            }
        }
    }
    
    /**
     * Remove post from index
     */
    public function remove_from_index($post_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'sffc_search_index';

        if ($this->index_table_exists === null) {
            $this->index_table_exists = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table);
        }

        if (!$this->index_table_exists) {
            return;
        }

        $wpdb->delete($table, array('post_id' => $post_id));
    }
    
    /**
     * Rebuild entire search index
     */
    public function rebuild_index() {
        global $wpdb;
        
        // Clear existing index
        $table = $wpdb->prefix . 'sffc_search_index';
        $wpdb->query("TRUNCATE TABLE $table");
        
        $entities_table = $wpdb->prefix . 'sffc_search_entities';
        $wpdb->query("TRUNCATE TABLE $entities_table");
        
        // Clear clicks table as well (optional - keeps analytics clean)
        $clicks_table = $wpdb->prefix . 'sffc_search_clicks';
        $wpdb->query("TRUNCATE TABLE $clicks_table");
        
        // Get all posts to index - FIXED: Use pagination to avoid memory issues
        $batch_size = 100;
        $offset = 0;
        $indexed = 0;
        
        do {
            $posts = get_posts(array(
                'post_type' => $this->indexed_post_types,
                'post_status' => 'publish',
                'numberposts' => $batch_size,
                'offset' => $offset
            ));
            
            foreach ($posts as $post) {
                if ($this->update_post_index($post->ID, $post)) {
                    $indexed++;
                }
            }
            
            $offset += $batch_size;
            
            // Prevent memory exhaustion
            if (function_exists('wp_suspend_cache_addition')) {
                wp_suspend_cache_addition(true);
            }
            
        } while (count($posts) === $batch_size);
        
        return $indexed;
    }
    
    /**
     * AJAX handler for rebuilding index
     */
    public function ajax_rebuild_index() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        check_ajax_referer('sffc_admin_nonce', 'nonce');
        
        $indexed = $this->rebuild_index();
        
        wp_send_json_success(array(
            'message' => sprintf('Successfully indexed %d posts', $indexed),
            'indexed_count' => $indexed
        ));
    }
}

// Initialize
SFFC_Search_Indexer::get_instance();
