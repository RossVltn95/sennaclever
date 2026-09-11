<?php

/**
 * Intelligent Salary Estimator V3 - Research-Based
 * 
 * Uses real market data and comprehensive analysis for accurate salary estimation
 * Based on actual salary surveys and market research
 * 
 * @package MENA Careers
 * @since 5.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Intelligent_Salary_Estimator_V3
{

    private static $instance = null;

    /**
     * Real market salary data by location and level (2024 data)
     * Based on actual salary surveys from Robert Half, Michael Page, Hays, etc.
     */
    private $market_salary_data = [
        // UK Market (GBP)
        'uk' => [
            'london' => [
                'entry' => ['min' => 25000, 'max' => 35000],
                'junior' => ['min' => 30000, 'max' => 45000],
                'mid' => ['min' => 40000, 'max' => 65000],
                'senior' => ['min' => 55000, 'max' => 85000],
                'lead' => ['min' => 70000, 'max' => 100000],
                'manager' => ['min' => 65000, 'max' => 95000],
                'senior_manager' => ['min' => 80000, 'max' => 120000],
                'associate_director' => ['min' => 90000, 'max' => 130000],
                'director' => ['min' => 100000, 'max' => 160000],
                'senior_director' => ['min' => 130000, 'max' => 200000],
                'vp' => ['min' => 150000, 'max' => 250000],
                'svp' => ['min' => 200000, 'max' => 350000],
                'c_level' => ['min' => 250000, 'max' => 500000]
            ],
            'birmingham' => [
                'entry' => ['min' => 22000, 'max' => 30000],
                'junior' => ['min' => 26000, 'max' => 38000],
                'mid' => ['min' => 35000, 'max' => 55000],
                'senior' => ['min' => 48000, 'max' => 70000],
                'lead' => ['min' => 60000, 'max' => 85000],
                'manager' => ['min' => 55000, 'max' => 80000],
                'senior_manager' => ['min' => 70000, 'max' => 100000],
                'associate_director' => ['min' => 75000, 'max' => 110000],
                'director' => ['min' => 85000, 'max' => 135000],
                'senior_director' => ['min' => 110000, 'max' => 170000],
                'vp' => ['min' => 130000, 'max' => 200000],
                'svp' => ['min' => 170000, 'max' => 280000],
                'c_level' => ['min' => 200000, 'max' => 400000]
            ],
            'manchester' => [
                'entry' => ['min' => 22000, 'max' => 32000],
                'junior' => ['min' => 27000, 'max' => 40000],
                'mid' => ['min' => 36000, 'max' => 58000],
                'senior' => ['min' => 50000, 'max' => 75000],
                'lead' => ['min' => 62000, 'max' => 90000],
                'manager' => ['min' => 58000, 'max' => 85000],
                'senior_manager' => ['min' => 72000, 'max' => 105000],
                'associate_director' => ['min' => 80000, 'max' => 115000],
                'director' => ['min' => 90000, 'max' => 140000],
                'senior_director' => ['min' => 115000, 'max' => 175000],
                'vp' => ['min' => 135000, 'max' => 210000],
                'svp' => ['min' => 175000, 'max' => 290000],
                'c_level' => ['min' => 210000, 'max' => 420000]
            ],
            'edinburgh' => [
                'entry' => ['min' => 23000, 'max' => 32000],
                'junior' => ['min' => 28000, 'max' => 40000],
                'mid' => ['min' => 37000, 'max' => 58000],
                'senior' => ['min' => 50000, 'max' => 75000],
                'lead' => ['min' => 62000, 'max' => 88000],
                'manager' => ['min' => 58000, 'max' => 83000],
                'senior_manager' => ['min' => 72000, 'max' => 103000],
                'associate_director' => ['min' => 78000, 'max' => 112000],
                'director' => ['min' => 88000, 'max' => 138000],
                'senior_director' => ['min' => 112000, 'max' => 172000],
                'vp' => ['min' => 132000, 'max' => 205000],
                'svp' => ['min' => 172000, 'max' => 285000],
                'c_level' => ['min' => 205000, 'max' => 410000]
            ],
            'default' => [ // Other UK cities
                'entry' => ['min' => 20000, 'max' => 28000],
                'junior' => ['min' => 25000, 'max' => 36000],
                'mid' => ['min' => 32000, 'max' => 52000],
                'senior' => ['min' => 45000, 'max' => 68000],
                'lead' => ['min' => 55000, 'max' => 80000],
                'manager' => ['min' => 52000, 'max' => 75000],
                'senior_manager' => ['min' => 65000, 'max' => 95000],
                'associate_director' => ['min' => 70000, 'max' => 105000],
                'director' => ['min' => 80000, 'max' => 125000],
                'senior_director' => ['min' => 100000, 'max' => 160000],
                'vp' => ['min' => 120000, 'max' => 190000],
                'svp' => ['min' => 160000, 'max' => 260000],
                'c_level' => ['min' => 190000, 'max' => 380000]
            ]
        ],

        // US Market (USD)
        'us' => [
            'new_york' => [
                'entry' => ['min' => 50000, 'max' => 70000],
                'junior' => ['min' => 65000, 'max' => 90000],
                'mid' => ['min' => 85000, 'max' => 130000],
                'senior' => ['min' => 120000, 'max' => 180000],
                'lead' => ['min' => 150000, 'max' => 220000],
                'manager' => ['min' => 140000, 'max' => 200000],
                'senior_manager' => ['min' => 170000, 'max' => 250000],
                'associate_director' => ['min' => 190000, 'max' => 280000],
                'director' => ['min' => 220000, 'max' => 350000],
                'senior_director' => ['min' => 280000, 'max' => 450000],
                'vp' => ['min' => 350000, 'max' => 550000],
                'svp' => ['min' => 450000, 'max' => 750000],
                'c_level' => ['min' => 600000, 'max' => 1500000]
            ],
            'san_francisco' => [
                'entry' => ['min' => 55000, 'max' => 75000],
                'junior' => ['min' => 70000, 'max' => 100000],
                'mid' => ['min' => 95000, 'max' => 145000],
                'senior' => ['min' => 135000, 'max' => 200000],
                'lead' => ['min' => 165000, 'max' => 240000],
                'manager' => ['min' => 155000, 'max' => 220000],
                'senior_manager' => ['min' => 185000, 'max' => 270000],
                'associate_director' => ['min' => 210000, 'max' => 300000],
                'director' => ['min' => 240000, 'max' => 380000],
                'senior_director' => ['min' => 300000, 'max' => 480000],
                'vp' => ['min' => 380000, 'max' => 600000],
                'svp' => ['min' => 480000, 'max' => 800000],
                'c_level' => ['min' => 650000, 'max' => 1600000]
            ],
            'chicago' => [
                'entry' => ['min' => 45000, 'max' => 62000],
                'junior' => ['min' => 58000, 'max' => 80000],
                'mid' => ['min' => 75000, 'max' => 115000],
                'senior' => ['min' => 105000, 'max' => 160000],
                'lead' => ['min' => 130000, 'max' => 190000],
                'manager' => ['min' => 125000, 'max' => 180000],
                'senior_manager' => ['min' => 150000, 'max' => 220000],
                'associate_director' => ['min' => 170000, 'max' => 250000],
                'director' => ['min' => 195000, 'max' => 310000],
                'senior_director' => ['min' => 250000, 'max' => 400000],
                'vp' => ['min' => 310000, 'max' => 490000],
                'svp' => ['min' => 400000, 'max' => 670000],
                'c_level' => ['min' => 540000, 'max' => 1350000]
            ],
            'default' => [ // Other US cities
                'entry' => ['min' => 40000, 'max' => 58000],
                'junior' => ['min' => 52000, 'max' => 75000],
                'mid' => ['min' => 68000, 'max' => 105000],
                'senior' => ['min' => 95000, 'max' => 145000],
                'lead' => ['min' => 115000, 'max' => 170000],
                'manager' => ['min' => 110000, 'max' => 160000],
                'senior_manager' => ['min' => 135000, 'max' => 200000],
                'associate_director' => ['min' => 155000, 'max' => 230000],
                'director' => ['min' => 175000, 'max' => 280000],
                'senior_director' => ['min' => 225000, 'max' => 360000],
                'vp' => ['min' => 280000, 'max' => 450000],
                'svp' => ['min' => 360000, 'max' => 600000],
                'c_level' => ['min' => 480000, 'max' => 1200000]
            ]
        ],

        // EU Market (EUR)
        'eu' => [
            'frankfurt' => [
                'entry' => ['min' => 40000, 'max' => 55000],
                'junior' => ['min' => 50000, 'max' => 70000],
                'mid' => ['min' => 65000, 'max' => 95000],
                'senior' => ['min' => 85000, 'max' => 125000],
                'lead' => ['min' => 105000, 'max' => 150000],
                'manager' => ['min' => 100000, 'max' => 140000],
                'senior_manager' => ['min' => 120000, 'max' => 170000],
                'associate_director' => ['min' => 135000, 'max' => 195000],
                'director' => ['min' => 155000, 'max' => 240000],
                'senior_director' => ['min' => 195000, 'max' => 310000],
                'vp' => ['min' => 240000, 'max' => 380000],
                'svp' => ['min' => 310000, 'max' => 520000],
                'c_level' => ['min' => 420000, 'max' => 850000]
            ],
            'paris' => [
                'entry' => ['min' => 35000, 'max' => 48000],
                'junior' => ['min' => 45000, 'max' => 62000],
                'mid' => ['min' => 58000, 'max' => 85000],
                'senior' => ['min' => 75000, 'max' => 110000],
                'lead' => ['min' => 92000, 'max' => 135000],
                'manager' => ['min' => 88000, 'max' => 125000],
                'senior_manager' => ['min' => 105000, 'max' => 155000],
                'associate_director' => ['min' => 120000, 'max' => 175000],
                'director' => ['min' => 140000, 'max' => 215000],
                'senior_director' => ['min' => 175000, 'max' => 280000],
                'vp' => ['min' => 215000, 'max' => 345000],
                'svp' => ['min' => 280000, 'max' => 470000],
                'c_level' => ['min' => 380000, 'max' => 780000]
            ],
            'default' => [ // Other EU cities
                'entry' => ['min' => 30000, 'max' => 42000],
                'junior' => ['min' => 38000, 'max' => 55000],
                'mid' => ['min' => 50000, 'max' => 75000],
                'senior' => ['min' => 65000, 'max' => 95000],
                'lead' => ['min' => 80000, 'max' => 115000],
                'manager' => ['min' => 75000, 'max' => 110000],
                'senior_manager' => ['min' => 90000, 'max' => 135000],
                'associate_director' => ['min' => 105000, 'max' => 155000],
                'director' => ['min' => 120000, 'max' => 190000],
                'senior_director' => ['min' => 155000, 'max' => 245000],
                'vp' => ['min' => 190000, 'max' => 305000],
                'svp' => ['min' => 245000, 'max' => 410000],
                'c_level' => ['min' => 330000, 'max' => 680000]
            ]
        ]
    ];

    /**
     * Industry multipliers based on real market data
     */
    private $industry_multipliers = [
        // High-paying industries
        'investment banking' => 1.4,
        'private equity' => 1.5,
        'hedge fund' => 1.6,
        'venture capital' => 1.3,
        'management consulting' => 1.25,
        'law firm' => 1.3,
        'technology' => 1.2,
        'fintech' => 1.15,
        'pharmaceutical' => 1.15,

        // Standard industries
        'asset management' => 1.1,
        'corporate banking' => 1.0,
        'insurance' => 0.95,
        'accounting' => 0.9,
        'retail banking' => 0.85,
        'real estate' => 1.0,
        'manufacturing' => 0.9,
        'retail' => 0.8,

        // Lower-paying sectors
        'government' => 0.75,
        'non-profit' => 0.65,
        'education' => 0.7,
        'hospitality' => 0.7
    ];

    /**
     * Currency symbols
     */
    private $currency_symbols = [
        'GBP' => '£',  // GBP first as it's our default
        'USD' => '$',
        'EUR' => '€',
        'CAD' => 'C$',
        'AUD' => 'A$',
        'SGD' => 'S$',
        'HKD' => 'HK$',
        'JPY' => '¥',
        'CHF' => 'CHF',
        'AED' => 'AED',
        'SAR' => 'SAR',
        'INR' => '₹',
        'CNY' => '¥'
    ];

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Main estimation function
     */
    public function estimate_salary($job_data)
    {
        // 1. Detect location and currency - THIS IS CRITICAL
        $location_info = $this->detect_location_and_currency($job_data['location'] ?? '');

        // Log detection for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'SFFC Salary Estimator V3: Location="%s" => Market=%s, City=%s, Currency=%s (Confidence=%.2f)',
                $job_data['location'] ?? 'N/A',
                $location_info['market'],
                $location_info['city'],
                $location_info['currency'],
                $location_info['confidence'] ?? 0
            ));
        }

        // 2. Detect seniority level from title
        $seniority = $this->detect_seniority_level($job_data['title'] ?? '');

        // 3. Detect industry
        $industry = $this->detect_industry(
            $job_data['company'] ?? '',
            $job_data['title'] ?? '',
            $job_data['description'] ?? ''
        );

        $gulf_finance_salary = $this->get_gulf_finance_benchmark_salary($job_data, $location_info, $seniority);
        if (!empty($gulf_finance_salary)) {
            $result = $this->format_salary_display($gulf_finance_salary, $location_info['currency']);
            $result['confidence'] = 0.88;
            $result['detected_location'] = $location_info;
            $result['source'] = $gulf_finance_salary['source'] ?? 'Gulf finance benchmark';
            $result['salary_period'] = 'monthly';
            return $result;
        }

        // 4. Get base salary from market data IN THE DETECTED CURRENCY
        $base_salary = $this->get_market_based_salary(
            $location_info['market'],
            $location_info['city'],
            $seniority
        );

        // 5. Apply industry multiplier
        $adjusted_salary = $this->apply_industry_adjustment($base_salary, $industry);

        // 6. Apply skill premiums if applicable
        $final_salary = $this->apply_skill_premiums($adjusted_salary, $job_data);

        // 7. Format for display WITH THE CORRECT CURRENCY
        $result = $this->format_salary_display($final_salary, $location_info['currency']);

        // Add confidence score to result
        $result['confidence'] = $location_info['confidence'] ?? 0.5;
        $result['detected_location'] = $location_info;

        return $result;
    }

    /**
     * Detect location and currency from location string
     * CRITICAL: This determines the base currency for ALL calculations
     */
    private function detect_location_and_currency($location_string)
    {
        $location_lower = strtolower(trim($location_string));

        // Priority 1: Check for explicit currency indicators in the string
        if (preg_match('/£|gbp|pounds?|sterling/i', $location_string)) {
            // Definitely UK
            $uk_cities = [
                'london',
                'birmingham',
                'manchester',
                'edinburgh',
                'glasgow',
                'leeds',
                'bristol',
                'liverpool',
                'newcastle',
                'nottingham',
                'sheffield',
                'cardiff',
                'belfast',
                'reading',
                'oxford',
                'cambridge',
                'southampton',
                'leicester',
                'coventry',
                'bradford',
                'bournemouth',
                'norwich',
                'york',
                'aberdeen'
            ];

            foreach ($uk_cities as $city) {
                if (strpos($location_lower, $city) !== false) {
                    return [
                        'market' => 'uk',
                        'city' => $city,
                        'currency' => 'GBP',
                        'confidence' => 1.0
                    ];
                }
            }

            return [
                'market' => 'uk',
                'city' => 'default',
                'currency' => 'GBP',
                'confidence' => 1.0
            ];
        }

        if (preg_match('/\$|usd|dollars?/i', $location_string) && !preg_match('/singapore|hong kong|australia|canada/i', $location_string)) {
            // US Dollar
            return [
                'market' => 'us',
                'city' => 'default',
                'currency' => 'USD',
                'confidence' => 1.0
            ];
        }

        if (preg_match('/€|eur|euros?/i', $location_string)) {
            // Euro
            return [
                'market' => 'eu',
                'city' => 'default',
                'currency' => 'EUR',
                'confidence' => 1.0
            ];
        }

        // Priority 2: UK detection - Check cities first, then regions
        $uk_cities = [
            'london',
            'birmingham',
            'manchester',
            'edinburgh',
            'glasgow',
            'leeds',
            'bristol',
            'liverpool',
            'newcastle',
            'nottingham',
            'sheffield',
            'cardiff',
            'belfast',
            'reading',
            'oxford',
            'cambridge',
            'southampton',
            'leicester',
            'coventry',
            'bradford',
            'bournemouth',
            'norwich',
            'york',
            'aberdeen',
            'portsmouth',
            'plymouth',
            'wolverhampton',
            'derby',
            'swansea',
            'barnsley',
            'sunderland',
            'warrington',
            'huddersfield',
            'peterborough',
            'brighton',
            'dundee',
            'east london',
            'west london',
            'central london',
            'greater london'
        ];

        foreach ($uk_cities as $city) {
            if (strpos($location_lower, $city) !== false) {
                // Map to main city for data lookup
                $mapped_city = $city;
                if (strpos($city, 'london') !== false) $mapped_city = 'london';

                return [
                    'market' => 'uk',
                    'city' => in_array($mapped_city, ['london', 'birmingham', 'manchester', 'edinburgh']) ? $mapped_city : 'default',
                    'currency' => 'GBP',
                    'confidence' => 0.95
                ];
            }
        }

        // Check for UK regions and country indicators
        $uk_indicators = [
            'united kingdom',
            'uk',
            'u.k.',
            'u.k',
            'gb',
            'great britain',
            'britain',
            'england',
            'scotland',
            'wales',
            'northern ireland',
            'midlands',
            'west midlands',
            'east midlands',
            'north west',
            'north east',
            'south west',
            'south east',
            'yorkshire',
            'london area',
            'home counties',
            'english',
            'scottish',
            'welsh',
            'british'
        ];

        foreach ($uk_indicators as $indicator) {
            if (preg_match('/\b' . preg_quote($indicator, '/') . '\b/i', $location_lower)) {
                return [
                    'market' => 'uk',
                    'city' => 'default',
                    'currency' => 'GBP',
                    'confidence' => 0.9
                ];
            }
        }

        // US detection
        $us_cities = [
            'new york',
            'san francisco',
            'chicago',
            'boston',
            'seattle',
            'los angeles',
            'washington',
            'austin',
            'denver',
            'atlanta',
            'miami',
            'dallas',
            'houston'
        ];
        foreach ($us_cities as $city) {
            if (
                strpos($location_lower, str_replace(' ', '', $city)) !== false ||
                strpos($location_lower, $city) !== false
            ) {
                return [
                    'market' => 'us',
                    'city' => str_replace(' ', '_', $city),
                    'currency' => 'USD'
                ];
            }
        }

        // Check for US state codes or country indicators
        if (
            preg_match('/\b(usa|us|united states|america)\b/i', $location_lower) ||
            preg_match('/\b(ny|ca|tx|fl|il|ma|wa|co|ga)\b/i', $location_lower)
        ) {
            return [
                'market' => 'us',
                'city' => 'default',
                'currency' => 'USD'
            ];
        }

        // EU detection
        $eu_cities = [
            'frankfurt',
            'paris',
            'amsterdam',
            'brussels',
            'milan',
            'madrid',
            'barcelona',
            'munich',
            'berlin',
            'vienna',
            'dublin',
            'luxembourg'
        ];
        foreach ($eu_cities as $city) {
            if (strpos($location_lower, $city) !== false) {
                return [
                    'market' => 'eu',
                    'city' => $city,
                    'currency' => 'EUR'
                ];
            }
        }

        // Check for EU country indicators
        if (preg_match('/\b(germany|france|netherlands|belgium|italy|spain|austria|ireland)\b/i', $location_lower)) {
            return [
                'market' => 'eu',
                'city' => 'default',
                'currency' => 'EUR'
            ];
        }

        // Priority 4: Additional location checks
        // Check for other markets before defaulting

        // Singapore
        if (preg_match('/\bsingapore\b/i', $location_lower)) {
            return [
                'market' => 'asia',
                'city' => 'singapore',
                'currency' => 'SGD',
                'confidence' => 0.9
            ];
        }

        // Hong Kong
        if (preg_match('/\bhong\s*kong\b/i', $location_lower)) {
            return [
                'market' => 'asia',
                'city' => 'hong_kong',
                'currency' => 'HKD',
                'confidence' => 0.9
            ];
        }

        // Australia
        if (preg_match('/\b(australia|sydney|melbourne|brisbane|perth|adelaide)\b/i', $location_lower)) {
            return [
                'market' => 'asia',
                'city' => 'sydney',
                'currency' => 'AUD',
                'confidence' => 0.9
            ];
        }

        // Canada
        if (preg_match('/\b(canada|toronto|vancouver|montreal|calgary|ottawa)\b/i', $location_lower)) {
            return [
                'market' => 'us', // Use US market data with CAD currency
                'city' => 'default',
                'currency' => 'CAD',
                'confidence' => 0.9
            ];
        }

        if (preg_match('/\b(dubai|abu dhabi|uae|united arab emirates)\b/i', $location_lower)) {
            return [
                'market' => 'gulf',
                'city' => 'dubai',
                'currency' => 'AED',
                'confidence' => 0.95
            ];
        }

        if (preg_match('/\b(saudi|riyadh|jeddah|ksa|kingdom of saudi arabia)\b/i', $location_lower)) {
            return [
                'market' => 'gulf',
                'city' => 'riyadh',
                'currency' => 'SAR',
                'confidence' => 0.95
            ];
        }

        if (preg_match('/\b(qatar|doha)\b/i', $location_lower)) {
            return [
                'market' => 'gulf',
                'city' => 'doha',
                'currency' => 'QAR',
                'confidence' => 0.85
            ];
        }

        if (preg_match('/\b(kuwait|bahrain|oman|muscat)\b/i', $location_lower)) {
            return [
                'market' => 'gulf',
                'city' => 'default',
                'currency' => 'USD',
                'confidence' => 0.65
            ];
        }

        // Switzerland
        if (preg_match('/\b(switzerland|swiss|zurich|geneva|basel|bern)\b/i', $location_lower)) {
            return [
                'market' => 'eu',
                'city' => 'default',
                'currency' => 'CHF',
                'confidence' => 0.9
            ];
        }

        // Japan
        if (preg_match('/\b(japan|tokyo|osaka|yokohama)\b/i', $location_lower)) {
            return [
                'market' => 'asia',
                'city' => 'tokyo',
                'currency' => 'JPY',
                'confidence' => 0.9
            ];
        }

        // Remote/Global - Default to GBP as per requirement
        if (preg_match('/\b(remote|global|worldwide|international)\b/i', $location_lower)) {
            // For remote, default to UK market with GBP
            return [
                'market' => 'uk',
                'city' => 'default',
                'currency' => 'GBP',
                'confidence' => 0.5
            ];
        }

        // Last resort: Check if location string is empty or very short
        if (strlen($location_lower) < 3) {
            // No location info - default to GBP
            return [
                'market' => 'uk',
                'city' => 'default',
                'currency' => 'GBP',
                'confidence' => 0.3
            ];
        }

        // Final default - Use GBP as the default currency
        // If we reach here, we couldn't identify the location
        return [
            'market' => 'uk',
            'city' => 'default',
            'currency' => 'GBP',
            'confidence' => 0.4
        ];
    }

    /**
     * Detect seniority level from job title
     */
    private function detect_seniority_level($title)
    {
        $title_lower = strtolower($title);

        // Check for C-level
        if (preg_match('/\b(ceo|cfo|cto|coo|cio|cmo|cro|chief\s+\w+\s+officer)\b/', $title_lower)) {
            return 'c_level';
        }

        // Check for SVP/EVP
        if (preg_match('/\b(svp|evp|senior\s+vice\s+president|executive\s+vice\s+president)\b/', $title_lower)) {
            return 'svp';
        }

        // Check for VP
        if (
            preg_match('/\b(vp|vice\s+president)\b/', $title_lower) &&
            !preg_match('/\b(assistant|associate)\s+(vp|vice\s+president)\b/', $title_lower)
        ) {
            return 'vp';
        }

        // Check for Senior Director
        if (preg_match('/\b(senior|executive|managing)\s+director\b/', $title_lower)) {
            return 'senior_director';
        }

        // Check for Associate/Assistant Director (comes before Director check)
        if (preg_match('/\b(associate|assistant|deputy)\s+director\b/', $title_lower)) {
            return 'associate_director';
        }

        // Check for Director
        if (preg_match('/\bdirector\b/', $title_lower) || preg_match('/\bhead\s+of\b/', $title_lower)) {
            return 'director';
        }

        // Check for Senior Manager
        if (preg_match('/\b(senior|principal|lead)\s+manager\b/', $title_lower)) {
            return 'senior_manager';
        }

        // Check for Manager
        if (
            preg_match('/\bmanager\b/', $title_lower) &&
            !preg_match('/\b(junior|assistant|associate)\s+manager\b/', $title_lower)
        ) {
            return 'manager';
        }

        // Check for Lead/Senior IC
        if (
            preg_match('/\b(lead|senior|sr\.?|principal)\s+/', $title_lower) ||
            preg_match('/\sIII\b/', $title)
        ) {
            return 'senior';
        }

        // Check for Mid-level
        if (
            preg_match('/\b(analyst|associate|consultant|specialist|developer|engineer)\b/', $title_lower) &&
            !preg_match('/\b(junior|jr|entry|graduate|intern|trainee)\b/', $title_lower)
        ) {
            return 'mid';
        }

        // Check for Junior
        if (preg_match('/\b(junior|jr\.?|graduate|entry\s+level)\b/', $title_lower)) {
            return 'junior';
        }

        // Check for Entry level
        if (preg_match('/\b(intern|trainee|apprentice)\b/', $title_lower)) {
            return 'entry';
        }

        // Default to mid-level
        return 'mid';
    }

    /**
     * Detect industry from company and job details
     */
    private function detect_industry($company, $title, $description)
    {
        $combined = strtolower($company . ' ' . $title . ' ' . $description);

        // Check for specific industries
        if (preg_match('/\b(investment\s+bank|ib|bulge\s+bracket|goldman|morgan\s+stanley|jp\s?morgan)\b/', $combined)) {
            return 'investment banking';
        }
        if (preg_match('/\b(private\s+equity|pe\s+fund|blackstone|kkr|apollo|carlyle)\b/', $combined)) {
            return 'private equity';
        }
        if (preg_match('/\b(hedge\s+fund|citadel|bridgewater|renaissance|two\s+sigma)\b/', $combined)) {
            return 'hedge fund';
        }
        if (preg_match('/\b(venture\s+capital|vc\s+fund|sequoia|andreessen)\b/', $combined)) {
            return 'venture capital';
        }
        if (preg_match('/\b(mckinsey|bain|bcg|consulting|advisory)\b/', $combined)) {
            return 'management consulting';
        }
        if (preg_match('/\b(fintech|payments|blockchain|crypto)\b/', $combined)) {
            return 'fintech';
        }
        if (preg_match('/\b(google|amazon|apple|microsoft|meta|facebook|netflix)\b/', $combined)) {
            return 'technology';
        }

        // Default to corporate banking
        return 'corporate banking';
    }

    private function get_gulf_finance_benchmark_salary($job_data, $location_info, $seniority)
    {
        if (($location_info['market'] ?? '') !== 'gulf') {
            return null;
        }

        $city = $location_info['city'] ?? '';
        if (!in_array($city, ['dubai', 'riyadh'], true)) {
            return null;
        }

        $text = strtolower(trim(implode(' ', [
            (string) ($job_data['title'] ?? ''),
            (string) ($job_data['seniority'] ?? ''),
            (string) ($job_data['sector'] ?? ''),
            (string) ($job_data['company'] ?? ''),
            (string) ($job_data['description'] ?? ''),
        ])));

        if (!preg_match('/\b(finance|accounting|fp&a|treasury|investment|bank|banking|private equity|private credit|asset management|risk|compliance|fund|portfolio|m&a|capital markets|corporate development)\b/i', $text)) {
            return null;
        }

        $benchmarks = [
            'dubai' => [
                'source' => 'Charterhouse UAE 2026 and Michael Page UAE 2026',
                'families' => [
                    'investment_banking' => [
                        'analyst' => [20000, 40000], 'associate' => [30000, 55000], 'vp' => [55000, 80000],
                        'director' => [70000, 110000], 'md' => [95000, 170000],
                    ],
                    'private_markets' => [
                        'analyst' => [20000, 40000], 'associate' => [35000, 60000], 'manager' => [45000, 82000],
                        'principal' => [55000, 90000], 'director' => [72000, 135000], 'md' => [90000, 170000], 'cio' => [100000, 225000],
                    ],
                    'corporate_banking' => [
                        'analyst' => [15000, 25000], 'associate' => [22000, 30000], 'manager' => [30000, 50000],
                        'vp' => [45000, 60000], 'director' => [70000, 90000], 'md' => [80000, 120000],
                    ],
                    'risk' => [
                        'analyst' => [15000, 30000], 'associate' => [20000, 35000], 'manager' => [40000, 55000],
                        'director' => [50000, 90000], 'cro' => [70000, 180000],
                    ],
                    'compliance' => [
                        'analyst' => [12000, 30000], 'associate' => [20000, 35000], 'manager' => [35000, 75000],
                        'director' => [45000, 120000], 'cco' => [60000, 120000],
                    ],
                    'finance_accounting' => [
                        'analyst' => [18000, 28000], 'associate' => [18000, 28000], 'manager' => [25000, 45000],
                        'controller' => [35000, 55000], 'director' => [50000, 100000], 'cfo' => [65000, 200000],
                    ],
                    'treasury' => [
                        'analyst' => [20000, 30000], 'manager' => [35000, 55000], 'director' => [50000, 90000], 'head' => [50000, 90000],
                    ],
                ],
            ],
            'riyadh' => [
                'source' => 'Hays Saudi Arabia Salary Guide 2026',
                'families' => [
                    'investment_banking' => [
                        'analyst' => [17000, 28000], 'associate' => [30000, 50000], 'vp' => [55000, 75000],
                        'svp' => [75000, 100000], 'director' => [80000, 120000], 'md' => [100000, 150000], 'cio' => [120000, 180000],
                    ],
                    'private_markets' => [
                        'analyst' => [17000, 28000], 'associate' => [30000, 50000], 'manager' => [35000, 70000],
                        'vp' => [55000, 75000], 'director' => [80000, 120000], 'md' => [100000, 150000], 'cio' => [120000, 180000],
                    ],
                    'risk' => [
                        'analyst' => [18000, 28000], 'associate' => [25000, 40000], 'manager' => [40000, 60000],
                        'director' => [55000, 80000], 'cro' => [75000, 120000],
                    ],
                    'compliance' => [
                        'analyst' => [18000, 28000], 'associate' => [25000, 40000], 'manager' => [40000, 60000],
                        'director' => [55000, 85000], 'cco' => [65000, 120000],
                    ],
                    'finance_accounting' => [
                        'analyst' => [15000, 25000], 'accountant' => [10000, 20000], 'manager' => [25000, 40000],
                        'controller' => [35000, 50000], 'director' => [55000, 80000], 'cfo' => [85000, 140000],
                    ],
                    'treasury' => [
                        'analyst' => [18000, 30000], 'manager' => [35000, 55000], 'director' => [50000, 80000], 'head' => [50000, 80000],
                    ],
                ],
            ],
        ];

        $family = $this->detect_gulf_finance_family($text);
        $level = $this->detect_gulf_finance_level($text, $seniority);
        $table = $benchmarks[$city]['families'][$family] ?? null;
        if (empty($table)) {
            return null;
        }

        $band = $table[$level] ?? null;
        if (empty($band)) {
            $band = $this->infer_gulf_finance_salary_band($table, $level);
        }
        if (empty($band)) {
            return null;
        }

        return [
            'min' => (int) $band[0],
            'max' => (int) $band[1],
            'source' => $benchmarks[$city]['source'],
        ];
    }

    private function detect_gulf_finance_family($text)
    {
        if (preg_match('/\b(private equity|private credit|private markets|venture capital|asset management|portfolio|fund manager|investment\s+(analyst|associate|manager|director)|family office|sovereign wealth)\b/i', $text)) {
            return 'private_markets';
        }
        if (preg_match('/\b(investment banking|m&a|mergers|acquisitions|dcm|ecm|leveraged finance|capital markets|corporate finance|debt advisory|restructuring)\b/i', $text)) {
            return 'investment_banking';
        }
        if (preg_match('/\b(corporate banking|relationship manager|wholesale banking|commercial banking)\b/i', $text)) {
            return 'corporate_banking';
        }
        if (preg_match('/\b(compliance|aml|kyc|mlro|financial crime|regulatory)\b/i', $text)) {
            return 'compliance';
        }
        if (preg_match('/\b(risk|credit risk|market risk|operational risk|enterprise risk)\b/i', $text)) {
            return 'risk';
        }
        if (preg_match('/\b(treasury|treasurer|liquidity|alm|irrbb)\b/i', $text)) {
            return 'treasury';
        }
        return 'finance_accounting';
    }

    private function detect_gulf_finance_level($text, $seniority)
    {
        if (preg_match('/\b(chief investment officer|cio)\b/i', $text)) return 'cio';
        if (preg_match('/\b(chief financial officer|cfo)\b/i', $text)) return 'cfo';
        if (preg_match('/\b(chief risk officer|cro)\b/i', $text)) return 'cro';
        if (preg_match('/\b(chief compliance officer|cco|mlro)\b/i', $text)) return 'cco';
        if (preg_match('/\b(managing director|\bmd\b|partner)\b/i', $text)) return 'md';
        if (preg_match('/\b(senior vice president|\bsvp\b|executive director|\bed\b)\b/i', $text)) return 'svp';
        if (preg_match('/\b(vice president|\bvp\b)\b/i', $text)) return 'vp';
        if (preg_match('/\b(principal)\b/i', $text)) return 'principal';
        if (preg_match('/\b(head of|head\b|director)\b/i', $text)) return 'director';
        if (preg_match('/\b(financial controller|controller)\b/i', $text)) return 'controller';
        if (preg_match('/\b(manager|fund manager|portfolio manager|relationship manager)\b/i', $text)) return 'manager';
        if (preg_match('/\b(senior accountant)\b/i', $text)) return 'senior_accountant';
        if (preg_match('/\b(associate|officer|consultant)\b/i', $text)) return 'associate';
        if (preg_match('/\b(accountant)\b/i', $text)) return 'accountant';
        if (preg_match('/\b(analyst|graduate|trainee|intern)\b/i', $text)) return 'analyst';

        $map = [
            'entry' => 'analyst',
            'junior' => 'analyst',
            'mid' => 'associate',
            'senior' => 'manager',
            'manager' => 'manager',
            'senior_manager' => 'manager',
            'associate_director' => 'director',
            'director' => 'director',
            'senior_director' => 'svp',
            'vp' => 'vp',
            'svp' => 'svp',
            'c_level' => 'md',
        ];

        return $map[$seniority] ?? 'associate';
    }

    private function infer_gulf_finance_salary_band($table, $level)
    {
        $ratios = [
            'analyst' => 1.0, 'associate' => 1.62, 'manager' => 2.2, 'vp' => 2.65,
            'principal' => 3.0, 'director' => 3.55, 'svp' => 3.85, 'md' => 5.0,
            'cfo' => 5.4, 'cio' => 6.2, 'cro' => 5.2, 'cco' => 4.8,
        ];
        $target_ratio = $ratios[$level] ?? 0;
        $best = null;

        if ($target_ratio <= 0) {
            return null;
        }

        foreach ($table as $candidate_level => $band) {
            $candidate_ratio = $ratios[$candidate_level] ?? 0;
            if ($candidate_ratio <= 0 || !is_array($band)) {
                continue;
            }
            $distance = abs(log($target_ratio / $candidate_ratio));
            if ($best === null || $distance < $best['distance']) {
                $best = [
                    'band' => $band,
                    'ratio' => $candidate_ratio,
                    'distance' => $distance,
                ];
            }
        }

        if ($best === null || $best['ratio'] <= 0) {
            return null;
        }

        $multiplier = $target_ratio / $best['ratio'];
        return [
            (int) round(((float) $best['band'][0]) * $multiplier),
            (int) round(((float) $best['band'][1]) * $multiplier),
        ];
    }

    /**
     * Get market-based salary from research data
     */
    private function get_market_based_salary($market, $city, $seniority)
    {
        // Check if we have specific data for this market
        if (isset($this->market_salary_data[$market])) {
            // Check for specific city data
            if (isset($this->market_salary_data[$market][$city])) {
                $salary_range = $this->market_salary_data[$market][$city][$seniority] ??
                    $this->market_salary_data[$market][$city]['mid'];
            } else {
                // Use default for the market
                $salary_range = $this->market_salary_data[$market]['default'][$seniority] ??
                    $this->market_salary_data[$market]['default']['mid'];
            }
        } else {
            // Market not found - use UK as base and convert if needed
            // This handles Asia and other markets
            $base_range = $this->market_salary_data['uk']['default'][$seniority] ??
                $this->market_salary_data['uk']['default']['mid'];

            // Apply regional adjustments for markets we don't have specific data for
            $market_multipliers = [
                'asia' => 1.1,  // Generally higher for expat roles
                'private_equity' => 1.2, // Tax-free premium
                'africa' => 0.7,
                'south_america' => 0.6
            ];

            $multiplier = $market_multipliers[$market] ?? 1.0;

            $salary_range = [
                'min' => round($base_range['min'] * $multiplier),
                'max' => round($base_range['max'] * $multiplier)
            ];
        }

        return [
            'min' => $salary_range['min'],
            'max' => $salary_range['max']
        ];
    }

    /**
     * Apply industry adjustment
     */
    private function apply_industry_adjustment($salary, $industry)
    {
        $multiplier = $this->industry_multipliers[$industry] ?? 1.0;

        return [
            'min' => round($salary['min'] * $multiplier),
            'max' => round($salary['max'] * $multiplier)
        ];
    }

    /**
     * Apply skill premiums for high-value skills
     */
    private function apply_skill_premiums($salary, $job_data)
    {
        $premium = 1.0;
        $text = strtolower(implode(' ', [
            $job_data['description'] ?? '',
            $job_data['requirements'] ?? '',
            implode(' ', $job_data['skills'] ?? [])
        ]));

        // Check for high-value skills
        $high_value_skills = [
            'machine learning' => 1.15,
            'artificial intelligence' => 1.15,
            'quantitative' => 1.1,
            'derivatives' => 1.1,
            'blockchain' => 1.1,
            'cloud architecture' => 1.1,
            'data science' => 1.1
        ];

        foreach ($high_value_skills as $skill => $multiplier) {
            if (strpos($text, $skill) !== false) {
                $premium = max($premium, $multiplier);
            }
        }

        // Check for certifications
        if (preg_match('/\b(cfa|frm|caia|pmp|cpa|aca|acca|aws|azure|gcp)\b/i', $text)) {
            $premium *= 1.05;
        }

        return [
            'min' => round($salary['min'] * $premium),
            'max' => round($salary['max'] * $premium)
        ];
    }

    /**
     * Format salary for display
     */
    private function format_salary_display($salary, $currency)
    {
        // Use GBP symbol if currency not found
        $symbol = $this->currency_symbols[$currency] ?? '£';

        // Format based on magnitude and currency
        if ($currency === 'JPY' || $currency === 'KRW') {
            // No decimals for Japanese Yen or Korean Won
            if ($salary['max'] >= 10000000) {
                $min_display = sprintf('%s%.0fM', $symbol, $salary['min'] / 1000000);
                $max_display = sprintf('%s%.0fM', $symbol, $salary['max'] / 1000000);
            } else {
                $min_display = sprintf('%s%s', $symbol, number_format($salary['min'], 0));
                $max_display = sprintf('%s%s', $symbol, number_format($salary['max'], 0));
            }
        } else {
            // Standard formatting for other currencies
            if ($salary['max'] >= 1000000) {
                $min_display = sprintf('%s%.1fM', $symbol, $salary['min'] / 1000000);
                $max_display = sprintf('%s%.1fM', $symbol, $salary['max'] / 1000000);
            } elseif ($salary['max'] >= 1000) {
                $min_display = sprintf('%s%dk', $symbol, round($salary['min'] / 1000));
                $max_display = sprintf('%s%dk', $symbol, round($salary['max'] / 1000));
            } else {
                $min_display = sprintf('%s%s', $symbol, number_format($salary['min']));
                $max_display = sprintf('%s%s', $symbol, number_format($salary['max']));
            }
        }

        $display = sprintf('%s - %s (AI est.)', $min_display, $max_display);

        return [
            'min' => $salary['min'],
            'max' => $salary['max'],
            'currency' => $currency,
            'symbol' => $symbol,
            'display' => $display,
            'is_estimated' => true
        ];
    }
}
