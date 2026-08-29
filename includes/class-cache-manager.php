<?php
/**
 * Centralized Cache Manager
 * Coordinates all caching to prevent conflicts
 * 
 * @package SennaCareers
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Cache_Manager {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Cache prefix to avoid conflicts
     */
    const CACHE_PREFIX = 'sffc_';
    
    /**
     * Cache groups
     */
    const GROUP_API = 'sffc_api';
    const GROUP_MARKET = 'sffc_market';
    const GROUP_USER = 'sffc_user';
    const GROUP_VISUAL = 'sffc_visual';
    
    /**
     * Default cache expiration times (in seconds)
     */
    private $expiration_times = array(
        self::GROUP_API => 300,      // 5 minutes for API responses
        self::GROUP_MARKET => 60,     // 1 minute for market data
        self::GROUP_USER => 3600,     // 1 hour for user data
        self::GROUP_VISUAL => 1800    // 30 minutes for visual cards
    );
    
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
        // Clear cache on plugin update
        add_action('upgrader_process_complete', array($this, 'clear_all_cache'), 10, 2);
        
        // Add cache clearing to admin bar
        add_action('admin_bar_menu', array($this, 'add_cache_clear_button'), 100);
        
        // AJAX handler for cache clearing
        add_action('wp_ajax_sffc_clear_cache', array($this, 'ajax_clear_cache'));
    }
    
    /**
     * Get cached data
     * 
     * @param string $key Cache key
     * @param string $group Cache group
     * @return mixed|false Cached data or false if not found
     */
    public function get($key, $group = self::GROUP_API) {
        $cache_key = $this->generate_cache_key($key, $group);
        
        // Try object cache first (if available)
        $cached = wp_cache_get($cache_key, $group);
        if (false !== $cached) {
            $this->log_cache_hit($key, $group, 'object');
            return $cached;
        }
        
        // Fall back to transient
        $cached = get_transient($cache_key);
        if (false !== $cached) {
            $this->log_cache_hit($key, $group, 'transient');
            // Also set in object cache for next request
            wp_cache_set($cache_key, $cached, $group, 60);
            return $cached;
        }
        
        $this->log_cache_miss($key, $group);
        return false;
    }
    
    /**
     * Set cache data
     * 
     * @param string $key Cache key
     * @param mixed $data Data to cache
     * @param string $group Cache group
     * @param int $expiration Optional expiration time in seconds
     * @return bool Success
     */
    public function set($key, $data, $group = self::GROUP_API, $expiration = null) {
        $cache_key = $this->generate_cache_key($key, $group);
        
        if (null === $expiration) {
            $expiration = $this->expiration_times[$group] ?? 300;
        }
        
        // Set in both object cache and transient
        wp_cache_set($cache_key, $data, $group, $expiration);
        set_transient($cache_key, $data, $expiration);
        
        $this->log_cache_set($key, $group);
        
        return true;
    }
    
    /**
     * Delete cached data
     * 
     * @param string $key Cache key
     * @param string $group Cache group
     * @return bool Success
     */
    public function delete($key, $group = self::GROUP_API) {
        $cache_key = $this->generate_cache_key($key, $group);
        
        wp_cache_delete($cache_key, $group);
        delete_transient($cache_key);
        
        return true;
    }
    
    /**
     * Clear all cache for a specific group
     * 
     * @param string $group Cache group to clear
     * @return int Number of items cleared
     */
    public function clear_group($group) {
        global $wpdb;
        
        $prefix = self::CACHE_PREFIX . $group;
        
        // Clear transients
        $sql = $wpdb->prepare(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE %s 
             OR option_name LIKE %s",
            '_transient_' . $prefix . '%',
            '_transient_timeout_' . $prefix . '%'
        );
        
        $cleared = $wpdb->query($sql);
        
        // Clear object cache group
        wp_cache_flush_group($group);
        
        return $cleared;
    }
    
    /**
     * Clear all plugin cache
     * 
     * @return int Total items cleared
     */
    public function clear_all_cache() {
        $total = 0;
        
        foreach (array_keys($this->expiration_times) as $group) {
            $total += $this->clear_group($group);
        }
        
        do_action('sffc_cache_cleared');
        
        return $total;
    }
    
    /**
     * Generate standardized cache key
     * 
     * @param string $key Original key
     * @param string $group Cache group
     * @return string Standardized cache key
     */
    private function generate_cache_key($key, $group) {
        // Add user context for user-specific caches
        if ($group === self::GROUP_USER) {
            $user_id = get_current_user_id();
            $key = $key . '_u' . $user_id;
        }
        
        // Ensure key length is within limits
        $key = self::CACHE_PREFIX . $group . '_' . md5($key);
        
        return substr($key, 0, 172); // WordPress transient name limit
    }
    
    /**
     * Add cache clear button to admin bar
     * 
     * @param WP_Admin_Bar $wp_admin_bar
     */
    public function add_cache_clear_button($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $wp_admin_bar->add_node(array(
            'id' => 'sffc-clear-cache',
            'title' => 'Clear SFFC Cache',
            'href' => '#',
            'meta' => array(
                'onclick' => 'sffcClearCache(); return false;'
            )
        ));
        
        // Add inline script
        add_action('admin_footer', array($this, 'add_cache_clear_script'));
    }
    
    /**
     * Add cache clear JavaScript
     */
    public function add_cache_clear_script() {
        ?>
        <script>
        function sffcClearCache() {
            if (!confirm('Clear all senna Finance cache?')) {
                return;
            }
            
            jQuery.post(ajaxurl, {
                action: 'sffc_clear_cache',
                nonce: '<?php echo wp_create_nonce('sffc_clear_cache'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('Cache cleared successfully!');
                } else {
                    alert('Failed to clear cache.');
                }
            });
        }
        </script>
        <?php
    }
    
    /**
     * AJAX handler for cache clearing
     */
    public function ajax_clear_cache() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        if (!check_ajax_referer('sffc_clear_cache', 'nonce', false)) {
            wp_send_json_error('Security check failed');
        }
        
        $cleared = $this->clear_all_cache();
        
        wp_send_json_success(array(
            'message' => sprintf('Cleared %d cache items', $cleared)
        ));
    }
    
    /**
     * Cache performance monitoring
     */
    private $cache_stats = array(
        'hits' => 0,
        'misses' => 0,
        'sets' => 0
    );
    
    /**
     * Log cache hit
     */
    private function log_cache_hit($key, $group, $type) {
        $this->cache_stats['hits']++;
        
        if (!empty($_ENV['WP_DEBUG'])) {
            error_log("SFFC Cache HIT: {$group}/{$key} from {$type}");
        }
    }
    
    /**
     * Log cache miss
     */
    private function log_cache_miss($key, $group) {
        $this->cache_stats['misses']++;
        
        if (!empty($_ENV['WP_DEBUG'])) {
            error_log("SFFC Cache MISS: {$group}/{$key}");
        }
    }
    
    /**
     * Log cache set
     */
    private function log_cache_set($key, $group) {
        $this->cache_stats['sets']++;
        
        if (!empty($_ENV['WP_DEBUG'])) {
            error_log("SFFC Cache SET: {$group}/{$key}");
        }
    }
    
    /**
     * Get cache statistics
     * 
     * @return array Cache performance stats
     */
    public function get_stats() {
        $hit_rate = 0;
        $total = (int)$this->cache_stats['hits'] + (int)$this->cache_stats['misses'];
        if ($total > 0) {
            $hit_rate = (int)$this->cache_stats['hits'] / $total * 100;
        }
        
        return array(
            'hits' => $this->cache_stats['hits'],
            'misses' => $this->cache_stats['misses'],
            'sets' => $this->cache_stats['sets'],
            'hit_rate' => round($hit_rate, 2)
        );
    }
    
    /**
     * Warm cache with commonly used data
     */
    public function warm_cache() {
        // Warm market data cache
        if (class_exists('SFFC_Market_Intelligence_Service')) {
            $market_service = SFFC_Market_Intelligence_Service::get_instance();
            $headlines = $market_service->get_market_headlines();
            if ($headlines) {
                $this->set('market_headlines', $headlines, self::GROUP_MARKET);
            }
        }
        
        // Warm user preferences
        $user_id = get_current_user_id();
        if ($user_id) {
            $preferences = get_user_meta($user_id, 'sffc_preferences', true);
            if ($preferences) {
                $this->set('user_preferences_' . $user_id, $preferences, self::GROUP_USER);
            }
        }
    }
}