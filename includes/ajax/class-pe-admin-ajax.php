<?php
/**
 * PE Admin AJAX Handlers
 * Handles admin operations for PE Intelligence system
 * 
 * @package SennaCareers
 * @since 10.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_PE_Admin_Ajax {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_sffc_initialize_pe_firms', array($this, 'ajax_initialize_pe_firms'));
        add_action('wp_ajax_sffc_clear_pe_content', array($this, 'ajax_clear_pe_content'));
        add_action('wp_ajax_sffc_flush_pe_rewrite', array($this, 'ajax_flush_rewrite_rules'));
        add_action('wp_ajax_sffc_test_single_feed', array($this, 'ajax_test_feed'));
        add_action('wp_ajax_sffc_generate_sample_pe_data', array($this, 'ajax_generate_sample_data'));
        add_action('wp_ajax_sffc_check_pe_cron', array($this, 'ajax_check_cron'));
        add_action('wp_ajax_sffc_fetch_pe_content_type', array($this, 'ajax_fetch_pe_content_type'));
        add_action('wp_ajax_sffc_create_sample_deal_financial', array($this, 'ajax_create_sample_deal_financial'));
    }
    
    /**
     * Initialize PE firms
     */
    public function ajax_initialize_pe_firms() {
        check_ajax_referer('sffc_pe_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Top PE firms data
        $pe_firms = array(
            array(
                'name' => 'KKR',
                'full_name' => 'Kohlberg Kravis Roberts',
                'slug' => 'kkr',
                'aum' => '524000000000',
                'founded' => 1976,
                'headquarters' => 'New York',
                'regions' => 'US, EU, APAC',
                'sectors' => 'Technology, Healthcare, Infrastructure, Energy',
                'portfolio_companies' => 100
            ),
            array(
                'name' => 'Blackstone',
                'full_name' => 'The Blackstone Group',
                'slug' => 'blackstone',
                'aum' => '1000000000000',
                'founded' => 1985,
                'headquarters' => 'New York',
                'regions' => 'US, EU, APAC, LATAM',
                'sectors' => 'Real Estate, Private Equity, Credit, Hedge Funds',
                'portfolio_companies' => 200
            ),
            array(
                'name' => 'Apollo',
                'full_name' => 'Apollo Global Management',
                'slug' => 'apollo',
                'aum' => '631000000000',
                'founded' => 1990,
                'headquarters' => 'New York',
                'regions' => 'US, EU',
                'sectors' => 'Credit, Private Equity, Real Assets',
                'portfolio_companies' => 150
            ),
            array(
                'name' => 'Carlyle',
                'full_name' => 'The Carlyle Group',
                'slug' => 'carlyle',
                'aum' => '426000000000',
                'founded' => 1987,
                'headquarters' => 'Washington DC',
                'regions' => 'US, EU, APAC, private equity',
                'sectors' => 'Aerospace, Defense, Technology, Healthcare',
                'portfolio_companies' => 185
            ),
            array(
                'name' => 'TPG',
                'full_name' => 'TPG Capital',
                'slug' => 'tpg',
                'aum' => '137000000000',
                'founded' => 1992,
                'headquarters' => 'Fort Worth',
                'regions' => 'US, EU, APAC',
                'sectors' => 'Technology, Healthcare, Climate',
                'portfolio_companies' => 70
            ),
            array(
                'name' => 'Warburg Pincus',
                'full_name' => 'Warburg Pincus LLC',
                'slug' => 'warburg-pincus',
                'aum' => '89000000000',
                'founded' => 1966,
                'headquarters' => 'New York',
                'regions' => 'US, EU, APAC, LATAM',
                'sectors' => 'Technology, Healthcare, Financial Services, Energy',
                'portfolio_companies' => 60
            ),
            array(
                'name' => 'Bain Capital',
                'full_name' => 'Bain Capital Partners',
                'slug' => 'bain-capital',
                'aum' => '165000000000',
                'founded' => 1984,
                'headquarters' => 'Boston',
                'regions' => 'US, EU, APAC',
                'sectors' => 'Technology, Healthcare, Consumer, Industrial',
                'portfolio_companies' => 90
            ),
            array(
                'name' => 'Advent International',
                'full_name' => 'Advent International Corporation',
                'slug' => 'advent',
                'aum' => '87000000000',
                'founded' => 1984,
                'headquarters' => 'Boston',
                'regions' => 'US, EU, LATAM, APAC',
                'sectors' => 'Business Services, Healthcare, Industrial, Retail',
                'portfolio_companies' => 75
            )
        );
        
        $created = 0;
        $updated = 0;
        
        foreach ($pe_firms as $firm) {
            // Check if firm exists
            $existing = get_posts(array(
                'post_type' => 'sffc_company',
                'meta_key' => '_sffc_firm_slug',
                'meta_value' => $firm['slug'],
                'posts_per_page' => 1
            ));
            
            if (!empty($existing)) {
                // Update existing
                $post_id = $existing[0]->ID;
                
                $update_args = array(
                    'ID' => $post_id,
                    'post_content' => sprintf(
                        '%s is a leading global investment firm with $%s billion in assets under management. Founded in %d and headquartered in %s, the firm operates across %s with a focus on %s.',
                        $firm['full_name'],
                        round($firm['aum'] / 1000000000),
                        $firm['founded'],
                        $firm['headquarters'],
                        $firm['regions'],
                        $firm['sectors']
                    )
                );

                if (class_exists('SFFC_Company_Title_Helper')) {
                    $update_args['post_title'] = SFFC_Company_Title_Helper::build_seo_title($firm['name']);
                    $update_args['post_name'] = get_post_field('post_name', $post_id);
                    SFFC_Company_Title_Helper::ensure_canonical_meta($post_id, $firm['name']);
                } else {
                    $update_args['post_title'] = $firm['name'];
                }

                wp_update_post($update_args);

                $updated++;
            } else {
                // Create new
                $post_args = array(
                    'post_type' => 'sffc_company',
                    'post_content' => sprintf(
                        '%s is a leading global investment firm with $%s billion in assets under management. Founded in %d and headquartered in %s, the firm operates across %s with a focus on %s.',
                        $firm['full_name'],
                        round($firm['aum'] / 1000000000),
                        $firm['founded'],
                        $firm['headquarters'],
                        $firm['regions'],
                        $firm['sectors']
                    ),
                    'post_status' => 'publish',
                    'post_name' => $firm['slug']
                );

                if (class_exists('SFFC_Company_Title_Helper')) {
                    $post_args['post_title'] = SFFC_Company_Title_Helper::build_seo_title($firm['name']);
                } else {
                    $post_args['post_title'] = $firm['name'];
                }

                $post_id = wp_insert_post($post_args);

                if ($post_id && !is_wp_error($post_id)) {
                    if (class_exists('SFFC_Company_Title_Helper')) {
                        SFFC_Company_Title_Helper::ensure_canonical_meta($post_id, $firm['name']);
                    }
                    $created++;
                }
            }
            
            // Update meta fields
            if ($post_id && !is_wp_error($post_id)) {
                update_post_meta($post_id, '_sffc_firm_slug', $firm['slug']);
                update_post_meta($post_id, '_sffc_full_name', $firm['full_name']);
                update_post_meta($post_id, '_sffc_aum', $firm['aum']);
                update_post_meta($post_id, '_sffc_founded', $firm['founded']);
                update_post_meta($post_id, '_sffc_headquarters', $firm['headquarters']);
                update_post_meta($post_id, '_sffc_regions', $firm['regions']);
                update_post_meta($post_id, '_sffc_sectors', $firm['sectors']);
                update_post_meta($post_id, '_sffc_portfolio_companies', $firm['portfolio_companies']);
            }
        }
        
        wp_send_json_success(array(
            'message' => sprintf('PE firms initialized: %d created, %d updated', $created, $updated)
        ));
    }
    
    /**
     * Clear PE content
     */
    public function ajax_clear_pe_content() {
        check_ajax_referer('sffc_pe_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $deleted = 0;
        
        // Delete PE news
        $news = get_posts(array(
            'post_type' => 'sffc_pe_news',
            'posts_per_page' => -1,
            'post_status' => 'any'
        ));
        
        foreach ($news as $post) {
            wp_delete_post($post->ID, true);
            $deleted++;
        }
        
        // Delete PE deals
        $deals = get_posts(array(
            'post_type' => 'sffc_pe_deal',
            'posts_per_page' => -1,
            'post_status' => 'any'
        ));
        
        foreach ($deals as $post) {
            wp_delete_post($post->ID, true);
            $deleted++;
        }
        
        // Delete market intel
        $intel = get_posts(array(
            'post_type' => 'sffc_market_intel',
            'posts_per_page' => -1,
            'post_status' => 'any'
        ));
        
        foreach ($intel as $post) {
            wp_delete_post($post->ID, true);
            $deleted++;
        }
        
        wp_send_json_success(array(
            'message' => sprintf('%d PE content posts deleted', $deleted)
        ));
    }
    
    /**
     * Flush rewrite rules to fix post types
     */
    public function ajax_flush_rewrite_rules() {
        check_ajax_referer('sffc_pe_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Force re-registration of post types
        if (class_exists('SFFC_PE_Content_Manager')) {
            $manager = SFFC_PE_Content_Manager::get_instance();
            $manager->register_post_types();
        }
        
        // Force re-registration of company post type
        if (class_exists('SFFC_Company_Intelligence_Engine')) {
            $company_engine = SFFC_Company_Intelligence_Engine::get_instance();
            $company_engine->register_company_post_type();
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Clear the registration flag to force re-flush
        delete_option('sffc_company_post_type_registered');
        
        // Check if post types now exist
        $pe_news_exists = post_type_exists('sffc_pe_news');
        $pe_deal_exists = post_type_exists('sffc_pe_deal');
        $market_intel_exists = post_type_exists('sffc_market_intel');
        $company_exists = post_type_exists('sffc_company');
        
        if ($pe_news_exists && $pe_deal_exists && $market_intel_exists && $company_exists) {
            wp_send_json_success(array(
                'message' => 'All PE post types registered successfully! Rewrite rules flushed.',
                'post_types' => array(
                    'sffc_pe_news' => $pe_news_exists,
                    'sffc_pe_deal' => $pe_deal_exists,
                    'sffc_market_intel' => $market_intel_exists,
                    'sffc_company' => $company_exists
                )
            ));
        } else {
            wp_send_json_error('Some post types could not be registered. Please deactivate and reactivate the plugin.');
        }
    }
    
    /**
     * Test single feed connection
     */
    public function ajax_test_feed() {
        check_ajax_referer('sffc_test_feed', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $feed_url = isset($_POST['feed_url']) ? esc_url_raw($_POST['feed_url']) : '';
        
        if (empty($feed_url)) {
            wp_send_json_error('Invalid feed URL');
        }
        
        // Test feed connection
        $response = wp_remote_get($feed_url, array(
            'timeout' => 10,
            'user-agent' => 'WordPress/' . get_bloginfo('version')
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            wp_send_json_error('Empty response from feed');
        }
        
        // Try to parse as XML
        $xml = @simplexml_load_string($body);
        if ($xml === false) {
            wp_send_json_error('Invalid XML format');
        }
        
        wp_send_json_success('Feed connection successful');
    }
    
    /**
     * Fetch specific PE content type
     */
    public function ajax_fetch_pe_content_type() {
        check_ajax_referer('sffc_pe_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'all';
        
        if (!class_exists('SFFC_PE_Content_Manager')) {
            wp_send_json_error('PE Content Manager not found');
            return;
        }
        
        $pe_manager = SFFC_PE_Content_Manager::get_instance();
        
        // Track counts before processing
        $before_counts = array(
            'pe_news' => wp_count_posts('sffc_pe_news')->publish,
            'pe_deals' => wp_count_posts('sffc_pe_deal')->publish,
            'market_intel' => wp_count_posts('sffc_market_intel')->publish
        );
        
        // Process based on type
        switch ($type) {
            case 'news':
                // Process only news feeds
                $this->process_news_feeds($pe_manager);
                $message = 'PE News feeds processed';
                break;
                
            case 'deals':
                // Process only deal feeds
                $this->process_deals_feeds($pe_manager);
                $message = 'PE Deals feeds processed';
                break;
                
            case 'market':
                // Process only market intelligence feeds
                $this->process_market_feeds($pe_manager);
                $message = 'Market Intelligence feeds processed';
                break;
                
            case 'all':
            default:
                // Process all feeds
                $pe_manager->process_feed_content();
                $message = 'All PE content feeds processed';
                break;
        }
        
        // Get counts after processing
        $after_counts = array(
            'pe_news' => wp_count_posts('sffc_pe_news')->publish,
            'pe_deals' => wp_count_posts('sffc_pe_deal')->publish,
            'market_intel' => wp_count_posts('sffc_market_intel')->publish
        );
        
        // Calculate new posts created
        $new_posts = array(
            'pe_news' => $after_counts['pe_news'] - $before_counts['pe_news'],
            'pe_deals' => $after_counts['pe_deals'] - $before_counts['pe_deals'],
            'market_intel' => $after_counts['market_intel'] - $before_counts['market_intel']
        );
        
        wp_send_json_success(array(
            'message' => $message,
            'stats' => $after_counts,
            'new_posts' => $new_posts
        ));
    }
    
    /**
     * Process news feeds specifically
     */
    private function process_news_feeds($pe_manager) {
        if (!class_exists('SFFC_XML_Feed_Processor')) {
            return;
        }
        
        $feed_processor = SFFC_XML_Feed_Processor::get_instance();
        $company_engine = SFFC_Company_Intelligence_Engine::get_instance();
        
        // Get PE entities for filtering
        $pe_entities = array(
            'companies' => $this->get_pe_companies_list()
        );
        
        // Process market data feeds for news
        $market_data = $feed_processor->get_live_data('market_analysis', $pe_entities);
        
        if (!empty($market_data['headlines'])) {
            foreach ($market_data['headlines'] as $headline) {
                // Use reflection to access private method
                $reflection = new ReflectionClass($pe_manager);
                $method = $reflection->getMethod('create_pe_news_post');
                $method->setAccessible(true);
                $method->invoke($pe_manager, $headline, $company_engine);
            }
        }
    }
    
    /**
     * Process deals feeds specifically
     */
    private function process_deals_feeds($pe_manager) {
        if (!class_exists('SFFC_XML_Feed_Processor')) {
            return;
        }
        
        $feed_processor = SFFC_XML_Feed_Processor::get_instance();
        $company_engine = SFFC_Company_Intelligence_Engine::get_instance();
        
        // Get PE entities for filtering
        $pe_entities = array(
            'companies' => $this->get_pe_companies_list()
        );
        
        // Process PE deals feeds
        $pe_deals_data = $feed_processor->get_live_data('pe_deals', $pe_entities);
        
        if (!empty($pe_deals_data['headlines'])) {
            foreach ($pe_deals_data['headlines'] as $headline) {
                // Use reflection to access private method
                $reflection = new ReflectionClass($pe_manager);
                $method = $reflection->getMethod('create_pe_deal_post');
                $method->setAccessible(true);
                $method->invoke($pe_manager, $headline, $company_engine);
            }
        }
    }
    
    /**
     * Process market intelligence feeds specifically
     */
    private function process_market_feeds($pe_manager) {
        if (!class_exists('SFFC_XML_Feed_Processor')) {
            return;
        }
        
        $feed_processor = SFFC_XML_Feed_Processor::get_instance();
        $company_engine = SFFC_Company_Intelligence_Engine::get_instance();
        
        // Get PE entities for filtering
        $pe_entities = array(
            'companies' => $this->get_pe_companies_list()
        );
        
        // Process company news for market intelligence
        $company_news = $feed_processor->get_live_data('company_news', $pe_entities);
        
        if (!empty($company_news['headlines'])) {
            foreach ($company_news['headlines'] as $headline) {
                // Use reflection to access private method
                $reflection = new ReflectionClass($pe_manager);
                $method = $reflection->getMethod('create_market_intel_post');
                $method->setAccessible(true);
                $method->invoke($pe_manager, $headline, $company_engine);
            }
        }
    }
    
    /**
     * Get list of PE companies for filtering
     */
    private function get_pe_companies_list() {
        $companies = array();
        
        if (class_exists('SFFC_Company_Intelligence_Engine')) {
            $company_engine = SFFC_Company_Intelligence_Engine::get_instance();
            $firms = $company_engine->get_all_firms();
            
            foreach ($firms as $firm_slug => $firm) {
                $companies[] = array(
                    'name' => $firm['name'],
                    'ticker' => ''
                );
            }
        }
        
        return $companies;
    }
    
    /**
     * Generate sample PE data
     */
    public function ajax_generate_sample_data() {
        check_ajax_referer('sffc_pe_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        wp_send_json_error(array(
            'message' => 'Sample PE content generation is disabled. Use live feed processing only.'
        ), 400);
    }
    
    /**
     * Check cron jobs
     */
    public function ajax_check_cron() {
        check_ajax_referer('sffc_check_cron', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $cron_status = array();
        
        // Check feed processing cron
        $feed_cron = wp_next_scheduled('sffc_process_pe_content');
        $cron_status['Feed Processing'] = $feed_cron ? 
            'Next run: ' . date('Y-m-d H:i:s', $feed_cron) : 
            'Not scheduled';
        
        $auto_cleanup_enabled = false;
        if (class_exists('SFFC_PE_Content_Manager')) {
            $manager = SFFC_PE_Content_Manager::get_instance();
            if (method_exists($manager, 'is_auto_cleanup_enabled')) {
                $auto_cleanup_enabled = $manager->is_auto_cleanup_enabled();
            }
        }

        // Check cleanup cron
        $cleanup_cron = wp_next_scheduled('sffc_cleanup_old_pe_content');
        if ($auto_cleanup_enabled) {
            $cron_status['Content Cleanup'] = $cleanup_cron ?
                'Next run: ' . date('Y-m-d H:i:s', $cleanup_cron) :
                'Not scheduled';
        } else {
            $cron_status['Content Cleanup'] = 'Automatic cleanup disabled';
        }
        
        // Check company metrics update
        $metrics_cron = wp_next_scheduled('sffc_update_company_metrics');
        $cron_status['Company Metrics Update'] = $metrics_cron ? 
            'Next run: ' . date('Y-m-d H:i:s', $metrics_cron) : 
            'Not scheduled';
        
        wp_send_json_success($cron_status);
    }

    /**
     * Disable non-deal feeds (general news, markets, etc.)
     * Keep only feeds that provide deal data for financial modeling
     */
    public static function cleanup_non_deal_feeds()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'sffc_xml_feeds';

        // Categories to DISABLE (no deal data)
        $disable_categories = array(
            'markets',
            'business',
            'central-banks',
            'crypto',
            'commodities',
            'german-economy-news',
            'spanish-finance',
            'hiring-trends',
            'france-business',
            'tech-vc', // General tech, not deals
        );

        // Also disable specific feed names that don't have deals
        $disable_names = array(
            'Yahoo Finance%',
            'BBC Business',
            'Guardian Business',
            'FT Markets',
            'FT World',
            'Financial Times - Markets',
            'Financial Times - World',
            'Bloomberg - Markets',
            'MarketWatch%',
            'Euronews%',
            'European Central Bank',
            'Bank of England%',
            'BoE%',
            'HM Treasury%',
            'FCA UK%',
            'BIS%',
            'FXStreet%',
            'This is Money%',
            'CoinDesk',
            'CoinTelegraph',
            'The Block Crypto',
            'Oil Price',
            'Energy Voice',
            'Offshore Energy',
            'WirtschaftsWoche',
            'Cinco Días',
            'Indeed Hiring Lab',
            'Investing.co%',
            'CNBC World Markets',
            'EU Business',
            'Politico EU',
            'The Next Web', // Tech news, not deals
        );

        $disabled_count = 0;

        // Disable by category
        foreach ($disable_categories as $category) {
            $result = $wpdb->query($wpdb->prepare(
                "UPDATE {$table_name} SET is_active = 0, updated_at = %s WHERE feed_category = %s AND is_active = 1",
                current_time('mysql'),
                $category
            ));
            $disabled_count += $result;
        }

        // Disable by name pattern
        foreach ($disable_names as $name_pattern) {
            if (strpos($name_pattern, '%') !== false) {
                $result = $wpdb->query($wpdb->prepare(
                    "UPDATE {$table_name} SET is_active = 0, updated_at = %s WHERE feed_name LIKE %s AND is_active = 1",
                    current_time('mysql'),
                    $name_pattern
                ));
            } else {
                $result = $wpdb->query($wpdb->prepare(
                    "UPDATE {$table_name} SET is_active = 0, updated_at = %s WHERE feed_name = %s AND is_active = 1",
                    current_time('mysql'),
                    $name_pattern
                ));
            }
            $disabled_count += $result;
        }

        // Get remaining active feeds count
        $active_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE is_active = 1");

        // Get list of remaining active categories
        $active_categories = $wpdb->get_col("SELECT DISTINCT feed_category FROM {$table_name} WHERE is_active = 1 ORDER BY feed_category");

        return array(
            'disabled' => $disabled_count,
            'remaining_active' => $active_count,
            'active_categories' => $active_categories
        );
    }

    /**
     * Create sample deal with full financial data for testing IC memo template
     */
    public function ajax_create_sample_deal_financial() {
        check_ajax_referer('sffc_pe_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $deal_title = 'CVC Capital Partners Acquires Smiths Detection from Smiths Group for £2 Billion';

        // Check if already exists
        $existing = get_posts(array(
            'post_type' => 'sffc_pe_deal',
            'post_status' => 'any',
            's' => 'Smiths Detection',
            'posts_per_page' => 1,
        ));

        if (!empty($existing)) {
            wp_send_json_success(array(
                'message' => 'Sample deal already exists!',
                'view_url' => get_permalink($existing[0]->ID),
                'edit_url' => admin_url('post.php?post=' . $existing[0]->ID . '&action=edit'),
                'post_id' => $existing[0]->ID
            ));
            return;
        }

        // Article content
        $content = '<p>CVC Capital Partners has agreed to acquire Smiths Detection, the security screening division of Smiths Group, in a deal valued at approximately £2 billion ($2.5 billion). The transaction represents one of the largest carve-out deals in the European security sector this year.</p>

<h2>Transaction Overview</h2>
<p>The acquisition values Smiths Detection at 16.3 times its headline operating profit of £122 million for the fiscal year ending July 2025. Smiths Group expects to receive net proceeds of approximately £1.85 billion after transaction costs and taxes, which it plans to return to shareholders through a combination of special dividends and share buybacks.</p>

<p>The deal is expected to close in the first half of 2026, subject to regulatory approvals including clearance from competition authorities in the United States, European Union, and United Kingdom.</p>

<h2>Strategic Rationale</h2>
<p>For CVC, the acquisition provides exposure to the growing airport security and threat detection market, which has seen increased investment following the post-COVID recovery in global air travel. Smiths Detection is a leading provider of security screening technology for airports, ports, and critical infrastructure worldwide.</p>

<h2>Company Profile: Smiths Detection</h2>
<p>Smiths Detection employs approximately 5,000 people globally and operates manufacturing facilities in Germany, the United States, and Malaysia. The division generated revenues of approximately £500 million in its last fiscal year, with an EBITDA margin of around 30%.</p>

<h2>Market Context</h2>
<p>The global aviation security market is projected to grow at approximately 7% annually through 2030. Recent comparable transactions in the security technology sector have seen valuations ranging from 12x to 18x EBITDA.</p>

<h2>Seller Perspective</h2>
<p>For Smiths Group, the divestiture represents a strategic pivot toward its core industrial technology businesses. The company shares rose 4.9% on announcement of the deal, extending year-to-date gains to approximately 40%.</p>';

        // Create the post
        $post_id = wp_insert_post(array(
            'post_title'   => $deal_title,
            'post_content' => $content,
            'post_status'  => 'publish',
            'post_type'    => 'sffc_pe_deal',
            'post_author'  => get_current_user_id(),
            'post_excerpt' => 'CVC Capital Partners to acquire Smiths Detection for £2bn at 16.3x operating profit, representing a premium carve-out in the airport security sector.',
        ));

        if (is_wp_error($post_id)) {
            wp_send_json_error('Failed to create post: ' . $post_id->get_error_message());
            return;
        }

        // Comprehensive deal financials
        $deal_financials = array(
            'deal_value' => array(
                'amount' => 2000,
                'currency' => 'GBP',
                'formatted' => '£2.0bn',
                'source' => 'DISCLOSED',
            ),
            'net_proceeds' => array(
                'amount' => 1850,
                'currency' => 'GBP',
                'formatted' => '£1.85bn',
                'source' => 'DISCLOSED',
            ),
            'target_financials' => array(
                'revenue' => array('amount' => 500, 'currency' => 'GBP', 'source' => 'DISCLOSED'),
                'ebitda' => array('amount' => 150, 'currency' => 'GBP', 'source' => 'CALCULATED'),
                'operating_profit' => array('amount' => 122, 'currency' => 'GBP', 'source' => 'DISCLOSED'),
                'ebitda_margin' => 30.0,
                'operating_margin' => 24.4,
            ),
            'multiples' => array(
                'disclosed' => array(
                    array('type' => 'EV/Operating Profit', 'value' => 16.3, 'source' => 'DISCLOSED'),
                ),
                'calculated' => array(
                    array('type' => 'EV/EBITDA', 'value' => 13.3, 'status' => 'CALCULATED'),
                    array('type' => 'EV/Revenue', 'value' => 4.0, 'status' => 'CALCULATED'),
                ),
                'ev_revenue' => array('value' => 4.0, 'source' => 'CALCULATED'),
                'ev_ebitda' => array('value' => 13.3, 'source' => 'CALCULATED'),
                'ev_operating_profit' => array('value' => 16.3, 'source' => 'DISCLOSED'),
            ),
            'deal_structure' => array(
                'deal_type' => array('type' => 'carve_out', 'label' => 'Carve-Out', 'source' => 'INFERRED'),
                'payment_type' => array('type' => 'cash', 'description' => 'All-cash consideration'),
                'financing' => array('leverage_ratio' => 5.0),
            ),
            'parties' => array(
                'buyer' => array('name' => 'CVC Capital Partners', 'type' => 'Private Equity'),
                'seller' => array('name' => 'Smiths Group', 'ticker' => 'SMIN.L'),
                'target' => array('name' => 'Smiths Detection', 'employees' => 5000),
            ),
            'market_reaction' => array('stock_impact' => 4.9, 'ytd_performance' => 40.0),
            'sector' => 'Industrials',
            'region' => 'UK',
            'deal_stage' => 'announced',
            'ai_enhanced' => array(
                'scenarios' => array(
                    'base' => array(
                        'exit_multiple' => 14.0, 'exit_year' => 5, 'irr' => 15.5, 'moic' => 2.1,
                        'assumptions' => 'Maintain market share, 5% annual revenue growth, stable margins',
                    ),
                    'upside' => array(
                        'exit_multiple' => 18.0, 'exit_year' => 4, 'irr' => 28.0, 'moic' => 2.8,
                        'assumptions' => 'Win major airport contracts, margin expansion, strategic add-ons',
                    ),
                    'downside' => array(
                        'exit_multiple' => 10.0, 'exit_year' => 6, 'irr' => 5.0, 'moic' => 1.3,
                        'assumptions' => 'Travel demand weakness, increased competition, regulatory delays',
                    ),
                ),
                'risks' => array(
                    array('category' => 'Market', 'description' => 'Airport security spending dependent on air travel volumes', 'severity' => 'medium', 'likelihood' => 'medium'),
                    array('category' => 'Execution', 'description' => 'Carve-out complexity from parent company', 'severity' => 'high', 'likelihood' => 'medium'),
                    array('category' => 'Competition', 'description' => 'Well-funded competitors (L3Harris, OSI Systems)', 'severity' => 'medium', 'likelihood' => 'medium'),
                    array('category' => 'Regulatory', 'description' => 'Multi-jurisdiction approval requirements', 'severity' => 'low', 'likelihood' => 'low'),
                ),
                'comparables' => array(
                    'sector_average_multiple' => 14.0,
                    'premium_discount' => 'Premium',
                    'premium_percentage' => 16.4,
                    'rationale' => 'Premium justified by market-leading position and regulatory moat',
                ),
                'analyst_commentary' => array(
                    'investment_thesis' => 'Strategic bet on growing airport security market with multiple value creation levers.',
                    'key_considerations' => array(
                        'Premium multiple reflects asset quality',
                        'Carve-out execution risk is manageable',
                        'Strong post-COVID travel recovery tailwinds',
                    ),
                ),
            ),
            'data_quality' => array(
                'overall_score' => 78,
                'disclosed_count' => 6,
                'calculated_count' => 4,
                'estimated_count' => 3,
                'missing_count' => 2,
            ),
        );

        // Save deal financials
        update_post_meta($post_id, '_sffc_deal_financials', $deal_financials);
        update_post_meta($post_id, '_deal_value', '£2bn');
        update_post_meta($post_id, '_deal_type', 'carve_out');
        update_post_meta($post_id, '_pe_firm', 'CVC Capital Partners');
        update_post_meta($post_id, '_region', 'UK');
        update_post_meta($post_id, '_sector', 'Industrials');

        wp_send_json_success(array(
            'message' => 'Sample deal with full financials created successfully!',
            'view_url' => get_permalink($post_id),
            'edit_url' => admin_url('post.php?post=' . $post_id . '&action=edit'),
            'post_id' => $post_id
        ));
    }
}

// Initialize
new SFFC_PE_Admin_Ajax();

// Run the cleanup immediately when this file loads (one-time migration)
// Comment this out after running once
add_action('admin_init', function() {
    if (get_option('sffc_non_deal_feeds_cleaned') !== 'done') {
        $result = SFFC_PE_Admin_Ajax::cleanup_non_deal_feeds();
        update_option('sffc_non_deal_feeds_cleaned', 'done');
        update_option('sffc_feed_cleanup_result', $result);
        error_log('SFFC Feed Cleanup: Disabled ' . $result['disabled'] . ' non-deal feeds. ' . $result['remaining_active'] . ' active feeds remain.');
    }
}, 999);
