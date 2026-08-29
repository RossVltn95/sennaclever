<?php
/**
 * Market Feed Manager - Parses real-time market data from XML feeds
 * 
 * @package SennaCareers
 * @subpackage Market
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Market_Feed_Manager {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Feed sources configuration
     */
    private $feeds = array(
        'bloomberg' => array(
            'url' => 'https://feeds.bloomberg.com/markets/news.rss',
            'name' => 'Bloomberg Markets',
            'region' => 'Global',
            'focus' => 'Markets & Finance'
        ),
        'ft' => array(
            'url' => 'https://www.ft.com/markets?format=rss',
            'name' => 'Financial Times',
            'region' => 'Global', 
            'focus' => 'Markets Analysis'
        ),
        'wsj' => array(
            'url' => 'https://feeds.a.dj.com/rss/RSSMarketsMain.xml',
            'name' => 'Wall Street Journal',
            'region' => 'US',
            'focus' => 'US Markets'
        ),
        'expansion' => array(
            'url' => 'https://e00-expansion.uecdn.es/rss/mercados.xml',
            'name' => 'Expansión',
            'region' => 'Spain',
            'focus' => 'European Markets'
        ),
        'ilsole' => array(
            'url' => 'https://www.ilsole24ore.com/rss/finanza.xml',
            'name' => 'Il Sole 24 Ore',
            'region' => 'Italy',
            'focus' => 'Italian Markets'
        )
    );
    
    /**
     * Cache duration in seconds (15 minutes)
     */
    private $cache_duration = 900;
    
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
        // Load dependencies
        $this->load_dependencies();
        
        // Schedule cron job for feed updates
        add_action('sffc_update_market_feeds', array($this, 'update_all_feeds'));
        
        if (!wp_next_scheduled('sffc_update_market_feeds')) {
            wp_schedule_event(time(), 'sffc_quarter_hourly', 'sffc_update_market_feeds');
        }
        
        // Add custom cron schedule
        add_filter('cron_schedules', array($this, 'add_cron_schedules'));
    }
    
    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        require_once SFFC_PLUGIN_DIR . 'includes/services/class-xml-feed-aggregator.php';
        require_once SFFC_PLUGIN_DIR . 'includes/parsers/class-feed-date-parser.php';
    }
    
    /**
     * Add custom cron schedules
     */
    public function add_cron_schedules($schedules) {
        $schedules['sffc_quarter_hourly'] = array(
            'interval' => 900,
            'display' => 'Every 15 minutes'
        );
        return $schedules;
    }
    
    /**
     * Fetch and parse a single feed
     */
    public function fetch_feed($feed_key) {
        if (!isset($this->feeds[$feed_key])) {
            return false;
        }
        
        $feed = $this->feeds[$feed_key];
        
        // Check cache first
        $cached = get_transient('sffc_feed_' . $feed_key);
        if ($cached !== false) {
            return $cached;
        }
        
        // Fetch fresh data
        $response = wp_remote_get($feed['url'], array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'Mozilla/5.0 (compatible; SFFC/1.0)'
            )
        ));
        
        if (is_wp_error($response)) {
            error_log('SFFC Feed Error: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $parsed = $this->parse_rss($body, $feed);
        
        // Cache the parsed data
        set_transient('sffc_feed_' . $feed_key, $parsed, $this->cache_duration);
        
        return $parsed;
    }
    
    /**
     * Parse RSS/XML content
     */
    private function parse_rss($xml_content, $feed_info) {
        if (empty($xml_content)) {
            return false;
        }
        
        // Suppress XML errors
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xml_content);
        
        if ($xml === false) {
            error_log('SFFC XML Parse Error for ' . $feed_info['name']);
            return false;
        }
        
        $items = array();
        $channel = $xml->channel;
        
        // Parse items
        foreach ($channel->item as $item) {
            $parsed_item = array(
                'title' => (string) $item->title,
                'description' => strip_tags((string) $item->description),
                'link' => (string) $item->link,
                'pubDate' => date('Y-m-d H:i:s', strtotime((string) $item->pubDate)),
                'source' => $feed_info['name'],
                'region' => $feed_info['region'],
                'focus' => $feed_info['focus']
            );
            
            // Extract categories if available
            if (isset($item->category)) {
                $categories = array();
                foreach ($item->category as $cat) {
                    $categories[] = (string) $cat;
                }
                $parsed_item['categories'] = $categories;
            }
            
            // Add WHY analysis placeholder
            $parsed_item['why_analysis'] = $this->generate_why_analysis($parsed_item);
            
            $items[] = $parsed_item;
        }
        
        return array(
            'source' => $feed_info,
            'updated' => current_time('mysql'),
            'items' => array_slice($items, 0, 10) // Limit to 10 items
        );
    }
    
    /**
     * Generate WHY analysis for a news item
     * This will be enhanced with Claude API integration
     */
    private function generate_why_analysis($item) {
        // Extract key indicators from title and description
        $content = strtolower($item['title'] . ' ' . $item['description']);
        
        $analysis = array(
            'market_impact' => 'pending_analysis',
            'career_relevance' => 'pending_analysis',
            'key_drivers' => array(),
            'learning_points' => array()
        );
        
        // Pattern matching for market movements
        if (preg_match('/(surge|jump|rise|gain|up\s+\d+%)/i', $content)) {
            $analysis['sentiment'] = 'positive';
            $analysis['key_drivers'][] = 'Positive market movement detected';
        } elseif (preg_match('/(drop|fall|decline|down\s+\d+%)/i', $content)) {
            $analysis['sentiment'] = 'negative';
            $analysis['key_drivers'][] = 'Negative market movement detected';
        } else {
            $analysis['sentiment'] = 'neutral';
        }
        
        // Detect PE/Finance keywords
        if (preg_match('/(private equity|PE|buyout|LBO|acquisition|merger)/i', $content)) {
            $analysis['career_relevance'] = 'high';
            $analysis['sector'] = 'Private Equity';
        }
        
        if (preg_match('/(hedge fund|trading|portfolio)/i', $content)) {
            $analysis['career_relevance'] = 'high';
            $analysis['sector'] = 'Hedge Funds';
        }
        
        if (preg_match('/(investment bank|IPO|capital markets)/i', $content)) {
            $analysis['career_relevance'] = 'high';
            $analysis['sector'] = 'Investment Banking';
        }
        
        return $analysis;
    }
    
    /**
     * Get aggregated market intelligence using new XML feeds
     */
    public function get_market_intelligence($query = '', $limit = 10) {
        $aggregator = SFFC_XML_Feed_Aggregator::get_instance();
        
        // If query provided, get matching feeds
        if (!empty($query)) {
            $feed_names = $aggregator->get_feeds_for_query($query);
        } else {
            // Default feeds for general market intelligence - now includes all 20 sources
            $feed_names = array(
                'pe_deals', 'pehub', 'pitchbook', 'pe_news',
                'bloomberg', 'marketwatch', 'mergermarket',
                'global_capital', 'economist', 'fx_street'
            );
        }
        
        // Get aggregated items
        // Note: aggregate_feeds expects ($query, $limit) not feed names
        $items = $aggregator->aggregate_feeds($query, $limit);
        
        // Format for response
        $intelligence = array(
            'timestamp' => current_time('mysql'),
            'query' => $query,
            'items' => array_slice($items, 0, $limit),
            'total' => count($items)
        );
        
        return $intelligence;
    }
    
    /**
     * Get aggregated market intelligence (legacy method)
     */
    public function get_market_intelligence_legacy() {
        $intelligence = array(
            'timestamp' => current_time('mysql'),
            'markets' => array(),
            'top_stories' => array(),
            'by_region' => array(),
            'by_sector' => array()
        );
        
        // Fetch from multiple sources
        $sources = array('bloomberg', 'ft', 'wsj');
        
        foreach ($sources as $source) {
            $feed_data = $this->fetch_feed($source);
            if ($feed_data && !empty($feed_data['items'])) {
                // Add to intelligence
                foreach ($feed_data['items'] as $item) {
                    // Categorize by region
                    $region = $feed_data['source']['region'];
                    if (!isset($intelligence['by_region'][$region])) {
                        $intelligence['by_region'][$region] = array();
                    }
                    $intelligence['by_region'][$region][] = $item;
                    
                    // Add to top stories (limit to 5)
                    if (count($intelligence['top_stories']) < 5) {
                        $intelligence['top_stories'][] = $item;
                    }
                }
            }
        }
        
        return $intelligence;
    }
    
    /**
     * Update all feeds (cron job)
     */
    public function update_all_feeds() {
        foreach (array_keys($this->feeds) as $feed_key) {
            $this->fetch_feed($feed_key);
            // Small delay between feeds
            sleep(1);
        }
    }
    
    /**
     * Search feeds for specific topics
     */
    public function search_feeds($query, $filters = array()) {
        $results = array();
        $query_lower = strtolower($query);
        
        // Search through cached feeds
        foreach (array_keys($this->feeds) as $feed_key) {
            $feed_data = $this->fetch_feed($feed_key);
            
            if ($feed_data && !empty($feed_data['items'])) {
                foreach ($feed_data['items'] as $item) {
                    $content = strtolower($item['title'] . ' ' . $item['description']);
                    
                    if (strpos($content, $query_lower) !== false) {
                        // Apply filters
                        if (!empty($filters['region']) && $item['region'] !== $filters['region']) {
                            continue;
                        }
                        
                        if (!empty($filters['sector']) && 
                            (!isset($item['why_analysis']['sector']) || 
                             $item['why_analysis']['sector'] !== $filters['sector'])) {
                            continue;
                        }
                        
                        $results[] = $item;
                    }
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Get trending topics based on frequency
     */
    public function get_trending_topics() {
        $word_frequency = array();
        $stop_words = array('the', 'and', 'for', 'are', 'with', 'has', 'was', 'been', 'have', 'had');
        
        // Analyze all feeds
        foreach (array_keys($this->feeds) as $feed_key) {
            $feed_data = $this->fetch_feed($feed_key);
            
            if ($feed_data && !empty($feed_data['items'])) {
                foreach ($feed_data['items'] as $item) {
                    $words = str_word_count(strtolower($item['title']), 1);
                    
                    foreach ($words as $word) {
                        if (strlen($word) > 3 && !in_array($word, $stop_words)) {
                            if (!isset($word_frequency[$word])) {
                                $word_frequency[$word] = 0;
                            }
                            $word_frequency[$word]++;
                        }
                    }
                }
            }
        }
        
        // Sort by frequency
        arsort($word_frequency);
        
        // Return top 10 trending topics
        return array_slice($word_frequency, 0, 10, true);
    }
}