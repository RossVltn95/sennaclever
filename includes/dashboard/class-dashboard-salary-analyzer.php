<?php
/**
 * Dashboard Salary Analyzer
 *
 * Provides salary intelligence for the career dashboard.
 * Integrates with the existing SFFC_Intelligent_Salary_Estimator.
 *
 * @package SFFC_Careers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Dashboard_Salary_Analyzer {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Salary estimator instance
     */
    private $estimator = null;

    /**
     * Location multipliers (relative to US base)
     */
    private $location_multipliers = array(
        'london' => 1.15,
        'new-york' => 1.25,
        'san-francisco' => 1.30,
        'hong-kong' => 1.20,
        'singapore' => 1.10,
        'dubai' => 1.05,
        'zurich' => 1.40,
        'paris' => 1.00,
        'frankfurt' => 1.05,
        'sydney' => 1.00,
        'toronto' => 0.95,
        'amsterdam' => 1.00,
        'dublin' => 1.00,
        'milan' => 0.90,
        'madrid' => 0.85,
        'mumbai' => 0.45,
    );

    /**
     * Industry multipliers
     */
    private $industry_multipliers = array(
        'investment-banking' => 1.40,
        'private-equity' => 1.50,
        'hedge-fund' => 1.60,
        'venture-capital' => 1.30,
        'asset-management' => 1.20,
        'wealth-management' => 1.10,
        'fintech' => 1.15,
        'consulting' => 1.20,
        'corporate-finance' => 1.10,
        'accounting' => 0.90,
        'insurance' => 0.95,
    );

    /**
     * Currency symbols and conversion rates to USD
     */
    private $currencies = array(
        'USD' => array('symbol' => '$', 'rate' => 1.00),
        'GBP' => array('symbol' => '£', 'rate' => 0.79),
        'EUR' => array('symbol' => '€', 'rate' => 0.92),
        'SGD' => array('symbol' => 'S$', 'rate' => 1.35),
        'HKD' => array('symbol' => 'HK$', 'rate' => 7.80),
        'AED' => array('symbol' => 'AED ', 'rate' => 3.67),
        'CHF' => array('symbol' => 'CHF ', 'rate' => 0.91),
        'AUD' => array('symbol' => 'A$', 'rate' => 1.52),
        'CAD' => array('symbol' => 'C$', 'rate' => 1.36),
    );

    /**
     * Location to currency mapping
     */
    private $location_currencies = array(
        'london' => 'GBP',
        'new-york' => 'USD',
        'san-francisco' => 'USD',
        'hong-kong' => 'HKD',
        'singapore' => 'SGD',
        'dubai' => 'AED',
        'zurich' => 'CHF',
        'paris' => 'EUR',
        'frankfurt' => 'EUR',
        'sydney' => 'AUD',
        'toronto' => 'CAD',
        'amsterdam' => 'EUR',
        'dublin' => 'EUR',
        'milan' => 'EUR',
        'madrid' => 'EUR',
        'mumbai' => 'USD',
    );

    /**
     * Cost of living index (NYC = 100)
     */
    private $cost_of_living_index = array(
        'london' => 95,
        'new-york' => 100,
        'san-francisco' => 105,
        'hong-kong' => 90,
        'singapore' => 85,
        'dubai' => 75,
        'zurich' => 115,
        'paris' => 85,
        'frankfurt' => 80,
        'sydney' => 82,
        'toronto' => 75,
        'amsterdam' => 80,
        'dublin' => 85,
        'milan' => 78,
        'madrid' => 65,
        'mumbai' => 35,
    );

    /**
     * Tax rates by location (effective rate for high earners)
     */
    private $tax_rates = array(
        'london' => 0.40,      // UK income tax
        'new-york' => 0.42,    // Federal + NY state + city
        'san-francisco' => 0.45, // Federal + CA state
        'hong-kong' => 0.17,   // Low flat tax
        'singapore' => 0.22,   // Low progressive tax
        'dubai' => 0.00,       // No income tax
        'zurich' => 0.35,      // Swiss federal + cantonal
        'paris' => 0.45,       // French income tax
        'frankfurt' => 0.42,   // German income tax
        'sydney' => 0.39,      // Australian tax
        'toronto' => 0.43,     // Canadian federal + provincial
        'amsterdam' => 0.49,   // Dutch income tax
        'dublin' => 0.40,      // Irish income tax
        'milan' => 0.43,       // Italian income tax
        'madrid' => 0.43,      // Spanish income tax
        'mumbai' => 0.30,      // Indian income tax
    );

    /**
     * Quality of life scores (composite index)
     */
    private $quality_of_life = array(
        'london' => array('score' => 82, 'commute' => 'Medium', 'work_life' => 'Moderate', 'culture' => 'Excellent'),
        'new-york' => array('score' => 78, 'commute' => 'Long', 'work_life' => 'Intense', 'culture' => 'Excellent'),
        'san-francisco' => array('score' => 80, 'commute' => 'Medium', 'work_life' => 'Moderate', 'culture' => 'Very Good'),
        'hong-kong' => array('score' => 72, 'commute' => 'Short', 'work_life' => 'Intense', 'culture' => 'Good'),
        'singapore' => array('score' => 85, 'commute' => 'Short', 'work_life' => 'Moderate', 'culture' => 'Very Good'),
        'dubai' => array('score' => 75, 'commute' => 'Medium', 'work_life' => 'Good', 'culture' => 'Good'),
        'zurich' => array('score' => 90, 'commute' => 'Short', 'work_life' => 'Good', 'culture' => 'Excellent'),
        'paris' => array('score' => 83, 'commute' => 'Medium', 'work_life' => 'Good', 'culture' => 'Excellent'),
        'frankfurt' => array('score' => 84, 'commute' => 'Short', 'work_life' => 'Good', 'culture' => 'Very Good'),
        'sydney' => array('score' => 88, 'commute' => 'Medium', 'work_life' => 'Good', 'culture' => 'Excellent'),
        'toronto' => array('score' => 86, 'commute' => 'Medium', 'work_life' => 'Good', 'culture' => 'Very Good'),
        'amsterdam' => array('score' => 87, 'commute' => 'Short', 'work_life' => 'Excellent', 'culture' => 'Excellent'),
        'dublin' => array('score' => 84, 'commute' => 'Short', 'work_life' => 'Good', 'culture' => 'Very Good'),
        'milan' => array('score' => 79, 'commute' => 'Medium', 'work_life' => 'Moderate', 'culture' => 'Excellent'),
        'madrid' => array('score' => 82, 'commute' => 'Medium', 'work_life' => 'Good', 'culture' => 'Excellent'),
        'mumbai' => array('score' => 60, 'commute' => 'Long', 'work_life' => 'Moderate', 'culture' => 'Good'),
    );

    /**
     * Typical bonus structures by industry (as % of base)
     */
    private $bonus_structures = array(
        'investment-banking' => array('min' => 50, 'max' => 200, 'typical' => 100, 'structure' => 'Cash bonus + deferred stock'),
        'private-equity' => array('min' => 75, 'max' => 300, 'typical' => 150, 'structure' => 'Bonus + carried interest'),
        'hedge-fund' => array('min' => 100, 'max' => 500, 'typical' => 200, 'structure' => 'Performance-based bonus'),
        'venture-capital' => array('min' => 30, 'max' => 150, 'typical' => 75, 'structure' => 'Bonus + carry participation'),
        'asset-management' => array('min' => 25, 'max' => 100, 'typical' => 50, 'structure' => 'Discretionary bonus'),
        'wealth-management' => array('min' => 20, 'max' => 75, 'typical' => 40, 'structure' => 'Commission + bonus'),
        'fintech' => array('min' => 10, 'max' => 50, 'typical' => 25, 'structure' => 'Bonus + equity/options'),
        'consulting' => array('min' => 15, 'max' => 50, 'typical' => 30, 'structure' => 'Performance bonus'),
        'corporate-finance' => array('min' => 10, 'max' => 40, 'typical' => 20, 'structure' => 'Annual bonus'),
        'accounting' => array('min' => 5, 'max' => 25, 'typical' => 15, 'structure' => 'Year-end bonus'),
        'insurance' => array('min' => 10, 'max' => 30, 'typical' => 15, 'structure' => 'Annual bonus'),
    );

    /**
     * Salary trend projections (annual growth %)
     */
    private $salary_trends = array(
        'investment-banking' => array('2024' => 3.5, '2025' => 4.0, '2026' => 3.5, 'outlook' => 'stable'),
        'private-equity' => array('2024' => 5.0, '2025' => 4.5, '2026' => 4.0, 'outlook' => 'positive'),
        'hedge-fund' => array('2024' => 2.0, '2025' => 3.0, '2026' => 3.5, 'outlook' => 'improving'),
        'venture-capital' => array('2024' => 1.5, '2025' => 3.0, '2026' => 4.0, 'outlook' => 'recovering'),
        'asset-management' => array('2024' => 2.5, '2025' => 3.0, '2026' => 3.0, 'outlook' => 'stable'),
        'wealth-management' => array('2024' => 3.0, '2025' => 3.5, '2026' => 4.0, 'outlook' => 'positive'),
        'fintech' => array('2024' => 4.0, '2025' => 5.0, '2026' => 5.5, 'outlook' => 'strong'),
        'consulting' => array('2024' => 3.5, '2025' => 4.0, '2026' => 4.0, 'outlook' => 'stable'),
        'corporate-finance' => array('2024' => 2.5, '2025' => 3.0, '2026' => 3.0, 'outlook' => 'stable'),
    );

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Load salary estimator if available
        if (class_exists('SFFC_Intelligent_Salary_Estimator')) {
            $this->estimator = SFFC_Intelligent_Salary_Estimator::get_instance();
        }
    }

    /**
     * Get comprehensive salary data for a user
     */
    public function get_salary_data($user_id, $location1 = 'london', $location2 = 'new-york') {
        $profile = $this->get_user_profile($user_id);

        if (empty($profile)) {
            return $this->get_default_salary_data();
        }

        // Calculate personalized estimate
        $estimate = $this->calculate_personalized_estimate($profile);

        // Get location comparison with enhanced data
        $location_comparison = $this->compare_locations_enhanced($profile, $location1, $location2);

        // Get industry comparison
        $industry_data = $this->get_industry_comparison($profile);

        // Calculate percentile
        $percentile = $this->calculate_salary_percentile($profile, $estimate);

        // Get bonus structure
        $bonus_data = $this->get_bonus_structure($profile);

        // Get salary trends
        $trends = $this->get_salary_trends($profile);

        // Get top quartile tips
        $top_quartile_tips = $this->get_top_quartile_tips($profile, $percentile);

        // Get total compensation estimate
        $total_comp = $this->calculate_total_compensation($estimate, $bonus_data);

        return array(
            'estimate' => $estimate,
            'total_compensation' => $total_comp,
            'location_comparison' => $location_comparison,
            'industry_data' => $industry_data,
            'percentile' => $percentile,
            'factors' => $this->get_salary_factors($profile),
            'bonus' => $bonus_data,
            'trends' => $trends,
            'top_quartile_tips' => $top_quartile_tips,
            'available_locations' => $this->get_available_locations(),
        );
    }

    /**
     * Calculate personalized salary estimate based on profile
     */
    public function calculate_personalized_estimate($profile) {
        // Get base parameters
        $experience = $this->parse_experience($profile['years_experience'] ?? '');
        $seniority = $this->detect_seniority($profile['current_role'] ?? '', $profile['target_seniority'] ?? '', $experience);
        $location = $this->get_primary_location($profile['preferred_locations'] ?? array());
        $industry = $this->get_primary_industry($profile['preferred_industries'] ?? array());
        $skills = (array)($profile['skills'] ?? array());

        // Get base salary range for seniority
        $base_range = $this->get_seniority_base($seniority);

        // Apply location multiplier
        $location_mult = $this->location_multipliers[$location] ?? 1.0;

        // Apply industry multiplier
        $industry_key = $this->normalize_industry_key($industry);
        $industry_mult = $this->industry_multipliers[$industry_key] ?? 1.0;

        // Apply skills bonus
        $skills_mult = $this->calculate_skills_multiplier($skills);

        // Calculate final range
        $min = round($base_range['min'] * $location_mult * $industry_mult * $skills_mult);
        $max = round($base_range['max'] * $location_mult * $industry_mult * $skills_mult);

        // Get currency for location
        $currency = $this->location_currencies[$location] ?? 'USD';

        // Convert to local currency
        $rate = $this->currencies[$currency]['rate'] ?? 1.0;
        $min = round($min / $rate);
        $max = round($max / $rate);

        return array(
            'min' => $min,
            'max' => $max,
            'currency' => $currency,
            'symbol' => $this->currencies[$currency]['symbol'] ?? '$',
            'formatted_min' => $this->format_salary($min, $currency),
            'formatted_max' => $this->format_salary($max, $currency),
        );
    }

    /**
     * Compare salaries between two locations
     */
    public function compare_locations($profile, $location1, $location2) {
        $experience = $this->parse_experience($profile['years_experience'] ?? '');
        $seniority = $this->detect_seniority($profile['current_role'] ?? '', $profile['target_seniority'] ?? '', $experience);
        $industry = $this->get_primary_industry($profile['preferred_industries'] ?? array());
        $skills = (array)($profile['skills'] ?? array());

        $base_range = $this->get_seniority_base($seniority);
        $industry_key = $this->normalize_industry_key($industry);
        $industry_mult = $this->industry_multipliers[$industry_key] ?? 1.0;
        $skills_mult = $this->calculate_skills_multiplier($skills);

        $results = array();

        foreach (array($location1, $location2) as $location) {
            $location_mult = $this->location_multipliers[$location] ?? 1.0;
            $currency = $this->location_currencies[$location] ?? 'USD';
            $rate = $this->currencies[$currency]['rate'] ?? 1.0;

            $min = round(($base_range['min'] * $location_mult * $industry_mult * $skills_mult) / $rate);
            $max = round(($base_range['max'] * $location_mult * $industry_mult * $skills_mult) / $rate);

            $results[$location] = array(
                'min' => $min,
                'max' => $max,
                'currency' => $currency,
                'symbol' => $this->currencies[$currency]['symbol'] ?? '$',
            );
        }

        return $results;
    }

    /**
     * Enhanced location comparison with COL, tax, QoL
     */
    public function compare_locations_enhanced($profile, $location1, $location2) {
        $experience = $this->parse_experience($profile['years_experience'] ?? '');
        $seniority = $this->detect_seniority($profile['current_role'] ?? '', $profile['target_seniority'] ?? '', $experience);
        $industry = $this->get_primary_industry($profile['preferred_industries'] ?? array());
        $skills = (array)($profile['skills'] ?? array());

        $base_range = $this->get_seniority_base($seniority);
        $industry_key = $this->normalize_industry_key($industry);
        $industry_mult = $this->industry_multipliers[$industry_key] ?? 1.0;
        $skills_mult = $this->calculate_skills_multiplier($skills);

        $results = array();

        foreach (array($location1, $location2) as $location) {
            $location_mult = $this->location_multipliers[$location] ?? 1.0;
            $currency = $this->location_currencies[$location] ?? 'USD';
            $rate = $this->currencies[$currency]['rate'] ?? 1.0;

            $min = round(($base_range['min'] * $location_mult * $industry_mult * $skills_mult) / $rate);
            $max = round(($base_range['max'] * $location_mult * $industry_mult * $skills_mult) / $rate);
            $median = round(($min + $max) / 2);

            // Calculate net salary (after tax)
            $tax_rate = $this->tax_rates[$location] ?? 0.35;
            $net_min = round($min * (1 - $tax_rate));
            $net_max = round($max * (1 - $tax_rate));
            $net_median = round($median * (1 - $tax_rate));

            // Cost of living adjusted purchasing power
            $col_index = $this->cost_of_living_index[$location] ?? 100;
            $purchasing_power = round(($net_median / $col_index) * 100);

            // Quality of life data
            $qol = $this->quality_of_life[$location] ?? array('score' => 75, 'commute' => 'Medium', 'work_life' => 'Moderate', 'culture' => 'Good');

            $results[$location] = array(
                'gross' => array(
                    'min' => $min,
                    'max' => $max,
                    'median' => $median,
                ),
                'net' => array(
                    'min' => $net_min,
                    'max' => $net_max,
                    'median' => $net_median,
                ),
                'currency' => $currency,
                'symbol' => $this->currencies[$currency]['symbol'] ?? '$',
                'tax_rate' => round($tax_rate * 100),
                'cost_of_living' => $col_index,
                'purchasing_power' => $purchasing_power,
                'quality_of_life' => $qol,
                'formatted' => array(
                    'gross_range' => $this->format_salary($min, $currency) . ' - ' . $this->format_salary($max, $currency),
                    'net_range' => $this->format_salary($net_min, $currency) . ' - ' . $this->format_salary($net_max, $currency),
                ),
            );
        }

        // Calculate comparison insights
        $loc1_data = $results[$location1];
        $loc2_data = $results[$location2];

        $gross_diff = round((($loc2_data['gross']['median'] - $loc1_data['gross']['median']) / $loc1_data['gross']['median']) * 100);
        $net_diff = round((($loc2_data['net']['median'] - $loc1_data['net']['median']) / $loc1_data['net']['median']) * 100);
        $pp_diff = round((($loc2_data['purchasing_power'] - $loc1_data['purchasing_power']) / $loc1_data['purchasing_power']) * 100);

        $results['comparison'] = array(
            'gross_difference' => $gross_diff,
            'net_difference' => $net_diff,
            'purchasing_power_difference' => $pp_diff,
            'winner_gross' => $gross_diff > 0 ? $location2 : $location1,
            'winner_net' => $net_diff > 0 ? $location2 : $location1,
            'winner_purchasing_power' => $pp_diff > 0 ? $location2 : $location1,
            'insight' => $this->generate_location_insight($location1, $location2, $results),
        );

        return $results;
    }

    /**
     * Generate insight about location comparison
     */
    private function generate_location_insight($loc1, $loc2, $data) {
        $loc1_name = ucwords(str_replace('-', ' ', $loc1));
        $loc2_name = ucwords(str_replace('-', ' ', $loc2));

        $pp_diff = $data['comparison']['purchasing_power_difference'];
        $net_diff = $data['comparison']['net_difference'];

        if ($pp_diff > 15) {
            return "{$loc2_name} offers significantly better purchasing power ({$pp_diff}% more) despite potential cost of living differences.";
        } elseif ($pp_diff < -15) {
            return "{$loc1_name} provides better real-world value with {$pp_diff}% higher purchasing power.";
        } elseif (abs($net_diff) > 10 && abs($pp_diff) < 5) {
            return "While gross salaries differ, after adjusting for taxes and cost of living, both locations offer similar purchasing power.";
        } else {
            return "Both locations offer comparable compensation packages. Consider quality of life factors in your decision.";
        }
    }

    /**
     * Get bonus structure for user's industry
     */
    public function get_bonus_structure($profile) {
        $industry = $this->get_primary_industry($profile['preferred_industries'] ?? array());
        $industry_key = $this->normalize_industry_key($industry);

        $bonus = $this->bonus_structures[$industry_key] ?? array(
            'min' => 10,
            'max' => 30,
            'typical' => 20,
            'structure' => 'Annual bonus'
        );

        return array(
            'percentage' => array(
                'min' => $bonus['min'],
                'max' => $bonus['max'],
                'typical' => $bonus['typical'],
            ),
            'structure' => $bonus['structure'],
            'industry' => $industry,
            'notes' => $this->get_bonus_notes($industry_key),
        );
    }

    /**
     * Get bonus notes for industry
     */
    private function get_bonus_notes($industry_key) {
        $notes = array(
            'investment-banking' => 'Bonuses typically paid in February/March. Expect 30-50% deferred in stock at senior levels.',
            'private-equity' => 'Carried interest vests over 4-5 years. Base bonus + meaningful carry can exceed 5x base salary.',
            'hedge-fund' => 'Performance-driven. Top performers can earn 3-5x base. Poor years may see minimal bonus.',
            'venture-capital' => 'Carry typically 10-20% of fund profits, split among partners. Junior staff get smaller allocation.',
            'asset-management' => 'More stable than IB/HF. Bonuses typically 25-50% of base at senior levels.',
            'wealth-management' => 'Mix of retainer and revenue-based compensation. Client book drives earnings.',
            'fintech' => 'Equity/options can be substantial at startups. RSUs common at larger firms.',
            'consulting' => 'Performance bonus plus profit sharing at partner level.',
            'corporate-finance' => 'Typically 10-30% annual bonus plus potential long-term incentive plans.',
        );

        return $notes[$industry_key] ?? 'Bonus structure varies by company and performance.';
    }

    /**
     * Get salary trends for user's industry
     */
    public function get_salary_trends($profile) {
        $industry = $this->get_primary_industry($profile['preferred_industries'] ?? array());
        $industry_key = $this->normalize_industry_key($industry);

        $trend = $this->salary_trends[$industry_key] ?? array(
            '2024' => 3.0,
            '2025' => 3.0,
            '2026' => 3.0,
            'outlook' => 'stable'
        );

        // Calculate projected salary based on estimate
        $estimate = $this->calculate_personalized_estimate($profile);
        $current_median = round(($estimate['min'] + $estimate['max']) / 2);

        $projections = array(
            array(
                'year' => '2024',
                'growth' => $trend['2024'],
                'projected' => $current_median,
            ),
            array(
                'year' => '2025',
                'growth' => $trend['2025'],
                'projected' => round($current_median * (1 + $trend['2025'] / 100)),
            ),
            array(
                'year' => '2026',
                'growth' => $trend['2026'],
                'projected' => round($current_median * (1 + $trend['2025'] / 100) * (1 + $trend['2026'] / 100)),
            ),
        );

        return array(
            'industry' => $industry,
            'outlook' => $trend['outlook'],
            'projections' => $projections,
            'currency' => $estimate['currency'],
            'symbol' => $estimate['symbol'],
            'insight' => $this->generate_trend_insight($industry, $trend),
        );
    }

    /**
     * Generate trend insight
     */
    private function generate_trend_insight($industry, $trend) {
        $outlook_text = array(
            'strong' => 'Strong growth expected',
            'positive' => 'Positive outlook ahead',
            'stable' => 'Steady compensation growth',
            'improving' => 'Market conditions improving',
            'recovering' => 'Recovering from recent downturn',
            'uncertain' => 'Mixed outlook',
        );

        $outlook = $outlook_text[$trend['outlook']] ?? 'Market conditions vary';
        $avg_growth = round(($trend['2024'] + $trend['2025'] + $trend['2026']) / 3, 1);

        return "{$outlook} for {$industry}. Average projected annual growth of {$avg_growth}% through 2026.";
    }

    /**
     * Get tips for reaching top quartile
     */
    public function get_top_quartile_tips($profile, $current_percentile) {
        $tips = array();

        // Already in top quartile
        if ($current_percentile >= 75) {
            $tips[] = array(
                'type' => 'maintain',
                'title' => 'Maintain Your Edge',
                'description' => 'Continue building expertise and expanding your network to stay competitive.',
                'impact' => 'Sustain top quartile position'
            );
        }

        // Skills-based tips
        $skills = (array)($profile['skills'] ?? array());
        $high_value_skills = array('Financial Modeling', 'Python', 'LBO Modeling', 'Valuation', 'Due Diligence');
        $missing_skills = array_diff($high_value_skills, array_map('ucwords', $skills));

        if (!empty($missing_skills)) {
            $tips[] = array(
                'type' => 'skill',
                'title' => 'Add High-Value Skills',
                'description' => 'Consider developing: ' . implode(', ', array_slice($missing_skills, 0, 3)),
                'impact' => '+10-20% salary potential'
            );
        }

        // Certification tips
        $certs = (array)($profile['certifications'] ?? array());
        $valuable_certs = array('CFA', 'MBA', 'CPA');
        $missing_certs = array();

        foreach ($valuable_certs as $cert) {
            $has_cert = false;
            foreach ($certs as $user_cert) {
                if (stripos($user_cert, $cert) !== false) {
                    $has_cert = true;
                    break;
                }
            }
            if (!$has_cert) {
                $missing_certs[] = $cert;
            }
        }

        if (!empty($missing_certs)) {
            $tips[] = array(
                'type' => 'certification',
                'title' => 'Professional Certifications',
                'description' => 'Pursuing ' . $missing_certs[0] . ' could significantly boost your market value.',
                'impact' => '+5-15% salary premium'
            );
        }

        // Experience tips
        $exp = $this->parse_experience($profile['years_experience'] ?? '');
        if ($exp < 5) {
            $tips[] = array(
                'type' => 'experience',
                'title' => 'Build Deal Experience',
                'description' => 'Seek opportunities to work on high-profile transactions and build your track record.',
                'impact' => 'Accelerate career progression'
            );
        }

        // Location tips
        $location = $this->get_primary_location($profile['preferred_locations'] ?? array());
        $high_pay_locations = array('zurich', 'san-francisco', 'new-york');

        if (!in_array($location, $high_pay_locations)) {
            $tips[] = array(
                'type' => 'location',
                'title' => 'Consider Premium Markets',
                'description' => 'Top-tier financial centers like NYC, SF, or Zurich offer 15-30% higher compensation.',
                'impact' => 'Significant salary uplift'
            );
        }

        // Negotiation tip
        $tips[] = array(
            'type' => 'negotiation',
            'title' => 'Negotiate Effectively',
            'description' => 'Research market rates, time negotiations with performance reviews, and consider total compensation.',
            'impact' => '+5-10% immediate uplift'
        );

        // Limit to top 4 most relevant tips
        return array_slice($tips, 0, 4);
    }

    /**
     * Calculate total compensation including bonus
     */
    public function calculate_total_compensation($estimate, $bonus_data) {
        $base_min = $estimate['min'];
        $base_max = $estimate['max'];

        $bonus_min_pct = $bonus_data['percentage']['min'] / 100;
        $bonus_max_pct = $bonus_data['percentage']['max'] / 100;
        $bonus_typical_pct = $bonus_data['percentage']['typical'] / 100;

        return array(
            'base' => array(
                'min' => $base_min,
                'max' => $base_max,
            ),
            'bonus' => array(
                'min' => round($base_min * $bonus_min_pct),
                'max' => round($base_max * $bonus_max_pct),
                'typical' => round((($base_min + $base_max) / 2) * $bonus_typical_pct),
            ),
            'total' => array(
                'min' => round($base_min * (1 + $bonus_min_pct)),
                'max' => round($base_max * (1 + $bonus_max_pct)),
                'typical' => round((($base_min + $base_max) / 2) * (1 + $bonus_typical_pct)),
            ),
            'currency' => $estimate['currency'],
            'symbol' => $estimate['symbol'],
            'formatted' => array(
                'base_range' => $estimate['formatted_min'] . ' - ' . $estimate['formatted_max'],
                'total_typical' => $this->format_salary(
                    round((($base_min + $base_max) / 2) * (1 + $bonus_typical_pct)),
                    $estimate['currency']
                ),
            ),
        );
    }

    /**
     * Get available locations for comparison
     */
    public function get_available_locations() {
        $locations = array();

        foreach ($this->location_multipliers as $key => $mult) {
            $currency = $this->location_currencies[$key] ?? 'USD';
            $locations[] = array(
                'value' => $key,
                'label' => ucwords(str_replace('-', ' ', $key)),
                'currency' => $currency,
                'symbol' => $this->currencies[$currency]['symbol'] ?? '$',
            );
        }

        return $locations;
    }

    /**
     * Get industry salary comparison
     */
    public function get_industry_comparison($profile) {
        $experience = $this->parse_experience($profile['years_experience'] ?? '');
        $seniority = $this->detect_seniority($profile['current_role'] ?? '', $profile['target_seniority'] ?? '', $experience);
        $location = $this->get_primary_location($profile['preferred_locations'] ?? array());
        $skills = (array)($profile['skills'] ?? array());

        $base_range = $this->get_seniority_base($seniority);
        $location_mult = $this->location_multipliers[$location] ?? 1.0;
        $skills_mult = $this->calculate_skills_multiplier($skills);
        $currency = $this->location_currencies[$location] ?? 'USD';
        $rate = $this->currencies[$currency]['rate'] ?? 1.0;

        // Top industries to compare
        $industries = array(
            'Investment Banking' => 'investment-banking',
            'Private Equity' => 'private-equity',
            'Asset Management' => 'asset-management',
            'Consulting' => 'consulting',
            'FinTech' => 'fintech',
        );

        $results = array();

        foreach ($industries as $label => $key) {
            $industry_mult = $this->industry_multipliers[$key] ?? 1.0;
            $median = round((($base_range['min'] + $base_range['max']) / 2) * $location_mult * $industry_mult * $skills_mult / $rate);

            $results[] = array(
                'industry' => $label,
                'median' => $median,
                'currency' => $currency,
            );
        }

        // Sort by median descending
        usort($results, function($a, $b) {
            return $b['median'] - $a['median'];
        });

        return $results;
    }

    /**
     * Calculate salary percentile for user
     */
    public function calculate_salary_percentile($profile, $estimate) {
        // Calculate percentile based on:
        // - Experience level
        // - Skills count and quality
        // - Certifications
        // - Education

        $score = 50; // Start at median

        // Experience boost
        $exp = $this->parse_experience($profile['years_experience'] ?? '');
        if ($exp >= 10) {
            $score += 15;
        } elseif ($exp >= 6) {
            $score += 10;
        } elseif ($exp >= 3) {
            $score += 5;
        }

        // Skills boost
        $skills = (array)($profile['skills'] ?? array());
        $high_value_skills = array('financial modeling', 'valuation', 'python', 'm&a', 'lbo', 'private equity');
        $skill_boost = 0;
        foreach ($skills as $skill) {
            $skill_lower = strtolower($skill);
            foreach ($high_value_skills as $hv) {
                if (strpos($skill_lower, $hv) !== false) {
                    $skill_boost += 3;
                }
            }
        }
        $score += min(15, $skill_boost);

        // Certifications boost
        $certs = (array)($profile['certifications'] ?? array());
        $high_value_certs = array('cfa', 'mba', 'cpa', 'frm');
        foreach ($certs as $cert) {
            $cert_lower = strtolower($cert);
            foreach ($high_value_certs as $hvc) {
                if (strpos($cert_lower, $hvc) !== false) {
                    $score += 5;
                    break;
                }
            }
        }

        return min(95, max(5, $score));
    }

    /**
     * Get factors affecting salary estimate
     */
    public function get_salary_factors($profile) {
        $factors = array();

        // Experience factor
        $exp = $this->parse_experience($profile['years_experience'] ?? '');
        if ($exp >= 10) {
            $factors[] = array('label' => 'Senior Experience', 'impact' => 'positive', 'value' => '+15-25%');
        } elseif ($exp < 3) {
            $factors[] = array('label' => 'Early Career', 'impact' => 'neutral', 'value' => 'Base range');
        }

        // Skills factor
        $skills = (array)($profile['skills'] ?? array());
        if (count($skills) >= 5) {
            $factors[] = array('label' => 'Diverse Skill Set', 'impact' => 'positive', 'value' => '+5-10%');
        }

        // Certifications factor
        $certs = (array)($profile['certifications'] ?? array());
        if (!empty($certs)) {
            $factors[] = array('label' => 'Professional Certifications', 'impact' => 'positive', 'value' => '+5-15%');
        }

        // Location factor
        $location = $this->get_primary_location($profile['preferred_locations'] ?? array());
        $mult = $this->location_multipliers[$location] ?? 1.0;
        if ($mult > 1.1) {
            $factors[] = array('label' => 'High Cost Market', 'impact' => 'positive', 'value' => '+' . round(($mult - 1) * 100) . '%');
        }

        return $factors;
    }

    /**
     * Get seniority base salary range
     */
    private function get_seniority_base($seniority) {
        $ranges = array(
            'entry' => array('min' => 45000, 'max' => 70000),
            'junior' => array('min' => 55000, 'max' => 85000),
            'mid' => array('min' => 80000, 'max' => 120000),
            'senior' => array('min' => 120000, 'max' => 180000),
            'lead' => array('min' => 150000, 'max' => 220000),
            'manager' => array('min' => 140000, 'max' => 200000),
            'senior_manager' => array('min' => 170000, 'max' => 250000),
            'director' => array('min' => 200000, 'max' => 320000),
            'vp' => array('min' => 250000, 'max' => 450000),
            'svp' => array('min' => 350000, 'max' => 600000),
            'c_level' => array('min' => 450000, 'max' => 800000),
        );

        return $ranges[$seniority] ?? $ranges['mid'];
    }

    /**
     * Detect seniority from role and experience
     */
    private function detect_seniority($current_role, $target_seniority, $experience) {
        $role_lower = strtolower($current_role . ' ' . $target_seniority);

        // Check for explicit seniority indicators
        if (preg_match('/\b(ceo|cfo|cto|coo|chief)\b/', $role_lower)) {
            return 'c_level';
        }
        if (preg_match('/\b(svp|senior vice president)\b/', $role_lower)) {
            return 'svp';
        }
        if (preg_match('/\b(vp|vice president)\b/', $role_lower)) {
            return 'vp';
        }
        if (preg_match('/\b(director|head of)\b/', $role_lower)) {
            return 'director';
        }
        if (preg_match('/\b(senior manager|principal)\b/', $role_lower)) {
            return 'senior_manager';
        }
        if (preg_match('/\b(manager)\b/', $role_lower)) {
            return 'manager';
        }
        if (preg_match('/\b(lead|team lead)\b/', $role_lower)) {
            return 'lead';
        }
        if (preg_match('/\b(senior|sr\.?)\b/', $role_lower)) {
            return 'senior';
        }
        if (preg_match('/\b(junior|jr\.?|entry|graduate)\b/', $role_lower)) {
            return $experience <= 2 ? 'entry' : 'junior';
        }

        // Fallback to experience-based
        if ($experience >= 15) {
            return 'director';
        } elseif ($experience >= 10) {
            return 'senior_manager';
        } elseif ($experience >= 7) {
            return 'manager';
        } elseif ($experience >= 5) {
            return 'senior';
        } elseif ($experience >= 3) {
            return 'mid';
        } elseif ($experience >= 1) {
            return 'junior';
        }

        return 'entry';
    }

    /**
     * Parse experience string to years
     */
    private function parse_experience($exp_string) {
        if (empty($exp_string)) {
            return 0;
        }

        $mapping = array(
            '0-2' => 1,
            '3-5' => 4,
            '6-10' => 8,
            '11-15' => 13,
            '16+' => 18,
        );

        foreach ($mapping as $range => $years) {
            if (strpos($exp_string, $range) !== false) {
                return $years;
            }
        }

        // Try to extract number
        preg_match('/(\d+)/', $exp_string, $matches);
        return isset($matches[1]) ? intval($matches[1]) : 3;
    }

    /**
     * Get primary location from preferences
     */
    private function get_primary_location($locations) {
        if (empty($locations)) {
            return 'london';
        }

        $locations = (array)$locations;
        $first = strtolower(str_replace(' ', '-', $locations[0]));

        // Normalize location name
        $normalized = preg_replace('/[^a-z-]/', '', $first);

        return $normalized ?: 'london';
    }

    /**
     * Get primary industry from preferences
     */
    private function get_primary_industry($industries) {
        if (empty($industries)) {
            return 'Investment Banking';
        }

        $industries = (array)$industries;
        return $industries[0];
    }

    /**
     * Normalize industry key
     */
    private function normalize_industry_key($industry) {
        return strtolower(str_replace(array(' ', '_'), '-', $industry));
    }

    /**
     * Calculate skills multiplier
     */
    private function calculate_skills_multiplier($skills) {
        if (empty($skills)) {
            return 1.0;
        }

        $high_value = array(
            'financial modeling', 'valuation', 'python', 'sql', 'm&a',
            'lbo', 'private equity', 'due diligence', 'deal execution',
            'machine learning', 'data analysis', 'bloomberg'
        );

        $count = 0;
        foreach ($skills as $skill) {
            $skill_lower = strtolower($skill);
            foreach ($high_value as $hv) {
                if (strpos($skill_lower, $hv) !== false) {
                    $count++;
                    break;
                }
            }
        }

        if ($count >= 4) {
            return 1.20;
        } elseif ($count >= 2) {
            return 1.10;
        } elseif ($count >= 1) {
            return 1.05;
        }

        return 1.0;
    }

    /**
     * Format salary for display
     */
    private function format_salary($amount, $currency) {
        $symbol = $this->currencies[$currency]['symbol'] ?? '$';

        if ($amount >= 1000000) {
            return $symbol . number_format($amount / 1000000, 1) . 'M';
        } elseif ($amount >= 1000) {
            return $symbol . number_format($amount / 1000) . 'k';
        }

        return $symbol . number_format($amount);
    }

    /**
     * Get user profile
     */
    private function get_user_profile($user_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'sffc_user_profiles';
        $profile = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d",
            $user_id
        ), ARRAY_A);

        if (!$profile) {
            return array();
        }

        // Decode JSON fields
        $json_fields = array('preferred_industries', 'preferred_locations', 'skills', 'certifications');
        foreach ($json_fields as $field) {
            if (!empty($profile[$field]) && is_string($profile[$field])) {
                $decoded = json_decode($profile[$field], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $profile[$field] = $decoded;
                }
            }
        }

        // Get skills from separate table
        $skills_table = $wpdb->prefix . 'sffc_user_skills';
        $skills = $wpdb->get_col($wpdb->prepare(
            "SELECT skill_name FROM $skills_table WHERE user_id = %d",
            $user_id
        ));

        if (!empty($skills)) {
            $profile['skills'] = $skills;
        }

        return $profile;
    }

    /**
     * Get default salary data for users without profile
     */
    private function get_default_salary_data() {
        return array(
            'estimate' => array(
                'min' => 60000,
                'max' => 90000,
                'currency' => 'GBP',
                'symbol' => '£',
                'formatted_min' => '£60k',
                'formatted_max' => '£90k',
            ),
            'location_comparison' => array(
                'london' => array('min' => 60000, 'max' => 90000, 'currency' => 'GBP'),
                'new-york' => array('min' => 75000, 'max' => 110000, 'currency' => 'USD'),
            ),
            'industry_data' => array(
                array('industry' => 'Private Equity', 'median' => 95000),
                array('industry' => 'Investment Banking', 'median' => 85000),
                array('industry' => 'Asset Management', 'median' => 75000),
                array('industry' => 'Consulting', 'median' => 70000),
                array('industry' => 'FinTech', 'median' => 68000),
            ),
            'percentile' => 50,
            'factors' => array(),
        );
    }
}

// Initialize
SFFC_Dashboard_Salary_Analyzer::get_instance();
