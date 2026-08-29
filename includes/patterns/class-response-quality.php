<?php
/**
 * Response Quality Assurance - Phase 3
 * Validates and ensures quality of generated responses
 * 
 * @package SennaCareers
 * @since 6.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Response_Quality {
    
    private static $instance = null;
    private $validation_rules = array();
    private $quality_metrics = array();
    private $fact_checker = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->initialize_rules();
        $this->load_fact_checker();
    }
    
    /**
     * Initialize validation rules
     */
    private function initialize_rules() {
        // Content rules
        $this->validation_rules['content'] = array(
            'min_length' => 20,
            'max_length' => 2000,
            'no_placeholders' => true,
            'no_broken_variables' => true,
            'proper_formatting' => true
        );
        
        // Data accuracy rules
        $this->validation_rules['data'] = array(
            'numbers_valid' => true,
            'dates_valid' => true,
            'percentages_reasonable' => true,
            'prices_formatted' => true
        );
        
        // Coherence rules
        $this->validation_rules['coherence'] = array(
            'complete_sentences' => true,
            'logical_flow' => true,
            'consistent_tense' => true,
            'no_contradictions' => true
        );
        
        // Relevance rules
        $this->validation_rules['relevance'] = array(
            'matches_intent' => true,
            'addresses_query' => true,
            'appropriate_detail' => true,
            'timely_data' => true
        );
    }
    
    /**
     * Load fact checker dependency
     */
    private function load_fact_checker() {
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/patterns/class-fact-checker.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/patterns/class-fact-checker.php';
            $this->fact_checker = SFFC_Fact_Checker::get_instance();
        }
    }
    
    /**
     * Validate response quality
     */
    public function validate_response($response, $classification, $query) {
        $validation_result = array(
            'is_valid' => true,
            'score' => 100,
            'issues' => array(),
            'suggestions' => array()
        );
        
        // Check content quality
        $content_check = $this->check_content_quality($response);
        if (!$content_check['passed']) {
            $validation_result['is_valid'] = false;
            $validation_result['score'] -= $content_check['penalty'];
            $validation_result['issues'] = array_merge(
                $validation_result['issues'],
                $content_check['issues']
            );
        }
        
        // Check data accuracy
        $data_check = $this->check_data_accuracy($response);
        if (!$data_check['passed']) {
            $validation_result['score'] -= $data_check['penalty'];
            $validation_result['issues'] = array_merge(
                $validation_result['issues'],
                $data_check['issues']
            );
        }
        
        // Check coherence
        $coherence_check = $this->check_coherence($response);
        if (!$coherence_check['passed']) {
            $validation_result['score'] -= $coherence_check['penalty'];
            $validation_result['issues'] = array_merge(
                $validation_result['issues'],
                $coherence_check['issues']
            );
        }
        
        // Check relevance
        $relevance_check = $this->check_relevance($response, $classification, $query);
        if (!$relevance_check['passed']) {
            $validation_result['score'] -= $relevance_check['penalty'];
            $validation_result['issues'] = array_merge(
                $validation_result['issues'],
                $relevance_check['issues']
            );
        }
        
        // Generate improvement suggestions
        if (!empty($validation_result['issues'])) {
            $validation_result['suggestions'] = $this->generate_suggestions(
                $validation_result['issues']
            );
        }
        
        // Set final validity based on score
        $validation_result['is_valid'] = $validation_result['score'] >= 70;
        
        return $validation_result;
    }
    
    /**
     * Check content quality
     */
    private function check_content_quality($response) {
        $result = array(
            'passed' => true,
            'penalty' => 0,
            'issues' => array()
        );
        
        $content = isset($response['content']) ? $response['content'] : '';
        
        // Check minimum length
        if (strlen($content) < $this->validation_rules['content']['min_length']) {
            $result['passed'] = false;
            $result['penalty'] += 30;
            $result['issues'][] = 'Response too short';
        }
        
        // Check maximum length
        if (strlen($content) > $this->validation_rules['content']['max_length']) {
            $result['penalty'] += 10;
            $result['issues'][] = 'Response too long';
        }
        
        // Check for placeholders
        if (preg_match('/\{[^}]+\}/', $content)) {
            $result['passed'] = false;
            $result['penalty'] += 40;
            $result['issues'][] = 'Unresolved placeholders found';
        }
        
        // Check for broken HTML
        if ($content !== wp_kses_post($content)) {
            $result['penalty'] += 20;
            $result['issues'][] = 'Invalid HTML detected';
        }
        
        // Check for repeated words
        if ($this->has_excessive_repetition($content)) {
            $result['penalty'] += 15;
            $result['issues'][] = 'Excessive word repetition';
        }
        
        return $result;
    }
    
    /**
     * Check data accuracy
     */
    private function check_data_accuracy($response) {
        $result = array(
            'passed' => true,
            'penalty' => 0,
            'issues' => array()
        );
        
        $content = isset($response['content']) ? $response['content'] : '';
        
        // Check percentage values
        preg_match_all('/(\d+\.?\d*)%/', $content, $percentages);
        foreach ($percentages[1] as $percentage) {
            if ($percentage > 1000) {
                $result['penalty'] += 20;
                $result['issues'][] = 'Unrealistic percentage value: ' . $percentage . '%';
            }
        }
        
        // Check price formatting
        preg_match_all('/\$([\d,]+\.?\d*)/', $content, $prices);
        foreach ($prices[1] as $price) {
            if (!$this->is_valid_price_format($price)) {
                $result['penalty'] += 10;
                $result['issues'][] = 'Invalid price format: $' . $price;
            }
        }
        
        // Check dates
        if ($this->has_invalid_dates($content)) {
            $result['penalty'] += 15;
            $result['issues'][] = 'Invalid or outdated dates detected';
        }
        
        // Fact check if available
        if ($this->fact_checker) {
            $fact_issues = $this->fact_checker->check_facts($response);
            if (!empty($fact_issues)) {
                $result['penalty'] += count($fact_issues) * 10;
                $result['issues'] = array_merge($result['issues'], $fact_issues);
            }
        }
        
        return $result;
    }
    
    /**
     * Check response coherence
     */
    private function check_coherence($response) {
        $result = array(
            'passed' => true,
            'penalty' => 0,
            'issues' => array()
        );
        
        $content = isset($response['content']) ? $response['content'] : '';
        
        // Check for complete sentences
        $sentences = preg_split('/[.!?]+/', $content);
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (!empty($sentence) && !$this->is_complete_sentence($sentence)) {
                $result['penalty'] += 5;
                $result['issues'][] = 'Incomplete sentence detected';
                break;
            }
        }
        
        // Check tense consistency
        if (!$this->has_consistent_tense($content)) {
            $result['penalty'] += 10;
            $result['issues'][] = 'Inconsistent verb tenses';
        }
        
        // Check logical flow
        if ($this->has_logical_issues($content)) {
            $result['penalty'] += 15;
            $result['issues'][] = 'Logical flow issues detected';
        }
        
        // Check for contradictions
        if ($this->has_contradictions($content)) {
            $result['penalty'] += 25;
            $result['issues'][] = 'Contradictory statements found';
        }
        
        return $result;
    }
    
    /**
     * Check response relevance
     */
    private function check_relevance($response, $classification, $query) {
        $result = array(
            'passed' => true,
            'penalty' => 0,
            'issues' => array()
        );
        
        $content = isset($response['content']) ? $response['content'] : '';
        
        // Check if response matches intent
        if (!$this->matches_intent($content, $classification['intent'])) {
            $result['penalty'] += 30;
            $result['issues'][] = 'Response does not match query intent';
        }
        
        // Check if key entities are mentioned
        if (!empty($classification['entities'])) {
            $mentioned = $this->check_entity_mentions($content, $classification['entities']);
            if ($mentioned < 0.5) {
                $result['penalty'] += 20;
                $result['issues'][] = 'Key entities not adequately addressed';
            }
        }
        
        // Check query terms coverage
        $coverage = $this->calculate_query_coverage($content, $query);
        if ($coverage < 0.3) {
            $result['penalty'] += 25;
            $result['issues'][] = 'Low query term coverage';
        }
        
        // Check detail appropriateness
        $detail_level = $this->assess_detail_level($content, $classification);
        if ($detail_level === 'insufficient') {
            $result['penalty'] += 15;
            $result['issues'][] = 'Insufficient detail for query complexity';
        } elseif ($detail_level === 'excessive') {
            $result['penalty'] += 10;
            $result['issues'][] = 'Excessive detail for simple query';
        }
        
        return $result;
    }
    
    /**
     * Check for excessive repetition
     */
    private function has_excessive_repetition($content) {
        $words = str_word_count(strtolower($content), 1);
        $word_counts = array_count_values($words);
        $total_words = count($words);
        
        foreach ($word_counts as $word => $count) {
            // Skip common words
            if (in_array($word, array('the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for'))) {
                continue;
            }
            
            // Check if any word appears too frequently
            if ($count > 5 && ($count / $total_words) > 0.05) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Validate price format
     */
    private function is_valid_price_format($price) {
        // Remove commas and check if valid number
        $clean_price = str_replace(',', '', $price);
        return is_numeric($clean_price) && $clean_price >= 0;
    }
    
    /**
     * Check for invalid dates
     */
    private function has_invalid_dates($content) {
        // Check for obviously wrong years
        preg_match_all('/(19\d{2}|20\d{2})/', $content, $years);
        foreach ($years[1] as $year) {
            $year_int = intval($year);
            if ($year_int > intval(date('Y')) + 10) {
                return true; // Future year too far ahead
            }
        }
        
        return false;
    }
    
    /**
     * Check if sentence is complete
     */
    private function is_complete_sentence($sentence) {
        if (strlen($sentence) < 3) {
            return false;
        }
        
        // Check for subject and verb (simplified)
        $words = str_word_count($sentence, 1);
        return count($words) >= 2;
    }
    
    /**
     * Check tense consistency
     */
    private function has_consistent_tense($content) {
        // Simplified tense checking
        $past_indicators = substr_count($content, 'was') + substr_count($content, 'were') + 
                          substr_count($content, 'had');
        $present_indicators = substr_count($content, 'is') + substr_count($content, 'are') + 
                             substr_count($content, 'has');
        
        // Allow some mixing but not excessive
        if ($past_indicators > 0 && $present_indicators > 0) {
            $ratio = min($past_indicators, $present_indicators) / 
                    max($past_indicators, $present_indicators);
            return $ratio > 0.2; // Allow 20% mixing
        }
        
        return true;
    }
    
    /**
     * Check for logical issues
     */
    private function has_logical_issues($content) {
        // Check for common logical connectors
        $connectors = array('however', 'therefore', 'thus', 'consequently', 'because');
        $has_connectors = false;
        
        foreach ($connectors as $connector) {
            if (stripos($content, $connector) !== false) {
                $has_connectors = true;
                break;
            }
        }
        
        // If long response without logical connectors, might have flow issues
        if (strlen($content) > 500 && !$has_connectors) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check for contradictions
     */
    private function has_contradictions($content) {
        // Check for opposite statements
        $contradictory_pairs = array(
            array('increase', 'decrease'),
            array('up', 'down'),
            array('bullish', 'bearish'),
            array('positive', 'negative'),
            array('growth', 'decline')
        );
        
        foreach ($contradictory_pairs as $pair) {
            if (stripos($content, $pair[0]) !== false && 
                stripos($content, $pair[1]) !== false) {
                // Both contradictory terms present - might be an issue
                // (This is simplified - real implementation would check context)
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if response matches intent
     */
    private function matches_intent($content, $intent) {
        $intent_keywords = array(
            'market_status' => array('market', 'index', 'trading', 'stocks'),
            'career_guidance' => array('career', 'job', 'skills', 'experience'),
            'company_analysis' => array('company', 'stock', 'earnings', 'revenue'),
            'private_equity' => array('PE', 'private equity', 'fund', 'investment'),
            'technical_analysis' => array('chart', 'technical', 'support', 'resistance')
        );
        
        if (!isset($intent_keywords[$intent])) {
            return true; // Unknown intent, assume match
        }
        
        $keywords = $intent_keywords[$intent];
        $content_lower = strtolower($content);
        
        foreach ($keywords as $keyword) {
            if (stripos($content_lower, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check entity mentions
     */
    private function check_entity_mentions($content, $entities) {
        $total_entities = 0;
        $mentioned_entities = 0;
        
        foreach ($entities as $entity_type => $entity_list) {
            foreach ($entity_list as $entity) {
                $total_entities++;
                $entity_name = isset($entity['name']) ? $entity['name'] : '';
                
                if (!empty($entity_name) && stripos($content, $entity_name) !== false) {
                    $mentioned_entities++;
                }
            }
        }
        
        return $total_entities > 0 ? $mentioned_entities / $total_entities : 1;
    }
    
    /**
     * Calculate query coverage
     */
    private function calculate_query_coverage($content, $query) {
        $query_words = str_word_count(strtolower($query), 1);
        $content_lower = strtolower($content);
        $matched_words = 0;
        
        foreach ($query_words as $word) {
            // Skip very short words
            if (strlen($word) < 3) {
                continue;
            }
            
            if (stripos($content_lower, $word) !== false) {
                $matched_words++;
            }
        }
        
        $significant_words = array_filter($query_words, function($word) {
            return strlen($word) >= 3;
        });
        
        return count($significant_words) > 0 ? 
               $matched_words / count($significant_words) : 1;
    }
    
    /**
     * Assess detail level
     */
    private function assess_detail_level($content, $classification) {
        $content_length = strlen($content);
        $complexity = isset($classification['complexity']) ? 
                     $classification['complexity'] : 'medium';
        
        // Expected lengths based on complexity
        $expected_lengths = array(
            'low' => array('min' => 50, 'max' => 200),
            'medium' => array('min' => 150, 'max' => 500),
            'high' => array('min' => 300, 'max' => 1000)
        );
        
        $expected = $expected_lengths[$complexity] ?? $expected_lengths['medium'];
        
        if ($content_length < $expected['min']) {
            return 'insufficient';
        }
        
        if ($content_length > $expected['max'] * 1.5) {
            return 'excessive';
        }
        
        return 'appropriate';
    }
    
    /**
     * Generate improvement suggestions
     */
    private function generate_suggestions($issues) {
        $suggestions = array();
        
        foreach ($issues as $issue) {
            $suggestion = $this->get_suggestion_for_issue($issue);
            if (!empty($suggestion)) {
                $suggestions[] = $suggestion;
            }
        }
        
        return array_unique($suggestions);
    }
    
    /**
     * Get suggestion for specific issue
     */
    private function get_suggestion_for_issue($issue) {
        $issue_lower = strtolower($issue);
        
        if (strpos($issue_lower, 'short') !== false) {
            return 'Add more detail and context to the response';
        }
        
        if (strpos($issue_lower, 'placeholder') !== false) {
            return 'Resolve all template variables with actual data';
        }
        
        if (strpos($issue_lower, 'repetition') !== false) {
            return 'Vary vocabulary and sentence structure';
        }
        
        if (strpos($issue_lower, 'tense') !== false) {
            return 'Maintain consistent verb tenses throughout';
        }
        
        if (strpos($issue_lower, 'entity') !== false) {
            return 'Include all relevant entities mentioned in the query';
        }
        
        if (strpos($issue_lower, 'coverage') !== false) {
            return 'Address more aspects of the original query';
        }
        
        return null;
    }
    
    /**
     * Enhance response quality
     */
    public function enhance_response($response, $validation_result) {
        if ($validation_result['is_valid']) {
            return $response;
        }
        
        // Apply automatic fixes where possible
        $enhanced = $response;
        
        // Fix placeholders
        if (in_array('Unresolved placeholders found', $validation_result['issues'])) {
            $enhanced['content'] = preg_replace('/\{[^}]+\}/', '', $enhanced['content']);
        }
        
        // Add metadata about quality
        $enhanced['quality_metadata'] = array(
            'score' => $validation_result['score'],
            'enhanced' => true,
            'issues_addressed' => count($validation_result['issues'])
        );
        
        return $enhanced;
    }
    
    /**
     * Get quality score
     */
    public function get_quality_score($response, $classification, $query) {
        $validation = $this->validate_response($response, $classification, $query);
        return $validation['score'];
    }
    
    /**
     * Is response acceptable
     */
    public function is_acceptable($response, $classification, $query) {
        $validation = $this->validate_response($response, $classification, $query);
        return $validation['is_valid'];
    }
}