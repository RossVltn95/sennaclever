(function () {
  'use strict';

  var config = window.sffcCrmPostArticleLayout || {};

  function parseAjaxJson(response) {
    return response.text().then(function (text) {
      try {
        return JSON.parse(text);
      } catch (error) {
        throw new Error('The server returned an invalid response.');
      }
    });
  }

  function syncSignupPrefill(payload) {
    var body;

    if (!config.ajaxUrl || !config.prefillNonce || typeof window.fetch !== 'function') {
      return Promise.resolve();
    }

    body = new URLSearchParams();
    body.set('action', 'sffc_sync_signup_prefill');
    body.set('nonce', config.prefillNonce);
    body.set('email', String(payload.email || ''));
    body.set('first_name', String(payload.firstName || ''));
    body.set('last_name', String(payload.lastName || ''));

    return window.fetch(config.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      body: body.toString(),
      keepalive: true
    }).catch(function () {});
  }

  function splitFullName(fullName) {
    var parts = String(fullName || '')
      .trim()
      .split(/\s+/)
      .filter(Boolean);

    return {
      firstName: parts.shift() || '',
      lastName: parts.join(' ')
    };
  }

  function setFeedback(modal, message, isError) {
    var feedback = modal ? modal.querySelector('[data-sffc-post-article-intro-feedback]') : null;
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
    feedback.textContent = String(message);
    feedback.classList.toggle('is-error', !!isError);
    feedback.classList.toggle('is-success', !isError);
  }

  function setSignupFeedback(scope, message, isError) {
    var feedback = scope ? scope.querySelector('[data-sffc-post-article-signup-feedback]') : null;
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
    feedback.textContent = String(message);
    feedback.classList.toggle('is-error', !!isError);
    feedback.classList.toggle('is-success', !isError);
  }

  function openModal(layout) {
    var modal = layout ? layout.querySelector('[data-sffc-post-article-intro-modal]') : null;
    var dialog = modal ? modal.querySelector('.sffc-crm-post-article-layout__intro-dialog') : null;

    if (!modal) {
      return;
    }

    setFeedback(modal, '', false);
    modal.hidden = false;
    document.documentElement.classList.add('sffc-crm-post-article-layout-modal-open');
    document.body.classList.add('sffc-crm-post-article-layout-modal-open');

    if (dialog && typeof dialog.focus === 'function') {
      window.setTimeout(function () {
        dialog.focus();
      }, 20);
    }
  }

  function closeModal(layout) {
    var modal = layout ? layout.querySelector('[data-sffc-post-article-intro-modal]') : null;

    if (!modal) {
      return;
    }

    modal.hidden = true;
    document.documentElement.classList.remove('sffc-crm-post-article-layout-modal-open');
    document.body.classList.remove('sffc-crm-post-article-layout-modal-open');
  }

  function openSignupModal(layout) {
    var modal = layout ? layout.querySelector('[data-sffc-post-article-signup-modal]') : null;
    var dialog = modal ? modal.querySelector('.sffc-crm-post-article-layout__signup-dialog') : null;
    var firstInput = modal ? modal.querySelector('input[name="first_name"]') : null;

    if (!modal) {
      return;
    }

    setSignupFeedback(modal, '', false);
    modal.hidden = false;
    document.documentElement.classList.add('sffc-crm-post-article-layout-modal-open');
    document.body.classList.add('sffc-crm-post-article-layout-modal-open');

    window.setTimeout(function () {
      if (firstInput && typeof firstInput.focus === 'function') {
        firstInput.focus();
      } else if (dialog && typeof dialog.focus === 'function') {
        dialog.focus();
      }
    }, 20);
  }

  function closeSignupModal(layout) {
    var modal = layout ? layout.querySelector('[data-sffc-post-article-signup-modal]') : null;

    if (!modal) {
      return;
    }

    modal.hidden = true;
    document.documentElement.classList.remove('sffc-crm-post-article-layout-modal-open');
    document.body.classList.remove('sffc-crm-post-article-layout-modal-open');
  }

  function markIntroInProgress(layout) {
    var trigger = layout ? layout.querySelector('[data-sffc-post-article-intro-open]') : null;
    if (!trigger) {
      return;
    }

    trigger.classList.add('is-send-intro-progress');
    trigger.textContent = 'In Progress';
    trigger.disabled = true;
    trigger.setAttribute('aria-disabled', 'true');
  }

  function submitIntro(layout, form) {
    var modal = layout ? layout.querySelector('[data-sffc-post-article-intro-modal]') : null;
    var submitButton = form ? form.querySelector('[data-sffc-post-article-intro-submit]') : null;
    var cvSelect = form ? form.querySelector('[data-sffc-post-article-intro-cv]') : null;
    var selectedOption = cvSelect ? cvSelect.options[cvSelect.selectedIndex] : null;
    var originalLabel = submitButton ? submitButton.textContent : '';
    var formData;

    if (!layout || !form || typeof window.fetch !== 'function') {
      return;
    }

    formData = new FormData(form);
    formData.append('action', 'sffc_crm_submit_discovery_intro_request');
    formData.append('nonce', config.crmNonce || '');
    formData.append('stored_cv_name', selectedOption ? String(selectedOption.getAttribute('data-cv-name') || '') : '');

    setFeedback(modal, '', false);

    if (submitButton) {
      submitButton.disabled = true;
      submitButton.textContent = 'Submitting...';
    }

    window.fetch(config.ajaxUrl || '/wp-admin/admin-ajax.php', {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
    })
      .then(parseAjaxJson)
      .then(function (payload) {
        if (!payload || !payload.success) {
          throw new Error((payload && payload.data && payload.data.message) || 'Unable to submit this intro request.');
        }

        setFeedback(modal, (payload.data && payload.data.message) || 'Intro request received.', false);
        markIntroInProgress(layout);
        window.setTimeout(function () {
          closeModal(layout);
        }, 650);
      })
      .catch(function (error) {
        setFeedback(modal, error && error.message ? error.message : 'Unable to submit this intro request.', true);
        if (submitButton) {
          submitButton.disabled = false;
          submitButton.textContent = originalLabel;
        }
      });
  }

  function submitSignup(layout, form) {
    var modal = layout ? layout.querySelector('[data-sffc-post-article-signup-modal]') : null;
    var feedbackScope = modal || form;
    var submitButton = form ? form.querySelector('[data-sffc-post-article-signup-submit]') : null;
    var originalLabel = submitButton ? submitButton.textContent : '';
    var formData;
    var isMembershipCapture = !!(form && form.hasAttribute('data-sffc-post-article-membership-capture'));

    if (!layout || !form || typeof window.fetch !== 'function') {
      return;
    }

    if (isMembershipCapture) {
      var fullName = String((form.querySelector('input[name="first_name"]') || {}).value || '').trim();
      var email = String((form.querySelector('input[name="email"]') || {}).value || '').trim();
      var nameParts = splitFullName(fullName);

      if (fullName === '' || email === '') {
        setSignupFeedback(feedbackScope, 'Enter your name and email to continue.', true);
        return;
      }

      if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = 'Opening membership...';
      }

      setSignupFeedback(feedbackScope, '', false);
      syncSignupPrefill({
        email: email,
        firstName: nameParts.firstName || fullName,
        lastName: nameParts.lastName || ''
      });

      var membershipsUrl = new URL(config.membershipsUrl || '/memberships/', window.location.origin);
      membershipsUrl.searchParams.set('full_name', fullName);
      membershipsUrl.searchParams.set('email', email);
      window.location.href = membershipsUrl.toString();

      return;
    }

    formData = new FormData(form);
    formData.append('action', 'sffc_crm_post_article_free_signup');
    formData.append('nonce', config.signupNonce || '');

    setSignupFeedback(feedbackScope, '', false);

    if (submitButton) {
      submitButton.disabled = true;
      submitButton.textContent = 'Continuing...';
    }

    window.fetch(config.ajaxUrl || '/wp-admin/admin-ajax.php', {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
    })
      .then(parseAjaxJson)
      .then(function (payload) {
        var redirectUrl;
        if (!payload || !payload.success) {
          throw new Error((payload && payload.data && payload.data.message) || 'Unable to continue right now.');
        }

        setSignupFeedback(feedbackScope, 'Opening memberships...', false);
        redirectUrl = (payload.data && payload.data.redirect) || config.membershipsUrl || '/memberships/';
        window.setTimeout(function () {
          window.location.href = redirectUrl;
        }, 350);
      })
      .catch(function (error) {
        var message = error && error.message ? error.message : 'Unable to continue right now.';
        setSignupFeedback(feedbackScope, message, true);
        if (submitButton) {
          submitButton.disabled = false;
          submitButton.textContent = originalLabel;
        }
      });
  }

  function closeApplyDropdowns(except) {
    document.querySelectorAll('[data-sffc-post-article-apply-dropdown].is-open').forEach(function (dropdown) {
      var toggle;
      var menu;

      if (except && dropdown === except) {
        return;
      }

      toggle = dropdown.querySelector('[data-sffc-post-article-apply-toggle]');
      menu = dropdown.querySelector('[data-sffc-post-article-apply-menu]');
      dropdown.classList.remove('is-open');
      if (toggle) {
        toggle.setAttribute('aria-expanded', 'false');
      }
      if (menu) {
        menu.hidden = true;
      }
    });
  }

  document.addEventListener('click', function (event) {
    var openTrigger = event.target.closest('[data-sffc-post-article-intro-open]');
    var closeTrigger = event.target.closest('[data-sffc-post-article-intro-close]');
    var signupTrigger = event.target.closest('[data-sffc-post-article-signup-open]');
    var signupCloseTrigger = event.target.closest('[data-sffc-post-article-signup-close]');
    var applyToggle = event.target.closest('[data-sffc-post-article-apply-toggle]');
    var applyDropdown = event.target.closest('[data-sffc-post-article-apply-dropdown]');

    if (applyToggle) {
      var dropdown = applyToggle.closest('[data-sffc-post-article-apply-dropdown]');
      var menu = dropdown ? dropdown.querySelector('[data-sffc-post-article-apply-menu]') : null;
      var shouldOpen = applyToggle.getAttribute('aria-expanded') !== 'true';

      event.preventDefault();
      closeApplyDropdowns(dropdown);

      if (dropdown && menu) {
        dropdown.classList.toggle('is-open', shouldOpen);
        menu.hidden = !shouldOpen;
        applyToggle.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
      }
      return;
    }

    if (!applyDropdown) {
      closeApplyDropdowns(null);
    }

    if (signupTrigger && !config.isLoggedIn) {
      event.preventDefault();
      closeApplyDropdowns(null);
      openSignupModal(signupTrigger.closest('[data-component="crm-post-article-layout"]'));
      return;
    }

    if (signupCloseTrigger) {
      event.preventDefault();
      closeSignupModal(signupCloseTrigger.closest('[data-component="crm-post-article-layout"]'));
      return;
    }

    if (openTrigger) {
      event.preventDefault();
      closeApplyDropdowns(null);
      openModal(openTrigger.closest('[data-component="crm-post-article-layout"]'));
      return;
    }

    if (closeTrigger) {
      event.preventDefault();
      closeModal(closeTrigger.closest('[data-component="crm-post-article-layout"]'));
    }
  });

  document.addEventListener('submit', function (event) {
    var form = event.target.closest('[data-sffc-post-article-intro-form]');
    var signupForm = event.target.closest('[data-sffc-post-article-signup-form]');

    if (signupForm) {
      event.preventDefault();
      submitSignup(signupForm.closest('[data-component="crm-post-article-layout"]'), signupForm);
      return;
    }

    if (!form) {
      return;
    }

    event.preventDefault();
    submitIntro(form.closest('[data-component="crm-post-article-layout"]'), form);
  });

  document.addEventListener('keydown', function (event) {
    var openModalNode;

    if (event.key !== 'Escape') {
      return;
    }

    closeApplyDropdowns(null);

    openModalNode = document.querySelector('[data-sffc-post-article-intro-modal]:not([hidden])');
    if (openModalNode) {
      closeModal(openModalNode.closest('[data-component="crm-post-article-layout"]'));
      return;
    }

    openModalNode = document.querySelector('[data-sffc-post-article-signup-modal]:not([hidden])');
    if (openModalNode) {
      closeSignupModal(openModalNode.closest('[data-component="crm-post-article-layout"]'));
      return;
    }

  });
})();
