<?php
/**
 * Smart Template Selector - Phase 5
 * Intelligently selects and combines response templates based on query analysis
 * 
 * @package SennaCareers
 * @since 5.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Smart_Template_Selector {
    
    private static $instance = null;
    private $templates = array();
    private $template_combinations = array();
    private $expertise_adaptations = array();
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->initialize_templates();
        $this->initialize_combinations();
        $this->initialize_expertise_adaptations();
    }
    
    /**
     * Initialize template library with hierarchical structure
     */
    private function initialize_templates() {
        $this->templates = array(
            // Specific templates (highest priority)
            'specific' => array(
                'stock_price_live' => array(
                    'priority' => 100,
                    'conditions' => array('has_price_data', 'has_ticker'),
                    'structure' => array(
                        'opening' => '{company} ({ticker}) is currently trading at {price}',
                        'movement' => '{direction} {change}% from previous close',
                        'volume' => 'Trading volume: {volume}',
                        'context' => '{recent_news}',
                        'visual' => 'price_chart'
                    )
                ),
                'earnings_report' => array(
                    'priority' => 95,
                    'conditions' => array('mentions_earnings', 'has_company'),
                    'structure' => array(
                        'headline' => '{company} earnings overview',
                        'results' => 'Q{quarter} results: Revenue {revenue}, EPS {eps}',
                        'comparison' => '{beat_or_miss} analyst expectations',
                        'guidance' => 'Forward guidance: {guidance}',
                        'visual' => 'earnings_table'
                    )
                ),
                'pe_fund_performance' => array(
                    'priority' => 90,
                    'conditions' => array('is_pe_firm', 'mentions_performance'),
                    'structure' => array(
                        'overview' => '{firm} fund performance metrics',
                        'returns' => 'Net IRR: {irr}%, Multiple: {multiple}x',
                        'portfolio' => 'Portfolio companies: {count}',
                        'recent_exits' => 'Recent exits: {exits}',
                        'visual' => 'performance_dashboard'
                    )
                ),
                'market_comparison' => array(
                    'priority' => 85,
                    'conditions' => array('multiple_companies', 'comparison_intent'),
                    'structure' => array(
                        'intro' => 'Comparing {company1} vs {company2}',
                        'metrics' => array(
                            'price' => 'Stock Price: {price1} vs {price2}',
                            'performance' => 'YTD Performance: {perf1} vs {perf2}',
                            'valuation' => 'P/E Ratio: {pe1} vs {pe2}'
                        ),
                        'verdict' => '{analysis_summary}',
                        'visual' => 'comparison_chart'
                    )
                )
            ),
            
            // Category templates (medium priority)
            'category' => array(
                'market_data' => array(
                    'priority' => 60,
                    'conditions' => array('data_request'),
                    'structure' => array(
                        'data_point' => '{metric}: {value}',
                        'trend' => 'Trend: {direction} over {period}',
                        'benchmark' => 'vs Industry: {comparison}',
                        'visual' => 'data_visualization'
                    )
                ),
                'educational' => array(
                    'priority' => 55,
                    'conditions' => array('explanation_request'),
                    'structure' => array(
                        'definition' => '{term} is {definition}',
                        'context' => 'In the context of {industry}: {explanation}',
                        'example' => 'For example: {example}',
                        'relevance' => 'Why it matters: {importance}',
                        'visual' => 'infographic'
                    )
                ),
                'news_summary' => array(
                    'priority' => 50,
                    'conditions' => array('news_request'),
                    'structure' => array(
                        'headline' => 'Latest on {topic}',
                        'bulletpoints' => array(
                            '{news_item_1}',
                            '{news_item_2}',
                            '{news_item_3}'
                        ),
                        'analysis' => 'Market impact: {impact}',
                        'visual' => 'news_feed'
                    )
                ),
                'career_guidance' => array(
                    'priority' => 45,
                    'conditions' => array('career_related'),
                    'structure' => array(
                        'opportunity' => '{role} at {company}',
                        'requirements' => 'Key requirements: {requirements}',
                        'path' => 'Career path: {progression}',
                        'preparation' => 'How to prepare: {tips}',
                        'visual' => 'career_roadmap'
                    )
                )
            ),
            
            // General templates (lowest priority)
            'general' => array(
                'informational' => array(
                    'priority' => 20,
                    'conditions' => array(),
                    'structure' => array(
                        'response' => '{information}',
                        'context' => '{additional_context}',
                        'followup' => 'Would you like to know more about {related_topic}?'
                    )
                ),
                'clarification' => array(
                    'priority' => 10,
                    'conditions' => array('low_confidence'),
                    'structure' => array(
                        'acknowledgment' => 'I understand you\'re asking about {topic}',
                        'clarification' => 'Could you specify {missing_detail}?',
                        'options' => 'Are you interested in: {option1}, {option2}, or {option3}?'
                    )
                ),
                'fallback' => array(
                    'priority' => 5,
                    'conditions' => array(),
                    'structure' => array(
                        'response' => 'I can help you with {capabilities}',
                        'suggestion' => 'Try asking about {suggestion}'
                    )
                )
            )
        );
    }
    
    /**
     * Initialize template combination rules
     */
    private function initialize_combinations() {
        $this->template_combinations = array(
            // Multi-part query combinations
            'price_and_news' => array(
                'templates' => array('stock_price_live', 'news_summary'),
                'connector' => 'Additionally, ',
                'conditions' => array('has_price_request', 'has_news_request')
            ),
            'comparison_with_analysis' => array(
                'templates' => array('market_comparison', 'market_data'),
                'connector' => 'Further analysis shows: ',
                'conditions' => array('comparison_intent', 'analysis_depth')
            ),
            'education_with_example' => array(
                'templates' => array('educational', 'specific_example'),
                'connector' => 'Here\'s a real-world example: ',
                'conditions' => array('explanation_request', 'needs_example')
            ),
            'data_with_interpretation' => array(
                'templates' => array('market_data', 'analytical_insight'),
                'connector' => 'This suggests that ',
                'conditions' => array('data_request', 'needs_interpretation')
            )
        );
    }
    
    /**
     * Initialize expertise level adaptations
     */
    private function initialize_expertise_adaptations() {
        $this->expertise_adaptations = array(
            'beginner' => array(
                'add_definitions' => true,
                'simplify_terms' => true,
                'include_examples' => true,
                'avoid_jargon' => true,
                'explain_acronyms' => true,
                'tone' => 'educational',
                'detail_level' => 'basic'
            ),
            'intermediate' => array(
                'add_definitions' => false,
                'simplify_terms' => false,
                'include_examples' => true,
                'avoid_jargon' => false,
                'explain_acronyms' => false,
                'tone' => 'professional',
                'detail_level' => 'moderate'
            ),
            'expert' => array(
                'add_definitions' => false,
                'simplify_terms' => false,
                'include_examples' => false,
                'avoid_jargon' => false,
                'explain_acronyms' => false,
                'tone' => 'technical',
                'detail_level' => 'detailed',
                'include_technicals' => true
            )
        );
    }
    
    /**
     * Select best template(s) for the query
     */
    public function select_template($analysis, $context_adjustments = array()) {
        $selected_templates = array();
        $expertise_level = $context_adjustments['expertise_level'] ?? 'intermediate';
        
        // Score all applicable templates
        $template_scores = $this->score_templates($analysis, $context_adjustments);
        
        // Sort by score
        arsort($template_scores);
        
        // Select top template(s)
        $primary_template = null;
        $secondary_templates = array();
        
        foreach ($template_scores as $template_key => $score) {
            if ($score > 70) {
                if (!$primary_template) {
                    $primary_template = $template_key;
                } else {
                    $secondary_templates[] = $template_key;
                }
            }
        }
        
        // Check for beneficial combinations
        $combination = $this->check_for_combinations($primary_template, $secondary_templates, $analysis);
        
        if ($combination) {
            return $this->build_combined_template($combination, $expertise_level);
        }
        
        // Return single template with expertise adaptations
        if ($primary_template) {
            return $this->adapt_template_for_expertise(
                $this->get_template_by_key($primary_template),
                $expertise_level
            );
        }
        
        // Fallback
        return $this->adapt_template_for_expertise(
            $this->templates['general']['fallback'],
            $expertise_level
        );
    }
    
    /**
     * Score templates based on query analysis
     */
    private function score_templates($analysis, $context) {
        $scores = array();
        
        foreach ($this->templates as $category => $category_templates) {
            foreach ($category_templates as $template_key => $template) {
                $score = $template['priority'];
                
                // Check conditions
                $conditions_met = 0;
                $total_conditions = count($template['conditions']);
                
                foreach ($template['conditions'] as $condition) {
                    if ($this->check_condition($condition, $analysis, $context)) {
                        $conditions_met++;
                    }
                }
                
                // Calculate condition score
                if ($total_conditions > 0) {
                    $condition_score = ($conditions_met / $total_conditions) * 50;
                    $score += $condition_score;
                }
                
                // Relevance bonus
                if ($this->is_highly_relevant($template_key, $analysis)) {
                    $score += 20;
                }
                
                // Context bonus
                if ($this->matches_context($template_key, $context)) {
                    $score += 10;
                }
                
                $scores[$category . '.' . $template_key] = $score;
            }
        }
        
        return $scores;
    }
    
    /**
     * Check if a condition is met
     */
    private function check_condition($condition, $analysis, $context) {
        switch ($condition) {
            case 'has_price_data':
                return in_array('data_request', $analysis['intent'] ?? array()) || 
                       $this->contains_term($analysis, array('price', 'stock', 'trading'));
            
            case 'has_ticker':
                return !empty($analysis['entities']['companies']);
            
            case 'mentions_earnings':
                return $this->contains_term($analysis, array('earnings', 'results', 'quarter', 'eps'));
            
            case 'has_company':
                return !empty($analysis['entities']['companies']);
            
            case 'is_pe_firm':
                return $this->is_pe_firm($analysis);
            
            case 'mentions_performance':
                return $this->contains_term($analysis, array('performance', 'returns', 'irr'));
            
            case 'multiple_companies':
                return count($analysis['entities']['companies'] ?? array()) > 1;
            
            case 'comparison_intent':
                return in_array('comparison', $analysis['intent'] ?? array());
            
            case 'data_request':
                return in_array('data_request', $analysis['intent'] ?? array());
            
            case 'explanation_request':
                return in_array('explanation', $analysis['intent'] ?? array());
            
            case 'news_request':
                return $this->contains_term($analysis, array('news', 'latest', 'recent'));
            
            case 'career_related':
                return $this->contains_term($analysis, array('career', 'job', 'role', 'opportunity'));
            
            case 'low_confidence':
                return ($analysis['confidence'] ?? 100) < 60;
            
            // Additional conditions for combinations
            case 'has_price_request':
                return $this->contains_term($analysis, array('price', 'stock', 'trading', 'quote'));
            
            case 'has_news_request':
                return $this->contains_term($analysis, array('news', 'latest', 'recent', 'headlines'));
            
            case 'analysis_depth':
                return in_array('analysis', $analysis['intent'] ?? array()) || 
                       $this->contains_term($analysis, array('analyze', 'analysis', 'performance'));
            
            case 'needs_example':
                return $this->contains_term($analysis, array('example', 'show', 'demonstrate'));
            
            case 'needs_interpretation':
                return $this->contains_term($analysis, array('what does', 'means', 'interpret', 'suggest'));
            
            default:
                return false;
        }
    }
    
    /**
     * Check if query contains specific terms
     */
    private function contains_term($analysis, $terms) {
        $query_lower = strtolower($analysis['original_query'] ?? '');
        foreach ($terms as $term) {
            if (stripos($query_lower, $term) !== false) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check if company is PE firm
     */
    private function is_pe_firm($analysis) {
        $pe_firms = array('kkr', 'blackstone', 'apollo', 'carlyle', 'tpg', 'warburg');
        
        if (!empty($analysis['entities']['companies'])) {
            foreach ($analysis['entities']['companies'] as $company) {
                if (in_array(strtolower($company['name']), $pe_firms)) {
                    return true;
                }
            }
        }
        return false;
    }
    
    /**
     * Check if template is highly relevant
     */
    private function is_highly_relevant($template_key, $analysis) {
        // Map response types to templates
        $relevance_map = array(
            'stock_price_response' => array('stock_price_live'),
            'concept_explanation' => array('educational'),
            'comparison_response' => array('market_comparison'),
            'analytical_response' => array('market_data', 'pe_fund_performance')
        );
        
        $response_type = $analysis['response_type'] ?? '';
        if (isset($relevance_map[$response_type])) {
            return in_array($template_key, $relevance_map[$response_type]);
        }
        
        return false;
    }
    
    /**
     * Check if template matches context
     */
    private function matches_context($template_key, $context) {
        $mode = $context['conversation_mode'] ?? 'general';
        
        $context_map = array(
            'market_data' => array('stock_price_live', 'market_data'),
            'educational' => array('educational'),
            'analytical' => array('market_comparison', 'pe_fund_performance'),
            'advisory' => array('career_guidance')
        );
        
        if (isset($context_map[$mode])) {
            return in_array($template_key, $context_map[$mode]);
        }
        
        return false;
    }
    
    /**
     * Check for beneficial template combinations
     */
    private function check_for_combinations($primary, $secondary, $analysis) {
        // Check if query explicitly asks for multiple things
        $query_lower = strtolower($analysis['original_query'] ?? '');
        $has_and = (strpos($query_lower, ' and ') !== false);
        $has_also = (strpos($query_lower, 'also') !== false);
        $has_plus = (strpos($query_lower, 'plus') !== false);
        
        // If query explicitly asks for multiple things, try to combine
        if ($has_and || $has_also || $has_plus) {
            // Check for price + news combination
            if ($this->contains_term($analysis, array('price')) && 
                $this->contains_term($analysis, array('news', 'latest'))) {
                return array(
                    'primary' => $primary,
                    'secondary' => !empty($secondary) ? $secondary[0] : 'specific.news_summary',
                    'connector' => 'Additionally, ',
                    'is_combined' => true
                );
            }
        }
        
        if (!$primary || empty($secondary)) {
            return null;
        }
        
        foreach ($this->template_combinations as $combo_key => $combo) {
            $all_conditions_met = true;
            foreach ($combo['conditions'] as $condition) {
                if (!$this->check_condition($condition, $analysis, array())) {
                    $all_conditions_met = false;
                    break;
                }
            }
            
            if ($all_conditions_met) {
                return array(
                    'primary' => $primary,
                    'secondary' => $secondary[0],
                    'connector' => $combo['connector'],
                    'is_combined' => true
                );
            }
        }
        
        return null;
    }
    
    /**
     * Build combined template
     */
    private function build_combined_template($combination, $expertise_level) {
        $primary = $this->get_template_by_key($combination['primary']);
        $secondary = $this->get_template_by_key($combination['secondary']);
        
        // Handle cases where templates might not be found
        if (!$primary) {
            $primary = $this->templates['general']['informational'];
        }
        if (!$secondary) {
            $secondary = $this->templates['general']['informational'];
        }
        
        $combined = array(
            'structure' => array_merge(
                $primary['structure'],
                array('connector' => $combination['connector']),
                $secondary['structure']
            ),
            'visual' => array($primary['structure']['visual'] ?? null, $secondary['structure']['visual'] ?? null),
            'is_combined' => true
        );
        
        return $this->adapt_template_for_expertise($combined, $expertise_level);
    }
    
    /**
     * Get template by key
     */
    private function get_template_by_key($key) {
        $parts = explode('.', $key);
        if (count($parts) === 2) {
            return $this->templates[$parts[0]][$parts[1]] ?? null;
        }
        
        // Search all categories
        foreach ($this->templates as $category => $templates) {
            if (isset($templates[$key])) {
                return $templates[$key];
            }
        }
        
        return null;
    }
    
    /**
     * Adapt template for expertise level
     */
    private function adapt_template_for_expertise($template, $expertise_level) {
        if (!$template) {
            return null;
        }
        
        $adaptations = $this->expertise_adaptations[$expertise_level] ?? $this->expertise_adaptations['intermediate'];
        
        $adapted = $template;
        $adapted['expertise_adaptations'] = $adaptations;
        
        // Modify structure based on expertise
        if ($adaptations['add_definitions'] && isset($adapted['structure'])) {
            $adapted['structure']['definitions'] = '{term_definitions}';
        }
        
        if ($adaptations['include_examples'] && !isset($adapted['structure']['example'])) {
            $adapted['structure']['example'] = '{relevant_example}';
        }
        
        // Add expertise-specific fields
        if ($expertise_level === 'expert' && $adaptations['include_technicals']) {
            $adapted['structure']['technical_analysis'] = '{technical_details}';
        }
        
        $adapted['tone'] = $adaptations['tone'];
        $adapted['detail_level'] = $adaptations['detail_level'];
        
        return $adapted;
    }
    
    /**
     * Fill template with data
     */
    public function fill_template($template, $data) {
        if (!$template || !isset($template['structure'])) {
            return null;
        }
        
        $filled = array();
        $visual = null;
        
        foreach ($template['structure'] as $key => $pattern) {
            // Handle visual separately - don't try to fill it as a pattern
            if ($key === 'visual') {
                $visual = $pattern;
                continue;
            }
            
            if (is_array($pattern)) {
                $filled[$key] = array();
                foreach ($pattern as $subkey => $subpattern) {
                    $filled[$key][$subkey] = $this->replace_placeholders($subpattern, $data);
                }
            } else {
                $filled[$key] = $this->replace_placeholders($pattern, $data);
            }
        }
        
        return array(
            'content' => $filled,
            'visual' => $visual ?? $template['visual'] ?? null,
            'tone' => $template['tone'] ?? 'professional',
            'detail_level' => $template['detail_level'] ?? 'moderate'
        );
    }
    
    /**
     * Replace placeholders with actual data
     */
    private function replace_placeholders($pattern, $data) {
        // Find all placeholders
        preg_match_all('/\{([^}]+)\}/', $pattern, $matches);
        
        $result = $pattern;
        foreach ($matches[1] as $placeholder) {
            if (isset($data[$placeholder])) {
                $result = str_replace('{' . $placeholder . '}', $data[$placeholder], $result);
            }
        }
        
        return $result;
    }
    
    /**
     * Get template suggestions for a query type
     */
    public function get_template_suggestions($query_type) {
        $suggestions = array();
        
        foreach ($this->templates as $category => $templates) {
            foreach ($templates as $key => $template) {
                if ($this->is_template_suitable_for_type($key, $query_type)) {
                    $suggestions[] = array(
                        'key' => $category . '.' . $key,
                        'priority' => $template['priority'],
                        'description' => $this->get_template_description($key)
                    );
                }
            }
        }
        
        // Sort by priority
        usort($suggestions, function($a, $b) {
            return $b['priority'] - $a['priority'];
        });
        
        return array_slice($suggestions, 0, 3);
    }
    
    /**
     * Check if template is suitable for query type
     */
    private function is_template_suitable_for_type($template_key, $query_type) {
        $type_map = array(
            'stock_price' => array('stock_price_live', 'market_data'),
            'company_info' => array('market_data', 'news_summary'),
            'education' => array('educational'),
            'comparison' => array('market_comparison'),
            'career' => array('career_guidance')
        );
        
        return isset($type_map[$query_type]) && in_array($template_key, $type_map[$query_type]);
    }
    
    /**
     * Get template description
     */
    private function get_template_description($template_key) {
        $descriptions = array(
            'stock_price_live' => 'Real-time stock price with movement and volume',
            'earnings_report' => 'Quarterly earnings results and guidance',
            'pe_fund_performance' => 'Private equity fund metrics and returns',
            'market_comparison' => 'Side-by-side company comparison',
            'educational' => 'Concept explanation with examples',
            'career_guidance' => 'Career opportunities and preparation tips'
        );
        
        return $descriptions[$template_key] ?? 'Standard response template';
    }
}