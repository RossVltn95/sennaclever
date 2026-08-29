(function ($) {
  'use strict';

  $(function () {
    var settings = window.sffcAskSenna || null;
    var $widget = $('[data-ask-senna]');

    if (!settings || !$widget.length) {
      return;
    }

    var $toggle = $widget.find('[data-role="toggle"]');
    var $panel = $widget.find('[data-role="panel"]');
    var $messages = $widget.find('[data-role="messages"]');
    var $form = $widget.find('[data-role="form"]');
    var $input = $widget.find('[data-role="input"]');
    var $close = $widget.find('[data-role="close"]');
    var $greeting = $widget.find('[data-role="greeting"]');
    var $templates = $widget.find('[data-role="templates"] button');
    var isLoggedIn = settings.isLoggedIn || false;
    var questionCount = 0;
    
    // Show delayed greeting notification
    showDelayedGreeting();
    var fallbackReplies = {
      'Hello, how are you?': "Always on and ready—ask me about hidden roles, recruiter introductions, or how to optimise your applications.",
      'Can you show me hidden roles in London private equity that match my background?': "Here's how I surface hidden opportunities for you:\n\n• **Private briefings**: I scan recruiter-only posts and confidential mandates (PE, credit, infra) that never make it to job boards.\n• **Smart filters**: I match by comp band, location, fund strategy, and team size so you only see what fits.\n• **Action panel**: Each role links to the recruiter, match score, and suggested next step (intro, message, or external apply).\n\nTell me the sectors/locations you care about and I will assemble a shortlist you can act on now.",
      'Introduce me to recruiters actively hiring investment banking associates.': "You can get routed straight to recruiters who respond. Here's what happens:\n\n1. **Signal scan** – I track which recruiters and search firms posted fresh mandates this week.\n2. **Response intelligence** – Recruiters with the High Response Rate badge sit at the top so you waste zero time.\n3. **One-click intro** – Hit *Introduce Me* and I'll prep the outreach plus log it inside your Outreach Lists tab.\n\nShare your vertical (IB, infra, levfin, ECM, etc.) and location, and I'll line up the right recruiters for you.",
      "Draft a recruiter message that explains why I'm a strong fit for this infrastructure finance role.": "Happy to craft it. My recruiter messages:\n\n• Reference the specific deal work, modelling depth, and regulatory exposure in the mandate.\n• Mirror the recruiter's tone while highlighting why your background plugs gaps in their search.\n• Include a direct CTA (intro call or send materials) and automatically log to your CRM messaging history.\n\nPaste the role highlights plus 2-3 achievements and I'll generate a polished message ready to send.",
      'Analyze my resume against the attached job description and highlight gaps to fix.': "Here's how resume intelligence works:\n\n• **Bulk CV engine** – I run your PDF/DOC through the same evaluator recruiters use, checking technical keywords, deal types, certifications, and years of experience.\n• **Gap report** – Green/yellow/red callouts map each section of the JD so you know exactly what to tighten.\n• **Action tips** – I recommend quantified bullets, phrasing, and sequencing so ATS + hiring managers pick up the right signals.\n\nUpload your resume + JD (or paste text) and I'll reply with the breakdown.",
      'Show me which recruiters are responding fastest right now and how to reach them.': "Great move. I track recruiter engagement by monitoring replies, outreach success, and Premium member activity.\n\n• **High Response Rate badge** – When you see it, that recruiter replied to members in the last 7 days.\n• **Contact paths** – You'll get email, LinkedIn, or CRM messaging access depending on your membership tier.\n• **Suggested next steps** – I recommend whether to request an intro, send a direct message, or log in Outreach for follow-up reminders.\n\nTell me the function and region, and I'll surface the recruiters worth contacting first.",
      'Can you help me write a recruiter outreach note?': "Absolutely. Share the role or team and two achievements you want to highlight. I'll deliver a concise intro that mirrors the JD, shows deal impact, and closes with a clear CTA for a call."
    };
    var typing = false;
    var greeted = false;
    var statusTimers = [];
    var statusMessages = {
      jobs: [
        'Looking for suitable jobs…',
        'Oh wait, this looks interesting.',
        'This could suit your profile—adding it to the shortlist.'
      ],
      news: [
        'Scanning the latest briefs…',
        'Cross-referencing M&A flow…',
        'Pinning a digest for you now.'
      ],
      glossary: ['Checking definitions…', 'Searching deeper…']
    };

    // Show greeting bubble after delay
    $greeting.remove();

    $toggle.on('click', function (event) {
      event.preventDefault();
      togglePanel();
    });

    $close.on('click', function (event) {
      event.preventDefault();
      togglePanel(false);
    });

    $(document).on('mousedown', function (event) {
      if (!$widget.hasClass('is-open')) {
        return;
      }
      var $target = $(event.target);
      if (!$target.closest('.sffc-ask-senna-panel, .sffc-ask-senna-toggle').length) {
        togglePanel(false);
      }
    });

    $widget.on('mouseenter', function () {
      $widget.addClass('is-hover');
    }).on('mouseleave', function () {
      $widget.removeClass('is-hover');
    });

    $form.on('submit', function (event) {
      event.preventDefault();
      var text = ($input.val() || '').trim();
      if (!text || typing) {
        return;
      }
      sendMessage(text);
    });

    $input.on('keydown', function (event) {
      if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        var value = ($input.val() || '').trim();
        if (!value || typing) {
          return;
        }
        sendMessage(value);
      }
    });

    $templates.on('click', function (event) {
      event.preventDefault();
      var template = $(this).data('template') || '';
      if (!template) {
        return;
      }
      if ($widget.hasClass('is-open') === false) {
        togglePanel(true);
      }
      sendMessage(template);
    });

    // Handle clicks on links in MENA Careers responses for non-logged-in users
    $messages.on('click', 'a', function(event) {
      if (!isLoggedIn) {
        event.preventDefault();
        showSubscribePrompt();
      }
    });

    function togglePanel(forceOpen) {
      var shouldOpen = typeof forceOpen === 'boolean' ? forceOpen : !$widget.hasClass('is-open');
      $widget.toggleClass('is-open', shouldOpen);
      $panel.toggleClass('is-visible', shouldOpen);
      $toggle.toggleClass('is-hidden', shouldOpen);
      if (shouldOpen) {
        if (!greeted) {
          addMessage('senna', settings.greetingText);
          greeted = true;
        }
        setTimeout(function () {
          $input.trigger('focus');
        }, 150);
      } else {
        clearStatusUpdates();
      }
    }

    function sendMessage(text) {
      // Check if logged out and exceeded question limit
      if (!isLoggedIn) {
        questionCount++;
        if (questionCount > 1) {
          addMessage('user', text);
          $input.val('');
          showSubscribePrompt();
          return;
        }
      }
      
      typing = true;
      var intent = detectIntent(text);
      addMessage('user', text);
      $input.val('');
      scheduleStatusUpdates(intent);
      fetchAssistantResponse(text, intent);
    }

    function fetchAssistantResponse(text, intent) {
      showTypingIndicator();

      $.ajax({
        method: 'POST',
        url: settings.ajaxUrl,
        dataType: 'json',
        data: {
          action: 'sffc_dashboard_quick_assist',
          nonce: settings.nonce,
          query: text,
          intent: intent
        }
      })
        .done(function (response) {
          hideTypingIndicator();
          clearStatusUpdates();
          if (response && response.success && response.data) {
            if (response.data.handled) {
              typing = false;
              if (response.data.message) {
                addMessage('senna', response.data.message);
              }
              if (response.data.jobs && response.data.jobs.length) {
                renderJobSuggestions(response.data.jobs);
              }
              if (response.data.news) {
                renderNewsDigest(response.data.news);
              }
              return;
            }
          }
          requestClaude(text);
        })
        .fail(function () {
          hideTypingIndicator();
          clearStatusUpdates();
          requestClaude(text);
        });
    }

    function requestClaude(text) {
      showTypingIndicator();

      $.ajax({
        method: 'POST',
        url: settings.ajaxUrl,
        dataType: 'json',
        data: {
          action: 'sffc_process_chat_query',
          nonce: settings.nonce,
          query: text,
          context: 'job_search'
        }
      })
        .done(function (response) {
          hideTypingIndicator();
          typing = false;
          if (response && response.success && response.data) {
            addMessage('senna', response.data.message || settings.errorText);
            if (response.data.jobs && response.data.jobs.length) {
              renderJobSuggestions(response.data.jobs);
            }
          } else {
            addMessage('senna', determineFallback(text));
          }
        })
        .fail(function () {
          hideTypingIndicator();
          typing = false;
          addMessage('senna', determineFallback(text));
        });
    }

    function determineFallback(queryText) {
      var preset = fallbackReplies[queryText];
      if (preset) {
        return preset;
      }
      return settings.errorText;
    }

    function showTypingIndicator() {
      if ($messages.find('.sffc-ask-senna-typing').length) {
        return;
      }
      var $indicator = $('<div/>', { class: 'sffc-ask-senna-message is-senna sffc-ask-senna-typing' });
      $indicator.append(buildAvatar());
      var $bubble = $('<div/>', { class: 'sffc-ask-senna-bubble' });
      $bubble.append('<span class="dot"></span><span class="dot"></span><span class="dot"></span>');
      $indicator.append($bubble);
      $messages.append($indicator);
      scrollMessages();
    }

    function hideTypingIndicator() {
      $messages.find('.sffc-ask-senna-typing').remove();
    }

    function addMessage(role, text) {
      if (!text) {
        return;
      }

      var $message = $('<div/>', { class: 'sffc-ask-senna-message is-' + role });
      if (role === 'senna') {
        $message.append(buildAvatar());
      }

      var $bubble = $('<div/>', { class: 'sffc-ask-senna-bubble' });
      appendTextWithBreaks($bubble, text);
      $message.append($bubble);
      $messages.append($message);
      scrollMessages();
    }

    function showSubscribePrompt() {
      var $message = $('<div/>', { class: 'sffc-ask-senna-message is-senna' });
      $message.append(buildAvatar());
      
      var $bubble = $('<div/>', { class: 'sffc-ask-senna-bubble' });
      $bubble.append($('<p/>').text('To continue our conversation and access unlimited questions, please subscribe to our premium plan.'));
      
      // Create subscribe button matching sffc-job-cta style
      var $subscribeBtn = $('<button/>', {
        class: 'sffc-job-cta',
        text: 'Subscribe',
        style: 'margin-top: 12px; display: inline-flex; align-items: center; gap: 8px;'
      });
      
      // Add arrow icon
      $subscribeBtn.append($('<svg/>', {
        width: '20',
        height: '20',
        viewBox: '0 0 20 20',
        fill: 'none',
        html: '<path d="M7.5 5L12.5 10L7.5 15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>'
      }));
      
      $subscribeBtn.on('click', function() {
        // Open plan modal if available, otherwise redirect
        var $modal = $('[data-plan-modal]');
        if ($modal.length) {
          $modal.attr('aria-hidden', 'false').addClass('is-open');
          $('body').addClass('plan-modal-open');
        } else {
          window.location.href = 'https://senna.com/sign-up';
        }
      });
      
      $bubble.append($subscribeBtn);
      $message.append($bubble);
      $messages.append($message);
      scrollMessages();
      
      // Disable input
      $input.prop('disabled', true).attr('placeholder', 'Subscribe to continue...');
    }

    function renderJobSuggestions(jobs) {
      if (!jobs || !jobs.length) {
        return;
      }

      var $message = $('<div/>', { class: 'sffc-ask-senna-message is-senna' });
      $message.append(buildAvatar());
      var $bubble = $('<div/>', { class: 'sffc-ask-senna-bubble' });
      appendTextWithBreaks($bubble, settings.jobsIntroText);

      var $list = $('<div/>', { class: 'sffc-ask-senna-jobs' });

      jobs.slice(0, 3).forEach(function (job) {
        var $jobLink = $('<a/>', {
          class: 'sffc-ask-senna-job',
          href: job.link || job.url || job.permalink || '#',
          target: '_blank',
          rel: 'noopener noreferrer'
        });

        var $title = $('<div/>', { class: 'sffc-ask-senna-job-title' }).text(job.title || job.job_title || 'Opportunity');
        var $meta = $('<div/>', { class: 'sffc-ask-senna-job-meta' });

        if (job.company || job.company_name) {
          $meta.append($('<span/>').text(job.company || job.company_name));
        }
        if (job.location || job.city) {
          $meta.append($('<span/>').text(job.location || job.city));
        }

        $jobLink.append($title, $meta, $('<span/>', { class: 'sffc-ask-senna-job-arrow', text: '→' }));
        $list.append($jobLink);
      });

      $bubble.append($list);
      $message.append($bubble);
      $messages.append($message);
      scrollMessages();
    }

    function renderNewsDigest(payload) {
      if (!payload) {
        return;
      }

      var $message = $('<div/>', { class: 'sffc-ask-senna-message is-senna' });
      $message.append(buildAvatar());
      var $bubble = $('<div/>', { class: 'sffc-ask-senna-bubble' });
      var $wrapper = $('<div/>', { class: 'sffc-ask-senna-news' });

      var labels = settings.newsLabels || {};
      var researchLabel = labels.research || 'Research Briefs';
      var dealLabel = labels.deals || 'Deal Flow';

      if (payload.pe_news && payload.pe_news.length) {
        $wrapper.append(buildNewsGroup(researchLabel, payload.pe_news));
      }

      if (payload.deals && payload.deals.length) {
        $wrapper.append(buildNewsGroup(dealLabel, payload.deals));
      }

      $bubble.append($wrapper);
      $message.append($bubble);
      $messages.append($message);
      scrollMessages();
    }

    function buildNewsGroup(title, items) {
      var $group = $('<div/>', { class: 'sffc-ask-senna-news-group' });
      $group.append($('<p/>', { class: 'sffc-ask-senna-news-heading', text: title }));

      items.forEach(function (item) {
        var $entry = $('<a/>', {
          class: 'sffc-ask-senna-news-item',
          href: item.link || '#',
          target: '_blank',
          rel: 'noopener noreferrer'
        });
        $entry.append($('<span/>', { class: 'sffc-ask-senna-news-title', text: item.title }));
        $entry.append($('<span/>', { class: 'sffc-ask-senna-news-meta', text: item.timestamp || '' }));
        $group.append($entry);
      });

      return $group;
    }

    function buildAvatar() {
      return $('<div/>', { class: 'sffc-ask-senna-avatar' }).append(
        $('<img/>', { src: settings.avatar, alt: 'MENA Careers' })
      );
    }

    function appendTextWithBreaks($container, text) {
      var lines = (text || '').split(/\n+/);
      lines.forEach(function (line, index) {
        if (index > 0) {
          $container.append('<br>');
        }
        $container.append(document.createTextNode(line.trim()));
      });
    }

    function scrollMessages() {
      $messages.scrollTop($messages[0].scrollHeight);
    }

    function detectIntent(text) {
      var value = (text || '').toLowerCase();
      if (/job|role|position|vacancy|hire|opportun/.test(value)) {
        return 'jobs';
      }
      if (/news|headline|update|deal|latest/.test(value)) {
        return 'news';
      }
      if (/what is|meaning|define|definition|who is|explain/.test(value)) {
        return 'glossary';
      }
      return 'general';
    }

    function scheduleStatusUpdates(intent) {
      clearStatusUpdates();
      var list = statusMessages[intent];
      if (!list || !list.length) {
        return;
      }

      list.forEach(function (text, index) {
        var timer = setTimeout(function () {
          addStatusMessage(text);
        }, 900 * index);
        statusTimers.push(timer);
      });
    }

    function addStatusMessage(text) {
      var $message = $('<div/>', { class: 'sffc-ask-senna-message is-senna sffc-ask-senna-status' });
      $message.append(buildAvatar());
      var $bubble = $('<div/>', { class: 'sffc-ask-senna-bubble' });
      appendTextWithBreaks($bubble, text);
      $message.append($bubble);
      $messages.append($message);
      scrollMessages();
    }

    function clearStatusUpdates() {
      statusTimers.forEach(function (timer) {
        clearTimeout(timer);
      });
      statusTimers = [];
      $messages.find('.sffc-ask-senna-status').remove();
    }
    
    function showDelayedGreeting() {
      // Get current time-based greeting
      var hour = new Date().getHours();
      var timeGreeting = hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening';
      
      // Create greeting notification
      setTimeout(function() {
        // Check if notification hasn't been shown today
        var today = new Date().toDateString();
        var lastShown = localStorage.getItem('sffcGreetingShown');
        
        if (lastShown !== today) {
          // Get plugin URL from settings or construct it
          var pluginUrl = settings.pluginUrl || '/wp-content/plugins/senna-finance-career/';
          var sennaImageUrl = pluginUrl + 'assets/images/senna.jpeg';
          
          // Create notification element with inline styles
          var $notification = $('<div/>', {
            html: '<div style="display: flex; align-items: center; gap: 12px;">' +
                  '<img src="' + sennaImageUrl + '" style="width: 40px !important; height: 40px !important; border-radius: 50% !important; object-fit: cover !important; flex-shrink: 0 !important;" alt="MENA Careers Assistant">' +
                  '<div style="flex: 1;">' +
                  '<p style="margin: 0; font-size: 14px; font-weight: 600; color: #0d353e;">Hi! Welcome, let me know if you need any help</p>' +
                  '</div>' +
                  '<button style="background: transparent; border: none; font-size: 20px; color: #9ca3af; cursor: pointer; padding: 4px;" aria-label="Close">&times;</button>' +
                  '</div>'
          });
          
          // Style and position the notification
          $notification.css({
            position: 'fixed',
            bottom: ($widget.height() + 120) + 'px',
            right: '32px',
            zIndex: 9998,
            background: 'white',
            borderRadius: '12px',
            boxShadow: '0 8px 24px rgba(0, 0, 0, 0.12)',
            padding: '12px 16px',
            maxWidth: '320px',
            border: '1px solid rgba(13, 53, 62, 0.08)',
            opacity: 0,
            transform: 'translateY(20px)',
            transition: 'all 0.4s ease'
          });
          
          // Add to body
          $('body').append($notification);
          
          // Animate in
          setTimeout(function() {
            $notification.css({
              opacity: 1,
              transform: 'translateY(0)'
            });
          }, 100);
          
          // Close button handler
          $notification.find('button').on('click', function() {
            $notification.css({
              opacity: 0,
              transform: 'translateY(20px)'
            });
            setTimeout(function() {
              $notification.remove();
            }, 400);
          });
          
          // Auto-hide after 8 seconds
          setTimeout(function() {
            $notification.css({
              opacity: 0,
              transform: 'translateY(20px)'
            });
            setTimeout(function() {
              $notification.remove();
            }, 400);
          }, 8000);
          
          // Mark as shown today
          localStorage.setItem('sffcGreetingShown', today);
        }
      }, 3000); // Show after 3 seconds
    }
  });
})(jQuery);
