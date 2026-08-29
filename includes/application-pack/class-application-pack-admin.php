<?php
/**
 * Application Pack Admin Settings
 *
 * Admin interface for configuring Application Pack settings and MemberPress integration.
 *
 * @package SFFC_Careers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Application_Pack_Admin {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Option keys
     */
    const OPTION_ENABLED = 'sffc_app_pack_enabled';
    const OPTION_RESTRICT_ACCESS = 'sffc_app_pack_restrict_access';
    const OPTION_PRODUCT_IDS = 'sffc_app_pack_product_ids';
    const OPTION_UPGRADE_URL = 'sffc_app_pack_upgrade_url';
    const OPTION_FREE_CREDITS = 'sffc_app_pack_free_credits';
    const OPTION_CREDIT_SYSTEM = 'sffc_app_pack_credit_system';

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
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_sffc_save_tier_config', array($this, 'ajax_save_tier_config'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'sffc-dashboard', // Parent slug - SF Finance main menu
            'Application Pack',
            'Application Pack',
            'manage_options',
            'sffc-application-pack',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // General Settings Section
        add_settings_section(
            'sffc_app_pack_general',
            'General Settings',
            array($this, 'render_general_section'),
            'sffc-application-pack'
        );

        // Enable/Disable
        register_setting('sffc_app_pack_settings', self::OPTION_ENABLED);
        add_settings_field(
            self::OPTION_ENABLED,
            'Enable Application Pack',
            array($this, 'render_enabled_field'),
            'sffc-application-pack',
            'sffc_app_pack_general'
        );

        // Access Control Section
        add_settings_section(
            'sffc_app_pack_access',
            'Access Control',
            array($this, 'render_access_section'),
            'sffc-application-pack'
        );

        // Restrict Access Toggle
        register_setting('sffc_app_pack_settings', self::OPTION_RESTRICT_ACCESS);
        add_settings_field(
            self::OPTION_RESTRICT_ACCESS,
            'Restrict to Members',
            array($this, 'render_restrict_field'),
            'sffc-application-pack',
            'sffc_app_pack_access'
        );

        // MemberPress Products
        register_setting('sffc_app_pack_settings', self::OPTION_PRODUCT_IDS);
        add_settings_field(
            self::OPTION_PRODUCT_IDS,
            'Allowed Memberships',
            array($this, 'render_products_field'),
            'sffc-application-pack',
            'sffc_app_pack_access'
        );

        // Upgrade URL
        register_setting('sffc_app_pack_settings', self::OPTION_UPGRADE_URL);
        add_settings_field(
            self::OPTION_UPGRADE_URL,
            'Upgrade Page URL',
            array($this, 'render_upgrade_url_field'),
            'sffc-application-pack',
            'sffc_app_pack_access'
        );

        // Credit System Section
        add_settings_section(
            'sffc_app_pack_credits',
            'Credit System',
            array($this, 'render_credits_section'),
            'sffc-application-pack'
        );

        // Enable Credit System
        register_setting('sffc_app_pack_settings', self::OPTION_CREDIT_SYSTEM);
        add_settings_field(
            self::OPTION_CREDIT_SYSTEM,
            'Enable Credit System',
            array($this, 'render_credit_system_field'),
            'sffc-application-pack',
            'sffc_app_pack_credits'
        );

        // Free Credits for Members
        register_setting('sffc_app_pack_settings', self::OPTION_FREE_CREDITS);
        add_settings_field(
            self::OPTION_FREE_CREDITS,
            'Monthly Credits (Members)',
            array($this, 'render_free_credits_field'),
            'sffc-application-pack',
            'sffc_app_pack_credits'
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'sffc-application-pack') === false) {
            return;
        }

        wp_enqueue_style(
            'sffc-app-pack-admin',
            plugins_url('assets/css/application-pack-admin.css', dirname(dirname(__FILE__))),
            array(),
            '1.0.0'
        );
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Check for save
        if (isset($_GET['settings-updated'])) {
            add_settings_error('sffc_app_pack_messages', 'sffc_app_pack_message', 'Settings Saved', 'updated');
        }

        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';

        settings_errors('sffc_app_pack_messages');
        ?>
        <div class="wrap sffc-app-pack-admin">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="sffc-app-pack-header">
                <div class="sffc-app-pack-status">
                    <?php $enabled = get_option(self::OPTION_ENABLED, '1'); ?>
                    <span class="status-indicator <?php echo $enabled ? 'active' : 'inactive'; ?>"></span>
                    <span class="status-text"><?php echo $enabled ? 'Active' : 'Disabled'; ?></span>
                </div>
            </div>

            <nav class="nav-tab-wrapper">
                <a href="?page=sffc-application-pack&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">General</a>
                <a href="?page=sffc-application-pack&tab=tiers" class="nav-tab <?php echo $active_tab === 'tiers' ? 'nav-tab-active' : ''; ?>">Membership Tiers</a>
                <a href="?page=sffc-application-pack&tab=stats" class="nav-tab <?php echo $active_tab === 'stats' ? 'nav-tab-active' : ''; ?>">Statistics</a>
            </nav>

            <div class="sffc-app-pack-tab-content">
                <?php
                switch ($active_tab) {
                    case 'tiers':
                        $this->render_tiers_tab();
                        break;
                    case 'stats':
                        $this->render_stats_tab();
                        break;
                    default:
                        $this->render_general_tab();
                        break;
                }
                ?>
            </div>
        </div>

        <style>
            .sffc-app-pack-admin { max-width: 800px; }
            .sffc-app-pack-header {
                background: #fff;
                padding: 20px;
                border: 1px solid #ccd0d4;
                margin-bottom: 20px;
                border-radius: 4px;
            }
            .sffc-app-pack-status { display: flex; align-items: center; gap: 10px; }
            .status-indicator {
                width: 12px;
                height: 12px;
                border-radius: 50%;
                background: #dc3545;
            }
            .status-indicator.active { background: #28a745; }
            .status-text { font-weight: 600; font-size: 14px; }
            .sffc-app-pack-stats {
                background: #fff;
                padding: 20px;
                border: 1px solid #ccd0d4;
                margin-top: 30px;
                border-radius: 4px;
            }
            .sffc-app-pack-stats h2 { margin-top: 0; }
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 20px;
                margin-top: 15px;
            }
            .stat-box {
                background: #f8f9fa;
                padding: 20px;
                text-align: center;
                border-radius: 4px;
            }
            .stat-value {
                font-size: 28px;
                font-weight: 700;
                color: #1a365d;
            }
            .stat-label {
                font-size: 12px;
                color: #666;
                text-transform: uppercase;
                margin-top: 5px;
            }
            .form-table th { width: 200px; }
            .description { color: #666; font-style: italic; }
            .membership-checkboxes {
                display: flex;
                flex-direction: column;
                gap: 8px;
                max-height: 200px;
                overflow-y: auto;
                padding: 10px;
                background: #f9f9f9;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            .membership-checkboxes label {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 5px;
                cursor: pointer;
            }
            .membership-checkboxes label:hover { background: #eee; }
            .no-memberpress {
                padding: 15px;
                background: #fff3cd;
                border: 1px solid #ffc107;
                border-radius: 4px;
                color: #856404;
            }
        </style>
        <?php
    }

    /**
     * Render general section
     */
    public function render_general_section() {
        echo '<p>Configure the Application Pack feature for your job listings.</p>';
    }

    /**
     * Render access section
     */
    public function render_access_section() {
        echo '<p>Control who can access Application Pack. When restrictions are enabled, only users with the selected MemberPress memberships can generate documents.</p>';
    }

    /**
     * Render credits section
     */
    public function render_credits_section() {
        echo '<p>Configure the credit system for Application Pack usage. Credits can limit how many documents users can generate.</p>';
    }

    /**
     * Render enabled field
     */
    public function render_enabled_field() {
        $value = get_option(self::OPTION_ENABLED, '1');
        ?>
        <label>
            <input type="checkbox" name="<?php echo self::OPTION_ENABLED; ?>" value="1" <?php checked($value, '1'); ?>>
            Enable Application Pack feature across the site
        </label>
        <p class="description">When disabled, the Application Pack button will not appear on job listings.</p>
        <?php
    }

    /**
     * Render restrict access field
     */
    public function render_restrict_field() {
        $value = get_option(self::OPTION_RESTRICT_ACCESS, '0');
        ?>
        <label>
            <input type="checkbox" name="<?php echo self::OPTION_RESTRICT_ACCESS; ?>" value="1" <?php checked($value, '1'); ?> id="restrict-access-toggle">
            Require membership to access Application Pack
        </label>
        <p class="description">When enabled, only users with selected memberships can generate documents. Others will see an upgrade prompt.</p>
        <?php
    }

    /**
     * Render products field
     */
    public function render_products_field() {
        $selected = get_option(self::OPTION_PRODUCT_IDS, array());
        if (!is_array($selected)) {
            $selected = array();
        }

        if (!class_exists('MeprProduct')) {
            echo '<div class="no-memberpress">MemberPress is not active. Install and activate MemberPress to use membership restrictions.</div>';
            return;
        }

        // Get all MemberPress products
        $products = \MeprProduct::get_all();

        if (empty($products)) {
            echo '<p>No MemberPress memberships found. <a href="' . admin_url('post-new.php?post_type=memberpressproduct') . '">Create a membership</a>.</p>';
            return;
        }

        echo '<div class="membership-checkboxes">';
        foreach ($products as $product) {
            $checked = in_array($product->ID, $selected) ? 'checked' : '';
            printf(
                '<label><input type="checkbox" name="%s[]" value="%d" %s> %s <span style="color:#999;">(%s)</span></label>',
                self::OPTION_PRODUCT_IDS,
                $product->ID,
                $checked,
                esc_html($product->post_title),
                esc_html($this->format_price($product))
            );
        }
        echo '</div>';
        echo '<p class="description">Users with any of these memberships will have access to Application Pack.</p>';
    }

    /**
     * Format product price
     */
    private function format_price($product) {
        if (class_exists('MeprAppHelper')) {
            return \MeprAppHelper::format_currency((float) $product->price);
        }
        return '$' . number_format((float) $product->price, 2);
    }

    /**
     * Render upgrade URL field
     */
    public function render_upgrade_url_field() {
        $value = get_option(self::OPTION_UPGRADE_URL, '/membership/');
        ?>
        <input type="text" name="<?php echo self::OPTION_UPGRADE_URL; ?>" value="<?php echo esc_attr($value); ?>" class="regular-text">
        <p class="description">URL where users are directed to upgrade their membership. Can be a MemberPress pricing page or custom page.</p>
        <?php
    }

    /**
     * Render credit system field
     */
    public function render_credit_system_field() {
        $value = get_option(self::OPTION_CREDIT_SYSTEM, '0');
        ?>
        <label>
            <input type="checkbox" name="<?php echo self::OPTION_CREDIT_SYSTEM; ?>" value="1" <?php checked($value, '1'); ?>>
            Enable credit-based usage limits
        </label>
        <p class="description">When enabled, users will have a limited number of credits to generate documents. Disable for unlimited access.</p>
        <?php
    }

    /**
     * Render free credits field
     */
    public function render_free_credits_field() {
        $value = get_option(self::OPTION_FREE_CREDITS, '10');
        ?>
        <input type="number" name="<?php echo self::OPTION_FREE_CREDITS; ?>" value="<?php echo esc_attr($value); ?>" min="0" max="999" style="width: 80px;">
        <span>credits per month</span>
        <p class="description">Number of credits members receive each month. Each CV costs 1 credit, full pack costs 3 credits.</p>
        <?php
    }

    /**
     * Render usage statistics
     */
    private function render_stats() {
        global $wpdb;
        $table = $wpdb->prefix . 'sffc_application_packs';

        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;

        if (!$table_exists) {
            echo '<p>No usage data yet. Statistics will appear here once users start generating documents.</p>';
            return;
        }

        // Get stats
        $total_generated = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $total_downloads = $wpdb->get_var("SELECT SUM(download_count) FROM $table");
        $unique_users = $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM $table");
        $this_month = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE created_at >= %s",
            date('Y-m-01 00:00:00')
        ));

        ?>
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-value"><?php echo number_format($total_generated ?: 0); ?></div>
                <div class="stat-label">Total Generated</div>
            </div>
            <div class="stat-box">
                <div class="stat-value"><?php echo number_format($total_downloads ?: 0); ?></div>
                <div class="stat-label">Total Downloads</div>
            </div>
            <div class="stat-box">
                <div class="stat-value"><?php echo number_format($unique_users ?: 0); ?></div>
                <div class="stat-label">Unique Users</div>
            </div>
            <div class="stat-box">
                <div class="stat-value"><?php echo number_format($this_month ?: 0); ?></div>
                <div class="stat-label">This Month</div>
            </div>
        </div>
        <?php
    }

    /**
     * Render general tab
     */
    private function render_general_tab() {
        ?>
        <form action="options.php" method="post">
            <?php
            settings_fields('sffc_app_pack_settings');
            do_settings_sections('sffc-application-pack');
            submit_button('Save Settings');
            ?>
        </form>
        <?php
    }

    /**
     * Render stats tab
     */
    private function render_stats_tab() {
        ?>
        <div class="sffc-app-pack-stats">
            <h2>Usage Statistics</h2>
            <?php $this->render_stats(); ?>
        </div>
        <?php
    }

    /**
     * Render tiers tab
     */
    private function render_tiers_tab() {
        $tiers_manager = SFFC_Application_Pack_Tiers::get_instance();
        $tiers = $tiers_manager->get_tiers();
        $documents = $tiers_manager->get_documents();
        $memberpress_products = $tiers_manager->get_memberpress_products();
        $upgrade_url = $tiers_manager->get_upgrade_url();
        ?>
        <div class="sffc-tiers-config">
            <p class="description" style="margin-bottom: 20px;">
                Configure which documents are available in each membership tier and map them to your MemberPress products.
            </p>

            <div class="sffc-tiers-grid">
                <?php foreach ($tiers as $tier_id => $tier): ?>
                <div class="sffc-tier-card" data-tier="<?php echo esc_attr($tier_id); ?>">
                    <div class="sffc-tier-header" style="background: <?php echo esc_attr($tier['bg_color']); ?>; border-left: 4px solid <?php echo esc_attr($tier['color']); ?>;">
                        <h3 style="color: <?php echo esc_attr($tier['color']); ?>;"><?php echo esc_html($tier['name']); ?></h3>
                        <span class="sffc-tier-badge" style="background: <?php echo esc_attr($tier['color']); ?>;">Tier <?php echo esc_html($tier['order']); ?></span>
                    </div>

                    <div class="sffc-tier-body">
                        <div class="sffc-tier-section">
                            <h4>Documents Included</h4>
                            <div class="sffc-document-checkboxes">
                                <?php foreach ($documents as $doc_id => $doc): ?>
                                <label>
                                    <input type="checkbox"
                                           name="tier_documents[<?php echo esc_attr($tier_id); ?>][]"
                                           value="<?php echo esc_attr($doc_id); ?>"
                                           <?php checked(in_array($doc_id, $tier['documents'] ?? array())); ?>>
                                    <?php echo esc_html($doc['name']); ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="sffc-tier-section">
                            <h4>MemberPress Products</h4>
                            <?php if (empty($memberpress_products)): ?>
                            <p class="sffc-notice">
                                <?php if (!class_exists('MeprProduct')): ?>
                                MemberPress is not active.
                                <?php else: ?>
                                No MemberPress products found.
                                <?php endif; ?>
                            </p>
                            <?php else: ?>
                            <div class="sffc-product-checkboxes">
                                <?php foreach ($memberpress_products as $product): ?>
                                <label>
                                    <input type="checkbox"
                                           name="tier_products[<?php echo esc_attr($tier_id); ?>][]"
                                           value="<?php echo esc_attr($product['id']); ?>"
                                           <?php checked(in_array($product['id'], $tier['memberpress_products'] ?? array())); ?>>
                                    <?php echo esc_html($product['title']); ?>
                                    <span class="sffc-product-price">$<?php echo number_format($product['price'], 2); ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="sffc-upgrade-url-section">
                <h3>Upgrade URL</h3>
                <p class="description">Where users are directed when they need to upgrade their membership.</p>
                <input type="text" id="sffc_upgrade_url" value="<?php echo esc_attr($upgrade_url); ?>" class="regular-text">
            </div>

            <div class="sffc-tier-actions">
                <button type="button" id="sffc-save-tiers" class="button button-primary">Save Tier Configuration</button>
                <span class="sffc-save-status"></span>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#sffc-save-tiers').on('click', function() {
                var $btn = $(this);
                var $status = $('.sffc-save-status');

                $btn.prop('disabled', true).text('Saving...');

                var tierDocuments = {};
                var tierProducts = {};

                $('input[name^="tier_documents"]').each(function() {
                    var match = this.name.match(/tier_documents\[(\w+)\]/);
                    if (match) {
                        var tier = match[1];
                        if (!tierDocuments[tier]) tierDocuments[tier] = [];
                        if (this.checked) tierDocuments[tier].push(this.value);
                    }
                });

                $('input[name^="tier_products"]').each(function() {
                    var match = this.name.match(/tier_products\[(\w+)\]/);
                    if (match) {
                        var tier = match[1];
                        if (!tierProducts[tier]) tierProducts[tier] = [];
                        if (this.checked) tierProducts[tier].push(parseInt(this.value));
                    }
                });

                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'sffc_save_tier_config',
                        nonce: '<?php echo wp_create_nonce('sffc_save_tier_config'); ?>',
                        tier_documents: tierDocuments,
                        tier_products: tierProducts,
                        upgrade_url: $('#sffc_upgrade_url').val()
                    },
                    success: function(response) {
                        $btn.prop('disabled', false).text('Save Tier Configuration');
                        if (response.success) {
                            $status.html('<span style="color: #059669;">Saved successfully!</span>').fadeIn().delay(2000).fadeOut();
                        } else {
                            $status.html('<span style="color: #dc2626;">Error: ' + response.data + '</span>').fadeIn();
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).text('Save Tier Configuration');
                        $status.html('<span style="color: #dc2626;">Network error. Please try again.</span>').fadeIn();
                    }
                });
            });
        });
        </script>

        <style>
            .sffc-tiers-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 20px;
                margin-bottom: 30px;
            }
            .sffc-tier-card {
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 8px;
                overflow: hidden;
            }
            .sffc-tier-header {
                padding: 16px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .sffc-tier-header h3 {
                margin: 0;
                font-size: 16px;
                font-weight: 600;
            }
            .sffc-tier-badge {
                padding: 4px 10px;
                border-radius: 12px;
                font-size: 11px;
                font-weight: 600;
                color: #fff;
            }
            .sffc-tier-body {
                padding: 16px;
            }
            .sffc-tier-section {
                margin-bottom: 20px;
            }
            .sffc-tier-section:last-child {
                margin-bottom: 0;
            }
            .sffc-tier-section h4 {
                margin: 0 0 10px;
                font-size: 13px;
                font-weight: 600;
                color: #374151;
            }
            .sffc-document-checkboxes,
            .sffc-product-checkboxes {
                display: flex;
                flex-direction: column;
                gap: 8px;
            }
            .sffc-document-checkboxes label,
            .sffc-product-checkboxes label {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 13px;
                cursor: pointer;
            }
            .sffc-product-price {
                color: #6b7280;
                font-size: 12px;
                margin-left: auto;
            }
            .sffc-notice {
                color: #6b7280;
                font-size: 13px;
                font-style: italic;
            }
            .sffc-upgrade-url-section {
                background: #fff;
                padding: 20px;
                border: 1px solid #ddd;
                border-radius: 8px;
                margin-bottom: 20px;
            }
            .sffc-upgrade-url-section h3 {
                margin: 0 0 8px;
                font-size: 14px;
            }
            .sffc-upgrade-url-section .description {
                margin: 0 0 12px;
            }
            .sffc-tier-actions {
                display: flex;
                align-items: center;
                gap: 15px;
            }
            .sffc-save-status {
                font-size: 13px;
            }
            @media (max-width: 1200px) {
                .sffc-tiers-grid {
                    grid-template-columns: 1fr;
                }
            }
        </style>
        <?php
    }

    /**
     * AJAX handler for saving tier configuration
     */
    public function ajax_save_tier_config() {
        check_ajax_referer('sffc_save_tier_config', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $tiers_manager = SFFC_Application_Pack_Tiers::get_instance();
        $tiers = $tiers_manager->get_tiers();

        // Update tier documents
        $tier_documents = isset($_POST['tier_documents']) ? $_POST['tier_documents'] : array();
        foreach ($tier_documents as $tier_id => $docs) {
            $tier_id = sanitize_key($tier_id);
            if (isset($tiers[$tier_id])) {
                $tiers[$tier_id]['documents'] = array_map('sanitize_key', $docs);
            }
        }

        // Ensure all tiers have documents array (even if empty)
        foreach ($tiers as $tier_id => $tier) {
            if (!isset($tier_documents[$tier_id])) {
                $tiers[$tier_id]['documents'] = array();
            }
        }

        // Update tier products
        $tier_products = isset($_POST['tier_products']) ? $_POST['tier_products'] : array();
        foreach ($tier_products as $tier_id => $products) {
            $tier_id = sanitize_key($tier_id);
            if (isset($tiers[$tier_id])) {
                $tiers[$tier_id]['memberpress_products'] = array_map('intval', $products);
            }
        }

        // Ensure all tiers have products array (even if empty)
        foreach ($tiers as $tier_id => $tier) {
            if (!isset($tier_products[$tier_id])) {
                $tiers[$tier_id]['memberpress_products'] = array();
            }
        }

        // Save tiers
        $tiers_manager->save_tiers($tiers);

        // Save upgrade URL
        if (isset($_POST['upgrade_url'])) {
            $tiers_manager->save_upgrade_url($_POST['upgrade_url']);
        }

        wp_send_json_success('Configuration saved');
    }

    /**
     * Static helper: Check if Application Pack is enabled
     */
    public static function is_enabled() {
        return get_option(self::OPTION_ENABLED, '1') === '1';
    }

    /**
     * Static helper: Check if access is restricted
     */
    public static function is_restricted() {
        return get_option(self::OPTION_RESTRICT_ACCESS, '0') === '1';
    }

    /**
     * Static helper: Get allowed product IDs
     */
    public static function get_allowed_products() {
        $products = get_option(self::OPTION_PRODUCT_IDS, array());
        return is_array($products) ? array_map('intval', $products) : array();
    }

    /**
     * Static helper: Get upgrade URL
     */
    public static function get_upgrade_url() {
        return get_option(self::OPTION_UPGRADE_URL, '/membership/');
    }

    /**
     * Static helper: Is credit system enabled
     */
    public static function is_credit_system_enabled() {
        return get_option(self::OPTION_CREDIT_SYSTEM, '0') === '1';
    }

    /**
     * Static helper: Get monthly credits
     */
    public static function get_monthly_credits() {
        return (int) get_option(self::OPTION_FREE_CREDITS, 10);
    }
}

// Initialize
add_action('plugins_loaded', function() {
    if (is_admin()) {
        SFFC_Application_Pack_Admin::get_instance();
    }
});
