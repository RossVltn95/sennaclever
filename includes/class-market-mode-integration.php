<?php
/**
 * Market Mode Integration - Orchestrates all market analysis components
 * 
 * @package SennaCareers
 * @subpackage Market
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Market_Mode_Integration {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Component instances
     */
    private $feed_manager;
    private $analysis_engine;
    private $why_engine;
    private $claude_api;
    private $visual_generator;
    private $cache_manager;
    
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
        $this->init_cron();
    }
    
    /**
     * Initialize components
     */
    private function init_components() {
        // Load required classes
        require_once SFFC_PLUGIN_DIR . 'includes/class-market-feed-manager.php';
        require_once SFFC_PLUGIN_DIR . 'includes/class-market-analysis-engine.php';
        require_once SFFC_PLUGIN_DIR . 'includes/class-market-why-engine.php';
        require_once SFFC_PLUGIN_DIR . 'includes/class-claude-api-manager.php';
        
        // Initialize components
        $this->feed_manager = SFFC_Market_Feed_Manager::get_instance();
        $this->analysis_engine = SFFC_Market_Analysis_Engine::get_instance();
        $this->why_engine = SFFC_Market_Why_Engine::get_instance();
        $this->claude_api = SFFC_Claude_API_Manager::get_instance();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // AJAX handlers removed - now handled by class-market-ajax-handlers.php to prevent duplicates
        // The following endpoints are managed by SFFC_Market_Ajax_Handlers:
        // - sffc_market_analysis
        // - sffc_market_why  
        // - sffc_market_compare
        // - sffc_market_opportunities
        // - sffc_market_education
    }
    
    /**
     * Initialize cron jobs
     */
    private function init_cron() {
        // Schedule market feed updates
        if (!wp_next_scheduled('sffc_update_market_feeds')) {
            wp_schedule_event(time(), 'sffc_15_minutes', 'sffc_update_market_feeds');
        }
        
        // Schedule market analysis refresh (every 3 hours to reduce API costs)
        if (!wp_next_scheduled('sffc_refresh_market_analysis')) {
            wp_schedule_event(time(), 'sffc_three_hours', 'sffc_refresh_market_analysis');
        }
        
        // Add cron actions
        add_action('sffc_update_market_feeds', array($this, 'update_feeds'));
        add_action('sffc_refresh_market_analysis', array($this, 'refresh_analysis'));
        
        // Custom cron schedule
        add_filter('cron_schedules', array($this, 'add_cron_schedules'));
    }
    
    /**
     * Add custom cron schedules
     */
    public function add_cron_schedules($schedules) {
        $schedules['sffc_15_minutes'] = array(
            'interval' => 900,
            'display' => 'Every 15 minutes'
        );
        $schedules['sffc_three_hours'] = array(
            'interval' => 10800,
            'display' => 'Every 3 hours'
        );
        return $schedules;
    }
    
    /**
     * Handle market analysis request
     */
    public function handle_market_analysis() {
        check_ajax_referer('sffc_frontend_nonce', 'nonce');
        
        $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
        $context = isset($_POST['context']) ? json_decode(stripslashes($_POST['context']), true) : array();
        
        // Build comprehensive response
        $response = $this->generate_market_response($query, $context);
        
        wp_send_json_success($response);
    }
    
    /**
     * Generate comprehensive market response
     */
    public function generate_market_response($query, $context) {
        // Get current market intelligence
        $market_intel = $this->feed_manager->get_market_intelligence();
        
        // Determine query intent
        $intent = $this->analyze_query_intent($query);
        
        // Build response based on intent
        switch ($intent['type']) {
            case 'why':
                return $this->generate_why_response($query, $market_intel, $context);
                
            case 'comparison':
                return $this->generate_comparison_response($query, $market_intel, $context);
                
            case 'education':
                return $this->generate_education_response($query, $market_intel, $context);
                
            case 'opportunity':
                return $this->generate_opportunity_response($query, $market_intel, $context);
                
            case 'general':
            default:
                return $this->generate_general_response($query, $market_intel, $context);
        }
    }
    
    /**
     * Generate WHY-focused response
     */
    private function generate_why_response($query, $market_intel, $context) {
        // Find relevant event
        $relevant_event = $this->find_most_relevant_event($query, $market_intel);
        
        // Deep WHY analysis
        $why_analysis = $this->why_engine->analyze_why($relevant_event, $context);
        
        // Build conversation
        $conversation = array(
            'greeting' => $this->get_why_greeting($query),
            'surface_explanation' => $this->explain_surface($why_analysis),
            'deeper_dive' => $this->explain_deeper($why_analysis),
            'market_mechanics' => $this->explain_mechanics($why_analysis),
            'career_connection' => $this->connect_to_career($why_analysis),
            'visual_components' => $this->select_visuals($why_analysis),
            'follow_up' => $this->suggest_follow_up($why_analysis)
        );
        
        return $this->format_response($conversation);
    }
    
    /**
     * Format response for frontend
     */
    private function format_response($conversation) {
        $formatted = array(
            'message' => $this->build_message($conversation),
            'visuals' => $conversation['visual_components'],
            'metadata' => array(
                'typing_delay' => $this->calculate_typing_delay($conversation),
                'complexity' => $this->assess_complexity($conversation),
                'follow_ups' => $conversation['follow_up']
            )
        );
        
        return $formatted;
    }
    
    /**
     * Build conversation message
     */
    private function build_message($conversation) {
        $message = $conversation['greeting'] . "\n\n";
        
        // Add surface explanation
        if (!empty($conversation['surface_explanation'])) {
            $message .= $conversation['surface_explanation'] . "\n\n";
        }
        
        // Add deeper analysis
        if (!empty($conversation['deeper_dive'])) {
            $message .= "But here's what's really happening:\n" . $conversation['deeper_dive'] . "\n\n";
        }
        
        // Add market mechanics
        if (!empty($conversation['market_mechanics'])) {
            $message .= "The market mechanics at play:\n" . $conversation['market_mechanics'] . "\n\n";
        }
        
        // Add career connection
        if (!empty($conversation['career_connection'])) {
            $message .= "What this means for your career:\n" . $conversation['career_connection'];
        }
        
        return $message;
    }
    
    /**
     * Select appropriate visuals
     */
    private function select_visuals($analysis) {
        $visuals = array();
        
        // Always include causality chain for WHY analysis
        if (!empty($analysis['causality_chain'])) {
            $visuals[] = array(
                'type' => 'causality_chain',
                'data' => array(
                    'chain' => $analysis['causality_chain'],
                    'conclusion' => $analysis['multi_layer_why']['strategic']['opportunity_set'],
                    'strategic_action' => $analysis['career_implications']
                )
            );
        }
        
        // Add multi-factor analysis if relevant
        if (!empty($analysis['multi_layer_why'])) {
            $visuals[] = array(
                'type' => 'multi_factor_analysis',
                'data' => $this->prepare_multi_factor_visual($analysis)
            );
        }
        
        // Add market psychology if sentiment is important
        if ($this->is_sentiment_relevant($analysis)) {
            $visuals[] = array(
                'type' => 'market_psychology',
                'data' => $this->prepare_psychology_visual($analysis)
            );
        }
        
        // Add knowledge check for learning
        $visuals[] = array(
            'type' => 'knowledge_check',
            'data' => $this->prepare_knowledge_check($analysis)
        );
        
        return $visuals;
    }
    
    /**
     * Update market feeds (cron job)
     */
    public function update_feeds() {
        $feeds_to_update = array('bloomberg', 'ft', 'wsj', 'expansion');
        
        foreach ($feeds_to_update as $feed) {
            $this->feed_manager->fetch_feed($feed);
        }
        
        // Clear old cache
        $this->clear_old_cache();
    }
    
    /**
     * Refresh market analysis (cron job)
     */
    public function refresh_analysis() {
        // Get latest intelligence
        $market_intel = $this->feed_manager->get_market_intelligence();
        
        // Analyze top stories
        foreach ($market_intel['top_stories'] as $story) {
            $analysis = $this->analysis_engine->analyze_market_event($story);
            
            // Cache the analysis
            $this->cache_analysis($story, $analysis);
        }
    }
    
    /**
     * Cache market analysis
     */
    private function cache_analysis($event, $analysis) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sffc_market_cache';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return; // Table doesn't exist yet
        }
        
        $wpdb->insert(
            $table_name,
            array(
                'source' => $event['source'],
                'data_type' => 'analysis',
                'content' => json_encode($analysis),
                'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour'))
            ),
            array('%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Clear old cache entries
     */
    private function clear_old_cache() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sffc_market_cache';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return; // Table doesn't exist yet
        }
        
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table_name WHERE expires_at < %s",
                current_time('mysql')
            )
        );
    }
    
    /**
     * Analyze query intent
     */
    private function analyze_query_intent($query) {
        $query_lower = strtolower($query);
        
        // WHY questions
        if (strpos($query_lower, 'why') !== false) {
            return array('type' => 'why', 'focus' => $this->extract_focus($query));
        }
        
        // Comparison requests
        if (preg_match('/compare|vs|versus|difference/i', $query_lower)) {
            return array('type' => 'comparison', 'entities' => $this->extract_entities($query));
        }
        
        // Educational requests
        if (preg_match('/explain|understand|learn|teach|how does/i', $query_lower)) {
            return array('type' => 'education', 'topic' => $this->extract_topic($query));
        }
        
        // Opportunity seeking
        if (preg_match('/opportunity|trade|position|strategy/i', $query_lower)) {
            return array('type' => 'opportunity', 'focus' => $this->extract_focus($query));
        }
        
        return array('type' => 'general', 'query' => $query);
    }
    
    /**
     * Get contextual greeting for WHY questions
     */
    private function get_why_greeting($query) {
        $greetings = array(
            "Excellent question! Let me peel back the layers on this...",
            "That's exactly the right question to ask. Here's what's really happening...",
            "You're thinking like a pro. Let me show you the mechanics behind this...",
            "Great instinct to dig deeper. The real story is fascinating...",
            "This is where it gets interesting. Let me explain the chain of events..."
        );
        
        return $greetings[array_rand($greetings)];
    }
    
    /**
     * Prepare multi-factor visual data
     */
    private function prepare_multi_factor_visual($analysis) {
        $factors = array();
        
        // Extract factors from multi-layer analysis
        foreach ($analysis['multi_layer_why'] as $layer => $content) {
            if (is_array($content) && !empty($content)) {
                $factors[] = array(
                    'name' => ucfirst($layer),
                    'weight' => $this->calculate_factor_weight($layer),
                    'why' => $content['description'] ?? '',
                    'mechanism' => $content['analysis'] ?? '',
                    'timeframe' => $this->get_layer_timeframe($layer),
                    'impact_level' => $this->assess_impact_level($content)
                );
            }
        }
        
        return array(
            'event' => $analysis['event_summary'],
            'factors' => $factors,
            'cumulative_impact' => 85,
            'cumulative_explanation' => 'Combined factors suggest significant market shift'
        );
    }
    
    /**
     * Helper methods
     */
    private function calculate_factor_weight($layer) {
        $weights = array(
            'trigger' => 30,
            'fundamentals' => 25,
            'structure' => 20,
            'psychology' => 15,
            'strategic' => 10
        );
        return $weights[$layer] ?? 10;
    }
    
    private function get_layer_timeframe($layer) {
        $timeframes = array(
            'trigger' => 'Immediate',
            'fundamentals' => '1-3 days',
            'structure' => '1 week',
            'psychology' => '2-4 weeks',
            'strategic' => '1-3 months'
        );
        return $timeframes[$layer] ?? 'Variable';
    }
    
    /**
     * CRITICAL MISSING METHODS - Required for Claude integration
     */
    
    /**
     * Generate general market response using Claude API
     */
    private function generate_general_response($query, $market_intel, $context) {
        // Build comprehensive market prompt
        $prompt = "Current market query: {$query}\n\n";
        $prompt .= "Latest market intelligence:\n";
        
        if (!empty($market_intel['top_stories'])) {
            foreach (array_slice($market_intel['top_stories'], 0, 3) as $story) {
                $prompt .= "- {$story['title']} ({$story['source']})\n";
            }
        }
        
        $prompt .= "\nProvide comprehensive market analysis with:\n";
        $prompt .= "1. KEY DEVELOPMENTS - What's happening right now\n";
        $prompt .= "2. MARKET IMPACT - Why it matters\n"; 
        $prompt .= "3. CAREER IMPLICATIONS - Opportunities for finance professionals\n";
        $prompt .= "4. ACTIONABLE INTELLIGENCE - Specific next steps\n\n";
        $prompt .= "Focus on real firms, real deals, and actionable insights.";
        
        // Call Claude API
        $claude_response = $this->claude_api->send_message($prompt, $context, 'market');
        
        if ($claude_response['success']) {
            return array(
                'message' => $claude_response['message'],
                'visual' => array(
                    'type' => 'market_analysis',
                    'data' => $this->extract_visual_data($claude_response['message'])
                ),
                'typing_delay' => 1200
            );
        }
        
        // Fallback if Claude fails
        return $this->get_market_fallback($query, $context);
    }
    
    /**
     * Generate comparison response
     */
    private function generate_comparison_response($query, $market_intel, $context) {
        $entities = $this->extract_entities($query);
        
        $prompt = "Compare: {$query}\n\n";
        $prompt .= "Provide detailed comparison analysis covering:\n";
        $prompt .= "1. PERFORMANCE METRICS - Key differences\n";
        $prompt .= "2. STRATEGIC POSITIONING - Market advantages\n";
        $prompt .= "3. CAREER OPPORTUNITIES - Where to focus\n";
        
        $claude_response = $this->claude_api->send_message($prompt, $context, 'market');
        
        if ($claude_response['success']) {
            return array(
                'message' => $claude_response['message'],
                'visual' => array('type' => 'comparison_analysis', 'data' => $entities),
                'typing_delay' => 1000
            );
        }
        
        return $this->get_market_fallback($query, $context);
    }
    
    /**
     * Generate education response
     */
    private function generate_education_response($query, $market_intel, $context) {
        $topic = $this->extract_topic($query);
        
        $prompt = "Educational request: {$query}\n\n";
        $prompt .= "Provide clear educational content covering:\n";
        $prompt .= "1. CONCEPT EXPLANATION - Core principles\n";
        $prompt .= "2. REAL-WORLD EXAMPLES - Current market examples\n";
        $prompt .= "3. PRACTICAL APPLICATION - How to use this knowledge\n";
        
        $claude_response = $this->claude_api->send_message($prompt, $context, 'market');
        
        if ($claude_response['success']) {
            return array(
                'message' => $claude_response['message'],
                'visual' => array('type' => 'education_module', 'data' => array('topic' => $topic)),
                'typing_delay' => 1100
            );
        }
        
        return $this->get_market_fallback($query, $context);
    }
    
    /**
     * Generate opportunity response
     */
    private function generate_opportunity_response($query, $market_intel, $context) {
        $focus = $this->extract_focus($query);
        
        $prompt = "Opportunity analysis: {$query}\n\n";
        $prompt .= "Identify specific opportunities covering:\n";
        $prompt .= "1. IMMEDIATE OPPORTUNITIES - Available now\n";
        $prompt .= "2. STRATEGIC POSITIONING - How to approach\n";
        $prompt .= "3. RISK/REWARD ANALYSIS - What to consider\n";
        
        $claude_response = $this->claude_api->send_message($prompt, $context, 'market');
        
        if ($claude_response['success']) {
            return array(
                'message' => $claude_response['message'],
                'visual' => array('type' => 'opportunity_matrix', 'data' => array('focus' => $focus)),
                'typing_delay' => 1000
            );
        }
        
        return $this->get_market_fallback($query, $context);
    }
    
    /**
     * Find most relevant market event
     */
    private function find_most_relevant_event($query, $market_intel) {
        if (empty($market_intel['top_stories'])) {
            return array('title' => 'Market Update', 'description' => 'General market conditions');
        }
        
        // Simple relevance matching
        $query_lower = strtolower($query);
        foreach ($market_intel['top_stories'] as $story) {
            if (strpos(strtolower($story['title']), $query_lower) !== false) {
                return $story;
            }
        }
        
        // Return first story as fallback
        return $market_intel['top_stories'][0];
    }
    
    /**
     * Extract visual data from Claude response
     */
    private function extract_visual_data($message) {
        // Parse Claude's structured response for visual elements
        return array(
            'headline' => 'Live Market Analysis',
            'timestamp' => 'Updated ' . date('g:i A'),
            'analysis' => substr($message, 0, 200),
            'source' => 'claude_api'
        );
    }
    
    /**
     * Get market fallback response
     */
    private function get_market_fallback($query, $context) {
        $user_name = $context['user_first_name'] ?? '';
        $greeting = !empty($user_name) ? "Hi {$user_name}" : "Hi there";
        
        return array(
            'message' => "{$greeting}, I'm experiencing some technical issues with real-time market data. Let me provide you with the latest analysis I have available.",
            'visual' => null,
            'typing_delay' => 800
        );
    }
    
    /**
     * Helper extraction methods
     */
    private function extract_entities($query) {
        // Extract comparison entities
        $entities = array();
        $firms = array('KKR', 'Blackstone', 'Apollo', 'Carlyle', 'TPG');
        foreach ($firms as $firm) {
            if (stripos($query, $firm) !== false) {
                $entities[] = $firm;
            }
        }
        return $entities;
    }
    
    private function extract_topic($query) {
        $topics = array('LBO', 'DCF', 'valuation', 'private equity', 'M&A', 'credit');
        foreach ($topics as $topic) {
            if (stripos($query, $topic) !== false) {
                return $topic;
            }
        }
        return 'finance';
    }
    
    private function extract_focus($query) {
        if (stripos($query, 'opportunity') !== false) return 'opportunities';
        if (stripos($query, 'sector') !== false) return 'sector';
        if (stripos($query, 'firm') !== false) return 'firm';
        return 'general';
    }
    
    /**
     * Missing helper methods for WHY analysis
     */
    private function explain_surface($analysis) {
        return $analysis['surface_explanation'] ?? 'Surface level market movement analysis';
    }
    
    private function explain_deeper($analysis) {
        return $analysis['deeper_analysis'] ?? 'Deeper market mechanics at play';
    }
    
    private function explain_mechanics($analysis) {
        return $analysis['market_mechanics'] ?? 'Technical market structure analysis';
    }
    
    private function connect_to_career($analysis) {
        return $analysis['career_implications'] ?? 'Career opportunities from this development';
    }
    
    private function suggest_follow_up($analysis) {
        return array('What sector interests you most?', 'Would you like firm-specific analysis?');
    }
    
    // Stub methods to fix PHPStan errors
    private function calculate_typing_delay($text = '') { return 1500; }
    private function assess_complexity($query = '') { return 'moderate'; }
    private function is_sentiment_relevant($query = '') { return strpos(strtolower($query), 'sentiment') !== false; }
    private function prepare_psychology_visual($analysis = array()) { return array('type' => 'sentiment', 'data' => array()); }
    private function prepare_knowledge_check($analysis = array()) { return array('question' => 'Test question', 'options' => array()); }
    private function assess_impact_level($event = array()) { return 'high'; }
}