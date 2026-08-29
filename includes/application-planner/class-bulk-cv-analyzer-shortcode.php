<?php
/**
 * Bulk CV Analyzer Shortcode
 *
 * Provides a bulk CV screening tool for recruiters.
 * Upload multiple CVs and rank candidates by job fit.
 *
 * @package SennaCareers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Bulk_CV_Analyzer_Shortcode {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode('sffc_bulk_cv_analyzer', array($this, 'render'));
        add_action('wp_enqueue_scripts', array($this, 'maybe_enqueue_assets'));
        add_action('wp_ajax_sffc_bulk_analyze_cvs', array($this, 'ajax_bulk_analyze'));
        add_action('wp_ajax_sffc_generate_candidate_summaries', array($this, 'ajax_generate_summaries'));
        add_action('wp_ajax_sffc_export_shortlist_pdf', array($this, 'ajax_export_pdf'));
    }

    public function render($atts) {
        $atts = shortcode_atts(array(
            'show_export' => 'true',
        ), $atts);

        $this->enqueue_assets();

        ob_start();
        $this->render_template($atts);
        return ob_get_clean();
    }

    private function render_template($atts) {
        $show_export = filter_var($atts['show_export'], FILTER_VALIDATE_BOOLEAN);
        ?>
        <div class="inst-terminal" data-component="bulk-cv-analyzer">

            <!-- Icon Sidebar -->
            <aside class="inst-sidebar">
                <svg class="inst-sidebar-logo" viewBox="0 0 28 28" fill="none">
                    <path d="M5 9c0-3 2.5-5.5 5.5-5.5h5c2.5 0 4.5 2 4.5 4.5 0 2-1.3 3.6-3.1 4.2-.2.1-.2.4 0 .5 1.8.6 3.1 2.2 3.1 4.2 0 2.5-2 4.5-4.5 4.5h-5C7.5 21.5 5 19 5 16" stroke="#0f5132" stroke-width="2.5" stroke-linecap="round" fill="none"/>
                    <circle cx="24" cy="5" r="3" fill="#0f5132"/>
                </svg>

                <div class="inst-sidebar-actions">
                    <button class="inst-sidebar-btn" data-action="analyze" data-tooltip="Analyze All">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                        </svg>
                    </button>

                    <button class="inst-sidebar-btn" data-action="reset" data-tooltip="Start Over">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="1 4 1 10 7 10"/>
                            <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/>
                        </svg>
                    </button>

                    <?php if ($show_export) : ?>
                    <button class="inst-sidebar-btn" data-action="export" data-tooltip="Export CSV">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                            <line x1="12" y1="18" x2="12" y2="12"/>
                            <polyline points="9 15 12 18 15 15"/>
                        </svg>
                    </button>
                    <?php endif; ?>
                </div>
            </aside>

            <!-- Main Content Area -->
            <div class="inst-main">

                <!-- Input Panel (Left) -->
                <div class="inst-article-panel">

                    <!-- Sticky Header -->
                    <header class="inst-article-header">
                        <span class="inst-article-header-title">Bulk CV Screener</span>
                        <div class="inst-article-header-meta">
                            <span>Recruiter Tool</span>
                        </div>
                    </header>

                    <!-- Article Content -->
                    <article class="inst-article-content">
                        <div class="inst-article-inner">

                            <div class="inst-article-eyebrow-row">
                                <span class="inst-article-type-badge">Bulk Analyzer</span>
                                <span class="inst-article-category">Candidate Screening</span>
                            </div>

                            <h1 class="inst-article-title">Bulk CV Screening</h1>
                            <p class="inst-article-subtitle">Upload multiple CVs and get instant rankings showing which candidates best match your job requirements</p>

                            <!-- Job Description Section -->
                            <details class="inst-takeaways-enhanced" open>
                                <summary class="inst-takeaways-header">
                                    <h3 class="inst-takeaways-label">Job Description</h3>
                                    <span class="inst-takeaways-count">Step 1</span>
                                </summary>
                                <div class="inst-article-body">
                                    <textarea
                                        class="inst-gap-textarea"
                                        data-input="jd"
                                        placeholder="Paste the full job description here...

Include:
• Role title and company
• Required qualifications
• Preferred skills
• Responsibilities
• Experience requirements"
                                        rows="12"
                                    ></textarea>
                                </div>
                            </details>

                            <!-- CV Upload Section -->
                            <details class="inst-takeaways-enhanced" open>
                                <summary class="inst-takeaways-header">
                                    <h3 class="inst-takeaways-label">Upload Candidate CVs</h3>
                                    <span class="inst-takeaways-count" data-cv-count>Step 2</span>
                                </summary>
                                <div class="inst-article-body">

                                    <!-- File Upload Zone -->
                                    <div class="bulk-cv-upload-zone" data-upload-zone>
                                        <div class="bulk-cv-upload-content">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                                <polyline points="17 8 12 3 7 8"/>
                                                <line x1="12" y1="3" x2="12" y2="15"/>
                                            </svg>
                                            <h4>Select Multiple CV Files</h4>
                                            <p>Choose PDF, DOCX, or TXT files</p>
                                            <p class="bulk-cv-upload-hint">Text will be extracted automatically from all files</p>
                                            <button type="button" class="bulk-cv-upload-btn" data-trigger-upload>
                                                Choose Files
                                            </button>
                                            <input
                                                type="file"
                                                class="bulk-cv-upload-input"
                                                data-file-input
                                                multiple
                                                accept=".pdf,.doc,.docx,.txt,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain"
                                                style="display: none;"
                                            />
                                        </div>
                                    </div>

                                    <!-- Uploaded Files List -->
                                    <div class="bulk-cv-files-list" data-files-list style="display: none;">
                                        <div class="bulk-cv-files-header">
                                            <h4>Uploaded CVs (<span data-file-count>0</span>)</h4>
                                            <button type="button" class="bulk-cv-clear-btn" data-clear-files>Clear All</button>
                                        </div>
                                        <div class="bulk-cv-files-items" data-files-items>
                                            <!-- Files will be listed here -->
                                        </div>
                                    </div>

                                </div>
                            </details>

                            <!-- Analyze Button -->
                            <div class="inst-executive-summary" style="cursor: pointer;" data-action="analyze">
                                <div class="inst-exec-header">
                                    <h2 class="inst-exec-title">Ready to Screen Candidates</h2>
                                    <span class="inst-exec-methodology">Click to Analyze</span>
                                </div>
                                <p class="inst-exec-thesis" data-analyze-message>Paste your job description and upload CV files above, then click here to rank all candidates by match quality.</p>
                            </div>

                        </div>
                    </article>

                </div>

                <!-- Resize Handle -->
                <div class="inst-resize-handle" title="Drag to resize"></div>

                <!-- Results Panel (Right) -->
                <div class="inst-charts-panel">
                    <div class="inst-charts-inner">

                        <!-- Loading Overlay -->
                        <div class="inst-analysis-loader" data-loader="bulk-analysis" style="display: none;">
                            <div class="inst-loader-content">
                                <div class="inst-loader-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10" stroke-opacity="0.2"/>
                                        <path d="M12 2a10 10 0 0 1 10 10" class="inst-loader-spinner"/>
                                    </svg>
                                </div>
                                <div class="inst-loader-percentage" data-loader-percent>0%</div>
                                <div class="inst-loader-status" data-loader-status>Analyzing candidates...</div>
                                <div class="inst-loader-bar">
                                    <div class="inst-loader-bar-fill" data-loader-bar></div>
                                </div>
                            </div>
                        </div>

                        <!-- Results Header -->
                        <div class="inst-charts-header">
                            <div class="inst-charts-header-row">
                                <div class="inst-charts-header-text">
                                    <h2 class="inst-charts-title">Ranked Candidates</h2>
                                    <p class="inst-charts-subtitle">Sorted by match score</p>
                                </div>
                                <?php if ($show_export) : ?>
                                <button type="button" class="inst-pdf-download-btn" data-action="export" title="Export as CSV" style="display: none;">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                        <polyline points="14 2 14 8 20 8"/>
                                        <line x1="12" y1="18" x2="12" y2="12"/>
                                        <polyline points="9 15 12 18 15 15"/>
                                    </svg>
                                    <span>Export</span>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="inst-charts-divider"></div>

                        <!-- Stats Summary -->
                        <div class="bulk-stats-summary" data-stats-summary style="display: none;">
                            <div class="bulk-stat">
                                <div class="bulk-stat-value" data-stat="strong">0</div>
                                <div class="bulk-stat-label">Strong Fit</div>
                            </div>
                            <div class="bulk-stat">
                                <div class="bulk-stat-value" data-stat="competitive">0</div>
                                <div class="bulk-stat-label">Competitive</div>
                            </div>
                            <div class="bulk-stat">
                                <div class="bulk-stat-value" data-stat="moderate">0</div>
                                <div class="bulk-stat-label">Moderate</div>
                            </div>
                            <div class="bulk-stat">
                                <div class="bulk-stat-value" data-stat="avg">0</div>
                                <div class="bulk-stat-label">Avg Score</div>
                            </div>
                        </div>

                        <!-- Shortlist Actions -->
                        <div class="bulk-shortlist-actions" data-shortlist-actions style="display: none;">
                            <div class="bulk-shortlist-header">
                                <span class="bulk-shortlist-count">
                                    <span data-shortlist-count>0</span> selected for shortlist
                                </span>
                                <div class="bulk-shortlist-btns">
                                    <button type="button" class="bulk-shortlist-btn bulk-shortlist-btn--secondary" data-action="clear-shortlist">
                                        Clear Selection
                                    </button>
                                    <button type="button" class="bulk-shortlist-btn bulk-shortlist-btn--primary" data-action="generate-summaries">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px; margin-right: 6px;">
                                            <circle cx="12" cy="12" r="10"/>
                                            <path d="M12 6v6l4 2"/>
                                        </svg>
                                        Generate AI Summaries
                                    </button>
                                    <button type="button" class="bulk-shortlist-btn bulk-shortlist-btn--pdf" data-action="export-pdf" style="display: none;">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px; margin-right: 6px;">
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                            <polyline points="14 2 14 8 20 8"/>
                                        </svg>
                                        Export PDF
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Candidates List -->
                        <div class="bulk-candidates-list" data-candidates-list>
                            <div class="inst-chart-narrative">
                                <div class="inst-chart-narrative-header">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"/>
                                        <line x1="12" y1="8" x2="12" y2="12"/>
                                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                                    </svg>
                                    <span>Awaiting Analysis</span>
                                </div>
                                <p style="color: var(--inst-gray-500); font-size: 13px;">Upload CVs and click Analyze to see ranked candidates.</p>
                            </div>
                        </div>

                    </div>
                </div>

            </div>

            <!-- Chatbox -->
            <div class="inst-chatbox" id="inst-chatbox-bulk">
                <div class="inst-chatbox-header">
                    <svg class="inst-chatbox-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                    <span class="inst-chatbox-title">Bulk CV Screener</span>
                    <span class="inst-chatbox-hint" data-chatbox-hint>Upload CVs to begin</span>
                    <span class="inst-chatbox-badge">AI</span>
                    <button class="inst-chatbox-send" type="button" data-action="analyze">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="22" y1="2" x2="11" y2="13"/>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                        </svg>
                    </button>
                </div>
            </div>

        </div>
        <?php
    }

    public function maybe_enqueue_assets() {
        global $post;
        if ($post && has_shortcode($post->post_content, 'sffc_bulk_cv_analyzer')) {
            $this->enqueue_assets();
        }
    }

    private function enqueue_assets() {
        // Enqueue institutional article CSS (base layout)
        wp_enqueue_style(
            'inst-article',
            SFFC_PLUGIN_URL . 'assets/css/institutional-article.css',
            array(),
            SFFC_VERSION
        );

        // Enqueue bulk CV analyzer CSS
        wp_enqueue_style(
            'sffc-bulk-cv-analyzer',
            SFFC_PLUGIN_URL . 'assets/css/bulk-cv-analyzer.css',
            array('inst-article'),
            SFFC_VERSION
        );

        // Enqueue PDF.js for PDF text extraction
        wp_enqueue_script(
            'pdfjs',
            'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js',
            array(),
            '3.11.174',
            true
        );

        // Enqueue Mammoth.js for DOCX text extraction
        wp_enqueue_script(
            'mammoth',
            'https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.6.0/mammoth.browser.min.js',
            array(),
            '1.6.0',
            true
        );

        // Enqueue institutional article JS
        wp_enqueue_script(
            'inst-article',
            SFFC_PLUGIN_URL . 'assets/js/institutional-article.js',
            array(),
            SFFC_VERSION,
            true
        );

        // Enqueue bulk CV analyzer JS
        wp_enqueue_script(
            'sffc-bulk-cv-analyzer',
            SFFC_PLUGIN_URL . 'assets/js/bulk-cv-analyzer.js',
            array('jquery', 'inst-article', 'pdfjs', 'mammoth'),
            SFFC_VERSION,
            true
        );

        wp_localize_script('sffc-bulk-cv-analyzer', 'sffc_bulk_cv_analyzer', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sffc_bulk_cv_analyzer_nonce'),
            'pdfjs_worker' => 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js',
        ));
    }

    public function ajax_bulk_analyze() {
        // Clean any stray output
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();

        check_ajax_referer('sffc_bulk_cv_analyzer_nonce', 'nonce');

        // Sanitize JD text - use sanitize_textarea_field to preserve line breaks
        $jd_text = isset($_POST['jd_text']) ? sanitize_textarea_field(wp_unslash($_POST['jd_text'])) : '';

        // Decode and sanitize CV data
        $cv_data_raw = isset($_POST['cv_data']) ? json_decode(stripslashes($_POST['cv_data']), true) : array();
        $cv_data = array();

        // Sanitize each CV entry
        if (is_array($cv_data_raw)) {
            foreach ($cv_data_raw as $cv) {
                if (isset($cv['name']) && isset($cv['text'])) {
                    $cv_data[] = array(
                        'name' => sanitize_text_field($cv['name']),
                        'text' => sanitize_textarea_field($cv['text']),
                    );
                }
            }
        }

        if (empty($jd_text)) {
            ob_end_clean();
            wp_send_json_error(array('message' => 'Job description is required.'));
        }

        if (empty($cv_data) || !is_array($cv_data)) {
            ob_end_clean();
            wp_send_json_error(array('message' => 'At least one CV is required.'));
        }

        if (strlen($jd_text) < 100) {
            ob_end_clean();
            wp_send_json_error(array('message' => 'Job description is too short. Please paste the full job description.'));
        }

        try {
            // Load the bulk analyzer
            require_once SFFC_PLUGIN_DIR . 'includes/application-planner/class-bulk-cv-analyzer.php';
            $bulk_analyzer = new SFFC_Bulk_CV_Analyzer();

            // Run bulk analysis
            $result = $bulk_analyzer->analyze_bulk($jd_text, $cv_data);

            // Discard any stray output
            $stray_output = ob_get_clean();
            if (!empty($stray_output)) {
                error_log('Bulk CV Analyzer: Discarded stray output: ' . substr($stray_output, 0, 500));
            }

            if (!$result['success']) {
                wp_send_json_error(array('message' => $result['message'] ?? 'Analysis failed'));
            }

            wp_send_json_success($result);

        } catch (Exception $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            error_log('Bulk CV Analyzer Exception: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Analysis failed: ' . $e->getMessage()));
        }
    }

    /**
     * AJAX handler to generate AI summaries for shortlisted candidates
     */
    public function ajax_generate_summaries() {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();

        check_ajax_referer('sffc_bulk_cv_analyzer_nonce', 'nonce');

        $jd_text = isset($_POST['jd_text']) ? sanitize_textarea_field(wp_unslash($_POST['jd_text'])) : '';
        $candidate_ids_raw = isset($_POST['candidate_ids']) ? json_decode(stripslashes($_POST['candidate_ids']), true) : array();
        $all_results_raw = isset($_POST['all_results']) ? json_decode(stripslashes($_POST['all_results']), true) : array();

        // Sanitize candidate IDs
        $candidate_ids = array();
        if (is_array($candidate_ids_raw)) {
            foreach ($candidate_ids_raw as $id) {
                $candidate_ids[] = intval($id);
            }
        }

        // Sanitize results array
        $all_results = array();
        if (is_array($all_results_raw)) {
            $all_results = $all_results_raw; // Already sanitized from initial analysis
        }

        if (empty($jd_text) || empty($candidate_ids)) {
            ob_end_clean();
            wp_send_json_error(array('message' => 'Job description and candidate IDs are required.'));
        }

        try {
            require_once SFFC_PLUGIN_DIR . 'includes/application-planner/class-bulk-cv-analyzer.php';
            $bulk_analyzer = new SFFC_Bulk_CV_Analyzer();

            // Generate AI summaries
            $result = $bulk_analyzer->generate_ai_summaries($jd_text, $candidate_ids, $all_results);

            $stray_output = ob_get_clean();
            if (!empty($stray_output)) {
                error_log('Bulk CV Analyzer Summaries: Discarded stray output: ' . substr($stray_output, 0, 500));
            }

            if (!$result['success']) {
                wp_send_json_error(array('message' => $result['message'] ?? 'Failed to generate summaries'));
            }

            wp_send_json_success($result);

        } catch (Exception $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            error_log('Bulk CV Analyzer Summaries Exception: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Failed to generate summaries: ' . $e->getMessage()));
        }
    }

    /**
     * AJAX handler to export shortlist as PDF
     */
    public function ajax_export_pdf() {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();

        check_ajax_referer('sffc_bulk_cv_analyzer_nonce', 'nonce');

        $jd_text = isset($_POST['jd_text']) ? sanitize_textarea_field(wp_unslash($_POST['jd_text'])) : '';
        $summaries_raw = isset($_POST['summaries']) ? json_decode(stripslashes($_POST['summaries']), true) : array();

        $summaries = array();
        if (is_array($summaries_raw)) {
            $summaries = $summaries_raw; // Already contains sanitized AI data
        }

        if (empty($jd_text) || empty($summaries)) {
            ob_end_clean();
            wp_send_json_error(array('message' => 'Job description and summaries are required.'));
        }

        try {
            require_once SFFC_PLUGIN_DIR . 'includes/application-planner/class-bulk-cv-analyzer.php';
            $bulk_analyzer = new SFFC_Bulk_CV_Analyzer();

            // Generate PDF HTML
            $html = $bulk_analyzer->export_shortlist_pdf($jd_text, $summaries);

            $stray_output = ob_get_clean();
            if (!empty($stray_output)) {
                error_log('Bulk CV Analyzer PDF: Discarded stray output: ' . substr($stray_output, 0, 500));
            }

            wp_send_json_success(array('html' => $html));

        } catch (Exception $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            error_log('Bulk CV Analyzer PDF Exception: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Failed to generate PDF: ' . $e->getMessage()));
        }
    }
}

// Initialize
SFFC_Bulk_CV_Analyzer_Shortcode::get_instance();
