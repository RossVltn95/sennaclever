<?php
/**
 * European Market Data Fetcher
 * 
 * @package SennaCareers
 * @subpackage EuropeanMarkets
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_European_Market_Fetcher {
    
    /**
     * API endpoints for market data
     */
    private $api_endpoints = array(
        'yahoo' => 'https://query1.finance.yahoo.com/v8/finance/chart/',
        'alpha_vantage' => 'https://www.alphavantage.com/query',
        'ecb' => 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml'
    );
    
    /**
     * European market symbols
     */
    private $european_symbols = array(
        'FTSE' => array('symbol' => '^FTSE', 'name' => 'FTSE 100', 'exchange' => 'LSE'),
        'DAX' => array('symbol' => '^GDAXI', 'name' => 'DAX', 'exchange' => 'Frankfurt'),
        'CAC' => array('symbol' => '^FCHI', 'name' => 'CAC 40', 'exchange' => 'Euronext Paris'),
        'STOXX50' => array('symbol' => '^STOXX50E', 'name' => 'EURO STOXX 50', 'exchange' => 'Multiple'),
        'STOXX600' => array('symbol' => '^STOXX', 'name' => 'STOXX Europe 600', 'exchange' => 'Multiple'),
        'IBEX' => array('symbol' => '^IBEX', 'name' => 'IBEX 35', 'exchange' => 'BME'),
        'FTMIB' => array('symbol' => '^FTMIB', 'name' => 'FTSE MIB', 'exchange' => 'Borsa Italiana'),
        'AEX' => array('symbol' => '^AEX', 'name' => 'AEX', 'exchange' => 'Euronext Amsterdam'),
        'BEL20' => array('symbol' => '^BFX', 'name' => 'BEL 20', 'exchange' => 'Euronext Brussels'),
        'SMI' => array('symbol' => '^SSMI', 'name' => 'SMI', 'exchange' => 'SIX')
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        // Initialize hooks
        add_action('sffc_fetch_european_markets', array($this, 'fetch_all_markets'));
    }
    
    /**
     * Fetch all European market data
     */
    public function fetch_all_markets() {
        foreach ($this->european_symbols as $key => $market) {
            $this->fetch_market_data($market['symbol']);
        }
        
        // Update last fetch time
        update_option('sffc_european_markets_last_fetch', current_time('mysql'));
    }
    
    /**
     * Fetch market data for a specific symbol
     */
    public function fetch_market_data($symbol) {
        // Try Yahoo Finance first
        $data = $this->fetch_yahoo_data($symbol);
        
        if (!$data) {
            // Fallback to Alpha Vantage
            $data = $this->fetch_alpha_vantage_data($symbol);
        }
        
        if ($data) {
            $this->save_market_data($symbol, $data);
        }
        
        return $data;
    }
    
    /**
     * Fetch data from Yahoo Finance
     */
    private function fetch_yahoo_data($symbol) {
        $url = $this->api_endpoints['yahoo'] . $symbol;
        
        $args = array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            )
        );
        
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            error_log('SFFC Yahoo Finance Error: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['chart']['result'][0])) {
            return false;
        }
        
        $result = $data['chart']['result'][0];
        $meta = $result['meta'];
        $quote = $result['indicators']['quote'][0];
        
        // Extract relevant data
        $market_data = array(
            'symbol' => $symbol,
            'price' => $meta['regularMarketPrice'] ?? null,
            'previous_close' => $meta['previousClose'] ?? null,
            'change_amount' => isset($meta['regularMarketPrice']) && isset($meta['previousClose']) 
                ? $meta['regularMarketPrice'] - $meta['previousClose'] : null,
            'change_percent' => isset($meta['regularMarketPrice']) && isset($meta['previousClose']) && $meta['previousClose'] > 0
                ? (($meta['regularMarketPrice'] - $meta['previousClose']) / $meta['previousClose']) * 100 : null,
            'volume' => end($quote['volume']) ?? null,
            'high' => max($quote['high']) ?? null,
            'low' => min(array_filter($quote['low'])) ?? null,
            'open' => $quote['open'][0] ?? null,
            'currency' => $meta['currency'] ?? 'USD',
            'exchange' => $meta['exchangeName'] ?? null,
            'data_source' => 'yahoo',
            'timestamp' => current_time('mysql')
        );
        
        return $market_data;
    }
    
    /**
     * Fetch data from Alpha Vantage
     */
    private function fetch_alpha_vantage_data($symbol) {
        $api_key = get_option('sffc_alpha_vantage_api_key');
        
        if (!$api_key) {
            return false;
        }
        
        $url = add_query_arg(array(
            'function' => 'GLOBAL_QUOTE',
            'symbol' => $symbol,
            'apikey' => $api_key
        ), $this->api_endpoints['alpha_vantage']);
        
        $response = wp_remote_get($url);
        
        if (is_wp_error($response)) {
            error_log('SFFC Alpha Vantage Error: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['Global Quote'])) {
            return false;
        }
        
        $quote = $data['Global Quote'];
        
        // Extract relevant data
        $market_data = array(
            'symbol' => $symbol,
            'price' => floatval($quote['05. price']) ?? null,
            'previous_close' => floatval($quote['08. previous close']) ?? null,
            'change_amount' => floatval($quote['09. change']) ?? null,
            'change_percent' => floatval(str_replace('%', '', $quote['10. change percent'])) ?? null,
            'volume' => intval($quote['06. volume']) ?? null,
            'high' => floatval($quote['03. high']) ?? null,
            'low' => floatval($quote['04. low']) ?? null,
            'open' => floatval($quote['02. open']) ?? null,
            'data_source' => 'alpha_vantage',
            'timestamp' => current_time('mysql')
        );
        
        return $market_data;
    }
    
    /**
     * Save market data to database
     */
    private function save_market_data($symbol, $data) {
        global $wpdb;
        
        require_once SFFC_PLUGIN_DIR . 'includes/class-european-database.php';
        $eu_db = SFFC_European_Database::get_instance();
        
        $table = $eu_db->get_table('market_cache');
        
        if (!$table) {
            return false;
        }
        
        // Get additional market info
        $market_info = $this->get_market_info($symbol);
        
        // Prepare data for insertion
        $insert_data = array(
            'symbol' => $data['symbol'],
            'exchange' => $data['exchange'] ?? $market_info['exchange'] ?? null,
            'market_type' => 'index',
            'region' => 'Europe',
            'price' => $data['price'],
            'previous_close' => $data['previous_close'],
            'open' => $data['open'],
            'high' => $data['high'],
            'low' => $data['low'],
            'volume' => $data['volume'],
            'change_amount' => $data['change_amount'],
            'change_percent' => $data['change_percent'],
            'currency' => $data['currency'] ?? $market_info['currency'] ?? 'EUR',
            'data_source' => $data['data_source'],
            'last_updated' => $data['timestamp']
        );
        
        // Check if record exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE symbol = %s",
            $symbol
        ));
        
        if ($existing) {
            // Update existing record
            $result = $wpdb->update(
                $table,
                $insert_data,
                array('symbol' => $symbol)
            );
        } else {
            // Insert new record
            $result = $wpdb->insert($table, $insert_data);
        }
        
        // Also save to intraday table for historical tracking
        $this->save_intraday_price($symbol, $data['price'], $data['volume']);
        
        return $result !== false;
    }
    
    /**
     * Save intraday price data
     */
    private function save_intraday_price($symbol, $price, $volume) {
        global $wpdb;
        
        require_once SFFC_PLUGIN_DIR . 'includes/class-european-database.php';
        $eu_db = SFFC_European_Database::get_instance();
        
        $table = $eu_db->get_table('intraday_prices');
        
        if (!$table || !$price) {
            return false;
        }
        
        return $wpdb->insert(
            $table,
            array(
                'symbol' => $symbol,
                'timestamp' => current_time('mysql'),
                'price' => $price,
                'volume' => $volume
            )
        );
    }
    
    /**
     * Get market info by symbol
     */
    private function get_market_info($symbol) {
        foreach ($this->european_symbols as $market) {
            if ($market['symbol'] === $symbol) {
                return $market;
            }
        }
        
        return array(
            'exchange' => null,
            'currency' => 'EUR'
        );
    }
    
    /**
     * Fetch ECB exchange rates
     */
    public function fetch_ecb_rates() {
        $url = $this->api_endpoints['ecb'];
        
        $response = wp_remote_get($url);
        
        if (is_wp_error($response)) {
            error_log('SFFC ECB Error: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        
        // Parse XML
        $xml = simplexml_load_string($body);
        
        if (!$xml) {
            return false;
        }
        
        $rates = array();
        $date = (string) $xml->Cube->Cube['time'];
        
        foreach ($xml->Cube->Cube->Cube as $rate) {
            $currency = (string) $rate['currency'];
            $value = (float) $rate['rate'];
            
            $rates[$currency] = $value;
            
            // Save to database
            $this->save_exchange_rate('EUR', $currency, $value, $date);
        }
        
        return $rates;
    }
    
    /**
     * Save exchange rate to database
     */
    private function save_exchange_rate($base, $target, $rate, $date) {
        global $wpdb;
        
        require_once SFFC_PLUGIN_DIR . 'includes/class-european-database.php';
        $eu_db = SFFC_European_Database::get_instance();
        
        $table = $eu_db->get_table('exchange_rates');
        
        if (!$table) {
            return false;
        }
        
        // Check if rate exists for this date
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE rate_date = %s AND base_currency = %s AND target_currency = %s",
            $date, $base, $target
        ));
        
        if ($existing) {
            // Update existing
            return $wpdb->update(
                $table,
                array(
                    'exchange_rate' => $rate,
                    'updated_at' => current_time('mysql')
                ),
                array(
                    'rate_date' => $date,
                    'base_currency' => $base,
                    'target_currency' => $target
                )
            );
        } else {
            // Insert new
            return $wpdb->insert(
                $table,
                array(
                    'rate_date' => $date,
                    'base_currency' => $base,
                    'target_currency' => $target,
                    'exchange_rate' => $rate,
                    'data_source' => 'ECB'
                )
            );
        }
    }
    
    /**
     * Get latest market data from cache
     */
    public function get_cached_market_data($symbol = null) {
        global $wpdb;
        
        require_once SFFC_PLUGIN_DIR . 'includes/class-european-database.php';
        $eu_db = SFFC_European_Database::get_instance();
        
        $table = $eu_db->get_table('market_cache');
        
        if (!$table) {
            return false;
        }
        
        if ($symbol) {
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE symbol = %s",
                $symbol
            ));
        } else {
            return $wpdb->get_results(
                "SELECT * FROM {$table} WHERE region = 'Europe' ORDER BY symbol"
            );
        }
    }
}

// Initialize
new SFFC_European_Market_Fetcher();
?>