<?php
/**
 * Visual Card Generator - Phase 5
 * Generates rich visual cards for financial data presentation
 * 
 * @package SennaCareers
 * @since 6.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Visual_Card_Generator {
    
    private static $instance = null;
    private $template_engine;
    private $data_integrator;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_dependencies();
    }
    
    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/patterns/class-template-engine.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/patterns/class-template-engine.php';
            $this->template_engine = SFFC_Template_Engine::get_instance();
        }
        
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/patterns/class-data-integrator.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/patterns/class-data-integrator.php';
            $this->data_integrator = SFFC_Data_Integrator::get_instance();
        }
    }
    
    /**
     * Generate visual card based on data type
     */
    public function generate_card($type, $data, $options = array()) {
        $method = 'generate_' . $type . '_card';
        
        if (method_exists($this, $method)) {
            return $this->$method($data, $options);
        }
        
        return $this->generate_default_card($data, $options);
    }
    
    /**
     * Generate market overview card
     */
    private function generate_market_overview_card($data, $options) {
        $card = array(
            'type' => 'market_overview',
            'layout' => 'grid',
            'sections' => array()
        );
        
        // Indices section
        if (!empty($data['indices'])) {
            $card['sections']['indices'] = array(
                'title' => 'Major Indices',
                'items' => $this->format_indices($data['indices'])
            );
        }
        
        // Sectors section
        if (!empty($data['sectors'])) {
            $card['sections']['sectors'] = array(
                'title' => 'Sector Performance',
                'items' => $this->format_sectors($data['sectors'])
            );
        }
        
        // Market movers
        if (!empty($data['movers'])) {
            $card['sections']['movers'] = array(
                'title' => 'Market Movers',
                'gainers' => array_slice($data['movers']['gainers'] ?? array(), 0, 3),
                'losers' => array_slice($data['movers']['losers'] ?? array(), 0, 3)
            );
        }
        
        // Add visual elements
        $card['visual_elements'] = array(
            'charts' => $this->generate_chart_config($data),
            'heatmap' => $this->generate_heatmap_config($data),
            'sparklines' => $this->generate_sparklines($data)
        );
        
        return $card;
    }
    
    /**
     * Generate company profile card
     */
    private function generate_company_profile_card($data, $options) {
        $card = array(
            'type' => 'company_profile',
            'layout' => 'detailed',
            'header' => array(
                'company' => $data['name'] ?? '',
                'ticker' => $data['ticker'] ?? '',
                'exchange' => $data['exchange'] ?? '',
                'sector' => $data['sector'] ?? '',
                'industry' => $data['industry'] ?? ''
            )
        );
        
        // Price information
        $card['price_data'] = array(
            'current' => $data['price'] ?? 0,
            'change' => $data['change'] ?? 0,
            'change_percent' => $data['change_percent'] ?? 0,
            'volume' => $data['volume'] ?? 0,
            'avg_volume' => $data['avg_volume'] ?? 0,
            'day_range' => array(
                'low' => $data['day_low'] ?? 0,
                'high' => $data['day_high'] ?? 0
            ),
            'year_range' => array(
                'low' => $data['year_low'] ?? 0,
                'high' => $data['year_high'] ?? 0
            )
        );
        
        // Key metrics
        $card['metrics'] = array(
            'market_cap' => $this->format_large_number($data['market_cap'] ?? 0),
            'pe_ratio' => $data['pe_ratio'] ?? 'N/A',
            'eps' => $data['eps'] ?? 'N/A',
            'dividend_yield' => $data['dividend_yield'] ?? 'N/A',
            'beta' => $data['beta'] ?? 'N/A'
        );
        
        // Financial highlights
        $card['financials'] = array(
            'revenue' => $this->format_large_number($data['revenue'] ?? 0),
            'net_income' => $this->format_large_number($data['net_income'] ?? 0),
            'gross_margin' => $data['gross_margin'] ?? 'N/A',
            'operating_margin' => $data['operating_margin'] ?? 'N/A'
        );
        
        // Analyst ratings
        if (!empty($data['analyst_ratings'])) {
            $card['analyst'] = array(
                'consensus' => $data['analyst_ratings']['consensus'] ?? 'N/A',
                'target_price' => $data['analyst_ratings']['target'] ?? 'N/A',
                'buy' => $data['analyst_ratings']['buy'] ?? 0,
                'hold' => $data['analyst_ratings']['hold'] ?? 0,
                'sell' => $data['analyst_ratings']['sell'] ?? 0
            );
        }
        
        // Visual elements
        $card['visual_elements'] = array(
            'price_chart' => $this->generate_price_chart($data),
            'volume_chart' => $this->generate_volume_chart($data),
            'performance_comparison' => $this->generate_performance_comparison($data)
        );
        
        return $card;
    }
    
    /**
     * Generate PE deal card
     */
    private function generate_pe_deal_card($data, $options) {
        $card = array(
            'type' => 'pe_deal',
            'layout' => 'deal_summary',
            'deal_info' => array(
                'headline' => $data['headline'] ?? '',
                'type' => $data['deal_type'] ?? '',
                'status' => $data['status'] ?? '',
                'announced_date' => $data['announced_date'] ?? '',
                'closing_date' => $data['closing_date'] ?? ''
            )
        );
        
        // Deal parties
        $card['parties'] = array(
            'buyer' => array(
                'name' => $data['buyer'] ?? '',
                'type' => $data['buyer_type'] ?? '',
                'location' => $data['buyer_location'] ?? ''
            ),
            'target' => array(
                'name' => $data['target'] ?? '',
                'sector' => $data['target_sector'] ?? '',
                'location' => $data['target_location'] ?? ''
            ),
            'seller' => array(
                'name' => $data['seller'] ?? '',
                'type' => $data['seller_type'] ?? ''
            )
        );
        
        // Financial details
        $card['financials'] = array(
            'deal_value' => $this->format_deal_value($data['deal_value'] ?? 0),
            'enterprise_value' => $this->format_deal_value($data['enterprise_value'] ?? 0),
            'revenue_multiple' => $data['revenue_multiple'] ?? 'N/A',
            'ebitda_multiple' => $data['ebitda_multiple'] ?? 'N/A'
        );
        
        // Deal rationale
        if (!empty($data['rationale'])) {
            $card['rationale'] = array(
                'strategic' => $data['rationale']['strategic'] ?? '',
                'synergies' => $data['rationale']['synergies'] ?? '',
                'growth_plan' => $data['rationale']['growth_plan'] ?? ''
            );
        }
        
        // Advisors
        if (!empty($data['advisors'])) {
            $card['advisors'] = array(
                'financial' => $data['advisors']['financial'] ?? array(),
                'legal' => $data['advisors']['legal'] ?? array(),
                'other' => $data['advisors']['other'] ?? array()
            );
        }
        
        // Visual elements
        $card['visual_elements'] = array(
            'deal_structure' => $this->generate_deal_structure_diagram($data),
            'timeline' => $this->generate_deal_timeline($data),
            'comparables' => $this->generate_comparables_chart($data)
        );
        
        return $card;
    }
    
    /**
     * Generate news card
     */
    private function generate_news_card($data, $options) {
        $card = array(
            'type' => 'news_feed',
            'layout' => 'timeline',
            'articles' => array()
        );
        
        foreach ($data as $article) {
            $card['articles'][] = array(
                'headline' => $article['headline'] ?? '',
                'summary' => $this->truncate_text($article['summary'] ?? '', 150),
                'source' => $article['source'] ?? '',
                'published' => $this->format_time_ago($article['published'] ?? ''),
                'category' => $article['category'] ?? '',
                'sentiment' => $this->analyze_sentiment($article),
                'entities' => $this->extract_entities($article),
                'importance' => $this->calculate_importance($article),
                'url' => $article['url'] ?? ''
            );
        }
        
        // Group by category
        $card['categories'] = $this->group_by_category($card['articles']);
        
        // Add trending topics
        $card['trending'] = $this->extract_trending_topics($data);
        
        // Visual elements
        $card['visual_elements'] = array(
            'sentiment_gauge' => $this->generate_sentiment_gauge($data),
            'topic_cloud' => $this->generate_topic_cloud($data),
            'timeline_chart' => $this->generate_news_timeline($data)
        );
        
        return $card;
    }
    
    /**
     * Generate economic indicators card
     */
    private function generate_economic_card($data, $options) {
        $card = array(
            'type' => 'economic_indicators',
            'layout' => 'dashboard',
            'indicators' => array()
        );
        
        // Key economic metrics
        $indicators = array(
            'gdp' => array(
                'label' => 'GDP Growth',
                'value' => $data['gdp_growth'] ?? 'N/A',
                'change' => $data['gdp_change'] ?? 0,
                'forecast' => $data['gdp_forecast'] ?? 'N/A'
            ),
            'inflation' => array(
                'label' => 'Inflation (CPI)',
                'value' => $data['cpi'] ?? 'N/A',
                'change' => $data['cpi_change'] ?? 0,
                'target' => '2.0%'
            ),
            'unemployment' => array(
                'label' => 'Unemployment Rate',
                'value' => $data['unemployment'] ?? 'N/A',
                'change' => $data['unemployment_change'] ?? 0,
                'natural_rate' => '4.0%'
            ),
            'interest_rates' => array(
                'label' => 'Fed Funds Rate',
                'value' => $data['fed_rate'] ?? 'N/A',
                'change' => $data['fed_rate_change'] ?? 0,
                'next_meeting' => $data['next_fomc'] ?? 'N/A'
            )
        );
        
        foreach ($indicators as $key => $indicator) {
            $card['indicators'][$key] = array_merge($indicator, array(
                'trend' => $this->calculate_trend($indicator),
                'status' => $this->evaluate_indicator_status($key, $indicator['value'])
            ));
        }
        
        // Fed watch
        $card['fed_watch'] = array(
            'next_decision' => $data['next_fed_decision'] ?? '',
            'probability_hike' => $data['prob_hike'] ?? 0,
            'probability_hold' => $data['prob_hold'] ?? 0,
            'probability_cut' => $data['prob_cut'] ?? 0
        );
        
        // Visual elements
        $card['visual_elements'] = array(
            'trend_charts' => $this->generate_economic_trends($data),
            'yield_curve' => $this->generate_yield_curve($data),
            'economic_calendar' => $this->generate_economic_calendar($data)
        );
        
        return $card;
    }
    
    /**
     * Generate career guidance card
     */
    private function generate_career_card($data, $options) {
        $card = array(
            'type' => 'career_guidance',
            'layout' => 'pathway',
            'profile' => array(
                'current_role' => $data['current_role'] ?? '',
                'experience_level' => $data['experience'] ?? '',
                'target_role' => $data['target_role'] ?? '',
                'industry' => $data['industry'] ?? 'Finance'
            )
        );
        
        // Career path
        $card['career_path'] = array(
            'current_position' => $data['current_position'] ?? '',
            'next_steps' => $data['next_steps'] ?? array(),
            'timeline' => $data['timeline'] ?? '',
            'milestones' => $data['milestones'] ?? array()
        );
        
        // Skills assessment
        $card['skills'] = array(
            'current' => $data['current_skills'] ?? array(),
            'required' => $data['required_skills'] ?? array(),
            'gaps' => $data['skill_gaps'] ?? array(),
            'recommendations' => $data['skill_recommendations'] ?? array()
        );
        
        // Compensation insights
        $card['compensation'] = array(
            'base_range' => $data['base_salary_range'] ?? '',
            'bonus_range' => $data['bonus_range'] ?? '',
            'total_comp' => $data['total_compensation'] ?? '',
            'percentile' => $data['comp_percentile'] ?? ''
        );
        
        // Job market
        $card['market'] = array(
            'openings' => $data['job_openings'] ?? 0,
            'demand_level' => $data['demand'] ?? 'Medium',
            'top_firms' => $data['top_firms'] ?? array(),
            'hot_skills' => $data['hot_skills'] ?? array()
        );
        
        // Visual elements
        $card['visual_elements'] = array(
            'career_roadmap' => $this->generate_career_roadmap($data),
            'skills_radar' => $this->generate_skills_radar($data),
            'comp_benchmark' => $this->generate_comp_benchmark($data)
        );
        
        return $card;
    }
    
    /**
     * Generate default card for unknown types
     */
    private function generate_default_card($data, $options) {
        return array(
            'type' => 'default',
            'layout' => 'simple',
            'content' => $data,
            'timestamp' => current_time('mysql')
        );
    }
    
    /**
     * Format indices for display
     */
    private function format_indices($indices) {
        $formatted = array();
        
        foreach ($indices as $index) {
            $formatted[] = array(
                'name' => $index['name'] ?? '',
                'value' => number_format($index['value'] ?? 0, 2),
                'change' => $this->format_change($index['change'] ?? 0),
                'change_percent' => $this->format_percent($index['change_percent'] ?? 0),
                'trend' => $this->determine_trend($index['change'] ?? 0)
            );
        }
        
        return $formatted;
    }
    
    /**
     * Format sectors for display
     */
    private function format_sectors($sectors) {
        $formatted = array();
        
        foreach ($sectors as $sector => $data) {
            $formatted[] = array(
                'name' => ucwords(str_replace('_', ' ', $sector)),
                'performance' => $this->format_percent($data['performance'] ?? 0),
                'volume' => $this->format_large_number($data['volume'] ?? 0),
                'leaders' => array_slice($data['leaders'] ?? array(), 0, 3),
                'trend' => $this->determine_trend($data['performance'] ?? 0)
            );
        }
        
        // Sort by performance
        usort($formatted, function($a, $b) {
            return $b['performance'] <=> $a['performance'];
        });
        
        return $formatted;
    }
    
    /**
     * Generate chart configuration
     */
    private function generate_chart_config($data) {
        return array(
            'type' => 'line',
            'data' => $this->prepare_chart_data($data),
            'options' => array(
                'responsive' => true,
                'maintainAspectRatio' => false,
                'scales' => array(
                    'y' => array('beginAtZero' => false)
                )
            )
        );
    }
    
    /**
     * Generate heatmap configuration
     */
    private function generate_heatmap_config($data) {
        return array(
            'type' => 'heatmap',
            'data' => $this->prepare_heatmap_data($data),
            'colorScale' => array(
                'min' => '#FF0000',
                'zero' => '#FFFFFF', 
                'max' => '#00FF00'
            )
        );
    }
    
    /**
     * Helper functions
     */
    private function format_large_number($number) {
        if ($number >= 1000000000000) {
            return round($number / 1000000000000, 2) . 'T';
        }
        if ($number >= 1000000000) {
            return round($number / 1000000000, 2) . 'B';
        }
        if ($number >= 1000000) {
            return round($number / 1000000, 2) . 'M';
        }
        if ($number >= 1000) {
            return round($number / 1000, 2) . 'K';
        }
        return number_format($number, 0);
    }
    
    private function format_change($change) {
        $formatted = number_format(abs($change), 2);
        return $change >= 0 ? '+' . $formatted : '-' . $formatted;
    }
    
    private function format_percent($percent) {
        $formatted = number_format(abs($percent), 2) . '%';
        return $percent >= 0 ? '+' . $formatted : '-' . $formatted;
    }
    
    private function determine_trend($value) {
        if ($value > 0.5) return 'up_strong';
        if ($value > 0) return 'up';
        if ($value < -0.5) return 'down_strong';
        if ($value < 0) return 'down';
        return 'neutral';
    }
    
    private function truncate_text($text, $length) {
        if (strlen($text) <= $length) {
            return $text;
        }
        return substr($text, 0, $length - 3) . '...';
    }
    
    private function format_time_ago($timestamp) {
        $time_diff = time() - strtotime($timestamp);
        
        if ($time_diff < 3600) {
            return round($time_diff / 60) . ' minutes ago';
        }
        if ($time_diff < 86400) {
            return round($time_diff / 3600) . ' hours ago';
        }
        if ($time_diff < 604800) {
            return round($time_diff / 86400) . ' days ago';
        }
        
        return date('M j, Y', strtotime($timestamp));
    }
    
    /**
     * Placeholder methods for complex visualizations
     */
    private function generate_sparklines($data) {
        return array('type' => 'sparklines', 'data' => array());
    }
    
    private function generate_price_chart($data) {
        return array('type' => 'price_chart', 'data' => array());
    }
    
    private function generate_volume_chart($data) {
        return array('type' => 'volume_chart', 'data' => array());
    }
    
    private function generate_performance_comparison($data) {
        return array('type' => 'performance_comparison', 'data' => array());
    }
    
    private function format_deal_value($value) {
        if ($value == 0) return 'Undisclosed';
        return '$' . $this->format_large_number($value);
    }
    
    private function generate_deal_structure_diagram($data) {
        return array('type' => 'deal_structure', 'data' => array());
    }
    
    private function generate_deal_timeline($data) {
        return array('type' => 'timeline', 'data' => array());
    }
    
    private function generate_comparables_chart($data) {
        return array('type' => 'comparables', 'data' => array());
    }
    
    private function analyze_sentiment($article) {
        // Simplified sentiment analysis
        return 'neutral';
    }
    
    private function extract_entities($article) {
        return array();
    }
    
    private function calculate_importance($article) {
        return 5; // Medium importance
    }
    
    private function group_by_category($articles) {
        $categories = array();
        foreach ($articles as $article) {
            $cat = $article['category'] ?? 'General';
            if (!isset($categories[$cat])) {
                $categories[$cat] = array();
            }
            $categories[$cat][] = $article;
        }
        return $categories;
    }
    
    private function extract_trending_topics($data) {
        return array();
    }
    
    private function generate_sentiment_gauge($data) {
        return array('type' => 'gauge', 'value' => 50);
    }
    
    private function generate_topic_cloud($data) {
        return array('type' => 'word_cloud', 'words' => array());
    }
    
    private function generate_news_timeline($data) {
        return array('type' => 'timeline', 'events' => array());
    }
    
    private function calculate_trend($indicator) {
        return 'stable';
    }
    
    private function evaluate_indicator_status($key, $value) {
        return 'normal';
    }
    
    private function generate_economic_trends($data) {
        return array('type' => 'trends', 'data' => array());
    }
    
    private function generate_yield_curve($data) {
        return array('type' => 'yield_curve', 'data' => array());
    }
    
    private function generate_economic_calendar($data) {
        return array('type' => 'calendar', 'events' => array());
    }
    
    private function generate_career_roadmap($data) {
        return array('type' => 'roadmap', 'stages' => array());
    }
    
    private function generate_skills_radar($data) {
        return array('type' => 'radar', 'skills' => array());
    }
    
    private function generate_comp_benchmark($data) {
        return array('type' => 'benchmark', 'data' => array());
    }
    
    private function prepare_chart_data($data) {
        return array();
    }
    
    private function prepare_heatmap_data($data) {
        return array();
    }
}