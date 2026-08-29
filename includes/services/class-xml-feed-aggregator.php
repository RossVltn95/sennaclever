<?php
/**
 * XML Feed Aggregator - FIXED VERSION with working URLs
 * Handles RSS, Google News Sitemap, and Standard XML Sitemap formats
 * 
 * @package SennaCareers
 * @since 3.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_XML_Feed_Aggregator {
    
    /**
     * Registered feeds
     */
    private $feeds = array();
    
    /**
     * Cache duration in seconds (15 minutes)
     */
    private $cache_duration = 900;
    
    /**
     * Maximum age for posts in days
     */
    private $max_age_days = 7;
    
    /**
     * Instance
     */
    private static $instance = null;
    
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
        $this->register_default_feeds();
        $this->load_custom_feeds_from_db();
    }
    
    /**
     * Register default feeds - FIXED URLS (only working feeds)
     */
    private function register_default_feeds() {
        // WORKING FEEDS ONLY
        
        // Private Equity Wire - WORKING
        $this->register_feed('pe_wire', array(
            'url' => 'https://www.privateequitywire.co.uk/feed/',
            'type' => 'rss',
            'category' => 'Private Equity',
            'keywords' => array('buyout', 'acquisition', 'portfolio', 'fund')
        ));
        
        // Private Equity International - WORKING
        $this->register_feed('pe_international', array(
            'url' => 'https://www.privateequityinternational.com/feed/',
            'type' => 'rss',
            'category' => 'Private Equity',
            'keywords' => array('PE', 'private equity', 'buyout')
        ));
        
        // ValueWalk - WORKING
        $this->register_feed('valuewalk', array(
            'url' => 'https://www.valuewalk.com/feed/',
            'type' => 'rss',
            'category' => 'Hedge Funds',
            'keywords' => array('hedge fund', 'value investing', 'activism')
        ));
        
        // MarketWatch - Major financial news (updated URL after redirect)
        $this->register_feed('marketwatch', array(
            'url' => 'https://feeds.content.dowjones.io/public/rss/mw_topstories',
            'type' => 'rss',
            'category' => 'Markets',
            'keywords' => array('market', 'stocks', 'fed', 'trading')
        ));
        
        // Economist Finance & Economics
        $this->register_feed('economist', array(
            'url' => 'https://www.economist.com/finance-and-economics/rss.xml',
            'type' => 'rss',
            'category' => 'Global Finance',
            'keywords' => array('economy', 'finance', 'markets', 'policy')
        ));
        
        // Alternative Credit Investor - WORKING
        $this->register_feed('credit_investor', array(
            'url' => 'https://alternativecreditinvestor.com/news-sitemap.xml',
            'type' => 'sitemap',
            'category' => 'Credit',
            'keywords' => array('credit', 'bond', 'debt', 'yield')
        ));
        
        // Health Investor - WORKING
        $this->register_feed('health_investor', array(
            'url' => 'https://healthinvestor.co.uk/post-sitemap8.xml',
            'type' => 'sitemap',
            'category' => 'Healthcare',
            'keywords' => array('biotech', 'pharma', 'health')
        ));
        
        // Real Assets IPE - WORKING
        $this->register_feed('real_assets', array(
            'url' => 'https://realassets.ipe.com/googlesitemap.aspx?year=2025',
            'type' => 'google_news',
            'category' => 'Infrastructure',
            'keywords' => array('infrastructure', 'real estate', 'REIT')
        ));
        
        // Seeking Alpha - WORKING
        $this->register_feed('seeking_alpha', array(
            'url' => 'https://seekingalpha.com/sitemap_news.xml',
            'type' => 'sitemap',
            'category' => 'Sector Analysis',
            'keywords' => array('sector', 'industry', 'analysis')
        ));
        
        // Infrastructure Investor
        $this->register_feed('infrastructure_investor', array(
            'url' => 'https://www.infrastructureinvestor.com/feed/',
            'type' => 'rss',
            'category' => 'Infrastructure',
            'keywords' => array('infrastructure', 'real assets', 'energy', 'utilities')
        ));
        
        // Additional reliable feeds to reach 20 sources
        
        // Wall Street Journal Markets
        $this->register_feed('wsj_markets', array(
            'url' => 'https://feeds.a.dj.com/rss/RSSMarketsMain.xml',
            'type' => 'rss',
            'category' => 'Markets',
            'keywords' => array('equity', 'stocks', 'trading')
        ));
        
        // CNBC Markets
        $this->register_feed('cnbc_markets', array(
            'url' => 'https://www.cnbc.com/id/10000664/device/rss/rss.html',
            'type' => 'rss',
            'category' => 'Markets',
            'keywords' => array('market', 'economy', 'business')
        ));
        
        // Benzinga Markets
        $this->register_feed('benzinga', array(
            'url' => 'https://www.benzinga.com/feed/',
            'type' => 'rss',
            'category' => 'Markets',
            'keywords' => array('trading', 'stocks', 'options', 'markets')
        ));
        
        // Financial Times
        $this->register_feed('ft_companies', array(
            'url' => 'https://www.ft.com/companies?format=rss',
            'type' => 'rss',
            'category' => 'Corporate',
            'keywords' => array('companies', 'corporate', 'business')
        ));
        
        // Investor's Business Daily
        $this->register_feed('ibd', array(
            'url' => 'https://www.investors.com/feed/',
            'type' => 'rss',
            'category' => 'Market Analysis',
            'keywords' => array('stocks', 'earnings', 'market', 'trading')
        ));
        
        // Venture Beat
        $this->register_feed('venturebeat', array(
            'url' => 'https://feeds.feedburner.com/venturebeat/SZYF',
            'type' => 'rss',
            'category' => 'Venture Capital',
            'keywords' => array('venture', 'startup', 'tech', 'AI')
        ));
        
        // Business Insider Markets
        $this->register_feed('business_insider', array(
            'url' => 'https://markets.businessinsider.com/rss/news',
            'type' => 'rss',
            'category' => 'Markets',
            'keywords' => array('market', 'stocks', 'investing')
        ));
        
        // InvestorPlace - Market analysis
        $this->register_feed('investorplace', array(
            'url' => 'https://investorplace.com/feed/',
            'type' => 'rss',
            'category' => 'Investment Analysis',
            'keywords' => array('stocks', 'investing', 'analysis', 'portfolio')
        ));
        
        // Crunchbase News
        $this->register_feed('crunchbase', array(
            'url' => 'https://news.crunchbase.com/feed/',
            'type' => 'rss',
            'category' => 'Startups',
            'keywords' => array('funding', 'startup', 'rounds', 'IPO')
        ));
        
        // Buyouts Insider Magazine
        $this->register_feed('buyouts_insider', array(
            'url' => 'https://www.buyoutsinsider.com/feed/',
            'type' => 'rss',
            'category' => 'Private Equity',
            'keywords' => array('buyout', 'PE', 'LBO', 'portfolio')
        ));
        
        // Additional high-quality finance feeds
        
        // Fortune Finance
        $this->register_feed('fortune', array(
            'url' => 'https://fortune.com/feed/',
            'type' => 'rss',
            'category' => 'Business',
            'keywords' => array('business', 'finance', 'corporate', 'CEO')
        ));
        
        // The Motley Fool
        $this->register_feed('motley_fool', array(
            'url' => 'https://www.fool.com/feeds/index.aspx',
            'type' => 'rss',
            'category' => 'Investment',
            'keywords' => array('stocks', 'investing', 'retirement', 'dividends')
        ));
        
        // Zero Hedge Markets
        $this->register_feed('zerohedge', array(
            'url' => 'https://feeds.feedburner.com/zerohedge/feed',
            'type' => 'rss',
            'category' => 'Market Analysis',
            'keywords' => array('markets', 'macro', 'trading', 'fed')
        ));
        
        // Venture Capital Journal
        $this->register_feed('vc_journal', array(
            'url' => 'https://www.venturecapitaljournal.com/feed/',
            'type' => 'rss',
            'category' => 'Venture Capital',
            'keywords' => array('venture', 'startup', 'seed', 'series')
        ));
        
        // City AM London
        $this->register_feed('city_am', array(
            'url' => 'https://www.cityam.com/feed/',
            'type' => 'rss',
            'category' => 'UK Finance',
            'keywords' => array('London', 'UK', 'banking', 'fintech')
        ));
    }
    
    /**
     * Load custom feeds from database
     */
    private function load_custom_feeds_from_db() {
        try {
            // Check if we're in a valid WordPress context
            if (!function_exists('get_option')) {
                return;
            }
            
            // Check if database class exists
            if (!class_exists('SFFC_Database')) {
                // Only try to load if the file exists
                $db_file = SFFC_PLUGIN_DIR . 'includes/class-database.php';
                if (!file_exists($db_file)) {
                    return;
                }
                require_once $db_file;
            }
            
            $db = SFFC_Database::get_instance();
            $table_name = $db->get_table('xml_feeds');
            
            if (!$table_name || !$db->table_exists('xml_feeds')) {
                return; // Table doesn't exist yet
            }
            
            global $wpdb;
            
            // Get active custom feeds from database
            $custom_feeds = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table_name} 
                 WHERE is_active = %d 
                 ORDER BY priority ASC, feed_name ASC",
                1
            ));
            
            if (!empty($custom_feeds)) {
                foreach ($custom_feeds as $feed) {
                    // Generate unique feed key
                    $feed_key = 'custom_' . $feed->id . '_' . sanitize_title($feed->feed_name);
                    
                    // Register the custom feed
                    $this->register_feed($feed_key, array(
                        'url' => $feed->feed_url,
                        'type' => 'rss', // Default to RSS
                        'category' => $feed->feed_category,
                        'keywords' => array(), // Custom feeds don't have keywords yet
                        'priority' => $feed->priority,
                        'is_custom' => true,
                        'feed_id' => $feed->id,
                        'feed_name' => $feed->feed_name
                    ));
                }
            }
        } catch (Exception $e) {
            error_log('SFFC: Error loading custom feeds: ' . $e->getMessage());
            return;
        }
    }
    
    /**
     * Register a new feed
     */
    public function register_feed($name, $config) {
        $this->feeds[$name] = $config;
    }
    
    /**
     * Get all registered feeds
     */
    public function get_registered_feeds() {
        return $this->feeds;
    }
    
    /**
     * Aggregate content from all feeds
     * 
     * @param string $query Optional search query
     * @param int $limit Maximum items to return
     * @return array
     */
    public function aggregate_feeds($query = '', $limit = 20) {
        // Check if feeds are enabled (default to enabled if not set)
        if (get_option('sffc_enable_xml_feeds', 1) == 0) {
            return array(); // Return empty only if explicitly disabled
        }
        
        $all_items = array();
        
        foreach ($this->feeds as $feed_name => $feed_config) {
            $items = $this->fetch_feed($feed_name, $feed_config);
            
            if (!empty($items)) {
                // Filter by query if provided
                if (!empty($query)) {
                    $items = $this->filter_by_query($items, $query);
                }
                
                $all_items = array_merge($all_items, $items);
            }
        }
        
        // Sort by date (newest first)
        usort($all_items, function($a, $b) {
            $date_a = isset($a['timestamp']) ? $a['timestamp'] : 0;
            $date_b = isset($b['timestamp']) ? $b['timestamp'] : 0;
            return $date_b - $date_a;
        });
        
        // Limit results
        return array_slice($all_items, 0, $limit);
    }
    
    /**
     * Fetch a single feed
     * 
     * @param string $feed_name
     * @param array $config
     * @return array
     */
    private function fetch_feed($feed_name, $config) {
        // Check cache first (but allow force refresh)
        $cache_key = 'sffc_feed_' . md5($feed_name . $config['url']);
        $cached = get_transient($cache_key);
        
        // Don't return cached empty results
        if ($cached !== false && !empty($cached)) {
            return $cached;
        }
        
        // Fetch fresh data with configurable timeout
        $timeout = get_option('sffc_feed_timeout', 15);
        
        // Check if SSL verification should be enabled (default to true for security)
        $ssl_verify = get_option('sffc_feed_ssl_verify', true);
        
        // For known problematic feeds, we can selectively disable SSL verification
        $problematic_hosts = array(
            'alternativecreditinvestor.com',
            'healthinvestor.co.uk',
            'realassets.ipe.com'
        );
        
        $host = parse_url($config['url'], PHP_URL_HOST);
        if (in_array($host, $problematic_hosts)) {
            $ssl_verify = false;
        }
        
        $response = wp_remote_get($config['url'], array(
            'timeout' => $timeout,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'sslverify' => $ssl_verify,  // Use selective SSL verification
            'redirection' => 5,    // Follow redirects
            'httpversion' => '1.1',
            'blocking' => true
        ));
        
        if (is_wp_error($response)) {
            error_log('SFFC Feed Error for ' . $feed_name . ': ' . $response->get_error_message());
            
            // Update error count in database for custom feeds
            if (!empty($config['is_custom']) && !empty($config['feed_id'])) {
                $this->update_feed_error_status($config['feed_id'], $response->get_error_message());
            }
            
            return array();
        }
        
        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            return array();
        }
        
        // Parse based on type
        $items = array();
        switch ($config['type']) {
            case 'rss':
                $items = $this->parse_rss_feed($body, $config);
                break;
            case 'google_news':
                $items = $this->parse_google_news_sitemap($body, $config);
                break;
            case 'sitemap':
                $items = $this->parse_xml_sitemap($body, $config);
                break;
        }
        
        // Filter by date
        $items = $this->filter_by_date($items);
        
        // Only cache if we got actual results (don't cache failures)
        if (!empty($items) && count($items) > 0) {
            set_transient($cache_key, $items, $this->cache_duration);
            
            // Update success count for custom feeds
            if (!empty($config['is_custom']) && !empty($config['feed_id'])) {
                $this->update_feed_success_status($config['feed_id']);
            }
        }
        
        return $items;
    }
    
    /**
     * Parse RSS feed
     */
    private function parse_rss_feed($xml_content, $config) {
        $items = array();
        
        // Suppress XML errors
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xml_content);
        
        if (!$xml || !isset($xml->channel->item)) {
            return $items;
        }
        
        foreach ($xml->channel->item as $item) {
            $parsed_item = array(
                'title' => (string) $item->title,
                'description' => strip_tags((string) $item->description),
                'link' => (string) $item->link,
                'pubDate' => (string) $item->pubDate,
                'category' => $config['category'],
                'source' => parse_url($config['url'], PHP_URL_HOST),
                'feed_name' => $config['category']
            );
            
            // Try to extract image
            $parsed_item['image'] = $this->extract_image_from_rss($item);
            
            $items[] = $parsed_item;
        }
        
        return $items;
    }
    
    /**
     * Parse Google News Sitemap
     */
    private function parse_google_news_sitemap($xml_content, $config) {
        $items = array();
        
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xml_content);
        
        if (!$xml) {
            return $items;
        }
        
        // Register namespaces
        $xml->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $xml->registerXPathNamespace('n', 'http://www.google.com/schemas/sitemap-news/0.9');
        
        $urls = $xml->xpath('//s:url');
        
        foreach ($urls as $url) {
            $news = $url->children('http://www.google.com/schemas/sitemap-news/0.9');
            
            if ($news && isset($news->news)) {
                $parsed_item = array(
                    'title' => (string) $news->news->title,
                    'link' => (string) $url->loc,
                    'pubDate' => (string) $news->news->publication_date,
                    'category' => $config['category'],
                    'source' => parse_url($config['url'], PHP_URL_HOST),
                    'feed_name' => $config['category'],
                    'description' => (string) $news->news->title, // Use title as description if not available
                    'image' => $this->get_placeholder_image($config['category'])
                );
                
                $items[] = $parsed_item;
            }
        }
        
        return $items;
    }
    
    /**
     * Parse standard XML Sitemap
     */
    private function parse_xml_sitemap($xml_content, $config) {
        $items = array();
        
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xml_content);
        
        if (!$xml) {
            return $items;
        }
        
        foreach ($xml->url as $url) {
            $parsed_item = array(
                'title' => $this->extract_title_from_url((string) $url->loc),
                'link' => (string) $url->loc,
                'pubDate' => isset($url->lastmod) ? (string) $url->lastmod : date('c'),
                'category' => $config['category'],
                'source' => parse_url($config['url'], PHP_URL_HOST),
                'feed_name' => $config['category'],
                'description' => 'Click to read full article',
                'image' => $this->get_placeholder_image($config['category'])
            );
            
            $items[] = $parsed_item;
        }
        
        return $items;
    }
    
    /**
     * Extract image from RSS item
     */
    private function extract_image_from_rss($item) {
        // Check for media:thumbnail
        $namespaces = $item->getNameSpaces(true);
        if (isset($namespaces['media'])) {
            $media = $item->children($namespaces['media']);
            if (isset($media->thumbnail)) {
                $attributes = $media->thumbnail->attributes();
                if (isset($attributes['url'])) {
                    return (string) $attributes['url'];
                }
            }
        }
        
        // Check for enclosure
        if (isset($item->enclosure)) {
            $attributes = $item->enclosure->attributes();
            if (isset($attributes['url']) && isset($attributes['type'])) {
                $type = (string) $attributes['type'];
                if (strpos($type, 'image') !== false) {
                    return (string) $attributes['url'];
                }
            }
        }
        
        // Return placeholder
        return $this->get_placeholder_image('default');
    }
    
    /**
     * Get placeholder image based on category
     */
    private function get_placeholder_image($category) {
        // Use senna.jpeg as universal placeholder since specific ones don't exist
        return SFFC_PLUGIN_URL . 'assets/images/senna.jpeg';
    }
    
    /**
     * Extract title from URL
     */
    private function extract_title_from_url($url) {
        $path = parse_url($url, PHP_URL_PATH);
        $segments = explode('/', trim($path, '/'));
        $last_segment = end($segments);
        
        // Remove file extension
        $title = preg_replace('/\.[^.]+$/', '', $last_segment);
        
        // Replace hyphens/underscores with spaces and capitalize
        $title = str_replace(array('-', '_'), ' ', $title);
        $title = ucwords($title);
        
        return $title ?: 'Market Update';
    }
    
    /**
     * Filter items by date
     */
    private function filter_by_date($items) {
        $filtered = array();
        $max_age_timestamp = strtotime("-{$this->max_age_days} days");
        
        foreach ($items as $item) {
            $item_date = $this->normalize_date($item['pubDate']);
            
            if ($item_date && $item_date >= $max_age_timestamp) {
                $item['time_ago'] = $this->get_time_ago($item_date);
                $item['timestamp'] = $item_date;
                $filtered[] = $item;
            }
        }
        
        return $filtered;
    }
    
    /**
     * Normalize various date formats
     */
    private function normalize_date($date_string) {
        if (empty($date_string)) {
            return time();
        }
        
        // Try standard parsing first
        $timestamp = strtotime($date_string);
        if ($timestamp !== false) {
            return $timestamp;
        }
        
        // Try ISO 8601
        $date = DateTime::createFromFormat(DateTime::ISO8601, $date_string);
        if ($date) {
            return $date->getTimestamp();
        }
        
        // Try RFC 3339
        $date = DateTime::createFromFormat(DateTime::RFC3339, $date_string);
        if ($date) {
            return $date->getTimestamp();
        }
        
        // Default to current time
        return time();
    }
    
    /**
     * Get human-readable time ago
     */
    private function get_time_ago($timestamp) {
        $diff = time() - $timestamp;
        
        if ($diff < 3600) {
            $mins = round($diff / 60);
            return $mins . ' min' . ($mins != 1 ? 's' : '') . ' ago';
        } elseif ($diff < 86400) {
            $hours = round($diff / 3600);
            return $hours . ' hour' . ($hours != 1 ? 's' : '') . ' ago';
        } else {
            $days = round($diff / 86400);
            return $days . ' day' . ($days != 1 ? 's' : '') . ' ago';
        }
    }
    
    /**
     * Filter items by search query
     */
    private function filter_by_query($items, $query) {
        if (empty($query)) {
            return $items;
        }
        
        $query_lower = strtolower($query);
        $filtered = array();
        
        foreach ($items as $item) {
            $searchable = strtolower($item['title'] . ' ' . $item['description'] . ' ' . $item['category']);
            
            if (strpos($searchable, $query_lower) !== false) {
                $filtered[] = $item;
            }
        }
        
        return $filtered;
    }
    
    /**
     * Update feed error status in database
     */
    private function update_feed_error_status($feed_id, $error_message) {
        if (!class_exists('SFFC_Database')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-database.php';
        }
        
        $db = SFFC_Database::get_instance();
        $table_name = $db->get_table('xml_feeds');
        
        if (!$table_name) {
            return;
        }
        
        global $wpdb;
        
        // Get current error count
        $current_error_count = $wpdb->get_var($wpdb->prepare(
            "SELECT error_count FROM {$table_name} WHERE id = %d",
            $feed_id
        ));
        
        // Update error status
        $wpdb->update(
            $table_name,
            array(
                'last_error' => $error_message,
                'error_count' => $current_error_count + 1,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $feed_id),
            array('%s', '%d', '%s'),
            array('%d')
        );
    }
    
    /**
     * Update feed success status in database
     */
    private function update_feed_success_status($feed_id) {
        if (!class_exists('SFFC_Database')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-database.php';
        }
        
        $db = SFFC_Database::get_instance();
        $table_name = $db->get_table('xml_feeds');
        
        if (!$table_name) {
            return;
        }
        
        global $wpdb;
        
        // Get current success count
        $current_success_count = $wpdb->get_var($wpdb->prepare(
            "SELECT success_count FROM {$table_name} WHERE id = %d",
            $feed_id
        ));
        
        // Update success status
        $wpdb->update(
            $table_name,
            array(
                'last_fetched' => current_time('mysql'),
                'success_count' => $current_success_count + 1,
                'error_count' => 0, // Reset error count on success
                'last_error' => null,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $feed_id),
            array('%s', '%d', '%d', '%s', '%s'),
            array('%d')
        );
    }
}