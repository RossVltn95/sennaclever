/**
 * Session State Manager for MENA Careers Chat
 * Manages user journey, preferences, and interaction history
 * 
 * @since 10.6.0
 */

(function($, window) {
    'use strict';
    
    class SessionStateManager {
        constructor() {
            this.sessionId = this.generateSessionId();
            this.initializeState();
            this.setupAutoSave();
        }
        
        generateSessionId() {
            return 'session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        }
        
        initializeState() {
            // Check for existing session in localStorage
            const savedSession = this.loadSession();
            
            if (savedSession && this.isSessionValid(savedSession)) {
                this.state = savedSession;
                this.state.resumed = true;
            } else {
                // Create new session
                this.state = {
                    sessionId: this.sessionId,
                    stage: 'initial', // initial, cv_uploaded, showing_matches, refining, exploring
                    timestamp: new Date().toISOString(),
                    resumed: false,
                    
                    user: {
                        isLoggedIn: window.sffc_ajax?.user_logged_in || false,
                        userId: window.sffc_ajax?.user_id || null
                    },
                    
                    cvData: {
                        uploaded: false,
                        parsed: null,
                        uploadTime: null,
                        matchesGenerated: false,
                        rawContent: null
                    },
                    
                    preferences: {
                        confirmed: {
                            location: [],
                            seniority: null,
                            firmType: [],
                            dealSize: [],
                            sectors: [],
                            compensation: null
                        },
                        inferred: {
                            preferredLocations: [],
                            flexibleOnSeniority: null,
                            firmTypePattern: null,
                            sectorInterest: []
                        },
                        flexibility: {
                            locationFlexible: null,
                            seniorityRange: null,
                            remoteAcceptable: null,
                            relocationWilling: null
                        }
                    },
                    
                    interactions: {
                        jobsViewed: [],
                        jobsSaved: [],
                        jobsApplied: [],
                        jobsSkipped: [],
                        jobsTailored: [],
                        searchQueries: [],
                        filterActions: [],
                        questionsAnswered: []
                    },
                    
                    matchResults: {
                        perfectMatches: [],
                        strongMatches: [],
                        possibleMatches: [],
                        stretchOpportunities: [],
                        totalFound: 0,
                        lastUpdated: null,
                        displayedCount: 0
                    },
                    
                    conversationContext: {
                        lastQuestion: null,
                        awaitingResponse: false,
                        followUpQueue: [],
                        messagesExchanged: 0,
                        lastInteraction: new Date().toISOString()
                    },
                    
                    analytics: {
                        sessionDuration: 0,
                        totalInteractions: 0,
                        matchQuality: null,
                        engagementScore: 0
                    }
                };
            }
        }
        
        /**
         * Check if saved session is still valid (less than 24 hours old)
         */
        isSessionValid(session) {
            if (!session || !session.timestamp) return false;
            
            const sessionAge = Date.now() - new Date(session.timestamp).getTime();
            const maxAge = 24 * 60 * 60 * 1000; // 24 hours
            
            return sessionAge < maxAge;
        }
        
        /**
         * Update session stage
         */
        updateStage(newStage) {
            const validStages = ['initial', 'cv_uploaded', 'cv_review', 'showing_matches', 'refining', 'exploring', 'completed'];
            
            if (!validStages.includes(newStage)) {
                return;
            }
            
            this.state.stage = newStage;
            this.state.conversationContext.lastInteraction = new Date().toISOString();
            this.saveSession();
            
            // Trigger stage change event
            $(document).trigger('sessionStageChanged', [newStage, this.state]);
        }
        
        /**
         * Store CV data
         */
        setCVData(cvData, rawContent = null) {
            this.state.cvData = {
                uploaded: true,
                parsed: cvData,
                uploadTime: new Date().toISOString(),
                matchesGenerated: false,
                rawContent: rawContent
            };
            
            // Auto-extract preferences from CV
            if (cvData) {
                if (cvData.location) {
                    this.addPreference('confirmed', 'location', cvData.location);
                }
                if (cvData.seniority) {
                    this.addPreference('confirmed', 'seniority', cvData.seniority);
                }
            }
            
            this.updateStage('cv_uploaded');
            this.saveSession();
        }
        
        /**
         * Add user preference
         */
        addPreference(type, category, value) {
            if (!this.state.preferences[type]) {
                return;
            }
            
            if (Array.isArray(this.state.preferences[type][category])) {
                if (!this.state.preferences[type][category].includes(value)) {
                    this.state.preferences[type][category].push(value);
                }
            } else {
                this.state.preferences[type][category] = value;
            }
            
            this.saveSession();
        }
        
        /**
         * Track job interaction
         */
        trackInteraction(type, jobData) {
            const interaction = {
                jobId: jobData.id || jobData.job_id,
                jobTitle: jobData.title,
                company: jobData.company,
                timestamp: new Date().toISOString(),
                duration: 0
            };
            
            switch(type) {
                case 'view':
                    this.state.interactions.jobsViewed.push(interaction);
                    this.inferPreferencesFromView(jobData);
                    break;
                case 'save':
                    this.state.interactions.jobsSaved.push(interaction);
                    this.inferPreferencesFromSave(jobData);
                    break;
                case 'skip':
                    this.state.interactions.jobsSkipped.push(interaction);
                    break;
                case 'apply':
                    this.state.interactions.jobsApplied.push(interaction);
                    break;
                case 'tailor':
                    this.state.interactions.jobsTailored.push(interaction);
                    break;
            }
            
            this.state.analytics.totalInteractions++;
            this.saveSession();
            
            // Trigger pattern detection
            this.detectPatterns();
        }
        
        /**
         * Infer preferences from viewed jobs
         */
        inferPreferencesFromView(jobData) {
            const viewedJobs = this.state.interactions.jobsViewed;
            
            // Location pattern
            if (viewedJobs.length >= 3) {
                const locations = viewedJobs.slice(-5).map(j => j.company?.location).filter(Boolean);
                const locationCounts = this.countOccurrences(locations);
                const topLocation = Object.entries(locationCounts).sort((a, b) => b[1] - a[1])[0];
                
                if (topLocation && topLocation[1] >= 2) {
                    this.addPreference('inferred', 'preferredLocations', topLocation[0]);
                }
            }
            
            // Firm type pattern
            const firmTypes = viewedJobs.map(j => this.detectFirmType(j.company)).filter(Boolean);
            if (firmTypes.length >= 3) {
                const typeCounts = this.countOccurrences(firmTypes);
                const topType = Object.entries(typeCounts).sort((a, b) => b[1] - a[1])[0];
                
                if (topType && topType[1] >= 2) {
                    this.state.preferences.inferred.firmTypePattern = topType[0];
                }
            }
        }
        
        /**
         * Infer strong preferences from saved jobs
         */
        inferPreferencesFromSave(jobData) {
            const savedJobs = this.state.interactions.jobsSaved;
            
            // Strong signal - if user saves similar jobs, they really want this
            if (savedJobs.length >= 2) {
                const sectors = savedJobs.map(j => this.detectSector(j.jobTitle)).filter(Boolean);
                const sectorCounts = this.countOccurrences(sectors);
                
                Object.entries(sectorCounts).forEach(([sector, count]) => {
                    if (count >= 2) {
                        this.addPreference('inferred', 'sectorInterest', sector);
                    }
                });
            }
        }
        
        /**
         * Detect patterns in user behavior
         */
        detectPatterns() {
            const patterns = {
                viewingPattern: null,
                skipPattern: null,
                engagementLevel: null
            };
            
            // Viewing pattern
            const recentViews = this.state.interactions.jobsViewed.slice(-10);
            if (recentViews.length >= 5) {
                // Check for consistent patterns
                const viewDurations = recentViews.map(v => v.duration || 0);
                const avgDuration = viewDurations.reduce((a, b) => a + b, 0) / viewDurations.length;
                
                if (avgDuration > 30) {
                    patterns.engagementLevel = 'high';
                } else if (avgDuration > 10) {
                    patterns.engagementLevel = 'medium';
                } else {
                    patterns.engagementLevel = 'low';
                }
            }
            
            // Trigger event with detected patterns
            $(document).trigger('patternsDetected', [patterns, this.state]);
            
            return patterns;
        }
        
        /**
         * Store match results
         */
        setMatchResults(matches) {
            // Categorize matches
            this.state.matchResults = {
                perfectMatches: matches.filter(m => m.matchScore >= 90),
                strongMatches: matches.filter(m => m.matchScore >= 70 && m.matchScore < 90),
                possibleMatches: matches.filter(m => m.matchScore >= 50 && m.matchScore < 70),
                stretchOpportunities: matches.filter(m => m.matchScore >= 30 && m.matchScore < 50),
                totalFound: matches.length,
                lastUpdated: new Date().toISOString(),
                displayedCount: matches.length
            };
            
            this.state.cvData.matchesGenerated = true;
            this.updateStage('showing_matches');
            this.saveSession();
        }
        
        /**
         * Add follow-up question to queue
         */
        queueFollowUp(question, priority = 5, context = {}) {
            const followUp = {
                question,
                priority,
                context,
                queued: new Date().toISOString(),
                asked: false
            };
            
            this.state.conversationContext.followUpQueue.push(followUp);
            
            // Sort by priority
            this.state.conversationContext.followUpQueue.sort((a, b) => b.priority - a.priority);
            
            this.saveSession();
        }
        
        /**
         * Get next follow-up question
         */
        getNextFollowUp() {
            const queue = this.state.conversationContext.followUpQueue;
            const next = queue.find(q => !q.asked);
            
            if (next) {
                next.asked = true;
                this.state.conversationContext.lastQuestion = next.question;
                this.state.conversationContext.awaitingResponse = true;
                this.saveSession();
            }
            
            return next;
        }
        
        /**
         * Record question answer
         */
        recordAnswer(question, answer) {
            this.state.interactions.questionsAnswered.push({
                question,
                answer,
                timestamp: new Date().toISOString()
            });
            
            this.state.conversationContext.awaitingResponse = false;
            this.state.conversationContext.messagesExchanged++;
            this.saveSession();
        }
        
        /**
         * Helper: Count occurrences in array
         */
        countOccurrences(arr) {
            return arr.reduce((acc, val) => {
                acc[val] = (acc[val] || 0) + 1;
                return acc;
            }, {});
        }
        
        /**
         * Helper: Detect firm type from company name
         */
        detectFirmType(company) {
            if (!company) return null;
            
            const companyLower = company.toLowerCase();
            
            if (companyLower.includes('partners') || companyLower.includes('capital')) {
                return 'PE';
            } else if (companyLower.includes('advisors') || companyLower.includes('advisory')) {
                return 'Advisory';
            } else if (companyLower.includes('bank')) {
                return 'Bank';
            }
            
            return 'Corporate';
        }
        
        /**
         * Helper: Detect sector from job title
         */
        detectSector(title) {
            if (!title) return null;
            
            const titleLower = title.toLowerCase();
            
            if (titleLower.includes('tech') || titleLower.includes('tmt')) {
                return 'Technology';
            } else if (titleLower.includes('healthcare') || titleLower.includes('life science')) {
                return 'Healthcare';
            } else if (titleLower.includes('real estate')) {
                return 'RealEstate';
            } else if (titleLower.includes('infrastructure')) {
                return 'Infrastructure';
            } else if (titleLower.includes('consumer')) {
                return 'Consumer';
            }
            
            return 'General';
        }
        
        /**
         * Calculate engagement score
         */
        calculateEngagementScore() {
            const interactions = this.state.analytics.totalInteractions;
            const views = this.state.interactions.jobsViewed.length;
            const saves = this.state.interactions.jobsSaved.length;
            const questions = this.state.interactions.questionsAnswered.length;
            
            // Weighted score
            const score = (views * 1) + (saves * 5) + (questions * 3) + (interactions * 0.5);
            
            this.state.analytics.engagementScore = Math.min(100, Math.round(score));
            
            return this.state.analytics.engagementScore;
        }
        
        /**
         * Save session to localStorage
         */
        saveSession() {
            try {
                const key = this.state.user.isLoggedIn ? 
                    `senna_session_${this.state.user.userId}` : 
                    'senna_session_anonymous';
                    
                localStorage.setItem(key, JSON.stringify(this.state));
            } catch (e) {
            }
        }
        
        /**
         * Load session from localStorage
         */
        loadSession() {
            try {
                const key = window.sffc_ajax?.user_logged_in ? 
                    `senna_session_${window.sffc_ajax.user_id}` : 
                    'senna_session_anonymous';
                    
                const saved = localStorage.getItem(key);
                return saved ? JSON.parse(saved) : null;
            } catch (e) {
                return null;
            }
        }
        
        /**
         * Clear session
         */
        clearSession() {
            this.initializeState();
            this.saveSession();
        }
        
        /**
         * Setup auto-save
         */
        setupAutoSave() {
            // Save every 30 seconds
            setInterval(() => {
                this.saveSession();
            }, 30000);
            
            // Save on page unload
            window.addEventListener('beforeunload', () => {
                this.saveSession();
            });
        }
        
        /**
         * Get session summary
         */
        getSessionSummary() {
            return {
                sessionId: this.state.sessionId,
                stage: this.state.stage,
                cvUploaded: this.state.cvData.uploaded,
                matchesFound: this.state.matchResults.totalFound,
                interactions: this.state.analytics.totalInteractions,
                engagementScore: this.calculateEngagementScore(),
                duration: Date.now() - new Date(this.state.timestamp).getTime()
            };
        }
    }
    
    // Initialize and expose globally
    window.SessionStateManager = SessionStateManager;
    
})(jQuery, window);
