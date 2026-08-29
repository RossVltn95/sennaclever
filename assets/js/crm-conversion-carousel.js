/**
 * Conversion Carousel JavaScript
 * Handles all interactions for the conversion flow
 */

(function($) {
    'use strict';

    // Global state
    const ConversionFlow = {
        selectedRecruiter: null,
        quizData: {
            currentLevel: '',
            targetRole: '',
            location: ''
        },
        currentStep: 1,
        allRecruiters: []
    };

    $(document).ready(function() {
        initConversionCarousel();
        initCountdownTimer();
    });

    function initConversionCarousel() {
        // Store all recruiters from the grid
        $('.sffc-conv-card').each(function() {
            const data = $(this).find('.sffc-conv-card-btn').data('recruiter-data');
            if (data) {
                ConversionFlow.allRecruiters.push(data);
            }
        });

        // Click on recruiter card
        $(document).on('click', '.sffc-conv-card-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const recruiterData = $(this).data('recruiter-data');
            ConversionFlow.selectedRecruiter = recruiterData;

            openLightbox(recruiterData);
        });

        // Lightbox CTA - open quiz
        $(document).on('click', '#sffcLightboxCTA', function() {
            closeLightbox();
            openQuiz();
        });

        // Quiz option selection
        $(document).on('click', '.sffc-conv-quiz-option', function() {
            const step = $(this).closest('.sffc-conv-quiz-step').data('quiz-step');
            const value = $(this).data('value');

            // Handle "other" input
            if (value === 'other') {
                const inputValue = $(this).find('.sffc-conv-quiz-other-input').val().trim();
                if (!inputValue) {
                    $(this).find('.sffc-conv-quiz-other-input').focus();
                    return;
                }
                saveQuizAnswer(step, inputValue);
            } else {
                saveQuizAnswer(step, value);
            }

            nextQuizStep();
        });

        // Email unlock button
        $(document).on('click', '#sffcMatchesUnlock', function() {
            const email = $('#sffcMatchesEmail').val().trim();
            const firstName = extractFirstName(email);

            if (!validateEmail(email)) {
                alert('Please enter a valid email address');
                return;
            }

            captureLeadEmail(email, firstName);
        });

        // Modal close buttons
        $(document).on('click', '.sffc-conv-modal-close', function() {
            $(this).closest('.sffc-conv-modal').fadeOut(300);
        });

        // Click outside modal to close
        $(document).on('click', '.sffc-conv-modal-overlay', function() {
            $(this).closest('.sffc-conv-modal').fadeOut(300);
        });
    }

    function openLightbox(recruiterData) {
        const recruiterName = recruiterData.recruiter_name || 'this recruiter';
        const recruiterTitle = recruiterData.recruiter_title || '';
        const company = recruiterData.company || '';

        $('#sffcLightboxRecruiterName').text(recruiterName);
        $('#sffcLightboxRecruiterFull').text(recruiterName);
        $('#sffcLightboxRecruiterTitle').text(recruiterTitle);
        $('#sffcLightboxCompany').text(company);

        // Set avatar
        const $avatar = $('#sffcLightboxAvatar');
        if (recruiterData.recruiter_photo) {
            $avatar.html('<img src="' + recruiterData.recruiter_photo + '" alt="">');
        } else {
            const initial = recruiterName ? recruiterName.charAt(0).toUpperCase() : 'R';
            $avatar.html('<span class="sffc-conv-card-initial">' + initial + '</span>');
        }

        $('#sffcConvLightbox').fadeIn(300);
    }

    function closeLightbox() {
        $('#sffcConvLightbox').fadeOut(300);
    }

    function openQuiz() {
        ConversionFlow.currentStep = 1;
        showQuizStep(1);
        updateQuizProgress();
        $('#sffcConvQuiz').fadeIn(300);
    }

    function showQuizStep(step) {
        $('.sffc-conv-quiz-step').hide();
        $('[data-quiz-step="' + step + '"]').show();
    }

    function updateQuizProgress() {
        const progress = (ConversionFlow.currentStep / 3) * 100;
        $('#sffcQuizProgressBar').css('width', progress + '%');
        $('#sffcQuizStepIndicator').text('Step ' + ConversionFlow.currentStep + ' of 3');
    }

    function saveQuizAnswer(step, value) {
        if (step === 1) {
            ConversionFlow.quizData.currentLevel = value;
        } else if (step === 2) {
            ConversionFlow.quizData.targetRole = value;
        } else if (step === 3) {
            ConversionFlow.quizData.location = value;
        }
    }

    function nextQuizStep() {
        ConversionFlow.currentStep++;

        if (ConversionFlow.currentStep <= 3) {
            showQuizStep(ConversionFlow.currentStep);
            updateQuizProgress();
        } else {
            // Quiz complete - show matches
            $('#sffcConvQuiz').fadeOut(300, function() {
                showMatchesPreview();
            });
        }
    }

    function showMatchesPreview() {
        const firstName = 'there'; // Will be replaced after email capture
        const matchCount = ConversionFlow.allRecruiters.length;
        const role = formatRoleForDisplay(ConversionFlow.quizData.targetRole);
        const location = formatLocationForDisplay(ConversionFlow.quizData.location);

        // Update personalization
        $('#sffcMatchesFirstName').text(firstName);
        $('#sffcMatchesCount').text(matchCount);
        $('#sffcMatchesRole').text(role);
        $('#sffcMatchesLocation').text(location);
        $('#sffcMatchesCTACount').text(matchCount);

        // Update kit info
        if (ConversionFlow.selectedRecruiter) {
            $('#sffcKitRecruiterName').text(ConversionFlow.selectedRecruiter.recruiter_name || 'the recruiter');
            $('#sffcKitCompany').text(ConversionFlow.selectedRecruiter.company || 'their');
        }

        // Populate matches grid
        populateMatchesGrid();

        $('#sffcConvMatches').fadeIn(300);
    }

    function populateMatchesGrid() {
        const $grid = $('#sffcMatchesGrid');
        $grid.empty();

        ConversionFlow.allRecruiters.forEach((recruiter, index) => {
            const isLocked = index >= 1; // Only first one unlocked
            const lockClass = isLocked ? 'sffc-conv-match-card--locked' : '';

            const initial = recruiter.recruiter_name ? recruiter.recruiter_name.charAt(0).toUpperCase() : 'R';
            const avatar = recruiter.recruiter_photo
                ? '<img src="' + recruiter.recruiter_photo + '" alt="">'
                : '<span class="sffc-conv-card-initial">' + initial + '</span>';

            const card = $('<div class="sffc-conv-match-card ' + lockClass + '">' +
                '<div class="sffc-conv-card-avatar">' + avatar + '</div>' +
                '<h4>' + (recruiter.job_title || 'Role') + '</h4>' +
                '<p>' + (recruiter.company || 'Company') + '</p>' +
                '<p>' + (recruiter.location || 'Location') + '</p>' +
                '</div>');

            $grid.append(card);
        });
    }

    function captureLeadEmail(email, firstName) {
        const $btn = $('#sffcMatchesUnlock');
        $btn.prop('disabled', true).text('Processing...');

        $.ajax({
            url: sffcConversionData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sffc_capture_conversion_lead',
                nonce: sffcConversionData.nonce,
                email: email,
                first_name: firstName,
                current_level: ConversionFlow.quizData.currentLevel,
                target_role: ConversionFlow.quizData.targetRole,
                location: ConversionFlow.quizData.location,
                recruiter_post_id: ConversionFlow.selectedRecruiter ? ConversionFlow.selectedRecruiter.id : 0
            },
            success: function(response) {
                if (response.success) {
                    // Unlock more matches
                    unlockMatches(firstName);

                    // Show pricing after brief delay
                    setTimeout(function() {
                        showPricingPage(firstName);
                    }, 2000);
                } else {
                    alert(response.data.message || 'An error occurred. Please try again.');
                    $btn.prop('disabled', false).text('See My ' + ConversionFlow.allRecruiters.length + ' Recruiter Matches');
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
                $btn.prop('disabled', false).text('See My ' + ConversionFlow.allRecruiters.length + ' Recruiter Matches');
            }
        });
    }

    function unlockMatches(firstName) {
        // Update first name
        $('#sffcMatchesFirstName').text(', ' + firstName);

        // Unlock second match
        $('#sffcMatchesGrid .sffc-conv-match-card').eq(1).removeClass('sffc-conv-match-card--locked');

        // Update CTA
        $('#sffcMatchesUnlock').text('Unlock All ' + ConversionFlow.allRecruiters.length + ' Matches + Application Kit');
    }

    function showPricingPage(firstName) {
        const matchCount = ConversionFlow.allRecruiters.length;
        const role = formatRoleForDisplay(ConversionFlow.quizData.targetRole);
        const location = formatLocationForDisplay(ConversionFlow.quizData.location);

        // Update personalization
        $('#sffcPricingFirstName').text(firstName);
        $('#sffcPricingRole').text(role);
        $('#sffcPricingLocation').text(location);
        $('#sffcPricingMatchCount').text(matchCount);
        $('#sffcValueMatchCount').text(matchCount);

        // Hide matches modal
        $('#sffcConvMatches').fadeOut(300);

        // Show pricing
        $('#sffcConvPricing').fadeIn(300);

        // Scroll to top
        $('html, body').animate({ scrollTop: 0 }, 500);
    }

    function initCountdownTimer() {
        const endTime = getCountdownEndTime();

        function updateTimer() {
            const now = new Date().getTime();
            const distance = endTime - now;

            if (distance < 0) {
                $('#sffcCountdownTimer').text('00:00:00');
                return;
            }

            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            const formatted =
                String(hours).padStart(2, '0') + ':' +
                String(minutes).padStart(2, '0') + ':' +
                String(seconds).padStart(2, '0');

            $('#sffcCountdownTimer').text(formatted);
        }

        updateTimer();
        setInterval(updateTimer, 1000);
    }

    function getCountdownEndTime() {
        // Check localStorage for existing countdown
        let endTime = localStorage.getItem('sffc_countdown_end');

        if (!endTime) {
            // Set 24 hours from now
            endTime = new Date().getTime() + (24 * 60 * 60 * 1000);
            localStorage.setItem('sffc_countdown_end', endTime);
        }

        return parseInt(endTime);
    }

    function validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    function extractFirstName(email) {
        // Extract name from email (before @)
        const parts = email.split('@')[0].split(/[._-]/);
        if (parts.length > 0) {
            const name = parts[0];
            return name.charAt(0).toUpperCase() + name.slice(1).toLowerCase();
        }
        return 'there';
    }

    function formatRoleForDisplay(role) {
        if (!role) return 'your target';

        // Convert kebab-case to Title Case
        return role.split('-')
            .map(word => word.charAt(0).toUpperCase() + word.slice(1))
            .join(' ');
    }

    function formatLocationForDisplay(location) {
        if (!location) return 'your location';

        // Convert kebab-case to Title Case
        return location.split('-')
            .map(word => word.charAt(0).toUpperCase() + word.slice(1))
            .join(' ');
    }

})(jQuery);
