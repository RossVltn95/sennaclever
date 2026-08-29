<?php
/**
 * Application Planner Template
 *
 * Premium gap analysis tool with institutional design.
 * Renders the [sffc_application_planner] shortcode.
 *
 * @package SennaCareers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="sffc-planner">
    <!-- Header -->
    <header class="sffc-planner__header">
        <h1 class="sffc-planner__title">Application Gap Analyzer</h1>
        <p class="sffc-planner__subtitle">
            Get brutally honest, actionable feedback on your application.
            Upload your CV and the job description to see exactly where you stand.
        </p>
    </header>

    <!-- Upload Section -->
    <section class="sffc-planner__upload">
        <!-- Job Description Upload -->
        <div class="sffc-planner__upload-card" data-upload="jd">
            <svg class="sffc-planner__upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <line x1="16" y1="13" x2="8" y2="13"></line>
                <line x1="16" y1="17" x2="8" y2="17"></line>
            </svg>
            <div class="sffc-planner__upload-label">Job Description</div>
            <div class="sffc-planner__upload-hint">PDF, DOCX, or TXT</div>
            <input type="file" id="jd-file" class="sffc-planner__upload-input" accept=".pdf,.docx,.txt">
            <div class="sffc-planner__text-toggle" data-toggle="text" data-type="jd">
                Or paste text instead
            </div>
        </div>

        <!-- CV Upload -->
        <div class="sffc-planner__upload-card" data-upload="cv">
            <svg class="sffc-planner__upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
            </svg>
            <div class="sffc-planner__upload-label">Your CV</div>
            <div class="sffc-planner__upload-hint">PDF, DOCX, or TXT</div>
            <input type="file" id="cv-file" class="sffc-planner__upload-input" accept=".pdf,.docx,.txt">
            <div class="sffc-planner__text-toggle" data-toggle="text" data-type="cv">
                Or paste text instead
            </div>
        </div>
    </section>

    <!-- Actions -->
    <section class="sffc-planner__actions">
        <button class="sffc-planner__btn" data-action="analyze" disabled>
            <span class="sffc-planner__spinner" style="display: none;"></span>
            <span class="sffc-planner__btn-text">Analyze Application</span>
        </button>
    </section>

    <!-- Results Container -->
    <section class="sffc-planner__results">
        <!-- Score Hero -->
        <div class="sffc-planner__score-hero">
            <div class="sffc-planner__score-display">
                <div class="sffc-planner__score-circle">
                    <svg class="sffc-planner__score-svg" width="160" height="160" viewBox="0 0 160 160">
                        <circle class="sffc-planner__score-track" cx="80" cy="80" r="70"></circle>
                        <circle class="sffc-planner__score-fill" cx="80" cy="80" r="70"></circle>
                    </svg>
                    <span class="sffc-planner__score-value">0</span>
                </div>
                <div class="sffc-planner__score-label">Overall Score</div>
            </div>
            <div class="sffc-planner__verdict">
                <span class="sffc-planner__verdict-badge">Loading...</span>
                <h2 class="sffc-planner__verdict-title">Analyzing your application...</h2>
                <p class="sffc-planner__verdict-summary">Please wait while we analyze your CV against the job description.</p>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="sffc-planner__quick-stats">
            <!-- Populated by JavaScript -->
        </div>

        <!-- Charts -->
        <div class="sffc-planner__charts">
            <div class="sffc-planner__chart">
                <h3 class="sffc-planner__chart-title">Score Breakdown</h3>
                <div class="sffc-planner__chart-wrapper">
                    <canvas class="sffc-planner__chart-canvas" data-chart="score-breakdown"></canvas>
                </div>
            </div>
            <div class="sffc-planner__chart">
                <h3 class="sffc-planner__chart-title">Keyword Match</h3>
                <div class="sffc-planner__chart-wrapper">
                    <canvas class="sffc-planner__chart-canvas" data-chart="keywords"></canvas>
                </div>
            </div>
        </div>

        <!-- Dealbreakers Section -->
        <section class="sffc-planner__section" data-section="dealbreakers" style="display: none;">
            <div class="sffc-planner__section-header">
                <h2 class="sffc-planner__section-title" style="color: var(--status-high);">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 8px;">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="15" y1="9" x2="9" y2="15"></line>
                        <line x1="9" y1="9" x2="15" y2="15"></line>
                    </svg>
                    Dealbreakers
                </h2>
                <span class="sffc-planner__section-count"></span>
            </div>
            <div class="sffc-planner__issues">
                <!-- Populated by JavaScript -->
            </div>
        </section>

        <!-- Critical Gaps Section -->
        <section class="sffc-planner__section" data-section="critical" style="display: none;">
            <div class="sffc-planner__section-header">
                <h2 class="sffc-planner__section-title" style="color: var(--status-moderate);">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 8px;">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                        <line x1="12" y1="9" x2="12" y2="13"></line>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                    Critical Gaps
                </h2>
                <span class="sffc-planner__section-count"></span>
            </div>
            <div class="sffc-planner__issues">
                <!-- Populated by JavaScript -->
            </div>
        </section>

        <!-- Improvements Section -->
        <section class="sffc-planner__section" data-section="improvements" style="display: none;">
            <div class="sffc-planner__section-header">
                <h2 class="sffc-planner__section-title" style="color: var(--status-competitive);">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 8px;">
                        <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"></polyline>
                        <polyline points="16 7 22 7 22 13"></polyline>
                    </svg>
                    Suggested Improvements
                </h2>
                <span class="sffc-planner__section-count"></span>
            </div>
            <div class="sffc-planner__issues">
                <!-- Populated by JavaScript -->
            </div>
        </section>

        <!-- Strengths Section -->
        <section class="sffc-planner__section" data-section="strengths" style="display: none;">
            <div class="sffc-planner__section-header">
                <h2 class="sffc-planner__section-title" style="color: var(--status-strong);">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 8px;">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    Your Strengths
                </h2>
            </div>
            <div class="sffc-planner__strengths">
                <!-- Populated by JavaScript -->
            </div>
        </section>

        <!-- Keyword Analysis Section -->
        <section class="sffc-planner__section" data-section="keywords">
            <div class="sffc-planner__section-header">
                <h2 class="sffc-planner__section-title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 8px;">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    Keyword Analysis
                </h2>
            </div>
            <div class="sffc-planner__keywords">
                <div class="sffc-planner__keywords-header">
                    <div>
                        <span class="sffc-planner__keywords-rate">0%</span>
                        <span class="sffc-planner__keywords-meta">match rate</span>
                    </div>
                </div>
                <div class="sffc-planner__keywords-grid">
                    <!-- Populated by JavaScript -->
                </div>
            </div>
        </section>

        <!-- ATS Analysis Section -->
        <section class="sffc-planner__section" data-section="ats">
            <div class="sffc-planner__section-header">
                <h2 class="sffc-planner__section-title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 8px;">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                        <line x1="8" y1="21" x2="16" y2="21"></line>
                        <line x1="12" y1="17" x2="12" y2="21"></line>
                    </svg>
                    ATS Compatibility
                </h2>
            </div>
            <div class="sffc-planner__ats">
                <div class="sffc-planner__ats-header">
                    <span class="sffc-planner__ats-score">0%</span>
                    <span class="sffc-planner__ats-verdict">Analyzing...</span>
                </div>
                <div class="sffc-planner__ats-breakdown">
                    <!-- Populated by JavaScript -->
                </div>
                <div class="sffc-planner__ats-issues">
                    <!-- Populated by JavaScript -->
                </div>
            </div>
        </section>

        <!-- ATS Radar Chart -->
        <div class="sffc-planner__charts" style="grid-template-columns: 1fr;">
            <div class="sffc-planner__chart">
                <h3 class="sffc-planner__chart-title">ATS Component Scores</h3>
                <div class="sffc-planner__chart-wrapper">
                    <canvas class="sffc-planner__chart-canvas" data-chart="ats"></canvas>
                </div>
            </div>
        </div>

        <!-- Strategic Options Section -->
        <section class="sffc-planner__section" data-section="strategies" style="display: none;">
            <div class="sffc-planner__section-header">
                <h2 class="sffc-planner__section-title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 8px;">
                        <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                        <polyline points="2 17 12 22 22 17"></polyline>
                        <polyline points="2 12 12 17 22 12"></polyline>
                    </svg>
                    Strategic Options
                </h2>
            </div>
            <div class="sffc-planner__strategies">
                <!-- Populated by JavaScript -->
            </div>
        </section>

        <!-- Multi-Job Comparison -->
        <div class="sffc-planner__comparison" style="display: none;">
            <div class="sffc-planner__comparison-header">
                <h3 class="sffc-planner__comparison-title">Job Comparison</h3>
            </div>
            <div class="sffc-planner__comparison-results">
                <!-- Populated by JavaScript -->
            </div>
            <div class="sffc-planner__comparison-recommendation">
                <!-- Populated by JavaScript -->
            </div>
        </div>

        <!-- Export Actions -->
        <div class="sffc-planner__export">
            <span class="sffc-planner__export-title">Export Analysis</span>
            <button class="sffc-planner__btn sffc-planner__btn--secondary" data-action="export-pdf">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                Download PDF
            </button>
            <button class="sffc-planner__btn sffc-planner__btn--secondary" data-action="save-report">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                    <polyline points="7 3 7 8 15 8"></polyline>
                </svg>
                Save Report
            </button>
            <?php if (class_exists('SFFC_Multi_Job_Comparison')) : ?>
            <button class="sffc-planner__btn sffc-planner__btn--secondary" data-action="compare-jobs">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="20" x2="18" y2="10"></line>
                    <line x1="12" y1="20" x2="12" y2="4"></line>
                    <line x1="6" y1="20" x2="6" y2="14"></line>
                </svg>
                Compare Jobs
            </button>
            <?php endif; ?>
        </div>
    </section>

    <!-- Saved Reports -->
    <section class="sffc-planner__saved-reports" style="display: none;">
        <div class="sffc-planner__section-header">
            <h2 class="sffc-planner__section-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 8px;">
                    <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                </svg>
                Saved Reports
            </h2>
        </div>
        <div class="sffc-planner__reports-list">
            <!-- Populated by JavaScript -->
        </div>
    </section>
</div>

<style>
/* Notification styles */
.sffc-planner__notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 16px 24px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    z-index: 9999;
    animation: slideIn 0.3s ease;
}

.sffc-planner__notification--error {
    background: #fee2e2;
    color: #dc2626;
    border: 1px solid #fecaca;
}

.sffc-planner__notification--success {
    background: #d1fae5;
    color: #059669;
    border: 1px solid #a7f3d0;
}

.sffc-planner__notification--info {
    background: #dbeafe;
    color: #2563eb;
    border: 1px solid #bfdbfe;
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* Modal styles */
.sffc-planner__modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9998;
}

.sffc-planner__modal-content {
    background: #fff;
    padding: 32px;
    border-radius: 16px;
    max-width: 600px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
}

.sffc-planner__modal-content h3 {
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 8px;
}

.sffc-planner__modal-content p {
    color: #6b7280;
    margin-bottom: 24px;
}

.sffc-planner__modal-actions {
    display: flex;
    gap: 12px;
    margin-top: 24px;
}

/* Loading state for button */
.sffc-planner__btn.is-loading .sffc-planner__spinner {
    display: inline-block !important;
}

.sffc-planner__btn.is-loading .sffc-planner__btn-text {
    margin-left: 8px;
}
</style>
