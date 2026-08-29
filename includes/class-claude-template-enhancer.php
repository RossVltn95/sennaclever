<?php
/**
 * Claude Template Enhancer
 * 
 * Uses Claude API to analyze real market data and enhance templates
 * with intelligent commentary and personalized insights
 * 
 * @package SennaFinanceCareer
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Claude_Template_Enhancer {
    
    private static $instance = null;
    private $claude_manager = null;
    private $deal_processor = null;
    private $pe_insights = null;
    
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
        // Initialize dependencies
        if (class_exists('SFFC_Claude_API_Manager')) {
            $this->claude_manager = SFFC_Claude_API_Manager::get_instance();
        }
        
        if (class_exists('SFFC_Deal_Intelligence_Processor')) {
            $this->deal_processor = SFFC_Deal_Intelligence_Processor::get_instance();
        }
        
        if (class_exists('SFFC_PE_Insights_Fetcher')) {
            $this->pe_insights = SFFC_PE_Insights_Fetcher::get_instance();
        }
        
        // Schedule weekly enhancement
        add_action('init', array($this, 'schedule_weekly_enhancement'));
        add_action('sffc_enhance_templates', array($this, 'enhance_all_templates'));
    }
    
    /**
     * Schedule weekly template enhancement
     */
    public function schedule_weekly_enhancement() {
        if (!wp_next_scheduled('sffc_enhance_templates')) {
            // Schedule for Sunday at 2 AM
            $next_sunday = strtotime('next Sunday 02:00:00');
            wp_schedule_event($next_sunday, 'weekly', 'sffc_enhance_templates');
        }
    }
    
    /**
     * Check if Claude is available
     */
    public function is_claude_available() {
        return $this->claude_manager !== null && method_exists($this->claude_manager, 'send_message');
    }
    
    /**
     * Enhance template with Claude analysis
     */
    public function enhance_template($category, $entities, $real_deals = array()) {
        if (!$this->is_claude_available()) {
            return $this->get_fallback_enhancement($category, $entities, $real_deals);
        }
        
        try {
            // Build context for Claude
            $context = $this->build_claude_context($category, $entities, $real_deals);
            
            // Get Claude's analysis
            $analysis = $this->get_claude_analysis($context);
            
            // Generate enhanced template data
            $enhanced_template = $this->generate_enhanced_template($category, $entities, $analysis, $real_deals);
            
            // Cache the enhancement
            $this->cache_enhancement($category, $enhanced_template);
            
            return $enhanced_template;
            
        } catch (Exception $e) {
            error_log('SFFC: Claude enhancement failed: ' . $e->getMessage());
            return $this->get_fallback_enhancement($category, $entities, $real_deals);
        }
    }
    
    /**
     * Build context for Claude analysis
     */
    private function build_claude_context($category, $entities, $real_deals) {
        $context = array(
            'category' => $category,
            'article_entities' => $entities,
            'market_data' => array(),
            'recent_activity' => array()
        );
        
        // Add real market intelligence if available
        if ($this->deal_processor && !empty($real_deals)) {
            $market_intel = $this->deal_processor->get_market_intelligence($category, 30);
            $context['market_data'] = $market_intel;
            
            // Extract key recent deals for context
            $context['recent_activity'] = $this->extract_key_deals($real_deals, 5);
        }
        
        // Add current market conditions
        $context['market_conditions'] = $this->get_market_conditions();
        
        return $context;
    }
    
    /**
     * Get Claude's analysis of market data
     */
    private function get_claude_analysis($context) {
        $prompt = $this->build_analysis_prompt($context);
        
        $response = $this->claude_manager->send_message($prompt, array(), 'market');
        
        if (!is_array($response) || empty($response['response'])) {
            throw new Exception('Invalid Claude response');
        }
        
        return $this->parse_claude_response($response['response']);
    }
    
    /**
     * Build prompt for Claude analysis
     */
    private function build_analysis_prompt($context) {
        $category = $context['category'];
        $entities = $context['article_entities'];
        $market_data = $context['market_data'];
        
        $prompt = "As a senior private equity research analyst, analyze the following market data:\n\n";
        
        // Add category context
        $prompt .= "FOCUS AREA: " . ucfirst($category) . " activity\n\n";
        
        // Add article context
        if (!empty($entities['companies'])) {
            $prompt .= "ARTICLE CONTEXT:\n";
            $prompt .= "- Companies mentioned: " . implode(', ', array_slice($entities['companies'], 0, 3)) . "\n";
        }
        
        if (!empty($entities['values'])) {
            $prompt .= "- Transaction values: " . implode(', ', array_column(array_slice($entities['values'], 0, 2), 'formatted')) . "\n";
        }
        
        if (!empty($entities['sectors'])) {
            $prompt .= "- Sectors: " . implode(', ', $entities['sectors']) . "\n";
        }
        
        $prompt .= "\n";
        
        // Add market intelligence
        if (!empty($market_data)) {
            $prompt .= "RECENT MARKET ACTIVITY:\n";
            $prompt .= "- Total recent deals: " . $market_data['total_deals'] . "\n";
            
            if (!empty($market_data['sector_breakdown'])) {
                $top_sectors = array_slice($market_data['sector_breakdown'], 0, 3, true);
                $prompt .= "- Top active sectors: " . implode(', ', array_keys($top_sectors)) . "\n";
            }
            
            if (!empty($market_data['recent_trends']['momentum'])) {
                $prompt .= "- Market momentum: " . $market_data['recent_trends']['momentum'] . "\n";
            }
            
            if (!empty($market_data['recent_trends']['dominant_firms'])) {
                $top_firms = array_slice(array_keys($market_data['recent_trends']['dominant_firms']), 0, 3);
                $prompt .= "- Most active firms: " . implode(', ', $top_firms) . "\n";
            }
        }
        
        $prompt .= "\nPROVIDE:\n";
        $prompt .= "1. MARKET_COMMENTARY: 2-3 sentences on current market conditions (max 150 words)\n";
        $prompt .= "2. KEY_INSIGHTS: 3 bullet points on recent trends (each max 25 words)\n";
        $prompt .= "3. OUTLOOK: 1-2 sentences on forward-looking perspective (max 100 words)\n";
        $prompt .= "4. CHART_CONTEXT: Brief context for data visualization (max 50 words)\n\n";
        
        $prompt .= "Focus on actionable insights for PE professionals. Use industry terminology. Be specific and data-driven.";
        
        return $prompt;
    }
    
    /**
     * Parse Claude's response into structured data
     */
    private function parse_claude_response($response) {
        $analysis = array(
            'market_commentary' => '',
            'key_insights' => array(),
            'outlook' => '',
            'chart_context' => ''
        );
        
        // Extract market commentary
        if (preg_match('/MARKET_COMMENTARY:\s*(.+?)(?=\d\.|$)/s', $response, $matches)) {
            $analysis['market_commentary'] = trim($matches[1]);
        }
        
        // Extract key insights
        if (preg_match('/KEY_INSIGHTS:\s*(.+?)(?=\d\.|$)/s', $response, $matches)) {
            $insights_text = trim($matches[1]);
            $insights = preg_split('/[-•]\s*/', $insights_text);
            $analysis['key_insights'] = array_filter(array_map('trim', $insights));
        }
        
        // Extract outlook
        if (preg_match('/OUTLOOK:\s*(.+?)(?=\d\.|$)/s', $response, $matches)) {
            $analysis['outlook'] = trim($matches[1]);
        }
        
        // Extract chart context
        if (preg_match('/CHART_CONTEXT:\s*(.+?)(?=\d\.|$)/s', $response, $matches)) {
            $analysis['chart_context'] = trim($matches[1]);
        }
        
        return $analysis;
    }
    
    /**
     * Generate enhanced template with Claude analysis
     */
    private function generate_enhanced_template($category, $entities, $analysis, $real_deals) {
        // Get base template
        $template_library = SFFC_Template_Library::get_instance();
        $method = "get_{$category}_insights";
        
        $base_template = array();
        if ($template_library && method_exists($template_library, $method)) {
            $base_template = $template_library->$method($entities);
        }
        
        // Enhance with Claude insights
        if (!empty($analysis['market_commentary'])) {
            $base_template['commentary'] = $analysis['market_commentary'];
        }
        
        // Add Claude-enhanced metrics
        $base_template['enhanced_metrics'] = $this->generate_enhanced_metrics($analysis, $real_deals);
        
        // Add market intelligence section
        $base_template['market_intelligence'] = array(
            'insights' => $analysis['key_insights'] ?? array(),
            'outlook' => $analysis['outlook'] ?? '',
            'chart_context' => $analysis['chart_context'] ?? '',
            'last_updated' => date('Y-m-d H:i:s'),
            'source' => 'claude_enhanced'
        );
        
        // Enhance charts with real data
        if (!empty($real_deals)) {
            $base_template['charts'] = $this->enhance_charts_with_real_data($base_template['charts'] ?? array(), $real_deals, $category);
        }
        
        return $base_template;
    }
    
    /**
     * Generate enhanced metrics from analysis
     */
    private function generate_enhanced_metrics($analysis, $real_deals) {
        $metrics = array();
        
        // Market sentiment metric
        $sentiment = $this->extract_sentiment_from_analysis($analysis['market_commentary'] ?? '');
        $metrics[] = array(
            'label' => 'Market Sentiment',
            'value' => ucfirst($sentiment),
            'trend' => $sentiment === 'positive' ? 'up' : ($sentiment === 'negative' ? 'down' : 'neutral')
        );
        
        // Activity level
        $activity_level = count($real_deals);
        $metrics[] = array(
            'label' => 'Recent Activity',
            'value' => $activity_level . ' deals',
            'trend' => $activity_level > 10 ? 'up' : 'neutral'
        );
        
        // Intelligence confidence
        $confidence = !empty($analysis['market_commentary']) ? 'High' : 'Medium';
        $metrics[] = array(
            'label' => 'Data Quality',
            'value' => $confidence,
            'trend' => 'neutral'
        );
        
        return $metrics;
    }
    
    /**
     * Enhance charts with real market data
     */
    private function enhance_charts_with_real_data($base_charts, $real_deals, $category) {
        if (empty($real_deals) || !$this->deal_processor) {
            return $base_charts;
        }
        
        $processed_deals = $this->deal_processor->process_deals($real_deals);
        
        // Add real firm comparison chart
        if ($category === 'fundraise' && !empty($base_charts['bar'])) {
            $base_charts['bar'] = $this->enhance_fundraise_bar_chart($base_charts['bar'], $processed_deals);
        }
        
        // Add real sector activity heatmap
        $base_charts['sector_heatmap'] = $this->generate_sector_activity_heatmap($processed_deals);
        
        // Add timeline of recent activity
        $base_charts['activity_timeline'] = $this->generate_activity_timeline($processed_deals);
        
        return $base_charts;
    }
    
    /**
     * Enhance fundraise bar chart with real data
     */
    private function enhance_fundraise_bar_chart($base_chart, $processed_deals) {
        $real_firms = array();
        
        foreach ($processed_deals as $deal) {
            foreach ($deal['entities']['firms'] as $firm) {
                foreach ($deal['entities']['values'] as $value) {
                    if ($value['value_usd'] >= 1000000000) { // $1bn+
                        $real_firms[$firm] = round($value['value_usd'] / 1000000000, 1);
                    }
                }
            }
        }
        
        // Replace some placeholder data with real firms
        if (!empty($real_firms)) {
            $real_data = array();
            $count = 0;
            foreach ($real_firms as $firm => $value) {
                $real_data[] = array(
                    'label' => $firm,
                    'value' => $value,
                    'highlighted' => $count === 0
                );
                $count++;
                if ($count >= 3) break;
            }
            
            // Merge with existing data, prioritizing real data
            $base_chart['data'] = array_merge($real_data, array_slice($base_chart['data'], count($real_data)));
        }
        
        return $base_chart;
    }
    
    /**
     * Generate sector activity heatmap
     */
    private function generate_sector_activity_heatmap($processed_deals) {
        $sector_activity = array();
        $quarters = array('Q1', 'Q2', 'Q3', 'Q4');
        $sectors = array('Technology', 'Healthcare', 'Financial Services', 'Consumer', 'Industrial');
        
        // Analyze real deal activity by sector and quarter
        foreach ($processed_deals as $deal) {
            $quarter = 'Q' . ceil(date('n', strtotime($deal['original']['date'])) / 3);
            foreach ($deal['entities']['sectors'] as $sector) {
                $sector_key = ucwords($sector);
                $key = $quarter . '_' . $sector_key;
                $sector_activity[$key] = ($sector_activity[$key] ?? 0) + 1;
            }
        }
        
        // Generate heatmap data
        $heatmap_data = array();
        foreach ($quarters as $quarter) {
            foreach ($sectors as $sector) {
                $key = $quarter . '_' . $sector;
                $value = $sector_activity[$key] ?? 0;
                $heatmap_data[] = array(
                    'x' => $quarter,
                    'y' => $sector,
                    'value' => min($value * 20 + 20, 100) // Scale to 20-100 range
                );
            }
        }
        
        return array(
            'title' => 'Sector Activity Heatmap',
            'type' => 'heatmap',
            'data' => $heatmap_data
        );
    }
    
    /**
     * Generate activity timeline
     */
    private function generate_activity_timeline($processed_deals) {
        $monthly_activity = array();
        
        foreach ($processed_deals as $deal) {
            $month = date('M', strtotime($deal['original']['date']));
            $monthly_activity[$month] = ($monthly_activity[$month] ?? 0) + 1;
        }
        
        $timeline_data = array();
        foreach ($monthly_activity as $month => $count) {
            $timeline_data[] = array(
                'month' => $month,
                'value' => $count
            );
        }
        
        return array(
            'title' => 'Deal Activity Timeline',
            'type' => 'line',
            'data' => $timeline_data
        );
    }
    
    /**
     * Extract sentiment from analysis text
     */
    private function extract_sentiment_from_analysis($text) {
        $positive_words = array('strong', 'robust', 'growth', 'momentum', 'optimistic', 'bullish', 'accelerat');
        $negative_words = array('weak', 'decline', 'volatility', 'headwinds', 'cautious', 'bearish', 'slow');
        
        $text_lower = strtolower($text);
        $positive_count = 0;
        $negative_count = 0;
        
        foreach ($positive_words as $word) {
            $positive_count += substr_count($text_lower, $word);
        }
        
        foreach ($negative_words as $word) {
            $negative_count += substr_count($text_lower, $word);
        }
        
        if ($positive_count > $negative_count) return 'positive';
        if ($negative_count > $positive_count) return 'negative';
        return 'neutral';
    }
    
    /**
     * Get fallback enhancement when Claude is unavailable
     */
    private function get_fallback_enhancement($category, $entities, $real_deals) {
        // Log the fallback usage
        error_log('SFFC: Using fallback enhancement - Claude unavailable');
        
        // Update admin status
        update_option('sffc_claude_enhancement_status', array(
            'status' => 'fallback',
            'timestamp' => time(),
            'reason' => 'Claude API unavailable'
        ));
        
        // Return basic template with real data if available
        $template_library = SFFC_Template_Library::get_instance();
        $method = "get_{$category}_insights";
        
        if ($template_library && method_exists($template_library, $method)) {
            $template = $template_library->$method($entities);
            
            // Add fallback market intelligence
            $template['market_intelligence'] = array(
                'insights' => array(
                    'Market analysis temporarily unavailable',
                    'Using cached data and real deal information',
                    'Enhanced insights will return when service is restored'
                ),
                'outlook' => 'Market analysis will be updated automatically when enhanced services are available.',
                'last_updated' => date('Y-m-d H:i:s'),
                'source' => 'fallback_mode'
            );
            
            return $template;
        }
        
        return array();
    }
    
    /**
     * Extract key deals for context
     */
    private function extract_key_deals($deals, $limit = 5) {
        $key_deals = array();
        
        foreach (array_slice($deals, 0, $limit) as $deal) {
            $key_deals[] = array(
                'title' => $deal['title'],
                'date' => $deal['date'],
                'category' => $deal['category']
            );
        }
        
        return $key_deals;
    }
    
    /**
     * Get current market conditions
     */
    private function get_market_conditions() {
        return array(
            'timestamp' => time(),
            'quarter' => 'Q' . ceil(date('n') / 3),
            'year' => date('Y'),
            'month' => date('F')
        );
    }
    
    /**
     * Cache enhancement for performance
     */
    private function cache_enhancement($category, $enhanced_template) {
        $cache_key = "sffc_enhanced_template_{$category}";
        set_transient($cache_key, $enhanced_template, 7 * DAY_IN_SECONDS); // Cache for 1 week
    }
    
    /**
     * Get cached enhancement
     */
    public function get_cached_enhancement($category) {
        $cache_key = "sffc_enhanced_template_{$category}";
        return get_transient($cache_key);
    }
    
    /**
     * Enhance all templates (scheduled task)
     */
    public function enhance_all_templates() {
        $categories = array('fundraise', 'acquisition', 'exit', 'esg', 'regulation', 'people_moves');
        $enhanced_count = 0;
        $errors = array();
        
        foreach ($categories as $category) {
            try {
                // Get recent deals for this category
                if ($this->pe_insights) {
                    $real_deals = $this->pe_insights->get_deals_by_category($category, 30);
                } else {
                    $real_deals = array();
                }
                
                // Enhance template
                $enhanced = $this->enhance_template($category, array(), $real_deals);
                
                if (!empty($enhanced)) {
                    $enhanced_count++;
                }
                
            } catch (Exception $e) {
                $errors[] = array(
                    'category' => $category,
                    'error' => $e->getMessage()
                );
            }
        }
        
        // Update enhancement status
        update_option('sffc_template_enhancement_status', array(
            'last_run' => time(),
            'enhanced_templates' => $enhanced_count,
            'total_categories' => count($categories),
            'errors' => $errors,
            'claude_available' => $this->is_claude_available()
        ));
        
        error_log("SFFC: Enhanced {$enhanced_count}/" . count($categories) . " templates");
    }
    
    /**
     * Get enhancement status for admin
     */
    public function get_enhancement_status() {
        return get_option('sffc_template_enhancement_status', array(
            'last_run' => 0,
            'enhanced_templates' => 0,
            'total_categories' => 0,
            'errors' => array(),
            'claude_available' => false
        ));
    }
}

// Initialize the enhancer
SFFC_Claude_Template_Enhancer::get_instance();