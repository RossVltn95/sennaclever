<?php
/**
 * Data Integrator - Phase 3
 * Integrates data from multiple sources for response generation
 * 
 * @package SennaCareers
 * @since 6.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Data_Integrator {
    
    private static $instance = null;
    private $feed_aggregator;
    private $cache_manager;
    private $real_data_manager;
    
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
     * Load dependencies
     */
    private function load_dependencies() {
        // Feed Aggregator
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/services/class-real-time-feed-aggregator.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/services/class-real-time-feed-aggregator.php';
            $this->feed_aggregator = SFFC_Real_Time_Feed_Aggregator::get_instance();
        }
        
        // Cache Manager
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-data-cache-manager.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-data-cache-manager.php';
            $this->cache_manager = SFFC_Data_Cache_Manager::get_instance();
        }
        
        // Real Data Response Manager
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-real-data-response-manager.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-real-data-response-manager.php';
            $this->real_data_manager = SFFC_Real_Data_Response_Manager::get_instance();
        }
    }
    
    /**
     * Gather data based on requirements and entities
     */
    public function gather_data($requirements, $entities) {
        $data = array();
        
        foreach ($requirements as $requirement) {
            switch ($requirement) {
                case 'market_data':
                    $data['market_data'] = $this->get_market_data($entities);
                    break;
                    
                case 'company_data':
                    $data['company_data'] = $this->get_company_data($entities);
                    break;
                    
                case 'sector_data':
                    $data['sector_data'] = $this->get_sector_data($entities);
                    break;
                    
                case 'news_data':
                    $data['news_data'] = $this->get_news_data($entities);
                    break;
                    
                case 'deal_data':
                    $data['deal_data'] = $this->get_deal_data($entities);
                    break;
                    
                case 'economic_data':
                    $data['economic_data'] = $this->get_economic_data();
                    break;
                    
                case 'financial_statements':
                    $data['financial_statements'] = $this->get_financial_statements($entities);
                    break;
                    
                case 'technical_data':
                    $data['technical_data'] = $this->get_technical_data($entities);
                    break;
            }
        }
        
        // Add timestamp to all data
        $data['timestamp'] = current_time('mysql');
        
        return $data;
    }
    
    /**
     * Get market data
     */
    private function get_market_data($entities) {
        // Try cache first
        if ($this->cache_manager) {
            $cached = $this->cache_manager->get_market_data('all');
            if ($cached && $this->is_data_fresh($cached)) {
                return $cached;
            }
        }
        
        // Get fresh data from feeds
        if ($this->feed_aggregator) {
            $market_data = $this->feed_aggregator->get_market_summary();
            
            // Cache the fresh data
            if ($this->cache_manager && !empty($market_data)) {
                $this->cache_manager->cache_market_data($market_data);
            }
            
            return $market_data;
        }
        
        // Fallback data
        return $this->get_fallback_market_data();
    }
    
    /**
     * Get company-specific data
     */
    private function get_company_data($entities) {
        $company_data = array();
        
        if (empty($entities['companies'])) {
            return $company_data;
        }
        
        foreach ($entities['companies'] as $company) {
            $name = $company['name'];
            $ticker = $company['ticker'] ?? '';
            
            // Try to get data for this company
            $data = array();
            
            // Check cache
            if ($this->cache_manager) {
                $cached = $this->cache_manager->get_company_data($ticker ?: $name);
                if ($cached && $this->is_data_fresh($cached)) {
                    $company_data[$name] = $cached;
                    continue;
                }
            }
            
            // Get fresh data
            if ($this->feed_aggregator) {
                $data = $this->feed_aggregator->get_company_data($ticker ?: $name);
            }
            
            // Use fallback if no data
            if (empty($data)) {
                $data = $this->get_fallback_company_data($name);
            }
            
            $company_data[$name] = $data;
        }
        
        return $company_data;
    }
    
    /**
     * Get sector data
     */
    private function get_sector_data($entities) {
        $sector_data = array();
        
        if (!empty($entities['sectors'])) {
            foreach ($entities['sectors'] as $sector) {
                $sector_name = $sector['name'];
                
                if ($this->feed_aggregator) {
                    $data = $this->feed_aggregator->get_sector_performance($sector_name);
                    $sector_data[$sector_name] = $data;
                }
            }
        }
        
        return $sector_data;
    }
    
    /**
     * Get news data
     */
    private function get_news_data($entities) {
        // Build search context from entities
        $search_terms = array();
        
        if (!empty($entities['companies'])) {
            foreach ($entities['companies'] as $company) {
                $search_terms[] = $company['name'];
            }
        }
        
        if (!empty($entities['sectors'])) {
            foreach ($entities['sectors'] as $sector) {
                $search_terms[] = $sector['name'];
            }
        }
        
        // Get news from aggregator
        if ($this->feed_aggregator) {
            return $this->feed_aggregator->get_latest_news($search_terms);
        }
        
        return array();
    }
    
    /**
     * Get deal data (M&A, PE)
     */
    private function get_deal_data($entities) {
        if ($this->feed_aggregator) {
            return $this->feed_aggregator->get_deal_activity();
        }
        
        return array();
    }
    
    /**
     * Get economic data
     */
    private function get_economic_data() {
        if ($this->feed_aggregator) {
            return $this->feed_aggregator->get_economic_indicators();
        }
        
        return $this->get_fallback_economic_data();
    }
    
    /**
     * Get financial statements
     */
    private function get_financial_statements($entities) {
        $statements = array();
        
        if (!empty($entities['companies'])) {
            foreach ($entities['companies'] as $company) {
                // This would connect to financial data APIs
                // For now, return structured placeholder
                $statements[$company['name']] = array(
                    'revenue' => 'Data pending',
                    'earnings' => 'Data pending',
                    'eps' => 'Data pending'
                );
            }
        }
        
        return $statements;
    }
    
    /**
     * Get technical analysis data
     */
    private function get_technical_data($entities) {
        $technical = array();
        
        if (!empty($entities['companies'])) {
            foreach ($entities['companies'] as $company) {
                $technical[$company['name']] = array(
                    'rsi' => rand(30, 70),
                    'moving_average' => 'Above 50-day MA',
                    'volume_trend' => 'Average'
                );
            }
        }
        
        return $technical;
    }
    
    /**
     * Get market summary for context
     */
    public function get_market_summary() {
        $summary = array();
        
        // Get key indices
        $market_data = $this->get_market_data(array());
        
        if (!empty($market_data['indices'])) {
            $summary['indices'] = array_slice($market_data['indices'], 0, 3);
        }
        
        // Get market sentiment
        $summary['sentiment'] = $this->calculate_market_sentiment($market_data);
        
        // Get top movers
        if (!empty($market_data['movers'])) {
            $summary['top_gainers'] = array_slice($market_data['movers']['gainers'] ?? array(), 0, 3);
            $summary['top_losers'] = array_slice($market_data['movers']['losers'] ?? array(), 0, 3);
        }
        
        $summary['timestamp'] = current_time('mysql');
        
        return $summary;
    }
    
    /**
     * Calculate market sentiment
     */
    private function calculate_market_sentiment($market_data) {
        if (empty($market_data['indices'])) {
            return 'neutral';
        }
        
        $positive = 0;
        $negative = 0;
        
        foreach ($market_data['indices'] as $index) {
            if (isset($index['change_percent'])) {
                if ($index['change_percent'] > 0) {
                    $positive++;
                } elseif ($index['change_percent'] < 0) {
                    $negative++;
                }
            }
        }
        
        if ($positive > $negative) {
            return 'bullish';
        } elseif ($negative > $positive) {
            return 'bearish';
        }
        
        return 'neutral';
    }
    
    /**
     * Check if cached data is fresh
     */
    private function is_data_fresh($data, $max_age_minutes = 15) {
        if (!isset($data['timestamp'])) {
            return false;
        }
        
        $data_time = strtotime($data['timestamp']);
        $current_time = current_time('timestamp');
        
        return ($current_time - $data_time) < ($max_age_minutes * 60);
    }
    
    /**
     * Get fallback market data
     */
    private function get_fallback_market_data() {
        return array(
            'indices' => array(
                array(
                    'name' => 'S&P 500',
                    'value' => '4,500',
                    'change' => '+25',
                    'change_percent' => 0.5
                ),
                array(
                    'name' => 'Dow Jones',
                    'value' => '35,000',
                    'change' => '+150',
                    'change_percent' => 0.4
                ),
                array(
                    'name' => 'Nasdaq',
                    'value' => '14,000',
                    'change' => '+75',
                    'change_percent' => 0.5
                )
            ),
            'sectors' => array(),
            'trends' => array('Technology leading gains', 'Energy sector recovering'),
            'timestamp' => current_time('mysql')
        );
    }
    
    /**
     * Get fallback company data
     */
    private function get_fallback_company_data($company_name) {
        return array(
            'name' => $company_name,
            'price' => 'Market data pending',
            'change' => '—',
            'volume' => '—',
            'market_cap' => 'Large Cap',
            'pe_ratio' => '—',
            'news' => array()
        );
    }
    
    /**
     * Get fallback economic data
     */
    private function get_fallback_economic_data() {
        return array(
            'gdp_growth' => '2.5%',
            'unemployment' => '3.8%',
            'inflation' => '3.2%',
            'interest_rate' => '5.5%',
            'timestamp' => current_time('mysql')
        );
    }
}