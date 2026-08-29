<?php

/**
 * Admin Settings Class
 * 
 * @package SennaCareers
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Admin_Settings
{

    /**
     * Constructor
     */
    public function __construct()
    {
        // No initialization needed here
    }

    /**
     * Render dashboard page
     */
    public function render_dashboard()
    {
?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="sffc-admin-dashboard">
                <!-- Statistics Cards -->
                <div class="sffc-stats-grid">
                    <div class="sffc-stat-card">
                        <div class="sffc-stat-icon">💬</div>
                        <div class="sffc-stat-content">
                            <h3><?php esc_html_e('Total Conversations', 'senna-finance'); ?></h3>
                            <p class="sffc-stat-number"><?php echo $this->get_total_conversations(); ?></p>
                        </div>
                    </div>

                    <div class="sffc-stat-card">
                        <div class="sffc-stat-icon">👥</div>
                        <div class="sffc-stat-content">
                            <h3><?php esc_html_e('Active Users', 'senna-finance'); ?></h3>
                            <p class="sffc-stat-number"><?php echo $this->get_active_users(); ?></p>
                        </div>
                    </div>

                    <div class="sffc-stat-card">
                        <div class="sffc-stat-icon">📊</div>
                        <div class="sffc-stat-content">
                            <h3><?php esc_html_e('Messages Today', 'senna-finance'); ?></h3>
                            <p class="sffc-stat-number"><?php echo $this->get_messages_today(); ?></p>
                        </div>
                    </div>

                    <div class="sffc-stat-card">
                        <div class="sffc-stat-icon">⚡</div>
                        <div class="sffc-stat-content">
                            <h3><?php esc_html_e('API Status', 'senna-finance'); ?></h3>
                            <p class="sffc-stat-status"><?php echo $this->get_api_status(); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Database Tables Section -->
                <div class="sffc-database-section">
                    <h2><?php esc_html_e('Database Tables', 'senna-finance'); ?></h2>
                    <div class="sffc-database-info">
                        <p><?php esc_html_e('Create or verify the required database tables for the plugin.', 'senna-finance'); ?></p>
                        <button class="button button-primary" id="sffc-create-tables">
                            <?php esc_html_e('Create/Update Database Tables', 'senna-finance'); ?>
                        </button>
                        <div id="sffc-database-status" style="margin-top: 10px;"></div>
                    </div>

                    <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Table Name', 'senna-finance'); ?></th>
                                <th><?php esc_html_e('Status', 'senna-finance'); ?></th>
                                <th><?php esc_html_e('Records', 'senna-finance'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $this->render_table_status(); ?>
                        </tbody>
                    </table>
                </div>

                <!-- Prep Materials Content Generation -->
                <div class="sffc-prep-content-section">
                    <h2><?php esc_html_e('Premium Content Generation', 'senna-finance'); ?></h2>
                    <div class="sffc-prep-content-info">
                        <p><?php esc_html_e('Generate premium prep materials including case studies, interview questions, financial terms, and day-in-life guides.', 'senna-finance'); ?></p>
                        <div class="sffc-content-stats">
                            <ul>
                                <li>✓ 10 Detailed Case Studies (Microsoft-Activision, Apollo-Tegna, etc.)</li>
                                <li>✓ 30 Comprehensive Interview Questions with Answers</li>
                                <li>✓ 40 Financial Terms Explained (PE, IB, AM terminology)</li>
                                <li>✓ 5 Financial Modeling Guides</li>
                                <li>✓ 18 Day-in-Life Role Guides</li>
                            </ul>
                        </div>
                        <button class="button button-primary button-hero" id="sffc-generate-all-content">
                            <span class="dashicons dashicons-admin-page" style="margin-top: 4px;"></span>
                            <?php esc_html_e('Generate All Premium Content', 'senna-finance'); ?>
                        </button>
                        <div id="sffc-content-generation-status" style="margin-top: 15px; display: none;">
                            <div class="sffc-progress-bar">
                                <div class="sffc-progress-fill"></div>
                            </div>
                            <p class="sffc-status-message"></p>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="sffc-quick-actions">
                    <h2><?php esc_html_e('Quick Actions', 'senna-finance'); ?></h2>
                    <div class="sffc-actions-grid">
                        <a href="<?php echo admin_url('admin.php?page=sffc-database'); ?>" class="sffc-action-button">
                            <span class="dashicons dashicons-database"></span>
                            <?php esc_html_e('Manage Database', 'senna-finance'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=sffc-settings'); ?>" class="sffc-action-button">
                            <span class="dashicons dashicons-admin-settings"></span>
                            <?php esc_html_e('Plugin Settings', 'senna-finance'); ?>
                        </a>
                        <button class="sffc-action-button" id="sffc-clear-cache">
                            <span class="dashicons dashicons-trash"></span>
                            <?php esc_html_e('Clear Cache', 'senna-finance'); ?>
                        </button>
                        <button class="sffc-action-button" id="sffc-export-data">
                            <span class="dashicons dashicons-download"></span>
                            <?php esc_html_e('Export Data', 'senna-finance'); ?>
                        </button>
                    </div>
                </div>

                <!-- Recent Conversations -->
                <div class="sffc-recent-conversations">
                    <h2><?php esc_html_e('Recent Conversations', 'senna-finance'); ?></h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('User', 'senna-finance'); ?></th>
                                <th><?php esc_html_e('Mode', 'senna-finance'); ?></th>
                                <th><?php esc_html_e('Messages', 'senna-finance'); ?></th>
                                <th><?php esc_html_e('Started', 'senna-finance'); ?></th>
                                <th><?php esc_html_e('Status', 'senna-finance'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php echo $this->get_recent_conversations_rows(); ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php
    }

    /**
     * Render settings page
     */
    public function render_settings()
    {
        $news_feed_enabled = (bool) apply_filters('sffc_pe_news_feed_enabled', false);

        // Handle European feeds installation
        if ($news_feed_enabled && isset($_POST['sffc_install_european_feeds']) && check_admin_referer('sffc_settings_nonce')) {
            require_once SFFC_PLUGIN_DIR . 'admin/class-european-feeds-installer.php';
            $result = SFFC_European_Feeds_Installer::install_feeds();

            if ($result['success']) {
                echo '<div class="notice notice-success"><p>';
                echo sprintf(
                    esc_html__('Successfully installed European feeds! Added: %d, Skipped: %d', 'senna-finance'),
                    $result['added'],
                    $result['skipped']
                );
                echo '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html($result['message']) . '</p></div>';
            }
        }

        // Handle Private Equity feeds installation
        if ($news_feed_enabled && isset($_POST['sffc_install_pe_feeds']) && check_admin_referer('sffc_settings_nonce')) {
            require_once SFFC_PLUGIN_DIR . 'admin/class-european-pe-feeds-installer.php';
            $result = SFFC_European_PE_Feeds_Installer::install_feeds();

            if ($result['success']) {
                echo '<div class="notice notice-success" style="border-left-color: #7c3aed;"><p>';
                echo '<strong>💼 Private Equity Feeds Successfully Installed!</strong><br>';
                echo sprintf(
                    esc_html__('Added: %d feeds | Skipped: %d existing | Total: %d feeds across %d categories', 'senna-finance'),
                    $result['added'],
                    $result['skipped'],
                    $result['total'],
                    $result['categories']
                );
                echo '</p>';
                if (!empty($result['category_list'])) {
                    echo '<p style="margin: 0; font-size: 12px; color: #666;">Categories: ' . esc_html(implode(', ', $result['category_list'])) . '</p>';
                }
                echo '</div>';
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html($result['message']) . '</p></div>';
            }
        }

        // Handle form submission
        if (isset($_POST['sffc_save_settings']) && check_admin_referer('sffc_settings_nonce')) {
            $this->save_settings();
            echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved successfully!', 'senna-finance') . '</p></div>';
        }

        // Use centralized API key manager
        $api_key = '';
        if (class_exists('SFFC_API_Key_Manager')) {
            $key_manager = SFFC_API_Key_Manager::get_instance();
            $api_key = $key_manager->get_api_key();
        }
        $claude_settings = get_option('sffc_claude_settings', array(
            'model' => 'claude-3-5-sonnet-20241022',
            'temperature' => 0.7,
            'max_tokens' => 4000,
            'timeout' => 15, // Reduced timeout for better UX
            'system_prompt_mode' => 'dynamic'
        ));
        $mode_settings = get_option('sffc_mode_settings', array(
            'enabled_modes' => array('career', 'market', 'skills', 'opportunities'),
            'default_mode' => 'career',
            'expert_mode_enabled' => false
        ));
        $appearance_settings = get_option('sffc_appearance_settings', array(
            'primary_color' => '#1B3B2F',
            'chat_font_size' => '18px',
            'enable_glassmorphism' => true
        ));

        $configured_user_menu = get_option('sffc_dashboard_user_menu_items', array());
        if (empty($configured_user_menu) || !is_array($configured_user_menu)) {
            $configured_user_menu = $this->get_default_dashboard_user_menu_items();
        }
        $user_menu_prefill = $this->stringify_user_menu_items($configured_user_menu);

    ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <form method="post" action="">
                <?php wp_nonce_field('sffc_settings_nonce'); ?>

                <div class="sffc-settings-container">
                    <!-- API Settings -->
                    <div class="sffc-settings-section">
                        <h2><?php esc_html_e('API Configuration', 'senna-finance'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="sffc_api_key"><?php esc_html_e('Claude API Key', 'senna-finance'); ?></label>
                                </th>
                                <td>
                                    <input type="password"
                                        id="sffc_api_key"
                                        name="sffc_api_key"
                                        value="<?php echo esc_attr($api_key); ?>"
                                        class="regular-text" />
                                    <p class="description">
                                        <?php esc_html_e('Enter your Claude API key from Anthropic', 'senna-finance'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="sffc_crm_gmail_client_id"><?php esc_html_e('Gmail Client ID', 'senna-finance'); ?></label>
                                </th>
                                <td>
                                    <input type="text"
                                        id="sffc_crm_gmail_client_id"
                                        name="sffc_crm_gmail_client_id"
                                        value="<?php echo esc_attr(get_option('sffc_crm_gmail_client_id', '')); ?>"
                                        class="regular-text" />
                                    <p class="description"><?php esc_html_e('OAuth 2.0 Client ID from Google Cloud Console', 'senna-finance'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="sffc_crm_gmail_client_secret"><?php esc_html_e('Gmail Client Secret', 'senna-finance'); ?></label>
                                </th>
                                <td>
                                    <input type="password"
                                        id="sffc_crm_gmail_client_secret"
                                        name="sffc_crm_gmail_client_secret"
                                        value="<?php echo esc_attr(get_option('sffc_crm_gmail_client_secret', '')); ?>"
                                        class="regular-text" />
                                    <p class="description"><?php esc_html_e('OAuth 2.0 client secret for Gmail integration', 'senna-finance'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="sffc_crm_outlook_client_id"><?php esc_html_e('Outlook Client ID', 'senna-finance'); ?></label>
                                </th>
                                <td>
                                    <input type="text"
                                        id="sffc_crm_outlook_client_id"
                                        name="sffc_crm_outlook_client_id"
                                        value="<?php echo esc_attr(get_option('sffc_crm_outlook_client_id', '')); ?>"
                                        class="regular-text" />
                                    <p class="description"><?php esc_html_e('Application (client) ID from Azure portal', 'senna-finance'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="sffc_crm_outlook_client_secret"><?php esc_html_e('Outlook Client Secret', 'senna-finance'); ?></label>
                                </th>
                                <td>
                                    <input type="password"
                                        id="sffc_crm_outlook_client_secret"
                                        name="sffc_crm_outlook_client_secret"
                                        value="<?php echo esc_attr(get_option('sffc_crm_outlook_client_secret', '')); ?>"
                                        class="regular-text" />
                                    <p class="description"><?php esc_html_e('Client secret value from Azure portal', 'senna-finance'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="sffc_crm_outlook_tenant"><?php esc_html_e('Outlook Tenant ID', 'senna-finance'); ?></label>
                                </th>
                                <td>
                                    <input type="text"
                                        id="sffc_crm_outlook_tenant"
                                        name="sffc_crm_outlook_tenant"
                                        value="<?php echo esc_attr(get_option('sffc_crm_outlook_tenant', 'common')); ?>"
                                        class="regular-text" />
                                    <p class="description"><?php esc_html_e('Tenant ID for your Azure app (use "common" for multi-tenant)', 'senna-finance'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Authentication Settings -->
                    <div class="sffc-settings-section">
                        <h2><?php esc_html_e('Authentication Settings', 'senna-finance'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="sffc_login_url"><?php esc_html_e('Login Page URL', 'senna-finance'); ?></label>
                                </th>
                                <td>
                                    <input type="url"
                                        id="sffc_login_url"
                                        name="sffc_login_url"
                                        value="<?php echo esc_attr(get_option('sffc_login_url', 'https://joinsenna.com/login-auth/')); ?>"
                                        class="regular-text"
                                        placeholder="https://example.com/login" />
                                    <p class="description">
                                        <?php esc_html_e('Enter the URL where users should be directed to log in. This will be used for profile builder and application features.', 'senna-finance'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="sffc_registration_url"><?php esc_html_e('Registration Page URL', 'senna-finance'); ?></label>
                                </th>
                                <td>
                                    <input type="text"
                                        id="sffc_registration_url"
                                        name="sffc_registration_url"
                                        value="<?php echo esc_attr(get_option('sffc_registration_url', 'https://joinsenna.com/memberships/')); ?>"
                                        class="regular-text"
                                        placeholder="https://example.com/register#anchor" />
                                    <p class="description">
                                        <?php esc_html_e('Enter the URL where new users should register. You can include anchors (e.g., #insights). Email and name will be passed as URL parameters.', 'senna-finance'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Dashboard User Menu -->
                    <div class="sffc-settings-section">
                        <h2><?php esc_html_e('Dashboard User Menu', 'senna-finance'); ?></h2>
                        <p><?php esc_html_e('Control the links that appear inside the newsroom profile dropdown (class="sffc-user-menu").', 'senna-finance'); ?></p>
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="sffc_user_menu_items"><?php esc_html_e('Menu entries', 'senna-finance'); ?></label>
                                </th>
                                <td>
                                    <textarea id="sffc_user_menu_items" name="sffc_user_menu_items" rows="6" class="large-text code" placeholder="Profile | {{profile_url}} | logged_in | same&#10;Join | https://joinsenna.com/memberships/ | logged_out | new"><?php echo esc_textarea($user_menu_prefill); ?></textarea>
                                    <p class="description">
                                        <?php esc_html_e('Format: Label | URL | visibility | target. Visibility accepts "logged_in", "logged_out" or "both". Target is optional (use "new" to force a new tab). Tokens: {{profile_url}}, {{login_url}}, {{logout_url}}, {{join_url}}, {{dashboard_url}}, {{saved_url}}, {{messages_url}}, {{home_url}}.', 'senna-finance'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Advanced Claude Settings -->
                    <div class="sffc-settings-section">
                        <h2><?php esc_html_e('Advanced Claude Configuration', 'senna-finance'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="sffc_claude_model"><?php esc_html_e('Claude Model', 'senna-finance'); ?></label>
                                </th>
                                <td>
                                    <select id="sffc_claude_model" name="sffc_claude_model" class="regular-text">
                                        <option value="claude-3-5-sonnet-20241022" <?php selected($claude_settings['model'], 'claude-3-5-sonnet-20241022'); ?>>
                                            Claude 3.5 Sonnet (Latest) - Best balance of intelligence and speed
                                        </option>
                                        <option value="claude-3-5-sonnet-20240620" <?php selected($claude_settings['model'], 'claude-3-5-sonnet-20240620'); ?>>
                                            Claude 3.5 Sonnet (June 2024)
                                        </option>
                                        <option value="claude-3-opus-20240229" <?php selected($claude_settings['model'], 'claude-3-opus-20240229'); ?>>
                                            Claude 3 Opus - Highest intelligence, slower
                                        </option>
                                        <option value="claude-3-sonnet-20240229" <?php selected($claude_settings['model'], 'claude-3-sonnet-20240229'); ?>>
                                            Claude 3 Sonnet - Good balance
                                        </option>
                                        <option value="claude-3-haiku-20240307" <?php selected($claude_settings['model'], 'claude-3-haiku-20240307'); ?>>
                                            Claude 3 Haiku - Fastest, most affordable
                                        </option>
                                    </select>
                                    <p class="description">
                                        <?php esc_html_e('Choose the Claude model. Sonnet 3.5 recommended for best financial expertise.', 'senna-finance'); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="sffc_claude_temperature"><?php esc_html_e('Temperature', 'senna-finance'); ?></label>
                                </th>
                                <td>
                                    <input type="range"
                                        id="sffc_claude_temperature"
                                        name="sffc_claude_temperature"
                                        min="0"
                                        max="1"
                                        step="0.1"
                                        value="<?php echo esc_attr($claude_settings['temperature']); ?>"
                                        class="sffc-range-slider" />
                                    <span class="sffc-range-value"><?php echo esc_html($claude_settings['temperature']); ?></span>
                                    <p class="description">
                                        <?php esc_html_e('Controls creativity vs consistency. 0.7 recommended for financial analysis. Lower = more consistent, Higher = more creative.', 'senna-finance'); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="sffc_claude_max_tokens"><?php esc_html_e('Max Tokens', 'senna-finance'); ?></label>
                                </th>
                                <td>
                                    <select id="sffc_claude_max_tokens" name="sffc_claude_max_tokens">
                                        <option value="1000" <?php selected($claude_settings['max_tokens'], 1000); ?>>1,000 - Short responses</option>
                                        <option value="2000" <?php selected($claude_settings['max_tokens'], 2000); ?>>2,000 - Medium responses</option>
                                        <option value="4000" <?php selected($claude_settings['max_tokens'], 4000); ?>>4,000 - Detailed responses (Recommended)</option>
                                        <option value="6000" <?php selected($claude_settings['max_tokens'], 6000); ?>>6,000 - Very detailed responses</option>
                                        <option value="8192" <?php selected($claude_settings['max_tokens'], 8192); ?>>8,192 - Maximum detail</option>
                                    </select>
                                    <p class="description">
                                        <?php esc_html_e('Maximum response length. Higher values allow more detailed analysis but cost more.', 'senna-finance'); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="sffc_claude_timeout"><?php esc_html_e('Request Timeout', 'senna-finance'); ?></label>
                                </th>
                                <td>
                                    <select id="sffc_claude_timeout" name="sffc_claude_timeout">
                                        <option value="15" <?php selected($claude_settings['timeout'], 15); ?>>15 seconds</option>
                                        <option value="30" <?php selected($claude_settings['timeout'], 30); ?>>30 seconds (Recommended)</option>
                                        <option value="45" <?php selected($claude_settings['timeout'], 45); ?>>45 seconds</option>
                                        <option value="60" <?php selected($claude_settings['timeout'], 60); ?>>60 seconds</option>
                                    </select>
                                    <p class="description">
                                        <?php esc_html_e('How long to wait for Claude response before falling back to templates.', 'senna-finance'); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="sffc_enable_xml_feeds"><?php esc_html_e('Enable XML Feeds', 'senna-finance'); ?></label>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox"
                                            id="sffc_enable_xml_feeds"
                                            name="sffc_enable_xml_feeds"
                                            value="1"
                                            <?php checked(get_option('sffc_enable_xml_feeds', 1), 1); ?> />
                                        <?php esc_html_e('Enable real-time market data from XML feeds', 'senna-finance'); ?>
                                    </label>
                                    <p class="description">
                                        <?php esc_html_e('When enabled, MENA Careers will fetch real market data from financial news feeds.', 'senna-finance'); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="sffc_feed_timeout"><?php esc_html_e('Feed Timeout', 'senna-finance'); ?></label>
                                </th>
                                <td>
                                    <select id="sffc_feed_timeout" name="sffc_feed_timeout">
                                        <option value="10" <?php selected(get_option('sffc_feed_timeout', 15), 10); ?>>10 seconds</option>
                                        <option value="15" <?php selected(get_option('sffc_feed_timeout', 15), 15); ?>>15 seconds (Default)</option>
                                        <option value="20" <?php selected(get_option('sffc_feed_timeout', 15), 20); ?>>20 seconds</option>
                                        <option value="30" <?php selected(get_option('sffc_feed_timeout', 15), 30); ?>>30 seconds</option>
                                    </select>
                                    <p class="description">
                                        <?php esc_html_e('How long to wait for feed responses. Increase if feeds are timing out.', 'senna-finance'); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="sffc_font_family"><?php esc_html_e('Font Family', 'senna-finance'); ?></label>
                                </th>
                                <td>
                                    <?php $current_font = get_option('sffc_font_family', 'system'); ?>
                                    <select id="sffc_font_family" name="sffc_font_family" class="regular-text">
                                        <option value="system" <?php selected($current_font, 'system'); ?>>System Default</option>
                                        <option value="inter" <?php selected($current_font, 'inter'); ?>>Inter (Modern & Clean)</option>
                                        <option value="helvetica-neue" <?php selected($current_font, 'helvetica-neue'); ?>>Helvetica Neue (Professional)</option>
                                        <option value="sf-pro" <?php selected($current_font, 'sf-pro'); ?>>SF Pro (Apple Style)</option>
                                        <option value="ibm-plex" <?php selected($current_font, 'ibm-plex'); ?>>IBM Plex Sans (Finance)</option>
                                        <option value="roboto" <?php selected($current_font, 'roboto'); ?>>Roboto (Google Style)</option>
                                        <option value="source-sans" <?php selected($current_font, 'source-sans'); ?>>Source Sans Pro</option>
                                        <option value="open-sans" <?php selected($current_font, 'open-sans'); ?>>Open Sans (Readable)</option>
                                        <option value="lato" <?php selected($current_font, 'lato'); ?>>Lato (Friendly)</option>
                                        <option value="bloomberg" <?php selected($current_font, 'bloomberg'); ?>>Monospace (Bloomberg Terminal)</option>
                                    </select>
                                    <p class="description">
                                        <?php esc_html_e('Choose the font for chat messages', 'senna-finance'); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="sffc_font_size"><?php esc_html_e('Font Size', 'senna-finance'); ?></label>
                                </th>
                                <td>
                                    <select id="sffc_font_size" name="sffc_font_size">
                                        <option value="14px" <?php selected(get_option('sffc_font_size', '16px'), '14px'); ?>>14px - Small</option>
                                        <option value="15px" <?php selected(get_option('sffc_font_size', '16px'), '15px'); ?>>15px - Medium Small</option>
                                        <option value="16px" <?php selected(get_option('sffc_font_size', '16px'), '16px'); ?>>16px - Default</option>
                                        <option value="17px" <?php selected(get_option('sffc_font_size', '16px'), '17px'); ?>>17px - Medium Large</option>
                                        <option value="18px" <?php selected(get_option('sffc_font_size', '16px'), '18px'); ?>>18px - Large</option>
                                        <option value="20px" <?php selected(get_option('sffc_font_size', '16px'), '20px'); ?>>20px - Extra Large</option>
                                    </select>
                                    <p class="description">
                                        <?php esc_html_e('Base font size for messages', 'senna-finance'); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="sffc_disable_shadows"><?php esc_html_e('Text Effects', 'senna-finance'); ?></label>
                                </th>
                                <td>
                                    <fieldset>
                                        <label>
                                            <input type="checkbox"
                                                id="sffc_disable_shadows"
                                                name="sffc_disable_shadows"
                                                value="1"
                                                <?php checked(get_option('sffc_disable_shadows', 0), 1); ?> />
                                            <?php esc_html_e('Disable text shadows (removes blur effect)', 'senna-finance'); ?>
                                        </label><br>
                                        <label>
                                            <input type="checkbox"
                                                id="sffc_disable_smoothing"
                                                name="sffc_disable_smoothing"
                                                value="1"
                                                <?php checked(get_option('sffc_disable_smoothing', 0), 1); ?> />
                                            <?php esc_html_e('Disable font smoothing', 'senna-finance'); ?>
                                        </label>
                                    </fieldset>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="sffc_line_height"><?php esc_html_e('Line Height', 'senna-finance'); ?></label>
                                </th>
                                <td>
                                    <select id="sffc_line_height" name="sffc_line_height">
                                        <option value="1.4" <?php selected(get_option('sffc_line_height', '1.6'), '1.4'); ?>>1.4 - Compact</option>
                                        <option value="1.5" <?php selected(get_option('sffc_line_height', '1.6'), '1.5'); ?>>1.5 - Normal</option>
                                        <option value="1.6" <?php selected(get_option('sffc_line_height', '1.6'), '1.6'); ?>>1.6 - Default</option>
                                        <option value="1.7" <?php selected(get_option('sffc_line_height', '1.6'), '1.7'); ?>>1.7 - Relaxed</option>
                                        <option value="1.8" <?php selected(get_option('sffc_line_height', '1.6'), '1.8'); ?>>1.8 - Spacious</option>
                                    </select>
                                    <p class="description">
                                        <?php esc_html_e('Space between lines of text', 'senna-finance'); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="sffc_system_prompt_mode"><?php esc_html_e('System Prompt Mode', 'senna-finance'); ?></label>
                                </th>
                                <td>
                                    <fieldset>
                                        <legend class="screen-reader-text"><span><?php esc_html_e('System Prompt Mode', 'senna-finance'); ?></span></legend>
                                        <label>
                                            <input type="radio" name="sffc_system_prompt_mode" value="dynamic" <?php checked($claude_settings['system_prompt_mode'], 'dynamic'); ?> />
                                            <strong>Dynamic</strong> - Context-aware prompts that adapt to each mode (Recommended)
                                        </label><br>
                                        <label>
                                            <input type="radio" name="sffc_system_prompt_mode" value="expert" <?php checked($claude_settings['system_prompt_mode'], 'expert'); ?> />
                                            <strong>Expert</strong> - Advanced financial expert persona with industry-specific knowledge
                                        </label><br>
                                        <label>
                                            <input type="radio" name="sffc_system_prompt_mode" value="conversational" <?php checked($claude_settings['system_prompt_mode'], 'conversational'); ?> />
                                            <strong>Conversational</strong> - Friendly, approachable tone for broad audiences
                                        </label>
                                    </fieldset>
                                    <p class="description">
                                        <?php esc_html_e('How Claude should behave. Dynamic mode provides the best experience across all modes.', 'senna-finance'); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="sffc_enable_streaming"><?php esc_html_e('Response Streaming', 'senna-finance'); ?></label>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox"
                                            id="sffc_enable_streaming"
                                            name="sffc_enable_streaming"
                                            value="1"
                                            <?php checked(isset($claude_settings['enable_streaming']) ? $claude_settings['enable_streaming'] : false); ?> />
                                        Enable streaming responses (experimental)
                                    </label>
                                    <p class="description">
                                        <?php esc_html_e('Stream responses in real-time for faster perceived performance. Requires additional API setup.', 'senna-finance'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Mode Settings -->
                    <div class="sffc-settings-section">
                        <h2><?php esc_html_e('Mode Configuration', 'senna-finance'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Enabled Modes', 'senna-finance'); ?></th>
                                <td>
                                    <fieldset>
                                        <label>
                                            <input type="checkbox"
                                                id="sffc_mode_career"
                                                name="sffc_enabled_modes[]"
                                                value="career"
                                                <?php checked(in_array('career', $mode_settings['enabled_modes'])); ?> />
                                            <?php esc_html_e('Career Assistance', 'senna-finance'); ?>
                                        </label><br>
                                        <label>
                                            <input type="checkbox"
                                                id="sffc_mode_market"
                                                name="sffc_enabled_modes[]"
                                                value="market"
                                                <?php checked(in_array('market', $mode_settings['enabled_modes'])); ?> />
                                            <?php esc_html_e('Market Analysis', 'senna-finance'); ?>
                                        </label><br>
                                        <label>
                                            <input type="checkbox"
                                                id="sffc_mode_skills"
                                                name="sffc_enabled_modes[]"
                                                value="skills"
                                                <?php checked(in_array('skills', $mode_settings['enabled_modes'])); ?> />
                                            <?php esc_html_e('Build Skills', 'senna-finance'); ?>
                                        </label><br>
                                        <label>
                                            <input type="checkbox"
                                                id="sffc_mode_opportunities"
                                                name="sffc_enabled_modes[]"
                                                value="opportunities"
                                                <?php checked(in_array('opportunities', $mode_settings['enabled_modes'])); ?> />
                                            <?php esc_html_e('Opportunities', 'senna-finance'); ?>
                                        </label>
                                    </fieldset>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="sffc_default_mode"><?php esc_html_e('Default Mode', 'senna-finance'); ?></label>
                                </th>
                                <td>
                                    <select id="sffc_default_mode" name="sffc_default_mode">
                                        <option value="career" <?php selected($mode_settings['default_mode'], 'career'); ?>>
                                            <?php esc_html_e('Career Assistance', 'senna-finance'); ?>
                                        </option>
                                        <option value="market" <?php selected($mode_settings['default_mode'], 'market'); ?>>
                                            <?php esc_html_e('Market Analysis', 'senna-finance'); ?>
                                        </option>
                                        <option value="skills" <?php selected($mode_settings['default_mode'], 'skills'); ?>>
                                            <?php esc_html_e('Build Skills', 'senna-finance'); ?>
                                        </option>
                                        <option value="opportunities" <?php selected($mode_settings['default_mode'], 'opportunities'); ?>>
                                            <?php esc_html_e('Opportunities', 'senna-finance'); ?>
                                        </option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Expert Mode', 'senna-finance'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox"
                                            id="sffc_expert_mode_enabled"
                                            name="sffc_expert_mode_enabled"
                                            value="1"
                                            <?php checked($mode_settings['expert_mode_enabled']); ?> />
                                        <?php esc_html_e('Enable Live Expert mode', 'senna-finance'); ?>
                                    </label>
                                    <p class="description">
                                        <?php esc_html_e('Allow users to chat with senna experts', 'senna-finance'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Appearance Settings -->
                    <div class="sffc-settings-section">
                        <h2><?php esc_html_e('Appearance Settings', 'senna-finance'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="sffc_primary_color"><?php esc_html_e('Primary Color', 'senna-finance'); ?></label>
                                </th>
                                <td>
                                    <input type="color"
                                        id="sffc_primary_color"
                                        name="sffc_primary_color"
                                        value="<?php echo esc_attr($appearance_settings['primary_color']); ?>" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="sffc_chat_font_size"><?php esc_html_e('Chat Font Size', 'senna-finance'); ?></label>
                                </th>
                                <td>
                                    <select id="sffc_chat_font_size" name="sffc_chat_font_size">
                                        <option value="16px" <?php selected($appearance_settings['chat_font_size'], '16px'); ?>>16px</option>
                                        <option value="18px" <?php selected($appearance_settings['chat_font_size'], '18px'); ?>>18px (Recommended)</option>
                                        <option value="20px" <?php selected($appearance_settings['chat_font_size'], '20px'); ?>>20px</option>
                                        <option value="22px" <?php selected($appearance_settings['chat_font_size'], '22px'); ?>>22px</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Glassmorphism Effect', 'senna-finance'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox"
                                            id="sffc_enable_glassmorphism"
                                            name="sffc_enable_glassmorphism"
                                            value="1"
                                            <?php checked($appearance_settings['enable_glassmorphism']); ?> />
                                        <?php esc_html_e('Enable glassmorphism blur effect', 'senna-finance'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- XML Feed Management Section -->
                    <div class="sffc-settings-section">
                        <h2><?php esc_html_e('XML Feed Management', 'senna-finance'); ?></h2>
                        <p class="description"><?php esc_html_e('Manage RSS/XML feeds for real-time market data. Add custom feeds or disable existing ones.', 'senna-finance'); ?></p>

                        <?php if (!$news_feed_enabled): ?>
                            <div class="notice notice-warning inline" style="margin: 16px 0;">
                                <p>
                                    <strong><?php esc_html_e('News feed production is disabled.', 'senna-finance'); ?></strong>
                                    <?php esc_html_e('European market feeds, private equity feeds, and the existing feed manager are disabled in production.', 'senna-finance'); ?>
                                </p>
                            </div>
                        <?php else: ?>
                        <!-- Add New Feed Form -->
                        <div class="sffc-add-feed-form" style="background: #f5f5f5; padding: 20px; border-radius: 5px; margin-bottom: 30px;">
                            <h3><?php esc_html_e('Add New Feed', 'senna-finance'); ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="new_feed_name"><?php esc_html_e('Feed Name', 'senna-finance'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text"
                                            id="new_feed_name"
                                            name="new_feed_name"
                                            class="regular-text"
                                            placeholder="e.g., Bloomberg Markets" />
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="new_feed_url"><?php esc_html_e('Feed URL', 'senna-finance'); ?></label>
                                    </th>
                                    <td>
                                        <input type="url"
                                            id="new_feed_url"
                                            name="new_feed_url"
                                            class="large-text"
                                            placeholder="https://example.com/rss.xml" />
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="new_feed_category"><?php esc_html_e('Category', 'senna-finance'); ?></label>
                                    </th>
                                    <td>
                                        <select id="new_feed_category" name="new_feed_category">
                                            <option value="markets">Markets</option>
                                            <option value="private-equity">Private Equity</option>
                                            <option value="venture-capital">Venture Capital</option>
                                            <option value="business">Business News</option>
                                            <option value="alternatives">Alternative Investments</option>
                                            <option value="crypto">Cryptocurrency</option>
                                            <option value="commodities">Commodities</option>
                                            <option value="central-banks">Central Banks</option>
                                            <option value="research">Research & Economics</option>
                                            <option value="emerging-markets">Emerging Markets</option>
                                            <option value="general">General Finance</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="new_feed_priority"><?php esc_html_e('Priority', 'senna-finance'); ?></label>
                                    </th>
                                    <td>
                                        <input type="number"
                                            id="new_feed_priority"
                                            name="new_feed_priority"
                                            min="1"
                                            max="100"
                                            value="10"
                                            style="width: 70px;" />
                                        <p class="description"><?php esc_html_e('Lower numbers = higher priority (1-100)', 'senna-finance'); ?></p>
                                    </td>
                                </tr>
                            </table>
                            <p>
                                <button type="button" id="sffc-add-feed" class="button button-secondary">
                                    <?php esc_html_e('Add Feed', 'senna-finance'); ?>
                                </button>
                                <button type="button" id="sffc-test-feed" class="button button-secondary">
                                    <?php esc_html_e('Test Feed URL', 'senna-finance'); ?>
                                </button>
                            </p>
                            <div id="sffc-feed-test-results" style="display: none; margin-top: 10px;"></div>
                        </div>

                        <!-- European Feeds Quick Add -->
                        <div class="sffc-quick-actions" style="background: #f0f8ff; border: 1px solid #b3d9ff; padding: 15px; margin: 20px 0; border-radius: 5px;">
                            <h4 style="margin: 0 0 10px 0;">🇪🇺 Quick Add European Market Feeds</h4>
                            <p style="margin: 0 0 10px 0;">Add 30+ verified working European market and financial news feeds instantly.</p>
                            <p><strong>✅ All feeds tested and working!</strong></p>
                            <ul style="margin: 10px 0; padding-left: 20px;">
                                <li><strong>Market Data:</strong> Yahoo Finance (FTSE, DAX, CAC 40, STOXX), Financial Times, Bloomberg, MarketWatch</li>
                                <li><strong>Business News:</strong> BBC, Guardian, CNBC Europe, City AM, Euronews, Politico EU</li>
                                <li><strong>Central Banks:</strong> European Central Bank, FXStreet</li>
                                <li><strong>FinTech:</strong> FinExtra, The Fintech Times</li>
                                <li><strong>Crypto:</strong> CoinDesk, CoinTelegraph, The Block</li>
                                <li><strong>Energy:</strong> Oil Price, Energy Voice, Offshore Energy</li>
                            </ul>
                            <button type="submit" name="sffc_install_european_feeds" value="1" class="button button-primary" style="font-size: 16px; padding: 8px 20px;">
                                <?php esc_html_e('🚀 Install All European Feeds Now', 'senna-finance'); ?>
                            </button>
                        </div>

                        <!-- Private Equity Feeds Quick Add -->
                        <div class="sffc-quick-actions" style="background: #f3e8ff; border: 1px solid #c084fc; padding: 15px; margin: 20px 0; border-radius: 5px;">
                            <h4 style="margin: 0 0 10px 0;">💼 European Private Equity & Venture Capital Feeds</h4>
                            <p style="margin: 0 0 10px 0;">Add 45+ specialized PE/VC feeds covering the European alternative investment landscape.</p>
                            <p><strong>🎯 Comprehensive coverage of European private markets!</strong></p>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 10px 0;">
                                <div>
                                    <h5 style="margin: 0 0 5px 0; color: #7c3aed;">Geographic Coverage:</h5>
                                    <ul style="margin: 5px 0; padding-left: 20px; font-size: 13px;">
                                        <li>Pan-European (10 sources)</li>
                                        <li>Germany/DACH (4 sources)</li>
                                        <li>France (3 sources)</li>
                                        <li>UK/Ireland (3 sources)</li>
                                        <li>Nordics (2 sources)</li>
                                        <li>Benelux (2 sources)</li>
                                        <li>Southern Europe (3 sources)</li>
                                        <li>CEE/Eastern (3 sources)</li>
                                    </ul>
                                </div>
                                <div>
                                    <h5 style="margin: 0 0 5px 0; color: #7c3aed;">Asset Classes:</h5>
                                    <ul style="margin: 5px 0; padding-left: 20px; font-size: 13px;">
                                        <li>Traditional PE/Buyout</li>
                                        <li>Venture Capital</li>
                                        <li>Growth Equity</li>
                                        <li>Infrastructure</li>
                                        <li>Private Debt</li>
                                        <li>Secondaries</li>
                                        <li>Impact/ESG Investing</li>
                                        <li>LP Perspectives</li>
                                    </ul>
                                </div>
                            </div>
                            <p style="margin: 10px 0; font-size: 13px;">
                                <strong>Key Sources Include:</strong> Private Equity Wire, Alt Assets, Sifted (FT), Private Equity International,
                                Infrastructure Investor, Private Debt Investor, Invest Europe, EU-Startups, Tech.eu, and many more regional sources.
                            </p>
                            <button type="submit" name="sffc_install_pe_feeds" value="1" class="button button-primary" style="font-size: 16px; padding: 8px 20px; background: #7c3aed; border-color: #7c3aed;">
                                <?php esc_html_e('💎 Install Private Equity Feeds Now', 'senna-finance'); ?>
                            </button>
                        </div>

                        <!-- Existing Feeds Table -->
                        <h3><?php esc_html_e('Existing Feeds', 'senna-finance'); ?></h3>
                        <table class="wp-list-table widefat fixed striped" id="sffc-feeds-table">
                            <thead>
                                <tr>
                                    <th style="width: 30px;"><?php esc_html_e('Active', 'senna-finance'); ?></th>
                                    <th><?php esc_html_e('Feed Name', 'senna-finance'); ?></th>
                                    <th><?php esc_html_e('URL', 'senna-finance'); ?></th>
                                    <th><?php esc_html_e('Category', 'senna-finance'); ?></th>
                                    <th style="width: 60px;"><?php esc_html_e('Priority', 'senna-finance'); ?></th>
                                    <th><?php esc_html_e('Last Fetched', 'senna-finance'); ?></th>
                                    <th><?php esc_html_e('Status', 'senna-finance'); ?></th>
                                    <th><?php esc_html_e('Actions', 'senna-finance'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $this->render_feeds_table(); ?>
                            </tbody>
                        </table>

                        <p class="description" style="margin-top: 20px;">
                            <strong><?php esc_html_e('Note:', 'senna-finance'); ?></strong>
                            <?php esc_html_e('Feeds are fetched in priority order. Disable feeds that are slow or unreliable to improve performance.', 'senna-finance'); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>

                <p class="submit">
                    <input type="submit"
                        name="sffc_save_settings"
                        class="button-primary"
                        value="<?php esc_attr_e('Save Settings', 'senna-finance'); ?>" />
                </p>
            </form>
        </div>
<?php
    }

    /**
     * Render feeds table
     */
    private function render_feeds_table()
    {
        global $wpdb;

        // Get the database instance
        require_once SFFC_PLUGIN_DIR . 'includes/class-database.php';
        $db = SFFC_Database::get_instance();

        $table_name = $db->get_table('xml_feeds');

        if (!$table_name) {
            echo '<tr><td colspan="8">' . esc_html__('Feed table not found. Please create database tables.', 'senna-finance') . '</td></tr>';
            return;
        }

        // Get all feeds from database
        $feeds = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY priority ASC, feed_name ASC");

        if (empty($feeds)) {
            echo '<tr><td colspan="8">' . esc_html__('No feeds configured. Add your first feed above.', 'senna-finance') . '</td></tr>';
            return;
        }

        foreach ($feeds as $feed) {
            $active_checked = $feed->is_active ? 'checked' : '';
            $status_class = $feed->error_count > 3 ? 'error' : ($feed->last_fetched ? 'success' : 'pending');
            $status_text = $feed->error_count > 3 ? 'Error' : ($feed->last_fetched ? 'Active' : 'Pending');

            $last_fetched = $feed->last_fetched ? human_time_diff(strtotime($feed->last_fetched), current_time('timestamp')) . ' ago' : 'Never';

            echo '<tr data-feed-id="' . esc_attr($feed->id) . '">';
            echo '<td><input type="checkbox" class="sffc-feed-active" data-feed-id="' . esc_attr($feed->id) . '" ' . $active_checked . ' /></td>';
            echo '<td class="feed-name">' . esc_html($feed->feed_name) . '</td>';
            echo '<td class="feed-url"><a href="' . esc_url($feed->feed_url) . '" target="_blank">' . esc_html(substr($feed->feed_url, 0, 50)) . '...</a></td>';
            echo '<td class="feed-category">' . esc_html($feed->feed_category) . '</td>';
            echo '<td class="feed-priority">' . esc_html($feed->priority) . '</td>';
            echo '<td>' . esc_html($last_fetched) . '</td>';
            echo '<td><span class="sffc-status sffc-status-' . esc_attr($status_class) . '">' . esc_html($status_text) . '</span></td>';
            echo '<td>';
            echo '<button type="button" class="button button-small sffc-test-feed" data-feed-id="' . esc_attr($feed->id) . '">Test</button> ';
            echo '<button type="button" class="button button-small sffc-edit-feed" data-feed-id="' . esc_attr($feed->id) . '">Edit</button> ';
            echo '<button type="button" class="button button-small sffc-delete-feed" data-feed-id="' . esc_attr($feed->id) . '">Delete</button>';
            echo '</td>';
            echo '</tr>';
        }
    }

    /**
     * Save settings
     */
    private function save_settings()
    {
        // Save API key using centralized manager
        if (isset($_POST['sffc_api_key'])) {
            $api_key = sanitize_text_field($_POST['sffc_api_key']);
            if (class_exists('SFFC_API_Key_Manager')) {
                $key_manager = SFFC_API_Key_Manager::get_instance();
                $key_manager->save_api_key($api_key);
            } else {
                update_option('sffc_api_key', $api_key);
            }
        }

        // Save authentication settings
        if (isset($_POST['sffc_login_url'])) {
            update_option('sffc_login_url', esc_url_raw($_POST['sffc_login_url']));
        }
        if (isset($_POST['sffc_registration_url'])) {
            update_option('sffc_registration_url', esc_url_raw($_POST['sffc_registration_url']));
        }

        // Save email integration settings
        $gmail_client_id = isset($_POST['sffc_crm_gmail_client_id']) ? sanitize_text_field($_POST['sffc_crm_gmail_client_id']) : '';
        $gmail_client_secret = isset($_POST['sffc_crm_gmail_client_secret']) ? sanitize_text_field($_POST['sffc_crm_gmail_client_secret']) : '';
        $outlook_client_id = isset($_POST['sffc_crm_outlook_client_id']) ? sanitize_text_field($_POST['sffc_crm_outlook_client_id']) : '';
        $outlook_client_secret = isset($_POST['sffc_crm_outlook_client_secret']) ? sanitize_text_field($_POST['sffc_crm_outlook_client_secret']) : '';
        $outlook_tenant = isset($_POST['sffc_crm_outlook_tenant']) ? sanitize_text_field($_POST['sffc_crm_outlook_tenant']) : 'common';

        update_option('sffc_crm_gmail_client_id', $gmail_client_id);
        update_option('sffc_crm_gmail_client_secret', $gmail_client_secret);
        update_option('sffc_crm_outlook_client_id', $outlook_client_id);
        update_option('sffc_crm_outlook_client_secret', $outlook_client_secret);
        update_option('sffc_crm_outlook_tenant', $outlook_tenant);

        // Save advanced Claude settings
        $claude_settings = array(
            'model' => isset($_POST['sffc_claude_model']) ? sanitize_text_field($_POST['sffc_claude_model']) : 'claude-3-5-sonnet-20241022',
            'temperature' => isset($_POST['sffc_claude_temperature']) ? floatval($_POST['sffc_claude_temperature']) : 0.7,
            'max_tokens' => isset($_POST['sffc_claude_max_tokens']) ? intval($_POST['sffc_claude_max_tokens']) : 4000,
            'timeout' => isset($_POST['sffc_claude_timeout']) ? intval($_POST['sffc_claude_timeout']) : 15,
            'system_prompt_mode' => isset($_POST['sffc_system_prompt_mode']) ? sanitize_text_field($_POST['sffc_system_prompt_mode']) : 'dynamic',
            'enable_streaming' => isset($_POST['sffc_enable_streaming'])
        );
        update_option('sffc_claude_settings', $claude_settings);

        // Save XML feed settings
        update_option('sffc_enable_xml_feeds', isset($_POST['sffc_enable_xml_feeds']) ? 1 : 0);
        update_option('sffc_feed_timeout', isset($_POST['sffc_feed_timeout']) ? intval($_POST['sffc_feed_timeout']) : 15);

        // Save typography settings
        update_option('sffc_font_family', isset($_POST['sffc_font_family']) ? sanitize_text_field($_POST['sffc_font_family']) : 'system');
        update_option('sffc_font_size', isset($_POST['sffc_font_size']) ? sanitize_text_field($_POST['sffc_font_size']) : '16px');
        update_option('sffc_line_height', isset($_POST['sffc_line_height']) ? sanitize_text_field($_POST['sffc_line_height']) : '1.6');
        update_option('sffc_disable_shadows', isset($_POST['sffc_disable_shadows']) ? 1 : 0);
        update_option('sffc_disable_smoothing', isset($_POST['sffc_disable_smoothing']) ? 1 : 0);

        // Save mode settings
        $mode_settings = array(
            'enabled_modes' => isset($_POST['sffc_enabled_modes']) ? array_map(function ($mode) {
                return sanitize_text_field($mode);
            }, $_POST['sffc_enabled_modes']) : array('career'),
            'default_mode' => isset($_POST['sffc_default_mode']) ? sanitize_text_field($_POST['sffc_default_mode']) : 'career',
            'expert_mode_enabled' => isset($_POST['sffc_expert_mode_enabled'])
        );
        update_option('sffc_mode_settings', $mode_settings);

        // Save appearance settings
        $appearance_settings = array(
            'primary_color' => isset($_POST['sffc_primary_color']) ? sanitize_hex_color($_POST['sffc_primary_color']) : '#1B3B2F',
            'chat_font_size' => isset($_POST['sffc_chat_font_size']) ? sanitize_text_field($_POST['sffc_chat_font_size']) : '18px',
            'enable_glassmorphism' => isset($_POST['sffc_enable_glassmorphism'])
        );
        update_option('sffc_appearance_settings', $appearance_settings);

        if (isset($_POST['sffc_user_menu_items'])) {
            $raw_items = wp_unslash($_POST['sffc_user_menu_items']);
            $lines = preg_split("/\r\n|\r|\n/", $raw_items);
            $parsed_items = array();

            if (is_array($lines)) {
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ('' === $line) {
                        continue;
                    }

                    $parts = array_map('trim', explode('|', $line));
                    $label = isset($parts[0]) ? sanitize_text_field($parts[0]) : '';
                    $raw_url = isset($parts[1]) ? $parts[1] : '';
                    if ('' !== $raw_url) {
                        $raw_url = trim($raw_url);
                    }
                    $url = '';
                    if ('' !== $raw_url) {
                        if (false !== strpos($raw_url, '{{') && false !== strpos($raw_url, '}}')) {
                            $url = sanitize_text_field($raw_url);
                        } else {
                            $url = esc_url_raw($raw_url);
                            if ('' === $url) {
                                $url = sanitize_text_field($raw_url);
                            }
                        }
                    }
                    if ('' === $label || '' === $url) {
                        continue;
                    }

                    $visibility = isset($parts[2]) ? strtolower(sanitize_key($parts[2])) : 'both';
                    if (!in_array($visibility, array('logged_in', 'logged_out', 'both'), true)) {
                        $visibility = 'both';
                    }

                    $target_raw = isset($parts[3]) ? strtolower(sanitize_text_field($parts[3])) : '';
                    $target = ('new' === $target_raw || '_blank' === $target_raw) ? '_blank' : '_self';

                    $parsed_items[] = array(
                        'label' => $label,
                        'url' => $url,
                        'visibility' => $visibility,
                        'target' => $target
                    );
                }
            }

            update_option('sffc_dashboard_user_menu_items', $parsed_items);
        }
    }

    private function stringify_user_menu_items($items)
    {
        if (empty($items) || !is_array($items)) {
            return '';
        }

        $lines = array();
        foreach ($items as $item) {
            $label = isset($item['label']) ? $item['label'] : '';
            $url = isset($item['url']) ? $item['url'] : '';
            $visibility = isset($item['visibility']) ? $item['visibility'] : 'both';
            $target = (isset($item['target']) && '_blank' === $item['target']) ? 'new' : 'same';
            if (!$label || !$url) {
                continue;
            }
            $lines[] = sprintf('%s | %s | %s | %s', $label, $url, $visibility, $target);
        }

        return implode("\n", $lines);
    }

    private function get_default_dashboard_user_menu_items()
    {
        if (class_exists('SFFC_PE_News_Dashboard')) {
            return SFFC_PE_News_Dashboard::get_instance()->get_default_user_menu_items();
        }

        return array(
            array(
                'label' => __('Profile', 'senna-finance'),
                'url' => '{{profile_url}}',
                'visibility' => 'logged_in',
                'target' => '_self'
            ),
            array(
                'label' => __('Join MENA Careers', 'senna-finance'),
                'url' => '{{join_url}}',
                'visibility' => 'logged_out',
                'target' => '_blank'
            ),
            array(
                'label' => __('Sign in', 'senna-finance'),
                'url' => '{{login_url}}',
                'visibility' => 'logged_out',
                'target' => '_self'
            )
        );
    }

    /**
     * Get total conversations count
     */
    private function get_total_conversations()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sffc_conversations';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            return '0';
        }

        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        return $count ? number_format($count) : '0';
    }

    /**
     * Get active users count
     */
    private function get_active_users()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sffc_conversations';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            return '0';
        }

        $count = $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) FROM $table WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        return $count ? number_format($count) : '0';
    }

    /**
     * Get messages today count
     */
    private function get_messages_today()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sffc_messages';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            return '0';
        }

        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table WHERE DATE(created_at) = CURDATE()"
        );
        return $count ? number_format($count) : '0';
    }

    /**
     * Get API status
     */
    private function get_api_status()
    {
        // Use centralized API key manager
        $api_key = '';
        if (class_exists('SFFC_API_Key_Manager')) {
            $key_manager = SFFC_API_Key_Manager::get_instance();
            $api_key = $key_manager->get_api_key();
        }

        if (empty($api_key)) {
            return '<span style="color: #d63638;">' . esc_html__('Not Configured', 'senna-finance') . '</span>';
        }

        // Check if key looks valid (basic check)
        if (strlen($api_key) < 40) {
            return '<span style="color: #dba617;">' . esc_html__('Invalid Key', 'senna-finance') . '</span>';
        }

        return '<span style="color: #00a32a;">' . esc_html__('Configured', 'senna-finance') . '</span>';
    }

    /**
     * Render database table status
     */
    private function render_table_status()
    {
        global $wpdb;

        $tables = array(
            'sffc_conversations' => __('Conversations', 'senna-finance'),
            'sffc_messages' => __('Messages', 'senna-finance'),
            'sffc_user_profiles' => __('User Profiles', 'senna-finance'),
            'sffc_expert_availability' => __('Expert Availability', 'senna-finance'),
            'sffc_opportunities' => __('Opportunities', 'senna-finance'),
            'sffc_file_uploads' => __('File Uploads', 'senna-finance'),
            'sffc_message_templates' => __('Message Templates', 'senna-finance')
        );

        foreach ($tables as $table_suffix => $display_name) {
            $table_name = $wpdb->prefix . $table_suffix;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
            $count = $exists ? $wpdb->get_var("SELECT COUNT(*) FROM $table_name") : 0;

            echo '<tr>';
            echo '<td>' . esc_html($display_name) . '</td>';
            echo '<td>';
            if ($exists) {
                echo '<span style="color: #00a32a;">✓ ' . esc_html__('Exists', 'senna-finance') . '</span>';
            } else {
                echo '<span style="color: #d63638;">✗ ' . esc_html__('Missing', 'senna-finance') . '</span>';
            }
            echo '</td>';
            echo '<td>' . number_format($count) . '</td>';
            echo '</tr>';
        }
    }

    /**
     * Get recent conversations rows
     */
    private function get_recent_conversations_rows()
    {
        global $wpdb;
        $conversations_table = $wpdb->prefix . 'sffc_conversations';
        $messages_table = $wpdb->prefix . 'sffc_messages';

        // Check if tables exist
        if ($wpdb->get_var("SHOW TABLES LIKE '$conversations_table'") != $conversations_table) {
            return '<tr><td colspan="5">' . esc_html__('Database tables not created yet', 'senna-finance') . '</td></tr>';
        }

        $conversations = $wpdb->get_results(
            "SELECT c.*, COUNT(m.id) as message_count 
             FROM $conversations_table c 
             LEFT JOIN $messages_table m ON c.id = m.conversation_id 
             GROUP BY c.id 
             ORDER BY c.created_at DESC 
             LIMIT 10"
        );

        if (empty($conversations)) {
            return '<tr><td colspan="5">' . esc_html__('No conversations yet', 'senna-finance') . '</td></tr>';
        }

        $output = '';
        foreach ($conversations as $conversation) {
            $user_name = $conversation->user_id ? get_userdata($conversation->user_id)->display_name : 'Guest';
            $mode = ucfirst($conversation->mode);
            $status = $conversation->status === 'active' ?
                '<span style="color: #00a32a;">Active</span>' :
                '<span style="color: #787c82;">Closed</span>';
            $created = human_time_diff(strtotime($conversation->created_at), current_time('timestamp')) . ' ago';

            $output .= sprintf(
                '<tr><td>%s</td><td>%s</td><td>%d</td><td>%s</td><td>%s</td></tr>',
                esc_html($user_name),
                esc_html($mode),
                $conversation->message_count,
                esc_html($created),
                $status
            );
        }

        return $output;
    }
}
