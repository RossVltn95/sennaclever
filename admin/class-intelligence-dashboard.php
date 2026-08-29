<?php
/**
 * Intelligence Dashboard Admin
 *
 * Admin dashboard for monitoring the Dynamic Financial Intelligence System.
 * Shows API usage, costs, generation stats, and budget management.
 *
 * @package SennaFinanceCareer
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Intelligence_Dashboard {

    /**
     * Singleton instance
     */
    private static $instance = null;

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
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_sffc_update_budget', array($this, 'ajax_update_budget'));
        add_action('wp_ajax_sffc_clear_caches', array($this, 'ajax_clear_caches'));
        add_action('wp_ajax_sffc_process_batch', array($this, 'ajax_process_batch'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php',
            'Intelligence Dashboard',
            'Intelligence Dashboard',
            'manage_options',
            'sffc-intelligence-dashboard',
            array($this, 'render_dashboard')
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'posts_page_sffc-intelligence-dashboard') {
            return;
        }

        wp_enqueue_style(
            'sffc-intelligence-dashboard',
            SFFC_PLUGIN_URL . 'admin/css/intelligence-dashboard.css',
            array(),
            SFFC_VERSION
        );

        wp_enqueue_script(
            'sffc-intelligence-dashboard',
            SFFC_PLUGIN_URL . 'admin/js/intelligence-dashboard.js',
            array('jquery', 'wp-util'),
            SFFC_VERSION,
            true
        );

        wp_localize_script('sffc-intelligence-dashboard', 'sffcDashboard', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sffc_dashboard_nonce'),
        ));
    }

    /**
     * Render the dashboard
     */
    public function render_dashboard() {
        // Get instances
        if (!class_exists('SFFC_API_Cost_Manager')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-api-cost-manager.php';
        }
        if (!class_exists('SFFC_Intelligence_Schema')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-intelligence-schema.php';
        }

        $cost_manager = SFFC_API_Cost_Manager::get_instance();
        $schema = SFFC_Intelligence_Schema::get_instance();

        // Get data
        $budget_status = $cost_manager->get_budget_status();
        $usage_report = $cost_manager->get_usage_report(30);
        $generation_stats = $schema->get_generation_stats();
        $batch_queue = get_option('sffc_batch_queue', array());

        ?>
        <div class="wrap sffc-intelligence-dashboard">
            <h1>Intelligence System Dashboard</h1>

            <div class="sffc-dashboard-grid">
                <!-- Budget Overview Card -->
                <div class="sffc-card sffc-budget-card">
                    <h2>API Budget Status</h2>

                    <div class="sffc-budget-meters">
                        <div class="sffc-meter-container">
                            <label>Daily Budget</label>
                            <div class="sffc-meter">
                                <div class="sffc-meter-fill <?php echo $budget_status['daily']['percentage_used'] > 80 ? 'warning' : ''; ?>"
                                     style="width: <?php echo min(100, $budget_status['daily']['percentage_used']); ?>%"></div>
                            </div>
                            <div class="sffc-meter-label">
                                $<?php echo number_format($budget_status['daily']['spent'], 2); ?> /
                                $<?php echo number_format($budget_status['daily']['budget'], 2); ?>
                                (<?php echo $budget_status['daily']['percentage_used']; ?>%)
                            </div>
                        </div>

                        <div class="sffc-meter-container">
                            <label>Monthly Budget</label>
                            <div class="sffc-meter">
                                <div class="sffc-meter-fill <?php echo $budget_status['monthly']['percentage_used'] > 80 ? 'warning' : ''; ?>"
                                     style="width: <?php echo min(100, $budget_status['monthly']['percentage_used']); ?>%"></div>
                            </div>
                            <div class="sffc-meter-label">
                                $<?php echo number_format($budget_status['monthly']['spent'], 2); ?> /
                                $<?php echo number_format($budget_status['monthly']['budget'], 2); ?>
                                (<?php echo $budget_status['monthly']['percentage_used']; ?>%)
                            </div>
                        </div>
                    </div>

                    <div class="sffc-budget-settings">
                        <h3>Budget Settings</h3>
                        <form id="sffc-budget-form">
                            <div class="sffc-form-row">
                                <label>Daily Budget ($)</label>
                                <input type="number" step="0.01" name="daily_budget"
                                       value="<?php echo esc_attr($budget_status['daily']['budget']); ?>">
                            </div>
                            <div class="sffc-form-row">
                                <label>Monthly Budget ($)</label>
                                <input type="number" step="0.01" name="monthly_budget"
                                       value="<?php echo esc_attr($budget_status['monthly']['budget']); ?>">
                            </div>
                            <button type="submit" class="button button-primary">Update Budget</button>
                        </form>
                    </div>
                </div>

                <!-- Today's Stats Card -->
                <div class="sffc-card sffc-stats-card">
                    <h2>Today's Activity</h2>
                    <div class="sffc-stats-grid">
                        <div class="sffc-stat">
                            <div class="sffc-stat-value"><?php echo $budget_status['operations_today']['classifications']; ?></div>
                            <div class="sffc-stat-label">Classifications</div>
                        </div>
                        <div class="sffc-stat">
                            <div class="sffc-stat-value"><?php echo $budget_status['operations_today']['generations']; ?></div>
                            <div class="sffc-stat-label">Generations</div>
                        </div>
                        <div class="sffc-stat">
                            <div class="sffc-stat-value"><?php echo $generation_stats['today_downloads']; ?></div>
                            <div class="sffc-stat-label">Downloads</div>
                        </div>
                        <div class="sffc-stat">
                            <div class="sffc-stat-value">$<?php echo number_format($budget_status['daily']['spent'], 2); ?></div>
                            <div class="sffc-stat-label">API Cost</div>
                        </div>
                    </div>
                </div>

                <!-- Overall Stats Card -->
                <div class="sffc-card sffc-overall-card">
                    <h2>Overall Statistics</h2>
                    <div class="sffc-stats-grid">
                        <div class="sffc-stat">
                            <div class="sffc-stat-value"><?php echo number_format($generation_stats['total_classified']); ?></div>
                            <div class="sffc-stat-label">Articles Classified</div>
                        </div>
                        <div class="sffc-stat">
                            <div class="sffc-stat-value"><?php echo number_format($generation_stats['total_with_analysis']); ?></div>
                            <div class="sffc-stat-label">With Analysis</div>
                        </div>
                        <div class="sffc-stat">
                            <div class="sffc-stat-value">$<?php echo number_format($usage_report['totals']['cost'], 2); ?></div>
                            <div class="sffc-stat-label">30-Day Cost</div>
                        </div>
                        <div class="sffc-stat">
                            <div class="sffc-stat-value">$<?php echo number_format($usage_report['averages']['daily_cost'], 2); ?></div>
                            <div class="sffc-stat-label">Avg Daily Cost</div>
                        </div>
                    </div>
                </div>

                <!-- Batch Queue Card -->
                <div class="sffc-card sffc-queue-card">
                    <h2>Batch Queue</h2>
                    <?php if (empty($batch_queue)) : ?>
                        <p class="sffc-empty-state">No items in queue</p>
                    <?php else : ?>
                        <div class="sffc-queue-info">
                            <strong><?php echo count($batch_queue); ?></strong> items pending
                        </div>
                        <ul class="sffc-queue-list">
                            <?php foreach (array_slice($batch_queue, 0, 5) as $item) : ?>
                                <li>
                                    Post #<?php echo esc_html($item['post_id']); ?> -
                                    <?php echo esc_html($item['operation']); ?> -
                                    <?php echo esc_html($item['queued_at']); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <button id="sffc-process-batch" class="button">Process Queue</button>
                    <?php endif; ?>
                </div>

                <!-- Usage Chart Card -->
                <div class="sffc-card sffc-chart-card full-width">
                    <h2>30-Day Usage History</h2>
                    <table class="sffc-usage-table wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Classifications</th>
                                <th>Generations</th>
                                <th>API Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $dates = array_keys($usage_report['daily']);
                            sort($dates);
                            $dates = array_reverse($dates);
                            foreach (array_slice($dates, 0, 14) as $date) :
                                $day = $usage_report['daily'][$date];
                            ?>
                                <tr>
                                    <td><?php echo esc_html($date); ?></td>
                                    <td><?php echo esc_html($day['classifications']); ?></td>
                                    <td><?php echo esc_html($day['generations']); ?></td>
                                    <td>$<?php echo number_format($day['cost'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Actions Card -->
                <div class="sffc-card sffc-actions-card">
                    <h2>System Actions</h2>
                    <div class="sffc-action-buttons">
                        <button id="sffc-clear-benchmark-cache" class="button">
                            Clear Benchmark Cache
                        </button>
                        <button id="sffc-clear-classification-cache" class="button">
                            Clear Classification Cache
                        </button>
                        <p class="description">
                            Clearing caches will force fresh data on next request.
                        </p>
                    </div>
                </div>

                <!-- Lead Captures Card -->
                <div class="sffc-card sffc-leads-card">
                    <h2>Recent Lead Captures</h2>
                    <?php
                    $leads = get_option('sffc_lead_captures', array());
                    $recent_leads = array_slice(array_reverse($leads), 0, 10);
                    ?>
                    <?php if (empty($recent_leads)) : ?>
                        <p class="sffc-empty-state">No leads captured yet</p>
                    <?php else : ?>
                        <table class="sffc-leads-table wp-list-table widefat striped">
                            <thead>
                                <tr>
                                    <th>Email</th>
                                    <th>Post</th>
                                    <th>Model</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_leads as $lead) : ?>
                                    <tr>
                                        <td><?php echo esc_html($lead['email']); ?></td>
                                        <td>
                                            <a href="<?php echo get_edit_post_link($lead['post_id']); ?>">
                                                #<?php echo esc_html($lead['post_id']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo esc_html($lead['model_type']); ?></td>
                                        <td><?php echo esc_html($lead['timestamp']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Update budget settings
     */
    public function ajax_update_budget() {
        check_ajax_referer('sffc_dashboard_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $daily = isset($_POST['daily_budget']) ? floatval($_POST['daily_budget']) : 5.0;
        $monthly = isset($_POST['monthly_budget']) ? floatval($_POST['monthly_budget']) : 100.0;

        if (!class_exists('SFFC_API_Cost_Manager')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-api-cost-manager.php';
        }

        $cost_manager = SFFC_API_Cost_Manager::get_instance();
        $cost_manager->set_budget_limits($daily, $monthly);

        wp_send_json_success(array(
            'message' => 'Budget updated successfully',
            'daily' => $daily,
            'monthly' => $monthly
        ));
    }

    /**
     * AJAX: Clear caches
     */
    public function ajax_clear_caches() {
        check_ajax_referer('sffc_dashboard_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $cache_type = isset($_POST['cache_type']) ? sanitize_text_field($_POST['cache_type']) : '';

        if (!class_exists('SFFC_Intelligence_Schema')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-intelligence-schema.php';
        }

        $schema = SFFC_Intelligence_Schema::get_instance();

        switch ($cache_type) {
            case 'benchmarks':
                $schema->clear_benchmark_caches();
                $message = 'Benchmark caches cleared';
                break;

            case 'classifications':
                // Clear all classification content hashes to force re-classification
                global $wpdb;
                $wpdb->delete($wpdb->postmeta, array('meta_key' => '_sffc_content_hash'));
                $message = 'Classification caches cleared';
                break;

            default:
                wp_send_json_error(array('message' => 'Invalid cache type'));
                return;
        }

        wp_send_json_success(array('message' => $message));
    }

    /**
     * AJAX: Process batch queue
     */
    public function ajax_process_batch() {
        check_ajax_referer('sffc_dashboard_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        if (!class_exists('SFFC_API_Cost_Manager')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-api-cost-manager.php';
        }

        $cost_manager = SFFC_API_Cost_Manager::get_instance();
        $result = $cost_manager->process_batch_queue(10);

        wp_send_json_success(array(
            'message' => "Processed {$result['processed']} items. {$result['remaining']} remaining.",
            'processed' => $result['processed'],
            'remaining' => $result['remaining']
        ));
    }
}

// Initialize
add_action('plugins_loaded', function() {
    if (is_admin()) {
        SFFC_Intelligence_Dashboard::get_instance();
    }
});
