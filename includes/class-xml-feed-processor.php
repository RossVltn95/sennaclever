<?php

/**
 * XML Feed Processor - Phase 3
 * Intelligent feed processing with real-time data extraction
 * 
 * @package SennaCareers
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_XML_Feed_Processor
{

    private static $instance = null;
    private $feed_sources = array();
    private $cache_manager;
    private $parser_strategies = array();

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->initialize_feed_sources();
        $this->initialize_parser_strategies();
        $this->cache_manager = SFFC_Data_Cache_Manager::get_instance();
    }

    /**
     * Initialize feed sources with intelligent categorization
     */
    private function initialize_feed_sources()
    {
        $this->feed_sources = array(
            'market_data' => array(
                array(
                    'name' => 'Bloomberg Markets',
                    'url' => 'https://feeds.bloomberg.com/markets/news.rss',
                    'type' => 'rss',
                    'priority' => 1,
                    'cache_duration' => 60, // 1 minute for market data
                    'fields' => array('title', 'description', 'pubDate', 'link')
                ),
                array(
                    'name' => 'Reuters Business',
                    'url' => 'https://news.google.com/rss/topics/CAAqJggKIiBDQkFTRWdvSUwyMHZNRGx6TVdZU0FtVnVHZ0pWVXlnQVAB',
                    'type' => 'rss',
                    'priority' => 2,
                    'cache_duration' => 60
                ),
                array(
                    'name' => 'Yahoo Finance',
                    'url' => 'https://finance.yahoo.com/rss/',
                    'type' => 'rss',
                    'priority' => 3,
                    'cache_duration' => 60
                )
            ),
            'pe_deals' => array(
                array(
                    'name' => 'PE Hub Wire',
                    'url' => 'https://www.pehub.com/wire/feed/',
                    'type' => 'rss',
                    'priority' => 1,
                    'cache_duration' => 300, // 5 minutes for deal data
                    'fields' => array('title', 'description', 'pubDate', 'category')
                ),
                array(
                    'name' => 'Pitchbook News',
                    'url' => 'https://pitchbook.com/rss/news',
                    'type' => 'rss',
                    'priority' => 2,
                    'cache_duration' => 300
                )
            ),
            'company_news' => array(
                array(
                    'name' => 'PR Newswire Finance',
                    'url' => 'https://www.prnewswire.com/rss/financial-services-latest-news.rss',
                    'type' => 'rss',
                    'priority' => 1,
                    'cache_duration' => 900, // 15 minutes for PR
                    'fields' => array('title', 'description', 'pubDate', 'company')
                )
            ),
            'economic_data' => array(
                array(
                    'name' => 'Fed Economic Data',
                    'url' => 'https://fred.stlouisfed.org/feeds/series.rss',
                    'type' => 'xml',
                    'priority' => 1,
                    'cache_duration' => 3600, // 1 hour for economic data
                    'namespace' => 'fred'
                )
            )
        );
    }

    /**
     * Initialize parsing strategies for different feed types
     */
    private function initialize_parser_strategies()
    {
        $this->parser_strategies = array(
            'rss' => array($this, 'parse_rss_feed'),
            'xml' => array($this, 'parse_xml_feed'),
            'json' => array($this, 'parse_json_feed'),
            'atom' => array($this, 'parse_atom_feed')
        );
    }

    /**
     * Get live data for a specific query
     */
    public function get_live_data($query_type, $entities = array(), $options = array())
    {
        $relevant_feeds = $this->determine_relevant_feeds($query_type, $entities);
        $aggregated_data = array();

        foreach ($relevant_feeds as $feed_category => $feeds) {
            foreach ($feeds as $feed) {
                $data = $this->fetch_and_parse_feed($feed, $entities, $options);
                if ($data && !empty($data['items'])) {
                    $aggregated_data[$feed_category][] = $data;
                }
            }
        }

        return $this->process_aggregated_data($aggregated_data, $query_type, $entities);
    }

    /**
     * Determine which feeds are relevant for the query
     */
    private function determine_relevant_feeds($query_type, $entities)
    {
        $relevant = array();

        // Map query types to feed categories
        $type_mapping = array(
            'stock_price' => array('market_data'),
            'company_news' => array('company_news', 'market_data'),
            'pe_deals' => array('pe_deals'),
            'market_analysis' => array('market_data', 'economic_data'),
            'earnings' => array('company_news', 'market_data'),
            'sector_news' => array('market_data', 'company_news')
        );

        if (isset($type_mapping[$query_type])) {
            foreach ($type_mapping[$query_type] as $category) {
                if (isset($this->feed_sources[$category])) {
                    $relevant[$category] = $this->feed_sources[$category];
                }
            }
        }

        // Add entity-specific feeds
        if (!empty($entities['companies'])) {
            $relevant['company_news'] = $this->feed_sources['company_news'];
        }

        return $relevant;
    }

    /**
     * Fetch and parse a feed with caching
     */
    private function fetch_and_parse_feed($feed_config, $entities, $options)
    {
        $cache_key = 'feed_' . md5($feed_config['url'] . serialize($entities));

        // Check cache first
        $cached = $this->cache_manager->get($cache_key);
        if ($cached !== false && !isset($options['force_refresh'])) {
            return $cached;
        }

        // Fetch fresh data
        $response = wp_remote_get($feed_config['url'], array(
            'timeout' => 10,
            'user-agent' => 'MENA Careers Finance Career Plugin/3.0'
        ));

        if (is_wp_error($response)) {
            return $this->handle_feed_error($feed_config, $response);
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            return false;
        }

        // Parse based on feed type
        $parser = $this->parser_strategies[$feed_config['type']] ?? null;
        if (!$parser) {
            return false;
        }

        $parsed_data = call_user_func($parser, $body, $feed_config, $entities);

        // Cache the parsed data
        if ($parsed_data && !empty($parsed_data['items'])) {
            $this->cache_manager->set($cache_key, $parsed_data, $feed_config['cache_duration']);
        }

        return $parsed_data;
    }

    /**
     * Parse RSS feed
     */
    private function parse_rss_feed($xml_content, $feed_config, $entities)
    {
        try {
            $xml = simplexml_load_string($xml_content, 'SimpleXMLElement', LIBXML_NOCDATA);
            if (!$xml || !isset($xml->channel->item)) {
                return false;
            }

            $items = array();
            $count = 0;

            foreach ($xml->channel->item as $item) {
                if ($count >= 10) break; // Limit to 10 items per feed

                $parsed_item = $this->extract_rss_item_data($item, $feed_config);

                // Filter by relevance to entities
                if ($this->is_item_relevant($parsed_item, $entities)) {
                    $parsed_item['relevance_score'] = $this->calculate_relevance_score($parsed_item, $entities);
                    $items[] = $parsed_item;
                    $count++;
                }
            }

            // Sort by relevance
            usort($items, function ($a, $b) {
                return $b['relevance_score'] - $a['relevance_score'];
            });

            return array(
                'source' => $feed_config['name'],
                'timestamp' => time(),
                'items' => $items
            );
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Extract data from RSS item
     */
    private function extract_rss_item_data($item, $feed_config)
    {
        $data = array(
            'title' => (string) $item->title,
            'description' => strip_tags((string) $item->description),
            'link' => (string) $item->link,
            'pubDate' => strtotime((string) $item->pubDate),
            'source' => $feed_config['name']
        );

        // Extract additional fields if specified
        if (isset($feed_config['fields'])) {
            foreach ($feed_config['fields'] as $field) {
                if (isset($item->$field) && !isset($data[$field])) {
                    $data[$field] = (string) $item->$field;
                }
            }
        }

        // Extract price data if present (with multiple currency symbols)
        if (preg_match('/[\$£€]([0-9,]+\.?[0-9]*)/', $data['description'], $matches)) {
            $data['price_mentioned'] = str_replace(',', '', $matches[1]);
        }

        // Extract percentage changes
        if (preg_match('/([+-]?[0-9]+\.?[0-9]*)%/', $data['description'], $matches)) {
            $data['percentage_change'] = $matches[1];
        }

        return $data;
    }

    /**
     * Check if item is relevant to entities
     */
    private function is_item_relevant($item, $entities)
    {
        if (empty($entities)) {
            return true; // No filtering if no entities
        }

        $text = strtolower($item['title'] . ' ' . $item['description']);

        // Check companies
        if (!empty($entities['companies'])) {
            foreach ($entities['companies'] as $company) {
                if (
                    stripos($text, $company['name']) !== false ||
                    stripos($text, $company['ticker']) !== false
                ) {
                    return true;
                }
            }
        }

        // Check financial terms
        if (!empty($entities['financial_terms'])) {
            foreach ($entities['financial_terms'] as $term) {
                if (stripos($text, $term['term']) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Calculate relevance score
     */
    private function calculate_relevance_score($item, $entities)
    {
        $score = 0;
        $text = strtolower($item['title'] . ' ' . $item['description']);

        // Company mentions (highest weight)
        if (!empty($entities['companies'])) {
            foreach ($entities['companies'] as $company) {
                if (!empty($company['name'])) {
                    $score += substr_count($text, strtolower($company['name'])) * 10;
                }
                if (!empty($company['ticker'])) {
                    $score += substr_count($text, strtolower($company['ticker'])) * 8;
                }
            }
        }

        // Recency bonus
        $age_hours = (time() - $item['pubDate']) / 3600;
        if ($age_hours < 1) {
            $score += 20;
        } elseif ($age_hours < 6) {
            $score += 10;
        } elseif ($age_hours < 24) {
            $score += 5;
        }

        // Price data bonus
        if (isset($item['price_mentioned'])) {
            $score += 5;
        }

        return $score;
    }

    /**
     * Process aggregated data from multiple feeds
     */
    private function process_aggregated_data($aggregated_data, $query_type, $entities)
    {
        $processed = array(
            'query_type' => $query_type,
            'timestamp' => time(),
            'data_points' => array(),
            'headlines' => array(),
            'insights' => array()
        );

        // Extract key data points
        foreach ($aggregated_data as $category => $feeds) {
            foreach ($feeds as $feed_data) {
                foreach ($feed_data['items'] as $item) {
                    // Headlines
                    if (isset($item['title'])) {
                        $processed['headlines'][] = array(
                            'title' => $item['title'],
                            'time' => $this->format_time_ago($item['pubDate']),
                            'source' => $item['source'],
                            'link' => $item['link'] ?? null,
                            'description' => isset($item['description']) ? wp_trim_words($item['description'], 40) : '',
                            'timestamp' => $item['pubDate'] ?? null,
                        );
                    }

                    // Extract data points
                    if (isset($item['price_mentioned'])) {
                        $processed['data_points'][] = array(
                            'type' => 'price',
                            'value' => $item['price_mentioned'],
                            'context' => substr($item['description'], 0, 100)
                        );
                    }

                    if (isset($item['percentage_change'])) {
                        $processed['data_points'][] = array(
                            'type' => 'change',
                            'value' => $item['percentage_change'],
                            'context' => substr($item['description'], 0, 100)
                        );
                    }
                }
            }
        }

        // Generate insights
        $processed['insights'] = $this->generate_insights($processed, $entities);

        // Limit results
        $processed['headlines'] = array_slice($processed['headlines'], 0, 5);
        $processed['data_points'] = array_slice($processed['data_points'], 0, 10);

        return $processed;
    }

    /**
     * Generate insights from processed data
     */
    private function generate_insights($processed_data, $entities)
    {
        $insights = array();

        // Market sentiment
        if (!empty($processed_data['data_points'])) {
            $positive = 0;
            $negative = 0;

            foreach ($processed_data['data_points'] as $point) {
                if ($point['type'] === 'change' && is_numeric($point['value'])) {
                    if ($point['value'] > 0) $positive++;
                    else $negative++;
                }
            }

            if ($positive > $negative) {
                $insights[] = "Market sentiment appears positive with more gainers than losers";
            } elseif ($negative > $positive) {
                $insights[] = "Market showing bearish sentiment with more declines";
            }
        }

        // News volume
        if (count($processed_data['headlines']) > 3) {
            $insights[] = "High news volume indicates significant market activity";
        }

        return $insights;
    }

    /**
     * Format time ago
     */
    private function format_time_ago($timestamp)
    {
        $diff = time() - $timestamp;

        if ($diff < 60) {
            return 'just now';
        } elseif ($diff < 3600) {
            return floor($diff / 60) . ' min ago';
        } elseif ($diff < 86400) {
            return floor($diff / 3600) . ' hours ago';
        } else {
            return floor($diff / 86400) . ' days ago';
        }
    }

    /**
     * Parse XML feed (for custom structured data)
     */
    private function parse_xml_feed($xml_content, $feed_config, $entities)
    {
        // Implementation for custom XML feeds
        return $this->parse_rss_feed($xml_content, $feed_config, $entities);
    }

    /**
     * Parse JSON feed
     */
    private function parse_json_feed($json_content, $feed_config, $entities)
    {
        $data = json_decode($json_content, true);
        if (!$data) {
            return false;
        }

        // Process JSON structure based on feed config
        return array(
            'source' => $feed_config['name'],
            'timestamp' => time(),
            'items' => array()
        );
    }

    /**
     * Parse Atom feed
     */
    private function parse_atom_feed($xml_content, $feed_config, $entities)
    {
        // Similar to RSS but with Atom structure
        return $this->parse_rss_feed($xml_content, $feed_config, $entities);
    }

    /**
     * Handle feed errors gracefully
     */
    private function handle_feed_error($feed_config, $error)
    {
        // Log error
        error_log('Feed error for ' . $feed_config['name'] . ': ' . $error->get_error_message());

        // Return cached data if available
        $cache_key = 'feed_fallback_' . md5($feed_config['url']);
        return $this->cache_manager->get($cache_key);
    }

    /**
     * Get stock price from feeds
     */
    public function get_stock_price_from_feeds($ticker)
    {
        $feeds = $this->get_live_data('stock_price', array(
            'tickers' => array($ticker)
        ));

        if (!empty($feeds['data_points'])) {
            foreach ($feeds['data_points'] as $point) {
                if ($point['type'] === 'price') {
                    return array(
                        'price' => $point['value'],
                        'source' => 'live_feed',
                        'timestamp' => time()
                    );
                }
            }
        }

        return null;
    }

    /**
     * Get latest news for company
     */
    public function get_company_news_from_feeds($company_name)
    {
        $feeds = $this->get_live_data('company_news', array(
            'companies' => array(
                array('name' => $company_name)
            )
        ));

        return $feeds['headlines'] ?? array();
    }
}
