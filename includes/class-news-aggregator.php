<?php
/**
 * News Aggregator
 * 
 * Intelligent news aggregation with keyword filtering, deal detection,
 * and SEO potential scoring
 * 
 * @package SennaCareers
 * @subpackage SEO
 * @since 3.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_News_Aggregator {
    
    /**
     * Deal keywords for detection
     */
    const DEAL_KEYWORDS = array(
        'acquisition', 'acquire', 'acquires', 'acquired', 'acquiring',
        'merger', 'merge', 'merges', 'merged', 'merging',
        'buyout', 'buy out', 'buys out', 'bought out',
        'investment', 'invest', 'invests', 'invested', 'investing',
        'funding', 'fund', 'funds', 'funded', 'raises',
        'ipo', 'initial public offering', 'going public',
        'takeover', 'take over', 'takes over', 'taken over',
        'partnership', 'partner', 'partners', 'partnered',
        'joint venture', 'jv', 'collaboration',
        'stake', 'equity', 'shares', 'ownership',
        'deal', 'transaction', 'agreement', 'closes'
    );
    
    /**
     * High-value sectors
     */
    const HIGH_VALUE_SECTORS = array(
        'technology', 'fintech', 'biotech', 'healthcare',
        'renewable energy', 'private equity', 'venture capital',
        'investment banking', 'asset management', 'insurance',
        'real estate', 'infrastructure', 'telecommunications'
    );
    
    /**
     * Minimum deal values by category
     */
    const MIN_DEAL_VALUES = array(
        'mega_deal' => 1000000000,    // $1B+
        'large_deal' => 100000000,     // $100M+
        'mid_deal' => 50000000,        // $50M+
        'small_deal' => 10000000,      // $10M+
        'micro_deal' => 1000000        // $1M+
    );
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Processing status
     */
    private $processing = false;
    
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
        // Cron hooks for automated aggregation
        add_action('sffc_aggregate_news', array($this, 'aggregate_news_batch'));
        add_action('sffc_analyze_aggregated_news', array($this, 'analyze_news_batch'));
        add_action('sffc_select_content_for_generation', array($this, 'select_content_batch'));
        
        // Schedule if not already scheduled
        if (!wp_next_scheduled('sffc_aggregate_news')) {
            wp_schedule_event(time(), 'hourly', 'sffc_aggregate_news');
        }
        
        if (!wp_next_scheduled('sffc_analyze_aggregated_news')) {
            wp_schedule_event(time(), 'twicedaily', 'sffc_analyze_aggregated_news');
        }
    }
    
    /**
     * Aggregate news from all sources
     */
    public function aggregate_news_batch() {
        if ($this->processing) {
            return;
        }
        
        // Set lock
        $lock_key = 'sffc_aggregation_lock';
        if (get_transient($lock_key)) {
            return;
        }
        set_transient($lock_key, true, 600); // 10 minute lock
        $this->processing = true;
        
        // Get active sources
        $sources = $this->get_active_sources();
        
        foreach ($sources as $source) {
            try {
                $this->process_source($source);
                
                // Update last fetched
                $this->update_source_status($source->id, 'success');
                
                // Rate limiting
                usleep(500000); // 0.5 seconds
                
            } catch (Exception $e) {
                $this->update_source_status($source->id, 'error', $e->getMessage());
            }
        }
        
        // Clear lock
        delete_transient($lock_key);
        $this->processing = false;
        
        // Trigger analysis
        wp_schedule_single_event(time() + 300, 'sffc_analyze_aggregated_news');
    }
    
    /**
     * Get active news sources
     */
    private function get_active_sources() {
        global $wpdb;
        
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}sffc_news_sources 
             WHERE is_active = 1 
             AND (last_fetched IS NULL OR last_fetched < DATE_SUB(NOW(), INTERVAL 1 HOUR))
             ORDER BY priority DESC, last_fetched ASC 
             LIMIT 20"
        );
    }
    
    /**
     * Process single source
     */
    private function process_source($source) {
        switch ($source->source_type) {
            case 'rss':
                $articles = $this->fetch_rss_feed($source);
                break;
            case 'api':
                $articles = $this->fetch_from_api($source);
                break;
            default:
                $articles = array();
        }
        
        // Filter articles by keywords
        $filtered = $this->filter_articles($articles, $source);
        
        // Store in database
        foreach ($filtered as $article) {
            $this->store_article($article, $source);
        }
        
        return count($filtered);
    }
    
    /**
     * Fetch RSS feed
     */
    private function fetch_rss_feed($source) {
        $articles = array();
        
        // Use SimplePie
        if (!class_exists('SimplePie')) {
            require_once(ABSPATH . WPINC . '/class-simplepie.php');
        }
        
        $feed = new SimplePie();
        $feed->set_feed_url($source->feed_url);
        $feed->set_cache_location(WP_CONTENT_DIR . '/cache');
        $feed->set_cache_duration(1800); // 30 minutes
        $feed->init();
        
        if ($feed->error()) {
            throw new Exception($feed->error());
        }
        
        $items = $feed->get_items(0, 50); // Get latest 50 items
        
        foreach ($items as $item) {
            $articles[] = array(
                'title' => $item->get_title(),
                'content' => $item->get_content() ?: $item->get_description(),
                'url' => $item->get_link(),
                'author' => $item->get_author() ? $item->get_author()->get_name() : '',
                'published_date' => $item->get_date('Y-m-d H:i:s'),
                'categories' => $this->extract_categories($item)
            );
        }
        
        return $articles;
    }
    
    /**
     * Filter articles by keywords and criteria
     */
    private function filter_articles($articles, $source) {
        $filtered = array();
        $keywords = $source->keywords ? explode(',', $source->keywords) : self::DEAL_KEYWORDS;
        
        foreach ($articles as $article) {
            // Check if article contains relevant keywords
            $relevance_score = $this->calculate_relevance($article, $keywords);
            
            if ($relevance_score < 20) {
                continue; // Skip low relevance
            }
            
            // Extract deal information
            $deal_info = $this->extract_deal_info($article);
            
            // Check minimum deal value if specified
            if ($source->min_deal_value > 0 && $deal_info['value'] < $source->min_deal_value) {
                continue;
            }
            
            // Add metadata
            $article['relevance_score'] = $relevance_score;
            $article['deal_info'] = $deal_info;
            $article['region'] = $source->region;
            $article['category'] = $source->category;
            
            $filtered[] = $article;
        }
        
        return $filtered;
    }
    
    /**
     * Calculate article relevance
     */
    private function calculate_relevance($article, $keywords) {
        $score = 0;
        $text = strtolower($article['title'] . ' ' . $article['content']);
        
        // Check title (higher weight)
        foreach ($keywords as $keyword) {
            $keyword = trim(strtolower($keyword));
            if (stripos($article['title'], $keyword) !== false) {
                $score += 20;
            }
            if (stripos($text, $keyword) !== false) {
                $score += 5;
            }
        }
        
        // Check for high-value sectors
        foreach (self::HIGH_VALUE_SECTORS as $sector) {
            if (stripos($text, $sector) !== false) {
                $score += 10;
            }
        }
        
        // Check for deal indicators
        if (preg_match('/\$[\d,]+\s*(million|billion|M|B)/i', $text)) {
            $score += 15;
        }
        
        // Check for company names (capitalized words)
        if (preg_match_all('/\b[A-Z][a-z]+(?:\s+[A-Z][a-z]+)*\b/', $text, $matches)) {
            $score += min(count($matches[0]) * 2, 20);
        }
        
        return min($score, 100); // Cap at 100
    }
    
    /**
     * Extract deal information
     */
    private function extract_deal_info($article) {
        $info = array(
            'type' => '',
            'value' => 0,
            'currency' => 'USD',
            'companies' => array(),
            'sector' => ''
        );
        
        $text = $article['title'] . ' ' . $article['content'];
        
        // Extract deal type
        foreach (self::DEAL_KEYWORDS as $keyword) {
            if (stripos($text, $keyword) !== false) {
                $info['type'] = $keyword;
                break;
            }
        }
        
        // Extract deal value
        if (preg_match('/(?:[\$€£¥]|USD|EUR|GBP)\s*([\d,\.]+)\s*(million|billion|M|B)/i', $text, $matches)) {
            $value = floatval(str_replace(',', '', $matches[1]));
            $multiplier = (stripos($matches[2], 'b') !== false) ? 1000000000 : 1000000;
            $info['value'] = $value * $multiplier;
            
            // Extract currency
            if (preg_match('/([\$€£¥]|USD|EUR|GBP|CHF|JPY)/', $text, $currency_match)) {
                $currency_map = array(
                    '$' => 'USD', '€' => 'EUR', '£' => 'GBP', '¥' => 'JPY'
                );
                $info['currency'] = $currency_map[$currency_match[1]] ?? $currency_match[1];
            }
        }
        
        // Extract company names (simplified)
        $info['companies'] = $this->extract_company_names($text);
        
        // Extract sector
        foreach (self::HIGH_VALUE_SECTORS as $sector) {
            if (stripos($text, $sector) !== false) {
                $info['sector'] = $sector;
                break;
            }
        }
        
        return $info;
    }
    
    /**
     * Extract company names from text
     */
    private function extract_company_names($text) {
        $companies = array();
        
        // Look for patterns like "X acquires Y", "X invests in Y"
        $patterns = array(
            '/([A-Z][a-zA-Z\s&]+?)\s+(?:acquires?|buys?|invests?\s+in|merges?\s+with)\s+([A-Z][a-zA-Z\s&]+?)(?:\s|,|\.|$)/i',
            '/([A-Z][a-zA-Z\s&]+?)\s+(?:raises?|secures?|closes?)\s+[\$€£][\d,]+/i',
            '/([A-Z][a-zA-Z\s&]+?)\s+(?:IPO|initial\s+public\s+offering)/i'
        );
        
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                for ($i = 1; $i < count($matches); $i++) {
                    foreach ($matches[$i] as $match) {
                        $company = trim($match);
                        if (strlen($company) > 2 && strlen($company) < 50) {
                            $companies[] = $company;
                        }
                    }
                }
            }
        }
        
        // Remove common words
        $stop_words = array('The', 'Inc', 'Corp', 'Ltd', 'LLC', 'Group', 'Holdings');
        $companies = array_map(function($company) use ($stop_words) {
            foreach ($stop_words as $word) {
                $company = preg_replace('/\b' . $word . '\b\.?/i', '', $company);
            }
            return trim($company);
        }, $companies);
        
        return array_unique(array_filter($companies));
    }
    
    /**
     * Store article in database
     */
    private function store_article($article, $source) {
        global $wpdb;
        
        // Check if already exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}sffc_aggregated_news 
             WHERE url = %s",
            $article['url']
        ));
        
        if ($exists) {
            return false;
        }
        
        // Calculate initial SEO score
        $seo_score = $this->calculate_seo_potential($article);
        
        $data = array(
            'source_id' => $source->id,
            'title' => $article['title'],
            'original_content' => $article['content'],
            'summary' => $this->generate_summary($article['content']),
            'url' => $article['url'],
            'author' => $article['author'],
            'published_date' => $article['published_date'],
            'categories' => json_encode($article['categories'] ?? array()),
            'tags' => json_encode($this->extract_tags($article)),
            'deal_type' => $article['deal_info']['type'],
            'companies_involved' => json_encode($article['deal_info']['companies']),
            'deal_value' => $article['deal_info']['value'],
            'deal_currency' => $article['deal_info']['currency'],
            'sector' => $article['deal_info']['sector'],
            'region' => $article['region'],
            'keyword_matches' => json_encode($this->get_keyword_matches($article)),
            'seo_score' => $seo_score,
            'content_quality_score' => $this->calculate_quality_score($article),
            'status' => 'new',
            'created_at' => current_time('mysql')
        );
        
        return $wpdb->insert(
            $wpdb->prefix . 'sffc_aggregated_news',
            $data
        );
    }
    
    /**
     * Calculate SEO potential score
     */
    private function calculate_seo_potential($article) {
        $score = 0;
        
        // Title length (optimal: 50-60 characters)
        $title_length = strlen($article['title']);
        if ($title_length >= 50 && $title_length <= 60) {
            $score += 20;
        } elseif ($title_length >= 40 && $title_length <= 70) {
            $score += 10;
        }
        
        // Content length
        $word_count = str_word_count($article['content']);
        if ($word_count >= 300) $score += 10;
        if ($word_count >= 600) $score += 10;
        if ($word_count >= 1000) $score += 10;
        
        // Deal value (higher value = more interest)
        if ($article['deal_info']['value'] > self::MIN_DEAL_VALUES['mega_deal']) {
            $score += 30;
        } elseif ($article['deal_info']['value'] > self::MIN_DEAL_VALUES['large_deal']) {
            $score += 20;
        } elseif ($article['deal_info']['value'] > self::MIN_DEAL_VALUES['mid_deal']) {
            $score += 10;
        }
        
        // Company names (brand searches)
        $score += min(count($article['deal_info']['companies']) * 10, 30);
        
        // Relevance score
        $score += intval($article['relevance_score'] / 2);
        
        return min($score, 100);
    }
    
    /**
     * Analyze aggregated news for content generation
     */
    public function analyze_news_batch() {
        global $wpdb;
        
        // Get unanalyzed news
        $articles = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}sffc_aggregated_news 
             WHERE status = 'new' 
             ORDER BY seo_score DESC, published_date DESC 
             LIMIT 50"
        );
        
        foreach ($articles as $article) {
            // Perform deeper analysis
            $analysis = $this->deep_analyze_article($article);
            
            // Update with analysis results
            $wpdb->update(
                $wpdb->prefix . 'sffc_aggregated_news',
                array(
                    'uniqueness_score' => $analysis['uniqueness'],
                    'seo_score' => $analysis['seo_score'],
                    'content_quality_score' => $analysis['quality'],
                    'status' => $analysis['suitable'] ? 'analyzed' : 'rejected',
                    'rejection_reason' => $analysis['rejection_reason'] ?? null,
                    'processed_at' => current_time('mysql')
                ),
                array('id' => $article->id)
            );
        }
        
        // Select best candidates for content generation
        wp_schedule_single_event(time() + 300, 'sffc_select_content_for_generation');
    }
    
    /**
     * Deep analysis of article
     */
    private function deep_analyze_article($article) {
        $analysis = array(
            'uniqueness' => 75, // Simplified for now
            'quality' => 0,
            'seo_score' => $article->seo_score,
            'suitable' => true,
            'rejection_reason' => null
        );
        
        // Quality checks
        $content_length = strlen($article->original_content);
        
        if ($content_length < 200) {
            $analysis['suitable'] = false;
            $analysis['rejection_reason'] = 'Content too short';
            return $analysis;
        }
        
        // Calculate quality score
        $quality = 50; // Base score
        
        // Has companies
        if (!empty($article->companies_involved)) {
            $quality += 20;
        }
        
        // Has deal value
        if ($article->deal_value > 0) {
            $quality += 20;
        }
        
        // Recent (within 48 hours)
        $age_hours = (time() - strtotime($article->published_date)) / 3600;
        if ($age_hours < 48) {
            $quality += 10;
        }
        
        $analysis['quality'] = min($quality, 100);
        
        // Check for duplicate topics
        if ($this->is_duplicate_topic($article)) {
            $analysis['suitable'] = false;
            $analysis['rejection_reason'] = 'Duplicate topic recently covered';
        }
        
        return $analysis;
    }
    
    /**
     * Check if topic was recently covered
     */
    private function is_duplicate_topic($article) {
        global $wpdb;
        
        $companies = json_decode($article->companies_involved, true) ?: array();
        
        if (empty($companies)) {
            return false;
        }
        
        // Check if we've written about these companies recently
        foreach ($companies as $company) {
            $recent = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}sffc_seo_articles 
                 WHERE content LIKE %s 
                 AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)",
                '%' . $company . '%'
            ));
            
            if ($recent > 0) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Select content for generation
     */
    public function select_content_batch() {
        global $wpdb;
        
        // Get top candidates
        $candidates = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}sffc_aggregated_news 
             WHERE status = 'analyzed' 
             AND deal_value > 10000000 
             ORDER BY seo_score DESC, content_quality_score DESC 
             LIMIT 10"
        );
        
        // Group related articles
        $groups = $this->group_related_articles($candidates);
        
        foreach ($groups as $group) {
            if (count($group) >= 2) {
                // Multiple sources on same topic - perfect for generation
                $this->queue_for_generation($group, 'multi_source');
            } else {
                // Single source - check if significant enough
                $article = $group[0];
                if ($article->seo_score >= 70 && $article->deal_value > 50000000) {
                    $this->queue_for_generation($group, 'single_source');
                }
            }
        }
    }
    
    /**
     * Group related articles
     */
    private function group_related_articles($articles) {
        $groups = array();
        $used = array();
        
        foreach ($articles as $article) {
            if (in_array($article->id, $used)) {
                continue;
            }
            
            $group = array($article);
            $used[] = $article->id;
            
            // Find related articles
            foreach ($articles as $other) {
                if ($other->id === $article->id || in_array($other->id, $used)) {
                    continue;
                }
                
                if ($this->are_articles_related($article, $other)) {
                    $group[] = $other;
                    $used[] = $other->id;
                }
            }
            
            $groups[] = $group;
        }
        
        return $groups;
    }
    
    /**
     * Check if articles are related
     */
    private function are_articles_related($article1, $article2) {
        // Check company overlap
        $companies1 = json_decode($article1->companies_involved, true) ?: array();
        $companies2 = json_decode($article2->companies_involved, true) ?: array();
        
        if (array_intersect($companies1, $companies2)) {
            return true;
        }
        
        // Check title similarity
        similar_text($article1->title, $article2->title, $percent);
        if ($percent > 60) {
            return true;
        }
        
        // Check same deal type and sector
        if ($article1->deal_type === $article2->deal_type && 
            $article1->sector === $article2->sector) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Queue articles for content generation
     */
    private function queue_for_generation($articles, $type) {
        global $wpdb;
        
        $source_ids = array_map(function($a) { return $a->id; }, $articles);
        
        // Determine target audience based on deal size
        $max_value = max(array_map(function($a) { return $a->deal_value; }, $articles));
        
        if ($max_value > 1000000000) {
            $target_audience = 'institutional_investors';
            $content_length = 2000;
        } elseif ($max_value > 100000000) {
            $target_audience = 'finance_professionals';
            $content_length = 1500;
        } else {
            $target_audience = 'retail_investors';
            $content_length = 1200;
        }
        
        // Create queue item
        $queue_data = array(
            'queue_type' => 'generation',
            'priority' => $this->calculate_priority($articles),
            'status' => 'pending',
            'task_data' => json_encode(array(
                'type' => $type,
                'articles' => $source_ids,
                'focus' => $articles[0]->deal_type,
                'companies' => json_decode($articles[0]->companies_involved, true)
            )),
            'source_ids' => implode(',', $source_ids),
            'target_length' => $content_length,
            'target_audience' => $target_audience,
            'target_location' => $articles[0]->region ?: 'united_kingdom',
            'created_at' => current_time('mysql')
        );
        
        $wpdb->insert(
            $wpdb->prefix . 'sffc_content_queue',
            $queue_data
        );
        
        // Mark articles as selected
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}sffc_aggregated_news 
             SET status = 'selected' 
             WHERE id IN (" . implode(',', array_fill(0, count($source_ids), '%d')) . ")",
            $source_ids
        ));
    }
    
    /**
     * Calculate priority for queue
     */
    private function calculate_priority($articles) {
        $priority = 5; // Base priority
        
        $max_value = max(array_map(function($a) { return $a->deal_value; }, $articles));
        $max_seo = max(array_map(function($a) { return $a->seo_score; }, $articles));
        
        if ($max_value > 1000000000) $priority += 3;
        elseif ($max_value > 100000000) $priority += 2;
        elseif ($max_value > 50000000) $priority += 1;
        
        if ($max_seo > 80) $priority += 2;
        elseif ($max_seo > 60) $priority += 1;
        
        if (count($articles) > 2) $priority += 1;
        
        return min($priority, 10);
    }
    
    /**
     * Helper methods
     */
    
    private function update_source_status($source_id, $status, $error = null) {
        global $wpdb;
        
        $data = array(
            'last_fetched' => current_time('mysql')
        );
        
        if ($status === 'success') {
            $data['success_count'] = array('success_count + 1');
        } else {
            $data['error_count'] = array('error_count + 1');
            $data['last_error'] = $error;
        }
        
        $wpdb->update(
            $wpdb->prefix . 'sffc_news_sources',
            $data,
            array('id' => $source_id)
        );
    }
    
    private function extract_categories($item) {
        $categories = array();
        
        if ($item->get_categories()) {
            foreach ($item->get_categories() as $category) {
                $categories[] = $category->get_label();
            }
        }
        
        return $categories;
    }
    
    private function generate_summary($content, $length = 200) {
        $content = strip_tags($content);
        $content = preg_replace('/\s+/', ' ', $content);
        
        if (strlen($content) <= $length) {
            return $content;
        }
        
        return substr($content, 0, $length) . '...';
    }
    
    private function extract_tags($article) {
        $tags = array();
        
        // Add deal type
        if (!empty($article['deal_info']['type'])) {
            $tags[] = $article['deal_info']['type'];
        }
        
        // Add sector
        if (!empty($article['deal_info']['sector'])) {
            $tags[] = $article['deal_info']['sector'];
        }
        
        // Add value range
        if ($article['deal_info']['value'] > 0) {
            if ($article['deal_info']['value'] > 1000000000) {
                $tags[] = 'mega-deal';
            } elseif ($article['deal_info']['value'] > 100000000) {
                $tags[] = 'large-deal';
            }
        }
        
        return $tags;
    }
    
    private function get_keyword_matches($article) {
        $matches = array();
        $text = strtolower($article['title'] . ' ' . $article['content']);
        
        foreach (self::DEAL_KEYWORDS as $keyword) {
            if (stripos($text, $keyword) !== false) {
                $matches[] = $keyword;
            }
        }
        
        return array_unique($matches);
    }
    
    private function calculate_quality_score($article) {
        $score = 50; // Base score
        
        // Has all key information
        if (!empty($article['title'])) $score += 10;
        if (!empty($article['author'])) $score += 5;
        if (!empty($article['published_date'])) $score += 5;
        if (str_word_count($article['content']) > 200) $score += 10;
        if (!empty($article['deal_info']['companies'])) $score += 10;
        if ($article['deal_info']['value'] > 0) $score += 10;
        
        return min($score, 100);
    }
    
    /**
     * Manual trigger for aggregation
     */
    public function manual_aggregate($source_id = null) {
        global $wpdb;
        
        if ($source_id) {
            $source = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}sffc_news_sources WHERE id = %d",
                $source_id
            ));
            
            if ($source) {
                return $this->process_source($source);
            }
        } else {
            $this->aggregate_news_batch();
        }
        
        return true;
    }
    
    /**
     * Get aggregation statistics
     */
    public function get_statistics() {
        global $wpdb;
        
        return array(
            'total_sources' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}sffc_news_sources"
            ),
            'active_sources' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}sffc_news_sources WHERE is_active = 1"
            ),
            'total_articles' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}sffc_aggregated_news"
            ),
            'analyzed_articles' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}sffc_aggregated_news WHERE status = 'analyzed'"
            ),
            'selected_articles' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}sffc_aggregated_news WHERE status = 'selected'"
            ),
            'published_articles' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}sffc_aggregated_news WHERE status = 'published'"
            ),
            'queued_items' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}sffc_content_queue WHERE status = 'pending'"
            )
        );
    }
}

// Initialize
SFFC_News_Aggregator::get_instance();
?>