<?php
/**
 * Context Analyzer
 * Phase 2: Analyzes query context and user intent
 * 
 * @package SennaCareers
 * @since 6.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Context_Analyzer {
    
    private static $instance = null;
    private $entity_extractor;
    private $data_cache;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Load entity extractor
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/patterns/class-entity-extractor.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/patterns/class-entity-extractor.php';
            $this->entity_extractor = SFFC_Entity_Extractor::get_instance();
        }
        
        // Load data cache
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-data-cache-manager.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-data-cache-manager.php';
            $this->data_cache = SFFC_Data_Cache_Manager::get_instance();
        }
    }
    
    /**
     * Build comprehensive context for query
     */
    public function analyze_context($query, $entities = null, $conversation_history = array()) {
        // Extract entities if not provided
        if (!$entities && $this->entity_extractor) {
            $entities = $this->entity_extractor->extract_entities($query);
        }
        
        $context = array(
            'market_context' => $this->get_market_context(),
            'temporal_context' => $this->get_temporal_context($entities),
            'entity_context' => $this->get_entity_context($entities),
            'sentiment_context' => $this->analyze_sentiment($query),
            'complexity_score' => $this->assess_complexity($query),
            'conversation_context' => $this->analyze_conversation($conversation_history),
            'data_requirements' => $this->determine_data_needs($query, $entities),
            'response_style' => $this->determine_response_style($query)
        );
        
        return $context;
    }
    
    /**
     * Get current market context
     */
    private function get_market_context() {
        $context = array(
            'market_hours' => $this->get_market_hours_status(),
            'market_trend' => 'neutral',
            'volatility' => 'normal',
            'key_events' => array()
        );
        
        // Get market data if available
        if ($this->data_cache) {
            $market_data = $this->data_cache->get_market_data('all');
            
            if (!empty($market_data)) {
                $context['market_trend'] = $market_data['summary']['trend'] ?? 'neutral';
                $context['volatility'] = $market_data['summary']['volatility'] ?? 'normal';
                
                // Check for significant moves
                if (!empty($market_data['indices'])) {
                    foreach ($market_data['indices'] as $index) {
                        if (abs($index['change_percent']) > 2) {
                            $context['key_events'][] = sprintf(
                                "%s %s %.2f%%",
                                $index['name'],
                                $index['change_percent'] > 0 ? 'up' : 'down',
                                abs($index['change_percent'])
                            );
                        }
                    }
                }
            }
        }
        
        return $context;
    }
    
    /**
     * Get market hours status
     */
    private function get_market_hours_status() {
        $now = new DateTime('now', new DateTimeZone('America/New_York'));
        $hour = (int) $now->format('H');
        $day = $now->format('N'); // 1 = Monday, 7 = Sunday
        
        // Weekend
        if ($day >= 6) {
            return 'closed_weekend';
        }
        
        // Pre-market: 4:00 AM - 9:30 AM ET
        if ($hour >= 4 && $hour < 9) {
            return 'pre_market';
        } elseif ($hour == 9 && (int) $now->format('i') < 30) {
            return 'pre_market';
        }
        
        // Regular hours: 9:30 AM - 4:00 PM ET
        if (($hour == 9 && (int) $now->format('i') >= 30) || ($hour >= 10 && $hour < 16)) {
            return 'regular_hours';
        }
        
        // After hours: 4:00 PM - 8:00 PM ET
        if ($hour >= 16 && $hour < 20) {
            return 'after_hours';
        }
        
        return 'closed';
    }
    
    /**
     * Get temporal context
     */
    private function get_temporal_context($entities) {
        $context = array(
            'primary_timeframe' => 'current',
            'specific_dates' => array(),
            'relative_time' => null
        );
        
        if (!empty($entities['timeframe'])) {
            $timeframe = $entities['timeframe'][0];
            $context['primary_timeframe'] = $timeframe['type'] ?? 'current';
            
            if (isset($timeframe['start'])) {
                $context['specific_dates']['start'] = $timeframe['start'];
            }
            if (isset($timeframe['end'])) {
                $context['specific_dates']['end'] = $timeframe['end'];
            }
            
            // Determine if historical or forward-looking
            if (in_array($timeframe['type'], array('past', 'past_period'))) {
                $context['relative_time'] = 'historical';
            } elseif (in_array($timeframe['type'], array('future', 'forecast'))) {
                $context['relative_time'] = 'forward_looking';
            } else {
                $context['relative_time'] = 'current';
            }
        }
        
        return $context;
    }
    
    /**
     * Get entity-specific context
     */
    private function get_entity_context($entities) {
        $context = array(
            'primary_entities' => array(),
            'entity_types' => array(),
            'entity_count' => 0
        );
        
        // Identify primary entities
        if (!empty($entities['companies'])) {
            foreach ($entities['companies'] as $company) {
                $context['primary_entities'][] = array(
                    'type' => 'company',
                    'name' => $company['name'],
                    'subtype' => $company['type']
                );
            }
            $context['entity_types'][] = 'company';
        }
        
        if (!empty($entities['indices'])) {
            foreach ($entities['indices'] as $index) {
                $context['primary_entities'][] = array(
                    'type' => 'index',
                    'name' => $index['name'],
                    'symbol' => $index['symbol']
                );
            }
            $context['entity_types'][] = 'index';
        }
        
        if (!empty($entities['sectors'])) {
            foreach ($entities['sectors'] as $sector) {
                $context['primary_entities'][] = array(
                    'type' => 'sector',
                    'name' => $sector['name']
                );
            }
            $context['entity_types'][] = 'sector';
        }
        
        $context['entity_count'] = count($context['primary_entities']);
        
        // Add entity relationships if multiple entities
        if ($context['entity_count'] > 1) {
            $context['requires_comparison'] = true;
        }
        
        return $context;
    }
    
    /**
     * Analyze sentiment
     */
    private function analyze_sentiment($query) {
        $sentiment = array(
            'overall' => 'neutral',
            'score' => 0,
            'indicators' => array()
        );
        
        // Positive indicators
        $positive_words = array(
            'bull', 'bullish', 'up', 'rise', 'gain', 'growth', 'positive', 
            'opportunity', 'strong', 'outperform', 'beat', 'exceed', 'surge'
        );
        
        // Negative indicators
        $negative_words = array(
            'bear', 'bearish', 'down', 'fall', 'loss', 'decline', 'negative',
            'risk', 'weak', 'underperform', 'miss', 'plunge', 'crash'
        );
        
        // Uncertainty indicators
        $uncertainty_words = array(
            'volatile', 'uncertain', 'maybe', 'possibly', 'might', 'could',
            'concern', 'worry', 'cautious'
        );
        
        $query_lower = strtolower($query);
        $positive_count = 0;
        $negative_count = 0;
        $uncertainty_count = 0;
        
        foreach ($positive_words as $word) {
            if (strpos($query_lower, $word) !== false) {
                $positive_count++;
                $sentiment['indicators'][] = array('type' => 'positive', 'word' => $word);
            }
        }
        
        foreach ($negative_words as $word) {
            if (strpos($query_lower, $word) !== false) {
                $negative_count++;
                $sentiment['indicators'][] = array('type' => 'negative', 'word' => $word);
            }
        }
        
        foreach ($uncertainty_words as $word) {
            if (strpos($query_lower, $word) !== false) {
                $uncertainty_count++;
                $sentiment['indicators'][] = array('type' => 'uncertainty', 'word' => $word);
            }
        }
        
        // Calculate overall sentiment
        $sentiment['score'] = ($positive_count - $negative_count) / max(1, $positive_count + $negative_count);
        
        if ($sentiment['score'] > 0.3) {
            $sentiment['overall'] = 'positive';
        } elseif ($sentiment['score'] < -0.3) {
            $sentiment['overall'] = 'negative';
        } elseif ($uncertainty_count > 2) {
            $sentiment['overall'] = 'uncertain';
        }
        
        return $sentiment;
    }
    
    /**
     * Assess query complexity
     */
    private function assess_complexity($query) {
        $complexity_score = 0;
        
        // Length factor
        $word_count = str_word_count($query);
        if ($word_count > 20) {
            $complexity_score += 3;
        } elseif ($word_count > 10) {
            $complexity_score += 2;
        } elseif ($word_count > 5) {
            $complexity_score += 1;
        }
        
        // Technical terms
        $technical_terms = array(
            'DCF', 'LBO', 'EBITDA', 'P/E', 'EV/EBITDA', 'IRR', 'WACC',
            'beta', 'alpha', 'correlation', 'volatility', 'derivatives',
            'hedging', 'arbitrage', 'yield curve', 'duration', 'convexity'
        );
        
        foreach ($technical_terms as $term) {
            if (stripos($query, $term) !== false) {
                $complexity_score += 2;
            }
        }
        
        // Multiple entity analysis
        if (preg_match('/compare|versus|vs|between/i', $query)) {
            $complexity_score += 2;
        }
        
        // Causal analysis
        if (preg_match('/why|how|explain|cause|impact/i', $query)) {
            $complexity_score += 2;
        }
        
        // Forward-looking analysis
        if (preg_match('/will|forecast|predict|outlook|future/i', $query)) {
            $complexity_score += 2;
        }
        
        return min($complexity_score, 10); // Cap at 10
    }
    
    /**
     * Analyze conversation history
     */
    private function analyze_conversation($history) {
        $context = array(
            'is_followup' => !empty($history),
            'topic_continuity' => false,
            'previous_entities' => array(),
            'conversation_depth' => count($history)
        );
        
        if (!empty($history)) {
            // Get entities from recent messages
            $recent = array_slice($history, -3);
            foreach ($recent as $message) {
                if ($this->entity_extractor) {
                    $prev_entities = $this->entity_extractor->extract_entities($message);
                    if (!empty($prev_entities['companies'])) {
                        foreach ($prev_entities['companies'] as $company) {
                            $context['previous_entities'][] = $company['name'];
                        }
                    }
                }
            }
            
            // Check for topic continuity
            if (!empty($context['previous_entities'])) {
                $context['topic_continuity'] = true;
            }
        }
        
        return $context;
    }
    
    /**
     * Determine data requirements
     */
    private function determine_data_needs($query, $entities) {
        $data_needs = array();
        
        // Market data needs
        if (preg_match('/market|index|indices|S&P|Nasdaq|Dow/i', $query)) {
            $data_needs[] = 'market_data';
        }
        
        // Company data needs
        if (!empty($entities['companies'])) {
            $data_needs[] = 'company_data';
            if (preg_match('/financials|earnings|revenue|profit/i', $query)) {
                $data_needs[] = 'financial_statements';
            }
        }
        
        // Sector data needs
        if (!empty($entities['sectors']) || preg_match('/sector/i', $query)) {
            $data_needs[] = 'sector_data';
        }
        
        // News needs
        if (preg_match('/news|latest|recent|announce/i', $query)) {
            $data_needs[] = 'news_data';
        }
        
        // PE/M&A needs
        if (preg_match('/PE|private equity|M&A|merger|acquisition|deal/i', $query)) {
            $data_needs[] = 'deal_data';
        }
        
        // Economic data needs
        if (preg_match('/GDP|inflation|unemployment|Fed|interest rate/i', $query)) {
            $data_needs[] = 'economic_data';
        }
        
        // Technical analysis needs
        if (preg_match('/technical|chart|support|resistance|moving average/i', $query)) {
            $data_needs[] = 'technical_data';
        }
        
        return $data_needs;
    }
    
    /**
     * Determine appropriate response style
     */
    private function determine_response_style($query) {
        $style = array(
            'format' => 'narrative',
            'detail_level' => 'standard',
            'tone' => 'professional',
            'urgency' => 'normal'
        );
        
        // Determine format
        if (preg_match('/list|bullet|summary/i', $query)) {
            $style['format'] = 'list';
        } elseif (preg_match('/table|compare|comparison/i', $query)) {
            $style['format'] = 'comparison';
        } elseif (preg_match('/explain|why|how/i', $query)) {
            $style['format'] = 'explanation';
        }
        
        // Determine detail level
        if (preg_match('/brief|quick|summary|overview/i', $query)) {
            $style['detail_level'] = 'brief';
        } elseif (preg_match('/detail|comprehensive|thorough|deep/i', $query)) {
            $style['detail_level'] = 'detailed';
        }
        
        // Determine urgency
        if (preg_match('/urgent|asap|now|immediately/i', $query)) {
            $style['urgency'] = 'high';
        }
        
        // Determine tone based on query type
        if (preg_match('/recommend|advise|should/i', $query)) {
            $style['tone'] = 'advisory';
        } elseif (preg_match('/risk|concern|worry/i', $query)) {
            $style['tone'] = 'cautious';
        }
        
        return $style;
    }
    
    /**
     * Get historical patterns
     */
    public function find_historical_patterns($entities, $timeframe = '30 days') {
        global $wpdb;
        
        $patterns = array();
        
        if (empty($entities)) {
            return $patterns;
        }
        
        $table = $wpdb->prefix . 'sffc_pattern_history';
        
        // Build entity condition
        $entity_json = json_encode($entities);
        
        // Query for similar patterns
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT detected_pattern, COUNT(*) as frequency 
             FROM $table 
             WHERE created_at > DATE_SUB(NOW(), INTERVAL %s)
             AND entities_extracted LIKE %s
             GROUP BY detected_pattern 
             ORDER BY frequency DESC 
             LIMIT 5",
            $timeframe,
            '%' . $wpdb->esc_like($entity_json) . '%'
        ));
        
        if ($results) {
            foreach ($results as $result) {
                $patterns[] = array(
                    'pattern' => $result->detected_pattern,
                    'frequency' => $result->frequency
                );
            }
        }
        
        return $patterns;
    }
}