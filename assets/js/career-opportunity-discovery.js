/**
 * Career Opportunity Discovery - Phase 2 Enhancement
 * Live job cards with real matching, explanations, and preference tracking
 */

(function() {
    'use strict';
    
    // Component: OpportunityFeed - Infinite scroll of matched jobs
    class OpportunityFeed {
        constructor(container) {
            this.container = container;
            this.currentPage = 0;
            this.isLoading = false;
            this.hasMore = true;
            this.jobs = [];
            this.observer = null;
            
            this.init();
        }
        
        init() {
            this.setupInfiniteScroll();
            this.loadOpportunities();
        }
        
        setupInfiniteScroll() {
            // Check if sentinel already exists
            let sentinel = document.getElementById('opportunity-sentinel');
            if (!sentinel) {
                // Create sentinel element for intersection observer
                sentinel = document.createElement('div');
                sentinel.id = 'opportunity-sentinel';
                sentinel.style.height = '1px';
                sentinel.style.visibility = 'hidden';
                this.container.appendChild(sentinel);
            }
            
            // Set up intersection observer for infinite scroll
            this.observer = new IntersectionObserver((entries) => {
                if (entries[0].isIntersecting && !this.isLoading && this.hasMore) {
                    this.loadMoreOpportunities();
                }
            }, {
                rootMargin: '100px'
            });
            
            this.observer.observe(sentinel);
        }
        
        async loadOpportunities(append = false) {
            if (this.isLoading) return;
            
            this.isLoading = true;
            this.showLoadingState(!append);
            
            try {
                const response = await this.fetchOpportunities();
                
                if (response.success && response.data) {
                    const opportunities = response.data.opportunities || [];
                    const total = response.data.total || 0;
                    
                    if (append) {
                        this.jobs = [...this.jobs, ...opportunities];
                    } else {
                        this.jobs = opportunities;
                    }
                    
                    this.renderOpportunities(opportunities, append);
                    this.hasMore = total > this.jobs.length;
                    
                    // Track view events for preference learning
                    opportunities.forEach(job => {
                        PreferenceTracker.trackView(job.id, job);
                    });
                }
            } catch (error) {
                console.error('Failed to load opportunities:', error);
                this.showError('Unable to load opportunities. Please try again.');
            } finally {
                this.isLoading = false;
                this.hideLoadingState();
            }
        }
        
        async fetchOpportunities() {
            const params = new URLSearchParams({
                action: 'sffc_get_opportunities',
                nonce: window.sffc_ajax?.nonce || '',
                limit: 9,
                offset: this.currentPage * 9,
                with_matching: true  // Request match explanations
            });
            
            const response = await fetch(window.sffc_ajax?.url || '/wp-admin/admin-ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: params.toString()
            });
            
            return await response.json();
        }
        
        renderOpportunities(opportunities, append) {
            const fragment = document.createDocumentFragment();
            
            opportunities.forEach((job, index) => {
                const card = new OpportunityCard(job, index);
                fragment.appendChild(card.render());
            });
            
            if (!append) {
                // Clear existing cards except sentinel
                const sentinel = document.getElementById('opportunity-sentinel');
                if (sentinel) {
                    // Remove all children except sentinel
                    while (this.container.firstChild && this.container.firstChild !== sentinel) {
                        this.container.removeChild(this.container.firstChild);
                    }
                } else {
                    this.container.innerHTML = '';
                }
            }
            
            // Insert cards before sentinel
            const sentinel = document.getElementById('opportunity-sentinel');
            if (sentinel) {
                this.container.insertBefore(fragment, sentinel);
            } else {
                this.container.appendChild(fragment);
            }
        }
        
        loadMoreOpportunities() {
            this.currentPage++;
            this.loadOpportunities(true);
        }
        
        showLoadingState(clear = false) {
            if (clear) {
                const loader = document.createElement('div');
                loader.className = 'opportunity-loader';
                loader.innerHTML = `
                    <div class="loader-spinner"></div>
                    <p>Finding your best matches...</p>
                `;
                this.container.appendChild(loader);
            }
        }
        
        hideLoadingState() {
            const loader = this.container.querySelector('.opportunity-loader');
            if (loader) {
                loader.remove();
            }
        }
        
        showError(message) {
            this.container.innerHTML = `
                <div class="opportunity-error">
                    <p>${message}</p>
                    <button onclick="location.reload()">Retry</button>
                </div>
            `;
        }
    }
    
    // Component: OpportunityCard - Interactive job display with match explanation
    class OpportunityCard {
        constructor(job, index) {
            this.job = job;
            this.index = index;
            this.element = null;
        }
        
        render() {
            const div = document.createElement('article');
            div.className = 'opportunity-card enhanced';
            div.dataset.jobId = this.job.id;
            div.style.animationDelay = `${this.index * 0.1}s`;
            
            // Enhanced match scoring with explanations
            const matchData = this.calculateMatchData();
            
            div.innerHTML = `
                <div class="opportunity-header">
                    <div class="company-info">
                        <div class="company-logo-wrapper">
                            <div class="company-logo">${this.getCompanyInitial()}</div>
                        </div>
                        <div class="company-details">
                            <h3 class="job-title">${this.job.title}</h3>
                            <div class="company-name">${this.job.company}</div>
                        </div>
                    </div>
                    <div class="match-indicator ${matchData.level}">
                        <div class="match-score">${matchData.score}%</div>
                        <div class="match-label">${matchData.label}</div>
                    </div>
                </div>
                
                <div class="job-meta">
                    <span class="meta-item location">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                            <circle cx="12" cy="10" r="3"></circle>
                        </svg>
                        ${this.job.location}
                    </span>
                    <span class="meta-item salary">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="1" x2="12" y2="23"></line>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                        </svg>
                        ${this.formatSalary()}
                    </span>
                    <span class="meta-item type">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                            <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                        </svg>
                        ${this.job.job_type || 'Full-time'}
                    </span>
                </div>
                
                ${this.renderMatchExplanation(matchData)}
                
                <div class="job-description">
                    ${this.getJobSummary()}
                </div>
                
                ${this.renderSkillsMatch()}
                
                <div class="card-actions">
                    ${this.renderQuickActions()}
                </div>
            `;
            
            this.element = div;
            this.attachEventListeners();
            
            return div;
        }
        
        calculateMatchData() {
            // Enhanced match calculation with explanation
            // Check if user is logged in
            const isLoggedIn = window.sffc_frontend?.is_logged_in === '1';
            
            // If not logged in, show login prompt instead of fake score
            if (!isLoggedIn) {
                return {
                    score: 0,
                    level: 'login-required',
                    label: 'Login to see match',
                    reasons: ['Create profile to see match score', 'Get personalized analysis', 'Track your applications'],
                    requiresLogin: true
                };
            }
            
            // Use actual match score from backend or calculate based on profile
            let score = this.job.match_score;
            
            // If no backend score, calculate from user profile
            if (!score && window.sffc_user_profile) {
                score = this.calculateActualMatchScore();
            }
            
            // Fallback to 0 if still no score (indicates missing data)
            if (!score) {
                score = 0;
            }
            
            let level = 'potential';
            let label = 'Potential Match';
            let reasons = [];
            
            if (score >= 90) {
                level = 'exceptional';
                label = 'Exceptional Fit';
                reasons = ['Perfect skill alignment', 'Ideal career progression', 'Strong culture fit'];
            } else if (score >= 80) {
                level = 'strong';
                label = 'Strong Match';
                reasons = ['High skill overlap', 'Good career step', 'Location matches preference'];
            } else if (score >= 70) {
                level = 'good';
                label = 'Good Alignment';
                reasons = ['Relevant experience valued', 'Growth opportunity', 'Competitive compensation'];
            } else {
                reasons = ['Transferable skills', 'New sector opportunity', 'Expand your network'];
            }
            
            return { score, level, label, reasons };
        }
        
        calculateActualMatchScore() {
            // Calculate real match score based on user profile
            const profile = JSON.parse(localStorage.getItem('sffc_user_profile') || '{}');
            if (!profile || !profile.skills) return 0;
            
            let score = 0;
            let factors = 0;
            
            // Skills match (40% weight)
            if (this.job.required_skills && profile.skills) {
                const jobSkills = this.job.required_skills.map(s => s.toLowerCase());
                const userSkills = profile.skills.map(s => s.toLowerCase());
                const matched = userSkills.filter(skill => jobSkills.includes(skill));
                const skillMatch = (matched.length / Math.max(jobSkills.length, 1)) * 100;
                score += skillMatch * 0.4;
                factors++;
            }
            
            // Experience match (30% weight)
            if (this.job.experience_required && profile.years_experience) {
                const reqExp = parseInt(this.job.experience_required);
                const userExp = parseInt(profile.years_experience);
                if (!isNaN(reqExp) && !isNaN(userExp)) {
                    const expMatch = userExp >= reqExp ? 100 : (userExp / reqExp) * 80;
                    score += Math.min(expMatch, 100) * 0.3;
                    factors++;
                }
            }
            
            // Location match (15% weight)
            if (this.job.location && profile.preferred_locations) {
                const jobLocation = this.job.location.toLowerCase();
                const locMatch = profile.preferred_locations.some(loc => 
                    jobLocation.includes(loc.toLowerCase())
                );
                score += (locMatch ? 100 : 50) * 0.15;
                factors++;
            }
            
            // Salary expectations (15% weight)
            if (this.job.salary_min && profile.salary_expectations) {
                const jobSalary = parseInt(this.job.salary_min);
                const expectedSalary = parseInt(profile.salary_expectations);
                if (!isNaN(jobSalary) && !isNaN(expectedSalary)) {
                    const salaryMatch = jobSalary >= expectedSalary ? 100 : 
                                       (jobSalary / expectedSalary) * 80;
                    score += Math.min(salaryMatch, 100) * 0.15;
                    factors++;
                }
            }
            
            // Return weighted average or 0 if no factors
            return factors > 0 ? Math.round(score) : 0;
        }
        
        renderMatchExplanation(matchData) {
            if (!matchData.reasons || matchData.reasons.length === 0) return '';
            
            // Special handling for login required
            if (matchData.requiresLogin) {
                return `
                    <div class="match-explanation login-required">
                        <div class="explanation-title">Get your personalized match score:</div>
                        <ul class="match-reasons">
                            ${matchData.reasons.map(reason => `
                                <li class="match-reason">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                                    </svg>
                                    ${reason}
                                </li>
                            `).join('')}
                        </ul>
                        <button class="btn-login-cta" onclick="window.location.href='/login/'">
                            Login to reveal match score
                        </button>
                    </div>
                `;
            }
            
            return `
                <div class="match-explanation">
                    <div class="explanation-title">Why this matches:</div>
                    <ul class="match-reasons">
                        ${matchData.reasons.map(reason => `
                            <li class="match-reason">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                                </svg>
                                ${reason}
                            </li>
                        `).join('')}
                    </ul>
                </div>
            `;
        }
        
        renderSkillsMatch() {
            const skills = this.job.skills || [];
            if (skills.length === 0) return '';
            
            // Show top 5 skills
            const topSkills = skills.slice(0, 5);
            const remainingCount = skills.length - 5;
            
            return `
                <div class="skills-match">
                    <div class="skills-list">
                        ${topSkills.map(skill => `
                            <span class="skill-tag">${skill}</span>
                        `).join('')}
                        ${remainingCount > 0 ? `
                            <span class="skill-more">+${remainingCount} more</span>
                        ` : ''}
                    </div>
                </div>
            `;
        }
        
        renderQuickActions() {
            return `
                <button class="action-btn btn-interested" 
                        data-action="interested">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                    </svg>
                    <span>Interested</span>
                </button>
                
                <button class="action-btn btn-analyze" data-action="analyze">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="m21 21-4.35-4.35"></path>
                    </svg>
                    <span>Analyze Fit</span>
                </button>
                
                <button class="action-btn btn-pass" data-action="pass">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                    <span>Not Now</span>
                </button>
            `;
        }
        
        attachEventListeners() {
            if (!this.element) return;
            
            // Track time spent viewing
            let viewStartTime = Date.now();
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        viewStartTime = Date.now();
                    } else {
                        const timeSpent = Date.now() - viewStartTime;
                        PreferenceTracker.trackTimeSpent(this.job.id, timeSpent);
                    }
                });
            });
            
            observer.observe(this.element);
            
            // Action button handlers
            this.element.querySelectorAll('.action-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const action = btn.dataset.action;
                    this.handleAction(action, btn);
                });
            });
            
            // Card click for detail view
            this.element.addEventListener('click', () => {
                this.openDetailView();
            });
        }
        
        handleAction(action, button) {
            switch(action) {
                case 'interested':
                    this.handleInterested(button);
                    break;
                case 'analyze':
                    this.handleAnalyze();
                    break;
                case 'pass':
                    this.handlePass(button);
                    break;
            }
        }
        
        handleInterested(button) {
            const isActive = button.classList.contains('active');
            
            if (!isActive) {
                button.classList.add('active');
                button.querySelector('span').textContent = 'Saved';
                
                // Track preference
                PreferenceTracker.trackAction(this.job.id, 'interested', this.job);
                
                // Show confirmation
                this.showToast('Added to your saved list');
            } else {
                button.classList.remove('active');
                button.querySelector('span').textContent = 'Interested';
            }
        }
        
        handleAnalyze() {
            // Open MENA Careers AI with context about this job
            if (window.SennaChat) {
                window.SennaChat.open({
                    context: 'job_analysis',
                    job: this.job,
                    prompt: `Analyze this ${this.job.title} role at ${this.job.company} for me`
                });
            } else {
                this.showToast('Opening analysis...');
            }
            
            // Track preference
            PreferenceTracker.trackAction(this.job.id, 'analyze', this.job);
        }
        
        handlePass(button) {
            button.classList.add('passed');
            button.disabled = true;
            button.querySelector('span').textContent = 'Passed';
            
            // Fade out card
            this.element.style.opacity = '0.5';
            
            // Track preference
            PreferenceTracker.trackAction(this.job.id, 'pass', this.job);
            
            this.showToast('We\'ll show fewer roles like this');
        }
        
        openDetailView() {
            // Future: Open detailed job view modal
            console.log('Opening detail view for job:', this.job.id);
        }
        
        getCompanyInitial() {
            return this.job.company.charAt(0).toUpperCase();
        }
        
        formatSalary() {
            if (this.job.salary_display) {
                return this.job.salary_display;
            }
            
            const min = this.job.salary_min;
            const max = this.job.salary_max;
            
            if (!min && !max) return 'Competitive';
            if (!max || max === min) return '$' + this.formatNumber(min) + '+';
            if (!min) return 'Up to $' + this.formatNumber(max);
            return '$' + this.formatNumber(min) + ' - $' + this.formatNumber(max);
        }
        
        formatNumber(num) {
            if (num >= 1000) {
                return Math.round(num / 1000) + 'k';
            }
            return num.toString();
        }
        
        getJobSummary() {
            if (this.job.description) {
                // Extract first 150 characters
                const summary = this.job.description.substring(0, 150);
                return summary + (this.job.description.length > 150 ? '...' : '');
            }
            return 'Discover how this role aligns with your career aspirations and professional growth objectives.';
        }
        
        showToast(message) {
            const toast = document.createElement('div');
            toast.className = 'opportunity-toast';
            toast.textContent = message;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.classList.add('show');
            }, 10);
            
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 2000);
        }
    }
    
    // Component: PreferenceTracker - Learn from user interactions
    class PreferenceTracker {
        static trackView(jobId, jobData) {
            this.sendTracking('view', jobId, jobData);
        }
        
        static trackTimeSpent(jobId, timeMs) {
            if (timeMs > 3000) { // Only track if viewed for more than 3 seconds
                this.sendTracking('time_spent', jobId, { time_ms: timeMs });
            }
        }
        
        static trackAction(jobId, action, jobData) {
            this.sendTracking(action, jobId, jobData);
        }
        
        static async sendTracking(eventType, jobId, data) {
            try {
                const params = new URLSearchParams({
                    action: 'sffc_track_preference',
                    nonce: window.sffc_ajax?.nonce || '',
                    event_type: eventType,
                    job_id: jobId,
                    data: JSON.stringify(data)
                });
                
                await fetch(window.sffc_ajax?.url || '/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: params.toString()
                });
            } catch (error) {
                console.error('Failed to track preference:', error);
            }
        }
    }
    
    
    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        // Check if we should use the enhanced version
        const container = document.getElementById('opportunities-grid');
        
        // Only initialize if container exists and simple version isn't already loaded
        // or if explicitly requested via data attribute
        if (container && (container.dataset.enhanced === 'true' || !window.jQuery)) {
            console.log('Initializing enhanced opportunity discovery...');
            
            // Clear any existing content from simple version
            if (container.children.length > 0 && !document.getElementById('opportunity-sentinel')) {
                container.innerHTML = '';
            }
            
            // Initialize the enhanced opportunity feed
            window.opportunityFeed = new OpportunityFeed(container);
            
            // Initialization complete
        } else if (container) {
            console.log('Using simple opportunities display (enhanced discovery available)');
        }
    });
    
    // Export for use in other components
    window.OpportunityDiscovery = {
        OpportunityFeed,
        OpportunityCard,
        PreferenceTracker
    };
    
})();