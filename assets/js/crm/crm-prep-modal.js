/**
 * CRM Prep Materials Modal - Professional Finance Design
 * Displays all job details with institutional design aesthetic
 */


(function($) {
    'use strict';

    function openLiveExpertMembershipModal() {
        if (window.sffcLiveExpert && typeof window.sffcLiveExpert.openMembershipModal === 'function') {
            window.sffcLiveExpert.openMembershipModal();
            return true;
        }

        var $toggle = $('.sffc-live-expert-toggle');
        if ($toggle.length) {
            $toggle.trigger('click');
            return true;
        }

        if (window.sffcCRMLinkedIn && window.sffcCRMLinkedIn.membershipUrl) {
            window.open(window.sffcCRMLinkedIn.membershipUrl, '_blank', 'noopener');
            return true;
        }

        return false;
    }

    class SFFCPrepModal {
        constructor() {
            this.$modal = null;
            this.currentPostData = null;
            this.init();
        }

        init() {
            this.$modal = $('[data-prep-modal]');
            if (!this.$modal.length) {
                console.error('Prep modal not found');
                return;
            }

            this.bindEvents();
        }

        bindEvents() {
            const self = this;

            // Close button
            $(document).on('click', '[data-prep-action="close"]', function(e) {
                e.preventDefault();
                self.close();
            });

            // Overlay click
            $(document).on('click', '[data-prep-modal-close]', function(e) {
                if (e.target === this) {
                    self.close();
                }
            });

            // Escape key
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && self.$modal.attr('aria-hidden') === 'false') {
                    self.close();
                }
            });

            // File clicks
            $(document).on('click', '.sffc-crm-prep-file', function(e) {
                e.preventDefault();
                const url = $(this).attr('href');
                if (url && url !== '#') {
                    window.open(url, '_blank');
                }
            });

            // View More / Expand content
            $(document).on('click', '[data-content-expand]', function(e) {
                e.preventDefault();
                const $link = $(this);
                const $content = $link.prev('.sffc-crm-prep-content--expandable');
                const $text = $link.find('.sffc-crm-prep-view-more-text');
                const $icon = $link.find('svg');

                if ($content.hasClass('sffc-crm-prep-content--expanded')) {
                    $content.removeClass('sffc-crm-prep-content--expanded');
                    $text.text('View More');
                    $icon.css('transform', 'rotate(0deg)');
                } else {
                    $content.addClass('sffc-crm-prep-content--expanded');
                    $text.text('View Less');
                    $icon.css('transform', 'rotate(180deg)');
                }
            });

            // Request Prep Materials
            $(document).on('click', '[data-prep-request-materials]', function(e) {
                e.preventDefault();
                const $btn = $(this);
                const postId = $btn.attr('data-post-id');

                self.requestPrepMaterials(postId);
            });

            // Individual Material Request
            $(document).on('click', '.sffc-crm-prep-material-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const $btn = $(this);
                const $item = $btn.closest('.sffc-crm-prep-material-item');
                const materialType = $item.attr('data-material-type');
                const materialName = $item.find('[data-material-name]').text();
                const postId = self.currentPostData?.postId || '';

                if (!postId) {
                    alert('Post ID not found. Please try again.');
                    return;
                }

                self.requestSpecificMaterial(postId, materialType, materialName, $btn, $item);
            });
        }

        /**
         * Open modal and populate all data
         */
        open(postData) {
            this.currentPostData = postData;

            // Set post ID for request button
            $('[data-prep-request-materials]').attr('data-post-id', postData.postId || '');

            // Update material items - store company name but don't show in title
            const companyName = postData.company || '';
            $('.sffc-crm-prep-material-item').each(function() {
                const $item = $(this);
                $item.attr('data-company-name', companyName);

                // Reset requested state
                $item.removeClass('sffc-crm-prep-material-item--requested');
                $item.find('.sffc-crm-prep-material-btn').prop('disabled', false);
            });

            // Populate all sections
            this.populateHeader(postData);
            this.populateRecruiterDetails(postData);
            this.populateTeamContacts(postData);
            this.populateProperties(postData);
            this.populateApplicationProcess(postData);
            this.populateKnockoutQuestions(postData);
            this.populateInterviewQuestions(postData);
            this.populateCoverLetter(postData);
            this.populatePrepMaterials(postData);
            this.populateDescription(postData);

            // Check and show/hide View More links
            this.checkContentOverflow();

            // Show modal
            this.$modal.attr('aria-hidden', 'false');
            $('body').css('overflow', 'hidden');
        }

        /**
         * Close modal
         */
        close() {
            this.$modal.attr('aria-hidden', 'true');
            $('body').css('overflow', '');
            this.currentPostData = null;
        }

        /**
         * Populate page header with company logo
         */
        populateHeader(data) {
            $('[data-prep-role-title]').text(data.roleTitle || '');
            $('[data-prep-company-name]').text(data.company || '');
            $('[data-prep-location]').text(data.location || '');

            // Company logo
            const $logo = $('[data-prep-company-logo]');
            const $initial = $('[data-prep-company-initial]');

            if (data.companyLogo) {
                $logo.html('<img src="' + data.companyLogo + '" alt="' + (data.company || '') + '">');
            } else {
                const initial = data.companyInitial || (data.company ? data.company.charAt(0).toUpperCase() : '?');
                $logo.html('<span>' + initial + '</span>');
            }

            // Header badges - Closing Date and Duration
            const closingDate = this.formatDate(data.closingDate);
            if (closingDate && closingDate !== '—') {
                $('[data-prep-closing-date-badge]').text(closingDate);
                $('[data-prep-closing-badge]').show();
            } else {
                $('[data-prep-closing-badge]').hide();
            }

            if (data.duration) {
                $('[data-prep-duration-badge-text]').text(data.duration);
                $('[data-prep-duration-badge]').show();
            } else {
                $('[data-prep-duration-badge]').hide();
            }
        }

        /**
         * Populate properties section
         */
        populateProperties(data) {
            $('[data-prep-sector]').text(data.sector || '—');
            $('[data-prep-seniority]').text(data.seniority || '—');

            // Format dates
            $('[data-prep-opening-date]').text(this.formatDate(data.openingDate) || '—');
            $('[data-prep-closing-date]').text(this.formatDate(data.closingDate) || '—');
            $('[data-prep-starting-date]').text(this.formatDate(data.startingDate) || '—');
            $('[data-prep-duration]').text(data.duration || '—');
        }

        /**
         * Populate application process section
         */
        populateApplicationProcess(data) {
            const $section = $('[data-prep-application-process-section]');
            const $container = $('[data-prep-application-process]');

            let process = [];
            if (data.applicationProcess) {
                try {
                    process = typeof data.applicationProcess === 'string' ?
                        JSON.parse(data.applicationProcess) : data.applicationProcess;
                } catch (e) {
                    console.error('Failed to parse application process:', e);
                }
            }

            if (Array.isArray(process) && process.length > 0) {
                const processLabels = {
                    'application': 'Application',
                    'video_interview': 'Video Interview',
                    'phone_screening': 'Phone Screening',
                    'technical_tests': 'Technical Tests',
                    'hirevue_test': 'HireVue Test',
                    'psychometric_testing': 'Psychometric Testing',
                    'cover_letter': 'Cover Letter',
                    'cv': 'CV',
                    'assessment_center': 'Assessment Center',
                    'interview_rounds': 'Interview Rounds'
                };

                const processIcons = {
                    'application': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
                    'video_interview': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>',
                    'phone_screening': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>',
                    'technical_tests': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>',
                    'hirevue_test': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="2.18" ry="2.18"/><line x1="7" y1="2" x2="7" y2="22"/><line x1="17" y1="2" x2="17" y2="22"/><line x1="2" y1="12" x2="22" y2="12"/><line x1="2" y1="7" x2="7" y2="7"/><line x1="2" y1="17" x2="7" y2="17"/><line x1="17" y1="17" x2="22" y2="17"/><line x1="17" y1="7" x2="22" y2="7"/></svg>',
                    'psychometric_testing': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
                    'cover_letter': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>',
                    'cv': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>',
                    'assessment_center': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
                    'interview_rounds': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>'
                };

                $container.empty();
                process.forEach(function(item) {
                    const label = processLabels[item] || item;
                    const icon = processIcons[item] || '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="9 11 12 14 15 10"/></svg>';
                    $('<div>', {
                        class: 'sffc-crm-prep-checkbox',
                        html: '<span class="sffc-crm-prep-checkbox-icon">' + icon + '</span>' +
                              '<span class="sffc-crm-prep-checkbox-label">' + label + '</span>'
                    }).appendTo($container);
                });

                $section.show();
            } else {
                $section.hide();
            }
        }

        /**
         * Populate knockout questions section
         */
        populateKnockoutQuestions(data) {
            const $section = $('[data-prep-knockout-section]');
            const $container = $('[data-prep-knockout-questions]');

            let questions = [];
            if (data.knockoutQuestions) {
                try {
                    questions = typeof data.knockoutQuestions === 'string' ?
                        JSON.parse(data.knockoutQuestions) : data.knockoutQuestions;
                } catch (e) {
                    console.error('Failed to parse knockout questions:', e);
                }
            }

            if (Array.isArray(questions) && questions.length > 0) {
                $container.empty();
                questions.forEach(function(q, index) {
                    const prompt = q.prompt || q.question || '';
                    const idealAnswer = q.ideal_answer || q.desired_response || '';

                    const $qBlock = $('<div>', {
                        class: 'sffc-crm-prep-question-block'
                    });

                    $('<div>', {
                        class: 'sffc-crm-prep-question-number',
                        text: (index + 1) + '.'
                    }).appendTo($qBlock);

                    $('<div>', {
                        class: 'sffc-crm-prep-question-prompt',
                        text: prompt
                    }).appendTo($qBlock);

                    if (idealAnswer) {
                        $('<div>', {
                            class: 'sffc-crm-prep-question-ideal',
                            html: '<strong>Ideal Answer:</strong> ' + idealAnswer
                        }).appendTo($qBlock);
                    }

                    $qBlock.appendTo($container);
                });

                $section.show();
            } else {
                $section.hide();
            }
        }

        /**
         * Populate interview questions HTML section
         */
        populateInterviewQuestions(data) {
            const $section = $('[data-prep-interview-section]');
            const $container = $('[data-prep-interview-content]');

            if (data.interviewQuestionsHtml) {
                $container.html(data.interviewQuestionsHtml);
                $section.show();
            } else {
                $section.hide();
            }
        }

        /**
         * Populate cover letter HTML section
         */
        populateCoverLetter(data) {
            const $section = $('[data-prep-cover-letter-section]');
            const $container = $('[data-prep-cover-letter-content]');

            if (data.coverLetterHtml) {
                $container.html(data.coverLetterHtml);
                $section.show();
            } else {
                $section.hide();
            }
        }

        /**
         * Populate recruiter details
         */
        populateRecruiterDetails(data) {
            $('[data-prep-recruiter-name]').text(data.recruiterName || '');
            $('[data-prep-recruiter-title]').text(data.recruiterTitle || '');

            // Avatar
            const $avatar = $('[data-prep-recruiter-avatar]');

            if (data.recruiterAvatar) {
                $avatar.html('<img src="' + data.recruiterAvatar + '" alt="' + (data.recruiterName || '') + '">');
            } else {
                const initial = data.recruiterInitial || (data.recruiterName ? data.recruiterName.charAt(0).toUpperCase() : '?');
                $avatar.html('<span>' + initial + '</span>');
            }

            // Email link
            if (data.recruiterEmail) {
                $('[data-prep-recruiter-email]').text(data.recruiterEmail);
                $('[data-prep-recruiter-email-link]')
                    .attr('href', 'mailto:' + data.recruiterEmail)
                    .show();
            } else {
                $('[data-prep-recruiter-email-link]').hide();
            }

            // LinkedIn link
            if (data.recruiterLinkedin) {
                $('[data-prep-recruiter-linkedin-link]')
                    .attr('href', data.recruiterLinkedin)
                    .show();
            } else {
                $('[data-prep-recruiter-linkedin-link]').hide();
            }
        }

        /**
         * Populate team contacts section
         */
        populateTeamContacts(data) {
            const $section = $('[data-prep-team-contacts-section]');
            const $container = $('[data-prep-team-contacts]');

            let contacts = [];
            if (data.teamContacts) {
                try {
                    contacts = typeof data.teamContacts === 'string' ?
                        JSON.parse(data.teamContacts) : data.teamContacts;
                } catch (e) {
                    console.error('Failed to parse team contacts:', e);
                }
            }

            if (Array.isArray(contacts) && contacts.length > 0) {
                $container.empty();
                contacts.forEach(function(contact) {
                    if (!contact.name) return; // Skip empty contacts

                    const $card = $('<div>', {
                        class: 'sffc-crm-prep-team-card'
                    });

                    // Avatar
                    const initial = contact.name ? contact.name.charAt(0).toUpperCase() : '?';
                    $('<div>', {
                        class: 'sffc-crm-prep-team-avatar',
                        html: '<span>' + initial + '</span>'
                    }).appendTo($card);

                    // Info
                    const $info = $('<div>', {
                        class: 'sffc-crm-prep-team-info'
                    }).appendTo($card);

                    $('<div>', {
                        class: 'sffc-crm-prep-team-name',
                        text: contact.name || ''
                    }).appendTo($info);

                    if (contact.title) {
                        $('<div>', {
                            class: 'sffc-crm-prep-team-title',
                            text: contact.title
                        }).appendTo($info);
                    }

                    // Links
                    const $links = $('<div>', {
                        class: 'sffc-crm-prep-team-links'
                    }).appendTo($info);

                    if (contact.email) {
                        $('<a>', {
                            href: 'mailto:' + contact.email,
                            text: contact.email,
                            class: 'sffc-crm-prep-team-link'
                        }).appendTo($links);
                    }

                    if (contact.linkedin) {
                        $('<a>', {
                            href: contact.linkedin,
                            text: 'LinkedIn',
                            class: 'sffc-crm-prep-team-link',
                            target: '_blank',
                            rel: 'noopener'
                        }).appendTo($links);
                    }

                    $card.appendTo($container);
                });

                $section.show();
            } else {
                $section.hide();
            }
        }

        /**
         * Populate prep materials files section
         */
        populatePrepMaterials(data) {
            const $section = $('[data-prep-materials-section]');
            let hasFiles = false;

            // Interview Questions
            if (data.interviewQuestionsUrl) {
                $('[data-prep-interview-file]')
                    .attr('href', data.interviewQuestionsUrl)
                    .show();
                hasFiles = true;
            } else {
                $('[data-prep-interview-file]').hide();
            }

            // CV Template
            if (data.cvTemplateUrl) {
                $('[data-prep-cv-file]')
                    .attr('href', data.cvTemplateUrl)
                    .show();
                hasFiles = true;
            } else {
                $('[data-prep-cv-file]').hide();
            }

            // Cover Letter
            if (data.coverLetterUrl) {
                $('[data-prep-cover-file]')
                    .attr('href', data.coverLetterUrl)
                    .show();
                hasFiles = true;
            } else {
                $('[data-prep-cover-file]').hide();
            }

            // Case Study
            if (data.caseStudyUrl) {
                $('[data-prep-case-file]')
                    .attr('href', data.caseStudyUrl)
                    .show();
                hasFiles = true;
            } else {
                $('[data-prep-case-file]').hide();
            }

            if (hasFiles) {
                $section.show();
            } else {
                $section.hide();
            }
        }

        /**
         * Populate job description section
         */
        populateDescription(data) {
            const $section = $('[data-prep-description-section]');
            const $container = $('[data-prep-snippet]');

            if (data.contentSnippet) {
                $container.text(data.contentSnippet);
                $section.show();
            } else {
                $section.hide();
            }
        }

        /**
         * Format date for display
         */
        formatDate(dateString) {
            if (!dateString) return '';

            try {
                const date = new Date(dateString);
                const options = { year: 'numeric', month: 'long', day: 'numeric' };
                return date.toLocaleDateString('en-US', options);
            } catch (e) {
                return dateString;
            }
        }

        /**
         * Check if content overflows and show/hide View More link
         */
        checkContentOverflow() {
            const self = this;

            // Use requestAnimationFrame to ensure DOM is rendered
            requestAnimationFrame(() => {
                $('.sffc-crm-prep-content--expandable').each(function() {
                    const $content = $(this);
                    const $viewMore = $content.next('[data-content-expand]');

                    if ($viewMore.length) {
                        // Check if content height exceeds max-height (400px)
                        if (this.scrollHeight > 400) {
                            $viewMore.css('display', 'inline-flex');
                        } else {
                            $viewMore.hide();
                        }
                    }
                });
            });
        }

        /**
         * Request prep materials - check auth and membership
         */
        requestPrepMaterials(postId) {
            const self = this;

            // Check if user is logged in
            if (typeof window.sffcUserData === 'undefined' || !window.sffcUserData.isLoggedIn) {
                // Show login modal
                if (window.SFFCAuthModal && typeof window.SFFCAuthModal.openLogin === 'function') {
                    window.SFFCAuthModal.openLogin();
                } else {
                    alert('Please log in to request prep materials.');
                }
                return;
            }

            // Check if user has membership
            if (!window.sffcUserData.hasMembership) {
                if (!openLiveExpertMembershipModal()) {
                    alert('Please upgrade to a premium membership to access prep materials.');
                }
                return;
            }

            // User is logged in and has membership, send request
            self.sendPrepMaterialRequest(postId);
        }

        /**
         * Send prep material request email to admin
         */
        sendPrepMaterialRequest(postId) {
            const self = this;
            const $btn = $('[data-prep-request-materials]');
            const originalText = $btn.find('span').text();

            // Show loading state
            $btn.prop('disabled', true).find('span').text('Sending...');

            $.ajax({
                url: window.sffc_ajax?.ajax_url || '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    action: 'sffc_request_prep_materials',
                    post_id: postId,
                    nonce: window.sffc_ajax?.nonce || ''
                },
                success: function(response) {
                    if (response.success) {
                        $btn.find('span').text('Request Sent!');
                        setTimeout(function() {
                            $btn.prop('disabled', false).find('span').text(originalText);
                        }, 3000);
                    } else {
                        alert(response.data?.message || 'Failed to send request. Please try again.');
                        $btn.prop('disabled', false).find('span').text(originalText);
                    }
                },
                error: function() {
                    alert('Failed to send request. Please try again.');
                    $btn.prop('disabled', false).find('span').text(originalText);
                }
            });
        }

        /**
         * Request specific prep material
         */
        requestSpecificMaterial(postId, materialType, materialName, $btn, $item) {
            const self = this;

            // Check if user is logged in
            if (typeof window.sffcUserData === 'undefined' || !window.sffcUserData.isLoggedIn) {
                // Show login modal
                if (window.SFFCAuthModal && typeof window.SFFCAuthModal.openLogin === 'function') {
                    window.SFFCAuthModal.openLogin();
                } else {
                    alert('Please log in to request prep materials.');
                }
                return;
            }

            // Check if user has membership
            if (!window.sffcUserData.hasMembership) {
                if (!openLiveExpertMembershipModal()) {
                    alert('Please upgrade to a premium membership to access prep materials.');
                }
                return;
            }

            // User is logged in and has membership, send request
            self.sendSpecificMaterialRequest(postId, materialType, materialName, $btn, $item);
        }

        /**
         * Send specific material request to admin
         */
        sendSpecificMaterialRequest(postId, materialType, materialName, $btn, $item) {
            const self = this;

            // Show loading state
            $btn.prop('disabled', true);
            const originalIcon = $btn.html();
            $btn.html('<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" style="animation: spin 1s linear infinite;"><circle cx="12" cy="12" r="10" stroke-opacity="0.25"/><path d="M12 2a10 10 0 0 1 10 10" stroke-opacity="0.75"/></svg>');

            $.ajax({
                url: window.sffc_ajax?.ajax_url || '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    action: 'sffc_request_specific_prep_material',
                    post_id: postId,
                    material_type: materialType,
                    material_name: materialName,
                    nonce: window.sffc_ajax?.nonce || ''
                },
                success: function(response) {
                    if (response.success) {
                        // Mark as requested
                        $item.addClass('sffc-crm-prep-material-item--requested');
                        $btn.html('<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><polyline points="20 6 9 17 4 12"/></svg>');

                        // Show success message
                        alert('Request sent! You should receive the ' + materialName + ' within 24 hours via email.');
                    } else {
                        alert(response.data?.message || 'Failed to send request. Please try again.');
                        $btn.html(originalIcon);
                        $btn.prop('disabled', false);
                    }
                },
                error: function() {
                    alert('Failed to send request. Please try again.');
                    $btn.html(originalIcon);
                    $btn.prop('disabled', false);
                }
            });
        }
    }

    // Initialize and expose globally
    $(document).ready(function() {
        window.SFFCPrepModal = new SFFCPrepModal();
    });

})(jQuery);
