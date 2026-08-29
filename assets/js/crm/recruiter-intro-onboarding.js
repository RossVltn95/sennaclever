(function () {
    'use strict';

    var config = window.sffcRecruiterIntroOnboarding || {};
    var root = document.querySelector('.sffc-recruiter-intro-onboarding');

    if (!root) {
        return;
    }

    // Load prefill data from cookies only (client-side, never from server)
    function getCookiePrefill() {
        var cookieData = {};
        try {
            var scopeValue = document.cookie.split('; ').find(function(row) {
                return row.startsWith('sffc_signup_scope=');
            });
            var ownerValue = document.cookie.split('; ').find(function(row) {
                return row.startsWith('sffc_signup_owner=');
            });
            var scope = scopeValue ? decodeURIComponent(scopeValue.split('=')[1]) : '';
            var owner = ownerValue ? decodeURIComponent(ownerValue.split('=')[1]) : '';
            if (!scope || !owner || scope !== owner) {
                return {};
            }
            var cookieValue = document.cookie.split('; ').find(function(row) {
                return row.startsWith('sffc_recruiter_intro_onboarding=') || row.startsWith('sffc_contact_data=');
            });
            if (cookieValue) {
                cookieData = JSON.parse(decodeURIComponent(cookieValue.split('=')[1]));
            }
        } catch (e) {
            cookieData = {};
        }
        return cookieData;
    }

    var cookiePrefill = getCookiePrefill();

    var state = {
        step: 1,
        path: 'intro',
        checkoutBackStep: 4,
        postId: String(config.postId || root.getAttribute('data-post-id') || ''),
        firstName: cookiePrefill.first_name || '',
        lastName: cookiePrefill.last_name || '',
        email: cookiePrefill.email || '',
        fullName: cookiePrefill.full_name || '',
        cycle: String(config.defaultCycle || root.getAttribute('data-default-cycle') || 'monthly'),
        planSlug: String(config.defaultPlanSlug || ''),
        topMatchEmailScheduled: false,
        isGeneral: !!config.isGeneral || root.getAttribute('data-general-onboarding') === '1'
    };

    // Client-side prefill of form fields from cookies only
    (function prefillFormFields() {
        if (state.fullName) {
            var nameField = q('[name="full_name"]');
            if (nameField) nameField.value = state.fullName;
        }
        if (state.email) {
            var emailField = q('[name="email"]');
            if (emailField) emailField.value = state.email;
        }
    })();

    function q(selector, scope) {
        return (scope || root).querySelector(selector);
    }

    function qa(selector, scope) {
        return Array.prototype.slice.call((scope || root).querySelectorAll(selector));
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function showStep(step) {
        qa('.sffc-onboarding-step').forEach(function (el) {
            el.classList.toggle('is-active', String(el.getAttribute('data-step')) === String(step));
        });
        var pageHead = q('.page-head');
        if (pageHead) {
            pageHead.style.display = String(step) === '5' ? 'none' : '';
        }
        state.step = step;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function getPreviousStep() {
        if (state.step === 5) {
            return state.checkoutBackStep || 4;
        }
        return Math.max(1, state.step - 1);
    }

    function setSelectedChoice(path) {
        state.path = path === 'email' ? 'email' : 'intro';
        qa('[data-choice-card]').forEach(function (card) {
            card.classList.toggle('selected', card.getAttribute('data-choice-card') === state.path);
        });
        var pathField = q('[name="selected_path"]');
        if (pathField) {
            pathField.value = state.path;
        }
    }

    function showMessage(message) {
        var el = q('[data-onboarding-message]');
        if (!el) {
            return;
        }
        if (!message) {
            el.textContent = '';
            el.classList.remove('is-visible');
            return;
        }
        el.textContent = message;
        el.classList.add('is-visible');
    }

    function parseName(fullName) {
        var trimmed = String(fullName || '').trim();
        if (!trimmed) {
            return { firstName: '', lastName: '' };
        }
        var parts = trimmed.split(/\s+/);
        return {
            firstName: parts.shift() || '',
            lastName: parts.join(' ')
        };
    }

    function formatFirstName(name) {
        var value = String(name || '').trim();
        if (!value) {
            return 'John';
        }
        return value.charAt(0).toUpperCase() + value.slice(1);
    }

    function updatePersonalization() {
        qa('[data-personalize-first-name]').forEach(function (el) {
            el.textContent = formatFirstName(state.firstName);
        });
        qa('[data-personalize-cv-name]').forEach(function (el) {
            el.textContent = formatFirstName(state.firstName);
        });
        // Update checkout benefit badge with personalized name
        updateCheckoutBenefitBadge();
    }

    function updateCheckoutBenefitBadge() {
        var firstName = formatFirstName(state.firstName);
        var activeShell = q('[data-plan-shell].is-active');
        if (activeShell) {
            var planName = activeShell.getAttribute('data-plan-name') || 'Member';
            var personalizedBenefit = firstName + "'s Plan - " + planName;
            qa('[data-checkout-plan-benefit]').forEach(function (el) {
                el.textContent = personalizedBenefit;
            });
        }
    }

    function updateContextCard() {
        qa('[data-context-copy]').forEach(function (el) {
            var isActive = el.getAttribute('data-context-copy') === state.path;
            el.hidden = !isActive;
        });
        qa('img[data-context-logo]').forEach(function (el) {
            var isActive = el.getAttribute('data-context-logo') === state.path;
            el.hidden = !isActive;
        });
    }

    function updateSelectedCount() {
        var seen = {};
        var count = 0;
        qa('[data-match-row]').forEach(function (row) {
            if (row.getAttribute('data-match-selected') === '0') {
                return;
            }
            var matchId = row.getAttribute('data-match-id') || '';
            if (matchId && seen[matchId]) {
                return;
            }
            if (matchId) {
                seen[matchId] = true;
            }
            count += 1;
        });
        qa('[data-selected-count]').forEach(function (el) {
            el.textContent = count === 1 ? '1 selected match' : count + ' selected matches';
        });
        qa('[data-apply-all-label]').forEach(function (el) {
            el.textContent = String(config.applyAllLabel || 'Apply All Matches');
        });
    }

    function syncQueueVisibility() {
        if (!queueList) {
            return;
        }
        qa('[data-queue-item]', queueList).forEach(function (item) {
            var matchId = item.getAttribute('data-match-id') || '';
            if (!matchId) {
                return;
            }
            var sourceRow = q('[data-match-row][data-match-id="' + matchId + '"][data-match-group="best"]');
            if (!sourceRow || sourceRow.getAttribute('data-match-selected') === '0') {
                item.remove();
                return;
            }
            item.classList.add('is-visible');
        });

        if (queueEmpty) {
            queueEmpty.hidden = qa('[data-queue-item]', queueList).length > 0;
        }
        updateQueueCount(qa('[data-queue-item]', queueList).length);
    }

    function fillMembershipForms() {
        if (!state.email && !state.firstName && !state.lastName) {
            return;
        }

        qa('input[name="user_email"], input[name="mepr_user_email"]').forEach(function (input) {
            input.value = state.email;
        });
        qa('input[name="user_first_name"], input[name="mepr_first_name"]').forEach(function (input) {
            input.value = state.firstName;
        });
        qa('input[name="user_last_name"], input[name="mepr_last_name"]').forEach(function (input) {
            input.value = state.lastName;
        });
        qa('input[name="user_login"], input[name="mepr_user_login"]').forEach(function (input) {
            if (!input.value) {
                input.value = state.email ? String(state.email).split('@')[0] : '';
            }
        });
    }

    function scheduleTopMatchEmail() {
        if (state.topMatchEmailScheduled || !state.email || !state.postId) {
            return;
        }

        state.topMatchEmailScheduled = true;
        var payload = new URLSearchParams({
            action: 'sffc_schedule_recruiter_intro_top_match_email',
            nonce: String(config.nonce || ''),
            email: state.email,
            first_name: state.firstName,
            post_id: state.postId
        });

        if (navigator.sendBeacon) {
            navigator.sendBeacon(String(config.ajaxUrl || ''), payload);
            return;
        }

        fetch(String(config.ajaxUrl || ''), {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: payload.toString(),
            keepalive: true
        }).catch(function () {
            state.topMatchEmailScheduled = false;
        });
    }

    function setCheckoutMode(mode) {
        qa('[data-checkout-mode]').forEach(function (shell) {
            shell.classList.toggle('is-active', shell.getAttribute('data-checkout-mode') === mode);
        });
    }

    function setSelectedPlan(planSlug) {
        if (!planSlug) {
            return;
        }
        state.planSlug = String(planSlug);
        var activeShell = null;

        qa('[data-plan-shell]').forEach(function (shell) {
            var isSelected = shell.getAttribute('data-plan-shell') === state.planSlug;
            var cycleMatches = shell.getAttribute('data-plan-cycle') === state.cycle;
            var shouldShow = isSelected && cycleMatches;
            shell.hidden = !shouldShow;
            shell.classList.toggle('is-active', shouldShow);
            if (shouldShow) {
                activeShell = shell;
            }
        });

        qa('[data-checkout-plan-option]').forEach(function (button) {
            var isActive = button.getAttribute('data-checkout-plan-option') === state.planSlug;
            button.classList.toggle('is-active', isActive);
        });

        if (!activeShell) {
            qa('[data-plan-shell]').some(function (shell) {
                if (shell.getAttribute('data-plan-cycle') === state.cycle) {
                    state.planSlug = shell.getAttribute('data-plan-shell') || state.planSlug;
                    shell.hidden = false;
                    shell.classList.add('is-active');
                    activeShell = shell;
                    return true;
                }
                return false;
            });

            qa('[data-checkout-plan-option]').forEach(function (button) {
                var isActive = button.getAttribute('data-checkout-plan-option') === state.planSlug;
                button.classList.toggle('is-active', isActive);
            });
        }

        if (activeShell) {
            var price = activeShell.getAttribute('data-plan-price') || '';
            var billing = activeShell.getAttribute('data-plan-billing') || '';
            var name = activeShell.getAttribute('data-plan-name') || '';
            var matches = parseInt(activeShell.getAttribute('data-plan-matches') || '0', 10);
            var benefit = activeShell.getAttribute('data-plan-benefit') || '';
            var copy = activeShell.getAttribute('data-plan-copy') || '';
            var featuresJson = activeShell.getAttribute('data-plan-features') || '[]';
            var features = [];
            try {
                features = JSON.parse(featuresJson);
            } catch (e) {
                features = [];
            }

            qa('[data-checkout-plan-price]').forEach(function (el) {
                el.textContent = price;
            });
            qa('[data-checkout-plan-cycle]').forEach(function (el) {
                el.textContent = billing;
            });
            qa('[data-checkout-plan-name]').forEach(function (el) {
                el.textContent = name;
            });
            qa('[data-checkout-plan-matches]').forEach(function (el) {
                el.textContent = matches > 0
                    ? (matches === 1 ? '1 mentorship session per week included' : matches + ' mentorship sessions per week included')
                    : '';
            });
            // Benefit badge updated by updateCheckoutBenefitBadge() function
            updateCheckoutBenefitBadge();
            qa('[data-checkout-plan-copy]').forEach(function (el) {
                el.textContent = copy;
            });

            // Update checkout benefits list with features from the selected plan
            qa('.checkout-benefits__list').forEach(function (list) {
                if (features.length > 0) {
                    var html = '';
                    features.forEach(function (feature) {
                        if (feature) {
                            html += '<li>✓ ' + escapeHtml(feature) + '</li>';
                        }
                    });
                    list.innerHTML = html;
                }
            });
        }
    }

    function setPlanCycle(cycle) {
        state.cycle = cycle === 'annual' ? 'annual' : 'monthly';

        qa('[data-carousel-cycle-toggle]').forEach(function (button) {
            var active = button.getAttribute('data-carousel-cycle-toggle') === state.cycle;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });

        qa('[data-checkout-plan-option]').forEach(function (button) {
            button.hidden = false;
        });

        setSelectedPlan(state.planSlug);
    }

    function saveLead() {
        var form = q('[data-onboarding-lead-form]');
        var nameField = q('input[name="full_name"]', form);
        var emailField = q('input[name="email"]', form);
        var fullName = String(nameField ? nameField.value : '').trim();
        var email = String(emailField ? emailField.value : '').trim();

        if (!fullName) {
            showMessage((config.strings && config.strings.nameRequired) || 'Please enter your full name.');
            return;
        }

        if (!email || email.indexOf('@') === -1) {
            showMessage((config.strings && config.strings.emailRequired) || 'Please enter a valid email address.');
            return;
        }

        showMessage('');
        var parsed = parseName(fullName);
        state.fullName = fullName;
        state.firstName = formatFirstName(parsed.firstName);
        state.lastName = parsed.lastName;
        state.email = email;
        updatePersonalization();

        fetch(String(config.ajaxUrl || ''), {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: new URLSearchParams({
                action: 'sffc_save_recruiter_intro_onboarding',
                nonce: String(config.nonce || ''),
                full_name: fullName,
                email: email,
                post_id: state.postId,
                selected_path: state.path
            })
        })
            .then(function (response) { return response.json(); })
            .then(function (payload) {
                if (!payload || !payload.success) {
                    throw new Error((payload && payload.data && payload.data.message) || ((config.strings && config.strings.saveError) || 'We could not save your details right now. Please try again.'));
                }

                if (payload.data) {
                    state.firstName = payload.data.first_name || state.firstName;
                    state.firstName = formatFirstName(state.firstName);
                    state.lastName = payload.data.last_name || state.lastName;
                    state.email = payload.data.email || state.email;
                }

                updatePersonalization();
                fillMembershipForms();
                scheduleTopMatchEmail();
                if (state.path === 'email' && !state.isGeneral) {
                    state.checkoutBackStep = 2;

                    // Auto-launch application link if available
                    var applyLink = String(config.primaryManualLink || '');
                    if (applyLink) {
                        window.open(applyLink, '_blank', 'noopener,noreferrer');
                    }

                    showStep(4);
                    startMatchingEngine();
                    return;
                }

                if (state.path === 'email' && state.isGeneral) {
                    state.checkoutBackStep = 2;

                    // For general onboarding, open support email
                    var supportEmail = String(config.supportEmail || 'mailto:support.team@joinsenna.com');
                    window.location.href = supportEmail + '?subject=Application%20Kit%20Request&body=Hi%20Senna%20Team%2C%0A%0AI%27d%20like%20to%20request%20a%20tailored%20CV%20and%20cover%20letter.%0A%0AName%3A%20' + encodeURIComponent(state.fullName) + '%0AEmail%3A%20' + encodeURIComponent(state.email);

                    showStep(4);
                    startMatchingEngine();
                    return;
                }

                showStep(3);
            })
            .catch(function (error) {
                showMessage(error.message || ((config.strings && config.strings.saveError) || 'We could not save your details right now. Please try again.'));
            });
    }

    qa('[data-choice-card]').forEach(function (card) {
        card.addEventListener('click', function (e) {
            var clickedAction = e.target.closest('.card-action');

            // Set the choice first
            setSelectedChoice(card.getAttribute('data-choice-card'));
            updateContextCard();

            // Auto-advance to step 2 if clicking the card-action button
            if (clickedAction) {
                showStep(2);
            }
        });
    });

    qa('[data-step-next]').forEach(function (button) {
        button.addEventListener('click', function () {
            showStep(parseInt(button.getAttribute('data-step-next'), 10) || 2);
        });
    });

    qa('[data-step-back]').forEach(function (button) {
        button.addEventListener('click', function () {
            showMessage('');
            showStep(parseInt(button.getAttribute('data-step-back'), 10) || 1);
        });
    });

    var checkoutBack = q('[data-step-back-checkout]');
    if (checkoutBack) {
        checkoutBack.addEventListener('click', function () {
            showMessage('');
            showStep(state.checkoutBackStep || 4);
        });
    }

    var leadSubmit = q('[data-step-submit]');
    if (leadSubmit) {
        leadSubmit.addEventListener('click', saveLead);
    }

    qa('[data-plan-choice]').forEach(function (plan) {
        plan.addEventListener('click', function () {
            var mode = plan.getAttribute('data-plan-choice') === 'email' ? 'email' : 'intro';
            state.path = mode;
            setCheckoutMode(mode);
            state.checkoutBackStep = 3;
            showStep(5);
        });
    });

    qa('[data-decision-next]').forEach(function (button) {
        button.addEventListener('click', function () {
            var nextMode = button.getAttribute('data-decision-next') === 'manual' ? 'email' : 'intro';
            state.path = nextMode;
            setCheckoutMode(nextMode);
            state.checkoutBackStep = 3;
            if (nextMode === 'intro') {
                var selectedPlan = button.getAttribute('data-decision-plan');
                var selectedCycle = button.getAttribute('data-decision-cycle');
                if (selectedCycle) {
                    state.cycle = selectedCycle;
                }
                if (selectedPlan) {
                    state.planSlug = selectedPlan;
                }
                setPlanCycle(state.cycle);
            }
            showStep(5);
        });
    });

    qa('[data-match-remove]').forEach(function (button) {
        button.addEventListener('click', function () {
            var row = button.closest('[data-match-row]');
            if (!row) {
                return;
            }
            var matchId = row.getAttribute('data-match-id') || '';
            qa('[data-match-row]').forEach(function (candidate) {
                if (!matchId || candidate.getAttribute('data-match-id') === matchId) {
                    candidate.setAttribute('data-match-selected', '0');
                    candidate.style.display = 'none';
                }
            });
            if (matchId && queueList) {
                qa('[data-queue-item]', queueList).forEach(function (item) {
                    if (item.getAttribute('data-match-id') === matchId) {
                        item.remove();
                    }
                });
            }
            syncQueueVisibility();
            updateSelectedCount();
        });
    });

    var submissionModal = q('[data-submission-modal]');
    qa('[data-open-submission-modal]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (!submissionModal) {
                return;
            }
            submissionModal.classList.add('is-visible');
        });
    });

    qa('[data-close-submission-modal]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (!submissionModal) {
                return;
            }
            submissionModal.classList.remove('is-visible');
        });
    });

    qa('[data-carousel-cycle-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            setPlanCycle(button.getAttribute('data-carousel-cycle-toggle'));
        });
    });

    qa('[data-checkout-plan-option]').forEach(function (button) {
        button.addEventListener('click', function () {
            var cycle = button.getAttribute('data-plan-cycle');
            if (cycle && cycle !== state.cycle) {
                state.cycle = cycle;
                qa('[data-carousel-cycle-toggle]').forEach(function (toggle) {
                    var active = toggle.getAttribute('data-carousel-cycle-toggle') === state.cycle;
                    toggle.classList.toggle('is-active', active);
                    toggle.setAttribute('aria-pressed', active ? 'true' : 'false');
                });
            }
            setSelectedPlan(button.getAttribute('data-checkout-plan-option'));
        });
    });

    var backBtn = q('[data-onboarding-back]');
    if (backBtn) {
        backBtn.addEventListener('click', function () {
            if (state.step > 1) {
                showStep(getPreviousStep());
                return;
            }
            if (window.history.length > 1) {
                window.history.back();
            }
        });
    }

    var form = q('[data-onboarding-lead-form]');
    if (form) {
        var initialName = q('input[name="full_name"]', form);
        var initialEmail = q('input[name="email"]', form);
        if (initialName && initialName.value) {
            var parsed = parseName(initialName.value);
            state.fullName = initialName.value;
            state.firstName = formatFirstName(parsed.firstName);
            state.lastName = parsed.lastName;
        }
        if (initialEmail && initialEmail.value) {
            state.email = initialEmail.value;
        }
    }

    updatePersonalization();
    fillMembershipForms();
    setSelectedChoice(state.path);
    updateContextCard();
    setPlanCycle(state.cycle);
    updateSelectedCount();

    var planModal = q('[data-plan-modal]');
    var benefitsModal = q('[data-benefits-modal]');
    var queueList = q('[data-queue-list]');
    var queueEmpty = q('[data-queue-empty]');
    var queueCountEls = qa('[data-queue-count]');

    function updateQueueCount(count) {
        queueCountEls.forEach(function (el) {
            el.textContent = String(count);
        });
    }

    function openModal(modal) {
        if (modal) {
            modal.hidden = false;
            // Animate progress bar for benefits modal
            if (modal.hasAttribute('data-benefits-modal')) {
                animateBenefitsProgress(modal);
            }
        }
    }

    function animateBenefitsProgress(modal) {
        var progressFill = modal.querySelector('[data-progress-fill]');
        var progressStatus = modal.querySelector('[data-progress-status]');
        var progressStatusText = modal.querySelector('[data-progress-status-text]');
        var progressPercent = modal.querySelector('[data-progress-percent]');
        var progressScanned = modal.querySelector('[data-progress-scanned]');
        var progressMatches = modal.querySelector('[data-progress-matches]');
        var progressCompleted = modal.querySelector('[data-progress-completed]');
        var titleCounter = modal.querySelector('[data-title-counter]');
        var matchesPreview = modal.querySelector('[data-matches-preview]');

        var totalJobs = parseInt(progressScanned ? progressScanned.getAttribute('data-total-jobs') : '2847', 10) || 2847;
        var totalMatches = parseInt(titleCounter ? titleCounter.getAttribute('data-total-matches') : '24', 10) || 24;
        var duration = 1400; // 1.4 seconds total
        var steps = 25;
        var stepTime = duration / steps;
        var currentStep = 0;

        // Reset to initial state
        if (progressFill) progressFill.style.width = '0%';
        if (progressStatus) progressStatus.classList.add('is-building');
        if (progressStatusText) progressStatusText.textContent = 'Building...';
        if (progressPercent) progressPercent.textContent = '0% complete';
        if (progressScanned) progressScanned.textContent = '0 jobs scanned';
        if (progressMatches) progressMatches.textContent = '0 matches found';
        if (progressCompleted) progressCompleted.hidden = true;
        if (titleCounter) titleCounter.textContent = '0';
        if (matchesPreview) matchesPreview.classList.remove('is-visible');

        var interval = window.setInterval(function () {
            currentStep++;
            var progress = Math.min(currentStep / steps, 1);
            var eased = 1 - Math.pow(1 - progress, 3); // ease-out cubic

            var percent = Math.round(eased * 100);
            var scanned = Math.round(eased * totalJobs);
            var matches = Math.round(eased * totalMatches);

            if (progressFill) progressFill.style.width = percent + '%';
            if (progressPercent) progressPercent.textContent = percent + '% complete';
            if (progressScanned) progressScanned.textContent = scanned.toLocaleString() + ' jobs scanned';
            if (progressMatches) progressMatches.textContent = matches.toLocaleString() + ' matches found';
            if (titleCounter) titleCounter.textContent = matches.toLocaleString();

            if (currentStep >= steps) {
                window.clearInterval(interval);
                // Change status to Ready
                if (progressStatus) progressStatus.classList.remove('is-building');
                if (progressStatusText) progressStatusText.textContent = 'Ready';
                if (progressMatches) progressMatches.textContent = totalMatches.toLocaleString() + ' matches found & queued';
                // Show completed time
                if (progressCompleted) {
                    progressCompleted.hidden = false;
                    var now = new Date();
                    var timeStr = now.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) + ', ' +
                                  now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }).toLowerCase();
                    progressCompleted.textContent = timeStr;
                }
                // Show matches preview with fade-in
                if (matchesPreview) {
                    matchesPreview.classList.add('is-visible');
                }
            }
        }, stepTime);
    }

    function startMatchingEngine() {
        var engine = q('[data-matching-engine]');
        if (!engine) return;

        var progressFill = engine.querySelector('[data-matching-fill]');
        var progressStatus = engine.querySelector('[data-matching-status]');
        var progressStatusText = engine.querySelector('[data-matching-status-text]');
        var progressPercent = engine.querySelector('[data-matching-percent]');
        var progressScanned = engine.querySelector('[data-matching-scanned]');
        var progressFound = engine.querySelector('[data-matching-found]');
        var resultsPanel = engine.querySelector('[data-matching-results]');

        var totalJobs = parseInt(progressScanned ? progressScanned.getAttribute('data-total-jobs') : '2500', 10) || 2500;
        var totalMatches = parseInt(progressFound ? progressFound.getAttribute('data-total-matches') : '24', 10) || 24;
        var duration = 2000; // 2 seconds
        var steps = 30;
        var stepTime = duration / steps;
        var currentStep = 0;

        // Reset to initial state
        if (progressFill) progressFill.style.width = '0%';
        if (progressStatus) progressStatus.classList.add('is-scanning');
        if (progressStatusText) progressStatusText.textContent = 'Scanning...';
        if (progressPercent) progressPercent.textContent = '0% complete';
        if (progressScanned) progressScanned.textContent = '0 roles checked';
        if (progressFound) progressFound.textContent = '0 matches';
        if (resultsPanel) resultsPanel.hidden = true;

        var interval = window.setInterval(function () {
            currentStep++;
            var progress = Math.min(currentStep / steps, 1);
            var eased = 1 - Math.pow(1 - progress, 3);

            var percent = Math.round(eased * 100);
            var scanned = Math.round(eased * totalJobs);
            var found = Math.round(eased * totalMatches);

            if (progressFill) progressFill.style.width = percent + '%';
            if (progressPercent) progressPercent.textContent = percent + '% complete';
            if (progressScanned) progressScanned.textContent = scanned.toLocaleString() + ' roles checked';
            if (progressFound) progressFound.textContent = found.toLocaleString() + ' matches';

            if (currentStep >= steps) {
                window.clearInterval(interval);
                if (progressStatus) progressStatus.classList.remove('is-scanning');
                if (progressStatusText) progressStatusText.textContent = 'Complete';
                if (progressFound) progressFound.textContent = totalMatches.toLocaleString() + ' matches found';
                // Show results with animation
                if (resultsPanel) {
                    resultsPanel.hidden = false;
                    resultsPanel.classList.add('is-visible');
                }
            }
        }, stepTime);
    }

    // Auto Apply button handlers
    qa('[data-auto-apply-single], [data-auto-apply-unlock], [data-auto-apply-all]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            // Set intro mode for recruiter introductions
            setCheckoutMode('intro');
            state.checkoutBackStep = 3;
            showStep(5);
        });
    });

    function closeModal(modal) {
        if (modal) {
            modal.hidden = true;
        }
    }

    function populateApplicationQueue() {
        if (!queueList) {
            return 0;
        }
        if (queueEmpty) {
            queueEmpty.hidden = qa('[data-queue-item]', queueList).length > 0;
        }
        var queueItems = qa('[data-queue-item]', queueList);
        queueItems.forEach(function (queueItem, index) {
            queueItem.classList.add('is-visible');
            var matchId = queueItem.getAttribute('data-match-id') || '';
            var row = matchId ? q('[data-match-row][data-match-id="' + matchId + '"][data-match-group="best"]') : null;
            var statusEl = q('.queue-item__status', queueItem);
            var rowStatus = row ? q('.status-select', row) : null;
            window.setTimeout(function () {
                if (rowStatus) {
                    rowStatus.className = 'status-select pending';
                    rowStatus.textContent = index === 0 ? 'Checking fit' : 'Applying...';
                }
            }, 160 + (index * 100));

            window.setTimeout(function () {
                if (statusEl) {
                    statusEl.textContent = index === 0 ? 'Priority queued' : 'Queued';
                    statusEl.classList.add('is-ready');
                }
                if (rowStatus) {
                    rowStatus.className = 'status-select' + (index === 0 ? ' pending' : '');
                    rowStatus.textContent = index === 0 ? 'Priority queued' : 'Ready to process';
                }
            }, 900 + (index * 120));
        });
        updateQueueCount(queueItems.length);
        return queueItems.length;
    }

    qa('[data-open-plan-modal]').forEach(function (button) {
        button.addEventListener('click', function () {
            openModal(planModal);
            if (stickyBar) {
                stickyBar.classList.add('is-hidden');
            }
        });
    });

    qa('[data-close-plan-modal]').forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            closeModal(planModal);
            updateStickyBarVisibility();
        });
    });

    qa('[data-close-benefits-modal]').forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            closeModal(benefitsModal);
            updateStickyBarVisibility();
        });
    });

    qa('[data-benefit-choice]').forEach(function (button) {
        button.addEventListener('click', function () {
            qa('[data-benefit-choice]').forEach(function (option) {
                option.classList.toggle('selected', option === button);
            });
        });
    });

    qa('[data-benefits-continue]').forEach(function (button) {
        button.addEventListener('click', function () {
            closeModal(benefitsModal);
            // Set checkout mode to intro and go to step 5
            setCheckoutMode('intro');
            state.checkoutBackStep = 3;
            showStep(5);
            updateStickyBarVisibility();
        });
    });

    qa('[data-step="3"] [data-dashboard-apply-all]').forEach(function (button) {
        button.addEventListener('click', function () {
            var count = populateApplicationQueue();
            updateQueueCount(count);
            window.setTimeout(function () {
                if (state.isGeneral) {
                    openModal(planModal);
                } else {
                    openModal(benefitsModal);
                }
                updateStickyBarVisibility();
            }, 180);
        });
    });

    qa('[data-decision-next="checkout"]').forEach(function (button) {
        button.addEventListener('click', function () {
            closeModal(planModal);
        });
    });

    // ============================================
    // NEW: Inline Pricing Panel Toggle
    // ============================================
    var pricingPanel = q('[data-inline-pricing-panel]');
    var stickyBar = q('[data-sticky-bottom-bar]');

    function showPricingPanel() {
        if (pricingPanel) {
            pricingPanel.classList.add('is-visible');
            if (stickyBar) {
                stickyBar.classList.add('is-hidden');
            }
        }
    }

    function hidePricingPanel() {
        if (pricingPanel) {
            pricingPanel.classList.remove('is-visible');
            if (stickyBar) {
                stickyBar.classList.remove('is-hidden');
            }
        }
    }

    function updateStickyBarVisibility() {
        if (!stickyBar) {
            return;
        }
        var stepAttr = stickyBar.getAttribute('data-step-sticky');
        var shouldShow = String(state.step) === String(stepAttr);
        var panelIsVisible = pricingPanel && pricingPanel.classList.contains('is-visible');
        var planModalVisible = planModal && !planModal.hidden;
        var benefitsModalVisible = benefitsModal && !benefitsModal.hidden;

        if (shouldShow && !panelIsVisible && !planModalVisible && !benefitsModalVisible) {
            stickyBar.classList.remove('is-hidden');
        } else {
            stickyBar.classList.add('is-hidden');
        }
    }

    // Toggle pricing panel (from toolbar and sticky bar)
    qa('[data-toggle-pricing-panel], [data-toggle-pricing-panel-sticky]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (button.hasAttribute('data-toggle-pricing-panel-sticky')) {
                if (state.isGeneral) {
                    openModal(planModal);
                } else {
                    openModal(benefitsModal);
                }
                updateStickyBarVisibility();
                return;
            }
            showPricingPanel();
        });
    });

    // Close pricing panel
    qa('[data-close-pricing-panel]').forEach(function (button) {
        button.addEventListener('click', function () {
            hidePricingPanel();
        });
    });

    // Apply Free (Top 3)
    qa('[data-apply-free]').forEach(function (button) {
        button.addEventListener('click', function () {
            // Show success message or update UI
            alert('Free tier: Applying to top 3 matches!');
            // In production, this would trigger actual application logic
        });
    });

    // Update sticky bar visibility on step change
    var originalShowStep = showStep;
    showStep = function (step) {
        originalShowStep(step);
        updateStickyBarVisibility();
        if (String(step) !== '3') {
            hidePricingPanel();
        }
    };

    // Initial sticky bar visibility
    syncQueueVisibility();
    updateStickyBarVisibility();

    // Checkout Accordion Functionality
    qa('[data-accordion-trigger]').forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            var accordionId = trigger.getAttribute('data-accordion-trigger');
            var accordion = trigger.closest('.checkout-accordion');
            var content = q('[data-accordion-content="' + accordionId + '"]', accordion);

            if (!accordion || !content) {
                return;
            }

            var isExpanded = accordion.classList.contains('checkout-accordion--expanded');

            if (isExpanded) {
                // Collapse
                accordion.classList.remove('checkout-accordion--expanded');
                content.classList.remove('checkout-accordion-content--expanded');
            } else {
                // Expand
                accordion.classList.add('checkout-accordion--expanded');
                content.classList.add('checkout-accordion-content--expanded');
            }
        });
    });

})();
