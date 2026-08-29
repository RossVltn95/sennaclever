<?php
/**
 * Intelligence System Database Schema
 *
 * Manages post meta registration, sector benchmarks,
 * and caching for the Dynamic Financial Intelligence System.
 *
 * @package SennaFinanceCareer
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Intelligence_Schema {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Post meta field definitions
     */
    private $meta_fields = array();

    /**
     * Current template version for cache busting
     */
    const TEMPLATE_VERSION = '1.0.0';

    /**
     * Transient expiration times
     */
    const BENCHMARK_EXPIRATION = DAY_IN_SECONDS * 7; // 7 days
    const TEMPLATE_CACHE_EXPIRATION = DAY_IN_SECONDS; // 24 hours

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
        $this->define_meta_fields();
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'register_meta_fields'));
        add_action('admin_init', array($this, 'maybe_update_benchmarks'));
    }

    /**
     * Define all post meta fields for the intelligence system
     */
    private function define_meta_fields() {
        $this->meta_fields = array(
            // Article Classification
            '_sffc_article_classification' => array(
                'type' => 'object',
                'description' => 'Article classification data including category, sector, deal type',
                'default' => array(),
                'sanitize_callback' => array($this, 'sanitize_json_meta'),
                'show_in_rest' => array(
                    'schema' => array(
                        'type' => 'object',
                        'properties' => array(
                            'category' => array('type' => 'string'),
                            'sector' => array('type' => 'string'),
                            'deal_type' => array('type' => 'string'),
                            'geography' => array('type' => 'string'),
                            'confidence' => array('type' => 'number'),
                        ),
                    ),
                ),
            ),

            // Matched Models
            '_sffc_matched_models' => array(
                'type' => 'array',
                'description' => 'Array of model types matched to this article',
                'default' => array(),
                'sanitize_callback' => array($this, 'sanitize_array_meta'),
                'show_in_rest' => array(
                    'schema' => array(
                        'type' => 'array',
                        'items' => array('type' => 'string'),
                    ),
                ),
            ),

            // Data Richness Level
            '_sffc_data_richness' => array(
                'type' => 'string',
                'description' => 'Level of financial data available: full, partial, none',
                'default' => 'none',
                'sanitize_callback' => 'sanitize_text_field',
                'show_in_rest' => true,
            ),

            // Extracted Entities
            '_sffc_extracted_entities' => array(
                'type' => 'object',
                'description' => 'Extracted entities from article: companies, people, values',
                'default' => array(),
                'sanitize_callback' => array($this, 'sanitize_json_meta'),
                'show_in_rest' => false,
            ),

            // Generated Analysis
            '_sffc_generated_analysis' => array(
                'type' => 'object',
                'description' => 'Full generated analysis content with inputs and scenarios',
                'default' => array(),
                'sanitize_callback' => array($this, 'sanitize_json_meta'),
                'show_in_rest' => false,
            ),

            // Generation Timestamp
            '_sffc_generation_timestamp' => array(
                'type' => 'string',
                'description' => 'When the analysis was last generated',
                'default' => '',
                'sanitize_callback' => 'sanitize_text_field',
                'show_in_rest' => true,
            ),

            // Content Hash
            '_sffc_content_hash' => array(
                'type' => 'string',
                'description' => 'MD5 hash of content for change detection',
                'default' => '',
                'sanitize_callback' => 'sanitize_text_field',
                'show_in_rest' => false,
            ),

            // Lead Capture Email
            '_sffc_lead_email' => array(
                'type' => 'string',
                'description' => 'Email captured for guest generation',
                'default' => '',
                'sanitize_callback' => 'sanitize_email',
                'show_in_rest' => false,
            ),

            // Generation Count
            '_sffc_generation_count' => array(
                'type' => 'integer',
                'description' => 'Number of times analysis has been generated',
                'default' => 0,
                'sanitize_callback' => 'absint',
                'show_in_rest' => true,
            ),

            // Download Count
            '_sffc_download_count' => array(
                'type' => 'integer',
                'description' => 'Number of times files have been downloaded',
                'default' => 0,
                'sanitize_callback' => 'absint',
                'show_in_rest' => true,
            ),
        );
    }

    /**
     * Register meta fields with WordPress
     */
    public function register_meta_fields() {
        foreach ($this->meta_fields as $meta_key => $args) {
            register_post_meta('post', $meta_key, array(
                'type' => $args['type'],
                'description' => $args['description'],
                'single' => true,
                'default' => $args['default'],
                'sanitize_callback' => $args['sanitize_callback'],
                'show_in_rest' => $args['show_in_rest'],
            ));
        }
    }

    /**
     * Sanitize JSON meta field
     */
    public function sanitize_json_meta($value) {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        return is_array($value) ? $value : array();
    }

    /**
     * Sanitize array meta field
     */
    public function sanitize_array_meta($value) {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        return is_array($value) ? array_map('sanitize_text_field', $value) : array();
    }

    /**
     * Get sector benchmarks
     *
     * @param string $sector Sector key
     * @return array Benchmark data
     */
    public function get_sector_benchmarks($sector) {
        $transient_key = 'sffc_benchmarks_' . sanitize_key($sector);
        $benchmarks = get_transient($transient_key);

        if ($benchmarks === false) {
            $benchmarks = $this->load_sector_benchmarks($sector);
            set_transient($transient_key, $benchmarks, self::BENCHMARK_EXPIRATION);
        }

        return $benchmarks;
    }

    /**
     * Load sector benchmark data
     *
     * @param string $sector Sector key
     * @return array Benchmark data
     */
    private function load_sector_benchmarks($sector) {
        // Default benchmarks by sector
        $all_benchmarks = array(
            'financial_services' => array(
                'ev_ebitda' => array('low' => 8.0, 'median' => 10.5, 'high' => 14.0),
                'debt_equity' => array('low' => 0.5, 'median' => 1.2, 'high' => 2.5),
                'roe' => array('low' => 8.0, 'median' => 12.0, 'high' => 18.0),
                'revenue_growth' => array('low' => 3.0, 'median' => 8.0, 'high' => 15.0),
            ),
            'technology' => array(
                'ev_ebitda' => array('low' => 12.0, 'median' => 18.0, 'high' => 30.0),
                'ev_revenue' => array('low' => 3.0, 'median' => 6.0, 'high' => 12.0),
                'gross_margin' => array('low' => 60.0, 'median' => 72.0, 'high' => 85.0),
                'revenue_growth' => array('low' => 10.0, 'median' => 25.0, 'high' => 50.0),
            ),
            'healthcare' => array(
                'ev_ebitda' => array('low' => 10.0, 'median' => 14.0, 'high' => 20.0),
                'ev_revenue' => array('low' => 2.0, 'median' => 4.0, 'high' => 8.0),
                'ebitda_margin' => array('low' => 15.0, 'median' => 22.0, 'high' => 30.0),
                'revenue_growth' => array('low' => 5.0, 'median' => 12.0, 'high' => 25.0),
            ),
            'consumer' => array(
                'ev_ebitda' => array('low' => 8.0, 'median' => 11.0, 'high' => 16.0),
                'ev_revenue' => array('low' => 1.0, 'median' => 2.0, 'high' => 4.0),
                'ebitda_margin' => array('low' => 10.0, 'median' => 15.0, 'high' => 22.0),
                'revenue_growth' => array('low' => 2.0, 'median' => 6.0, 'high' => 12.0),
            ),
            'industrial' => array(
                'ev_ebitda' => array('low' => 7.0, 'median' => 10.0, 'high' => 14.0),
                'ev_revenue' => array('low' => 1.0, 'median' => 1.8, 'high' => 3.0),
                'ebitda_margin' => array('low' => 12.0, 'median' => 16.0, 'high' => 22.0),
                'revenue_growth' => array('low' => 2.0, 'median' => 5.0, 'high' => 10.0),
            ),
            'real_estate' => array(
                'cap_rate' => array('low' => 4.0, 'median' => 5.5, 'high' => 7.5),
                'noi_margin' => array('low' => 55.0, 'median' => 65.0, 'high' => 75.0),
                'occupancy' => array('low' => 85.0, 'median' => 92.0, 'high' => 98.0),
                'rent_psf' => array('low' => 25.0, 'median' => 45.0, 'high' => 80.0),
            ),
            'energy' => array(
                'ev_ebitda' => array('low' => 5.0, 'median' => 7.0, 'high' => 10.0),
                'debt_ebitda' => array('low' => 1.5, 'median' => 2.5, 'high' => 4.0),
                'dividend_yield' => array('low' => 3.0, 'median' => 5.0, 'high' => 8.0),
                'fcf_yield' => array('low' => 5.0, 'median' => 8.0, 'high' => 12.0),
            ),
            'private_equity' => array(
                'entry_multiple' => array('low' => 8.0, 'median' => 11.0, 'high' => 15.0),
                'exit_multiple' => array('low' => 9.0, 'median' => 12.0, 'high' => 16.0),
                'leverage' => array('low' => 4.0, 'median' => 5.5, 'high' => 7.0),
                'target_irr' => array('low' => 18.0, 'median' => 22.0, 'high' => 28.0),
                'hold_period' => array('low' => 3.0, 'median' => 5.0, 'high' => 7.0),
            ),
        );

        return $all_benchmarks[$sector] ?? $all_benchmarks['private_equity'];
    }

    /**
     * Get LBO benchmarks
     *
     * @return array LBO-specific benchmarks
     */
    public function get_lbo_benchmarks() {
        $transient_key = 'sffc_lbo_benchmarks';
        $benchmarks = get_transient($transient_key);

        if ($benchmarks === false) {
            $benchmarks = array(
                'entry_multiple' => array(
                    'low' => 8.0,
                    'median' => 11.0,
                    'high' => 15.0,
                    'label' => 'Entry EV/EBITDA',
                ),
                'exit_multiple' => array(
                    'low' => 9.0,
                    'median' => 12.0,
                    'high' => 16.0,
                    'label' => 'Exit EV/EBITDA',
                ),
                'leverage' => array(
                    'low' => 4.0,
                    'median' => 5.5,
                    'high' => 7.0,
                    'label' => 'Total Debt / EBITDA',
                ),
                'senior_leverage' => array(
                    'low' => 3.0,
                    'median' => 4.0,
                    'high' => 5.5,
                    'label' => 'Senior Debt / EBITDA',
                ),
                'equity_contribution' => array(
                    'low' => 30.0,
                    'median' => 40.0,
                    'high' => 50.0,
                    'label' => 'Equity %',
                ),
                'target_irr' => array(
                    'low' => 18.0,
                    'median' => 22.0,
                    'high' => 28.0,
                    'label' => 'Target IRR',
                ),
                'target_moic' => array(
                    'low' => 2.0,
                    'median' => 2.5,
                    'high' => 3.0,
                    'label' => 'Target MOIC',
                ),
                'hold_period' => array(
                    'low' => 3,
                    'median' => 5,
                    'high' => 7,
                    'label' => 'Hold Period (Years)',
                ),
            );
            set_transient($transient_key, $benchmarks, self::BENCHMARK_EXPIRATION);
        }

        return $benchmarks;
    }

    /**
     * Get M&A benchmarks
     *
     * @return array M&A-specific benchmarks
     */
    public function get_ma_benchmarks() {
        $transient_key = 'sffc_ma_benchmarks';
        $benchmarks = get_transient($transient_key);

        if ($benchmarks === false) {
            $benchmarks = array(
                'control_premium' => array(
                    'low' => 20.0,
                    'median' => 30.0,
                    'high' => 45.0,
                    'label' => 'Control Premium %',
                ),
                'synergy_value' => array(
                    'low' => 3.0,
                    'median' => 7.0,
                    'high' => 15.0,
                    'label' => 'Synergies (% of Target Rev)',
                ),
                'cost_synergies' => array(
                    'low' => 5.0,
                    'median' => 10.0,
                    'high' => 20.0,
                    'label' => 'Cost Synergies ($M)',
                ),
                'integration_costs' => array(
                    'low' => 1.0,
                    'median' => 2.5,
                    'high' => 5.0,
                    'label' => 'Integration Costs (% of Deal)',
                ),
                'cash_consideration' => array(
                    'low' => 40.0,
                    'median' => 60.0,
                    'high' => 100.0,
                    'label' => 'Cash Consideration %',
                ),
            );
            set_transient($transient_key, $benchmarks, self::BENCHMARK_EXPIRATION);
        }

        return $benchmarks;
    }

    /**
     * Get Commercial RE benchmarks
     *
     * @param string $property_type Property type (office, industrial, retail, multifamily)
     * @return array CRE-specific benchmarks
     */
    public function get_cre_benchmarks($property_type = 'office') {
        $transient_key = 'sffc_cre_benchmarks_' . sanitize_key($property_type);
        $benchmarks = get_transient($transient_key);

        if ($benchmarks === false) {
            $all_cre = array(
                'office' => array(
                    'cap_rate' => array('low' => 5.0, 'median' => 6.5, 'high' => 8.0),
                    'rent_psf' => array('low' => 30.0, 'median' => 55.0, 'high' => 90.0),
                    'occupancy' => array('low' => 75.0, 'median' => 88.0, 'high' => 95.0),
                    'expense_ratio' => array('low' => 30.0, 'median' => 40.0, 'high' => 50.0),
                    'ti_allowance' => array('low' => 40.0, 'median' => 70.0, 'high' => 120.0),
                ),
                'industrial' => array(
                    'cap_rate' => array('low' => 4.0, 'median' => 5.0, 'high' => 6.5),
                    'rent_psf' => array('low' => 6.0, 'median' => 10.0, 'high' => 16.0),
                    'occupancy' => array('low' => 90.0, 'median' => 95.0, 'high' => 99.0),
                    'expense_ratio' => array('low' => 15.0, 'median' => 25.0, 'high' => 35.0),
                ),
                'retail' => array(
                    'cap_rate' => array('low' => 5.5, 'median' => 7.0, 'high' => 9.0),
                    'rent_psf' => array('low' => 20.0, 'median' => 40.0, 'high' => 75.0),
                    'occupancy' => array('low' => 80.0, 'median' => 90.0, 'high' => 97.0),
                    'expense_ratio' => array('low' => 20.0, 'median' => 30.0, 'high' => 40.0),
                ),
                'multifamily' => array(
                    'cap_rate' => array('low' => 4.0, 'median' => 5.0, 'high' => 6.0),
                    'rent_per_unit' => array('low' => 1200.0, 'median' => 1800.0, 'high' => 3000.0),
                    'occupancy' => array('low' => 90.0, 'median' => 95.0, 'high' => 98.0),
                    'expense_ratio' => array('low' => 35.0, 'median' => 45.0, 'high' => 55.0),
                ),
            );

            $benchmarks = $all_cre[$property_type] ?? $all_cre['office'];
            set_transient($transient_key, $benchmarks, self::BENCHMARK_EXPIRATION);
        }

        return $benchmarks;
    }

    /**
     * Clear all benchmark caches
     */
    public function clear_benchmark_caches() {
        global $wpdb;

        // Delete all benchmark transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_sffc_benchmarks_%'
             OR option_name LIKE '_transient_timeout_sffc_benchmarks_%'
             OR option_name LIKE '_transient_sffc_lbo_%'
             OR option_name LIKE '_transient_timeout_sffc_lbo_%'
             OR option_name LIKE '_transient_sffc_ma_%'
             OR option_name LIKE '_transient_timeout_sffc_ma_%'
             OR option_name LIKE '_transient_sffc_cre_%'
             OR option_name LIKE '_transient_timeout_sffc_cre_%'"
        );
    }

    /**
     * Maybe update benchmarks (weekly check)
     */
    public function maybe_update_benchmarks() {
        $last_update = get_option('sffc_benchmarks_last_update', 0);
        $week_ago = time() - (7 * DAY_IN_SECONDS);

        if ($last_update < $week_ago) {
            // Clear caches to force refresh
            $this->clear_benchmark_caches();
            update_option('sffc_benchmarks_last_update', time());
        }
    }

    /**
     * Get template version for cache busting
     *
     * @return string Template version
     */
    public function get_template_version() {
        return get_option('sffc_model_template_version', self::TEMPLATE_VERSION);
    }

    /**
     * Set template version (for updates)
     *
     * @param string $version New version
     */
    public function set_template_version($version) {
        update_option('sffc_model_template_version', $version);
    }

    /**
     * Track generation event
     *
     * @param int $post_id Post ID
     * @param string $model_type Model type
     */
    public function track_generation($post_id, $model_type) {
        // Increment generation count
        $count = (int) get_post_meta($post_id, '_sffc_generation_count', true);
        update_post_meta($post_id, '_sffc_generation_count', $count + 1);

        // Update timestamp
        update_post_meta($post_id, '_sffc_generation_timestamp', current_time('mysql'));

        // Track in options for analytics
        $daily_key = 'sffc_daily_generations_' . date('Y-m-d');
        $daily_count = (int) get_option($daily_key, 0);
        update_option($daily_key, $daily_count + 1, false);
    }

    /**
     * Track download event
     *
     * @param int $post_id Post ID
     * @param string $format File format (excel, pdf)
     */
    public function track_download($post_id, $format) {
        // Increment download count
        $count = (int) get_post_meta($post_id, '_sffc_download_count', true);
        update_post_meta($post_id, '_sffc_download_count', $count + 1);

        // Track in options for analytics
        $daily_key = 'sffc_daily_downloads_' . date('Y-m-d');
        $daily_count = (int) get_option($daily_key, 0);
        update_option($daily_key, $daily_count + 1, false);
    }

    /**
     * Get generation statistics
     *
     * @return array Statistics
     */
    public function get_generation_stats() {
        global $wpdb;

        $today = date('Y-m-d');
        $week_ago = date('Y-m-d', strtotime('-7 days'));

        return array(
            'today_generations' => (int) get_option('sffc_daily_generations_' . $today, 0),
            'today_downloads' => (int) get_option('sffc_daily_downloads_' . $today, 0),
            'total_with_analysis' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->postmeta}
                 WHERE meta_key = '_sffc_generated_analysis'
                 AND meta_value != '' AND meta_value != 'a:0:{}'"
            ),
            'total_classified' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->postmeta}
                 WHERE meta_key = '_sffc_article_classification'
                 AND meta_value != '' AND meta_value != 'a:0:{}'"
            ),
        );
    }

    /**
     * Check if generation limit reached for user
     *
     * @param int $user_id User ID (0 for guest)
     * @return bool Whether limit is reached
     */
    public function is_generation_limit_reached($user_id = 0) {
        // Premium users have no limit
        if ($user_id > 0 && user_can($user_id, 'premium_member')) {
            return false;
        }

        // Free users: 3 per month
        if ($user_id > 0) {
            $month_key = 'sffc_user_generations_' . $user_id . '_' . date('Y-m');
            $count = (int) get_user_meta($user_id, $month_key, true);
            return $count >= 3;
        }

        // Guest users: 1 per session (tracked by session)
        return false; // Email gate handles this
    }

    /**
     * Increment user generation count
     *
     * @param int $user_id User ID
     */
    public function increment_user_generation_count($user_id) {
        if ($user_id > 0) {
            $month_key = 'sffc_user_generations_' . $user_id . '_' . date('Y-m');
            $count = (int) get_user_meta($user_id, $month_key, true);
            update_user_meta($user_id, $month_key, $count + 1);
        }
    }

    /**
     * Get user's remaining generations
     *
     * @param int $user_id User ID
     * @return int|string Remaining count or 'unlimited'
     */
    public function get_remaining_generations($user_id) {
        if ($user_id > 0 && user_can($user_id, 'premium_member')) {
            return 'unlimited';
        }

        if ($user_id > 0) {
            $month_key = 'sffc_user_generations_' . $user_id . '_' . date('Y-m');
            $count = (int) get_user_meta($user_id, $month_key, true);
            return max(0, 3 - $count);
        }

        return 1; // Guest gets one with email capture
    }
}

// Initialize
add_action('plugins_loaded', function() {
    SFFC_Intelligence_Schema::get_instance();
});
