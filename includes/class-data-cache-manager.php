<?php
/**
 * Data Cache Manager
 * Phase 1: Manages caching and retrieval of real-time financial data
 * 
 * @package SennaCareers
 * @since 6.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Data_Cache_Manager {
    
    private static $instance = null;
    private $feed_aggregator;
    private $cache_expiry;
    private $test_cache = array();
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->cache_expiry = 300; // 5 minutes default
        
        // Skip initialization in test mode
        if (defined('SFFC_TEST_MODE')) {
            return;
        }
        
        // Load feed aggregator
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/services/class-real-time-feed-aggregator.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/services/class-real-time-feed-aggregator.php';
            $this->feed_aggregator = SFFC_Real_Time_Feed_Aggregator::get_instance();
        }
        
        // Schedule analysis generation
        add_action('sffc_generate_analysis', array($this, 'generate_pre_computed_analysis'));
        
        if (!wp_next_scheduled('sffc_generate_analysis')) {
            wp_schedule_event(time(), 'sffc_four_hours', 'sffc_generate_analysis');
        }
    }
    
    /**
     * Get current market data with caching
     */
    public function get_market_data($type = 'all') {
        global $wpdb;
        
        $table_market_cache = $wpdb->prefix . 'sffc_market_cache';
        $cache_key = 'sffc_market_data_' . $type;
        
        // Check transient cache first (1 minute cache)
        $cached_data = get_transient($cache_key);
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        // Query database
        if ($type === 'all') {
            $data = $wpdb->get_results(
                "SELECT * FROM $table_market_cache 
                 WHERE timestamp_updated > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                 ORDER BY data_type, symbol"
            );
        } else {
            $data = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_market_cache 
                 WHERE data_type = %s 
                 AND timestamp_updated > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                 ORDER BY symbol",
                $type
            ));
        }
        
        // Transform data for easier use
        $formatted_data = $this->format_market_data($data);
        
        // Cache for 1 minute
        set_transient($cache_key, $formatted_data, 60);
        
        return $formatted_data;
    }
    
    /**
     * Format market data for response generation
     */
    private function format_market_data($raw_data) {
        $formatted = array(
            'indices' => array(),
            'sectors' => array(),
            'commodities' => array(),
            'currencies' => array(),
            'summary' => array(
                'trend' => 'neutral',
                'volatility' => 'normal',
                'breadth' => 'mixed'
            )
        );
        
        $positive_count = 0;
        $negative_count = 0;
        
        foreach ($raw_data as $item) {
            $formatted_item = array(
                'symbol' => $item->symbol,
                'name' => $item->name,
                'value' => floatval($item->value),
                'change' => floatval($item->change_amount),
                'change_percent' => floatval($item->change_percent),
                'volume' => intval($item->volume),
                'updated' => $item->timestamp_updated
            );
            
            // Categorize by type
            switch ($item->data_type) {
                case 'index':
                    $formatted['indices'][] = $formatted_item;
                    break;
                case 'sector':
                    $formatted['sectors'][] = $formatted_item;
                    break;
                case 'commodity':
                    $formatted['commodities'][] = $formatted_item;
                    break;
                case 'currency':
                    $formatted['currencies'][] = $formatted_item;
                    break;
            }
            
            // Track overall trend
            if ($item->change_percent > 0) {
                $positive_count++;
            } elseif ($item->change_percent < 0) {
                $negative_count++;
            }
        }
        
        // Determine overall trend
        if ($positive_count > $negative_count * 1.5) {
            $formatted['summary']['trend'] = 'bullish';
        } elseif ($negative_count > $positive_count * 1.5) {
            $formatted['summary']['trend'] = 'bearish';
        }
        
        // Check volatility (if VIX data available)
        foreach ($formatted['indices'] as $index) {
            if ($index['symbol'] === 'VIX') {
                if ($index['value'] > 25) {
                    $formatted['summary']['volatility'] = 'high';
                } elseif ($index['value'] < 15) {
                    $formatted['summary']['volatility'] = 'low';
                }
                break;
            }
        }
        
        return $formatted;
    }
    
    /**
     * Get recent news with intelligent filtering
     */
    public function get_relevant_news($context = array()) {
        global $wpdb;
        
        $table_news_cache = $wpdb->prefix . 'sffc_news_cache';
        $limit = isset($context['limit']) ? intval($context['limit']) : 5;
        
        // Build query based on context
        $query = "SELECT * FROM $table_news_cache WHERE 1=1";
        
        // Filter by category if specified
        if (!empty($context['category'])) {
            $query .= $wpdb->prepare(" AND category = %s", $context['category']);
        }
        
        // Filter by entities if specified
        if (!empty($context['entities'])) {
            $entity_conditions = array();
            foreach ($context['entities'] as $entity) {
                $entity_conditions[] = $wpdb->prepare("entities LIKE %s", '%' . $entity . '%');
            }
            $query .= " AND (" . implode(" OR ", $entity_conditions) . ")";
        }
        
        // Filter by importance
        if (!empty($context['min_importance'])) {
            $query .= $wpdb->prepare(" AND importance_score >= %d", $context['min_importance']);
        }
        
        // Order by relevance and recency
        $query .= " ORDER BY importance_score DESC, published_date DESC LIMIT $limit";
        
        $news = $wpdb->get_results($query);
        
        // Format news items
        return $this->format_news_items($news);
    }
    
    /**
     * Format news items for response
     */
    private function format_news_items($news_items) {
        $formatted = array();
        
        foreach ($news_items as $item) {
            $formatted[] = array(
                'headline' => $item->headline,
                'summary' => $item->summary,
                'source' => $item->source,
                'url' => $item->url,
                'published' => human_time_diff(strtotime($item->published_date), current_time('timestamp')) . ' ago',
                'category' => $item->category,
                'entities' => json_decode($item->entities, true),
                'sentiment' => $this->interpret_sentiment($item->sentiment_score),
                'importance' => $item->importance_score
            );
        }
        
        return $formatted;
    }
    
    /**
     * Interpret sentiment score
     */
    private function interpret_sentiment($score) {
        if ($score > 0.3) {
            return 'positive';
        } elseif ($score < -0.3) {
            return 'negative';
        } else {
            return 'neutral';
        }
    }
    
    /**
     * Generate pre-computed analysis
     */
    public function generate_pre_computed_analysis() {
        global $wpdb;
        
        $table_analysis_cache = $wpdb->prefix . 'sffc_analysis_cache';
        
        // Generate market summary
        $market_data = $this->get_market_data('all');
        $market_analysis = $this->analyze_market_conditions($market_data);
        
        $wpdb->replace(
            $table_analysis_cache,
            array(
                'analysis_type' => 'market_summary',
                'analysis_key' => 'daily',
                'analysis_content' => $market_analysis['summary'],
                'supporting_data' => json_encode($market_analysis['data']),
                'confidence_score' => $market_analysis['confidence'],
                'generated_at' => current_time('mysql'),
                'expires_at' => date('Y-m-d H:i:s', strtotime('+4 hours'))
            ),
            array('%s', '%s', '%s', '%s', '%f', '%s', '%s')
        );
        
        // Generate sector analysis
        if (!empty($market_data['sectors'])) {
            $sector_analysis = $this->analyze_sectors($market_data['sectors']);
            
            $wpdb->replace(
                $table_analysis_cache,
                array(
                    'analysis_type' => 'sector_analysis',
                    'analysis_key' => 'rotation',
                    'analysis_content' => $sector_analysis['summary'],
                    'supporting_data' => json_encode($sector_analysis['data']),
                    'confidence_score' => $sector_analysis['confidence'],
                    'generated_at' => current_time('mysql'),
                    'expires_at' => date('Y-m-d H:i:s', strtotime('+4 hours'))
                ),
                array('%s', '%s', '%s', '%s', '%f', '%s', '%s')
            );
        }
        
        // Generate PE/Finance opportunities
        $opportunities = $this->identify_opportunities();
        
        $wpdb->replace(
            $table_analysis_cache,
            array(
                'analysis_type' => 'opportunities',
                'analysis_key' => 'current',
                'analysis_content' => $opportunities['summary'],
                'supporting_data' => json_encode($opportunities['data']),
                'confidence_score' => $opportunities['confidence'],
                'generated_at' => current_time('mysql'),
                'expires_at' => date('Y-m-d H:i:s', strtotime('+4 hours'))
            ),
            array('%s', '%s', '%s', '%s', '%f', '%s', '%s')
        );
    }
    
    /**
     * Analyze market conditions
     */
    private function analyze_market_conditions($market_data) {
        $analysis = array(
            'summary' => '',
            'data' => array(),
            'confidence' => 0.7
        );
        
        // Build summary based on actual data
        $trend = $market_data['summary']['trend'];
        $volatility = $market_data['summary']['volatility'];
        
        $summary_parts = array();
        
        // Market trend
        if ($trend === 'bullish') {
            $summary_parts[] = "Markets are showing positive momentum with broad-based gains across major indices.";
        } elseif ($trend === 'bearish') {
            $summary_parts[] = "Markets are under pressure with widespread declines across major indices.";
        } else {
            $summary_parts[] = "Markets are trading mixed with no clear directional bias.";
        }
        
        // Volatility assessment
        if ($volatility === 'high') {
            $summary_parts[] = "Elevated volatility suggests increased uncertainty and risk in the market.";
        } elseif ($volatility === 'low') {
            $summary_parts[] = "Low volatility indicates a stable market environment with reduced risk.";
        }
        
        // Index performance
        if (!empty($market_data['indices'])) {
            $sp500 = null;
            foreach ($market_data['indices'] as $index) {
                if ($index['symbol'] === 'SPX') {
                    $sp500 = $index;
                    break;
                }
            }
            
            if ($sp500) {
                $summary_parts[] = sprintf(
                    "The S&P 500 is %s %.2f%% at %.2f.",
                    $sp500['change_percent'] >= 0 ? 'up' : 'down',
                    abs($sp500['change_percent']),
                    $sp500['value']
                );
            }
        }
        
        $analysis['summary'] = implode(' ', $summary_parts);
        $analysis['data'] = $market_data;
        
        return $analysis;
    }
    
    /**
     * Analyze sector performance
     */
    private function analyze_sectors($sectors) {
        $analysis = array(
            'summary' => '',
            'data' => array(),
            'confidence' => 0.65
        );
        
        // Sort sectors by performance
        usort($sectors, function($a, $b) {
            return $b['change_percent'] <=> $a['change_percent'];
        });
        
        $top_sectors = array_slice($sectors, 0, 3);
        $bottom_sectors = array_slice($sectors, -3);
        
        $summary_parts = array();
        
        // Leading sectors
        if (!empty($top_sectors)) {
            $leaders = array_map(function($s) { return $s['name']; }, $top_sectors);
            $summary_parts[] = "Leading sectors include " . implode(', ', $leaders) . ".";
        }
        
        // Lagging sectors
        if (!empty($bottom_sectors)) {
            $laggards = array_map(function($s) { return $s['name']; }, $bottom_sectors);
            $summary_parts[] = "Underperforming sectors are " . implode(', ', $laggards) . ".";
        }
        
        // Rotation signals
        if (!empty($top_sectors) && !empty($bottom_sectors)) {
            if (strpos($top_sectors[0]['name'], 'Tech') !== false && strpos($bottom_sectors[0]['name'], 'Util') !== false) {
                $summary_parts[] = "Rotation into growth sectors suggests risk-on sentiment.";
            } elseif (strpos($top_sectors[0]['name'], 'Util') !== false && strpos($bottom_sectors[0]['name'], 'Tech') !== false) {
                $summary_parts[] = "Defensive rotation indicates risk-off positioning.";
            }
        }
        
        $analysis['summary'] = implode(' ', $summary_parts);
        $analysis['data'] = array(
            'leaders' => $top_sectors,
            'laggards' => $bottom_sectors
        );
        
        return $analysis;
    }
    
    /**
     * Identify opportunities based on market conditions
     */
    private function identify_opportunities() {
        $opportunities = array(
            'summary' => '',
            'data' => array(),
            'confidence' => 0.6
        );
        
        $market_data = $this->get_market_data('all');
        $recent_news = $this->get_relevant_news(array('min_importance' => 7, 'limit' => 5));
        
        $opp_list = array();
        
        // Check for PE opportunities
        foreach ($recent_news as $news) {
            if ($news['category'] === 'pe_deals') {
                $opp_list[] = "Recent PE activity in " . implode(', ', $news['entities']) . " indicates deal flow momentum.";
            }
        }
        
        // Market-based opportunities
        if ($market_data['summary']['volatility'] === 'high') {
            $opp_list[] = "Elevated volatility creates entry points for distressed investing.";
        }
        
        if ($market_data['summary']['trend'] === 'bullish') {
            $opp_list[] = "Positive market sentiment supports growth equity strategies.";
        }
        
        // Sector opportunities
        if (!empty($market_data['sectors'])) {
            foreach ($market_data['sectors'] as $sector) {
                if ($sector['change_percent'] < -5) {
                    $opp_list[] = "Oversold conditions in " . $sector['name'] . " may present value opportunities.";
                    break;
                }
            }
        }
        
        $opportunities['summary'] = !empty($opp_list) ? 
            implode(' ', array_slice($opp_list, 0, 3)) : 
            "Current market conditions suggest maintaining selective positioning with focus on quality assets.";
        
        $opportunities['data'] = array(
            'opportunities' => $opp_list,
            'market_context' => $market_data['summary']
        );
        
        return $opportunities;
    }
    
    /**
     * Get pre-computed analysis
     */
    public function get_analysis($type, $key = 'default') {
        global $wpdb;
        
        $table_analysis_cache = $wpdb->prefix . 'sffc_analysis_cache';
        
        $analysis = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_analysis_cache 
             WHERE analysis_type = %s 
             AND analysis_key = %s 
             AND expires_at > %s",
            $type,
            $key,
            current_time('mysql')
        ));
        
        if ($analysis) {
            return array(
                'content' => $analysis->analysis_content,
                'data' => json_decode($analysis->supporting_data, true),
                'confidence' => floatval($analysis->confidence_score),
                'generated' => $analysis->generated_at
            );
        }
        
        return null;
    }
    
    /**
     * Simple cache get method for testing
     */
    public function get($key) {
        if (defined('SFFC_TEST_MODE')) {
            return isset($this->test_cache[$key]) ? $this->test_cache[$key]['value'] : false;
        }
        return get_transient($key);
    }
    
    /**
     * Simple cache set method for testing
     */
    public function set($key, $value, $expiry = 300) {
        if (defined('SFFC_TEST_MODE')) {
            $this->test_cache[$key] = array(
                'value' => $value,
                'expiry' => time() + $expiry
            );
            return true;
        }
        return set_transient($key, $value, $expiry);
    }
}