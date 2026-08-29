/**
 * Mock Interview Simulator
 * Phase 4: AI-powered interview practice system
 *
 * @package SennaCareers
 * @since 1.4.0
 */

(function($) {
    'use strict';

    var MockInterviewSimulator = {
        sessionId: null,
        currentQuestion: null,
        questionIndex: 0,
        totalQuestions: 0,
        interviewType: null,
        difficultyLevel: null,
        startTime: null,
        allFeedback: [],

        /**
         * Initialize mock interview system
         */
        init: function() {
            this.bindEvents();
            this.renderInterviewTypes();
        },

        /**
         * Bind event listeners
         */
        bindEvents: function() {
            var self = this;

            // Start interview button
            $(document).on('click', '.sffc-start-interview-btn', function(e) {
                e.preventDefault();
                var type = $(this).data('interview-type');
                var level = $(this).data('difficulty-level');
                self.startInterview(type, level);
            });

            // Submit answer button
            $(document).on('click', '.sffc-submit-answer-btn', function(e) {
                e.preventDefault();
                self.submitAnswer();
            });

            // Next question button
            $(document).on('click', '.sffc-next-question-btn', function(e) {
                e.preventDefault();
                self.showNextQuestion();
            });

            // End interview button
            $(document).on('click', '.sffc-end-interview-btn', function(e) {
                e.preventDefault();
                self.endInterview();
            });

            // View interview history
            $(document).on('click', '.sffc-view-interview-history', function(e) {
                e.preventDefault();
                self.loadInterviewHistory();
            });
        },

        /**
         * Render interview type selection
         */
        renderInterviewTypes: function() {
            var html = `
                <div class="sffc-interview-types">
                    <h2>Choose Your Interview Type</h2>
                    <p class="sffc-interview-subtitle">Practice with AI-powered interview simulation</p>

                    <div class="sffc-interview-type-grid">
                        <div class="sffc-interview-type-card" data-type="investment_banking">
                            <div class="sffc-interview-type-icon">🏦</div>
                            <h3>Investment Banking</h3>
                            <p>Technical and behavioral questions for IB roles</p>
                            <div class="sffc-difficulty-selector">
                                <button class="sffc-start-interview-btn" data-interview-type="investment_banking" data-difficulty-level="Analyst">
                                    Analyst Level
                                </button>
                                <button class="sffc-start-interview-btn" data-interview-type="investment_banking" data-difficulty-level="Associate">
                                    Associate Level
                                </button>
                            </div>
                        </div>

                        <div class="sffc-interview-type-card" data-type="private_equity">
                            <div class="sffc-interview-type-icon">💼</div>
                            <h3>Private Equity</h3>
                            <p>LBO modeling, deal experience, and market views</p>
                            <div class="sffc-difficulty-selector">
                                <button class="sffc-start-interview-btn" data-interview-type="private_equity" data-difficulty-level="Associate">
                                    Associate Level
                                </button>
                                <button class="sffc-start-interview-btn" data-interview-type="private_equity" data-difficulty-level="VP">
                                    VP Level
                                </button>
                            </div>
                        </div>

                        <div class="sffc-interview-type-card" data-type="hedge_fund">
                            <div class="sffc-interview-type-icon">📈</div>
                            <h3>Private Credit</h3>
                            <p>Credit underwriting, debt structuring, and downside risk analysis</p>
                            <div class="sffc-difficulty-selector">
                                <button class="sffc-start-interview-btn" data-interview-type="hedge_fund" data-difficulty-level="Analyst">
                                    Analyst Level
                                </button>
                                <button class="sffc-start-interview-btn" data-interview-type="hedge_fund" data-difficulty-level="PM">
                                    PM Level
                                </button>
                            </div>
                        </div>

                        <div class="sffc-interview-type-card" data-type="consulting">
                            <div class="sffc-interview-type-icon">💡</div>
                            <h3>Management Consulting</h3>
                            <p>Case studies, frameworks, and problem-solving</p>
                            <div class="sffc-difficulty-selector">
                                <button class="sffc-start-interview-btn" data-interview-type="consulting" data-difficulty-level="Analyst">
                                    Analyst Level
                                </button>
                                <button class="sffc-start-interview-btn" data-interview-type="consulting" data-difficulty-level="Consultant">
                                    Consultant Level
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="sffc-interview-history-section">
                        <button class="sffc-btn sffc-btn-secondary sffc-view-interview-history">
                            View Past Interviews
                        </button>
                    </div>
                </div>
            `;

            $('.sffc-crm-mock-interview-content').html(html);
        },

        /**
         * Start interview
         */
        startInterview: function(type, level) {
            var self = this;

            $.ajax({
                url: sffcCRMLinkedIn.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_start_mock_interview',
                    interview_type: type,
                    difficulty_level: level,
                    nonce: sffcCRMLinkedIn.nonce
                },
                beforeSend: function() {
                    self.showLoading('Preparing your interview...');
                },
                success: function(response) {
                    if (response.success) {
                        self.sessionId = response.data.session_id;
                        self.totalQuestions = response.data.total_questions;
                        self.currentQuestion = response.data.first_question;
                        self.questionIndex = 0;
                        self.interviewType = response.data.interview_type;
                        self.difficultyLevel = response.data.difficulty_level;
                        self.startTime = Date.now();
                        self.allFeedback = [];

                        self.renderInterviewScreen();
                        self.displayQuestion(self.currentQuestion);
                    } else {
                        self.showError(response.data.message || 'Failed to start interview');
                    }
                },
                error: function() {
                    self.showError('Connection error. Please try again.');
                }
            });
        },

        /**
         * Render interview screen
         */
        renderInterviewScreen: function() {
            var html = `
                <div class="sffc-interview-active">
                    <div class="sffc-interview-header">
                        <div class="sffc-interview-progress">
                            <span class="sffc-interview-progress-text">Question <span class="sffc-current-q">1</span> of ${this.totalQuestions}</span>
                            <div class="sffc-interview-progress-bar">
                                <div class="sffc-interview-progress-fill" style="width: 0%"></div>
                            </div>
                        </div>
                        <button class="sffc-btn sffc-btn-text sffc-end-interview-btn">End Interview</button>
                    </div>

                    <div class="sffc-interview-question-container">
                        <div class="sffc-interview-question-box">
                            <div class="sffc-interview-category-badge"></div>
                            <h2 class="sffc-interview-question-text"></h2>
                            <p class="sffc-interview-question-hint"></p>
                        </div>

                        <div class="sffc-interview-answer-section">
                            <label for="sffc-interview-answer">Your Answer:</label>
                            <textarea id="sffc-interview-answer" class="sffc-interview-answer-input"
                                rows="10" placeholder="Type your answer here..."></textarea>
                            <div class="sffc-interview-answer-actions">
                                <button class="sffc-btn sffc-btn-primary sffc-submit-answer-btn">
                                    Submit Answer
                                </button>
                            </div>
                        </div>

                        <div class="sffc-interview-feedback-section" style="display: none;">
                            <div class="sffc-interview-feedback-card">
                                <div class="sffc-interview-score-display">
                                    <div class="sffc-score-circle">
                                        <svg width="100" height="100">
                                            <circle cx="50" cy="50" r="45" fill="none" stroke="#E5E7EB" stroke-width="8"></circle>
                                            <circle cx="50" cy="50" r="45" fill="none" stroke="#10B981" stroke-width="8"
                                                class="sffc-score-arc" stroke-dasharray="0 283" transform="rotate(-90 50 50)"></circle>
                                            <text x="50" y="55" text-anchor="middle" font-size="24" font-weight="bold" fill="#10B981" class="sffc-score-text">0</text>
                                        </svg>
                                    </div>
                                    <div class="sffc-score-label">Your Score</div>
                                </div>

                                <div class="sffc-interview-feedback-content">
                                    <div class="sffc-feedback-section">
                                        <h4>✓ Strengths</h4>
                                        <ul class="sffc-feedback-strengths"></ul>
                                    </div>

                                    <div class="sffc-feedback-section">
                                        <h4>→ Areas to Improve</h4>
                                        <ul class="sffc-feedback-improvements"></ul>
                                    </div>

                                    <div class="sffc-feedback-section">
                                        <h4>💡 Better Answer</h4>
                                        <div class="sffc-feedback-model-answer"></div>
                                    </div>
                                </div>

                                <div class="sffc-interview-navigation">
                                    <button class="sffc-btn sffc-btn-primary sffc-next-question-btn">
                                        Next Question →
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            $('.sffc-crm-mock-interview-content').html(html);
        },

        /**
         * Display question
         */
        displayQuestion: function(question) {
            // Update progress
            this.questionIndex++;
            $('.sffc-current-q').text(this.questionIndex);
            var progress = (this.questionIndex / this.totalQuestions) * 100;
            $('.sffc-interview-progress-fill').css('width', progress + '%');

            // Display question
            $('.sffc-interview-category-badge').text(question.category.toUpperCase());
            $('.sffc-interview-question-text').text(question.question);
            $('.sffc-interview-question-hint').text('Ideal time: ' + (question.ideal_time || '2-3 minutes'));

            // Reset answer section
            $('.sffc-interview-answer-input').val('').prop('disabled', false);
            $('.sffc-interview-answer-section').show();
            $('.sffc-interview-feedback-section').hide();
            $('.sffc-submit-answer-btn').prop('disabled', false).text('Submit Answer');
        },

        /**
         * Submit answer
         */
        submitAnswer: function() {
            var self = this;
            var answer = $('.sffc-interview-answer-input').val().trim();

            if (!answer) {
                self.showError('Please provide an answer');
                return;
            }

            $.ajax({
                url: sffcCRMLinkedIn.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_submit_interview_answer',
                    session_id: self.sessionId,
                    answer: answer,
                    nonce: sffcCRMLinkedIn.nonce
                },
                beforeSend: function() {
                    $('.sffc-submit-answer-btn').prop('disabled', true).text('Evaluating...');
                },
                success: function(response) {
                    if (response.success) {
                        self.allFeedback.push(response.data.feedback);
                        self.displayFeedback(response.data.feedback);

                        // Store next question
                        if (response.data.has_next) {
                            self.currentQuestion = response.data.next_question;
                        } else {
                            // Last question - show different button
                            $('.sffc-next-question-btn').text('View Results →');
                        }
                    } else {
                        self.showError(response.data.message || 'Failed to evaluate answer');
                        $('.sffc-submit-answer-btn').prop('disabled', false).text('Submit Answer');
                    }
                },
                error: function() {
                    self.showError('Connection error. Please try again.');
                    $('.sffc-submit-answer-btn').prop('disabled', false).text('Submit Answer');
                }
            });
        },

        /**
         * Display feedback
         */
        displayFeedback: function(feedback) {
            // Show feedback section
            $('.sffc-interview-answer-section').hide();
            $('.sffc-interview-feedback-section').show();

            // Update score circle
            var score = feedback.score || 0;
            var circumference = 283; // 2 * PI * 45
            var dashArray = (circumference * score / 10) + ' ' + circumference;
            $('.sffc-score-arc').attr('stroke-dasharray', dashArray);
            $('.sffc-score-text').text(score + '/10');

            // Display strengths
            var strengthsHtml = '';
            if (feedback.strengths && feedback.strengths.length > 0) {
                feedback.strengths.forEach(function(strength) {
                    strengthsHtml += '<li>' + strength + '</li>';
                });
            } else {
                strengthsHtml = '<li>Keep practicing to improve!</li>';
            }
            $('.sffc-feedback-strengths').html(strengthsHtml);

            // Display improvements
            var improvementsHtml = '';
            if (feedback.missed_points && feedback.missed_points.length > 0) {
                feedback.missed_points.forEach(function(point) {
                    improvementsHtml += '<li>' + point + '</li>';
                });
            } else {
                improvementsHtml = '<li>Great job covering all key points!</li>';
            }
            $('.sffc-feedback-improvements').html(improvementsHtml);

            // Display model answer
            $('.sffc-feedback-model-answer').html('<pre>' + feedback.better_answer + '</pre>');
        },

        /**
         * Show next question
         */
        showNextQuestion: function() {
            if (this.questionIndex >= this.totalQuestions) {
                // All questions done - end interview
                this.endInterview();
            } else {
                // Show next question
                this.displayQuestion(this.currentQuestion);
            }
        },

        /**
         * End interview
         */
        endInterview: function() {
            var self = this;

            $.ajax({
                url: sffcCRMLinkedIn.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_end_mock_interview',
                    session_id: self.sessionId,
                    nonce: sffcCRMLinkedIn.nonce
                },
                beforeSend: function() {
                    self.showLoading('Calculating your results...');
                },
                success: function(response) {
                    if (response.success) {
                        self.displayResults(response.data);
                    } else {
                        self.showError(response.data.message || 'Failed to end interview');
                    }
                },
                error: function() {
                    self.showError('Connection error. Please try again.');
                }
            });
        },

        /**
         * Display final results
         */
        displayResults: function(data) {
            var duration = Math.floor(data.duration / 60); // Convert to minutes

            var html = `
                <div class="sffc-interview-results">
                    <div class="sffc-results-header">
                        <h2>Interview Complete! ✓</h2>
                        <p>Great job completing the interview. Here are your results:</p>
                    </div>

                    <div class="sffc-results-scores">
                        <div class="sffc-results-score-card">
                            <div class="sffc-results-score-value">${data.scores.overall}</div>
                            <div class="sffc-results-score-label">Overall Score</div>
                        </div>
                        <div class="sffc-results-score-card">
                            <div class="sffc-results-score-value">${data.scores.technical}</div>
                            <div class="sffc-results-score-label">Technical</div>
                        </div>
                        <div class="sffc-results-score-card">
                            <div class="sffc-results-score-value">${data.scores.behavioral}</div>
                            <div class="sffc-results-score-label">Behavioral</div>
                        </div>
                        <div class="sffc-results-score-card">
                            <div class="sffc-results-score-value">${data.scores.case}</div>
                            <div class="sffc-results-score-label">Case/Market</div>
                        </div>
                    </div>

                    <div class="sffc-results-details">
                        <div class="sffc-results-section">
                            <h3>🎯 Your Strengths</h3>
                            <ul>
                                ${data.strengths.slice(0, 5).map(s => '<li>' + s + '</li>').join('')}
                            </ul>
                        </div>

                        <div class="sffc-results-section">
                            <h3>📚 Areas for Improvement</h3>
                            <ul>
                                ${data.improvements.slice(0, 5).map(i => '<li>' + i + '</li>').join('')}
                            </ul>
                        </div>

                        <div class="sffc-results-section">
                            <h3>💡 Recommended Courses</h3>
                            <p>Based on your performance, we recommend reviewing:</p>
                            <ul>
                                <li>Financial Modeling Fundamentals</li>
                                <li>Interview Preparation Course</li>
                            </ul>
                        </div>

                        <div class="sffc-results-meta">
                            <span>Duration: ${duration} minutes</span>
                            <span>Questions Answered: ${data.questions_answered}</span>
                        </div>
                    </div>

                    <div class="sffc-results-actions">
                        <button class="sffc-btn sffc-btn-primary" onclick="location.reload()">
                            Start New Interview
                        </button>
                        <button class="sffc-btn sffc-btn-secondary sffc-view-interview-history">
                            View History
                        </button>
                    </div>
                </div>
            `;

            $('.sffc-crm-mock-interview-content').html(html);
        },

        /**
         * Load interview history
         */
        loadInterviewHistory: function() {
            var self = this;

            $.ajax({
                url: sffcCRMLinkedIn.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_get_interview_stats',
                    nonce: sffcCRMLinkedIn.nonce
                },
                beforeSend: function() {
                    self.showLoading('Loading your interview history...');
                },
                success: function(response) {
                    if (response.success) {
                        self.displayHistory(response.data);
                    } else {
                        self.showError(response.data.message || 'Failed to load history');
                    }
                },
                error: function() {
                    self.showError('Connection error. Please try again.');
                }
            });
        },

        /**
         * Display interview history
         */
        displayHistory: function(data) {
            var stats = data.stats || {};
            var history = data.history || [];

            var html = `
                <div class="sffc-interview-history">
                    <div class="sffc-history-header">
                        <h2>Your Interview History</h2>
                        <button class="sffc-btn sffc-btn-secondary" onclick="location.reload()">
                            ← Back to Interviews
                        </button>
                    </div>

                    <div class="sffc-history-stats">
                        <div class="sffc-stat-card">
                            <div class="sffc-stat-value">${stats.total_interviews || 0}</div>
                            <div class="sffc-stat-label">Total Interviews</div>
                        </div>
                        <div class="sffc-stat-card">
                            <div class="sffc-stat-value">${stats.avg_score ? Math.round(stats.avg_score) : 0}</div>
                            <div class="sffc-stat-label">Average Score</div>
                        </div>
                        <div class="sffc-stat-card">
                            <div class="sffc-stat-value">${stats.best_score ? Math.round(stats.best_score) : 0}</div>
                            <div class="sffc-stat-label">Best Score</div>
                        </div>
                    </div>

                    <div class="sffc-history-list">
                        <h3>Recent Interviews</h3>
                        <div class="sffc-history-items">
                            ${history.length > 0 ? history.map(interview => `
                                <div class="sffc-history-item">
                                    <div class="sffc-history-item-header">
                                        <strong>${interview.interview_type.replace('_', ' ').toUpperCase()}</strong>
                                        <span class="sffc-history-item-date">${new Date(interview.conducted_at).toLocaleDateString()}</span>
                                    </div>
                                    <div class="sffc-history-item-score">Score: ${Math.round(interview.overall_score)}/100</div>
                                </div>
                            `).join('') : '<p>No interviews yet. Start your first interview!</p>'}
                        </div>
                    </div>
                </div>
            `;

            $('.sffc-crm-mock-interview-content').html(html);
        },

        /**
         * Show loading state
         */
        showLoading: function(message) {
            var html = `
                <div class="sffc-interview-loading">
                    <div class="sffc-loading-spinner"></div>
                    <p>${message}</p>
                </div>
            `;
            $('.sffc-crm-mock-interview-content').html(html);
        },

        /**
         * Show error message
         */
        showError: function(message) {
            // Show error in notification
            console.error('Mock Interview Error:', message);
            // Could use existing notification system here
            alert(message);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        if ($('.sffc-crm-mock-interview-content').length > 0) {
            MockInterviewSimulator.init();
        }
    });

    // Expose to global scope
    window.sffcMockInterview = MockInterviewSimulator;

})(jQuery);
