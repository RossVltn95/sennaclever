<?php
/**
 * Query Classification Engine
 * Phase 2: Classifies queries and routes to appropriate response handlers
 * 
 * @package SennaCareers
 * @since 6.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Query_Classifier {
    
    private static $instance = null;
    private $pattern_library;
    private $entity_extractor;
    private $context_analyzer;
    
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
        // Load pattern library
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/patterns/class-pattern-library.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/patterns/class-pattern-library.php';
            $this->pattern_library = SFFC_Pattern_Library::get_instance();
        }
        
        // Load entity extractor
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/patterns/class-entity-extractor.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/patterns/class-entity-extractor.php';
            $this->entity_extractor = SFFC_Entity_Extractor::get_instance();
        }
        
        // Load context analyzer
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/patterns/class-context-analyzer.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/patterns/class-context-analyzer.php';
            $this->context_analyzer = SFFC_Context_Analyzer::get_instance();
        }
    }
    
    /**
     * Classify a query and determine response approach
     */
    public function classify($query, $conversation_history = array()) {
        $classification = array(
            'query' => $query,
            'intent' => null,
            'entities' => array(),
            'context' => array(),
            'complexity' => 0,
            'confidence' => 0,
            'response_type' => 'template',
            'data_requirements' => array(),
            'requires_claude' => false,
            'timestamp' => current_time('mysql')
        );
        
        // Step 1: Pattern matching
        if ($this->pattern_library) {
            $pattern_match = $this->pattern_library->match_query($query);
            $classification['intent'] = $pattern_match['intent'];
            $classification['confidence'] = $pattern_match['confidence'];
            
            // Get data requirements for this intent
            if ($pattern_match['intent']) {
                $classification['data_requirements'] = $this->pattern_library->get_required_data($pattern_match['intent']);
            }
        }
        
        // Step 2: Entity extraction
        if ($this->entity_extractor) {
            $classification['entities'] = $this->entity_extractor->extract_entities($query);
            
            // Extract relationships if multiple entities
            if (count($classification['entities']) > 1) {
                $classification['relationships'] = $this->entity_extractor->extract_relationships($query, $classification['entities']);
            }
        }
        
        // Step 3: Context analysis
        if ($this->context_analyzer) {
            $classification['context'] = $this->context_analyzer->analyze_context(
                $query,
                $classification['entities'],
                $conversation_history
            );
            
            $classification['complexity'] = $classification['context']['complexity_score'] ?? 0;
        }
        
        // Step 4: Determine response approach
        $classification = $this->determine_response_approach($classification);
        
        // Step 5: Log the classification for learning
        $this->log_classification($classification);
        
        return $classification;
    }
    
    /**
     * Determine how to respond to the query
     */
    private function determine_response_approach($classification) {
        // High complexity queries need Claude
        if ($classification['complexity'] >= 7) {
            $classification['requires_claude'] = true;
            $classification['response_type'] = 'claude_analysis';
            $classification['reason'] = 'High complexity query requiring deep analysis';
            return $classification;
        }
        
        // Specific company analysis needs Claude
        if (!empty($classification['entities']['companies'])) {
            $companies = $classification['entities']['companies'];
            foreach ($companies as $company) {
                // If asking for specific analysis of a company
                if (preg_match('/analyze|evaluation|deep.*dive|detailed/i', $classification['query'])) {
                    $classification['requires_claude'] = true;
                    $classification['response_type'] = 'claude_analysis';
                    $classification['reason'] = 'Specific company analysis requested';
                    return $classification;
                }
            }
        }
        
        // Forward-looking predictions need Claude
        if ($classification['intent'] === 'prediction' && 
            !empty($classification['entities']) && 
            $classification['confidence'] < 0.7) {
            $classification['requires_claude'] = true;
            $classification['response_type'] = 'claude_analysis';
            $classification['reason'] = 'Complex prediction requiring analysis';
            return $classification;
        }
        
        // Multi-entity comparisons might need Claude
        if ($classification['intent'] === 'comparison' && 
            count($classification['entities']) > 2) {
            $classification['requires_claude'] = true;
            $classification['response_type'] = 'claude_analysis';
            $classification['reason'] = 'Multi-entity comparison';
            return $classification;
        }
        
        // Causal explanations often need Claude
        if ($classification['intent'] === 'explanation' && 
            preg_match('/why|cause|reason|explain.*in.*detail/i', $classification['query'])) {
            $classification['requires_claude'] = true;
            $classification['response_type'] = 'claude_analysis';
            $classification['reason'] = 'Causal explanation requested';
            return $classification;
        }
        
        // Career guidance with specific scenarios
        if ($classification['intent'] === 'career_guidance' && 
            preg_match('/my|I|me|personal|specific/i', $classification['query'])) {
            $classification['requires_claude'] = true;
            $classification['response_type'] = 'claude_analysis';
            $classification['reason'] = 'Personalized career guidance';
            return $classification;
        }
        
        // Otherwise, use template/data responses
        $classification['requires_claude'] = false;
        
        // Determine template type based on intent
        switch ($classification['intent']) {
            case 'market_status':
            case 'sector_analysis':
            case 'volatility_risk':
                $classification['response_type'] = 'real_data_response';
                break;
                
            case 'private_equity':
            case 'mergers_acquisitions':
            case 'earnings':
                $classification['response_type'] = 'news_based_response';
                break;
                
            case 'career_guidance':
            case 'recommendation':
                $classification['response_type'] = 'template_response';
                break;
                
            default:
                // For unknown intents with low complexity, use template
                if ($classification['complexity'] < 3) {
                    $classification['response_type'] = 'template_response';
                } else {
                    // Medium complexity unknown queries might need Claude
                    $classification['requires_claude'] = true;
                    $classification['response_type'] = 'claude_analysis';
                    $classification['reason'] = 'Unmatched pattern with medium complexity';
                }
        }
        
        return $classification;
    }
    
    /**
     * Log classification for pattern learning
     */
    private function log_classification($classification) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'sffc_pattern_history';
        
        // Prepare data for logging
        $log_data = array(
            'user_query' => substr($classification['query'], 0, 500),
            'detected_pattern' => $classification['intent'] ?? 'unknown',
            'entities_extracted' => json_encode($classification['entities']),
            'response_template_used' => $classification['response_type'],
            'response_source' => $classification['requires_claude'] ? 'claude' : 'template',
            'user_session' => $this->get_session_id(),
            'created_at' => current_time('mysql')
        );
        
        $wpdb->insert(
            $table,
            $log_data,
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Get or create session ID
     */
    private function get_session_id() {
        if (!session_id()) {
            session_start();
        }
        
        if (!isset($_SESSION['sffc_session_id'])) {
            $_SESSION['sffc_session_id'] = wp_generate_uuid4();
        }
        
        return $_SESSION['sffc_session_id'];
    }
    
    /**
     * Quick classification for routing (lighter weight)
     */
    public function quick_classify($query) {
        // Simple classification for quick routing decisions
        $classification = array(
            'is_simple' => true,
            'needs_data' => false,
            'intent_type' => 'unknown'
        );
        
        $query_lower = strtolower($query);
        $word_count = str_word_count($query);
        
        // Very simple queries
        if ($word_count <= 3) {
            $classification['is_simple'] = true;
            
            // Check for common simple patterns
            if (preg_match('/^(hi|hello|hey|thanks|ok|bye)$/i', trim($query))) {
                $classification['intent_type'] = 'greeting';
            } elseif (preg_match('/market|S&P|Nasdaq|Dow/i', $query)) {
                $classification['intent_type'] = 'market_check';
                $classification['needs_data'] = true;
            }
            
            return $classification;
        }
        
        // Check for data requirements
        if (preg_match('/price|value|trading|today|now|current/i', $query)) {
            $classification['needs_data'] = true;
        }
        
        // Check complexity indicators
        if ($word_count > 15 || 
            preg_match('/why|explain|analyze|compare|forecast/i', $query) ||
            substr_count($query, ',') > 2) {
            $classification['is_simple'] = false;
        }
        
        // Identify broad intent category
        if (preg_match('/career|job|interview|salary/i', $query)) {
            $classification['intent_type'] = 'career';
        } elseif (preg_match('/market|index|stock|sector/i', $query)) {
            $classification['intent_type'] = 'market';
        } elseif (preg_match('/PE|private equity|buyout|LBO/i', $query)) {
            $classification['intent_type'] = 'private_equity';
        } elseif (preg_match('/M&A|merger|acquisition|deal/i', $query)) {
            $classification['intent_type'] = 'ma';
        }
        
        return $classification;
    }
    
    /**
     * Get classification statistics
     */
    public function get_statistics($timeframe = '7 days') {
        global $wpdb;
        
        $table = $wpdb->prefix . 'sffc_pattern_history';
        
        // Get pattern distribution
        $patterns = $wpdb->get_results($wpdb->prepare(
            "SELECT detected_pattern, COUNT(*) as count 
             FROM $table 
             WHERE created_at > DATE_SUB(NOW(), INTERVAL %s)
             GROUP BY detected_pattern 
             ORDER BY count DESC",
            $timeframe
        ));
        
        // Get response source distribution
        $sources = $wpdb->get_results($wpdb->prepare(
            "SELECT response_source, COUNT(*) as count 
             FROM $table 
             WHERE created_at > DATE_SUB(NOW(), INTERVAL %s)
             GROUP BY response_source",
            $timeframe
        ));
        
        // Get total queries
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
             FROM $table 
             WHERE created_at > DATE_SUB(NOW(), INTERVAL %s)",
            $timeframe
        ));
        
        return array(
            'total_queries' => $total,
            'pattern_distribution' => $patterns,
            'source_distribution' => $sources,
            'timeframe' => $timeframe
        );
    }
}