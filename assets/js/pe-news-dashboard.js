(function ($) {
  'use strict';

  function initNewsDashboard($root) {
    if (!$root.length) {
      return;
    }

    var $body = $('body');
    var defaultTab = ($root.data('defaultTab') || 'insights').toString();
    var $filterColumn = $root.find('.sffc-column--left');
    var $filterOverlay = $root.find('[data-role="filter-overlay"]');
    var rawIds = ($root.data('postIds') || '').toString();
    var postIds = rawIds
      ? rawIds.split(',').map(function (val) {
          return parseInt(val, 10);
        }).filter(function (val) {
          return !isNaN(val);
        })
      : [];
    var nonce = $root.data('nonce');
    var state = {
      activeTab: defaultTab,
      filters: {
        insights: {
          deal_types: null,
          regions: null,
          sectors: null
        },
        jobs: {
          job_functions: null,
          job_regions: null,
          job_levels: null
        }
      },
      saved: {},
      searchQuery: '',
      loadLimit: {
        insights: 6,
        jobs: 6,
        saved: 6,
        research: 6,
        alerts: 6
      },
      loadStep: 4
    };
    var strings = (window.sffcNewsDashboard && window.sffcNewsDashboard.strings) || {};
    var ajaxUrl = window.sffcNewsDashboard ? window.sffcNewsDashboard.ajax_url : null;
    var isLoggedIn = parseInt($root.data('loggedIn'), 10) === 1;
    var $dashboardContainer = $root.closest('.sffc-dashboard-container');
    var $globalHeader = $('.sffc-global-header');
    var $searchContext = $globalHeader.length ? $globalHeader : $dashboardContainer;
    if (!$searchContext || !$searchContext.length) {
      $searchContext = $root;
    }
    var $searchInput = $searchContext.find('[data-role="search-input"]');
    var $searchClear = $searchContext.find('[data-role="search-clear"]');
    var $searchSuggestions = $searchContext.find('[data-role="search-suggestions"]');
    var $dashboardSearch = $searchContext.find('[data-role="dashboard-search"]');
    var $userMenu = $('.sffc-global-header [data-role="user-menu"]');
    if (!$userMenu.length && $dashboardContainer.length) {
      $userMenu = $dashboardContainer.find('[data-role="user-menu"]');
    }
    if (!$userMenu.length) {
      $userMenu = $('[data-role="user-menu"]').first();
    }
    var $planModal = $('[data-plan-modal]');
    var $planForms = $planModal.find('[data-plan-form]');
    var $planCheckout = $planModal.find('[data-plan-checkout]');
    var $planMessage = $planModal.find('[data-plan-message]');
    var $planExternal = $planModal.find('[data-plan-external]');
    var $planExternalLink = $planModal.find('[data-plan-external-link]');
    var searchEntries = [];
    var instanceId = Math.floor(Math.random() * 100000);
    var docNamespace = '.sffcDashboard' + instanceId;

    state.activeTab = null;
    activateTab(defaultTab);
    hydrateSavedState();
    renderSaved();
    rebuildSearchIndex();

    if ($searchInput.length) {
      $searchInput.on('input', function () {
        setSearchQuery($(this).val());
      });

      $searchInput.on('focus', function () {
        renderSuggestions($searchInput.val());
      });
    }

    if ($searchClear.length) {
      $searchClear.on('click', function () {
        $searchInput.val('');
        setSearchQuery('');
        closeSearchSuggestions();
      });
    }

    if ($searchSuggestions.length) {
      $searchSuggestions.on('click', '.sffc-search-suggestion', function () {
        var targetTab = $(this).data('suggestion-tab');
        var postId = $(this).data('suggestion-id');
        var queryValue = $(this).data('suggestion-query') || $searchInput.val();
        if (typeof queryValue !== 'undefined') {
          $searchInput.val(queryValue);
          setSearchQuery(queryValue);
        }
        closeSearchSuggestions();
        if (targetTab) {
          activateTab(targetTab);
        }
        if (postId) {
          var $targetCard = $root.find('.sffc-feed-card[data-post-id="' + postId + '"]').first();
          if ($targetCard.length) {
            $targetCard.get(0).scrollIntoView({ behavior: 'smooth', block: 'start' });
            highlightCard($targetCard);
          }
        }
      });
    }

    $(document).on('click', '[data-action="open-plan-modal"]', function (event) {
      if (!$planModal.length) {
        return;
      }
      event.preventDefault();
      openPlanModal();
    });

    $planModal.on('click', '[data-plan-close]', function () {
      closePlanModal();
    });

    // Handle clicks on demo message cards for non-logged-in users
    $(document).on('click', '.sffc-message-card[data-trigger-modal="true"]', function (event) {
      event.preventDefault();
      if (!isLoggedIn && $planModal.length) {
        openPlanModal();
      }
    });

    // Handle clicks on unlock button for demo messages
    $(document).on('click', '.sffc-unlock-btn', function (event) {
      event.preventDefault();
      if ($planModal.length) {
        openPlanModal();
      }
    });

    // Handle clicks on join button
    $(document).on('click', '.sffc-join-btn', function (event) {
      event.preventDefault();
      if ($planModal.length) {
        openPlanModal();
      }
    });

    // Handle clicks on feed cards when logged out
    $(document).on('click', '.sffc-feed-card', function (event) {
      if (!isLoggedIn && $planModal.length) {
        event.preventDefault();
        event.stopPropagation();
        openPlanModal();
      }
    });

    // Handle clicks on job CTA buttons when logged out
    $(document).on('click', '.sffc-job-cta', function (event) {
      if (!isLoggedIn && $planModal.length) {
        event.preventDefault();
        openPlanModal();
      }
    });

    $planModal.on('click', '[data-plan-select]', function () {
      var $button = $(this);
      var url = $button.data('planUrl');
      var slug = $button.data('planSlug');
      if (!slug && !url) {
        return;
      }
      $planModal.find('[data-plan-card]').removeClass('is-active');
      $button.closest('[data-plan-card]').addClass('is-active');
      var $targetForm = slug ? $planForms.filter('[data-plan-form="' + slug + '"]') : $();
      if ($targetForm.length) {
        if ($planCheckout.length) {
          $planCheckout.removeAttr('hidden');
        }
        $planForms.attr('hidden', 'hidden');
        $targetForm.removeAttr('hidden');
        if ($planMessage.length) {
          $planMessage.text(strings.plan_ready || 'Checkout ready.');
        }
      } else {
        if ($planCheckout.length) {
          $planCheckout.attr('hidden', 'hidden');
        }
        $planForms.attr('hidden', 'hidden');
        if ($planMessage.length) {
          $planMessage.text(strings.plan_loading || 'Opening secure checkout…');
        }
        if (url) {
          window.open(url, '_blank', 'noopener');
        }
      }
      if ($planExternal.length) {
        if (url) {
          $planExternal.removeAttr('hidden');
          if ($planExternalLink.length) {
            $planExternalLink.attr('href', url);
          }
        } else {
          $planExternal.attr('hidden', 'hidden');
          if ($planExternalLink.length) {
            $planExternalLink.attr('href', '#');
          }
        }
      }
    });

    if ($userMenu.length) {
      $(document).on('click', '[data-role="user-toggle"]', function (event) {
        event.preventDefault();
        console.log('User menu toggle clicked'); // Debug log
        var isOpen = $userMenu.hasClass('is-open');
        console.log('Menu currently open:', isOpen); // Debug log
        toggleUserMenu(!isOpen);
      });
    }

    toggleClearButton();
    updateTrendingPanel(state.activeTab);

    $root.on('click', '.sffc-tab-btn', function (event) {
      event.preventDefault();
      var target = $(this).data('tab-target');
      // Allow navigation to messages tab without triggering modal
      activateTab(target);
    });

    $root.on('click', '.sffc-donut-segment', function (event) {
      event.preventDefault();
      var target = $(this).data('tab');
      activateTab(target);
    });

    $root.on('click', '.sffc-donut-segment', function (event) {
      event.preventDefault();
      var target = $(this).data('tab');
      activateTab(target);
    });

    // Profile card "View Profile" button click
    $(document).on('click', '[data-tab-target]', function (event) {
      var $btn = $(this);
      // Skip if it's already a tab button (handled elsewhere)
      if ($btn.hasClass('sffc-tab-btn')) {
        return;
      }
      var target = $btn.data('tab-target');
      if (target) {
        event.preventDefault();
        activateTab(target);
      }
    });

    $root.on('click', '.sffc-filter-toggle', function (event) {
      event.preventDefault();
      toggleFilters(true);
    });

    $root.on('click', '[data-role="filter-overlay"]', function (event) {
      event.preventDefault();
      toggleFilters(false);
    });

    $(document).on('keydown.sffcFilters', function (event) {
      if (event.key === 'Escape') {
        toggleFilters(false);
        toggleUserMenu(false);
        closeSearchSuggestions();
        closePlanModal();
      }
    });

    function activateTab(target) {
      if (!target || state.activeTab === target) {
        return;
      }
      state.activeTab = target;
      $root.find('.sffc-tab-btn').removeClass('is-active');
      $root.find('.sffc-tab-btn[data-tab-target="' + target + '"]').addClass('is-active');
      $root.find('.sffc-feed-tab').removeClass('is-active');
      $root.find('.sffc-feed-tab[data-tab="' + target + '"]').addClass('is-active');
      updateFilterStacks();
      applyFilters();
      toggleFilters(false);
      highlightDonutSegment(target);
      updateTrendingPanel(target);
      updateJobSuggestionsPanel(target);
    }

    // Toggle job suggestions panel visibility based on active tab
    function updateJobSuggestionsPanel(tab) {
      var $suggestionsPanel = $('[data-panel="profile-suggestions"]');
      if (!$suggestionsPanel.length) {
        return;
      }
      if (tab === 'signals') {
        $suggestionsPanel.slideDown(200);
      } else {
        $suggestionsPanel.slideUp(200);
      }
    }

    $root.on('click', '.sffc-filter-btn', function (event) {
      event.preventDefault();
      var $btn = $(this);
      var view = getActiveFilterView();
      if (!view) {
        return;
      }
      var availableGroups = state.filters[view];
      if (!availableGroups) {
        return;
      }
      var keyword = $btn.data('keyword');
      var group = $btn.closest('[data-filter-group]').data('filter-group');
      if (!group || typeof availableGroups[group] === 'undefined') {
        return;
      }

      var isActive = $btn.hasClass('is-active');
      var $grid = $btn.closest('.sffc-filter-grid');

      if (keyword === 'all') {
        availableGroups[group] = null;
        $grid.find('.sffc-filter-btn').removeClass('is-active');
        $btn.addClass('is-active');
      } else {
        if (isActive) {
          $btn.removeClass('is-active');
          availableGroups[group] = null;
          $grid.find('[data-keyword="all"]').addClass('is-active');
        } else {
          $grid.find('.sffc-filter-btn').removeClass('is-active');
          $btn.addClass('is-active');
          availableGroups[group] = keyword;
        }
      }

      applyFilters();
    });

    $root.on('click', '.sffc-save-btn', function (event) {
      event.preventDefault();
      var $btn = $(this);
      var $card = $btn.closest('.sffc-feed-card');
      var cardId = $card.data('postId');
      if (!cardId) {
        return;
      }

      var wasSaved = $btn.hasClass('is-saved');
      var targetState = !wasSaved;

      if (targetState && !isLoggedIn) {
        if ($planModal.length) {
          openPlanModal();
        } else {
          window.alert(getString('login_required', 'Please sign in to save stories and jobs.'));
        }
        return;
      }

      var previousMarkup = state.saved[cardId];
      setButtonsForPost(cardId, targetState);
      updateSavedState(cardId, targetState, $card);

      syncSavedState(cardId, targetState)
        .fail(function (response) {
          setButtonsForPost(cardId, wasSaved);
          if (typeof previousMarkup !== 'undefined') {
            state.saved[cardId] = previousMarkup;
          } else {
            delete state.saved[cardId];
          }
          renderSaved();
          var message = extractErrorMessage(response) || getString('save_error', 'Unable to update your saved list. Please try again.');
          window.alert(message);
        });
    });

    $root.on('click', '.sffc-tailor-btn', function (event) {
      event.preventDefault();
      triggerTailorCV($(this));
    });

    $root.on('click', '[data-action="refresh-analytics"]', function (event) {
      event.preventDefault();
      fetchAnalytics();
    });

    function getActiveFilterView() {
      if (state.activeTab === 'insights') {
        return 'insights';
      }
      if (state.activeTab === 'jobs') {
        return 'jobs';
      }
      return null;
    }

    function triggerTailorCV($btn) {
      if (!$btn || !$btn.length || $btn.hasClass('is-loading')) {
        return;
      }

      var jobData = buildJobData($btn);
      if (!jobData.id) {
        return;
      }

      $btn.addClass('is-loading');
      window.currentTailoringJob = jobData;

      var handled = dispatchTailorRequest(jobData);
      if (!handled) {
        window.alert(getString('tailor_unavailable', 'We are preparing the CV tailoring workspace. Please try again in a moment.'));
      }

      window.setTimeout(function () {
        $btn.removeClass('is-loading');
      }, 1400);
    }

    function buildJobData($btn) {
      var $card = $btn.closest('.sffc-feed-card');
      var jobId = $btn.data('jobId') || ($card.length ? $card.data('postId') : null);
      var jobTitle = $btn.data('jobTitle') || ($card.length ? $card.find('h3').first().text().trim() : '');
      var jobCompany = $btn.data('jobCompany') || ($card.length ? $card.find('.sffc-job-company').text().trim() : '');
      var jobLocation = $btn.data('jobLocation') || ($card.length ? $card.find('.sffc-job-badge').first().text().trim() : '');
      var jobLink = $btn.data('jobLink') || ($card.length ? $card.find('.sffc-job-cta').attr('href') : '');

      return {
        id: jobId,
        title: jobTitle,
        company: jobCompany,
        location: jobLocation,
        apply_url: jobLink
      };
    }

    function dispatchTailorRequest(jobData) {
      try {
        if (window.tailorCVForJob && typeof window.tailorCVForJob === 'function') {
          window.tailorCVForJob(jobData);
          return true;
        }
        if (window.tailorCVEnhanced && typeof window.tailorCVEnhanced === 'function') {
          window.tailorCVEnhanced(jobData);
          return true;
        }
        if (window.CVTailoringManager && typeof window.CVTailoringManager.tailorCV === 'function') {
          window.CVTailoringManager.tailorCV(jobData);
          return true;
        }
        if (window.CVTailoringEngine && typeof window.CVTailoringEngine.open === 'function') {
          window.CVTailoringEngine.open(jobData);
          return true;
        }
        if (typeof window.tailorCV === 'function') {
          window.tailorCV(jobData.id);
          return true;
        }
        if (typeof window.handleCVTailoring === 'function') {
          window.handleCVTailoring(jobData.id, jobData);
          return true;
        }
      } catch (error) {
        console.error('Tailor CV integration error:', error);
      }
      return false;
    }

    function updateFilterStacks() {
      var view = getActiveFilterView();
      $root.find('.sffc-filter-stack').each(function () {
        var $stack = $(this);
        var stackView = $stack.data('filter-view');
        $stack.toggleClass('is-active', !!view && stackView === view);
      });
    }

    function applyFilters() {
      // Use client-side filtering by default to avoid breaking existing functionality
      applyClientSideFilters();
    }
    
    // Enhanced filtering with AJAX support (can be enabled when needed)
    function applyFiltersWithAjax() {
      var activeTab = getActiveView();
      if (!activeTab) return;
      
      var filters = state.filters[activeTab] || {};
      var searchQuery = state.searchQuery;
      
      // Check if AJAX endpoint is available
      if (!window.sffcNewsDashboard || !window.sffcNewsDashboard.ajaxUrl) {
        applyClientSideFilters();
        return;
      }
      
      // Show loading state
      var $tab = $root.find('.sffc-feed-tab[data-tab="' + activeTab + '"]');
      $tab.addClass('is-loading');
      
      // Make AJAX request for filtered content
      $.ajax({
        url: window.sffcNewsDashboard.ajaxUrl,
        type: 'POST',
        data: {
          action: 'sffc_dashboard_filter',
          nonce: window.sffcNewsDashboard.nonce,
          tab: activeTab,
          filters: filters,
          search: searchQuery,
          page: 1
        },
        success: function(response) {
          $tab.removeClass('is-loading');
          
          if (response.success && response.data.results) {
            // Clear existing cards
            var $container = $tab.find('.sffc-feed-list, .sffc-jobs-grid').first();
            if (!$container.length) {
              $container = $tab;
            }
            
            if (response.data.results.length > 0) {
              // Build new cards HTML
              var cardsHtml = '';
              response.data.results.forEach(function(item) {
                cardsHtml += buildCardHtml(item, activeTab);
              });
              
              $container.html(cardsHtml);
              $tab.removeClass('is-empty');
              
              // Update count display
              updateTabCount(activeTab, response.data.total);
            } else {
              // Show empty state
              $container.html('<div class="sffc-empty-state">' + 
                             '<p>No results found for your filters.</p>' +
                             '<button class="sffc-clear-filters">Clear Filters</button>' +
                             '</div>');
              $tab.addClass('is-empty');
            }
            
            // Rebuild search index for new content
            rebuildSearchIndex();
          }
        },
        error: function() {
          $tab.removeClass('is-loading');
          console.error('Filter request failed');
          // Fall back to client-side filtering
          applyClientSideFilters();
        }
      });
    }
    
    // Fallback client-side filtering (existing logic)
    function applyClientSideFilters() {
      var tabs = ['insights', 'jobs', 'saved', 'research', 'alerts'];
      var query = state.searchQuery;

      tabs.forEach(function (view) {
        var $tab = $root.find('.sffc-feed-tab[data-tab="' + view + '"]');
        if (!$tab.length) {
          return;
        }

        var filters = state.filters[view] || null;
        var matchCount = 0;

        $tab.find('.sffc-feed-card').each(function () {
          var $card = $(this);
          var matches = true;

          if (filters) {
            var tokens = ($card.data('keywords') || '').toString().split(' ').filter(Boolean);
            Object.keys(filters).forEach(function (group) {
              if (!matches) {
                return;
              }
              var slug = filters[group];
              if (slug && tokens.indexOf(slug) === -1) {
                matches = false;
              }
            });
          }

          if (matches && query) {
            var searchIndex = ($card.data('searchIndex') || '').toString();
            if (searchIndex.indexOf(query) === -1) {
              matches = false;
            }
          }

          $card.toggleClass('is-filter-hidden', !matches);
          if (matches) {
            matchCount += 1;
          }
        });

        $tab.toggleClass('is-empty', matchCount === 0);
        if (typeof applyLoadLimitForView === 'function') {
          applyLoadLimitForView(view);
        }
      });
    }
    
    // Build card HTML from AJAX response
    function buildCardHtml(item, tabType) {
      var isLoggedIn = window.sffcNewsDashboard ? window.sffcNewsDashboard.isLoggedIn : false;
      var cardClass = 'sffc-feed-card';
      var keywords = item.keywords || '';
      var category = item.category || '';
      
      if (tabType === 'jobs') {
        cardClass = 'sffc-job-card';
      }
      
      var html = '<article class="' + cardClass + '" ' +
                 'data-post-id="' + item.id + '" ' +
                 'data-keywords="' + keywords + '" ' +
                 'data-category="' + category + '" ' +
                 'data-search-index="' + (item.title + ' ' + item.excerpt).toLowerCase() + '">';
      
      html += '<div class="sffc-card-inner">';
      
      if (category) {
        html += '<span class="sffc-job-badge">' + category + '</span>';
      }
      
      html += '<h3>' + item.title + '</h3>';
      html += '<p>' + item.excerpt + '</p>';
      
      if (item.date) {
        html += '<time>' + item.date + '</time>';
      }
      
      if (!isLoggedIn) {
        html += '<div class="sffc-avatar-blur"></div>';
      }
      
      html += '<div class="sffc-card-actions">';
      html += '<button class="sffc-save-btn" data-post-id="' + item.id + '">';
      html += '<span class="sffc-save-icon">⭐</span>';
      html += '</button>';
      html += '</div>';
      
      html += '</div>';
      html += '</article>';
      
      return html;
    }
    
    // Update tab count display
    function updateTabCount(tab, count) {
      var $tabBtn = $root.find('.sffc-tab-btn[data-tab="' + tab + '"]');
      var $badge = $tabBtn.find('.sffc-tab-count');
      
      if (!$badge.length) {
        $badge = $('<span class="sffc-tab-count"></span>');
        $tabBtn.append($badge);
      }
      
      $badge.text(count);
    }

    function hydrateSavedState() {
      var $list = $root.find('[data-role="saved-list"]');
      var initial = {};
      if ($list.length) {
        $list.find('.sffc-feed-card').each(function () {
          var postId = $(this).data('postId');
          if (postId) {
            initial[postId] = $(this).prop('outerHTML');
          }
        });
      }
      state.saved = initial;
    }

    function renderSaved() {
      var $tab = $root.find('[data-tab="saved"]');
      var $empty = $tab.find('[data-role="saved-empty"]');
      var $list = $tab.find('[data-role="saved-list"]');
      if (!$tab.length || !$empty.length || !$list.length) {
        return;
      }

      var keys = Object.keys(state.saved);
      if (!keys.length) {
        $list.empty();
        $empty.show();
        rebuildSearchIndex();
        applyLoadLimitForView('saved');
        updateLoadMoreVisibility('saved', 0, 0);
        return;
      }

      $empty.hide();
      $list.empty();
      keys.forEach(function (key) {
        if (!state.saved[key]) {
          return;
        }
        var $markup = $(state.saved[key]);
        setButtonSavedState($markup.find('.sffc-save-btn'), true);
        $list.append($markup);
      });

      rebuildSearchIndex();
      applyLoadLimitForView('saved');
      updateLoadMoreVisibility('saved');
    }

    function updateSavedState(cardId, shouldSave, $card) {
      if (shouldSave) {
        var $clone = $card.clone();
        setButtonSavedState($clone.find('.sffc-save-btn'), true);
        state.saved[cardId] = $('<div/>').append($clone).html();
      } else {
        delete state.saved[cardId];
      }
      renderSaved();
    }

    function setSearchQuery(value) {
      var query = (value || '').toString().toLowerCase().trim();
      state.searchQuery = query;
      toggleClearButton();
      applyFilters();
      renderSuggestions(value);
      
      // Perform backend search if query is not empty
      if (query.length >= 2) {
        performBackendSearch(query);
      }
    }

    function renderSuggestions(value) {
      if (!$searchSuggestions.length) {
        return;
      }
      var query = (value || '').toString().toLowerCase().trim();
      $searchSuggestions.empty().removeClass('is-visible');
      if (!query || query.length < 2) {
        return;
      }

      var matches = [];
      for (var i = 0; i < searchEntries.length; i += 1) {
        if (searchEntries[i].searchIndex.indexOf(query) !== -1) {
          matches.push(searchEntries[i]);
          if (matches.length === 5) {
            break;
          }
        }
      }

      if (!matches.length) {
        return;
      }

      matches.forEach(function (entry) {
        var $item = $('<button/>', {
          type: 'button',
          class: 'sffc-search-suggestion',
          'data-suggestion-id': entry.id,
          'data-suggestion-tab': entry.tab,
          'data-suggestion-query': value
        });
        $item.append($('<span/>', { class: 'sffc-search-suggestion__title', text: entry.title }));
        $item.append($('<span/>', { class: 'sffc-search-suggestion__meta', text: formatSuggestionMeta(entry) }));
        $searchSuggestions.append($item);
      });

      $searchSuggestions.addClass('is-visible');
    }

    function closeSearchSuggestions() {
      if ($searchSuggestions.length) {
        $searchSuggestions.removeClass('is-visible');
      }
    }

    function toggleClearButton() {
      if (!$searchClear.length) {
        return;
      }
      $searchClear.toggleClass('is-visible', state.searchQuery.length > 0);
    }

    function rebuildSearchIndex() {
      searchEntries = [];
      $root.find('.sffc-feed-tab').each(function () {
        var tab = $(this).data('tab');
        $(this)
          .find('.sffc-feed-card')
          .each(function () {
            var $card = $(this);
            var entry = {
              id: $card.data('postId'),
              tab: tab,
              type: $card.data('type'),
              searchIndex: ($card.data('searchIndex') || '').toString(),
              title: $.trim($card.find('h3').text())
            };
            if (entry.id) {
              searchEntries.push(entry);
            }
          });
      });
    }

    function highlightCard($card) {
      if (!$card || !$card.length) {
        return;
      }
      $card.addClass('is-highlighted');
      setTimeout(function () {
        $card.removeClass('is-highlighted');
      }, 2000);
    }

    function formatSuggestionMeta(entry) {
      var typeLabels = {
        news: 'Insight',
        deal: 'Deal',
        job: 'Job',
        research: 'Research',
        message: 'Message'
      };
      var label = typeLabels[entry.type] || entry.type || entry.tab || '';
      if (!label) {
        label = 'Result';
      }
      return label;
    }

    function syncSavedState(postId, shouldSave) {
      if (!ajaxUrl || !nonce || !isLoggedIn) {
        return $.Deferred().resolve().promise();
      }
      return $.ajax({
        method: 'POST',
        url: ajaxUrl,
        dataType: 'json',
        data: {
          action: 'sffc_toggle_saved_item',
          nonce: nonce,
          post_id: postId,
          save: shouldSave ? 1 : 0
        }
      });
    }

    function setButtonsForPost(postId, isSaved) {
      $root.find('.sffc-save-btn[data-post-id="' + postId + '"]').each(function () {
        setButtonSavedState($(this), isSaved);
      });
    }

    function setButtonSavedState($btn, isSaved) {
      if (!$btn || !$btn.length) {
        return;
      }
      $btn.toggleClass('is-saved', !!isSaved);
      var label = isSaved ? getString('saved_label', 'Saved') : getString('save_label', 'Save');
      $btn.find('span').text(label);
    }

    function getString(key, fallback) {
      if (strings && strings[key]) {
        return strings[key];
      }
      return fallback || '';
    }

    function extractErrorMessage(response) {
      if (response && response.responseJSON && response.responseJSON.data && response.responseJSON.data.message) {
        return response.responseJSON.data.message;
      }
      return '';
    }

    function applyLoadLimitForView(view) {
      var $tab = $root.find('.sffc-feed-tab[data-tab="' + view + '"]');
      if (!$tab.length) {
        return;
      }

      var limit = state.loadLimit[view] || state.loadStep;
      var shown = 0;
      var available = 0;

      $tab.find('.sffc-feed-card').each(function () {
        var $card = $(this);
        if ($card.hasClass('is-filter-hidden')) {
          $card.addClass('is-hidden');
          return;
        }
        available += 1;
        if (shown < limit) {
          $card.removeClass('is-hidden is-limited');
          shown += 1;
        } else {
          $card.addClass('is-hidden is-limited');
        }
      });

      updateLoadMoreVisibility(view, shown, available);
    }

    function updateLoadMoreVisibility(view, shown, available) {
      var $tab = $root.find('.sffc-feed-tab[data-tab="' + view + '"]');
      var $button = $tab.find('[data-role="load-more"][data-tab="' + view + '"]');
      if (!$button.length) {
        return;
      }
      if (typeof shown === 'undefined' || typeof available === 'undefined') {
        shown = $tab.find('.sffc-feed-card:not(.is-hidden)').length;
        available = $tab.find('.sffc-feed-card:not(.is-filter-hidden)').length;
      }
      var hasMore = available > shown;
      $button.toggleClass('is-visible', hasMore);
    }

    function highlightDonutSegment(tab) {
      var $card = $('.sffc-donut-card');
      if (!$card.length) {
        return;
      }
      var $segments = $card.find('.sffc-donut-segment');
      var $target = $segments.filter('[data-tab="' + tab + '"]');
      if (!$target.length) {
        $target = $segments.first();
      }
      $segments.removeClass('is-active');
      $target.addClass('is-active');
      $card.find('[data-role="donut-value"]').text($target.data('value'));
      $card.find('[data-role="donut-label"]').text($target.data('label'));
    }

    function updateTrendingPanel(tab) {
      var $panel = $root.closest('.sffc-dashboard-container').find('[data-trending-panel]');
      if (!$panel.length) {
        return;
      }
      var targetView = tab === 'jobs' ? 'jobs' : 'insights';
      var $lists = $panel.find('[data-trending-view]');
      $lists.removeClass('is-active').attr('aria-hidden', 'true');
      var $targetList = $panel.find('[data-trending-view="' + targetView + '"]');
      if (!$targetList.length) {
        $targetList = $panel.find('[data-trending-view="insights"]').first();
        targetView = 'insights';
      }
      $targetList.addClass('is-active').attr('aria-hidden', 'false');
      var $heading = $panel.find('[data-trending-heading]');
      if ($heading.length) {
        var label = targetView === 'jobs'
          ? getString('trending_jobs_title', 'Trending Roles')
          : getString('trending_insights_title', 'Trending Today');
        $heading.text(label);
      }
    }

    function openPlanModal() {
      if (!$planModal.length) {
        return;
      }
      $planModal.addClass('is-open').attr('aria-hidden', 'false');
      $('body').addClass('sffc-modal-open');
      $planModal.find('[data-plan-card]').removeClass('is-active');
      if ($planCheckout.length) {
        $planCheckout.attr('hidden', 'hidden');
      }
      if ($planForms.length) {
        $planForms.attr('hidden', 'hidden');
      }
      if ($planExternal.length) {
        $planExternal.attr('hidden', 'hidden');
        if ($planExternalLink.length) {
          $planExternalLink.attr('href', '#');
        }
      }
      if ($planMessage.length) {
        $planMessage.text(strings.plan_prompt || 'Select a plan to continue.');
      }
    }

    function closePlanModal() {
      if (!$planModal.length || !$planModal.hasClass('is-open')) {
        return;
      }
      $planModal.removeClass('is-open').attr('aria-hidden', 'true');
      $('body').removeClass('sffc-modal-open');
      if ($planForms.length) {
        $planForms.attr('hidden', 'hidden');
      }
      if ($planExternal.length) {
        $planExternal.attr('hidden', 'hidden');
        if ($planExternalLink.length) {
          $planExternalLink.attr('href', '#');
        }
      }
      if ($planMessage.length) {
        $planMessage.text(strings.plan_prompt || 'Select a plan to continue.');
      }
    }

    // Track current page for each tab
    var tabPages = {
      insights: 1,
      jobs: 1,
      signals: 1,
      saved: 1,
      research: 1
    };

    // Track if we're currently loading
    var isLoadingMore = {};

    $root.on('click', '[data-role="load-more"]', function (event) {
      event.preventDefault();
      var $btn = $(this);
      var tab = $btn.data('tab');

      // Prevent double-clicks
      if (isLoadingMore[tab]) {
        return;
      }

      // Increment page for this tab
      tabPages[tab] = (tabPages[tab] || 1) + 1;

      // Load more from backend
      loadMoreFromBackend(tab, $btn);
    });

    function loadMoreFromBackend(tab, $btn) {
      if (!ajaxUrl) {
        // Fallback to client-side if no AJAX URL
        state.loadLimit[tab] = (state.loadLimit[tab] || state.loadStep) + state.loadStep;
        applyLoadLimitForView(tab);
        return;
      }

      isLoadingMore[tab] = true;
      $btn.addClass('is-loading').text(getString('loading', 'Loading...'));

      var $tab = $root.find('.sffc-feed-tab[data-tab="' + tab + '"]');
      var $feedList = $tab.find('.sffc-feed-list, .sffc-jobs-grid').first();
      if (!$feedList.length) {
        $feedList = $tab;
      }

      // Get current filters for this tab
      var currentFilters = state.filters[tab] || {};

      $.ajax({
        url: ajaxUrl,
        type: 'POST',
        data: {
          action: 'sffc_load_more_posts',
          nonce: nonce,
          tab: tab,
          page: tabPages[tab],
          per_page: state.loadStep,
          filters: currentFilters,
          search: state.searchQuery
        },
        success: function(response) {
          isLoadingMore[tab] = false;
          $btn.removeClass('is-loading');

          if (response.success && response.data.html) {
            // Append new cards to the feed
            var $newCards = $(response.data.html);

            // Insert before the load more button
            if ($btn.length) {
              $btn.before($newCards);
            } else {
              $feedList.append($newCards);
            }

            // Rebuild search index with new content
            rebuildSearchIndex();

            // Update button visibility based on has_more
            if (!response.data.has_more) {
              $btn.removeClass('is-visible').text(getString('no_more', 'No more items'));
              setTimeout(function() {
                $btn.hide();
              }, 2000);
            } else {
              $btn.text(getString('load_more', 'Load more'));
            }

            // Update count display if available
            if (response.data.total) {
              updateTabCount(tab, response.data.total);
            }
          } else {
            $btn.text(getString('load_more', 'Load more'));
            // No more results
            if (response.data && response.data.count === 0) {
              $btn.removeClass('is-visible');
            }
          }
        },
        error: function() {
          isLoadingMore[tab] = false;
          $btn.removeClass('is-loading').text(getString('load_more', 'Load more'));
          console.error('Failed to load more posts');

          // Fallback to client-side
          state.loadLimit[tab] = (state.loadLimit[tab] || state.loadStep) + state.loadStep;
          applyLoadLimitForView(tab);
        }
      });
    }

    $(document).on('click' + docNamespace, function (event) {
      if ($userMenu.length && !$userMenu.is(event.target) && $userMenu.has(event.target).length === 0) {
        toggleUserMenu(false);
      }
      if ($dashboardSearch.length && !$dashboardSearch.is(event.target) && $dashboardSearch.has(event.target).length === 0) {
        closeSearchSuggestions();
      }
      if ($planModal.length && $planModal.hasClass('is-open') && $planModal.is(event.target)) {
        closePlanModal();
      }
    });

    $(window).on('unload' + docNamespace, function () {
      $(document).off('click' + docNamespace);
      $(window).off('unload' + docNamespace);
    });

    function toggleUserMenu(open) {
      console.log('toggleUserMenu called with:', open); // Debug log
      if (!$userMenu.length) {
        console.log('No user menu found'); // Debug log
        return;
      }
      $userMenu.toggleClass('is-open', !!open);
      console.log('User menu classes:', $userMenu.attr('class')); // Debug log
      var $toggle = $userMenu.find('[data-role="user-toggle"]');
      if ($toggle.length) {
        $toggle.attr('aria-expanded', open ? 'true' : 'false');
      }
    }

    function fetchAnalytics() {
      var $panel = $root.find('.sffc-analytics-card');
      if (!$panel.length || !nonce) {
        return;
      }
      if (!ajaxUrl) {
        return;
      }
      $panel.addClass('is-loading');
      $.ajax({
        method: 'POST',
        url: ajaxUrl,
        data: {
          action: 'sffc_fetch_newsroom_analytics',
          nonce: nonce,
          post_ids: postIds
        }
      })
        .done(function (response) {
          if (response && response.success && response.data) {
            updateAnalytics(response.data);
          }
        })
        .always(function () {
          $panel.removeClass('is-loading');
        });
    }

    function updateAnalytics(data) {
      var $panel = $root.find('.sffc-analytics-card');
      if (!$panel.length || !data) {
        return;
      }
      $panel.attr('data-source', data.source || 'templates');
      $panel.find('[data-role="summary"]').text(data.summary || '');
      if (data.timestamp) {
        $panel
          .find('[data-role="timestamp"]')
          .text(formatRelativeTime(data.timestamp))
          .attr('datetime', new Date(data.timestamp * 1000).toISOString());
      }
    }

    function formatRelativeTime(timestamp) {
      var now = Math.floor(Date.now() / 1000);
      var diff = Math.max(1, now - parseInt(timestamp, 10));
      if (diff < 60) {
        return diff + 's ago';
      }
      if (diff < 3600) {
        return Math.round(diff / 60) + 'm ago';
      }
      if (diff < 86400) {
        return Math.round(diff / 3600) + 'h ago';
      }
      return Math.round(diff / 86400) + 'd ago';
    }

    updateFilterStacks();
    applyFilters();
    setTimeout(fetchAnalytics, 600);

    function toggleFilters(forceOpen) {
      if (!$filterColumn.length) {
        return;
      }
      var shouldOpen = typeof forceOpen === 'boolean' ? forceOpen : !$root.hasClass('filters-open');
      $root.toggleClass('filters-open', shouldOpen);
      $filterColumn.toggleClass('is-open', shouldOpen);
      $filterOverlay.toggleClass('is-visible', shouldOpen);
      $body.toggleClass('sffc-filters-open', shouldOpen);
    }
    
    // Backend search functionality
    var searchTimeout = null;
    var searchXHR = null;
    var originalCards = {}; // Store original cards before search

    function performBackendSearch(query) {
      // Clear existing timeout
      if (searchTimeout) {
        clearTimeout(searchTimeout);
      }

      // Cancel previous request if still pending
      if (searchXHR && searchXHR.readyState !== 4) {
        searchXHR.abort();
      }

      // Debounce search requests
      searchTimeout = setTimeout(function() {
        // Show loading state on all tabs
        var tabs = ['insights', 'jobs', 'signals', 'research'];

        tabs.forEach(function(tabName) {
          var $tab = $root.find('.sffc-feed-tab[data-tab="' + tabName + '"]');
          var $feedList = $tab.find('.sffc-feed-list, .sffc-jobs-grid').first();
          if (!$feedList.length) $feedList = $tab;

          // Store original cards if not already stored
          if (!originalCards[tabName]) {
            originalCards[tabName] = $feedList.find('.sffc-feed-card').detach();
          }

          // Add loading indicator
          $feedList.find('.sffc-search-loading').remove();
          $feedList.prepend('<div class="sffc-search-loading" style="text-align:center;padding:20px;color:#666;">Searching...</div>');
        });

        // Perform AJAX search
        searchXHR = $.ajax({
          url: ajaxUrl || (window.sffcNewsDashboard && window.sffcNewsDashboard.ajax_url),
          type: 'POST',
          data: {
            action: 'sffc_dashboard_search',
            query: query,
            nonce: nonce,
            per_page: 30,
            paged: 1
          },
          success: function(response) {
            $('.sffc-search-loading').remove();

            if (response.success && response.data.results && response.data.results.length > 0) {
              displaySearchResults(response.data.results, query);
            } else {
              displayNoResults(query);
            }
          },
          error: function() {
            $('.sffc-search-loading').remove();
            displaySearchError();
          }
        });
      }, 300); // 300ms debounce delay
    }
    
    function displaySearchResults(results, query) {
      // Group results by type for different tabs
      var groupedResults = {
        insights: [],
        jobs: [],
        research: [],
        signals: []
      };

      results.forEach(function(result) {
        var item = result.data || result;
        var type = item.type || 'news';

        if (type === 'job') {
          groupedResults.jobs.push(result);
        } else if (type === 'deal') {
          groupedResults.signals.push(result);
          groupedResults.insights.push(result);
        } else {
          groupedResults.insights.push(result);
          if (item.category && item.category.toLowerCase().indexOf('research') !== -1) {
            groupedResults.research.push(result);
          }
        }
      });

      // Display results in each tab
      ['insights', 'jobs', 'signals', 'research'].forEach(function(tabName) {
        var $tab = $root.find('.sffc-feed-tab[data-tab="' + tabName + '"]');
        var $feedList = $tab.find('.sffc-feed-list, .sffc-jobs-grid').first();
        if (!$feedList.length) $feedList = $tab;

        // Clear loading and existing search markers
        $feedList.find('.sffc-search-loading, .sffc-search-results-marker, .sffc-no-results').remove();

        var tabResults = groupedResults[tabName] || [];

        // Add search results marker
        $feedList.prepend(
          '<div class="sffc-search-results-marker" style="background:#f5f5f5;padding:12px 16px;border-radius:8px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;">' +
          '<span><strong>' + tabResults.length + ' results</strong> for "' + escapeHtml(query) + '"</span> ' +
          '<button type="button" class="sffc-clear-search" style="background:#0d353e;color:#fff;border:none;padding:6px 12px;border-radius:4px;cursor:pointer;">Clear Search</button>' +
          '</div>'
        );

        // Add result cards
        if (tabResults.length > 0) {
          tabResults.forEach(function(result) {
            var html = result.html;
            if (html) {
              var $card = $(html);
              $card.addClass('sffc-search-result');
              $feedList.append($card);
            }
          });
        } else {
          $feedList.append(
            '<div class="sffc-no-results" style="text-align:center;padding:40px 20px;color:#666;">' +
            '<p>No ' + tabName + ' found for "' + escapeHtml(query) + '"</p>' +
            '</div>'
          );
        }

        // Hide load more button during search
        $tab.find('[data-role="load-more"]').removeClass('is-visible');
      });

      // Rebuild search index with new results
      rebuildSearchIndex();
    }
    
    function displayNoResults(query) {
      // Already handled in displaySearchResults, but keep for direct calls
      var tabs = ['insights', 'jobs', 'signals', 'research'];

      tabs.forEach(function(tabName) {
        var $tab = $root.find('.sffc-feed-tab[data-tab="' + tabName + '"]');
        var $feedList = $tab.find('.sffc-feed-list, .sffc-jobs-grid').first();
        if (!$feedList.length) $feedList = $tab;

        $feedList.find('.sffc-search-loading').remove();
        if (!$feedList.find('.sffc-no-results').length) {
          $feedList.append(
            '<div class="sffc-no-results" style="text-align:center;padding:40px 20px;color:#666;">' +
            '<p>No results found for "' + escapeHtml(query) + '"</p>' +
            '<p style="font-size:14px;">Try adjusting your search terms or browse the categories.</p>' +
            '<button type="button" class="sffc-clear-search" style="background:#0d353e;color:#fff;border:none;padding:8px 16px;border-radius:4px;cursor:pointer;margin-top:12px;">Clear Search</button>' +
            '</div>'
          );
        }
      });
    }

    function displaySearchError() {
      var tabs = ['insights', 'jobs', 'signals', 'research'];

      tabs.forEach(function(tabName) {
        var $tab = $root.find('.sffc-feed-tab[data-tab="' + tabName + '"]');
        var $feedList = $tab.find('.sffc-feed-list, .sffc-jobs-grid').first();
        if (!$feedList.length) $feedList = $tab;

        $feedList.find('.sffc-search-loading, .sffc-search-error').remove();
        $feedList.append(
          '<div class="sffc-search-error" style="text-align:center;padding:40px 20px;color:#c00;">' +
          '<p>An error occurred while searching. Please try again.</p>' +
          '<button type="button" class="sffc-clear-search" style="background:#0d353e;color:#fff;border:none;padding:8px 16px;border-radius:4px;cursor:pointer;margin-top:12px;">Clear Search</button>' +
          '</div>'
        );
      });
    }

    function clearSearchResults() {
      // Restore original cards for each tab
      ['insights', 'jobs', 'signals', 'research'].forEach(function(tabName) {
        var $tab = $root.find('.sffc-feed-tab[data-tab="' + tabName + '"]');
        var $feedList = $tab.find('.sffc-feed-list, .sffc-jobs-grid').first();
        if (!$feedList.length) $feedList = $tab;

        // Remove search markers and results
        $feedList.find('.sffc-search-results-marker, .sffc-no-results, .sffc-search-error, .sffc-search-result').remove();

        // Restore original cards if we saved them
        if (originalCards[tabName] && originalCards[tabName].length) {
          $feedList.prepend(originalCards[tabName]);
          originalCards[tabName] = null;
        }

        // Show load more button again
        $tab.find('[data-role="load-more"]').addClass('is-visible');
      });

      // Clear search input
      if ($searchInput.length) {
        $searchInput.val('');
      }
      state.searchQuery = '';
      toggleClearButton();

      // Rebuild search index
      rebuildSearchIndex();

      // Reapply filters
      applyFilters();
    }
    
    function escapeHtml(text) {
      var map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
      };
      return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    // Handle clear search button
    $(document).on('click', '.sffc-clear-search', function() {
      clearSearchResults();
    });
  }

  $(document).on('click', '[data-article-prompt]', function () {
    var prompt = $(this).data('articlePrompt');
    triggerAskSennaPrompt(prompt);
  });

  $(function () {
    updateDashboardDate();
    $('.sffc-feed-shell').each(function () {
      initNewsDashboard($(this));
    });
    ['insights', 'jobs', 'saved', 'research', 'alerts'].forEach(function (view) {
      applyLoadLimitForView(view);
    });
    highlightDonutSegment('insights');

    // Mini match donut segment hover tooltips
    $(document).on('mouseenter', '.sffc-segment', function() {
      var label = $(this).data('label');
      if (label) {
        $(this).closest('.sffc-mini-match').find('.sffc-segment-tooltip').text(label);
      }
    });
  });

  function triggerAskSennaPrompt(prompt) {
    if (!prompt) {
      return;
    }
    var $widget = $('[data-ask-senna]');
    if (!$widget.length) {
      return;
    }
    if (!$widget.hasClass('is-open')) {
      $widget.find('[data-role="toggle"]').trigger('click');
    }
    setTimeout(function () {
      var $input = $widget.find('[data-role="input"]');
      var $form = $widget.find('[data-role="form"]');
      if (!$input.length || !$form.length) {
        return;
      }
      $input.val(prompt);
      $form.trigger('submit');
    }, 180);
  }

  function updateDashboardDate() {
    var el = document.querySelector('[data-role="dashboard-date"]');
    if (!el) {
      return;
    }
    var now = new Date();
    var formatted = now.toLocaleDateString(undefined, {
      weekday: 'short',
      day: 'numeric',
      month: 'short',
      year: 'numeric'
    });
    el.textContent = formatted;
  }
})(jQuery);


