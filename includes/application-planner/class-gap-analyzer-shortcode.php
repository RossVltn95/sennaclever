<?php

/**
 * Gap Analyzer Shortcode
 *
 * Provides a CV vs JD comparison tool using the institutional article layout.
 * EXACT copy of [sffc_editorial_article layout="institutional"] structure.
 *
 * @package SennaCareers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Gap_Analyzer_Shortcode
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
        add_shortcode('sffc_gap_analyzer', array($this, 'render'));
        add_action('wp_enqueue_scripts', array($this, 'maybe_enqueue_assets'));
        add_action('wp_ajax_sffc_analyze_gap', array($this, 'ajax_analyze'));
        add_action('wp_ajax_nopriv_sffc_analyze_gap', array($this, 'ajax_analyze'));
        add_action('wp_ajax_sffc_export_gap_pdf', array($this, 'ajax_export_pdf'));
        add_action('wp_ajax_nopriv_sffc_export_gap_pdf', array($this, 'ajax_export_pdf'));
    }

    public function render($atts)
    {
        $atts = shortcode_atts(array(
            'show_export' => 'true',
            'prefill_cv' => '',
            'prefill_jd' => '',
            'prefill_job_title' => '',
            'embedded_context' => '',
        ), $atts);

        $this->enqueue_assets();
        $prefill = $this->get_prefill_payload($atts);

        ob_start();
        $this->render_template($atts, $prefill);
        return ob_get_clean();
    }

    private function render_template($atts, $prefill = array())
    {
        $show_export = filter_var($atts['show_export'], FILTER_VALIDATE_BOOLEAN);
        $can_analyze = $this->user_has_premium_access();
        $membership_url = $this->get_membership_url();
        $preview_error_count = 4;
        $prefill_job_title = isset($prefill['job_title']) ? (string) $prefill['job_title'] : '';
        $prefill_jd_text = isset($prefill['jd_text']) ? (string) $prefill['jd_text'] : '';
        $embedded_context = sanitize_key((string) ($atts['embedded_context'] ?? ''));
        $embedded_back_label = $embedded_context === 'cv_match_studio'
            ? __('Back to CV Match Studio', 'senna-finance')
            : __('Back to Apply Options', 'senna-finance');
?>
        <div
            class="inst-terminal inst-terminal--gap-redesign has-gap-mobile-jd has-gap-mobile-cv"
            data-component="gap-analyzer"
            data-gap-stage="scan"
            data-embedded-context="<?php echo esc_attr($embedded_context); ?>"
            data-gap-mobile-step="cv"
            data-prefill-job-title="<?php echo esc_attr($prefill_job_title); ?>"
            data-prefill-jd="<?php echo esc_attr($prefill_jd_text); ?>"
            data-prefill-cv="<?php echo esc_attr(isset($prefill['cv_text']) ? (string) $prefill['cv_text'] : ''); ?>">
            <div class="inst-gap-app">
                <span data-gap-job-title hidden><?php esc_html_e('Review this CV for the role', 'senna-finance'); ?></span>
                <div class="inst-gap-mobile-stepper" aria-label="<?php esc_attr_e('Career Assessment progress', 'senna-finance'); ?>">
                    <button
                        type="button"
                        class="inst-article-header-back"
                        data-gap-back
                        <?php echo $embedded_context === 'cv_match_studio' ? '' : 'hidden'; ?>><?php echo esc_html($embedded_back_label); ?></button>
                    <div class="inst-gap-mobile-stepper__bar" aria-hidden="true">
                        <span data-gap-mobile-progress></span>
                    </div>
                    <div class="inst-gap-mobile-stepper__meta">
                        <strong data-gap-mobile-title><?php esc_html_e('CV', 'senna-finance'); ?></strong>
                        <span data-gap-mobile-page><?php esc_html_e('Page 1 of 3', 'senna-finance'); ?></span>
                    </div>
                </div>

                <div class="inst-gap-layout">
                    <section class="inst-gap-scan-stage">
                        <div class="inst-gap-scan-grid">
                            <section class="inst-gap-scan-pane inst-gap-scan-pane--cv" data-gap-mobile-panel="cv">
                                <div class="inst-gap-scan-pane-head">
                                    <h2><?php esc_html_e('CV*', 'senna-finance'); ?></h2>
                                    <span class="inst-gap-scan-pane-meta"><?php esc_html_e('Paste for best accuracy', 'senna-finance'); ?></span>
                                    <button type="button" class="inst-gap-mobile-btn inst-gap-mobile-btn--primary" data-gap-mobile-next="jd"><?php esc_html_e('Next', 'senna-finance'); ?></button>
                                </div>
                                <div class="inst-gap-scan-pane-body inst-article-body" data-gap-cv-section>
                                    <div class="inst-gap-uploadbar">
                                        <button type="button" class="inst-gap-uploadbtn" data-gap-cv-upload-trigger>
                                            <?php esc_html_e('Upload CV', 'senna-finance'); ?>
                                        </button>
                                        <span class="inst-gap-uploadstatus" data-gap-cv-upload-status><?php esc_html_e('PDF, DOCX, DOC, or TXT', 'senna-finance'); ?></span>
                                        <input type="file" data-gap-cv-file accept=".pdf,.doc,.docx,.txt,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain" hidden>
                                    </div>
                                    <textarea
                                        class="inst-gap-textarea inst-gap-textarea--scan"
                                        data-input="cv"
                                        placeholder="Paste your CV here or upload a PDF, DOCX, DOC, or TXT file."
                                        rows="16"></textarea>
                                    <p class="inst-gap-inline-validation" data-gap-cv-feedback hidden><?php esc_html_e('Please paste your CV', 'senna-finance'); ?></p>
                                </div>
                                <div class="inst-gap-scan-pane-foot">
                                    <p><?php esc_html_e('For the most reliable scan, paste your CV text directly. DOCX works better than PDF when you upload elsewhere in MENA Careers.', 'senna-finance'); ?></p>
                                </div>
                            </section>

                            <section class="inst-gap-scan-pane inst-gap-scan-pane--jd" data-gap-mobile-panel="jd">
                                <div class="inst-gap-scan-pane-head">
                                    <h2>
                                        <?php esc_html_e('Job description*', 'senna-finance'); ?>
                                        <span data-gap-scan-job-title><?php esc_html_e('Paste the live role brief', 'senna-finance'); ?></span>
                                    </h2>
                                </div>
                                <div class="inst-gap-scan-pane-body inst-article-body">
                                    <textarea
                                        class="inst-gap-textarea inst-gap-textarea--scan"
                                        data-input="jd"
                                        placeholder="Paste the full job description here."
                                        rows="16"></textarea>
                                </div>
                                <div class="inst-gap-scan-pane-foot">
                                    <p><?php esc_html_e('Use the full job description so MENA Careers can map responsibilities, requirements, and hiring signals accurately.', 'senna-finance'); ?></p>
                                    <div class="inst-gap-mobile-actions">
                                        <button type="button" class="inst-gap-mobile-btn inst-gap-mobile-btn--ghost" data-gap-mobile-back="cv"><?php esc_html_e('Back', 'senna-finance'); ?></button>
                                        <button type="button" class="inst-gap-mobile-btn inst-gap-mobile-btn--primary" data-gap-mobile-next="scan"><?php esc_html_e('Next', 'senna-finance'); ?></button>
                                    </div>
                                </div>
                            </section>
                        </div>

                        <div class="inst-gap-scan-actions" data-gap-mobile-panel="scan">
                            <div class="inst-gap-scan-actions-copy">
                                <strong><?php esc_html_e('Career Assessment + Tailored Materials', 'senna-finance'); ?></strong>
                                <p><?php esc_html_e('Paste your CV and the live job description, then launch the review to assess your suitability, sharpen your positioning, and unlock the recruiter-facing route for this role.', 'senna-finance'); ?></p>
                            </div>
                            <div class="inst-gap-inline-cta">
                                <button type="button" class="inst-analyze-btn" data-action="analyze">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="22" y1="2" x2="11" y2="13" />
                                        <polygon points="22 2 15 22 11 13 2 9 22 2" />
                                    </svg>
                                    <span><?php esc_html_e('Start Review', 'senna-finance'); ?></span>
                                </button>
                                <?php if (!$can_analyze) : ?>
                                    <p class="inst-gap-cta-note">Preview mode is available. <a href="<?php echo esc_url($membership_url); ?>"><?php esc_html_e('Join MENA Careers', 'senna-finance'); ?></a> <?php esc_html_e('to unlock full rewrites, downloads, and the complete toolkit.', 'senna-finance'); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="inst-gap-mobile-actions">
                                <button type="button" class="inst-gap-mobile-btn inst-gap-mobile-btn--ghost" data-gap-mobile-back="jd"><?php esc_html_e('Back', 'senna-finance'); ?></button>
                            </div>
                        </div>
                    </section>

                    <section class="inst-gap-results-stage">
                        <aside class="inst-gap-score-rail">
                            <div class="inst-gap-score-rail-header">
                                <p><?php esc_html_e('Resume scan results', 'senna-finance'); ?></p>
                                <h2 data-gap-report-title><?php esc_html_e('CV Match Report', 'senna-finance'); ?></h2>
                            </div>

                            <div class="inst-gap-score-hero">
                                <div class="inst-gap-score-ring" data-hero-ring>
                                    <div class="inst-gap-score-ring-inner">
                                        <span class="inst-gap-score-value" data-hero-score>--%</span>
                                        <span class="inst-gap-score-label"><?php esc_html_e('Match Rate', 'senna-finance'); ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="inst-gap-score-actions">
                                <button type="button" class="inst-gap-score-btn inst-gap-score-btn--primary" data-action="reset"><?php esc_html_e('Upload & rescan', 'senna-finance'); ?></button>
                                <button type="button" class="inst-gap-score-btn inst-gap-score-btn--secondary" data-action="optimize"><?php esc_html_e('One-Click Optimize', 'senna-finance'); ?></button>
                            </div>

                            <div class="inst-gap-score-breakdown">
                                <div class="inst-gap-score-breakdown-item" data-rail-metric="searchability">
                                    <div class="inst-gap-score-breakdown-top">
                                        <span><?php esc_html_e('Searchability', 'senna-finance'); ?></span>
                                        <strong data-rail-issue="searchability"><?php esc_html_e('Awaiting scan', 'senna-finance'); ?></strong>
                                    </div>
                                    <div class="inst-gap-score-breakdown-bar"><span data-rail-bar="searchability"></span></div>
                                    <p class="inst-gap-score-breakdown-note" data-rail-note="searchability"><?php esc_html_e('MENA Careers will surface ATS and contact issues here.', 'senna-finance'); ?></p>
                                </div>
                                <div class="inst-gap-score-breakdown-item" data-rail-metric="skills">
                                    <div class="inst-gap-score-breakdown-top">
                                        <span><?php esc_html_e('Hard Skills', 'senna-finance'); ?></span>
                                        <strong data-rail-issue="skills"><?php esc_html_e('Awaiting scan', 'senna-finance'); ?></strong>
                                    </div>
                                    <div class="inst-gap-score-breakdown-bar"><span data-rail-bar="skills"></span></div>
                                    <p class="inst-gap-score-breakdown-note" data-rail-note="skills"><?php esc_html_e('Missing tools and technical signals will appear here.', 'senna-finance'); ?></p>
                                </div>
                                <div class="inst-gap-score-breakdown-item" data-rail-metric="experience">
                                    <div class="inst-gap-score-breakdown-top">
                                        <span><?php esc_html_e('Relevant Experience', 'senna-finance'); ?></span>
                                        <strong data-rail-issue="experience"><?php esc_html_e('Awaiting scan', 'senna-finance'); ?></strong>
                                    </div>
                                    <div class="inst-gap-score-breakdown-bar"><span data-rail-bar="experience"></span></div>
                                    <p class="inst-gap-score-breakdown-note" data-rail-note="experience"><?php esc_html_e('Role-fit evidence will be summarised here.', 'senna-finance'); ?></p>
                                </div>
                                <div class="inst-gap-score-breakdown-item" data-rail-metric="keywords">
                                    <div class="inst-gap-score-breakdown-top">
                                        <span><?php esc_html_e('ATS Keywords', 'senna-finance'); ?></span>
                                        <strong data-rail-issue="keywords"><?php esc_html_e('Awaiting scan', 'senna-finance'); ?></strong>
                                    </div>
                                    <div class="inst-gap-score-breakdown-bar"><span data-rail-bar="keywords"></span></div>
                                    <p class="inst-gap-score-breakdown-note" data-rail-note="keywords"><?php esc_html_e('MENA Careers will flag missing screening terms here.', 'senna-finance'); ?></p>
                                </div>
                            </div>

                            <nav class="inst-gap-report-toc" aria-label="<?php esc_attr_e('Report contents', 'senna-finance'); ?>">
                                <p><?php esc_html_e('Contents', 'senna-finance'); ?></p>
                                <button type="button" data-gap-scroll-section="overview">
                                    <span><?php esc_html_e('01', 'senna-finance'); ?></span>
                                    <?php esc_html_e('Scorecard', 'senna-finance'); ?>
                                </button>
                                <button type="button" data-gap-scroll-section="fit">
                                    <span><?php esc_html_e('02', 'senna-finance'); ?></span>
                                    <?php esc_html_e('Fit Map', 'senna-finance'); ?>
                                </button>
                                <button type="button" data-gap-scroll-section="strategy">
                                    <span><?php esc_html_e('03', 'senna-finance'); ?></span>
                                    <?php esc_html_e('Action Plan', 'senna-finance'); ?>
                                </button>
                                <button type="button" data-gap-scroll-section="cv">
                                    <span><?php esc_html_e('04', 'senna-finance'); ?></span>
                                    <?php esc_html_e('CV Rewrite', 'senna-finance'); ?>
                                </button>
                                <button type="button" data-gap-scroll-section="keywords">
                                    <span><?php esc_html_e('05', 'senna-finance'); ?></span>
                                    <?php esc_html_e('ATS Terms', 'senna-finance'); ?>
                                </button>
                            </nav>
                        </aside>

                        <div class="inst-charts-panel">
                            <div class="inst-charts-inner">

                                <!-- Loading Overlay -->
                                <div class="inst-analysis-loader" data-loader="analysis" style="display: none;">
                                    <div class="inst-loader-visual" aria-hidden="true">
                                        <div class="inst-loader-orbit inst-loader-orbit--one"></div>
                                        <div class="inst-loader-orbit inst-loader-orbit--two"></div>
                                        <div class="inst-loader-orbit inst-loader-orbit--three"></div>
                                        <div class="inst-loader-core">
                                            <div class="inst-loader-icon">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <circle cx="12" cy="12" r="10" stroke-opacity="0.16" />
                                                    <path d="M12 2a10 10 0 0 1 10 10" class="inst-loader-spinner" />
                                                </svg>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="inst-loader-content">
                                        <p class="inst-loader-kicker">Tailored review in progress</p>
                                        <h3 class="inst-loader-title">We&rsquo;re improving your application.</h3>
                                        <p class="inst-loader-intro">MENA Careers is structuring this job description, mapping the hiring signals, and preparing a sharper review workspace so the result is worth the wait.</p>
                                        <div class="inst-loader-percentage" data-loader-percent>0%</div>
                                        <div class="inst-loader-meta">
                                            <span class="inst-loader-meta-label">Estimated completion</span>
                                            <strong data-loader-eta>~90 seconds</strong>
                                        </div>
                                        <div class="inst-loader-status" data-loader-status>Parsing job description...</div>
                                        <div class="inst-loader-bar">
                                            <div class="inst-loader-bar-fill" data-loader-bar></div>
                                        </div>
                                        <div class="inst-loader-steps">
                                            <div class="inst-loader-step" data-step="parse">
                                                <span class="inst-loader-step-icon"></span>
                                                <span class="inst-loader-step-text">Parsing job description</span>
                                            </div>
                                            <div class="inst-loader-step" data-step="extract">
                                                <span class="inst-loader-step-icon"></span>
                                                <span class="inst-loader-step-text">Extracting candidate signals</span>
                                            </div>
                                            <div class="inst-loader-step" data-step="skills">
                                                <span class="inst-loader-step-icon"></span>
                                                <span class="inst-loader-step-text">Mapping skills and experience</span>
                                            </div>
                                            <div class="inst-loader-step" data-step="match">
                                                <span class="inst-loader-step-icon"></span>
                                                <span class="inst-loader-step-text">Building tailored recommendations</span>
                                            </div>
                                            <div class="inst-loader-step" data-step="report">
                                                <span class="inst-loader-step-icon"></span>
                                                <span class="inst-loader-step-text">Preparing downloads and outreach tools</span>
                                            </div>
                                        </div>
                                        <div class="inst-loader-value">
                                            <span>What you&rsquo;ll get:</span>
                                            <strong>detected requirements, missing signals, stronger positioning, and clearer application direction</strong>
                                        </div>
                                    </div>
                                </div>

                                <div class="inst-panel-view inst-report-view is-active" id="inst-report-view" role="tabpanel">
                                    <div class="inst-optimizer-flow">
                                        <section class="inst-optimizer-section inst-optimizer-section--overview" data-optimizer-section="overview" data-slide-label="01">
                                            <div class="inst-report-cover">
                                                <div class="inst-report-cover__copy">
                                                    <p class="inst-report-cover__eyebrow">CV match report</p>
                                                    <h2 class="inst-report-cover__title">Application readiness report</h2>
                                                    <p class="inst-report-cover__dek" data-report-verdict>Paste a CV and job description to generate a visual hiring-readiness report.</p>
                                                    <div class="inst-report-cover__meta">
                                                        <div class="inst-analysis-status" data-meta="analysis-status" data-status="waiting">
                                                            <span class="inst-status-icon">
                                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                    <circle cx="12" cy="12" r="10" />
                                                                    <polyline points="12 6 12 12 16 14" />
                                                                </svg>
                                                            </span>
                                                            <span class="inst-status-label">Awaiting input</span>
                                                        </div>
                                                        <span data-report-recommendation>Ready for scan</span>
                                                        <span data-report-location>Role-specific review</span>
                                                    </div>
                                                </div>
                                                <div class="inst-report-cover__visual" aria-label="<?php esc_attr_e('Application readiness graphic', 'senna-finance'); ?>">
                                                    <div class="inst-report-compass" data-report-compass>
                                                        <span class="inst-report-compass__score" data-report-compass-score>--%</span>
                                                        <span class="inst-report-compass__label"><?php esc_html_e('Ready', 'senna-finance'); ?></span>
                                                    </div>
                                                    <div class="inst-report-signal-bars">
                                                        <span data-report-signal="skills"></span>
                                                        <span data-report-signal="experience"></span>
                                                        <span data-report-signal="keywords"></span>
                                                    </div>
                                                </div>
                                                <?php if ($show_export) : ?>
                                                    <div class="inst-report-cover__actions">
                                                        <button type="button" class="inst-pdf-download-btn" data-action="export" title="Download as PDF">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                                                <polyline points="14 2 14 8 20 8" />
                                                                <line x1="12" y1="18" x2="12" y2="12" />
                                                                <polyline points="9 15 12 18 15 15" />
                                                            </svg>
                                                            <span>PDF</span>
                                                        </button>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <div class="inst-report-contents-slide">
                                                <div class="inst-report-contents-slide__image" aria-hidden="true">
                                                    <span><?php esc_html_e('Contents', 'senna-finance'); ?></span>
                                                </div>
                                                <div class="inst-report-contents-slide__list">
                                                    <a href="#inst-report-view" data-gap-scroll-section="overview"><span>01</span><strong><?php esc_html_e('Application readiness', 'senna-finance'); ?></strong></a>
                                                    <a href="#inst-report-view" data-gap-scroll-section="overview"><span>03</span><strong><?php esc_html_e('Scorecard', 'senna-finance'); ?></strong></a>
                                                    <a href="#inst-report-view" data-gap-scroll-section="overview"><span>04</span><strong><?php esc_html_e('Application assets', 'senna-finance'); ?></strong></a>
                                                    <a href="#inst-report-view" data-gap-scroll-section="fit"><span>05</span><strong><?php esc_html_e('Fit map', 'senna-finance'); ?></strong></a>
                                                    <a href="#inst-report-view" data-gap-scroll-section="strategy"><span>06</span><strong><?php esc_html_e('Action roadmap', 'senna-finance'); ?></strong></a>
                                                    <a href="#inst-report-view" data-gap-scroll-section="cv"><span>07</span><strong><?php esc_html_e('CV rewrite preview', 'senna-finance'); ?></strong></a>
                                                    <a href="#inst-report-view" data-gap-scroll-section="keywords"><span>09</span><strong><?php esc_html_e('ATS keyword board', 'senna-finance'); ?></strong></a>
                                                </div>
                                            </div>

                                            <div class="inst-report-score-slide">
                                                <div class="inst-report-score-slide__intro">
                                                    <p class="inst-optimizer-section-kicker"><?php esc_html_e('Scorecard', 'senna-finance'); ?></p>
                                                    <h3 class="inst-optimizer-section-title"><?php esc_html_e('Where the application stands', 'senna-finance'); ?></h3>
                                                    <div class="inst-report-key-insight" data-report-key-insight>
                                                        <span><?php esc_html_e('Key insight', 'senna-finance'); ?></span>
                                                        <strong><?php esc_html_e('Your strongest and weakest hiring signals will appear here after analysis.', 'senna-finance'); ?></strong>
                                                    </div>
                                                </div>
                                                <div class="inst-report-score-slide__metrics">
                                                    <div class="inst-metrics inst-metrics--gap">
                                                        <div class="inst-metric inst-metric--gap" data-metric="overall">
                                                            <div class="inst-metric-value" data-score="overall">--</div>
                                                            <div class="inst-metric-label">Overall</div>
                                                            <div class="inst-metric-bar">
                                                                <div class="inst-metric-bar-fill" data-bar="overall"></div>
                                                            </div>
                                                            <div class="inst-metric-status" data-status="overall">Awaiting analysis</div>
                                                        </div>
                                                        <div class="inst-metric inst-metric--gap" data-metric="skills">
                                                            <div class="inst-metric-value" data-score="skills">--</div>
                                                            <div class="inst-metric-label">Skills</div>
                                                            <div class="inst-metric-bar">
                                                                <div class="inst-metric-bar-fill" data-bar="skills"></div>
                                                            </div>
                                                            <div class="inst-metric-status" data-status="skills">Awaiting analysis</div>
                                                        </div>
                                                        <div class="inst-metric inst-metric--gap" data-metric="experience">
                                                            <div class="inst-metric-value" data-score="experience">--</div>
                                                            <div class="inst-metric-label">Experience</div>
                                                            <div class="inst-metric-bar">
                                                                <div class="inst-metric-bar-fill" data-bar="experience"></div>
                                                            </div>
                                                            <div class="inst-metric-status" data-status="experience">Awaiting analysis</div>
                                                        </div>
                                                        <div class="inst-metric inst-metric--gap" data-metric="keywords">
                                                            <div class="inst-metric-value" data-score="keywords">--</div>
                                                            <div class="inst-metric-label">Keywords</div>
                                                            <div class="inst-metric-bar">
                                                                <div class="inst-metric-bar-fill" data-bar="keywords"></div>
                                                            </div>
                                                            <div class="inst-metric-status" data-status="keywords">Awaiting analysis</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php if (!$can_analyze) : ?>
                                                <div class="inst-preview-access-cta">
                                                    <a class="inst-preview-access-cta__button" href="<?php echo esc_url($membership_url); ?>">
                                                        <span>Access Your Application Report</span>
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <path d="M5 12h14" />
                                                            <path d="M13 5l7 7-7 7" />
                                                        </svg>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                            <div class="inst-gap-quick-stack inst-gap-quick-stack--deck" data-slide-label="04">
                                                <section class="inst-gap-quick-card inst-gap-quick-card--materials">
                                                    <div class="inst-gap-quick-card-head">
                                                        <div class="inst-gap-quick-card-copy">
                                                            <p><?php esc_html_e('Improved & Tailored Materials', 'senna-finance'); ?></p>
                                                            <h3><?php esc_html_e('Use role-specific assets without reading the full report', 'senna-finance'); ?></h3>
                                                        </div>
                                                        <span><?php esc_html_e('Specific to this role', 'senna-finance'); ?></span>
                                                    </div>
                                                    <div class="inst-gap-quick-grid">
                                                        <div class="sffc-crm-reddit-single-pack-grid" data-gap-quick-materials hidden></div>
                                                    </div>
                                                </section>

                                                <section class="inst-gap-quick-card inst-gap-quick-card--networking">
                                                    <div class="inst-gap-quick-card-head">
                                                        <div class="inst-gap-quick-card-copy">
                                                            <p><?php esc_html_e('Referral Strategy', 'senna-finance'); ?></p>
                                                            <h3><?php esc_html_e('See who to contact and how to approach them', 'senna-finance'); ?></h3>
                                                        </div>
                                                        <span><?php esc_html_e('Recruiter-aware', 'senna-finance'); ?></span>
                                                    </div>
                                                    <div class="inst-gap-networking-overview">
                                                        <div class="inst-gap-networking-preview sffc-crm-reddit-single-method-recruiter" data-gap-networking-recruiter hidden>
                                                            <div class="sffc-crm-reddit-single-method-recruiter-avatar">
                                                                <img src="" alt="" data-gap-networking-recruiter-photo hidden>
                                                                <span data-gap-networking-recruiter-initial>R</span>
                                                            </div>
                                                            <div class="sffc-crm-reddit-single-method-recruiter-copy">
                                                                <strong data-gap-networking-recruiter-name><?php esc_html_e('Recruiter contact', 'senna-finance'); ?></strong>
                                                                <span data-gap-networking-recruiter-role><?php esc_html_e('Recruitment Team', 'senna-finance'); ?></span>
                                                            </div>
                                                        </div>
                                                        <p><?php esc_html_e('Open the recruiter strategy, contact map, follow-up logic, and messaging templates tied to this exact role and company.', 'senna-finance'); ?></p>
                                                        <button type="button" class="inst-gap-networking-cta" data-gap-open-networking><?php esc_html_e('Open Referral Strategy', 'senna-finance'); ?></button>
                                                    </div>
                                                </section>
                                            </div>
                                        </section>

                                        <section class="inst-optimizer-section inst-optimizer-section--fit" data-optimizer-section="fit" data-slide-label="05">
                                            <div class="inst-optimizer-section-head">
                                                <div class="inst-optimizer-section-copy">
                                                    <p class="inst-optimizer-section-kicker" data-base-kicker="Role Fit">Role Fit</p>
                                                    <h3 class="inst-optimizer-section-title">Fit map</h3>
                                                    <p class="inst-optimizer-section-text">This section shows what recruiters may struggle to find in your CV and which requirements you already meet clearly.</p>
                                                </div>
                                            </div>
                                            <div class="inst-optimizer-insight-grid">
                                                <div class="inst-chart-card">
                                                    <div class="inst-chart-card-header">
                                                        <h3 class="inst-chart-card-title">Missing from Your CV</h3>
                                                        <p class="inst-chart-card-subtitle"><span data-count="missing">0</span> gaps identified</p>
                                                    </div>
                                                    <div class="inst-chart-card-body" data-list="missing">
                                                        <div class="inst-chart-narrative">
                                                            <div class="inst-chart-narrative-header">
                                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                    <circle cx="12" cy="12" r="10" />
                                                                    <line x1="12" y1="8" x2="12" y2="12" />
                                                                    <line x1="12" y1="16" x2="12.01" y2="16" />
                                                                </svg>
                                                                <span>Waiting for Analysis</span>
                                                            </div>
                                                            <p style="color: var(--inst-gray-500); font-size: 13px;">Paste your JD and CV, then click Analyze to see missing requirements.</p>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="inst-chart-card">
                                                    <div class="inst-chart-card-header">
                                                        <h3 class="inst-chart-card-title">Matched Requirements</h3>
                                                        <p class="inst-chart-card-subtitle"><span data-count="matched">0</span> requirements met</p>
                                                    </div>
                                                    <div class="inst-chart-card-body" data-list="matched">
                                                        <div class="inst-chart-narrative">
                                                            <div class="inst-chart-narrative-header">
                                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                                                                    <polyline points="22 4 12 14.01 9 11.01" />
                                                                </svg>
                                                                <span>Your Strengths</span>
                                                            </div>
                                                            <p style="color: var(--inst-gray-500); font-size: 13px;">Requirements you already meet will appear here.</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </section>

                                        <?php if (!$can_analyze) : ?>
                                            <section class="inst-optimizer-section inst-optimizer-section--preview-lock" data-preview-report-stop>
                                                <div class="inst-preview-report-stop-intro">
                                                    <p class="inst-optimizer-section-kicker" data-base-kicker="<?php esc_attr_e('Before you apply', 'senna-finance'); ?>"><?php esc_html_e('Before you apply', 'senna-finance'); ?></p>
                                                    <h3 class="inst-optimizer-section-title">
                                                        <?php
                                                        printf(
                                                            /* translators: 1: opening span tag, 2: number of issues, 3: closing span tag */
                                                            wp_kses_post(__('Fix %1$s%2$d errors%3$s before applying.', 'senna-finance')),
                                                            '<span class="inst-optimizer-section-title-count">',
                                                            (int) $preview_error_count,
                                                            '</span>'
                                                        );
                                                        ?>
                                                    </h3>
                                                    <p class="inst-optimizer-section-text"><?php esc_html_e('Increase your odds and beat the competition.', 'senna-finance'); ?></p>
                                                </div>
                                                <div class="inst-preview-report-ghost" aria-hidden="true">
                                                    <div class="inst-preview-report-ghost__metrics">
                                                        <div class="inst-preview-report-ghost__metric">
                                                            <span class="inst-preview-report-ghost__metric-label">Application strategy</span>
                                                            <strong>84%</strong>
                                                            <span class="inst-preview-report-ghost__metric-bar"><span style="width:84%"></span></span>
                                                        </div>
                                                        <div class="inst-preview-report-ghost__metric">
                                                            <span class="inst-preview-report-ghost__metric-label">Recruiter traction</span>
                                                            <strong>71%</strong>
                                                            <span class="inst-preview-report-ghost__metric-bar"><span style="width:71%"></span></span>
                                                        </div>
                                                        <div class="inst-preview-report-ghost__metric">
                                                            <span class="inst-preview-report-ghost__metric-label">ATS coverage</span>
                                                            <strong>76%</strong>
                                                            <span class="inst-preview-report-ghost__metric-bar"><span style="width:76%"></span></span>
                                                        </div>
                                                    </div>
                                                    <div class="inst-preview-report-ghost__grid">
                                                        <article class="inst-preview-report-ghost__card inst-preview-report-ghost__card--donut">
                                                            <div class="inst-preview-report-ghost__card-head">
                                                                <span>Improvement map</span>
                                                                <strong>Report score</strong>
                                                            </div>
                                                            <div class="inst-preview-report-ghost__donut">
                                                                <div class="inst-preview-report-ghost__donut-ring"></div>
                                                                <div class="inst-preview-report-ghost__donut-center">
                                                                    <strong>82%</strong>
                                                                    <span>after fixes</span>
                                                                </div>
                                                            </div>
                                                            <div class="inst-preview-report-ghost__legend">
                                                                <span><i class="is-blue"></i> CV positioning</span>
                                                                <span><i class="is-green"></i> Recruiter route</span>
                                                                <span><i class="is-gold"></i> ATS terms</span>
                                                            </div>
                                                        </article>

                                                        <article class="inst-preview-report-ghost__card inst-preview-report-ghost__card--bars">
                                                            <div class="inst-preview-report-ghost__card-head">
                                                                <span>Priority rewrites</span>
                                                                <strong>What MENA Careers would improve</strong>
                                                            </div>
                                                            <div class="inst-preview-report-ghost__bar-list">
                                                                <div class="inst-preview-report-ghost__bar-row">
                                                                    <span>Deal evidence</span>
                                                                    <strong>91%</strong>
                                                                    <span class="inst-preview-report-ghost__bar-track"><span class="is-blue" style="width:91%"></span></span>
                                                                </div>
                                                                <div class="inst-preview-report-ghost__bar-row">
                                                                    <span>Finance keywords</span>
                                                                    <strong>78%</strong>
                                                                    <span class="inst-preview-report-ghost__bar-track"><span class="is-gold" style="width:78%"></span></span>
                                                                </div>
                                                                <div class="inst-preview-report-ghost__bar-row">
                                                                    <span>Interview readiness</span>
                                                                    <strong>73%</strong>
                                                                    <span class="inst-preview-report-ghost__bar-track"><span class="is-green" style="width:73%"></span></span>
                                                                </div>
                                                                <div class="inst-preview-report-ghost__bar-row">
                                                                    <span>Outreach angle</span>
                                                                    <strong>68%</strong>
                                                                    <span class="inst-preview-report-ghost__bar-track"><span class="is-purple" style="width:68%"></span></span>
                                                                </div>
                                                            </div>
                                                        </article>

                                                        <article class="inst-preview-report-ghost__card inst-preview-report-ghost__card--panel">
                                                            <div class="inst-preview-report-ghost__card-head">
                                                                <span>CV + cover letter</span>
                                                                <strong>Tailored materials</strong>
                                                            </div>
                                                            <div class="inst-preview-report-ghost__stack">
                                                                <div class="inst-preview-report-ghost__sheet is-front"></div>
                                                                <div class="inst-preview-report-ghost__sheet is-mid"></div>
                                                                <div class="inst-preview-report-ghost__sheet is-back"></div>
                                                            </div>
                                                        </article>

                                                        <article class="inst-preview-report-ghost__card inst-preview-report-ghost__card--insights">
                                                            <div class="inst-preview-report-ghost__card-head">
                                                                <span>Next steps</span>
                                                                <strong>Recruiter-facing plan</strong>
                                                            </div>
                                                            <div class="inst-preview-report-ghost__insight-list">
                                                                <span>Reposition bullets for live mandates</span>
                                                                <span>Generate tailored cover letter draft</span>
                                                                <span>Unlock interview prep tied to this role</span>
                                                                <span>Open recruiter route and contact map</span>
                                                            </div>
                                                        </article>
                                                    </div>
                                                </div>
                                                <div class="inst-preview-report-stop-card">
                                                    <div class="inst-preview-report-stop-card__lock" aria-hidden="true">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                            <path d="M7.5 10V7.75A4.5 4.5 0 0 1 12 3.25a4.5 4.5 0 0 1 4.5 4.5V10" />
                                                            <rect x="5" y="10" width="14" height="10" rx="3" />
                                                            <circle cx="12" cy="15" r="1.2" />
                                                        </svg>
                                                    </div>
                                                    <div class="inst-preview-report-stop-card__copy">
                                                        <p class="inst-preview-report-stop-card__eyebrow"><?php esc_html_e('Full report locked', 'senna-finance'); ?></p>
                                                        <h3><?php esc_html_e('Subscribe to view full report', 'senna-finance'); ?></h3>
                                                        <p><?php esc_html_e('Unlock the application strategy, CV rewrites, cover letter draft, ATS keywords, interview prep, and downloadable materials for this exact role.', 'senna-finance'); ?></p>
                                                    </div>
                                                    <a class="inst-preview-report-stop-card__button" href="<?php echo esc_url($membership_url); ?>">
                                                        <span><?php esc_html_e('Subscribe Now', 'senna-finance'); ?></span>
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <path d="M5 12h14" />
                                                            <path d="M13 5l7 7-7 7" />
                                                        </svg>
                                                    </a>
                                                </div>
                                            </section>
                                        <?php endif; ?>

                                        <section class="inst-optimizer-section inst-optimizer-section--strategy" data-optimizer-section="strategy" data-slide-label="06">
                                            <div class="inst-optimizer-section-head">
                                                <div class="inst-optimizer-section-copy">
                                                    <p class="inst-optimizer-section-kicker" data-base-kicker="Application Strategy">Application Strategy</p>
                                                    <h3 class="inst-optimizer-section-title">Action plan before you apply</h3>
                                                    <p class="inst-optimizer-section-text">These are the highest-priority changes to make your application sharper, more recruiter-friendly, and better aligned to the live brief.</p>
                                                </div>
                                                <div class="inst-optimizer-section-asset" data-gap-material-slot="hiring_guide" hidden></div>
                                            </div>
                                            <div class="inst-chart-card">
                                                <div class="inst-chart-card-header">
                                                    <h3 class="inst-chart-card-title">Priority moves</h3>
                                                    <p class="inst-chart-card-subtitle">Short, practical fixes ranked for recruiter impact</p>
                                                </div>
                                                <div class="inst-chart-card-body" data-list="recommendations">
                                                    <div class="inst-chart-narrative">
                                                        <div class="inst-chart-narrative-header">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                <circle cx="12" cy="12" r="10" />
                                                                <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3" />
                                                                <line x1="12" y1="17" x2="12.01" y2="17" />
                                                            </svg>
                                                            <span>Action Items</span>
                                                        </div>
                                                        <p style="color: var(--inst-gray-500); font-size: 13px;">Personalized recommendations will appear after analysis.</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </section>

                                        <section class="inst-optimizer-section inst-optimizer-section--cv" data-optimizer-section="cv" data-slide-label="07">
                                            <div class="inst-optimizer-section-head">
                                                <div class="inst-optimizer-section-copy">
                                                    <p class="inst-optimizer-section-kicker" data-base-kicker="CV Optimization">CV Optimization</p>
                                                    <h3 class="inst-optimizer-section-title">How your CV improves for this role</h3>
                                                    <p class="inst-optimizer-section-text">See your current match, the projected improvement after fixes, and the strongest rewrites MENA Careers would make before you apply.</p>
                                                </div>
                                                <div class="inst-optimizer-section-asset" data-gap-material-slot="cv_template" hidden></div>
                                            </div>
                                            <div class="inst-toolkit-section">
                                                <div class="inst-toolkit-section-header">
                                                    <div class="inst-toolkit-section-header-row">
                                                        <div>
                                                            <h3>Tailored CV Preview</h3>
                                                            <p>Comprehensive improvements to optimize your CV for this role</p>
                                                        </div>
                                                        <button class="inst-toolkit-btn inst-toolkit-btn--secondary" data-action="download-cv-word" title="Download as Word document">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                                                <polyline points="14 2 14 8 20 8" />
                                                                <line x1="12" y1="18" x2="12" y2="12" />
                                                                <polyline points="9 15 12 18 15 15" />
                                                            </svg>
                                                            Download Word
                                                        </button>
                                                    </div>
                                                </div>

                                                <!-- Score Summary -->
                                                <div class="inst-cv-score-summary">
                                                    <div class="inst-cv-score-item">
                                                        <span class="inst-cv-score-label">Current Match</span>
                                                        <span class="inst-cv-score-value inst-cv-score-value--before" data-cv-score="before">--%</span>
                                                    </div>
                                                    <div class="inst-cv-score-arrow">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <path d="M5 12h14M12 5l7 7-7 7" />
                                                        </svg>
                                                    </div>
                                                    <div class="inst-cv-score-item">
                                                        <span class="inst-cv-score-label">After Improvements</span>
                                                        <span class="inst-cv-score-value inst-cv-score-value--after" data-cv-score="after">--%</span>
                                                    </div>
                                                </div>

                                                <!-- Optimized CV Full Preview -->
                                                <div class="inst-cv-full-preview" data-cv-full-preview>
                                                    <div class="inst-cv-placeholder">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                                            <polyline points="14 2 14 8 20 8" />
                                                        </svg>
                                                        <span>Your optimized CV will appear here after analysis</span>
                                                    </div>
                                                </div>

                                                <!-- Improvement Items by Section -->
                                                <div class="inst-toolkit-subsection">
                                                    <h4>
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;">
                                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                                        </svg>
                                                        Section-by-Section Improvements
                                                    </h4>
                                                    <div class="inst-toolkit-improvements" data-list="cv-improvements">
                                                        <div class="inst-toolkit-empty">
                                                            <p>Run analysis to see CV improvement suggestions</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </section>

                                        <section class="inst-optimizer-section inst-optimizer-section--cover" data-optimizer-section="cover" data-slide-label="08">
                                            <div class="inst-optimizer-section-head">
                                                <div class="inst-optimizer-section-copy">
                                                    <p class="inst-optimizer-section-kicker" data-base-kicker="Cover Letter">Cover Letter</p>
                                                    <h3 class="inst-optimizer-section-title">A stronger letter for this role and company</h3>
                                                    <p class="inst-optimizer-section-text">Use the tailored draft and key talking points below to sharpen how you position your motivation, fit, and contribution.</p>
                                                </div>
                                                <div class="inst-optimizer-section-asset" data-gap-material-slot="cover_letter" hidden></div>
                                            </div>
                                            <div class="inst-toolkit-section">
                                                <div class="inst-toolkit-section-header">
                                                    <h3>Tailored Cover Letter</h3>
                                                    <p>A cover letter that addresses your gaps and highlights your strengths</p>
                                                </div>

                                                <div class="inst-cover-letter-card" data-content="cover-letter">
                                                    <div class="inst-cover-letter-preview">
                                                        <div class="inst-toolkit-empty">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                                                                <polyline points="22,6 12,13 2,6" />
                                                            </svg>
                                                            <p>Run analysis to generate a tailored cover letter</p>
                                                        </div>
                                                    </div>
                                                    <div class="inst-cover-letter-actions" style="display: none;">
                                                        <button class="inst-toolkit-btn inst-toolkit-btn--primary" data-action="copy-cover">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2" />
                                                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
                                                            </svg>
                                                            Copy
                                                        </button>
                                                        <button class="inst-toolkit-btn" data-action="download-cover-word">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                                                <polyline points="14 2 14 8 20 8" />
                                                                <line x1="12" y1="18" x2="12" y2="12" />
                                                                <polyline points="9 15 12 18 15 15" />
                                                            </svg>
                                                            Download Word
                                                        </button>
                                                    </div>
                                                </div>

                                                <!-- Key Points to Emphasize -->
                                                <div class="inst-toolkit-subsection">
                                                    <h4>Key Points to Emphasize</h4>
                                                    <div class="inst-cover-points" data-list="cover-points">
                                                        <div class="inst-toolkit-empty">
                                                            <p>Key talking points will appear here</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </section>

                                        <section class="inst-optimizer-section inst-optimizer-section--keywords" data-optimizer-section="keywords" data-slide-label="09">
                                            <div class="inst-optimizer-section-head">
                                                <div class="inst-optimizer-section-copy">
                                                    <p class="inst-optimizer-section-kicker" data-base-kicker="ATS + Hiring Signals">ATS + Hiring Signals</p>
                                                    <h3 class="inst-optimizer-section-title">Keywords, language, and screening terms</h3>
                                                    <p class="inst-optimizer-section-text">This shows the terms recruiters and ATS filters are likely to look for, what is already present, and what still needs to be woven into your application.</p>
                                                </div>
                                            </div>
                                            <div class="inst-toolkit-section">
                                                <div class="inst-toolkit-section-header">
                                                    <h3>ATS Keywords</h3>
                                                    <p>Keywords to include in your CV for better ATS matching</p>
                                                </div>

                                                <!-- Keyword Stats -->
                                                <div class="inst-keyword-stats">
                                                    <div class="inst-keyword-stat">
                                                        <span class="inst-keyword-stat-value" data-stat="matched">0</span>
                                                        <span class="inst-keyword-stat-label">Matched</span>
                                                    </div>
                                                    <div class="inst-keyword-stat">
                                                        <span class="inst-keyword-stat-value" data-stat="missing">0</span>
                                                        <span class="inst-keyword-stat-label">Missing</span>
                                                    </div>
                                                    <div class="inst-keyword-stat">
                                                        <span class="inst-keyword-stat-value" data-stat="match-rate">0%</span>
                                                        <span class="inst-keyword-stat-label">Match Rate</span>
                                                    </div>
                                                </div>

                                                <!-- Missing Keywords (Priority) -->
                                                <div class="inst-toolkit-subsection">
                                                    <h4>
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" style="width:16px;height:16px;">
                                                            <circle cx="12" cy="12" r="10" />
                                                            <line x1="12" y1="8" x2="12" y2="12" />
                                                            <line x1="12" y1="16" x2="12.01" y2="16" />
                                                        </svg>
                                                        Missing Keywords - Add to CV
                                                    </h4>
                                                    <div class="inst-keyword-list inst-keyword-list--missing" data-list="keywords-missing">
                                                        <div class="inst-toolkit-empty">
                                                            <p>Missing keywords will appear here</p>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Matched Keywords -->
                                                <div class="inst-toolkit-subsection">
                                                    <h4>
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2" style="width:16px;height:16px;">
                                                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                                                            <polyline points="22 4 12 14.01 9 11.01" />
                                                        </svg>
                                                        Matched Keywords - Already in CV
                                                    </h4>
                                                    <div class="inst-keyword-list inst-keyword-list--matched" data-list="keywords-matched">
                                                        <div class="inst-toolkit-empty">
                                                            <p>Matched keywords will appear here</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </section>

                                        <section class="inst-optimizer-section inst-optimizer-section--interview" data-optimizer-section="interview" data-slide-label="10">
                                            <div class="inst-optimizer-section-head">
                                                <div class="inst-optimizer-section-copy">
                                                    <p class="inst-optimizer-section-kicker" data-base-kicker="Interview Preparation">Interview Preparation</p>
                                                    <h3 class="inst-optimizer-section-title">Likely questions and answer angles for this role</h3>
                                                    <p class="inst-optimizer-section-text">Prepare for the themes this firm is likely to test, the follow-ups that can expose weak spots, and the answer structure that sounds more convincing.</p>
                                                </div>
                                                <div class="inst-optimizer-section-asset" data-gap-material-slot="interview_questions" hidden></div>
                                            </div>
                                            <div class="inst-toolkit-section">
                                                <div class="inst-toolkit-section-header">
                                                    <h3>Interview Preparation</h3>
                                                    <p>Likely questions based on the role and your profile gaps</p>
                                                </div>

                                                <div class="inst-interview-questions" data-list="interview-questions">
                                                    <div class="inst-toolkit-empty">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                                                        </svg>
                                                        <p>Interview questions will appear after analysis</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </section>
                                    </div>
                                </div>
                                <?php if (!$can_analyze) : ?>
                                    <div class="inst-preview-report-lockbar" data-preview-lockbar>
                                        <div class="inst-preview-report-lockbar__inner">
                                            <div class="inst-preview-report-lockbar__copy">
                                                <span class="inst-preview-report-lockbar__eyebrow"><?php esc_html_e('Preview ends here', 'senna-finance'); ?></span>
                                                <strong><?php esc_html_e('Subscribe to view full report', 'senna-finance'); ?></strong>
                                                <span><?php esc_html_e('Unlock the full MENA Careers analysis, rewrites, downloads, and recruiter-facing application tools.', 'senna-finance'); ?></span>
                                            </div>
                                            <a class="inst-preview-report-lockbar__button" href="<?php echo esc_url($membership_url); ?>">
                                                <span><?php esc_html_e('Subscribe Now', 'senna-finance'); ?></span>
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M5 12h14" />
                                                    <path d="M13 5l7 7-7 7" />
                                                </svg>
                                            </a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
