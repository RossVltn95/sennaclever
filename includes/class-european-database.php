<?php
/**
 * European Markets Database Handler
 * 
 * @package SennaCareers
 * @subpackage EuropeanMarkets
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_European_Database {
    
    /**
     * Database version for European markets
     */
    const EU_DB_VERSION = '2.0.0';
    
    /**
     * European table names
     */
    private $eu_tables = array();
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Get instance
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
        global $wpdb;
        
        // Define European market table names
        $this->eu_tables = array(
            'market_cache' => $wpdb->prefix . 'sffc_market_cache',
            'intraday_prices' => $wpdb->prefix . 'sffc_intraday_prices',
            'european_indices' => $wpdb->prefix . 'sffc_european_indices',
            'pe_transactions' => $wpdb->prefix . 'sffc_pe_transactions',
            'pe_fundraising' => $wpdb->prefix . 'sffc_pe_fundraising',
            'pe_firms' => $wpdb->prefix . 'sffc_pe_firms',
            'market_correlations' => $wpdb->prefix . 'sffc_market_correlations',
            'sector_flows' => $wpdb->prefix . 'sffc_sector_flows',
            'macro_events' => $wpdb->prefix . 'sffc_macro_events',
            'market_sentiment' => $wpdb->prefix . 'sffc_market_sentiment',
            'exchange_rates' => $wpdb->prefix . 'sffc_exchange_rates'
        );
    }
    
    /**
     * Get table name
     */
    public function get_table($name) {
        return isset($this->eu_tables[$name]) ? $this->eu_tables[$name] : false;
    }
    
    /**
     * Create all European market tables
     */
    public function create_tables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $results = array();
        
        // Create each table
        $results['market_cache'] = $this->create_market_cache_table();
        $results['intraday_prices'] = $this->create_intraday_prices_table();
        $results['european_indices'] = $this->create_european_indices_table();
        $results['pe_transactions'] = $this->create_pe_transactions_table();
        $results['pe_fundraising'] = $this->create_pe_fundraising_table();
        $results['pe_firms'] = $this->create_pe_firms_table();
        $results['market_correlations'] = $this->create_market_correlations_table();
        $results['sector_flows'] = $this->create_sector_flows_table();
        $results['macro_events'] = $this->create_macro_events_table();
        $results['market_sentiment'] = $this->create_market_sentiment_table();
        $results['exchange_rates'] = $this->create_exchange_rates_table();
        
        // Insert default data
        $this->insert_default_indices();
        
        // Update database version
        update_option('sffc_eu_db_version', self::EU_DB_VERSION);
        
        return $results;
    }
    
    /**
     * Create enhanced market cache table
     */
    public function create_market_cache_table() {
        global $wpdb;
        
        $table_name = $this->eu_tables['market_cache'];
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            symbol varchar(20) NOT NULL,
            exchange varchar(50) DEFAULT NULL,
            market_type varchar(30) DEFAULT 'equity',
            region varchar(30) DEFAULT 'US',
            price decimal(12,4) DEFAULT NULL,
            previous_close decimal(12,4) DEFAULT NULL,
            open decimal(12,4) DEFAULT NULL,
            high decimal(12,4) DEFAULT NULL,
            low decimal(12,4) DEFAULT NULL,
            volume bigint(20) DEFAULT NULL,
            average_volume bigint(20) DEFAULT NULL,
            market_cap bigint(20) DEFAULT NULL,
            pe_ratio decimal(8,2) DEFAULT NULL,
            dividend_yield decimal(5,2) DEFAULT NULL,
            week_52_high decimal(12,4) DEFAULT NULL,
            week_52_low decimal(12,4) DEFAULT NULL,
            change_amount decimal(12,4) DEFAULT NULL,
            change_percent decimal(8,4) DEFAULT NULL,
            volatility_30d decimal(8,4) DEFAULT NULL,
            volatility_90d decimal(8,4) DEFAULT NULL,
            sector varchar(100) DEFAULT NULL,
            industry varchar(100) DEFAULT NULL,
            currency varchar(10) DEFAULT 'USD',
            last_updated datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            data_source varchar(50) DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY symbol_exchange (symbol, exchange),
            INDEX idx_region (region),
            INDEX idx_sector (sector),
            INDEX idx_last_updated (last_updated),
            INDEX idx_market_type (market_type)
        ) $charset_collate;";
        
        dbDelta($sql);
        return $this->table_exists('market_cache');
    }
    
    /**
     * Create intraday prices table
     */
    public function create_intraday_prices_table() {
        global $wpdb;
        
        $table_name = $this->eu_tables['intraday_prices'];
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            symbol varchar(20) NOT NULL,
            timestamp datetime NOT NULL,
            price decimal(12,4) NOT NULL,
            volume bigint(20) DEFAULT NULL,
            bid decimal(12,4) DEFAULT NULL,
            ask decimal(12,4) DEFAULT NULL,
            bid_size int(11) DEFAULT NULL,
            ask_size int(11) DEFAULT NULL,
            vwap decimal(12,4) DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY symbol_timestamp (symbol, timestamp),
            INDEX idx_symbol (symbol),
            INDEX idx_timestamp (timestamp)
        ) $charset_collate;";
        
        dbDelta($sql);
        return $this->table_exists('intraday_prices');
    }
    
    /**
     * Create European indices table
     */
    public function create_european_indices_table() {
        global $wpdb;
        
        $table_name = $this->eu_tables['european_indices'];
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            index_symbol varchar(20) NOT NULL,
            index_name varchar(100) NOT NULL,
            country varchar(50) NOT NULL,
            exchange varchar(50) NOT NULL,
            constituents_count int(11) DEFAULT NULL,
            currency varchar(10) NOT NULL,
            tracking_enabled tinyint(1) DEFAULT 1,
            data_frequency varchar(20) DEFAULT 'daily',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY index_symbol (index_symbol),
            INDEX idx_country (country),
            INDEX idx_exchange (exchange)
        ) $charset_collate;";
        
        dbDelta($sql);
        return $this->table_exists('european_indices');
    }
    
    /**
     * Create PE transactions table
     */
    public function create_pe_transactions_table() {
        global $wpdb;
        
        $table_name = $this->eu_tables['pe_transactions'];
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            deal_id varchar(100) DEFAULT NULL,
            announcement_date date NOT NULL,
            completion_date date DEFAULT NULL,
            deal_type varchar(50) NOT NULL,
            target_company varchar(255) NOT NULL,
            target_sector varchar(100) DEFAULT NULL,
            target_country varchar(50) DEFAULT NULL,
            target_region varchar(50) DEFAULT NULL,
            acquirer varchar(255) DEFAULT NULL,
            acquirer_type varchar(50) DEFAULT NULL,
            deal_value_usd bigint(20) DEFAULT NULL,
            deal_value_eur bigint(20) DEFAULT NULL,
            deal_value_gbp bigint(20) DEFAULT NULL,
            enterprise_value bigint(20) DEFAULT NULL,
            revenue_multiple decimal(8,2) DEFAULT NULL,
            ebitda_multiple decimal(8,2) DEFAULT NULL,
            debt_component bigint(20) DEFAULT NULL,
            equity_component bigint(20) DEFAULT NULL,
            deal_status varchar(50) DEFAULT 'announced',
            exit_type varchar(50) DEFAULT NULL,
            holding_period_months int(11) DEFAULT NULL,
            irr decimal(8,2) DEFAULT NULL,
            multiple_return decimal(8,2) DEFAULT NULL,
            data_source varchar(100) DEFAULT NULL,
            source_url text DEFAULT NULL,
            notes text DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY deal_id (deal_id),
            INDEX idx_announcement_date (announcement_date),
            INDEX idx_target_sector (target_sector),
            INDEX idx_target_country (target_country),
            INDEX idx_deal_type (deal_type),
            INDEX idx_acquirer (acquirer),
            INDEX idx_deal_status (deal_status),
            FULLTEXT KEY ft_search (target_company, acquirer, notes)
        ) $charset_collate;";
        
        dbDelta($sql);
        return $this->table_exists('pe_transactions');
    }
    
    /**
     * Create PE fundraising table
     */
    public function create_pe_fundraising_table() {
        global $wpdb;
        
        $table_name = $this->eu_tables['pe_fundraising'];
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            fund_id varchar(100) DEFAULT NULL,
            fund_name varchar(255) NOT NULL,
            firm_name varchar(255) NOT NULL,
            fund_strategy varchar(100) DEFAULT NULL,
            fund_geography varchar(100) DEFAULT NULL,
            target_size_usd bigint(20) DEFAULT NULL,
            target_size_eur bigint(20) DEFAULT NULL,
            target_size_gbp bigint(20) DEFAULT NULL,
            current_size bigint(20) DEFAULT NULL,
            final_close_size bigint(20) DEFAULT NULL,
            first_close_date date DEFAULT NULL,
            final_close_date date DEFAULT NULL,
            fundraising_status varchar(50) DEFAULT 'raising',
            vintage_year int(4) DEFAULT NULL,
            fund_number int(11) DEFAULT NULL,
            management_fee_percent decimal(5,2) DEFAULT NULL,
            carried_interest_percent decimal(5,2) DEFAULT NULL,
            hurdle_rate_percent decimal(5,2) DEFAULT NULL,
            investment_period_years int(11) DEFAULT NULL,
            fund_life_years int(11) DEFAULT NULL,
            data_source varchar(100) DEFAULT NULL,
            notes text DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY fund_id (fund_id),
            INDEX idx_firm_name (firm_name),
            INDEX idx_fund_strategy (fund_strategy),
            INDEX idx_fund_geography (fund_geography),
            INDEX idx_vintage_year (vintage_year),
            INDEX idx_fundraising_status (fundraising_status),
            FULLTEXT KEY ft_search (fund_name, firm_name, notes)
        ) $charset_collate;";
        
        dbDelta($sql);
        return $this->table_exists('pe_fundraising');
    }
    
    /**
     * Create PE firms table
     */
    public function create_pe_firms_table() {
        global $wpdb;
        
        $table_name = $this->eu_tables['pe_firms'];
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            firm_name varchar(255) NOT NULL,
            firm_type varchar(50) DEFAULT NULL,
            headquarters_country varchar(50) DEFAULT NULL,
            headquarters_city varchar(100) DEFAULT NULL,
            founding_year int(4) DEFAULT NULL,
            aum_usd_millions bigint(20) DEFAULT NULL,
            aum_eur_millions bigint(20) DEFAULT NULL,
            active_portfolio_companies int(11) DEFAULT NULL,
            total_investments int(11) DEFAULT NULL,
            total_exits int(11) DEFAULT NULL,
            average_irr decimal(8,2) DEFAULT NULL,
            average_multiple decimal(8,2) DEFAULT NULL,
            investment_focus text DEFAULT NULL,
            sector_focus text DEFAULT NULL,
            geographic_focus text DEFAULT NULL,
            website varchar(255) DEFAULT NULL,
            league_table_rank_global int(11) DEFAULT NULL,
            league_table_rank_europe int(11) DEFAULT NULL,
            last_fund_size bigint(20) DEFAULT NULL,
            last_fund_vintage int(4) DEFAULT NULL,
            data_source varchar(100) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY firm_name (firm_name),
            INDEX idx_firm_type (firm_type),
            INDEX idx_headquarters_country (headquarters_country),
            INDEX idx_aum (aum_usd_millions),
            INDEX idx_league_table_rank_europe (league_table_rank_europe),
            FULLTEXT KEY ft_search (firm_name, investment_focus, sector_focus)
        ) $charset_collate;";
        
        dbDelta($sql);
        return $this->table_exists('pe_firms');
    }
    
    /**
     * Create market correlations table
     */
    public function create_market_correlations_table() {
        global $wpdb;
        
        $table_name = $this->eu_tables['market_correlations'];
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            asset1_symbol varchar(20) NOT NULL,
            asset1_type varchar(30) NOT NULL,
            asset2_symbol varchar(20) NOT NULL,
            asset2_type varchar(30) NOT NULL,
            correlation_period varchar(20) NOT NULL,
            correlation_coefficient decimal(5,4) NOT NULL,
            covariance decimal(12,6) DEFAULT NULL,
            beta decimal(8,4) DEFAULT NULL,
            r_squared decimal(5,4) DEFAULT NULL,
            calculation_date date NOT NULL,
            data_points int(11) DEFAULT NULL,
            significance_level decimal(5,4) DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY correlation_unique (asset1_symbol, asset2_symbol, correlation_period, calculation_date),
            INDEX idx_asset1 (asset1_symbol),
            INDEX idx_asset2 (asset2_symbol),
            INDEX idx_correlation_period (correlation_period),
            INDEX idx_calculation_date (calculation_date),
            INDEX idx_correlation_coefficient (correlation_coefficient)
        ) $charset_collate;";
        
        dbDelta($sql);
        return $this->table_exists('market_correlations');
    }
    
    /**
     * Create sector flows table
     */
    public function create_sector_flows_table() {
        global $wpdb;
        
        $table_name = $this->eu_tables['sector_flows'];
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            flow_date date NOT NULL,
            sector varchar(100) NOT NULL,
            region varchar(50) NOT NULL,
            net_flow_amount decimal(15,2) DEFAULT NULL,
            inflow_amount decimal(15,2) DEFAULT NULL,
            outflow_amount decimal(15,2) DEFAULT NULL,
            flow_currency varchar(10) DEFAULT 'USD',
            institutional_flow decimal(15,2) DEFAULT NULL,
            retail_flow decimal(15,2) DEFAULT NULL,
            etf_flow decimal(15,2) DEFAULT NULL,
            mutual_fund_flow decimal(15,2) DEFAULT NULL,
            flow_sentiment varchar(20) DEFAULT NULL,
            volume_rank int(11) DEFAULT NULL,
            momentum_score decimal(5,2) DEFAULT NULL,
            data_source varchar(50) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY flow_unique (flow_date, sector, region),
            INDEX idx_flow_date (flow_date),
            INDEX idx_sector (sector),
            INDEX idx_region (region),
            INDEX idx_net_flow (net_flow_amount)
        ) $charset_collate;";
        
        dbDelta($sql);
        return $this->table_exists('sector_flows');
    }
    
    /**
     * Create macro events table
     */
    public function create_macro_events_table() {
        global $wpdb;
        
        $table_name = $this->eu_tables['macro_events'];
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            event_date datetime NOT NULL,
            event_type varchar(100) NOT NULL,
            event_name varchar(255) NOT NULL,
            event_description text DEFAULT NULL,
            country varchar(50) DEFAULT NULL,
            region varchar(50) DEFAULT NULL,
            importance varchar(20) DEFAULT 'medium',
            actual_value varchar(100) DEFAULT NULL,
            forecast_value varchar(100) DEFAULT NULL,
            previous_value varchar(100) DEFAULT NULL,
            market_impact_score decimal(5,2) DEFAULT NULL,
            affected_sectors text DEFAULT NULL,
            affected_assets text DEFAULT NULL,
            sp500_impact decimal(8,4) DEFAULT NULL,
            stoxx600_impact decimal(8,4) DEFAULT NULL,
            ftse100_impact decimal(8,4) DEFAULT NULL,
            dax_impact decimal(8,4) DEFAULT NULL,
            cac40_impact decimal(8,4) DEFAULT NULL,
            vix_change decimal(8,4) DEFAULT NULL,
            vstoxx_change decimal(8,4) DEFAULT NULL,
            data_source varchar(50) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_event_date (event_date),
            INDEX idx_event_type (event_type),
            INDEX idx_country (country),
            INDEX idx_importance (importance),
            FULLTEXT KEY ft_search (event_name, event_description, affected_sectors)
        ) $charset_collate;";
        
        dbDelta($sql);
        return $this->table_exists('macro_events');
    }
    
    /**
     * Create market sentiment table
     */
    public function create_market_sentiment_table() {
        global $wpdb;
        
        $table_name = $this->eu_tables['market_sentiment'];
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            calculation_date date NOT NULL,
            market varchar(50) NOT NULL,
            sentiment_score decimal(5,2) NOT NULL,
            fear_greed_index decimal(5,2) DEFAULT NULL,
            put_call_ratio decimal(8,4) DEFAULT NULL,
            vix_level decimal(8,2) DEFAULT NULL,
            vstoxx_level decimal(8,2) DEFAULT NULL,
            advance_decline_ratio decimal(8,4) DEFAULT NULL,
            new_highs int(11) DEFAULT NULL,
            new_lows int(11) DEFAULT NULL,
            bullish_percent decimal(5,2) DEFAULT NULL,
            rsi_average decimal(5,2) DEFAULT NULL,
            data_source varchar(50) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY sentiment_unique (calculation_date, market),
            INDEX idx_calculation_date (calculation_date),
            INDEX idx_market (market),
            INDEX idx_sentiment_score (sentiment_score)
        ) $charset_collate;";
        
        dbDelta($sql);
        return $this->table_exists('market_sentiment');
    }
    
    /**
     * Create exchange rates table
     */
    public function create_exchange_rates_table() {
        global $wpdb;
        
        $table_name = $this->eu_tables['exchange_rates'];
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            rate_date date NOT NULL,
            base_currency varchar(10) NOT NULL,
            target_currency varchar(10) NOT NULL,
            exchange_rate decimal(12,6) NOT NULL,
            daily_change decimal(8,4) DEFAULT NULL,
            weekly_change decimal(8,4) DEFAULT NULL,
            monthly_change decimal(8,4) DEFAULT NULL,
            yearly_change decimal(8,4) DEFAULT NULL,
            data_source varchar(50) DEFAULT NULL,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY rate_unique (rate_date, base_currency, target_currency),
            INDEX idx_rate_date (rate_date),
            INDEX idx_currencies (base_currency, target_currency)
        ) $charset_collate;";
        
        dbDelta($sql);
        return $this->table_exists('exchange_rates');
    }
    
    /**
     * Insert default European indices
     */
    private function insert_default_indices() {
        global $wpdb;
        
        $table_name = $this->eu_tables['european_indices'];
        
        $indices = array(
            array('^FTSE', 'FTSE 100', 'United Kingdom', 'LSE', 'GBP'),
            array('^GDAXI', 'DAX', 'Germany', 'Frankfurt', 'EUR'),
            array('^FCHI', 'CAC 40', 'France', 'Euronext Paris', 'EUR'),
            array('^STOXX50E', 'EURO STOXX 50', 'Europe', 'Multiple', 'EUR'),
            array('^STOXX', 'STOXX Europe 600', 'Europe', 'Multiple', 'EUR'),
            array('^IBEX', 'IBEX 35', 'Spain', 'BME', 'EUR'),
            array('^FTMIB', 'FTSE MIB', 'Italy', 'Borsa Italiana', 'EUR'),
            array('^AEX', 'AEX', 'Netherlands', 'Euronext Amsterdam', 'EUR'),
            array('^BFX', 'BEL 20', 'Belgium', 'Euronext Brussels', 'EUR'),
            array('^SSMI', 'SMI', 'Switzerland', 'SIX', 'CHF'),
            array('^OMX', 'OMX Stockholm 30', 'Sweden', 'Nasdaq Stockholm', 'SEK'),
            array('^OSEAX', 'OSE All Share', 'Norway', 'Oslo Børs', 'NOK')
        );
        
        foreach ($indices as $index) {
            $wpdb->insert(
                $table_name,
                array(
                    'index_symbol' => $index[0],
                    'index_name' => $index[1],
                    'country' => $index[2],
                    'exchange' => $index[3],
                    'currency' => $index[4],
                    'tracking_enabled' => 1,
                    'data_frequency' => 'daily'
                ),
                array('%s', '%s', '%s', '%s', '%s', '%d', '%s')
            );
        }
    }
    
    /**
     * Check if table exists
     */
    public function table_exists($table_key) {
        global $wpdb;
        
        if (!isset($this->eu_tables[$table_key])) {
            return false;
        }
        
        $table_name = $this->eu_tables[$table_key];
        $query = $wpdb->prepare("SHOW TABLES LIKE %s", $table_name);
        
        return $wpdb->get_var($query) === $table_name;
    }
    
    /**
     * Get tables status
     */
    public function get_tables_status() {
        $status = array();
        
        foreach ($this->eu_tables as $key => $table_name) {
            $status[$key] = array(
                'name' => $table_name,
                'exists' => $this->table_exists($key)
            );
        }
        
        return $status;
    }
    
    /**
     * Repair tables - drop and recreate
     */
    public function repair_tables() {
        global $wpdb;
        
        $results = array();
        
        foreach ($this->eu_tables as $key => $table_name) {
            // Drop table if exists
            $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
            
            // Recreate table
            $method = 'create_' . $key . '_table';
            if (method_exists($this, $method)) {
                $results[$key] = $this->$method();
            }
        }
        
        return $results;
    }
    
    /**
     * Create single missing table
     */
    public function create_missing_table($table_key) {
        if (!isset($this->eu_tables[$table_key])) {
            return false;
        }
        
        // Check if already exists
        if ($this->table_exists($table_key)) {
            return true;
        }
        
        // Create the table
        $method = 'create_' . $table_key . '_table';
        if (method_exists($this, $method)) {
            return $this->$method();
        }
        
        return false;
    }
    
    /**
     * Verify table structure
     */
    public function verify_table_structure($table_key) {
        global $wpdb;
        
        if (!isset($this->eu_tables[$table_key])) {
            return false;
        }
        
        $table_name = $this->eu_tables[$table_key];
        
        // Get table structure
        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_name}");
        
        return $columns;
    }
}
?>