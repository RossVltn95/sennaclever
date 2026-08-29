<?php

/**
 * Search Query Cleanup Admin Interface
 * 
 * Admin panel for managing search query database cleanup
 * 
 * @package SennaCareers
 * @since 11.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Search_Cleanup_Admin
{
    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_sffc_get_cleanup_stats', array($this, 'ajax_get_stats'));
        add_action('wp_ajax_sffc_emergency_cleanup', array($this, 'ajax_emergency_cleanup'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu()
    {
        add_submenu_page(
            'sffc-admin',
            'Search Query Cleanup',
            'Search Cleanup',
            'manage_options',
            'sffc-search-cleanup',
            array($this, 'admin_page')
        );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook)
    {
        if (strpos($hook, 'sffc-search-cleanup') === false) {
            return;
        }

        wp_enqueue_script('sffc-cleanup-admin', plugin_dir_url(__FILE__) . '../assets/js/cleanup-admin.js', array('jquery'), '1.0.0', true);
        wp_localize_script('sffc-cleanup-admin', 'sffcCleanup', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sffc_cleanup_nonce')
        ));
    }

    /**
     * Admin page content
     */
    public function admin_page()
    {
        $cleanup = SFFC_Search_Query_Cleanup::get_instance();
        $stats = $cleanup->get_cleanup_stats();
        ?>
        <div class="wrap">
            <h1>Search Query Cleanup Manager</h1>
            
            <div class="notice notice-info">
                <p><strong>Current Database Status:</strong> 
                   <?php echo number_format($stats['total_queries']); ?> total search queries, 
                   <?php echo $stats['table_size_mb']; ?> MB table size</p>
            </div>

            <div class="card" style="margin-top: 20px;">
                <h2>Cleanup Statistics</h2>
                <table class="wp-list-table widefat fixed striped">
                    <tbody>
                        <tr>
                            <td><strong>Total Queries</strong></td>
                            <td><?php echo number_format($stats['total_queries']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Queries (Last 7 days)</strong></td>
                            <td><?php echo number_format($stats['last_7_days']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Queries (Last 30 days)</strong></td>
                            <td><?php echo number_format($stats['last_30_days']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Potential Duplicates</strong></td>
                            <td><?php echo number_format($stats['potential_duplicates']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Estimated Non-SEO Queries</strong></td>
                            <td><?php echo number_format($stats['estimated_non_seo']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Table Size</strong></td>
                            <td><?php echo $stats['table_size_mb']; ?> MB</td>
                        </tr>
                        <tr>
                            <td><strong>Last Cleanup</strong></td>
                            <td><?php echo $stats['last_cleanup']; ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="card" style="margin-top: 20px;">
                <h2>Cleanup Actions</h2>
                <p>Regular cleanup runs automatically daily at 3 AM. You can also trigger manual cleanup below.</p>
                
                <div style="margin: 20px 0;">
                    <button id="sffc-manual-cleanup" class="button button-primary button-large">
                        Run Full Cleanup Now
                    </button>
                    <button id="sffc-emergency-cleanup" class="button button-secondary button-large" style="margin-left: 10px;">
                        Emergency Cleanup (Quick)
                    </button>
                    <button id="sffc-refresh-stats" class="button" style="margin-left: 10px;">
                        Refresh Statistics
                    </button>
                </div>

                <div id="cleanup-progress" style="display: none;">
                    <div class="notice notice-info">
                        <p><strong>Cleanup in progress...</strong> Please wait.</p>
                    </div>
                </div>

                <div id="cleanup-results" style="display: none;">
                    <!-- Results will be populated by JavaScript -->
                </div>
            </div>

            <div class="card" style="margin-top: 20px;">
                <h2>Cleanup Settings</h2>
                <form method="post" action="options.php">
                    <?php
                    settings_fields('sffc_cleanup_settings');
                    do_settings_sections('sffc_cleanup_settings');
                    ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Automatic Cleanup</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="sffc_cleanup_enabled" value="1" 
                                           <?php checked(get_option('sffc_cleanup_enabled', 1)); ?> />
                                    Enable automatic daily cleanup
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Retention Period</th>
                            <td>
                                <select name="sffc_cleanup_retention_days">
                                    <option value="30" <?php selected(get_option('sffc_cleanup_retention_days', 90), 30); ?>>30 days</option>
                                    <option value="60" <?php selected(get_option('sffc_cleanup_retention_days', 90), 60); ?>>60 days</option>
                                    <option value="90" <?php selected(get_option('sffc_cleanup_retention_days', 90), 90); ?>>90 days</option>
                                    <option value="180" <?php selected(get_option('sffc_cleanup_retention_days', 90), 180); ?>>180 days</option>
                                </select>
                                <p class="description">How long to keep search queries before deletion</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Duplicate Threshold</th>
                            <td>
                                <input type="number" name="sffc_cleanup_duplicate_threshold" 
                                       value="<?php echo get_option('sffc_cleanup_duplicate_threshold', 50); ?>" 
                                       min="10" max="1000" />
                                <p class="description">Remove queries repeated more than this many times from same source</p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button('Save Settings'); ?>
                </form>
            </div>

            <div class="card" style="margin-top: 20px;">
                <h2>Recent Cleanup Log</h2>
                <div style="background: #f0f0f0; padding: 15px; border-radius: 5px; max-height: 300px; overflow-y: auto;">
                    <?php
                    $logs = get_option('sffc_cleanup_logs', array());
                    if (!empty($logs)) {
                        echo '<pre style="margin: 0; white-space: pre-wrap;">';
                        foreach (array_reverse(array_slice($logs, -20)) as $log) {
                            echo esc_html($log['time']) . ' - ' . esc_html($log['message']) . "\n";
                        }
                        echo '</pre>';
                    } else {
                        echo '<p>No cleanup logs available yet.</p>';
                    }
                    ?>
                </div>
            </div>

            <div class="card" style="margin-top: 20px;">
                <h2>What Gets Cleaned Up</h2>
                <ul style="list-style-type: disc; padding-left: 20px;">
                    <li><strong>Repetitive Queries:</strong> Queries repeated more than 50 times from the same IP/user (keeps 10 most recent)</li>
                    <li><strong>Non-SEO Friendly:</strong> Queries with repeated words like "top top top", too short/long queries</li>
                    <li><strong>Spam Patterns:</strong> Test queries, random characters, bot traffic, suspicious patterns</li>
                    <li><strong>Old Queries:</strong> Queries older than the retention period (default: 90 days)</li>
                    <li><strong>Zero Results:</strong> Frequently repeated queries that return no results (likely spam)</li>
                </ul>
            </div>
        </div>

        <style>
        .card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .card h2 {
            margin-top: 0;
            margin-bottom: 15px;
        }
        #cleanup-progress .notice {
            margin: 0;
        }
        #cleanup-results {
            margin-top: 15px;
        }
        .cleanup-result {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 4px;
            padding: 10px;
            margin: 5px 0;
        }
        .cleanup-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            padding: 10px;
            margin: 5px 0;
        }
        </style>
        <?php
    }

    /**
     * AJAX: Get current cleanup statistics
     */
    public function ajax_get_stats()
    {
        if (!check_ajax_referer('sffc_cleanup_nonce', 'nonce', false) || !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }

        $cleanup = SFFC_Search_Query_Cleanup::get_instance();
        $stats = $cleanup->get_cleanup_stats();
        
        wp_send_json_success($stats);
    }

    /**
     * AJAX: Emergency cleanup
     */
    public function ajax_emergency_cleanup()
    {
        if (!check_ajax_referer('sffc_cleanup_nonce', 'nonce', false) || !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }

        $cleanup = SFFC_Search_Query_Cleanup::get_instance();
        $removed = $cleanup->emergency_cleanup();
        
        wp_send_json_success(array(
            'removed' => $removed,
            'message' => "Emergency cleanup completed. Removed {$removed} problematic queries."
        ));
    }
}