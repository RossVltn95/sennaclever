<?php
/**
 * Relocation Page Scheduler
 *
 * Handles scheduled generation of relocation pages with configurable daily limits.
 *
 * Strategy:
 * - Day 1: Generate all 60 popular routes (initial burst)
 * - Day 2+: Generate 100 pages/day (configurable)
 * - Priority: Popular routes → City-to-city → Country-to-country → Mixed
 *
 * @package SFFC_Careers
 * @since 11.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Relocation_Scheduler {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Option keys
     */
    const OPTION_DAILY_LIMIT = 'sffc_relocation_daily_limit';
    const OPTION_GENERATION_QUEUE = 'sffc_relocation_generation_queue';
    const OPTION_GENERATION_LOG = 'sffc_relocation_generation_log';
    const OPTION_LAST_RUN = 'sffc_relocation_last_run';
    const OPTION_INITIAL_BURST_DONE = 'sffc_relocation_initial_burst_done';
    const OPTION_SCHEDULER_ENABLED = 'sffc_relocation_scheduler_enabled';
    const OPTION_DELAY_BETWEEN_CALLS = 'sffc_relocation_api_delay';

    /**
     * Cron hook name
     */
    const CRON_HOOK = 'sffc_relocation_scheduled_generation';

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
        // Register cron schedules
        add_filter('cron_schedules', array($this, 'add_cron_schedules'));

        // Register cron hook
        add_action(self::CRON_HOOK, array($this, 'run_scheduled_generation'));

        // Admin hooks
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_sffc_trigger_generation', array($this, 'ajax_trigger_generation'));
        add_action('wp_ajax_sffc_reset_queue', array($this, 'ajax_reset_queue'));
        add_action('wp_ajax_sffc_pause_scheduler', array($this, 'ajax_pause_scheduler'));

        // Schedule cron on init (not admin_init) so it works for frontend cron too
        add_action('init', array($this, 'maybe_schedule_cron'));
    }

    /**
     * Add custom cron schedules
     */
    public function add_cron_schedules($schedules) {
        $schedules['twice_daily'] = array(
            'interval' => 12 * HOUR_IN_SECONDS,
            'display' => 'Twice Daily',
        );
        $schedules['four_times_daily'] = array(
            'interval' => 6 * HOUR_IN_SECONDS,
            'display' => 'Four Times Daily',
        );
        return $schedules;
    }

    /**
     * Schedule cron if not already scheduled
     */
    public function maybe_schedule_cron() {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'twice_daily', self::CRON_HOOK);
        }
    }

    /**
     * Register admin settings
     */
    public function register_settings() {
        register_setting('sffc_relocation_settings', self::OPTION_DAILY_LIMIT, array(
            'type' => 'integer',
            'default' => 100,
            'sanitize_callback' => 'absint',
        ));

        register_setting('sffc_relocation_settings', self::OPTION_SCHEDULER_ENABLED, array(
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => 'rest_sanitize_boolean',
        ));

        register_setting('sffc_relocation_settings', self::OPTION_DELAY_BETWEEN_CALLS, array(
            'type' => 'integer',
            'default' => 5,
            'sanitize_callback' => 'absint',
        ));

        register_setting('sffc_relocation_settings', 'sffc_relocation_test_mode', array(
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => 'rest_sanitize_boolean',
        ));
    }

    /**
     * Get daily generation limit
     */
    public function get_daily_limit() {
        return (int) get_option(self::OPTION_DAILY_LIMIT, 100);
    }

    /**
     * Check if scheduler is enabled
     */
    public function is_enabled() {
        return (bool) get_option(self::OPTION_SCHEDULER_ENABLED, true);
    }

    /**
     * Get delay between API calls (seconds)
     */
    public function get_api_delay() {
        return (int) get_option(self::OPTION_DELAY_BETWEEN_CALLS, 5);
    }

    /**
     * Check if initial burst is done
     */
    public function is_initial_burst_done() {
        return (bool) get_option(self::OPTION_INITIAL_BURST_DONE, false);
    }

    /**
     * Build the full generation queue with priorities
     */
    public function build_generation_queue() {
        $pages = SFFC_Relocation_Pages::get_instance();
        $popular_routes = $pages->get_popular_routes();
        $locations = $pages->get_all_locations();

        $queue = array();
        $existing_slugs = $this->get_existing_page_slugs();

        // Priority 1: Popular routes (pre-defined high-intent routes)
        foreach ($popular_routes as $route) {
            $slug = $route['from'] . '-to-' . $route['to'];
            if (!in_array($slug, $existing_slugs)) {
                $queue[] = array(
                    'from' => $route['from'],
                    'to' => $route['to'],
                    'priority' => 1,
                    'type' => 'popular',
                );
            }
        }

        // Priority 2: Major financial hub city-to-city routes
        $major_hubs = array('london', 'new-york', 'dubai', 'singapore', 'hong-kong', 'zurich', 'frankfurt', 'paris', 'tokyo', 'sydney');
        foreach ($major_hubs as $from_city) {
            foreach ($major_hubs as $to_city) {
                if ($from_city === $to_city) continue;
                $slug = $from_city . '-to-' . $to_city;
                if (!in_array($slug, $existing_slugs) && !$this->route_in_queue($queue, $from_city, $to_city)) {
                    $queue[] = array(
                        'from' => $from_city,
                        'to' => $to_city,
                        'priority' => 2,
                        'type' => 'major_hub',
                    );
                }
            }
        }

        // Priority 3: Country-to-country routes
        $country_slugs = array_keys($locations);
        foreach ($country_slugs as $from_country) {
            foreach ($country_slugs as $to_country) {
                if ($from_country === $to_country) continue;
                $slug = $from_country . '-to-' . $to_country;
                if (!in_array($slug, $existing_slugs) && !$this->route_in_queue($queue, $from_country, $to_country)) {
                    $queue[] = array(
                        'from' => $from_country,
                        'to' => $to_country,
                        'priority' => 3,
                        'type' => 'country',
                    );
                }
            }
        }

        // Priority 4: All city-to-city routes
        $all_cities = array();
        foreach ($locations as $country_slug => $country_data) {
            foreach ($country_data['cities'] as $city_slug => $city_data) {
                $all_cities[] = $city_slug;
            }
        }

        foreach ($all_cities as $from_city) {
            foreach ($all_cities as $to_city) {
                if ($from_city === $to_city) continue;
                $slug = $from_city . '-to-' . $to_city;
                if (!in_array($slug, $existing_slugs) && !$this->route_in_queue($queue, $from_city, $to_city)) {
                    $queue[] = array(
                        'from' => $from_city,
                        'to' => $to_city,
                        'priority' => 4,
                        'type' => 'city',
                    );
                }
            }
        }

        // Sort by priority
        usort($queue, function($a, $b) {
            return $a['priority'] - $b['priority'];
        });

        return $queue;
    }

    /**
     * Check if route is already in queue
     */
    private function route_in_queue($queue, $from, $to) {
        foreach ($queue as $item) {
            if ($item['from'] === $from && $item['to'] === $to) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get existing page slugs
     */
    private function get_existing_page_slugs() {
        $existing_pages = get_posts(array(
            'post_type' => 'sffc_relocation',
            'posts_per_page' => -1,
            'post_status' => array('publish', 'draft'),
            'fields' => 'ids',
        ));

        $slugs = array();
        foreach ($existing_pages as $page_id) {
            $post = get_post($page_id);
            if ($post) {
                $slugs[] = $post->post_name;
            }
        }

        return $slugs;
    }

    /**
     * Get or initialize the queue
     */
    public function get_queue() {
        $queue = get_option(self::OPTION_GENERATION_QUEUE, null);

        if ($queue === null) {
            $queue = $this->build_generation_queue();
            update_option(self::OPTION_GENERATION_QUEUE, $queue);
        }

        return $queue;
    }

    /**
     * Update queue (remove processed items)
     */
    public function update_queue($queue) {
        update_option(self::OPTION_GENERATION_QUEUE, $queue);
    }

    /**
     * Run scheduled generation
     */
    public function run_scheduled_generation() {
        if (!$this->is_enabled()) {
            $this->log('Scheduler is disabled, skipping run.');
            return;
        }

        // Check if already run today and hit limit
        $today = date('Y-m-d');
        $last_run = get_option(self::OPTION_LAST_RUN, array());
        $generated_today = isset($last_run['date']) && $last_run['date'] === $today
            ? (int) $last_run['count']
            : 0;

        // Determine limit for this run
        $daily_limit = $this->get_daily_limit();

        // Initial burst: generate all popular routes first
        if (!$this->is_initial_burst_done()) {
            $pages = SFFC_Relocation_Pages::get_instance();
            $popular_count = count($pages->get_popular_routes());
            $limit_this_run = max($popular_count, 60); // At least 60 for initial burst
            $this->log("Initial burst mode: generating up to {$limit_this_run} popular routes");
        } else {
            // Calculate remaining for today
            $remaining_today = $daily_limit - $generated_today;
            // Split across runs (assuming twice daily)
            $limit_this_run = ceil($remaining_today / 2);
        }

        if ($limit_this_run <= 0) {
            $this->log("Daily limit reached ({$daily_limit}), skipping until tomorrow.");
            return;
        }

        $queue = $this->get_queue();

        if (empty($queue)) {
            $this->log('Queue is empty, all pages generated!');
            return;
        }

        $this->log("Starting generation run: limit={$limit_this_run}, queue_size=" . count($queue));

        $pages_instance = SFFC_Relocation_Pages::get_instance();
        $delay = $this->get_api_delay();
        $generated = 0;
        $failed = 0;

        // Process queue
        $remaining_queue = array();
        foreach ($queue as $index => $route) {
            if ($generated >= $limit_this_run) {
                // Keep remaining items in queue
                $remaining_queue = array_slice($queue, $index);
                break;
            }

            $result = $this->generate_page($pages_instance, $route['from'], $route['to']);

            if ($result['success']) {
                $generated++;
                $this->log("Generated: {$route['from']} → {$route['to']} (Priority: {$route['priority']})");
            } else {
                $failed++;
                $this->log("Failed: {$route['from']} → {$route['to']} - {$result['error']}");
                // Add back to queue with retry flag (move to end)
                $route['retries'] = isset($route['retries']) ? $route['retries'] + 1 : 1;
                if ($route['retries'] < 3) {
                    $remaining_queue[] = $route;
                }
            }

            // Delay between API calls
            if ($delay > 0 && $generated < $limit_this_run) {
                sleep($delay);
            }
        }

        // Update queue with remaining items
        $this->update_queue($remaining_queue);

        // Update run stats
        $new_count = $generated_today + $generated;
        update_option(self::OPTION_LAST_RUN, array(
            'date' => $today,
            'count' => $new_count,
            'last_run_time' => current_time('mysql'),
            'last_run_generated' => $generated,
            'last_run_failed' => $failed,
        ));

        // Mark initial burst as done if we processed popular routes
        if (!$this->is_initial_burst_done() && $generated > 0) {
            // Check if all priority 1 routes are done
            $remaining_popular = array_filter($remaining_queue, function($r) {
                return $r['priority'] === 1;
            });
            if (empty($remaining_popular)) {
                update_option(self::OPTION_INITIAL_BURST_DONE, true);
                $this->log("Initial burst complete!");
            }
        }

        $this->log("Run complete: generated={$generated}, failed={$failed}, remaining=" . count($remaining_queue));
    }

    /**
     * Generate a single page
     */
    private function generate_page($pages_instance, $from, $to) {
        try {
            $post_id = $pages_instance->create_relocation_page($from, $to);

            if (is_wp_error($post_id)) {
                return array(
                    'success' => false,
                    'error' => $post_id->get_error_message(),
                );
            }

            return array(
                'success' => true,
                'post_id' => $post_id,
            );
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage(),
            );
        }
    }

    /**
     * Log generation activity
     */
    private function log($message) {
        $log = get_option(self::OPTION_GENERATION_LOG, array());

        // Keep last 500 entries
        if (count($log) > 500) {
            $log = array_slice($log, -400);
        }

        $log[] = array(
            'time' => current_time('mysql'),
            'message' => $message,
        );

        update_option(self::OPTION_GENERATION_LOG, $log);

        // Also log to debug if enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[SFFC Relocation Scheduler] ' . $message);
        }
    }

    /**
     * Get generation statistics
     */
    public function get_statistics() {
        $queue = $this->get_queue();
        $last_run = get_option(self::OPTION_LAST_RUN, array());
        $existing_slugs = $this->get_existing_page_slugs();

        $queue_by_priority = array(
            1 => 0,
            2 => 0,
            3 => 0,
            4 => 0,
        );

        foreach ($queue as $item) {
            $priority = $item['priority'] ?? 4;
            $queue_by_priority[$priority]++;
        }

        return array(
            'total_generated' => count($existing_slugs),
            'queue_remaining' => count($queue),
            'queue_by_priority' => $queue_by_priority,
            'daily_limit' => $this->get_daily_limit(),
            'generated_today' => isset($last_run['date']) && $last_run['date'] === date('Y-m-d')
                ? (int) $last_run['count']
                : 0,
            'last_run' => $last_run,
            'scheduler_enabled' => $this->is_enabled(),
            'initial_burst_done' => $this->is_initial_burst_done(),
            'next_scheduled' => wp_next_scheduled(self::CRON_HOOK),
        );
    }

    /**
     * Get generation log
     */
    public function get_log($limit = 50) {
        $log = get_option(self::OPTION_GENERATION_LOG, array());
        return array_slice(array_reverse($log), 0, $limit);
    }

    /**
     * AJAX: Trigger manual generation run
     */
    public function ajax_trigger_generation() {
        check_ajax_referer('sffc_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        // Run generation
        $this->run_scheduled_generation();

        wp_send_json_success(array(
            'message' => 'Generation run triggered',
            'stats' => $this->get_statistics(),
        ));
    }

    /**
     * AJAX: Reset queue
     */
    public function ajax_reset_queue() {
        check_ajax_referer('sffc_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        // Rebuild queue
        $queue = $this->build_generation_queue();
        update_option(self::OPTION_GENERATION_QUEUE, $queue);

        wp_send_json_success(array(
            'message' => 'Queue rebuilt with ' . count($queue) . ' routes',
            'stats' => $this->get_statistics(),
        ));
    }

    /**
     * AJAX: Pause/resume scheduler
     */
    public function ajax_pause_scheduler() {
        check_ajax_referer('sffc_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $current = $this->is_enabled();
        update_option(self::OPTION_SCHEDULER_ENABLED, !$current);

        wp_send_json_success(array(
            'message' => $current ? 'Scheduler paused' : 'Scheduler resumed',
            'enabled' => !$current,
        ));
    }

    /**
     * Unschedule cron on deactivation
     */
    public static function deactivate() {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }
}

// Initialize
add_action('plugins_loaded', function() {
    SFFC_Relocation_Scheduler::get_instance();
});

// Deactivation hook - only register if SFFC_PLUGIN_FILE is defined
if (defined('SFFC_PLUGIN_FILE')) {
    register_deactivation_hook(SFFC_PLUGIN_FILE, array('SFFC_Relocation_Scheduler', 'deactivate'));
}
