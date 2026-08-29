/**
 * Job Intelligence Package Generator
 * Creates magazine-style intelligence briefings for job opportunities
 * @since 10.5.0
 */

(function($) {
    'use strict';
    
    class IntelligencePackage {
        constructor() {
            this.cache = new Map();
            this.comparisonJobs = [];
            this.currentJob = null;
            this.userProfile = this.loadUserProfile();
            
            // Application system detection patterns
            this.applicationSystems = {
                'workday': {
                    patterns: ['workday.com', 'myworkday.com', 'wd3.myworkdayjobs.com', 'wd5.myworkdayjobs.com'],
                    time: '45-60 minutes',
                    warning: 'Long application - have your documents ready'
                },
                'greenhouse': {
                    patterns: ['greenhouse.io', 'boards.greenhouse.io'],
                    time: '15-20 minutes',
                    tip: 'Straightforward application process'
                },
                'lever': {
                    patterns: ['lever.com', 'jobs.lever.com'],
                    time: '10-15 minutes',
                    tip: 'Quick and simple application'
                },
                'taleo': {
                    patterns: ['taleo.net', 'tbe.taleo.net'],
                    time: '30-45 minutes',
                    warning: 'Oracle system - may require account creation'
                },
                'icims': {
                    patterns: ['icims.com', 'jobs.icims.com'],
                    time: '25-35 minutes',
                    tip: 'Standard corporate application'
                },
                'bamboohr': {
                    patterns: ['bamboohr.com', 'bamboo.hr'],
                    time: '15-20 minutes',
                    tip: 'Modern, user-friendly system'
                },
                'ashbyhq': {
                    patterns: ['ashbyhq.com', 'jobs.ashbyhq.com'],
                    time: '10-15 minutes',
                    tip: 'Startup-friendly, quick process'
                },
                'linkedin': {
                    patterns: ['linkedin.com/jobs', 'linkedin.com/company'],
                    time: '5-10 minutes',
                    tip: 'Easy Apply - uses your LinkedIn profile'
                },
                'email': {
                    patterns: ['mailto:', '@'],
                    time: '5 minutes',
                    tip: 'Direct email - personalize your message'
                },
                'company_portal': {
                    patterns: ['/careers/', '/jobs/', '/opportunities/'],
                    time: '20-30 minutes',
                    tip: 'Direct company application'
                }
            };
            
            // Company reputation patterns
            this.reputationIndicators = {
                established: ['Goldman', 'Morgan Stanley', 'JP Morgan', 'BlackRock', 'KKR', 'Apollo', 'Blackstone', 'Carlyle', 'Warburg'],
                recently_launched: ['Founded 20', 'Series A', 'Series B', 'stealth', 'early-stage', 'new fund'],
                small_and_growing: ['boutique', '10-50 employees', 'growing team', 'expanding', 'hiring'],
                proceed_with_caution: ['restructuring', 'layoffs', 'turnover', 'mixed reviews']
            };
        }
        
        /**
         * Generate intelligence package for a job
         */
        async generate(jobId) {
            // Check cache first
            if (this.cache.has(jobId)) {
                const cached = this.cache.get(jobId);
                if (Date.now() - cached.timestamp < 3600000) { // 1 hour cache
                    return this.render(cached.data);
                }
            }
            
            // Note: Loading state is handled by senna-conversational.js
            
            try {
                // Fetch job data
                const jobData = await this.fetchJobData(jobId);
                this.currentJob = jobData;
                
                // Generate intelligence sections
                const intelligence = {
                    job: jobData,
                    applicationTime: this.detectApplicationTime(jobData.application_url || jobData.link),
                    companyReputation: this.assessCompanyReputation(jobData),
                    matchScore: this.calculateMatchScore(jobData),
                    competitionLevel: this.assessCompetition(jobData),
                    strategicInsights: await this.generateStrategicInsights(jobData),
                    battlePlan: this.generateBattlePlan(jobData),
                    timestamp: Date.now()
                };
                
                // Cache the intelligence
                this.cache.set(jobId, {
                    data: intelligence,
                    timestamp: Date.now()
                });
                
                // Render the package
                return this.render(intelligence);
                
            } catch (error) {
                console.error('Error generating intelligence:', error);
                return this.renderError(error);
            }
        }
        
        /**
         * Detect application time from URL
         */
        detectApplicationTime(url) {
            if (!url) {
                return {
                    system: 'Unknown',
                    time: '15-30 minutes',
                    tip: 'Standard application process'
                };
            }
            
            const urlLower = url.toLowerCase();
            
            // Check each system's patterns
            for (const [system, config] of Object.entries(this.applicationSystems)) {
                for (const pattern of config.patterns) {
                    if (urlLower.includes(pattern)) {
                        return {
                            system: system.charAt(0).toUpperCase() + system.slice(1),
                            time: config.time,
                            tip: config.tip || config.warning || ''
                        };
                    }
                }
            }
            
            // Default fallback
            return {
                system: 'Company Portal',
                time: '20-30 minutes',
                tip: 'Direct company application'
            };
        }
        
        /**
         * Assess company reputation
         */
        assessCompanyReputation(job) {
            const company = (job.company || '').toLowerCase();
            const description = (job.description || '').toLowerCase();
            const combined = company + ' ' + description;
            
            // Check established firms
            for (const firm of this.reputationIndicators.established) {
                if (combined.includes(firm.toLowerCase())) {
                    return {
                        status: 'Established',
                        color: '#1B4332',
                        description: 'Blue-chip firm with proven track record'
                    };
                }
            }
            
            // Check for recent launch indicators
            for (const indicator of this.reputationIndicators.recently_launched) {
                if (combined.includes(indicator.toLowerCase())) {
                    return {
                        status: 'Recently Launched',
                        color: '#F59E0B',
                        description: 'New firm with high growth potential'
                    };
                }
            }
            
            // Check for growth indicators
            for (const indicator of this.reputationIndicators.small_and_growing) {
                if (combined.includes(indicator.toLowerCase())) {
                    return {
                        status: 'Small & Growing',
                        color: '#3B82F6',
                        description: 'Boutique firm with expansion plans'
                    };
                }
            }
            
            // Default to established if large company
            if (job.company_size === 'large' || parseInt(job.employees) > 500) {
                return {
                    status: 'Established',
                    color: '#1B4332',
                    description: 'Established organization'
                };
            }
            
            return {
                status: 'Growing',
                color: '#10B981',
                description: 'Active in the market'
            };
        }
        
        /**
         * Calculate match score
         */
        calculateMatchScore(job) {
            let score = 60; // Base score
            
            // Check skills match
            if (job.skills && this.userProfile.skills) {
                const jobSkills = job.skills.map(s => s.toLowerCase());
                const userSkills = this.userProfile.skills.map(s => s.toLowerCase());
                const matches = jobSkills.filter(skill => userSkills.includes(skill));
                score += Math.min(20, matches.length * 5);
            }
            
            // Check experience level match
            if (job.experience_level && this.userProfile.experience_level) {
                if (job.experience_level === this.userProfile.experience_level) {
                    score += 10;
                }
            }
            
            // Check location preference
            if (job.location && this.userProfile.preferred_locations) {
                if (this.userProfile.preferred_locations.includes(job.location)) {
                    score += 10;
                }
            }
            
            return Math.min(95, score);
        }
        
        /**
         * Assess competition level
         */
        assessCompetition(job) {
            const company = (job.company || '').toLowerCase();
            const title = (job.title || '').toLowerCase();
            
            // High competition indicators
            if (company.includes('goldman') || company.includes('morgan') || 
                company.includes('blackstone') || title.includes('partner')) {
                return 'High';
            }
            
            // Low competition indicators
            if (title.includes('senior') || title.includes('head of') || 
                job.experience_years > 10) {
                return 'Moderate';
            }
            
            return 'Standard';
        }
        
        /**
         * Generate strategic insights
         */
        async generateStrategicInsights(job) {
            return {
                companyIntel: this.generateCompanyIntel(job),
                rolePositioning: this.generateRolePositioning(job),
                successFactors: this.generateSuccessFactors(job)
            };
        }
        
        /**
         * Generate company intelligence
         */
        generateCompanyIntel(job) {
            const insights = [];
            
            // Recent news (placeholder - would connect to news API)
            insights.push(`${job.company} is actively hiring for multiple ${job.department || 'positions'}`);
            
            // Culture insights based on job description
            if (job.description) {
                if (job.description.includes('fast-paced')) {
                    insights.push('Fast-paced, high-performance culture');
                }
                if (job.description.includes('collaborative')) {
                    insights.push('Emphasis on teamwork and collaboration');
                }
                if (job.description.includes('entrepreneurial')) {
                    insights.push('Entrepreneurial environment with autonomy');
                }
            }
            
            // Growth trajectory
            if (job.company_growth) {
                insights.push(`Company showing ${job.company_growth} growth trajectory`);
            }
            
            return insights.slice(0, 3);
        }
        
        /**
         * Generate role positioning insights
         */
        generateRolePositioning(job) {
            const insights = [];
            
            // Team structure
            if (job.team_size) {
                insights.push(`Team of ${job.team_size} professionals`);
            }
            
            // Progression pathway
            const title = job.title || '';
            if (title.includes('Analyst')) {
                insights.push('Clear path to Associate in 2-3 years');
            } else if (title.includes('Associate')) {
                insights.push('VP promotion typically in 3-4 years');
            } else if (title.includes('VP')) {
                insights.push('Director/MD track based on performance');
            }
            
            // Impact potential
            if (job.description && job.description.includes('strategic')) {
                insights.push('High visibility role with strategic impact');
            }
            
            return insights.slice(0, 3);
        }
        
        /**
         * Generate success factors
         */
        generateSuccessFactors(job) {
            const factors = [];
            
            // Critical skills match
            if (job.skills && job.skills.length > 0) {
                factors.push(`Strong match on ${job.skills[0]} expertise`);
            }
            
            // Experience alignment
            if (job.experience_years) {
                factors.push(`${job.experience_years}+ years experience requirement met`);
            }
            
            // Cultural fit
            factors.push('Cultural values alignment indicated');
            
            return factors;
        }
        
        /**
         * Generate battle plan
         */
        generateBattlePlan(job) {
            return {
                networking: this.generateNetworkingStrategy(job),
                materials: this.generateMaterialsStrategy(job),
                application: this.generateApplicationStrategy(job)
            };
        }
        
        /**
         * Generate networking strategy
         */
        generateNetworkingStrategy(job) {
            return [
                `Connect with 2-3 ${job.department || 'team'} analysts on LinkedIn`,
                `Reference these conversations when reaching out to senior team members`,
                `Join relevant ${job.company} alumni groups if available`
            ];
        }
        
        /**
         * Generate materials strategy
         */
        generateMaterialsStrategy(job) {
            const strategies = [];
            
            if (job.skills && job.skills.length > 0) {
                strategies.push(`Emphasize ${job.skills[0]} experience prominently`);
            }
            
            if (job.key_requirements && job.key_requirements.length > 0) {
                strategies.push(`Address "${job.key_requirements[0]}" requirement directly`);
            }
            
            strategies.push('Quantify achievements with specific metrics');
            
            return strategies.slice(0, 3);
        }
        
        /**
         * Generate application strategy
         */
        generateApplicationStrategy(job) {
            const appTime = this.detectApplicationTime(job.application_url);
            return [
                `Apply via ${appTime.system} (${appTime.time})`,
                'Best to apply Tuesday-Thursday morning',
                'Follow up after 5-7 business days if no response'
            ];
        }
        
        /**
         * Render the intelligence package
         */
        render(intelligence) {
            const { job, applicationTime, companyReputation, matchScore, competitionLevel, strategicInsights, battlePlan } = intelligence;
            
            // Format salary
            const salaryDisplay = this.formatSalary(job);
            
            // Check if profile is complete for locked sections
            const isProfileComplete = this.isProfileComplete || (this.userProfile?.isComplete);
            
            const html = `
                <div class="intelligence-package">
                    <!-- Hero Section -->
                    <div class="intel-hero">
                        <div class="intel-company">${job.company || 'Company'}</div>
                        <h1 class="intel-role">${job.title || 'Position'}</h1>
                        <div class="intel-meta">
                            <span class="intel-meta-item">
                                ${job.location || 'Location'}
                            </span>
                            <span class="intel-meta-item">
                                ${salaryDisplay}
                            </span>
                            <span class="intel-meta-item">
                                ${job.experience_level || job.seniority_level || 'Mid-Senior'}
                            </span>
                        </div>
                    </div>
                    
                    <!-- Executive Summary -->
                    <div class="intel-section intel-summary">
                        <div class="intel-summary-text">
                            ${this.generateExecutiveSummary(job, companyReputation, matchScore)}
                        </div>
                    </div>
                    
                    <!-- The Opportunity -->
                    <div class="intel-section">
                        <h2 class="intel-section-title">The Opportunity</h2>
                        <ul class="intel-opportunity-list">
                            ${this.generateOpportunityInsights(job).map(insight => 
                                `<li class="intel-opportunity-item">${insight}</li>`
                            ).join('')}
                        </ul>
                    </div>
                    
                    <!-- Company Analysis -->
                    <div class="intel-section">
                        <h2 class="intel-section-title">Company Analysis</h2>
                        <div class="intel-analysis-grid">
                            <div class="intel-analysis-card">
                                <h3 class="intel-analysis-title">Market Position</h3>
                                <ul class="intel-analysis-list">
                                    ${this.generateDetailedCompanyAnalysis(job, companyReputation).marketPosition.map(item => 
                                        `<li>${item}</li>`
                                    ).join('')}
                                </ul>
                            </div>
                            
                            <div class="intel-analysis-card">
                                <h3 class="intel-analysis-title">Culture & Work Style</h3>
                                <ul class="intel-analysis-list">
                                    ${this.generateDetailedCompanyAnalysis(job, companyReputation).culture.map(item => 
                                        `<li>${item}</li>`
                                    ).join('')}
                                </ul>
                            </div>
                            
                            <div class="intel-analysis-card">
                                <h3 class="intel-analysis-title">Growth Trajectory</h3>
                                <ul class="intel-analysis-list">
                                    ${this.generateDetailedCompanyAnalysis(job, companyReputation).growth.map(item => 
                                        `<li>${item}</li>`
                                    ).join('')}
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Personalized Insights - Locked for incomplete profiles -->
                    ${!isProfileComplete ? '' : `
                    <div class="intel-section">
                        <h2 class="intel-section-title">
                            Your Personalized Insights
                            <span style="background: #10B981; color: white; font-size: 11px; padding: 2px 8px; border-radius: 4px; margin-left: 8px; vertical-align: middle;">EXCLUSIVE</span>
                        </h2>
                        <div class="intel-personalized-insights">
                            ${this.generatePersonalizedInsights(job).map(insight => `
                                <div class="intel-insight-card">
                                    <h3>${insight.title}</h3>
                                    <p>${insight.content}</p>
                                    ${insight.action ? `<p class="intel-insight-action">→ ${insight.action}</p>` : ''}
                                </div>
                            `).join('')}
                        </div>
                    </div>
                    `}
                    
                    <!-- Role Intelligence -->
                    <div class="intel-section">
                        <h2 class="intel-section-title">Role Intelligence</h2>
                        <div class="intel-analysis-grid">
                            <div class="intel-analysis-card">
                                <h3 class="intel-analysis-title">Core Requirements</h3>
                                <ul class="intel-analysis-list">
                                    ${this.generateRoleIntelligence(job).requirements.map(item => 
                                        `<li>${item}</li>`
                                    ).join('')}
                                </ul>
                            </div>
                            
                            <div class="intel-analysis-card">
                                <h3 class="intel-analysis-title">Career Progression</h3>
                                <ul class="intel-analysis-list">
                                    ${this.generateRoleIntelligence(job).progression.map(item => 
                                        `<li>${item}</li>`
                                    ).join('')}
                                </ul>
                            </div>
                            
                            <div class="intel-analysis-card">
                                <h3 class="intel-analysis-title">Success Metrics</h3>
                                <ul class="intel-analysis-list">
                                    ${this.generateRoleIntelligence(job).metrics.map(item => 
                                        `<li>${item}</li>`
                                    ).join('')}
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Battle Plan - Locked for incomplete profiles -->
                    <div class="intel-section intel-battle-plan ${!isProfileComplete ? 'intel-locked' : ''}">
                        <h2 class="intel-section-title">
                            Your Personalized Battle Plan
                            ${!isProfileComplete ? '<span class="intel-lock-icon">🔒</span>' : ''}
                        </h2>
                        
                        ${!isProfileComplete ? `
                            <div class="intel-locked-overlay">
                                <div class="intel-locked-content">
                                    <svg class="lock-icon" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                    </svg>
                                    <h3>Unlock Your Personalized Strategy</h3>
                                    <p>Complete your profile to access:</p>
                                    <ul>
                                        <li>• Tailored networking approach based on your background</li>
                                        <li>• Custom talking points for your experience level</li>
                                        <li>• Personalized application timeline</li>
                                        <li>• Skill-gap analysis and recommendations</li>
                                    </ul>
                                    <button class="intel-unlock-btn" onclick="window.openProfileBuilder ? window.openProfileBuilder() : null">
                                        Complete Profile to Unlock
                                    </button>
                                </div>
                            </div>
                        ` : `
                            <div class="intel-step">
                                <div class="intel-step-number">1</div>
                                <h3 class="intel-step-title">Network Strategically</h3>
                                <ul class="intel-step-actions">
                                    ${battlePlan.networking.map(action => 
                                        `<li class="intel-step-action">${action}</li>`
                                    ).join('')}
                                </ul>
                            </div>
                            
                            <div class="intel-step">
                                <div class="intel-step-number">2</div>
                                <h3 class="intel-step-title">Tailor Your Materials</h3>
                                <ul class="intel-step-actions">
                                    ${battlePlan.materials.map(action => 
                                        `<li class="intel-step-action">${action}</li>`
                                    ).join('')}
                                </ul>
                            </div>
                            
                            <div class="intel-step">
                                <div class="intel-step-number">3</div>
                                <h3 class="intel-step-title">Application Strategy</h3>
                                <ul class="intel-step-actions">
                                    ${battlePlan.application.map(action => 
                                        `<li class="intel-step-action">${action}</li>`
                                    ).join('')}
                                </ul>
                            </div>
                        `}
                    </div>
                    
                    <!-- Quick Stats -->
                    <div class="intel-stats-bar">
                        <div class="intel-stat match-score">
                            <span class="intel-stat-value">${isProfileComplete ? matchScore + '%' : '—'}</span>
                            <span class="intel-stat-label">${isProfileComplete ? 'Match Score' : 'Complete Profile'}</span>
                        </div>
                        <div class="intel-stat ${applicationTime.time.includes('45') ? 'warning' : 'quick-apply'}">
                            <span class="intel-stat-value">${applicationTime.time.split('-')[0]}m</span>
                            <span class="intel-stat-label">Apply Time</span>
                        </div>
                        <div class="intel-stat ${competitionLevel === 'High' ? 'high-competition' : ''}">
                            <span class="intel-stat-value">${competitionLevel}</span>
                            <span class="intel-stat-label">Competition</span>
                        </div>
                        <div class="intel-stat ${companyReputation.status === 'Established' ? 'established-company' : ''}">
                            <span class="intel-stat-value">${companyReputation.status}</span>
                            <span class="intel-stat-label">Company</span>
                        </div>
                    </div>
                    
                    <!-- Action Center -->
                    <div class="intel-actions">
                        <button class="intel-action-btn intel-action-primary" onclick="intelligencePackage.applyNow('${job.id}')">
                            Apply Now
                        </button>
                        <button class="intel-action-btn intel-action-secondary" onclick="intelligencePackage.tailorCV('${job.id}')">
                            Tailor CV
                        </button>
                        <button class="intel-action-btn intel-action-tertiary" onclick="intelligencePackage.saveIntelligence('${job.id}')">
                            Save Briefing
                        </button>
                    </div>
                </div>
            `;
            
            return html;
        }
        
        /**
         * Generate executive summary
         */
        generateExecutiveSummary(job, reputation, matchScore) {
            const company = job.company || 'This company';
            const jobType = job.job_type || this.detectJobType(job.title);
            const department = job.department || '';
            
            let summary = `<span class="intel-summary-highlight">${company}</span>`;
            
            // Role-specific opening based on job type
            if (jobType === 'IT Support') {
                summary += ` is seeking a ${job.title || 'technical professional'} to strengthen their IT operations`;
            } else if (jobType === 'Wealth Management') {
                summary += ` is expanding their wealth management team with this ${job.title || 'advisory role'}`;
            } else if (jobType === 'Private Equity') {
                summary += ` is building out their investment team with this ${job.title || 'investment role'}`;
            } else if (jobType === 'Sales/BD') {
                summary += ` is growing their sales organization with this ${job.title || 'business development opportunity'}`;
            } else if (department) {
                summary += ` is expanding their ${department}`;
            } else {
                summary += ` has an opening for ${job.title || 'this position'}`;
            }
            
            // Company-specific context
            if (company.toLowerCase().includes('barings')) {
                summary += '. As a $400B+ global asset manager, they offer institutional stability with entrepreneurial opportunity. ';
            } else if (company.toLowerCase().includes('dartmouth')) {
                summary += '. This boutique firm offers personalized client engagement and direct partnership track. ';
            } else if (reputation.status === 'Established') {
                summary += ', offering stability and proven career progression. ';
            } else if (reputation.status === 'Recently Launched') {
                summary += ', presenting a ground-floor opportunity with high growth potential. ';
            } else {
                summary += ', creating new opportunities for talented professionals. ';
            }
            
            // Match score commentary
            if (matchScore >= 80) {
                summary += `With a <span class="intel-summary-highlight">${matchScore}% match score</span>, this role aligns exceptionally well with your background. `;
            } else if (matchScore >= 60) {
                summary += `This role shows strong alignment with your experience and goals. `;
            }
            
            // Key requirements - clean any date artifacts
            if (job.key_requirements && job.key_requirements.length > 0) {
                const cleanReq = job.key_requirements.find(req => 
                    req && !req.includes('ago') && !req.includes('Posted') && req.length > 10
                );
                if (cleanReq) {
                    summary += `Key focus: ${cleanReq}. `;
                }
            } else if (job.skills && job.skills.length > 0) {
                const cleanSkill = job.skills.find(skill => 
                    skill && !skill.includes('ago') && skill.length > 3
                );
                if (cleanSkill) {
                    summary += `Core requirement: ${cleanSkill} expertise. `;
                }
            }
            
            return summary;
        }
        
        /**
         * Generate truly personalized insights based on user profile and job match
         */
        generatePersonalizedInsights(job) {
            const insights = [];
            const profile = this.userProfile || {};
            
            // Skill Match Analysis
            if (profile.skills && job.skills) {
                const userSkills = profile.skills.map(s => s.toLowerCase());
                const jobSkills = job.skills.map(s => s.toLowerCase());
                const matchingSkills = jobSkills.filter(skill => 
                    userSkills.some(userSkill => skill.includes(userSkill) || userSkill.includes(skill))
                );
                const missingSkills = jobSkills.filter(skill => 
                    !userSkills.some(userSkill => skill.includes(userSkill) || userSkill.includes(skill))
                );
                
                if (matchingSkills.length > 0) {
                    insights.push({
                        title: `Strong Match: ${matchingSkills.slice(0, 2).join(' & ')}`,
                        content: `You have ${matchingSkills.length} of ${jobSkills.length} required skills. This ${Math.round((matchingSkills.length/jobSkills.length)*100)}% match puts you ahead of most candidates.`,
                        action: matchingSkills.length === jobSkills.length ? 
                            'Lead with your complete skill alignment in your cover letter' :
                            `Emphasize your ${matchingSkills.join(', ')} experience prominently`
                    });
                }
                
                if (missingSkills.length > 0 && missingSkills.length <= 2) {
                    insights.push({
                        title: `Skill Gap: ${missingSkills[0]}`,
                        content: `While you lack formal ${missingSkills[0]} experience, your ${matchingSkills[0] || 'background'} provides transferable skills that can bridge this gap.`,
                        action: 'Prepare examples showing how your existing skills relate to this requirement'
                    });
                }
            }
            
            // Experience Level Match
            if (profile.years_experience && job.experience_years) {
                const expDiff = profile.years_experience - job.experience_years;
                
                if (Math.abs(expDiff) <= 1) {
                    insights.push({
                        title: 'Perfect Experience Alignment',
                        content: `Your ${profile.years_experience} years perfectly matches their ${job.experience_years}+ year requirement. You're in the sweet spot - experienced enough to add value immediately, but not overqualified.`,
                        action: 'Highlight specific achievements from your most recent 2-3 years'
                    });
                } else if (expDiff > 1) {
                    insights.push({
                        title: 'Senior Candidate Advantage',
                        content: `With ${expDiff} years more experience than required, position yourself as someone who can mentor junior team members while executing at a high level.`,
                        action: 'Emphasize leadership experience and ability to scale team capabilities'
                    });
                } else if (expDiff < -2) {
                    insights.push({
                        title: 'High-Potential Candidate Strategy',
                        content: `While you have ${Math.abs(expDiff)} fewer years than typical, focus on your trajectory and learning velocity. Companies often prefer hungry talent over years alone.`,
                        action: 'Showcase rapid progression and exceptional achievements relative to tenure'
                    });
                }
            }
            
            // Location & Logistics
            if (profile.preferred_locations && job.location) {
                const isLocalCandidate = profile.preferred_locations.some(loc => 
                    job.location.toLowerCase().includes(loc.toLowerCase())
                );
                
                if (isLocalCandidate) {
                    insights.push({
                        title: `Local Market Advantage`,
                        content: `Being already in ${job.location} eliminates relocation concerns and shows market commitment. You can start immediately and likely have local network connections.`,
                        action: 'Mention your local network and ability to start without relocation delays'
                    });
                } else {
                    const primaryLocation = profile.preferred_locations[0];
                    insights.push({
                        title: `Cross-Market Opportunity`,
                        content: `Coming from ${primaryLocation} to ${job.location} brings fresh perspectives. Many firms value diverse geographic experience for new insights.`,
                        action: 'Frame your geographic diversity as bringing best practices from another major market'
                    });
                }
            }
            
            // Compensation Insights
            if (job.salary_max && profile.salary_expectations) {
                const salaryMatch = job.salary_max >= profile.salary_expectations;
                if (salaryMatch) {
                    insights.push({
                        title: 'Compensation Alignment',
                        content: `This role's upper range aligns with your expectations. You have room to negotiate while staying within their budget.`,
                        action: 'Delay compensation discussions until after you have demonstrated value in interviews'
                    });
                }
            }
            
            // Industry/Sector Experience
            if (profile.industry_experience && job.department) {
                const hasRelevantExp = profile.industry_experience.toLowerCase().includes(job.department.toLowerCase());
                if (hasRelevantExp) {
                    insights.push({
                        title: `Direct ${job.department} Experience`,
                        content: `Your background in ${profile.industry_experience} directly translates to their ${job.department} team needs. You'll have minimal ramp-up time.`,
                        action: 'Prepare specific examples of similar deals or projects from your experience'
                    });
                }
            }
            
            // Return top 3-4 most relevant insights
            return insights.slice(0, 4);
        }
        
        /**
         * Generate opportunity insights
         */
        generateOpportunityInsights(job) {
            const insights = [];
            const jobType = job.job_type || this.detectJobType(job.title);
            
            // Clean function to filter out date artifacts
            const isValidContent = (str) => {
                if (!str) return false;
                const s = str.toString();
                return !s.includes('weeks ago') && !s.includes('days ago') && 
                       !s.includes('hours ago') && !s.includes('Posted') && s.length > 10;
            };
            
            // Core requirements - filtered and role-specific
            if (job.skills && job.skills.length > 0) {
                const validSkills = job.skills.filter(isValidContent);
                if (validSkills.length > 0) {
                    insights.push(`Core requirement: <span class="intel-opportunity-highlight">${validSkills[0]}</span> expertise with proven track record`);
                    if (validSkills.length > 1) {
                        insights.push(`Additional valued skills: ${validSkills.slice(1, 3).join(', ')}`);
                    }
                }
            } else if (job.key_requirements && job.key_requirements.length > 0) {
                const validReqs = job.key_requirements.filter(isValidContent);
                validReqs.slice(0, 2).forEach(req => {
                    insights.push(req);
                });
            }
            
            // Role-specific insights
            if (jobType === 'IT Support') {
                if (job.technical_skills) {
                    insights.push(`Technical stack: ${job.technical_skills.join(', ')}`);
                }
                insights.push('Opportunity to work with enterprise-level infrastructure');
            } else if (jobType === 'Wealth Management') {
                insights.push('Direct client interaction with UHNW individuals');
                if (job.aum_target) {
                    insights.push(`AUM growth target: ${job.aum_target}`);
                }
            } else if (jobType === 'Private Equity') {
                if (job.fund_size) {
                    insights.push(`Fund size: ${job.fund_size}`);
                }
                if (job.sectors) {
                    insights.push(`Sector focus: ${job.sectors}`);
                }
            } else if (jobType === 'Sales/BD') {
                if (job.quota) {
                    insights.push(`Annual quota: ${job.quota}`);
                }
                insights.push('Territory ownership with expansion potential');
            }
            
            // Hidden requirements from description
            if (job.description) {
                const desc = job.description.toLowerCase();
                if (desc.includes('mba') && jobType === 'Private Equity') {
                    insights.push('MBA strongly preferred (though not always explicitly stated)');
                } else if (desc.includes('cfa') && jobType === 'Wealth Management') {
                    insights.push('CFA designation highly valued');
                } else if (desc.includes('international')) {
                    insights.push('International experience will set you apart');
                }
            }
            
            // Growth and compensation insights
            if (job.team_expansion || (job.description && job.description.includes('growing'))) {
                insights.push('Team expansion indicates strong business momentum');
            }
            
            if (job.salary_max) {
                const salaryStr = this.formatSalary(job);
                if (salaryStr !== 'Competitive') {
                    insights.push(`Compensation range: ${salaryStr}`);
                }
            }
            
            // Ensure we have meaningful insights
            if (insights.length === 0) {
                // Add generic but relevant insights based on job type
                if (jobType === 'IT Support') {
                    insights.push('Opportunity to modernize IT infrastructure');
                    insights.push('Direct impact on operational efficiency');
                } else if (jobType === 'Wealth Management') {
                    insights.push('Build and manage your own book of business');
                    insights.push('Access to firm\'s investment platform and research');
                } else if (jobType === 'Sales/BD') {
                    insights.push('Uncapped commission potential');
                    insights.push('Strategic account management opportunity');
                } else {
                    insights.push(`Opportunity to join ${job.company || 'a growing organization'}`);
                    insights.push('Role offers significant growth potential');
                }
            }
            
            return insights.slice(0, 4);
        }
        
        /**
         * Generate detailed company analysis
         */
        generateDetailedCompanyAnalysis(job, reputation) {
            const analysis = {
                marketPosition: [],
                culture: [],
                growth: []
            };
            
            // Detect job type for appropriate company analysis
            const jobType = job.job_type || this.detectJobType(job.title);
            const company = job.company || 'The company';
            
            // Market Position Analysis based on actual company and role
            if (jobType === 'IT Support' || jobType === 'DevOps/Infrastructure') {
                // IT/Tech role analysis
                if (reputation.status === 'Established') {
                    analysis.marketPosition.push(`${company} has mature IT infrastructure with established processes`);
                    analysis.marketPosition.push('Structured IT service management following ITIL framework');
                    analysis.marketPosition.push('Investment in latest technologies and continuous training');
                } else {
                    analysis.marketPosition.push(`${company} offers opportunity to shape IT infrastructure`);
                    analysis.marketPosition.push('Flexible environment for implementing new technologies');
                    analysis.marketPosition.push('Direct impact on technology decisions and strategy');
                }
            } else if (jobType === 'Wealth Management') {
                // Wealth Management analysis
                if (company.toLowerCase().includes('dartmouth')) {
                    analysis.marketPosition.push('Dartmouth Partners is a boutique wealth management firm');
                    analysis.marketPosition.push('Focus on high-net-worth individuals and family offices');
                    analysis.marketPosition.push('Personalized approach with direct client relationships');
                } else if (reputation.status === 'Established') {
                    analysis.marketPosition.push(`${company} is a leading wealth management institution`);
                    analysis.marketPosition.push('Access to comprehensive investment platforms and research');
                    analysis.marketPosition.push('Established UHNW client base with succession opportunities');
                } else {
                    analysis.marketPosition.push(`${company} offers entrepreneurial wealth management environment`);
                    analysis.marketPosition.push('Opportunity to build your own book of business');
                    analysis.marketPosition.push('Flexible investment approach and client strategies');
                }
            } else if (jobType === 'Private Equity') {
                // PE-specific analysis (keep existing logic but refined)
                if (reputation.status === 'Established') {
                    analysis.marketPosition.push(`${company} is a tier-one PE firm with strong track record`);
                    analysis.marketPosition.push('Access to premium deal flow and blue-chip portfolio');
                    analysis.marketPosition.push('Established LP relationships and fundraising capabilities');
                } else {
                    analysis.marketPosition.push(`${company} is a growing PE platform`);
                    analysis.marketPosition.push('Opportunity to shape investment strategy and thesis');
                    analysis.marketPosition.push('Direct access to senior partners and decision makers');
                }
            } else if (jobType === 'Sales/BD') {
                // Sales/BD analysis
                if (company.toLowerCase().includes('barings')) {
                    analysis.marketPosition.push('Barings is a $400B+ global asset management firm');
                    analysis.marketPosition.push('Part of MassMutual with strong institutional backing');
                    analysis.marketPosition.push('Global distribution network across Americas, EMEA, and APAC');
                } else if (reputation.status === 'Established') {
                    analysis.marketPosition.push(`${company} has established market presence and brand recognition`);
                    analysis.marketPosition.push('Existing client base provides warm lead opportunities');
                    analysis.marketPosition.push('Proven sales processes and support systems');
                } else {
                    analysis.marketPosition.push(`${company} offers opportunity to build new markets`);
                    analysis.marketPosition.push('Entrepreneurial sales environment with territory ownership');
                    analysis.marketPosition.push('Direct input into product development and go-to-market strategy');
                }
            } else {
                // Generic but still specific to company size/type
                if (reputation.status === 'Established') {
                    analysis.marketPosition.push(`${company} is an established player in the market`);
                    analysis.marketPosition.push('Stable environment with defined processes and procedures');
                    analysis.marketPosition.push('Strong market reputation and client relationships');
                } else {
                    analysis.marketPosition.push(`${company} is expanding their ${job.department || 'team'}`);
                    analysis.marketPosition.push('Growth phase creating advancement opportunities');
                    analysis.marketPosition.push('Ability to shape role and make immediate impact');
                }
            }
            
            // Culture Analysis - Role-specific
            const workStyle = job.work_style || job.pe_work_style || 'Hybrid';
            
            if (jobType === 'IT Support' || jobType === 'DevOps/Infrastructure') {
                if (workStyle === 'Hybrid') {
                    analysis.culture.push('Hybrid model with on-site presence for critical infrastructure support');
                    analysis.culture.push('Flexible scheduling with on-call rotation requirements');
                } else if (workStyle === 'Remote') {
                    analysis.culture.push('Remote-first IT support leveraging cloud-based tools');
                    analysis.culture.push('Strong documentation culture and async communication');
                } else {
                    analysis.culture.push('On-site IT presence for hands-on hardware and user support');
                    analysis.culture.push('Direct collaboration with business units and stakeholders');
                }
            } else if (jobType === 'Wealth Management') {
                analysis.culture.push('Client-centric culture with focus on relationship building');
                analysis.culture.push('Professional environment with emphasis on trust and discretion');
                if (workStyle === 'Hybrid') {
                    analysis.culture.push('Flexibility to meet clients at convenient locations');
                }
            } else if (jobType === 'Sales/BD') {
                analysis.culture.push('Performance-driven culture with clear metrics and targets');
                analysis.culture.push('Competitive but collaborative sales team environment');
                if (workStyle === 'Hybrid') {
                    analysis.culture.push('Field sales combined with office collaboration days');
                }
            } else if (jobType === 'Private Equity') {
                // Keep PE-specific culture analysis
                if (workStyle === 'Hybrid') {
                    analysis.culture.push('Flexible hybrid model for deal teams');
                    analysis.culture.push('In-office presence expected during live transactions');
                } else {
                    analysis.culture.push('Traditional deal team culture with high intensity periods');
                    analysis.culture.push('Expect long hours during due diligence and closing');
                }
            } else {
                // Generic work style culture
                if (workStyle === 'Hybrid') {
                    analysis.culture.push('Hybrid work model balancing flexibility with collaboration');
                } else if (workStyle === 'Remote') {
                    analysis.culture.push('Remote-first culture with digital collaboration tools');
                } else {
                    analysis.culture.push('Office-based culture fostering direct collaboration');
                }
            }
            
            // Add description-based culture insights
            if (job.description) {
                if (job.description.toLowerCase().includes('entrepreneurial')) {
                    analysis.culture.push('Entrepreneurial environment where initiative and ownership are rewarded');
                }
                if (job.description.toLowerCase().includes('collaborative')) {
                    analysis.culture.push('Collaborative culture with cross-functional team exposure');
                }
                if (job.description.toLowerCase().includes('meritocratic')) {
                    analysis.culture.push('Meritocratic progression based on performance rather than tenure');
                }
            }
            
            // Growth Trajectory - Role and company specific
            if (jobType === 'IT Support') {
                if (reputation.status === 'Established') {
                    analysis.growth.push('Structured career path from support to specialization');
                    analysis.growth.push('Training programs and certification support');
                    analysis.growth.push('Opportunity to move into architecture or management tracks');
                } else {
                    analysis.growth.push('Fast-track to senior technical roles as team grows');
                    analysis.growth.push('Broad exposure to different technologies and projects');
                    analysis.growth.push('Influence on technology stack and process decisions');
                }
            } else if (jobType === 'Wealth Management') {
                analysis.growth.push('Career progression tied to AUM growth and client acquisition');
                analysis.growth.push('Partnership potential based on book of business development');
                analysis.growth.push('Increasing autonomy and revenue share with seniority');
            } else if (jobType === 'Sales/BD') {
                analysis.growth.push('Clear progression from individual contributor to team leadership');
                analysis.growth.push('Commission and territory expansion with proven performance');
                analysis.growth.push('Potential move to strategic accounts or product management');
            } else if (jobType === 'Private Equity') {
                if (reputation.status === 'Established') {
                    analysis.growth.push('Traditional 2-year analyst/associate promotion cycles');
                    analysis.growth.push('Carry participation typically begins at VP level');
                    analysis.growth.push('Strong PE alumni network for exit opportunities');
                } else {
                    analysis.growth.push('Accelerated progression based on deal contributions');
                    analysis.growth.push('Early carry participation possible for key contributors');
                    analysis.growth.push('Broader deal exposure across sectors and stages');
                }
            } else {
                // Generic growth trajectory based on company size
                if (job.company_size === 'large' || reputation.status === 'Established') {
                    analysis.growth.push('Structured career progression with defined timelines');
                    analysis.growth.push('Established mentorship and development programs');
                    analysis.growth.push('Internal mobility across departments and geographies');
                } else {
                    analysis.growth.push('Fast growth environment with rapid advancement potential');
                    analysis.growth.push('Broader responsibilities and skill development');
                    analysis.growth.push('Direct impact on company growth and direction');
                }
            }
            
            return analysis;
        }
        
        /**
         * Generate detailed role intelligence
         */
        generateRoleIntelligence(job) {
            const intelligence = {
                requirements: [],
                progression: [],
                metrics: []
            };
            
            // Detect job type for appropriate analysis
            const jobType = job.job_type || this.detectJobType(job.title);
            const seniority = job.seniority_level || this.detectSeniorityFromTitle(job.title);
            
            // Parse actual requirements from job data - filter out date artifacts
            const cleanRequirements = (reqs) => {
                if (!reqs) return [];
                return reqs.filter(req => {
                    if (!req) return false;
                    const reqStr = req.toString();
                    // Filter out date-related strings and empty values
                    return !reqStr.includes('weeks ago') && 
                           !reqStr.includes('days ago') && 
                           !reqStr.includes('hours ago') &&
                           reqStr.length > 10; // Must be meaningful content
                });
            };
            
            // Extract clean requirements from job data
            const hasValidSkills = job.skills && job.skills.length > 0 && 
                                  cleanRequirements(job.skills).length > 0;
            const hasValidReqs = job.key_requirements && job.key_requirements.length > 0 && 
                                cleanRequirements(job.key_requirements).length > 0;
            
            // Use actual job data first
            if (hasValidSkills) {
                const cleanSkills = cleanRequirements(job.skills);
                cleanSkills.slice(0, 4).forEach(skill => {
                    intelligence.requirements.push(skill);
                });
            } else if (hasValidReqs) {
                const cleanReqs = cleanRequirements(job.key_requirements);
                cleanReqs.slice(0, 4).forEach(req => {
                    intelligence.requirements.push(req);
                });
            }
            
            // Add sophisticated role-specific requirements based on job type
            if (jobType === 'Private Equity') {
                if (intelligence.requirements.length === 0) {
                    intelligence.requirements.push('Advanced LBO modeling: Multiple debt tranches, complex waterfalls, PIK toggles, equity rollovers');
                    intelligence.requirements.push('Deal sourcing: Proprietary network, intermediary relationships, corporate carve-out identification');
                    intelligence.requirements.push('Portfolio value creation: 100-day plans, margin expansion, digital transformation, buy-and-build strategies');
                    intelligence.requirements.push('Due diligence mastery: QofE analysis, commercial DD, operational improvement identification');
                    intelligence.requirements.push('Fund mechanics: LP reporting, capital calls, distribution waterfalls, management fee calculations');
                }
                // Add hidden requirements for PE
                if (title.includes('vp') || title.includes('principal')) {
                    intelligence.requirements.push('Unwritten: Board observer/director experience strongly preferred');
                    intelligence.requirements.push('Unwritten: Existing LP relationships for future fundraising');
                }
                if (title.includes('associate')) {
                    intelligence.requirements.push('Unwritten: CFA progress or completion highly valued');
                    intelligence.requirements.push('Unwritten: Sector expertise in 1-2 core verticals expected');
                }
            } else if (jobType === 'Asset Management') {
                if (intelligence.requirements.length === 0) {
                    intelligence.requirements.push('Portfolio construction: Optimization theory, factor models, risk parity, Black-Litterman implementation');
                    intelligence.requirements.push('Alpha generation: Verifiable track record, Information Ratio >1.0, consistent outperformance vs benchmark');
                    intelligence.requirements.push('Research process: Primary research, expert networks, alternative data, mosaic theory application');
                    intelligence.requirements.push('Risk management: VaR, stress testing, drawdown control, correlation analysis, liquidity management');
                    intelligence.requirements.push('Client skills: Institutional presentations, consultant relations, performance attribution, market commentary');
                    intelligence.requirements.push('Regulatory compliance: UCITS, AIFMD, MiFID II, Form PF, best execution');
                }
                if (title.includes('portfolio manager')) {
                    intelligence.requirements.push('Unwritten: P&L responsibility of $500M-2B+ expected from day one');
                    intelligence.requirements.push('Unwritten: Established buy-side network for idea generation and market intelligence');
                    intelligence.requirements.push('Unwritten: Must maintain investment discipline during redemption pressures');
                }
            } else if (jobType === 'Investment Banking') {
                if (intelligence.requirements.length === 0) {
                    intelligence.requirements.push('Financial modeling excellence: Merger models (accretion/dilution), LBOs (returns analysis), DCFs (WACC, terminal value)');
                    intelligence.requirements.push('Deal execution: CIM drafting, buyer outreach, data room management, due diligence coordination');
                    intelligence.requirements.push('Materials mastery: Pitch books (100+ pages), board presentations, fairness opinions, valuation analyses');
                    intelligence.requirements.push('Client management: C-suite relationship building, difficult conversation navigation, expectation management');
                    intelligence.requirements.push('Sector expertise: Industry KPIs, strategic landscape, precedent transactions, market multiples');
                    intelligence.requirements.push('Process management: Multiple deal streams, competing deadlines, internal/external stakeholder coordination');
                }
                intelligence.requirements.push('Unwritten: 80-100 hour weeks standard, protected Saturday policy often ignored');
                intelligence.requirements.push('Unwritten: Face time culture persists - leaving before MD sends poor signal');
                intelligence.requirements.push('Unwritten: Technical perfection expected - no typos, perfect formatting, flawless models');
                intelligence.requirements.push('Unwritten: Strong preference for bulge bracket training');
            } else if (jobType === 'Hedge Fund') {
                if (intelligence.requirements.length === 0) {
                    intelligence.requirements.push('Edge identification: Variant perception, information asymmetry, behavioral inefficiencies, structural opportunities');
                    intelligence.requirements.push('Risk management: Position sizing (Kelly criterion), stop losses, correlation hedging, tail risk protection');
                    intelligence.requirements.push('Quantitative skills: Statistical arbitrage, factor modeling, backtesting, Python/R/MATLAB proficiency');
                    intelligence.requirements.push('Trading execution: Market microstructure understanding, order types, dark pools, algorithmic trading');
                    intelligence.requirements.push('Portfolio construction: Long/short balance, gross/net exposure management, sector neutrality, factor exposures');
                    intelligence.requirements.push('Research process: Alternative data, expert networks, channel checks, forensic accounting');
                }
                intelligence.requirements.push('Unwritten: Must generate 15-20% net returns (after 2/20) to justify existence');
                intelligence.requirements.push('Unwritten: Mental resilience for 50%+ drawdowns - ability to size up when others capitulate');
                intelligence.requirements.push('Unwritten: 24/7 market monitoring expected - global macro events impact all strategies');
                intelligence.requirements.push('Unwritten: Personal capital investment expected - skin in the game matters to LPs');
            } else if (jobType === 'Venture Capital') {
                if (intelligence.requirements.length === 0) {
                    intelligence.requirements.push('Network within startup ecosystem and founder communities');
                    intelligence.requirements.push('Thesis-driven investment approach in specific verticals');
                    intelligence.requirements.push('Board governance and strategic advisory experience');
                    intelligence.requirements.push('Understanding of venture mechanics: SAFEs, priced rounds, liquidation preferences');
                }
                intelligence.requirements.push('Unwritten: Personal brand and thought leadership expected');
                intelligence.requirements.push('Unwritten: Operator experience highly valued');
            } else if (jobType === 'Credit/Special Situations') {
                if (intelligence.requirements.length === 0) {
                    intelligence.requirements.push('Deep credit analysis: covenant review, recovery analysis, waterfall modeling');
                    intelligence.requirements.push('Restructuring experience: DIP financing, 363 sales, Chapter 11');
                    intelligence.requirements.push('Distressed debt valuation and fulcrum security identification');
                    intelligence.requirements.push('Legal document review and negotiation skills');
                }
            } else if (jobType === 'Real Estate') {
                if (intelligence.requirements.length === 0) {
                    intelligence.requirements.push('ARGUS modeling and development feasibility analysis');
                    intelligence.requirements.push('Asset management: leasing, capex planning, disposition strategy');
                    intelligence.requirements.push('Capital markets knowledge: CMBS, construction finance, JV structuring');
                    intelligence.requirements.push('Market analysis: comps, rent rolls, absorption rates');
                }
            } else if (jobType === 'Infrastructure') {
                if (intelligence.requirements.length === 0) {
                    intelligence.requirements.push('Project finance modeling with complex capital structures');
                    intelligence.requirements.push('PPP/PFI transaction experience and government relations');
                    intelligence.requirements.push('Technical due diligence coordination and asset lifecycle understanding');
                    intelligence.requirements.push('Regulated asset knowledge: RAB models, concession agreements');
                }
            } else if (jobType === 'Investment Research') {
                if (intelligence.requirements.length === 0) {
                    intelligence.requirements.push('Financial statement analysis and earnings modeling');
                    intelligence.requirements.push('Primary research: expert networks, channel checks, surveys');
                    intelligence.requirements.push('Written communication: initiation reports, sector primers');
                    intelligence.requirements.push('Regulatory knowledge: MiFID II unbundling, research standards');
                }
            } else if (jobType === 'Finance Operations') {
                if (intelligence.requirements.length === 0) {
                    intelligence.requirements.push('Fund accounting: NAV calculation, investor reporting');
                    intelligence.requirements.push('Trade lifecycle: confirmation, settlement, reconciliation');
                    intelligence.requirements.push('Regulatory reporting: AIFMD, Form PF, CPO-PQR');
                    intelligence.requirements.push('System expertise: Bloomberg AIM, SimCorp, BlackRock Aladdin');
                }
            } else if (jobType === 'Wealth Management') {
                if (intelligence.requirements.length === 0) {
                    intelligence.requirements.push('UHNW relationship management and trust building');
                    intelligence.requirements.push('Complex wealth structuring: trusts, foundations, family governance');
                    intelligence.requirements.push('Investment advisory across traditional and alternative assets');
                    intelligence.requirements.push('Multi-generational wealth transfer and estate planning');
                }
            } else if (jobType === 'IT Support') {
                if (intelligence.requirements.length === 0) {
                    intelligence.requirements.push('Experience with ticketing systems (ServiceNow, JIRA, Remedy)');
                    intelligence.requirements.push('Hardware and software troubleshooting expertise');
                    intelligence.requirements.push('Customer service orientation with technical communication skills');
                }
                if (job.technical_skills) {
                    intelligence.requirements.push(`Technical certifications preferred: ${job.technical_skills.join(', ')}`);
                }
            } else if (jobType === 'Sales/BD') {
                if (intelligence.requirements.length === 0) {
                    intelligence.requirements.push('Proven track record of meeting/exceeding sales targets');
                    intelligence.requirements.push('Strong relationship management and presentation skills');
                    intelligence.requirements.push('Understanding of target market and competitive landscape');
                }
            }
            
            if (job.technical_skills && job.technical_skills.length > 0) {
                intelligence.requirements.push(`Technical proficiency in ${job.technical_skills[0]} is non-negotiable`);
            }
            
            if (job.experience_years) {
                intelligence.requirements.push(`${job.experience_years}+ years of directly relevant experience required, less may be considered for exceptional candidates`);
            }
            
            // Add role-appropriate hidden requirements
            const title = (job.title || '').toLowerCase();
            if (jobType === 'Private Equity') {
                if (title.includes('vp') || title.includes('vice president')) {
                    intelligence.requirements.push('Unwritten expectation: MBA from top-tier program or equivalent experience');
                    intelligence.requirements.push('Deal execution track record with at least 2-3 closed transactions');
                } else if (title.includes('associate')) {
                    intelligence.requirements.push('Strong financial modeling skills with ability to build LBO models from scratch');
                    intelligence.requirements.push('Prior investment banking or consulting experience strongly preferred');
                }
            } else if (jobType === 'Wealth Management') {
                if (title.includes('director') || title.includes('vp')) {
                    intelligence.requirements.push('Existing book of business or strong network preferred');
                }
            } else if (jobType === 'IT Support') {
                if (title.includes('senior') || title.includes('lead')) {
                    intelligence.requirements.push('Experience mentoring junior team members expected');
                }
            }
            
            // Career Progression - Sophisticated finance-specific paths
            // title already declared above, reuse it
            
            if (jobType === 'Private Equity') {
                if (title.includes('analyst')) {
                    intelligence.progression.push('PE Analyst → Senior Analyst (2 years) → Associate (post-MBA or exceptional direct promote)');
                    intelligence.progression.push('Compensation trajectory: $100-150k base + 50-100% bonus → $150-200k + 75-125% → $250-350k + 100-150% (pre-carry)');
                    intelligence.progression.push('Exit opportunities: Top MBA programs (H/S/W), growth equity, venture capital, corp dev at portfolio companies');
                    intelligence.progression.push('Key milestone: Lead a platform acquisition or major add-on independently');
                    intelligence.progression.push('Bonus determinants: Deal contribution (40%), modeling quality (30%), sourcing (20%), firm performance (10%)');
                } else if (title.includes('associate')) {
                    intelligence.progression.push('Associate → Senior Associate (2-3 years) → VP (3-4 years) → Principal (3-4 years)');
                    intelligence.progression.push('Carry participation: Typically starts at Senior Associate (0.25-0.5%), increasing to 1-2% at VP, 3-5% at Principal');
                    intelligence.progression.push('Compensation evolution: $275-350k → $400-600k → $700k-1.2M → $1.5-3M+ (including carry)');
                    intelligence.progression.push('Critical skills development: Board management, LP relations, thesis development, portfolio value creation');
                    intelligence.progression.push('Exit opportunities: Portfolio company C-suite ($500k-1M packages), launching own fund, strategic roles at LPs');
                    intelligence.progression.push('Political dynamics: Align with successful deal partners, avoid failed investments, build LP relationships');
                } else if (title.includes('vp') || title.includes('principal')) {
                    intelligence.progression.push('VP/Principal → Partner/MD (merit-based, typically 4-7 years)');
                    intelligence.progression.push('Carry economics: 2-3% at VP, 5-10% at Principal, 10-20% at Partner level');
                    intelligence.progression.push('Expectations: Source 2-3 deals annually, lead 1-2 platform investments, develop sector thesis');
                    intelligence.progression.push('Partnership criteria: Demonstrable sourcing network, LP relationships, successful exit track record');
                }
            } else if (jobType === 'Asset Management') {
                if (title.includes('analyst')) {
                    intelligence.progression.push('Research Analyst → Senior Analyst (2-3 years) → Associate PM (3-4 years) → PM (merit-based)');
                    intelligence.progression.push('Coverage expansion: 5-10 names → 15-20 names → Sector responsibility → Multi-sector PM');
                    intelligence.progression.push('Compensation: $100-150k → $150-250k → $300-500k → $1M+ (with performance)');
                    intelligence.progression.push('Key milestone: Generate consistent alpha in paper portfolio before live capital allocation');
                    intelligence.progression.push('Critical skill development: DCF mastery → Relative value → Portfolio construction → Risk management');
                    intelligence.progression.push('Client interaction: None → Quarterly updates → Leading presentations → Direct relationships');
                } else if (title.includes('portfolio manager')) {
                    intelligence.progression.push('PM → Senior PM → Partner/CIO track');
                    intelligence.progression.push('AUM progression: $500M → $1-2B → $5B+ → Multi-strategy oversight');
                    intelligence.progression.push('Compensation structure: Base + 10-20% of alpha generated (increasing with seniority)');
                    intelligence.progression.push('Exit opportunities: Launch own fund, family office CIO, sovereign wealth funds');
                    intelligence.progression.push('Risk budget expansion: 3% tracking error → 5% → 8% → Unconstrained mandate');
                    intelligence.progression.push('Team building: Solo PM → 2-3 analysts → 5+ person team → Multi-PM platform');
                } else {
                    intelligence.progression.push('Distribution/Client Service → Relationship Management → Business Development → Sales Leadership');
                    intelligence.progression.push('Account ownership: Support role → $100M accounts → $500M+ → Strategic relationships');
                    intelligence.progression.push('Revenue responsibility: None → $1-2M → $5-10M → $20M+ annually');
                }
            } else if (jobType === 'Investment Banking') {
                if (title.includes('analyst')) {
                    intelligence.progression.push('IB Analyst (2-3 year program) → Associate (post-MBA or A2A promote)');
                    intelligence.progression.push('Compensation: $110-130k base + $70-120k bonus → $175-225k base + $150-250k bonus');
                    intelligence.progression.push('Exit opportunities: PE (70% placement), HF, corp dev, MBA programs');
                    intelligence.progression.push('Promotion criteria: Top bucket bonuses, live deal execution, internal sponsors');
                } else if (title.includes('associate')) {
                    intelligence.progression.push('Associate → VP (3-4 years) → Director/SVP (3-4 years) → MD (4-6 years)');
                    intelligence.progression.push('Revenue responsibility: Support role → $1-5M quota → $10-20M → $50M+ book');
                    intelligence.progression.push('Compensation scaling: $400-600k → $700k-1.2M → $1.5-3M → $3M+ (heavily variable)');
                    intelligence.progression.push('Critical transition: VP level shift from execution to origination and client management');
                }
            } else if (jobType === 'Hedge Fund') {
                if (title.includes('analyst')) {
                    intelligence.progression.push('HF Analyst → Senior Analyst (2-3 years) → PM (high performers only)');
                    intelligence.progression.push('Capital allocation: Paper trading → $10-50M → $100-500M → $1B+ book');
                    intelligence.progression.push('Compensation: $150-200k + bonus → $250-400k → $500k-2M (formula-based)');
                    intelligence.progression.push('Make-or-break: Must generate 10-15% net returns consistently to advance');
                } else if (title.includes('pm') || title.includes('portfolio manager')) {
                    intelligence.progression.push('PM → Senior PM/Partner → Launch own fund or Co-CIO');
                    intelligence.progression.push('Economics: 15-20% of P&L → 20-30% → Seed deal for own fund');
                    intelligence.progression.push('Risk limits expansion: $500M → $1-2B → Multi-strategy allocation');
                }
            } else if (jobType === 'Venture Capital') {
                intelligence.progression.push('VC Analyst/Associate → Principal (3-5 years) → Partner (5-7 years) → GP/Managing Partner');
                intelligence.progression.push('Carry progression: 0% → 0.5-1% → 2-5% → 10-20% of fund carry');
                intelligence.progression.push('Investment authority: Source deals → Lead Series A/B → Board seats → Investment committee');
                intelligence.progression.push('Critical metrics: IRR/multiple on invested capital, successful exits, founder references');
            } else if (jobType === 'Credit/Special Situations') {
                intelligence.progression.push('Credit Analyst → Senior Analyst → VP → Director/Partner');
                intelligence.progression.push('Complexity progression: Performing credit → Stressed → Distressed → Restructuring lead');
                intelligence.progression.push('Compensation: $125-175k → $200-300k → $400-700k → $1M+ with carry');
                intelligence.progression.push('Exit opportunities: Distressed PE, credit hedge funds, restructuring advisory');
            } else if (jobType === 'Real Estate') {
                intelligence.progression.push('RE Analyst → Associate (2-3 years) → VP (3-4 years) → Principal/Partner');
                intelligence.progression.push('Deal size progression: $10-50M → $50-200M → $200M-1B → Portfolio strategy');
                intelligence.progression.push('Promote participation: 0% → 5-10% deal promote → 10-20% → GP stake');
                intelligence.progression.push('Specialization paths: Acquisitions, asset management, development, capital markets');
            } else if (jobType === 'Infrastructure') {
                intelligence.progression.push('Infrastructure Analyst → Associate → VP → Director/Partner');
                intelligence.progression.push('Asset focus: Greenfield development → Brownfield → Operating assets → Portfolio management');
                intelligence.progression.push('Typical timeline: 2-3 years per level with increasing government/regulatory interface');
                intelligence.progression.push('Exit opportunities: Infrastructure funds at LPs, project development, government advisory');
            } else if (jobType === 'Investment Research') {
                intelligence.progression.push('Research Associate → Analyst (2-3 years) → Senior Analyst (3-5 years) → Sector Head/Director');
                intelligence.progression.push('Coverage progression: Support role → 5-10 companies → 15-20 companies → Sector oversight');
                intelligence.progression.push('Buy-side transition opportunities after 3-5 years to AM/HF analyst roles');
                intelligence.progression.push('Compensation: $85-100k → $150-250k → $300-500k (top-ranked analysts)');
            } else if (jobType === 'Wealth Management') {
                if (title.includes('associate')) {
                    intelligence.progression.push('Associate → Senior Associate (2-3 years) → VP/Director (3-5 years) → MD/Partner');
                    intelligence.progression.push('AUM progression: Support role → $50-100M → $250-500M → $1B+ book');
                    intelligence.progression.push('Revenue share: 0% → 10-20% → 30-40% → 50%+ of revenue generated');
                    intelligence.progression.push('Client progression: Support senior advisors → Inherit smaller accounts → Direct UHNW relationships');
                } else {
                    intelligence.progression.push('Advisor → Senior Advisor → Partner/MD');
                    intelligence.progression.push('Book building through client referrals, inheritance, and new acquisition');
                    intelligence.progression.push('Economics shift from salary to revenue-based compensation');
                }
            } else if (jobType === 'Finance Operations') {
                intelligence.progression.push('Operations Analyst → Senior Analyst → AVP/Manager → VP → Director');
                intelligence.progression.push('Scope expansion: Single function → Multi-function → Regional → Global responsibility');
                intelligence.progression.push('Technical to strategic: Process execution → Process improvement → Strategy and transformation');
                intelligence.progression.push('Compensation: $60-80k → $80-120k → $120-180k → $200k+');
            } else if (jobType === 'IT Support') {
                if (title.includes('analyst') || title.includes('specialist')) {
                    intelligence.progression.push('Service Desk → Senior Analyst (2 years) → Team Lead (2-3 years)');
                    intelligence.progression.push('Specialization paths: Infrastructure, Security, Cloud, Applications');
                    intelligence.progression.push('Certifications drive advancement: ITIL, CompTIA, Microsoft, AWS');
                } else {
                    intelligence.progression.push('Technical career path with specialization opportunities');
                    intelligence.progression.push('Management track available after 3-5 years');
                }
            } else if (jobType === 'Sales/BD') {
                intelligence.progression.push('Individual contributor → Team lead → Regional manager → Director');
                intelligence.progression.push('Commission structure typically increases with seniority and territory size');
                intelligence.progression.push('Top performers can move to strategic accounts or product leadership');
            } else {
                // Generic progression for other roles
                intelligence.progression.push(`${seniority} → Senior ${seniority} (2-3 years) → Next level (3-5 years)`);
                intelligence.progression.push('Progression based on performance and business impact');
                intelligence.progression.push('Lateral moves to gain broader experience often beneficial');
            }
            
            // Success Metrics - Sophisticated finance-specific KPIs
            if (jobType === 'Private Equity') {
                intelligence.metrics.push('First 30 days: Complete deep dive on entire portfolio (20+ companies), understand value creation plans');
                intelligence.metrics.push('First 90 days: Lead due diligence workstream, build 3-statement model, present IC memo');
                intelligence.metrics.push('First 6 months: Source 5-10 proprietary opportunities, advance 1-2 to LOI stage');
                intelligence.metrics.push('First year: Close 1-2 platform investments, identify 3-5 add-on targets, achieve 25% IRR on paper');
                intelligence.metrics.push('Performance metrics: Deal flow generation, model accuracy, portfolio company board impact');
                intelligence.metrics.push('Hidden expectations: 80+ hour weeks during deals, weekend availability, travel 30-40% for due diligence');
                intelligence.metrics.push('Political capital: Build relationships with operating partners, win internal deal competition, gain MD sponsorship');
            } else if (jobType === 'Asset Management') {
                intelligence.metrics.push('First 30 days: Master existing portfolio holdings, understand investment process and risk framework');
                intelligence.metrics.push('First 90 days: Present 2-3 investment ideas with full thesis, contribute to portfolio positioning');
                intelligence.metrics.push('First 6 months: Generate positive alpha on paper portfolio, establish research coverage');
                intelligence.metrics.push('First year: Achieve top quartile performance, expand coverage universe, lead client presentations');
                intelligence.metrics.push('Key metrics: Information ratio >1.0, hit rate >55%, Sharpe ratio >1.5, 8-10 actionable ideas monthly');
                intelligence.metrics.push('Research depth: 50+ management meetings, 100+ expert calls, 10+ site visits annually');
                intelligence.metrics.push('Client impact: Contribute to quarterly letters, lead 5+ client meetings, develop thought leadership pieces');
            } else if (jobType === 'Investment Banking') {
                intelligence.metrics.push('First 30 days: Master financial modeling tests, learn proprietary tools, understand deal pipeline');
                intelligence.metrics.push('First 90 days: Run live deal processes, manage data rooms, coordinate due diligence');
                intelligence.metrics.push('First 6 months: Lead execution on 2-3 transactions, develop direct client relationships');
                intelligence.metrics.push('First year: Contribute to $100M+ in fees, receive top-tier bonus ranking');
                intelligence.metrics.push('Performance indicators: Modeling accuracy, client feedback, deal contribution, utilization rate');
            } else if (jobType === 'Hedge Fund') {
                intelligence.metrics.push('First 30 days: Master fund strategy, risk limits, prime broker relationships, existing portfolio construction');
                intelligence.metrics.push('First 90 days: Generate 3-5 high-conviction ideas with 3:1 risk/reward, detailed thesis, catalyst identification');
                intelligence.metrics.push('First 6 months: Run paper portfolio achieving 10%+ returns, earn small real capital allocation ($10-50M)');
                intelligence.metrics.push('First year: Generate 15-20% net returns, Sharpe >2.0, win rate >55%, max drawdown <10%');
                intelligence.metrics.push('Performance metrics: Daily P&L tracking, attribution analysis, hit rate, avg win/loss, portfolio turnover');
                intelligence.metrics.push('Risk metrics: VaR utilization, stress test results, correlation to broader portfolio, liquidity profile');
                intelligence.metrics.push('Hidden reality: One bad quarter can end your career - consistency matters more than home runs');
            } else if (jobType === 'Venture Capital') {
                intelligence.metrics.push('First 30 days: Map ecosystem in focus sectors (AI, fintech, SaaS, etc.), attend 5+ industry events');
                intelligence.metrics.push('First 90 days: Source 20-30 deals, advance 3-5 to partner meetings, build relationships with 10+ founders');
                intelligence.metrics.push('First 6 months: Lead due diligence on 2-3 investments, develop 1-2 investment theses, publish thought leadership');
                intelligence.metrics.push('First year: Close 1-2 investments, achieve 1 board observer seat, establish sector expertise reputation');
                intelligence.metrics.push('Success metrics: Deal flow quality (tier-1 founders), founder NPS (>50), portfolio support impact (measurable KPIs)');
                intelligence.metrics.push('Network building: 100+ founder meetings, 50+ co-investor relationships, 20+ downstream investor connections');
                intelligence.metrics.push('Hidden dynamics: Personal brand matters - Twitter followers, blog posts, podcast appearances all count');
            } else if (jobType === 'Credit/Special Situations') {
                intelligence.metrics.push('First 30 days: Master credit documentation, understand existing portfolio and watchlist');
                intelligence.metrics.push('First 90 days: Complete credit analysis on 5-10 names, present investment recommendations');
                intelligence.metrics.push('First 6 months: Identify distressed opportunities, model recovery scenarios');
                intelligence.metrics.push('First year: Generate 12-15% returns on recommendations, lead restructuring workstream');
            } else if (jobType === 'Real Estate') {
                intelligence.metrics.push('First 30 days: Tour portfolio properties, understand asset business plans');
                intelligence.metrics.push('First 90 days: Underwrite 10-15 acquisitions, present investment committee memos');
                intelligence.metrics.push('First 6 months: Close 1-2 acquisitions, implement value-add strategies');
                intelligence.metrics.push('First year: Achieve 15-20% IRR on investments, expand sourcing network');
            } else if (jobType === 'Infrastructure') {
                intelligence.metrics.push('First 30 days: Understand portfolio assets, regulatory frameworks, and concession agreements');
                intelligence.metrics.push('First 90 days: Build complex project finance model, evaluate 3-5 opportunities');
                intelligence.metrics.push('First 6 months: Lead due diligence on infrastructure investment');
                intelligence.metrics.push('First year: Close transaction, manage government stakeholder relationships');
            } else if (jobType === 'Investment Research') {
                intelligence.metrics.push('First 30 days: Complete initiation reports on 2-3 companies, establish management relationships');
                intelligence.metrics.push('First 90 days: Publish differentiated research, participate in earnings calls');
                intelligence.metrics.push('First 6 months: Build industry expert network, conduct primary research');
                intelligence.metrics.push('First year: Achieve top-3 sector ranking in client votes, generate actionable ideas');
            } else if (jobType === 'Finance Operations') {
                intelligence.metrics.push('First 30 days: Master operational workflows, understand control framework');
                intelligence.metrics.push('First 90 days: Identify process improvements, reduce operational risk');
                intelligence.metrics.push('First 6 months: Implement automation initiatives, improve STP rates');
                intelligence.metrics.push('First year: Achieve 99%+ accuracy, reduce costs by 10-15%');
            } else if (jobType === 'Wealth Management') {
                intelligence.metrics.push('First 30 days: Complete regulatory licensing, understand client segmentation');
                intelligence.metrics.push('First 90 days: Shadow senior advisors, begin managing smaller accounts');
                intelligence.metrics.push('First 6 months: Generate $10M+ in new AUM, deepen existing relationships');
                intelligence.metrics.push('First year: Build $50M+ book, achieve 95%+ client retention');
            } else if (jobType === 'IT Support') {
                intelligence.metrics.push('First 30 days: Complete training, understand ticketing system and SLAs');
                intelligence.metrics.push('First 90 days: Handle tickets independently, maintain 95%+ customer satisfaction');
                intelligence.metrics.push('First year: Become specialist in 1-2 technical areas, mentor new team members');
            } else if (jobType === 'Sales/BD') {
                intelligence.metrics.push('First 30 days: Complete product training, understand sales process and CRM');
                intelligence.metrics.push('First 90 days: Build pipeline, close first deals, achieve 50% of quota');
                intelligence.metrics.push('First year: Exceed annual quota, develop key account relationships');
            } else {
                intelligence.metrics.push('First 30 days: Understand role responsibilities and team dynamics');
                intelligence.metrics.push('First 90 days: Deliver initial projects and demonstrate value');
                intelligence.metrics.push('First year: Achieve performance goals and establish expertise');
            }
            
            if (job.department) {
                intelligence.metrics.push(`Specific to ${job.department}: Establish yourself as go-to person for sector coverage`);
            }
            
            return intelligence;
        }
        
        /**
         * Format salary display
         */
        formatSalary(job) {
            if (job.salary_display) {
                return job.salary_display;
            }
            
            if (job.salary_min && job.salary_max) {
                const min = (job.salary_min / 1000).toFixed(0);
                const max = (job.salary_max / 1000).toFixed(0);
                
                // Determine currency
                const currency = job.currency === 'USD' ? '$' : 
                                job.currency === 'GBP' ? '£' : 
                                job.currency === 'EUR' ? '€' : '$';
                
                return `${currency}${min}k-${currency}${max}k`;
            }
            
            return 'Competitive';
        }
        
        /**
         * Render error state
         */
        renderError(error) {
            return `
                <div class="intelligence-package">
                    <div class="intel-section">
                        <p style="color: #DC2626;">Unable to generate intelligence briefing. Please try again.</p>
                    </div>
                </div>
            `;
        }
        
        /**
         * Fetch job data
         */
        async fetchJobData(jobId) {
            // First try to get from existing job cards - check multiple possible selectors
            const $jobCard = $(`.job-card-vogue[data-job-id="${jobId}"], .sffc-job-card[data-job-id="${jobId}"], .pe-job-card[data-job-id="${jobId}"]`).first();
            
            if ($jobCard.length > 0) {
                // Extract ALL available data from card and data attributes
                const jobData = {
                    id: jobId,
                    title: $jobCard.find('.sffc-job-title, .job-title, h3').first().text().trim(),
                    company: $jobCard.find('.sffc-company-name, .company-name').first().text().split('•')[0].trim(),
                    location: $jobCard.find('.sffc-location, .job-location').text().trim() || 
                             $jobCard.find('.sffc-company-name').text().split('•')[1]?.trim() || 
                             'Location not specified',
                    
                    // Extract from data attributes if available
                    sffc_job_title: $jobCard.attr('data-sffc_job_title') || $jobCard.attr('data-title'),
                    sffc_company: $jobCard.attr('data-sffc_company') || $jobCard.attr('data-company'),
                    sffc_location: $jobCard.attr('data-sffc_location') || $jobCard.attr('data-location'),
                    sffc_application_url: $jobCard.attr('data-sffc_application_url') || $jobCard.attr('data-application-url'),
                    
                    // Parse salary information
                    salary_display: $jobCard.find('.sffc-salary, .salary-range').text().trim() ||
                                   $jobCard.find('.sffc-company-name').text().split('•')[2]?.trim(),
                    salary_min: parseInt($jobCard.attr('data-salary-min')) || null,
                    salary_max: parseInt($jobCard.attr('data-salary-max')) || null,
                    
                    // Extract skills and requirements
                    skills: $jobCard.find('.sffc-job-tag, .job-skill, .skill-tag').map((i, el) => $(el).text().trim()).get(),
                    key_requirements: $jobCard.attr('data-requirements')?.split(',').map(r => r.trim()) || [],
                    
                    // Get full description if available
                    description: $jobCard.attr('data-description') || 
                                $jobCard.find('.job-description').text() || 
                                '',
                    
                    // Additional metadata
                    seniority_level: $jobCard.attr('data-seniority') || this.detectSeniorityFromTitle($jobCard.find('.sffc-job-title').text()),
                    experience_years: parseInt($jobCard.attr('data-experience-years')) || null,
                    company_size: $jobCard.attr('data-company-size'),
                    department: $jobCard.attr('data-department'),
                    job_type: $jobCard.attr('data-job-type') || this.detectJobType($jobCard.find('.sffc-job-title').text()),
                    posted_date: $jobCard.attr('data-posted-date'),
                    application_deadline: $jobCard.attr('data-deadline')
                };
                
                // Use sffc_ prefixed values as primary if available
                if (jobData.sffc_job_title) jobData.title = jobData.sffc_job_title;
                if (jobData.sffc_company) jobData.company = jobData.sffc_company;
                if (jobData.sffc_location) jobData.location = jobData.sffc_location;
                if (jobData.sffc_application_url) jobData.application_url = jobData.sffc_application_url;
                
                return jobData;
            }
            
            // Fallback to AJAX call
            return new Promise((resolve, reject) => {
                // Check if sffc_ajax is available
                if (typeof sffc_ajax === 'undefined' || !sffc_ajax) {
                    reject(new Error('AJAX configuration not available'));
                    return;
                }
                
                $.ajax({
                    url: sffc_ajax.url,
                    type: 'POST',
                    data: {
                        action: 'sffc_get_job_details',
                        nonce: sffc_ajax.nonce,
                        job_id: jobId
                    },
                    success: (response) => {
                        if (response.success) {
                            resolve(response.data);
                        } else {
                            reject(new Error(response.data || 'Failed to fetch job details'));
                        }
                    },
                    error: (xhr, status, error) => {
                        reject(error);
                    }
                });
            });
        }
        
        /**
         * Detect seniority level from job title
         */
        detectSeniorityFromTitle(title) {
            const titleLower = (title || '').toLowerCase();
            if (titleLower.includes('junior') || titleLower.includes('entry')) return 'Entry Level';
            if (titleLower.includes('senior')) return 'Senior';
            if (titleLower.includes('director')) return 'Director';
            if (titleLower.includes('vp') || titleLower.includes('vice president')) return 'VP';
            if (titleLower.includes('partner') || titleLower.includes('managing director')) return 'Partner/MD';
            if (titleLower.includes('analyst')) return 'Analyst';
            if (titleLower.includes('associate')) return 'Associate';
            if (titleLower.includes('manager')) return 'Manager';
            return 'Mid-Senior';
        }
        
        /**
         * Detect job type from title
         */
        detectJobType(title) {
            const titleLower = (title || '').toLowerCase();
            
            // Private Equity roles - HIGHEST PRIORITY
            if (titleLower.includes('private equity') || 
                (titleLower.includes('pe ') && (titleLower.includes('associate') || titleLower.includes('analyst'))) ||
                titleLower.includes('leveraged finance') || titleLower.includes('lbo') ||
                titleLower.includes('buyout') || titleLower.includes('growth equity')) {
                return 'Private Equity';
            }
            
            // Asset Management roles
            if (titleLower.includes('asset management') || titleLower.includes('portfolio manager') ||
                titleLower.includes('investment manager') || titleLower.includes('fund manager') ||
                (titleLower.includes('portfolio') && !titleLower.includes('wealth')) ||
                titleLower.includes('aum') || titleLower.includes('institutional sales')) {
                return 'Asset Management';
            }
            
            // Investment Banking
            if (titleLower.includes('investment banking') || titleLower.includes('ibd ') ||
                titleLower.includes('m&a') || titleLower.includes('mergers') ||
                titleLower.includes('capital markets') || titleLower.includes('ecm') || 
                titleLower.includes('dcm') || titleLower.includes('coverage')) {
                return 'Investment Banking';
            }
            
            // Hedge Funds
            if (titleLower.includes('hedge fund') || titleLower.includes('quant') ||
                titleLower.includes('systematic') || titleLower.includes('alpha') ||
                titleLower.includes('trading') && (titleLower.includes('analyst') || titleLower.includes('pm'))) {
                return 'Hedge Fund';
            }
            
            // Venture Capital
            if (titleLower.includes('venture') || titleLower.includes('vc ') ||
                titleLower.includes('seed') || titleLower.includes('early stage')) {
                return 'Venture Capital';
            }
            
            // Credit/Debt
            if (titleLower.includes('credit') || titleLower.includes('debt') ||
                titleLower.includes('distressed') || titleLower.includes('special situations') ||
                titleLower.includes('restructuring')) {
                return 'Credit/Special Situations';
            }
            
            // Real Estate
            if (titleLower.includes('real estate') || titleLower.includes('reit') ||
                titleLower.includes('property')) {
                return 'Real Estate';
            }
            
            // Infrastructure
            if (titleLower.includes('infrastructure') && !titleLower.includes('it')) {
                return 'Infrastructure';
            }
            
            // Wealth Management/Private Banking
            if (titleLower.includes('wealth') || titleLower.includes('private bank') || 
                titleLower.includes('family office') || titleLower.includes('client advisor') ||
                titleLower.includes('relationship manager')) {
                return 'Wealth Management';
            }
            
            // Finance Operations/Middle Office
            if (titleLower.includes('middle office') || titleLower.includes('fund accounting') ||
                titleLower.includes('fund admin') || titleLower.includes('valuations') ||
                titleLower.includes('settlements')) {
                return 'Finance Operations';
            }
            
            // Risk Management
            if (titleLower.includes('risk') || titleLower.includes('compliance') ||
                titleLower.includes('regulatory')) {
                return 'Risk/Compliance';
            }
            
            // Research
            if (titleLower.includes('equity research') || titleLower.includes('credit research') ||
                (titleLower.includes('research') && (titleLower.includes('analyst') || titleLower.includes('associate')))) {
                return 'Investment Research';
            }
            
            // IT/Technology roles
            if (titleLower.includes('service desk') || titleLower.includes('it support') || 
                titleLower.includes('help desk') || titleLower.includes('technical support')) {
                return 'IT Support';
            }
            if (titleLower.includes('software') || titleLower.includes('developer') || 
                titleLower.includes('engineer') && (titleLower.includes('front') || titleLower.includes('back') || titleLower.includes('full'))) {
                return 'Software Engineering';
            }
            if (titleLower.includes('data') && (titleLower.includes('engineer') || titleLower.includes('scientist'))) {
                return 'Data/Analytics';
            }
            if (titleLower.includes('devops') || titleLower.includes('sre')) {
                return 'DevOps/Infrastructure';
            }
            
            // Sales/BD
            if (titleLower.includes('sales') || titleLower.includes('business development') || 
                titleLower.includes('bd ') || titleLower === 'bd') {
                return 'Sales/BD';
            }
            
            // Research/Analysis
            if (titleLower.includes('research') || (titleLower.includes('analyst') && 
                !titleLower.includes('service') && !titleLower.includes('data'))) {
                return 'Research/Analysis';
            }
            
            // Operations
            if (titleLower.includes('operations') || titleLower.includes('coo')) {
                return 'Operations';
            }
            
            // Risk/Compliance
            if (titleLower.includes('risk') || titleLower.includes('compliance') || 
                titleLower.includes('regulatory')) {
                return 'Risk/Compliance';
            }
            
            // Marketing/IR
            if (titleLower.includes('marketing') || titleLower.includes('investor relations') || 
                titleLower.includes(' ir ')) {
                return 'Marketing/IR';
            }
            
            return 'General';
        }
        
        /**
         * Load user profile
         */
        loadUserProfile() {
            // Load from localStorage or session
            const stored = localStorage.getItem('sffc_user_profile');
            if (stored) {
                try {
                    return JSON.parse(stored);
                } catch (e) {
                    console.error('Error parsing user profile:', e);
                }
            }
            
            // Default profile
            return {
                skills: [],
                experience_level: 'Mid-Senior',
                preferred_locations: ['London', 'New York'],
                experience_years: 5
            };
        }
        
        /**
         * Apply now action
         */
        applyNow(jobId) {
            const job = this.currentJob;
            // Use sffc_application_url as primary, fallback to application_url
            const appUrl = job?.sffc_application_url || job?.application_url || job?.link;
            
            if (job && appUrl) {
                // Track application start
                this.trackApplication(jobId, 'started');
                
                // Open application URL
                window.open(appUrl, '_blank');
                
                // Show follow-up message
                if (window.sennaConversational && window.sennaConversational.addSennaMessage) {
                    window.sennaConversational.addSennaMessage(
                        'I\'ve opened the application page for you. Remember to reference the key points from the intelligence briefing. Good luck!',
                        true
                    );
                }
            } else {
                console.error('No application URL available for job:', jobId);
                alert('Application URL not available. Please try again or contact support.');
            }
        }
        
        /**
         * Tailor CV action
         */
        tailorCV(jobId) {
            if (window.tailorCV) {
                window.tailorCV(jobId);
            }
        }
        
        /**
         * Save intelligence and job
         */
        saveIntelligence(jobId) {
            const job = this.currentJob;
            const intelligence = this.cache.get(jobId);
            
            if (job) {
                // Save job to saved jobs list
                let savedJobs = JSON.parse(localStorage.getItem('sffc_saved_jobs') || '[]');
                
                // Check if job already saved
                const existingIndex = savedJobs.findIndex(j => j.id === jobId);
                if (existingIndex === -1) {
                    // Add new saved job
                    savedJobs.push({
                        id: jobId,
                        title: job.title,
                        company: job.company,
                        location: job.location,
                        salary_display: job.salary_display,
                        saved_date: Date.now(),
                        application_url: job.sffc_application_url || job.application_url,
                        job_data: job
                    });
                    
                    // Keep only last 50 saved jobs
                    if (savedJobs.length > 50) {
                        savedJobs = savedJobs.slice(-50);
                    }
                    
                    localStorage.setItem('sffc_saved_jobs', JSON.stringify(savedJobs));
                }
                
                // Save intelligence briefing if available
                if (intelligence) {
                    const savedIntel = JSON.parse(localStorage.getItem('sffc_saved_intelligence') || '[]');
                    savedIntel.push({
                        jobId: jobId,
                        timestamp: Date.now(),
                        data: intelligence.data
                    });
                    localStorage.setItem('sffc_saved_intelligence', JSON.stringify(savedIntel));
                }
                
                // Save to database if user is logged in
                if (window.sffc_ajax && window.sffc_ajax.is_logged_in === '1') {
                    $.ajax({
                        url: window.sffc_ajax.url,
                        type: 'POST',
                        data: {
                            action: 'sffc_save_job',
                            nonce: window.sffc_ajax.nonce,
                            job_id: jobId,
                            job_data: JSON.stringify(job)
                        },
                        success: (response) => {
                            if (response.success) {
                                console.log('Job saved to database');
                            }
                        }
                    });
                }
                
                // Show confirmation with count
                const totalSaved = savedJobs.length;
                if (window.sennaConversational && window.sennaConversational.addSennaMessage) {
                    window.sennaConversational.addSennaMessage(
                        `✓ Role saved successfully! You now have ${totalSaved} saved ${totalSaved === 1 ? 'role' : 'roles'}. Access them anytime from "View Saved Roles" in the menu.`,
                        true
                    );
                } else {
                    alert(`Role saved! You have ${totalSaved} saved roles.`);
                }
            }
        }
        
        /**
         * Track application events
         */
        trackApplication(jobId, status) {
            const applications = JSON.parse(localStorage.getItem('sffc_applications') || '[]');
            applications.push({
                jobId: jobId,
                status: status,
                timestamp: Date.now()
            });
            localStorage.setItem('sffc_applications', JSON.stringify(applications));
        }
    }
    
    // Initialize and expose globally
    window.IntelligencePackage = IntelligencePackage;
    window.intelligencePackage = new IntelligencePackage();
    
})(jQuery);
