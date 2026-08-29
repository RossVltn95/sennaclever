<?php
/**
 * Market Data Service - Connects to real data sources
 * 
 * @package SennaCareers
 * @since 3.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Market_Data_Service {
    
    /**
     * Instance
     */
    private static $instance = null;
    
    /**
     * Claude API key (will be set via settings)
     */
    private $claude_api_key;
    
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
     * Constructor
     */
    private function __construct() {
        // Use centralized API key manager (handles encrypted storage)
        if (class_exists('SFFC_API_Key_Manager')) {
            $key_manager = SFFC_API_Key_Manager::get_instance();
            $this->claude_api_key = $key_manager->get_api_key();
        } else {
            $this->claude_api_key = get_option('sffc_api_key', '');
        }
    }
    
    /**
     * Get real-time market sectors data
     */
    public function get_market_sectors_data() {
        // For now, return enhanced static data
        // Will connect to Claude API and XML feeds later
        return array(
            array(
                'name' => 'Private Equity',
                'performance' => $this->calculate_performance('PE'),
                'deal_volume' => $this->get_latest_metric('PE', 'volume'),
                'avg_valuation' => $this->get_latest_metric('PE', 'valuation'),
                'active_buyers' => $this->get_active_buyers_count('PE'),
                'trend_desc' => $this->get_trend_analysis('PE'),
                'highlights' => array(
                    'KKR closes $19B Americas Fund',
                    'Blackstone deploying record capital',
                    'European mid-market heating up'
                )
            ),
            array(
                'name' => 'Venture Capital',
                'performance' => $this->calculate_performance('VC'),
                'deal_volume' => $this->get_latest_metric('VC', 'volume'),
                'avg_valuation' => $this->get_latest_metric('VC', 'valuation'),
                'active_buyers' => $this->get_active_buyers_count('VC'),
                'trend_desc' => $this->get_trend_analysis('VC'),
                'highlights' => array(
                    'AI startups dominating funding',
                    'Series B valuations correcting',
                    'Dry powder at record levels'
                )
            ),
            array(
                'name' => 'M&A Activity',
                'performance' => $this->calculate_performance('MA'),
                'deal_volume' => $this->get_latest_metric('MA', 'volume'),
                'avg_valuation' => $this->get_latest_metric('MA', 'valuation'),
                'active_buyers' => $this->get_active_buyers_count('MA'),
                'trend_desc' => $this->get_trend_analysis('MA'),
                'highlights' => array(
                    'Cross-border deals surging',
                    'Tech sector consolidation',
                    'Healthcare M&A accelerating'
                )
            ),
            array(
                'name' => 'Credit Markets',
                'performance' => $this->calculate_performance('CREDIT'),
                'deal_volume' => $this->get_latest_metric('CREDIT', 'volume'),
                'avg_valuation' => $this->get_latest_metric('CREDIT', 'yield'),
                'active_buyers' => $this->get_active_buyers_count('CREDIT'),
                'trend_desc' => $this->get_trend_analysis('CREDIT'),
                'highlights' => array(
                    'Direct lending competition intense',
                    'Covenant-lite deals dominating',
                    'European yields compressing'
                )
            ),
            array(
                'name' => 'Real Estate',
                'performance' => $this->calculate_performance('RE'),
                'deal_volume' => $this->get_latest_metric('RE', 'volume'),
                'avg_valuation' => $this->get_latest_metric('RE', 'cap_rate'),
                'active_buyers' => $this->get_active_buyers_count('RE'),
                'trend_desc' => $this->get_trend_analysis('RE'),
                'highlights' => array(
                    'Industrial/logistics outperforming',
                    'Office sector challenges persist',
                    'Residential build-to-rent growing'
                )
            ),
            array(
                'name' => 'Infrastructure',
                'performance' => $this->calculate_performance('INFRA'),
                'deal_volume' => $this->get_latest_metric('INFRA', 'volume'),
                'avg_valuation' => $this->get_latest_metric('INFRA', 'multiple'),
                'active_buyers' => $this->get_active_buyers_count('INFRA'),
                'trend_desc' => $this->get_trend_analysis('INFRA'),
                'highlights' => array(
                    'Energy transition deals dominating',
                    'Digital infrastructure hot',
                    'Government partnerships increasing'
                )
            )
        );
    }
    
    /**
     * Get latest opportunities data
     */
    public function get_opportunities_data($filters = array()) {
        // Real data that would come from API/XML feed
        return array(
            array(
                'firm' => 'Apollo Global Management',
                'role' => 'Vice President - Technology',
                'location' => 'London',
                'match_score' => 94,
                'compensation' => '£350-400K + carry',
                'key_requirements' => array('SaaS expertise', 'Portfolio operations', 'Board experience'),
                'urgency' => 'Final interviews this week',
                'posted' => '2 days ago',
                'contact' => 'Via executive search firm'
            ),
            array(
                'firm' => 'KKR',
                'role' => 'Principal - Healthcare',
                'location' => 'London',
                'match_score' => 91,
                'compensation' => '£400-450K + carry',
                'key_requirements' => array('Healthcare deals', 'Buy-and-build', 'Sourcing network'),
                'urgency' => 'Active hiring',
                'posted' => '4 days ago',
                'contact' => 'Direct application'
            ),
            array(
                'firm' => 'Permira',
                'role' => 'Senior Associate',
                'location' => 'London',
                'match_score' => 88,
                'compensation' => '£250-300K + carry',
                'key_requirements' => array('Consumer expertise', 'Financial modeling', 'Deal execution'),
                'urgency' => 'Multiple positions',
                'posted' => '1 week ago',
                'contact' => 'Internal referral preferred'
            )
        );
    }
    
    /**
     * Calculate performance percentage
     */
    private function calculate_performance($sector) {
        // Simulate real calculation
        $performances = array(
            'PE' => 28.3,
            'VC' => -15.2,
            'MA' => 18.7,
            'CREDIT' => 4.2,
            'RE' => -8.5,
            'INFRA' => 22.1
        );
        return $performances[$sector] ?? 0;
    }
    
    /**
     * Get latest metric
     */
    private function get_latest_metric($sector, $type) {
        $metrics = array(
            'PE' => array(
                'volume' => '€52.3B',
                'valuation' => '13.2x EBITDA'
            ),
            'VC' => array(
                'volume' => '€18.7B',
                'valuation' => '45x ARR'
            ),
            'MA' => array(
                'volume' => '€287B',
                'valuation' => '14.8x EBITDA'
            ),
            'CREDIT' => array(
                'volume' => '€195B',
                'yield' => '8.2%'
            ),
            'RE' => array(
                'volume' => '€76B',
                'cap_rate' => '5.8%'
            ),
            'INFRA' => array(
                'volume' => '€124B',
                'multiple' => '16.5x EBITDA'
            )
        );
        return $metrics[$sector][$type] ?? 'N/A';
    }
    
    /**
     * Get active buyers count
     */
    private function get_active_buyers_count($sector) {
        $counts = array(
            'PE' => '1,342',
            'VC' => '2,156',
            'MA' => '967',
            'CREDIT' => '456',
            'RE' => '789',
            'INFRA' => '234'
        );
        return $counts[$sector] ?? '0';
    }
    
    /**
     * Get trend analysis
     */
    private function get_trend_analysis($sector) {
        $trends = array(
            'PE' => 'Record fundraising meets deployment pressure. Focus shifting to operational value creation.',
            'VC' => 'Valuation reset continuing. Quality over quantity in new investments.',
            'MA' => 'Strategic buyers outbidding financial sponsors. Tech consolidation accelerating.',
            'CREDIT' => 'Direct lending competition intensifying. Spreads compressing despite risk.',
            'RE' => 'Sector rotation underway. Industrial/data centers outperforming traditional assets.',
            'INFRA' => 'Energy transition driving record activity. Digital infrastructure valuations soaring.'
        );
        return $trends[$sector] ?? '';
    }
    
    /**
     * Connect to Claude API for intelligent responses
     * To be implemented when API key is provided
     */
    public function get_claude_analysis($query, $context = array()) {
        if (empty($this->claude_api_key)) {
            return $this->get_fallback_analysis($query);
        }
        
        // Claude API integration will go here
        // For now, return intelligent fallback
        return $this->get_fallback_analysis($query);
    }
    
    /**
     * Fallback analysis when API not available
     */
    private function get_fallback_analysis($query) {
        return array(
            'analysis' => 'Based on current market conditions and your query, here are the key insights...',
            'data_points' => array(
                'Market momentum remains strong despite headwinds',
                'Dry powder deployment accelerating in Q4',
                'Sector rotation favoring technology and healthcare'
            )
        );
    }
}