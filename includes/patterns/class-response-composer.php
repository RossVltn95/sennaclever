<?php
/**
 * Response Composer - Phase 3
 * Intelligently composes responses by combining patterns, data, and context
 * 
 * @package SennaCareers
 * @since 6.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Response_Composer {
    
    private static $instance = null;
    private $pattern_library;
    private $data_integrator;
    private $template_engine;
    private $claude_api;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_dependencies();
    }
    
    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        // Pattern Library
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/patterns/class-pattern-library.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/patterns/class-pattern-library.php';
            $this->pattern_library = SFFC_Pattern_Library::get_instance();
        }
        
        // Data Integrator
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/patterns/class-data-integrator.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/patterns/class-data-integrator.php';
            $this->data_integrator = SFFC_Data_Integrator::get_instance();
        }
        
        // Template Engine
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/patterns/class-template-engine.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/patterns/class-template-engine.php';
            $this->template_engine = SFFC_Template_Engine::get_instance();
        }
        
        // Claude API for complex queries
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-claude-api-manager.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-claude-api-manager.php';
            $this->claude_api = SFFC_Claude_API_Manager::get_instance();
        }
    }
    
    /**
     * Compose a response based on classification and context
     */
    public function compose_response($classification, $query, $context = array()) {
        // Check if Claude is required
        if ($classification['requires_claude']) {
            return $this->compose_claude_response($query, $classification, $context);
        }
        
        // Otherwise compose from patterns and data
        $response_data = array(
            'content' => '',
            'metadata' => array(),
            'visual_cards' => array(),
            'suggestions' => array()
        );
        
        // Step 1: Get base template based on intent
        $template = $this->get_response_template($classification);
        
        // Step 2: Gather required data
        $data = $this->gather_response_data($classification);
        
        // Step 3: Merge template with data
        $response_data['content'] = $this->merge_template_with_data($template, $data, $classification);
        
        // Step 4: Add visual cards if applicable
        $response_data['visual_cards'] = $this->generate_visual_cards($classification, $data);
        
        // Step 5: Generate follow-up suggestions
        $response_data['suggestions'] = $this->generate_suggestions($classification);
        
        // Step 6: Add metadata
        $response_data['metadata'] = array(
            'intent' => $classification['intent'],
            'confidence' => $classification['confidence'],
            'data_sources' => array_keys($data),
            'response_type' => $classification['response_type'],
            'generated_at' => current_time('mysql')
        );
        
        return $response_data;
    }
    
    /**
     * Get response template based on classification
     */
    private function get_response_template($classification) {
        if (!$this->template_engine) {
            return $this->get_fallback_template($classification['intent']);
        }
        
        return $this->template_engine->get_template(
            $classification['intent'],
            $classification['response_type']
        );
    }
    
    /**
     * Gather required data for response
     */
    private function gather_response_data($classification) {
        if (!$this->data_integrator) {
            return array();
        }
        
        return $this->data_integrator->gather_data(
            $classification['data_requirements'],
            $classification['entities']
        );
    }
    
    /**
     * Merge template with data
     */
    private function merge_template_with_data($template, $data, $classification) {
        if (!$this->template_engine) {
            return $this->simple_merge($template, $data);
        }
        
        return $this->template_engine->render($template, $data, $classification);
    }
    
    /**
     * Generate visual cards based on classification and data
     */
    private function generate_visual_cards($classification, $data) {
        $cards = array();
        
        // Market data cards
        if (in_array('market_data', $classification['data_requirements'])) {
            if (!empty($data['market_data'])) {
                $cards[] = $this->create_market_card($data['market_data']);
            }
        }
        
        // Company cards
        if (!empty($classification['entities']['companies'])) {
            foreach ($classification['entities']['companies'] as $company) {
                if (!empty($data['company_data'][$company['name']])) {
                    $cards[] = $this->create_company_card(
                        $company,
                        $data['company_data'][$company['name']]
                    );
                }
            }
        }
        
        // News cards
        if (in_array('news_data', $classification['data_requirements'])) {
            if (!empty($data['news_data'])) {
                $cards[] = $this->create_news_card($data['news_data']);
            }
        }
        
        return $cards;
    }
    
    /**
     * Create market data visual card
     */
    private function create_market_card($market_data) {
        return array(
            'type' => 'market_dashboard',
            'title' => 'Market Overview',
            'data' => array(
                'indices' => $market_data['indices'] ?? array(),
                'sectors' => $market_data['sectors'] ?? array(),
                'trends' => $market_data['trends'] ?? array(),
                'timestamp' => $market_data['timestamp'] ?? current_time('mysql')
            )
        );
    }
    
    /**
     * Create company visual card
     */
    private function create_company_card($company, $company_data) {
        return array(
            'type' => 'company_profile',
            'title' => $company['name'],
            'data' => array(
                'ticker' => $company['ticker'] ?? '',
                'price' => $company_data['price'] ?? '',
                'change' => $company_data['change'] ?? '',
                'volume' => $company_data['volume'] ?? '',
                'market_cap' => $company_data['market_cap'] ?? '',
                'pe_ratio' => $company_data['pe_ratio'] ?? '',
                'news' => array_slice($company_data['news'] ?? array(), 0, 3)
            )
        );
    }
    
    /**
     * Create news visual card
     */
    private function create_news_card($news_data) {
        return array(
            'type' => 'news_feed',
            'title' => 'Latest Finance News',
            'data' => array(
                'articles' => array_slice($news_data, 0, 5),
                'source' => 'aggregated',
                'timestamp' => current_time('mysql')
            )
        );
    }
    
    /**
     * Generate follow-up suggestions
     */
    private function generate_suggestions($classification) {
        $suggestions = array();
        
        // Intent-based suggestions
        $intent_suggestions = array(
            'market_status' => array(
                'Tell me more about sector performance',
                'What is driving today\'s market movement?',
                'Show me the top gainers and losers'
            ),
            'career_guidance' => array(
                'What skills should I focus on?',
                'How do I prepare for interviews?',
                'What are the salary expectations?'
            ),
            'private_equity' => array(
                'What are recent PE deals?',
                'How do PE firms evaluate companies?',
                'What is the current fundraising environment?'
            ),
            'company_analysis' => array(
                'Compare with competitors',
                'Show me the financials',
                'What do analysts say?'
            )
        );
        
        if (isset($intent_suggestions[$classification['intent']])) {
            $suggestions = $intent_suggestions[$classification['intent']];
        }
        
        // Add entity-specific suggestions
        if (!empty($classification['entities']['companies'])) {
            $company = $classification['entities']['companies'][0];
            $suggestions[] = "Tell me more about " . $company['name'];
        }
        
        return array_slice($suggestions, 0, 3);
    }
    
    /**
     * Compose response using Claude API for complex queries
     */
    private function compose_claude_response($query, $classification, $context) {
        if (!$this->claude_api) {
            return $this->get_complex_query_fallback($query);
        }
        
        // Gather context data for Claude
        $claude_context = array(
            'entities' => $classification['entities'],
            'intent' => $classification['intent'],
            'market_data' => $this->get_current_market_context(),
            'user_context' => $context
        );
        
        // Send to Claude with rich context
        $response = $this->claude_api->send_message($query, $claude_context);
        
        // Format Claude response
        return array(
            'content' => $response,
            'metadata' => array(
                'source' => 'claude',
                'intent' => $classification['intent'],
                'complexity' => $classification['complexity'],
                'generated_at' => current_time('mysql')
            ),
            'visual_cards' => $this->extract_visual_cards_from_response($response),
            'suggestions' => $this->generate_suggestions($classification)
        );
    }
    
    /**
     * Get current market context for Claude
     */
    private function get_current_market_context() {
        if ($this->data_integrator) {
            return $this->data_integrator->get_market_summary();
        }
        return array();
    }
    
    /**
     * Extract visual cards from Claude response
     */
    private function extract_visual_cards_from_response($response) {
        // Look for structured data in Claude's response
        $cards = array();
        
        // Check for market data mentions
        if (preg_match('/S&P.*?([+-]?\\d+\\.?\\d*%)/i', $response, $matches)) {
            $cards[] = array(
                'type' => 'market_mention',
                'data' => array('index' => 'S&P 500', 'change' => $matches[1])
            );
        }
        
        return $cards;
    }
    
    /**
     * Get fallback template for intent
     */
    private function get_fallback_template($intent) {
        $templates = array(
            'market_status' => 'The markets are showing {trend} movement today. {indices_summary}',
            'career_guidance' => 'For your finance career, {key_advice}. Focus on {skills}.',
            'private_equity' => 'Private equity {current_state}. Key trends include {trends}.',
            'default' => 'Based on your query, {analysis}.'
        );
        
        return $templates[$intent] ?? $templates['default'];
    }
    
    /**
     * Simple template merge without template engine
     */
    private function simple_merge($template, $data) {
        $result = $template;
        
        // Replace variables in template
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = implode(', ', array_slice($value, 0, 3));
            }
            $result = str_replace('{' . $key . '}', $value, $result);
        }
        
        // Remove any unreplaced variables
        $result = preg_replace('/\{[^}]+\}/', '', $result);
        
        return trim($result);
    }
    
    /**
     * Get fallback for complex queries
     */
    private function get_complex_query_fallback($query) {
        return array(
            'content' => "Your question about '" . esc_html($query) . "' requires detailed analysis. " .
                        "Let me connect you with our advanced analytics system for a comprehensive response.",
            'metadata' => array(
                'source' => 'fallback',
                'reason' => 'claude_unavailable',
                'generated_at' => current_time('mysql')
            ),
            'visual_cards' => array(),
            'suggestions' => array(
                'Try a simpler question',
                'Ask about market overview',
                'Request career guidance'
            )
        );
    }
}