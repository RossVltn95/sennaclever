<?php
/**
 * Mobility Data Service
 *
 * Handles data loading and comparison for Career Mobility Platform.
 * Uses CSV files with data from free government/open sources.
 *
 * @package Senna_Careers
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Mobility_Data_Service {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Data directory path
     */
    private $data_dir;

    /**
     * Cached data arrays
     */
    private $cost_of_living_data = null;
    private $tax_rates_data = null;
    private $visa_requirements_data = null;
    private $quality_of_life_data = null;
    private $city_country_map = null;

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
        $this->data_dir = dirname(__FILE__) . '/data/';
    }

    /**
     * Detect location from job post
     *
     * @param int|WP_Post $job Job post ID or object
     * @return array Location data with city and country
     */
    public function detect_job_location($job) {
        $job_id = is_object($job) ? $job->ID : intval($job);

        $location = array(
            'city' => '',
            'country' => '',
            'detected_from' => '',
        );

        // First try: Get from job meta
        $city = get_post_meta($job_id, 'sffc_location_city', true);
        $country = get_post_meta($job_id, 'sffc_location_country', true);

        if (!empty($city) || !empty($country)) {
            $location['city'] = sanitize_text_field($city);
            $location['country'] = sanitize_text_field($country);
            $location['detected_from'] = 'meta';
            return $location;
        }

        // Second try: Scan job description for location patterns
        $job_post = get_post($job_id);
        if ($job_post) {
            $content = $job_post->post_content . ' ' . $job_post->post_title;
            $detected = $this->extract_location_from_text($content);

            if (!empty($detected['city']) || !empty($detected['country'])) {
                $location['city'] = $detected['city'];
                $location['country'] = $detected['country'];
                $location['detected_from'] = 'description';
                return $location;
            }
        }

        // Third try: Check for location in custom fields
        $location_field = get_post_meta($job_id, 'sffc_job_location', true);
        if (!empty($location_field)) {
            $detected = $this->extract_location_from_text($location_field);
            $location['city'] = $detected['city'];
            $location['country'] = $detected['country'];
            $location['detected_from'] = 'custom_field';
        }

        return $location;
    }

    /**
     * Extract location from text using pattern matching
     *
     * @param string $text Text to scan
     * @return array City and country
     */
    private function extract_location_from_text($text) {
        $result = array(
            'city' => '',
            'country' => '',
        );

        if (empty($text)) {
            return $result;
        }

        // Load city-country mapping for detection
        $city_map = $this->get_city_country_map();

        $text_lower = strtolower($text);

        // Check for city matches first (more specific)
        foreach ($city_map as $city => $country) {
            if (stripos($text, $city) !== false) {
                $result['city'] = $city;
                $result['country'] = $country;
                return $result;
            }
        }

        // Check for country matches
        $countries = array_unique(array_values($city_map));
        foreach ($countries as $country) {
            if (stripos($text, $country) !== false) {
                $result['country'] = $country;
                return $result;
            }
        }

        // Pattern matching for common location formats
        // "Location: City, Country" or "Based in City"
        $patterns = array(
            '/(?:location|based|office|headquarters?)[\s:]+([A-Za-z\s]+),\s*([A-Za-z\s]+)/i',
            '/(?:in|at)\s+([A-Za-z\s]+),\s*([A-Za-z\s]+)/i',
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                if (isset($matches[1])) {
                    $result['city'] = trim($matches[1]);
                }
                if (isset($matches[2])) {
                    $result['country'] = trim($matches[2]);
                }
                if (!empty($result['city']) || !empty($result['country'])) {
                    return $result;
                }
            }
        }

        return $result;
    }

    /**
     * Get city to country mapping
     *
     * @return array City => Country mapping
     */
    private function get_city_country_map() {
        if ($this->city_country_map !== null) {
            return $this->city_country_map;
        }

        // Major financial/professional cities
        $this->city_country_map = array(
            // United States
            'New York' => 'United States',
            'Los Angeles' => 'United States',
            'Chicago' => 'United States',
            'San Francisco' => 'United States',
            'Boston' => 'United States',
            'Seattle' => 'United States',
            'Washington D.C.' => 'United States',
            'Miami' => 'United States',
            'Houston' => 'United States',
            'Dallas' => 'United States',
            'Atlanta' => 'United States',
            'Denver' => 'United States',
            'Austin' => 'United States',
            'Philadelphia' => 'United States',

            // United Kingdom
            'London' => 'United Kingdom',
            'Manchester' => 'United Kingdom',
            'Birmingham' => 'United Kingdom',
            'Edinburgh' => 'United Kingdom',
            'Glasgow' => 'United Kingdom',
            'Bristol' => 'United Kingdom',
            'Leeds' => 'United Kingdom',

            // Germany
            'Frankfurt' => 'Germany',
            'Berlin' => 'Germany',
            'Munich' => 'Germany',
            'Hamburg' => 'Germany',
            'Düsseldorf' => 'Germany',
            'Cologne' => 'Germany',

            // France
            'Paris' => 'France',
            'Lyon' => 'France',
            'Marseille' => 'France',

            // Switzerland
            'Zurich' => 'Switzerland',
            'Geneva' => 'Switzerland',
            'Basel' => 'Switzerland',

            // Netherlands
            'Amsterdam' => 'Netherlands',
            'Rotterdam' => 'Netherlands',
            'The Hague' => 'Netherlands',

            // Singapore
            'Singapore' => 'Singapore',

            // Hong Kong
            'Hong Kong' => 'Hong Kong',

            // Japan
            'Tokyo' => 'Japan',
            'Osaka' => 'Japan',

            // Australia
            'Sydney' => 'Australia',
            'Melbourne' => 'Australia',
            'Brisbane' => 'Australia',
            'Perth' => 'Australia',

            // Canada
            'Toronto' => 'Canada',
            'Vancouver' => 'Canada',
            'Montreal' => 'Canada',
            'Calgary' => 'Canada',

            // Ireland
            'Dublin' => 'Ireland',

            // Luxembourg
            'Luxembourg' => 'Luxembourg',

            // Spain
            'Madrid' => 'Spain',
            'Barcelona' => 'Spain',

            // Italy
            'Milan' => 'Italy',
            'Rome' => 'Italy',

            // Belgium
            'Brussels' => 'Belgium',

            // Austria
            'Vienna' => 'Austria',

            // Sweden
            'Stockholm' => 'Sweden',

            // Denmark
            'Copenhagen' => 'Denmark',

            // Norway
            'Oslo' => 'Norway',

            // Finland
            'Helsinki' => 'Finland',

            // UAE
            'Dubai' => 'United Arab Emirates',
            'Abu Dhabi' => 'United Arab Emirates',

            // India
            'Mumbai' => 'India',
            'Bangalore' => 'India',
            'Delhi' => 'India',
            'Hyderabad' => 'India',

            // China
            'Shanghai' => 'China',
            'Beijing' => 'China',
            'Shenzhen' => 'China',

            // South Korea
            'Seoul' => 'South Korea',

            // Brazil
            'São Paulo' => 'Brazil',
            'Rio de Janeiro' => 'Brazil',

            // Mexico
            'Mexico City' => 'Mexico',

            // South Africa
            'Johannesburg' => 'South Africa',
            'Cape Town' => 'South Africa',

            // Poland
            'Warsaw' => 'Poland',
            'Krakow' => 'Poland',

            // Czech Republic
            'Prague' => 'Czech Republic',

            // Portugal
            'Lisbon' => 'Portugal',
        );

        return $this->city_country_map;
    }

    /**
     * Load cost of living data from CSV
     *
     * @return array Cost of living data indexed by city/country
     */
    public function get_cost_of_living_data() {
        if ($this->cost_of_living_data !== null) {
            return $this->cost_of_living_data;
        }

        $file = $this->data_dir . 'cost_of_living.csv';
        $this->cost_of_living_data = $this->load_csv_data($file, 'location');

        return $this->cost_of_living_data;
    }

    /**
     * Load tax rates data from CSV
     *
     * @return array Tax rates data indexed by country
     */
    public function get_tax_rates_data() {
        if ($this->tax_rates_data !== null) {
            return $this->tax_rates_data;
        }

        $file = $this->data_dir . 'tax_rates.csv';
        $this->tax_rates_data = $this->load_csv_data($file, 'country');

        return $this->tax_rates_data;
    }

    /**
     * Load visa requirements data from CSV
     *
     * @return array Visa requirements indexed by country
     */
    public function get_visa_requirements_data() {
        if ($this->visa_requirements_data !== null) {
            return $this->visa_requirements_data;
        }

        $file = $this->data_dir . 'visa_requirements.csv';
        $this->visa_requirements_data = $this->load_csv_data($file, 'country');

        return $this->visa_requirements_data;
    }

    /**
     * Load quality of life data from CSV
     *
     * @return array Quality of life data indexed by city/country
     */
    public function get_quality_of_life_data() {
        if ($this->quality_of_life_data !== null) {
            return $this->quality_of_life_data;
        }

        $file = $this->data_dir . 'quality_of_life.csv';
        $this->quality_of_life_data = $this->load_csv_data($file, 'location');

        return $this->quality_of_life_data;
    }

    /**
     * Load CSV data into array
     *
     * @param string $file Path to CSV file
     * @param string $key_column Column to use as array key
     * @return array Parsed data
     */
    private function load_csv_data($file, $key_column) {
        $data = array();

        if (!file_exists($file)) {
            return $data;
        }

        $handle = fopen($file, 'r');
        if ($handle === false) {
            return $data;
        }

        // Get headers (PHP 8.4+ requires escape parameter)
        $headers = fgetcsv($handle, 0, ',', '"', '\\');
        if ($headers === false) {
            fclose($handle);
            return $data;
        }

        // Clean headers
        $headers = array_map('trim', $headers);
        $headers = array_map('strtolower', $headers);

        $key_index = array_search(strtolower($key_column), $headers);
        if ($key_index === false) {
            $key_index = 0; // Default to first column
        }

        // Parse rows
        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            if (count($row) < count($headers)) {
                continue;
            }

            $row_data = array();
            foreach ($headers as $i => $header) {
                $row_data[$header] = isset($row[$i]) ? trim($row[$i]) : '';
            }

            $key = strtolower(trim($row[$key_index]));
            if (!empty($key)) {
                $data[$key] = $row_data;
            }
        }

        fclose($handle);
        return $data;
    }

    /**
     * Get cost of living for a location
     *
     * @param string $city City name
     * @param string $country Country name
     * @return array|null Cost of living data or null
     */
    public function get_location_cost_of_living($city, $country) {
        $data = $this->get_cost_of_living_data();

        // Try city first
        if (!empty($city)) {
            $city_key = strtolower($city);
            if (isset($data[$city_key])) {
                return $data[$city_key];
            }
        }

        // Fall back to country
        if (!empty($country)) {
            $country_key = strtolower($country);
            if (isset($data[$country_key])) {
                return $data[$country_key];
            }
        }

        return null;
    }

    /**
     * Get tax rates for a country
     *
     * @param string $country Country name
     * @return array|null Tax rates data or null
     */
    public function get_country_tax_rates($country) {
        if (empty($country)) {
            return null;
        }

        $data = $this->get_tax_rates_data();
        $country_key = strtolower($country);

        return isset($data[$country_key]) ? $data[$country_key] : null;
    }

    /**
     * Get visa requirements for a country
     *
     * @param string $country Country name
     * @return array|null Visa requirements or null
     */
    public function get_country_visa_requirements($country) {
        if (empty($country)) {
            return null;
        }

        $data = $this->get_visa_requirements_data();
        $country_key = strtolower($country);

        return isset($data[$country_key]) ? $data[$country_key] : null;
    }

    /**
     * Get quality of life for a location
     *
     * @param string $city City name
     * @param string $country Country name
     * @return array|null Quality of life data or null
     */
    public function get_location_quality_of_life($city, $country) {
        $data = $this->get_quality_of_life_data();

        // Try city first
        if (!empty($city)) {
            $city_key = strtolower($city);
            if (isset($data[$city_key])) {
                return $data[$city_key];
            }
        }

        // Fall back to country
        if (!empty($country)) {
            $country_key = strtolower($country);
            if (isset($data[$country_key])) {
                return $data[$country_key];
            }
        }

        return null;
    }

    /**
     * Compare two locations for mobility analysis
     *
     * @param array $from_location Origin location (city, country)
     * @param array $to_location Destination location (city, country)
     * @return array Comparison data
     */
    public function compare_locations($from_location, $to_location) {
        $comparison = array(
            'from' => $from_location,
            'to' => $to_location,
            'cost_of_living' => $this->compare_cost_of_living($from_location, $to_location),
            'tax_rates' => $this->compare_tax_rates($from_location, $to_location),
            'visa_requirements' => $this->get_country_visa_requirements($to_location['country']),
            'quality_of_life' => $this->compare_quality_of_life($from_location, $to_location),
            'mobility_score' => 0,
        );

        // Calculate overall mobility score
        $comparison['mobility_score'] = $this->calculate_mobility_score($comparison);

        return $comparison;
    }

    /**
     * Compare cost of living between two locations
     *
     * @param array $from Origin location
     * @param array $to Destination location
     * @return array Cost of living comparison
     */
    private function compare_cost_of_living($from, $to) {
        $from_data = $this->get_location_cost_of_living($from['city'], $from['country']);
        $to_data = $this->get_location_cost_of_living($to['city'], $to['country']);

        $result = array(
            'from' => $from_data,
            'to' => $to_data,
            'difference' => null,
            'percentage_change' => null,
            'direction' => 'unknown',
        );

        if ($from_data && $to_data) {
            $from_index = isset($from_data['cost_index']) ? floatval($from_data['cost_index']) : 0;
            $to_index = isset($to_data['cost_index']) ? floatval($to_data['cost_index']) : 0;

            if ($from_index > 0) {
                $result['difference'] = $to_index - $from_index;
                $result['percentage_change'] = round((($to_index - $from_index) / $from_index) * 100, 1);
                $result['direction'] = $to_index > $from_index ? 'higher' : ($to_index < $from_index ? 'lower' : 'same');
            }
        }

        return $result;
    }

    /**
     * Compare tax rates between two countries
     *
     * @param array $from Origin location
     * @param array $to Destination location
     * @return array Tax rate comparison
     */
    private function compare_tax_rates($from, $to) {
        $from_data = $this->get_country_tax_rates($from['country']);
        $to_data = $this->get_country_tax_rates($to['country']);

        $result = array(
            'from' => $from_data,
            'to' => $to_data,
            'income_tax_difference' => null,
            'direction' => 'unknown',
        );

        if ($from_data && $to_data) {
            $from_rate = isset($from_data['top_income_tax_rate']) ? floatval($from_data['top_income_tax_rate']) : 0;
            $to_rate = isset($to_data['top_income_tax_rate']) ? floatval($to_data['top_income_tax_rate']) : 0;

            $result['income_tax_difference'] = $to_rate - $from_rate;
            $result['direction'] = $to_rate > $from_rate ? 'higher' : ($to_rate < $from_rate ? 'lower' : 'same');
        }

        return $result;
    }

    /**
     * Compare quality of life between two locations
     *
     * @param array $from Origin location
     * @param array $to Destination location
     * @return array Quality of life comparison
     */
    private function compare_quality_of_life($from, $to) {
        $from_data = $this->get_location_quality_of_life($from['city'], $from['country']);
        $to_data = $this->get_location_quality_of_life($to['city'], $to['country']);

        $result = array(
            'from' => $from_data,
            'to' => $to_data,
            'score_difference' => null,
            'direction' => 'unknown',
        );

        if ($from_data && $to_data) {
            $from_score = isset($from_data['quality_score']) ? floatval($from_data['quality_score']) : 0;
            $to_score = isset($to_data['quality_score']) ? floatval($to_data['quality_score']) : 0;

            $result['score_difference'] = $to_score - $from_score;
            $result['direction'] = $to_score > $from_score ? 'better' : ($to_score < $from_score ? 'worse' : 'same');
        }

        return $result;
    }

    /**
     * Calculate overall mobility score
     *
     * Higher score = more favorable move
     *
     * @param array $comparison Full comparison data
     * @return int Score 0-100
     */
    private function calculate_mobility_score($comparison) {
        $score = 50; // Start neutral

        // Cost of living factor (-15 to +15)
        if (isset($comparison['cost_of_living']['percentage_change'])) {
            $col_change = $comparison['cost_of_living']['percentage_change'];
            // Lower cost is better
            $col_factor = min(15, max(-15, -$col_change / 3));
            $score += $col_factor;
        }

        // Tax rate factor (-10 to +10)
        if (isset($comparison['tax_rates']['income_tax_difference'])) {
            $tax_diff = $comparison['tax_rates']['income_tax_difference'];
            // Lower tax is better
            $tax_factor = min(10, max(-10, -$tax_diff / 2));
            $score += $tax_factor;
        }

        // Quality of life factor (-15 to +15)
        if (isset($comparison['quality_of_life']['score_difference'])) {
            $qol_diff = $comparison['quality_of_life']['score_difference'];
            // Higher quality is better
            $qol_factor = min(15, max(-15, $qol_diff / 3));
            $score += $qol_factor;
        }

        // Visa requirements factor (-10 to 0)
        if ($comparison['visa_requirements']) {
            $visa = $comparison['visa_requirements'];
            $difficulty = isset($visa['difficulty']) ? strtolower($visa['difficulty']) : '';

            switch ($difficulty) {
                case 'easy':
                    $score += 5;
                    break;
                case 'moderate':
                    $score += 0;
                    break;
                case 'difficult':
                    $score -= 5;
                    break;
                case 'very difficult':
                    $score -= 10;
                    break;
            }
        }

        return max(0, min(100, round($score)));
    }

    /**
     * Get user's current location from profile
     *
     * @param int $user_id User ID
     * @return array Location data
     */
    public function get_user_location($user_id) {
        $location = array(
            'city' => '',
            'country' => '',
            'detected_from' => 'profile',
        );

        // Try user meta
        $city = get_user_meta($user_id, 'sffc_city', true);
        $country = get_user_meta($user_id, 'sffc_country', true);

        if (!empty($city)) {
            $location['city'] = sanitize_text_field($city);
        }
        if (!empty($country)) {
            $location['country'] = sanitize_text_field($country);
        }

        // Try billing address as fallback
        if (empty($location['city']) && empty($location['country'])) {
            $billing_city = get_user_meta($user_id, 'billing_city', true);
            $billing_country = get_user_meta($user_id, 'billing_country', true);

            if (!empty($billing_city)) {
                $location['city'] = sanitize_text_field($billing_city);
            }
            if (!empty($billing_country)) {
                // WooCommerce uses country codes, convert if needed
                $location['country'] = $this->get_country_name($billing_country);
                $location['detected_from'] = 'billing';
            }
        }

        return $location;
    }

    /**
     * Convert country code to name
     *
     * @param string $code Country code (e.g., US, GB)
     * @return string Country name
     */
    private function get_country_name($code) {
        $countries = array(
            'US' => 'United States',
            'GB' => 'United Kingdom',
            'DE' => 'Germany',
            'FR' => 'France',
            'CH' => 'Switzerland',
            'NL' => 'Netherlands',
            'SG' => 'Singapore',
            'HK' => 'Hong Kong',
            'JP' => 'Japan',
            'AU' => 'Australia',
            'CA' => 'Canada',
            'IE' => 'Ireland',
            'LU' => 'Luxembourg',
            'ES' => 'Spain',
            'IT' => 'Italy',
            'BE' => 'Belgium',
            'AT' => 'Austria',
            'SE' => 'Sweden',
            'DK' => 'Denmark',
            'NO' => 'Norway',
            'FI' => 'Finland',
            'AE' => 'United Arab Emirates',
            'IN' => 'India',
            'CN' => 'China',
            'KR' => 'South Korea',
            'BR' => 'Brazil',
            'MX' => 'Mexico',
            'ZA' => 'South Africa',
            'PL' => 'Poland',
            'CZ' => 'Czech Republic',
            'PT' => 'Portugal',
        );

        $code = strtoupper($code);
        return isset($countries[$code]) ? $countries[$code] : $code;
    }

    /**
     * Get mobility badge data for a job
     *
     * @param int $job_id Job ID
     * @param int|null $user_id User ID (optional, uses current user)
     * @return array Badge data
     */
    public function get_mobility_badge_data($job_id, $user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        $badge = array(
            'show' => false,
            'label' => '',
            'type' => '', // favorable, neutral, challenging
            'score' => 0,
            'requires_relocation' => false,
        );

        if (!$user_id) {
            return $badge;
        }

        $user_location = $this->get_user_location($user_id);
        $job_location = $this->detect_job_location($job_id);

        // Check if relocation is needed
        if (empty($user_location['city']) && empty($user_location['country'])) {
            return $badge; // Can't determine without user location
        }

        if (empty($job_location['city']) && empty($job_location['country'])) {
            return $badge; // Can't determine without job location
        }

        // Same location - no relocation needed
        $same_city = strtolower($user_location['city']) === strtolower($job_location['city']);
        $same_country = strtolower($user_location['country']) === strtolower($job_location['country']);

        if ($same_city && $same_country) {
            $badge['show'] = true;
            $badge['label'] = 'Local Opportunity';
            $badge['type'] = 'local';
            $badge['requires_relocation'] = false;
            return $badge;
        }

        // Different location - calculate mobility score
        $badge['requires_relocation'] = true;
        $comparison = $this->compare_locations($user_location, $job_location);
        $badge['score'] = $comparison['mobility_score'];

        $badge['show'] = true;

        if ($comparison['mobility_score'] >= 65) {
            $badge['label'] = 'Favorable Move';
            $badge['type'] = 'favorable';
        } elseif ($comparison['mobility_score'] >= 40) {
            $badge['label'] = 'Relocation Required';
            $badge['type'] = 'neutral';
        } else {
            $badge['label'] = 'Consider Carefully';
            $badge['type'] = 'challenging';
        }

        return $badge;
    }

    /**
     * Get full mobility analysis for job page
     *
     * @param int $job_id Job ID
     * @param int|null $user_id User ID
     * @return array Full mobility analysis
     */
    public function get_mobility_analysis($job_id, $user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        $analysis = array(
            'available' => false,
            'user_location' => null,
            'job_location' => null,
            'comparison' => null,
            'insights' => array(),
        );

        $user_location = $this->get_user_location($user_id);
        $job_location = $this->detect_job_location($job_id);

        $analysis['user_location'] = $user_location;
        $analysis['job_location'] = $job_location;

        // Need both locations for analysis
        if ((empty($user_location['city']) && empty($user_location['country'])) ||
            (empty($job_location['city']) && empty($job_location['country']))) {
            return $analysis;
        }

        $analysis['available'] = true;
        $analysis['comparison'] = $this->compare_locations($user_location, $job_location);

        // Generate insights
        $analysis['insights'] = $this->generate_mobility_insights($analysis['comparison']);

        return $analysis;
    }

    /**
     * Generate human-readable mobility insights
     *
     * @param array $comparison Comparison data
     * @return array Insights
     */
    private function generate_mobility_insights($comparison) {
        $insights = array();

        // Cost of living insight
        if (isset($comparison['cost_of_living']['percentage_change'])) {
            $change = $comparison['cost_of_living']['percentage_change'];
            if ($change > 10) {
                $insights[] = array(
                    'type' => 'warning',
                    'category' => 'cost_of_living',
                    'message' => sprintf('Cost of living is %d%% higher than your current location', abs($change)),
                );
            } elseif ($change < -10) {
                $insights[] = array(
                    'type' => 'positive',
                    'category' => 'cost_of_living',
                    'message' => sprintf('Cost of living is %d%% lower than your current location', abs($change)),
                );
            } else {
                $insights[] = array(
                    'type' => 'neutral',
                    'category' => 'cost_of_living',
                    'message' => 'Cost of living is similar to your current location',
                );
            }
        }

        // Tax insight
        if (isset($comparison['tax_rates']['income_tax_difference'])) {
            $diff = $comparison['tax_rates']['income_tax_difference'];
            if ($diff > 5) {
                $insights[] = array(
                    'type' => 'warning',
                    'category' => 'tax',
                    'message' => sprintf('Top tax rate is %d%% higher', abs($diff)),
                );
            } elseif ($diff < -5) {
                $insights[] = array(
                    'type' => 'positive',
                    'category' => 'tax',
                    'message' => sprintf('Top tax rate is %d%% lower', abs($diff)),
                );
            }
        }

        // Visa insight
        if ($comparison['visa_requirements']) {
            $visa = $comparison['visa_requirements'];
            $difficulty = isset($visa['difficulty']) ? $visa['difficulty'] : '';
            $work_visa = isset($visa['work_visa_type']) ? $visa['work_visa_type'] : '';

            if ($difficulty) {
                $type = strtolower($difficulty) === 'easy' ? 'positive' :
                       (strtolower($difficulty) === 'moderate' ? 'neutral' : 'warning');
                $insights[] = array(
                    'type' => $type,
                    'category' => 'visa',
                    'message' => sprintf('Work visa process: %s%s',
                        $difficulty,
                        $work_visa ? ' (' . $work_visa . ')' : ''
                    ),
                );
            }
        }

        // Quality of life insight
        if (isset($comparison['quality_of_life']['direction'])) {
            $direction = $comparison['quality_of_life']['direction'];
            if ($direction === 'better') {
                $insights[] = array(
                    'type' => 'positive',
                    'category' => 'quality_of_life',
                    'message' => 'Quality of life indicators are higher',
                );
            } elseif ($direction === 'worse') {
                $insights[] = array(
                    'type' => 'warning',
                    'category' => 'quality_of_life',
                    'message' => 'Quality of life indicators are lower',
                );
            }
        }

        return $insights;
    }
}
