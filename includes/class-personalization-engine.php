<?php
/**
 * Personalization Engine - Phase 7
 * Learns user preferences and adapts responses
 * 
 * @package SennaCareers
 * @since 7.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Personalization_Engine {
    
    private static $instance = null;
    private $user_profiles = array();
    private $preference_weights = array();
    private $interaction_history = array();
    private $learning_threshold = 3; // Minimum interactions before personalizing
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->initialize_preference_weights();
        $this->load_user_profiles();
    }
    
    /**
     * Initialize preference weight system
     */
    private function initialize_preference_weights() {
        $this->preference_weights = array(
            'query_topics' => array(
                'private_equity' => 0,
                'investment_banking' => 0,
                'stock_analysis' => 0,
                'market_news' => 0,
                'career_advice' => 0,
                'educational' => 0,
                'technical_analysis' => 0,
                'fundamental_analysis' => 0
            ),
            'company_interests' => array(),
            'expertise_indicators' => array(
                'beginner' => 0,
                'intermediate' => 0,
                'expert' => 0
            ),
            'response_preferences' => array(
                'detailed_explanations' => 0,
                'quick_answers' => 0,
                'visual_content' => 0,
                'data_heavy' => 0,
                'conversational' => 0
            ),
            'interaction_patterns' => array(
                'follow_up_questions' => 0,
                'deep_dives' => 0,
                'quick_checks' => 0,
                'research_mode' => 0
            )
        );
    }
    
    /**
     * Track user interaction and update profile
     */
    public function track_interaction($session_id, $query, $analysis, $response) {
        if (!$session_id) return;
        
        // Initialize profile if new user
        if (!isset($this->user_profiles[$session_id])) {
            $this->user_profiles[$session_id] = $this->create_new_profile($session_id);
        }
        
        $profile = &$this->user_profiles[$session_id];
        
        // Update interaction count
        $profile['interaction_count']++;
        $profile['last_interaction'] = time();
        
        // Track query topics
        $this->track_query_topics($profile, $analysis);
        
        // Track company interests
        $this->track_company_interests($profile, $analysis);
        
        // Detect expertise level
        $this->detect_expertise_level($profile, $query, $analysis);
        
        // Track response preferences
        $this->track_response_preferences($profile, $response);
        
        // Update interaction patterns
        $this->update_interaction_patterns($profile, $query, $analysis);
        
        // Add to interaction history
        $this->add_to_history($session_id, $query, $analysis, $response);
        
        // Save profile
        $this->save_user_profile($session_id);
    }
    
    /**
     * Get personalization adjustments for response
     */
    public function get_personalization_adjustments($session_id, $current_analysis = array()) {
        if (!$session_id || !isset($this->user_profiles[$session_id])) {
            return $this->get_default_adjustments();
        }
        
        $profile = $this->user_profiles[$session_id];
        
        // Don't personalize until we have enough data
        if ($profile['interaction_count'] < $this->learning_threshold) {
            return $this->get_default_adjustments();
        }
        
        $adjustments = array(
            'expertise_level' => $this->determine_expertise_level($profile),
            'preferred_detail_level' => $this->determine_detail_preference($profile),
            'include_visuals' => $this->should_include_visuals($profile),
            'companies_of_interest' => $this->get_top_companies($profile),
            'topics_of_interest' => $this->get_top_topics($profile),
            'response_style' => $this->determine_response_style($profile),
            'suggested_follow_ups' => $this->generate_follow_up_suggestions($profile, $current_analysis),
            'proactive_insights' => $this->generate_proactive_insights($profile, $current_analysis)
        );
        
        return $adjustments;
    }
    
    /**
     * Track query topics
     */
    private function track_query_topics(&$profile, $analysis) {
        $topics = $this->extract_topics($analysis);
        
        foreach ($topics as $topic) {
            if (isset($profile['preferences']['query_topics'][$topic])) {
                $profile['preferences']['query_topics'][$topic]++;
            }
        }
    }
    
    /**
     * Track company interests
     */
    private function track_company_interests(&$profile, $analysis) {
        if (!empty($analysis['entities']['companies'])) {
            foreach ($analysis['entities']['companies'] as $company) {
                $company_name = $company['name'] ?? '';
                if ($company_name) {
                    if (!isset($profile['preferences']['company_interests'][$company_name])) {
                        $profile['preferences']['company_interests'][$company_name] = 0;
                    }
                    $profile['preferences']['company_interests'][$company_name]++;
                }
            }
        }
    }
    
    /**
     * Detect expertise level from query complexity
     */
    private function detect_expertise_level(&$profile, $query, $analysis) {
        $query_lower = strtolower($query);
        
        // Beginner indicators
        if (strpos($query_lower, 'what is') !== false ||
            strpos($query_lower, 'explain') !== false ||
            strpos($query_lower, 'how does') !== false ||
            strpos($query_lower, 'basics') !== false) {
            $profile['preferences']['expertise_indicators']['beginner']++;
        }
        
        // Expert indicators
        if (strpos($query_lower, 'leverage ratio') !== false ||
            strpos($query_lower, 'irr') !== false ||
            strpos($query_lower, 'moic') !== false ||
            strpos($query_lower, 'lbo model') !== false ||
            strpos($query_lower, 'waterfall') !== false ||
            strpos($query_lower, 'carry') !== false) {
            $profile['preferences']['expertise_indicators']['expert']++;
        }
        
        // Check for technical terms in entities
        if (!empty($analysis['entities']['financial_terms'])) {
            foreach ($analysis['entities']['financial_terms'] as $term) {
                $complexity = $term['complexity'] ?? 'intermediate';
                if ($complexity === 'advanced') {
                    $profile['preferences']['expertise_indicators']['expert']++;
                } elseif ($complexity === 'basic') {
                    $profile['preferences']['expertise_indicators']['beginner']++;
                } else {
                    $profile['preferences']['expertise_indicators']['intermediate']++;
                }
            }
        }
    }
    
    /**
     * Track response preferences
     */
    private function track_response_preferences(&$profile, $response) {
        // Track if user prefers visual content
        if (isset($response['visual_card'])) {
            $profile['preferences']['response_preferences']['visual_content']++;
        }
        
        // Track detail level preference based on response length
        if (isset($response['message'])) {
            $message_length = strlen($response['message']);
            if ($message_length > 500) {
                $profile['preferences']['response_preferences']['detailed_explanations']++;
            } else {
                $profile['preferences']['response_preferences']['quick_answers']++;
            }
        }
        
        // Track data preference
        if (isset($response['data']) && !empty($response['data'])) {
            $profile['preferences']['response_preferences']['data_heavy']++;
        }
    }
    
    /**
     * Update interaction patterns
     */
    private function update_interaction_patterns(&$profile, $query, $analysis) {
        // Check if this is a follow-up question
        if ($this->is_follow_up_question($profile, $analysis)) {
            $profile['preferences']['interaction_patterns']['follow_up_questions']++;
        }
        
        // Check for deep dive pattern
        if ($this->is_deep_dive($profile, $analysis)) {
            $profile['preferences']['interaction_patterns']['deep_dives']++;
        }
        
        // Check for quick check pattern
        if ($analysis['response_type'] === 'stock_price_response' ||
            strlen($query) < 50) {
            $profile['preferences']['interaction_patterns']['quick_checks']++;
        }
        
        // Check for research mode
        if (strpos(strtolower($query), 'research') !== false ||
            strpos(strtolower($query), 'analyze') !== false ||
            strpos(strtolower($query), 'compare') !== false) {
            $profile['preferences']['interaction_patterns']['research_mode']++;
        }
    }
    
    /**
     * Determine user's expertise level
     */
    private function determine_expertise_level($profile) {
        $indicators = $profile['preferences']['expertise_indicators'];
        
        // Find the highest indicator
        $max_value = max($indicators);
        $expertise_level = 'intermediate'; // default
        
        foreach ($indicators as $level => $count) {
            if ($count == $max_value && $count > 0) {
                $expertise_level = $level;
                break;
            }
        }
        
        return $expertise_level;
    }
    
    /**
     * Determine detail preference
     */
    private function determine_detail_preference($profile) {
        $detailed = $profile['preferences']['response_preferences']['detailed_explanations'] ?? 0;
        $quick = $profile['preferences']['response_preferences']['quick_answers'] ?? 0;
        
        if ($detailed > $quick * 1.5) {
            return 'detailed';
        } elseif ($quick > $detailed * 1.5) {
            return 'concise';
        }
        
        return 'balanced';
    }
    
    /**
     * Should include visuals
     */
    private function should_include_visuals($profile) {
        $visual_count = $profile['preferences']['response_preferences']['visual_content'] ?? 0;
        $total_interactions = $profile['interaction_count'];
        
        // Include visuals if user has shown preference (>30% of interactions)
        return ($total_interactions > 0 && ($visual_count / $total_interactions) > 0.3);
    }
    
    /**
     * Get top companies of interest
     */
    private function get_top_companies($profile, $limit = 3) {
        $companies = $profile['preferences']['company_interests'] ?? array();
        arsort($companies);
        return array_slice(array_keys($companies), 0, $limit);
    }
    
    /**
     * Get top topics of interest
     */
    private function get_top_topics($profile, $limit = 3) {
        $topics = $profile['preferences']['query_topics'] ?? array();
        arsort($topics);
        $top_topics = array();
        
        foreach ($topics as $topic => $count) {
            if ($count > 0) {
                $top_topics[] = $topic;
                if (count($top_topics) >= $limit) break;
            }
        }
        
        return $top_topics;
    }
    
    /**
     * Determine response style
     */
    private function determine_response_style($profile) {
        $patterns = $profile['preferences']['interaction_patterns'] ?? array();
        
        // Determine primary interaction style
        if (($patterns['research_mode'] ?? 0) > 5) {
            return 'analytical';
        } elseif (($patterns['quick_checks'] ?? 0) > 10) {
            return 'efficient';
        } elseif (($patterns['follow_up_questions'] ?? 0) > 5) {
            return 'conversational';
        } elseif (($patterns['deep_dives'] ?? 0) > 3) {
            return 'comprehensive';
        }
        
        return 'balanced';
    }
    
    /**
     * Generate follow-up suggestions
     */
    private function generate_follow_up_suggestions($profile, $current_analysis) {
        $suggestions = array();
        $topics = $this->get_top_topics($profile);
        $companies = $this->get_top_companies($profile);
        
        // Suggest based on current query context
        if (!empty($current_analysis['entities']['companies'])) {
            $current_company = $current_analysis['entities']['companies'][0]['name'] ?? '';
            if ($current_company) {
                $suggestions[] = "Would you like to see {$current_company}'s competitors?";
                
                if (in_array('technical_analysis', $topics)) {
                    $suggestions[] = "Want to see technical indicators for {$current_company}?";
                }
            }
        }
        
        // Suggest based on interests
        if (in_array('private_equity', $topics)) {
            $suggestions[] = "Check out recent PE deals in this sector";
        }
        
        if (in_array('career_advice', $topics)) {
            $suggestions[] = "Explore career opportunities in this field";
        }
        
        return array_slice($suggestions, 0, 2);
    }
    
    /**
     * Generate proactive insights
     */
    private function generate_proactive_insights($profile, $current_analysis) {
        $insights = array();
        $companies = $this->get_top_companies($profile);
        
        // Generate insights based on user's interests
        foreach ($companies as $company) {
            // In production, this would check real data
            $insights[] = array(
                'type' => 'company_update',
                'company' => $company,
                'message' => "Since you often track {$company}, you might be interested in today's 2.3% gain following earnings beat."
            );
        }
        
        // Add topic-based insights
        $topics = $this->get_top_topics($profile);
        if (in_array('private_equity', $topics)) {
            $insights[] = array(
                'type' => 'market_trend',
                'topic' => 'private_equity',
                'message' => "PE dry powder reaches record $3.7T - opportunities in healthcare and tech sectors."
            );
        }
        
        return array_slice($insights, 0, 1); // Return only most relevant
    }
    
    /**
     * Extract topics from analysis
     */
    private function extract_topics($analysis) {
        $topics = array();
        
        // Map response types to topics
        $type_to_topic = array(
            'stock_price_response' => 'stock_analysis',
            'concept_explanation' => 'educational',
            'comparison_response' => 'fundamental_analysis',
            'analytical_response' => 'technical_analysis',
            'recommendation_response' => 'investment_banking'
        );
        
        if (isset($analysis['response_type']) && isset($type_to_topic[$analysis['response_type']])) {
            $topics[] = $type_to_topic[$analysis['response_type']];
        }
        
        // Check for PE-related content
        if (!empty($analysis['entities']['financial_terms'])) {
            foreach ($analysis['entities']['financial_terms'] as $term) {
                if ($term['category'] === 'pe_related') {
                    $topics[] = 'private_equity';
                    break;
                }
            }
        }
        
        // Check for career-related queries
        if (isset($analysis['intent']) && is_string($analysis['intent']) && strpos($analysis['intent'], 'career') !== false) {
            $topics[] = 'career_advice';
        }
        
        // Check for news-related queries
        if (isset($analysis['intent']) && is_string($analysis['intent']) && strpos($analysis['intent'], 'news') !== false) {
            $topics[] = 'market_news';
        }
        
        return array_unique($topics);
    }
    
    /**
     * Check if this is a follow-up question
     */
    private function is_follow_up_question($profile, $analysis) {
        if (empty($this->interaction_history[$profile['session_id']])) {
            return false;
        }
        
        $history = $this->interaction_history[$profile['session_id']];
        if (count($history) < 2) {
            return false;
        }
        
        $last_interaction = end($history);
        $current_time = time();
        
        // Check if within 5 minutes and related topic
        if (($current_time - $last_interaction['timestamp']) < 300) {
            // Check for topic continuity
            if (!empty($analysis['entities']['companies']) && !empty($last_interaction['companies'])) {
                $current_companies = array_column($analysis['entities']['companies'], 'name');
                $overlap = array_intersect($current_companies, $last_interaction['companies']);
                return !empty($overlap);
            }
        }
        
        return false;
    }
    
    /**
     * Check if user is in deep dive mode
     */
    private function is_deep_dive($profile, $analysis) {
        if (empty($this->interaction_history[$profile['session_id']])) {
            return false;
        }
        
        $history = $this->interaction_history[$profile['session_id']];
        if (count($history) < 3) {
            return false;
        }
        
        // Check last 3 interactions for same topic/company
        $recent = array_slice($history, -3);
        $topics = array();
        
        foreach ($recent as $interaction) {
            if (!empty($interaction['topics'])) {
                $topics = array_merge($topics, $interaction['topics']);
            }
        }
        
        // Count topic frequency
        $topic_counts = array_count_values($topics);
        if (empty($topic_counts)) {
            return false;
        }
        $max_count = max($topic_counts);
        
        // Deep dive if same topic appears in all recent interactions
        return $max_count >= 3;
    }
    
    /**
     * Add to interaction history
     */
    private function add_to_history($session_id, $query, $analysis, $response) {
        if (!isset($this->interaction_history[$session_id])) {
            $this->interaction_history[$session_id] = array();
        }
        
        $this->interaction_history[$session_id][] = array(
            'timestamp' => time(),
            'query' => $query,
            'topics' => $this->extract_topics($analysis),
            'companies' => !empty($analysis['entities']['companies']) 
                ? array_column($analysis['entities']['companies'], 'name') 
                : array(),
            'response_type' => $analysis['response_type'] ?? 'general'
        );
        
        // Keep only last 20 interactions in memory
        if (count($this->interaction_history[$session_id]) > 20) {
            $this->interaction_history[$session_id] = array_slice(
                $this->interaction_history[$session_id], 
                -20
            );
        }
    }
    
    /**
     * Create new user profile
     */
    private function create_new_profile($session_id) {
        return array(
            'session_id' => $session_id,
            'created_at' => time(),
            'last_interaction' => time(),
            'interaction_count' => 0,
            'preferences' => $this->preference_weights
        );
    }
    
    /**
     * Get default adjustments
     */
    private function get_default_adjustments() {
        return array(
            'expertise_level' => 'intermediate',
            'preferred_detail_level' => 'balanced',
            'include_visuals' => true,
            'companies_of_interest' => array(),
            'topics_of_interest' => array(),
            'response_style' => 'balanced',
            'suggested_follow_ups' => array(),
            'proactive_insights' => array()
        );
    }
    
    /**
     * Load user profiles from storage
     */
    private function load_user_profiles() {
        // In production, load from database
        // For now, use transient storage
        if (defined('SFFC_TEST_MODE') && SFFC_TEST_MODE) {
            $this->user_profiles = array();
        } else {
            $profiles = get_transient('sffc_user_profiles');
            if ($profiles && is_array($profiles)) {
                $this->user_profiles = $profiles;
            }
        }
    }
    
    /**
     * Save user profile
     */
    private function save_user_profile($session_id) {
        // In production, save to database
        // For now, use transient storage
        if (!defined('SFFC_TEST_MODE') || !SFFC_TEST_MODE) {
            set_transient('sffc_user_profiles', $this->user_profiles, DAY_IN_SECONDS);
        }
    }
    
    /**
     * Get user statistics for debugging
     */
    public function get_user_stats($session_id) {
        if (!isset($this->user_profiles[$session_id])) {
            return null;
        }
        
        $profile = $this->user_profiles[$session_id];
        
        return array(
            'interaction_count' => $profile['interaction_count'],
            'expertise_level' => $this->determine_expertise_level($profile),
            'top_topics' => $this->get_top_topics($profile, 5),
            'top_companies' => $this->get_top_companies($profile, 5),
            'response_style' => $this->determine_response_style($profile),
            'detail_preference' => $this->determine_detail_preference($profile),
            'visual_preference' => $this->should_include_visuals($profile)
        );
    }
}