<?php

/**
 * Ultimate CV Tailoring Integration
 * Ensures the Ultimate CV Tailoring system is loaded and works with existing buttons
 * 
 * @package MENA Careers
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Ultimate_CV_Integration
{
    /**
     * Determine whether Ultimate CV assets are needed on the current frontend request.
     */
    private static function should_load_frontend_assets()
    {
        global $post;

        if (is_admin()) {
            return false;
        }

        if (!is_a($post, 'WP_Post')) {
            return false;
        }

        $content = (string) ($post->post_content ?? '');
        if ($content === '') {
            return false;
        }

        $shortcodes = array(
            'career_opportunities',
            'senna_reply',
            'sffc_crm_reddit_dashboard',
            'sffc_crm_reddit_feed',
            'sffc_crm_reddit_job',
            'sffc_pe_search',
            'sffc_pe_search_results',
            'sffc_application_audit',
            'sffc_audit_button',
        );

        foreach ($shortcodes as $shortcode) {
            if (has_shortcode($content, $shortcode) || stripos($content, '[' . $shortcode) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Initialize the integration
     */
    public static function init()
    {
        // Load the Ultimate CV Tailoring class if not already loaded
        add_action('plugins_loaded', array(__CLASS__, 'load_ultimate_cv_system'), 1);

        // Ensure assets are loaded on all relevant pages
        add_action('wp_enqueue_scripts', array(__CLASS__, 'ensure_assets_loaded'), 999);

        // Add inline script to connect existing buttons
        add_action('wp_footer', array(__CLASS__, 'add_integration_script'), 999);
    }

    /**
     * Load the Ultimate CV Tailoring system
     */
    public static function load_ultimate_cv_system()
    {
        $ultimate_cv_file = plugin_dir_path(dirname(__FILE__)) . 'includes/class-ultimate-cv-tailoring.php';

        if (file_exists($ultimate_cv_file)) {
            require_once $ultimate_cv_file;

            // The class self-initializes via add_action at the end of its file
            // No need for manual instantiation here
        }
    }

    /**
     * Ensure assets are loaded
     */
    public static function ensure_assets_loaded()
    {
        if (!self::should_load_frontend_assets()) {
            return;
        }

        if (!wp_script_is('sffc-ultimate-cv-script', 'enqueued')) {
            wp_enqueue_script(
                'sffc-ultimate-cv-script',
                plugin_dir_url(dirname(__FILE__)) . 'assets/js/ultimate-cv-tailoring.js',
                array('jquery'),
                '3.0.1',
                true
            );

            wp_localize_script('sffc-ultimate-cv-script', 'sffc_ultimate_cv', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sffc_ultimate_cv_nonce'),
                'max_file_size' => 10485760,
                'allowed_types' => array('pdf', 'doc', 'docx', 'txt'),
                'messages' => array(
                    'upload_success' => 'CV uploaded successfully!',
                    'upload_error' => 'Upload failed. Please try again.',
                    'tailoring_success' => 'CV tailored successfully!',
                    'tailoring_error' => 'Tailoring failed. Please try again.',
                    'no_cv' => 'Please upload your CV first.',
                    'no_job_title' => 'Job title is required.',
                    'processing' => 'Processing...'
                )
            ));
        }

        if (!wp_style_is('sffc-ultimate-cv-styles', 'enqueued')) {
            wp_enqueue_style(
                'sffc-ultimate-cv-styles',
                plugin_dir_url(dirname(__FILE__)) . 'assets/css/ultimate-cv-tailoring.css',
                array(),
                '3.0.1'
            );
        }
    }

    /**
     * Add integration script to connect everything
     */
    public static function add_integration_script()
    {
        if (!self::should_load_frontend_assets()) {
            return;
        }
?>
        <script type="text/javascript">
            (function($) {
                'use strict';

                // Integration checker
                function ensureUltimateCVIntegration() {
                    console.log('🔄 Checking Ultimate CV Integration...');

                    // Check if CVTailoringManager exists
                    if (typeof window.CVTailoringManager === 'undefined') {
                        console.warn('⚠️ CVTailoringManager not found, retrying...');
                        setTimeout(ensureUltimateCVIntegration, 500);
                        return;
                    }

                    // Find all tailor buttons
                    var buttons = $('.sffc-btn-tailor, .tailor-cv-btn, .cv-tailor-button');
                    console.log('✅ Found ' + buttons.length + ' CV tailor buttons');

                    // Ensure data attributes are set
                    buttons.each(function() {
                        var $btn = $(this);
                        var $card = $btn.closest('.sffc-match-card, .job-card, article');

                        // Try to extract and set job data if missing
                        if (!$btn.data('job-title')) {
                            var title = $card.find('.job-title, h3').first().text().trim();
                            if (title) $btn.attr('data-job-title', title);
                        }

                        if (!$btn.data('company')) {
                            var company = $card.find('.copany, .copany-name').first().text().trim();
                            if (company) $btn.attr('data-company', company);
                        }

                        if (!$btn.data('location')) {
                            var location = $card.find('.location').first().text().trim();
                            if (location) $btn.attr('data-location', location);
                        }
                    });

                    // Override any inline onclick handlers
                    buttons.each(function() {
                        var $btn = $(this);
                        if ($btn.attr('onclick')) {
                            $btn.removeAttr('onclick');
                            console.log('Removed inline onclick from button');
                        }
                    });

                    // Log successful integration
                    console.log('✅ Ultimate CV Tailoring Integration Complete');
                    console.log('   - Manager:', typeof window.CVTailoringManager);
                    console.log('   - Buttons:', buttons.length);
                    console.log('   - Ajax URL:', typeof sffc_ultimate_cv !== 'undefined' ? sffc_ultimate_cv.ajax_url : 'Not set');
                }

                // Run on document ready
                $(document).ready(function() {
                    setTimeout(ensureUltimateCVIntegration, 100);
                });

                // Also run when new content is loaded (for dynamic content)
                $(document).on('contentLoaded jobsLoaded ajaxComplete', function() {
                    setTimeout(ensureUltimateCVIntegration, 100);
                });

                // Monitor for dynamically added buttons
                var observer = new MutationObserver(function(mutations) {
                    var needsCheck = false;
                    mutations.forEach(function(mutation) {
                        if (mutation.addedNodes.length) {
                            $(mutation.addedNodes).each(function() {
                                if ($(this).find('.sffc-btn-tailor').length ||
                                    $(this).hasClass('sffc-btn-tailor')) {
                                    needsCheck = true;
                                }
                            });
                        }
                    });

                    if (needsCheck) {
                        setTimeout(ensureUltimateCVIntegration, 100);
                    }
                });

                // Start observing when ready
                $(document).ready(function() {
                    if (document.body) {
                        observer.observe(document.body, {
                            childList: true,
                            subtree: true
                        });
                    }
                });

            })(jQuery);
        </script>
<?php
    }
}

// Initialize the integration
SFFC_Ultimate_CV_Integration::init();
