<?php

/**
 * Plugin Name: MENA Careers
 * Plugin URI: https://joinsenna.com/careers
 * Description: AI-powered career intelligence platform featuring MENA Careers, your intelligent career advisor. Comprehensive job board, application audit, CV tailoring, and strategic career tools.
 * Version: 11.14.1
 * Author: MENA Careers
 * Author URI: https://joinsenna.com
 * Text Domain: senna-careers
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package SennaCareers
 * @since 11.9.0 - GAP ANALYZER & CLAUDE API OPTIMIZATION: Added comprehensive CV vs JD gap analyzer shortcode [sffc_gap_analyzer] with Claude Sonnet 4 integration for detailed requirements analysis, skills breakdown, experience matching, red flags, strengths, CV improvements, cover letter points, and interview prep. Disabled Claude API for non-essential shortcodes (sffc_editorial_article institutional, sffc_pe_news, sffc_pe_signal, sffc_pedeal) to reduce API costs - these now use template fallbacks.
 * @since 10.25.0 - PE NEWS SOCIAL DASHBOARD: Introduced a three-panel newsroom experience that merges sffc_pe_news and sffc_pe_deal content with Claude-powered analytics. Includes notification bells, alert stream, real-time filters, and a white-label social feed aesthetic without emojis or dark themes, fully compatible with WordPress shortcodes.
 * @since 10.24.0 - PREMIUM ARTICLE SEARCH INTEGRATION: Enhanced premium article shortcode with compact search functionality, Join MENA Careers button, and SEO-optimized structure. Added minimal-space search bar with mode switching (Jobs/Insights), responsive design, and seamless redirect to sffc_pe_search_results. Includes comprehensive structured data, Open Graph tags, and article-first design that maintains Google ranking priority while providing user engagement features.
 * @since 10.23.0 - GOOGLE-STYLE PE SEARCH ENGINE: Complete Phase 2 implementation featuring professional Google-replica search interface with premium grey underlines, favicon/logo system with letter fallbacks, smart keyword highlighting, and revolutionary PE-specific insights that surpass Google. Added three-tier system: search interface (class-pe-search-interface.php), results page (class-pe-search-results.php), and enhanced styling (pe-search-results.css). Includes unique features like salary insights, experience matching, quick actions, and company analysis - the game-changing functionality that Google lacks.
 * @since 10.22.0 - ENHANCED XML FEED SYSTEM: Major upgrade to XML feed fetcher supporting both sitemaps (Piper Maddox) and website job listing pages (Focus Selection). Added intelligent job URL detection, enhanced HTML extraction with JSON-LD support, comprehensive salary/location parsing, and new "Website Page" feed type option. System now handles 28+ job sources per page with robust fallback extraction methods.
 * @since 10.21.0 - JOB CONTENT MANAGER EXTENSION: Added automated job retrieval system following reliable PE content pattern. Leverages existing sophisticated parsers (XML, Workday) with hourly cron scheduling, quality source focus (BlackRock RSS, Finatal sitemap), duplicate prevention, and seamless integration with sffc_job post type. Extension preserves complex parsing logic while ensuring reliable automation.
 * @since 10.20.0 - DYNAMIC POST TYPE FILTERING SYSTEM: Added real data filtering system that creates intelligence cards from any custom post type (like M42's job system). Includes admin interface for mapping post fields to card elements, AJAX integration with ZENA's PE filters, and seamless display of real WordPress post data
 * @since 10.19.0 - LIVE CHAT SYSTEM OVERHAUL: Fixed "Could not insert post into the database" error, enhanced live expert chat with WhatsApp-style UI, auto-resize textarea, Enter-to-send, real-time updates, connection status indicators, and robust post type registration with emergency fallbacks
 * @since 10.18.0 - FEED SYSTEM ENHANCEMENT: Added Greenhouse API support with GIC Singapore, KKR, Point72 integrations, company/recruiter source classification, and expanded feed configuration management
 * @since 10.15.0 - MAJOR EXPANSION: Added 13 verified Workday endpoints (854+ jobs) including Brookfield, BlackRock Professional, Oaktree, Hamilton Lane, Dimensional, IMF, and premium booking form with MENA Careers avatar + URL redirect system
 * @since 10.14.0 - POWERFUL CV System: Ultimate parser handles ALL CV formats (Ropa, Zac, Maria, William), POWERFUL tailoring engine guarantees 85-100% match scores, aggressive keyword injection
 * @since 10.13.0 - CV Intelligence Engine V2: Achieved 100% CV parsing accuracy with smart pattern detection, comprehensive section extraction, and bulletproof WordPress integration
 * @since 10.12.0 - MAJOR UPDATE: Repositioned MENA Careers as comprehensive AI career strategist with 4 value props, simplified CV upload, strategic action buttons, and fixed CV upload security/nonce issues
 * @since 10.11.1 - ENHANCED: Added 4-layer fallback system with verification to guarantee action cards work 100% of the time
 * @since 10.11.0 - CRITICAL FIX: Action cards now route directly to SennaChat.send(), bypassing job filtering logic for proper AI responses
 * @since 10.10.2 - Added tailor-cv-fix.js to properly handle tailor-cv-main-btn clicks and integrate with action cards system
 * @since 10.10.1 - Fixed action card prompts to use proper MENA Careers handleUserInput() method for correct message handling
 * @since 10.10.0 - Expanded action cards system to 100 cards with advanced PE-specific prompts across all categories
 * @since 10.9.0 - Enhanced smart features: improved context detection, loading states, tooltips, and mobile responsiveness
 * @since 10.8.5 - Fixed action card prompts not being processed by adding processUserIntent call after addUserMessage
 * @since 10.8.4 - Fixed container not found error with better initialization logic and container creation fallbacks
 * @since 10.8.3 - Fixed competing filter systems by disabling pe-filter-cards-initializer and ensuring proper load order
 * @since 10.8.2 - Added Prompt Library as default active filter, ensuring action cards take priority and proper design alignment
 * @since 10.8.1 - Fixed Action Cards System initialization to properly take over pe-filter-sidebar and display cards
 * @since 10.8.0 - Added Action Cards System with 70 AI action triggers in PE filter sidebar for contextualized prompts and smart filtering
 * @since 10.7.2 - Fixed support popup email submission to use WordPress mail settings with admin email
 * @since 10.7.1 - Fixed Tailor CV button to create visual cv-tailoring-container interface like CREW implementation
 * @since 10.7.0 - Added Get Advice button replacing Save, fixed job cards right-shift issue with pro tip, improved support popup z-index and button styling
 * @since 10.6.0 - Added Apply Now buttons below job tags in job cards with ask-senna-btn styling, increased job fetch timeouts
 * @since 10.5.0 - Fixed intelligent search to use cached jobs, improved location filtering, CV matcher uses all jobs, fixed follow-up question visibility with Playfair Displayfont
 * @since 10.4.0 - Desktop job cards now match TikTok-style question-card design, mobile job cards improved with proper height and action buttons, browse button enhanced with prompt variations
 * @since 10.3.0 - Enhanced mobile interface with native app experience, responsive design (320px-768px), chat search, profile panel, and autocomplete
 * @since 10.2.0 - Added Slack-inspired input design for desktop and mobile with professional 8px border radius
 * @since 10.1.0 - Added premium job browsing interface with shortlist panel and enhanced job card system
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define senna-careers specific constants (with guards to prevent redefinition)
if (!defined('SENNA_CAREERS_VERSION')) {
    define('SENNA_CAREERS_VERSION', '11.14.1');
}
if (!defined('SENNA_CAREERS_PATH')) {
    define('SENNA_CAREERS_PATH', plugin_dir_path(__FILE__));
}
if (!defined('SENNA_CAREERS_URL')) {
    define('SENNA_CAREERS_URL', plugin_dir_url(__FILE__));
}
if (!defined('SENNA_CAREERS_BASENAME')) {
    define('SENNA_CAREERS_BASENAME', plugin_basename(__FILE__));
}
if (!defined('SENNA_CAREERS_FILE')) {
    define('SENNA_CAREERS_FILE', __FILE__);
}

// Legacy SENNA_ constants - only define if not already set by senna-plugin
if (!defined('SENNA_VERSION')) {
    define('SENNA_VERSION', '11.8.1');
}
if (!defined('SENNA_PLUGIN_DIR')) {
    define('SENNA_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('SENNA_PLUGIN_URL')) {
    define('SENNA_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('SENNA_PLUGIN_BASENAME')) {
    define('SENNA_PLUGIN_BASENAME', plugin_basename(__FILE__));
}
if (!defined('SENNA_PLUGIN_FILE')) {
    define('SENNA_PLUGIN_FILE', __FILE__);
}

// Backward compatibility aliases for SFFC_ constants (use senna-careers specific paths)
if (!defined('SFFC_VERSION')) {
    define('SFFC_VERSION', SENNA_CAREERS_VERSION);
}
if (!defined('SFFC_PLUGIN_DIR')) {
    define('SFFC_PLUGIN_DIR', SENNA_CAREERS_PATH);
}
if (!defined('SFFC_PLUGIN_URL')) {
    define('SFFC_PLUGIN_URL', SENNA_CAREERS_URL);
}
if (!defined('SFFC_PLUGIN_BASENAME')) {
    define('SFFC_PLUGIN_BASENAME', SENNA_CAREERS_BASENAME);
}
if (!defined('SFFC_PLUGIN_FILE')) {
    define('SFFC_PLUGIN_FILE', SENNA_CAREERS_FILE);
}

/**
 * The main plugin class - MENA Careers
 */
class Senna_Careers
{

    /**
     * Instance
     */
    private static $instance = null;

    /**
     * One-time option key for deactivating disabled PE feeds.
     *
     * @var string
     */
    private const DISABLED_PE_FEEDS_OPTION = 'sffc_disabled_pe_feeds_deactivated';

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
     * Constructor
     */
    private function __construct()
    {
        $this->enable_pe_news_feed_production();
        $this->load_classes();
        $this->init_ajax_handlers_immediately(); // CRITICAL: Must be before init_hooks()
        $this->init_hooks();
    }

    /**
     * Enable PE news/deals/signals production and intelligence modules.
     */
    private function enable_pe_news_feed_production()
    {
        add_filter('sffc_pe_news_feed_enabled', '__return_true');
    }

    /**
     * Legacy kill switch for PE news/deals/signals production feed.
     *
     * This now exits when the feature is enabled, but remains available if another
     * environment deliberately overrides sffc_pe_news_feed_enabled to false.
     */
    private function disable_pe_news_feed_production()
    {
        if (apply_filters('sffc_pe_news_feed_enabled', false)) {
            return;
        }

        foreach (array(
            'sffc_process_pe_content',
            'sffc_cleanup_old_pe_content',
            'sffc_process_pe_feeds',
            'sffc_process_pe_feeds_async',
            'sffc_pe_insights_fetch',
        ) as $hook) {
            wp_clear_scheduled_hook($hook);
        }

        if (class_exists('SFFC_Database')) {
            global $wpdb;

            $db = SFFC_Database::get_instance();
            $xml_feeds_table = $db->get_table('xml_feeds');

            if ($xml_feeds_table) {
                $wpdb->query("UPDATE {$xml_feeds_table} SET is_active = 0");
            }
        }
    }

