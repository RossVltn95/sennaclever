<?php

/**
 * AutoFill System Loader
 * 
 * Loads and initializes all AutoFill components
 * 
 * @package MENA Careers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_AutoFill_Loader
{

    /**
     * Instance
     */
    private static $instance = null;

    /**
     * Required files
     */
    private $required_files = [
        'includes/class-cv-upload-handler.php',
        'includes/class-ai-parser-service.php',
        'includes/class-document-parser.php',
        'includes/class-platform-patterns-manager.php',
        'includes/class-token-manager.php',
        'includes/class-error-recovery-system.php'
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Get instance
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load required files
     */
    private function load_dependencies()
    {
        $plugin_dir = plugin_dir_path(dirname(__FILE__));

        foreach ($this->required_files as $file) {
            $filepath = $plugin_dir . $file;
            if (file_exists($filepath)) {
                require_once $filepath;
            } else {
                error_log('SFFC AutoFill: Required file not found: ' . $file);
            }
        }
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // Add settings page
        add_action('admin_menu', [$this, 'add_admin_menu']);

        // Initialize components
        add_action('init', [$this, 'init_components']);

        // Add REST API endpoints
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        // Add shortcodes
        add_shortcode('sffc_cv_upload', [$this, 'render_cv_upload_interface']);
        add_shortcode('sffc_autofill_settings', [$this, 'render_autofill_settings']);
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes()
    {
        register_rest_route('sffc/v1', '/autofill/profile', [
            'methods' => 'GET',
            'callback' => [$this, 'get_user_profile'],
            'permission_callback' => function () {
                return is_user_logged_in();
            }
        ]);

        register_rest_route('sffc/v1', '/autofill/track', [
            'methods' => 'POST',
            'callback' => [$this, 'track_application'],
            'permission_callback' => function () {
                return is_user_logged_in();
            }
        ]);
    }

    /**
     * Get user profile for autofill
     */
    public function get_user_profile($request)
    {
        $user_id = get_current_user_id();

        // Get parsed CV data
        global $wpdb;
        $table_name = $wpdb->prefix . 'sffc_parsed_profiles';
        $profile = $wpdb->get_row($wpdb->prepare(
            "SELECT parsed_data FROM $table_name WHERE user_id = %d",
            $user_id
        ));

        if ($profile && $profile->parsed_data) {
            $parsed_data = json_decode($profile->parsed_data, true);

            // Add user meta data
            $user = wp_get_current_user();
            $parsed_data['personal']['email'] = $user->user_email;
            $parsed_data['personal']['first_name'] = get_user_meta($user_id, 'first_name', true);
            $parsed_data['personal']['last_name'] = get_user_meta($user_id, 'last_name', true);

            return rest_ensure_response($parsed_data);
        }

        // Return basic profile if no CV uploaded
        $user = wp_get_current_user();
        return rest_ensure_response([
            'personal' => [
                'email' => $user->user_email,
                'first_name' => get_user_meta($user_id, 'first_name', true),
                'last_name' => get_user_meta($user_id, 'last_name', true),
                'full_name' => $user->display_name
            ]
        ]);
    }

    /**
     * Track application
     */
    public function track_application($request)
    {
        $user_id = get_current_user_id();
        $params = $request->get_json_params();

        global $wpdb;
        $table_name = $wpdb->prefix . 'sffc_autofill_applications';

        $result = $wpdb->insert(
            $table_name,
            [
                'user_id' => $user_id,
                'platform' => sanitize_text_field($params['platform'] ?? 'unknown'),
                'company_name' => sanitize_text_field($params['company'] ?? ''),
                'position_title' => sanitize_text_field($params['position'] ?? ''),
                'application_url' => esc_url_raw($params['url'] ?? ''),
                'autofill_success' => $params['success'] ?? false,
                'fields_filled' => intval($params['fields_filled'] ?? 0),
                'fields_failed' => intval($params['fields_failed'] ?? 0),
                'error_log' => json_encode($params['errors'] ?? []),
                'time_spent' => intval($params['time_spent'] ?? 0),
                'applied_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%d', '%s']
        );

        return rest_ensure_response([
            'success' => $result !== false,
            'id' => $wpdb->insert_id
        ]);
    }

    /**
     * Initialize components
     */
    public function init_components()
    {
        // Initialize services if classes exist
        if (class_exists('SFFC_CV_Upload_Handler')) {
            SFFC_CV_Upload_Handler::get_instance();
        }

        if (class_exists('SFFC_Platform_Patterns_Manager')) {
            SFFC_Platform_Patterns_Manager::get_instance();
        }

        if (class_exists('SFFC_Token_Manager')) {
            SFFC_Token_Manager::get_instance();
        }

        if (class_exists('SFFC_Error_Recovery_System')) {
            SFFC_Error_Recovery_System::get_instance();
        }
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets()
    {
        // Only load on relevant pages
        if (!$this->should_load_assets()) {
            return;
        }

        $plugin_url = plugin_dir_url(dirname(__FILE__));
        $version = '1.0.0';

        // Enqueue styles
        wp_enqueue_style(
            'sffc-autofill-styles',
            $plugin_url . 'assets/css/autofill-styles.css',
            [],
            $version
        );

        // Enqueue scripts
        wp_enqueue_script(
            'sffc-cv-upload',
            $plugin_url . 'assets/js/cv-upload-handler.js',
            ['jquery'],
            $version,
            true
        );

        wp_enqueue_script(
            'sffc-manual-mapper',
            $plugin_url . 'assets/js/manual-field-mapper.js',
            ['jquery', 'jquery-ui-draggable', 'jquery-ui-droppable'],
            $version,
            true
        );

        // Localize script with AJAX data
        wp_localize_script('sffc-cv-upload', 'sffc_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sffc_public_nonce'),
            'user_id' => get_current_user_id(),
            'is_logged_in' => is_user_logged_in(),
            'upload_max_size' => 10485760, // 10MB
            'allowed_types' => ['pdf', 'doc', 'docx'],
            'strings' => [
                'upload_error' => __('Upload failed. Please try again.', 'senna'),
                'parse_error' => __('Failed to parse CV. Please try manual entry.', 'senna'),
                'network_error' => __('Network error. Please check your connection.', 'senna'),
                'success' => __('CV uploaded and parsed successfully!', 'senna')
            ]
        ]);

        // Add PDF.js for client-side PDF parsing
        wp_enqueue_script(
            'pdfjs',
            'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js',
            [],
            '3.11.174',
            true
        );

        // Add Mammoth.js for DOCX parsing
        wp_enqueue_script(
            'mammoth',
            'https://cdn.jsdelivr.net/npm/mammoth@1.6.0/mammoth.browser.min.js',
            [],
            '1.6.0',
            true
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook)
    {
        // Only load on our admin pages
        if (!strpos($hook, 'sffc-autofill')) {
            return;
        }

        $plugin_url = plugin_dir_url(dirname(__FILE__));
        $version = '1.0.0';

        wp_enqueue_style(
            'sffc-admin-styles',
            $plugin_url . 'assets/css/admin-styles.css',
            [],
            $version
        );

        wp_enqueue_script(
            'sffc-admin-scripts',
            $plugin_url . 'assets/js/admin-scripts.js',
            ['jquery'],
            $version,
            true
        );
    }

    /**
     * Check if assets should be loaded
     */
    private function should_load_assets()
    {
        // Only load if shortcode is present
        global $post;
        if ($post && (has_shortcode($post->post_content, 'sffc_cv_upload') ||
            has_shortcode($post->post_content, 'sffc_autofill_settings'))) {
            return true;
        }

        // Load on application pages with specific parameters
        if (isset($_GET['job_id']) || isset($_GET['application_id'])) {
            return true;
        }

        // Allow developers to force loading via filter
        return apply_filters('sffc_load_autofill_assets', false);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu()
    {
        add_submenu_page(
            'senna-settings',
            __('AutoFill Settings', 'senna'),
            __('AutoFill', 'senna'),
            'manage_options',
            'sffc-autofill-settings',
            [$this, 'render_admin_page']
        );
    }

    /**
     * Render admin page
     */
    public function render_admin_page()
    {
?>
        <div class="wrap">
            <h1><?php _e('senna AutoFill Settings', 'senna'); ?></h1>

            <div class="sffc-admin-tabs">
                <h2 class="nav-tab-wrapper">
                    <a href="#general" class="nav-tab nav-tab-active"><?php _e('General', 'senna'); ?></a>
                    <a href="#platforms" class="nav-tab"><?php _e('Platforms', 'senna'); ?></a>
                    <a href="#tokens" class="nav-tab"><?php _e('Tokens', 'senna'); ?></a>
                    <a href="#errors" class="nav-tab"><?php _e('Error Logs', 'senna'); ?></a>
                </h2>

                <div id="general" class="tab-content">
                    <form method="post" action="options.php">
                        <?php settings_fields('sffc_autofill_settings'); ?>

                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="sffc_openai_api_key"><?php _e('OpenAI API Key', 'senna'); ?></label>
                                </th>
                                <td>
                                    <input type="password" id="sffc_openai_api_key" name="sffc_openai_api_key"
                                        value="<?php echo esc_attr(get_option('sffc_openai_api_key')); ?>"
                                        class="regular-text" />
                                    <p class="description"><?php _e('Enter your OpenAI API key for AI-powered CV parsing', 'senna'); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="sffc_max_file_size"><?php _e('Max File Size (MB)', 'senna'); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="sffc_max_file_size" name="sffc_max_file_size"
                                        value="<?php echo esc_attr(get_option('sffc_max_file_size', 10)); ?>"
                                        min="1" max="50" />
                                    <p class="description"><?php _e('Maximum CV file size in megabytes', 'senna'); ?></p>
                                </td>
                            </tr>
                        </table>

                        <?php submit_button(); ?>
                    </form>
                </div>

                <div id="platforms" class="tab-content" style="display:none;">
                    <?php $this->render_platforms_tab(); ?>
                </div>

                <div id="tokens" class="tab-content" style="display:none;">
                    <?php $this->render_tokens_tab(); ?>
                </div>

                <div id="errors" class="tab-content" style="display:none;">
                    <?php $this->render_errors_tab(); ?>
                </div>
            </div>
        </div>
    <?php
    }

    /**
     * Render platforms tab
     */
    private function render_platforms_tab()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sffc_platform_patterns';

        $platforms = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY priority DESC", ARRAY_A);
    ?>
        <h3><?php _e('Supported Platforms', 'senna'); ?></h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Platform', 'senna'); ?></th>
                    <th><?php _e('URL Pattern', 'senna'); ?></th>
                    <th><?php _e('Priority', 'senna'); ?></th>
                    <th><?php _e('Success Rate', 'senna'); ?></th>
                    <th><?php _e('Status', 'senna'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($platforms as $platform): ?>
                    <tr>
                        <td><strong><?php echo esc_html($platform['platform_name']); ?></strong></td>
                        <td><code><?php echo esc_html($platform['url_pattern']); ?></code></td>
                        <td><?php echo esc_html($platform['priority']); ?></td>
                        <td><?php echo esc_html($platform['success_rate']); ?>%</td>
                        <td>
                            <?php if ($platform['is_active']): ?>
                                <span class="dashicons dashicons-yes" style="color:green;"></span>
                            <?php else: ?>
                                <span class="dashicons dashicons-no" style="color:red;"></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php
    }

    /**
     * Render tokens tab
     */
    private function render_tokens_tab()
    {
    ?>
        <h3><?php _e('Active Tokens', 'senna'); ?></h3>
        <div id="tokens-list">
            <!-- Will be populated via AJAX -->
        </div>
        <button class="button button-primary" id="generate-new-token">
            <?php _e('Generate New Token', 'senna'); ?>
        </button>
        <?php
    }

    /**
     * Render errors tab
     */
    private function render_errors_tab()
    {
        if (class_exists('SFFC_Error_Recovery_System')) {
            $error_system = SFFC_Error_Recovery_System::get_instance();
            $stats = $error_system->get_error_stats(7);
        ?>
            <h3><?php _e('Error Statistics (Last 7 Days)', 'senna'); ?></h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Error Code', 'senna'); ?></th>
                        <th><?php _e('Type', 'senna'); ?></th>
                        <th><?php _e('Count', 'senna'); ?></th>
                        <th><?php _e('Recovered', 'senna'); ?></th>
                        <th><?php _e('Recovery Rate', 'senna'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats as $stat): ?>
                        <tr>
                            <td><strong><?php echo esc_html($stat['error_code']); ?></strong></td>
                            <td><?php echo esc_html($stat['error_type']); ?></td>
                            <td><?php echo esc_html($stat['count']); ?></td>
                            <td><?php echo esc_html($stat['recovered']); ?></td>
                            <td><?php echo number_format($stat['recovery_rate'], 1); ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php
        }
    }

    /**
     * Render CV upload interface shortcode
     */
    public function render_cv_upload_interface($atts)
    {
        $atts = shortcode_atts([
            'show_title' => 'yes',
            'button_text' => __('Upload CV', 'senna'),
            'success_redirect' => ''
        ], $atts);

        ob_start();
        ?>
        <div id="sffc-cv-upload-wrapper" class="sffc-cv-upload-container">
            <?php if ($atts['show_title'] === 'yes'): ?>
                <h2><?php _e('Upload Your CV', 'senna'); ?></h2>
            <?php endif; ?>

            <div id="cv-upload-interface">
                <!-- Will be populated by JavaScript -->
            </div>

            <?php if (!empty($atts['success_redirect'])): ?>
                <script>
                    jQuery(document).on('cv:profile:completed', function(e, data) {
                        window.location.href = '<?php echo esc_url($atts['success_redirect']); ?>';
                    });
                </script>
            <?php endif; ?>
        </div>
    <?php
        return ob_get_clean();
    }

    /**
     * Render autofill settings shortcode
     */
    public function render_autofill_settings($atts)
    {
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to access AutoFill settings.', 'senna') . '</p>';
        }

        ob_start();
    ?>
        <div class="sffc-autofill-settings">
            <h3><?php _e('AutoFill Extension Settings', 'senna'); ?></h3>

            <div class="settings-section">
                <h4><?php _e('Chrome Extension', 'senna'); ?></h4>
                <p><?php _e('Install the senna AutoFill extension to automatically fill job applications.', 'senna'); ?></p>

                <div class="extension-status">
                    <span id="extension-status-indicator" class="status-unknown">
                        <?php _e('Checking extension status...', 'senna'); ?>
                    </span>
                </div>

                <div class="token-section">
                    <h4><?php _e('Access Token', 'senna'); ?></h4>
                    <div id="current-token">
                        <!-- Will be populated via JavaScript -->
                    </div>
                    <button class="button" id="generate-token">
                        <?php _e('Generate New Token', 'senna'); ?>
                    </button>
                </div>

                <div class="instructions">
                    <h4><?php _e('Setup Instructions', 'senna'); ?></h4>
                    <ol>
                        <li><?php _e('Install the Chrome extension', 'senna'); ?></li>
                        <li><?php _e('Generate an access token above', 'senna'); ?></li>
                        <li><?php _e('Enter the token in the extension', 'senna'); ?></li>
                        <li><?php _e('Start applying with AutoFill!', 'senna'); ?></li>
                    </ol>
                </div>
            </div>
        </div>
<?php
        return ob_get_clean();
    }
}

// Initialize the loader
add_action('plugins_loaded', function () {
    SFFC_AutoFill_Loader::get_instance();
});

// Register settings
add_action('admin_init', function () {
    register_setting('sffc_autofill_settings', 'sffc_openai_api_key');
    register_setting('sffc_autofill_settings', 'sffc_max_file_size');
});
