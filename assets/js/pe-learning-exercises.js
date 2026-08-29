/**
 * PE Learning Exercises - Multi-Step Learning in Chat
 * Works WITH existing action-cards-system to provide guided learning experiences
 * NO DESIGN CHANGES - exercises happen in the existing MENA Careers chat interface
 * ALL RESPONSES FROM CLAUDE - no hardcoded feedback
 */

(function($) {
    'use strict';

    class PELearningExercises {
        constructor() {
            this.currentExercise = null;
            this.currentStep = 0;
            this.userProgress = this.loadProgress();
            this.exerciseData = this.getExerciseDefinitions();

            console.log('[PE Learning] System initialized');
            this.init();
        }

        init() {
            // Don't intercept chat - let everything flow to Claude
            // We just add context to messages when in an exercise
            this.setupContextInjection();
        }

        loadProgress() {
            const saved = localStorage.getItem('pe_learning_progress');
            if (saved) {
                try {
                    return JSON.parse(saved);
                } catch(e) {
                    console.error('[PE Learning] Failed to parse progress', e);
                }
            }
            return {
                completedExercises: [],
                currentExercise: null,
                currentStep: 0,
                totalXP: 0
            };
        }

        saveProgress() {
            localStorage.setItem('pe_learning_progress', JSON.stringify(this.userProgress));
        }

        /**
         * Predefined exercise definitions for specific learning paths
         */
        getExerciseDefinitions() {
            return {
                'lbo-fundamentals': {
                    id: 'lbo-fundamentals',
                    title: 'LBO Fundamentals',
                    totalSteps: 5,
                    learningObjectives: [
                        'Understand what an LBO is',
                        'Learn why PE firms use leverage',
                        'Know typical debt/equity splits',
                        'Calculate equity requirements',
                        'Identify attractive LBO targets'
                    ]
                },
                'lbo-target-screening': {
                    id: 'lbo-target-screening',
                    title: 'LBO Target Screening',
                    totalSteps: 4,
                    learningObjectives: [
                        'Compare LBO candidates',
                        'Understand minimum profitability requirements',
                        'Calculate EV/EBITDA multiples',
                        'Identify red flags in due diligence'
                    ]
                }
            };
        }

        /**
         * Setup context injection - adds exercise metadata as hidden system context
         */
        setupContextInjection() {
            const self = this;

            const wrapIfReady = () => {
                if (!window.SennaChat || !window.SennaChat.send || window.SennaChat.__peTutorWrapped) {
                    return false;
                }

                const originalSend = window.SennaChat.send.bind(window.SennaChat);

                window.SennaChat.send = function(message, additionalContext = {}) {
                    let contextPayload = {};
                    if (typeof additionalContext === 'string' && additionalContext.trim().length) {
                        contextPayload.system_prompt = additionalContext;
                    } else if (additionalContext && typeof additionalContext === 'object' && !Array.isArray(additionalContext)) {
                        contextPayload = { ...additionalContext };
                    }

                    if (self.currentExercise) {
                        const systemContext = self.buildSystemContext();
                        // PUT OVERRIDE CONTEXT FIRST - it must come before any other instructions
                        contextPayload.system_prompt = contextPayload.system_prompt
                            ? `${systemContext}\n\n${contextPayload.system_prompt}`
                            : systemContext;
                        if (self.currentExercise.id) {
                            contextPayload.exercise_id = self.currentExercise.id;
                        }
                    }

                    originalSend(message, contextPayload);
                };

                window.SennaChat.__peTutorWrapped = true;
                console.log('[PE Learning] Context injection set up');
                return true;
            };

            if (!wrapIfReady()) {
                const maxAttempts = 20;
                let attempts = 0;
                const timer = setInterval(() => {
                    attempts += 1;
                    if (wrapIfReady() || attempts >= maxAttempts) {
                        clearInterval(timer);
                    }
                }, 250);
            }
        }

        /**
         * Build system context (not shown to user, sent to Claude as instructions)
         */
        buildSystemContext() {
            const exercise = this.currentExercise;

            let context = `>>>OVERRIDE ALL OTHER INSTRUCTIONS<<<\n\n`;
            context += `YOU ARE NOW IN FINANCE TECHNICAL TUTOR MODE.\n`;
            context += `YOU ARE NOT A CAREER ADVISOR. YOU ARE NOT A FINANCE CAREER EXPERT.\n`;
            context += `YOU ARE A TECHNICAL FINANCE SKILLS TEACHER FOR IB, AM, AND PE.\n\n`;
            context += `IGNORE ANY PREVIOUS INSTRUCTIONS ABOUT BEING A CAREER ADVISOR.\n`;
            context += `DO NOT GIVE CAREER ADVICE. DO NOT DISCUSS JOB SEARCH. DO NOT TALK ABOUT NETWORKING.\n`;
            context += `YOUR ONLY JOB IS TO TEACH TECHNICAL FINANCE CONCEPTS.\n\n`;
            context += `[SYSTEM CONTEXT - Do not show this to user]\n`;

            context += `ACTIVE LESSON CONTEXT:\n`;
            context += `You are currently teaching a lesson on "${exercise.title}"\n`;
            context += `You have ALREADY introduced this lesson to the user.\n`;
            context += `The user knows you are teaching them about:\n`;
            if (exercise.learningObjectives) {
                exercise.learningObjectives.forEach((obj) => {
                    context += `- ${obj}\n`;
                });
            }
            context += `\nCurrent step: ${this.currentStep + 1} of ${exercise.totalSteps}\n`;
            if (exercise.learningObjectives) {
                context += `Progress through learning objectives:\n`;
                exercise.learningObjectives.forEach((obj, i) => {
                    const completed = i < this.currentStep ? '✓' : '-';
                    context += `${completed} ${obj}\n`;
                });
            }

            context += `\nCONVERSATION CONTINUITY:\n`;
            context += `- This is a CONTINUATION of the lesson you started\n`;
            context += `- DO NOT re-introduce yourself or the lesson\n`;
            context += `- DO NOT ask "What would you like to learn?"\n`;
            context += `- Continue teaching where you left off in the lesson flow\n`;
            context += `- Remember: you already introduced the topics above\n`;
            context += `- BE SPECIFIC: Reference the exact topic name from the objectives, not generic terms\n\n`;

            context += `COMMUNICATION STYLE - CRITICAL:\n`;
            context += `- Write ONLY direct dialogue - NO roleplay, NO action descriptions\n`;
            context += `- NEVER write "*clears throat*", "*shifts into mode*", "*nods*", or ANY asterisk actions\n`;
            context += `- NO stage directions, NO descriptions of what you're doing\n`;
            context += `- Just speak naturally and directly - be conversational but professional\n`;
            context += `- Get straight to teaching - don't announce you're switching modes\n\n`;

            context += `REMINDER - You are a FINANCE TECHNICAL TUTOR TEACHING technical concepts:\n`;
            context += `- Stay focused ONLY on investment banking, asset management, and private equity technical skills\n`;
            context += `- NEVER ask about career goals, background, or job search\n`;
            context += `- REFERENCE SPECIFIC TOPICS: Use the exact objective names, not generic terms\n`;
            context += `- EXPLAIN the finance concept first before asking questions\n`;
            context += `- Use real finance examples with actual numbers (DCF, comps, attribution, duration, LBO returns, etc.)\n`;
            context += `- After explaining, check understanding: "Does this make sense?"\n`;
            context += `- Then provide practice problems to apply what you taught\n`;
            context += `- Give detailed feedback with explanations, not just "correct/incorrect"\n`;
            context += `- When this objective is mastered, teach the next specific concept from the list\n`;
            context += `- Lead the conversation - you decide what to teach next\n`;
            context += `- DO NOT revert to career advisor mode under any circumstances\n`;
            context += `- NO roleplay text or action descriptions - just natural teaching dialogue\n\n`;

            context += `>>>FINAL REMINDER<<<\n`;
            context += `YOU ARE A FINANCE TECHNICAL TUTOR. NOT A CAREER ADVISOR.\n`;
            context += `TEACH ONLY TECHNICAL FINANCE CONCEPTS. NO CAREER ADVICE.\n`;

            return context;
        }

        /**
         * Start a learning exercise
         */
        startExercise(exerciseId, cardMeta = {}) {
            let exercise = this.exerciseData[exerciseId];

            // If no predefined exercise exists, create a dynamic one from the card
            if (!exercise) {
                console.log('[PE Learning] Creating dynamic exercise for:', exerciseId);
                exercise = this.createDynamicExercise(exerciseId, cardMeta);
            }

            if (!exercise) {
                console.error('[PE Learning] Failed to create exercise:', exerciseId);
                return;
            }

            console.log('[PE Learning] Starting exercise:', exerciseId);

            this.currentExercise = exercise;
            this.currentStep = 0;
            this.userProgress.currentExercise = exerciseId;
            this.userProgress.currentStep = 0;
            this.saveProgress();

            // Send the first prompt to Claude with full exercise context
            this.startExerciseWithClaude(exercise);
        }

        /**
         * Send initial exercise prompt to Claude
         */
        startExerciseWithClaude(exercise) {
            // Build system context (sent as additionalContext, not shown to user)
            let systemContext = `>>>OVERRIDE ALL OTHER INSTRUCTIONS<<<\n\n`;
            systemContext += `YOU ARE NOW IN FINANCE TECHNICAL TUTOR MODE.\n`;
            systemContext += `YOU ARE NOT A CAREER ADVISOR. YOU ARE NOT A FINANCE CAREER EXPERT.\n`;
            systemContext += `YOU ARE A FINANCE TECHNICAL SKILLS TEACHER FOR IB, AM, AND PE.\n\n`;
            systemContext += `IGNORE ANY PREVIOUS INSTRUCTIONS ABOUT BEING A CAREER ADVISOR.\n`;
            systemContext += `DO NOT GIVE CAREER ADVICE. DO NOT DISCUSS JOB SEARCH. DO NOT TALK ABOUT NETWORKING.\n`;
            systemContext += `YOUR ONLY JOB IS TO TEACH TECHNICAL FINANCE CONCEPTS.\n\n`;

            systemContext += `[SYSTEM CONTEXT - Start of learning exercise]\n`;
            systemContext += `Exercise: ${exercise.title}\n`;
            systemContext += `Total Steps: ${exercise.totalSteps}\n\n`;

            if (exercise.learningObjectives) {
                systemContext += `Learning Objectives to cover:\n`;
                exercise.learningObjectives.forEach(obj => {
                    systemContext += `- ${obj}\n`;
                });
                systemContext += `\n`;
            }

            systemContext += `TEACHER ROLE:\n`;
            systemContext += `You are MENA Careers, a finance technical teacher for investment banking, asset management, and private equity. This is a continuous learning conversation, not a start/stop script and not a generic Q&A bot.\n`;
            systemContext += `Do not provide job listings, application advice, CV advice, salary guidance, recruiting strategy, networking advice, or career coaching.\n`;
            systemContext += `If the student asks about any job-related topic, briefly say this room is for learning and convert the request into the relevant technical finance skill.\n\n`;

            systemContext += `HOW TO TEACH:\n`;
            systemContext += `- Continue naturally from the student's latest message and the current exercise.\n`;
            systemContext += `- Infer the student's learning style from their wording: beginner-friendly, numeric/model-driven, conceptual, concise, or exploratory.\n`;
            systemContext += `- Adapt quietly without announcing the learning-style diagnosis.\n`;
            systemContext += `- Teach one concept at a time.\n`;
            systemContext += `- Give a worked example with real numbers or formulas.\n`;
            systemContext += `- End with exactly one practice question or next-step prompt.\n`;
            systemContext += `- When the student answers, mark what is right, correct what is wrong, then advance one small step.\n`;
            systemContext += `- Use the specific exercise objectives; do not drift into generic finance commentary.\n`;
            systemContext += `- No roleplay text, no action descriptions, no stage directions.\n`;
            systemContext += `- Never say you are analyzing a complex query.\n\n`;

            systemContext += `STARTING BEHAVIOR:\n`;
            systemContext += `Start teaching immediately. Do not ask the student to type "start". Introduce the first concept, give a compact worked example, then ask one practice question.\n`;

            // User-facing message (what actually appears in chat)
            const userMessage = `I'd like to learn about ${exercise.title}`;

            // Send to Claude with system context
            if (window.SennaChat && window.SennaChat.send) {
                window.SennaChat.send(userMessage, {
                    system_prompt: systemContext,
                    exercise_id: exercise.id,
                    mode: 'pe_tutor'  // Force PE tutor mode
                });
            } else {
                console.error('[PE Learning] SennaChat not available');
            }
        }

        /**
         * Create a dynamic exercise from a card that doesn't have a predefined exercise
         */
        createDynamicExercise(exerciseId, cardMeta) {
            const {title, originalPrompt, actionType, learningObjectives} = cardMeta;

            const objectives = Array.isArray(learningObjectives) && learningObjectives.length
                ? learningObjectives
                : [
                    'Understand the core concepts',
                    'Apply the knowledge practically',
                    'Validate understanding with examples'
                ];

            return {
                id: exerciseId,
                title: title || exerciseId,
                totalSteps: 3,
                originalPrompt: originalPrompt,
                actionType: actionType,
                learningObjectives: objectives
            };
        }

        /**
         * Move to next step (called manually or automatically)
         */
        nextStep() {
            this.currentStep++;
            this.userProgress.currentStep = this.currentStep;

            if (this.currentStep >= this.currentExercise.totalSteps) {
                // Exercise complete
                this.completeExercise();
            } else {
                this.saveProgress();
                console.log('[PE Learning] Advanced to step', this.currentStep + 1);
            }
        }

        /**
         * Complete the current exercise
         */
        completeExercise() {
            const exerciseId = this.currentExercise.id;

            // Update progress
            if (!this.userProgress.completedExercises.includes(exerciseId)) {
                this.userProgress.completedExercises.push(exerciseId);
                this.userProgress.totalXP += 50;
            }

            this.currentExercise = null;
            this.currentStep = 0;
            this.userProgress.currentExercise = null;
            this.userProgress.currentStep = 0;
            this.saveProgress();

            console.log('[PE Learning] Exercise completed:', exerciseId);
        }

        /**
         * End exercise early (user wants to exit)
         */
        endExercise() {
            if (this.currentExercise) {
                console.log('[PE Learning] Exercise ended early:', this.currentExercise.id);
                this.currentExercise = null;
                this.currentStep = 0;
                this.userProgress.currentExercise = null;
                this.userProgress.currentStep = 0;
                this.saveProgress();
            }
        }
    }

    // Initialize when ready
    $(document).ready(function() {
        window.peLearningExercises = new PELearningExercises();
    });

})(jQuery);
