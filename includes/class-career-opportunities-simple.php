<?php

/**
 * Career Opportunities - SIMPLE AND WORKING
 * No login required, no complex REST API, just working WordPress
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Career_Opportunities_Simple
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
        // Load currency manager
        require_once SFFC_PLUGIN_DIR . 'includes/class-currency-manager.php';

        // Register shortcode
        add_shortcode('career_opportunities', [$this, 'render_opportunities']);

        // Register AJAX handlers - NO LOGIN REQUIRED
        add_action('wp_ajax_nopriv_sffc_get_opportunities', [$this, 'ajax_get_opportunities']);
        add_action('wp_ajax_sffc_get_opportunities', [$this, 'ajax_get_opportunities']);

        // Register intelligent search endpoint
        add_action('wp_ajax_nopriv_sffc_intelligent_search', [$this, 'ajax_intelligent_search']);
        add_action('wp_ajax_sffc_intelligent_search', [$this, 'ajax_intelligent_search']);

        add_action('wp_ajax_nopriv_sffc_save_opportunity', [$this, 'ajax_save_opportunity']);
        add_action('wp_ajax_sffc_save_opportunity', [$this, 'ajax_save_opportunity']);

        // New enhanced handlers for Phase 2
        add_action('wp_ajax_nopriv_sffc_track_preference', [$this, 'ajax_track_preference']);
        add_action('wp_ajax_sffc_track_preference', [$this, 'ajax_track_preference']);

        // CRITICAL: Enqueue MENA Careers chat globally for Ultimate interface
        add_action('wp_enqueue_scripts', [$this, 'enqueue_senna_chat_globally'], 20);


        // Profile completion check
        add_action('wp_ajax_sffc_check_profile_completion', [$this, 'ajax_check_profile_completion']);
        add_action('init', [$this, 'register_live_expert_post_types'], 5); // Earlier priority
        add_action('wp_loaded', [$this, 'verify_post_types_registered'], 10);
        // OLD SLOW HANDLERS - DISABLED (replaced by fast handlers in class-live-expert-ajax.php)
        // add_action('wp_ajax_sffc_live_expert_message', [$this, 'ajax_live_expert_message']);
        // add_action('wp_ajax_nopriv_sffc_live_expert_message', [$this, 'ajax_live_expert_message']);
        // add_action('wp_ajax_sffc_live_expert_fetch', [$this, 'ajax_live_expert_fetch']);
        // add_action('wp_ajax_nopriv_sffc_live_expert_fetch', [$this, 'ajax_live_expert_fetch']);
        add_shortcode('senna_reply', [$this, 'render_live_expert_console']);
        add_action('wp_ajax_nopriv_sffc_check_profile_completion', [$this, 'ajax_check_profile_completion']);

        // Shared jobs handler - for external links
        add_action('wp_ajax_sffc_get_shared_jobs', [$this, 'ajax_get_shared_jobs']);
        add_action('wp_ajax_nopriv_sffc_get_shared_jobs', [$this, 'ajax_get_shared_jobs']);

        // Claude API job analysis
        add_action('wp_ajax_sffc_analyze_job_with_claude', [$this, 'ajax_analyze_job']);
        add_action('wp_ajax_nopriv_sffc_analyze_job_with_claude', [$this, 'ajax_analyze_job']);

        // Process chat queries with Claude
        add_action('wp_ajax_sffc_process_chat_query', [$this, 'ajax_process_chat_query']);
        add_action('wp_ajax_nopriv_sffc_process_chat_query', [$this, 'ajax_process_chat_query']);

        // Handle input field session continuation
        add_action('wp_ajax_sffc_get_input_session', [$this, 'ajax_get_input_session']);
        add_action('wp_ajax_nopriv_sffc_get_input_session', [$this, 'ajax_get_input_session']);

        // Enqueue assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    private function should_enqueue_senna_chat_assets()
    {
        global $post;

        if (isset($_GET['load_opportunities']) || isset($_GET['force_sffc'])) {
            return true;
        }

        if (!is_a($post, 'WP_Post')) {
            return false;
        }

        $content = (string) ($post->post_content ?? '');
        if ($content === '') {
            return false;
        }

        return
            has_shortcode($content, 'career_opportunities') ||
            has_shortcode($content, 'senna_reply') ||
            stripos($content, '[career_opportunities') !== false ||
            stripos($content, '[senna_reply') !== false;
    }

    /**
     * CRITICAL FIX: Enqueue MENA Careers Chat scripts globally
     * This ensures chat works on ALL pages, not just with the shortcode
     */
    public function enqueue_senna_chat_globally()
    {
        if (!$this->should_enqueue_senna_chat_assets()) {
            return;
        }

        // JS - Session State Manager (new)
        wp_enqueue_script(
            'sffc-session-state-manager',
            SFFC_PLUGIN_URL . 'assets/js/session-state-manager.js',
            ['jquery'],
            SFFC_VERSION,
            true
        );

        // JS - Conversation Flow Controller (new)
        wp_enqueue_script(
            'sffc-conversation-flow-controller',
            SFFC_PLUGIN_URL . 'assets/js/conversation-flow-controller.js',
            ['jquery', 'sffc-session-state-manager'],
            SFFC_VERSION,
            true
        );

        // JS - MENA Careers Chat Interface (must be global for Ultimate)
        wp_enqueue_script(
            'sffc-senna-chat',
            SFFC_PLUGIN_URL . 'assets/js/senna-chat.js',
            ['jquery', 'sffc-session-state-manager', 'sffc-conversation-flow-controller'],
            SFFC_VERSION,
            true
        );

        // Get career journey data for personalized AI responses
        $career_journey = [];
        if (is_user_logged_in()) {
            $intake_data = get_user_meta(get_current_user_id(), 'senna_intake_data', true);
            if (!empty($intake_data) && is_array($intake_data)) {
                $career_journey = [
                    'goal' => $intake_data['goal'] ?? '',
                    'situation' => $intake_data['situation'] ?? '',
                    'timeline' => $intake_data['timeline'] ?? '',
                    'challenge' => $intake_data['challenge'] ?? ''
                ];
            }
        }

        // Localize script with AJAX configuration
        wp_localize_script('sffc-senna-chat', 'sffc_ajax', [
            'url' => admin_url('admin-ajax.php'),
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sffc_public_nonce'),
            'plugin_url' => SFFC_PLUGIN_URL,
            'user_id' => get_current_user_id(),
            'user_logged_in' => is_user_logged_in(),
            'is_logged_in' => is_user_logged_in(),
            'career_journey' => $career_journey
        ]);

        // Trigger state manager ready event
        wp_add_inline_script('sffc-conversation-flow-controller', "
            jQuery(document).ready(function() {
                jQuery(document).trigger('stateManagerReady');
            });
        ");
    }

    /**
     * Enqueue assets - SIMPLE
     */
    public function enqueue_assets()
    {
        $should_load = $this->should_enqueue_senna_chat_assets();

        if ($should_load) {
            // Font Awesome for icons (fallback if theme doesn't have it)
            if (!wp_style_is('font-awesome', 'enqueued')) {
                wp_enqueue_style(
                    'font-awesome',
                    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
                    [],
                    '6.4.0'
                );
            }

            // CSS - Original (always load as it's working)
            wp_enqueue_style(
                'sffc-opportunities-simple',
                SFFC_PLUGIN_URL . 'assets/css/opportunities-simple.css',
                [],
                SFFC_VERSION
            );

            // CSS - MENA Careers Chat Interface
            wp_enqueue_style(
                'sffc-senna-chat',
                SFFC_PLUGIN_URL . 'assets/css/senna-chat.css',
                [],
                SFFC_VERSION
            );

            // CSS - MENA Careers Vogue Ultra-Premium Interface
            wp_enqueue_style(
                'sffc-senna-vogue',
                SFFC_PLUGIN_URL . 'assets/css/senna-vogue.css',
                [],
                SFFC_VERSION
            );

            // CSS - Currency Selector
            wp_enqueue_style(
                'sffc-currency-selector',
                SFFC_PLUGIN_URL . 'assets/css/currency-selector.css',
                [],
                SFFC_VERSION
            );

            // CSS - Shared Jobs Styling
            wp_enqueue_style(
                'sffc-shared-jobs',
                SFFC_PLUGIN_URL . 'assets/css/shared-jobs.css',
                [],
                SFFC_VERSION
            );

            // CSS - Skeleton Loader for shared jobs
            wp_enqueue_style(
                'sffc-skeleton-loader',
                SFFC_PLUGIN_URL . 'assets/css/skeleton-loader.css',
                [],
                SFFC_VERSION
            );

            // CSS - Ultimate Vogue Premium (10/10 Design)
            wp_enqueue_style(
                'sffc-ultimate-vogue',
                SFFC_PLUGIN_URL . 'assets/css/ultimate-vogue-premium.css',
                [],
                SFFC_VERSION
            );

            // CSS - Vogue Premium Fixes
            wp_enqueue_style(
                'sffc-vogue-fixes',
                SFFC_PLUGIN_URL . 'assets/css/vogue-premium-fixes.css',
                [],
                SFFC_VERSION
            );

            // CSS - SIMPLE FIXES (FULLSCREEN PROFILE)
            wp_enqueue_style(
                'sffc-simple-fixes',
                SFFC_PLUGIN_URL . 'assets/css/simple-fixes.css',
                [],
                SFFC_VERSION . '.simplefixes'
            );

            // WSJ CV RENDERER - CRITICAL FOR CV TAILORING
            // WSJ CV Display Styles
            wp_enqueue_style(
                'sffc-wsj-cv-display',
                SFFC_PLUGIN_URL . 'assets/css/wsj-cv-display.css',
                [],
                SFFC_VERSION
            );

            // WSJ CV Renderer Ultimate - New secure CV parsing and display
            wp_enqueue_script(
                'sffc-wsj-cv-renderer',
                SFFC_PLUGIN_URL . 'assets/js/wsj-cv-renderer-ultimate.js',
                [],
                SFFC_VERSION,
                true
            );

            // WSJ CV Integration - Connects upload to WSJ display
            wp_enqueue_script(
                'sffc-cv-upload-wsj',
                SFFC_PLUGIN_URL . 'assets/js/cv-upload-wsj-integration.js',
                ['jquery', 'sffc-wsj-cv-renderer'],
                SFFC_VERSION,
                true
            );

            // JS - Interested Counter
            wp_enqueue_script(
                'sffc-interested-counter',
                SFFC_PLUGIN_URL . 'assets/js/interested-counter.js',
                ['jquery'],
                SFFC_VERSION,
                true
            );

            // JS - Auto Scroll Messages (scrolls to first sentence of new messages)
            wp_enqueue_script(
                'sffc-auto-scroll-messages',
                SFFC_PLUGIN_URL . 'assets/js/auto-scroll-messages.js',
                ['jquery'],
                SFFC_VERSION,
                true
            );

            // Application system removed - CV Tailoring will be added here

            // Z-Index fixes removed - file doesn't exist

            // CSS - Enhanced Discovery (only load if requested)
            if (isset($_GET['enhanced_opportunities'])) {
                wp_enqueue_style(
                    'sffc-opportunity-discovery',
                    SFFC_PLUGIN_URL . 'assets/css/career-opportunity-discovery.css',
                    [],
                    SFFC_VERSION
                );
            }

            // jQuery (WordPress includes it)
            wp_enqueue_script('jquery');

            // Verification script removed - file doesn't exist

            // Our simple JS - always load as it's working
            // Removed dependency on non-existent application-error-fixes
            wp_enqueue_script(
                'sffc-opportunities-simple',
                SFFC_PLUGIN_URL . 'assets/js/opportunities-simple.js',
                ['jquery'],
                SFFC_VERSION,
                true
            );

            // MENA Careers chat is already enqueued globally - removed duplicate

            // JS - Intelligent Job Filter
            wp_enqueue_script(
                'sffc-intelligent-filter',
                SFFC_PLUGIN_URL . 'assets/js/intelligent-job-filter.js',
                ['jquery'],
                SFFC_VERSION,
                true
            );

            // JS - MENA Careers Conversational Interface (depends on intelligent filter)
            wp_enqueue_script(
                'sffc-senna-conversational',
                SFFC_PLUGIN_URL . 'assets/js/senna-conversational.js',
                ['jquery', 'sffc-intelligent-filter'],
                SFFC_VERSION,
                true
            );

            // JS - Intelligence Package (for job intelligence briefings)
            wp_enqueue_script(
                'sffc-intelligence-package',
                SFFC_PLUGIN_URL . 'assets/js/intelligence-package.js',
                ['jquery', 'sffc-senna-conversational'],
                SFFC_VERSION,
                true
            );

            // CSS - Intelligence Package Styles
            wp_enqueue_style(
                'sffc-intelligence-package',
                SFFC_PLUGIN_URL . 'assets/css/intelligence-package.css',
                ['sffc-mobile-interface-v2'],
                SFFC_VERSION
            );

            // JS - Autocomplete Overlay for suggestions
            wp_enqueue_script(
                'sffc-autocomplete-overlay',
                SFFC_PLUGIN_URL . 'assets/js/autocomplete-overlay.js',
                ['jquery', 'sffc-senna-conversational'],
                SFFC_VERSION,
                true
            );

            // Mobile Interface V2 - CSS (Works WITH desktop, not against it)
            wp_enqueue_style(
                'sffc-mobile-interface-v2',
                SFFC_PLUGIN_URL . 'assets/css/mobile-interface-v2.css',
                ['sffc-opportunities-simple'], // Removed non-existent pe-filters dependency
                SFFC_VERSION
            );

            // Mobile Interface V2 - JS (Waits for desktop elements, transforms them)
            wp_enqueue_script(
                'sffc-mobile-interface-v2',
                SFFC_PLUGIN_URL . 'assets/js/mobile-interface-v2.js',
                ['jquery', 'sffc-senna-conversational', 'sffc-pe-filter-cards-extended'],
                SFFC_VERSION,
                true
            );

            // JS - Action Cards System (70 AI action triggers for PE filter sidebar)
            // Load with higher priority to take control before other systems
            wp_enqueue_script(
                'sffc-action-cards-system',
                SFFC_PLUGIN_URL . 'assets/js/action-cards-system.js',
                ['jquery', 'sffc-senna-conversational', 'sffc-pe-filters'],
                SFFC_VERSION,
                true
            );

            // JS - PE Learning Exercises (Multi-step interactive learning in chat)
            // Works WITH action-cards-system to provide guided learning experiences
            wp_enqueue_script(
                'sffc-pe-learning-exercises',
                SFFC_PLUGIN_URL . 'assets/js/pe-learning-exercises.js',
                ['jquery', 'sffc-action-cards-system', 'sffc-senna-conversational'],
                SFFC_VERSION,
                true
            );

            // JS - Mobile Action Cards Fix (CRITICAL for mobile functionality)
            // Load last with all dependencies to ensure it overrides everything
            wp_enqueue_script(
                'sffc-mobile-action-cards-fix',
                SFFC_PLUGIN_URL . 'assets/js/mobile-action-cards-fix.js',
                ['jquery', 'sffc-action-cards-system', 'sffc-mobile-interface-v2', 'sffc-senna-conversational'],
                SFFC_VERSION,
                true // Load in footer
            );

            // Set high priority for this script
            wp_script_add_data('sffc-mobile-action-cards-fix', 'group', 1);

            // Add inline script to ensure it initializes after everything else
            wp_add_inline_script('sffc-mobile-action-cards-fix', '
                // Ensure mobile action cards fix runs last
                jQuery(window).on("load", function() {
                    setTimeout(function() {
                        console.log("[MobileActionFix] Final initialization check");
                        if (window.mobileActionCardsFix && !window.mobileActionCardsFix.initialized) {
                            window.mobileActionCardsFix.init();
                        }
                    }, 2000);
                });
            ');

            // JS - Mobile Chat Scroll Fix (Fixes bouncing and rough scrolling)
            wp_enqueue_script(
                'sffc-mobile-chat-scroll-fix',
                SFFC_PLUGIN_URL . 'assets/js/mobile-chat-scroll-fix.js',
                ['jquery', 'sffc-senna-conversational'],
                SFFC_VERSION,
                true
            );

            // CSS - Mobile Action Cards Fix (CRITICAL for mobile functionality)
            wp_enqueue_style(
                'sffc-mobile-action-cards-fix',
                SFFC_PLUGIN_URL . 'assets/css/mobile-action-cards-fix.css',
                ['sffc-senna-vogue'],
                SFFC_VERSION
            );

            // CSS - Mobile Chat Edge-to-Edge (Kindle-like reading experience)
            wp_enqueue_style(
                'sffc-mobile-chat-edge-to-edge',
                SFFC_PLUGIN_URL . 'assets/css/mobile-chat-edge-to-edge.css',
                ['sffc-mobile-interface-v2'],
                SFFC_VERSION
            );

            // CSS - CV Tailoring in Chat
            wp_enqueue_style(
                'sffc-cv-tailoring-chat',
                SFFC_PLUGIN_URL . 'assets/css/cv-tailoring-chat.css',
                ['sffc-senna-vogue'],
                SFFC_VERSION
            );

            // CSS - Conversation Flow (Follow-up questions and intelligent UI)
            wp_enqueue_style(
                'sffc-conversation-flow',
                SFFC_PLUGIN_URL . 'assets/css/conversation-flow.css',
                ['sffc-senna-vogue'],
                SFFC_VERSION
            );

            // CSS - PE Filters Simple Center (No overflow, proper centering)
            wp_enqueue_style(
                'sffc-pe-filters-simple',
                SFFC_PLUGIN_URL . 'assets/css/pe-filters-simple-center.css',
                ['sffc-senna-vogue'],
                SFFC_VERSION
            );

            // JS - PE Filters (Phase 1 Implementation)
            wp_enqueue_script(
                'sffc-pe-filters',
                SFFC_PLUGIN_URL . 'assets/js/pe-filters.js',
                ['jquery', 'sffc-senna-conversational'],
                SFFC_VERSION,
                true
            );

            // JS - PE Filter Cards Extended (200+ randomized cards with categories)
            // Now depends on action-cards-system to ensure proper load order
            wp_enqueue_script(
                'sffc-pe-filter-cards-extended',
                SFFC_PLUGIN_URL . 'assets/js/pe-filter-cards-extended.js',
                ['jquery', 'sffc-pe-filters', 'sffc-action-cards-system'],
                SFFC_VERSION,
                true
            );

            // JS - PE Filter Cards Initializer - DISABLED: Causes re-initialization conflicts
            // wp_enqueue_script(
            //     'sffc-pe-filter-cards-initializer',
            //     SFFC_PLUGIN_URL . 'assets/js/pe-filter-cards-initializer.js',
            //     ['jquery', 'sffc-pe-filter-cards-extended'],
            //     SFFC_VERSION . '.' . time(),
            //     true
            // );

            // JS - PE Job Comparison (Phase 1 Implementation)
            wp_enqueue_script(
                'sffc-pe-job-comparison',
                SFFC_PLUGIN_URL . 'assets/js/pe-job-comparison.js',
                ['jquery', 'sffc-senna-conversational'],
                SFFC_VERSION,
                true
            );

            // JS - PE Filter Suggestions (Phase 3 Implementation)
            wp_enqueue_script(
                'sffc-pe-filter-suggestions',
                SFFC_PLUGIN_URL . 'assets/js/pe-filter-suggestions.js',
                ['jquery', 'sffc-pe-filters'],
                SFFC_VERSION,
                true
            );

            // Localize PE Filters for AJAX
            wp_localize_script('sffc-pe-filters', 'peFiltersAjax', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('pe_filters_nonce')
            ]);

            // Get career journey data for personalized AI responses
            $career_journey = [];
            if (is_user_logged_in()) {
                $intake_data = get_user_meta(get_current_user_id(), 'senna_intake_data', true);
                if (!empty($intake_data) && is_array($intake_data)) {
                    $career_journey = [
                        'goal' => $intake_data['goal'] ?? '',
                        'situation' => $intake_data['situation'] ?? '',
                        'timeline' => $intake_data['timeline'] ?? '',
                        'challenge' => $intake_data['challenge'] ?? ''
                    ];
                }
            }

            // JS - Currency Handler
            wp_enqueue_script(
                'sffc-currency-handler',
                SFFC_PLUGIN_URL . 'assets/js/currency-handler.js',
                ['jquery'],
                SFFC_VERSION,
                true
            );

            // JS - WSJ CV Chat Integration (MUST load before tailor-cv-fix)
            wp_enqueue_script(
                'sffc-wsj-cv-chat',
                SFFC_PLUGIN_URL . 'assets/js/wsj-cv-chat-integration.js',
                ['jquery', 'sffc-senna-conversational', 'sffc-wsj-cv-renderer'],
                SFFC_VERSION,
                true
            );

            // JS - Tailor CV Fix
            wp_enqueue_script(
                'sffc-tailor-cv-fix',
                SFFC_PLUGIN_URL . 'assets/js/tailor-cv-fix.js',
                ['jquery', 'sffc-action-cards-system', 'sffc-senna-conversational', 'sffc-wsj-cv-chat'],
                SFFC_VERSION,
                true
            );

            // Localize script with AJAX data for CV tailoring
            wp_localize_script('sffc-tailor-cv-fix', 'sffc_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sffc_public_nonce'),
                'plugin_url' => SFFC_PLUGIN_URL,
                'is_logged_in' => is_user_logged_in() ? '1' : '0',
                'user_logged_in' => is_user_logged_in(),
                'user_id' => get_current_user_id(),
                'user_name' => wp_get_current_user()->first_name ?: 'there',
                'career_journey' => $career_journey
            ));

            // JS - Message Search
            wp_enqueue_script(
                'sffc-message-search',
                SFFC_PLUGIN_URL . 'assets/js/message-search.js',
                ['jquery'],
                SFFC_VERSION,
                true
            );

            // JS - Floating Search Icon
            wp_enqueue_script(
                'sffc-floating-search',
                SFFC_PLUGIN_URL . 'assets/js/floating-search.js',
                ['jquery'],
                SFFC_VERSION,
                true
            );


            // JS - Desktop Luxury Fixed (Clean & Working)
            wp_enqueue_script(
                'sffc-desktop-luxury-fixed',
                SFFC_PLUGIN_URL . 'assets/js/desktop-luxury-fixed.js',
                ['jquery', 'sffc-senna-conversational'],
                SFFC_VERSION,
                true
            );



            // Enhanced Discovery JS - only load if requested via URL parameter for testing
            if (isset($_GET['enhanced_opportunities'])) {
                wp_enqueue_script(
                    'sffc-opportunity-discovery',
                    SFFC_PLUGIN_URL . 'assets/js/career-opportunity-discovery.js',
                    [],
                    SFFC_VERSION,
                    true
                );
            }

            // Localize for AJAX - CRITICAL for AJAX to work
            wp_localize_script('sffc-opportunities-simple', 'sffc_ajax', [
                'url' => admin_url('admin-ajax.php'),
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sffc_public_nonce'),
                'user_name' => wp_get_current_user()->first_name ?: 'Your',
                'plugin_url' => SFFC_PLUGIN_URL,
                'login_url' => get_option('sffc_login_url', 'https://joinsenna.com/login-auth/'),
                'registration_url' => get_option('sffc_registration_url', 'https://joinsenna.com/memberships/'),
                'career_journey' => $career_journey
            ]);

            // Localize as sffc_ajax for the senna-conversational script
            wp_localize_script('sffc-senna-conversational', 'sffc_ajax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'url' => admin_url('admin-ajax.php'),
                'plugin_url' => SFFC_PLUGIN_URL,
                'nonce' => wp_create_nonce('sffc_public_nonce'),
                'is_logged_in' => is_user_logged_in() ? '1' : '0',
                'user_logged_in' => is_user_logged_in(),
                'user_id' => get_current_user_id(),
                'user_name' => wp_get_current_user()->first_name ?: 'there',
                'career_journey' => $career_journey
            ]);

            // Also localize sffc_frontend for backward compatibility
            wp_localize_script('sffc-senna-conversational', 'sffc_frontend', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'plugin_url' => SFFC_PLUGIN_URL,
                'nonce' => wp_create_nonce('sffc_public_nonce'),
                'is_logged_in' => is_user_logged_in() ? '1' : '0',
                'user_id' => get_current_user_id(),
                'career_journey' => $career_journey
            ]);
        }
    }

    /**
     * Render opportunities shortcode
     */
    public function render_opportunities($atts = [])
    {
        $atts = shortcode_atts([
            'limit' => 6,
            'style' => 'cards',
            'enhanced' => 'false'  // Add flag for enhanced version
        ], $atts);

        $quick_prompts = class_exists('SkillFarm_AI_Dashboard_Settings')
            ? SkillFarm_AI_Dashboard_Settings::get_quick_prompts()
            : array();

        ob_start();
?>
        <div class="sffc-opportunities-wrapper">

            <!-- Main Content Area -->
            <main class="sffc-main-container">
                <!-- Conversational View Only -->
                <div class="sffc-conversational-view" id="conversational-view">

                    <!-- Subtle User Profile Dropdown -->
                    <div class="sffc-user-dropdown-container">
                        <?php $current_user = wp_get_current_user(); ?>
                        <button class="sffc-user-dropdown-trigger" onclick="toggleUserDropdown(event)">
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($current_user->first_name ?: $current_user->display_name, 0, 1)); ?>
                            </div>
                            <span class="user-name"><?php echo esc_html($current_user->first_name ?: $current_user->display_name); ?></span>
                            <svg class="dropdown-arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="m6 9 6 6 6-6" />
                            </svg>
                        </button>

                        <div class="sffc-user-dropdown-menu" id="userDropdownMenu">
                            <a href="#" onclick="openProfileBuilder(); return false;" class="dropdown-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                                    <circle cx="12" cy="7" r="4" />
                                </svg>
                                <span>Profile</span>
                            </a>

                            <a href="#" onclick="openSupportPopup(); return false;" class="dropdown-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                                    <polyline points="22,6 12,13 2,6" />
                                </svg>
                                <span>Support</span>
                            </a>

                            <div class="dropdown-divider"></div>

                            <a href="<?php echo wp_logout_url(home_url()); ?>" class="dropdown-item logout">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                                    <polyline points="16 17 21 12 16 7" />
                                    <line x1="21" y1="12" x2="9" y2="12" />
                                </svg>
                                <span>Logout</span>
                            </a>
                        </div>
                    </div>

                    <!-- Support Email Popup -->
                    <div id="supportPopup" class="sffc-support-popup">
                        <div class="popup-overlay" onclick="closeSupportPopup()"></div>
                        <div class="popup-content">
                            <button class="popup-close" onclick="closeSupportPopup()">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="18" y1="6" x2="6" y2="18" />
                                    <line x1="6" y1="6" x2="18" y2="18" />
                                </svg>
                            </button>

                            <h3>Contact Support</h3>
                            <p>Send us a message and we'll get back to you as soon as possible.</p>

                            <form id="supportEmailForm" onsubmit="sendSupportEmail(event)">
                                <div class="form-group">
                                    <label for="supportSubject">Subject</label>
                                    <input type="text" id="supportSubject" name="subject" required placeholder="How can we help?">
                                </div>

                                <div class="form-group">
                                    <label for="supportMessage">Message</label>
                                    <textarea id="supportMessage" name="message" rows="6" required placeholder="Describe your issue or question..."></textarea>
                                </div>

                                <div class="form-actions">
                                    <button type="button" class="btn-cancel" onclick="closeSupportPopup()">Cancel</button>
                                    <button type="submit" class="btn-submit">
                                        <span>Send Message</span>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="22" y1="2" x2="11" y2="13" />
                                            <polygon points="22 2 15 22 11 13 2 9 22 2" />
                                        </svg>
                                    </button>
                                </div>
                            </form>

                            <div id="supportSuccess" class="support-success" style="display: none;">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                                    <polyline points="22 4 12 14.01 9 11.01" />
                                </svg>
                                <h4>Message Sent Successfully!</h4>
                                <p>We've received your message and will respond within 24 hours.</p>
                                <button onclick="closeSupportPopup()" class="btn-primary">Close</button>
                            </div>
                        </div>
                    </div>

                    <!-- Application Support Modal -->
                    <div id="applicationModal" class="sffc-application-modal" style="display: none;">
                        <div class="modal-overlay" onclick="closeApplicationModal()"></div>
                        <div class="modal-content">
                            <button class="modal-close" onclick="closeApplicationModal()">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="18" y1="6" x2="6" y2="18" />
                                    <line x1="6" y1="6" x2="18" y2="18" />
                                </svg>
                            </button>

                            <div class="modal-header">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#2D6A4F" stroke-width="2">
                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                                </svg>
                                <h2>Ready to Apply!</h2>
                            </div>

                            <div class="job-details-section">
                                <h3 id="applyJobTitle">Job Title</h3>
                                <p id="applyCompanyName">Company Name</p>
                            </div>

                            <div class="support-message">
                                <div class="message-box">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <path d="M12 6v6l4 2"></path>
                                    </svg>
                                    <p><strong>We're here to support you!</strong></p>
                                    <p>If you need help with any part of the application, just come back and ask. We can help with:</p>
                                    <ul>
                                        <li>Tailoring your CV for this specific role</li>
                                        <li>Writing a compelling cover letter</li>
                                        <li>Preparing for interviews</li>
                                        <li>Negotiating your offer</li>
                                    </ul>
                                </div>
                            </div>

                            <div class="application-link-section">
                                <label>Application Link:</label>
                                <div class="link-container">
                                    <input type="text" id="applicationLink" readonly>
                                    <button onclick="copyApplicationLink()" class="copy-btn">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                        </svg>
                                        <span>Copy</span>
                                    </button>
                                </div>
                                <span id="copySuccess" class="copy-success" style="display: none;">✓ Link copied!</span>
                            </div>

                            <div class="modal-actions">
                                <button onclick="tailorCVFirst()" class="btn-secondary">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                        <polyline points="14 2 14 8 20 8"></polyline>
                                    </svg>
                                    Tailor CV First
                                </button>
                                <button onclick="proceedToApplication()" class="btn-primary">
                                    Continue to Application
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M7 17l9.2-9.2M17 17V7H7" />
                                    </svg>
                                </button>
                            </div>

                            <div class="save-progress">
                                <input type="checkbox" id="saveInterest" checked>
                                <label for="saveInterest">Save this job to track my application progress</label>
                            </div>
                        </div>
                    </div>

                    <!-- Expert Advice Modal -->
                    <div id="adviceModal" style="display: none;">
                        <div class="sffc-advice-modal">
                            <div class="sffc-modal-header">
                                <button class="sffc-modal-close" onclick="closeAdviceModal()">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="18" y1="6" x2="6" y2="18" />
                                        <line x1="6" y1="6" x2="18" y2="18" />
                                    </svg>
                                </button>

                                <div class="sffc-modal-icon">
                                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                    </svg>
                                </div>
                                <h2 class="sffc-modal-title">Get Expert Advice</h2>
                                <p class="sffc-modal-subtitle">for <strong id="adviceJobTitle">Position</strong> at <strong id="adviceCompanyName">Company</strong></p>
                            </div>

                            <div class="sffc-modal-body">
                                <!-- Advice Type Selection -->
                                <div class="sffc-advice-options">
                                    <h3 class="sffc-section-title">What type of advice do you need?</h3>
                                    <div class="sffc-advice-cards">
                                        <div class="sffc-advice-card" onclick="selectAdviceType('quick')">
                                            <div class="sffc-advice-card-icon">
                                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <circle cx="12" cy="12" r="10" />
                                                    <polyline points="12 6 12 12 16 14" />
                                                </svg>
                                            </div>
                                            <h4>Quick Concept Check</h4>
                                            <p>Get a concise explanation and a practice prompt</p>
                                            <span class="sffc-advice-time">Ready now</span>
                                        </div>

                                        <div class="sffc-advice-card" onclick="selectAdviceType('expert')">
                                            <div class="sffc-advice-card-icon">
                                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                                                    <circle cx="12" cy="7" r="4" />
                                                </svg>
                                            </div>
                                            <h4>Deep Lesson</h4>
                                            <p>Work through the concept step by step</p>
                                            <span class="sffc-advice-time">Guided</span>
                                        </div>

                                        <div class="sffc-advice-card" onclick="selectAdviceType('peer')">
                                            <div class="sffc-advice-card-icon">
                                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                                    <circle cx="9" cy="7" r="4" />
                                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                                                    <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                                                </svg>
                                            </div>
                                            <h4>Practice Drill</h4>
                                            <p>Try a calculation and get feedback</p>
                                            <span class="sffc-advice-time">Interactive</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Specific Questions -->
                                <div class="sffc-advice-questions">
                                    <h3 class="sffc-section-title">Or ask specific questions:</h3>
                                    <div class="sffc-question-chips">
                                        <button class="sffc-chip" onclick="askQuestion('Teach me DCF valuation')">DCF valuation</button>
                                        <button class="sffc-chip" onclick="askQuestion('Walk me through portfolio construction')">Portfolio construction</button>
                                        <button class="sffc-chip" onclick="askQuestion('Give me an IRR and MOIC practice problem')">IRR & MOIC</button>
                                        <button class="sffc-chip" onclick="askQuestion('Teach me trading comparables')">Trading comps</button>
                                        <button class="sffc-chip" onclick="askQuestion('Explain fixed income duration')">Fixed income</button>
                                        <button class="sffc-chip" onclick="askQuestion('Teach me LBO basics')">LBO basics</button>
                                    </div>

                                    <div class="sffc-custom-question">
                                        <textarea id="customQuestion" placeholder="Or type your specific question here..." rows="3"></textarea>
                                        <button class="sffc-ask-btn" onclick="submitQuestion()">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <line x1="22" y1="2" x2="11" y2="13" />
                                                <polygon points="22 2 15 22 11 13 2 9 22 2" />
                                            </svg>
                                            Ask Question
                                        </button>
                                    </div>
                                </div>

                                <!-- Response Area (Hidden initially) -->
                                <div class="sffc-advice-response" id="adviceResponse" style="display: none;">
                                    <div class="sffc-response-header">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2D6A4F" stroke-width="2">
                                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                        </svg>
                                        <h4>Expert Insight</h4>
                                    </div>
                                    <div class="sffc-response-content" id="adviceContent">
                                        <!-- Dynamic content will be inserted here -->
                                    </div>
                                    <div class="sffc-response-actions">
                                        <button onclick="askAnother()" class="sffc-btn-secondary">Ask Another Question</button>
                                        <button onclick="saveAdvice()" class="sffc-btn-primary">Save This Advice</button>
                                    </div>
                                </div>
                            </div>

                            <div class="sffc-modal-footer">
                                <p class="sffc-footer-text">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                                    </svg>
                                    Premium members get unlimited guided lessons
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Message Search Bar -->
                    <div class="sffc-message-search">
                        <div class="sffc-search-wrapper">
                            <input type="text"
                                class="sffc-search-input"
                                id="message-search"
                                placeholder="Search conversations...">
                            <svg class="sffc-search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="m21 21-4.35-4.35"></path>
                            </svg>
                        </div>
                    </div>

                    <!-- Hamburger Menu Toggle (Mobile) -->
                    <button class="sffc-menu-toggle" id="menu-toggle" aria-label="Menu">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="3" y1="6" x2="21" y2="6"></line>
                            <line x1="3" y1="12" x2="21" y2="12"></line>
                            <line x1="3" y1="18" x2="21" y2="18"></line>
                        </svg>
                    </button>

                    <!-- Stage Menu Dropdown -->
                    <div class="sffc-stage-menu" id="stage-menu">
                        <button class="stage-menu-item active" data-stage="browse">
                            <svg class="menu-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="m21 21-4.35-4.35"></path>
                            </svg>
                            <span>Learning Path</span>
                        </button>
                        <button class="stage-menu-item" data-stage="analyze">
                            <svg class="menu-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="20" x2="18" y2="10"></line>
                                <line x1="12" y1="20" x2="12" y2="4"></line>
                                <line x1="6" y1="20" x2="6" y2="14"></line>
                            </svg>
                            <span>Practice & Feedback</span>
                        </button>
                        <button class="stage-menu-item" data-stage="apply">
                            <svg class="menu-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="14 2 20 8 8 20 3 21 4 16 16 4"></polygon>
                                <line x1="14.5" y1="5.5" x2="18.5" y2="9.5"></line>
                            </svg>
                            <span>Lesson Notes</span>
                        </button>
                    </div>


                    <!-- MENA Careers Conversation Area -->
                    <div class="sffc-senna-conversation">
                        <div class="senna-messages" id="senna-messages">
                            <!-- Tutor messages and lesson cards will appear here -->
                        </div>
                        <!-- Glassmorphism Autocomplete Input (connected to senna-conversational) -->
                        <div class="sffc-autocomplete-container">
                            <div class="sffc-autocomplete-wrapper">
                                <!-- Autocomplete Suggestions -->
                                <div class="sffc-autocomplete-suggestions" id="autocomplete-suggestions">
                                    <!-- Suggestions will appear here -->
                                </div>

                                <!-- Input Field with senna IDs for compatibility -->
                                <div class="sffc-input-group">
                                    <div class="sffc-chat-input-wrapper">
                                        <input type="text"
                                            id="senna-input"
                                            class="sffc-chat-input senna-input"
                                            placeholder="Ask MENA Careers for an IB, asset management, or private equity lesson..."
                                            autocomplete="off">
                                        <div class="sffc-input-actions">
                                            <button type="button" class="sffc-tutor-hint-trigger" aria-label="Show hint options" aria-expanded="false">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M9 18h6"></path>
                                                    <path d="M10 22h4"></path>
                                                    <path d="M12 2a7 7 0 0 0-4 12.74V17h8v-2.26A7 7 0 0 0 12 2z"></path>
                                                </svg>
                                            </button>
                                            <button type="button" class="sffc-save-lesson-trigger" aria-label="Save lesson transcript">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                                    <polyline points="7 3 7 8 15 8"></polyline>
                                                </svg>
                                            </button>
                                            <button id="senna-send" type="button" aria-label="Send message">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M22 2L11 13"></path>
                                                    <path d="M22 2L15 22L11 13L2 9L22 2Z"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="sffc-tutor-hint-drawer" hidden>
                                        <button type="button" data-tutor-hint="nudge">Nudge</button>
                                        <button type="button" data-tutor-hint="formula">Formula</button>
                                        <button type="button" data-tutor-hint="example">Mini example</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </main>

            <script>
                window.sffcQuickPrompts = <?php echo wp_json_encode($quick_prompts); ?>;
                (function() {
                    const promptChips = document.querySelectorAll('.sffc-prompt-chip[data-prompt]');

                    function sendSennaPrompt(text) {
                        if (!text) {
                            return;
                        }

                        const chatInput = document.querySelector('.sffc-chat-input');
                        if (chatInput) {
                            chatInput.value = text;
                            chatInput.dispatchEvent(new Event('input', {
                                bubbles: true
                            }));
                        }

                        if (typeof window.safeHandleInput === 'function') {
                            window.safeHandleInput(text);
                            return;
                        }

                        if (window.sennaConversational) {
                            if (typeof window.sennaConversational.handleUserInput === 'function') {
                                window.sennaConversational.handleUserInput(text);
                                return;
                            }

                            if (typeof window.sennaConversational.addUserMessage === 'function' &&
                                typeof window.sennaConversational.processUserIntent === 'function') {
                                window.sennaConversational.addUserMessage(text);
                                window.sennaConversational.processUserIntent(text);
                                return;
                            }
                        }

                        if (chatInput) {
                            const enterEvent = new KeyboardEvent('keydown', {
                                key: 'Enter',
                                code: 'Enter',
                                which: 13,
                                keyCode: 13,
                                bubbles: true
                            });
                            chatInput.dispatchEvent(enterEvent);
                        }
                    }

                    promptChips.forEach((chip) => {
                        chip.addEventListener('click', () => {
                            const prompt = chip.dataset.prompt || '';
                            sendSennaPrompt(prompt);
                        });
                    });
                })();
            </script>

            <!-- MENA Careers AI Chat Interface -->
            <?php echo do_shortcode('[senna_chat mode="overlay" theme="premium"]'); ?>

            <!-- Profile Builder Interface -->
            <?php echo do_shortcode('[sffc_profile_builder mode="fullscreen"]'); ?>
        </div>
    <?php
        return ob_get_clean();
    }

    /**
     * AJAX: Intelligent job search with tier-based results
     * SIMPLIFIED: Just use the working get_job_posts method with filtering
     */
    public function ajax_intelligent_search()
    {
        check_ajax_referer('sffc_public_nonce', 'nonce', false);

        $search_query = sanitize_text_field($_POST['query'] ?? '');
        $tier = sanitize_text_field($_POST['tier'] ?? 'perfect');
        $cv_data = $_POST['cv_data'] ?? [];
        $offset = intval($_POST['offset'] ?? 0);
        $limit = 8;

        // 🧠 Normalize search query before matching

        $search_lower = strtolower($search_query);
        $search_lower = preg_replace(
            '/^(?:can you\s+)?(?:please\s+)?(?:find|show|list|search|give me|show me|display|look for)?\s*(?:all\s+)?(?:the\s+)?(?:jobs?|roles?|positions?|opportunities?)?\s*(?:available\s+|open\s+|vacant\s+|for\s+)?(?:in|for)?\s*/i',
            '',
            $search_lower
        );
        $search_lower = preg_replace(
            '/(?:\s+please|\s+thanks?|\s+thank you|[?.!,]+)$/i',
            '',
            $search_lower
        );
        $search_lower = trim($search_lower);

        // Use the WORKING method to get ALL jobs (same as ajax_get_opportunities)
        $all_jobs = $this->get_job_posts(-1, 0); // -1 = get ALL jobs

        // Parse the search query  

        $filtered_jobs = [];

        // Check what type of search this is
        $show_all = strpos($search_lower, 'show me all') !== false ||
            strpos($search_lower, 'all opportunities') !== false ||
            strpos($search_lower, 'all jobs') !== false ||
            empty($search_query);

        if ($show_all) {
            // Return ALL jobs
            $filtered_jobs = $all_jobs;
        } else {
            // Apply filtering based on query
            foreach ($all_jobs as $job) {
                $match = false;

                // Convert to lowercase for comparison
                $title_lower = strtolower($job['title'] ?? '');
                $company_lower = strtolower($job['company'] ?? '');
                $location_lower = strtolower($job['location'] ?? '');


                // Location search (e.g., "London Opportunities")
                if (
                    (strpos($search_lower, 'london') !== false && strpos($location_lower, 'london') !== false)
                ) {
                    $match = true;
                } elseif (
                    (strpos($search_lower, 'paris') !== false && strpos($location_lower, 'paris') !== false)
                ) {
                    $match = true;
                } elseif (
                    (strpos($search_lower, 'frankfurt') !== false && strpos($location_lower, 'frankfurt') !== false)
                ) {
                    $match = true;
                } elseif (
                    (strpos($search_lower, 'berlin') !== false && strpos($location_lower, 'berlin') !== false)
                ) {
                    $match = true;
                } elseif (
                    (strpos($search_lower, 'munich') !== false && strpos($location_lower, 'munich') !== false) ||
                    (strpos($search_lower, 'münchen') !== false && strpos($location_lower, 'münchen') !== false)
                ) {
                    $match = true;
                } elseif (
                    (strpos($search_lower, 'zurich') !== false && strpos($location_lower, 'zurich') !== false) ||
                    (strpos($search_lower, 'zürich') !== false && strpos($location_lower, 'zürich') !== false)
                ) {
                    $match = true;
                } elseif (
                    (strpos($search_lower, 'geneva') !== false && strpos($location_lower, 'geneva') !== false) ||
                    (strpos($search_lower, 'genève') !== false && strpos($location_lower, 'genève') !== false) ||
                    (strpos($search_lower, 'geneve') !== false && strpos($location_lower, 'geneve') !== false)
                ) {
                    $match = true;
                } elseif (
                    (strpos($search_lower, 'milan') !== false && strpos($location_lower, 'milan') !== false) ||
                    (strpos($search_lower, 'milano') !== false && strpos($location_lower, 'milano') !== false)
                ) {
                    $match = true;
                } elseif (
                    (strpos($search_lower, 'rome') !== false && strpos($location_lower, 'rome') !== false) ||
                    (strpos($search_lower, 'roma') !== false && strpos($location_lower, 'roma') !== false)
                ) {
                    $match = true;
                } elseif (
                    (strpos($search_lower, 'madrid') !== false && strpos($location_lower, 'madrid') !== false)
                ) {
                    $match = true;
                } elseif (
                    (strpos($search_lower, 'barcelona') !== false && strpos($location_lower, 'barcelona') !== false)
                ) {
                    $match = true;
                } elseif (
                    (strpos($search_lower, 'lisbon') !== false && strpos($location_lower, 'lisbon') !== false) ||
                    (strpos($search_lower, 'lisboa') !== false && strpos($location_lower, 'lisboa') !== false)
                ) {
                    $match = true;
                } elseif (
                    (strpos($search_lower, 'amsterdam') !== false && strpos($location_lower, 'amsterdam') !== false)
                ) {
                    $match = true;
                } elseif (
                    (strpos($search_lower, 'brussels') !== false && strpos($location_lower, 'brussels') !== false) ||
                    (strpos($search_lower, 'bruxelles') !== false && strpos($location_lower, 'bruxelles') !== false) ||
                    (strpos($search_lower, 'bruxelas') !== false && strpos($location_lower, 'bruxelas') !== false)
                ) {
                    $match = true;
                } elseif (
                    (strpos($search_lower, 'luxembourg') !== false && strpos($location_lower, 'luxembourg') !== false) ||
                    (strpos($search_lower, 'luxemburg') !== false && strpos($location_lower, 'luxemburg') !== false)
                ) {
                    $match = true;
                } elseif (
                    (strpos($search_lower, 'vienna') !== false && strpos($location_lower, 'vienna') !== false) ||
                    (strpos($search_lower, 'wien') !== false && strpos($location_lower, 'wien') !== false)
                ) {
                    $match = true;
                } elseif (
                    (strpos($search_lower, 'stockholm') !== false && strpos($location_lower, 'stockholm') !== false)
                ) {
                    $match = true;
                } elseif (
                    (strpos($search_lower, 'copenhagen') !== false && strpos($location_lower, 'copenhagen') !== false) ||
                    (strpos($search_lower, 'københavn') !== false && strpos($location_lower, 'københavn') !== false)
                ) {
                    $match = true;
                } elseif (
                    (strpos($search_lower, 'oslo') !== false && strpos($location_lower, 'oslo') !== false)
                ) {
                    $match = true;
                } elseif (
                    (strpos($search_lower, 'dublin') !== false && strpos($location_lower, 'dublin') !== false)
                ) {
                    $match = true;
                } elseif (
                    (strpos($search_lower, 'warsaw') !== false && strpos($location_lower, 'warsaw') !== false) ||
                    (strpos($search_lower, 'warszawa') !== false && strpos($location_lower, 'warszawa') !== false)
                ) {
                    $match = true;
                } elseif (
                    (strpos($search_lower, 'prague') !== false && strpos($location_lower, 'prague') !== false) ||
                    (strpos($search_lower, 'praha') !== false && strpos($location_lower, 'praha') !== false)
                ) {
                    $match = true;
                } elseif (
                    (strpos($search_lower, 'budapest') !== false && strpos($location_lower, 'budapest') !== false)
                ) {
                    $match = true;
                } elseif (
                    (strpos($search_lower, 'bucharest') !== false && strpos($location_lower, 'bucharest') !== false) ||
                    (strpos($search_lower, 'bucuresti') !== false && strpos($location_lower, 'bucuresti') !== false)
                ) {
                    $match = true;
                }

                /** 🌎 SOUTH AMERICA **/
                elseif (
                    (strpos($search_lower, 'são paulo') !== false && strpos($location_lower, 'são paulo') !== false) ||
                    (strpos($search_lower, 'sao paulo') !== false && strpos($location_lower, 'sao paulo') !== false)
                ) {
                    $match = true;
                } elseif (
                    (strpos($search_lower, 'rio de janeiro') !== false && strpos($location_lower, 'rio de janeiro') !== false) ||
                    (strpos($search_lower, 'rio') !== false && strpos($location_lower, 'rio') !== false)
                ) {
                    $match = true;
                } elseif (
                    (strpos($search_lower, 'buenos aires') !== false && strpos($location_lower, 'buenos aires') !== false)
                ) {
                    $match = true;
                } elseif (
                    (strpos($search_lower, 'santiago') !== false && strpos($location_lower, 'santiago') !== false)
                ) {
                    $match = true;
                } elseif (
                    (strpos($search_lower, 'bogotá') !== false && strpos($location_lower, 'bogotá') !== false) ||
                    (strpos($search_lower, 'bogota') !== false && strpos($location_lower, 'bogota') !== false)
                ) {
                    $match = true;
                } elseif (
                    (strpos($search_lower, 'lima') !== false && strpos($location_lower, 'lima') !== false)
                ) {
                    $match = true;
                } elseif (
                    (strpos($search_lower, 'montevideo') !== false && strpos($location_lower, 'montevideo') !== false)
                ) {
                    $match = true;
                }

                /** 🌍 PRIVATE EQUITY **/
                elseif (
                    (strpos($search_lower, 'dubai') !== false && strpos($location_lower, 'dubai') !== false)
                ) {
                    $match = true;
                } elseif (
                    (strpos($search_lower, 'abu dhabi') !== false && strpos($location_lower, 'abu dhabi') !== false)
                ) {
                    $match = true;
                } elseif (
                    (strpos($search_lower, 'doha') !== false && strpos($location_lower, 'doha') !== false)
                ) {
                    $match = true;
                } elseif (
                    (strpos($search_lower, 'riyadh') !== false && strpos($location_lower, 'riyadh') !== false)
                ) {
                    $match = true;
                } elseif (
                    (strpos($search_lower, 'jeddah') !== false && strpos($location_lower, 'jeddah') !== false)
                ) {
                    $match = true;
                } elseif (
                    (strpos($search_lower, 'manama') !== false && strpos($location_lower, 'manama') !== false)
                ) {
                    $match = true;
                } elseif (
                    (strpos($search_lower, 'tel aviv') !== false && strpos($location_lower, 'tel aviv') !== false)
                ) {
                    $match = true;
                }

                /** 🇿🇦 AFRICA **/
                elseif (
                    (strpos($search_lower, 'johannesburg') !== false && strpos($location_lower, 'johannesburg') !== false) ||
                    (strpos($search_lower, 'joburg') !== false && strpos($location_lower, 'johannesburg') !== false)
                ) {
                    $match = true;
                }

                // Role/title search (e.g., "manager", "director")
                elseif (strpos($search_lower, 'manager') !== false && strpos($title_lower, 'manager') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'director') !== false && strpos($title_lower, 'director') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'analyst') !== false && strpos($title_lower, 'analyst') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'associate') !== false && strpos($title_lower, 'associate') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'intern') !== false && strpos($title_lower, 'intern') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'graduate') !== false && strpos($title_lower, 'graduate') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'partner') !== false && strpos($title_lower, 'partner') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'principal') !== false && strpos($title_lower, 'principal') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'vp') !== false && strpos($title_lower, 'vp') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'vice president') !== false && strpos($title_lower, 'vice president') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'cfo') !== false && strpos($title_lower, 'cfo') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'cio') !== false && strpos($title_lower, 'cio') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'ceo') !== false && strpos($title_lower, 'ceo') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'coo') !== false && strpos($title_lower, 'coo') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'accountant') !== false && strpos($title_lower, 'accountant') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'bookkeeper') !== false && strpos($title_lower, 'bookkeeper') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'controller') !== false && strpos($title_lower, 'controller') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'finance manager') !== false && strpos($title_lower, 'finance manager') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'financial controller') !== false && strpos($title_lower, 'financial controller') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'fp&a') !== false && strpos($title_lower, 'fp&a') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'financial planning') !== false && strpos($title_lower, 'financial planning') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'finance business partner') !== false && strpos($title_lower, 'finance business partner') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'cost accountant') !== false && strpos($title_lower, 'cost accountant') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'payroll') !== false && strpos($title_lower, 'payroll') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'treasury') !== false && strpos($title_lower, 'treasury') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'tax') !== false && strpos($title_lower, 'tax') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'auditor') !== false && strpos($title_lower, 'auditor') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'consultant') !== false && strpos($title_lower, 'consultant') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'strategy') !== false && strpos($title_lower, 'strategy') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'business development') !== false && strpos($title_lower, 'business development') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'investment associate') !== false && strpos($title_lower, 'investment associate') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'investment analyst') !== false && strpos($title_lower, 'investment analyst') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'private equity analyst') !== false && strpos($title_lower, 'private equity analyst') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'private equity associate') !== false && strpos($title_lower, 'private equity associate') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'private equity') !== false && strpos($title_lower, 'private equity') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'm&a analyst') !== false && strpos($title_lower, 'm&a analyst') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'm&a associate') !== false && strpos($title_lower, 'm&a associate') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'investment banking analyst') !== false && strpos($title_lower, 'investment banking analyst') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'investment banking associate') !== false && strpos($title_lower, 'investment banking associate') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'investment banker') !== false && strpos($title_lower, 'investment banker') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'venture capital') !== false && strpos($title_lower, 'venture capital') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'vc analyst') !== false && strpos($title_lower, 'vc analyst') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'real estate analyst') !== false && strpos($title_lower, 'real estate analyst') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'real estate associate') !== false && strpos($title_lower, 'real estate associate') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'credit analyst') !== false && strpos($title_lower, 'credit analyst') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'loan officer') !== false && strpos($title_lower, 'loan officer') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'trader') !== false && strpos($title_lower, 'trader') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'sales trader') !== false && strpos($title_lower, 'sales trader') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'quant') !== false && strpos($title_lower, 'quant') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'risk') !== false && strpos($title_lower, 'risk') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'esg') !== false && strpos($title_lower, 'esg') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'sustainability') !== false && strpos($title_lower, 'sustainability') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'investor relations') !== false && strpos($title_lower, 'investor relations') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'product specialist') !== false && strpos($title_lower, 'product specialist') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'fund accountant') !== false && strpos($title_lower, 'fund accountant') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'fund administrator') !== false && strpos($title_lower, 'fund administrator') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'data analyst') !== false && strpos($title_lower, 'data analyst') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'business analyst') !== false && strpos($title_lower, 'business analyst') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'project manager') !== false && strpos($title_lower, 'project manager') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'operations') !== false && strpos($title_lower, 'operations') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'middle office') !== false && strpos($title_lower, 'middle office') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'back office') !== false && strpos($title_lower, 'back office') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'legal counsel') !== false && strpos($title_lower, 'legal counsel') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'compliance') !== false && strpos($title_lower, 'compliance') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'human resources') !== false && strpos($title_lower, 'human resources') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'hr') !== false && strpos($title_lower, 'hr') !== false) {
                    $match = true;
                }

                /** 🌍 Language variants (Europe / South America)**/
                elseif (strpos($search_lower, 'analista') !== false && strpos($title_lower, 'analista') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'contador') !== false && strpos($title_lower, 'contador') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'gestor') !== false && strpos($title_lower, 'gestor') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'consultor') !== false && strpos($title_lower, 'consultor') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'asociado') !== false && strpos($title_lower, 'asociado') !== false) {
                    $match = true;
                } elseif (strpos($search_lower, 'director') !== false && strpos($title_lower, 'diretor') !== false) { // PT variant
                    $match = true;
                }

                // Company search
                elseif (!empty($company_lower) && strpos($search_lower, $company_lower) !== false) {
                    $match = true;
                }

                // ✅ Skills search (Quick Fix)
                elseif (!empty($job['skills']) && is_array($job['skills'])) {
                    foreach ($job['skills'] as $skill) {
                        if (stripos($search_lower, strtolower($skill)) !== false) {
                            $match = true;
                            break;
                        }
                    }
                }
                // General text match
                elseif (
                    strpos($title_lower, $search_lower) !== false ||
                    strpos($company_lower, $search_lower) !== false ||
                    strpos($location_lower, $search_lower) !== false
                ) {
                    $match = true;
                }

                // If CV data provided, enhance matching with CV-based scoring
                if ($match && !empty($cv_data)) {
                    // Add match score based on CV
                    $job['match_score'] = $this->calculate_cv_match_score($job, $cv_data);
                }

                if ($match) {
                    $filtered_jobs[] = $job;
                }
            }
        }
        // ✅ Deduplicate jobs by ID before sorting & pagination
        $filtered_jobs = array_values(array_reduce($filtered_jobs, function ($carry, $item) {
            $carry[$item['id']] = $item;
            return $carry;
        }, []));

        // Sort by match score if CV data provided
        if (!empty($cv_data) && !empty($filtered_jobs)) {
            usort($filtered_jobs, function ($a, $b) {
                $score_a = $a['match_score'] ?? 50;
                $score_b = $b['match_score'] ?? 50;
                return $score_b - $score_a;
            });
        }

        // Apply pagination
        $paged_jobs = array_slice($filtered_jobs, $offset, $limit);

        wp_send_json_success([
            'jobs' => $paged_jobs,
            'tier' => $tier,
            'total' => count($filtered_jobs),
            'hasMore' => count($filtered_jobs) > ($offset + $limit)
        ]);
    }

    /**
     * Calculate match score based on CV data
     */
    private function calculate_cv_match_score($job, $cv_data)
    {
        $score = 50; // Base score

        // Location match
        if (!empty($cv_data['location']) && !empty($job['location'])) {
            if (stripos($job['location'], $cv_data['location']) !== false) {
                $score += 20;
            }
        }

        // Seniority match (if we have it)
        if (!empty($cv_data['seniority']) && !empty($job['title'])) {
            $job_seniority = $this->extract_seniority_from_title($job['title']);
            if ($job_seniority == $cv_data['seniority']) {
                $score += 15;
            } elseif (abs($job_seniority - $cv_data['seniority']) <= 1) {
                $score += 10;
            }
        }

        // Industry/sector match
        if (!empty($cv_data['sectors']) && is_array($cv_data['sectors'])) {
            foreach ($cv_data['sectors'] as $sector) {
                if (
                    stripos($job['description'] ?? '', $sector) !== false ||
                    stripos($job['company'] ?? '', $sector) !== false
                ) {
                    $score += 15;
                    break;
                }
            }
        }

        return min($score, 100); // Cap at 100
    }

    /**
     * Extract seniority level from job title
     */
    private function extract_seniority_from_title($title)
    {
        $title_lower = strtolower($title);

        if (strpos($title_lower, 'intern') !== false || strpos($title_lower, 'summer analyst') !== false || strpos($title_lower, 'placement') !== false || strpos($title_lower, 'apprentice') !== false) return 1;
        if (strpos($title_lower, 'graduate') !== false || strpos($title_lower, 'trainee') !== false) return 2;
        if (strpos($title_lower, 'junior') !== false) return 3;
        if (strpos($title_lower, 'research analyst') !== false || (strpos($title_lower, 'analyst') !== false && strpos($title_lower, 'senior') === false)) return 4;
        if (strpos($title_lower, 'associate') !== false && strpos($title_lower, 'senior') === false) return 5;
        if (strpos($title_lower, 'senior analyst') !== false || (strpos($title_lower, 'analyst') !== false && strpos($title_lower, 'senior') !== false)) return 6;
        if (strpos($title_lower, 'senior associate') !== false || (strpos($title_lower, 'associate') !== false && strpos($title_lower, 'senior') !== false)) return 7;
        if (strpos($title_lower, 'consultant') !== false && strpos($title_lower, 'senior') === false) return 8;
        if (strpos($title_lower, 'senior consultant') !== false) return 9;
        if (strpos($title_lower, 'manager') !== false && strpos($title_lower, 'senior') === false) return 10;
        if (strpos($title_lower, 'senior manager') !== false) return 11;
        if (strpos($title_lower, 'lead') !== false || strpos($title_lower, 'team lead') !== false) return 12;
        if (strpos($title_lower, 'avp') !== false || strpos($title_lower, 'assistant vice president') !== false) return 13;
        if (strpos($title_lower, 'vp') !== false || strpos($title_lower, 'vice president') !== false) return 14;
        if (strpos($title_lower, 'executive director') !== false) return 15;
        if (strpos($title_lower, 'director') !== false && strpos($title_lower, 'managing') === false && strpos($title_lower, 'executive') === false) return 16;
        if (strpos($title_lower, 'head of') !== false || strpos($title_lower, 'head,') !== false) return 17;
        if (strpos($title_lower, 'managing director') !== false || strpos($title_lower, 'md') !== false) return 18;
        if (strpos($title_lower, 'partner') !== false || strpos($title_lower, 'principal') !== false) return 19;

        return 5; // Default to mid-level
    }

    /**
     * Get all jobs for intelligent search - uses same format as working method
     */
    private function get_all_jobs_for_search($search_query = '')
    {
        // Use the SAME approach as get_job_posts but get ALL jobs
        $args = [
            'post_type' => 'sffc_job',
            'post_status' => 'publish',
            'posts_per_page' => -1, // Get ALL jobs
            'orderby' => 'rand',
            'order' => 'DESC'
        ];

        $query = new WP_Query($args);
        $jobs = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();

                // Use EXACT same data retrieval as the working get_job_posts method
                $company = get_post_meta($post_id, 'sffc_actual_company', true) ?: get_post_meta($post_id, 'sffc_company', true) ?: 'Company';
                $location = get_post_meta($post_id, 'sffc_location', true) ?: get_post_meta($post_id, 'location', true) ?: 'Location';
                $salary_display = get_post_meta($post_id, 'sffc_salary_display', true);
                $highlights = get_post_meta($post_id, 'sffc_highlights', true);

                // Parse salary
                $salary_min = 0;
                $salary_max = 0;
                if ($salary_display) {
                    if (preg_match_all('/\$(\d+)k?/i', $salary_display, $matches)) {
                        $salary_min = isset($matches[1][0]) ? intval($matches[1][0]) * 1000 : 0;
                        $salary_max = isset($matches[1][1]) ? intval($matches[1][1]) * 1000 : 0;
                    }
                }

                $skills = get_post_meta($post_id, 'sffc_skills', true) ?: [];

                // Build job data in SAME format as working method
                $jobs[] = [
                    'id' => $post_id,
                    'title' => get_the_title(),
                    'company' => $company,
                    'location' => $location,
                    'salary_min' => $salary_min,
                    'salary_max' => $salary_max,
                    'salary_display' => $salary_display ?: '',
                    'job_type' => get_post_meta($post_id, 'sffc_job_type', true) ?: 'Full-time',
                    'description' => wp_trim_words(get_the_content(), 30),
                    'match_score' => 75, // Default score
                    'match_reasons' => [],
                    'posted_date' => get_the_date(),
                    'highlights' => $highlights ?: [],
                    'skills' => is_array($skills) ? array_slice($skills, 0, 10) : []
                ];
            }
        }
        wp_reset_postdata();

        return $jobs;
    }

    /**
     * AJAX: Get opportunities - PUBLIC ACCESS
     */

    public function register_live_expert_post_types()
    {
        if (!post_type_exists('sffc_live_chat')) {
            $conversation_result = register_post_type('sffc_live_chat', [
                'label' => __('Live Expert Conversations', 'senna'),
                'labels' => [
                    'name' => __('Live Expert Conversations', 'senna'),
                    'singular_name' => __('Live Expert Conversation', 'senna'),
                ],
                'public' => false,
                'show_ui' => false,
                'supports' => ['title', 'author'],
                'hierarchical' => false,
                'rewrite' => false,
                'query_var' => false,
                'can_export' => false,
                'capability_type' => 'post',
                'map_meta_cap' => true,
                'exclude_from_search' => true,
                'publicly_queryable' => false,
                'show_in_nav_menus' => false,
                'show_in_menu' => false,
                'show_in_admin_bar' => false,
                'show_in_rest' => false,
                'delete_with_user' => false,
            ]);

            if (is_wp_error($conversation_result)) {
                error_log('SFFC Live Expert: Failed to register sffc_live_chat post type - ' . $conversation_result->get_error_message());
            } else {
                error_log('SFFC Live Expert: Successfully registered sffc_live_chat post type');
            }
        }

        if (!post_type_exists('sffc_chat_message')) {
            $result = register_post_type('sffc_chat_message', [
                'label' => __('Live Expert Messages', 'senna'),
                'labels' => [
                    'name' => __('Live Expert Messages', 'senna'),
                    'singular_name' => __('Live Expert Message', 'senna'),
                ],
                'public' => false,
                'show_ui' => false,
                'supports' => ['editor', 'author', 'title'],
                'hierarchical' => false,
                'rewrite' => false,
                'query_var' => false,
                'can_export' => false,
                'capability_type' => 'post',
                'map_meta_cap' => true,
                'exclude_from_search' => true,
                'publicly_queryable' => false,
                'show_in_nav_menus' => false,
                'show_in_menu' => false,
                'show_in_admin_bar' => false,
                'show_in_rest' => false,
                'delete_with_user' => false,
            ]);

            if (is_wp_error($result)) {
                error_log('SFFC Live Expert: Failed to register sffc_chat_message post type - ' . $result->get_error_message());
            } else {
                error_log('SFFC Live Expert: Successfully registered sffc_chat_message post type');
            }
        }

        // Verify post types are registered and available
        add_action('wp_loaded', function () {
            if (!post_type_exists('sffc_chat_message')) {
                error_log('SFFC Live Expert: WARNING - Post type sffc_chat_message not found after wp_loaded');

                // Emergency re-registration
                register_post_type('sffc_chat_message', [
                    'label' => __('Live Expert Messages', 'senna'),
                    'public' => false,
                    'supports' => ['editor', 'author', 'title'],
                    'capability_type' => 'post',
                    'map_meta_cap' => true,
                    'show_ui' => false,
                    'query_var' => false,
                    'rewrite' => false,
                ]);

                error_log('SFFC Live Expert: Emergency re-registration attempted for sffc_chat_message');
            } else {
                error_log('SFFC Live Expert: Post type sffc_chat_message confirmed available after wp_loaded');
            }
        }, 20);
    }

    public function verify_post_types_registered()
    {
        $missing_types = [];

        if (!post_type_exists('sffc_live_chat')) {
            $missing_types[] = 'sffc_live_chat';
        }

        if (!post_type_exists('sffc_chat_message')) {
            $missing_types[] = 'sffc_chat_message';
        }

        if (!empty($missing_types)) {
            error_log('SFFC Live Expert: Missing post types after wp_loaded: ' . implode(', ', $missing_types));

            // Force re-registration
            $this->register_live_expert_post_types();

            // Check again and log results
            foreach ($missing_types as $type) {
                if (post_type_exists($type)) {
                    error_log("SFFC Live Expert: Successfully recovered post type: $type");
                } else {
                    error_log("SFFC Live Expert: CRITICAL - Still missing post type: $type");
                }
            }
        } else {
            error_log('SFFC Live Expert: All post types confirmed registered on wp_loaded');
        }
    }

    private function should_grant_live_expert_caps()
    {
        $action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '';
        if (strpos($action, 'sffc_live_expert_') === 0) {
            return true;
        }

        // Allow capability elevation when the expert console posts via AJAX
        if (!empty($_POST) && isset($_POST['action']) && 'sffc_live_expert_message' === sanitize_key(wp_unslash($_POST['action']))) {
            return true;
        }

        return false;
    }

    private function insert_live_expert_post(array $postarr)
    {
        // Ensure required fields are set
        if (empty($postarr['post_type'])) {
            return new WP_Error('missing_post_type', __('Post type is required.', 'senna'));
        }

        if (empty($postarr['post_status'])) {
            $postarr['post_status'] = 'publish';
        }

        if (empty($postarr['post_title'])) {
            $postarr['post_title'] = 'Live Chat Message';
        }

        if (empty($postarr['post_content'])) {
            $postarr['post_content'] = '';
        }

        // Set post author to 0 if not specified (for anonymous users)
        if (!isset($postarr['post_author'])) {
            $postarr['post_author'] = 0;
        }

        // Validate post type exists
        if (!post_type_exists($postarr['post_type'])) {
            error_log("SFFC Live Expert: Post type {$postarr['post_type']} does not exist");

            // Try to register the post type again as a fallback
            if ($postarr['post_type'] === 'sffc_chat_message') {
                error_log('SFFC Live Expert: Attempting emergency registration of sffc_chat_message');

                register_post_type('sffc_chat_message', [
                    'label' => __('Live Expert Messages', 'senna'),
                    'public' => false,
                    'supports' => ['editor', 'author', 'title'],
                    'capability_type' => 'post',
                    'map_meta_cap' => true,
                    'show_ui' => false,
                    'query_var' => false,
                    'rewrite' => false,
                    'exclude_from_search' => true,
                    'publicly_queryable' => false,
                ]);

                // Check again
                if (!post_type_exists($postarr['post_type'])) {
                    return new WP_Error('invalid_post_type', sprintf(__('Post type "%s" does not exist and could not be registered.', 'senna'), $postarr['post_type']));
                } else {
                    error_log('SFFC Live Expert: Emergency registration successful');
                }
            } else {
                return new WP_Error('invalid_post_type', sprintf(__('Post type "%s" does not exist.', 'senna'), $postarr['post_type']));
            }
        }

        $instance = $this;
        $filter = function ($allcaps, $caps, $args, $user) use ($instance) {
            if ($instance->should_grant_live_expert_caps()) {
                // Grant all necessary capabilities
                $allcaps['edit_posts'] = true;
                $allcaps['create_posts'] = true;
                $allcaps['publish_posts'] = true;
                $allcaps['edit_others_posts'] = true;
                $allcaps['delete_posts'] = true;
                $allcaps['delete_others_posts'] = true;
                $allcaps['delete_private_posts'] = true;
                $allcaps['delete_published_posts'] = true;
                $allcaps['edit_private_posts'] = true;
                $allcaps['edit_published_posts'] = true;
                $allcaps['read_private_posts'] = true;
                $allcaps['read'] = true;

                // Grant custom post type capabilities
                foreach ((array) $caps as $cap) {
                    $allcaps[$cap] = true;
                }
            }
            return $allcaps;
        };

        // Add error logging for debugging
        $original_error_handler = set_error_handler(function ($severity, $message, $file, $line) {
            error_log("SFFC Live Expert wp_insert_post error: $message in $file:$line");
            return false; // Let WordPress handle the error too
        });

        add_filter('user_has_cap', $filter, 999, 4);

        try {
            $post_id = wp_insert_post($postarr, true);

            if (is_wp_error($post_id)) {
                error_log('SFFC Live Expert: wp_insert_post failed - ' . $post_id->get_error_message());
                error_log('SFFC Live Expert: Post data - ' . print_r($postarr, true));
            } else if (!$post_id) {
                error_log('SFFC Live Expert: wp_insert_post returned 0');
                error_log('SFFC Live Expert: Post data - ' . print_r($postarr, true));
                $post_id = new WP_Error('insert_failed', __('Failed to insert post into database.', 'senna'));
            }
        } catch (Exception $e) {
            error_log('SFFC Live Expert: Exception during wp_insert_post - ' . $e->getMessage());
            $post_id = new WP_Error('insert_exception', sprintf(__('Database error: %s', 'senna'), $e->getMessage()));
        }

        remove_filter('user_has_cap', $filter, 999);

        // Restore original error handler
        if ($original_error_handler) {
            set_error_handler($original_error_handler);
        } else {
            restore_error_handler();
        }

        return $post_id;
    }

    private function get_live_expert_conversation($conversation_id)
    {
        $conversation_id = absint($conversation_id);
        if (!$conversation_id) {
            return null;
        }

        $conversation = get_post($conversation_id);
        if ($conversation && $conversation->post_type === 'sffc_live_chat') {
            return $conversation;
        }

        return null;
    }

    private function get_live_expert_conversation_by_session($session_id)
    {
        if (empty($session_id)) {
            return null;
        }

        $results = get_posts([
            'post_type' => 'sffc_live_chat',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'meta_key' => 'sffc_chat_session',
            'meta_value' => $session_id,
        ]);

        if (!empty($results)) {
            return $results[0];
        }

        return null;
    }

    private function generate_live_expert_guest_alias()
    {
        $counter = (int) get_option('sffc_live_guest_counter', 0);
        $counter++;
        update_option('sffc_live_guest_counter', $counter, false);
        return sprintf(__('Guest %d', 'senna'), $counter);
    }

    private function create_live_expert_conversation($session_id)
    {
        $user_id = get_current_user_id();
        $alias = '';
        $email = '';

        if ($user_id) {
            $user = get_userdata($user_id);
            if ($user) {
                $alias = $user->first_name ? $user->first_name : $user->display_name;
                $email = $user->user_email;
            }
        }

        if (empty($alias)) {
            $alias = $this->generate_live_expert_guest_alias();
        }

        $conversation_id = $this->insert_live_expert_post([
            'post_type' => 'sffc_live_chat',
            'post_status' => 'publish',
            'post_author' => $user_id,
            'post_title' => sprintf(__('Live Expert Conversation %s', 'senna'), current_time('Y-m-d H:i')),
        ]);

        if (is_wp_error($conversation_id) || !$conversation_id) {
            if (is_wp_error($conversation_id)) {
                return $conversation_id;
            }
            return new WP_Error('conversation_create_failed', __('Unable to create conversation.', 'senna'));
        }

        update_post_meta($conversation_id, 'sffc_chat_session', $session_id);
        update_post_meta($conversation_id, 'sffc_chat_user_id', $user_id);
        update_post_meta($conversation_id, 'sffc_chat_user_name', $alias);
        update_post_meta($conversation_id, 'sffc_chat_user_email', $email);
        update_post_meta($conversation_id, 'sffc_chat_last_activity', current_time('timestamp'));

        return [
            'conversation_id' => $conversation_id,
            'alias' => $alias,
            'email' => $email,
            'user_id' => $user_id,
        ];
    }

    private function ensure_live_expert_conversation($conversation_id, $session_id)
    {
        $conversation = null;

        if ($conversation_id) {
            $conversation = $this->get_live_expert_conversation($conversation_id);
        }

        if (!$conversation && $session_id) {
            $conversation = $this->get_live_expert_conversation_by_session($session_id);
        }

        if (!$conversation) {
            if (empty($session_id)) {
                return new WP_Error('missing_session', __('Missing session identifier for live expert chat.', 'senna'));
            }

            $created = $this->create_live_expert_conversation($session_id);
            if (is_wp_error($created)) {
                return $created;
            }

            return $created;
        }

        $conversation_id = $conversation->ID;

        // Get all meta in one query instead of 3 separate queries
        $conv_meta = get_post_meta($conversation_id);
        $alias = isset($conv_meta['sffc_chat_user_name'][0]) ? $conv_meta['sffc_chat_user_name'][0] : '';
        $email = isset($conv_meta['sffc_chat_user_email'][0]) ? $conv_meta['sffc_chat_user_email'][0] : '';
        $user_id = isset($conv_meta['sffc_chat_user_id'][0]) ? (int) $conv_meta['sffc_chat_user_id'][0] : 0;

        if (empty($alias)) {
            $alias = $this->generate_live_expert_guest_alias();
            update_post_meta($conversation_id, 'sffc_chat_user_name', $alias);
        }

        if (!empty($session_id)) {
            update_post_meta($conversation_id, 'sffc_chat_session', $session_id);
        }

        return [
            'conversation_id' => $conversation_id,
            'alias' => $alias,
            'email' => $email,
            'user_id' => $user_id,
        ];
    }

    private function format_live_expert_message_post($message_post)
    {
        $message_id = $message_post->ID;

        // Get all meta in one query instead of 4 separate queries
        $all_meta = get_post_meta($message_id);

        $sender = isset($all_meta['sffc_chat_sender'][0]) ? $all_meta['sffc_chat_sender'][0] : '';
        $sender_name = isset($all_meta['sffc_chat_sender_name'][0]) ? $all_meta['sffc_chat_sender_name'][0] : '';
        $sender_email = isset($all_meta['sffc_chat_sender_email'][0]) ? $all_meta['sffc_chat_sender_email'][0] : '';
        $timestamp = isset($all_meta['sffc_chat_timestamp'][0]) ? (int) $all_meta['sffc_chat_timestamp'][0] : 0;

        if (!$timestamp) {
            $timestamp = get_post_time('U', true, $message_post);
        }

        return [
            'id' => $message_id,
            'sender' => $sender ? sanitize_key($sender) : 'user',
            'sender_name' => $sender_name ?: '',
            'sender_email' => $sender_email ?: '',
            'content' => $message_post->post_content ?: '',
            'timestamp' => $timestamp ? (int) $timestamp : 0,
        ];
    }

    private function get_live_expert_messages($conversation_id, $since = 0)
    {
        $conversation_id = absint($conversation_id);
        if (!$conversation_id) {
            return [];
        }

        $query = get_posts([
            'post_type' => 'sffc_chat_message',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'post_parent' => $conversation_id,
            'orderby' => 'date',
            'order' => 'ASC',
        ]);

        if (empty($query)) {
            return [];
        }

        $messages = [];
        $since = absint($since);

        foreach ($query as $message_post) {
            $formatted = $this->format_live_expert_message_post($message_post);
            if ($since && $formatted['timestamp'] <= $since) {
                continue;
            }
            $messages[] = $formatted;
        }

        return $messages;
    }

    public function ajax_live_expert_message()
    {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';

        if (empty($nonce) || (!wp_verify_nonce($nonce, 'sffc_public_nonce') && !wp_verify_nonce($nonce, 'sffc_ajax_nonce'))) {
            wp_send_json_error(['message' => __('Invalid request. Please refresh and try again.', 'senna')], 403);
        }

        $session_id = isset($_POST['session_id']) ? sanitize_text_field(wp_unslash($_POST['session_id'])) : '';
        $conversation_id = isset($_POST['conversation_id']) ? absint($_POST['conversation_id']) : 0;
        $sender = isset($_POST['sender']) ? sanitize_key(wp_unslash($_POST['sender'])) : 'user';
        $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';

        if ($sender === 'expert' && !current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('You must be logged in as an expert to reply.', 'senna')], 403);
        }

        if (!$conversation_id && empty($session_id)) {
            wp_send_json_error(['message' => __('Missing session identifier.', 'senna')], 400);
        }

        if ($sender !== 'system' && empty($message)) {
            wp_send_json_error(['message' => __('Please enter a message before sending.', 'senna')], 400);
        }

        $conversation_data = $this->ensure_live_expert_conversation($conversation_id, $session_id);
        if (is_wp_error($conversation_data)) {
            wp_send_json_error(['message' => $conversation_data->get_error_message()], 400);
        }

        $conversation_id = (int) $conversation_data['conversation_id'];
        $session_id = get_post_meta($conversation_id, 'sffc_chat_session', true);
        $user_alias = $conversation_data['alias'];
        $user_email = $conversation_data['email'];

        if ($sender === 'user') {
            $sender_name = $user_alias;
            $sender_email = $user_email;
        } elseif ($sender === 'expert') {
            $current_user = wp_get_current_user();
            $sender_name = $current_user && $current_user->exists() ? ($current_user->display_name ?: __('Live Expert', 'senna')) : __('Live Expert', 'senna');
            $sender_email = $current_user && $current_user->exists() ? $current_user->user_email : '';
        } else {
            $sender_name = __('System', 'senna');
            $sender_email = '';
        }

        // Disable term counting and other unnecessary operations for speed
        wp_defer_term_counting(true);
        wp_defer_comment_counting(true);

        $message_id = $this->insert_live_expert_post([
            'post_type' => 'sffc_chat_message',
            'post_status' => 'publish',
            'post_parent' => $conversation_id,
            'post_author' => $sender === 'expert' ? get_current_user_id() : 0,
            'post_title' => sprintf('%s message', ucfirst($sender)),
            'post_content' => $message,
        ]);

        wp_defer_term_counting(false);
        wp_defer_comment_counting(false);

        if (is_wp_error($message_id) || !$message_id) {
            $error_message = is_wp_error($message_id) && $message_id->get_error_message() ? $message_id->get_error_message() : __('Unable to send message. Please try again.', 'senna');
            wp_send_json_error(['message' => $error_message], 500);
        }

        $timestamp = current_time('timestamp');

        // Batch update all post meta in one go to reduce database queries
        $meta_data = [
            'sffc_chat_sender' => $sender,
            'sffc_chat_sender_name' => $sender_name,
            'sffc_chat_sender_email' => $sender_email,
            'sffc_chat_timestamp' => $timestamp,
            'sffc_chat_session' => $session_id,
        ];

        if (!empty($context)) {
            $meta_data['sffc_chat_context'] = $context;
        }

        // Use a single database transaction-like operation
        foreach ($meta_data as $key => $value) {
            update_post_meta($message_id, $key, $value);
        }

        // Update conversation last activity
        update_post_meta($conversation_id, 'sffc_chat_last_activity', $timestamp);

        // Get post once for formatting - avoid redundant queries
        $post = get_post($message_id);
        $formatted = $this->format_live_expert_message_post($post);

        // Send response immediately without delays
        wp_send_json_success([
            'conversation_id' => $conversation_id,
            'messages' => [$formatted],
        ]);
    }

    public function ajax_live_expert_fetch()
    {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';

        if (empty($nonce) || (!wp_verify_nonce($nonce, 'sffc_public_nonce') && !wp_verify_nonce($nonce, 'sffc_ajax_nonce'))) {
            wp_send_json_error(['message' => __('Invalid request. Please refresh and try again.', 'senna')], 403);
        }

        $session_id = isset($_POST['session_id']) ? sanitize_text_field(wp_unslash($_POST['session_id'])) : '';
        $conversation_id = isset($_POST['conversation_id']) ? absint($_POST['conversation_id']) : 0;
        $since = isset($_POST['since']) ? absint($_POST['since']) : 0;

        if (!$conversation_id && $session_id) {
            $conversation = $this->get_live_expert_conversation_by_session($session_id);
            if ($conversation) {
                $conversation_id = $conversation->ID;
            }
        }

        if (!$conversation_id) {
            wp_send_json_success([
                'conversation_id' => 0,
                'messages' => [],
            ]);
        }

        $messages = $this->get_live_expert_messages($conversation_id, $since);

        wp_send_json_success([
            'conversation_id' => $conversation_id,
            'messages' => $messages,
        ]);
    }

    public function render_live_expert_console($atts = [])
    {
        $atts = shortcode_atts([
            'limit' => 10,
        ], $atts, 'senna_reply');

        if (!current_user_can('edit_posts')) {
            return '<p>' . esc_html__('Expert access required.', 'senna') . '</p>';
        }

        $limit = max(1, (int) $atts['limit']);
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('sffc_public_nonce');

        // Load conversations from FAST database tables
        $messaging = SFFC_Live_Expert_Messaging::get_instance();
        $conversations = $messaging->get_recent_conversations($limit);

        ob_start();
    ?>
        <div class="senna-live-expert-console" data-ajax-url="<?php echo esc_attr($ajax_url); ?>" data-nonce="<?php echo esc_attr($nonce); ?>">
            <?php if (empty($conversations)) : ?>
                <p><?php esc_html_e('No live expert conversations yet.', 'senna'); ?></p>
            <?php else : ?>
                <?php foreach ($conversations as $conversation) :
                    $conversation_id = $conversation['id'];
                    $session_id = $conversation['session_id'];
                    $alias = $conversation['user_name'] ?: __('Guest', 'senna');
                    $email = $conversation['user_email'];
                    $messages = $messaging->get_all_messages($conversation_id);
                    $last_timestamp = 0;
                    foreach ($messages as $m) {
                        if (!empty($m['timestamp'])) {
                            $last_timestamp = max($last_timestamp, (int) $m['timestamp']);
                        }
                    }
                ?>
                    <section class="live-expert-thread" data-conversation-id="<?php echo esc_attr($conversation_id); ?>" data-session-id="<?php echo esc_attr($session_id); ?>" data-last-timestamp="<?php echo esc_attr($last_timestamp); ?>">
                        <header class="live-expert-thread__header">
                            <div class="live-expert-thread__identity">
                                <strong><?php echo esc_html($alias); ?></strong>
                                <?php if ($email) : ?>
                                    <span class="live-expert-thread__contact"><?php echo esc_html($email); ?></span>
                                <?php endif; ?>
                            </div>
                        </header>
                        <div class="live-expert-thread__history">
                            <?php foreach ($messages as $message) :
                                $time_attr = $message['timestamp'] ? esc_attr(date('c', $message['timestamp'])) : '';
                                $time_label = $message['timestamp'] ? esc_html(date_i18n(get_option('time_format'), $message['timestamp'])) : '';
                            ?>
                                <article class="live-expert-thread__message live-expert-thread__message--<?php echo esc_attr($message['sender']); ?>">
                                    <div class="live-expert-thread__meta">
                                        <span><?php echo esc_html($message['sender_name'] ?: __('Guest', 'senna')); ?></span>
                                        <?php if ($time_label) : ?>
                                            <time datetime="<?php echo $time_attr; ?>"><?php echo $time_label; ?></time>
                                        <?php endif; ?>
                                    </div>
                                    <div class="live-expert-thread__body"><?php echo wpautop(esc_html($message['content'])); ?></div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                        <form class="live-expert-reply-form" data-conversation-id="<?php echo esc_attr($conversation_id); ?>" data-session-id="<?php echo esc_attr($session_id); ?>">
                            <div class="live-expert-form-container">
                                <label class="screen-reader-text" for="live-expert-reply-<?php echo esc_attr($conversation_id); ?>"><?php esc_html_e('Your reply', 'senna'); ?></label>
                                <div class="live-expert-input-wrapper">
                                    <textarea
                                        id="live-expert-reply-<?php echo esc_attr($conversation_id); ?>"
                                        rows="3"
                                        placeholder="<?php esc_attr_e('Type your reply to the participant…', 'senna'); ?>"
                                        data-auto-resize="true"></textarea>
                                    <div class="live-expert-form-actions">
                                        <button type="submit" class="live-expert-send-btn" disabled>
                                            <span class="send-text"><?php esc_html_e('Send Reply', 'senna'); ?></span>
                                            <span class="sending-text" style="display: none;"><?php esc_html_e('Sending...', 'senna'); ?></span>
                                        </button>
                                    </div>
                                </div>
                                <div class="live-expert-status" style="display: none;">
                                    <span class="typing-indicator"><?php esc_html_e('Typing...', 'senna'); ?></span>
                                </div>
                            </div>
                        </form>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <style>
            .senna-live-expert-console {
                display: grid;
                gap: 24px;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            }

            .live-expert-thread {
                border: 1px solid #e2e8f0;
                border-radius: 16px;
                padding: 24px;
                background: #fff;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            }

            .live-expert-thread__history {
                display: flex;
                flex-direction: column;
                gap: 16px;
                margin: 20px 0;
                max-height: 400px;
                overflow-y: auto;
                padding-right: 8px;
                scrollbar-width: thin;
                scrollbar-color: #cbd5e0 transparent;
            }

            .live-expert-thread__history::-webkit-scrollbar {
                width: 6px;
            }

            .live-expert-thread__history::-webkit-scrollbar-track {
                background: transparent;
            }

            .live-expert-thread__history::-webkit-scrollbar-thumb {
                background: #cbd5e0;
                border-radius: 3px;
            }

            .live-expert-thread__message {
                padding: 14px 16px;
                border-radius: 12px;
                background: #f8fafc;
                max-width: 80%;
                align-self: flex-start;
                position: relative;
            }

            .live-expert-thread__message--expert {
                background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
                align-self: flex-end;
                border: 1px solid #bbf7d0;
            }

            .live-expert-thread__meta {
                font-size: 11px;
                color: #64748b;
                margin-bottom: 8px;
                display: flex;
                gap: 8px;
                align-items: center;
                font-weight: 500;
            }

            .live-expert-thread__body {
                font-size: 14px;
                color: #1e293b;
                line-height: 1.5;
                margin: 0;
            }

            /* Enhanced Form Styles */
            .live-expert-reply-form {
                margin-top: 20px;
                border-top: 1px solid #e2e8f0;
                padding-top: 20px;
            }

            .live-expert-form-container {
                position: relative;
            }

            .live-expert-input-wrapper {
                display: flex;
                gap: 12px;
                align-items: flex-end;
                background: #f8fafc;
                border: 2px solid #e2e8f0;
                border-radius: 16px;
                padding: 8px;
                transition: border-color 0.2s ease;
            }

            .live-expert-input-wrapper:focus-within {
                border-color: #1a472a;
                box-shadow: 0 0 0 3px rgba(26, 71, 42, 0.1);
            }

            .live-expert-reply-form textarea {
                flex: 1;
                border: none;
                background: transparent;
                padding: 8px 12px;
                font-size: 14px;
                line-height: 1.4;
                resize: none;
                min-height: 20px;
                max-height: 120px;
                outline: none;
                font-family: inherit;
            }

            .live-expert-reply-form textarea::placeholder {
                color: #94a3b8;
            }

            .live-expert-form-actions {
                display: flex;
                align-items: center;
            }

            .live-expert-send-btn {
                background: #1a472a;
                color: #fff;
                border: none;
                border-radius: 12px;
                padding: 10px 16px;
                font-weight: 600;
                cursor: pointer;
                font-size: 13px;
                transition: all 0.2s ease;
                min-width: 80px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .live-expert-send-btn:hover:not(:disabled) {
                background: #15543d;
                transform: translateY(-1px);
            }

            .live-expert-send-btn:disabled {
                background: #94a3b8;
                cursor: not-allowed;
                transform: none;
            }

            .live-expert-status {
                position: absolute;
                bottom: -24px;
                left: 12px;
                font-size: 12px;
                color: #64748b;
                font-style: italic;
            }

            .typing-indicator {
                position: relative;
            }

            .typing-indicator::after {
                content: '...';
                animation: typing-dots 1.5s infinite;
            }

            @keyframes typing-dots {

                0%,
                20% {
                    opacity: 0;
                }

                50% {
                    opacity: 1;
                }

                80%,
                100% {
                    opacity: 0;
                }
            }
        </style>
        <script>
            (function() {
                const root = document.querySelector('.senna-live-expert-console');
                if (!root) {
                    return;
                }
                const ajaxUrl = root.dataset.ajaxUrl;
                const nonce = root.dataset.nonce;

                // Auto-resize textarea functionality
                function setupAutoResize(textarea) {
                    textarea.addEventListener('input', function() {
                        this.style.height = 'auto';
                        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
                    });
                }

                // Update send button state
                function updateSendButton(form) {
                    const textarea = form.querySelector('textarea');
                    const button = form.querySelector('.live-expert-send-btn');
                    if (textarea && button) {
                        const hasContent = textarea.value.trim().length > 0;
                        button.disabled = !hasContent;
                    }
                }

                function appendMessage(section, message) {
                    const history = section.querySelector('.live-expert-thread__history');
                    if (!history) {
                        return;
                    }

                    // Check if message already exists to prevent duplicates
                    const existingMessages = history.querySelectorAll('.live-expert-thread__message');
                    for (let msg of existingMessages) {
                        const existingContent = msg.querySelector('.live-expert-thread__body')?.textContent;
                        if (existingContent === message.content &&
                            msg.classList.contains('live-expert-thread__message--' + (message.sender || 'user'))) {
                            return; // Don't add duplicate
                        }
                    }

                    const article = document.createElement('article');
                    article.className = 'live-expert-thread__message live-expert-thread__message--' + (message.sender || 'user');

                    const meta = document.createElement('div');
                    meta.className = 'live-expert-thread__meta';
                    const name = document.createElement('span');
                    name.textContent = message.sender_name || (message.sender === 'expert' ? 'Live Expert' : 'User');
                    meta.appendChild(name);

                    if (message.timestamp) {
                        const time = document.createElement('time');
                        const date = new Date(message.timestamp * 1000);
                        time.dateTime = date.toISOString();
                        time.textContent = date.toLocaleTimeString([], {
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                        meta.appendChild(time);
                    }

                    const body = document.createElement('div');
                    body.className = 'live-expert-thread__body';
                    body.innerHTML = (message.content || '').replace(/\n/g, '<br>');

                    article.appendChild(meta);
                    article.appendChild(body);

                    // Add with animation
                    article.style.opacity = '0';
                    article.style.transform = 'translateY(10px)';
                    history.appendChild(article);

                    // Animate in
                    requestAnimationFrame(() => {
                        article.style.transition = 'all 0.3s ease';
                        article.style.opacity = '1';
                        article.style.transform = 'translateY(0)';
                    });

                    // Smooth scroll to bottom
                    setTimeout(() => {
                        history.scrollTo({
                            top: history.scrollHeight,
                            behavior: 'smooth'
                        });
                    }, 50);

                    if (message.timestamp) {
                        section.dataset.lastTimestamp = message.timestamp;
                    }
                }

                function refreshConversation(section) {
                    const conversationId = section.dataset.conversationId;
                    const sessionId = section.dataset.sessionId;
                    const lastTs = section.dataset.lastTimestamp || 0;
                    const params = new URLSearchParams();
                    params.append('action', 'sffc_live_expert_fetch_fast');
                    params.append('nonce', nonce);
                    params.append('conversation_id', conversationId || '');
                    params.append('session_id', sessionId || '');
                    params.append('since', lastTs);

                    fetch(ajaxUrl, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                            },
                            body: params.toString()
                        })
                        .then((response) => response.json())
                        .then((result) => {
                            if (!result || result.success !== true || !result.data) {
                                return;
                            }
                            const messages = result.data.messages || [];
                            messages.forEach((message) => appendMessage(section, message));
                        })
                        .catch(() => {});
                }

                // Setup each form
                root.querySelectorAll('.live-expert-reply-form').forEach((form) => {
                    const textarea = form.querySelector('textarea');
                    const button = form.querySelector('.live-expert-send-btn');

                    if (textarea) {
                        // Setup auto-resize
                        setupAutoResize(textarea);

                        // Update button state on input
                        textarea.addEventListener('input', () => {
                            updateSendButton(form);
                        });

                        // Submit on Enter (but not Shift+Enter)
                        textarea.addEventListener('keydown', (e) => {
                            if (e.key === 'Enter' && !e.shiftKey) {
                                e.preventDefault();
                                if (button && !button.disabled) {
                                    form.dispatchEvent(new Event('submit'));
                                }
                            }
                        });
                    }

                    // Form submission
                    form.addEventListener('submit', function(event) {
                        event.preventDefault();
                        const message = textarea.value.trim();
                        if (!message || !button) {
                            return;
                        }

                        const conversationId = this.dataset.conversationId || '';
                        const sessionId = this.dataset.sessionId || '';

                        // Update UI immediately
                        const sendText = button.querySelector('.send-text');
                        const sendingText = button.querySelector('.sending-text');
                        if (sendText && sendingText) {
                            sendText.style.display = 'none';
                            sendingText.style.display = 'block';
                        }
                        button.disabled = true;
                        textarea.disabled = true;

                        const params = new URLSearchParams();
                        params.append('action', 'sffc_live_expert_send_fast');
                        params.append('nonce', nonce);
                        params.append('conversation_id', conversationId);
                        params.append('session_id', sessionId);
                        params.append('sender', 'expert');
                        params.append('message', message);

                        fetch(ajaxUrl, {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                                },
                                body: params.toString()
                            })
                            .then((response) => response.json())
                            .then((result) => {
                                if (result && result.success && result.data) {
                                    const data = result.data;
                                    if (data.conversation_id) {
                                        form.closest('.live-expert-thread').dataset.conversationId = data.conversation_id;
                                    }
                                    // Append the single message returned
                                    if (data.message) {
                                        appendMessage(form.closest('.live-expert-thread'), data.message);
                                    }
                                    textarea.value = '';
                                    textarea.style.height = 'auto';
                                } else {
                                    console.error('Failed to send message:', result);
                                }
                            })
                            .catch((error) => {
                                console.error('Error sending message:', error);
                            })
                            .finally(() => {
                                // Reset UI
                                if (sendText && sendingText) {
                                    sendText.style.display = 'block';
                                    sendingText.style.display = 'none';
                                }
                                textarea.disabled = false;
                                updateSendButton(form);
                                textarea.focus();
                            });
                    });

                    // Initial button state
                    updateSendButton(form);
                });

                // Polling for new messages
                setInterval(() => {
                    root.querySelectorAll('.live-expert-thread').forEach((section) => {
                        refreshConversation(section);
                    });
                }, 3000); // Poll every 3 seconds
            })();
        </script>
<?php
        return ob_get_clean();
    }

    public function ajax_get_opportunities()
    {
        // Allow public access - nonce is optional for job listings
        check_ajax_referer('sffc_public_nonce', 'nonce', false);

        // No login check - this is PUBLIC

        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 6;
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;

        // Get jobs from database
        $jobs = $this->get_job_posts($limit, $offset);

        wp_send_json_success([
            'opportunities' => $jobs,
            'total' => $this->get_total_jobs()
        ]);
    }

    /**
     * Parse natural language search query into database filters
     */
    private function parse_search_intent($query, $cv_data)
    {
        $parsed = [
            'keywords' => [],
            'location' => null,
            'seniority' => null,
            'sector' => null,
            'skills' => [],
            'company_type' => null
        ];

        $query_lower = strtolower($query);

        // Extract location
        $locations = [
            // 🌍 Global financial hubs
            'london',
            'ldn',
            'new york',
            'nyc',
            'paris',
            'singapore',
            'hong kong',
            'hk',
            'dubai',
            'frankfurt',
            'zurich',
            'geneva',
            'luxembourg',
            'amsterdam',

            // 🇬🇧 UK
            'manchester',
            'birmingham',
            'edinburgh',
            'glasgow',
            'bristol',
            'leeds',

            // 🇫🇷 France
            'lyon',
            'marseille',
            'toulouse',

            // 🇩🇪 Germany
            'berlin',
            'munich',
            'muenchen',
            'münchen',
            'hamburg',
            'düsseldorf',
            'duesseldorf',
            'stuttgart',

            // 🇨🇭 Switzerland
            'basel',
            'lausanne',
            'zug',

            // 🇮🇹 Italy
            'milan',
            'milano',
            'rome',
            'roma',
            'turin',
            'torino',
            'florence',
            'firenze',
            'naples',
            'napoli',

            // 🇪🇸 Spain
            'madrid',
            'barcelona',
            'valencia',
            'seville',
            'sevilla',
            'bilbao',

            // 🇳🇱 Netherlands & Nordics
            'the hague',
            'rotterdam',
            'copenhagen',
            'stockholm',
            'oslo',
            'helsinki',
            'gothenburg',

            // 🇧🇪 Belgium / 🇱🇺 Luxembourg
            'brussels',
            'antwerp',
            'luxembourg',

            // 🇵🇱🇨🇿 Eastern Europe
            'warsaw',
            'prague',
            'budapest',
            'bucharest',
            'bratislava',

            // 🇺🇸 USA
            'boston',
            'san francisco',
            'sf',
            'los angeles',
            'la',
            'chicago',
            'houston',
            'dallas',
            'miami',
            'washington',
            'washington dc',
            'dc',
            'seattle',
            'atlanta',
            'philadelphia',

            // 🇨🇦 Canada
            'toronto',
            'vancouver',
            'montreal',
            'calgary',
            'ottawa',

            // 🇧🇷🇲🇽🇦🇷 South & Latin America
            'sao paulo',
            'são paulo',
            'rio de janeiro',
            'buenos aires',
            'mexico city',
            'cdmx',
            'bogota',
            'lima',
            'santiago',
            'montevideo',

            // 🌍 private equity
            'abu dhabi',
            'doha',
            'riyadh',
            'jeddah',
            'amman',
            'kuwait city',
            'manama',

            // 🌍 Africa
            'johannesburg',
            'cape town',
            'nairobi',
            'lagos',
            'cairo',
            'casablanca',

            // 🌏 Asia-Pacific
            'tokyo',
            'osaka',
            'seoul',
            'busan',
            'shanghai',
            'beijing',
            'shenzhen',
            'mumbai',
            'delhi',
            'bangalore',
            'bengaluru',
            'chennai',
            'jakarta',
            'bangkok',
            'sydney',
            'melbourne',
            'auckland',
            'manila',
            'ho chi minh',
            'hanoi',
            'kuala lumpur'
        ];

        foreach ($locations as $loc) {
            if (strpos($query_lower, $loc) !== false) {
                $parsed['location'] = $loc;
                break;
            }
        }

        // Extract sector/industry
        // 🧬 Life Sciences / Healthcare
        if (
            strpos($query_lower, 'life science') !== false ||
            strpos($query_lower, 'biotech') !== false ||
            strpos($query_lower, 'pharma') !== false ||
            strpos($query_lower, 'pharmaceutical') !== false ||
            strpos($query_lower, 'medical') !== false ||
            strpos($query_lower, 'medtech') !== false ||
            strpos($query_lower, 'healthcare') !== false ||
            strpos($query_lower, 'health care') !== false ||
            strpos($query_lower, 'health-tech') !== false ||
            strpos($query_lower, 'health tech') !== false
        ) {
            $parsed['sector'] = 'life_sciences';
        } elseif (
            strpos($query_lower, 'santé') !== false ||
            strpos($query_lower, 'salud') !== false ||
            strpos($query_lower, 'saúde') !== false ||
            strpos($query_lower, 'gesundheit') !== false ||
            strpos($query_lower, 'scienze della vita') !== false ||
            strpos($query_lower, 'ciencias de la vida') !== false
        ) {
            $parsed['sector'] = 'life_sciences';
        }

        // 💸 Venture Capital
        elseif (
            strpos($query_lower, 'vc') !== false ||
            strpos($query_lower, 'venture capital') !== false ||
            strpos($query_lower, 'venture') !== false ||
            strpos($query_lower, 'early stage') !== false ||
            strpos($query_lower, 'seed') !== false ||
            strpos($query_lower, 'startups') !== false ||
            strpos($query_lower, 'startup investing') !== false ||
            strpos($query_lower, 'venture firm') !== false ||
            strpos($query_lower, 'venture fund') !== false
        ) {
            $parsed['company_type'] = 'venture_capital';
        } elseif (
            strpos($query_lower, 'capital riesgo') !== false ||
            strpos($query_lower, 'capital de riesgo') !== false ||
            strpos($query_lower, 'capital-risque') !== false ||
            strpos($query_lower, 'venture capitaliste') !== false ||
            strpos($query_lower, 'venture capitalismo') !== false
        ) {
            $parsed['company_type'] = 'venture_capital';
        }

        // 🏦 Private Equity / Growth Equity
        elseif (
            strpos($query_lower, 'private equity') !== false ||
            strpos($query_lower, 'pe ') !== false ||
            strpos($query_lower, 'pe fund') !== false ||
            strpos($query_lower, 'buyout') !== false ||
            strpos($query_lower, 'buy-out') !== false ||
            strpos($query_lower, 'leveraged buyout') !== false ||
            strpos($query_lower, 'pe-backed') !== false
        ) {
            $parsed['company_type'] = 'private_equity';
        } elseif (
            strpos($query_lower, 'capital investissement') !== false ||
            strpos($query_lower, 'capital privado') !== false ||
            strpos($query_lower, 'equity privato') !== false ||
            strpos($query_lower, 'private equity firm') !== false ||
            strpos($query_lower, 'eigenkapitalfonds') !== false
        ) {
            $parsed['company_type'] = 'private_equity';
        } elseif (
            strpos($query_lower, 'growth equity') !== false ||
            strpos($query_lower, 'growth investing') !== false ||
            strpos($query_lower, 'late stage') !== false ||
            strpos($query_lower, 'expansion capital') !== false
        ) {
            $parsed['company_type'] = 'growth_equity';
        } elseif (
            strpos($query_lower, 'capital crecimiento') !== false ||
            strpos($query_lower, 'capital croissance') !== false ||
            strpos($query_lower, 'capitale di crescita') !== false
        ) {
            $parsed['company_type'] = 'growth_equity';
        }

        // 🧠 Hedge Funds, Credit, Funds
        elseif (
            strpos($query_lower, 'hedge fund') !== false ||
            strpos($query_lower, 'hedgefund') !== false ||
            strpos($query_lower, 'hf ') !== false ||
            strpos($query_lower, 'hedgefonds') !== false
        ) {
            $parsed['company_type'] = 'hedge_fund';
        } elseif (
            strpos($query_lower, 'credit fund') !== false ||
            strpos($query_lower, 'private credit') !== false ||
            strpos($query_lower, 'mezzanine') !== false ||
            strpos($query_lower, 'distressed') !== false ||
            strpos($query_lower, 'debt fund') !== false ||
            strpos($query_lower, 'structured credit') !== false
        ) {
            $parsed['company_type'] = 'credit_fund';
        } elseif (
            strpos($query_lower, 'secondary fund') !== false ||
            strpos($query_lower, 'secondaries') !== false ||
            strpos($query_lower, 'fund of funds') !== false ||
            strpos($query_lower, 'fund-of-funds') !== false ||
            strpos($query_lower, 'fof') !== false
        ) {
            $parsed['company_type'] = 'fund_of_funds';
        } elseif (
            strpos($query_lower, 'placement agent') !== false ||
            strpos($query_lower, 'fund placement') !== false ||
            strpos($query_lower, 'placement advisory') !== false
        ) {
            $parsed['company_type'] = 'placement_agent';
        }

        // 🏛 Institutional Investors
        elseif (
            strpos($query_lower, 'family office') !== false ||
            strpos($query_lower, 'single family') !== false ||
            strpos($query_lower, 'multi family') !== false ||
            strpos($query_lower, 'oficina de familia') !== false ||
            strpos($query_lower, 'office familial') !== false
        ) {
            $parsed['company_type'] = 'family_office';
        } elseif (
            strpos($query_lower, 'sovereign wealth') !== false ||
            strpos($query_lower, 'swf') !== false ||
            strpos($query_lower, 'fonds souverain') !== false
        ) {
            $parsed['company_type'] = 'sovereign_wealth_fund';
        } elseif (
            strpos($query_lower, 'pension fund') !== false ||
            strpos($query_lower, 'retirement fund') !== false ||
            strpos($query_lower, 'provident fund') !== false ||
            strpos($query_lower, 'fonds de pension') !== false ||
            strpos($query_lower, 'fondo de pensiones') !== false
        ) {
            $parsed['company_type'] = 'pension_fund';
        } elseif (
            strpos($query_lower, 'wealth management') !== false ||
            strpos($query_lower, 'private bank') !== false ||
            strpos($query_lower, 'private wealth') !== false ||
            strpos($query_lower, 'gestión de patrimonio') !== false ||
            strpos($query_lower, 'gestion de patrimoine') !== false
        ) {
            $parsed['company_type'] = 'wealth_management';
        }

        // 🏢 Asset Management
        elseif (
            strpos($query_lower, 'asset management') !== false ||
            strpos($query_lower, 'asset manager') !== false ||
            strpos($query_lower, 'investment management') !== false ||
            strpos($query_lower, 'fund manager') !== false ||
            strpos($query_lower, 'fund management') !== false ||
            strpos($query_lower, 'money manager') !== false
        ) {
            $parsed['company_type'] = 'asset_management';
        } elseif (
            strpos($query_lower, 'gestion d\'actifs') !== false ||
            strpos($query_lower, 'gestora de fondos') !== false ||
            strpos($query_lower, 'gestão de ativos') !== false ||
            strpos($query_lower, 'vermögensverwaltung') !== false
        ) {
            $parsed['company_type'] = 'asset_management';
        }

        // 🏦 Banking
        elseif (
            strpos($query_lower, 'investment bank') !== false ||
            strpos($query_lower, 'bulge bracket') !== false ||
            strpos($query_lower, 'm&a advisory') !== false ||
            strpos($query_lower, 'corporate finance') !== false ||
            strpos($query_lower, 'advisory bank') !== false
        ) {
            $parsed['company_type'] = 'investment_banking';
        } elseif (
            strpos($query_lower, 'boutique bank') !== false ||
            strpos($query_lower, 'boutique advisory') !== false ||
            strpos($query_lower, 'independent advisory') !== false
        ) {
            $parsed['company_type'] = 'boutique_investment_bank';
        } elseif (
            strpos($query_lower, 'commercial bank') !== false ||
            strpos($query_lower, 'retail bank') !== false ||
            strpos($query_lower, 'high street bank') !== false ||
            strpos($query_lower, 'banca') !== false ||
            strpos($query_lower, 'banque') !== false ||
            strpos($query_lower, 'banco') !== false
        ) {
            $parsed['sector'] = 'banking';
        }


        // Extract seniority - more comprehensive
        // Check seniority in order of specificity (most specific first)
        if (strpos($query_lower, 'managing director') !== false || strpos($query_lower, ' md') !== false) {
            $parsed['seniority'] = 'managing_director';
            $parsed['keywords'][] = 'managing director';
        } elseif (strpos($query_lower, 'partner') !== false || strpos($query_lower, 'principal') !== false) {
            $parsed['seniority'] = 'partner';
            $parsed['keywords'][] = 'partner';
        } elseif (strpos($query_lower, 'head of') !== false || strpos($query_lower, 'head,') !== false) {
            $parsed['seniority'] = 'head';
            $parsed['keywords'][] = 'head';
        } elseif (strpos($query_lower, 'executive director') !== false) {
            $parsed['seniority'] = 'executive_director';
            $parsed['keywords'][] = 'executive director';
        } elseif (strpos($query_lower, 'director') !== false) {
            $parsed['seniority'] = 'director';
            $parsed['keywords'][] = 'director';
        } elseif (strpos($query_lower, 'vp') !== false || strpos($query_lower, 'vice president') !== false) {
            $parsed['seniority'] = 'vp';
            $parsed['keywords'][] = 'vice president';
        } elseif (strpos($query_lower, 'avp') !== false || strpos($query_lower, 'assistant vice president') !== false) {
            $parsed['seniority'] = 'avp';
            $parsed['keywords'][] = 'assistant vice president';
        } elseif (strpos($query_lower, 'senior manager') !== false) {
            $parsed['seniority'] = 'senior_manager';
            $parsed['keywords'][] = 'manager';
        } elseif (strpos($query_lower, 'manager') !== false) {
            $parsed['seniority'] = 'manager';
            $parsed['keywords'][] = 'manager';
        } elseif (strpos($query_lower, 'senior consultant') !== false) {
            $parsed['seniority'] = 'senior_consultant';
            $parsed['keywords'][] = 'consultant';
        } elseif (strpos($query_lower, 'consultant') !== false) {
            $parsed['seniority'] = 'consultant';
            $parsed['keywords'][] = 'consultant';
        } elseif (strpos($query_lower, 'lead') !== false || strpos($query_lower, 'team lead') !== false) {
            $parsed['seniority'] = 'lead';
            $parsed['keywords'][] = 'lead';
        } elseif (strpos($query_lower, 'senior associate') !== false) {
            $parsed['seniority'] = 'senior_associate';
            $parsed['keywords'][] = 'associate';
        } elseif (strpos($query_lower, 'associate') !== false) {
            $parsed['seniority'] = 'associate';
            $parsed['keywords'][] = 'associate';
        } elseif (strpos($query_lower, 'senior analyst') !== false) {
            $parsed['seniority'] = 'senior_analyst';
            $parsed['keywords'][] = 'analyst';
        } elseif (strpos($query_lower, 'research analyst') !== false || strpos($query_lower, 'analyst') !== false) {
            $parsed['seniority'] = 'analyst';
            $parsed['keywords'][] = 'analyst';
        } elseif (strpos($query_lower, 'junior') !== false) {
            $parsed['seniority'] = 'junior';
        } elseif (strpos($query_lower, 'graduate') !== false || strpos($query_lower, 'trainee') !== false) {
            $parsed['seniority'] = 'graduate';
            $parsed['keywords'][] = 'graduate';
        } elseif (strpos($query_lower, 'intern') !== false || strpos($query_lower, 'summer analyst') !== false || strpos($query_lower, 'placement') !== false || strpos($query_lower, 'apprentice') !== false) {
            $parsed['seniority'] = 'intern';
            $parsed['keywords'][] = 'intern';
        } elseif (strpos($query_lower, 'senior') !== false) {
            // Generic senior catch-all (if not already caught by specific titles)
            $parsed['seniority'] = 'senior';
        }

        // Extract key skills / roles / sectors
        if (strpos($query_lower, 'analyst') !== false) {
            $parsed['keywords'][] = 'analyst';
        }
        if (strpos($query_lower, 'associate') !== false) {
            $parsed['keywords'][] = 'associate';
        }
        if (strpos($query_lower, 'consultant') !== false) {
            $parsed['keywords'][] = 'consultant';
        }
        if (strpos($query_lower, 'manager') !== false) {
            $parsed['keywords'][] = 'manager';
        }
        if (strpos($query_lower, 'director') !== false) {
            $parsed['keywords'][] = 'director';
        }
        if (strpos($query_lower, 'partner') !== false || strpos($query_lower, 'principal') !== false) {
            $parsed['keywords'][] = 'partner';
        }
        if (strpos($query_lower, 'executive') !== false) {
            $parsed['keywords'][] = 'executive';
        }

        // Core finance & risk functions
        if (strpos($query_lower, 'risk') !== false) {
            $parsed['keywords'][] = 'risk';
        }
        if (strpos($query_lower, 'compliance') !== false) {
            $parsed['keywords'][] = 'compliance';
        }
        if (strpos($query_lower, 'audit') !== false) {
            $parsed['keywords'][] = 'audit';
        }
        if (strpos($query_lower, 'control') !== false) {
            $parsed['keywords'][] = 'control';
        }
        if (strpos($query_lower, 'internal controls') !== false) {
            $parsed['keywords'][] = 'internal controls';
        }
        if (strpos($query_lower, 'finance') !== false || strpos($query_lower, 'financial') !== false) {
            $parsed['keywords'][] = 'finance';
        }
        if (strpos($query_lower, 'accounting') !== false || strpos($query_lower, 'accountant') !== false) {
            $parsed['keywords'][] = 'accounting';
        }
        if (strpos($query_lower, 'bookkeeping') !== false || strpos($query_lower, 'bookkeeper') !== false) {
            $parsed['keywords'][] = 'bookkeeping';
        }
        if (strpos($query_lower, 'treasury') !== false) {
            $parsed['keywords'][] = 'treasury';
        }
        if (strpos($query_lower, 'cash management') !== false) {
            $parsed['keywords'][] = 'cash management';
        }
        if (strpos($query_lower, 'tax') !== false) {
            $parsed['keywords'][] = 'tax';
        }
        if (strpos($query_lower, 'regulatory') !== false || strpos($query_lower, 'regulation') !== false) {
            $parsed['keywords'][] = 'regulatory';
        }
        if (strpos($query_lower, 'governance') !== false) {
            $parsed['keywords'][] = 'governance';
        }
        if (strpos($query_lower, 'reporting') !== false) {
            $parsed['keywords'][] = 'reporting';
        }
        if (strpos($query_lower, 'fp&a') !== false || strpos($query_lower, 'fp&a') !== false || strpos($query_lower, 'financial planning') !== false) {
            $parsed['keywords'][] = 'fp&a';
        }
        if (strpos($query_lower, 'budget') !== false || strpos($query_lower, 'forecast') !== false) {
            $parsed['keywords'][] = 'budgeting';
        }

        // Investment & deal functions
        if (strpos($query_lower, 'investment') !== false) {
            $parsed['keywords'][] = 'investment';
        }
        if (strpos($query_lower, 'private equity') !== false || strpos($query_lower, 'pe') !== false) {
            $parsed['keywords'][] = 'private equity';
        }
        if (strpos($query_lower, 'venture capital') !== false || strpos($query_lower, 'vc') !== false) {
            $parsed['keywords'][] = 'venture capital';
        }
        if (strpos($query_lower, 'm&a') !== false || strpos($query_lower, 'mergers') !== false || strpos($query_lower, 'acquisitions') !== false) {
            $parsed['keywords'][] = 'm&a';
        }
        if (strpos($query_lower, 'corporate development') !== false || strpos($query_lower, 'corp dev') !== false) {
            $parsed['keywords'][] = 'corporate development';
        }
        if (strpos($query_lower, 'leveraged finance') !== false || strpos($query_lower, 'levfin') !== false) {
            $parsed['keywords'][] = 'leveraged finance';
        }
        if (strpos($query_lower, 'investment banking') !== false || strpos($query_lower, 'ibd') !== false) {
            $parsed['keywords'][] = 'investment banking';
        }
        if (strpos($query_lower, 'capital markets') !== false) {
            $parsed['keywords'][] = 'capital markets';
        }
        if (strpos($query_lower, 'equity research') !== false) {
            $parsed['keywords'][] = 'equity research';
        }
        if (strpos($query_lower, 'portfolio') !== false) {
            $parsed['keywords'][] = 'portfolio';
        }
        if (strpos($query_lower, 'fund') !== false || strpos($query_lower, 'asset management') !== false) {
            $parsed['keywords'][] = 'fund management';
        }
        if (strpos($query_lower, 'trading') !== false || strpos($query_lower, 'trader') !== false) {
            $parsed['keywords'][] = 'trading';
        }
        if (strpos($query_lower, 'hedge fund') !== false) {
            $parsed['keywords'][] = 'hedge fund';
        }
        if (strpos($query_lower, 'structured finance') !== false || strpos($query_lower, 'securitisation') !== false) {
            $parsed['keywords'][] = 'structured finance';
        }
        if (strpos($query_lower, 'real estate finance') !== false) {
            $parsed['keywords'][] = 'real estate finance';
        }

        // Strategy & consulting
        if (strpos($query_lower, 'strategy') !== false || strpos($query_lower, 'strategic') !== false) {
            $parsed['keywords'][] = 'strategy';
        }
        if (strpos($query_lower, 'transformation') !== false) {
            $parsed['keywords'][] = 'transformation';
        }
        if (strpos($query_lower, 'turnaround') !== false) {
            $parsed['keywords'][] = 'turnaround';
        }
        if (strpos($query_lower, 'restructuring') !== false) {
            $parsed['keywords'][] = 'restructuring';
        }
        if (strpos($query_lower, 'advisory') !== false) {
            $parsed['keywords'][] = 'advisory';
        }
        if (strpos($query_lower, 'commercial') !== false) {
            $parsed['keywords'][] = 'commercial';
        }
        if (strpos($query_lower, 'operations') !== false || strpos($query_lower, 'operational') !== false) {
            $parsed['keywords'][] = 'operations';
        }
        if (strpos($query_lower, 'outsourcing') !== false) {
            $parsed['keywords'][] = 'outsourcing';
        }
        if (strpos($query_lower, 'shared services') !== false) {
            $parsed['keywords'][] = 'shared services';
        }

        // Technology & data
        if (strpos($query_lower, 'data') !== false) {
            $parsed['keywords'][] = 'data';
        }
        if (strpos($query_lower, 'digital') !== false) {
            $parsed['keywords'][] = 'digital';
        }
        if (strpos($query_lower, 'technology') !== false || strpos($query_lower, 'tech') !== false || strpos($query_lower, 'it ') !== false) {
            $parsed['keywords'][] = 'technology';
        }
        if (strpos($query_lower, 'fintech') !== false) {
            $parsed['keywords'][] = 'fintech';
        }
        if (strpos($query_lower, 'ai') !== false || strpos($query_lower, 'artificial intelligence') !== false) {
            $parsed['keywords'][] = 'ai';
        }
        if (strpos($query_lower, 'ml') !== false || strpos($query_lower, 'machine learning') !== false) {
            $parsed['keywords'][] = 'machine learning';
        }
        if (strpos($query_lower, 'automation') !== false) {
            $parsed['keywords'][] = 'automation';
        }
        if (strpos($query_lower, 'product') !== false) {
            $parsed['keywords'][] = 'product';
        }
        if (strpos($query_lower, 'cyber') !== false || strpos($query_lower, 'cybersecurity') !== false) {
            $parsed['keywords'][] = 'cybersecurity';
        }
        if (strpos($query_lower, 'engineering') !== false || strpos($query_lower, 'developer') !== false || strpos($query_lower, 'software') !== false) {
            $parsed['keywords'][] = 'engineering';
        }
        if (strpos($query_lower, 'cloud') !== false) {
            $parsed['keywords'][] = 'cloud';
        }

        // Commercial functions
        if (strpos($query_lower, 'sales') !== false) {
            $parsed['keywords'][] = 'sales';
        }
        if (strpos($query_lower, 'business development') !== false || strpos($query_lower, 'bd') !== false) {
            $parsed['keywords'][] = 'business development';
        }
        if (strpos($query_lower, 'marketing') !== false) {
            $parsed['keywords'][] = 'marketing';
        }
        if (strpos($query_lower, 'customer success') !== false) {
            $parsed['keywords'][] = 'customer success';
        }
        if (strpos($query_lower, 'account management') !== false) {
            $parsed['keywords'][] = 'account management';
        }

        // Corporate functions
        if (strpos($query_lower, 'legal') !== false || strpos($query_lower, 'law') !== false) {
            $parsed['keywords'][] = 'legal';
        }
        if (strpos($query_lower, 'hr') !== false || strpos($query_lower, 'human resources') !== false || strpos($query_lower, 'talent') !== false) {
            $parsed['keywords'][] = 'hr';
        }
        if (strpos($query_lower, 'recruitment') !== false || strpos($query_lower, 'recruiter') !== false) {
            $parsed['keywords'][] = 'recruitment';
        }
        if (strpos($query_lower, 'procurement') !== false || strpos($query_lower, 'purchasing') !== false) {
            $parsed['keywords'][] = 'procurement';
        }
        if (strpos($query_lower, 'admin') !== false || strpos($query_lower, 'administration') !== false) {
            $parsed['keywords'][] = 'administration';
        }
        if (strpos($query_lower, 'office manager') !== false) {
            $parsed['keywords'][] = 'office manager';
        }

        // Industry sectors
        if (strpos($query_lower, 'real estate') !== false || strpos($query_lower, 'property') !== false) {
            $parsed['keywords'][] = 'real estate';
        }
        if (strpos($query_lower, 'infrastructure') !== false) {
            $parsed['keywords'][] = 'infrastructure';
        }
        if (strpos($query_lower, 'energy') !== false || strpos($query_lower, 'renewables') !== false) {
            $parsed['keywords'][] = 'energy';
        }
        if (strpos($query_lower, 'tmt') !== false || strpos($query_lower, 'technology media telecom') !== false) {
            $parsed['keywords'][] = 'tmt';
        }
        if (strpos($query_lower, 'healthcare') !== false || strpos($query_lower, 'pharma') !== false || strpos($query_lower, 'biotech') !== false) {
            $parsed['keywords'][] = 'healthcare';
        }
        if (strpos($query_lower, 'consumer') !== false || strpos($query_lower, 'retail') !== false) {
            $parsed['keywords'][] = 'consumer';
        }
        if (strpos($query_lower, 'industrials') !== false || strpos($query_lower, 'manufacturing') !== false) {
            $parsed['keywords'][] = 'industrials';
        }
        if (strpos($query_lower, 'financial services') !== false || strpos($query_lower, 'banking') !== false || strpos($query_lower, 'insurance') !== false) {
            $parsed['keywords'][] = 'financial services';
        }
        if (strpos($query_lower, 'public sector') !== false || strpos($query_lower, 'government') !== false) {
            $parsed['keywords'][] = 'public sector';
        }
        if (strpos($query_lower, 'education') !== false) {
            $parsed['keywords'][] = 'education';
        }
        if (strpos($query_lower, 'transport') !== false || strpos($query_lower, 'logistics') !== false || strpos($query_lower, 'shipping') !== false) {
            $parsed['keywords'][] = 'transport';
        }
        if (strpos($query_lower, 'mining') !== false || strpos($query_lower, 'natural resources') !== false || strpos($query_lower, 'commodities') !== false) {
            $parsed['keywords'][] = 'natural resources';
        }
        // 🔸 Private Markets — Core Strategies
        if (strpos($query_lower, 'private equity') !== false || strpos($query_lower, 'pe') !== false) {
            $parsed['keywords'][] = 'private equity';
        }
        if (strpos($query_lower, 'venture capital') !== false || strpos($query_lower, 'vc') !== false) {
            $parsed['keywords'][] = 'venture capital';
        }
        if (strpos($query_lower, 'growth equity') !== false || strpos($query_lower, 'growth investing') !== false) {
            $parsed['keywords'][] = 'growth equity';
        }
        if (strpos($query_lower, 'buyout') !== false) {
            $parsed['keywords'][] = 'buyout';
        }
        if (strpos($query_lower, 'leveraged buyout') !== false || strpos($query_lower, 'lbo') !== false) {
            $parsed['keywords'][] = 'leveraged buyout';
        }
        if (strpos($query_lower, 'private credit') !== false || strpos($query_lower, 'direct lending') !== false || strpos($query_lower, 'private debt') !== false) {
            $parsed['keywords'][] = 'private credit';
        }
        if (strpos($query_lower, 'distressed') !== false) {
            $parsed['keywords'][] = 'distressed';
        }
        if (strpos($query_lower, 'special situations') !== false) {
            $parsed['keywords'][] = 'special situations';
        }
        if (strpos($query_lower, 'mezzanine') !== false) {
            $parsed['keywords'][] = 'mezzanine';
        }
        if (strpos($query_lower, 'secondaries') !== false || strpos($query_lower, 'secondary market') !== false) {
            $parsed['keywords'][] = 'secondaries';
        }
        if (strpos($query_lower, 'co-invest') !== false || strpos($query_lower, 'co invest') !== false || strpos($query_lower, 'co-investment') !== false) {
            $parsed['keywords'][] = 'co-investment';
        }
        if (strpos($query_lower, 'fund of funds') !== false || strpos($query_lower, 'fof') !== false) {
            $parsed['keywords'][] = 'fund of funds';
        }
        if (strpos($query_lower, 'real assets') !== false) {
            $parsed['keywords'][] = 'real assets';
        }
        if (strpos($query_lower, 'infrastructure') !== false) {
            $parsed['keywords'][] = 'infrastructure';
        }
        if (strpos($query_lower, 'real estate') !== false || strpos($query_lower, 'property') !== false) {
            $parsed['keywords'][] = 'real estate';
        }
        if (strpos($query_lower, 'natural resources') !== false || strpos($query_lower, 'commodities') !== false) {
            $parsed['keywords'][] = 'natural resources';
        }

        // 🔸 Private Markets — Fund Functions
        if (strpos($query_lower, 'deal origination') !== false || strpos($query_lower, 'origination') !== false) {
            $parsed['keywords'][] = 'origination';
        }
        if (strpos($query_lower, 'deal execution') !== false || strpos($query_lower, 'execution') !== false) {
            $parsed['keywords'][] = 'execution';
        }
        if (strpos($query_lower, 'due diligence') !== false || strpos($query_lower, 'diligence') !== false) {
            $parsed['keywords'][] = 'due diligence';
        }
        if (strpos($query_lower, 'valuation') !== false || strpos($query_lower, 'valuations') !== false) {
            $parsed['keywords'][] = 'valuation';
        }
        if (strpos($query_lower, 'financial modeling') !== false || strpos($query_lower, 'financial modelling') !== false || strpos($query_lower, 'modeling') !== false || strpos($query_lower, 'modelling') !== false) {
            $parsed['keywords'][] = 'financial modeling';
        }
        if (strpos($query_lower, 'investment committee') !== false || strpos($query_lower, 'ic') !== false) {
            $parsed['keywords'][] = 'investment committee';
        }
        if (strpos($query_lower, 'portfolio monitoring') !== false || strpos($query_lower, 'asset management') !== false || strpos($query_lower, 'post-acquisition') !== false) {
            $parsed['keywords'][] = 'portfolio monitoring';
        }
        if (strpos($query_lower, 'exit strategy') !== false || strpos($query_lower, 'exit planning') !== false || strpos($query_lower, 'divestment') !== false) {
            $parsed['keywords'][] = 'exit strategy';
        }
        if (strpos($query_lower, 'bolt-on') !== false || strpos($query_lower, 'add-on') !== false) {
            $parsed['keywords'][] = 'bolt-on acquisitions';
        }
        if (strpos($query_lower, 'platform investment') !== false || strpos($query_lower, 'platform deal') !== false) {
            $parsed['keywords'][] = 'platform investment';
        }

        // 🔸 Fundraising & IR
        if (strpos($query_lower, 'fundraising') !== false || strpos($query_lower, 'capital raising') !== false) {
            $parsed['keywords'][] = 'fundraising';
        }
        if (strpos($query_lower, 'investor relations') !== false || strpos($query_lower, 'ir') !== false) {
            $parsed['keywords'][] = 'investor relations';
        }
        if (strpos($query_lower, 'placement agent') !== false) {
            $parsed['keywords'][] = 'placement agent';
        }
        if (strpos($query_lower, 'fund structuring') !== false || strpos($query_lower, 'gp structuring') !== false) {
            $parsed['keywords'][] = 'fund structuring';
        }
        if (strpos($query_lower, 'gp stakes') !== false || strpos($query_lower, 'gp interests') !== false) {
            $parsed['keywords'][] = 'gp stakes';
        }
        if (strpos($query_lower, 'lp relations') !== false || strpos($query_lower, 'limited partner') !== false || strpos($query_lower, 'lp base') !== false) {
            $parsed['keywords'][] = 'lp relations';
        }
        if (strpos($query_lower, 'fund administration') !== false || strpos($query_lower, 'fund admin') !== false) {
            $parsed['keywords'][] = 'fund administration';
        }
        if (strpos($query_lower, 'fund accounting') !== false) {
            $parsed['keywords'][] = 'fund accounting';
        }
        if (strpos($query_lower, 'carried interest') !== false || strpos($query_lower, 'carry') !== false) {
            $parsed['keywords'][] = 'carried interest';
        }
        if (strpos($query_lower, 'waterfall') !== false || strpos($query_lower, 'distribution waterfall') !== false) {
            $parsed['keywords'][] = 'distribution waterfall';
        }
        if (strpos($query_lower, 'subscription line') !== false || strpos($query_lower, 'nav facility') !== false || strpos($query_lower, 'fund financing') !== false) {
            $parsed['keywords'][] = 'fund financing';
        }

        // 🔸 LP / Allocator Side
        if (strpos($query_lower, 'fund of funds') !== false || strpos($query_lower, 'fof') !== false) {
            $parsed['keywords'][] = 'fund of funds';
        }
        if (strpos($query_lower, 'sovereign wealth fund') !== false || strpos($query_lower, 'swf') !== false) {
            $parsed['keywords'][] = 'sovereign wealth fund';
        }
        if (strpos($query_lower, 'pension fund') !== false) {
            $parsed['keywords'][] = 'pension fund';
        }
        if (strpos($query_lower, 'endowment') !== false || strpos($query_lower, 'foundation') !== false) {
            $parsed['keywords'][] = 'endowment';
        }
        if (strpos($query_lower, 'family office') !== false) {
            $parsed['keywords'][] = 'family office';
        }
        if (strpos($query_lower, 'fund selection') !== false || strpos($query_lower, 'manager selection') !== false) {
            $parsed['keywords'][] = 'fund selection';
        }
        if (strpos($query_lower, 'secondaries buyer') !== false || strpos($query_lower, 'lp secondary') !== false) {
            $parsed['keywords'][] = 'secondary buyer';
        }
        if (strpos($query_lower, 'primary fundraising') !== false) {
            $parsed['keywords'][] = 'primary fundraising';
        }
        if (strpos($query_lower, 'co-invest program') !== false || strpos($query_lower, 'coinvest program') !== false) {
            $parsed['keywords'][] = 'co-invest program';
        }

        // 🟦 Asset Management — Core Functions
        if (strpos($query_lower, 'asset management') !== false || strpos($query_lower, 'am ') !== false) {
            $parsed['keywords'][] = 'asset management';
        }
        if (strpos($query_lower, 'fund management') !== false || strpos($query_lower, 'investment management') !== false) {
            $parsed['keywords'][] = 'fund management';
        }
        if (strpos($query_lower, 'portfolio manager') !== false || strpos($query_lower, 'portfolio management') !== false) {
            $parsed['keywords'][] = 'portfolio management';
        }
        if (strpos($query_lower, 'investment manager') !== false) {
            $parsed['keywords'][] = 'investment manager';
        }
        if (strpos($query_lower, 'fund manager') !== false) {
            $parsed['keywords'][] = 'fund manager';
        }
        if (strpos($query_lower, 'asset manager') !== false) {
            $parsed['keywords'][] = 'asset manager';
        }
        if (strpos($query_lower, 'mandate') !== false) {
            $parsed['keywords'][] = 'mandate';
        }
        if (strpos($query_lower, 'fiduciary management') !== false) {
            $parsed['keywords'][] = 'fiduciary management';
        }
        if (strpos($query_lower, 'outsourced cio') !== false || strpos($query_lower, 'ocio') !== false) {
            $parsed['keywords'][] = 'ocio';
        }
        if (strpos($query_lower, 'multi-manager') !== false || strpos($query_lower, 'multi asset') !== false) {
            $parsed['keywords'][] = 'multi-manager';
        }
        if (strpos($query_lower, 'active management') !== false) {
            $parsed['keywords'][] = 'active management';
        }
        if (strpos($query_lower, 'passive management') !== false || strpos($query_lower, 'index fund') !== false) {
            $parsed['keywords'][] = 'passive management';
        }
        if (strpos($query_lower, 'etf') !== false || strpos($query_lower, 'exchange traded fund') !== false) {
            $parsed['keywords'][] = 'etf';
        }
        if (strpos($query_lower, 'mutual fund') !== false || strpos($query_lower, 'ucits') !== false) {
            $parsed['keywords'][] = 'mutual fund';
        }
        if (strpos($query_lower, 'segregated account') !== false || strpos($query_lower, 'segregated mandate') !== false) {
            $parsed['keywords'][] = 'segregated mandate';
        }
        if (strpos($query_lower, 'fund range') !== false) {
            $parsed['keywords'][] = 'fund range';
        }
        if (strpos($query_lower, 'aum') !== false || strpos($query_lower, 'assets under management') !== false) {
            $parsed['keywords'][] = 'aum';
        }

        // 🟧 Asset Classes & Strategies
        if (strpos($query_lower, 'equities') !== false || strpos($query_lower, 'equity') !== false) {
            $parsed['keywords'][] = 'equities';
        }
        if (strpos($query_lower, 'fixed income') !== false || strpos($query_lower, 'bonds') !== false || strpos($query_lower, 'credit') !== false) {
            $parsed['keywords'][] = 'fixed income';
        }
        if (strpos($query_lower, 'multi-asset') !== false || strpos($query_lower, 'multi asset') !== false) {
            $parsed['keywords'][] = 'multi-asset';
        }
        if (strpos($query_lower, 'alternatives') !== false || strpos($query_lower, 'alts') !== false) {
            $parsed['keywords'][] = 'alternatives';
        }
        if (strpos($query_lower, 'real assets') !== false) {
            $parsed['keywords'][] = 'real assets';
        }
        if (strpos($query_lower, 'esg') !== false || strpos($query_lower, 'sustainability') !== false || strpos($query_lower, 'responsible investing') !== false) {
            $parsed['keywords'][] = 'esg';
        }
        if (strpos($query_lower, 'impact investing') !== false || strpos($query_lower, 'impact fund') !== false) {
            $parsed['keywords'][] = 'impact investing';
        }
        if (strpos($query_lower, 'smart beta') !== false) {
            $parsed['keywords'][] = 'smart beta';
        }
        if (strpos($query_lower, 'factor investing') !== false) {
            $parsed['keywords'][] = 'factor investing';
        }
        if (strpos($query_lower, 'quant') !== false || strpos($query_lower, 'quantitative') !== false) {
            $parsed['keywords'][] = 'quantitative';
        }
        if (strpos($query_lower, 'systematic') !== false) {
            $parsed['keywords'][] = 'systematic';
        }
        if (strpos($query_lower, 'absolute return') !== false || strpos($query_lower, 'total return') !== false) {
            $parsed['keywords'][] = 'absolute return';
        }
        if (strpos($query_lower, 'benchmark') !== false) {
            $parsed['keywords'][] = 'benchmark';
        }

        // 🟨 Distribution / Sales / Client
        if (strpos($query_lower, 'institutional sales') !== false || strpos($query_lower, 'institutional distribution') !== false) {
            $parsed['keywords'][] = 'institutional sales';
        }
        if (strpos($query_lower, 'wholesale distribution') !== false || strpos($query_lower, 'wholesale sales') !== false) {
            $parsed['keywords'][] = 'wholesale distribution';
        }
        if (strpos($query_lower, 'retail distribution') !== false || strpos($query_lower, 'ifa') !== false || strpos($query_lower, 'intermediary') !== false) {
            $parsed['keywords'][] = 'retail distribution';
        }
        if (strpos($query_lower, 'client relationship') !== false || strpos($query_lower, 'client servicing') !== false) {
            $parsed['keywords'][] = 'client relationship';
        }
        if (strpos($query_lower, 'business development') !== false || strpos($query_lower, 'distribution') !== false) {
            $parsed['keywords'][] = 'distribution';
        }
        if (strpos($query_lower, 'consultant relations') !== false) {
            $parsed['keywords'][] = 'consultant relations';
        }
        if (strpos($query_lower, 'product specialist') !== false) {
            $parsed['keywords'][] = 'product specialist';
        }
        if (strpos($query_lower, 'rfp') !== false || strpos($query_lower, 'request for proposal') !== false) {
            $parsed['keywords'][] = 'rfp';
        }
        if (strpos($query_lower, 'due diligence questionnaire') !== false || strpos($query_lower, 'ddq') !== false) {
            $parsed['keywords'][] = 'ddq';
        }
        if (strpos($query_lower, 'client reporting') !== false) {
            $parsed['keywords'][] = 'client reporting';
        }
        if (strpos($query_lower, 'marketing') !== false && strpos($query_lower, 'fund') !== false) {
            $parsed['keywords'][] = 'fund marketing';
        }

        // 🟩 Operations / Risk / Infrastructure
        if (strpos($query_lower, 'middle office') !== false) {
            $parsed['keywords'][] = 'middle office';
        }
        if (strpos($query_lower, 'back office') !== false) {
            $parsed['keywords'][] = 'back office';
        }
        if (strpos($query_lower, 'front office') !== false) {
            $parsed['keywords'][] = 'front office';
        }
        if (strpos($query_lower, 'operations') !== false) {
            $parsed['keywords'][] = 'operations';
        }
        if (strpos($query_lower, 'trade support') !== false) {
            $parsed['keywords'][] = 'trade support';
        }
        if (strpos($query_lower, 'fund accounting') !== false) {
            $parsed['keywords'][] = 'fund accounting';
        }
        if (strpos($query_lower, 'fund administration') !== false || strpos($query_lower, 'fund admin') !== false) {
            $parsed['keywords'][] = 'fund administration';
        }
        if (strpos($query_lower, 'transfer agency') !== false || strpos($query_lower, 'ta') !== false) {
            $parsed['keywords'][] = 'transfer agency';
        }
        if (strpos($query_lower, 'custody') !== false || strpos($query_lower, 'custodian') !== false) {
            $parsed['keywords'][] = 'custody';
        }
        if (strpos($query_lower, 'fund operations') !== false) {
            $parsed['keywords'][] = 'fund operations';
        }
        if (strpos($query_lower, 'risk management') !== false || strpos($query_lower, 'risk manager') !== false) {
            $parsed['keywords'][] = 'risk management';
        }
        if (strpos($query_lower, 'liquidity risk') !== false) {
            $parsed['keywords'][] = 'liquidity risk';
        }
        if (strpos($query_lower, 'market risk') !== false) {
            $parsed['keywords'][] = 'market risk';
        }
        if (strpos($query_lower, 'credit risk') !== false) {
            $parsed['keywords'][] = 'credit risk';
        }
        if (strpos($query_lower, 'performance measurement') !== false || strpos($query_lower, 'performance analyst') !== false) {
            $parsed['keywords'][] = 'performance measurement';
        }
        if (strpos($query_lower, 'benchmarks') !== false) {
            $parsed['keywords'][] = 'benchmarks';
        }
        if (strpos($query_lower, 'regulatory reporting') !== false || strpos($query_lower, 'regulation') !== false) {
            $parsed['keywords'][] = 'regulatory reporting';
        }

        // 🟦 Investment Products & Structures
        if (strpos($query_lower, 'mutual fund') !== false) {
            $parsed['keywords'][] = 'mutual fund';
        }
        if (strpos($query_lower, 'hedge fund') !== false) {
            $parsed['keywords'][] = 'hedge fund';
        }
        if (strpos($query_lower, 'ucits') !== false) {
            $parsed['keywords'][] = 'ucits';
        }
        if (strpos($query_lower, 'oeic') !== false || strpos($query_lower, 'sicav') !== false) {
            $parsed['keywords'][] = 'oeic/sicav';
        }
        if (strpos($query_lower, 'etf') !== false) {
            $parsed['keywords'][] = 'etf';
        }
        if (strpos($query_lower, 'segregated account') !== false || strpos($query_lower, 'segregated mandate') !== false) {
            $parsed['keywords'][] = 'segregated account';
        }
        if (strpos($query_lower, 'pension fund') !== false) {
            $parsed['keywords'][] = 'pension fund';
        }
        if (strpos($query_lower, 'sovereign wealth') !== false || strpos($query_lower, 'swf') !== false) {
            $parsed['keywords'][] = 'sovereign wealth fund';
        }
        if (strpos($query_lower, 'family office') !== false) {
            $parsed['keywords'][] = 'family office';
        }
        if (strpos($query_lower, 'endowment') !== false || strpos($query_lower, 'foundation') !== false) {
            $parsed['keywords'][] = 'endowment/foundation';
        }


        return $parsed;
    }

    /**
     * Search jobs by tier with progressive relaxation of criteria
     */
    private function search_jobs_by_tier($parsed_query, $tier, $limit, $offset)
    {
        $args = [
            'post_type' => 'sffc_job',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'offset' => $offset,
            'meta_query' => ['relation' => 'AND']
        ];

        // Build query based on tier
        switch ($tier) {
            case 'perfect':
                // Strict matching - all criteria must match
                if (!empty($parsed_query['location'])) {
                    $args['meta_query'][] = [
                        'key' => 'sffc_location',
                        'value' => $parsed_query['location'],
                        'compare' => 'LIKE'
                    ];
                }

                // Keyword/seniority matching - simplified to avoid SQL errors
                if (!empty($parsed_query['keywords'])) {
                    // Use WordPress built-in search which searches title and content
                    $args['s'] = implode(' ', $parsed_query['keywords']);
                }

                if (!empty($parsed_query['company_type'])) {
                    $args['meta_query'][] = [
                        'key' => 'sffc_company_type',
                        'value' => $parsed_query['company_type'],
                        'compare' => '='
                    ];
                }
                break;

            case 'stretch':
                // Relaxed matching - broaden location, allow +/- 1 seniority
                if (!empty($parsed_query['location'])) {
                    // Include nearby cities or remote options
                    $args['meta_query'][] = [
                        'relation' => 'OR',
                        [
                            'key' => 'sffc_location',
                            'value' => $parsed_query['location'],
                            'compare' => 'LIKE'
                        ],
                        [
                            'key' => 'sffc_remote',
                            'value' => 'yes',
                            'compare' => '='
                        ]
                    ];
                }
                // Broaden keyword search
                if (!empty($parsed_query['keywords'])) {
                    $args['s'] = $parsed_query['keywords'][0]; // Use primary keyword only
                }
                break;

            case 'exploratory':
                // Very relaxed - different industries, locations, higher seniority
                // Only apply sector filter if specified
                if (!empty($parsed_query['sector'])) {
                    $args['meta_query'][] = [
                        'key' => 'sffc_industry',
                        'value' => $parsed_query['sector'],
                        'compare' => '!=' // Different industry for exploration
                    ];
                }
                // No location filter - show all locations
                // Order by salary to show growth opportunities
                $args['orderby'] = 'meta_value_num';
                $args['meta_key'] = 'sffc_salary_max';
                $args['order'] = 'DESC';
                break;
        }

        $query = new WP_Query($args);
        $jobs = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $jobs[] = $this->format_job_for_response(get_the_ID());
            }
        }
        wp_reset_postdata();

        return $jobs;
    }

    /**
     * Calculate intelligent match score based on tier and CV data
     */
    private function calculate_intelligent_match_score($job, $cv_data, $tier)
    {
        $score = 0;
        $max_score = 100;

        // Base score by tier
        $tier_base = ['perfect' => 70, 'stretch' => 40, 'exploratory' => 20];
        $score = $tier_base[$tier] ?? 50;

        // Location match
        if (!empty($cv_data['location']) && !empty($job['location'])) {
            if (strtolower($cv_data['location']) === strtolower($job['location'])) {
                $score += 15;
            }
        }

        // Skills match
        if (!empty($cv_data['skills']) && !empty($job['skills'])) {
            $matched_skills = array_intersect(
                array_map('strtolower', $cv_data['skills']),
                array_map('strtolower', $job['skills'])
            );
            $skill_score = (count($matched_skills) / max(count($cv_data['skills']), 1)) * 10;
            $score += $skill_score;
        }

        // Seniority match
        if (!empty($cv_data['seniority']) && !empty($job['seniority_level'])) {
            $seniority_diff = abs($cv_data['seniority'] - $job['seniority_level']);
            if ($seniority_diff === 0) $score += 5;
            elseif ($seniority_diff === 1) $score += 3;
        }

        return min($score, $max_score);
    }

    /**
     * Get match reasons for display
     */
    private function get_match_reasons($job, $cv_data)
    {
        $reasons = [];

        if (!empty($cv_data['latestRole']) && stripos($job['title'], $cv_data['latestRole']) !== false) {
            $reasons[] = "Similar to your current role";
        }

        if (!empty($cv_data['location']) && !empty($job['location'])) {
            if (strtolower($cv_data['location']) === strtolower($job['location'])) {
                $reasons[] = "In your preferred location";
            }
        }

        if (!empty($cv_data['skills']) && !empty($job['skills'])) {
            $matched_skills = array_intersect(
                array_map('strtolower', $cv_data['skills']),
                array_map('strtolower', $job['skills'])
            );
            if (count($matched_skills) > 0) {
                $reasons[] = "Matches " . count($matched_skills) . " of your skills";
            }
        }

        return $reasons;
    }

    /**
     * Format job post data for response
     */
    private function format_job_for_response($post_id)
    {
        return [
            'id' => $post_id,
            'title' => get_the_title($post_id),
            'company' => get_post_meta($post_id, 'sffc_company', true),
            'location' => get_post_meta($post_id, 'sffc_location', true),
            'salary_min' => get_post_meta($post_id, 'sffc_salary_min', true),
            'salary_max' => get_post_meta($post_id, 'sffc_salary_max', true),
            'skills' => get_post_meta($post_id, 'sffc_skills', true) ?: [],
            'seniority_level' => get_post_meta($post_id, 'sffc_seniority_level', true),
            'description' => get_the_content(null, false, $post_id),
            'job_type' => get_post_meta($post_id, 'sffc_job_type', true),
            'posted_date' => get_the_date('Y-m-d', $post_id)
        ];
    }

    /**
     * AJAX: Get shared jobs by IDs - PUBLIC ACCESS
     */
    public function ajax_get_shared_jobs()
    {
        // Note: No nonce check for shared links to work for non-logged-in users
        // This is intentional as shared links should work without authentication

        try {
            // Enable error reporting for debugging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_reporting(E_ALL);
                ini_set('display_errors', 0);
            }

            // Get job IDs from request
            $job_ids_string = isset($_POST['job_ids']) ? sanitize_text_field($_POST['job_ids']) : '';

            if (empty($job_ids_string)) {
                wp_send_json_error(['message' => 'No job IDs provided']);
                wp_die();
            }

            // Parse and validate job IDs
            $job_ids = array_map('intval', explode(',', $job_ids_string));
            $job_ids = array_filter($job_ids, function ($id) {
                return $id > 0;
            });

            // Limit to 10 jobs max for performance
            $job_ids = array_slice($job_ids, 0, 10);

            if (empty($job_ids)) {
                wp_send_json_error(['message' => 'Invalid job IDs']);
                wp_die();
            }


            // Get jobs by IDs
            $jobs = $this->get_jobs_by_ids($job_ids);

            if (empty($jobs)) {
                wp_send_json_error(['message' => 'No jobs found with the provided IDs']);
                wp_die();
            }

            // Track share analytics (optional)
            $this->track_share_click($job_ids);

            wp_send_json_success([
                'jobs' => $jobs,
                'count' => count($jobs)
            ]);
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => 'An error occurred while loading shared jobs',
                'error' => WP_DEBUG ? $e->getMessage() : 'Server error'
            ]);
            wp_die();
        }
    }

    /**
     * Get jobs by specific IDs - OPTIMIZED with caching and single query
     */
    private function get_jobs_by_ids($job_ids)
    {
        if (empty($job_ids)) {
            return [];
        }

        // Sort IDs for consistent cache key
        sort($job_ids);

        // Create cache key based on job IDs
        $cache_key = 'sffc_shared_jobs_' . md5(implode('_', $job_ids));

        // Check transient cache first (1 hour cache)
        $cached_jobs = get_transient($cache_key);
        if ($cached_jobs !== false) {
            return $cached_jobs;
        }


        // Use optimized custom SQL query with JOIN to get all data in one query
        global $wpdb;

        // Prepare the job IDs for SQL IN clause
        $placeholders = implode(',', array_fill(0, count($job_ids), '%d'));

        // Single optimized query to get posts and ALL meta data at once
        $query = $wpdb->prepare("
            SELECT 
                p.ID as job_id,
                p.post_title,
                p.post_content,
                p.post_excerpt,
                p.post_date,
                p.guid,
                GROUP_CONCAT(
                    CONCAT(pm.meta_key, ':::', pm.meta_value) 
                    SEPARATOR '|||'
                ) as all_meta
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.ID IN ($placeholders)
            AND p.post_type = 'sffc_job'
            AND p.post_status = 'publish'
            GROUP BY p.ID
            ORDER BY FIELD(p.ID, $placeholders)
        ", ...array_merge($job_ids, $job_ids));

        $results = $wpdb->get_results($query);

        $found_jobs = [];

        foreach ($results as $job) {
            // Parse all meta data from the concatenated string
            $meta_data = [];
            if (!empty($job->all_meta)) {
                $meta_pairs = explode('|||', $job->all_meta);
                foreach ($meta_pairs as $pair) {
                    $parts = explode(':::', $pair, 2);
                    if (count($parts) == 2) {
                        $meta_data[$parts[0]] = $parts[1];
                    }
                }
            }

            // Extract specific meta values efficiently
            $company = $meta_data['sffc_actual_company'] ?? $meta_data['sffc_company'] ?? 'Company';
            $location = $meta_data['sffc_location'] ?? $meta_data['location'] ?? 'Location';
            $salary_display = $meta_data['sffc_salary_display'] ?? '';
            $highlights = $meta_data['sffc_highlights'] ?? '';

            // Parse salary
            $salary_min = 0;
            $salary_max = 0;
            if ($salary_display) {
                if (preg_match_all('/\$(\d+)k?/i', $salary_display, $matches)) {
                    $salary_min = isset($matches[1][0]) ? intval($matches[1][0]) * 1000 : 0;
                    $salary_max = isset($matches[1][1]) ? intval($matches[1][1]) * 1000 : 0;
                }
            }

            // Unserialize skills if needed
            $skills = isset($meta_data['sffc_skills']) ? maybe_unserialize($meta_data['sffc_skills']) : [];

            // Build job array - KEEPING THE SAME STRUCTURE WITH IDs VISIBLE
            $found_jobs[] = [
                'id' => intval($job->job_id),  // Ensure ID is accessible
                'job_id' => intval($job->job_id),  // Duplicate for compatibility
                'title' => $job->post_title,
                'job_title' => $job->post_title,
                'company' => $company,
                'location' => $location,
                'salary' => $salary_display ?: 'Competitive',
                'salary_min' => $salary_min,
                'salary_max' => $salary_max,
                'type' => $meta_data['sffc_job_type'] ?? 'Full-time',
                'level' => $meta_data['sffc_level'] ?? 'Mid-level',
                'experience' => $meta_data['sffc_experience_required'] ?? '2+ years',
                'skills' => is_array($skills) ? $skills : [],
                'responsibilities' => $meta_data['sffc_responsibilities'] ?? '',
                'qualifications' => $meta_data['sffc_qualifications'] ?? '',
                'requirements' => $meta_data['sffc_requirements'] ?? '',
                'description' => $meta_data['sffc_description'] ?? $job->post_excerpt,
                'url' => get_permalink($job->job_id),
                'highlights' => $highlights,
                'match_score' => 85,
                'posted_date' => date('Y-m-d', strtotime($job->post_date)),
                // Add debug info to verify IDs are accessible
                '_debug_id' => intval($job->job_id)
            ];

            // Log each job ID being processed for debugging
            error_log('SFFC: Processed job ID ' . $job->job_id . ' - ' . $job->post_title);
        }

        // Cache the results for 1 hour (3600 seconds)
        if (!empty($found_jobs)) {
            set_transient($cache_key, $found_jobs, HOUR_IN_SECONDS);
            error_log('SFFC: Cached ' . count($found_jobs) . ' jobs with IDs: ' . implode(',', array_column($found_jobs, 'id')));
        }

        return $found_jobs;
    }

    /**
     * Track share click for analytics
     */
    private function track_share_click($job_ids)
    {
        // Log the share event
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'job_ids' => $job_ids,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referrer' => $_SERVER['HTTP_REFERER'] ?? ''
        ];

        // Store in option or custom table (simplified for now)
        $share_logs = get_option('sffc_share_logs', []);
        $share_logs[] = $log_entry;

        // Keep only last 1000 entries
        if (count($share_logs) > 1000) {
            $share_logs = array_slice($share_logs, -1000);
        }

        update_option('sffc_share_logs', $share_logs);
    }

    /**
     * Get job posts from database
     */
    private function get_job_posts($limit = 6, $offset = 0)
    {
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

                // Get job data - using ACTUAL sffc_ prefixed meta keys
                $company = get_post_meta($post_id, 'sffc_actual_company', true) ?: get_post_meta($post_id, 'sffc_company', true) ?: 'Company';
                $location = get_post_meta($post_id, 'sffc_location', true) ?: get_post_meta($post_id, 'location', true) ?: 'Location';
                $salary_display = get_post_meta($post_id, 'sffc_salary_display', true);
                $highlights = get_post_meta($post_id, 'sffc_highlights', true);

                // Parse salary from display or highlights
                $salary_min = 0;
                $salary_max = 0;
                if ($salary_display) {
                    // Extract from format like "$72k - $106k"
                    if (preg_match_all('/\$(\d+)k?/i', $salary_display, $matches)) {
                        $salary_min = isset($matches[1][0]) ? intval($matches[1][0]) * 1000 : 0;
                        $salary_max = isset($matches[1][1]) ? intval($matches[1][1]) * 1000 : 0;
                    }
                }

                // Get enhanced data for Phase 2
                $skills = get_post_meta($post_id, 'sffc_skills', true) ?: [];
                $responsibilities = get_post_meta($post_id, 'sffc_responsibilities', true) ?: '';
                $qualifications = get_post_meta($post_id, 'sffc_qualifications', true) ?: '';
                $requirements = get_post_meta($post_id, 'sffc_requirements', true) ?: '';
                $description = get_post_meta($post_id, 'sffc_description', true) ?: '';
                // Check all possible application URL meta keys
                $application_url = get_post_meta($post_id, '_sffc_job_application_url', true);
                if (empty($application_url)) {
                    $application_url = get_post_meta($post_id, 'sffc_application_url', true);
                }
                if (empty($application_url)) {
                    $application_url = get_post_meta($post_id, 'sffc_apply_url', true);
                }
                $application_url = $application_url ?: '';


                // Extract key requirements intelligently
                $key_requirements = [];
                if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-intelligent-requirements-extractor.php')) {
                    require_once SFFC_PLUGIN_DIR . 'includes/class-intelligent-requirements-extractor.php';
                    $extractor = SFFC_Intelligent_Requirements_Extractor::get_instance();
                    $key_requirements = $extractor->extract_key_requirements([
                        'qualifications' => $qualifications,
                        'requirements' => $requirements,
                        'responsibilities' => $responsibilities,
                        'description' => $description
                    ]);
                }

                // Calculate match score with explanations
                $match_data = $this->calculate_match_score($post_id);

                // Apply match display filter if available
                $job_data = [
                    'id' => $post_id,
                    'title' => get_the_title(),
                    'company' => $company,
                    'location' => $location,
                    'salary_min' => $salary_min,
                    'salary_max' => $salary_max,
                    'salary_display' => $salary_display ?: '',
                    'job_type' => get_post_meta($post_id, 'sffc_job_type', true) ?: 'Full-time',
                    'description' => wp_trim_words(get_the_content(), 30),
                    'match_score' => $match_data['score'],
                    'match_reasons' => $match_data['reasons'],
                    'posted_date' => get_the_date(),
                    'highlights' => $highlights ?: [],
                    'skills' => is_array($skills) ? array_slice($skills, 0, 10) : [], // Top 10 skills
                    'responsibilities' => wp_trim_words($responsibilities, 50),
                    'qualifications' => wp_trim_words($qualifications, 50),
                    'key_requirements' => $key_requirements, // Add intelligent key requirements
                    'application_url' => $application_url,  // ✅ ADD THIS
                ];

                // Apply match display filter to add enhanced match data
                if (class_exists('SFFC_Match_Display_Frontend')) {
                    $job_data = apply_filters('sffc_job_card_data', $job_data, $post_id);
                }

                $jobs[] = $job_data;
            }
            wp_reset_postdata();
        }

        // NO STATIC DATA - return what we have from database

        return $jobs;
    }

    /**
     * Get total job count
     */
    private function get_total_jobs()
    {
        $count = wp_count_posts('sffc_job');
        return $count->publish ?: 0;
    }


    /**
     * AJAX: Save opportunity to shortlist
     */
    public function ajax_save_opportunity()
    {
        // Verify nonce for security (but allow public access)
        if (!check_ajax_referer('sffc_public_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed']);
            wp_die();
        }

        $job_id = isset($_POST['job_id']) ? intval($_POST['job_id']) : 0;
        $action = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : 'save';

        // For logged in users, save to database
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();

            if ($action === 'save') {
                // Save to user meta or custom table
                $saved = get_user_meta($user_id, 'sffc_saved_jobs', true) ?: [];
                if (!in_array($job_id, $saved)) {
                    $saved[] = $job_id;
                    update_user_meta($user_id, 'sffc_saved_jobs', $saved);
                }
            } else {
                // Remove from saved
                $saved = get_user_meta($user_id, 'sffc_saved_jobs', true) ?: [];
                $saved = array_diff($saved, [$job_id]);
                update_user_meta($user_id, 'sffc_saved_jobs', $saved);
            }
        }

        // For non-logged in users, just return success (they can use localStorage)
        wp_send_json_success(['message' => 'Saved successfully']);
    }

    /**
     * AJAX: Track user preference for learning engine
     */
    public function ajax_track_preference()
    {
        // No login required - track anonymously with session
        $event_type = sanitize_text_field($_POST['event_type'] ?? '');
        $job_id = intval($_POST['job_id'] ?? 0);
        $data = json_decode(stripslashes($_POST['data'] ?? '{}'), true);

        if (empty($event_type) || empty($job_id)) {
            wp_send_json_error(['message' => 'Invalid tracking data']);
        }

        // Track in preference learning system
        if (class_exists('SFFC_Job_Preference_Tracker')) {
            $tracker = SFFC_Job_Preference_Tracker::get_instance();

            // Get or create anonymous session ID
            $session_id = $_COOKIE['sffc_session'] ?? '';
            if (empty($session_id)) {
                $session_id = wp_generate_uuid4();
                setcookie('sffc_session', $session_id, time() + (30 * DAY_IN_SECONDS), '/');
            }

            // Track the preference
            $tracker->track_interaction(
                $session_id,
                $job_id,
                $event_type,
                $data
            );
        }

        wp_send_json_success(['message' => 'Preference tracked']);
    }

    /**
     * Calculate match score with explanations - USING ACTUAL PROFILE DATA
     */
    private function calculate_match_score($job_id)
    {
        // Get actual user profile from profile builder
        $user_profile = $this->get_user_profile_data();

        // Initialize scoring variables
        $score = 0;
        $reasons = [];
        $max_score = 100;

        // Get job data
        $job_skills = get_post_meta($job_id, 'sffc_skills', true) ?: [];
        $job_location = get_post_meta($job_id, 'sffc_location', true) ?: '';
        $job_company = get_post_meta($job_id, 'sffc_company', true) ?: '';
        $job_salary_min = intval(get_post_meta($job_id, 'sffc_salary_min', true) ?: 0);
        $job_salary_max = intval(get_post_meta($job_id, 'sffc_salary_max', true) ?: 0);
        $job_experience = intval(get_post_meta($job_id, 'sffc_experience_required', true) ?: 0);
        $job_industry = get_post_meta($job_id, 'sffc_industry', true) ?: '';
        $job_level = get_post_meta($job_id, 'sffc_level', true) ?: '';

        // 1. Skills Match (40% weight)
        if (!empty($user_profile['skills']) && !empty($job_skills)) {
            $user_skill_names = array_map(function ($skill) {
                return is_array($skill) ? $skill['skill_name'] : $skill;
            }, $user_profile['skills']);

            $matched_skills = array_intersect(
                array_map('strtolower', $user_skill_names),
                array_map('strtolower', $job_skills)
            );

            $skill_match_ratio = count($matched_skills) / max(count($job_skills), 1);
            $skill_score = $skill_match_ratio * 40;
            $score += $skill_score;

            if ($skill_match_ratio >= 0.7) {
                $reasons[] = 'Excellent skills match (' . round($skill_match_ratio * 100) . '%)';
            } elseif ($skill_match_ratio >= 0.5) {
                $reasons[] = 'Good skills alignment';
            } elseif ($skill_match_ratio > 0) {
                $reasons[] = count($matched_skills) . ' matching skills';
            }
        } elseif (!empty($job_skills)) {
            // No profile skills - give minimal points
            $score += 10;
        }

        // 2. Experience Match (20% weight)
        if (!empty($user_profile['years_experience'])) {
            $user_experience = intval($user_profile['years_experience']);

            if ($job_experience > 0) {
                if ($user_experience >= $job_experience && $user_experience <= $job_experience + 3) {
                    $score += 20;
                    $reasons[] = 'Perfect experience level';
                } elseif ($user_experience >= $job_experience - 1 && $user_experience <= $job_experience + 5) {
                    $score += 15;
                    $reasons[] = 'Good experience match';
                } elseif ($user_experience >= $job_experience - 2) {
                    $score += 10;
                } else {
                    $score += 5;
                }
            } else {
                $score += 15; // No specific requirement
            }
        } else {
            $score += 5; // No profile experience data
        }

        // 3. Location Match (15% weight)
        if (!empty($user_profile['preferred_locations']) && !empty($job_location)) {
            $preferred_locations = is_array($user_profile['preferred_locations']) ?
                $user_profile['preferred_locations'] :
                [$user_profile['preferred_locations']];

            $location_matched = false;
            foreach ($preferred_locations as $pref_loc) {
                if (
                    stripos($job_location, $pref_loc) !== false ||
                    stripos($pref_loc, $job_location) !== false
                ) {
                    $location_matched = true;
                    break;
                }
            }

            if ($location_matched) {
                $score += 15;
                $reasons[] = 'Location matches preference';
            } elseif (stripos($job_location, 'remote') !== false) {
                $score += 10;
                $reasons[] = 'Remote opportunity';
            } else {
                $score += 3;
            }
        } else {
            $score += 8; // No location preference set
        }

        // 4. Salary Match (15% weight)
        if (!empty($user_profile['salary_target_min'])) {
            $target_min = intval($user_profile['salary_target_min']);
            $target_max = intval($user_profile['salary_target_max'] ?: $target_min * 1.3);

            if ($job_salary_max >= $target_min && $job_salary_min <= $target_max) {
                $score += 15;
                $reasons[] = 'Salary meets expectations';
            } elseif ($job_salary_max >= $target_min * 0.9) {
                $score += 10;
                $reasons[] = 'Competitive compensation';
            } elseif ($job_salary_max >= $target_min * 0.8) {
                $score += 7;
            } else {
                $score += 3;
            }
        } else {
            $score += 8; // No salary expectation set
        }

        // 5. Industry/Company Match (10% weight)
        if (!empty($user_profile['preferred_industries'])) {
            $preferred_industries = is_array($user_profile['preferred_industries']) ?
                $user_profile['preferred_industries'] :
                [$user_profile['preferred_industries']];

            // Determine job industry
            $job_industry_detected = $this->detect_industry($job_company, $job_industry);

            if (in_array($job_industry_detected, $preferred_industries)) {
                $score += 10;
                $reasons[] = 'Preferred industry';
            } else {
                $score += 3;
            }
        } else {
            // Check for premium companies
            $premium_firms = [
                // 🇺🇸 Global Majors (Already Included)
                'blackstone',
                'kkr',
                'apollo',
                'goldman sachs',
                'morgan stanley',
                'jp morgan',
                'mckinsey',
                'bain',
                'bcg',

                // 🇬🇧 United Kingdom
                'barclays',
                'rothschild & co',
                'rothschild',
                'evercore',
                'lazard',
                'moelis',
                'perella weinberg',
                'ocorian',
                'pjt partners',
                'cvc capital partners',
                'permitting partners',
                'bridgepoint',
                '3i group',
                'baillie gifford',
                'schroders',
                'man group',
                'hermes investment',
                'octopus investments',
                'abrdn',
                'hedge fund',
                'oxford capital',
                'pantheon',
                'bc partners',
                'permidacapital',
                'charterhouse capital',
                'cinven',

                // 🇫🇷 France
                'ardian',
                'eurazeo',
                'pAI partners',
                'wendel',
                'bnpp paribas',
                'societe generale',
                'natixis',
                'lazard paris',
                'rothschild paris',
                'mazars',
                'accuracy',
                'kepler cheuvreux',
                'caixa banque',
                'credit agricole',
                'bpifrance',
                'astorg',
                'omnes capital',
                'idinvest partners',
                'apax partners france',

                // 🇮🇹 Italy
                'mediobanca',
                'intesa sanpaolo',
                'unicredit',
                'cdp equity',
                'f2i sgr',
                'ardian italy',
                'clessidra',
                'nb renaissance',
                'ambienta sgr',
                'alpha associates',
                'equita sim',
                'gianni origoni',
                'p101 ventures',
                'algebris investments',
                'fineco asset management',
                'banca generali',
                'kairos partners',
                'azimut',
                'aris capital',

                // 🇪🇸 Spain
                'bbva',
                'santander',
                'bankinter',
                'sabadell',
                'nmas1',
                'miura partners',
                'portobello capital',
                'corpfin capital',
                'magnum capital',
                'altamar capital partners',
                'arcano partners',
                'gala capital',
                'seaya ventures',
                'nazca capital',
                'axa im spain',
                'mutua madrileña',
                'everwood capital',

                // 🇩🇪 Germany
                'deutsche bank',
                'commerzbank',
                'allianz',
                'munich re',
                'dws group',
                'bertelsmann',
                'rocket internet',
                'deutsche boerse',
                'bayern kapital',
                'apax partners germany',
                'silverfleet capital',
                'montagu private equity',
                'capiton ag',
                'triton partners',
                'union investment',
                'macquarie germany',
                'bain germany',
                'bcg germany',
                'mckinsey germany',

                // 🇨🇭 Zurich / Switzerland
                'ubs',
                'credit suisse',
                'pictet',
                'lgt capital partners',
                'partners group',
                'lombard odier',
                'vontobel',
                'mirabaud',
                'julius baer',
                'zürcher kantonalbank',
                'gam investments',
                'elanders capital',
                'baer capital',
                'heritage capital',

                // 🇧🇷 Brazil
                'btg pactual',
                'itau bba',
                'bradesco bbi',
                'xp investimentos',
                'patria investments',
                'gávea investimentos',
                'vale',
                '3g capital',
                'temasek brazil',
                'tarpon investments',
                'ambipar capital',
                'renaissance capital brazil',
                'credit suisse brazil',
                'banco do brasil',
                'safra bank',
                'br partners',
                'vinci partners',
                'cyrela capital',
                'hsbc brazil',

                // 🏦 Global AM / PE names with European hubs
                'blackrock',
                'vanguard',
                'fidelity',
                'wellington',
                'pimco',
                'invesco',
                't rowe price',
                'neuberger berman',
                'franklin templeton',
                'axa investment managers',
                'amundi',
                'robeco',
                'gic',
                'temasek',
                'cpp investments',
                'ontario teachers pension plan',
                'aberdeen standard',
                'leggmason',
                'brookfield',
                'macquarie',
                'bridgewater associates',
                'citadel',
                'man group',
                'two sigma',
                'millennium management',
                'point72',
                'KKR',
                'EQT',
                'Blackstone',
                'Thoma Bravo',
                'TPG',
                'CVC Capital Partners',
                'Hg',
                'Hellman & Friedman',
                'Clayton, Dubilier & Rice',
                'Insight Partners',
                'Silver Lake',
                'Clearlake Capital Group',
                'General Atlantic',
                'Goldman Sachs Asset Management',
                'Bain Capital',
                'Advent International',
                'The Carlyle Group',
                'Warburg Pincus',
                'Andreessen Horowitz',
                'Vista Equity Partners',
                'Apollo Global Management',
                'Neuberger Berman',
                'TA Associates',
                'GTCR',
                'Veritas Capital',
                'Bridgepoint',
                'New Mountain Capital',
                'Partners Group',
                'Cinven',
                'Apax Partners',
                'Stone Point Capital',
                'Nordic Capital',
                'Leonard Green & Partners',
                'Francisco Partners',
                'Tiger Global Management',
                'Blue Owl Capital',
                'Brookfield Asset Management',
                'Genstar Capital',
                'Permira Advisers',
                'BDT & MSD Partners',
                'L Catterton*',
                'Summit Partners',
                'Ardian',
                'Platinum Equity',
                'China Merchants Capital',
                'Hillhouse Capital Group',
                'PSG',
                'HarbourVest Partners',
                'The Jordan Company',
                'ICONIQ Capital',
                'Hamilton Lane',
                'BlackRock',
                'Astorg',
                'China Reform Fund Management Corporation',
                'Vitruvian Partners',
                'PAI Partners',
                'Accel',
                'Lightspeed Venture Partners',
                'Coatue Management',
                'MBK Partners',
                'HongShan',
                'Berkshire Partners',
                'Roark Capital Group',
                'H.I.G Capital',
                'Thomas H. Lee Partners',
                'General Catalyst Partners',
                'BC Partners',
                'LGT Capital Partners',
                'Adams Street Partners',
                'Morgan Stanley Investment Management',
                'Oak Hill Capital',
                'Quantum Energy Partners',
                'K1 Investment Management',
                'Bregal Investments',
                'Audax Group',
                'Patient Square Capital',
                'STG',
                'Arctos Partners',
                'New Enterprise Associates',
                'Thrive Capital',
                'GI Partners',
                'Oaktree Capital Management',
                'KPS Capital Partners',
                'Centerbridge Partners',
                'IK Partners',
                'Alpine Investors',
                'ARCH Venture Partners',
                'Waterland Private Equity Investments',
                'CPE',
                'Oakley Capital Private Equity',
                'Kohlberg & Company',
                'Madison Dearborn Partners',
                'Lindsay Goldberg',
                'Bessemer Venture Partners',
                'TSG Consumer Partners',
                'Accel-KKR',
                'Inflexion Private Equity Partners',
                'TCV',
                'Index Ventures',
                'Ares Management',
                'FTV Capital',
                'Eurazeo',
                'Investindustrial',
                'Nautic Partners',
                'Valor Equity Partners',
                'Archimed',
                'TowerBrook Capital Partners',
                'Arcline Investment Management',
                'Founders Fund',
                'One Equity Partners',
                'Flagship Pioneering',
                'B Capital',
                'AEA Investors',
                'Investcorp',
                'DCP Capital',
                'Hahn & Co.',
                'EnCap Investments',
                'Five Arrows',
                'Kinderhook Industries',
                'The Sterling Group',
                'GHO Capital Partners',
                'Battery Ventures',
                'Altaris',
                'Arsenal Capital Partners',
                'Norwest',
                'Searchlight Capital Partners',
                'Montagu Private Equity',
                'Primavera Capital Group',
                'TDR Capital',
                'Kelso & Company',
                'Wynnchurch Capital',
                'The Vistria Group',
                'Charlesbank Capital Partners',
                'Triton Partners',
                'Wellington Management',
                'Welsh, Carson, Anderson & Stowe',
                'Frazier Healthcare Partners',
                'Harvest Partners',
                'J.F. Lehman & Company',
                'Keensight Capital',
                'Qiming Venture Partners',
                'CBC Group',
                'Ara Partners',
                'Lone Star Funds',
                'Kleiner Perkins',
                'OrbiMed Advisors',
                'Great Hill Partners',
                'Tikehau Capital',
                'Verdane',
                'Khosla Ventures',
                'Forbion',
                'Greenbriar Equity Group',
                'American Industrial Partners',
                'Sapphire Ventures',
                'EMK Capital',
                'Main Capital Partners',
                'Legend Capital',
                'Parthenon Capital Partners',
                'Altas Partners',
                'Peak Rock Capital',
                'JMI Equity',
                'Onex',
                'Hony Capital',
                'Peak XV Partners',
                'Patria Investments',
                'Aquiline Capital Partners',
                'BOND',
                'Wind Point Partners',
                'Novacap',
                'Sentinel Capital Partners',
                'Yingke Private Equity',
                'Spectrum Equity',
                'NGP Energy Capital Management',
                'Trivest Partners',
                'Pathway Capital Management',
                'Linden Capital Partners',
                'Trive Capital',
                'Sagard Partners',
                'Gaorong Capital',
                'EIG',
                'Capital Constellation',
                'Haveli Investments',
                'SK Capital Partners',
                'Arlington Capital Partners',
                'Gridiron Capital',
                'Churchill Asset Management',
                'Schroders Capital',
                'Incline Equity Partners',
                'LLR Partners',
                'PAG',
                'Montefiore Investment',
                'One Rock Capital Partners',
                'IMM Private Equity',
                'Hunter Point Capital',
                'Cathay Capital Private Equity',
                'Y Combinator',
                'CAZ Investments',
                'CapVest',
                'China Renaissance Group',
                'Cerberus Capital Management',
                'Reverence Capital Partners',
                'Generation Investment Management',
                'Aurora Capital Partners',
                'The Riverside Company',
                'Warren Equity Partners',
                'Castik Capital',
                'Altamar CAM Partners',
                'RUBICON Technology Partners',
                'Adelis Equity Partners',
                'Ridgemont Equity Partners',
                'Gryphon Investors',
                'Riverwood Capital',
                'Paine Schwartz Partners',
                'Pacific Equity Partners',
                'Butterfly Equity',
                'Cove Hill Partners',
                'Vivo Capital',
                'Energy Impact Partners',
                'Paradigm',
                'IVP',
                'Shore Capital Partners',
                'Ribbit Capital',
                'American Securities',
                'Monomoy Capital Partners',
                'WestCap',
                'Summa Equity',
                'Oak HC/FT',
                'Vertex Holdings',
                'Altor Equity Partners',
                'RedBird Capital Partners',
                'Altimeter Capital Management',
                'Motive Partners',
                'Odyssey Investment Partners',
                'Tenex Capital Management',
                'Providence Equity Partners',
                'FSN Capital',
                'Lexington Partners',
                'Cortec Group',
                'Levine Leichtman Capital Partners',
                'CDH Investments',
                'Graham Partners',
                'SkyKnight Capital',
                'Torquest Partners',
                'Stripes',
                'Pantheon',
                'Atlas Holdings',
                'Lee Equity Partners',
                'Lightyear Capital',
                'Bonaccord Capital Partners',
                'Lead Edge Capital',
                'CITIC Capital',
                'The Column Group',
                'Brightstar Capital Partners',
                'OceanSound Partners',
                'Brighton Park Capital',
                'Liberty Strategic Capital',
                'BV Investment Partners',
                'Appian Capital Advisory',
                'Rhône Group',
                'Lightrock',
                'DFJ Growth',
                'Gemspring Capital',
                'Shamrock Capital',
                'Baypine',
                'FountainVest Partners',
                'Alvarez & Marsal Capital Partners',
                'Latour Capital',
                'Webster Equity Partners',
                'Flexpoint Ford',
                'Leeds Equity Partners',
                'Integral Corporation',
                '5Y Capital',
                'Crestview Partners',
                'Bertram Capital',
                'MPC',
                'Kedaara Capital',
                'Breakthrough Energy',
                'Menlo Ventures',
                'Lux Capital Management',
                'GreenOaks Capital Partners',
                'Avista Healthcare Partners',
                'AE Industrial Partners',
                'LS Power Group',
                'Sequoia Capital',
                'Corsair Capital',
                'Rivean Capital',
                'Notable Capital',
                'Eastern Bell Capital',
                'Source Code Capital',
                'Valar Ventures',
                'Siguler Guff',
                'Mill Point Capital',
                'Neos Partners',
                'Craft Ventures',
                'Balderton Capital',
                'Norvestor',
                'Ampersand Capital Partners',
                'Seven2',
                'HGGC',
                'Revelstoke Capital Partners',
                'A&M Capital',
                'Aberdeen',
                'Antin',
                'Equistone',
                'European',
                'HarbourVest',
                'Macquarie',
                'Midlands Engine Investment Fund',
                'Partners',
                'Schroders',
                'Elysian',
                'Enterprise',
                'Environmental',
                'Equitix',
                'Growth',
                'Infracapital',
                'NorthEdge',
                'Palatine',
                'Resonance',
                'RisingStars',
                'Tenzing',
                'Westbridge',
                'Yorkshire',
                'Alpine',
                'BVIP',
                'CCMP',
                'Falko',
                'GENERAL',
                'Genstar',
                'Goldman',
                'GS',
                'HIPEP',
                'InfraBridge',
                'ISQ',
                'Lexington',
                'Napier',
                'North',
                'Q-BLK',
                'Sciens',
                'Stonepeak',
                'Tiger',
                'Trilantic',
                '3i Group',
                '50 South Capital',
                'ABRY Partners',
                'ACON Investments',
                'Altaris Capital Partners',
                'Arctos Sports Partners',
                'Avista Capital Partners',
                'Barings',
                'BDT Capital Partners',
                'Castlelake',
                'Cornell Capital',
                'Court Square Capital Partners',
                'Denham Capital Management',
                'Eight Roads',
                'Equistone Partners Europe',
                'Freeman Spogli & Co.',
                'Fundamental Advisors',
                'GCM Grosvenor',
                'GGV Capital',
                'Goldman Sachs',
                'G Squared',
                'Hermes GPE',
                'H.I.G. Capital',
                'IDG Capital',
                'IK Investment Partners',
                'Inflexion Private Equity',
                'Kayne Anderson Capital Advisors',
                'Kimmeridge',
                'KSL Capital Partners',
                'L Catterton',
                'Lime Rock Partners',
                'Littlejohn & Co',
                'Marlin Equity Partners',
                'Matrix Partners',
                'Mid Europa Partners',
                'MidOcean Partners',
                'Neuberger Berman Private Markets',
                'Norwest Venture Partners',
                'Olympus Partners',
                'Owl Ventures',
                'Palladium Equity Partners',
                'Pamplona Capital Partners',
                'Peppertree Capital Management',
                'Pollen Street Capital',
                'Quad-C Management',
                'Rhone Group',
                'Riverstone Holdings',
                'RoundShield Partners',
                'Section Partners',
                'Siris Capital Group',
                'Summit Rock Advisors',
                'Sun Capital Partners',
                'Sycamore Partners',
                'Symphony Technology Group',
                'Tailwater Capital',
                'The Energy & Minerals Group',
                'Thompson Street Capital Partners',
                'Trilantic Capital Partners North America',
                'Varde Partners',
                'Versant Ventures',
                'Vestar Capital Partners',
                'Vistria Group',
                'Waud Capital Partners',
                'Wellington Management Company',
            ];

            if (in_array(strtolower($job_company), $premium_firms)) {
                $score += 8;
                $reasons[] = 'Top-tier firm';
            } else {
                $score += 5;
            }
        }

        // Ensure score is within bounds
        $score = min(100, max(0, round($score)));

        // Add default reasons if needed
        if (empty($reasons)) {
            if ($score >= 80) {
                $reasons = ['Strong alignment', 'Excellent opportunity', 'Career advancement'];
            } elseif ($score >= 60) {
                $reasons = ['Good potential', 'Career growth', 'Relevant role'];
            } elseif ($score >= 40) {
                $reasons = ['Transferable skills', 'New opportunity', 'Career pivot'];
            } else {
                $reasons = ['Stretch role', 'Learning opportunity', 'New sector'];
            }
        }

        return [
            'score' => $score,
            'reasons' => array_slice($reasons, 0, 3), // Max 3 reasons
            'breakdown' => [
                'skills' => isset($skill_score) ? round($skill_score) : 0,
                'experience' => isset($user_experience) ? 'Matched' : 'Not set',
                'location' => isset($location_matched) && $location_matched ? 'Matched' : 'Partial',
                'salary' => isset($target_min) ? 'In range' : 'Not set'
            ]
        ];
    }

    /**
     * Get user profile data from database or session
     */
    private function get_user_profile_data()
    {
        global $wpdb;

        // Try to get logged-in user profile first
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $profile_table = $wpdb->prefix . 'sffc_user_profiles';

            $profile = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $profile_table WHERE user_id = %d",
                $user_id
            ), ARRAY_A);

            if ($profile) {
                // Get skills
                $skills_table = $wpdb->prefix . 'sffc_user_skills';
                $skills = $wpdb->get_results($wpdb->prepare(
                    "SELECT skill_name, proficiency_level FROM $skills_table WHERE user_id = %d",
                    $user_id
                ), ARRAY_A);

                $profile['skills'] = $skills;

                // Unserialize arrays
                $profile['preferred_locations'] = maybe_unserialize($profile['preferred_locations']);
                $profile['preferred_industries'] = maybe_unserialize($profile['preferred_industries']);

                return $profile;
            }
        }

        // Try session/transient for anonymous users
        $session_id = $_COOKIE['sffc_session_id'] ?? '';
        if ($session_id) {
            $session_profile = get_transient('sffc_profile_' . $session_id);
            if ($session_profile) {
                return $session_profile;
            }
        }

        // Return empty profile if nothing found
        return [
            'skills' => [],
            'years_experience' => 0,
            'preferred_locations' => [],
            'salary_target_min' => 0,
            'salary_target_max' => 0,
            'preferred_industries' => []
        ];
    }

    /**
     * Detect industry from company name or industry field
     */
    private function detect_industry($company, $industry = '')
    {
        if (!empty($industry)) {
            return $industry;
        }

        $company_lower = strtolower($company);

        // 🏦 Investment Banking
        // 🇦🇪 private equity Banks & Financial Institutions (Priority)
        if (preg_match('/(emirates\snbd|fab|first\sabu\sdhabi|adcb|abu\sdhabi\scommercial|mashreq|dib|dubai\sislamic|enbd|rakbank|cbd|commercial\sbank\sof\sdubai|nbad|national\sbank\sof\sabu\sdhabi|adib|abu\sdhabi\sislamic|ncb|national\scommercial\sbank|snb|saudi\snational|al\srajhi|samba|riyad\sbank|banque\ssaudi\sfransi|alinma|arab\snational\sbank|albilad|sab|saudi\sawwal|qnb|qatar\snational|commercial\sbank\sof\sqatar|doha\sbank|qib|qatar\sislamic|ahli\sbank|nbk|national\sbank\sof\skuwait|gulf\sbank|burgan|kfh|kuwait\sfinance|boubyan|bmo|bank\smuscat|national\sbank\sof\soman|oman\sarab|sohar|ahli\sunited|gulf\sinternational\sbank|gib|arab\sbanking\scorporation|abc|investcorp|waha\scapital|al\smal\scapital|shuaa|arqaam|efg\shermes|ci\scapital|naeem)/i', $company_lower)) {
            return 'private equity Banking';
        }

        // 👑 Sovereign Wealth Funds
        if (preg_match('/(adia|abu\sdhabi\sinvestment|mubadala|pif|public\sinvestment\sfund|qia|qatar\sinvestment|kia|kuwait\sinvestment|oman\sinvestment|bahrain\smumtalakat|emirates\sinvestment|adq|gic|temasek|cppib|adic|abu\sdhabi\sinvestment\scouncil|taameer|masdar)/i', $company_lower)) {
            return 'Sovereign Wealth';
        }

        if (preg_match('/(bank|goldman|morgan|jp\smorgan|j\.p\.\smorgan|barclays|citi|citigroup|ubs|credit\ssuisse|deutsche|bnp\sparibas|societe\sgenerale|natixis|unicredit|intesa|mediobanca|bbva|santander|commerzbank|lazard|rothschild|evercore|moelis|perella|pjt|jefferies|macquarie|nomura|hsbc|rbccm|rbc\scapital\smarkets|wells\sfargo|opencapital|btg\spactual|itau|bradesco|safra)/i', $company_lower)) {
            return 'Investment Banking';
        }

        // 🏦 Private Equity
        if (preg_match('/(blackstone|kkr|apollo|carlyle|tpg|warburg|advent|hellman\s*&\s*friedman|bain\scapital|cvc|bridgepoint|3i\s*group|ardian|pai\spartners|eurazeo|cinven|bc\spartners|charterhouse|apax|astorg|f2i|clessidra|nb\s*renaissance|ambienta|miura\spartners|portobello|corpfin|magnum\scapital|triton|silverfleet|montagu|patria|gávea|vinci\spartners|3g\scapital|seaya|nazca|arcano|altamar|gala\scapital|eqt|thoma\s*bravo|hg|clayton,\s*dubilier\s*&\s*rice|insight\spartners|clearlake\scapital|general\satlantic|goldman\ssachs\s*asset\s*management|ta\s*associates|gtcr|veritas\scapital|new\smountain\scapital|partners\sgroup|stone\spoint\scapital|nordic\scapital|leonard\sgreen\s*&\s*partners|francisco\spartners|blue\s*owl\scapital|genstar\scapital|permira|bdt\s*&\s*msd\spartners|l\s*catterton|summit\spartners|platinum\sequity|psg|harbourvest\spartners|the\s*jordan\s*company|iconiq\scapital|hamilton\slane|china\s*merchants\scapital|mbk\spartners|berkshire\spartners|roark\scapital|h\.?\s*i\.?\s*g\.?\s*capital|thomas\s*h\.?\s*lee\spartners|bc\spartners|lgt\scapital\spartners|adams\sstreet\spartners|morgan\s*stanley\sinvestment\s*management|oak\shill\scapital|quantum\s*energy\spartners|k1\sinvestment\s*management|bregal\sinvestments|audax\sgroup|patient\ssquare\scapital|stg|arctos\spartners|gi\spartners|oaktree\scapital\s*management|kps\scapital\spartners|centerbridge\spartners|ik\spartners|alpine\sinvestors|waterland\sprivate\sequity\sinvestments|cpe|oakley\scapital\sprivate\sequity|kohlberg\s*&\s*company|madison\sdearborn\spartners|lindsay\sgoldberg|tsg\sconsumer\spartners|inflexion\sprivate\sequity\spartners|ares\smanagement|ftv\scapital|investindustrial|nautic\spartners|valor\sequity\spartners|archimed|towerbrook\scapital\spartners|arcline\sinvestment\smanagement|aea\sinvestors|investcorp|hahn\s*&\s*co\.?|encap\sinvestments|five\sarrows|kinderhook\sindustries|the\ssterling\sgroup|gho\scapital\spartners|altaris|arsenal\scapital\spartners|searchlight\scapital\spartners|montagu\sprivate\sequity|primavera\scapital\sgroup|tdr\scapital|kelso\s*&\s*company|wynnchurch\scapital|the\svistria\sgroup|charlesbank\scapital\spartners|triton\spartners|welsh,\s*carson,\s*anderson\s*&\s*stowe|harvest\spartners|j\.?\s*f\.?\s*lehman\s*&\s*company|keensight\scapital|cbc\sgroup|ara\spartners|lone\s*star\sfunds|great\shill\spartners|tikehau\scapital|verdane|forbion|greenbriar\sequity\sgroup|american\sindustrial\spartners|emk\scapital|main\scapital\spartners|parthenon\scapital\spartners|altas\spartners|peak\srock\scapital|jmi\sequity|onex|hony\scapital|peak\sxv\spartners|aquiline\scapital\spartners|wind\spoint\spartners|novacap|sentinel\scapital\spartners|spectrum\sequity|ngp\senergy\scapital\smanagement|trivest\spartners|linden\scapital\spartners|trive\scapital|sagard\spartners|eig|capital\sconstellation|sk\scapital\spartners|arlington\scapital\spartners|gridiron\scapital|churchill\sasset\smanagement|schroders\scapital|incline\sequity\spartners|llr\spartners|pag|montefiore\sinvestment|one\srock\scapital\spartners|imm\sprivate\sequity|hunter\spoint\scapital|cathay\scapital\sprivate\sequity|caz\sinvestments|capvest|cerberus\scapital\smanagement|reverence\scapital\spartners|aurora\scapital\spartners|the\sriverside\scompany|warren\sequity\spartners|castik\scapital|altamar\s*cam\spartners|rubicon\stechnology\spartners|adelis\sequity\spartners|ridgemont\sequity\spartners|gryphon\sinvestors|riverwood\scapital|paine\sschwartz\spartners|pacific\sequity\spartners|butterfly\sequity|cove\shill\spartners|energy\simpact\spartners|american\ssecurities|monomoy\scapital\spartners|westcap|summa\sequity|redbird\scapital\spartners|motive\spartners|odyssey\sinvestment\spartners|tenex\scapital\smanagement|providence\sequity\spartners|fsn\scapital|lexington\spartners|cortec\sgroup|levine\sleichtman\scapital\spartners|cdh\sinvestments|graham\spartners|skyknight\scapital|torquest\spartners|pantheon|atlas\sholdings|lee\sequity\spartners|lightyear\scapital|bonaccord\scapital\spartners|lead\sedge\scapital|citic\scapital|brightstar\scapital\spartners|oceansound\spartners|brighton\spark\scapital|bv\sinvestment\spartners|appian\scapital\sadvisory|rhône\sgroup|lightrock|gemspring\scapital|shamrock\scapital|baypine|fountainvest\spartners|alvarez\s*&\s*marsal\scapital\spartners|latour\scapital|webster\sequity\spartners|flexpoint\sford|leeds\sequity\spartners|integral\scorporation|crestview\spartners|bertram\scapital|mpc|kedaara\scapital|avista\shealthcare\spartners|ae\sindustrial\spartners|ls\spower\sgroup|corsair\scapital|rivean\scapital|eastern\sbell\scapital|siguler\sguff|mill\spoint\scapital|neos\spartners|balderton\scapital|norvestor|ampersand\scapital\spartners|seven2|hggc|revelstoke\scapital\spartners|a&m\scapital|antin|equistone|harbourvest|macquarie|equitix|northledge|palatine|resonance|tenzing|westbridge|yorkshire|alpine|ccmp|falko|general|infra\sbridge|isq|napier|north|stonepeak|trilantic|abry\spartners|acon\sinvestments|altaris\scapital\spartners|arctos\ssports\spartners|avista\scapital\spartners|barings|bdt\scapital\spartners|castlelake|cornell\scapital|court\ssquare\scapital\spartners|denham\scapital\smanagement|eight\sroads|equistone\spartners\seurope|freeman\sspogli|fundamental\sadvisors|gcm\sgrosvenor|g\squared|hermes\sgpe|h\.?\s*i\.?\s*g\.?\s*capital|idg\scapital|ik\sinvestment\spartners|inflexion\sprivate\sequity|kayne\sanderson\scapital\sadvisors|ksl\scapital\spartners|lime\srock\spartners|littlejohn|marlin\sequity\spartners|matrix\spartners|mid\seuropa\spartners|midocean\spartners|neuberger\sberman\sprivate\smarkets|olympus\spartners|palladium\sequity\spartners|pamplona\scapital\spartners|peppertree\scapital\smanagement|pollen\sstreet\scapital|quad-c\smanagement|rhone\sgroup|riverstone\sholdings|roundshield\spartners|siris\scapital\sgroup|summit\srock\sadvisors|sun\scapital\spartners|sycamore\spartners|symphony\stechnology\sgroup|tailwater\scapital|the\senergy\s*&\s*minerals\sgroup|thompson\sstreet\scapital\spartners|trilantic\scapital\spartners\s*north\s*america|varde\spartners|versant\sventures|vestar\scapital\spartners|vistria\sgroup|waud\scapital\spartners|wellington\smanagement)/i', $company_lower)) {
            return 'Private Equity';
        }

        // 🚀 Venture Capital
        if (preg_match('/(andreessen\s*horowitz|sequoia\scapital|accel|lightspeed\s*venture\spartners|insight\s*venture\spartners|general\scatalyst|bessemer\s*venture\spartners|index\s*ventures|battery\s*ventures|coatu(e|e)\s*management|kleiner\s*perkins|y\s*combinator|thrive\scapital|new\s*enterprise\s*associates|nea|arch\s*venture\spartners|founders\sfund|flagship\spioneering|b\s*capital|dfj\sgrowth|menlo\s*ventures|lux\scapital\s*management|notable\scapital|ribbit\scapital|paradigm|ivp|sapphire\s*ventures|craft\s*ventures|balderton\scapital|norwest\s*venture\spartners|seven2|lead\s*edge\scapital|stripes|section\spartners|owl\s*ventures|matrix\spartners|idg\scapital|ggv\scapital|eight\s*roads|g\s*squared|summit\s*rock\sadvisors|bond|qiming\s*venture\spartners|gaorong\scapital|vertex\sholdings|5y\scapital|breakthrough\s*energy|dfj|earlybird\s*venture\scapital|northzone|seedcamp|speedinvest|point\s*nine\scapital|atomico|partech|alven\scapital|khosla\sventures|forbion|hongshan|coatue|coastline|felicis|a16z|500\s*startups|boost\s*vc|yc|ycombinator|venture\s*capital|venture\s*partners|venture\s*fund)/i', $company_lower)) {
            return 'Venture Capital';
        }



        // 📈 Asset Management / Hedge Funds
        // 🐯 Hedge Funds / Asset Managers
        if (preg_match('/(blackrock|vanguard|fidelity|schroders|pimco|wellington|t\s*rowe\s*price|invesco|neuberger|franklin|amundi|axa\sinvestment|robeco|baillie\sgifford|man\sgroup|two\s*sigma|citadel|bridgewater|point72|millennium|gic|temasek|cpp|ontario\steachers|dws|allianz|vontobel|pictet|lgt|partners\sgroup|lombard|mirabaud|julius\s*baer|gam\sinvestments|azimut|kairos|algebris|banca\sgenerali|fineco|octopus|abrdn|hermes|pantheon|mutua\smadrileña|tiger\sglobal|coatue|renaissance\stechnologies|man\s*a\sgroup|marshall\swace|millennium\smanagement|third\spoint|baupost\sgroup|pershing\ssquare|elliott\smanagement|d\.?\s*e\.?\s*shaw|two\s*creeks|glg\spartners|brevan\*howard|bluecrest\scapital|manulife\sinvestment|legal\s*&\s*general|janus\shenderson|carmignac|natixis\sinvestment\smanagers|morgan\sstanley\sinvestment\smanagement|jp\s*morgan\sasset\smanagement|goldman\ssachs\sasset\smanagement|blackstone\salternatives|brookfield\sasset\smanagement|oaktree\scapital\smanagement|apollo\sglobal\smanagement|adir\s*investment|mubadala|adia|qia|korea\s*investment\scorporation|future\sfund|teacher\sretirement|calpers|calstrs|norges\sbank\sinvestment\smanagement|nbim)/i', $company_lower)) {
            return 'Hedge Fund';
        }


        // 🧠 Consulting / Advisory
        if (preg_match('/(mckinsey|bain|bcg|boston\sconsulting|deloitte|pwc|pricewaterhousecoopers|kpmg|ey|ernst\syoung|accenture|oliver\swyman|strategy&|roland\sberger|lek\sconsulting|alix\sPartners|occstrategy|analysis\sgroup|bering\sPoint|mazars|accuracy)/i', $company_lower)) {
            return 'Consulting';
        }

        // 💻 Technology
        if (preg_match('/(google|microsoft|amazon|apple|meta|facebook|netflix|alphabet|oracle|ibm|sap|salesforce|adobe|palantir|stripe|shopify|snowflake|tiktok|bytedance|linkedin)/i', $company_lower)) {
            return 'Technology';
        }

        return 'General';
    }

    /**
     * AJAX: Check profile completion
     */
    public function ajax_check_profile_completion()
    {
        global $wpdb;

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_success(['is_complete' => false]);
            return;
        }

        // Check profile table
        $profile_table = $wpdb->prefix . 'sffc_user_profiles';
        $profile = $wpdb->get_row($wpdb->prepare(
            "SELECT full_name, years_experience FROM $profile_table WHERE user_id = %d",
            $user_id
        ));

        // Check skills table
        $skills_table = $wpdb->prefix . 'sffc_user_skills';
        $skills_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $skills_table WHERE user_id = %d",
            $user_id
        ));

        $is_complete = ($profile &&
            !empty($profile->full_name) &&
            !empty($profile->years_experience) &&
            $skills_count > 0);

        $membership_url = $this->get_membership_join_url();

        wp_send_json_success([
            'is_complete' => $is_complete,
            'has_name' => !empty($profile->full_name),
            'has_experience' => !empty($profile->years_experience),
            'has_skills' => $skills_count > 0,
            'membership_url' => $is_complete ? $membership_url : ''
        ]);
    }

    /**
     * Resolve the best membership join URL (MemberPress if available)
     */
    private function get_membership_join_url()
    {
        $default_url = esc_url_raw(get_option('sffc_registration_url', 'https://joinsenna.com/memberships/'));

        if (class_exists('SFFC_MemberPress_Integration')) {
            $integration = SFFC_MemberPress_Integration::get_instance();
            if (method_exists($integration, 'get_available_memberships')) {
                $memberships = $integration->get_available_memberships();
                if (!empty($memberships)) {
                    foreach ($memberships as $membership) {
                        if (!empty($membership['register_url'])) {
                            return esc_url_raw($membership['register_url']);
                        }
                    }
                }
            }
        }

        return $default_url;
    }


    /**
     * AJAX: Process chat query with Claude API
     */
    public function ajax_process_chat_query()
    {
        // Verify nonce
        if (!check_ajax_referer('sffc_public_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed']);
            return;
        }

        $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
        $context = isset($_POST['context']) ? sanitize_text_field($_POST['context']) : 'general';

        if (empty($query)) {
            wp_send_json_error(['message' => 'No query provided']);
            return;
        }

        // Check if Claude API is available
        if (class_exists('SFFC_Claude_API_Manager')) {
            $claude = SFFC_Claude_API_Manager::get_instance();

            // Prepare strict learning-coach prompt
            $prompt = $this->prepare_chat_prompt($query, $context);

            try {
                // Call Claude API
                $response = $claude->call_api($prompt, [
                    'max_tokens' => 500,
                    'temperature' => 0.7,
                    'mode' => 'pe_tutor'
                ]);

                if ($response && isset($response['content'])) {
                    $result = [
                        'message' => $response['content'][0]['text'] ?? $response['content']
                    ];

                    wp_send_json_success($result);
                } else {
                    // Fallback response
                    wp_send_json_error(['message' => 'Could not process your request']);
                }
            } catch (Exception $e) {
                error_log('SFFC Chat Query Error: ' . $e->getMessage());
                wp_send_json_error(['message' => 'An error occurred processing your request']);
            }
        } else {
            // Claude not available, use fallback
            $this->process_fallback_chat($query, $context);
        }
    }

    /**
     * Prepare chat prompt for Claude
     */
    private function prepare_chat_prompt($query, $context)
    {
        $prompt = "You are MENA Careers, a dedicated finance technical learning coach for investment banking, asset management, and private equity candidates. This is strictly a teaching tool, not a jobs, applications, salary, CV, recruiting, or career-advice tool.\n\n";
        $prompt .= "Run a continuous lesson. Do not restart unless the student asks for a new topic. Use the student's wording to infer their learning style: beginner, numeric/model-driven, conceptual, concise, or exploratory. Adapt quietly.\n\n";
        $prompt .= "Teaching loop:\n";
        $prompt .= "1. Connect to the previous idea or the student's latest answer.\n";
        $prompt .= "2. Teach one concept clearly.\n";
        $prompt .= "3. Give a worked example from the relevant finance track with real numbers or formulas.\n";
        $prompt .= "4. Ask exactly one focused practice question.\n";
        $prompt .= "5. If the student gives an answer, mark what is right, correct what is wrong, and advance one step.\n\n";

        $prompt .= "Student input: \"$query\"\n";
        $prompt .= "If the student asks about jobs, roles, openings, applications, CVs, salary, or interviews, briefly redirect: this space is for learning. Then convert the question into a technical finance lesson.\n";
        $prompt .= "Do not mention market conditions or generic complex analysis. Do not give job listings. Do not ask about career goals.\n";

        return $prompt;
    }

    /**
     * Check if query is job search related
     */
    private function is_job_search_query($query)
    {
        $job_keywords = [
            // 🇬🇧 English — Core
            'job',
            'jobs',
            'position',
            'positions',
            'role',
            'roles',
            'opportunity',
            'opportunities',
            'opening',
            'openings',
            'vacancy',
            'vacancies',
            'career',
            'careers',
            'employment',
            'work',
            'hiring',
            'recruiting',
            'recruitment',
            'now hiring',
            'we are hiring',
            'join our team',
            'job offer',
            'career opportunity',
            'career opportunities',
            'salary',
            'compensation',
            'package',
            'benefits',
            'earnings',
            'pay',
            'remuneration',

            // 🇮🇹 Italian
            'lavoro',              // job / work
            'posizione',           // position
            'posizioni',           // positions
            'ruolo',               // role
            'ruoli',               // roles
            'opportunità',         // opportunity
            'opportunita',         // opportunity (no accent)
            'assunzioni',          // hiring
            'assunzione',          // hiring singular
            'carriera',           // career
            'lavora con noi',     // work with us
            'offerta di lavoro',  // job offer
            'stipendio',         // salary
            'retribuzione',      // compensation
            'benefit',           // benefits (often same word)
            'ricerca personale', // recruiting
            'selezione',         // selection / recruitment

            // 🇫🇷 French
            'emploi',             // job
            'emplois',            // jobs
            'poste',              // position
            'postes',             // positions
            'rôle',               // role
            'role',               // role (no accent)
            'opportunité',        // opportunity
            'opportunites',       // opportunities (no accent)
            'carrière',           // career
            'carriere',           // career (no accent)
            'recrutement',        // recruitment
            'en recrutement',     // hiring
            'offre d\'emploi',    // job offer
            'offres d\'emploi',   // job offers
            'travail',            // work
            'salaire',            // salary
            'rémunération',       // compensation
            'remuneration',       // compensation (no accent)
            'avantages',          // benefits
            'nous recrutons',     // we are hiring
            'rejoignez notre équipe', // join our team

            // 🇪🇸 Spanish
            'trabajo',            // job
            'trabajos',           // jobs
            'puesto',             // position
            'puestos',            // positions
            'rol',                // role
            'oportunidad',        // opportunity
            'oportunidades',      // opportunities
            'empleo',             // job
            'empleos',            // jobs
            'carrera',            // career
            'reclutamiento',      // recruitment
            'estamos contratando', // we are hiring
            'oferta de trabajo',  // job offer
            'ofertas de trabajo', // job offers
            'salario',            // salary
            'compensación',       // compensation
            'compensacion',       // compensation (no accent)
            'beneficios',         // benefits
            'únete a nuestro equipo', // join our team
            'contratación',       // hiring
            'contratacion',       // hiring (no accent)

            // 🇩🇪 German
            'stelle',            // position / job
            'stellen',           // jobs / positions
            'jobangebot',        // job offer
            'jobangebote',       // job offers
            'position',          // position
            'positionen',        // positions
            'rolle',             // role
            'rollen',            // roles
            'karriere',          // career
            'arbeit',            // work
            'wir stellen ein',   // we are hiring
            'wir suchen',        // we're looking for
            'vakanz',            // vacancy
            'gehalt',            // salary
            'vergütung',         // compensation
            'verguetung',        // compensation (no umlaut)
            'leistungen',        // benefits
            'bewerbung',         // application

            // 🇧🇷 Portuguese (Brazil)
            'emprego',           // job
            'empregos',          // jobs
            'vaga',              // vacancy / position
            'vagas',             // vacancies
            'posição',           // position
            'posicao',           // position (no accent)
            'cargo',             // role / position
            'oportunidade',      // opportunity
            'oportunidades',     // opportunities
            'carreira',          // career
            'recrutamento',      // recruitment
            'contratação',       // hiring
            'contratacao',       // hiring (no accent)
            'estamos contratando', // we are hiring
            'oferta de emprego', // job offer
            'ofertas de emprego', // job offers
            'salário',           // salary
            'salario',           // salary (no accent)
            'remuneração',       // compensation
            'remuneracao',       // compensation (no accent)
            'benefícios',        // benefits
            'beneficios',        // benefits (no accent)
            'trabalho',          // work

            // 🌍 Other useful English job terms
            'graduate scheme',
            'internship',
            'internships',
            'trainee',
            'traineeship',
            'entry level',
            'junior role',
            'senior role',
            'lead position',
            'vacant role',
            'new opening',
            'hiring now',
            'now hiring',
            'join us',
            'apply now',
            'job vacancy',
            'team opening',
            'staff wanted',
            'open position',
            'career page'
        ];

        $lower_query = strtolower($query);

        foreach ($job_keywords as $keyword) {
            if (strpos($lower_query, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract job filters from natural language query
     */
    private function extract_job_filters($query)
    {
        $filters = [];
        $lower_query = strtolower($query);

        // Location extraction
        if (preg_match('/in ([\w\s]+)/', $lower_query, $matches)) {
            $filters['location'] = trim($matches[1]);
        }

        // Salary extraction
        if (preg_match('/(\d+)k/', $lower_query, $matches)) {
            $filters['salary_min'] = intval($matches[1]) * 1000;
        }

        // Job type extraction
        // 🟦 Private Equity
        if (
            // 📝 Core English terms
            strpos($lower_query, 'private equity') !== false ||
            strpos($lower_query, ' pe ') !== false ||
            strpos($lower_query, 'p.e.') !== false ||
            strpos($lower_query, 'p.e') !== false ||
            strpos($lower_query, 'p/e') !== false ||
            strpos($lower_query, 'buyout') !== false ||
            strpos($lower_query, 'leveraged buyout') !== false ||
            strpos($lower_query, 'lbo') !== false ||
            strpos($lower_query, 'growth equity') !== false ||
            strpos($lower_query, 'growth fund') !== false ||
            strpos($lower_query, 'buyout fund') !== false ||
            strpos($lower_query, 'buyout firm') !== false ||
            strpos($lower_query, 'growth investor') !== false ||
            strpos($lower_query, 'pe firm') !== false ||
            strpos($lower_query, 'pe fund') !== false ||
            strpos($lower_query, 'private markets') !== false ||
            strpos($lower_query, 'unlisted investments') !== false ||
            strpos($lower_query, 'alternative investments') !== false ||
            strpos($lower_query, 'direct investing') !== false ||
            strpos($lower_query, 'direct investment') !== false ||
            strpos($lower_query, 'gp stakes') !== false ||
            strpos($lower_query, 'secondaries') !== false ||
            strpos($lower_query, 'secondary fund') !== false ||
            strpos($lower_query, 'co-invest') !== false ||
            strpos($lower_query, 'co-investment') !== false ||
            strpos($lower_query, 'co investment') !== false ||
            strpos($lower_query, 'coinvest') !== false ||
            strpos($lower_query, 'placement agent') !== false ||
            strpos($lower_query, 'fund placement') !== false ||
            strpos($lower_query, 'fund investing') !== false ||
            strpos($lower_query, 'fund investor') !== false ||

            // 🇮🇹 Italian
            strpos($lower_query, 'capitale privato') !== false ||
            strpos($lower_query, 'investimenti privati') !== false ||
            strpos($lower_query, 'fondi di private equity') !== false ||

            // 🇪🇸 Spanish / 🇵🇹 Portuguese
            strpos($lower_query, 'capital privado') !== false ||
            strpos($lower_query, 'inversiones privadas') !== false ||
            strpos($lower_query, 'fondos de private equity') !== false ||
            strpos($lower_query, 'fondos de capital privado') !== false ||
            strpos($lower_query, 'mercados privados') !== false ||
            strpos($lower_query, 'inversion directa') !== false ||
            strpos($lower_query, 'co-inversion') !== false ||

            // 🇫🇷 French
            strpos($lower_query, 'capital-investissement') !== false ||
            strpos($lower_query, 'fonds de private equity') !== false ||
            strpos($lower_query, 'fonds d’investissement privé') !== false ||
            strpos($lower_query, 'marchés privés') !== false ||
            strpos($lower_query, 'co-investissement') !== false ||

            // 🇩🇪 German
            strpos($lower_query, 'beteiligungskapital') !== false ||
            strpos($lower_query, 'private märkte') !== false ||
            strpos($lower_query, 'co-investition') !== false ||
            strpos($lower_query, 'co investment') !== false ||
            strpos($lower_query, 'private equity fonds') !== false ||
            strpos($lower_query, 'beteiligungsfonds') !== false ||

            // 🇧🇷 Portuguese (Brazil)
            strpos($lower_query, 'mercados privados') !== false ||
            strpos($lower_query, 'investimento privado') !== false ||
            strpos($lower_query, 'fundos de private equity') !== false ||

            // 🌍 Common hybrid / phrasing
            strpos($lower_query, 'pe investing') !== false ||
            strpos($lower_query, 'pe investments') !== false ||
            strpos($lower_query, 'private equity investing') !== false ||
            strpos($lower_query, 'private equity role') !== false ||
            strpos($lower_query, 'private equity jobs') !== false
        ) {
            $filters['type'] = 'private_equity';
        }


        // 🟨 Investment Banking
        elseif (
            strpos($lower_query, 'investment banking') !== false ||
            strpos($lower_query, ' ib ') !== false ||
            strpos($lower_query, 'i.b.') !== false ||
            strpos($lower_query, 'm&a') !== false ||
            strpos($lower_query, 'mergers and acquisitions') !== false ||
            strpos($lower_query, 'equity capital markets') !== false ||
            strpos($lower_query, 'debt capital markets') !== false ||
            strpos($lower_query, 'levfin') !== false ||
            strpos($lower_query, 'corporate finance') !== false ||
            strpos($lower_query, 'coverage') !== false ||
            strpos($lower_query, 'capital markets') !== false ||
            // 🇮🇹 🇪🇸 🇫🇷 🇩🇪 🇧🇷 translations
            strpos($lower_query, 'banca d\'investimento') !== false ||  // IT
            strpos($lower_query, 'banca de inversión') !== false ||     // ES
            strpos($lower_query, 'banque d\'investissement') !== false || // FR
            strpos($lower_query, 'investmentbanking') !== false ||      // DE
            strpos($lower_query, 'banco de investimento') !== false     // PT
        ) {
            $filters['type'] = 'investment_banking';
        }

        // 🟥 Hedge Funds
        elseif (
            strpos($lower_query, 'hedge fund') !== false ||
            strpos($lower_query, ' hf ') !== false ||
            strpos($lower_query, 'h.f.') !== false ||
            strpos($lower_query, 'multi-strategy') !== false ||
            strpos($lower_query, 'long/short') !== false ||
            strpos($lower_query, 'event driven') !== false ||
            strpos($lower_query, 'global macro') !== false ||
            strpos($lower_query, 'quant fund') !== false ||
            strpos($lower_query, 'systematic fund') !== false ||
            // 🇮🇹 🇪🇸 🇫🇷 🇩🇪 🇧🇷 translations
            strpos($lower_query, 'fondo hedge') !== false ||            // IT/ES
            strpos($lower_query, 'fonds spéculatif') !== false ||       // FR
            strpos($lower_query, 'hedgefonds') !== false ||             // DE
            strpos($lower_query, 'fundo hedge') !== false               // PT
        ) {
            $filters['type'] = 'hedge_fund';
        }

        // 🟩 Asset Management
        elseif (
            strpos($lower_query, 'asset management') !== false ||
            strpos($lower_query, 'am ') !== false ||
            strpos($lower_query, 'fund management') !== false ||
            strpos($lower_query, 'investment management') !== false ||
            strpos($lower_query, 'portfolio management') !== false ||
            strpos($lower_query, 'wealth management') !== false ||
            strpos($lower_query, 'multi-asset') !== false ||
            strpos($lower_query, 'active management') !== false ||
            strpos($lower_query, 'passive management') !== false ||
            // 🇮🇹 🇪🇸 🇫🇷 🇩🇪 🇧🇷 translations
            strpos($lower_query, 'gestione patrimoniale') !== false ||      // IT
            strpos($lower_query, 'gestión de activos') !== false ||         // ES
            strpos($lower_query, 'gestion d\'actifs') !== false ||          // FR
            strpos($lower_query, 'vermögensverwaltung') !== false ||        // DE
            strpos($lower_query, 'gestão de ativos') !== false              // PT
        ) {
            $filters['type'] = 'asset_management';
        }

        // 🟧 Venture Capital
        elseif (
            strpos($lower_query, 'venture capital') !== false ||
            strpos($lower_query, ' vc ') !== false ||
            strpos($lower_query, 'v.c.') !== false ||
            strpos($lower_query, 'early stage') !== false ||
            strpos($lower_query, 'seed investing') !== false ||
            strpos($lower_query, 'series a') !== false ||
            // 🇮🇹 🇪🇸 🇫🇷 🇩🇪 🇧🇷 translations
            strpos($lower_query, 'capital de riesgo') !== false ||         // ES
            strpos($lower_query, 'capital-risque') !== false ||            // FR
            strpos($lower_query, 'venture capital italiano') !== false ||  // IT
            strpos($lower_query, 'risikokapital') !== false ||            // DE
            strpos($lower_query, 'capital de risco') !== false            // PT
        ) {
            $filters['type'] = 'venture_capital';
        }

        // 🟦 Private Credit / Direct Lending
        elseif (
            strpos($lower_query, 'private credit') !== false ||
            strpos($lower_query, 'direct lending') !== false ||
            strpos($lower_query, 'private debt') !== false ||
            strpos($lower_query, 'mezzanine') !== false ||
            strpos($lower_query, 'distressed') !== false ||
            strpos($lower_query, 'special situations') !== false ||
            // 🇮🇹 🇪🇸 🇫🇷 🇩🇪 🇧🇷 translations
            strpos($lower_query, 'credito privato') !== false ||        // IT
            strpos($lower_query, 'crédito privado') !== false ||       // ES/PT
            strpos($lower_query, 'dette privée') !== false ||          // FR
            strpos($lower_query, 'privatkredit') !== false             // DE
        ) {
            $filters['type'] = 'private_credit';
        }

        // 🟨 Consulting
        elseif (
            strpos($lower_query, 'consulting') !== false ||
            strpos($lower_query, 'strategy consulting') !== false ||
            strpos($lower_query, 'management consulting') !== false ||
            strpos($lower_query, 'transaction advisory') !== false ||
            strpos($lower_query, 'deal advisory') !== false ||
            // 🇮🇹 🇪🇸 🇫🇷 🇩🇪 🇧🇷 translations
            strpos($lower_query, 'consulenza') !== false ||           // IT
            strpos($lower_query, 'consultoría') !== false ||         // ES
            strpos($lower_query, 'conseil') !== false ||             // FR
            strpos($lower_query, 'beratung') !== false ||            // DE
            strpos($lower_query, 'consultoria') !== false            // PT
        ) {
            $filters['type'] = 'consulting';
        }

        // 🟫 Technology
        elseif (
            strpos($lower_query, 'technology') !== false ||
            strpos($lower_query, 'tech') !== false ||
            strpos($lower_query, 'software') !== false ||
            strpos($lower_query, 'developer') !== false ||
            strpos($lower_query, 'ai') !== false ||
            strpos($lower_query, 'machine learning') !== false ||
            strpos($lower_query, 'data science') !== false ||
            strpos($lower_query, 'fintech') !== false ||
            strpos($lower_query, 'digital') !== false ||
            // 🇮🇹 🇪🇸 🇫🇷 🇩🇪 🇧🇷 translations
            strpos($lower_query, 'tecnologia') !== false ||       // IT/ES/PT
            strpos($lower_query, 'technologie') !== false ||     // FR/DE
            strpos($lower_query, 'informatique') !== false       // FR
        ) {
            $filters['type'] = 'technology';
        }


        // Remote work
        if (strpos($lower_query, 'remote') !== false) {
            $filters['remote'] = true;
        }

        return $filters;
    }

    /**
     * Get filtered jobs based on extracted criteria
     */
    private function get_filtered_jobs($filters)
    {
        $jobs = $this->get_opportunities_data(0, 10);

        if (empty($filters)) {
            return $jobs;
        }

        return array_filter($jobs, function ($job) use ($filters) {
            // Location filter
            if (isset($filters['location']) && stripos($job['location'], $filters['location']) === false) {
                return false;
            }

            // Salary filter
            if (isset($filters['salary_min']) && $job['salary_min'] < $filters['salary_min']) {
                return false;
            }

            // Type filter
            if (isset($filters['type'])) {
                $job_lower = strtolower($job['title'] . ' ' . $job['company']);
                switch ($filters['type']) {
                    case 'private_equity':
                        if (stripos($job_lower, 'private equity') === false && stripos($job_lower, 'pe ') === false) {
                            return false;
                        }
                        break;
                    case 'investment_banking':
                        if (stripos($job_lower, 'investment bank') === false && stripos($job_lower, 'analyst') === false) {
                            return false;
                        }
                        break;
                    case 'hedge_fund':
                        if (stripos($job_lower, 'hedge') === false && stripos($job_lower, 'fund') === false) {
                            return false;
                        }
                        break;
                }
            }

            // Remote filter
            if (isset($filters['remote']) && stripos($job['location'], 'remote') === false) {
                return false;
            }

            return true;
        });
    }

    /**
     * Process fallback chat when Claude is unavailable
     */
    private function process_fallback_chat($query, $context)
    {
        $lower_query = strtolower($query);
        $style_note = '';
        if (preg_match('/\b(simple|beginner|confused|lost|explain like)\b/', $lower_query)) {
            $style_note = '<p><strong>Plain-English lens:</strong> understand the direction first; the formulas will feel easier after that.</p>';
        } elseif (preg_match('/\b(formula|calculate|math|number|model|excel)\b/', $lower_query)) {
            $style_note = '<p><strong>Model lens:</strong> write the formula, plug in numbers, then sanity-check the answer.</p>';
        } elseif (preg_match('/\b(why|intuition|concept|conceptual)\b/', $lower_query)) {
            $style_note = '<p><strong>Intuition:</strong> PE returns improve when the company grows, debt falls, or the exit valuation is stronger.</p>';
        }

        if (preg_match('/\b(job|jobs|role|roles|opening|openings|opportunit|hiring|recruit|application|apply|cv|resume|salary|compensation|interview)\b/', $lower_query)) {
            $response = '<p>This is a learning room, so I will not switch into jobs, applications, CVs, salary, or recruiting.</p>'
                . '<h3>Turn It Into a Finance Skill</h3>'
                . '<p>The underlying skill depends on the track: IB valuation, AM portfolio analysis, or PE deal returns.</p>'
                . $style_note
                . '<h4>Worked Example</h4>'
                . '<ul><li>IB: EBITDA x EV/EBITDA multiple = enterprise value</li><li>AM: portfolio return - benchmark return = active return</li><li>PE: exit equity value / sponsor equity = MOIC</li></ul>'
                . '<h4>Your Turn</h4>'
                . '<p>Which track should we use for the next example: investment banking, asset management, or private equity?</p>';
        } elseif (
            strpos($lower_query, 'asset management') !== false ||
            strpos($lower_query, 'portfolio') !== false ||
            strpos($lower_query, 'benchmark') !== false ||
            strpos($lower_query, 'duration') !== false ||
            strpos($lower_query, 'fixed income') !== false ||
            strpos($lower_query, 'bond') !== false ||
            strpos($lower_query, 'attribution') !== false
        ) {
            $response = '<p>Good, let us switch to the asset management track.</p>'
                . '<h3>Active Return</h3>'
                . '<p>Asset managers need to explain performance relative to a benchmark, not just absolute return.</p>'
                . $style_note
                . '<h4>Worked Example</h4>'
                . '<ul><li>Portfolio return: 8.5%</li><li>Benchmark return: 6.0%</li><li>Active return: 8.5% - 6.0% = 2.5%</li></ul>'
                . '<h4>Your Turn</h4>'
                . '<p>If a portfolio returns 7.2% and the benchmark returns 5.8%, what is active return?</p>';
        } elseif (
            strpos($lower_query, 'investment banking') !== false ||
            preg_match('/\bib\b/', $lower_query) ||
            strpos($lower_query, 'dcf') !== false ||
            strpos($lower_query, 'comps') !== false ||
            strpos($lower_query, 'm&a') !== false ||
            strpos($lower_query, 'accretion') !== false ||
            strpos($lower_query, 'dilution') !== false
        ) {
            $response = '<p>Good, let us switch to the investment banking track.</p>'
                . '<h3>Enterprise Value from Comps</h3>'
                . '<p>Bankers often use market multiples to convert company performance into valuation.</p>'
                . $style_note
                . '<h4>Worked Example</h4>'
                . '<ul><li>Company EBITDA: GBP 25m</li><li>Selected EV/EBITDA multiple: 9.0x</li><li>Enterprise value: GBP 25m x 9.0x = GBP 225m</li></ul>'
                . '<h4>Your Turn</h4>'
                . '<p>If EBITDA is GBP 40m and the selected multiple is 7.5x, what is enterprise value?</p>';
        } elseif (strpos($lower_query, 'debt') !== false) {
            $response = '<p>Good, we are in the financing section of the lesson.</p>'
                . '<h3>Debt Schedule</h3>'
                . '<p>Debt repayment is central because lower debt at exit leaves more equity value for the sponsor.</p>'
                . $style_note
                . '<h4>Worked Example</h4>'
                . '<ul><li>Opening debt: GBP 100m</li><li>Mandatory amortization: GBP 5m</li><li>Cash sweep: GBP 12m</li><li>Ending debt: GBP 83m</li></ul>'
                . '<h4>Your Turn</h4>'
                . '<p>Opening debt is GBP 120m, amortization is GBP 6m, and cash sweep is GBP 14m. What is ending debt?</p>';
        } elseif (strpos($lower_query, 'irr') !== false || strpos($lower_query, 'moic') !== false || strpos($lower_query, 'return') !== false) {
            $response = '<p>Now we are measuring the sponsor outcome.</p>'
                . '<h3>MOIC and IRR</h3>'
                . '<p>MOIC measures total money multiple. IRR measures annualized speed of return.</p>'
                . $style_note
                . '<h4>Worked Example</h4>'
                . '<ul><li>Sponsor equity invested: GBP 80m</li><li>Exit equity value: GBP 200m</li><li>MOIC: GBP 200m / GBP 80m = 2.5x</li></ul>'
                . '<h4>Your Turn</h4>'
                . '<p>If sponsor equity is GBP 100m and exit equity value is GBP 230m, what is MOIC?</p>';
        } else {
            $response = '<p>Let us continue from the foundation and build one concept at a time.</p>'
                . '<h3>Finance Technical Fundamentals</h3>'
                . '<p>We can learn across three tracks: investment banking valuation and transactions, asset management portfolio analysis, or private equity deal returns.</p>'
                . $style_note
                . '<h4>Worked Example</h4>'
                . '<ul><li>IB: EBITDA x multiple = enterprise value</li><li>AM: portfolio return - benchmark return = active return</li><li>PE: exit equity value / sponsor equity = MOIC</li></ul>'
                . '<h4>Your Turn</h4>'
                . '<p>Which track should we start with: investment banking, asset management, or private equity?</p>';
        }

        wp_send_json_success(['message' => $response]);
    }

    /**
     * AJAX: Analyze job with Claude API
     */
    public function ajax_analyze_job()
    {
        // Get job data from request
        $job_data_json = isset($_POST['job_data']) ? stripslashes($_POST['job_data']) : '';
        $job_data = json_decode($job_data_json, true);

        if (empty($job_data)) {
            wp_send_json_error(['message' => 'Invalid job data']);
            return;
        }

        // Check if Claude API manager is available
        if (class_exists('SFFC_Claude_API_Manager')) {
            $claude = SFFC_Claude_API_Manager::get_instance();

            // Prepare the prompt for Claude
            $prompt = $this->prepare_job_analysis_prompt($job_data);

            // Get analysis from Claude
            $result = $claude->send_message($prompt, [], 'opportunities');

            if ($result['success']) {
                // Parse Claude's response into structured format
                $analysis = $this->parse_claude_response($result['response'], $job_data);
                wp_send_json_success($analysis);
                return;
            }
        }

        // Fallback to template-based analysis
        $analysis = $this->generate_job_analysis($job_data);
        wp_send_json_success($analysis);
    }

    /**
     * Prepare prompt for Claude job analysis
     */
    private function prepare_job_analysis_prompt($job_data)
    {
        $prompt = "You are MENA Careers, a Private Equity tutor mentoring a student on real private equity mandates. ";
        $prompt .= "Use the job below as a teaching moment—explain what the team actually does, which technical muscles matter, and how the student should think like a PE investor. Provide a structured response with **exact section headers** matching this format:\n\n";
        $prompt .= "The Role:\n[Short descriptive paragraph with private equity context]\n\n";
        $prompt .= "What They're Looking For:\n[Bullet point list of key requirements, skills, and qualifications]\n\n";
        $prompt .= "Culture & Team:\n[Short descriptive paragraph about team style and operating expectations]\n\n";
        $prompt .= "Application Strategy:\n[Short paragraph with actionable advice tailored for this mandate]\n\n";

        $prompt .= "================ JOB INFORMATION ================\n";
        $prompt .= "Role: {$job_data['title']} at {$job_data['company']}\n";
        $prompt .= "Location: {$job_data['location']}\n";

        if (!empty($job_data['description'])) {
            $prompt .= "Description: {$job_data['description']}\n";
        }

        if (!empty($job_data['requirements'])) {
            $req = is_array($job_data['requirements']) ? implode(', ', $job_data['requirements']) : $job_data['requirements'];
            $prompt .= "Requirements: {$req}\n";
        }

        $prompt .= "================================================\n\n";

        $prompt .= "✅ Important instructions:\n";
        $prompt .= "- Use **the exact headers**: 'The Role', 'What They're Looking For', 'Culture & Team', 'Application Strategy'.\n";
        $prompt .= "- For 'What They're Looking For', use bullet points (• or -), one requirement per line.\n";
        $prompt .= "- If the job description is not in English, translate it first and then analyze in English.\n";
        $prompt .= "- Be concise, structured, and insightful. Avoid generic filler.\n";
        $prompt .= "- Focus on actionable insights relevant to private equity, buy-side recruiting, fund strategy, deal exposure, portfolio work, and recruiter expectations.\n";
        $prompt .= "- Mention relevant private equity context: fund strategy, transaction exposure, modelling expectations, portfolio company exposure, and recruiter route if applicable.\n";
        $prompt .= "- Keep the tone instructional, as if you are coaching a student through the nuances of this mandate.\n";

        return $prompt;
    }

    /**
     * Parse Claude's response into structured format
     */
    private function parse_claude_response($response, $job_data)
    {
        // Try to extract sections from Claude's response
        $analysis = [
            'role_overview' => '',
            'requirements' => [],
            'culture_team' => '',
            'application_strategy' => ''
        ];

        // Look for section markers in response
        if (strpos($response, 'The Role') !== false) {
            preg_match('/The Role[:\-\s]*(.*?)(?=What They|Requirements|Culture|Application|$)/si', $response, $matches);
            $analysis['role_overview'] = isset($matches[1]) ? trim($matches[1]) : '';
        }

        if (preg_match('/(what[\s\']*they.*looking for|requirements)/i', $response)) {
            preg_match('/(what[\s\']*they.*looking for|requirements)[:\-\s]*(.*?)(?=\n[A-Z]|Culture|Application|$)/is', $response, $matches);
            if (!empty($matches[2])) {
                $req_text = trim($matches[2]);
                // Split on line breaks or bullet characters, but keep things clean
                $requirements = preg_split('/(?:\r\n|\r|\n)|(?:•|\-|\*)\s*/', $req_text);
                $requirements = array_filter(array_map('trim', $requirements));
                $analysis['requirements'] = $requirements;
            }
        }

        if (strpos($response, 'Culture') !== false) {
            preg_match('/Culture[^:]*[:\-\s]*(.*?)(?=Application|$)/si', $response, $matches);
            $analysis['culture_team'] = isset($matches[1]) ? trim($matches[1]) : '';
        }

        if (strpos($response, 'Application') !== false) {
            preg_match('/Application[^:]*[:\-\s]*(.*?)$/si', $response, $matches);
            $analysis['application_strategy'] = isset($matches[1]) ? trim($matches[1]) : '';
        }

        // Fill in any missing sections with private-equity-focused defaults
        if (empty($analysis['role_overview'])) {
            $analysis['role_overview'] = "Join {$job_data['company']} as a {$job_data['title']} in {$job_data['location']}. This role offers an opportunity to build private equity-relevant experience through analytical work, transaction context, portfolio exposure, or direct buy-side execution.";
        }

        if (empty($analysis['requirements'])) {
            $analysis['requirements'] = [
                'Relevant experience in finance or related field',
                'Strong analytical and financial modeling skills',
                'Excellent communication abilities',
                'Clear interest in transactions, portfolio work, or investing'
            ];
        }

        if (empty($analysis['culture_team'])) {
            $analysis['culture_team'] = "{$job_data['company']} offers a demanding, analytical work environment where clear thinking, concise communication, and commercial judgment matter.";
        }

        if (empty($analysis['application_strategy'])) {
            $analysis['application_strategy'] = "Highlight any private equity, transaction, portfolio, or investing experience. Emphasize evidence of judgment, modeling, diligence, and the ability to speak clearly about what drives value.";
        }

        return $analysis;
    }

    /**
     * Generate template-based job analysis - private equity focused
     */
    private function generate_job_analysis($job_data)
    {
        $analysis = [
            'role_overview' => '',
            'requirements' => [],
            'culture_team' => '',
            'application_strategy' => ''
        ];

        // Role overview
        $analysis['role_overview'] = "You'll join {$job_data['company']} as a {$job_data['title']} in {$job_data['location']}. ";
        $analysis['role_overview'] .= "This is an opportunity to build private-equity-relevant experience through analytical work, transaction context, portfolio exposure, or direct buy-side execution. ";
        if (!empty($job_data['description'])) {
            $sentences = preg_split('/[.!?]/', $job_data['description']);
            if (!empty($sentences[0])) {
                $analysis['role_overview'] .= trim($sentences[0]) . '.';
            }
        }

        // Requirements
        if (!empty($job_data['skills']) && is_array($job_data['skills'])) {
            foreach ($job_data['skills'] as $skill) {
                $analysis['requirements'][] = "Strong {$skill} expertise";
            }
        }
        if (!empty($job_data['experience_level'])) {
            $analysis['requirements'][] = "{$job_data['experience_level']} level experience required";
        }
        if (count($analysis['requirements']) < 3) {
            $analysis['requirements'][] = "Proven track record in finance or related field";
            $analysis['requirements'][] = "Strong analytical and problem-solving abilities";
        }
        $analysis['requirements'][] = "Ability to discuss deals, business quality, or portfolio issues with clarity";

        // Culture & Team
        $culture_keywords = ['dynamic', 'internationally diverse', 'fast-paced', 'growth-oriented'];
        $culture = $culture_keywords[array_rand($culture_keywords)];
        $analysis['culture_team'] = "{$job_data['company']} offers a {$culture} work environment. ";
        if (!empty($job_data['job_type'])) {
            $analysis['culture_team'] .= "The role provides {$job_data['job_type']} arrangements. ";
        }
        $analysis['culture_team'] .= "You'll collaborate with industry experts and contribute to high-stakes investing or portfolio work.";

        // Application strategy - ME focused
        $analysis['application_strategy'] = "To stand out, emphasize your ";
        if (!empty($job_data['skills'][0])) {
            $analysis['application_strategy'] .= "{$job_data['skills'][0]} experience and ";
        }
        $analysis['application_strategy'] .= "quantifiable achievements. ";
        $analysis['application_strategy'] .= "Research {$job_data['company']} recent deals, portfolio activity, and mandate focus, then explain why your background is relevant. ";
        $analysis['application_strategy'] .= "The interview process typically rewards specificity, commercial judgment, and strong technical communication.";

        return $analysis;
    }

    /**
     * Handle input field session continuation
     */
    public function ajax_get_input_session()
    {
        // Verify nonce
        if (!check_ajax_referer('sffc_public_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed']);
            return;
        }

        $session_id = sanitize_text_field($_POST['session_id'] ?? '');

        if (empty($session_id)) {
            wp_send_json_error(['message' => 'No session ID provided']);
            return;
        }

        // Get session data using the input field class method
        if (class_exists('SFFC_Senna_Input_Field')) {
            $session_data = SFFC_Senna_Input_Field::get_session_data($session_id);

            if ($session_data) {
                // Process the enhanced query as if it was a regular chat query
                $context = $session_data['context'] ?? [];
                $enhanced_query = $session_data['enhanced_query'] ?? $session_data['original_query'];

                $response = [
                    'session_found' => true,
                    'original_query' => $session_data['original_query'],
                    'enhanced_query' => $enhanced_query,
                    'context' => $context,
                    'auto_send' => true
                ];

                // Clear the session data after use
                SFFC_Senna_Input_Field::clear_session_data($session_id);

                wp_send_json_success($response);
            } else {
                wp_send_json_error(['message' => 'Session not found or expired']);
            }
        } else {
            wp_send_json_error(['message' => 'Input field handler not available']);
        }
    }
}

// Initialize
SFFC_Career_Opportunities_Simple::get_instance();
