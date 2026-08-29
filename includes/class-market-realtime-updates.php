<?php
/**
 * Market Real-time Updates System
 * Handles WebSocket connections and real-time market data streaming
 * 
 * @package SennaCareers
 * @subpackage Market
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Market_Realtime_Updates {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Update channels
     */
    private $channels = array();
    
    /**
     * Active connections
     */
    private $connections = array();
    
    /**
     * Update queue
     */
    private $update_queue = array();
    
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
        $this->init_channels();
        $this->init_hooks();
        $this->init_heartbeat();
    }
    
    /**
     * Initialize update channels
     */
    private function init_channels() {
        $this->channels = array(
            'market_prices' => array(
                'interval' => 5000, // 5 seconds
                'priority' => 'high',
                'data_source' => 'price_feed'
            ),
            'market_news' => array(
                'interval' => 60000, // 1 minute
                'priority' => 'medium',
                'data_source' => 'news_feed'
            ),
            'market_analysis' => array(
                'interval' => 300000, // 5 minutes
                'priority' => 'low',
                'data_source' => 'analysis_engine'
            ),
            'market_alerts' => array(
                'interval' => 'instant', // Push immediately
                'priority' => 'critical',
                'data_source' => 'alert_system'
            )
        );
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // WebSocket fallback via AJAX polling
        add_action('wp_ajax_sffc_market_poll', array($this, 'handle_poll_request'));
        add_action('wp_ajax_nopriv_sffc_market_poll', array($this, 'handle_poll_request'));
        
        // Server-sent events endpoint
        add_action('wp_ajax_sffc_market_stream', array($this, 'handle_stream_request'));
        add_action('wp_ajax_nopriv_sffc_market_stream', array($this, 'handle_stream_request'));
        
        // Subscribe/unsubscribe to channels
        add_action('wp_ajax_sffc_market_subscribe', array($this, 'handle_subscribe'));
        add_action('wp_ajax_nopriv_sffc_market_subscribe', array($this, 'handle_subscribe'));
        
        // Market event triggers
        add_action('sffc_market_event', array($this, 'handle_market_event'), 10, 2);
        add_action('sffc_price_update', array($this, 'handle_price_update'), 10, 1);
        add_action('sffc_news_update', array($this, 'handle_news_update'), 10, 1);
    }
    
    /**
     * Initialize heartbeat for connection management
     */
    private function init_heartbeat() {
        // WordPress Heartbeat API integration
        add_filter('heartbeat_received', array($this, 'heartbeat_received'), 10, 2);
        add_filter('heartbeat_settings', array($this, 'heartbeat_settings'));
    }
    
    /**
     * Handle polling request (WebSocket fallback)
     */
    public function handle_poll_request() {
        check_ajax_referer('sffc_frontend_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $last_update = isset($_POST['last_update']) ? intval($_POST['last_update']) : 0;
        $channels = isset($_POST['channels']) ? array_map('sanitize_text_field', $_POST['channels']) : array('market_news');
        
        // Get updates since last poll
        $updates = $this->get_updates_since($last_update, $channels);
        
        // Check for critical alerts
        $alerts = $this->check_critical_alerts($user_id);
        
        if (!empty($alerts)) {
            $updates['alerts'] = $alerts;
        }
        
        // Add timestamp
        $updates['timestamp'] = time();
        $updates['next_poll'] = $this->calculate_next_poll($channels);
        
        wp_send_json_success($updates);
    }
    
    /**
     * Handle server-sent events stream
     */
    public function handle_stream_request() {
        // Verify nonce
        check_ajax_referer('sffc_frontend_nonce', 'nonce');
        
        // Set headers for SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Disable Nginx buffering
        
        // Initialize connection
        $connection_id = $this->register_connection();
        
        // Send initial connection event
        $this->send_sse_message('connected', array(
            'connection_id' => $connection_id,
            'channels' => array_keys($this->channels)
        ));
        
        // Stream loop
        $last_update = time();
        $iteration = 0;
        
        while (true) {
            // Check for updates every second
            $updates = $this->get_pending_updates($connection_id);
            
            if (!empty($updates)) {
                foreach ($updates as $update) {
                    $this->send_sse_message($update['type'], $update['data']);
                }
                $last_update = time();
            }
            
            // Send heartbeat every 30 seconds
            if ($iteration % 30 == 0) {
                $this->send_sse_message('heartbeat', array('time' => time()));
            }
            
            // Clean buffer and send
            ob_flush();
            flush();
            
            // Check connection
            if (connection_aborted()) {
                $this->unregister_connection($connection_id);
                break;
            }
            
            // Sleep for 1 second
            sleep(1);
            $iteration++;
            
            // Timeout after 5 minutes (reconnect mechanism)
            if ($iteration > 300) {
                $this->send_sse_message('reconnect', array('reason' => 'timeout'));
                break;
            }
        }
        
        exit;
    }
    
    /**
     * Send SSE message
     */
    private function send_sse_message($event, $data) {
        echo "event: $event\n";
        echo "data: " . json_encode($data) . "\n\n";
    }
    
    /**
     * Handle channel subscription
     */
    public function handle_subscribe() {
        check_ajax_referer('sffc_frontend_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $channels = isset($_POST['channels']) ? array_map('sanitize_text_field', $_POST['channels']) : array();
        $action = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : 'subscribe';
        
        if ($action === 'subscribe') {
            $this->subscribe_to_channels($user_id, $channels);
            $message = 'Subscribed to channels: ' . implode(', ', $channels);
        } else {
            $this->unsubscribe_from_channels($user_id, $channels);
            $message = 'Unsubscribed from channels: ' . implode(', ', $channels);
        }
        
        wp_send_json_success(array(
            'message' => $message,
            'active_channels' => $this->get_user_channels($user_id)
        ));
    }
    
    /**
     * Handle market event
     */
    public function handle_market_event($event_type, $event_data) {
        // Determine affected channels
        $affected_channels = $this->determine_affected_channels($event_type);
        
        // Queue update for each channel
        foreach ($affected_channels as $channel) {
            $this->queue_update($channel, array(
                'event_type' => $event_type,
                'data' => $event_data,
                'timestamp' => time(),
                'priority' => $this->channels[$channel]['priority']
            ));
        }
        
        // Process high-priority updates immediately
        $this->process_priority_queue();
    }
    
    /**
     * Handle price update
     */
    public function handle_price_update($price_data) {
        // Format price update
        $update = array(
            'type' => 'price',
            'symbols' => $price_data['symbols'],
            'changes' => $price_data['changes'],
            'timestamp' => time()
        );
        
        // Queue for price channel
        $this->queue_update('market_prices', $update);
        
        // Check for significant moves (alerts)
        $this->check_price_alerts($price_data);
    }
    
    /**
     * Handle news update
     */
    public function handle_news_update($news_data) {
        // Format news update
        $update = array(
            'type' => 'news',
            'headlines' => array_slice($news_data['items'], 0, 5),
            'breaking' => $news_data['breaking'] ?? false,
            'timestamp' => time()
        );
        
        // Queue for news channel
        $this->queue_update('market_news', $update);
        
        // If breaking news, also send as alert
        if ($update['breaking']) {
            $this->queue_update('market_alerts', array(
                'type' => 'breaking_news',
                'headline' => $news_data['items'][0]['title'],
                'impact' => 'high'
            ));
        }
    }
    
    /**
     * WordPress Heartbeat integration
     */
    public function heartbeat_received($response, $data) {
        // Add market updates to heartbeat response
        if (isset($data['sffc_market_check'])) {
            $response['sffc_market_updates'] = $this->get_latest_updates();
        }
        
        return $response;
    }
    
    public function heartbeat_settings($settings) {
        // Adjust heartbeat interval for market mode
        if ($this->is_market_mode_active()) {
            $settings['interval'] = 15; // 15 seconds in market mode
        }
        
        return $settings;
    }
    
    /**
     * Get updates since timestamp
     */
    private function get_updates_since($timestamp, $channels) {
        global $wpdb;
        
        $updates = array();
        
        // Get from cache first
        foreach ($channels as $channel) {
            $cache_key = 'sffc_channel_' . $channel;
            $channel_data = get_transient($cache_key);
            
            if ($channel_data && $channel_data['timestamp'] > $timestamp) {
                $updates[$channel] = $channel_data['data'];
            }
        }
        
        // Check database for persistent updates
        $table_name = $wpdb->prefix . 'sffc_market_cache';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM $table_name 
                     WHERE data_type IN (" . implode(',', array_fill(0, count($channels), '%s')) . ")
                     AND created_at > FROM_UNIXTIME(%d)
                     ORDER BY created_at DESC
                     LIMIT 10",
                    array_merge($channels, array($timestamp))
                )
            );
            
            foreach ($results as $result) {
                if (!isset($updates[$result->data_type])) {
                    $updates[$result->data_type] = json_decode($result->content, true);
                }
            }
        }
        
        return $updates;
    }
    
    /**
     * Check for critical alerts
     */
    private function check_critical_alerts($user_id) {
        $alerts = array();
        
        // Check user-specific alert conditions
        $user_alerts = get_user_meta($user_id, 'sffc_market_alerts', true);
        
        if (!empty($user_alerts)) {
            foreach ($user_alerts as $alert_config) {
                if ($this->evaluate_alert_condition($alert_config)) {
                    $alerts[] = array(
                        'type' => $alert_config['type'],
                        'message' => $this->format_alert_message($alert_config),
                        'severity' => $alert_config['severity'] ?? 'medium',
                        'timestamp' => time()
                    );
                }
            }
        }
        
        // Check system-wide alerts
        $system_alerts = get_transient('sffc_system_market_alerts');
        if ($system_alerts) {
            $alerts = array_merge($alerts, $system_alerts);
        }
        
        return $alerts;
    }
    
    /**
     * Queue update for processing
     */
    private function queue_update($channel, $update) {
        if (!isset($this->update_queue[$channel])) {
            $this->update_queue[$channel] = array();
        }
        
        $this->update_queue[$channel][] = $update;
        
        // Limit queue size
        if (count($this->update_queue[$channel]) > 100) {
            array_shift($this->update_queue[$channel]);
        }
        
        // Store in transient for persistence
        set_transient(
            'sffc_channel_' . $channel,
            array(
                'data' => $update,
                'timestamp' => time()
            ),
            300 // 5 minutes
        );
    }
    
    /**
     * Process priority queue
     */
    private function process_priority_queue() {
        foreach ($this->update_queue as $channel => $updates) {
            if ($this->channels[$channel]['priority'] === 'critical' || 
                $this->channels[$channel]['priority'] === 'high') {
                
                // Process immediately
                foreach ($updates as $update) {
                    $this->broadcast_update($channel, $update);
                }
                
                // Clear processed updates
                $this->update_queue[$channel] = array();
            }
        }
    }
    
    /**
     * Broadcast update to connected clients
     */
    private function broadcast_update($channel, $update) {
        // In a real implementation, this would push to WebSocket server
        // For now, we'll use WordPress transients as a message queue
        
        $broadcast_queue = get_transient('sffc_broadcast_queue') ?: array();
        
        $broadcast_queue[] = array(
            'channel' => $channel,
            'update' => $update,
            'timestamp' => time()
        );
        
        // Keep only last 50 broadcasts
        if (count($broadcast_queue) > 50) {
            $broadcast_queue = array_slice($broadcast_queue, -50);
        }
        
        set_transient('sffc_broadcast_queue', $broadcast_queue, 60);
        
        // Trigger action for other systems to hook into
        do_action('sffc_market_broadcast', $channel, $update);
    }
    
    /**
     * Connection management
     */
    private function register_connection() {
        $connection_id = wp_generate_uuid4();
        
        $this->connections[$connection_id] = array(
            'user_id' => get_current_user_id(),
            'started' => time(),
            'last_activity' => time(),
            'channels' => array()
        );
        
        // Store in database for persistence
        update_user_meta(
            get_current_user_id(),
            'sffc_market_connection',
            $connection_id
        );
        
        return $connection_id;
    }
    
    private function unregister_connection($connection_id) {
        if (isset($this->connections[$connection_id])) {
            $user_id = $this->connections[$connection_id]['user_id'];
            unset($this->connections[$connection_id]);
            
            // Clean up user meta
            delete_user_meta($user_id, 'sffc_market_connection');
        }
    }
    
    /**
     * Helper methods
     */
    private function calculate_next_poll($channels) {
        $min_interval = PHP_INT_MAX;
        
        foreach ($channels as $channel) {
            if (isset($this->channels[$channel])) {
                $interval = $this->channels[$channel]['interval'];
                if (is_numeric($interval) && $interval < $min_interval) {
                    $min_interval = $interval;
                }
            }
        }
        
        // Default to 60 seconds if no valid interval found
        return $min_interval === PHP_INT_MAX ? 60000 : $min_interval;
    }
    
    private function is_market_mode_active() {
        // Check if current user has market mode active
        $user_id = get_current_user_id();
        if ($user_id) {
            $user_mode = get_user_meta($user_id, 'sffc_active_mode', true);
            return $user_mode === 'market';
        }
        
        return false;
    }
    
    private function get_latest_updates() {
        // Get most recent updates from all channels
        $latest = array();
        
        foreach ($this->channels as $channel => $config) {
            $cache_key = 'sffc_channel_' . $channel;
            $data = get_transient($cache_key);
            
            if ($data) {
                $latest[$channel] = $data;
            }
        }
        
        return $latest;
    }
    
    /**
     * Alert evaluation
     */
    private function evaluate_alert_condition($alert_config) {
        // Simplified alert evaluation
        switch ($alert_config['type']) {
            case 'price_threshold':
                // Check if price crossed threshold
                return $this->check_price_threshold(
                    $alert_config['symbol'],
                    $alert_config['threshold'],
                    $alert_config['direction']
                );
                
            case 'volatility_spike':
                // Check if volatility exceeded limit
                return $this->check_volatility_spike($alert_config['level']);
                
            case 'news_keyword':
                // Check if news contains keyword
                return $this->check_news_keyword($alert_config['keyword']);
                
            default:
                return false;
        }
    }
    
    private function format_alert_message($alert_config) {
        $templates = array(
            'price_threshold' => '%s has crossed %s',
            'volatility_spike' => 'Market volatility has spiked to %s',
            'news_keyword' => 'Breaking: News about %s'
        );
        
        $template = $templates[$alert_config['type']] ?? 'Market alert triggered';
        
        return sprintf(
            $template,
            $alert_config['symbol'] ?? 'Market',
            $alert_config['threshold'] ?? 'threshold'
        );
    }
    
    // Placeholder methods for price/volatility checks
    private function check_price_threshold($symbol, $threshold, $direction) {
        // Would check actual price data
        return false;
    }
    
    private function check_volatility_spike($level) {
        // Would check actual volatility data
        return false;
    }
    
    private function check_news_keyword($keyword) {
        // Would check recent news
        return false;
    }
    
    private function check_price_alerts($price_data) {
        // Check for significant price movements
        foreach ($price_data['changes'] as $symbol => $change) {
            if (abs($change) > 5) { // 5% move
                $this->queue_update('market_alerts', array(
                    'type' => 'price_alert',
                    'symbol' => $symbol,
                    'change' => $change,
                    'message' => "$symbol moved " . ($change > 0 ? '+' : '') . "$change%"
                ));
            }
        }
    }
    
    /**
     * Channel subscription management
     */
    private function subscribe_to_channels($user_id, $channels) {
        $current = get_user_meta($user_id, 'sffc_subscribed_channels', true) ?: array();
        $updated = array_unique(array_merge($current, $channels));
        update_user_meta($user_id, 'sffc_subscribed_channels', $updated);
    }
    
    private function unsubscribe_from_channels($user_id, $channels) {
        $current = get_user_meta($user_id, 'sffc_subscribed_channels', true) ?: array();
        $updated = array_diff($current, $channels);
        update_user_meta($user_id, 'sffc_subscribed_channels', $updated);
    }
    
    private function get_user_channels($user_id) {
        return get_user_meta($user_id, 'sffc_subscribed_channels', true) ?: array('market_news');
    }
    
    private function determine_affected_channels($event_type) {
        // Map event types to channels
        $mapping = array(
            'price_change' => array('market_prices', 'market_alerts'),
            'news_break' => array('market_news', 'market_alerts'),
            'analysis_complete' => array('market_analysis'),
            'volatility_spike' => array('market_alerts', 'market_prices'),
            'market_open' => array('market_prices', 'market_news', 'market_analysis'),
            'market_close' => array('market_analysis')
        );
        
        return $mapping[$event_type] ?? array('market_news');
    }
    
    private function get_pending_updates($connection_id) {
        if (!isset($this->connections[$connection_id])) {
            return array();
        }
        
        $updates = array();
        $last_check = $this->connections[$connection_id]['last_activity'];
        
        // Get updates from broadcast queue
        $broadcast_queue = get_transient('sffc_broadcast_queue') ?: array();
        
        foreach ($broadcast_queue as $broadcast) {
            if ($broadcast['timestamp'] > $last_check) {
                $updates[] = array(
                    'type' => $broadcast['channel'],
                    'data' => $broadcast['update']
                );
            }
        }
        
        // Update last activity
        $this->connections[$connection_id]['last_activity'] = time();
        
        return $updates;
    }
}

// Initialize
add_action('init', function() {
    SFFC_Market_Realtime_Updates::get_instance();
});