<?php

/**
 * Intelligent Salary Estimator
 * 
 * Provides accurate salary estimations based on:
 * - Location and currency
 * - Seniority level detected from title and requirements
 * - Years of experience required
 * - Skills complexity
 * - Industry sector
 * 
 * @package MENA Careers
 * @since 5.2.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Intelligent_Salary_Estimator
{

    private static $instance = null;

    /**
     * Location-based currency mapping
     */
    private $location_currencies = [
        // UK
        'london' => 'GBP',
        'manchester' => 'GBP',
        'birmingham' => 'GBP',
        'edinburgh' => 'GBP',
        'glasgow' => 'GBP',
        'leeds' => 'GBP',
        'bristol' => 'GBP',
        'liverpool' => 'GBP',
        'newcastle' => 'GBP',
        'nottingham' => 'GBP',
        'sheffield' => 'GBP',
        'cardiff' => 'GBP',
        'belfast' => 'GBP',
        'uk' => 'GBP',
        'u.k.' => 'GBP',
        'united kingdom' => 'GBP',
        'great britain' => 'GBP',
        'england' => 'GBP',
        'scotland' => 'GBP',
        'wales' => 'GBP',
        'northern ireland' => 'GBP',

        // Europe
        'paris' => 'EUR',
        'frankfurt' => 'EUR',
        'munich' => 'EUR',
        'berlin' => 'EUR',
        'amsterdam' => 'EUR',
        'brussels' => 'EUR',
        'madrid' => 'EUR',
        'barcelona' => 'EUR',
        'milan' => 'EUR',
        'rome' => 'EUR',
        'vienna' => 'EUR',
        'dublin' => 'EUR',
        'luxembourg' => 'EUR',
        'germany' => 'EUR',
        'france' => 'EUR',
        'spain' => 'EUR',
        'italy' => 'EUR',
        'netherlands' => 'EUR',
        'belgium' => 'EUR',
        'austria' => 'EUR',
        'ireland' => 'EUR',

        // private equity (using USD as standard)
        'dubai' => 'USD',
        'abu dhabi' => 'USD',
        'doha' => 'USD',
        'riyadh' => 'USD',
        'kuwait' => 'USD',
        'bahrain' => 'USD',
        'muscat' => 'USD',
        'uae' => 'USD',
        'qatar' => 'USD',
        'saudi' => 'USD',
        'oman' => 'USD',

        // Asia Pacific
        'singapore' => 'SGD',
        'hong kong' => 'HKD',
        'tokyo' => 'JPY',
        'sydney' => 'AUD',
        'melbourne' => 'AUD',
        'australia' => 'AUD',

        // Americas
        'new york' => 'USD',
        'san francisco' => 'USD',
        'chicago' => 'USD',
        'boston' => 'USD',
        'toronto' => 'CAD',
        'vancouver' => 'CAD',
        'canada' => 'CAD',
        'usa' => 'USD',
        'us' => 'USD'
    ];

    /**
     * Base salary ranges in USD (will be converted based on location)
     */
    private $seniority_base_salaries = [
        'entry' => [
            'min' => 40000,
            'max' => 70000,
            'experience_years' => [0, 2]
        ],
        'junior' => [
            'min' => 50000,
            'max' => 85000,
            'experience_years' => [1, 3]
        ],
        'mid' => [
            'min' => 75000,
            'max' => 120000,
            'experience_years' => [3, 6]
        ],
        'senior' => [
            'min' => 110000,
            'max' => 180000,
            'experience_years' => [5, 10]
        ],
        'lead' => [
            'min' => 140000,
            'max' => 220000,
            'experience_years' => [7, 12]
        ],
        'manager' => [
            'min' => 130000,
            'max' => 200000,
            'experience_years' => [5, 15]
        ],
        'senior_manager' => [
            'min' => 160000,
            'max' => 250000,
            'experience_years' => [8, 15]
        ],
        'director' => [
            'min' => 180000,
            'max' => 300000,
            'experience_years' => [10, 20]
        ],
        'vp' => [
            'min' => 220000,
            'max' => 400000,
            'experience_years' => [12, 25]
        ],
        'svp' => [
            'min' => 280000,
            'max' => 500000,
            'experience_years' => [15, 30]
        ],
        'c_level' => [
            'min' => 350000,
            'max' => 750000,
            'experience_years' => [15, 30]
        ]
    ];

    /**
     * Location cost multipliers (relative to US)
     */
    private $location_multipliers = [
        // High cost locations
        'san francisco' => 1.3,
        'new york' => 1.25,
        'london' => 1.15,
        'zurich' => 1.4,
        'singapore' => 1.1,
        'hong kong' => 1.2,
        'tokyo' => 1.1,
        'sydney' => 1.05,
        'dubai' => 1.0,
        'luxembourg' => 1.3,

        // Medium-high cost
        'paris' => 1.0,
        'frankfurt' => 1.05,
        'amsterdam' => 1.0,
        'dublin' => 1.0,
        'munich' => 1.0,
        'boston' => 1.1,
        'seattle' => 1.1,
        'toronto' => 0.95,

        // Medium cost
        'chicago' => 0.95,
        'manchester' => 0.85,
        'birmingham' => 0.80,
        'edinburgh' => 0.85,
        'glasgow' => 0.80,
        'leeds' => 0.78,
        'bristol' => 0.82,
        'berlin' => 0.9,
        'madrid' => 0.85,
        'milan' => 0.9,
        'brussels' => 0.95,
        'melbourne' => 1.0,

        // Lower cost
        'barcelona' => 0.8,
        'lisbon' => 0.75,
        'prague' => 0.7,
        'warsaw' => 0.7,
        'budapest' => 0.65,
        'athens' => 0.7
    ];

    /**
     * Currency conversion rates (relative to USD)
     */
    private $currency_rates = [
        'USD' => 1.0,
        'GBP' => 0.79,
        'EUR' => 0.92,
        'SGD' => 1.35,
        'HKD' => 7.8,
        'JPY' => 149,
        'AUD' => 1.52,
        'CAD' => 1.36,
        'CHF' => 0.91,
        'AED' => 3.67,
        'SAR' => 3.75,
        'QAR' => 3.64
    ];

    /**
     * Industry multipliers
     */
    private $industry_multipliers = [
        'investment banking' => 1.4,
        'private equity' => 1.5,
        'hedge fund' => 1.6,
        'venture capital' => 1.3,
        'asset management' => 1.2,
        'wealth management' => 1.1,
        'corporate banking' => 1.0,
        'retail banking' => 0.9,
        'insurance' => 0.95,
        'fintech' => 1.15,
        'consulting' => 1.2,
        'accounting' => 0.9,
        'audit' => 0.85,
        'real estate' => 1.05,
        'government' => 0.8,
        'non-profit' => 0.7
    ];

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Estimate salary based on comprehensive analysis
     */
    public function estimate_salary($job_data)
    {
        // Use V3 estimator if available for most accurate market-based estimation
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-intelligent-salary-estimator-v3.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-intelligent-salary-estimator-v3.php';
            $estimator = SFFC_Intelligent_Salary_Estimator_V3::get_instance();
            return $estimator->estimate_salary($job_data);
        }

        // Fallback to original logic if V3 not available
        // Extract key information
        $title = $job_data['title'] ?? '';
        $location = $job_data['location'] ?? '';
        $company = $job_data['company'] ?? '';
        $description = $job_data['description'] ?? '';
        $requirements = $job_data['qualifications'] ?? $job_data['requirements'] ?? '';
        $responsibilities = $job_data['responsibilities'] ?? '';
        $skills = $job_data['skills'] ?? [];

        // Combine all text for analysis
        $full_text = implode(' ', [
            $title,
            $description,
            $requirements,
            $responsibilities
        ]);

        // 1. Detect seniority level
        $seniority = $this->detect_seniority_level($title, $full_text);

        // 2. Extract years of experience requirement
        $experience_years = $this->extract_experience_years($full_text);

        // 3. Get location and currency
        $location_data = $this->detect_location_and_currency($location, $title);

        // 4. Detect industry
        $industry = $this->detect_industry($company, $title, $full_text);

        // 5. Calculate base salary
        $base_salary = $this->calculate_base_salary($seniority, $experience_years);

        // 6. Apply location multiplier
        $location_adjusted = $this->apply_location_adjustment($base_salary, $location_data['city']);

        // 7. Apply industry multiplier
        $industry_adjusted = $this->apply_industry_adjustment($location_adjusted, $industry);

        // 8. Apply skills complexity bonus
        $skills_adjusted = $this->apply_skills_adjustment($industry_adjusted, $skills, $full_text);

        // 9. Convert to appropriate currency
        $final_salary = $this->convert_to_currency($skills_adjusted, $location_data['currency']);

        // 10. Apply reasonable bounds
        $final_salary = $this->apply_reasonable_bounds($final_salary, $seniority, $location_data['currency']);

        // Format for display
        return $this->format_salary_display($final_salary, $location_data['currency'], true);
    }

    /**
     * Detect seniority level from title and content
     */
    private function detect_seniority_level($title, $full_text)
    {
        $title_lower = strtolower($title);
        $text_lower = strtolower($full_text);

        // C-Level detection
        if (preg_match('/\b(ceo|cfo|cto|coo|cio|cmo|cro|chief\s+\w+\s+officer)\b/', $title_lower)) {
            return 'c_level';
        }

        // SVP/EVP detection
        if (preg_match('/\b(svp|evp|senior\s+vice\s+president|executive\s+vice\s+president)\b/', $title_lower)) {
            return 'svp';
        }

        // VP detection
        if (preg_match('/\b(vp|vice\s+president)\b/', $title_lower) && !preg_match('/\b(assistant|associate)\s+vp\b/', $title_lower)) {
            return 'vp';
        }

        // Director detection
        if (preg_match('/\b(director|head\s+of)\b/', $title_lower) && !preg_match('/\b(associate|assistant|junior)\s+director\b/', $title_lower)) {
            // Check if it's executive/managing director
            if (preg_match('/\b(managing|executive)\s+director\b/', $title_lower)) {
                return 'vp'; // Treat as VP level
            }
            return 'director';
        }

        // Senior Manager detection
        if (preg_match('/\b(senior\s+manager|principal)\b/', $title_lower)) {
            return 'senior_manager';
        }

        // Manager detection
        if (preg_match('/\bmanager\b/', $title_lower) && !preg_match('/\b(junior|assistant|associate)\s+manager\b/', $title_lower)) {
            return 'manager';
        }

        // Lead/Senior Individual Contributor
        if (preg_match('/\b(lead|senior|sr\.?)\s+/', $title_lower) || preg_match('/\bIII\b/', $title)) {
            // Check experience requirements
            if (preg_match('/\b(10|ten|\d{2})\+?\s*years?\b/i', $full_text)) {
                return 'lead';
            }
            return 'senior';
        }

        // Mid-level detection
        if (
            preg_match('/\b(analyst|associate|consultant|specialist|developer|engineer)\b/', $title_lower) &&
            !preg_match('/\b(junior|jr|entry|graduate|intern)\b/', $title_lower)
        ) {
            // Check if it's senior associate/analyst
            if (preg_match('/\b(senior|sr\.?)\s+(analyst|associate|consultant)/', $title_lower)) {
                return 'senior';
            }
            return 'mid';
        }

        // Junior detection
        if (preg_match('/\b(junior|jr\.?|graduate|entry)\b/', $title_lower)) {
            return 'junior';
        }

        // Intern detection
        if (preg_match('/\b(intern|trainee|apprentice)\b/', $title_lower)) {
            return 'entry';
        }

        // Default based on experience requirements
        if (preg_match('/\b([7-9]|seven|eight|nine)\+?\s*years?\b/i', $full_text)) {
            return 'senior';
        } elseif (preg_match('/\b([4-6]|four|five|six)\+?\s*years?\b/i', $full_text)) {
            return 'mid';
        } elseif (preg_match('/\b([1-3]|one|two|three)\+?\s*years?\b/i', $full_text)) {
            return 'junior';
        }

        return 'mid'; // Default to mid-level
    }

    /**
     * Extract years of experience from requirements
     */
    private function extract_experience_years($text)
    {
        // Look for experience requirements
        if (preg_match('/\b(\d+)\+?\s*(?:to\s*(\d+)\s*)?years?\s*(?:of\s*)?(?:experience|exp)\b/i', $text, $matches)) {
            $min_years = intval($matches[1]);
            $max_years = isset($matches[2]) ? intval($matches[2]) : $min_years + 3;
            return ['min' => $min_years, 'max' => $max_years];
        }

        // Look for written numbers
        $written_numbers = [
            'one' => 1,
            'two' => 2,
            'three' => 3,
            'four' => 4,
            'five' => 5,
            'six' => 6,
            'seven' => 7,
            'eight' => 8,
            'nine' => 9,
            'ten' => 10,
            'twelve' => 12,
            'fifteen' => 15,
            'twenty' => 20
        ];

        foreach ($written_numbers as $word => $num) {
            if (preg_match('/\b' . $word . '\+?\s*years?\s*(?:of\s*)?(?:experience|exp)\b/i', $text)) {
                return ['min' => $num, 'max' => $num + 3];
            }
        }

        // Default based on common patterns
        if (stripos($text, 'senior') !== false) {
            return ['min' => 5, 'max' => 10];
        } elseif (stripos($text, 'junior') !== false || stripos($text, 'entry') !== false) {
            return ['min' => 0, 'max' => 3];
        }

        return ['min' => 3, 'max' => 6]; // Default to mid-level
    }

    /**
     * Detect location and appropriate currency
     */
    private function detect_location_and_currency($location_string, $title = '')
    {
        $location_lower = strtolower($location_string . ' ' . $title);
        $detected_city = '';
        $detected_currency = 'USD';

        // First try to match specific cities
        foreach ($this->location_currencies as $city => $currency) {
            if (stripos($location_lower, $city) !== false) {
                $detected_city = $city;
                $detected_currency = $currency;
                break;
            }
        }

        // If no city found, try broader patterns
        if (empty($detected_city)) {
            if (preg_match('/\b(london|manchester|birmingham|edinburgh|glasgow)\b/i', $location_lower, $matches)) {
                $detected_city = strtolower($matches[1]);
                $detected_currency = 'GBP';
            } elseif (preg_match('/\b(paris|frankfurt|munich|berlin|amsterdam|madrid|milan|brussels)\b/i', $location_lower, $matches)) {
                $detected_city = strtolower($matches[1]);
                $detected_currency = 'EUR';
            } elseif (preg_match('/\b(dubai|doha|riyadh|kuwait)\b/i', $location_lower, $matches)) {
                $detected_city = strtolower($matches[1]);
                $detected_currency = 'USD'; // private equity typically uses USD for international roles
            }
        }

        return [
            'city' => $detected_city ?: 'global',
            'currency' => $detected_currency
        ];
    }

    /**
     * Detect industry from company and job details
     */
    private function detect_industry($company, $title, $text)
    {
        $combined = strtolower($company . ' ' . $title . ' ' . $text);

        // Check for specific industry keywords
        if (preg_match('/\b(investment\s+bank|ib|m&a|mergers|capital\s+markets|ecm|dcm)\b/', $combined)) {
            return 'investment banking';
        }
        if (preg_match('/\b(private\s+equity|pe\s+fund|buyout|lbo|portfolio\s+company)\b/', $combined)) {
            return 'private equity';
        }
        if (preg_match('/\b(hedge\s+fund|quant|algorithmic\s+trading|systematic\s+trading)\b/', $combined)) {
            return 'hedge fund';
        }
        if (preg_match('/\b(venture\s+capital|vc\s+fund|startup\s+investment|seed\s+funding)\b/', $combined)) {
            return 'venture capital';
        }
        if (preg_match('/\b(asset\s+management|fund\s+management|portfolio\s+management|aum)\b/', $combined)) {
            return 'asset management';
        }
        if (preg_match('/\b(wealth\s+management|private\s+banking|hnw|uhnw|family\s+office)\b/', $combined)) {
            return 'wealth management';
        }
        if (preg_match('/\b(fintech|financial\s+technology|payments|blockchain|crypto)\b/', $combined)) {
            return 'fintech';
        }
        if (preg_match('/\b(consulting|advisory|mckinsey|bain|bcg|deloitte|pwc|ey|kpmg)\b/', $combined)) {
            return 'consulting';
        }

        return 'corporate banking'; // Default
    }

    /**
     * Calculate base salary based on seniority and experience
     */
    private function calculate_base_salary($seniority, $experience_years)
    {
        $base_range = $this->seniority_base_salaries[$seniority] ?? $this->seniority_base_salaries['mid'];

        // Adjust based on actual experience vs expected
        $expected_exp_min = $base_range['experience_years'][0];
        $expected_exp_max = $base_range['experience_years'][1];
        $actual_exp = $experience_years['min'];

        // Calculate position within range
        if ($actual_exp <= $expected_exp_min) {
            $position = 0.3; // Lower third of range
        } elseif ($actual_exp >= $expected_exp_max) {
            $position = 0.8; // Upper portion of range
        } else {
            // Linear interpolation
            $exp_range = $expected_exp_max - $expected_exp_min;
            $exp_position = ($actual_exp - $expected_exp_min) / $exp_range;
            $position = 0.3 + ($exp_position * 0.5);
        }

        $min = $base_range['min'];
        $max = $base_range['max'];
        $range = $max - $min;

        return [
            'min' => $min + ($range * max(0, $position - 0.2)),
            'max' => $min + ($range * min(1, $position + 0.3))
        ];
    }

    /**
     * Apply location cost of living adjustment
     */
    private function apply_location_adjustment($salary, $city)
    {
        $multiplier = $this->location_multipliers[$city] ?? 1.0;

        return [
            'min' => $salary['min'] * $multiplier,
            'max' => $salary['max'] * $multiplier
        ];
    }

    /**
     * Apply industry multiplier
     */
    private function apply_industry_adjustment($salary, $industry)
    {
        $multiplier = $this->industry_multipliers[$industry] ?? 1.0;

        return [
            'min' => $salary['min'] * $multiplier,
            'max' => $salary['max'] * $multiplier
        ];
    }

    /**
     * Apply skills complexity adjustment
     */
    private function apply_skills_adjustment($salary, $skills, $text)
    {
        $multiplier = 1.0;

        // Check for high-value technical skills
        $high_value_skills = [
            'machine learning',
            'artificial intelligence',
            'ai',
            'ml',
            'quantitative',
            'quant',
            'algorithmic trading',
            'blockchain',
            'cryptocurrency',
            'defi',
            'risk modeling',
            'derivatives',
            'structured products',
            'private equity',
            'leveraged finance',
            'm&a'
        ];

        $text_lower = strtolower(implode(' ', $skills) . ' ' . $text);
        $skill_matches = 0;

        foreach ($high_value_skills as $skill) {
            if (stripos($text_lower, $skill) !== false) {
                $skill_matches++;
            }
        }

        // Add up to 20% for high-value skills
        if ($skill_matches >= 3) {
            $multiplier = 1.2;
        } elseif ($skill_matches >= 2) {
            $multiplier = 1.15;
        } elseif ($skill_matches >= 1) {
            $multiplier = 1.1;
        }

        // Check for advanced certifications
        if (preg_match('/\b(cfa|frm|caia|pmp|cpa|aca|acca|mba)\b/i', $text)) {
            $multiplier *= 1.1;
        }

        return [
            'min' => $salary['min'] * $multiplier,
            'max' => $salary['max'] * $multiplier
        ];
    }

    /**
     * Convert salary to appropriate currency
     */
    private function convert_to_currency($salary_usd, $currency)
    {
        $rate = $this->currency_rates[$currency] ?? 1.0;

        return [
            'min' => $salary_usd['min'] * $rate,
            'max' => $salary_usd['max'] * $rate,
            'currency' => $currency
        ];
    }

    /**
     * Apply reasonable bounds to prevent unrealistic estimates
     */
    private function apply_reasonable_bounds($salary, $seniority, $currency)
    {
        // Define maximum reasonable salaries by seniority and currency
        $max_bounds = [
            'USD' => [
                'entry' => 100000,
                'junior' => 130000,
                'mid' => 180000,
                'senior' => 250000,
                'lead' => 300000,
                'manager' => 300000,
                'senior_manager' => 400000,
                'director' => 500000,
                'vp' => 700000,
                'svp' => 1000000,
                'c_level' => 2000000
            ],
            'GBP' => [
                'entry' => 70000,
                'junior' => 90000,
                'mid' => 130000,
                'senior' => 180000,
                'lead' => 220000,
                'manager' => 220000,
                'senior_manager' => 300000,
                'director' => 400000,
                'vp' => 550000,
                'svp' => 800000,
                'c_level' => 1500000
            ],
            'EUR' => [
                'entry' => 80000,
                'junior' => 100000,
                'mid' => 150000,
                'senior' => 200000,
                'lead' => 250000,
                'manager' => 250000,
                'senior_manager' => 350000,
                'director' => 450000,
                'vp' => 600000,
                'svp' => 900000,
                'c_level' => 1800000
            ]
        ];

        $bounds = $max_bounds[$currency] ?? $max_bounds['USD'];
        $max_reasonable = $bounds[$seniority] ?? 500000;

        // Also set minimum bounds
        $min_bounds = [
            'USD' => [
                'entry' => 30000,
                'junior' => 40000,
                'mid' => 60000,
                'senior' => 80000,
                'lead' => 100000,
                'manager' => 90000,
                'senior_manager' => 120000,
                'director' => 150000,
                'vp' => 200000,
                'svp' => 250000,
                'c_level' => 300000
            ]
        ];

        $min_bound_currency = $min_bounds[$currency] ?? $min_bounds['USD'];
        $min_reasonable = $min_bound_currency[$seniority] ?? 40000;

        // Apply currency conversion to min bounds if needed
        if ($currency === 'GBP') {
            $min_reasonable *= 0.79;
        } elseif ($currency === 'EUR') {
            $min_reasonable *= 0.92;
        }

        return [
            'min' => max($min_reasonable, min($salary['min'], $max_reasonable * 0.6)),
            'max' => min($salary['max'], $max_reasonable),
            'currency' => $currency
        ];
    }

    /**
     * Format salary for display
     */
    private function format_salary_display($salary, $currency, $is_estimated = false)
    {
        $symbols = [
            'USD' => '$',
            'GBP' => '£',
            'EUR' => '€',
            'SGD' => 'S$',
            'HKD' => 'HK$',
            'JPY' => '¥',
            'AUD' => 'A$',
            'CAD' => 'C$',
            'CHF' => 'CHF',
            'AED' => 'AED',
            'SAR' => 'SAR',
            'QAR' => 'QAR'
        ];

        $symbol = $symbols[$currency] ?? '$';

        // Format based on magnitude
        if ($salary['max'] >= 1000000) {
            $min_display = sprintf('%s%.1fM', $symbol, $salary['min'] / 1000000);
            $max_display = sprintf('%s%.1fM', $symbol, $salary['max'] / 1000000);
        } elseif ($salary['max'] >= 1000) {
            $min_display = sprintf('%s%dk', $symbol, round($salary['min'] / 1000));
            $max_display = sprintf('%s%dk', $symbol, round($salary['max'] / 1000));
        } else {
            $min_display = sprintf('%s%d', $symbol, round($salary['min']));
            $max_display = sprintf('%s%d', $symbol, round($salary['max']));
        }

        $display = sprintf('%s - %s', $min_display, $max_display);

        // Add AI indicator if estimated
        if ($is_estimated) {
            $display .= ' (AI est.)';
        }

        return [
            'min' => $salary['min'],
            'max' => $salary['max'],
            'currency' => $currency,
            'symbol' => $symbol,
            'display' => $display,
            'is_estimated' => $is_estimated
        ];
    }
}
