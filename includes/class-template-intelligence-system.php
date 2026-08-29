<?php
/**
 * Template Intelligence System
 * 
 * Provides weighted keyword detection and dynamic template matching
 * for editorial articles with pre-filled data extraction
 * 
 * @package SennaFinanceCareer
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Template_Intelligence_System {
    
    private static $instance = null;
    
    /**
     * Template categories with keywords and weights
     */
    private $template_categories = array(
        'fundraise' => array(
            'keywords' => array(
                'fund' => 10,
                'fundraise' => 15,
                'fundraising' => 15,
                'raise' => 8,
                'raising' => 8,
                'capital' => 5,
                'billion' => 6,
                'million' => 5,
                'target' => 7,
                'close' => 6,
                'closing' => 6,
                'commitment' => 7,
                'lp' => 8,
                'limited partner' => 9,
                'investor' => 5,
                'allocation' => 6,
                'deploy' => 5,
                'deployment' => 5,
                'dry powder' => 10,
                'vintage' => 8
            ),
            'template_ids' => array('fund_raise_global', 'fund_raise_regional', 'fund_raise_sector', 'fund_raise_comparison')
        ),
        'acquisition' => array(
            'keywords' => array(
                'acquire' => 12,
                'acquisition' => 15,
                'buy' => 10,
                'purchase' => 10,
                'takeover' => 12,
                'buyout' => 14,
                'lbo' => 12,
                'leveraged' => 8,
                'portfolio company' => 10,
                'platform' => 9,
                'add-on' => 11,
                'bolt-on' => 11,
                'strategic' => 7,
                'synergy' => 8,
                'integration' => 7,
                'valuation' => 8,
                'multiple' => 7,
                'ebitda' => 9,
                'enterprise value' => 10
            ),
            'template_ids' => array('acquisition_analysis', 'acquisition_sector', 'acquisition_strategy', 'acquisition_comparison')
        ),
        'exit' => array(
            'keywords' => array(
                'exit' => 15,
                'sale' => 10,
                'sell' => 8,
                'disposal' => 10,
                'divestment' => 11,
                'ipo' => 14,
                'listing' => 12,
                'public' => 8,
                'secondary' => 10,
                'buyer' => 7,
                'proceeds' => 8,
                'return' => 9,
                'irr' => 12,
                'moic' => 12,
                'realization' => 10,
                'cash-on-cash' => 11,
                'dividend' => 7,
                'recapitalization' => 10,
                'refinancing' => 8
            ),
            'template_ids' => array('exit_analysis', 'exit_returns', 'exit_strategy', 'exit_market_timing')
        ),
        'merger' => array(
            'keywords' => array(
                'merger' => 15,
                'merge' => 12,
                'combination' => 10,
                'consolidation' => 11,
                'joint venture' => 12,
                'jv' => 10,
                'partnership' => 8,
                'alliance' => 7,
                'combine' => 9,
                'integration' => 8,
                'synergies' => 10,
                'economies of scale' => 11,
                'market share' => 9,
                'competitive' => 7,
                'antitrust' => 10,
                'regulatory approval' => 11,
                'clearance' => 9
            ),
            'template_ids' => array('merger_analysis', 'merger_synergies', 'merger_integration', 'merger_regulatory')
        ),
        'esg' => array(
            'keywords' => array(
                'esg' => 20,
                'environmental' => 12,
                'social' => 10,
                'governance' => 11,
                'sustainability' => 13,
                'sustainable' => 11,
                'climate' => 12,
                'carbon' => 11,
                'net zero' => 14,
                'renewable' => 10,
                'diversity' => 10,
                'inclusion' => 9,
                'impact' => 8,
                'responsible' => 9,
                'stewardship' => 10,
                'stakeholder' => 8,
                'disclosure' => 9,
                'tcfd' => 12,
                'sfdr' => 12,
                'article 8' => 13,
                'article 9' => 14
            ),
            'template_ids' => array('esg_analysis', 'esg_metrics', 'esg_regulation', 'esg_impact')
        ),
        'regulation' => array(
            'keywords' => array(
                'regulation' => 15,
                'regulatory' => 13,
                'compliance' => 12,
                'sec' => 14,
                'fca' => 13,
                'esma' => 13,
                'penalty' => 11,
                'fine' => 10,
                'enforcement' => 11,
                'investigation' => 10,
                'probe' => 9,
                'scrutiny' => 8,
                'rules' => 7,
                'legislation' => 10,
                'policy' => 8,
                'framework' => 9,
                'directive' => 10,
                'requirement' => 8,
                'disclosure' => 9,
                'reporting' => 8
            ),
            'template_ids' => array('regulation_impact', 'regulation_compliance', 'regulation_changes', 'regulation_comparison')
        ),
        'people_moves' => array(
            'keywords' => array(
                'appoint' => 12,
                'appointment' => 13,
                'hire' => 11,
                'hired' => 11,
                'join' => 10,
                'joined' => 10,
                'promote' => 11,
                'promotion' => 11,
                'partner' => 9,
                'managing director' => 12,
                'md' => 10,
                'ceo' => 13,
                'cfo' => 12,
                'coo' => 11,
                'president' => 11,
                'chairman' => 12,
                'board' => 9,
                'leadership' => 8,
                'team' => 7,
                'departure' => 10,
                'leave' => 9,
                'retire' => 10
            ),
            'template_ids' => array('people_moves_impact', 'people_moves_leadership', 'people_moves_team', 'people_moves_succession')
        ),
        'rankings' => array(
            'keywords' => array(
                'ranking' => 15,
                'league table' => 14,
                'top' => 8,
                'best' => 8,
                'leading' => 9,
                'performance' => 10,
                'benchmark' => 11,
                'quartile' => 12,
                'percentile' => 11,
                'outperform' => 10,
                'underperform' => 9,
                'alpha' => 11,
                'beta' => 10,
                'sharpe' => 11,
                'comparison' => 8,
                'peer' => 9,
                'competitor' => 8,
                'market share' => 10,
                'aum' => 11,
                'assets under management' => 12
            ),
            'template_ids' => array('rankings_analysis', 'rankings_performance', 'rankings_comparison', 'rankings_methodology')
        ),
        'trends' => array(
            'keywords' => array(
                'trend' => 12,
                'outlook' => 10,
                'forecast' => 11,
                'prediction' => 9,
                'market' => 7,
                'sector' => 8,
                'industry' => 7,
                'growth' => 8,
                'decline' => 8,
                'shift' => 9,
                'disruption' => 10,
                'innovation' => 9,
                'technology' => 8,
                'digital' => 8,
                'transformation' => 9,
                'evolution' => 8,
                'emergence' => 9,
                'opportunity' => 7,
                'challenge' => 7,
                'risk' => 8
            ),
            'template_ids' => array('trends_analysis', 'trends_sector', 'trends_technology', 'trends_market')
        )
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
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Weekly template refresh cron job
        add_action('init', array($this, 'schedule_template_refresh'));
        add_action('sffc_refresh_templates', array($this, 'refresh_templates'));
    }
    
    /**
     * Schedule weekly template refresh
     */
    public function schedule_template_refresh() {
        if (!wp_next_scheduled('sffc_refresh_templates')) {
            wp_schedule_event(time(), 'weekly', 'sffc_refresh_templates');
        }
    }
    
    /**
     * Analyze article and determine best template match
     */
    public function analyze_article($post) {
        $title = get_the_title($post);
        $content = wp_strip_all_tags($post->post_content);
        $excerpt = get_the_excerpt($post);
        
        // Combine all text for analysis
        $full_text = strtolower($title . ' ' . $excerpt . ' ' . $content);
        
        // Score each category
        $category_scores = array();
        
        foreach ($this->template_categories as $category => $data) {
            $score = 0;
            $matched_keywords = array();
            
            foreach ($data['keywords'] as $keyword => $weight) {
                // Count occurrences of keyword
                $count = substr_count($full_text, strtolower($keyword));
                
                if ($count > 0) {
                    // Apply weight multiplied by occurrence count (capped at 3)
                    $occurrence_multiplier = min($count, 3);
                    $keyword_score = $weight * $occurrence_multiplier;
                    $score += $keyword_score;
                    
                    $matched_keywords[] = array(
                        'keyword' => $keyword,
                        'weight' => $weight,
                        'count' => $count,
                        'score' => $keyword_score
                    );
                }
            }
            
            // Bonus for title matches (2x multiplier)
            foreach ($data['keywords'] as $keyword => $weight) {
                if (stripos($title, $keyword) !== false) {
                    $score += $weight * 2;
                }
            }
            
            $category_scores[$category] = array(
                'score' => $score,
                'matched_keywords' => $matched_keywords,
                'confidence' => $this->calculate_confidence($score)
            );
        }
        
        // Sort by score
        arsort($category_scores);
        
        // Get the best match
        $best_category = key($category_scores);
        $best_score = current($category_scores);
        
        // Extract entities for pre-filling
        $entities = $this->extract_entities($post, $best_category);
        
        return array(
            'category' => $best_category,
            'score' => $best_score['score'],
            'confidence' => $best_score['confidence'],
            'matched_keywords' => $best_score['matched_keywords'],
            'entities' => $entities,
            'all_scores' => $category_scores,
            'recommended_template' => $this->select_template($best_category, $entities)
        );
    }
    
    /**
     * Calculate confidence level based on score
     */
    private function calculate_confidence($score) {
        if ($score >= 100) return 'very_high';
        if ($score >= 70) return 'high';
        if ($score >= 40) return 'medium';
        if ($score >= 20) return 'low';
        return 'very_low';
    }
    
    /**
     * Extract entities from article for pre-filling templates
     */
    public function extract_entities($post, $category) {
        $title = get_the_title($post);
        $content = wp_strip_all_tags($post->post_content);
        $entities = array();
        
        // Extract company names (entities in title case)
        preg_match_all('/\b([A-Z][a-z]+ ?(?:[A-Z][a-z]+ ?)*(?:LLC|LLP|LP|Inc|Corp|Company|Partners|Capital|Group|Management|Advisors)?)\b/', $title . ' ' . $content, $company_matches);
        $entities['companies'] = array_unique(array_filter($company_matches[1], function($company) {
            return strlen($company) > 2 && !in_array(strtolower($company), array('the', 'and', 'for', 'with'));
        }));
        
        // Extract monetary values
        preg_match_all('/(?:\$|USD|EUR|GBP|£|€)?\s*(\d+(?:\.\d+)?)\s*(?:billion|million|bn|mn|m|b)/i', $title . ' ' . $content, $value_matches);
        $entities['values'] = array();
        foreach ($value_matches[0] as $index => $match) {
            $number = floatval($value_matches[1][$index]);
            $multiplier = 1;
            if (stripos($match, 'billion') !== false || stripos($match, 'bn') !== false || stripos($match, 'b') !== false) {
                $multiplier = 1000000000;
            } elseif (stripos($match, 'million') !== false || stripos($match, 'mn') !== false || stripos($match, 'm') !== false) {
                $multiplier = 1000000;
            }
            $entities['values'][] = array(
                'raw' => $match,
                'number' => $number,
                'value' => $number * $multiplier,
                'formatted' => $this->format_currency($number * $multiplier)
            );
        }
        
        // Extract percentages
        preg_match_all('/(\d+(?:\.\d+)?)\s*%/', $content, $percentage_matches);
        $entities['percentages'] = array_map('floatval', $percentage_matches[1]);
        
        // Extract dates/years
        preg_match_all('/\b(20\d{2})\b/', $content, $year_matches);
        $entities['years'] = array_unique($year_matches[1]);
        
        // Extract regions/countries
        $regions = array('US', 'USA', 'United States', 'Europe', 'Asia', 'UK', 'United Kingdom', 'China', 'India', 'Japan', 'Germany', 'France', 'Canada', 'Australia', 'private equity', 'Africa', 'Latin America', 'EMEA', 'APAC', 'Americas');
        $entities['regions'] = array();
        foreach ($regions as $region) {
            if (stripos($title . ' ' . $content, $region) !== false) {
                $entities['regions'][] = $region;
            }
        }
        
        // Extract sectors
        $sectors = array('technology', 'healthcare', 'financial services', 'consumer', 'retail', 'industrial', 'energy', 'real estate', 'infrastructure', 'telecom', 'media', 'software', 'biotech', 'pharma', 'fintech', 'saas', 'e-commerce', 'logistics', 'manufacturing', 'hospitality', 'education', 'edtech');
        $entities['sectors'] = array();
        foreach ($sectors as $sector) {
            if (stripos($content, $sector) !== false) {
                $entities['sectors'][] = $sector;
            }
        }
        
        // Category-specific extraction
        switch ($category) {
            case 'fundraise':
                // Extract fund number (e.g., "fifth fund", "Fund V")
                preg_match('/(?:first|second|third|fourth|fifth|sixth|seventh|eighth|ninth|tenth|\d+(?:st|nd|rd|th))\s+(?:fund|vehicle)/i', $content, $fund_number);
                $entities['fund_number'] = $fund_number[0] ?? null;
                break;
                
            case 'people_moves':
                // Extract person names (consecutive capitalized words)
                preg_match_all('/\b([A-Z][a-z]+ [A-Z][a-z]+(?:\s+[A-Z][a-z]+)?)\b/', $content, $person_matches);
                $entities['people'] = array_unique(array_filter($person_matches[1], function($name) {
                    $words = explode(' ', $name);
                    return count($words) >= 2 && count($words) <= 4;
                }));
                
                // Extract titles
                $titles = array('CEO', 'CFO', 'COO', 'CTO', 'President', 'Chairman', 'Partner', 'Managing Director', 'Director', 'Vice President', 'VP', 'Head', 'Chief');
                $entities['titles'] = array();
                foreach ($titles as $title) {
                    if (stripos($content, $title) !== false) {
                        $entities['titles'][] = $title;
                    }
                }
                break;
        }
        
        return $entities;
    }
    
    /**
     * Format currency value
     */
    private function format_currency($value) {
        if ($value >= 1000000000) {
            return '$' . round($value / 1000000000, 1) . 'bn';
        } elseif ($value >= 1000000) {
            return '$' . round($value / 1000000, 1) . 'm';
        }
        return '$' . number_format($value);
    }
    
    /**
     * Select best template based on category and entities
     */
    private function select_template($category, $entities) {
        $templates = $this->get_templates_for_category($category);
        
        // Rotate templates to keep them fresh
        $current_week = date('W');
        $template_index = $current_week % count($templates);
        
        return $templates[$template_index];
    }
    
    /**
     * Get templates for a category
     */
    private function get_templates_for_category($category) {
        if (!isset($this->template_categories[$category])) {
            return array('default');
        }
        
        return $this->template_categories[$category]['template_ids'];
    }
    
    /**
     * Refresh templates (weekly cron job)
     */
    public function refresh_templates() {
        // Update template variations
        update_option('sffc_template_refresh_date', current_time('mysql'));
        
        // Rotate featured templates
        $this->rotate_featured_templates();
        
        // Clean up old cached analyses
        $this->cleanup_old_cache();
    }
    
    /**
     * Rotate featured templates
     */
    private function rotate_featured_templates() {
        $current_featured = get_option('sffc_featured_templates', array());
        $new_featured = array();
        
        foreach ($this->template_categories as $category => $data) {
            $templates = $data['template_ids'];
            $current = $current_featured[$category] ?? 0;
            $next = ($current + 1) % count($templates);
            $new_featured[$category] = $next;
        }
        
        update_option('sffc_featured_templates', $new_featured);
    }
    
    /**
     * Clean up old cached analyses
     */
    private function cleanup_old_cache() {
        global $wpdb;
        
        // Clean up analyses older than 30 days
        $wpdb->query(
            "DELETE FROM {$wpdb->postmeta} 
             WHERE meta_key LIKE '_sffc_template_analysis_%' 
             AND meta_value LIKE '%\"timestamp\"%' 
             AND meta_value NOT LIKE '%" . date('Y-m', strtotime('-30 days')) . "%'"
        );
    }
}

// Initialize the system
SFFC_Template_Intelligence_System::get_instance();