// ===== MOBILE HEADER OPTIMIZATIONS =====

// Mobile Header Optimizations
(function() {
    'use strict';
    
    // Detect mobile device
    var isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    var isTouch = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
    
    if (isMobile || isTouch) {
        document.addEventListener('DOMContentLoaded', function() {
            // Optimize search input for mobile
            var searchInput = document.getElementById('sffc-dashboard-search-input');
            if (searchInput) {
                // Prevent zoom on focus (iOS)
                searchInput.addEventListener('focus', function() {
                    if (window.innerWidth < 768) {
                        document.querySelector('meta[name="viewport"]').setAttribute('content', 
                            'width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0');
                    }
                });
                
                searchInput.addEventListener('blur', function() {
                    document.querySelector('meta[name="viewport"]').setAttribute('content', 
                        'width=device-width, initial-scale=1');
                });
                
                // Improve virtual keyboard behavior
                searchInput.addEventListener('touchstart', function(e) {
                    e.stopPropagation();
                });
            }
            
            // Optimize dropdown menus for touch
            var userToggle = document.querySelector('[data-role="user-toggle"]');
            if (userToggle) {
                var touchStartY = 0;
                
                userToggle.addEventListener('touchstart', function(e) {
                    touchStartY = e.touches[0].clientY;
                }, { passive: true });
                
                userToggle.addEventListener('touchend', function(e) {
                    var touchEndY = e.changedTouches[0].clientY;
                    // Only trigger if it's a tap, not a swipe
                    if (Math.abs(touchEndY - touchStartY) < 10) {
                        e.preventDefault();
                        e.target.click();
                    }
                });
            }
            
            // Close dropdowns on outside touch
            document.addEventListener('touchstart', function(e) {
                var userMenu = document.querySelector('.sffc-user-menu.is-open');
                if (userMenu && !userMenu.contains(e.target)) {
                    userMenu.classList.remove('is-open');
                }
                
                var searchSuggestions = document.querySelector('.sffc-search-suggestions.is-visible');
                if (searchSuggestions && !searchSuggestions.parentElement.contains(e.target)) {
                    searchSuggestions.classList.remove('is-visible');
                }
            });
            
            // Optimize scroll performance
            var header = document.querySelector('.sffc-global-header-bar');
            if (header && window.innerWidth < 768) {
                var lastScrollY = window.scrollY;
                var ticking = false;
                
                function updateHeader() {
                    var currentScrollY = window.scrollY;
                    
                    if (currentScrollY > lastScrollY && currentScrollY > 100) {
                        // Scrolling down - hide header
                        header.style.transform = 'translateY(-100%)';
                    } else {
                        // Scrolling up - show header
                        header.style.transform = 'translateY(0)';
                    }
                    
                    lastScrollY = currentScrollY;
                    ticking = false;
                }
                
                window.addEventListener('scroll', function() {
                    if (!ticking) {
                        window.requestAnimationFrame(updateHeader);
                        ticking = true;
                    }
                }, { passive: true });
                
                // Add smooth transition
                header.style.transition = 'transform 0.3s ease';
            }
            
            // Add haptic feedback for iOS
            if (window.webkit && window.webkit.messageHandlers) {
                document.querySelectorAll('button, a, [role="button"]').forEach(function(el) {
                    el.addEventListener('touchstart', function() {
                        if (navigator.vibrate) {
                            navigator.vibrate(10);
                        }
                    });
                });
            }
            
            // Improve search suggestions for mobile
            var searchSuggestions = document.querySelector('.sffc-search-suggestions');
            if (searchSuggestions) {
                searchSuggestions.addEventListener('touchmove', function(e) {
                    // Allow scrolling within suggestions
                    e.stopPropagation();
                }, { passive: true });
            }
        });
    }
})();


