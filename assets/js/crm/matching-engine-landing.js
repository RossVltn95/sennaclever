(function () {
    'use strict';

    var config = window.sffcCrmMatchingEngine || {};
    var membershipsUrl = 'https://joinsenna.com/memberships/';

    function tokenize(text) {
        return String(text || '')
            .toLowerCase()
            .replace(/[^a-z0-9\s/+.-]/g, ' ')
            .split(/\s+/)
            .filter(function (token) {
                return token && token.length > 1;
            });
    }

    function unique(values) {
        return Array.from(new Set(values.filter(Boolean)));
    }

    function normalizeServerPost(raw) {
        if (!raw) {
            return null;
        }

        return {
            id: Number(raw.id || 0),
            roleTitle: raw.role_title || raw.roleTitle || '',
            company: raw.company || '',
            sector: raw.sector || raw.industry || '',
            location: raw.location || raw.location_city || raw.location_country || '',
            companyLogo: raw.company_logo || raw.companyLogo || '',
            viewUrl: raw.application_url || raw.viewUrl || raw.source_url || '',
            skills: Array.isArray(raw.skills_mentioned) ? raw.skills_mentioned : (Array.isArray(raw.skills) ? raw.skills : []),
            keywords: Array.isArray(raw.keywords) ? raw.keywords : tokenize(raw.keywords || ''),
            reasons: Array.isArray(raw.match_reasons) ? raw.match_reasons : [],
            score: Number(raw.match_score || 0)
        };
    }

    function buildApplicationPrep(post, cvText) {
        var tokenSet = new Set(tokenize(cvText));
        var skills = unique((post.skills || []).flatMap(tokenize));
        var keywords = unique((post.keywords || []).flatMap(tokenize));
        var targetSignals = unique(skills.concat(keywords)).slice(0, 12);
        var matchedSignals = targetSignals.filter(function (token) { return tokenSet.has(token); }).slice(0, 6);
        var missingSignals = targetSignals.filter(function (token) { return !tokenSet.has(token); }).slice(0, 5);
        var readiness = matchedSignals.length >= 5 ? 'Ready now' : (matchedSignals.length >= 3 ? 'Needs light tailoring' : 'Needs heavier tailoring');

        return {
            readiness: readiness,
            matchedSignals: matchedSignals,
            missingSignals: missingSignals,
            focusLine: matchedSignals.length
                ? 'Lead with ' + matchedSignals.slice(0, 3).join(', ') + ' when positioning your experience for this role.'
                : 'Your CV does not surface enough of this role\'s core signals yet. Rework the top bullets before applying.'
        };
    }

    function hydrateMatches(items) {
        return items.map(function (item) {
            var post = normalizeServerPost(item);
            return {
                post: post,
                score: Number(post && post.score ? post.score : 0),
                reasons: post && Array.isArray(post.reasons) ? post.reasons : []
            };
        }).filter(function (entry) {
            return entry.post && entry.post.id > 0;
        });
    }

    function setFeedback(root, message) {
        var feedback = root.querySelector('[data-matching-feedback]');
        if (!feedback) {
            return;
        }

        if (!message) {
            feedback.hidden = true;
            feedback.textContent = '';
            return;
        }

        feedback.hidden = false;
        feedback.textContent = message;
    }

    function setServiceFeedback(form, message, isError) {
        var feedback = form.querySelector('[data-service-feedback]');
        if (!feedback) {
            return;
        }

        if (!message) {
            feedback.hidden = true;
            feedback.textContent = '';
            feedback.classList.remove('is-error', 'is-success');
            return;
        }

        feedback.hidden = false;
        feedback.textContent = message;
        feedback.classList.toggle('is-error', !!isError);
        feedback.classList.toggle('is-success', !isError);
    }

    function renderResults(root, matches) {
        var countNode = root.querySelector('[data-matching-count]');
        var listNode = root.querySelector('[data-matching-results-list]');
        var bulkBar = root.querySelector('[data-matching-bulk-bar]');

        if (!listNode || !countNode) {
            return;
        }

        countNode.textContent = matches.length + (matches.length === 1 ? ' match' : ' matches');

        if (bulkBar) {
            bulkBar.hidden = true;
        }

        if (!matches.length) {
            listNode.innerHTML = '<div class="sffc-crm-matching-empty">No strong matches yet. Add more detail about sectors, locations, technical skills, and the types of roles you want.</div>';
            return;
        }

        listNode.innerHTML = matches.map(function (entry) {
            var post = entry.post;
            var reasons = Array.isArray(entry.reasons) && entry.reasons.length ? entry.reasons : (post.reasons || []);
            var reasonHtml = reasons.map(function (reason) {
                return '<li>' + reason + '</li>';
            }).join('');
            var skillsHtml = (post.skills || []).slice(0, 5).map(function (skill) {
                return '<span>' + skill + '</span>';
            }).join('');
            var meta = [post.company, post.sector, post.location].filter(Boolean).join(' • ');
            var scoreLabel = Math.max(1, Math.min(99, entry.score));
            var targetUrl = post.viewUrl || (config.loggedIn ? config.dashboardUrl : config.loginUrl) || '#';

            return '' +
                '<article class="sffc-crm-matching-result-card" data-matching-result-card data-post-id="' + post.id + '">' +
                    '<div class="sffc-crm-matching-result-top">' +
                        '<label class="sffc-crm-matching-result-select">' +
                            '<input type="checkbox" data-matching-select value="' + post.id + '">' +
                            '<span></span>' +
                        '</label>' +
                        '<div class="sffc-crm-matching-result-brand">' +
                            (post.companyLogo ? '<img src="' + post.companyLogo + '" alt="">' : '<span class="sffc-crm-matching-result-initial">' + (post.company || 'S').charAt(0).toUpperCase() + '</span>') +
                        '</div>' +
                        '<div class="sffc-crm-matching-result-copy">' +
                            '<h3>' + post.roleTitle + '</h3>' +
                            '<p>' + meta + '</p>' +
                        '</div>' +
                        '<div class="sffc-crm-matching-result-score">' + scoreLabel + '</div>' +
                    '</div>' +
                    (skillsHtml ? '<div class="sffc-crm-matching-result-skills">' + skillsHtml + '</div>' : '') +
                    '<ul class="sffc-crm-matching-result-reasons">' + reasonHtml + '</ul>' +
                    '<div class="sffc-crm-matching-result-actions">' +
                        '<a href="' + targetUrl + '" class="sffc-crm-matching-result-btn">View Match</a>' +
                    '</div>' +
                '</article>';
        }).join('');
    }

    function updateBulkSelection(root) {
        var selected = Array.from(root.querySelectorAll('[data-matching-select]:checked'));
        var bulkBar = root.querySelector('[data-matching-bulk-bar]');
        var countNode = root.querySelector('[data-matching-selected-count]');

        root.querySelectorAll('[data-matching-result-card]').forEach(function (card) {
            var checkbox = card.querySelector('[data-matching-select]');
            card.classList.toggle('is-selected', !!(checkbox && checkbox.checked));
        });

        if (!bulkBar || !countNode) {
            return;
        }

        if (!selected.length) {
            bulkBar.hidden = true;
            countNode.textContent = '0 roles selected';
            return;
        }

        bulkBar.hidden = false;
        countNode.textContent = selected.length + (selected.length === 1 ? ' role selected' : ' roles selected');
    }

    function renderWorkspace(root, selectedPosts, cvText) {
        var workspace = root.querySelector('[data-matching-workspace]');
        var list = root.querySelector('[data-matching-workspace-list]');

        if (!workspace || !list) {
            return;
        }

        if (!selectedPosts.length) {
            workspace.hidden = true;
            list.innerHTML = '';
            return;
        }

        list.innerHTML = selectedPosts.map(function (post, index) {
            var prep = buildApplicationPrep(post, cvText);
            var matched = prep.matchedSignals.map(function (signal) { return '<span>' + signal + '</span>'; }).join('');
            var missing = prep.missingSignals.map(function (signal) { return '<span>' + signal + '</span>'; }).join('');
            var targetUrl = post.viewUrl || (config.loggedIn ? config.dashboardUrl : config.loginUrl) || '#';

            return '' +
                '<article class="sffc-crm-matching-workspace-card">' +
                    '<div class="sffc-crm-matching-workspace-top">' +
                        '<div>' +
                            '<p class="sffc-crm-matching-workspace-step">Role ' + (index + 1) + '</p>' +
                            '<h3>' + post.roleTitle + '</h3>' +
                            '<p>' + [post.company, post.sector, post.location].filter(Boolean).join(' • ') + '</p>' +
                        '</div>' +
                        '<span class="sffc-crm-matching-workspace-readiness">' + prep.readiness + '</span>' +
                    '</div>' +
                    '<div class="sffc-crm-matching-workspace-grid">' +
                        '<div>' +
                            '<strong>Highlight on your CV</strong>' +
                            '<div class="sffc-crm-matching-workspace-tags">' + (matched || '<span>Review core bullets first</span>') + '</div>' +
                        '</div>' +
                        '<div>' +
                            '<strong>Signals still missing</strong>' +
                            '<div class="sffc-crm-matching-workspace-tags is-missing">' + (missing || '<span>No major gaps detected</span>') + '</div>' +
                        '</div>' +
                    '</div>' +
                    '<p class="sffc-crm-matching-workspace-focus">' + prep.focusLine + '</p>' +
                    '<div class="sffc-crm-matching-workspace-actions">' +
                        '<a href="' + targetUrl + '" class="sffc-crm-matching-result-btn">Open Role</a>' +
                    '</div>' +
                '</article>';
        }).join('');

        workspace.hidden = false;
        workspace.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function renderMatchState(root, matches, message) {
        var resultsPanelShell = root.querySelector('[data-matching-results-panel]');
        var resultsPanel = root.querySelector('[data-matching-results]');
        var workspace = root.querySelector('[data-matching-workspace]');

        renderResults(root, matches);

        if (resultsPanelShell) {
            resultsPanelShell.hidden = false;
        }

        if (workspace) {
            workspace.hidden = true;
        }

        if (resultsPanel) {
            resultsPanel.hidden = false;
            resultsPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        updateBulkSelection(root);
        setFeedback(root, message || '');
    }

    function showRoute(root, routeName) {
        var hub = root.querySelector('[data-matching-hub]');
        var panels = root.querySelectorAll('[data-route-panel]');

        panels.forEach(function (panel) {
            panel.hidden = panel.getAttribute('data-route-panel') !== routeName;
        });

        if (hub) {
            hub.hidden = !!routeName;
        }
    }

    function resetToHub(root) {
        showRoute(root, '');
        root.querySelectorAll('[data-service-form]').forEach(function (form) {
            setServiceFeedback(form, '');
        });
    }

    function submitServiceForm(form, actionName) {
        var submitButton = form.querySelector('[type="submit"]');
        var formData = new FormData(form);
        formData.append('action', actionName);
        formData.append('nonce', config.nonce || '');

        setServiceFeedback(form, '');

        if (submitButton) {
            submitButton.disabled = true;
        }

        fetch(config.ajaxUrl || '/wp-admin/admin-ajax.php', {
            method: 'POST',
            body: formData
        }).then(function (response) {
            return response.json();
        }).then(function (payload) {
            if (!payload || !payload.success) {
                throw new Error((payload && payload.data && payload.data.message) || 'Unable to submit your request right now.');
            }

            setServiceFeedback(form, (payload.data && payload.data.message) || 'Request sent.', false);

            var fileInput = form.querySelector('input[type="file"]');
            var preservedName = form.querySelector('input[name="name"]');
            var preservedEmail = form.querySelector('input[name="email"]');
            var nameValue = preservedName ? preservedName.value : '';
            var emailValue = preservedEmail ? preservedEmail.value : '';

            form.reset();

            if (preservedName) {
                preservedName.value = nameValue;
            }

            if (preservedEmail) {
                preservedEmail.value = emailValue;
            }

            if (fileInput) {
                fileInput.value = '';
            }
        }).catch(function (error) {
            setServiceFeedback(form, error.message || 'Unable to submit your request right now.', true);
        }).finally(function () {
            if (submitButton) {
                submitButton.disabled = false;
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var engines = document.querySelectorAll('[data-component="crm-matching-engine"]');

        engines.forEach(function (root) {
            var textarea = root.querySelector('[data-matching-input]');
            var runButton = root.querySelector('[data-matching-run]');
            var resetButton = root.querySelector('[data-matching-reset]');
            var resultsPanelShell = root.querySelector('[data-matching-results-panel]');
            var resultsPanel = root.querySelector('[data-matching-results]');
            var bulkPrepareButton = root.querySelector('[data-matching-bulk-prepare]');
            var workspace = root.querySelector('[data-matching-workspace]');
            var manualCvButton = root.querySelector('[data-cv-review-mode="manual"]');
            var manualCvForm = root.querySelector('[data-service-form="cv-review"]');
            var interviewForm = root.querySelector('[data-service-form="interview-practice"]');
            var currentMatches = [];
            var requestCache = new Map();
            var activeController = null;

            if (!textarea || !runButton || !resultsPanel) {
                return;
            }

            root.querySelectorAll('[data-route-target]').forEach(function (button) {
                button.addEventListener('click', function () {
                    window.location.href = membershipsUrl;
                });
            });

            root.querySelectorAll('[data-route-back]').forEach(function (button) {
                button.addEventListener('click', function () {
                    resetToHub(root);
                });
            });

            if (manualCvButton && manualCvForm) {
                manualCvButton.addEventListener('click', function () {
                    manualCvForm.hidden = false;
                    setServiceFeedback(manualCvForm, '');
                    manualCvForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            }

            if (manualCvForm) {
                manualCvForm.addEventListener('submit', function (event) {
                    event.preventDefault();
                    submitServiceForm(manualCvForm, 'sffc_crm_request_cv_review');
                });
            }

            if (interviewForm) {
                interviewForm.addEventListener('submit', function (event) {
                    event.preventDefault();
                    submitServiceForm(interviewForm, 'sffc_crm_request_interview_practice');
                });
            }

            runButton.addEventListener('click', function () {
                var cvText = textarea.value.trim();

                if (!cvText) {
                    setFeedback(root, 'Paste your CV first so MENA Careers can score live recruiter posts against it.');
                    textarea.focus();
                    return;
                }

                if (!config.ajaxUrl || !config.nonce) {
                    setFeedback(root, 'Matching is not configured correctly on this page yet.');
                    return;
                }

                if (requestCache.has(cvText)) {
                    currentMatches = requestCache.get(cvText);
                    renderMatchState(root, currentMatches, '');
                    return;
                }

                setFeedback(root, '');

                if (activeController) {
                    activeController.abort();
                }

                activeController = new AbortController();
                runButton.disabled = true;
                runButton.textContent = 'Scoring Matches...';

                var requestBody = new URLSearchParams();
                requestBody.append('action', 'sffc_crm_get_matches');
                requestBody.append('nonce', config.nonce || '');
                requestBody.append('cv_text', cvText);
                requestBody.append('fast_mode', '1');

                fetch(config.ajaxUrl || '/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: requestBody.toString(),
                    signal: activeController.signal
                }).then(function (response) {
                    return response.json();
                }).then(function (payload) {
                    if (!payload || !payload.success || !payload.data) {
                        throw new Error((payload && payload.data && payload.data.message) || 'Unable to score matches right now.');
                    }

                    currentMatches = hydrateMatches(Array.isArray(payload.data.items) ? payload.data.items : []);
                    requestCache.set(cvText, currentMatches);
                    renderMatchState(root, currentMatches, payload.data.message || '');
                }).catch(function (error) {
                    if (error && error.name === 'AbortError') {
                        return;
                    }

                    setFeedback(root, error.message || 'Unable to score matches right now.');
                }).finally(function () {
                    activeController = null;
                    runButton.disabled = false;
                    runButton.textContent = 'Build My Matches';
                });
            });

            if (resetButton) {
                resetButton.addEventListener('click', function () {
                    currentMatches = [];
                    resultsPanel.hidden = true;
                    if (resultsPanelShell) {
                        resultsPanelShell.hidden = true;
                    }
                    if (workspace) {
                        workspace.hidden = true;
                    }
                    setFeedback(root, '');
                });
            }

            root.addEventListener('change', function (event) {
                if (event.target && event.target.matches('[data-matching-select]')) {
                    updateBulkSelection(root);
                }
            });

            if (bulkPrepareButton) {
                bulkPrepareButton.addEventListener('click', function () {
                    var selected = Array.from(root.querySelectorAll('[data-matching-select]:checked'));

                    if (!selected.length) {
                        setFeedback(root, 'Select at least one role before preparing your application sprint.');
                        return;
                    }

                    var selectedIds = selected.map(function (item) {
                        return Number(item.value);
                    });

                    var selectedPosts = currentMatches.map(function (entry) {
                        return entry.post;
                    }).filter(function (post) {
                        return selectedIds.indexOf(Number(post.id)) !== -1;
                    });

                    renderWorkspace(root, selectedPosts, textarea.value.trim());
                    setFeedback(root, '');
                });
            }
        });
    });
})();
