<?php
/**
 * Deal Intelligence Processor
 * 
 * Processes PE deal data to extract entities, values, and insights
 * Structures data for chart generation and template enhancement
 * 
 * @package SennaFinanceCareer
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Deal_Intelligence_Processor {
    
    private static $instance = null;
    
    /**
     * Known PE firms for better entity extraction
     */
    private $pe_firms = array(
        'Blackstone', 'KKR', 'Carlyle', 'Apollo', 'TPG', 'Bain Capital', 'CVC Capital',
        'Advent International', 'Warburg Pincus', 'Silver Lake', 'General Atlantic',
        'Vista Equity Partners', 'Thoma Bravo', 'Leonard Green', 'Fortress Investment',
        'Brookfield', 'Lone Star', 'Cerberus', 'Oaktree Capital', 'Ares Management',
        'EQT Partners', 'Permira', 'Cinven', 'BC Partners', 'PAI Partners',
        'Apax Partners', 'Clayton Dubilier', 'Goldman Sachs', 'Morgan Stanley',
        'Bridgepoint', 'Hellman & Friedman', 'Francisco Partners', 'GI Partners'
    );
    
    /**
     * Sector mappings for better categorization
     */
    private $sectors = array(
        'technology' => array('tech', 'software', 'saas', 'ai', 'fintech', 'digital', 'cyber'),
        'healthcare' => array('healthcare', 'pharma', 'biotech', 'medical', 'health'),
        'financial services' => array('financial', 'banking', 'insurance', 'fintech', 'payments'),
        'consumer' => array('consumer', 'retail', 'brand', 'food', 'beverage', 'restaurant'),
        'industrial' => array('industrial', 'manufacturing', 'logistics', 'supply chain'),
        'real estate' => array('real estate', 'property', 'reit', 'housing', 'commercial'),
        'energy' => array('energy', 'oil', 'gas', 'renewable', 'solar', 'wind', 'power'),
        'infrastructure' => array('infrastructure', 'utilities', 'transport', 'telecom')
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
     * Process raw deals into structured intelligence
     */
    public function process_deals($raw_deals) {
        $processed_deals = array();
        
        foreach ($raw_deals as $deal) {
            $intelligence = $this->extract_deal_intelligence($deal);
            if ($intelligence) {
                $processed_deals[] = $intelligence;
            }
        }
        
        return $processed_deals;
    }
    
    /**
     * Extract intelligence from a single deal
     */
    public function extract_deal_intelligence($deal) {
        $title = $deal['title'];
        $category = $deal['category'];
        
        // Extract entities
        $entities = array(
            'firms' => $this->extract_pe_firms($title),
            'companies' => $this->extract_companies($title),
            'values' => $this->extract_values($title),
            'sectors' => $this->extract_sectors($title),
            'regions' => $this->extract_regions($title),
            'people' => $this->extract_people($title)
        );
        
        // Calculate metrics
        $metrics = $this->calculate_metrics($entities, $category);
        
        // Enhance with additional context
        $context = $this->build_context($title, $entities, $category);
        
        return array(
            'original' => $deal,
            'entities' => $entities,
            'metrics' => $metrics,
            'context' => $context,
            'processed_date' => time(),
            'intelligence_score' => $this->calculate_intelligence_score($entities)
        );
    }
    
    /**
     * Extract PE firm names from title
     */
    private function extract_pe_firms($title) {
        $found_firms = array();
        
        foreach ($this->pe_firms as $firm) {
            if (stripos($title, $firm) !== false) {
                $found_firms[] = $firm;
            }
        }
        
        return array_unique($found_firms);
    }
    
    /**
     * Extract company names (excluding PE firms)
     */
    private function extract_companies($title) {
        $companies = array();
        
        // Look for capitalized words that might be company names
        preg_match_all('/\b([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*)\b/', $title, $matches);
        
        foreach ($matches[1] as $potential_company) {
            // Skip if it's a known PE firm
            if (!in_array($potential_company, $this->pe_firms)) {
                // Skip common words
                $skip_words = array('The', 'And', 'For', 'Inc', 'Corp', 'Company', 'Group', 'Capital', 'Partners', 'Management');
                if (!in_array($potential_company, $skip_words)) {
                    $companies[] = $potential_company;
                }
            }
        }
        
        return array_unique($companies);
    }
    
    /**
     * Extract monetary values
     */
    private function extract_values($title) {
        $values = array();
        
        // Pattern for billions/millions with currency symbols
        preg_match_all('/(?:\$|USD|EUR|GBP|£|€)?\s*(\d+(?:\.\d+)?)\s*(billion|million|bn|mn|m|b)(?:\s|$)/i', $title, $matches);
        
        for ($i = 0; $i < count($matches[1]); $i++) {
            $number = floatval($matches[1][$i]);
            $unit = strtolower($matches[2][$i]);
            
            $multiplier = 1;
            if (in_array($unit, array('billion', 'bn', 'b'))) {
                $multiplier = 1000000000;
            } elseif (in_array($unit, array('million', 'mn', 'm'))) {
                $multiplier = 1000000;
            }
            
            $values[] = array(
                'raw' => $matches[0][$i],
                'number' => $number,
                'unit' => $unit,
                'value_usd' => $number * $multiplier,
                'formatted' => $this->format_value($number * $multiplier)
            );
        }
        
        return $values;
    }
    
    /**
     * Extract sector information
     */
    private function extract_sectors($title) {
        $found_sectors = array();
        $title_lower = strtolower($title);
        
        foreach ($this->sectors as $sector => $keywords) {
            foreach ($keywords as $keyword) {
                if (stripos($title_lower, $keyword) !== false) {
                    $found_sectors[] = $sector;
                    break;
                }
            }
        }
        
        return array_unique($found_sectors);
    }
    
    /**
     * Extract regional information
     */
    private function extract_regions($title) {
        $regions = array();
        $region_patterns = array(
            'North America' => array('US', 'USA', 'United States', 'America', 'North America', 'Canada'),
            'Europe' => array('Europe', 'European', 'UK', 'United Kingdom', 'Germany', 'France', 'Italy', 'Spain'),
            'Asia' => array('Asia', 'Asian', 'China', 'Japan', 'India', 'Singapore', 'Hong Kong', 'Korea'),
            'Global' => array('Global', 'International', 'Worldwide'),
            'Latin America' => array('Latin America', 'Brazil', 'Mexico', 'Argentina'),
            'private equity' => array('private equity', 'UAE', 'Saudi Arabia', 'Israel'),
            'Africa' => array('Africa', 'South Africa', 'Nigeria')
        );
        
        foreach ($region_patterns as $region => $keywords) {
            foreach ($keywords as $keyword) {
                if (stripos($title, $keyword) !== false) {
                    $regions[] = $region;
                    break;
                }
            }
        }
        
        return array_unique($regions);
    }
    
    /**
     * Extract people names
     */
    private function extract_people($title) {
        $people = array();
        
        // Look for patterns like "John Smith" (two capitalized words)
        preg_match_all('/\b([A-Z][a-z]+\s+[A-Z][a-z]+)\b/', $title, $matches);
        
        foreach ($matches[1] as $potential_name) {
            // Filter out company-like names
            $skip_patterns = array('Private Equity', 'Capital Partners', 'Investment Group');
            $is_person = true;
            
            foreach ($skip_patterns as $pattern) {
                if (stripos($potential_name, $pattern) !== false) {
                    $is_person = false;
                    break;
                }
            }
            
            if ($is_person) {
                $people[] = $potential_name;
            }
        }
        
        return array_unique($people);
    }
    
    /**
     * Calculate deal metrics
     */
    private function calculate_metrics($entities, $category) {
        $metrics = array(
            'deal_size_category' => 'unknown',
            'firm_tier' => 'unknown',
            'complexity_score' => 0,
            'market_significance' => 'low'
        );
        
        // Analyze deal size
        if (!empty($entities['values'])) {
            $largest_value = max(array_column($entities['values'], 'value_usd'));
            
            if ($largest_value >= 10000000000) { // $10bn+
                $metrics['deal_size_category'] = 'mega';
                $metrics['market_significance'] = 'high';
            } elseif ($largest_value >= 1000000000) { // $1bn+
                $metrics['deal_size_category'] = 'large';
                $metrics['market_significance'] = 'medium';
            } elseif ($largest_value >= 100000000) { // $100m+
                $metrics['deal_size_category'] = 'mid';
                $metrics['market_significance'] = 'medium';
            } else {
                $metrics['deal_size_category'] = 'small';
                $metrics['market_significance'] = 'low';
            }
        }
        
        // Analyze firm tier
        if (!empty($entities['firms'])) {
            $tier1_firms = array('Blackstone', 'KKR', 'Carlyle', 'Apollo', 'TPG');
            foreach ($entities['firms'] as $firm) {
                if (in_array($firm, $tier1_firms)) {
                    $metrics['firm_tier'] = 'tier1';
                    break;
                }
            }
            if ($metrics['firm_tier'] === 'unknown') {
                $metrics['firm_tier'] = 'tier2';
            }
        }
        
        // Calculate complexity score
        $complexity_factors = array(
            'multiple_firms' => count($entities['firms']) > 1 ? 20 : 0,
            'cross_border' => count($entities['regions']) > 1 ? 15 : 0,
            'large_value' => !empty($entities['values']) && max(array_column($entities['values'], 'value_usd')) >= 1000000000 ? 25 : 0,
            'multiple_sectors' => count($entities['sectors']) > 1 ? 10 : 0,
            'people_involved' => count($entities['people']) > 0 ? 5 : 0
        );
        
        $metrics['complexity_score'] = array_sum($complexity_factors);
        
        return $metrics;
    }
    
    /**
     * Build contextual information
     */
    private function build_context($title, $entities, $category) {
        $context = array(
            'headline_quality' => $this->assess_headline_quality($title),
            'data_richness' => $this->assess_data_richness($entities),
            'market_relevance' => $this->assess_market_relevance($entities, $category),
            'template_suitability' => $this->assess_template_suitability($entities, $category)
        );
        
        return $context;
    }
    
    /**
     * Calculate overall intelligence score
     */
    private function calculate_intelligence_score($entities) {
        $score = 0;
        
        // Points for different types of entities
        $score += count($entities['firms']) * 15;
        $score += count($entities['companies']) * 10;
        $score += count($entities['values']) * 20;
        $score += count($entities['sectors']) * 8;
        $score += count($entities['regions']) * 5;
        $score += count($entities['people']) * 3;
        
        return min($score, 100); // Cap at 100
    }
    
    /**
     * Assess headline quality
     */
    private function assess_headline_quality($title) {
        $quality_factors = array(
            'length' => strlen($title) > 20 && strlen($title) < 200 ? 20 : 0,
            'specificity' => preg_match('/\d/', $title) ? 15 : 0,
            'clarity' => substr_count($title, ' ') >= 3 ? 10 : 0
        );
        
        return array_sum($quality_factors);
    }
    
    /**
     * Assess data richness
     */
    private function assess_data_richness($entities) {
        $richness = 0;
        
        foreach ($entities as $type => $items) {
            $richness += count($items) * 10;
        }
        
        return min($richness, 100);
    }
    
    /**
     * Assess market relevance
     */
    private function assess_market_relevance($entities, $category) {
        $relevance = 50; // Base relevance
        
        // Boost for tier 1 firms
        $tier1_firms = array('Blackstone', 'KKR', 'Carlyle', 'Apollo', 'TPG');
        foreach ($entities['firms'] as $firm) {
            if (in_array($firm, $tier1_firms)) {
                $relevance += 20;
                break;
            }
        }
        
        // Boost for large values
        if (!empty($entities['values'])) {
            $max_value = max(array_column($entities['values'], 'value_usd'));
            if ($max_value >= 1000000000) {
                $relevance += 15;
            }
        }
        
        return min($relevance, 100);
    }
    
    /**
     * Assess template suitability
     */
    private function assess_template_suitability($entities, $category) {
        $suitability = array(
            'fundraise' => 0,
            'acquisition' => 0,
            'exit' => 0,
            'esg' => 0,
            'regulation' => 0,
            'people_moves' => 0
        );
        
        // Base suitability for detected category
        $suitability[$category] = 40;
        
        // Enhance based on available data
        if (!empty($entities['values'])) {
            $suitability['fundraise'] += 20;
            $suitability['acquisition'] += 15;
            $suitability['exit'] += 15;
        }
        
        if (!empty($entities['firms'])) {
            foreach (array_keys($suitability) as $template_type) {
                $suitability[$template_type] += 10;
            }
        }
        
        if (!empty($entities['people'])) {
            $suitability['people_moves'] += 25;
        }
        
        return $suitability;
    }
    
    /**
     * Format monetary value
     */
    private function format_value($value) {
        if ($value >= 1000000000) {
            return '$' . round($value / 1000000000, 1) . 'bn';
        } elseif ($value >= 1000000) {
            return '$' . round($value / 1000000, 1) . 'm';
        } else {
            return '$' . number_format($value);
        }
    }
    
    /**
     * Get market intelligence summary for a category
     */
    public function get_market_intelligence($category, $deals_limit = 50) {
        $pe_insights = SFFC_PE_Insights_Fetcher::get_instance();
        $raw_deals = $pe_insights->get_deals_by_category($category, $deals_limit);
        $processed_deals = $this->process_deals($raw_deals);
        
        return array(
            'total_deals' => count($processed_deals),
            'high_value_deals' => $this->filter_high_value_deals($processed_deals),
            'tier1_firm_deals' => $this->filter_tier1_deals($processed_deals),
            'sector_breakdown' => $this->analyze_sector_breakdown($processed_deals),
            'regional_distribution' => $this->analyze_regional_distribution($processed_deals),
            'value_distribution' => $this->analyze_value_distribution($processed_deals),
            'recent_trends' => $this->identify_recent_trends($processed_deals)
        );
    }
    
    /**
     * Filter high-value deals
     */
    private function filter_high_value_deals($deals) {
        return array_filter($deals, function($deal) {
            if (empty($deal['entities']['values'])) return false;
            $max_value = max(array_column($deal['entities']['values'], 'value_usd'));
            return $max_value >= 1000000000; // $1bn+
        });
    }
    
    /**
     * Filter tier 1 firm deals
     */
    private function filter_tier1_deals($deals) {
        $tier1_firms = array('Blackstone', 'KKR', 'Carlyle', 'Apollo', 'TPG');
        
        return array_filter($deals, function($deal) use ($tier1_firms) {
            foreach ($deal['entities']['firms'] as $firm) {
                if (in_array($firm, $tier1_firms)) {
                    return true;
                }
            }
            return false;
        });
    }
    
    /**
     * Analyze sector breakdown
     */
    private function analyze_sector_breakdown($deals) {
        $sectors = array();
        
        foreach ($deals as $deal) {
            foreach ($deal['entities']['sectors'] as $sector) {
                $sectors[$sector] = ($sectors[$sector] ?? 0) + 1;
            }
        }
        
        arsort($sectors);
        return $sectors;
    }
    
    /**
     * Analyze regional distribution
     */
    private function analyze_regional_distribution($deals) {
        $regions = array();
        
        foreach ($deals as $deal) {
            foreach ($deal['entities']['regions'] as $region) {
                $regions[$region] = ($regions[$region] ?? 0) + 1;
            }
        }
        
        arsort($regions);
        return $regions;
    }
    
    /**
     * Analyze value distribution
     */
    private function analyze_value_distribution($deals) {
        $distribution = array(
            'mega' => 0,    // $10bn+
            'large' => 0,   // $1-10bn
            'mid' => 0,     // $100m-1bn
            'small' => 0    // <$100m
        );
        
        foreach ($deals as $deal) {
            $category = $deal['metrics']['deal_size_category'] ?? 'unknown';
            if (isset($distribution[$category])) {
                $distribution[$category]++;
            }
        }
        
        return $distribution;
    }
    
    /**
     * Identify recent trends
     */
    private function identify_recent_trends($deals) {
        // Group deals by month
        $monthly_activity = array();
        
        foreach ($deals as $deal) {
            $month = date('Y-m', strtotime($deal['original']['date']));
            $monthly_activity[$month] = ($monthly_activity[$month] ?? 0) + 1;
        }
        
        ksort($monthly_activity);
        
        return array(
            'monthly_activity' => $monthly_activity,
            'momentum' => $this->calculate_momentum($monthly_activity),
            'dominant_firms' => $this->get_dominant_firms($deals),
            'hot_sectors' => $this->get_hot_sectors($deals)
        );
    }
    
    /**
     * Calculate market momentum
     */
    private function calculate_momentum($monthly_activity) {
        $months = array_keys($monthly_activity);
        if (count($months) < 2) return 'stable';
        
        $recent = array_slice($monthly_activity, -3, 3, true);
        $earlier = array_slice($monthly_activity, -6, 3, true);
        
        $recent_avg = array_sum($recent) / count($recent);
        $earlier_avg = array_sum($earlier) / count($earlier);
        
        $change = ($recent_avg - $earlier_avg) / $earlier_avg * 100;
        
        if ($change > 20) return 'accelerating';
        if ($change < -20) return 'decelerating';
        return 'stable';
    }
    
    /**
     * Get dominant firms
     */
    private function get_dominant_firms($deals) {
        $firm_activity = array();
        
        foreach ($deals as $deal) {
            foreach ($deal['entities']['firms'] as $firm) {
                $firm_activity[$firm] = ($firm_activity[$firm] ?? 0) + 1;
            }
        }
        
        arsort($firm_activity);
        return array_slice($firm_activity, 0, 5, true);
    }
    
    /**
     * Get hot sectors
     */
    private function get_hot_sectors($deals) {
        $sector_activity = $this->analyze_sector_breakdown($deals);
        return array_slice($sector_activity, 0, 3, true);
    }

    // =========================================================================
    // PHASE 1: FINANCIAL MODELING ENHANCEMENTS
    // =========================================================================

    /**
     * Extract comprehensive financial metrics from article content
     *
     * @param string $content Full article content
     * @param string $title Article title
     * @return array Structured financial data
     */
    public function extract_financial_metrics($content, $title = '') {
        $full_text = $title . ' ' . $content;

        $financials = array(
            'deal_value' => $this->extract_deal_value($full_text),
            'funding_amount' => $this->extract_funding_amount($full_text),
            'valuation' => $this->extract_valuation($full_text),
            'net_proceeds' => $this->extract_net_proceeds($full_text),
            'target_financials' => $this->extract_target_financials($full_text),
            'multiples' => $this->extract_multiples($full_text),
            'deal_structure' => $this->extract_deal_structure($full_text),
            'buyer_info' => $this->extract_buyer_info($full_text),
            'seller_info' => $this->extract_seller_info($full_text),
            'market_reaction' => $this->extract_market_reaction($full_text),
            'extraction_timestamp' => current_time('mysql')
        );

        // For fundraising articles, use funding amount as deal value if deal_value is empty
        if (empty($financials['deal_value']['amount']) && !empty($financials['funding_amount']['amount'])) {
            $financials['deal_value'] = $financials['funding_amount'];
            $financials['deal_value']['source'] = 'DISCLOSED_FUNDING';
        }

        // Calculate implied multiples where possible
        $financials['calculated_multiples'] = $this->calculate_implied_multiples($financials);

        // Assess data quality
        $financials['data_quality'] = $this->assess_financial_data_quality($financials);

        return $financials;
    }

    /**
     * Currency pattern for regex - matches symbols and codes
     */
    private function get_currency_pattern() {
        // Symbols: $, £, €, ¥, ₹, ₩, ₺, ₽
        // Codes: USD, GBP, EUR, AED, SAR, JPY, CNY, CHF, CAD, AUD, SGD, HKD, INR, KRW, BRL, SEK, NOK, DKK, PLN, TRY, ZAR, RUB
        return '(?:[\$£€¥₹₩₺₽]|USD|GBP|EUR|AED|SAR|JPY|CNY|RMB|CHF|CAD|AUD|SGD|HKD|INR|KRW|BRL|SEK|NOK|DKK|PLN|TRY|ZAR|RUB)';
    }

    /**
     * Extract funding amount (for fundraising/investment rounds)
     * e.g., "raised $300 million", "secured AED 500M funding"
     */
    private function extract_funding_amount($text) {
        $result = array(
            'amount' => null,
            'currency' => 'USD',
            'formatted' => null,
            'source' => 'NOT_FOUND',
            'round_type' => null
        );

        $curr = $this->get_currency_pattern();

        $patterns = array(
            // "raised $300 million" / "has raised AED 300M"
            '/(?:has\s+)?raised\s+(?:over\s+|approximately\s+|about\s+|around\s+|nearly\s+)?(?P<currency>' . $curr . ')\s*(?P<amount>[\d,]+(?:\.\d+)?)\s*(?P<unit>billion|million|bn|mn|m|b)/iu',
            // "raises $300 million" / "is raising €300M"
            '/(?:is\s+)?rais(?:es|ing)\s+(?:over\s+|approximately\s+|about\s+|around\s+|nearly\s+)?(?P<currency>' . $curr . ')\s*(?P<amount>[\d,]+(?:\.\d+)?)\s*(?P<unit>billion|million|bn|mn|m|b)/iu',
            // "$300 million funding round" / "AED 300M round"
            '/(?P<currency>' . $curr . ')\s*(?P<amount>[\d,]+(?:\.\d+)?)\s*(?P<unit>billion|million|bn|mn|m|b)\s+(?:funding\s+)?(?:round|investment|financing)/iu',
            // "secured $300 million" / "closed ¥300M"
            '/(?:secured|closed|completed|announced)\s+(?:a\s+)?(?:over\s+|approximately\s+)?(?P<currency>' . $curr . ')\s*(?P<amount>[\d,]+(?:\.\d+)?)\s*(?P<unit>billion|million|bn|mn|m|b)/iu',
            // "Series X of $300 million" / "Series C funding of €300M"
            '/series\s+[a-z]\d?\s+(?:funding\s+)?(?:of\s+|round\s+(?:of\s+)?)?(?:over\s+|approximately\s+)?(?P<currency>' . $curr . ')\s*(?P<amount>[\d,]+(?:\.\d+)?)\s*(?P<unit>billion|million|bn|mn|m|b)/iu',
            // "investment of $300 million" / "funding of AED 300M"
            '/(?:investment|funding|financing)\s+(?:of\s+|totaling\s+)?(?:over\s+|approximately\s+)?(?P<currency>' . $curr . ')\s*(?P<amount>[\d,]+(?:\.\d+)?)\s*(?P<unit>billion|million|bn|mn|m|b)/iu',
            // Text currency before amount: "AED 300 million" / "EUR 500M"
            '/(?P<currency>AED|SAR|USD|GBP|EUR|JPY|CNY|CHF|CAD|AUD|SGD|INR)\s+(?P<amount>[\d,]+(?:\.\d+)?)\s*(?P<unit>billion|million|bn|mn|m|b)/iu',
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $amount = floatval(str_replace(',', '', $matches['amount']));
                $unit = strtolower($matches['unit'] ?? '');
                $currency_symbol = $matches['currency'] ?? '$';

                $multiplier = $this->get_unit_multiplier($unit);
                $base_amount = $amount * $multiplier;
                $currency = $this->parse_currency($currency_symbol);

                $result = array(
                    'amount' => $base_amount,
                    'currency' => $currency,
                    'formatted' => $this->format_currency_value($base_amount, $currency),
                    'source' => 'DISCLOSED',
                    'round_type' => $this->extract_round_type($text)
                );
                break;
            }
        }

        return $result;
    }

    /**
     * Extract company valuation
     * e.g., "valued at $3 billion", "valuation of over AED 3B"
     */
    private function extract_valuation($text) {
        $result = array(
            'amount' => null,
            'currency' => 'USD',
            'formatted' => null,
            'source' => 'NOT_FOUND',
            'valuation_type' => null // pre-money, post-money, etc.
        );

        $curr = $this->get_currency_pattern();

        $patterns = array(
            // "values the company at over $3 billion"
            '/valu(?:es|ed|ing)\s+(?:the\s+)?(?:company|startup|business|it)\s+(?:at\s+)?(?:over\s+|approximately\s+|about\s+|around\s+|nearly\s+|more\s+than\s+)?(?P<currency>' . $curr . ')\s*(?P<amount>[\d,]+(?:\.\d+)?)\s*(?P<unit>billion|million|bn|mn|m|b)/iu',
            // "valuation of over $3 billion" / "valuation at AED 3B"
            '/valuation\s+(?:of\s+|at\s+)?(?:over\s+|approximately\s+|about\s+|around\s+|nearly\s+|more\s+than\s+)?(?P<currency>' . $curr . ')\s*(?P<amount>[\d,]+(?:\.\d+)?)\s*(?P<unit>billion|million|bn|mn|m|b)/iu',
            // "valued at over $3 billion" (standalone)
            '/valued\s+(?:at\s+)?(?:over\s+|approximately\s+|about\s+|around\s+|nearly\s+|more\s+than\s+)?(?P<currency>' . $curr . ')\s*(?P<amount>[\d,]+(?:\.\d+)?)\s*(?P<unit>billion|million|bn|mn|m|b)/iu',
            // "worth over $3 billion" / "now worth €3B"
            '/(?:now\s+)?worth\s+(?:over\s+|approximately\s+|about\s+|around\s+|nearly\s+|more\s+than\s+)?(?P<currency>' . $curr . ')\s*(?P<amount>[\d,]+(?:\.\d+)?)\s*(?P<unit>billion|million|bn|mn|m|b)/iu',
            // "$3 billion valuation" / "AED 3 billion valuation"
            '/(?P<currency>' . $curr . ')\s*(?P<amount>[\d,]+(?:\.\d+)?)\s*(?P<unit>billion|million|bn|mn|m|b)\s+valuation/iu',
            // Text currency: "AED 3 billion valuation" / "valued at EUR 500 million"
            '/(?P<currency>AED|SAR|USD|GBP|EUR|JPY|CNY|CHF|CAD|AUD|SGD|INR)\s+(?P<amount>[\d,]+(?:\.\d+)?)\s*(?P<unit>billion|million|bn|mn|m|b)/iu',
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $amount = floatval(str_replace(',', '', $matches['amount']));
                $unit = strtolower($matches['unit'] ?? '');
                $currency_symbol = $matches['currency'] ?? '$';

                $multiplier = $this->get_unit_multiplier($unit);
                $base_amount = $amount * $multiplier;
                $currency = $this->parse_currency($currency_symbol);

                // Determine valuation type
                $valuation_type = 'post-money'; // Default for funding rounds
                if (stripos($text, 'pre-money') !== false) {
                    $valuation_type = 'pre-money';
                }

                $result = array(
                    'amount' => $base_amount,
                    'currency' => $currency,
                    'formatted' => $this->format_currency_value($base_amount, $currency),
                    'source' => 'DISCLOSED',
                    'valuation_type' => $valuation_type
                );
                break;
            }
        }

        return $result;
    }

    /**
     * Extract funding round type (Series A, B, C, etc.)
     */
    private function extract_round_type($text) {
        if (preg_match('/series\s+([a-z]\d?)/i', $text, $matches)) {
            return 'Series ' . strtoupper($matches[1]);
        }
        if (stripos($text, 'seed') !== false) {
            return 'Seed';
        }
        if (stripos($text, 'pre-seed') !== false) {
            return 'Pre-Seed';
        }
        if (preg_match('/(?:growth|expansion)\s+(?:round|funding)/i', $text)) {
            return 'Growth';
        }
        return null;
    }

    /**
     * Extract primary deal value
     */
    private function extract_deal_value($text) {
        $result = array(
            'amount' => null,
            'currency' => 'USD',
            'formatted' => null,
            'source' => 'NOT_FOUND'
        );

        $curr = $this->get_currency_pattern();

        // Pattern for deal values with context words (use /u flag for Unicode)
        $patterns = array(
            // "values the company at over $3 billion" / "valued at approximately AED 3 billion"
            '/valu(?:ing|ed?|es)\s+(?:the\s+)?(?:business|company|deal|it|startup)?\s*(?:at\s+)?(?:over\s+|approximately\s+|about\s+|around\s+|nearly\s+)?(?P<currency>' . $curr . ')\s*(?P<amount>[\d,]+(?:\.\d+)?)\s*(?P<unit>billion|million|bn|mn|m|b)/iu',
            // "valuation of $3 billion" / "valuation at over €3 billion"
            '/valuation\s+(?:of\s+|at\s+)?(?:over\s+|approximately\s+|about\s+|around\s+|nearly\s+)?(?P<currency>' . $curr . ')\s*(?P<amount>[\d,]+(?:\.\d+)?)\s*(?P<unit>billion|million|bn|mn|m|b)/iu',
            // "now valued at over $3B" / "now worth ¥3B"
            '/(?:now\s+)?(?:valued|worth)\s+(?:at\s+)?(?:over\s+|approximately\s+|about\s+|around\s+|nearly\s+)?(?P<currency>' . $curr . ')\s*(?P<amount>[\d,]+(?:\.\d+)?)\s*(?P<unit>billion|million|bn|mn|m|b)/iu',
            // "in £2bn deal" / "AED 2bn deal"
            '/(?:in\s+(?:a\s+)?)?(?P<currency>' . $curr . ')\s*(?P<amount>[\d,]+(?:\.\d+)?)\s*(?P<unit>billion|million|bn|mn|m|b)\s+(?:deal|acquisition|transaction|buyout|sale|funding|round)/iu',
            // "deal worth £2bn" / "transaction valued at €2bn"
            '/(?:deal|transaction|acquisition|investment)\s+(?:worth|valued\s+at)\s*(?:over\s+|approximately\s+)?(?P<currency>' . $curr . ')\s*(?P<amount>[\d,]+(?:\.\d+)?)\s*(?P<unit>billion|million|bn|mn|m|b)/iu',
            // "for £2bn" / "agreed to sell... for AED 2bn"
            '/(?:for|at)\s+(?:over\s+|approximately\s+)?(?P<currency>' . $curr . ')\s*(?P<amount>[\d,]+(?:\.\d+)?)\s*(?P<unit>billion|million|bn|mn|m|b)/iu',
            // Generic: "$2bn" or "AED 2bn" with currency symbols/codes
            '/(?P<currency>' . $curr . ')\s*(?P<amount>[\d,]+(?:\.\d+)?)\s*(?P<unit>billion|billion|bn|b)(?:\s|,|\.)/iu',
            // Text currency before amount: "AED 2 billion deal" / "EUR 500 million acquisition"
            '/(?P<currency>AED|SAR|USD|GBP|EUR|JPY|CNY|CHF|CAD|AUD|SGD|INR)\s+(?P<amount>[\d,]+(?:\.\d+)?)\s*(?P<unit>billion|million|bn|mn|m|b)/iu',
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $amount = floatval(str_replace(',', '', $matches['amount']));
                $unit = strtolower($matches['unit'] ?? '');
                $currency_symbol = $matches['currency'] ?? '$';

                // Convert to base amount
                $multiplier = $this->get_unit_multiplier($unit);
                $base_amount = $amount * $multiplier;

                // Determine currency
                $currency = $this->parse_currency($currency_symbol);

                $result = array(
                    'amount' => $base_amount,
                    'currency' => $currency,
                    'formatted' => $this->format_currency_value($base_amount, $currency),
                    'source' => 'DISCLOSED'
                );
                break;
            }
        }

        return $result;
    }

    /**
     * Extract net proceeds (what seller receives after fees/debt)
     */
    private function extract_net_proceeds($text) {
        $result = array(
            'amount' => null,
            'currency' => 'USD',
            'formatted' => null,
            'source' => 'NOT_FOUND'
        );

        // Pattern for net proceeds (use /u for Unicode)
        $patterns = array(
            // "net proceeds of about £1.85bn"
            '/net\s+proceeds?\s+(?:of\s+)?(?:about\s+|approximately\s+)?(?P<currency>[\$£€])\s*(?P<amount>[\d,]+(?:\.\d+)?)\s*(?P<unit>billion|million|bn|mn|m|b)/iu',
            // "expects net proceeds of about £1.85bn"
            '/expects?\s+(?:net\s+)?proceeds?\s+(?:of\s+)?(?:about\s+|approximately\s+)?(?P<currency>[\$£€])\s*(?P<amount>[\d,]+(?:\.\d+)?)\s*(?P<unit>billion|million|bn|mn|m|b)/iu',
            // "proceeds of £1.85bn"
            '/proceeds?\s+(?:of\s+)?(?:about\s+|approximately\s+)?(?P<currency>[\$£€])\s*(?P<amount>[\d,]+(?:\.\d+)?)\s*(?P<unit>billion|million|bn|mn|m|b)/iu',
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $amount = floatval(str_replace(',', '', $matches['amount']));
                $unit = strtolower($matches['unit'] ?? '');
                $currency_symbol = $matches['currency'] ?? '$';

                $multiplier = $this->get_unit_multiplier($unit);
                $base_amount = $amount * $multiplier;
                $currency = $this->parse_currency($currency_symbol);

                $result = array(
                    'amount' => $base_amount,
                    'currency' => $currency,
                    'formatted' => $this->format_currency_value($base_amount, $currency),
                    'source' => 'DISCLOSED'
                );
                break;
            }
        }

        return $result;
    }

    /**
     * Extract target company financial metrics
     */
    private function extract_target_financials($text) {
        $financials = array(
            'revenue' => array('amount' => null, 'currency' => 'USD', 'period' => null, 'source' => 'NOT_FOUND'),
            'ebitda' => array('amount' => null, 'currency' => 'USD', 'period' => null, 'source' => 'NOT_FOUND'),
            'operating_profit' => array('amount' => null, 'currency' => 'USD', 'period' => null, 'source' => 'NOT_FOUND'),
            'net_income' => array('amount' => null, 'currency' => 'USD', 'period' => null, 'source' => 'NOT_FOUND'),
            'gross_profit' => array('amount' => null, 'currency' => 'USD', 'period' => null, 'source' => 'NOT_FOUND'),
        );

        // Revenue patterns (use /u for Unicode)
        $revenue_patterns = array(
            '/revenue[s]?\s+(?:of\s+)?(?P<currency>[\$£€])?\s*(?P<amount>[\d,]+(?:\.\d+)?)\s*(?P<unit>billion|million|bn|mn|m|b)/iu',
            '/(?P<currency>[\$£€])?\s*(?P<amount>[\d,]+(?:\.\d+)?)\s*(?P<unit>billion|million|bn|mn|m|b)\s+(?:in\s+)?(?:annual\s+)?revenue/iu',
            '/sales\s+(?:of\s+)?(?P<currency>[\$£€])?\s*(?P<amount>[\d,]+(?:\.\d+)?)\s*(?P<unit>billion|million|bn|mn|m|b)/iu',
        );

        foreach ($revenue_patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $financials['revenue'] = $this->parse_financial_match($matches);
                break;
            }
        }

        // EBITDA patterns (use /u for Unicode)
        $ebitda_patterns = array(
            '/ebitda\s+(?:of\s+)?(?P<currency>[\$£€])?\s*(?P<amount>[\d,]+(?:\.\d+)?)\s*(?P<unit>billion|million|bn|mn|m|b)?/iu',
            '/(?P<currency>[\$£€])?\s*(?P<amount>[\d,]+(?:\.\d+)?)\s*(?P<unit>billion|million|bn|mn|m|b)?\s+(?:of\s+)?ebitda/iu',
        );

        foreach ($ebitda_patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $financials['ebitda'] = $this->parse_financial_match($matches);
                break;
            }
        }

        // Operating profit patterns (use /u for Unicode)
        $op_patterns = array(
            // "operating profit of £122m"
            '/(?:headline\s+)?operating\s+profit\s+(?:of\s+)?(?P<currency>[\$£€])\s*(?P<amount>[\d,]+(?:\.\d+)?)\s*(?P<unit>billion|million|bn|mn|m|b)?/iu',
            // "£122m operating profit" or "£122m headline operating profit"
            '/(?P<currency>[\$£€])\s*(?P<amount>[\d,]+(?:\.\d+)?)\s*(?P<unit>billion|million|bn|mn|m|b)?\s+(?:headline\s+)?operating\s+profit/iu',
            // "on operating profit of £122m"
            '/on\s+(?:\w+\s+)?(?:headline\s+)?operating\s+profit\s+of\s+(?P<currency>[\$£€])\s*(?P<amount>[\d,]+(?:\.\d+)?)\s*(?P<unit>billion|million|bn|mn|m|b)?/iu',
        );

        foreach ($op_patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $financials['operating_profit'] = $this->parse_financial_match($matches);
                break;
            }
        }

        // Net income patterns (use /u for Unicode)
        $ni_patterns = array(
            '/net\s+(?:income|profit|earnings)\s+(?:of\s+)?(?P<currency>[\$£€])?\s*(?P<amount>[\d,]+(?:\.\d+)?)\s*(?P<unit>billion|million|bn|mn|m|b)?/iu',
            '/(?:earnings|profit)\s+(?:of\s+)?(?P<currency>[\$£€])?\s*(?P<amount>[\d,]+(?:\.\d+)?)\s*(?P<unit>billion|million|bn|mn|m|b)?/iu',
        );

        foreach ($ni_patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $financials['net_income'] = $this->parse_financial_match($matches);
                break;
            }
        }

        // Extract fiscal period if mentioned
        if (preg_match('/(?:year\s+(?:ended?|to|ending)\s+)?(?P<month>January|February|March|April|May|June|July|August|September|October|November|December)\s*(?P<year>\d{4})?/i', $text, $period_match)) {
            $period = $period_match['month'] . (isset($period_match['year']) ? ' ' . $period_match['year'] : '');
            foreach ($financials as $key => &$metric) {
                if ($metric['source'] === 'DISCLOSED') {
                    $metric['period'] = $period;
                }
            }
        }

        return $financials;
    }

    /**
     * Extract disclosed valuation multiples
     */
    private function extract_multiples($text) {
        $multiples = array(
            'ev_revenue' => array('value' => null, 'source' => 'NOT_FOUND'),
            'ev_ebitda' => array('value' => null, 'source' => 'NOT_FOUND'),
            'ev_operating_profit' => array('value' => null, 'source' => 'NOT_FOUND'),
            'pe_ratio' => array('value' => null, 'source' => 'NOT_FOUND'),
            'price_to_book' => array('value' => null, 'source' => 'NOT_FOUND'),
        );

        // EV/Revenue or Revenue multiple patterns
        if (preg_match('/(?P<value>[\d.]+)\s*[x×]\s+(?:of\s+)?(?:the\s+)?(?:annual\s+)?(?:revenue|sales)/i', $text, $matches) ||
            preg_match('/revenue\s+multiple\s+(?:of\s+)?(?P<value>[\d.]+)\s*[x×]?/i', $text, $matches)) {
            $multiples['ev_revenue'] = array(
                'value' => floatval($matches['value']),
                'source' => 'DISCLOSED'
            );
        }

        // EV/EBITDA patterns
        if (preg_match('/(?P<value>[\d.]+)\s*[x×]\s+(?:of\s+)?(?:the\s+)?ebitda/i', $text, $matches) ||
            preg_match('/ebitda\s+multiple\s+(?:of\s+)?(?P<value>[\d.]+)\s*[x×]?/i', $text, $matches)) {
            $multiples['ev_ebitda'] = array(
                'value' => floatval($matches['value']),
                'source' => 'DISCLOSED'
            );
        }

        // EV/Operating Profit patterns (common in UK deals)
        // "16.3x on Smiths Detection headline operating profit" - allow company name in between
        if (preg_match('/(?P<value>[\d.]+)\s*x\s+on\s+(?:[\w\s]+?\s+)?(?:headline\s+)?operating\s+profit/iu', $text, $matches) ||
            preg_match('/multiple\s+(?:of\s+)?(?P<value>[\d.]+)\s*[x×]?\s+(?:on\s+)?(?:[\w\s]+?\s+)?(?:headline\s+)?operating\s+profit/iu', $text, $matches) ||
            preg_match('/(?P<value>[\d.]+)\s*[x×]\s+(?:operating\s+profit|op(?:erating)?\s*profit)/iu', $text, $matches)) {
            $multiples['ev_operating_profit'] = array(
                'value' => floatval($matches['value']),
                'source' => 'DISCLOSED'
            );
        }

        // P/E ratio patterns
        if (preg_match('/(?:p\/e|pe|price[\s-](?:to[\s-])?earnings)\s+(?:ratio\s+)?(?:of\s+)?(?P<value>[\d.]+)\s*[x×]?/i', $text, $matches)) {
            $multiples['pe_ratio'] = array(
                'value' => floatval($matches['value']),
                'source' => 'DISCLOSED'
            );
        }

        return $multiples;
    }

    /**
     * Extract deal structure information
     */
    private function extract_deal_structure($text) {
        $structure = array(
            'deal_type' => $this->classify_deal_type($text),
            'payment_type' => $this->extract_payment_type($text),
            'financing' => $this->extract_financing_info($text),
            'conditions' => $this->extract_deal_conditions($text),
        );

        return $structure;
    }

    /**
     * Classify the type of deal
     */
    private function classify_deal_type($text) {
        $text_lower = strtolower($text);

        $deal_types = array(
            'carve_out' => array('carve-out', 'carve out', 'divest', 'divestiture', 'spin-off', 'spin off', 'offload'),
            'lbo' => array('leveraged buyout', 'lbo', 'management buyout', 'mbo'),
            'acquisition' => array('acquire', 'acquisition', 'takeover', 'take over', 'buy'),
            'merger' => array('merger', 'merge', 'combination'),
            'growth_equity' => array('growth equity', 'growth investment', 'minority stake', 'minority investment'),
            'secondary' => array('secondary', 'sponsor-to-sponsor', 'continuation'),
            'ipo' => array('ipo', 'initial public offering', 'public listing', 'float'),
            'recap' => array('recapitalization', 'recap', 'dividend recap'),
            'pipe' => array('pipe', 'private investment in public equity'),
        );

        foreach ($deal_types as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($text_lower, $keyword) !== false) {
                    return array(
                        'type' => $type,
                        'label' => ucwords(str_replace('_', ' ', $type)),
                        'source' => 'INFERRED'
                    );
                }
            }
        }

        return array('type' => 'acquisition', 'label' => 'Acquisition', 'source' => 'DEFAULT');
    }

    /**
     * Extract payment structure
     */
    private function extract_payment_type($text) {
        $text_lower = strtolower($text);

        $payment_info = array(
            'type' => 'unknown',
            'cash_component' => null,
            'stock_component' => null,
            'earnout' => null,
            'source' => 'NOT_FOUND'
        );

        if (strpos($text_lower, 'all-cash') !== false || strpos($text_lower, 'all cash') !== false) {
            $payment_info['type'] = 'all_cash';
            $payment_info['source'] = 'DISCLOSED';
        } elseif (strpos($text_lower, 'cash and stock') !== false || strpos($text_lower, 'cash and shares') !== false) {
            $payment_info['type'] = 'cash_and_stock';
            $payment_info['source'] = 'DISCLOSED';
        } elseif (strpos($text_lower, 'all-stock') !== false || strpos($text_lower, 'all stock') !== false) {
            $payment_info['type'] = 'all_stock';
            $payment_info['source'] = 'DISCLOSED';
        }

        // Check for earnout
        if (preg_match('/earnout|earn-out|contingent\s+(?:payment|consideration)/i', $text)) {
            $payment_info['earnout'] = true;
        }

        return $payment_info;
    }

    /**
     * Extract financing information
     */
    private function extract_financing_info($text) {
        $financing = array(
            'debt_financing' => false,
            'equity_commitment' => null,
            'lenders' => array(),
            'source' => 'NOT_FOUND'
        );

        if (preg_match('/debt\s+financ/i', $text)) {
            $financing['debt_financing'] = true;
            $financing['source'] = 'DISCLOSED';
        }

        // Look for equity commitment
        if (preg_match('/equity\s+(?:commitment|contribution)\s+(?:of\s+)?(?P<currency>[\$£€])?\s*(?P<amount>[\d,]+(?:\.\d+)?)\s*(?P<unit>billion|million|bn|mn|m|b)?/i', $text, $matches)) {
            $financing['equity_commitment'] = $this->parse_financial_match($matches);
        }

        return $financing;
    }

    /**
     * Extract deal conditions
     */
    private function extract_deal_conditions($text) {
        $conditions = array(
            'regulatory_approval' => false,
            'shareholder_approval' => false,
            'antitrust' => false,
            'expected_close' => null,
        );

        $text_lower = strtolower($text);

        if (strpos($text_lower, 'regulatory approval') !== false || strpos($text_lower, 'regulator') !== false) {
            $conditions['regulatory_approval'] = true;
        }

        if (strpos($text_lower, 'shareholder') !== false && strpos($text_lower, 'approv') !== false) {
            $conditions['shareholder_approval'] = true;
        }

        if (strpos($text_lower, 'antitrust') !== false || strpos($text_lower, 'competition') !== false) {
            $conditions['antitrust'] = true;
        }

        // Expected close date
        if (preg_match('/(?:expected?\s+(?:to\s+)?close|completion\s+(?:expected?|anticipated))\s+(?:in\s+)?(?P<timing>Q[1-4]\s+\d{4}|(?:first|second|third|fourth)\s+(?:quarter|half)|(?:early|mid|late)\s+\d{4}|\d{4})/i', $text, $matches)) {
            $conditions['expected_close'] = $matches['timing'];
        }

        return $conditions;
    }

    /**
     * Extract buyer information
     */
    private function extract_buyer_info($text) {
        $buyer = array(
            'name' => null,
            'type' => 'unknown',
            'is_consortium' => false,
            'members' => array(),
        );

        // Check for PE firms
        foreach ($this->pe_firms as $firm) {
            if (stripos($text, $firm) !== false) {
                $buyer['name'] = $firm;
                $buyer['type'] = 'private_equity';
                break;
            }
        }

        // Check for consortium
        if (preg_match('/consortium|group\s+(?:of\s+)?(?:investors|buyers)|alongside|together\s+with/i', $text)) {
            $buyer['is_consortium'] = true;
            // Try to find all PE firms mentioned
            foreach ($this->pe_firms as $firm) {
                if (stripos($text, $firm) !== false) {
                    $buyer['members'][] = $firm;
                }
            }
        }

        // Check if strategic buyer
        if (preg_match('/(?:strategic\s+)?(?:buyer|acquirer|bidder)/i', $text) && $buyer['type'] === 'unknown') {
            $buyer['type'] = 'strategic';
        }

        return $buyer;
    }

    /**
     * Extract seller information
     */
    private function extract_seller_info($text) {
        $seller = array(
            'name' => null,
            'ticker' => null,
            'market_cap' => null,
            'stock_impact' => null,
            'ytd_performance' => null,
        );

        // Extract ticker (patterns like "FTSE-listed", "NYSE:", etc.)
        if (preg_match('/(?P<exchange>FTSE|NYSE|NASDAQ|LSE)[\s:-]+(?:listed)?/i', $text, $matches)) {
            $seller['ticker'] = strtoupper($matches['exchange']) . '-listed';
        }

        // Extract market cap - "market value above £8bn" or "market cap of £8bn" (use /u for Unicode)
        if (preg_match('/market\s+(?:cap|capitalisation|capitalization|value)\s+(?:of\s+)?(?:about\s+|approximately\s+|above\s+|over\s+)?(?P<currency>[\$£€])\s*(?P<amount>[\d,]+(?:\.\d+)?)\s*(?P<unit>billion|million|bn|mn|m|b)/iu', $text, $matches)) {
            $seller['market_cap'] = $this->parse_financial_match($matches);
        }

        // Extract stock movement
        if (preg_match('/(?:shares?|stock)\s+(?:rose|fell|gained|dropped|up|down)\s+(?:as\s+much\s+as\s+)?(?P<pct>[\d.]+)\s*%/i', $text, $matches)) {
            $seller['stock_impact'] = floatval($matches['pct']);
        }

        // Extract YTD performance
        if (preg_match('/(?P<pct>[\d.]+)\s*%\s+(?:this\s+year|year[\s-]to[\s-]date|ytd)/i', $text, $matches)) {
            $seller['ytd_performance'] = floatval($matches['pct']);
        }

        return $seller;
    }

    /**
     * Extract market reaction to deal
     */
    private function extract_market_reaction($text) {
        $reaction = array(
            'stock_move_day' => null,
            'stock_move_ytd' => null,
            'analyst_sentiment' => null,
        );

        // Day stock move
        if (preg_match('/(?:shares?|stock)\s+(?:rose|gained|up|jumped|rallied|surged)\s+(?:as\s+much\s+as\s+)?(?P<pct>[\d.]+)\s*%/i', $text, $matches)) {
            $reaction['stock_move_day'] = '+' . floatval($matches['pct']) . '%';
        } elseif (preg_match('/(?:shares?|stock)\s+(?:fell|dropped|down|slid|declined)\s+(?:as\s+much\s+as\s+)?(?P<pct>[\d.]+)\s*%/i', $text, $matches)) {
            $reaction['stock_move_day'] = '-' . floatval($matches['pct']) . '%';
        }

        return $reaction;
    }

    /**
     * Calculate implied multiples from extracted data
     */
    public function calculate_implied_multiples($financials) {
        $calculated = array(
            'ev_revenue' => array('value' => null, 'source' => 'NOT_CALCULABLE'),
            'ev_ebitda' => array('value' => null, 'source' => 'NOT_CALCULABLE'),
            'ev_operating_profit' => array('value' => null, 'source' => 'NOT_CALCULABLE'),
        );

        $deal_value = $financials['deal_value']['amount'] ?? null;

        if (!$deal_value) {
            return $calculated;
        }

        // Calculate EV/Revenue if we have revenue
        $revenue = $financials['target_financials']['revenue']['amount'] ?? null;
        if ($revenue && $revenue > 0) {
            $calculated['ev_revenue'] = array(
                'value' => round($deal_value / $revenue, 2),
                'source' => 'CALCULATED'
            );
        }

        // Calculate EV/EBITDA if we have EBITDA
        $ebitda = $financials['target_financials']['ebitda']['amount'] ?? null;
        if ($ebitda && $ebitda > 0) {
            $calculated['ev_ebitda'] = array(
                'value' => round($deal_value / $ebitda, 2),
                'source' => 'CALCULATED'
            );
        }

        // Calculate EV/Operating Profit if we have it
        $op_profit = $financials['target_financials']['operating_profit']['amount'] ?? null;
        if ($op_profit && $op_profit > 0) {
            $calculated['ev_operating_profit'] = array(
                'value' => round($deal_value / $op_profit, 2),
                'source' => 'CALCULATED'
            );
        }

        // Merge with disclosed multiples (prefer disclosed over calculated)
        foreach ($financials['multiples'] as $key => $multiple) {
            if ($multiple['source'] === 'DISCLOSED' && isset($calculated[$key])) {
                $calculated[$key] = $multiple;
            }
        }

        return $calculated;
    }

    /**
     * Assess the quality of extracted financial data
     */
    public function assess_financial_data_quality($financials) {
        $quality = array(
            'disclosed_count' => 0,
            'calculated_count' => 0,
            'estimated_count' => 0,
            'missing_count' => 0,
            'confidence_score' => 0,
            'data_completeness' => 'poor',
        );

        // Count data sources
        $this->count_data_sources($financials, $quality);

        // Calculate confidence score (0-100)
        $total_fields = $quality['disclosed_count'] + $quality['calculated_count'] +
                        $quality['estimated_count'] + $quality['missing_count'];

        if ($total_fields > 0) {
            // Disclosed = 100%, Calculated = 70%, Estimated = 40%, Missing = 0%
            $weighted_score = (
                ($quality['disclosed_count'] * 100) +
                ($quality['calculated_count'] * 70) +
                ($quality['estimated_count'] * 40)
            ) / $total_fields;

            $quality['confidence_score'] = round($weighted_score);
        }

        // Determine completeness level
        if ($quality['confidence_score'] >= 70) {
            $quality['data_completeness'] = 'high';
        } elseif ($quality['confidence_score'] >= 50) {
            $quality['data_completeness'] = 'medium';
        } elseif ($quality['confidence_score'] >= 30) {
            $quality['data_completeness'] = 'low';
        } else {
            $quality['data_completeness'] = 'poor';
        }

        return $quality;
    }

    /**
     * Recursively count data sources in financials array
     */
    private function count_data_sources($data, &$counts) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if ($key === 'source') {
                    switch ($value) {
                        case 'DISCLOSED':
                            $counts['disclosed_count']++;
                            break;
                        case 'CALCULATED':
                            $counts['calculated_count']++;
                            break;
                        case 'ESTIMATED':
                            $counts['estimated_count']++;
                            break;
                        case 'NOT_FOUND':
                        case 'NOT_CALCULABLE':
                            $counts['missing_count']++;
                            break;
                    }
                } elseif (is_array($value)) {
                    $this->count_data_sources($value, $counts);
                }
            }
        }
    }

    /**
     * Helper: Parse financial match from regex
     */
    private function parse_financial_match($matches) {
        $amount = floatval(str_replace(',', '', $matches['amount'] ?? 0));
        $unit = strtolower($matches['unit'] ?? 'm');
        $currency_symbol = $matches['currency'] ?? '$';

        $multiplier = $this->get_unit_multiplier($unit);
        $base_amount = $amount * $multiplier;
        $currency = $this->parse_currency($currency_symbol);

        return array(
            'amount' => $base_amount,
            'currency' => $currency,
            'formatted' => $this->format_currency_value($base_amount, $currency),
            'source' => 'DISCLOSED',
            'period' => null
        );
    }

    /**
     * Helper: Get multiplier for unit
     */
    private function get_unit_multiplier($unit) {
        $unit = strtolower($unit);
        if (in_array($unit, array('billion', 'bn', 'b'))) {
            return 1000000000;
        } elseif (in_array($unit, array('million', 'mn', 'm'))) {
            return 1000000;
        } elseif (in_array($unit, array('thousand', 'k'))) {
            return 1000;
        }
        return 1;
    }

    /**
     * Helper: Parse currency symbol to code
     */
    private function parse_currency($symbol) {
        $symbol = strtoupper(trim($symbol));

        // Map symbols and codes to standard ISO currency codes
        $currency_map = array(
            // US Dollar
            '$' => 'USD', 'USD' => 'USD', 'US$' => 'USD',
            // British Pound
            '£' => 'GBP', 'GBP' => 'GBP',
            // Euro
            '€' => 'EUR', 'EUR' => 'EUR',
            // Japanese Yen
            '¥' => 'JPY', 'JPY' => 'JPY', 'YEN' => 'JPY',
            // Chinese Yuan
            'CNY' => 'CNY', 'RMB' => 'CNY', 'CN¥' => 'CNY', '元' => 'CNY',
            // UAE Dirham
            'AED' => 'AED', 'DH' => 'AED', 'DHS' => 'AED',
            // Saudi Riyal
            'SAR' => 'SAR', 'SR' => 'SAR',
            // Indian Rupee
            '₹' => 'INR', 'INR' => 'INR', 'RS' => 'INR',
            // Swiss Franc
            'CHF' => 'CHF', 'SFR' => 'CHF',
            // Canadian Dollar
            'CAD' => 'CAD', 'C$' => 'CAD', 'CA$' => 'CAD',
            // Australian Dollar
            'AUD' => 'AUD', 'A$' => 'AUD', 'AU$' => 'AUD',
            // Singapore Dollar
            'SGD' => 'SGD', 'S$' => 'SGD', 'SG$' => 'SGD',
            // Hong Kong Dollar
            'HKD' => 'HKD', 'HK$' => 'HKD',
            // Korean Won
            '₩' => 'KRW', 'KRW' => 'KRW',
            // Brazilian Real
            'BRL' => 'BRL', 'R$' => 'BRL',
            // Swedish Krona
            'SEK' => 'SEK', 'KR' => 'SEK',
            // Norwegian Krone
            'NOK' => 'NOK',
            // Danish Krone
            'DKK' => 'DKK',
            // Polish Zloty
            'PLN' => 'PLN', 'ZŁ' => 'PLN',
            // Turkish Lira
            '₺' => 'TRY', 'TRY' => 'TRY', 'TL' => 'TRY',
            // South African Rand
            'ZAR' => 'ZAR',
            // Russian Ruble
            '₽' => 'RUB', 'RUB' => 'RUB',
        );

        return $currency_map[$symbol] ?? 'USD';
    }

    /**
     * Helper: Format currency value
     */
    private function format_currency_value($amount, $currency = 'USD') {
        $symbols = array(
            'USD' => '$',
            'GBP' => '£',
            'EUR' => '€',
            'JPY' => '¥',
            'CNY' => '¥',
            'AED' => 'AED ',
            'SAR' => 'SAR ',
            'INR' => '₹',
            'CHF' => 'CHF ',
            'CAD' => 'C$',
            'AUD' => 'A$',
            'SGD' => 'S$',
            'HKD' => 'HK$',
            'KRW' => '₩',
            'BRL' => 'R$',
            'SEK' => 'SEK ',
            'NOK' => 'NOK ',
            'DKK' => 'DKK ',
            'PLN' => 'zł',
            'TRY' => '₺',
            'ZAR' => 'R',
            'RUB' => '₽',
        );
        $symbol = $symbols[$currency] ?? $currency . ' ';

        if ($amount >= 1000000000) {
            return $symbol . round($amount / 1000000000, 2) . 'bn';
        } elseif ($amount >= 1000000) {
            return $symbol . round($amount / 1000000, 1) . 'm';
        } else {
            return $symbol . number_format($amount);
        }
    }

    /**
     * Build complete deal financials meta for storage
     *
     * @param string $content Article content
     * @param string $title Article title
     * @param bool   $include_ai_enhancement Whether to call Claude for AI enhancement
     * @return array Complete financials meta structure
     */
    public function build_deal_financials_meta($content, $title = '', $include_ai_enhancement = false) {
        // Extract all financial data
        $financials = $this->extract_financial_metrics($content, $title);

        // Build standardized meta structure
        $meta = array(
            'version' => '1.1',
            'extracted_at' => current_time('mysql'),

            'deal_value' => $financials['deal_value'],
            'funding_amount' => $financials['funding_amount'] ?? null,
            'valuation' => $financials['valuation'] ?? null,
            'net_proceeds' => $financials['net_proceeds'],

            'target_financials' => $financials['target_financials'],

            'multiples' => array(
                'disclosed' => $financials['multiples'],
                'calculated' => $financials['calculated_multiples'],
            ),

            'deal_structure' => $financials['deal_structure'],

            'parties' => array(
                'buyer' => $financials['buyer_info'],
                'seller' => $financials['seller_info'],
                'target' => array(
                    'name' => $this->extract_company_name($title, $content),
                ),
            ),

            'market_reaction' => $financials['market_reaction'],

            'data_quality' => $financials['data_quality'],

            // Placeholder for Claude-enhanced data
            'ai_enhanced' => array(
                'scenarios' => null,
                'comparables' => null,
                'risks' => null,
                'analyst_commentary' => null,
                'estimated_financials' => null,
                'enhanced_at' => null,
                'source' => null,
            ),
        );

        // Auto-enable AI enhancement if we have some disclosed data but need more context
        // This ensures Claude researches missing data for articles with partial information
        $has_disclosed_data = !empty($meta['deal_value']['amount']) ||
                              !empty($meta['funding_amount']['amount']) ||
                              !empty($meta['valuation']['amount']);

        $needs_enhancement = $has_disclosed_data && (
            empty($meta['target_financials']['revenue']['amount']) ||
            empty($meta['ai_enhanced']['scenarios'])
        );

        if ($include_ai_enhancement || $needs_enhancement) {
            $meta = $this->enhance_with_ai($meta, $content, $title);
        }

        return $meta;
    }

    /**
     * Extract company name from title/content
     */
    private function extract_company_name($title, $content) {
        // Try to find company name patterns
        $patterns = array(
            // "Company Name raises/raised"
            '/([A-Z][A-Za-z0-9\s]+(?:Labs?|Inc\.?|Corp\.?|Ltd\.?|LLC)?)\s+(?:has\s+)?(?:raised|raises|secures|closes)/i',
            // "Company Name, a startup"
            '/([A-Z][A-Za-z0-9\s]+(?:Labs?|Inc\.?|Corp\.?|Ltd\.?|LLC)?),?\s+(?:a|an|the)\s+(?:startup|company)/i',
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $title . ' ' . $content, $matches)) {
                return trim($matches[1]);
            }
        }

        return null;
    }

    /**
     * Enhance deal financials with Claude AI analysis
     *
     * @param array  $meta    Existing deal financials meta
     * @param string $content Article content
     * @param string $title   Article title
     * @return array Enhanced meta with AI analysis
     */
    public function enhance_with_ai($meta, $content, $title) {
        // Check if Claude API Manager is available
        if (!class_exists('SFFC_Claude_API_Manager')) {
            return $meta;
        }

        try {
            $claude = SFFC_Claude_API_Manager::get_instance();

            // Check if API is available
            if (!$claude->is_available()) {
                return $meta;
            }

            // Call Claude for deal analysis
            $analysis = $claude->analyze_deal($title, $content, $meta);

            if (!empty($analysis) && ($analysis['success'] ?? false)) {
                // Merge AI analysis into meta
                $meta['ai_enhanced'] = array(
                    'scenarios' => $analysis['scenarios'] ?? null,
                    'comparables' => $analysis['comparables'] ?? null,
                    'risks' => $analysis['risks'] ?? null,
                    'analyst_commentary' => $analysis['analyst_commentary'] ?? null,
                    'estimated_financials' => $analysis['estimated_financials'] ?? null,
                    'enhanced_at' => current_time('mysql'),
                    'source' => $analysis['source'] ?? 'unknown',
                );

                // Update data quality to reflect AI enhancement
                $meta['data_quality']['ai_enhanced'] = true;
                $meta['data_quality']['enhancement_source'] = $analysis['source'] ?? 'unknown';

                // If Claude estimated financials we didn't have, add them
                if (!empty($analysis['estimated_financials'])) {
                    $this->merge_estimated_financials($meta, $analysis['estimated_financials']);
                }
            }

        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('SFFC AI Enhancement error: ' . $e->getMessage());
            }
        }

        return $meta;
    }

    /**
     * Merge Claude's estimated financials into the meta structure
     */
    private function merge_estimated_financials(&$meta, $estimated) {
        // Only add estimated values if we don't have disclosed values
        if (!empty($estimated['revenue']['amount']) &&
            empty($meta['target_financials']['revenue']['amount'])) {
            $meta['target_financials']['revenue'] = array(
                'amount' => floatval($estimated['revenue']['amount']),
                'currency' => $meta['deal_value']['currency'] ?? 'USD',
                'source' => 'AI_ESTIMATED',
                'methodology' => $estimated['revenue']['methodology'] ?? '',
            );
        }

        if (!empty($estimated['ebitda']['amount']) &&
            empty($meta['target_financials']['ebitda']['amount'])) {
            $meta['target_financials']['ebitda'] = array(
                'amount' => floatval($estimated['ebitda']['amount']),
                'currency' => $meta['deal_value']['currency'] ?? 'USD',
                'source' => 'AI_ESTIMATED',
                'methodology' => $estimated['ebitda']['methodology'] ?? '',
            );
        }

        if (!empty($estimated['ebitda_margin']['value']) &&
            empty($meta['target_financials']['ebitda_margin'])) {
            $meta['target_financials']['ebitda_margin'] = array(
                'value' => floatval($estimated['ebitda_margin']['value']),
                'source' => $estimated['ebitda_margin']['source'] ?? 'AI_ESTIMATED',
            );
        }
    }

    /**
     * Enhance an existing post's deal financials with AI
     *
     * @param int $post_id The post ID
     * @return bool Success status
     */
    public function enhance_post_with_ai($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return false;
        }

        // Get existing financials or extract new ones
        $meta = get_post_meta($post_id, '_sffc_deal_financials', true);

        if (empty($meta)) {
            // No existing data, build from scratch with AI
            $meta = $this->build_deal_financials_meta(
                $post->post_content,
                $post->post_title,
                true // Include AI enhancement
            );
        } else {
            // Enhance existing data
            $meta = $this->enhance_with_ai($meta, $post->post_content, $post->post_title);
        }

        // Save updated meta
        update_post_meta($post_id, '_sffc_deal_financials', $meta);

        // Clear any cached template data
        delete_transient('inst_article_data_' . $post_id);

        return true;
    }
}

// Initialize the processor
SFFC_Deal_Intelligence_Processor::get_instance();