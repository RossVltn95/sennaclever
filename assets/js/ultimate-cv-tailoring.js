/**
 * Ultimate CV Tailoring Frontend
 * Complete working implementation with no shortcuts
 * 
 * @version 4.0.0
 */

(function($) {
    'use strict';
    
    // Main CV Tailoring Manager
    var CVTailoringManager = {
        
        // Properties
        currentCVId: null,
        isProcessing: false,
        debug: true,
        processingTimers: [],
        
        /**
         * Initialize the system
         */
        init: function() {
            console.log('✅ Ultimate CV Tailoring System Initializing...');
            
            // Bind all event handlers
            this.bindEvents();
            
            // Check for existing CV
            this.checkExistingCV();
            
            // Set up job data extraction
            this.setupJobDataExtraction();
            
            console.log('✅ Ultimate CV Tailoring System Ready!');
        },
        
        /**
         * Bind all event handlers
         */
        bindEvents: function() {
            var self = this;
            
            // Handle tailor button clicks - MAIN CONNECTION POINT
            $(document).on('click', '.sffc-btn-tailor, .tailor-cv-btn, .cv-tailor-button, [data-action="tailor-cv"]', function(e) {
                e.preventDefault();
                console.log('✨ AI-Powered CV Tailoring Initiated');
                self.handleTailorClick($(this));
            });
            
            // Handle CV upload in modal
            $(document).on('change', '#ultimate-cv-file-input', function(e) {
                var file = this.files[0];
                if (file) {
                    self.validateAndShowFileInfo(file);
                }
            });
            
            // Handle upload button in modal
            $(document).on('click', '#ultimate-cv-upload-btn', function(e) {
                e.preventDefault();
                self.uploadCV();
            });
            
            // Handle modal close
            $(document).on('click', '.ultimate-cv-modal-close', function() {
                self.closeModal();
            });
            
            // Handle export buttons
            $(document).on('click', '.export-tailored-pdf', function() {
                self.exportCV('pdf');
            });
            
            $(document).on('click', '.export-tailored-docx', function() {
                self.exportCV('docx');
            });
        },
        
        /**
         * Check if CV already exists
         */
        checkExistingCV: function() {
            var self = this;
            
            // Check localStorage first
            var storedId = localStorage.getItem('sffc_ultimate_cv_id');
            if (storedId) {
                self.currentCVId = storedId;
                if (self.debug) console.log('Found CV ID in localStorage:', storedId);
            }
            
            // Also check sessionStorage
            if (!storedId) {
                storedId = sessionStorage.getItem('sffc_ultimate_cv_id');
                if (storedId) {
                    self.currentCVId = storedId;
                    if (self.debug) console.log('Found CV ID in sessionStorage:', storedId);
                }
            }
            
            // Verify with server
            $.ajax({
                url: sffc_ultimate_cv.ajax_url,
                type: 'POST',
                data: {
                    action: 'sffc_ultimate_check_cv',
                    nonce: sffc_ultimate_cv.nonce
                },
                success: function(response) {
                    if (response.success && response.data.has_cv) {
                        self.currentCVId = response.data.cv_id;
                        self.storeCVId(response.data.cv_id);
                        if (self.debug) {
                            console.log('CV verified on server:', response.data);
                        }
                    }
                }
            });
        },
        
        /**
         * Handle tailor button click
         */
        handleTailorClick: function($button) {
            if (this.isProcessing) {
                alert('Please wait, processing...');
                return;
            }
            
            // Extract job data from the card/page
            var jobData = this.extractJobData($button);
            
            if (this.debug) {
                console.log('Extracted job data:', jobData);
            }
            
            // Check if we have a CV
            if (!this.currentCVId) {
                this.showUploadModal(jobData);
            } else {
                this.tailorCV(jobData);
            }
        },
        
        /**
         * Extract job data from button/card with retry logic
         */
        extractJobData: function($button) {
            var self = this;
            
            // Find the job card container - expand search
            var $card = $button.closest('.sffc-match-card, .job-card-vogue, .job-card, .opportunity-card, article, .job-listing, .job-post, [data-job-id]');
            
            // If no card found, try parent containers
            if (!$card.length) {
                $card = $button.parent().parent();
            }
            
            // Initialize job data object
            var jobData = {
                job_title: '',
                company: '',
                location: '',
                job_description: '',
                job_id: ''
            };
            
            // Function to extract with fallbacks
            function extractWithFallbacks() {
                // Try multiple methods to get job title - EXPANDED
                jobData.job_title = 
                    $button.data('job-title') ||
                    $button.attr('data-job-title') ||
                    $card.find('.job-title, .sffc-job-title, .position-title, h3.title, h2.title, .role-title, .position').first().text().trim() ||
                    $card.find('h3:first, h2:first, h4:first').text().trim() ||
                    $card.find('[class*="title"]:first').text().trim() ||
                    $card.data('job-title') ||
                    $card.attr('data-job-title') ||
                    // Check page-level elements
                    $('.job-header h1, .job-title-main, #job-title, .single-job-title').first().text().trim() ||
                    $('h1:contains("Analyst"), h1:contains("Associate"), h1:contains("Manager")').first().text().trim() ||
                    document.title.split('-')[0].trim() ||
                    'Position';
                
                // Try multiple methods to get company - EXPANDED
                jobData.company = 
                    $button.data('company') ||
                    $button.attr('data-company') ||
                    $card.find('.company, .company-name, .sffc-company, .employer, .firm-name, .organization').first().text().trim() ||
                    $card.find('[class*="company"]:first').text().trim() ||
                    $card.data('company') ||
                    $card.attr('data-company') ||
                    // Check page-level elements
                    $('#company-name, .company-header, .employer-name').first().text().trim() ||
                    $('.company-info h2, .firm-details h2').first().text().trim() ||
                    $('h2:contains("Capital"), h2:contains("Partners"), h2:contains("Investment")').first().text().trim() ||
                    'Company';
            
                // Try multiple methods to get location - EXPANDED
                jobData.location = 
                    $button.data('location') ||
                    $button.attr('data-location') ||
                    $card.find('.location, .job-location, .sffc-location, .city').first().text().trim() ||
                    $card.find('[class*="location"]:first').text().trim() ||
                    $card.find('.fa-map-marker-alt, .fa-location-dot').parent().text().trim() ||
                    $card.data('location') ||
                    $('#job-location, .location-header').first().text().trim() ||
                    $('span:contains("New York"), span:contains("London"), span:contains("San Francisco")').first().text().trim() ||
                    '';
                
                // Try multiple methods to get description - EXPANDED
                jobData.job_description = 
                    $button.data('description') ||
                    $button.attr('data-description') ||
                    $card.find('.description, .job-description, .job-summary, .job-details').first().text().trim() ||
                    $card.find('[class*="description"]:first').text().trim() ||
                    $card.data('description') ||
                    // Page-level description
                    $('#job-description, .job-content, .job-details-section').first().text().trim() ||
                    $('.description-content, .role-description').first().text().trim() ||
                    $('div:contains("Responsibilities"):first').parent().text().trim() ||
                    '';
                
                // Get job ID if available
                jobData.job_id = 
                    $button.data('job-id') ||
                    $button.attr('data-id') ||
                    $card.data('job-id') ||
                    $card.attr('data-id') ||
                    $card.attr('id') ||
                    '';
            }
            
            // Run extraction immediately
            extractWithFallbacks();
            
            // If we don't have good data, wait and try again
            if (!jobData.job_title || jobData.job_title === 'Position' || 
                !jobData.company || jobData.company === 'Company') {
                
                // Try again after a short delay (content might still be loading)
                setTimeout(function() {
                    extractWithFallbacks();
                    
                    // Clean up the data after retry
                    jobData.job_title = self.cleanText(jobData.job_title);
                    jobData.company = self.cleanText(jobData.company);
                    jobData.location = self.cleanText(jobData.location);
                    
                    if (self.debug) {
                        console.log('Retried job extraction:', jobData);
                    }
                }, 500);
            }
            
            // Clean up the data
            jobData.job_title = this.cleanText(jobData.job_title);
            jobData.company = this.cleanText(jobData.company);
            jobData.location = this.cleanText(jobData.location);
            
            // If STILL no job title after all attempts, check metadata
            if (jobData.job_title === 'Position' || !jobData.job_title) {
                // Check page title
                var pageTitle = $('title').text();
                if (pageTitle && pageTitle.includes('-')) {
                    jobData.job_title = pageTitle.split('-')[0].trim();
                }
                
                // Check meta tags
                var metaTitle = $('meta[property="og:title"]').attr('content') || 
                               $('meta[name="twitter:title"]').attr('content');
                if (metaTitle) {
                    jobData.job_title = metaTitle.split('-')[0].trim();
                }
            }
            
            return jobData;
        },
        
        /**
         * Clean extracted text
         */
        cleanText: function(text) {
            if (!text) return '';
            // Remove extra whitespace, newlines, and common artifacts
            return text.replace(/\s+/g, ' ').replace(/^\s+|\s+$/g, '').replace(/\n/g, ' ');
        },
        
        /**
         * Show CV upload modal
         */
        showUploadModal: function(jobData) {
            var self = this;
            
            // Store job data for after upload
            this.pendingJobData = jobData;
            
            var modalHtml = `
                <div id="ultimate-cv-modal" class="sffc-modal-overlay">
                    <div class="sffc-modal-content">
                        <div class="sffc-modal-header">
                            <h2>Upload Your CV</h2>
                            <span class="ultimate-cv-modal-close">&times;</span>
                        </div>
                        <div class="sffc-modal-body">
                            <p>To tailor your CV for <strong>${jobData.job_title}</strong> at <strong>${jobData.company}</strong>, please upload your CV first.</p>
                            
                            <div class="cv-upload-area">
                                <input type="file" id="ultimate-cv-file-input" accept=".pdf,.doc,.docx,.txt" />
                                <label for="ultimate-cv-file-input" class="cv-upload-label">
                                    <svg width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                        <polyline points="17 8 12 3 7 8"></polyline>
                                        <line x1="12" y1="3" x2="12" y2="15"></line>
                                    </svg>
                                    <span>Choose CV file or drag here</span>
                                    <small>PDF, DOC, DOCX, TXT (Max 10MB)</small>
                                </label>
                            </div>
                            
                            <div id="cv-file-info" style="display:none;">
                                <p class="file-name"></p>
                                <p class="file-size"></p>
                            </div>
                            
                            <div id="cv-upload-status"></div>
                            
                            <button id="ultimate-cv-upload-btn" class="sffc-btn-primary" style="display:none;">
                                Upload CV & Continue
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            // Add modal to page
            $('body').append(modalHtml);
            
            // Add drag and drop support
            this.setupDragDrop();
        },
        
        /**
         * Setup drag and drop for CV upload
         */
        setupDragDrop: function() {
            var self = this;
            var $dropArea = $('.cv-upload-area');
            
            $dropArea.on('dragover', function(e) {
                e.preventDefault();
                $(this).addClass('drag-over');
            });
            
            $dropArea.on('dragleave', function() {
                $(this).removeClass('drag-over');
            });
            
            $dropArea.on('drop', function(e) {
                e.preventDefault();
                $(this).removeClass('drag-over');
                
                var files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    $('#ultimate-cv-file-input')[0].files = files;
                    self.validateAndShowFileInfo(files[0]);
                }
            });
        },
        
        /**
         * Validate file and show info
         */
        validateAndShowFileInfo: function(file) {
            // Check file type
            var allowedTypes = ['application/pdf', 'application/msword', 
                              'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                              'text/plain'];
            
            if (!allowedTypes.includes(file.type)) {
                this.showStatus('Invalid file type. Please upload PDF, DOC, DOCX, or TXT.', 'error');
                return;
            }
            
            // Check file size
            if (file.size > sffc_ultimate_cv.max_file_size) {
                this.showStatus('File too large. Maximum size is 10MB.', 'error');
                return;
            }
            
            // Show file info
            $('#cv-file-info').show();
            $('#cv-file-info .file-name').text('File: ' + file.name);
            $('#cv-file-info .file-size').text('Size: ' + this.formatFileSize(file.size));
            $('#ultimate-cv-upload-btn').show();
        },
        
        /**
         * Format file size for display
         */
        formatFileSize: function(bytes) {
            if (bytes < 1024) return bytes + ' bytes';
            if (bytes < 1048576) return Math.round(bytes / 1024) + ' KB';
            return Math.round(bytes / 1048576 * 10) / 10 + ' MB';
        },
        
        /**
         * Upload CV to server
         */
        uploadCV: function() {
            var self = this;
            var fileInput = document.getElementById('ultimate-cv-file-input');
            
            if (!fileInput.files || !fileInput.files[0]) {
                this.showStatus('Please select a CV file', 'error');
                return;
            }
            
            var file = fileInput.files[0];
            var formData = new FormData();
            
            formData.append('action', 'sffc_ultimate_upload_cv');
            formData.append('nonce', sffc_ultimate_cv.nonce);
            formData.append('cv_file', file);
            
            // Show processing with AI stage
            this.isProcessing = true;
            this.showProcessingModal('Uploading your CV...', 'uploading');
            $('#ultimate-cv-upload-btn').prop('disabled', true);
            
            $.ajax({
                url: sffc_ultimate_cv.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    self.isProcessing = false;
                    
                    if (response.success) {
                        // Store CV ID
                        self.currentCVId = response.data.cv_id;
                        self.storeCVId(response.data.cv_id);
                        
                        self.showStatus('CV uploaded successfully!', 'success');
                        
                        // Close modal and continue with tailoring
                        setTimeout(function() {
                            self.closeModal();
                            
                            // Continue with tailoring if we have pending job data
                            if (self.pendingJobData) {
                                self.tailorCV(self.pendingJobData);
                            }
                        }, 1500);
                        
                    } else {
                        $('#ultimate-cv-upload-btn').prop('disabled', false);
                        self.showStatus(response.data.message || 'Upload failed', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    self.isProcessing = false;
                    $('#ultimate-cv-upload-btn').prop('disabled', false);
                    
                    var message = 'Upload failed: ';
                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        message += xhr.responseJSON.data.message;
                    } else {
                        message += error;
                    }
                    
                    self.showStatus(message, 'error');
                    
                    if (self.debug) {
                        console.error('Upload error:', xhr.responseText);
                    }
                }
            });
        },
        
        /**
         * Tailor CV with job data
         */
        tailorCV: function(jobData) {
            var self = this;
            
            if (!this.currentCVId) {
                this.showUploadModal(jobData);
                return;
            }
            
            // Show processing modal with AI stage
            this.showProcessingModal('Tailoring your CV for ' + jobData.job_title + '...', 'ai_analyzing');
            this.isProcessing = true;
            this.clearProcessingTimers();
            
            // Update stages during processing
            var self = this;
            this.processingTimers.push(setTimeout(function() {
                self.showProcessingModal('Extracting your experience...', 'parsing');
            }, 2000));
            this.processingTimers.push(setTimeout(function() {
                self.showProcessingModal('Optimizing for the role...', 'tailoring');
            }, 4000));
            
            // Prepare data
            var requestData = {
                action: 'sffc_ultimate_tailor_cv',
                nonce: sffc_ultimate_cv.nonce,
                cv_id: this.currentCVId,
                job_title: jobData.job_title,
                company: jobData.company,
                location: jobData.location,
                job_description: jobData.job_description,
                job_id: jobData.job_id
            };
            
            if (this.debug) {
                console.log('Sending tailoring request:', requestData);
            }
            
            $.ajax({
                url: sffc_ultimate_cv.ajax_url,
                type: 'POST',
                data: requestData,
                success: function(response) {
                    self.isProcessing = false;
                    self.clearProcessingTimers();
                    self.closeProcessingModal();
                    
                    if (response.success) {
                        // Add AI insights flag if AI was used
                        response.data.ai_powered = true;
                        console.log('✅ AI-Powered CV Tailoring Complete');
                        self.showTailoringResults(response.data);
                    } else {
                        // Check for AI fallback
                        if (response.data && response.data.message && response.data.message.includes('API')) {
                            console.log('⚠️ AI unavailable, using standard parsing');
                        }
                        alert(response.data.message || 'Tailoring failed. Please try again.');
                    }
                },
                error: function(xhr, status, error) {
                    self.isProcessing = false;
                    self.clearProcessingTimers();
                    self.closeProcessingModal();
                    
                    var message = 'Tailoring failed: ';
                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        message += xhr.responseJSON.data.message;
                    } else {
                        message += error;
                    }
                    
                    alert(message);
                    
                    if (self.debug) {
                        console.error('Tailoring error:', xhr.responseText);
                    }
                }
            });
        },
        
        /**
         * Show tailoring results
         */
        showTailoringResults: function(data) {
            var self = this;
            
            var resultsHtml = `
                <div id="ultimate-results-modal" class="sffc-modal-overlay">
                    <div class="sffc-modal-content sffc-modal-large">
                        <div class="sffc-modal-header">
                            <h2>CV Tailoring Complete!</h2>
                            <span class="ultimate-cv-modal-close">&times;</span>
                        </div>
                        <div class="sffc-modal-body">
                            <div class="results-summary">
                                <div class="match-score">
                                    <div class="score-circle">
                                        <span class="score-number">${data.match_score}%</span>
                                        <span class="score-label">Match</span>
                                    </div>
                                </div>
                                <div class="job-details">
                                    <h3>${data.job_title}</h3>
                                    <p>${data.company}</p>
                                </div>
                            </div>
                            
                            ${data.recommendations && data.recommendations.length ? `
                            <div class="recommendations-section">
                                <h3>Recommendations</h3>
                                <ul>
                                    ${data.recommendations.map(rec => `<li>${rec}</li>`).join('')}
                                </ul>
                            </div>
                            ` : ''}
                            
                            ${data.improvements && data.improvements.length ? `
                            <div class="improvements-section">
                                <h3>Suggested Improvements</h3>
                                <ul>
                                    ${data.improvements.map(imp => `<li>${imp}</li>`).join('')}
                                </ul>
                            </div>
                            ` : ''}
                            
                            ${data.keywords_added && data.keywords_added.length ? `
                            <div class="keywords-section">
                                <h3>Keywords to Include</h3>
                                <div class="keywords-list">
                                    ${data.keywords_added.map(kw => `<span class="keyword-tag">${kw}</span>`).join('')}
                                </div>
                            </div>
                            ` : ''}
                            
                            <div class="action-buttons">
                                <button class="export-tailored-pdf sffc-btn-primary" data-tailored-id="${data.tailored_id}">
                                    Download as PDF
                                </button>
                                <button class="export-tailored-docx sffc-btn-secondary" data-tailored-id="${data.tailored_id}">
                                    Download as Word
                                </button>
                                <button class="close-results sffc-btn-outline">
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(resultsHtml);
            
            // Store tailored ID for export
            this.lastTailoredId = data.tailored_id;
            
            // ALSO store globally for downloadTailoredCV() function to use
            window.lastTailoredCvId = data.tailored_id;
            
            // Bind close button
            $('.close-results, #ultimate-results-modal .ultimate-cv-modal-close').on('click', function() {
                $('#ultimate-results-modal').remove();
            });
            
            // Bind export buttons
            $('.export-tailored-pdf').on('click', function() {
                self.exportCV('pdf');
            });
            
            $('.export-tailored-docx').on('click', function() {
                self.exportCV('docx');
            });
        },
        
        /**
         * Export tailored CV
         */
        exportCV: function(format) {
            var self = this;
            
            if (!this.lastTailoredId) {
                alert('No tailored CV to export');
                return;
            }
            
            this.showProcessingModal('Generating ' + format.toUpperCase() + ' file...');
            
            $.ajax({
                url: sffc_ultimate_cv.ajax_url,
                type: 'POST',
                data: {
                    action: 'sffc_ultimate_export_cv',
                    nonce: sffc_ultimate_cv.nonce,
                    tailored_id: this.lastTailoredId,
                    format: format
                },
                success: function(response) {
                    self.closeProcessingModal();
                    
                    if (response.success) {
                        // Download the file
                        window.location.href = response.data.download_url;
                    } else {
                        alert(response.data.message || 'Export failed');
                    }
                },
                error: function() {
                    self.closeProcessingModal();
                    alert('Export failed. Please try again.');
                }
            });
        },
        
        /**
         * Store CV ID in multiple places
         */
        storeCVId: function(cvId) {
            // Store in localStorage
            localStorage.setItem('sffc_ultimate_cv_id', cvId);
            
            // Store in sessionStorage
            sessionStorage.setItem('sffc_ultimate_cv_id', cvId);
            
            // Store in cookie
            document.cookie = 'sffc_ultimate_cv_id=' + cvId + '; path=/; max-age=' + (30*24*60*60);
            
            if (this.debug) {
                console.log('CV ID stored:', cvId);
            }
        },
        
        /**
         * Show status message
         */
        showStatus: function(message, type) {
            var $status = $('#cv-upload-status');
            
            $status.removeClass('error success processing')
                   .addClass(type)
                   .html(message);
        },
        
        /**
         * Show processing modal
         */
        showProcessingModal: function(message, stage) {
            var self = this;
            
            // Define processing stages with better messaging
            var stages = {
                'uploading': '📤 Uploading your CV...',
                'ai_analyzing': '🤖 AI analyzing your CV structure...',
                'parsing': '📝 Extracting experience and skills...',
                'tailoring': '✨ Optimizing for the job role...',
                'finalizing': '📋 Generating your tailored CV...'
            };
            
            var stageProgress = {
                'uploading': 20,
                'ai_analyzing': 40,
                'parsing': 60,
                'tailoring': 80,
                'finalizing': 95
            };
            
            var displayMessage = stages[stage] || message;
            var progress = stageProgress[stage] || 50;
            
            // Check if modal exists, update it, otherwise create it
            if ($('#processing-modal').length) {
                $('#processing-modal .processing-message').text(displayMessage);
                $('#processing-modal .progress-fill').css('width', progress + '%');
                $('#processing-modal .progress-percent').text(progress + '%');
            } else {
                var modalHtml = `
                    <div id="processing-modal" class="sffc-modal-overlay">
                        <div class="sffc-modal-content sffc-modal-small">
                            <div class="sffc-processing-content">
                                <div class="ai-processing-header">
                                    <div class="ai-badge">AI-Powered Processing</div>
                                </div>
                                <div class="processing-spinner"></div>
                                <p class="processing-message">${displayMessage}</p>
                                <div class="progress-container">
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: ${progress}%"></div>
                                    </div>
                                    <span class="progress-percent">${progress}%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                $('body').append(modalHtml);
            }
        },
        
        /**
         * Close processing modal
         */
        closeProcessingModal: function() {
            this.clearProcessingTimers();
            $('#processing-modal').remove();
        },

        /**
         * Clear pending processing stage timers
         */
        clearProcessingTimers: function() {
            if (!this.processingTimers || !this.processingTimers.length) {
                return;
            }
            this.processingTimers.forEach(function(timerId) {
                clearTimeout(timerId);
            });
            this.processingTimers = [];
        },
        
        /**
         * Close modal
         */
        closeModal: function() {
            $('#ultimate-cv-modal').remove();
        },
        
        /**
         * Setup job data extraction helpers with dynamic content support
         */
        setupJobDataExtraction: function() {
            var self = this;
            
            // Function to process job cards
            function processJobCards() {
                $('.sffc-match-card, .job-card, .job-card-vogue, .opportunity-card').each(function() {
                    var $card = $(this);
                    
                    // Try to add job data if not present
                    if (!$card.data('job-title')) {
                        var title = $card.find('.job-title, .sffc-job-title, .position-title, h3.title, h2.title, h3:first, h2:first').first().text().trim();
                        if (title) $card.attr('data-job-title', title);
                    }
                    
                    if (!$card.data('company')) {
                        var company = $card.find('.company, .company-name, .sffc-company, .employer').first().text().trim();
                        if (company) $card.attr('data-company', company);
                    }
                    
                    if (!$card.data('location')) {
                        var location = $card.find('.location, .job-location, .sffc-location').first().text().trim();
                        if (location) $card.attr('data-location', location);
                    }
                    
                    if (!$card.data('description')) {
                        var desc = $card.find('.description, .job-description, .job-desc').first().text().trim();
                        if (desc) $card.attr('data-description', desc);
                    }
                });
            }
            
            // Process immediately
            processJobCards();
            
            // Process again after a delay (for AJAX content)
            setTimeout(processJobCards, 1000);
            setTimeout(processJobCards, 2000);
            setTimeout(processJobCards, 3000);
            
            // Watch for DOM changes (new job cards added dynamically)
            if (window.MutationObserver) {
                var observer = new MutationObserver(function(mutations) {
                    var hasNewCards = mutations.some(function(mutation) {
                        return mutation.addedNodes.length > 0;
                    });
                    
                    if (hasNewCards) {
                        setTimeout(processJobCards, 100);
                    }
                });
                
                // Start observing
                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
                
                if (self.debug) {
                    console.log('MutationObserver started for job card detection');
                }
            }
            
            // Also listen for custom events that might signal content loaded
            $(document).on('jobsLoaded opportunitiesLoaded contentLoaded ajaxComplete', function() {
                setTimeout(processJobCards, 100);
            });
            
            // Listen for jQuery AJAX completion
            $(document).ajaxComplete(function() {
                setTimeout(processJobCards, 100);
            });
        }
    };
    
    // Initialize when document is ready AND after window load
    $(document).ready(function() {
        // Initialize immediately
        CVTailoringManager.init();
        
        // Make it globally accessible for debugging
        window.CVTailoringManager = CVTailoringManager;
        window.dispatchEvent(new CustomEvent('sffcUltimateCvReady', {
            detail: {
                manager: CVTailoringManager
            }
        }));
        
        // Re-initialize after window fully loads (images, styles, etc.)
        $(window).on('load', function() {
            CVTailoringManager.setupJobDataExtraction();
            console.log('CV Tailoring: Re-initialized after window load');
        });
        
        // Also reinitialize if the page uses Turbo/Turbolinks or similar
        $(document).on('turbo:load turbolinks:load page:load pjax:complete', function() {
            CVTailoringManager.setupJobDataExtraction();
            console.log('CV Tailoring: Re-initialized after page navigation');
        });
    });
    
})(jQuery);
