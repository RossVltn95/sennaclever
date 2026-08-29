<?php
/**
 * Intelligent Query Understanding Engine
 * Extracts entities, intent, and context from user queries
 * 
 * @package SennaCareers
 * @since 10.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Intelligent_Query_Engine {
    
    private static $instance = null;
    
    // Entity dictionaries
    private $companies = array();
    private $financial_terms = array();
    private $action_verbs = array();
    private $temporal_markers = array();
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->initialize_dictionaries();
    }
    
    /**
     * Initialize entity dictionaries
     */
    private function initialize_dictionaries() {
        // Major companies and their variants
        $this->companies = array(
            'barclays' => array('ticker' => 'BARC.L', 'variants' => array('barc', 'bcs', 'barclays bank')),
            'goldman' => array('ticker' => 'GS', 'variants' => array('goldman sachs', 'gs', 'goldmans')),
            'jpmorgan' => array('ticker' => 'JPM', 'variants' => array('jp morgan', 'jpm', 'chase', 'jpmorgan chase')),
            'blackstone' => array('ticker' => 'BX', 'variants' => array('bx', 'blackstone group')),
            'kkr' => array('ticker' => 'KKR', 'variants' => array('kohlberg kravis', 'kkr & co')),
            'apollo' => array('ticker' => 'APO', 'variants' => array('apo', 'apollo global')),
            'morgan stanley' => array('ticker' => 'MS', 'variants' => array('ms', 'morgan')),
            'bank of america' => array('ticker' => 'BAC', 'variants' => array('bofa', 'boa', 'bac')),
            'citigroup' => array('ticker' => 'C', 'variants' => array('citi', 'citibank')),
            'carlyle' => array('ticker' => 'CG', 'variants' => array('carlyle group', 'cg')),
            'blackrock' => array('ticker' => 'BLK', 'variants' => array('blk')),
            'wells fargo' => array('ticker' => 'WFC', 'variants' => array('wells', 'wfc'))
        );
        
        // Financial terms and what they relate to
        $this->financial_terms = array(
            'price_related' => array('price', 'stock price', 'share price', 'trading at', 'quote', 'cost', 'value', 'worth'),
            'performance_related' => array('performance', 'return', 'gain', 'loss', 'up', 'down', 'change', 'movement'),
            'volume_related' => array('volume', 'trading volume', 'shares traded', 'liquidity'),
            'fundamental_related' => array('earnings', 'revenue', 'profit', 'pe ratio', 'market cap', 'dividend', 'yield'),
            'technical_related' => array('chart', 'technical', 'resistance', 'support', 'moving average', 'rsi', 'macd'),
            'deal_related' => array('deal', 'acquisition', 'merger', 'm&a', 'buyout', 'lbo', 'ipo', 'spac'),
            'market_related' => array('market', 'sector', 'industry', 'index', 's&p', 'dow', 'nasdaq', 'ftse'),
            'pe_related' => array('private equity', 'pe', 'portfolio company', 'dry powder', 'fund', 'lp', 'gp', 'carried interest'),
            'career_related' => array('job', 'career', 'salary', 'compensation', 'interview', 'role', 'position', 'hiring')
        );
        
        // Action verbs that indicate intent
        $this->action_verbs = array(
            'data_request' => array('what is', 'what are', 'show', 'tell me', 'give me', 'find', 'get', 'display'),
            'explanation' => array('explain', 'how does', 'how do', 'why', 'what does mean', 'describe', 'define'),
            'comparison' => array('compare', 'versus', 'vs', 'difference between', 'better than', 'which is'),
            'analysis' => array('analyze', 'analyse', 'evaluate', 'assess', 'review', 'examine', 'investigate'),
            'prediction' => array('will', 'predict', 'forecast', 'expect', 'outlook', 'future', 'projection'),
            'recommendation' => array('should i', 'recommend', 'suggest', 'advise', 'best', 'top', 'good'),
            'creation' => array('create', 'generate', 'make', 'build', 'write', 'develop', 'design')
        );
        
        // Temporal markers
        $this->temporal_markers = array(
            'current' => array('now', 'today', 'current', 'currently', 'right now', 'at the moment', 'latest'),
            'past' => array('yesterday', 'last week', 'last month', 'last year', 'previously', 'historical', 'ytd'),
            'future' => array('tomorrow', 'next week', 'next month', 'next year', 'upcoming', 'future', 'forecast')
        );
    }
    
    /**
     * Main analysis function - the brain of the system
     */
    public function analyze_query($query, $context = array()) {
        $query_lower = strtolower(trim($query));
        
        $analysis = array(
            'original_query' => $query,
            'normalized_query' => $query_lower,
            'intent' => $this->extract_intent($query_lower),
            'entities' => $this->extract_entities($query_lower),
            'temporal' => $this->extract_temporal_context($query_lower),
            'data_needed' => $this->determine_data_requirements($query_lower),
            'response_type' => null,
            'confidence' => 0,
            'requires_live_data' => false,
            'visual_type' => null
        );
        
        // Determine response type based on intent and entities
        $analysis['response_type'] = $this->determine_response_type($analysis);
        
        // Calculate confidence score
        $analysis['confidence'] = $this->calculate_confidence($analysis);
        
        // Determine if live data is needed
        $analysis['requires_live_data'] = $this->needs_live_data($analysis);
        
        // Determine visual requirements
        $analysis['visual_type'] = $this->determine_visual_type($analysis);
        
        return $analysis;
    }
    
    /**
     * Extract user intent from query
     */
    private function extract_intent($query) {
        $intents = array();
        
        foreach ($this->action_verbs as $intent_type => $verbs) {
            foreach ($verbs as $verb) {
                if (stripos($query, $verb) !== false) {
                    $intents[] = $intent_type;
                    break;
                }
            }
        }
        
        // If no explicit intent, infer from content
        if (empty($intents)) {
            // Price-related queries
            if (preg_match('/price|trading|quote|cost|worth|share/i', $query)) {
                $intents[] = 'data_request';
            }
            // News-related queries
            elseif (preg_match('/news|latest|recent|announcement/i', $query)) {
                $intents[] = 'data_request';
            }
            // Any question
            elseif (preg_match('/\?$/', $query)) {
                $intents[] = 'data_request';
            }
            // PE/finance concepts
            elseif (stripos($query, 'private equity') !== false || stripos($query, ' pe ') !== false) {
                $intents[] = 'explanation';
            }
            // Company + context suggests data request
            elseif (!empty($this->find_companies_in_query($query))) {
                $intents[] = 'data_request';
            }
        }
        
        // Default to data_request if nothing found
        if (empty($intents)) {
            $intents[] = 'general_inquiry';
        }
        
        return $intents;
    }
    
    /**
     * Extract entities (companies, financial terms, etc.)
     */
    private function extract_entities($query) {
        $entities = array(
            'companies' => array(),
            'financial_terms' => array(),
            'tickers' => array(),
            'amounts' => array(),
            'percentages' => array()
        );
        
        // Extract companies
        foreach ($this->companies as $company => $data) {
            if (stripos($query, $company) !== false) {
                $entities['companies'][] = array(
                    'name' => $company,
                    'ticker' => $data['ticker']
                );
                $entities['tickers'][] = $data['ticker'];
            } else {
                // Check variants
                foreach ($data['variants'] as $variant) {
                    if (stripos($query, $variant) !== false) {
                        $entities['companies'][] = array(
                            'name' => $company,
                            'ticker' => $data['ticker']
                        );
                        $entities['tickers'][] = $data['ticker'];
                        break;
                    }
                }
            }
        }
        
        // Extract financial terms
        foreach ($this->financial_terms as $category => $terms) {
            foreach ($terms as $term) {
                if (stripos($query, $term) !== false) {
                    $entities['financial_terms'][] = array(
                        'term' => $term,
                        'category' => $category
                    );
                }
            }
        }
        
        // Extract amounts (e.g., $1M, £2.5B)
        preg_match_all('/[\$£€][\d,]+\.?\d*[MBK]?/i', $query, $amounts);
        if (!empty($amounts[0])) {
            $entities['amounts'] = $amounts[0];
        }
        
        // Extract percentages
        preg_match_all('/\d+\.?\d*%/', $query, $percentages);
        if (!empty($percentages[0])) {
            $entities['percentages'] = $percentages[0];
        }
        
        return $entities;
    }
    
    /**
     * Extract temporal context
     */
    private function extract_temporal_context($query) {
        foreach ($this->temporal_markers as $period => $markers) {
            foreach ($markers as $marker) {
                if (stripos($query, $marker) !== false) {
                    return $period;
                }
            }
        }
        return 'current'; // Default to current
    }
    
    /**
     * Determine what data is needed to answer the query
     */
    private function determine_data_requirements($query) {
        $requirements = array();
        
        // Check for price data needs
        if (preg_match('/price|trading|quote|cost|worth/i', $query)) {
            $requirements[] = 'stock_price';
            $requirements[] = 'price_change';
        }
        
        // Check for volume needs
        if (preg_match('/volume|shares traded|liquidity/i', $query)) {
            $requirements[] = 'trading_volume';
        }
        
        // Check for news needs
        if (preg_match('/news|announcement|latest|recent/i', $query)) {
            $requirements[] = 'recent_news';
        }
        
        // Check for fundamental data
        if (preg_match('/earnings|revenue|pe|market cap|dividend/i', $query)) {
            $requirements[] = 'fundamentals';
        }
        
        // Check for deal data
        if (preg_match('/deal|acquisition|merger|buyout/i', $query)) {
            $requirements[] = 'deal_data';
        }
        
        return $requirements;
    }
    
    /**
     * Determine the type of response needed
     */
    private function determine_response_type($analysis) {
        // If asking for specific stock price
        if (in_array('data_request', $analysis['intent']) && 
            !empty($analysis['entities']['companies']) &&
            $this->has_price_terms($analysis['entities']['financial_terms'])) {
            return 'stock_price_response';
        }
        
        // If asking about PE/IB concepts
        if ((in_array('explanation', $analysis['intent']) || in_array('data_request', $analysis['intent'])) &&
            $this->has_pe_terms($analysis['entities']['financial_terms'])) {
            return 'concept_explanation';
        }
        
        // If comparing companies
        if (in_array('comparison', $analysis['intent']) &&
            count($analysis['entities']['companies']) >= 2) {
            return 'comparison_response';
        }
        
        // If asking for analysis
        if (in_array('analysis', $analysis['intent'])) {
            return 'analytical_response';
        }
        
        // If asking for recommendations
        if (in_array('recommendation', $analysis['intent'])) {
            return 'recommendation_response';
        }
        
        // Default to informational
        return 'informational_response';
    }
    
    /**
     * Calculate confidence score
     */
    private function calculate_confidence($analysis) {
        $score = 0;
        
        // Points for clear intent
        if (!in_array('general_inquiry', $analysis['intent'])) {
            $score += 30;
        }
        
        // Points for identified entities
        if (!empty($analysis['entities']['companies'])) {
            $score += 25;
        }
        if (!empty($analysis['entities']['financial_terms'])) {
            $score += 20;
        }
        
        // Points for specific data requirements
        if (!empty($analysis['data_needed'])) {
            $score += 15;
        }
        
        // Points for clear response type
        if ($analysis['response_type'] !== 'informational_response') {
            $score += 10;
        }
        
        return min($score, 100);
    }
    
    /**
     * Determine if live data is needed
     */
    private function needs_live_data($analysis) {
        $live_data_types = array('stock_price', 'price_change', 'trading_volume', 'recent_news');
        
        foreach ($analysis['data_needed'] as $need) {
            if (in_array($need, $live_data_types)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Determine what type of visual to show
     */
    private function determine_visual_type($analysis) {
        if ($analysis['response_type'] === 'stock_price_response') {
            return 'price_chart';
        }
        
        if ($analysis['response_type'] === 'comparison_response') {
            return 'comparison_table';
        }
        
        if (in_array('deal_data', $analysis['data_needed'])) {
            return 'deal_cards';
        }
        
        if (in_array('recent_news', $analysis['data_needed'])) {
            return 'news_cards';
        }
        
        return null;
    }
    
    /**
     * Helper: Check if has price-related terms
     */
    private function has_price_terms($financial_terms) {
        foreach ($financial_terms as $term_data) {
            if ($term_data['category'] === 'price_related') {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Helper: Check if has PE-related terms
     */
    private function has_pe_terms($financial_terms) {
        foreach ($financial_terms as $term_data) {
            if ($term_data['category'] === 'pe_related') {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Helper: Find companies in query (for better intent detection)
     */
    private function find_companies_in_query($query) {
        $found_companies = array();
        
        foreach ($this->companies as $company => $data) {
            if (stripos($query, $company) !== false) {
                $found_companies[] = $company;
            } else {
                foreach ($data['variants'] as $variant) {
                    if (stripos($query, $variant) !== false) {
                        $found_companies[] = $company;
                        break;
                    }
                }
            }
        }
        
        return $found_companies;
    }
}