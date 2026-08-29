/**
 * Smart Filters for Career Opportunities
 * AI-powered filtering and suggestions
 */

(function($) {
    'use strict';
    
    class SmartFilters {
        constructor() {
            this.filters = {
                skills: [],
                location: '',
                salaryMin: 0,
                salaryMax: 500000,
                matchScore: 0,
                type: 'all',
                level: 'all',
                industry: 'all'
            };
            
            this.opportunities = [];
            this.init();
        }
        
        init() {
            this.loadOpportunities();
            this.createFilterUI();
            this.bindEvents();
            this.applyAISuggestions();
        }
        
        createFilterUI() {
            const filterHTML = `
                <div class="sffc-smart-filters">
                    <!-- Match Score Filter -->
                    <div class="sffc-filter-dropdown" data-filter="match">
                        <div class="sffc-filter-pill sffc-filter-dropdown-toggle">
                            <span>Match Score</span>
                            <span class="sffc-filter-arrow">▼</span>
                        </div>
                        <div class="sffc-filter-dropdown-menu">
                            <div class="sffc-filter-option" data-value="90">90%+ Exceptional</div>
                            <div class="sffc-filter-option" data-value="80">80%+ Strong</div>
                            <div class="sffc-filter-option" data-value="70">70%+ Good</div>
                            <div class="sffc-filter-option" data-value="0">All Matches</div>
                        </div>
                    </div>
                    
                    <!-- Salary Filter -->
                    <div class="sffc-filter-dropdown" data-filter="salary">
                        <div class="sffc-filter-pill sffc-filter-dropdown-toggle">
                            <span>Salary Range</span>
                            <span class="sffc-filter-arrow">▼</span>
                        </div>
                        <div class="sffc-filter-dropdown-menu">
                            <div class="sffc-salary-filter">
                                <div class="sffc-salary-range">
                                    <span class="sffc-salary-min">$0k</span>
                                    <span class="sffc-salary-max">$500k+</span>
                                </div>
                                <div class="sffc-salary-slider">
                                    <div class="sffc-salary-slider-fill"></div>
                                    <div class="sffc-salary-handle min"></div>
                                    <div class="sffc-salary-handle max"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Location Filter -->
                    <div class="sffc-filter-dropdown" data-filter="location">
                        <div class="sffc-filter-pill sffc-filter-dropdown-toggle">
                            <span>Location</span>
                            <span class="sffc-filter-arrow">▼</span>
                        </div>
                        <div class="sffc-filter-dropdown-menu">
                            <div class="sffc-filter-option" data-value="remote">Remote</div>
                            <div class="sffc-filter-option" data-value="hybrid">Hybrid</div>
                            <div class="sffc-filter-option" data-value="onsite">On-site</div>
                            <div class="sffc-filter-option" data-value="all">All Locations</div>
                        </div>
                    </div>
                    
                    <!-- Skills Filter -->
                    <div class="sffc-filter-dropdown" data-filter="skills">
                        <div class="sffc-filter-pill sffc-filter-dropdown-toggle">
                            <span>Skills Match</span>
                            <span class="sffc-filter-arrow">▼</span>
                        </div>
                        <div class="sffc-filter-dropdown-menu">
                            <div class="sffc-filter-option" data-value="all">All Skills</div>
                            <div class="sffc-filter-option" data-value="leadership">Leadership</div>
                            <div class="sffc-filter-option" data-value="technical">Technical</div>
                            <div class="sffc-filter-option" data-value="analytical">Analytical</div>
                            <div class="sffc-filter-option" data-value="strategic">Strategic</div>
                        </div>
                    </div>
                    
                    <!-- AI Suggestion -->
                    <div class="sffc-filter-pill sffc-ai-suggestion" id="ai-suggestion">
                        <span>Best Matches for You</span>
                    </div>
                    
                    <!-- Clear Filters -->
                    <div class="sffc-filter-pill" id="clear-filters" style="background: rgba(244, 67, 54, 0.1); color: #F44336;">
                        <span>Clear All</span>
                    </div>
                </div>
            `;
            
            // Insert filters after header or at top of opportunities
            if ($('.sffc-opportunities-header').length) {
                $('.sffc-opportunities-header').after(filterHTML);
            } else {
                $('.sffc-opportunities-wrapper').prepend(filterHTML);
            }
        }
        
        bindEvents() {
            const self = this;
            
            // Dropdown toggles
            $('.sffc-filter-dropdown-toggle').on('click', function(e) {
                e.stopPropagation();
                const dropdown = $(this).parent();
                
                // Close other dropdowns
                $('.sffc-filter-dropdown').not(dropdown).removeClass('open');
                
                // Toggle current
                dropdown.toggleClass('open');
            });
            
            // Filter options
            $('.sffc-filter-option').on('click', function() {
                const $option = $(this);
                const $dropdown = $option.closest('.sffc-filter-dropdown');
                const filter = $dropdown.data('filter');
                const value = $option.data('value');
                
                // Update selection
                $dropdown.find('.sffc-filter-option').removeClass('selected');
                $option.addClass('selected');
                
                // Update filter
                self.updateFilter(filter, value);
                
                // Close dropdown
                $dropdown.removeClass('open');
            });
            
            // AI Suggestion
            $('#ai-suggestion').on('click', function() {
                self.applyAIFilters();
            });
            
            // Clear filters
            $('#clear-filters').on('click', function() {
                self.clearFilters();
            });
            
            // Salary sliders
            this.initSalarySlider();
            
            // Close dropdowns on outside click
            $(document).on('click', function() {
                $('.sffc-filter-dropdown').removeClass('open');
            });
        }
        
        initSalarySlider() {
            const self = this;
            let isDragging = false;
            let currentHandle = null;
            
            $('.sffc-salary-handle').on('mousedown', function(e) {
                isDragging = true;
                currentHandle = $(this).hasClass('min') ? 'min' : 'max';
                e.preventDefault();
            });
            
            $(document).on('mousemove', function(e) {
                if (!isDragging) return;
                
                const slider = $('.sffc-salary-slider');
                const rect = slider[0].getBoundingClientRect();
                const x = e.clientX - rect.left;
                const percentage = Math.max(0, Math.min(100, (x / rect.width) * 100));
                
                if (currentHandle === 'min') {
                    const maxPos = parseFloat($('.sffc-salary-handle.max').css('left'));
                    if (percentage < maxPos) {
                        $('.sffc-salary-handle.min').css('left', percentage + '%');
                        self.filters.salaryMin = Math.round((percentage / 100) * 500000);
                    }
                } else {
                    const minPos = parseFloat($('.sffc-salary-handle.min').css('left'));
                    if (percentage > minPos) {
                        $('.sffc-salary-handle.max').css('left', percentage + '%');
                        self.filters.salaryMax = Math.round((percentage / 100) * 500000);
                    }
                }
                
                // Update slider fill
                self.updateSalarySlider();
            });
            
            $(document).on('mouseup', function() {
                if (isDragging) {
                    isDragging = false;
                    currentHandle = null;
                    self.applyFilters();
                }
            });
        }
        
        updateSalarySlider() {
            const minPos = parseFloat($('.sffc-salary-handle.min').css('left'));
            const maxPos = parseFloat($('.sffc-salary-handle.max').css('left'));
            
            $('.sffc-salary-slider-fill').css({
                left: minPos + '%',
                right: (100 - maxPos) + '%'
            });
            
            $('.sffc-salary-min').text('$' + Math.round(this.filters.salaryMin / 1000) + 'k');
            $('.sffc-salary-max').text('$' + Math.round(this.filters.salaryMax / 1000) + 'k+');
        }
        
        updateFilter(type, value) {
            switch(type) {
                case 'match':
                    this.filters.matchScore = parseInt(value) || 0;
                    break;
                case 'location':
                    this.filters.location = value;
                    break;
                case 'skills':
                    this.filters.skills = value === 'all' ? [] : [value];
                    break;
                case 'level':
                    this.filters.level = value;
                    break;
                case 'industry':
                    this.filters.industry = value;
                    break;
            }
            
            this.applyFilters();
        }
        
        applyFilters() {
            const self = this;
            let filtered = this.opportunities;
            
            // Match score filter
            if (this.filters.matchScore > 0) {
                filtered = filtered.filter(job => (job.match_score || 75) >= this.filters.matchScore);
            }
            
            // Salary filter
            filtered = filtered.filter(job => {
                const salary = job.salary_max || job.salary_min || 100000;
                return salary >= this.filters.salaryMin && salary <= this.filters.salaryMax;
            });
            
            // Location filter
            if (this.filters.location && this.filters.location !== 'all') {
                filtered = filtered.filter(job => {
                    const location = (job.location || '').toLowerCase();
                    if (this.filters.location === 'remote') {
                        return location.includes('remote');
                    } else if (this.filters.location === 'hybrid') {
                        return location.includes('hybrid');
                    } else {
                        return !location.includes('remote') && !location.includes('hybrid');
                    }
                });
            }
            
            // Skills filter
            if (this.filters.skills.length > 0) {
                filtered = filtered.filter(job => {
                    const jobSkills = job.skills || [];
                    return this.filters.skills.some(skill => 
                        jobSkills.some(js => js.toLowerCase().includes(skill))
                    );
                });
            }
            
            // Update display
            this.displayFilteredOpportunities(filtered);
            this.updateFilterCount(filtered.length);
        }
        
        applyAIFilters() {
            // AI-powered filter suggestions based on user profile
            const userProfile = this.getUserProfile();
            
            // Set optimal filters based on AI analysis
            this.filters.matchScore = 80; // High matches only
            this.filters.salaryMin = userProfile.targetSalary * 0.9;
            this.filters.salaryMax = userProfile.targetSalary * 1.3;
            this.filters.skills = userProfile.topSkills;
            
            // Update UI
            $('.sffc-filter-option[data-value="80"]').click();
            this.updateSalarySlider();
            
            // Apply and show results
            this.applyFilters();
            
            // Show notification
            this.showNotification('AI filters applied based on your profile', 'success');
        }
        
        clearFilters() {
            this.filters = {
                skills: [],
                location: '',
                salaryMin: 0,
                salaryMax: 500000,
                matchScore: 0,
                type: 'all',
                level: 'all',
                industry: 'all'
            };
            
            // Reset UI
            $('.sffc-filter-option').removeClass('selected');
            $('.sffc-filter-option[data-value="0"], .sffc-filter-option[data-value="all"]').addClass('selected');
            $('.sffc-salary-handle.min').css('left', '0%');
            $('.sffc-salary-handle.max').css('left', '100%');
            this.updateSalarySlider();
            
            // Apply filters
            this.applyFilters();
        }
        
        displayFilteredOpportunities(opportunities) {
            const $grid = $('#opportunities-grid');
            
            if (opportunities.length === 0) {
                $grid.html(`
                    <div class="sffc-no-results">
                        <h3>No opportunities match your filters</h3>
                        <p>Try adjusting your criteria or <a href="#" id="clear-filters-link">clear all filters</a></p>
                    </div>
                `);
                return;
            }
            
            // Re-render opportunities
            $grid.empty();
            opportunities.forEach((job, index) => {
                const card = this.createOpportunityCard(job, index);
                $grid.append(card);
            });
            
            // Re-bind events
            if (window.bindCardEvents) {
                window.bindCardEvents();
            }
        }
        
        createOpportunityCard(job, index) {
            // Use existing card creation logic
            if (window.createOpportunityCard) {
                return window.createOpportunityCard(job, index);
            }
            
            // Fallback simple card
            return `
                <div class="sffc-opportunity-card" data-job-id="${job.id}">
                    <h3>${job.title}</h3>
                    <p>${job.company} - ${job.location}</p>
                    <div class="sffc-match-badge" data-score="${job.match_score || 75}">
                        <div class="sffc-match-strength">
                            <div class="sffc-match-strength-fill"></div>
                        </div>
                        <span class="sffc-match-text">${this.getMatchLevel(job.match_score || 75)}</span>
                    </div>
                </div>
            `;
        }
        
        getMatchLevel(score) {
            if (score >= 90) return 'EXCEPTIONAL';
            if (score >= 80) return 'STRONG';
            if (score >= 70) return 'GOOD';
            return 'MODERATE';
        }
        
        updateFilterCount(count) {
            // Update count display
            const text = count === 1 ? '1 opportunity' : `${count} opportunities`;
            if ($('.sffc-filter-count').length === 0) {
                $('.sffc-smart-filters').append(`<span class="sffc-filter-count">${text}</span>`);
            } else {
                $('.sffc-filter-count').text(text);
            }
        }
        
        loadOpportunities() {
            // Load from localStorage or global variable
            const saved = localStorage.getItem('sffc_opportunities');
            if (saved) {
                this.opportunities = JSON.parse(saved);
            } else if (window.sffc_opportunities) {
                this.opportunities = window.sffc_opportunities;
            }
        }
        
        getUserProfile() {
            // Get user profile for AI suggestions
            return {
                targetSalary: 150000,
                topSkills: ['leadership', 'strategic'],
                preferredLocation: 'remote',
                experienceLevel: 'senior'
            };
        }
        
        showNotification(message, type = 'info') {
            const notification = $(`
                <div class="sffc-notification sffc-notification-${type}">
                    ${message}
                </div>
            `);
            
            $('body').append(notification);
            
            setTimeout(() => {
                notification.addClass('show');
            }, 100);
            
            setTimeout(() => {
                notification.removeClass('show');
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }
        
        applyAISuggestions() {
            // Analyze user behavior and suggest filters
            const userBehavior = this.analyzeUserBehavior();
            
            if (userBehavior.preferenceSalary > 0) {
                // Suggest salary range based on viewed jobs
                $('#ai-suggestion').attr('title', `Suggested: $${Math.round(userBehavior.preferenceSalary/1000)}k+ roles`);
            }
        }
        
        analyzeUserBehavior() {
            // Analyze viewed and saved jobs
            const viewedJobs = JSON.parse(localStorage.getItem('sffc_viewed_jobs') || '[]');
            const savedJobs = JSON.parse(localStorage.getItem('sffc_saved_jobs') || '[]');
            
            let avgSalary = 0;
            let preferredSkills = [];
            
            [...viewedJobs, ...savedJobs].forEach(job => {
                if (job.salary_max) {
                    avgSalary += job.salary_max;
                }
                if (job.skills) {
                    preferredSkills.push(...job.skills);
                }
            });
            
            return {
                preferenceSalary: avgSalary / (viewedJobs.length + savedJobs.length) || 0,
                preferredSkills: [...new Set(preferredSkills)].slice(0, 5)
            };
        }
    }
    
    // Initialize filters when ready
    $(document).ready(function() {
        // Only initialize on opportunities page
        if ($('.sffc-opportunities-wrapper').length || $('#opportunities-grid').length) {
            window.smartFilters = new SmartFilters();
        }
    });
    
})(jQuery);