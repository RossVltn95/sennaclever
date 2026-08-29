/**
 * MENA Careers Visual Enhancements
 * Adds charts, infographics, and quick apply functionality
 */

(function($) {
    'use strict';
    
    class SennaVisualEnhancements {
        constructor() {
            this.init();
        }
        
        init() {
            this.enhanceMessages();
            this.addTailorCVButtons();
            this.bindEvents();
            console.log('MENA Careers Visual Enhancements initialized');
        }
        
        bindEvents() {
            // Tailor CV button clicks
            $(document).on('click', '.btn-tailor-cv', (e) => {
                e.stopPropagation();
                this.handleTailorCV(e);
            });
            
            // Analyze first button clicks
            $(document).on('click', '.btn-analyze-first', (e) => {
                e.stopPropagation();
                this.handleAnalyzeFirst(e);
            });
            
            // Enhance new messages as they appear
            $(document).on('DOMNodeInserted', '.sffc-messages-container, .ultimate-messages', (e) => {
                if ($(e.target).hasClass('sffc-message') || $(e.target).hasClass('message')) {
                    this.enhanceMessage($(e.target));
                }
            });
        }
        
        /**
         * Enhance existing messages with visuals
         */
        enhanceMessages() {
            $('.sffc-message, .message').each((index, element) => {
                this.enhanceMessage($(element));
            });
        }
        
        /**
         * Enhance a single message
         */
        enhanceMessage($message) {
            const text = $message.find('.sffc-message-content, .message-text').text().toLowerCase();
            
            // Check if message contains job information
            if (this.containsJobInfo(text)) {
                this.addJobVisuals($message);
            }
            
            // Check if message contains salary information
            if (text.includes('salary') || text.includes('compensation') || text.includes('$')) {
                this.addSalaryVisualization($message);
            }
            
            // Check if message contains skills
            if (text.includes('skills') || text.includes('requirements')) {
                this.addSkillsVisualization($message);
            }
        }
        
        /**
         * Check if text contains job information
         */
        containsJobInfo(text) {
            const jobKeywords = ['position', 'role', 'job', 'opportunity', 'opening', 'vacancy'];
            return jobKeywords.some(keyword => text.includes(keyword));
        }
        
        /**
         * Add job visuals to message
         */
        addJobVisuals($message) {
            // Extract job data from message
            const jobData = this.extractJobData($message);
            
            if (!jobData.title) return;
            
            // Create visual card
            const visualHtml = `
                <div class="job-analytics-chart">
                    <div class="chart-header">
                        <span class="chart-title">Match Analysis</span>
                        <span class="chart-value">${jobData.matchScore || '85'}%</span>
                    </div>
                    
                    <div class="match-score-circle ${jobData.matchScore > 80 ? 'high-match' : ''}">
                        <svg width="120" height="120">
                            <circle cx="60" cy="60" r="50" fill="none" stroke="#e5e7eb" stroke-width="8"/>
                            <circle cx="60" cy="60" r="50" fill="none" 
                                    stroke="url(#gradient)" stroke-width="8"
                                    stroke-dasharray="${(jobData.matchScore || 85) * 3.14} 314"
                                    stroke-linecap="round"/>
                            <defs>
                                <linearGradient id="gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" style="stop-color:#667eea;stop-opacity:1" />
                                    <stop offset="100%" style="stop-color:#764ba2;stop-opacity:1" />
                                </linearGradient>
                            </defs>
                        </svg>
                        <div class="score-text">${jobData.matchScore || (window.sffc_frontend?.is_logged_in === '1' ? '0' : 'Login')}${jobData.matchScore ? '%' : ''}</div>
                        <div class="score-label">Match</div>
                    </div>
                    
                    <div class="job-timeline">
                        <div class="timeline-step active">
                            <div class="timeline-dot"></div>
                        </div>
                        <div class="timeline-step">
                            <div class="timeline-dot"></div>
                        </div>
                        <div class="timeline-step">
                            <div class="timeline-dot"></div>
                        </div>
                        <div class="timeline-step">
                            <div class="timeline-dot"></div>
                        </div>
                    </div>
                    
                    <div style="display: flex; justify-content: space-around; margin-top: 8px; font-size: 11px; color: #6b7280;">
                        <span>Tailor CV</span>
                        <span>Interview</span>
                        <span>Assessment</span>
                        <span>Offer</span>
                    </div>
                </div>
            `;
            
            // Append visual to message if not already added
            if (!$message.find('.job-analytics-chart').length) {
                $message.find('.sffc-message-content, .message-text').append(visualHtml);
            }
        }
        
        /**
         * Extract job data from message
         */
        extractJobData($message) {
            const text = $message.find('.sffc-message-content, .message-text').text();
            
            // Try to extract job title
            const titleMatch = text.match(/(?:position|role|job|title):\s*([^,\n]+)/i);
            const title = titleMatch ? titleMatch[1].trim() : null;
            
            // Try to extract company
            const companyMatch = text.match(/(?:company|organization|employer):\s*([^,\n]+)/i);
            const company = companyMatch ? companyMatch[1].trim() : null;
            
            // Try to extract match score
            const scoreMatch = text.match(/(\d+)%?\s*match/i);
            const matchScore = scoreMatch ? parseInt(scoreMatch[1]) : 85;
            
            return {
                title: title,
                company: company,
                matchScore: matchScore
            };
        }
        
        /**
         * Add salary visualization
         */
        addSalaryVisualization($message) {
            const salaryData = this.extractSalaryData($message);
            
            if (!salaryData.min) return;
            
            const visualHtml = `
                <div class="salary-visual">
                    <div class="chart-header">
                        <span class="chart-title">💰 Salary Range</span>
                    </div>
                    <div class="salary-bar" style="width: ${salaryData.percentage}%">
                        $${salaryData.min.toLocaleString()} - $${salaryData.max.toLocaleString()}
                    </div>
                    <div class="salary-labels">
                        <span>Entry Level</span>
                        <span>Market Average</span>
                        <span>Senior Level</span>
                    </div>
                </div>
            `;
            
            if (!$message.find('.salary-visual').length) {
                $message.find('.sffc-message-content, .message-text').append(visualHtml);
            }
        }
        
        /**
         * Extract salary data
         */
        extractSalaryData($message) {
            const text = $message.find('.sffc-message-content, .message-text').text();
            
            // Look for salary ranges
            const salaryMatch = text.match(/\$?([\d,]+)\s*[-–]\s*\$?([\d,]+)/);
            
            if (salaryMatch) {
                const min = parseInt(salaryMatch[1].replace(/,/g, ''));
                const max = parseInt(salaryMatch[2].replace(/,/g, ''));
                
                // Calculate percentage for visualization (assume $200k is max)
                const percentage = Math.min((max / 200000) * 100, 100);
                
                return {
                    min: min,
                    max: max,
                    percentage: percentage
                };
            }
            
            return { min: null, max: null, percentage: 0 };
        }
        
        /**
         * Add skills visualization
         */
        addSkillsVisualization($message) {
            const skills = this.extractSkills($message);
            
            if (skills.length === 0) return;
            
            const skillsHtml = skills.map(skill => {
                const isMatched = Math.random() > 0.4; // Simulate match
                return `<div class="skill-badge ${isMatched ? 'matched' : 'missing'}">${skill}</div>`;
            }).join('');
            
            const visualHtml = `
                <div class="visual-card">
                    <div class="chart-header">
                        <span class="chart-title">🎯 Skills Analysis</span>
                    </div>
                    <div class="skills-match-visual">
                        ${skillsHtml}
                    </div>
                </div>
            `;
            
            if (!$message.find('.skills-match-visual').length) {
                $message.find('.sffc-message-content, .message-text').append(visualHtml);
            }
        }
        
        /**
         * Extract skills from message
         */
        extractSkills($message) {
            const text = $message.find('.sffc-message-content, .message-text').text();
            
            // Common finance skills
            const skillKeywords = [
                'Excel', 'Python', 'SQL', 'Financial Modeling',
                'Analysis', 'Reporting', 'Leadership', 'Communication',
                'Problem-solving', 'Tableau', 'PowerBI', 'VBA'
            ];
            
            const foundSkills = [];
            skillKeywords.forEach(skill => {
                if (text.toLowerCase().includes(skill.toLowerCase())) {
                    foundSkills.push(skill);
                }
            });
            
            // Limit to 8 skills for display
            return foundSkills.slice(0, 8);
        }
        
        /**
         * Add Tailor CV buttons to job cards
         */
        addTailorCVButtons() {
            $('.job-card-vogue, .job-card, .opportunity-card').each((index, element) => {
                this.addTailorCVToCard($(element));
            });
            
            // Also watch for new job cards
            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    mutation.addedNodes.forEach((node) => {
                        if ($(node).hasClass('job-card-vogue') || 
                            $(node).hasClass('job-card') || 
                            $(node).hasClass('opportunity-card')) {
                            this.addTailorCVToCard($(node));
                        }
                    });
                });
            });
            
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
        
        /**
         * Add Tailor CV button to a single card
         */
        addTailorCVToCard($card) {
            // Don't add if already exists
            if ($card.find('.cv-tailor-wrapper').length) return;
            
            // Extract job data
            const jobId = $card.data('job-id') || $card.attr('data-id');
            const jobTitle = $card.find('.job-title, h3, .title').first().text();
            const company = $card.find('.company-name, .company').first().text();
            
            const buttonsHtml = `
                <div class="cv-tailor-wrapper">
                    <button class="btn-tailor-cv" 
                            data-job-id="${jobId}"
                            data-job-title="${jobTitle}"
                            data-company="${company}">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 11l3 3L22 4"></path>
                            <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"></path>
                        </svg>
                        Quick Apply
                    </button>
                    <button class="btn-analyze-first"
                            data-job-id="${jobId}"
                            data-job-title="${jobTitle}"
                            data-company="${company}">
                        <span class="icon">📊</span>
                        Analyze
                    </button>
                </div>
            `;
            
            $card.append(buttonsHtml);
        }
        
        /**
         * Handle Tailor CV click
         */
        handleTailorCV(e) {
            const $btn = $(e.currentTarget);
            const jobData = {
                id: $btn.data('job-id'),
                title: $btn.data('job-title'),
                company: $btn.data('company')
            };
            
            console.log('Tailor CV for:', jobData);
            
            // Show loading state
            $btn.html('<span class="spinner"></span> Tailoring...').prop('disabled', true);
            
            // CV Tailoring Engine placeholder
            setTimeout(() => {
                // Show CV tailoring message
                const message = $(`
                    <div class="cv-tailor-popup" style="
                        position: fixed;
                        top: 50%;
                        left: 50%;
                        transform: translate(-50%, -50%);
                        background: linear-gradient(135deg, #00C896 0%, #00A07E 100%);
                        color: white;
                        padding: 25px 35px;
                        border-radius: 15px;
                        box-shadow: 0 15px 40px rgba(0,0,0,0.3);
                        z-index: 10000;
                        text-align: center;
                        max-width: 400px;
                    ">
                        <div style="font-size: 48px; margin-bottom: 15px;">📄</div>
                        <h3 style="margin: 0 0 10px 0; font-size: 24px;">CV Tailoring Engine</h3>
                        <p style="margin: 0 0 5px 0; opacity: 0.95;">Optimize your CV for:</p>
                        <p style="margin: 0; font-weight: bold;">"${jobData.title}"</p>
                        <p style="margin: 5px 0 15px 0; opacity: 0.9;">at ${jobData.company}</p>
                        <div style="background: rgba(255,255,255,0.2); padding: 10px; border-radius: 8px; margin-top: 15px;">
                            <p style="margin: 0; font-size: 14px;">✨ Coming Soon!</p>
                        </div>
                    </div>
                `);
                
                $('body').append(message);
                
                // Update button
                $btn.html('📄 Tailor CV').prop('disabled', false);
                
                // Remove popup after 3 seconds
                setTimeout(() => {
                    message.fadeOut(300, () => message.remove());
                }, 3000);
            }, 500);
        }
        
        /**
         * Handle analyze first click
         */
        handleAnalyzeFirst(e) {
            const $btn = $(e.currentTarget);
            const jobData = {
                id: $btn.data('job-id'),
                title: $btn.data('job-title'),
                company: $btn.data('company')
            };
            
            console.log('Analyzing job:', jobData);
            
            // Show loading state
            $btn.html('<span class="spinner"></span> Analyzing...').prop('disabled', true);
            
            // Trigger normal apply mode with analysis
            // CV Tailoring will handle this
            if (window.CVTailoringEngine) {
                window.CVTailoringEngine.analyze(jobData);
            } else {
                // Trigger MENA Careers conversation about this job
                const message = `Tell me more about the ${jobData.title} position at ${jobData.company}`;
                $('.senna-input').val(message);
                $('.senna-send-btn').click();
            }
            
            // Reset button
            setTimeout(() => {
                $btn.html('<span class="icon">📊</span> Analyze').prop('disabled', false);
            }, 2000);
        }
        
        /**
         * Create loading spinner
         */
        createSpinner() {
            return `
                <style>
                    .spinner {
                        display: inline-block;
                        width: 14px;
                        height: 14px;
                        border: 2px solid rgba(255, 255, 255, 0.3);
                        border-top-color: white;
                        border-radius: 50%;
                        animation: spin 0.8s linear infinite;
                    }
                    @keyframes spin {
                        to { transform: rotate(360deg); }
                    }
                </style>
            `;
        }
    }
    
    // Initialize on document ready
    $(document).ready(() => {
        // Add spinner styles once
        if (!$('#spinner-styles').length) {
            $('head').append(`<style id="spinner-styles">
                .spinner {
                    display: inline-block;
                    width: 14px;
                    height: 14px;
                    border: 2px solid rgba(255, 255, 255, 0.3);
                    border-top-color: white;
                    border-radius: 50%;
                    animation: spin 0.8s linear infinite;
                }
                @keyframes spin {
                    to { transform: rotate(360deg); }
                }
            </style>`);
        }
        
        window.SennaVisualEnhancements = new SennaVisualEnhancements();
    });
    
})(jQuery);