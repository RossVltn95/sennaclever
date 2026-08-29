<?php
/**
 * Article Classifier
 *
 * Classifies articles by topic and matches them to appropriate financial model templates.
 * Uses keyword analysis and pattern matching for fast, API-free classification.
 *
 * @package SFFC
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Article_Classifier {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Classification patterns
     */
    private $patterns = array();

    /**
     * Model configs cache
     */
    private $model_configs = array();

    /**
     * Get singleton instance
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
        $this->init_patterns();
    }

    /**
     * Initialize classification patterns
     */
    private function init_patterns() {
        $this->patterns = array(
            // Primary categories with keywords and weights
            'categories' => array(
                'pe_acquisition' => array(
                    'keywords' => array(
                        'private equity' => 10,
                        'buyout' => 10,
                        'lbo' => 10,
                        'leveraged buyout' => 10,
                        'take private' => 9,
                        'takes private' => 9,
                        'going private' => 9,
                        'sponsor' => 6,
                        'pe firm' => 8,
                        'pe-backed' => 8,
                        'kkr' => 7,
                        'blackstone' => 7,
                        'carlyle' => 7,
                        'apollo' => 7,
                        'tpg' => 6,
                        'warburg' => 6,
                        'bain capital' => 7,
                        'advent' => 6,
                        'cvc' => 6,
                        'eqt' => 6,
                        'cinven' => 6,
                        'permira' => 6,
                        'management buyout' => 9,
                        'mbo' => 8,
                        'secondary buyout' => 8,
                    ),
                    'model' => 'lbo',
                    'threshold' => 15,
                ),
                'strategic_ma' => array(
                    'keywords' => array(
                        'acquire' => 6,
                        'acquires' => 7,
                        'acquisition' => 7,
                        'acquiring' => 6,
                        'merger' => 8,
                        'merges' => 8,
                        'merging' => 7,
                        'merge with' => 8,
                        'takeover' => 7,
                        'bid for' => 6,
                        'offer for' => 6,
                        'deal value' => 5,
                        'transaction value' => 5,
                        'enterprise value' => 5,
                        'strategic acquisition' => 9,
                        'bolt-on' => 7,
                        'add-on acquisition' => 7,
                        'synergies' => 7,
                        'accretive' => 7,
                        'dilutive' => 6,
                        'cash and stock' => 6,
                        'all-cash deal' => 6,
                        'all-stock deal' => 6,
                    ),
                    'model' => 'merger',
                    'threshold' => 12,
                ),
                'real_estate' => array(
                    'keywords' => array(
                        'real estate' => 8,
                        'property' => 5,
                        'office space' => 8,
                        'sq ft' => 7,
                        'square feet' => 7,
                        'square foot' => 7,
                        'sqm' => 6,
                        'square meter' => 6,
                        'lease' => 6,
                        'leasing' => 6,
                        'leased' => 5,
                        'tenant' => 6,
                        'landlord' => 5,
                        'rent' => 4,
                        'rental' => 5,
                        'occupancy' => 6,
                        'vacancy' => 6,
                        'cap rate' => 9,
                        'noi' => 8,
                        'net operating income' => 9,
                        'canary wharf' => 7,
                        'mayfair' => 6,
                        'city of london' => 6,
                        'office tower' => 7,
                        'office building' => 7,
                        'warehouse' => 5,
                        'logistics' => 5,
                        'industrial property' => 7,
                        'retail space' => 6,
                        'shopping center' => 6,
                        'reit' => 7,
                    ),
                    'model' => 'commercial-re',
                    'threshold' => 15,
                ),
                'ipo_capital_markets' => array(
                    'keywords' => array(
                        'ipo' => 10,
                        'initial public offering' => 10,
                        'going public' => 9,
                        'public listing' => 9,
                        'stock exchange' => 7,
                        'nasdaq' => 6,
                        'nyse' => 6,
                        'lse' => 6,
                        'london stock exchange' => 7,
                        'float' => 5,
                        'flotation' => 7,
                        'prospectus' => 7,
                        'spac' => 8,
                        'blank check' => 7,
                        'de-spac' => 8,
                        'direct listing' => 8,
                    ),
                    'model' => 'ipo',
                    'threshold' => 15,
                ),
                'debt_financing' => array(
                    'keywords' => array(
                        'bond' => 6,
                        'bonds' => 6,
                        'bond issuance' => 8,
                        'debt financing' => 8,
                        'refinancing' => 7,
                        'refinance' => 7,
                        'credit facility' => 8,
                        'term loan' => 7,
                        'high yield' => 7,
                        'leveraged loan' => 8,
                        'syndicated loan' => 7,
                        'credit rating' => 6,
                        'moody' => 5,
                        's&p' => 4,
                        'fitch' => 5,
                        'investment grade' => 7,
                        'junk bond' => 7,
                        'covenant' => 6,
                    ),
                    'model' => 'debt',
                    'threshold' => 15,
                ),
                'restructuring' => array(
                    'keywords' => array(
                        'restructuring' => 9,
                        'restructure' => 8,
                        'bankruptcy' => 9,
                        'chapter 11' => 10,
                        'chapter 7' => 9,
                        'insolvency' => 9,
                        'administration' => 6,
                        'liquidation' => 8,
                        'distressed' => 8,
                        'turnaround' => 7,
                        'workout' => 7,
                        'debt restructuring' => 9,
                        'creditor' => 6,
                        'debtor' => 6,
                        'haircut' => 7,
                        'write-down' => 6,
                        'impairment' => 5,
                    ),
                    'model' => 'restructuring',
                    'threshold' => 15,
                ),
                'fundraising' => array(
                    'keywords' => array(
                        'raises' => 5,
                        'raised' => 5,
                        'raising' => 5,
                        'fund raise' => 8,
                        'fundraising' => 8,
                        'fund closing' => 8,
                        'final close' => 8,
                        'first close' => 7,
                        'committed capital' => 8,
                        'dry powder' => 7,
                        'aum' => 6,
                        'assets under management' => 7,
                        'lp' => 4,
                        'limited partner' => 7,
                        'gp commit' => 7,
                        'fund size' => 6,
                        'fund vi' => 6,
                        'fund vii' => 6,
                        'vintage' => 5,
                    ),
                    'model' => 'fund',
                    'threshold' => 15,
                ),
                'earnings' => array(
                    'keywords' => array(
                        'earnings' => 6,
                        'quarterly results' => 8,
                        'q1 results' => 7,
                        'q2 results' => 7,
                        'q3 results' => 7,
                        'q4 results' => 7,
                        'annual results' => 8,
                        'full year results' => 8,
                        'revenue' => 4,
                        'profit' => 4,
                        'eps' => 6,
                        'earnings per share' => 7,
                        'beat estimates' => 7,
                        'missed estimates' => 7,
                        'guidance' => 5,
                        'outlook' => 4,
                        'forecast' => 4,
                        'trading update' => 7,
                    ),
                    'model' => 'three-statement',
                    'threshold' => 15,
                ),
                'valuation' => array(
                    'keywords' => array(
                        'valuation' => 7,
                        'valued at' => 7,
                        'worth' => 4,
                        'fair value' => 8,
                        'intrinsic value' => 8,
                        'target price' => 7,
                        'price target' => 7,
                        'dcf' => 9,
                        'discounted cash flow' => 10,
                        'sum of parts' => 8,
                        'sotp' => 8,
                        'ev/ebitda' => 8,
                        'p/e ratio' => 6,
                        'multiple' => 4,
                        'premium' => 4,
                        'discount' => 4,
                    ),
                    'model' => 'dcf',
                    'threshold' => 15,
                ),
            ),

            // Sector detection
            'sectors' => array(
                'technology' => array('tech', 'software', 'saas', 'cloud', 'ai', 'artificial intelligence', 'fintech', 'cybersecurity', 'semiconductor', 'chip'),
                'healthcare' => array('healthcare', 'pharma', 'pharmaceutical', 'biotech', 'medical', 'hospital', 'drug', 'therapy', 'clinical'),
                'financial_services' => array('bank', 'banking', 'insurance', 'asset management', 'wealth management', 'fintech', 'payments', 'credit card'),
                'industrials' => array('industrial', 'manufacturing', 'aerospace', 'defense', 'automotive', 'machinery', 'engineering'),
                'consumer' => array('consumer', 'retail', 'e-commerce', 'food', 'beverage', 'apparel', 'luxury', 'restaurant'),
                'energy' => array('energy', 'oil', 'gas', 'renewable', 'solar', 'wind', 'utility', 'power'),
                'real_estate' => array('real estate', 'property', 'reit', 'office', 'retail space', 'warehouse', 'logistics'),
                'telecom' => array('telecom', 'telecommunications', 'mobile', '5g', 'broadband', 'fiber'),
                'media' => array('media', 'entertainment', 'streaming', 'advertising', 'publishing', 'gaming'),
            ),

            // Deal type patterns
            'deal_types' => array(
                'acquisition' => array('acquires', 'acquisition', 'acquire', 'to buy', 'purchasing'),
                'merger' => array('merger', 'merge', 'merging', 'combination'),
                'carve_out' => array('carve-out', 'carve out', 'divestiture', 'divest', 'spin-off', 'spin off', 'sells unit', 'sells division'),
                'take_private' => array('take private', 'takes private', 'going private', 'delisting'),
                'secondary' => array('secondary buyout', 'sponsor-to-sponsor', 'secondary sale'),
                'ipo' => array('ipo', 'initial public offering', 'going public', 'public listing'),
                'fundraise' => array('raises', 'fundraise', 'fund closing', 'capital raise'),
            ),

            // Geography detection
            'geography' => array(
                'uk' => array('uk', 'united kingdom', 'britain', 'british', 'london', 'manchester', 'birmingham', 'edinburgh', 'ftse'),
                'us' => array('us', 'united states', 'american', 'new york', 'california', 'texas', 'nasdaq', 'nyse'),
                'europe' => array('europe', 'european', 'eu', 'germany', 'france', 'spain', 'italy', 'netherlands'),
                'asia' => array('asia', 'china', 'japan', 'india', 'singapore', 'hong kong', 'korea'),
            ),
        );
    }

    /**
     * Classify an article
     *
     * @param int    $post_id  The post ID
     * @param string $title    Article title
     * @param string $content  Article content
     * @return array Classification result
     */
    public function classify($post_id, $title, $content) {
        // Combine title (weighted higher) and content for analysis
        $text = strtolower($title . ' ' . $title . ' ' . $title . ' ' . $content);

        // Get category scores
        $category_scores = $this->score_categories($text);

        // Determine primary category
        $primary_category = $this->get_primary_category($category_scores);

        // Get sector
        $sector = $this->detect_sector($text);

        // Get deal type
        $deal_type = $this->detect_deal_type($text);

        // Get geography
        $geography = $this->detect_geography($text);

        // Get data richness
        $data_richness = $this->assess_data_richness($text, $post_id);

        // Build classification result
        $classification = array(
            'primary_category' => $primary_category['category'],
            'category_confidence' => $primary_category['confidence'],
            'matched_model' => $primary_category['model'],
            'sector' => $sector,
            'deal_type' => $deal_type,
            'geography' => $geography,
            'data_richness' => $data_richness,
            'all_scores' => $category_scores,
            'classified_at' => current_time('mysql'),
        );

        // Store classification
        update_post_meta($post_id, '_sffc_article_classification', $classification);
        update_post_meta($post_id, '_sffc_matched_models', array($primary_category['model']));
        update_post_meta($post_id, '_sffc_data_richness', $data_richness);

        return $classification;
    }

    /**
     * Score all categories for the text
     */
    private function score_categories($text) {
        $scores = array();

        foreach ($this->patterns['categories'] as $category => $config) {
            $score = 0;
            $matched_keywords = array();

            foreach ($config['keywords'] as $keyword => $weight) {
                $count = substr_count($text, $keyword);
                if ($count > 0) {
                    $score += $weight * min($count, 3); // Cap at 3 occurrences
                    $matched_keywords[] = $keyword;
                }
            }

            $scores[$category] = array(
                'score' => $score,
                'threshold' => $config['threshold'],
                'model' => $config['model'],
                'matched_keywords' => $matched_keywords,
                'passes_threshold' => $score >= $config['threshold'],
            );
        }

        return $scores;
    }

    /**
     * Get primary category from scores
     */
    private function get_primary_category($scores) {
        $best_category = null;
        $best_score = 0;
        $best_model = 'dcf'; // Default fallback

        foreach ($scores as $category => $data) {
            if ($data['score'] > $best_score) {
                $best_score = $data['score'];
                $best_category = $category;
                $best_model = $data['model'];
            }
        }

        // Calculate confidence (0-100)
        $confidence = 0;
        if ($best_category && isset($scores[$best_category])) {
            $threshold = $scores[$best_category]['threshold'];
            $confidence = min(100, round(($best_score / $threshold) * 50));
        }

        return array(
            'category' => $best_category ?: 'general',
            'model' => $best_model,
            'score' => $best_score,
            'confidence' => $confidence,
        );
    }

    /**
     * Detect sector from text
     */
    private function detect_sector($text) {
        $sector_scores = array();

        foreach ($this->patterns['sectors'] as $sector => $keywords) {
            $score = 0;
            foreach ($keywords as $keyword) {
                $score += substr_count($text, $keyword) * 2;
            }
            $sector_scores[$sector] = $score;
        }

        arsort($sector_scores);
        $top_sector = key($sector_scores);

        return $sector_scores[$top_sector] > 0 ? $top_sector : 'general';
    }

    /**
     * Detect deal type from text
     */
    private function detect_deal_type($text) {
        foreach ($this->patterns['deal_types'] as $deal_type => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($text, $keyword) !== false) {
                    return $deal_type;
                }
            }
        }
        return null;
    }

    /**
     * Detect geography from text
     */
    private function detect_geography($text) {
        $geo_scores = array();

        foreach ($this->patterns['geography'] as $geo => $keywords) {
            $score = 0;
            foreach ($keywords as $keyword) {
                $score += substr_count($text, $keyword);
            }
            $geo_scores[$geo] = $score;
        }

        arsort($geo_scores);
        $top_geo = key($geo_scores);

        return $geo_scores[$top_geo] > 0 ? $top_geo : 'global';
    }

    /**
     * Assess data richness in the article
     */
    private function assess_data_richness($text, $post_id) {
        $score = 0;

        // Check for financial figures
        $currency_patterns = array(
            '/\$[\d,]+(?:\.\d+)?\s*(?:million|billion|mn|bn|m|b)/i',
            '/£[\d,]+(?:\.\d+)?\s*(?:million|billion|mn|bn|m|b)/i',
            '/€[\d,]+(?:\.\d+)?\s*(?:million|billion|mn|bn|m|b)/i',
            '/[\d,]+(?:\.\d+)?\s*(?:million|billion|mn|bn)\s*(?:dollars|pounds|euros)/i',
        );

        foreach ($currency_patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                $score += count($matches[0]) * 5;
            }
        }

        // Check for multiples
        if (preg_match_all('/[\d.]+x\s*(?:ebitda|revenue|earnings|ev)/i', $text, $matches)) {
            $score += count($matches[0]) * 8;
        }

        // Check for percentages
        if (preg_match_all('/[\d.]+\s*%/', $text, $matches)) {
            $score += min(count($matches[0]), 5) * 3;
        }

        // Check for specific deal terms
        $deal_terms = array('enterprise value', 'equity value', 'transaction value', 'purchase price', 'deal value');
        foreach ($deal_terms as $term) {
            if (strpos($text, $term) !== false) {
                $score += 10;
            }
        }

        // Check for existing deal financials
        $deal_financials = get_post_meta($post_id, '_sffc_deal_financials', true);
        if (!empty($deal_financials) && !empty($deal_financials['deal_value']['amount'])) {
            $score += 30;
        }

        // Determine richness level
        if ($score >= 50) {
            return 'full';
        } elseif ($score >= 25) {
            return 'partial';
        } else {
            return 'none';
        }
    }

    /**
     * Get model config for a model type
     */
    public function get_model_config($model_type) {
        if (isset($this->model_configs[$model_type])) {
            return $this->model_configs[$model_type];
        }

        $config_path = SFFC_PLUGIN_DIR . 'templates/models/' . $model_type . '/config.json';

        if (!file_exists($config_path)) {
            return null;
        }

        $config = json_decode(file_get_contents($config_path), true);
        $this->model_configs[$model_type] = $config;

        return $config;
    }

    /**
     * Get all available model types
     */
    public function get_available_models() {
        $models_dir = SFFC_PLUGIN_DIR . 'templates/models/';
        $models = array();

        if (is_dir($models_dir)) {
            $dirs = scandir($models_dir);
            foreach ($dirs as $dir) {
                if ($dir !== '.' && $dir !== '..' && is_dir($models_dir . $dir)) {
                    $config_path = $models_dir . $dir . '/config.json';
                    if (file_exists($config_path)) {
                        $config = json_decode(file_get_contents($config_path), true);
                        $models[$dir] = array(
                            'type' => $dir,
                            'name' => $config['model_name'] ?? ucfirst($dir),
                            'description' => $config['description'] ?? '',
                            'use_cases' => $config['use_cases'] ?? array(),
                        );
                    }
                }
            }
        }

        return $models;
    }

    /**
     * Quick classify for display purposes (without saving)
     */
    public function quick_classify($title, $content = '') {
        $text = strtolower($title . ' ' . $content);
        $category_scores = $this->score_categories($text);
        $primary = $this->get_primary_category($category_scores);
        $sector = $this->detect_sector($text);

        return array(
            'model' => $primary['model'],
            'category' => $primary['category'],
            'confidence' => $primary['confidence'],
            'sector' => $sector,
        );
    }

    /**
     * Extract entities from article text
     *
     * @param string $title   Article title
     * @param string $content Article content
     * @return array Extracted entities
     */
    public function extract_entities($title, $content) {
        $text = $title . ' ' . $content;
        $entities = array(
            'companies' => $this->extract_companies($text),
            'people' => $this->extract_people($text),
            'monetary_values' => $this->extract_monetary_values($text),
            'percentages' => $this->extract_percentages($text),
            'multiples' => $this->extract_multiples($text),
            'dates' => $this->extract_dates($text),
        );

        return $entities;
    }

    /**
     * Extract company names from text
     */
    private function extract_companies($text) {
        $companies = array();

        // Common PE firms
        $pe_firms = array(
            'KKR', 'Blackstone', 'Carlyle', 'Apollo', 'TPG', 'Warburg Pincus',
            'Bain Capital', 'Advent International', 'CVC Capital', 'EQT', 'Cinven',
            'Permira', 'BC Partners', 'General Atlantic', 'Silver Lake', 'Vista Equity',
            'Thoma Bravo', 'Hellman & Friedman', 'Brookfield', 'Ares', 'GTCR'
        );

        foreach ($pe_firms as $firm) {
            if (stripos($text, $firm) !== false) {
                $companies[] = array(
                    'name' => $firm,
                    'type' => 'pe_firm',
                    'role' => 'acquirer',
                );
            }
        }

        // Look for "Company Name + (verb)" patterns for targets/acquirers
        $acquirer_patterns = array(
            '/([A-Z][a-zA-Z\s&\.]+(?:Inc|Corp|Ltd|Plc|LLC|LP|Partners|Group|Holdings)?)\s+(?:to acquire|acquires|buys|purchases|to buy)/i',
            '/([A-Z][a-zA-Z\s&\.]+(?:Inc|Corp|Ltd|Plc|LLC|LP|Partners|Group|Holdings)?)\s+(?:announces|confirms|completes)\s+(?:acquisition|deal|merger)/i',
        );

        $target_patterns = array(
            '/(?:acquire|acquires|buys|purchases|buying|to buy)\s+([A-Z][a-zA-Z\s&\.]+(?:Inc|Corp|Ltd|Plc|LLC|LP|Partners|Group|Holdings)?)/i',
            '/(?:takeover|bid)\s+(?:for|of)\s+([A-Z][a-zA-Z\s&\.]+(?:Inc|Corp|Ltd|Plc|LLC|LP|Partners|Group|Holdings)?)/i',
        );

        foreach ($acquirer_patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] as $company) {
                    $company = trim($company);
                    if (strlen($company) > 2 && strlen($company) < 100) {
                        $companies[] = array(
                            'name' => $company,
                            'type' => 'company',
                            'role' => 'acquirer',
                        );
                    }
                }
            }
        }

        foreach ($target_patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] as $company) {
                    $company = trim($company);
                    if (strlen($company) > 2 && strlen($company) < 100) {
                        $companies[] = array(
                            'name' => $company,
                            'type' => 'company',
                            'role' => 'target',
                        );
                    }
                }
            }
        }

        // Remove duplicates
        $unique = array();
        foreach ($companies as $company) {
            $key = strtolower($company['name']);
            if (!isset($unique[$key])) {
                $unique[$key] = $company;
            }
        }

        return array_values($unique);
    }

    /**
     * Extract people names from text
     */
    private function extract_people($text) {
        $people = array();

        // Look for title + name patterns
        $patterns = array(
            '/(?:CEO|CFO|COO|CTO|Chairman|President|Managing Director|Partner|MD|Director)\s+([A-Z][a-z]+\s+[A-Z][a-z]+(?:\s+[A-Z][a-z]+)?)/i',
            '/([A-Z][a-z]+\s+[A-Z][a-z]+(?:\s+[A-Z][a-z]+)?),?\s+(?:the\s+)?(?:CEO|CFO|COO|CTO|Chairman|President|Managing Director|Partner|MD|Director)/i',
            '/(?:appointed|named|hired|promoted)\s+([A-Z][a-z]+\s+[A-Z][a-z]+(?:\s+[A-Z][a-z]+)?)\s+(?:as|to)/i',
        );

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] as $name) {
                    $name = trim($name);
                    if (strlen($name) > 4 && strlen($name) < 50) {
                        $people[] = array(
                            'name' => $name,
                            'context' => 'executive',
                        );
                    }
                }
            }
        }

        // Remove duplicates
        $unique = array();
        foreach ($people as $person) {
            $key = strtolower($person['name']);
            if (!isset($unique[$key])) {
                $unique[$key] = $person;
            }
        }

        return array_values($unique);
    }

    /**
     * Extract monetary values from text
     */
    private function extract_monetary_values($text) {
        $values = array();

        // Currency patterns
        $patterns = array(
            // $X billion/million format
            '/\$\s*([\d,]+(?:\.\d+)?)\s*(billion|billion|bn|b|million|mn|m|thousand|k)/i' => 'USD',
            // £X billion/million format
            '/£\s*([\d,]+(?:\.\d+)?)\s*(billion|billion|bn|b|million|mn|m|thousand|k)/i' => 'GBP',
            // €X billion/million format
            '/€\s*([\d,]+(?:\.\d+)?)\s*(billion|billion|bn|b|million|mn|m|thousand|k)/i' => 'EUR',
            // X billion/million dollars/pounds/euros
            '/([\d,]+(?:\.\d+)?)\s*(billion|bn|million|mn)\s*(?:US\s*)?(dollars|pounds|euros)/i' => 'MIXED',
        );

        foreach ($patterns as $pattern => $default_currency) {
            if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $amount = floatval(str_replace(',', '', $match[1]));
                    $unit = strtolower($match[2]);
                    $currency = $default_currency;

                    // Determine currency for MIXED pattern
                    if ($default_currency === 'MIXED' && isset($match[3])) {
                        $curr_word = strtolower($match[3]);
                        if ($curr_word === 'dollars') $currency = 'USD';
                        elseif ($curr_word === 'pounds') $currency = 'GBP';
                        elseif ($curr_word === 'euros') $currency = 'EUR';
                    }

                    // Normalize to millions
                    if (in_array($unit, array('billion', 'bn', 'b'))) {
                        $amount *= 1000;
                    } elseif (in_array($unit, array('thousand', 'k'))) {
                        $amount /= 1000;
                    }

                    $values[] = array(
                        'amount' => $amount,
                        'currency' => $currency,
                        'unit' => 'millions',
                        'raw' => $match[0],
                        'context' => $this->determine_value_context($text, $match[0]),
                    );
                }
            }
        }

        // Sort by amount descending
        usort($values, function($a, $b) {
            return $b['amount'] <=> $a['amount'];
        });

        return $values;
    }

    /**
     * Determine the context of a monetary value
     */
    private function determine_value_context($text, $match) {
        // Find position of match
        $pos = strpos($text, $match);
        if ($pos === false) return 'unknown';

        // Get surrounding context (100 chars before)
        $start = max(0, $pos - 100);
        $context = strtolower(substr($text, $start, $pos - $start + strlen($match)));

        $contexts = array(
            'deal_value' => array('deal value', 'transaction value', 'acquisition', 'purchase price', 'valued at', 'worth'),
            'enterprise_value' => array('enterprise value', 'ev of', 'ev at'),
            'revenue' => array('revenue', 'sales', 'turnover'),
            'ebitda' => array('ebitda', 'operating profit', 'earnings'),
            'equity_value' => array('equity value', 'market cap', 'market capitalization'),
            'debt' => array('debt', 'loan', 'borrowing', 'financing'),
        );

        foreach ($contexts as $ctx => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($context, $keyword) !== false) {
                    return $ctx;
                }
            }
        }

        return 'unspecified';
    }

    /**
     * Extract percentages from text
     */
    private function extract_percentages($text) {
        $percentages = array();

        // Pattern for percentages
        if (preg_match_all('/([\d.]+)\s*%/', $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                $value = floatval($match[1][0]);
                $pos = $match[0][1];

                // Get context
                $start = max(0, $pos - 50);
                $context = strtolower(substr($text, $start, 50));

                $type = 'unknown';
                if (preg_match('/premium|discount/', $context)) $type = 'premium_discount';
                elseif (preg_match('/stake|ownership|equity|share/', $context)) $type = 'ownership';
                elseif (preg_match('/growth|increase|rise|up/', $context)) $type = 'growth';
                elseif (preg_match('/margin|ebitda|profit/', $context)) $type = 'margin';
                elseif (preg_match('/irr|return/', $context)) $type = 'return';

                $percentages[] = array(
                    'value' => $value,
                    'type' => $type,
                    'raw' => $match[0][0],
                );
            }
        }

        return $percentages;
    }

    /**
     * Extract valuation multiples from text
     */
    private function extract_multiples($text) {
        $multiples = array();

        // Pattern for multiples (e.g., 12.5x EBITDA, 8x revenue)
        $pattern = '/([\d.]+)\s*x\s*(ebitda|revenue|sales|earnings|ev|p\/e|pe)/i';

        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $multiples[] = array(
                    'value' => floatval($match[1]),
                    'type' => strtoupper($match[2]),
                    'raw' => $match[0],
                );
            }
        }

        return $multiples;
    }

    /**
     * Extract dates from text
     */
    private function extract_dates($text) {
        $dates = array();

        // Common date patterns
        $patterns = array(
            // "January 15, 2025" or "Jan 15, 2025"
            '/(?:January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+\d{1,2},?\s+\d{4}/i',
            // "15 January 2025" or "15 Jan 2025"
            '/\d{1,2}\s+(?:January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+\d{4}/i',
            // Q1 2025, Q4 2024, etc.
            '/Q[1-4]\s+\d{4}/i',
            // FY2025, FY 2025
            '/FY\s*\d{4}/i',
        );

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[0] as $date) {
                    $dates[] = array(
                        'raw' => $date,
                        'normalized' => $this->normalize_date($date),
                    );
                }
            }
        }

        // Remove duplicates
        $unique = array();
        foreach ($dates as $date) {
            if (!in_array($date['raw'], array_column($unique, 'raw'))) {
                $unique[] = $date;
            }
        }

        return $unique;
    }

    /**
     * Normalize a date string
     */
    private function normalize_date($date_str) {
        $date = date_create($date_str);
        if ($date) {
            return date_format($date, 'Y-m-d');
        }
        return $date_str;
    }

    /**
     * Full classification with entity extraction
     *
     * @param int    $post_id  The post ID
     * @param string $title    Article title
     * @param string $content  Article content
     * @return array Full classification with entities
     */
    public function full_classify($post_id, $title, $content) {
        // Get basic classification
        $classification = $this->classify($post_id, $title, $content);

        // Extract entities
        $entities = $this->extract_entities($title, $content);

        // Merge entities into classification
        $classification['entities'] = $entities;

        // If we found a large monetary value and no deal value context, mark as potential deal value
        if (!empty($entities['monetary_values'])) {
            $largest = $entities['monetary_values'][0];
            if ($largest['amount'] > 100 && $largest['context'] === 'unspecified') {
                $classification['potential_deal_value'] = $largest;
            }
        }

        // Store the enriched classification
        update_post_meta($post_id, '_sffc_article_classification', $classification);
        update_post_meta($post_id, '_sffc_extracted_entities', $entities);

        return $classification;
    }
}
