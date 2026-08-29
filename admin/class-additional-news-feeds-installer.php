<?php
/**
 * Additional News Feeds Installer
 * 
 * Comprehensive installer for additional financial news feeds including incremental sitemaps
 * 
 * @package SennaCareers
 * @subpackage NewsFeeds
 * @since 2.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Additional_News_Feeds_Installer {
    
    /**
     * Install additional news feeds
     */
    public static function install_feeds() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sffc_xml_feeds';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return array('success' => false, 'message' => 'Feed table does not exist');
        }
        
        // Additional comprehensive news feeds
        $feeds = array(
            // === AFRICAN PRIVATE EQUITY & MARKETS ===
            array('Africa Private Equity News', 'https://www.africaprivatequitynews.com/news_sitemap.xml', 'private-equity', 15),
            
            // === PE INSIGHTS & ANALYSIS ===
            array('PE Insights - Posts 1', 'https://pe-insights.com/wp-sitemap-posts-post-1.xml', 'private-equity', 20),
            array('PE Insights - Posts 2', 'https://pe-insights.com/wp-sitemap-posts-post-2.xml', 'private-equity', 21),
            array('PE Insights - Posts 3', 'https://pe-insights.com/wp-sitemap-posts-post-3.xml', 'private-equity', 22),
            array('PE Insights - Posts 4', 'https://pe-insights.com/wp-sitemap-posts-post-4.xml', 'private-equity', 23),
            array('PE Insights - Posts 5', 'https://pe-insights.com/wp-sitemap-posts-post-5.xml', 'private-equity', 24),
            
            // === ALTERNATIVE INVESTMENTS ===
            array('Alternative Watch', 'https://www.alternativewatch.com/post-sitemap8.xml', 'alternatives', 25),
            
            // === REAL ESTATE & ASSETS ===
            array('Real Assets - 2025', 'https://realassets.pe/google/sitemap.aspx?year=2025', 'real-estate', 30),
            
            // === PULSE 2.0 BUSINESS NEWS ===
            array('Pulse 2.0 News', 'https://pulse2.com/news-sitemap.xml', 'business-news', 35),
            
            // === THE REAL DEAL (INCREMENTAL) ===
            array('The Real Deal - 165', 'https://therealdeal.com/post-sitemap165.xml', 'real-estate', 40),
            array('The Real Deal - 166', 'https://therealdeal.com/post-sitemap166.xml', 'real-estate', 41),
            array('The Real Deal - 167', 'https://therealdeal.com/post-sitemap167.xml', 'real-estate', 42),
            array('The Real Deal - 168', 'https://therealdeal.com/post-sitemap168.xml', 'real-estate', 43),
            array('The Real Deal - 169', 'https://therealdeal.com/post-sitemap169.xml', 'real-estate', 44),
            array('The Real Deal - 170', 'https://therealdeal.com/post-sitemap170.xml', 'real-estate', 45),
            
            // === ESG TODAY (INCREMENTAL) ===
            array('ESG Today - 7', 'https://www.esgtoday.com/post-sitemap7.xml', 'esg', 50),
            array('ESG Today - 8', 'https://www.esgtoday.com/post-sitemap8.xml', 'esg', 51),
            array('ESG Today - 9', 'https://www.esgtoday.com/post-sitemap9.xml', 'esg', 52),
            array('ESG Today - 10', 'https://www.esgtoday.com/post-sitemap10.xml', 'esg', 53),
            array('ESG Today - 11', 'https://www.esgtoday.com/post-sitemap11.xml', 'esg', 54),
            
            // === CHAMPION MANAGER ONLINE ===
            array('Champion Manager - Top News', 'https://champmanager-online.com/en/rss/toper/news/', 'sports-business', 60),
            
            // === THE PEAK PE ===
            array('The Peak PE - 165', 'https://thepeakpe.com/post-sitemap165.xml', 'private-equity', 65),
            array('The Peak PE - 166', 'https://thepeakpe.com/post-sitemap166.xml', 'private-equity', 66),
            array('The Peak PE - 167', 'https://thepeakpe.com/post-sitemap167.xml', 'private-equity', 67),
            array('The Peak PE - 168', 'https://thepeakpe.com/post-sitemap168.xml', 'private-equity', 68),
            array('The Peak PE - 169', 'https://thepeakpe.com/post-sitemap169.xml', 'private-equity', 69),
            array('The Peak PE - 170', 'https://thepeakpe.com/post-sitemap170.xml', 'private-equity', 70),
        );
        
        $added = 0;
        $skipped = 0;
        $categories = array();
        
        foreach ($feeds as $feed_data) {
            list($name, $url, $category, $priority) = $feed_data;
            
            // Track categories
            if (!in_array($category, $categories)) {
                $categories[] = $category;
            }
            
            // Check if feed already exists
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name WHERE feed_url = %s",
                $url
            ));
            
            if ($existing) {
                $skipped++;
                continue;
            }
            
            // Insert new feed
            $result = $wpdb->insert(
                $table_name,
                array(
                    'feed_name' => $name,
                    'feed_url' => $url,
                    'feed_category' => $category,
                    'priority' => $priority,
                    'is_active' => 1,
                    'created_at' => current_time('mysql'),
                    'error_count' => 0
                ),
                array('%s', '%s', '%s', '%d', '%d', '%s', '%d')
            );
            
            if ($result) {
                $added++;
            }
        }
        
        return array(
            'success' => true,
            'added' => $added,
            'skipped' => $skipped,
            'total' => count($feeds),
            'categories' => count($categories),
            'category_list' => $categories,
            'focus' => 'Comprehensive financial news with incremental sitemap coverage'
        );
    }
    
    /**
     * Add incremental feeds for a specific domain
     * 
     * @param string $domain Domain name (e.g., 'therealdeal.co')
     * @param string $pattern URL pattern (e.g., 'https://therealdeal.com/post-sitemap{n}.xml')
     * @param int $start Starting number
     * @param int $end Ending number
     * @param string $category Feed category
     * @param int $base_priority Base priority (incremental feeds will start from this)
     */
    public static function add_incremental_feeds($domain, $pattern, $start, $end, $category, $base_priority) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sffc_xml_feeds';
        $added = 0;
        
        for ($i = $start; $i <= $end; $i++) {
            $url = str_replace('{n}', $i, $pattern);
            $name = $domain . ' - Sitemap ' . $i;
            $priority = $base_priority + ($i - $start);
            
            // Check if feed already exists
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name WHERE feed_url = %s",
                $url
            ));
            
            if (!$existing) {
                $result = $wpdb->insert(
                    $table_name,
                    array(
                        'feed_name' => $name,
                        'feed_url' => $url,
                        'feed_category' => $category,
                        'priority' => $priority,
                        'is_active' => 1,
                        'created_at' => current_time('mysql'),
                        'error_count' => 0
                    ),
                    array('%s', '%s', '%s', '%d', '%d', '%s', '%d')
                );
                
                if ($result) {
                    $added++;
                }
            }
        }
        
        return $added;
    }
    
    /**
     * Test feed availability
     * 
     * @param string $url Feed URL
     * @return array Test results
     */
    public static function test_feed($url) {
        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'user-agent' => 'SkillFarmFinance/2.1 (+https://joinsenna.com)'
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code === 200) {
            // Try to parse XML
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($body);
            
            if ($xml !== false) {
                return array(
                    'success' => true,
                    'message' => 'Feed is valid XML',
                    'items' => count($xml->xpath('//item | //entry | //url'))
                );
            } else {
                return array(
                    'success' => false,
                    'message' => 'Invalid XML format'
                );
            }
        } else {
            return array(
                'success' => false,
                'message' => 'HTTP ' . $status_code . ' error'
            );
        }
    }
}