    /**
     * Initialize AJAX handlers IMMEDIATELY - not on init hook
     * This MUST happen as soon as classes are loaded or AJAX won't work
     */
    private function init_ajax_handlers_immediately()
    {
        // V2 Ajax Handler MUST be initialized NOW to register AJAX actions
        if (class_exists('SFFC_Ajax_Handler_V2')) {
            SFFC_Ajax_Handler_V2::get_instance();
        }

        // Initialize MemberPress Campaigns system (wrapped in try-catch to prevent fatal errors)
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-memberpress-campaigns-init.php')) {
            try {
                require_once SFFC_PLUGIN_DIR . 'includes/class-memberpress-campaigns-init.php';
                // Only initialize if class exists
                if (class_exists('SFFC_MemberPress_Campaigns_Init')) {
                    SFFC_MemberPress_Campaigns_Init::get_instance();
                }
            } catch (Exception $e) {
                // Log error but don't break the site
                error_log('MemberPress Campaigns initialization error: ' . $e->getMessage());
            }
        }
    }

    /**
     * Load all required classes
     */
    private function load_classes()
    {

        // Load Composer autoloader for PDF parser and other libraries
        if (file_exists(SFFC_PLUGIN_DIR . 'vendor/autoload.php')) {
            require_once SFFC_PLUGIN_DIR . 'vendor/autoload.php';
        }

        // CRITICAL: Load plugin fixes FIRST before anything else
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-plugin-critical-fixes.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-plugin-critical-fixes.php';
        }

        // Disabled temporarily: this file self-initializes and runs admin/database
        // repair logic on normal requests, which is too broad for production.

        // Load core classes
        require_once SFFC_PLUGIN_DIR . 'includes/class-database.php';
        require_once SFFC_PLUGIN_DIR . 'includes/class-database-table-manager.php';
        require_once SFFC_PLUGIN_DIR . 'includes/class-session-manager.php';
        require_once SFFC_PLUGIN_DIR . 'includes/class-user-manager.php';
        require_once SFFC_PLUGIN_DIR . 'includes/class-api-key-manager.php';

        // Load Error Handler - CRITICAL: Must be loaded before it's used

        // Load Template Intelligence System - NEW CLASSES FOR PE INSIGHTS
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-template-intelligence-system.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-template-intelligence-system.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-template-library.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-template-library.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-pe-insights-fetcher.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-pe-insights-fetcher.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-deal-intelligence-processor.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-deal-intelligence-processor.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-claude-template-enhancer.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-claude-template-enhancer.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-advanced-chart-generator.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-advanced-chart-generator.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-admin-management-dashboard.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-admin-management-dashboard.php';
        }
        require_once SFFC_PLUGIN_DIR . 'includes/class-error-handler.php';

        // Load Cache Manager - Referenced by other classes
        require_once SFFC_PLUGIN_DIR . 'includes/class-cache-manager.php';


        // Load Claude API Manager - Required by hybrid response manager
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-claude-api-manager.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-claude-api-manager.php';
        }

        // Load REST API Endpoints
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-api-endpoints.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-api-endpoints.php';
        }

        // Load Deal Excel Generator - For deal article Excel export
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-deal-excel-generator.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-deal-excel-generator.php';
        }

        // Load Dynamic Financial Intelligence System
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-intelligence-schema.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-intelligence-schema.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-api-cost-manager.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-api-cost-manager.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-article-classifier.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-article-classifier.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-market-data.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-market-data.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-model-matcher.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-model-matcher.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-file-streamer.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-file-streamer.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-model-generator.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-model-generator.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-memo-generator.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-memo-generator.php';
        }

        // Load Intelligence Brief System
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-intelligence-enrichment.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-intelligence-enrichment.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-intelligence-narrative-generator.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-intelligence-narrative-generator.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-intelligence-market-context.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-intelligence-market-context.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-intelligence-brief-assembler.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-intelligence-brief-assembler.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-intelligence-brief-generator.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-intelligence-brief-generator.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-intelligence-brief-ajax.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-intelligence-brief-ajax.php';
        }

        // Load Intelligence Dashboard Admin
        if (is_admin() && file_exists(SFFC_PLUGIN_DIR . 'admin/class-intelligence-dashboard.php')) {
            require_once SFFC_PLUGIN_DIR . 'admin/class-intelligence-dashboard.php';
        }

        // Load Intelligence Settings Admin
        if (is_admin() && file_exists(SFFC_PLUGIN_DIR . 'admin/class-intelligence-settings.php')) {
            require_once SFFC_PLUGIN_DIR . 'admin/class-intelligence-settings.php';
        }

        // Load Hybrid Response Manager - Required by ajax handler
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-hybrid-response-manager.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-hybrid-response-manager.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-pe-visit-tracker.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-pe-visit-tracker.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-template-library.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-template-library.php';
        }

        // Load Professional Profile System
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-professional-profile-database.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-professional-profile-database.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-professional-profile-manager.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-professional-profile-manager.php';
        }

        // Load Professional Networking System
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-networking-system.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-networking-system.php';
        }

        // Load Job Fetcher Classes - For programmatic SEO
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-workday-job-fetcher-v2.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-workday-job-fetcher-v2.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-workday-auto-detector.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-workday-auto-detector.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-xml-job-fetcher.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-xml-job-fetcher.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-crm-job-title-normalizer.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-crm-job-title-normalizer.php';
        }

        // Load Gupy Job Fetcher for Brazilian feeds
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-gupy-job-fetcher.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-gupy-job-fetcher.php';
        }

        // Load SuccessFactors Job Fetcher for enterprise career portals
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-successfactors-job-fetcher.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-successfactors-job-fetcher.php';
        }

        // Load Comeet Job Fetcher for Comeet-powered career portals
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-comeet-job-fetcher.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-comeet-job-fetcher.php';
        }

        // Load Oracle HCM Job Fetcher for Oracle Fusion HCM career portals
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-oracle-hcm-job-fetcher.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-oracle-hcm-job-fetcher.php';
        }

        if (is_admin() && file_exists(SFFC_PLUGIN_DIR . 'includes/class-feed-manager-admin.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-feed-manager-admin.php';
        }


        // Load Custom Post Types for Jobs
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-custom-post-types.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-custom-post-types.php';
        }

        // TEMPORARY: Post type cleanup script - remove after running cleanup
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-post-type-cleanup.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-post-type-cleanup.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-company-title-helper.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-company-title-helper.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-recruiter-manager.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-recruiter-manager.php';
        }

        // Load PE Data Enrichment - Extracts PE-specific metadata
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-pe-data-enrichment.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-pe-data-enrichment.php';
        }




        // Load SEO Permalinks Manager - Creates optimized URLs
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-seo-permalinks.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-seo-permalinks.php';
        }

        // Load User Profile Manager - Core profile system
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-user-profile-manager.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-user-profile-manager.php';
        }

        // Load Professional CV System - Handles CV upload, parsing, and tailoring
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-professional-cv-system.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-professional-cv-system.php';
        }

        // Load Profile Builder Frontend - User-facing profile interface
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-profile-builder-frontend.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-profile-builder-frontend.php';
        }

        // Load Career Onboarding - McKinsey-style onboarding for new users
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-career-onboarding.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-career-onboarding.php';
        }

        // Load Unified Career Dashboard - Main dashboard hub
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/dashboard/class-dashboard-analytics-engine.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/dashboard/class-dashboard-analytics-engine.php';
        }
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/dashboard/class-dashboard-salary-analyzer.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/dashboard/class-dashboard-salary-analyzer.php';
        }
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/dashboard/class-dashboard-ai-insights.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/dashboard/class-dashboard-ai-insights.php';
        }
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/dashboard/class-dashboard-skills-analyzer.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/dashboard/class-dashboard-skills-analyzer.php';
        }
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/dashboard/class-dashboard-market-intelligence.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/dashboard/class-dashboard-market-intelligence.php';
        }
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/dashboard/class-dashboard-membership-handler.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/dashboard/class-dashboard-membership-handler.php';
        }
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/dashboard/class-dashboard-preferences-handler.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/dashboard/class-dashboard-preferences-handler.php';
        }
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/dashboard/class-unified-career-dashboard.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/dashboard/class-unified-career-dashboard.php';
        }

        // Mobility/relocation disabled.

        // Load CV Upload Handler - CV parsing and autofill preparation
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-cv-upload-handler.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-cv-upload-handler.php';
        }

        // Load Recruiter Introduction Admin Dashboard
        if (is_admin() && file_exists(SFFC_PLUGIN_DIR . 'includes/class-recruiter-introduction-admin.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-recruiter-introduction-admin.php';
        }

        // Load Recruiter Portal (self-service for recruiters)
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-recruiter-portal.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-recruiter-portal.php';
        }

        // Load Intro Submission Tracker (tracks manual recruiter outreach)
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-intro-submission-tracker.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-intro-submission-tracker.php';
        }

        // Load Recruiter Intro Admin Panel (for ops team)
        if (is_admin() && file_exists(SFFC_PLUGIN_DIR . 'includes/class-recruiter-intro-admin.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-recruiter-intro-admin.php';
        }

        // Load Skills Library
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-skills-library.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-skills-library.php';
        }

        // Load Application Audit System - Role-aware audit for job applications
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-application-audit.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-application-audit.php';
        }

        // Load Application Pack Generator - Premium document generation (CV, Cover Letter, etc.)
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/application-pack/class-application-pack-generator.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/application-pack/class-application-pack-generator.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-editorial-article-renderer.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-editorial-article-renderer.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-cv-parser.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-cv-parser.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-matching-engine.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-matching-engine.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-export-manager.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-export-manager.php';
        }


        // Load Ultimate CV Tailoring System - Complete working CV tailoring solution
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-ultimate-cv-tailoring.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-ultimate-cv-tailoring.php';
            // The class will self-instantiate via add_action('init') at the end of its file
            // No need to instantiate here as it would cause double instantiation
        }

        // Load Company Intelligence System - PE firm tracking and analysis
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-company-intelligence-engine.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-company-intelligence-engine.php';
        }

        // Load Company Profile Aggregator - Company data aggregation
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-company-profile-aggregator.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-company-profile-aggregator.php';
        }

        // Load Company Registry - Canonical entity resolution
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-company-registry.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-company-registry.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-pe-content-manager.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-pe-content-manager.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-pe-news-dashboard.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-pe-news-dashboard.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-news-diagnostics.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-news-diagnostics.php';
        }

        // Load Company Database Setup - Database tables for company system
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-company-database-setup.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-company-database-setup.php';
        }

        // Load Contacts Database for Newsroom Terminal
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-contacts-database.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-contacts-database.php';
        }

        // Load Candidate Conversations Database for NRT Replies Tab
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-candidate-conversations-database.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-candidate-conversations-database.php';
        }

        // Load Candidate Notifications - Email alerts for opportunities and messages
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-candidate-notifications.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-candidate-notifications.php';
        }

        // Load Google News compatibility for Newsroom Terminal
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-newsroom-google-news.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-newsroom-google-news.php';
        }

        // Load WebSub/PubSubHubbub Publisher - Instant Google notification on publish
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-websub-publisher.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-websub-publisher.php';
        }

        // Load User Preferences Manager - Handles feed/profile preferences
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-sffc-user-preferences.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-sffc-user-preferences.php';
        }

        // Load Personalized Feed Generator - Curates content based on user preferences
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-sffc-personalized-feed.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-sffc-personalized-feed.php';
        }

        // Load Premium Article Renderer - SEO-optimized sophisticated article display
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-premium-article-renderer.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-premium-article-renderer.php';
        }

        // Load PE Data Generator - Sample data for testing
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-pe-data-generator.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-pe-data-generator.php';
        }

        // Load Feed Integration Connector - Connects feeds to PE system
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-feed-integration-connector.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-feed-integration-connector.php';
        }

        // Load PE Article Enhancer - Generates rich narratives for PE news/deals
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-pe-article-enhancer.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-pe-article-enhancer.php';
        }

        // PE Content Manager intentionally disabled. Legacy PE news/deal/signal feeds are off.

        // XML Feed Processor - Intelligent feed processing with real-time data extraction
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-xml-feed-processor.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-xml-feed-processor.php';
        }


        // Load Ultimate CV Integration - Connects with existing buttons
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-ultimate-cv-integration.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-ultimate-cv-integration.php';
        }

        // Load Custom Intelligence Filters - CUSTOM INTELLIGENCE SYSTEM
        // Load Dynamic Post Type Filters - REAL DATA FILTERING SYSTEM
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-dynamic-post-type-filters.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-dynamic-post-type-filters.php';
        }

        // Load Dynamic Post Type Filters Admin - Admin interface for post type filters
        if (is_admin() && file_exists(SFFC_PLUGIN_DIR . 'admin/class-dynamic-post-type-filters-admin.php')) {
            require_once SFFC_PLUGIN_DIR . 'admin/class-dynamic-post-type-filters-admin.php';
        }

        // Load Search Query Cleanup Admin - Admin interface for search database cleanup
        if (is_admin() && file_exists(SFFC_PLUGIN_DIR . 'admin/class-search-cleanup-admin.php')) {
            require_once SFFC_PLUGIN_DIR . 'admin/class-search-cleanup-admin.php';
        }

        // Load Match Display Frontend - Shows match scores on job cards
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-match-display-frontend.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-match-display-frontend.php';
        }

        // Load Requirements Extractor - AI-powered job analysis
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-requirements-extractor.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-requirements-extractor.php';
        }

        // Load Intelligent Salary Estimator - Advanced salary calculation
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-intelligent-salary-estimator.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-intelligent-salary-estimator.php';
        }


        // Load MemberPress Integration - Subscription management bridge
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-memberpress-integration.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-memberpress-integration.php';
        }

        // Load Candidate-to-Recruiter CRM System
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/crm/class-crm-init.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/crm/class-crm-init.php';
        }



        // Load MENA Careers Integration Helper - Prepares data for AI assistant
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-senna-integration-helper.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-senna-integration-helper.php';
        }

        // Load Phase 4 AJAX Handlers - Shortlist, Comparison, Strategy Dashboard
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/ajax/class-phase4-ajax-handlers.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/ajax/class-phase4-ajax-handlers.php';
        }

        // Load Profile AJAX Handlers - Authentication and profile syncing
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/ajax/class-profile-ajax-handlers.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/ajax/class-profile-ajax-handlers.php';
        }

        // Load CV AJAX Handlers - CV storage and retrieval
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/ajax/class-cv-ajax-handlers.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/ajax/class-cv-ajax-handlers.php';
        }

        // Load Application Leads Database
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-application-leads-db.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-application-leads-db.php';
        }

        // Load Database Setup - Creates and manages database tables
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-database-setup.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-database-setup.php';
        }

        // Load Lead AJAX Handlers
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/ajax/class-lead-ajax-handlers.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/ajax/class-lead-ajax-handlers.php';
        }

        // Load Application AJAX Handlers - Handles application tracking
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/ajax/class-application-ajax-handlers.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/ajax/class-application-ajax-handlers.php';
        }

        // Load AI Content AJAX Handler - Handles AI-powered content generation
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/ajax/class-ai-content-ajax-handler.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/ajax/class-ai-content-ajax-handler.php';
        }

        // Load AJAX Error Handler - Handles AJAX errors and nonce issues
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/ajax/class-ajax-error-handler.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/ajax/class-ajax-error-handler.php';
        }

        // Load PE Admin AJAX - Handles PE admin operations
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/ajax/class-pe-admin-ajax.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/ajax/class-pe-admin-ajax.php';
        }

        // Load Opportunities AJAX Handler - Handles job loading
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/ajax/class-opportunities-ajax-handler.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/ajax/class-opportunities-ajax-handler.php';
        }

        // Load Dashboard Search AJAX Handler - Handles search across PE news, deals, and jobs
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/ajax/class-dashboard-search-handler.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/ajax/class-dashboard-search-handler.php';
        }

        // Expert chat and legacy expert mode removed.

        // Load Expert Management System - Expert profiles, bookings, and admin approval
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-expert-management.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-expert-management.php';
        }


        // Load Candidate Opportunities Admin - Admin interface for NRT opportunities
        if (file_exists(SFFC_PLUGIN_DIR . 'admin/class-candidate-opportunities-admin.php')) {
            require_once SFFC_PLUGIN_DIR . 'admin/class-candidate-opportunities-admin.php';
        }

        // Load Candidate Conversations Admin - Admin interface for NRT conversations
        if (file_exists(SFFC_PLUGIN_DIR . 'admin/class-candidate-conversations-admin.php')) {
            require_once SFFC_PLUGIN_DIR . 'admin/class-candidate-conversations-admin.php';
        }

        // Use V2 Ajax Handler - NO PLACEHOLDER DATA
        require_once SFFC_PLUGIN_DIR . 'includes/class-ajax-handler-v2.php';

        // Load Market Mode handlers for Market Analysis functionality
        require_once SFFC_PLUGIN_DIR . 'includes/class-market-ajax-handlers.php';

        // Load Performance-Optimized Market Analysis Components
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-correlation-engine.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-correlation-engine.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-market-scheduler.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-market-scheduler.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-sector-analyzer.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-sector-analyzer.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-technical-indicators.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-technical-indicators.php';
        }

        // Load Economic Calendar for market events
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-economic-calendar.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-economic-calendar.php';
        }

        // Load Chart Renderer for TradingView integration
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-chart-renderer.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-chart-renderer.php';
        }

        // Load Chart AJAX Handlers
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-chart-ajax-handlers.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-chart-ajax-handlers.php';
        }

        // Load SEO Content Generation System
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-news-aggregator.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-news-aggregator.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-seo-article-generator.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-seo-article-generator.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-ai-content-processor.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-ai-content-processor.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-content-publisher.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-content-publisher.php';
        }


        // Load Career Opportunities Simple - Frontend interface for jobs
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-career-opportunities-simple.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-career-opportunities-simple.php';
        }

        // Load FAST Live Expert Messaging System
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-live-expert-messaging.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-live-expert-messaging.php';
        }
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-live-expert-ajax.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-live-expert-ajax.php';
        }

        // Load Recruiter Terminal - Campaign management for recruiters
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/recruiter-terminal/class-recruiter-terminal.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/recruiter-terminal/class-recruiter-terminal.php';
            Recruiter_Terminal::get_instance();
        }

        // Load AutoFill System Components
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-autofill-loader.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-autofill-loader.php';
        }

        // Load MENA Careers Chat Interface - AI Career Advisor Chat
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-senna-chat-interface.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-senna-chat-interface.php';
        }

        // MENA Careers Booking Form
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-senna-booking-form.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-senna-booking-form.php';
        }

        // PE Search Interface - Google-style search for PE content
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-pe-search-interface.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-autosuggestion-library.php';
            require_once SFFC_PLUGIN_DIR . 'includes/class-pe-search-interface.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-quick-search.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-quick-search.php';
        }

        // PE Search Results - Google-style results page with PE enhancements
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-pe-search-results.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-pe-search-results.php';
        }

        // Load Intelligence Feed - unified editorial board
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-intelligence-feed.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-intelligence-feed.php';
        }

        // PE Search Indexer - Phase 3.1: Search index builder for backend
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-search-indexer.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-search-indexer.php';
        }

        // PE Search Query Processor - Phase 3.1: Query processing and results ranking
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-search-query.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-search-query.php';
        }

        // PE Search Admin - Phase 3.1: Admin interface for search management
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-search-admin.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-search-admin.php';
        }

        // Search Query Cleanup System - Automated database optimization
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-search-query-cleanup.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-search-query-cleanup.php';
        }

        // Load AJAX Registration Fix - Ensures AJAX handlers are properly registered
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/ajax-registration-fix.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/ajax-registration-fix.php';
        }

        // Phase 21: Load Edge Case Handler
        require_once SFFC_PLUGIN_DIR . 'includes/class-edge-case-handler.php';

        // Phase 1: Load Data Foundation classes
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-database-schema.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-database-schema.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/services/class-real-time-feed-aggregator.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/services/class-real-time-feed-aggregator.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-data-cache-manager.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-data-cache-manager.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-real-data-response-manager.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-real-data-response-manager.php';
        }

        // Load COMPREHENSIVE ERROR FIXER FIRST - This is critical!
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-comprehensive-error-fixer.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-comprehensive-error-fixer.php';
        }

        // Recruiter Posts System - Admin-curated recruiter opportunities
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-recruiter-posts.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-recruiter-posts.php';
        }

        // Career Advice System - Daily Market Briefing integration
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-career-advice-library.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-career-advice-library.php';
        }
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-career-advice-manager.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-career-advice-manager.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-topic-idea-crawler.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-topic-idea-crawler.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-external-data-fetcher.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-external-data-fetcher.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-guide-charts-library.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-guide-charts-library.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-guide-template-library.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-guide-template-library.php';
        }

        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-guide-generator.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-guide-generator.php';
        }

        // Load admin classes only in admin
        if (is_admin()) {
            $admin_settings_file = $this->resolve_plugin_file('admin/class-admin-settings.php');
            if ($admin_settings_file !== '') {
                require_once $admin_settings_file;
            }

            $database_manager_file = $this->resolve_plugin_file('admin/class-database-manager.php');
            if ($database_manager_file === '') {
                $database_manager_file = $this->resolve_plugin_file('includes/class-database-manager.php');
            }
            if ($database_manager_file !== '') {
                require_once $database_manager_file;
            }

            // Load Job Data Cleanup admin page
            if (file_exists(SFFC_PLUGIN_DIR . 'admin/cleanup-job-data.php')) {
                require_once SFFC_PLUGIN_DIR . 'admin/cleanup-job-data.php';
            }

            // Load Contacts Database and Import Admin
            if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-contacts-database.php')) {
                require_once SFFC_PLUGIN_DIR . 'includes/class-contacts-database.php';
            }
            if (file_exists(SFFC_PLUGIN_DIR . 'admin/class-contacts-import-admin.php')) {
                require_once SFFC_PLUGIN_DIR . 'admin/class-contacts-import-admin.php';
            }

            // Load Manual Job Feed System
            if (file_exists(SFFC_PLUGIN_DIR . 'admin/class-manual-job-feed-admin.php')) {
                require_once SFFC_PLUGIN_DIR . 'admin/class-manual-job-feed-admin.php';
            }

            if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-manual-job-feed-generator.php')) {
                require_once SFFC_PLUGIN_DIR . 'includes/class-manual-job-feed-generator.php';
            }

            // Register AJAX handlers for database manager
            add_action('wp_ajax_sffc_register_live_chat_post_types', [$this, 'ajax_register_live_chat_post_types']);
            add_action('wp_ajax_sffc_verify_live_chat_post_types', [$this, 'ajax_verify_live_chat_post_types']);

            // Register error prevention settings
            add_action('admin_init', function () {
                register_setting('sffc_error_prevention_settings', 'sffc_error_prevention_auto');
                register_setting('sffc_error_prevention_settings', 'sffc_error_verbose_logging');
            });

            // Load Company Cleanup Utility
            if (file_exists(SFFC_PLUGIN_DIR . 'admin/merge-duplicate-companies.php')) {
                require_once SFFC_PLUGIN_DIR . 'admin/merge-duplicate-companies.php';
            }

            // Load Company Cleanup Direct Access
            if (file_exists(SFFC_PLUGIN_DIR . 'admin/company-cleanup-direct.php')) {
                require_once SFFC_PLUGIN_DIR . 'admin/company-cleanup-direct.php';
            }

            // Load European Markets Admin
            if (file_exists(SFFC_PLUGIN_DIR . 'admin/class-european-markets-admin.php')) {
                require_once SFFC_PLUGIN_DIR . 'admin/class-european-markets-admin.php';
            }

            // Load European Activator for manual activation
            if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-european-activator.php')) {
                require_once SFFC_PLUGIN_DIR . 'includes/class-european-activator.php';
                // Check tables on admin init
                add_action('admin_init', array('SFFC_European_Activator', 'maybe_upgrade'));
            }

            // Load Feed Ajax Handlers AND INITIALIZE IT
            if (file_exists(SFFC_PLUGIN_DIR . 'admin/class-feed-ajax-handlers.php')) {
                require_once SFFC_PLUGIN_DIR . 'admin/class-feed-ajax-handlers.php';
                // Initialize immediately for AJAX to work
                SFFC_Feed_Ajax_Handlers::get_instance();
            }

            // Load Profile Admin
            if (file_exists(SFFC_PLUGIN_DIR . 'admin/class-profile-admin.php')) {
                require_once SFFC_PLUGIN_DIR . 'admin/class-profile-admin.php';
            }

            // Initialize Search Query Cleanup System
            if (class_exists('SFFC_Search_Query_Cleanup')) {
                SFFC_Search_Query_Cleanup::get_instance();
            }

            // Initialize Search Query Cleanup Admin
            if (is_admin() && class_exists('SFFC_Search_Cleanup_Admin')) {
                new SFFC_Search_Cleanup_Admin();
            }

            // Load HTML Content Importer for Gutenberg HTML uploads
            if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-html-content-importer.php')) {
                require_once SFFC_PLUGIN_DIR . 'includes/class-html-content-importer.php';
                if (class_exists('SFFC_HTML_Content_Importer')) {
                    SFFC_HTML_Content_Importer::get_instance();
                }
            }
        }

        // Load Career Strategy Frontend
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-career-strategy-frontend.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-career-strategy-frontend.php';
        }

        // Load SIMPLE Career Opportunities Frontend
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-career-opportunities-simple.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-career-opportunities-simple.php';
        }

        // Load Application Planner
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/application-planner/class-application-planner.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/application-planner/class-application-planner.php';
        }

        // Load Gap Analyzer Shortcode (new side-by-side comparison)
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/application-planner/class-gap-analyzer-shortcode.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/application-planner/class-gap-analyzer-shortcode.php';
        }

        // Load Gap Analyzer Prompt Shortcode (prompt-led entry into the gap analyzer)
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/application-planner/class-gap-analyzer-prompt-shortcode.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/application-planner/class-gap-analyzer-prompt-shortcode.php';
        }

        // Load Bulk CV Analyzer Shortcode (recruiter screening tool)
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/application-planner/class-bulk-cv-analyzer-shortcode.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/application-planner/class-bulk-cv-analyzer-shortcode.php';
        }

        // Load Job Landing Page Shortcode (LinkedIn conversion landing page)
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-job-landing-shortcode.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-job-landing-shortcode.php';
        }

        // Load Logo Carousel Shortcode
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-logo-carousel-shortcode.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-logo-carousel-shortcode.php';
        }
    }

    /**
     * Resolve a plugin-relative file path safely.
     *
     * Falls back to the current plugin file directory in case a legacy constant
     * was defined incorrectly in the runtime environment.
     */
    private function resolve_plugin_file($relative_path)
    {
        $relative_path = ltrim((string) $relative_path, '/\\');
        if ($relative_path === '') {
            return '';
        }

        $candidates = [
            trailingslashit((string) SFFC_PLUGIN_DIR) . $relative_path,
            trailingslashit(__DIR__) . $relative_path,
        ];

        foreach (array_unique($candidates) as $candidate) {
            if (is_string($candidate) && $candidate !== '' && file_exists($candidate)) {
                return $candidate;
            }
        }

        if (function_exists('error_log')) {
            error_log(sprintf('MENA Careers: missing plugin file "%s".', $relative_path));
        }

        return '';
    }

    private function maybe_boot_frontend_feature(callable $boot_callback, array $shortcodes = [], array $ajax_actions = [], array $query_flags = [])
    {
        if (wp_doing_ajax()) {
            $action = sanitize_key(wp_unslash($_REQUEST['action'] ?? ''));
            if ($action !== '' && in_array($action, $ajax_actions, true)) {
                $boot_callback();
            }
            return;
        }

        if (is_admin() || wp_doing_cron()) {
            return;
        }

        add_action('wp', function () use ($boot_callback, $shortcodes, $query_flags) {
            if ($this->request_has_any_query_flag($query_flags) || $this->current_post_has_any_shortcode($shortcodes)) {
                $boot_callback();
            }
        }, 1);
    }

    private function request_has_any_query_flag(array $query_flags)
    {
        foreach ($query_flags as $flag) {
            if ($flag !== '' && isset($_GET[$flag])) {
                return true;
            }
        }

        return false;
    }

    private function current_post_has_any_shortcode(array $shortcodes)
    {
        if (empty($shortcodes)) {
            return false;
        }

        $post = get_post(get_queried_object_id());
        if (!$post instanceof WP_Post) {
            return false;
        }

        $content = (string) ($post->post_content ?? '');
        if ($content === '') {
            return false;
        }

        foreach ($shortcodes as $shortcode) {
            if ($shortcode === '') {
                continue;
            }

            if (has_shortcode($content, $shortcode) || stripos($content, '[' . $shortcode) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        // Initialize Custom Post Types immediately
        if (class_exists('SFFC_Custom_Post_Types')) {
            SFFC_Custom_Post_Types::get_instance();
        }

        // Initialize Unified Job Manager for fetching and saving jobs
        if (class_exists('SFFC_Unified_Job_Manager')) {
            SFFC_Unified_Job_Manager::get_instance();
        }

        // Initialize Background Processor - ONLY if not on frontend
        if (class_exists('SFFC_Background_Job_Processor') && (is_admin() || wp_doing_cron())) {
            SFFC_Background_Job_Processor::get_instance();
        }

        // Initialize SEO Permalinks - Lightweight, always needed
        if (class_exists('SFFC_SEO_Permalinks')) {
            SFFC_SEO_Permalinks::get_instance();
        }

        if (class_exists('SFFC_Job_Collection_Manager')) {
            SFFC_Job_Collection_Manager::get_instance();
        }

        if (class_exists('SFFC_PE_Content_Manager')) {
            SFFC_PE_Content_Manager::get_instance();
        }

        if (class_exists('SFFC_PE_News_Dashboard')) {
            SFFC_PE_News_Dashboard::get_instance();
        }

        // Initialize Advanced Job Opportunities
        if (class_exists('SFFC_Job_Opportunities_Advanced')) {
            SFFC_Job_Opportunities_Advanced::get_instance();
        }

        if (class_exists('SFFC_Career_Opportunities_Simple')) {
            $this->maybe_boot_frontend_feature(
                static function () {
                    SFFC_Career_Opportunities_Simple::get_instance();
                },
                ['career_opportunities', 'senna_reply'],
                [
                    'sffc_get_opportunities',
                    'sffc_intelligent_search',
                    'sffc_save_opportunity',
                    'sffc_track_preference',
                    'sffc_check_profile_completion',
                    'sffc_get_shared_jobs',
                    'sffc_analyze_job_with_claude',
                    'sffc_process_chat_query',
                    'sffc_get_input_session',
                ],
                ['load_opportunities', 'force_sffc']
            );
        }

        // Initialize Job Dashboard - Independent job-focused dashboard with monetization features
        if (class_exists('SFFC_Job_Dashboard')) {
            SFFC_Job_Dashboard::get_instance();
        }

        if (class_exists('SFFC_Quick_Search')) {
            $this->maybe_boot_frontend_feature(
                static function () {
                    SFFC_Quick_Search::get_instance();
                },
                ['sffc_quick_search'],
                ['sffc_quick_search_suggestions']
            );
        }

        if (class_exists('SFFC_Application_Planner')) {
            $this->maybe_boot_frontend_feature(
                static function () {
                    SFFC_Application_Planner::get_instance();
                },
                ['sffc_application_planner'],
                [
                    'sffc_analyze_application',
                    'sffc_save_planner_report',
                    'sffc_get_planner_reports',
                    'sffc_get_planner_report',
                    'sffc_delete_planner_report',
                    'sffc_export_planner_pdf',
                    'sffc_get_crm_jobs_for_comparison',
                    'sffc_compare_multiple_jobs',
                ]
            );
        }

        // Initialize Job System Integration - Deferred to avoid early loading issues
        add_action('init', function () {
            if (class_exists('SFFC_Job_System_Integration')) {
                SFFC_Job_System_Integration::get_instance();
            }
        }, 10);

        // Ultimate CV Tailoring initializes itself via singleton at the bottom of its file
        // No need to initialize here - it registers its own AJAX handlers

        // Initialize Professional CV System - Handles CV upload, parsing, and tailoring
        if (class_exists('SFFC_Professional_CV_System')) {
            SFFC_Professional_CV_System::get_instance();
        }

        // Application Audit System self-initializes via singleton at the bottom of its file

        // Initialize Job System Admin after WordPress is ready
        add_action('init', function () {
            if (class_exists('SFFC_Job_System_Admin')) {
                SFFC_Job_System_Admin::get_instance();
            }
        });

        // Activation/Deactivation
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Init
        add_action('init', array($this, 'init'));

        // Admin
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

            // Initialize admin classes
            $this->init_admin_classes();
        }

        // PREMIUM assets enqueue - CRITICAL for styling
        add_action('wp_enqueue_scripts', array($this, 'enqueue_premium_assets'));

        // Clear disabled feature cron hooks once after deploy/activation.
        add_action('init', array($this, 'maybe_clear_disabled_feature_crons'), 1);

        // Phase 1: Add cron schedules for data updates
        add_filter('cron_schedules', array('SFFC_Real_Time_Feed_Aggregator', 'add_cron_intervals'));

        // AJAX
        $this->register_ajax_handlers();
    }

    /**
     * Initialize admin classes
     */
    private function init_admin_classes()
    {
        // Initialize Manual Job Feed Admin
        if (class_exists('SFFC_Manual_Job_Feed_Admin')) {
            new SFFC_Manual_Job_Feed_Admin();
        }

        // Initialize other admin classes as needed
        // Add more admin class instantiations here
    }

    /**
     * Plugin activation
     */
    public function activate()
    {
        // Start output buffering to prevent any unexpected output
        ob_start();

        // Suppress any errors/warnings during activation
        $error_reporting = error_reporting();
        error_reporting(0);

        try {
            // Initialize database tables
            if (class_exists('SFFC_Database_Setup')) {
                SFFC_Database_Setup::init();
            }

            // Set default options - ALL MODES ENABLED FOR TESTING
            add_option('sffc_api_key', '');
            add_option('sffc_mode_settings', array(
                'enabled_modes' => array('career', 'market', 'skills', 'opportunities'),
                'default_mode' => 'career',
                'expert_mode_enabled' => true  // ENABLED FOR TESTING
            ));
            add_option('sffc_appearance_settings', array(
                'primary_color' => '#1B3B2F',
                'chat_font_size' => '18px',
                'enable_glassmorphism' => true
            ));

            // Enable XML feeds by default
            add_option('sffc_enable_xml_feeds', 1);  // Enabled by default
            add_option('sffc_feed_timeout', 15);     // 15 second timeout by default

            // Create upload directory
            $upload_dir = wp_upload_dir();
            $sffc_dir = $upload_dir['basedir'] . '/sffc-uploads';
            if (!file_exists($sffc_dir)) {
                @wp_mkdir_p($sffc_dir);
            }

            // Phase 1: Create database tables for pattern recognition engine
            if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-database-schema.php')) {
                require_once SFFC_PLUGIN_DIR . 'includes/class-database-schema.php';
                $schema = SFFC_Database_Schema::get_instance();
                // Start new buffer for this operation
                ob_start();
                $schema->create_tables();
                ob_end_clean();
            }

            // Initialize learning platform (seed courses)
            if (file_exists(SFFC_PLUGIN_DIR . 'includes/learning/class-seed-courses.php')) {
                require_once SFFC_PLUGIN_DIR . 'includes/learning/class-course-meta-fields.php';
                require_once SFFC_PLUGIN_DIR . 'includes/learning/class-seed-courses.php';
                $seed_courses = SFFC_Seed_Courses::get_instance();
                ob_start();
                $seed_courses->create_seed_courses();
                ob_end_clean();

                // Generate all lesson content
                if (file_exists(SFFC_PLUGIN_DIR . 'includes/learning/class-lesson-content-generator.php')) {
                    require_once SFFC_PLUGIN_DIR . 'includes/learning/class-lesson-content-generator.php';
                    $content_generator = SFFC_Lesson_Content_Generator::get_instance();
                    ob_start();
                    $content_generator->generate_all_lessons();
                    ob_end_clean();
                }
            }

            // Create background job queue table
            if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-background-job-processor.php')) {
                require_once SFFC_PLUGIN_DIR . 'includes/class-background-job-processor.php';
                if (class_exists('SFFC_Background_Job_Processor')) {
                    $processor = SFFC_Background_Job_Processor::get_instance();
                    ob_start();
                    $processor->create_queue_table();
                    ob_end_clean();
                }
            }



            // Create application leads table
            if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-application-leads-db.php')) {
                require_once SFFC_PLUGIN_DIR . 'includes/class-application-leads-db.php';
                if (class_exists('SFFC_Application_Leads_DB')) {
                    $leads_db = SFFC_Application_Leads_DB::get_instance();
                    ob_start();
                    $leads_db->create_table();
                    ob_end_clean();
                }
            }

            // Create user profile tables
            if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-user-profile-manager.php')) {
                require_once SFFC_PLUGIN_DIR . 'includes/class-user-profile-manager.php';
                if (class_exists('SFFC_User_Profile_Manager')) {
                    $profile_manager = SFFC_User_Profile_Manager::get_instance();
                    ob_start();
                    $profile_manager->create_tables();
                    ob_end_clean();
                }
            }

            // Mark that rewrite rules need flushing
            if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-seo-permalinks.php')) {
                require_once SFFC_PLUGIN_DIR . 'includes/class-seo-permalinks.php';
                if (class_exists('SFFC_SEO_Permalinks')) {
                    SFFC_SEO_Permalinks::mark_flush_needed();
                }
            }

            $this->clear_disabled_feature_crons();
            update_option('sffc_disabled_feature_crons_version', '1');

            @flush_rewrite_rules();
        } catch (Exception $e) {
            // Silently catch any exceptions during activation
        }

        // Restore error reporting
        error_reporting($error_reporting);

        // Clear any output that was generated during activation
        ob_end_clean();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate()
    {
        // Clear scheduled cron events
        wp_clear_scheduled_hook('sffc_process_job_queue');
        wp_clear_scheduled_hook('sffc_fetch_jobs_batch');
        wp_clear_scheduled_hook('sffc_generate_content_batch');
        wp_clear_scheduled_hook('sffc_cleanup_old_jobs');

        // Clear PE/news cron events
        wp_clear_scheduled_hook('sffc_process_pe_content');
        wp_clear_scheduled_hook('sffc_process_pe_feeds');
        wp_clear_scheduled_hook('sffc_process_pe_feeds_async');
        wp_clear_scheduled_hook('sffc_aggregate_news');
        wp_clear_scheduled_hook('sffc_analyze_aggregated_news');
        wp_clear_scheduled_hook('sffc_update_market_feeds');
        wp_clear_scheduled_hook('sffc_process_unlinked_news');
        wp_clear_scheduled_hook('sffc_websub_sitemap_ping');
        wp_clear_scheduled_hook('sffc_relocation_scheduled_generation');

        // Clear session cleanup cron
        wp_clear_scheduled_hook('sffc_cleanup_sessions');

        flush_rewrite_rules();
    }

    public function maybe_clear_disabled_feature_crons()
    {
        if (get_option('sffc_disabled_feature_crons_version') === '1') {
            return;
        }

        $this->clear_disabled_feature_crons();
        update_option('sffc_disabled_feature_crons_version', '1');
    }

    public function clear_disabled_feature_crons()
    {
        // PE/news disabled.
        wp_clear_scheduled_hook('sffc_process_pe_content');
        wp_clear_scheduled_hook('sffc_process_pe_feeds');
        wp_clear_scheduled_hook('sffc_process_pe_feeds_async');
        wp_clear_scheduled_hook('sffc_pe_insights_fetch');
        wp_clear_scheduled_hook('sffc_aggregate_news');
        wp_clear_scheduled_hook('sffc_analyze_aggregated_news');
        wp_clear_scheduled_hook('sffc_update_market_feeds');
        wp_clear_scheduled_hook('sffc_process_unlinked_news');
        wp_clear_scheduled_hook('sffc_websub_sitemap_ping');

        if (get_option(self::DISABLED_PE_FEEDS_OPTION) !== '1' && class_exists('SFFC_Database')) {
            global $wpdb;

            $db = SFFC_Database::get_instance();
            $xml_feeds_table = $db->get_table('xml_feeds');

            if ($xml_feeds_table) {
                $wpdb->query("UPDATE {$xml_feeds_table} SET is_active = 0 WHERE is_active <> 0");
            }

            update_option(self::DISABLED_PE_FEEDS_OPTION, '1');
        }

        // Relocation disabled.
        wp_clear_scheduled_hook('sffc_relocation_scheduled_generation');
    }


    /**
     * Add admin menu
     */
    public function add_admin_menu()
    {
        // Main menu
        add_menu_page(
            'MENA Careers',
            'SF Finance',
            'manage_options',
            'sffc-dashboard',
            array($this, 'render_dashboard'),
            'dashicons-chart-area',
            30
        );

        // Dashboard submenu
        add_submenu_page(
            'sffc-dashboard',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'sffc-dashboard',
            array($this, 'render_dashboard')
        );

        add_submenu_page(
            'sffc-dashboard',
            __('User Menu', 'senna-finance'),
            __('User Menu', 'senna-finance'),
            'manage_options',
            'sffc-user-menu',
            array($this, 'render_user_menu_manager')
        );

        // Database submenu
        add_submenu_page(
            'sffc-dashboard',
            'Database Tables',
            'Database',
            'manage_options',
            'sffc-database',
            array($this, 'render_database')
        );

        // Settings submenu
        add_submenu_page(
            'sffc-dashboard',
            'Settings',
            'Settings',
            'manage_options',
            'sffc-settings',
            array($this, 'render_settings')
        );

        // Complete Content Generator submenu
        add_submenu_page(
            'sffc-dashboard',
            'Generate Premium Content',
            'Generate Premium Content',
            'manage_options',
            'sffc-generate-premium-content',
            array($this, 'render_generate_premium_content')
        );

        // PE Intelligence Admin submenu
        add_submenu_page(
            'sffc-dashboard',
            'PE Intelligence',
            'PE Intelligence',
            'manage_options',
            'sffc-pe-admin',
            array($this, 'render_pe_admin')
        );

        // PE System Test submenu
        add_submenu_page(
            'sffc-dashboard',
            'PE System Test',
            'PE System Test',
            'manage_options',
            'sffc-pe-test',
            array($this, 'render_pe_test')
        );

        // Error Prevention Engine submenu
        add_submenu_page(
            'sffc-dashboard',
            'Error Prevention',
            'Error Prevention',
            'manage_options',
            'sffc-error-prevention',
            array($this, 'render_error_prevention')
        );

        add_submenu_page(
            'edit.php?post_type=sffc_company',
            __('News Diagnostics', 'senna-finance'),
            __('News Diagnostics', 'senna-finance'),
            'manage_options',
            'sffc-company-news-diagnostics',
            array($this, 'render_company_news_diagnostics')
        );
    }

    /**
     * Render dashboard page
     */
    public function render_dashboard()
    {
        $admin = new SFFC_Admin_Settings();
        $admin->render_dashboard();
    }

    /**
     * Render database page
     */
    public function render_database()
    {
        $db_manager = new SFFC_Database_Manager();
        $db_manager->render_page();
    }

    /**
     * Render settings page
     */
    public function render_settings()
    {
        $admin = new SFFC_Admin_Settings();
        $admin->render_settings();
    }

    public function render_user_menu_manager()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $dashboard = class_exists('SFFC_PE_News_Dashboard') ? SFFC_PE_News_Dashboard::get_instance() : null;
        $stored_items = get_option('sffc_dashboard_user_menu_items', array());
        $stored_plans = get_option('sffc_dashboard_plans', array());
        $normalized_stored_plans = $this->normalize_dashboard_plan_settings($stored_plans);
        if ($normalized_stored_plans !== $stored_plans) {
            update_option('sffc_dashboard_plans', $normalized_stored_plans);
            $stored_plans = $normalized_stored_plans;
        }

        if (isset($_POST['sffc_user_menu_submit']) && check_admin_referer('sffc_user_menu_manager', 'sffc_user_menu_nonce')) {
            $raw_rows = isset($_POST['sffc_user_menu']) ? wp_unslash($_POST['sffc_user_menu']) : array();
            $sanitized = $this->sanitize_user_menu_rows($raw_rows);
            update_option('sffc_dashboard_user_menu_items', $sanitized);
            $stored_items = $sanitized;
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('User menu updated successfully.', 'senna-finance') . '</p></div>';
        }

        if (isset($_POST['sffc_plan_submit']) && check_admin_referer('sffc_plan_manager', 'sffc_plan_nonce')) {
            $raw_rows = isset($_POST['sffc_plan']) ? wp_unslash($_POST['sffc_plan']) : array();
            $sanitized_plans = $this->normalize_dashboard_plan_settings($this->sanitize_plan_rows($raw_rows));
            update_option('sffc_dashboard_plans', $sanitized_plans);
            $stored_plans = $sanitized_plans;
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Membership plans updated successfully.', 'senna-finance') . '</p></div>';
        }

        if (empty($stored_items) && $dashboard) {
            $stored_items = $dashboard->get_default_user_menu_items();
        }

        if (empty($stored_items)) {
            $stored_items = array();
        }

        if (empty($stored_plans) && $dashboard) {
            $stored_plans = $dashboard->get_default_subscription_plans();
        }

        $rows = $stored_items;
        $rows[] = array(
            'label' => '',
            'url' => '',
            'visibility' => 'both',
            'target' => '_self'
        );

        $plan_rows = $stored_plans;
        $plan_rows[] = array(
            'name' => '',
            'price' => '',
            'price_amount' => '',
            'annual_price' => '',
            'annual_price_amount' => '',
            'price_currency' => get_option('currency_detector_base_currency', 'USD'),
            'matches_per_week' => '',
            'billing_cycle' => '',
            'annual_billing_cycle' => '',
            'tagline' => '',
            'audience' => '',
            'features' => array(),
            'mp_url' => '',
            'shortcode' => '',
            'annual_mp_url' => '',
            'annual_shortcode' => '',
            'memberpress_product_id' => 0,
            'slug' => '',
            'featured_signup' => 0,
            'is_annual' => 0,
            'recruiter_contact_pricing' => 0,
            'signup_path' => 'platform',
            'hero_eyebrow' => '',
            'hero_title' => '',
            'hero_copy' => '',
            'hero_image_url' => '',
            'hero_image_alt' => '',
            'hero_cta_label' => '',
            'authority_title' => '',
            'authority_copy' => '',
            'social_title' => '',
            'social_copy' => '',
            'social_review_score' => '',
            'social_review_count' => '',
            'social_reviews' => array(),
            'free_title' => '',
            'free_copy' => '',
            'category_title' => '',
            'category_copy' => '',
            'scarcity_title' => '',
            'scarcity_copy' => '',
            'now_title' => '',
            'now_copy' => '',
            'other_plans_label' => '',
            'back_label' => ''
        );
        $supported_plan_currencies = get_option('currency_detector_supported_currencies', array('USD', 'EUR', 'GBP'));
        if (empty($supported_plan_currencies) || !is_array($supported_plan_currencies)) {
            $supported_plan_currencies = array('USD', 'EUR', 'GBP');
        }

        $visibility_options = array(
            'both' => __('All users', 'senna-finance'),
            'logged_in' => __('Logged-in only', 'senna-finance'),
            'logged_out' => __('Guests only', 'senna-finance')
        );
        $target_options = array(
            '_self' => __('Same tab', 'senna-finance'),
            '_blank' => __('New tab', 'senna-finance')
        );
