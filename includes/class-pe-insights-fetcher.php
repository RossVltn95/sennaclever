<?php
/**
 * PE Insights Data Fetcher
 * 
 * Fetches real market data from PE Insights XML sitemaps
 * Provides authentic deal information for template enhancement
 * 
 * @package SennaFinanceCareer
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_PE_Insights_Fetcher {
    
    private static $instance = null;
    private $base_url = 'https://pe-insights.com/wp-sitemap-posts-post-';
    private $max_sitemap_index = 10;
    private $cache_duration = 24 * HOUR_IN_SECONDS; // 24 hours
    
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
        // Schedule daily refresh
        add_action('init', array($this, 'schedule_daily_fetch'));
        add_action('sffc_pe_insights_fetch', array($this, 'fetch_and_cache_deals'));
        
        // Admin hooks for manual refresh
        add_action('wp_ajax_sffc_refresh_pe_insights', array($this, 'ajax_manual_refresh'));
    }
    
    /**
     * Schedule daily PE Insights data fetch
     */
    public function schedule_daily_fetch() {
        if (!wp_next_scheduled('sffc_pe_insights_fetch')) {
            wp_schedule_event(time(), 'daily', 'sffc_pe_insights_fetch');
        }
    }
    
    /**
     * Fetch deals from all available sitemaps
     */
    public function fetch_all_deals($force_refresh = false) {
        $cache_key = 'sffc_pe_insights_deals';
        
        if (!$force_refresh) {
            $cached_deals = get_transient($cache_key);
            if ($cached_deals !== false) {
                return $cached_deals;
            }
        }
        
        $all_deals = array();
        $successful_fetches = 0;
        $errors = array();
        
        // Try sitemaps 5 through 10
        for ($i = 5; $i <= $this->max_sitemap_index; $i++) {
            $url = $this->base_url . $i . '.xml';
            
            try {
                $deals = $this->fetch_sitemap_deals($url);
                if (!empty($deals)) {
                    $all_deals = array_merge($all_deals, $deals);
                    $successful_fetches++;
                    
                    // Log successful fetch
                    error_log("SFFC: Successfully fetched " . count($deals) . " deals from sitemap-{$i}");
                }
            } catch (Exception $e) {
                $errors[] = array(
                    'sitemap' => $i,
                    'error' => $e->getMessage(),
                    'url' => $url
                );
                error_log("SFFC: Failed to fetch sitemap-{$i}: " . $e->getMessage());
            }
        }
        
        // Sort deals by date (newest first)
        usort($all_deals, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
        // Keep only the most recent 200 deals
        $all_deals = array_slice($all_deals, 0, 200);
        
        // Cache the results
        set_transient($cache_key, $all_deals, $this->cache_duration);
        
        // Update fetch status
        update_option('sffc_pe_insights_last_fetch', array(
            'timestamp' => time(),
            'total_deals' => count($all_deals),
            'successful_sitemaps' => $successful_fetches,
            'errors' => $errors,
            'status' => empty($errors) ? 'success' : 'partial'
        ));
        
        return $all_deals;
    }
    
    /**
     * Fetch deals from a single sitemap XML
     */
    private function fetch_sitemap_deals($url) {
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'headers' => array(
                'User-Agent' => 'SennaFinance/1.0'
            )
        ));
        
        if (is_wp_error($response)) {
            throw new Exception('HTTP Request failed: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            throw new Exception('HTTP Status: ' . $status_code);
        }
        
        $xml_content = wp_remote_retrieve_body($response);
        if (empty($xml_content)) {
            throw new Exception('Empty response body');
        }
        
        return $this->parse_sitemap_xml($xml_content);
    }
    
    /**
     * Parse XML sitemap and extract deal information
     */
    private function parse_sitemap_xml($xml_content) {
        // Disable libxml errors to handle malformed XML gracefully
        libxml_use_internal_errors(true);
        
        $xml = simplexml_load_string($xml_content);
        if ($xml === false) {
            $errors = libxml_get_errors();
            $error_message = 'XML Parse error';
            if (!empty($errors)) {
                $error_message .= ': ' . $errors[0]->message;
            }
            throw new Exception($error_message);
        }
        
        $deals = array();
        $namespaces = $xml->getNamespaces(true);
        
        foreach ($xml->url as $url_entry) {
            $loc = (string) $url_entry->loc;
            $lastmod = (string) $url_entry->lastmod;
            
            // Extract title from URL or use a default pattern
            $title = $this->extract_title_from_url($loc);
            
            // Basic deal categorization based on URL patterns and title
            $category = $this->categorize_deal($title, $loc);
            
            if ($category) {
                $deals[] = array(
                    'url' => $loc,
                    'title' => $title,
                    'date' => $lastmod ?: date('Y-m-d'),
                    'category' => $category,
                    'source' => 'pe-insights'
                );
            }
        }
        
        return $deals;
    }
    
    /**
     * Extract meaningful title from URL
     */
    private function extract_title_from_url($url) {
        $path = parse_url($url, PHP_URL_PATH);
        $slug = basename($path);
        
        // Convert slug to title
        $title = str_replace(array('-', '_'), ' ', $slug);
        $title = ucwords($title);
        
        return $title;
    }
    
    /**
     * Categorize deal based on title and URL patterns
     */
    private function categorize_deal($title, $url) {
        $title_lower = strtolower($title);
        $url_lower = strtolower($url);
        
        // Fundraising keywords
        if (preg_match('/\b(fund|fundraise|raise|capital|billion|million|close|target)\b/', $title_lower)) {
            return 'fundraise';
        }
        
        // Acquisition keywords
        if (preg_match('/\b(acquire|acquisition|buy|purchase|takeover|buyout)\b/', $title_lower)) {
            return 'acquisition';
        }
        
        // Exit keywords
        if (preg_match('/\b(exit|sale|sell|ipo|listing|disposal|divest)\b/', $title_lower)) {
            return 'exit';
        }
        
        // ESG keywords
        if (preg_match('/\b(esg|sustainability|climate|carbon|renewable|diversity)\b/', $title_lower)) {
            return 'esg';
        }
        
        // Regulation keywords
        if (preg_match('/\b(regulation|regulatory|compliance|sec|fine|penalty)\b/', $title_lower)) {
            return 'regulation';
        }
        
        // People moves keywords
        if (preg_match('/\b(appoint|hire|join|promote|partner|ceo|cfo|director)\b/', $title_lower)) {
            return 'people_moves';
        }
        
        // Default to general if no specific category
        return 'general';
    }
    
    /**
     * Get recent deals by category
     */
    public function get_deals_by_category($category, $limit = 20) {
        $all_deals = $this->fetch_all_deals();
        
        $filtered_deals = array_filter($all_deals, function($deal) use ($category) {
            return $deal['category'] === $category;
        });
        
        return array_slice($filtered_deals, 0, $limit);
    }
    
    /**
     * Get fetch status for admin dashboard
     */
    public function get_fetch_status() {
        return get_option('sffc_pe_insights_last_fetch', array(
            'timestamp' => 0,
            'total_deals' => 0,
            'successful_sitemaps' => 0,
            'errors' => array(),
            'status' => 'never'
        ));
    }
    
    /**
     * Manual refresh via AJAX
     */
    public function ajax_manual_refresh() {
        // Check nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'sffc_admin_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
            return;
        }
        
        try {
            $deals = $this->fetch_all_deals(true);
            wp_send_json_success(array(
                'message' => 'Successfully refreshed PE Insights data',
                'deals_count' => count($deals),
                'timestamp' => time()
            ));
        } catch (Exception $e) {
            wp_send_json_error('Refresh failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Scheduled fetch and cache
     */
    public function fetch_and_cache_deals() {
        try {
            $this->fetch_all_deals(true);
        } catch (Exception $e) {
            error_log('SFFC: Scheduled PE Insights fetch failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Clear cache
     */
    public function clear_cache() {
        delete_transient('sffc_pe_insights_deals');
    }
}

// Initialize the fetcher
SFFC_PE_Insights_Fetcher::get_instance();