<?php
    }

    public function maybe_enqueue_assets()
    {
        global $post;
        if ($post && has_shortcode($post->post_content, 'sffc_gap_analyzer')) {
            $this->enqueue_assets();
        }
    }

    private function enqueue_assets()
    {
        $gap_css_path = defined('SFFC_PLUGIN_DIR') ? SFFC_PLUGIN_DIR . 'assets/css/gap-analyzer.css' : '';
        $gap_js_path = defined('SFFC_PLUGIN_DIR') ? SFFC_PLUGIN_DIR . 'assets/js/gap-analyzer.js' : '';
        $gap_css_version = $gap_css_path && file_exists($gap_css_path) ? (string) filemtime($gap_css_path) : SFFC_VERSION;
        $gap_js_version = $gap_js_path && file_exists($gap_js_path) ? (string) filemtime($gap_js_path) : SFFC_VERSION;

        // Enqueue institutional article CSS (the base layout)
        wp_enqueue_style(
            'inst-article',
            SFFC_PLUGIN_URL . 'assets/css/institutional-article.css',
            array(),
            SFFC_VERSION
        );

        // Enqueue gap analyzer specific CSS
        wp_enqueue_style(
            'sffc-gap-analyzer',
            SFFC_PLUGIN_URL . 'assets/css/gap-analyzer.css',
            array('inst-article'),
            $gap_css_version
        );

        // Enqueue institutional article JS (for resize handle, view toggle, etc.)
        wp_enqueue_script(
            'inst-article',
            SFFC_PLUGIN_URL . 'assets/js/institutional-article.js',
            array(),
            SFFC_VERSION,
            true
        );

        wp_enqueue_script(
            'pdfjs',
            'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js',
            array(),
            '3.11.174',
            true
        );

        wp_enqueue_script(
            'mammoth',
            'https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.6.0/mammoth.browser.min.js',
            array(),
            '1.6.0',
            true
        );

        // Enqueue gap analyzer JS
        wp_enqueue_script(
            'sffc-gap-analyzer',
            SFFC_PLUGIN_URL . 'assets/js/gap-analyzer.js',
            array('jquery', 'inst-article', 'pdfjs', 'mammoth'),
            $gap_js_version,
            true
        );

        $can_analyze = $this->user_has_premium_access();

        wp_localize_script('sffc-gap-analyzer', 'sffc_gap_analyzer', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sffc_gap_analyzer_nonce'),
            'can_analyze' => $can_analyze,
            'is_logged_in' => is_user_logged_in(),
            'membership_url' => $this->get_membership_url(),
            'login_url' => wp_login_url(),
            'pdf_worker' => 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js',
            'prefill' => $this->get_prefill_payload(),
        ));
    }

    private function get_prefill_payload(array $atts = array())
    {
        $jd_text = isset($atts['prefill_jd']) ? (string) $atts['prefill_jd'] : '';
        $job_title = isset($atts['prefill_job_title']) ? (string) $atts['prefill_job_title'] : '';
        $cv_text = isset($atts['prefill_cv']) ? (string) $atts['prefill_cv'] : '';

        if ($jd_text === '' && isset($_POST['senna_gap_prefill_jd'])) {
            $jd_text = wp_unslash((string) $_POST['senna_gap_prefill_jd']);
        } elseif ($jd_text === '' && isset($_GET['senna_gap_prefill_jd'])) {
            $jd_text = wp_unslash((string) $_GET['senna_gap_prefill_jd']);
        }

        if ($job_title === '' && isset($_POST['senna_gap_prefill_job_title'])) {
            $job_title = wp_unslash((string) $_POST['senna_gap_prefill_job_title']);
        } elseif ($job_title === '' && isset($_GET['senna_gap_prefill_job_title'])) {
            $job_title = wp_unslash((string) $_GET['senna_gap_prefill_job_title']);
        }

        $jd_text = sanitize_textarea_field($jd_text);
        $job_title = sanitize_text_field($job_title);
        $cv_text = sanitize_textarea_field($cv_text);

        if ($job_title === '' && $jd_text !== '') {
            $job_title = $this->extract_job_title_from_jd($jd_text);
        }

        return array(
            'jd_text' => $jd_text,
            'job_title' => $job_title,
            'cv_text' => $cv_text,
        );
    }

    private function extract_job_title_from_jd($jd_text)
    {
        $lines = preg_split("/\r\n|\n|\r/", (string) $jd_text);
        if (!is_array($lines)) {
            return '';
        }

        $blacklist = array(
            'about the role',
            'job description',
            'job summary',
            'responsibilities',
            'requirements',
            'qualifications',
            'about us',
            'overview',
        );

        foreach ($lines as $line) {
            $candidate = trim(wp_strip_all_tags((string) $line));
            if ($candidate === '') {
                continue;
            }

            if (strlen($candidate) > 90) {
                continue;
            }

            $normalized = strtolower($candidate);
            if (in_array($normalized, $blacklist, true)) {
                continue;
            }

            if (substr($candidate, -1) === ':') {
                continue;
            }

            return sanitize_text_field($candidate);
        }

        return '';
    }

    public function ajax_analyze()
    {
        // Clean any stray output that might have been generated before AJAX
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();

        check_ajax_referer('sffc_gap_analyzer_nonce', 'nonce');

        $jd_text = sanitize_textarea_field($_POST['jd_text'] ?? '');
        $cv_text = sanitize_textarea_field($_POST['cv_text'] ?? '');
        $preview_mode = sanitize_key($_POST['preview_mode'] ?? '');
        $is_preview_request = $preview_mode === 'guest';

        if (empty($jd_text) || empty($cv_text)) {
            ob_end_clean();
            wp_send_json_error(array('message' => 'Both JD and CV are required.'));
        }

        if (strlen($jd_text) < 100) {
            ob_end_clean();
            wp_send_json_error(array('message' => 'Job description is too short. Please paste the full job description.'));
        }

        if (strlen($cv_text) < 100) {
            ob_end_clean();
            wp_send_json_error(array('message' => 'CV is too short. Please paste your full CV.'));
        }

        try {
            $base_path = SFFC_PLUGIN_DIR . 'includes/application-planner/';
            $required_files = array(
                'class-knowledge-base.php',
                'class-jd-parser.php',
                'class-cv-parser.php',
                'class-ai-analyzer.php',
                'class-fallback-analyzer.php',
                'class-ats-analyzer.php',
                'class-gap-analyzer.php',
            );

            foreach ($required_files as $file) {
                $path = $base_path . $file;
                if (file_exists($path)) {
                    require_once $path;
                }
            }

            $gap_analyzer = new SFFC_Gap_Analyzer();
            if ($is_preview_request) {
                $gap_analyzer->set_mode('fallback_only');
            }
            $result = $gap_analyzer->analyze_for_terminal($jd_text, $cv_text);
            if ($is_preview_request) {
                $result = $this->build_preview_analysis_response($result);
            }

            // Discard any stray output before sending JSON
            $stray_output = ob_get_clean();
            if (!empty($stray_output)) {
                error_log('Gap Analyzer: Discarded stray output before JSON response: ' . substr($stray_output, 0, 500));
            }

            if (empty($result) || !is_array($result)) {
                wp_send_json_error(array('message' => 'Analysis failed: unable to generate a structured report.'));
            }

            wp_send_json_success($result);
        } catch (Exception $e) {
            // Clean any output buffer before sending error
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            error_log('Gap Analyzer Exception: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Analysis failed: ' . $e->getMessage()));
        }
    }

    private function build_preview_analysis_response(array $result)
    {
        $preview = $result;

        $preview['source'] = 'fallback_preview';
        $preview['analysis_source'] = 'fallback_preview';

        if (isset($preview['meta']) && is_array($preview['meta'])) {
            $preview['meta']['analysis_source'] = 'fallback_preview';
        }

        if (!empty($preview['executive_summary']) && is_array($preview['executive_summary'])) {
            $preview['executive_summary']['recommendation'] = __('Preview ready. Upgrade to unlock the full MENA Careers review, deeper rewrites, and the complete toolkit.', 'senna-finance');
        }

        if (!empty($preview['scores']) && is_array($preview['scores'])) {
            $preview['scores'] = array_intersect_key($preview['scores'], array(
                'overall' => true,
                'skills_match' => true,
                'experience_match' => true,
                'education_match' => true,
                'keywords_match' => true,
            ));
        }

        if (!empty($preview['skills_breakdown']) && is_array($preview['skills_breakdown'])) {
            $preview['skills_breakdown']['matched_skills'] = array_slice((array) ($preview['skills_breakdown']['matched_skills'] ?? []), 0, 4);
            $preview['skills_breakdown']['missing_skills'] = array_slice((array) ($preview['skills_breakdown']['missing_skills'] ?? []), 0, 4);
            $preview['skills_breakdown']['transferable_skills'] = array_slice((array) ($preview['skills_breakdown']['transferable_skills'] ?? []), 0, 2);
        }

        $preview['requirements_analysis'] = array_slice((array) ($preview['requirements_analysis'] ?? []), 0, 6);

        if (!empty($preview['experience_analysis']) && is_array($preview['experience_analysis'])) {
            $preview['experience_analysis']['relevant_roles'] = array_slice((array) ($preview['experience_analysis']['relevant_roles'] ?? []), 0, 2);
        }

        if (!empty($preview['experience_improvements']) && is_array($preview['experience_improvements'])) {
            $preview['experience_improvements']['priority_fixes'] = array_slice((array) ($preview['experience_improvements']['priority_fixes'] ?? []), 0, 2);
            $preview['experience_improvements']['action_verb_upgrades'] = array_slice((array) ($preview['experience_improvements']['action_verb_upgrades'] ?? []), 0, 2);
            $preview['experience_improvements']['keyword_integration'] = array_slice((array) ($preview['experience_improvements']['keyword_integration'] ?? []), 0, 2);
            $preview['experience_improvements']['achievement_reframes'] = array_slice((array) ($preview['experience_improvements']['achievement_reframes'] ?? []), 0, 2);
            $preview['experience_improvements']['additional_experience_to_add'] = array_slice((array) ($preview['experience_improvements']['additional_experience_to_add'] ?? []), 0, 2);
        }

        if (!empty($preview['keyword_analysis']) && is_array($preview['keyword_analysis'])) {
            $preview['keyword_analysis']['critical_missing'] = array_slice((array) ($preview['keyword_analysis']['critical_missing'] ?? []), 0, 4);
            $preview['keyword_analysis']['well_represented'] = array_slice((array) ($preview['keyword_analysis']['well_represented'] ?? []), 0, 4);
            $preview['keyword_analysis']['suggested_additions'] = array_slice((array) ($preview['keyword_analysis']['suggested_additions'] ?? []), 0, 4);
        }

        $preview['red_flags'] = array_slice((array) ($preview['red_flags'] ?? []), 0, 2);
        $preview['strengths_to_highlight'] = array_slice((array) ($preview['strengths_to_highlight'] ?? []), 0, 3);
        $preview['cv_improvements'] = array_slice((array) ($preview['cv_improvements'] ?? []), 0, 3);
        $preview['cover_letter_points'] = array_slice((array) ($preview['cover_letter_points'] ?? []), 0, 2);
        $preview['interview_prep'] = array_slice((array) ($preview['interview_prep'] ?? []), 0, 2);

        return $preview;
    }

    /**
     * AJAX handler for PDF export
     */
    public function ajax_export_pdf()
    {
        // Clean any stray output
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();

        check_ajax_referer('sffc_gap_analyzer_nonce', 'nonce');

        $analysis_data = isset($_POST['analysis_data']) ? $_POST['analysis_data'] : '';

        if (empty($analysis_data)) {
            ob_end_clean();
            wp_send_json_error(array('message' => 'No analysis data provided.'));
        }

        // Decode JSON data
        $data = json_decode(stripslashes($analysis_data), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            ob_end_clean();
            wp_send_json_error(array('message' => 'Invalid analysis data.'));
        }

        // Generate PDF HTML
        $html = $this->generate_pdf_html($data);

        ob_end_clean();
        wp_send_json_success(array('html' => $html));
    }

    /**
     * Generate PDF-ready HTML from analysis data
     */
    private function generate_pdf_html($data)
    {
        $summary = $data['executive_summary'] ?? [];
        $scores = $data['scores'] ?? [];
        $skills = $data['skills_breakdown'] ?? [];
        $keywords = $data['keyword_analysis'] ?? [];
        $redFlags = $data['red_flags'] ?? [];
        $strengths = $data['strengths_to_highlight'] ?? [];
        $cvImprovements = $data['cv_improvements'] ?? [];
        $interviewPrep = $data['interview_prep'] ?? [];
        $overall = $data['overall_assessment'] ?? [];

        $date = date('F j, Y');

        // Extract scores with proper defaults
        $matchScore = intval($summary['match_score'] ?? $scores['overall'] ?? 0);
        $skillsScore = intval($scores['skills_match'] ?? 0);
        $expScore = intval($scores['experience_match'] ?? 0);
        $kwScore = intval($scores['keywords_match'] ?? 0);

        // Determine score colors
        $getScoreColor = function ($score) {
            if ($score >= 75) return '#059669';
            if ($score >= 50) return '#d97706';
            return '#dc2626';
        };

        $overallColor = $getScoreColor($matchScore);
        $skillsColor = $getScoreColor($skillsScore);
        $expColor = $getScoreColor($expScore);
        $kwColor = $getScoreColor($kwScore);

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Gap Analysis Report - MENA Careers</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            font-size: 11px;
            line-height: 1.5;
            color: #1e293b;
            background: #fff;
        }

        .page {
            max-width: 800px;
            margin: 0 auto;
            padding: 32px 40px;
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 24px;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 24px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo-icon {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, #0f172a 0%, #334155 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-icon svg { width: 18px; height: 18px; }

        .logo-text {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: 0;
        }

        .header-meta {
            text-align: right;
            color: #64748b;
            font-size: 10px;
        }

        .header-meta strong {
            display: block;
            font-size: 12px;
            color: #0f172a;
            margin-bottom: 2px;
        }

        /* Hero Score Section */
        .hero {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            border-radius: 12px;
            padding: 28px 32px;
            margin-bottom: 24px;
            display: flex;
            gap: 32px;
            align-items: center;
        }

        .hero-score {
            text-align: center;
            min-width: 100px;
        }

        .hero-score-circle {
            width: 88px;
            height: 88px;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
            border: 4px solid;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px;
        }

        .hero-score-value {
            font-size: 32px;
            font-weight: 700;
            color: #fff;
            line-height: 1;
        }

        .hero-score-label {
            font-size: 9px;
            color: rgba(255,255,255,0.7);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 4px;
        }

        .hero-content {
            flex: 1;
        }

        .hero-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }

        .hero-badge.high { background: rgba(5, 150, 105, 0.2); color: #34d399; }
        .hero-badge.medium { background: rgba(217, 119, 6, 0.2); color: #fbbf24; }
        .hero-badge.low { background: rgba(220, 38, 38, 0.2); color: #f87171; }

        .hero-title {
            font-size: 16px;
            font-weight: 600;
            color: #fff;
            margin-bottom: 8px;
            line-height: 1.3;
        }

        .hero-desc {
            font-size: 12px;
            color: rgba(255,255,255,0.8);
            line-height: 1.5;
        }

        /* Score Cards Grid */
        .scores-grid {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
        }

        .score-card {
            flex: 1;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 16px;
            text-align: center;
        }

        .score-card-value {
            font-size: 28px;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 4px;
        }

        .score-card-label {
            font-size: 10px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .score-card-bar {
            height: 4px;
            background: #e2e8f0;
            border-radius: 2px;
            margin-top: 10px;
            overflow: hidden;
        }

        .score-card-bar-fill {
            height: 100%;
            border-radius: 2px;
            transition: width 0.3s ease;
        }

        /* Sections */
        .section {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid #f1f5f9;
        }

        .section-icon {
            width: 24px;
            height: 24px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .section-icon svg { width: 14px; height: 14px; }
        .section-icon.red { background: #fef2f2; color: #dc2626; }
        .section-icon.amber { background: #fffbeb; color: #d97706; }
        .section-icon.green { background: #f0fdf4; color: #059669; }
        .section-icon.blue { background: #eff6ff; color: #2563eb; }
        .section-icon.purple { background: #faf5ff; color: #7c3aed; }

        .section-title {
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
            flex: 1;
        }

        .section-count {
            font-size: 10px;
            color: #64748b;
            background: #f1f5f9;
            padding: 2px 8px;
            border-radius: 10px;
        }

        /* Items */
        .item {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 14px 16px;
            margin-bottom: 8px;
        }

        .item.critical { border-left: 3px solid #dc2626; background: #fef2f2; }
        .item.warning { border-left: 3px solid #d97706; background: #fffbeb; }
        .item.success { border-left: 3px solid #059669; background: #f0fdf4; }
        .item.info { border-left: 3px solid #2563eb; background: #eff6ff; }

        .item-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 6px;
        }

        .item-title {
            font-size: 12px;
            font-weight: 600;
            color: #0f172a;
            line-height: 1.3;
        }

        .item-badge {
            font-size: 9px;
            font-weight: 600;
            text-transform: uppercase;
            padding: 2px 8px;
            border-radius: 10px;
            white-space: nowrap;
        }

        .item-badge.critical { background: #dc2626; color: #fff; }
        .item-badge.serious { background: #ea580c; color: #fff; }
        .item-badge.important { background: #d97706; color: #fff; }
        .item-badge.nice { background: #0284c7; color: #fff; }

        .item-desc {
            font-size: 11px;
            color: #475569;
            line-height: 1.5;
        }

        .item-action {
            margin-top: 10px;
            padding: 10px 12px;
            background: rgba(255,255,255,0.7);
            border-radius: 6px;
            font-size: 11px;
        }

        .item-action strong {
            color: #059669;
        }

        /* Keywords */
        .keywords-wrap {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 8px;
        }

        .keyword {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 500;
        }

        .keyword.matched { background: #dcfce7; color: #166534; }
        .keyword.missing { background: #fee2e2; color: #991b1b; }

        /* Two Column Layout */
        .two-col {
            display: flex;
            gap: 16px;
        }

        .two-col > .section {
            flex: 1;
        }

        /* Footer */
        .footer {
            margin-top: 32px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .footer-logo {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: #0f172a;
            font-size: 12px;
        }

        .footer-logo-icon {
            width: 20px;
            height: 20px;
            background: #0f172a;
            border-radius: 4px;
        }

        .footer-text {
            font-size: 10px;
            color: #94a3b8;
            text-align: right;
        }

        @media print {
            .page { padding: 20px; }
            .section { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="page">
        <!-- Header -->
        <div class="header">
            <div class="logo">
                <div class="logo-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5">
                        <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                    </svg>
                </div>
                <span class="logo-text">MENA Careers</span>
            </div>
            <div class="header-meta">
                <strong>Gap Analysis Report</strong>
                {$date}
            </div>
        </div>
HTML;

        // Hero section
        $riskLevel = strtoupper($summary['risk_level'] ?? 'UNKNOWN');
        $recommendation = htmlspecialchars($summary['recommendation'] ?? 'Analysis Complete');
        $verdict = htmlspecialchars($summary['verdict'] ?? '');
        $badgeClass = $matchScore >= 70 ? 'high' : ($matchScore >= 45 ? 'medium' : 'low');

        $html .= <<<HTML
        <!-- Hero Score -->
        <div class="hero">
            <div class="hero-score">
                <div class="hero-score-circle" style="border-color: {$overallColor};">
                    <span class="hero-score-value">{$matchScore}</span>
                </div>
                <div class="hero-score-label">Match Score</div>
            </div>
            <div class="hero-content">
                <span class="hero-badge {$badgeClass}">{$riskLevel}</span>
                <h2 class="hero-title">{$recommendation}</h2>
                <p class="hero-desc">{$verdict}</p>
            </div>
        </div>

        <!-- Score Cards -->
        <div class="scores-grid">
            <div class="score-card">
                <div class="score-card-value" style="color: {$skillsColor};">{$skillsScore}%</div>
                <div class="score-card-label">Skills Match</div>
                <div class="score-card-bar"><div class="score-card-bar-fill" style="width: {$skillsScore}%; background: {$skillsColor};"></div></div>
            </div>
            <div class="score-card">
                <div class="score-card-value" style="color: {$expColor};">{$expScore}%</div>
                <div class="score-card-label">Experience</div>
                <div class="score-card-bar"><div class="score-card-bar-fill" style="width: {$expScore}%; background: {$expColor};"></div></div>
            </div>
            <div class="score-card">
                <div class="score-card-value" style="color: {$kwColor};">{$kwScore}%</div>
                <div class="score-card-label">Keywords</div>
                <div class="score-card-bar"><div class="score-card-bar-fill" style="width: {$kwScore}%; background: {$kwColor};"></div></div>
            </div>
        </div>
HTML;

        // Red Flags Section
        if (!empty($redFlags)) {
            $flagCount = count($redFlags);
            $html .= <<<HTML
        <div class="section">
            <div class="section-header">
                <div class="section-icon red">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                </div>
                <h3 class="section-title">Critical Issues</h3>
                <span class="section-count">{$flagCount} found</span>
            </div>
HTML;
            foreach (array_slice($redFlags, 0, 4) as $flag) {
                $issue = htmlspecialchars($flag['issue'] ?? '');
                $severity = strtolower($flag['severity'] ?? 'serious');
                $mitigation = htmlspecialchars($flag['mitigation'] ?? '');
                $html .= <<<HTML
            <div class="item critical">
                <div class="item-header">
                    <span class="item-title">{$issue}</span>
                    <span class="item-badge {$severity}">{$severity}</span>
                </div>
                <div class="item-action"><strong>Fix:</strong> {$mitigation}</div>
            </div>
HTML;
            }
            $html .= '</div>';
        }

        // Missing Skills
        $missingSkills = $skills['missing_skills'] ?? [];
        if (!empty($missingSkills)) {
            $skillCount = count($missingSkills);
            $html .= <<<HTML
        <div class="section">
            <div class="section-header">
                <div class="section-icon amber">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                </div>
                <h3 class="section-title">Skills to Develop</h3>
                <span class="section-count">{$skillCount} gaps</span>
            </div>
HTML;
            foreach (array_slice($missingSkills, 0, 5) as $skill) {
                $skillName = htmlspecialchars($skill['skill'] ?? '');
                $importance = strtolower($skill['importance'] ?? 'important');
                $suggestion = htmlspecialchars($skill['suggestion'] ?? '');
                $html .= <<<HTML
            <div class="item warning">
                <div class="item-header">
                    <span class="item-title">{$skillName}</span>
                    <span class="item-badge {$importance}">{$importance}</span>
                </div>
                <p class="item-desc">{$suggestion}</p>
            </div>
HTML;
            }
            $html .= '</div>';
        }

        // Strengths
        if (!empty($strengths)) {
            $strengthCount = count($strengths);
            $html .= <<<HTML
        <div class="section">
            <div class="section-header">
                <div class="section-icon green">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                </div>
                <h3 class="section-title">Your Strengths</h3>
                <span class="section-count">{$strengthCount} identified</span>
            </div>
HTML;
            foreach (array_slice($strengths, 0, 4) as $strength) {
                $title = htmlspecialchars($strength['strength'] ?? '');
                $leverage = htmlspecialchars($strength['how_to_leverage'] ?? $strength['relevance'] ?? '');
                $html .= <<<HTML
            <div class="item success">
                <span class="item-title">{$title}</span>
                <p class="item-desc">{$leverage}</p>
            </div>
HTML;
            }
            $html .= '</div>';
        }

        // Keywords Section
        if (!empty($keywords)) {
            $matchedKws = $keywords['well_represented'] ?? [];
            $missingKws = $keywords['critical_missing'] ?? [];
            $kwMatchPct = intval($keywords['match_percentage'] ?? 0);

            $html .= <<<HTML
        <div class="section">
            <div class="section-header">
                <div class="section-icon blue">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                </div>
                <h3 class="section-title">Keyword Analysis</h3>
                <span class="section-count">{$kwMatchPct}% match</span>
            </div>
HTML;

            if (!empty($matchedKws)) {
                $html .= '<p style="font-size: 10px; color: #059669; font-weight: 600; margin-bottom: 6px;">MATCHED KEYWORDS</p><div class="keywords-wrap">';
                foreach (array_slice($matchedKws, 0, 12) as $kw) {
                    $html .= '<span class="keyword matched">' . htmlspecialchars($kw) . '</span>';
                }
                $html .= '</div>';
            }

            if (!empty($missingKws)) {
                $html .= '<p style="font-size: 10px; color: #dc2626; font-weight: 600; margin: 12px 0 6px;">MISSING - ADD TO CV</p><div class="keywords-wrap">';
                foreach (array_slice($missingKws, 0, 12) as $kw) {
                    $html .= '<span class="keyword missing">' . htmlspecialchars($kw) . '</span>';
                }
                $html .= '</div>';
            }
            $html .= '</div>';
        }

        // CV Improvements
        if (!empty($cvImprovements)) {
            $html .= <<<HTML
        <div class="section">
            <div class="section-header">
                <div class="section-icon purple">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                </div>
                <h3 class="section-title">CV Improvements</h3>
            </div>
HTML;
            foreach (array_slice($cvImprovements, 0, 3) as $imp) {
                $section = htmlspecialchars($imp['section'] ?? '');
                $suggested = htmlspecialchars($imp['suggested'] ?? '');
                $impact = htmlspecialchars($imp['impact'] ?? '');
                $html .= <<<HTML
            <div class="item info">
                <span class="item-title">{$section}</span>
                <p class="item-desc">{$suggested}</p>
                <p class="item-desc" style="margin-top: 6px; color: #2563eb;"><strong>Impact:</strong> {$impact}</p>
            </div>
HTML;
            }
            $html .= '</div>';
        }

        // Interview Prep (condensed)
        if (!empty($interviewPrep)) {
            $html .= <<<HTML
        <div class="section">
            <div class="section-header">
                <div class="section-icon blue">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                </div>
                <h3 class="section-title">Interview Prep</h3>
            </div>
HTML;
            foreach (array_slice($interviewPrep, 0, 3) as $prep) {
                $question = htmlspecialchars($prep['likely_question'] ?? '');
                $response = htmlspecialchars($prep['suggested_response_angle'] ?? '');
                $html .= <<<HTML
            <div class="item">
                <span class="item-title">Q: {$question}</span>
                <p class="item-desc" style="margin-top: 6px;"><strong>Angle:</strong> {$response}</p>
            </div>
HTML;
            }
            $html .= '</div>';
        }

        // Final recommendation
        $finalRec = htmlspecialchars($overall['final_recommendation'] ?? '');
        if ($finalRec) {
            $html .= <<<HTML
        <div class="item info" style="margin-top: 16px;">
            <span class="item-title">Final Recommendation</span>
            <p class="item-desc" style="margin-top: 6px;">{$finalRec}</p>
        </div>
HTML;
        }

        // Footer
        $html .= <<<HTML
        <!-- Footer -->
        <div class="footer">
            <div class="footer-logo">
                <div class="footer-logo-icon"></div>
                MENA Careers
            </div>
            <div class="footer-text">
                joinsenna.com<br>
                Analysis generated {$date}
            </div>
        </div>
    </div>
</body>
</html>
HTML;

        return $html;
    }

    private function user_has_premium_access()
    {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return false;
        }

        if (user_can($user_id, 'manage_options')) {
            return true;
        }

        if (class_exists('SFFC_CRM_MemberPress_Integration')) {
            $crm_integration = SFFC_CRM_MemberPress_Integration::get_instance();
            $tier = $crm_integration->get_crm_tier($user_id);
            if (in_array($tier, array('insider', 'pro'), true)) {
                return true;
            }
            if ($tier === 'basic' || $tier === 'free') {
                return false;
            }
        }

        if (!class_exists('SFFC_MemberPress_Integration')) {
            return false;
        }

        $mepr = SFFC_MemberPress_Integration::get_instance();
        return $mepr->has_premium_access($user_id, 'insider') || $mepr->has_premium_access($user_id, 'pro');
    }

    private function get_membership_url()
    {
        return apply_filters('sffc_gap_analyzer_membership_url', 'https://joinsenna.com/memberships/');
    }
}

// Initialize
SFFC_Gap_Analyzer_Shortcode::get_instance();
