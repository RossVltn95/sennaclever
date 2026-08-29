/**
 * Learning Platform JavaScript
 * Phase 2: Learning Tab UI Functionality
 *
 * Handles course browsing, filtering, enrollment, and progress tracking
 */

(function($) {
    'use strict';

    // Learning Platform Manager
    var LearningPlatform = {
        currentSubTab: 'courses',
        currentFilter: 'all',
        visibleCourses: 6,
        allCourses: [],

        init: function() {
            this.bindEvents();
            this.bindSidebarStats();
            this.loadCourses();
            this.loadUserProgress();
        },

        bindSidebarStats: function() {
            var self = this;

            // Listen for main tab switches
            $(document).on('click', '.sffc-crm-linkedin-tab', function() {
                var tab = $(this).data('tab');

                if (tab === 'learning') {
                    // Show learning stats, hide default stats
                    $('[data-stats="default"]').hide();
                    $('[data-stats="learning"]').show();

                    // Load learning stats
                    self.loadUserProgress();
                } else {
                    // Show default stats, hide learning stats
                    $('[data-stats="learning"]').hide();
                    $('[data-stats="default"]').show();
                }
            });
        },

        bindEvents: function() {
            var self = this;

            // Sub-tab switching
            $(document).on('click', '.sffc-crm-learning-sub-tab', function(e) {
                e.preventDefault();
                var tab = $(this).data('learning-tab');
                self.switchSubTab(tab);
            });

            // Filter buttons
            $(document).on('click', '.sffc-crm-filter-btn', function(e) {
                e.preventDefault();
                $('.sffc-crm-filter-btn').removeClass('active');
                $(this).addClass('active');

                var filter = $(this).data('filter');
                self.currentFilter = filter;
                self.visibleCourses = 6; // Reset
                self.filterCourses(filter);
            });

            // Load more button
            $(document).on('click', '.sffc-crm-load-more-courses', function(e) {
                e.preventDefault();
                self.visibleCourses += 6;
                self.renderCourses();
            });

            // Course card click - navigate to course page
            $(document).on('click', '.sffc-crm-course-card', function(e) {
                // Don't navigate if clicking a button
                if ($(e.target).closest('button').length) {
                    return;
                }

                var courseId = $(this).data('course-id');
                self.viewCourse(courseId);
            });

            // Course enrollment/continue buttons (in course grid)
            $(document).on('click', '.sffc-crm-enroll-course', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var courseId = $(this).data('course-id');
                self.viewCourse(courseId);
            });

            $(document).on('click', '.sffc-crm-continue-course', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var courseId = $(this).data('course-id');
                self.viewCourse(courseId);
            });

            // Enrollment button on course overview page
            $(document).on('click', '.sffc-enroll-course-btn', function(e) {
                e.preventDefault();
                var courseId = $(this).data('course-id');
                self.enrollCourse(courseId);
            });

            // Mark lesson as complete button
            $(document).on('click', '.sffc-mark-complete-btn', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var courseId = $btn.data('course-id');
                var moduleId = $btn.data('module-id');
                var lessonId = $btn.data('lesson-id');
                self.markLessonComplete(courseId, moduleId, lessonId, $btn);
            });

            // Learning search/composer
            $(document).on('keypress', '.sffc-crm-learning-search', function(e) {
                if (e.which === 13) { // Enter key
                    e.preventDefault();
                    var query = $(this).val().trim();
                    if (query) {
                        self.searchCourses(query);
                    }
                }
            });

            // Suggestion buttons
            $(document).on('click', '.sffc-crm-learning-composer .sffc-crm-suggestion-btn', function(e) {
                e.preventDefault();
                var prompt = $(this).data('prompt');
                $('.sffc-crm-learning-search').val(prompt).focus();

                // Visual feedback
                $(this).css({
                    'background': 'var(--senna-accent)',
                    'border-color': 'var(--senna-primary)',
                    'color': 'var(--senna-primary)'
                });

                setTimeout(function() {
                    $(this).css({'background': '', 'border-color': '', 'color': ''});
                }.bind(this), 300);
            });

            // Mock interview buttons
            $(document).on('click', '.sffc-crm-start-interview', function(e) {
                e.preventDefault();
                var type = $(this).data('interview-type');
                self.startMockInterview(type);
            });
        },

        switchSubTab: function(tab) {
            this.currentSubTab = tab;

            // Update tab buttons
            $('.sffc-crm-learning-sub-tab').removeClass('active');
            $('.sffc-crm-learning-sub-tab[data-learning-tab="' + tab + '"]').addClass('active');

            // Update content areas
            $('.sffc-crm-learning-content').removeClass('active');
            $('.sffc-crm-learning-content[data-learning-content="' + tab + '"]').addClass('active');

            // Load tab-specific data
            if (tab === 'my-learning') {
                this.loadUserProgress();
            } else if (tab === 'mock-interviews') {
                this.loadInterviewHistory();
            } else if (tab === 'certificates') {
                this.loadCertificates();
            }
        },

        loadCourses: function() {
            var self = this;

            $.ajax({
                url: sffcCRMLinkedIn.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_get_learning_courses',
                    nonce: sffcCRMLinkedIn.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.allCourses = response.data.courses || [];
                        self.renderCourses();
                    } else {
                        self.showError('Failed to load courses');
                    }
                },
                error: function() {
                    self.showError('Network error loading courses');
                }
            });
        },

        renderCourses: function() {
            var self = this;
            var $grid = $('#sffc-courses-grid');
            var coursesToShow = this.allCourses.slice(0, this.visibleCourses);

            if (coursesToShow.length === 0) {
                $grid.html('<p class="sffc-crm-empty-state">No courses found matching your criteria.</p>');
                $('.sffc-crm-learning-load-more').hide();
                return;
            }

            var html = '';
            coursesToShow.forEach(function(course) {
                html += self.createCourseCard(course);
            });

            $grid.html(html);

            // Show/hide load more button
            if (this.visibleCourses >= this.allCourses.length) {
                $('.sffc-crm-learning-load-more').hide();
            } else {
                $('.sffc-crm-learning-load-more').show();
            }
        },

        createCourseCard: function(course) {
            var progress = course.progress || 0;
            var isEnrolled = progress > 0;
            var difficultyClass = (course.difficulty || 'intermediate').toLowerCase();

            var skillsHtml = '';
            if (course.skills && course.skills.length > 0) {
                course.skills.slice(0, 3).forEach(function(skill) {
                    skillsHtml += '<span class="sffc-crm-skill-tag">' + skill + '</span>';
                });
            }

            var progressHtml = '';
            if (isEnrolled) {
                progressHtml = `
                    <div class="sffc-crm-course-progress">
                        <div class="sffc-crm-progress-bar">
                            <div class="sffc-crm-progress-fill" style="width: ${progress}%"></div>
                        </div>
                        <p class="sffc-crm-progress-text">${progress}% Complete</p>
                    </div>
                `;
            }

            var actionButton = isEnrolled
                ? `<button class="sffc-crm-btn sffc-crm-btn-primary sffc-crm-continue-course" data-course-id="${course.id}">Continue Learning</button>`
                : `<button class="sffc-crm-btn sffc-crm-btn-primary sffc-crm-enroll-course" data-course-id="${course.id}">Start Course</button>`;

            return `
                <article class="sffc-crm-course-card" data-course-id="${course.id}" data-category="${course.category}">
                    <div class="sffc-crm-course-header">
                        <div class="sffc-crm-course-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                                <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
                            </svg>
                        </div>
                        <span class="sffc-crm-difficulty-badge ${difficultyClass}">${course.difficulty || 'Intermediate'}</span>
                    </div>

                    <h3 class="sffc-crm-course-title">${course.title}</h3>

                    <p class="sffc-crm-course-instructor">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="8.5" cy="7" r="4"></circle>
                            <polyline points="17 11 19 13 23 9"></polyline>
                        </svg>
                        ${course.instructor || 'MENA Careers + Expert Review'}
                    </p>

                    <div class="sffc-crm-course-meta">
                        <span class="sffc-crm-course-meta-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                            ${course.hours || 0} hours
                        </span>
                        <span class="sffc-crm-course-meta-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                            </svg>
                            ${course.lessons || 0} lessons
                        </span>
                        <span class="sffc-crm-course-meta-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path>
                            </svg>
                            Certificate
                        </span>
                    </div>

                    <p class="sffc-crm-course-description">${course.description || ''}</p>

                    <div class="sffc-crm-skills-taught">
                        ${skillsHtml}
                    </div>

                    ${progressHtml}

                    <div class="sffc-crm-course-actions">
                        ${actionButton}
                    </div>
                </article>
            `;
        },

        filterCourses: function(filter) {
            var self = this;

            if (filter === 'all') {
                // Show all courses
                this.renderCourses();
                return;
            }

            if (filter === 'in-progress') {
                // Filter enrolled courses
                this.allCourses = this.allCourses.filter(function(course) {
                    return course.progress > 0 && course.progress < 100;
                });
            } else {
                // Filter by category
                this.allCourses = this.allCourses.filter(function(course) {
                    return course.category && course.category.toLowerCase().replace(/\s+/g, '-') === filter;
                });
            }

            this.renderCourses();

            // Reload all courses for next filter
            setTimeout(function() {
                self.loadCourses();
            }, 100);
        },

        searchCourses: function(query) {
            var self = this;

            $.ajax({
                url: sffcCRMLinkedIn.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_search_learning_courses',
                    query: query,
                    nonce: sffcCRMLinkedIn.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.allCourses = response.data.courses || [];
                        self.renderCourses();
                    }
                }
            });
        },

        enrollCourse: function(courseId) {
            var self = this;

            $.ajax({
                url: sffcCRMLinkedIn.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_enroll_course',
                    course_id: courseId,
                    nonce: sffcCRMLinkedIn.nonce
                },
                beforeSend: function() {
                    $('.sffc-crm-enroll-course[data-course-id="' + courseId + '"], .sffc-enroll-course-btn[data-course-id="' + courseId + '"]')
                        .prop('disabled', true)
                        .text('Enrolling...');
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Enrolled successfully!');

                        // Reload page to show updated enrollment status (if on course page)
                        if (window.location.pathname.indexOf('/course/') !== -1) {
                            setTimeout(function() {
                                window.location.reload();
                            }, 1000);
                        } else {
                            // Navigate to course page
                            setTimeout(function() {
                                self.viewCourse(courseId);
                            }, 1000);
                        }
                    } else {
                        self.showError(response.data.message || 'Enrollment failed');
                        $('.sffc-crm-enroll-course[data-course-id="' + courseId + '"], .sffc-enroll-course-btn[data-course-id="' + courseId + '"]')
                            .prop('disabled', false)
                            .text('Start Course');
                    }
                }
            });
        },

        viewCourse: function(courseId) {
            // Find course to get slug
            var course = this.allCourses.find(function(c) {
                return c.id == courseId;
            });

            if (!course) {
                console.error('Course not found:', courseId);
                return;
            }

            // Navigate to course page using the slug from WordPress
            var courseSlug = course.slug || course.title.toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');

            window.location.href = '/course/' + courseSlug + '/';
        },

        continueCourse: function(courseId) {
            // Navigate to course page (same as viewCourse)
            this.viewCourse(courseId);
        },

        markLessonComplete: function(courseId, moduleId, lessonId, $btn) {
            var self = this;

            $.ajax({
                url: sffcCRMLinkedIn.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_mark_lesson_complete',
                    course_id: courseId,
                    module_id: moduleId,
                    lesson_id: lessonId,
                    nonce: sffcCRMLinkedIn.nonce
                },
                beforeSend: function() {
                    $btn.prop('disabled', true).text('Saving...');
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess(response.data.message);

                        // Replace button with completed badge
                        var badge = '<div class="sffc-lesson-completed-badge">' +
                                    '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                                    '<polyline points="20 6 9 17 4 12"></polyline>' +
                                    '</svg>' +
                                    '<span>Lesson Completed</span>' +
                                    '</div>';

                        $('.sffc-lesson-actions').html(badge);

                        // Update progress circle in sidebar
                        var progress = response.data.progress_percentage || 0;
                        var circumference = 163;
                        var dashArray = (circumference * progress / 100) + ' ' + circumference;

                        $('.sffc-progress-circle circle:last-child').attr('stroke-dasharray', dashArray);
                        $('.sffc-progress-circle text').text(Math.round(progress) + '%');

                        // Mark lesson in sidebar as complete
                        var lessonKey = 'module_' + moduleId + '_lesson_' + lessonId;
                        $('.sffc-lesson-nav-item').each(function() {
                            var $link = $(this).find('a');
                            if ($link.attr('href') && $link.attr('href').indexOf('module/' + moduleId + '/lesson/' + lessonId) !== -1) {
                                $(this).addClass('completed');
                            }
                        });
                    } else {
                        self.showError(response.data.message || 'Failed to save progress');
                        $btn.prop('disabled', false).html('<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> Mark as Complete');
                    }
                },
                error: function() {
                    self.showError('Network error. Please try again.');
                    $btn.prop('disabled', false).html('<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> Mark as Complete');
                }
            });
        },

        loadUserProgress: function() {
            var self = this;

            $.ajax({
                url: sffcCRMLinkedIn.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_get_user_learning_progress',
                    nonce: sffcCRMLinkedIn.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.renderUserProgress(response.data);
                    }
                }
            });
        },

        renderUserProgress: function(data) {
            // Update stats on My Learning dashboard
            $('#enrolled-count').text(data.enrolled || 0);
            $('#hours-learned').text(data.hours || 0);
            $('#streak-count').text(data.streak || 0);
            $('#certs-earned').text(data.certificates || 0);

            // Update sidebar learning stats
            $('[data-learning-stat="enrolled"]').text(data.enrolled || 0);
            $('[data-learning-stat="hours"]').text(data.hours || 0);
            $('[data-learning-stat="streak"]').text(data.streak || 0);
            $('[data-learning-stat="certificates"]').text(data.certificates || 0);

            // Render in-progress courses
            var $inProgress = $('#in-progress-courses');
            if (data.in_progress && data.in_progress.length > 0) {
                var html = '';
                data.in_progress.forEach(function(course) {
                    html += `
                        <div class="sffc-crm-progress-course-card">
                            <div class="sffc-crm-progress-course-info">
                                <h4 class="sffc-crm-progress-course-title">${course.title}</h4>
                                <p class="sffc-crm-progress-course-meta">Last: ${course.last_lesson}</p>
                                <div class="sffc-crm-progress-bar">
                                    <div class="sffc-crm-progress-fill" style="width: ${course.progress}%"></div>
                                </div>
                            </div>
                            <button class="sffc-crm-btn sffc-crm-btn-primary sffc-crm-continue-course" data-course-id="${course.id}">Continue</button>
                        </div>
                    `;
                });
                $inProgress.html(html);
            }

            // Render completed courses
            var $completed = $('#completed-courses');
            if (data.completed && data.completed.length > 0) {
                var html = '';
                data.completed.forEach(function(course) {
                    html += `
                        <div class="sffc-crm-progress-course-card">
                            <div class="sffc-crm-progress-course-info">
                                <h4 class="sffc-crm-progress-course-title">${course.title}</h4>
                                <p class="sffc-crm-progress-course-meta">Completed: ${course.completed_date}</p>
                            </div>
                            <button class="sffc-crm-btn sffc-crm-btn-secondary" data-course-id="${course.id}">Review</button>
                        </div>
                    `;
                });
                $completed.html(html);
            }
        },

        loadInterviewHistory: function() {
            var self = this;

            $.ajax({
                url: sffcCRMLinkedIn.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_get_interview_history',
                    nonce: sffcCRMLinkedIn.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.renderInterviewHistory(response.data);
                    }
                }
            });
        },

        renderInterviewHistory: function(interviews) {
            var $list = $('#interview-history-list');

            if (!interviews || interviews.length === 0) {
                return;
            }

            var html = '';
            interviews.forEach(function(interview) {
                html += `
                    <div class="sffc-crm-interview-history-item">
                        <h4>${interview.type} Interview</h4>
                        <p>Score: ${interview.score}/100 • ${interview.date}</p>
                    </div>
                `;
            });

            $list.html(html);
        },

        loadCertificates: function() {
            var self = this;

            $.ajax({
                url: sffcCRMLinkedIn.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_get_user_certificates',
                    nonce: sffcCRMLinkedIn.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.renderCertificates(response.data);
                    }
                }
            });
        },

        renderCertificates: function(certificates) {
            var $grid = $('#certificates-grid');

            if (!certificates || certificates.length === 0) {
                return;
            }

            var html = '';
            certificates.forEach(function(cert) {
                html += `
                    <div class="sffc-crm-cert-card">
                        <div class="sffc-crm-cert-preview">
                            <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                        </div>
                        <h4 class="sffc-crm-cert-title">${cert.course_title}</h4>
                        <p class="sffc-crm-cert-date">Issued: ${cert.issued_date}</p>
                        <div class="sffc-crm-cert-actions">
                            <a href="${cert.pdf_url}" class="sffc-crm-btn sffc-crm-btn-secondary" download>Download</a>
                            <button class="sffc-crm-btn sffc-crm-btn-primary" onclick="window.open('https://linkedin.com/share')">Share</button>
                        </div>
                    </div>
                `;
            });

            $grid.html(html);
        },

        startMockInterview: function(type) {
            console.log('Starting mock interview:', type);
            // TODO: Implement in Phase 4
            this.showSuccess('Mock interview simulator coming in Phase 4!');
        },

        showSuccess: function(message) {
            // Reuse existing toast/notification system
            if (typeof showToast === 'function') {
                showToast(message, 'success');
            } else {
                alert(message);
            }
        },

        showError: function(message) {
            if (typeof showToast === 'function') {
                showToast(message, 'error');
            } else {
                alert(message);
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        // Only initialize if we're on the learning tab
        if ($('.sffc-crm-linkedin-panel[data-panel="learning"]').length > 0) {
            LearningPlatform.init();
        }
    });

})(jQuery);
