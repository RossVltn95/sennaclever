<?php
/**
 * Application Planner - Main Controller
 *
 * Provides the [sffc_application_planner] shortcode for CV-to-JD gap analysis.
 * Uses Claude API for intelligent analysis with rule-based fallback.
 *
 * @package SennaCareers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Application_Planner {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Dependencies
     */
    private $jd_parser;
    private $cv_parser;
    private $gap_analyzer;
    private $report_generator;

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
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required classes
     */
    private function load_dependencies() {
        $base_path = dirname(__FILE__);

        require_once $base_path . '/class-knowledge-base.php';
        require_once $base_path . '/class-jd-parser.php';
        require_once $base_path . '/class-cv-parser.php';
        require_once $base_path . '/class-ai-analyzer.php';
        require_once $base_path . '/class-fallback-analyzer.php';
        require_once $base_path . '/class-gap-analyzer.php';
        require_once $base_path . '/class-report-generator.php';
        require_once $base_path . '/class-ats-analyzer.php';
        require_once $base_path . '/class-multi-job-comparison.php';
        require_once $base_path . '/class-pdf-exporter.php';

        // Initialize dependencies
        $this->jd_parser = new SFFC_JD_Parser();
        $this->cv_parser = new SFFC_Planner_CV_Parser();
        $this->gap_analyzer = new SFFC_Gap_Analyzer();
        $this->report_generator = new SFFC_Report_Generator();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Shortcode
        add_shortcode('sffc_application_planner', [$this, 'render_shortcode']);

        // Assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        // AJAX handlers
        add_action('wp_ajax_sffc_analyze_application', [$this, 'ajax_analyze']);
        add_action('wp_ajax_nopriv_sffc_analyze_application', [$this, 'ajax_analyze']);
        add_action('wp_ajax_sffc_save_planner_report', [$this, 'ajax_save_report']);
        add_action('wp_ajax_sffc_get_planner_reports', [$this, 'ajax_get_reports']);
        add_action('wp_ajax_sffc_get_planner_report', [$this, 'ajax_get_report']);
        add_action('wp_ajax_sffc_delete_planner_report', [$this, 'ajax_delete_report']);
        add_action('wp_ajax_sffc_export_planner_pdf', [$this, 'ajax_export_pdf']);
        add_action('wp_ajax_sffc_get_crm_jobs_for_comparison', [$this, 'ajax_get_crm_jobs']);
        add_action('wp_ajax_sffc_compare_multiple_jobs', [$this, 'ajax_compare_jobs']);

        // Create database table on activation
        register_activation_hook(SFFC_PLUGIN_FILE, [$this, 'create_tables']);
    }

    /**
     * Render standalone shortcode
     */
    public function render_shortcode($atts) {
        $atts = shortcode_atts([
            'show_jd_input' => 'true',
            'job_id' => '',
            'mode' => 'standalone', // standalone | embedded | comparison
        ], $atts);

        // Enqueue assets
        $this->enqueue_assets(true);

        ob_start();
        include SFFC_PLUGIN_DIR . 'templates/application-planner.php';
        return ob_get_clean();
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_assets($force = false) {
        // Only load on pages with shortcode
        global $post;
        if (!$force && (!$post || !has_shortcode($post->post_content, 'sffc_application_planner'))) {
            return;
        }

        // CSS - First ensure institutional article is loaded
        wp_enqueue_style(
            'inst-article',
            SFFC_PLUGIN_URL . 'assets/css/institutional-article.css',
            [],
            SFFC_VERSION
        );

        wp_enqueue_style(
            'sffc-application-planner',
            SFFC_PLUGIN_URL . 'assets/css/application-planner.css',
            ['inst-article'], // Inherit from institutional article
            SFFC_VERSION
        );

        // PDF.js for PDF parsing
        wp_enqueue_script(
            'pdfjs',
            'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js',
            [],
            '3.11.174',
            true
        );

        // Mammoth.js for DOCX parsing
        wp_enqueue_script(
            'mammoth',
            'https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.6.0/mammoth.browser.min.js',
            [],
            '1.6.0',
            true
        );

        // html2pdf for PDF export
        wp_enqueue_script(
            'html2pdf',
            'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js',
            [],
            '0.10.1',
            true
        );

        // Chart.js for visualizations
        wp_enqueue_script(
            'chartjs',
            'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js',
            [],
            '4.4.1',
            true
        );

        // Main JS
        wp_enqueue_script(
            'sffc-application-planner',
            SFFC_PLUGIN_URL . 'assets/js/application-planner.js',
            ['jquery', 'pdfjs', 'mammoth', 'html2pdf', 'chartjs'],
            SFFC_VERSION,
            true
        );

        // Localize script
        wp_localize_script('sffc-application-planner', 'sffc_planner', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sffc_planner_nonce'),
            'user_id' => get_current_user_id(),
            'is_logged_in' => is_user_logged_in(),
            'i18n' => [
                'analyzing' => __('Analyzing your application...', 'senna-careers'),
                'parsing_jd' => __('Parsing job description...', 'senna-careers'),
                'parsing_cv' => __('Analyzing your CV...', 'senna-careers'),
                'identifying_gaps' => __('Identifying gaps...', 'senna-careers'),
                'generating_report' => __('Generating recommendations...', 'senna-careers'),
                'error_generic' => __('Analysis failed. Please try again.', 'senna-careers'),
                'error_jd_short' => __('Job description is too short. Please provide more details.', 'senna-careers'),
                'error_cv_short' => __('CV text is too short. Please provide your full CV.', 'senna-careers'),
                'upload_pdf' => __('Upload PDF', 'senna-careers'),
                'upload_docx' => __('Upload DOCX', 'senna-careers'),
                'paste_text' => __('Or paste text', 'senna-careers'),
            ],
        ]);
    }

    /**
     * AJAX: Analyze application
     */
    public function ajax_analyze() {
        check_ajax_referer('sffc_planner_nonce', 'nonce');

        $jd_text = sanitize_textarea_field($_POST['jd_text'] ?? '');
        $cv_text = sanitize_textarea_field($_POST['cv_text'] ?? '');

        // Validate inputs
        if (strlen($jd_text) < 100) {
            wp_send_json_error([
                'message' => __('Job description is too short. Please provide at least 100 characters.', 'senna-careers'),
                'code' => 'jd_too_short'
            ]);
        }

        if (strlen($cv_text) < 100) {
            wp_send_json_error([
                'message' => __('CV text is too short. Please provide at least 100 characters.', 'senna-careers'),
                'code' => 'cv_too_short'
            ]);
        }

        // Rate limiting
        $rate_check = $this->check_rate_limit();
        if (is_wp_error($rate_check)) {
            wp_send_json_error([
                'message' => $rate_check->get_error_message(),
                'code' => 'rate_limit'
            ]);
        }

        try {
            // Run analysis - returns structure expected by frontend
            $result = $this->gap_analyzer->analyze($jd_text, $cv_text);

            // Save report if user is logged in
            if (is_user_logged_in()) {
                $result['meta']['report_id'] = $this->save_analysis($jd_text, $cv_text, $result);
            }

            wp_send_json_success($result);

        } catch (Exception $e) {
            error_log('Application Planner Error: ' . $e->getMessage());
            wp_send_json_error([
                'message' => __('Analysis failed. Please try again.', 'senna-careers'),
                'code' => 'analysis_error'
            ]);
        }
    }

    /**
     * Check rate limiting
     */
    private function check_rate_limit() {
        $user_key = get_current_user_id() ?: $_SERVER['REMOTE_ADDR'];
        $minute_key = 'sffc_planner_rate_min_' . md5($user_key);
        $day_key = 'sffc_planner_rate_day_' . md5($user_key) . '_' . date('Y-m-d');

        // Per-minute limit (10)
        $minute_count = get_transient($minute_key) ?: 0;
        if ($minute_count >= 10) {
            return new WP_Error('rate_limit', __('Too many requests. Please wait a moment.', 'senna-careers'));
        }

        // Per-day limit (100)
        $day_count = get_transient($day_key) ?: 0;
        if ($day_count >= 100) {
            return new WP_Error('rate_limit', __('Daily limit reached. Try again tomorrow.', 'senna-careers'));
        }

        // Increment counters
        set_transient($minute_key, $minute_count + 1, 60);
        set_transient($day_key, $day_count + 1, DAY_IN_SECONDS);

        return true;
    }

    /**
     * Save analysis to database
     */
    private function save_analysis($jd_text, $cv_text, $analysis) {
        global $wpdb;

        $table = $wpdb->prefix . 'sffc_application_reports';

        // Ensure table exists
        $this->maybe_create_table();

        $wpdb->insert($table, [
            'user_id' => get_current_user_id(),
            'post_id' => isset($_POST['post_id']) ? intval($_POST['post_id']) : null,
            'jd_hash' => md5($jd_text),
            'cv_hash' => md5($cv_text),
            'jd_text' => $jd_text,
            'cv_text' => $cv_text,
            'analysis_source' => $analysis['meta']['analysis_source'] ?? 'fallback',
            'score' => $analysis['score'] ?? 0,
            'risk_level' => $analysis['risk_level'] ?? 'unknown',
            'report_data' => wp_json_encode($analysis),
            'created_at' => current_time('mysql'),
        ]);

        return $wpdb->insert_id;
    }

    /**
     * AJAX: Save report
     */
    public function ajax_save_report() {
        check_ajax_referer('sffc_planner_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Please log in to save reports.', 'senna-careers')]);
        }

        $analysis = json_decode(stripslashes($_POST['analysis'] ?? ''), true);

        if (empty($analysis)) {
            wp_send_json_error(['message' => __('Invalid analysis data.', 'senna-careers')]);
        }

        $pdf_exporter = new SFFC_PDF_Exporter();
        $report_id = $pdf_exporter->save_report(get_current_user_id(), $analysis);

        if ($report_id) {
            wp_send_json_success(['report_id' => $report_id]);
        } else {
            wp_send_json_error(['message' => __('Failed to save report.', 'senna-careers')]);
        }
    }

    /**
     * AJAX: Get saved reports
     */
    public function ajax_get_reports() {
        check_ajax_referer('sffc_planner_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Please log in to view reports.', 'senna-careers')]);
        }

        $pdf_exporter = new SFFC_PDF_Exporter();
        $reports = $pdf_exporter->get_user_reports(get_current_user_id(), 20);

        wp_send_json_success(['reports' => $reports]);
    }

    /**
     * AJAX: Get single report
     */
    public function ajax_get_report() {
        check_ajax_referer('sffc_planner_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Please log in to view reports.', 'senna-careers')]);
        }

        $report_id = intval($_POST['report_id'] ?? 0);

        if (!$report_id) {
            wp_send_json_error(['message' => __('Invalid report ID.', 'senna-careers')]);
        }

        $pdf_exporter = new SFFC_PDF_Exporter();
        $report = $pdf_exporter->get_report($report_id, get_current_user_id());

        if (!$report) {
            wp_send_json_error(['message' => __('Report not found.', 'senna-careers')]);
        }

        wp_send_json_success($report);
    }

    /**
     * AJAX: Delete report
     */
    public function ajax_delete_report() {
        check_ajax_referer('sffc_planner_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Please log in to delete reports.', 'senna-careers')]);
        }

        $report_id = intval($_POST['report_id'] ?? 0);

        if (!$report_id) {
            wp_send_json_error(['message' => __('Invalid report ID.', 'senna-careers')]);
        }

        $pdf_exporter = new SFFC_PDF_Exporter();
        $result = $pdf_exporter->delete_report($report_id, get_current_user_id());

        if ($result) {
            wp_send_json_success(['message' => __('Report deleted.', 'senna-careers')]);
        } else {
            wp_send_json_error(['message' => __('Failed to delete report.', 'senna-careers')]);
        }
    }

    /**
     * AJAX: Export PDF
     */
    public function ajax_export_pdf() {
        check_ajax_referer('sffc_planner_nonce', 'nonce');

        $analysis = json_decode(stripslashes($_POST['analysis'] ?? ''), true);

        if (empty($analysis)) {
            wp_send_json_error(['message' => __('Invalid analysis data.', 'senna-careers')]);
        }

        $exporter = new SFFC_PDF_Exporter();

        // Generate HTML for client-side PDF rendering
        $html = $exporter->generate_html($analysis);
        $filename = $exporter->generate_filename($analysis);

        wp_send_json_success([
            'html' => $html,
            'filename' => $filename
        ]);
    }

    /**
     * AJAX: Get CRM jobs for comparison
     */
    public function ajax_get_crm_jobs() {
        check_ajax_referer('sffc_planner_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Please log in to access your jobs.', 'senna-careers')]);
        }

        $comparison = new SFFC_Multi_Job_Comparison();
        $result = $comparison->get_crm_jobs(get_current_user_id());

        if ($result['success']) {
            wp_send_json_success(['jobs' => $result['jobs']]);
        } else {
            wp_send_json_error(['message' => $result['error'] ?? 'Failed to load jobs']);
        }
    }

    /**
     * AJAX: Compare multiple jobs
     */
    public function ajax_compare_jobs() {
        check_ajax_referer('sffc_planner_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Please log in to compare jobs.', 'senna-careers')]);
        }

        $cv_text = sanitize_textarea_field($_POST['cv_text'] ?? '');
        $jobs = json_decode(stripslashes($_POST['jobs'] ?? '[]'), true);

        if (strlen($cv_text) < 100) {
            wp_send_json_error(['message' => __('CV text is too short.', 'senna-careers')]);
        }

        if (empty($jobs) || count($jobs) > 5) {
            wp_send_json_error(['message' => __('Please select 1-5 jobs to compare.', 'senna-careers')]);
        }

        $comparison = new SFFC_Multi_Job_Comparison();
        $results = $comparison->compare($cv_text, $jobs);

        wp_send_json_success($results);
    }

    /**
     * Check and create table if needed (called on first use)
     */
    private function maybe_create_table() {
        global $wpdb;

        $table = $wpdb->prefix . 'sffc_application_reports';

        // Check if table exists using a transient to avoid repeated checks
        $table_exists = get_transient('sffc_application_reports_exists');

        if ($table_exists) {
            return;
        }

        // Check if table actually exists
        $table_check = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");

        if ($table_check === $table) {
            set_transient('sffc_application_reports_exists', true, DAY_IN_SECONDS);
            return;
        }

        // Table doesn't exist, create it
        $this->create_tables();
        set_transient('sffc_application_reports_exists', true, DAY_IN_SECONDS);
    }

    /**
     * Create database tables
     */
    public function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table = $wpdb->prefix . 'sffc_application_reports';

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED DEFAULT NULL,
            post_id BIGINT UNSIGNED DEFAULT NULL,
            jd_hash VARCHAR(64) NOT NULL,
            cv_hash VARCHAR(64) NOT NULL,
            jd_text LONGTEXT NOT NULL,
            cv_text LONGTEXT NOT NULL,
            analysis_source ENUM('ai', 'fallback') NOT NULL DEFAULT 'fallback',
            score TINYINT UNSIGNED NOT NULL DEFAULT 0,
            risk_level VARCHAR(20) NOT NULL DEFAULT 'unknown',
            report_data JSON NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_post_id (post_id),
            INDEX idx_jd_hash (jd_hash),
            INDEX idx_created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}

// Initialize
SFFC_Application_Planner::get_instance();
