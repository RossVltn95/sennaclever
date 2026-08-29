<?php
/**
 * AJAX Registration Fix
 * Ensures Career Opportunities AJAX handlers are properly registered
 * This file is loaded by the main plugin to guarantee AJAX functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Force AJAX handler registration early in WordPress lifecycle
 */
add_action('init', function() {
    // Only run if plugin constants are defined
    if (!defined('SFFC_PLUGIN_DIR')) {
        return;
    }
    
    // Load the Career Opportunities class if not already loaded
    if (!class_exists('SFFC_Career_Opportunities_Simple')) {
        $class_file = SFFC_PLUGIN_DIR . 'includes/class-career-opportunities-simple.php';
        if (file_exists($class_file)) {
            require_once $class_file;
        }
    }
    
    // Ensure instance exists and AJAX handlers are registered
    if (class_exists('SFFC_Career_Opportunities_Simple')) {
        $instance = SFFC_Career_Opportunities_Simple::get_instance();
        
        // Check if AJAX actions are registered, if not, register them
        if (!has_action('wp_ajax_nopriv_sffc_get_opportunities')) {
            // Register for non-logged-in users (CRITICAL)
            add_action('wp_ajax_nopriv_sffc_get_opportunities', [$instance, 'ajax_get_opportunities']);
            add_action('wp_ajax_nopriv_sffc_save_opportunity', [$instance, 'ajax_save_opportunity']);
        }
        
        if (!has_action('wp_ajax_sffc_get_opportunities')) {
            // Register for logged-in users
            add_action('wp_ajax_sffc_get_opportunities', [$instance, 'ajax_get_opportunities']);
            add_action('wp_ajax_sffc_save_opportunity', [$instance, 'ajax_save_opportunity']);
        }
    }
}, 5); // Priority 5 - run early

/**
 * Ensure scripts are properly enqueued with localized AJAX URL
 */
add_action('wp_enqueue_scripts', function() {
    // Check if constants are defined
    if (!defined('SFFC_PLUGIN_URL') || !defined('SFFC_VERSION')) {
        return;
    }
    
    global $post;
    
    // Determine if we should load the scripts
    $should_load = false;

    if (is_page() && is_a($post, 'WP_Post')) {
        $content = (string) ($post->post_content ?? '');
        $should_load =
            has_shortcode($content, 'career_opportunities') ||
            strpos($content, '[career_opportunities') !== false;
    }
    
    // Method 4: Force load on specific pages by ID (if needed)
    // $specific_page_ids = []; // Add page IDs here if needed
    // if (is_page($specific_page_ids)) {
    //     $should_load = true;
    // }
    
    // Method 5: Allow forcing via URL parameter for testing
    if (isset($_GET['force_opportunities'])) {
        $should_load = true;
    }
    
    if ($should_load) {
        // Don't double-load if already enqueued by main class
        if (!wp_script_is('sffc-opportunities-simple', 'enqueued')) {
            // Ensure jQuery is loaded
            wp_enqueue_script('jquery');
            
            // Enqueue the opportunities script with unique handle
            wp_enqueue_script(
                'sffc-opportunities-ajax-fixed',
                SFFC_PLUGIN_URL . 'assets/js/opportunities-simple.js',
                ['jquery'],
                SFFC_VERSION . '.ajax-fix',
                true // Load in footer
            );
            
            // Localize the AJAX URL and nonce - CRITICAL
            wp_localize_script('sffc-opportunities-ajax-fixed', 'sffc_ajax', [
                'url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sffc_public_nonce'),
                'plugin_url' => SFFC_PLUGIN_URL,
                'debug' => defined('WP_DEBUG') && WP_DEBUG
            ]);
            
            // Also enqueue the CSS
            wp_enqueue_style(
                'sffc-opportunities-style-fixed',
                SFFC_PLUGIN_URL . 'assets/css/opportunities-simple.css',
                [],
                SFFC_VERSION . '.ajax-fix'
            );
            
            // Enqueue bulletproof CV tailoring integration
            wp_enqueue_script(
                'sffc-bulletproof-cv-integration',
                SFFC_PLUGIN_URL . 'assets/js/bulletproof-cv-integration.js',
                ['jquery', 'sffc-opportunities-ajax-fixed'],
                SFFC_VERSION . '.bulletproof',
                true // Load in footer
            );
        }
    }
}, 100); // Priority 100 - run after other scripts

/**
 * Debug helper - Add admin notice if AJAX handlers are not registered
 */
if (defined('WP_DEBUG') && WP_DEBUG) {
    add_action('admin_notices', function() {
        if (!has_action('wp_ajax_nopriv_sffc_get_opportunities')) {
            echo '<div class="notice notice-warning"><p>SFFC Warning: AJAX handlers for Career Opportunities are not registered!</p></div>';
        }
    });
}

/**
 * Alternative AJAX endpoint fallback (if main AJAX fails)
 * This creates a REST API endpoint as backup
 */
add_action('rest_api_init', function() {
    register_rest_route('sffc/v1', '/opportunities', [
        'methods' => 'POST',
        'callback' => function($request) {
            // Check if main class exists
            if (!class_exists('SFFC_Career_Opportunities_Simple')) {
                return new WP_Error('class_not_found', 'Career Opportunities class not found', ['status' => 500]);
            }
            
            // Get parameters
            $limit = $request->get_param('limit') ?: 6;
            $offset = $request->get_param('offset') ?: 0;
            
            // Get jobs from database
            $args = [
                'post_type' => 'sffc_job',
                'post_status' => 'publish',
                'posts_per_page' => $limit,
                'offset' => $offset,
                'orderby' => 'date',
                'order' => 'DESC'
            ];
            
            $query = new WP_Query($args);
            $jobs = [];
            
            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $post_id = get_the_ID();
                    
                    $jobs[] = [
                        'id' => $post_id,
                        'title' => get_the_title(),
                        'company' => get_post_meta($post_id, 'sffc_actual_company', true) ?: get_post_meta($post_id, 'sffc_company', true) ?: 'Company',
                        'location' => get_post_meta($post_id, 'sffc_location', true) ?: 'Location',
                        'salary_display' => get_post_meta($post_id, 'sffc_salary_display', true) ?: '',
                        'job_type' => get_post_meta($post_id, 'sffc_job_type', true) ?: 'Full-time',
                        'description' => wp_trim_words(get_the_content(), 30),
                        'match_score' => rand(70, 95),
                        'posted_date' => get_the_date()
                    ];
                }
                wp_reset_postdata();
            }
            
            return [
                'success' => true,
                'data' => [
                    'opportunities' => $jobs,
                    'total' => wp_count_posts('sffc_job')->publish ?: 0
                ]
            ];
        },
        'permission_callback' => '__return_true' // Public endpoint
    ]);
});

// Log for debugging
if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
    error_log('SFFC: AJAX Registration Fix loaded');
    
    add_action('init', function() {
        if (has_action('wp_ajax_nopriv_sffc_get_opportunities')) {
            error_log('SFFC: AJAX handlers successfully registered');
        } else {
            error_log('SFFC: WARNING - AJAX handlers NOT registered');
        }
    }, 20);
}
