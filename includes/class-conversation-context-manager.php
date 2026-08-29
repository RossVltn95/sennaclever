<?php
/**
 * Conversation Context Manager - Phase 4
 * Maintains conversation state and context across messages
 * 
 * @package SennaCareers
 * @since 4.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Conversation_Context_Manager {
    
    private static $instance = null;
    private $session_manager;
    private $current_context = array();
    private $conversation_history = array();
    private $max_history = 20;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Load session manager
        if (!class_exists('SFFC_Session_Manager')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-session-manager.php';
        }
        $this->session_manager = SFFC_Session_Manager::get_instance();
        
        // Initialize context
        $this->initialize_context();
    }
    
    /**
     * Initialize or restore conversation context
     */
    private function initialize_context() {
        $session_id = $this->session_manager->get_session_id();
        
        // Try to restore from session
        $stored_context = get_transient('sffc_context_' . $session_id);
        if ($stored_context) {
            $this->current_context = $stored_context['context'];
            $this->conversation_history = $stored_context['history'];
        } else {
            $this->reset_context();
        }
    }
    
    /**
     * Reset conversation context
     */
    public function reset_context() {
        $this->current_context = array(
            'session_id' => $this->session_manager->get_session_id(),
            'started_at' => time(),
            'last_activity' => time(),
            'current_topic' => null,
            'current_company' => null,
            'current_sector' => null,
            'entities_mentioned' => array(),
            'topics_discussed' => array(),
            'user_preferences' => array(),
            'conversation_mode' => 'general',
            'expertise_level' => 'intermediate'
        );
        
        $this->conversation_history = array();
    }
    
    /**
     * Update context with new message
     */
    public function update_context($query, $analysis, $response) {
        // Update timestamp
        $this->current_context['last_activity'] = time();
        
        // Add to history
        $this->add_to_history(array(
            'timestamp' => time(),
            'query' => $query,
            'intent' => $analysis['intent'] ?? array(),
            'entities' => $analysis['entities'] ?? array(),
            'response_type' => $analysis['response_type'] ?? 'general',
            'response_summary' => $this->summarize_response($response)
        ));
        
        // Update current topic based on query
        $this->update_current_topic($analysis);
        
        // Track entities mentioned
        $this->track_entities($analysis['entities'] ?? array());
        
        // Update conversation mode
        $this->determine_conversation_mode($analysis);
        
        // Detect expertise level
        $this->detect_expertise_level($query, $analysis);
        
        // Save context
        $this->save_context();
    }
    
    /**
     * Add message to history
     */
    private function add_to_history($entry) {
        array_unshift($this->conversation_history, $entry);
        
        // Limit history size
        if (count($this->conversation_history) > $this->max_history) {
            array_pop($this->conversation_history);
        }
    }
    
    /**
     * Update current topic based on analysis
     */
    private function update_current_topic($analysis) {
        // Extract primary topic from entities and intent
        if (!empty($analysis['entities']['companies'])) {
            $company = $analysis['entities']['companies'][0];
            $this->current_context['current_company'] = $company['name'];
            $this->current_context['current_topic'] = 'company_analysis';
        } elseif (!empty($analysis['entities']['financial_terms'])) {
            $term = $analysis['entities']['financial_terms'][0];
            if ($term['category'] === 'pe_related') {
                $this->current_context['current_topic'] = 'private_equity';
            } elseif ($term['category'] === 'market_related') {
                $this->current_context['current_topic'] = 'market_analysis';
            }
        }
        
        // Track topic in history
        if ($this->current_context['current_topic']) {
            if (!in_array($this->current_context['current_topic'], $this->current_context['topics_discussed'])) {
                $this->current_context['topics_discussed'][] = $this->current_context['current_topic'];
            }
        }
    }
    
    /**
     * Track entities mentioned in conversation
     */
    private function track_entities($entities) {
        if (!empty($entities['companies'])) {
            foreach ($entities['companies'] as $company) {
                $key = $company['ticker'] ?? $company['name'];
                if (!isset($this->current_context['entities_mentioned']['companies'][$key])) {
                    $this->current_context['entities_mentioned']['companies'][$key] = array(
                        'name' => $company['name'],
                        'ticker' => $company['ticker'] ?? null,
                        'mention_count' => 0,
                        'first_mentioned' => time(),
                        'last_mentioned' => time()
                    );
                }
                $this->current_context['entities_mentioned']['companies'][$key]['mention_count']++;
                $this->current_context['entities_mentioned']['companies'][$key]['last_mentioned'] = time();
            }
        }
    }
    
    /**
     * Determine conversation mode
     */
    private function determine_conversation_mode($analysis) {
        $intent = $analysis['intent'] ?? array();
        
        if (in_array('data_request', $intent) && !empty($analysis['entities']['companies'])) {
            $this->current_context['conversation_mode'] = 'market_data';
        } elseif (in_array('explanation', $intent)) {
            $this->current_context['conversation_mode'] = 'educational';
        } elseif (in_array('analysis', $intent)) {
            $this->current_context['conversation_mode'] = 'analytical';
        } elseif (in_array('recommendation', $intent)) {
            $this->current_context['conversation_mode'] = 'advisory';
        }
    }
    
    /**
     * Detect user expertise level
     */
    private function detect_expertise_level($query, $analysis) {
        $technical_terms = array('IRR', 'EBITDA', 'LBO', 'DCF', 'multiple', 'basis points', 'carry', 'GP', 'LP', 'SOFR', 'leverage');
        $intermediate_terms = array('j-curve', 'fund performance', 'carried interest', 'portfolio', 'returns');
        $query_lower = strtolower($query);
        
        $technical_count = 0;
        foreach ($technical_terms as $term) {
            if (stripos($query_lower, strtolower($term)) !== false) {
                $technical_count++;
            }
        }
        
        $intermediate_count = 0;
        foreach ($intermediate_terms as $term) {
            if (stripos($query_lower, strtolower($term)) !== false) {
                $intermediate_count++;
            }
        }
        
        // Adjust expertise level based on technical terms used
        if ($technical_count >= 2) {
            $this->current_context['expertise_level'] = 'expert';
        } elseif ($technical_count === 1 || $intermediate_count >= 1) {
            $this->current_context['expertise_level'] = 'intermediate';
        } elseif ((stripos($query_lower, 'what is') !== false || stripos($query_lower, 'explain') !== false) && 
                 $technical_count === 0 && $intermediate_count === 0) {
            // Basic question with no technical terms indicates beginner
            $this->current_context['expertise_level'] = 'beginner';
        }
    }
    
    /**
     * Resolve pronouns based on context
     */
    public function resolve_pronouns($query) {
        $resolved_query = $query;
        $query_lower = strtolower($query);
        
        // Pronoun patterns
        $pronouns = array(
            'it' => $this->current_context['current_company'] ?? $this->current_context['current_topic'],
            'its' => $this->current_context['current_company'] ?? $this->current_context['current_topic'],
            'they' => $this->get_last_mentioned_companies(),
            'their' => $this->get_last_mentioned_companies(),
            'this' => $this->get_last_topic(),
            'that' => $this->get_last_topic()
        );
        
        // Replace pronouns with context
        foreach ($pronouns as $pronoun => $replacement) {
            if ($replacement && preg_match('/\b' . $pronoun . '\b/i', $query_lower)) {
                // Handle "What about its competitors?" → "What about Barclays competitors?"
                $resolved_query = preg_replace('/\b' . $pronoun . '\b/i', $replacement, $resolved_query);
            }
        }
        
        // Handle follow-up patterns
        if (preg_match('/^(and|also|what about|how about|additionally)/i', $query_lower)) {
            // This is a follow-up question
            if ($this->current_context['current_company']) {
                // Add company context if not present
                if (stripos($query_lower, $this->current_context['current_company']) === false) {
                    $resolved_query = $resolved_query . ' (regarding ' . $this->current_context['current_company'] . ')';
                }
            }
        }
        
        return $resolved_query;
    }
    
    /**
     * Get context-aware response adjustments
     */
    public function get_response_adjustments() {
        return array(
            'expertise_level' => $this->current_context['expertise_level'],
            'conversation_mode' => $this->current_context['conversation_mode'],
            'current_topic' => $this->current_context['current_topic'],
            'current_company' => $this->current_context['current_company'],
            'topics_discussed' => $this->current_context['topics_discussed'],
            'include_definitions' => ($this->current_context['expertise_level'] === 'beginner'),
            'include_technical_details' => ($this->current_context['expertise_level'] === 'expert'),
            'reference_previous' => $this->should_reference_previous()
        );
    }
    
    /**
     * Check if should reference previous conversation
     */
    private function should_reference_previous() {
        if (count($this->conversation_history) < 2) {
            return false;
        }
        
        // Check if current query relates to previous
        $current_entities = $this->get_recent_entities(1);
        $previous_entities = $this->get_recent_entities(2);
        
        // If discussing same company/topic, reference it
        return !empty(array_intersect($current_entities, $previous_entities));
    }
    
    /**
     * Get recently mentioned entities
     */
    private function get_recent_entities($limit = 1) {
        $entities = array();
        $count = 0;
        
        foreach ($this->conversation_history as $entry) {
            if ($count >= $limit) break;
            
            if (!empty($entry['entities']['companies'])) {
                foreach ($entry['entities']['companies'] as $company) {
                    $entities[] = $company['name'];
                }
            }
            $count++;
        }
        
        return array_unique($entities);
    }
    
    /**
     * Get last mentioned companies
     */
    private function get_last_mentioned_companies() {
        if (!empty($this->conversation_history[0]['entities']['companies'])) {
            $companies = array();
            foreach ($this->conversation_history[0]['entities']['companies'] as $company) {
                $companies[] = $company['name'];
            }
            return implode(' and ', $companies);
        }
        return null;
    }
    
    /**
     * Get last topic discussed
     */
    private function get_last_topic() {
        if (!empty($this->conversation_history[0]['response_type'])) {
            return $this->conversation_history[0]['response_type'];
        }
        return $this->current_context['current_topic'];
    }
    
    /**
     * Get conversation summary
     */
    public function get_conversation_summary() {
        $summary = array(
            'duration' => time() - $this->current_context['started_at'],
            'message_count' => count($this->conversation_history),
            'topics_discussed' => $this->current_context['topics_discussed'],
            'companies_mentioned' => array_keys($this->current_context['entities_mentioned']['companies'] ?? array()),
            'expertise_level' => $this->current_context['expertise_level'],
            'conversation_mode' => $this->current_context['conversation_mode']
        );
        
        // Add most discussed company
        if (!empty($this->current_context['entities_mentioned']['companies'])) {
            $most_mentioned = null;
            $max_count = 0;
            foreach ($this->current_context['entities_mentioned']['companies'] as $key => $data) {
                if ($data['mention_count'] > $max_count) {
                    $max_count = $data['mention_count'];
                    $most_mentioned = $data['name'];
                }
            }
            $summary['primary_focus'] = $most_mentioned;
        }
        
        return $summary;
    }
    
    /**
     * Save context to storage
     */
    private function save_context() {
        $session_id = $this->session_manager->get_session_id();
        
        set_transient('sffc_context_' . $session_id, array(
            'context' => $this->current_context,
            'history' => $this->conversation_history
        ), 3600); // 1 hour expiry
    }
    
    /**
     * Summarize response for history
     */
    private function summarize_response($response) {
        if (is_array($response) && isset($response['message'])) {
            // Extract first 100 chars
            return substr($response['message'], 0, 100);
        }
        return '';
    }
    
    /**
     * Get current context
     */
    public function get_context() {
        return $this->current_context;
    }
    
    /**
     * Get conversation history
     */
    public function get_history() {
        return $this->conversation_history;
    }
}