<?php
/**
 * PE Data Enrichment
 * Extracts PE-specific metadata from existing job fields
 * 
 * @package SennaCareers
 * @since 6.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_PE_Data_Enrichment {
    
    private static $instance = null;
    
    /**
     * Performance: Cache for enriched data
     */
    private $enrichment_cache = [];
    private $cache_ttl = 3600; // 1 hour cache
    
    /**
     * PE Fund Database
     * Maps company names to PE-specific attributes
     */
    private $pe_fund_database = [
        // MEGA-CAP FUNDS (€5bn+)
        'blackstone' => [
            'fund_size' => 'mega',
            'fund_type' => 'top-tier',
            'geo_focus' => 'global',
            'work_style' => 'intense',
            'typical_hours' => '70+',
            'culture' => 'deal-driven',
            'aliases' => ['blackstone group', 'bx', 'blackstone inc']
        ],
        'kkr' => [
            'fund_size' => 'mega',
            'fund_type' => 'top-tier',
            'geo_focus' => 'global',
            'work_style' => 'intense',
            'typical_hours' => '70+',
            'culture' => 'aggressive',
            'aliases' => ['kohlberg kravis roberts', 'kkr & co']
        ],
        'apollo' => [
            'fund_size' => 'mega',
            'fund_type' => 'top-tier',
            'geo_focus' => 'global',
            'work_style' => 'intense',
            'typical_hours' => '70+',
            'culture' => 'entrepreneurial',
            'aliases' => ['apollo global management', 'apollo management']
        ],
        'carlyle' => [
            'fund_size' => 'mega',
            'fund_type' => 'top-tier',
            'geo_focus' => 'global',
            'work_style' => 'intense',
            'typical_hours' => '65-75',
            'culture' => 'collaborative',
            'aliases' => ['carlyle group', 'the carlyle group']
        ],
        'tpg' => [
            'fund_size' => 'mega',
            'fund_type' => 'top-tier',
            'geo_focus' => 'global',
            'work_style' => 'intense',
            'typical_hours' => '65-75',
            'culture' => 'operational',
            'aliases' => ['texas pacific group', 'tpg capital']
        ],
        
        // LARGE-CAP FUNDS (€1-5bn)
        'permira' => [
            'fund_size' => 'large',
            'fund_type' => 'top-tier',
            'geo_focus' => 'pan-european',
            'work_style' => 'fluctuates',
            'typical_hours' => '60-70',
            'culture' => 'european-style',
            'aliases' => ['permira advisers']
        ],
        'cinven' => [
            'fund_size' => 'large',
            'fund_type' => 'mid-size',
            'geo_focus' => 'pan-european',
            'work_style' => 'fluctuates',
            'typical_hours' => '55-65',
            'culture' => 'partnership',
            'aliases' => ['cinven partners', 'cinven limited']
        ],
        'eqt' => [
            'fund_size' => 'large',
            'fund_type' => 'mid-size',
            'geo_focus' => 'nordics',
            'work_style' => 'normal',
            'typical_hours' => '50-60',
            'culture' => 'scandinavian',
            'aliases' => ['eqt partners', 'eqt ab']
        ],
        'cvc' => [
            'fund_size' => 'large',
            'fund_type' => 'top-tier',
            'geo_focus' => 'pan-european',
            'work_style' => 'intense',
            'typical_hours' => '65-75',
            'culture' => 'deal-focused',
            'aliases' => ['cvc capital partners', 'cvc capital']
        ],
        'advent' => [
            'fund_size' => 'large',
            'fund_type' => 'top-tier',
            'geo_focus' => 'global',
            'work_style' => 'fluctuates',
            'typical_hours' => '60-70',
            'culture' => 'international',
            'aliases' => ['advent international']
        ],
        
        // MID-MARKET FUNDS
        'bridgepoint' => [
            'fund_size' => 'mid',
            'fund_type' => 'mid-size',
            'geo_focus' => 'pan-european',
            'work_style' => 'fluctuates',
            'typical_hours' => '55-65',
            'culture' => 'entrepreneurial',
            'aliases' => ['bridgepoint group', 'bridgepoint capital']
        ],
        'montagu' => [
            'fund_size' => 'mid',
            'fund_type' => 'mid-size',
            'geo_focus' => 'uk-ireland',
            'work_style' => 'normal',
            'typical_hours' => '50-60',
            'culture' => 'traditional',
            'aliases' => ['montagu private equity']
        ],
        'triton' => [
            'fund_size' => 'mid',
            'fund_type' => 'mid-size',
            'geo_focus' => 'dach',
            'work_style' => 'fluctuates',
            'typical_hours' => '55-65',
            'culture' => 'germanic',
            'aliases' => ['triton partners']
        ],
        'pai' => [
            'fund_size' => 'mid',
            'fund_type' => 'mid-size',
            'geo_focus' => 'pan-european',
            'work_style' => 'fluctuates',
            'typical_hours' => '55-65',
            'culture' => 'french-style',
            'aliases' => ['pai partners']
        ],
        
        // LOWER MID-MARKET
        'ldc' => [
            'fund_size' => 'lower',
            'fund_type' => 'boutique',
            'geo_focus' => 'uk-ireland',
            'work_style' => 'normal',
            'typical_hours' => '50-55',
            'culture' => 'regional',
            'aliases' => ['lloyds development capital']
        ],
        'livingbridge' => [
            'fund_size' => 'lower',
            'fund_type' => 'boutique',
            'geo_focus' => 'uk-ireland',
            'work_style' => 'normal',
            'typical_hours' => '50-55',
            'culture' => 'growth-focused',
            'aliases' => ['livingbridge ep']
        ],
        
        // ASSET MANAGEMENT FIRMS
        'blackrock' => [
            'fund_size' => 'mega',
            'fund_type' => 'asset-manager',
            'geo_focus' => 'global',
            'work_style' => 'fluctuates',
            'typical_hours' => '55-65',
            'culture' => 'institutional',
            'aliases' => ['blackrock inc', 'blackrock advisors']
        ],
        'vanguard' => [
            'fund_size' => 'mega',
            'fund_type' => 'asset-manager',
            'geo_focus' => 'global',
            'work_style' => 'normal',
            'typical_hours' => '45-55',
            'culture' => 'investor-focused',
            'aliases' => ['vanguard group', 'vanguard investments']
        ],
        'fidelity' => [
            'fund_size' => 'mega',
            'fund_type' => 'asset-manager',
            'geo_focus' => 'global',
            'work_style' => 'normal',
            'typical_hours' => '50-60',
            'culture' => 'research-driven',
            'aliases' => ['fidelity investments', 'fidelity management']
        ],
        'schroders' => [
            'fund_size' => 'large',
            'fund_type' => 'asset-manager',
            'geo_focus' => 'global',
            'work_style' => 'normal',
            'typical_hours' => '50-60',
            'culture' => 'traditional',
            'aliases' => ['schroders plc', 'schroder investment']
        ],
        'pimco' => [
            'fund_size' => 'large',
            'fund_type' => 'asset-manager',
            'geo_focus' => 'global',
            'work_style' => 'intense',
            'typical_hours' => '60-70',
            'culture' => 'fixed-income-focused',
            'aliases' => ['pacific investment management']
        ],
        'wellington' => [
            'fund_size' => 'large',
            'fund_type' => 'asset-manager',
            'geo_focus' => 'global',
            'work_style' => 'normal',
            'typical_hours' => '50-60',
            'culture' => 'collaborative',
            'aliases' => ['wellington management', 'wellington funds']
        ],
        'ares' => [
            'fund_size' => 'large',
            'fund_type' => 'alternative-asset',
            'geo_focus' => 'global',
            'work_style' => 'intense',
            'typical_hours' => '60-70',
            'culture' => 'credit-focused',
            'aliases' => ['ares management', 'ares capital']
        ],
        'oaktree' => [
            'fund_size' => 'large',
            'fund_type' => 'alternative-asset',
            'geo_focus' => 'global',
            'work_style' => 'fluctuates',
            'typical_hours' => '55-65',
            'culture' => 'distressed-focused',
            'aliases' => ['oaktree capital', 'oaktree capital management']
        ]
    ];
    
    /**
     * Seniority patterns for title matching
     */
    private $seniority_patterns = [
        'partner' => [
            'patterns' => ['/\bpartner\b/i', '/\bgeneral partner\b/i', '/\bgp\b/i', '/\bmanaging partner\b/i'],
            'level' => 'partner',
            'years_experience' => '15+'
        ],
        'operating' => [
            'patterns' => ['/operating partner/i', '/operational partner/i', '/portfolio operations/i'],
            'level' => 'operating',
            'years_experience' => '15+'
        ],
        'director-md' => [
            'patterns' => ['/\bdirector\b/i', '/\bmanaging director\b/i', '/\bmd\b/i', '/partner.?track/i'],
            'level' => 'director-md',
            'years_experience' => '10-15'
        ],
        'vp-principal' => [
            'patterns' => ['/\bvp\b/i', '/vice president/i', '/principal/i', '/senior associate/i'],
            'level' => 'vp-principal',
            'years_experience' => '5-10'
        ],
        'associate' => [
            'patterns' => ['/\bassociate\b/i', '/investment professional/i'],
            'level' => 'associate',
            'years_experience' => '2-5'
        ],
        'analyst' => [
            'patterns' => ['/\banalyst\b/i', '/\bjunior\b/i'],
            'level' => 'analyst',
            'years_experience' => '0-2'
        ]
    ];
    
    /**
     * Location to geographic focus mapping
     */
    private $location_mapping = [
        'pan-european' => ['europe', 'european', 'eu', 'multi-country'],
        'uk-ireland' => ['london', 'uk', 'united kingdom', 'manchester', 'edinburgh', 'dublin', 'ireland'],
        'dach' => ['germany', 'austria', 'switzerland', 'frankfurt', 'munich', 'zurich', 'vienna', 'berlin'],
        'nordics' => ['sweden', 'norway', 'denmark', 'finland', 'stockholm', 'copenhagen', 'oslo', 'helsinki'],
        'benelux' => ['netherlands', 'belgium', 'luxembourg', 'amsterdam', 'brussels', 'rotterdam'],
        'southern-europe' => ['spain', 'italy', 'portugal', 'madrid', 'milan', 'barcelona', 'lisbon'],
        'france' => ['paris', 'france', 'lyon', 'marseille'],
        'emerging-eu' => ['poland', 'czech', 'hungary', 'romania', 'warsaw', 'prague', 'budapest'],
        'global' => ['new york', 'hong kong', 'singapore', 'tokyo', 'sydney', 'global', 'international']
    ];
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Enrich job data with PE-specific metadata
     */
    public function enrich_job_data($job_data) {
        // Performance: Check cache first
        $cache_key = $this->get_cache_key($job_data);
        
        // Try to get from in-memory cache
        if (isset($this->enrichment_cache[$cache_key])) {
            $cached = $this->enrichment_cache[$cache_key];
            if (time() - $cached['timestamp'] < $this->cache_ttl) {
                return $cached['data'];
            }
        }
        
        // Try to get from WordPress transient cache
        $transient_key = 'pe_enrich_' . $cache_key;
        $cached_data = get_transient($transient_key);
        if ($cached_data !== false) {
            $this->enrichment_cache[$cache_key] = [
                'data' => $cached_data,
                'timestamp' => time()
            ];
            return $cached_data;
        }
        
        // Extract from existing fields
        $company = strtolower($job_data['company'] ?? '');
        $title = $job_data['title'] ?? '';
        $location = strtolower($job_data['location'] ?? '');
        $description = strtolower($job_data['description'] ?? '');
        
        // 1. Identify PE fund and get metadata
        $fund_data = $this->identify_fund($company, $description);
        if ($fund_data) {
            $job_data['fund_size'] = $fund_data['fund_size'];
            $job_data['fund_type'] = $fund_data['fund_type'];
            $job_data['work_style'] = $fund_data['work_style'];
            $job_data['typical_hours'] = $fund_data['typical_hours'];
            $job_data['fund_geo_focus'] = $fund_data['geo_focus'];
            $job_data['fund_culture'] = $fund_data['culture'];
        }
        
        // 2. Extract seniority level from title
        $seniority = $this->extract_seniority($title);
        if ($seniority) {
            $job_data['seniority_level'] = $seniority['level'];
            $job_data['years_experience'] = $seniority['years_experience'];
        }
        
        // 3. Determine geographic focus from location
        $geo_focus = $this->extract_geo_focus($location);
        if ($geo_focus) {
            $job_data['geo_focus'] = $geo_focus;
        }
        
        // 4. Extract work style indicators from description
        $work_indicators = $this->extract_work_style_from_description($description);
        if ($work_indicators && !isset($job_data['work_style'])) {
            $job_data['work_style'] = $work_indicators['style'];
            $job_data['estimated_hours'] = $work_indicators['hours'];
        }
        
        // 5. Detect deal types and sectors
        $deal_focus = $this->extract_deal_focus($description);
        if ($deal_focus) {
            $job_data['deal_types'] = $deal_focus['types'];
            $job_data['sectors'] = $deal_focus['sectors'];
        }
        
        // 6. Add computed match scores for PE filters
        $job_data['pe_relevance_score'] = $this->calculate_pe_relevance($job_data);
        
        // Cache the enriched data
        $this->enrichment_cache[$cache_key] = [
            'data' => $job_data,
            'timestamp' => time()
        ];
        
        // Store in WordPress transient for persistence
        set_transient($transient_key, $job_data, $this->cache_ttl);
        
        // Clean cache if it gets too large
        if (count($this->enrichment_cache) > 100) {
            $this->clean_cache();
        }
        
        return $job_data;
    }
    
    /**
     * Generate cache key for job data
     */
    private function get_cache_key($job_data) {
        $key_parts = [
            $job_data['id'] ?? '',
            $job_data['company'] ?? '',
            $job_data['title'] ?? ''
        ];
        return md5(implode('_', $key_parts));
    }
    
    /**
     * Clean old cache entries
     */
    private function clean_cache() {
        $now = time();
        foreach ($this->enrichment_cache as $key => $cached) {
            if ($now - $cached['timestamp'] > $this->cache_ttl) {
                unset($this->enrichment_cache[$key]);
            }
        }
        
        // If still too large, keep only 50 most recent
        if (count($this->enrichment_cache) > 50) {
            uasort($this->enrichment_cache, function($a, $b) {
                return $b['timestamp'] - $a['timestamp'];
            });
            $this->enrichment_cache = array_slice($this->enrichment_cache, 0, 50, true);
        }
    }
    
    /**
     * Identify PE fund from company name
     */
    private function identify_fund($company_name, $description = '') {
        $company_lower = strtolower($company_name);
        $desc_lower = strtolower($description);
        $combined = $company_lower . ' ' . $desc_lower;
        
        foreach ($this->pe_fund_database as $fund_key => $fund_data) {
            // Check main name
            if (strpos($company_lower, $fund_key) !== false) {
                return $fund_data;
            }
            
            // Check aliases
            foreach ($fund_data['aliases'] as $alias) {
                if (strpos($company_lower, strtolower($alias)) !== false) {
                    return $fund_data;
                }
            }
            
            // Check description for fund mentions
            if (strpos($desc_lower, $fund_key) !== false) {
                return $fund_data;
            }
        }
        
        return null;
    }
    
    /**
     * Extract seniority level from job title
     */
    private function extract_seniority($title) {
        foreach ($this->seniority_patterns as $key => $pattern_data) {
            foreach ($pattern_data['patterns'] as $pattern) {
                if (preg_match($pattern, $title)) {
                    return [
                        'level' => $pattern_data['level'],
                        'years_experience' => $pattern_data['years_experience']
                    ];
                }
            }
        }
        return null;
    }
    
    /**
     * Extract geographic focus from location
     */
    private function extract_geo_focus($location) {
        $location_lower = strtolower($location);
        
        foreach ($this->location_mapping as $region => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($location_lower, $keyword) !== false) {
                    return $region;
                }
            }
        }
        
        return 'other';
    }
    
    /**
     * Extract work style indicators from description
     */
    private function extract_work_style_from_description($description) {
        $indicators = [
            'intense' => [
                'keywords' => ['fast-paced', 'demanding', 'high-pressure', 'ambitious', 'driven', 'intensive'],
                'hours' => '70+',
                'score' => 0
            ],
            'fluctuates' => [
                'keywords' => ['deal-driven', 'project-based', 'variable', 'dynamic', 'flexible hours'],
                'hours' => '55-70',
                'score' => 0
            ],
            'normal' => [
                'keywords' => ['work-life balance', 'flexible', 'family-friendly', 'sustainable', 'balanced'],
                'hours' => '50-60',
                'score' => 0
            ]
        ];
        
        foreach ($indicators as $style => &$data) {
            foreach ($data['keywords'] as $keyword) {
                if (strpos($description, $keyword) !== false) {
                    $data['score']++;
                }
            }
        }
        
        // Determine most likely work style
        $max_score = 0;
        $selected_style = 'fluctuates'; // default
        
        foreach ($indicators as $style => $data) {
            if ($data['score'] > $max_score) {
                $max_score = $data['score'];
                $selected_style = $style;
            }
        }
        
        return [
            'style' => $selected_style,
            'hours' => $indicators[$selected_style]['hours']
        ];
    }
    
    /**
     * Extract deal focus and sectors
     */
    private function extract_deal_focus($description) {
        $deal_types = [];
        $sectors = [];
        
        // Deal type patterns
        $deal_patterns = [
            'buyout' => '/buyout|lbo|leveraged/i',
            'growth' => '/growth equity|growth capital|expansion/i',
            'venture' => '/venture|vc|early stage/i',
            'distressed' => '/distressed|turnaround|restructuring/i',
            'infrastructure' => '/infrastructure|energy transition/i',
            'real_estate' => '/real estate|property|reit/i'
        ];
        
        // Sector patterns  
        $sector_patterns = [
            'technology' => '/technology|software|saas|tech/i',
            'healthcare' => '/healthcare|pharma|medical|biotech/i',
            'consumer' => '/consumer|retail|fmcg|brands/i',
            'industrials' => '/industrial|manufacturing|logistics/i',
            'financial_services' => '/financial services|fintech|insurance/i',
            'energy' => '/energy|renewables|oil|gas/i'
        ];
        
        foreach ($deal_patterns as $type => $pattern) {
            if (preg_match($pattern, $description)) {
                $deal_types[] = $type;
            }
        }
        
        foreach ($sector_patterns as $sector => $pattern) {
            if (preg_match($pattern, $description)) {
                $sectors[] = $sector;
            }
        }
        
        return [
            'types' => $deal_types,
            'sectors' => $sectors
        ];
    }
    
    /**
     * Calculate PE relevance score
     */
    private function calculate_pe_relevance($job_data) {
        $score = 0;
        $max_score = 100;
        
        // Fund identification (40 points)
        if (isset($job_data['fund_size'])) {
            $score += 40;
        }
        
        // Seniority match (20 points)
        if (isset($job_data['seniority_level'])) {
            $score += 20;
        }
        
        // Location/geo focus (15 points)
        if (isset($job_data['geo_focus']) && $job_data['geo_focus'] !== 'other') {
            $score += 15;
        }
        
        // Work style identified (10 points)
        if (isset($job_data['work_style'])) {
            $score += 10;
        }
        
        // Deal types/sectors (15 points)
        if (!empty($job_data['deal_types']) || !empty($job_data['sectors'])) {
            $score += 15;
        }
        
        return min($score, $max_score);
    }
    
    /**
     * Save enriched data as post meta
     */
    public function save_enriched_meta($post_id, $enriched_data) {
        // PE-specific meta fields
        $pe_fields = [
            'fund_size', 'fund_type', 'work_style', 'typical_hours',
            'fund_geo_focus', 'fund_culture', 'seniority_level',
            'years_experience', 'geo_focus', 'estimated_hours',
            'deal_types', 'sectors', 'pe_relevance_score'
        ];
        
        foreach ($pe_fields as $field) {
            if (isset($enriched_data[$field])) {
                $value = $enriched_data[$field];
                if (is_array($value)) {
                    $value = json_encode($value);
                }
                update_post_meta($post_id, 'sffc_pe_' . $field, $value);
            }
        }
    }
    
    /**
     * Get enriched data for a job post
     */
    public function get_enriched_data($post_id) {
        $pe_data = [];
        
        // Get all PE meta fields
        $meta = get_post_meta($post_id);
        foreach ($meta as $key => $value) {
            if (strpos($key, 'sffc_pe_') === 0) {
                $field_name = str_replace('sffc_pe_', '', $key);
                $pe_data[$field_name] = maybe_unserialize($value[0]);
            }
        }
        
        return $pe_data;
    }
}

// Initialize
SFFC_PE_Data_Enrichment::get_instance();