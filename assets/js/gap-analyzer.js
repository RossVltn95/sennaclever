/**
 * Gap Analyzer - Comprehensive CV vs JD Analysis
 *
 * Renders detailed AI-powered gap analysis in institutional layout.
 */

(function($) {
    'use strict';

    var CV_GAP_PREFILL_KEY = 'sffcGapAnalyzerPrefill';

    if (typeof sffc_gap_analyzer === 'undefined') {
        console.error('Gap Analyzer: sffc_gap_analyzer is not defined.');
        return;
    }

    class GapAnalyzer {
        constructor(container) {
            this.$container = $(container);
            this.jdText = '';
            this.cvText = '';
            this.analysisResult = null;
            this.hasPremiumAccess = !!sffc_gap_analyzer.can_analyze;
            this.canAnalyze = true;
            this.isLoggedIn = !!sffc_gap_analyzer.is_logged_in;
            this.membershipUrl = sffc_gap_analyzer.membership_url || 'https://joinsenna.com/memberships/';
            this.loginUrl = sffc_gap_analyzer.login_url || '/wp-login.php';
            this.$loader = $();
            this.$loaderPortalTarget = $();
            this.$loaderOriginalParent = $();
            this.$loaderOriginalNextSibling = $();
            this.mobileStep = 'cv';

            this.init();
        }

        init() {
            this.$container.data('gapAnalyzerInstance', this);
            this.$loader = this.$container.find('[data-loader="analysis"]').first();
            this.setStage('scan');
            this.unlockPreviewEntryPoints();
            this.bindEvents();
            this.applyPrefill();
            this.updateMobileStepMeta();
        }

        getCandidateFirstName() {
            const localized = sffc_gap_analyzer || {};
            const currentUser = localized.current_user || localized.currentUser || {};
            const candidates = [
                currentUser.first_name,
                currentUser.firstName,
                localized.first_name,
                localized.firstName,
                this.$container.find('input[name="first_name"], input[name="user_first_name"], #first_name, #user_first_name').first().val(),
                currentUser.display_name,
                currentUser.displayName,
                localized.display_name,
                localized.displayName
            ];

            for (const candidate of candidates) {
                const firstName = String(candidate || '').trim().split(/\s+/).filter(Boolean)[0] || '';
                if (firstName) {
                    return firstName;
                }
            }

            return 'Candidate';
        }


        applyPrefill() {
            const localizedPrefill = sffc_gap_analyzer && sffc_gap_analyzer.prefill ? sffc_gap_analyzer.prefill : {};
            let sessionPrefill = {};
            const attributePrefillJd = this.$container.attr('data-prefill-jd') || '';
            const attributePrefillTitle = this.$container.attr('data-prefill-job-title') || '';
            const attributePrefillCv = this.$container.attr('data-prefill-cv') || '';
            let storedValue = '';

            try {
                storedValue = window.sessionStorage.getItem(CV_GAP_PREFILL_KEY) || '';
                sessionPrefill = storedValue ? JSON.parse(storedValue) || {} : {};
            } catch (error) {
                sessionPrefill = {};
            }

            if (storedValue) {
                try {
                    window.sessionStorage.removeItem(CV_GAP_PREFILL_KEY);
                } catch (error) {
                    // ignore storage cleanup issues
                }
            }

            const jdText = (sessionPrefill.jd_text || localizedPrefill.jd_text || attributePrefillJd || '').trim();
            const cvText = (sessionPrefill.cv_text || localizedPrefill.cv_text || attributePrefillCv || '').trim();
            const jobTitle = (sessionPrefill.job_title || localizedPrefill.job_title || attributePrefillTitle || '').trim();

            if (!jdText && !cvText) {
                return;
            }

            this.loadDocuments(jdText, cvText, {
                autoAnalyze: !!(jdText && cvText && this.canAnalyze),
                resetView: true,
                statusLabel: cvText ? 'Role and CV loaded' : 'Role loaded',
                statusDetails: cvText
                    ? 'Your role brief and CV are ready for analysis.'
                    : 'Now paste your CV to compare it against this role.',
                hintText: cvText ? 'Review and analyze when ready' : 'Paste your CV to continue',
                jobTitleText: jobTitle,
            });

            const $focusTarget = cvText
                ? this.$container.find('[data-action="analyze"]').first()
                : this.$container.find('[data-input="cv"]').first();

            if ($focusTarget.length) {
                window.setTimeout(() => {
                    $focusTarget.trigger('focus');
                }, 120);
            }
        }

        unlockPreviewEntryPoints() {
            if (this.hasPremiumAccess) {
                return;
            }

            this.$container.find('[data-action="analyze"]').prop('disabled', false).removeAttr('disabled data-locked');
            this.$container.find('.inst-analyze-btn span').text('Start Review');
            this.$container.find('.inst-gap-inline-cta .inst-gap-cta-note').html(
                `Preview mode is available. <a href="${this.membershipUrl}">Join MENA Careers</a> to unlock full rewrites, downloads, and full interview prep.`
            );
            this.$container.find('.inst-executive-summary').removeClass('is-locked');
            this.$container.find('.inst-exec-methodology').removeClass('inst-exec-methodology--locked').text('Preview Available');
        }

        loadDocuments(jdText = '', cvText = '', options = {}) {
            const settings = {
                autoAnalyze: false,
                resetView: true,
                statusLabel: 'Detected',
                statusDetails: 'Review the detected JD and CV, then analyze.',
                hintText: 'Review the detected JD and CV, then analyze',
                jobTitleText: '',
                ...options,
            };

            const jdValue = typeof jdText === 'string' ? jdText : '';
            const cvValue = typeof cvText === 'string' ? cvText : '';

            if (settings.resetView) {
                this.clearAnalysisState();
            }

            this.$container.find('[data-input="jd"]').val(jdValue);
            this.$container.find('[data-input="cv"]').val(cvValue);
            this.jdText = jdValue.trim();
            this.cvText = cvValue.trim();
            if (this.cvText) {
                this.setUploadStatus('CV loaded', false);
            }

            this.$container.find('[data-input="jd"]').trigger('input');
            this.$container.find('[data-input="cv"]').trigger('input');
            this.updateDisplayedJobTitle(settings.jobTitleText || '');

            this.updateStatus('waiting', settings.statusLabel, settings.statusDetails);
            this.$container.find('.inst-chatbox-hint').text(settings.hintText);

            if (settings.autoAnalyze && this.canAnalyze && this.jdText.length >= 100 && this.cvText.length >= 100) {
                this.runAnalysis();
            }
        }

        bindEvents() {
            this.$container.on('input', '[data-input="jd"]', (e) => {
                this.jdText = $(e.target).val().trim();
                this.updateMobileStepMeta();
            });

            this.$container.on('input', '[data-input="cv"]', (e) => {
                this.cvText = $(e.target).val().trim();
                if (this.cvText.length > 0) {
                    this.clearCVRequiredState();
                }
                this.updateMobileStepMeta();
            });

            // PDF paste support - clean up text when pasting
            this.$container.on('paste', '[data-input="jd"], [data-input="cv"]', (e) => {
                const $textarea = $(e.target);
                const inputType = $textarea.data('input');

                // Get pasted text
                const pastedText = (e.originalEvent.clipboardData || window.clipboardData).getData('text');

                // Check if it looks like PDF text (has common PDF artifacts)
                if (this.looksLikePDFText(pastedText)) {
                    e.preventDefault();

                    // Clean the text
                    const cleanedText = this.cleanPDFText(pastedText);

                    // Insert at cursor position
                    const cursorPos = e.target.selectionStart;
                    const textBefore = $textarea.val().substring(0, cursorPos);
                    const textAfter = $textarea.val().substring(e.target.selectionEnd);

                    $textarea.val(textBefore + cleanedText + textAfter);

                    // Update stored text
                    if (inputType === 'jd') {
                        this.jdText = $textarea.val().trim();
                    } else {
                        this.cvText = $textarea.val().trim();
                    }
                    this.updateMobileStepMeta();

                    // Show notification
                    this.showToast('PDF text cleaned and pasted');
                }
            });

            this.$container.on('click', '[data-action="analyze"]', () => {
                this.runAnalysis();
            });

            this.$container.on('click', '[data-gap-mobile-next]', (e) => {
                const targetStep = String($(e.currentTarget).attr('data-gap-mobile-next') || '').trim();
                this.goToMobileStep(targetStep);
            });

            this.$container.on('click', '[data-gap-mobile-back]', (e) => {
                const targetStep = String($(e.currentTarget).attr('data-gap-mobile-back') || '').trim();
                this.setMobileStep(targetStep || 'cv');
            });

            this.$container.on('click', '[data-action="reset"]', () => {
                this.reset();
            });

            this.$container.on('click', '[data-action="export"]', () => {
                this.exportReport();
            });

            this.$container.on('click', '[data-action="optimize"]', () => {
                this.setStage('results');
                this.scrollToResultsSection('cv');
            });

            this.$container.on('click', '[data-gap-scroll-section]', (e) => {
                e.preventDefault();
                const section = String($(e.currentTarget).attr('data-gap-scroll-section') || '').trim();
                if (section) {
                    this.scrollToResultsSection(section);
                }
            });

            this.$container.on('click', '[data-gap-back]', (e) => {
                e.preventDefault();

                if (this.isEmbeddedInCvMatchStudio()) {
                    this.$container.get(0).dispatchEvent(
                        new window.CustomEvent('sffc:gap-analyzer-exit', {
                            bubbles: true,
                            detail: {
                                targetState: 'jobs-mailbox',
                            },
                        })
                    );
                    return;
                }

                this.reset();
            });

            this.$container.on('click', '.inst-view-toggle-btn', (e) => {
                const view = $(e.currentTarget).data('view');
                this.switchReportView(view);
            });

            // Toolkit tab switching
            this.$container.on('click', '[data-toolkit-tab]', (e) => {
                this.switchToolkitTab($(e.currentTarget).data('toolkit-tab'));
            });

            // Interview question toggle
            this.$container.on('click', '.inst-interview-question', (e) => {
                $(e.currentTarget).closest('.inst-interview-item').toggleClass('is-open');
            });

            // Copy cover letter
            this.$container.on('click', '[data-action="copy-cover"]', () => {
                this.copyCoverLetter();
            });

            // Copy keyword
            this.$container.on('click', '.inst-keyword-tag', (e) => {
                const keyword = $(e.currentTarget).data('keyword');
                if (keyword) {
                    this.copyToClipboard(keyword);
                    this.showToast('Keyword copied!');
                }
            });

            // Copy improvement suggestion
            this.$container.on('click', '[data-action="copy-improvement"]', (e) => {
                const text = $(e.currentTarget).data('text');
                if (text) {
                    this.copyToClipboard(text);
                    this.showToast('Suggestion copied!');
                }
            });

            this.$container.on('click', '[data-gap-cv-upload-trigger]', () => {
                this.$container.find('[data-gap-cv-file]').first().trigger('click');
            });

            this.$container.on('change', '[data-gap-cv-file]', async (e) => {
                const file = e.target.files && e.target.files[0] ? e.target.files[0] : null;
                if (!file) {
                    return;
                }

                try {
                    this.setUploadStatus(`Reading ${file.name}...`, false);
                    const extractedText = await this.parseCvFile(file);

                    if (!extractedText || extractedText.length < 40) {
                        throw new Error('We could not extract enough text from that CV.');
                    }

                    this.$container.find('[data-input="cv"]').val(extractedText).trigger('input');
                    this.cvText = extractedText.trim();
                    this.clearCVRequiredState();
                    this.setUploadStatus(`${file.name} loaded`, false);
                    this.setMobileStep('jd');
                    this.showToast('CV extracted successfully');

                    if (this.jdText && this.jdText.length >= 100) {
                        this.runAnalysis();
                    }
                } catch (error) {
                    this.setUploadStatus(error && error.message ? error.message : 'Upload failed', true);
                    this.showError(error && error.message ? error.message : 'We could not read that CV file.');
                } finally {
                    e.target.value = '';
                }
            });

            // Download CV as Word document
            this.$container.on('click', '[data-action="download-cv-word"]', () => {
                this.downloadCVAsWord();
            });

            // Download Cover Letter as Word document
            this.$container.on('click', '[data-action="download-cover-word"]', () => {
                this.downloadCoverLetterAsWord();
            });
        }

        switchToolkitTab(tab) {
            // Update tab buttons
            this.$container.find('[data-toolkit-tab]').removeClass('is-active');
            this.$container.find(`[data-toolkit-tab="${tab}"]`).addClass('is-active');

            // Update panels
            this.$container.find('[data-toolkit-panel]').removeClass('is-active');
            this.$container.find(`[data-toolkit-panel="${tab}"]`).addClass('is-active');
        }

        setUploadStatus(message, isError = false) {
            const $status = this.$container.find('[data-gap-cv-upload-status]').first();
            if (!$status.length) {
                return;
            }

            $status.text(message || 'PDF, DOCX, DOC, or TXT');
            $status.toggleClass('is-error', !!isError);
            $status.toggleClass('is-success', !isError && !!message && message !== 'PDF, DOCX, DOC, or TXT');
        }

        async parseCvFile(file) {
            const name = String(file && file.name ? file.name : '').toLowerCase();

            if (!file) {
                throw new Error('No CV file selected.');
            }

            if (name.endsWith('.pdf')) {
                return this.parsePdfFile(file);
            }

            if (name.endsWith('.docx') || name.endsWith('.doc')) {
                return this.parseDocxFile(file);
            }

            if (name.endsWith('.txt')) {
                return this.parseTxtFile(file);
            }

            throw new Error('Please upload a PDF, DOCX, DOC, or TXT file.');
        }

        async parsePdfFile(file) {
            if (typeof pdfjsLib === 'undefined') {
                throw new Error('PDF parsing is unavailable right now.');
            }

            if (sffc_gap_analyzer.pdf_worker) {
                pdfjsLib.GlobalWorkerOptions.workerSrc = sffc_gap_analyzer.pdf_worker;
            }

            const buffer = await file.arrayBuffer();
            const pdf = await pdfjsLib.getDocument({ data: buffer }).promise;
            const pages = [];

            for (let i = 1; i <= pdf.numPages; i += 1) {
                pages.push(
                    pdf.getPage(i).then((page) => page.getTextContent().then((content) => (
                        content.items.map((item) => item.str || '').join(' ')
                    )))
                );
            }

            return (await Promise.all(pages)).join('\n').trim();
        }

        async parseDocxFile(file) {
            if (typeof mammoth === 'undefined') {
                throw new Error('DOCX parsing is unavailable right now.');
            }

            const buffer = await file.arrayBuffer();
            const result = await mammoth.extractRawText({ arrayBuffer: buffer });
            return String(result && result.value ? result.value : '').trim();
        }

        async parseTxtFile(file) {
            const content = await file.text();
            return String(content || '').trim();
        }

        async runAnalysis() {
            if (!this.canAnalyze) {
                this.promptUpgrade();
                return;
            }

            if (!this.cvText) {
                this.showCVRequiredState('Please paste your CV');
                this.updateStatus('error', 'Missing CV', 'Please paste your CV to continue.');
                this.$container.find('.inst-chatbox-hint').text('Please paste your CV');
                return;
            }

            if (!this.jdText) {
                this.showError('Please enter the full job description.');
                return;
            }

            if (this.jdText.length < 100) {
                this.showError('Job description is too short. Please paste the full job description.');
                return;
            }

            if (this.cvText.length < 100) {
                this.showCVRequiredState('Please paste your CV');
                this.updateStatus('error', 'CV too short', 'Please paste more of your CV to continue.');
                this.$container.find('.inst-chatbox-hint').text('Please paste your CV');
                return;
            }

            const $btns = this.$container.find('[data-action="analyze"]');
            const $chatboxHint = this.$container.find('.inst-chatbox-hint');

            $btns.addClass('is-loading').prop('disabled', true);
            if (!this.isLoggedIn) {
                $chatboxHint.text('Preparing preview...');
                this.updateStatus('analyzing', 'Preparing preview', 'Building a realistic preview of your Career Assessment...');
                try {
                    await this.runGuestPreviewAnalysis();
                } catch (error) {
                    console.error('Guest preview analysis error:', error);
                    this.hideLoader();
                    this.showError('Unable to prepare your preview right now. Please try again.');
                } finally {
                    $btns.removeClass('is-loading').prop('disabled', false);
                }
                return;
            }

            $chatboxHint.text('Analyzing...');
            this.updateStatus('analyzing', 'Analyzing', 'Running AI analysis...');

            // Start the loading animation
            this.showLoader();
            this.startProgressSimulation();

            try {
                const rawResponse = await $.ajax({
                    url: sffc_gap_analyzer.ajax_url,
                    type: 'POST',
                    timeout: 180000,
                    dataType: 'text', // Get raw text to handle any HTML prefix
                    data: {
                        action: 'sffc_analyze_gap',
                        nonce: sffc_gap_analyzer.nonce,
                        jd_text: this.jdText,
                        cv_text: this.cvText,
                    },
                });

                // Extract JSON from response (may have HTML errors prepended)
                let response;
                try {
                    const jsonMatch = rawResponse.match(/\{[\s\S]*\}$/);
                    if (jsonMatch) {
                        response = JSON.parse(jsonMatch[0]);
                    } else {
                        throw new Error('No JSON found in response');
                    }
                } catch (parseError) {
                    console.error('Failed to parse response:', parseError);
                    console.error('Raw response:', rawResponse.substring(0, 500));
                    throw new Error('Invalid response format');
                }

                // Complete the progress to 100%
                this.completeProgress();

                if (response.success) {
                    this.analysisResult = response.data;
                    // Small delay to show 100% before hiding
                    await this.delay(500);
                    this.hideLoader();
                    this.renderResults(response.data);
                } else {
                    this.hideLoader();
                    this.showError(response.data?.message || 'Analysis failed. Please try again.');
                }
            } catch (error) {
                console.error('Analysis error:', error);
                this.hideLoader();
                if (error.statusText === 'timeout') {
                    this.showError('Analysis timed out. Please try again with shorter text.');
                } else {
                    this.showError('Failed to analyze application. Please try again.');
                }
            } finally {
                $btns.removeClass('is-loading').prop('disabled', false);
            }
        }

        // Loading Progress Methods
        showLoader(config = {}) {
            this.mountLoaderToPortal();
            this.$loader.stop(true, true).css('display', 'flex').hide().fadeIn(200);
            this.resetLoaderState(config);
        }

        hideLoader() {
            this.stopProgressSimulation();
            this.$loader.fadeOut(300, () => {
                this.restoreLoaderFromPortal();
            });
        }

        resetLoaderState(config = {}) {
            const settings = {
                kicker: 'Tailored review in progress',
                title: 'We’re improving your application.',
                intro: 'MENA Careers is structuring this job description, mapping the hiring signals, and preparing a sharper review workspace so the result is worth the wait.',
                eta: '~90 seconds',
                status: 'Parsing job description...',
                ...config,
            };

            this.$loader.find('[data-loader-percent]').text('0%');
            this.$loader.find('[data-loader-bar]').css('width', '0%');
            this.$loader.find('.inst-loader-kicker').text(settings.kicker);
            this.$loader.find('.inst-loader-title').text(settings.title);
            this.$loader.find('.inst-loader-intro').text(settings.intro);
            this.$loader.find('[data-loader-status]').text(settings.status);
            this.$loader.find('[data-loader-eta]').text(settings.eta);
            this.$loader.find('.inst-loader-step').removeClass('is-active is-complete');
        }

        startProgressSimulation(config = {}) {
            const steps = Array.isArray(config.steps) && config.steps.length
                ? config.steps
                : [
                    {
                        step: 'parse',
                        time: 0,
                        percent: 12,
                        title: 'We’re parsing the job description.',
                        status: 'Parsing job description...'
                    },
                    {
                        step: 'extract',
                        time: 14000,
                        percent: 28,
                        title: 'We’re extracting candidate signals.',
                        status: 'Extracting candidate signals...'
                    },
                    {
                        step: 'skills',
                        time: 32000,
                        percent: 48,
                        title: 'We’re mapping skills and experience.',
                        status: 'Mapping skills and experience...'
                    },
                    {
                        step: 'match',
                        time: 54000,
                        percent: 72,
                        title: 'We’re building tailored recommendations.',
                        status: 'Building tailored recommendations...'
                    },
                    {
                        step: 'report',
                        time: 76000,
                        percent: 94,
                        title: 'We’re preparing downloads and outreach tools.',
                        status: 'Preparing downloads and outreach tools...'
                    },
                ];
            const totalDuration = Math.max(
                1000,
                parseInt(config.totalDuration || 90000, 10) || 90000
            );

            this.progressPercent = 0;
            this.progressRunning = true;
            this.progressStartTime = performance.now();
            this.loaderEtaSeconds = Math.max(1, Math.ceil(totalDuration / 1000));

            let currentStepIndex = 0;
            const minEtaSeconds = totalDuration <= 12000 ? 1 : 8;
            if (this.loaderEtaInterval) {
                window.clearInterval(this.loaderEtaInterval);
            }
            this.loaderEtaInterval = window.setInterval(() => {
                const elapsed = performance.now() - this.progressStartTime;
                const remaining = Math.max(minEtaSeconds, Math.ceil((totalDuration - elapsed) / 1000));
                this.loaderEtaSeconds = remaining;
                this.$loader.find('[data-loader-eta]').text(`~${remaining} seconds`);
            }, 1000);

            const animate = (currentTime) => {
                if (!this.progressRunning) return;

                const elapsed = currentTime - this.progressStartTime;
                const boundedElapsed = Math.min(elapsed, totalDuration);

                while (
                    currentStepIndex < steps.length - 1 &&
                    boundedElapsed >= steps[currentStepIndex + 1].time
                ) {
                    this.$loader.find(`[data-step="${steps[currentStepIndex].step}"]`)
                        .removeClass('is-active')
                        .addClass('is-complete');
                    currentStepIndex++;
                }

                const currentStep = steps[currentStepIndex];
                const nextStep = steps[currentStepIndex + 1] || null;

                this.$loader.find('.inst-loader-step').removeClass('is-active');
                this.$loader.find(`[data-step="${currentStep.step}"]`).addClass('is-active');
                this.$loader.find('.inst-loader-title').text(currentStep.title);
                this.$loader.find('[data-loader-status]').text(currentStep.status);

                if (nextStep) {
                    const segmentDuration = Math.max(1, nextStep.time - currentStep.time);
                    const segmentProgress = Math.min(1, Math.max(0, (boundedElapsed - currentStep.time) / segmentDuration));
                    this.progressPercent = currentStep.percent + ((nextStep.percent - currentStep.percent) * segmentProgress);
                } else {
                    this.progressPercent = currentStep.percent;
                }

                this.updateLoaderProgress(this.progressPercent);

                if (this.progressRunning) {
                    this.progressAnimationId = requestAnimationFrame(animate);
                }
            };

            this.$loader.find(`[data-step="${steps[0].step}"]`).addClass('is-active');
            this.$loader.find('.inst-loader-title').text(steps[0].title);
            this.$loader.find('[data-loader-status]').text(steps[0].status);

            this.progressAnimationId = requestAnimationFrame(animate);
        }

        stopProgressSimulation() {
            this.progressRunning = false;
            if (this.progressAnimationId) {
                cancelAnimationFrame(this.progressAnimationId);
                this.progressAnimationId = null;
            }
            if (this.loaderEtaInterval) {
                window.clearInterval(this.loaderEtaInterval);
                this.loaderEtaInterval = null;
            }
        }

        completeProgress(config = {}) {
            const settings = {
                title: 'We’re finalising your application report.',
                eta: 'Finalising',
                status: 'Analysis complete!',
                ...config,
            };
            this.stopProgressSimulation();

            // Mark all steps as complete
            this.$loader.find('.inst-loader-step').removeClass('is-active').addClass('is-complete');

            // Animate to 100%
            this.updateLoaderProgress(100);
            this.$loader.find('.inst-loader-title').text(settings.title);
            this.$loader.find('[data-loader-eta]').text(settings.eta);
            this.$loader.find('[data-loader-status]').text(settings.status);
        }

        async runGuestPreviewAnalysis() {
            const previewSeed = this.buildGuestPreviewAnalysis();
            const previewSignals = (previewSeed.__previewSignals || []).slice(0, 3);
            const signalPhrase = previewSignals.join(', ');
            const loaderConfig = {
                kicker: 'Preview analysis in progress',
                title: 'We’re building your CV preview.',
                intro: signalPhrase
                    ? `MENA Careers is reading your CV, picking up signals like ${signalPhrase}, and mapping them against the live role before revealing a preview.`
                    : 'MENA Careers is reading your CV, mapping the hiring signals, and preparing a realistic preview before revealing the locked review.',
                eta: '~8 seconds',
                status: 'Reading your CV sections...',
                totalDuration: 7800,
                steps: [
                    {
                        step: 'parse',
                        time: 0,
                        percent: 14,
                        title: 'We’re reading your CV structure.',
                        status: 'Parsing your CV sections...'
                    },
                    {
                        step: 'extract',
                        time: 1600,
                        percent: 34,
                        title: 'We’re extracting your strongest signals.',
                        status: signalPhrase
                            ? `Pulling signals like ${signalPhrase}...`
                            : 'Extracting the strongest CV signals...'
                    },
                    {
                        step: 'skills',
                        time: 3300,
                        percent: 56,
                        title: 'We’re checking ATS and role fit.',
                        status: 'Comparing your CV against the live job brief...'
                    },
                    {
                        step: 'match',
                        time: 5400,
                        percent: 78,
                        title: 'We’re drafting your preview analysis.',
                        status: 'Preparing strengths, gaps, and recruiter-facing notes...'
                    },
                    {
                        step: 'report',
                        time: 6900,
                        percent: 95,
                        title: 'We’re preparing your locked results.',
                        status: 'Packaging your preview report and next steps...'
                    }
                ]
            };

            this.showLoader(loaderConfig);
            this.startProgressSimulation(loaderConfig);

            try {
                const rawResponse = await $.ajax({
                    url: sffc_gap_analyzer.ajax_url,
                    type: 'POST',
                    timeout: 180000,
                    dataType: 'text',
                    data: {
                        action: 'sffc_analyze_gap',
                        nonce: sffc_gap_analyzer.nonce,
                        jd_text: this.jdText,
                        cv_text: this.cvText,
                        preview_mode: 'guest'
                    }
                });

                let response;
                try {
                    const jsonMatch = rawResponse.match(/\{[\s\S]*\}$/);
                    if (jsonMatch) {
                        response = JSON.parse(jsonMatch[0]);
                    } else {
                        throw new Error('No JSON found in response');
                    }
                } catch (parseError) {
                    console.error('Failed to parse guest preview response:', parseError);
                    console.error('Raw response:', rawResponse.substring(0, 500));
                    throw new Error('Invalid preview response format');
                }

                if (!response.success || !response.data) {
                    throw new Error((response.data && response.data.message) || 'Preview analysis failed');
                }

                const previewData = {
                    ...previewSeed,
                    ...response.data,
                    __detectedCv: previewSeed.__detectedCv,
                    __profileSignals: previewSeed.__profileSignals,
                    __careerSignals: previewSeed.__careerSignals,
                    __previewSignals: previewSeed.__previewSignals
                };

                this.completeProgress({
                    title: 'Your preview analysis is ready.',
                    eta: 'Preview ready',
                    status: 'Upgrade to unlock the full report.'
                });

                await this.delay(450);
                this.hideLoader();

                this.analysisResult = previewData;
                this.renderResults(previewData);
                this.renderGuestPreviewAccessCard(previewData);
                this.updateStatus('success', 'Preview ready', 'Upgrade to view your results and analysis.');
                this.$container.find('.inst-chatbox-hint').text('Upgrade to unlock the full analysis');
            } catch (error) {
                console.error('Guest preview server analysis error:', error);

                this.completeProgress({
                    title: 'Preview ready with local fallback.',
                    eta: 'Preview ready',
                    status: 'Showing a simplified preview.'
                });

                await this.delay(450);
                this.hideLoader();

                this.analysisResult = previewSeed;
                this.renderResults(previewSeed);
                this.renderGuestPreviewAccessCard(previewSeed);
                this.updateStatus('success', 'Preview ready', 'Upgrade to view your results and analysis.');
                this.$container.find('.inst-chatbox-hint').text('Upgrade to unlock the full analysis');
            }
        }

        buildGuestPreviewAnalysis() {
            const cvInsights = this.extractPreviewCvInsights(this.cvText);
            const jdInsights = this.extractPreviewJdInsights(this.jdText);
            const requiredSignals = this.buildPreviewRequiredSignals(jdInsights);
            const overlapSignals = requiredSignals.filter((term) => cvInsights.lookup.has(this.normalizePreviewSignal(term)));
            const matchedSignals = overlapSignals.slice(0, 6).length
                ? overlapSignals.slice(0, 6)
                : cvInsights.topSignals.slice(0, 4);
            const missingSignals = requiredSignals
                .filter((term) => !cvInsights.lookup.has(this.normalizePreviewSignal(term)))
                .slice(0, 6);
            const strengthSnippets = cvInsights.summarySnippets.slice(0, 3);
            const scoreBase = 58 + (matchedSignals.length * 7) + Math.min(10, cvInsights.topSignals.length * 2);
            const missingPenalty = missingSignals.length * 3;
            const overallScore = Math.max(61, Math.min(89, scoreBase - missingPenalty));
            const skillsScore = Math.max(56, Math.min(92, overallScore + (matchedSignals.length * 2) - 4));
            const experienceScore = Math.max(52, Math.min(90, overallScore - 2 + strengthSnippets.length * 3));
            const keywordsScore = Math.max(48, Math.min(88, overallScore - 5 + matchedSignals.length * 4));
            const roleTitle = jdInsights.roleTitle || 'this role';
            const strengths = strengthSnippets.length ? strengthSnippets : matchedSignals;
            const seniorityComparison = this.comparePreviewSeniority(cvInsights.seniorityProfile, jdInsights.seniorityProfile);
            const certificationComparison = this.comparePreviewCertifications(cvInsights.certifications, jdInsights.certifications);

            return {
                __detectedCv: {
                    languages: cvInsights.languages.slice(0, 4),
                    certifications: cvInsights.certifications.slice(0, 3),
                    tools: cvInsights.tools.slice(0, 4),
                    roleSignals: cvInsights.roleSignals.slice(0, 4)
                },
                __profileSignals: cvInsights.profileSignals,
                __careerSignals: {
                    seniority: seniorityComparison,
                    certifications: certificationComparison
                },
                __previewSignals: strengths.slice(0, 3),
                scores: {
                    overall: overallScore,
                    skills_match: skillsScore,
                    experience_match: experienceScore,
                    keywords_match: keywordsScore
                },
                executive_summary: {
                    match_score: overallScore,
                    recommendation: strengths.length
                        ? `We picked up ${strengths.slice(0, 2).join(' and ')} in your CV, but the role still needs clearer evidence of ${missingSignals[0] || 'role-specific proof'}.`
                        : `Your CV has useful building blocks, but it still needs clearer evidence for ${roleTitle}.`,
                    key_insight: strengths.length
                        ? `Strongest detected signals: ${strengths.slice(0, 3).join(' · ')}.`
                        : 'We identified a useful base profile, but more role-specific proof needs surfacing.',
                    risk_level: overallScore >= 75 ? 'Strong preview' : 'Needs tightening'
                },
                overall_assessment: {
                    verdict: overallScore >= 75 ? 'Promising fit' : 'Worth tightening',
                    final_recommendation: matchedSignals.length
                        ? `Use your ${matchedSignals.slice(0, 2).join(' and ')} experience more explicitly before applying.`
                        : `Sharpen your positioning for ${roleTitle} before applying.`
                },
                red_flags: missingSignals.slice(0, 2).map((signal, index) => ({
                    issue: index === 0 ? `The CV does not clearly surface ${signal}` : `The role still expects stronger proof of ${signal}`,
                    severity: index === 0 ? 'serious' : 'significant',
                    mitigation: `Add a concrete bullet showing ${signal} in action.`,
                    evidence: signal
                })),
                skills_breakdown: {
                    matched_skills: matchedSignals.map((skill) => ({
                        skill,
                        cv_evidence: `Detected in your CV and aligned to the brief for ${roleTitle}.`,
                        strength_level: 'strong'
                    })),
                    missing_skills: missingSignals.map((skill) => ({
                        skill,
                        suggestion: `Work ${skill} naturally into your recent experience bullets.`,
                        importance: 'important'
                    }))
                },
                requirements_analysis: [
                    ...matchedSignals.slice(0, 4).map((signal) => ({
                        requirement: signal,
                        match_status: 'STRONG_MATCH',
                        gap_severity: 'low',
                        cv_evidence: `${signal} is already visible in the pasted CV.`,
                        action_needed: `Keep ${signal} early and easy to scan.`
                    })),
                    ...missingSignals.map((signal) => ({
                        requirement: signal,
                        match_status: 'NOT_FOUND',
                        gap_severity: 'significant',
                        cv_evidence: 'Not clearly surfaced in the pasted CV.',
                        action_needed: `Show how you handled ${signal} with one quantified example.`
                    }))
                ],
                keyword_analysis: {
                    critical_missing: missingSignals,
                    well_represented: matchedSignals
                },
                strengths_to_highlight: strengths.map((signal) => ({
                    strength: signal,
                    relevance: `Relevant for ${roleTitle}`,
                    how_to_leverage: `Use ${signal} in your headline, summary, and first recruiter-facing bullet.`
                })),
                cv_improvements: missingSignals.slice(0, 4).map((signal, index) => ({
                    section: index === 0 ? 'Headline & summary' : `Experience proof ${index}`,
                    suggested: `Add a more explicit line around ${signal} so it is visible within six seconds.`,
                    impact: `Improves ATS and recruiter recognition for ${signal}.`
                })),
                cover_letter_points: [
                    strengths[0]
                        ? `Lead with ${strengths[0]} and connect it directly to ${roleTitle}.`
                        : `Open by connecting your background directly to ${roleTitle}.`,
                    missingSignals[0]
                        ? `Pre-empt the gap around ${missingSignals[0]} by framing adjacent evidence and a fast ramp-up.`
                        : 'Use the cover letter to make the recruiter’s strongest reason to interview you obvious.'
                ],
                interview_prep: [
                    {
                        likely_question: `What makes you a strong fit for ${roleTitle}?`,
                        suggested_response_angle: strengths.length
                            ? `Anchor the answer around ${strengths.slice(0, 2).join(' and ')} with one proof point each.`
                            : 'Anchor the answer around your strongest relevant achievements and transferable evidence.'
                    },
                    {
                        likely_question: 'Where would you need to ramp up most quickly?',
                        suggested_response_angle: missingSignals[0]
                            ? `Be honest about ${missingSignals[0]}, then explain how you would close that gap quickly.`
                            : 'Acknowledge one development area and explain your learning plan.'
                    }
                ],
                experience_analysis: {
                    relevant_roles: []
                },
                experience_improvements: {
                    summary: strengths.length
                        ? `Your CV already signals ${strengths.slice(0, 2).join(' and ')}, but the role needs those signals stated more explicitly.`
                        : `Your CV needs clearer role-fit evidence for ${roleTitle}.`,
                    priority_fixes: missingSignals.slice(0, 3).map((signal) => ({
                        issue: `Surface ${signal} more clearly`,
                        improved_text: `Add a concise bullet proving ${signal} with a measurable outcome.`,
                        impact: 'high'
                    }))
                }
            };
        }

        extractPreviewCvInsights(text) {
            const sourceText = String(text || '');
            const lines = String(text || '')
                .split(/\n+/)
                .map(line => line.trim())
                .filter(Boolean);
            const summarySnippets = [];
            const signalSet = new Set();
            const languages = this.extractPreviewLanguages(sourceText);
            const certifications = [];

            lines.forEach((line) => {
                const colonIndex = line.indexOf(':');
                if (colonIndex > 0 && colonIndex < 28) {
                    const label = line.slice(0, colonIndex).trim().toLowerCase();
                    const value = line.slice(colonIndex + 1).trim();
                    if (label.includes('certification') || label.includes('qualification') || label.includes('license')) {
                        value.split(/,|&|\/|\||;/).forEach((part) => {
                            const snippet = part.trim().replace(/\s+/g, ' ');
                            if (snippet.length >= 3 && certifications.length < 6) {
                                certifications.push(snippet);
                            }
                        });
                    }
                    value.split(/,|&|\/|\||;/).forEach((part) => {
                        const snippet = part.trim().replace(/\s+/g, ' ');
                        if (snippet.length >= 3 && summarySnippets.length < 8) {
                            summarySnippets.push(snippet);
                        }
                    });
                }
            });

            this.extractMeaningfulTerms(text, 14).forEach((term) => {
                signalSet.add(term);
            });
            summarySnippets.forEach((snippet) => {
                signalSet.add(snippet);
            });

            const normalizedText = sourceText.toLowerCase();
            const tools = this.extractPreviewTools(lines, sourceText);
            const roleSignals = this.extractPreviewRoleSignals(sourceText, 8);

            const emailMatch = sourceText.match(/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i);
            const linkedinMatch = sourceText.match(/(?:https?:\/\/)?(?:[\w]+\.)?linkedin\.com\/in\/[A-Za-z0-9\-_%]+/i);
            const phoneCandidates = sourceText.match(/(?:\+?\d[\d\s().-]{7,}\d)/g) || [];
            const phoneMatch = phoneCandidates.find((candidate) => {
                const digits = (candidate.match(/\d/g) || []).length;
                return digits >= 8;
            }) || '';
            const datePatterns = [
                /\b(?:jan|january|feb|february|mar|march|apr|april|may|jun|june|jul|july|aug|august|sep|sept|september|oct|october|nov|november|dec|december)\s+\d{4}\b/ig,
                /\b\d{1,2}[\/.-]\d{2,4}\b/g,
                /\b(?:19|20)\d{2}\s*[-–—]\s*(?:present|current|now|(?:19|20)\d{2})\b/ig,
                /\b(?:present|current)\b/ig
            ];
            const dateMatches = [];
            datePatterns.forEach((pattern) => {
                const matches = sourceText.match(pattern) || [];
                matches.forEach((match) => {
                    const cleaned = String(match).trim();
                    if (cleaned && dateMatches.indexOf(cleaned) === -1) {
                        dateMatches.push(cleaned);
                    }
                });
            });

            const normalizedCertifications = this.extractKnownCertifications(sourceText, certifications);
            const titleEntries = this.extractPreviewCvTitleEntries(lines);
            const seniorityProfile = this.buildPreviewCvSeniorityProfile(titleEntries);
            const lookup = new Set();

            Array.from(signalSet).forEach((item) => lookup.add(this.normalizePreviewSignal(item)));
            roleSignals.forEach((item) => lookup.add(this.normalizePreviewSignal(item)));
            tools.forEach((item) => lookup.add(this.normalizePreviewSignal(item)));
            normalizedCertifications.forEach((item) => lookup.add(this.normalizePreviewSignal(item)));
            languages.forEach((item) => lookup.add(this.normalizePreviewSignal(item)));
            lines.forEach((line) => {
                const normalized = this.normalizePreviewSignal(line);
                if (normalized) {
                    lookup.add(normalized);
                }
            });

            return {
                topSignals: Array.from(signalSet).slice(0, 10),
                summarySnippets: summarySnippets.slice(0, 6),
                lookup,
                languages: Array.from(new Set(languages)).slice(0, 4),
                certifications: normalizedCertifications,
                tools: Array.from(new Set(tools)),
                roleSignals: Array.from(new Set(roleSignals)),
                titleEntries,
                seniorityProfile,
                profileSignals: {
                    hasLinkedIn: !!linkedinMatch,
                    linkedinValue: linkedinMatch ? linkedinMatch[0] : '',
                    hasEmail: !!emailMatch,
                    emailValue: emailMatch ? emailMatch[0] : '',
                    hasPhone: !!phoneMatch,
                    phoneValue: phoneMatch,
                    hasDates: dateMatches.length >= 2,
                    dateCount: dateMatches.length,
                    datePreview: dateMatches.slice(0, 3)
                }
            };
        }

        extractPreviewJdInsights(text) {
            const roleTitle = String(text || '')
                .split(/\n+/)
                .map(line => line.trim())
                .find(line => line && line.length < 90) || '';
            const sourceText = String(text || '');
            const seniorityProfile = this.detectPreviewRoleSeniority(roleTitle, sourceText);
            const certifications = this.extractKnownCertifications(sourceText, []);
            const tools = this.extractPreviewTools(
                sourceText.split(/\n+/).map((line) => line.trim()).filter(Boolean),
                sourceText
            );
            const roleSignals = this.extractPreviewRoleSignals(sourceText, 10);
            const languages = this.extractPreviewLanguages(sourceText, true);

            return {
                roleTitle: roleTitle || 'this role',
                keywords: this.extractMeaningfulTerms(text, 10),
                tools,
                roleSignals,
                languages,
                seniorityProfile,
                certifications
            };
        }

        buildPreviewRequiredSignals(jdInsights) {
            const rawSignals = [
                ...(Array.isArray(jdInsights.roleSignals) ? jdInsights.roleSignals : []),
                ...(Array.isArray(jdInsights.tools) ? jdInsights.tools : []),
                ...(Array.isArray(jdInsights.certifications) ? jdInsights.certifications : []),
                ...(Array.isArray(jdInsights.languages) ? jdInsights.languages : []),
                ...(Array.isArray(jdInsights.keywords) ? jdInsights.keywords : [])
            ];
            const curated = [];
            const seen = new Set();

            rawSignals.forEach((signal) => {
                const cleaned = this.cleanPreviewSignal(signal);
                const normalized = this.normalizePreviewSignal(cleaned);
                if (!cleaned || !normalized || seen.has(normalized) || this.isWeakPreviewSignal(cleaned)) {
                    return;
                }
                seen.add(normalized);
                curated.push(cleaned);
            });

            return curated.slice(0, 10);
        }

        getPreviewSeniorityDefinitions() {
            return [
                { key: 'intern', label: 'Intern', level: 1, patterns: [/\boff[- ]cycle\b/i, /\bintern(ship)?\b/i, /\bsummer analyst\b/i, /\bplacement year\b/i] },
                { key: 'analyst', label: 'Analyst', level: 2, patterns: [/\banalyst\b/i, /\bjunior\b/i, /\bentry[- ]level\b/i, /\bgraduate\b/i] },
                { key: 'senior_analyst', label: 'Senior Analyst', level: 3, patterns: [/\bsenior analyst\b/i, /\banalyst ii\b/i] },
                { key: 'associate', label: 'Associate', level: 4, patterns: [/\bassociate\b/i] },
                { key: 'manager', label: 'Manager', level: 5, patterns: [/\bmanager\b/i, /\bengagement manager\b/i, /\bproject manager\b/i] },
                { key: 'vice_president', label: 'Vice President', level: 6, patterns: [/\bvice president\b/i, /\bvp\b/i] },
                { key: 'director', label: 'Director', level: 7, patterns: [/\bdirector\b/i, /\bprincipal\b/i, /\bhead of\b/i] },
                { key: 'executive', label: 'Executive', level: 8, patterns: [/\bmanaging director\b/i, /\bexecutive director\b/i, /\bpartner\b/i, /\bchief\b/i, /\bc[- ]?suite\b/i] }
            ];
        }

        getPreviewTitleKeywordPattern() {
            return /\b(intern|analyst|associate|manager|vice president|vp|director|principal|partner|consultant|specialist|assistant|research|portfolio|investment|equity|credit|recruiter|treasury|valuation|m&a|private equity|asset management|investor relations|capital markets)\b/i;
        }

        getPreviewToolCatalog() {
            return [
                { label: 'Excel', patterns: [/\bms\.?\s*excel\b/i, /\bmicrosoft excel\b/i, /\bexcel\b/i] },
                { label: 'PowerPoint', patterns: [/\bmicrosoft power\s*point\b/i, /\bmicrosoft powerpoint\b/i, /\bpower\s*point\b/i, /\bpowerpoint\b/i] },
                { label: 'Word', patterns: [/\bmicrosoft word\b/i, /\bms\.?\s*word\b/i, /\bword\b/i] },
                { label: 'Microsoft Office', patterns: [/\bmicrosoft office suite\b/i, /\bmicrosoft office\b/i, /\bms office\b/i, /\boffice suite\b/i] },
                { label: 'Bloomberg', patterns: [/\bbloomberg\b/i] },
                { label: 'Eikon', patterns: [/\beikon\b/i] },
                { label: 'Refinitiv', patterns: [/\brefinitiv\b/i] },
                { label: 'FactSet', patterns: [/\bfactset\b/i] },
                { label: 'Capital IQ', patterns: [/\bcapital iq\b/i, /\bcap iq\b/i] },
                { label: 'PitchBook', patterns: [/\bpitchbook\b/i] },
                { label: 'BarraOne', patterns: [/\bbarraone\b/i] },
                { label: 'ThinkFolio', patterns: [/\bthinkfolio\b/i] },
                { label: 'OutSystems', patterns: [/\boutsystems\b/i] },
                { label: 'Power BI', patterns: [/\bpower\s*bi\b/i, /\bpowerbi\b/i] },
                { label: 'Tableau', patterns: [/\btableau\b/i] },
                { label: 'Qlik Sense', patterns: [/\bqlik sense\b/i] },
                { label: 'Python', patterns: [/\bpython\b/i] },
                { label: 'SQL', patterns: [/\bsql\b/i, /\bpostgresql\b/i] },
                { label: 'PostgreSQL', patterns: [/\bpostgresql\b/i] },
                { label: 'VBA', patterns: [/\bvba\b/i, /\bvisual basic for applications\b/i] },
                { label: 'JavaScript', patterns: [/\bjavascript\b/i] },
                { label: 'Java', patterns: [/\bjava\b/i] },
                { label: 'Scala', patterns: [/\bscala\b/i] },
                { label: 'R', patterns: [/(?:^|[^A-Z])\bR\b(?:[^A-Z]|$)/i] },
                { label: 'Matlab', patterns: [/\bmatlab\b/i] },
                { label: 'SAS', patterns: [/\bsas\b/i] },
                { label: 'Teradata', patterns: [/\bteradata\b/i] },
                { label: 'Azure', patterns: [/\bmicrosoft azure\b/i, /\bazure\b/i] },
                { label: 'Databricks', patterns: [/\bdatabricks\b/i] },
                { label: 'SAP', patterns: [/\bsap\b/i] },
                { label: 'Salesforce', patterns: [/\bsalesforce\b/i] },
                { label: 'CRM', patterns: [/\bcrm\b/i] },
                { label: 'QuickBooks', patterns: [/\bquick\s*books\b/i, /\bquickbooks\b/i] },
                { label: 'Tally ERP', patterns: [/\btally\s*[-–—]?\s*erp\b/i, /\btally\b/i] },
                { label: 'Sage ERP', patterns: [/\bsage\s*erp\b/i, /\bsage\b/i] },
                { label: 'Voxtron', patterns: [/\bvoxtron\b/i] },
                { label: 'Diane', patterns: [/\bdiane\b/i] },
                { label: 'Xerfi', patterns: [/\bxerfi\b/i] },
                { label: 'Quandl API', patterns: [/\bquandl api\b/i, /\bquandl\b/i] }
            ];
        }

        getPreviewRoleSignalCatalog() {
            return [
                { label: 'Financial Modelling', patterns: [/\bfinancial modelling\b/i, /\bfinancial modeling\b/i, /\blbo\b/i, /\bvaluation model\b/i] },
                { label: 'Investment Analysis', patterns: [/\binvestment analysis\b/i, /\binvestment opportunities\b/i, /\binvestment recommendation\b/i] },
                { label: 'Due Diligence', patterns: [/\bdue diligence\b/i, /\bdiligence workstreams\b/i, /\bcommercial diligence\b/i, /\bfinancial diligence\b/i] },
                { label: 'Market Research', patterns: [/\bmarket research\b/i, /\bsector research\b/i, /\bindustry research\b/i, /\bthematic research\b/i] },
                { label: 'Valuation', patterns: [/\bvaluation\b/i, /\bvaluations\b/i, /\bcomparable company\b/i, /\bprecedent transaction\b/i] },
                { label: 'Portfolio Monitoring', patterns: [/\bportfolio monitoring\b/i, /\bmonitor portfolio\b/i, /\bportfolio performance\b/i] },
                { label: 'Investment Memos', patterns: [/\binvestment memo\b/i, /\binvestment memorand\w*\b/i, /\binvestment committee memo\b/i] },
                { label: 'Committee Presentations', patterns: [/\bcommittee presentations?\b/i, /\binvestment committee\b/i, /\bic memos?\b/i] },
                { label: 'Private Equity', patterns: [/\bprivate equity\b/i, /\bpe\b/i] },
                { label: 'Private Credit', patterns: [/\bprivate credit\b/i, /\bdirect lending\b/i, /\bcredit investing\b/i] },
                { label: 'Asset Management', patterns: [/\basset management\b/i, /\bportfolio management\b/i] },
                { label: 'Investor Relations', patterns: [/\binvestor relations\b/i, /\bfundraising\b/i, /\bddq\b/i, /\brfp\b/i] },
                { label: 'Fixed Income', patterns: [/\bfixed income\b/i, /\bpreferred securities\b/i, /\bcredit research\b/i] },
                { label: 'Trade Support', patterns: [/\btrade support\b/i, /\btrade capture\b/i, /\btrade booking\b/i, /\bsettlement issues\b/i] },
                { label: 'Reconciliation', patterns: [/\breconciliation\b/i, /\bnav reconciliation\b/i, /\btrade breaks\b/i] },
                { label: 'Financial Reporting', patterns: [/\bfinancial reporting\b/i, /\bgaap\b/i, /\bstatutory\b/i, /\b10q\b/i, /\b10k\b/i] },
                { label: 'Regulatory Reporting', patterns: [/\bregulatory reporting\b/i, /\bsolvency ii\b/i, /\bprudent person principle\b/i] },
                { label: 'Product Management', patterns: [/\bproduct management\b/i, /\bproduct strategy\b/i, /\bproduct launches?\b/i] },
                { label: 'Infrastructure Investing', patterns: [/\binfrastructure\b/i, /\benergy transition\b/i, /\bdigital infrastructure\b/i] },
                { label: 'Real Estate Investing', patterns: [/\breal estate\b/i, /\bproperty financing\b/i, /\brepo facilities\b/i] },
                { label: 'M&A', patterns: [/\bm&a\b/i, /\bmergers?\b/i, /\bacquisitions?\b/i] }
            ];
        }

        getPreviewLanguageCatalog() {
            return [
                'English', 'French', 'German', 'Spanish', 'Italian', 'Portuguese', 'Arabic',
                'Mandarin', 'Cantonese', 'Japanese', 'Korean', 'Hindi', 'Urdu', 'Russian', 'Dutch'
            ];
        }

        extractPreviewRoleSignals(text, limit = 8) {
            const sourceText = String(text || '');
            const found = [];
            const seen = new Set();

            this.getPreviewRoleSignalCatalog().forEach((entry) => {
                if (entry.patterns.some((pattern) => pattern.test(sourceText))) {
                    const normalized = this.normalizePreviewSignal(entry.label);
                    if (!seen.has(normalized)) {
                        found.push(entry.label);
                        seen.add(normalized);
                    }
                }
            });

            return found.slice(0, limit);
        }

        extractPreviewLanguages(text, requireFluencyHint = false) {
            const sourceText = String(text || '');
            const found = [];
            const seen = new Set();

            this.getPreviewLanguageCatalog().forEach((language) => {
                const basePattern = new RegExp(`\\b${language.replace(/\s+/g, '\\s+')}\\b`, 'i');
                if (!basePattern.test(sourceText)) {
                    return;
                }

                if (requireFluencyHint) {
                    const languagePattern = new RegExp(`\\b${language.replace(/\s+/g, '\\s+')}\\b[^\\n.;]{0,30}\\b(native|fluent|proficient|bilingual|required|desirable)\\b|\\b(native|fluent|proficient|bilingual|required|desirable)\\b[^\\n.;]{0,30}\\b${language.replace(/\s+/g, '\\s+')}\\b`, 'i');
                    if (!languagePattern.test(sourceText)) {
                        return;
                    }
                }

                if (!seen.has(language.toLowerCase())) {
                    found.push(language);
                    seen.add(language.toLowerCase());
                }
            });

            return found.slice(0, 4);
        }

        isPreviewLikelySectionHeading(line) {
            const value = String(line || '').trim();
            if (!value) {
                return false;
            }
            if (/^(summary|profile|experience|professional experience|work experience|employment|education|skills|technical skills(?:\s*&\s*interests)?|computer skills(?:\s*&\s*interests)?|languages|interests|activities|certifications?|qualifications?|additional information|volunteer work(?:\s*&\s*certification)?|projects|publications|core strengths(?:\s*&\s*competencies)?|technical|contact|personal information)$/i.test(value)) {
                return true;
            }
            return /^[A-Z][A-Z0-9 &/(),.'’\-]{5,}$/.test(value) && value.length <= 90;
        }

        extractPreviewSkillSectionChunks(lines) {
            const chunks = [];
            let captureSkills = false;
            lines.forEach((line) => {
                const value = String(line || '').trim();
                if (!value) {
                    return;
                }
                const colonIndex = value.indexOf(':');
                if (colonIndex > 0 && colonIndex < 44) {
                    const label = value.slice(0, colonIndex).trim().toLowerCase();
                    const detail = value.slice(colonIndex + 1).trim();
                    if (/(technical skills?|computer skills?|technical|skills?|tools?|platforms?|systems?|software)/i.test(label)) {
                        if (detail) {
                            chunks.push(detail);
                        }
                        captureSkills = /^(skills?|technical skills?|computer skills?|technical)$/i.test(label);
                        return;
                    }
                }
                if (/^(skills|technical skills(?:\s*&\s*interests)?|computer skills(?:\s*&\s*interests)?|technical|skills, activities\s*&\s*interests|languages,\s*computer skills and interests)$/i.test(value.toLowerCase())) {
                    captureSkills = true;
                    return;
                }
                if (captureSkills) {
                    if (this.isPreviewLikelySectionHeading(value)) {
                        captureSkills = false;
                        return;
                    }
                    chunks.push(value);
                }
            });
            return chunks.slice(0, 18);
        }

        extractPreviewTools(lines, sourceText) {
            const chunks = this.extractPreviewSkillSectionChunks(lines);
            const scores = new Map();
            const addScore = (label, value) => {
                scores.set(label, (scores.get(label) || 0) + value);
            };

            this.getPreviewToolCatalog().forEach((entry) => {
                const sectionMatches = chunks.reduce((count, chunk) => {
                    return count + (entry.patterns.some((pattern) => pattern.test(chunk)) ? 1 : 0);
                }, 0);
                const bodyMatches = entry.patterns.reduce((count, pattern) => {
                    return count + (pattern.test(sourceText) ? 1 : 0);
                }, 0);

                if (sectionMatches) {
                    addScore(entry.label, sectionMatches * 5);
                }
                if (bodyMatches) {
                    addScore(entry.label, bodyMatches);
                }
            });

            return Array.from(scores.entries())
                .sort((a, b) => {
                    if (b[1] !== a[1]) {
                        return b[1] - a[1];
                    }
                    return a[0].localeCompare(b[0]);
                })
                .map((entry) => entry[0])
                .slice(0, 10);
        }

        getPreviewCertificationCatalog() {
            return [
                { label: 'CFA', pattern: /\bcfa\b/i },
                { label: 'CAIA', pattern: /\bcaia\b/i },
                { label: 'ACA', pattern: /\baca\b/i },
                { label: 'ACCA', pattern: /\bacca\b/i },
                { label: 'CPA', pattern: /\bcpa\b/i },
                { label: 'FRM', pattern: /\bfrm\b/i },
                { label: 'CIMA', pattern: /\bcima\b/i },
                { label: 'IMC', pattern: /\bimc\b/i },
                { label: 'Series 7', pattern: /\bseries\s*7\b/i },
                { label: 'Series 63', pattern: /\bseries\s*63\b/i },
                { label: 'Series 79', pattern: /\bseries\s*79\b/i },
                { label: 'MBA', pattern: /\bmba\b/i },
                { label: 'BSc', pattern: /\bbachelor of science\b|\bbsc\b/i },
                { label: 'BA', pattern: /\bbachelor of arts\b|\bba\b/i },
                { label: 'BASc', pattern: /\bbachelor of arts and sciences\b|\bbasc\b/i },
                { label: 'BEng', pattern: /\bbachelor of engineering\b|\bbeng\b/i },
                { label: 'BCom', pattern: /\bbachelor of commerce\b|\bbcom\b/i },
                { label: 'BBA', pattern: /\bbachelor of business administration\b|\bbba\b/i },
                { label: 'BEc', pattern: /\bbachelor of economics\b|\bbec\b/i },
                { label: 'LLB', pattern: /\bbachelor of law\b|\bllb\b/i },
                { label: 'MEng', pattern: /\bmaster of engineering\b|\bmeng\b/i },
                { label: 'MChem', pattern: /\bmaster of chemistry\b|\bmchem\b/i },
                { label: 'MBio', pattern: /\bmaster of bioscience\b|\bmbio\b/i },
                { label: 'MSci', pattern: /\bmaster of science\b|\bmsci\b/i },
                { label: 'MSc', pattern: /\bmaster of science\b|\bmsc\b/i },
                { label: 'MA', pattern: /\bmaster of arts\b|\bma\b/i },
                { label: 'MMath', pattern: /\bmaster of mathematics\b|\bmmath\b/i },
                { label: 'MMathPhys', pattern: /\bmaster of mathematics and physics\b|\bmmathphys\b/i },
                { label: 'MMathStat', pattern: /\bmaster of mathematics and statistics\b|\bmmathstat\b/i },
                { label: 'MPhys', pattern: /\bmaster of physics\b|\bmphys\b/i },
                { label: 'MMORSE', pattern: /\bmaster of mathematics, operational research, statistics, and economics\b|\bmmorse\b/i },
                { label: 'MFin', pattern: /\bmaster of finance\b|\bmfin\b/i },
                { label: 'MRes', pattern: /\bmaster of research\b|\bmres\b/i },
                { label: 'MPhil', pattern: /\bmaster of philosophy\b|\bmphil\b/i },
                { label: 'PhD', pattern: /\bphd\b|\bdoctor of philosophy\b/i },
                { label: 'DPhil', pattern: /\bdphil\b/i },
                { label: 'JD', pattern: /\bjuris doctor\b|\bjd\b/i },
                { label: 'MBBS', pattern: /\bmbbs\b|\bbachelor of medicine\b/i },
                { label: 'MBChB', pattern: /\bmbchb\b/i }
            ];
        }

        extractKnownCertifications(sourceText, seeded = []) {
            const found = Array.isArray(seeded) ? seeded.slice() : [];
            const seen = new Set(found.map((item) => String(item).toLowerCase()));
            this.getPreviewCertificationCatalog().forEach((entry) => {
                if (entry.pattern.test(sourceText) && !seen.has(entry.label.toLowerCase())) {
                    found.push(entry.label);
                    seen.add(entry.label.toLowerCase());
                }
            });
            this.extractAcademicQualificationLines(sourceText).forEach((entry) => {
                if (!seen.has(entry.toLowerCase())) {
                    found.push(entry);
                    seen.add(entry.toLowerCase());
                }
            });
            return found.slice(0, 12);
        }

        extractAcademicQualificationLines(sourceText) {
            const text = String(sourceText || '');
            if (!text) {
                return [];
            }
            const patterns = [
                /\b([A-Z][A-Za-z&,'\/()\- ]{2,90})\s+(BSc|BA|BASc|BEng|BCom|BBA|BEc|LLB|MEng|MChem|MBio|MSci|MSc|MA|MMathPhys|MMathStat|MMath|MPhys|MMORSE|MFin|MRes|MPhil|MBA|PhD|DPhil|JD|MBBS|MBChB)\b/g,
                /\b(Bachelor of Science|Bachelor of Arts|Bachelor of Arts and Sciences|Bachelor of Engineering|Bachelor of Commerce|Bachelor of Business Administration|Bachelor of Economics|Bachelor of Law|Master of Engineering|Master of Chemistry|Master of Bioscience|Master of Science|Master of Arts|Master of Mathematics and Physics|Master of Mathematics and Statistics|Master of Mathematics|Master of Physics|Master of Mathematics, Operational Research, Statistics, and Economics|Master of Finance|Master of Research|Master of Philosophy|Doctor of Philosophy)\s*\(([A-Za-z]+)\)/g
            ];
            const results = [];
            const seen = new Set();
            patterns.forEach((pattern) => {
                let match;
                while ((match = pattern.exec(text)) !== null) {
                    const raw = String(match[0] || '').replace(/\s+/g, ' ').trim();
                    if (!raw || raw.length > 110) {
                        continue;
                    }
                    if (!seen.has(raw.toLowerCase())) {
                        results.push(raw);
                        seen.add(raw.toLowerCase());
                    }
                }
            });
            return results.slice(0, 8);
        }

        normalizeQualificationLabel(label) {
            const value = String(label || '').trim();
            if (!value) {
                return '';
            }
            const upper = value.toUpperCase();
            const directMap = [
                'CFA', 'CAIA', 'ACA', 'ACCA', 'CPA', 'FRM', 'CIMA', 'IMC',
                'SERIES 7', 'SERIES 63', 'SERIES 79', 'MBA', 'BSC', 'BA', 'BASC',
                'BENG', 'BCOM', 'BBA', 'BEC', 'LLB', 'MENG', 'MCHEM', 'MBIO',
                'MSCI', 'MSC', 'MA', 'MMATHPHYS', 'MMATHSTAT', 'MMATH', 'MPHYS',
                'MMORSE', 'MFIN', 'MRES', 'MPHIL', 'PHD', 'DPHIL', 'JD', 'MBBS', 'MBCHB'
            ];
            for (let i = 0; i < directMap.length; i++) {
                if (upper.includes(directMap[i])) {
                    return directMap[i];
                }
            }
            if (/bachelor of science/i.test(value)) return 'BSC';
            if (/bachelor of arts and sciences/i.test(value)) return 'BASC';
            if (/bachelor of arts/i.test(value)) return 'BA';
            if (/bachelor of engineering/i.test(value)) return 'BENG';
            if (/bachelor of commerce/i.test(value)) return 'BCOM';
            if (/bachelor of business administration/i.test(value)) return 'BBA';
            if (/bachelor of economics/i.test(value)) return 'BEC';
            if (/bachelor of law/i.test(value)) return 'LLB';
            if (/master of engineering/i.test(value)) return 'MENG';
            if (/master of chemistry/i.test(value)) return 'MCHEM';
            if (/master of bioscience/i.test(value)) return 'MBIO';
            if (/master of science/i.test(value)) return 'MSC';
            if (/master of arts/i.test(value)) return 'MA';
            if (/master of mathematics and physics/i.test(value)) return 'MMATHPHYS';
            if (/master of mathematics and statistics/i.test(value)) return 'MMATHSTAT';
            if (/master of mathematics/i.test(value)) return 'MMATH';
            if (/master of physics/i.test(value)) return 'MPHYS';
            if (/master of finance/i.test(value)) return 'MFIN';
            if (/master of research/i.test(value)) return 'MRES';
            if (/master of philosophy/i.test(value)) return 'MPHIL';
            if (/doctor of philosophy/i.test(value)) return 'PHD';
            return upper;
        }

        normalizePreviewSignal(value) {
            return String(value || '')
                .toLowerCase()
                .replace(/\b(native|fluent|proficient|bilingual|required|preferred|desirable)\b/g, '')
                .replace(/[^\w+#./& -]+/g, ' ')
                .replace(/\s+/g, ' ')
                .trim();
        }

        cleanPreviewSignal(value) {
            return String(value || '')
                .replace(/\((native|fluent|proficient|bilingual)\)/ig, '')
                .replace(/\s+/g, ' ')
                .trim();
        }

        isWeakPreviewSignal(value) {
            const normalized = this.normalizePreviewSignal(value);
            if (!normalized) {
                return true;
            }

            const weakTerms = new Set([
                'investment',
                'investments',
                'private',
                'public',
                'portfolio',
                'markets',
                'market',
                'finance',
                'financial',
                'analysis',
                'research',
                'role',
                'company',
                'astorg'
            ]);

            if (weakTerms.has(normalized)) {
                return true;
            }

            if (normalized.length < 4) {
                return true;
            }

            return false;
        }

        extractPreviewCvTitleEntries(lines) {
            const entries = [];
            const titlePattern = this.getPreviewTitleKeywordPattern();
            lines.forEach((line, index) => {
                if (!line || line.length > 140 || /^[-•]/.test(line) || /@/.test(line)) {
                    return;
                }
                const lower = line.toLowerCase();
                if (/^(education|experience|work experience|employment|skills|languages|certifications|qualifications|contact|summary|profile)$/i.test(lower)) {
                    return;
                }
                const hasTitleKeyword = titlePattern.test(line);
                const detectedSeniority = this.detectPreviewSeniorityLevel(line);
                const adjacentDateText = [lines[index - 1] || '', line, lines[index + 1] || '', lines[index + 2] || ''].join(' ');
                const dateRange = this.extractPreviewDateRange(adjacentDateText);
                let score = 0;
                if (hasTitleKeyword) score += 2;
                if (detectedSeniority) score += 2;
                if (dateRange) score += 2;
                if (/[|,@\-–—]/.test(line)) score += 1;
                if (line.split(/\s+/).length <= 8) score += 1;
                if (score < 3) {
                    return;
                }
                entries.push({
                    title: line.replace(/\s+/g, ' ').trim(),
                    index,
                    score,
                    seniority: detectedSeniority,
                    dateRange
                });
            });
            return entries.slice(0, 8);
        }

        detectPreviewSeniorityLevel(text) {
            const source = String(text || '');
            let best = null;
            this.getPreviewSeniorityDefinitions().forEach((entry) => {
                const matched = entry.patterns.some((pattern) => pattern.test(source));
                if (!matched) {
                    return;
                }
                if (!best || entry.level > best.level) {
                    best = {
                        key: entry.key,
                        label: entry.label,
                        level: entry.level
                    };
                }
            });
            return best;
        }

        extractPreviewDateRange(text) {
            const source = String(text || '');
            if (!source) {
                return null;
            }
            let match = source.match(/((?:19|20)\d{2})\s*[-–—]\s*(present|current|now|(?:19|20)\d{2})/i);
            if (match) {
                return {
                    startYear: parseInt(match[1], 10),
                    endYear: /present|current|now/i.test(match[2]) ? new Date().getFullYear() : parseInt(match[2], 10),
                    isCurrent: /present|current|now/i.test(match[2])
                };
            }
            match = source.match(/(?:jan|january|feb|february|mar|march|apr|april|may|jun|june|jul|july|aug|august|sep|sept|september|oct|october|nov|november|dec|december)\s+((?:19|20)\d{2}).{0,24}(present|current|now|(?:jan|january|feb|february|mar|march|apr|april|may|jun|june|jul|july|aug|august|sep|sept|september|oct|october|nov|november|dec|december)\s+(?:19|20)\d{2})/i);
            if (match) {
                const endYearMatch = String(match[2]).match(/((?:19|20)\d{2})/);
                return {
                    startYear: parseInt(match[1], 10),
                    endYear: /present|current|now/i.test(match[2]) ? new Date().getFullYear() : (endYearMatch ? parseInt(endYearMatch[1], 10) : parseInt(match[1], 10)),
                    isCurrent: /present|current|now/i.test(match[2])
                };
            }
            return null;
        }

        buildPreviewCvSeniorityProfile(entries) {
            if (!Array.isArray(entries) || !entries.length) {
                return {
                    detected: false,
                    confidence: 'low',
                    confidenceScore: 0,
                    estimatedYears: 0,
                    timelineSummary: 'Timeline unclear',
                    topTitles: []
                };
            }

            const weightedEntries = entries.filter((entry) => entry.seniority);
            const totalWeight = weightedEntries.reduce((sum, entry) => sum + entry.score + (entry.dateRange && entry.dateRange.isCurrent ? 1 : 0), 0);
            const weightedLevel = weightedEntries.reduce((sum, entry) => sum + (entry.seniority.level * (entry.score + (entry.dateRange && entry.dateRange.isCurrent ? 1 : 0))), 0);
            const averageLevel = totalWeight ? (weightedLevel / totalWeight) : 0;
            const yearsSeen = entries
                .map((entry) => entry.dateRange)
                .filter(Boolean)
                .map((range) => Math.max(0.5, (range.endYear - range.startYear) + 1));
            const estimatedYears = yearsSeen.length
                ? Math.round((yearsSeen.reduce((sum, value) => sum + value, 0) / yearsSeen.length) * 10) / 10
                : 0;
            const currentEntry = weightedEntries.find((entry) => entry.dateRange && entry.dateRange.isCurrent) || weightedEntries[0] || entries[0];
            const currentSeniority = currentEntry && currentEntry.seniority
                ? currentEntry.seniority
                : this.mapPreviewLevelToSeniority(Math.round(averageLevel));
            const confidenceScore = Math.min(100, Math.round((weightedEntries.length * 18) + (yearsSeen.length * 16) + Math.min(26, totalWeight * 2.5)));
            const confidence = confidenceScore >= 68 ? 'high' : (confidenceScore >= 42 ? 'medium' : 'low');

            return {
                detected: !!currentSeniority,
                confidence,
                confidenceScore,
                estimatedYears,
                currentSeniority,
                averageLevel,
                timelineSummary: currentEntry ? currentEntry.title : 'Timeline unclear',
                topTitles: entries.slice(0, 3).map((entry) => entry.title)
            };
        }

        mapPreviewLevelToSeniority(level) {
            return this.getPreviewSeniorityDefinitions().find((entry) => entry.level === level) || null;
        }

        detectPreviewRoleSeniority(roleTitle, sourceText) {
            const titleSeniority = this.detectPreviewSeniorityLevel(roleTitle);
            const bodySeniority = this.detectPreviewSeniorityLevel(sourceText);
            const detected = titleSeniority || bodySeniority || null;
            return {
                detected: !!detected,
                currentSeniority: detected,
                timelineSummary: roleTitle || 'this role'
            };
        }

        comparePreviewSeniority(cvProfile, roleProfile) {
            const roleSeniority = roleProfile && roleProfile.currentSeniority ? roleProfile.currentSeniority : null;
            const cvSeniority = cvProfile && cvProfile.currentSeniority ? cvProfile.currentSeniority : null;
            if (!cvSeniority || !roleSeniority) {
                return {
                    label: 'Seniority unclear',
                    tone: 'neutral',
                    detail: 'We need clearer titles and dates to estimate your level confidently.',
                    confidence: cvProfile?.confidence || 'low',
                    currentTitle: cvProfile?.timelineSummary || 'Timeline unclear',
                    roleTitle: roleProfile?.timelineSummary || 'this role',
                    estimatedYears: cvProfile?.estimatedYears || 0
                };
            }
            const delta = cvSeniority.level - roleSeniority.level;
            let label = 'Seniority aligned';
            let tone = 'good';
            let detail = `Your likely level looks close to the role’s ${roleSeniority.label.toLowerCase()} seniority.`;
            if (delta >= 2 || (delta >= 1 && (cvProfile?.estimatedYears || 0) >= this.getPreviewExpectedYears(roleSeniority.level) + 2)) {
                label = 'Potentially overqualified';
                tone = 'risk';
                detail = `Your CV reads closer to ${cvSeniority.label.toLowerCase()} level, so recruiters may see you as senior for this role.`;
            } else if (delta <= -2) {
                label = 'Potentially underqualified';
                tone = 'risk';
                detail = `The role reads closer to ${roleSeniority.label.toLowerCase()} level, while your CV currently looks more ${cvSeniority.label.toLowerCase()}.`;
            } else if (delta === 1) {
                label = 'Slightly senior';
                tone = 'warn';
                detail = `Your CV reads a touch more senior than the role, so be ready to explain the fit.`;
            } else if (delta === -1) {
                label = 'Slightly junior';
                tone = 'info';
                detail = `You look slightly earlier-career than the role, but the gap is still workable.`;
            }
            return {
                label,
                tone,
                detail,
                confidence: cvProfile?.confidence || 'medium',
                currentTitle: cvProfile?.timelineSummary || '',
                roleTitle: roleProfile?.timelineSummary || '',
                cvSeniority,
                roleSeniority,
                estimatedYears: cvProfile?.estimatedYears || 0
            };
        }

        getPreviewExpectedYears(level) {
            const map = {
                1: 1,
                2: 2,
                3: 4,
                4: 5,
                5: 7,
                6: 9,
                7: 12,
                8: 15
            };
            return map[level] || 4;
        }

        comparePreviewCertifications(cvCerts, roleCerts) {
            const cvList = Array.isArray(cvCerts) ? cvCerts : [];
            const roleList = Array.isArray(roleCerts) ? roleCerts : [];
            const cvSet = new Set(cvList.map((item) => this.normalizeQualificationLabel(item)).filter(Boolean));
            const included = roleList.filter((item) => cvSet.has(this.normalizeQualificationLabel(item)));
            const missing = roleList.filter((item) => !cvSet.has(this.normalizeQualificationLabel(item)));
            return {
                cv: cvList.slice(0, 4),
                role: roleList.slice(0, 6),
                included: included.slice(0, 4),
                missing: missing.slice(0, 4)
            };
        }

        extractMeaningfulTerms(text, limit = 10) {
            const stopWords = new Set([
                'the', 'and', 'for', 'with', 'you', 'your', 'this', 'that', 'from',
                'have', 'has', 'will', 'into', 'role', 'roles', 'their', 'they',
                'our', 'about', 'across', 'using', 'used', 'able', 'strong',
                'clear', 'need', 'needs', 'work', 'works', 'working', 'team',
                'experience', 'years', 'year', 'skills', 'skill', 'candidate',
                'responsible', 'responsibilities', 'requirements', 'requirement',
                'preferred', 'preference', 'apply', 'application', 'finance',
                'investment', 'investments', 'private', 'public', 'portfolio',
                'market', 'markets', 'analysis', 'research', 'astorg'
            ]);
            const counts = new Map();
            const cleanedText = String(text || '').replace(/[^\w+#./& -]+/g, ' ');

            cleanedText.split(/\s+/).forEach((word) => {
                const term = word.trim();
                const normalized = term.toLowerCase();
                if (normalized.length < 4 || stopWords.has(normalized) || /^\d+$/.test(normalized)) {
                    return;
                }
                if (!/[a-z]/i.test(normalized)) {
                    return;
                }
                counts.set(term, (counts.get(term) || 0) + 1);
            });

            return Array.from(counts.entries())
                .sort((a, b) => b[1] - a[1])
                .map((entry) => entry[0])
                .slice(0, limit);
        }

        renderGuestPreviewAccessCard(data) {
            if (this.isLoggedIn || this.hasPremiumAccess) {
                return;
            }

            const $cta = this.$container.find('.inst-preview-access-cta').first();
            if (!$cta.length) {
                return;
            }

            const score = parseInt(data?.scores?.overall || data?.executive_summary?.match_score || 0, 10) || 0;
            const strengths = (data?.strengths_to_highlight || []).map((item) => item.strength).filter(Boolean).slice(0, 3);
            const gaps = (data?.keyword_analysis?.critical_missing || []).filter(Boolean).slice(0, 2);
            const detected = data?.__detectedCv || {};
            const profileSignals = data?.__profileSignals || {};
            const careerSignals = data?.__careerSignals || {};
            const senioritySignals = careerSignals.seniority || {};
            const certificationSignals = careerSignals.certifications || {};
            const summary = data?.executive_summary?.recommendation || 'MENA Careers prepared a realistic preview based on your pasted CV.';
            const snippetsMarkup = strengths.map((item) => `<span>${this.escapeHtml(item)}</span>`).join('');
            const gapCopy = gaps.length
                ? `Full analysis will show how to tighten ${gaps.join(' and ')} before you apply.`
                : 'Full analysis will show the missing signals to tighten before you apply.';
            const detectedGroups = [
                {
                    label: 'Languages detected',
                    values: Array.isArray(detected.languages) ? detected.languages.slice(0, 3) : []
                },
                {
                    label: 'Role signals spotted',
                    values: Array.isArray(detected.roleSignals) ? detected.roleSignals.slice(0, 3) : []
                },
                {
                    label: 'Tools and systems',
                    values: Array.isArray(detected.tools) ? detected.tools.slice(0, 3) : []
                },
                {
                    label: 'Qualifications found',
                    values: Array.isArray(detected.certifications) ? detected.certifications.slice(0, 2) : []
                }
            ].filter((group) => group.values.length);
            const detectedMarkup = detectedGroups.length
                ? `<div class="inst-preview-access-cta__detected"><div class="inst-preview-access-cta__detected-head">What we detected from your CV so far</div>${detectedGroups.map((group) => `<div class="inst-preview-access-cta__detected-row"><strong>${this.escapeHtml(group.label)}</strong><div class="inst-preview-access-cta__detected-tags">${group.values.map((value) => `<span>${this.escapeHtml(value)}</span>`).join('')}</div></div>`).join('')}</div>`
                : '';
            const quickChecks = [
                {
                    label: 'LinkedIn profile',
                    present: !!profileSignals.hasLinkedIn,
                    detail: profileSignals.hasLinkedIn ? 'Found linkedin.com/in/' : 'Missing linkedin.com/in/'
                },
                {
                    label: 'Email address',
                    present: !!profileSignals.hasEmail,
                    detail: profileSignals.hasEmail ? 'Found email address' : 'Missing email address'
                },
                {
                    label: 'Phone number',
                    present: !!profileSignals.hasPhone,
                    detail: profileSignals.hasPhone ? 'Found telephone number' : 'Missing telephone number'
                },
                {
                    label: 'Experience dates',
                    present: !!profileSignals.hasDates,
                    detail: profileSignals.hasDates
                        ? `Found ${profileSignals.dateCount || 0} date signals`
                        : 'Missing clear duration / date signals'
                }
            ];
            const checksMarkup = `
                <div class="inst-preview-access-cta__checks">
                    <div class="inst-preview-access-cta__checks-head">
                        <strong>Quick CV checks</strong>
                        <span>Present vs missing</span>
                    </div>
                    <div class="inst-preview-access-cta__checks-grid">
                        ${quickChecks.map((item) => `
                            <div class="inst-preview-access-cta__check ${item.present ? 'is-present' : 'is-missing'}">
                                <span class="inst-preview-access-cta__check-icon" aria-hidden="true">${item.present ? '✓' : '!'}</span>
                                <div class="inst-preview-access-cta__check-copy">
                                    <strong>${this.escapeHtml(item.label)}</strong>
                                    <span>${this.escapeHtml(item.detail)}</span>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
            const certificationsIncluded = Array.isArray(certificationSignals.included) ? certificationSignals.included : [];
            const certificationsMissing = Array.isArray(certificationSignals.missing) ? certificationSignals.missing : [];
            const roleCertifications = Array.isArray(certificationSignals.role) ? certificationSignals.role : [];
            const cvCertifications = Array.isArray(certificationSignals.cv) ? certificationSignals.cv : [];
            const seniorityMarkup = `
                <div class="inst-preview-access-cta__careerfit">
                    <div class="inst-preview-access-cta__careerfit-head">
                        <strong>Seniority & certifications</strong>
                        <span>Deterministic CV read</span>
                    </div>
                    <div class="inst-preview-access-cta__careerfit-grid">
                        <div class="inst-preview-access-cta__careerfit-card is-${this.escapeHtml(senioritySignals.tone || 'neutral')}">
                            <div class="inst-preview-access-cta__careerfit-kicker">Seniority match</div>
                            <strong>${this.escapeHtml(senioritySignals.label || 'Seniority unclear')}</strong>
                            <p>${this.escapeHtml(senioritySignals.detail || 'We need clearer title and date signals to compare your level to the role.')}</p>
                            <div class="inst-preview-access-cta__careerfit-meta">
                                <span>CV read: ${this.escapeHtml(senioritySignals.currentTitle || 'Timeline unclear')}</span>
                                <span>Role: ${this.escapeHtml(senioritySignals.roleTitle || 'this role')}</span>
                                <span>Confidence: ${this.escapeHtml((senioritySignals.confidence || 'low').toUpperCase())}${senioritySignals.estimatedYears ? ` · ~${this.escapeHtml(String(senioritySignals.estimatedYears))} years` : ''}</span>
                            </div>
                        </div>
                        <div class="inst-preview-access-cta__careerfit-card">
                            <div class="inst-preview-access-cta__careerfit-kicker">Certifications</div>
                            <strong>${roleCertifications.length ? 'What the role expects vs your CV' : 'No clear role certifications detected'}</strong>
                            <div class="inst-preview-access-cta__careerfit-groups">
                                <div class="inst-preview-access-cta__careerfit-group">
                                    <span>Included</span>
                                    <div class="inst-preview-access-cta__careerfit-tags">
                                        ${certificationsIncluded.length
                                            ? certificationsIncluded.map((item) => `<b class="is-included">${this.escapeHtml(item)}</b>`).join('')
                                            : '<b class="is-empty">None detected</b>'}
                                    </div>
                                </div>
                                <div class="inst-preview-access-cta__careerfit-group">
                                    <span>Missing</span>
                                    <div class="inst-preview-access-cta__careerfit-tags">
                                        ${certificationsMissing.length
                                            ? certificationsMissing.map((item) => `<b class="is-missing">${this.escapeHtml(item)}</b>`).join('')
                                            : '<b class="is-empty">No obvious gaps</b>'}
                                    </div>
                                </div>
                            </div>
                            ${!roleCertifications.length && cvCertifications.length ? `<p class="inst-preview-access-cta__careerfit-note">Detected on your CV: ${cvCertifications.map((item) => this.escapeHtml(item)).join(', ')}</p>` : ''}
                        </div>
                    </div>
                </div>
            `;
            const promiseCopy = gaps.length
                ? `Full review will turn these signals into sharper ATS fixes, stronger role positioning, and better application materials for ${gaps.join(' and ')}.`
                : 'Full review will turn these signals into sharper ATS fixes, stronger role positioning, and better application materials for this role.';

            $cta.html(`
                <div class="inst-preview-access-cta__signalstrip">
                    <div class="inst-preview-access-cta__content">
                        <div class="inst-preview-access-cta__eyebrow">Preview ready</div>
                        <strong>Upgrade to view your results and analysis</strong>
                        <p>${this.escapeHtml(summary)}</p>
                        ${snippetsMarkup ? `<div class="inst-preview-access-cta__snippets">${snippetsMarkup}</div>` : ''}
                        ${checksMarkup}
                        ${seniorityMarkup}
                        ${detectedMarkup}
                        <small>${this.escapeHtml(gapCopy)}</small>
                        <small class="inst-preview-access-cta__promise">${this.escapeHtml(promiseCopy)}</small>
                    </div>
                    <a class="inst-preview-access-cta__button inst-preview-access-cta__button--analysis" href="${this.membershipUrl}">
                        <span>Upgrade to view your results and analysis</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 12h14"/>
                            <path d="M13 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>
            `);

            const $previewLock = this.$container.find('[data-preview-report-stop]').first();
            if ($previewLock.length) {
                $previewLock.insertAfter($cta);
            }
        }

        updateLoaderProgress(percent) {
            const rounded = Math.round(percent);
            this.$loader.find('[data-loader-percent]').text(rounded + '%');
            this.$loader.find('[data-loader-bar]').css('width', rounded + '%');
        }

        showCVRequiredState(message = 'Please paste your CV') {
            const $cvSection = this.$container.find('[data-gap-cv-section]').first();
            const $cvFeedback = this.$container.find('[data-gap-cv-feedback]').first();
            const $cvTextarea = this.$container.find('[data-input="cv"]').first();
            const $cvDetails = $cvSection.closest('details');

            if ($cvDetails.length) {
                $cvDetails.prop('open', true);
            }

            $cvSection.addClass('is-required');
            if ($cvFeedback.length) {
                $cvFeedback.text(message).prop('hidden', false);
            }

            if ($cvTextarea.length) {
                $cvTextarea.trigger('focus');
            }
        }

        clearCVRequiredState() {
            const $cvSection = this.$container.find('[data-gap-cv-section]').first();
            const $cvFeedback = this.$container.find('[data-gap-cv-feedback]').first();

            $cvSection.removeClass('is-required');
            if ($cvFeedback.length) {
                $cvFeedback.prop('hidden', true).text('Please paste your CV');
            }
        }

        getLoaderPortalTarget() {
            const $gapModal = this.$container.closest('[data-reddit-gap-modal]');
            if ($gapModal.length) {
                return $gapModal;
            }

            return $('body');
        }

        mountLoaderToPortal() {
            if (!this.$loader.length) {
                this.$loader = this.$container.find('[data-loader="analysis"]').first();
            }

            if (!this.$loader.length || this.$loader.data('isPortaled')) {
                return;
            }

            this.$loaderOriginalParent = this.$loader.parent();
            this.$loaderOriginalNextSibling = this.$loader.next();
            this.$loaderPortalTarget = this.getLoaderPortalTarget();

            this.$loader.detach();
            this.$loaderPortalTarget.append(this.$loader);
            this.$loader.data('isPortaled', true);
        }

        restoreLoaderFromPortal() {
            if (!this.$loader.length || !this.$loader.data('isPortaled') || !this.$loaderOriginalParent.length) {
                return;
            }

            if (this.$loaderOriginalNextSibling.length && this.$loaderOriginalNextSibling.parent().length) {
                this.$loaderOriginalNextSibling.before(this.$loader);
            } else {
                this.$loaderOriginalParent.append(this.$loader);
            }

            this.$loader.removeData('isPortaled');
            this.$loaderPortalTarget = $();
            this.$loaderOriginalParent = $();
            this.$loaderOriginalNextSibling = $();
        }

        delay(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        }

        isEmbeddedInCvMatchStudio() {
            return (this.$container.attr('data-embedded-context') || '') === 'cv_match_studio';
        }

        setMobileStep(step) {
            const normalized = ['cv', 'jd', 'scan'].includes(step) ? step : 'cv';
            const meta = {
                cv: {
                    title: 'CV',
                    page: 'Page 1 of 3',
                    progress: '33.333%',
                },
                jd: {
                    title: 'Job description',
                    page: 'Page 2 of 3',
                    progress: '66.666%',
                },
                scan: {
                    title: 'Scan',
                    page: 'Page 3 of 3',
                    progress: '100%',
                },
            };

            this.mobileStep = normalized;
            this.$container.attr('data-gap-mobile-step', normalized);
            this.$container.find('[data-gap-mobile-title]').text(meta[normalized].title);
            this.$container.find('[data-gap-mobile-page]').text(meta[normalized].page);
            this.$container.find('[data-gap-mobile-progress]').css('width', meta[normalized].progress);
            this.updateMobileStepMeta();

            const target = this.$container.find(`[data-gap-mobile-panel="${normalized}"]`).first();
            if (target.length && window.matchMedia && window.matchMedia('(max-width: 720px)').matches) {
                window.setTimeout(() => {
                    target.get(0).scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 30);
            }
        }

        goToMobileStep(step) {
            const target = ['cv', 'jd', 'scan'].includes(step) ? step : 'cv';

            if ((target === 'jd' || target === 'scan') && this.cvText.length < 40) {
                this.showCVRequiredState('Please upload or paste your CV first.');
                this.updateStatus('error', 'Missing CV', 'Please upload or paste your CV to continue.');
                this.setMobileStep('cv');
                return;
            }

            if (target === 'scan') {
                if (this.jdText.length < 100) {
                    this.showError('Please paste the full job description before scanning.');
                    this.setMobileStep('jd');
                    return;
                }
            }

            this.setMobileStep(target);
        }

        updateMobileStepMeta() {
            const cvReady = this.cvText.length >= 40;
            const jdReady = this.jdText.length >= 100;
            this.$container.toggleClass('has-gap-mobile-cv', cvReady);
            this.$container.toggleClass('has-gap-mobile-jd', jdReady);
            this.$container.find('[data-gap-mobile-next="jd"]').prop('disabled', !cvReady);
            this.$container.find('[data-gap-mobile-next="scan"]').prop('disabled', !jdReady);
        }

        setStage(stage) {
            const normalizedStage = stage === 'results' ? 'results' : 'scan';
            this.$container.attr('data-gap-stage', normalizedStage);
            this.$container.find('.inst-gap-scan-stage').prop('hidden', normalizedStage !== 'scan');
            this.$container.find('.inst-gap-results-stage').prop('hidden', normalizedStage !== 'results');
            if (normalizedStage === 'scan' && this.mobileStep !== 'cv' && this.mobileStep !== 'jd' && this.mobileStep !== 'scan') {
                this.setMobileStep('cv');
            }
        }

        switchReportView(view) {
            if (!this.$container.find('.inst-view-toggle-btn').length || !this.$container.find('.inst-panel-view').length) {
                return;
            }

            const normalized = view === 'toolkit' ? 'toolkit' : 'report';
            if (!this.$container.find(`.inst-${normalized}-view`).length) {
                return;
            }
            this.$container.find('.inst-view-toggle-btn').removeClass('is-active').attr('aria-selected', 'false');
            this.$container.find(`.inst-view-toggle-btn[data-view="${normalized}"]`).addClass('is-active').attr('aria-selected', 'true');
            this.$container.find('.inst-panel-view').removeClass('is-active').hide();
            this.$container.find(`.inst-${normalized}-view`).addClass('is-active').show();
        }

        scrollToResultsSection(section) {
            const $target = this.$container.find(`[data-optimizer-section="${section}"]`).first();
            if (!$target.length) {
                return;
            }

            const panel = this.$container.find('.inst-charts-panel').get(0);
            const panelRect = panel ? panel.getBoundingClientRect() : null;
            const targetRect = $target.get(0).getBoundingClientRect();

            if (panel && panelRect) {
                const scrollOffset = targetRect.top - panelRect.top + panel.scrollTop - 24;
                panel.scrollTo({
                    top: Math.max(0, scrollOffset),
                    behavior: 'smooth'
                });
                return;
            }

            window.scrollTo({
                top: window.scrollY + targetRect.top - 24,
                behavior: 'smooth'
            });
        }

        updateDisplayedJobTitle(jobTitleText = '') {
            const cleaned = (jobTitleText || '').trim();
            const currentTitle = this.$container.find('[data-gap-job-title]').first().text().trim();
            const finalTitle = cleaned || currentTitle || 'Optimize this application';

            this.$container.find('[data-gap-job-title]').text(finalTitle);
            this.$container.find('[data-gap-scan-job-title]').text(finalTitle);
            this.$container.find('[data-gap-report-title]').text(finalTitle);
        }

        renderResults(data) {
            this.setStage('results');
            // Update scores
            this.renderScores(data);
            this.renderReportCover(data);

            // Update executive summary status
            const summary = data.executive_summary || {};
            this.updateStatus('success', summary.risk_level || 'Complete', summary.recommendation || 'Analysis complete');
            this.$container.find('.inst-chatbox-hint').text(`${summary.match_score || data.scores?.overall || 0}% match`);
            this.updateSectionKickers(summary.location || '');

            // Render missing/gaps
            this.renderMissingSection(data);

            // Render matched/strengths
            this.renderMatchedSection(data);

            // Render recommendations
            this.renderRecommendations(data);

            // Render toolkit sections
            this.renderToolkit(data);

            // Apply tactical preview gating for non-premium users.
            this.applyPreviewModeToResults();

            // Scroll to results
            $('html, body').animate({
                scrollTop: this.$container.find('.inst-charts-panel').offset().top - 20
            }, 500);
        }

        renderReportCover(data) {
            const scores = data.scores || {};
            const summary = data.executive_summary || {};
            const overall = Math.max(0, Math.min(100, parseInt(scores.overall || summary.match_score || 0, 10)));
            const skills = Math.max(0, Math.min(100, parseInt(scores.skills_match || 0, 10)));
            const experience = Math.max(0, Math.min(100, parseInt(scores.experience_match || 0, 10)));
            const keywords = Math.max(0, Math.min(100, parseInt(scores.keywords_match || 0, 10)));
            const verdict = String(summary.verdict || data.overall_assessment?.final_recommendation || '').trim();
            const keyInsight = String(summary.key_insight || '').trim();
            const recommendation = String(summary.recommendation || summary.risk_level || '').trim();
            const location = String(summary.location || '').trim();
            const compassDegrees = Math.round((overall / 100) * 360);
            const accentEnd = Math.min(360, compassDegrees + 36);

            this.$container.find('[data-report-verdict]').text(
                verdict || 'Your report is ready. Use the scorecard and action plan to decide what to fix before applying.'
            );
            this.$container.find('[data-report-recommendation]').text(recommendation || 'Analysis complete');
            this.$container.find('[data-report-location]').text(location || 'Role-specific review');
            this.$container.find('[data-report-compass-score]').text(overall + '%');
            this.$container.find('[data-report-compass]').css(
                'background',
                `conic-gradient(#0a66c2 0deg ${compassDegrees}deg, #f2c94c ${compassDegrees}deg ${accentEnd}deg, rgba(23, 35, 44, 0.08) ${accentEnd}deg 360deg)`
            );
            this.$container.find('[data-report-signal="skills"]').css('height', Math.max(12, skills) + '%');
            this.$container.find('[data-report-signal="experience"]').css('height', Math.max(12, experience) + '%');
            this.$container.find('[data-report-signal="keywords"]').css('height', Math.max(12, keywords) + '%');
            this.$container.find('[data-report-key-insight] strong').text(
                keyInsight || 'The report has identified the highest-impact gaps and the strongest evidence in your current CV.'
            );
        }

        updateSectionKickers(location = '') {
            const cleanedLocation = String(location || '').trim();
            this.$container.find('.inst-optimizer-section-kicker[data-base-kicker]').each((_, node) => {
                const $node = $(node);
                const base = String($node.attr('data-base-kicker') || $node.text() || '').trim();
                if (!base) {
                    return;
                }

                $node.text(cleanedLocation ? `${cleanedLocation} · ${base}` : base);
            });
        }

        renderToolkit(data) {
            this.renderCVPreview(data);
            this.renderCVImprovements(data);
            this.renderCoverLetter(data);
            this.renderKeywords(data);
            this.renderInterviewPrep(data);
        }

        applyPreviewModeToResults() {
            this.clearPreviewModeArtifacts();

            if (this.hasPremiumAccess) {
                return;
            }

            this.$container.addClass('inst-preview-mode');

            this.gateActionButton('[data-action="optimize"]', 'Unlock one-click optimization with membership');
            this.gateActionButton('[data-gap-open-networking]', 'Unlock the recruiter strategy with membership');
            this.gateActionButton('[data-action="download-cv-word"]', 'Download Word is available with membership');
            this.gateActionButton('[data-action="download-cover-word"]', 'Download Word is available with membership');
            this.gateActionButton('[data-action="export"]', 'Export PDF is available with membership');
            this.gateActionButton('[data-action="copy-cover"]', 'Unlock the full cover letter toolkit');
        }

        clearPreviewModeArtifacts() {
            this.$container.removeClass('inst-preview-mode');
            this.$container.find('.inst-preview-obscured').removeClass('inst-preview-obscured');
            this.$container.find('.inst-preview-gate-host').removeClass('inst-preview-gate-host');
            this.$container.find('.inst-preview-button-locked')
                .removeClass('inst-preview-button-locked')
                .prop('disabled', false)
                .removeAttr('aria-disabled data-preview-disabled');
            this.$container.find('.inst-preview-upsell').remove();
        }

        applyPreviewCollection($elements, visibleCount, message, options = {}) {
            if (!$elements.length || $elements.length <= visibleCount) {
                return;
            }

            const settings = {
                promptTarget: null,
                inline: false,
                ...options,
            };

            $elements.each((index, element) => {
                if (index >= visibleCount) {
                    $(element).addClass('inst-preview-obscured');
                }
            });

            const $hiddenElements = $elements.slice(visibleCount);
            const $promptTarget = settings.promptTarget && settings.promptTarget.length
                ? settings.promptTarget
                : ($hiddenElements.first().parent().length ? $hiddenElements.first().parent() : $elements.parent());

            this.insertPreviewPrompt($promptTarget, message, settings.inline);
        }

        applyBlurOnly($elements, message, $promptTarget = null) {
            if (!$elements.length) {
                return;
            }

            $elements.addClass('inst-preview-obscured');
            this.insertPreviewPrompt($promptTarget && $promptTarget.length ? $promptTarget : $elements.last(), message, false);
        }

        gateActionButton(selector, message) {
            if (this.hasPremiumAccess) {
                return;
            }

            this.$container.find(selector).each((index, button) => {
                const $button = $(button);

                $button
                    .addClass('inst-preview-button-locked')
                    .prop('disabled', true)
                    .attr('aria-disabled', 'true')
                    .attr('data-preview-disabled', 'true');

                if (index === 0) {
                    this.insertPreviewPrompt($button, message, true);
                }
            });
        }

        insertPreviewPrompt($target, message, inline = false) {
            if (!$target || !$target.length) {
                return;
            }

            const promptHtml = this.getPreviewPromptHtml(message, inline);

            if (inline) {
                if (
                    $target.hasClass('inst-cv-skills-list') ||
                    $target.hasClass('inst-keyword-list') ||
                    $target.hasClass('inst-cover-letter-preview') ||
                    $target.hasClass('inst-exp-priority-list') ||
                    $target.attr('data-list') === 'interview-questions'
                ) {
                    $target.append(promptHtml);
                    return;
                }

                $target.after(promptHtml);
                return;
            }

            $target.addClass('inst-preview-gate-host');
            if (!$target.css('position') || $target.css('position') === 'static') {
                $target.css('position', 'relative');
            }
            if (
                $target.hasClass('inst-cv-skills-list') ||
                $target.hasClass('inst-keyword-list') ||
                $target.hasClass('inst-cover-letter-preview') ||
                $target.hasClass('inst-exp-priority-list') ||
                $target.attr('data-list') === 'interview-questions' ||
                $target.attr('data-list') === 'missing' ||
                $target.attr('data-list') === 'matched' ||
                $target.attr('data-list') === 'recommendations'
            ) {
                $target.append(promptHtml);
                return;
            }

            $target.append(promptHtml);
        }

        getPreviewPromptHtml(message, inline = false) {
            return `
                <div class="inst-preview-upsell${inline ? ' inst-preview-upsell--inline' : ''}">
                    <span class="inst-preview-upsell__lock" aria-hidden="true">
                        <svg viewBox="0 0 20 20" fill="none">
                            <path d="M6.6 8V6.7A3.4 3.4 0 0 1 10 3.3a3.4 3.4 0 0 1 3.4 3.4V8m-8 0h9.2a1 1 0 0 1 1 1v6.2a1 1 0 0 1-1 1H5.4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <div class="inst-preview-upsell__copy">
                        <strong class="inst-preview-upsell__title">${inline ? 'Unlock more' : 'Subscribe to view'}</strong>
                        <span class="inst-preview-upsell__label">${this.escapeHtml(message)}</span>
                    </div>
                    <a class="inst-preview-upsell__link" href="${this.membershipUrl}">Upgrade your account</a>
                </div>
            `;
        }

        renderCVPreview(data) {
            const scores = data.scores || {};
            const summary = data.executive_summary || {};
            const improvements = data.cv_improvements || [];
            const keywords = data.keyword_analysis || {};
            const strengths = data.strengths_to_highlight || [];
            const skills = data.skills_breakdown || {};

            const beforeScore = parseInt(scores.overall || summary.match_score || 0);
            const afterScore = Math.min(beforeScore + 15 + Math.floor(improvements.length * 2), 95);

            // Update scores
            this.$container.find('[data-cv-score="before"]').text(beforeScore + '%');
            this.$container.find('[data-cv-score="after"]').text(afterScore + '%');

            // Store for Word export
            this.optimizedCVData = {
                beforeScore,
                afterScore,
                summary,
                improvements,
                keywords,
                strengths,
                skills,
                originalCV: this.cvText,
                jdText: this.jdText
            };

            // Build comprehensive optimized CV preview
            this.renderFullCVPreview(data);
        }

        renderFullCVPreview(data) {
            const $container = this.$container.find('[data-cv-full-preview]');
            const summary = data.executive_summary || {};
            const improvements = data.cv_improvements || [];
            const keywords = data.keyword_analysis || {};
            const strengths = data.strengths_to_highlight || [];
            const skills = data.skills_breakdown || {};
            const missingKeywords = keywords.critical_missing || [];
            const matchedKeywords = keywords.well_represented || [];

            // Parse original CV to extract sections
            const cvSections = this.parseCVSections(this.cvText);

            let html = '<div class="inst-cv-document">';

            // PERSONAL SUMMARY / PROFILE SECTION
            html += `
                <div class="inst-cv-section inst-cv-section--summary">
                    <div class="inst-cv-section-header">
                        <h3 class="inst-cv-section-title">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                            Professional Summary
                        </h3>
                        <span class="inst-cv-section-badge inst-cv-section-badge--new">Enhanced</span>
                    </div>
                    <div class="inst-cv-section-content">
                        ${this.generatePersonalSummary(data, cvSections)}
                    </div>
                </div>
            `;

            // RELEVANT WORK EXPERIENCE SECTION
            html += `
                <div class="inst-cv-section inst-cv-section--experience">
                    <div class="inst-cv-section-header">
                        <h3 class="inst-cv-section-title">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/>
                                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                            </svg>
                            Relevant Work Experience
                        </h3>
                        <span class="inst-cv-section-badge">${improvements.filter(i => i.section?.toLowerCase().includes('experience')).length} improvements</span>
                    </div>
                    <div class="inst-cv-section-content">
                        ${this.generateExperienceSection(data, cvSections)}
                    </div>
                </div>
            `;

            // KEY SKILLS SECTION
            html += `
                <div class="inst-cv-section inst-cv-section--skills">
                    <div class="inst-cv-section-header">
                        <h3 class="inst-cv-section-title">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                            </svg>
                            Key Skills & Competencies
                        </h3>
                        <span class="inst-cv-section-badge inst-cv-section-badge--add">+${missingKeywords.length} to add</span>
                    </div>
                    <div class="inst-cv-section-content">
                        ${this.generateSkillsSection(data, cvSections)}
                    </div>
                </div>
            `;

            // EDUCATION & CERTIFICATIONS
            html += `
                <div class="inst-cv-section inst-cv-section--education">
                    <div class="inst-cv-section-header">
                        <h3 class="inst-cv-section-title">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 10v6M2 10l10-5 10 5-10 5z"/>
                                <path d="M6 12v5c3 3 9 3 12 0v-5"/>
                            </svg>
                            Education & Certifications
                        </h3>
                    </div>
                    <div class="inst-cv-section-content">
                        ${this.generateEducationSection(data, cvSections)}
                    </div>
                </div>
            `;

            // ACHIEVEMENTS SECTION (if strengths available)
            if (strengths.length > 0) {
                html += `
                    <div class="inst-cv-section inst-cv-section--achievements">
                        <div class="inst-cv-section-header">
                            <h3 class="inst-cv-section-title">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="8" r="7"/>
                                    <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/>
                                </svg>
                                Key Achievements to Highlight
                            </h3>
                        </div>
                        <div class="inst-cv-section-content">
                            ${this.generateAchievementsSection(strengths)}
                        </div>
                    </div>
                `;
            }

            html += '</div>';
            $container.html(html);
        }

        parseCVSections(cvText) {
            const lines = cvText.split('\n');
            const sections = {
                header: [],
                summary: [],
                experience: [],
                education: [],
                skills: [],
                certifications: [],
                other: []
            };

            let currentSection = 'header';

            lines.forEach(line => {
                const trimmedLine = line.trim();
                const lowerLine = trimmedLine.toLowerCase();

                // Detect section headers
                if (/^(professional\s*)?(summary|profile|objective|about)/i.test(trimmedLine)) {
                    currentSection = 'summary';
                } else if (/^(work\s*)?(experience|employment|history|career)/i.test(trimmedLine)) {
                    currentSection = 'experience';
                } else if (/^education|qualifications|academic/i.test(trimmedLine)) {
                    currentSection = 'education';
                } else if (/^(key\s*)?(skills|competencies|expertise|technical)/i.test(trimmedLine)) {
                    currentSection = 'skills';
                } else if (/^certifications?|licenses?|training/i.test(trimmedLine)) {
                    currentSection = 'certifications';
                } else if (trimmedLine) {
                    sections[currentSection].push(trimmedLine);
                }
            });

            return sections;
        }

        generatePersonalSummary(data, cvSections) {
            const summary = data.executive_summary || {};
            const strengths = data.strengths_to_highlight || [];
            const keywords = data.keyword_analysis?.critical_missing?.slice(0, 3) || [];

            // Get original summary if exists
            const originalSummary = cvSections.summary.join(' ').substring(0, 200);

            let html = '<div class="inst-cv-summary-block">';

            // Generate enhanced summary
            let summaryText = '';

            if (strengths.length > 0) {
                const topStrengths = strengths.slice(0, 3).map(s => s.strength).join(', ');
                summaryText = `Results-driven professional with proven expertise in ${topStrengths}. `;
            } else if (originalSummary) {
                summaryText = originalSummary + ' ';
            } else {
                summaryText = 'Highly motivated professional seeking to leverage skills and experience in a challenging role. ';
            }

            // Add keywords naturally
            if (keywords.length > 0) {
                summaryText += `Demonstrated proficiency in <span class="inst-cv-highlight inst-cv-highlight--add">${keywords.slice(0, 2).join('</span> and <span class="inst-cv-highlight inst-cv-highlight--add">')}</span>. `;
            }

            summaryText += 'Committed to delivering excellence and driving organizational success.';

            html += `<p class="inst-cv-summary-text">${summaryText}</p>`;

            if (keywords.length > 0) {
                html += `
                    <div class="inst-cv-summary-tip">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="16" x2="12" y2="12"/>
                            <line x1="12" y1="8" x2="12.01" y2="8"/>
                        </svg>
                        <span>Keywords added: <strong>${keywords.join(', ')}</strong></span>
                    </div>
                `;
            }

            html += '</div>';
            return html;
        }

        generateExperienceSection(data, cvSections) {
            const expAnalysis = data.experience_analysis || {};
            const expImprovements = data.experience_improvements || {};
            const relevantRoles = expAnalysis.relevant_roles || [];
            const priorityFixes = expImprovements.priority_fixes || [];
            const actionVerbUpgrades = expImprovements.action_verb_upgrades || [];
            const quantFixes = expImprovements.quantification_fixes || [];
            const keywordIntegration = expImprovements.keyword_integration || [];
            const achievementReframes = expImprovements.achievement_reframes || [];

            let html = '';

            // Experience Summary
            if (expImprovements.summary) {
                html += `
                    <div class="inst-exp-summary">
                        <div class="inst-exp-summary-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="12" y1="16" x2="12" y2="12"/>
                                <line x1="12" y1="8" x2="12.01" y2="8"/>
                            </svg>
                        </div>
                        <p>${this.escapeHtml(expImprovements.summary)}</p>
                    </div>
                `;
            }

            // Relevant Roles from AI Analysis
            if (relevantRoles.length > 0) {
                relevantRoles.forEach((role, idx) => {
                    const relevanceClass = role.relevance_score >= 70 ? 'high' : (role.relevance_score >= 50 ? 'medium' : 'low');
                    const bulletImprovements = role.bullet_improvements || [];

                    html += `
                        <div class="inst-cv-experience-item inst-cv-experience-item--analyzed">
                            <div class="inst-cv-experience-header">
                                <div class="inst-cv-experience-header-left">
                                    <h4 class="inst-cv-experience-title">${this.escapeHtml(role.role)}</h4>
                                    <span class="inst-cv-experience-company">${this.escapeHtml(role.company || '')} ${role.duration ? '• ' + this.escapeHtml(role.duration) : ''}</span>
                                </div>
                                <div class="inst-cv-experience-score inst-cv-experience-score--${relevanceClass}">
                                    <span class="inst-cv-experience-score-value">${role.relevance_score || 0}%</span>
                                    <span class="inst-cv-experience-score-label">Relevance</span>
                                </div>
                            </div>
                    `;

                    // Bullet point improvements (before/after)
                    if (bulletImprovements.length > 0) {
                        html += `<div class="inst-exp-bullet-improvements">`;
                        bulletImprovements.slice(0, 4).forEach((bullet, bIdx) => {
                            html += `
                                <div class="inst-exp-bullet-comparison">
                                    <div class="inst-exp-bullet-before">
                                        <span class="inst-exp-bullet-label">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                            Before
                                        </span>
                                        <p>${this.escapeHtml(bullet.original || '')}</p>
                                    </div>
                                    <div class="inst-exp-bullet-after">
                                        <span class="inst-exp-bullet-label">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                            After
                                        </span>
                                        <p>${this.escapeHtml(bullet.improved || '')}</p>
                                        ${bullet.keywords_added && bullet.keywords_added.length > 0 ? `
                                            <div class="inst-exp-keywords-added">
                                                <span>Keywords added:</span>
                                                ${bullet.keywords_added.map(kw => `<span class="inst-exp-keyword-tag">${this.escapeHtml(kw)}</span>`).join('')}
                                            </div>
                                        ` : ''}
                                    </div>
                                    ${bullet.reason ? `<div class="inst-exp-bullet-reason"><strong>Why:</strong> ${this.escapeHtml(bullet.reason)}</div>` : ''}
                                </div>
                            `;
                        });
                        html += `</div>`;
                    }

                    // Missing achievements for this role
                    if (role.missing_achievements && role.missing_achievements.length > 0) {
                        html += `
                            <div class="inst-exp-missing-achievements">
                                <h5>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                    Add These Achievements
                                </h5>
                                <ul>
                                    ${role.missing_achievements.slice(0, 3).map(a => `<li>${this.escapeHtml(a)}</li>`).join('')}
                                </ul>
                            </div>
                        `;
                    }

                    // Quantification opportunities
                    if (role.quantification_opportunities && role.quantification_opportunities.length > 0) {
                        html += `
                            <div class="inst-exp-quantify">
                                <h5>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20V10"/><path d="M18 20V4"/><path d="M6 20v-4"/></svg>
                                    Add Numbers & Metrics
                                </h5>
                                <ul>
                                    ${role.quantification_opportunities.slice(0, 3).map(q => `<li>${this.escapeHtml(q)}</li>`).join('')}
                                </ul>
                            </div>
                        `;
                    }

                    html += `</div>`;
                });
            }

            // Action Verb Upgrades Section
            if (actionVerbUpgrades.length > 0) {
                html += `
                    <div class="inst-exp-section inst-exp-section--verbs">
                        <h5 class="inst-exp-section-title">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 19l7-7 3 3-7 7-3-3z"/>
                                <path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"/>
                                <path d="M2 2l7.586 7.586"/>
                            </svg>
                            Upgrade Your Action Verbs
                        </h5>
                        <div class="inst-exp-verb-list">
                            ${actionVerbUpgrades.slice(0, 5).map(v => `
                                <div class="inst-exp-verb-item">
                                    <div class="inst-exp-verb-change">
                                        <span class="inst-exp-verb-weak">${this.escapeHtml(v.weak_verb)}</span>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                                        <span class="inst-exp-verb-strong">${this.escapeHtml(v.strong_verb)}</span>
                                    </div>
                                    ${v.example_rewrite ? `<p class="inst-exp-verb-example">"${this.escapeHtml(v.example_rewrite)}"</p>` : ''}
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
            }

            // Achievement Reframes Section
            if (achievementReframes.length > 0) {
                html += `
                    <div class="inst-exp-section inst-exp-section--achievements">
                        <h5 class="inst-exp-section-title">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="8" r="7"/>
                                <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/>
                            </svg>
                            Turn Duties Into Achievements
                        </h5>
                        <div class="inst-exp-reframe-list">
                            ${achievementReframes.slice(0, 4).map(r => `
                                <div class="inst-exp-reframe-item">
                                    <div class="inst-exp-reframe-before">
                                        <span class="inst-exp-reframe-label">Duty (weak):</span>
                                        <p>${this.escapeHtml(r.current_duty)}</p>
                                    </div>
                                    <div class="inst-exp-reframe-after">
                                        <span class="inst-exp-reframe-label">Achievement (strong):</span>
                                        <p>${this.escapeHtml(r.achievement_version)}</p>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
            }

            // Quantifiable Outcomes Section
            if (quantFixes.length > 0) {
                html += `
                    <div class="inst-exp-section inst-exp-section--quant">
                        <h5 class="inst-exp-section-title">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 19h16"/>
                                <path d="M7 16V8"/>
                                <path d="M12 16V5"/>
                                <path d="M17 16v-3"/>
                            </svg>
                            Quantifiable Outcomes
                        </h5>
                        <div class="inst-exp-quant-list">
                            ${quantFixes.slice(0, 4).map((fix) => `
                                <div class="inst-exp-quant-item">
                                    <div class="inst-exp-quant-current">
                                        <span class="inst-exp-quant-label">Current</span>
                                        <p>${this.escapeHtml(fix.original || '')}</p>
                                    </div>
                                    <div class="inst-exp-quant-improved">
                                        <span class="inst-exp-quant-label">Improve</span>
                                        <p>${this.escapeHtml(fix.improved || '')}</p>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
            }

            // Keyword Integration Section
            if (keywordIntegration.length > 0) {
                html += `
                    <div class="inst-exp-section inst-exp-section--keywords">
                        <h5 class="inst-exp-section-title">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"/>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                            </svg>
                            Missing Keywords to Add
                        </h5>
                        <div class="inst-exp-keyword-integration">
                            ${keywordIntegration.slice(0, 5).map(k => `
                                <div class="inst-exp-keyword-item">
                                    <span class="inst-exp-keyword-name">${this.escapeHtml(k.missing_keyword)}</span>
                                    <span class="inst-exp-keyword-where">Add to: ${this.escapeHtml(k.suggested_placement || 'Experience section')}</span>
                                    ${k.integration_example ? `<p class="inst-exp-keyword-example">"${this.escapeHtml(k.integration_example)}"</p>` : ''}
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
            }

            // Priority Fixes
            if (priorityFixes.length > 0) {
                html += `
                    <div class="inst-exp-section inst-exp-section--priority">
                        <h5 class="inst-exp-section-title">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                <line x1="12" y1="9" x2="12" y2="13"/>
                                <line x1="12" y1="17" x2="12.01" y2="17"/>
                            </svg>
                            Priority Fixes
                        </h5>
                        <div class="inst-exp-priority-list">
                            ${priorityFixes.slice(0, 4).map((fix, idx) => `
                                <div class="inst-exp-priority-item inst-exp-priority-item--${fix.impact || 'medium'}">
                                    <span class="inst-exp-priority-num">${idx + 1}</span>
                                    <div class="inst-exp-priority-content">
                                        <strong>${this.escapeHtml(fix.issue)}</strong>
                                        ${fix.current_text ? `<p class="inst-exp-priority-current"><em>Current:</em> "${this.escapeHtml(fix.current_text)}"</p>` : ''}
                                        ${fix.improved_text ? `<p class="inst-exp-priority-improved"><em>Improved:</em> "${this.escapeHtml(fix.improved_text)}"</p>` : ''}
                                        ${fix.jd_alignment ? `<span class="inst-exp-priority-align">Aligns with: ${this.escapeHtml(fix.jd_alignment)}</span>` : ''}
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
            }

            // Fallback if no AI analysis data
            if (!relevantRoles.length && !priorityFixes.length) {
                // Parse experience from CV text as fallback
                const expLines = cvSections.experience;
                let currentJob = null;
                const jobs = [];

                expLines.forEach(line => {
                    if (/\d{4}|present|current/i.test(line) || /^[A-Z\s]+$/.test(line.substring(0, 30))) {
                        if (currentJob) jobs.push(currentJob);
                        currentJob = { title: line, bullets: [] };
                    } else if (currentJob) {
                        currentJob.bullets.push(line.replace(/^[•\-\*]\s*/, ''));
                    }
                });
                if (currentJob) jobs.push(currentJob);

                if (jobs.length === 0) {
                    html += `<div class="inst-exp-empty"><p>Paste your CV to see detailed experience improvements</p></div>`;
                } else {
                    jobs.slice(0, 3).forEach(job => {
                        html += `
                            <div class="inst-cv-experience-item">
                                <h4 class="inst-cv-experience-title">${this.escapeHtml(job.title)}</h4>
                                <ul class="inst-cv-experience-bullets">
                                    ${job.bullets.slice(0, 4).map(b => `<li class="inst-cv-bullet">${this.escapeHtml(b)}</li>`).join('')}
                                </ul>
                            </div>
                        `;
                    });
                }
            }

            return html;
        }

        generateSkillsSection(data, cvSections) {
            const skills = data.skills_breakdown || {};
            const keywords = data.keyword_analysis || {};
            const matched = skills.matched_skills || [];
            const missing = skills.missing_skills || [];
            const matchedKw = keywords.well_represented || [];
            const missingKw = keywords.critical_missing || [];

            let html = '<div class="inst-cv-skills-grid">';

            // Matched skills
            if (matchedKw.length > 0 || matched.length > 0) {
                html += `
                    <div class="inst-cv-skills-group">
                        <h5 class="inst-cv-skills-group-title">
                            <svg viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                <polyline points="22 4 12 14.01 9 11.01"/>
                            </svg>
                            Current Skills (Keep)
                        </h5>
                        <div class="inst-cv-skills-list">
                `;

                const allMatched = [...new Set([...matchedKw, ...matched.map(s => s.skill)])].slice(0, 10);
                allMatched.forEach(skill => {
                    html += `<span class="inst-cv-skill inst-cv-skill--matched">${this.escapeHtml(skill)}</span>`;
                });

                html += '</div></div>';
            }

            // Missing skills - to add
            if (missingKw.length > 0 || missing.length > 0) {
                html += `
                    <div class="inst-cv-skills-group inst-cv-skills-group--add">
                        <h5 class="inst-cv-skills-group-title">
                            <svg viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2">
                                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                            Add These Skills
                        </h5>
                        <div class="inst-cv-skills-list">
                `;

                const allMissing = [...new Set([...missingKw, ...missing.map(s => s.skill)])].slice(0, 10);
                allMissing.forEach(skill => {
                    html += `<span class="inst-cv-skill inst-cv-skill--add">${this.escapeHtml(skill)}</span>`;
                });

                html += '</div></div>';
            }

            html += '</div>';
            return html;
        }

        generateEducationSection(data, cvSections) {
            const eduLines = cvSections.education;
            const certLines = cvSections.certifications;

            let html = '<div class="inst-cv-education-list">';

            if (eduLines.length > 0) {
                eduLines.slice(0, 4).forEach(line => {
                    html += `<div class="inst-cv-education-item">${this.escapeHtml(line)}</div>`;
                });
            } else {
                html += `
                    <div class="inst-cv-education-item inst-cv-education-item--placeholder">
                        <em>Add your educational qualifications here</em>
                    </div>
                `;
            }

            if (certLines.length > 0) {
                html += '<h5 class="inst-cv-education-subtitle">Certifications</h5>';
                certLines.slice(0, 3).forEach(line => {
                    html += `<div class="inst-cv-education-item">${this.escapeHtml(line)}</div>`;
                });
            }

            html += '</div>';
            return html;
        }

        generateAchievementsSection(strengths) {
            let html = '<ul class="inst-cv-achievements-list">';

            strengths.slice(0, 4).forEach(s => {
                html += `
                    <li class="inst-cv-achievement">
                        <span class="inst-cv-achievement-title">${this.escapeHtml(s.strength)}</span>
                        <span class="inst-cv-achievement-desc">${this.escapeHtml(s.how_to_leverage || s.relevance || '')}</span>
                    </li>
                `;
            });

            html += '</ul>';
            return html;
        }

        renderCVImprovements(data) {
            const $container = this.$container.find('[data-list="cv-improvements"]');
            const improvements = data.cv_improvements || [];

            if (improvements.length === 0) {
                $container.html('<div class="inst-toolkit-empty"><p>No specific improvements identified</p></div>');
                return;
            }

            let html = '';
            improvements.forEach((imp, idx) => {
                const iconClass = imp.section?.toLowerCase().includes('skill') ? 'add' :
                                 imp.section?.toLowerCase().includes('experience') ? 'change' : 'section';
                html += `
                    <div class="inst-improvement-item">
                        <div class="inst-improvement-icon inst-improvement-icon--${iconClass}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                ${iconClass === 'add' ? '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>' :
                                  iconClass === 'change' ? '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>' :
                                  '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>'}
                            </svg>
                        </div>
                        <div class="inst-improvement-content">
                            <div class="inst-improvement-title">${this.escapeHtml(imp.section || 'Improvement')}</div>
                            <div class="inst-improvement-desc">${this.escapeHtml(imp.current || '')}</div>
                            <div class="inst-improvement-suggestion">
                                <strong>Suggested</strong>
                                ${this.escapeHtml(imp.suggested || '')}
                            </div>
                            <div class="inst-improvement-actions">
                                <button class="inst-improvement-btn" data-action="copy-improvement" data-text="${this.escapeHtml(imp.suggested || '')}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                                    </svg>
                                    Copy
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });

            $container.html(html);
        }

        renderCoverLetter(data) {
            const $preview = this.$container.find('.inst-cover-letter-preview');
            const $actions = this.$container.find('.inst-cover-letter-actions');
            const $points = this.$container.find('[data-list="cover-points"]');

            // Extract data from multiple sources for robustness
            const summary = data.executive_summary || {};
            const skills = data.skills_breakdown || {};
            const keywords = data.keyword_analysis || {};
            const improvements = data.cv_improvements || [];

            // Get role info
            const roleTitle = summary.role_title || 'this position';
            const company = summary.company || '';

            // Build strengths from multiple sources
            let strengths = data.strengths_to_highlight || [];
            if (strengths.length === 0) {
                // Fallback: create strengths from matched skills
                const matchedSkills = skills.matched_skills || [];
                strengths = matchedSkills.slice(0, 3).map(s => ({
                    strength: typeof s === 'string' ? s : (s.skill || s.name || 'Relevant skill'),
                    how_to_leverage: typeof s === 'string' ? 'Directly applicable to role requirements' : (s.relevance || s.context || 'Matches job requirements')
                }));
            }

            // Build talking points from multiple sources
            let talkingPoints = data.cover_letter_points || [];
            if (talkingPoints.length === 0 && improvements.length > 0) {
                // Fallback: create points from improvements
                talkingPoints = improvements.slice(0, 2).map(imp =>
                    `I have experience in ${imp.section || 'this area'} and am committed to continuous improvement in my approach.`
                );
            }

            // Get keywords
            const matchedKeywords = keywords.well_represented || [];
            const missingKeywords = keywords.critical_missing || [];

            // Store cover letter data for Word export
            this.coverLetterData = {
                strengths,
                points: talkingPoints,
                matchedKeywords,
                missingKeywords,
                roleTitle,
                company,
                summary
            };

            // Build cover letter
            const date = new Date().toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });

            let letterHtml = `<div class="inst-cover-letter-content">`;
            letterHtml += `<p class="inst-cl-date">${date}</p>`;
            letterHtml += `<p class="inst-cl-salutation">Dear Hiring Manager,</p>`;

            // Opening paragraph
            let opening = `I am writing to express my strong interest in `;
            if (company) {
                opening += `the <span class="inst-cl-highlight inst-cl-highlight--role">${this.escapeHtml(roleTitle)}</span> position at <span class="inst-cl-highlight inst-cl-highlight--company">${this.escapeHtml(company)}</span>`;
            } else {
                opening += `the <span class="inst-cl-highlight inst-cl-highlight--role">${this.escapeHtml(roleTitle)}</span> position`;
            }
            opening += `. After reviewing the role requirements, I am confident that my background and skills make me an excellent candidate.`;
            letterHtml += `<p class="inst-cl-paragraph">${opening}</p>`;

            // Key strengths section
            if (strengths.length > 0) {
                letterHtml += `<p class="inst-cl-paragraph">Key strengths I would bring to this role include:</p>`;
                letterHtml += `<ul class="inst-cl-list">`;
                strengths.slice(0, 3).forEach(s => {
                    const strengthName = typeof s === 'string' ? s : (s.strength || s.skill || 'Relevant experience');
                    const strengthDetail = typeof s === 'string' ? '' : (s.how_to_leverage || s.relevance || '');
                    let strengthText = `<span class="inst-cl-highlight inst-cl-highlight--strength">${this.escapeHtml(strengthName)}</span>`;
                    if (strengthDetail) {
                        strengthText += `: ${this.escapeHtml(strengthDetail)}`;
                    }
                    letterHtml += `<li>${strengthText}</li>`;
                });
                letterHtml += `</ul>`;
            }

            // Talking points
            if (talkingPoints.length > 0) {
                talkingPoints.slice(0, 2).forEach(point => {
                    const pointStr = typeof point === 'string' ? point : (point.point || point.text || '');
                    if (pointStr) {
                        let pointText = this.escapeHtml(pointStr);
                        // Highlight matched keywords
                        matchedKeywords.slice(0, 5).forEach(keyword => {
                            if (keyword) {
                                const regex = new RegExp(`\\b(${keyword.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})\\b`, 'gi');
                                pointText = pointText.replace(regex, '<span class="inst-cl-highlight inst-cl-highlight--keyword">$1</span>');
                            }
                        });
                        letterHtml += `<p class="inst-cl-paragraph">${pointText}</p>`;
                    }
                });
            }

            // Missing keywords paragraph
            if (missingKeywords.length > 0) {
                letterHtml += `<p class="inst-cl-paragraph">My experience also includes proficiency in `;
                const keywordsToMention = missingKeywords.slice(0, 3);
                keywordsToMention.forEach((kw, idx) => {
                    letterHtml += `<span class="inst-cl-highlight inst-cl-highlight--add">${this.escapeHtml(kw)}</span>`;
                    if (idx < keywordsToMention.length - 2) {
                        letterHtml += ', ';
                    } else if (idx === keywordsToMention.length - 2) {
                        letterHtml += ', and ';
                    }
                });
                letterHtml += `, which I believe would contribute to the team's success.</p>`;
            }

            // Closing
            letterHtml += `<p class="inst-cl-paragraph">I am excited about the opportunity to contribute to your team and would welcome the chance to discuss how my experience aligns with your needs.</p>`;
            letterHtml += `<p class="inst-cl-closing">Sincerely,<br><span class="inst-cl-placeholder">${this.escapeHtml(this.getCandidateFirstName())}</span></p>`;
            letterHtml += `</div>`;

            // Legend
            letterHtml += `
                <div class="inst-cl-legend">
                    <span class="inst-cl-legend-title">Highlights:</span>
                    <span class="inst-cl-legend-item"><span class="inst-cl-highlight inst-cl-highlight--strength">Strengths</span></span>
                    <span class="inst-cl-legend-item"><span class="inst-cl-highlight inst-cl-highlight--keyword">Keywords</span></span>
                    <span class="inst-cl-legend-item"><span class="inst-cl-highlight inst-cl-highlight--add">Add These</span></span>
                </div>
            `;

            // Store plain text for copy
            this.generatedCoverLetter = this.getCoverLetterPlainText(letterHtml);

            $preview.html(letterHtml);
            $actions.show();

            // Render key points sidebar
            this.renderCoverPoints($points, strengths, talkingPoints, missingKeywords);
        }

        /**
         * Render cover letter key points
         */
        renderCoverPoints($container, strengths, talkingPoints, missingKeywords) {
            let html = '';
            let idx = 1;

            // Strengths
            strengths.slice(0, 3).forEach(s => {
                const name = typeof s === 'string' ? s : (s.strength || s.skill || 'Strength');
                const detail = typeof s === 'string' ? '' : (s.how_to_leverage || s.relevance || '');
                html += `
                    <div class="inst-cover-point inst-cover-point--strength">
                        <span class="inst-cover-point-num">${idx++}</span>
                        <div class="inst-cover-point-content">
                            <span class="inst-cover-point-tag">Strength</span>
                            <span class="inst-cover-point-text"><strong>${this.escapeHtml(name)}</strong></span>
                            ${detail ? `<span class="inst-cover-point-detail">${this.escapeHtml(detail)}</span>` : ''}
                        </div>
                    </div>
                `;
            });

            // Talking points
            talkingPoints.slice(0, 3).forEach(point => {
                const text = typeof point === 'string' ? point : (point.point || point.text || '');
                if (text) {
                    html += `
                        <div class="inst-cover-point inst-cover-point--talking">
                            <span class="inst-cover-point-num">${idx++}</span>
                            <div class="inst-cover-point-content">
                                <span class="inst-cover-point-tag">Talking Point</span>
                                <span class="inst-cover-point-text">${this.escapeHtml(text.substring(0, 150))}${text.length > 150 ? '...' : ''}</span>
                            </div>
                        </div>
                    `;
                }
            });

            // Keywords to include
            if (missingKeywords.length > 0) {
                html += `
                    <div class="inst-cover-point inst-cover-point--keywords">
                        <span class="inst-cover-point-num">!</span>
                        <div class="inst-cover-point-content">
                            <span class="inst-cover-point-tag">Keywords to Include</span>
                            <div class="inst-cover-point-keywords">
                                ${missingKeywords.slice(0, 5).map(kw => `<span class="inst-cover-point-keyword">${this.escapeHtml(kw)}</span>`).join('')}
                            </div>
                        </div>
                    </div>
                `;
            }

            if (html === '') {
                html = '<div class="inst-toolkit-empty"><p>Key points will appear after analysis</p></div>';
            }

            $container.html(html);
        }

        /**
         * Get plain text version of cover letter
         */
        getCoverLetterPlainText(html) {
            const div = document.createElement('div');
            div.innerHTML = html;
            div.querySelectorAll('br').forEach(br => br.replaceWith('\n'));
            div.querySelectorAll('p').forEach(p => {
                p.prepend(document.createTextNode('\n'));
                p.append(document.createTextNode('\n'));
            });
            div.querySelectorAll('li').forEach(li => {
                li.prepend(document.createTextNode('• '));
                li.append(document.createTextNode('\n'));
            });
            // Remove legend
            div.querySelectorAll('.inst-cl-legend').forEach(el => el.remove());
            return (div.textContent || div.innerText || '').trim();
        }

        renderKeywords(data) {
            const keywords = data.keyword_analysis || {};
            const skills = data.skills_breakdown || {};

            // Get keywords from multiple sources
            let matched = keywords.well_represented || [];
            let missing = keywords.critical_missing || [];

            // Fallback: get from skills if keywords empty
            if (matched.length === 0) {
                const matchedSkills = skills.matched_skills || [];
                matched = matchedSkills.slice(0, 10).map(s =>
                    typeof s === 'string' ? s : (s.skill || s.name || '')
                ).filter(s => s);
            }

            if (missing.length === 0) {
                const missingSkills = skills.missing_skills || [];
                missing = missingSkills.slice(0, 10).map(s =>
                    typeof s === 'string' ? s : (s.skill || s.name || '')
                ).filter(s => s);
            }

            // Calculate match rate
            const total = matched.length + missing.length;
            const matchRate = total > 0 ? Math.round((matched.length / total) * 100) : (keywords.match_percentage || 0);

            // Update stats
            this.$container.find('[data-stat="matched"]').text(matched.length);
            this.$container.find('[data-stat="missing"]').text(missing.length);
            this.$container.find('[data-stat="match-rate"]').text(matchRate + '%');

            // Render missing keywords
            const $missing = this.$container.find('[data-list="keywords-missing"]');
            if (missing.length > 0) {
                let html = '';
                missing.forEach(kw => {
                    if (kw) {
                        html += `
                            <span class="inst-keyword-tag inst-keyword-tag--missing" data-keyword="${this.escapeHtml(kw)}">
                                ${this.escapeHtml(kw)}
                                <svg class="inst-keyword-tag-copy" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                                </svg>
                            </span>
                        `;
                    }
                });
                $missing.html(html || '<div class="inst-toolkit-empty"><p>No missing keywords identified</p></div>');
            } else {
                $missing.html('<div class="inst-toolkit-empty"><p>No missing keywords identified</p></div>');
            }

            // Render matched keywords
            const $matched = this.$container.find('[data-list="keywords-matched"]');
            if (matched.length > 0) {
                let html = '';
                matched.forEach(kw => {
                    if (kw) {
                        html += `
                            <span class="inst-keyword-tag inst-keyword-tag--matched" data-keyword="${this.escapeHtml(kw)}">
                                ${this.escapeHtml(kw)}
                                <svg class="inst-keyword-tag-copy" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                                </svg>
                            </span>
                        `;
                    }
                });
                $matched.html(html || '<div class="inst-toolkit-empty"><p>No matched keywords found</p></div>');
            } else {
                $matched.html('<div class="inst-toolkit-empty"><p>No matched keywords found</p></div>');
            }
        }

        renderInterviewPrep(data) {
            const $container = this.$container.find('[data-list="interview-questions"]');
            let questions = data.interview_prep || [];

            // Generate fallback questions if too few
            if (questions.length < 3) {
                const summary = data.executive_summary || {};
                const skills = data.skills_breakdown || {};
                const missingSkills = skills.missing_skills || [];
                const roleTitle = summary.role_title || 'this role';

                // Default behavioral questions based on analysis
                const fallbackQuestions = [
                    {
                        category: 'behavioral',
                        likely_question: 'Tell me about yourself and why you\'re interested in this role.',
                        why_theyll_ask: 'Standard opening to assess your communication and motivation',
                        suggested_response_angle: 'Structure with: current role, key achievements, why this opportunity',
                        example_answer: `I'm currently a professional with experience in relevant areas. I've achieved [key result] and I'm excited about ${roleTitle} because it aligns with my career goals and strengths.`
                    },
                    {
                        category: 'behavioral',
                        likely_question: 'Walk me through a challenging project you\'ve worked on.',
                        why_theyll_ask: 'Assesses problem-solving and how you handle pressure',
                        suggested_response_angle: 'Use STAR method: Situation, Task, Action, Result with quantified outcomes'
                    },
                    {
                        category: 'situational',
                        likely_question: 'How would you approach learning the new skills needed for this role?',
                        why_theyll_ask: 'Tests self-awareness and growth mindset',
                        suggested_response_angle: 'Acknowledge gaps honestly, show concrete learning plan'
                    },
                    {
                        category: 'technical',
                        likely_question: 'What relevant technical skills or tools do you bring to this role?',
                        why_theyll_ask: 'Verifies your practical capabilities',
                        suggested_response_angle: 'Focus on transferable skills and willingness to learn'
                    },
                    {
                        category: 'weakness_probe',
                        likely_question: 'What areas are you looking to develop professionally?',
                        why_theyll_ask: 'Tests self-awareness and honesty',
                        suggested_response_angle: 'Pick a genuine but non-critical skill, show you\'re actively working on it'
                    }
                ];

                // Add fallback questions that don't duplicate existing ones
                const existingQTexts = questions.map(q => (q.likely_question || q.question || '').toLowerCase());
                fallbackQuestions.forEach(fq => {
                    if (questions.length < 5 && !existingQTexts.includes(fq.likely_question.toLowerCase())) {
                        questions.push(fq);
                    }
                });
            }

            if (questions.length === 0) {
                $container.html('<div class="inst-toolkit-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><p>Interview questions will appear after analysis</p></div>');
                return;
            }

            // Category icons and colors
            const categoryConfig = {
                behavioral: { icon: '🎯', label: 'Behavioral', color: '#2563eb' },
                technical: { icon: '⚙️', label: 'Technical', color: '#7c3aed' },
                situational: { icon: '🧩', label: 'Situational', color: '#0891b2' },
                culture_fit: { icon: '🤝', label: 'Culture Fit', color: '#059669' },
                strength_based: { icon: '💪', label: 'Strength', color: '#16a34a' },
                weakness_probe: { icon: '⚠️', label: 'Weakness', color: '#dc2626' }
            };

            let html = '';
            questions.forEach((q, idx) => {
                const category = q.category || 'behavioral';
                const config = categoryConfig[category] || categoryConfig.behavioral;
                const hasExample = q.example_answer && q.example_answer.length > 10;
                const hasPitfalls = q.pitfalls_to_avoid && q.pitfalls_to_avoid.length > 5;
                const hasFollowUps = q.follow_up_questions && q.follow_up_questions.length > 0;

                html += `
                    <div class="inst-interview-item" data-category="${category}">
                        <div class="inst-interview-question">
                            <span class="inst-interview-q-num">${idx + 1}</span>
                            <div class="inst-interview-q-content">
                                <span class="inst-interview-category" style="background: ${config.color}20; color: ${config.color}">
                                    ${config.icon} ${config.label}
                                </span>
                                <span class="inst-interview-q-text">${this.escapeHtml(q.likely_question || q.question || '')}</span>
                                ${q.why_theyll_ask ? `<span class="inst-interview-why">Why they'll ask: ${this.escapeHtml(q.why_theyll_ask)}</span>` : ''}
                            </div>
                            <svg class="inst-interview-toggle" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="6 9 12 15 18 9"/>
                            </svg>
                        </div>
                        <div class="inst-interview-answer">
                            <div class="inst-interview-section">
                                <div class="inst-interview-section-header">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20V10"/><path d="M18 20V4"/><path d="M6 20v-4"/></svg>
                                    Strategy
                                </div>
                                <div class="inst-interview-section-content">${this.escapeHtml(q.suggested_response_angle || 'Focus on relevant experience and concrete examples.')}</div>
                            </div>

                            ${hasExample ? `
                                <div class="inst-interview-section inst-interview-section--example">
                                    <div class="inst-interview-section-header">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                        Example Answer
                                    </div>
                                    <div class="inst-interview-section-content inst-interview-example-text">"${this.escapeHtml(q.example_answer)}"</div>
                                    <button class="inst-interview-copy-btn" data-action="copy-answer" data-text="${this.escapeHtml(q.example_answer)}">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                        Copy
                                    </button>
                                </div>
                            ` : ''}

                            ${hasPitfalls ? `
                                <div class="inst-interview-section inst-interview-section--pitfalls">
                                    <div class="inst-interview-section-header">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                        Avoid These Mistakes
                                    </div>
                                    <div class="inst-interview-section-content inst-interview-pitfalls-text">${this.escapeHtml(q.pitfalls_to_avoid)}</div>
                                </div>
                            ` : ''}

                            ${hasFollowUps ? `
                                <div class="inst-interview-section inst-interview-section--followups">
                                    <div class="inst-interview-section-header">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                                        Likely Follow-ups
                                    </div>
                                    <ul class="inst-interview-followups-list">
                                        ${q.follow_up_questions.slice(0, 3).map(fq => `<li>${this.escapeHtml(fq)}</li>`).join('')}
                                    </ul>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `;
            });

            $container.html(html);

            // Bind copy button events
            $container.find('[data-action="copy-answer"]').on('click', (e) => {
                e.stopPropagation();
                const text = $(e.currentTarget).data('text');
                this.copyToClipboard(text);
                this.showToast('Answer copied!');
            });
        }

        copyCoverLetter() {
            if (!this.hasPremiumAccess) {
                this.promptUpgrade();
                return;
            }

            if (this.generatedCoverLetter) {
                this.copyToClipboard(this.generatedCoverLetter);
                this.showToast('Cover letter copied to clipboard!');
            }
        }

        copyToClipboard(text) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text);
            } else {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
            }
        }

        showToast(message) {
            const $toast = $('<div class="inst-toast">' + message + '</div>');
            $toast.css({
                position: 'fixed',
                bottom: '20px',
                left: '50%',
                transform: 'translateX(-50%)',
                padding: '10px 20px',
                background: '#0f172a',
                color: '#fff',
                borderRadius: '8px',
                fontSize: '13px',
                fontWeight: '500',
                zIndex: 10000,
                opacity: 0,
                transition: 'opacity 0.3s ease'
            });
            $('body').append($toast);
            setTimeout(() => $toast.css('opacity', 1), 10);
            setTimeout(() => {
                $toast.css('opacity', 0);
                setTimeout(() => $toast.remove(), 300);
            }, 2000);
        }

        escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // PDF Text Cleaning Methods
        looksLikePDFText(text) {
            if (!text || text.length < 50) return false;

            // Count PDF artifacts
            let artifactScore = 0;

            // Check for excessive line breaks mid-sentence (PDF columns)
            const shortLines = text.split('\n').filter(line => line.trim().length > 0 && line.trim().length < 60);
            if (shortLines.length > text.split('\n').length * 0.5) artifactScore += 2;

            // Check for words split by line breaks (hyphenation)
            if (/[a-z]-\n[a-z]/i.test(text)) artifactScore += 2;

            // Check for multiple spaces (PDF column gaps)
            if (/\s{3,}/.test(text)) artifactScore += 1;

            // Check for page numbers or headers repeating
            if (/Page \d+|^\d+\s*$/m.test(text)) artifactScore += 1;

            // Check for unusual character sequences (PDF encoding issues)
            if (/[\uf0b7\uf0a7\ufffd]/.test(text)) artifactScore += 2;

            // Check for bullet point characters that PDFs often use
            if (/[•●○■□▪▫]/.test(text)) artifactScore += 1;

            // Lines ending without punctuation followed by lowercase (wrapped sentences)
            const wrappedSentences = (text.match(/[a-zA-Z]\n[a-z]/g) || []).length;
            if (wrappedSentences > 3) artifactScore += 2;

            return artifactScore >= 3;
        }

        cleanPDFText(text) {
            if (!text) return '';

            let cleaned = text;

            // Remove page numbers and headers/footers
            cleaned = cleaned.replace(/^Page\s*\d+.*$/gm, '');
            cleaned = cleaned.replace(/^\d+\s*$/gm, '');

            // Fix hyphenated words split across lines
            cleaned = cleaned.replace(/(\w)-\n(\w)/g, '$1$2');

            // Replace PDF bullet characters with standard bullets
            cleaned = cleaned.replace(/[\uf0b7\uf0a7●○■□▪▫]/g, '•');

            // Replace unusual whitespace and control characters
            cleaned = cleaned.replace(/[\ufffd\u00a0]/g, ' ');

            // Fix line breaks in the middle of sentences
            // Keep line breaks after sentence-ending punctuation
            cleaned = cleaned.replace(/([a-z,;:])\n([a-z])/gi, '$1 $2');

            // Preserve paragraph breaks (double newlines or lines ending with period)
            cleaned = cleaned.replace(/\.\n([A-Z])/g, '.\n\n$1');

            // Collapse multiple spaces into single space
            cleaned = cleaned.replace(/[ \t]+/g, ' ');

            // Collapse multiple newlines into max two
            cleaned = cleaned.replace(/\n{3,}/g, '\n\n');

            // Clean up spaces around newlines
            cleaned = cleaned.replace(/ +\n/g, '\n');
            cleaned = cleaned.replace(/\n +/g, '\n');

            // Trim each line
            cleaned = cleaned.split('\n').map(line => line.trim()).join('\n');

            // Remove empty lines at start and end
            cleaned = cleaned.trim();

            return cleaned;
        }

        renderScores(data) {
            const scores = data.scores || {};
            const summary = data.executive_summary || {};

            // Extract score values
            const overallScore = parseInt(scores.overall || summary.match_score || 0);
            const skillsScore = parseInt(scores.skills_match || 0);
            const expScore = parseInt(scores.experience_match || 0);
            const kwScore = parseInt(scores.keywords_match || 0);

            // Update score values
            this.$container.find('[data-score="overall"]').text(overallScore + '%');
            this.$container.find('[data-score="skills"]').text(skillsScore + '%');
            this.$container.find('[data-score="experience"]').text(expScore + '%');
            this.$container.find('[data-score="keywords"]').text(kwScore + '%');

            // Update bars and colors
            this.updateMetricBar('overall', overallScore, data);
            this.updateMetricBar('skills', skillsScore, data);
            this.updateMetricBar('experience', expScore, data);
            this.updateMetricBar('keywords', kwScore, data);
            this.updateScoreRail({
                overall: overallScore,
                skills: skillsScore,
                experience: expScore,
                keywords: kwScore
            }, data);
        }

        updateMetricBar(metric, score, data = {}) {
            const $metric = this.$container.find(`[data-metric="${metric}"]`);
            const $bar = this.$container.find(`[data-bar="${metric}"]`);
            const $status = this.$container.find(`[data-status="${metric}"]`);

            // Remove all state classes
            $metric.removeClass('is-good is-advisory is-critical is-info');

            // Set bar width
            $bar.css('width', Math.min(score, 100) + '%');

            // Determine state and status text based on score
            let stateClass, statusText;

            if (score >= 70) {
                stateClass = 'is-good';
                statusText = 'Strong match';
            } else if (score >= 50) {
                stateClass = 'is-advisory';
                statusText = 'Needs attention';
            } else if (score > 0) {
                stateClass = 'is-critical';
                statusText = 'Needs improvement';
            } else {
                stateClass = '';
                statusText = 'Awaiting analysis';
            }

            $metric.addClass(stateClass);
            $status.text(this.getMetricStatusText(metric, score, data, statusText));
        }

        getMetricStatusText(metric, score, data, fallbackText) {
            if (score <= 0) {
                return fallbackText || 'Awaiting analysis';
            }

            if (metric === 'overall') {
                if (score >= 70) {
                    return 'Strong match';
                }
                if (score >= 50) {
                    return 'Needs attention';
                }
                return 'Needs improvement';
            }

            if (metric === 'skills') {
                const missingSkills = Array.isArray(data?.missing_skills) ? data.missing_skills.length : 0;
                if (score >= 75 && missingSkills <= 1) {
                    return 'Strong tool coverage';
                }
                if (score >= 50) {
                    return 'Missing key tools';
                }
                return 'Tooling gaps';
            }

            if (metric === 'experience') {
                if (score >= 75) {
                    return 'Strong match';
                }
                if (score >= 50) {
                    return 'Relevant but thin';
                }
                return 'Limited alignment';
            }

            if (metric === 'keywords') {
                const missingKeywords = Array.isArray(data?.keyword_analysis?.critical_missing)
                    ? data.keyword_analysis.critical_missing.length
                    : 0;
                if (score >= 75 && missingKeywords <= 1) {
                    return 'Strong ATS coverage';
                }
                if (score >= 50) {
                    return 'Low ATS coverage';
                }
                return 'Missing ATS terms';
            }

            return fallbackText || 'Awaiting analysis';
        }

        updateScoreRail(scores, data = {}) {
            const overall = Math.max(0, Math.min(100, parseInt(scores.overall || 0, 10)));
            const railMetrics = {
                searchability: {
                    score: Math.max(0, Math.min(100, parseInt(scores.overall || 0, 10))),
                    issues: (data.red_flags || []).filter(item => {
                        const severity = String(item.severity || '').toLowerCase();
                        return severity === 'dealbreaker' || severity === 'serious' || severity === 'significant';
                    }).length
                },
                skills: {
                    score: Math.max(0, Math.min(100, parseInt(scores.skills || 0, 10))),
                    issues: (data.skills_breakdown?.missing_skills || []).length
                },
                experience: {
                    score: Math.max(0, Math.min(100, parseInt(scores.experience || 0, 10))),
                    issues: (data.requirements_analysis || []).filter(item => {
                        const matchStatus = String(item.match_status || '').toUpperCase();
                        const gapSeverity = String(item.gap_severity || '').toLowerCase();
                        return matchStatus === 'NOT_FOUND' || gapSeverity === 'critical' || gapSeverity === 'significant';
                    }).length
                },
                keywords: {
                    score: Math.max(0, Math.min(100, parseInt(scores.keywords || 0, 10))),
                    issues: (data.keyword_analysis?.critical_missing || []).length
                }
            };

            this.$container.find('[data-hero-score]').text(overall + '%');
            this.$container.find('[data-hero-ring]').css(
                'background',
                `conic-gradient(#f3d445 0deg ${Math.round((overall / 100) * 360)}deg, rgba(23, 35, 44, 0.08) ${Math.round((overall / 100) * 360)}deg 360deg)`
            );

            Object.keys(railMetrics).forEach((metric) => {
                const { score, issues } = railMetrics[metric];
                const state = this.getRailScoreState(score);
                const $item = this.$container.find(`[data-rail-metric="${metric}"]`);
                const $bar = this.$container.find(`[data-rail-bar="${metric}"]`);
                const $issue = this.$container.find(`[data-rail-issue="${metric}"]`);
                const $note = this.$container.find(`[data-rail-note="${metric}"]`);

                $item.attr('data-score-state', state);
                $bar.css('width', score + '%');
                $issue.text(this.getRailIssueLabel(score, issues));
                $note.text(this.getRailDetail(metric, data, score, issues));
            });
        }

        getRailScoreState(score) {
            if (score >= 75) {
                return 'good';
            }
            if (score >= 50) {
                return 'advisory';
            }
            if (score > 0) {
                return 'critical';
            }
            return 'idle';
        }

        getRailIssueLabel(score, missingCount) {
            if (score <= 0) {
                return 'Awaiting scan';
            }
            if (missingCount <= 0) {
                if (score >= 75) {
                    return 'Strong match';
                }
                if (score >= 50) {
                    return 'Mostly covered';
                }
                return 'Needs attention';
            }
            if (missingCount === 1) {
                return '1 issue to fix';
            }
            return `${missingCount} issues to fix`;
        }

        getRailDetail(metric, data, score, issues) {
            if (score <= 0) {
                return 'Run the optimizer to generate a role-specific diagnosis.';
            }

            if (metric === 'searchability') {
                const redFlags = (data.red_flags || [])
                    .filter(item => {
                        const issue = String(item.issue || '').toLowerCase();
                        return issue.includes('email') ||
                            issue.includes('phone') ||
                            issue.includes('address') ||
                            issue.includes('location') ||
                            issue.includes('summary') ||
                            issue.includes('format') ||
                            issue.includes('ats');
                    })
                    .map(item => this.getSearchabilityLabel(item.issue || ''))
                    .filter(Boolean);

                if (redFlags.length) {
                    return redFlags.slice(0, 2).join(' · ');
                }

                return score >= 75
                    ? 'ATS-readable structure and core contact details look visible.'
                    : 'Structure is mostly readable, but there are small ATS risks to tighten.';
            }

            if (metric === 'skills') {
                const missingSkills = (data.skills_breakdown?.missing_skills || [])
                    .map(item => typeof item === 'string' ? item : (item && item.skill ? item.skill : ''))
                    .map(item => String(item).trim())
                    .filter(Boolean);

                if (missingSkills.length) {
                    return 'Add ' + missingSkills.slice(0, 2).join(' · ');
                }

                return score >= 75
                    ? 'Core technical and role-specific skills are already visible.'
                    : 'Your hard-skill evidence is present, but it could be framed more explicitly.';
            }

            if (metric === 'experience') {
                const missingRequirements = (data.requirements_analysis || [])
                    .filter(item => {
                        const matchStatus = String(item.match_status || '').toUpperCase();
                        const gapSeverity = String(item.gap_severity || '').toLowerCase();
                        return matchStatus === 'NOT_FOUND' || gapSeverity === 'critical' || gapSeverity === 'significant';
                    })
                    .map(item => String(item.requirement || '').trim())
                    .filter(Boolean);

                if (missingRequirements.length) {
                    return 'Need clearer evidence of ' + missingRequirements.slice(0, 2).join(' · ');
                }

                return score >= 75
                    ? 'Relevant deal, finance, or role-fit evidence is coming through clearly.'
                    : 'Experience looks directionally relevant, but the role fit needs sharper proof.';
            }

            if (metric === 'keywords') {
                const missingKeywords = (data.keyword_analysis?.critical_missing || [])
                    .map(item => String(item || '').trim())
                    .filter(Boolean);

                if (missingKeywords.length) {
                    return 'Missing ' + missingKeywords.slice(0, 2).join(' · ');
                }

                return score >= 75
                    ? 'Core screening terms are already covered in your CV.'
                    : 'Most recruiter keywords are present, but a few terms still need surfacing.';
            }

            return issues > 0 ? `${issues} issues surfaced for this area.` : 'This area is currently in good shape.';
        }

        getSearchabilityLabel(issueText) {
            const issue = String(issueText || '').toLowerCase();
            if (!issue) {
                return '';
            }
            if (issue.includes('email')) {
                return 'Add an email address';
            }
            if (issue.includes('phone')) {
                return 'Add a phone number';
            }
            if (issue.includes('address') || issue.includes('location')) {
                return 'Add your location';
            }
            if (issue.includes('summary')) {
                return 'Add a summary section';
            }
            if (issue.includes('format') || issue.includes('ats')) {
                return 'Tighten ATS formatting';
            }

            return issueText;
        }

        renderMissingSection(data) {
            const $container = this.$container.find('[data-list="missing"]');
            const $count = this.$container.find('[data-count="missing"]');

            let missingItems = [];
            const seen = new Set();
            const pushMissingItem = (item) => {
                const title = String(item?.title || '').trim();
                if (!title) {
                    return;
                }
                const key = title.toLowerCase();
                if (seen.has(key)) {
                    return;
                }
                seen.add(key);
                missingItems.push(item);
            };

            // Red flags (dealbreakers)
            (data.red_flags || []).forEach(item => {
                if (item.severity === 'dealbreaker' || item.severity === 'serious') {
                    pushMissingItem({
                        title: item.issue,
                        description: item.mitigation || item.evidence,
                        severity: item.severity,
                        type: 'red_flag'
                    });
                }
            });

            // Missing skills
            const skills = data.skills_breakdown || {};
            (skills.missing_skills || []).forEach(item => {
                pushMissingItem({
                    title: item.skill,
                    description: item.suggestion,
                    severity: item.importance,
                    type: 'skill'
                });
            });

            // Requirements not found
            (data.requirements_analysis || []).forEach(item => {
                if (item.match_status === 'NOT_FOUND' || item.gap_severity === 'critical' || item.gap_severity === 'significant') {
                    pushMissingItem({
                        title: item.requirement,
                        description: item.action_needed || `Gap: ${item.cv_evidence}`,
                        severity: item.gap_severity,
                        type: 'requirement'
                    });
                }
            });

            // Keyword gaps
            const keywords = data.keyword_analysis || {};
            (keywords.critical_missing || []).slice(0, 5).forEach(kw => {
                pushMissingItem({
                    title: kw,
                    description: 'Add this keyword to your CV to improve ATS matching',
                    severity: 'important',
                    type: 'keyword'
                });
            });

            $count.text(missingItems.length);

            if (missingItems.length === 0) {
                $container.html(`
                    <div class="inst-chart-narrative">
                        <div class="inst-chart-narrative-header">
                            <svg viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                <polyline points="22 4 12 14.01 9 11.01"/>
                            </svg>
                            <span style="color: #16a34a;">No Critical Gaps Found</span>
                        </div>
                        <p style="color: var(--inst-gray-500); font-size: 13px;">Your profile aligns well with the job requirements.</p>
                    </div>
                `);
                return;
            }

            const prioritizedMissingItems = missingItems.filter(item => item.type === 'keyword');
            const displayItems = (prioritizedMissingItems.length ? prioritizedMissingItems : missingItems).slice(0, 3);

            const html = displayItems.map(item => {
                const severityClass = item.severity === 'critical' || item.severity === 'dealbreaker'
                    ? 'inst-gap-item--critical'
                    : 'inst-gap-item--missing';
                const severityLabel = item.severity === 'critical' || item.severity === 'dealbreaker'
                    ? '<span style="color:#dc2626;font-weight:600;font-size:10px;text-transform:uppercase;">CRITICAL</span>'
                    : '';

                return `
                    <div class="inst-gap-item ${severityClass}">
                        <div class="inst-gap-item__title">${this.escapeHtml(item.title)} ${severityLabel}</div>
                        ${item.description ? `<div class="inst-gap-item__text">${this.escapeHtml(item.description)}</div>` : ''}
                    </div>
                `;
            }).join('');

            $container.html(html);
        }

        renderMatchedSection(data) {
            const $container = this.$container.find('[data-list="matched"]');
            const $count = this.$container.find('[data-count="matched"]');

            let matchedItems = [];
            const seen = new Set();
            const pushMatchedItem = (item) => {
                const title = String(item?.title || '').trim();
                if (!title) {
                    return;
                }
                const key = title.toLowerCase();
                if (seen.has(key)) {
                    return;
                }
                seen.add(key);
                matchedItems.push(item);
            };

            // Strengths to highlight
            (data.strengths_to_highlight || []).forEach(item => {
                pushMatchedItem({
                    title: item.strength,
                    description: item.how_to_leverage || item.relevance,
                    type: 'strength'
                });
            });

            // Matched skills
            const skills = data.skills_breakdown || {};
            (skills.matched_skills || []).forEach(item => {
                pushMatchedItem({
                    title: item.skill,
                    description: item.cv_evidence || item.relevance || 'Detected in your CV and aligned to the role.',
                    type: 'skill'
                });
            });

            // Transferable skills
            (skills.transferable_skills || []).forEach(item => {
                pushMatchedItem({
                    title: item.skill,
                    description: item.positioning || item.relevance,
                    type: 'transferable'
                });
            });

            // Strong requirement matches
            (data.requirements_analysis || []).forEach(item => {
                if (item.match_status === 'STRONG_MATCH') {
                    pushMatchedItem({
                        title: item.requirement,
                        description: item.cv_evidence,
                        type: 'requirement'
                    });
                }
            });

            $count.text(matchedItems.length);

            if (matchedItems.length === 0) {
                $container.html(`
                    <div class="inst-chart-narrative">
                        <div class="inst-chart-narrative-header">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="12" y1="8" x2="12" y2="12"/>
                                <line x1="12" y1="16" x2="12.01" y2="16"/>
                            </svg>
                            <span>No Strong Matches Found</span>
                        </div>
                        <p style="color: var(--inst-gray-500); font-size: 13px;">Review the gaps section to improve your application.</p>
                    </div>
                `);
                return;
            }

            const html = matchedItems.slice(0, 10).map(item => `
                <div class="inst-gap-item inst-gap-item--matched">
                    <div class="inst-gap-item__title">${this.escapeHtml(item.title)}</div>
                    ${item.description ? `<div class="inst-gap-item__text">${this.escapeHtml(item.description)}</div>` : ''}
                </div>
            `).join('');

            $container.html(html);
        }

        renderRecommendations(data) {
            const $container = this.$container.find('[data-list="recommendations"]');
            const summary = data.executive_summary || {};
            const overall = data.overall_assessment || {};
            const scores = data.scores || {};
            const score = parseInt(scores.overall || summary.match_score || 0, 10);
            const missingSkills = (data.skills_breakdown?.missing_skills || [])
                .map(item => typeof item === 'string' ? item : item?.skill)
                .filter(Boolean);
            const missingKeywords = (data.keyword_analysis?.critical_missing || [])
                .map(item => String(item || '').trim())
                .filter(Boolean);
            const matchedKeywords = (data.keyword_analysis?.well_represented || [])
                .map(item => String(item || '').trim())
                .filter(Boolean);
            const criticalRequirements = (data.requirements_analysis || [])
                .filter(item => {
                    const matchStatus = String(item.match_status || '').toUpperCase();
                    const severity = String(item.gap_severity || '').toLowerCase();
                    return matchStatus === 'NOT_FOUND' || severity === 'critical' || severity === 'significant';
                })
                .map(item => item.requirement)
                .filter(Boolean);

            const nextMoves = [];
            (data.cv_improvements || []).slice(0, 4).forEach(item => {
                nextMoves.push({
                    label: item.section || 'CV section',
                    title: `Improve ${item.section || 'positioning'}`,
                    text: item.suggested || item.impact || 'Make this section more specific to the role.',
                    tone: 'blue'
                });
            });
            (data.cover_letter_points || []).slice(0, 2).forEach(point => {
                nextMoves.push({
                    label: 'Cover letter',
                    title: 'Use this proof point',
                    text: point,
                    tone: 'gold'
                });
            });
            (data.interview_prep || []).slice(0, 2).forEach(item => {
                nextMoves.push({
                    label: 'Interview',
                    title: item.likely_question || 'Prepare a likely question',
                    text: item.suggested_response_angle || 'Prepare a concise answer angle for this requirement.',
                    tone: 'green'
                });
            });

            if (!summary.verdict && !summary.key_insight && !overall.final_recommendation && nextMoves.length === 0) {
                $container.html(`
                    <div class="inst-gap-empty">
                        <p>No specific recommendations available.</p>
                    </div>
                `);
                return;
            }

            const scoreState = score >= 70 ? 'competitive' : (score >= 50 ? 'repairable' : 'high-risk');
            const verdict = summary.verdict || overall.final_recommendation || 'The report has identified the fastest fixes before you apply.';
            const keyInsight = summary.key_insight || 'Prioritise the missing evidence that maps directly to recruiter screening terms.';
            const focusChips = [
                ...missingSkills.slice(0, 3),
                ...criticalRequirements.slice(0, 2),
                ...missingKeywords.slice(0, 3)
            ].filter(Boolean).slice(0, 6);

            const html = `
                <div class="inst-rec-magazine">
                    <section class="inst-rec-lead inst-rec-lead--${scoreState}">
                        <div class="inst-rec-lead__score">
                            <strong>${Number.isFinite(score) ? score : 0}%</strong>
                            <span>${this.escapeHtml(scoreState.replace('-', ' '))}</span>
                        </div>
                        <div class="inst-rec-lead__copy">
                            <p>Assessment</p>
                            <h4>${this.escapeHtml(this.truncateText(verdict, 190))}</h4>
                            <span>${this.escapeHtml(this.truncateText(keyInsight, 150))}</span>
                        </div>
                    </section>

                    <section class="inst-rec-focus-strip">
                        <div>
                            <span>Fix first</span>
                            <strong>${missingSkills.length + criticalRequirements.length + missingKeywords.length}</strong>
                        </div>
                        <div>
                            <span>ATS terms</span>
                            <strong>${missingKeywords.length}</strong>
                        </div>
                        <div>
                            <span>Proof gaps</span>
                            <strong>${criticalRequirements.length}</strong>
                        </div>
                        <div>
                            <span>Already visible</span>
                            <strong>${matchedKeywords.length}</strong>
                        </div>
                    </section>

                    ${focusChips.length ? `
                        <section class="inst-rec-chip-board" aria-label="Priority signals">
                            ${focusChips.map((chip, index) => `
                                <span class="inst-rec-chip inst-rec-chip--${index < 3 ? 'missing' : 'neutral'}">${this.escapeHtml(chip)}</span>
                            `).join('')}
                        </section>
                    ` : ''}

                    <section class="inst-rec-move-grid">
                        ${nextMoves.slice(0, 6).map((move, index) => `
                            <article class="inst-rec-move inst-rec-move--${move.tone}">
                                <span class="inst-rec-move__num">${String(index + 1).padStart(2, '0')}</span>
                                <div>
                                    <p>${this.escapeHtml(move.label)}</p>
                                    <h4>${this.escapeHtml(this.truncateText(move.title, 70))}</h4>
                                    <span>${this.escapeHtml(this.truncateText(move.text, 135))}</span>
                                </div>
                            </article>
                        `).join('')}
                    </section>
                </div>
            `;

            $container.html(html);
        }

        truncateText(text, maxLength) {
            const clean = String(text || '').replace(/\s+/g, ' ').trim();
            if (clean.length <= maxLength) {
                return clean;
            }
            return clean.slice(0, Math.max(0, maxLength - 3)).trim() + '...';
        }

        clearAnalysisState() {
            this.analysisResult = null;
            this.clearPreviewModeArtifacts();
            this.setStage('scan');
            this.switchReportView('report');

            // Reset scores
            this.$container.find('[data-score="overall"]').text('--');
            this.$container.find('[data-score="keywords"]').text('--');
            this.$container.find('[data-score="skills"]').text('--');
            this.$container.find('[data-score="experience"]').text('--');
            this.$container.find('[data-hero-score]').text('--%');
            this.$container.find('[data-hero-ring]').css('background', 'conic-gradient(rgba(23, 35, 44, 0.08) 0deg 360deg)');
            this.$container.find('[data-report-verdict]').text('Paste a CV and job description to generate a visual hiring-readiness report.');
            this.$container.find('[data-report-recommendation]').text('Ready for scan');
            this.$container.find('[data-report-location]').text('Role-specific review');
            this.$container.find('[data-report-compass-score]').text('--%');
            this.$container.find('[data-report-compass]').css('background', 'conic-gradient(rgba(23, 35, 44, 0.08) 0deg 360deg)');
            this.$container.find('[data-report-signal]').css('height', '12%');
            this.$container.find('[data-report-key-insight] strong').text('Your strongest and weakest hiring signals will appear here after analysis.');

            ['searchability', 'skills', 'experience', 'keywords'].forEach(metric => {
                this.$container.find(`[data-rail-metric="${metric}"]`).attr('data-score-state', 'idle');
                this.$container.find(`[data-rail-bar="${metric}"]`).css('width', '0%');
                this.$container.find(`[data-rail-issue="${metric}"]`).text('Awaiting scan');
                this.$container.find(`[data-rail-note="${metric}"]`).text('Run the optimizer to generate a role-specific diagnosis.');
            });

            // Reset metric bars and states
            const metrics = ['overall', 'skills', 'experience', 'keywords'];
            metrics.forEach(metric => {
                const $metric = this.$container.find(`[data-metric="${metric}"]`);
                const $bar = this.$container.find(`[data-bar="${metric}"]`);
                const $status = this.$container.find(`[data-status="${metric}"]`);

                $metric.removeClass('is-good is-advisory is-critical is-info');
                $bar.css('width', '0%');
                $status.text('Awaiting analysis');
            });

            // Reset lists with placeholders
            this.$container.find('[data-list="missing"]').html(`
                <div class="inst-chart-narrative">
                    <div class="inst-chart-narrative-header">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="8" x2="12" y2="12"/>
                            <line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                        <span>Waiting for Analysis</span>
                    </div>
                    <p style="color: var(--inst-gray-500); font-size: 13px;">Paste your JD and CV, then click Analyze.</p>
                </div>
            `);

            this.$container.find('[data-list="matched"]').html(`
                <div class="inst-chart-narrative">
                    <div class="inst-chart-narrative-header">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                            <polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                        <span>Your Strengths</span>
                    </div>
                    <p style="color: var(--inst-gray-500); font-size: 13px;">Matching skills and experience will appear here.</p>
                </div>
            `);

            this.$container.find('[data-list="recommendations"]').html(`
                <div class="inst-chart-narrative">
                    <div class="inst-chart-narrative-header">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                            <line x1="12" y1="17" x2="12.01" y2="17"/>
                        </svg>
                        <span>Action Items</span>
                    </div>
                    <p style="color: var(--inst-gray-500); font-size: 13px;">Personalized recommendations will appear here.</p>
                </div>
            `);

            // Reset counts
            this.$container.find('[data-count="missing"]').text('0');
            this.$container.find('[data-count="matched"]').text('0');

            // Reset toolkit sections
            this.resetToolkit();
        }

        reset() {
            this.$container.find('[data-input="jd"]').val('');
            this.$container.find('[data-input="cv"]').val('');
            this.jdText = '';
            this.cvText = '';
            this.setUploadStatus('', false);
            this.clearCVRequiredState();
            this.clearAnalysisState();
            this.setMobileStep('cv');
            this.updateDisplayedJobTitle('Optimize this application');

            // Reset status
            this.updateStatus('waiting', 'Awaiting input');
            this.$container.find('.inst-chatbox-hint').text('Paste JD & CV, then analyze');

            $('html, body').animate({
                scrollTop: this.$container.offset().top - 20
            }, 300);
        }

        resetToolkit() {
            // Reset CV preview scores
            this.$container.find('[data-cv-score="before"]').text('--%');
            this.$container.find('[data-cv-score="after"]').text('--%');

            // Reset full CV preview
            this.$container.find('[data-cv-full-preview]').html(`
                <div class="inst-cv-placeholder">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                    </svg>
                    <span>Your optimized CV will appear here after analysis</span>
                </div>
            `);

            // Clear stored CV data
            this.optimizedCVData = null;

            // Reset CV improvements
            this.$container.find('[data-list="cv-improvements"]').html(`
                <div class="inst-toolkit-empty">
                    <p>Run analysis to see CV improvement suggestions</p>
                </div>
            `);

            // Reset cover letter
            this.$container.find('.inst-cover-letter-preview').html(`
                <div class="inst-toolkit-empty">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                        <polyline points="22,6 12,13 2,6"/>
                    </svg>
                    <p>Run analysis to generate a tailored cover letter</p>
                </div>
            `);
            this.$container.find('.inst-cover-letter-actions').hide();
            this.$container.find('[data-list="cover-points"]').html(`
                <div class="inst-toolkit-empty">
                    <p>Key talking points will appear here</p>
                </div>
            `);
            this.generatedCoverLetter = null;

            // Reset keywords
            this.$container.find('[data-stat="matched"]').text('0');
            this.$container.find('[data-stat="missing"]').text('0');
            this.$container.find('[data-stat="match-rate"]').text('0%');
            this.$container.find('[data-list="keywords-missing"]').html(`
                <div class="inst-toolkit-empty">
                    <p>Missing keywords will appear here</p>
                </div>
            `);
            this.$container.find('[data-list="keywords-matched"]').html(`
                <div class="inst-toolkit-empty">
                    <p>Matched keywords will appear here</p>
                </div>
            `);

            // Reset interview prep
            this.$container.find('[data-list="interview-questions"]').html(`
                <div class="inst-toolkit-empty">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                    <p>Interview questions will appear after analysis</p>
                </div>
            `);

            // Reset to first toolkit tab
            this.switchToolkitTab('cv');
        }

        async exportReport() {
            if (!this.hasPremiumAccess) {
                this.promptUpgrade();
                return;
            }

            if (!this.analysisResult) {
                this.showError('No analysis to export. Please run an analysis first.');
                return;
            }

            const $btn = this.$container.find('[data-action="export"]');
            $btn.addClass('is-loading').prop('disabled', true).text('Preparing...');

            try {
                // Get styled HTML from server
                const rawResponse = await $.ajax({
                    url: sffc_gap_analyzer.ajax_url,
                    type: 'POST',
                    dataType: 'text',
                    data: {
                        action: 'sffc_export_gap_pdf',
                        nonce: sffc_gap_analyzer.nonce,
                        analysis_data: JSON.stringify(this.analysisResult),
                    },
                });

                // Extract JSON from response
                let response;
                const jsonMatch = rawResponse.match(/\{[\s\S]*\}$/);
                if (jsonMatch) {
                    response = JSON.parse(jsonMatch[0]);
                } else {
                    throw new Error('Invalid response format');
                }

                if (!response.success) {
                    throw new Error(response.data?.message || 'Failed to generate report');
                }

                // Open in new window and trigger print dialog
                const printWindow = window.open('', '_blank');
                printWindow.document.write(response.data.html);
                printWindow.document.close();

                // Trigger print after content loads
                printWindow.onload = function() {
                    setTimeout(() => {
                        printWindow.print();
                    }, 300);
                };

            } catch (error) {
                console.error('Export error:', error);
                this.showError('Failed to generate report. Please try again.');
            } finally {
                $btn.removeClass('is-loading').prop('disabled', false).html(`
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="7 10 12 15 17 10"/>
                        <line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                    Export PDF
                `);
            }
        }

        promptUpgrade() {
            this.showError('Unlock the full MENA Careers review with membership.');
            if (this.membershipUrl) {
                setTimeout(() => {
                    window.location.href = this.membershipUrl;
                }, 500);
            }
        }

        downloadCVAsWord() {
            if (!this.hasPremiumAccess) {
                this.promptUpgrade();
                return;
            }

            if (!this.optimizedCVData) {
                this.showError('No CV analysis available. Please run an analysis first.');
                return;
            }

            const $btn = this.$container.find('[data-action="download-cv-word"]');
            $btn.addClass('is-loading').prop('disabled', true);

            try {
                const data = this.optimizedCVData;
                const cvSections = this.parseCVSections(data.originalCV);
                const date = new Date().toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });

                // Generate Word-compatible HTML
                let html = `
                    <!DOCTYPE html>
                    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word">
                    <head>
                        <meta charset="UTF-8">
                        <title>Optimized CV - Gap Analysis</title>
                        <style>
                            body { font-family: Calibri, Arial, sans-serif; font-size: 11pt; line-height: 1.5; color: #1e293b; margin: 40px; }
                            h1 { font-size: 18pt; color: #0f172a; margin-bottom: 6pt; border-bottom: 2px solid #0f172a; padding-bottom: 6pt; }
                            h2 { font-size: 13pt; color: #0f172a; margin-top: 18pt; margin-bottom: 8pt; text-transform: uppercase; letter-spacing: 1pt; border-bottom: 1px solid #e5e7eb; padding-bottom: 4pt; }
                            h3 { font-size: 11pt; color: #334155; margin-top: 12pt; margin-bottom: 4pt; }
                            p { margin: 6pt 0; }
                            ul { margin: 6pt 0 12pt 20pt; padding: 0; }
                            li { margin-bottom: 4pt; }
                            .summary-box { background-color: #f8fafc; border: 1px solid #e5e7eb; padding: 12pt; margin: 12pt 0; }
                            .highlight { background-color: #dcfce7; padding: 2pt 4pt; }
                            .add-keyword { color: #059669; font-weight: bold; }
                            .skill-tag { display: inline-block; padding: 2pt 8pt; margin: 2pt; border-radius: 4pt; font-size: 10pt; }
                            .skill-matched { background-color: #dcfce7; color: #166534; }
                            .skill-add { background-color: #fee2e2; color: #991b1b; }
                            .improvement-box { background-color: #eff6ff; border-left: 3px solid #2563eb; padding: 8pt 12pt; margin: 8pt 0; }
                            .score-section { text-align: center; margin: 20pt 0; }
                            .score { font-size: 24pt; font-weight: bold; }
                            .score-before { color: #dc2626; }
                            .score-after { color: #059669; }
                            .footer { margin-top: 30pt; padding-top: 12pt; border-top: 1px solid #e5e7eb; font-size: 9pt; color: #64748b; }
                        </style>
                    </head>
                    <body>
                        <h1>OPTIMIZED CV</h1>
                        <p style="font-size: 9pt; color: #64748b;">Generated by MENA Careers Gap Analyzer | ${date}</p>

                        <div class="score-section">
                            <span class="score score-before">${data.beforeScore}%</span>
                            <span style="margin: 0 20pt; font-size: 18pt;">→</span>
                            <span class="score score-after">${data.afterScore}%</span>
                            <p style="font-size: 10pt; color: #64748b;">Match Score Improvement</p>
                        </div>

                        <h2>PROFESSIONAL SUMMARY</h2>
                        <div class="summary-box">
                `;

                // Generate summary
                const strengths = data.strengths || [];
                const missingKeywords = data.keywords?.critical_missing || [];

                if (strengths.length > 0) {
                    const topStrengths = strengths.slice(0, 3).map(s => s.strength).join(', ');
                    html += `<p>Results-driven professional with proven expertise in <strong>${topStrengths}</strong>. `;
                } else {
                    html += `<p>Highly motivated professional seeking to leverage skills and experience in a challenging role. `;
                }

                if (missingKeywords.length > 0) {
                    html += `Demonstrated proficiency in <span class="add-keyword">${missingKeywords.slice(0, 2).join('</span> and <span class="add-keyword">')}</span>. `;
                }
                html += `Committed to delivering excellence and driving organizational success.</p>`;

                if (missingKeywords.length > 0) {
                    html += `<p style="font-size: 9pt; color: #059669;"><strong>Keywords to include:</strong> ${missingKeywords.slice(0, 5).join(', ')}</p>`;
                }
                html += `</div>`;

                // Experience Section
                html += `<h2>RELEVANT WORK EXPERIENCE</h2>`;

                const expLines = cvSections.experience;
                let currentJob = null;
                const jobs = [];

                expLines.forEach(line => {
                    if (/\d{4}|present|current/i.test(line) || /^[A-Z\s]+$/.test(line.substring(0, 30))) {
                        if (currentJob) jobs.push(currentJob);
                        currentJob = { title: line, bullets: [] };
                    } else if (currentJob) {
                        currentJob.bullets.push(line.replace(/^[•\-\*]\s*/, ''));
                    }
                });
                if (currentJob) jobs.push(currentJob);

                if (jobs.length === 0) {
                    jobs.push({ title: '[Your Job Title] | [Company Name] | [Dates]', bullets: ['Add your key responsibilities and achievements'] });
                }

                jobs.slice(0, 4).forEach((job, idx) => {
                    html += `<h3>${this.escapeHtml(job.title)}</h3><ul>`;
                    job.bullets.slice(0, 5).forEach((bullet, bulletIdx) => {
                        if (bulletIdx === 0 && missingKeywords[idx]) {
                            html += `<li>${this.escapeHtml(bullet)} <span class="add-keyword">[Add: ${missingKeywords[idx]}]</span></li>`;
                        } else {
                            html += `<li>${this.escapeHtml(bullet)}</li>`;
                        }
                    });
                    html += `</ul>`;

                    // Add improvement suggestion
                    const expImprovement = (data.improvements || []).find(i =>
                        i.section?.toLowerCase().includes('experience') ||
                        i.section?.toLowerCase().includes('work')
                    );
                    if (expImprovement && idx === 0) {
                        html += `<div class="improvement-box"><strong>💡 Improvement:</strong> ${this.escapeHtml(expImprovement.suggested || '')}</div>`;
                    }
                });

                // Skills Section
                html += `<h2>KEY SKILLS & COMPETENCIES</h2>`;

                const matchedSkills = [...new Set([
                    ...(data.keywords?.well_represented || []),
                    ...(data.skills?.matched_skills || []).map(s => s.skill)
                ])].slice(0, 12);

                const missingSkills = [...new Set([
                    ...(data.keywords?.critical_missing || []),
                    ...(data.skills?.missing_skills || []).map(s => s.skill)
                ])].slice(0, 10);

                if (matchedSkills.length > 0) {
                    html += `<p><strong>Current Skills (Keep):</strong></p><p>`;
                    matchedSkills.forEach(skill => {
                        html += `<span class="skill-tag skill-matched">${this.escapeHtml(skill)}</span> `;
                    });
                    html += `</p>`;
                }

                if (missingSkills.length > 0) {
                    html += `<p><strong style="color: #dc2626;">Skills to Add:</strong></p><p>`;
                    missingSkills.forEach(skill => {
                        html += `<span class="skill-tag skill-add">${this.escapeHtml(skill)}</span> `;
                    });
                    html += `</p>`;
                }

                // Education Section
                html += `<h2>EDUCATION & CERTIFICATIONS</h2>`;
                if (cvSections.education.length > 0) {
                    cvSections.education.slice(0, 4).forEach(line => {
                        html += `<p>${this.escapeHtml(line)}</p>`;
                    });
                } else {
                    html += `<p><em>[Add your educational qualifications]</em></p>`;
                }

                if (cvSections.certifications.length > 0) {
                    html += `<p><strong>Certifications:</strong></p>`;
                    cvSections.certifications.slice(0, 3).forEach(line => {
                        html += `<p>• ${this.escapeHtml(line)}</p>`;
                    });
                }

                // Achievements Section
                if (strengths.length > 0) {
                    html += `<h2>KEY ACHIEVEMENTS TO HIGHLIGHT</h2><ul>`;
                    strengths.slice(0, 4).forEach(s => {
                        html += `<li><strong>${this.escapeHtml(s.strength)}:</strong> ${this.escapeHtml(s.how_to_leverage || s.relevance || '')}</li>`;
                    });
                    html += `</ul>`;
                }

                // All Improvements Section
                if (data.improvements && data.improvements.length > 0) {
                    html += `<h2>ALL RECOMMENDED IMPROVEMENTS</h2>`;
                    data.improvements.forEach((imp, idx) => {
                        html += `
                            <div class="improvement-box">
                                <p><strong>${idx + 1}. ${this.escapeHtml(imp.section || 'Improvement')}</strong></p>
                                ${imp.current ? `<p><em>Current:</em> ${this.escapeHtml(imp.current)}</p>` : ''}
                                <p><strong>Suggested:</strong> ${this.escapeHtml(imp.suggested || '')}</p>
                                ${imp.impact ? `<p><em>Impact:</em> ${this.escapeHtml(imp.impact)}</p>` : ''}
                            </div>
                        `;
                    });
                }

                // Footer
                html += `
                        <div class="footer">
                            <p>Generated by MENA Careers Gap Analyzer | joinsenna.com</p>
                            <p>This document contains AI-generated suggestions. Review and customize before submitting.</p>
                        </div>
                    </body>
                    </html>
                `;

                // Create and download the file
                const blob = new Blob([html], { type: 'application/msword' });
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = 'Optimized_CV_' + new Date().toISOString().split('T')[0] + '.doc';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);

                this.showToast('CV downloaded successfully!');

            } catch (error) {
                console.error('Word export error:', error);
                this.showError('Failed to generate Word document. Please try again.');
            } finally {
                $btn.removeClass('is-loading').prop('disabled', false);
            }
        }

        /**
         * Download Cover Letter as Word document
         */
        downloadCoverLetterAsWord() {
            if (!this.hasPremiumAccess) {
                this.promptUpgrade();
                return;
            }

            if (!this.coverLetterData) {
                this.showError('No cover letter available. Please run an analysis first.');
                return;
            }

            const $btn = this.$container.find('[data-action="download-cover-word"]');
            $btn.addClass('is-loading').prop('disabled', true);

            try {
                const data = this.coverLetterData;
                const date = new Date().toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });

                // Generate Word-compatible HTML for cover letter
                let html = `
                    <!DOCTYPE html>
                    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word">
                    <head>
                        <meta charset="UTF-8">
                        <title>Cover Letter - ${this.escapeHtml(data.roleTitle)}</title>
                        <style>
                            body {
                                font-family: Calibri, Arial, sans-serif;
                                font-size: 11pt;
                                line-height: 1.6;
                                color: #1e293b;
                                margin: 60px;
                            }
                            .date { color: #64748b; margin-bottom: 24pt; }
                            .salutation { margin-bottom: 12pt; }
                            p { margin: 12pt 0; }
                            ul { margin: 12pt 0 12pt 20pt; padding: 0; }
                            li { margin-bottom: 6pt; }
                            .highlight-strength {
                                background-color: #dbeafe;
                                padding: 1pt 4pt;
                                font-weight: bold;
                            }
                            .highlight-keyword {
                                background-color: #dcfce7;
                                padding: 1pt 4pt;
                            }
                            .highlight-add {
                                background-color: #fef3c7;
                                padding: 1pt 4pt;
                                font-weight: bold;
                            }
                            .highlight-role {
                                background-color: #e0e7ff;
                                padding: 1pt 4pt;
                                font-weight: bold;
                            }
                            .highlight-company {
                                background-color: #fce7f3;
                                padding: 1pt 4pt;
                                font-weight: bold;
                            }
                            .closing { margin-top: 24pt; }
                            .placeholder { color: #dc2626; font-style: italic; }
                            .footer {
                                margin-top: 40pt;
                                padding-top: 12pt;
                                border-top: 1px solid #e5e7eb;
                                font-size: 9pt;
                                color: #64748b;
                            }
                            .legend {
                                margin-top: 30pt;
                                padding: 12pt;
                                background-color: #f8fafc;
                                border: 1px solid #e5e7eb;
                                font-size: 9pt;
                            }
                            .legend-title { font-weight: bold; display: block; margin-bottom: 8pt; }
                            .tip-box {
                                background-color: #eff6ff;
                                border-left: 3px solid #2563eb;
                                padding: 10pt 12pt;
                                margin: 16pt 0;
                                font-size: 10pt;
                            }
                        </style>
                    </head>
                    <body>
                        <p class="date">${date}</p>
                        <p class="salutation">Dear Hiring Manager,</p>
                `;

                // Opening paragraph
                let opening = `I am writing to express my strong interest in `;
                if (data.company) {
                    opening += `the <span class="highlight-role">${this.escapeHtml(data.roleTitle)}</span> position at <span class="highlight-company">${this.escapeHtml(data.company)}</span>`;
                } else {
                    opening += `the <span class="highlight-role">${this.escapeHtml(data.roleTitle)}</span> position`;
                }
                opening += `. After reviewing the role requirements, I am confident that my background and skills make me an excellent candidate.`;
                html += `<p>${opening}</p>`;

                // Key strengths
                if (data.strengths && data.strengths.length > 0) {
                    html += `<p>Key strengths I would bring to this role include:</p><ul>`;
                    data.strengths.slice(0, 3).forEach(s => {
                        let strengthText = `<span class="highlight-strength">${this.escapeHtml(s.strength)}</span>`;
                        if (s.how_to_leverage || s.relevance) {
                            strengthText += `: ${this.escapeHtml(s.how_to_leverage || s.relevance)}`;
                        }
                        html += `<li>${strengthText}</li>`;
                    });
                    html += `</ul>`;
                }

                // Cover letter points with highlighted keywords
                if (data.points && data.points.length > 0) {
                    data.points.slice(0, 2).forEach(point => {
                        let pointText = this.escapeHtml(point);
                        // Highlight matched keywords
                        (data.matchedKeywords || []).slice(0, 5).forEach(keyword => {
                            const regex = new RegExp(`\\b(${keyword.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})\\b`, 'gi');
                            pointText = pointText.replace(regex, '<span class="highlight-keyword">$1</span>');
                        });
                        html += `<p>${pointText}</p>`;
                    });
                }

                // Missing keywords paragraph
                if (data.missingKeywords && data.missingKeywords.length > 0) {
                    html += `<p>My experience also includes proficiency in `;
                    const keywordsToMention = data.missingKeywords.slice(0, 3);
                    keywordsToMention.forEach((kw, idx) => {
                        html += `<span class="highlight-add">${this.escapeHtml(kw)}</span>`;
                        if (idx < keywordsToMention.length - 2) {
                            html += ', ';
                        } else if (idx === keywordsToMention.length - 2) {
                            html += ', and ';
                        }
                    });
                    html += `, which I believe would contribute to the team's success.</p>`;
                }

                // Closing
                html += `
                    <p>I am excited about the opportunity to contribute to your team and would welcome the chance to discuss how my experience aligns with your needs.</p>
                    <p class="closing">Sincerely,<br><br><span class="placeholder">${this.escapeHtml(this.getCandidateFirstName())}</span><br><span class="placeholder">[Your Email]</span><br><span class="placeholder">[Your Phone]</span></p>
                `;

                // Add tips section
                html += `
                    <div class="tip-box">
                        <strong>Tips for customizing this letter:</strong><br>
                        • Replace all <span class="placeholder">[placeholder]</span> text with your actual information<br>
                        • The highlighted terms are key phrases to keep - they match the job requirements<br>
                        • <span class="highlight-add">Yellow highlights</span> are keywords you should weave into your specific examples<br>
                        • Consider adding specific metrics or achievements to make your points more compelling
                    </div>
                `;

                // Legend
                html += `
                    <div class="legend">
                        <span class="legend-title">Highlight Key:</span>
                        <span class="highlight-role">Role Title</span> &nbsp;
                        <span class="highlight-company">Company Name</span> &nbsp;
                        <span class="highlight-strength">Your Strengths</span> &nbsp;
                        <span class="highlight-keyword">Matched Keywords</span> &nbsp;
                        <span class="highlight-add">Keywords to Add</span>
                    </div>
                `;

                // Footer
                html += `
                        <div class="footer">
                            <p>Generated by MENA Careers Gap Analyzer | joinsenna.com</p>
                            <p>This is a template based on AI analysis. Personalize it with your specific experiences and achievements.</p>
                        </div>
                    </body>
                    </html>
                `;

                // Create and download the file
                const blob = new Blob([html], { type: 'application/msword' });
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                const fileName = data.company
                    ? `Cover_Letter_${data.company.replace(/[^a-zA-Z0-9]/g, '_')}_${new Date().toISOString().split('T')[0]}.doc`
                    : `Cover_Letter_${new Date().toISOString().split('T')[0]}.doc`;
                link.download = fileName;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);

                this.showToast('Cover letter downloaded successfully!');

            } catch (error) {
                console.error('Cover letter export error:', error);
                this.showError('Failed to generate cover letter document. Please try again.');
            } finally {
                $btn.removeClass('is-loading').prop('disabled', false);
            }
        }

        /**
         * Update the visual status indicator
         * @param {string} state - 'waiting', 'analyzing', 'success', 'error'
         * @param {string} label - Main status label
         * @param {string} details - Optional additional details
         */
        updateStatus(state, label, details = '') {
            const $status = this.$container.find('[data-meta="analysis-status"]');

            // Define icons for each state
            const icons = {
                waiting: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12 6 12 12 16 14"/>
                </svg>`,
                analyzing: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="inst-status-spin">
                    <path d="M12 2v4"/>
                    <path d="M12 18v4"/>
                    <path d="M4.93 4.93l2.83 2.83"/>
                    <path d="M16.24 16.24l2.83 2.83"/>
                    <path d="M2 12h4"/>
                    <path d="M18 12h4"/>
                    <path d="M4.93 19.07l2.83-2.83"/>
                    <path d="M16.24 7.76l2.83-2.83"/>
                </svg>`,
                success: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>`,
                error: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="15" y1="9" x2="9" y2="15"/>
                    <line x1="9" y1="9" x2="15" y2="15"/>
                </svg>`
            };

            // Build HTML with optional details
            let labelHtml = `<span class="inst-status-label">${this.escapeHtml(label)}</span>`;
            if (details) {
                labelHtml = `<span class="inst-status-label"><strong>${this.escapeHtml(label)}</strong></span>
                             <span class="inst-status-details">${this.escapeHtml(details)}</span>`;
            }

            $status.attr('data-status', state).html(`
                <span class="inst-status-icon">${icons[state] || icons.waiting}</span>
                ${labelHtml}
            `);
        }

        showError(message) {
            // Update status to show error
            this.updateStatus('error', 'Error', message);
            this.$container.find('.inst-chatbox-hint').text('Error - try again');

            // Also show alert for immediate feedback
            alert(message);
        }
    }

    // Initialize
    $(function() {
        $('[data-component="gap-analyzer"]').each(function() {
            new GapAnalyzer(this);
        });
    });

})(jQuery);
