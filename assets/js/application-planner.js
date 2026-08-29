/**
 * Application Planner Frontend
 *
 * Handles file uploads, analysis requests, and result rendering.
 * Integrates with Chart.js for visualizations.
 */

(function($) {
    'use strict';

    // Main Application Planner class
    class ApplicationPlanner {
        constructor(container) {
            this.$container = $(container);
            this.$uploadJD = this.$container.find('[data-upload="jd"]');
            this.$uploadCV = this.$container.find('[data-upload="cv"]');
            this.$analyzeBtn = this.$container.find('[data-action="analyze"]');
            this.$results = this.$container.find('.sffc-planner__results');

            this.jdText = '';
            this.cvText = '';
            this.analysisResult = null;
            this.charts = {};

            this.init();
        }

        init() {
            this.bindEvents();
            this.loadSavedReports();
        }

        bindEvents() {
            // File upload handlers - exclude clicks on the input itself and text toggle
            this.$uploadJD.on('click', (e) => {
                if (!$(e.target).is('input, [data-toggle="text"], textarea')) {
                    this.triggerUpload('jd');
                }
            });
            this.$uploadCV.on('click', (e) => {
                if (!$(e.target).is('input, [data-toggle="text"], textarea')) {
                    this.triggerUpload('cv');
                }
            });

            // Prevent file input clicks from bubbling to parent
            this.$container.find('#jd-file, #cv-file').on('click', (e) => e.stopPropagation());

            // File input change
            this.$container.find('#jd-file').on('change', (e) => this.handleFileUpload(e, 'jd'));
            this.$container.find('#cv-file').on('change', (e) => this.handleFileUpload(e, 'cv'));

            // Text toggle
            this.$container.on('click', '[data-toggle="text"]', (e) => this.toggleTextInput(e));

            // Text input change
            this.$container.on('input', '[data-text="jd"]', (e) => {
                this.jdText = $(e.target).val();
                this.updateUploadState('jd', this.jdText.length > 0);
            });
            this.$container.on('input', '[data-text="cv"]', (e) => {
                this.cvText = $(e.target).val();
                this.updateUploadState('cv', this.cvText.length > 0);
            });

            // Analyze button
            this.$analyzeBtn.on('click', () => this.runAnalysis());

            // Export buttons
            this.$container.on('click', '[data-action="export-pdf"]', () => this.exportPDF());
            this.$container.on('click', '[data-action="save-report"]', () => this.saveReport());

            // Comparison handlers
            this.$container.on('click', '[data-action="compare-jobs"]', () => this.showComparisonModal());
            this.$container.on('click', '[data-job-select]', (e) => this.toggleJobSelection(e));
            this.$container.on('click', '[data-action="run-comparison"]', () => this.runComparison());

            // Report handlers
            this.$container.on('click', '[data-action="view-report"]', (e) => this.viewReport(e));
            this.$container.on('click', '[data-action="delete-report"]', (e) => this.deleteReport(e));
        }

        triggerUpload(type) {
            // Use native click() instead of jQuery trigger() to avoid event bubbling issues
            const input = document.getElementById(`${type}-file`);
            if (input) {
                input.click();
            }
        }

        async handleFileUpload(event, type) {
            const file = event.target.files[0];
            if (!file) return;

            const $card = type === 'jd' ? this.$uploadJD : this.$uploadCV;

            try {
                $card.addClass('is-loading');

                const text = await this.extractText(file);

                if (type === 'jd') {
                    this.jdText = text;
                } else {
                    this.cvText = text;
                }

                this.updateUploadState(type, true, file.name);

            } catch (error) {
                console.error('File upload error:', error);
                this.showError(`Failed to process ${file.name}: ${error.message}`);
            } finally {
                $card.removeClass('is-loading');
            }
        }

        async extractText(file) {
            const extension = file.name.split('.').pop().toLowerCase();

            if (extension === 'txt') {
                return await file.text();
            }

            if (extension === 'pdf') {
                return await this.extractPDFText(file);
            }

            if (extension === 'docx') {
                return await this.extractDocxText(file);
            }

            throw new Error('Unsupported file type. Please use PDF, DOCX, or TXT.');
        }

        async extractPDFText(file) {
            if (typeof pdfjsLib === 'undefined') {
                throw new Error('PDF.js library not loaded');
            }

            const arrayBuffer = await file.arrayBuffer();
            const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;

            let text = '';
            for (let i = 1; i <= pdf.numPages; i++) {
                const page = await pdf.getPage(i);
                const content = await page.getTextContent();
                text += content.items.map(item => item.str).join(' ') + '\n';
            }

            return text.trim();
        }

        async extractDocxText(file) {
            if (typeof mammoth === 'undefined') {
                throw new Error('Mammoth.js library not loaded');
            }

            const arrayBuffer = await file.arrayBuffer();
            const result = await mammoth.extractRawText({ arrayBuffer });

            return result.value.trim();
        }

        updateUploadState(type, hasContent, fileName = null) {
            const $card = type === 'jd' ? this.$uploadJD : this.$uploadCV;

            if (hasContent) {
                $card.addClass('is-active');
                if (fileName) {
                    $card.find('.sffc-planner__file-name').remove();
                    $card.append(`
                        <div class="sffc-planner__file-name">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                            </svg>
                            ${fileName}
                        </div>
                    `);
                }
            } else {
                $card.removeClass('is-active');
                $card.find('.sffc-planner__file-name').remove();
            }

            this.updateAnalyzeButton();
        }

        updateAnalyzeButton() {
            const canAnalyze = this.jdText.length > 50 && this.cvText.length > 50;
            this.$analyzeBtn.prop('disabled', !canAnalyze);
        }

        toggleTextInput(event) {
            const $toggle = $(event.currentTarget);
            const type = $toggle.data('type');
            const $card = type === 'jd' ? this.$uploadJD : this.$uploadCV;
            const $textarea = $card.find('.sffc-planner__textarea');

            if ($textarea.length) {
                $textarea.slideToggle(200);
            } else {
                $card.append(`
                    <textarea
                        class="sffc-planner__textarea"
                        data-text="${type}"
                        placeholder="Paste ${type === 'jd' ? 'job description' : 'CV'} text here..."
                    ></textarea>
                `);
            }
        }

        async runAnalysis() {
            if (!this.jdText || !this.cvText) {
                this.showError('Please provide both a job description and CV');
                return;
            }

            this.$analyzeBtn.addClass('is-loading').prop('disabled', true);
            this.$analyzeBtn.find('.sffc-planner__btn-text').text('Analyzing...');

            try {
                const response = await $.ajax({
                    url: sffc_planner.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'sffc_analyze_application',
                        nonce: sffc_planner.nonce,
                        jd_text: this.jdText,
                        cv_text: this.cvText
                    }
                });

                if (response.success) {
                    this.analysisResult = response.data;
                    this.renderResults(response.data);
                } else {
                    this.showError(response.data?.message || 'Analysis failed');
                }

            } catch (error) {
                console.error('Analysis error:', error);
                this.showError('Failed to analyze application. Please try again.');
            } finally {
                this.$analyzeBtn.removeClass('is-loading').prop('disabled', false);
                this.$analyzeBtn.find('.sffc-planner__btn-text').text('Analyze Application');
            }
        }

        renderResults(data) {
            this.$results.addClass('is-visible');

            // Scroll to results
            $('html, body').animate({
                scrollTop: this.$results.offset().top - 100
            }, 500);

            // Render score hero
            this.renderScoreHero(data);

            // Render quick stats
            this.renderQuickStats(data);

            // Render charts
            this.renderCharts(data);

            // Render issues sections
            this.renderDealbreakers(data.dealbreakers || []);
            this.renderCriticalGaps(data.critical_gaps || []);
            this.renderImprovements(data.improvements || []);
            this.renderStrengths(data.strengths || []);

            // Render keyword analysis
            this.renderKeywordAnalysis(data.keyword_analysis);

            // Render ATS analysis
            this.renderATSAnalysis(data.ats_analysis);

            // Render strategic options
            this.renderStrategicOptions(data.strategic_options || []);
        }

        renderScoreHero(data) {
            const score = data.score || 0;
            const risk = data.risk_level || 'unknown';
            const meta = data.meta || {};

            // Calculate circle progress
            const circumference = 2 * Math.PI * 70;
            const offset = circumference - (score / 100) * circumference;

            // Get score color
            const scoreColor = this.getScoreColor(score);

            const $hero = this.$results.find('.sffc-planner__score-hero');

            $hero.find('.sffc-planner__score-fill')
                .css({
                    'stroke': scoreColor,
                    'stroke-dasharray': circumference,
                    'stroke-dashoffset': circumference
                })
                .animate({ 'stroke-dashoffset': offset }, 1000);

            $hero.find('.sffc-planner__score-value').text(score);

            $hero.find('.sffc-planner__verdict-badge')
                .removeClass('sffc-planner__verdict-badge--strong sffc-planner__verdict-badge--competitive sffc-planner__verdict-badge--moderate sffc-planner__verdict-badge--high')
                .addClass(`sffc-planner__verdict-badge--${risk}`)
                .text(this.getRiskLabel(risk));

            $hero.find('.sffc-planner__verdict-title').text(
                `${meta.role_title || 'Role'} at ${meta.company || 'Company'}`
            );

            $hero.find('.sffc-planner__verdict-summary').text(
                this.getVerdictSummary(risk, score, data.dealbreakers?.length || 0)
            );
        }

        renderQuickStats(data) {
            const $stats = this.$results.find('.sffc-planner__quick-stats');

            const stats = [
                {
                    value: data.dealbreakers?.length || 0,
                    label: 'Dealbreakers',
                    class: (data.dealbreakers?.length || 0) > 0 ? 'danger' : 'success'
                },
                {
                    value: data.critical_gaps?.length || 0,
                    label: 'Critical Gaps',
                    class: (data.critical_gaps?.length || 0) > 2 ? 'warning' : 'success'
                },
                {
                    value: `${data.keyword_analysis?.match_rate || 0}%`,
                    label: 'Keyword Match',
                    class: (data.keyword_analysis?.match_rate || 0) >= 70 ? 'success' : 'warning'
                },
                {
                    value: data.ats_analysis?.percentage || 0,
                    label: 'ATS Score',
                    class: (data.ats_analysis?.percentage || 0) >= 70 ? 'success' : 'warning'
                }
            ];

            $stats.html(stats.map(stat => `
                <div class="sffc-planner__stat">
                    <div class="sffc-planner__stat-value sffc-planner__stat-value--${stat.class}">${stat.value}</div>
                    <div class="sffc-planner__stat-label">${stat.label}</div>
                </div>
            `).join(''));
        }

        renderCharts(data) {
            // Score breakdown chart
            this.renderScoreBreakdownChart(data.score_breakdown);

            // Keyword match chart
            this.renderKeywordChart(data.keyword_analysis);

            // ATS breakdown chart
            this.renderATSChart(data.ats_analysis);
        }

        renderScoreBreakdownChart(breakdown) {
            const $canvas = this.$results.find('[data-chart="score-breakdown"]');
            if (!$canvas.length || typeof Chart === 'undefined') return;

            if (this.charts.scoreBreakdown) {
                this.charts.scoreBreakdown.destroy();
            }

            const ctx = $canvas[0].getContext('2d');

            const penalties = breakdown?.penalties || [];
            const bonuses = breakdown?.bonuses || [];

            this.charts.scoreBreakdown = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Base', ...penalties.map(p => p.type), ...bonuses.map(b => b.type)],
                    datasets: [{
                        data: [
                            breakdown?.base || 100,
                            ...penalties.map(p => p.points),
                            ...bonuses.map(b => b.points)
                        ],
                        backgroundColor: [
                            '#6b7280',
                            ...penalties.map(() => '#ef4444'),
                            ...bonuses.map(() => '#10b981')
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    resizeDelay: 200,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: false,
                            min: -35,
                            max: 105
                        }
                    }
                }
            });
        }

        renderKeywordChart(keywordData) {
            const $canvas = this.$results.find('[data-chart="keywords"]');
            if (!$canvas.length || typeof Chart === 'undefined') return;

            if (this.charts.keywords) {
                this.charts.keywords.destroy();
            }

            const ctx = $canvas[0].getContext('2d');
            const matched = keywordData?.matched_keywords || 0;
            const total = keywordData?.total_jd_keywords || 0;
            const missing = total - matched;

            this.charts.keywords = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Matched', 'Missing'],
                    datasets: [{
                        data: [matched, missing],
                        backgroundColor: ['#10b981', '#ef4444'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    resizeDelay: 200,
                    cutout: '70%',
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        renderATSChart(atsData) {
            const $canvas = this.$results.find('[data-chart="ats"]');
            if (!$canvas.length || typeof Chart === 'undefined') return;

            if (this.charts.ats) {
                this.charts.ats.destroy();
            }

            const ctx = $canvas[0].getContext('2d');
            const breakdown = atsData?.breakdown || {};

            this.charts.ats = new Chart(ctx, {
                type: 'radar',
                data: {
                    labels: ['Keywords', 'Format', 'Structure', 'Title Match', 'Recency'],
                    datasets: [{
                        label: 'Your Score',
                        data: [
                            (breakdown.keywords?.score || 0) / 30 * 100,
                            (breakdown.format?.score || 0) / 25 * 100,
                            (breakdown.structure?.score || 0) / 20 * 100,
                            (breakdown.title_match?.score || 0) / 15 * 100,
                            (breakdown.recency?.score || 0) / 10 * 100
                        ],
                        backgroundColor: 'rgba(26, 26, 26, 0.1)',
                        borderColor: '#1a1a1a',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    resizeDelay: 200,
                    scales: {
                        r: {
                            beginAtZero: true,
                            max: 100,
                            ticks: { stepSize: 25 }
                        }
                    },
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
        }

        renderDealbreakers(dealbreakers) {
            const $section = this.$results.find('[data-section="dealbreakers"]');
            if (!dealbreakers.length) {
                $section.hide();
                return;
            }

            $section.show();
            $section.find('.sffc-planner__section-count').text(`(${dealbreakers.length})`);

            const $issues = $section.find('.sffc-planner__issues');
            $issues.html(dealbreakers.map(item => this.renderIssueCard(item, 'dealbreaker')).join(''));
        }

        renderCriticalGaps(gaps) {
            const $section = this.$results.find('[data-section="critical"]');
            if (!gaps.length) {
                $section.hide();
                return;
            }

            $section.show();
            $section.find('.sffc-planner__section-count').text(`(${gaps.length})`);

            const $issues = $section.find('.sffc-planner__issues');
            $issues.html(gaps.map(item => this.renderIssueCard(item, 'critical')).join(''));
        }

        renderImprovements(improvements) {
            const $section = this.$results.find('[data-section="improvements"]');
            if (!improvements.length) {
                $section.hide();
                return;
            }

            $section.show();
            $section.find('.sffc-planner__section-count').text(`(${improvements.length})`);

            const $issues = $section.find('.sffc-planner__issues');
            $issues.html(improvements.map(item => this.renderIssueCard(item, 'improvement')).join(''));
        }

        renderIssueCard(item, type) {
            const typeLabel = type === 'dealbreaker' ? 'Dealbreaker' :
                             type === 'critical' ? 'Critical' : 'Improvement';

            let comparisonHtml = '';
            if (item.requirement && item.candidate_has) {
                comparisonHtml = `
                    <div class="sffc-planner__issue-comparison">
                        <div class="sffc-planner__issue-required">
                            <strong>Required</strong>
                            ${this.escapeHtml(item.requirement)}
                        </div>
                        <div class="sffc-planner__issue-candidate">
                            <strong>You Have</strong>
                            ${this.escapeHtml(item.candidate_has)}
                        </div>
                    </div>
                `;
            }

            let fixHtml = '';
            const fix = item.fix_strategy || item.fix || item.suggested;
            if (fix) {
                fixHtml = `
                    <div class="sffc-planner__issue-fix">
                        <strong>How to fix:</strong> ${this.escapeHtml(fix)}
                    </div>
                `;
            }

            return `
                <div class="sffc-planner__issue sffc-planner__issue--${type}">
                    <div class="sffc-planner__issue-header">
                        <span class="sffc-planner__issue-type sffc-planner__issue-type--${type}">${typeLabel}</span>
                    </div>
                    <h4 class="sffc-planner__issue-title">${this.escapeHtml(item.title || 'Issue')}</h4>
                    <p class="sffc-planner__issue-body">${this.escapeHtml(item.gap_description || item.description || item.reality_check || '')}</p>
                    ${comparisonHtml}
                    ${fixHtml}
                </div>
            `;
        }

        renderStrengths(strengths) {
            const $section = this.$results.find('[data-section="strengths"]');
            if (!strengths.length) {
                $section.hide();
                return;
            }

            $section.show();

            const $container = $section.find('.sffc-planner__strengths');
            $container.html(strengths.map(item => `
                <div class="sffc-planner__strength">
                    <h4 class="sffc-planner__strength-title">${this.escapeHtml(item.title || 'Strength')}</h4>
                    <p class="sffc-planner__strength-body">${this.escapeHtml(item.description || '')}</p>
                    ${item.leverage ? `<p class="sffc-planner__strength-leverage"><strong>Leverage:</strong> ${this.escapeHtml(item.leverage)}</p>` : ''}
                </div>
            `).join(''));
        }

        renderKeywordAnalysis(keywordData) {
            if (!keywordData) return;

            const $section = this.$results.find('[data-section="keywords"]');
            $section.find('.sffc-planner__keywords-rate').text(`${keywordData.match_rate}%`);
            $section.find('.sffc-planner__keywords-meta').text(
                `${keywordData.matched_keywords} of ${keywordData.total_jd_keywords} keywords found`
            );

            const $grid = $section.find('.sffc-planner__keywords-grid');
            const details = keywordData.details || {};

            $grid.html(Object.values(details).map(kw => `
                <span class="sffc-planner__keyword sffc-planner__keyword--${kw.match ? 'match' : 'missing'}">
                    <span class="sffc-planner__keyword-dot"></span>
                    ${this.escapeHtml(kw.keyword)}
                </span>
            `).join(''));
        }

        renderATSAnalysis(atsData) {
            if (!atsData) return;

            const $section = this.$results.find('[data-section="ats"]');
            const $ats = $section.find('.sffc-planner__ats');

            $ats.find('.sffc-planner__ats-score').text(`${atsData.percentage}%`);

            const verdictClass = atsData.verdict === 'likely_pass' ? 'pass' :
                                atsData.verdict === 'at_risk' ? 'risk' : 'fail';
            const verdictText = atsData.verdict === 'likely_pass' ? 'Likely Pass' :
                               atsData.verdict === 'at_risk' ? 'At Risk' : 'Likely Fail';

            $ats.find('.sffc-planner__ats-verdict')
                .removeClass('sffc-planner__ats-verdict--pass sffc-planner__ats-verdict--risk sffc-planner__ats-verdict--fail')
                .addClass(`sffc-planner__ats-verdict--${verdictClass}`)
                .text(verdictText);

            // Render breakdown
            const breakdown = atsData.breakdown || {};
            const $breakdown = $ats.find('.sffc-planner__ats-breakdown');

            $breakdown.html(`
                <div class="sffc-planner__ats-metric">
                    <div class="sffc-planner__ats-metric-value">${breakdown.keywords?.score || 0}/30</div>
                    <div class="sffc-planner__ats-metric-label">Keywords</div>
                </div>
                <div class="sffc-planner__ats-metric">
                    <div class="sffc-planner__ats-metric-value">${breakdown.format?.score || 0}/25</div>
                    <div class="sffc-planner__ats-metric-label">Format</div>
                </div>
                <div class="sffc-planner__ats-metric">
                    <div class="sffc-planner__ats-metric-value">${breakdown.structure?.score || 0}/20</div>
                    <div class="sffc-planner__ats-metric-label">Structure</div>
                </div>
                <div class="sffc-planner__ats-metric">
                    <div class="sffc-planner__ats-metric-value">${breakdown.title_match?.score || 0}/15</div>
                    <div class="sffc-planner__ats-metric-label">Title</div>
                </div>
                <div class="sffc-planner__ats-metric">
                    <div class="sffc-planner__ats-metric-value">${breakdown.recency?.score || 0}/10</div>
                    <div class="sffc-planner__ats-metric-label">Recency</div>
                </div>
            `);

            // Render issues
            const issues = atsData.issues || [];
            const $issues = $ats.find('.sffc-planner__ats-issues');

            if (issues.length) {
                $issues.html(`
                    <h4 style="margin-bottom: 12px; font-size: 0.875rem;">Issues to Address</h4>
                    ${issues.slice(0, 5).map(issue => `
                        <div class="sffc-planner__ats-issue">
                            <span class="sffc-planner__ats-issue-severity sffc-planner__ats-issue-severity--${issue.severity}"></span>
                            <div>
                                <strong>${this.escapeHtml(issue.message)}</strong>
                                ${issue.fix ? `<br><em>${this.escapeHtml(issue.fix)}</em>` : ''}
                            </div>
                        </div>
                    `).join('')}
                `);
            }
        }

        renderStrategicOptions(options) {
            const $section = this.$results.find('[data-section="strategies"]');
            if (!options.length) {
                $section.hide();
                return;
            }

            $section.show();

            const $container = $section.find('.sffc-planner__strategies');
            $container.html(options.map((option, idx) => `
                <div class="sffc-planner__strategy">
                    <span class="sffc-planner__strategy-number">${idx + 1}</span>
                    <div class="sffc-planner__strategy-content">
                        <h4 class="sffc-planner__strategy-title">${this.escapeHtml(option.title || 'Option')}</h4>
                        <p class="sffc-planner__strategy-body">${this.escapeHtml(option.description || '')}</p>
                        ${option.success_probability ? `<span class="sffc-planner__strategy-probability">Success: ${this.escapeHtml(option.success_probability)}</span>` : ''}
                    </div>
                </div>
            `).join(''));
        }

        async exportPDF() {
            if (!this.analysisResult || typeof html2pdf === 'undefined') {
                this.showError('Unable to export PDF. Please run analysis first.');
                return;
            }

            const $btn = this.$container.find('[data-action="export-pdf"]');
            $btn.addClass('is-loading').prop('disabled', true);

            try {
                const response = await $.ajax({
                    url: sffc_planner.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'sffc_export_planner_pdf',
                        nonce: sffc_planner.nonce,
                        analysis: JSON.stringify(this.analysisResult)
                    }
                });

                if (response.success) {
                    // Create PDF from HTML
                    const $temp = $('<div>').html(response.data.html);

                    const opt = {
                        margin: 10,
                        filename: response.data.filename,
                        image: { type: 'jpeg', quality: 0.98 },
                        html2canvas: { scale: 2 },
                        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
                    };

                    await html2pdf().set(opt).from($temp[0]).save();
                } else {
                    this.showError('Failed to generate PDF');
                }
            } catch (error) {
                console.error('PDF export error:', error);
                this.showError('Failed to export PDF');
            } finally {
                $btn.removeClass('is-loading').prop('disabled', false);
            }
        }

        async saveReport() {
            if (!this.analysisResult) {
                this.showError('No analysis to save');
                return;
            }

            const $btn = this.$container.find('[data-action="save-report"]');
            $btn.addClass('is-loading').prop('disabled', true);

            try {
                const response = await $.ajax({
                    url: sffc_planner.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'sffc_save_planner_report',
                        nonce: sffc_planner.nonce,
                        analysis: JSON.stringify(this.analysisResult)
                    }
                });

                if (response.success) {
                    this.showSuccess('Report saved successfully');
                    this.loadSavedReports();
                } else {
                    this.showError(response.data?.message || 'Failed to save report');
                }
            } catch (error) {
                console.error('Save error:', error);
                this.showError('Failed to save report');
            } finally {
                $btn.removeClass('is-loading').prop('disabled', false);
            }
        }

        async loadSavedReports() {
            const $section = this.$container.find('.sffc-planner__saved-reports');

            try {
                const response = await $.ajax({
                    url: sffc_planner.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'sffc_get_planner_reports',
                        nonce: sffc_planner.nonce
                    }
                });

                if (response.success && response.data.reports?.length) {
                    $section.show();
                    const $list = $section.find('.sffc-planner__reports-list');

                    $list.html(response.data.reports.map(report => `
                        <div class="sffc-planner__report-card" data-report-id="${report.id}">
                            <div class="sffc-planner__report-info">
                                <span class="sffc-planner__report-title">${this.escapeHtml(report.role_title)} at ${this.escapeHtml(report.company)}</span>
                                <span class="sffc-planner__report-meta">${report.created_at}</span>
                            </div>
                            <div class="sffc-planner__report-score sffc-planner__stat-value--${this.getScoreClass(report.score)}">${report.score}</div>
                            <div class="sffc-planner__report-actions">
                                <button class="sffc-planner__btn sffc-planner__btn--sm sffc-planner__btn--secondary" data-action="view-report">View</button>
                                <button class="sffc-planner__btn sffc-planner__btn--sm sffc-planner__btn--secondary" data-action="delete-report">Delete</button>
                            </div>
                        </div>
                    `).join(''));
                } else {
                    $section.hide();
                }
            } catch (error) {
                console.error('Load reports error:', error);
            }
        }

        async showComparisonModal() {
            // Load CRM jobs
            try {
                const response = await $.ajax({
                    url: sffc_planner.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'sffc_get_crm_jobs_for_comparison',
                        nonce: sffc_planner.nonce
                    }
                });

                if (response.success) {
                    this.renderComparisonModal(response.data.jobs);
                } else {
                    this.showError(response.data?.message || 'Failed to load jobs');
                }
            } catch (error) {
                console.error('Load CRM jobs error:', error);
                this.showError('Failed to load saved jobs');
            }
        }

        renderComparisonModal(jobs) {
            // Implementation for comparison modal
            const $modal = $(`
                <div class="sffc-planner__modal">
                    <div class="sffc-planner__modal-content">
                        <h3>Compare Against Multiple Jobs</h3>
                        <p>Select up to 5 jobs from your CRM to compare:</p>
                        <div class="sffc-planner__job-select">
                            ${jobs.map(job => `
                                <div class="sffc-planner__job-chip" data-job-select data-job-id="${job.id}" data-description="${this.escapeHtml(job.description || '')}">
                                    ${this.escapeHtml(job.title)} - ${this.escapeHtml(job.company)}
                                </div>
                            `).join('')}
                        </div>
                        <div class="sffc-planner__modal-actions">
                            <button class="sffc-planner__btn" data-action="run-comparison">Compare Selected</button>
                            <button class="sffc-planner__btn sffc-planner__btn--secondary" data-action="close-modal">Cancel</button>
                        </div>
                    </div>
                </div>
            `);

            $modal.on('click', '[data-action="close-modal"]', () => $modal.remove());
            this.$container.append($modal);
        }

        toggleJobSelection(event) {
            const $chip = $(event.currentTarget);
            const selectedCount = this.$container.find('.sffc-planner__job-chip.is-selected').length;

            if (!$chip.hasClass('is-selected') && selectedCount >= 5) {
                this.showError('Maximum 5 jobs can be compared at once');
                return;
            }

            $chip.toggleClass('is-selected');
        }

        async runComparison() {
            const $selected = this.$container.find('.sffc-planner__job-chip.is-selected');

            if ($selected.length < 2) {
                this.showError('Select at least 2 jobs to compare');
                return;
            }

            const jobs = [];
            $selected.each(function() {
                jobs.push({
                    id: $(this).data('job-id'),
                    title: $(this).text().split(' - ')[0].trim(),
                    company: $(this).text().split(' - ')[1]?.trim() || '',
                    description: $(this).data('description')
                });
            });

            try {
                const response = await $.ajax({
                    url: sffc_planner.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'sffc_compare_multiple_jobs',
                        nonce: sffc_planner.nonce,
                        cv_text: this.cvText,
                        jobs: JSON.stringify(jobs)
                    }
                });

                if (response.success) {
                    this.$container.find('.sffc-planner__modal').remove();
                    this.renderComparisonResults(response.data);
                } else {
                    this.showError(response.data?.message || 'Comparison failed');
                }
            } catch (error) {
                console.error('Comparison error:', error);
                this.showError('Failed to compare jobs');
            }
        }

        renderComparisonResults(data) {
            const $comparison = this.$results.find('.sffc-planner__comparison');
            $comparison.show();

            const $results = $comparison.find('.sffc-planner__comparison-results');

            $results.html(data.results.map(result => `
                <div class="sffc-planner__comparison-card">
                    <div class="sffc-planner__comparison-job">
                        <span class="sffc-planner__comparison-job-title">${this.escapeHtml(result.title)}</span>
                        <span class="sffc-planner__comparison-job-company">${this.escapeHtml(result.company)}</span>
                    </div>
                    <div class="sffc-planner__comparison-score">
                        <div class="sffc-planner__comparison-score-value" style="color: ${this.getScoreColor(result.score)}">${result.score || 'N/A'}</div>
                        <div class="sffc-planner__comparison-score-badge">${this.getRiskLabel(result.risk_level)}</div>
                    </div>
                </div>
            `).join(''));

            // Add recommendation
            if (data.recommendation) {
                $comparison.find('.sffc-planner__comparison-recommendation').html(`
                    <div class="sffc-planner__issue sffc-planner__issue--${data.recommendation.type === 'positive' ? 'strength' : 'improvement'}">
                        <h4 class="sffc-planner__issue-title">${this.escapeHtml(data.recommendation.headline)}</h4>
                        <p class="sffc-planner__issue-body">${this.escapeHtml(data.recommendation.message)}</p>
                    </div>
                `);
            }
        }

        async viewReport(event) {
            const $card = $(event.target).closest('.sffc-planner__report-card');
            const reportId = $card.data('report-id');

            if (!reportId) return;

            try {
                const response = await $.ajax({
                    url: sffc_planner.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'sffc_get_planner_report',
                        nonce: sffc_planner.nonce,
                        report_id: reportId
                    }
                });

                if (response.success && response.data.analysis_data) {
                    this.analysisResult = response.data.analysis_data;
                    this.renderResults(response.data.analysis_data);

                    // Scroll to results
                    $('html, body').animate({
                        scrollTop: this.$results.offset().top - 100
                    }, 500);
                } else {
                    this.showError('Failed to load report');
                }
            } catch (error) {
                console.error('View report error:', error);
                this.showError('Failed to load report');
            }
        }

        async deleteReport(event) {
            const $card = $(event.target).closest('.sffc-planner__report-card');
            const reportId = $card.data('report-id');

            if (!reportId) return;

            if (!confirm('Are you sure you want to delete this report?')) {
                return;
            }

            try {
                const response = await $.ajax({
                    url: sffc_planner.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'sffc_delete_planner_report',
                        nonce: sffc_planner.nonce,
                        report_id: reportId
                    }
                });

                if (response.success) {
                    $card.fadeOut(300, () => {
                        $card.remove();
                        // Hide section if no more reports
                        if (!this.$container.find('.sffc-planner__report-card').length) {
                            this.$container.find('.sffc-planner__saved-reports').hide();
                        }
                    });
                    this.showSuccess('Report deleted');
                } else {
                    this.showError(response.data?.message || 'Failed to delete report');
                }
            } catch (error) {
                console.error('Delete report error:', error);
                this.showError('Failed to delete report');
            }
        }

        // Utility methods
        getScoreColor(score) {
            if (score >= 80) return '#059669';
            if (score >= 60) return '#2563eb';
            if (score >= 40) return '#d97706';
            return '#dc2626';
        }

        getScoreClass(score) {
            if (score >= 80) return 'success';
            if (score >= 60) return 'warning';
            return 'danger';
        }

        getRiskLabel(risk) {
            const labels = {
                'strong': 'Strong Match',
                'competitive': 'Competitive',
                'moderate': 'Moderate Risk',
                'high': 'High Risk'
            };
            return labels[risk] || 'Unknown';
        }

        getVerdictSummary(risk, score, dealbreakers) {
            if (dealbreakers > 0) {
                return `Your application has ${dealbreakers} dealbreaker${dealbreakers > 1 ? 's' : ''} that significantly reduce your chances. Address these critical issues before applying.`;
            }

            const summaries = {
                'strong': 'You are a strong match for this role. Your profile aligns well with the requirements. Apply promptly and prepare for interviews.',
                'competitive': 'You are a competitive candidate with minor gaps. Make targeted improvements to your CV to maximize your chances.',
                'moderate': 'There are some notable gaps between your profile and this role. Consider whether this role is the right target.',
                'high': 'Significant gaps exist between your profile and this role. Consider targeting roles that better match your experience.'
            };

            return summaries[risk] || 'Analysis complete.';
        }

        escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        showError(message) {
            this.showNotification(message, 'error');
        }

        showSuccess(message) {
            this.showNotification(message, 'success');
        }

        showNotification(message, type = 'info') {
            const $notification = $(`
                <div class="sffc-planner__notification sffc-planner__notification--${type}">
                    ${this.escapeHtml(message)}
                </div>
            `);

            this.$container.prepend($notification);

            setTimeout(() => {
                $notification.fadeOut(300, () => $notification.remove());
            }, 4000);
        }
    }

    // Initialize on document ready
    $(document).ready(function() {
        $('.sffc-planner').each(function() {
            new ApplicationPlanner(this);
        });
    });

})(jQuery);
