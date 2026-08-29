/**
 * Ultimate Dynamic Analyzer
 * Hyperpersonalized job analysis engine with data visualization
 */

class UltimateDynamicAnalyzer {
    constructor() {
        this.userProfile = null;
        this.savedJobs = [];
        this.currentJob = null;
        this.visualizations = {};
        this.isLoggedIn = false;
        
        this.init();
    }
    
    init() {
        this.loadUserProfile();
        this.loadSavedJobs();
        this.checkLoginStatus();
        this.setupDataConnectors();
    }
    
    /**
     * Load user profile from Profile Builder
     */
    loadUserProfile() {
        if (window.getCompleteProfileData) {
            this.userProfile = window.getCompleteProfileData();
        } else {
            try {
                const stored = localStorage.getItem('sffc_user_profile');
                this.userProfile = stored ? JSON.parse(stored) : null;
            } catch (e) {
                console.error('Failed to load profile:', e);
            }
        }
    }
    
    /**
     * Load saved jobs from localStorage
     */
    loadSavedJobs() {
        try {
            const savedJobs = localStorage.getItem('sffc_saved_jobs');
            this.savedJobs = savedJobs ? JSON.parse(savedJobs) : [];
        } catch (e) {
            console.error('Failed to load saved jobs:', e);
            this.savedJobs = [];
        }
    }
    
    /**
     * Check if user is logged in
     */
    checkLoginStatus() {
        this.isLoggedIn = !!(window.sffc_profile?.is_logged_in || window.sffc_ajax?.user_id);
    }
    
    /**
     * Setup real-time data connectors
     */
    setupDataConnectors() {
        // Listen for profile updates
        jQuery(document).on('profileBuilder:updated', (e, data) => {
            this.userProfile = data;
            this.refreshAnalysis();
        });
        
        // Listen for saved jobs changes
        jQuery(document).on('saved_jobs:updated', (e, data) => {
            this.savedJobs = data;
            this.refreshAnalysis();
        });
    }
    
    /**
     * Analyze a specific job with personalization
     */
    analyzeJob(job) {
        this.currentJob = job;
        
        const analysis = {
            matchScore: this.calculateMatchScore(job),
            matchBreakdown: this.generateMatchBreakdown(job),
            skillAnalysis: this.analyzeSkills(job),
            salaryAnalysis: this.analyzeSalary(job),
            competitionAnalysis: this.analyzeCompetition(job),
            strategies: this.generateStrategies(job),
            timeline: this.generateTimeline(job),
            jobBreakdown: this.breakdownJobDescription(job),
            keyRequirements: this.extractKeyRequirements(job),
            standoutStrategies: this.generateStandoutStrategies(job),
            qualificationAnalysis: this.analyzeQualifications(job),
            interviewPrep: this.generateInterviewPrep(job),
            industryInsights: this.getIndustryInsights(job),
            applicationPriority: this.calculateApplicationPriority(job),
            hiddenOpportunities: this.findHiddenOpportunities(job),
            redFlags: this.identifyRedFlags(job),
            growthPotential: this.assessGrowthPotential(job),
            cultureFit: this.analyzeCultureFit(job),
            negotiationPoints: this.identifyNegotiationPoints(job),
            visualizations: []
        };
        
        // Generate visualizations based on login status
        if (this.isLoggedIn && this.userProfile) {
            analysis.visualizations = this.generatePersonalizedVisualizations(job, analysis);
        } else {
            analysis.visualizations = this.generateBasicVisualizations(job, analysis);
        }
        
        return analysis;
    }
    
    /**
     * Calculate personalized match score
     */
    calculateMatchScore(job) {
        if (!this.isLoggedIn || !this.userProfile) {
            return this.calculateBasicMatchScore(job);
        }
        
        const weights = {
            skills: 0.35,
            experience: 0.25,
            location: 0.15,
            salary: 0.15,
            careerGoals: 0.10
        };
        
        let score = 0;
        
        // Skills matching
        if (this.userProfile.skills && job.requirements) {
            const skillMatch = this.calculateSkillMatch(
                this.userProfile.skills,
                this.extractSkillsFromJob(job)
            );
            score += skillMatch * weights.skills;
        }
        
        // Experience matching
        if (this.userProfile.years_experience && job.experience_required) {
            const expMatch = this.calculateExperienceMatch(
                this.userProfile.years_experience,
                job.experience_required
            );
            score += expMatch * weights.experience;
        }
        
        // Location matching
        if (this.userProfile.preferred_locations && job.location) {
            const locationMatch = this.calculateLocationMatch(
                this.userProfile.preferred_locations,
                job.location
            );
            score += locationMatch * weights.location;
        }
        
        // Salary alignment
        if (this.userProfile.salary_expectations && job.salary) {
            const salaryMatch = this.calculateSalaryMatch(
                this.userProfile.salary_expectations,
                job.salary
            );
            score += salaryMatch * weights.salary;
        }
        
        // Career goals alignment
        if (this.userProfile.career_priorities) {
            const careerMatch = this.calculateCareerGoalsMatch(
                this.userProfile.career_priorities,
                job
            );
            score += careerMatch * weights.careerGoals;
        }
        
        return Math.round(score * 100);
    }
    
    /**
     * Basic match score for logged-out users
     */
    calculateBasicMatchScore(job) {
        // Simple keyword matching
        const title = job.title?.toLowerCase() || '';
        const description = job.description?.toLowerCase() || '';
        
        let score = 50; // Base score
        
        // Boost for common keywords
        const keywords = ['analyst', 'manager', 'developer', 'engineer', 'coordinator'];
        keywords.forEach(keyword => {
            if (title.includes(keyword) || description.includes(keyword)) {
                score += 5;
            }
        });
        
        // Cap at 75 for non-logged in users
        return Math.min(score, 75);
    }
    
    /**
     * Analyze skills gap
     */
    analyzeSkills(job) {
        const jobSkills = this.extractSkillsFromJob(job);
        
        if (!this.isLoggedIn || !this.userProfile?.skills) {
            return {
                required: jobSkills,
                matched: [],
                gaps: jobSkills,
                matchPercentage: 0
            };
        }
        
        const userSkills = this.userProfile.skills;
        const matched = jobSkills.filter(skill => 
            userSkills.some(userSkill => 
                this.skillsAreSimilar(skill, userSkill)
            )
        );
        const gaps = jobSkills.filter(skill => !matched.includes(skill));
        
        return {
            required: jobSkills,
            matched: matched,
            gaps: gaps,
            matchPercentage: jobSkills.length > 0 
                ? Math.round((matched.length / jobSkills.length) * 100)
                : 0,
            recommendations: this.generateSkillRecommendations(gaps)
        };
    }
    
