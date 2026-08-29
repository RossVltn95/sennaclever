<?php
/**
 * Market AJAX Handlers - Handles all AJAX requests for Market Mode
 * 
 * @package SennaCareers
 * @subpackage Market
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Market_Ajax_Handlers {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Component instances
     */
    private $integration;
    private $feed_manager;
    private $why_engine;
    private $prompt_library;
    private $response_templates;
    
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
        $this->init_components();
        $this->init_hooks();
    }
    
    /**
     * Initialize components
     */
    private function init_components() {
        // Load dependencies
        require_once SFFC_PLUGIN_DIR . 'includes/class-market-mode-integration.php';
        require_once SFFC_PLUGIN_DIR . 'includes/class-market-feed-manager.php';
        require_once SFFC_PLUGIN_DIR . 'includes/class-market-why-engine.php';
        require_once SFFC_PLUGIN_DIR . 'includes/class-market-prompt-library.php';
        require_once SFFC_PLUGIN_DIR . 'includes/class-market-response-templates.php';
        
        // Initialize
        $this->integration = SFFC_Market_Mode_Integration::get_instance();
        $this->feed_manager = SFFC_Market_Feed_Manager::get_instance();
        $this->why_engine = SFFC_Market_Why_Engine::get_instance();
        $this->prompt_library = SFFC_Market_Prompt_Library::get_instance();
        $this->response_templates = SFFC_Market_Response_Templates::get_instance();
    }
    
    /**
     * Initialize AJAX hooks
     */
    private function init_hooks() {
        // Public AJAX endpoints
        add_action('wp_ajax_sffc_get_market_data', array($this, 'handle_get_market_data'));
        add_action('wp_ajax_nopriv_sffc_get_market_data', array($this, 'handle_get_market_data'));
        
        add_action('wp_ajax_sffc_market_analysis', array($this, 'handle_market_analysis'));
        add_action('wp_ajax_nopriv_sffc_market_analysis', array($this, 'handle_market_analysis'));
        
        add_action('wp_ajax_sffc_market_why', array($this, 'handle_why_analysis'));
        add_action('wp_ajax_nopriv_sffc_market_why', array($this, 'handle_why_analysis'));
        
        add_action('wp_ajax_sffc_market_compare', array($this, 'handle_comparison'));
        add_action('wp_ajax_nopriv_sffc_market_compare', array($this, 'handle_comparison'));
        
        add_action('wp_ajax_sffc_market_opportunities', array($this, 'handle_opportunities'));
        add_action('wp_ajax_nopriv_sffc_market_opportunities', array($this, 'handle_opportunities'));
        
        add_action('wp_ajax_sffc_market_education', array($this, 'handle_education'));
        add_action('wp_ajax_nopriv_sffc_market_education', array($this, 'handle_education'));
        
        // Admin AJAX endpoints
        add_action('wp_ajax_sffc_check_all_feeds', array($this, 'handle_check_all_feeds'));
        add_action('wp_ajax_sffc_force_refresh_market', array($this, 'handle_force_refresh'));
    }
    
    /**
     * Get current market data
     */
    public function handle_get_market_data() {
        check_ajax_referer('sffc_frontend_nonce', 'nonce');
        
        // Get cached market intelligence
        $market_intel = $this->feed_manager->get_market_intelligence();
        
        // Calculate market indicators
        $indicators = $this->calculate_market_indicators($market_intel);
        
        // Get trending topics
        $trending = $this->extract_trending_topics($market_intel);
        
        // Format response
        $response = array(
            'timestamp' => current_time('mysql'),
            'market_status' => $this->get_market_status(),
            'indicators' => $indicators,
            'trending' => $trending,
            'top_stories' => array_slice($market_intel['top_stories'], 0, 5),
            'summary' => $this->generate_market_summary($market_intel)
        );
        
        wp_send_json_success($response);
    }
    
    /**
     * Handle market analysis request
     */
    public function handle_market_analysis() {
        check_ajax_referer('sffc_frontend_nonce', 'nonce');
        
        $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
        $context = isset($_POST['context']) ? json_decode(stripslashes($_POST['context']), true) : array();
        
        if (empty($query)) {
            wp_send_json_error('Query is required');
        }
        
        // Process through integration
        $response = $this->integration->generate_market_response($query, $context);
        
        // Track usage
        $this->track_api_usage('market_analysis', $response);
        
        wp_send_json_success($response);
    }
    
    /**
     * Handle WHY analysis request
     */
    public function handle_why_analysis() {
        check_ajax_referer('sffc_frontend_nonce', 'nonce');
        
        $event = isset($_POST['event']) ? sanitize_text_field($_POST['event']) : '';
        $context = isset($_POST['context']) ? json_decode(stripslashes($_POST['context']), true) : array();
        
        // Get current market events
        $market_intel = $this->feed_manager->get_market_intelligence();
        
        // Find matching event or use latest significant
        $target_event = $this->find_event($event, $market_intel);
        
        if (!$target_event) {
            wp_send_json_error('No relevant market event found');
        }
        
        // Deep WHY analysis
        $analysis = $this->why_engine->analyze_why($target_event, $context);
        
        // Build response with templates
        $response = $this->build_why_response($analysis);
        
        wp_send_json_success($response);
    }
    
    /**
     * Handle comparison request
     */
    public function handle_comparison() {
        check_ajax_referer('sffc_frontend_nonce', 'nonce');
        
        $entities = isset($_POST['entities']) ? array_map(function($item) { return sanitize_text_field($item); }, $_POST['entities']) : array();
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'general';
        
        if (count($entities) < 2) {
            wp_send_json_error('At least two entities required for comparison');
        }
        
        // Get comparison data
        $comparison_data = $this->gather_comparison_data($entities, $type);
        
        // Generate comparison analysis
        $analysis = $this->generate_comparison($comparison_data);
        
        // Build visual comparison
        $visuals = $this->build_comparison_visuals($comparison_data);
        
        $response = array(
            'message' => $analysis['narrative'],
            'visuals' => $visuals,
            'verdict' => $analysis['verdict'],
            'metadata' => array(
                'entities' => $entities,
                'type' => $type,
                'timestamp' => current_time('mysql')
            )
        );
        
        wp_send_json_success($response);
    }
    
    /**
     * Handle opportunities request
     */
    public function handle_opportunities() {
        check_ajax_referer('sffc_frontend_nonce', 'nonce');
        
        $user_profile = isset($_POST['profile']) ? json_decode(stripslashes($_POST['profile']), true) : array();
        $focus = isset($_POST['focus']) ? sanitize_text_field($_POST['focus']) : 'general';
        
        // Get current market conditions
        $market_intel = $this->feed_manager->get_market_intelligence();
        
        // Identify opportunities
        $opportunities = $this->identify_opportunities($market_intel, $user_profile, $focus);
        
        // Build response
        $response = array(
            'opportunities' => $opportunities,
            'message' => $this->build_opportunities_narrative($opportunities, $market_intel),
            'visuals' => $this->build_opportunities_visuals($opportunities),
            'action_items' => $this->generate_action_items($opportunities)
        );
        
        wp_send_json_success($response);
    }
    
    /**
     * Handle education request
     */
    public function handle_education() {
        check_ajax_referer('sffc_frontend_nonce', 'nonce');
        
        $topic = isset($_POST['topic']) ? sanitize_text_field($_POST['topic']) : null;
        $level = isset($_POST['level']) ? sanitize_text_field($_POST['level']) : 'intermediate';
        
        // Get current market context for teaching
        $market_intel = $this->feed_manager->get_market_intelligence();
        
        // Select topic if not specified
        if (!$topic) {
            $topic = $this->select_relevant_topic($market_intel);
        }
        
        // Generate educational content
        $education = $this->generate_education($topic, $market_intel);
        
        // Build interactive components
        $interactive = $this->build_interactive_education($education);
        
        $response = array(
            'topic' => $topic,
            'lesson' => $education['content'],
            'visuals' => $education['visuals'],
            'interactive' => $interactive,
            'knowledge_check' => $education['quiz'],
            'next_topics' => $education['related']
        );
        
        wp_send_json_success($response);
    }
    
    /**
     * Check all feeds (admin)
     */
    public function handle_check_all_feeds() {
        check_ajax_referer('sffc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $feeds = array('bloomberg', 'ft', 'wsj', 'expansion', 'ilsole');
        $results = array();
        
        foreach ($feeds as $feed) {
            $data = $this->feed_manager->fetch_feed($feed);
            
            if ($data && !empty($data['items'])) {
                $results[$feed] = array(
                    'success' => true,
                    'message' => count($data['items']) . ' items fetched',
                    'last_item' => $data['items'][0]['title'] ?? 'Unknown'
                );
            } else {
                $results[$feed] = array(
                    'success' => false,
                    'message' => 'Failed to fetch feed'
                );
            }
        }
        
        wp_send_json_success($results);
    }
    
    /**
     * Force refresh market data (admin)
     */
    public function handle_force_refresh() {
        check_ajax_referer('sffc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Clear all caches
        delete_transient('sffc_market_intelligence');
        $this->clear_feed_cache();
        
        // Refresh feeds
        $this->integration->update_feeds();
        
        // Refresh analysis
        $this->integration->refresh_analysis();
        
        wp_send_json_success(array(
            'message' => 'Market data refreshed successfully',
            'timestamp' => current_time('mysql')
        ));
    }
    
    /**
     * Helper: Calculate market indicators
     */
    private function calculate_market_indicators($market_intel) {
        $indicators = array();
        
        // Volatility (simplified)
        $volatility = $this->assess_volatility($market_intel);
        $indicators['volatility'] = array(
            'value' => $volatility['level'],
            'change' => $volatility['change'],
            'direction' => $volatility['trend']
        );
        
        // Sentiment
        $sentiment = $this->assess_sentiment($market_intel);
        $indicators['sentiment'] = array(
            'value' => $sentiment['score'],
            'change' => $sentiment['label'],
            'direction' => $sentiment['trend']
        );
        
        // Momentum
        $momentum = $this->assess_momentum($market_intel);
        $indicators['momentum'] = array(
            'value' => $momentum['strength'],
            'change' => $momentum['label'],
            'direction' => $momentum['direction']
        );
        
        return $indicators;
    }
    
    /**
     * Helper: Extract trending topics
     */
    private function extract_trending_topics($market_intel) {
        $topics = array();
        
        // Extract from top stories
        foreach ($market_intel['top_stories'] as $story) {
            $extracted = $this->extract_topics_from_story($story);
            foreach ($extracted as $topic) {
                if (!isset($topics[$topic])) {
                    $topics[$topic] = 0;
                }
                $topics[$topic]++;
            }
        }
        
        // Sort by frequency
        arsort($topics);
        
        // Format for response
        $trending = array();
        $count = 0;
        foreach ($topics as $topic => $frequency) {
            if ($count >= 5) break;
            
            $trending[] = array(
                'id' => sanitize_title($topic),
                'name' => $topic,
                'heat' => $this->calculate_heat_level($frequency)
            );
            $count++;
        }
        
        return $trending;
    }
    
    /**
     * Helper: Get market status
     */
    private function get_market_status() {
        $now = new DateTime('now', new DateTimeZone('America/New_York'));
        $hour = (int) $now->format('H');
        $minute = (int) $now->format('i');
        $day = (int) $now->format('N'); // 1 = Monday, 7 = Sunday
        
        // Check if weekend
        if ($day >= 6) {
            return array(
                'status' => 'closed',
                'message' => 'Markets closed for weekend',
                'next_open' => 'Monday 9:30 AM ET'
            );
        }
        
        // Check market hours (9:30 AM - 4:00 PM ET)
        $market_open = ($hour == 9 && $minute >= 30) || ($hour > 9 && $hour < 16);
        
        if ($market_open) {
            return array(
                'status' => 'open',
                'message' => 'Markets open',
                'closes' => '4:00 PM ET'
            );
        } else if ($hour < 9 || ($hour == 9 && $minute < 30)) {
            return array(
                'status' => 'pre-market',
                'message' => 'Pre-market trading',
                'opens' => '9:30 AM ET'
            );
        } else {
            return array(
                'status' => 'after-hours',
                'message' => 'After-hours trading',
                'next_open' => 'Tomorrow 9:30 AM ET'
            );
        }
    }
    
    /**
     * Helper: Generate market summary
     */
    private function generate_market_summary($market_intel) {
        $summary_parts = array();
        
        // Overall tone
        $sentiment = $this->assess_sentiment($market_intel);
        if ($sentiment['score'] > 60) {
            $summary_parts[] = "Markets showing positive momentum";
        } else if ($sentiment['score'] < 40) {
            $summary_parts[] = "Markets under pressure";
        } else {
            $summary_parts[] = "Markets mixed";
        }
        
        // Key theme
        if (!empty($market_intel['trending_topics'])) {
            $top_topic = $market_intel['trending_topics'][0];
            $summary_parts[] = "Focus on " . $top_topic;
        }
        
        // Volatility note
        $volatility = $this->assess_volatility($market_intel);
        if ($volatility['level'] > 70) {
            $summary_parts[] = "Elevated volatility";
        }
        
        return implode('. ', $summary_parts) . '.';
    }
    
    /**
     * Helper: Build WHY response
     */
    private function build_why_response($analysis) {
        // Use response templates
        $components = array(
            array(
                'category' => 'why',
                'subcategory' => 'introduction',
                'replacements' => array()
            ),
            array(
                'category' => 'why',
                'subcategory' => 'deeper_dive',
                'replacements' => array(
                    'MECHANISM' => $analysis['root_causes'][0] ?? 'market dynamics',
                    'ROOT_CAUSE' => $analysis['multi_layer_why']['fundamentals']['analysis'] ?? ''
                )
            )
        );
        
        $message = $this->response_templates->build_response($components);
        
        // Select visuals
        $visuals = array();
        if (!empty($analysis['causality_chain'])) {
            $visuals[] = array(
                'type' => 'causality_chain',
                'data' => $analysis['causality_chain']
            );
        }
        
        return array(
            'message' => $message,
            'visuals' => $visuals,
            'analysis_depth' => $analysis,
            'follow_ups' => $this->suggest_why_follow_ups($analysis)
        );
    }
    
    /**
     * Helper: Clear feed cache
     */
    private function clear_feed_cache() {
        $feeds = array('bloomberg', 'ft', 'wsj', 'expansion', 'ilsole');
        foreach ($feeds as $feed) {
            delete_transient('sffc_feed_' . $feed);
        }
    }
    
    /**
     * Helper: Track API usage
     */
    private function track_api_usage($endpoint, $response) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sffc_api_usage';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return; // Table doesn't exist yet
        }
        
        $wpdb->insert(
            $table_name,
            array(
                'api_type' => 'market_analysis',
                'endpoint' => $endpoint,
                'tokens_used' => isset($response['metadata']['tokens']) ? $response['metadata']['tokens'] : 0,
                'cost_estimate' => 0.0001, // Placeholder
                'response_time' => 0,
                'status' => 'success'
            ),
            array('%s', '%s', '%d', '%f', '%d', '%s')
        );
    }
    
    /**
     * Assessment helpers
     */
    private function assess_volatility($market_intel) {
        // Simplified volatility assessment
        $volatility_keywords = array('volatile', 'swings', 'turbulent', 'uncertainty');
        $count = 0;
        
        foreach ($market_intel['top_stories'] as $story) {
            foreach ($volatility_keywords as $keyword) {
                if (stripos($story['title'] . ' ' . $story['description'], $keyword) !== false) {
                    $count++;
                }
            }
        }
        
        $level = min(100, $count * 20);
        
        return array(
            'level' => $level,
            'change' => $level > 50 ? '+' . ($level - 50) : '-' . (50 - $level),
            'trend' => $level > 50 ? 'up' : 'down'
        );
    }
    
    private function assess_sentiment($market_intel) {
        // Simplified sentiment assessment
        $positive_keywords = array('gains', 'rally', 'surge', 'boost', 'optimism');
        $negative_keywords = array('falls', 'drops', 'concerns', 'fears', 'decline');
        
        $positive_count = 0;
        $negative_count = 0;
        
        foreach ($market_intel['top_stories'] as $story) {
            $text = strtolower($story['title'] . ' ' . $story['description']);
            
            foreach ($positive_keywords as $keyword) {
                $positive_count += substr_count($text, $keyword);
            }
            
            foreach ($negative_keywords as $keyword) {
                $negative_count += substr_count($text, $keyword);
            }
        }
        
        $total = $positive_count + $negative_count;
        $score = $total > 0 ? round(($positive_count / $total) * 100) : 50;
        
        return array(
            'score' => $score,
            'label' => $score > 60 ? 'Bullish' : ($score < 40 ? 'Bearish' : 'Neutral'),
            'trend' => $score > 50 ? 'up' : 'down'
        );
    }
    
    private function assess_momentum($market_intel) {
        // Simplified momentum assessment
        $momentum_keywords = array('momentum', 'accelerating', 'building', 'continuing');
        $strength = 50;
        
        foreach ($market_intel['top_stories'] as $story) {
            foreach ($momentum_keywords as $keyword) {
                if (stripos($story['title'], $keyword) !== false) {
                    $strength += 10;
                }
            }
        }
        
        $strength = min(100, $strength);
        
        return array(
            'strength' => $strength,
            'label' => $strength > 70 ? 'Strong' : ($strength < 30 ? 'Weak' : 'Moderate'),
            'direction' => $strength > 50 ? 'up' : 'down'
        );
    }
    
    /**
     * More helper methods would follow...
     */
    private function extract_topics_from_story($story) {
        // Extract key topics from story
        $topics = array();
        
        // Simple keyword extraction
        $important_words = array('Fed', 'inflation', 'earnings', 'GDP', 'rates', 'tech', 'energy', 'banking');
        
        foreach ($important_words as $word) {
            if (stripos($story['title'] . ' ' . $story['description'], $word) !== false) {
                $topics[] = $word;
            }
        }
        
        return $topics;
    }
    
    private function calculate_heat_level($frequency) {
        if ($frequency >= 5) return '🔥🔥🔥';
        if ($frequency >= 3) return '🔥🔥';
        return '🔥';
    }
    
    private function find_event($query, $market_intel) {
        // Find matching event from market intelligence
        foreach ($market_intel['top_stories'] as $story) {
            if (stripos($story['title'], $query) !== false) {
                return $story;
            }
        }
        
        // Return most recent if no match
        return $market_intel['top_stories'][0] ?? null;
    }
    
    private function suggest_why_follow_ups($analysis) {
        return array(
            "What are the second-order effects of this?",
            "How should I position for this change?",
            "What historical precedent exists for this?",
            "What's the contrarian view here?"
        );
    }
    
    /**
     * Gather comparison data for entities
     */
    private function gather_comparison_data($entities, $type) {
        $data = array();
        
        foreach ($entities as $entity) {
            // Simulate gathering data for each entity
            $data[$entity] = array(
                'name' => $entity,
                'price' => rand(100, 500),
                'change' => rand(-10, 10) / 10,
                'volume' => rand(1000000, 10000000),
                'pe_ratio' => rand(10, 30),
                'market_cap' => rand(1000000000, 100000000000),
                'metrics' => array(
                    'revenue_growth' => rand(5, 25),
                    'profit_margin' => rand(5, 30),
                    'debt_ratio' => rand(20, 60)
                )
            );
        }
        
        return $data;
    }
    
    /**
     * Generate comparison analysis
     */
    private function generate_comparison($comparison_data) {
        $entities = array_keys($comparison_data);
        $leader = null;
        $highest_score = 0;
        
        // Determine leader based on metrics
        foreach ($comparison_data as $entity => $data) {
            $score = $data['price'] + ($data['change'] * 10) + ($data['metrics']['revenue_growth'] * 2);
            if ($score > $highest_score) {
                $highest_score = $score;
                $leader = $entity;
            }
        }
        
        $narrative = "Comparing " . implode(' vs ', $entities) . ": ";
        $narrative .= "$leader shows stronger performance with better growth metrics. ";
        $narrative .= "Key differentiators include revenue growth and market positioning.";
        
        return array(
            'narrative' => $narrative,
            'verdict' => "$leader appears to be the stronger choice based on current metrics",
            'leader' => $leader
        );
    }
    
    /**
     * Build comparison visuals
     */
    private function build_comparison_visuals($comparison_data) {
        return array(
            'type' => 'comparison_chart',
            'data' => $comparison_data,
            'chart_type' => 'bar',
            'metrics_displayed' => array('price', 'change', 'volume', 'pe_ratio')
        );
    }
    
    /**
     * Identify opportunities from market data
     */
    private function identify_opportunities($market_intel, $user_profile = array(), $focus = 'general') {
        $opportunities = array();
        
        // Analyze market data for opportunities
        if (!empty($market_intel['items'])) {
            foreach ($market_intel['items'] as $item) {
                // Simple opportunity detection based on keywords
                $text = strtolower($item['title'] . ' ' . $item['description']);
                
                if (strpos($text, 'undervalued') !== false || 
                    strpos($text, 'growth') !== false ||
                    strpos($text, 'breakout') !== false) {
                    $opportunities[] = array(
                        'type' => 'growth',
                        'title' => $item['title'],
                        'reason' => 'Potential growth opportunity identified'
                    );
                }
            }
        }
        
        // Add some default opportunities if none found
        if (empty($opportunities)) {
            $opportunities[] = array(
                'type' => 'diversification',
                'title' => 'Consider sector rotation',
                'reason' => 'Market conditions suggest opportunity in defensive sectors'
            );
        }
        
        return $opportunities;
    }
    
    /**
     * Build opportunities narrative
     */
    private function build_opportunities_narrative($opportunities, $market_intel) {
        $narrative = "Based on current market conditions, I've identified " . count($opportunities) . " potential opportunities:\n\n";
        
        foreach ($opportunities as $index => $opp) {
            $num = $index + 1;
            $narrative .= "$num. {$opp['title']}: {$opp['reason']}\n";
        }
        
        return $narrative;
    }
    
    /**
     * Build opportunities visuals
     */
    private function build_opportunities_visuals($opportunities) {
        return array(
            'type' => 'opportunity_cards',
            'opportunities' => $opportunities,
            'display_format' => 'cards'
        );
    }
    
    /**
     * Generate action items based on opportunities
     */
    private function generate_action_items($opportunities) {
        $actions = array();
        
        foreach ($opportunities as $opp) {
            $actions[] = array(
                'priority' => 'high',
                'action' => "Research " . $opp['title'],
                'timeline' => 'This week'
            );
        }
        
        return $actions;
    }
    
    /**
     * Select relevant educational topic
     */
    private function select_relevant_topic($market_intel) {
        // Analyze market data to pick relevant topic
        $topics = array(
            'Market Volatility',
            'PE Ratios Explained',
            'Understanding Market Cycles',
            'Risk Management Basics',
            'Portfolio Diversification'
        );
        
        // Simple selection based on market conditions
        if (!empty($market_intel['items'])) {
            $text = strtolower($market_intel['items'][0]['title']);
            if (strpos($text, 'volatile') !== false) {
                return 'Market Volatility';
            }
            if (strpos($text, 'earnings') !== false) {
                return 'PE Ratios Explained';
            }
        }
        
        return $topics[array_rand($topics)];
    }
    
    /**
     * Generate educational content
     */
    private function generate_education($topic, $market_intel) {
        $education = array(
            'topic' => $topic,
            'content' => "Let me explain $topic in the context of today's market...",
            'key_points' => array(
                "Understanding the basics of $topic",
                "How it applies to current conditions",
                "What investors should know"
            ),
            'examples' => array()
        );
        
        // Add relevant examples from market data
        if (!empty($market_intel['items'])) {
            $education['examples'][] = array(
                'title' => 'Real Market Example',
                'description' => $market_intel['items'][0]['title']
            );
        }
        
        return $education;
    }
    
    /**
     * Build interactive education visuals
     */
    private function build_interactive_education($education) {
        return array(
            'type' => 'educational_module',
            'topic' => $education['topic'],
            'content' => $education['content'],
            'interactive_elements' => array(
                'quiz' => false,
                'examples' => true,
                'glossary' => true
            ),
            'key_points' => $education['key_points']
        );
    }
}

// Initialize
add_action('init', function() {
    SFFC_Market_Ajax_Handlers::get_instance();
});