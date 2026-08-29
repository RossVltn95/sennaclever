<?php
/**
 * Feed Integration Connector
 * Ensures proper connection between XML Feed Processor and PE Intelligence system
 * 
 * @package SennaCareers
 * @since 10.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Feed_Integration_Connector {
    
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
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Connect feed processor to news company linker
        add_action('sffc_feed_item_processed', array($this, 'process_feed_item_for_pe'), 10, 2);
        
        // Add cron job for regular feed processing
        add_action('sffc_process_pe_feeds', array($this, 'process_all_feeds'));
        
        if (!wp_next_scheduled('sffc_process_pe_feeds')) {
            wp_schedule_event(time(), 'hourly', 'sffc_process_pe_feeds');
        }
        
        // AJAX handler for manual feed processing
        add_action('wp_ajax_sffc_process_feeds_now', array($this, 'ajax_process_feeds'));
    }
    
    /**
     * Process feed item for PE system
     */
    public function process_feed_item_for_pe($feed_item, $source) {
        // Prepare data for news company linker
        $news_data = array(
            'title' => isset($feed_item['title']) ? $feed_item['title'] : '',
            'description' => isset($feed_item['description']) ? $feed_item['description'] : '',
            'content' => isset($feed_item['content']) ? $feed_item['content'] : $feed_item['description'],
            'link' => isset($feed_item['link']) ? $feed_item['link'] : '',
            'pubDate' => isset($feed_item['pubDate']) ? $feed_item['pubDate'] : time(),
            'source' => $source
        );
        
        // Trigger the news company linker
        do_action('sffc_process_feed_item', $news_data, $source);
        
        // Store in database if it's PE-related
        if ($this->is_pe_related($news_data)) {
            $this->store_pe_news($news_data);
        }
    }
    
    /**
     * Check if news is PE-related
     */
    private function is_pe_related($news_data) {
        $pe_keywords = array(
            'private equity', 'buyout', 'acquisition', 'merger', 'portfolio company',
            'fund raise', 'exit', 'ipo', 'leverage', 'capital', 'investment',
            'KKR', 'Blackstone', 'Apollo', 'Carlyle', 'TPG', 'Warburg Pincus',
            'Bain Capital', 'Advent', 'CVC', 'EQT', 'Vista Equity'
        );
        
        $text = strtolower($news_data['title'] . ' ' . $news_data['description']);
        
        foreach ($pe_keywords as $keyword) {
            if (stripos($text, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Store PE news
     */
    private function store_pe_news($news_data) {
        $news_company_linker = SFFC_News_Company_Linker::get_instance();
        $article_id = $news_company_linker->ensure_news_article($news_data);

        return $article_id;
    }
    
    /**
     * Process all feeds
     */
    public function process_all_feeds() {
        // Get feed processor instance
        if (class_exists('SFFC_XML_Feed_Processor')) {
            $feed_processor = SFFC_XML_Feed_Processor::get_instance();
            
            // Process PE deals feeds
            $pe_entities = array(
                'companies' => $this->get_pe_companies(),
                'sectors' => array('technology', 'healthcare', 'finance', 'consumer')
            );
            
            // Process market data feeds
            $market_data = $feed_processor->process_category_feeds('market_data', $pe_entities);
            if ($market_data) {
                foreach ($market_data as $feed_data) {
                    if (isset($feed_data['items'])) {
                        foreach ($feed_data['items'] as $item) {
                            $this->process_feed_item_for_pe($item, $feed_data['source']);
                        }
                    }
                }
            }
            
            // Process PE deals feeds
            $pe_deals = $feed_processor->process_category_feeds('pe_deals', $pe_entities);
            if ($pe_deals) {
                foreach ($pe_deals as $feed_data) {
                    if (isset($feed_data['items'])) {
                        foreach ($feed_data['items'] as $item) {
                            $this->process_feed_item_for_pe($item, $feed_data['source']);
                        }
                    }
                }
            }
            
            // Process company news feeds
            $company_news = $feed_processor->process_category_feeds('company_news', $pe_entities);
            if ($company_news) {
                foreach ($company_news as $feed_data) {
                    if (isset($feed_data['items'])) {
                        foreach ($feed_data['items'] as $item) {
                            $this->process_feed_item_for_pe($item, $feed_data['source']);
                        }
                    }
                }
            }
        }
        
        // Update company metrics after processing
        $this->update_company_metrics();
    }
    
    /**
     * Get PE companies for filtering
     */
    private function get_pe_companies() {
        $companies = array();
        
        $posts = get_posts(array(
            'post_type' => 'sffc_company',
            'posts_per_page' => -1
        ));
        
        foreach ($posts as $post) {
            $name = $post->post_title;
            if (class_exists('SFFC_Company_Title_Helper')) {
                $name = SFFC_Company_Title_Helper::get_canonical_name($post);
            }

            $companies[] = array(
                'name' => $name,
                'ticker' => get_post_meta($post->ID, '_sffc_ticker', true)
            );
        }
        
        return $companies;
    }
    
    /**
     * Update company metrics
     */
    private function update_company_metrics() {
        global $wpdb;
        
        $companies = get_posts(array(
            'post_type' => 'sffc_company',
            'posts_per_page' => -1
        ));
        
        foreach ($companies as $company) {
            // Count today's news
            $table = $wpdb->prefix . 'sffc_company_news_links';
            $today_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table 
                WHERE company_id = %d 
                AND DATE(created_at) = CURDATE()",
                $company->ID
            ));
            
            update_post_meta($company->ID, '_sffc_news_count_today', $today_count);
            
            // Count week's news
            $week_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table 
                WHERE company_id = %d 
                AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
                $company->ID
            ));
            
            update_post_meta($company->ID, '_sffc_news_count_week', $week_count);
        }
    }
    
    /**
     * Get news category ID
     */
    private function get_news_category_id() {
        $category = get_category_by_slug('market-news');
        
        if (!$category) {
            $cat_id = wp_create_category('Market News');
            return $cat_id;
        }
        
        return $category->term_id;
    }
    
    /**
     * AJAX handler for manual feed processing
     */
    public function ajax_process_feeds() {
        check_ajax_referer('sffc_intelligence_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $this->process_all_feeds();
        
        wp_send_json_success(array(
            'message' => 'Feeds processed successfully',
            'timestamp' => current_time('mysql')
        ));
    }
    
    /**
     * Get feed status
     */
    public function get_feed_status() {
        $status = array(
            'last_run' => get_option('sffc_feeds_last_run', 'Never'),
            'next_run' => wp_next_scheduled('sffc_process_pe_feeds') ? 
                         date('Y-m-d H:i:s', wp_next_scheduled('sffc_process_pe_feeds')) : 
                         'Not scheduled',
            'total_news' => wp_count_posts('post')->publish,
            'companies_tracked' => wp_count_posts('sffc_company')->publish
        );
        
        return $status;
    }
}

// Initialize
SFFC_Feed_Integration_Connector::get_instance();
