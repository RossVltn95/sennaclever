<?php
/**
 * CRM Signup Form Settings
 * Customization options for the signup form shortcode
 *
 * @package SennaCareers
 * @since 7.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_Signup_Form_Settings
{
    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('admin_menu', [$this, 'add_menu_page'], 60);
        add_action('admin_init', [$this, 'register_settings']);
    }

    /**
     * Add menu page under CRM
     */
    public function add_menu_page()
    {
        add_submenu_page(
            'sffc-crm',
            __('Signup Form Settings', 'senna-finance'),
            __('Signup Form', 'senna-finance'),
            'manage_options',
            'sffc-signup-form-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register settings
     */
    public function register_settings()
    {
        // Register all settings
        register_setting('sffc_signup_form_settings', 'sffc_signup_form_title');
        register_setting('sffc_signup_form_settings', 'sffc_signup_form_subtitle');
        register_setting('sffc_signup_form_settings', 'sffc_signup_form_button');
        register_setting('sffc_signup_form_settings', 'sffc_signup_form_redirect');
        register_setting('sffc_signup_form_settings', 'sffc_signup_form_show_logo');
        register_setting('sffc_signup_form_settings', 'sffc_signup_form_logo_url');
        register_setting('sffc_signup_form_settings', 'sffc_signup_form_bg_color');
        register_setting('sffc_signup_form_settings', 'sffc_signup_form_button_color');

        // Paid membership settings
        register_setting('sffc_signup_form_settings', 'sffc_signup_is_paid');
        register_setting('sffc_signup_form_settings', 'sffc_signup_memberpress_form_id');
        register_setting('sffc_signup_form_settings', 'sffc_signup_membership_price');
        register_setting('sffc_signup_form_settings', 'sffc_signup_membership_currency');
        register_setting('sffc_signup_form_settings', 'sffc_signup_memberpress_page_url');

        // Add settings section
        add_settings_section(
            'sffc_signup_form_general',
            __('Form Content', 'senna-finance'),
            null,
            'sffc-signup-form-settings'
        );

        add_settings_section(
            'sffc_signup_form_styling',
            __('Styling', 'senna-finance'),
            null,
            'sffc-signup-form-settings'
        );

        // Add settings fields
        add_settings_field(
            'sffc_signup_form_title',
            __('Form Title', 'senna-finance'),
            [$this, 'render_text_field'],
            'sffc-signup-form-settings',
            'sffc_signup_form_general',
            ['label_for' => 'sffc_signup_form_title', 'default' => 'Join MENA Careers']
        );

        add_settings_field(
            'sffc_signup_form_subtitle',
            __('Form Subtitle', 'senna-finance'),
            [$this, 'render_textarea_field'],
            'sffc-signup-form-settings',
            'sffc_signup_form_general',
            ['label_for' => 'sffc_signup_form_subtitle', 'default' => 'Access exclusive PE internships and connect directly with recruiters']
        );

        add_settings_field(
            'sffc_signup_form_button',
            __('Button Text', 'senna-finance'),
            [$this, 'render_text_field'],
            'sffc-signup-form-settings',
            'sffc_signup_form_general',
            ['label_for' => 'sffc_signup_form_button', 'default' => 'Create Account']
        );

        add_settings_field(
            'sffc_signup_form_redirect',
            __('Redirect URL', 'senna-finance'),
            [$this, 'render_text_field'],
            'sffc-signup-form-settings',
            'sffc_signup_form_general',
            ['label_for' => 'sffc_signup_form_redirect', 'default' => '/memberships/', 'description' => 'Where to redirect users after successful signup']
        );

        add_settings_field(
            'sffc_signup_form_show_logo',
            __('Show Logo', 'senna-finance'),
            [$this, 'render_checkbox_field'],
            'sffc-signup-form-settings',
            'sffc_signup_form_styling',
            ['label_for' => 'sffc_signup_form_show_logo']
        );

        add_settings_field(
            'sffc_signup_form_logo_url',
            __('Logo URL', 'senna-finance'),
            [$this, 'render_text_field'],
            'sffc-signup-form-settings',
            'sffc_signup_form_styling',
            ['label_for' => 'sffc_signup_form_logo_url', 'default' => 'https://joinsenna.com/wp-content/uploads/2024/03/Screenshot-2024-03-31-at-19.59.08.png']
        );

        add_settings_field(
            'sffc_signup_form_bg_color',
            __('Background Color', 'senna-finance'),
            [$this, 'render_color_field'],
            'sffc-signup-form-settings',
            'sffc_signup_form_styling',
            ['label_for' => 'sffc_signup_form_bg_color', 'default' => 'transparent']
        );

        add_settings_field(
            'sffc_signup_form_button_color',
            __('Button Color', 'senna-finance'),
            [$this, 'render_color_field'],
            'sffc-signup-form-settings',
            'sffc_signup_form_styling',
            ['label_for' => 'sffc_signup_form_button_color', 'default' => '#0D353E']
        );

        // Add paid membership section
        add_settings_section(
            'sffc_signup_form_membership',
            __('Paid Membership Settings', 'senna-finance'),
            [$this, 'render_membership_section_description'],
            'sffc-signup-form-settings'
        );

        add_settings_field(
            'sffc_signup_is_paid',
            __('Enable Paid Membership', 'senna-finance'),
            [$this, 'render_checkbox_field'],
            'sffc-signup-form-settings',
            'sffc_signup_form_membership',
            ['label_for' => 'sffc_signup_is_paid', 'description' => 'Enable this to redirect users to MemberPress for payment instead of free signup']
        );

        add_settings_field(
            'sffc_signup_memberpress_form_id',
            __('MemberPress Form ID', 'senna-finance'),
            [$this, 'render_text_field'],
            'sffc-signup-form-settings',
            'sffc_signup_form_membership',
            ['label_for' => 'sffc_signup_memberpress_form_id', 'default' => '234380', 'description' => 'The membership ID from MemberPress']
        );

        add_settings_field(
            'sffc_signup_membership_price',
            __('Membership Price', 'senna-finance'),
            [$this, 'render_text_field'],
            'sffc-signup-form-settings',
            'sffc_signup_form_membership',
            ['label_for' => 'sffc_signup_membership_price', 'default' => '29.99', 'description' => 'Enter the price amount (e.g., 29.99)']
        );

        add_settings_field(
            'sffc_signup_membership_currency',
            __('Price Currency', 'senna-finance'),
            [$this, 'render_currency_select_field'],
            'sffc-signup-form-settings',
            'sffc_signup_form_membership',
            ['label_for' => 'sffc_signup_membership_currency', 'default' => '', 'description' => 'The currency of the price above. Leave empty to use Currency Detector base currency.']
        );

        add_settings_field(
            'sffc_signup_memberpress_page_url',
            __('MemberPress Registration Page URL', 'senna-finance'),
            [$this, 'render_text_field'],
            'sffc-signup-form-settings',
            'sffc_signup_form_membership',
            ['label_for' => 'sffc_signup_memberpress_page_url', 'default' => '/register/', 'description' => 'The URL of your MemberPress registration page (e.g., /register/ or /membership/)']
        );
    }

    /**
     * Render membership section description
     */
    public function render_membership_section_description()
    {
        $base_currency = get_option('currency_detector_base_currency', 'USD');
        ?>
        <p style="margin-top: 0;">
            <?php esc_html_e('Configure paid membership settings. When enabled, users will be redirected to MemberPress for payment after entering their basic information.', 'senna-finance'); ?>
        </p>
        <p style="margin-top: 10px; padding: 10px; background: #e7f3ff; border-left: 4px solid #0073aa;">
            <strong><?php esc_html_e('Currency Detection:', 'senna-finance'); ?></strong>
            <?php printf(
                esc_html__('Your Currency Detector base currency is currently set to: %s. The price will be automatically converted to each user\'s local currency.', 'senna-finance'),
                '<strong>' . esc_html($base_currency) . '</strong>'
            ); ?>
        </p>
        <?php
    }

    /**
     * Render text field
     */
    public function render_text_field($args)
    {
        $value = get_option($args['label_for'], $args['default'] ?? '');
        ?>
        <input
            type="text"
            id="<?php echo esc_attr($args['label_for']); ?>"
            name="<?php echo esc_attr($args['label_for']); ?>"
            value="<?php echo esc_attr($value); ?>"
            class="regular-text">
        <?php if (!empty($args['description'])): ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render textarea field
     */
    public function render_textarea_field($args)
    {
        $value = get_option($args['label_for'], $args['default'] ?? '');
        ?>
        <textarea
            id="<?php echo esc_attr($args['label_for']); ?>"
            name="<?php echo esc_attr($args['label_for']); ?>"
            rows="3"
            class="large-text"><?php echo esc_textarea($value); ?></textarea>
        <?php if (!empty($args['description'])): ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render checkbox field
     */
    public function render_checkbox_field($args)
    {
        $value = get_option($args['label_for'], isset($args['default']) ? $args['default'] : '');
        $default_text = ($args['label_for'] === 'sffc_signup_form_show_logo')
            ? __('Display logo above the form', 'senna-finance')
            : __('Enable', 'senna-finance');
        ?>
        <label>
            <input
                type="checkbox"
                id="<?php echo esc_attr($args['label_for']); ?>"
                name="<?php echo esc_attr($args['label_for']); ?>"
                value="1"
                <?php checked($value, '1'); ?>>
            <?php echo esc_html($default_text); ?>
        </label>
        <?php if (!empty($args['description'])): ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render currency select field
     */
    public function render_currency_select_field($args)
    {
        $value = get_option($args['label_for'], $args['default'] ?? '');
        $base_currency = get_option('currency_detector_base_currency', 'USD');

        // Common currencies
        $currencies = [
            '' => sprintf(__('Auto (Currently: %s)', 'senna-finance'), $base_currency),
            'USD' => __('USD - US Dollar ($)', 'senna-finance'),
            'GBP' => __('GBP - British Pound (£)', 'senna-finance'),
            'EUR' => __('EUR - Euro (€)', 'senna-finance'),
            'AED' => __('AED - UAE Dirham (د.إ)', 'senna-finance'),
            'CAD' => __('CAD - Canadian Dollar (C$)', 'senna-finance'),
            'AUD' => __('AUD - Australian Dollar (A$)', 'senna-finance'),
            'CHF' => __('CHF - Swiss Franc (CHF)', 'senna-finance'),
            'SGD' => __('SGD - Singapore Dollar (S$)', 'senna-finance'),
            'HKD' => __('HKD - Hong Kong Dollar (HK$)', 'senna-finance'),
            'JPY' => __('JPY - Japanese Yen (¥)', 'senna-finance'),
            'CNY' => __('CNY - Chinese Yuan (¥)', 'senna-finance'),
            'INR' => __('INR - Indian Rupee (₹)', 'senna-finance'),
        ];
        ?>
        <select
            id="<?php echo esc_attr($args['label_for']); ?>"
            name="<?php echo esc_attr($args['label_for']); ?>"
            class="regular-text">
            <?php foreach ($currencies as $code => $label): ?>
                <option value="<?php echo esc_attr($code); ?>" <?php selected($value, $code); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (!empty($args['description'])): ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render color field
     */
    public function render_color_field($args)
    {
        $value = get_option($args['label_for'], $args['default'] ?? '#ffffff');
        ?>
        <input
            type="text"
            id="<?php echo esc_attr($args['label_for']); ?>"
            name="<?php echo esc_attr($args['label_for']); ?>"
            value="<?php echo esc_attr($value); ?>"
            class="sffc-color-picker"
            placeholder="<?php esc_attr_e('e.g., #ffffff or transparent', 'senna-finance'); ?>">
        <p class="description">
            <?php esc_html_e('Enter a hex color (e.g., #ffffff) or "transparent" for no background.', 'senna-finance'); ?>
        </p>
        <?php
    }

    /**
     * Render settings page
     */
    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Enqueue color picker
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div style="background: white; padding: 20px; margin: 20px 0; border-radius: 8px;">
                <h2><?php esc_html_e('Shortcode Usage', 'senna-finance'); ?></h2>
                <p><?php esc_html_e('Use this shortcode to display the signup form on any page:', 'senna-finance'); ?></p>
                <code style="display: block; padding: 12px; background: #f5f5f5; border-radius: 4px; margin: 10px 0;">[sffc_signup_form]</code>
                <p><?php esc_html_e('You can also override settings in the shortcode:', 'senna-finance'); ?></p>
                <code style="display: block; padding: 12px; background: #f5f5f5; border-radius: 4px; margin: 10px 0;">[sffc_signup_form title="Start Your Journey" button_text="Sign Up Now"]</code>
            </div>

            <form action="options.php" method="post">
                <?php
                settings_fields('sffc_signup_form_settings');
                do_settings_sections('sffc-signup-form-settings');
                submit_button(__('Save Settings', 'senna-finance'));
                ?>
            </form>

            <div style="background: #f9fafb; padding: 20px; margin: 20px 0; border-radius: 8px; border-left: 4px solid #0D353E;">
                <h3><?php esc_html_e('Preview', 'senna-finance'); ?></h3>
                <p><?php esc_html_e('To preview the signup form, create a new page and add the shortcode above.', 'senna-finance'); ?></p>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Initialize color pickers with support for "transparent"
            $('.sffc-color-picker').each(function() {
                var $input = $(this);
                var currentValue = $input.val();

                // Don't initialize color picker if value is "transparent"
                if (currentValue === 'transparent') {
                    // Just leave as text input
                    return;
                }

                // Initialize WordPress color picker
                $input.wpColorPicker({
                    change: function(event, ui) {
                        // Allow user to type "transparent" manually
                        var val = $input.val();
                        if (val === 'transparent') {
                            // Destroy color picker to allow text input
                            $input.wpColorPicker('destroy');
                        }
                    }
                });
            });

            // Allow typing "transparent" to disable color picker
            $('.sffc-color-picker').on('input', function() {
                var $input = $(this);
                if ($input.val() === 'transparent' && $input.hasClass('wp-color-picker')) {
                    $input.wpColorPicker('destroy');
                }
            });
        });
        </script>
        <?php
    }
}

// Initialize
SFFC_CRM_Signup_Form_Settings::get_instance();
