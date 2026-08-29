(function () {
  'use strict';

  var config = window.sffcCommunityEditorialConfig || {};

  function getOnboardingState(root) {
    if (!root.__sffcOnboardingState) {
      root.__sffcOnboardingState = {
        accessChoice: 'full_access',
        accessLabel: 'CV + LinkedIn Review',
        locationChoice: '',
        locationOther: '',
        roleInterest: 'analyst_associate',
        idealRole: '',
        targetSector: '',
        targetRoleItems: [],
        targetRoles: [],
        targetSectors: [],
        selectedPlan: '',
        cvSaved: false,
        cvFileName: '',
        cvSkipAvailable: false,
        cvFailureCount: 0
      };
    }
    return root.__sffcOnboardingState;
  }

  function registerOnboardingCvFailure(root) {
    var state = getOnboardingState(root);

    state.cvFailureCount = Math.max(0, parseInt(state.cvFailureCount || 0, 10)) + 1;
    state.cvSkipAvailable = true;

    updateOnboardingSkipState(root);
  }

  function parseAjaxJson(response) {
    return response.text().then(function (text) {
      var payload = null;
      var trimmed = String(text || '').trim();

      try {
        payload = JSON.parse(text);
      } catch (error) {
        if (trimmed.slice(0, 1) === '<') {
          throw new Error('The server returned HTML instead of JSON.');
        }
        throw new Error('The server returned an invalid JSON response.');
      }

      return payload;
    });
  }

  function primeOnboardingPlanSummaryFromRoot(root) {
    var state = getOnboardingState(root);

    if (!state.accessLabel) {
      state.accessLabel = root.getAttribute('data-sffc-community-summary-access') || 'CV + LinkedIn Review';
    }
    if (!state.accessChoice) {
      state.accessChoice = 'full_access';
    }
    if (!state.locationChoice && !state.locationOther) {
      state.locationOther = root.getAttribute('data-sffc-community-summary-location') || 'Middle East, London, or Singapore';
    }
    if (!Array.isArray(state.targetRoles) || !state.targetRoles.length) {
      state.targetRoles = String(root.getAttribute('data-sffc-community-summary-roles') || 'Target private equity roles')
        .split(',')
        .map(function (role) {
          return role.trim();
        })
        .filter(Boolean);
    }
    if (!state.cvSaved) {
      state.cvFileName = root.getAttribute('data-sffc-community-summary-cv') || 'Add during checkout';
    }
  }

  function openCommunityMembershipPlanModal(root) {
    var overlay = root ? root.querySelector('[data-sffc-community-onboarding]') : null;

    if (!root || !overlay) {
      return false;
    }

    primeOnboardingPlanSummaryFromRoot(root);
    showOnboarding(root);
    setOnboardingStep(root, 'plan');
    updateOnboardingPlanPrices(root);

    return true;
  }

  function setCommunityAuthMode(root, mode) {
    var panel = root ? root.querySelector('[data-sffc-community-auth-panel]') : null;
    if (!panel) {
      return;
    }

    mode = mode === 'signin' ? 'signin' : 'signup';

    panel.querySelectorAll('[data-sffc-community-auth-mode]').forEach(function (button) {
      var isActive = button.getAttribute('data-sffc-community-auth-mode') === mode;
      button.classList.toggle('is-active', isActive);
      button.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });

    panel.querySelectorAll('[data-sffc-community-auth-form]').forEach(function (form) {
      var isActive = form.getAttribute('data-sffc-community-auth-form') === mode;
      form.hidden = !isActive;
      form.classList.toggle('is-active', isActive);
    });
  }

  function openCommunityAuthDropdown(root, mode) {
    var panel = root ? root.querySelector('[data-sffc-community-auth-panel]') : null;
    var firstInput;
    if (!panel) {
      return;
    }

    setCommunityAuthMode(root, mode || 'signup');
    panel.hidden = false;
    root.__sffcCommunityAuthOpen = true;

    firstInput = panel.querySelector('.sffc-community-editorial__auth-form.is-active input:not([type="hidden"])');
    if (firstInput && typeof firstInput.focus === 'function') {
      window.setTimeout(function () {
        firstInput.focus();
      }, 20);
    }
  }

  function closeCommunityAuthDropdown(root) {
    var panel = root ? root.querySelector('[data-sffc-community-auth-panel]') : null;
    if (!panel) {
      return;
    }

    panel.hidden = true;
    root.__sffcCommunityAuthOpen = false;
  }

  function setCommunityAuthFeedback(form, message, state) {
    var feedback = form ? form.querySelector('[data-sffc-community-auth-feedback]') : null;
    if (!feedback) {
      return;
    }

    feedback.textContent = message || '';
    feedback.hidden = !message;
    feedback.classList.toggle('is-error', state === 'error');
    feedback.classList.toggle('is-success', state === 'success');
    feedback.classList.toggle('is-loading', state === 'loading');
  }

  function checkCommunitySignupEmail(email) {
    var body = new FormData();
    body.append('action', 'sffc_crm_reddit_check_account_email');
    body.append('nonce', config.crmNonce || '');
    body.append('email', email || '');
    body.append('redirect_to', config.terminalUrl || '/terminal/');

    return window.fetch((config.ajaxUrl || '/wp-admin/admin-ajax.php'), {
      method: 'POST',
      body: body,
      credentials: 'same-origin'
    }).then(parseAjaxJson);
  }

  function shouldOpenCommunityMembershipPlanForUnpaidClick() {
    return false;
  }

  function userHasCommunityPremiumAccess(root) {
    if (root && root.getAttribute('data-sffc-community-premium-access') === 'true') {
      return true;
    }
    return !!(config && config.hasPremiumAccess);
  }

  function openCommunityApplyForMeModal(root, trigger) {
    var modal = root ? root.querySelector('[data-sffc-apply-for-me-modal]') : null;
    var dialog = modal ? modal.querySelector('.sffc-community-editorial__apply-for-me-dialog') : null;
    var form = modal ? modal.querySelector('[data-sffc-apply-for-me-form]') : null;
    var roleEl = modal ? modal.querySelector('[data-sffc-apply-for-me-role]') : null;
    var companyEl = modal ? modal.querySelector('[data-sffc-apply-for-me-company]') : null;
    var recruiterEl = modal ? modal.querySelector('[data-sffc-apply-for-me-recruiter]') : null;
    var avatarFallback = modal ? modal.querySelector('[data-sffc-apply-for-me-avatar-fallback]') : null;
    var feedback = modal ? modal.querySelector('[data-sffc-apply-for-me-feedback]') : null;
    var roleTitle;
    var companyName;
    var recruiterName;
    var recruiterEmail;
    var postId;
    var initial;

    if (!modal || !form || !trigger) {
      return;
    }

    root.__sffcCommunityApplyForMeTrigger = trigger;

    roleTitle = String(trigger.getAttribute('data-sffc-community-role-title') || '').trim();
    companyName = String(trigger.getAttribute('data-sffc-community-discovery-company') || trigger.getAttribute('data-sffc-community-company') || '').trim();
    recruiterName = String(trigger.getAttribute('data-sffc-community-discovery-name') || trigger.getAttribute('data-sffc-community-recruiter-name') || '').trim();
    recruiterEmail = String(trigger.getAttribute('data-sffc-community-discovery-email') || trigger.getAttribute('data-sffc-community-recruiter-email') || '').trim();
    postId = String(trigger.getAttribute('data-sffc-community-post-id') || '').trim();
    initial = String(trigger.getAttribute('data-sffc-community-discovery-initial') || recruiterName.charAt(0) || 'S').trim() || 'S';

    form.reset();
    form.elements.post_id.value = postId;
    form.elements.role_title.value = roleTitle;
    form.elements.company_name.value = companyName;
    form.elements.recruiter_name.value = recruiterName;
    form.elements.recruiter_email.value = recruiterEmail;

    if (roleEl) {
      roleEl.textContent = roleTitle || 'Role';
    }
    if (companyEl) {
      companyEl.textContent = companyName || 'Company';
    }
    if (recruiterEl) {
      recruiterEl.textContent = recruiterName ? recruiterName + (recruiterEmail ? ' · ' + recruiterEmail : '') : 'Recruiter route';
    }
    if (avatarFallback) {
      avatarFallback.textContent = initial;
    }
    if (feedback) {
      feedback.hidden = true;
      feedback.textContent = '';
      feedback.classList.remove('is-error', 'is-success');
    }

    modal.hidden = false;
    document.documentElement.classList.add('sffc-community-editorial-modal-open');
    document.body.classList.add('sffc-community-editorial-modal-open');

    if (dialog && typeof dialog.focus === 'function') {
      window.setTimeout(function () {
        dialog.focus();
      }, 20);
    }
  }

  function closeCommunityApplyForMeModal(root) {
    var modal = root ? root.querySelector('[data-sffc-apply-for-me-modal]') : null;
    if (!modal) {
      return;
    }

    modal.hidden = true;
    root.__sffcCommunityApplyForMeTrigger = null;
    document.documentElement.classList.remove('sffc-community-editorial-modal-open');
    document.body.classList.remove('sffc-community-editorial-modal-open');
  }

  function submitCommunityApplyForMeRequest(root, form) {
    var feedback = form ? form.querySelector('[data-sffc-apply-for-me-feedback]') : null;
    var submitButton = form ? form.querySelector('[data-sffc-apply-for-me-submit]') : null;
    var cvSelect = form ? form.querySelector('[data-sffc-apply-for-me-cv]') : null;
    var selectedOption = cvSelect ? cvSelect.options[cvSelect.selectedIndex] : null;
    var storedCvUrl = cvSelect ? String(cvSelect.value || '').trim() : '';
    var storedCvName = selectedOption ? String(selectedOption.getAttribute('data-cv-name') || selectedOption.textContent || '').trim() : '';
    var notes = form && form.elements.apply_for_me_notes ? String(form.elements.apply_for_me_notes.value || '').trim() : '';
    var roleTitle = form && form.elements.role_title ? String(form.elements.role_title.value || '').trim() : '';
    var companyName = form && form.elements.company_name ? String(form.elements.company_name.value || '').trim() : '';
    var recruiterName = form && form.elements.recruiter_name ? String(form.elements.recruiter_name.value || '').trim() : '';
    var recruiterEmail = form && form.elements.recruiter_email ? String(form.elements.recruiter_email.value || '').trim() : '';
    var postId = form && form.elements.post_id ? String(form.elements.post_id.value || '').trim() : '';
    var trigger = root ? root.__sffcCommunityApplyForMeTrigger : null;
    var originalLabel = trigger ? String(trigger.textContent || '').trim() : 'Get Hired';
    var requestParts;
    var formData;

    if (!root || !form || typeof window.fetch !== 'function') {
      return;
    }

    if (!storedCvUrl) {
      if (feedback) {
        feedback.hidden = false;
        feedback.classList.add('is-error');
        feedback.classList.remove('is-success');
        feedback.textContent = 'Choose a saved CV first.';
      }
      if (cvSelect) {
        cvSelect.focus();
      }
      return;
    }

    requestParts = [
      'Get Hired request',
      roleTitle ? 'Role: ' + roleTitle : '',
      companyName ? 'Company: ' + companyName : '',
      recruiterName ? 'Recruiter: ' + recruiterName : '',
      recruiterEmail ? 'Recruiter email: ' + recruiterEmail : '',
      storedCvName ? 'Saved CV: ' + storedCvName : '',
      notes ? 'Notes: ' + notes : ''
    ].filter(Boolean);

    formData = new FormData();
    formData.append('action', 'sffc_crm_apply_chat_request_human_followup');
    formData.append('nonce', config.requestHumanNonce || '');
    formData.append('post_id', postId);
    formData.append('role_title', roleTitle);
    formData.append('message', requestParts.join(' | '));
    formData.append('active_path', 'community_editorial');
    formData.append('prompt_state', 'community_apply_for_me');
    formData.append('page_url', window.location.href || '');
    formData.append('conversation_focus', 'community_editorial_apply_for_me');
    formData.append('cv_profile', [storedCvName, storedCvUrl].filter(Boolean).join(' · '));
    formData.append('context_summary', requestParts.join(' | '));
    formData.append('request_kind', 'apply_for_me');

    if (feedback) {
      feedback.hidden = true;
      feedback.textContent = '';
      feedback.classList.remove('is-error', 'is-success');
    }
    if (submitButton) {
      submitButton.disabled = true;
      submitButton.textContent = 'Sending...';
    }

    window.fetch(config.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: formData
    })
      .then(parseAjaxJson)
      .then(function (payload) {
        if (!payload || !payload.success) {
          throw new Error(payload && payload.data && payload.data.message ? payload.data.message : 'We could not send that request right now.');
        }

        if (trigger) {
          trigger.disabled = true;
          trigger.setAttribute('aria-disabled', 'true');
          trigger.textContent = 'Requested';
        }
        closeCommunityApplyForMeModal(root);
      })
      .catch(function (error) {
        if (feedback) {
          feedback.hidden = false;
          feedback.classList.add('is-error');
          feedback.classList.remove('is-success');
          feedback.textContent = error && error.message ? error.message : 'We could not send that request right now.';
        }
        if (submitButton) {
          submitButton.disabled = false;
          submitButton.textContent = originalLabel === 'Get Hired' ? 'Send request' : originalLabel;
        }
      });
  }

  function closeCommunityRoleMenus(root, exceptMenu) {
    if (!root) {
      return;
    }

    root.querySelectorAll('[data-sffc-community-role-menu]').forEach(function (menu) {
      var toggle;

      if (exceptMenu && menu === exceptMenu) {
        return;
      }

      menu.hidden = true;
      toggle = menu.parentElement ? menu.parentElement.querySelector('[data-sffc-community-role-menu-toggle]') : null;
      if (toggle) {
        toggle.setAttribute('aria-expanded', 'false');
      }
    });
  }

  function toggleCommunityRoleMenu(root, toggle) {
    var wrap = toggle ? toggle.closest('.sffc-community-editorial__post-title-wrap') : null;
    var menu = wrap ? wrap.querySelector('[data-sffc-community-role-menu]') : null;
    var willOpen;

    if (!root || !toggle || !menu) {
      return;
    }

    willOpen = menu.hidden;
    closeCommunityRoleMenus(root, willOpen ? menu : null);
    menu.hidden = !willOpen;
    toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
  }

  function setEmbeddedProfileReviewFeedback(scope, message, isError) {
    if (!scope) {
      return;
    }

    var feedback = scope.querySelector('[data-reddit-profile-feedback]');
    if (!feedback) {
      feedback = scope.querySelector('.sffc-crm-dashboard-app-review-feedback');
    }

    if (!feedback) {
      return;
    }

    if (!message) {
      feedback.hidden = true;
      feedback.textContent = '';
      feedback.classList.remove('is-error');
      return;
    }

    feedback.hidden = false;
    feedback.textContent = String(message);
    feedback.classList.toggle('is-error', !!isError);
  }

  function replaceEmbeddedProfileReviewShell(root, markup) {
    var currentShell;
    var temp;
    var nextShell;

    if (!root || !markup) {
      return null;
    }

    currentShell = root.querySelector('[data-dashboard-review-shell]');
    if (!currentShell) {
      return null;
    }

    temp = document.createElement('div');
    temp.innerHTML = String(markup || '').trim();
    nextShell = temp.querySelector('[data-dashboard-review-shell]');

    if (!nextShell) {
      return null;
    }

    currentShell.replaceWith(nextShell);
    return nextShell;
  }

  function handleEmbeddedActiveResume(root, trigger) {
    var uploadId;
    var requestBody;
    var reviewShell;
    var originalLabel;

    if (!root || !trigger || typeof window.fetch !== 'function') {
      return;
    }

    uploadId = parseInt(trigger.getAttribute('data-reddit-set-resume') || '0', 10);
    if (isNaN(uploadId) || uploadId <= 0) {
      return;
    }

    reviewShell = trigger.closest('[data-dashboard-review-shell]') || root.querySelector('[data-dashboard-review-shell]');
    originalLabel = trigger.textContent;

    requestBody = new FormData();
    requestBody.append('action', 'sffc_crm_reddit_set_active_resume');
    requestBody.append('nonce', config.accountNonce || '');
    requestBody.append('upload_id', String(uploadId));

    trigger.disabled = true;
    trigger.textContent = '…';
    setEmbeddedProfileReviewFeedback(reviewShell || root, 'Updating active resume...', false);

    window.fetch((config.ajaxUrl || '/wp-admin/admin-ajax.php'), {
      method: 'POST',
      body: requestBody,
      credentials: 'same-origin'
    })
      .then(parseAjaxJson)
      .then(function (payload) {
        var nextShell;

        if (!payload || !payload.success || !payload.data) {
          throw new Error((payload && payload.data && payload.data.message) || 'Unable to update your active resume.');
        }

        nextShell = payload.data.review_markup
          ? replaceEmbeddedProfileReviewShell(root, payload.data.review_markup)
          : null;

        setEmbeddedProfileReviewFeedback(nextShell || reviewShell || root, 'Active resume updated.', false);
      })
      .catch(function (error) {
        setEmbeddedProfileReviewFeedback(
          reviewShell || root,
          error && error.message ? error.message : 'Unable to update your active resume.',
          true
        );
        trigger.disabled = false;
        trigger.textContent = originalLabel;
      });
  }

  function setCommunityNewsletterFeedback(card, message, isError) {
    var feedback = card ? card.querySelector('[data-cv-match-newsletter-feedback]') : null;

    if (!feedback) {
      return;
    }

    feedback.hidden = !message;
    feedback.textContent = message || '';
    feedback.classList.toggle('is-error', !!isError);
  }

  function handleCommunityNewsletterToggle(root, trigger) {
    var newsletterId;
    var card;
    var originalText;
    var requestBody;

    if (!root || !trigger || typeof window.fetch !== 'function') {
      return;
    }

    newsletterId = trigger.getAttribute('data-newsletter-id') || '';
    card = trigger.closest('[data-cv-match-newsletter-card]');

    if (!newsletterId || !config.ajaxUrl || !config.crmNonce) {
      setCommunityNewsletterFeedback(card, 'We could not update this newsletter right now.', true);
      return;
    }

    originalText = trigger.textContent;
    requestBody = new FormData();
    requestBody.append('action', 'sffc_cv_match_toggle_newsletter_subscription');
    requestBody.append('nonce', config.crmNonce || '');
    requestBody.append('newsletter_id', newsletterId);

    trigger.disabled = true;
    trigger.textContent = 'Updating...';
    setCommunityNewsletterFeedback(card, '', false);

    window.fetch(config.ajaxUrl, {
      method: 'POST',
      body: requestBody,
      credentials: 'same-origin'
    })
      .then(parseAjaxJson)
      .then(function (payload) {
        var isSubscribed;
        var label;
        var message;

        if (!payload || !payload.success) {
          throw new Error((payload && payload.data && payload.data.message) || 'We could not update this newsletter right now.');
        }

        isSubscribed = !!(payload.data && payload.data.subscribed);
        label = (payload.data && payload.data.label) || (isSubscribed ? 'On' : 'Subscribe');
        message = (payload.data && payload.data.message) || (isSubscribed ? 'You are subscribed.' : 'Alerts are off.');

        trigger.textContent = label === 'Turn on' ? 'Subscribe' : label;
        trigger.setAttribute('aria-pressed', isSubscribed ? 'true' : 'false');

        if (card) {
          card.classList.toggle('is-subscribed', isSubscribed);
        }

        setCommunityNewsletterFeedback(card, message, false);
      })
      .catch(function (error) {
        trigger.textContent = originalText;
        setCommunityNewsletterFeedback(
          card,
          error && error.message ? error.message : 'We could not update this newsletter right now.',
          true
        );
      })
      .finally(function () {
        trigger.disabled = false;
      });
  }

  function loadExternalScript(url, globalName) {
    var normalizedUrl = String(url || '').trim();
    var pendingKey;
    var existing;

    if (globalName && typeof window[globalName] !== 'undefined') {
      return Promise.resolve(window[globalName]);
    }

    if (!normalizedUrl) {
      return Promise.reject(new Error('Script URL is unavailable.'));
    }

    pendingKey = '__sffcCommunityScriptPromise__' + normalizedUrl;
    if (window[pendingKey]) {
      return window[pendingKey];
    }

    existing = document.querySelector('script[src="' + normalizedUrl + '"]');
    if (existing && globalName && typeof window[globalName] !== 'undefined') {
      return Promise.resolve(window[globalName]);
    }

    window[pendingKey] = new Promise(function (resolve, reject) {
      var script = existing || document.createElement('script');

      script.async = true;
      script.src = normalizedUrl;
      script.onload = function () {
        if (globalName && typeof window[globalName] === 'undefined') {
          reject(new Error('Loaded script did not expose ' + globalName + '.'));
          return;
        }
        resolve(globalName ? window[globalName] : true);
      };
      script.onerror = function () {
        reject(new Error('Unable to load ' + normalizedUrl + '.'));
      };

      if (!existing) {
        document.head.appendChild(script);
      }
    });

    return window[pendingKey];
  }

  function parsePdf(file) {
    return loadExternalScript(config.pdfScriptUrl, 'pdfjsLib')
      .then(function (pdfjs) {
        if (!pdfjs) {
          throw new Error('PDF parser is unavailable.');
        }

        if (config.pdfWorker) {
          pdfjs.GlobalWorkerOptions.workerSrc = config.pdfWorker;
        }

        return file.arrayBuffer().then(function (buffer) {
          return pdfjs.getDocument({ data: buffer }).promise;
        });
      })
      .then(function (pdf) {
        var pages = [];
        for (var i = 1; i <= pdf.numPages; i += 1) {
          pages.push(
            pdf.getPage(i).then(function (page) {
              return page.getTextContent().then(function (content) {
                return content.items.map(function (item) {
                  return item.str || '';
                }).join(' ');
              });
            })
          );
        }

        return Promise.all(pages).then(function (parts) {
          return parts.join('\n').trim();
        });
      });
  }

  function parseDocx(file) {
    return loadExternalScript(config.mammothScriptUrl, 'mammoth')
      .then(function (mammothLib) {
        if (!mammothLib) {
          throw new Error('DOCX parser is unavailable.');
        }

        return file.arrayBuffer().then(function (buffer) {
          return mammothLib.extractRawText({ arrayBuffer: buffer });
        });
      })
      .then(function (result) {
        return String(result && result.value ? result.value : '').trim();
      });
  }

  function parseTxt(file) {
    return file.text().then(function (content) {
      return String(content || '').trim();
    });
  }

  function parseFile(file) {
    if (!file) {
      return Promise.reject(new Error((config.strings && config.strings.cvEmpty) || 'Choose a CV file first.'));
    }

    var name = String(file.name || '').toLowerCase();

    if (name.endsWith('.pdf')) {
      return parsePdf(file);
    }
    if (name.endsWith('.docx') || name.endsWith('.doc')) {
      return parseDocx(file);
    }
    if (name.endsWith('.txt')) {
      return parseTxt(file);
    }

    return Promise.reject(new Error((config.strings && config.strings.cvParseError) || 'Unsupported file type.'));
  }

  function updateOnboardingUploadStatus(root, message, isLoaded) {
    var overlay = root.querySelector('[data-sffc-community-onboarding]');
    var statusNode = overlay ? overlay.querySelector('[data-sffc-community-onboarding-upload-status]') : null;
    var card = overlay ? overlay.querySelector('[data-sffc-community-onboarding-cv-card]') : null;

    if (statusNode && message) {
      statusNode.textContent = String(message);
    }
    if (card) {
      card.classList.toggle('is-loaded', !!isLoaded);
    }

    updateOnboardingSubmitState(root);
  }

  function updateOnboardingSubmitState(root) {
    var overlay = root.querySelector('[data-sffc-community-onboarding]');
    var submitButton = overlay ? overlay.querySelector('[data-sffc-community-onboarding-submit]') : null;
    var state = getOnboardingState(root);

    if (!submitButton) {
      return;
    }

    submitButton.disabled = false;
  }

  function updateOnboardingSkipState(root) {
    var overlay = root.querySelector('[data-sffc-community-onboarding]');
    var skipButton = overlay ? overlay.querySelector('[data-sffc-community-onboarding-skip]') : null;
    var state = getOnboardingState(root);

    if (!skipButton) {
      return;
    }

    skipButton.hidden = !state.cvSkipAvailable;
  }

  function persistOnboardingCvText(root, cvText, fileName) {
    var normalized = String(cvText || '').trim();
    var requestBody = new FormData();

    if (!normalized) {
      return Promise.reject(new Error((config.strings && config.strings.cvParseError) || 'We could not read that file yet.'));
    }

    requestBody.append('action', 'sffc_crm_reddit_save_pasted_cv');
    requestBody.append('nonce', config.accountNonce || '');
    requestBody.append('cv_text', normalized);
    requestBody.append('file_name', String(fileName || 'resume'));

    return window.fetch((config.ajaxUrl || '/wp-admin/admin-ajax.php'), {
      method: 'POST',
      body: requestBody,
      credentials: 'same-origin'
    })
      .then(parseAjaxJson)
      .then(function (payload) {
        if (!payload || !payload.success) {
          throw new Error((payload && payload.data && payload.data.message) || ((config.strings && config.strings.saveError) || 'We could not save your CV right now.'));
        }

        var state = getOnboardingState(root);
        state.cvSaved = true;
        state.cvFileName = String(fileName || '');
        updateOnboardingUploadStatus(root, (config.strings && config.strings.cvSaved) || 'CV saved. MENA Careers will use it to tailor your feed.', true);
        return payload;
      });
  }

  function getCommunityGuestCvToken(root) {
    return root ? String(root.getAttribute('data-sffc-community-guest-cv-token') || '').trim() : '';
  }

  function getStoredCommunityGuestCvToken() {
    try {
      return window.sessionStorage ? String(window.sessionStorage.getItem('sffcCommunityGuestCvToken') || '').trim() : '';
    } catch (error) {
      return '';
    }
  }

  function setStoredCommunityGuestCvToken(token) {
    try {
      if (window.sessionStorage && token) {
        window.sessionStorage.setItem('sffcCommunityGuestCvToken', String(token));
      }
    } catch (error) {
      // Session storage can be blocked in private contexts; the in-page token still works.
    }
  }

  function getCommunityMatchCvUploadNonce(root) {
    return String(
      config.matchCvUploadNonce ||
      (root ? root.getAttribute('data-sffc-community-match-cv-upload-nonce') : '') ||
      ''
    ).trim();
  }

  function getCommunityMembershipUrl(email) {
    var baseUrl = String(config.membershipsUrl || '/memberships/').trim() || '/memberships/';
    var cleanEmail = String(email || '').trim();
    if (!cleanEmail) {
      return baseUrl;
    }

    try {
      var url = new URL(baseUrl, window.location.origin);
      url.searchParams.set('sffc_capture_signup_email', '1');
      url.searchParams.set('user_email', cleanEmail);
      return url.toString();
    } catch (error) {
      var separator = baseUrl.indexOf('?') === -1 ? '?' : '&';
      return baseUrl + separator + 'sffc_capture_signup_email=1&user_email=' + encodeURIComponent(cleanEmail);
    }
  }

  function communityHasPremiumAccess(root) {
    if (root && root.getAttribute('data-sffc-community-premium-access') === 'true') {
      return true;
    }

    return !!config.hasPremiumAccess;
  }

  function getCommunityCvBenefitsGate(root) {
    return root ? root.querySelector('[data-sffc-community-cv-benefits-gate]') : null;
  }

  function openCommunityCvBenefitsGate(root) {
    var gate = getCommunityCvBenefitsGate(root);
    if (!gate) {
      window.location.assign(getCommunityMembershipUrl(config.currentUserEmail || ''));
      return;
    }

    gate.hidden = false;
    gate.classList.add('is-open');
    document.documentElement.classList.add('sffc-community-editorial-modal-open');
    document.body.classList.add('sffc-community-editorial-modal-open');

    var emailInput = gate.querySelector('[data-sffc-community-cv-benefits-email]');
    if (emailInput && !emailInput.value && config.currentUserEmail) {
      emailInput.value = String(config.currentUserEmail || '');
    }

    window.setTimeout(function () {
      if (emailInput) {
        emailInput.focus();
      }
    }, 50);
  }

  function closeCommunityCvBenefitsGate(root) {
    var gate = getCommunityCvBenefitsGate(root);
    if (!gate) {
      return;
    }

    gate.hidden = true;
    gate.classList.remove('is-open');
    document.documentElement.classList.remove('sffc-community-editorial-modal-open');
    document.body.classList.remove('sffc-community-editorial-modal-open');
  }

  function submitCommunityCvBenefitsGate(root, form) {
    var gate = getCommunityCvBenefitsGate(root);
    var emailInput = form ? form.querySelector('[data-sffc-community-cv-benefits-email]') : null;
    var submitButton = form ? form.querySelector('button[type="submit"]') : null;
    var feedback = gate ? gate.querySelector('[data-sffc-community-cv-benefits-feedback]') : null;
    var email = emailInput ? String(emailInput.value || '').trim() : '';

    if (!email || email.indexOf('@') === -1) {
      if (feedback) {
        feedback.hidden = false;
        feedback.classList.add('is-error');
        feedback.textContent = 'Enter a valid email address.';
      }
      if (emailInput) {
        emailInput.focus();
      }
      return;
    }

    if (feedback) {
      feedback.hidden = false;
      feedback.classList.remove('is-error');
      feedback.textContent = 'Taking you to membership...';
    }
    if (submitButton) {
      submitButton.disabled = true;
      submitButton.textContent = 'Opening...';
    }

    var requestBody = new FormData();
    requestBody.append('action', 'sffc_crm_editorial_cv_upload_membership_capture');
    requestBody.append('nonce', String(config.cvUploadMembershipNonce || ''));
    requestBody.append('email', email);

    window.fetch((config.ajaxUrl || '/wp-admin/admin-ajax.php'), {
      method: 'POST',
      body: requestBody,
      credentials: 'same-origin'
    })
      .then(parseAjaxJson)
      .then(function (payload) {
        var redirect = payload && payload.data && payload.data.redirect ? String(payload.data.redirect) : getCommunityMembershipUrl(email);
        window.location.assign(redirect);
      })
      .catch(function () {
        window.location.assign(getCommunityMembershipUrl(email));
      });
  }

  function setCommunityCvUploadStatus(root, message, state) {
    if (!root) {
      return;
    }

    root.querySelectorAll('[data-sffc-community-cv-upload-label]').forEach(function (label) {
      label.textContent = message || 'Upload CV';
    });

    root.querySelectorAll('[data-sffc-community-cv-upload-trigger]').forEach(function (trigger) {
      trigger.classList.toggle('is-loading', state === 'loading');
      trigger.classList.toggle('is-loaded', state === 'loaded');
      if ('disabled' in trigger) {
        trigger.disabled = state === 'loading';
      }
    });
  }

  function openCommunityCvUpload(root) {
    var input = root ? root.querySelector('[data-sffc-community-cv-upload-input]') : null;
    if (input && typeof input.click === 'function') {
      input.click();
    }
  }

  function uploadCommunityMatchCv(root, file) {
    if (!root || !file) {
      return Promise.reject(new Error('Choose a CV file first.'));
    }

    setCommunityCvUploadStatus(root, 'Reading CV...', 'loading');

    return parseFile(file)
      .catch(function () {
        return '';
      })
      .then(function (cvText) {
        var requestBody = new FormData();
        var normalizedText = String(cvText || '').trim();

        requestBody.append('action', 'sffc_crm_editorial_upload_match_cv');
        requestBody.append('nonce', getCommunityMatchCvUploadNonce(root));
        requestBody.append('file_name', file.name || 'resume');

        if (normalizedText) {
          requestBody.append('cv_text', normalizedText);
        } else {
          requestBody.append('cv_file', file);
        }

        return window.fetch((config.ajaxUrl || '/wp-admin/admin-ajax.php'), {
          method: 'POST',
          body: requestBody,
          credentials: 'same-origin'
        });
      })
      .then(parseAjaxJson)
      .then(function (payload) {
        if (!payload || !payload.success || !payload.data) {
          throw new Error((payload && payload.data && payload.data.message) || 'We could not read that CV.');
        }

        root.setAttribute('data-sffc-community-guest-cv-token', String(payload.data.token || ''));
        setStoredCommunityGuestCvToken(payload.data.token || '');
        setCommunityCvUploadStatus(root, 'CV uploaded', 'loaded');
        return requestCommunityFeed(root, { scrollToResults: false });
      })
      .catch(function (error) {
        setCommunityCvUploadStatus(root, 'Upload CV', '');
        window.alert((error && error.message) || 'We could not read that CV.');
        return null;
      });
  }

  function updateUrl(tab) {
    try {
      var url = new URL(window.location.href);
      url.searchParams.set('community_tab', tab);
      window.history.replaceState({}, '', url.toString());
    } catch (error) {
      // Ignore URL sync issues and keep the UI interactive.
    }
  }

  function getAutocompleteState(root) {
    if (!root.__sffcCommunityAutocompleteState) {
      root.__sffcCommunityAutocompleteState = {
        timerId: 0,
        requestId: 0,
        mode: 'contains',
        controller: null
      };
    }

    return root.__sffcCommunityAutocompleteState;
  }

  function setCommunityAutocompleteMode(root, mode) {
    var state = getAutocompleteState(root);
    var buttons = root ? root.querySelectorAll('[data-sffc-community-autocomplete-mode]') : [];
    var normalizedMode = mode === 'title' ? 'title' : 'contains';

    state.mode = normalizedMode;

    Array.prototype.forEach.call(buttons, function (button) {
      var isActive = String(button.getAttribute('data-sffc-community-autocomplete-mode') || '') === normalizedMode;
      button.classList.toggle('is-active', isActive);
      button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
  }

  function closeCommunityAutocomplete(root) {
    var results = root ? root.querySelector('[data-sffc-community-autocomplete-results]') : null;
    if (!results) {
      return;
    }

    results.hidden = true;
    results.innerHTML = '';
    results.classList.remove('is-loading');
  }

  function renderCommunityAutocompleteLoading(root) {
    var results = root ? root.querySelector('[data-sffc-community-autocomplete-results]') : null;
    if (!results) {
      return;
    }

    results.hidden = false;
    results.classList.add('is-loading');
    results.innerHTML =
      '<div class="sffc-community-editorial__finder-loading">' +
      '<span class="sffc-community-editorial__finder-loading-orb"></span>' +
      '<span>' + String((config.strings && config.strings.autocompleteLoading) || 'Searching MENA Careers...') + '</span>' +
      '</div>';
  }

  function performCommunityAutocomplete(root, term) {
    var results = root ? root.querySelector('[data-sffc-community-autocomplete-results]') : null;
    var state = getAutocompleteState(root);
    var requestBody;
    var requestId;

    if (!root || !results || !term || !config.autocompleteNonce) {
      return;
    }

    if (state.controller && typeof state.controller.abort === 'function') {
      state.controller.abort();
    }

    requestBody = new FormData();
    requestBody.append('action', 'sffc_crm_editorial_autocomplete_search');
    requestBody.append('nonce', config.autocompleteNonce || '');
    requestBody.append('term', term);
    requestBody.append('mode', state.mode || 'contains');

    state.requestId += 1;
    requestId = state.requestId;
    state.controller = window.AbortController ? new window.AbortController() : null;

    renderCommunityAutocompleteLoading(root);

    window.fetch((config.ajaxUrl || '/wp-admin/admin-ajax.php'), {
      method: 'POST',
      body: requestBody,
      credentials: 'same-origin',
      signal: state.controller ? state.controller.signal : undefined
    })
      .then(parseAjaxJson)
      .then(function (payload) {
        if (requestId !== state.requestId) {
          return;
        }

        if (!payload || !payload.success) {
          throw new Error((payload && payload.data && payload.data.message) || 'Unable to search MENA Careers right now.');
        }

        results.classList.remove('is-loading');
        results.hidden = false;
        results.innerHTML = payload.data && payload.data.html ? String(payload.data.html) : '';

        if (!results.innerHTML.trim()) {
          results.innerHTML =
            '<div class="sffc-community-editorial__finder-empty">' +
            '<strong>' + String((config.strings && config.strings.autocompleteEmpty) || 'No matching jobs found yet.') + '</strong>' +
            '</div>';
        }
      })
      .catch(function (error) {
        if (error && error.name === 'AbortError') {
          return;
        }

        if (requestId !== state.requestId) {
          return;
        }

        results.classList.remove('is-loading');
        results.hidden = false;
        results.innerHTML =
          '<div class="sffc-community-editorial__finder-empty">' +
          '<strong>' + String((config.strings && config.strings.autocompleteEmpty) || 'No matching jobs found yet.') + '</strong>' +
          '</div>';
      });
  }

  function getStandaloneSearchState(widget) {
    if (!widget.__sffcCommunityStandaloneSearchState) {
      widget.__sffcCommunityStandaloneSearchState = {
        timerId: 0,
        requestId: 0,
        category: 'jobs',
        controller: null
      };
    }

    return widget.__sffcCommunityStandaloneSearchState;
  }

  function closeStandaloneSearch(widget) {
    var results = widget ? widget.querySelector('[data-sffc-community-standalone-results]') : null;
    if (!results) {
      return;
    }

    results.hidden = true;
    results.innerHTML = '';
    results.classList.remove('is-loading');
  }

  function setStandaloneSearchLoading(widget) {
    var results = widget ? widget.querySelector('[data-sffc-community-standalone-results]') : null;
    if (!results) {
      return;
    }

    results.hidden = false;
    results.classList.add('is-loading');
    results.innerHTML =
      '<div class="sffc-community-editorial-search__loading">' +
      '<span></span>' +
      '<em>' + String((config.strings && config.strings.autocompleteLoading) || 'Searching MENA Careers...') + '</em>' +
      '</div>';
  }

  function setStandaloneSearchCategory(widget, category) {
    var state = getStandaloneSearchState(widget);
    var normalizedCategory = ['jobs', 'resources'].indexOf(category) !== -1 ? category : 'jobs';

    state.category = normalizedCategory;
    widget.querySelectorAll('[data-sffc-community-standalone-category]').forEach(function (button) {
      var isActive = button.getAttribute('data-sffc-community-standalone-category') === normalizedCategory;
      button.classList.toggle('is-active', isActive);
      button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
  }

  function performStandaloneSearch(widget, term) {
    var state = getStandaloneSearchState(widget);
    var results = widget ? widget.querySelector('[data-sffc-community-standalone-results]') : null;
    var requestBody;
    var requestId;

    if (!widget || !results || !term || !config.standaloneSearchNonce || !window.fetch) {
      return;
    }

    if (state.controller && typeof state.controller.abort === 'function') {
      state.controller.abort();
    }

    requestBody = new FormData();
    requestBody.append('action', 'sffc_crm_editorial_standalone_search');
    requestBody.append('nonce', config.standaloneSearchNonce || '');
    requestBody.append('term', term);
    requestBody.append('category', state.category || 'jobs');
    requestBody.append('mode', 'contains');

    state.requestId += 1;
    requestId = state.requestId;
    state.controller = window.AbortController ? new window.AbortController() : null;

    setStandaloneSearchLoading(widget);

    window.fetch((config.ajaxUrl || '/wp-admin/admin-ajax.php'), {
      method: 'POST',
      body: requestBody,
      credentials: 'same-origin',
      signal: state.controller ? state.controller.signal : undefined
    })
      .then(parseAjaxJson)
      .then(function (payload) {
        if (requestId !== state.requestId) {
          return;
        }

        if (!payload || !payload.success) {
          throw new Error((payload && payload.data && payload.data.message) || 'Unable to search MENA Careers right now.');
        }

        results.classList.remove('is-loading');
        results.hidden = false;
        results.innerHTML = payload.data && payload.data.html ? String(payload.data.html) : '';
      })
      .catch(function (error) {
        if (requestId !== state.requestId) {
          return;
        }

        if (error && error.name === 'AbortError') {
          return;
        }

        results.classList.remove('is-loading');
        results.hidden = false;
        results.innerHTML =
          '<div class="sffc-community-editorial-search__empty">' +
          '<strong>' + String((config.strings && config.strings.autocompleteEmpty) || 'No matching results found yet.') + '</strong>' +
          '</div>';
      });
  }

  function focusCommunityPostResult(root, postId, groupSlug) {
    var normalizedPostId = parseInt(postId || '0', 10);
    var normalizedGroup = String(groupSlug || '').trim();

    if (!root || !normalizedPostId) {
      return Promise.resolve(false);
    }

    activateTab(root, 'posts');
    if (normalizedGroup) {
      activateGroupFilter(root, normalizedGroup);
    }

    return requestCommunityFeed(root, { scrollToResults: false, batchSize: 80, focusPostId: normalizedPostId }).then(function () {
      var postsWrap = root.querySelector('.sffc-community-editorial__posts');
      var post = postsWrap ? postsWrap.querySelector('[data-sffc-community-post-id="' + normalizedPostId + '"]') : null;

      if (!post || !postsWrap) {
        return false;
      }

      var postShell = post.closest('.sffc-community-editorial__post-shell');
      var focusTarget = postShell || post;
      focusTarget.classList.add('is-search-focus');
      window.setTimeout(function () {
        focusTarget.classList.remove('is-search-focus');
      }, 1800);

      if (typeof focusTarget.scrollIntoView === 'function') {
        window.setTimeout(function () {
          focusTarget.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 40);
      }

      return true;
    });
  }

  function buildStandaloneTerminalUrl(widget, type, postId, groupSlug, recruiterId) {
    var base = widget ? String(widget.getAttribute('data-sffc-community-search-result-url') || '') : '';
    var url;

    if (!base) {
      base = config.terminalUrl || '/terminal/';
    }

    try {
      url = new URL(base, window.location.origin);
      if (type === 'recruiter') {
        url.searchParams.set('community_tab', 'posts');
        if (recruiterId) {
          url.searchParams.set('sffc_community_recruiter', String(recruiterId));
        }
      } else {
        url.searchParams.set('community_tab', 'posts');
        if (postId) {
          url.searchParams.set('sffc_community_focus_post', String(postId));
        }
        if (groupSlug) {
          url.searchParams.set('sffc_community_group', String(groupSlug));
        }
      }
      return url.toString();
    } catch (error) {
      return base;
    }
  }

  function buildStandaloneTerminalBaseUrl(widget) {
    var base = widget ? String(widget.getAttribute('data-sffc-community-search-result-url') || '') : '';

    if (!base) {
      base = config.terminalUrl || '/terminal/';
    }

    try {
      return new URL(base, window.location.origin).toString();
    } catch (error) {
      return base;
    }
  }

  function handleStandaloneSearchResult(widget, result) {
    var type = String(result.getAttribute('data-result-type') || 'job');
    var url = String(result.getAttribute('data-result-url') || result.getAttribute('href') || '');
    var postId = parseInt(result.getAttribute('data-post-id') || '0', 10);
    var groupSlug = String(result.getAttribute('data-group-slug') || '');
    var recruiterId = parseInt(result.getAttribute('data-recruiter-id') || '0', 10);
    var root = document.querySelector('[data-sffc-community-editorial]');
    if (type === 'resource') {
      if (url) {
        window.location.href = url;
      }
      return;
    }

    if (type === 'recruiter') {
      if (root) {
        activateTab(root, 'posts');
        closeStandaloneSearch(widget);
        return;
      }

      window.location.href = buildStandaloneTerminalUrl(widget, type, postId, groupSlug, recruiterId);
      return;
    }

    if (root) {
      focusCommunityPostResult(root, postId, groupSlug).then(function (focused) {
        if (!focused) {
          window.location.href = buildStandaloneTerminalUrl(widget, type, postId, groupSlug, recruiterId);
        }
      });
      closeStandaloneSearch(widget);
      return;
    }

    window.location.href = buildStandaloneTerminalUrl(widget, type, postId, groupSlug, recruiterId);
  }

  function applyInitialCommunitySearchFocus(root) {
    var url;
    var postId;
    var groupSlug;

    if (!root || !window.URLSearchParams) {
      return;
    }

    try {
      url = new URL(window.location.href);
    } catch (error) {
      return;
    }

    postId = parseInt(url.searchParams.get('sffc_community_focus_post') || '0', 10);
    groupSlug = String(url.searchParams.get('sffc_community_group') || '');
    if (!postId) {
      return;
    }

    window.setTimeout(function () {
      focusCommunityPostResult(root, postId, groupSlug);
    }, 120);
  }

  function initStandaloneCommunitySearch(widget) {
    var input = widget ? widget.querySelector('[data-sffc-community-standalone-input]') : null;
    var form = widget ? widget.querySelector('[data-sffc-community-standalone-form]') : null;
    var state;

    if (!widget || !input || widget.__sffcCommunityStandaloneSearchInitialized) {
      return;
    }

    widget.__sffcCommunityStandaloneSearchInitialized = true;
    state = getStandaloneSearchState(widget);
    setStandaloneSearchCategory(widget, state.category || 'jobs');

    widget.querySelectorAll('[data-sffc-community-standalone-category]').forEach(function (button) {
      button.addEventListener('click', function () {
        var term = String(input.value || '').trim();
        setStandaloneSearchCategory(widget, button.getAttribute('data-sffc-community-standalone-category') || 'jobs');
        if (term.length >= 2) {
          performStandaloneSearch(widget, term);
        }
      });
    });

    input.addEventListener('input', function () {
      var term = String(input.value || '').trim();
      window.clearTimeout(state.timerId);
      if (term.length < 2) {
        closeStandaloneSearch(widget);
        return;
      }

      state.timerId = window.setTimeout(function () {
        performStandaloneSearch(widget, term);
      }, 220);
    });

    input.addEventListener('focus', function () {
      var term = String(input.value || '').trim();
      if (term.length >= 2) {
        performStandaloneSearch(widget, term);
      }
    });

    input.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        closeStandaloneSearch(widget);
      }
    });

    if (form) {
      form.addEventListener('submit', function (event) {
        event.preventDefault();
        var firstResult = widget.querySelector('[data-sffc-community-standalone-result]');
        if (firstResult) {
          handleStandaloneSearchResult(widget, firstResult);
          return;
        }

        if (String(input.value || '').trim().length >= 2) {
          performStandaloneSearch(widget, String(input.value || '').trim());
        }
      });
    }

    widget.addEventListener('click', function (event) {
      var result = event.target.closest('[data-sffc-community-standalone-result]');
      if (!result || !widget.contains(result)) {
        return;
      }

      event.preventDefault();
      handleStandaloneSearchResult(widget, result);
    });

    document.addEventListener('click', function (event) {
      var target = event.target;
      if (target && target.closest && target.closest('[data-sffc-community-editorial-search]')) {
        return;
      }

      closeStandaloneSearch(widget);
    });
  }

  function findCommunityFilterLauncherTarget(launcher) {
    var selector = launcher ? String(launcher.getAttribute('data-sffc-community-filter-launcher-target') || '').trim() : '';
    var root = null;

    if (selector) {
      try {
        root = document.querySelector(selector);
      } catch (error) {
        root = null;
      }
    }

    if (!root) {
      root = document.querySelector('[data-sffc-community-editorial]');
    }

    return root;
  }

  function setCommunityLauncherOptionState(root, key, value) {
    var normalizedValue = value || 'all';
    var selector = '[data-sffc-community-' + key + '-filter]';
    var matchedOption = null;
    var state = getCommunityFilterState(root);

    root.querySelectorAll(selector).forEach(function (option) {
      var optionValue = option.getAttribute('data-sffc-community-' + key + '-filter') || 'all';
      var isActive = optionValue === normalizedValue;
      option.classList.toggle('is-active', isActive);
      option.setAttribute('aria-pressed', isActive ? 'true' : 'false');
      if (isActive) {
        matchedOption = option;
      }
    });

    state[key] = normalizedValue;
    updateToolbarFilterLabel(
      root,
      key,
      normalizedValue === 'all'
        ? ''
        : (matchedOption ? (matchedOption.getAttribute('data-sffc-community-location-label') || matchedOption.textContent.trim()) : normalizedValue)
    );
  }

  function applyCommunityFilterLauncher(launcher) {
    var root = findCommunityFilterLauncherTarget(launcher);
    var searchInput = launcher ? launcher.querySelector('[data-sffc-community-filter-launcher-search]') : null;
    var senioritySelect = launcher ? launcher.querySelector('[data-sffc-community-filter-launcher-seniority]') : null;
    var locationSelect = launcher ? launcher.querySelector('[data-sffc-community-filter-launcher-location]') : null;
    var toolbarSearch = root ? root.querySelector('[data-sffc-community-search-input]') : null;
    var state;

    if (!root) {
      return;
    }

    state = getCommunityFilterState(root);
    state.search = searchInput ? String(searchInput.value || '').trim() : '';
    setCommunityLauncherOptionState(root, 'seniority', senioritySelect ? (senioritySelect.value || 'all') : 'all');
    setCommunityLauncherOptionState(root, 'location', locationSelect ? (locationSelect.value || 'all') : 'all');

    if (toolbarSearch) {
      toolbarSearch.value = state.search;
    }

    closeCommunityFilterPanels(root, '');
    activateTab(root, 'posts');
    requestCommunityFeed(root, { scrollToResults: true, batchSize: 80 });
  }

  function initCommunityFilterLauncher(launcher) {
    var form = launcher ? launcher.querySelector('[data-sffc-community-filter-launcher-form]') : null;

    if (!launcher || !form || launcher.__sffcCommunityFilterLauncherInitialized) {
      return;
    }

    launcher.__sffcCommunityFilterLauncherInitialized = true;
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      applyCommunityFilterLauncher(launcher);
    });
  }

  function initJobsCarousel(carousel) {
    var track = carousel ? carousel.querySelector('[data-sffc-jobs-carousel-track]') : null;
    var previous = carousel ? carousel.querySelector('[data-sffc-jobs-carousel-prev]') : null;
    var next = carousel ? carousel.querySelector('[data-sffc-jobs-carousel-next]') : null;

    if (!carousel || !track || carousel.__sffcJobsCarouselInitialized) {
      return;
    }

    carousel.__sffcJobsCarouselInitialized = true;

    function scrollByCard(direction) {
      var card = track.querySelector('.sffc-jobs-carousel__card');
      var distance = card ? card.getBoundingClientRect().width + 20 : track.clientWidth;
      track.scrollBy({
        left: distance * direction,
        behavior: 'smooth'
      });
    }

    if (previous) {
      previous.addEventListener('click', function () {
        scrollByCard(-1);
      });
    }

    if (next) {
      next.addEventListener('click', function () {
        scrollByCard(1);
      });
    }
  }

  function normalizeCommunityToken(value) {
    return String(value || '')
      .toLowerCase()
      .replace(/[_-]+/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();
  }

  function filterCommunityDropdownOptions(searchInput) {
    var panel;
    var query;
    var options;

    if (!searchInput) {
      return;
    }

    panel = searchInput.closest('[data-sffc-community-filter-panel]');
    if (!panel) {
      return;
    }

    query = normalizeCommunityToken(searchInput.value || '');
    options = panel.querySelectorAll(
      '[data-sffc-community-filter-option-search-target], ' +
      '.sffc-community-editorial__filter-option, ' +
      '.sffc-community-editorial__tracker-filter-option'
    );
    options.forEach(function (option) {
      var label = normalizeCommunityToken(
        option.getAttribute('data-sffc-community-tracker-label') ||
        option.getAttribute('data-sffc-community-filter-label') ||
        option.textContent ||
        ''
      );
      var shouldHide = !!query && label.indexOf(query) === -1;
      option.hidden = shouldHide;
      option.style.display = shouldHide ? 'none' : '';
    });
  }

  function getApplyForMePanel(root) {
    return root ? root.querySelector('[data-sffc-apply-for-me-panel]') : null;
  }

  function getApplyForMeStep(panel, index) {
    return panel ? panel.querySelector('[data-sffc-apply-step="' + String(index) + '"]') : null;
  }

  function getApplyForMeCurrentStep(panel) {
    var current = panel ? panel.querySelector('[data-sffc-apply-step].is-active') : null;
    var index = current ? parseInt(current.getAttribute('data-sffc-apply-step') || '0', 10) : 0;
    return isNaN(index) ? 0 : index;
  }

  function getApplyForMeLastStep(panel) {
    var steps = panel ? Array.prototype.slice.call(panel.querySelectorAll('[data-sffc-apply-step]')) : [];
    return steps.reduce(function (max, step) {
      var index = parseInt(step.getAttribute('data-sffc-apply-step') || '0', 10);
      return isNaN(index) ? max : Math.max(max, index);
    }, 0);
  }

  function isApplyForMeMultiStep(step) {
    var key = step ? (step.getAttribute('data-sffc-apply-step-key') || '') : '';
    return ['targets', 'location', 'mandate'].indexOf(key) !== -1;
  }

  function setApplyForMeStep(panel, nextIndex) {
    var lastStep = getApplyForMeLastStep(panel);
    var boundedIndex = Math.max(0, Math.min(lastStep, parseInt(nextIndex || '0', 10) || 0));

    if (!panel) {
      return;
    }

    panel.querySelectorAll('[data-sffc-apply-step]').forEach(function (step) {
      var index = parseInt(step.getAttribute('data-sffc-apply-step') || '0', 10);
      var active = index === boundedIndex;
      step.hidden = !active;
      step.classList.toggle('is-active', active);
    });

    panel.querySelectorAll('[data-sffc-apply-step-jump]').forEach(function (trigger) {
      var index = parseInt(trigger.getAttribute('data-sffc-apply-step-jump') || '0', 10);
      trigger.classList.toggle('is-active', index === boundedIndex);
      trigger.classList.toggle('is-complete', index < boundedIndex);
    });

    var back = panel.querySelector('[data-sffc-apply-back]');
    var next = panel.querySelector('[data-sffc-apply-next]');
    if (back) {
      back.hidden = boundedIndex === 0;
    }
    if (next) {
      next.textContent = boundedIndex >= lastStep - 1 ? 'Continue with Senna' : 'Continue';
      next.hidden = boundedIndex >= lastStep;
    }

    if (boundedIndex === lastStep) {
      renderApplyForMeSummary(panel);
    }
  }

  function collectApplyForMeMandate(panel) {
    var data = {};

    if (!panel) {
      return data;
    }

    panel.querySelectorAll('[data-sffc-apply-step]').forEach(function (step) {
      var key = step.getAttribute('data-sffc-apply-step-key') || '';
      if (!key || key === 'review') {
        return;
      }

      data[key] = {
        options: Array.prototype.slice.call(step.querySelectorAll('[data-sffc-apply-option].is-selected')).map(function (option) {
          return option.getAttribute('data-sffc-apply-option-label') || option.textContent.trim();
        }),
        fields: {}
      };

      step.querySelectorAll('[data-sffc-apply-field]').forEach(function (field) {
        var name = field.getAttribute('name') || '';
        if (name) {
          data[key].fields[name] = String(field.value || '').trim();
          if (name === 'stored_cv_url' && field.tagName === 'SELECT') {
            var selectedOption = field.options && field.selectedIndex >= 0 ? field.options[field.selectedIndex] : null;
            data[key].fields.stored_cv_name = selectedOption ? String(selectedOption.getAttribute('data-cv-name') || selectedOption.textContent || '').trim() : '';
          }
        }
      });

      step.querySelectorAll('[data-sffc-apply-file]').forEach(function (field) {
        var name = field.getAttribute('name') || '';
        if (name && field.files && field.files.length) {
          data[key].fields[name] = field.files[0].name || 'Uploaded CV';
        }
      });
    });

    return data;
  }

  function hasApplyForMeStepInput(step) {
    var selected = step ? step.querySelector('[data-sffc-apply-option].is-selected') : null;
    var textValue = false;

    if (!step) {
      return false;
    }

    step.querySelectorAll('[data-sffc-apply-field]').forEach(function (field) {
      if (String(field.value || '').trim() !== '') {
        textValue = true;
      }
    });

    step.querySelectorAll('[data-sffc-apply-file]').forEach(function (field) {
      if (field.files && field.files.length) {
        textValue = true;
      }
    });

    return !!selected || textValue;
  }

  function validateApplyForMeStep(panel) {
    var step = getApplyForMeStep(panel, getApplyForMeCurrentStep(panel));
    var feedback = panel ? panel.querySelector('[data-sffc-apply-feedback]') : null;

    if (!step || step.getAttribute('data-sffc-apply-step-key') === 'review') {
      return true;
    }

    if (hasApplyForMeStepInput(step)) {
      if (feedback) {
        feedback.hidden = true;
        feedback.textContent = '';
      }
      return true;
    }

    if (feedback) {
      feedback.hidden = false;
      feedback.textContent = 'Choose an option or add a note before continuing.';
    }
    return false;
  }

  function validateApplyForMeMandateComplete(panel) {
    var feedback = panel ? panel.querySelector('[data-sffc-apply-feedback]') : null;
    var missingIndex = -1;

    if (!panel) {
      return false;
    }

    panel.querySelectorAll('[data-sffc-apply-step]').forEach(function (step) {
      var key = step.getAttribute('data-sffc-apply-step-key') || '';
      var index = parseInt(step.getAttribute('data-sffc-apply-step') || '-1', 10);
      if (missingIndex !== -1 || key === 'review') {
        return;
      }
      if (!hasApplyForMeStepInput(step)) {
        missingIndex = isNaN(index) ? 0 : index;
      }
    });

    if (missingIndex === -1) {
      if (feedback) {
        feedback.hidden = true;
        feedback.textContent = '';
        feedback.classList.remove('is-error');
      }
      return true;
    }

    setApplyForMeStep(panel, missingIndex);
    if (feedback) {
      feedback.hidden = false;
      feedback.classList.remove('is-success');
      feedback.classList.add('is-error');
      feedback.textContent = 'Complete this section before saving your Get Hired brief.';
    }
    return false;
  }

  function renderApplyForMeSummary(panel) {
    var summary = panel ? panel.querySelector('[data-sffc-apply-summary]') : null;
    var mandate = collectApplyForMeMandate(panel);
    var labels = {
      goal: 'Goal',
      targets: 'Target roles',
      location: 'Private equity markets',
      working: 'Working and visa rules',
      level: 'Seniority',
      compensation: 'Compensation',
      mandate: 'Application criteria',
      cv: 'CV for applications'
    };

    if (!summary) {
      return;
    }

    summary.innerHTML = Object.keys(labels).map(function (key) {
      var section = mandate[key] || { options: [], fields: {} };
      var options = section.options.length
        ? section.options.map(function (option) {
          return '<em>✓</em><span>' + escapeCommunityHtml(option) + '</span>';
        }).join('')
        : '<span>Not specified</span>';
      var fields = Object.keys(section.fields || {}).filter(function (fieldKey) {
        return section.fields[fieldKey] !== '' && ['stored_cv_url', 'cv_file'].indexOf(fieldKey) === -1;
      }).map(function (fieldKey) {
        var fieldLabel = fieldKey === 'stored_cv_name' ? 'selected CV' : fieldKey.replace(/_/g, ' ');
        return '<span><strong>' + escapeCommunityHtml(fieldLabel) + '</strong><small>' + escapeCommunityHtml(section.fields[fieldKey]) + '</small></span>';
      }).join('');

      return (
        '<article class="sffc-community-editorial__apply-for-me-summary-card">' +
          '<span>' + escapeCommunityHtml(labels[key]) + '</span>' +
          '<strong>' + options + '</strong>' +
          (fields ? '<div>' + fields + '</div>' : '') +
        '</article>'
      );
    }).join('');

    try {
      window.localStorage.setItem('sffcApplyForMeMandate', JSON.stringify(mandate));
    } catch (error) {
      // Storage is optional; the visible summary remains the source of truth.
    }
  }

  function submitApplyForMeMandate(root, panel, trigger) {
    var feedback = panel ? panel.querySelector('[data-sffc-apply-feedback]') : null;
    var mandate = collectApplyForMeMandate(panel);
    var body;
    var originalText;

    if (!root || !panel || !window.fetch) {
      return;
    }

    if (root.getAttribute('data-sffc-community-premium-access') !== 'true' && !config.hasPremiumAccess) {
      return;
    }

    if (!validateApplyForMeMandateComplete(panel)) {
      return;
    }

    renderApplyForMeSummary(panel);
    body = new FormData();
    body.append('action', 'sffc_crm_editorial_save_apply_for_me_mandate');
    body.append('nonce', config.applyForMeMandateNonce || '');
    body.append('mandate', JSON.stringify(mandate));

    var selectedCv = panel.querySelector('[data-sffc-apply-cv-select]');
    if (selectedCv) {
      var selectedOption = selectedCv.options && selectedCv.selectedIndex >= 0 ? selectedCv.options[selectedCv.selectedIndex] : null;
      body.append('stored_cv_url', String(selectedCv.value || '').trim());
      body.append('stored_cv_name', selectedOption ? String(selectedOption.getAttribute('data-cv-name') || selectedOption.textContent || '').trim() : '');
    }
    var cvFile = panel.querySelector('[data-sffc-apply-file]');
    if (cvFile && cvFile.files && cvFile.files.length) {
      body.append('cv_file', cvFile.files[0]);
    }

    if (feedback) {
      feedback.hidden = true;
      feedback.textContent = '';
      feedback.classList.remove('is-success', 'is-error');
    }

    if (trigger) {
      originalText = trigger.textContent;
      trigger.textContent = 'Saving...';
      trigger.setAttribute('aria-disabled', 'true');
      trigger.classList.add('is-saving');
    }

    window.fetch((config.ajaxUrl || '/wp-admin/admin-ajax.php'), {
      method: 'POST',
      body: body,
      credentials: 'same-origin'
    })
      .then(parseAjaxJson)
      .then(function (payload) {
        if (!payload || !payload.success) {
          var redirect = payload && payload.data && payload.data.redirect;
          if (redirect) {
            window.location.href = redirect;
            return null;
          }
          throw new Error((payload && payload.data && payload.data.message) || 'We could not save this Get Hired brief.');
        }

        if (feedback) {
          feedback.hidden = false;
          feedback.classList.remove('is-error');
          feedback.classList.add('is-success');
          feedback.textContent = (payload.data && payload.data.message) || 'Your Get Hired brief has been saved.';
        }
        if (trigger) {
          trigger.textContent = 'Saved to profile';
        }
        return payload;
      })
      .catch(function (error) {
        if (feedback) {
          feedback.hidden = false;
          feedback.classList.remove('is-success');
          feedback.classList.add('is-error');
          feedback.textContent = (error && error.message) || 'We could not save this Get Hired brief.';
        }
        if (trigger && originalText) {
          trigger.textContent = originalText;
        }
      })
      .finally(function () {
        if (trigger) {
          trigger.removeAttribute('aria-disabled');
          trigger.classList.remove('is-saving');
        }
      });
  }

  function initializeApplyForMePanel(root) {
    var panel = getApplyForMePanel(root);

    if (!panel || panel.__sffcApplyForMeInitialized) {
      return;
    }

    panel.__sffcApplyForMeInitialized = true;
    setApplyForMeStep(panel, 0);
  }

  function trackCommunityCompanyClick(root, trigger) {
    var companyName;
    var companyLogo;
    var requestBody;

    if (!root || !trigger || !window.fetch) {
      return;
    }

    companyName = String(trigger.getAttribute('data-company-name') || '').trim();
    companyLogo = String(trigger.getAttribute('data-company-logo') || '').trim();

    if (!companyName) {
      return;
    }

    requestBody = new FormData();
    requestBody.append('action', 'sffc_crm_editorial_track_company_click');
    requestBody.append('nonce', config.crmNonce || '');
    requestBody.append('company_name', companyName);
    requestBody.append('logo', companyLogo);

    window.fetch((config.ajaxUrl || '/wp-admin/admin-ajax.php'), {
      method: 'POST',
      body: requestBody,
      credentials: 'same-origin',
      keepalive: true
    }).catch(function () {
      return null;
    });
  }

  function getCommunityFilterState(root) {
    if (!root.__sffcCommunityFilterState) {
      var toolbar = root.querySelector('.sffc-community-editorial__filter-toolbar');
      root.__sffcCommunityFilterState = {
        search: '',
        location: toolbar ? (toolbar.getAttribute('data-sffc-community-default-location') || 'all') : 'all',
        seniority: toolbar ? (toolbar.getAttribute('data-sffc-community-default-seniority') || 'all') : 'all',
        qualification: toolbar ? (toolbar.getAttribute('data-sffc-community-default-qualification') || 'all') : 'all',
        postedDate: toolbar ? (toolbar.getAttribute('data-sffc-community-default-posted-date') || 'all') : 'all',
        company: toolbar ? (toolbar.getAttribute('data-sffc-community-default-company') || 'all') : 'all',
        sector: toolbar ? (toolbar.getAttribute('data-sffc-community-default-sector') || 'all') : 'all',
        workType: toolbar ? (toolbar.getAttribute('data-sffc-community-default-work-type') || 'all') : 'all',
        match: toolbar ? (toolbar.getAttribute('data-sffc-community-default-match') || 'all') : 'all',
        recency: 'all',
        trackers: []
      };
    }
    return root.__sffcCommunityFilterState;
  }

  function moveCommunityFilterToolbarBelowHeader(root) {
    var header = root ? root.querySelector('.sffc-community-editorial__header') : null;
    var toolbar = root ? root.querySelector('.sffc-community-editorial__filter-toolbar') : null;
    if (!header || !toolbar || toolbar.previousElementSibling === header) {
      return;
    }
    header.insertAdjacentElement('afterend', toolbar);
  }

  function setupCommunityFilterToolbarScroll(root) {
    var toolbar = root ? root.querySelector('.sffc-community-editorial__filter-toolbar') : null;
    var nav = root ? root.querySelector('.sffc-community-editorial__nav') : null;
    if ((!toolbar && !nav) || root.__sffcCommunityScrollReady) {
      return;
    }

    root.__sffcCommunityScrollReady = true;
    var lastY = window.scrollY || 0;
    var ticking = false;

    var update = function () {
      var currentY = window.scrollY || 0;
      var openPanel = root.querySelector('[data-sffc-community-filter-panel]:not([hidden])');
      var isMobileViewport = (window.innerWidth || document.documentElement.clientWidth || 0) <= 767
        || (window.matchMedia && window.matchMedia('(hover: none) and (pointer: coarse)').matches);
      var shouldCollapse = !openPanel && currentY > 140 && currentY > lastY + 4;
      var shouldExpand = currentY < lastY - 4 || currentY < 80 || !!openPanel;

      if (toolbar && shouldCollapse) {
        toolbar.classList.add('is-collapsed');
      } else if (toolbar && shouldExpand) {
        toolbar.classList.remove('is-collapsed');
      }

      if (nav && isMobileViewport) {
        if (shouldCollapse) {
          nav.classList.add('is-hidden-on-scroll');
        } else if (shouldExpand) {
          nav.classList.remove('is-hidden-on-scroll');
        }
      } else if (nav) {
        nav.classList.remove('is-hidden-on-scroll');
      }

      if (openPanel) {
        positionCommunityFilterPanel(root, openPanel.getAttribute('data-sffc-community-filter-panel') || '');
      }

      lastY = currentY;
      ticking = false;
    };

    var requestUpdate = function () {
      if (ticking) {
        return;
      }
      ticking = true;
      window.requestAnimationFrame(update);
    };

    window.addEventListener('scroll', requestUpdate, { passive: true });
    window.addEventListener('resize', requestUpdate);
  }

  function getCommunityDiscoveryFilterState(root) {
    if (!root.__sffcCommunityDiscoveryFilterState) {
      root.__sffcCommunityDiscoveryFilterState = {
        search: '',
        location: 'all',
        industry: 'all',
        role: 'all',
        visibleCount: 18
      };
    }
    return root.__sffcCommunityDiscoveryFilterState;
  }

  function initializeCommunityDiscoveryControls(root) {
    var discoverySearchInput;
    var discoveryLocationSelect;
    var discoveryIndustrySelect;
    var discoveryRoleSelect;

    if (!root) {
      return;
    }

    discoverySearchInput = root.querySelector('[data-sffc-community-discovery-search]');
    if (discoverySearchInput && discoverySearchInput.getAttribute('data-sffc-community-discovery-ready') !== 'true') {
      discoverySearchInput.setAttribute('data-sffc-community-discovery-ready', 'true');
      discoverySearchInput.addEventListener('input', function () {
        var discoveryState = getCommunityDiscoveryFilterState(root);
        discoveryState.search = discoverySearchInput.value || '';
        discoveryState.visibleCount = 18;
        applyCommunityDiscoveryFilters(root);
      });
    }

    discoveryLocationSelect = root.querySelector('[data-sffc-community-discovery-location]');
    if (discoveryLocationSelect && discoveryLocationSelect.getAttribute('data-sffc-community-discovery-ready') !== 'true') {
      discoveryLocationSelect.setAttribute('data-sffc-community-discovery-ready', 'true');
      discoveryLocationSelect.addEventListener('change', function () {
        var discoveryState = getCommunityDiscoveryFilterState(root);
        discoveryState.location = discoveryLocationSelect.value || 'all';
        discoveryState.visibleCount = 18;
        applyCommunityDiscoveryFilters(root);
      });
    }

    discoveryIndustrySelect = root.querySelector('[data-sffc-community-discovery-industry]');
    if (discoveryIndustrySelect && discoveryIndustrySelect.getAttribute('data-sffc-community-discovery-ready') !== 'true') {
      discoveryIndustrySelect.setAttribute('data-sffc-community-discovery-ready', 'true');
      discoveryIndustrySelect.addEventListener('change', function () {
        var discoveryState = getCommunityDiscoveryFilterState(root);
        discoveryState.industry = discoveryIndustrySelect.value || 'all';
        discoveryState.visibleCount = 18;
        applyCommunityDiscoveryFilters(root);
      });
    }

    discoveryRoleSelect = root.querySelector('[data-sffc-community-discovery-role]');
    if (discoveryRoleSelect && discoveryRoleSelect.getAttribute('data-sffc-community-discovery-ready') !== 'true') {
      discoveryRoleSelect.setAttribute('data-sffc-community-discovery-ready', 'true');
      discoveryRoleSelect.addEventListener('change', function () {
        var discoveryState = getCommunityDiscoveryFilterState(root);
        discoveryState.role = discoveryRoleSelect.value || 'all';
        discoveryState.visibleCount = 18;
        applyCommunityDiscoveryFilters(root);
      });
    }

    applyCommunityDiscoveryFilters(root);
  }

  function applyCommunityDiscoveryFilters(root) {
    if (!root) {
      return;
    }

    var state = getCommunityDiscoveryFilterState(root);
    var cards = Array.prototype.slice.call(root.querySelectorAll('[data-sffc-community-discovery-card]'));
    var loadMoreButton = root.querySelector('[data-sffc-community-discovery-load-more]');
    var searchNeedle = normalizeCommunityToken(state.search);
    var matchedCards = [];
    var hasRemoteMore = !!(loadMoreButton && loadMoreButton.getAttribute('data-sffc-community-discovery-has-more') === 'true');

    cards.forEach(function (card) {
      var searchIndex = normalizeCommunityToken(card.getAttribute('data-sffc-community-discovery-search') || '');
      var locationValue = normalizeCommunityToken(card.getAttribute('data-sffc-community-discovery-location') || '');
      var industriesValue = normalizeCommunityToken(card.getAttribute('data-sffc-community-discovery-industries') || '');
      var rolesValue = normalizeCommunityToken(card.getAttribute('data-sffc-community-discovery-roles') || '');
      var searchTokens = searchNeedle ? searchNeedle.split(' ').filter(Boolean) : [];
      var matchesSearch = !searchNeedle
        || searchIndex.indexOf(searchNeedle) !== -1
        || searchTokens.every(function (token) {
          return searchIndex.indexOf(token) !== -1;
        });
      var locationNeedle = normalizeCommunityToken(state.location);
      var industryNeedle = normalizeCommunityToken(state.industry);
      var roleNeedle = normalizeCommunityToken(state.role);
      var matchesLocation = state.location === 'all'
        || (locationNeedle && (locationValue.indexOf(locationNeedle) !== -1 || locationNeedle.indexOf(locationValue) !== -1));
      var matchesIndustry = state.industry === 'all'
        || (industryNeedle && industriesValue.indexOf(industryNeedle) !== -1);
      var matchesRole = state.role === 'all'
        || (roleNeedle && rolesValue.indexOf(roleNeedle) !== -1);
      var isMatch = matchesSearch && matchesLocation && matchesIndustry && matchesRole;

      card.dataset.sffcCommunityDiscoveryMatch = isMatch ? '1' : '0';
      card.hidden = true;
      if (isMatch) {
        matchedCards.push(card);
      }
    });

    matchedCards.slice(0, state.visibleCount).forEach(function (card) {
      card.hidden = false;
    });

    if (loadMoreButton) {
      loadMoreButton.hidden = !(hasRemoteMore || matchedCards.length > state.visibleCount);
      loadMoreButton.disabled = false;
    }
  }

  function doesPostMatchLocations(post, locations) {
    var locationValue = normalizeCommunityToken(post.getAttribute('data-sffc-community-post-location'));
    if (!locationValue || !locations.length) {
      return false;
    }

    return locations.some(function (location) {
      var needle = normalizeCommunityToken(location);
      return needle && (locationValue.indexOf(needle) !== -1 || needle.indexOf(locationValue) !== -1);
    });
  }

  function doesPostMatchSeniority(post, seniorities) {
    var seniorityValue = normalizeCommunityToken(post.getAttribute('data-sffc-community-post-seniority-filter') || post.getAttribute('data-sffc-community-post-seniority'));
    if (!seniorityValue || !seniorities.length) {
      return false;
    }

    return seniorities.some(function (seniority) {
      return seniorityValue === normalizeCommunityToken(seniority);
    });
  }

  function doesPostMatchQualifications(post, qualifications) {
    var qualificationValues = (post.getAttribute('data-sffc-community-post-qualifications') || '')
      .split('|')
      .map(function (item) {
        return normalizeCommunityToken(item);
      })
      .filter(Boolean);

    if (!qualificationValues.length || !qualifications.length) {
      return false;
    }

    return qualifications.some(function (qualification) {
      return qualificationValues.indexOf(normalizeCommunityToken(qualification)) !== -1;
    });
  }

  function doesPostMatchTokenAttribute(post, attributeName, value) {
    var expectedValue = String(value || 'all');
    if (!expectedValue || expectedValue === 'all') {
      return true;
    }

    return String(post.getAttribute(attributeName) || '') === expectedValue;
  }

  function doesPostMatchWorkType(post, workType) {
    var expectedValue = String(workType || 'all');
    if (!expectedValue || expectedValue === 'all') {
      return true;
    }

    var values = (post.getAttribute('data-sffc-community-post-work-types') || '')
      .split('|')
      .map(function (item) {
        return String(item || '').trim();
      })
      .filter(Boolean);

    return values.indexOf(expectedValue) !== -1;
  }

  function doesPostMatchPostedDate(post, postedDate) {
    var value = String(postedDate || 'all');
    if (!value || value === 'all') {
      return true;
    }

    var timestamp = parseInt(post.getAttribute('data-sffc-community-post-timestamp') || '0', 10);
    if (!timestamp) {
      return false;
    }

    var now = Math.floor(Date.now() / 1000);
    var age = Math.max(0, now - timestamp);
    var day = 24 * 60 * 60;

    if (value === 'past_24_hours') {
      return age <= day;
    }
    if (value === 'past_48_hours') {
      return age <= 2 * day;
    }
    if (value === 'past_7_days') {
      return age <= 7 * day;
    }
    if (value === 'past_14_days') {
      return age <= 14 * day;
    }
    if (value === 'past_30_days') {
      return age <= 30 * day;
    }

    return true;
  }

  function doesPostMatchSearch(post, searchTerm) {
    var normalizedTerm = normalizeCommunityToken(searchTerm);
    if (!normalizedTerm) {
      return true;
    }

    var searchIndex = post.getAttribute('data-sffc-community-post-search') || post.textContent || '';
    var normalizedIndex = normalizeCommunityToken(searchIndex);
    var searchTokens = normalizedTerm.split(' ').filter(Boolean);

    if (normalizedIndex.indexOf(normalizedTerm) !== -1) {
      return true;
    }

    if (!searchTokens.length) {
      return true;
    }

    return searchTokens.every(function (token) {
      return normalizedIndex.indexOf(token) !== -1;
    });
  }

  function positionCommunityFilterPanel(root, panelKey) {
    var toolbar = root ? root.querySelector('.sffc-community-editorial__filter-toolbar') : null;
    var panel = root ? root.querySelector('[data-sffc-community-filter-panel="' + panelKey + '"]') : null;
    var toggle = root ? root.querySelector('[data-sffc-community-filter-toggle="' + panelKey + '"]') : null;
    if (!toolbar || !panel || !toggle || panel.hidden) {
      return;
    }

    var toolbarRect = toolbar.getBoundingClientRect();
    var toggleRect = toggle.getBoundingClientRect();
    var viewportWidth = window.innerWidth || document.documentElement.clientWidth || 0;
    var viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
    var isMobileViewport = viewportWidth <= 900 || (window.matchMedia && window.matchMedia('(hover: none) and (pointer: coarse)').matches);
    var preferredPanelWidth = panelKey === 'trackers' ? 560 : (panelKey === 'match' ? 460 : (panelKey === 'search' ? 420 : 360));
    var maxPanelWidth = Math.max(260, viewportWidth - 24);
    var panelWidth = Math.min(Math.max(toggleRect.width, preferredPanelWidth), maxPanelWidth);
    var left = Math.max(12, Math.min(toggleRect.left, viewportWidth - panelWidth - 12));

    if (isMobileViewport && panel.parentElement !== toolbar) {
      toolbar.appendChild(panel);
    }

    if (isMobileViewport) {
      panelWidth = Math.min(Math.max(280, viewportWidth - 48), 360);
      left = Math.max(16, Math.round((viewportWidth - panelWidth) / 2));
      panel.style.position = 'fixed';
      panel.style.zIndex = '2147483000';
      panel.style.top = Math.max(8, Math.min(toolbarRect.bottom + 8, viewportHeight - 180)) + 'px';
      panel.style.left = left + 'px';
      panel.style.right = 'auto';
      panel.style.width = panelWidth + 'px';
      panel.style.minWidth = panelWidth + 'px';
      panel.style.maxWidth = panelWidth + 'px';
      panel.style.maxHeight = Math.min(420, Math.max(220, viewportHeight - Math.max(toolbarRect.bottom + 8, 8) - 24)) + 'px';
      panel.style.display = 'grid';
      panel.style.visibility = 'visible';
      panel.style.opacity = '1';
      return;
    }

    panel.style.position = 'fixed';
    panel.style.zIndex = '2147483000';
    panel.style.top = Math.max(0, toolbarRect.bottom + 8) + 'px';
    panel.style.left = left + 'px';
    panel.style.right = 'auto';
    panel.style.width = panelWidth + 'px';
    panel.style.minWidth = panelWidth + 'px';
    panel.style.maxWidth = Math.max(260, viewportWidth - 24) + 'px';
  }

  function closeCommunityFilterPanels(root, exceptKey) {
    root.querySelectorAll('[data-sffc-community-filter-panel]').forEach(function (panel) {
      var panelKey = panel.getAttribute('data-sffc-community-filter-panel') || '';
      var shouldShow = exceptKey && panelKey === exceptKey;
      panel.hidden = !shouldShow;
      if (shouldShow) {
        positionCommunityFilterPanel(root, panelKey);
      } else {
        panel.removeAttribute('style');
      }
    });

    root.querySelectorAll('[data-sffc-community-filter-toggle]').forEach(function (toggle) {
      var toggleKey = toggle.getAttribute('data-sffc-community-filter-toggle') || '';
      toggle.setAttribute('aria-expanded', exceptKey && toggleKey === exceptKey ? 'true' : 'false');
      toggle.classList.toggle('is-active', !!(exceptKey && toggleKey === exceptKey));
    });
  }

  function toggleCommunityTrackerSection(tracker, forceExpanded) {
    if (!tracker) {
      return;
    }

    var banner = tracker.querySelector('[data-sffc-community-tracker-toggle]');
    var list = tracker.querySelector('[data-sffc-community-tracker-list]');
    var isCollapsed = tracker.classList.contains('is-collapsed');
    var shouldExpand = typeof forceExpanded === 'boolean' ? forceExpanded : isCollapsed;

    tracker.classList.toggle('is-collapsed', !shouldExpand);
    if (banner) {
      banner.setAttribute('aria-expanded', shouldExpand ? 'true' : 'false');
    }
    if (list) {
      list.hidden = !shouldExpand;
    }
  }

  function sortCommunityTrackerByRecent(root, tracker) {
    if (!tracker) {
      return;
    }

    var list = tracker.querySelector('[data-sffc-community-tracker-list]');
    if (!list) {
      return;
    }

    Array.prototype.slice.call(list.querySelectorAll('.sffc-community-editorial__post-shell')).sort(function (left, right) {
      var leftPost = left.querySelector('[data-sffc-community-post-timestamp]');
      var rightPost = right.querySelector('[data-sffc-community-post-timestamp]');
      var leftTime = parseInt(leftPost ? leftPost.getAttribute('data-sffc-community-post-timestamp') || '0' : '0', 10) || 0;
      var rightTime = parseInt(rightPost ? rightPost.getAttribute('data-sffc-community-post-timestamp') || '0' : '0', 10) || 0;
      return rightTime - leftTime;
    }).forEach(function (shell) {
      list.appendChild(shell);
    });

    refreshCommunityVisibleTrackerHighlights(root || document);
  }

  function getCommunityTrackerSortMatch(sortValue) {
    if (sortValue === 'qualified' || sortValue === 'consider' || sortValue === 'mismatch') {
      return sortValue;
    }
    return 'all';
  }

  function setCommunityEarlyBirdState(root, value) {
    var state = getCommunityFilterState(root);
    var normalizedValue = value === 'early_bird' ? 'early_bird' : 'all';
    var isActive = normalizedValue === 'early_bird';

    state.recency = normalizedValue;
    root.querySelectorAll('[data-sffc-community-early-bird-filter]').forEach(function (button) {
      button.classList.toggle('is-active', isActive);
      button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
    root.querySelectorAll('[data-sffc-community-early-bird-nav]').forEach(function (trigger) {
      trigger.classList.toggle('is-active', isActive);
      trigger.setAttribute('aria-current', isActive ? 'page' : 'false');
    });
  }

  function getSelectedCommunityTrackers(root) {
    var state = getCommunityFilterState(root);
    return Array.isArray(state.trackers) ? state.trackers.filter(Boolean) : [];
  }

  function setCommunityTrackerFilterState(root, trackers) {
    if (!root) {
      return;
    }

    var state = getCommunityFilterState(root);
    var selected = Array.isArray(trackers)
      ? trackers.map(String).filter(Boolean)
      : [];
    var selectedMap = {};

    selected = selected.filter(function (slug) {
      if (selectedMap[slug]) {
        return false;
      }
      selectedMap[slug] = true;
      return true;
    });

    state.trackers = selected;
    root.querySelectorAll('[data-sffc-community-tracker-filter]').forEach(function (option) {
      var slug = option.getAttribute('data-sffc-community-tracker-filter') || '';
      var isActive = selected.indexOf(slug) !== -1;
      option.classList.toggle('is-active', isActive);
      option.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });

    root.querySelectorAll('[data-sffc-community-filter-toggle="trackers"]').forEach(function (button) {
      button.classList.toggle('has-selection', selected.length > 0);
    });

    updateToolbarFilterLabel(root, 'trackers', selected.length ? String(selected.length) : '');
  }

  function getCommunitySavedTrackerSlugs(root) {
    if (!root) {
      return [];
    }
    if (Array.isArray(root.__sffcCommunitySavedTrackers)) {
      return root.__sffcCommunitySavedTrackers.slice();
    }

    var fromRoot = String(root.getAttribute('data-sffc-community-saved-trackers') || '')
      .split('|')
      .map(function (slug) {
        return String(slug || '').trim();
      })
      .filter(Boolean);
    var fromStorage = [];

    try {
      fromStorage = JSON.parse(window.localStorage.getItem('sffcCommunitySavedTrackers') || '[]');
    } catch (error) {
      fromStorage = [];
    }

    root.__sffcCommunitySavedTrackers = fromRoot.concat(Array.isArray(fromStorage) ? fromStorage : [])
      .map(function (slug) {
        return String(slug || '').trim();
      })
      .filter(Boolean)
      .filter(function (slug, index, slugs) {
        return slugs.indexOf(slug) === index;
      });

    return root.__sffcCommunitySavedTrackers.slice();
  }

  function getCommunityPremiumTrackerSlugs(root) {
    if (!root) {
      return [];
    }

    return String(root.getAttribute('data-sffc-community-premium-trackers') || '')
      .split('|')
      .map(function (slug) {
        return String(slug || '').trim();
      })
      .filter(Boolean);
  }

  function setCommunitySavedTrackerSlugs(root, slugs) {
    var normalized = Array.isArray(slugs) ? slugs : [];
    normalized = normalized
      .map(function (slug) {
        return String(slug || '').trim();
      })
      .filter(Boolean)
      .filter(function (slug, index, items) {
        return items.indexOf(slug) === index;
      });

    if (root) {
      root.__sffcCommunitySavedTrackers = normalized;
      root.setAttribute('data-sffc-community-saved-trackers', normalized.join('|'));
    }
    try {
      window.localStorage.setItem('sffcCommunitySavedTrackers', JSON.stringify(normalized));
    } catch (error) {}
  }

  function updateCommunitySavedTrackerButtons(root) {
    if (!root) {
      return;
    }
    var saved = getCommunitySavedTrackerSlugs(root);
    root.querySelectorAll('[data-sffc-community-save-tracker]').forEach(function (button) {
      var slug = button.getAttribute('data-tracker-slug') || '';
      var isSaved = slug && saved.indexOf(slug) !== -1;
      button.classList.toggle('is-saved', !!isSaved);
      button.setAttribute('aria-pressed', isSaved ? 'true' : 'false');
      var label = button.querySelector('span');
      if (label) {
        label.textContent = isSaved ? 'Saved' : 'Save';
      }
    });
  }

  function saveCommunityTracker(root, button) {
    if (!root || !button) {
      return;
    }

    var slug = button.getAttribute('data-tracker-slug') || '';
    if (!slug) {
      return;
    }

    var saved = getCommunitySavedTrackerSlugs(root);
    var isSaved = saved.indexOf(slug) !== -1;
    var nextSaved = !isSaved;
    var nextList = nextSaved
      ? saved.concat([slug])
      : saved.filter(function (savedSlug) {
        return savedSlug !== slug;
      });

    setCommunitySavedTrackerSlugs(root, nextList);
    updateCommunitySavedTrackerButtons(root);
    if ((root.getAttribute('data-active-tab') || '') === 'saved_trackers') {
      setCommunityTrackerFilterState(root, getCommunityPremiumTrackerSlugs(root));
      requestCommunityFeed(root, { scrollToResults: false, batchSize: 40 });
    }

    var requestBody = new FormData();
    requestBody.append('action', 'sffc_crm_editorial_toggle_saved_tracker');
    requestBody.append('nonce', root.getAttribute('data-sffc-community-saved-tracker-nonce') || config.savedTrackerNonce || '');
    requestBody.append('tracker_slug', slug);
    requestBody.append('saved', nextSaved ? '1' : '0');

    window.fetch((config.ajaxUrl || '/wp-admin/admin-ajax.php'), {
      method: 'POST',
      body: requestBody,
      credentials: 'same-origin'
    })
      .then(parseAjaxJson)
      .then(function (payload) {
        if (payload && payload.success && payload.data && Array.isArray(payload.data.savedTrackers)) {
          setCommunitySavedTrackerSlugs(root, payload.data.savedTrackers);
          updateCommunitySavedTrackerButtons(root);
        }
      })
      .catch(function () {
        setCommunitySavedTrackerSlugs(root, saved);
        updateCommunitySavedTrackerButtons(root);
      });
  }

  function setCommunitySingleFilterState(root, stateKey, attributeKey, value, label) {
    if (!root) {
      return;
    }

    var normalizedValue = value || 'all';
    var state = getCommunityFilterState(root);
    state[stateKey] = normalizedValue;

    root.querySelectorAll('[data-sffc-community-' + attributeKey + '-filter]').forEach(function (option) {
      var isActive = (option.getAttribute('data-sffc-community-' + attributeKey + '-filter') || 'all') === normalizedValue;
      option.classList.toggle('is-active', isActive);
      option.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });

    root.querySelectorAll('[data-sffc-community-filter-toggle="' + attributeKey + '"]').forEach(function (button) {
      button.classList.toggle('has-selection', normalizedValue !== 'all');
    });

    updateToolbarFilterLabel(root, attributeKey, normalizedValue === 'all' ? '' : (label || ''));
  }

  function resetCommunityPostFilters(root) {
    if (!root) {
      return;
    }

    var state = getCommunityFilterState(root);
    var searchInput = root.querySelector('[data-sffc-community-search-input]');

    state.search = '';
    state.location = 'all';
    state.seniority = 'all';
    state.qualification = 'all';
    state.postedDate = 'all';
    state.company = 'all';
    state.sector = 'all';
    state.workType = 'all';
    state.match = 'all';
    state.recency = 'all';
    state.trackers = [];

    activateGroupFilter(root, 'all');
    setCommunityEarlyBirdState(root, 'all');
    setCommunityTrackerFilterState(root, []);

    root.querySelectorAll('[data-sffc-community-seniority-filter]').forEach(function (option) {
      var isActive = option.getAttribute('data-sffc-community-seniority-filter') === 'all';
      option.classList.toggle('is-active', isActive);
      option.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });

    root.querySelectorAll('[data-sffc-community-location-filter]').forEach(function (option) {
      var isActive = option.getAttribute('data-sffc-community-location-filter') === 'all';
      option.classList.toggle('is-active', isActive);
      option.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });

    root.querySelectorAll('[data-sffc-community-qualification-filter]').forEach(function (option) {
      var isActive = option.getAttribute('data-sffc-community-qualification-filter') === 'all';
      option.classList.toggle('is-active', isActive);
      option.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });

    setCommunitySingleFilterState(root, 'postedDate', 'posted-date', 'all', '');
    setCommunitySingleFilterState(root, 'company', 'company', 'all', '');
    setCommunitySingleFilterState(root, 'sector', 'sector', 'all', '');
    setCommunitySingleFilterState(root, 'workType', 'work-type', 'all', '');
    setCommunitySingleFilterState(root, 'match', 'match', 'all', '');

    updateToolbarFilterLabel(root, 'seniority', '');
    updateToolbarFilterLabel(root, 'location', '');
    updateToolbarFilterLabel(root, 'qualification', '');
    updateToolbarFilterLabel(root, 'posted-date', '');
    updateToolbarFilterLabel(root, 'company', '');
    updateToolbarFilterLabel(root, 'sector', '');
    updateToolbarFilterLabel(root, 'work-type', '');
    updateToolbarFilterLabel(root, 'match', '');

    if (searchInput) {
      searchInput.value = '';
    }

    closeCommunityFilterPanels(root, '');
    updateCommunityAppliedFilters(root);
  }

  function normalizeCommunityPostActionButtons(root) {
    if (!root) {
      return;
    }

    root.querySelectorAll('[data-sffc-community-post-action-label]').forEach(function (button) {
      var label = button.getAttribute('data-sffc-community-post-action-label') || '';
      var textNode = Array.prototype.find.call(button.childNodes, function (node) {
        return node.nodeType === 3 && String(node.nodeValue || '').trim() !== '';
      });

      if (!label) {
        return;
      }

      if (textNode) {
        textNode.nodeValue = label + ' ';
      } else {
        button.insertBefore(document.createTextNode(label + ' '), button.firstChild || null);
      }
    });
  }

  function closeCommunityPostPremiumPopovers(root, exceptPanel) {
    if (!root) {
      return;
    }

    root.querySelectorAll('[data-sffc-community-post-premium-panel]').forEach(function (panel) {
      var shouldShow = !!exceptPanel && panel === exceptPanel;
      panel.hidden = !shouldShow;
      var shell = panel.closest('.sffc-community-editorial__post-premium-lock');
      var toggle = shell ? shell.querySelector('[data-sffc-community-post-premium-toggle]') : null;
      if (toggle) {
        toggle.setAttribute('aria-expanded', shouldShow ? 'true' : 'false');
        toggle.classList.toggle('is-active', shouldShow);
      }
    });
  }

  function applyCommunityFilters(root, options) {
    if (!root) {
      return;
    }

    var settings = options || {};
    var toolbarState = getCommunityFilterState(root);
    var selectedTrackers = getSelectedCommunityTrackers(root);
    var groupSlug = selectedTrackers.length
      ? 'all'
      : getEffectiveCommunityGroup(root, root.getAttribute('data-active-group-filter') || 'all');
    var posts = root.querySelectorAll('[data-sffc-community-post-groups]');
    var emptyState = root.querySelector('[data-sffc-community-filter-empty]');
    var loadMoreButton = root.querySelector('[data-sffc-community-load-more]');
    var visibleCount = 0;
    var firstVisiblePost = null;

    posts.forEach(function (post) {
      var groups = (post.getAttribute('data-sffc-community-post-groups') || '')
        .split(/\s+/)
        .filter(Boolean);
      var matchesGroup = groupSlug === 'all' || groups.indexOf(groupSlug) !== -1;
      var matchesTrackers = !selectedTrackers.length || selectedTrackers.some(function (trackerSlug) {
        return groups.indexOf(trackerSlug) !== -1;
      });
      var matchesLocation = toolbarState.location === 'all'
        || doesPostMatchLocations(post, [toolbarState.location]);
      var matchesSeniority = toolbarState.seniority === 'all'
        || doesPostMatchSeniority(post, [toolbarState.seniority]);
      var matchesQualification = toolbarState.qualification === 'all'
        || doesPostMatchQualifications(post, [toolbarState.qualification]);
      var matchesPostedDate = doesPostMatchPostedDate(post, toolbarState.postedDate);
      var matchesCompany = doesPostMatchTokenAttribute(post, 'data-sffc-community-post-company', toolbarState.company);
      var matchesSector = doesPostMatchTokenAttribute(post, 'data-sffc-community-post-sector', toolbarState.sector);
      var matchesWorkType = doesPostMatchWorkType(post, toolbarState.workType);
      var matchesMatch = doesPostMatchTokenAttribute(post, 'data-sffc-community-post-match-rank', toolbarState.match);
      var matchesSearch = doesPostMatchSearch(post, toolbarState.search);
      var matchesRecency = toolbarState.recency !== 'early_bird'
        || post.getAttribute('data-sffc-community-post-recency') === 'early_bird';
      var isVisible = matchesGroup && matchesTrackers && matchesLocation && matchesSeniority && matchesQualification && matchesPostedDate && matchesCompany && matchesSector && matchesWorkType && matchesMatch && matchesSearch && matchesRecency;

      post.hidden = !isVisible;
      var postShell = post.closest('.sffc-community-editorial__post-shell');
      if (postShell) {
        postShell.hidden = !isVisible;
      }
      if (isVisible) {
        visibleCount += 1;
        if (!firstVisiblePost) {
          firstVisiblePost = post;
        }
      }
    });

    refreshCommunityVisibleTrackerHighlights(root);

    root.querySelectorAll('[data-sffc-community-tracker]').forEach(function (tracker) {
      var trackerSlug = tracker.getAttribute('data-sffc-community-tracker-slug') || '';
      var visibleTrackerPosts = tracker.querySelectorAll('[data-sffc-community-post-groups]:not([hidden])');
      var matchesSelectedTracker = !selectedTrackers.length || selectedTrackers.indexOf(trackerSlug) !== -1;
      tracker.hidden = visibleTrackerPosts.length === 0 || !matchesSelectedTracker;
    });

    if (emptyState) {
      emptyState.hidden = visibleCount > 0;
    }

    updateLoadMoreState(root, loadMoreButton);
    refreshCommunityLinkedinPostsLayout(root);

    if (settings.scrollToResults) {
      var scrollTarget = firstVisiblePost || emptyState;
      if (scrollTarget && typeof scrollTarget.scrollIntoView === 'function') {
        window.setTimeout(function () {
          scrollTarget.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
          });
        }, 20);
      }
    }
  }

  function refreshCommunityVisibleTrackerHighlights(root) {
    if (!root) {
      return;
    }

    root.querySelectorAll('[data-sffc-community-tracker]').forEach(function (tracker) {
      var visibleShells = Array.prototype.slice.call(tracker.querySelectorAll('.sffc-community-editorial__post-shell')).filter(function (shell) {
        var post = shell.querySelector('[data-sffc-community-post-groups]');
        return post && !shell.hidden && !post.hidden;
      });
      var banner = tracker.querySelector('.sffc-community-editorial__post-category-banner');
      if (!visibleShells.length || !banner) {
        return;
      }

      visibleShells.forEach(function (shell) {
        shell.classList.remove('has-category-highlight');
      });

      var leadShell = visibleShells[0];
      leadShell.classList.add('has-category-highlight');
      if (banner.hasAttribute('data-sffc-community-tracker-banner')) {
        var trackerList = tracker.querySelector('[data-sffc-community-tracker-list]');
        if (trackerList && banner.nextElementSibling !== trackerList) {
          tracker.insertBefore(banner, trackerList);
        }
        return;
      }
      if (banner.parentElement !== leadShell) {
        leadShell.insertBefore(banner, leadShell.firstChild);
      }
    });
  }

  function applyOnboardingPersonalization(root, state) {
    if (!root || !state) {
      return;
    }

    var posts = root.querySelectorAll('[data-sffc-community-post-groups]');
    var emptyState = root.querySelector('[data-sffc-community-filter-empty]');
    var locationLabels = config.locationLabels || {};
    var roleInterestMap = config.roleInterestMap || {};
    var activeLocations = [];
    var activeSeniorities = [];
    var visibleCount = 0;
    var firstVisiblePost = null;

    if (state.locationChoice === 'other' && state.locationOther) {
      activeLocations = [state.locationOther];
    } else if (state.locationChoice && locationLabels[state.locationChoice]) {
      activeLocations = [locationLabels[state.locationChoice]];
    }

    if (state.roleInterest && Array.isArray(roleInterestMap[state.roleInterest])) {
      activeSeniorities = roleInterestMap[state.roleInterest].slice();
    }

    if (!activeLocations.length && !activeSeniorities.length) {
      return;
    }

    posts.forEach(function (post) {
      var locationMatch = activeLocations.length ? doesPostMatchLocations(post, activeLocations) : false;
      var seniorityMatch = activeSeniorities.length ? doesPostMatchSeniority(post, activeSeniorities) : false;
      var requiresLocation = activeLocations.length > 0;
      var requiresSeniority = activeSeniorities.length > 0;
      var isVisible = (!requiresLocation || locationMatch) && (!requiresSeniority || seniorityMatch);

      post.hidden = !isVisible;
      if (isVisible) {
        visibleCount += 1;
        if (!firstVisiblePost) {
          firstVisiblePost = post;
        }
      }
    });

    if (emptyState) {
      emptyState.hidden = visibleCount > 0;
    }

    if (firstVisiblePost && typeof firstVisiblePost.scrollIntoView === 'function') {
      window.setTimeout(function () {
        firstVisiblePost.scrollIntoView({
          behavior: 'smooth',
          block: 'start'
        });
      }, 60);
    }
  }

  function activateTab(root, tab) {
    if (!root || !tab) {
      return;
    }

    var triggers = root.querySelectorAll('[data-sffc-community-tab-trigger]');
    var panels = root.querySelectorAll('[data-sffc-community-tab-panel]');
    var profileSubnavShell = root.querySelector('[data-sffc-community-profile-subnav-shell]');
    var communityShell = root.querySelector('.sffc-community-editorial__shell');
    var panelTab = (tab === 'early_bird' || tab === 'saved_trackers') ? 'posts' : tab;

    triggers.forEach(function (trigger) {
      var isActive = trigger.getAttribute('data-sffc-community-tab-trigger') === tab;
      trigger.classList.toggle('is-active', isActive);
      trigger.setAttribute('aria-current', isActive ? 'page' : 'false');
    });

    panels.forEach(function (panel) {
      var isActive = panel.getAttribute('data-sffc-community-tab-panel') === panelTab;
      panel.hidden = !isActive;
      panel.classList.toggle('is-active', isActive);
    });

    root.setAttribute('data-active-tab', tab);
    if (communityShell) {
      communityShell.classList.toggle('sffc-community-editorial__shell--posts-linkedin', tab === 'posts' || tab === 'early_bird' || tab === 'saved_trackers');
    }
    if (profileSubnavShell) {
      profileSubnavShell.hidden = tab !== 'templates';
    }
    if (tab === 'templates') {
      activateProfileSubtab(root, root.__sffcCommunityActiveProfileTab || 'profile');
    }
    requestDeferredCommunityTab(root, panelTab);
    updateUrl(tab);
  }

  function requestCommunitySidebars(root) {
    var leftShell;
    var rightShell;
    var body;

    if (!root || root.__sffcCommunitySidebarsLoaded || root.__sffcCommunitySidebarsLoading || !window.fetch) {
      return;
    }

    leftShell = root.querySelector('[data-sffc-community-sidebars-left]');
    rightShell = root.querySelector('[data-sffc-community-sidebars-right]');
    if (!leftShell && !rightShell) {
      root.__sffcCommunitySidebarsLoaded = true;
      return;
    }

    root.__sffcCommunitySidebarsLoading = true;
    body = new FormData();
    body.append('action', 'sffc_crm_editorial_load_sidebars');
    body.append('nonce', config.sidebarsNonce || '');
    body.append('context_post_id', root.getAttribute('data-sffc-community-context-post-id') || '0');
    body.append('context_group', root.getAttribute('data-sffc-community-context-group') || '');

    window.fetch((config.ajaxUrl || '/wp-admin/admin-ajax.php'), {
      method: 'POST',
      body: body,
      credentials: 'same-origin'
    })
      .then(parseAjaxJson)
      .then(function (payload) {
        if (!payload || !payload.success || !payload.data) {
          throw new Error('Sidebar load failed.');
        }

        if (leftShell && typeof payload.data.leftHtml === 'string') {
          leftShell.innerHTML = payload.data.leftHtml;
        }
        if (rightShell && typeof payload.data.rightHtml === 'string') {
          rightShell.innerHTML = payload.data.rightHtml;
        }

        root.__sffcCommunitySidebarsLoaded = true;
      })
      .catch(function () {
        root.__sffcCommunitySidebarsLoaded = false;
      })
      .finally(function () {
        root.__sffcCommunitySidebarsLoading = false;
      });
  }

  function requestDeferredCommunityTab(root, tab) {
    var panel;
    var deferred;
    var body;

    if (!root || !tab || !window.fetch) {
      return;
    }

    panel = root.querySelector('[data-sffc-community-tab-panel="' + tab + '"]');
    if (!panel) {
      return;
    }

    deferred = panel.querySelector('[data-sffc-community-deferred-panel="' + tab + '"]');
    if (!deferred) {
      panel.setAttribute('data-sffc-community-deferred-loaded', 'true');
      return;
    }

    if (panel.getAttribute('data-sffc-community-deferred-loaded') === 'true' || panel.__sffcCommunityDeferredLoading) {
      return;
    }

    panel.__sffcCommunityDeferredLoading = true;
    body = new FormData();
    body.append('action', 'sffc_crm_editorial_load_tab_panel');
    body.append('nonce', config.tabPanelNonce || '');
    body.append('tab', tab);

    window.fetch((config.ajaxUrl || '/wp-admin/admin-ajax.php'), {
      method: 'POST',
      body: body,
      credentials: 'same-origin'
    })
      .then(parseAjaxJson)
      .then(function (payload) {
        if (!payload || !payload.success || !payload.data || typeof payload.data.html !== 'string') {
          throw new Error('Panel load failed.');
        }

        panel.innerHTML = payload.data.html;
        panel.setAttribute('data-sffc-community-deferred-loaded', 'true');
      })
      .catch(function () {
        panel.__sffcCommunityDeferredLoading = false;
      })
      .finally(function () {
        panel.__sffcCommunityDeferredLoading = false;
      });
  }

  function prefetchDeferredCommunityTabs(root) {
    if (!root) {
      return;
    }

    ['templates'].forEach(function (tab) {
      window.setTimeout(function () {
        requestDeferredCommunityTab(root, tab);
      }, 500);
    });
  }

  function activateIntrosSubtab(root, tab) {
    if (!root || !tab) {
      return;
    }

    var triggers = root.querySelectorAll('[data-sffc-community-intros-tab-trigger]');
    var panels = root.querySelectorAll('[data-sffc-community-intros-tab-panel]');

    triggers.forEach(function (trigger) {
      var isActive = trigger.getAttribute('data-sffc-community-intros-tab-trigger') === tab;
      trigger.classList.toggle('is-active', isActive);
      trigger.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });

    panels.forEach(function (panel) {
      var isActive = panel.getAttribute('data-sffc-community-intros-tab-panel') === tab;
      panel.hidden = !isActive;
      panel.classList.toggle('is-active', isActive);
    });

    root.__sffcCommunityActiveIntrosTab = tab;
  }

  function activateProfileSubtab(root, tab) {
    if (!root || !tab) {
      return;
    }

    var triggers = root.querySelectorAll('[data-sffc-community-profile-tab-trigger]');
    var panels = root.querySelectorAll('[data-sffc-community-profile-tab-panel]');

    triggers.forEach(function (trigger) {
      var isActive = trigger.getAttribute('data-sffc-community-profile-tab-trigger') === tab;
      trigger.classList.toggle('is-active', isActive);
      trigger.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });

    panels.forEach(function (panel) {
      var isActive = panel.getAttribute('data-sffc-community-profile-tab-panel') === tab;
      panel.hidden = !isActive;
      panel.classList.toggle('is-active', isActive);
    });

    root.__sffcCommunityActiveProfileTab = tab;
  }

  function getProfileAccountCarouselIndex(shell) {
    var carousel = shell ? shell.querySelector('[data-sffc-profile-account-carousel]') : null;
    var cards = carousel ? Array.prototype.slice.call(carousel.querySelectorAll('.sffc-crm-dashboard-app-profile-account-feature-card')) : [];

    if (!carousel || !cards.length) {
      return 0;
    }

    var current = 0;
    var distance = Infinity;

    cards.forEach(function (card, index) {
      var cardDistance = Math.abs(card.offsetLeft - carousel.scrollLeft);
      if (cardDistance < distance) {
        distance = cardDistance;
        current = index;
      }
    });

    return current;
  }

  function updateProfileAccountCarousel(shell) {
    var container = shell ? shell.closest('.sffc-crm-dashboard-app-profile-account-premium') : null;
    var carousel = shell ? shell.querySelector('[data-sffc-profile-account-carousel]') : null;
    var cards = carousel ? Array.prototype.slice.call(carousel.querySelectorAll('.sffc-crm-dashboard-app-profile-account-feature-card')) : [];
    var current = getProfileAccountCarouselIndex(shell);

    if (!carousel || !cards.length) {
      return;
    }

    (container || shell).querySelectorAll('[data-sffc-profile-account-carousel-dot]').forEach(function (dot) {
      var dotIndex = parseInt(dot.getAttribute('data-sffc-profile-account-carousel-dot') || '0', 10);
      dot.classList.toggle('is-active', dotIndex === current);
    });

    shell.querySelectorAll('[data-sffc-profile-account-carousel-control]').forEach(function (control) {
      control.disabled = false;
    });
  }

  function scrollProfileAccountCarousel(shell, index) {
    var carousel = shell ? shell.querySelector('[data-sffc-profile-account-carousel]') : null;
    var cards = carousel ? Array.prototype.slice.call(carousel.querySelectorAll('.sffc-crm-dashboard-app-profile-account-feature-card')) : [];
    var nextIndex = cards.length ? ((index % cards.length) + cards.length) % cards.length : 0;

    if (!carousel || !cards[nextIndex]) {
      return;
    }

    carousel.scrollTo({
      left: cards[nextIndex].offsetLeft,
      behavior: 'smooth',
    });

    window.setTimeout(function () {
      updateProfileAccountCarousel(shell);
    }, 180);
  }

  function startProfileAccountCarouselAutoplay(shell) {
    var carousel = shell ? shell.querySelector('[data-sffc-profile-account-carousel]') : null;

    if (!shell || !carousel || shell.__sffcAccountCarouselTimer) {
      return;
    }

    shell.__sffcAccountCarouselPaused = false;
    shell.__sffcAccountCarouselTimer = window.setInterval(function () {
      if (shell.__sffcAccountCarouselPaused || carousel.offsetParent === null) {
        return;
      }

      scrollProfileAccountCarousel(shell, getProfileAccountCarouselIndex(shell) + 1);
    }, 4200);
  }

  function openCommunityProfileUtility(root, triggerName) {
    if (!root || !triggerName) {
      return false;
    }

    var profilePanel = root.querySelector('[data-sffc-community-profile-tab-panel="profile"]');
    if (!profilePanel || profilePanel.hidden) {
      return false;
    }

    if (triggerName === 'career-report' && config.careerReportUrl) {
      window.location.href = String(config.careerReportUrl);
      return true;
    }

    if (triggerName === 'saved-lists' && config.savedListsUrl) {
      window.location.href = String(config.savedListsUrl);
      return true;
    }

    return false;
  }

  function activateGroupFilter(root, slug, options) {
    if (!root) {
      return;
    }

    var normalizedSlug = slug || 'all';
    var filters = root.querySelectorAll('[data-sffc-community-group-filter]');
    filters.forEach(function (filter) {
      var isActive = filter.getAttribute('data-sffc-community-group-filter') === normalizedSlug;
      filter.classList.toggle('is-active', isActive);
      filter.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });

    root.setAttribute('data-active-group-filter', normalizedSlug);
  }

  function updateToolbarFilterLabel(root, key, value) {
    var label = root.querySelector('[data-sffc-community-filter-label="' + key + '"]');
    if (!label) {
      return;
    }
    label.textContent = value ? ': ' + value : '';
  }

  function getCommunityFilterDisplayLabel(root, key, value) {
    if (!value || value === 'all') {
      return '';
    }

    if (key === 'search') {
      return String(value);
    }

    if (key === 'group') {
      var groupNode = root.querySelector('[data-sffc-community-group-filter="' + value + '"] strong');
      return groupNode ? groupNode.textContent.trim() : String(value);
    }

    if (key === 'trackers') {
      var trackerValues = Array.isArray(value) ? value : String(value).split('|').filter(Boolean);
      var trackerLabels = trackerValues.map(function (slug) {
        var trackerNode = root.querySelector('[data-sffc-community-tracker-filter="' + slug + '"]');
        return trackerNode ? (trackerNode.getAttribute('data-sffc-community-tracker-label') || trackerNode.textContent || '').trim() : slug;
      }).filter(Boolean);
      if (trackerLabels.length > 2) {
        return trackerLabels.slice(0, 2).join(', ') + ' +' + String(trackerLabels.length - 2);
      }
      return trackerLabels.join(', ');
    }

    if (key === 'location') {
      var locationOption = root.querySelector('[data-sffc-community-location-filter="' + value + '"]');
      if (locationOption) {
        return (locationOption.getAttribute('data-sffc-community-location-label') || locationOption.textContent || '').trim();
      }
    }

    var attributeKey = key.replace(/([A-Z])/g, '-$1').replace(/_/g, '-').toLowerCase();
    var option = root.querySelector('[data-sffc-community-' + attributeKey + '-filter="' + value + '"]');
    return option ? option.textContent.trim() : String(value);
  }

  function updateCommunityAppliedFilters(root) {
    if (!root) {
      return;
    }

    var state = getCommunityFilterState(root);
    var container = root.querySelector('[data-sffc-community-applied-filters]');
    var resetButton = root.querySelector('[data-sffc-community-filter-reset]');
    var activeGroup = root.getAttribute('data-active-group-filter') || 'all';
    var toolbar = root.querySelector('.sffc-community-editorial__filter-toolbar');
    var defaultSeniority = toolbar ? (toolbar.getAttribute('data-sffc-community-default-seniority') || 'all') : 'all';
    var defaultLocation = toolbar ? (toolbar.getAttribute('data-sffc-community-default-location') || 'all') : 'all';
    var defaultQualification = toolbar ? (toolbar.getAttribute('data-sffc-community-default-qualification') || 'all') : 'all';
    var defaultPostedDate = toolbar ? (toolbar.getAttribute('data-sffc-community-default-posted-date') || 'all') : 'all';
    var defaultCompany = toolbar ? (toolbar.getAttribute('data-sffc-community-default-company') || 'all') : 'all';
    var defaultSector = toolbar ? (toolbar.getAttribute('data-sffc-community-default-sector') || 'all') : 'all';
    var defaultWorkType = toolbar ? (toolbar.getAttribute('data-sffc-community-default-work-type') || 'all') : 'all';
    var defaultMatch = toolbar ? (toolbar.getAttribute('data-sffc-community-default-match') || 'all') : 'all';
    var items = [];

    if (!container || !resetButton) {
      return;
    }

    if (activeGroup !== 'all') {
      items.push({
        key: 'group',
        label: 'Group',
        display: getCommunityFilterDisplayLabel(root, 'group', activeGroup)
      });
    }

    if (state.seniority && state.seniority !== 'all' && state.seniority !== defaultSeniority) {
      items.push({
        key: 'seniority',
        label: 'Role',
        display: getCommunityFilterDisplayLabel(root, 'seniority', state.seniority)
      });
    }

    if (state.location && state.location !== 'all' && state.location !== defaultLocation) {
      items.push({
        key: 'location',
        label: 'Location',
        display: getCommunityFilterDisplayLabel(root, 'location', state.location)
      });
    }

    if (state.qualification && state.qualification !== 'all' && state.qualification !== defaultQualification) {
      items.push({
        key: 'qualification',
        label: 'Qualification',
        display: getCommunityFilterDisplayLabel(root, 'qualification', state.qualification)
      });
    }

    if (state.postedDate && state.postedDate !== 'all' && state.postedDate !== defaultPostedDate) {
      items.push({
        key: 'postedDate',
        label: 'Posted date',
        display: getCommunityFilterDisplayLabel(root, 'posted-date', state.postedDate)
      });
    }

    if (state.company && state.company !== 'all' && state.company !== defaultCompany) {
      items.push({
        key: 'company',
        label: 'Company',
        display: getCommunityFilterDisplayLabel(root, 'company', state.company)
      });
    }

    if (state.sector && state.sector !== 'all' && state.sector !== defaultSector) {
      items.push({
        key: 'sector',
        label: 'Sector',
        display: getCommunityFilterDisplayLabel(root, 'sector', state.sector)
      });
    }

    if (state.workType && state.workType !== 'all' && state.workType !== defaultWorkType) {
      items.push({
        key: 'workType',
        label: 'Work type',
        display: getCommunityFilterDisplayLabel(root, 'work-type', state.workType)
      });
    }

    if (state.match && state.match !== 'all' && state.match !== defaultMatch) {
      items.push({
        key: 'match',
        label: 'Qualified Jobs',
        display: getCommunityFilterDisplayLabel(root, 'match', state.match)
      });
    }

    if (state.search && String(state.search).trim() !== '') {
      items.push({
        key: 'search',
        label: 'Search',
        display: getCommunityFilterDisplayLabel(root, 'search', state.search)
      });
    }

    if (state.recency === 'early_bird') {
      items.push({
        key: 'recency',
        label: 'Fresh posts',
        display: 'Fresh private equity roles'
      });
    }

    if (Array.isArray(state.trackers) && state.trackers.length) {
      items.push({
        key: 'trackers',
        label: 'Trackers',
        display: getCommunityFilterDisplayLabel(root, 'trackers', state.trackers)
      });
    }

    resetButton.hidden = items.length === 0;
    container.hidden = items.length === 0;

    if (!items.length) {
      container.innerHTML = '';
      return;
    }

    container.innerHTML = items.map(function (item) {
      var itemLabel = escapeCommunityHtml(item.label);
      var itemDisplay = escapeCommunityHtml(item.display);
      return (
        '<span class="sffc-community-editorial__applied-filter">' +
          '<strong>' + itemLabel + ':</strong>' +
          '<span>' + itemDisplay + '</span>' +
          '<button type="button" class="sffc-community-editorial__applied-filter-remove" data-sffc-community-filter-remove="' + escapeCommunityHtml(item.key) + '" aria-label="Remove ' + itemLabel + ' filter" title="Remove ' + itemLabel + ' filter">' +
            '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>' +
          '</button>' +
        '</span>'
      );
    }).join('');
  }

  function updateLoadMoreState(root, button, forceState) {
    var loadButton = button || root.querySelector('[data-sffc-community-load-more]');
    if (!loadButton) {
      return;
    }

    if (typeof forceState === 'boolean') {
      loadButton.hidden = !forceState;
      return;
    }

    var activeFilter = getEffectiveCommunityGroup(root, root.getAttribute('data-active-group-filter') || 'all');
    var hasMoreByGroup = root.__sffcCommunityHasMoreByGroup || {};
    var hasMore = Object.prototype.hasOwnProperty.call(hasMoreByGroup, activeFilter)
      ? !!hasMoreByGroup[activeFilter]
      : (loadButton.getAttribute('data-sffc-community-has-more') === 'true');

    loadButton.hidden = !hasMore;
  }

  function getCommunityRequestFilters(root) {
    var toolbarState = getCommunityFilterState(root);
    var activeGroup = root.getAttribute('data-active-group-filter') || 'all';
    var selectedTrackers = getSelectedCommunityTrackers(root);
    var hasBroadFilters = !!(
      (toolbarState.search || '').trim()
      || (toolbarState.location && toolbarState.location !== 'all')
      || (toolbarState.seniority && toolbarState.seniority !== 'all')
      || (toolbarState.qualification && toolbarState.qualification !== 'all')
      || (toolbarState.postedDate && toolbarState.postedDate !== 'all')
      || (toolbarState.company && toolbarState.company !== 'all')
      || (toolbarState.sector && toolbarState.sector !== 'all')
      || (toolbarState.workType && toolbarState.workType !== 'all')
      || (toolbarState.match && toolbarState.match !== 'all')
      || (toolbarState.recency && toolbarState.recency !== 'all')
    );
    var requestGroup = selectedTrackers.length
      ? 'all'
      : ((activeGroup === 'all' || activeGroup === '') && hasBroadFilters ? 'all' : getEffectiveCommunityGroup(root, activeGroup));

    return {
      group: requestGroup,
      search: toolbarState.search || '',
      location: toolbarState.location || 'all',
      seniority: toolbarState.seniority || 'all',
      qualification: toolbarState.qualification || 'all',
      postedDate: toolbarState.postedDate || 'all',
      company: toolbarState.company || 'all',
      sector: toolbarState.sector || 'all',
      workType: toolbarState.workType || 'all',
      match: toolbarState.match || 'all',
      recency: toolbarState.recency || 'all',
      trackers: selectedTrackers
    };
  }

  function getEffectiveCommunityGroup(root, group) {
    var normalizedGroup = group || 'all';
    var contextGroup = root ? (root.getAttribute('data-sffc-community-context-group') || '') : '';

    if ((normalizedGroup === 'all' || normalizedGroup === '') && contextGroup) {
      return contextGroup;
    }

    return normalizedGroup;
  }

  function getCommunityLinkedinPostsLayout(root) {
    return root ? root.querySelector('[data-sffc-community-linkedin-posts-layout]') : null;
  }

  function getCommunityLinkedinPostId(card) {
    var postId = card ? parseInt(card.getAttribute('data-sffc-community-post-id') || '0', 10) : 0;
    return isNaN(postId) ? 0 : postId;
  }

  function setCommunityLinkedinActivePost(root, postId, openMobileDetail) {
    var layout = getCommunityLinkedinPostsLayout(root);
    var detailWrap = root ? root.querySelector('[data-sffc-community-linkedin-detail]') : null;
    var selectedId = parseInt(postId || '0', 10);

    if (!layout || !detailWrap || isNaN(selectedId) || selectedId <= 0) {
      return false;
    }

    var selectedPanel = detailWrap.querySelector('[data-sffc-linkedin-jobs-detail-panel="' + selectedId + '"]');
    if (!selectedPanel) {
      return false;
    }

    layout.querySelectorAll('[data-sffc-community-post-id]').forEach(function (card) {
      var isSelected = getCommunityLinkedinPostId(card) === selectedId;
      card.classList.toggle('is-active', isSelected);
      card.setAttribute('aria-current', isSelected ? 'true' : 'false');
      card.querySelectorAll('.sffc-community-editorial__post-result').forEach(function (result) {
        result.classList.toggle('is-active', isSelected);
        result.setAttribute('aria-current', isSelected ? 'true' : 'false');
      });
    });

    detailWrap.querySelectorAll('[data-sffc-linkedin-jobs-detail-panel]').forEach(function (panel) {
      var isSelected = panel === selectedPanel;
      panel.classList.toggle('is-active', isSelected);
      panel.hidden = !isSelected;
    });

    if (openMobileDetail) {
      layout.classList.add('is-mobile-detail-open');
    }

    return true;
  }

  function refreshCommunityLinkedinPostsLayout(root) {
    var layout = getCommunityLinkedinPostsLayout(root);

    if (!layout) {
      return;
    }

    var activeCard = layout.querySelector('[data-sffc-community-post-id].is-active:not([hidden])');
    if (activeCard && setCommunityLinkedinActivePost(root, getCommunityLinkedinPostId(activeCard))) {
      return;
    }

    var firstCard = layout.querySelector('[data-sffc-community-post-id]:not([hidden])');
    if (firstCard) {
      setCommunityLinkedinActivePost(root, getCommunityLinkedinPostId(firstCard));
    }
  }

  function requestCommunityFeed(root, options) {
    if (!root) {
      return Promise.resolve();
    }

    var settings = options || {};
    var postsWrap = root.querySelector('.sffc-community-editorial__posts');
    var requestBody = new FormData();
    var filters = getCommunityRequestFilters(root);
    var batchSize = settings.batchSize || 20;

    if (!postsWrap) {
      return Promise.resolve();
    }

    requestBody.append('action', 'sffc_crm_editorial_filter_feed');
    requestBody.append('nonce', config.filterFeedNonce || '');
    requestBody.append('batch_size', String(batchSize));
    requestBody.append('group', filters.group);
    requestBody.append('search', filters.search);
    requestBody.append('location', filters.location);
    requestBody.append('seniority', filters.seniority);
    requestBody.append('qualification', filters.qualification);
    requestBody.append('posted_date', filters.postedDate);
    requestBody.append('company', filters.company);
    requestBody.append('sector', filters.sector);
    requestBody.append('work_type', filters.workType);
    requestBody.append('match_rank', filters.match);
    requestBody.append('recency', filters.recency);
    requestBody.append('trackers', filters.trackers.join('|'));
    requestBody.append('guest_cv_token', getCommunityGuestCvToken(root));
    requestBody.append('render_mode', (root.getAttribute('data-active-tab') || '') === 'early_bird' ? 'qualified_combined' : 'trackers');
    if (settings.focusPostId) {
      requestBody.append('focus_post_id', String(settings.focusPostId));
    }

    root.classList.add('is-loading');

    return window.fetch((config.ajaxUrl || '/wp-admin/admin-ajax.php'), {
      method: 'POST',
      body: requestBody,
      credentials: 'same-origin'
    })
      .then(parseAjaxJson)
      .then(function (payload) {
        if (!payload || !payload.success || !payload.data) {
          throw new Error((payload && payload.data && payload.data.message) || 'Unable to filter posts.');
        }

        postsWrap.innerHTML = String(payload.data.html || '');
        var detailWrap = root.querySelector('[data-sffc-community-linkedin-detail]');
        if (detailWrap && Object.prototype.hasOwnProperty.call(payload.data, 'detailHtml')) {
          detailWrap.innerHTML = String(payload.data.detailHtml || '');
        }
        normalizeCommunityPostActionButtons(postsWrap);
        updateCommunitySavedTrackerButtons(root);
        root.__sffcCommunityHasMoreByGroup = root.__sffcCommunityHasMoreByGroup || {};
        root.__sffcCommunityHasMoreByGroup[filters.group] = !!payload.data.hasMore;
        updateLoadMoreState(root);
        updateCommunityAppliedFilters(root);
        refreshCommunityLinkedinPostsLayout(root);

        if (settings.scrollToResults) {
          var firstPost = postsWrap.querySelector('[data-sffc-community-post-groups]');
          var emptyState = postsWrap.querySelector('[data-sffc-community-filter-empty], .sffc-community-editorial__empty');
          var target = firstPost || emptyState;
          if (target && typeof target.scrollIntoView === 'function') {
            window.setTimeout(function () {
              target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
              });
            }, 20);
          }
        }
      })
      .finally(function () {
        root.classList.remove('is-loading');
      });
  }

  function setOnboardingStep(root, step) {
    var overlay = root.querySelector('[data-sffc-community-onboarding]');
    if (!overlay) {
      return;
    }
    var backButton = overlay.querySelector('[data-sffc-community-onboarding-back]');

    overlay.querySelectorAll('[data-sffc-community-onboarding-step]').forEach(function (panel) {
      panel.hidden = panel.getAttribute('data-sffc-community-onboarding-step') !== step;
    });

    if (backButton) {
      backButton.hidden = step === 'access' || step === 'location';
    }

    root.setAttribute('data-sffc-community-onboarding-step', step);
    if (step === 'plan') {
      updateOnboardingPlanSummary(root);
    }
  }

  function showOnboarding(root) {
    var overlay = root.querySelector('[data-sffc-community-onboarding]');
    if (!overlay) {
      return;
    }

    var dialog = overlay.querySelector('.sffc-community-editorial__onboarding-dialog');
    overlay.hidden = false;

    if (dialog && typeof dialog.focus === 'function') {
      window.setTimeout(function () {
        dialog.focus();
      }, 20);
    }
  }

  function hideOnboarding(root) {
    var overlay = root.querySelector('[data-sffc-community-onboarding]');
    if (!overlay) {
      return;
    }

    overlay.hidden = true;
    root.setAttribute('data-sffc-community-onboarding-active', 'false');
  }

  function openCommunityDirectModal(shell) {
    if (!shell) {
      return;
    }

    var modal = shell.querySelector('[data-sffc-community-direct-modal]');
    var dialog = modal ? modal.querySelector('.sffc-match-modal-content') : null;
    if (!modal) {
      return;
    }

    modal.hidden = false;
    document.documentElement.classList.add('sffc-community-editorial-modal-open');
    document.body.classList.add('sffc-community-editorial-modal-open');
    document.body.classList.add('sffc-match-modal-open');

    if (dialog && typeof dialog.focus === 'function') {
      window.setTimeout(function () {
        dialog.focus();
      }, 20);
    }
  }

  function closeCommunityDirectModal(shell) {
    if (!shell) {
      return;
    }

    var modal = shell.querySelector('[data-sffc-community-direct-modal]');
    if (!modal) {
      return;
    }

    modal.hidden = true;
    document.documentElement.classList.remove('sffc-community-editorial-modal-open');
    document.body.classList.remove('sffc-community-editorial-modal-open');
    document.body.classList.remove('sffc-match-modal-open');
  }

  function toggleCommunityDirectEmailPanel(trigger) {
    if (!trigger) {
      return;
    }

    var card = trigger.closest('.sffc-community-editorial__direct-email-card');
    var panel = card ? card.querySelector('[data-sffc-community-direct-email-panel]') : null;
    if (!panel) {
      return;
    }

    var modal = trigger.closest('[data-sffc-community-direct-modal]');
    if (modal) {
      modal.querySelectorAll('[data-sffc-community-direct-email-panel]').forEach(function (otherPanel) {
        if (otherPanel !== panel) {
          otherPanel.hidden = true;
          var otherToggle = otherPanel.closest('.sffc-community-editorial__direct-email-card');
          otherToggle = otherToggle ? otherToggle.querySelector('[data-sffc-community-direct-email-toggle]') : null;
          if (otherToggle) {
            otherToggle.setAttribute('aria-expanded', 'false');
          }
        }
      });
    }

    var shouldOpen = panel.hidden;
    panel.hidden = !shouldOpen;
    trigger.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');

    if (shouldOpen) {
      window.setTimeout(function () {
        var input = panel.querySelector('[data-sffc-community-direct-email-input]');
        if (input && typeof input.focus === 'function') {
          input.focus();
        }
      }, 20);
    }
  }

  function validateCommunityDirectEmail(link) {
    var card = link ? link.closest('.sffc-community-editorial__direct-email-card') : null;
    var input = card ? card.querySelector('[data-sffc-community-direct-email-input]') : null;
    if (!input) {
      return true;
    }

    var value = (input.value || '').trim();
    var isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
    input.classList.toggle('is-invalid', !isValid);

    if (!isValid && typeof input.focus === 'function') {
      input.focus();
    }

    return isValid;
  }

  function toggleLinkedinJobsGetStartedPanel(trigger) {
    if (!trigger) {
      return;
    }

    var card = trigger.closest('[data-sffc-linkedin-jobs-get-started-card]');
    var panel = card ? card.querySelector('[data-sffc-linkedin-jobs-get-started-panel]') : null;
    if (!panel) {
      return;
    }

    var modal = trigger.closest('[data-sffc-linkedin-jobs-apply-modal]');
    if (modal) {
      modal.querySelectorAll('[data-sffc-linkedin-jobs-get-started-panel], [data-sffc-linkedin-jobs-direct-email-panel]').forEach(function (otherPanel) {
        if (otherPanel !== panel) {
          otherPanel.hidden = true;
          var otherToggle = otherPanel.closest('[data-sffc-linkedin-jobs-get-started-card]');
          otherToggle = otherToggle ? otherToggle.querySelector('[data-sffc-linkedin-jobs-get-started-toggle]') : null;
          if (!otherToggle) {
            otherToggle = otherPanel.closest('[data-sffc-linkedin-jobs-direct-email-card]');
            otherToggle = otherToggle ? otherToggle.querySelector('[data-sffc-linkedin-jobs-direct-email-toggle]') : null;
          }
          if (otherToggle) {
            otherToggle.setAttribute('aria-expanded', 'false');
          }
        }
      });
    }

    var shouldOpen = panel.hidden;
    panel.hidden = !shouldOpen;
    trigger.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');

    if (shouldOpen) {
      window.setTimeout(function () {
        var input = panel.querySelector('[data-sffc-linkedin-jobs-get-started-email]');
        if (input && typeof input.focus === 'function') {
          input.focus();
        }
      }, 20);
    }
  }

  function validateLinkedinJobsGetStartedForm(link) {
    var card = link ? link.closest('[data-sffc-linkedin-jobs-get-started-card]') : null;
    var fields = card ? Array.prototype.slice.call(card.querySelectorAll('[data-sffc-linkedin-jobs-get-started-required]')) : [];
    var isValid = true;

    fields.forEach(function (field) {
      var fieldValid = typeof field.checkValidity === 'function' ? field.checkValidity() : (field.value || '').trim() !== '';
      field.classList.toggle('is-invalid', !fieldValid);
      if (!fieldValid && isValid && typeof field.reportValidity === 'function') {
        field.reportValidity();
      }
      isValid = isValid && fieldValid;
    });

    if (isValid && link && window.URL) {
      try {
        var url = new URL(link.href, window.location.href);
        fields.forEach(function (field) {
          if (field.name && field.value) {
            url.searchParams.set(field.name, field.value.trim());
          }
        });
        link.href = url.toString();
      } catch (error) {}
    }

    return isValid;
  }

  function scheduleCommunityDirectApplyFollowup(root, link) {
    var card;
    var input;
    var requestBody;

    if (!root || !link || !window.fetch) {
      return;
    }

    card = link.closest('.sffc-community-editorial__direct-email-card');
    input = card ? card.querySelector('[data-sffc-community-direct-email-input]') : null;
    if (!input || !input.value) {
      return;
    }

    requestBody = new URLSearchParams();
    requestBody.set('action', 'sffc_crm_editorial_schedule_direct_apply_followup');
    requestBody.set('nonce', config.crmNonce || '');
    requestBody.set('email', String(input.value || '').trim());
    requestBody.set('crm_post_id', String(link.getAttribute('data-crm-post-id') || '0'));
    requestBody.set('apply_url', String(link.getAttribute('href') || ''));

    window.fetch((config.ajaxUrl || '/wp-admin/admin-ajax.php'), {
      method: 'POST',
      body: requestBody,
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      credentials: 'same-origin',
      keepalive: true
    }).catch(function () {
      return null;
    });
  }

  function closeCommunityAlertPanels(root, exceptPanel) {
    if (!root) {
      return;
    }

    root.querySelectorAll('[data-sffc-community-alert-panel]').forEach(function (panel) {
      if (exceptPanel && panel === exceptPanel) {
        return;
      }
      panel.hidden = true;
      var panelId = panel.getAttribute('id') || '';
      var toggle = panelId && panel.parentNode ? panel.parentNode.querySelector('[data-sffc-community-alert-toggle][aria-controls="' + panelId + '"]') : null;
      if (!toggle && panel.parentNode) {
        toggle = panel.parentNode.querySelector('[data-sffc-community-alert-toggle]');
      }
      if (toggle) {
        toggle.setAttribute('aria-expanded', 'false');
      }
    });
  }

  function positionCommunityToolbarAlertPanel(button, panel) {
    if (!button || !panel || !panel.classList.contains('sffc-community-editorial__alert-panel--toolbar')) {
      return;
    }

    if (window.matchMedia('(max-width: 767px)').matches) {
      panel.style.top = '';
      panel.style.right = '';
      panel.style.bottom = '';
      panel.style.left = '';
      return;
    }

    var rect = button.getBoundingClientRect();
    var panelWidth = Math.min(390, Math.max(320, panel.offsetWidth || 390));
    var viewportWidth = window.innerWidth || document.documentElement.clientWidth || 0;
    var left = Math.max(16, Math.min(rect.left, viewportWidth - panelWidth - 16));

    panel.style.top = Math.round(rect.bottom + 10) + 'px';
    panel.style.left = Math.round(left) + 'px';
    panel.style.right = 'auto';
    panel.style.bottom = 'auto';
  }

  function toggleCommunityAlertPanel(root, button) {
    if (!root || !button) {
      return;
    }

    var panelId = button.getAttribute('aria-controls') || '';
    var panel = panelId ? document.getElementById(panelId) : null;
    if (panel && !root.contains(panel)) {
      panel = null;
    }
    if (!panel) {
      return;
    }

    var shouldOpen = panel.hidden;
    closeCommunityAlertPanels(root, panel);
    panel.hidden = !shouldOpen;
    button.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');

    if (shouldOpen) {
      positionCommunityToolbarAlertPanel(button, panel);
      var alertSetup = panel.querySelector('[data-sffc-community-alert-setup]');
      var alertBenefits = panel.querySelector('[data-sffc-community-alert-benefits]');
      if (alertSetup && alertBenefits) {
        alertSetup.hidden = false;
        alertBenefits.hidden = true;
      }
      var exportSetup = panel.querySelector('[data-sffc-community-export-setup]');
      var exportBenefits = panel.querySelector('[data-sffc-community-export-benefits]');
      if (exportSetup && exportBenefits) {
        exportSetup.hidden = false;
        exportBenefits.hidden = true;
      }
      var selectedTrackers = getSelectedCommunityTrackers(root);
      panel.querySelectorAll('[data-sffc-community-alert-tracker-select]').forEach(function (trackerSelect) {
        if (selectedTrackers.length && !trackerSelect.value) {
          trackerSelect.value = selectedTrackers[0];
        }
        syncCommunityAlertTrackerFields(trackerSelect.closest('[data-sffc-community-alert-form]'));
      });
      var input = panel.querySelector('input[name="full_name"], input[name="email"]');
      if (input && typeof input.focus === 'function') {
        window.setTimeout(function () {
          input.focus();
        }, 20);
      }
    }
  }

  function syncCommunityAlertTrackerFields(form) {
    if (!form) {
      return;
    }

    var trackerSelect = form.querySelector('[data-sffc-community-alert-tracker-select]');
    if (!trackerSelect) {
      return;
    }

    var selectedOption = trackerSelect.options && trackerSelect.selectedIndex >= 0
      ? trackerSelect.options[trackerSelect.selectedIndex]
      : null;
    var trackerName = selectedOption
      ? (selectedOption.getAttribute('data-post-group-name') || selectedOption.textContent || '').trim()
      : '';
    var trackerSlug = selectedOption
      ? (selectedOption.getAttribute('data-post-group-slug') || trackerSelect.value || '').trim()
      : '';
    var trackerId = selectedOption
      ? (selectedOption.getAttribute('data-post-group-id') || '').trim()
      : '';

    var groupIdInput = form.querySelector('input[name="post_group_id"]');
    var groupSlugInput = form.querySelector('input[name="post_group_slug"]');
    var groupNameInput = form.querySelector('input[name="post_group_name"]');

    if (groupIdInput) {
      groupIdInput.value = trackerId;
    }
    if (groupSlugInput) {
      groupSlugInput.value = trackerSlug;
    }
    if (groupNameInput) {
      groupNameInput.value = trackerName || trackerSelect.value || '';
    }
  }

  function submitCommunityTrackerAlert(root, form) {
    if (!root || !form) {
      return;
    }

    syncCommunityAlertTrackerFields(form);

    var feedback = form.querySelector('[data-sffc-community-alert-feedback]');
    var submitButton = form.querySelector('button[type="submit"]');
    var defaultLabel = submitButton ? submitButton.textContent : '';
    var requestBody = new FormData(form);

    requestBody.append('action', 'sffc_crm_editorial_tracker_alert_subscribe');
    requestBody.append('nonce', config.trackerAlertsNonce || '');

    if (feedback) {
      feedback.hidden = false;
      feedback.classList.remove('is-error');
      feedback.textContent = 'Saving your alert...';
    }
    if (submitButton) {
      submitButton.disabled = true;
      submitButton.textContent = 'Saving...';
    }

    window.fetch((config.ajaxUrl || '/wp-admin/admin-ajax.php'), {
      method: 'POST',
      body: requestBody,
      credentials: 'same-origin'
    })
      .then(parseAjaxJson)
      .then(function (payload) {
        if (!payload || !payload.success) {
          throw new Error((payload && payload.data && payload.data.message) || 'Unable to save this alert.');
        }

        var data = payload.data || {};
        if (feedback) {
          feedback.classList.remove('is-error');
          feedback.textContent = data.message || 'Alert saved. Check your email for the first role.';
        }

        if (data.redirectUrl) {
          window.setTimeout(function () {
            window.location.href = data.redirectUrl;
          }, 900);
        }
      })
      .catch(function (error) {
        if (feedback) {
          feedback.hidden = false;
          feedback.classList.add('is-error');
          feedback.textContent = error && error.message ? error.message : 'Unable to save this alert.';
        }
      })
      .finally(function () {
        if (submitButton) {
          submitButton.disabled = false;
          submitButton.textContent = defaultLabel || 'Send me alerts';
        }
      });
  }

  function openCommunityCompanyModal(root) {
    var modal = root ? root.querySelector('[data-sffc-community-company-modal]') : null;
    var dialog = modal ? modal.querySelector('.sffc-community-editorial__company-dialog') : null;
    if (!modal) {
      return;
    }

    modal.hidden = false;
    document.documentElement.classList.add('sffc-community-editorial-modal-open');
    document.body.classList.add('sffc-community-editorial-modal-open');

    if (dialog && typeof dialog.focus === 'function') {
      window.setTimeout(function () {
        dialog.focus();
      }, 20);
    }
  }

  function closeCommunityCompanyModal(root) {
    var modal = root ? root.querySelector('[data-sffc-community-company-modal]') : null;
    if (!modal) {
      return;
    }

    modal.hidden = true;
    document.documentElement.classList.remove('sffc-community-editorial-modal-open');
    document.body.classList.remove('sffc-community-editorial-modal-open');
  }

  function openCommunityDiscoveryIntroModal(root, trigger) {
    var modal = root ? root.querySelector('[data-sffc-community-discovery-intro-modal]') : null;
    var dialog = modal ? modal.querySelector('.sffc-community-editorial__discovery-intro-dialog') : null;
    var form = modal ? modal.querySelector('[data-sffc-community-discovery-intro-form]') : null;
    var avatar = modal ? modal.querySelector('[data-sffc-community-discovery-intro-avatar]') : null;
    var avatarFallback = modal ? modal.querySelector('[data-sffc-community-discovery-intro-avatar-fallback]') : null;
    var nameEl = modal ? modal.querySelector('[data-sffc-community-discovery-intro-name]') : null;
    var titleEl = modal ? modal.querySelector('[data-sffc-community-discovery-intro-title]') : null;
    var companyEl = modal ? modal.querySelector('[data-sffc-community-discovery-intro-company]') : null;
    var emailEl = modal ? modal.querySelector('[data-sffc-community-discovery-intro-email]') : null;
    var linkedinEl = modal ? modal.querySelector('[data-sffc-community-discovery-intro-linkedin]') : null;
    var feedback = modal ? modal.querySelector('[data-sffc-community-discovery-intro-feedback]') : null;
    var photoUrl;
    var recruiterName;
    var recruiterTitle;
    var companyName;
    var recruiterEmail;
    var recruiterLinkedin;
    var recruiterId;
    var roleTitle;
    var postId;
    var sourceAction;
    var initial;
    var avatarImage;

    if (!modal || !form || !trigger) {
      return;
    }

    root.__sffcCommunityDiscoveryIntroTrigger = trigger;

    photoUrl = String(trigger.getAttribute('data-sffc-community-discovery-photo') || '').trim();
    recruiterName = String(trigger.getAttribute('data-sffc-community-discovery-name') || '').trim();
    recruiterTitle = String(trigger.getAttribute('data-sffc-community-discovery-title') || '').trim();
    companyName = String(trigger.getAttribute('data-sffc-community-discovery-company') || '').trim();
    recruiterEmail = String(trigger.getAttribute('data-sffc-community-discovery-email') || '').trim();
    recruiterLinkedin = String(trigger.getAttribute('data-sffc-community-discovery-linkedin') || '').trim();
    recruiterId = String(trigger.getAttribute('data-sffc-community-discovery-recruiter-id') || '').trim();
    roleTitle = String(trigger.getAttribute('data-sffc-community-role-title') || '').trim();
    postId = String(trigger.getAttribute('data-sffc-community-post-id') || '').trim();
    sourceAction = String(trigger.getAttribute('data-sffc-community-source-action') || 'recruiter_discovery').trim();
    initial = String(trigger.getAttribute('data-sffc-community-discovery-initial') || 'R').trim() || 'R';

    form.reset();
    form.elements.recruiter_id.value = recruiterId;
    form.elements.recruiter_name.value = recruiterName;
    form.elements.recruiter_title.value = recruiterTitle;
    form.elements.company_name.value = companyName;
    form.elements.recruiter_email.value = recruiterEmail;
    form.elements.recruiter_linkedin.value = recruiterLinkedin;
    form.elements.recruiter_photo.value = photoUrl;
    if (form.elements.role_title) {
      form.elements.role_title.value = roleTitle;
    }
    if (form.elements.post_id) {
      form.elements.post_id.value = postId;
    }
    if (form.elements.source_action) {
      form.elements.source_action.value = sourceAction;
    }

    if (feedback) {
      feedback.hidden = true;
      feedback.textContent = '';
      feedback.classList.remove('is-error', 'is-success');
    }

    if (nameEl) {
      nameEl.textContent = recruiterName || 'Recruiter contact';
    }
    if (titleEl) {
      titleEl.textContent = recruiterTitle || 'Recruitment contact';
    }
    if (companyEl) {
      companyEl.textContent = companyName || 'Company';
    }
    if (emailEl) {
      emailEl.textContent = recruiterEmail || 'Not available';
    }
    if (linkedinEl) {
      if (recruiterLinkedin) {
        linkedinEl.innerHTML = '';
        var link = document.createElement('a');
        link.href = recruiterLinkedin;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        link.textContent = 'Open profile';
        linkedinEl.appendChild(link);
      } else {
        linkedinEl.textContent = 'Not available';
      }
    }

    if (avatar) {
      avatar.classList.toggle('has-photo', !!photoUrl);
      avatarImage = avatar.querySelector('img');
      if (photoUrl) {
        if (!avatarImage) {
          avatarImage = document.createElement('img');
          avatar.insertBefore(avatarImage, avatar.firstChild);
        }
        avatarImage.src = photoUrl;
        avatarImage.alt = recruiterName || 'Recruiter';
      } else if (avatarImage) {
        avatarImage.remove();
      }
    }
    if (avatarFallback) {
      avatarFallback.hidden = !!photoUrl;
      avatarFallback.textContent = initial;
    }

    modal.hidden = false;
    document.documentElement.classList.add('sffc-community-editorial-modal-open');
    document.body.classList.add('sffc-community-editorial-modal-open');

    if (dialog && typeof dialog.focus === 'function') {
      window.setTimeout(function () {
        dialog.focus();
      }, 20);
    }
  }

  function closeCommunityDiscoveryIntroModal(root) {
    var modal = root ? root.querySelector('[data-sffc-community-discovery-intro-modal]') : null;
    if (!modal) {
      return;
    }

    modal.hidden = true;
    root.__sffcCommunityDiscoveryIntroTrigger = null;
    root.__sffcCommunityDiscoveryIntroSecondaryTrigger = null;
    document.documentElement.classList.remove('sffc-community-editorial-modal-open');
    document.body.classList.remove('sffc-community-editorial-modal-open');
  }

  function submitCommunityDiscoveryIntro(root, form) {
    var feedback = form ? form.querySelector('[data-sffc-community-discovery-intro-feedback]') : null;
    var submitButton = form ? form.querySelector('[data-sffc-community-discovery-intro-submit]') : null;
    var cvSelect = form ? form.querySelector('[data-sffc-community-discovery-intro-cv]') : null;
    var formData;
    var selectedOption;

    if (!root || !form) {
      return;
    }

    formData = new FormData(form);
    selectedOption = cvSelect ? cvSelect.options[cvSelect.selectedIndex] : null;
    formData.append('action', 'sffc_crm_submit_discovery_intro_request');
    formData.append('nonce', config.crmNonce || '');
    formData.append('stored_cv_name', selectedOption ? String(selectedOption.getAttribute('data-cv-name') || '') : '');

    if (feedback) {
      feedback.hidden = true;
      feedback.textContent = '';
      feedback.classList.remove('is-error', 'is-success');
    }

    if (submitButton) {
      submitButton.disabled = true;
      submitButton.textContent = 'Submitting...';
    }

    window.fetch((config.ajaxUrl || '/wp-admin/admin-ajax.php'), {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
    })
      .then(parseAjaxJson)
      .then(function (payload) {
        if (!payload || !payload.success) {
          throw new Error((payload && payload.data && payload.data.message) || 'Unable to submit intro request.');
        }

        if (feedback) {
          feedback.hidden = false;
          feedback.classList.remove('is-error');
          feedback.classList.add('is-success');
          feedback.textContent = (payload.data && payload.data.message) || 'Intro request submitted successfully.';
        }

        if (payload && payload.data && payload.data.request) {
          prependCommunityIntroRequest(root, payload.data.request);
          activateTab(root, 'network');
          activateIntrosSubtab(root, 'intros');
        }

        setCommunitySendIntroTriggerInProgress(root.__sffcCommunityDiscoveryIntroTrigger);
        setCommunitySendIntroTriggerInProgress(root.__sffcCommunityDiscoveryIntroSecondaryTrigger);

        window.setTimeout(function () {
          closeCommunityDiscoveryIntroModal(root);
        }, 1400);
      })
      .catch(function (error) {
        if (feedback) {
          feedback.hidden = false;
          feedback.classList.remove('is-success');
          feedback.classList.add('is-error');
          feedback.textContent = error && error.message ? error.message : 'Unable to submit intro request.';
        }
      })
      .finally(function () {
        if (submitButton) {
          submitButton.disabled = false;
          submitButton.textContent = 'Confirm Intro Request';
        }
      });
  }

  function setCommunitySendIntroTriggerInProgress(button) {
    if (!button) {
      return;
    }

    button.classList.add('is-send-intro-progress');

    if (button.tagName === 'BUTTON') {
      button.disabled = true;
    }

    button.setAttribute('aria-disabled', 'true');
    button.textContent = 'In Progress';
  }

  function prependCommunityIntroRequest(root, request) {
    var panel = root ? root.querySelector('[data-sffc-community-intros-tab-panel="intros"]') : null;
    var grid;
    var empty;
    var table;
    var tbody;
    var row;
    var name = request && request.name ? String(request.name) : 'Recruiter contact';
    var title = request && request.title ? String(request.title) : '';
    var company = request && request.company ? String(request.company) : '';
    var statusLabel = request && request.status_label ? String(request.status_label) : 'Queued';
    var companyInfo = request && request.company_info ? String(request.company_info) : '';
    var email = request && request.email ? String(request.email) : '';
    var linkedinUrl = request && request.linkedin ? String(request.linkedin) : '';
    var subject = request && request.subject ? String(request.subject) : '';
    var messageBody = request && request.body ? String(request.body) : '';
    var attachments = request && request.attachments ? String(request.attachments) : '';
    var sentAt = request && request.sent_at ? String(request.sent_at) : 'Queued';
    var avatarUrl = request && request.avatar_url ? String(request.avatar_url) : '';
    var avatarInitial = request && request.avatar_initial ? String(request.avatar_initial) : 'R';

    if (!panel || !request) {
      return;
    }

    grid = panel.querySelector('.sffc-community-editorial__reached-grid');
    empty = panel.querySelector('.sffc-community-editorial__empty');

    if (!grid) {
      grid = document.createElement('div');
      grid.className = 'sffc-community-editorial__reached-grid';
      panel.insertBefore(grid, empty || null);
    }

    table = grid.querySelector('.sffc-community-editorial__reached-table');
    tbody = table ? table.querySelector('tbody') : null;

    if (!table || !tbody) {
      grid.innerHTML =
        '<div class="sffc-community-editorial__reached-table-wrap">' +
          '<table class="sffc-community-editorial__reached-table">' +
            '<thead><tr>' +
              '<th>Recruiter</th>' +
              '<th>Company</th>' +
              '<th>Status</th>' +
              '<th>Materials</th>' +
              '<th>Sent</th>' +
              '<th>Action</th>' +
            '</tr></thead>' +
            '<tbody></tbody>' +
          '</table>' +
        '</div>';
      table = grid.querySelector('.sffc-community-editorial__reached-table');
      tbody = table ? table.querySelector('tbody') : null;
    }

    if (empty) {
      empty.remove();
    }

    if (request.id) {
      var existing = tbody ? tbody.querySelector('[data-sffc-community-intro-request-id="' + String(request.id).replace(/"/g, '&quot;') + '"]') : null;
      if (existing) {
        existing.remove();
      }
    }

    if (!tbody) {
      return;
    }

    row = document.createElement('tr');
    if (request.id) {
      row.setAttribute('data-sffc-community-intro-request-id', String(request.id));
    }

    row.innerHTML =
      '<td>' +
        '<div class="sffc-community-editorial__reached-person">' +
          '<div class="sffc-community-editorial__reached-avatar-table">' +
            (avatarUrl
              ? '<img src="' + escapeCommunityHtml(avatarUrl) + '" alt="' + escapeCommunityHtml(name) + '">'
              : '<span>' + escapeCommunityHtml(avatarInitial) + '</span>') +
          '</div>' +
          '<div class="sffc-community-editorial__reached-person-copy">' +
            '<strong>' + escapeCommunityHtml(name) + '</strong>' +
            (title ? '<span>' + escapeCommunityHtml(title) + '</span>' : '') +
          '</div>' +
        '</div>' +
      '</td>' +
      '<td><div class="sffc-community-editorial__reached-company-cell"><strong>' + escapeCommunityHtml(company || 'Company') + '</strong></div></td>' +
      '<td><span class="sffc-community-editorial__reached-status">' + escapeCommunityHtml(statusLabel) + '</span></td>' +
      '<td><span class="sffc-community-editorial__reached-materials">Tailored CV</span></td>' +
      '<td><span class="sffc-community-editorial__reached-sent">' + escapeCommunityHtml(sentAt) + '</span></td>' +
      '<td>' +
        '<button type="button" class="sffc-community-editorial__reached-cta"' +
        ' data-sffc-community-reached-open' +
        ' data-name="' + escapeCommunityHtml(name) + '"' +
        ' data-title="' + escapeCommunityHtml(title) + '"' +
        ' data-company="' + escapeCommunityHtml(company) + '"' +
        ' data-company-info="' + escapeCommunityHtml(companyInfo) + '"' +
        ' data-email="' + escapeCommunityHtml(email) + '"' +
        ' data-linkedin="' + escapeCommunityHtml(linkedinUrl) + '"' +
        ' data-subject="' + escapeCommunityHtml(subject) + '"' +
        ' data-body="' + escapeCommunityHtml(messageBody) + '"' +
        ' data-attachments="' + escapeCommunityHtml(attachments) + '"' +
        ' data-sent-at="' + escapeCommunityHtml(sentAt) + '"' +
        ' data-avatar-url="' + escapeCommunityHtml(avatarUrl) + '"' +
        ' data-avatar-initial="' + escapeCommunityHtml(avatarInitial) + '">' +
        'View Message</button>' +
      '</td>';

    tbody.insertBefore(row, tbody.firstChild);
  }

  function escapeCommunityHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function updateLinkedinJobsProfileReviewFeature(panel, key) {
    var data = {
      cv: {
        image: 'https://media.joinsenna.com/2026/08/vecteezy_powerful-indian-businessman_24753938_529-scaled.jpg',
        title: 'CV evidence check',
        copy: 'The recruiter expert checks role level, sector signal, transaction evidence, numbers, and whether your CV gives enough proof to shortlist.'
      },
      linkedin: {
        image: 'https://media.joinsenna.com/2026/08/vecteezy_young-woman-working-on-laptop-in-co-working-office_29052432_973-scaled.jpg',
        title: 'LinkedIn positioning review',
        copy: 'The recruiter expert checks headline clarity, location signal, sector keywords, credibility markers, and whether hiring teams can quickly understand your fit.'
      },
      expert: {
        image: 'https://media.joinsenna.com/2026/08/vecteezy_man-using-chatbot-in-computer-and-tablet-smart-intelligence_33509648-scaled.jpg',
        title: 'Written expert feedback within 24 hours',
        copy: 'You receive focused observations on what to strengthen before applying, what may weaken the profile, and how to position the application.'
      }
    };
    var selected = data[key] || data.cv;
    var feature = panel ? panel.querySelector('[data-sffc-linkedin-jobs-profile-review-feature]') : null;
    if (!feature) {
      return;
    }
    var image = feature.querySelector('img');
    var title = feature.querySelector('strong');
    var copy = feature.querySelector('p');
    if (image) {
      image.src = selected.image;
    }
    if (title) {
      title.textContent = selected.title;
    }
    if (copy) {
      copy.textContent = selected.copy;
    }
    panel.querySelectorAll('[data-sffc-linkedin-jobs-profile-review-tab]').forEach(function (tab) {
      tab.classList.toggle('is-active', (tab.getAttribute('data-sffc-linkedin-jobs-profile-review-tab') || 'cv') === key);
    });
  }

  function setLinkedinJobsProfileReviewFeedback(form, message, isError) {
    var feedback = form ? form.querySelector('[data-sffc-linkedin-jobs-profile-review-feedback]') : null;
    if (!feedback) {
      return;
    }
    feedback.hidden = false;
    feedback.textContent = message || '';
    feedback.classList.toggle('is-error', !!isError);
  }

  function updateLinkedinJobsProfileReviewStatus(form, step) {
    var status = form ? form.querySelector('[data-sffc-linkedin-jobs-profile-review-status]') : null;
    if (!status) {
      return;
    }
    status.querySelectorAll('span').forEach(function (item, index) {
      item.classList.toggle('is-active', index <= step);
    });
  }

  function setLinkedinJobsProfileReviewStatusLabels(form, labels) {
    var status = form ? form.querySelector('[data-sffc-linkedin-jobs-profile-review-status]') : null;
    if (!status || !labels) {
      return;
    }
    status.querySelectorAll('span').forEach(function (item, index) {
      if (typeof labels[index] !== 'undefined') {
        item.textContent = labels[index];
      }
    });
  }

  function setLinkedinJobsProfileReviewStep(panel, step) {
    var form = panel ? panel.querySelector('[data-sffc-linkedin-jobs-profile-review-form]') : null;
    if (!form) {
      return;
    }
    var steps = form.querySelectorAll('[data-sffc-linkedin-jobs-profile-review-step]');
    var maxStep = Math.max(0, steps.length - 1);
    var nextStep = Math.max(0, Math.min(maxStep, parseInt(step, 10) || 0));
    form.setAttribute('data-sffc-linkedin-jobs-profile-review-current-step', String(nextStep));
    steps.forEach(function (item) {
      var isActive = (parseInt(item.getAttribute('data-sffc-linkedin-jobs-profile-review-step'), 10) || 0) === nextStep;
      item.hidden = !isActive;
      item.classList.toggle('is-active', isActive);
    });
    form.querySelectorAll('[data-sffc-linkedin-jobs-profile-review-progress]').forEach(function (item) {
      var index = parseInt(item.getAttribute('data-sffc-linkedin-jobs-profile-review-progress'), 10) || 0;
      item.classList.toggle('is-active', index <= nextStep);
      item.classList.toggle('is-current', index === nextStep);
    });
    var cvInput = form.querySelector('[data-sffc-linkedin-jobs-profile-review-file]');
    var linkedinInput = form.querySelector('input[name="linkedin_profile_url"]');
    var notesInput = form.querySelector('textarea[name="review_notes"]');
    var hasCv = !!(cvInput && cvInput.files && cvInput.files.length);
    var hasLinkedin = !!(linkedinInput && String(linkedinInput.value || '').trim() !== '');
    var hasContext = !!(notesInput && String(notesInput.value || '').trim() !== '');
    setLinkedinJobsProfileReviewStatusLabels(form, [
      hasCv ? 'CV received' : 'CV pending',
      hasLinkedin && nextStep >= 3 ? 'LinkedIn attached' : 'LinkedIn pending',
      nextStep >= 4 ? (hasContext ? 'Context added' : 'Context skipped') : 'Context pending',
      'Career Assessment queued'
    ]);
    updateLinkedinJobsProfileReviewStatus(form, Math.max(0, Math.min(2, nextStep - 1)));
  }

  function validateLinkedinJobsProfileReviewStep(panel) {
    var form = panel ? panel.querySelector('[data-sffc-linkedin-jobs-profile-review-form]') : null;
    if (!form) {
      return false;
    }
    var currentStep = parseInt(form.getAttribute('data-sffc-linkedin-jobs-profile-review-current-step'), 10) || 0;
    var step = form.querySelector('[data-sffc-linkedin-jobs-profile-review-step="' + currentStep + '"]');
    var fields = step ? step.querySelectorAll('input, textarea, select') : [];
    for (var i = 0; i < fields.length; i += 1) {
      if (typeof fields[i].checkValidity === 'function' && !fields[i].checkValidity()) {
        if (typeof fields[i].reportValidity === 'function') {
          fields[i].reportValidity();
        } else if (typeof form.reportValidity === 'function') {
          form.reportValidity();
        }
        return false;
      }
    }
    return true;
  }

  function formatLinkedinJobsProfileReviewFileSize(file) {
    var size = file && typeof file.size === 'number' ? file.size : 0;
    if (size >= 1024 * 1024) {
      return (size / (1024 * 1024)).toFixed(size >= 10 * 1024 * 1024 ? 0 : 1) + ' MB';
    }
    if (size >= 1024) {
      return Math.max(1, Math.round(size / 1024)) + ' KB';
    }
    return size ? size + ' B' : 'Uploaded CV';
  }

  function buildLinkedinJobsProfileReviewUploadPreviewCard(file) {
    var fileName = file && file.name ? String(file.name) : 'Uploaded CV';
    var fileUrl = window.URL && window.URL.createObjectURL ? window.URL.createObjectURL(file) : '';
    var isPdf = /\.pdf$/i.test(fileName) || (file && file.type === 'application/pdf');
    var isText = /\.txt$/i.test(fileName) || (file && file.type === 'text/plain');
    var meta = escapeCommunityHtml(formatLinkedinJobsProfileReviewFileSize(file)) + ' · Ready for expert review';

    if (isPdf && fileUrl) {
      return {
        objectUrl: fileUrl,
        html:
          '<div class="sffc-crm-apply-chat__upload-preview-card sffc-crm-linkedin-jobs__profile-review-upload-card">' +
          '<div class="sffc-crm-apply-chat__upload-preview-sheet">' +
          '<object class="sffc-crm-apply-chat__upload-preview-object" data="' + escapeCommunityHtml(fileUrl) + '" type="application/pdf">' +
          '<a href="' + escapeCommunityHtml(fileUrl) + '" target="_blank" rel="noopener noreferrer">Open CV preview</a>' +
          '</object>' +
          '</div>' +
          '<div class="sffc-crm-apply-chat__upload-preview-meta"><strong>' + escapeCommunityHtml(fileName) + '</strong><span>' + meta + '</span></div>' +
          '</div>'
      };
    }

    if (isText && window.FileReader) {
      return { objectUrl: '', html: '', textPreview: true };
    }

    if (fileUrl) {
      window.setTimeout(function () {
        try {
          window.URL.revokeObjectURL(fileUrl);
        } catch (error) {}
      }, 1000);
    }

    return {
      objectUrl: '',
      html:
        '<div class="sffc-crm-apply-chat__upload-preview-card sffc-crm-linkedin-jobs__profile-review-upload-card is-file-only">' +
        '<div class="sffc-crm-apply-chat__upload-preview-meta"><strong>' + escapeCommunityHtml(fileName) + '</strong><span>' + meta + '</span></div>' +
        '</div>'
    };
  }

  function renderLinkedinJobsProfileReviewPreview(input) {
    var form = input ? input.closest('[data-sffc-linkedin-jobs-profile-review-form]') : null;
    var preview = form ? form.querySelector('[data-sffc-linkedin-jobs-profile-review-preview]') : null;
    var status = form ? form.querySelector('[data-sffc-linkedin-jobs-profile-review-file-status]') : null;
    var file = input && input.files ? input.files[0] : null;
    if (!preview || !file) {
      return;
    }
    if (status) {
      status.textContent = file.name + ' selected. Building preview for assessment comments.';
    }
    setLinkedinJobsProfileReviewStatusLabels(form, ['CV received', 'LinkedIn pending', 'Context pending', 'Career Assessment queued']);
    updateLinkedinJobsProfileReviewStatus(form, 0);
    if (preview.__sffcProfileReviewObjectUrl) {
      try {
        window.URL.revokeObjectURL(preview.__sffcProfileReviewObjectUrl);
      } catch (error) {}
      preview.__sffcProfileReviewObjectUrl = '';
    }
    var card = buildLinkedinJobsProfileReviewUploadPreviewCard(file);
    if (card.textPreview) {
      var reader = new FileReader();
      reader.onload = function () {
        preview.innerHTML =
          '<div class="sffc-crm-apply-chat__upload-preview-card sffc-crm-linkedin-jobs__profile-review-upload-card sffc-crm-linkedin-jobs__profile-review-upload-card--text">' +
          '<div class="sffc-crm-apply-chat__upload-preview-sheet"><pre>' + escapeCommunityHtml(String(reader.result || '').slice(0, 4000)) + '</pre></div>' +
          '<div class="sffc-crm-apply-chat__upload-preview-meta"><strong>' + escapeCommunityHtml(file.name) + '</strong><span>' + escapeCommunityHtml(formatLinkedinJobsProfileReviewFileSize(file)) + ' · Text preview</span></div>' +
          '</div>';
      };
      reader.readAsText(file);
      return;
    }
    preview.__sffcProfileReviewObjectUrl = card.objectUrl || '';
    preview.innerHTML = card.html;
  }

  function selectLinkedinJobsProfileReviewPackage(card) {
    var form = card ? card.closest('[data-sffc-linkedin-jobs-profile-review-form]') : null;
    if (!form) {
      return;
    }
    var packageKey = String(card.getAttribute('data-sffc-linkedin-jobs-profile-review-package') || '').trim();
    var membershipId = String(card.getAttribute('data-membership-id') || '').trim();
    var packagePrice = String(card.getAttribute('data-package-price') || '').trim();
    var packageInput = form.querySelector('[data-sffc-linkedin-jobs-profile-review-package-input]');
    var membershipInput = form.querySelector('[data-sffc-linkedin-jobs-profile-review-package-membership-input]');
    var priceInput = form.querySelector('[data-sffc-linkedin-jobs-profile-review-package-price-input]');

    form.querySelectorAll('[data-sffc-linkedin-jobs-profile-review-package]').forEach(function (item) {
      var isSelected = String(item.getAttribute('data-sffc-linkedin-jobs-profile-review-package') || '').trim() === packageKey;
      item.classList.toggle('is-selected', isSelected);
      item.classList.toggle('is-active', isSelected);
      item.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
      if (item.getAttribute('role') === 'tab') {
        item.setAttribute('aria-selected', isSelected ? 'true' : 'false');
      }
    });
    form.querySelectorAll('[data-sffc-profile-review-expert-package-panel]').forEach(function (panel) {
      var isActivePanel = panel.getAttribute('data-sffc-profile-review-expert-package-panel') === packageKey;
      panel.hidden = !isActivePanel;
      panel.classList.toggle('is-active', isActivePanel);
    });
    if (packageInput) {
      packageInput.value = packageKey;
    }
    if (membershipInput) {
      membershipInput.value = membershipId;
    }
    if (priceInput) {
      priceInput.value = packagePrice;
    }
  }

  function revealLinkedinJobsProfileReviewCheckout(form, packageKey) {
    var panel = form ? form.closest('[data-sffc-linkedin-jobs-profile-review-panel]') : null;
    var checkout = panel ? panel.querySelector('[data-sffc-linkedin-jobs-profile-review-checkout]') : null;
    if (!checkout) {
      return;
    }
    if (panel) {
      panel.classList.add('is-checkout-open');
    }
    checkout.hidden = false;
    checkout.querySelectorAll('[data-sffc-profile-review-checkout-package]').forEach(function (item) {
      item.hidden = item.getAttribute('data-sffc-profile-review-checkout-package') !== packageKey;
    });
    if (typeof checkout.scrollIntoView === 'function') {
      checkout.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }

  function submitLinkedinJobsProfileReview(form) {
    if (!form || !window.fetch || !window.FormData) {
      return;
    }
    var submit = form.querySelector('.sffc-crm-linkedin-jobs__profile-review-submit');
    var body = new FormData(form);
    body.append('action', 'sffc_linkedin_jobs_profile_review_request');
    body.append('nonce', config.crmNonce || '');
    if (submit) {
      submit.disabled = true;
      submit.classList.add('is-loading');
    }
    setLinkedinJobsProfileReviewFeedback(form, 'Sending your Career Assessment request to a recruiter expert...', false);
    setLinkedinJobsProfileReviewStatusLabels(form, ['CV received', 'LinkedIn attached', 'Context added', 'Sending to expert']);
    updateLinkedinJobsProfileReviewStatus(form, 1);
    window.fetch((config.ajaxUrl || '/wp-admin/admin-ajax.php'), {
      method: 'POST',
      body: body,
      credentials: 'same-origin'
    })
      .then(parseAjaxJson)
      .then(function (payload) {
        if (!payload || !payload.success) {
          throw new Error(payload && payload.data && payload.data.message ? payload.data.message : 'We could not send your assessment request.');
        }
        updateLinkedinJobsProfileReviewStatus(form, 3);
        setLinkedinJobsProfileReviewStatusLabels(form, ['CV received', 'LinkedIn attached', 'Context added', 'Career Assessment queued']);
        setLinkedinJobsProfileReviewFeedback(form, payload.data && payload.data.message ? payload.data.message : 'Your Career Assessment has been queued with a recruiter expert.', false);
        var preview = form.querySelector('[data-sffc-linkedin-jobs-profile-review-preview]');
        if (preview && payload.data && payload.data.cv_preview) {
          preview.insertAdjacentHTML('beforeend', '<div class="sffc-crm-linkedin-jobs__profile-review-comments is-complete"><strong>Assessment request sent</strong><span>CV text extracted</span><span>' + escapeCommunityHtml(String(payload.data.word_count || 0)) + ' words read</span><span>LinkedIn profile attached</span><span>Admin has been notified</span></div>');
        }
      })
      .catch(function (error) {
        updateLinkedinJobsProfileReviewStatus(form, 0);
        setLinkedinJobsProfileReviewStatusLabels(form, ['CV received', 'LinkedIn attached', 'Context added', 'Career Assessment not sent']);
        setLinkedinJobsProfileReviewFeedback(form, error && error.message ? error.message : 'We could not send your assessment request.', true);
      })
      .finally(function () {
        if (submit) {
          submit.disabled = false;
          submit.classList.remove('is-loading');
        }
      });
  }

  function openCommunityReachedModal(root, trigger) {
    var modal = root ? root.querySelector('[data-sffc-community-reached-modal]') : null;
    var dialog = modal ? modal.querySelector('.sffc-community-editorial__reached-dialog') : null;
    var name = String(trigger.getAttribute('data-name') || '').trim();
    var title = String(trigger.getAttribute('data-title') || '').trim();
    var company = String(trigger.getAttribute('data-company') || '').trim();
    var companyInfo = String(trigger.getAttribute('data-company-info') || '').trim();
    var email = String(trigger.getAttribute('data-email') || '').trim();
    var linkedin = String(trigger.getAttribute('data-linkedin') || '').trim();
    var subject = String(trigger.getAttribute('data-subject') || '').trim();
    var body = String(trigger.getAttribute('data-body') || '').trim();
    var attachments = String(trigger.getAttribute('data-attachments') || '').trim();
    var sentAt = String(trigger.getAttribute('data-sent-at') || '').trim();
    var avatarUrl = String(trigger.getAttribute('data-avatar-url') || '').trim();
    var avatarInitial = String(trigger.getAttribute('data-avatar-initial') || 'R').trim() || 'R';
    var avatar = modal ? modal.querySelector('[data-sffc-community-reached-avatar]') : null;
    var avatarFallback = modal ? modal.querySelector('[data-sffc-community-reached-avatar-fallback]') : null;
    var avatarImage;

    if (!modal) {
      return;
    }

    var setText = function (selector, value, fallback) {
      var node = modal.querySelector(selector);
      if (node) {
        node.textContent = value || fallback || '';
      }
    };

    setText('[data-sffc-community-reached-name]', name, 'Recruiter contact');
    setText('[data-sffc-community-reached-role]', title, 'Outreach details and attachments sent through MENA Careers.');
    setText('[data-sffc-community-reached-company]', company, 'Company');
    setText('[data-sffc-community-reached-company-info]', companyInfo, 'No extra company notes saved.');
    setText('[data-sffc-community-reached-sent-at]', sentAt, 'Sent date not recorded.');
    setText('[data-sffc-community-reached-subject]', subject, 'No subject saved.');
    setText('[data-sffc-community-reached-body]', body, 'No message body saved yet.');

    var emailNode = modal.querySelector('[data-sffc-community-reached-email]');
    if (emailNode) {
      emailNode.textContent = email || 'Not available';
    }

    var linkedinNode = modal.querySelector('[data-sffc-community-reached-linkedin]');
    if (linkedinNode) {
      linkedinNode.innerHTML = '';
      if (linkedin) {
        var link = document.createElement('a');
        link.href = linkedin;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        link.textContent = 'Open profile';
        linkedinNode.appendChild(link);
      } else {
        linkedinNode.textContent = 'Not available';
      }
    }

    var attachmentsNode = modal.querySelector('[data-sffc-community-reached-attachments]');
    if (attachmentsNode) {
      attachmentsNode.innerHTML = '';
      var attachmentList = attachments.split(/\r?\n/).map(function (item) {
        return String(item || '').trim();
      }).filter(Boolean);

      if (attachmentList.length) {
        attachmentList.forEach(function (item) {
          var chip = document.createElement('a');
          chip.className = 'sffc-community-editorial__reached-attachment';
          chip.href = item;
          chip.target = '_blank';
          chip.rel = 'noopener noreferrer';
          chip.textContent = item.split('/').pop() || 'Attachment';
          attachmentsNode.appendChild(chip);
        });
      } else {
        attachmentsNode.textContent = 'No attachments recorded.';
      }
    }

    if (avatar) {
      avatar.classList.toggle('has-photo', !!avatarUrl);
      avatarImage = avatar.querySelector('img');
      if (avatarUrl) {
        if (!avatarImage) {
          avatarImage = document.createElement('img');
          avatar.insertBefore(avatarImage, avatar.firstChild);
        }
        avatarImage.src = avatarUrl;
        avatarImage.alt = name || 'Recruiter';
      } else if (avatarImage) {
        avatarImage.remove();
      }
    }

    if (avatarFallback) {
      avatarFallback.hidden = !!avatarUrl;
      avatarFallback.textContent = avatarInitial;
    }

    modal.hidden = false;
    document.documentElement.classList.add('sffc-community-editorial-modal-open');
    document.body.classList.add('sffc-community-editorial-modal-open');

    if (dialog && typeof dialog.focus === 'function') {
      window.setTimeout(function () {
        dialog.focus();
      }, 20);
    }
  }

  function closeCommunityReachedModal(root) {
    var modal = root ? root.querySelector('[data-sffc-community-reached-modal]') : null;
    if (!modal) {
      return;
    }

    modal.hidden = true;
    document.documentElement.classList.remove('sffc-community-editorial-modal-open');
    document.body.classList.remove('sffc-community-editorial-modal-open');
  }

  function submitIntroCampaign(root, form) {
    var feedback = form ? form.querySelector('[data-sffc-community-intros-feedback]') : null;
    var submitButton = form ? form.querySelector('[data-sffc-community-intros-submit]') : null;
    var cvSelect = form ? form.querySelector('select[name="stored_cv_url"]') : null;
    var formData;
    var selectedOption;

    if (!root || !form) {
      return;
    }

    formData = new FormData(form);
    selectedOption = cvSelect ? cvSelect.options[cvSelect.selectedIndex] : null;
    formData.append('action', 'sffc_crm_submit_intro_campaign');
    formData.append('nonce', config.crmNonce || '');
    formData.append('stored_cv_name', selectedOption ? String(selectedOption.getAttribute('data-cv-name') || '') : '');

    if (feedback) {
      feedback.hidden = true;
      feedback.textContent = '';
      feedback.classList.remove('is-error', 'is-success');
    }

    if (submitButton) {
      submitButton.disabled = true;
      submitButton.textContent = 'Submitting...';
    }

    window.fetch((config.ajaxUrl || '/wp-admin/admin-ajax.php'), {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
    })
      .then(parseAjaxJson)
      .then(function (payload) {
        if (!payload || !payload.success) {
          throw new Error((payload && payload.data && payload.data.message) || 'Unable to submit intro campaign.');
        }

        if (feedback) {
          feedback.hidden = false;
          feedback.classList.remove('is-error');
          feedback.classList.add('is-success');
          feedback.textContent = (payload.data && payload.data.message) || 'Intro campaign submitted successfully.';
        }

        form.reset();
        activateIntrosSubtab(root, 'applications');
        window.setTimeout(function () {
          window.location.reload();
        }, 900);
      })
      .catch(function (error) {
        if (feedback) {
          feedback.hidden = false;
          feedback.classList.remove('is-success');
          feedback.classList.add('is-error');
          feedback.textContent = error && error.message ? error.message : 'Unable to submit intro campaign.';
        }
      })
      .finally(function () {
        if (submitButton) {
          submitButton.disabled = false;
          submitButton.textContent = 'Request Intro Campaign';
        }
      });
  }

  function renderCommunityCompanyRoles(root, companyName, roles) {
    var modal = root ? root.querySelector('[data-sffc-community-company-modal]') : null;
    var title = modal ? modal.querySelector('#sffc-community-company-title') : null;
    var subtitle = modal ? modal.querySelector('[data-sffc-community-company-subtitle]') : null;
    var results = modal ? modal.querySelector('[data-sffc-community-company-results]') : null;
    var empty = modal ? modal.querySelector('[data-sffc-community-company-empty]') : null;

    if (!modal || !results) {
      return;
    }

    if (title) {
      title.textContent = companyName || 'View Roles';
    }

    if (subtitle) {
      subtitle.textContent = companyName
        ? ('Latest roles from ' + companyName + ' on MENA Careers.')
        : 'Latest roles from this company on MENA Careers.';
    }

    results.innerHTML = '';

    if (!roles || !roles.length) {
      if (empty) {
        empty.hidden = false;
      }
      return;
    }

    if (empty) {
      empty.hidden = true;
    }

    roles.forEach(function (role) {
      var article = document.createElement('article');
      article.className = 'sffc-community-editorial__company-role';

      var copy = document.createElement('div');
      copy.className = 'sffc-community-editorial__company-role-copy';

      var strong = document.createElement('strong');
      strong.textContent = String(role.title || '');
      copy.appendChild(strong);

      var metaParts = [];
      if (role.location) {
        metaParts.push(String(role.location));
      }
      if (role.posted_label) {
        metaParts.push(String(role.posted_label));
      }
      if (metaParts.length) {
        var meta = document.createElement('span');
        meta.textContent = metaParts.join(' • ');
        copy.appendChild(meta);
      }

      var action = document.createElement('a');
      action.className = 'sffc-community-editorial__button is-primary sffc-community-editorial__company-role-link';
      action.href = String(role.url || '#');
      action.target = '_blank';
      action.rel = 'noopener noreferrer';
      action.textContent = 'View Role';

      article.appendChild(copy);
      article.appendChild(action);
      results.appendChild(article);
    });
  }

  function loadCommunityCompanyRoles(root, trigger) {
    var companyName = String(trigger.getAttribute('data-company-name') || '').trim();
    var modal = root ? root.querySelector('[data-sffc-community-company-modal]') : null;
    var loading = modal ? modal.querySelector('[data-sffc-community-company-loading]') : null;
    var results = modal ? modal.querySelector('[data-sffc-community-company-results]') : null;
    var empty = modal ? modal.querySelector('[data-sffc-community-company-empty]') : null;
    var requestBody;

    if (!root || !modal || !companyName) {
      return;
    }

    if (loading) {
      loading.hidden = false;
    }
    if (results) {
      results.innerHTML = '';
    }
    if (empty) {
      empty.hidden = true;
    }

    openCommunityCompanyModal(root);

    requestBody = new FormData();
    requestBody.append('action', 'sffc_crm_editorial_company_roles');
    requestBody.append('nonce', config.crmNonce || '');
    requestBody.append('company_name', companyName);

    window.fetch((config.ajaxUrl || '/wp-admin/admin-ajax.php'), {
      method: 'POST',
      body: requestBody,
      credentials: 'same-origin'
    })
      .then(parseAjaxJson)
      .then(function (payload) {
        if (!payload || !payload.success) {
          throw new Error((payload && payload.data && payload.data.message) || 'Unable to load company roles.');
        }

        renderCommunityCompanyRoles(root, companyName, (payload.data && payload.data.roles) || []);
      })
      .catch(function () {
        renderCommunityCompanyRoles(root, companyName, []);
      })
      .finally(function () {
        if (loading) {
          loading.hidden = true;
        }
      });
  }

  function setCommunityCopyFeedback(shell, selector, message, isError) {
    var feedback = shell ? shell.querySelector(selector) : null;
    if (!feedback) {
      return;
    }

    feedback.hidden = false;
    feedback.classList.toggle('is-error', !!isError);
    feedback.classList.toggle('is-success', !isError);
    feedback.textContent = String(message || '');
  }

  function copyCommunityText(value) {
    var text = String(value || '');
    if (!text) {
      return Promise.reject(new Error('Nothing to copy.'));
    }

    if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
      return navigator.clipboard.writeText(text);
    }

    return new Promise(function (resolve, reject) {
      var textarea = document.createElement('textarea');
      textarea.value = text;
      textarea.setAttribute('readonly', 'readonly');
      textarea.style.position = 'fixed';
      textarea.style.opacity = '0';
      document.body.appendChild(textarea);
      textarea.select();

      try {
        if (document.execCommand('copy')) {
          document.body.removeChild(textarea);
          resolve();
          return;
        }
      } catch (error) {
        reject(error);
      }

      document.body.removeChild(textarea);
      reject(new Error('Unable to copy text.'));
    });
  }

  function markSelected(collection, activeElement) {
    collection.forEach(function (element) {
      element.classList.toggle('is-selected', element === activeElement);
    });
  }

  function getCookieValue(name) {
    var target = String(name || '') + '=';
    var parts = String(document.cookie || '').split(';');

    for (var i = 0; i < parts.length; i += 1) {
      var part = parts[i].trim();
      if (part.indexOf(target) === 0) {
        return decodeURIComponent(part.slice(target.length));
      }
    }

    return '';
  }

  function getLocalStorageValue(key) {
    try {
      return window.localStorage ? window.localStorage.getItem(key) : '';
    } catch (error) {
      return '';
    }
  }

  function formatOnboardingMoney(amount, currency) {
    var normalizedCurrency = String(currency || 'GBP').toUpperCase();
    var multipliers = {
      GBP: 1,
      USD: 1.28,
      EUR: 1.16,
      AED: 4.7,
      SAR: 4.8
    };
    var symbols = {
      GBP: '£',
      USD: '$',
      EUR: '€',
      AED: 'AED ',
      SAR: 'SAR '
    };
    var multiplier = multipliers[normalizedCurrency] || multipliers.GBP;
    var symbol = symbols[normalizedCurrency] || symbols.GBP;
    var value = Math.round((parseFloat(amount || 0) * multiplier) * 100) / 100;
    var display = value % 1 === 0 ? String(value.toFixed(0)) : String(value.toFixed(2));

    return symbol + display;
  }

  function updateOnboardingPlanPrices(root) {
    var overlay = root ? root.querySelector('[data-sffc-community-onboarding]') : null;
    var currency = getCookieValue('currency_detector_currency') || window.sffcCurrencyDetectorCurrency || getLocalStorageValue('sffc_currency') || 'GBP';

    if (!overlay) {
      return;
    }

    overlay.querySelectorAll('[data-sffc-community-onboarding-price]').forEach(function (priceNode) {
      var base = priceNode.getAttribute('data-plan-price-gbp') || '';
      if (base) {
        priceNode.textContent = formatOnboardingMoney(base, currency);
      }
    });
  }

  function collectOnboardingTargetRoles(onboardingRoot) {
    var items = [];
    var roles = [];
    var sectors = [];
    var sectorLabels = [];
    var firstIncomplete = false;

    if (!onboardingRoot) {
      return {
        items: items,
        roles: roles,
        sectors: sectors,
        sectorLabels: sectorLabels,
        firstIncomplete: firstIncomplete
      };
    }

    onboardingRoot.querySelectorAll('.sffc-community-editorial__onboarding-role-row').forEach(function (row) {
      var roleInput = row.querySelector('[data-sffc-community-onboarding-target-role]');
      var sectorSelect = row.querySelector('[data-sffc-community-onboarding-target-sector]');
      var role = roleInput ? roleInput.value.trim() : '';
      var sector = sectorSelect ? String(sectorSelect.value || '').trim() : '';
      var sectorLabel = sectorSelect && sectorSelect.selectedOptions && sectorSelect.selectedOptions[0]
        ? sectorSelect.selectedOptions[0].textContent.trim()
        : '';

      if (role || sector) {
        if (!role || !sector) {
          firstIncomplete = true;
          return;
        }

        items.push({
          role: role,
          sector: sector,
          sectorLabel: sectorLabel || sector
        });
        roles.push(role);
        sectors.push(sector);
        sectorLabels.push(sectorLabel || sector);
      }
    });

    return {
      items: items.slice(0, 3),
      roles: roles.slice(0, 3),
      sectors: sectors.slice(0, 3),
      sectorLabels: sectorLabels.slice(0, 3),
      firstIncomplete: firstIncomplete
    };
  }

  function getOnboardingLocationLabel(state) {
    var locationLabels = config.locationLabels || {};

    if (state.locationChoice === 'other') {
      return state.locationOther || 'Other';
    }

    return (state.locationChoice && locationLabels[state.locationChoice]) || state.locationChoice || '';
  }

  function updateOnboardingPlanSummary(root) {
    var overlay = root ? root.querySelector('[data-sffc-community-onboarding]') : null;
    var state = getOnboardingState(root);
    var roles = Array.isArray(state.targetRoles) ? state.targetRoles : [];

    if (!overlay) {
      return;
    }

    var accessNode = overlay.querySelector('[data-sffc-community-onboarding-summary-access]');
    var locationNode = overlay.querySelector('[data-sffc-community-onboarding-summary-location]');
    var rolesNode = overlay.querySelector('[data-sffc-community-onboarding-summary-roles]');
    var cvNode = overlay.querySelector('[data-sffc-community-onboarding-summary-cv]');

    if (accessNode) {
      accessNode.textContent = state.accessLabel || state.accessChoice || 'Selected access';
    }
    if (locationNode) {
      locationNode.textContent = getOnboardingLocationLabel(state) || state.locationOther || 'Selected location';
    }
    if (rolesNode) {
      rolesNode.textContent = roles.length ? roles.join(', ') : (state.idealRole || 'Selected roles');
    }
    if (cvNode) {
      cvNode.textContent = state.cvSaved ? (state.cvFileName || 'Uploaded') : (state.cvFileName || 'Skipped for now');
    }

    updateOnboardingPlanPrices(root);
  }

  function submitOnboarding(root, options) {
    var settings = options || {};
    var overlay = root.querySelector('[data-sffc-community-onboarding]');
    var feedback = overlay ? overlay.querySelector('[data-sffc-community-onboarding-feedback]') : null;
    var submitButton = overlay ? overlay.querySelector('[data-sffc-community-onboarding-submit]') : null;
    var state = getOnboardingState(root);
    var formData = new FormData();

    if (!settings.skip && !settings.allowNoCv && !state.cvSaved) {
      registerOnboardingCvFailure(root);
      if (feedback) {
        feedback.hidden = false;
        feedback.classList.add('is-error');
        feedback.textContent = 'Upload your CV before completing onboarding.';
      }
      updateOnboardingSubmitState(root);
      return;
    }

    formData.append('action', 'sffc_crm_editorial_onboarding_submit');
    formData.append('nonce', config.onboardingNonce || '');

    if (settings.skip) {
      formData.append('skip', '1');
    } else {
      formData.append('access_choice', state.accessChoice || '');
      formData.append('location_choice', state.locationChoice || '');
      formData.append('location_other', state.locationOther || '');
      formData.append('role_interest', state.roleInterest || '');
      formData.append('ideal_role', state.idealRole || '');
      formData.append('target_sector', state.targetSector || '');
      formData.append('target_role_items', JSON.stringify(Array.isArray(state.targetRoleItems) ? state.targetRoleItems : []));
      formData.append('target_roles', JSON.stringify(Array.isArray(state.targetRoles) ? state.targetRoles : []));
      formData.append('target_sectors', JSON.stringify(Array.isArray(state.targetSectors) ? state.targetSectors : []));
      formData.append('cv_saved', state.cvSaved ? '1' : '0');
      if (settings.allowNoCv) {
        formData.append('allow_no_cv', '1');
      }
    }

    if (feedback) {
      feedback.hidden = true;
      feedback.classList.remove('is-error');
      feedback.textContent = '';
    }

    if (submitButton) {
      submitButton.disabled = true;
      submitButton.textContent = (config.strings && config.strings.saving) || 'Saving...';
    }

    window.fetch((config.ajaxUrl || '/wp-admin/admin-ajax.php'), {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
    })
      .then(parseAjaxJson)
      .then(function (payload) {
        if (!payload || !payload.success) {
          throw new Error((payload && payload.data && payload.data.message) || ((config.strings && config.strings.saveError) || 'Save failed.'));
        }

        updateOnboardingPlanSummary(root);
        hideOnboarding(root);
      })
      .catch(function (error) {
        var message = error && error.message ? error.message : ((config.strings && config.strings.saveError) || 'Save failed.');

        if (message === 'Upload your CV before completing onboarding.') {
          registerOnboardingCvFailure(root);
        }

        if (feedback) {
          feedback.hidden = false;
          feedback.classList.add('is-error');
          feedback.textContent = message;
        }
      })
      .finally(function () {
        if (submitButton) {
          submitButton.disabled = false;
          submitButton.textContent = 'Finish Onboarding';
        }
      });
  }

  function bindCommunityFilterOutsideClose(root) {
    if (!root || root.__sffcCommunityFilterOutsideCloseBound) {
      return;
    }

    root.__sffcCommunityFilterOutsideCloseBound = true;

    function handleOutsideFilterTap(event) {
      var target = event.target;
      var openPanel = root.querySelector('[data-sffc-community-filter-panel]:not([hidden])');

      if (!openPanel || !target || !target.closest) {
        return;
      }

      if (target.closest('[data-sffc-community-filter-panel]') || target.closest('[data-sffc-community-filter-toggle]')) {
        return;
      }

      closeCommunityFilterPanels(root, '');
    }

    if (window.PointerEvent) {
      document.addEventListener('pointerdown', handleOutsideFilterTap, true);
    } else {
      document.addEventListener('touchstart', handleOutsideFilterTap, true);
      document.addEventListener('click', handleOutsideFilterTap, true);
    }
  }

  function init(root) {
    if (!root) {
      return;
    }

    bindCommunityFilterOutsideClose(root);
    moveCommunityFilterToolbarBelowHeader(root);
    setupCommunityFilterToolbarScroll(root);

    var storedGuestCvToken = getStoredCommunityGuestCvToken();
    if (storedGuestCvToken && !getCommunityGuestCvToken(root)) {
      root.setAttribute('data-sffc-community-guest-cv-token', storedGuestCvToken);
      setCommunityCvUploadStatus(root, 'CV uploaded', 'loaded');
      window.setTimeout(function () {
        requestCommunityFeed(root, { scrollToResults: false });
      }, 0);
    }

    var initialTab = root.getAttribute('data-active-tab') || 'posts';
    var initialGroupFilter = root.getAttribute('data-active-group-filter') || 'all';
    var initialEffectiveGroupFilter = getEffectiveCommunityGroup(root, initialGroupFilter);
    root.__sffcCommunityHasMoreByGroup = {
      all: !!root.querySelector('[data-sffc-community-load-more]')
    };
    root.__sffcCommunityHasMoreByGroup[initialEffectiveGroupFilter] = !!root.querySelector('[data-sffc-community-load-more]');
    activateTab(root, initialTab);
    activateGroupFilter(root, initialGroupFilter);
    activateIntrosSubtab(root, root.__sffcCommunityActiveIntrosTab || 'intros');
    activateProfileSubtab(root, root.__sffcCommunityActiveProfileTab || 'profile');
    initializeApplyForMePanel(root);
    normalizeCommunityPostActionButtons(root);
    updateOnboardingSubmitState(root);
    updateOnboardingSkipState(root);
    closeCommunityFilterPanels(root, '');
    updateLoadMoreState(root);
    refreshCommunityLinkedinPostsLayout(root);

    if (root.getAttribute('data-sffc-community-onboarding-active') === 'true') {
      showOnboarding(root);
      setOnboardingStep(root, 'location');
      updateOnboardingPlanPrices(root);
    }

    if (typeof window.requestIdleCallback === 'function') {
      window.requestIdleCallback(function () {
        requestCommunitySidebars(root);
      }, { timeout: 1200 });
    } else {
      window.setTimeout(function () {
        requestCommunitySidebars(root);
      }, 200);
    }

    if (typeof window.requestIdleCallback === 'function') {
      window.requestIdleCallback(function () {
        prefetchDeferredCommunityTabs(root);
      }, { timeout: 1600 });
    } else {
      window.setTimeout(function () {
        prefetchDeferredCommunityTabs(root);
      }, 350);
    }

    root.addEventListener('input', function (event) {
      var directEmailInput = event.target.closest('[data-sffc-community-direct-email-input]');
      if (directEmailInput && root.contains(directEmailInput)) {
        directEmailInput.classList.remove('is-invalid');
        return;
      }

      var linkedinDirectEmailInput = event.target.closest('[data-sffc-linkedin-jobs-direct-email-input]');
      if (linkedinDirectEmailInput && root.contains(linkedinDirectEmailInput)) {
        linkedinDirectEmailInput.classList.remove('is-invalid');
        return;
      }

      var linkedinGetStartedInput = event.target.closest('[data-sffc-linkedin-jobs-get-started-required]');
      if (linkedinGetStartedInput && root.contains(linkedinGetStartedInput)) {
        linkedinGetStartedInput.classList.remove('is-invalid');
        return;
      }

      var linkedinProfileReviewInput = event.target.closest('[data-sffc-linkedin-jobs-profile-review-form] input, [data-sffc-linkedin-jobs-profile-review-form] textarea');
      if (linkedinProfileReviewInput && root.contains(linkedinProfileReviewInput)) {
        var linkedinProfileReviewForm = linkedinProfileReviewInput.closest('[data-sffc-linkedin-jobs-profile-review-form]');
        var linkedinProfileReviewFeedback = linkedinProfileReviewForm ? linkedinProfileReviewForm.querySelector('[data-sffc-linkedin-jobs-profile-review-feedback]') : null;
        if (linkedinProfileReviewFeedback) {
          linkedinProfileReviewFeedback.hidden = true;
        }
        return;
      }

      var optionSearchInput = event.target.closest('[data-sffc-community-option-search], [data-sffc-community-tracker-search], .sffc-community-editorial__filter-search input[type="search"]');
      if (optionSearchInput && root.contains(optionSearchInput) && !optionSearchInput.matches('[data-sffc-community-search-input]')) {
        filterCommunityDropdownOptions(optionSearchInput);
        return;
      }

      var feedSearchInput = event.target.closest('[data-sffc-community-search-input]');
      if (feedSearchInput && root.contains(feedSearchInput)) {
        var searchValue = feedSearchInput.value || '';
        getCommunityFilterState(root).search = searchValue;
        updateToolbarFilterLabel(root, 'search', searchValue.trim());
        window.clearTimeout(root.__sffcCommunityFeedSearchTimer);
        root.__sffcCommunityFeedSearchTimer = window.setTimeout(function () {
          activateTab(root, 'posts');
          requestCommunityFeed(root, { scrollToResults: false, batchSize: 80 });
        }, 450);
      }
    });

    root.addEventListener('change', function (event) {
      var linkedinProfileReviewFile = event.target.closest('[data-sffc-linkedin-jobs-profile-review-file]');
      if (linkedinProfileReviewFile && root.contains(linkedinProfileReviewFile)) {
        renderLinkedinJobsProfileReviewPreview(linkedinProfileReviewFile);
      }

      var alertTrackerSelect = event.target.closest('[data-sffc-community-alert-tracker-select]');
      if (alertTrackerSelect && root.contains(alertTrackerSelect)) {
        syncCommunityAlertTrackerFields(alertTrackerSelect.closest('[data-sffc-community-alert-form]'));
      }
    });

    root.addEventListener('keydown', function (event) {
      var trackerToggle = event.target.closest('[data-sffc-community-tracker-toggle]');
      if (!trackerToggle || !root.contains(trackerToggle)) {
        return;
      }
      if (event.target.closest('.sffc-community-editorial__post-category-alert-button, [data-sffc-community-tracker-sort]')) {
        return;
      }
      if (event.key !== 'Enter' && event.key !== ' ') {
        return;
      }
      event.preventDefault();
      toggleCommunityTrackerSection(trackerToggle.closest('[data-sffc-community-tracker]'));
    });

    root.addEventListener('click', function (event) {
      var communityMembershipLink = event.target.closest('[data-sffc-community-membership-link]');
      if (communityMembershipLink && root.contains(communityMembershipLink)) {
        event.preventDefault();
        event.stopPropagation();
        window.location.assign(getCommunityMembershipUrl(config.currentUserEmail || ''));
        return;
      }

      if (shouldOpenCommunityMembershipPlanForUnpaidClick(root, event)) {
        event.preventDefault();
        event.stopPropagation();
        openCommunityMembershipPlanModal(root);
        return;
      }

      var authOpenTrigger = event.target.closest('[data-sffc-community-auth-open]');
      if (authOpenTrigger && root.contains(authOpenTrigger) && root.getAttribute('data-sffc-community-logged-in') !== 'true') {
        event.preventDefault();
        event.stopPropagation();
        if (root.__sffcCommunityAuthOpen) {
          closeCommunityAuthDropdown(root);
        } else {
          openCommunityAuthDropdown(root, 'signup');
        }
        return;
      }

      var authModeTrigger = event.target.closest('[data-sffc-community-auth-mode]');
      if (authModeTrigger && root.contains(authModeTrigger)) {
        event.preventDefault();
        event.stopPropagation();
        setCommunityAuthMode(root, authModeTrigger.getAttribute('data-sffc-community-auth-mode'));
        return;
      }

      if (
        root.__sffcCommunityAuthOpen &&
        !event.target.closest('[data-sffc-community-auth-panel]') &&
        !event.target.closest('[data-sffc-community-auth-open]')
      ) {
        closeCommunityAuthDropdown(root);
      }

      var applyForMePanel = getApplyForMePanel(root);
      var applyForMeOption = event.target.closest('[data-sffc-apply-option]');
      if (applyForMePanel && applyForMeOption && applyForMePanel.contains(applyForMeOption)) {
        event.preventDefault();
        event.stopPropagation();
        var applyStep = applyForMeOption.closest('[data-sffc-apply-step]');
        if (!isApplyForMeMultiStep(applyStep)) {
          applyStep.querySelectorAll('[data-sffc-apply-option]').forEach(function (option) {
            if (option !== applyForMeOption) {
              option.classList.remove('is-selected');
              option.setAttribute('aria-pressed', 'false');
            }
          });
        }
        var selected = !applyForMeOption.classList.contains('is-selected');
        applyForMeOption.classList.toggle('is-selected', selected);
        applyForMeOption.setAttribute('aria-pressed', selected ? 'true' : 'false');
        return;
      }

      var applyStepJump = event.target.closest('[data-sffc-apply-step-jump]');
      if (applyForMePanel && applyStepJump && applyForMePanel.contains(applyStepJump)) {
        event.preventDefault();
        event.stopPropagation();
        var requestedApplyStep = parseInt(applyStepJump.getAttribute('data-sffc-apply-step-jump') || '0', 10);
        var currentApplyStep = getApplyForMeCurrentStep(applyForMePanel);
        if (isNaN(requestedApplyStep)) {
          requestedApplyStep = 0;
        }
        if (requestedApplyStep > currentApplyStep + 1) {
          requestedApplyStep = currentApplyStep + 1;
        }
        if (requestedApplyStep > currentApplyStep && !validateApplyForMeStep(applyForMePanel)) {
          return;
        }
        setApplyForMeStep(applyForMePanel, requestedApplyStep);
        return;
      }

      var applyBack = event.target.closest('[data-sffc-apply-back]');
      if (applyForMePanel && applyBack && applyForMePanel.contains(applyBack)) {
        event.preventDefault();
        event.stopPropagation();
        setApplyForMeStep(applyForMePanel, getApplyForMeCurrentStep(applyForMePanel) - 1);
        return;
      }

      var applyNext = event.target.closest('[data-sffc-apply-next]');
      if (applyForMePanel && applyNext && applyForMePanel.contains(applyNext)) {
        event.preventDefault();
        event.stopPropagation();
        if (!validateApplyForMeStep(applyForMePanel)) {
          return;
        }
        setApplyForMeStep(applyForMePanel, getApplyForMeCurrentStep(applyForMePanel) + 1);
        return;
      }

      var applySubmit = event.target.closest('[data-sffc-apply-submit]');
      if (applyForMePanel && applySubmit && applyForMePanel.contains(applySubmit)) {
        renderApplyForMeSummary(applyForMePanel);
        if (root.getAttribute('data-sffc-community-premium-access') === 'true' || config.hasPremiumAccess) {
          event.preventDefault();
          event.stopPropagation();
          submitApplyForMeMandate(root, applyForMePanel, applySubmit);
        }
      }

      var alertToggle = event.target.closest('[data-sffc-community-alert-toggle]');
      if (alertToggle && root.contains(alertToggle)) {
        event.preventDefault();
        event.stopPropagation();
        toggleCommunityAlertPanel(root, alertToggle);
        return;
      }

      var trackerSortOption = event.target.closest('[data-sffc-tracker-sort-option]');
      if (trackerSortOption && root.contains(trackerSortOption)) {
        event.preventDefault();
        event.stopPropagation();
        var trackerSort = trackerSortOption.closest('[data-sffc-community-tracker-sort]');
        var tracker = trackerSortOption.closest('[data-sffc-community-tracker]');
        var sortLabel = trackerSort ? trackerSort.querySelector('[data-sffc-tracker-sort-label]') : null;
        var sortValue = trackerSortOption.getAttribute('data-sffc-tracker-sort-option') || 'recent';
        if (trackerSortOption.hasAttribute('data-sffc-tracker-sort-pro') && root.getAttribute('data-sffc-community-premium-access') !== 'true') {
          window.location.href = trackerSortOption.getAttribute('href') || '/memberships/';
          return;
        }
        if (sortLabel) {
          sortLabel.textContent = (trackerSortOption.childNodes[0] ? trackerSortOption.childNodes[0].textContent : trackerSortOption.textContent).trim();
        }
        if (trackerSort) {
          trackerSort.querySelectorAll('[data-sffc-tracker-sort-option]').forEach(function (item) {
            item.classList.toggle('is-active', item === trackerSortOption);
          });
          trackerSort.open = false;
        }
        setCommunitySingleFilterState(root, 'match', 'match', getCommunityTrackerSortMatch(sortValue), sortLabel ? sortLabel.textContent : '');
        sortCommunityTrackerByRecent(root, tracker);
        requestCommunityFeed(root, { scrollToResults: true, batchSize: 80 });
        return;
      }

      var alertContinue = event.target.closest('[data-sffc-community-alert-continue], [data-sffc-community-export-continue]');
      if (alertContinue && root.contains(alertContinue)) {
        event.preventDefault();
        event.stopPropagation();
        var alertPanel = alertContinue.closest('[data-sffc-community-alert-panel]');
        var alertSetup = alertContinue.closest('[data-sffc-community-alert-setup], [data-sffc-community-export-setup]');
        var alertBenefits = null;
        if (alertPanel) {
          alertBenefits = alertContinue.matches('[data-sffc-community-export-continue]')
            ? alertPanel.querySelector('[data-sffc-community-export-benefits]')
            : alertPanel.querySelector('[data-sffc-community-alert-benefits]');
        }
        if (alertSetup && typeof alertSetup.reportValidity === 'function' && !alertSetup.reportValidity()) {
          return;
        }
        if (alertSetup) {
          alertSetup.hidden = true;
        }
        if (alertBenefits) {
          alertBenefits.hidden = false;
          var alertBenefitsCta = alertBenefits.querySelector('.sffc-community-editorial__button.is-primary');
          if (alertBenefitsCta && typeof alertBenefitsCta.focus === 'function') {
            window.setTimeout(function () {
              alertBenefitsCta.focus();
            }, 20);
          }
        }
        return;
      }

      var saveTrackerButton = event.target.closest('[data-sffc-community-save-tracker]');
      if (saveTrackerButton && root.contains(saveTrackerButton)) {
        event.preventDefault();
        event.stopPropagation();
        saveCommunityTracker(root, saveTrackerButton);
        return;
      }

      if (!event.target.closest('[data-sffc-community-alert-panel]')) {
        closeCommunityAlertPanels(root);
      }

      var trackerViewLoadMoreTrigger = event.target.closest('[data-sffc-community-tracker-view-load-more]');
      if (trackerViewLoadMoreTrigger && root.contains(trackerViewLoadMoreTrigger)) {
        event.preventDefault();
        event.stopPropagation();
        var trackerViewSection = trackerViewLoadMoreTrigger.closest('[data-sffc-community-tracker]');
        var trackerLoadMoreButton = trackerViewSection
          ? trackerViewSection.querySelector('[data-sffc-community-load-more][data-sffc-community-tracker-load-more]')
          : null;
        if (trackerLoadMoreButton && trackerLoadMoreButton.disabled) {
          return;
        }
        if (
          trackerLoadMoreButton &&
          !trackerLoadMoreButton.hidden &&
          trackerLoadMoreButton.getAttribute('data-sffc-community-has-more') !== 'false'
        ) {
          trackerViewLoadMoreTrigger.disabled = true;
          trackerLoadMoreButton.click();
        } else {
          trackerViewLoadMoreTrigger.hidden = true;
        }
        return;
      }

      var trackerToggle = event.target.closest('[data-sffc-community-tracker-toggle]');
      if (trackerToggle && root.contains(trackerToggle)) {
        if (event.target.closest('[data-sffc-community-alert-toggle], [data-sffc-community-alert-panel], [data-sffc-community-save-tracker], [data-sffc-community-tracker-sort], .sffc-community-editorial__post-category-alert-button')) {
          return;
        }
        event.preventDefault();
        toggleCommunityTrackerSection(trackerToggle.closest('[data-sffc-community-tracker]'));
        return;
      }

      var roleMenuToggle = event.target.closest('[data-sffc-community-role-menu-toggle]');
      if (roleMenuToggle && root.contains(roleMenuToggle)) {
        event.preventDefault();
        event.stopPropagation();
        toggleCommunityRoleMenu(root, roleMenuToggle);
        return;
      }

      if (!event.target.closest('[data-sffc-community-role-menu]')) {
        closeCommunityRoleMenus(root);
      }

      var linkedinPostCard = event.target.closest('[data-sffc-community-linkedin-posts-layout] [data-sffc-community-post-id]');
      if (linkedinPostCard && root.contains(linkedinPostCard)) {
        var interactiveTarget = event.target.closest('a, button, input, select, textarea, label, summary, details, [role="button"], [data-sffc-community-alert-panel]');
        if (!interactiveTarget || interactiveTarget === linkedinPostCard) {
          event.preventDefault();
          setCommunityLinkedinActivePost(root, getCommunityLinkedinPostId(linkedinPostCard), true);
          return;
        }
        setCommunityLinkedinActivePost(root, getCommunityLinkedinPostId(linkedinPostCard));
      }

      var linkedinMobileBack = event.target.closest('[data-sffc-linkedin-jobs-mobile-back]');
      if (linkedinMobileBack && root.contains(linkedinMobileBack)) {
        var linkedinMobileLayout = getCommunityLinkedinPostsLayout(root);
        if (linkedinMobileLayout) {
          event.preventDefault();
          linkedinMobileLayout.classList.remove('is-mobile-detail-open');
          return;
        }
      }

      var linkedinApplyOpen = event.target.closest('[data-sffc-linkedin-jobs-apply-open]');
      if (linkedinApplyOpen && root.contains(linkedinApplyOpen)) {
        var linkedinApplyPostId = linkedinApplyOpen.getAttribute('data-sffc-linkedin-jobs-apply-open') || '';
        var linkedinApplyModal = root.querySelector('[data-sffc-linkedin-jobs-apply-modal="' + linkedinApplyPostId + '"]');
        if (linkedinApplyModal) {
          event.preventDefault();
          linkedinApplyModal.hidden = false;
          document.documentElement.classList.add('sffc-community-editorial-modal-open');
          document.body.classList.add('sffc-community-editorial-modal-open');
          var linkedinApplyDialog = linkedinApplyModal.querySelector('.sffc-crm-linkedin-jobs__apply-dialog');
          if (linkedinApplyDialog && typeof linkedinApplyDialog.focus === 'function') {
            window.setTimeout(function () {
              linkedinApplyDialog.focus();
            }, 20);
          }
          return;
        }
      }

      var linkedinApplyClose = event.target.closest('[data-sffc-linkedin-jobs-apply-close]');
      if (linkedinApplyClose && root.contains(linkedinApplyClose)) {
        var openLinkedinApplyModal = linkedinApplyClose.closest('[data-sffc-linkedin-jobs-apply-modal]');
        if (openLinkedinApplyModal) {
          event.preventDefault();
          openLinkedinApplyModal.querySelectorAll('[data-sffc-linkedin-jobs-profile-review-panel]').forEach(function (panel) {
            panel.hidden = true;
            panel.classList.remove('is-fullscreen', 'is-checkout-open');
            panel.querySelectorAll('[data-sffc-linkedin-jobs-profile-review-checkout]').forEach(function (checkout) {
              checkout.hidden = true;
            });
          });
          openLinkedinApplyModal.querySelectorAll('[data-sffc-linkedin-jobs-profile-review-open]').forEach(function (button) {
            button.setAttribute('aria-expanded', 'false');
          });
          openLinkedinApplyModal.hidden = true;
          document.documentElement.classList.remove('sffc-community-editorial-modal-open');
          document.documentElement.classList.remove('sffc-crm-linkedin-jobs-profile-review-open');
          document.body.classList.remove('sffc-community-editorial-modal-open');
          document.body.classList.remove('sffc-crm-linkedin-jobs-profile-review-open');
          return;
        }
      }

      var linkedinFloatingChatOpen = event.target.closest('[data-sffc-linkedin-jobs-floating-chat-open]');
      if (linkedinFloatingChatOpen && root.contains(linkedinFloatingChatOpen)) {
        event.preventDefault();
        var linkedinFloatingChatTrigger = document.querySelector('[data-sffc-efc-trigger]');
        if (linkedinFloatingChatTrigger && typeof linkedinFloatingChatTrigger.click === 'function') {
          window.setTimeout(function () {
            linkedinFloatingChatTrigger.click();
          }, 40);
        }
        return;
      }

      var linkedinProfileReviewOpen = event.target.closest('[data-sffc-linkedin-jobs-profile-review-open]');
      if (linkedinProfileReviewOpen && root.contains(linkedinProfileReviewOpen)) {
        event.preventDefault();
        var linkedinProfileReviewShell = linkedinProfileReviewOpen.closest('[data-sffc-linkedin-jobs-profile-review]');
        var linkedinProfileReviewPanel = linkedinProfileReviewShell ? linkedinProfileReviewShell.querySelector('[data-sffc-linkedin-jobs-profile-review-panel]') : null;
        if (linkedinProfileReviewPanel) {
          linkedinProfileReviewPanel.hidden = false;
          linkedinProfileReviewOpen.setAttribute('aria-expanded', 'true');
          linkedinProfileReviewPanel.classList.add('is-fullscreen');
          document.documentElement.classList.add('sffc-crm-linkedin-jobs-profile-review-open');
          document.body.classList.add('sffc-crm-linkedin-jobs-profile-review-open');
          updateLinkedinJobsProfileReviewFeature(linkedinProfileReviewPanel, 'cv');
          setLinkedinJobsProfileReviewStep(linkedinProfileReviewPanel, 0);
          var linkedinProfileReviewFocusTarget = linkedinProfileReviewPanel.querySelector('[data-sffc-linkedin-jobs-profile-review-close]') || linkedinProfileReviewPanel;
          if (linkedinProfileReviewFocusTarget && typeof linkedinProfileReviewFocusTarget.focus === 'function') {
            window.setTimeout(function () {
              linkedinProfileReviewFocusTarget.focus();
            }, 20);
          }
        }
        return;
      }

      var linkedinProfileReviewClose = event.target.closest('[data-sffc-linkedin-jobs-profile-review-close]');
      if (linkedinProfileReviewClose && root.contains(linkedinProfileReviewClose)) {
        event.preventDefault();
        var linkedinProfileReviewClosePanel = linkedinProfileReviewClose.closest('[data-sffc-linkedin-jobs-profile-review-panel]');
        var linkedinProfileReviewCloseShell = linkedinProfileReviewClosePanel ? linkedinProfileReviewClosePanel.closest('[data-sffc-linkedin-jobs-profile-review]') : null;
        var linkedinProfileReviewCloseOpen = linkedinProfileReviewCloseShell ? linkedinProfileReviewCloseShell.querySelector('[data-sffc-linkedin-jobs-profile-review-open]') : null;
        if (linkedinProfileReviewClosePanel) {
          linkedinProfileReviewClosePanel.hidden = true;
          linkedinProfileReviewClosePanel.classList.remove('is-fullscreen', 'is-checkout-open');
          linkedinProfileReviewClosePanel.querySelectorAll('[data-sffc-linkedin-jobs-profile-review-checkout]').forEach(function (checkout) {
            checkout.hidden = true;
          });
          document.documentElement.classList.remove('sffc-crm-linkedin-jobs-profile-review-open');
          document.body.classList.remove('sffc-crm-linkedin-jobs-profile-review-open');
        }
        if (linkedinProfileReviewCloseOpen) {
          linkedinProfileReviewCloseOpen.setAttribute('aria-expanded', 'false');
          if (typeof linkedinProfileReviewCloseOpen.focus === 'function') {
            linkedinProfileReviewCloseOpen.focus();
          }
        }
        return;
      }

      var linkedinProfileReviewTab = event.target.closest('[data-sffc-linkedin-jobs-profile-review-tab]');
      if (linkedinProfileReviewTab && root.contains(linkedinProfileReviewTab)) {
        event.preventDefault();
        var linkedinProfileReviewTabPanel = linkedinProfileReviewTab.closest('[data-sffc-linkedin-jobs-profile-review-panel]');
        updateLinkedinJobsProfileReviewFeature(linkedinProfileReviewTabPanel, linkedinProfileReviewTab.getAttribute('data-sffc-linkedin-jobs-profile-review-tab') || 'cv');
        return;
      }

      var linkedinProfileReviewNext = event.target.closest('[data-sffc-linkedin-jobs-profile-review-next]');
      if (linkedinProfileReviewNext && root.contains(linkedinProfileReviewNext)) {
        event.preventDefault();
        var linkedinProfileReviewNextPanel = linkedinProfileReviewNext.closest('[data-sffc-linkedin-jobs-profile-review-panel]');
        var linkedinProfileReviewNextForm = linkedinProfileReviewNextPanel ? linkedinProfileReviewNextPanel.querySelector('[data-sffc-linkedin-jobs-profile-review-form]') : null;
        var linkedinProfileReviewNextStep = linkedinProfileReviewNextForm ? (parseInt(linkedinProfileReviewNextForm.getAttribute('data-sffc-linkedin-jobs-profile-review-current-step'), 10) || 0) : 0;
        if (validateLinkedinJobsProfileReviewStep(linkedinProfileReviewNextPanel)) {
          setLinkedinJobsProfileReviewStep(linkedinProfileReviewNextPanel, linkedinProfileReviewNextStep + 1);
        }
        return;
      }

      var linkedinProfileReviewPrev = event.target.closest('[data-sffc-linkedin-jobs-profile-review-prev]');
      if (linkedinProfileReviewPrev && root.contains(linkedinProfileReviewPrev)) {
        event.preventDefault();
        var linkedinProfileReviewPrevPanel = linkedinProfileReviewPrev.closest('[data-sffc-linkedin-jobs-profile-review-panel]');
        var linkedinProfileReviewPrevForm = linkedinProfileReviewPrevPanel ? linkedinProfileReviewPrevPanel.querySelector('[data-sffc-linkedin-jobs-profile-review-form]') : null;
        var linkedinProfileReviewPrevStep = linkedinProfileReviewPrevForm ? (parseInt(linkedinProfileReviewPrevForm.getAttribute('data-sffc-linkedin-jobs-profile-review-current-step'), 10) || 0) : 0;
        setLinkedinJobsProfileReviewStep(linkedinProfileReviewPrevPanel, linkedinProfileReviewPrevStep - 1);
        return;
      }

      var linkedinDirectEmailToggle = event.target.closest('[data-sffc-linkedin-jobs-direct-email-toggle]');
      if (linkedinDirectEmailToggle && root.contains(linkedinDirectEmailToggle)) {
        var linkedinDirectEmailCard = linkedinDirectEmailToggle.closest('[data-sffc-linkedin-jobs-direct-email-card]');
        var linkedinDirectEmailPanel = linkedinDirectEmailCard ? linkedinDirectEmailCard.querySelector('[data-sffc-linkedin-jobs-direct-email-panel]') : null;
        if (linkedinDirectEmailPanel) {
          event.preventDefault();
          var nextExpanded = linkedinDirectEmailPanel.hidden;
          var linkedinDirectModal = linkedinDirectEmailToggle.closest('[data-sffc-linkedin-jobs-apply-modal]');
          if (linkedinDirectModal) {
            linkedinDirectModal.querySelectorAll('[data-sffc-linkedin-jobs-direct-email-panel], [data-sffc-linkedin-jobs-get-started-panel]').forEach(function (panel) {
              if (panel !== linkedinDirectEmailPanel) {
                panel.hidden = true;
                var otherToggle = panel.closest('[data-sffc-linkedin-jobs-direct-email-card]');
                otherToggle = otherToggle ? otherToggle.querySelector('[data-sffc-linkedin-jobs-direct-email-toggle]') : null;
                if (!otherToggle) {
                  otherToggle = panel.closest('[data-sffc-linkedin-jobs-get-started-card]');
                  otherToggle = otherToggle ? otherToggle.querySelector('[data-sffc-linkedin-jobs-get-started-toggle]') : null;
                }
                if (otherToggle) {
                  otherToggle.setAttribute('aria-expanded', 'false');
                }
              }
            });
          }
          linkedinDirectEmailPanel.hidden = !nextExpanded;
          linkedinDirectEmailToggle.setAttribute('aria-expanded', nextExpanded ? 'true' : 'false');
          return;
        }
      }

      var linkedinGetStartedToggle = event.target.closest('[data-sffc-linkedin-jobs-get-started-toggle]');
      if (linkedinGetStartedToggle && root.contains(linkedinGetStartedToggle)) {
        event.preventDefault();
        toggleLinkedinJobsGetStartedPanel(linkedinGetStartedToggle);
        return;
      }

      var linkedinGetStartedSubmit = event.target.closest('[data-sffc-linkedin-jobs-get-started-submit]');
      if (linkedinGetStartedSubmit && root.contains(linkedinGetStartedSubmit)) {
        if (!validateLinkedinJobsGetStartedForm(linkedinGetStartedSubmit)) {
          event.preventDefault();
        }
        return;
      }

      var linkedinDirectEmailSubmit = event.target.closest('[data-sffc-linkedin-jobs-direct-email-submit]');
      if (linkedinDirectEmailSubmit && root.contains(linkedinDirectEmailSubmit)) {
        var linkedinDirectEmailSubmitCard = linkedinDirectEmailSubmit.closest('[data-sffc-linkedin-jobs-direct-email-card]');
        var linkedinDirectEmailInput = linkedinDirectEmailSubmitCard ? linkedinDirectEmailSubmitCard.querySelector('[data-sffc-linkedin-jobs-direct-email-input]') : null;
        if (linkedinDirectEmailInput && !linkedinDirectEmailInput.checkValidity()) {
          event.preventDefault();
          linkedinDirectEmailInput.classList.add('is-invalid');
          if (typeof linkedinDirectEmailInput.reportValidity === 'function') {
            linkedinDirectEmailInput.reportValidity();
          }
          return;
        }
      }

      var directEmailToggle = event.target.closest('[data-sffc-community-direct-email-toggle]');
      if (directEmailToggle && root.contains(directEmailToggle)) {
        event.preventDefault();
        toggleCommunityDirectEmailPanel(directEmailToggle);
        return;
      }

      var directEmailSubmit = event.target.closest('[data-sffc-community-direct-email-submit]');
      if (directEmailSubmit && root.contains(directEmailSubmit)) {
        if (!validateCommunityDirectEmail(directEmailSubmit)) {
          event.preventDefault();
          return;
        }
        scheduleCommunityDirectApplyFollowup(root, directEmailSubmit);
      }

      var profileReviewStart = event.target.closest('[data-sffc-profile-review-start]');
      if (profileReviewStart && root.contains(profileReviewStart)) {
        var profileReviewUrl;
        event.preventDefault();
        try {
          profileReviewUrl = new URL(profileReviewStart.getAttribute('href') || '/terminal/', window.location.href);
          profileReviewUrl.searchParams.set('sffc_review', 'profile');
          profileReviewUrl.searchParams.set('sffc_apply_chat_open', '1');
          profileReviewUrl.searchParams.set('sffc_apply_context', 'community_editorial');
          profileReviewUrl.searchParams.set('crm_post_id', profileReviewStart.getAttribute('data-crm-post-id') || '');
          profileReviewUrl.searchParams.set('post_id', profileReviewStart.getAttribute('data-crm-post-id') || '');
          profileReviewUrl.searchParams.set('jobs_post_id', profileReviewStart.getAttribute('data-jobs-post-id') || '');
          profileReviewUrl.searchParams.set('role_title', profileReviewStart.getAttribute('data-sffc-community-role-title') || '');
          profileReviewUrl.searchParams.set('company', profileReviewStart.getAttribute('data-sffc-community-role-company') || '');
          profileReviewUrl.searchParams.set('location', profileReviewStart.getAttribute('data-sffc-community-role-location') || '');
          profileReviewUrl.searchParams.set('sector', profileReviewStart.getAttribute('data-sffc-community-role-sector') || '');
          profileReviewUrl.searchParams.set('seniority', profileReviewStart.getAttribute('data-sffc-community-role-seniority') || '');
          window.location.href = profileReviewUrl.toString();
        } catch (error) {
          window.location.href = profileReviewStart.getAttribute('href') || '/terminal/';
        }
        return;
      }

      var companyClickTrigger = event.target.closest('[data-sffc-community-company-click]');
      if (companyClickTrigger && root.contains(companyClickTrigger)) {
        trackCommunityCompanyClick(root, companyClickTrigger);
      }

      var discoveryIntroOpen = event.target.closest('[data-sffc-community-discovery-intro-open]');
      if (discoveryIntroOpen && root.contains(discoveryIntroOpen)) {
        event.preventDefault();
        openCommunityDiscoveryIntroModal(root, discoveryIntroOpen);
        return;
      }

      var communityApplyForMeRequest = event.target.closest('[data-sffc-community-apply-for-me-request]');
      if (communityApplyForMeRequest && root.contains(communityApplyForMeRequest)) {
        event.preventDefault();
        openCommunityApplyForMeModal(root, communityApplyForMeRequest);
        return;
      }

      var introsTabTrigger = event.target.closest('[data-sffc-community-intros-tab-trigger]');
      if (introsTabTrigger && root.contains(introsTabTrigger)) {
        event.preventDefault();
        activateIntrosSubtab(root, introsTabTrigger.getAttribute('data-sffc-community-intros-tab-trigger'));
        return;
      }

      var profileTabTrigger = event.target.closest('[data-sffc-community-profile-tab-trigger]');
      if (profileTabTrigger && root.contains(profileTabTrigger)) {
        event.preventDefault();
        activateProfileSubtab(root, profileTabTrigger.getAttribute('data-sffc-community-profile-tab-trigger'));
        return;
      }

      var accountCarouselControl = event.target.closest('[data-sffc-profile-account-carousel-control]');
      if (accountCarouselControl && root.contains(accountCarouselControl)) {
        event.preventDefault();
        var accountCarouselShell = accountCarouselControl.closest('[data-sffc-profile-account-carousel-shell]');
        var accountCarouselDirection = accountCarouselControl.getAttribute('data-sffc-profile-account-carousel-control');
        var accountCarouselIndex = getProfileAccountCarouselIndex(accountCarouselShell);
        scrollProfileAccountCarousel(accountCarouselShell, accountCarouselDirection === 'prev' ? accountCarouselIndex - 1 : accountCarouselIndex + 1);
        return;
      }

      var accountCarouselDot = event.target.closest('[data-sffc-profile-account-carousel-dot]');
      if (accountCarouselDot && root.contains(accountCarouselDot)) {
        event.preventDefault();
        var accountCarouselDotShell = accountCarouselDot.closest('.sffc-crm-dashboard-app-profile-account-premium');
        var accountCarouselDotIndex = parseInt(accountCarouselDot.getAttribute('data-sffc-profile-account-carousel-dot') || '0', 10);
        scrollProfileAccountCarousel(accountCarouselDotShell ? accountCarouselDotShell.querySelector('[data-sffc-profile-account-carousel-shell]') : null, accountCarouselDotIndex);
        return;
      }

      var reachedOpen = event.target.closest('[data-sffc-community-reached-open]');
      if (reachedOpen && root.contains(reachedOpen)) {
        event.preventDefault();
        openCommunityReachedModal(root, reachedOpen);
        return;
      }

      var reachedClose = event.target.closest('[data-sffc-community-reached-close]');
      if (reachedClose && root.contains(reachedClose)) {
        event.preventDefault();
        closeCommunityReachedModal(root);
        return;
      }

      var discoveryIntroClose = event.target.closest('[data-sffc-community-discovery-intro-close]');
      if (discoveryIntroClose && root.contains(discoveryIntroClose)) {
        event.preventDefault();
        closeCommunityDiscoveryIntroModal(root);
        return;
      }

      var applyForMeClose = event.target.closest('[data-sffc-apply-for-me-close]');
      if (applyForMeClose && root.contains(applyForMeClose)) {
        event.preventDefault();
        closeCommunityApplyForMeModal(root);
        return;
      }

      var onboardingRoot = event.target.closest('[data-sffc-community-onboarding]');
      if (onboardingRoot && root.contains(onboardingRoot)) {
        var onboardingState = getOnboardingState(root);
        var backButton = event.target.closest('[data-sffc-community-onboarding-back]');
        if (backButton) {
          event.preventDefault();
          var currentStep = root.getAttribute('data-sffc-community-onboarding-step') || 'access';
          if (currentStep === 'plan') {
            setOnboardingStep(root, 'cv');
          } else if (currentStep === 'cv') {
            setOnboardingStep(root, 'details');
          } else if (currentStep === 'details') {
            setOnboardingStep(root, 'location');
          }
          return;
        }

        var accessCard = event.target.closest('[data-sffc-community-onboarding-access]');
        if (accessCard) {
          event.preventDefault();
          onboardingState.accessChoice = accessCard.getAttribute('data-sffc-community-onboarding-access') || '';
          onboardingState.accessLabel = accessCard.getAttribute('data-sffc-community-onboarding-label') || accessCard.textContent.trim();
          markSelected(onboardingRoot.querySelectorAll('[data-sffc-community-onboarding-access]'), accessCard);
          setOnboardingStep(root, 'location');
          return;
        }

        var locationCard = event.target.closest('[data-sffc-community-onboarding-location]');
        if (locationCard) {
          event.preventDefault();
          onboardingState.locationChoice = locationCard.getAttribute('data-sffc-community-onboarding-location') || '';
          onboardingState.locationOther = '';
          markSelected(onboardingRoot.querySelectorAll('[data-sffc-community-onboarding-location]'), locationCard);
          setOnboardingStep(root, 'details');
          return;
        }

        var otherToggle = event.target.closest('[data-sffc-community-onboarding-other-toggle]');
        if (otherToggle) {
          event.preventDefault();
          var otherField = onboardingRoot.querySelector('[data-sffc-community-onboarding-other-field]');
          if (otherField) {
            otherField.hidden = false;
            var otherInput = otherField.querySelector('[data-sffc-community-onboarding-other-input]');
            if (otherInput) {
              otherInput.focus();
            }
          }
          return;
        }

        var otherContinue = event.target.closest('[data-sffc-community-onboarding-other-continue]');
        if (otherContinue) {
          event.preventDefault();
          var otherInputValue = onboardingRoot.querySelector('[data-sffc-community-onboarding-other-input]');
          if (!otherInputValue || !otherInputValue.value.trim()) {
            return;
          }
          onboardingState.locationChoice = 'other';
          onboardingState.locationOther = otherInputValue.value.trim();
          setOnboardingStep(root, 'details');
          return;
        }

        var addRole = event.target.closest('[data-sffc-community-onboarding-add-role]');
        if (addRole) {
          event.preventDefault();
          var hiddenRoleRow = onboardingRoot.querySelector('[data-sffc-community-onboarding-role-row][hidden]');
          if (hiddenRoleRow) {
            hiddenRoleRow.hidden = false;
            var nextRoleInput = hiddenRoleRow.querySelector('[data-sffc-community-onboarding-target-role]');
            if (nextRoleInput) {
              nextRoleInput.focus();
            }
          }
          if (!onboardingRoot.querySelector('[data-sffc-community-onboarding-role-row][hidden]')) {
            addRole.hidden = true;
          }
          return;
        }

        var detailsContinue = event.target.closest('[data-sffc-community-onboarding-details-continue]');
        if (detailsContinue) {
          event.preventDefault();
          var rolePayload = collectOnboardingTargetRoles(onboardingRoot);
          var detailsFeedback = onboardingRoot.querySelector('[data-sffc-community-onboarding-details-feedback]');
          if (detailsFeedback) {
            detailsFeedback.hidden = true;
            detailsFeedback.classList.remove('is-error');
            detailsFeedback.textContent = '';
          }
          if (rolePayload.firstIncomplete || !rolePayload.items.length) {
            if (detailsFeedback) {
              detailsFeedback.hidden = false;
              detailsFeedback.classList.add('is-error');
              detailsFeedback.textContent = 'Add a role and sector together before continuing.';
            }
            return;
          }
          onboardingState.targetRoleItems = rolePayload.items;
          onboardingState.targetRoles = rolePayload.roles;
          onboardingState.targetSectors = rolePayload.sectors;
          onboardingState.idealRole = rolePayload.roles[0] || '';
          onboardingState.targetSector = rolePayload.sectors[0] || '';
          setOnboardingStep(root, 'cv');
          return;
        }

        var uploadTrigger = event.target.closest('[data-sffc-community-onboarding-upload-trigger]');
        if (uploadTrigger) {
          event.preventDefault();
          var uploadInput = onboardingRoot.querySelector('[data-sffc-community-onboarding-file]');
          if (uploadInput) {
            uploadInput.click();
          }
          return;
        }

        var skipOnboarding = event.target.closest('[data-sffc-community-onboarding-skip]');
        if (skipOnboarding) {
          event.preventDefault();
          onboardingState.cvSaved = false;
          onboardingState.cvFileName = 'Skipped for now';
          onboardingState.cvSkipAvailable = true;
          updateOnboardingSkipState(root);
          submitOnboarding(root, { allowNoCv: true });
          return;
        }
      }

      var cvUploadTrigger = event.target.closest('[data-sffc-community-cv-upload-trigger]');
      if (cvUploadTrigger && root.contains(cvUploadTrigger)) {
        event.preventDefault();
        closeCommunityFilterPanels(root, '');
        if (cvUploadTrigger.classList.contains('sffc-community-editorial__finder-cv-upload') && !communityHasPremiumAccess(root)) {
          openCommunityCvBenefitsGate(root);
          return;
        }
        openCommunityCvUpload(root);
        return;
      }

      var cvBenefitsClose = event.target.closest('[data-sffc-community-cv-benefits-close]');
      if (cvBenefitsClose && root.contains(cvBenefitsClose)) {
        event.preventDefault();
        closeCommunityCvBenefitsGate(root);
        return;
      }

      var filterToggle = event.target.closest('[data-sffc-community-filter-toggle]');
      if (filterToggle && root.contains(filterToggle)) {
        event.preventDefault();
        closeCommunityPostPremiumPopovers(root, null);
        var panelKey = filterToggle.getAttribute('data-sffc-community-filter-toggle') || '';
        var isExpanded = filterToggle.getAttribute('aria-expanded') === 'true';
        closeCommunityFilterPanels(root, isExpanded ? '' : panelKey);
        if (!isExpanded && panelKey === 'search') {
          window.setTimeout(function () {
            var searchInput = root.querySelector('[data-sffc-community-search-input]');
            if (searchInput) {
              searchInput.focus();
            }
          }, 20);
        }
        return;
      }

      var directClose = event.target.closest('[data-sffc-community-direct-close]');
      if (directClose && root.contains(directClose)) {
        event.preventDefault();
        closeCommunityDirectModal(directClose.closest('.sffc-community-editorial__post-shell'));
        return;
      }

      var postPremiumToggle = event.target.closest('[data-sffc-community-post-premium-toggle]');
      if (postPremiumToggle && root.contains(postPremiumToggle)) {
        event.preventDefault();
        var postPremiumShell = postPremiumToggle.closest('.sffc-community-editorial__post-premium-lock');
        var postPremiumPanel = postPremiumShell ? postPremiumShell.querySelector('[data-sffc-community-post-premium-panel]') : null;
        var postPremiumExpanded = postPremiumToggle.getAttribute('aria-expanded') === 'true';
        closeCommunityFilterPanels(root, '');
        closeCommunityPostPremiumPopovers(root, postPremiumExpanded ? null : postPremiumPanel);
        return;
      }

      var directOpen = event.target.closest('[data-sffc-community-direct-open]');
      if (directOpen && root.contains(directOpen)) {
        event.preventDefault();
        closeCommunityPostPremiumPopovers(root, null);
        openCommunityDirectModal(directOpen.closest('.sffc-community-editorial__post-shell'));
        return;
      }

      var companyRolesOpen = event.target.closest('[data-sffc-community-company-roles-open]');
      if (companyRolesOpen && root.contains(companyRolesOpen)) {
        event.preventDefault();
        loadCommunityCompanyRoles(root, companyRolesOpen);
        return;
      }

      var companyRolesClose = event.target.closest('[data-sffc-community-company-close]');
      if (companyRolesClose && root.contains(companyRolesClose)) {
        event.preventDefault();
        closeCommunityCompanyModal(root);
        return;
      }

      var seniorityOption = event.target.closest('[data-sffc-community-seniority-filter]');
      if (seniorityOption && root.contains(seniorityOption)) {
        event.preventDefault();
        var seniorityValue = seniorityOption.getAttribute('data-sffc-community-seniority-filter') || 'all';
        getCommunityFilterState(root).seniority = seniorityValue;
        root.querySelectorAll('[data-sffc-community-seniority-filter]').forEach(function (option) {
          var isActive = option.getAttribute('data-sffc-community-seniority-filter') === seniorityValue;
          option.classList.toggle('is-active', isActive);
          option.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
        updateToolbarFilterLabel(root, 'seniority', seniorityValue === 'all' ? '' : seniorityOption.textContent.trim());
        closeCommunityFilterPanels(root, '');
        requestCommunityFeed(root, { scrollToResults: true, batchSize: 80 });
        return;
      }

      var locationOption = event.target.closest('[data-sffc-community-location-filter]');
      if (locationOption && root.contains(locationOption)) {
        event.preventDefault();
        var locationValue = locationOption.getAttribute('data-sffc-community-location-filter') || 'all';
        getCommunityFilterState(root).location = locationValue;
        root.querySelectorAll('[data-sffc-community-location-filter]').forEach(function (option) {
          var isActive = option.getAttribute('data-sffc-community-location-filter') === locationValue;
          option.classList.toggle('is-active', isActive);
          option.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
        updateToolbarFilterLabel(
          root,
          'location',
          locationValue === 'all'
            ? ''
            : (locationOption.getAttribute('data-sffc-community-location-label') || locationOption.textContent.trim())
        );
        closeCommunityFilterPanels(root, '');
        requestCommunityFeed(root, { scrollToResults: true, batchSize: 80 });
        return;
      }

      var qualificationOption = event.target.closest('[data-sffc-community-qualification-filter]');
      if (qualificationOption && root.contains(qualificationOption)) {
        event.preventDefault();
        var qualificationValue = qualificationOption.getAttribute('data-sffc-community-qualification-filter') || 'all';
        getCommunityFilterState(root).qualification = qualificationValue;
        root.querySelectorAll('[data-sffc-community-qualification-filter]').forEach(function (option) {
          var isActive = option.getAttribute('data-sffc-community-qualification-filter') === qualificationValue;
          option.classList.toggle('is-active', isActive);
          option.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
        updateToolbarFilterLabel(root, 'qualification', qualificationValue === 'all' ? '' : qualificationOption.textContent.trim());
        closeCommunityFilterPanels(root, '');
        requestCommunityFeed(root, { scrollToResults: true, batchSize: 80 });
        return;
      }

      var singleFilterMap = [
        { selector: '[data-sffc-community-posted-date-filter]', attribute: 'posted-date', state: 'postedDate' },
        { selector: '[data-sffc-community-company-filter]', attribute: 'company', state: 'company' },
        { selector: '[data-sffc-community-sector-filter]', attribute: 'sector', state: 'sector' },
        { selector: '[data-sffc-community-work-type-filter]', attribute: 'work-type', state: 'workType' },
        { selector: '[data-sffc-community-match-filter]', attribute: 'match', state: 'match' }
      ];

      for (var singleFilterIndex = 0; singleFilterIndex < singleFilterMap.length; singleFilterIndex += 1) {
        var singleFilterConfig = singleFilterMap[singleFilterIndex];
        var singleFilterOption = event.target.closest(singleFilterConfig.selector);
        if (singleFilterOption && root.contains(singleFilterOption)) {
          event.preventDefault();
          var singleFilterValue = singleFilterOption.getAttribute('data-sffc-community-' + singleFilterConfig.attribute + '-filter') || 'all';
          setCommunitySingleFilterState(root, singleFilterConfig.state, singleFilterConfig.attribute, singleFilterValue, singleFilterOption.textContent.trim());
          if (singleFilterConfig.state === 'match' && root.getAttribute('data-active-tab') === 'early_bird' && singleFilterValue !== 'qualified') {
            activateTab(root, 'posts');
          }
          closeCommunityFilterPanels(root, '');
          requestCommunityFeed(root, { scrollToResults: true, batchSize: 80 });
          return;
        }
      }

      var trackerOption = event.target.closest('[data-sffc-community-tracker-filter]');
      if (trackerOption && root.contains(trackerOption)) {
        event.preventDefault();
        var trackerSlug = trackerOption.getAttribute('data-sffc-community-tracker-filter') || '';
        var selectedTrackers = getSelectedCommunityTrackers(root);
        var trackerIndex = selectedTrackers.indexOf(trackerSlug);
        if (trackerSlug) {
          if (trackerIndex === -1) {
            selectedTrackers.push(trackerSlug);
          } else {
            selectedTrackers.splice(trackerIndex, 1);
          }
        }
        activateTab(root, 'posts');
        activateGroupFilter(root, 'all');
        setCommunityTrackerFilterState(root, selectedTrackers);
        requestCommunityFeed(root, { scrollToResults: true, batchSize: 80 });
        return;
      }

      var alertTrackerSelect = event.target.closest('[data-sffc-community-alert-tracker-select]');
      if (alertTrackerSelect && root.contains(alertTrackerSelect)) {
        syncCommunityAlertTrackerFields(alertTrackerSelect.closest('[data-sffc-community-alert-form]'));
        return;
      }

      var trackerClear = event.target.closest('[data-sffc-community-tracker-filter-clear]');
      if (trackerClear && root.contains(trackerClear)) {
        event.preventDefault();
        setCommunityTrackerFilterState(root, []);
        requestCommunityFeed(root, { scrollToResults: true, batchSize: 80 });
        return;
      }

      var filterApply = event.target.closest('[data-sffc-community-filter-apply]');
      if (filterApply && root.contains(filterApply)) {
        event.preventDefault();
        closeCommunityFilterPanels(root, '');
        return;
      }

      var earlyBirdFilter = event.target.closest('[data-sffc-community-early-bird-filter]');
      if (earlyBirdFilter && root.contains(earlyBirdFilter)) {
        event.preventDefault();
        var earlyBirdState = getCommunityFilterState(root);
        var nextEarlyBirdValue = earlyBirdState.recency === 'early_bird' ? 'all' : 'early_bird';
        activateTab(root, 'posts');
        setCommunityEarlyBirdState(root, nextEarlyBirdValue);
        closeCommunityFilterPanels(root, '');
        requestCommunityFeed(root, { scrollToResults: true, batchSize: 80 });
        return;
      }

      var earlyBirdNav = event.target.closest('[data-sffc-community-early-bird-nav]');
      if (earlyBirdNav && root.contains(earlyBirdNav)) {
        event.preventDefault();
        if (root.getAttribute('data-sffc-community-premium-access') !== 'true') {
          closeCommunityFilterPanels(root, 'early-bird');
          return;
        }
        activateTab(root, 'posts');
        setCommunityEarlyBirdState(root, 'early_bird');
        closeCommunityFilterPanels(root, '');
        requestCommunityFeed(root, { scrollToResults: true, batchSize: 80 });
        return;
      }

      var removeFilter = event.target.closest('[data-sffc-community-filter-remove]');
      if (removeFilter && root.contains(removeFilter)) {
        event.preventDefault();

        var removeKey = removeFilter.getAttribute('data-sffc-community-filter-remove') || '';
        var removeState = getCommunityFilterState(root);
        var searchInputForRemove = root.querySelector('[data-sffc-community-search-input]');

        if (removeKey === 'group') {
          activateGroupFilter(root, 'all');
        } else if (removeKey === 'search') {
          removeState.search = '';
          if (searchInputForRemove) {
            searchInputForRemove.value = '';
          }
        } else if (removeKey === 'location') {
          removeState.location = 'all';
          root.querySelectorAll('[data-sffc-community-location-filter]').forEach(function (option) {
            var isActive = option.getAttribute('data-sffc-community-location-filter') === 'all';
            option.classList.toggle('is-active', isActive);
            option.setAttribute('aria-pressed', isActive ? 'true' : 'false');
          });
          updateToolbarFilterLabel(root, 'location', '');
        } else if (removeKey === 'seniority') {
          removeState.seniority = 'all';
          root.querySelectorAll('[data-sffc-community-seniority-filter]').forEach(function (option) {
            var isActive = option.getAttribute('data-sffc-community-seniority-filter') === 'all';
            option.classList.toggle('is-active', isActive);
            option.setAttribute('aria-pressed', isActive ? 'true' : 'false');
          });
          updateToolbarFilterLabel(root, 'seniority', '');
        } else if (removeKey === 'qualification') {
          removeState.qualification = 'all';
          root.querySelectorAll('[data-sffc-community-qualification-filter]').forEach(function (option) {
            var isActive = option.getAttribute('data-sffc-community-qualification-filter') === 'all';
            option.classList.toggle('is-active', isActive);
            option.setAttribute('aria-pressed', isActive ? 'true' : 'false');
          });
          updateToolbarFilterLabel(root, 'qualification', '');
        } else if (removeKey === 'postedDate') {
          setCommunitySingleFilterState(root, 'postedDate', 'posted-date', 'all', '');
        } else if (removeKey === 'company') {
          setCommunitySingleFilterState(root, 'company', 'company', 'all', '');
        } else if (removeKey === 'sector') {
          setCommunitySingleFilterState(root, 'sector', 'sector', 'all', '');
        } else if (removeKey === 'workType') {
          setCommunitySingleFilterState(root, 'workType', 'work-type', 'all', '');
        } else if (removeKey === 'match') {
          setCommunitySingleFilterState(root, 'match', 'match', 'all', '');
        } else if (removeKey === 'recency') {
          setCommunityEarlyBirdState(root, 'all');
        } else if (removeKey === 'trackers') {
          setCommunityTrackerFilterState(root, []);
        }

        requestCommunityFeed(root, { scrollToResults: true, batchSize: 80 });
        return;
      }

      var resetFilters = event.target.closest('[data-sffc-community-filter-reset]');
      if (resetFilters && root.contains(resetFilters)) {
        event.preventDefault();

        activateTab(root, 'posts');
        resetCommunityPostFilters(root);
        requestCommunityFeed(root, { scrollToResults: true, batchSize: 80 });
        return;
      }

      var filterTrigger = event.target.closest('[data-sffc-community-group-filter]');
      if (filterTrigger && root.contains(filterTrigger)) {
        event.preventDefault();
        if (filterTrigger.hasAttribute('data-sffc-community-group-open-posts')) {
          activateTab(root, 'posts');
        }
        activateGroupFilter(root, filterTrigger.getAttribute('data-sffc-community-group-filter'));
        requestCommunityFeed(root, { scrollToResults: true, batchSize: 80 });
        return;
      }

      if (!event.target.closest('[data-sffc-community-filter-panel]')) {
        closeCommunityFilterPanels(root, '');
      }

      if (!event.target.closest('.sffc-community-editorial__finder')) {
        closeCommunityAutocomplete(root);
      }

      var tailoredApplyTrigger = event.target.closest('[data-reddit-open-gap]');
      if (tailoredApplyTrigger && root.contains(tailoredApplyTrigger)) {
        var tailoredApplyModal = tailoredApplyTrigger.closest('[data-sffc-community-direct-modal]');
        if (tailoredApplyModal) {
          closeCommunityDirectModal(tailoredApplyModal.closest('.sffc-community-editorial__post-shell'));
        }
        return;
      }

      var loadMoreTrigger = event.target.closest('[data-sffc-community-load-more]');
      if (loadMoreTrigger && root.contains(loadMoreTrigger)) {
        event.preventDefault();
        if (loadMoreTrigger.disabled) {
          return;
        }

        var buttonGroup = loadMoreTrigger.getAttribute('data-sffc-community-group') || '';
        var activeFilter = buttonGroup && buttonGroup !== 'all'
          ? buttonGroup
          : getEffectiveCommunityGroup(root, root.getAttribute('data-active-group-filter') || 'all');
        var trackerSection = loadMoreTrigger.closest('[data-sffc-community-tracker]');
        var trackerList = trackerSection ? trackerSection.querySelector('[data-sffc-community-tracker-list]') : null;
        var isTrackerLoadMore = !!(trackerSection && trackerList && loadMoreTrigger.hasAttribute('data-sffc-community-tracker-load-more'));
        var postsWrap = root.querySelector('.sffc-community-editorial__posts');
        var emptyState = root.querySelector('[data-sffc-community-filter-empty]');
        var batchSize = parseInt(loadMoreTrigger.getAttribute('data-sffc-community-batch-size') || '10', 10);
        var requestOffset = parseInt(loadMoreTrigger.getAttribute('data-sffc-community-offset') || '0', 10);
        var requestBody = new FormData();
        var defaultLabel = loadMoreTrigger.textContent;
        var loadedIds = [];

        requestBody.append('action', 'sffc_crm_editorial_load_more');
        requestBody.append('nonce', config.loadMoreNonce || '');
        requestBody.append('offset', String(isNaN(requestOffset) ? 0 : requestOffset));
        requestBody.append('batch_size', String(isNaN(batchSize) ? 10 : batchSize));
        requestBody.append('group', activeFilter);
        requestBody.append('search', getCommunityFilterState(root).search || '');
        requestBody.append('location', getCommunityFilterState(root).location || 'all');
        requestBody.append('seniority', getCommunityFilterState(root).seniority || 'all');
        requestBody.append('qualification', getCommunityFilterState(root).qualification || 'all');
        requestBody.append('posted_date', getCommunityFilterState(root).postedDate || 'all');
        requestBody.append('company', getCommunityFilterState(root).company || 'all');
        requestBody.append('sector', getCommunityFilterState(root).sector || 'all');
        requestBody.append('work_type', getCommunityFilterState(root).workType || 'all');
        requestBody.append('match_rank', getCommunityFilterState(root).match || 'all');
        requestBody.append('recency', getCommunityFilterState(root).recency || 'all');
        requestBody.append('trackers', isTrackerLoadMore ? '' : getSelectedCommunityTrackers(root).join('|'));
        requestBody.append('guest_cv_token', getCommunityGuestCvToken(root));

        if (!isTrackerLoadMore) {
          root.querySelectorAll('[data-sffc-community-post-groups]').forEach(function (post) {
            var groups = (post.getAttribute('data-sffc-community-post-groups') || '')
              .split(/\s+/)
              .filter(Boolean);
            var isMatch = activeFilter === 'all' || groups.indexOf(activeFilter) !== -1;
            var postId = parseInt(post.getAttribute('data-sffc-community-post-id') || '0', 10);
            if (isMatch && !isNaN(postId) && postId > 0) {
              loadedIds.push(postId);
            }
          });
        }

        loadedIds.forEach(function (postId) {
          requestBody.append('exclude_ids[]', String(postId));
        });

        loadMoreTrigger.disabled = true;
        loadMoreTrigger.textContent = 'Loading...';
        root.classList.add('is-loading');

        window.fetch((config.ajaxUrl || '/wp-admin/admin-ajax.php'), {
          method: 'POST',
          body: requestBody,
          credentials: 'same-origin'
        })
          .then(parseAjaxJson)
          .then(function (payload) {
            if (!payload || !payload.success) {
              throw new Error((payload && payload.data && payload.data.message) || 'Unable to load more posts.');
            }

            var html = payload.data && payload.data.html ? String(payload.data.html) : '';
            var hasMore = !!(payload.data && payload.data.hasMore);

            if (html && postsWrap) {
              var temp = document.createElement('div');
              temp.innerHTML = html;
              if (trackerList && trackerSection) {
                var incomingTracker = temp.querySelector('[data-sffc-community-tracker]');
                var incomingList = incomingTracker ? incomingTracker.querySelector('[data-sffc-community-tracker-list]') : null;
                var incomingCards = incomingList
                  ? Array.prototype.slice.call(incomingList.children)
                  : Array.prototype.slice.call(temp.querySelectorAll('.sffc-community-editorial__post-shell, .sffc-crm-linkedin-jobs__result-card'));

                incomingCards.forEach(function (card) {
                  trackerList.appendChild(card);
                });
              } else {
                while (temp.firstChild) {
                  postsWrap.insertBefore(temp.firstChild, emptyState || loadMoreTrigger.parentNode || null);
                }
              }
              normalizeCommunityPostActionButtons(postsWrap);
            }
            if (payload.data && payload.data.detailHtml) {
              var detailWrap = root.querySelector('[data-sffc-community-linkedin-detail]');
              if (detailWrap) {
                var tempDetail = document.createElement('div');
                tempDetail.innerHTML = String(payload.data.detailHtml || '');
                while (tempDetail.firstChild) {
                  detailWrap.appendChild(tempDetail.firstChild);
                }
              }
            }

            loadMoreTrigger.setAttribute('data-sffc-community-has-more', hasMore ? 'true' : 'false');
            if (payload.data && typeof payload.data.nextOffset !== 'undefined') {
              loadMoreTrigger.setAttribute('data-sffc-community-offset', String(payload.data.nextOffset));
            }
            root.__sffcCommunityHasMoreByGroup = root.__sffcCommunityHasMoreByGroup || {};
            root.__sffcCommunityHasMoreByGroup[activeFilter] = hasMore;
            updateLoadMoreState(root, loadMoreTrigger, hasMore);
            if (trackerSection) {
              trackerSection.querySelectorAll('[data-sffc-community-tracker-view-load-more]').forEach(function (trackerViewButton) {
                trackerViewButton.hidden = !hasMore;
                trackerViewButton.disabled = false;
              });
            }
            refreshCommunityLinkedinPostsLayout(root);
          })
          .catch(function () {
            loadMoreTrigger.hidden = false;
            if (trackerSection) {
              trackerSection.querySelectorAll('[data-sffc-community-tracker-view-load-more]').forEach(function (trackerViewButton) {
                trackerViewButton.hidden = false;
                trackerViewButton.disabled = false;
              });
            }
          })
          .finally(function () {
            root.classList.remove('is-loading');
            loadMoreTrigger.disabled = false;
            loadMoreTrigger.textContent = defaultLabel;
            if (trackerSection) {
              trackerSection.querySelectorAll('[data-sffc-community-tracker-view-load-more]').forEach(function (trackerViewButton) {
                trackerViewButton.disabled = false;
              });
            }
          });
        return;
      }

      var discoveryLoadMoreTrigger = event.target.closest('[data-sffc-community-discovery-load-more]');
      if (discoveryLoadMoreTrigger && root.contains(discoveryLoadMoreTrigger)) {
        event.preventDefault();
        if (discoveryLoadMoreTrigger.disabled) {
          return;
        }

        var discoveryState = getCommunityDiscoveryFilterState(root);
        var discoveryBatchSize = parseInt(discoveryLoadMoreTrigger.getAttribute('data-sffc-community-discovery-batch-size') || '9', 10);
        var discoveryGrid = root.querySelector('.sffc-community-editorial__discovery-grid');
        var defaultDiscoveryLabel = discoveryLoadMoreTrigger.textContent;
        var discoveryRequestBody = new FormData();
        var discoveryLoadedIds = [];
        var batchValue = isNaN(discoveryBatchSize) ? 9 : discoveryBatchSize;

        if (!discoveryGrid) {
          return;
        }

        discoveryGrid.querySelectorAll('[data-sffc-community-discovery-card]').forEach(function (card) {
          var recruiterId = parseInt(card.getAttribute('data-sffc-community-discovery-id') || '0', 10);
          if (!isNaN(recruiterId) && recruiterId > 0) {
            discoveryLoadedIds.push(recruiterId);
          }
        });

        discoveryRequestBody.append('action', 'sffc_crm_editorial_discovery_load_more');
        discoveryRequestBody.append('nonce', config.discoveryLoadMoreNonce || '');
        discoveryRequestBody.append('batch_size', String(batchValue));
        discoveryRequestBody.append('search', discoveryState.search || '');
        discoveryRequestBody.append('location', discoveryState.location || 'all');
        discoveryRequestBody.append('industry', discoveryState.industry || 'all');
        discoveryRequestBody.append('role', discoveryState.role || 'all');
        discoveryLoadedIds.forEach(function (recruiterId) {
          discoveryRequestBody.append('exclude_ids[]', String(recruiterId));
        });

        discoveryLoadMoreTrigger.disabled = true;
        discoveryLoadMoreTrigger.textContent = 'Loading...';
        root.classList.add('is-loading');

        window.fetch((config.ajaxUrl || '/wp-admin/admin-ajax.php'), {
          method: 'POST',
          body: discoveryRequestBody,
          credentials: 'same-origin'
        })
          .then(parseAjaxJson)
          .then(function (payload) {
            if (!payload || !payload.success) {
              throw new Error((payload && payload.data && payload.data.message) || 'Unable to load more recruiters.');
            }

            var html = payload.data && payload.data.html ? String(payload.data.html) : '';
            var hasMore = !!(payload.data && payload.data.hasMore);

            if (html) {
              var tempDiscovery = document.createElement('div');
              tempDiscovery.innerHTML = html;
              while (tempDiscovery.firstChild) {
                discoveryGrid.appendChild(tempDiscovery.firstChild);
              }
            }

            discoveryState.visibleCount += batchValue;
            discoveryLoadMoreTrigger.setAttribute('data-sffc-community-discovery-has-more', hasMore ? 'true' : 'false');
            applyCommunityDiscoveryFilters(root);
          })
          .catch(function () {
            discoveryLoadMoreTrigger.hidden = false;
          })
          .finally(function () {
            root.classList.remove('is-loading');
            discoveryLoadMoreTrigger.disabled = false;
            discoveryLoadMoreTrigger.textContent = defaultDiscoveryLabel;
          });

        return;
      }

      var communityProfileUtilityTrigger = event.target.closest('[data-cv-match-nav-trigger]');
      if (
        communityProfileUtilityTrigger &&
        root.contains(communityProfileUtilityTrigger) &&
        openCommunityProfileUtility(
          root,
          communityProfileUtilityTrigger.getAttribute('data-cv-match-nav-trigger') || ''
        )
      ) {
        event.preventDefault();
        return;
      }

      var finderFiltersTrigger = event.target.closest('[data-sffc-community-finder-open-filters]');
      if (finderFiltersTrigger && root.contains(finderFiltersTrigger)) {
        event.preventDefault();
        activateTab(root, 'posts');
        var filterToolbar = root.querySelector('.sffc-community-editorial__filter-toolbar');
        if (filterToolbar) {
          filterToolbar.classList.remove('is-collapsed');
        }
        if (filterToolbar && typeof filterToolbar.scrollIntoView === 'function') {
          filterToolbar.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        return;
      }

      var embeddedActiveResumeTrigger = event.target.closest('[data-reddit-set-resume]');
      if (embeddedActiveResumeTrigger && root.contains(embeddedActiveResumeTrigger)) {
        var embeddedProfileShell = embeddedActiveResumeTrigger.closest('.sffc-community-editorial__profile-dashboard-shell');
        if (embeddedProfileShell) {
          event.preventDefault();
          handleEmbeddedActiveResume(root, embeddedActiveResumeTrigger);
          return;
        }
      }

      var communityNewsletterToggle = event.target.closest('[data-cv-match-newsletter-toggle]');
      if (
        communityNewsletterToggle &&
        root.contains(communityNewsletterToggle) &&
        communityNewsletterToggle.closest('.sffc-community-editorial__mini-row--newsletter')
      ) {
        event.preventDefault();
        handleCommunityNewsletterToggle(root, communityNewsletterToggle);
        return;
      }

      var profileReviewPackageCard = event.target.closest('[data-sffc-linkedin-jobs-profile-review-package]');
      if (profileReviewPackageCard && root.contains(profileReviewPackageCard)) {
        event.preventDefault();
        selectLinkedinJobsProfileReviewPackage(profileReviewPackageCard);
        return;
      }

      var trigger = event.target.closest('[data-sffc-community-tab-trigger]');
      if (!trigger || !root.contains(trigger)) {
        return;
      }

      event.preventDefault();
      var nextTab = trigger.getAttribute('data-sffc-community-tab-trigger');
      if (trigger.getAttribute('data-sffc-community-memberships-redirect') === 'true') {
        window.location.href = trigger.getAttribute('href') || '/memberships/';
        return;
      }
      if (nextTab === 'early_bird') {
        setCommunitySingleFilterState(root, 'match', 'match', 'qualified', 'Qualified');
        activateTab(root, nextTab);
        requestCommunityFeed(root, { scrollToResults: true, batchSize: 40 });
        return;
      }
      if (nextTab === 'saved_trackers') {
        setCommunitySingleFilterState(root, 'match', 'match', 'all', '');
        setCommunityEarlyBirdState(root, 'all');
        setCommunityTrackerFilterState(root, getCommunityPremiumTrackerSlugs(root));
        activateTab(root, nextTab);
        requestCommunityFeed(root, { scrollToResults: true, batchSize: 40 });
        return;
      }
      if (nextTab === 'posts') {
        activateTab(root, nextTab);
        resetCommunityPostFilters(root);
        requestCommunityFeed(root, { scrollToResults: true, batchSize: 80 });
        return;
      }
      activateTab(root, nextTab);
    });

    root.addEventListener('submit', function (event) {
      var linkedinProfileReviewForm = event.target.closest('[data-sffc-linkedin-jobs-profile-review-form]');
      if (linkedinProfileReviewForm && root.contains(linkedinProfileReviewForm)) {
        event.preventDefault();
        if (typeof linkedinProfileReviewForm.checkValidity === 'function' && !linkedinProfileReviewForm.checkValidity()) {
          if (typeof linkedinProfileReviewForm.reportValidity === 'function') {
            linkedinProfileReviewForm.reportValidity();
          }
          return;
        }
        submitLinkedinJobsProfileReview(linkedinProfileReviewForm);
        return;
      }

      var authSignupForm = event.target.closest('[data-sffc-community-auth-form="signup"]');
      if (authSignupForm && root.contains(authSignupForm)) {
        var authEmailInput = authSignupForm.querySelector('[data-sffc-community-auth-email]');
        var authEmail = authEmailInput ? String(authEmailInput.value || '').trim() : '';
        var authSubmit = authSignupForm.querySelector('.sffc-community-editorial__auth-submit');

        if (!authEmail || authEmail.indexOf('@') === -1 || !window.fetch) {
          return;
        }

        if (authSignupForm.getAttribute('data-sffc-community-auth-email-checked') === authEmail) {
          return;
        }

        event.preventDefault();
        authSignupForm.removeAttribute('data-sffc-community-auth-email-checked');
        if (authSubmit) {
          authSubmit.disabled = true;
          authSubmit.setAttribute('aria-busy', 'true');
        }
        setCommunityAuthFeedback(authSignupForm, 'Checking account...', 'loading');

        checkCommunitySignupEmail(authEmail)
          .then(function (payload) {
            var data = payload && payload.data ? payload.data : {};
            if (!payload || !payload.success || data.valid === false) {
              throw new Error('Enter a valid email address.');
            }
            if (data.exists) {
              setCommunityAuthFeedback(authSignupForm, 'That email already has an account. Sign in to continue.', 'error');
              setCommunityAuthMode(root, 'signin');
              var signinEmail = root.querySelector('[data-sffc-community-auth-form="signin"] input[name="log"]');
              if (signinEmail) {
                signinEmail.value = authEmail;
                signinEmail.focus();
              }
              return;
            }

            authSignupForm.setAttribute('data-sffc-community-auth-email-checked', authEmail);
            setCommunityAuthFeedback(authSignupForm, 'Email available. Creating your account...', 'success');
            authSignupForm.submit();
          })
          .catch(function (error) {
            setCommunityAuthFeedback(authSignupForm, error && error.message ? error.message : 'We could not check that email right now.', 'error');
          })
          .finally(function () {
            if (authSubmit) {
              authSubmit.disabled = false;
              authSubmit.removeAttribute('aria-busy');
            }
          });
        return;
      }

      var trackerAlertForm = event.target.closest('[data-sffc-community-alert-form]');
      if (trackerAlertForm && root.contains(trackerAlertForm)) {
        event.preventDefault();
        submitCommunityTrackerAlert(root, trackerAlertForm);
        return;
      }

      var introsForm = event.target.closest('[data-sffc-community-intros-form]');
      if (introsForm && root.contains(introsForm)) {
        event.preventDefault();
        submitIntroCampaign(root, introsForm);
        return;
      }

      var discoveryIntroForm = event.target.closest('[data-sffc-community-discovery-intro-form]');
      if (discoveryIntroForm && root.contains(discoveryIntroForm)) {
        event.preventDefault();
        submitCommunityDiscoveryIntro(root, discoveryIntroForm);
        return;
      }

      var applyForMeForm = event.target.closest('[data-sffc-apply-for-me-form]');
      if (applyForMeForm && root.contains(applyForMeForm)) {
        event.preventDefault();
        submitCommunityApplyForMeRequest(root, applyForMeForm);
      }
    });

    root.addEventListener('keydown', function (event) {
      if (event.key !== 'Escape') {
        return;
      }

      closeCommunityAlertPanels(root);
      closeCommunityAuthDropdown(root);

      var discoveryModal = root.querySelector('[data-sffc-community-discovery-intro-modal]');
      if (discoveryModal && !discoveryModal.hidden) {
        event.preventDefault();
        closeCommunityDiscoveryIntroModal(root);
        return;
      }

      var applyForMeModal = root.querySelector('[data-sffc-apply-for-me-modal]');
      if (applyForMeModal && !applyForMeModal.hidden) {
        event.preventDefault();
        closeCommunityApplyForMeModal(root);
        return;
      }

      var reachedModal = root.querySelector('[data-sffc-community-reached-modal]');
      if (reachedModal && !reachedModal.hidden) {
        event.preventDefault();
        closeCommunityReachedModal(root);
      }
    });

    var marketsShowcase = root.querySelector('[data-sffc-community-markets-showcase]');
    if (marketsShowcase) {
      var marketSlides = marketsShowcase.querySelectorAll('[data-sffc-community-markets-slide]');
      var marketIndex = 0;
      var marketTimerId = null;

      function activateMarketSlide(nextIndex) {
        if (!marketSlides.length) {
          return;
        }

        marketIndex = nextIndex;

        marketSlides.forEach(function (slide, index) {
          var isActive = index === marketIndex;
          slide.hidden = !isActive;
          slide.classList.toggle('is-active', isActive);
        });
      }

      function startMarketsCarousel() {
        if (marketSlides.length <= 1) {
          return;
        }

        window.clearInterval(marketTimerId);
        marketTimerId = window.setInterval(function () {
          activateMarketSlide((marketIndex + 1) % marketSlides.length);
        }, 6500);
      }

      activateMarketSlide(0);
      startMarketsCarousel();
    }

    var searchInput = root.querySelector('[data-sffc-community-search-input]');
    if (searchInput) {
      searchInput.addEventListener('input', function () {
        getCommunityFilterState(root).search = searchInput.value || '';
      });

      searchInput.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter') {
          return;
        }

        event.preventDefault();
        getCommunityFilterState(root).search = searchInput.value || '';
        activateTab(root, 'posts');
        closeCommunityFilterPanels(root, '');
        requestCommunityFeed(root, { scrollToResults: true, batchSize: 80 });
      });
    }

    root.querySelectorAll('[data-sffc-community-tracker-search], .sffc-community-editorial__tracker-filter-search input[type="search"]').forEach(function (trackerSearchInput) {
      if (trackerSearchInput.__sffcCommunityTrackerSearchReady) {
        return;
      }
      trackerSearchInput.__sffcCommunityTrackerSearchReady = true;
      trackerSearchInput.addEventListener('input', function () {
        filterCommunityDropdownOptions(trackerSearchInput);
      });
    });

    root.querySelectorAll('[data-sffc-community-option-search], .sffc-community-editorial__filter-dropdown-panel .sffc-community-editorial__filter-search input[type="search"]:not([data-sffc-community-search-input]):not([data-sffc-community-tracker-search])').forEach(function (optionSearchInput) {
      if (optionSearchInput.__sffcCommunityOptionSearchReady) {
        return;
      }
      optionSearchInput.__sffcCommunityOptionSearchReady = true;
      optionSearchInput.addEventListener('input', function () {
        filterCommunityDropdownOptions(optionSearchInput);
      });
    });

    var autocompleteInput = root.querySelector('[data-sffc-community-autocomplete-input]');
    if (autocompleteInput) {
      autocompleteInput.addEventListener('input', function () {
        var state = getAutocompleteState(root);
        var term = String(autocompleteInput.value || '').trim();

        window.clearTimeout(state.timerId);
        if (term.length < 2) {
          closeCommunityAutocomplete(root);
          return;
        }

        state.timerId = window.setTimeout(function () {
          performCommunityAutocomplete(root, term);
        }, 220);
      });

      autocompleteInput.addEventListener('focus', function () {
        var term = String(autocompleteInput.value || '').trim();
        if (term.length >= 2) {
          performCommunityAutocomplete(root, term);
        }
      });

      autocompleteInput.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
          closeCommunityAutocomplete(root);
        }
      });

      if (!root.__sffcCommunityAutocompleteOutsideCloseBound) {
        root.__sffcCommunityAutocompleteOutsideCloseBound = true;
        document.addEventListener('click', function (event) {
          var target = event.target;
          if (!target || (target.closest && target.closest('.sffc-community-editorial__finder'))) {
            return;
          }

          closeCommunityAutocomplete(root);
        });
      }
    }

    Array.prototype.forEach.call(root.querySelectorAll('[data-sffc-community-autocomplete-mode]'), function (button) {
      button.addEventListener('click', function () {
        var nextMode = String(button.getAttribute('data-sffc-community-autocomplete-mode') || 'contains');
        var term = autocompleteInput ? String(autocompleteInput.value || '').trim() : '';

        setCommunityAutocompleteMode(root, nextMode);
        if (term.length >= 2) {
          performCommunityAutocomplete(root, term);
        }
      });
    });

    setCommunityAutocompleteMode(root, 'contains');

    initializeCommunityDiscoveryControls(root);

    updateCommunitySavedTrackerButtons(root);
    if ((root.getAttribute('data-active-tab') || '') === 'saved_trackers') {
      setCommunityTrackerFilterState(root, getCommunityPremiumTrackerSlugs(root));
    }
    updateCommunityAppliedFilters(root);
    applyInitialCommunitySearchFocus(root);

    var onboardingForm = root.querySelector('[data-sffc-community-onboarding-form]');
    if (onboardingForm) {
      onboardingForm.addEventListener('submit', function (event) {
        event.preventDefault();
        submitOnboarding(root);
      });
    }

    var onboardingFile = root.querySelector('[data-sffc-community-onboarding-file]');
    if (onboardingFile) {
      onboardingFile.addEventListener('change', function () {
        var file = onboardingFile.files && onboardingFile.files[0] ? onboardingFile.files[0] : null;
        var overlay = root.querySelector('[data-sffc-community-onboarding]');
        var feedback = overlay ? overlay.querySelector('[data-sffc-community-onboarding-feedback]') : null;
        var state = getOnboardingState(root);

        if (!file) {
          return;
        }

        state.cvSaved = false;
        state.cvFileName = '';
        state.cvSkipAvailable = false;

        if (feedback) {
          feedback.hidden = true;
          feedback.classList.remove('is-error');
          feedback.textContent = '';
        }

        updateOnboardingUploadStatus(root, 'Reading ' + (file.name || 'resume') + '...', false);

        parseFile(file)
          .then(function (text) {
            return persistOnboardingCvText(root, text, file.name || 'resume');
          })
          .then(function (payload) {
            state.cvFailureCount = 0;
            state.cvSkipAvailable = false;
            updateOnboardingSkipState(root);
            return payload;
          })
          .catch(function (error) {
            registerOnboardingCvFailure(root);
            updateOnboardingUploadStatus(root, (error && error.message) || ((config.strings && config.strings.cvParseError) || 'We could not read that file yet.'), false);
            if (feedback) {
              feedback.hidden = false;
              feedback.classList.add('is-error');
              feedback.textContent = (error && error.message) || ((config.strings && config.strings.cvParseError) || 'We could not read that file yet.');
            }
          })
          .finally(function () {
            onboardingFile.value = '';
          });
      });
    }

    var communityCvUploadInput = root.querySelector('[data-sffc-community-cv-upload-input]');
    if (communityCvUploadInput) {
      communityCvUploadInput.addEventListener('change', function () {
        var file = communityCvUploadInput.files && communityCvUploadInput.files[0] ? communityCvUploadInput.files[0] : null;
        if (!file) {
          return;
        }

        uploadCommunityMatchCv(root, file)
          .finally(function () {
            communityCvUploadInput.value = '';
          });
      });
    }

    var communityCvBenefitsForm = root.querySelector('[data-sffc-community-cv-benefits-form]');
    if (communityCvBenefitsForm) {
      communityCvBenefitsForm.addEventListener('submit', function (event) {
        event.preventDefault();
        submitCommunityCvBenefitsGate(root, communityCvBenefitsForm);
      });

      var communityCvBenefitsEmail = communityCvBenefitsForm.querySelector('[data-sffc-community-cv-benefits-email]');
      if (communityCvBenefitsEmail) {
        communityCvBenefitsEmail.addEventListener('input', function () {
          var gate = getCommunityCvBenefitsGate(root);
          var feedback = gate ? gate.querySelector('[data-sffc-community-cv-benefits-feedback]') : null;
          if (feedback) {
            feedback.hidden = true;
            feedback.classList.remove('is-error');
            feedback.textContent = '';
          }
        });
      }
    }

    root.querySelectorAll('[data-sffc-profile-account-carousel-shell]').forEach(function (accountCarouselShell) {
      var accountCarousel = accountCarouselShell.querySelector('[data-sffc-profile-account-carousel]');
      var accountCarouselScrollTimer = null;

      updateProfileAccountCarousel(accountCarouselShell);
      startProfileAccountCarouselAutoplay(accountCarouselShell);

      if (accountCarousel) {
        accountCarousel.addEventListener('scroll', function () {
          window.clearTimeout(accountCarouselScrollTimer);
          accountCarouselScrollTimer = window.setTimeout(function () {
            updateProfileAccountCarousel(accountCarouselShell);
          }, 80);
        }, { passive: true });

        accountCarousel.addEventListener('mouseenter', function () {
          accountCarouselShell.__sffcAccountCarouselPaused = true;
        });

        accountCarousel.addEventListener('mouseleave', function () {
          accountCarouselShell.__sffcAccountCarouselPaused = false;
        });

        accountCarousel.addEventListener('focusin', function () {
          accountCarouselShell.__sffcAccountCarouselPaused = true;
        });

        accountCarousel.addEventListener('focusout', function () {
          accountCarouselShell.__sffcAccountCarouselPaused = false;
        });
      }
    });

    document.addEventListener('keydown', function (event) {
      var membershipLink = event.target && event.target.closest ? event.target.closest('[data-sffc-community-membership-link]') : null;
      if (membershipLink && root.contains(membershipLink) && (event.key === 'Enter' || event.key === ' ')) {
        event.preventDefault();
        window.location.assign(getCommunityMembershipUrl(config.currentUserEmail || ''));
        return;
      }

      if (event.key !== 'Escape') {
        return;
      }

      root.querySelectorAll('[data-sffc-community-direct-modal]').forEach(function (modal) {
        if (!modal.hidden) {
          closeCommunityDirectModal(modal.closest('.sffc-community-editorial__post-shell'));
        }
      });
      var companyModal = root.querySelector('[data-sffc-community-company-modal]');
      if (companyModal && !companyModal.hidden) {
        closeCommunityCompanyModal(root);
      }
      var reachedDetailModal = root.querySelector('[data-sffc-community-reached-modal]');
      if (reachedDetailModal && !reachedDetailModal.hidden) {
        closeCommunityReachedModal(root);
      }
      var cvBenefitsGate = getCommunityCvBenefitsGate(root);
      if (cvBenefitsGate && !cvBenefitsGate.hidden) {
        closeCommunityCvBenefitsGate(root);
      }
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-sffc-community-editorial]').forEach(init);
    document.querySelectorAll('[data-sffc-community-editorial-search]').forEach(initStandaloneCommunitySearch);
    document.querySelectorAll('[data-sffc-community-filter-launcher]').forEach(initCommunityFilterLauncher);
    document.querySelectorAll('[data-sffc-jobs-carousel]').forEach(initJobsCarousel);
  });
})();