    /**
     * Generate skill recommendations
     */
    generateSkillRecommendations(gaps) {
        if (!gaps || gaps.length === 0) return [];
        
        return gaps.slice(0, 3).map(skill => {
            const skillLower = skill.toLowerCase();
            
            // Determine learning path based on skill type
            if (skillLower.includes('excel') || skillLower.includes('powerpoint')) {
                return {
                    skill: skill,
                    priority: 'High',
                    timeframe: '1-2 weeks',
                    resources: ['LinkedIn Learning Microsoft Office course', 'Practice with real datasets', 'YouTube tutorials']
                };
            } else if (skillLower.includes('python') || skillLower.includes('sql') || skillLower.includes('code')) {
                return {
                    skill: skill,
                    priority: 'High',
                    timeframe: '4-8 weeks',
                    resources: ['Codecademy or DataCamp', 'Build practice projects', 'GitHub portfolio']
                };
            } else if (skillLower.includes('management') || skillLower.includes('leadership')) {
                return {
                    skill: skill,
                    priority: 'Medium',
                    timeframe: '2-3 months',
                    resources: ['Management certification', 'Lead volunteer projects', 'Mentorship programs']
                };
            } else {
                return {
                    skill: skill,
                    priority: 'Medium',
                    timeframe: '2-4 weeks',
                    resources: ['Online courses', 'Industry publications', 'Professional workshops']
                };
            }
        });
    }
    
    /**
     * Analyze salary information
     */
    analyzeSalary(job) {
        // Try different salary formats
        const salaryValue = job.salary || job.salary_display || job.salary_max || job.salary_min;
        const jobSalary = this.parseSalary(salaryValue);
        
        if (!this.isLoggedIn || !this.userProfile?.salary_expectations) {
            return {
                range: salaryValue || 'Competitive',
                negotiable: true,
                market_comparison: 'Market rate',
                recommendation: 'Research similar roles for benchmarking'
            };
        }
        
        const expectedSalary = this.parseSalary(this.userProfile.salary_expectations);
        const comparison = jobSalary && expectedSalary ? 
            Math.round((jobSalary / expectedSalary) * 100) : null;
        
        return {
            range: salaryValue || 'Competitive',
            expected: this.userProfile.salary_expectations,
            comparison: comparison,
            negotiable: true,
            market_comparison: comparison ? 
                (comparison >= 100 ? 'Above expectations' : 
                 comparison >= 90 ? 'Within range' : 'Below expectations') : 
                'Requires discussion',
            recommendation: this.generateSalaryRecommendation(comparison)
        };
    }
    
    /**
     * Generate salary recommendation
     */
    generateSalaryRecommendation(comparison) {
        if (!comparison) return 'Discuss compensation package during interview';
        if (comparison >= 110) return 'Excellent match - focus on growth opportunities';
        if (comparison >= 100) return 'Strong position - negotiate for benefits and bonuses';
        if (comparison >= 90) return 'Consider total compensation package including benefits';
        return 'Prepare strong justification for higher compensation based on your skills';
    }
    
    /**
     * Analyze competition for the role
     */
    analyzeCompetition(job) {
        // Estimate based on job level and market
        const title = job.title?.toLowerCase() || '';
        let competitionLevel = 'Medium';
        let estimatedApplicants = '50-100';
        
        if (title.includes('senior') || title.includes('director') || title.includes('head')) {
            competitionLevel = 'High';
            estimatedApplicants = '100-200';
        } else if (title.includes('junior') || title.includes('entry')) {
            competitionLevel = 'Very High';
            estimatedApplicants = '200+';
        } else if (title.includes('specialist') || title.includes('expert')) {
            competitionLevel = 'Low';
            estimatedApplicants = '20-50';
        }
        
        return {
            level: competitionLevel,
            estimatedApplicants: estimatedApplicants,
            standoutFactors: this.isLoggedIn ? 
                this.identifyStandoutFactors() : 
                ['Strong technical skills', 'Relevant experience', 'Cultural fit'],
            timing: this.assessApplicationTiming(job)
        };
    }
    
    /**
     * Identify user's standout factors
     */
    identifyStandoutFactors() {
        const factors = [];
        
        if (this.userProfile?.years_experience > 5) {
            factors.push('Extensive industry experience');
        }
        if (this.userProfile?.certifications?.length > 0) {
            factors.push('Professional certifications');
        }
        if (this.userProfile?.skills?.length > 10) {
            factors.push('Diverse skill set');
        }
        factors.push('Personalized application approach');
        
        return factors;
    }
    
    /**
     * Assess application timing
     */
    assessApplicationTiming(job) {
        // Simple timing based on when job was posted
        return {
            urgency: 'Medium',
            recommendation: 'Apply within 48 hours for best consideration',
            optimal_day: 'Tuesday-Thursday',
            optimal_time: 'Morning (9-11 AM)'
        };
    }
    
    /**
     * Extract skills from job description
     */
    extractSkillsFromJob(job) {
        const text = `${job.title} ${job.description} ${job.requirements || ''}`.toLowerCase();
        
        const skillPatterns = [
            'excel', 'powerpoint', 'word', 'outlook',
            'python', 'javascript', 'java', 'sql', 'r',
            'project management', 'agile', 'scrum',
            'financial analysis', 'data analysis', 'business analysis',
            'communication', 'leadership', 'teamwork',
            'problem solving', 'critical thinking',
            'fund administration', 'compliance', 'aml',
            'stakeholder management', 'client relations'
        ];
        
        return skillPatterns.filter(skill => text.includes(skill));
    }
    
