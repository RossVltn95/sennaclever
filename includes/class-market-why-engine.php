<?php
/**
 * Market WHY Engine - Deep analysis of causality and market mechanics
 * The core differentiator: Explaining WHY things happen, not just reporting
 * 
 * @package SennaCareers  
 * @subpackage Market
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Market_Why_Engine {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Analysis depth levels
     */
    private $analysis_levels = array(
        'surface' => 'What everyone sees',
        'mechanism' => 'How markets actually work',
        'psychology' => 'What traders are thinking',
        'structural' => 'Deep market structure',
        'strategic' => 'Long-term implications'
    );
    
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
     * Core WHY Analysis - Multi-layer deep dive
     */
    public function analyze_why($event, $context = array()) {
        $analysis = array(
            'event_summary' => $this->summarize_event($event),
            'multi_layer_why' => $this->build_why_layers($event),
            'causality_chain' => $this->trace_causality($event),
            'market_mechanics' => $this->explain_mechanics($event),
            'behavioral_factors' => $this->analyze_behavior($event),
            'structural_factors' => $this->analyze_structure($event),
            'second_order_effects' => $this->find_second_order($event),
            'third_order_effects' => $this->find_third_order($event),
            'contrarian_view' => $this->generate_contrarian($event),
            'historical_analog' => $this->find_historical_analog($event),
            'career_implications' => $this->derive_career_impact($event),
            'learning_extraction' => $this->extract_learnings($event),
            'visual_recommendations' => $this->recommend_visuals($event)
        );
        
        return $analysis;
    }
    
    /**
     * Build multi-layer WHY analysis
     */
    private function build_why_layers($event) {
        $layers = array();
        
        // Layer 1: Immediate trigger
        $layers['trigger'] = array(
            'what' => $this->identify_trigger($event),
            'why_it_matters' => $this->explain_trigger_importance($event),
            'market_reaction' => $this->predict_immediate_reaction($event)
        );
        
        // Layer 2: Underlying fundamentals
        $layers['fundamentals'] = array(
            'economic_factors' => $this->identify_economic_factors($event),
            'business_drivers' => $this->identify_business_drivers($event),
            'valuation_impact' => $this->calculate_valuation_impact($event)
        );
        
        // Layer 3: Market structure
        $layers['structure'] = array(
            'positioning' => $this->analyze_positioning($event),
            'flows' => $this->analyze_flows($event),
            'technical_levels' => $this->identify_technical_levels($event)
        );
        
        // Layer 4: Sentiment and psychology
        $layers['psychology'] = array(
            'sentiment_shift' => $this->detect_sentiment_shift($event),
            'narrative_change' => $this->identify_narrative_change($event),
            'crowd_behavior' => $this->predict_crowd_behavior($event)
        );
        
        // Layer 5: Strategic implications
        $layers['strategic'] = array(
            'regime_change' => $this->assess_regime_change($event),
            'opportunity_set' => $this->identify_opportunities($event),
            'risk_factors' => $this->identify_new_risks($event)
        );
        
        return $layers;
    }
    
    /**
     * Trace complete causality chain
     */
    private function trace_causality($event) {
        $chain = array();
        
        // Start with the triggering event
        $current = array(
            'event' => $event['title'],
            'because' => $this->find_root_cause($event),
            'leads_to' => $this->predict_first_order($event),
            'timeframe' => 'Immediate (0-24 hours)'
        );
        $chain[] = $current;
        
        // Build the chain of consequences
        $consequences = $this->build_consequence_chain($event);
        foreach ($consequences as $consequence) {
            $chain[] = array(
                'event' => $consequence['what'],
                'because' => $consequence['why'],
                'leads_to' => $consequence['result'],
                'timeframe' => $consequence['when']
            );
        }
        
        // Add ultimate outcome
        $chain[] = array(
            'event' => 'Ultimate market outcome',
            'because' => $this->synthesize_factors($chain),
            'leads_to' => $this->predict_end_state($event),
            'timeframe' => 'Long-term (6-12 months)'
        );
        
        return $chain;
    }
    
    /**
     * Explain market mechanics in detail
     */
    private function explain_mechanics($event) {
        return array(
            'price_discovery' => array(
                'mechanism' => $this->explain_price_mechanism($event),
                'participants' => $this->identify_key_participants($event),
                'information_flow' => $this->trace_information_flow($event)
            ),
            'liquidity_dynamics' => array(
                'providers' => $this->identify_liquidity_providers($event),
                'conditions' => $this->assess_liquidity_conditions($event),
                'impact' => $this->calculate_liquidity_impact($event)
            ),
            'risk_transfer' => array(
                'hedging_activity' => $this->analyze_hedging($event),
                'derivative_flows' => $this->analyze_derivatives($event),
                'correlation_effects' => $this->analyze_correlations($event)
            ),
            'feedback_loops' => array(
                'positive_feedback' => $this->identify_positive_loops($event),
                'negative_feedback' => $this->identify_negative_loops($event),
                'stability_assessment' => $this->assess_stability($event)
            )
        );
    }
    
    /**
     * Analyze behavioral factors
     */
    private function analyze_behavior($event) {
        return array(
            'institutional_behavior' => array(
                'positioning' => $this->analyze_institutional_positioning($event),
                'rebalancing' => $this->predict_rebalancing($event),
                'risk_management' => $this->predict_risk_actions($event)
            ),
            'retail_behavior' => array(
                'sentiment' => $this->gauge_retail_sentiment($event),
                'flow_patterns' => $this->analyze_retail_flows($event),
                'behavioral_biases' => $this->identify_biases($event)
            ),
            'algo_behavior' => array(
                'systematic_response' => $this->predict_algo_response($event),
                'momentum_effects' => $this->calculate_momentum($event),
                'volatility_targeting' => $this->predict_vol_targeting($event)
            )
        );
    }
    
    /**
     * Find second-order effects
     */
    private function find_second_order($event) {
        $effects = array();
        
        // Sector spillovers
        $effects['sector_spillover'] = array(
            'affected_sectors' => $this->identify_affected_sectors($event),
            'transmission_mechanism' => $this->explain_transmission($event),
            'magnitude' => $this->estimate_spillover_magnitude($event)
        );
        
        // Cross-asset effects
        $effects['cross_asset'] = array(
            'correlated_assets' => $this->find_correlated_assets($event),
            'substitution_effects' => $this->identify_substitutes($event),
            'portfolio_rebalancing' => $this->predict_rebalancing_flows($event)
        );
        
        // Policy responses
        $effects['policy'] = array(
            'central_bank' => $this->predict_cb_response($event),
            'regulatory' => $this->predict_regulatory_response($event),
            'fiscal' => $this->predict_fiscal_response($event)
        );
        
        return $effects;
    }
    
    /**
     * Find third-order effects - the unexpected consequences
     */
    private function find_third_order($event) {
        return array(
            'unintended_consequences' => $this->identify_unintended($event),
            'systemic_risks' => $this->assess_systemic_risk($event),
            'opportunity_creation' => $this->find_hidden_opportunities($event),
            'paradigm_shifts' => $this->identify_paradigm_shifts($event)
        );
    }
    
    /**
     * Generate contrarian analysis
     */
    private function generate_contrarian($event) {
        return array(
            'consensus_view' => $this->identify_consensus($event),
            'contrarian_thesis' => $this->build_contrarian_thesis($event),
            'supporting_evidence' => $this->find_contrarian_evidence($event),
            'risk_reward' => $this->calculate_contrarian_rr($event),
            'implementation' => $this->suggest_contrarian_trade($event)
        );
    }
    
    /**
     * Career impact analysis
     */
    private function derive_career_impact($event) {
        $impacts = array();
        
        // By role
        $roles = array(
            'pe_associate' => array(
                'immediate' => 'Deal flow implications',
                'skills_needed' => 'Valuation adjustments',
                'opportunities' => 'Distressed situations'
            ),
            'ib_analyst' => array(
                'immediate' => 'Advisory opportunities',
                'skills_needed' => 'Crisis management',
                'opportunities' => 'Restructuring mandates'
            ),
            'hf_analyst' => array(
                'immediate' => 'Trading opportunities',
                'skills_needed' => 'Volatility strategies',
                'opportunities' => 'Event-driven trades'
            )
        );
        
        foreach ($roles as $role => $impact) {
            $impacts[$role] = $this->analyze_role_impact($event, $role, $impact);
        }
        
        return $impacts;
    }
    
    /**
     * Extract learnings for knowledge building
     */
    private function extract_learnings($event) {
        return array(
            'key_principle' => $this->identify_principle($event),
            'market_lesson' => $this->extract_lesson($event),
            'application' => $this->suggest_application($event),
            'mental_model' => $this->build_mental_model($event),
            'quiz_question' => $this->create_quiz($event)
        );
    }
    
    /**
     * Recommend appropriate visuals
     */
    private function recommend_visuals($event) {
        $visuals = array();
        
        // Analyze event characteristics
        $characteristics = $this->analyze_event_characteristics($event);
        
        // Core visuals - always include
        $visuals[] = 'causality_chain';
        $visuals[] = 'multi_factor_analysis';
        
        // Conditional visuals based on event type
        if ($characteristics['has_earnings']) {
            $visuals[] = 'earnings_analysis';
        }
        
        if ($characteristics['has_macro']) {
            $visuals[] = 'macro_factors';
        }
        
        if ($characteristics['has_sentiment']) {
            $visuals[] = 'market_psychology';
        }
        
        if ($characteristics['has_technical']) {
            $visuals[] = 'tech_fund_divergence';
        }
        
        if ($characteristics['has_flows']) {
            $visuals[] = 'systematic_flow';
        }
        
        // Educational visual
        $visuals[] = 'knowledge_check';
        
        return $visuals;
    }
    
    /**
     * Build comprehensive market narrative
     */
    public function build_market_narrative($events, $timeframe = 'today') {
        $narrative = array(
            'headline' => $this->create_headline($events, $timeframe),
            'summary' => $this->summarize_market_state($events),
            'key_themes' => $this->identify_themes($events),
            'connecting_dots' => $this->connect_events($events),
            'hidden_story' => $this->find_hidden_narrative($events),
            'forward_looking' => $this->project_forward($events),
            'actionable_insights' => $this->generate_actionables($events)
        );
        
        return $narrative;
    }
    
    /**
     * Helper methods for deep analysis
     */
    private function identify_trigger($event) {
        // Parse event for triggering factor
        $triggers = array(
            'earnings' => '/earnings|EPS|revenue|guidance/i',
            'macro' => '/GDP|inflation|employment|Fed|ECB/i',
            'deal' => '/acquisition|merger|buyout|IPO/i',
            'technical' => '/resistance|support|breakout|breakdown/i'
        );
        
        foreach ($triggers as $type => $pattern) {
            if (preg_match($pattern, $event['title'] . ' ' . $event['description'])) {
                return $type;
            }
        }
        
        return 'general';
    }
    
    private function find_root_cause($event) {
        // This would integrate with Claude API for deeper analysis
        // For now, return structured analysis
        return "Fundamental shift in market expectations driven by " . $this->identify_trigger($event);
    }
    
    private function build_consequence_chain($event) {
        // Build chain of consequences
        return array(
            array(
                'what' => 'Initial market reaction',
                'why' => 'Algorithmic and momentum traders respond',
                'result' => 'Price movement and volatility spike',
                'when' => 'First hour'
            ),
            array(
                'what' => 'Institutional repositioning',
                'why' => 'Risk models trigger rebalancing',
                'result' => 'Sector rotation and correlation effects',
                'when' => '1-3 days'
            ),
            array(
                'what' => 'Fundamental reassessment',
                'why' => 'Analysts update models and targets',
                'result' => 'Valuation adjustments across sector',
                'when' => '1-2 weeks'
            )
        );
    }
    
    // Stub methods to fix PHPStan errors - these need proper implementation
    private function summarize_event($event) { return isset($event['summary']) ? $event['summary'] : 'Market event'; }
    private function analyze_structure($event) { return 'Market structure analysis'; }
    private function find_historical_analog($event) { return '2008 Financial Crisis'; }
    private function explain_trigger_importance($event) { return 'Critical market trigger'; }
    private function predict_immediate_reaction($event) { return 'Volatility spike expected'; }
    private function identify_economic_factors($event) { return array('inflation', 'growth', 'employment'); }
    private function identify_business_drivers($event) { return array('earnings', 'margins', 'guidance'); }
    private function calculate_valuation_impact($event) { return '5-10% adjustment expected'; }
    private function analyze_positioning($event) { return 'Market positioned defensively'; }
    private function analyze_flows($event) { return 'Outflows from risk assets'; }
    private function identify_technical_levels($event) { return array('support' => 4200, 'resistance' => 4400); }
    private function detect_sentiment_shift($event) { return 'Risk-off sentiment emerging'; }
    private function identify_narrative_change($event) { return 'Shift from growth to value'; }
    private function predict_crowd_behavior($event) { return 'Herd behavior likely'; }
    private function assess_regime_change($event) { return 'Potential regime shift'; }
    private function identify_opportunities($event) { return array('oversold sectors', 'quality names'); }
    private function identify_new_risks($event) { return array('contagion risk', 'liquidity risk'); }
    
    // Additional stub methods for WHY analysis
    private function predict_first_order($event) { return 'Initial market reaction'; }
    private function synthesize_factors($chain) { return 'Combined market forces'; }
    private function predict_end_state($event) { return 'Market equilibrium'; }
    private function explain_price_mechanism($event) { return 'Supply and demand dynamics'; }
    private function identify_key_participants($event) { return array('institutions', 'retail', 'algos'); }
    private function trace_information_flow($event) { return 'News -> Algos -> Market makers -> Retail'; }
    private function identify_liquidity_providers($event) { return array('market makers', 'HFT'); }
    private function assess_liquidity_conditions($event) { return 'Normal liquidity'; }
    private function calculate_liquidity_impact($event) { return 'Minimal impact expected'; }
    private function analyze_hedging($event) { return 'Increased hedging activity'; }
    private function analyze_derivatives($event) { return 'Options flow neutral'; }
    private function analyze_correlations($event) { return 'Normal correlations'; }
    private function identify_positive_loops($event) { return 'Momentum reinforcement'; }
    private function identify_negative_loops($event) { return 'Mean reversion forces'; }
    private function assess_stability($event) { return 'System stable'; }
    private function analyze_institutional_positioning($event) { return 'Neutral positioning'; }
    private function predict_rebalancing($event) { return 'Monthly rebalancing expected'; }
    private function predict_risk_actions($event) { return 'De-risking likely'; }
    private function gauge_retail_sentiment($event) { return 'Retail bullish'; }
    private function analyze_retail_flows($event) { return 'Inflows steady'; }
    private function identify_biases($event) { return array('recency', 'anchoring'); }
    private function predict_algo_response($event) { return 'Algorithmic selling'; }
    private function calculate_momentum($event) { return 'Positive momentum'; }
    private function predict_vol_targeting($event) { return 'Vol target reduction'; }
    private function identify_affected_sectors($event) { return array('tech', 'finance'); }
    private function explain_transmission($event) { return 'Cross-sector correlation'; }
    private function estimate_spillover_magnitude($event) { return '30-40% spillover'; }
    private function find_correlated_assets($event) { return array('bonds', 'gold'); }
    private function identify_substitutes($event) { return array('alternatives'); }
    private function predict_rebalancing_flows($event) { return 'Rotation expected'; }
    private function predict_cb_response($event) { return 'Dovish pivot likely'; }
    private function predict_regulatory_response($event) { return 'Enhanced oversight'; }
    private function predict_fiscal_response($event) { return 'Stimulus possible'; }
    private function identify_unintended($event) { return 'Unintended consequences'; }
    private function assess_systemic_risk($event) { return 'Low systemic risk'; }
    private function find_hidden_opportunities($event) { return array('dislocations'); }
    private function identify_paradigm_shifts($event) { return 'No paradigm shift'; }
    private function identify_consensus($event) { return 'Market consensus view'; }
    private function build_contrarian_thesis($event) { return 'Contrarian opportunity'; }
    private function find_contrarian_evidence($event) { return array('evidence'); }
    private function calculate_contrarian_rr($event) { return '3:1 risk/reward'; }
    private function suggest_contrarian_trade($event) { return 'Long volatility'; }
    private function analyze_role_impact($event, $role, $impact) { return $impact; }
    private function identify_principle($event) { return 'Market principle'; }
    private function extract_lesson($event) { return 'Key lesson learned'; }
    private function suggest_application($event) { return 'Apply to portfolio'; }
    private function build_mental_model($event) { return 'Mental model framework'; }
    private function create_quiz($event) { return array('question' => 'What caused this?', 'answer' => 'Market forces'); }
    private function analyze_event_characteristics($event) { return array('has_earnings' => false, 'has_macro' => true, 'has_sentiment' => true, 'has_technical' => false, 'has_flows' => true); }
    private function create_headline($events, $timeframe) { return 'Market Update: ' . $timeframe; }
    private function summarize_market_state($events) { return 'Market summary'; }
    private function identify_themes($events) { return array('volatility', 'rotation'); }
    private function connect_events($events) { return 'Events connected by theme'; }
    private function find_hidden_narrative($events) { return 'Hidden market story'; }
    private function project_forward($events) { return 'Forward projection'; }
    private function generate_actionables($events) { return array('monitor', 'position'); }
}