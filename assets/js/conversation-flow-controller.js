/**
 * Conversation Flow Controller for MENA Careers Chat
 * Manages conversation stages, questions, and intelligent follow-ups
 * 
 * @since 10.6.0
 */

(function($, window) {
    'use strict';
    
    class ConversationFlowController {
        constructor(stateManager) {
            this.stateManager = stateManager;
            this.currentFlow = null;
            this.initialize();
        }
        
        initialize() {
            // Define conversation flows
            this.flows = {
                cv_uploaded: {
                    name: 'CV Analysis Flow',
                    steps: [
                        {
                            id: 'confirm_profile',
                            trigger: 'after_showing_matches',
                            getMessage: (state) => {
                                const cv = state.cvData.parsed;
                                return `I see you're a <strong>${cv.latestRole || 'Professional'}</strong>${cv.company ? ` at ${cv.company}` : ''}${cv.location ? ` based in ${cv.location}` : ''}. Is this correct?`;
                            },
                            quickResponses: ['Yes, that\'s right', 'Not quite', 'Update details'],
                            handler: 'handleProfileConfirmation'
                        },
                        {
                            id: 'location_flexibility',
                            trigger: 'after_confirmation',
                            getMessage: (state) => {
                                const location = state.cvData.parsed.location;
                                return `Are you only looking for opportunities in <strong>${location}</strong>, or are you open to other locations?`;
                            },
                            quickResponses: ['Only ' + '{location}', 'Open to relocation', 'Nearby cities okay'],
                            handler: 'handleLocationPreference'
                        },
                        {
                            id: 'firm_preference',
                            trigger: 'after_3_views',
                            getMessage: () => 'What type of firms are you most interested in?',
                            quickResponses: ['Bulge Bracket', 'Mid-Market', 'Boutique', 'No preference'],
                            handler: 'handleFirmPreference'
                        }
                    ]
                },
                
                showing_matches: {
                    name: 'Match Refinement Flow',
                    steps: [
                        {
                            id: 'match_feedback',
                            trigger: 'after_showing_matches',
                            getMessage: (state) => {
                                const perfect = state.matchResults.perfectMatches.length;
                                const strong = state.matchResults.strongMatches.length;
                                
                                if (perfect > 0) {
                                    return `I found <strong>${perfect} perfect matches</strong> and ${strong} strong alternatives. Which type would you like to explore first?`;
                                } else if (strong > 0) {
                                    return `I found <strong>${strong} strong matches</strong> for your profile. Would you like to see all of them or shall I refine further?`;
                                } else {
                                    return `I found ${state.matchResults.totalFound} possible opportunities. Should I expand the search criteria?`;
                                }
                            },
                            quickResponses: (state) => {
                                if (state.matchResults.perfectMatches.length > 0) {
                                    return ['Show perfect matches', 'Show all matches', 'Refine criteria'];
                                }
                                return ['Show all', 'Expand search', 'Change criteria'];
                            },
                            handler: 'handleMatchFeedback'
                        },
                        {
                            id: 'interest_check',
                            trigger: 'after_3_views',
                            getMessage: (state) => {
                                const viewed = state.interactions.jobsViewed.slice(-3);
                                const pattern = this.detectViewingPattern(viewed);
                                
                                if (pattern) {
                                    return `I notice you're interested in ${pattern}. Should I find more similar opportunities?`;
                                }
                                return 'Which of these opportunities interests you most?';
                            },
                            quickResponses: ['Yes, find similar', 'No, show variety', 'Let me browse'],
                            handler: 'handleInterestSignal'
                        },
                        {
                            id: 'save_prompt',
                            trigger: 'after_5_views_no_save',
                            getMessage: () => 'Would you like to save any of these opportunities for later?',
                            quickResponses: ['Yes, show me how', 'Not yet', 'Set up alerts instead'],
                            handler: 'handleSavePrompt'
                        }
                    ]
                },
                
                no_matches: {
                    name: 'Recovery Flow',
                    steps: [
                        {
                            id: 'diagnose_issue',
                            trigger: 'immediate',
                            getMessage: (state) => {
                                const cv = state.cvData.parsed;
                                return `No exact matches found for ${cv.latestRole} in ${cv.location}. What's most important to you?`;
                            },
                            quickResponses: ['Keep same role', 'Keep same location', 'Open to both'],
                            handler: 'handleNoMatchDiagnosis'
                        },
                        {
                            id: 'expand_seniority',
                            trigger: 'if_role_flexible',
                            getMessage: (state) => {
                                const seniority = state.cvData.parsed.seniority;
                                const above = this.getSeniorityName(seniority + 1);
                                const below = this.getSeniorityName(seniority - 1);
                                
                                return `Would you consider ${below} or ${above} positions?`;
                            },
                            quickResponses: (state) => {
                                const s = state.cvData.parsed.seniority;
                                return [
                                    this.getSeniorityName(s - 1) + ' is fine',
                                    this.getSeniorityName(s + 1) + ' (stretch)',
                                    'Both are okay'
                                ];
                            },
                            handler: 'handleSeniorityExpansion'
                        },
                        {
                            id: 'expand_location',
                            trigger: 'if_location_flexible',
                            getMessage: (state) => {
                                const location = state.cvData.parsed.location;
                                const nearby = this.getNearbyLocations(location);
                                
                                return `How about opportunities in ${nearby.join(' or ')}?`;
                            },
                            quickResponses: (state) => {
                                const nearby = this.getNearbyLocations(state.cvData.parsed.location);
                                return ['Yes to all', nearby[0] + ' only', 'Show me options'];
                            },
                            handler: 'handleLocationExpansion'
                        }
                    ]
                },
                
                refinement: {
                    name: 'Preference Refinement Flow',
                    steps: [
                        {
                            id: 'refine_sectors',
                            trigger: 'after_pattern_detected',
                            getMessage: (state) => {
                                const sectors = state.preferences.inferred.sectorInterest;
                                if (sectors.length > 0) {
                                    return `You seem interested in ${sectors.join(' and ')}. Should I focus on these sectors?`;
                                }
                                return 'Any particular sectors you\'d like to focus on?';
                            },
                            quickResponses: ['Yes, focus there', 'Include all sectors', 'Let me choose'],
                            handler: 'handleSectorRefinement'
                        },
                        {
                            id: 'compensation_check',
                            trigger: 'after_10_interactions',
                            getMessage: () => 'Would you like to filter by compensation range?',
                            quickResponses: ['Yes', 'No, not important', 'Only show confirmed'],
                            handler: 'handleCompensationPreference'
                        },
                        {
                            id: 'deal_size_preference',
                            trigger: 'for_pe_roles',
                            getMessage: () => 'What deal size range interests you most?',
                            quickResponses: ['<$500M', '$500M-$2B', '$2B+', 'No preference'],
                            handler: 'handleDealSizePreference'
                        }
                    ]
                }
            };
            
            // Bind event listeners
            this.bindEvents();
        }
        
        bindEvents() {
            // Listen for stage changes
            $(document).on('sessionStageChanged', (e, stage, state) => {
                this.handleStageChange(stage, state);
            });
            
            // Listen for pattern detection
            $(document).on('patternsDetected', (e, patterns, state) => {
                this.handlePatternDetection(patterns, state);
            });
        }
        
        /**
         * Handle stage change
         */
        handleStageChange(stage, state) {
            // Check if we have a flow for this stage
            if (this.flows[stage]) {
                this.currentFlow = stage;
                this.checkTriggers(state);
            }
        }
        
        /**
         * Check if any triggers are met
         */
        checkTriggers(state) {
            if (!this.currentFlow) return;
            
            const flow = this.flows[this.currentFlow];
            const step = this.getNextUnaskedStep(flow, state);
            
            if (step) {
                if (this.shouldTriggerStep(step, state)) {
                    this.triggerStep(step, state);
                }
            }
        }
        
        /**
         * Get next unasked step in flow
         */
        getNextUnaskedStep(flow, state) {
            const answered = state.interactions.questionsAnswered.map(q => q.question);
            
            for (let step of flow.steps) {
                const message = typeof step.getMessage === 'function' ? 
                    step.getMessage(state) : step.getMessage;
                    
                if (!answered.includes(message)) {
                    return step;
                }
            }
            
            return null;
        }
        
        /**
         * Check if step should be triggered
         */
        shouldTriggerStep(step, state) {
            switch(step.trigger) {
                case 'immediate':
                    // For CV uploaded flow, wait until jobs are actually displayed
                    if (state.stage === 'cv_uploaded' && state.matchResults.displayedCount === 0) {
                        return false;
                    }
                    return true;
                    
                case 'after_confirmation':
                    return state.interactions.questionsAnswered.length >= 1;
                    
                case 'after_3_views':
                    return state.interactions.jobsViewed.length >= 3;
                    
                case 'after_5_views_no_save':
                    return state.interactions.jobsViewed.length >= 5 && 
                           state.interactions.jobsSaved.length === 0;
                           
                case 'after_showing_matches':
                    return state.matchResults.displayedCount > 0;
                    
                case 'after_pattern_detected':
                    return state.preferences.inferred.sectorInterest.length > 0;
                    
                case 'after_10_interactions':
                    return state.analytics.totalInteractions >= 10;
                    
                case 'for_pe_roles':
                    return state.interactions.jobsViewed.some(j => 
                        j.jobTitle.toLowerCase().includes('private equity') ||
                        j.company.toLowerCase().includes('partners')
                    );
                    
                default:
                    return false;
            }
        }
        
        /**
         * Trigger a conversation step
         */
        triggerStep(step, state) {
            const message = typeof step.getMessage === 'function' ? 
                step.getMessage(state) : step.getMessage;
                
            const quickResponses = typeof step.quickResponses === 'function' ?
                step.quickResponses(state) : step.quickResponses;
            
            // Queue the follow-up
            this.stateManager.queueFollowUp(message, 8, {
                stepId: step.id,
                handler: step.handler,
                quickResponses: this.processQuickResponses(quickResponses, state)
            });
            
            // Trigger event to display question
            $(document).trigger('showFollowUpQuestion', [{
                question: message,
                quickResponses: this.processQuickResponses(quickResponses, state),
                handler: step.handler,
                stepId: step.id
            }]);
        }
        
        /**
         * Process quick responses (replace variables)
         */
        processQuickResponses(responses, state) {
            if (!responses) return [];
            
            return responses.map(response => {
                let processed = response;
                
                // Replace {location} with actual location
                if (state.cvData.parsed?.location) {
                    processed = processed.replace('{location}', state.cvData.parsed.location);
                }
                
                return processed;
            });
        }
        
        /**
         * Detect viewing pattern
         */
        detectViewingPattern(viewedJobs) {
            if (!viewedJobs || viewedJobs.length < 3) return null;
            
            // Check for company pattern
            const companies = viewedJobs.map(j => j.company);
            const companyTypes = companies.map(c => this.getCompanyType(c));
            
            if (companyTypes.every(t => t === 'PE')) {
                return 'Private Equity firms';
            } else if (companyTypes.every(t => t === 'Bank')) {
                return 'Investment Banks';
            }
            
            // Check for location pattern
            const locations = viewedJobs.map(j => j.location?.split(',')[0]).filter(Boolean);
            if (locations.length >= 3 && locations.every(l => l === locations[0])) {
                return `roles in ${locations[0]}`;
            }
            
            // Check for title pattern
            const titles = viewedJobs.map(j => j.jobTitle.toLowerCase());
            if (titles.every(t => t.includes('director'))) {
                return 'Director level positions';
            } else if (titles.every(t => t.includes('vp') || t.includes('vice president'))) {
                return 'VP level positions';
            }
            
            return null;
        }
        
        /**
         * Get company type
         */
        getCompanyType(company) {
            if (!company) return 'Unknown';
            
            const lower = company.toLowerCase();
            if (lower.includes('partners') || lower.includes('capital')) return 'PE';
            if (lower.includes('bank') || lower.includes('sachs') || lower.includes('morgan')) return 'Bank';
            if (lower.includes('advisors') || lower.includes('advisory')) return 'Advisory';
            
            return 'Corporate';
        }
        
        /**
         * Get seniority name
         */
        getSeniorityName(level) {
            const names = {
                1: 'Analyst',
                2: 'Associate', 
                3: 'Senior Associate',
                4: 'Vice President',
                5: 'Director',
                6: 'Managing Director',
                7: 'Partner',
                8: 'Senior Partner'
            };
            
            return names[level] || 'Professional';
        }
        
        /**
         * Get nearby locations
         */
        getNearbyLocations(location) {
            const locationMap = {
                'London': ['Manchester', 'Birmingham', 'Edinburgh'],
                'New York': ['Boston', 'Philadelphia', 'Washington DC'],
                'San Francisco': ['Los Angeles', 'Seattle', 'San Diego'],
                'Hong Kong': ['Singapore', 'Shanghai', 'Tokyo'],
                'Dubai': ['Abu Dhabi', 'Riyadh', 'Doha']
            };
            
            const city = location?.split(',')[0].trim();
            return locationMap[city] || ['nearby cities'];
        }
        
        /**
         * Handle pattern detection
         */
        handlePatternDetection(patterns, state) {
            // Queue relevant questions based on patterns
            if (patterns.engagementLevel === 'high') {
                // User is engaged, ask more detailed questions
                this.checkTriggers(state);
            } else if (patterns.engagementLevel === 'low') {
                // User might be losing interest, offer help
                this.stateManager.queueFollowUp(
                    'Not finding what you\'re looking for? Let me help refine the search.',
                    9,
                    {
                        quickResponses: ['Yes, help me', 'Just browsing', 'Show different roles'],
                        handler: 'handleLowEngagement'
                    }
                );
            }
        }
        
        /**
         * Process user response to follow-up
         */
        processResponse(question, answer, context) {
            // Record the answer
            this.stateManager.recordAnswer(question, answer);
            
            // Call the appropriate handler
            if (context.handler && this[context.handler]) {
                this[context.handler](answer, this.stateManager.state);
            }
            
            // Check for next steps
            setTimeout(() => {
                this.checkTriggers(this.stateManager.state);
            }, 1000);
        }
        
        // Response Handlers
        
        handleProfileConfirmation(answer, state) {
            if (answer.includes('Yes') || answer.includes('right')) {
                this.stateManager.addPreference('confirmed', 'profileAccurate', true);
                $(document).trigger('profileConfirmed', [state.cvData.parsed]);
            } else {
                $(document).trigger('requestProfileUpdate');
            }
        }
        
        handleLocationPreference(answer, state) {
            if (answer.includes('Only')) {
                this.stateManager.addPreference('confirmed', 'locationFlexible', false);
            } else if (answer.includes('relocation')) {
                this.stateManager.addPreference('confirmed', 'locationFlexible', true);
                this.stateManager.state.preferences.flexibility.relocationWilling = true;
            } else if (answer.includes('Nearby')) {
                this.stateManager.addPreference('confirmed', 'locationFlexible', 'nearby');
            }
            
            this.stateManager.saveSession();
            $(document).trigger('preferencesUpdated', ['location', answer]);
        }
        
        handleFirmPreference(answer, state) {
            const firmTypes = {
                'Bulge Bracket': 'bulge',
                'Mid-Market': 'midmarket',
                'Boutique': 'boutique',
                'No preference': 'all'
            };
            
            const preference = firmTypes[answer] || 'all';
            this.stateManager.addPreference('confirmed', 'firmType', preference);
            
            $(document).trigger('preferencesUpdated', ['firmType', preference]);
        }
        
        handleMatchFeedback(answer, state) {
            if (answer.includes('perfect')) {
                $(document).trigger('showMatchCategory', ['perfect']);
            } else if (answer.includes('all')) {
                $(document).trigger('showMatchCategory', ['all']);
            } else if (answer.includes('Expand') || answer.includes('expand')) {
                $(document).trigger('expandSearchCriteria');
            } else if (answer.includes('Refine') || answer.includes('Change')) {
                $(document).trigger('refineSearchCriteria');
            }
        }
        
        handleInterestSignal(answer, state) {
            if (answer.includes('similar')) {
                const pattern = this.detectViewingPattern(state.interactions.jobsViewed.slice(-3));
                $(document).trigger('findSimilarJobs', [pattern]);
            } else if (answer.includes('variety')) {
                $(document).trigger('showVariedJobs');
            }
        }
        
        handleSavePrompt(answer, state) {
            if (answer.includes('show me how')) {
                $(document).trigger('showSaveInstructions');
            } else if (answer.includes('alerts')) {
                $(document).trigger('setupJobAlerts');
            }
        }
        
        handleNoMatchDiagnosis(answer, state) {
            if (answer.includes('role')) {
                this.stateManager.addPreference('confirmed', 'priorityRole', true);
                $(document).trigger('expandLocationSearch');
            } else if (answer.includes('location')) {
                this.stateManager.addPreference('confirmed', 'priorityLocation', true);
                $(document).trigger('expandSenioritySearch');
            } else {
                $(document).trigger('expandAllCriteria');
            }
        }
    }
    
    // Expose globally
    window.ConversationFlowController = ConversationFlowController;
    
})(jQuery, window);