    /**
     * Generate personalized visualizations
     */
    generatePersonalizedVisualizations(job, analysis) {
        const visualizations = [];
        
        // 1. Skills Radar Chart
        visualizations.push({
            type: 'radar',
            id: 'skills-radar',
            data: this.buildRadarChartData(analysis.skillAnalysis),
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Skills Alignment Analysis'
                    }
                }
            }
        });
        
        // 2. Match Score Gauge
        visualizations.push({
            type: 'gauge',
            id: 'match-gauge',
            data: {
                value: analysis.matchScore,
                min: 0,
                max: 100,
                thresholds: {
                    low: 40,
                    medium: 70,
                    high: 85
                }
            }
        });
        
        // 3. Competition Heatmap
        visualizations.push({
            type: 'heatmap',
            id: 'competition-heatmap',
            data: this.buildHeatmapData(job, analysis),
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Competitive Positioning Matrix'
                    }
                }
            }
        });
        
        // 4. Salary Range Box Plot
        if (analysis.salaryAnalysis) {
            visualizations.push({
                type: 'boxplot',
                id: 'salary-boxplot',
                data: this.buildSalaryBoxPlotData(analysis.salaryAnalysis)
            });
        }
        
        // 5. Timeline Gantt
        visualizations.push({
            type: 'gantt',
            id: 'timeline-gantt',
            data: this.buildTimelineData(analysis.timeline)
        });
        
        return visualizations;
    }
    
    /**
     * Generate basic visualizations for logged-out users
     */
    generateBasicVisualizations(job, analysis) {
        return [
            {
                type: 'bar',
                id: 'basic-match',
                data: {
                    labels: ['Match Score'],
                    datasets: [{
                        label: 'Your Compatibility',
                        data: [analysis.matchScore],
                        backgroundColor: '#2D6A4F'
                    }]
                }
            },
            {
                type: 'text',
                id: 'login-prompt',
                content: 'Sign in to unlock personalized analysis with your profile data'
            }
        ];
    }
    
    /**
     * Generate personalized strategies
     */
    generateStrategies(job) {
        const strategies = [];
        
        if (!this.isLoggedIn || !this.userProfile) {
            return [
                {
                    type: 'generic',
                    title: 'Highlight Relevant Experience',
                    description: 'Focus on experiences that directly relate to the job requirements'
                },
                {
                    type: 'generic',
                    title: 'Research the Company',
                    description: 'Understand their values, culture, and recent developments'
                },
                {
                    type: 'generic',
                    title: 'Customize Your Application',
                    description: 'Tailor your resume and cover letter to match the job description'
                }
            ];
        }
        
        // Personalized strategies based on profile
        const skillGaps = this.analyzeSkills(job).gaps;
        
        if (skillGaps.length > 0) {
            strategies.push({
                type: 'skill_development',
                title: 'Address Skill Gaps',
                description: `Focus on: ${skillGaps.slice(0, 3).join(', ')}`,
                action: 'Consider online courses or certifications',
                priority: 'high'
            });
        }
        
        if (this.userProfile.years_experience) {
            const expYears = parseInt(this.userProfile.years_experience);
            const reqExp = parseInt(job.experience_required) || 0;
            
            if (expYears > reqExp + 2) {
                strategies.push({
                    type: 'experience',
                    title: 'Leverage Your Seniority',
                    description: 'Emphasize leadership and mentoring capabilities',
                    priority: 'medium'
                });
            } else if (expYears < reqExp) {
                strategies.push({
                    type: 'experience',
                    title: 'Compensate for Experience Gap',
                    description: 'Highlight fast learning, relevant projects, and transferable skills',
                    priority: 'high'
                });
            }
        }
        
        // Location-based strategy
        if (this.userProfile.preferred_locations && job.location) {
            const isRemote = job.location.toLowerCase().includes('remote');
            const isPreferred = this.userProfile.preferred_locations.some(loc => 
                job.location.toLowerCase().includes(loc.toLowerCase())
            );
            
            if (isRemote) {
                strategies.push({
                    type: 'location',
                    title: 'Emphasize Remote Work Skills',
                    description: 'Highlight self-management, communication, and collaboration tools expertise',
                    priority: 'medium'
                });
            } else if (!isPreferred) {
                strategies.push({
                    type: 'location',
                    title: 'Address Relocation',
                    description: 'Express flexibility and enthusiasm for the location',
                    priority: 'high'
                });
            }
        }
        
        return strategies;
    }
    
    /**
     * Generate application timeline
     */
    generateTimeline(job) {
        const today = new Date();
        const timeline = [];
        
        // Week 1
        timeline.push({
            week: 1,
            phase: 'Preparation',
            tasks: [
                'Research company and role thoroughly',
                'Update resume with relevant keywords',
                'Write tailored cover letter',
                'Prepare portfolio/work samples'
            ],
            deadline: new Date(today.getTime() + 7 * 24 * 60 * 60 * 1000).toLocaleDateString()
        });
        
        // Week 2
        timeline.push({
            week: 2,
            phase: 'Application & Networking',
            tasks: [
                'Submit application through official channel',
                'Connect with current employees on LinkedIn',
                'Follow up with recruiter if applicable',
                'Research interview questions'
            ],
            deadline: new Date(today.getTime() + 14 * 24 * 60 * 60 * 1000).toLocaleDateString()
        });
        
        // Week 3-4
        timeline.push({
            week: 3,
            phase: 'Interview Preparation',
            tasks: [
                'Practice behavioral questions',
                'Prepare technical assessments',
                'Plan interview outfit and logistics',
                'Prepare questions for interviewers'
            ],
            deadline: new Date(today.getTime() + 28 * 24 * 60 * 60 * 1000).toLocaleDateString()
        });
        
        // Week 5+
        timeline.push({
            week: 5,
            phase: 'Follow-up & Decision',
            tasks: [
                'Send thank you notes post-interview',
                'Follow up if no response',
                'Negotiate offer if successful',
                'Plan transition if accepted'
            ],
            deadline: new Date(today.getTime() + 35 * 24 * 60 * 60 * 1000).toLocaleDateString()
        });
        
        return timeline;
    }
    
    /**
     * Build radar chart data for skills
     */
    buildRadarChartData(skillAnalysis) {
        const labels = [...skillAnalysis.matched, ...skillAnalysis.gaps];
        const userData = [];
        const jobData = [];
        
        // Get user's actual skill proficiency levels from profile
        const profile = JSON.parse(localStorage.getItem('sffc_user_profile') || '{}');
        const userSkillLevels = profile.skill_levels || {};
        
        labels.forEach(skill => {
            if (skillAnalysis.matched.includes(skill)) {
                // Use actual proficiency level if available, otherwise use 80 as default for matched
                const level = userSkillLevels[skill] || 80;
                userData.push(level);
                jobData.push(100);
            } else {
                userData.push(0);
                jobData.push(100);
            }
        });
        
        return {
            labels: labels,
            datasets: [
                {
                    label: 'Your Skills',
                    data: userData,
                    backgroundColor: 'rgba(201, 169, 97, 0.2)',
                    borderColor: '#2D6A4F',
                    pointBackgroundColor: '#2D6A4F'
                },
                {
                    label: 'Job Requirements',
                    data: jobData,
                    backgroundColor: 'rgba(26, 35, 50, 0.1)',
                    borderColor: '#1A2332',
                    pointBackgroundColor: '#1A2332'
                }
            ]
        };
    }
    
    /**
     * Check if two skills are similar
     */
    skillsAreSimilar(skill1, skill2) {
        skill1 = skill1.toLowerCase().trim();
        skill2 = skill2.toLowerCase().trim();
        
        // Exact match
        if (skill1 === skill2) return true;
        
        // Contains match
        if (skill1.includes(skill2) || skill2.includes(skill1)) return true;
        
        // Common variations
        const variations = {
            'excel': ['microsoft excel', 'ms excel', 'spreadsheets'],
            'powerpoint': ['microsoft powerpoint', 'ms powerpoint', 'presentations'],
            'project management': ['pm', 'project manager', 'project coordination'],
            'data analysis': ['data analytics', 'analytical skills', 'data analyst']
        };
        
        for (const [key, values] of Object.entries(variations)) {
            if ((skill1.includes(key) || values.some(v => skill1.includes(v))) &&
                (skill2.includes(key) || values.some(v => skill2.includes(v)))) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Calculate skill match percentage
     */
    calculateSkillMatch(userSkills, jobSkills) {
        if (!jobSkills.length) return 0;
        
        const matches = jobSkills.filter(jobSkill =>
            userSkills.some(userSkill => 
                this.skillsAreSimilar(jobSkill, userSkill)
            )
        ).length;
        
        return matches / jobSkills.length;
    }
    
    /**
     * Calculate experience match
     */
    calculateExperienceMatch(userExp, jobExp) {
        const userYears = parseInt(userExp) || 0;
        const jobYears = parseInt(jobExp) || 0;
        
        if (userYears >= jobYears) return 1;
        if (userYears >= jobYears - 1) return 0.8;
        if (userYears >= jobYears - 2) return 0.6;
        return 0.4;
    }
    
    /**
     * Calculate location match
     */
    calculateLocationMatch(preferredLocations, jobLocation) {
        if (!preferredLocations || !jobLocation) return 0.5;
        
        const jobLoc = jobLocation.toLowerCase();
        
        // Remote work scores high for everyone
        if (jobLoc.includes('remote')) return 0.9;
        
        // Check if job location matches preferences
        return preferredLocations.some(loc => 
            jobLoc.includes(loc.toLowerCase())
        ) ? 1 : 0.3;
    }
    
    /**
     * Calculate salary match
     */
    calculateSalaryMatch(expectations, jobSalary) {
        if (!expectations || !jobSalary) return 0.5;
        
        // Parse salary values
        const expected = this.parseSalary(expectations);
        const offered = this.parseSalary(jobSalary);
        
        if (!expected || !offered) return 0.5;
        
        const ratio = offered / expected;
        
        if (ratio >= 1) return 1;
        if (ratio >= 0.9) return 0.85;
        if (ratio >= 0.8) return 0.7;
        if (ratio >= 0.7) return 0.5;
        return 0.3;
    }
    
    /**
     * Parse salary string to number
     */
    parseSalary(salaryStr) {
        if (!salaryStr) return null;
        if (typeof salaryStr === 'number') return salaryStr;
        
        const cleaned = salaryStr.toString().replace(/[^0-9]/g, '');
        return parseInt(cleaned) || null;
    }
    
    /**
     * Calculate career goals match
     */
    calculateCareerGoalsMatch(priorities, job) {
        if (!priorities || !Array.isArray(priorities)) return 0.5;
        
        let score = 0.5; // Base score
        const jobText = `${job.title} ${job.description}`.toLowerCase();
        
        priorities.forEach(priority => {
            const p = priority.toLowerCase();
            
            if (p.includes('growth') && jobText.includes('growth')) score += 0.1;
            if (p.includes('learning') && jobText.includes('training')) score += 0.1;
            if (p.includes('leadership') && jobText.includes('lead')) score += 0.1;
            if (p.includes('remote') && jobText.includes('remote')) score += 0.1;
            if (p.includes('flexible') && jobText.includes('flexible')) score += 0.1;
        });
        
        return Math.min(score, 1);
    }
    
    /**
     * Generate detailed match breakdown
     */
    generateMatchBreakdown(job) {
        const breakdown = {
            overall: this.calculateMatchScore(job),
            components: {},
            strengths: [],
            improvements: [],
            verdict: ''
        };
        
        // Extract all job data dynamically
        const jobText = `${job.title || ''} ${job.description || ''} ${job.requirements || ''}`.toLowerCase();
        const jobWords = jobText.split(/\s+/);
        
        // Skills component
        if (this.userProfile?.skills) {
            const skillMatch = this.analyzeSkills(job);
            breakdown.components.skills = {
                score: skillMatch.matchPercentage,
                weight: 35,
                details: `${skillMatch.matched.length} of ${skillMatch.required.length} required skills matched`
            };
            
            if (skillMatch.matchPercentage > 70) {
                breakdown.strengths.push(`Strong skill alignment (${skillMatch.matchPercentage}%)`);
            } else {
                breakdown.improvements.push(`Develop ${skillMatch.gaps.slice(0, 3).join(', ')}`);
            }
        }
        
        // Experience component
        const expRequired = this.extractExperienceYears(job);
        if (this.userProfile?.years_experience && expRequired) {
            const expDiff = this.userProfile.years_experience - expRequired;
            const expScore = expDiff >= 0 ? 100 : Math.max(0, 100 + (expDiff * 20));
            
            breakdown.components.experience = {
                score: expScore,
                weight: 25,
                details: `You have ${this.userProfile.years_experience} years, role requires ${expRequired} years`
            };
            
            if (expDiff >= 0) {
                breakdown.strengths.push(`Experience requirement met (${expDiff > 0 ? '+' + expDiff : 'exact match'})`);
            } else {
                breakdown.improvements.push(`${Math.abs(expDiff)} more years of experience needed`);
            }
        }
        
        // Location component
        if (job.location) {
            const locationScore = this.userProfile?.preferred_locations ? 
                this.calculateLocationMatch(this.userProfile.preferred_locations, job.location) * 100 : 50;
            
            breakdown.components.location = {
                score: locationScore,
                weight: 15,
                details: job.location
            };
            
            if (locationScore > 80) {
                breakdown.strengths.push(`Location match: ${job.location}`);
            }
        }
        
        // Salary component
        const salaryData = this.analyzeSalary(job);
        if (salaryData?.range) {
            breakdown.components.salary = {
                score: salaryData.alignment || 75,
                weight: 15,
                details: salaryData.range
            };
        }
        
        // Generate dynamic verdict based on actual score
        const overallScore = breakdown.overall;
        if (overallScore >= 80) {
            breakdown.verdict = 'Excellent Match - High priority application';
        } else if (overallScore >= 60) {
            breakdown.verdict = 'Good Match - Worth pursuing with tailored approach';
        } else if (overallScore >= 40) {
            breakdown.verdict = 'Moderate Match - Consider if aligns with career goals';
        } else {
            breakdown.verdict = 'Stretch Opportunity - Focus on transferable skills';
        }
        
        return breakdown;
    }
    
    /**
     * Breakdown job description into key components
     */
    breakdownJobDescription(job) {
        const description = job.description || '';
        const requirements = job.requirements || '';
        const fullText = `${description} ${requirements}`;
        
        const breakdown = {
            role_purpose: this.extractRolePurpose(fullText),
            key_responsibilities: this.extractResponsibilities(fullText),
            must_haves: this.extractMustHaves(fullText),
            nice_to_haves: this.extractNiceToHaves(fullText),
            team_structure: this.extractTeamInfo(fullText),
            growth_opportunities: this.extractGrowthOpportunities(fullText),
            work_environment: this.extractWorkEnvironment(fullText),
            success_metrics: this.extractSuccessMetrics(fullText)
        };
        
        return breakdown;
    }
    
    /**
     * Extract role purpose from job text
     */
    extractRolePurpose(text) {
        const purposeIndicators = ['responsible for', 'will be', 'role is', 'position is', 'looking for', 'seeking'];
        const sentences = text.split(/[.!?]+/);
        
        for (const sentence of sentences) {
            for (const indicator of purposeIndicators) {
                if (sentence.toLowerCase().includes(indicator)) {
                    return sentence.trim();
                }
            }
        }
        
        // Return first meaningful sentence if no indicators found
        return sentences.find(s => s.length > 50) || 'Drive key initiatives and deliver results';
    }
    
    /**
     * Extract key requirements dynamically
     */
    extractKeyRequirements(job) {
        const text = `${job.description || ''} ${job.requirements || ''}`;
        const requirements = {
            technical: [],
            soft_skills: [],
            experience: [],
            education: [],
            certifications: []
        };
        
        // Extract technical skills
        const techPatterns = /\b(python|java|javascript|sql|excel|powerbi|tableau|aws|azure|react|node|docker|kubernetes|git|agile|scrum)\b/gi;
        const techMatches = text.match(techPatterns) || [];
        requirements.technical = [...new Set(techMatches.map(m => m.charAt(0).toUpperCase() + m.slice(1).toLowerCase()))];
        
        // Extract soft skills
        const softPatterns = /\b(communication|leadership|teamwork|analytical|problem-solving|creative|organized|detail-oriented|collaborative)\b/gi;
        const softMatches = text.match(softPatterns) || [];
        requirements.soft_skills = [...new Set(softMatches.map(m => m.charAt(0).toUpperCase() + m.slice(1).toLowerCase()))];
        
        // Extract experience requirements
        const expPatterns = /(\d+)\+?\s*years?/gi;
        const expMatches = text.match(expPatterns) || [];
        requirements.experience = [...new Set(expMatches)];
        
        // Extract education
        const eduPatterns = /\b(bachelor|master|phd|doctorate|degree|diploma|mba|cfa|cpa)\b/gi;
        const eduMatches = text.match(eduPatterns) || [];
        requirements.education = [...new Set(eduMatches.map(m => m.toUpperCase()))];
        
        return requirements;
    }
    
    /**
     * Generate standout strategies based on job analysis
     */
    generateStandoutStrategies(job) {
        const strategies = [];
        const jobText = `${job.title || ''} ${job.description || ''}`.toLowerCase();
        
        // Dynamic strategy generation based on job content
        if (jobText.includes('startup') || jobText.includes('fast-paced')) {
            strategies.push({
                title: 'Demonstrate Agility',
                description: 'Highlight experiences where you adapted quickly to change and wore multiple hats',
                priority: 'high'
            });
        }
        
        if (jobText.includes('data') || jobText.includes('analytic')) {
            strategies.push({
                title: 'Quantify Your Impact',
                description: 'Use specific metrics and numbers to demonstrate your analytical achievements',
                priority: 'high'
            });
        }
        
        if (jobText.includes('lead') || jobText.includes('manage')) {
            strategies.push({
                title: 'Showcase Leadership',
                description: 'Emphasize team management, mentoring, and cross-functional collaboration experiences',
                priority: 'high'
            });
        }
        
        if (jobText.includes('client') || jobText.includes('customer')) {
            strategies.push({
                title: 'Client Success Stories',
                description: 'Share specific examples of improving client satisfaction or retention',
                priority: 'high'
            });
        }
        
        // Add strategies based on user profile gaps
        if (this.userProfile) {
            const skillGaps = this.analyzeSkills(job).gaps;
            if (skillGaps.length > 0) {
                strategies.push({
                    title: 'Address Skill Gaps',
                    description: `Mention ongoing learning in ${skillGaps.slice(0, 2).join(' and ')} or transferable skills`,
                    priority: 'medium'
                });
            }
        }
        
        // Always add these universal strategies
        strategies.push({
            title: 'Research the Company',
            description: `Learn about ${job.company || 'the company'}'s recent news, values, and culture`,
            priority: 'high'
        });
        
        strategies.push({
            title: 'Prepare Questions',
            description: 'Develop thoughtful questions about the role, team, and growth opportunities',
            priority: 'medium'
        });
        
        return strategies;
    }
    
    /**
     * Analyze qualifications match
     */
    analyzeQualifications(job) {
        const required = this.extractKeyRequirements(job);
        const analysis = {
            met: [],
            partial: [],
            missing: [],
            score: 0
        };
        
        if (!this.userProfile) {
            analysis.missing = [...required.technical, ...required.soft_skills];
            return analysis;
        }
        
        // Check technical skills
        required.technical.forEach(skill => {
            if (this.userProfile.skills?.some(s => s.toLowerCase().includes(skill.toLowerCase()))) {
                analysis.met.push(skill);
            } else {
                analysis.missing.push(skill);
            }
        });
        
        // Check experience
        const reqExp = this.extractExperienceYears(job);
        if (reqExp) {
            if (this.userProfile.years_experience >= reqExp) {
                analysis.met.push(`${reqExp}+ years experience`);
            } else if (this.userProfile.years_experience >= reqExp - 1) {
                analysis.partial.push(`Experience (have ${this.userProfile.years_experience}, need ${reqExp})`);
            } else {
                analysis.missing.push(`${reqExp} years experience required`);
            }
        }
        
        // Calculate score
        const total = analysis.met.length + analysis.partial.length + analysis.missing.length;
        if (total > 0) {
            analysis.score = Math.round(((analysis.met.length + (analysis.partial.length * 0.5)) / total) * 100);
        }
        
        return analysis;
    }
    
    /**
     * Generate interview preparation guide
     */
    generateInterviewPrep(job) {
        const jobText = `${job.title || ''} ${job.description || ''}`.toLowerCase();
        const prep = {
            likely_questions: [],
            star_situations: [],
            technical_topics: [],
            research_areas: []
        };
        
        // Generate questions based on job content
        prep.likely_questions.push(`Why are you interested in ${job.title || 'this role'}?`);
        prep.likely_questions.push(`What makes you a good fit for ${job.company || 'our company'}?`);
        
        if (jobText.includes('team')) {
            prep.likely_questions.push('Describe your experience working in teams');
            prep.star_situations.push('Prepare a story about successful team collaboration');
        }
        
        if (jobText.includes('problem')) {
            prep.likely_questions.push('Tell me about a complex problem you solved');
            prep.star_situations.push('Example of innovative problem-solving');
        }
        
        if (jobText.includes('deadline') || jobText.includes('pressure')) {
            prep.likely_questions.push('How do you handle tight deadlines?');
            prep.star_situations.push('Managing competing priorities under pressure');
        }
        
        // Extract technical topics
        const techSkills = this.extractKeyRequirements(job).technical;
        prep.technical_topics = techSkills.map(skill => `${skill} best practices and recent experience`);
        
        // Research areas
        prep.research_areas = [
            `${job.company || 'Company'} recent news and developments`,
            'Industry trends and challenges',
            'Competitors and market position',
            'Company culture and values'
        ];
        
        return prep;
    }
    
    /**
     * Get industry insights
     */
    getIndustryInsights(job) {
        const jobText = `${job.title || ''} ${job.description || ''}`.toLowerCase();
        const insights = {
            trends: [],
            skills_in_demand: [],
            growth_areas: [],
            challenges: []
        };
        
        // Detect industry from job content
        if (jobText.includes('finance') || jobText.includes('banking')) {
            insights.trends.push('Digital transformation in financial services');
            insights.trends.push('Rise of fintech and open banking');
            insights.skills_in_demand.push('Risk management', 'Regulatory compliance', 'Data analytics');
            insights.challenges.push('Regulatory changes', 'Cybersecurity threats');
        }
        
        if (jobText.includes('tech') || jobText.includes('software')) {
            insights.trends.push('AI and machine learning adoption');
            insights.trends.push('Cloud-first strategies');
            insights.skills_in_demand.push('Cloud platforms', 'DevOps', 'AI/ML');
            insights.challenges.push('Talent shortage', 'Rapid technology changes');
        }
        
        if (jobText.includes('marketing') || jobText.includes('digital')) {
            insights.trends.push('Data-driven marketing');
            insights.trends.push('Personalization at scale');
            insights.skills_in_demand.push('Marketing analytics', 'Content strategy', 'Marketing automation');
            insights.challenges.push('Privacy regulations', 'Multi-channel attribution');
        }
        
        // Default insights if no specific industry detected
        if (insights.trends.length === 0) {
            insights.trends.push('Digital transformation across industries');
            insights.trends.push('Remote and hybrid work models');
            insights.skills_in_demand.push('Digital literacy', 'Adaptability', 'Data analysis');
            insights.challenges.push('Economic uncertainty', 'Talent retention');
        }
        
        return insights;
    }
    
    /**
     * Calculate application priority
     */
    calculateApplicationPriority(job) {
        const factors = {
            match_score: this.calculateMatchScore(job),
            deadline_urgency: 50,
            competition_level: 50,
            growth_potential: 50,
            salary_fit: 50
        };
        
        // Check for application deadline
        if (job.deadline || job.closing_date) {
            const deadline = new Date(job.deadline || job.closing_date);
            const today = new Date();
            const daysLeft = Math.ceil((deadline - today) / (1000 * 60 * 60 * 24));
            
            if (daysLeft <= 3) factors.deadline_urgency = 100;
            else if (daysLeft <= 7) factors.deadline_urgency = 80;
            else if (daysLeft <= 14) factors.deadline_urgency = 60;
        }
        
        // Assess competition
        const jobText = job.description?.toLowerCase() || '';
        if (jobText.includes('senior') || jobText.includes('director')) {
            factors.competition_level = 70;
        }
        if (jobText.includes('entry') || jobText.includes('junior')) {
            factors.competition_level = 30;
        }
        
        // Calculate overall priority
        const priority = (
            factors.match_score * 0.4 +
            factors.deadline_urgency * 0.3 +
            factors.growth_potential * 0.2 +
            factors.salary_fit * 0.1
        );
        
        return {
            score: Math.round(priority),
            factors: factors,
            recommendation: priority >= 70 ? 'Apply immediately' : 
                          priority >= 50 ? 'Apply within 3 days' : 
                          'Apply when ready'
        };
    }
    
    /**
     * Find hidden opportunities in the job
     */
    findHiddenOpportunities(job) {
        const opportunities = [];
        const jobText = `${job.title || ''} ${job.description || ''}`.toLowerCase();
        
        if (jobText.includes('grow') || jobText.includes('build')) {
            opportunities.push('Role involves building something new - high visibility potential');
        }
        
        if (jobText.includes('cross-functional') || jobText.includes('stakeholder')) {
            opportunities.push('Exposure to multiple departments - networking opportunities');
        }
        
        if (jobText.includes('strategy') || jobText.includes('roadmap')) {
            opportunities.push('Strategic involvement - influence on company direction');
        }
        
        if (jobText.includes('mentor') || jobText.includes('lead')) {
            opportunities.push('Leadership development - management track potential');
        }
        
        if (jobText.includes('global') || jobText.includes('international')) {
            opportunities.push('International exposure - global career opportunities');
        }
        
        if (jobText.includes('startup') || jobText.includes('early stage')) {
            opportunities.push('Early employee advantages - equity and rapid growth potential');
        }
        
        return opportunities;
    }
    
    /**
     * Identify potential red flags
     */
    identifyRedFlags(job) {
        const flags = [];
        const jobText = `${job.description || ''}`.toLowerCase();
        
        if (jobText.includes('wear many hats') && jobText.includes('fast-paced')) {
            flags.push({
                type: 'workload',
                concern: 'May involve excessive responsibilities',
                mitigation: 'Clarify role boundaries and support structure'
            });
        }
        
        if (jobText.includes('other duties as assigned')) {
            flags.push({
                type: 'scope_creep',
                concern: 'Undefined role boundaries',
                mitigation: 'Ask for specific examples of typical tasks'
            });
        }
        
        if (!job.salary && !jobText.includes('competitive')) {
            flags.push({
                type: 'compensation',
                concern: 'No salary information provided',
                mitigation: 'Research market rates and ask early in process'
            });
        }
        
        const buzzwords = (jobText.match(/dynamic|ninja|rockstar|guru|wizard/g) || []).length;
        if (buzzwords > 2) {
            flags.push({
                type: 'culture',
                concern: 'Heavy use of buzzwords may indicate unclear expectations',
                mitigation: 'Ask specific questions about day-to-day responsibilities'
            });
        }
        
        return flags;
    }
    
    /**
     * Assess growth potential
     */
    assessGrowthPotential(job) {
        const jobText = `${job.title || ''} ${job.description || ''}`.toLowerCase();
        let score = 50; // Base score
        const factors = [];
        
        if (jobText.includes('career development') || jobText.includes('training')) {
            score += 15;
            factors.push('Mentions career development programs');
        }
        
        if (jobText.includes('promotion') || jobText.includes('advancement')) {
            score += 15;
            factors.push('Clear advancement opportunities');
        }
        
        if (jobText.includes('mentor')) {
            score += 10;
            factors.push('Mentorship available');
        }
        
        if (jobText.includes('learning') || jobText.includes('development')) {
            score += 10;
            factors.push('Focus on continuous learning');
        }
        
        if (job.company_size === 'startup' || jobText.includes('startup')) {
            score += 10;
            factors.push('Startup environment - rapid growth potential');
        }
        
        return {
            score: Math.min(score, 100),
            factors: factors,
            recommendation: score >= 70 ? 'Excellent growth prospects' :
                          score >= 50 ? 'Good growth potential' :
                          'Limited growth visibility'
        };
    }
    
    /**
     * Analyze culture fit
     */
    analyzeCultureFit(job) {
        const jobText = `${job.description || ''}`.toLowerCase();
        const culture = {
            work_style: '',
            team_dynamics: '',
            values: [],
            environment: ''
        };
        
        // Work style
        if (jobText.includes('autonomous') || jobText.includes('independent')) {
            culture.work_style = 'Independent and self-directed';
        } else if (jobText.includes('collaborative') || jobText.includes('team')) {
            culture.work_style = 'Collaborative and team-oriented';
        } else {
            culture.work_style = 'Balanced independence and collaboration';
        }
        
        // Team dynamics
        if (jobText.includes('fast-paced') || jobText.includes('dynamic')) {
            culture.team_dynamics = 'Fast-paced and dynamic';
        } else if (jobText.includes('structured') || jobText.includes('process')) {
            culture.team_dynamics = 'Structured and process-driven';
        } else {
            culture.team_dynamics = 'Balanced and flexible';
        }
        
        // Values extraction
        const valueKeywords = {
            'innovation': 'Innovation and creativity',
            'customer': 'Customer focus',
            'quality': 'Quality and excellence',
            'integrity': 'Integrity and ethics',
            'diversity': 'Diversity and inclusion',
            'sustainability': 'Environmental responsibility'
        };
        
        Object.keys(valueKeywords).forEach(keyword => {
            if (jobText.includes(keyword)) {
                culture.values.push(valueKeywords[keyword]);
            }
        });
        
        // Environment
        if (jobText.includes('remote') || jobText.includes('flexible')) {
            culture.environment = 'Flexible/Remote work options';
        } else if (jobText.includes('office') || jobText.includes('onsite')) {
            culture.environment = 'Office-based';
        } else {
            culture.environment = 'Hybrid work model';
        }
        
        return culture;
    }
    
    /**
     * Identify negotiation points
     */
    identifyNegotiationPoints(job) {
        const points = [];
        const jobText = `${job.description || ''}`.toLowerCase();
        
        // Salary negotiation
        if (!job.salary || job.salary.includes('negotiable')) {
            points.push({
                area: 'Base Salary',
                leverage: 'Market rates and your experience level',
                strategy: 'Research industry benchmarks and prepare a range'
            });
        }
        
        // Remote work
        if (!jobText.includes('remote') && !jobText.includes('office required')) {
            points.push({
                area: 'Remote Work',
                leverage: 'Productivity and work-life balance',
                strategy: 'Propose a hybrid schedule after proving yourself'
            });
        }
        
        // Professional development
        if (jobText.includes('learning') || jobText.includes('development')) {
            points.push({
                area: 'Professional Development',
                leverage: 'Mutual benefit of skill enhancement',
                strategy: 'Request specific training budget or certifications'
            });
        }
        
        // Flexible hours
        if (!jobText.includes('strict hours') && !jobText.includes('shift')) {
            points.push({
                area: 'Flexible Hours',
                leverage: 'Output-based performance',
                strategy: 'Propose core hours with flexibility'
            });
        }
        
        // Signing bonus
        if (jobText.includes('immediate') || jobText.includes('urgent')) {
            points.push({
                area: 'Signing Bonus',
                leverage: 'Quick availability and immediate impact',
                strategy: 'Request to offset relocation or lost benefits'
            });
        }
        
        return points;
    }
    
    /**
     * Extract responsibilities dynamically
     */
    extractResponsibilities(text) {
        const responsibilities = [];
        const lines = text.split(/[\n.]/);
        
        const indicators = ['will', 'responsible for', 'duties include', 'you will', 'tasks', 'responsibilities'];
        
        lines.forEach(line => {
            const lower = line.toLowerCase();
            if (indicators.some(ind => lower.includes(ind)) || line.match(/^[\s]*[-•*]/)) {
                const cleaned = line.replace(/^[\s-•*]+/, '').trim();
                if (cleaned.length > 20 && cleaned.length < 200) {
                    responsibilities.push(cleaned);
                }
            }
        });
        
        return responsibilities.slice(0, 7); // Return top 7 responsibilities
    }
    
    /**
     * Extract must-haves
     */
    extractMustHaves(text) {
        const mustHaves = [];
        const lines = text.split(/[\n.]/);
        
        const indicators = ['must have', 'required', 'mandatory', 'essential', 'minimum'];
        
        lines.forEach(line => {
            const lower = line.toLowerCase();
            if (indicators.some(ind => lower.includes(ind))) {
                const cleaned = line.replace(/^[\s-•*]+/, '').trim();
                if (cleaned.length > 10) {
                    mustHaves.push(cleaned);
                }
            }
        });
        
        return mustHaves;
    }
    
    /**
     * Extract nice-to-haves
     */
    extractNiceToHaves(text) {
        const niceToHaves = [];
        const lines = text.split(/[\n.]/);
        
        const indicators = ['nice to have', 'preferred', 'bonus', 'advantageous', 'desirable', 'plus'];
        
        lines.forEach(line => {
            const lower = line.toLowerCase();
            if (indicators.some(ind => lower.includes(ind))) {
                const cleaned = line.replace(/^[\s-•*]+/, '').trim();
                if (cleaned.length > 10) {
                    niceToHaves.push(cleaned);
                }
            }
        });
        
        return niceToHaves;
    }
    
    /**
     * Extract team information
     */
    extractTeamInfo(text) {
        const lower = text.toLowerCase();
        const info = {
            size: 'Not specified',
            structure: 'Not specified',
            reporting: 'Not specified'
        };
        
        // Team size
        const sizeMatch = lower.match(/team of (\d+)|(\d+)[-\s]person team/);
        if (sizeMatch) {
            info.size = `Team of ${sizeMatch[1] || sizeMatch[2]}`;
        }
        
        // Reporting structure
        if (lower.includes('report to')) {
            const reportMatch = text.match(/report to ([^.,]+)/i);
            if (reportMatch) {
                info.reporting = reportMatch[1].trim();
            }
        }
        
        // Team structure
        if (lower.includes('cross-functional')) {
            info.structure = 'Cross-functional team';
        } else if (lower.includes('agile') || lower.includes('scrum')) {
            info.structure = 'Agile/Scrum team';
        } else if (lower.includes('matrix')) {
            info.structure = 'Matrix organization';
        }
        
        return info;
    }
    
    /**
     * Extract growth opportunities
     */
    extractGrowthOpportunities(text) {
        const opportunities = [];
        const lower = text.toLowerCase();
        
        if (lower.includes('career path') || lower.includes('progression')) {
            opportunities.push('Clear career progression path');
        }
        
        if (lower.includes('training') || lower.includes('development program')) {
            opportunities.push('Professional development programs');
        }
        
        if (lower.includes('mentor')) {
            opportunities.push('Mentorship opportunities');
        }
        
        if (lower.includes('conference') || lower.includes('certification')) {
            opportunities.push('Support for conferences and certifications');
        }
        
        if (lower.includes('promote from within') || lower.includes('internal mobility')) {
            opportunities.push('Internal mobility and promotions');
        }
        
        return opportunities;
    }
    
    /**
     * Extract work environment details
     */
    extractWorkEnvironment(text) {
        const lower = text.toLowerCase();
        const environment = {
            location: '',
            schedule: '',
            travel: '',
            benefits: []
        };
        
        // Location
        if (lower.includes('remote')) {
            environment.location = 'Remote';
        } else if (lower.includes('hybrid')) {
            environment.location = 'Hybrid';
        } else if (lower.includes('office') || lower.includes('onsite')) {
            environment.location = 'Office-based';
        }
        
        // Schedule
        if (lower.includes('flexible') || lower.includes('flex')) {
            environment.schedule = 'Flexible hours';
        } else if (lower.includes('9-5') || lower.includes('standard hours')) {
            environment.schedule = 'Standard business hours';
        }
        
        // Travel
        const travelMatch = lower.match(/(\d+)%?\s*travel/);
        if (travelMatch) {
            environment.travel = `${travelMatch[1]}% travel required`;
        } else if (lower.includes('travel')) {
            environment.travel = 'Some travel required';
        }
        
        // Benefits
        if (lower.includes('health') || lower.includes('medical')) {
            environment.benefits.push('Health insurance');
        }
        if (lower.includes('401k') || lower.includes('pension')) {
            environment.benefits.push('Retirement plan');
        }
        if (lower.includes('pto') || lower.includes('vacation')) {
            environment.benefits.push('Paid time off');
        }
        
        return environment;
    }
    
    /**
     * Extract success metrics
     */
    extractSuccessMetrics(text) {
        const metrics = [];
        const lower = text.toLowerCase();
        
        if (lower.includes('kpi') || lower.includes('metric')) {
            const lines = text.split(/[\n.]/);
            lines.forEach(line => {
                if (line.toLowerCase().includes('measure') || line.toLowerCase().includes('success')) {
                    metrics.push(line.trim());
                }
            });
        }
        
        // Common success indicators
        if (lower.includes('revenue') || lower.includes('sales')) {
            metrics.push('Revenue/sales targets');
        }
        if (lower.includes('customer satisfaction')) {
            metrics.push('Customer satisfaction scores');
        }
        if (lower.includes('project delivery')) {
            metrics.push('On-time project delivery');
        }
        if (lower.includes('quality')) {
            metrics.push('Quality standards achievement');
        }
        
        return metrics;
    }
    
    /**
     * Extract experience years from job
     */
    extractExperienceYears(job) {
        const text = `${job.description || ''} ${job.requirements || ''} ${job.experience_required || ''}`;
        const match = text.match(/(\d+)\+?\s*years?/i);
        return match ? parseInt(match[1]) : null;
    }
    
    /**
     * Refresh analysis when data changes
     */
    refreshAnalysis() {
        if (this.currentJob) {
            const analysis = this.analyzeJob(this.currentJob);
            this.renderAnalysis(analysis);
        }
    }
    
    /**
     * Render analysis to UI
     */
    renderAnalysis(analysis) {
        // This will be called by the main Ultimate interface
        const event = new CustomEvent('ultimateAnalysisReady', {
            detail: analysis
        });
        document.dispatchEvent(event);
    }
    
    /**
     * Build heatmap data for competition analysis
     */
    buildHeatmapData(job, analysis) {
        return {
            labels: ['Skill Match', 'Experience', 'Compensation', 'Growth', 'Culture'],
            datasets: [{
                label: 'Your Position',
                data: [
                    analysis.matchScore || 75,
                    analysis.experienceMatch || 80,
                    85, // Placeholder for compensation match
                    90, // Growth potential
                    88  // Culture fit
                ],
                backgroundColor: 'rgba(45, 106, 79, 0.3)',
                borderColor: '#2D6A4F'
            }]
        };
    }
    
    /**
     * Build salary box plot data
     */
    buildSalaryBoxPlotData(salaryAnalysis) {
        return {
            labels: ['Base', 'Bonus', 'Total Comp'],
            datasets: [{
                label: 'Salary Range',
                data: [
                    salaryAnalysis.base || {min: 100000, q1: 120000, median: 140000, q3: 160000, max: 180000},
                    salaryAnalysis.bonus || {min: 20000, q1: 30000, median: 40000, q3: 50000, max: 60000},
                    salaryAnalysis.total || {min: 120000, q1: 150000, median: 180000, q3: 210000, max: 240000}
                ],
                backgroundColor: 'rgba(45, 106, 79, 0.3)',
                borderColor: '#2D6A4F'
            }]
        };
    }
    
    /**
     * Build timeline data
     */
    buildTimelineData(job) {
        const now = new Date();
        const posted = new Date(job.posted_date || now);
        const deadline = new Date(job.deadline || new Date(now.getTime() + 30*24*60*60*1000));
        
        return {
            datasets: [{
                label: 'Application Timeline',
                data: [{
                    x: [posted, deadline],
                    y: 0
                }],
                backgroundColor: '#2D6A4F'
            }]
        };
    }
}

// Initialize when document is ready
jQuery(document).ready(() => {
    window.ultimateAnalyzer = new UltimateDynamicAnalyzer();
});