?>
        <div class="wrap">
            <h1><?php esc_html_e('Dashboard User Menu', 'senna-finance'); ?></h1>
            <p class="description">
                <?php esc_html_e('Manage the entries that appear in the Ask MENA Careers profile dropdown. Tokens like {{profile_url}}, {{login_url}}, {{logout_url}}, {{join_url}}, {{dashboard_url}}, {{saved_url}}, {{messages_url}}, and {{home_url}} are supported.', 'senna-finance'); ?>
            </p>
            <form method="post">
                <?php wp_nonce_field('sffc_user_menu_manager', 'sffc_user_menu_nonce'); ?>
                <table class="widefat fixed striped" style="margin-top: 20px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Label', 'senna-finance'); ?></th>
                            <th><?php esc_html_e('URL / Token', 'senna-finance'); ?></th>
                            <th><?php esc_html_e('Visibility', 'senna-finance'); ?></th>
                            <th><?php esc_html_e('Target', 'senna-finance'); ?></th>
                            <th><?php esc_html_e('Actions', 'senna-finance'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="sffc-user-menu-rows">
                        <?php foreach ($rows as $row) :
                            $label = isset($row['label']) ? $row['label'] : '';
                            $url = isset($row['url']) ? $row['url'] : '';
                            $visibility = isset($row['visibility']) ? $row['visibility'] : 'both';
                            $target = isset($row['target']) ? $row['target'] : '_self';
                        ?>
                            <tr>
                                <td><input type="text" name="sffc_user_menu[label][]" value="<?php echo esc_attr($label); ?>" class="regular-text" placeholder="<?php esc_attr_e('Label', 'senna-finance'); ?>"></td>
                                <td><input type="text" name="sffc_user_menu[url][]" value="<?php echo esc_attr($url); ?>" class="regular-text" placeholder="<?php esc_attr_e('https://… or {{profile_url}}', 'senna-finance'); ?>"></td>
                                <td>
                                    <select name="sffc_user_menu[visibility][]">
                                        <?php foreach ($visibility_options as $key => $text) : ?>
                                            <option value="<?php echo esc_attr($key); ?>" <?php selected($visibility, $key); ?>><?php echo esc_html($text); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <select name="sffc_user_menu[target][]">
                                        <?php foreach ($target_options as $key => $text) : ?>
                                            <option value="<?php echo esc_attr($key); ?>" <?php selected($target, $key); ?>><?php echo esc_html($text); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><button type="button" class="button sffc-remove-user-menu-row">&times;</button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p><button type="button" class="button" id="sffc-add-user-menu-row"><?php esc_html_e('Add Row', 'senna-finance'); ?></button></p>
                <p><button type="submit" name="sffc_user_menu_submit" class="button button-primary button-hero"><?php esc_html_e('Save Menu', 'senna-finance'); ?></button></p>
            </form>
        </div>
        <script>
            (function($) {
                var visibilityOptions = <?php echo wp_json_encode($visibility_options); ?>;
                var targetOptions = <?php echo wp_json_encode($target_options); ?>;
                var $table = $('#sffc-user-menu-rows');

                function buildSelect(name, options, selected) {
                    var html = '<select name="' + name + '">';
                    $.each(options, function(value, label) {
                        var sel = value === selected ? ' selected' : '';
                        html += '<option value="' + value + '"' + sel + '>' + label + '</option>';
                    });
                    html += '</select>';
                    return html;
                }

                function newRow() {
                    return '<tr>' +
                        '<td><input type="text" name="sffc_user_menu[label][]" class="regular-text" placeholder="<?php echo esc_js(__('Label', 'senna-finance')); ?>"></td>' +
                        '<td><input type="text" name="sffc_user_menu[url][]" class="regular-text" placeholder="<?php echo esc_js(__('https://… or {{profile_url}}', 'senna-finance')); ?>"></td>' +
                        '<td>' + buildSelect('sffc_user_menu[visibility][]', visibilityOptions, 'both') + '</td>' +
                        '<td>' + buildSelect('sffc_user_menu[target][]', targetOptions, '_self') + '</td>' +
                        '<td><button type="button" class="button sffc-remove-user-menu-row">&times;</button></td>' +
                        '</tr>';
                }

                $('#sffc-add-user-menu-row').on('click', function() {
                    $table.append(newRow());
                });

                $table.on('click', '.sffc-remove-user-menu-row', function() {
                    if ($table.find('tr').length > 1) {
                        $(this).closest('tr').remove();
                    } else {
                        $(this).closest('tr').find('input').val('');
                    }
                });
            })(jQuery);
        </script>
        <hr style="margin: 40px 0;" />
        <div class="wrap">
            <h2><?php esc_html_e('Membership Plans', 'senna-finance'); ?></h2>
            <p class="description"><?php esc_html_e('Configure the plans that drive the pricing experience. Assign each plan to a signup option; the signup screen shows the annual version only when an annual MemberPress shortcode is present.', 'senna-finance'); ?></p>
            <form method="post">
                <?php wp_nonce_field('sffc_plan_manager', 'sffc_plan_nonce'); ?>
                <table class="widefat fixed striped" style="margin-top: 20px;">
                    <thead>
                        <tr>
                            <th style="width:24%;"><?php esc_html_e('Plan', 'senna-finance'); ?></th>
                            <th style="width:22%;"><?php esc_html_e('Hero', 'senna-finance'); ?></th>
                            <th style="width:28%;"><?php esc_html_e('Influence Tiles', 'senna-finance'); ?></th>
                            <th style="width:18%;"><?php esc_html_e('Checkout + Labels', 'senna-finance'); ?></th>
                            <th style="width:8%;"><?php esc_html_e('Flags', 'senna-finance'); ?></th>
                            <th><?php esc_html_e('Actions', 'senna-finance'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="sffc-plan-rows">
                        <?php foreach ($plan_rows as $plan) :
                            $plan = wp_parse_args($plan, array(
                                'name' => '',
                                'price' => '',
                                'price_amount' => '',
                                'annual_price' => '',
                                'annual_price_amount' => '',
                                'price_currency' => get_option('currency_detector_base_currency', 'USD'),
                                'matches_per_week' => '',
                                'billing_cycle' => '',
                                'annual_billing_cycle' => '',
                                'tagline' => '',
                                'audience' => '',
                                'features' => array(),
                                'mp_url' => '',
                                'shortcode' => '',
                                'annual_mp_url' => '',
                                'annual_shortcode' => '',
                                'memberpress_product_id' => 0,
                                'slug' => '',
                                'featured_signup' => 0,
                                'is_annual' => 0,
                                'recruiter_contact_pricing' => 0,
                                'signup_path' => 'platform',
                                'hero_eyebrow' => '',
                                'hero_title' => '',
                                'hero_copy' => '',
                                'hero_image_url' => '',
                                'hero_image_alt' => '',
                                'hero_cta_label' => '',
                                'authority_title' => '',
                                'authority_copy' => '',
                                'social_title' => '',
                                'social_copy' => '',
                                'social_review_score' => '',
                                'social_review_count' => '',
                                'social_reviews' => array(),
                                'free_title' => '',
                                'free_copy' => '',
                                'category_title' => '',
                                'category_copy' => '',
                                'scarcity_title' => '',
                                'scarcity_copy' => '',
                                'now_title' => '',
                                'now_copy' => '',
                                'other_plans_label' => '',
                                'back_label' => ''
                            ));
                            $features_text = '';
                            if (!empty($plan['features'])) {
                                $features_text = is_array($plan['features']) ? implode("\n", $plan['features']) : $plan['features'];
                            }
                            $social_reviews_text = '';
                            if (!empty($plan['social_reviews'])) {
                                $social_reviews_text = is_array($plan['social_reviews']) ? implode("\n", $plan['social_reviews']) : $plan['social_reviews'];
                            }
                            $signup_path_raw = strtolower(trim((string) ($plan['signup_path'] ?? 'platform')));
                            $signup_path = 'platform';
                            $signup_path_key = sanitize_key($signup_path_raw);
                            if (in_array($signup_path_key, array('platform', 'mentorship', 'all_access', 'one_contact', 'extra_contacts', 'ongoing_contacts'), true)) {
                                $signup_path = $signup_path_key;
                            } elseif (preg_match('/\b(one contact|single contact|one recruiter contact)\b/', $signup_path_raw)) {
                                $signup_path = 'one_contact';
                            } elseif (preg_match('/\b(extra contacts|multiple contacts|more contacts)\b/', $signup_path_raw)) {
                                $signup_path = 'extra_contacts';
                            } elseif (preg_match('/\b(ongoing contacts|ongoing recruiter contacts|recruiter alerts|ongoing access)\b/', $signup_path_raw)) {
                                $signup_path = 'ongoing_contacts';
                            } elseif (
                                preg_match('/\b(all access|premium|full access|everything)\b/', $signup_path_raw)
                                || (
                                    preg_match('/\b(recruiter|contact|intro|platform|access)\b/', $signup_path_raw)
                                    && preg_match('/\b(cv|resume|linkedin|profile|review|mentorship|support)\b/', $signup_path_raw)
                                )
                            ) {
                                $signup_path = 'all_access';
                            } elseif (preg_match('/\b(profile positioning|cv positioning|cv|resume|linkedin|profile|review|mentorship|support)\b/', $signup_path_raw)) {
                                $signup_path = 'mentorship';
                            } elseif (preg_match('/\b(recruiter contacts?|recruiter|contacts?|intros?|platform|access only|basic)\b/', $signup_path_raw)) {
                                $signup_path = 'platform';
                            }
                        ?>
                            <tr>
                                <td>
                                    <p><label><strong><?php esc_html_e('Plan name', 'senna-finance'); ?></strong><br><input type="text" name="sffc_plan[name][]" value="<?php echo esc_attr($plan['name']); ?>" class="regular-text" placeholder="<?php esc_attr_e('Insights Membership', 'senna-finance'); ?>"></label></p>
                                    <p><label><strong><?php esc_html_e('Price label', 'senna-finance'); ?></strong><br><input type="text" name="sffc_plan[price][]" value="<?php echo esc_attr($plan['price']); ?>" class="regular-text" placeholder="<?php esc_attr_e('£29.99 / month', 'senna-finance'); ?>"></label></p>
                                    <p style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
                                        <label><strong><?php esc_html_e('Monthly amount', 'senna-finance'); ?></strong><br><input type="number" step="0.01" name="sffc_plan[price_amount][]" value="<?php echo esc_attr($plan['price_amount']); ?>" class="small-text" placeholder="29.99"></label>
                                        <label><strong><?php esc_html_e('Base currency', 'senna-finance'); ?></strong><br>
                                            <?php $selected_currency = $plan['price_currency']; ?>
                                            <select name="sffc_plan[price_currency][]">
                                                <?php foreach ($supported_plan_currencies as $currency_code) : ?>
                                                    <option value="<?php echo esc_attr($currency_code); ?>" <?php selected($selected_currency, $currency_code); ?>><?php echo esc_html($currency_code); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                        <label><strong><?php esc_html_e('Matches / week', 'senna-finance'); ?></strong><br><input type="number" step="1" min="0" name="sffc_plan[matches_per_week][]" value="<?php echo esc_attr($plan['matches_per_week']); ?>" class="small-text" placeholder="15"></label>
                                        <label><strong><?php esc_html_e('Monthly billing copy', 'senna-finance'); ?></strong><br><input type="text" name="sffc_plan[billing_cycle][]" value="<?php echo esc_attr($plan['billing_cycle']); ?>" class="regular-text" placeholder="<?php esc_attr_e('per month', 'senna-finance'); ?>"></label>
                                    </p>
                                    <p style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
                                        <label><strong><?php esc_html_e('Annual price label', 'senna-finance'); ?></strong><br><input type="text" name="sffc_plan[annual_price][]" value="<?php echo esc_attr($plan['annual_price'] ?? ''); ?>" class="regular-text" placeholder="<?php esc_attr_e('£299 / year', 'senna-finance'); ?>"></label>
                                        <label><strong><?php esc_html_e('Annual amount', 'senna-finance'); ?></strong><br><input type="number" step="0.01" name="sffc_plan[annual_price_amount][]" value="<?php echo esc_attr($plan['annual_price_amount'] ?? ''); ?>" class="small-text" placeholder="299"></label>
                                        <label><strong><?php esc_html_e('Annual billing copy', 'senna-finance'); ?></strong><br><input type="text" name="sffc_plan[annual_billing_cycle][]" value="<?php echo esc_attr($plan['annual_billing_cycle'] ?? ''); ?>" class="regular-text" placeholder="<?php esc_attr_e('per year', 'senna-finance'); ?>"></label>
                                    </p>
                                    <p><label><strong><?php esc_html_e('Tagline', 'senna-finance'); ?></strong><br><input type="text" name="sffc_plan[tagline][]" value="<?php echo esc_attr($plan['tagline']); ?>" class="regular-text" placeholder="<?php esc_attr_e('Short description', 'senna-finance'); ?>"></label></p>
                                    <p><label><strong><?php esc_html_e('Audience note', 'senna-finance'); ?></strong><br><input type="text" name="sffc_plan[audience][]" value="<?php echo esc_attr($plan['audience']); ?>" class="regular-text" placeholder="<?php esc_attr_e('Audience hint', 'senna-finance'); ?>"></label></p>
                                    <p><label><strong><?php esc_html_e('Features (one per line)', 'senna-finance'); ?></strong><br><textarea name="sffc_plan[features][]" rows="6" class="large-text code" placeholder="<?php esc_attr_e('One benefit per line', 'senna-finance'); ?>"><?php echo esc_textarea($features_text); ?></textarea></label></p>
                                </td>
                                <td>
                                    <p><label><strong><?php esc_html_e('Hero eyebrow', 'senna-finance'); ?></strong><br><input type="text" name="sffc_plan[hero_eyebrow][]" value="<?php echo esc_attr($plan['hero_eyebrow']); ?>" class="regular-text" placeholder="<?php esc_attr_e('MENA Careers Membership', 'senna-finance'); ?>"></label></p>
                                    <p><label><strong><?php esc_html_e('Hero title', 'senna-finance'); ?></strong><br><input type="text" name="sffc_plan[hero_title][]" value="<?php echo esc_attr($plan['hero_title']); ?>" class="regular-text" placeholder="<?php esc_attr_e('Choose the membership that fits how you want to win.', 'senna-finance'); ?>"></label></p>
                                    <p><label><strong><?php esc_html_e('Hero supporting copy', 'senna-finance'); ?></strong><br><textarea name="sffc_plan[hero_copy][]" rows="4" class="large-text" placeholder="<?php esc_attr_e('Main hero message shown in the top tile.', 'senna-finance'); ?>"><?php echo esc_textarea($plan['hero_copy']); ?></textarea></label></p>
                                    <div class="sffc-plan-hero-image-field" style="margin:0 0 1em;">
                                        <p><label><strong><?php esc_html_e('Hero image URL', 'senna-finance'); ?></strong><br><input type="url" name="sffc_plan[hero_image_url][]" value="<?php echo esc_attr($plan['hero_image_url']); ?>" class="regular-text sffc-plan-hero-image-url" placeholder="https://example.com/image.jpg"></label></p>
                                        <p style="display:flex;gap:8px;flex-wrap:wrap;">
                                            <button type="button" class="button sffc-plan-hero-upload"><?php esc_html_e('Upload image', 'senna-finance'); ?></button>
                                            <button type="button" class="button sffc-plan-hero-remove"><?php esc_html_e('Remove image', 'senna-finance'); ?></button>
                                        </p>
                                        <div class="sffc-plan-hero-preview<?php echo empty($plan['hero_image_url']) ? ' is-empty' : ''; ?>" style="margin-top:8px;">
                                            <img src="<?php echo esc_url($plan['hero_image_url']); ?>" alt="" style="<?php echo empty($plan['hero_image_url']) ? 'display:none;' : 'display:block;max-width:180px;border-radius:10px;'; ?>" class="sffc-plan-hero-preview-image">
                                        </div>
                                    </div>
                                    <p><label><strong><?php esc_html_e('Hero image alt text', 'senna-finance'); ?></strong><br><input type="text" name="sffc_plan[hero_image_alt][]" value="<?php echo esc_attr($plan['hero_image_alt']); ?>" class="regular-text" placeholder="<?php esc_attr_e('Descriptive image alt text', 'senna-finance'); ?>"></label></p>
                                    <p><label><strong><?php esc_html_e('Primary CTA label', 'senna-finance'); ?></strong><br><input type="text" name="sffc_plan[hero_cta_label][]" value="<?php echo esc_attr($plan['hero_cta_label']); ?>" class="regular-text" placeholder="<?php esc_attr_e('Join now', 'senna-finance'); ?>"></label></p>
                                </td>
                                <td>
                                    <p><strong><?php esc_html_e('Authority bias', 'senna-finance'); ?></strong><br><input type="text" name="sffc_plan[authority_title][]" value="<?php echo esc_attr($plan['authority_title']); ?>" class="regular-text" placeholder="<?php esc_attr_e('Authority title', 'senna-finance'); ?>"><br><textarea name="sffc_plan[authority_copy][]" rows="3" class="large-text" placeholder="<?php esc_attr_e('Authority copy', 'senna-finance'); ?>"><?php echo esc_textarea($plan['authority_copy']); ?></textarea></p>
                                    <p><strong><?php esc_html_e('Social proof', 'senna-finance'); ?></strong><br><input type="text" name="sffc_plan[social_title][]" value="<?php echo esc_attr($plan['social_title']); ?>" class="regular-text" placeholder="<?php esc_attr_e('Social proof title', 'senna-finance'); ?>"><br><textarea name="sffc_plan[social_copy][]" rows="2" class="large-text" placeholder="<?php esc_attr_e('Fallback copy if no reviews are set', 'senna-finance'); ?>"><?php echo esc_textarea($plan['social_copy']); ?></textarea><br><textarea name="sffc_plan[social_reviews][]" rows="5" class="large-text" placeholder="<?php esc_attr_e('Format: Review text | First | Last | 5', 'senna-finance'); ?>"><?php echo esc_textarea($social_reviews_text); ?></textarea><br><input type="number" step="0.1" min="0" max="5" name="sffc_plan[social_review_score][]" value="<?php echo esc_attr($plan['social_review_score']); ?>" class="small-text" placeholder="4.9"> <input type="number" step="1" min="0" name="sffc_plan[social_review_count][]" value="<?php echo esc_attr($plan['social_review_count']); ?>" class="small-text" placeholder="1200"></p>
                                    <p><strong><?php esc_html_e('Power of free', 'senna-finance'); ?></strong><br><input type="text" name="sffc_plan[free_title][]" value="<?php echo esc_attr($plan['free_title']); ?>" class="regular-text" placeholder="<?php esc_attr_e('Free item title', 'senna-finance'); ?>"><br><textarea name="sffc_plan[free_copy][]" rows="3" class="large-text" placeholder="<?php esc_attr_e('Free item copy', 'senna-finance'); ?>"><?php echo esc_textarea($plan['free_copy']); ?></textarea></p>
                                    <p><strong><?php esc_html_e('Category heuristics', 'senna-finance'); ?></strong><br><input type="text" name="sffc_plan[category_title][]" value="<?php echo esc_attr($plan['category_title']); ?>" class="regular-text" placeholder="<?php esc_attr_e('Category title', 'senna-finance'); ?>"><br><textarea name="sffc_plan[category_copy][]" rows="3" class="large-text" placeholder="<?php esc_attr_e('Category copy', 'senna-finance'); ?>"><?php echo esc_textarea($plan['category_copy']); ?></textarea></p>
                                    <p><strong><?php esc_html_e('Scarcity bias', 'senna-finance'); ?></strong><br><input type="text" name="sffc_plan[scarcity_title][]" value="<?php echo esc_attr($plan['scarcity_title']); ?>" class="regular-text" placeholder="<?php esc_attr_e('Scarcity title', 'senna-finance'); ?>"><br><textarea name="sffc_plan[scarcity_copy][]" rows="3" class="large-text" placeholder="<?php esc_attr_e('Scarcity copy', 'senna-finance'); ?>"><?php echo esc_textarea($plan['scarcity_copy']); ?></textarea></p>
                                    <p><strong><?php esc_html_e('Power of now', 'senna-finance'); ?></strong><br><input type="text" name="sffc_plan[now_title][]" value="<?php echo esc_attr($plan['now_title']); ?>" class="regular-text" placeholder="<?php esc_attr_e('Power of now title', 'senna-finance'); ?>"><br><textarea name="sffc_plan[now_copy][]" rows="3" class="large-text" placeholder="<?php esc_attr_e('Power of now copy', 'senna-finance'); ?>"><?php echo esc_textarea($plan['now_copy']); ?></textarea></p>
                                </td>
                                <td>
                                    <p><label><strong><?php esc_html_e('MemberPress URL', 'senna-finance'); ?></strong><br><input type="url" name="sffc_plan[mp_url][]" value="<?php echo esc_attr($plan['mp_url']); ?>" class="regular-text" placeholder="https://example.com#anchor"></label></p>
                                    <p><label><strong><?php esc_html_e('MemberPress shortcode', 'senna-finance'); ?></strong><br><input type="text" name="sffc_plan[shortcode][]" value="<?php echo esc_attr($plan['shortcode']); ?>" class="regular-text" placeholder="[mepr-membership-registration-form id=&quot;123&quot;]"></label></p>
                                    <p><label><strong><?php esc_html_e('Annual MemberPress URL', 'senna-finance'); ?></strong><br><input type="url" name="sffc_plan[annual_mp_url][]" value="<?php echo esc_attr($plan['annual_mp_url'] ?? ''); ?>" class="regular-text" placeholder="https://example.com#annual"></label></p>
                                    <p><label><strong><?php esc_html_e('Annual MemberPress shortcode', 'senna-finance'); ?></strong><br><input type="text" name="sffc_plan[annual_shortcode][]" value="<?php echo esc_attr($plan['annual_shortcode'] ?? ''); ?>" class="regular-text" placeholder="[mepr-membership-registration-form id=&quot;456&quot;]"></label></p>
                                    <?php
                                    $detected_memberpress_product_id = !empty($plan['memberpress_product_id'])
                                        ? (int) $plan['memberpress_product_id']
                                        : $this->extract_memberpress_product_id_from_shortcode($plan['shortcode']);
                                    ?>
                                    <p class="description">
                                        <?php
                                        if ($detected_memberpress_product_id) {
                                            printf(
                                                esc_html__('Detected MemberPress product ID: %d', 'senna-finance'),
                                                $detected_memberpress_product_id
                                            );
                                        } else {
                                            esc_html_e('No MemberPress product ID detected yet. Add a valid MemberPress registration shortcode.', 'senna-finance');
                                        }
                                        ?>
                                    </p>
                                    <p><label><strong><?php esc_html_e('Other plans label', 'senna-finance'); ?></strong><br><input type="text" name="sffc_plan[other_plans_label][]" value="<?php echo esc_attr($plan['other_plans_label']); ?>" class="regular-text" placeholder="<?php esc_attr_e('Other plans', 'senna-finance'); ?>"></label></p>
                                    <p><label><strong><?php esc_html_e('Back from form label', 'senna-finance'); ?></strong><br><input type="text" name="sffc_plan[back_label][]" value="<?php echo esc_attr($plan['back_label']); ?>" class="regular-text" placeholder="<?php esc_attr_e('Back to plans', 'senna-finance'); ?>"></label></p>
                                </td>
                                <td>
                                    <p>
                                        <label><strong><?php esc_html_e('Signup option', 'senna-finance'); ?></strong><br>
                                            <select name="sffc_plan[signup_path][]">
                                                <option value="platform" <?php selected($signup_path, 'platform'); ?>><?php esc_html_e('Recruiter Contacts', 'senna-finance'); ?></option>
                                                <option value="mentorship" <?php selected($signup_path, 'mentorship'); ?>><?php esc_html_e('Profile Positioning', 'senna-finance'); ?></option>
                                                <option value="all_access" <?php selected($signup_path, 'all_access'); ?>><?php esc_html_e('All Access', 'senna-finance'); ?></option>
                                                <option value="one_contact" <?php selected($signup_path, 'one_contact'); ?>><?php esc_html_e('One Contact Plan', 'senna-finance'); ?></option>
                                                <option value="extra_contacts" <?php selected($signup_path, 'extra_contacts'); ?>><?php esc_html_e('Extra Contacts', 'senna-finance'); ?></option>
                                                <option value="ongoing_contacts" <?php selected($signup_path, 'ongoing_contacts'); ?>><?php esc_html_e('Ongoing Contacts', 'senna-finance'); ?></option>
                                            </select>
                                        </label>
                                    </p>
                                    <input type="hidden" name="sffc_plan[featured_signup][]" value="<?php echo !empty($plan['featured_signup']) ? '1' : '0'; ?>" class="sffc-plan-flag-value">
                                    <label style="display:block;margin-bottom:14px;"><input type="checkbox" class="sffc-plan-flag-toggle" <?php checked(!empty($plan['featured_signup'])); ?>> <?php esc_html_e('Primary', 'senna-finance'); ?></label>
                                    <input type="hidden" name="sffc_plan[is_annual][]" value="<?php echo !empty($plan['is_annual']) ? '1' : '0'; ?>" class="sffc-plan-annual-value">
                                    <label style="display:block;margin-bottom:14px;"><input type="checkbox" class="sffc-plan-annual-toggle" <?php checked(!empty($plan['is_annual'])); ?>> <?php esc_html_e('Annual', 'senna-finance'); ?></label>
                                    <input type="hidden" name="sffc_plan[recruiter_contact_pricing][]" value="<?php echo !empty($plan['recruiter_contact_pricing']) ? '1' : '0'; ?>" class="sffc-plan-recruiter-pricing-value">
                                    <label style="display:block;"><input type="checkbox" class="sffc-plan-recruiter-pricing-toggle" <?php checked(!empty($plan['recruiter_contact_pricing'])); ?>> <?php esc_html_e('Include in recruiter contact pricing', 'senna-finance'); ?></label>
                                </td>
                                <td style="vertical-align:top;"><button type="button" class="button sffc-remove-plan-row">&times;</button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p><button type="button" class="button" id="sffc-add-plan-row"><?php esc_html_e('Add Plan', 'senna-finance'); ?></button></p>
                <p><button type="submit" name="sffc_plan_submit" class="button button-primary button-hero"><?php esc_html_e('Save Plans', 'senna-finance'); ?></button></p>
            </form>
        </div>
        <script>
            (function($) {
                var $planTable = $('#sffc-plan-rows');
                var mediaFrame = null;

                function updateHeroPreview($field, url) {
                    var $image = $field.find('.sffc-plan-hero-preview-image');
                    var hasUrl = !!url;
                    $field.find('.sffc-plan-hero-image-url').val(url || '');
                    $field.find('.sffc-plan-hero-preview').toggleClass('is-empty', !hasUrl);
                    if (hasUrl) {
                        $image.attr('src', url).show();
                    } else {
                        $image.attr('src', '').hide();
                    }
                }

                function newPlanRow() {
                    return '<tr>' +
                        '<td>' +
                        '<p><label><strong><?php echo esc_js(__('Plan name', 'senna-finance')); ?></strong><br><input type="text" name="sffc_plan[name][]" class="regular-text" placeholder="<?php echo esc_js(__('Plan name', 'senna-finance')); ?>"></label></p>' +
                        '<p><label><strong><?php echo esc_js(__('Price label', 'senna-finance')); ?></strong><br><input type="text" name="sffc_plan[price][]" class="regular-text" placeholder="<?php echo esc_js(__('£29.99 / month', 'senna-finance')); ?>"></label></p>' +
                        '<p style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">' +
                        '<label><strong><?php echo esc_js(__('Monthly amount', 'senna-finance')); ?></strong><br><input type="number" step="0.01" name="sffc_plan[price_amount][]" class="small-text" placeholder="29.99"></label>' +
                        '<label><strong><?php echo esc_js(__('Base currency', 'senna-finance')); ?></strong><br><select name="sffc_plan[price_currency][]">' +
                        <?php foreach ($supported_plan_currencies as $currency_code) : ?> '<option value="<?php echo esc_js($currency_code); ?>"><?php echo esc_js($currency_code); ?></option>' +
                        <?php endforeach; ?> '</select></label>' +
                        '<label><strong><?php echo esc_js(__('Matches / week', 'senna-finance')); ?></strong><br><input type="number" step="1" min="0" name="sffc_plan[matches_per_week][]" class="small-text" placeholder="15"></label>' +
                        '<label><strong><?php echo esc_js(__('Monthly billing copy', 'senna-finance')); ?></strong><br><input type="text" name="sffc_plan[billing_cycle][]" class="regular-text" placeholder="<?php echo esc_js(__('per month', 'senna-finance')); ?>"></label>' +
                        '</p>' +
                        '<p style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">' +
                        '<label><strong><?php echo esc_js(__('Annual price label', 'senna-finance')); ?></strong><br><input type="text" name="sffc_plan[annual_price][]" class="regular-text" placeholder="<?php echo esc_js(__('£299 / year', 'senna-finance')); ?>"></label>' +
                        '<label><strong><?php echo esc_js(__('Annual amount', 'senna-finance')); ?></strong><br><input type="number" step="0.01" name="sffc_plan[annual_price_amount][]" class="small-text" placeholder="299"></label>' +
                        '<label><strong><?php echo esc_js(__('Annual billing copy', 'senna-finance')); ?></strong><br><input type="text" name="sffc_plan[annual_billing_cycle][]" class="regular-text" placeholder="<?php echo esc_js(__('per year', 'senna-finance')); ?>"></label>' +
                        '</p>' +
                        '<p><label><strong><?php echo esc_js(__('Tagline', 'senna-finance')); ?></strong><br><input type="text" name="sffc_plan[tagline][]" class="regular-text" placeholder="<?php echo esc_js(__('Short tagline', 'senna-finance')); ?>"></label></p>' +
                        '<p><label><strong><?php echo esc_js(__('Audience note', 'senna-finance')); ?></strong><br><input type="text" name="sffc_plan[audience][]" class="regular-text" placeholder="<?php echo esc_js(__('Audience note', 'senna-finance')); ?>"></label></p>' +
                        '<p><label><strong><?php echo esc_js(__('Features (one per line)', 'senna-finance')); ?></strong><br><textarea name="sffc_plan[features][]" rows="6" class="large-text code" placeholder="<?php echo esc_js(__('One benefit per line', 'senna-finance')); ?>"></textarea></label></p>' +
                        '</td>' +
                        '<td>' +
                        '<p><label><strong><?php echo esc_js(__('Hero eyebrow', 'senna-finance')); ?></strong><br><input type="text" name="sffc_plan[hero_eyebrow][]" class="regular-text" placeholder="<?php echo esc_js(__('MENA Careers Membership', 'senna-finance')); ?>"></label></p>' +
                        '<p><label><strong><?php echo esc_js(__('Hero title', 'senna-finance')); ?></strong><br><input type="text" name="sffc_plan[hero_title][]" class="regular-text" placeholder="<?php echo esc_js(__('Choose the membership that fits how you want to win.', 'senna-finance')); ?>"></label></p>' +
                        '<p><label><strong><?php echo esc_js(__('Hero supporting copy', 'senna-finance')); ?></strong><br><textarea name="sffc_plan[hero_copy][]" rows="4" class="large-text" placeholder="<?php echo esc_js(__('Main hero message shown in the top tile.', 'senna-finance')); ?>"></textarea></label></p>' +
                        '<div class="sffc-plan-hero-image-field" style="margin:0 0 1em;">' +
                        '<p><label><strong><?php echo esc_js(__('Hero image URL', 'senna-finance')); ?></strong><br><input type="url" name="sffc_plan[hero_image_url][]" class="regular-text sffc-plan-hero-image-url" placeholder="https://example.com/image.jpg"></label></p>' +
                        '<p style="display:flex;gap:8px;flex-wrap:wrap;">' +
                        '<button type="button" class="button sffc-plan-hero-upload"><?php echo esc_js(__('Upload image', 'senna-finance')); ?></button>' +
                        '<button type="button" class="button sffc-plan-hero-remove"><?php echo esc_js(__('Remove image', 'senna-finance')); ?></button>' +
                        '</p>' +
                        '<div class="sffc-plan-hero-preview is-empty" style="margin-top:8px;"><img src="" alt="" style="display:none;max-width:180px;border-radius:10px;" class="sffc-plan-hero-preview-image"></div>' +
                        '</div>' +
                        '<p><label><strong><?php echo esc_js(__('Hero image alt text', 'senna-finance')); ?></strong><br><input type="text" name="sffc_plan[hero_image_alt][]" class="regular-text" placeholder="<?php echo esc_js(__('Descriptive image alt text', 'senna-finance')); ?>"></label></p>' +
                        '<p><label><strong><?php echo esc_js(__('Primary CTA label', 'senna-finance')); ?></strong><br><input type="text" name="sffc_plan[hero_cta_label][]" class="regular-text" placeholder="<?php echo esc_js(__('Join now', 'senna-finance')); ?>"></label></p>' +
                        '</td>' +
                        '<td>' +
                        '<p><strong><?php echo esc_js(__('Authority bias', 'senna-finance')); ?></strong><br><input type="text" name="sffc_plan[authority_title][]" class="regular-text" placeholder="<?php echo esc_js(__('Authority title', 'senna-finance')); ?>"><br><textarea name="sffc_plan[authority_copy][]" rows="3" class="large-text" placeholder="<?php echo esc_js(__('Authority copy', 'senna-finance')); ?>"></textarea></p>' +
                        '<p><strong><?php echo esc_js(__('Social proof', 'senna-finance')); ?></strong><br><input type="text" name="sffc_plan[social_title][]" class="regular-text" placeholder="<?php echo esc_js(__('Social proof title', 'senna-finance')); ?>"><br><textarea name="sffc_plan[social_copy][]" rows="2" class="large-text" placeholder="<?php echo esc_js(__('Fallback copy if no reviews are set', 'senna-finance')); ?>"></textarea><br><textarea name="sffc_plan[social_reviews][]" rows="5" class="large-text" placeholder="<?php echo esc_js(__('Format: Review text | First | Last | 5', 'senna-finance')); ?>"></textarea><br><input type="number" step="0.1" min="0" max="5" name="sffc_plan[social_review_score][]" class="small-text" placeholder="4.9"> <input type="number" step="1" min="0" name="sffc_plan[social_review_count][]" class="small-text" placeholder="1200"></p>' +
                        '<p><strong><?php echo esc_js(__('Power of free', 'senna-finance')); ?></strong><br><input type="text" name="sffc_plan[free_title][]" class="regular-text" placeholder="<?php echo esc_js(__('Free item title', 'senna-finance')); ?>"><br><textarea name="sffc_plan[free_copy][]" rows="3" class="large-text" placeholder="<?php echo esc_js(__('Free item copy', 'senna-finance')); ?>"></textarea></p>' +
                        '<p><strong><?php echo esc_js(__('Category heuristics', 'senna-finance')); ?></strong><br><input type="text" name="sffc_plan[category_title][]" class="regular-text" placeholder="<?php echo esc_js(__('Category title', 'senna-finance')); ?>"><br><textarea name="sffc_plan[category_copy][]" rows="3" class="large-text" placeholder="<?php echo esc_js(__('Category copy', 'senna-finance')); ?>"></textarea></p>' +
                        '<p><strong><?php echo esc_js(__('Scarcity bias', 'senna-finance')); ?></strong><br><input type="text" name="sffc_plan[scarcity_title][]" class="regular-text" placeholder="<?php echo esc_js(__('Scarcity title', 'senna-finance')); ?>"><br><textarea name="sffc_plan[scarcity_copy][]" rows="3" class="large-text" placeholder="<?php echo esc_js(__('Scarcity copy', 'senna-finance')); ?>"></textarea></p>' +
                        '<p><strong><?php echo esc_js(__('Power of now', 'senna-finance')); ?></strong><br><input type="text" name="sffc_plan[now_title][]" class="regular-text" placeholder="<?php echo esc_js(__('Power of now title', 'senna-finance')); ?>"><br><textarea name="sffc_plan[now_copy][]" rows="3" class="large-text" placeholder="<?php echo esc_js(__('Power of now copy', 'senna-finance')); ?>"></textarea></p>' +
                        '</td>' +
                        '<td>' +
                        '<p><label><strong><?php echo esc_js(__('MemberPress URL', 'senna-finance')); ?></strong><br><input type="url" name="sffc_plan[mp_url][]" class="regular-text" placeholder="https://example.com#anchor"></label></p>' +
                        '<p><label><strong><?php echo esc_js(__('MemberPress shortcode', 'senna-finance')); ?></strong><br><input type="text" name="sffc_plan[shortcode][]" class="regular-text" placeholder="[mepr-membership-registration-form id=&quot;123&quot;]"></label></p>' +
                        '<p><label><strong><?php echo esc_js(__('Annual MemberPress URL', 'senna-finance')); ?></strong><br><input type="url" name="sffc_plan[annual_mp_url][]" class="regular-text" placeholder="https://example.com#annual"></label></p>' +
                        '<p><label><strong><?php echo esc_js(__('Annual MemberPress shortcode', 'senna-finance')); ?></strong><br><input type="text" name="sffc_plan[annual_shortcode][]" class="regular-text" placeholder="[mepr-membership-registration-form id=&quot;456&quot;]"></label></p>' +
                        '<p><label><strong><?php echo esc_js(__('Other plans label', 'senna-finance')); ?></strong><br><input type="text" name="sffc_plan[other_plans_label][]" class="regular-text" placeholder="<?php echo esc_js(__('Other plans', 'senna-finance')); ?>"></label></p>' +
                        '<p><label><strong><?php echo esc_js(__('Back from form label', 'senna-finance')); ?></strong><br><input type="text" name="sffc_plan[back_label][]" class="regular-text" placeholder="<?php echo esc_js(__('Back to plans', 'senna-finance')); ?>"></label></p>' +
                        '</td>' +
                        '<td>' +
                        '<p><label><strong><?php echo esc_js(__('Signup option', 'senna-finance')); ?></strong><br><select name="sffc_plan[signup_path][]"><option value="platform"><?php echo esc_js(__('Recruiter Contacts', 'senna-finance')); ?></option><option value="mentorship"><?php echo esc_js(__('Profile Positioning', 'senna-finance')); ?></option><option value="all_access"><?php echo esc_js(__('All Access', 'senna-finance')); ?></option><option value="one_contact"><?php echo esc_js(__('One Contact Plan', 'senna-finance')); ?></option><option value="extra_contacts"><?php echo esc_js(__('Extra Contacts', 'senna-finance')); ?></option><option value="ongoing_contacts"><?php echo esc_js(__('Ongoing Contacts', 'senna-finance')); ?></option></select></label></p>' +
                        '<input type="hidden" name="sffc_plan[featured_signup][]" value="0" class="sffc-plan-flag-value">' +
                        '<label style="display:block;margin-bottom:14px;"><input type="checkbox" class="sffc-plan-flag-toggle"> <?php echo esc_js(__('Primary', 'senna-finance')); ?></label>' +
                        '<input type="hidden" name="sffc_plan[is_annual][]" value="0" class="sffc-plan-annual-value">' +
                        '<label style="display:block;margin-bottom:14px;"><input type="checkbox" class="sffc-plan-annual-toggle"> <?php echo esc_js(__('Annual', 'senna-finance')); ?></label>' +
                        '<input type="hidden" name="sffc_plan[recruiter_contact_pricing][]" value="0" class="sffc-plan-recruiter-pricing-value">' +
                        '<label style="display:block;"><input type="checkbox" class="sffc-plan-recruiter-pricing-toggle"> <?php echo esc_js(__('Include in recruiter contact pricing', 'senna-finance')); ?></label>' +
                        '</td>' +
                        '<td style="vertical-align:top;"><button type="button" class="button sffc-remove-plan-row">&times;</button></td>' +
                        '</tr>';
                }

                $('#sffc-add-plan-row').on('click', function() {
                    $planTable.append(newPlanRow());
                });

                $planTable.on('click', '.sffc-remove-plan-row', function() {
                    if ($planTable.find('tr').length > 1) {
                        $(this).closest('tr').remove();
                    } else {
                        $(this).closest('tr').find('input, textarea').val('');
                    }
                });

                function syncFlag($checkbox, selector) {
                    $checkbox.closest('tr').find(selector).val($checkbox.is(':checked') ? '1' : '0');
                }

                $planTable.on('change', '.sffc-plan-flag-toggle', function() {
                    syncFlag($(this), '.sffc-plan-flag-value');
                });

                $planTable.on('change', '.sffc-plan-annual-toggle', function() {
                    syncFlag($(this), '.sffc-plan-annual-value');
                });

                $planTable.on('change', '.sffc-plan-recruiter-pricing-toggle', function() {
                    syncFlag($(this), '.sffc-plan-recruiter-pricing-value');
                });

                $planTable.on('input', '.sffc-plan-hero-image-url', function() {
                    updateHeroPreview($(this).closest('.sffc-plan-hero-image-field'), $(this).val());
                });

                $planTable.on('click', '.sffc-plan-hero-remove', function() {
                    updateHeroPreview($(this).closest('.sffc-plan-hero-image-field'), '');
                });

                $planTable.on('click', '.sffc-plan-hero-upload', function(e) {
                    e.preventDefault();

                    var $field = $(this).closest('.sffc-plan-hero-image-field');

                    if (typeof wp === 'undefined' || !wp.media) {
                        return;
                    }

                    mediaFrame = wp.media({
                        title: '<?php echo esc_js(__('Select hero image', 'senna-finance')); ?>',
                        button: {
                            text: '<?php echo esc_js(__('Use image', 'senna-finance')); ?>'
                        },
                        library: {
                            type: 'image'
                        },
                        multiple: false
                    });

                    mediaFrame.on('select', function() {
                        var attachment = mediaFrame.state().get('selection').first().toJSON();
                        updateHeroPreview($field, attachment.url || '');
                    });

                    mediaFrame.open();
                });

                $planTable.find('tr').each(function() {
                    var $row = $(this);
                    syncFlag($row.find('.sffc-plan-flag-toggle'), '.sffc-plan-flag-value');
                    syncFlag($row.find('.sffc-plan-annual-toggle'), '.sffc-plan-annual-value');
                    syncFlag($row.find('.sffc-plan-recruiter-pricing-toggle'), '.sffc-plan-recruiter-pricing-value');
                    updateHeroPreview($row.find('.sffc-plan-hero-image-field'), $row.find('.sffc-plan-hero-image-url').val());
                });
            })(jQuery);
        </script>
    <?php
    }

    /**
     * Render PE admin page
     */
    public function render_pe_admin()
    {
        if (file_exists(SFFC_PLUGIN_DIR . 'admin/pe-admin-tools.php')) {
            include SFFC_PLUGIN_DIR . 'admin/pe-admin-tools.php';
        } else {
            echo '<div class="wrap"><h1>PE Intelligence Manager</h1><p>PE Admin Tools not found.</p></div>';
        }
    }

    /**
     * Render PE test page
     */
    public function render_pe_test()
    {
        if (file_exists(SFFC_PLUGIN_DIR . 'admin/pe-system-test.php')) {
            include SFFC_PLUGIN_DIR . 'admin/pe-system-test.php';
        } else {
            echo '<div class="wrap"><h1>PE System Test</h1><p>Test page not found.</p></div>';
        }
    }

    /**
     * Render generate premium content page
     */
    public function render_generate_premium_content()
    {
        // Use the fixed generator that actually works
        require_once SFFC_PLUGIN_DIR . 'admin/fixed-content-generator.php';
    }

    /**
     * Render error prevention page
     */
    public function render_error_prevention()
    {
        // Handle clear logs request
        if (isset($_POST['sffc_clear_error_logs']) && wp_verify_nonce($_POST['sffc_clear_logs_nonce'], 'sffc_clear_error_logs')) {
            delete_transient('sffc_prevented_errors');
            echo '<div class="notice notice-success is-dismissible"><p>Error logs cleared successfully.</p></div>';
        }

        // Include the error prevention settings page
        require_once SFFC_PLUGIN_DIR . 'admin/partials/error-prevention-settings.php';
    }

    /**
     * Render company news diagnostics page
     */
    public function render_company_news_diagnostics()
    {
        if (file_exists(SFFC_PLUGIN_DIR . 'admin/company-news-diagnostics.php')) {
            include SFFC_PLUGIN_DIR . 'admin/company-news-diagnostics.php';
        } else {
            echo '<div class="wrap"><h1>' . esc_html__('Company News Diagnostics', 'senna-finance') . '</h1><p>' . esc_html__('Diagnostics view not found.', 'senna-finance') . '</p></div>';
        }
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook)
    {
        // Ensure $hook is not null
        if (empty($hook)) {
            return;
        }

        // Only on our pages
        if (strpos($hook, 'sffc-') === false && strpos($hook, 'sf-finance') === false) {
            return;
        }

        wp_enqueue_style(
            'sffc-admin',
            SFFC_PLUGIN_URL . 'admin/assets/css/admin.css',
            array(),
            SFFC_VERSION
        );

        // Enhanced admin settings styles
        wp_enqueue_style(
            'sffc-admin-settings',
            SFFC_PLUGIN_URL . 'admin/css/admin-settings.css',
            array('sffc-admin'),
            SFFC_VERSION
        );

        wp_enqueue_script(
            'sffc-admin',
            SFFC_PLUGIN_URL . 'admin/assets/js/admin.js',
            array('jquery'),
            SFFC_VERSION,
            true
        );

        // Enhanced admin settings JavaScript
        wp_enqueue_script(
            'sffc-admin-settings',
            SFFC_PLUGIN_URL . 'admin/js/admin-settings.js',
            array('jquery', 'sffc-admin'),
            SFFC_VERSION,
            true
        );

        wp_localize_script('sffc-admin', 'sffc_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sffc_admin_nonce')
        ));

        // Also localize for admin-settings script
        wp_localize_script('sffc-admin-settings', 'sffc_settings', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sffc_settings_nonce'),
            'pe_news_feed_enabled' => (bool) apply_filters('sffc_pe_news_feed_enabled', false),
        ));
    }

    /**
     * Initialize plugin
     */
    public function init()
    {
        // Defer textdomain loading to avoid "too early" warning
        if (did_action('init')) {
            load_plugin_textdomain('senna-finance', false, dirname(plugin_basename(__FILE__)) . '/languages');
        }

        // Initialize other components that don't need immediate loading
        // (V2 Ajax Handler is already initialized in constructor)

        // Initialize database tables (for upgrades)
        if (class_exists('SFFC_Database_Setup')) {
            SFFC_Database_Setup::init();
        }

        // Session Manager - Manages conversations
        if (class_exists('SFFC_Session_Manager')) {
            SFFC_Session_Manager::get_instance();
        }

        // Database Manager
        if (class_exists('SFFC_Database')) {
            SFFC_Database::get_instance();
        }

        // Error Handler
        if (class_exists('SFFC_Error_Handler')) {
            SFFC_Error_Handler::get_instance();
        }

        // Cache Manager
        if (class_exists('SFFC_Cache_Manager')) {
            SFFC_Cache_Manager::get_instance();
        }

        // Performance Monitor - DISABLED
        // if (class_exists('SFFC_Performance_Monitor')) {
        //     SFFC_Performance_Monitor::get_instance();
        // }

        // Edge Case Handler
        if (class_exists('SFFC_Edge_Case_Handler')) {
            SFFC_Edge_Case_Handler::get_instance();
        }

        // Learning Platform - Initialize course meta fields handler
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/learning/class-course-meta-fields.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/learning/class-course-meta-fields.php';
            SFFC_Course_Meta_Fields::get_instance();
        }

        // Learning Platform - Initialize seed courses handler
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/learning/class-seed-courses.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/learning/class-seed-courses.php';
            SFFC_Seed_Courses::get_instance();
        }

        // Learning Platform - Initialize AJAX handler
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/ajax/class-learning-ajax-handler.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/ajax/class-learning-ajax-handler.php';
            SFFC_Learning_Ajax_Handler::get_instance();
        }

        // Learning Platform - Initialize lesson content manager
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/learning/class-lesson-content.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/learning/class-lesson-content.php';
            SFFC_Lesson_Content::get_instance();
        }

        // Learning Platform - Initialize lesson content generator
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/learning/class-lesson-content-generator.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/learning/class-lesson-content-generator.php';
            SFFC_Lesson_Content_Generator::get_instance();
        }

        // Learning Platform - Initialize mock interview system
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/learning/class-mock-interview.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/learning/class-mock-interview.php';
            SFFC_Mock_Interview::get_instance();
        }
    }

    /**
     * Enqueue premium assets - CRITICAL METHOD
     */
    public function enqueue_premium_assets()
    {
        // CV Upload Handler assets - ONLY load on pages with CV upload shortcode or opportunities pages
        global $post;
        $should_load_cv_assets = false;
        $has_post_context = is_a($post, 'WP_Post');
        $page_shortcodes = [];

        if ($has_post_context) {
            $page_shortcodes = [
                'sffc_application_audit',
                'sffc_audit_button',
                'sffc_ask_senna',
                'sffc_crm_reddit_feed',
                'sffc_crm_reddit_dashboard',
                'sffc_crm_reddit_job',
                'sffc_crm_console_search',
                'sffc_profile_builder',
                'sffc_professional_profile',
                'sffc_profile_dashboard',
                'career_opportunities',
                'senna_chat',
                'sffc_contact_capture',
                'sffc_contact_flow',
                'sffc_dubai_career_flow',
                'sffc_recruiter_intro_onboarding',
                'sffc_recruiter_intro_onboarding_general',
                'sffc_guest_search',
                'sffc_app_pack_preview',
                'sffc_service_preview',
            ];
        }

        $page_has_premium_frontend = false;
        if ($has_post_context) {
            foreach ($page_shortcodes as $shortcode) {
                if (has_shortcode($post->post_content, $shortcode)) {
                    $page_has_premium_frontend = true;
                    break;
                }
            }
        }

        // Check if current page has CV upload shortcode
        if ($has_post_context) {
            if (
                has_shortcode($post->post_content, 'sffc_cv_upload') ||
                has_shortcode($post->post_content, 'sffc_opportunities') ||
                has_shortcode($post->post_content, 'sffc_career_opportunities')
            ) {
                $should_load_cv_assets = true;
            }
        }

        // Check if on company archive pages
        if (
            is_post_type_archive('sffc_company') ||
            is_singular('sffc_company')
        ) {
            $should_load_cv_assets = true;
        }

        // IMPORTANT: Do NOT load on PE Intelligence pages
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'pe_intelligence_dashboard')) {
            $should_load_cv_assets = false;
        }

        // Only enqueue CV assets if needed
        if ($should_load_cv_assets) {
            wp_enqueue_style(
                'sffc-cv-upload',
                SFFC_PLUGIN_URL . 'assets/css/cv-upload.css',
                array(),
                SFFC_VERSION
            );

            wp_enqueue_script(
                'sffc-cv-upload',
                SFFC_PLUGIN_URL . 'assets/js/cv-upload-handler.js',
                array('jquery'),
                SFFC_VERSION,
                true
            );

            // WSJ CV Renderer Ultimate - New secure CV parsing and display
            wp_enqueue_script(
                'sffc-wsj-cv-renderer',
                SFFC_PLUGIN_URL . 'assets/js/wsj-cv-renderer-ultimate.js',
                array(),
                SFFC_VERSION,
                true
            );

            // WSJ CV Display Styles
            wp_enqueue_style(
                'sffc-wsj-cv-display',
                SFFC_PLUGIN_URL . 'assets/css/wsj-cv-display.css',
                array(),
                SFFC_VERSION
            );

            // WSJ CV Integration - Connects upload to WSJ display
            wp_enqueue_script(
                'sffc-cv-upload-wsj',
                SFFC_PLUGIN_URL . 'assets/js/cv-upload-wsj-integration.js',
                array('jquery', 'sffc-wsj-cv-renderer', 'sffc-cv-upload'),
                SFFC_VERSION,
                true
            );

            // Localize script with AJAX data
            wp_localize_script('sffc-cv-upload', 'sffc_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sffc_ajax_nonce'),
                'cv_upload_nonce' => wp_create_nonce('sffc_cv_upload'),
                'autofill_token_nonce' => wp_create_nonce('sffc_autofill_token'),
                'auth_settings' => array(
                    'login_url' => get_option('sffc_login_url', 'https://joinsenna.com/login-auth/'),
                    'registration_url' => get_option('sffc_registration_url', 'https://joinsenna.com/memberships/')
                ),
                'wsj_enabled' => true // Flag to enable WSJ renderer
            ));
        }

        // Professional Profile System Assets
        if (is_a($post, 'WP_Post')) {
            if (
                has_shortcode($post->post_content, 'sffc_professional_profile') ||
                has_shortcode($post->post_content, 'sffc_profile_dashboard')
            ) {
                wp_enqueue_style(
                    'sffc-professional-profile',
                    SFFC_PLUGIN_URL . 'assets/css/professional-profile-jpmorgan.css',
                    array(),
                    SFFC_VERSION
                );

                wp_enqueue_script(
                    'sffc-professional-profile',
                    SFFC_PLUGIN_URL . 'assets/js/professional-profile-jpmorgan.js',
                    array('jquery'),
                    SFFC_VERSION,
                    true
                );

                wp_localize_script('sffc-professional-profile', 'sffc_ajax', array(
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('sffc_professional_profile')
                ));
            }
        }

        if ($page_has_premium_frontend) {
            // Visual Artifacts System - For action cards structured interfaces
            wp_enqueue_style(
                'sffc-visual-artifacts',
                SFFC_PLUGIN_URL . 'assets/css/visual-artifacts.css',
                array(),
                SFFC_VERSION
            );

            wp_enqueue_script(
                'sffc-visual-artifacts',
                SFFC_PLUGIN_URL . 'assets/js/visual-artifacts-system.js',
                array('jquery'),
                SFFC_VERSION,
                true
            );

            // Z-index fixes for overlapping interfaces
            wp_enqueue_style(
                'sffc-profile-builder-zindex-fix',
                SFFC_PLUGIN_URL . 'assets/css/profile-builder-zindex-fix.css',
                array(),
                SFFC_VERSION
            );

            // Premium Authentication Form Styling
            wp_enqueue_style(
                'sffc-premium-auth-form',
                SFFC_PLUGIN_URL . 'assets/css/premium-auth-form.css',
                array(),
                SFFC_VERSION
            );

            // MENA Careers Visual Enhancements
            wp_enqueue_script(
                'sffc-senna-visual-enhancements',
                SFFC_PLUGIN_URL . 'assets/js/senna-visual-enhancements.js',
                array('jquery'),
                SFFC_VERSION,
                true
            );

            // Conversation Centering Fix CSS
            wp_enqueue_style(
                'sffc-conversation-centering-fix',
                SFFC_PLUGIN_URL . 'assets/css/conversation-centering-fix.css',
                array(),
                SFFC_VERSION
            );

            // Login Check for Ask MENA Careers Buttons - Ensures logged out users see join card
            wp_enqueue_script(
                'sffc-login-check-ask-senna',
                SFFC_PLUGIN_URL . 'assets/js/login-check-for-ask-senna.js',
                array('jquery'),
                SFFC_VERSION,
                true
            );

            // Chat Card Actions Fix - Ensures tailor CV and save buttons work in chat cards
            wp_enqueue_script(
                'sffc-chat-card-actions-fix',
                SFFC_PLUGIN_URL . 'assets/js/chat-card-actions-fix.js',
                array('jquery'),
                SFFC_VERSION,
                true
            );

            // Auto Scroll Messages - Ensures proper scrolling to senna-avatar
            wp_enqueue_script(
                'sffc-auto-scroll-messages',
                SFFC_PLUGIN_URL . 'assets/js/auto-scroll-messages.js',
                array('jquery'),
                SFFC_VERSION,
                true
            );
        }

        if (class_exists('SFFC_Recruiter_Manager') && did_action('wp') && (is_singular(SFFC_Recruiter_Manager::POST_TYPE) || is_post_type_archive(SFFC_Recruiter_Manager::POST_TYPE))) {
            wp_enqueue_style(
                'sffc-recruiter-profile',
                SFFC_PLUGIN_URL . 'assets/css/recruiter-profile.css',
                array(),
                SFFC_VERSION
            );
        }

        if ($has_post_context && (
            has_shortcode($post->post_content, 'sffc_application_audit') ||
            has_shortcode($post->post_content, 'sffc_audit_button')
        )) {
            // Application Audit System - Role-aware audit for job applications
            wp_enqueue_style(
                'sffc-application-audit',
                SFFC_PLUGIN_URL . 'assets/css/application-audit.css',
                array(),
                SFFC_VERSION
            );

            wp_enqueue_script(
                'sffc-application-audit',
                SFFC_PLUGIN_URL . 'assets/js/application-audit.js',
                array('jquery'),
                SFFC_VERSION,
                true
            );

            wp_localize_script('sffc-application-audit', 'sffc_ajax', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sffc_ajax_nonce'),
            ));
        }

        if ($page_has_premium_frontend && wp_script_is('sffc-login-check-ask-senna', 'enqueued')) {
            // Localize login check script with user data and settings
            wp_localize_script('sffc-login-check-ask-senna', 'sffc_login_check', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'plugin_url' => SFFC_PLUGIN_URL,
                'nonce' => wp_create_nonce('sffc_public_nonce'),
                'is_logged_in' => is_user_logged_in() ? '1' : '0',
                'user_logged_in' => is_user_logged_in(),
                'user_id' => get_current_user_id(),
                'join_url' => get_option('sffc_registration_url', 'https://joinsenna.com/memberships/'),
                'login_url' => get_option('sffc_login_url', 'https://joinsenna.com/login-auth/')
            ));
        }
    }

    /**
     * Register AJAX handlers
     */
    private function register_ajax_handlers()
    {
        // V2 AJAX Handler now manages frontend AJAX endpoints
        // Removed duplicate registrations: sffc_start_conversation, sffc_send_message

        // Admin AJAX (V2 handler manages sffc_test_claude_api)
        add_action('wp_ajax_sffc_create_tables', array($this, 'ajax_create_tables'));

        // FIXED: Add missing mode update handler
        add_action('wp_ajax_sffc_update_mode', array($this, 'ajax_update_mode'));
        add_action('wp_ajax_nopriv_sffc_update_mode', array($this, 'ajax_update_mode'));

        // PE Search Engine AJAX handlers
        add_action('wp_ajax_sffc_create_search_tables', array($this, 'ajax_create_search_tables'));
        add_action('wp_ajax_sffc_index_all_content', array($this, 'ajax_index_all_content'));
        add_action('wp_ajax_sffc_verify_search_index', array($this, 'ajax_verify_search_index'));
        add_action('wp_ajax_sffc_test_search', array($this, 'ajax_test_search'));
        add_action('wp_ajax_sffc_index_content_by_type', array($this, 'ajax_index_content_by_type'));
        add_action('wp_ajax_sffc_force_create_clicks_table', array($this, 'ajax_force_create_clicks_table'));
        add_action('wp_ajax_sffc_auto_fix_search', array($this, 'ajax_auto_fix_search'));
        add_action('wp_ajax_sffc_fix_fulltext_indexes', array($this, 'ajax_fix_fulltext_indexes'));

        // Spam Search Query Cleanup AJAX handlers
        add_action('wp_ajax_sffc_preview_spam_queries', array($this, 'ajax_preview_spam_queries'));
        add_action('wp_ajax_sffc_clear_all_spam_queries', array($this, 'ajax_clear_all_spam_queries'));

        // Recruiter Terminal AJAX handlers
        add_action('wp_ajax_sffc_create_rt_tables', array($this, 'ajax_create_rt_tables'));
        add_action('wp_ajax_sffc_verify_rt_tables', array($this, 'ajax_verify_rt_tables'));
        add_action('wp_ajax_sffc_test_rt_shortcode', array($this, 'ajax_test_rt_shortcode'));
    }

    /**
     * AJAX: Start conversation
     */
    public function ajax_start_conversation()
    {
        // The V2 handler will verify the nonce
        $ajax = SFFC_Ajax_Handler_V2::get_instance();
        $ajax->handle_start_conversation();
    }

    /**
     * AJAX: Send message
     */
    public function ajax_send_message()
    {
        // The V2 handler will verify the nonce
        $ajax = SFFC_Ajax_Handler_V2::get_instance();
        $ajax->handle_send_message();
    }

    /**
     * AJAX: Create tables
     */
    public function ajax_create_tables()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_ajax_referer('sffc_admin_nonce', 'nonce');

        // Create legacy tables via SFFC_Database
        $database = SFFC_Database::get_instance();
        $results = $database->create_tables();

        // Create modern tables via Database Table Manager (including networking tables)
        if (class_exists('SFFC_Database_Table_Manager')) {
            $table_manager = SFFC_Database_Table_Manager::get_instance();
            $table_results = $table_manager->create_all_tables();
            $results = array_merge($results, $table_results);
        }

        // Create Pattern Recognition & Dashboard tables via SFFC_Database_Schema
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-database-schema.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-database-schema.php';
            $schema = SFFC_Database_Schema::get_instance();
            ob_start();
            $schema->create_tables();
            ob_end_clean();
            $results['dashboard_schema'] = 'Dashboard tables created/updated';
        }

        wp_send_json_success(array(
            'message' => 'Tables created successfully',
            'results' => $results
        ));
    }

    /**
     * AJAX: Test Claude API
     */
    public function ajax_test_claude_api()
    {
        $ajax = SFFC_Ajax_Handler_V2::get_instance();
        $ajax->handle_test_claude_api();
    }

    /**
     * AJAX: Update mode - FIXED MISSING HANDLER
     */
    public function ajax_update_mode()
    {
        check_ajax_referer('sffc_frontend_nonce', 'nonce');

        $mode = sanitize_text_field($_POST['mode']);
        $allowed_modes = array('ai', 'expert', 'live');

        if (!in_array($mode, $allowed_modes)) {
            wp_send_json_error(array('message' => 'Invalid mode'));
        }

        // Update session mode
        if (isset($_SESSION)) {
            $_SESSION['sffc_expert_mode'] = $mode;
        }

        wp_send_json_success(array(
            'message' => 'Mode updated successfully',
            'mode' => $mode
        ));
    }

    /**
     * AJAX: Create Search Tables
     */
    public function ajax_create_search_tables()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_ajax_referer('sffc_admin_nonce', 'nonce');

        if (!class_exists('SFFC_Search_Indexer')) {
            wp_send_json_error('Search Indexer class not found. Please check plugin installation.');
            return;
        }

        try {
            $indexer = SFFC_Search_Indexer::get_instance();
            $indexer->create_tables();

            wp_send_json_success(array(
                'message' => 'Search tables created successfully',
                'details' => 'All PE search engine database tables have been created and are ready for content indexing.'
            ));
        } catch (Exception $e) {
            wp_send_json_error('Error creating search tables: ' . $e->getMessage());
        }
    }

    /**
     * AJAX: Index All Content
     */
    public function ajax_index_all_content()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_ajax_referer('sffc_admin_nonce', 'nonce');

        if (!class_exists('SFFC_Search_Indexer')) {
            wp_send_json_error('Search Indexer class not found. Please check plugin installation.');
            return;
        }

        try {
            $indexer = SFFC_Search_Indexer::get_instance();

            // Ensure tables exist first
            $indexer->maybe_create_tables();

            // Index all content using rebuild_index
            $results = $indexer->rebuild_index();

            wp_send_json_success(array(
                'message' => "Successfully rebuilt search index",
                'details' => 'All PE content has been indexed and is now searchable. This includes jobs, news, recruiters, companies, and deals.'
            ));
        } catch (Exception $e) {
            wp_send_json_error('Error indexing content: ' . $e->getMessage());
        }
    }

    /**
     * AJAX: Verify Search Index
     */
    public function ajax_verify_search_index()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_ajax_referer('sffc_admin_nonce', 'nonce');

        global $wpdb;

        try {
            $search_tables = array(
                'search_index' => $wpdb->prefix . 'sffc_search_index',
                'search_entities' => $wpdb->prefix . 'sffc_search_entities',
                'search_queries' => $wpdb->prefix . 'sffc_search_queries',
                'search_clicks' => $wpdb->prefix . 'sffc_search_clicks'
            );

            $verification_results = array();
            $total_indexed = 0;

            foreach ($search_tables as $name => $table) {
                $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table;
                if ($exists) {
                    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
                    $verification_results[$name] = array('exists' => true, 'count' => $count);
                    if ($name === 'search_index') {
                        $total_indexed = $count;
                    }
                } else {
                    $verification_results[$name] = array('exists' => false, 'count' => 0);
                }
            }

            $details = '<ul>';
            foreach ($verification_results as $name => $result) {
                $status = $result['exists'] ? '✅' : '❌';
                $display_name = ucwords(str_replace('_', ' ', $name));
                $details .= "<li>$status $display_name: " . ($result['exists'] ? $result['count'] . ' items' : 'Table missing') . "</li>";
            }
            $details .= '</ul>';

            wp_send_json_success(array(
                'message' => "Search index verification complete. Total indexed items: $total_indexed",
                'details' => $details
            ));
        } catch (Exception $e) {
            wp_send_json_error('Error verifying search index: ' . $e->getMessage());
        }
    }

    /**
     * AJAX: Test Search
     */
    public function ajax_test_search()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_ajax_referer('sffc_admin_nonce', 'nonce');

        if (!class_exists('SFFC_Search_Query')) {
            wp_send_json_error('Search Query processor not found. Please check plugin installation.');
            return;
        }

        try {
            $query = sanitize_text_field($_POST['query'] ?? 'analyst');
            $search_processor = SFFC_Search_Query::get_instance();
            $results = $search_processor->search($query, 'jobs', 1, array());

            $message = "Search test completed for query: '$query'";
            $details = '<ul>';
            $details .= '<li>Total results found: ' . $results['total'] . '</li>';
            $details .= '<li>Results returned: ' . count($results['results']) . '</li>';
            $details .= '<li>Search time: ' . $results['search_time'] . ' seconds</li>';
            $details .= '<li>Status: ' . ($results['total'] > 0 ? '✅ Search working correctly' : '⚠️ No results found - check content indexing') . '</li>';

            if (!empty($results['results'])) {
                $details .= '<li>Sample results:<ul>';
                foreach (array_slice($results['results'], 0, 3) as $result) {
                    $title = strip_tags($result['title'] ?? 'Untitled');
                    $details .= "<li>$title</li>";
                }
                $details .= '</ul></li>';
            }
            $details .= '</ul>';

            wp_send_json_success(array(
                'message' => $message,
                'details' => $details
            ));
        } catch (Exception $e) {
            wp_send_json_error('Error testing search: ' . $e->getMessage());
        }
    }

    /**
     * AJAX: Index Content By Type
     */
    public function ajax_index_content_by_type()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_ajax_referer('sffc_admin_nonce', 'nonce');

        if (!class_exists('SFFC_Search_Indexer')) {
            wp_send_json_error('Search Indexer class not found. Please check plugin installation.');
            return;
        }

        try {
            $post_type = sanitize_text_field($_POST['post_type'] ?? '');
            $allowed_types = array('sffc_pe_news', 'sffc_recruiter', 'sffc_company', 'sffc_deal');

            if (!in_array($post_type, $allowed_types)) {
                wp_send_json_error('Invalid post type specified.');
                return;
            }

            $indexer = SFFC_Search_Indexer::get_instance();

            // Ensure tables exist first
            $indexer->maybe_create_tables();

            // Index specific post type by getting posts and calling update_post_index
            global $wpdb;

            // Get posts of this type
            $posts = get_posts(array(
                'post_type' => $post_type,
                'post_status' => 'publish',
                'numberposts' => -1
            ));

            $count = 0;
            foreach ($posts as $post) {
                $indexer->update_post_index($post->ID, $post);
                $count++;
            }

            $display_name = ucwords(str_replace('sffc_', '', $post_type));

            wp_send_json_success(array(
                'message' => "Successfully indexed $count $display_name items",
                'details' => "Content indexing complete for post type: $post_type"
            ));
        } catch (Exception $e) {
            wp_send_json_error('Error indexing content by type: ' . $e->getMessage());
        }
    }

    /**
     * AJAX: Force Create Clicks Table (Emergency Fix)
     */
    public function ajax_force_create_clicks_table()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_ajax_referer('sffc_admin_nonce', 'nonce');

        global $wpdb;

        try {
            $clicks_table = $wpdb->prefix . 'sffc_search_clicks';

            // Check if table already exists
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$clicks_table'");

            if ($table_exists) {
                wp_send_json_success(array(
                    'message' => 'Search clicks table already exists',
                    'details' => 'The table is present but may not be showing in status. Try refreshing the page.'
                ));
                return;
            }

            // Create the table directly with SQL
            $charset_collate = $wpdb->get_charset_collate();

            $sql_clicks = "CREATE TABLE $clicks_table (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                query_id bigint(20),
                query_text varchar(500) NOT NULL,
                clicked_result_id bigint(20) NOT NULL,
                clicked_result_type varchar(50),
                clicked_position int DEFAULT NULL,
                result_url text,
                result_title varchar(500),
                user_id bigint(20) DEFAULT NULL,
                ip_address varchar(45),
                user_agent text,
                session_id varchar(100),
                search_mode varchar(50) DEFAULT 'jobs',
                time_to_click int DEFAULT NULL,
                clicked_date datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY query_idx (query_text(255)),
                KEY result_idx (clicked_result_id),
                KEY position_idx (clicked_position),
                KEY date_idx (clicked_date),
                KEY user_idx (user_id),
                KEY session_idx (session_id)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            $result = dbDelta($sql_clicks);

            // Verify table was created
            $table_exists_now = $wpdb->get_var("SHOW TABLES LIKE '$clicks_table'");

            if ($table_exists_now) {
                wp_send_json_success(array(
                    'message' => 'Search clicks table created successfully!',
                    'details' => 'The table has been created and should now show as ✅ in the status. Refresh the page to update the display.'
                ));
            } else {
                wp_send_json_error('Table creation failed. Check WordPress error logs for details.');
            }
        } catch (Exception $e) {
            wp_send_json_error('Error creating clicks table: ' . $e->getMessage());
        }
    }

    /**
     * AJAX: Auto-Fix All Search Issues
     */
    public function ajax_auto_fix_search()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_ajax_referer('sffc_admin_nonce', 'nonce');

        global $wpdb;

        $fixes_applied = array();
        $errors_found = array();
        $critical_issues = array();

        try {
            // TEST 1: Check search backend class and method
            if (class_exists('SFFC_Search_Query')) {
                $fixes_applied[] = '✅ SFFC_Search_Query class loaded';

                $search_processor = SFFC_Search_Query::get_instance();

                // Test if search method exists
                if (method_exists($search_processor, 'search')) {
                    $fixes_applied[] = '✅ search() method exists';

                    // Test actual search execution
                    try {
                        $test_results = $search_processor->search('analyst', 'jobs', 1, array());

                        if (isset($test_results['total']) && isset($test_results['results'])) {
                            $fixes_applied[] = "✅ Search backend returns valid structure (Total: {$test_results['total']}, Results: " . count($test_results['results']) . ")";

                            if ($test_results['total'] > 0 && empty($test_results['results'])) {
                                $critical_issues[] = "🚨 CRITICAL: Backend finds {$test_results['total']} total but returns " . count($test_results['results']) . " results - FORMATTING ISSUE";
                            }
                        } else {
                            $errors_found[] = '❌ Search method returns invalid structure';
                        }
                    } catch (Exception $e) {
                        $errors_found[] = '❌ Search execution error: ' . $e->getMessage();
                    }
                } else {
                    $errors_found[] = '❌ search() method missing from SFFC_Search_Query';
                }
            } else {
                $errors_found[] = '❌ SFFC_Search_Query class not found';
            }

            // TEST 2: Database content verification
            $index_table = $wpdb->prefix . 'sffc_search_index';
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$index_table'");

            if ($table_exists) {
                $total_indexed = $wpdb->get_var("SELECT COUNT(*) FROM $index_table");
                $fixes_applied[] = "✅ Search index has $total_indexed items";

                if ($total_indexed == 0) {
                    $critical_issues[] = "🚨 CRITICAL: Search index is empty - need to run indexing";
                }

                // Test direct database query
                $direct_query = "SELECT id, post_id, title, post_type FROM $index_table WHERE MATCH(title, content) AGAINST('analyst' IN NATURAL LANGUAGE MODE) LIMIT 5";
                $direct_results = $wpdb->get_results($direct_query);

                if (!empty($direct_results)) {
                    $fixes_applied[] = "✅ Direct DB query finds " . count($direct_results) . " matches for 'analyst'";
                } else {
                    $critical_issues[] = "🚨 CRITICAL: Direct database query finds no 'analyst' matches - indexing problem";
                }
            } else {
                $errors_found[] = "❌ Search index table missing";
            }

            // TEST 3: Results formatting check  
            if (class_exists('SFFC_PE_Search_Results')) {
                $fixes_applied[] = '✅ SFFC_PE_Search_Results class loaded';

                // Check if format_backend_result method is simplified
                $reflection = new ReflectionClass('SFFC_PE_Search_Results');
                if ($reflection->hasMethod('format_backend_result')) {
                    $fixes_applied[] = '✅ format_backend_result method exists';
                } else {
                    $errors_found[] = '❌ format_backend_result method missing';
                }
            } else {
                $errors_found[] = '❌ SFFC_PE_Search_Results class not found';
            }

            // TEST 4: Template and shortcode check
            if (function_exists('has_shortcode')) {
                // Check if search results shortcode is registered
                global $shortcode_tags;
                if (isset($shortcode_tags['sffc_pe_search_results'])) {
                    $fixes_applied[] = '✅ Search results shortcode registered';
                } else {
                    $errors_found[] = '❌ Search results shortcode not registered';
                }
            }

            // SUMMARY
            $total_fixes = count($fixes_applied);
            $total_errors = count($errors_found);
            $total_critical = count($critical_issues);

            $status_message = "Auto-fix analysis complete: $total_fixes working, $total_errors errors, $total_critical critical issues";

            $details = '<div style="max-height: 300px; overflow-y: auto;">';

            if (!empty($critical_issues)) {
                $details .= '<h4 style="color: #dc3232;">🚨 CRITICAL ISSUES (Fix These First!):</h4><ul>';
                foreach ($critical_issues as $issue) {
                    $details .= "<li>$issue</li>";
                }
                $details .= '</ul>';
            }

            if (!empty($errors_found)) {
                $details .= '<h4 style="color: #d63638;">❌ ERRORS FOUND:</h4><ul>';
                foreach ($errors_found as $error) {
                    $details .= "<li>$error</li>";
                }
                $details .= '</ul>';
            }

            if (!empty($fixes_applied)) {
                $details .= '<h4 style="color: #00a32a;">✅ WORKING CORRECTLY:</h4><ul>';
                foreach ($fixes_applied as $fix) {
                    $details .= "<li>$fix</li>";
                }
                $details .= '</ul>';
            }

            $details .= '</div>';

            wp_send_json_success(array(
                'message' => $status_message,
                'details' => $details,
                'critical_count' => $total_critical,
                'error_count' => $total_errors,
                'working_count' => $total_fixes
            ));
        } catch (Exception $e) {
            wp_send_json_error('Auto-fix system error: ' . $e->getMessage());
        }
    }

    /**
     * AJAX: Fix FULLTEXT indexes on search table
     */
    public function ajax_fix_fulltext_indexes()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_ajax_referer('sffc_admin_nonce', 'nonce');

        global $wpdb;

        $fixes_applied = array();
        $errors_found = array();

        try {
            $table_name = $wpdb->prefix . 'sffc_search_index';

            // Check if table exists
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
            if (!$table_exists) {
                wp_send_json_error("Search index table does not exist. Please create tables first.");
                return;
            }

            $fixes_applied[] = "✅ Search index table exists";

            // Check current indexes
            $current_indexes = $wpdb->get_results("SHOW INDEX FROM $table_name WHERE Key_name LIKE 'ft_%'");
            if (!empty($current_indexes)) {
                // Drop existing FULLTEXT indexes
                foreach ($current_indexes as $index) {
                    $index_name = $index->Key_name;
                    $drop_result = $wpdb->query("ALTER TABLE $table_name DROP INDEX `$index_name`");
                    if ($drop_result !== false) {
                        $fixes_applied[] = "✅ Dropped existing FULLTEXT index: $index_name";
                    } else {
                        $errors_found[] = "❌ Failed to drop index: $index_name";
                    }
                }
            }

            // Create comprehensive FULLTEXT indexes
            $indexes_to_create = array(
                'ft_title' => 'title',
                'ft_content' => 'content',
                'ft_excerpt' => 'excerpt',
                'ft_keywords' => 'keywords',
                'ft_title_content' => 'title, content',
                'ft_title_excerpt' => 'title, excerpt',
                'ft_all_text' => 'title, content, excerpt, keywords'
            );

            foreach ($indexes_to_create as $index_name => $columns) {
                $create_query = "ALTER TABLE $table_name ADD FULLTEXT INDEX $index_name ($columns)";
                $result = $wpdb->query($create_query);

                if ($result !== false) {
                    $fixes_applied[] = "✅ Created FULLTEXT index: $index_name ($columns)";
                } else {
                    $errors_found[] = "❌ Failed to create index: $index_name ($columns) - " . $wpdb->last_error;
                }
            }

            // Test the indexes
            $test_query = "SELECT COUNT(*) FROM $table_name WHERE MATCH(title) AGAINST('private' IN NATURAL LANGUAGE MODE)";
            $test_result = $wpdb->get_var($test_query);

            if ($test_result !== null) {
                $fixes_applied[] = "✅ FULLTEXT search test successful - found $test_result matches for 'private'";
            } else {
                $errors_found[] = "❌ FULLTEXT search test failed: " . $wpdb->last_error;
            }

            // Verify all indexes exist
            $final_indexes = $wpdb->get_results("SHOW INDEX FROM $table_name WHERE Key_name LIKE 'ft_%'");
            $created_count = count($final_indexes);
            $expected_count = count($indexes_to_create);

            if ($created_count >= $expected_count) {
                $fixes_applied[] = "✅ All FULLTEXT indexes created successfully ($created_count/$expected_count)";
            } else {
                $errors_found[] = "⚠️ Some indexes may be missing ($created_count/$expected_count created)";
            }

            // SUMMARY
            $total_fixes = count($fixes_applied);
            $total_errors = count($errors_found);

            $status_message = $total_errors == 0 ?
                "FULLTEXT indexes fixed successfully! Applied $total_fixes fixes." :
                "FULLTEXT index fix completed with issues: $total_fixes fixes, $total_errors errors";

            $details = '<div style="max-height: 300px; overflow-y: auto;">';

            if (!empty($errors_found)) {
                $details .= '<h4 style="color: #d63638;">❌ ERRORS:</h4><ul>';
                foreach ($errors_found as $error) {
                    $details .= "<li>$error</li>";
                }
                $details .= '</ul>';
            }

            if (!empty($fixes_applied)) {
                $details .= '<h4 style="color: #00a32a;">✅ FIXES APPLIED:</h4><ul>';
                foreach ($fixes_applied as $fix) {
                    $details .= "<li>$fix</li>";
                }
                $details .= '</ul>';
            }

            $details .= '<div class="notice notice-info" style="margin-top: 15px; padding: 10px;"><p><strong>Next Steps:</strong><br/>';
            $details .= '1. Test your search functionality<br/>';
            $details .= '2. If issues persist, try "Index All Content" to refresh the search data<br/>';
            $details .= '3. Check the "Test Search" function to verify everything works</p></div>';

            $details .= '</div>';

            if ($total_errors == 0) {
                wp_send_json_success(array(
                    'message' => $status_message,
                    'details' => $details
                ));
            } else {
                wp_send_json_error(array(
                    'message' => $status_message,
                    'details' => $details
                ));
            }
        } catch (Exception $e) {
            wp_send_json_error('FULLTEXT index fix failed: ' . $e->getMessage());
        }
    }

    /**
     * AJAX: Preview spam search queries before deletion
     */
    public function ajax_preview_spam_queries()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_ajax_referer('sffc_admin_nonce', 'nonce');

        global $wpdb;

        try {
            $table = $wpdb->prefix . 'sffc_search_queries';

            // Check if table exists
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
                wp_send_json_error('Search queries table does not exist.');
                return;
            }

            // Count spam queries matching repetitive patterns
            $spam_count = $wpdb->get_var("
                SELECT COUNT(*) FROM {$table}
                WHERE query_text REGEXP '(^|[[:space:]])(Top|top|Best|best|firms|compensation|headhunter|Private|equity|Fundraising|insights)[[:space:]]+(Top|top|Best|best|firms|compensation|headhunter|Private|equity|Fundraising|insights)'
                   OR query_text LIKE '%Top Top%'
                   OR query_text LIKE '%top top%'
                   OR query_text LIKE '%firms firms%'
                   OR query_text LIKE '%compensation compensation%'
                   OR query_text LIKE '%headhunter list%headhunter%'
                   OR LENGTH(query_text) > 100
            ");

            // Get sample spam queries
            $samples = $wpdb->get_col("
                SELECT DISTINCT query_text FROM {$table}
                WHERE query_text REGEXP '(^|[[:space:]])(Top|top|Best|best|firms|compensation|headhunter|Private|equity|Fundraising|insights)[[:space:]]+(Top|top|Best|best|firms|compensation|headhunter|Private|equity|Fundraising|insights)'
                   OR query_text LIKE '%Top Top%'
                   OR query_text LIKE '%top top%'
                   OR query_text LIKE '%firms firms%'
                   OR query_text LIKE '%compensation compensation%'
                   OR query_text LIKE '%headhunter list%headhunter%'
                   OR LENGTH(query_text) > 100
                LIMIT 20
            ");

            wp_send_json_success(array(
                'count' => intval($spam_count),
                'samples' => array_map('esc_html', $samples)
            ));
        } catch (Exception $e) {
            wp_send_json_error('Error: ' . $e->getMessage());
        }
    }

    /**
     * AJAX: Clear ALL spam search queries from database
     */
    public function ajax_clear_all_spam_queries()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_ajax_referer('sffc_admin_nonce', 'nonce');

        global $wpdb;

        try {
            $table = $wpdb->prefix . 'sffc_search_queries';

            // Check if table exists
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
                wp_send_json_error('Search queries table does not exist.');
                return;
            }

            // Count before deletion
            $count_before = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

            // Delete spam queries matching repetitive patterns
            // Pattern 1: Repetitive words using REGEXP
            $deleted1 = $wpdb->query("
                DELETE FROM {$table}
                WHERE query_text REGEXP '(^|[[:space:]])(Top|top|Best|best|firms|compensation|headhunter|Private|equity|Fundraising|insights)[[:space:]]+(Top|top|Best|best|firms|compensation|headhunter|Private|equity|Fundraising|insights)'
            ");

            // Pattern 2: Specific LIKE patterns for common spam
            $deleted2 = $wpdb->query("
                DELETE FROM {$table}
                WHERE query_text LIKE '%Top Top%'
                   OR query_text LIKE '%top top%'
                   OR query_text LIKE '%firms firms%'
                   OR query_text LIKE '%compensation compensation%'
                   OR query_text LIKE '%headhunter list%headhunter%'
            ");

            // Pattern 3: Excessively long queries (likely spam)
            $deleted3 = $wpdb->query("
                DELETE FROM {$table}
                WHERE LENGTH(query_text) > 100
            ");

            // Pattern 4: More repetitive word patterns
            $deleted4 = $wpdb->query("
                DELETE FROM {$table}
                WHERE query_text REGEXP '([[:alpha:]]+)[[:space:]]+\\\\1[[:space:]]+\\\\1'
            ");

            // Count after deletion
            $count_after = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
            $total_deleted = $count_before - $count_after;

            // Optimize the table after deletion
            $wpdb->query("OPTIMIZE TABLE {$table}");

            wp_send_json_success(array(
                'message' => "Successfully deleted {$total_deleted} spam queries!",
                'deleted' => intval($total_deleted),
                'remaining' => intval($count_after),
                'breakdown' => array(
                    'repetitive_pattern' => intval($deleted1),
                    'like_patterns' => intval($deleted2),
                    'long_queries' => intval($deleted3),
                    'triple_words' => intval($deleted4)
                )
            ));
        } catch (Exception $e) {
            wp_send_json_error('Error during cleanup: ' . $e->getMessage());
        }
    }

    /**
     * AJAX: Create Recruiter Terminal database tables
     */
    public function ajax_create_rt_tables()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_ajax_referer('sffc_admin_nonce', 'nonce');

        global $wpdb;

        try {
            // Check if RT DB class exists
            if (!class_exists('Recruiter_Terminal_DB')) {
                // Try to load it
                $db_file = SFFC_PLUGIN_DIR . 'includes/recruiter-terminal/class-recruiter-terminal-db.php';
                if (file_exists($db_file)) {
                    require_once $db_file;
                } else {
                    wp_send_json_error('Recruiter Terminal DB class file not found. Please upload the plugin files first.');
                    return;
                }
            }

            // Create tables
            Recruiter_Terminal_DB::create_tables();

            // Verify tables were created
            $tables = array(
                'rt_campaigns' => $wpdb->prefix . 'rt_campaigns',
                'rt_campaign_targets' => $wpdb->prefix . 'rt_campaign_targets',
                'rt_activity_log' => $wpdb->prefix . 'rt_activity_log',
                'rt_email_templates' => $wpdb->prefix . 'rt_email_templates'
            );

            $created = array();
            $failed = array();

            foreach ($tables as $name => $table) {
                $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table;
                if ($exists) {
                    $created[] = $name;
                } else {
                    $failed[] = $name;
                }
            }

            if (empty($failed)) {
                $details = '<ul>';
                foreach ($created as $table) {
                    $details .= "<li>✅ {$table} - Created successfully</li>";
                }
                $details .= '</ul>';

                wp_send_json_success(array(
                    'message' => 'All Recruiter Terminal tables created successfully!',
                    'details' => $details
                ));
            } else {
                $details = '<ul>';
                foreach ($created as $table) {
                    $details .= "<li>✅ {$table} - Created</li>";
                }
                foreach ($failed as $table) {
                    $details .= "<li>❌ {$table} - Failed to create</li>";
                }
                $details .= '</ul>';

                wp_send_json_error(array(
                    'message' => 'Some tables could not be created.',
                    'details' => $details
                ));
            }
        } catch (Exception $e) {
            wp_send_json_error('Failed to create RT tables: ' . $e->getMessage());
        }
    }

    /**
     * AJAX: Verify Recruiter Terminal database tables
     */
    public function ajax_verify_rt_tables()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_ajax_referer('sffc_admin_nonce', 'nonce');

        global $wpdb;

        $tables = array(
            'rt_campaigns' => $wpdb->prefix . 'rt_campaigns',
            'rt_campaign_targets' => $wpdb->prefix . 'rt_campaign_targets',
            'rt_activity_log' => $wpdb->prefix . 'rt_activity_log',
            'rt_email_templates' => $wpdb->prefix . 'rt_email_templates'
        );

        $results = array();
        $all_exist = true;

        foreach ($tables as $name => $table) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table;
            $row_count = $exists ? $wpdb->get_var("SELECT COUNT(*) FROM $table") : 0;
            $results[$name] = array(
                'exists' => $exists,
                'rows' => $row_count
            );
            if (!$exists) {
                $all_exist = false;
            }
        }

        $details = '<table class="wp-list-table widefat striped" style="margin-top: 10px;">';
        $details .= '<thead><tr><th>Table</th><th>Status</th><th>Rows</th></tr></thead><tbody>';

        foreach ($results as $name => $data) {
            $status = $data['exists'] ? '✅ Exists' : '❌ Missing';
            $status_class = $data['exists'] ? 'color: #00a32a;' : 'color: #dc3232;';
            $details .= "<tr><td>{$name}</td><td style='{$status_class}'>{$status}</td><td>{$data['rows']}</td></tr>";
        }

        $details .= '</tbody></table>';

        // Also check for shortcode
        $shortcode_exists = shortcode_exists('senna_recruiter_terminal');
        $details .= '<div style="margin-top: 15px;">';
        $details .= '<strong>Shortcode Status:</strong> ';
        $details .= $shortcode_exists ? '✅ [senna_recruiter_terminal] registered' : '❌ Shortcode not registered';
        $details .= '</div>';

        // Check DB version
        $db_version = get_option('rt_db_version', 'Not set');
        $details .= '<div style="margin-top: 10px;">';
        $details .= '<strong>DB Version:</strong> ' . esc_html($db_version);
        $details .= '</div>';

        $message = $all_exist ? 'All Recruiter Terminal tables exist!' : 'Some tables are missing. Click "Create RT Tables" to create them.';

        wp_send_json_success(array(
            'message' => $message,
            'details' => $details
        ));
    }

    /**
     * AJAX: Test Recruiter Terminal shortcode
     */
    public function ajax_test_rt_shortcode()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_ajax_referer('sffc_admin_nonce', 'nonce');

        $checks = array();

        // Check if main class exists
        $checks['main_class'] = array(
            'label' => 'Recruiter_Terminal class',
            'status' => class_exists('Recruiter_Terminal')
        );

        // Check if DB class exists
        $checks['db_class'] = array(
            'label' => 'Recruiter_Terminal_DB class',
            'status' => class_exists('Recruiter_Terminal_DB')
        );

        // Check if AJAX class exists
        $checks['ajax_class'] = array(
            'label' => 'Recruiter_Terminal_Ajax class',
            'status' => class_exists('Recruiter_Terminal_Ajax')
        );

        // Check if shortcode is registered
        $checks['shortcode'] = array(
            'label' => 'Shortcode [senna_recruiter_terminal]',
            'status' => shortcode_exists('senna_recruiter_terminal')
        );

        // Check if template exists
        $template_path = SFFC_PLUGIN_DIR . 'templates/recruiter-terminal.php';
        $checks['template'] = array(
            'label' => 'Template file',
            'status' => file_exists($template_path)
        );

        // Check CSS file
        $css_path = SFFC_PLUGIN_DIR . 'assets/css/recruiter-terminal.css';
        $checks['css'] = array(
            'label' => 'CSS file',
            'status' => file_exists($css_path)
        );

        // Check JS file
        $js_path = SFFC_PLUGIN_DIR . 'assets/js/recruiter-terminal.js';
        $checks['js'] = array(
            'label' => 'JavaScript file',
            'status' => file_exists($js_path)
        );

        // Check tables
        global $wpdb;
        $checks['tables'] = array(
            'label' => 'Database tables',
            'status' => class_exists('Recruiter_Terminal_DB') && Recruiter_Terminal_DB::tables_exist()
        );

        $all_passed = true;
        $details = '<table class="wp-list-table widefat striped" style="margin-top: 10px;">';
        $details .= '<thead><tr><th>Component</th><th>Status</th></tr></thead><tbody>';

        foreach ($checks as $key => $check) {
            $status = $check['status'] ? '✅ OK' : '❌ MISSING';
            $status_class = $check['status'] ? 'color: #00a32a;' : 'color: #dc3232;';
            $details .= "<tr><td>{$check['label']}</td><td style='{$status_class}'>{$status}</td></tr>";
            if (!$check['status']) {
                $all_passed = false;
            }
        }

        $details .= '</tbody></table>';

        if ($all_passed) {
            $details .= '<div class="notice notice-success" style="margin-top: 15px; padding: 10px;">';
            $details .= '<p><strong>✅ Recruiter Terminal is ready!</strong><br/>';
            $details .= 'You can use the shortcode <code>[senna_recruiter_terminal]</code> on any page.</p>';
            $details .= '</div>';
            $message = 'All checks passed! Recruiter Terminal is operational.';
        } else {
            $details .= '<div class="notice notice-warning" style="margin-top: 15px; padding: 10px;">';
            $details .= '<p><strong>⚠️ Some components are missing.</strong><br/>';
            $details .= 'Please ensure all files are uploaded and click "Create RT Tables" if tables are missing.</p>';
            $details .= '</div>';
            $message = 'Some components are missing. See details below.';
        }

        wp_send_json_success(array(
            'message' => $message,
            'details' => $details
        ));
    }

    private function sanitize_user_menu_rows($raw_rows)
    {
        $items = array();
        if (empty($raw_rows) || !is_array($raw_rows)) {
            return $items;
        }

        $labels = isset($raw_rows['label']) ? (array) $raw_rows['label'] : array();
        $urls = isset($raw_rows['url']) ? (array) $raw_rows['url'] : array();
        $visibilities = isset($raw_rows['visibility']) ? (array) $raw_rows['visibility'] : array();
        $targets = isset($raw_rows['target']) ? (array) $raw_rows['target'] : array();

        $total = max(count($labels), count($urls));
        for ($i = 0; $i < $total; $i++) {
            $label = isset($labels[$i]) ? sanitize_text_field($labels[$i]) : '';
            $url_input = isset($urls[$i]) ? trim(wp_kses_post($urls[$i])) : '';
            if ('' === $label || '' === $url_input) {
                continue;
            }

            $visibility = isset($visibilities[$i]) ? sanitize_key($visibilities[$i]) : 'both';
            if (!in_array($visibility, array('both', 'logged_in', 'logged_out'), true)) {
                $visibility = 'both';
            }

            $target = isset($targets[$i]) ? sanitize_text_field($targets[$i]) : '_self';
            $target = ('_blank' === $target || 'new' === strtolower($target)) ? '_blank' : '_self';

            $items[] = array(
                'label' => $label,
                'url' => $url_input,
                'visibility' => $visibility,
                'target' => $target
            );
        }

        return $items;
    }

    private function extract_memberpress_product_id_from_shortcode($shortcode)
    {
        if (empty($shortcode)) {
            return 0;
        }

        $shortcode = stripslashes((string) $shortcode);
            if (preg_match('/\[mepr[-_]membership[-_]registration[-_]form[^\]]*\bid=[\'"]?(\d+)[\'"]?/i', $shortcode, $matches)) {
                return absint($matches[1]);
            }

        return 0;
    }

    private function sanitize_plan_rows($raw_rows)
    {
        $plans = array();
        if (empty($raw_rows) || !is_array($raw_rows)) {
            return $plans;
        }

        $names = isset($raw_rows['name']) ? (array) $raw_rows['name'] : array();
        $prices = isset($raw_rows['price']) ? (array) $raw_rows['price'] : array();
        $price_amounts = isset($raw_rows['price_amount']) ? (array) $raw_rows['price_amount'] : array();
        $annual_prices = isset($raw_rows['annual_price']) ? (array) $raw_rows['annual_price'] : array();
        $annual_price_amounts = isset($raw_rows['annual_price_amount']) ? (array) $raw_rows['annual_price_amount'] : array();
        $price_currencies = isset($raw_rows['price_currency']) ? (array) $raw_rows['price_currency'] : array();
        $matches_per_week = isset($raw_rows['matches_per_week']) ? (array) $raw_rows['matches_per_week'] : array();
        $billing_cycles = isset($raw_rows['billing_cycle']) ? (array) $raw_rows['billing_cycle'] : array();
        $annual_billing_cycles = isset($raw_rows['annual_billing_cycle']) ? (array) $raw_rows['annual_billing_cycle'] : array();
        $taglines = isset($raw_rows['tagline']) ? (array) $raw_rows['tagline'] : array();
        $audiences = isset($raw_rows['audience']) ? (array) $raw_rows['audience'] : array();
        $features = isset($raw_rows['features']) ? (array) $raw_rows['features'] : array();
        $urls = isset($raw_rows['mp_url']) ? (array) $raw_rows['mp_url'] : array();
        $shortcodes = isset($raw_rows['shortcode']) ? (array) $raw_rows['shortcode'] : array();
        $annual_urls = isset($raw_rows['annual_mp_url']) ? (array) $raw_rows['annual_mp_url'] : array();
        $annual_shortcodes = isset($raw_rows['annual_shortcode']) ? (array) $raw_rows['annual_shortcode'] : array();
        $featured_signup = isset($raw_rows['featured_signup']) ? (array) $raw_rows['featured_signup'] : array();
        $is_annual = isset($raw_rows['is_annual']) ? (array) $raw_rows['is_annual'] : array();
        $recruiter_contact_pricing = isset($raw_rows['recruiter_contact_pricing']) ? (array) $raw_rows['recruiter_contact_pricing'] : array();
        $signup_paths = isset($raw_rows['signup_path']) ? (array) $raw_rows['signup_path'] : array();
        $hero_eyebrows = isset($raw_rows['hero_eyebrow']) ? (array) $raw_rows['hero_eyebrow'] : array();
        $hero_titles = isset($raw_rows['hero_title']) ? (array) $raw_rows['hero_title'] : array();
        $hero_copies = isset($raw_rows['hero_copy']) ? (array) $raw_rows['hero_copy'] : array();
        $hero_image_urls = isset($raw_rows['hero_image_url']) ? (array) $raw_rows['hero_image_url'] : array();
        $hero_image_alts = isset($raw_rows['hero_image_alt']) ? (array) $raw_rows['hero_image_alt'] : array();
        $hero_cta_labels = isset($raw_rows['hero_cta_label']) ? (array) $raw_rows['hero_cta_label'] : array();
        $authority_titles = isset($raw_rows['authority_title']) ? (array) $raw_rows['authority_title'] : array();
        $authority_copies = isset($raw_rows['authority_copy']) ? (array) $raw_rows['authority_copy'] : array();
        $social_titles = isset($raw_rows['social_title']) ? (array) $raw_rows['social_title'] : array();
        $social_copies = isset($raw_rows['social_copy']) ? (array) $raw_rows['social_copy'] : array();
        $social_reviews = isset($raw_rows['social_reviews']) ? (array) $raw_rows['social_reviews'] : array();
        $social_review_scores = isset($raw_rows['social_review_score']) ? (array) $raw_rows['social_review_score'] : array();
        $social_review_counts = isset($raw_rows['social_review_count']) ? (array) $raw_rows['social_review_count'] : array();
        $free_titles = isset($raw_rows['free_title']) ? (array) $raw_rows['free_title'] : array();
        $free_copies = isset($raw_rows['free_copy']) ? (array) $raw_rows['free_copy'] : array();
        $category_titles = isset($raw_rows['category_title']) ? (array) $raw_rows['category_title'] : array();
        $category_copies = isset($raw_rows['category_copy']) ? (array) $raw_rows['category_copy'] : array();
        $scarcity_titles = isset($raw_rows['scarcity_title']) ? (array) $raw_rows['scarcity_title'] : array();
        $scarcity_copies = isset($raw_rows['scarcity_copy']) ? (array) $raw_rows['scarcity_copy'] : array();
        $now_titles = isset($raw_rows['now_title']) ? (array) $raw_rows['now_title'] : array();
        $now_copies = isset($raw_rows['now_copy']) ? (array) $raw_rows['now_copy'] : array();
        $other_plans_labels = isset($raw_rows['other_plans_label']) ? (array) $raw_rows['other_plans_label'] : array();
        $back_labels = isset($raw_rows['back_label']) ? (array) $raw_rows['back_label'] : array();

        $count = max(count($names), count($urls));
        for ($i = 0; $i < $count; $i++) {
            $name = isset($names[$i]) ? sanitize_text_field($names[$i]) : '';
            $mp_url = isset($urls[$i]) ? esc_url_raw($urls[$i]) : '';
            if ('' === $name || '' === $mp_url) {
                continue;
            }

            $slug = sanitize_title($name);

            $shortcode = isset($shortcodes[$i]) ? wp_unslash(sanitize_text_field($shortcodes[$i])) : '';
            $memberpress_product_id = $this->extract_memberpress_product_id_from_shortcode($shortcode);
            $annual_shortcode = isset($annual_shortcodes[$i]) ? wp_unslash(sanitize_text_field($annual_shortcodes[$i])) : '';
            $annual_memberpress_product_id = $this->extract_memberpress_product_id_from_shortcode($annual_shortcode);
            $signup_path_raw = strtolower(trim((string) ($signup_paths[$i] ?? 'platform')));
            $signup_path = 'platform';
            $signup_path_key = sanitize_key($signup_path_raw);
            if (in_array($signup_path_key, array('platform', 'mentorship', 'all_access', 'one_contact', 'extra_contacts', 'ongoing_contacts'), true)) {
                $signup_path = $signup_path_key;
            } elseif (preg_match('/\b(one contact|single contact|one recruiter contact)\b/', $signup_path_raw)) {
                $signup_path = 'one_contact';
            } elseif (preg_match('/\b(extra contacts|multiple contacts|more contacts)\b/', $signup_path_raw)) {
                $signup_path = 'extra_contacts';
            } elseif (preg_match('/\b(ongoing contacts|ongoing recruiter contacts|recruiter alerts|ongoing access)\b/', $signup_path_raw)) {
                $signup_path = 'ongoing_contacts';
            } elseif (
                preg_match('/\b(all access|premium|full access|everything)\b/', $signup_path_raw)
                || (
                    preg_match('/\b(recruiter|contact|intro|platform|access)\b/', $signup_path_raw)
                    && preg_match('/\b(cv|resume|linkedin|profile|review|mentorship|support)\b/', $signup_path_raw)
                )
            ) {
                $signup_path = 'all_access';
            } elseif (preg_match('/\b(profile positioning|cv positioning|cv|resume|linkedin|profile|review|mentorship|support)\b/', $signup_path_raw)) {
                $signup_path = 'mentorship';
            } elseif (preg_match('/\b(recruiter contacts?|recruiter|contacts?|intros?|platform|access only|basic)\b/', $signup_path_raw)) {
                $signup_path = 'platform';
            }

            $plan = array(
                'name' => $name,
                'price' => isset($prices[$i]) ? sanitize_text_field($prices[$i]) : '',
                'price_amount' => isset($price_amounts[$i]) ? floatval($price_amounts[$i]) : '',
                'annual_price' => isset($annual_prices[$i]) ? sanitize_text_field($annual_prices[$i]) : '',
                'annual_price_amount' => isset($annual_price_amounts[$i]) ? floatval($annual_price_amounts[$i]) : '',
                'price_currency' => isset($price_currencies[$i]) ? strtoupper(sanitize_text_field($price_currencies[$i])) : get_option('currency_detector_base_currency', 'USD'),
                'matches_per_week' => isset($matches_per_week[$i]) ? max(0, intval($matches_per_week[$i])) : 0,
                'billing_cycle' => isset($billing_cycles[$i]) ? sanitize_text_field($billing_cycles[$i]) : '',
                'annual_billing_cycle' => isset($annual_billing_cycles[$i]) ? sanitize_text_field($annual_billing_cycles[$i]) : '',
                'tagline' => isset($taglines[$i]) ? sanitize_text_field($taglines[$i]) : '',
                'audience' => isset($audiences[$i]) ? sanitize_text_field($audiences[$i]) : '',
                'mp_url' => $mp_url,
                'slug' => $slug,
                'shortcode' => $shortcode,
                'annual_mp_url' => isset($annual_urls[$i]) ? esc_url_raw($annual_urls[$i]) : '',
                'annual_shortcode' => $annual_shortcode,
                'memberpress_product_id' => $memberpress_product_id,
                'annual_memberpress_product_id' => $annual_memberpress_product_id,
                'featured_signup' => !empty($featured_signup[$i]) ? 1 : 0,
                'is_annual' => !empty($is_annual[$i]) ? 1 : 0,
                'recruiter_contact_pricing' => !empty($recruiter_contact_pricing[$i]) ? 1 : 0,
                'signup_path' => $signup_path,
                'hero_eyebrow' => isset($hero_eyebrows[$i]) ? sanitize_text_field($hero_eyebrows[$i]) : '',
                'hero_title' => isset($hero_titles[$i]) ? sanitize_text_field($hero_titles[$i]) : '',
                'hero_copy' => isset($hero_copies[$i]) ? sanitize_textarea_field($hero_copies[$i]) : '',
                'hero_image_url' => isset($hero_image_urls[$i]) ? esc_url_raw($hero_image_urls[$i]) : '',
                'hero_image_alt' => isset($hero_image_alts[$i]) ? sanitize_text_field($hero_image_alts[$i]) : '',
                'hero_cta_label' => isset($hero_cta_labels[$i]) ? sanitize_text_field($hero_cta_labels[$i]) : '',
                'authority_title' => isset($authority_titles[$i]) ? sanitize_text_field($authority_titles[$i]) : '',
                'authority_copy' => isset($authority_copies[$i]) ? sanitize_textarea_field($authority_copies[$i]) : '',
                'social_title' => isset($social_titles[$i]) ? sanitize_text_field($social_titles[$i]) : '',
                'social_copy' => isset($social_copies[$i]) ? sanitize_textarea_field($social_copies[$i]) : '',
                'social_review_score' => isset($social_review_scores[$i]) ? max(0, min(5, floatval($social_review_scores[$i]))) : '',
                'social_review_count' => isset($social_review_counts[$i]) ? max(0, intval($social_review_counts[$i])) : '',
                'social_reviews' => array(),
                'free_title' => isset($free_titles[$i]) ? sanitize_text_field($free_titles[$i]) : '',
                'free_copy' => isset($free_copies[$i]) ? sanitize_textarea_field($free_copies[$i]) : '',
                'category_title' => isset($category_titles[$i]) ? sanitize_text_field($category_titles[$i]) : '',
                'category_copy' => isset($category_copies[$i]) ? sanitize_textarea_field($category_copies[$i]) : '',
                'scarcity_title' => isset($scarcity_titles[$i]) ? sanitize_text_field($scarcity_titles[$i]) : '',
                'scarcity_copy' => isset($scarcity_copies[$i]) ? sanitize_textarea_field($scarcity_copies[$i]) : '',
                'now_title' => isset($now_titles[$i]) ? sanitize_text_field($now_titles[$i]) : '',
                'now_copy' => isset($now_copies[$i]) ? sanitize_textarea_field($now_copies[$i]) : '',
                'other_plans_label' => isset($other_plans_labels[$i]) ? sanitize_text_field($other_plans_labels[$i]) : '',
                'back_label' => isset($back_labels[$i]) ? sanitize_text_field($back_labels[$i]) : ''
            );

            $raw_features = isset($features[$i]) ? $features[$i] : '';
            if (is_string($raw_features)) {
                $raw_features = preg_split("/\r?\n/", $raw_features);
            }
            $plan['features'] = array();
            if (is_array($raw_features)) {
                foreach ($raw_features as $feature) {
                    $feature = trim(wp_strip_all_tags($feature));
                    if ($feature !== '') {
                        $plan['features'][] = $feature;
                    }
                }
            }

            $raw_social_reviews = isset($social_reviews[$i]) ? $social_reviews[$i] : '';
            if (is_string($raw_social_reviews)) {
                $raw_social_reviews = preg_split("/\r?\n/", $raw_social_reviews);
            }
            if (is_array($raw_social_reviews)) {
                foreach ($raw_social_reviews as $review) {
                    $review = trim(wp_strip_all_tags($review));
                    if ($review === '') {
                        continue;
                    }

                    $parts = array_map('trim', explode('|', $review));
                    $plan['social_reviews'][] = array(
                        'text' => isset($parts[0]) ? $parts[0] : '',
                        'first_name' => isset($parts[1]) ? sanitize_text_field($parts[1]) : '',
                        'last_name' => isset($parts[2]) ? sanitize_text_field($parts[2]) : '',
                        'rating' => isset($parts[3]) ? max(0, min(5, floatval($parts[3]))) : 5,
                    );
                }
            }

            $plans[] = $plan;
        }

        return $plans;
    }

    public function normalize_dashboard_plan_settings($plans)
    {
        if (!is_array($plans)) {
            return array();
        }

        foreach ($plans as $index => $plan) {
            if (!is_array($plan)) {
                unset($plans[$index]);
                continue;
            }

            $signup_path_raw = strtolower(trim((string) ($plan['signup_path'] ?? 'platform')));
            $signup_path = 'platform';
            $signup_path_key = sanitize_key($signup_path_raw);

            if (in_array($signup_path_key, array('platform', 'mentorship', 'all_access', 'one_contact', 'extra_contacts', 'ongoing_contacts'), true)) {
                $signup_path = $signup_path_key;
            } elseif (preg_match('/\b(one contact|single contact|one recruiter contact)\b/', $signup_path_raw)) {
                $signup_path = 'one_contact';
            } elseif (preg_match('/\b(extra contacts|multiple contacts|more contacts)\b/', $signup_path_raw)) {
                $signup_path = 'extra_contacts';
            } elseif (preg_match('/\b(ongoing contacts|ongoing recruiter contacts|recruiter alerts|ongoing access)\b/', $signup_path_raw)) {
                $signup_path = 'ongoing_contacts';
            } elseif (
                preg_match('/\b(all access|premium|full access|everything)\b/', $signup_path_raw)
                || (
                    preg_match('/\b(recruiter|contact|intro|platform|access)\b/', $signup_path_raw)
                    && preg_match('/\b(cv|resume|linkedin|profile|review|mentorship|support)\b/', $signup_path_raw)
                )
            ) {
                $signup_path = 'all_access';
            } elseif (preg_match('/\b(profile positioning|cv positioning|cv|resume|linkedin|profile|review|mentorship|support)\b/', $signup_path_raw)) {
                $signup_path = 'mentorship';
            } elseif (preg_match('/\b(recruiter contacts?|recruiter|contacts?|intros?|platform|access only|basic)\b/', $signup_path_raw)) {
                $signup_path = 'platform';
            }

            $plan['signup_path'] = $signup_path;
            $plan['featured_signup'] = !empty($plan['featured_signup']) ? 1 : 0;
            $plan['is_annual'] = !empty($plan['is_annual']) ? 1 : 0;
            $plan['recruiter_contact_pricing'] = !empty($plan['recruiter_contact_pricing']) ? 1 : 0;

            $plans[$index] = $plan;
        }

        return array_values($plans);
    }

    /**
     * AJAX: Register live chat post types
     */
    public function ajax_register_live_chat_post_types()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        if (!check_ajax_referer('sffc_admin_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }

        try {
            // Force register the conversation post type
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

            // Force register the message post type
            $message_result = register_post_type('sffc_chat_message', [
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

            $errors = [];
            if (is_wp_error($conversation_result)) {
                $errors[] = 'Conversation post type: ' . $conversation_result->get_error_message();
            }
            if (is_wp_error($message_result)) {
                $errors[] = 'Message post type: ' . $message_result->get_error_message();
            }

            if (!empty($errors)) {
                wp_send_json_error(['message' => 'Registration failed: ' . implode(', ', $errors)]);
            }

            // Verify registration
            $conversation_exists = post_type_exists('sffc_live_chat');
            $message_exists = post_type_exists('sffc_chat_message');

            if ($conversation_exists && $message_exists) {
                wp_send_json_success(['message' => 'Live chat post types registered successfully!']);
            } else {
                $missing = [];
                if (!$conversation_exists) $missing[] = 'sffc_live_chat';
                if (!$message_exists) $missing[] = 'sffc_chat_message';
                wp_send_json_error(['message' => 'Registration incomplete. Missing: ' . implode(', ', $missing)]);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Exception: ' . $e->getMessage()]);
        }
    }

    /**
     * AJAX: Verify live chat post types
     */
    public function ajax_verify_live_chat_post_types()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        if (!check_ajax_referer('sffc_admin_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }

        $results = [
            'sffc_live_chat' => post_type_exists('sffc_live_chat'),
            'sffc_chat_message' => post_type_exists('sffc_chat_message')
        ];

        $all_exist = array_reduce($results, function ($carry, $exists) {
            return $carry && $exists;
        }, true);

        if ($all_exist) {
            wp_send_json_success([
                'message' => 'All live chat post types are properly registered!',
                'results' => $results
            ]);
        } else {
            $missing = array_keys(array_filter($results, function ($exists) {
                return !$exists;
            }));

            wp_send_json_success([
                'message' => 'Some post types are missing: ' . implode(', ', $missing),
                'results' => $results
            ]);
        }
    }
}

// Initialize plugin with proper timing to avoid WordPress 6.7 warnings
// AJAX handlers need early registration but we'll handle textdomain separately
function senna_initialize_plugin()
{
    // Initialize main plugin instance
    Senna_Careers::get_instance();

    // Initialize Matcher-Messaging Integration
    if (file_exists(plugin_dir_path(__FILE__) . 'includes/class-matcher-messaging-integration.php')) {
        require_once plugin_dir_path(__FILE__) . 'includes/class-matcher-messaging-integration.php';
        SFFC_Matcher_Messaging_Integration::get_instance();
    }
}

// For AJAX requests, initialize immediately
if (wp_doing_ajax()) {
    senna_initialize_plugin();
} else {
    // For regular requests, wait for plugins_loaded
    add_action('plugins_loaded', 'senna_initialize_plugin', 1);
}

if (!function_exists('sffc_get_password_set_link')) {
    /**
     * Generate branded password reset links without regenerating keys.
     */
    function sffc_get_password_set_link($user, $context = 'crm')
    {
        static $cache = [];

        if (!$user instanceof WP_User) {
            return '';
        }

        $context = ($context === 'wp') ? 'wp' : 'crm';
        $cache_key = $user->ID;

        if (!isset($cache[$cache_key])) {
            if (!function_exists('get_password_reset_key')) {
                require_once ABSPATH . 'wp-includes/pluggable.php';
            }

            $reset_key = get_password_reset_key($user);
            if (is_wp_error($reset_key)) {
                $cache[$cache_key] = ['crm' => '', 'wp' => ''];
            } else {
                $crm_url = add_query_arg(
                    [
                        'tab' => 'account',
                        'key' => $reset_key,
                        'login' => $user->user_login,
                    ],
                    home_url('/terminal/')
                );

                $cache[$cache_key] = ['crm' => $crm_url, 'wp' => $crm_url];
            }
        }

        return $cache[$cache_key][$context] ?? '';
    }
}

if (!function_exists('sffc_customize_new_user_notification_email')) {
    /**
     * Replace the default plain-text new user email with a modern HTML welcome message.
     */
    function sffc_customize_new_user_notification_email($wp_new_user_notification_email, $user, $blogname)
    {
        if (!$user instanceof WP_User) {
            return $wp_new_user_notification_email;
        }

        $set_password_url = sffc_get_password_set_link($user, 'crm');
        if (!$set_password_url) {
            return $wp_new_user_notification_email;
        }

        $site_name = $blogname ?: get_bloginfo('name');
        $first_name = trim($user->first_name);
        if (!$first_name) {
            $first_name = trim($user->display_name);
        }
        if (!$first_name) {
            $first_name = $user->user_login;
        }

        $crm_account_url = home_url('/terminal/');
        $preview = sprintf(__('Welcome to %s - set your password to access finance jobs and HR contact lists.', 'senna-finance'), $site_name);
        $subject = sprintf(__('Welcome to %s - Activate Your Finance Careers Access', 'senna-finance'), $site_name);

        ob_start();
    ?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo esc_html($subject); ?></title>
        </head>

        <body style="margin:0;padding:32px;background:#f5f6f8;color:#0f172a;font-family:-apple-system,BlinkMacSystemFont,'Inter','Helvetica Neue',Helvetica,Arial,sans-serif;">
            <span style="display:none!important;opacity:0;color:transparent;height:0;width:0;overflow:hidden;visibility:hidden;"><?php echo esc_html($preview); ?></span>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:700px;margin:0 auto;border-spacing:0;">
                <tr>
                    <td align="center" style="padding:0;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-spacing:0;background:#ffffff;border-radius:18px;box-shadow:0 15px 60px rgba(15,23,42,0.08);overflow:hidden;">
                            <tr>
                                <td style="padding:40px 48px 32px;text-align:left;">
                                    <div style="text-transform:uppercase;font-size:12px;letter-spacing:0.2em;color:#94a3b8;margin-bottom:16px;">MENA Careers</div>
                                    <h1 style="margin:0 0 16px;font-size:28px;line-height:1.25;color:#0f172a;">Set your password to unlock finance roles and HR contact lists</h1>
                                    <p style="margin:0 0 12px;font-size:16px;line-height:1.6;color:#1f2933;">Hi <?php echo esc_html($first_name); ?>,</p>
                                    <p style="margin:0 0 24px;font-size:16px;line-height:1.6;color:#475467;">Your MENA Careers account is ready. Finish setting your password to access curated finance jobs, finance leadership lists, investment banking opportunities, asset management roles, and HR contacts you can use in your search.</p>
                                    <p style="margin:0 0 32px;text-align:center;">
                                        <a href="<?php echo esc_url($set_password_url); ?>" style="display:inline-block;padding:16px 32px;background:#0d353e;color:#ffffff;font-weight:600;font-size:16px;border-radius:999px;text-decoration:none;">Set Your Password</a>
                                    </p>
                                    <p style="margin:0 0 24px;font-size:14px;color:#475467;text-align:center;">
                                        <a href="<?php echo esc_url($crm_account_url); ?>" style="color:#0d353e;font-weight:600;text-decoration:none;">Open MENA Careers</a>
                                        <span style="color:#94a3b8;">· Latest Lists shows finance roles and contact-led job lists</span>
                                    </p>
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-spacing:0;background:#f6f9fb;border:1px solid #e5edf2;border-radius:14px;margin-bottom:32px;">
                                        <tr>
                                            <td style="padding:20px 24px;">
                                                <p style="margin:0 0 12px;font-size:15px;font-weight:600;color:#0f172a;">Inside MENA Careers you can:</p>
                                                <ul style="margin:0;padding-left:18px;color:#475467;font-size:14px;line-height:1.6;">
                                                    <li>Browse curated roles across investment banking, corporate finance, asset management, and senior finance</li>
                                                    <li>Download role lists with recruiter and HR contact details where available</li>
                                                    <li>Track applications, save target lists, and keep your outreach organized</li>
                                                </ul>
                                            </td>
                                        </tr>
                                    </table>
                                    <p style="margin:0 0 8px;font-size:14px;color:#475467;">Having trouble with the button? Copy and paste this link into your browser:</p>
                                    <p style="margin:0 0 24px;font-size:14px;color:#0d353e;word-break:break-all;">
                                        <a href="<?php echo esc_url($set_password_url); ?>" style="color:#0d353e;text-decoration:none;"><?php echo esc_html($set_password_url); ?></a>
                                    </p>
                                    <p style="margin:0;font-size:14px;color:#94a3b8;">Need help? Reply to this email or contact <a href="mailto:support.team@joinsenna.com" style="color:#0d353e;text-decoration:none;font-weight:600;">support.team@joinsenna.com</a>.</p>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:24px 32px;background:#0d353e;color:#d9f2ee;text-align:center;font-size:13px;">
                                    <?php echo esc_html($site_name); ?> · Top finance jobs, HR contact lists, and application tracking
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>

        </html>
<?php
        $html = ob_get_clean();

        $wp_new_user_notification_email['subject'] = $subject;
        $wp_new_user_notification_email['headers'] = array('Content-Type: text/html; charset=UTF-8');
        $wp_new_user_notification_email['message'] = $html;

        return $wp_new_user_notification_email;
    }
}

add_filter('wp_new_user_notification_email', 'sffc_customize_new_user_notification_email', 10, 3);

if (!function_exists('sffc_redirect_wp_password_reset_to_terminal')) {
    /**
     * Send native WordPress reset links to the branded MENA Careers password form.
     */
    function sffc_redirect_wp_password_reset_to_terminal()
    {
        $action = isset($_GET['action']) ? sanitize_key(wp_unslash((string) $_GET['action'])) : '';
        if (!in_array($action, ['rp', 'resetpass'], true)) {
            return;
        }

        $key = isset($_GET['key']) ? sanitize_text_field(wp_unslash((string) $_GET['key'])) : '';
        $login = isset($_GET['login']) ? sanitize_user(wp_unslash((string) $_GET['login']), true) : '';
        if ($key === '' || $login === '') {
            return;
        }

        wp_safe_redirect(add_query_arg(
            [
                'tab' => 'account',
                'key' => $key,
                'login' => $login,
            ],
            home_url('/terminal/')
        ));
        exit;
    }
}

add_action('login_init', 'sffc_redirect_wp_password_reset_to_terminal');

if (!function_exists('sffc_crm_handle_set_password')) {
    /**
     * AJAX handler to set a password via CRM Account tab.
     */
    function sffc_crm_handle_set_password()
    {
        if (!check_ajax_referer('sffc_crm_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Invalid security token. Please refresh the page and try again.', 'senna-finance')], 403);
        }

        $login = isset($_POST['login']) ? sanitize_user(wp_unslash($_POST['login']), true) : '';
        $key = isset($_POST['key']) ? sanitize_text_field(wp_unslash($_POST['key'])) : '';
        $password = isset($_POST['password']) ? (string) wp_unslash($_POST['password']) : '';
        $confirm = isset($_POST['confirm_password']) ? (string) wp_unslash($_POST['confirm_password']) : '';

        if (empty($login) || empty($key)) {
            wp_send_json_error(['message' => __('Reset link missing required information. Request a new email and try again.', 'senna-finance')], 400);
        }

        if (empty($password) || empty($confirm)) {
            wp_send_json_error(['message' => __('Enter and confirm your new password.', 'senna-finance')], 400);
        }

        if ($password !== $confirm) {
            wp_send_json_error(['message' => __('Passwords do not match. Please try again.', 'senna-finance')], 400);
        }

        if (strlen($password) < 8) {
            wp_send_json_error(['message' => __('Please choose a password with at least 8 characters.', 'senna-finance')], 400);
        }

        if (!function_exists('check_password_reset_key')) {
            require_once ABSPATH . 'wp-includes/pluggable.php';
        }

        $user = check_password_reset_key($key, $login);
        if (is_wp_error($user)) {
            wp_send_json_error(['message' => $user->get_error_message() ?: __('This reset link is no longer valid. Request a new one.', 'senna-finance')], 400);
        }

        reset_password($user, $password);
        wp_set_current_user((int) $user->ID);
        wp_set_auth_cookie((int) $user->ID, true);
        do_action('wp_login', $user->user_login, $user);

        wp_send_json_success([
            'message' => __('Password updated. Opening MENA Careers now.', 'senna-finance'),
            'redirect' => home_url('/terminal/'),
        ]);
    }
}

add_action('wp_ajax_sffc_crm_set_password', 'sffc_crm_handle_set_password');
add_action('wp_ajax_nopriv_sffc_crm_set_password', 'sffc_crm_handle_set_password');

/**
 * Redirect users to terminal dashboard after login
 */
function sffc_login_redirect($redirect_to, $request, $user)
{
    // Only redirect if no specific redirect is requested
    if (empty($request) || $request === '' || strpos($request, 'wp-admin') === false) {
        return home_url('/terminal/?tab=dashboard');
    }
    return $redirect_to;
}
add_filter('login_redirect', 'sffc_login_redirect', 10, 3);
