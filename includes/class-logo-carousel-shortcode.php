<?php
/**
 * Logo Carousel Shortcode
 *
 * Displays a carousel of partner/client logos with customizable styles
 * including grayscale, opacity variations, and uniform sizing.
 *
 * Usage: [sffc_logo_carousel style="grayscale" speed="slow"]
 *
 * @package Senna_Finance
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Logo_Carousel_Shortcode {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', [$this, 'register_shortcodes']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function register_shortcodes() {
        add_shortcode('sffc_logo_carousel', [$this, 'render_carousel']);
    }

    public function enqueue_assets() {
        global $post;

        $has_shortcode = is_a($post, 'WP_Post')
            && has_shortcode((string) ($post->post_content ?? ''), 'sffc_logo_carousel');

        if (!$has_shortcode) {
            return;
        }

        wp_enqueue_style(
            'sffc-logo-carousel',
            SFFC_PLUGIN_URL . 'assets/css/logo-carousel.css',
            [],
            SFFC_VERSION
        );

        wp_enqueue_script(
            'sffc-logo-carousel',
            SFFC_PLUGIN_URL . 'assets/js/logo-carousel.js',
            ['jquery'],
            SFFC_VERSION,
            true
        );
    }

    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=sffc_recruiter_post',
            'Logo Carousel',
            'Logo Carousel',
            'manage_options',
            'sffc-logo-carousel',
            [$this, 'render_admin_page']
        );
    }

    public function register_settings() {
        register_setting('sffc_logo_carousel_settings', 'sffc_logo_carousel_images', [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_logo_data'],
            'default' => []
        ]);
    }

    /**
     * Sanitize logo data before saving
     */
    public function sanitize_logo_data($input) {
        if (!is_array($input)) {
            return [];
        }

        $sanitized = [];
        foreach ($input as $logo) {
            if (!is_array($logo)) {
                continue;
            }

            // Only save if we have required fields
            if (isset($logo['id']) && isset($logo['url'])) {
                $sanitized[] = [
                    'id' => absint($logo['id']),
                    'url' => esc_url_raw($logo['url']),
                    'alt' => isset($logo['alt']) ? sanitize_text_field($logo['alt']) : ''
                ];
            }
        }

        return $sanitized;
    }

    public function enqueue_admin_assets($hook) {
        if ('sffc_recruiter_post_page_sffc-logo-carousel' !== $hook) {
            return;
        }

        wp_enqueue_media();

        wp_enqueue_style(
            'sffc-logo-carousel-admin',
            SFFC_PLUGIN_URL . 'assets/css/logo-carousel-admin.css',
            [],
            SFFC_VERSION
        );

        wp_enqueue_script(
            'sffc-logo-carousel-admin',
            SFFC_PLUGIN_URL . 'assets/js/logo-carousel-admin.js',
            ['jquery', 'jquery-ui-sortable'],
            SFFC_VERSION,
            true
        );

        wp_localize_script('sffc-logo-carousel-admin', 'sffcLogoCarousel', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sffc_logo_carousel_nonce')
        ]);
    }

    public function render_admin_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $logos = get_option('sffc_logo_carousel_images', []);
        if (!is_array($logos)) {
            $logos = [];
        }
        ?>
        <div class="wrap sffc-logo-carousel-admin">
            <h1>Logo Carousel Manager</h1>
            <p>Add and manage logos for the carousel. Drag to reorder.</p>

            <form method="post" action="options.php">
                <?php settings_fields('sffc_logo_carousel_settings'); ?>

                <div class="sffc-logo-grid" id="sffc-logo-grid">
                    <?php foreach ($logos as $index => $logo):
                        // Safety checks for array keys
                        if (!is_array($logo) || !isset($logo['url']) || !isset($logo['id'])) {
                            continue;
                        }
                        $logo_id = isset($logo['id']) ? absint($logo['id']) : 0;
                        $logo_url = isset($logo['url']) ? esc_url($logo['url']) : '';
                        $logo_alt = isset($logo['alt']) ? esc_attr($logo['alt']) : '';
                    ?>
                        <div class="sffc-logo-item" data-index="<?php echo esc_attr($index); ?>">
                            <div class="sffc-logo-preview">
                                <img src="<?php echo $logo_url; ?>" alt="<?php echo $logo_alt; ?>" />
                            </div>
                            <input type="hidden" name="sffc_logo_carousel_images[<?php echo $index; ?>][id]" value="<?php echo $logo_id; ?>" />
                            <input type="hidden" name="sffc_logo_carousel_images[<?php echo $index; ?>][url]" value="<?php echo $logo_url; ?>" />
                            <input type="text"
                                   name="sffc_logo_carousel_images[<?php echo $index; ?>][alt]"
                                   placeholder="Company name"
                                   value="<?php echo $logo_alt; ?>"
                                   class="sffc-logo-alt" />
                            <button type="button" class="button sffc-remove-logo">Remove</button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <p>
                    <button type="button" class="button button-primary" id="sffc-add-logo">Add Logo</button>
                </p>

                <?php submit_button('Save Logos'); ?>
            </form>

            <div class="sffc-shortcode-info">
                <h2>Usage</h2>
                <p>Add this shortcode to any page or post:</p>
                <code>[sffc_logo_carousel]</code>

                <h3>Available Options:</h3>
                <ul>
                    <li><code>style</code> - Visual style: <strong>default</strong>, grayscale, opacity, hover-color, fade</li>
                    <li><code>speed</code> - Scroll speed: slow, <strong>medium</strong>, fast</li>
                    <li><code>height</code> - Logo height in pixels (default: 60)</li>
                    <li><code>width</code> - Logo width: <strong>auto</strong> or pixels (e.g., 120)</li>
                    <li><code>size_mode</code> - Sizing behavior: <strong>contain</strong> (fit within box), cover (fill box), fixed (exact size)</li>
                </ul>

                <h3>Examples:</h3>
                <p><strong>Basic with grayscale:</strong></p>
                <code>[sffc_logo_carousel style="grayscale" speed="slow"]</code>

                <p><strong>Custom height (taller logos):</strong></p>
                <code>[sffc_logo_carousel style="opacity" height="80"]</code>

                <p><strong>Fixed width and height (uniform boxes):</strong></p>
                <code>[sffc_logo_carousel width="120" height="80" size_mode="contain"]</code>

                <p><strong>Wide logos with auto width:</strong></p>
                <code>[sffc_logo_carousel height="60" width="auto"]</code>

                <p><strong>Fill boxes completely (may crop):</strong></p>
                <code>[sffc_logo_carousel width="150" height="100" size_mode="cover"]</code>
            </div>
        </div>
        <?php
    }

    public function render_carousel($atts = []) {
        $atts = shortcode_atts([
            'style' => 'default',      // default, grayscale, opacity, hover-color, fade
            'speed' => 'medium',       // slow, medium, fast
            'height' => '60',          // Logo height in pixels
            'width' => 'auto',         // Logo width: auto, or pixels (e.g., 120)
            'size_mode' => 'contain',  // contain (fit within box), cover (fill box), fixed (exact size)
        ], $atts);

        $logos = get_option('sffc_logo_carousel_images', []);
        if (!is_array($logos) || empty($logos)) {
            return '<div class="sffc-logo-carousel-empty">No logos added yet. Add logos from the admin panel.</div>';
        }

        $style_class = 'sffc-carousel-' . sanitize_html_class($atts['style']);
        $speed_class = 'sffc-carousel-speed-' . sanitize_html_class($atts['speed']);
        $size_mode_class = 'sffc-carousel-size-' . sanitize_html_class($atts['size_mode']);

        $logo_height = intval($atts['height']);
        $logo_width = ($atts['width'] === 'auto') ? 'auto' : intval($atts['width']);

        // Build inline styles for logo container
        $slide_styles = "height: {$logo_height}px;";
        if ($logo_width !== 'auto') {
            $slide_styles .= " width: {$logo_width}px;";
        }

        // Build inline styles for logo image
        $img_base_styles = "max-height: {$logo_height}px;";
        if ($logo_width !== 'auto') {
            $img_base_styles .= " max-width: {$logo_width}px;";
        }

        // Adjust object-fit based on size mode
        $object_fit = 'contain'; // default
        if ($atts['size_mode'] === 'cover') {
            $object_fit = 'cover';
        } elseif ($atts['size_mode'] === 'fixed') {
            $object_fit = 'fill';
        }

        ob_start();
        ?>
        <div class="sffc-logo-carousel <?php echo esc_attr($style_class); ?> <?php echo esc_attr($speed_class); ?> <?php echo esc_attr($size_mode_class); ?>"
             data-speed="<?php echo esc_attr($atts['speed']); ?>">
            <div class="sffc-logo-track">
                <?php
                // Duplicate logos for seamless loop
                $all_logos = array_merge($logos, $logos);
                foreach ($all_logos as $logo):
                    // Safety checks for array keys
                    if (!is_array($logo) || !isset($logo['url'])) {
                        continue;
                    }
                    $logo_url = esc_url($logo['url']);
                    $logo_alt = isset($logo['alt']) ? esc_attr($logo['alt']) : '';

                    // Build complete image style
                    $img_styles = $img_base_styles . " object-fit: {$object_fit};";
                ?>
                    <div class="sffc-logo-slide" style="<?php echo esc_attr($slide_styles); ?>">
                        <img src="<?php echo $logo_url; ?>"
                             alt="<?php echo $logo_alt; ?>"
                             loading="lazy"
                             style="<?php echo esc_attr($img_styles); ?>" />
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Initialize
SFFC_Logo_Carousel_Shortcode::get_instance();
