/**
 * PE Job Comparison JavaScript
 * Version: 1.0.0
 * Description: Job comparison functionality for PE opportunities (New implementation)
 */

(function($) {
    'use strict';

    class PEJobComparison {
        constructor() {
            this.selectedJobs = new Map();
            this.maxCompare = 3;
            this.isInitialized = false;
            this.comparisonTableLoaded = false;
            this.tableGenerationOptimized = false;
            
            // Performance: Lazy load comparison functionality
            this.comparisonModule = null;
            
            // Wait for DOM ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.init());
            } else {
                this.init();
            }
        }

        init() {
            console.log('PE Job Comparison: Initializing...');
            
            // Initialize after a short delay to ensure job cards are loaded
            setTimeout(() => {
                this.initializeComparison();
            }, 1000);
            
            // Watch for new job cards
            this.observeJobCards();
        }

        initializeComparison() {
            this.isInitialized = true;
            
            // Add selection checkboxes to existing cards
            this.addSelectionCheckboxes();
            
            // Bind events
            this.bindSelectionEvents();
            
            // Update compare button
            this.updateCompareButton();
        }

        addSelectionCheckboxes() {
            // Target job cards in the chat container specifically
            const chatContainer = document.querySelector('.job-cards-in-chat');
            const jobCards = chatContainer ? 
                chatContainer.querySelectorAll('.job-card-vogue') : 
                document.querySelectorAll('.job-card-vogue');
            
            jobCards.forEach(card => {
                // Check if checkbox already exists
                if (!card.querySelector('.select-checkbox')) {
                    // Get job data
                    const jobButton = card.querySelector('[data-job]');
                    let jobData = {};
                    
                    if (jobButton) {
                        try {
                            jobData = JSON.parse(jobButton.dataset.job.replace(/&apos;/g, "'"));
                        } catch (e) {
                            console.warn('Could not parse job data for comparison:', e);
                        }
                    }
                    
                    // Create checkbox
                    const checkbox = document.createElement('div');
                    checkbox.className = 'select-checkbox';
                    checkbox.dataset.jobId = card.dataset.jobId || jobData.id || Math.random().toString(36).substr(2, 9);
                    checkbox.dataset.job = JSON.stringify(jobData);
                    
                    // Insert at the beginning of the card
                    card.insertBefore(checkbox, card.firstChild);
                }
            });
        }

        bindSelectionEvents() {
            // Use event delegation for dynamic content
            document.addEventListener('click', (e) => {
                if (e.target.classList.contains('select-checkbox')) {
                    this.handleSelection(e.target);
                }
            });
            
            // Compare button
            const compareBtn = document.getElementById('pe-compare-btn');
            if (compareBtn) {
                compareBtn.addEventListener('click', () => this.showComparison());
            }
        }

        handleSelection(checkbox) {
            try {
                const jobId = checkbox.dataset.jobId;
                const jobCard = checkbox.closest('.job-card-vogue');
                
                if (this.selectedJobs.has(jobId)) {
                    // Deselect
                    this.selectedJobs.delete(jobId);
                    checkbox.classList.remove('selected');
                    jobCard.classList.remove('selected');
                } else if (this.selectedJobs.size < this.maxCompare) {
                    // Select
                    let jobData = {};
                    try {
                        jobData = JSON.parse(checkbox.dataset.job);
                    } catch (e) {
                        // Fallback to extracting from DOM
                        jobData = this.extractJobDataFromCard(jobCard);
                    }
                    
                    this.selectedJobs.set(jobId, jobData);
                    checkbox.classList.add('selected');
                    checkbox.classList.add('visible');
                    jobCard.classList.add('selected');
                } else {
                    // Show max selection message
                    this.showMaxSelectionMessage();
                }
                
                this.updateCompareButton();
            } catch (error) {
                console.error('PE Job Comparison: Selection error:', error);
                this.showMessage('Failed to select job. Please try again.');
            }
        }

        extractJobDataFromCard(card) {
            return {
                id: card.dataset.jobId,
                title: card.querySelector('.vogue-title')?.textContent || '',
                company: card.querySelector('.vogue-company')?.textContent || '',
                location: card.querySelector('.vogue-meta-item')?.textContent || '',
                salary: card.querySelector('.vogue-meta-item:nth-child(2)')?.textContent || '',
                match_score: card.querySelector('.vogue-match-score')?.textContent || ''
            };
        }

        updateCompareButton() {
            const compareBtn = document.getElementById('pe-compare-btn');
            if (!compareBtn) return;
            
            const count = this.selectedJobs.size;
            const countElement = compareBtn.querySelector('.pe-compare-count');
            
            if (countElement) {
                countElement.textContent = count;
            }
            
            // Enable/disable button
            compareBtn.disabled = count < 2;
            
            // Update button style
            if (count >= 2) {
                compareBtn.classList.add('ready');
            } else {
                compareBtn.classList.remove('ready');
            }
        }

        // Public method for compare button
        compareJobs() {
            console.log('PE Job Comparison: compareJobs called with', this.selectedJobs.size, 'jobs');
            this.showComparison();
        }
        
        async showComparison() {
            if (this.selectedJobs.size < 2) {
                this.showMessage('Please select at least 2 jobs to compare');
                return;
            }
            
            // Performance: Lazy load comparison table generation
            if (!this.comparisonTableLoaded) {
                await this.loadComparisonModule();
            }
            
            // Show loading indicator
            this.showLoadingIndicator();
            
            // Use requestAnimationFrame for smooth rendering
            requestAnimationFrame(() => {
                // Generate comparison table with optimized rendering
                const comparisonHTML = this.generateOptimizedComparisonTable();
                
                // Insert into chat
                this.insertComparisonIntoChat(comparisonHTML);
                
                // Hide loading indicator
                this.hideLoadingIndicator();
                
                // Scroll to comparison
                this.scrollToComparison();
                
                // Clear selections
                this.clearSelections();
            });
        }
        
        async loadComparisonModule() {
            // Simulate lazy loading of heavy comparison logic
            return new Promise((resolve) => {
                this.comparisonTableLoaded = true;
                console.log('PE Job Comparison: Comparison module loaded');
                resolve();
            });
        }
        
        showLoadingIndicator() {
            const indicator = document.createElement('div');
            indicator.id = 'comparison-loading';
            indicator.className = 'comparison-loading-indicator';
            indicator.style.cssText = `
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: rgba(255, 255, 255, 0.95);
                padding: 20px 40px;
                border-radius: 12px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.15);
                z-index: 10000;
                font-size: 14px;
                color: #333;
            `;
            indicator.innerHTML = `
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div class="spinner" style="
                        width: 20px;
                        height: 20px;
                        border: 3px solid #f3f3f3;
                        border-top: 3px solid #2D6A4F;
                        border-radius: 50%;
                        animation: spin 1s linear infinite;
                    "></div>
                    <span>Generating comparison...</span>
                </div>
            `;
            document.body.appendChild(indicator);
        }
        
        hideLoadingIndicator() {
            const indicator = document.getElementById('comparison-loading');
            if (indicator) {
                indicator.remove();
            }
        }
        
        generateOptimizedComparisonTable() {
            // Use optimized table generation
            if (!this.tableGenerationOptimized) {
                this.optimizeTableGeneration();
            }
            return this.generateComparisonTable();
        }
        
        optimizeTableGeneration() {
            this.tableGenerationOptimized = true;
            // Add any specific optimizations for table generation
        }

        generateComparisonTable() {
            const jobs = Array.from(this.selectedJobs.values());
            
            // Build comparison HTML
            let html = `
                <div class="comparison-table-wrapper">
                    <div class="comparison-header">
                        <h3 class="comparison-title">Opportunity Comparison</h3>
                        <button class="close-comparison" onclick="this.closest('.comparison-message').remove()">×</button>
                    </div>
                    <div class="comparison-grid">
            `;
            
            // Header row
            html += '<div class="comparison-cell comparison-label">CRITERIA</div>';
            jobs.forEach(job => {
                html += `
                    <div class="comparison-cell comparison-header-cell">
                        <div style="font-size: 15px; margin-bottom: 4px; font-weight: 600;">${job.title || 'Position'}</div>
                        <div style="font-size: 12px; color: #666;">${job.company || 'Company'}</div>
                    </div>
                `;
            });
            
            // Comparison criteria
            const criteria = [
                { label: 'Location', key: 'location' },
                { label: 'Salary Range', key: 'salary' },
                { label: 'Match Score', key: 'match_score' },
                { label: 'Work Style', key: 'work_style', default: 'Not specified' },
                { label: 'Fund Size', key: 'fund_size', default: 'Not specified' },
                { label: 'Geo Focus', key: 'geo_focus', default: 'Not specified' },
                { label: 'Seniority', key: 'seniority', default: this.extractSeniority },
                { label: 'Industry Focus', key: 'industry', default: 'Not specified' }
            ];
            
            criteria.forEach(criterion => {
                html += `<div class="comparison-cell comparison-label">${criterion.label}</div>`;
                
                jobs.forEach((job, index) => {
                    let value = job[criterion.key];
                    
                    // Handle defaults
                    if (!value && criterion.default) {
                        if (typeof criterion.default === 'function') {
                            value = criterion.default(job);
                        } else {
                            value = criterion.default;
                        }
                    }
                    
                    // Determine if this is the "better" value
                    const isBetter = this.isBetterValue(criterion.key, value, jobs);
                    
                    html += `
                        <div class="comparison-cell comparison-value ${isBetter ? 'highlight-better' : ''}">
                            ${value || 'N/A'}
                        </div>
                    `;
                });
            });
            
            html += `
                    </div>
                </div>
            `;
            
            return html;
        }

        extractSeniority(job) {
            const title = (job.title || '').toLowerCase();
            
            if (title.includes('partner')) return 'Partner Level';
            if (title.includes('director') || title.includes('md')) return 'Director/MD';
            if (title.includes('principal')) return 'Principal';
            if (title.includes('vp') || title.includes('vice president')) return 'VP';
            if (title.includes('associate')) return 'Associate';
            if (title.includes('analyst')) return 'Analyst';
            
            return 'Not specified';
        }

        isBetterValue(key, value, allJobs) {
            // Simple heuristics for highlighting "better" values
            if (key === 'match_score') {
                const scores = allJobs.map(j => parseInt(j.match_score) || 0);
                const maxScore = Math.max(...scores);
                return parseInt(value) === maxScore;
            }
            
            if (key === 'salary') {
                // Extract numeric value from salary string
                const nums = allJobs.map(j => {
                    const match = (j.salary || '').match(/[\d,]+/);
                    return match ? parseInt(match[0].replace(/,/g, '')) : 0;
                });
                const maxSalary = Math.max(...nums);
                const thisNum = (value || '').match(/[\d,]+/);
                const thisSalary = thisNum ? parseInt(thisNum[0].replace(/,/g, '')) : 0;
                return thisSalary === maxSalary;
            }
            
            return false;
        }

        insertComparisonIntoChat(html) {
            // Try multiple selectors for the chat container
            const chatContainer = document.getElementById('senna-messages') || 
                                document.querySelector('.sffc-messages-container') || 
                                document.querySelector('.senna-messages');
            
            if (!chatContainer) {
                console.warn('Chat container not found');
                return;
            }
            
            // Create message wrapper
            const messageWrapper = document.createElement('div');
            messageWrapper.className = 'sffc-message assistant-message comparison-message';
            messageWrapper.innerHTML = `
                <div class="message-content">
                    <div class="message-header">
                        <div class="assistant-avatar">S</div>
                        <div class="message-info">
                            <span class="assistant-name">MENA Careers</span>
                            <span class="message-time">${new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>
                        </div>
                    </div>
                    ${html}
                </div>
            `;
            
            // Append to chat
            chatContainer.appendChild(messageWrapper);
            
            // Animate entrance
            setTimeout(() => {
                messageWrapper.style.opacity = '0';
                messageWrapper.style.transform = 'translateY(20px)';
                messageWrapper.style.transition = 'all 0.3s ease';
                
                setTimeout(() => {
                    messageWrapper.style.opacity = '1';
                    messageWrapper.style.transform = 'translateY(0)';
                }, 50);
            }, 0);
        }

        scrollToComparison() {
            const chatContainer = document.getElementById('senna-messages') || 
                                document.querySelector('.sffc-messages-container');
            if (chatContainer) {
                setTimeout(() => {
                    chatContainer.scrollTop = chatContainer.scrollHeight;
                    // Also try to scroll the comparison into view
                    const comparison = chatContainer.querySelector('.comparison-message:last-child');
                    if (comparison) {
                        comparison.scrollIntoView({ behavior: 'smooth', block: 'end' });
                    }
                }, 300);
            }
        }

        clearSelections() {
            // Clear selected jobs
            this.selectedJobs.clear();
            
            // Remove selection UI - target chat container specifically
            const chatContainer = document.querySelector('.job-cards-in-chat');
            const checkboxes = chatContainer ? 
                chatContainer.querySelectorAll('.select-checkbox.selected') : 
                document.querySelectorAll('.select-checkbox.selected');
            
            checkboxes.forEach(checkbox => {
                checkbox.classList.remove('selected');
                checkbox.classList.remove('visible');
            });
            
            const selectedCards = chatContainer ?
                chatContainer.querySelectorAll('.job-card-vogue.selected') :
                document.querySelectorAll('.job-card-vogue.selected');
                
            selectedCards.forEach(card => {
                card.classList.remove('selected');
            });
            
            // Update button
            this.updateCompareButton();
        }

        showMaxSelectionMessage() {
            const message = `You can compare up to ${this.maxCompare} jobs at once. Please deselect a job first.`;
            this.showMessage(message);
        }

        showMessage(text) {
            // Create temporary message
            const messageEl = document.createElement('div');
            messageEl.className = 'pe-filter-error';
            messageEl.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: #FFF3CD;
                color: #856404;
                padding: 12px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                z-index: 10000;
                animation: slideInUp 0.3s ease;
            `;
            messageEl.textContent = text;
            
            document.body.appendChild(messageEl);
            
            // Remove after 3 seconds
            setTimeout(() => {
                messageEl.style.animation = 'slideOutDown 0.3s ease';
                setTimeout(() => messageEl.remove(), 300);
            }, 3000);
        }

        observeJobCards() {
            // Watch for new job cards being added
            const observer = new MutationObserver((mutations) => {
                let hasNewCards = false;
                
                mutations.forEach(mutation => {
                    mutation.addedNodes.forEach(node => {
                        if (node.nodeType === 1) { // Element node
                            if (node.classList && node.classList.contains('job-card-vogue')) {
                                hasNewCards = true;
                            }
                            if (node.classList && node.classList.contains('job-cards-in-chat')) {
                                hasNewCards = true;
                            }
                            if (node.querySelectorAll) {
                                const cards = node.querySelectorAll('.job-card-vogue');
                                if (cards.length > 0) hasNewCards = true;
                            }
                        }
                    });
                });
                
                if (hasNewCards) {
                    // Add checkboxes to new cards
                    setTimeout(() => {
                        this.addSelectionCheckboxes();
                    }, 100);
                }
            });
            
            // Observe the chat container and other potential containers
            const containers = [
                '.sffc-messages-container',
                '.job-cards-in-chat',
                '.sffc-opportunities-wrapper'
            ];
            
            containers.forEach(selector => {
                const container = document.querySelector(selector);
                if (container) {
                    observer.observe(container, {
                        childList: true,
                        subtree: true
                    });
                }
            });
        }

        // Public API
        getSelectedJobs() {
            return Array.from(this.selectedJobs.values());
        }

        clearAll() {
            this.clearSelections();
        }

        selectJob(jobId, jobData) {
            if (this.selectedJobs.size < this.maxCompare) {
                this.selectedJobs.set(jobId, jobData);
                this.updateCompareButton();
                return true;
            }
            return false;
        }

        deselectJob(jobId) {
            if (this.selectedJobs.has(jobId)) {
                this.selectedJobs.delete(jobId);
                this.updateCompareButton();
                return true;
            }
            return false;
        }
    }

    // Add animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideInUp {
            from {
                transform: translateY(100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOutDown {
            from {
                transform: translateY(0);
                opacity: 1;
            }
            to {
                transform: translateY(100%);
                opacity: 0;
            }
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .comparison-loading-indicator {
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    `;
    document.head.appendChild(style);

    // Initialize
    window.PEJobComparison = PEJobComparison;
    window.peJobComparison = new PEJobComparison();

})(jQuery);