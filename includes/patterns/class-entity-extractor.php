<?php
/**
 * Entity Extraction System
 * Phase 2: Extracts and normalizes entities from queries
 * 
 * @package SennaCareers
 * @since 6.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Entity_Extractor {
    
    private static $instance = null;
    private $pattern_library;
    private $data_cache;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Load pattern library
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/patterns/class-pattern-library.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/patterns/class-pattern-library.php';
            $this->pattern_library = SFFC_Pattern_Library::get_instance();
        }
        
        // Load data cache
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-data-cache-manager.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-data-cache-manager.php';
            $this->data_cache = SFFC_Data_Cache_Manager::get_instance();
        }
    }
    
    /**
     * Extract all entities from query
     */
    public function extract_entities($query) {
        $entities = array(
            'companies' => $this->extract_companies($query),
            'indices' => $this->extract_indices($query),
            'sectors' => $this->extract_sectors($query),
            'timeframe' => $this->extract_timeframe($query),
            'metrics' => $this->extract_metrics($query),
            'values' => $this->extract_values($query),
            'people' => $this->extract_people($query),
            'locations' => $this->extract_locations($query),
            'trading_terms' => $this->extract_trading_terms($query)
        );
        
        // Remove empty arrays
        return array_filter($entities, function($arr) {
            return !empty($arr);
        });
    }
    
    /**
     * Extract company names
     */
    public function extract_companies($query) {
        $companies = array();
        
        // Major PE firms
        $pe_firms = array(
            'Blackstone' => array('Blackstone', 'BX'),
            'KKR' => array('KKR', 'Kohlberg Kravis Roberts'),
            'Apollo' => array('Apollo', 'Apollo Global'),
            'Carlyle' => array('Carlyle', 'Carlyle Group'),
            'TPG' => array('TPG', 'Texas Pacific'),
            'Warburg Pincus' => array('Warburg Pincus', 'Warburg'),
            'Bain Capital' => array('Bain Capital', 'Bain'),
            'EQT' => array('EQT'),
            'CVC' => array('CVC', 'CVC Capital'),
            'Advent' => array('Advent', 'Advent International')
        );
        
        // Investment banks
        $banks = array(
            'Goldman Sachs' => array('Goldman Sachs', 'Goldman', 'GS'),
            'Morgan Stanley' => array('Morgan Stanley', 'MS'),
            'JPMorgan' => array('JPMorgan', 'JP Morgan', 'JPM', 'Chase'),
            'Bank of America' => array('Bank of America', 'BofA', 'BAC', 'Merrill Lynch'),
            'Citigroup' => array('Citigroup', 'Citi', 'Citibank'),
            'Deutsche Bank' => array('Deutsche Bank', 'Deutsche', 'DB'),
            'Barclays' => array('Barclays'),
            'UBS' => array('UBS'),
            'Credit Suisse' => array('Credit Suisse', 'CS'),
            'Wells Fargo' => array('Wells Fargo', 'Wells')
        );
        
        // Tech companies
        $tech = array(
            'Apple' => array('Apple', 'AAPL'),
            'Microsoft' => array('Microsoft', 'MSFT'),
            'Amazon' => array('Amazon', 'AMZN'),
            'Google' => array('Google', 'Alphabet', 'GOOGL', 'GOOG'),
            'Meta' => array('Meta', 'Facebook', 'FB', 'META'),
            'Tesla' => array('Tesla', 'TSLA'),
            'Nvidia' => array('Nvidia', 'NVDA'),
            'Netflix' => array('Netflix', 'NFLX')
        );
        
        $all_companies = array_merge($pe_firms, $banks, $tech);
        
        foreach ($all_companies as $canonical => $variations) {
            foreach ($variations as $variation) {
                if (preg_match('/\\b' . preg_quote($variation, '/') . '\\b/i', $query)) {
                    $companies[] = array(
                        'name' => $canonical,
                        'match' => $variation,
                        'type' => $this->classify_company($canonical)
                    );
                    break;
                }
            }
        }
        
        return $companies;
    }
    
    /**
     * Extract market indices
     */
    public function extract_indices($query) {
        $indices = array();
        
        $index_patterns = array(
            'SPX' => array('S&P 500', 'S&P500', 'SP500', 'SPX', 'S&P'),
            'COMP' => array('Nasdaq', 'NASDAQ', 'COMP', 'QQQ'),
            'DJI' => array('Dow Jones', 'Dow', 'DJIA', 'DJI'),
            'RUT' => array('Russell 2000', 'Russell', 'IWM', 'RUT'),
            'VIX' => array('VIX', 'Volatility Index', 'Fear Index'),
            'DXY' => array('DXY', 'Dollar Index', 'USD Index')
        );
        
        foreach ($index_patterns as $symbol => $variations) {
            foreach ($variations as $variation) {
                if (preg_match('/\\b' . preg_quote($variation, '/') . '\\b/i', $query)) {
                    $indices[] = array(
                        'symbol' => $symbol,
                        'name' => $variations[0],
                        'match' => $variation
                    );
                    break;
                }
            }
        }
        
        return $indices;
    }
    
    /**
     * Extract sectors
     */
    public function extract_sectors($query) {
        $sectors = array();
        
        $sector_map = array(
            'Technology' => array('tech', 'technology', 'software', 'hardware'),
            'Healthcare' => array('healthcare', 'health care', 'pharma', 'biotech'),
            'Financials' => array('financials', 'financial', 'banks', 'banking'),
            'Energy' => array('energy', 'oil', 'gas', 'petroleum'),
            'Consumer Discretionary' => array('consumer discretionary', 'retail', 'consumer'),
            'Industrials' => array('industrials', 'industrial', 'manufacturing'),
            'Materials' => array('materials', 'mining', 'chemicals'),
            'Utilities' => array('utilities', 'utility', 'power'),
            'Real Estate' => array('real estate', 'REIT', 'property'),
            'Communication Services' => array('communication', 'telecom', 'media')
        );
        
        foreach ($sector_map as $sector => $keywords) {
            foreach ($keywords as $keyword) {
                if (preg_match('/\\b' . preg_quote($keyword, '/') . '\\b/i', $query)) {
                    $sectors[] = array(
                        'name' => $sector,
                        'match' => $keyword
                    );
                    break;
                }
            }
        }
        
        return $sectors;
    }
    
    /**
     * Extract timeframe references
     */
    public function extract_timeframe($query) {
        $timeframes = array();
        
        // Relative timeframes
        if (preg_match('/\\b(today|now|current)\\b/i', $query)) {
            $timeframes[] = array(
                'type' => 'current',
                'start' => date('Y-m-d'),
                'end' => date('Y-m-d')
            );
        }
        
        if (preg_match('/\\b(yesterday)\\b/i', $query)) {
            $timeframes[] = array(
                'type' => 'past',
                'start' => date('Y-m-d', strtotime('-1 day')),
                'end' => date('Y-m-d', strtotime('-1 day'))
            );
        }
        
        if (preg_match('/\\bthis\\s+week\\b/i', $query)) {
            $timeframes[] = array(
                'type' => 'current_period',
                'period' => 'week',
                'start' => date('Y-m-d', strtotime('monday this week')),
                'end' => date('Y-m-d', strtotime('sunday this week'))
            );
        }
        
        if (preg_match('/\\blast\\s+week\\b/i', $query)) {
            $timeframes[] = array(
                'type' => 'past_period',
                'period' => 'week',
                'start' => date('Y-m-d', strtotime('monday last week')),
                'end' => date('Y-m-d', strtotime('sunday last week'))
            );
        }
        
        // Quarter references
        if (preg_match('/\\b(Q1|Q2|Q3|Q4|quarter)\\b/i', $query, $matches)) {
            $quarter = $matches[1];
            $timeframes[] = array(
                'type' => 'quarter',
                'quarter' => $quarter,
                'year' => date('Y')
            );
        }
        
        // YTD
        if (preg_match('/\\b(YTD|year\\s+to\\s+date)\\b/i', $query)) {
            $timeframes[] = array(
                'type' => 'ytd',
                'start' => date('Y-01-01'),
                'end' => date('Y-m-d')
            );
        }
        
        return $timeframes;
    }
    
    /**
     * Extract financial metrics
     */
    public function extract_metrics($query) {
        $metrics = array();
        
        $metric_patterns = array(
            'pe_ratio' => array('PE', 'P/E', 'price to earnings', 'price-to-earnings'),
            'market_cap' => array('market cap', 'market capitalization', 'mcap'),
            'revenue' => array('revenue', 'sales', 'top line'),
            'earnings' => array('earnings', 'profit', 'income', 'bottom line'),
            'eps' => array('EPS', 'earnings per share'),
            'ebitda' => array('EBITDA', 'operating income'),
            'margin' => array('margin', 'gross margin', 'operating margin', 'net margin'),
            'yield' => array('yield', 'dividend yield'),
            'volume' => array('volume', 'trading volume'),
            'volatility' => array('volatility', 'vol', 'standard deviation')
        );
        
        foreach ($metric_patterns as $metric_type => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match('/\\b' . preg_quote($pattern, '/') . '\\b/i', $query)) {
                    $metrics[] = array(
                        'type' => $metric_type,
                        'match' => $pattern
                    );
                    break;
                }
            }
        }
        
        return $metrics;
    }
    
    /**
     * Extract numerical values and amounts
     */
    public function extract_values($query) {
        $values = array();
        
        // Match currency values
        if (preg_match_all('/\\$?([0-9]+(?:\\.[0-9]+)?)\\s*(B|billion|M|million|K|thousand|T|trillion)/i', $query, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $multiplier = $this->get_multiplier($match[2]);
                $values[] = array(
                    'raw' => $match[0],
                    'number' => floatval($match[1]),
                    'multiplier' => $multiplier,
                    'value' => floatval($match[1]) * $multiplier,
                    'type' => 'currency'
                );
            }
        }
        
        // Match percentages
        if (preg_match_all('/([\+\-]?)([0-9]+(?:\.[0-9]+)?)%/', $query, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $values[] = array(
                    'raw' => $match[0],
                    'sign' => $match[1],
                    'number' => floatval($match[2]),
                    'type' => 'percentage'
                );
            }
        }
        
        return $values;
    }
    
    /**
     * Extract people names
     */
    public function extract_people($query) {
        $people = array();
        
        $notable_people = array(
            'Warren Buffett' => array('role' => 'CEO Berkshire Hathaway', 'type' => 'investor'),
            'Jamie Dimon' => array('role' => 'CEO JPMorgan', 'type' => 'banker'),
            'Larry Fink' => array('role' => 'CEO BlackRock', 'type' => 'asset_manager'),
            'Stephen Schwarzman' => array('role' => 'CEO Blackstone', 'type' => 'pe'),
            'Jerome Powell' => array('role' => 'Fed Chair', 'type' => 'regulator'),
            'Janet Yellen' => array('role' => 'Treasury Secretary', 'type' => 'government'),
            'Elon Musk' => array('role' => 'CEO Tesla', 'type' => 'tech'),
            'Tim Cook' => array('role' => 'CEO Apple', 'type' => 'tech'),
            'Satya Nadella' => array('role' => 'CEO Microsoft', 'type' => 'tech')
        );
        
        foreach ($notable_people as $name => $info) {
            if (stripos($query, $name) !== false) {
                $people[] = array_merge(array('name' => $name), $info);
            }
        }
        
        return $people;
    }
    
    /**
     * Extract locations/regions
     */
    public function extract_locations($query) {
        $locations = array();
        
        $location_map = array(
            'US' => array('US', 'USA', 'United States', 'America', 'American'),
            'Europe' => array('Europe', 'European', 'EU', 'Eurozone'),
            'UK' => array('UK', 'Britain', 'British', 'London'),
            'China' => array('China', 'Chinese', 'Beijing', 'Shanghai'),
            'Japan' => array('Japan', 'Japanese', 'Tokyo'),
            'Emerging Markets' => array('emerging markets', 'EM', 'developing'),
            'Global' => array('global', 'worldwide', 'international')
        );
        
        foreach ($location_map as $region => $variations) {
            foreach ($variations as $variation) {
                if (preg_match('/\\b' . preg_quote($variation, '/') . '\\b/i', $query)) {
                    $locations[] = array(
                        'region' => $region,
                        'match' => $variation
                    );
                    break;
                }
            }
        }
        
        return $locations;
    }
    
    /**
     * Extract trading terms
     */
    public function extract_trading_terms($query) {
        $terms = array();
        
        $trading_terms = array(
            'bullish' => 'positive',
            'bearish' => 'negative',
            'long' => 'buy',
            'short' => 'sell',
            'overbought' => 'sell_signal',
            'oversold' => 'buy_signal',
            'breakout' => 'bullish_signal',
            'breakdown' => 'bearish_signal',
            'support' => 'price_floor',
            'resistance' => 'price_ceiling'
        );
        
        foreach ($trading_terms as $term => $sentiment) {
            if (preg_match('/\\b' . $term . '\\b/i', $query)) {
                $terms[] = array(
                    'term' => $term,
                    'sentiment' => $sentiment
                );
            }
        }
        
        return $terms;
    }
    
    /**
     * Classify company type
     */
    private function classify_company($company) {
        $pe_firms = array('Blackstone', 'KKR', 'Apollo', 'Carlyle', 'TPG', 'Warburg Pincus', 'Bain Capital');
        $banks = array('Goldman Sachs', 'Morgan Stanley', 'JPMorgan', 'Bank of America', 'Citigroup');
        $tech = array('Apple', 'Microsoft', 'Amazon', 'Google', 'Meta', 'Tesla', 'Nvidia');
        
        if (in_array($company, $pe_firms)) {
            return 'private_equity';
        } elseif (in_array($company, $banks)) {
            return 'investment_bank';
        } elseif (in_array($company, $tech)) {
            return 'technology';
        }
        
        return 'other';
    }
    
    /**
     * Get multiplier for value units
     */
    private function get_multiplier($unit) {
        $unit = strtolower($unit);
        
        switch ($unit) {
            case 't':
            case 'trillion':
                return 1000000000000;
            case 'b':
            case 'billion':
                return 1000000000;
            case 'm':
            case 'million':
                return 1000000;
            case 'k':
            case 'thousand':
                return 1000;
            default:
                return 1;
        }
    }
    
    /**
     * Extract relationships between entities
     */
    public function extract_relationships($query, $entities) {
        $relationships = array();
        
        // Comparison relationships
        if (preg_match('/(vs?\\.?|versus|compared?|against)/i', $query)) {
            if (count($entities['companies']) >= 2) {
                $relationships[] = array(
                    'type' => 'comparison',
                    'entity1' => $entities['companies'][0],
                    'entity2' => $entities['companies'][1]
                );
            }
        }
        
        // Causal relationships
        if (preg_match('/(because|due\\s+to|caused\\s+by|result\\s+of)/i', $query)) {
            $relationships[] = array(
                'type' => 'causal',
                'entities' => $entities
            );
        }
        
        // Temporal relationships
        if (preg_match('/(before|after|during|since|until)/i', $query)) {
            $relationships[] = array(
                'type' => 'temporal',
                'entities' => $entities
            );
        }
        
        return $relationships;
    }
}