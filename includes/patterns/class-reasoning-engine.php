<?php
/**
 * Financial Reasoning Engine - Phase 3
 * Applies financial logic rules and market causation patterns
 * 
 * @package SennaCareers
 * @since 6.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Reasoning_Engine {
    
    private static $instance = null;
    
    /**
     * Market causation rules with confidence scores
     */
    private $market_rules = array(
        // Interest Rate Impacts
        'interest_rates_impact' => array(
            'if' => 'interest_rates_increase',
            'then' => array(
                'valuations_decrease' => 0.8,
                'pe_activity_slows' => 0.7,
                'refinancing_harder' => 0.9,
                'dividend_stocks_less_attractive' => 0.6,
                'bond_yields_increase' => 0.95,
                'tech_valuations_compress' => 0.85
            )
        ),
        
        // Market Volatility Rules
        'market_volatility' => array(
            'if' => 'vix_above_25',
            'then' => array(
                'ipo_window_closes' => 0.85,
                'ma_activity_slows' => 0.7,
                'flight_to_quality' => 0.8,
                'spreads_widen' => 0.75,
                'defensive_sectors_outperform' => 0.7,
                'risk_assets_underperform' => 0.8
            )
        ),
        
        // Economic Growth Indicators
        'economic_growth' => array(
            'if' => 'gdp_growth_strong',
            'then' => array(
                'cyclicals_outperform' => 0.75,
                'employment_improves' => 0.8,
                'consumer_spending_increases' => 0.7,
                'corporate_earnings_grow' => 0.75,
                'credit_conditions_ease' => 0.65
            )
        ),
        
        // Inflation Impacts
        'inflation_impacts' => array(
            'if' => 'inflation_rising',
            'then' => array(
                'fed_hawkish' => 0.8,
                'real_assets_outperform' => 0.75,
                'bonds_underperform' => 0.85,
                'energy_sector_benefits' => 0.7,
                'consumer_discretionary_suffers' => 0.65
            )
        )
    );
    
    /**
     * Sector correlation patterns
     */
    private $sector_correlations = array(
        'tech_sector' => array(
            'positive_with' => array('nasdaq' => 0.9, 'growth_stocks' => 0.85, 'semiconductors' => 0.8),
            'negative_with' => array('utilities' => -0.4, 'value_stocks' => -0.3)
        ),
        'energy_sector' => array(
            'positive_with' => array('oil_prices' => 0.85, 'materials' => 0.6, 'inflation_expectations' => 0.7),
            'negative_with' => array('airlines' => -0.6, 'consumer_discretionary' => -0.3)
        ),
        'financial_sector' => array(
            'positive_with' => array('interest_rates' => 0.8, 'yield_curve' => 0.75, 'economic_growth' => 0.7),
            'negative_with' => array('credit_defaults' => -0.8, 'regulation_risk' => -0.5)
        ),
        'healthcare_sector' => array(
            'positive_with' => array('demographics' => 0.6, 'biotech_innovation' => 0.7),
            'negative_with' => array('drug_pricing_pressure' => -0.6, 'regulatory_changes' => -0.5)
        )
    );
    
    /**
     * Market condition indicators
     */
    private $market_conditions = array(
        'bull_market' => array(
            'indicators' => array(
                'indices_near_highs' => array('weight' => 0.3, 'threshold' => 0.95),
                'low_vix' => array('weight' => 0.2, 'threshold' => 15),
                'positive_breadth' => array('weight' => 0.2, 'threshold' => 0.6),
                'earnings_growth' => array('weight' => 0.3, 'threshold' => 0.05)
            ),
            'confidence_threshold' => 0.7
        ),
        'bear_market' => array(
            'indicators' => array(
                'indices_20_percent_down' => array('weight' => 0.4, 'threshold' => -0.2),
                'high_vix' => array('weight' => 0.2, 'threshold' => 30),
                'negative_breadth' => array('weight' => 0.2, 'threshold' => 0.4),
                'earnings_decline' => array('weight' => 0.2, 'threshold' => -0.05)
            ),
            'confidence_threshold' => 0.75
        ),
        'correction' => array(
            'indicators' => array(
                'indices_10_percent_down' => array('weight' => 0.5, 'threshold' => -0.1),
                'elevated_vix' => array('weight' => 0.3, 'threshold' => 20),
                'mixed_breadth' => array('weight' => 0.2, 'threshold' => 0.5)
            ),
            'confidence_threshold' => 0.65
        )
    );
    
    /**
     * PE/Finance specific rules
     */
    private $pe_rules = array(
        'deal_environment' => array(
            'favorable' => array(
                'low_rates' => 0.3,
                'available_credit' => 0.25,
                'stable_markets' => 0.2,
                'strong_exits' => 0.25
            ),
            'challenging' => array(
                'high_rates' => 0.3,
                'credit_tightening' => 0.3,
                'volatile_markets' => 0.2,
                'limited_exits' => 0.2
            )
        ),
        'valuation_drivers' => array(
            'multiple_expansion' => array('low_rates' => 0.4, 'growth_acceleration' => 0.3, 'market_optimism' => 0.3),
            'multiple_compression' => array('high_rates' => 0.4, 'growth_deceleration' => 0.3, 'risk_aversion' => 0.3)
        )
    );
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Analyze market conditions based on current data
     */
    public function analyze_market_condition($data) {
        $conditions = array();
        $confidence_scores = array();
        
        // Evaluate each market condition
        foreach ($this->market_conditions as $condition_name => $condition_rules) {
            $score = $this->evaluate_condition($condition_rules, $data);
            
            if ($score >= $condition_rules['confidence_threshold']) {
                $conditions[] = $condition_name;
                $confidence_scores[$condition_name] = $score;
            }
        }
        
        // Apply market rules based on conditions
        $implications = $this->derive_implications($conditions, $data);
        
        return array(
            'primary_condition' => !empty($conditions) ? $conditions[0] : 'neutral',
            'confidence' => !empty($confidence_scores) ? max($confidence_scores) : 0.5,
            'all_conditions' => $conditions,
            'implications' => $implications,
            'timestamp' => current_time('mysql')
        );
    }
    
    /**
     * Evaluate a specific market condition
     */
    private function evaluate_condition($rules, $data) {
        $total_score = 0;
        $total_weight = 0;
        
        foreach ($rules['indicators'] as $indicator => $config) {
            $indicator_value = $this->get_indicator_value($indicator, $data);
            
            if ($indicator_value !== null) {
                $meets_threshold = $this->check_threshold($indicator_value, $config['threshold']);
                $total_score += $meets_threshold ? $config['weight'] : 0;
            }
            
            $total_weight += $config['weight'];
        }
        
        return $total_weight > 0 ? ($total_score / $total_weight) : 0;
    }
    
    /**
     * Get indicator value from data
     */
    private function get_indicator_value($indicator, $data) {
        // Map indicator names to data fields
        $indicator_map = array(
            'indices_near_highs' => 'index_position_percentile',
            'low_vix' => 'vix_level',
            'high_vix' => 'vix_level',
            'positive_breadth' => 'advance_decline_ratio',
            'negative_breadth' => 'advance_decline_ratio',
            'earnings_growth' => 'sp500_earnings_growth',
            'earnings_decline' => 'sp500_earnings_growth'
        );
        
        $data_field = isset($indicator_map[$indicator]) ? $indicator_map[$indicator] : $indicator;
        
        return isset($data[$data_field]) ? $data[$data_field] : null;
    }
    
    /**
     * Check if value meets threshold
     */
    private function check_threshold($value, $threshold) {
        if (is_numeric($threshold)) {
            if ($threshold < 0) {
                return $value <= $threshold; // For negative thresholds
            } else {
                return $value >= $threshold; // For positive thresholds
            }
        }
        return false;
    }
    
    /**
     * Derive implications from market conditions
     */
    private function derive_implications($conditions, $data) {
        $implications = array();
        
        foreach ($conditions as $condition) {
            switch ($condition) {
                case 'bull_market':
                    $implications[] = 'Risk assets likely to outperform';
                    $implications[] = 'Consider growth-oriented strategies';
                    $implications[] = 'IPO and M&A activity likely to increase';
                    break;
                    
                case 'bear_market':
                    $implications[] = 'Defensive positioning recommended';
                    $implications[] = 'Quality and value likely to outperform';
                    $implications[] = 'Deal activity likely to slow';
                    break;
                    
                case 'correction':
                    $implications[] = 'Selective opportunities emerging';
                    $implications[] = 'Volatility creating entry points';
                    $implications[] = 'Focus on fundamentals';
                    break;
            }
        }
        
        // Add rate-specific implications
        if (isset($data['interest_rate_trend'])) {
            if ($data['interest_rate_trend'] === 'rising') {
                $implications = array_merge($implications, $this->get_rate_implications('rising'));
            } elseif ($data['interest_rate_trend'] === 'falling') {
                $implications = array_merge($implications, $this->get_rate_implications('falling'));
            }
        }
        
        return $implications;
    }
    
    /**
     * Get interest rate implications
     */
    private function get_rate_implications($direction) {
        if ($direction === 'rising') {
            return array(
                'Valuation pressure on growth stocks',
                'Refinancing costs increasing for leveraged companies',
                'Banks likely to benefit from wider margins'
            );
        } else {
            return array(
                'Supportive for equity valuations',
                'Favorable refinancing environment',
                'Dividend stocks more attractive'
            );
        }
    }
    
    /**
     * Analyze sector relationships
     */
    public function analyze_sector_relationships($sector, $market_data) {
        if (!isset($this->sector_correlations[$sector])) {
            return array(
                'error' => 'Sector not found in correlation matrix'
            );
        }
        
        $correlations = $this->sector_correlations[$sector];
        $analysis = array(
            'sector' => $sector,
            'positive_factors' => array(),
            'negative_factors' => array(),
            'overall_outlook' => 'neutral'
        );
        
        // Analyze positive correlations
        foreach ($correlations['positive_with'] as $factor => $correlation) {
            $factor_status = $this->get_factor_status($factor, $market_data);
            
            if ($factor_status !== null) {
                $impact = $factor_status * $correlation;
                $analysis['positive_factors'][] = array(
                    'factor' => $factor,
                    'correlation' => $correlation,
                    'current_impact' => $impact
                );
            }
        }
        
        // Analyze negative correlations
        foreach ($correlations['negative_with'] as $factor => $correlation) {
            $factor_status = $this->get_factor_status($factor, $market_data);
            
            if ($factor_status !== null) {
                $impact = $factor_status * $correlation;
                $analysis['negative_factors'][] = array(
                    'factor' => $factor,
                    'correlation' => $correlation,
                    'current_impact' => $impact
                );
            }
        }
        
        // Calculate overall outlook
        $total_impact = 0;
        foreach ($analysis['positive_factors'] as $factor) {
            $total_impact += $factor['current_impact'];
        }
        foreach ($analysis['negative_factors'] as $factor) {
            $total_impact += $factor['current_impact'];
        }
        
        if ($total_impact > 0.3) {
            $analysis['overall_outlook'] = 'positive';
        } elseif ($total_impact < -0.3) {
            $analysis['overall_outlook'] = 'negative';
        }
        
        return $analysis;
    }
    
    /**
     * Get current status of a market factor
     */
    private function get_factor_status($factor, $market_data) {
        // Normalize factor status to -1 to 1 scale
        $factor_map = array(
            'nasdaq' => 'nasdaq_performance',
            'oil_prices' => 'wti_crude_change',
            'interest_rates' => 'ten_year_yield_change',
            'inflation_expectations' => 'tips_breakeven'
        );
        
        $data_field = isset($factor_map[$factor]) ? $factor_map[$factor] : $factor;
        
        if (isset($market_data[$data_field])) {
            // Convert percentage changes to normalized scale
            $value = $market_data[$data_field];
            
            if (is_numeric($value)) {
                // Normalize to -1 to 1 range (assuming ±10% as extremes)
                return max(-1, min(1, $value / 10));
            }
        }
        
        return null;
    }
    
    /**
     * Apply reasoning to enhance response
     */
    public function apply_reasoning($query_context, $market_data) {
        $reasoning = array(
            'market_condition' => $this->analyze_market_condition($market_data),
            'applicable_rules' => array(),
            'confidence_level' => 0
        );
        
        // Find applicable rules based on query context
        foreach ($this->market_rules as $rule_name => $rule) {
            if ($this->rule_applies($rule, $query_context, $market_data)) {
                $reasoning['applicable_rules'][] = array(
                    'rule' => $rule_name,
                    'implications' => $rule['then'],
                    'confidence' => $this->calculate_rule_confidence($rule, $market_data)
                );
            }
        }
        
        // Calculate overall confidence
        if (!empty($reasoning['applicable_rules'])) {
            $confidences = array_column($reasoning['applicable_rules'], 'confidence');
            $reasoning['confidence_level'] = array_sum($confidences) / count($confidences);
        }
        
        return $reasoning;
    }
    
    /**
     * Check if a rule applies to current context
     */
    private function rule_applies($rule, $context, $data) {
        // Simple condition matching for now
        $condition = $rule['if'];
        
        // Check various condition types
        if ($condition === 'interest_rates_increase' && isset($data['rate_change']) && $data['rate_change'] > 0) {
            return true;
        }
        if ($condition === 'vix_above_25' && isset($data['vix_level']) && $data['vix_level'] > 25) {
            return true;
        }
        if ($condition === 'gdp_growth_strong' && isset($data['gdp_growth']) && $data['gdp_growth'] > 2.5) {
            return true;
        }
        if ($condition === 'inflation_rising' && isset($data['cpi_change']) && $data['cpi_change'] > 2) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Calculate confidence for a rule based on data quality
     */
    private function calculate_rule_confidence($rule, $data) {
        // Base confidence starts at 0.5
        $confidence = 0.5;
        
        // Increase confidence based on data freshness
        if (isset($data['timestamp'])) {
            $age_minutes = (time() - strtotime($data['timestamp'])) / 60;
            
            if ($age_minutes < 15) {
                $confidence += 0.3; // Very fresh data
            } elseif ($age_minutes < 60) {
                $confidence += 0.2; // Recent data
            } elseif ($age_minutes < 240) {
                $confidence += 0.1; // Acceptable data
            }
        }
        
        // Increase confidence based on multiple data sources
        if (isset($data['source_count']) && $data['source_count'] > 1) {
            $confidence += min(0.2, $data['source_count'] * 0.05);
        }
        
        return min(1.0, $confidence);
    }
}