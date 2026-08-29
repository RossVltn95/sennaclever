<?php
/**
 * Feed Management AJAX Handlers
 * 
 * @package SennaCareers
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Feed_Ajax_Handlers {
    
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
        if (!apply_filters('sffc_pe_news_feed_enabled', false)) {
            return;
        }

        // Feed management AJAX actions
        add_action('wp_ajax_sffc_add_feed', array($this, 'handle_add_feed'));
        add_action('wp_ajax_sffc_test_feed', array($this, 'handle_test_feed'));
        add_action('wp_ajax_sffc_test_feed_url', array($this, 'handle_test_feed_url'));
        add_action('wp_ajax_sffc_test_existing_feed', array($this, 'handle_test_existing_feed'));
        add_action('wp_ajax_sffc_fetch_existing_feed_now', array($this, 'handle_fetch_existing_feed_now'));
        add_action('wp_ajax_sffc_delete_feed', array($this, 'handle_delete_feed'));
        add_action('wp_ajax_sffc_toggle_feed', array($this, 'handle_toggle_feed'));
        add_action('wp_ajax_sffc_toggle_feed_status', array($this, 'handle_toggle_feed_status'));
        add_action('wp_ajax_sffc_get_feeds_table', array($this, 'handle_get_feeds_table'));
        add_action('wp_ajax_sffc_bulk_feed_action', array($this, 'handle_bulk_feed_action'));
        
        // European feeds management
        add_action('wp_ajax_sffc_add_european_feeds_bulk', array($this, 'handle_add_european_feeds_bulk'));
        add_action('wp_ajax_sffc_preview_european_feeds', array($this, 'handle_preview_european_feeds'));
        
        // Manual job feed actions
        add_action('wp_ajax_sffc_fetch_manual_feeds', array($this, 'handle_fetch_manual_feeds'));
        add_action('wp_ajax_sffc_process_manual_feeds', array($this, 'handle_process_manual_feeds'));
    }
    
    /**
     * Handle add feed
     */
    public function handle_add_feed() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sffc_admin_nonce')) {
            wp_send_json_error(array('message' => 'Invalid security token'));
            return;
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }
        
        // Validate inputs
        $feed_name = isset($_POST['feed_name']) ? sanitize_text_field($_POST['feed_name']) : '';
        $feed_url = isset($_POST['feed_url']) ? esc_url_raw($_POST['feed_url']) : '';
        $feed_category = isset($_POST['feed_category']) ? sanitize_text_field($_POST['feed_category']) : 'general';
        $priority = isset($_POST['priority']) ? intval($_POST['priority']) : 10;
        
        if (empty($feed_name) || empty($feed_url)) {
            wp_send_json_error(array('message' => 'Feed name and URL are required'));
            return;
        }
        
        // Get database instance
        require_once SFFC_PLUGIN_DIR . 'includes/class-database.php';
        $db = SFFC_Database::get_instance();
        
        global $wpdb;
        $table_name = $db->get_table('xml_feeds');
        
        // Check if feed URL already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE feed_url = %s",
            $feed_url
        ));
        
        if ($existing) {
            wp_send_json_error(array('message' => 'This feed URL already exists'));
            return;
        }
        
        // Insert new feed
        $result = $wpdb->insert(
            $table_name,
            array(
                'feed_name' => $feed_name,
                'feed_url' => $feed_url,
                'feed_category' => $feed_category,
                'priority' => $priority,
                'is_active' => 1,
                'added_by' => get_current_user_id(),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s')
        );
        
        if ($result) {
            wp_send_json_success(array(
                'message' => 'Feed added successfully',
                'feed_id' => $wpdb->insert_id
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to add feed'));
        }
    }
    
    /**
     * Handle test feed
     */
    public function handle_test_feed() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sffc_admin_nonce')) {
            wp_send_json_error(array('message' => 'Invalid security token'));
            return;
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }
        
        $feed_url = isset($_POST['feed_url']) ? esc_url_raw($_POST['feed_url']) : '';
        $feed_id = isset($_POST['feed_id']) ? intval($_POST['feed_id']) : 0;
        
        // If feed_id provided, get URL from database
        if ($feed_id > 0) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-database.php';
            $db = SFFC_Database::get_instance();
            
            global $wpdb;
            $table_name = $db->get_table('xml_feeds');
            $feed_url = $wpdb->get_var($wpdb->prepare(
                "SELECT feed_url FROM {$table_name} WHERE id = %d",
                $feed_id
            ));
        }
        
        if (empty($feed_url)) {
            wp_send_json_error(array('message' => 'Feed URL is required'));
            return;
        }
        
        // Test the feed
        $response = wp_remote_get($feed_url, array(
            'timeout' => 10,
            'user-agent' => 'SkillFarmFinance/1.0'
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => 'Failed to fetch feed: ' . $response->get_error_message()
            ));
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            wp_send_json_error(array('message' => 'Feed returned empty content'));
            return;
        }
        
        // Try to parse as RSS/XML
        $xml = @simplexml_load_string($body);
        if ($xml === false) {
            wp_send_json_error(array('message' => 'Invalid RSS/XML format'));
            return;
        }
        
        // Count items and get sample titles
        $items = array();
        $sample_titles = array();
        
        // Check for RSS 2.0
        if (isset($xml->channel->item)) {
            foreach ($xml->channel->item as $item) {
                $items[] = $item;
                if (count($sample_titles) < 3) {
                    $sample_titles[] = (string) $item->title;
                }
            }
        }
        // Check for Atom
        elseif (isset($xml->entry)) {
            foreach ($xml->entry as $entry) {
                $items[] = $entry;
                if (count($sample_titles) < 3) {
                    $sample_titles[] = (string) $entry->title;
                }
            }
        }
        // Check for RDF
        elseif (isset($xml->item)) {
            foreach ($xml->item as $item) {
                $items[] = $item;
                if (count($sample_titles) < 3) {
                    $sample_titles[] = (string) $item->title;
                }
            }
        }
        
        $item_count = count($items);
        
        // Update last_fetched if testing existing feed
        if ($feed_id > 0) {
            global $wpdb;
            require_once SFFC_PLUGIN_DIR . 'includes/class-database.php';
            $db = SFFC_Database::get_instance();
            $table_name = $db->get_table('xml_feeds');
            
            $wpdb->update(
                $table_name,
                array(
                    'last_fetched' => current_time('mysql'),
                    'success_count' => $wpdb->get_var($wpdb->prepare(
                        "SELECT success_count FROM {$table_name} WHERE id = %d",
                        $feed_id
                    )) + 1
                ),
                array('id' => $feed_id),
                array('%s', '%d'),
                array('%d')
            );
        }
        
        wp_send_json_success(array(
            'message' => 'Feed test successful',
            'item_count' => $item_count,
            'sample_titles' => $sample_titles
        ));
    }
    
    /**
     * Handle delete feed
     */
    public function handle_delete_feed() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sffc_admin_nonce')) {
            wp_send_json_error(array('message' => 'Invalid security token'));
            return;
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }
        
        $feed_id = isset($_POST['feed_id']) ? intval($_POST['feed_id']) : 0;
        
        if (!$feed_id) {
            wp_send_json_error(array('message' => 'Invalid feed ID'));
            return;
        }
        
        // Delete from database
        require_once SFFC_PLUGIN_DIR . 'includes/class-database.php';
        $db = SFFC_Database::get_instance();
        
        global $wpdb;
        $table_name = $db->get_table('xml_feeds');
        
        $result = $wpdb->delete(
            $table_name,
            array('id' => $feed_id),
            array('%d')
        );
        
        if ($result) {
            wp_send_json_success(array('message' => 'Feed deleted successfully'));
        } else {
            wp_send_json_error(array('message' => 'Failed to delete feed'));
        }
    }
    
    /**
     * Handle toggle feed active status
     */
    public function handle_toggle_feed() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sffc_admin_nonce')) {
            wp_send_json_error(array('message' => 'Invalid security token'));
            return;
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }
        
        $feed_id = isset($_POST['feed_id']) ? intval($_POST['feed_id']) : 0;
        $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 0;
        
        if (!$feed_id) {
            wp_send_json_error(array('message' => 'Invalid feed ID'));
            return;
        }
        
        // Update in database
        require_once SFFC_PLUGIN_DIR . 'includes/class-database.php';
        $db = SFFC_Database::get_instance();
        
        global $wpdb;
        $table_name = $db->get_table('xml_feeds');
        
        $result = $wpdb->update(
            $table_name,
            array(
                'is_active' => $is_active,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $feed_id),
            array('%d', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success(array('message' => 'Feed status updated'));
        } else {
            wp_send_json_error(array('message' => 'Failed to update feed status'));
        }
    }
    
    /**
     * Handle get feeds table HTML
     */
    public function handle_get_feeds_table() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sffc_admin_nonce')) {
            wp_send_json_error(array('message' => 'Invalid security token'));
            return;
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }
        
        // Get the admin settings instance to use its render method
        require_once SFFC_PLUGIN_DIR . 'admin/class-admin-settings.php';
        $admin = new SFFC_Admin_Settings();
        
        // Capture the output
        ob_start();
        
        // Use reflection to call the private method
        $reflection = new ReflectionClass($admin);
        $method = $reflection->getMethod('render_feeds_table');
        $method->setAccessible(true);
        $method->invoke($admin);
        
        $html = ob_get_clean();
        
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * Handle test feed URL (for new feeds)
     */
    public function handle_test_feed_url() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sffc_settings_nonce')) {
            wp_send_json_error('Invalid security token');
            return;
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $feed_url = isset($_POST['feed_url']) ? esc_url_raw($_POST['feed_url']) : '';
        
        if (!$feed_url) {
            wp_send_json_error('Feed URL is required');
            return;
        }
        
        $analysis = $this->analyze_feed_url($feed_url);
        if (is_wp_error($analysis)) {
            wp_send_json_error($analysis->get_error_message());
            return;
        }
        
        wp_send_json_success(array(
            'title' => $analysis['title'],
            'item_count' => $analysis['item_count'],
            'sample_items' => $analysis['sample_items'],
            'last_updated' => current_time('mysql')
        ));
    }
    
    /**
     * Handle test existing feed
     */
    public function handle_test_existing_feed() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sffc_settings_nonce')) {
            wp_send_json_error('Invalid security token');
            return;
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $feed_id = isset($_POST['feed_id']) ? intval($_POST['feed_id']) : 0;
        
        if (!$feed_id) {
            wp_send_json_error('Invalid feed ID');
            return;
        }
        
        // Get feed from database
        global $wpdb;
        require_once SFFC_PLUGIN_DIR . 'includes/class-database.php';
        $db = SFFC_Database::get_instance();
        $table_name = $db->get_table('xml_feeds');
        
        $feed = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $feed_id
        ));
        
        if (!$feed) {
            wp_send_json_error('Feed not found');
            return;
        }
        
        $analysis = $this->analyze_feed_url($feed->feed_url);
        if (is_wp_error($analysis)) {
            wp_send_json_error($analysis->get_error_message());
            return;
        }
        
        // Update last_fetched
        $wpdb->update(
            $table_name,
            array(
                'last_fetched' => current_time('mysql'),
                'error_count' => 0,
                'last_error' => null
            ),
            array('id' => $feed_id),
            array('%s', '%d', '%s'),
            array('%d')
        );
        
        wp_send_json_success(sprintf(
            __('Feed test successful: %d item(s) detected.', 'senna-finance'),
            $analysis['item_count']
        ));
    }

    private function analyze_feed_url($feed_url) {
        $response = wp_remote_get($feed_url, array(
            'timeout' => 20,
            'redirection' => 5,
            'user-agent' => 'Mozilla/5.0 (compatible; SennaCareersFeedTester/1.0; +https://joinsenna.com/)',
            'headers' => array(
                'Accept' => 'application/rss+xml, application/atom+xml, application/xml, text/xml, */*;q=0.8',
            ),
        ));

        if (is_wp_error($response)) {
            return new WP_Error('feed_fetch_failed', 'Failed to fetch feed: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $code = (int) wp_remote_retrieve_response_code($response);

        if ($code < 200 || $code >= 300) {
            return new WP_Error('feed_http_error', 'HTTP Error ' . $code);
        }

        if (empty($body)) {
            return new WP_Error('feed_empty', 'Feed returned empty content');
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
        if (!$xml) {
            return new WP_Error('feed_invalid_xml', 'Invalid XML format');
        }

        $title = '';
        $sample_items = array();
        $item_count = 0;

        if (isset($xml->channel)) {
            $title = (string) $xml->channel->title;
            foreach ($xml->channel->item as $item) {
                $item_count++;
                if (count($sample_items) < 3) {
                    $sample_items[] = (string) $item->title;
                }
            }
        } elseif (isset($xml->entry)) {
            $title = (string) $xml->title;
            foreach ($xml->entry as $entry) {
                $item_count++;
                if (count($sample_items) < 3) {
                    $sample_items[] = (string) $entry->title;
                }
            }
        } elseif (isset($xml->item)) {
            $title = (string) $xml->channel->title;
            foreach ($xml->item as $item) {
                $item_count++;
                if (count($sample_items) < 3) {
                    $sample_items[] = (string) $item->title;
                }
            }
        } else {
            $url_nodes = $xml->xpath('//*[local-name()="url"]') ?: array();
            $sitemap_nodes = $xml->xpath('//*[local-name()="sitemap"]') ?: array();
            $nodes = !empty($url_nodes) ? $url_nodes : $sitemap_nodes;
            $title = parse_url($feed_url, PHP_URL_HOST);

            foreach ($nodes as $node) {
                $loc = $this->get_xml_child_value($node, 'loc');
                if (!$loc) {
                    continue;
                }

                $item_count++;
                if (count($sample_items) < 3) {
                    $sample_items[] = $loc;
                }
            }
        }

        if ($item_count === 0) {
            return new WP_Error('feed_no_items', 'Valid XML, but no RSS/Atom/RDF items or sitemap URLs were found');
        }

        return array(
            'title' => $title,
            'item_count' => $item_count,
            'sample_items' => $sample_items,
        );
    }

    private function get_xml_child_value($node, $name) {
        $matches = $node->xpath('./*[local-name()="' . $name . '"]');
        return !empty($matches) ? trim((string) $matches[0]) : '';
    }

    /**
     * Handle fetch existing feed now
     */
    public function handle_fetch_existing_feed_now() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sffc_settings_nonce')) {
            wp_send_json_error('Invalid security token');
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $feed_id = isset($_POST['feed_id']) ? intval($_POST['feed_id']) : 0;
        if (!$feed_id) {
            wp_send_json_error('Invalid feed ID');
            return;
        }

        if (!class_exists('SFFC_PE_Content_Manager')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-pe-content-manager.php';
        }

        $manager = SFFC_PE_Content_Manager::get_instance();
        $result = $manager->process_single_feed_content($feed_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
            return;
        }

        $counts = isset($result['counts']) && is_array($result['counts']) ? $result['counts'] : array();
        $created = isset($result['created']) ? intval($result['created']) : 0;
        $message = $created > 0
            ? sprintf(
                __('Fetched %1$s and imported %2$d new item(s).', 'senna-finance'),
                $result['feed_name'] ?? __('feed', 'senna-finance'),
                $created
            )
            : sprintf(
                __('Fetched %s with no new items to import.', 'senna-finance'),
                $result['feed_name'] ?? __('feed', 'senna-finance')
            );

        wp_send_json_success(array(
            'message' => $message,
            'created' => $created,
            'counts' => $counts,
            'last_fetched' => __('just now', 'senna-finance'),
            'status_html' => '<span class="sffc-status sffc-status-success">' . esc_html__('Active', 'senna-finance') . '</span>',
        ));
    }
    
    /**
     * Handle toggle feed status
     */
    public function handle_toggle_feed_status() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sffc_settings_nonce')) {
            wp_send_json_error('Invalid security token');
            return;
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $feed_id = isset($_POST['feed_id']) ? intval($_POST['feed_id']) : 0;
        $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 0;
        
        if (!$feed_id) {
            wp_send_json_error('Invalid feed ID');
            return;
        }
        
        // Update in database
        global $wpdb;
        require_once SFFC_PLUGIN_DIR . 'includes/class-database.php';
        $db = SFFC_Database::get_instance();
        $table_name = $db->get_table('xml_feeds');
        
        $result = $wpdb->update(
            $table_name,
            array(
                'is_active' => $is_active,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $feed_id),
            array('%d', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success('Feed status updated');
        } else {
            wp_send_json_error('Failed to update feed status');
        }
    }
    
    /**
     * Handle bulk feed actions
     */
    public function handle_bulk_feed_action() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sffc_settings_nonce')) {
            wp_send_json_error('Invalid security token');
            return;
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $action = isset($_POST['bulk_action']) ? sanitize_text_field($_POST['bulk_action']) : '';
        $feed_ids = isset($_POST['feed_ids']) ? array_map('intval', $_POST['feed_ids']) : array();
        
        if (!$action || empty($feed_ids)) {
            wp_send_json_error('Invalid parameters');
            return;
        }
        
        global $wpdb;
        require_once SFFC_PLUGIN_DIR . 'includes/class-database.php';
        $db = SFFC_Database::get_instance();
        $table_name = $db->get_table('xml_feeds');
        
        $ids_placeholder = implode(',', array_fill(0, count($feed_ids), '%d'));
        
        $count = count($feed_ids);
        $message = '';

        switch ($action) {
            case 'activate':
            case 'enable':
                $result = $wpdb->query($wpdb->prepare(
                    "UPDATE {$table_name} SET is_active = 1, updated_at = %s WHERE id IN ({$ids_placeholder})",
                    array_merge([current_time('mysql')], $feed_ids)
                ));
                $message = "Enabled {$count} feed(s)";
                break;

            case 'deactivate':
            case 'disable':
                $result = $wpdb->query($wpdb->prepare(
                    "UPDATE {$table_name} SET is_active = 0, updated_at = %s WHERE id IN ({$ids_placeholder})",
                    array_merge([current_time('mysql')], $feed_ids)
                ));
                $message = "Disabled {$count} feed(s)";
                break;

            case 'delete':
                $result = $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$table_name} WHERE id IN ({$ids_placeholder})",
                    $feed_ids
                ));
                $message = "Deleted {$count} feed(s)";
                break;

            case 'test':
                // Test each feed individually
                $feeds = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, feed_url FROM {$table_name} WHERE id IN ({$ids_placeholder})",
                    $feed_ids
                ));

                $tested = 0;
                foreach ($feeds as $feed) {
                    // Test each feed
                    $response = wp_remote_get($feed->feed_url, array(
                        'timeout' => 10,
                        'user-agent' => 'SFFC Feed Tester/1.0'
                    ));

                    if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                        $tested++;
                        // Update last_fetched
                        $wpdb->update(
                            $table_name,
                            array('last_fetched' => current_time('mysql'), 'error_count' => 0),
                            array('id' => $feed->id),
                            array('%s', '%d'),
                            array('%d')
                        );
                    }
                }

                wp_send_json_success(array('message' => "Tested {$tested} of {$count} feeds successfully"));
                return;

            default:
                wp_send_json_error('Invalid action');
                return;
        }

        if ($result !== false) {
            wp_send_json_success(array('message' => $message));
        } else {
            wp_send_json_error('Bulk action failed');
        }
    }
    
    /**
     * Handle add European feeds in bulk
     */
    public function handle_add_european_feeds_bulk() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sffc_settings_nonce')) {
            wp_send_json_error('Invalid security token');
            return;
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        // European feeds array
        $european_feeds = array(
            // Priority European Market Data (1-10)
            array('Yahoo Finance - FTSE 100', 'https://feeds.finance.yahoo.com/rss/2.0/headline?s=^FTSE&region=US&lang=en-US', 'markets', 1),
            array('Yahoo Finance - DAX', 'https://feeds.finance.yahoo.com/rss/2.0/headline?s=^GDAXI&region=US&lang=en-US', 'markets', 2),
            array('Yahoo Finance - CAC 40', 'https://feeds.finance.yahoo.com/rss/2.0/headline?s=^FCHI&region=US&lang=en-US', 'markets', 3),
            array('Yahoo Finance - STOXX 600', 'https://feeds.finance.yahoo.com/rss/2.0/headline?s=^STOXX&region=US&lang=en-US', 'markets', 4),
            array('Financial Times - Markets', 'https://www.ft.com/markets?format=rss', 'markets', 5),
            array('Financial Times - Europe', 'https://www.ft.com/world/europe?format=rss', 'markets', 6),
            array('Reuters - European Markets', 'https://feeds.reuters.com/reuters/UKbusinessNews', 'markets', 7),
            array('Bloomberg - Markets', 'https://feeds.bloomberg.com/markets/news.rss', 'markets', 8),
            array('MarketWatch - Europe', 'https://feeds.marketwatch.com/marketwatch/marketpulse/', 'markets', 9),
            array('European Financial Review', 'https://www.europeanfinancialreview.com/feed/', 'markets', 10),

            // Private Equity & VC Feeds (15-25)
            array('Private Equity Wire', 'https://www.privateequitywire.co.uk/feed/', 'private-equity', 15),
            array('Alternative Investment News', 'https://www.ai-cio.com/feed/', 'alternatives', 18),
            array('Venture Capital Journal', 'https://www.venturecapitaljournal.com/feed/', 'venture-capital', 19),

            // European Business News (20-30)
            array('City AM - London', 'https://www.cityam.com/feed/', 'business', 20),
            array('BBC Business', 'http://feeds.bbci.co.uk/news/business/rss.xml', 'business', 21),
            array('The Telegraph Business', 'https://www.telegraph.co.uk/business/rss.xml', 'business', 22),
            array('Guardian Business', 'https://www.theguardian.com/uk/business/rss', 'business', 23),
            array('Irish Times Business', 'https://www.irishtimes.com/cmlink/business-1.1319192', 'business', 24),

            // Central Banks & Policy (30-40)
            array('European Central Bank', 'https://www.ecb.europa.eu/rss/news.xml', 'central-banks', 30),
            array('Bank of England', 'https://www.bankofengland.co.uk/news.rss', 'central-banks', 31),
            array('Swiss National Bank', 'https://www.snb.ch/rss/en/mmr', 'central-banks', 33),

            // Sector-Specific (40-50)
            array('Energy Voice', 'https://www.energyvoice.com/feed/', 'commodities', 40),
            array('FinTech Futures', 'https://www.fintechfutures.com/feed/', 'business', 41),
            array('European Pharma Review', 'https://www.europeanpharmaceuticalreview.com/feed/', 'business', 42),

            // Research & Economics (50-60)
            array('Oxford Economics', 'https://www.oxfordeconomics.com/recent-releases/rss', 'research', 50),
            array('OECD Economics', 'https://www.oecd.org/economy/rss.xml', 'research', 52),

            // Cryptocurrency (European Focus) (60-70)
            array('CoinDesk', 'https://feeds.feedburner.com/CoinDesk', 'crypto', 60),
            array('The Block', 'https://www.theblockcrypto.com/rss.xml', 'crypto', 61),

            // Emerging Markets (70-80)
            array('Emerging Europe', 'https://emerging-europe.com/feed/', 'emerging-markets', 70),
        );
        
        global $wpdb;
        require_once SFFC_PLUGIN_DIR . 'includes/class-database.php';
        $db = SFFC_Database::get_instance();
        $table_name = $db->get_table('xml_feeds');
        
        if (!$table_name) {
            wp_send_json_error('Feed table not found. Please create database tables first.');
            return;
        }
        
        $added = 0;
        $skipped = 0;
        $errors = 0;
        
        foreach ($european_feeds as $feed) {
            list($name, $url, $category, $priority) = $feed;
            
            // Check if feed already exists
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table_name} WHERE feed_url = %s",
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
                    'updated_at' => current_time('mysql'),
                    'last_fetched' => null,
                    'error_count' => 0,
                    'success_count' => 0,
                    'last_error' => null
                ),
                array('%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%s')
            );
            
            if ($result) {
                $added++;
            } else {
                $errors++;
            }
        }
        
        wp_send_json_success(array(
            'added' => $added,
            'skipped' => $skipped,
            'errors' => $errors,
            'message' => "Successfully added {$added} European feeds. Skipped {$skipped} existing feeds."
        ));
    }
    
    /**
     * Handle preview European feeds
     */
    public function handle_preview_european_feeds() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sffc_settings_nonce')) {
            wp_send_json_error('Invalid security token');
            return;
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $preview_html = '<h1>European Market Analysis Feeds Preview</h1>';
        $preview_html .= '<p>This will add the following feeds to your system:</p>';
        
        $categories = array(
            'markets' => 'European Market Data',
            'private-equity' => 'Private Equity',
            'alternatives' => 'Alternative Investments', 
            'venture-capital' => 'Venture Capital',
            'business' => 'European Business News',
            'central-banks' => 'Central Banks & Policy',
            'commodities' => 'Commodities & Energy',
            'research' => 'Research & Economics',
            'crypto' => 'Cryptocurrency',
            'emerging-markets' => 'Emerging Markets'
        );
        
        foreach ($categories as $category => $title) {
            $preview_html .= "<h2>{$title}</h2><ul>";
            
            // Add specific feeds for each category (abbreviated for preview)
            switch ($category) {
                case 'markets':
                    $preview_html .= '<li>Yahoo Finance - FTSE 100</li>';
                    $preview_html .= '<li>Yahoo Finance - DAX</li>';
                    $preview_html .= '<li>Financial Times - Markets</li>';
                    $preview_html .= '<li>Reuters - European Markets</li>';
                    $preview_html .= '<li>Bloomberg - Markets</li>';
                    break;
                case 'private-equity':
                    $preview_html .= '<li>Private Equity Wire</li>';
                    $preview_html .= '<li>Alternative Investment News</li>';
                    break;
                case 'business':
                    $preview_html .= '<li>City AM - London</li>';
                    $preview_html .= '<li>BBC Business</li>';
                    $preview_html .= '<li>The Telegraph Business</li>';
                    break;
                case 'central-banks':
                    $preview_html .= '<li>European Central Bank</li>';
                    $preview_html .= '<li>Bank of England</li>';
                    $preview_html .= '<li>Swiss National Bank</li>';
                    break;
                default:
                    $preview_html .= '<li>Various specialized feeds...</li>';
            }
            
            $preview_html .= '</ul>';
        }
        
        $preview_html .= '<p><strong>Total: 25+ European market and financial news feeds</strong></p>';
        $preview_html .= '<p>These feeds will provide real-time data for your European market analysis platform.</p>';
        
        wp_send_json_success($preview_html);
    }
    
    /**
     * Handle fetch manual feeds
     */
    public function handle_fetch_manual_feeds() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sffc_admin_nonce')) {
            wp_send_json_error(array('message' => 'Invalid security token'));
            return;
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }
        
        // Get manual job feed generator
        require_once SFFC_PLUGIN_DIR . 'includes/class-manual-job-feed-generator.php';
        $generator = new SFFC_Manual_Job_Feed_Generator();
        
        $feeds = $generator->get_manual_feeds();
        
        if (empty($feeds)) {
            wp_send_json_error(array('message' => 'No manual job feeds found'));
            return;
        }
        
        // Create a temporary "fetch" button context for manual feeds
        $processed = 0;
        $errors = array();
        
        foreach ($feeds as $feed) {
            try {
                // Read the XML feed file
                $xml_content = file_get_contents($feed['path']);
                
                if (!$xml_content) {
                    $errors[] = 'Failed to read feed: ' . $feed['filename'];
                    continue;
                }
                
                // Process the XML feed through existing XML processor
                require_once SFFC_PLUGIN_DIR . 'includes/class-xml-feed-processor.php';
                $processor = new SFFC_XML_Feed_Processor();
                
                // Create a temporary feed record for processing
                $temp_feed = (object) array(
                    'id' => 'manual_' . time(),
                    'feed_name' => 'Manual Submission - ' . $feed['filename'],
                    'feed_url' => $feed['path'],
                    'feed_category' => 'manual',
                    'is_active' => 1
                );
                
                $result = $processor->process_feed_content($xml_content, $temp_feed);
                
                if (!is_wp_error($result)) {
                    $processed++;
                    
                    // Remove processed feed file
                    $generator->delete_feed($feed['filename']);
                } else {
                    $errors[] = $result->get_error_message();
                }
                
            } catch (Exception $e) {
                $errors[] = 'Error processing ' . $feed['filename'] . ': ' . $e->getMessage();
            }
        }
        
        wp_send_json_success(array(
            'message' => "Processed {$processed} manual feeds",
            'processed' => $processed,
            'errors' => $errors,
            'total_feeds' => count($feeds)
        ));
    }
    
    /**
     * Handle process manual feeds
     */
    public function handle_process_manual_feeds() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sffc_admin_nonce')) {
            wp_send_json_error(array('message' => 'Invalid security token'));
            return;
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }
        
        $feed_filename = isset($_POST['feed_filename']) ? sanitize_text_field($_POST['feed_filename']) : '';
        
        if (empty($feed_filename)) {
            wp_send_json_error(array('message' => 'Feed filename is required'));
            return;
        }
        
        // Get manual job feed generator
        require_once SFFC_PLUGIN_DIR . 'includes/class-manual-job-feed-generator.php';
        $generator = new SFFC_Manual_Job_Feed_Generator();
        
        $feeds = $generator->get_manual_feeds();
        $target_feed = null;
        
        foreach ($feeds as $feed) {
            if ($feed['filename'] === $feed_filename) {
                $target_feed = $feed;
                break;
            }
        }
        
        if (!$target_feed) {
            wp_send_json_error(array('message' => 'Feed not found'));
            return;
        }
        
        try {
            // Read the XML feed file
            $xml_content = file_get_contents($target_feed['path']);
            
            if (!$xml_content) {
                wp_send_json_error(array('message' => 'Failed to read feed file'));
                return;
            }
            
            // Process the XML feed through existing XML processor
            require_once SFFC_PLUGIN_DIR . 'includes/class-xml-feed-processor.php';
            $processor = new SFFC_XML_Feed_Processor();
            
            // Create a temporary feed record for processing
            $temp_feed = (object) array(
                'id' => 'manual_' . time(),
                'feed_name' => 'Manual Submission - ' . $feed_filename,
                'feed_url' => $target_feed['path'],
                'feed_category' => 'manual',
                'is_active' => 1
            );
            
            $result = $processor->process_feed_content($xml_content, $temp_feed);
            
            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()));
                return;
            }
            
            // Remove processed feed file
            $generator->delete_feed($feed_filename);
            
            wp_send_json_success(array(
                'message' => 'Manual feed processed successfully',
                'jobs_created' => $result
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Error processing feed: ' . $e->getMessage()));
        }
    }
}
