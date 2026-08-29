<?php
/**
 * Advanced Pattern Matcher - Scientific Approach
 * Implements Claude-like pattern recognition with weighted scoring and contextual understanding
 * 
 * @package SennaCareers
 * @since 10.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Advanced_Pattern_Matcher {
    
    private static $instance = null;
    
    /**
     * Pattern taxonomy with weighted features
     * Scientific classification of query patterns
     */
    private $pattern_taxonomy = array();
    
    /**
     * Linguistic markers and their weights
     */
    private $linguistic_markers = array();
    
    /**
     * Contextual signals
     */
    private $contextual_signals = array();
    
    /**
     * Intent classification matrix
     */
    private $intent_matrix = array();
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->initialize_pattern_taxonomy();
        $this->initialize_linguistic_markers();
        $this->initialize_contextual_signals();
        $this->initialize_intent_matrix();
    }
    
    /**
     * Initialize comprehensive pattern taxonomy
     */
    private function initialize_pattern_taxonomy() {
        $this->pattern_taxonomy = array(
            
            // GREETING PATTERNS - Highest Priority
            'greeting' => array(
                'primary_markers' => array(
                    '/^(hello|hi|hey|greetings?|good\s+(morning|afternoon|evening))[\s,!.]*/i' => 100,
                    '/^(how\s+are\s+you|how\'s\s+it\s+going|what\'s\s+up)/i' => 95,
                    '/^(welcome|pleased\s+to|nice\s+to)/i' => 90
                ),
                'secondary_markers' => array(
                    '/\b(hello|hi|hey)\b/i' => 50,
                    '/how\s+can\s+you\s+help/i' => 45,
                    '/what\s+can\s+you\s+do/i' => 40
                ),
                'negative_markers' => array(
                    '/\b(price|stock|company|analyze|compare)\b/i' => -30
                ),
                'card_mapping' => 'markets_daily_card',
                'confidence_threshold' => 60
            ),
            
            // STOCK PRICE PATTERNS
            'stock_price' => array(
                'primary_markers' => array(
                    '/\b(price|quote|trading\s+at|current\s+price|stock\s+price)\b/i' => 100,
                    '/\b(what\'s|what\s+is|show\s+me).{0,20}(price|quote|trading)/i' => 95,
                    '/\b(how\s+much\s+is|cost\s+of|value\s+of)\b/i' => 90,
                    '/\b(ticker|symbol|\\$[A-Z]{1,5})\b/' => 85
                ),
                'secondary_markers' => array(
                    '/\b(stock|share|equity)\b/i' => 40,
                    '/\b(market\s+value|valuation)\b/i' => 35,
                    '/\b(live|real-?time|current)\b/i' => 30
                ),
                'entity_requirements' => array('companies' => 0), // Optional companies
                'card_mapping' => 'bloomberg_terminal_card',
                'confidence_threshold' => 60
            ),
            
            // COMPANY ANALYSIS PATTERNS
            'company_analysis' => array(
                'primary_markers' => array(
                    '/\b(analyze|analysis\s+of|deep\s+dive|examine|review)\b/i' => 100,
                    '/\b(tell\s+me\s+about|information\s+on|details\s+about)\b/i' => 90,
                    '/\b(company\s+profile|company\s+overview|about\s+[A-Z])/i' => 85,
                    '/\b(performance|fundamentals|metrics)\b/i' => 80
                ),
                'secondary_markers' => array(
                    '/\b(revenue|earnings|profit|margin)\b/i' => 45,
                    '/\b(business\s+model|operations|strategy)\b/i' => 40,
                    '/\b(outlook|forecast|guidance)\b/i' => 35
                ),
                'entity_requirements' => array('companies' => 1),
                'card_mapping' => 'global_investor_card',
                'confidence_threshold' => 65
            ),
            
            // NEWS PATTERNS
            'market_news' => array(
                'primary_markers' => array(
                    '/\b(news|headlines?|latest|breaking|recent)\b/i' => 100,
                    '/\b(what\'s\s+happening|current\s+events|today\'s)\b/i' => 95,
                    '/\b(market\s+update|market\s+news|financial\s+news)\b/i' => 90,
                    '/\b(announcement|report|development)\b/i' => 85
                ),
                'secondary_markers' => array(
                    '/\b(today|now|this\s+week|recent)\b/i' => 40,
                    '/\b(market|markets|trading)\b/i' => 35,
                    '/\b(update|story|article)\b/i' => 30
                ),
                'card_mapping' => 'business_chronicle_card',
                'confidence_threshold' => 60
            ),
            
            // EDUCATIONAL PATTERNS
            'educational' => array(
                'primary_markers' => array(
                    '/^what\s+is\s+/i' => 100,
                    '/^explain\s+/i' => 100,
                    '/^how\s+does?\s+.{0,30}\s+work/i' => 95,
                    '/^define\s+/i' => 95,
                    '/\b(teach|learn|understand|basics|fundamental)\b/i' => 90,
                    '/\b(concept|theory|principle|definition)\b/i' => 85
                ),
                'secondary_markers' => array(
                    '/\b(example|illustration|demonstrate)\b/i' => 45,
                    '/\b(beginner|introduction|overview)\b/i' => 40,
                    '/\b(means?|significance|important)\b/i' => 35
                ),
                'negative_markers' => array(
                    '/\b(price|stock|company\s+name)\b/i' => -20
                ),
                'card_mapping' => 'capital_insights_card',
                'confidence_threshold' => 70
            ),
            
            // RECOMMENDATION PATTERNS
            'recommendations' => array(
                'primary_markers' => array(
                    '/\b(recommend|suggest|advice|should\s+I|best)\b/i' => 100,
                    '/\b(opportunities|picks|ideas|options)\b/i' => 90,
                    '/\b(what.{0,20}invest|where.{0,20}invest|how.{0,20}invest)\b/i' => 85,
                    '/\b(top\s+stocks|best\s+stocks|stock\s+picks)\b/i' => 95
                ),
                'secondary_markers' => array(
                    '/\b(buy|sell|hold|position)\b/i' => 45,
                    '/\b(portfolio|allocation|strategy)\b/i' => 40,
                    '/\b(outlook|potential|prospect)\b/i' => 35
                ),
                'card_mapping' => 'equity_insights_card',
                'confidence_threshold' => 65
            ),
            
            // CAREER PATTERNS
            'career' => array(
                'primary_markers' => array(
                    '/\b(career|job|employment|profession|work)\b/i' => 100,
                    '/\b(become\s+a|how\s+to\s+become|path\s+to)\b/i' => 95,
                    '/\b(analyst|banker|trader|associate|VP|MD)\b/i' => 90,
                    '/\b(interview|recruit|hiring|application)\b/i' => 85,
                    '/\b(PE|IB|private\s+equity|investment\s+banking)\s+career/i' => 100
                ),
                'secondary_markers' => array(
                    '/\b(skills|qualification|experience|education)\b/i' => 45,
                    '/\b(salary|compensation|bonus|pay)\b/i' => 40,
                    '/\b(roadmap|pathway|journey|progression)\b/i' => 35
                ),
                'card_mapping' => 'career_roadmap_card',
                'confidence_threshold' => 60
            ),
            
            // COMPARISON PATTERNS
            'comparison' => array(
                'primary_markers' => array(
                    '/\b(compare|comparison|versus|vs\.?|difference)\b/i' => 100,
                    '/\b(between|which\s+is\s+better|or)\b/i' => 85,
                    '/\b([A-Z]\w+)\s+(vs\.?|versus|and)\s+([A-Z]\w+)/i' => 95,
                    '/\b(contrast|distinguish|relative\s+to)\b/i' => 80
                ),
                'secondary_markers' => array(
                    '/\b(better|worse|superior|inferior)\b/i' => 45,
                    '/\b(advantage|disadvantage|pros?\s+and\s+cons?)\b/i' => 40,
                    '/\b(similar|different|alike)\b/i' => 35
                ),
                'entity_requirements' => array('companies' => 2),
                'card_mapping' => 'comparison_matrix_card',
                'confidence_threshold' => 70
            ),
            
            // DEEP ANALYSIS PATTERNS
            'deep_analysis' => array(
                'primary_markers' => array(
                    '/\b(deep\s+dive|comprehensive|detailed\s+analysis|thorough)\b/i' => 100,
                    '/\b(in-?depth|extensive|complete\s+review)\b/i' => 95,
                    '/\b(earnings\s+report|quarterly\s+results|financial\s+statement)\b/i' => 90,
                    '/\b(breakdown|dissect|examine\s+closely)\b/i' => 85
                ),
                'secondary_markers' => array(
                    '/\b(segment|division|unit|subsidiary)\b/i' => 45,
                    '/\b(trend|pattern|correlation|driver)\b/i' => 40,
                    '/\b(implication|impact|effect|consequence)\b/i' => 35
                ),
                'card_mapping' => 'executive_digest_card',
                'confidence_threshold' => 65
            ),
            
            // STRATEGY PATTERNS
            'strategy' => array(
                'primary_markers' => array(
                    '/\b(strategy|strategic|approach|plan)\b/i' => 100,
                    '/\b(what\s+should\s+I|how\s+should\s+I|which\s+way)\b/i' => 95,
                    '/\b(investment\s+strategy|portfolio\s+strategy|trading\s+strategy)\b/i' => 90,
                    '/\b(choose|select|decide|option)\b/i' => 85
                ),
                'secondary_markers' => array(
                    '/\b(conservative|aggressive|balanced|moderate)\b/i' => 45,
                    '/\b(risk|return|allocation|diversification)\b/i' => 40,
                    '/\b(goal|objective|target|aim)\b/i' => 35
                ),
                'card_mapping' => 'strategy_choice_card',
                'confidence_threshold' => 60
            ),
            
            // MARKET OVERVIEW PATTERNS
            'market_overview' => array(
                'primary_markers' => array(
                    '/\b(market\s+overview|market\s+summary|market\s+status)\b/i' => 100,
                    '/\b(sector\s+performance|industry\s+performance)\b/i' => 95,
                    '/\b(heatmap|heat\s+map|market\s+map)\b/i' => 90,
                    '/\b(movers|gainers|losers|actives)\b/i' => 85
                ),
                'secondary_markers' => array(
                    '/\b(index|indices|dow|s&p|nasdaq)\b/i' => 45,
                    '/\b(broad\s+market|overall\s+market)\b/i' => 40,
                    '/\b(sentiment|momentum|flow)\b/i' => 35
                ),
                'card_mapping' => 'market_heatmap_card',
                'confidence_threshold' => 65
            ),
            
            // PE/DEALS PATTERNS
            'pe_deals' => array(
                'primary_markers' => array(
                    '/\b(PE\s+deal|private\s+equity\s+deal|buyout|acquisition)\b/i' => 100,
                    '/\b(M&A|merger|takeover|transaction)\b/i' => 95,
                    '/\b(KKR|Blackstone|Apollo|Carlyle|TPG)\b/i' => 90,
                    '/\b(LBO|leveraged\s+buyout|portfolio\s+company)\b/i' => 85
                ),
                'secondary_markers' => array(
                    '/\b(fund|sponsor|GP|LP)\b/i' => 45,
                    '/\b(exit|IPO|sale|divestiture)\b/i' => 40,
                    '/\b(multiple|valuation|enterprise\s+value)\b/i' => 35
                ),
                'card_mapping' => 'pe_deal_card',
                'confidence_threshold' => 60
            ),
            
            // VOLATILITY ANALYSIS PATTERNS - USER'S SPECIFIC REQUEST
            'volatility_patterns' => array(
                'primary_markers' => array(
                    '/\b(volatility|volatile|vol)\b/i' => 100,
                    '/\b(patterns?|trends?|behaviors?)\b/i' => 95,
                    '/\b(volatility\s+patterns?|vol\s+patterns?)\b/i' => 120,
                    '/\b(market\s+volatility|price\s+volatility)\b/i' => 110
                ),
                'secondary_markers' => array(
                    '/\b(analysis|analyze|examine)\b/i' => 50,
                    '/\b(VIX|volatility\s+index)\b/i' => 60,
                    '/\b(risk|uncertainty|fluctuation)\b/i' => 45,
                    '/\b(historical|implied|realized)\b/i' => 40
                ),
                'entity_requirements' => array('companies' => 0), // Optional
                'card_mapping' => 'volatility_analysis_card',
                'confidence_threshold' => 55
            ),
            
            // ESG & SUSTAINABILITY PATTERNS - USER'S SPECIFIC REQUEST  
            'esg_trends' => array(
                'primary_markers' => array(
                    '/\b(ESG|sustainability|sustainable)\b/i' => 100,
                    '/\b(investment\s+trends?|investing\s+trends?)\b/i' => 95,
                    '/\b(ESG\s+investment|sustainable\s+investment)\b/i' => 120,
                    '/\b(green\s+investing|impact\s+investing)\b/i' => 110
                ),
                'secondary_markers' => array(
                    '/\b(environmental|social|governance)\b/i' => 50,
                    '/\b(climate|carbon|renewable)\b/i' => 45,
                    '/\b(SRI|socially\s+responsible)\b/i' => 55,
                    '/\b(trends?|patterns?|movements?)\b/i' => 40
                ),
                'entity_requirements' => array('companies' => 0), // Optional  
                'card_mapping' => 'esg_metrics_card',
                'confidence_threshold' => 55
            ),
            
            // IMPROVED STOCK PRICE PATTERN - Lower threshold for better matching
            'stock_price_enhanced' => array(
                'primary_markers' => array(
                    '/\b(stock\s+prices?|share\s+prices?)\b/i' => 85,
                    '/\b(prices?)\s.{0,10}(stock|share|equity)\b/i' => 80,
                    '/\b(current\s+prices?|latest\s+prices?)\b/i' => 75
                ),
                'secondary_markers' => array(
                    '/\b(market|trading|quote)\b/i' => 35,
                    '/\b(today|now|live)\b/i' => 30,
                    '/\b(check|show|get)\b/i' => 25
                ),
                'entity_requirements' => array('companies' => 0), // Optional
                'card_mapping' => 'bloomberg_terminal_card', 
                'confidence_threshold' => 50
            )
        );
    }
    
    /**
     * Initialize linguistic markers with weights
     */
    private function initialize_linguistic_markers() {
        $this->linguistic_markers = array(
            'interrogative' => array(
                'what' => 10,
                'how' => 10,
                'why' => 10,
                'when' => 8,
                'where' => 8,
                'which' => 8,
                'who' => 7,
                'whose' => 6,
                'whom' => 6
            ),
            'imperative' => array(
                'show' => 12,
                'tell' => 12,
                'explain' => 15,
                'analyze' => 15,
                'compare' => 15,
                'provide' => 10,
                'give' => 10,
                'list' => 10,
                'find' => 10
            ),
            'modal' => array(
                'should' => 8,
                'would' => 7,
                'could' => 7,
                'can' => 6,
                'will' => 6,
                'might' => 5,
                'may' => 5,
                'must' => 8
            ),
            'temporal' => array(
                'today' => 10,
                'now' => 10,
                'current' => 10,
                'latest' => 12,
                'recent' => 10,
                'yesterday' => 8,
                'tomorrow' => 8,
                'this week' => 9,
                'this month' => 8,
                'this year' => 7
            )
        );
    }
    
    /**
     * Initialize contextual signals
     */
    private function initialize_contextual_signals() {
        $this->contextual_signals = array(
            'entity_density' => array(
                'high' => array('min' => 3, 'weight' => 20),
                'medium' => array('min' => 2, 'weight' => 10),
                'low' => array('min' => 1, 'weight' => 5)
            ),
            'query_length' => array(
                'long' => array('min' => 15, 'weight' => 15),
                'medium' => array('min' => 8, 'weight' => 10),
                'short' => array('min' => 3, 'weight' => 5)
            ),
            'specificity' => array(
                'ticker_present' => 25,
                'company_name_present' => 20,
                'financial_term_present' => 15,
                'number_present' => 10,
                'date_present' => 10
            ),
            'formality' => array(
                'formal' => 10,
                'casual' => 5,
                'technical' => 15
            )
        );
    }
    
    /**
     * Initialize intent classification matrix
     */
    private function initialize_intent_matrix() {
        $this->intent_matrix = array(
            'informational' => array(
                'patterns' => array('educational', 'market_news', 'company_analysis'),
                'weight' => 1.0
            ),
            'transactional' => array(
                'patterns' => array('stock_price', 'pe_deals'),
                'weight' => 1.2
            ),
            'navigational' => array(
                'patterns' => array('greeting', 'career'),
                'weight' => 0.9
            ),
            'analytical' => array(
                'patterns' => array('comparison', 'deep_analysis', 'market_overview'),
                'weight' => 1.3
            ),
            'advisory' => array(
                'patterns' => array('recommendations', 'strategy'),
                'weight' => 1.1
            )
        );
    }
    
    /**
     * Perform scientific pattern matching
     */
    public function match_patterns($query, $entities = array(), $context = array()) {
        $scores = array();
        $query_lower = strtolower($query);
        $query_length = str_word_count($query);
        
        // Step 1: Calculate base pattern scores
        foreach ($this->pattern_taxonomy as $pattern_type => $pattern_config) {
            $score = 0;
            $matches = array();
            
            // Primary markers (highest weight)
            foreach ($pattern_config['primary_markers'] as $regex => $weight) {
                if (preg_match($regex, $query, $match)) {
                    $score += $weight;
                    $matches[] = array('type' => 'primary', 'match' => $match[0], 'weight' => $weight);
                }
            }
            
            // Secondary markers
            if (isset($pattern_config['secondary_markers'])) {
                foreach ($pattern_config['secondary_markers'] as $regex => $weight) {
                    if (preg_match($regex, $query, $match)) {
                        $score += $weight;
                        $matches[] = array('type' => 'secondary', 'match' => $match[0], 'weight' => $weight);
                    }
                }
            }
            
            // Negative markers (reduce score)
            if (isset($pattern_config['negative_markers'])) {
                foreach ($pattern_config['negative_markers'] as $regex => $weight) {
                    if (preg_match($regex, $query)) {
                        $score += $weight; // Weight is negative
                    }
                }
            }
            
            // Entity requirements
            if (isset($pattern_config['entity_requirements'])) {
                $entity_boost = $this->check_entity_requirements($pattern_config['entity_requirements'], $entities);
                $score *= $entity_boost;
            }
            
            // Apply linguistic marker boosts
            $linguistic_boost = $this->calculate_linguistic_boost($query_lower, $pattern_type);
            $score += $linguistic_boost;
            
            // Apply contextual signal boosts
            $contextual_boost = $this->calculate_contextual_boost($query, $entities, $pattern_type);
            $score += $contextual_boost;
            
            // Store result
            $scores[$pattern_type] = array(
                'score' => $score,
                'confidence' => min(100, ($score / $pattern_config['confidence_threshold']) * 100),
                'card' => $pattern_config['card_mapping'],
                'matches' => $matches,
                'threshold' => $pattern_config['confidence_threshold']
            );
        }
        
        // Step 2: Apply intent classification weights
        $scores = $this->apply_intent_weights($scores, $query);
        
        // Step 3: Sort by score and confidence
        uasort($scores, function($a, $b) {
            if ($a['score'] == $b['score']) {
                return $b['confidence'] - $a['confidence'];
            }
            return $b['score'] - $a['score'];
        });
        
        // Step 4: Return top matches with confidence above threshold
        $results = array();
        foreach ($scores as $pattern_type => $data) {
            if ($data['score'] >= $data['threshold']) {
                $results[] = array(
                    'pattern' => $pattern_type,
                    'card' => $data['card'],
                    'score' => $data['score'],
                    'confidence' => $data['confidence'],
                    'matches' => $data['matches']
                );
            }
        }
        
        return $results;
    }
    
    /**
     * Check entity requirements
     */
    private function check_entity_requirements($requirements, $entities) {
        $boost = 1.0;
        
        foreach ($requirements as $entity_type => $min_count) {
            if ($entity_type === 'companies') {
                $count = isset($entities['companies']) ? count($entities['companies']) : 0;
                if ($min_count === 0) {
                    // Optional requirement - small boost if entities present, no penalty if not
                    $boost *= ($count > 0) ? 1.2 : 1.0;
                } else if ($count >= $min_count) {
                    $boost *= 1.5; // Boost if requirement met
                } else {
                    $boost *= 0.3; // Significant penalty if not met
                }
            }
        }
        
        return $boost;
    }
    
    /**
     * Calculate linguistic marker boost
     */
    private function calculate_linguistic_boost($query_lower, $pattern_type) {
        $boost = 0;
        
        // Check interrogatives
        foreach ($this->linguistic_markers['interrogative'] as $word => $weight) {
            if (strpos($query_lower, $word) !== false) {
                // Boost educational and analytical patterns for questions
                if (in_array($pattern_type, array('educational', 'company_analysis', 'deep_analysis'))) {
                    $boost += $weight * 1.5;
                } else {
                    $boost += $weight;
                }
            }
        }
        
        // Check imperatives
        foreach ($this->linguistic_markers['imperative'] as $word => $weight) {
            if (strpos($query_lower, $word) !== false) {
                // Boost action-oriented patterns
                if (in_array($pattern_type, array('recommendations', 'strategy', 'comparison'))) {
                    $boost += $weight * 1.5;
                } else {
                    $boost += $weight;
                }
            }
        }
        
        // Check temporal markers
        foreach ($this->linguistic_markers['temporal'] as $word => $weight) {
            if (strpos($query_lower, $word) !== false) {
                // Boost news and market overview patterns
                if (in_array($pattern_type, array('market_news', 'market_overview', 'stock_price'))) {
                    $boost += $weight * 2;
                } else {
                    $boost += $weight;
                }
            }
        }
        
        return $boost;
    }
    
    /**
     * Calculate contextual boost
     */
    private function calculate_contextual_boost($query, $entities, $pattern_type) {
        $boost = 0;
        
        // Entity density boost
        $entity_count = 0;
        if (!empty($entities['companies'])) $entity_count += count($entities['companies']);
        if (!empty($entities['financial_terms'])) $entity_count += count($entities['financial_terms']);
        
        foreach ($this->contextual_signals['entity_density'] as $level => $config) {
            if ($entity_count >= $config['min']) {
                $boost += $config['weight'];
                break;
            }
        }
        
        // Query length boost
        $word_count = str_word_count($query);
        foreach ($this->contextual_signals['query_length'] as $level => $config) {
            if ($word_count >= $config['min']) {
                if (in_array($pattern_type, array('deep_analysis', 'comparison', 'educational'))) {
                    $boost += $config['weight'] * 1.5; // Longer queries often mean deeper analysis
                } else {
                    $boost += $config['weight'];
                }
                break;
            }
        }
        
        // Specificity boost
        if (preg_match('/\$[A-Z]{1,5}/', $query)) {
            $boost += $this->contextual_signals['specificity']['ticker_present'];
        }
        if (preg_match('/\b[A-Z][a-z]+(\s+[A-Z][a-z]+)*\b/', $query)) {
            $boost += $this->contextual_signals['specificity']['company_name_present'];
        }
        if (preg_match('/\b\d+(\.\d+)?%?\b/', $query)) {
            $boost += $this->contextual_signals['specificity']['number_present'];
        }
        
        return $boost;
    }
    
    /**
     * Apply intent classification weights
     */
    private function apply_intent_weights($scores, $query) {
        // Determine primary intent
        $intent = $this->classify_intent($query);
        
        if (isset($this->intent_matrix[$intent])) {
            $intent_config = $this->intent_matrix[$intent];
            
            foreach ($scores as $pattern_type => &$data) {
                if (in_array($pattern_type, $intent_config['patterns'])) {
                    $data['score'] *= $intent_config['weight'];
                }
            }
        }
        
        return $scores;
    }
    
    /**
     * Classify query intent
     */
    private function classify_intent($query) {
        $query_lower = strtolower($query);
        
        if (preg_match('/\b(what|how|why|explain|define)\b/', $query_lower)) {
            return 'informational';
        }
        if (preg_match('/\b(price|quote|trading|value)\b/', $query_lower)) {
            return 'transactional';
        }
        if (preg_match('/\b(compare|versus|difference|analyze)\b/', $query_lower)) {
            return 'analytical';
        }
        if (preg_match('/\b(recommend|suggest|should|best)\b/', $query_lower)) {
            return 'advisory';
        }
        if (preg_match('/\b(hello|hi|career|help)\b/', $query_lower)) {
            return 'navigational';
        }
        
        return 'informational'; // Default
    }
    
    /**
     * Get pattern confidence explanation
     */
    public function explain_match($pattern_result) {
        $explanation = array(
            'pattern' => $pattern_result['pattern'],
            'confidence' => $pattern_result['confidence'],
            'reasoning' => array()
        );
        
        foreach ($pattern_result['matches'] as $match) {
            $explanation['reasoning'][] = sprintf(
                "Found %s marker '%s' (weight: %d)",
                $match['type'],
                $match['match'],
                $match['weight']
            );
        }
        
        return $explanation;
    }
}