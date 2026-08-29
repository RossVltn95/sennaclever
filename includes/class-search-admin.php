<?php
/**
 * Search Administration Interface
 * Provides admin interface for managing search index and settings
 * 
 * @package SennaCareers
 * @since 10.23.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Search_Admin {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
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
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_sffc_rebuild_search_index', array($this, 'handle_rebuild_index'));
        add_action('wp_ajax_sffc_search_status', array($this, 'get_search_status'));
        add_action('wp_ajax_sffc_save_search_settings', array($this, 'save_search_settings'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=sffc_job',
            'Search Engine Settings',
            'Search Settings',
            'manage_options',
            'sffc-search-settings',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook_suffix) {
        if ($hook_suffix !== 'sffc_job_page_sffc-search-settings') {
            return;
        }
        
        wp_enqueue_script(
            'sffc-search-admin',
            SFFC_PLUGIN_URL . 'assets/js/search-admin.js',
            array('jquery'),
            SFFC_VERSION,
            true
        );
        
        wp_localize_script('sffc-search-admin', 'sffc_search_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sffc_admin_nonce'),
            'strings' => array(
                'rebuilding' => 'Rebuilding search index...',
                'rebuild_success' => 'Search index rebuilt successfully!',
                'rebuild_error' => 'Error rebuilding search index.',
                'confirm_rebuild' => 'This will rebuild the entire search index. Continue?'
            )
        ));
        
        wp_enqueue_style(
            'sffc-search-admin',
            SFFC_PLUGIN_URL . 'assets/css/search-admin.css',
            array(),
            SFFC_VERSION
        );
    }
    
    /**
     * Admin page
     */
    public function admin_page() {
        $status = $this->get_index_status();
        $settings = $this->get_search_settings();
        ?>
        <div class="wrap sffc-search-admin">
            <h1>🔍 MENA Careers Private Equity Search Engine</h1>
            <p>Manage your "Google for Private Equity" search system settings and index.</p>
            
            <div class="sffc-admin-grid">
                <!-- Search Index Status -->
                <div class="sffc-admin-card">
                    <h2>📊 Search Index Status</h2>
                    <div class="sffc-status-grid">
                        <div class="sffc-stat">
                            <span class="sffc-stat-number"><?php echo number_format($status['total_indexed']); ?></span>
                            <span class="sffc-stat-label">Total Items Indexed</span>
                        </div>
                        <div class="sffc-stat">
                            <span class="sffc-stat-number"><?php echo number_format($status['entities_count']); ?></span>
                            <span class="sffc-stat-label">Entities Tracked</span>
                        </div>
                        <div class="sffc-stat">
                            <span class="sffc-stat-number"><?php echo $status['last_updated']; ?></span>
                            <span class="sffc-stat-label">Last Updated</span>
                        </div>
                    </div>
                    
                    <div class="sffc-post-type-breakdown">
                        <h3>Content Breakdown:</h3>
                        <?php foreach ($status['by_post_type'] as $post_type => $count): ?>
                            <div class="sffc-breakdown-item">
                                <span class="sffc-breakdown-type"><?php echo $this->get_post_type_label($post_type); ?></span>
                                <span class="sffc-breakdown-count"><?php echo number_format($count); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Index Management -->
                <div class="sffc-admin-card">
                    <h2>⚙️ Index Management</h2>
                    <p>Rebuild the search index to ensure all content is searchable.</p>
                    
                    <div class="sffc-rebuild-actions">
                        <button id="sffc-rebuild-index" class="button button-primary button-large">
                            🔄 Rebuild Search Index
                        </button>
                        <button id="sffc-check-status" class="button button-secondary">
                            📊 Check Status
                        </button>
                    </div>
                    
                    <div id="sffc-rebuild-progress" class="sffc-progress-container" style="display: none;">
                        <div class="sffc-progress-bar">
                            <div class="sffc-progress-fill"></div>
                        </div>
                        <div class="sffc-progress-text">Starting...</div>
                    </div>
                    
                    <div id="sffc-rebuild-results" class="sffc-results-container"></div>
                </div>
                
                <!-- Search Settings -->
                <div class="sffc-admin-card">
                    <h2>🎯 Search Settings</h2>
                    <form id="sffc-search-settings-form">
                        <?php wp_nonce_field('sffc_admin_nonce', 'settings_nonce'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">Results Per Page</th>
                                <td>
                                    <input type="number" name="results_per_page" value="<?php echo esc_attr($settings['results_per_page']); ?>" min="5" max="50" />
                                    <p class="description">Number of search results to show per page (5-50)</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Enable Autocomplete</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="enable_autocomplete" value="1" <?php checked($settings['enable_autocomplete']); ?> />
                                        Show search suggestions as users type
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Search Modes</th>
                                <td>
                                    <?php 
                                    $modes = array(
                                        'jobs' => 'Jobs',
                                        'insights' => 'Insights',
                                        'recruiters' => 'Recruiters',
                                        'companies' => 'Companies',
                                        'templates' => 'Templates'
                                    );
                                    foreach ($modes as $mode => $label): ?>
                                        <label style="display: block; margin-bottom: 5px;">
                                            <input type="checkbox" name="enabled_modes[]" value="<?php echo $mode; ?>" 
                                                   <?php checked(in_array($mode, $settings['enabled_modes'])); ?> />
                                            <?php echo $label; ?>
                                        </label>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Default Search Mode</th>
                                <td>
                                    <select name="default_mode">
                                        <?php foreach ($modes as $mode => $label): ?>
                                            <option value="<?php echo $mode; ?>" <?php selected($settings['default_mode'], $mode); ?>>
                                                <?php echo $label; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Search Analytics</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="enable_analytics" value="1" <?php checked($settings['enable_analytics']); ?> />
                                        Track search queries and user behavior
                                    </label>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <button type="submit" class="button button-primary">Save Settings</button>
                        </p>
                    </form>
                </div>
                
                <!-- Search Analytics -->
                <div class="sffc-admin-card">
                    <h2>📈 Search Analytics</h2>
                    <?php if ($settings['enable_analytics']): ?>
                        <?php $analytics = $this->get_search_analytics(); ?>
                        <div class="sffc-analytics-grid">
                            <div class="sffc-analytics-stat">
                                <span class="sffc-stat-number"><?php echo number_format($analytics['total_searches']); ?></span>
                                <span class="sffc-stat-label">Total Searches (30 days)</span>
                            </div>
                            <div class="sffc-analytics-stat">
                                <span class="sffc-stat-number"><?php echo number_format($analytics['unique_queries']); ?></span>
                                <span class="sffc-stat-label">Unique Queries</span>
                            </div>
                            <div class="sffc-analytics-stat">
                                <span class="sffc-stat-number"><?php echo $analytics['avg_results']; ?></span>
                                <span class="sffc-stat-label">Avg Results</span>
                            </div>
                        </div>
                        
                        <h3>Top Search Queries</h3>
                        <div class="sffc-top-queries">
                            <?php foreach ($analytics['top_queries'] as $query): ?>
                                <div class="sffc-query-item">
                                    <span class="sffc-query-text"><?php echo esc_html($query['query']); ?></span>
                                    <span class="sffc-query-count"><?php echo $query['count']; ?> searches</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p>Enable search analytics in settings to view search data.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get search index status
     */
    private function get_index_status() {
        global $wpdb;
        
        $index_table = $wpdb->prefix . 'sffc_search_index';
        $entities_table = $wpdb->prefix . 'sffc_search_entities';
        
        // Check if tables exist
        if ($wpdb->get_var("SHOW TABLES LIKE '$index_table'") !== $index_table) {
            return array(
                'total_indexed' => 0,
                'entities_count' => 0,
                'by_post_type' => array(),
                'last_updated' => 'Never'
            );
        }
        
        // Total indexed
        $total_indexed = $wpdb->get_var("SELECT COUNT(*) FROM $index_table");
        
        // Entities count
        $entities_count = $wpdb->get_var("SELECT COUNT(*) FROM $entities_table");
        
        // By post type
        $by_post_type = array();
        $results = $wpdb->get_results("SELECT post_type, COUNT(*) as count FROM $index_table GROUP BY post_type");
        foreach ($results as $result) {
            $by_post_type[$result->post_type] = $result->count;
        }
        
        // Last updated
        $last_updated = $wpdb->get_var("SELECT modified_date FROM $index_table ORDER BY modified_date DESC LIMIT 1");
        if ($last_updated) {
            $last_updated = human_time_diff(strtotime($last_updated)) . ' ago';
        } else {
            $last_updated = 'Never';
        }
        
        return array(
            'total_indexed' => intval($total_indexed),
            'entities_count' => intval($entities_count),
            'by_post_type' => $by_post_type,
            'last_updated' => $last_updated
        );
    }
    
    /**
     * Get search settings with defaults
     */
    private function get_search_settings() {
        $defaults = array(
            'results_per_page' => 12,
            'enable_autocomplete' => true,
            'enabled_modes' => array('jobs', 'insights', 'recruiters', 'companies', 'templates'),
            'default_mode' => 'jobs',
            'enable_analytics' => true
        );
        
        $settings = get_option('sffc_search_settings', array());
        return wp_parse_args($settings, $defaults);
    }
    
    /**
     * Get search analytics
     */
    private function get_search_analytics() {
        global $wpdb;
        
        $analytics_table = $wpdb->prefix . 'sffc_search_queries';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$analytics_table'") !== $analytics_table) {
            return array(
                'total_searches' => 0,
                'unique_queries' => 0,
                'avg_results' => 0,
                'top_queries' => array()
            );
        }
        
        $date_30_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));
        
        // Total searches last 30 days
        $total_searches = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $analytics_table WHERE created_date >= %s",
            $date_30_days_ago
        ));
        
        // Unique queries
        $unique_queries = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT query_text) FROM $analytics_table WHERE created_date >= %s",
            $date_30_days_ago
        ));
        
        // Average results
        $avg_results = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(results_count) FROM $analytics_table WHERE created_date >= %s AND results_count > 0",
            $date_30_days_ago
        ));
        
        // Top queries
        $top_queries = $wpdb->get_results($wpdb->prepare(
            "SELECT query_text as query, COUNT(*) as count 
             FROM $analytics_table 
             WHERE created_date >= %s 
             GROUP BY query_text 
             ORDER BY count DESC 
             LIMIT 10",
            $date_30_days_ago
        ), ARRAY_A);
        
        return array(
            'total_searches' => intval($total_searches),
            'unique_queries' => intval($unique_queries),
            'avg_results' => round(floatval($avg_results), 1),
            'top_queries' => $top_queries ?: array()
        );
    }
    
    /**
     * Get post type label
     */
    private function get_post_type_label($post_type) {
        $labels = array(
            'sffc_job' => '💼 Jobs',
            'sffc_pe_news' => '📰 News',
            'sffc_recruiter' => '👥 Recruiters',
            'sffc_company' => '🏢 Companies',
            'sffc_deal' => '💰 Deals'
        );
        
        return $labels[$post_type] ?? $post_type;
    }
    
    /**
     * Handle rebuild index AJAX
     */
    public function handle_rebuild_index() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        check_ajax_referer('sffc_admin_nonce', 'nonce');
        
        // Get indexer instance
        $indexer = SFFC_Search_Indexer::get_instance();
        
        if (!$indexer) {
            wp_send_json_error('Search indexer not available');
        }
        
        // Rebuild index
        $indexed = $indexer->rebuild_index();
        
        wp_send_json_success(array(
            'message' => sprintf('Successfully indexed %d items', $indexed),
            'indexed_count' => $indexed,
            'status' => $this->get_index_status()
        ));
    }
    
    /**
     * Get search status AJAX
     */
    public function get_search_status() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        check_ajax_referer('sffc_admin_nonce', 'nonce');
        
        wp_send_json_success($this->get_index_status());
    }
    
    /**
     * Save search settings AJAX
     */
    public function save_search_settings() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        check_ajax_referer('sffc_admin_nonce', 'settings_nonce');
        
        $settings = array(
            'results_per_page' => max(5, min(50, intval($_POST['results_per_page']))),
            'enable_autocomplete' => isset($_POST['enable_autocomplete']),
            'enabled_modes' => array_intersect($_POST['enabled_modes'] ?? array(), array('jobs', 'insights', 'recruiters', 'companies', 'templates')),
            'default_mode' => sanitize_text_field($_POST['default_mode']),
            'enable_analytics' => isset($_POST['enable_analytics'])
        );
        
        update_option('sffc_search_settings', $settings);
        
        wp_send_json_success('Settings saved successfully');
    }
}

// Initialize
SFFC_Search_Admin::get_instance();
