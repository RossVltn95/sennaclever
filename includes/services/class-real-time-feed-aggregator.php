<?php
/**
 * Real-Time Feed Aggregator Service
 * Phase 1: Fetches and processes XML/RSS feeds from financial sources
 * 
 * @package SennaCareers
 * @since 6.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Real_Time_Feed_Aggregator {
    
    private static $instance = null;
    private $cache_duration = 900; // 15 minutes in seconds
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Schedule cron for feed updates
        add_action('sffc_update_feeds', array($this, 'update_all_feeds'));
        
        if (!wp_next_scheduled('sffc_update_feeds')) {
            wp_schedule_event(time(), 'sffc_fifteen_minutes', 'sffc_update_feeds');
        }
    }
    
    /**
     * Register custom cron interval
     */
    public static function add_cron_intervals($schedules) {
        $schedules['sffc_fifteen_minutes'] = array(
            'interval' => 900,
            'display' => 'Every 15 Minutes'
        );
        
        $schedules['sffc_four_hours'] = array(
            'interval' => 14400,
            'display' => 'Every 4 Hours'
        );
        
        // Expert Chat cron intervals
        $schedules['sffc_every_minute'] = array(
            'interval' => 60,
            'display' => 'Every Minute'
        );
        
        $schedules['sffc_every_five_minutes'] = array(
            'interval' => 300,
            'display' => 'Every 5 Minutes'
        );
        
        return $schedules;
    }
    
    /**
     * Update all feeds based on priority
     */
    public function update_all_feeds() {
        global $wpdb;
        
        $table_feed_status = $wpdb->prefix . 'sffc_feed_status';
        
        // Get feeds that need updating
        $feeds = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_feed_status 
             WHERE next_fetch <= %s 
             AND fetch_status != 'processing' 
             ORDER BY priority ASC, last_fetch ASC 
             LIMIT 10",
            current_time('mysql')
        ));
        
        foreach ($feeds as $feed) {
            $this->process_feed($feed);
        }
    }
    
    /**
     * Process individual feed
     */
    private function process_feed($feed) {
        global $wpdb;
        
        $table_feed_status = $wpdb->prefix . 'sffc_feed_status';
        
        // Mark as processing
        $wpdb->update(
            $table_feed_status,
            array('fetch_status' => 'processing'),
            array('id' => $feed->id),
            array('%s'),
            array('%d')
        );
        
        // Fetch feed content
        $response = wp_remote_get($feed->feed_url, array(
            'timeout' => 15,
            'headers' => array(
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url')
            )
        ));
        
        if (is_wp_error($response)) {
            // Update error status
            $wpdb->update(
                $table_feed_status,
                array(
                    'fetch_status' => 'error',
                    'error_message' => $response->get_error_message(),
                    'next_fetch' => date('Y-m-d H:i:s', strtotime('+1 hour'))
                ),
                array('id' => $feed->id),
                array('%s', '%s', '%s'),
                array('%d')
            );
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        
        // Parse XML/RSS
        $items = $this->parse_feed_content($body, $feed->feed_name);
        
        if (!empty($items)) {
            // Process based on feed type
            if (strpos($feed->feed_name, 'market') !== false || strpos($feed->feed_name, 'yahoo') !== false) {
                $this->process_market_data($items, $feed->feed_name);
            } else {
                $this->process_news_items($items, $feed->feed_name);
            }
            
            // Update success status
            $wpdb->update(
                $table_feed_status,
                array(
                    'fetch_status' => 'success',
                    'last_fetch' => current_time('mysql'),
                    'next_fetch' => date('Y-m-d H:i:s', strtotime('+15 minutes')),
                    'items_processed' => count($items),
                    'error_message' => null
                ),
                array('id' => $feed->id),
                array('%s', '%s', '%s', '%d', '%s'),
                array('%d')
            );
        } else {
            // No items found
            $wpdb->update(
                $table_feed_status,
                array(
                    'fetch_status' => 'empty',
                    'next_fetch' => date('Y-m-d H:i:s', strtotime('+30 minutes'))
                ),
                array('id' => $feed->id),
                array('%s', '%s'),
                array('%d')
            );
        }
    }
    
    /**
     * Parse feed content
     */
    private function parse_feed_content($xml_content, $feed_name) {
        $items = array();
        
        // Suppress XML errors
        libxml_use_internal_errors(true);
        
        try {
            $xml = simplexml_load_string($xml_content);
            
            if ($xml === false) {
                return $items;
            }
            
            // Handle RSS format
            if (isset($xml->channel->item)) {
                foreach ($xml->channel->item as $item) {
                    $items[] = array(
                        'title' => (string) $item->title,
                        'description' => (string) $item->description,
                        'link' => (string) $item->link,
                        'pubDate' => (string) $item->pubDate,
                        'source' => $feed_name
                    );
                }
            }
            // Handle Atom format
            elseif (isset($xml->entry)) {
                foreach ($xml->entry as $entry) {
                    $items[] = array(
                        'title' => (string) $entry->title,
                        'description' => (string) $entry->summary,
                        'link' => (string) $entry->link['href'],
                        'pubDate' => (string) $entry->updated,
                        'source' => $feed_name
                    );
                }
            }
        } catch (Exception $e) {
            error_log('SFFC Feed Parse Error: ' . $e->getMessage());
        }
        
        return array_slice($items, 0, 20); // Limit to 20 items per feed
    }
    
    /**
     * Process market data items
     */
    private function process_market_data($items, $source) {
        global $wpdb;
        
        $table_market_cache = $wpdb->prefix . 'sffc_market_cache';
        
        foreach ($items as $item) {
            // Extract market data from title/description
            $data = $this->extract_market_metrics($item);
            
            if (!empty($data['symbol'])) {
                // Insert or update market data
                $wpdb->replace(
                    $table_market_cache,
                    array(
                        'data_type' => $data['type'],
                        'symbol' => $data['symbol'],
                        'name' => $data['name'],
                        'value' => $data['value'],
                        'change_percent' => $data['change_percent'],
                        'change_amount' => $data['change_amount'],
                        'volume' => $data['volume'],
                        'timestamp_updated' => current_time('mysql'),
                        'source' => $source
                    ),
                    array('%s', '%s', '%s', '%f', '%f', '%f', '%d', '%s', '%s')
                );
            }
        }
    }
    
    /**
     * Process news items
     */
    private function process_news_items($items, $source) {
        global $wpdb;
        
        $table_news_cache = $wpdb->prefix . 'sffc_news_cache';
        
        foreach ($items as $item) {
            // Check if already exists (by URL)
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_news_cache WHERE url = %s",
                $item['link']
            ));
            
            if (!$exists) {
                // Extract entities and analyze sentiment
                $analysis = $this->analyze_news_item($item);
                
                $wpdb->insert(
                    $table_news_cache,
                    array(
                        'headline' => $item['title'],
                        'summary' => wp_trim_words($item['description'], 50),
                        'source' => $source,
                        'url' => $item['link'],
                        'published_date' => date('Y-m-d H:i:s', strtotime($item['pubDate'])),
                        'category' => $analysis['category'],
                        'entities' => json_encode($analysis['entities']),
                        'sentiment_score' => $analysis['sentiment'],
                        'importance_score' => $analysis['importance']
                    ),
                    array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%d')
                );
            }
        }
    }
    
    /**
     * Extract market metrics from text
     */
    private function extract_market_metrics($item) {
        $data = array(
            'type' => 'index',
            'symbol' => '',
            'name' => '',
            'value' => 0,
            'change_percent' => 0,
            'change_amount' => 0,
            'volume' => 0
        );
        
        $text = $item['title'] . ' ' . $item['description'];
        
        // Extract index symbols
        if (preg_match('/S&P\s*500|SPX/i', $text)) {
            $data['symbol'] = 'SPX';
            $data['name'] = 'S&P 500';
        } elseif (preg_match('/Nasdaq|COMP/i', $text)) {
            $data['symbol'] = 'COMP';
            $data['name'] = 'Nasdaq Composite';
        } elseif (preg_match('/Dow\s*Jones|DJIA/i', $text)) {
            $data['symbol'] = 'DJI';
            $data['name'] = 'Dow Jones Industrial';
        }
        
        // Extract numerical values
        if (preg_match('/(\d+[\d,]*\.?\d*)\s*(?:points?|pts?)/i', $text, $matches)) {
            $data['value'] = floatval(str_replace(',', '', $matches[1]));
        }
        
        if (preg_match('/([\+\-]?\d+\.?\d*)\s*%/', $text, $matches)) {
            $data['change_percent'] = floatval($matches[1]);
        }
        
        return $data;
    }
    
    /**
     * Analyze news item for entities and sentiment
     */
    private function analyze_news_item($item) {
        $analysis = array(
            'category' => 'general',
            'entities' => array(),
            'sentiment' => 0,
            'importance' => 5
        );
        
        $text = strtolower($item['title'] . ' ' . $item['description']);
        
        // Detect category
        if (preg_match('/private equity|buyout|acquisition|lbo/i', $text)) {
            $analysis['category'] = 'pe_deals';
        } elseif (preg_match('/ipo|public offering|listing/i', $text)) {
            $analysis['category'] = 'ipo';
        } elseif (preg_match('/earnings|revenue|profit|loss/i', $text)) {
            $analysis['category'] = 'earnings';
        } elseif (preg_match('/fed|federal reserve|interest rate|monetary policy/i', $text)) {
            $analysis['category'] = 'monetary_policy';
        } elseif (preg_match('/merger|acquisition|m&a|deal/i', $text)) {
            $analysis['category'] = 'ma';
        }
        
        // Extract entities (companies)
        $companies = array('Blackstone', 'KKR', 'Apollo', 'Carlyle', 'TPG', 'Goldman Sachs', 
                          'Morgan Stanley', 'JPMorgan', 'Bank of America', 'Citigroup');
        
        foreach ($companies as $company) {
            if (stripos($text, strtolower($company)) !== false) {
                $analysis['entities'][] = $company;
            }
        }
        
        // Simple sentiment analysis
        $positive_words = array('surge', 'gain', 'rise', 'up', 'positive', 'growth', 'profit', 'beat', 'exceed');
        $negative_words = array('fall', 'drop', 'decline', 'down', 'negative', 'loss', 'miss', 'below');
        
        $positive_count = 0;
        $negative_count = 0;
        
        foreach ($positive_words as $word) {
            $positive_count += substr_count($text, $word);
        }
        
        foreach ($negative_words as $word) {
            $negative_count += substr_count($text, $word);
        }
        
        if ($positive_count > $negative_count) {
            $analysis['sentiment'] = min(1, $positive_count * 0.2);
        } elseif ($negative_count > $positive_count) {
            $analysis['sentiment'] = max(-1, -$negative_count * 0.2);
        }
        
        // Importance based on category
        if (in_array($analysis['category'], array('monetary_policy', 'pe_deals', 'ma'))) {
            $analysis['importance'] = 8;
        } elseif ($analysis['category'] === 'earnings' && !empty($analysis['entities'])) {
            $analysis['importance'] = 7;
        }
        
        return $analysis;
    }
    
    /**
     * Get latest market data
     */
    public function get_market_snapshot() {
        global $wpdb;
        
        $table_market_cache = $wpdb->prefix . 'sffc_market_cache';
        
        return $wpdb->get_results(
            "SELECT * FROM $table_market_cache 
             WHERE data_type = 'index' 
             AND timestamp_updated > DATE_SUB(NOW(), INTERVAL 1 HOUR)
             ORDER BY timestamp_updated DESC"
        );
    }
    
    /**
     * Get latest news
     */
    public function get_latest_news($limit = 10, $category = null) {
        global $wpdb;
        
        $table_news_cache = $wpdb->prefix . 'sffc_news_cache';
        
        $query = "SELECT * FROM $table_news_cache WHERE 1=1";
        
        if ($category) {
            $query .= $wpdb->prepare(" AND category = %s", $category);
        }
        
        $query .= " ORDER BY published_date DESC LIMIT $limit";
        
        return $wpdb->get_results($query);
    }
}