/**
 * Bulk CV Analyzer - Client-side text extraction and analysis
 *
 * Extracts text from PDF, DOCX, and TXT files using:
 * - PDF.js for PDFs
 * - Mammoth.js for DOCX
 * - FileReader for TXT
 */

(function($) {
    'use strict';

    if (typeof sffc_bulk_cv_analyzer === 'undefined') {
        console.error('Bulk CV Analyzer: sffc_bulk_cv_analyzer is not defined.');
        return;
    }

    // Configure PDF.js worker
    if (typeof pdfjsLib !== 'undefined') {
        pdfjsLib.GlobalWorkerOptions.workerSrc = sffc_bulk_cv_analyzer.pdfjs_worker;
    }

    class BulkCVAnalyzer {
        constructor(container) {
            this.$container = $(container);
            this.jdText = '';
            this.cvFiles = []; // Array of {name, text, file}
            this.analysisResult = null;
            this.shortlist = []; // Array of candidate IDs
            this.aiSummaries = null; // AI summaries for shortlisted candidates

            this.init();
        }

        init() {
            this.bindEvents();
        }

        bindEvents() {
            // JD textarea input
            this.$container.on('input', '[data-input="jd"]', (e) => {
                this.jdText = $(e.target).val().trim();
                this.updateAnalyzeButton();
            });

            // Trigger file upload
            this.$container.on('click', '[data-trigger-upload]', () => {
                this.$container.find('[data-file-input]').click();
            });

            // File selection
            this.$container.on('change', '[data-file-input]', (e) => {
                const files = e.target.files;
                if (files && files.length > 0) {
                    this.handleFileSelection(files);
                }
            });

            // Clear all files
            this.$container.on('click', '[data-clear-files]', () => {
                this.clearAllFiles();
            });

            // Remove individual file
            this.$container.on('click', '[data-remove-file]', (e) => {
                const index = $(e.currentTarget).data('remove-file');
                this.removeFile(index);
            });

            // Analyze button
            this.$container.on('click', '[data-action="analyze"]', () => {
                this.runBulkAnalysis();
            });

            // Reset
            this.$container.on('click', '[data-action="reset"]', () => {
                this.reset();
            });

            // Export
            this.$container.on('click', '[data-action="export"]', () => {
                this.exportResults();
            });

            // View candidate details
            this.$container.on('click', '[data-view-candidate]', (e) => {
                const index = $(e.currentTarget).data('view-candidate');
                this.viewCandidateDetails(index);
            });

            // Shortlist checkbox toggle
            this.$container.on('change', '[data-shortlist-checkbox]', (e) => {
                const id = parseInt($(e.currentTarget).data('shortlist-checkbox'));
                this.toggleShortlist(id);
            });

            // Clear shortlist
            this.$container.on('click', '[data-action="clear-shortlist"]', () => {
                this.clearShortlist();
            });

            // Generate AI summaries
            this.$container.on('click', '[data-action="generate-summaries"]', () => {
                this.generateAISummaries();
            });

            // Export PDF
            this.$container.on('click', '[data-action="export-pdf"]', () => {
                this.exportPDF();
            });
        }

        async handleFileSelection(files) {
            this.showToast(`Processing ${files.length} file(s)...`);

            const filesArray = Array.from(files);
            let processed = 0;
            let failed = 0;

            for (const file of filesArray) {
                try {
                    const text = await this.extractTextFromFile(file);
                    if (text && text.trim().length > 50) {
                        this.cvFiles.push({
                            name: file.name,
                            text: text.trim(),
                            file: file,
                        });
                        processed++;
                    } else {
                        console.error('File text too short or empty:', file.name);
                        failed++;
                    }
                } catch (error) {
                    console.error('Failed to extract text from file:', file.name, error);
                    failed++;
                }
            }

            if (processed > 0) {
                this.showToast(`✓ Extracted text from ${processed} CV(s)${failed > 0 ? `, ${failed} failed` : ''}`);
                this.renderFilesList();
                this.updateAnalyzeButton();
            } else {
                this.showError('Failed to extract text from any files. Please try different files.');
            }

            // Reset file input
            this.$container.find('[data-file-input]').val('');
        }

        async extractTextFromFile(file) {
            const fileName = file.name.toLowerCase();

            if (fileName.endsWith('.pdf')) {
                return await this.extractFromPDF(file);
            } else if (fileName.endsWith('.docx')) {
                return await this.extractFromDOCX(file);
            } else if (fileName.endsWith('.txt') || fileName.endsWith('.doc')) {
                return await this.extractFromText(file);
            } else {
                throw new Error('Unsupported file type');
            }
        }

        async extractFromPDF(file) {
            if (typeof pdfjsLib === 'undefined') {
                throw new Error('PDF.js not loaded');
            }

            const arrayBuffer = await file.arrayBuffer();
            const pdf = await pdfjsLib.getDocument({data: arrayBuffer}).promise;

            let fullText = '';
            for (let i = 1; i <= pdf.numPages; i++) {
                const page = await pdf.getPage(i);
                const textContent = await page.getTextContent();
                const pageText = textContent.items.map(item => item.str).join(' ');
                fullText += pageText + '\n';
            }

            return fullText;
        }

        async extractFromDOCX(file) {
            if (typeof mammoth === 'undefined') {
                throw new Error('Mammoth.js not loaded');
            }

            const arrayBuffer = await file.arrayBuffer();
            const result = await mammoth.extractRawText({arrayBuffer: arrayBuffer});
            return result.value;
        }

        async extractFromText(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = (e) => resolve(e.target.result);
                reader.onerror = (e) => reject(e);
                reader.readAsText(file);
            });
        }

        renderFilesList() {
            const $list = this.$container.find('[data-files-list]');
            const $items = this.$container.find('[data-files-items]');
            const $count = this.$container.find('[data-file-count]');
            const $cvCount = this.$container.find('[data-cv-count]');

            if (this.cvFiles.length === 0) {
                $list.hide();
                $cvCount.text('Step 2');
                return;
            }

            $list.show();
            $count.text(this.cvFiles.length);
            $cvCount.text(`${this.cvFiles.length} CVs`);

            let html = '';
            this.cvFiles.forEach((cv, index) => {
                const wordCount = cv.text.split(/\s+/).length;
                html += `
                    <div class="bulk-cv-file-item">
                        <div class="bulk-cv-file-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                            </svg>
                        </div>
                        <div class="bulk-cv-file-info">
                            <div class="bulk-cv-file-name">${this.escapeHtml(cv.name)}</div>
                            <div class="bulk-cv-file-meta">${wordCount} words extracted</div>
                        </div>
                        <button type="button" class="bulk-cv-file-remove" data-remove-file="${index}" title="Remove">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"/>
                                <line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                        </button>
                    </div>
                `;
            });

            $items.html(html);
        }

        removeFile(index) {
            this.cvFiles.splice(index, 1);
            this.renderFilesList();
            this.updateAnalyzeButton();
        }

        clearAllFiles() {
            this.cvFiles = [];
            this.renderFilesList();
            this.updateAnalyzeButton();
            this.showToast('All files cleared');
        }

        updateAnalyzeButton() {
            const $message = this.$container.find('[data-analyze-message]');
            const $hint = this.$container.find('[data-chatbox-hint]');

            if (!this.jdText) {
                $message.text('Paste your job description above to begin.');
                $hint.text('Add job description');
            } else if (this.cvFiles.length === 0) {
                $message.text('Upload CV files to continue.');
                $hint.text('Upload CVs');
            } else {
                $message.text(`Ready to analyze ${this.cvFiles.length} candidate(s). Click here to begin.`);
                $hint.text(`${this.cvFiles.length} CVs ready`);
            }
        }

        async runBulkAnalysis() {
            if (!this.jdText || this.cvFiles.length === 0) {
                this.showError('Please provide a job description and upload at least one CV.');
                return;
            }

            if (this.jdText.length < 100) {
                this.showError('Job description is too short. Please paste the full job description.');
                return;
            }

            const $btns = this.$container.find('[data-action="analyze"]');
            $btns.addClass('is-loading').prop('disabled', true);

            this.showLoader();

            try {
                // Prepare CV data for backend
                const cvData = this.cvFiles.map(cv => ({
                    name: cv.name.replace(/\.(pdf|docx?|txt)$/i, ''),
                    text: cv.text,
                }));

                const response = await $.ajax({
                    url: sffc_bulk_cv_analyzer.ajax_url,
                    type: 'POST',
                    timeout: 120000, // 2 minutes
                    dataType: 'json',
                    data: {
                        action: 'sffc_bulk_analyze_cvs',
                        nonce: sffc_bulk_cv_analyzer.nonce,
                        jd_text: this.jdText,
                        cv_data: JSON.stringify(cvData),
                    },
                });

                this.hideLoader();

                if (response.success) {
                    this.analysisResult = response.data;
                    this.renderResults(response.data);
                } else {
                    this.showError(response.data?.message || 'Analysis failed. Please try again.');
                }

            } catch (error) {
                console.error('Bulk analysis error:', error);
                this.hideLoader();

                if (error.statusText === 'timeout') {
                    this.showError('Analysis timed out. Please try with fewer CVs or shorter documents.');
                } else {
                    this.showError('Failed to analyze CVs. Please try again.');
                }
            } finally {
                $btns.removeClass('is-loading').prop('disabled', false);
            }
        }

        renderResults(data) {
            const $list = this.$container.find('[data-candidates-list]');
            const $stats = this.$container.find('[data-stats-summary]');
            const $exportBtn = this.$container.find('[data-action="export"]');

            // Show stats
            $stats.show();
            $stats.find('[data-stat="strong"]').text(data.stats.strong_fit_count);
            $stats.find('[data-stat="competitive"]').text(data.stats.competitive_count);
            $stats.find('[data-stat="moderate"]').text(data.stats.moderate_count);
            $stats.find('[data-stat="avg"]').text(data.stats.avg_score + '%');

            // Show export button
            $exportBtn.show();

            // Render candidates
            let html = '';

            if (data.results.length === 0) {
                html = `
                    <div class="inst-chart-narrative">
                        <div class="inst-chart-narrative-header">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="12" y1="8" x2="12" y2="12"/>
                                <line x1="12" y1="16" x2="12.01" y2="16"/>
                            </svg>
                            <span>No Results</span>
                        </div>
                        <p>No candidates could be analyzed.</p>
                    </div>
                `;
            } else {
                data.results.forEach((candidate, index) => {
                    const riskClass = this.getRiskClass(candidate.risk_level);
                    const recClass = this.getRecommendationClass(candidate.recommendation);
                    const icon = this.getRecommendationIcon(candidate.recommendation);

                    html += `
                        <div class="bulk-candidate-card ${riskClass}" data-candidate-id="${candidate.id}">
                            <input type="checkbox" class="bulk-candidate-checkbox" data-shortlist-checkbox="${candidate.id}" title="Add to shortlist">
                            <div class="bulk-candidate-header">
                                <div class="bulk-candidate-rank">#${candidate.rank}</div>
                                <div class="bulk-candidate-info">
                                    <h3 class="bulk-candidate-name">${this.escapeHtml(candidate.name)}</h3>
                                    <div class="bulk-candidate-meta">
                                        <span class="bulk-candidate-score">${candidate.score}% match</span>
                                        <span class="bulk-candidate-rec ${recClass}">${icon} ${this.formatRecommendation(candidate.recommendation)}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="bulk-candidate-stats">
                                <div class="bulk-candidate-stat">
                                    <span class="bulk-candidate-stat-label">Keywords</span>
                                    <span class="bulk-candidate-stat-value">${candidate.keyword_match_rate}%</span>
                                </div>
                                <div class="bulk-candidate-stat">
                                    <span class="bulk-candidate-stat-label">Red Flags</span>
                                    <span class="bulk-candidate-stat-value ${candidate.dealbreaker_count > 0 ? 'text-danger' : ''}">${candidate.dealbreaker_count}</span>
                                </div>
                                <div class="bulk-candidate-stat">
                                    <span class="bulk-candidate-stat-label">Issues</span>
                                    <span class="bulk-candidate-stat-value ${candidate.critical_count > 0 ? 'text-warning' : ''}">${candidate.critical_count}</span>
                                </div>
                            </div>
                            <div class="bulk-candidate-actions">
                                <button type="button" class="bulk-candidate-btn bulk-candidate-btn--secondary" data-view-candidate="${candidate.id}">
                                    View Details
                                </button>
                            </div>
                        </div>
                    `;
                });
            }

            $list.html(html);

            // Update chatbox
            const topScore = data.top_candidate ? data.top_candidate.score : 0;
            this.$container.find('[data-chatbox-hint]').text(`Top: ${topScore}% match`);

            // Scroll to results
            $('html, body').animate({
                scrollTop: this.$container.find('.inst-charts-panel').offset().top - 20
            }, 500);

            this.showToast(`✓ Ranked ${data.processed} candidate(s)`);
        }

        getRiskClass(riskLevel) {
            const map = {
                'strong': 'risk-strong',
                'competitive': 'risk-competitive',
                'moderate': 'risk-moderate',
                'high': 'risk-high',
            };
            return map[riskLevel] || 'risk-moderate';
        }

        getRecommendationClass(rec) {
            if (rec.includes('PRIORITY') || rec.includes('STRONG')) return 'rec-priority';
            if (rec.includes('CONSIDER')) return 'rec-consider';
            if (rec.includes('WEAK') || rec.includes('NOT')) return 'rec-weak';
            return 'rec-neutral';
        }

        getRecommendationIcon(rec) {
            if (rec.includes('PRIORITY')) return '⭐';
            if (rec.includes('STRONG')) return '✓';
            if (rec.includes('CONSIDER')) return '⚠';
            return '•';
        }

        formatRecommendation(rec) {
            return rec.replace(/_/g, ' ').toLowerCase()
                .split(' ')
                .map(word => word.charAt(0).toUpperCase() + word.slice(1))
                .join(' ');
        }

        viewCandidateDetails(candidateId) {
            // Find candidate in results
            if (!this.analysisResult || !this.analysisResult.results) {
                return;
            }

            const candidate = this.analysisResult.results.find(c => c.id === candidateId);
            if (!candidate) {
                return;
            }

            // For now, just show an alert with details
            // In future, could open modal or new page
            alert(`Candidate Details:\n\nName: ${candidate.name}\nScore: ${candidate.score}%\nRisk: ${candidate.risk_level}\nRecommendation: ${this.formatRecommendation(candidate.recommendation)}\n\nDealbreakers: ${candidate.dealbreaker_count}\nCritical Issues: ${candidate.critical_count}\nKeyword Match: ${candidate.keyword_match_rate}%`);
        }

        exportResults() {
            if (!this.analysisResult || !this.analysisResult.results) {
                this.showError('No results to export');
                return;
            }

            // Generate CSV
            let csv = 'Rank,Candidate Name,Score,Risk Level,Recommendation,Dealbreakers,Critical Issues,Keyword Match\n';

            this.analysisResult.results.forEach(c => {
                csv += `${c.rank},"${c.name.replace(/"/g, '""')}",${c.score},${c.risk_level},${c.recommendation},${c.dealbreaker_count},${c.critical_count},${c.keyword_match_rate}%\n`;
            });

            // Download
            const blob = new Blob([csv], {type: 'text/csv'});
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `cv-screening-results-${Date.now()}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);

            this.showToast('✓ Results exported to CSV');
        }

        reset() {
            if (!confirm('Clear all data and start over?')) {
                return;
            }

            this.jdText = '';
            this.cvFiles = [];
            this.analysisResult = null;

            this.$container.find('[data-input="jd"]').val('');
            this.renderFilesList();
            this.$container.find('[data-candidates-list]').html(`
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
            `);
            this.$container.find('[data-stats-summary]').hide();
            this.$container.find('[data-action="export"]').hide();
            this.updateAnalyzeButton();

            this.showToast('Reset complete');
        }

        showLoader() {
            this.$container.find('[data-loader="bulk-analysis"]').fadeIn(200);
            this.$container.find('[data-loader-status]').text(`Analyzing ${this.cvFiles.length} candidate(s)...`);
        }

        hideLoader() {
            this.$container.find('[data-loader="bulk-analysis"]').fadeOut(300);
        }

        showToast(message) {
            // Simple toast notification
            const $toast = $('<div class="bulk-toast">' + this.escapeHtml(message) + '</div>');
            $('body').append($toast);
            setTimeout(() => $toast.addClass('show'), 10);
            setTimeout(() => {
                $toast.removeClass('show');
                setTimeout(() => $toast.remove(), 300);
            }, 3000);
        }

        showError(message) {
            alert(message);
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // ===== SHORTLIST MANAGEMENT =====

        toggleShortlist(candidateId) {
            const index = this.shortlist.indexOf(candidateId);
            if (index > -1) {
                this.shortlist.splice(index, 1);
                this.$container.find(`[data-candidate-id="${candidateId}"]`).removeClass('is-shortlisted');
            } else {
                this.shortlist.push(candidateId);
                this.$container.find(`[data-candidate-id="${candidateId}"]`).addClass('is-shortlisted');
            }
            this.updateShortlistUI();
        }

        clearShortlist() {
            this.shortlist = [];
            this.aiSummaries = null;
            this.$container.find('.bulk-candidate-card').removeClass('is-shortlisted');
            this.$container.find('[data-shortlist-checkbox]').prop('checked', false);
            this.updateShortlistUI();
            this.showToast('Shortlist cleared');
        }

        updateShortlistUI() {
            const $actions = this.$container.find('[data-shortlist-actions]');
            const $count = this.$container.find('[data-shortlist-count]');
            const $pdfBtn = this.$container.find('[data-action="export-pdf"]');

            $count.text(this.shortlist.length);

            if (this.shortlist.length > 0) {
                $actions.show();
                // Show PDF button only if we have AI summaries
                if (this.aiSummaries && this.aiSummaries.summaries && this.aiSummaries.summaries.length > 0) {
                    $pdfBtn.show();
                } else {
                    $pdfBtn.hide();
                }
            } else {
                $actions.hide();
            }
        }

        // ===== AI SUMMARIES =====

        async generateAISummaries() {
            if (this.shortlist.length === 0) {
                this.showError('Please select candidates for shortlist first.');
                return;
            }

            if (!this.jdText || !this.analysisResult) {
                this.showError('Analysis data not available. Please run analysis first.');
                return;
            }

            const $btn = this.$container.find('[data-action="generate-summaries"]');
            $btn.addClass('is-loading').prop('disabled', true).text('Generating...');

            this.showLoader();
            this.$container.find('[data-loader-status]').text(`Generating AI summaries for ${this.shortlist.length} candidate(s)...`);

            try {
                const response = await $.ajax({
                    url: sffc_bulk_cv_analyzer.ajax_url,
                    type: 'POST',
                    timeout: 180000, // 3 minutes for AI analysis
                    dataType: 'json',
                    data: {
                        action: 'sffc_generate_candidate_summaries',
                        nonce: sffc_bulk_cv_analyzer.nonce,
                        jd_text: this.jdText,
                        candidate_ids: JSON.stringify(this.shortlist),
                        all_results: JSON.stringify(this.analysisResult.results),
                    },
                });

                this.hideLoader();

                if (response.success) {
                    this.aiSummaries = response.data;
                    this.showToast(`✓ Generated summaries for ${response.data.processed} candidate(s)`);
                    this.updateShortlistUI(); // Show PDF button
                    this.renderAISummaries(response.data.summaries);
                } else {
                    this.showError(response.data?.message || 'Failed to generate summaries.');
                }

            } catch (error) {
                console.error('AI Summaries error:', error);
                this.hideLoader();
                if (error.statusText === 'timeout') {
                    this.showError('Summary generation timed out. Please try with fewer candidates.');
                } else {
                    this.showError('Failed to generate summaries. Please try again.');
                }
            } finally {
                $btn.removeClass('is-loading').prop('disabled', false).html(`
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px; margin-right: 6px;">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M12 6v6l4 2"/>
                    </svg>
                    Generate AI Summaries
                `);
            }
        }

        renderAISummaries(summaries) {
            // Add AI summary indicators to candidate cards
            summaries.forEach(summary => {
                const $card = this.$container.find(`[data-candidate-id="${summary.id}"]`);
                if ($card.length) {
                    // Add a badge or indicator
                    if (!$card.find('.ai-summary-badge').length) {
                        $card.find('.bulk-candidate-header').append(`
                            <span class="ai-summary-badge" style="background: #fbbf24; color: #78350f; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600;">
                                AI Summary Ready
                            </span>
                        `);
                    }
                }
            });

            this.showToast('✓ AI summaries ready for export');
        }

        // ===== PDF EXPORT =====

        async exportPDF() {
            if (!this.aiSummaries || !this.aiSummaries.summaries || this.aiSummaries.summaries.length === 0) {
                this.showError('Please generate AI summaries first.');
                return;
            }

            const $btn = this.$container.find('[data-action="export-pdf"]');
            $btn.addClass('is-loading').prop('disabled', true).text('Generating PDF...');

            try {
                const response = await $.ajax({
                    url: sffc_bulk_cv_analyzer.ajax_url,
                    type: 'POST',
                    timeout: 60000,
                    dataType: 'json',
                    data: {
                        action: 'sffc_export_shortlist_pdf',
                        nonce: sffc_bulk_cv_analyzer.nonce,
                        jd_text: this.jdText,
                        summaries: JSON.stringify(this.aiSummaries.summaries),
                    },
                });

                if (response.success && response.data.html) {
                    // Open PDF in new window for printing
                    const pdfWindow = window.open('', '_blank');
                    pdfWindow.document.write(response.data.html);
                    pdfWindow.document.close();

                    // Auto-trigger print dialog after a short delay
                    setTimeout(() => {
                        pdfWindow.print();
                    }, 500);

                    this.showToast('✓ PDF opened in new window');
                } else {
                    this.showError(response.data?.message || 'Failed to generate PDF.');
                }

            } catch (error) {
                console.error('PDF Export error:', error);
                this.showError('Failed to export PDF. Please try again.');
            } finally {
                $btn.removeClass('is-loading').prop('disabled', false).html(`
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px; margin-right: 6px;">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                    </svg>
                    Export PDF
                `);
            }
        }
    }

    // Initialize on page load
    $(document).ready(function() {
        $('[data-component="bulk-cv-analyzer"]').each(function() {
            new BulkCVAnalyzer(this);
        });
    });

})(jQuery);