// ===== PROFESSIONAL PROFILE MODULE =====
(function($) {
    'use strict';

    var ProfileManager = {
        modal: null,
        isLoading: false,
        ajaxUrl: null,
        nonce: null,

        init: function() {
            this.cacheElements();
            this.bindEvents();
            this.initSkillsInput();
        },

        cacheElements: function() {
            this.modal = $('.sffc-profile-modal');
            this.editBtn = $('.sffc-profile-edit-btn');
            this.shareBtn = $('.sffc-profile-share-btn');
            this.visibilityOptions = $('.sffc-visibility-option');
            this.skillsContainer = $('.sffc-skills-grid');

            // Get AJAX settings
            if (window.sffcNewsDashboard) {
                this.ajaxUrl = window.sffcNewsDashboard.ajax_url;
                this.nonce = window.sffcNewsDashboard.nonce;
            } else if (window.sffcProfileSettings) {
                this.ajaxUrl = window.sffcProfileSettings.ajax_url;
                this.nonce = window.sffcProfileSettings.nonce;
            }
        },

        bindEvents: function() {
            var self = this;

            // LinkedIn-style edit buttons (main edit and section edits)
            $(document).on('click', '.sffc-linkedin-edit, .sffc-linkedin-add', function(e) {
                e.preventDefault();
                var section = $(this).data('section') || $(this).data('action');
                if (section === 'edit-profile') {
                    self.openModal('header');
                } else if (section) {
                    self.openModal(section);
                }
            });

            // Close modal
            $(document).on('click', '.sffc-modal-close, .sffc-profile-modal__close', function(e) {
                e.preventDefault();
                self.closeModal();
            });

            // Close modal on overlay click
            $(document).on('click', '.sffc-profile-modal__overlay', function(e) {
                e.preventDefault();
                self.closeModal();
            });

            // Close modal on Escape key
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    self.closeModal();
                }
            });

            // Save profile form
            $(document).on('submit', '.sffc-profile-form', function(e) {
                e.preventDefault();
                self.saveProfile($(this));
            });

            // Save button click
            $(document).on('click', '.sffc-profile-modal .sffc-btn--primary', function(e) {
                e.preventDefault();
                var $form = $(this).closest('.sffc-profile-modal').find('.sffc-profile-form');
                if ($form.length) {
                    self.saveProfile($form);
                }
            });

            // Cancel button
            $(document).on('click', '.sffc-profile-modal .sffc-btn--secondary', function(e) {
                e.preventDefault();
                self.closeModal();
            });

            // Add skill button in form
            $(document).on('click', '.sffc-add-skill-btn', function(e) {
                e.preventDefault();
                self.addSkillInput();
            });

            // Remove skill button
            $(document).on('click', '.sffc-remove-skill', function(e) {
                e.preventDefault();
                $(this).closest('.sffc-skill-input-row').remove();
            });

            // Add experience button
            $(document).on('click', '.sffc-add-experience-btn', function(e) {
                e.preventDefault();
                self.addExperienceRow();
            });

            // Remove experience button
            $(document).on('click', '.sffc-remove-experience', function(e) {
                e.preventDefault();
                $(this).closest('.sffc-experience-input-row').remove();
            });

            // Add education button
            $(document).on('click', '.sffc-add-education-btn', function(e) {
                e.preventDefault();
                self.addEducationRow();
            });

            // Remove education button
            $(document).on('click', '.sffc-remove-education', function(e) {
                e.preventDefault();
                $(this).closest('.sffc-education-input-row').remove();
            });
        },

        openModal: function(section) {
            var self = this;
            section = section || 'header';

            // Generate form content based on section
            var formHtml = this.generateFormContent(section);

            // Update modal content
            var $modalBody = $('[data-modal-content]');
            if ($modalBody.length) {
                $modalBody.html(formHtml);
            }

            // Update modal title
            var titles = {
                'header': 'Edit Profile',
                'about': 'Edit About',
                'experience': 'Add Experience',
                'education': 'Add Education',
                'skills': 'Edit Skills',
                'preferences': 'Edit Job Preferences'
            };
            $('.sffc-profile-modal__header h3').text(titles[section] || 'Edit Profile');

            // Show modal
            this.modal = $('.sffc-profile-modal');
            this.modal.attr('aria-hidden', 'false').css('display', 'flex');
            $('body').addClass('sffc-modal-open');

            // Focus first input
            setTimeout(function() {
                self.modal.find('input, select, textarea').first().focus();
            }, 100);
        },

        closeModal: function() {
            var $modal = $('.sffc-profile-modal');
            $modal.attr('aria-hidden', 'true').css('display', 'none');
            $('body').removeClass('sffc-modal-open');
        },

        generateFormContent: function(section) {
            var html = '<form class="sffc-profile-form" data-section="' + section + '">';

            switch (section) {
                case 'header':
                    html += this.generateHeaderForm();
                    break;
                case 'about':
                    html += this.generateAboutForm();
                    break;
                case 'experience':
                    html += this.generateExperienceForm();
                    break;
                case 'education':
                    html += this.generateEducationForm();
                    break;
                case 'skills':
                    html += this.generateSkillsForm();
                    break;
                case 'preferences':
                    html += this.generatePreferencesForm();
                    break;
                case 'match-preferences':
                    html += this.generateMatchPreferencesForm();
                    break;
                case 'career-journey':
                    html += this.generateCareerJourneyForm();
                    break;
                default:
                    html += this.generateHeaderForm();
            }

            html += '<div class="sffc-form-actions">';
            html += '<button type="button" class="sffc-btn sffc-btn--secondary">Cancel</button>';
            html += '<button type="submit" class="sffc-btn sffc-btn--primary">Save Changes</button>';
            html += '</div>';
            html += '</form>';

            return html;
        },

        generateHeaderForm: function() {
            var displayName = $('.sffc-linkedin-name').text().trim();
            var nameParts = displayName.split(' ');
            var firstName = nameParts[0] || '';
            var lastName = nameParts.slice(1).join(' ') || '';
            var headline = $('.sffc-linkedin-headline').not('.sffc-linkedin-headline--placeholder').text().trim();
            var location = $('.sffc-linkedin-meta__item').first().text().trim();
            var pronouns = $('.sffc-linkedin-pronouns').text().replace(/[()]/g, '').trim();

            return '<div class="sffc-form-section">' +
                '<div class="sffc-form-row">' +
                    '<div class="sffc-form-group">' +
                        '<label for="sffc_first_name">First Name</label>' +
                        '<input type="text" id="sffc_first_name" name="first_name" value="' + this.escapeHtml(firstName) + '" placeholder="First name">' +
                    '</div>' +
                    '<div class="sffc-form-group">' +
                        '<label for="sffc_last_name">Last Name</label>' +
                        '<input type="text" id="sffc_last_name" name="last_name" value="' + this.escapeHtml(lastName) + '" placeholder="Last name">' +
                    '</div>' +
                '</div>' +
                '<div class="sffc-form-group">' +
                    '<label for="sffc_headline">Professional Headline</label>' +
                    '<input type="text" id="sffc_headline" name="headline" value="' + this.escapeHtml(headline) + '" placeholder="e.g., Senior Associate at Investment Firm">' +
                '</div>' +
                '<div class="sffc-form-row">' +
                    '<div class="sffc-form-group">' +
                        '<label for="sffc_pronouns">Pronouns (optional)</label>' +
                        '<select id="sffc_pronouns" name="pronouns">' +
                            '<option value="">Select pronouns</option>' +
                            '<option value="he/him"' + (pronouns === 'he/him' ? ' selected' : '') + '>He/Him</option>' +
                            '<option value="she/her"' + (pronouns === 'she/her' ? ' selected' : '') + '>She/Her</option>' +
                            '<option value="they/them"' + (pronouns === 'they/them' ? ' selected' : '') + '>They/Them</option>' +
                        '</select>' +
                    '</div>' +
                    '<div class="sffc-form-group">' +
                        '<label for="sffc_location">Location</label>' +
                        '<input type="text" id="sffc_location" name="location" value="' + this.escapeHtml(location) + '" placeholder="e.g., New York, NY">' +
                    '</div>' +
                '</div>' +
            '</div>';
        },

        generateAboutForm: function() {
            var bio = $('.sffc-linkedin-bio').text().trim();

            return '<div class="sffc-form-section">' +
                '<div class="sffc-form-group">' +
                    '<label for="sffc_bio">About</label>' +
                    '<textarea id="sffc_bio" name="bio" rows="6" placeholder="Tell recruiters about your background, experience, and what you\'re looking for...">' + this.escapeHtml(bio) + '</textarea>' +
                    '<p class="sffc-form-hint">Write 2-3 paragraphs about your professional journey, key achievements, and career goals.</p>' +
                '</div>' +
            '</div>';
        },

        generateExperienceForm: function() {
            return '<div class="sffc-form-section">' +
                '<p class="sffc-form-intro">Add your work experience to help recruiters understand your background.</p>' +
                '<div class="sffc-experience-inputs">' +
                    '<div class="sffc-experience-input-row">' +
                        '<div class="sffc-form-group">' +
                            '<label>Job Title</label>' +
                            '<input type="text" name="exp_title[]" placeholder="e.g., Senior Associate">' +
                        '</div>' +
                        '<div class="sffc-form-group">' +
                            '<label>Company</label>' +
                            '<input type="text" name="exp_company[]" placeholder="e.g., Goldman Sachs">' +
                        '</div>' +
                        '<div class="sffc-form-row">' +
                            '<div class="sffc-form-group">' +
                                '<label>Start Date</label>' +
                                '<input type="text" name="exp_start[]" placeholder="e.g., Jan 2020">' +
                            '</div>' +
                            '<div class="sffc-form-group">' +
                                '<label>End Date</label>' +
                                '<input type="text" name="exp_end[]" placeholder="Present or date">' +
                            '</div>' +
                        '</div>' +
                        '<div class="sffc-form-group">' +
                            '<label>Description</label>' +
                            '<textarea name="exp_desc[]" rows="3" placeholder="Describe your responsibilities and achievements..."></textarea>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
                '<button type="button" class="sffc-add-experience-btn">+ Add Another Experience</button>' +
            '</div>';
        },

        generateEducationForm: function() {
            return '<div class="sffc-form-section">' +
                '<p class="sffc-form-intro">Add your educational background.</p>' +
                '<div class="sffc-education-inputs">' +
                    '<div class="sffc-education-input-row">' +
                        '<div class="sffc-form-group">' +
                            '<label>School</label>' +
                            '<input type="text" name="edu_school[]" placeholder="e.g., Harvard Business School">' +
                        '</div>' +
                        '<div class="sffc-form-group">' +
                            '<label>Degree</label>' +
                            '<input type="text" name="edu_degree[]" placeholder="e.g., MBA">' +
                        '</div>' +
                        '<div class="sffc-form-group">' +
                            '<label>Field of Study</label>' +
                            '<input type="text" name="edu_field[]" placeholder="e.g., Finance">' +
                        '</div>' +
                        '<div class="sffc-form-row">' +
                            '<div class="sffc-form-group">' +
                                '<label>Start Date</label>' +
                                '<input type="month" name="edu_start[]">' +
                            '</div>' +
                            '<div class="sffc-form-group">' +
                                '<label>End Date</label>' +
                                '<input type="month" name="edu_end[]">' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
                '<button type="button" class="sffc-add-education-btn">+ Add Another Education</button>' +
            '</div>';
        },

        generateSkillsForm: function() {
            var existingSkills = [];
            $('.sffc-linkedin-skill').each(function() {
                existingSkills.push($(this).text().trim());
            });

            var html = '<div class="sffc-form-section">' +
                '<p class="sffc-form-intro">Add skills to showcase your expertise.</p>' +
                '<div class="sffc-skills-input-container">';

            if (existingSkills.length > 0) {
                existingSkills.forEach(function(skill) {
                    html += '<div class="sffc-skill-input-row">' +
                        '<input type="text" name="skill_name[]" value="' + skill + '" placeholder="Skill name">' +
                        '<select name="skill_level[]">' +
                            '<option value="beginner">Beginner</option>' +
                            '<option value="intermediate" selected>Intermediate</option>' +
                            '<option value="advanced">Advanced</option>' +
                            '<option value="expert">Expert</option>' +
                        '</select>' +
                        '<button type="button" class="sffc-remove-skill" title="Remove">×</button>' +
                    '</div>';
                });
            } else {
                html += '<div class="sffc-skill-input-row">' +
                    '<input type="text" name="skill_name[]" placeholder="e.g., Financial Modeling">' +
                    '<select name="skill_level[]">' +
                        '<option value="beginner">Beginner</option>' +
                        '<option value="intermediate" selected>Intermediate</option>' +
                        '<option value="advanced">Advanced</option>' +
                        '<option value="expert">Expert</option>' +
                    '</select>' +
                    '<button type="button" class="sffc-remove-skill" title="Remove">×</button>' +
                '</div>';
            }

            html += '</div>' +
                '<button type="button" class="sffc-add-skill-btn">+ Add Skill</button>' +
            '</div>';

            return html;
        },

        generatePreferencesForm: function() {
            return '<div class="sffc-form-section">' +
                '<div class="sffc-form-group">' +
                    '<label for="sffc_preferred_roles">Target Roles</label>' +
                    '<input type="text" id="sffc_preferred_roles" name="preferred_roles" placeholder="e.g., Associate, VP, Director (comma separated)">' +
                '</div>' +
                '<div class="sffc-form-group">' +
                    '<label for="sffc_preferred_industries">Preferred Industries</label>' +
                    '<input type="text" id="sffc_preferred_industries" name="preferred_industries" placeholder="e.g., Technology, Healthcare (comma separated)">' +
                '</div>' +
                '<div class="sffc-form-group">' +
                    '<label for="sffc_work_style">Work Style</label>' +
                    '<select id="sffc_work_style" name="work_style">' +
                        '<option value="onsite">On-site</option>' +
                        '<option value="hybrid">Hybrid</option>' +
                        '<option value="remote">Remote</option>' +
                        '<option value="flexible" selected>Flexible</option>' +
                    '</select>' +
                '</div>' +
                '<div class="sffc-form-row">' +
                    '<div class="sffc-form-group">' +
                        '<label for="sffc_salary_min">Minimum Salary ($)</label>' +
                        '<input type="number" id="sffc_salary_min" name="salary_min" placeholder="80000" step="5000">' +
                    '</div>' +
                    '<div class="sffc-form-group">' +
                        '<label for="sffc_salary_max">Maximum Salary ($)</label>' +
                        '<input type="number" id="sffc_salary_max" name="salary_max" placeholder="150000" step="5000">' +
                    '</div>' +
                '</div>' +
            '</div>';
        },

        generateMatchPreferencesForm: function() {
            return '<div class="sffc-form-section">' +
                '<p class="sffc-form-intro">Complete these fields to get accurate job match scores on each role.</p>' +
                '<div class="sffc-form-row">' +
                    '<div class="sffc-form-group">' +
                        '<label for="sffc_preferred_location">Preferred Location</label>' +
                        '<input type="text" id="sffc_preferred_location" name="preferred_location" placeholder="e.g., Paris, London, Frankfurt">' +
                    '</div>' +
                    '<div class="sffc-form-group">' +
                        '<label for="sffc_experience_level">Experience Level</label>' +
                        '<select id="sffc_experience_level" name="experience_level">' +
                            '<option value="">Select level</option>' +
                            '<option value="junior">Junior (0-2 years)</option>' +
                            '<option value="mid">Mid-Level (3-5 years)</option>' +
                            '<option value="senior">Senior (6-10 years)</option>' +
                            '<option value="executive">Executive (10+ years)</option>' +
                        '</select>' +
                    '</div>' +
                '</div>' +
                '<div class="sffc-form-row">' +
                    '<div class="sffc-form-group">' +
                        '<label for="sffc_role_type">Role Type</label>' +
                        '<select id="sffc_role_type" name="role_type">' +
                            '<option value="">Select type</option>' +
                            '<option value="front_office">Front Office</option>' +
                            '<option value="back_office">Back Office</option>' +
                            '<option value="operations">Operations</option>' +
                            '<option value="support">Support Functions</option>' +
                        '</select>' +
                    '</div>' +
                    '<div class="sffc-form-group">' +
                        '<label for="sffc_preferred_next_role">Preferred Next Role</label>' +
                        '<input type="text" id="sffc_preferred_next_role" name="preferred_next_role" placeholder="e.g., VP of Finance, Senior Associate">' +
                    '</div>' +
                '</div>' +
                '<div class="sffc-form-group">' +
                    '<label for="sffc_preferred_industries">Preferred Industries</label>' +
                    '<input type="text" id="sffc_match_preferred_industries" name="preferred_industries" placeholder="e.g., Private Equity, Private Credit, Investment Banking">' +
                    '<span class="sffc-form-hint">Separate multiple industries with commas</span>' +
                '</div>' +
                '<div class="sffc-form-group">' +
                    '<label for="sffc_latest_experience">Latest Experience Summary</label>' +
                    '<textarea id="sffc_latest_experience" name="latest_experience_description" rows="6" placeholder="Paste a detailed description of your most recent role from your CV. Include responsibilities, achievements, and key skills used."></textarea>' +
                    '<span class="sffc-form-hint">This helps us match you with relevant opportunities based on your actual experience.</span>' +
                '</div>' +
            '</div>';
        },

        generateCareerJourneyForm: function() {
            return '<div class="sffc-form-section">' +
                '<p class="sffc-form-intro">Tell us about your career goals so MENA Careers can provide personalized guidance.</p>' +
                '<div class="sffc-form-row">' +
                    '<div class="sffc-form-group">' +
                        '<label for="sffc_career_goal">Career Goal</label>' +
                        '<select id="sffc_career_goal" name="goal">' +
                            '<option value="">Select your goal</option>' +
                            '<option value="transition">Career Transition</option>' +
                            '<option value="advance">Advance in Current Path</option>' +
                            '<option value="explore">Explore Options</option>' +
                            '<option value="pivot">Industry Pivot</option>' +
                        '</select>' +
                    '</div>' +
                    '<div class="sffc-form-group">' +
                        '<label for="sffc_current_situation">Current Situation</label>' +
                        '<select id="sffc_current_situation" name="situation">' +
                            '<option value="">Select your situation</option>' +
                            '<option value="student">Student/Recent Graduate</option>' +
                            '<option value="analyst">Analyst Level</option>' +
                            '<option value="associate">Associate Level</option>' +
                            '<option value="senior">Senior Professional</option>' +
                            '<option value="between">Between Roles</option>' +
                            '<option value="other">Other</option>' +
                        '</select>' +
                    '</div>' +
                '</div>' +
                '<div class="sffc-form-row">' +
                    '<div class="sffc-form-group">' +
                        '<label for="sffc_timeline">Timeline</label>' +
                        '<select id="sffc_timeline" name="timeline">' +
                            '<option value="">When do you want to move?</option>' +
                            '<option value="immediate">Ready Now</option>' +
                            '<option value="3months">Within 3 Months</option>' +
                            '<option value="6months">Within 6 Months</option>' +
                            '<option value="year">Within a Year</option>' +
                        '</select>' +
                    '</div>' +
                    '<div class="sffc-form-group">' +
                        '<label for="sffc_challenge">Biggest Challenge</label>' +
                        '<select id="sffc_challenge" name="challenge">' +
                            '<option value="">What\'s holding you back?</option>' +
                            '<option value="technical">Technical Skills</option>' +
                            '<option value="network">Building Network</option>' +
                            '<option value="experience">Gaining Experience</option>' +
                            '<option value="brand">Personal Branding</option>' +
                            '<option value="clarity">Career Clarity</option>' +
                            '<option value="interview">Interview Prep</option>' +
                        '</select>' +
                    '</div>' +
                '</div>' +
            '</div>';
        },

        addExperienceRow: function() {
            var $container = $('.sffc-experience-inputs');
            var html = '<div class="sffc-experience-input-row" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid rgba(13,53,62,0.1);">' +
                '<button type="button" class="sffc-remove-experience" style="float: right; background: none; border: none; color: #ef4444; cursor: pointer;">Remove</button>' +
                '<div class="sffc-form-group">' +
                    '<label>Job Title</label>' +
                    '<input type="text" name="exp_title[]" placeholder="e.g., Senior Associate">' +
                '</div>' +
                '<div class="sffc-form-group">' +
                    '<label>Company</label>' +
                    '<input type="text" name="exp_company[]" placeholder="e.g., Goldman Sachs">' +
                '</div>' +
                '<div class="sffc-form-row">' +
                    '<div class="sffc-form-group">' +
                        '<label>Start Date</label>' +
                        '<input type="text" name="exp_start[]" placeholder="e.g., Jan 2020">' +
                    '</div>' +
                    '<div class="sffc-form-group">' +
                        '<label>End Date</label>' +
                        '<input type="text" name="exp_end[]" placeholder="Present or date">' +
                    '</div>' +
                '</div>' +
                '<div class="sffc-form-group">' +
                    '<label>Description</label>' +
                    '<textarea name="exp_desc[]" rows="3" placeholder="Describe your responsibilities and achievements..."></textarea>' +
                '</div>' +
            '</div>';
            $container.append(html);
        },

        addEducationRow: function() {
            var $container = $('.sffc-education-inputs');
            var html = '<div class="sffc-education-input-row" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid rgba(13,53,62,0.1);">' +
                '<button type="button" class="sffc-remove-education" style="float: right; background: none; border: none; color: #ef4444; cursor: pointer;">Remove</button>' +
                '<div class="sffc-form-group">' +
                    '<label>School</label>' +
                    '<input type="text" name="edu_school[]" placeholder="e.g., Harvard Business School">' +
                '</div>' +
                '<div class="sffc-form-group">' +
                    '<label>Degree</label>' +
                    '<input type="text" name="edu_degree[]" placeholder="e.g., MBA">' +
                '</div>' +
                '<div class="sffc-form-group">' +
                    '<label>Field of Study</label>' +
                    '<input type="text" name="edu_field[]" placeholder="e.g., Finance">' +
                '</div>' +
                '<div class="sffc-form-row">' +
                    '<div class="sffc-form-group">' +
                        '<label>Start Date</label>' +
                        '<input type="month" name="edu_start[]">' +
                    '</div>' +
                    '<div class="sffc-form-group">' +
                        '<label>End Date</label>' +
                        '<input type="month" name="edu_end[]">' +
                    '</div>' +
                '</div>' +
            '</div>';
            $container.append(html);
        },

        saveProfile: function($form) {
            var self = this;

            if (this.isLoading) {
                return;
            }

            this.isLoading = true;
            var $submitBtn = $form.find('button[type="submit"], .sffc-btn--primary');
            var originalText = $submitBtn.text();
            $submitBtn.text('Saving...').prop('disabled', true);

            var section = $form.data('section') || 'header';

            // Collect form data based on section
            var formData = {
                action: 'sffc_save_profile',
                nonce: this.nonce,
                section: section
            };

            // Add section-specific data
            switch (section) {
                case 'header':
                    formData.first_name = $form.find('[name="first_name"]').val();
                    formData.last_name = $form.find('[name="last_name"]').val();
                    formData.display_name = $form.find('[name="first_name"]').val() + ' ' + $form.find('[name="last_name"]').val();
                    formData.headline = $form.find('[name="headline"]').val();
                    formData.pronouns = $form.find('[name="pronouns"]').val();
                    formData.location = $form.find('[name="location"]').val();
                    break;
                case 'about':
                    formData.bio = $form.find('[name="bio"]').val();
                    break;
                case 'experience':
                    formData.experience = this.collectExperience($form);
                    break;
                case 'education':
                    formData.education = this.collectEducation($form);
                    break;
                case 'skills':
                    formData.skills = this.collectSkills($form);
                    break;
                case 'preferences':
                    formData.preferred_roles = $form.find('[name="preferred_roles"]').val();
                    formData.preferred_industries = $form.find('[name="preferred_industries"]').val();
                    formData.work_style = $form.find('[name="work_style"]').val();
                    formData.salary_min = $form.find('[name="salary_min"]').val();
                    formData.salary_max = $form.find('[name="salary_max"]').val();
                    break;
                case 'match-preferences':
                    formData.preferred_location = $form.find('[name="preferred_location"]').val();
                    formData.experience_level = $form.find('[name="experience_level"]').val();
                    formData.role_type = $form.find('[name="role_type"]').val();
                    formData.preferred_next_role = $form.find('[name="preferred_next_role"]').val();
                    formData.preferred_industries = $form.find('[name="preferred_industries"]').val();
                    formData.latest_experience_description = $form.find('[name="latest_experience_description"]').val();
                    break;
                case 'career-journey':
                    formData.goal = $form.find('[name="goal"]').val();
                    formData.situation = $form.find('[name="situation"]').val();
                    formData.timeline = $form.find('[name="timeline"]').val();
                    formData.challenge = $form.find('[name="challenge"]').val();
                    break;
            }

            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    self.isLoading = false;
                    $submitBtn.text(originalText).prop('disabled', false);

                    if (response.success) {
                        self.showNotification('Profile saved successfully!', 'success');
                        self.closeModal();
                        self.updateProfileDisplay(response.data, section);
                        // Reload page to show updated profile
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        self.showNotification(response.data.message || 'Failed to save profile.', 'error');
                    }
                },
                error: function() {
                    self.isLoading = false;
                    $submitBtn.text(originalText).prop('disabled', false);
                    self.showNotification('An error occurred. Please try again.', 'error');
                }
            });
        },

        collectSkills: function($form) {
            var skills = [];
            $form.find('.sffc-skill-input-row').each(function() {
                var skill = $(this).find('input[name="skill_name[]"]').val();
                var level = $(this).find('select[name="skill_level[]"]').val();
                if (skill && skill.trim()) {
                    skills.push({
                        name: skill.trim(),
                        level: level || 'intermediate'
                    });
                }
            });
            return skills;
        },

        collectExperience: function($form) {
            var experience = [];
            $form.find('.sffc-experience-input-row').each(function() {
                var title = $(this).find('input[name="exp_title[]"]').val();
                var company = $(this).find('input[name="exp_company[]"]').val();
                if (title && title.trim()) {
                    experience.push({
                        title: title.trim(),
                        company: company ? company.trim() : '',
                        start_date: $(this).find('input[name="exp_start[]"]').val(),
                        end_date: $(this).find('input[name="exp_end[]"]').val(),
                        description: $(this).find('textarea[name="exp_desc[]"]').val()
                    });
                }
            });
            return experience;
        },

        collectEducation: function($form) {
            var education = [];
            $form.find('.sffc-education-input-row').each(function() {
                var school = $(this).find('input[name="edu_school[]"]').val();
                if (school && school.trim()) {
                    education.push({
                        school: school.trim(),
                        degree: $(this).find('input[name="edu_degree[]"]').val(),
                        field: $(this).find('input[name="edu_field[]"]').val(),
                        start_date: $(this).find('input[name="edu_start[]"]').val(),
                        end_date: $(this).find('input[name="edu_end[]"]').val()
                    });
                }
            });
            return education;
        },

        saveVisibility: function(visibility) {
            if (!this.ajaxUrl || !this.nonce) {
                return;
            }

            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_save_profile_visibility',
                    nonce: this.nonce,
                    visibility: visibility
                },
                success: function(response) {
                    if (response.success) {
                        // Update status badge
                        var statusText = visibility === 'active' ? 'Actively Looking' :
                                        visibility === 'open' ? 'Open to Opportunities' : 'Not Looking';
                        $('.sffc-profile-status-badge').text(statusText);
                    }
                }
            });
        },

        shareProfile: function() {
            var profileUrl = window.location.origin + '/profile/' + ($('.sffc-professional-profile').data('userId') || '');

            if (navigator.share) {
                navigator.share({
                    title: 'My Professional Profile',
                    url: profileUrl
                }).catch(function() {});
            } else if (navigator.clipboard) {
                navigator.clipboard.writeText(profileUrl).then(function() {
                    this.showNotification('Profile link copied to clipboard!', 'success');
                }.bind(this));
            } else {
                // Fallback
                var $temp = $('<input>');
                $('body').append($temp);
                $temp.val(profileUrl).select();
                document.execCommand('copy');
                $temp.remove();
                this.showNotification('Profile link copied!', 'success');
            }
        },

        initSkillsInput: function() {
            // Initialize any existing skill inputs
            $('.sffc-skills-input-container').each(function() {
                // Add event listeners for skill inputs if needed
            });
        },

        addSkillInput: function() {
            var $container = $('.sffc-skills-input-container');
            var $newRow = $(
                '<div class="sffc-skill-input-row">' +
                    '<input type="text" name="skill_name[]" placeholder="Skill name" class="sffc-form-input">' +
                    '<select name="skill_level[]" class="sffc-form-select">' +
                        '<option value="beginner">Beginner</option>' +
                        '<option value="intermediate" selected>Intermediate</option>' +
                        '<option value="advanced">Advanced</option>' +
                        '<option value="expert">Expert</option>' +
                    '</select>' +
                    '<button type="button" class="sffc-remove-skill" title="Remove">×</button>' +
                '</div>'
            );
            $container.append($newRow);
            $newRow.find('input').focus();
        },

        removeSkill: function($skillTag) {
            var skillName = $skillTag.find('.sffc-skill-name').text();

            // Animate removal
            $skillTag.fadeOut(200, function() {
                $(this).remove();
            });

            // Save updated skills
            this.saveSkillsToServer();
        },

        saveSkillsToServer: function() {
            var skills = [];
            $('.sffc-skill-tag').each(function() {
                skills.push({
                    name: $(this).find('.sffc-skill-name').text(),
                    level: $(this).data('level') || 'intermediate'
                });
            });

            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_update_skills',
                    nonce: this.nonce,
                    skills: skills
                }
            });
        },

        updateProfileDisplay: function(data) {
            if (!data) return;

            // Update display name
            if (data.display_name) {
                $('.sffc-profile-name').text(data.display_name);
            }

            // Update headline
            if (data.headline) {
                $('.sffc-profile-headline').text(data.headline);
            }

            // Update skills display
            if (data.skills && data.skills.length) {
                var $skillsGrid = $('.sffc-skills-grid');
                $skillsGrid.empty();
                data.skills.forEach(function(skill) {
                    var levelClass = 'sffc-skill-level--' + (skill.level || 'intermediate');
                    $skillsGrid.append(
                        '<span class="sffc-skill-tag" data-level="' + skill.level + '">' +
                            '<span class="sffc-skill-name">' + this.escapeHtml(skill.name) + '</span>' +
                            '<span class="sffc-skill-level ' + levelClass + '">' +
                                this.capitalizeFirst(skill.level || 'Intermediate') +
                            '</span>' +
                        '</span>'
                    );
                }.bind(this));
            }

            // Update preferences
            if (data.preferred_roles) {
                $('.sffc-preference-item[data-pref="roles"] .sffc-preference-item__value').text(data.preferred_roles);
            }
            if (data.preferred_industries) {
                $('.sffc-preference-item[data-pref="industries"] .sffc-preference-item__value').text(data.preferred_industries);
            }
            if (data.work_style) {
                $('.sffc-preference-item[data-pref="work-style"] .sffc-preference-item__value').text(this.capitalizeFirst(data.work_style));
            }
            if (data.salary_min && data.salary_max) {
                $('.sffc-preference-item[data-pref="salary"] .sffc-preference-item__value').text(
                    '$' + this.formatNumber(data.salary_min) + ' - $' + this.formatNumber(data.salary_max)
                );
            }

            // Update completion percentage
            this.updateCompletionBar(data.completion || 0);
        },

        updateCompletionBar: function(percentage) {
            var $bar = $('.sffc-completion-bar');
            var $fill = $bar.find('.sffc-completion-fill');
            var $percentage = $('.sffc-completion-percentage');

            $fill.css('width', percentage + '%');
            $percentage.text(percentage + '%');

            // Update color based on completion
            if (percentage >= 80) {
                $fill.css('background', 'linear-gradient(90deg, #97ffd5, #4ade80)');
            } else if (percentage >= 50) {
                $fill.css('background', 'linear-gradient(90deg, #fbbf24, #f59e0b)');
            } else {
                $fill.css('background', 'linear-gradient(90deg, #f87171, #ef4444)');
            }
        },

        showNotification: function(message, type) {
            type = type || 'info';

            // Remove existing notifications
            $('.sffc-notification').remove();

            var $notification = $(
                '<div class="sffc-notification sffc-notification--' + type + '">' +
                    '<span class="sffc-notification__message">' + this.escapeHtml(message) + '</span>' +
                    '<button class="sffc-notification__close">×</button>' +
                '</div>'
            );

            $('body').append($notification);

            // Animate in
            setTimeout(function() {
                $notification.addClass('is-visible');
            }, 10);

            // Auto-dismiss
            setTimeout(function() {
                $notification.removeClass('is-visible');
                setTimeout(function() {
                    $notification.remove();
                }, 300);
            }, 4000);

            // Close button
            $notification.find('.sffc-notification__close').on('click', function() {
                $notification.removeClass('is-visible');
                setTimeout(function() {
                    $notification.remove();
                }, 300);
            });
        },

        toggleSectionEdit: function($section) {
            $section.toggleClass('is-editing');
            var isEditing = $section.hasClass('is-editing');

            $section.find('.sffc-profile-section__edit-btn').text(isEditing ? 'Done' : 'Edit');

            if (isEditing) {
                $section.find('.sffc-inline-edit').prop('disabled', false).addClass('is-active');
            } else {
                $section.find('.sffc-inline-edit').prop('disabled', true).removeClass('is-active');
                // Save inline edits
                this.saveInlineEdits($section);
            }
        },

        saveInlineEdits: function($section) {
            var sectionType = $section.data('section');
            var data = {
                action: 'sffc_save_profile_section',
                nonce: this.nonce,
                section: sectionType
            };

            $section.find('.sffc-inline-edit').each(function() {
                var name = $(this).attr('name');
                var value = $(this).val();
                if (name) {
                    data[name] = value;
                }
            });

            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: data
            });
        },

        escapeHtml: function(text) {
            if (!text) return '';
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            };
            return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
        },

        capitalizeFirst: function(str) {
            if (!str) return '';
            return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
        },

        formatNumber: function(num) {
            if (!num) return '0';
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }
    };

    // Initialize when document is ready
    $(function() {
        ProfileManager.init();
    });

    // Expose to window for external access
    window.SFFCProfileManager = ProfileManager;

})(jQuery);
