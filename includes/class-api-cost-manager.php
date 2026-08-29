<?php
/**
 * API Cost Manager
 *
 * Manages API call budgets, caching, and cost control for the
 * Dynamic Financial Intelligence System.
 *
 * @package SennaFinanceCareer
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_API_Cost_Manager {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * API cost constants (in cents)
     */
    const COST_CLASSIFICATION = 0.5;    // ~500 tokens
    const COST_FULL_GENERATION = 4.0;   // ~4000 tokens
    const DAILY_BUDGET_CENTS = 500;     // $5/day default
    const MONTHLY_BUDGET_CENTS = 10000; // $100/month default

    /**
     * Post creation limits
     */
    const DAILY_POST_LIMIT = 50;        // Max posts created per day (all CPTs combined)

    /**
     * Token limits
     */
    const MAX_CLASSIFICATION_TOKENS = 600;
    const MAX_GENERATION_TOKENS = 5000;

    /**
     * Cache durations
     */
    const CLASSIFICATION_CACHE_HOURS = 24;
    const GENERATION_CACHE_HOURS = 24;

    /**
     * Quality thresholds
     */
    const MIN_ARTICLE_LENGTH = 200;     // Minimum characters
    const MIN_WORD_COUNT = 50;          // Minimum words

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
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('admin_init', array($this, 'maybe_reset_daily_counters'));
    }

    /**
     * Check if classification should be skipped (content unchanged)
     *
     * @param int $post_id Post ID
     * @return bool True if classification is already cached and content unchanged
     */
    public function should_skip_classification($post_id) {
        // Get stored content hash
        $stored_hash = get_post_meta($post_id, '_sffc_content_hash', true);

        if (empty($stored_hash)) {
            return false;
        }

        // Get current content hash
        $post = get_post($post_id);
        $current_hash = $this->generate_content_hash($post->post_content, $post->post_title);

        // Check if classification exists and content unchanged
        $classification = get_post_meta($post_id, '_sffc_article_classification', true);

        if (!empty($classification) && $stored_hash === $current_hash) {
            return true; // Skip - use cached classification
        }

        return false;
    }

    /**
     * Generate content hash for change detection
     *
     * @param string $content Post content
     * @param string $title Post title
     * @return string MD5 hash
     */
    public function generate_content_hash($content, $title) {
        // Strip HTML and normalize whitespace
        $normalized = wp_strip_all_tags($content);
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        $normalized = trim($title . ' ' . $normalized);

        return md5($normalized);
    }

    /**
     * Store content hash after classification
     *
     * @param int $post_id Post ID
     */
    public function store_content_hash($post_id) {
        $post = get_post($post_id);
        $hash = $this->generate_content_hash($post->post_content, $post->post_title);
        update_post_meta($post_id, '_sffc_content_hash', $hash);
    }

    /**
     * Check if generation should be rate limited
     *
     * @param int $post_id Post ID
     * @return bool True if rate limited
     */
    public function is_generation_rate_limited($post_id) {
        $last_generated = get_post_meta($post_id, '_sffc_generation_timestamp', true);

        if (empty($last_generated)) {
            return false;
        }

        $last_time = strtotime($last_generated);
        $hours_since = (time() - $last_time) / 3600;

        // Allow one generation per 24 hours
        return $hours_since < self::GENERATION_CACHE_HOURS;
    }

    /**
     * Get time until next generation allowed
     *
     * @param int $post_id Post ID
     * @return int Hours remaining (0 if not limited)
     */
    public function get_generation_cooldown($post_id) {
        $last_generated = get_post_meta($post_id, '_sffc_generation_timestamp', true);

        if (empty($last_generated)) {
            return 0;
        }

        $last_time = strtotime($last_generated);
        $hours_since = (time() - $last_time) / 3600;
        $remaining = self::GENERATION_CACHE_HOURS - $hours_since;

        return max(0, ceil($remaining));
    }

    /**
     * Check if article meets quality threshold for generation
     *
     * @param int $post_id Post ID
     * @return array ['passes' => bool, 'reason' => string]
     */
    public function check_quality_threshold($post_id) {
        $post = get_post($post_id);

        if (!$post) {
            return array(
                'passes' => false,
                'reason' => 'Post not found'
            );
        }

        $content = wp_strip_all_tags($post->post_content);
        $char_count = strlen($content);
        $word_count = str_word_count($content);

        // Check minimum length
        if ($char_count < self::MIN_ARTICLE_LENGTH) {
            return array(
                'passes' => false,
                'reason' => 'Article too short for analysis (minimum ' . self::MIN_ARTICLE_LENGTH . ' characters)',
                'char_count' => $char_count,
                'required' => self::MIN_ARTICLE_LENGTH
            );
        }

        // Check minimum word count
        if ($word_count < self::MIN_WORD_COUNT) {
            return array(
                'passes' => false,
                'reason' => 'Article too short for analysis (minimum ' . self::MIN_WORD_COUNT . ' words)',
                'word_count' => $word_count,
                'required' => self::MIN_WORD_COUNT
            );
        }

        return array(
            'passes' => true,
            'reason' => 'OK',
            'char_count' => $char_count,
            'word_count' => $word_count
        );
    }

    /**
     * Check if within daily API budget
     *
     * @param string $operation_type 'classification' or 'generation'
     * @return bool True if within budget
     */
    public function is_within_daily_budget($operation_type = 'generation') {
        $today = date('Y-m-d');
        $daily_spent = (float) get_option('sffc_daily_api_cost_' . $today, 0);
        $daily_budget = (float) get_option('sffc_daily_api_budget', self::DAILY_BUDGET_CENTS);

        $operation_cost = ($operation_type === 'classification')
            ? self::COST_CLASSIFICATION
            : self::COST_FULL_GENERATION;

        return ($daily_spent + $operation_cost) <= $daily_budget;
    }

    /**
     * Check if within monthly API budget
     *
     * @param string $operation_type 'classification' or 'generation'
     * @return bool True if within budget
     */
    public function is_within_monthly_budget($operation_type = 'generation') {
        $month = date('Y-m');
        $monthly_spent = (float) get_option('sffc_monthly_api_cost_' . $month, 0);
        $monthly_budget = (float) get_option('sffc_monthly_api_budget', self::MONTHLY_BUDGET_CENTS);

        $operation_cost = ($operation_type === 'classification')
            ? self::COST_CLASSIFICATION
            : self::COST_FULL_GENERATION;

        return ($monthly_spent + $operation_cost) <= $monthly_budget;
    }

    /**
     * Check if within daily post creation limit
     *
     * @return bool True if within limit
     */
    public function is_within_daily_post_limit() {
        $today = date('Y-m-d');
        $posts_created = (int) get_option('sffc_daily_posts_created_' . $today, 0);
        $daily_limit = (int) get_option('sffc_daily_post_limit', self::DAILY_POST_LIMIT);

        return $posts_created < $daily_limit;
    }

    /**
     * Get remaining posts allowed today
     *
     * @return int Remaining posts
     */
    public function get_remaining_daily_posts() {
        $today = date('Y-m-d');
        $posts_created = (int) get_option('sffc_daily_posts_created_' . $today, 0);
        $daily_limit = (int) get_option('sffc_daily_post_limit', self::DAILY_POST_LIMIT);

        return max(0, $daily_limit - $posts_created);
    }

    /**
     * Increment daily post counter
     *
     * @param string $post_type The post type being created
     * @return int New count
     */
    public function increment_daily_post_count($post_type = '') {
        $today = date('Y-m-d');
        $key = 'sffc_daily_posts_created_' . $today;
        $count = (int) get_option($key, 0);
        $new_count = $count + 1;

        update_option($key, $new_count, false);

        // Also track by post type for granular reporting
        if (!empty($post_type)) {
            $type_key = 'sffc_daily_posts_' . $post_type . '_' . $today;
            $type_count = (int) get_option($type_key, 0);
            update_option($type_key, $type_count + 1, false);
        }

        // Log if debug enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $daily_limit = (int) get_option('sffc_daily_post_limit', self::DAILY_POST_LIMIT);
            error_log(sprintf(
                '[SFFC Post Limit] Created: %d/%d posts today (type: %s)',
                $new_count,
                $daily_limit,
                $post_type ?: 'unknown'
            ));
        }

        return $new_count;
    }

    /**
     * Set daily post limit
     *
     * @param int $limit New limit
     */
    public function set_daily_post_limit($limit) {
        update_option('sffc_daily_post_limit', max(1, (int) $limit));
    }

    /**
     * Record API cost
     *
     * @param string $operation_type 'classification' or 'generation'
     * @param int $post_id Post ID (for tracking)
     * @param int $actual_tokens Actual tokens used (optional)
     */
    public function record_api_cost($operation_type, $post_id = 0, $actual_tokens = 0) {
        $today = date('Y-m-d');
        $month = date('Y-m');

        // Calculate cost
        if ($actual_tokens > 0) {
            // Estimate based on actual tokens (~$0.01 per 1000 tokens for Claude)
            $cost = ($actual_tokens / 1000) * 1.0; // 1 cent per 1000 tokens
        } else {
            $cost = ($operation_type === 'classification')
                ? self::COST_CLASSIFICATION
                : self::COST_FULL_GENERATION;
        }

        // Update daily total
        $daily_key = 'sffc_daily_api_cost_' . $today;
        $daily_spent = (float) get_option($daily_key, 0);
        update_option($daily_key, $daily_spent + $cost, false);

        // Update monthly total
        $monthly_key = 'sffc_monthly_api_cost_' . $month;
        $monthly_spent = (float) get_option($monthly_key, 0);
        update_option($monthly_key, $monthly_spent + $cost, false);

        // Update operation counters
        $daily_ops_key = 'sffc_daily_' . $operation_type . '_count_' . $today;
        $ops_count = (int) get_option($daily_ops_key, 0);
        update_option($daily_ops_key, $ops_count + 1, false);

        // Log for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[SFFC API Cost] Operation: %s, Post: %d, Cost: $%.4f, Daily: $%.4f, Monthly: $%.4f',
                $operation_type,
                $post_id,
                $cost / 100,
                ($daily_spent + $cost) / 100,
                ($monthly_spent + $cost) / 100
            ));
        }
    }

    /**
     * Get current budget status
     *
     * @return array Budget information
     */
    public function get_budget_status() {
        $today = date('Y-m-d');
        $month = date('Y-m');

        $daily_spent = (float) get_option('sffc_daily_api_cost_' . $today, 0);
        $daily_budget = (float) get_option('sffc_daily_api_budget', self::DAILY_BUDGET_CENTS);

        $monthly_spent = (float) get_option('sffc_monthly_api_cost_' . $month, 0);
        $monthly_budget = (float) get_option('sffc_monthly_api_budget', self::MONTHLY_BUDGET_CENTS);

        $posts_created = (int) get_option('sffc_daily_posts_created_' . $today, 0);
        $daily_post_limit = (int) get_option('sffc_daily_post_limit', self::DAILY_POST_LIMIT);

        return array(
            'daily' => array(
                'spent' => $daily_spent / 100,           // In dollars
                'budget' => $daily_budget / 100,         // In dollars
                'remaining' => ($daily_budget - $daily_spent) / 100,
                'percentage_used' => ($daily_budget > 0) ? round(($daily_spent / $daily_budget) * 100, 1) : 0
            ),
            'monthly' => array(
                'spent' => $monthly_spent / 100,
                'budget' => $monthly_budget / 100,
                'remaining' => ($monthly_budget - $monthly_spent) / 100,
                'percentage_used' => ($monthly_budget > 0) ? round(($monthly_spent / $monthly_budget) * 100, 1) : 0
            ),
            'post_limit' => array(
                'created' => $posts_created,
                'limit' => $daily_post_limit,
                'remaining' => max(0, $daily_post_limit - $posts_created),
                'percentage_used' => ($daily_post_limit > 0) ? round(($posts_created / $daily_post_limit) * 100, 1) : 0
            ),
            'operations_today' => array(
                'classifications' => (int) get_option('sffc_daily_classification_count_' . $today, 0),
                'generations' => (int) get_option('sffc_daily_generation_count_' . $today, 0)
            )
        );
    }

    /**
     * Reset daily counters (called via admin_init)
     */
    public function maybe_reset_daily_counters() {
        $today = date('Y-m-d');
        $last_reset = get_option('sffc_last_daily_reset', '');

        if ($last_reset !== $today) {
            // Clean up old daily options (older than 7 days)
            $this->cleanup_old_daily_options();
            update_option('sffc_last_daily_reset', $today, false);
        }
    }

    /**
     * Clean up old daily tracking options
     */
    private function cleanup_old_daily_options() {
        global $wpdb;

        $week_ago = date('Y-m-d', strtotime('-7 days'));

        // Find and delete old daily options
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE 'sffc_daily_%%'
             AND option_name REGEXP '[0-9]{4}-[0-9]{2}-[0-9]{2}'
             AND option_name < %s",
            'sffc_daily_' . $week_ago
        ));
    }

    /**
     * Can perform operation (checks all limits)
     *
     * @param string $operation_type 'classification' or 'generation'
     * @param int $post_id Post ID
     * @param int $user_id User ID
     * @return array ['allowed' => bool, 'reason' => string]
     */
    public function can_perform_operation($operation_type, $post_id, $user_id = 0) {
        // Check quality threshold
        if ($operation_type === 'generation') {
            $quality = $this->check_quality_threshold($post_id);
            if (!$quality['passes']) {
                return array(
                    'allowed' => false,
                    'reason' => $quality['reason'],
                    'code' => 'quality_threshold'
                );
            }
        }

        // Check classification caching
        if ($operation_type === 'classification' && $this->should_skip_classification($post_id)) {
            return array(
                'allowed' => false,
                'reason' => 'Classification already cached and content unchanged',
                'code' => 'cached',
                'use_cached' => true
            );
        }

        // Check generation rate limit
        if ($operation_type === 'generation' && $this->is_generation_rate_limited($post_id)) {
            $cooldown = $this->get_generation_cooldown($post_id);
            return array(
                'allowed' => false,
                'reason' => "Analysis was recently generated. Try again in {$cooldown} hours.",
                'code' => 'rate_limited',
                'cooldown_hours' => $cooldown
            );
        }

        // Check user generation limits
        if ($operation_type === 'generation' && $user_id > 0) {
            $schema = SFFC_Intelligence_Schema::get_instance();
            if ($schema->is_generation_limit_reached($user_id)) {
                return array(
                    'allowed' => false,
                    'reason' => 'Monthly generation limit reached. Upgrade to premium for unlimited access.',
                    'code' => 'user_limit_reached'
                );
            }
        }

        // Check daily budget
        if (!$this->is_within_daily_budget($operation_type)) {
            return array(
                'allowed' => false,
                'reason' => 'Daily API budget exceeded. Please try again tomorrow.',
                'code' => 'daily_budget_exceeded'
            );
        }

        // Check monthly budget
        if (!$this->is_within_monthly_budget($operation_type)) {
            return array(
                'allowed' => false,
                'reason' => 'Monthly API budget exceeded.',
                'code' => 'monthly_budget_exceeded'
            );
        }

        return array(
            'allowed' => true,
            'reason' => 'OK',
            'code' => 'allowed'
        );
    }

    /**
     * Get template fallback when API fails
     *
     * @param int $post_id Post ID
     * @param string $model_type Model type
     * @return array Fallback content
     */
    public function get_template_fallback($post_id, $model_type) {
        $schema = SFFC_Intelligence_Schema::get_instance();

        // Get sector from classification if available
        $classification = get_post_meta($post_id, '_sffc_article_classification', true);
        $sector = !empty($classification['sector']) ? $classification['sector'] : 'private_equity';

        // Get benchmarks for the sector
        $benchmarks = $schema->get_sector_benchmarks($sector);

        // Build fallback response
        $fallback = array(
            'status' => 'fallback',
            'message' => 'Using template defaults. Personalized analysis temporarily unavailable.',
            'model_type' => $model_type,
            'sector' => $sector,
            'benchmarks' => $benchmarks,
            'use_defaults' => true
        );

        // Add model-specific defaults
        switch ($model_type) {
            case 'lbo':
                $fallback['inputs'] = array(
                    'entry_multiple' => $benchmarks['entry_multiple']['median'] ?? 11.0,
                    'exit_multiple' => $benchmarks['exit_multiple']['median'] ?? 12.0,
                    'leverage' => $benchmarks['leverage']['median'] ?? 5.5,
                    'hold_period' => 5
                );
                break;

            case 'merger':
            case 'ma':
                $ma_benchmarks = $schema->get_ma_benchmarks();
                $fallback['inputs'] = array(
                    'control_premium' => $ma_benchmarks['control_premium']['median'] ?? 30.0,
                    'synergy_value' => $ma_benchmarks['synergy_value']['median'] ?? 7.0,
                    'cash_consideration' => $ma_benchmarks['cash_consideration']['median'] ?? 60.0
                );
                break;

            case 'commercial-re':
            case 'cre':
                $cre_benchmarks = $schema->get_cre_benchmarks('office');
                $fallback['inputs'] = array(
                    'cap_rate' => $cre_benchmarks['cap_rate']['median'] ?? 6.5,
                    'occupancy' => $cre_benchmarks['occupancy']['median'] ?? 88.0,
                    'rent_psf' => $cre_benchmarks['rent_psf']['median'] ?? 55.0
                );
                break;

            case 'dcf':
                $fallback['inputs'] = array(
                    'wacc' => 10.0,
                    'terminal_growth' => 2.5,
                    'projection_years' => 5
                );
                break;

            case 'three-statement':
                $fallback['inputs'] = array(
                    'revenue_growth' => $benchmarks['revenue_growth']['median'] ?? 8.0,
                    'ebitda_margin' => $benchmarks['ebitda_margin']['median'] ?? 20.0,
                    'projection_years' => 3
                );
                break;

            default:
                $fallback['inputs'] = array();
        }

        return $fallback;
    }

    /**
     * Should use batch processing (for high-volume periods)
     *
     * @return bool True if should defer to batch processing
     */
    public function should_use_batch_processing() {
        $today = date('Y-m-d');
        $hour = (int) date('H');

        // Get today's operation count
        $today_generations = (int) get_option('sffc_daily_generation_count_' . $today, 0);

        // Batch processing recommended if:
        // 1. More than 50 generations today (high volume)
        // 2. Business hours (9 AM - 6 PM) with high load
        if ($today_generations > 50 && $hour >= 9 && $hour <= 18) {
            // Check last hour's rate
            $last_hour_key = 'sffc_hourly_generations_' . $today . '_' . ($hour - 1);
            $last_hour_count = (int) get_option($last_hour_key, 0);

            // If more than 10 per hour, recommend batching
            return $last_hour_count > 10;
        }

        return false;
    }

    /**
     * Queue article for batch processing
     *
     * @param int $post_id Post ID
     * @param string $operation_type Operation type
     * @return bool Success
     */
    public function queue_for_batch($post_id, $operation_type) {
        $queue = get_option('sffc_batch_queue', array());

        $queue[] = array(
            'post_id' => $post_id,
            'operation' => $operation_type,
            'queued_at' => current_time('mysql'),
            'priority' => 'normal'
        );

        return update_option('sffc_batch_queue', $queue, false);
    }

    /**
     * Process batch queue (called via cron or manual trigger)
     *
     * @param int $limit Max items to process
     * @return array Results
     */
    public function process_batch_queue($limit = 10) {
        $queue = get_option('sffc_batch_queue', array());

        if (empty($queue)) {
            return array('processed' => 0, 'remaining' => 0);
        }

        $processed = 0;
        $results = array();

        // Process up to limit items
        while ($processed < $limit && !empty($queue)) {
            $item = array_shift($queue);

            // Check if still within budget
            if (!$this->is_within_daily_budget($item['operation'])) {
                // Re-add to queue for tomorrow
                $queue[] = $item;
                break;
            }

            // Process would go here (triggers actual classification/generation)
            // This is a hook point for the actual processing logic
            $results[] = array(
                'post_id' => $item['post_id'],
                'operation' => $item['operation'],
                'status' => 'processed'
            );

            $processed++;
        }

        // Save remaining queue
        update_option('sffc_batch_queue', $queue, false);

        return array(
            'processed' => $processed,
            'remaining' => count($queue),
            'results' => $results
        );
    }

    /**
     * Set custom budget limits
     *
     * @param float $daily Daily budget in dollars
     * @param float $monthly Monthly budget in dollars
     */
    public function set_budget_limits($daily, $monthly) {
        update_option('sffc_daily_api_budget', $daily * 100);     // Store in cents
        update_option('sffc_monthly_api_budget', $monthly * 100); // Store in cents
    }

    /**
     * Get API usage report for admin
     *
     * @param int $days Number of days to include
     * @return array Usage report
     */
    public function get_usage_report($days = 30) {
        $report = array(
            'daily' => array(),
            'totals' => array(
                'cost' => 0,
                'classifications' => 0,
                'generations' => 0
            )
        );

        for ($i = 0; $i < $days; $i++) {
            $date = date('Y-m-d', strtotime("-{$i} days"));

            $cost = (float) get_option('sffc_daily_api_cost_' . $date, 0);
            $classifications = (int) get_option('sffc_daily_classification_count_' . $date, 0);
            $generations = (int) get_option('sffc_daily_generation_count_' . $date, 0);

            $report['daily'][$date] = array(
                'cost' => $cost / 100,
                'classifications' => $classifications,
                'generations' => $generations
            );

            $report['totals']['cost'] += $cost / 100;
            $report['totals']['classifications'] += $classifications;
            $report['totals']['generations'] += $generations;
        }

        $report['averages'] = array(
            'daily_cost' => $report['totals']['cost'] / $days,
            'daily_classifications' => $report['totals']['classifications'] / $days,
            'daily_generations' => $report['totals']['generations'] / $days
        );

        return $report;
    }
}

// Initialize
add_action('plugins_loaded', function() {
    SFFC_API_Cost_Manager::get_instance();
}, 15); // After schema class
