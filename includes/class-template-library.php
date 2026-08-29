<?php
/**
 * Template Library
 * 
 * Contains all template variations for insights and research sections
 * Each template can be pre-filled with extracted entities
 * 
 * @package SennaFinanceCareer
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Template_Library {
    
    private static $instance = null;
    
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
     * Get template - compatibility method for existing code
     */
    public function get_template($type, $template_name, $context = array()) {
        // Handle legacy calls to get_template method
        switch ($type) {
            case 'market':
                if ($template_name === 'market_update') {
                    return __('Live coverage updated across private markets with real-time intelligence.', 'senna-finance');
                }
                break;
            case 'insights':
                return $this->get_fundraise_insights($context, 'global');
            case 'research':
                return $this->get_fundraise_research($context, 'standard');
        }
        
        return __('Market intelligence template unavailable.', 'senna-finance');
    }
    
    /**
     * Get insights template for fundraising
     */
    public function get_fundraise_insights($entities, $template_variant = 'global') {
        $company = $entities['companies'][0] ?? 'Leading PE Firm';
        $value = $entities['values'][0]['formatted'] ?? '$10bn';
        $year = $entities['years'][0] ?? date('Y');
        $region = $entities['regions'][0] ?? 'Global';
        
        $templates = array(
            'global' => array(
                'title' => "Global Fundraising Momentum {$year}",
                'charts' => array(
                    'bar' => array(
                        'title' => "Fund Sizes by Vintage Year",
                        'data' => array(
                            array('label' => $company, 'value' => $this->extract_billions($value), 'highlighted' => true),
                            array('label' => 'Blackstone', 'value' => 26),
                            array('label' => 'KKR', 'value' => 19),
                            array('label' => 'Carlyle', 'value' => 22),
                            array('label' => 'Apollo', 'value' => 25),
                            array('label' => 'TPG', 'value' => 15)
                        ),
                        'suffix' => 'bn'
                    ),
                    'line' => array(
                        'title' => "Fundraising Velocity Trend",
                        'data' => array(
                            array('month' => 'Jan', 'value' => 45),
                            array('month' => 'Feb', 'value' => 52),
                            array('month' => 'Mar', 'value' => 48),
                            array('month' => 'Apr', 'value' => 61),
                            array('month' => 'May', 'value' => 58),
                            array('month' => 'Jun', 'value' => 72),
                            array('month' => 'Jul', 'value' => 68),
                            array('month' => 'Aug', 'value' => 75)
                        ),
                        'metric' => 'Months to Close'
                    ),
                    'pie' => array(
                        'title' => "LP Composition",
                        'data' => array(
                            array('label' => 'Pension Funds', 'value' => 35, 'color' => '#1e40af'),
                            array('label' => 'Sovereign Wealth', 'value' => 25, 'color' => '#3b82f6'),
                            array('label' => 'Insurance', 'value' => 20, 'color' => '#60a5fa'),
                            array('label' => 'Family Offices', 'value' => 12, 'color' => '#93bbfc'),
                            array('label' => 'Endowments', 'value' => 8, 'color' => '#cbd5e1')
                        )
                    )
                ),
                'metrics' => array(
                    array('label' => 'Target Size', 'value' => $value, 'trend' => 'up'),
                    array('label' => 'Hard Cap', 'value' => $this->calculate_hard_cap($value), 'trend' => 'neutral'),
                    array('label' => 'First Close', 'value' => $this->calculate_first_close($value), 'trend' => 'up'),
                    array('label' => 'Time to Close', 'value' => '8-12 months', 'trend' => 'down')
                ),
                'commentary' => "The fundraising environment shows strong momentum with {$company} targeting {$value}, positioning it among the top quartile of {$year} vintages. LP appetite remains robust particularly from North American pension funds and Asian sovereign wealth funds."
            ),
            'regional' => array(
                'title' => "{$region} Fund Formation Analysis",
                'charts' => array(
                    'stacked' => array(
                        'title' => "Regional Fund Raises by Strategy",
                        'data' => array(
                            array('region' => 'North America', 'buyout' => 120, 'growth' => 45, 'venture' => 65),
                            array('region' => 'Europe', 'buyout' => 85, 'growth' => 35, 'venture' => 25),
                            array('region' => 'Asia', 'buyout' => 65, 'growth' => 55, 'venture' => 45),
                            array('region' => 'Emerging', 'buyout' => 25, 'growth' => 30, 'venture' => 15)
                        ),
                        'metric' => '$bn'
                    ),
                    'heatmap' => array(
                        'title' => "Fundraising Heat Map",
                        'data' => $this->generate_fundraising_heatmap($region, $company)
                    )
                ),
                'metrics' => array(
                    array('label' => 'Regional Allocation', 'value' => $this->calculate_regional_allocation($region), 'trend' => 'up'),
                    array('label' => 'Oversubscription', 'value' => '1.4x', 'trend' => 'up'),
                    array('label' => 'Re-up Rate', 'value' => '73%', 'trend' => 'neutral'),
                    array('label' => 'New LPs', 'value' => '28%', 'trend' => 'up')
                )
            )
        );
        
        return $templates[$template_variant] ?? $templates['global'];
    }
    
    /**
     * Get research template for fundraising
     */
    public function get_fundraise_research($entities, $template_variant = 'standard') {
        $company = $entities['companies'][0] ?? 'Leading PE Firm';
        $value = $entities['values'][0]['formatted'] ?? '$10bn';
        $year = $entities['years'][0] ?? date('Y');
        
        return array(
            'executive_summary' => array(
                'title' => 'Executive Summary',
                'content' => "The {$year} fundraising cycle demonstrates robust LP demand with {$company}'s {$value} target reflecting market confidence in established platforms. Key drivers include: (1) Strong DPI performance from 2018-2020 vintages creating re-investment capacity, (2) Denominator effect normalization allowing increased PE allocations, (3) Geographic diversification strategies driving cross-border commitments."
            ),
            'key_developments' => array(
                'title' => 'Key Market Developments',
                'sections' => array(
                    array(
                        'heading' => 'Supply-Demand Dynamics',
                        'points' => array(
                            "Fundraising pace accelerated 23% YoY with mega-funds (>$5bn) capturing 68% of capital",
                            "Average fund size increased to $2.8bn, up from $2.1bn in prior vintage",
                            "Time to close compressed to 11 months median, down from 14 months historically"
                        )
                    ),
                    array(
                        'heading' => 'LP Landscape Evolution',
                        'points' => array(
                            "Asian LPs increased allocations by 35% with focus on North American and European funds",
                            "ESG-linked commitments now represent 42% of new capital, up from 28% last year",
                            "Co-investment rights negotiated in 78% of commitments above $100m"
                        )
                    ),
                    array(
                        'heading' => 'Terms & Conditions',
                        'points' => array(
                            "Management fees averaging 1.85% on committed capital during investment period",
                            "Carried interest predominantly at 20% with European waterfall gaining adoption",
                            "Key person provisions tightened with 65% requiring two named executives"
                        )
                    )
                )
            ),
            'market_context' => array(
                'title' => 'Market Context & Implications',
                'content' => "The current fundraising environment reflects a bifurcated market where established platforms with strong track records command premium terms while emerging managers face extended timelines. {$company}'s positioning benefits from: demonstrated value creation capabilities, sector expertise alignment with macro themes, and established LP relationships enabling efficient capital formation."
            ),
            'outlook' => array(
                'title' => 'Forward Outlook',
                'scenarios' => array(
                    array(
                        'scenario' => 'Base Case',
                        'probability' => '60%',
                        'description' => "Fundraising momentum continues with $450bn raised globally, slight compression in multiples"
                    ),
                    array(
                        'scenario' => 'Upside',
                        'probability' => '25%',
                        'description' => "Accelerated deployments drive faster recycling, pushing annual fundraising above $500bn"
                    ),
                    array(
                        'scenario' => 'Downside',
                        'probability' => '15%',
                        'description' => "Macro headwinds slow exits, creating denomination effect pressure on new commitments"
                    )
                )
            )
        );
    }
    
    /**
     * Get insights template for acquisitions
     */
    public function get_acquisition_insights($entities, $template_variant = 'analysis') {
        $buyer = $entities['companies'][0] ?? 'Strategic Buyer';
        $target = $entities['companies'][1] ?? 'Target Company';
        $value = $entities['values'][0]['formatted'] ?? '$500m';
        $sector = $entities['sectors'][0] ?? 'Technology';
        
        $templates = array(
            'analysis' => array(
                'title' => "M&A Transaction Analysis: {$sector}",
                'charts' => array(
                    'bar' => array(
                        'title' => "Valuation Multiples Comparison",
                        'data' => array(
                            array('label' => $target, 'value' => $this->calculate_multiple($value), 'highlighted' => true),
                            array('label' => 'Sector Median', 'value' => 14.5),
                            array('label' => 'Recent Comp 1', 'value' => 15.8),
                            array('label' => 'Recent Comp 2', 'value' => 13.2),
                            array('label' => 'Recent Comp 3', 'value' => 16.1)
                        ),
                        'suffix' => 'x EBITDA'
                    ),
                    'waterfall' => array(
                        'title' => "Value Creation Bridge",
                        'data' => array(
                            array('label' => 'Entry Valuation', 'value' => 100),
                            array('label' => 'Revenue Growth', 'value' => 35),
                            array('label' => 'Margin Expansion', 'value' => 25),
                            array('label' => 'Multiple Expansion', 'value' => 15),
                            array('label' => 'Synergies', 'value' => 20),
                            array('label' => 'Exit Valuation', 'value' => 195)
                        )
                    ),
                    'scatter' => array(
                        'title' => "Growth vs Multiple Analysis",
                        'data' => $this->generate_growth_multiple_scatter($sector, $target)
                    )
                ),
                'metrics' => array(
                    array('label' => 'Transaction Value', 'value' => $value, 'trend' => 'neutral'),
                    array('label' => 'EV/EBITDA', 'value' => $this->calculate_multiple($value) . 'x', 'trend' => 'up'),
                    array('label' => 'Revenue Growth', 'value' => '22%', 'trend' => 'up'),
                    array('label' => 'EBITDA Margin', 'value' => '28%', 'trend' => 'up')
                ),
                'commentary' => "{$buyer}'s acquisition of {$target} at {$value} represents a strategic expansion in {$sector}. The valuation implies confidence in operational improvements and revenue synergies, with integration focus on technology consolidation and market expansion."
            ),
            'sector' => array(
                'title' => "{$sector} Sector Consolidation Trends",
                'charts' => array(
                    'bubble' => array(
                        'title' => "Strategic Rationale Matrix",
                        'data' => $this->generate_strategic_rationale_bubble($sector)
                    ),
                    'timeline' => array(
                        'title' => "Integration Timeline",
                        'milestones' => array(
                            array('month' => 0, 'event' => 'Closing', 'status' => 'completed'),
                            array('month' => 3, 'event' => 'Systems Integration', 'status' => 'in_progress'),
                            array('month' => 6, 'event' => 'Commercial Integration', 'status' => 'planned'),
                            array('month' => 9, 'event' => 'Full Synergies', 'status' => 'planned'),
                            array('month' => 12, 'event' => 'Optimization Complete', 'status' => 'planned')
                        )
                    )
                )
            )
        );
        
        return $templates[$template_variant] ?? $templates['analysis'];
    }
    
    /**
     * Get insights template for ESG
     */
    public function get_esg_insights($entities, $template_variant = 'metrics') {
        $company = $entities['companies'][0] ?? 'Portfolio Company';
        $year = $entities['years'][0] ?? date('Y');
        
        $templates = array(
            'metrics' => array(
                'title' => "ESG Performance Dashboard {$year}",
                'charts' => array(
                    'radar' => array(
                        'title' => "ESG Score Breakdown",
                        'dimensions' => array(
                            array('axis' => 'Environmental', 'value' => 78),
                            array('axis' => 'Social', 'value' => 82),
                            array('axis' => 'Governance', 'value' => 91),
                            array('axis' => 'Climate', 'value' => 71),
                            array('axis' => 'Diversity', 'value' => 85),
                            array('axis' => 'Ethics', 'value' => 94)
                        )
                    ),
                    'progress' => array(
                        'title' => "Sustainability Goals Progress",
                        'goals' => array(
                            array('metric' => 'Carbon Reduction', 'target' => 100, 'current' => 67, 'unit' => '%'),
                            array('metric' => 'Renewable Energy', 'target' => 100, 'current' => 43, 'unit' => '%'),
                            array('metric' => 'Water Efficiency', 'target' => 100, 'current' => 81, 'unit' => '%'),
                            array('metric' => 'Waste Reduction', 'target' => 100, 'current' => 72, 'unit' => '%'),
                            array('metric' => 'Board Diversity', 'target' => 100, 'current' => 55, 'unit' => '%')
                        )
                    ),
                    'trend' => array(
                        'title' => "ESG Score Evolution",
                        'data' => array(
                            array('year' => '2021', 'score' => 68),
                            array('year' => '2022', 'score' => 74),
                            array('year' => '2023', 'score' => 81),
                            array('year' => '2024', 'score' => 87)
                        )
                    )
                ),
                'metrics' => array(
                    array('label' => 'ESG Score', 'value' => '87/100', 'trend' => 'up'),
                    array('label' => 'Carbon Intensity', 'value' => '-23% YoY', 'trend' => 'up'),
                    array('label' => 'SFDR Classification', 'value' => 'Article 8', 'trend' => 'neutral'),
                    array('label' => 'UN SDGs Aligned', 'value' => '6 Goals', 'trend' => 'up')
                ),
                'commentary' => "ESG integration at {$company} demonstrates measurable progress with particular strength in governance frameworks. Climate transition planning accelerated with Science-Based Targets initiative validation expected by Q2 {$year}."
            ),
            'regulation' => array(
                'title' => "Regulatory Compliance & Reporting",
                'charts' => array(
                    'compliance_matrix' => array(
                        'title' => "Regulatory Framework Compliance",
                        'frameworks' => array(
                            array('regulation' => 'EU SFDR', 'status' => 'Compliant', 'level' => 'Article 8'),
                            array('regulation' => 'TCFD', 'status' => 'Aligned', 'level' => 'Full Disclosure'),
                            array('regulation' => 'EU Taxonomy', 'status' => 'In Progress', 'level' => '62% Eligible'),
                            array('regulation' => 'SEC Climate', 'status' => 'Preparing', 'level' => 'Draft Rules'),
                            array('regulation' => 'UK SDR', 'status' => 'Monitoring', 'level' => 'Pending')
                        )
                    )
                )
            )
        );
        
        return $templates[$template_variant] ?? $templates['metrics'];
    }
    
    /**
     * Helper function to extract billions from formatted value
     */
    private function extract_billions($formatted_value) {
        preg_match('/(\d+(?:\.\d+)?)/', $formatted_value, $matches);
        $number = floatval($matches[1] ?? 10);
        
        if (stripos($formatted_value, 'bn') !== false || stripos($formatted_value, 'billion') !== false) {
            return $number;
        } elseif (stripos($formatted_value, 'm') !== false || stripos($formatted_value, 'million') !== false) {
            return $number / 1000;
        }
        
        return $number;
    }
    
    /**
     * Calculate hard cap from target
     */
    private function calculate_hard_cap($target) {
        $value = $this->extract_billions($target);
        $hard_cap = $value * 1.2;
        return '$' . round($hard_cap, 1) . 'bn';
    }
    
    /**
     * Calculate first close estimate
     */
    private function calculate_first_close($target) {
        $value = $this->extract_billions($target);
        $first_close = $value * 0.4;
        return '$' . round($first_close, 1) . 'bn';
    }
    
    /**
     * Calculate valuation multiple
     */
    private function calculate_multiple($value) {
        $billions = $this->extract_billions($value);
        // Estimate multiple based on size
        if ($billions > 5) return rand(16, 20);
        if ($billions > 1) return rand(14, 17);
        return rand(12, 15);
    }
    
    /**
     * Calculate regional allocation
     */
    private function calculate_regional_allocation($region) {
        $allocations = array(
            'North America' => '45%',
            'Europe' => '30%',
            'Asia' => '20%',
            'Global' => '100%',
            'Emerging' => '5%'
        );
        
        return $allocations[$region] ?? '25%';
    }
    
    /**
     * Generate fundraising heatmap data
     */
    private function generate_fundraising_heatmap($region, $company) {
        return array(
            array('x' => 'Q1', 'y' => 'North America', 'value' => rand(70, 95)),
            array('x' => 'Q2', 'y' => 'North America', 'value' => rand(75, 98)),
            array('x' => 'Q3', 'y' => 'North America', 'value' => rand(80, 100)),
            array('x' => 'Q4', 'y' => 'North America', 'value' => rand(85, 100)),
            array('x' => 'Q1', 'y' => 'Europe', 'value' => rand(60, 85)),
            array('x' => 'Q2', 'y' => 'Europe', 'value' => rand(65, 88)),
            array('x' => 'Q3', 'y' => 'Europe', 'value' => rand(70, 90)),
            array('x' => 'Q4', 'y' => 'Europe', 'value' => rand(72, 92)),
            array('x' => 'Q1', 'y' => 'Asia', 'value' => rand(50, 75)),
            array('x' => 'Q2', 'y' => 'Asia', 'value' => rand(55, 78)),
            array('x' => 'Q3', 'y' => 'Asia', 'value' => rand(60, 82)),
            array('x' => 'Q4', 'y' => 'Asia', 'value' => rand(65, 85))
        );
    }
    
    /**
     * Generate growth vs multiple scatter plot
     */
    private function generate_growth_multiple_scatter($sector, $target) {
        $companies = array(
            array('name' => $target, 'growth' => rand(20, 35), 'multiple' => rand(14, 20), 'size' => 100),
            array('name' => 'Comp A', 'growth' => rand(15, 30), 'multiple' => rand(12, 18), 'size' => 80),
            array('name' => 'Comp B', 'growth' => rand(10, 25), 'multiple' => rand(10, 16), 'size' => 60),
            array('name' => 'Comp C', 'growth' => rand(18, 32), 'multiple' => rand(13, 19), 'size' => 75),
            array('name' => 'Comp D', 'growth' => rand(22, 38), 'multiple' => rand(15, 22), 'size' => 90)
        );
        
        return $companies;
    }
    
    /**
     * Generate strategic rationale bubble chart
     */
    private function generate_strategic_rationale_bubble($sector) {
        return array(
            array('x' => 'Market Access', 'y' => 'High', 'size' => rand(60, 90), 'label' => 'Geographic Expansion'),
            array('x' => 'Technology', 'y' => 'High', 'size' => rand(70, 95), 'label' => 'Digital Capabilities'),
            array('x' => 'Cost Synergies', 'y' => 'Medium', 'size' => rand(50, 75), 'label' => 'Operational Efficiency'),
            array('x' => 'Revenue Synergies', 'y' => 'High', 'size' => rand(65, 85), 'label' => 'Cross-Sell'),
            array('x' => 'Talent', 'y' => 'Medium', 'size' => rand(40, 65), 'label' => 'Team Acquisition')
        );
    }
}

// Initialize the library
SFFC_Template_Library::get_instance();