<?php
/**
 * Topic Idea Crawler
 *
 * Extracts topic ideas from competitor sitemaps and RSS feeds
 * Only extracts URLs/titles for inspiration - no content copying
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Topic_Idea_Crawler
{
    private static $instance = null;

    // Feed sources for topic ideas
    private $feed_sources = array(
        array(
            'url'  => 'https://corporatefinanceinstitute.com/resource-sitemap5.xml',
            'type' => 'sitemap',
            'name' => 'CFI Resources',
        ),
        array(
            'url'  => 'https://www.adventuresincre.com/feed/',
            'type' => 'rss',
            'name' => 'Adventures in CRE',
        ),
        array(
            'url'  => 'https://www.peakframeworks.com/blog-posts-sitemap.xml',
            'type' => 'sitemap',
            'name' => 'Peak Frameworks',
        ),
        array(
            'url'  => 'https://www.apolloacademy.com/post-sitemap2.xml',
            'type' => 'sitemap',
            'name' => 'Apollo Academy Posts',
        ),
        array(
            'url'  => 'https://www.apolloacademy.com/sfwd-lessons-sitemap.xml',
            'type' => 'sitemap',
            'name' => 'Apollo Academy Lessons',
        ),
        array(
            'url'  => 'https://www.gsinvestmentuniversity.com/post-sitemap.xml',
            'type' => 'sitemap',
            'name' => 'GS Investment University Posts',
        ),
        array(
            'url'  => 'https://www.gsinvestmentuniversity.com/sfwd-courses-sitemap.xml',
            'type' => 'sitemap',
            'name' => 'GS Investment University Courses',
        ),
        array(
            'url'  => 'https://www.gsinvestmentuniversity.com/sfwd-lessons-sitemap.xml',
            'type' => 'sitemap',
            'name' => 'GS Investment University Lessons',
        ),
        array(
            'url'  => 'https://www.gsinvestmentuniversity.com/sfwd-topic-sitemap.xml',
            'type' => 'sitemap',
            'name' => 'GS Investment University Topics',
        ),
    );

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        add_action('sffc_crawl_topic_ideas', array($this, 'crawl_all_sources'));
    }

    /**
     * Get all configured feed sources
     */
    public function get_feed_sources()
    {
        return apply_filters('sffc_topic_feed_sources', $this->feed_sources);
    }

    /**
     * Add a new feed source
     */
    public function add_feed_source($url, $type = 'sitemap', $name = '')
    {
        $this->feed_sources[] = array(
            'url'  => $url,
            'type' => $type,
            'name' => $name ?: parse_url($url, PHP_URL_HOST),
        );
    }

    /**
     * Crawl all sources and extract topic ideas
     */
    public function crawl_all_sources($limit_per_source = 10)
    {
        $all_topics = array();

        foreach ($this->feed_sources as $source) {
            $topics = $this->crawl_source($source, $limit_per_source);
            $all_topics = array_merge($all_topics, $topics);

            // Be respectful - small delay between sources
            usleep(500000); // 0.5 second
        }

        // Store crawled topics
        $this->store_topic_ideas($all_topics);

        return $all_topics;
    }

    /**
     * Crawl a single source
     */
    public function crawl_source($source, $limit = 10)
    {
        $topics = array();

        try {
            if ($source['type'] === 'sitemap') {
                $topics = $this->parse_sitemap($source['url'], $limit);
            } elseif ($source['type'] === 'rss') {
                $topics = $this->parse_rss_feed($source['url'], $limit);
            }

            // Add source info to each topic
            foreach ($topics as &$topic) {
                $topic['source_name'] = $source['name'];
                $topic['source_url'] = $source['url'];
            }
        } catch (Exception $e) {
            error_log('SFFC Topic Crawler Error for ' . $source['url'] . ': ' . $e->getMessage());
        }

        return $topics;
    }

    /**
     * Parse XML sitemap
     */
    private function parse_sitemap($url, $limit)
    {
        $topics = array();

        $response = wp_remote_get($url, array(
            'timeout'    => 30,
            'user-agent' => 'Mozilla/5.0 (compatible; SennaBot/1.0)',
        ));

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            return $topics;
        }

        // Suppress XML errors
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        libxml_clear_errors();

        if (!$xml) {
            return $topics;
        }

        // Register namespaces
        $namespaces = $xml->getNamespaces(true);

        $count = 0;
        foreach ($xml->url as $url_node) {
            if ($count >= $limit) {
                break;
            }

            $loc = (string) $url_node->loc;
            if (empty($loc)) {
                continue;
            }

            // Skip already processed URLs
            if ($this->is_url_processed($loc)) {
                continue;
            }

            $topic = $this->extract_topic_from_url($loc);
            if ($topic) {
                $topics[] = $topic;
                $count++;
            }
        }

        return $topics;
    }

    /**
     * Parse RSS feed
     */
    private function parse_rss_feed($url, $limit)
    {
        $topics = array();

        $response = wp_remote_get($url, array(
            'timeout'    => 30,
            'user-agent' => 'Mozilla/5.0 (compatible; SennaBot/1.0)',
        ));

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            return $topics;
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        libxml_clear_errors();

        if (!$xml) {
            return $topics;
        }

        $count = 0;

        // Handle both RSS and Atom feeds
        $items = isset($xml->channel->item) ? $xml->channel->item : (isset($xml->entry) ? $xml->entry : array());

        foreach ($items as $item) {
            if ($count >= $limit) {
                break;
            }

            $link = isset($item->link) ? (string) $item->link : '';
            $title = isset($item->title) ? (string) $item->title : '';

            // For Atom feeds
            if (empty($link) && isset($item->link['href'])) {
                $link = (string) $item->link['href'];
            }

            if (empty($link)) {
                continue;
            }

            // Skip already processed URLs
            if ($this->is_url_processed($link)) {
                continue;
            }

            $topic = array(
                'original_url'   => $link,
                'original_title' => $title,
                'topic_slug'     => $this->url_to_slug($link),
                'extracted_at'   => current_time('mysql'),
            );

            // Parse topic ideas from title and URL
            $topic['topic_ideas'] = $this->generate_topic_variations($topic);

            $topics[] = $topic;
            $count++;
        }

        return $topics;
    }

    /**
     * Extract topic information from URL
     */
    private function extract_topic_from_url($url)
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (empty($path)) {
            return null;
        }

        // Get the last segment of the URL path
        $segments = explode('/', trim($path, '/'));
        $slug = end($segments);

        // Skip generic pages
        $skip_slugs = array('about', 'contact', 'privacy', 'terms', 'login', 'register', 'cart', 'checkout');
        if (in_array($slug, $skip_slugs) || empty($slug)) {
            return null;
        }

        // Skip if too short (likely not content)
        if (strlen($slug) < 5) {
            return null;
        }

        return array(
            'original_url'   => $url,
            'original_title' => '', // Will be filled by Claude
            'topic_slug'     => $slug,
            'topic_ideas'    => $this->generate_topic_variations(array('topic_slug' => $slug)),
            'extracted_at'   => current_time('mysql'),
        );
    }

    /**
     * Convert URL to readable slug
     */
    private function url_to_slug($url)
    {
        $path = parse_url($url, PHP_URL_PATH);
        $segments = explode('/', trim($path, '/'));
        return end($segments);
    }

    /**
     * Generate topic variations for Claude to choose from
     */
    private function generate_topic_variations($topic)
    {
        $slug = $topic['topic_slug'] ?? '';
        $title = $topic['original_title'] ?? '';

        // Convert slug to readable words
        $words = str_replace(array('-', '_'), ' ', $slug);
        $words = ucwords(strtolower($words));

        $variations = array();

        // Detect topic type and suggest variations
        $slug_lower = strtolower($slug);

        // Company/Firm detection
        if ($this->looks_like_company($slug_lower)) {
            $company = $words;
            $variations[] = array(
                'type'  => 'interview-guide',
                'title' => "{$company} Interview Questions & Answers",
            );
            $variations[] = array(
                'type'  => 'salary-guide',
                'title' => "{$company} Salary Guide: Compensation & Benefits",
            );
            $variations[] = array(
                'type'  => 'firm-profile',
                'title' => "Working at {$company}: Culture, Career Path & Reviews",
            );
        }

        // Concept/Skill detection
        if ($this->looks_like_concept($slug_lower)) {
            $variations[] = array(
                'type'  => 'how-to-guide',
                'title' => "How to Master {$words}: Complete Guide",
            );
            $variations[] = array(
                'type'  => 'industry-explainer',
                'title' => "What is {$words}? Definition & Examples",
            );
            $variations[] = array(
                'type'  => 'skills-guide',
                'title' => "{$words}: Skills You Need to Succeed",
            );
        }

        // Location/Market detection
        if ($this->looks_like_location($slug_lower)) {
            $location = $words;
            $variations[] = array(
                'type'  => 'market-analysis',
                'title' => "Finance Jobs in {$location}: Market Overview",
            );
            $variations[] = array(
                'type'  => 'salary-guide',
                'title' => "Finance Salaries in {$location}: Complete Guide",
            );
        }

        // Career path detection
        if (strpos($slug_lower, 'career') !== false || strpos($slug_lower, 'path') !== false) {
            $variations[] = array(
                'type'  => 'career-path',
                'title' => "{$words}: Complete Career Path Guide",
            );
        }

        // Interview detection
        if (strpos($slug_lower, 'interview') !== false) {
            $variations[] = array(
                'type'  => 'interview-guide',
                'title' => "{$words}: Questions, Answers & Tips",
            );
        }

        // Model/Valuation detection
        if (strpos($slug_lower, 'model') !== false || strpos($slug_lower, 'valuation') !== false) {
            $variations[] = array(
                'type'  => 'how-to-guide',
                'title' => "{$words}: Step-by-Step Tutorial",
            );
        }

        // Default fallback if no specific pattern matched
        if (empty($variations)) {
            $variations[] = array(
                'type'  => 'industry-explainer',
                'title' => "{$words}: Complete Guide for Finance Professionals",
            );
        }

        return $variations;
    }

    /**
     * Check if slug looks like a company name
     */
    private function looks_like_company($slug)
    {
        $company_indicators = array(
            'bank', 'capital', 'partners', 'group', 'holdings', 'management',
            'advisors', 'securities', 'investments', 'asset', 'fund',
            'blackstone', 'kkr', 'apollo', 'carlyle', 'tpg', 'bain',
            'goldman', 'morgan', 'jpmorgan', 'citi', 'barclays', 'ubs',
            'credit-suisse', 'deutsche', 'hsbc', 'bofa', 'merrill',
        );

        foreach ($company_indicators as $indicator) {
            if (strpos($slug, $indicator) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if slug looks like a concept/skill
     */
    private function looks_like_concept($slug)
    {
        $concept_indicators = array(
            'what-is', 'how-to', 'guide', 'tutorial', 'basics', 'fundamentals',
            'introduction', 'overview', 'explained', 'definition',
            'modeling', 'valuation', 'analysis', 'method', 'formula',
            'lbo', 'dcf', 'wacc', 'irr', 'npv', 'ebitda', 'multiple',
        );

        foreach ($concept_indicators as $indicator) {
            if (strpos($slug, $indicator) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if slug looks like a location
     */
    private function looks_like_location($slug)
    {
        $location_indicators = array(
            'london', 'new-york', 'nyc', 'singapore', 'hong-kong', 'dubai',
            'frankfurt', 'paris', 'tokyo', 'sydney', 'chicago', 'boston',
            'san-francisco', 'los-angeles', 'toronto', 'mumbai', 'shanghai',
            'usa', 'uk', 'europe', 'asia', 'private-equity', 'latin-america',
            'brazil', 'india', 'china', 'germany', 'france', 'australia',
        );

        foreach ($location_indicators as $indicator) {
            if (strpos($slug, $indicator) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if URL has already been processed
     */
    private function is_url_processed($url)
    {
        global $wpdb;

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta}
             WHERE meta_key = '_source_topic_url' AND meta_value = %s",
            $url
        ));

        return $existing > 0;
    }

    /**
     * Store topic ideas for later processing
     */
    private function store_topic_ideas($topics)
    {
        $existing = get_option('sffc_pending_topic_ideas', array());
        $new_topics = array_merge($existing, $topics);

        // Remove duplicates by URL
        $unique = array();
        foreach ($new_topics as $topic) {
            $key = md5($topic['original_url']);
            if (!isset($unique[$key])) {
                $unique[$key] = $topic;
            }
        }

        update_option('sffc_pending_topic_ideas', array_values($unique));

        return count($topics);
    }

    /**
     * Get pending topic ideas for processing
     */
    public function get_pending_topics($limit = 60)
    {
        $topics = get_option('sffc_pending_topic_ideas', array());
        return array_slice($topics, 0, $limit);
    }

    /**
     * Mark topics as processed
     */
    public function mark_topics_processed($topic_urls)
    {
        $topics = get_option('sffc_pending_topic_ideas', array());

        $topics = array_filter($topics, function ($topic) use ($topic_urls) {
            return !in_array($topic['original_url'], $topic_urls);
        });

        update_option('sffc_pending_topic_ideas', array_values($topics));
    }

    /**
     * Get topic statistics
     */
    public function get_stats()
    {
        $pending = get_option('sffc_pending_topic_ideas', array());

        global $wpdb;
        $processed = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'sffc_career_insights' AND post_status = 'publish'"
        );

        return array(
            'pending_topics'   => count($pending),
            'processed_guides' => intval($processed),
            'sources_count'    => count($this->feed_sources),
        );
    }
}

// Initialize
SFFC_Topic_Idea_Crawler::get_instance();
