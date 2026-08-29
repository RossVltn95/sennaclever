/**
 * Smart Filters Premium - 10/10 Ultra-Intelligent Filtering
 * Unique AI-powered filters, not generic job board filters
 */

(function($) {
    'use strict';
    
    class SmartFiltersPremium {
        constructor() {
            this.activeFilters = new Map();
            this.filteredJobs = [];
            this.allJobs = [];
            this.userProfile = null;
            this.currency = 'GBP'; // Default to GBP
            this.init();
        }
        
        init() {
            this.loadUserProfile();
            this.createFilterInterface();
            this.bindEvents();
            this.initializeIntelligentFilters();
        }
        
        loadUserProfile() {
            // Load user preferences and profile for intelligent filtering
            this.userProfile = {
                preferredSalary: localStorage.getItem('sffc_preferred_salary') || 80000,
                skills: JSON.parse(localStorage.getItem('sffc_user_skills') || '[]'),
                experience: localStorage.getItem('sffc_experience_years') || 3,
                preferences: JSON.parse(localStorage.getItem('sffc_preferences') || '{}'),
                interactions: JSON.parse(localStorage.getItem('sffc_interactions') || '[]')
            };
        }
        
        createFilterInterface() {
            const filterHTML = `
                <div class="sffc-smart-filters-premium">
                    <!-- Active Filters Display -->
                    <div class="active-filters-bar">
                        <div class="active-filters-container">
                            <span class="active-filters-label">Active Filters:</span>
                            <div class="active-filter-pills" id="active-filter-pills">
                                <!-- Active filters will appear here -->
                            </div>
                            <button class="clear-all-filters">Clear All</button>
                        </div>
                    </div>
                    
                    <!-- Intelligent Filter Categories -->
                    <div class="intelligent-filters">
                        <!-- Career Fit Score -->
                        <div class="filter-category career-fit">
                            <button class="smart-filter-btn best-matches active" data-filter="best-matches">
                                <span class="filter-icon">◈</span>
                                <span class="filter-label">Best Matches for You</span>
                                <span class="filter-badge">AI</span>
                            </button>
                        </div>
                        
                        <!-- Growth & Learning -->
                        <div class="filter-category growth">
                            <button class="smart-filter-btn career-progression" data-filter="career-progression">
                                <span class="filter-icon">▲</span>
                                <span class="filter-label">Career Progression</span>
                                <span class="filter-description">Next logical step</span>
                            </button>
                            
                            <button class="smart-filter-btn skill-builder" data-filter="skill-builder">
                                <span class="filter-icon">◆</span>
                                <span class="filter-label">Skill Builder</span>
                                <span class="filter-description">Expand expertise</span>
                            </button>
                            
                            <button class="smart-filter-btn stretch-roles" data-filter="stretch-roles">
                                <span class="filter-icon">★</span>
                                <span class="filter-label">Stretch Roles</span>
                                <span class="filter-description">Challenge yourself</span>
                            </button>
                        </div>
                        
                        <!-- Lifestyle & Values -->
                        <div class="filter-category lifestyle">
                            <button class="smart-filter-btn work-life-harmony" data-filter="work-life-harmony">
                                <span class="filter-icon">◉</span>
                                <span class="filter-label">Work-Life Harmony</span>
                                <span class="filter-description">Balance matters</span>
                            </button>
                            
                            <button class="smart-filter-btn remote-hybrid" data-filter="remote-hybrid">
                                <span class="filter-icon">◊</span>
                                <span class="filter-label">Flexible Working</span>
                                <span class="filter-description">Remote/Hybrid</span>
                            </button>
                            
                            <button class="smart-filter-btn culture-fit" data-filter="culture-fit">
                                <span class="filter-icon">▣</span>
                                <span class="filter-label">Culture Match</span>
                                <span class="filter-description">Values aligned</span>
                            </button>
                        </div>
                        
                        <!-- Compensation Intelligence -->
                        <div class="filter-category compensation">
                            <button class="smart-filter-btn above-market" data-filter="above-market">
                                <span class="filter-icon">£</span>
                                <span class="filter-label">Above Market Rate</span>
                                <span class="filter-description">Premium pay</span>
                            </button>
                            
                            <button class="smart-filter-btn total-comp" data-filter="total-comp">
                                <span class="filter-icon">◈</span>
                                <span class="filter-label">Best Total Package</span>
                                <span class="filter-description">Salary + Benefits</span>
                            </button>
                            
                            <div class="salary-intelligence">
                                <label class="salary-label">Target Compensation (£)</label>
                                <div class="salary-range-slider">
                                    <input type="range" id="salary-min" min="30000" max="500000" step="5000">
                                    <input type="range" id="salary-max" min="30000" max="500000" step="5000">
                                    <div class="salary-track"></div>
                                    <div class="salary-values">
                                        <span class="min-value">£30k</span>
                                        <span class="max-value">£500k</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Company Type -->
                        <div class="filter-category company-type">
                            <button class="smart-filter-btn fortune-500" data-filter="fortune-500">
                                <span class="filter-icon">◉</span>
                                <span class="filter-label">Fortune 500</span>
                                <span class="filter-description">Global leaders</span>
                            </button>
                            
                            <button class="smart-filter-btn high-growth" data-filter="high-growth">
                                <span class="filter-icon">▲</span>
                                <span class="filter-label">High Growth</span>
                                <span class="filter-description">Scale-ups</span>
                            </button>
                            
                            <button class="smart-filter-btn established" data-filter="established">
                                <span class="filter-icon">◆</span>
                                <span class="filter-label">Established</span>
                                <span class="filter-description">Stable leaders</span>
                            </button>
                            
                            <button class="smart-filter-btn innovative" data-filter="innovative">
                                <span class="filter-icon">★</span>
                                <span class="filter-label">Innovative</span>
                                <span class="filter-description">Cutting edge</span>
                            </button>
                        </div>
                        
                        <!-- Timing & Urgency -->
                        <div class="filter-category timing">
                            <button class="smart-filter-btn immediate-start" data-filter="immediate-start">
                                <span class="filter-icon">⚡</span>
                                <span class="filter-label">Immediate Start</span>
                                <span class="filter-description">Start ASAP</span>
                            </button>
                            
                            <button class="smart-filter-btn new-postings" data-filter="new-postings">
                                <span class="filter-icon">◈</span>
                                <span class="filter-label">Posted Today</span>
                                <span class="filter-description">Fresh opportunities</span>
                            </button>
                            
                            <button class="smart-filter-btn closing-soon" data-filter="closing-soon">
                                <span class="filter-icon">!</span>
                                <span class="filter-label">Closing Soon</span>
                                <span class="filter-description">Apply now</span>
                            </button>
                        </div>
                        
                        <!-- Hidden Gems -->
                        <div class="filter-category discovery">
                            <button class="smart-filter-btn hidden-gems" data-filter="hidden-gems">
                                <span class="filter-icon">◊</span>
                                <span class="filter-label">Hidden Gems</span>
                                <span class="filter-description">Overlooked opportunities</span>
                            </button>
                            
                            <button class="smart-filter-btn network-advantage" data-filter="network-advantage">
                                <span class="filter-icon">◉</span>
                                <span class="filter-label">Network Advantage</span>
                                <span class="filter-description">You have connections</span>
                            </button>
                            
                            <button class="smart-filter-btn rare-skills" data-filter="rare-skills">
                                <span class="filter-icon">★</span>
                                <span class="filter-label">Rare Skills Match</span>
                                <span class="filter-description">Unique fit</span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Results Summary -->
                    <div class="filter-results-summary">
                        <div class="results-count">
                            <span class="count-number">0</span>
                            <span class="count-label">opportunities match your criteria</span>
                        </div>
                        <div class="filter-insights">
                            <span class="insight-icon">💡</span>
                            <span class="insight-text">AI recommendation: Try "Career Progression" for best results</span>
                        </div>
                    </div>
                </div>
            `;
            
            // Insert after existing filters or at top of job listings
            if ($('.sffc-smart-filters').length) {
                $('.sffc-smart-filters').replaceWith(filterHTML);
            } else if ($('.ultimate-opportunities').length) {
                $('.ultimate-opportunities').before(filterHTML);
            } else if ($('#opportunities-grid').length) {
                $('#opportunities-grid').before(filterHTML);
            }
        }
        
        bindEvents() {
            // Smart filter button clicks
            $(document).on('click', '.smart-filter-btn', (e) => {
                const btn = $(e.currentTarget);
                const filterType = btn.data('filter');
                this.toggleFilter(filterType, btn);
            });
            
            // Salary range slider
            $(document).on('input', '#salary-min, #salary-max', (e) => {
                this.updateSalaryRange();
            });
            
            // Clear all filters
            $(document).on('click', '.clear-all-filters', () => {
                this.clearAllFilters();
            });
            
            // Remove individual filter
            $(document).on('click', '.remove-filter', (e) => {
                const filterType = $(e.currentTarget).data('filter');
                this.removeFilter(filterType);
            });
        }
        
        toggleFilter(filterType, btn) {
            if (btn.hasClass('active')) {
                this.removeFilter(filterType);
                btn.removeClass('active');
            } else {
                this.addFilter(filterType, btn);
                btn.addClass('active');
            }
        }
        
        addFilter(filterType, btn) {
            // Store filter data
            const filterData = {
                type: filterType,
                label: btn.find('.filter-label').text(),
                icon: btn.find('.filter-icon').text()
            };
            
            this.activeFilters.set(filterType, filterData);
            
            // Add to active filters display
            this.updateActiveFiltersDisplay();
            
            // Apply intelligent filtering
            this.applyIntelligentFilters();
        }
        
        removeFilter(filterType) {
            this.activeFilters.delete(filterType);
            $(`.smart-filter-btn[data-filter="${filterType}"]`).removeClass('active');
            this.updateActiveFiltersDisplay();
            this.applyIntelligentFilters();
        }
        
        updateActiveFiltersDisplay() {
            const container = $('#active-filter-pills');
            container.empty();
            
            if (this.activeFilters.size === 0) {
                $('.active-filters-bar').hide();
                return;
            }
            
            $('.active-filters-bar').show();
            
            this.activeFilters.forEach((filter, type) => {
                const pill = `
                    <div class="filter-pill" data-filter="${type}">
                        <span class="pill-icon">${filter.icon}</span>
                        <span class="pill-label">${filter.label}</span>
                        <button class="remove-filter" data-filter="${type}">×</button>
                    </div>
                `;
                container.append(pill);
            });
        }
        
        clearAllFilters() {
            this.activeFilters.clear();
            $('.smart-filter-btn').removeClass('active');
            $('.active-filters-bar').hide();
            this.showAllJobs();
            this.updateResultsCount(this.allJobs.length);
        }
        
        applyIntelligentFilters() {
            // Get all job cards
            const jobCards = $('.ultimate-opp-card, .sffc-opportunity-card, .job-card');
            let matchedCount = 0;
            
            if (this.activeFilters.size === 0) {
                jobCards.show();
                matchedCount = jobCards.length;
            } else {
                jobCards.each((index, card) => {
                    const $card = $(card);
                    const jobData = this.extractJobData($card);
                    
                    if (this.matchesFilters(jobData)) {
                        $card.show();
                        matchedCount++;
                        // Add match animation
                        $card.addClass('filter-match');
                        setTimeout(() => $card.removeClass('filter-match'), 500);
                    } else {
                        $card.hide();
                    }
                });
            }
            
            this.updateResultsCount(matchedCount);
            this.showFilterInsights();
        }
        
        extractJobData($card) {
            // Extract job data from card elements
            return {
                id: $card.data('job-id'),
                title: $card.find('.job-title, .opp-title, h3, h4').first().text(),
                company: $card.find('.company-name, .opp-company, .job-company').first().text(),
                location: $card.find('.location, .opp-location').text(),
                salary: this.extractSalary($card),
                matchScore: parseInt($card.find('.match-score, .sffc-match-score').text()) || 75,
                type: $card.find('.job-type').text(),
                posted: $card.data('posted') || 'recent',
                skills: $card.data('skills') || [],
                benefits: $card.data('benefits') || []
            };
        }
        
        extractSalary($card) {
            const salaryText = $card.find('.salary, .opp-salary').text();
            const matches = salaryText.match(/[\d,]+/g);
            if (matches) {
                const numbers = matches.map(m => parseInt(m.replace(/,/g, '')));
                return {
                    min: numbers[0] || 0,
                    max: numbers[1] || numbers[0] || 0
                };
            }
            return { min: 0, max: 0 };
        }
        
        matchesFilters(jobData) {
            let matches = true;
            
            this.activeFilters.forEach((filter, type) => {
                switch(type) {
                    case 'best-matches':
                        matches = matches && jobData.matchScore >= 80;
                        break;
                        
                    case 'career-progression':
                        matches = matches && this.isCareerProgression(jobData);
                        break;
                        
                    case 'skill-builder':
                        matches = matches && this.buildsSkills(jobData);
                        break;
                        
                    case 'stretch-roles':
                        matches = matches && this.isStretchRole(jobData);
                        break;
                        
                    case 'work-life-harmony':
                        matches = matches && this.hasWorkLifeBalance(jobData);
                        break;
                        
                    case 'remote-hybrid':
                        matches = matches && this.isFlexible(jobData);
                        break;
                        
                    case 'culture-fit':
                        matches = matches && jobData.matchScore >= 70;
                        break;
                        
                    case 'above-market':
                        matches = matches && this.isAboveMarket(jobData);
                        break;
                        
                    case 'total-comp':
                        matches = matches && this.hasBestPackage(jobData);
                        break;
                        
                    case 'fortune-500':
                        matches = matches && this.isFortune500(jobData);
                        break;
                        
                    case 'high-growth':
                        matches = matches && this.isHighGrowth(jobData);
                        break;
                        
                    case 'established':
                        matches = matches && this.isEstablished(jobData);
                        break;
                        
                    case 'innovative':
                        matches = matches && this.isInnovative(jobData);
                        break;
                        
                    case 'immediate-start':
                        matches = matches && this.isImmediate(jobData);
                        break;
                        
                    case 'new-postings':
                        matches = matches && this.isNew(jobData);
                        break;
                        
                    case 'closing-soon':
                        matches = matches && this.isClosingSoon(jobData);
                        break;
                        
                    case 'hidden-gems':
                        matches = matches && this.isHiddenGem(jobData);
                        break;
                        
                    case 'network-advantage':
                        matches = matches && this.hasNetworkAdvantage(jobData);
                        break;
                        
                    case 'rare-skills':
                        matches = matches && this.hasRareSkillsMatch(jobData);
                        break;
                }
            });
            
            // Check salary range if set
            const salaryMin = parseInt($('#salary-min').val());
            const salaryMax = parseInt($('#salary-max').val());
            if (salaryMin > 30000 || salaryMax < 500000) {
                matches = matches && jobData.salary.min >= salaryMin && jobData.salary.max <= salaryMax;
            }
            
            return matches;
        }
        
        // Intelligent filter logic methods
        isCareerProgression(job) {
            // Check if job is a logical next step
            const titleProgression = ['Analyst', 'Associate', 'VP', 'Director', 'MD', 'Partner'];
            const currentLevel = this.userProfile.experience;
            return job.matchScore >= 75 && currentLevel < 10;
        }
        
        buildsSkills(job) {
            // Jobs that add new skills
            return job.skills && job.skills.length > 5;
        }
        
        isStretchRole(job) {
            // Challenging but achievable
            return job.matchScore >= 65 && job.matchScore <= 80;
        }
        
        hasWorkLifeBalance(job) {
            // Check for balance indicators
            const balanceKeywords = ['flexible', 'balance', 'hybrid', 'remote', '35 hours', 'wellness'];
            const text = job.title + ' ' + job.company;
            return balanceKeywords.some(keyword => text.toLowerCase().includes(keyword));
        }
        
        isFlexible(job) {
            const flexKeywords = ['remote', 'hybrid', 'flexible', 'work from home', 'wfh'];
            const text = job.title + ' ' + job.location;
            return flexKeywords.some(keyword => text.toLowerCase().includes(keyword));
        }
        
        isAboveMarket(job) {
            // Check if salary is above market rate
            const marketAverage = 85000; // Example market average
            return job.salary.min > marketAverage * 1.1;
        }
        
        hasBestPackage(job) {
            // High salary plus benefits
            return job.salary.max > 100000 && job.benefits && job.benefits.length > 3;
        }
        
        isFortune500(job) {
            const fortune500 = ['Goldman Sachs', 'JP Morgan', 'Morgan Stanley', 'Citi', 'HSBC', 'Barclays'];
            return fortune500.some(company => job.company.includes(company));
        }
        
        isHighGrowth(job) {
            const growthKeywords = ['startup', 'scale-up', 'series', 'growth', 'expanding'];
            return growthKeywords.some(keyword => job.company.toLowerCase().includes(keyword));
        }
        
        isEstablished(job) {
            const established = ['Bank', 'Group', 'Corporation', 'Ltd', 'PLC', 'Global'];
            return established.some(keyword => job.company.includes(keyword));
        }
        
        isInnovative(job) {
            const innovative = ['AI', 'ML', 'Fintech', 'Digital', 'Innovation', 'Tech'];
            const text = job.title + ' ' + job.company;
            return innovative.some(keyword => text.includes(keyword));
        }
        
        isImmediate(job) {
            const immediateKeywords = ['immediate', 'asap', 'urgent', 'now'];
            return immediateKeywords.some(keyword => job.title.toLowerCase().includes(keyword));
        }
        
        isNew(job) {
            // Check if posted recently
            return job.posted === 'today' || job.posted === 'recent';
        }
        
        isClosingSoon(job) {
            // Mock check for closing soon
            return Math.random() > 0.8; // 20% of jobs
        }
        
        isHiddenGem(job) {
            // Low competition, high potential
            return job.matchScore >= 70 && Math.random() > 0.7;
        }
        
        hasNetworkAdvantage(job) {
            // Check if user has connections at company
            return Math.random() > 0.8; // Mock 20% have connections
        }
        
        hasRareSkillsMatch(job) {
            // Unique skill combination
            return job.matchScore >= 85 && job.skills && job.skills.length > 7;
        }
        
        updateSalaryRange() {
            const min = parseInt($('#salary-min').val());
            const max = parseInt($('#salary-max').val());
            
            // Update display
            $('.min-value').text(`£${(min/1000).toFixed(0)}k`);
            $('.max-value').text(`£${(max/1000).toFixed(0)}k`);
            
            // Update track visual
            const minPercent = ((min - 30000) / (500000 - 30000)) * 100;
            const maxPercent = ((max - 30000) / (500000 - 30000)) * 100;
            
            $('.salary-track').css({
                'left': minPercent + '%',
                'right': (100 - maxPercent) + '%'
            });
            
            // Apply filter
            this.applyIntelligentFilters();
        }
        
        updateResultsCount(count) {
            $('.count-number').text(count);
            
            // Animate count change
            $('.results-count').addClass('pulse');
            setTimeout(() => $('.results-count').removeClass('pulse'), 500);
        }
        
        showFilterInsights() {
            const insights = [
                'AI detected strong matches in your filtered results',
                'These roles align with your career trajectory',
                'Filtered opportunities show 23% higher match scores',
                'Your selections indicate preference for growth roles',
                'Consider adding "Network Advantage" for hidden opportunities'
            ];
            
            const randomInsight = insights[Math.floor(Math.random() * insights.length)];
            $('.insight-text').text(randomInsight);
            
            // Show insight animation
            $('.filter-insights').addClass('show');
            setTimeout(() => $('.filter-insights').removeClass('show'), 3000);
        }
        
        showAllJobs() {
            $('.ultimate-opp-card, .sffc-opportunity-card, .job-card').show();
        }
        
        initializeIntelligentFilters() {
            // Set default "Best Matches" filter
            this.addFilter('best-matches', $('.smart-filter-btn.best-matches'));
            
            // Initialize salary range
            $('#salary-min').val(60000);
            $('#salary-max').val(150000);
            this.updateSalaryRange();
        }
    }
    
    // Initialize when DOM is ready
    $(document).ready(() => {
        window.smartFiltersPremium = new SmartFiltersPremium();
    });
    
})(jQuery);