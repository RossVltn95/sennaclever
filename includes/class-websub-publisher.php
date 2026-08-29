<?php
/**
 * WebSub/PubSubHubbub Publisher
 *
 * Instantly notifies Google and other subscribers when new content is published.
 * This dramatically reduces the time between publishing and indexing.
 *
 * @package SennaFinanceCareer
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_WebSub_Publisher {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * WebSub Hub URLs to ping
     */
    private $hubs = array(
        'https://pubsubhubbub.appspot.com/',      // Google's official hub
        'https://pubsubhubbub.superfeedr.com/',   // Superfeedr hub (backup)
        'https://websubhub.com/hub',              // WebSubHub (additional coverage)
    );

    /**
     * Post types to notify about
     */
    private $post_types = array(
        'post',
        'sffc_pe_news',
        'sffc_pe_deal',
    );

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
        // Hook into post publishing
        add_action('publish_post', array($this, 'on_publish'), 10, 2);
        add_action('publish_sffc_pe_news', array($this, 'on_publish'), 10, 2);
        add_action('publish_sffc_pe_deal', array($this, 'on_publish'), 10, 2);

        // Hook into post updates (for significant edits)
        add_action('post_updated', array($this, 'on_update'), 10, 3);

        // Add WebSub discovery links to feeds
        add_action('rss2_head', array($this, 'add_hub_links_to_feed'));
        add_action('atom_head', array($this, 'add_hub_links_to_feed'));
        add_action('rss_head', array($this, 'add_hub_links_to_feed'));

        // Add WebSub discovery links to HTML head
        add_action('wp_head', array($this, 'add_hub_links_to_head'), 5);

        // Register REST endpoint for hub verification
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        // Schedule periodic sitemap ping
        add_action('sffc_websub_sitemap_ping', array($this, 'ping_sitemap'));
        if (!wp_next_scheduled('sffc_websub_sitemap_ping')) {
            wp_schedule_event(time(), 'hourly', 'sffc_websub_sitemap_ping');
        }
    }

    /**
     * Handle new post publication
     */
    public function on_publish($post_id, $post) {
        // Avoid pinging for revisions or autosaves
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        // Check if this is a supported post type
        if (!in_array($post->post_type, $this->post_types)) {
            return;
        }

        // Don't ping if already pinged recently (within 5 minutes)
        $last_ping = get_post_meta($post_id, '_websub_last_ping', true);
        if ($last_ping && (time() - $last_ping) < 300) {
            return;
        }

        // Ping all hubs
        $this->ping_hubs($post_id);

        // Also ping Google's sitemap endpoint
        $this->ping_google_sitemap();

        // Ping IndexNow as well
        $this->ping_indexnow($post_id);

        // Update last ping time
        update_post_meta($post_id, '_websub_last_ping', time());

        // Log the ping
        $this->log_ping($post_id, 'publish');
    }

    /**
     * Handle post updates
     */
    public function on_update($post_id, $post_after, $post_before) {
        // Only ping if significant content changed
        if ($post_before->post_content === $post_after->post_content &&
            $post_before->post_title === $post_after->post_title) {
            return;
        }

        // Only for published posts
        if ($post_after->post_status !== 'publish') {
            return;
        }

        // Check if this is a supported post type
        if (!in_array($post_after->post_type, $this->post_types)) {
            return;
        }

        // Don't ping if already pinged recently (within 10 minutes for updates)
        $last_ping = get_post_meta($post_id, '_websub_last_ping', true);
        if ($last_ping && (time() - $last_ping) < 600) {
            return;
        }

        // Ping all hubs
        $this->ping_hubs($post_id);

        // Update last ping time
        update_post_meta($post_id, '_websub_last_ping', time());

        // Log the ping
        $this->log_ping($post_id, 'update');
    }

    /**
     * Ping all WebSub hubs
     */
    private function ping_hubs($post_id = null) {
        $feed_urls = $this->get_feed_urls();

        foreach ($this->hubs as $hub) {
            foreach ($feed_urls as $feed_url) {
                $this->send_ping($hub, $feed_url);
            }
        }

        // If specific post, also ping its permalink
        if ($post_id) {
            $permalink = get_permalink($post_id);
            foreach ($this->hubs as $hub) {
                $this->send_ping($hub, $permalink);
            }
        }
    }

    /**
     * Send ping to a specific hub
     */
    private function send_ping($hub_url, $topic_url) {
        $response = wp_remote_post($hub_url, array(
            'timeout' => 10,
            'body' => array(
                'hub.mode' => 'publish',
                'hub.url' => $topic_url,
            ),
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
        ));

        if (is_wp_error($response)) {
            $this->log_error('WebSub ping failed to ' . $hub_url . ': ' . $response->get_error_message());
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code >= 200 && $code < 300) {
            return true;
        }

        $this->log_error('WebSub ping returned ' . $code . ' from ' . $hub_url);
        return false;
    }

    /**
     * Ping Google's sitemap endpoint
     */
    public function ping_google_sitemap() {
        $sitemap_url = home_url('/sitemap-news.xml');
        $ping_url = 'https://www.google.com/ping?sitemap=' . urlencode($sitemap_url);

        $response = wp_remote_get($ping_url, array(
            'timeout' => 10,
        ));

        if (is_wp_error($response)) {
            $this->log_error('Google sitemap ping failed: ' . $response->get_error_message());
            return false;
        }

        return true;
    }

    /**
     * Ping IndexNow (Bing, Yandex, etc.)
     */
    private function ping_indexnow($post_id) {
        $permalink = get_permalink($post_id);
        $host = wp_parse_url(home_url(), PHP_URL_HOST);

        // IndexNow API key (stored in options or generated)
        $api_key = get_option('sffc_indexnow_key');
        if (!$api_key) {
            $api_key = wp_generate_uuid4();
            update_option('sffc_indexnow_key', $api_key);
            // Create the key file for verification
            $this->create_indexnow_key_file($api_key);
        }

        // Ping IndexNow endpoints
        $indexnow_endpoints = array(
            'https://api.indexnow.org/indexnow',
            'https://www.bing.com/indexnow',
        );

        foreach ($indexnow_endpoints as $endpoint) {
            $response = wp_remote_post($endpoint, array(
                'timeout' => 10,
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
                'body' => wp_json_encode(array(
                    'host' => $host,
                    'key' => $api_key,
                    'keyLocation' => home_url('/' . $api_key . '.txt'),
                    'urlList' => array($permalink),
                )),
            ));

            if (is_wp_error($response)) {
                $this->log_error('IndexNow ping failed to ' . $endpoint . ': ' . $response->get_error_message());
            }
        }
    }

    /**
     * Create IndexNow key file for verification
     */
    private function create_indexnow_key_file($api_key) {
        $file_path = ABSPATH . $api_key . '.txt';
        if (!file_exists($file_path)) {
            file_put_contents($file_path, $api_key);
        }
    }

    /**
     * Scheduled sitemap ping
     */
    public function ping_sitemap() {
        $this->ping_google_sitemap();

        // Also ping main sitemap
        $main_sitemap = home_url('/sitemap.xml');
        $ping_url = 'https://www.google.com/ping?sitemap=' . urlencode($main_sitemap);
        wp_remote_get($ping_url, array('timeout' => 10));
    }

    /**
     * Add WebSub hub links to RSS/Atom feeds
     */
    public function add_hub_links_to_feed() {
        foreach ($this->hubs as $hub) {
            echo '<atom:link rel="hub" href="' . esc_url($hub) . '" />' . "\n";
        }
        echo '<atom:link rel="self" href="' . esc_url($this->get_current_feed_url()) . '" />' . "\n";
    }

    /**
     * Add WebSub discovery links to HTML head
     */
    public function add_hub_links_to_head() {
        // Only on singular posts
        if (!is_singular($this->post_types)) {
            return;
        }

        echo "\n<!-- WebSub Discovery Links -->\n";
        foreach ($this->hubs as $hub) {
            echo '<link rel="hub" href="' . esc_url($hub) . '">' . "\n";
        }
        echo '<link rel="self" href="' . esc_url(get_permalink()) . '">' . "\n";
        echo "<!-- End WebSub Discovery -->\n\n";
    }

    /**
     * Register REST routes for hub verification
     */
    public function register_rest_routes() {
        register_rest_route('sffc/v1', '/websub', array(
            'methods' => array('GET', 'POST'),
            'callback' => array($this, 'handle_hub_callback'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Handle hub verification callbacks
     */
    public function handle_hub_callback($request) {
        $params = $request->get_params();

        // Handle verification request (GET)
        if ($request->get_method() === 'GET' && isset($params['hub_challenge'])) {
            return new WP_REST_Response($params['hub_challenge'], 200);
        }

        // Handle content distribution (POST)
        if ($request->get_method() === 'POST') {
            // Log received content notification
            $this->log_ping(0, 'received_notification');
            return new WP_REST_Response('OK', 200);
        }

        return new WP_REST_Response('Invalid request', 400);
    }

    /**
     * Get all feed URLs to ping
     */
    private function get_feed_urls() {
        return array(
            get_feed_link('rss2'),
            get_feed_link('atom'),
            home_url('/feed/'),
        );
    }

    /**
     * Get current feed URL
     */
    private function get_current_feed_url() {
        if (is_feed('atom')) {
            return get_feed_link('atom');
        }
        return get_feed_link('rss2');
    }

    /**
     * Log ping activity
     */
    private function log_ping($post_id, $type) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $log_entry = sprintf(
            '[%s] WebSub %s ping for post %d',
            current_time('Y-m-d H:i:s'),
            $type,
            $post_id
        );

        error_log($log_entry);
    }

    /**
     * Log errors
     */
    private function log_error($message) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        error_log('[WebSub Error] ' . $message);
    }

    /**
     * Manual ping trigger (for admin use)
     */
    public function manual_ping($post_id = null) {
        if ($post_id) {
            $this->ping_hubs($post_id);
            $this->ping_indexnow($post_id);
        } else {
            $this->ping_hubs();
        }
        $this->ping_google_sitemap();

        return true;
    }

    /**
     * Get ping statistics
     */
    public function get_stats() {
        global $wpdb;

        $stats = array(
            'total_posts' => 0,
            'pinged_posts' => 0,
            'last_ping' => null,
        );

        // Count total published posts
        $stats['total_posts'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN (%s, %s, %s) AND post_status = 'publish'",
            'post',
            'sffc_pe_news',
            'sffc_pe_deal'
        ));

        // Count pinged posts
        $stats['pinged_posts'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_websub_last_ping'"
        );

        // Get last ping time
        $last_ping = $wpdb->get_var(
            "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_websub_last_ping' ORDER BY meta_value DESC LIMIT 1"
        );

        if ($last_ping) {
            $stats['last_ping'] = date('Y-m-d H:i:s', $last_ping);
        }

        return $stats;
    }
}

// Initialize the class
add_action('plugins_loaded', function() {
    SFFC_WebSub_Publisher::get_instance();
});

/**
 * Helper function to manually trigger a ping
 */
function sffc_websub_ping($post_id = null) {
    $publisher = SFFC_WebSub_Publisher::get_instance();
    return $publisher->manual_ping($post_id);
}

/**
 * Helper function to get ping statistics
 */
function sffc_websub_stats() {
    $publisher = SFFC_WebSub_Publisher::get_instance();
    return $publisher->get_stats();
}
