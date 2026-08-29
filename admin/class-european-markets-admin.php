<?php

/**
 * European Markets Admin Interface
 * 
 * @package SennaCareers
 * @subpackage EuropeanMarkets
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_European_Markets_Admin
{

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_sffc_install_eu_tables', array($this, 'ajax_install_tables'));
        add_action('wp_ajax_sffc_check_eu_tables', array($this, 'ajax_check_tables'));
        add_action('wp_ajax_sffc_install_single_table', array($this, 'ajax_install_single_table'));
        add_action('wp_ajax_sffc_repair_tables', array($this, 'ajax_repair_tables'));

        // Check and create tables on admin init if needed
        add_action('admin_init', array($this, 'check_and_create_tables'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu()
    {
        add_submenu_page(
            'sffc-settings',
            'European Markets',
            'European Markets',
            'manage_options',
            'sffc-european-markets',
            array($this, 'render_admin_page')
        );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook)
    {
        if ($hook !== 'senna-finance_page_sffc-european-markets') {
            return;
        }

        wp_enqueue_script(
            'sffc-european-admin',
            SFFC_PLUGIN_URL . 'admin/js/european-markets-admin.js',
            array('jquery'),
            '2.0.0',
            true
        );

        wp_localize_script('sffc-european-admin', 'sffc_eu_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sffc_eu_admin_nonce')
        ));

        wp_enqueue_style(
            'sffc-european-admin',
            SFFC_PLUGIN_URL . 'admin/css/european-markets-admin.css',
            array(),
            '2.0.0'
        );
    }

    /**
     * Render admin page
     */
    public function render_admin_page()
    {
        // Check if European database class exists
        if (!class_exists('SFFC_European_Database')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-european-database.php';
        }

        $eu_db = SFFC_European_Database::get_instance();
        $tables_status = $eu_db->get_tables_status();

?>
        <div class="wrap">
            <h1>🇪🇺 European Market Analysis Platform</h1>

            <div class="sffc-eu-dashboard">
                <!-- Implementation Status -->
                <div class="sffc-card">
                    <h2>Implementation Status</h2>
                    <div class="sffc-progress-tracker">
                        <div class="phase-item <?php echo $this->is_phase_complete(1) ? 'complete' : 'pending'; ?>">
                            <span class="phase-number">1</span>
                            <span class="phase-name">Database Schema</span>
                            <span class="phase-status"><?php echo $this->get_phase_status(1); ?></span>
                        </div>
                        <div class="phase-item <?php echo $this->is_phase_complete(2) ? 'complete' : 'pending'; ?>">
                            <span class="phase-number">2</span>
                            <span class="phase-name">Data Integration</span>
                            <span class="phase-status"><?php echo $this->get_phase_status(2); ?></span>
                        </div>
                        <div class="phase-item <?php echo $this->is_phase_complete(3) ? 'complete' : 'pending'; ?>">
                            <span class="phase-number">3</span>
                            <span class="phase-name">Analysis Engine</span>
                            <span class="phase-status"><?php echo $this->get_phase_status(3); ?></span>
                        </div>
                        <div class="phase-item <?php echo $this->is_phase_complete(4) ? 'complete' : 'pending'; ?>">
                            <span class="phase-number">4</span>
                            <span class="phase-name">Intelligence Layer</span>
                            <span class="phase-status"><?php echo $this->get_phase_status(4); ?></span>
                        </div>
                        <div class="phase-item <?php echo $this->is_phase_complete(5) ? 'complete' : 'pending'; ?>">
                            <span class="phase-number">5</span>
                            <span class="phase-name">Premium Interface</span>
                            <span class="phase-status"><?php echo $this->get_phase_status(5); ?></span>
                        </div>
                        <div class="phase-item <?php echo $this->is_phase_complete(6) ? 'complete' : 'pending'; ?>">
                            <span class="phase-number">6</span>
                            <span class="phase-name">Advanced Features</span>
                            <span class="phase-status"><?php echo $this->get_phase_status(6); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Database Tables Status -->
                <div class="sffc-card">
                    <h2>Database Tables Status</h2>
                    <div class="tables-grid">
                        <?php foreach ($tables_status as $key => $table): ?>
                            <div class="table-status-item <?php echo $table['exists'] ? 'exists' : 'missing'; ?>">
                                <span class="table-icon"><?php echo $table['exists'] ? '✅' : '❌'; ?></span>
                                <span class="table-name"><?php echo $this->format_table_name($key); ?></span>
                                <span class="table-technical"><?php echo esc_html($table['name']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (!$this->all_tables_exist($tables_status)): ?>
                        <div class="install-section">
                            <button id="install-eu-tables" class="button button-primary button-hero">
                                Install All Missing Tables
                            </button>
                            <button id="repair-tables" class="button button-secondary">
                                Repair Tables
                            </button>
                            <div id="install-progress" style="display:none;">
                                <div class="spinner is-active"></div>
                                <span>Installing database tables...</span>
                            </div>
                            <div id="install-result"></div>
                        </div>
                    <?php else: ?>
                        <div class="success-message">
                            <p>✅ All European market tables are installed and ready!</p>
                            <button id="verify-tables" class="button button-secondary">
                                Verify Table Structure
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- European Indices -->
                <div class="sffc-card">
                    <h2>Tracked European Indices</h2>
                    <div class="indices-list">
                        <?php
                        $indices = $this->get_european_indices();
                        if ($indices):
                            foreach ($indices as $index):
                        ?>
                                <div class="index-item">
                                    <span class="index-symbol"><?php echo esc_html($index->index_symbol); ?></span>
                                    <span class="index-name"><?php echo esc_html($index->index_name); ?></span>
                                    <span class="index-country"><?php echo esc_html($index->country); ?></span>
                                    <span class="index-currency"><?php echo esc_html($index->currency); ?></span>
                                </div>
                            <?php
                            endforeach;
                        else:
                            ?>
                            <p>No indices configured yet. Install the database tables to get started.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Data Feed Status -->
                <div class="sffc-card">
                    <h2>Data Feed Status</h2>
                    <div class="feed-stats">
                        <?php
                        $feed_stats = $this->get_feed_statistics();
                        ?>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $feed_stats['total']; ?></span>
                            <span class="stat-label">Total Feeds</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $feed_stats['active']; ?></span>
                            <span class="stat-label">Active Feeds</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $feed_stats['european']; ?></span>
                            <span class="stat-label">European Feeds</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $feed_stats['pe']; ?></span>
                            <span class="stat-label">PE/VC Feeds</span>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="sffc-card">
                    <h2>Quick Actions</h2>
                    <div class="quick-actions">
                        <a href="<?php echo admin_url('admin.php?page=sffc-settings&tab=feeds'); ?>" class="button">
                            Manage Data Feeds
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=sffc-settings&tab=api'); ?>" class="button">
                            Configure APIs
                        </a>
                        <button class="button" id="test-data-fetch">
                            Test Data Fetching
                        </button>
                        <button class="button" id="clear-cache">
                            Clear Market Cache
                        </button>
                    </div>
                </div>
            </div>
        </div>
<?php
    }

    /**
     * AJAX handler for installing tables
     */
    public function ajax_install_tables()
    {
        check_ajax_referer('sffc_eu_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        require_once SFFC_PLUGIN_DIR . 'includes/class-european-database.php';
        $eu_db = SFFC_European_Database::get_instance();

        $results = $eu_db->create_tables();

        $success = true;
        $message = 'Tables installed successfully!';
        $details = array();

        foreach ($results as $table => $status) {
            $details[$table] = $status ? 'Created' : 'Failed';
            if (!$status) {
                $success = false;
                $message = 'Some tables failed to install.';
            }
        }

        wp_send_json(array(
            'success' => $success,
            'message' => $message,
            'details' => $details
        ));
    }

    /**
     * AJAX handler for checking tables
     */
    public function ajax_check_tables()
    {
        check_ajax_referer('sffc_eu_admin_nonce', 'nonce');

        require_once SFFC_PLUGIN_DIR . 'includes/class-european-database.php';
        $eu_db = SFFC_European_Database::get_instance();

        $status = $eu_db->get_tables_status();

        wp_send_json(array(
            'success' => true,
            'tables' => $status
        ));
    }

    /**
     * AJAX handler for installing single table
     */
    public function ajax_install_single_table()
    {
        check_ajax_referer('sffc_eu_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $table_name = sanitize_text_field($_POST['table_name']);

        require_once SFFC_PLUGIN_DIR . 'includes/class-european-database.php';
        $eu_db = SFFC_European_Database::get_instance();

        $method = 'create_' . $table_name . '_table';

        if (method_exists($eu_db, $method)) {
            $result = $eu_db->$method();
            wp_send_json(array(
                'success' => $result,
                'message' => $result ? 'Table created successfully' : 'Failed to create table'
            ));
        } else {
            wp_send_json(array(
                'success' => false,
                'message' => 'Invalid table name'
            ));
        }
    }

    /**
     * AJAX handler for repairing tables
     */
    public function ajax_repair_tables()
    {
        check_ajax_referer('sffc_eu_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        require_once SFFC_PLUGIN_DIR . 'includes/class-european-database.php';
        $eu_db = SFFC_European_Database::get_instance();

        // Drop and recreate all tables
        $results = $eu_db->repair_tables();

        wp_send_json(array(
            'success' => true,
            'message' => 'Tables repaired',
            'results' => $results
        ));
    }

    /**
     * Check and create tables on admin init
     */
    public function check_and_create_tables()
    {
        // Only run on our admin page
        if (!isset($_GET['page']) || $_GET['page'] !== 'sffc-european-markets') {
            return;
        }

        // Check if auto-create is enabled
        if (get_option('sffc_eu_auto_create_tables', false)) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-european-database.php';
            $eu_db = SFFC_European_Database::get_instance();

            $status = $eu_db->get_tables_status();
            $missing = array();

            foreach ($status as $key => $table) {
                if (!$table['exists']) {
                    $missing[] = $key;
                }
            }

            if (!empty($missing)) {
                // Try to create missing tables
                foreach ($missing as $table_key) {
                    $method = 'create_' . $table_key . '_table';
                    if (method_exists($eu_db, $method)) {
                        $eu_db->$method();
                    }
                }
            }
        }
    }

    /**
     * Check if phase is complete
     */
    private function is_phase_complete($phase)
    {
        switch ($phase) {
            case 1:
                // Check if all tables exist
                require_once SFFC_PLUGIN_DIR . 'includes/class-european-database.php';
                $eu_db = SFFC_European_Database::get_instance();
                $status = $eu_db->get_tables_status();
                return $this->all_tables_exist($status);
            default:
                return false;
        }
    }

    /**
     * Get phase status text
     */
    private function get_phase_status($phase)
    {
        if ($this->is_phase_complete($phase)) {
            return '✅ Complete';
        }

        switch ($phase) {
            case 1:
                return '🔄 In Progress';
            default:
                return '⏳ Pending';
        }
    }

    /**
     * Check if all tables exist
     */
    private function all_tables_exist($status)
    {
        foreach ($status as $table) {
            if (!$table['exists']) {
                return false;
            }
        }
        return true;
    }

    /**
     * Format table name for display
     */
    private function format_table_name($key)
    {
        $names = array(
            'market_cache' => 'Market Cache',
            'intraday_prices' => 'Intraday Prices',
            'european_indices' => 'European Indices',
            'pe_transactions' => 'PE Transactions',
            'pe_fundraising' => 'PE Fundraising',
            'pe_firms' => 'PE Firms',
            'market_correlations' => 'Market Correlations',
            'sector_flows' => 'Sector Flows',
            'macro_events' => 'Macro Events',
            'market_sentiment' => 'Market Sentiment',
            'exchange_rates' => 'Exchange Rates'
        );

        return isset($names[$key]) ? $names[$key] : ucfirst(str_replace('_', ' ', $key));
    }

    /**
     * Get European indices from database
     */
    private function get_european_indices()
    {
        global $wpdb;

        require_once SFFC_PLUGIN_DIR . 'includes/class-european-database.php';
        $eu_db = SFFC_European_Database::get_instance();

        if (!$eu_db->table_exists('european_indices')) {
            return false;
        }

        $table = $eu_db->get_table('european_indices');
        return $wpdb->get_results("SELECT * FROM {$table} WHERE tracking_enabled = 1 ORDER BY country, index_name");
    }

    /**
     * Get feed statistics
     */
    private function get_feed_statistics()
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sffc_xml_feeds';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") != $table) {
            return array('total' => 0, 'active' => 0, 'european' => 0, 'pe' => 0);
        }

        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        $active = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE is_active = 1");
        $european = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE feed_category IN ('markets', 'business', 'central-banks') AND feed_name LIKE '%Euro%' OR feed_name LIKE '%UK%' OR feed_name LIKE '%FTSE%' OR feed_name LIKE '%DAX%'");
        $pe = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE feed_category IN ('private-equity', 'venture-capital', 'alternatives')");

        return array(
            'total' => $total ?: 0,
            'active' => $active ?: 0,
            'european' => $european ?: 0,
            'pe' => $pe ?: 0
        );
    }
}

// Initialize
new SFFC_European_Markets_Admin();
?>