<?php
/**
 * MemberPress Marketing Campaigns Admin
 * 
 * Admin interface for managing legacy users, campaigns, and win-back sequences
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_MemberPress_Campaigns_Admin {
    
    private static $instance = null;
    private $campaign_manager;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // AJAX handlers
        add_action('wp_ajax_sffc_import_legacy_users', [$this, 'ajax_import_legacy_users']);
        add_action('wp_ajax_sffc_mark_user_as_legacy', [$this, 'ajax_mark_user_as_legacy']);
        add_action('wp_ajax_sffc_create_campaign', [$this, 'ajax_create_campaign']);
        add_action('wp_ajax_sffc_update_campaign', [$this, 'ajax_update_campaign']);
        add_action('wp_ajax_sffc_delete_campaign', [$this, 'ajax_delete_campaign']);
        add_action('wp_ajax_sffc_test_campaign_email', [$this, 'ajax_test_campaign_email']);
        add_action('wp_ajax_sffc_get_campaign_stats', [$this, 'ajax_get_campaign_stats']);
        add_action('wp_ajax_sffc_activate_campaign', [$this, 'ajax_activate_campaign']);
        add_action('wp_ajax_sffc_pause_campaign', [$this, 'ajax_pause_campaign']);
        add_action('wp_ajax_sffc_preview_email_template', [$this, 'ajax_preview_email_template']);
    }
    
    /**
     * Add admin menu items
     */
    public function add_admin_menus() {
        // Main campaigns menu
        add_menu_page(
            'MemberPress Campaigns',
            'MP Campaigns',
            'manage_options',
            'memberpress-campaigns',
            [$this, 'render_dashboard_page'],
            'dashicons-megaphone',
            30
        );
        
        // Dashboard submenu
        add_submenu_page(
            'memberpress-campaigns',
            'Campaign Dashboard',
            'Dashboard',
            'manage_options',
            'memberpress-campaigns',
            [$this, 'render_dashboard_page']
        );
        
        // Legacy Users submenu
        add_submenu_page(
            'memberpress-campaigns',
            'Legacy Users',
            'Legacy Users',
            'manage_options',
            'mp-legacy-users',
            [$this, 'render_legacy_users_page']
        );
        
        // Campaigns submenu
        add_submenu_page(
            'memberpress-campaigns',
            'Manage Campaigns',
            'Campaigns',
            'manage_options',
            'mp-campaigns',
            [$this, 'render_campaigns_page']
        );
        
        // Email Templates submenu
        add_submenu_page(
            'memberpress-campaigns',
            'Email Templates',
            'Email Templates',
            'manage_options',
            'mp-email-templates',
            [$this, 'render_email_templates_page']
        );
        
        // Analytics submenu
        add_submenu_page(
            'memberpress-campaigns',
            'Campaign Analytics',
            'Analytics',
            'manage_options',
            'mp-campaign-analytics',
            [$this, 'render_analytics_page']
        );
        
        // Settings submenu
        add_submenu_page(
            'memberpress-campaigns',
            'Campaign Settings',
            'Settings',
            'manage_options',
            'mp-campaign-settings',
            [$this, 'render_settings_page']
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (!strpos($hook, 'memberpress-campaigns') && !strpos($hook, 'mp-')) {
            return;
        }
        
        // Enqueue CSS
        wp_enqueue_style(
            'mp-campaigns-admin',
            plugin_dir_url(__FILE__) . 'css/mp-campaigns-admin.css',
            [],
            '1.0.0'
        );
        
        // Enqueue JavaScript
        wp_enqueue_script(
            'mp-campaigns-admin',
            plugin_dir_url(__FILE__) . 'js/mp-campaigns-admin.js',
            ['jquery', 'wp-util', 'wp-color-picker'],
            '1.0.0',
            true
        );
        
        // Add Chart.js for analytics
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
            [],
            '3.9.1'
        );
        
        // Localize script
        wp_localize_script('mp-campaigns-admin', 'mpCampaigns', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mp_campaigns_admin'),
            'strings' => [
                'confirm_delete' => 'Are you sure you want to delete this campaign?',
                'confirm_activate' => 'Are you sure you want to activate this campaign?',
                'confirm_pause' => 'Are you sure you want to pause this campaign?',
                'import_success' => 'Legacy users imported successfully',
                'import_error' => 'Error importing legacy users',
                'test_email_sent' => 'Test email sent successfully',
                'save_success' => 'Campaign saved successfully',
                'save_error' => 'Error saving campaign'
            ]
        ]);
        
        // Add color picker
        wp_enqueue_style('wp-color-picker');
    }
    
    /**
     * Render dashboard page
     */
    public function render_dashboard_page() {
        // Get statistics
        $stats = $this->get_dashboard_stats();
        ?>
        <div class="wrap mp-campaigns-dashboard">
            <h1>MemberPress Campaign Dashboard</h1>
            
            <!-- Quick Stats -->
            <div class="mp-stats-grid">
                <div class="mp-stat-card">
                    <div class="mp-stat-icon">
                        <span class="dashicons dashicons-groups"></span>
                    </div>
                    <div class="mp-stat-content">
                        <h3>Legacy Users</h3>
                        <div class="mp-stat-number"><?php echo number_format($stats['legacy_users']); ?></div>
                        <div class="mp-stat-meta">Former subscribers</div>
                    </div>
                </div>
                
                <div class="mp-stat-card">
                    <div class="mp-stat-icon">
                        <span class="dashicons dashicons-megaphone"></span>
                    </div>
                    <div class="mp-stat-content">
                        <h3>Active Campaigns</h3>
                        <div class="mp-stat-number"><?php echo $stats['active_campaigns']; ?></div>
                        <div class="mp-stat-meta">Currently running</div>
                    </div>
                </div>
                
                <div class="mp-stat-card">
                    <div class="mp-stat-icon">
                        <span class="dashicons dashicons-email-alt"></span>
                    </div>
                    <div class="mp-stat-content">
                        <h3>Emails Sent</h3>
                        <div class="mp-stat-number"><?php echo number_format($stats['emails_sent']); ?></div>
                        <div class="mp-stat-meta">This month</div>
                    </div>
                </div>
                
                <div class="mp-stat-card success">
                    <div class="mp-stat-icon">
                        <span class="dashicons dashicons-chart-line"></span>
                    </div>
                    <div class="mp-stat-content">
                        <h3>Win-Back Rate</h3>
                        <div class="mp-stat-number"><?php echo $stats['winback_rate']; ?>%</div>
                        <div class="mp-stat-meta">Conversion rate</div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="mp-dashboard-section">
                <h2>Recent Campaign Activity</h2>
                <div class="mp-activity-table">
                    <?php $this->render_recent_activity_table(); ?>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="mp-dashboard-section">
                <h2>Quick Actions</h2>
                <div class="mp-quick-actions">
                    <a href="<?php echo admin_url('admin.php?page=mp-legacy-users'); ?>" class="button button-primary">
                        <span class="dashicons dashicons-upload"></span>
                        Import Legacy Users
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=mp-campaigns&action=new'); ?>" class="button button-primary">
                        <span class="dashicons dashicons-plus-alt"></span>
                        Create New Campaign
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=mp-email-templates&action=new'); ?>" class="button">
                        <span class="dashicons dashicons-welcome-write-blog"></span>
                        New Email Template
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=mp-campaign-analytics'); ?>" class="button">
                        <span class="dashicons dashicons-analytics"></span>
                        View Analytics
                    </a>
                </div>
            </div>
            
            <!-- Active Campaigns Chart -->
            <div class="mp-dashboard-section">
                <h2>Campaign Performance (Last 30 Days)</h2>
                <div class="mp-chart-container">
                    <canvas id="campaignPerformanceChart"></canvas>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render legacy users page
     */
    public function render_legacy_users_page() {
        $action = isset($_GET['action']) ? $_GET['action'] : 'list';
        
        if ($action === 'import') {
            $this->render_legacy_users_import();
        } else {
            $this->render_legacy_users_list();
        }
    }
    
    /**
     * Render legacy users list
     */
    private function render_legacy_users_list() {
        ?>
        <div class="wrap mp-legacy-users">
            <h1>
                Legacy Users
                <a href="<?php echo admin_url('admin.php?page=mp-legacy-users&action=import'); ?>" class="page-title-action">Import Users</a>
            </h1>
            
            <div class="mp-filters">
                <form method="get">
                    <input type="hidden" name="page" value="mp-legacy-users">
                    
                    <select name="status" class="mp-filter-select">
                        <option value="">All Status</option>
                        <option value="imported">Imported</option>
                        <option value="contacted">Contacted</option>
                        <option value="converted">Converted</option>
                        <option value="declined">Declined</option>
                    </select>
                    
                    <select name="original_tier" class="mp-filter-select">
                        <option value="">All Tiers</option>
                        <option value="basic">Basic</option>
                        <option value="premium">Premium</option>
                        <option value="professional">Professional</option>
                    </select>
                    
                    <input type="date" name="last_active_from" placeholder="Last Active From">
                    <input type="date" name="last_active_to" placeholder="Last Active To">
                    
                    <button type="submit" class="button">Filter</button>
                    <a href="<?php echo admin_url('admin.php?page=mp-legacy-users'); ?>" class="button">Clear</a>
                </form>
            </div>
            
            <div class="mp-legacy-users-table">
                <?php $this->render_legacy_users_table(); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render legacy users import form
     */
    private function render_legacy_users_import() {
        ?>
        <div class="wrap mp-legacy-import">
            <h1>Import Legacy Users</h1>
            
            <div class="mp-import-container">
                <div class="mp-import-method">
                    <h2>Import Methods</h2>
                    
                    <!-- CSV Upload -->
                    <div class="mp-import-option">
                        <h3><span class="dashicons dashicons-media-spreadsheet"></span> CSV Upload</h3>
                        <p>Upload a CSV file with legacy user information</p>
                        <form id="mp-csv-import-form" enctype="multipart/form-data">
                            <?php wp_nonce_field('mp_import_legacy_csv'); ?>
                            <input type="file" name="legacy_users_csv" accept=".csv" required>
                            <button type="submit" class="button button-primary">Upload CSV</button>
                        </form>
                        <div class="mp-csv-format">
                            <h4>CSV Format:</h4>
                            <code>email, name, original_tier, original_price, last_payment_date, cancel_date, total_spent</code>
                        </div>
                    </div>
                    
                    <!-- Manual Entry -->
                    <div class="mp-import-option">
                        <h3><span class="dashicons dashicons-edit"></span> Manual Entry</h3>
                        <p>Add legacy users one by one or paste a list</p>
                        <form id="mp-manual-import-form">
                            <?php wp_nonce_field('mp_import_legacy_manual'); ?>
                            <textarea name="legacy_users_list" rows="10" placeholder="Enter email addresses, one per line or paste CSV data"></textarea>
                            
                            <div class="mp-manual-options">
                                <label>
                                    Default Tier:
                                    <select name="default_tier">
                                        <option value="basic">Basic</option>
                                        <option value="premium">Premium</option>
                                        <option value="professional">Professional</option>
                                    </select>
                                </label>
                                
                                <label>
                                    Default Original Price:
                                    <input type="number" name="default_price" step="0.01" placeholder="29.99">
                                </label>
                            </div>
                            
                            <button type="submit" class="button button-primary">Import Users</button>
                        </form>
                    </div>
                    
                    <!-- MemberPress Import -->
                    <div class="mp-import-option">
                        <h3><span class="dashicons dashicons-update"></span> From MemberPress History</h3>
                        <p>Import cancelled/expired subscriptions from MemberPress</p>
                        <form id="mp-memberpress-import-form">
                            <?php wp_nonce_field('mp_import_from_memberpress'); ?>
                            
                            <label>
                                Date Range:
                                <input type="date" name="from_date"> to <input type="date" name="to_date">
                            </label>
                            
                            <label>
                                <input type="checkbox" name="include_expired" checked> Include Expired
                            </label>
                            
                            <label>
                                <input type="checkbox" name="include_cancelled" checked> Include Cancelled
                            </label>
                            
                            <button type="submit" class="button button-primary">Scan & Import</button>
                        </form>
                    </div>
                </div>
                
                <!-- Import Preview -->
                <div class="mp-import-preview" style="display: none;">
                    <h2>Import Preview</h2>
                    <div id="mp-import-preview-content"></div>
                    <div class="mp-import-actions">
                        <button id="mp-confirm-import" class="button button-primary">Confirm Import</button>
                        <button id="mp-cancel-import" class="button">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render campaigns page
     */
    public function render_campaigns_page() {
        $action = isset($_GET['action']) ? $_GET['action'] : 'list';
        
        if ($action === 'new' || $action === 'edit') {
            $this->render_campaign_editor();
        } else {
            $this->render_campaigns_list();
        }
    }
    
    /**
     * Render campaign editor
     */
    private function render_campaign_editor() {
        $campaign_id = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : 0;
        $campaign = $campaign_id ? $this->get_campaign($campaign_id) : null;
        ?>
        <div class="wrap mp-campaign-editor">
            <h1><?php echo $campaign ? 'Edit Campaign' : 'Create New Campaign'; ?></h1>
            
            <form id="mp-campaign-form" method="post">
                <?php wp_nonce_field('mp_save_campaign'); ?>
                <input type="hidden" name="campaign_id" value="<?php echo $campaign_id; ?>">
                
                <!-- Campaign Basic Info -->
                <div class="mp-editor-section">
                    <h2>Campaign Information</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="campaign_name">Campaign Name</label></th>
                            <td>
                                <input type="text" id="campaign_name" name="campaign_name" 
                                       value="<?php echo $campaign ? esc_attr($campaign->name) : ''; ?>" 
                                       class="regular-text" required>
                                <p class="description">Internal name for this campaign</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><label for="campaign_type">Campaign Type</label></th>
                            <td>
                                <select id="campaign_type" name="campaign_type" required>
                                    <option value="">Select Type</option>
                                    <option value="winback_expired">Win-Back (Expired Subscriptions)</option>
                                    <option value="winback_cancelled">Win-Back (Cancelled Subscriptions)</option>
                                    <option value="legacy_pricing">Legacy Pricing Offer</option>
                                    <option value="reengagement">Re-engagement Campaign</option>
                                    <option value="special_offer">Special Offer</option>
                                    <option value="custom">Custom Campaign</option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><label for="campaign_status">Status</label></th>
                            <td>
                                <select id="campaign_status" name="campaign_status">
                                    <option value="draft">Draft</option>
                                    <option value="scheduled">Scheduled</option>
                                    <option value="active">Active</option>
                                    <option value="paused">Paused</option>
                                    <option value="completed">Completed</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Target Audience -->
                <div class="mp-editor-section">
                    <h2>Target Audience</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th><label>User Segments</label></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="target_segments[]" value="legacy_users">
                                    Legacy Users
                                </label><br>
                                <label>
                                    <input type="checkbox" name="target_segments[]" value="expired_subs">
                                    Expired Subscriptions
                                </label><br>
                                <label>
                                    <input type="checkbox" name="target_segments[]" value="cancelled_subs">
                                    Cancelled Subscriptions
                                </label><br>
                                <label>
                                    <input type="checkbox" name="target_segments[]" value="specific_tier">
                                    Specific Tier: 
                                    <select name="target_tier">
                                        <option value="">All</option>
                                        <option value="basic">Basic</option>
                                        <option value="premium">Premium</option>
                                        <option value="professional">Professional</option>
                                    </select>
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><label>Date Filters</label></th>
                            <td>
                                <label>
                                    Last Active Between:
                                    <input type="date" name="last_active_from"> and 
                                    <input type="date" name="last_active_to">
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Campaign Offer -->
                <div class="mp-editor-section">
                    <h2>Campaign Offer</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="offer_type">Offer Type</label></th>
                            <td>
                                <select id="offer_type" name="offer_type">
                                    <option value="percentage_discount">Percentage Discount</option>
                                    <option value="fixed_discount">Fixed Amount Discount</option>
                                    <option value="legacy_pricing">Legacy Pricing</option>
                                    <option value="free_trial">Extended Free Trial</option>
                                    <option value="custom_pricing">Custom Pricing</option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr class="offer-details">
                            <th><label for="offer_value">Offer Value</label></th>
                            <td>
                                <input type="number" id="offer_value" name="offer_value" step="0.01">
                                <span class="offer-suffix">%</span>
                                <p class="description">Enter discount percentage or amount</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><label for="offer_duration">Offer Duration</label></th>
                            <td>
                                <select name="offer_duration">
                                    <option value="1_month">1 Month</option>
                                    <option value="3_months">3 Months</option>
                                    <option value="6_months">6 Months</option>
                                    <option value="1_year">1 Year</option>
                                    <option value="lifetime">Lifetime</option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><label for="offer_expiry">Offer Expires</label></th>
                            <td>
                                <input type="number" name="offer_expiry_days" value="30" min="1" max="365">
                                <span>days after email sent</span>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Email Sequence -->
                <div class="mp-editor-section">
                    <h2>Email Sequence</h2>
                    
                    <div id="mp-email-sequence">
                        <div class="mp-sequence-controls">
                            <button type="button" class="button" id="mp-add-email">Add Email</button>
                            <select id="mp-template-selector">
                                <option value="">Load Template</option>
                                <option value="winback_3_step">Win-Back (3 Steps)</option>
                                <option value="winback_5_step">Win-Back (5 Steps)</option>
                                <option value="legacy_pricing">Legacy Pricing Announcement</option>
                                <option value="reengagement">Re-engagement Series</option>
                            </select>
                        </div>
                        
                        <div class="mp-email-list">
                            <!-- Email items will be added here -->
                        </div>
                    </div>
                </div>
                
                <!-- Schedule -->
                <div class="mp-editor-section">
                    <h2>Campaign Schedule</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th><label>Start Date</label></th>
                            <td>
                                <input type="datetime-local" name="start_date">
                            </td>
                        </tr>
                        
                        <tr>
                            <th><label>End Date</label></th>
                            <td>
                                <input type="datetime-local" name="end_date">
                                <p class="description">Leave empty for no end date</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><label>Send Time Optimization</label></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="optimize_send_time" checked>
                                    Automatically optimize send times based on user timezone and engagement
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="mp-form-actions">
                    <button type="submit" class="button button-primary">Save Campaign</button>
                    <button type="button" class="button" id="mp-save-draft">Save as Draft</button>
                    <button type="button" class="button" id="mp-preview-campaign">Preview</button>
                    <a href="<?php echo admin_url('admin.php?page=mp-campaigns'); ?>" class="button">Cancel</a>
                </div>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render email templates page
     */
    public function render_email_templates_page() {
        ?>
        <div class="wrap mp-email-templates">
            <h1>
                Email Templates
                <a href="#" class="page-title-action" id="mp-new-template">New Template</a>
            </h1>
            
            <div class="mp-templates-grid">
                <?php $this->render_email_templates(); ?>
            </div>
        </div>
        
        <!-- Template Editor Modal -->
        <div id="mp-template-modal" class="mp-modal" style="display: none;">
            <div class="mp-modal-content">
                <span class="mp-modal-close">&times;</span>
                <h2>Email Template Editor</h2>
                
                <div class="mp-template-editor">
                    <div class="mp-editor-tabs">
                        <button class="mp-tab-button active" data-tab="visual">Visual</button>
                        <button class="mp-tab-button" data-tab="html">HTML</button>
                        <button class="mp-tab-button" data-tab="preview">Preview</button>
                    </div>
                    
                    <div class="mp-tab-content" id="mp-visual-tab">
                        <!-- Visual editor content -->
                    </div>
                    
                    <div class="mp-tab-content" id="mp-html-tab" style="display: none;">
                        <textarea id="mp-template-html" rows="20"></textarea>
                    </div>
                    
                    <div class="mp-tab-content" id="mp-preview-tab" style="display: none;">
                        <iframe id="mp-template-preview"></iframe>
                    </div>
                </div>
                
                <div class="mp-modal-actions">
                    <button class="button button-primary" id="mp-save-template">Save Template</button>
                    <button class="button" id="mp-cancel-template">Cancel</button>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get dashboard statistics
     */
    private function get_dashboard_stats() {
        global $wpdb;
        
        $stats = [
            'legacy_users' => 0,
            'active_campaigns' => 0,
            'emails_sent' => 0,
            'winback_rate' => 0
        ];
        
        // Get legacy users count
        $stats['legacy_users'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} 
             WHERE meta_key = '_is_legacy_user' AND meta_value = '1'"
        ) ?: 0;
        
        // Get active campaigns (check if table exists first)
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}mp_campaigns'");
        if ($table_exists) {
            $stats['active_campaigns'] = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}mp_campaigns 
                 WHERE status = 'active'"
            ) ?: 0;
            
            // Get emails sent this month
            $stats['emails_sent'] = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}mp_campaign_emails 
                 WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
            ) ?: 0;
            
            // Calculate win-back rate
            $converted = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}mp_campaign_conversions 
                 WHERE converted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
            ) ?: 0;
        } else {
            // Tables don't exist yet
            $stats['active_campaigns'] = 0;
            $stats['emails_sent'] = 0;
            $converted = 0;
        }
        
        if ($stats['emails_sent'] > 0) {
            $stats['winback_rate'] = round(($converted / $stats['emails_sent']) * 100, 1);
        }
        
        return $stats;
    }
    
    /**
     * Render recent activity table
     */
    private function render_recent_activity_table() {
        global $wpdb;
        
        $activities = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}mp_campaign_activity 
             ORDER BY created_at DESC LIMIT 10"
        );
        
        if (empty($activities)) {
            echo '<p>No recent activity</p>';
            return;
        }
        
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Campaign</th>
                    <th>Action</th>
                    <th>User</th>
                    <th>Result</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($activities as $activity): ?>
                <tr>
                    <td><?php echo human_time_diff(strtotime($activity->created_at)) . ' ago'; ?></td>
                    <td><?php echo esc_html($activity->campaign_name); ?></td>
                    <td><?php echo esc_html($activity->action); ?></td>
                    <td><?php echo esc_html($activity->user_email); ?></td>
                    <td>
                        <?php if ($activity->result === 'success'): ?>
                            <span class="mp-badge success">Success</span>
                        <?php elseif ($activity->result === 'pending'): ?>
                            <span class="mp-badge pending">Pending</span>
                        <?php else: ?>
                            <span class="mp-badge failed">Failed</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * AJAX Handlers Implementation
     */
    
    /**
     * Handle legacy users import via AJAX
     */
    public function ajax_import_legacy_users() {
        // Check nonce and capabilities
        if (!wp_verify_nonce($_POST['_ajax_nonce'], 'mp_campaigns_admin')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $import_type = sanitize_text_field($_POST['import_type'] ?? '');
        $manager = SFFC_MemberPress_Campaigns_Manager::get_instance();
        
        switch ($import_type) {
            case 'csv':
                $result = $this->handle_csv_import();
                break;
                
            case 'manual':
                $result = $this->handle_manual_import();
                break;
                
            case 'memberpress':
                $result = $this->handle_memberpress_import();
                break;
                
            default:
                wp_send_json_error('Invalid import type');
                return;
        }
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Mark user as legacy via AJAX
     */
    public function ajax_mark_user_as_legacy() {
        if (!wp_verify_nonce($_POST['_ajax_nonce'], 'mp_campaigns_admin')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        
        if ($user_id) {
            update_user_meta($user_id, '_is_legacy_user', '1');
            update_user_meta($user_id, '_legacy_tier', sanitize_text_field($_POST['tier'] ?? 'basic'));
            update_user_meta($user_id, '_legacy_price', sanitize_text_field($_POST['price'] ?? '29.99'));
            
            wp_send_json_success(['message' => 'User marked as legacy']);
        }
        
        wp_send_json_error('Invalid user ID');
    }
    
    /**
     * Create campaign via AJAX
     */
    public function ajax_create_campaign() {
        if (!wp_verify_nonce($_POST['_ajax_nonce'], 'mp_campaigns_admin')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        parse_str($_POST['data'] ?? '', $data);
        
        $manager = SFFC_MemberPress_Campaigns_Manager::get_instance();
        $campaign_id = $manager->create_campaign($data);
        
        if ($campaign_id) {
            wp_send_json_success(['campaign_id' => $campaign_id, 'message' => 'Campaign created successfully']);
        } else {
            wp_send_json_error('Failed to create campaign');
        }
    }
    
    /**
     * Update campaign via AJAX
     */
    public function ajax_update_campaign() {
        if (!wp_verify_nonce($_POST['_ajax_nonce'], 'mp_campaigns_admin')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $campaign_id = intval($_POST['campaign_id'] ?? 0);
        parse_str($_POST['data'] ?? '', $data);
        
        $manager = SFFC_MemberPress_Campaigns_Manager::get_instance();
        $result = $manager->update_campaign($campaign_id, $data);
        
        if ($result) {
            wp_send_json_success(['message' => 'Campaign updated successfully']);
        } else {
            wp_send_json_error('Failed to update campaign');
        }
    }
    
    /**
     * Delete campaign via AJAX
     */
    public function ajax_delete_campaign() {
        if (!wp_verify_nonce($_POST['_ajax_nonce'], 'mp_campaigns_admin')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        global $wpdb;
        
        $campaign_id = intval($_POST['campaign_id'] ?? 0);
        
        if ($campaign_id) {
            // Delete campaign and related data
            $wpdb->delete("{$wpdb->prefix}mp_campaigns", ['id' => $campaign_id]);
            $wpdb->delete("{$wpdb->prefix}mp_campaign_users", ['campaign_id' => $campaign_id]);
            $wpdb->delete("{$wpdb->prefix}mp_campaign_emails", ['campaign_id' => $campaign_id]);
            
            wp_send_json_success(['message' => 'Campaign deleted']);
        }
        
        wp_send_json_error('Invalid campaign ID');
    }
    
    /**
     * Test campaign email via AJAX
     */
    public function ajax_test_campaign_email() {
        if (!wp_verify_nonce($_POST['_ajax_nonce'], 'mp_campaigns_admin')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $email = sanitize_email($_POST['test_email'] ?? get_option('admin_email'));
        $template_id = intval($_POST['template_id'] ?? 0);
        
        // Send test email
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $subject = 'Test Campaign Email';
        $content = $this->get_test_email_content($template_id);
        
        if (wp_mail($email, $subject, $content, $headers)) {
            wp_send_json_success(['message' => 'Test email sent to ' . $email]);
        } else {
            wp_send_json_error('Failed to send test email');
        }
    }
    
    /**
     * Get campaign statistics via AJAX
     */
    public function ajax_get_campaign_stats() {
        if (!wp_verify_nonce($_POST['_ajax_nonce'], 'mp_campaigns_admin')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        global $wpdb;
        
        $period = sanitize_text_field($_POST['period'] ?? '30_days');
        $days = $period === '7_days' ? 7 : ($period === '90_days' ? 90 : 30);
        
        // Get stats for chart
        $stats = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(sent_at) as date,
                    COUNT(*) as sent,
                    SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opens,
                    SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicks
             FROM {$wpdb->prefix}mp_campaign_emails
             WHERE sent_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY DATE(sent_at)
             ORDER BY date ASC",
            $days
        ));
        
        // Format data for Chart.js
        $labels = [];
        $sent = [];
        $opens = [];
        $conversions = [];
        
        foreach ($stats as $stat) {
            $labels[] = date('M j', strtotime($stat->date));
            $sent[] = $stat->sent;
            $opens[] = $stat->opens;
            $conversions[] = $stat->clicks;
        }
        
        wp_send_json_success([
            'labels' => $labels,
            'sent' => $sent,
            'opens' => $opens,
            'conversions' => $conversions
        ]);
    }
    
    /**
     * Activate campaign via AJAX
     */
    public function ajax_activate_campaign() {
        if (!wp_verify_nonce($_POST['_ajax_nonce'], 'mp_campaigns_admin')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $campaign_id = intval($_POST['campaign_id'] ?? 0);
        
        if ($campaign_id) {
            $manager = SFFC_MemberPress_Campaigns_Manager::get_instance();
            $manager->activate_campaign($campaign_id);
            
            wp_send_json_success(['message' => 'Campaign activated']);
        }
        
        wp_send_json_error('Invalid campaign ID');
    }
    
    /**
     * Pause campaign via AJAX
     */
    public function ajax_pause_campaign() {
        if (!wp_verify_nonce($_POST['_ajax_nonce'], 'mp_campaigns_admin')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        global $wpdb;
        
        $campaign_id = intval($_POST['campaign_id'] ?? 0);
        
        if ($campaign_id) {
            $wpdb->update(
                "{$wpdb->prefix}mp_campaigns",
                ['status' => 'paused'],
                ['id' => $campaign_id]
            );
            
            wp_send_json_success(['message' => 'Campaign paused']);
        }
        
        wp_send_json_error('Invalid campaign ID');
    }
    
    /**
     * Preview email template via AJAX
     */
    public function ajax_preview_email_template() {
        if (!wp_verify_nonce($_POST['_ajax_nonce'], 'mp_campaigns_admin')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $template_id = intval($_POST['template_id'] ?? 0);
        $content = $this->get_template_preview($template_id);
        
        wp_send_json_success(['html' => $content]);
    }
    
    /**
     * Helper Methods
     */
    
    /**
     * Render legacy users table
     */
    private function render_legacy_users_table() {
        global $wpdb;
        
        $users = $wpdb->get_results(
            "SELECT u.ID, u.user_email, u.display_name, 
                    um1.meta_value as is_legacy,
                    um2.meta_value as legacy_tier,
                    um3.meta_value as legacy_price
             FROM {$wpdb->users} u
             LEFT JOIN {$wpdb->usermeta} um1 ON u.ID = um1.user_id AND um1.meta_key = '_is_legacy_user'
             LEFT JOIN {$wpdb->usermeta} um2 ON u.ID = um2.user_id AND um2.meta_key = '_legacy_tier'
             LEFT JOIN {$wpdb->usermeta} um3 ON u.ID = um3.user_id AND um3.meta_key = '_legacy_price'
             WHERE um1.meta_value = '1'
             LIMIT 100"
        );
        
        if (empty($users)) {
            echo '<p>No legacy users found. Use the import feature to add legacy users.</p>';
            return;
        }
        
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Email</th>
                    <th>Name</th>
                    <th>Original Tier</th>
                    <th>Original Price</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo esc_html($user->user_email); ?></td>
                    <td><?php echo esc_html($user->display_name); ?></td>
                    <td><?php echo esc_html($user->legacy_tier ?: 'N/A'); ?></td>
                    <td>$<?php echo esc_html($user->legacy_price ?: '0'); ?></td>
                    <td><span class="mp-badge success">Legacy</span></td>
                    <td>
                        <button class="button button-small mp-send-campaign" data-user-id="<?php echo $user->ID; ?>">
                            Send Campaign
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Render campaigns list
     */
    private function render_campaigns_list() {
        global $wpdb;
        
        $campaigns = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}mp_campaigns ORDER BY created_at DESC"
        );
        
        ?>
        <div class="wrap">
            <h1>
                Campaigns
                <a href="<?php echo admin_url('admin.php?page=mp-campaigns&action=new'); ?>" class="page-title-action">Add New</a>
            </h1>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Users</th>
                        <th>Emails Sent</th>
                        <th>Conversions</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($campaigns)): ?>
                        <tr>
                            <td colspan="8">No campaigns found. Create your first campaign!</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($campaigns as $campaign): ?>
                        <tr>
                            <td><?php echo esc_html($campaign->name); ?></td>
                            <td><?php echo esc_html($campaign->type); ?></td>
                            <td>
                                <span class="mp-badge <?php echo $campaign->status === 'active' ? 'success' : 'pending'; ?>">
                                    <?php echo esc_html($campaign->status); ?>
                                </span>
                            </td>
                            <td><?php echo $this->get_campaign_user_count($campaign->id); ?></td>
                            <td><?php echo $this->get_campaign_email_count($campaign->id); ?></td>
                            <td><?php echo $this->get_campaign_conversion_count($campaign->id); ?></td>
                            <td><?php echo human_time_diff(strtotime($campaign->created_at)) . ' ago'; ?></td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=mp-campaigns&action=edit&campaign_id=' . $campaign->id); ?>" 
                                   class="button button-small">Edit</a>
                                <?php if ($campaign->status !== 'active'): ?>
                                    <button class="button button-small mp-activate-campaign" data-campaign-id="<?php echo $campaign->id; ?>">
                                        Activate
                                    </button>
                                <?php else: ?>
                                    <button class="button button-small mp-pause-campaign" data-campaign-id="<?php echo $campaign->id; ?>">
                                        Pause
                                    </button>
                                <?php endif; ?>
                                <button class="button button-small mp-delete-campaign" data-campaign-id="<?php echo $campaign->id; ?>">
                                    Delete
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Render email templates
     */
    private function render_email_templates() {
        global $wpdb;
        
        $templates = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}mp_email_templates ORDER BY created_at DESC"
        );
        
        foreach ($templates as $template) {
            ?>
            <div class="mp-template-card" data-template-id="<?php echo $template->id; ?>">
                <div class="mp-template-preview">
                    <!-- Preview thumbnail -->
                </div>
                <div class="mp-template-info">
                    <h3 class="mp-template-name"><?php echo esc_html($template->name); ?></h3>
                    <p class="mp-template-description"><?php echo esc_html($template->type); ?></p>
                </div>
            </div>
            <?php
        }
    }
    
    /**
     * Render analytics page
     */
    public function render_analytics_page() {
        ?>
        <div class="wrap mp-analytics">
            <h1>Campaign Analytics</h1>
            
            <div class="mp-analytics-controls">
                <select id="mp-analytics-range">
                    <option value="7_days">Last 7 Days</option>
                    <option value="30_days" selected>Last 30 Days</option>
                    <option value="90_days">Last 90 Days</option>
                </select>
                
                <button id="mp-export-analytics" class="button">Export Data</button>
            </div>
            
            <div class="mp-stats-grid">
                <?php
                $stats = $this->get_dashboard_stats();
                ?>
                <div class="mp-stat-card">
                    <h3>Total Campaigns</h3>
                    <div class="mp-stat-number"><?php echo $stats['active_campaigns']; ?></div>
                </div>
                <div class="mp-stat-card">
                    <h3>Emails Sent</h3>
                    <div class="mp-stat-number"><?php echo number_format($stats['emails_sent']); ?></div>
                </div>
                <div class="mp-stat-card">
                    <h3>Win-back Rate</h3>
                    <div class="mp-stat-number"><?php echo $stats['winback_rate']; ?>%</div>
                </div>
            </div>
            
            <div class="mp-chart-container">
                <canvas id="analyticsChart"></canvas>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Campaign Settings</h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('mp_campaigns_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th><label for="mp_email_from_name">From Name</label></th>
                        <td>
                            <input type="text" id="mp_email_from_name" name="mp_email_from_name" 
                                   value="<?php echo get_option('mp_email_from_name', get_bloginfo('name')); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="mp_email_from_address">From Email</label></th>
                        <td>
                            <input type="email" id="mp_email_from_address" name="mp_email_from_address" 
                                   value="<?php echo get_option('mp_email_from_address', get_option('admin_email')); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="mp_emails_per_batch">Emails Per Batch</label></th>
                        <td>
                            <input type="number" id="mp_emails_per_batch" name="mp_emails_per_batch" 
                                   value="<?php echo get_option('mp_emails_per_batch', 50); ?>" 
                                   min="1" max="500">
                            <p class="description">Number of emails to send per batch (every 5 minutes)</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Helper methods for imports
     */
    
    private function handle_csv_import() {
        // Handle CSV file upload
        if (!isset($_FILES['legacy_users_csv'])) {
            return ['success' => false, 'message' => 'No file uploaded'];
        }
        
        $file = $_FILES['legacy_users_csv'];
        $csv_data = array_map('str_getcsv', file($file['tmp_name']));
        
        $users_data = [];
        foreach ($csv_data as $row) {
            if (count($row) >= 2) {
                $users_data[] = [
                    'email' => $row[0],
                    'name' => $row[1] ?? '',
                    'tier' => $row[2] ?? 'basic',
                    'price' => $row[3] ?? '29.99'
                ];
            }
        }
        
        $manager = SFFC_MemberPress_Campaigns_Manager::get_instance();
        $imported = $manager->import_legacy_users($users_data);
        
        return [
            'success' => true,
            'message' => "Successfully imported {$imported} users",
            'imported' => $imported
        ];
    }
    
    private function handle_manual_import() {
        parse_str($_POST['data'] ?? '', $data);
        
        $emails = explode("\n", $data['legacy_users_list'] ?? '');
        $users_data = [];
        
        foreach ($emails as $email) {
            $email = trim($email);
            if (is_email($email)) {
                $users_data[] = [
                    'email' => $email,
                    'tier' => $data['default_tier'] ?? 'basic',
                    'price' => $data['default_price'] ?? '29.99'
                ];
            }
        }
        
        $manager = SFFC_MemberPress_Campaigns_Manager::get_instance();
        $imported = $manager->import_legacy_users($users_data, [
            'tier' => $data['default_tier'] ?? 'basic',
            'price' => $data['default_price'] ?? '29.99'
        ]);
        
        return [
            'success' => true,
            'message' => "Successfully imported {$imported} users",
            'imported' => $imported
        ];
    }
    
    private function handle_memberpress_import() {
        if (!class_exists('MeprSubscription')) {
            return ['success' => false, 'message' => 'MemberPress not active'];
        }
        
        global $wpdb;
        
        parse_str($_POST['data'] ?? '', $data);
        
        $query = "SELECT DISTINCT u.ID, u.user_email, u.display_name
                  FROM {$wpdb->users} u
                  INNER JOIN {$wpdb->prefix}mepr_subscriptions s ON u.ID = s.user_id
                  WHERE s.status IN ('expired', 'cancelled')";
        
        if (!empty($data['from_date'])) {
            $query .= $wpdb->prepare(" AND s.created_at >= %s", $data['from_date']);
        }
        
        if (!empty($data['to_date'])) {
            $query .= $wpdb->prepare(" AND s.created_at <= %s", $data['to_date']);
        }
        
        $users = $wpdb->get_results($query);
        
        $formatted_users = [];
        foreach ($users as $user) {
            $formatted_users[] = [
                'email' => $user->user_email,
                'name' => $user->display_name,
                'user_id' => $user->ID
            ];
        }
        
        return [
            'success' => true,
            'count' => count($formatted_users),
            'users' => $formatted_users
        ];
    }
    
    private function get_campaign($campaign_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mp_campaigns WHERE id = %d",
            $campaign_id
        ));
    }
    
    private function get_campaign_user_count($campaign_id) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}mp_campaign_users WHERE campaign_id = %d",
            $campaign_id
        ));
    }
    
    private function get_campaign_email_count($campaign_id) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}mp_campaign_emails WHERE campaign_id = %d AND status = 'sent'",
            $campaign_id
        ));
    }
    
    private function get_campaign_conversion_count($campaign_id) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}mp_campaign_conversions WHERE campaign_id = %d",
            $campaign_id
        ));
    }
    
    private function get_test_email_content($template_id) {
        global $wpdb;
        
        if ($template_id) {
            $template = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}mp_email_templates WHERE id = %d",
                $template_id
            ));
            
            if ($template) {
                return $template->html_content;
            }
        }
        
        // Return default test content
        return '<h1>Test Campaign Email</h1><p>This is a test email from your campaign system.</p>';
    }
    
    private function get_template_preview($template_id) {
        global $wpdb;
        
        $template = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mp_email_templates WHERE id = %d",
            $template_id
        ));
        
        if ($template) {
            // Replace variables with sample data
            $html = $template->html_content;
            $html = str_replace('{{name}}', 'John Doe', $html);
            $html = str_replace('{{site_name}}', get_bloginfo('name'), $html);
            $html = str_replace('{{discount}}', '50', $html);
            
            return $html;
        }
        
        return '<p>Template not found</p>';
    }
}

// Don't initialize here - let the init class handle it