<?php
/**
 * Currency Manager for Job Opportunities
 * Handles multi-currency support with automatic conversion
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Currency_Manager {
    
    private static $instance = null;
    
    /**
     * Currency configurations by region
     */
    private $currencies = [
        'USD' => [
            'symbol' => '$',
            'name' => 'US Dollar',
            'position' => 'before',
            'decimal_places' => 0,
            'thousands_separator' => ',',
            'countries' => ['US', 'AE', 'SA', 'QA', 'BH', 'KW', 'OM'] // Include Gulf countries
        ],
        'GBP' => [
            'symbol' => '£',
            'name' => 'British Pound',
            'position' => 'before',
            'decimal_places' => 0,
            'thousands_separator' => ',',
            'countries' => ['GB', 'UK']
        ],
        'EUR' => [
            'symbol' => '€',
            'name' => 'Euro',
            'position' => 'before',
            'decimal_places' => 0,
            'thousands_separator' => '.',
            'countries' => ['DE', 'FR', 'IT', 'ES', 'NL', 'BE', 'AT', 'PT', 'IE', 'FI', 'GR', 'LU']
        ],
        'CAD' => [
            'symbol' => 'C$',
            'name' => 'Canadian Dollar',
            'position' => 'before',
            'decimal_places' => 0,
            'thousands_separator' => ',',
            'countries' => ['CA']
        ],
        'AUD' => [
            'symbol' => 'A$',
            'name' => 'Australian Dollar',
            'position' => 'before',
            'decimal_places' => 0,
            'thousands_separator' => ',',
            'countries' => ['AU', 'NZ']
        ],
        'CHF' => [
            'symbol' => 'CHF',
            'name' => 'Swiss Franc',
            'position' => 'before',
            'decimal_places' => 0,
            'thousands_separator' => "'",
            'countries' => ['CH']
        ],
        'SGD' => [
            'symbol' => 'S$',
            'name' => 'Singapore Dollar',
            'position' => 'before',
            'decimal_places' => 0,
            'thousands_separator' => ',',
            'countries' => ['SG']
        ],
        'HKD' => [
            'symbol' => 'HK$',
            'name' => 'Hong Kong Dollar',
            'position' => 'before',
            'decimal_places' => 0,
            'thousands_separator' => ',',
            'countries' => ['HK']
        ],
        'INR' => [
            'symbol' => '₹',
            'name' => 'Indian Rupee',
            'position' => 'before',
            'decimal_places' => 0,
            'thousands_separator' => ',',
            'countries' => ['IN']
        ],
        'BRL' => [
            'symbol' => 'R$',
            'name' => 'Brazilian Real',
            'position' => 'before',
            'decimal_places' => 0,
            'thousands_separator' => '.',
            'countries' => ['BR']
        ],
        'ZAR' => [
            'symbol' => 'R',
            'name' => 'South African Rand',
            'position' => 'before',
            'decimal_places' => 0,
            'thousands_separator' => ',',
            'countries' => ['ZA']
        ],
        'JPY' => [
            'symbol' => '¥',
            'name' => 'Japanese Yen',
            'position' => 'before',
            'decimal_places' => 0,
            'thousands_separator' => ',',
            'countries' => ['JP']
        ],
        'CNY' => [
            'symbol' => '¥',
            'name' => 'Chinese Yuan',
            'position' => 'before',
            'decimal_places' => 0,
            'thousands_separator' => ',',
            'countries' => ['CN']
        ]
    ];
    
    /**
     * Exchange rates (base: USD)
     * In production, these should be fetched from an API
     */
    private $exchange_rates = [
        'USD' => 1.00,
        'GBP' => 0.79,
        'EUR' => 0.92,
        'CAD' => 1.36,
        'AUD' => 1.53,
        'CHF' => 0.88,
        'SGD' => 1.34,
        'HKD' => 7.83,
        'INR' => 83.12,
        'BRL' => 4.97,
        'ZAR' => 18.85,
        'JPY' => 149.50,
        'CNY' => 7.24
    ];
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Initialize hooks
        add_action('wp_ajax_sffc_get_currency_settings', [$this, 'ajax_get_currency_settings']);
        add_action('wp_ajax_nopriv_sffc_get_currency_settings', [$this, 'ajax_get_currency_settings']);
        
        add_action('wp_ajax_sffc_set_currency_preference', [$this, 'ajax_set_currency_preference']);
        add_action('wp_ajax_nopriv_sffc_set_currency_preference', [$this, 'ajax_set_currency_preference']);
        
        // Update exchange rates daily
        add_action('sffc_daily_cron', [$this, 'update_exchange_rates']);
    }
    
    /**
     * Get user's currency based on location or preference
     */
    public function get_user_currency() {
        // Check user preference first
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $saved_currency = get_user_meta($user_id, 'sffc_currency_preference', true);
            if ($saved_currency && isset($this->currencies[$saved_currency])) {
                return $saved_currency;
            }
        }
        
        // Check session/cookie
        if (isset($_COOKIE['sffc_currency'])) {
            $cookie_currency = sanitize_text_field($_COOKIE['sffc_currency']);
            if (isset($this->currencies[$cookie_currency])) {
                return $cookie_currency;
            }
        }
        
        // Detect from IP location
        $country_code = $this->detect_country_from_ip();
        $currency = $this->get_currency_by_country($country_code);
        
        return $currency ?: 'USD'; // Default to USD
    }
    
    /**
     * Detect country from IP address
     */
    private function detect_country_from_ip() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        
        // For localhost/development
        if (in_array($ip, ['127.0.0.1', '::1'])) {
            return 'US';
        }
        
        // Use a geolocation service (example with ipapi.co)
        $response = wp_remote_get("https://ipapi.com/{$ip}/country/");
        if (!is_wp_error($response)) {
            $country = wp_remote_retrieve_body($response);
            if (strlen($country) === 2) {
                return strtoupper($country);
            }
        }
        
        // Fallback: try to detect from browser headers
        $accept_language = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        if (preg_match('/^([a-z]{2})-([A-Z]{2})/', $accept_language, $matches)) {
            return $matches[2];
        }
        
        return 'US'; // Default
    }
    
    /**
     * Get currency code by country
     */
    private function get_currency_by_country($country_code) {
        foreach ($this->currencies as $currency_code => $config) {
            if (in_array($country_code, $config['countries'])) {
                return $currency_code;
            }
        }
        return null;
    }
    
    /**
     * Convert amount between currencies
     */
    public function convert($amount, $from_currency = 'USD', $to_currency = null) {
        if (!$to_currency) {
            $to_currency = $this->get_user_currency();
        }
        
        if ($from_currency === $to_currency) {
            return $amount;
        }
        
        // Convert to USD first (base currency)
        $usd_amount = $amount / $this->exchange_rates[$from_currency];
        
        // Convert to target currency
        $converted = $usd_amount * $this->exchange_rates[$to_currency];
        
        // Round to sensible values for salary ranges
        if ($converted > 10000) {
            $converted = round($converted / 1000) * 1000;
        } elseif ($converted > 1000) {
            $converted = round($converted / 100) * 100;
        }
        
        return $converted;
    }
    
    /**
     * Format currency for display
     */
    public function format($amount, $currency_code = null) {
        if (!$currency_code) {
            $currency_code = $this->get_user_currency();
        }
        
        $config = $this->currencies[$currency_code];
        
        // Format number
        $formatted = number_format(
            $amount,
            $config['decimal_places'],
            '.',
            $config['thousands_separator']
        );
        
        // Abbreviate large numbers
        if ($amount >= 1000000) {
            $formatted = number_format($amount / 1000000, 1) . 'M';
        } elseif ($amount >= 1000) {
            $formatted = number_format($amount / 1000, 0) . 'k';
        }
        
        // Add symbol
        if ($config['position'] === 'before') {
            return $config['symbol'] . $formatted;
        } else {
            return $formatted . $config['symbol'];
        }
    }
    
    /**
     * Format salary range for display
     */
    public function format_salary_range($min, $max, $currency_code = null, $original_currency = 'USD') {
        if (!$currency_code) {
            $currency_code = $this->get_user_currency();
        }
        
        // Convert if needed
        if ($original_currency !== $currency_code) {
            $min = $this->convert($min, $original_currency, $currency_code);
            $max = $this->convert($max, $original_currency, $currency_code);
        }
        
        if (!$min && !$max) {
            return 'Competitive';
        }
        
        if (!$max || $max === $min) {
            return $this->format($min, $currency_code) . '+';
        }
        
        if (!$min) {
            return 'Up to ' . $this->format($max, $currency_code);
        }
        
        return $this->format($min, $currency_code) . ' - ' . $this->format($max, $currency_code);
    }
    
    /**
     * AJAX: Get currency settings for frontend
     */
    public function ajax_get_currency_settings() {
        $current_currency = $this->get_user_currency();
        $available_currencies = [];
        
        foreach ($this->currencies as $code => $config) {
            $available_currencies[] = [
                'code' => $code,
                'symbol' => $config['symbol'],
                'name' => $config['name']
            ];
        }
        
        wp_send_json_success([
            'current' => $current_currency,
            'currencies' => $available_currencies,
            'rates' => $this->exchange_rates
        ]);
    }
    
    /**
     * AJAX: Set currency preference
     */
    public function ajax_set_currency_preference() {
        $currency = sanitize_text_field($_POST['currency'] ?? '');
        
        if (!isset($this->currencies[$currency])) {
            wp_send_json_error(['message' => 'Invalid currency']);
            return;
        }
        
        // Save to user meta if logged in
        if (is_user_logged_in()) {
            update_user_meta(get_current_user_id(), 'sffc_currency_preference', $currency);
        }
        
        // Set cookie for 30 days
        setcookie('sffc_currency', $currency, time() + (30 * DAY_IN_SECONDS), '/', '', true, true);
        
        wp_send_json_success([
            'currency' => $currency,
            'message' => 'Currency preference updated'
        ]);
    }
    
    /**
     * Update exchange rates from API
     */
    public function update_exchange_rates() {
        // In production, fetch from a reliable API like exchangerate-api.co
        $api_key = get_option('sffc_exchange_rate_api_key');
        if (!$api_key) {
            return;
        }
        
        $response = wp_remote_get("https://api.exchangerate-api.com/v4/latest/USD");
        if (!is_wp_error($response)) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($data['rates'])) {
                // Update rates in database
                update_option('sffc_exchange_rates', $data['rates']);
                $this->exchange_rates = array_merge($this->exchange_rates, $data['rates']);
            }
        }
    }
}

// Initialize
SFFC_Currency_Manager::get_instance();