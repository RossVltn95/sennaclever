<?php
/**
 * Intelligence Brief Settings
 *
 * Admin settings page for API keys and configuration.
 *
 * @package Skill_Farm_Finance_Career
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Intelligence_Settings {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Settings group
     */
    private $option_group = 'sffc_intelligence_settings';

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
        add_action('admin_menu', array($this, 'add_menu_page'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Add admin menu page
     */
    public function add_menu_page() {
        add_submenu_page(
            'edit.php?post_type=sffc_pe_news',
            __('Intelligence Settings', 'sffc'),
            __('Intelligence Settings', 'sffc'),
            'manage_options',
            'sffc-intelligence-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // Register settings
        register_setting($this->option_group, 'sffc_anthropic_key', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ));

        register_setting($this->option_group, 'sffc_alpha_vantage_key', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ));

        register_setting($this->option_group, 'sffc_finnhub_key', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ));

        // API Keys Section
        add_settings_section(
            'sffc_api_keys_section',
            __('API Keys', 'sffc'),
            array($this, 'render_api_keys_section'),
            'sffc-intelligence-settings'
        );

        // Anthropic API Key
        add_settings_field(
            'sffc_anthropic_key',
            __('Anthropic API Key', 'sffc'),
            array($this, 'render_api_key_field'),
            'sffc-intelligence-settings',
            'sffc_api_keys_section',
            array(
                'id' => 'sffc_anthropic_key',
                'description' => __('Required for Claude AI extraction and web search. Get your key at <a href="https://console.anthropic.com/" target="_blank">console.anthropic.com</a>', 'sffc'),
            )
        );

        // Alpha Vantage API Key
        add_settings_field(
            'sffc_alpha_vantage_key',
            __('Alpha Vantage API Key', 'sffc'),
            array($this, 'render_api_key_field'),
            'sffc-intelligence-settings',
            'sffc_api_keys_section',
            array(
                'id' => 'sffc_alpha_vantage_key',
                'description' => __('For stock data and company fundamentals. Free tier: 500 requests/day. Get your key at <a href="https://www.alphavantage.com/support/#api-key" target="_blank">alphavantage.co</a>', 'sffc'),
            )
        );

        // Finnhub API Key
        add_settings_field(
            'sffc_finnhub_key',
            __('Finnhub API Key', 'sffc'),
            array($this, 'render_api_key_field'),
            'sffc-intelligence-settings',
            'sffc_api_keys_section',
            array(
                'id' => 'sffc_finnhub_key',
                'description' => __('For IPO calendar and market news. Free tier available. Get your key at <a href="https://finnhub.io/" target="_blank">finnhub.io</a>', 'sffc'),
            )
        );

        // Data Sources Section
        add_settings_section(
            'sffc_data_sources_section',
            __('Free Data Sources (No API Key Required)', 'sffc'),
            array($this, 'render_data_sources_section'),
            'sffc-intelligence-settings'
        );
    }

    /**
     * Render API keys section description
     */
    public function render_api_keys_section() {
        echo '<p>' . esc_html__('Configure API keys for external data sources. These are used to enrich articles with company information, market data, and financial metrics.', 'sffc') . '</p>';
    }

    /**
     * Render data sources section
     */
    public function render_data_sources_section() {
        ?>
        <div class="sffc-data-sources-info">
            <p><?php esc_html_e('The following free data sources are used automatically without API keys:', 'sffc'); ?></p>
            <table class="widefat" style="max-width: 600px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Source', 'sffc'); ?></th>
                        <th><?php esc_html_e('Data Provided', 'sffc'); ?></th>
                        <th><?php esc_html_e('Status', 'sffc'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Wikidata</strong></td>
                        <td><?php esc_html_e('Company profiles, founding date, HQ, industry', 'sffc'); ?></td>
                        <td><span class="dashicons dashicons-yes-alt" style="color: green;"></span> <?php esc_html_e('Active', 'sffc'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>SEC EDGAR</strong></td>
                        <td><?php esc_html_e('US public company filings, financials', 'sffc'); ?></td>
                        <td><span class="dashicons dashicons-yes-alt" style="color: green;"></span> <?php esc_html_e('Active', 'sffc'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Wikipedia</strong></td>
                        <td><?php esc_html_e('Company descriptions, history', 'sffc'); ?></td>
                        <td><span class="dashicons dashicons-yes-alt" style="color: green;"></span> <?php esc_html_e('Active', 'sffc'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render API key field
     */
    public function render_api_key_field($args) {
        $id = $args['id'];
        $value = get_option($id, '');
        $masked_value = $value ? str_repeat('*', strlen($value) - 4) . substr($value, -4) : '';

        ?>
        <input
            type="password"
            id="<?php echo esc_attr($id); ?>"
            name="<?php echo esc_attr($id); ?>"
            value="<?php echo esc_attr($value); ?>"
            class="regular-text"
            autocomplete="off"
        />
        <?php if ($value) : ?>
            <span class="description" style="margin-left: 10px;">
                <?php esc_html_e('Current:', 'sffc'); ?> <code><?php echo esc_html($masked_value); ?></code>
            </span>
        <?php endif; ?>
        <p class="description"><?php echo wp_kses_post($args['description']); ?></p>
        <?php
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Check for settings saved message
        if (isset($_GET['settings-updated'])) {
            add_settings_error(
                'sffc_intelligence_messages',
                'sffc_intelligence_message',
                __('Settings Saved', 'sffc'),
                'updated'
            );
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php settings_errors('sffc_intelligence_messages'); ?>

            <form action="options.php" method="post">
                <?php
                settings_fields($this->option_group);
                do_settings_sections('sffc-intelligence-settings');
                submit_button(__('Save Settings', 'sffc'));
                ?>
            </form>

            <hr />

            <h2><?php esc_html_e('Test Data Enrichment', 'sffc'); ?></h2>
            <p><?php esc_html_e('Test the enrichment pipeline with a sample article.', 'sffc'); ?></p>

            <div id="sffc-enrichment-test">
                <p>
                    <label for="sffc-test-post-id"><?php esc_html_e('Post ID:', 'sffc'); ?></label>
                    <input type="number" id="sffc-test-post-id" class="small-text" />
                    <button type="button" id="sffc-test-enrichment" class="button button-secondary">
                        <?php esc_html_e('Test Enrichment', 'sffc'); ?>
                    </button>
                </p>
                <div id="sffc-enrichment-result" style="display: none; margin-top: 20px;">
                    <h4><?php esc_html_e('Result:', 'sffc'); ?></h4>
                    <pre id="sffc-enrichment-output" style="background: #f0f0f0; padding: 15px; overflow: auto; max-height: 400px;"></pre>
                </div>
            </div>

            <script>
            jQuery(document).ready(function($) {
                $('#sffc-test-enrichment').on('click', function() {
                    var postId = $('#sffc-test-post-id').val();

                    if (!postId) {
                        alert('Please enter a Post ID');
                        return;
                    }

                    var $btn = $(this);
                    $btn.prop('disabled', true).text('Testing...');

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'sffc_enrich_article',
                            nonce: '<?php echo wp_create_nonce('sffc_intelligence_brief'); ?>',
                            post_id: postId
                        },
                        success: function(response) {
                            $('#sffc-enrichment-result').show();
                            $('#sffc-enrichment-output').text(JSON.stringify(response, null, 2));
                        },
                        error: function(xhr, status, error) {
                            $('#sffc-enrichment-result').show();
                            $('#sffc-enrichment-output').text('Error: ' + error);
                        },
                        complete: function() {
                            $btn.prop('disabled', false).text('Test Enrichment');
                        }
                    });
                });
            });
            </script>
        </div>
        <?php
    }
}

// Initialize
SFFC_Intelligence_Settings::get_instance();
