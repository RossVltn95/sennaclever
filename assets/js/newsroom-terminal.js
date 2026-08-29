/**
 * Newsroom Terminal JavaScript
 * Handles story selection, filtering, search, and chart rendering
 */

(function() {
    'use strict';

    // Chart colors matching our palette
    const CHART_COLORS = {
        navy: '#0f2137',
        blue: '#3b82f6',
        blueLight: '#93c5fd',
        green: '#1e6b4a',
        gray: '#a1a1aa',
        cream: '#f5f2ed'
    };

    class NewsroomTerminal {
        constructor() {
            this.terminal = document.querySelector('.nrt-terminal');
            if (!this.terminal) return;

            // AJAX URL for all requests
            this.ajaxUrl = window.sffc_frontend?.ajaxUrl ||
                           window.nrt_ajax?.ajax_url ||
                           '/wp-admin/admin-ajax.php';

            // Nonce for AJAX requests
            this.nonce = window.sffc_frontend?.nonce ||
                         window.nrt_ajax?.nonce ||
                         '';

            // Load data from script tag (safer for special characters)
            const allData = this.loadTerminalData();
            this.storiesData = allData.stories || [];
            this.jobsData = allData.jobs || [];

            // Current tab state
            this.activeTab = 'news';

            // News filters
            this.currentType = 'all';
            this.selectedSectors = [];
            this.selectedRegions = [];
            this.searchQuery = '';

            // Jobs filters
            this.currentJobFunction = 'all';
            this.currentJobLocation = 'all';
            this.selectedJobLevels = [];
            this.selectedJobRegions = [];

            // Login state
            this.isLoggedIn = this.terminal.dataset.loggedIn === 'true';

            this.init();
        }

        loadTerminalData() {
            try {
                const dataId = this.terminal.dataset.terminalId;
                if (dataId) {
                    const scriptEl = document.getElementById(dataId);
                    if (scriptEl) {
                        const data = JSON.parse(scriptEl.textContent || '{}');
                        // Handle both old format (array) and new format (object with stories/jobs)
                        if (Array.isArray(data)) {
                            return { stories: data, jobs: [] };
                        }
                        return data;
                    }
                }
                return { stories: [], jobs: [] };
            } catch (e) {
                console.error('Failed to parse terminal data:', e);
                return { stories: [], jobs: [] };
            }
        }

        init() {
            this.isMobile = window.innerWidth <= 768;
            this.isArticleOpen = false;

            this.bindEvents();
            this.bindMobileEvents();
            this.initCharts();
            this.initLearningTab();
            this.setupMobileView();
            this.initDefaultTab();

            // Initialize welcome modal for logged-out users
            if (!this.isLoggedIn) {
                this.bindWelcomeModalEvents();
            }
        }

        /**
         * Initialize the default active tab based on server-side setting
         */
        initDefaultTab() {
            const activeNavItem = this.terminal.querySelector('.nrt-nav-item.is-active');
            if (activeNavItem) {
                const tabName = activeNavItem.dataset.tab;
                this.activeTab = tabName;

                // Initialize the appropriate tab content and select first item
                if (tabName === 'profile') {
                    this.initProfileDashboard();
                } else if (tabName === 'contacts') {
                    this.initContactsTab();
                } else if (tabName === 'matches') {
                    this.loadMatches();
                } else if (tabName === 'news') {
                    // Select first story
                    const firstStory = this.terminal.querySelector('.nrt-story-card:not(.nrt-firm-card)');
                    if (firstStory) firstStory.classList.add('is-active');
                } else if (tabName === 'recruiter-posts') {
                    this.initRecruiterPostsTab();
                } else if (tabName === 'replies') {
                    this.initRepliesTab();
                }
            }
        }

        /**
         * Setup initial mobile view state
         */
        setupMobileView() {
            if (!this.isMobile) {
                // Hide mobile elements on desktop
                const mobileNav = document.getElementById('nrt-mobile-nav');
                const mobileBack = document.getElementById('nrt-mobile-back');
                if (mobileNav) mobileNav.style.display = 'none';
                if (mobileBack) mobileBack.style.display = 'none';
                return;
            }

            // Show mobile search button
            const mobileSearchBtn = document.getElementById('nrt-mobile-search-open');
            if (mobileSearchBtn) mobileSearchBtn.style.display = 'flex';
        }

        /**
         * Bind all mobile-specific events
         */
        bindMobileEvents() {
            // Resize handler to detect mobile/desktop switch
            let resizeTimeout;
            window.addEventListener('resize', () => {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(() => {
                    const wasMobile = this.isMobile;
                    this.isMobile = window.innerWidth <= 768;
                    if (wasMobile !== this.isMobile) {
                        this.setupMobileView();
                        if (!this.isMobile && this.isArticleOpen) {
                            this.closeMobileArticle();
                        }
                    }
                }, 150);
            });

            // Mobile bottom navigation
            document.addEventListener('click', (e) => {
                const navItem = e.target.closest('.nrt-mobile-nav-item');
                if (!navItem) return;

                e.preventDefault();
                const tab = navItem.dataset.mobileTab;
                this.handleMobileNavClick(tab, navItem);
            });

            // Mobile back button
            const backBtn = document.getElementById('nrt-back-btn');
            if (backBtn) {
                backBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.closeMobileArticle();
                });
            }

            // Mobile search open
            const searchOpenBtn = document.getElementById('nrt-mobile-search-open');
            if (searchOpenBtn) {
                searchOpenBtn.addEventListener('click', () => {
                    this.openMobileSearch();
                });
            }

            // Mobile search close
            const searchCloseBtn = document.getElementById('nrt-mobile-search-close');
            if (searchCloseBtn) {
                searchCloseBtn.addEventListener('click', () => {
                    this.closeMobileSearch();
                });
            }

            // Mobile search input
            const searchInput = document.getElementById('nrt-mobile-search-input');
            if (searchInput) {
                searchInput.addEventListener('input', (e) => {
                    this.handleMobileSearch(e.target.value);
                });
            }

            // Mobile share button
            const shareBtn = document.getElementById('nrt-mobile-share');
            if (shareBtn) {
                shareBtn.addEventListener('click', () => {
                    this.handleMobileShare();
                });
            }

            // Handle swipe gestures for going back
            this.setupSwipeGestures();
        }

        /**
         * Setup swipe gesture handling for mobile
         */
        setupSwipeGestures() {
            const contentPanel = document.getElementById('nrt-content-panel');
            if (!contentPanel) return;

            let touchStartX = 0;
            let touchStartY = 0;
            let touchEndX = 0;

            contentPanel.addEventListener('touchstart', (e) => {
                touchStartX = e.changedTouches[0].screenX;
                touchStartY = e.changedTouches[0].screenY;
            }, { passive: true });

            contentPanel.addEventListener('touchend', (e) => {
                touchEndX = e.changedTouches[0].screenX;
                const touchEndY = e.changedTouches[0].screenY;

                const deltaX = touchEndX - touchStartX;
                const deltaY = Math.abs(touchEndY - touchStartY);

                // Swipe right to go back (only if horizontal swipe is dominant)
                if (deltaX > 100 && deltaY < 50 && this.isMobile && this.isArticleOpen) {
                    this.closeMobileArticle();
                }
            }, { passive: true });
        }

        /**
         * Handle mobile bottom nav clicks
         */
        handleMobileNavClick(tab, navItem) {
            // Update active state
            document.querySelectorAll('.nrt-mobile-nav-item').forEach(item => {
                item.classList.remove('is-active');
            });
            navItem.classList.add('is-active');

            // Close article view if open
            if (this.isArticleOpen) {
                this.closeMobileArticle();
            }

            // Handle special tabs
            if (tab === 'saved') {
                this.showMobileSaved();
                return;
            }

            if (tab === 'profile') {
                this.showMobileProfile();
                return;
            }

            // Handle "more" tab - show bottom sheet with additional options
            if (tab === 'more') {
                this.showMobileMoreSheet();
                return;
            }

            // Switch content tab
            this.switchTab(tab);
        }

        /**
         * Show mobile "More" bottom sheet with additional navigation options
         */
        showMobileMoreSheet() {
            // Check if sheet already exists
            let sheet = document.getElementById('nrt-mobile-more-sheet');
            if (sheet) {
                sheet.classList.add('is-visible');
                return;
            }

            // Create bottom sheet
            sheet = document.createElement('div');
            sheet.id = 'nrt-mobile-more-sheet';
            sheet.className = 'nrt-mobile-more-sheet';
            sheet.innerHTML = `
                <div class="nrt-more-sheet-backdrop"></div>
                <div class="nrt-more-sheet-content">
                    <div class="nrt-more-sheet-header">
                        <span>More Options</span>
                        <button type="button" class="nrt-more-sheet-close">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                                <line x1="18" y1="6" x2="6" y2="18"/>
                                <line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                        </button>
                    </div>
                    <div class="nrt-more-sheet-items">
                        <button type="button" class="nrt-more-sheet-item" data-tab="contacts">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                            <span>HR Contacts</span>
                        </button>
                        <button type="button" class="nrt-more-sheet-item" data-tab="matches">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                            </svg>
                            <span>Matches</span>
                        </button>
                        <button type="button" class="nrt-more-sheet-item" data-tab="profile">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                            <span>Profile</span>
                        </button>
                        <button type="button" class="nrt-more-sheet-item" data-tab="database">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <ellipse cx="12" cy="5" rx="9" ry="3"/>
                                <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/>
                                <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>
                            </svg>
                            <span>Database</span>
                        </button>
                    </div>
                </div>
            `;

            document.body.appendChild(sheet);

            // Bind events
            const backdrop = sheet.querySelector('.nrt-more-sheet-backdrop');
            const closeBtn = sheet.querySelector('.nrt-more-sheet-close');
            const items = sheet.querySelectorAll('.nrt-more-sheet-item');

            backdrop.addEventListener('click', () => this.hideMobileMoreSheet());
            closeBtn.addEventListener('click', () => this.hideMobileMoreSheet());

            items.forEach(item => {
                item.addEventListener('click', () => {
                    const tab = item.dataset.tab;
                    this.hideMobileMoreSheet();
                    this.switchTab(tab);
                });
            });

            // Show with animation
            requestAnimationFrame(() => {
                sheet.classList.add('is-visible');
            });
        }

        /**
         * Hide mobile "More" bottom sheet
         */
        hideMobileMoreSheet() {
            const sheet = document.getElementById('nrt-mobile-more-sheet');
            if (sheet) {
                sheet.classList.remove('is-visible');
            }
        }

        /**
         * Open article in mobile view
         */
        openMobileArticle(title) {
            if (!this.isMobile) return;

            this.isArticleOpen = true;
            this.terminal.classList.add('article-open');

            // Update back button title
            const titleEl = document.getElementById('nrt-mobile-back-title');
            if (titleEl && title) {
                // Truncate long titles
                titleEl.textContent = title.length > 30 ? title.substring(0, 30) + '...' : title;
            }

            // Scroll content panel to top
            const contentPanel = document.getElementById('nrt-content-panel');
            if (contentPanel) {
                contentPanel.scrollTop = 0;
            }
        }

        /**
         * Close article view and return to list
         */
        closeMobileArticle() {
            this.isArticleOpen = false;
            this.terminal.classList.remove('article-open');
        }

        /**
         * Open mobile search overlay
         */
        openMobileSearch() {
            const overlay = document.getElementById('nrt-mobile-search-overlay');
            const input = document.getElementById('nrt-mobile-search-input');

            if (overlay) {
                overlay.classList.add('is-active');
                document.body.style.overflow = 'hidden';
            }

            if (input) {
                setTimeout(() => input.focus(), 100);
            }
        }

        /**
         * Close mobile search overlay
         */
        closeMobileSearch() {
            const overlay = document.getElementById('nrt-mobile-search-overlay');
            const input = document.getElementById('nrt-mobile-search-input');

            if (overlay) {
                overlay.classList.remove('is-active');
                document.body.style.overflow = '';
            }

            if (input) {
                input.value = '';
            }

            // Clear search results
            const resultsContainer = document.getElementById('nrt-mobile-search-results');
            if (resultsContainer) {
                resultsContainer.innerHTML = `
                    <div class="nrt-mobile-search-empty">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <circle cx="11" cy="11" r="8"/>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                        <p>Search for stories, deals, or jobs</p>
                    </div>
                `;
            }
        }

        /**
         * Handle mobile search input
         */
        handleMobileSearch(query) {
            const resultsContainer = document.getElementById('nrt-mobile-search-results');
            if (!resultsContainer) return;

            query = query.toLowerCase().trim();

            if (query.length < 2) {
                resultsContainer.innerHTML = `
                    <div class="nrt-mobile-search-empty">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <circle cx="11" cy="11" r="8"/>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                        <p>Search for stories, deals, or jobs</p>
                    </div>
                `;
                return;
            }

            // Search stories
            const matchedStories = this.storiesData.filter(story => {
                const searchText = `${story.title} ${story.excerpt} ${story.sector || ''} ${story.region || ''}`.toLowerCase();
                return searchText.includes(query);
            }).slice(0, 10);

            // Search jobs
            const matchedJobs = this.jobsData.filter(job => {
                const searchText = `${job.title} ${job.company || ''} ${job.location || ''}`.toLowerCase();
                return searchText.includes(query);
            }).slice(0, 5);

            if (matchedStories.length === 0 && matchedJobs.length === 0) {
                resultsContainer.innerHTML = `
                    <div class="nrt-mobile-search-empty">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="15" y1="9" x2="9" y2="15"/>
                            <line x1="9" y1="9" x2="15" y2="15"/>
                        </svg>
                        <p>No results found for "${this.escapeHtml(query)}"</p>
                    </div>
                `;
                return;
            }

            let html = '';

            if (matchedStories.length > 0) {
                html += '<div class="nrt-search-section"><h4 class="nrt-search-section-title">Stories</h4>';
                matchedStories.forEach(story => {
                    html += `
                        <article class="nrt-story-card nrt-search-result" data-story-id="${this.escapeHtml(story.id)}">
                            <h3 class="nrt-story-title">${this.escapeHtml(story.title)}</h3>
                            <p class="nrt-story-excerpt">${this.escapeHtml(story.excerpt || '').substring(0, 80)}...</p>
                            <div class="nrt-story-meta">
                                ${story.sector ? `<span class="nrt-story-sector">${this.escapeHtml(story.sector)}</span>` : ''}
                            </div>
                        </article>
                    `;
                });
                html += '</div>';
            }

            if (matchedJobs.length > 0) {
                html += '<div class="nrt-search-section"><h4 class="nrt-search-section-title">Jobs</h4>';
                matchedJobs.forEach(job => {
                    html += `
                        <article class="nrt-job-card nrt-search-result" data-job-id="${this.escapeHtml(job.id)}">
                            <h3 class="nrt-job-title">${this.escapeHtml(job.title)}</h3>
                            <div class="nrt-job-company">${this.escapeHtml(job.company || '')}</div>
                            <div class="nrt-job-meta">
                                ${job.location ? `<span class="nrt-job-location">${this.escapeHtml(job.location)}</span>` : ''}
                            </div>
                        </article>
                    `;
                });
                html += '</div>';
            }

            resultsContainer.innerHTML = html;

            // Add click handlers for search results
            resultsContainer.querySelectorAll('.nrt-search-result').forEach(card => {
                card.addEventListener('click', () => {
                    const storyId = card.dataset.storyId;
                    const jobId = card.dataset.jobId;

                    this.closeMobileSearch();

                    if (storyId) {
                        const storyCard = document.querySelector(`.nrt-story-card[data-story-id="${storyId}"]`);
                        if (storyCard) this.selectStory(storyCard);
                    } else if (jobId) {
                        const jobCard = document.querySelector(`.nrt-job-card[data-job-id="${jobId}"]`);
                        if (jobCard) this.selectJob(jobCard);
                    }
                });
            });
        }

        /**
         * Handle mobile share action
         */
        handleMobileShare() {
            const url = window.location.href;
            const title = document.getElementById('nrt-mobile-back-title')?.textContent || 'Check out this article';

            if (navigator.share) {
                navigator.share({
                    title: title,
                    url: url
                }).catch(() => {
                    // User cancelled or error - fallback to clipboard
                    this.copyToClipboard(url);
                });
            } else {
                this.copyToClipboard(url);
            }
        }

        /**
         * Copy text to clipboard with feedback
         */
        copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                // Show brief feedback
                const shareBtn = document.getElementById('nrt-mobile-share');
                if (shareBtn) {
                    const originalHTML = shareBtn.innerHTML;
                    shareBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>';
                    setTimeout(() => {
                        shareBtn.innerHTML = originalHTML;
                    }, 1500);
                }
            }).catch(console.error);
        }

        /**
         * Show saved items - switches to profile tab
         */
        showMobileSaved() {
            // Switch to profile tab where saved items are shown
            this.switchTab('profile');
            // Scroll to saved section after a brief delay
            setTimeout(() => {
                const savedSection = document.getElementById('nrt-profile-saved');
                if (savedSection) {
                    savedSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }, 100);
        }

        /**
         * Show profile section - switches to profile tab
         */
        showMobileProfile() {
            this.switchTab('profile');
        }

        bindEvents() {
            // Tab navigation clicks
            this.terminal.addEventListener('click', (e) => {
                const navItem = e.target.closest('.nrt-nav-item[data-tab]');
                if (navItem) {
                    e.preventDefault();
                    this.switchTab(navItem.dataset.tab);
                }
            });

            // Submissions "Load More" button
            this.terminal.addEventListener('click', (e) => {
                const loadMoreBtn = e.target.closest('.nrt-submissions-load-more');
                if (loadMoreBtn) {
                    const page = parseInt(loadMoreBtn.dataset.page) || 2;
                    loadMoreBtn.disabled = true;
                    loadMoreBtn.textContent = 'Loading...';
                    this.loadSubmissions(page);
                }
            });

            // User dropdown toggle
            const userWrapper = this.terminal.querySelector('.nrt-user-wrapper');
            const userToggle = this.terminal.querySelector('.nrt-user');
            if (userWrapper && userToggle) {
                userToggle.addEventListener('click', (e) => {
                    e.stopPropagation();
                    userWrapper.classList.toggle('is-open');
                });

                // Close dropdown when clicking outside
                document.addEventListener('click', (e) => {
                    if (!userWrapper.contains(e.target)) {
                        userWrapper.classList.remove('is-open');
                    }
                });

                // Handle dropdown item clicks (for database tab)
                const dropdownItems = userWrapper.querySelectorAll('.nrt-user-dropdown-item[data-tab]');
                dropdownItems.forEach(item => {
                    item.addEventListener('click', (e) => {
                        e.preventDefault();
                        this.switchTab(item.dataset.tab);
                        userWrapper.classList.remove('is-open');
                    });
                });
            }

            // Story card clicks (exclude firm cards and job cards which use nrt-story-card as base class)
            this.terminal.addEventListener('click', (e) => {
                const storyCard = e.target.closest('.nrt-story-card');
                if (storyCard && !storyCard.classList.contains('nrt-firm-card')) {
                    this.selectStory(storyCard);
                }
            });

            // Row 1: Deal Type chips (single-select) - News tab
            this.terminal.addEventListener('click', (e) => {
                const chip = e.target.closest('.nrt-tab-news .nrt-filter-chip');
                if (!chip || !chip.dataset.type) return;

                // Update active state (single-select)
                this.terminal.querySelectorAll('.nrt-tab-news .nrt-filter-chip').forEach(c => c.classList.remove('is-active'));
                chip.classList.add('is-active');

                // Apply filter
                this.currentType = chip.dataset.type;
                this.applyFilters();
            });

            // Row 2: Sector/Region tags (multi-select)
            this.terminal.addEventListener('click', (e) => {
                const tag = e.target.closest('.nrt-filter-tag');
                if (!tag) return;

                // Toggle active state (multi-select)
                tag.classList.toggle('is-active');

                // Update selected arrays
                if (tag.dataset.sector !== undefined) {
                    const sector = tag.dataset.sector;
                    if (tag.classList.contains('is-active')) {
                        if (!this.selectedSectors.includes(sector)) {
                            this.selectedSectors.push(sector);
                        }
                    } else {
                        this.selectedSectors = this.selectedSectors.filter(s => s !== sector);
                    }
                } else if (tag.dataset.region !== undefined) {
                    const region = tag.dataset.region;
                    if (tag.classList.contains('is-active')) {
                        if (!this.selectedRegions.includes(region)) {
                            this.selectedRegions.push(region);
                        }
                    } else {
                        this.selectedRegions = this.selectedRegions.filter(r => r !== region);
                    }
                }

                this.updateFilterCount();
                this.applyFilters();
            });

            // Clear filters button
            this.terminal.addEventListener('click', (e) => {
                const clearBtn = e.target.closest('.nrt-filter-clear');
                if (!clearBtn) return;

                // Clear all tag selections
                this.terminal.querySelectorAll('.nrt-filter-tag').forEach(t => t.classList.remove('is-active'));
                this.selectedSectors = [];
                this.selectedRegions = [];
                this.updateFilterCount();
                this.applyFilters();
            });

            // Legacy: Filter tabs (for backwards compatibility)
            const filterTabs = this.terminal.querySelectorAll('.nrt-filter-tab');
            filterTabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    this.setFilter(tab.dataset.filter);
                    filterTabs.forEach(t => t.classList.remove('is-active'));
                    tab.classList.add('is-active');
                });
            });

            // Legacy: Sector filter dropdown (for backwards compatibility)
            const sectorFilter = document.getElementById('nrt-sector-filter');
            if (sectorFilter) {
                sectorFilter.addEventListener('change', (e) => {
                    this.currentSector = e.target.value;
                    this.applyFilters();
                });
            }

            // Legacy: Region filter dropdown (for backwards compatibility)
            const regionFilter = document.getElementById('nrt-region-filter');
            if (regionFilter) {
                regionFilter.addEventListener('change', (e) => {
                    this.currentRegion = e.target.value;
                    this.applyFilters();
                });
            }

            // Search
            const searchInput = document.getElementById('nrt-search');
            if (searchInput) {
                let debounceTimer;
                searchInput.addEventListener('input', (e) => {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(() => {
                        this.searchQuery = e.target.value.toLowerCase();
                        this.applyFilters();
                    }, 300);
                });
            }

            // Refresh button
            const refreshBtn = document.getElementById('nrt-refresh');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', () => {
                    this.refresh();
                });
            }

            // Load more
            const loadMoreBtn = document.getElementById('nrt-load-more');
            if (loadMoreBtn) {
                loadMoreBtn.addEventListener('click', () => {
                    this.loadMore();
                });
            }

            // Action buttons in article
            this.terminal.addEventListener('click', (e) => {
                const actionBtn = e.target.closest('.nrt-action-btn[data-action]');
                if (actionBtn) {
                    this.handleAction(actionBtn.dataset.action);
                }
            });

            // View toggle (Report / Data)
            this.terminal.addEventListener('click', (e) => {
                const toggleBtn = e.target.closest('.nrt-view-toggle-btn');
                if (toggleBtn) {
                    this.handleViewToggle(toggleBtn);
                }
            });

            // PDF download button
            this.terminal.addEventListener('click', (e) => {
                const pdfBtn = e.target.closest('.nrt-pdf-btn, #nrt-pdf-download');
                if (pdfBtn) {
                    e.preventDefault();
                    this.downloadPDF();
                }
            });

            // Excel download button
            this.terminal.addEventListener('click', (e) => {
                const excelBtn = e.target.closest('.nrt-excel-btn, #nrt-excel-download');
                if (excelBtn) {
                    e.preventDefault();
                    this.downloadExcel();
                }
            });

            // Profile quick links - scroll to section in right panel
            this.terminal.addEventListener('click', (e) => {
                const quickLink = e.target.closest('.nrt-profile-quick-link[data-scroll-to]');
                if (quickLink) {
                    e.preventDefault();
                    const targetId = quickLink.dataset.scrollTo;
                    const targetEl = document.getElementById(targetId);
                    const profileView = document.getElementById('nrt-profile-view');

                    if (targetEl && profileView) {
                        // Scroll within the profile view container
                        targetEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }
            });
        }

        handleViewToggle(btn) {
            const view = btn.dataset.view;
            const article = btn.closest('.nrt-article');
            if (!article) return;

            // Update toggle buttons
            const allToggleBtns = article.querySelectorAll('.nrt-view-toggle-btn');
            allToggleBtns.forEach(b => {
                b.classList.remove('is-active');
                b.setAttribute('aria-selected', 'false');
            });
            btn.classList.add('is-active');
            btn.setAttribute('aria-selected', 'true');

            // Update views
            const reportView = article.querySelector('.nrt-report-view');
            const dataView = article.querySelector('.nrt-data-view');

            if (view === 'report') {
                if (reportView) {
                    reportView.style.display = '';
                    reportView.classList.add('is-active');
                }
                if (dataView) {
                    dataView.style.display = 'none';
                    dataView.classList.remove('is-active');
                }
            } else if (view === 'data') {
                if (reportView) {
                    reportView.style.display = 'none';
                    reportView.classList.remove('is-active');
                }
                if (dataView) {
                    dataView.style.display = '';
                    dataView.classList.add('is-active');
                }
            }
        }

        selectStory(card) {
            // Update active state
            const allCards = this.terminal.querySelectorAll('.nrt-story-card');
            allCards.forEach(c => c.classList.remove('is-active'));
            card.classList.add('is-active');

            // Get story title for mobile back button
            const storyTitle = card.querySelector('.nrt-story-title')?.textContent || 'Article';

            // Check if card is locked (for logged-out users)
            if (card.dataset.locked === 'true') {
                this.showSubscriptionBox(card);
                // Open mobile article view for subscription box too
                this.openMobileArticle(storyTitle);
                return;
            }

            const storyId = card.dataset.storyId;
            this.loadStoryContent(storyId);

            // Open mobile article view with slide animation
            this.openMobileArticle(storyTitle);
        }

        showSubscriptionBox(card) {
            // Use content-inner for mobile compatibility (preserves back nav)
            const contentInner = document.getElementById('nrt-content-inner');
            const contentPanel = contentInner || document.getElementById('nrt-content-panel');
            const template = document.getElementById('nrt-subscription-template');

            if (!contentPanel || !template) return;

            // Clone the template content
            const subscriptionBox = template.content.cloneNode(true);

            // Get title from the card (works for both stories and guides)
            const storyTitle = card.querySelector('.nrt-story-title')?.textContent ||
                               card.querySelector('.nrt-guide-title')?.textContent ||
                               'Premium Content';

            // Set the story title in the subscription box
            const titleEl = subscriptionBox.querySelector('[data-story-title]');
            if (titleEl) {
                titleEl.textContent = storyTitle;
            }

            // Clear and show subscription box
            contentPanel.innerHTML = '';
            contentPanel.appendChild(subscriptionBox);
        }

        switchTab(tabName) {
            if (this.activeTab === tabName) return;
            this.activeTab = tabName;

            // Update desktop nav items
            this.terminal.querySelectorAll('.nrt-nav-item[data-tab]').forEach(item => {
                item.classList.toggle('is-active', item.dataset.tab === tabName);
            });

            // Update mobile nav items
            document.querySelectorAll('.nrt-mobile-nav-item[data-mobile-tab]').forEach(item => {
                item.classList.toggle('is-active', item.dataset.mobileTab === tabName);
            });

            // Update tab content visibility
            this.terminal.querySelectorAll('.nrt-tab-content').forEach(content => {
                const isActive = content.dataset.tabContent === tabName;
                content.classList.toggle('is-active', isActive);
            });

            // Handle content panel switching between normal content and special views
            const contentInner = document.getElementById('nrt-content-inner');
            const guideView = document.getElementById('nrt-guide-view');
            const profileView = document.getElementById('nrt-profile-view');
            const contactView = document.getElementById('nrt-contact-view');
            const opportunityView = document.getElementById('nrt-opportunity-view');
            const conversationView = document.getElementById('nrt-conversation-view');
            const introsView = document.getElementById('nrt-intros-view');
            const welcomePanel = document.getElementById('nrt-welcome-panel');

            // Hide all views first
            if (contentInner) contentInner.style.display = 'none';
            if (guideView) guideView.style.display = 'none';
            if (profileView) profileView.style.display = 'none';
            if (contactView) contactView.style.display = 'none';
            if (opportunityView) opportunityView.style.display = 'none';
            if (conversationView) conversationView.style.display = 'none';
            if (introsView) introsView.style.display = 'none';
            if (welcomePanel) welcomePanel.style.display = 'none';

            // Show appropriate view based on tab
            // For logged-out users, show welcome panel and update content based on tab
            if (!this.isLoggedIn && welcomePanel) {
                welcomePanel.style.display = '';
                this.updateWelcomePanelContent(tabName);
            } else if (tabName === 'profile') {
                // Show profile dashboard
                if (profileView) profileView.style.display = '';
            } else if (tabName === 'contacts') {
                // Show contact view for contacts tab
                if (contactView) contactView.style.display = '';
            } else if (tabName === 'opportunities') {
                // Show opportunity view for opportunities tab
                if (opportunityView) opportunityView.style.display = '';
            } else if (tabName === 'replies') {
                // Show conversation view for replies tab
                if (conversationView) conversationView.style.display = '';
            } else if (tabName === 'recruiter-intros') {
                // Show intros view for recruiter intros tab
                if (introsView) introsView.style.display = '';
            } else if (tabName === 'learning') {
                // Show normal content until user clicks a guide
                if (contentInner) contentInner.style.display = '';
            } else if (tabName === 'database') {
                // Initialize database tab and show normal content
                if (contentInner) contentInner.style.display = '';
                this.initDatabaseTab();
            } else {
                // News, jobs, companies, matches or other tabs - show normal content
                if (contentInner) contentInner.style.display = '';
            }

            // Update search placeholder based on tab
            const searchInput = document.getElementById('nrt-search');
            if (searchInput) {
                const placeholders = {
                    'news': 'Search stories...',
                    'jobs': 'Search jobs...',
                    'database': 'Search firms...',
                    'learning': 'Search guides...',
                    'matches': 'Search matches...',
                    'profile': 'Search...',
                    'contacts': 'Search HR contacts...',
                    'recruiter-intros': 'Search...',
                    'opportunities': 'Search opportunities...',
                    'replies': 'Search conversations...'
                };
                searchInput.placeholder = placeholders[tabName] || 'Search...';
            }

            // If switching to profile, initialize profile dashboard
            if (tabName === 'profile') {
                this.initProfileDashboard();
            }

            // If switching to matches, load matches
            if (tabName === 'matches') {
                this.loadMatches();
            }

            // If switching to contacts, initialize contacts tab
            if (tabName === 'contacts') {
                this.initContactsTab();
            }

            // If switching to profile, initialize profile networking
            if (tabName === 'profile') {
                this.initProfileNetworking();
            }

            // If switching to news, select first story if none selected
            if (tabName === 'news') {
                const selectedStory = this.terminal.querySelector('.nrt-story-card:not(.nrt-firm-card).is-active');
                if (!selectedStory) {
                    const firstStory = this.terminal.querySelector('.nrt-story-card:not(.nrt-firm-card)');
                    if (firstStory) {
                        this.selectStory(firstStory);
                    }
                }
            }

            // If switching to recruiter-posts, initialize that tab
            if (tabName === 'recruiter-posts') {
                this.initRecruiterPostsTab();
            }

            // If switching to replies, initialize that tab
            if (tabName === 'replies') {
                this.initRepliesTab();
            }
        }

        /**
         * Initialize Recruiter Posts Tab
         */
        initRecruiterPostsTab() {
            if (this.recruiterPostsInitialized) return;
            this.recruiterPostsInitialized = true;

            // Store recruiter posts data
            this.recruiterPosts = [];
            this.recruiterPostsFilter = 'all';
            this.selectedRecruiterPostId = null;

            // Load recruiter posts
            this.loadRecruiterPosts();

            // Bind filter buttons
            const filterBtns = document.querySelectorAll('.nrt-rp-filter-btn');
            filterBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    filterBtns.forEach(b => b.classList.remove('is-active'));
                    btn.classList.add('is-active');
                    this.recruiterPostsFilter = btn.dataset.rpFilter;
                    this.renderRecruiterPostsList();
                });
            });

            // Bind recruiter post detail actions
            this.bindRecruiterPostDetailActions();
        }

        /**
         * Load recruiter posts from server
         */
        loadRecruiterPosts() {
            const loading = document.getElementById('nrt-recruiter-posts-loading');
            const empty = document.getElementById('nrt-recruiter-posts-empty');
            const list = document.getElementById('nrt-recruiter-posts-list');

            // Show loading
            if (loading) loading.style.display = '';
            if (empty) empty.style.display = 'none';

            // Fetch recruiter posts from server
            fetch(this.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'nrt_load_recruiter_posts',
                    nonce: this.nonce,
                    filter: this.recruiterPostsFilter || 'all'
                })
            })
            .then(res => res.json())
            .then(response => {
                if (loading) loading.style.display = 'none';

                if (response.success && response.data && response.data.length > 0) {
                    this.recruiterPosts = response.data;
                    this.renderRecruiterPostsList();
                } else {
                    this.recruiterPosts = [];
                    if (empty) empty.style.display = '';
                }
            })
            .catch(err => {
                console.error('Error loading recruiter posts:', err);
                if (loading) loading.style.display = 'none';
                if (empty) empty.style.display = '';
                this.recruiterPosts = [];
            });
        }

        /**
         * Render recruiter posts list based on current filter
         */
        renderRecruiterPostsList() {
            const list = document.getElementById('nrt-recruiter-posts-list');
            const empty = document.getElementById('nrt-recruiter-posts-empty');

            if (!list) return;

            // Filter posts
            let filtered = this.recruiterPosts;
            if (this.recruiterPostsFilter === 'recent') {
                filtered = this.recruiterPosts.filter(p => p.isNew);
            } else if (this.recruiterPostsFilter === 'featured') {
                filtered = this.recruiterPosts.filter(p => p.isFeatured);
            }

            if (filtered.length === 0) {
                list.innerHTML = '';
                if (empty) empty.style.display = '';
                return;
            }

            if (empty) empty.style.display = 'none';

            // Render cards
            list.innerHTML = filtered.map((post, index) => this.renderRecruiterPostCard(post, index)).join('');

            // Bind click handlers
            list.querySelectorAll('.nrt-recruiter-post-card').forEach(card => {
                card.addEventListener('click', () => {
                    const postId = parseInt(card.dataset.postId);
                    this.selectRecruiterPost(postId);

                    // Update active state
                    list.querySelectorAll('.nrt-recruiter-post-card').forEach(c => c.classList.remove('is-active'));
                    card.classList.add('is-active');
                });
            });
        }

        /**
         * Render a single recruiter post card
         */
        renderRecruiterPostCard(post, index = 0) {
            const initial = (post.company || 'C').charAt(0).toUpperCase();
            const recruiterInfo = post.recruiter?.company || post.recruiter?.name || 'Recruiter';

            // Badges
            let badges = '';
            if (post.isNew) badges += '<span class="nrt-rp-card-badge nrt-rp-badge-new">NEW</span>';
            if (post.isFeatured) badges += '<span class="nrt-rp-card-badge nrt-rp-badge-featured">FEATURED</span>';
            if (post.isUrgent) badges += '<span class="nrt-rp-card-badge nrt-rp-badge-urgent">URGENT</span>';

            return `
                <div class="nrt-recruiter-post-card ${this.selectedRecruiterPostId === post.id ? 'is-active' : ''}" data-post-id="${post.id}">
                    ${badges}
                    <div class="nrt-rp-card-header">
                        <div class="nrt-rp-card-logo">
                            <span>${initial}</span>
                        </div>
                        <div class="nrt-rp-card-info">
                            <h4 class="nrt-rp-card-title">${post.jobTitle || post.title || ''}</h4>
                            <p class="nrt-rp-card-company">${post.company || 'Confidential'}</p>
                        </div>
                    </div>
                    <div class="nrt-rp-card-meta">
                        ${post.location ? `<span class="nrt-rp-card-location">${post.location}</span>` : ''}
                        ${post.salary ? `<span class="nrt-rp-card-salary">${post.salary}</span>` : ''}
                    </div>
                    <p class="nrt-rp-card-recruiter">${recruiterInfo}</p>
                    <span class="nrt-rp-card-time">${post.time || ''}</span>
                </div>
            `;
        }

        /**
         * Select and display recruiter post details
         */
        selectRecruiterPost(postId) {
            const post = this.recruiterPosts.find(p => p.id === postId);
            if (!post) return;

            this.selectedRecruiterPostId = postId;

            // Show the detail view
            const detailView = document.getElementById('nrt-recruiter-post-view');
            if (detailView) {
                detailView.style.display = '';
            }

            // Hide placeholder, show content
            const placeholder = document.getElementById('nrt-opportunity-placeholder');
            const content = document.getElementById('nrt-opportunity-content');

            if (placeholder) placeholder.style.display = 'none';
            if (content) content.style.display = '';

            // Populate content (reusing opportunity detail elements)
            const companyInitial = (post.company || 'C').charAt(0).toUpperCase();
            const recruiterInitial = (post.recruiter?.name || 'R').charAt(0).toUpperCase();

            const companyLogoEl = document.querySelector('#nrt-opp-company-logo .nrt-opp-company-initial');
            if (companyLogoEl) companyLogoEl.textContent = companyInitial;

            const titleEl = document.getElementById('nrt-opp-title');
            if (titleEl) titleEl.textContent = post.jobTitle || post.title || '';

            const companyEl = document.getElementById('nrt-opp-company');
            if (companyEl) companyEl.textContent = post.company || 'Confidential';

            const locationEl = document.getElementById('nrt-opp-location');
            if (locationEl) locationEl.textContent = post.location || '';

            const salaryEl = document.getElementById('nrt-opp-salary');
            if (salaryEl) salaryEl.textContent = post.salary || '';

            const badge = document.getElementById('nrt-opp-badge');
            if (badge) {
                if (post.isNew) {
                    badge.textContent = 'NEW';
                    badge.style.display = '';
                } else if (post.isFeatured) {
                    badge.textContent = 'FEATURED';
                    badge.style.display = '';
                } else {
                    badge.style.display = 'none';
                }
            }

            const recruiterAvatarEl = document.querySelector('#nrt-opp-recruiter-avatar .nrt-opp-recruiter-initial');
            if (recruiterAvatarEl) recruiterAvatarEl.textContent = recruiterInitial;

            const recruiterNameEl = document.getElementById('nrt-opp-recruiter-name');
            if (recruiterNameEl) recruiterNameEl.textContent = post.recruiter?.name || '';

            const recruiterTitleEl = document.getElementById('nrt-opp-recruiter-title');
            if (recruiterTitleEl) recruiterTitleEl.textContent = post.recruiter?.title || '';

            const recruiterCompanyEl = document.getElementById('nrt-opp-recruiter-company');
            if (recruiterCompanyEl) recruiterCompanyEl.textContent = post.recruiter?.company || '';

            const recruiterStatusEl = document.getElementById('nrt-opp-recruiter-status');
            if (recruiterStatusEl) recruiterStatusEl.textContent = 'Hiring for this role';

            // Update match section to show industries/experience
            const matchList = document.getElementById('nrt-opp-match-list');
            if (matchList) {
                let matchItems = [];
                if (post.experience) matchItems.push(`Experience: ${post.experience}`);
                if (post.industries && post.industries.length > 0) {
                    matchItems.push(`Industry: ${post.industries.join(', ')}`);
                }
                if (post.postType && post.postType.length > 0) {
                    matchItems.push(`Type: ${post.postType.join(', ')}`);
                }

                matchList.innerHTML = matchItems.map(item => `
                    <li class="nrt-opp-match-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        <span>${item}</span>
                    </li>
                `).join('');
            }

            // Update action buttons
            const startChatBtn = document.querySelector('.nrt-opp-action-btn[data-action="start-chat"]');
            if (startChatBtn && post.recruiter?.linkedin) {
                startChatBtn.onclick = () => {
                    window.open(post.recruiter.linkedin, '_blank');
                };
                startChatBtn.innerHTML = `
                    <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18">
                        <path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/>
                    </svg>
                    View on LinkedIn
                `;
            }

            // Hide save button (not applicable for public posts)
            const saveBtn = document.querySelector('.nrt-opp-text-btn[data-action="save-opportunity"]');
            if (saveBtn) saveBtn.style.display = 'none';

            const notInterestedBtn = document.querySelector('.nrt-opp-text-btn[data-action="not-interested"]');
            if (notInterestedBtn) notInterestedBtn.style.display = 'none';
        }

        /**
         * Bind recruiter post detail action buttons
         */
        bindRecruiterPostDetailActions() {
            // View Job button
            const viewJobBtn = document.querySelector('.nrt-opp-action-btn[data-action="view-job"]');
            if (viewJobBtn) {
                viewJobBtn.addEventListener('click', () => {
                    if (this.selectedRecruiterPostId) {
                        // Could open a modal or link to job details
                        console.log('View job:', this.selectedRecruiterPostId);
                    }
                });
            }
        }

        /**
         * Open Campaign Setup Modal
         */
        openCampaignSetupModal(editMode = false) {
            // Check if modal already exists
            let modal = document.getElementById('nrt-campaign-setup-modal');
            if (!modal) {
                modal = this.createCampaignSetupModal();
                document.body.appendChild(modal);
            }

            // Show modal
            modal.classList.add('is-visible');
            document.body.style.overflow = 'hidden';

            // Initialize first step
            this.campaignSetupStep = 1;
            this.updateCampaignSetupStep();
        }

        /**
         * Create Campaign Setup Modal HTML
         */
        createCampaignSetupModal() {
            const modal = document.createElement('div');
            modal.id = 'nrt-campaign-setup-modal';
            modal.className = 'nrt-campaign-modal';
            modal.innerHTML = `
                <div class="nrt-campaign-modal-backdrop"></div>
                <div class="nrt-campaign-modal-content">
                    <div class="nrt-campaign-modal-header">
                        <h2>Set Up Your Recruiter Intro Campaign</h2>
                        <button type="button" class="nrt-campaign-modal-close" aria-label="Close">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                                <line x1="18" y1="6" x2="6" y2="18"/>
                                <line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                        </button>
                    </div>
                    <div class="nrt-campaign-modal-progress">
                        <div class="nrt-campaign-progress-bar">
                            <div class="nrt-campaign-progress-fill" style="width: 20%;"></div>
                        </div>
                        <span class="nrt-campaign-step-indicator">Step 1 of 5</span>
                    </div>
                    <div class="nrt-campaign-modal-body">
                        <!-- Step 1: Target Roles -->
                        <div class="nrt-campaign-step" data-step="1">
                            <h3>What roles are you looking for?</h3>
                            <p class="nrt-campaign-step-desc">Tell us about your ideal position so we can match you with the right recruiters.</p>
                            <div class="nrt-campaign-field">
                                <label for="nrt-target-roles">Target Role Titles</label>
                                <input type="text" id="nrt-target-roles" placeholder="e.g., Senior Analyst, Associate Director, VP Finance" class="nrt-campaign-input">
                                <span class="nrt-campaign-hint">Separate multiple roles with commas</span>
                            </div>
                            <div class="nrt-campaign-field">
                                <label>Target Industries</label>
                                <div class="nrt-campaign-checkbox-grid" id="nrt-target-industries">
                                    <label class="nrt-campaign-checkbox"><input type="checkbox" value="private-equity"> Private Equity</label>
                                    <label class="nrt-campaign-checkbox"><input type="checkbox" value="investment-banking"> Investment Banking</label>
                                    <label class="nrt-campaign-checkbox"><input type="checkbox" value="venture-capital"> Venture Capital</label>
                                    <label class="nrt-campaign-checkbox"><input type="checkbox" value="private-credit"> Private Credit</label>
                                    <label class="nrt-campaign-checkbox"><input type="checkbox" value="corporate-finance"> Corporate Finance</label>
                                    <label class="nrt-campaign-checkbox"><input type="checkbox" value="consulting"> Consulting</label>
                                    <label class="nrt-campaign-checkbox"><input type="checkbox" value="real-estate"> Real Estate</label>
                                    <label class="nrt-campaign-checkbox"><input type="checkbox" value="tech"> Technology</label>
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: Strategy & Salary -->
                        <div class="nrt-campaign-step" data-step="2" style="display: none;">
                            <h3>Which strategy and how much?</h3>
                            <p class="nrt-campaign-step-desc">Set your private equity strategy preferences and salary expectations.</p>
                            <div class="nrt-campaign-field">
                                <label>Preferred Strategies</label>
                                <div class="nrt-campaign-checkbox-grid" id="nrt-target-locations">
                                    <label class="nrt-campaign-checkbox"><input type="checkbox" value="buyout"> Buyout</label>
                                    <label class="nrt-campaign-checkbox"><input type="checkbox" value="growth-equity"> Growth Equity</label>
                                    <label class="nrt-campaign-checkbox"><input type="checkbox" value="private-credit"> Private Credit</label>
                                    <label class="nrt-campaign-checkbox"><input type="checkbox" value="secondaries"> Secondaries</label>
                                    <label class="nrt-campaign-checkbox"><input type="checkbox" value="infrastructure"> Infrastructure</label>
                                    <label class="nrt-campaign-checkbox"><input type="checkbox" value="portfolio-ops"> Portfolio Ops</label>
                                    <label class="nrt-campaign-checkbox"><input type="checkbox" value="investor-relations"> Investor Relations</label>
                                </div>
                            </div>
                            <div class="nrt-campaign-field">
                                <label for="nrt-salary-range">Salary Expectations (Annual)</label>
                                <div class="nrt-campaign-salary-range">
                                    <select id="nrt-salary-min" class="nrt-campaign-select">
                                        <option value="">Minimum</option>
                                        <option value="50000">$50,000</option>
                                        <option value="75000">$75,000</option>
                                        <option value="100000">$100,000</option>
                                        <option value="125000">$125,000</option>
                                        <option value="150000">$150,000</option>
                                        <option value="200000">$200,000</option>
                                        <option value="250000">$250,000</option>
                                        <option value="300000">$300,000+</option>
                                    </select>
                                    <span>to</span>
                                    <select id="nrt-salary-max" class="nrt-campaign-select">
                                        <option value="">Maximum</option>
                                        <option value="75000">$75,000</option>
                                        <option value="100000">$100,000</option>
                                        <option value="125000">$125,000</option>
                                        <option value="150000">$150,000</option>
                                        <option value="200000">$200,000</option>
                                        <option value="250000">$250,000</option>
                                        <option value="300000">$300,000</option>
                                        <option value="500000">$500,000+</option>
                                    </select>
                                </div>
                            </div>
                            <div class="nrt-campaign-field">
                                <label>Availability</label>
                                <div class="nrt-campaign-radio-group" id="nrt-availability">
                                    <label class="nrt-campaign-radio"><input type="radio" name="availability" value="immediately"> Immediately</label>
                                    <label class="nrt-campaign-radio"><input type="radio" name="availability" value="1-month" checked> Within 1 month</label>
                                    <label class="nrt-campaign-radio"><input type="radio" name="availability" value="3-months"> Within 3 months</label>
                                    <label class="nrt-campaign-radio"><input type="radio" name="availability" value="exploring"> Just exploring</label>
                                </div>
                            </div>
                        </div>

                        <!-- Step 3: Skills -->
                        <div class="nrt-campaign-step" data-step="3" style="display: none;">
                            <h3>What are your key skills?</h3>
                            <p class="nrt-campaign-step-desc">Select the skills that best represent your expertise.</p>
                            <div class="nrt-campaign-field">
                                <label>Key Skills (select all that apply)</label>
                                <div class="nrt-campaign-checkbox-grid nrt-campaign-skills-grid" id="nrt-campaign-skills">
                                    <label class="nrt-campaign-checkbox"><input type="checkbox" value="financial-modeling"> Financial Modeling</label>
                                    <label class="nrt-campaign-checkbox"><input type="checkbox" value="valuation"> Valuation</label>
                                    <label class="nrt-campaign-checkbox"><input type="checkbox" value="ma"> M&A</label>
                                    <label class="nrt-campaign-checkbox"><input type="checkbox" value="dcf"> DCF Analysis</label>
                                    <label class="nrt-campaign-checkbox"><input type="checkbox" value="due-diligence"> Due Diligence</label>
                                    <label class="nrt-campaign-checkbox"><input type="checkbox" value="lbo"> LBO Modeling</label>
                                    <label class="nrt-campaign-checkbox"><input type="checkbox" value="excel"> Advanced Excel</label>
                                    <label class="nrt-campaign-checkbox"><input type="checkbox" value="python"> Python</label>
                                    <label class="nrt-campaign-checkbox"><input type="checkbox" value="sql"> SQL</label>
                                    <label class="nrt-campaign-checkbox"><input type="checkbox" value="powerbi"> PowerBI/Tableau</label>
                                    <label class="nrt-campaign-checkbox"><input type="checkbox" value="deal-execution"> Deal Execution</label>
                                    <label class="nrt-campaign-checkbox"><input type="checkbox" value="client-management"> Client Management</label>
                                </div>
                            </div>
                            <div class="nrt-campaign-field">
                                <label for="nrt-custom-skills">Add Custom Skills</label>
                                <input type="text" id="nrt-custom-skills" placeholder="e.g., Bloomberg Terminal, CFA" class="nrt-campaign-input">
                            </div>
                        </div>

                        <!-- Step 4: CV Upload -->
                        <div class="nrt-campaign-step" data-step="4" style="display: none;">
                            <h3>Upload your CV</h3>
                            <p class="nrt-campaign-step-desc">Your CV helps recruiters understand your background quickly.</p>
                            <div class="nrt-campaign-field">
                                <div class="nrt-campaign-upload-zone" id="nrt-cv-upload-zone">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                        <polyline points="14 2 14 8 20 8"/>
                                        <line x1="12" y1="18" x2="12" y2="12"/>
                                        <line x1="9" y1="15" x2="12" y2="12"/>
                                        <line x1="15" y1="15" x2="12" y2="12"/>
                                    </svg>
                                    <p>Drag & drop your CV here or <span class="nrt-campaign-upload-browse">browse</span></p>
                                    <span class="nrt-campaign-upload-hint">PDF, DOC, DOCX (max 5MB)</span>
                                    <input type="file" id="nrt-cv-file" accept=".pdf,.doc,.docx" style="display: none;">
                                </div>
                                <div class="nrt-campaign-uploaded-file" id="nrt-cv-uploaded" style="display: none;">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                        <polyline points="14 2 14 8 20 8"/>
                                    </svg>
                                    <span class="nrt-campaign-file-name"></span>
                                    <button type="button" class="nrt-campaign-file-remove" aria-label="Remove file">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                            <line x1="18" y1="6" x2="6" y2="18"/>
                                            <line x1="6" y1="6" x2="18" y2="18"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Step 5: Your Pitch -->
                        <div class="nrt-campaign-step" data-step="5" style="display: none;">
                            <h3>Write your pitch</h3>
                            <p class="nrt-campaign-step-desc">This is what recruiters will see first. Make it count!</p>
                            <div class="nrt-campaign-field">
                                <label for="nrt-pitch">Your Pitch</label>
                                <textarea id="nrt-pitch" class="nrt-campaign-textarea" rows="5" maxlength="300" placeholder="CFA charterholder with 8 years of experience in financial modeling and M&A across PE and IB. Led due diligence on $500M+ transactions. Looking for senior roles where I can lead deal teams..."></textarea>
                                <div class="nrt-campaign-char-count"><span id="nrt-pitch-count">0</span>/300</div>
                            </div>
                            <div class="nrt-campaign-tips">
                                <h4>Tips for a great pitch:</h4>
                                <ul>
                                    <li>Lead with your strongest credential</li>
                                    <li>Mention specific achievements with numbers</li>
                                    <li>State what you're looking for clearly</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="nrt-campaign-modal-footer">
                        <button type="button" class="nrt-campaign-btn nrt-campaign-btn--secondary" id="nrt-campaign-back" style="display: none;">
                            Back
                        </button>
                        <button type="button" class="nrt-campaign-btn nrt-campaign-btn--primary" id="nrt-campaign-next">
                            Continue
                        </button>
                    </div>
                </div>
            `;

            // Bind modal events
            this.bindCampaignModalEvents(modal);

            return modal;
        }

        /**
         * Bind Campaign Modal Events
         */
        bindCampaignModalEvents(modal) {
            const backdrop = modal.querySelector('.nrt-campaign-modal-backdrop');
            const closeBtn = modal.querySelector('.nrt-campaign-modal-close');
            const backBtn = modal.querySelector('#nrt-campaign-back');
            const nextBtn = modal.querySelector('#nrt-campaign-next');
            const pitchTextarea = modal.querySelector('#nrt-pitch');
            const cvUploadZone = modal.querySelector('#nrt-cv-upload-zone');
            const cvFileInput = modal.querySelector('#nrt-cv-file');

            // Close modal
            backdrop.addEventListener('click', () => this.closeCampaignSetupModal());
            closeBtn.addEventListener('click', () => this.closeCampaignSetupModal());

            // Navigation
            backBtn.addEventListener('click', () => {
                if (this.campaignSetupStep > 1) {
                    this.campaignSetupStep--;
                    this.updateCampaignSetupStep();
                }
            });

            nextBtn.addEventListener('click', () => {
                if (this.campaignSetupStep < 5) {
                    this.campaignSetupStep++;
                    this.updateCampaignSetupStep();
                } else {
                    this.submitCampaign();
                }
            });

            // Pitch character count
            if (pitchTextarea) {
                pitchTextarea.addEventListener('input', () => {
                    const count = pitchTextarea.value.length;
                    modal.querySelector('#nrt-pitch-count').textContent = count;
                });
            }

            // CV Upload
            if (cvUploadZone && cvFileInput) {
                cvUploadZone.addEventListener('click', () => cvFileInput.click());
                cvUploadZone.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    cvUploadZone.classList.add('is-dragover');
                });
                cvUploadZone.addEventListener('dragleave', () => {
                    cvUploadZone.classList.remove('is-dragover');
                });
                cvUploadZone.addEventListener('drop', (e) => {
                    e.preventDefault();
                    cvUploadZone.classList.remove('is-dragover');
                    const file = e.dataTransfer.files[0];
                    if (file) this.handleCVUpload(file);
                });
                cvFileInput.addEventListener('change', (e) => {
                    const file = e.target.files[0];
                    if (file) this.handleCVUpload(file);
                });
            }
        }

        /**
         * Update Campaign Setup Step
         */
        updateCampaignSetupStep() {
            const modal = document.getElementById('nrt-campaign-setup-modal');
            if (!modal) return;

            const steps = modal.querySelectorAll('.nrt-campaign-step');
            const backBtn = modal.querySelector('#nrt-campaign-back');
            const nextBtn = modal.querySelector('#nrt-campaign-next');
            const progressFill = modal.querySelector('.nrt-campaign-progress-fill');
            const stepIndicator = modal.querySelector('.nrt-campaign-step-indicator');

            // Show/hide steps
            steps.forEach(step => {
                step.style.display = parseInt(step.dataset.step) === this.campaignSetupStep ? '' : 'none';
            });

            // Update progress
            progressFill.style.width = `${(this.campaignSetupStep / 5) * 100}%`;
            stepIndicator.textContent = `Step ${this.campaignSetupStep} of 5`;

            // Show/hide back button
            backBtn.style.display = this.campaignSetupStep > 1 ? '' : 'none';

            // Update next button text
            nextBtn.textContent = this.campaignSetupStep === 5 ? 'Launch Campaign' : 'Continue';
        }

        /**
         * Handle CV Upload
         */
        handleCVUpload(file) {
            const modal = document.getElementById('nrt-campaign-setup-modal');
            const uploadZone = modal.querySelector('#nrt-cv-upload-zone');
            const uploadedFile = modal.querySelector('#nrt-cv-uploaded');
            const fileName = modal.querySelector('.nrt-campaign-file-name');
            const removeBtn = modal.querySelector('.nrt-campaign-file-remove');

            // Validate file
            const validTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            if (!validTypes.includes(file.type)) {
                alert('Please upload a PDF or Word document.');
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                alert('File size must be less than 5MB.');
                return;
            }

            // Store file reference
            this.campaignCV = file;

            // Update UI
            uploadZone.style.display = 'none';
            uploadedFile.style.display = 'flex';
            fileName.textContent = file.name;

            // Bind remove
            removeBtn.onclick = () => {
                this.campaignCV = null;
                uploadZone.style.display = '';
                uploadedFile.style.display = 'none';
            };
        }

        /**
         * Close Campaign Setup Modal
         */
        closeCampaignSetupModal() {
            const modal = document.getElementById('nrt-campaign-setup-modal');
            if (modal) {
                modal.classList.remove('is-visible');
                document.body.style.overflow = '';
            }
        }

        /**
         * Submit Campaign
         */
        submitCampaign() {
            // For logged-out users, show the welcome modal instead of submitting
            if (!this.isLoggedIn) {
                this.closeCampaignSetupModal();
                this.showWelcomeModal('recruiter-intros');
                return;
            }

            const modal = document.getElementById('nrt-campaign-setup-modal');

            // Collect form data
            const campaignData = {
                target_roles: modal.querySelector('#nrt-target-roles')?.value || '',
                target_industries: Array.from(modal.querySelectorAll('#nrt-target-industries input:checked')).map(i => i.value),
                target_locations: Array.from(modal.querySelectorAll('#nrt-target-locations input:checked')).map(i => i.value),
                salary_min: modal.querySelector('#nrt-salary-min')?.value || '',
                salary_max: modal.querySelector('#nrt-salary-max')?.value || '',
                availability: modal.querySelector('input[name="availability"]:checked')?.value || '',
                skills: Array.from(modal.querySelectorAll('#nrt-campaign-skills input:checked')).map(i => i.value),
                custom_skills: modal.querySelector('#nrt-custom-skills')?.value || '',
                pitch: modal.querySelector('#nrt-pitch')?.value || ''
            };

            // Show loading state
            const nextBtn = modal.querySelector('#nrt-campaign-next');
            nextBtn.disabled = true;
            nextBtn.innerHTML = '<span class="nrt-loading-spinner-small"></span> Launching...';

            // Submit via AJAX (placeholder for now)
            setTimeout(() => {
                this.closeCampaignSetupModal();
                this.showCampaignSuccess();
            }, 1500);
        }

        /**
         * Show Campaign Success
         */
        showCampaignSuccess() {
            // Update the recruiter intros tab to show active campaign state
            const setupSection = document.getElementById('nrt-intros-setup');
            const activeSection = document.getElementById('nrt-intros-active');
            const statusBadge = document.querySelector('#nrt-campaign-status .nrt-status-badge');

            if (setupSection) setupSection.style.display = 'none';
            if (activeSection) activeSection.style.display = '';
            if (statusBadge) {
                statusBadge.className = 'nrt-status-badge nrt-status-active';
                statusBadge.textContent = 'Campaign Active';
            }

            // Load submissions for the "Sent To" section
            this.loadSubmissions();

            // Update intros view in right panel
            const introsView = document.getElementById('nrt-intros-view');
            if (introsView) {
                introsView.innerHTML = `
                    <div class="nrt-intros-success">
                        <div class="nrt-intros-success-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="64" height="64">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                <polyline points="22 4 12 14.01 9 11.01"/>
                            </svg>
                        </div>
                        <h3>Your Campaign is Live!</h3>
                        <p>We're now sending your profile to recruiters matching your criteria. You'll see opportunities appear as recruiters express interest.</p>
                        <div class="nrt-intros-success-stats">
                            <div class="nrt-intros-success-stat">
                                <span class="nrt-intros-success-value">127</span>
                                <span class="nrt-intros-success-label">Target Recruiters</span>
                            </div>
                            <div class="nrt-intros-success-stat">
                                <span class="nrt-intros-success-value">48h</span>
                                <span class="nrt-intros-success-label">Expected Response</span>
                            </div>
                        </div>
                    </div>
                `;
            }
        }

        /**
         * Toggle Campaign Pause
         */
        toggleCampaignPause() {
            const statusBadge = document.querySelector('#nrt-campaign-status .nrt-status-badge');
            const pauseBtn = document.getElementById('nrt-pause-campaign-btn');

            if (statusBadge.classList.contains('nrt-status-active')) {
                statusBadge.className = 'nrt-status-badge nrt-status-paused';
                statusBadge.textContent = 'Campaign Paused';
                pauseBtn.innerHTML = `
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                        <polygon points="5 3 19 12 5 21 5 3"/>
                    </svg>
                    Resume Campaign
                `;
            } else {
                statusBadge.className = 'nrt-status-badge nrt-status-active';
                statusBadge.textContent = 'Campaign Active';
                pauseBtn.innerHTML = `
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                        <rect x="6" y="4" width="4" height="16"/>
                        <rect x="14" y="4" width="4" height="16"/>
                    </svg>
                    Pause Campaign
                `;
            }
        }

        /**
         * Load Submissions (who received the profile)
         */
        loadSubmissions(page = 1) {
            const listContainer = document.getElementById('nrt-sent-list');
            const countEl = document.getElementById('nrt-sent-count');

            if (!listContainer) return;

            // Show loading on first page
            if (page === 1) {
                listContainer.innerHTML = `
                    <div class="nrt-intros-sent-loading">
                        <div class="nrt-loading-spinner"></div>
                        <span>Loading submissions...</span>
                    </div>
                `;
            }

            fetch(this.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'sffc_get_submissions',
                    nonce: this.nonce,
                    page: page
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.data) {
                    const { submissions, stats, has_more } = data.data;

                    // Update count
                    if (countEl && stats) {
                        countEl.textContent = `${stats.total} recruiter${stats.total !== 1 ? 's' : ''}`;

                        // Also update stats section
                        const sentEl = document.getElementById('nrt-intros-sent');
                        const viewsEl = document.getElementById('nrt-intros-views');
                        const responsesEl = document.getElementById('nrt-intros-responses');

                        if (sentEl) sentEl.textContent = stats.total || 0;
                        if (viewsEl) viewsEl.textContent = stats.viewed || 0;
                        if (responsesEl) responsesEl.textContent = stats.responded || 0;
                    }

                    // Render submissions
                    if (page === 1) {
                        listContainer.innerHTML = '';
                    } else {
                        // Remove load more button if exists
                        const loadMoreBtn = listContainer.querySelector('.nrt-submissions-load-more');
                        if (loadMoreBtn) loadMoreBtn.remove();
                    }

                    if (submissions.length === 0 && page === 1) {
                        listContainer.innerHTML = `
                            <div class="nrt-intros-sent-empty">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="40" height="40">
                                    <path d="M22 2L11 13"/>
                                    <path d="M22 2l-7 20-4-9-9-4 20-7z"/>
                                </svg>
                                <p>Your profile hasn't been sent to any recruiters yet.<br>We'll update this as outreach begins.</p>
                            </div>
                        `;
                        return;
                    }

                    // Render each submission card
                    submissions.forEach(sub => {
                        listContainer.insertAdjacentHTML('beforeend', this.renderSubmissionCard(sub));
                    });

                    // Add load more button if there are more
                    if (has_more) {
                        listContainer.insertAdjacentHTML('beforeend', `
                            <button type="button" class="nrt-submissions-load-more" data-page="${page + 1}">
                                Load More
                            </button>
                        `);
                    }
                }
            })
            .catch(err => {
                console.error('Error loading submissions:', err);
                if (page === 1) {
                    listContainer.innerHTML = `
                        <div class="nrt-intros-sent-empty">
                            <p>Failed to load submissions. Please try again.</p>
                        </div>
                    `;
                }
            });
        }

        /**
         * Render a single submission card
         */
        renderSubmissionCard(sub) {
            const rec = sub.recruiter;
            const initials = rec.name ? rec.name.split(' ').map(n => n[0]).join('').slice(0, 2) : '?';

            const statusLabels = {
                'sent': 'Sent',
                'viewed': 'Viewed',
                'responded': 'Responded',
                'no_response': 'No Response',
                'declined': 'Declined'
            };

            const specialtiesHtml = rec.specialties && rec.specialties.length > 0
                ? `<div class="nrt-submission-specialties">
                    ${rec.specialties.slice(0, 3).map(s => `<span class="nrt-submission-specialty">${this.escapeHtml(s)}</span>`).join('')}
                   </div>`
                : '';

            const linkedinHtml = rec.linkedin
                ? `<a href="${this.escapeHtml(rec.linkedin)}" target="_blank" rel="noopener" class="nrt-submission-linkedin">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                    LinkedIn
                   </a>`
                : '';

            return `
                <div class="nrt-submission-card" data-submission-id="${sub.id}">
                    <div class="nrt-submission-photo">
                        ${rec.photo
                            ? `<img src="${this.escapeHtml(rec.photo)}" alt="${this.escapeHtml(rec.name)}">`
                            : `<span class="nrt-submission-initials">${initials}</span>`
                        }
                    </div>
                    <div class="nrt-submission-info">
                        <p class="nrt-submission-name">${this.escapeHtml(rec.name || 'Unknown Recruiter')}</p>
                        <p class="nrt-submission-title">${this.escapeHtml(rec.title || '')}${rec.title && rec.company ? ' at ' : ''}${this.escapeHtml(rec.company || '')}</p>
                        <div class="nrt-submission-meta">
                            <span>${sub.time_ago}</span>
                        </div>
                        ${specialtiesHtml}
                    </div>
                    <div class="nrt-submission-status">
                        <span class="nrt-status-badge nrt-status-${sub.status}">${statusLabels[sub.status] || sub.status}</span>
                        ${linkedinHtml}
                    </div>
                </div>
            `;
        }

        /**
         * Initialize Opportunities Tab
         */
        initOpportunitiesTab() {
            if (this.opportunitiesInitialized) return;
            this.opportunitiesInitialized = true;

            // Store opportunities data
            this.opportunities = [];
            this.currentFilter = 'all';
            this.selectedOpportunityId = null;

            // Load opportunities
            this.loadOpportunities();

            // Bind filter buttons
            const filterBtns = document.querySelectorAll('.nrt-opp-filter-btn');
            filterBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    filterBtns.forEach(b => b.classList.remove('is-active'));
                    btn.classList.add('is-active');
                    this.currentFilter = btn.dataset.oppFilter;
                    this.renderOpportunitiesList();
                });
            });

            // Bind "Launch Campaign" CTA button
            const ctaBtn = document.querySelector('.nrt-opportunities-cta-btn[data-action="go-to-intros"]');
            if (ctaBtn) {
                ctaBtn.addEventListener('click', () => {
                    this.switchTab('recruiter-intros');
                });
            }

            // Bind opportunity detail actions
            this.bindOpportunityDetailActions();
        }

        /**
         * Load opportunities data
         */
        loadOpportunities() {
            const loading = document.getElementById('nrt-opportunities-loading');
            const empty = document.getElementById('nrt-opportunities-empty');
            const list = document.getElementById('nrt-opportunities-list');

            // Show loading
            if (loading) loading.style.display = '';
            if (empty) empty.style.display = 'none';

            // Fetch real opportunities from server
            fetch(this.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'nrt_load_opportunities',
                    nonce: this.nonce
                })
            })
            .then(res => res.json())
            .then(response => {
                if (loading) loading.style.display = 'none';

                if (response.success && response.data && response.data.length > 0) {
                    this.opportunities = response.data;
                    this.renderOpportunitiesList();
                } else {
                    this.opportunities = [];
                    if (empty) empty.style.display = '';
                }
            })
            .catch(err => {
                console.error('Error loading opportunities:', err);
                if (loading) loading.style.display = 'none';
                if (empty) empty.style.display = '';
                this.opportunities = [];
            });
        }

        /**
         * Render opportunities list based on current filter
         */
        renderOpportunitiesList() {
            const list = document.getElementById('nrt-opportunities-list');
            const empty = document.getElementById('nrt-opportunities-empty');

            if (!list) return;

            // Filter opportunities
            let filtered = this.opportunities;
            if (this.currentFilter === 'new') {
                filtered = this.opportunities.filter(o => o.isNew);
            } else if (this.currentFilter === 'saved') {
                filtered = this.opportunities.filter(o => o.isSaved);
            }

            if (filtered.length === 0) {
                list.innerHTML = '';
                if (empty) empty.style.display = '';
                return;
            }

            if (empty) empty.style.display = 'none';

            // Render cards
            list.innerHTML = filtered.map((opp, index) => this.renderOpportunityCard(opp, index)).join('');

            // Bind click handlers
            list.querySelectorAll('.nrt-opportunity-card').forEach(card => {
                card.addEventListener('click', () => {
                    const oppId = parseInt(card.dataset.opportunityId);
                    this.selectOpportunity(oppId);

                    // Update active state
                    list.querySelectorAll('.nrt-opportunity-card').forEach(c => c.classList.remove('is-active'));
                    card.classList.add('is-active');
                });
            });
        }

        /**
         * Render a single opportunity card
         */
        renderOpportunityCard(opp, index = 0) {
            const initial = (opp.company || 'C').charAt(0).toUpperCase();
            const sourceLabel = {
                'recruiter_interest': 'Recruiter interested',
                'profile_view': 'Viewed your profile',
                'campaign_match': 'Campaign match'
            }[opp.source] || 'Opportunity';

            // Lock all except first for logged-out users
            const isLocked = !this.isLoggedIn && index > 0;
            const lockIcon = isLocked ? `<div class="nrt-story-lock"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div>` : '';

            return `
                <div class="nrt-opportunity-card ${opp.isNew ? 'is-new' : ''} ${this.selectedOpportunityId === opp.id ? 'is-active' : ''} ${isLocked ? 'is-locked' : ''}" data-opportunity-id="${opp.id}" ${isLocked ? 'data-locked="true"' : ''}>
                    ${opp.isNew ? '<span class="nrt-opp-card-badge">NEW</span>' : ''}
                    <div class="nrt-opp-card-header">
                        <div class="nrt-opp-card-logo">
                            <span>${initial}</span>
                        </div>
                        <div class="nrt-opp-card-info">
                            <h4 class="nrt-opp-card-title">${opp.title || ''}</h4>
                            <p class="nrt-opp-card-company">${opp.company || ''}</p>
                        </div>
                    </div>
                    <p class="nrt-opp-card-source">${opp.recruiter?.name || 'Recruiter'} • ${sourceLabel}</p>
                    <span class="nrt-opp-card-time">${opp.time || ''}</span>
                    ${lockIcon}
                </div>
            `;
        }

        /**
         * Select and display opportunity details
         */
        selectOpportunity(oppId) {
            const opp = this.opportunities.find(o => o.id === oppId);
            if (!opp) return;

            this.selectedOpportunityId = oppId;

            // Hide placeholder, show content
            const placeholder = document.getElementById('nrt-opportunity-placeholder');
            const content = document.getElementById('nrt-opportunity-content');

            if (placeholder) placeholder.style.display = 'none';
            if (content) content.style.display = '';

            // Populate content
            const companyInitial = (opp.company || 'C').charAt(0).toUpperCase();
            const recruiterInitial = (opp.recruiter?.name || 'R').charAt(0).toUpperCase();

            document.querySelector('#nrt-opp-company-logo .nrt-opp-company-initial').textContent = companyInitial;
            document.getElementById('nrt-opp-title').textContent = opp.title || '';
            document.getElementById('nrt-opp-company').textContent = opp.company || '';
            document.getElementById('nrt-opp-location').textContent = opp.location || '';
            document.getElementById('nrt-opp-salary').textContent = opp.salary || '';

            const badge = document.getElementById('nrt-opp-badge');
            if (badge) {
                badge.style.display = opp.isNew ? '' : 'none';
            }

            document.querySelector('#nrt-opp-recruiter-avatar .nrt-opp-recruiter-initial').textContent = recruiterInitial;
            document.getElementById('nrt-opp-recruiter-name').textContent = opp.recruiter?.name || '';
            document.getElementById('nrt-opp-recruiter-title').textContent = opp.recruiter?.title || '';
            document.getElementById('nrt-opp-recruiter-company').textContent = opp.recruiter?.company || '';
            document.getElementById('nrt-opp-recruiter-status').textContent = opp.recruiter?.status || '';

            // Populate match reasons
            const matchList = document.getElementById('nrt-opp-match-list');
            if (matchList && Array.isArray(opp.matchReasons)) {
                matchList.innerHTML = opp.matchReasons.map(reason => `
                    <li class="nrt-opp-match-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        <span>${reason}</span>
                    </li>
                `).join('');
            }

            // Update save button state
            const saveBtn = document.querySelector('.nrt-opp-text-btn[data-action="save-opportunity"]');
            if (saveBtn) {
                if (opp.isSaved) {
                    saveBtn.innerHTML = `
                        <svg viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" width="16" height="16">
                            <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
                        </svg>
                        Saved
                    `;
                } else {
                    saveBtn.innerHTML = `
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                            <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
                        </svg>
                        Save for Later
                    `;
                }
            }

            // Mark as viewed (no longer new)
            if (opp.isNew) {
                opp.isNew = false;
                this.renderOpportunitiesList();
            }
        }

        /**
         * Bind opportunity detail action buttons
         */
        bindOpportunityDetailActions() {
            const content = document.getElementById('nrt-opportunity-content');
            if (!content) return;

            content.addEventListener('click', (e) => {
                const actionBtn = e.target.closest('[data-action]');
                if (!actionBtn) return;

                const action = actionBtn.dataset.action;
                const opp = this.opportunities.find(o => o.id === this.selectedOpportunityId);

                switch (action) {
                    case 'start-chat':
                        // Switch to replies tab and start conversation
                        this.showToast('Starting conversation with ' + (opp?.recruiter?.name || 'recruiter'), 'info');
                        this.switchTab('replies');
                        break;

                    case 'view-job':
                        // Open job details (would navigate to job listing)
                        this.showToast('Opening job details...', 'info');
                        break;

                    case 'save-opportunity':
                        if (opp) {
                            const newSaved = !opp.isSaved;
                            opp.isSaved = newSaved;
                            this.selectOpportunity(opp.id); // Refresh display
                            this.renderOpportunitiesList();
                            this.showToast(newSaved ? 'Opportunity saved' : 'Removed from saved', 'success');

                            // Persist via AJAX
                            fetch(this.ajaxUrl, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: new URLSearchParams({
                                    action: 'nrt_save_opportunity',
                                    nonce: this.nonce,
                                    opportunity_id: opp.id,
                                    saved: newSaved ? '1' : '0'
                                })
                            }).catch(err => console.error('Error saving opportunity:', err));
                        }
                        break;

                    case 'not-interested':
                        if (opp) {
                            // Remove from list immediately
                            this.opportunities = this.opportunities.filter(o => o.id !== opp.id);
                            this.renderOpportunitiesList();
                            // Reset detail view
                            document.getElementById('nrt-opportunity-placeholder').style.display = '';
                            document.getElementById('nrt-opportunity-content').style.display = 'none';
                            this.selectedOpportunityId = null;
                            this.showToast('Opportunity dismissed', 'info');

                            // Persist via AJAX
                            fetch(this.ajaxUrl, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: new URLSearchParams({
                                    action: 'nrt_dismiss_opportunity',
                                    nonce: this.nonce,
                                    opportunity_id: opp.id
                                })
                            }).catch(err => console.error('Error dismissing opportunity:', err));
                        }
                        break;
                }
            });
        }

        /**
         * Initialize Replies Tab
         */
        initRepliesTab() {
            if (this.repliesInitialized) return;
            this.repliesInitialized = true;

            // Store conversations data
            this.conversations = [];
            this.currentConversationFilter = 'all';
            this.selectedConversationId = null;

            // Load conversations
            this.loadConversations();

            // Bind filter buttons
            const filterBtns = document.querySelectorAll('.nrt-reply-filter-btn');
            filterBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    filterBtns.forEach(b => b.classList.remove('is-active'));
                    btn.classList.add('is-active');
                    this.currentConversationFilter = btn.dataset.replyFilter;
                    this.renderConversationsList();
                });
            });

            // Bind conversation actions
            this.bindConversationActions();
        }

        /**
         * Load conversations data via AJAX
         */
        loadConversations() {
            const loading = document.getElementById('nrt-replies-loading');
            const empty = document.getElementById('nrt-replies-empty');
            const list = document.getElementById('nrt-replies-list');

            if (loading) loading.style.display = '';
            if (empty) empty.style.display = 'none';

            fetch(this.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'nrt_load_conversations',
                    nonce: this.nonce
                })
            })
            .then(res => res.json())
            .then(response => {
                if (loading) loading.style.display = 'none';

                if (response.success && response.data && response.data.length > 0) {
                    this.conversations = response.data;
                    this.renderConversationsList();
                } else {
                    this.conversations = [];
                    if (list) list.innerHTML = '';
                    if (empty) empty.style.display = '';
                }
            })
            .catch(err => {
                console.error('Error loading conversations:', err);
                if (loading) loading.style.display = 'none';
                if (empty) empty.style.display = '';
                this.conversations = [];
            });
        }

        /**
         * Render conversations list based on current filter
         */
        renderConversationsList() {
            const list = document.getElementById('nrt-replies-list');
            const empty = document.getElementById('nrt-replies-empty');

            if (!list) return;

            // Filter conversations
            let filtered = this.conversations;
            if (this.currentConversationFilter === 'unread') {
                filtered = this.conversations.filter(c => c.isUnread);
            } else if (this.currentConversationFilter === 'starred') {
                filtered = this.conversations.filter(c => c.isStarred);
            }

            if (filtered.length === 0) {
                list.innerHTML = '';
                if (empty) empty.style.display = '';
                return;
            }

            if (empty) empty.style.display = 'none';

            // Render cards
            list.innerHTML = filtered.map((conv, index) => this.renderConversationCard(conv, index)).join('');

            // Bind click handlers
            list.querySelectorAll('.nrt-conversation-card').forEach(card => {
                card.addEventListener('click', () => {
                    // Show welcome modal for locked conversations
                    if (card.dataset.locked === 'true') {
                        this.showWelcomeModal('replies');
                        return;
                    }

                    const convId = parseInt(card.dataset.conversationId);
                    this.selectConversation(convId);

                    // Update active state
                    list.querySelectorAll('.nrt-conversation-card').forEach(c => c.classList.remove('is-active'));
                    card.classList.add('is-active');
                });
            });
        }

        /**
         * Render a single conversation card
         */
        renderConversationCard(conv, index = 0) {
            const contactName = conv.contact?.name || 'Contact';
            const initial = contactName.charAt(0).toUpperCase();
            const lastMsg = conv.lastMessage || '';
            const truncatedMessage = lastMsg.length > 60
                ? lastMsg.substring(0, 60) + '...'
                : lastMsg;

            // Lock all except first for logged-out users
            const isLocked = !this.isLoggedIn && index > 0;
            const lockIcon = isLocked ? `<div class="nrt-story-lock"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div>` : '';

            return `
                <div class="nrt-conversation-card ${conv.isUnread ? 'is-unread' : ''} ${this.selectedConversationId === conv.id ? 'is-active' : ''} ${isLocked ? 'is-locked' : ''}" data-conversation-id="${conv.id}" ${isLocked ? 'data-locked="true"' : ''}>
                    <div class="nrt-conv-card-avatar">
                        <span>${initial}</span>
                        ${conv.isUnread ? '<span class="nrt-conv-card-unread-dot"></span>' : ''}
                    </div>
                    <div class="nrt-conv-card-content">
                        <div class="nrt-conv-card-header">
                            <span class="nrt-conv-card-name">${contactName}</span>
                            <span class="nrt-conv-card-time">${conv.time || ''}</span>
                        </div>
                        <p class="nrt-conv-card-company">${conv.contact?.company || ''}</p>
                        <p class="nrt-conv-card-preview">${truncatedMessage}</p>
                    </div>
                    ${conv.isStarred ? '<span class="nrt-conv-card-star"><svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></span>' : ''}
                    ${lockIcon}
                </div>
            `;
        }

        /**
         * Select and display conversation messages
         */
        selectConversation(convId) {
            const conv = this.conversations.find(c => c.id === convId);
            if (!conv) return;

            this.selectedConversationId = convId;

            // Hide placeholder, show content
            const placeholder = document.getElementById('nrt-conversation-placeholder');
            const content = document.getElementById('nrt-conversation-content');

            if (placeholder) placeholder.style.display = 'none';
            if (content) content.style.display = '';

            // Populate header
            const contactName = conv.contact?.name || 'Contact';
            const initial = contactName.charAt(0).toUpperCase();
            document.querySelector('#nrt-conv-avatar .nrt-conv-avatar-initial').textContent = initial;
            document.getElementById('nrt-conv-name').textContent = contactName;
            document.getElementById('nrt-conv-role').textContent = `${conv.contact?.title || ''} at ${conv.contact?.company || ''}`;

            // Load messages via AJAX
            const messagesContainer = document.getElementById('nrt-conv-messages');
            if (messagesContainer) {
                messagesContainer.innerHTML = '<div class="nrt-conv-loading">Loading messages...</div>';

                fetch(this.ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'nrt_load_conversation_messages',
                        nonce: this.nonce,
                        conversation_id: convId
                    })
                })
                .then(res => res.json())
                .then(response => {
                    if (response.success && response.data) {
                        conv.messages = response.data;
                        messagesContainer.innerHTML = conv.messages.map(msg => `
                            <div class="nrt-conv-message ${msg.from === 'me' ? 'nrt-conv-message--sent' : 'nrt-conv-message--received'}">
                                <div class="nrt-conv-message-bubble">
                                    <p>${msg.text}</p>
                                    <span class="nrt-conv-message-time">${msg.time}</span>
                                </div>
                            </div>
                        `).join('');

                        // Scroll to bottom
                        messagesContainer.scrollTop = messagesContainer.scrollHeight;
                    } else {
                        messagesContainer.innerHTML = '<div class="nrt-conv-empty">No messages yet</div>';
                        conv.messages = [];
                    }
                })
                .catch(err => {
                    console.error('Error loading messages:', err);
                    messagesContainer.innerHTML = '<div class="nrt-conv-error">Failed to load messages</div>';
                });
            }

            // Update star button state
            const starBtn = document.querySelector('.nrt-conv-action-btn[data-action="star-conversation"]');
            if (starBtn) {
                const svg = starBtn.querySelector('svg');
                if (conv.isStarred) {
                    svg.setAttribute('fill', 'currentColor');
                    starBtn.classList.add('is-starred');
                } else {
                    svg.setAttribute('fill', 'none');
                    starBtn.classList.remove('is-starred');
                }
            }

            // Mark as read via AJAX
            if (conv.isUnread) {
                conv.isUnread = false;
                this.renderConversationsList();

                fetch(this.ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'nrt_mark_conversation_read',
                        nonce: this.nonce,
                        conversation_id: convId
                    })
                }).catch(err => console.error('Error marking conversation read:', err));
            }
        }

        /**
         * Bind conversation action handlers
         */
        bindConversationActions() {
            // Message input handling
            const input = document.getElementById('nrt-conv-input');
            const sendBtn = document.getElementById('nrt-conv-send');

            if (input && sendBtn) {
                input.addEventListener('input', () => {
                    sendBtn.disabled = input.value.trim().length === 0;
                    // Auto-resize textarea
                    input.style.height = 'auto';
                    input.style.height = Math.min(input.scrollHeight, 120) + 'px';
                });

                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        if (input.value.trim()) {
                            this.sendMessage(input.value.trim());
                            input.value = '';
                            input.style.height = 'auto';
                            sendBtn.disabled = true;
                        }
                    }
                });

                sendBtn.addEventListener('click', () => {
                    if (input.value.trim()) {
                        this.sendMessage(input.value.trim());
                        input.value = '';
                        input.style.height = 'auto';
                        sendBtn.disabled = true;
                    }
                });
            }

            // Header actions
            const content = document.getElementById('nrt-conversation-content');
            if (content) {
                content.addEventListener('click', (e) => {
                    const actionBtn = e.target.closest('[data-action]');
                    if (!actionBtn) return;

                    const action = actionBtn.dataset.action;
                    const conv = this.conversations.find(c => c.id === this.selectedConversationId);

                    switch (action) {
                        case 'star-conversation':
                            if (conv) {
                                const newStarred = !conv.isStarred;
                                conv.isStarred = newStarred;

                                // Update UI immediately
                                const starBtn = document.querySelector('.nrt-conv-action-btn[data-action="star-conversation"]');
                                if (starBtn) {
                                    const svg = starBtn.querySelector('svg');
                                    if (newStarred) {
                                        svg.setAttribute('fill', 'currentColor');
                                        starBtn.classList.add('is-starred');
                                    } else {
                                        svg.setAttribute('fill', 'none');
                                        starBtn.classList.remove('is-starred');
                                    }
                                }
                                this.renderConversationsList();
                                this.showToast(newStarred ? 'Conversation starred' : 'Star removed', 'success');

                                // Persist via AJAX
                                fetch(this.ajaxUrl, {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                    body: new URLSearchParams({
                                        action: 'nrt_toggle_conversation_star',
                                        nonce: this.nonce,
                                        conversation_id: conv.id,
                                        starred: newStarred ? '1' : '0'
                                    })
                                }).catch(err => console.error('Error toggling star:', err));
                            }
                            break;

                        case 'view-profile':
                            this.showToast('Opening recruiter profile...', 'info');
                            break;

                        case 'schedule-call':
                            this.showToast('Opening calendar to schedule call...', 'info');
                            break;

                        case 'share-availability':
                            this.showToast('Sharing your availability...', 'info');
                            break;

                        case 'save-to-contacts':
                            if (conv) {
                                this.showToast(`${conv.contact.name} saved to your contacts`, 'success');
                                // In production, this would call an AJAX endpoint to save the contact
                            }
                            break;
                    }
                });
            }
        }

        /**
         * Send a new message via AJAX
         */
        sendMessage(text) {
            const conv = this.conversations.find(c => c.id === this.selectedConversationId);
            if (!conv) return;

            // Optimistically add message to UI
            const tempMessage = {
                id: 'temp-' + Date.now(),
                from: 'me',
                text: text,
                time: 'Sending...'
            };

            if (!conv.messages) conv.messages = [];
            conv.messages.push(tempMessage);

            const messagesContainer = document.getElementById('nrt-conv-messages');
            if (messagesContainer) {
                messagesContainer.innerHTML += `
                    <div class="nrt-conv-message nrt-conv-message--sent nrt-conv-message--sending" data-temp-id="${tempMessage.id}">
                        <div class="nrt-conv-message-bubble">
                            <p>${text}</p>
                            <span class="nrt-conv-message-time">Sending...</span>
                        </div>
                    </div>
                `;
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }

            // Send via AJAX
            fetch(this.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'nrt_send_message',
                    nonce: this.nonce,
                    conversation_id: this.selectedConversationId,
                    message: text
                })
            })
            .then(res => res.json())
            .then(response => {
                if (response.success) {
                    // Update temp message with real data
                    const tempEl = messagesContainer?.querySelector(`[data-temp-id="${tempMessage.id}"]`);
                    if (tempEl) {
                        tempEl.classList.remove('nrt-conv-message--sending');
                        tempEl.removeAttribute('data-temp-id');
                        const timeEl = tempEl.querySelector('.nrt-conv-message-time');
                        if (timeEl) timeEl.textContent = 'Just now';
                    }

                    // Update conversation in list
                    conv.lastMessage = text;
                    conv.time = 'Just now';
                    this.renderConversationsList();

                    this.showToast('Message sent', 'success');
                } else {
                    // Remove failed message from UI
                    const tempEl = messagesContainer?.querySelector(`[data-temp-id="${tempMessage.id}"]`);
                    if (tempEl) tempEl.remove();
                    conv.messages = conv.messages.filter(m => m.id !== tempMessage.id);
                    this.showToast('Failed to send message', 'error');
                }
            })
            .catch(err => {
                console.error('Error sending message:', err);
                const tempEl = messagesContainer?.querySelector(`[data-temp-id="${tempMessage.id}"]`);
                if (tempEl) tempEl.remove();
                conv.messages = conv.messages.filter(m => m.id !== tempMessage.id);
                this.showToast('Failed to send message', 'error');
            });
        }

        /**
         * Initialize Profile Networking
         */
        initProfileNetworking() {
            if (this.profileNetworkingInitialized) {
                return;
            }
            this.profileNetworkingInitialized = true;

            // Load networking stats and data
            this.loadNetworkingStats();
            this.loadSavedContacts();
            this.loadTargetCompanies();
            this.loadOutreachLog();
        }

        /**
         * Load networking stats for profile
         */
        loadNetworkingStats() {
            fetch(this.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'nrt_get_networking_stats',
                    nonce: this.nonce
                })
            })
            .then(res => res.json())
            .then(response => {
                if (response.success) {
                    const data = response.data;
                    const savedEl = document.getElementById('nrt-stat-saved-contacts');
                    const targetsEl = document.getElementById('nrt-stat-target-companies');
                    const outreachEl = document.getElementById('nrt-stat-outreach');

                    if (savedEl) savedEl.textContent = data.saved_contacts || 0;
                    if (targetsEl) targetsEl.textContent = data.target_companies || 0;
                    if (outreachEl) outreachEl.textContent = data.outreach_count || 0;
                }
            })
            .catch(err => console.error('Error loading networking stats:', err));
        }

        /**
         * Load saved contacts
         */
        loadSavedContacts() {
            const container = document.getElementById('nrt-saved-contacts-list');
            if (!container) return;

            fetch(this.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'nrt_get_saved_contacts',
                    nonce: this.nonce
                })
            })
            .then(res => res.json())
            .then(response => {
                if (response.success && response.data.html) {
                    container.innerHTML = response.data.html;
                    this.bindSavedContactEvents();
                } else {
                    container.innerHTML = '<div class="nrt-profile-empty"><p>Save contacts from the Contacts tab to track them here.</p></div>';
                }
            })
            .catch(err => {
                console.error('Error loading saved contacts:', err);
                container.innerHTML = '<div class="nrt-profile-empty"><p>Failed to load saved contacts.</p></div>';
            });
        }

        /**
         * Bind events for saved contact cards
         */
        bindSavedContactEvents() {
            document.querySelectorAll('.nrt-unsave-contact-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const contactId = btn.dataset.contactId;
                    this.unsaveContact(contactId);
                });
            });
        }

        /**
         * Unsave a contact
         */
        unsaveContact(contactId) {
            fetch(this.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'nrt_unsave_contact',
                    nonce: this.nonce,
                    contact_id: contactId
                })
            })
            .then(res => res.json())
            .then(response => {
                if (response.success) {
                    // Reload saved contacts
                    this.loadSavedContacts();
                    this.loadNetworkingStats();
                }
            })
            .catch(err => console.error('Error unsaving contact:', err));
        }

        /**
         * Load target companies
         */
        loadTargetCompanies() {
            const container = document.getElementById('nrt-target-companies-list');
            if (!container) return;

            fetch(this.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'nrt_get_target_companies',
                    nonce: this.nonce
                })
            })
            .then(res => res.json())
            .then(response => {
                if (response.success && response.data.html) {
                    container.innerHTML = response.data.html;
                    this.bindTargetCompanyEvents();
                } else {
                    container.innerHTML = '<div class="nrt-profile-empty"><p>Add companies from the HR Contacts tab as targets.</p></div>';
                }
            })
            .catch(err => {
                console.error('Error loading target companies:', err);
                container.innerHTML = '<div class="nrt-profile-empty"><p>Failed to load target companies.</p></div>';
            });
        }

        /**
         * Bind events for target company cards
         */
        bindTargetCompanyEvents() {
            document.querySelectorAll('.nrt-remove-target-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const companyId = btn.dataset.companyId;
                    this.removeTargetCompany(companyId);
                });
            });
        }

        /**
         * Remove target company
         */
        removeTargetCompany(companyId) {
            fetch(this.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'nrt_remove_target_company',
                    nonce: this.nonce,
                    company_id: companyId
                })
            })
            .then(res => res.json())
            .then(response => {
                if (response.success) {
                    this.loadTargetCompanies();
                    this.loadNetworkingStats();
                }
            })
            .catch(err => console.error('Error removing target company:', err));
        }

        /**
         * Load outreach log
         */
        loadOutreachLog() {
            const container = document.getElementById('nrt-outreach-log-list');
            if (!container) return;

            fetch(this.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'nrt_get_outreach_log',
                    nonce: this.nonce
                })
            })
            .then(res => res.json())
            .then(response => {
                if (response.success && response.data.html) {
                    container.innerHTML = response.data.html;
                    this.bindOutreachEvents();
                } else {
                    container.innerHTML = '<div class="nrt-profile-empty"><p>Track contacts you\'ve reached out to.</p></div>';
                }
            })
            .catch(err => {
                console.error('Error loading outreach log:', err);
                container.innerHTML = '<div class="nrt-profile-empty"><p>Failed to load outreach log.</p></div>';
            });
        }

        /**
         * Bind events for outreach cards
         */
        bindOutreachEvents() {
            document.querySelectorAll('.nrt-remove-outreach-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const contactId = btn.dataset.contactId;
                    this.removeOutreach(contactId);
                });
            });
        }

        /**
         * Remove outreach log entry
         */
        removeOutreach(contactId) {
            fetch(this.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'nrt_remove_outreach',
                    nonce: this.nonce,
                    contact_id: contactId
                })
            })
            .then(res => res.json())
            .then(response => {
                if (response.success) {
                    this.loadOutreachLog();
                    this.loadNetworkingStats();
                }
            })
            .catch(err => console.error('Error removing outreach:', err));
        }

        /**
         * Load matches for the Matches tab
         */
        loadMatches() {
            const matchesList = document.getElementById('nrt-matches-list');
            const matchesEmpty = document.getElementById('nrt-matches-empty');

            if (!matchesList) return;

            // Show loading state
            matchesList.innerHTML = '<div class="nrt-matches-loading"><div class="nrt-loading-spinner"></div><span>Finding your matches...</span></div>';
            if (matchesEmpty) matchesEmpty.style.display = 'none';

            // Fetch matches via AJAX
            fetch(this.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'nrt_load_matches',
                    status: 'pending'
                })
            })
            .then(res => res.json())
            .then(response => {
                if (response.success && response.data.html) {
                    matchesList.innerHTML = response.data.html;
                    this.bindMatchCardEvents();
                } else {
                    matchesList.innerHTML = '';
                    if (matchesEmpty) matchesEmpty.style.display = '';
                }
            })
            .catch(err => {
                console.error('Error loading matches:', err);
                matchesList.innerHTML = '<div class="nrt-matches-empty"><p>Failed to load matches. Please try again.</p></div>';
            });
        }

        /**
         * Bind event handlers for match cards
         */
        bindMatchCardEvents() {
            const matchCards = this.terminal.querySelectorAll('.nrt-match-card');

            matchCards.forEach(card => {
                // View action - load detail in content panel
                const viewBtn = card.querySelector('[data-action="view"]');
                if (viewBtn) {
                    viewBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        const briefId = card.dataset.briefId;
                        this.loadMatchDetail(briefId);
                        // Select this card
                        matchCards.forEach(c => c.classList.remove('is-selected'));
                        card.classList.add('is-selected');
                    });
                }

                // Skip action
                const skipBtn = card.querySelector('[data-action="skip"]');
                if (skipBtn) {
                    skipBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        const briefId = card.dataset.briefId;
                        this.updateMatchStatus(briefId, 'skipped', card);
                    });
                }

                // Interested action
                const interestedBtn = card.querySelector('[data-action="interested"]');
                if (interestedBtn) {
                    interestedBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        const briefId = card.dataset.briefId;
                        this.updateMatchStatus(briefId, 'interested', card);
                    });
                }

                // Card click opens detail
                card.addEventListener('click', () => {
                    const briefId = card.dataset.briefId;
                    this.loadMatchDetail(briefId);
                    matchCards.forEach(c => c.classList.remove('is-selected'));
                    card.classList.add('is-selected');
                });
            });
        }

        /**
         * Load match detail in content panel
         */
        loadMatchDetail(briefId) {
            const contentInner = document.getElementById('nrt-content-inner');
            if (!contentInner) return;

            // Show loading in content panel
            contentInner.innerHTML = '<div class="nrt-matches-loading" style="padding:60px 20px;"><div class="nrt-loading-spinner"></div><span>Loading opportunity...</span></div>';

            fetch(this.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'nrt_load_match_detail',
                    brief_id: briefId
                })
            })
            .then(res => res.json())
            .then(response => {
                if (response.success && response.data.html) {
                    contentInner.innerHTML = response.data.html;
                    this.bindDetailActions(briefId);
                } else {
                    contentInner.innerHTML = '<div class="nrt-matches-empty"><p>Failed to load opportunity details.</p></div>';
                }
            })
            .catch(err => {
                console.error('Error loading match detail:', err);
                contentInner.innerHTML = '<div class="nrt-matches-empty"><p>Failed to load opportunity details.</p></div>';
            });
        }

        /**
         * Bind action buttons in detail panel
         */
        bindDetailActions(briefId) {
            const contentInner = document.getElementById('nrt-content-inner');
            if (!contentInner) return;

            const skipBtn = contentInner.querySelector('[data-action="skip"]');
            const interestedBtn = contentInner.querySelector('[data-action="interested"]');
            const undoBtn = contentInner.querySelector('[data-action="pending"]');

            if (skipBtn) {
                skipBtn.addEventListener('click', () => {
                    this.updateMatchStatus(briefId, 'skipped');
                });
            }

            if (interestedBtn) {
                interestedBtn.addEventListener('click', () => {
                    this.updateMatchStatus(briefId, 'interested');
                });
            }

            if (undoBtn) {
                undoBtn.addEventListener('click', () => {
                    this.updateMatchStatus(briefId, 'pending');
                    // Reload the detail to show the pending state
                    this.loadMatchDetail(briefId);
                    // Reload the matches list to show this match again
                    this.loadMatches();
                });
            }
        }

        /**
         * Update match status (interested/skipped)
         */
        updateMatchStatus(briefId, status, card = null) {
            fetch(this.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'nrt_update_match_status',
                    brief_id: briefId,
                    status: status
                })
            })
            .then(res => res.json())
            .then(response => {
                if (response.success) {
                    // Remove the card from list with animation
                    if (card) {
                        card.style.transition = 'opacity 0.3s, transform 0.3s';
                        card.style.opacity = '0';
                        card.style.transform = status === 'interested' ? 'translateX(100px)' : 'translateX(-100px)';
                        setTimeout(() => {
                            card.remove();
                            // Check if list is empty
                            const matchesList = document.getElementById('nrt-matches-list');
                            const matchesEmpty = document.getElementById('nrt-matches-empty');
                            if (matchesList && !matchesList.querySelector('.nrt-match-card')) {
                                if (matchesEmpty) matchesEmpty.style.display = '';
                            }
                        }, 300);
                    }

                    // Show toast notification
                    const toastMessages = {
                        'interested': 'Interest registered!',
                        'skipped': 'Match skipped',
                        'pending': 'Match restored'
                    };
                    this.showToast(toastMessages[status] || response.data.message);

                    // Clear content panel and load next match (only for interested/skipped)
                    if (status === 'interested' || status === 'skipped') {
                        const nextCard = this.terminal.querySelector('.nrt-match-card:not(.is-selected)');
                        if (nextCard) {
                            nextCard.click();
                        } else {
                            const contentInner = document.getElementById('nrt-content-inner');
                            if (contentInner) {
                                contentInner.innerHTML = '<div class="nrt-matches-empty" style="padding:60px 20px;"><h4>All caught up!</h4><p>Check back later for new matches.</p></div>';
                            }
                        }
                    }
                } else {
                    this.showToast(response.data?.message || 'Failed to update match');
                }
            })
            .catch(err => {
                console.error('Error updating match status:', err);
                this.showToast('Failed to update match');
            });
        }

        /**
         * Show a toast notification
         */
        showToast(message, type = 'default') {
            // Remove existing toast
            const existing = document.querySelector('.nrt-toast');
            if (existing) existing.remove();

            // Define colors based on type
            const colors = {
                default: '#333',
                success: '#10b981',
                error: '#ef4444',
                info: '#2563eb'
            };
            const bgColor = colors[type] || colors.default;

            const toast = document.createElement('div');
            toast.className = 'nrt-toast';
            toast.textContent = message;
            toast.style.cssText = `position:fixed;bottom:80px;left:50%;transform:translateX(-50%);background:${bgColor};color:#fff;padding:12px 24px;border-radius:8px;font-size:14px;z-index:9999;animation:nrt-toast-in 0.3s;`;
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.style.animation = 'nrt-toast-out 0.3s forwards';
                setTimeout(() => toast.remove(), 300);
            }, 2500);
        }

        /**
         * Initialize profile dashboard components
         */
        initProfileDashboard() {
            // Bind profile action buttons if not already bound
            if (this._profileInitialized) return;
            this._profileInitialized = true;

            const profileTab = this.terminal.querySelector('.nrt-tab-profile');
            const profileView = document.getElementById('nrt-profile-view');

            // Handle profile action buttons in sidebar
            if (profileTab) {
                profileTab.addEventListener('click', (e) => {
                    const actionBtn = e.target.closest('[data-action]');
                    if (!actionBtn) return;

                    const action = actionBtn.dataset.action;
                    this.handleProfileAction(action);
                });
            }

            // Handle profile action buttons in main profile dashboard
            if (profileView) {
                profileView.addEventListener('click', (e) => {
                    // Handle data-action buttons
                    const actionBtn = e.target.closest('[data-action]');
                    if (actionBtn) {
                        const action = actionBtn.dataset.action;
                        this.handleProfileAction(action);
                        return;
                    }

                    // Handle data-tab buttons (quick actions that switch tabs)
                    const tabBtn = e.target.closest('[data-tab]');
                    if (tabBtn) {
                        const tabName = tabBtn.dataset.tab;
                        this.switchTab(tabName);
                    }
                });
            }

            // Handle feed filter change
            const feedFilter = document.getElementById('nrt-feed-filter');
            if (feedFilter) {
                feedFilter.addEventListener('change', (e) => {
                    this.filterProfileFeed(e.target.value);
                });
            }

            // Initialize preferences modal
            this.initPreferencesModal();

            // Load initial personalized feed
            this.loadPersonalizedFeed();
        }

        /**
         * Handle profile action button clicks
         */
        handleProfileAction(action) {
            switch (action) {
                case 'open-preferences':
                    // Open matching modal if it exists
                    const matchingModal = document.getElementById('sffc-matching-modal');
                    if (matchingModal) {
                        matchingModal.classList.add('is-open');
                        matchingModal.setAttribute('aria-hidden', 'false');
                    } else {
                        // Fallback: redirect to preferences page
                        window.location.href = '/account/';
                    }
                    break;

                case 'open-alerts':
                    // Open newsletter modal if it exists
                    const newsletterModal = document.getElementById('sffc-newsletter-modal');
                    if (newsletterModal) {
                        newsletterModal.classList.add('is-open');
                        newsletterModal.setAttribute('aria-hidden', 'false');
                    } else {
                        window.location.href = '/account/';
                    }
                    break;

                case 'manage-topics':
                    // Open preferences modal
                    this.openPreferencesModal();
                    break;

                case 'view-all-saved':
                    // Scroll to saved section or expand it
                    const savedSection = document.getElementById('nrt-profile-saved');
                    if (savedSection) {
                        savedSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                    break;

                case 'edit-profile':
                case 'edit-photo':
                case 'add-photo':
                case 'add-headline':
                case 'add-skills':
                case 'upload-cv':
                    // Open preferences modal for profile editing
                    this.openPreferencesModal();
                    break;

                case 'view-as-recruiter':
                    // Show coming soon notification
                    this.showToast('Recruiter preview coming soon!', 'info');
                    break;

                default:
                    // Unknown action - silently ignore
                    break;
            }
        }

        /**
         * Initialize Database Tab
         */
        initDatabaseTab() {
            if (this._databaseInitialized) {
                // Already initialized, just select a random firm if none selected
                this.selectRandomFirm();
                return;
            }
            this._databaseInitialized = true;

            // Render initial firms list
            this.renderDatabaseFirms();

            // Bind search input
            const searchInput = document.getElementById('nrt-db-search');
            if (searchInput) {
                searchInput.addEventListener('input', (e) => {
                    this.filterDatabaseFirms();
                });
            }

            // Bind region filter chips
            const filterChips = this.terminal.querySelectorAll('[data-db-region]');
            filterChips.forEach(chip => {
                chip.addEventListener('click', () => {
                    filterChips.forEach(c => c.classList.remove('is-active'));
                    chip.classList.add('is-active');
                    this.filterDatabaseFirms();
                });
            });

            // Bind firm card clicks
            const dbList = document.getElementById('nrt-database-list');
            if (dbList) {
                dbList.addEventListener('click', (e) => {
                    const card = e.target.closest('.nrt-firm-card');
                    if (card) {
                        this.selectFirm(card);
                    }
                });
            }

            // Auto-select a random firm
            this.selectRandomFirm();
        }

        /**
         * Select a random firm from the database
         */
        selectRandomFirm() {
            const dbList = document.getElementById('nrt-database-list');
            if (!dbList) return;

            // Only select from non-locked firms (featured firms for logged-out users)
            const firmCards = dbList.querySelectorAll('.nrt-firm-card:not([data-locked="true"])');
            if (firmCards.length === 0) {
                // Fallback to first firm if all are locked (shouldn't happen with 60 featured)
                const allCards = dbList.querySelectorAll('.nrt-firm-card');
                if (allCards.length > 0) {
                    this.selectFirm(allCards[0]);
                }
                return;
            }

            // Pick a random unlocked firm
            const randomIndex = Math.floor(Math.random() * firmCards.length);
            const randomCard = firmCards[randomIndex];

            // Select it
            this.selectFirm(randomCard);

            // Scroll the selected card into view
            randomCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        /**
         * Get PE Firms Database
         */
        getPEFirmsDatabase() {
            // Featured firms with rich data (top 60 PE firms globally)
            const featuredFirms = [
                {"n":"Blackstone","c":"United States","f":1985,"aum":"$1+ trillion","hq":"New York","s":"Real Estate, Private Equity, Credit, Infrastructure","d":"World's largest alternative asset manager with over $1 trillion in AUM. Founded by Stephen Schwarzman and Peter Peterson.","w":"blackstone.com","featured":true},
                {"n":"KKR","c":"United States","f":1976,"aum":"$553 billion","hq":"New York","s":"Private Equity, Infrastructure, Real Estate, Credit","d":"Pioneer of the leveraged buyout. Founded by Jerome Kohlberg, Henry Kravis and George Roberts. Known for historic RJR Nabisco deal.","w":"kkr.com","featured":true},
                {"n":"The Carlyle Group","c":"United States","f":1987,"aum":"$447 billion","hq":"Washington, D.C.","s":"Private Equity, Credit, Real Assets","d":"Global investment firm with deep expertise across industries. Founded by William Conway, David Rubenstein and Daniel D'Aniello.","w":"carlyle.com","featured":true},
                {"n":"Apollo Global Management","c":"United States","f":1990,"aum":"$696 billion","hq":"New York","s":"Private Equity, Credit, Real Assets","d":"Alternative investment manager specializing in credit and private equity. Founded by Leon Black, Josh Harris and Marc Rowan.","w":"apollo.com","featured":true},
                {"n":"TPG","c":"United States","f":1992,"aum":"$286 billion","hq":"Fort Worth / San Francisco","s":"Private Equity, Growth, Impact, Real Estate","d":"Global alternative asset firm investing across diverse sectors. Founded by David Bonderman and James Coulter.","w":"tpg.com","featured":true},
                {"n":"Warburg Pincus","c":"United States","f":1966,"aum":"$87 billion","hq":"New York","s":"Growth Equity, Technology, Healthcare, Financial Services","d":"One of the oldest private equity firms, pioneering growth investing since 1966. Led by Timothy Geithner as Chairman.","w":"warburgpincus.com","featured":true},
                {"n":"Bain Capital","c":"United States","f":1984,"aum":"$185 billion","hq":"Boston","s":"Private Equity, Venture, Credit, Real Estate","d":"Multi-asset alternative investment firm. Founded by partners including Mitt Romney from Bain & Company.","w":"baincapital.com","featured":true},
                {"n":"CVC Capital Partners","c":"Luxembourg","f":1981,"aum":"€200 billion","hq":"Luxembourg","s":"Private Equity, Credit, Secondaries, Infrastructure","d":"One of the world's largest private equity firms. Listed on Euronext Amsterdam in 2024.","w":"cvc.com","featured":true},
                {"n":"EQT","c":"Sweden","f":1994,"aum":"€266 billion","hq":"Stockholm","s":"Private Equity, Infrastructure, Real Estate, Venture","d":"Nordic roots, global reach. Founded with support from Investor AB and the Wallenberg family. Third largest PE firm globally.","w":"eqtgroup.com","featured":true},
                {"n":"Advent International","c":"United States","f":1984,"aum":"$100 billion","hq":"Boston","s":"Business Services, Healthcare, Industrial, Technology","d":"Global private equity investor focused on buyouts. Founded by Peter Brooke. Operates in 42 countries.","w":"adventinternational.com","featured":true},
                {"n":"Thoma Bravo","c":"United States","f":1980,"aum":"$184 billion","hq":"Miami / Chicago","s":"Software, Technology, Cybersecurity","d":"World's largest software-focused private equity firm. Pioneer of buy-and-build strategy in technology.","w":"thomabravo.com","featured":true},
                {"n":"Silver Lake","c":"United States","f":1999,"aum":"$102 billion","hq":"Menlo Park / New York","s":"Technology, Software, Internet","d":"Global technology investment firm. Invested in Dell, Alibaba, Twitter, Airbnb and Unity Technologies.","w":"silverlake.com","featured":true},
                {"n":"Vista Equity Partners","c":"United States","f":2000,"aum":"$100 billion","hq":"Austin","s":"Enterprise Software, Data, Technology","d":"Leading software-focused investment firm. Founded by Robert F. Smith. Portfolio of 85+ enterprise software companies.","w":"vistaequitypartners.com","featured":true},
                {"n":"General Atlantic","c":"United States","f":1980,"aum":"$114 billion","hq":"New York","s":"Technology, Consumer, Financial Services, Healthcare","d":"Global growth equity firm. Founded as investment arm for Atlantic Philanthropies by Chuck Feeney.","w":"generalatlantic.com","featured":true},
                {"n":"Hellman & Friedman","c":"United States","f":1984,"aum":"$90+ billion","hq":"San Francisco","s":"Software, Financial Services, Healthcare, Media","d":"Private equity firm focused on high-quality businesses. Founded by Warren Hellman and Tully Friedman.","w":"hf.com","featured":true},
                {"n":"Permira","c":"United Kingdom","f":1985,"aum":"€80 billion","hq":"London","s":"Technology, Consumer, Healthcare, Services","d":"Global investment firm with thematic approach. Originally known as Schroder Ventures until 2001.","w":"permira.com","featured":true},
                {"n":"Cinven","c":"United Kingdom","f":1977,"aum":"€44 billion","hq":"London","s":"Business Services, Consumer, Healthcare, TMT","d":"European-focused PE firm. Name derived from Coal Industry Nominees Ventures. Partner-owned since 1995.","w":"cinven.com","featured":true},
                {"n":"BC Partners","c":"United Kingdom","f":1986,"aum":"€40 billion","hq":"London","s":"Healthcare, TMT, Services, Industrials","d":"Pioneer of pan-European buyouts. Investments include Petco, PetSmart, and numerous healthcare companies.","w":"bcpartners.com","featured":true},
                {"n":"Apax Partners","c":"United Kingdom","f":1981,"aum":"$77 billion","hq":"London","s":"Technology, Services, Healthcare, Internet/Consumer","d":"One of the oldest global private equity firms. Over 50 years of transforming businesses.","w":"apax.com","featured":true},
                {"n":"PAI Partners","c":"France","f":1872,"aum":"€26 billion","hq":"Paris","s":"Business Services, Food & Consumer, Healthcare, Industrial","d":"Originally the investment arm of Paribas. Acquired Tropicana brands from PepsiCo for $3.3 billion.","w":"paipartners.com","featured":true},
                {"n":"Ardian","c":"France","f":1996,"aum":"$180 billion","hq":"Paris","s":"Buyout, Infrastructure, Real Estate, Credit","d":"Largest European-headquartered PE firm. Founded by Dominique Senequier, spun out from AXA in 2013.","w":"ardian.com","featured":true},
                {"n":"Bridgepoint","c":"United Kingdom","f":1984,"aum":"€67 billion","hq":"London","s":"Healthcare, Technology, Consumer, Services","d":"Quoted private asset growth investor. FTSE 250 constituent. Known for Pret A Manger and MotoGP deals.","w":"bridgepoint.eu","featured":true},
                {"n":"Clayton, Dubilier & Rice","c":"United States","f":1976,"aum":"$30 billion","hq":"New York","s":"Industrial, Consumer, Healthcare, Services","d":"24th oldest PE firm globally. Founded as turnaround specialist. Invested in Hertz, US Foods, Sally Beauty.","w":"cdr-inc.com","featured":true},
                {"n":"Leonard Green & Partners","c":"United States","f":1989,"aum":"$75 billion","hq":"Los Angeles","s":"Consumer, Healthcare, Business Services","d":"LA-based PE firm partnering with founders and management teams. Invested in Petco, Container Store.","w":"leonardgreen.com","featured":true},
                {"n":"GTCR","c":"United States","f":1980,"aum":"$40 billion","hq":"Chicago","s":"Financial Services, Healthcare, Technology, Business Services","d":"Chicago-based PE firm. Pioneer of buy-and-build strategy. Over 290 investments since founding.","w":"gtcr.com","featured":true},
                {"n":"New Mountain Capital","c":"United States","f":1999,"aum":"$55+ billion","hq":"New York","s":"Healthcare, Software, Business Services","d":"Defensive growth investor. Founded by Steven Klinsky. Notable exits include Blue Yonder and Signify Health.","w":"newmountaincapital.com","featured":true},
                {"n":"Cerberus Capital Management","c":"United States","f":1992,"aum":"$65 billion","hq":"New York","s":"Distressed, Credit, Real Estate, Private Equity","d":"Global alternative investment firm. Founded by Steve Feinberg. Known for Chrysler and GMAC investments.","w":"cerberus.com","featured":true},
                {"n":"Lone Star Funds","c":"United States","f":1995,"aum":"$95 billion","hq":"Dallas","s":"Real Estate, Credit, Private Equity","d":"Global investor in real estate and credit. 25 funds raised since inception. Acquired Kidde for $3 billion.","w":"lonestarfunds.com","featured":true},
                {"n":"Brookfield Asset Management","c":"Canada","f":1899,"aum":"$1+ trillion","hq":"New York","s":"Real Estate, Infrastructure, Renewable Energy, Private Equity","d":"Century-old origins, modern alternative asset giant. Crossed $1 trillion AUM in late 2024.","w":"brookfield.com","featured":true},
                {"n":"Ares Management","c":"United States","f":1997,"aum":"$596 billion","hq":"Los Angeles","s":"Credit, Private Equity, Real Estate","d":"Global alternative investment manager. Founded by Antony Ressler, Michael Arougheti and others.","w":"aresmgmt.com","featured":true},
                {"n":"Partners Group","c":"Switzerland","f":1996,"aum":"$174 billion","hq":"Zug","s":"Private Equity, Infrastructure, Real Estate, Debt","d":"Swiss-based global private markets firm. Fifth most valuable publicly listed PE firm by market cap.","w":"partnersgroup.com","featured":true},
                {"n":"Nordic Capital","c":"Sweden","f":1989,"aum":"€25 billion","hq":"Stockholm","s":"Healthcare, Technology, Financial Services","d":"Leading Northern European PE firm. Known for Inovalon ($7.3B) and Hargreaves Lansdown ($7B) deals.","w":"nordiccapital.com","featured":true},
                {"n":"Triton Partners","c":"Luxembourg","f":1997,"aum":"$14 billion","hq":"Luxembourg","s":"Industrial Tech, Business Services, Healthcare","d":"European mid-market specialist. Portfolio companies have combined sales of €16 billion.","w":"triton-partners.com","featured":true},
                {"n":"Inflexion","c":"United Kingdom","f":1999,"aum":"£10 billion","hq":"London","s":"Business Services, Technology, Healthcare, Consumer","d":"European mid-market PE firm. Backed 117 businesses since founding. Largest European minority fund.","w":"inflexion.com","featured":true},
                {"n":"Montagu","c":"United Kingdom","f":1968,"aum":"€11 billion","hq":"London","s":"Healthcare, Financial Services, Critical Data, Education","d":"Mid-market PE firm with deep carve-out expertise. Traces roots to Midland Bank in 1968.","w":"montagu.com","featured":true},
                {"n":"L Catterton","c":"United States","f":1989,"aum":"$37 billion","hq":"Greenwich","s":"Consumer, Retail, Restaurants, Beauty","d":"World's largest consumer-focused PE firm. Partnership with LVMH and Bernard Arnault's family office.","w":"lcatterton.com","featured":true},
                {"n":"Clearlake Capital Group","c":"United States","f":2006,"aum":"$90 billion","hq":"Santa Monica","s":"Technology, Industrials, Consumer","d":"Fast-growing PE firm. Co-acquired Chelsea F.C. for $3 billion in 2022.","w":"clearlake.com","featured":true},
                {"n":"Platinum Equity","c":"United States","f":1995,"aum":"$50 billion","hq":"Beverly Hills","s":"Manufacturing, Distribution, Technology","d":"M&A&O specialist. Founded by Tom Gores. Portfolio generates $100+ billion revenue with 200,000 employees.","w":"platinumequity.com","featured":true},
                {"n":"American Securities","c":"United States","f":1947,"aum":"$26 billion","hq":"New York","s":"Industrial, Consumer, Healthcare, Services","d":"Roots in Rosenwald family office (Sears). Values-based investing pioneer. Invested in Planet Fitness.","w":"american-securities.com","featured":true},
                {"n":"TDR Capital","c":"United Kingdom","f":2002,"aum":"€15 billion","hq":"London","s":"Consumer, Financial Services, Leisure","d":"British PE firm. Major investor in Asda, David Lloyd Leisure, and Euro Garages.","w":"tdrcapital.com","featured":true},
                {"n":"Stone Point Capital","c":"United States","f":1985,"aum":"$65 billion","hq":"Greenwich","s":"Financial Services, Insurance, Asset Management","d":"Leading financial services-focused PE firm. Deep expertise in insurance and asset management.","w":"stonepoint.com","featured":true},
                {"n":"Roark Capital Group","c":"United States","f":2001,"aum":"$41 billion","hq":"Atlanta","s":"Franchise, Restaurants, Consumer Services","d":"Leading franchise investor. Owns Subway, Inspire Brands (Arby's, Dunkin', Buffalo Wild Wings), and more.","w":"roarkcapital.com","featured":true},
                {"n":"Summit Partners","c":"United States","f":1984,"aum":"$46 billion","hq":"Boston","s":"Technology, Healthcare, Growth Products","d":"Pioneer of growth investing. Invested in Uber, McAfee, Avast. Over 550 investments since inception.","w":"summitpartners.com","featured":true},
                {"n":"TA Associates","c":"United States","f":1968,"aum":"$65 billion","hq":"Boston","s":"Technology, Healthcare, Financial Services, Business Services","d":"One of the oldest PE firms. 560+ investments including Biogen in the 1970s. Named Mid-Market Firm of Year 2025.","w":"ta.com","featured":true},
                {"n":"Genstar Capital","c":"United States","f":1988,"aum":"$50 billion","hq":"San Francisco","s":"Financial Services, Software, Industrial Technology, Healthcare","d":"Middle-market PE firm. Raised $12.6 billion for Fund XI. Ranked 25th in PEI 300.","w":"gencap.com","featured":true},
                {"n":"Veritas Capital","c":"United States","f":1992,"aum":"$50+ billion","hq":"New York","s":"Government Technology, Defense, Healthcare IT","d":"Leading government technology investor. Fund IX closed at $14.4 billion hard cap.","w":"veritascapital.com","featured":true},
                {"n":"Francisco Partners","c":"United States","f":1999,"aum":"$45 billion","hq":"San Francisco","s":"Technology, Software, Security, Fintech","d":"Technology buyout pioneer. Top-ranked tech PE firm by HEC Paris. Acquired The Weather Company.","w":"franciscopartners.com","featured":true},
                {"n":"Insight Partners","c":"United States","f":1995,"aum":"$90 billion","hq":"New York","s":"Software, SaaS, Cybersecurity, Fintech","d":"Global software investor. 800+ investments, 55+ IPOs. Early investor in Twitter and Alibaba.","w":"insightpartners.com","featured":true},
                {"n":"IK Partners","c":"United Kingdom","f":1989,"aum":"€14 billion","hq":"London","s":"Business Services, Healthcare, Industrials","d":"Northern European mid-market specialist. 200+ investments. Acquired by Wendel in 2024 for €3.8 billion.","w":"ikpartners.com","featured":true},
                {"n":"Investindustrial","c":"United Kingdom","f":1990,"aum":"€13 billion","hq":"London","s":"Industrial, Healthcare, Consumer, Technology","d":"European mid-market firm. Founded by Andrea Bonomi. Invested in Rimac and Fassi.","w":"investindustrial.com","featured":true},
                {"n":"Eurazeo","c":"France","f":1969,"aum":"€35 billion","hq":"Paris","s":"Healthcare, Technology, Consumer, Real Estate","d":"French investment group. €24.7 billion AUM from third parties. 600+ portfolio companies.","w":"eurazeo.com","featured":true},
                {"n":"ICG","c":"United Kingdom","f":1989,"aum":"$124 billion","hq":"London","s":"Credit, Private Equity, Real Assets","d":"FTSE 100 alternative asset manager. Three decades of experience across credit and equity strategies.","w":"icgam.com","featured":true},
                {"n":"HPS Investment Partners","c":"United States","f":2007,"aum":"$148 billion","hq":"New York","s":"Private Credit, Direct Lending, Structured Credit","d":"Leading private credit firm. Acquired by BlackRock in 2024. $203 billion invested since founding.","w":"hpspartners.com","featured":true},
                {"n":"Oaktree Capital Management","c":"United States","f":1995,"aum":"$218 billion","hq":"Los Angeles","s":"Distressed Debt, Credit, Private Equity","d":"World's largest distressed debt investor. Part of Brookfield family. Founded by Howard Marks.","w":"oaktreecapital.com","featured":true},
                {"n":"Madison Dearborn Partners","c":"United States","f":1992,"aum":"$36 billion","hq":"Chicago","s":"Basic Industries, Financial Services, Healthcare, Technology","d":"Leading Chicago PE firm. 160+ investments. Roots in First Chicago Venture Capital.","w":"mdcp.com","featured":true},
                {"n":"Providence Equity Partners","c":"United States","f":1989,"aum":"$40 billion","hq":"Providence, RI","s":"Media, Communications, Education, Technology","d":"Pioneer of sector-focused investing. Major media investments include Hulu, Warner Music, Univision.","w":"provequity.com","featured":true},
                {"n":"Blue Owl Capital","c":"United States","f":2016,"aum":"$174 billion","hq":"New York","s":"Credit, GP Strategic Capital, Real Estate","d":"Fast-growing alternative asset manager. Formed from merger of Owl Rock and Dyal Capital in 2021.","w":"blueowl.com","featured":true},
                {"n":"GI Partners","c":"United States","f":2001,"aum":"$49 billion","hq":"San Francisco","s":"Data Infrastructure, Healthcare, Software","d":"Specialist in digital infrastructure and data centers. Invested in Flexential, ViaWest, Telx Group.","w":"gipartners.com","featured":true},
                {"n":"H.I.G. Capital","c":"United States","f":1993,"aum":"$69 billion","hq":"Miami","s":"Private Equity, Credit, Real Estate, Infrastructure","d":"Global alternative investment firm with 19 offices worldwide. 400+ investments since founding.","w":"hig.com","featured":true},
                {"n":"Thomas H. Lee Partners","c":"United States","f":1974,"aum":"$50 billion","hq":"Boston","s":"Financial Technology, Healthcare, Business Services","d":"Founded by Thomas H. Lee. $260 billion aggregate enterprise value. Invested in Dunkin', Aramark, FIS.","w":"thl.com","featured":true}
            ];

            // Standard firms (remaining database)
            const standardFirms = [
                {"n":"21 Invest","c":"France"},{"n":"360 Capital","c":"France"},{"n":"50 Partners","c":"France"},{"n":"5Y Capital","c":"China"},{"n":"747 Capital","c":"United States"},{"n":"7GC & CO","c":"Luxembourg"},{"n":"9 Capital Management","c":"France"},{"n":"Abenex","c":"France"},{"n":"Accel","c":"United States"},{"n":"Accel-KKR","c":"United States"},{"n":"Activa Capital","c":"France"},{"n":"Adams Street Partners","c":"United Kingdom"},{"n":"Adelis Equity Partners","c":"Sweden"},{"n":"AE Industrial Partners","c":"United States"},{"n":"AEA Investors","c":"United States"},{"n":"Affiliated Managers Group","c":"United Arab Emirates"},{"n":"Albacore Capital Group","c":"United Kingdom"},{"n":"Aliante","c":"Switzerland"},{"n":"All Seas Capital","c":"United Kingdom"},{"n":"Alpine Investors","c":"United States"},{"n":"Alsa Ventures","c":"United Kingdom"},{"n":"Altamar CAM Partners","c":"Spain"},{"n":"Altaris","c":"United States"},{"n":"Altas Partners","c":"Canada"},{"n":"Alter Equity","c":"France"},{"n":"Altimeter Capital Management","c":"United States"},{"n":"Altis Capital","c":"France"},{"n":"Alto Partners SGR","c":"Italy"},{"n":"Altor Equity Partners","c":"Sweden"},{"n":"Alvarez & Marsal Capital Partners","c":"United States"},{"n":"Ambienta","c":"United Kingdom"},{"n":"American Industrial Partners","c":"United States"},{"n":"Ampersand Capital Partners","c":"United States"},{"n":"Anaxago Capital","c":"France"},{"n":"Andera Partners","c":"France"},{"n":"Andreessen Horowitz","c":"United States"},{"n":"Angeles Equity Partners","c":"United States"},{"n":"Antares Capital","c":"United States"},{"n":"Apeira Capital","c":"United States"},{"n":"Apera Asset Management","c":"United Kingdom"},{"n":"Apheon","c":"Belgium"},{"n":"Appian Capital Advisory","c":"United Kingdom"},{"n":"Aquila Capital","c":"Germany"},{"n":"Aquiline Capital Partners","c":"United States"},{"n":"Ara Partners","c":"United States"},{"n":"ARCH Venture Partners","c":"United States"},{"n":"Archimed","c":"France"},{"n":"Arcis Group","c":"France"},{"n":"Arcline Investment Management","c":"United States"},{"n":"Arctos Partners","c":"United States"},{"n":"Argos Wityu","c":"France"},{"n":"Arkea Capital","c":"France"},{"n":"Arlington Capital Partners","c":"United States"},{"n":"Armen","c":"France"},{"n":"Arsenal Capital Partners","c":"United States"},{"n":"Astanor Ventures","c":"Belgium"},{"n":"Astorg","c":"Luxembourg"},{"n":"Atlas Holdings","c":"United States"},{"n":"Audacia","c":"France"},{"n":"Audax Group","c":"United States"},{"n":"August Equity","c":"United Kingdom"},{"n":"Auriga Partners","c":"France"},{"n":"Aurora Capital Partners","c":"United States"},{"n":"Avista Healthcare Partners","c":"United States"},{"n":"AXA IM Prime","c":"France"},{"n":"AXA Investment Managers","c":"France"},{"n":"Axcel","c":"Denmark"},{"n":"Axeleo Capital","c":"France"},{"n":"Axiom Asia Private Capital","c":"Singapore"},{"n":"Azulis Capital","c":"France"},{"n":"B Capital","c":"United States"},{"n":"Balderton Capital","c":"United Kingdom"},{"n":"Barings","c":"United Kingdom"},{"n":"Battery Ventures","c":"United States"},{"n":"Baypine","c":"United States"},{"n":"BDT & MSD Partners","c":"United States"},{"n":"Berkshire Partners","c":"United States"},{"n":"Bertram Capital","c":"United States"},{"n":"Bessemer Venture Partners","c":"United States"},{"n":"BEX Capital","c":"France"},{"n":"Blackfin Capital Partners","c":"France"},{"n":"Bonaccord Capital Partners","c":"United States"},{"n":"BOND","c":"United States"},{"n":"Breakthrough Energy","c":"United States"},{"n":"Bregal Investments","c":"United Kingdom"},{"n":"Brighton Park Capital","c":"United States"},{"n":"Brightstar Capital Partners","c":"United States"},{"n":"Butterfly Equity","c":"United States"},{"n":"BV Investment Partners","c":"United States"},{"n":"Cap10 Partners","c":"United Kingdom"},{"n":"Capital Constellation","c":"United States"},{"n":"Capital Croissance","c":"France"},{"n":"Capital Dynamics","c":"Switzerland"},{"n":"Capiton AG","c":"Germany"},{"n":"CapMan","c":"Finland"},{"n":"Capricorn Partners","c":"Belgium"},{"n":"CapVest","c":"United Kingdom"},{"n":"Capza","c":"France"},{"n":"Castik Capital","c":"Luxembourg"},{"n":"Cathay Capital","c":"France"},{"n":"Causeway Capital Partners","c":"Ireland"},{"n":"CAZ Investments","c":"United States"},{"n":"CBC Group","c":"Singapore"},{"n":"CBPE Capital","c":"United Kingdom"},{"n":"CBRE Investment Management","c":"United Kingdom"},{"n":"CDH Investments","c":"Hong Kong"},{"n":"CDP Venture Capital","c":"Italy"},{"n":"Centerbridge Partners","c":"United States"},{"n":"Centeroak Partners","c":"United States"},{"n":"Cerea Partners","c":"France"},{"n":"CF Private Equity","c":"United States"},{"n":"Charlesbank Capital Partners","c":"United States"},{"n":"Charterhouse Capital Partners","c":"United Kingdom"},{"n":"Cheyne Capital","c":"United Kingdom"},{"n":"China Merchants Capital","c":"China"},{"n":"China Renaissance Group","c":"China"},{"n":"Chorus Capital","c":"United Kingdom"},{"n":"Churchill Asset Management","c":"United States"},{"n":"CIC Private Debt","c":"France"},{"n":"Ciclad","c":"France"},{"n":"CITIC Capital","c":"Hong Kong"},{"n":"Citizen Capital","c":"France"},{"n":"Clessidra","c":"Italy"},{"n":"Coatue Management","c":"United States"},{"n":"COI Partners","c":"Switzerland"},{"n":"Columbia Threadneedle Investments","c":"United Kingdom"},{"n":"Columna Capital","c":"United Kingdom"},{"n":"Committed Advisors","c":"France"},{"n":"Comvest Partners","c":"United States"},{"n":"Conquest","c":"France"},{"n":"Coparion","c":"Germany"},{"n":"Copper Street Capital","c":"United Kingdom"},{"n":"Corsair Capital","c":"United Kingdom"},{"n":"Cortec Group","c":"United States"},{"n":"Court Square Capital Partners","c":"United States"},{"n":"Cove Hill Partners","c":"United States"},{"n":"Craft Ventures","c":"United States"},{"n":"Crestview Partners","c":"United States"},{"n":"Cubera Private Equity","c":"Norway"},{"n":"Daphni","c":"France"},{"n":"Dawson Partners","c":"Canada"},{"n":"DBAY Advisors","c":"United Kingdom"},{"n":"DCP Capital","c":"Hong Kong"},{"n":"DEA Capital Alternative Funds","c":"Italy"},{"n":"Declaration Partners","c":"United States"},{"n":"Deerpath Capital","c":"United States"},{"n":"DFJ Growth","c":"United States"},{"n":"DigitalBridge","c":"United Kingdom"},{"n":"Dominus Capital","c":"United States"},{"n":"Eastern Bell Capital","c":"China"},{"n":"Edmond de Rothschild Group","c":"France"},{"n":"Edmond de Rothschild Private Equity","c":"Switzerland"},{"n":"Eiffel Investment Group","c":"France"},{"n":"EIG","c":"United States"},{"n":"Ekkio Capital","c":"France"},{"n":"Elaia","c":"France"},{"n":"Elevation Capital Partners","c":"France"},{"n":"EMK Capital","c":"United Kingdom"},{"n":"EMZ Partners","c":"France"},{"n":"EnCap Investments","c":"United States"},{"n":"Energy Impact Partners","c":"United States"},{"n":"Epiris","c":"United Kingdom"},{"n":"Épopée Gestion","c":"France"},{"n":"Equita Capital SGR","c":"Italy"},{"n":"Equitix","c":"United Kingdom"},{"n":"Essling Capital","c":"France"},{"n":"Experienced Capital","c":"France"},{"n":"Exponent","c":"United Kingdom"},{"n":"Extens","c":"France"},{"n":"Financière Arbevel","c":"France"},{"n":"Flagship Pioneering","c":"United States"},{"n":"Flexam Invest","c":"France"},{"n":"Flexpoint Ford","c":"United States"},{"n":"Flexstone Partners","c":"France"},{"n":"Flora Ventures","c":"Israel"},{"n":"FNB Private Equity","c":"France"},{"n":"Forbion","c":"Netherlands"},{"n":"Fortino Capital","c":"Belgium"},{"n":"Founders Fund","c":"United States"},{"n":"Founders Future","c":"France"},{"n":"FountainVest Partners","c":"Hong Kong"},{"n":"Frazier Healthcare Partners","c":"United States"},{"n":"Frenchfood Capital","c":"France"},{"n":"Freshstream Investment Partners","c":"United Kingdom"},{"n":"Frog Capital","c":"United Kingdom"},{"n":"FSN Capital","c":"Norway"},{"n":"FTV Capital","c":"United States"},{"n":"G Squared","c":"Switzerland"},{"n":"Galia Gestion","c":"France"},{"n":"Galiena Capital","c":"France"},{"n":"Gaorong Capital","c":"China"},{"n":"Gemspring Capital","c":"United States"},{"n":"Geneo Capital Entrepreneur","c":"France"},{"n":"General Catalyst","c":"United States"},{"n":"Generis Capital Partners","c":"France"},{"n":"Genui","c":"Germany"},{"n":"GHO Capital Partners","c":"United Kingdom"},{"n":"Golding Capital Partners","c":"Germany"},{"n":"Goldman Sachs Asset Management","c":"United States"},{"n":"Golub Capital","c":"United States"},{"n":"Gradiente SGR","c":"Italy"},{"n":"Graham Partners","c":"United States"},{"n":"Great Hill Partners","c":"United States"},{"n":"Greenbriar Equity Group","c":"United States"},{"n":"GreenOaks Capital Partners","c":"United States"},{"n":"Gridiron Capital","c":"United States"},{"n":"Groupama AM","c":"France"},{"n":"Grove Street Advisors","c":"United States"},{"n":"Gryphon Investors","c":"United States"},{"n":"Gyrus Capital","c":"Switzerland"},{"n":"H Capital Partners","c":"Portugal"},{"n":"Hahn & Co.","c":"Republic of Korea"},{"n":"Hambro Perks","c":"United Kingdom"},{"n":"Hand Partners","c":"France"},{"n":"Hanover Investors","c":"United Kingdom"},{"n":"Harvest Partners","c":"United States"},{"n":"Haveli Investments","c":"United States"},{"n":"Headline","c":"France"},{"n":"Heartwood Partners","c":"United States"},{"n":"HG","c":"Germany"},{"n":"HGGC","c":"United States"},{"n":"HI Inov","c":"France"},{"n":"Hillhouse Capital Group","c":"Hong Kong"},{"n":"Hivest Capital Partners","c":"France"},{"n":"Hollyport Capital","c":"United Kingdom"},{"n":"HongShan","c":"China"},{"n":"Hony Capital","c":"China"},{"n":"Hunter Point Capital","c":"United States"},{"n":"I4B","c":"Belgium"},{"n":"ICONIQ Capital","c":"United States"},{"n":"Icos Capital Management","c":"Netherlands"},{"n":"Idico","c":"France"},{"n":"IGI Private Equity","c":"Italy"},{"n":"Igneo Infrastructure Partners","c":"United Kingdom"},{"n":"IMM Private Equity","c":"Republic of Korea"},{"n":"Impact Partners","c":"France"},{"n":"Incline Equity Partners","c":"United States"},{"n":"Index Ventures","c":"United States"},{"n":"Infranity","c":"France"},{"n":"Initiative & Finance","c":"France"},{"n":"Innovafonds","c":"France"},{"n":"Insight Equity","c":"United States"},{"n":"Integral Corporation","c":"Japan"},{"n":"Intera Partners","c":"Finland"},{"n":"Intermediate Capital Group","c":"United Kingdom"},{"n":"Investcorp","c":"Bahrain"},{"n":"IRD Invest","c":"France"},{"n":"Iris","c":"France"},{"n":"ISAI Gestion","c":"France"},{"n":"Isalt","c":"France"},{"n":"Isatis Capital","c":"France"},{"n":"IVP","c":"United States"},{"n":"J.C. Flowers & Co.","c":"United States"},{"n":"J.F. Lehman & Company","c":"United States"},{"n":"J.P. Morgan Asset Management","c":"France"},{"n":"J12 Ventures","c":"Sweden"},{"n":"Jeito Capital","c":"France"},{"n":"JMI Equity","c":"United States"},{"n":"Jolt Capital","c":"France"},{"n":"Juuri Partners","c":"Finland"},{"n":"K1 Investment Management","c":"United States"},{"n":"Karista","c":"France"},{"n":"Kartesia","c":"Luxembourg"},{"n":"KC Invest","c":"France"},{"n":"Kedaara Capital","c":"India"},{"n":"Keensight Capital","c":"France"},{"n":"Keles","c":"France"},{"n":"Kelso & Company","c":"United States"},{"n":"Khosla Ventures","c":"United States"},{"n":"Kibo Ventures","c":"Spain"},{"n":"Kinderhook Industries","c":"United States"},{"n":"King Street","c":"United Kingdom"},{"n":"Kleiner Perkins","c":"United States"},{"n":"Kohlberg & Company","c":"United States"},{"n":"Korelya Capital","c":"France"},{"n":"KPS Capital Partners","c":"United States"},{"n":"Kurma Partners","c":"France"},{"n":"Kyip Capital","c":"Italy"},{"n":"La Française","c":"France"},{"n":"La Maison Partners","c":"France"},{"n":"Lakestar","c":"Switzerland"},{"n":"Latour Capital","c":"France"},{"n":"Lauxera Capital Partners","c":"France"},{"n":"LBO France","c":"France"},{"n":"LBP AM","c":"France"},{"n":"Lead Edge Capital","c":"United States"},{"n":"Lee Equity Partners","c":"United States"},{"n":"Leeds Equity Partners","c":"United States"},{"n":"Legend Capital","c":"China"},{"n":"Levine Leichtman Capital Partners","c":"United States"},{"n":"Lexington Partners","c":"United Kingdom"},{"n":"Liberty Strategic Capital","c":"United States"},{"n":"Libremax Capital","c":"United States"},{"n":"Lightrock","c":"United Kingdom"},{"n":"Lightspeed Venture Partners","c":"United States"},{"n":"Lightyear Capital","c":"United States"},{"n":"Linden Capital Partners","c":"United States"},{"n":"Lindsay Goldberg","c":"United States"},{"n":"Littlejohn & Co.","c":"United States"},{"n":"LLR Partners","c":"United States"},{"n":"LS Power Group","c":"United States"},{"n":"LT Capital","c":"France"},{"n":"Lux Capital","c":"United States"},{"n":"Luxcara","c":"Germany"},{"n":"M80","c":"Belgium"},{"n":"Macquarie Asset Management","c":"United Kingdom"},{"n":"Main Capital Partners","c":"Netherlands"},{"n":"Manulife Investment Management","c":"United Kingdom"},{"n":"MassMutual Ventures","c":"United Kingdom"},{"n":"MBK Partners","c":"Republic of Korea"},{"n":"MBO+","c":"France"},{"n":"Meanings Capital Partners","c":"France"},{"n":"Menlo Ventures","c":"United States"},{"n":"Meridiam","c":"France"},{"n":"Mérieux Equity Partners","c":"France"},{"n":"Mill Point Capital","c":"United States"},{"n":"Mircap Partners","c":"France"},{"n":"Mirova","c":"France"},{"n":"MML Capital Partners","c":"France"},{"n":"Monograph Capital","c":"United Kingdom"},{"n":"Monomoy Capital Partners","c":"United States"},{"n":"Monroe Capital","c":"United States"},{"n":"Montana Capital Partners","c":"Switzerland"},{"n":"Montefiore Investment","c":"France"},{"n":"Morgan Stanley Investment Management","c":"United Kingdom"},{"n":"Motion Equity Partners","c":"France"},{"n":"Motive Partners","c":"United States"},{"n":"MTIP","c":"Switzerland"},{"n":"Muus Climate Partners","c":"United States"},{"n":"Muzinich & Co.","c":"United States"},{"n":"MV Credit Partners","c":"United Kingdom"},{"n":"Nauta","c":"United Kingdom"},{"n":"Nautic Partners","c":"United States"},{"n":"Naxicap Partners","c":"France"},{"n":"NB Renaissance","c":"Italy"},{"n":"NCI","c":"France"},{"n":"Neos Partners","c":"United States"},{"n":"Neuberger Berman","c":"United States"},{"n":"New 2nd Capital","c":"United States"},{"n":"New Enterprise Associates","c":"United States"},{"n":"Newvest","c":"United States"},{"n":"Next Gear Ventures","c":"Israel"},{"n":"NextStage AM","c":"France"},{"n":"NGP Energy Capital Management","c":"United States"},{"n":"NorthEdge Capital","c":"United Kingdom"},{"n":"Norvestor","c":"Norway"},{"n":"Norwest","c":"United States"},{"n":"Notable Capital","c":"United States"},{"n":"Noteus Partners","c":"France"},{"n":"Notion Capital","c":"United Kingdom"},{"n":"Novacap","c":"Canada"},{"n":"Nuveen","c":"United Kingdom"},{"n":"Oak HC/FT","c":"United States"},{"n":"Oak Hill Capital","c":"United States"},{"n":"Oakley Capital","c":"United Kingdom"},{"n":"OceanSound Partners","c":"United States"},{"n":"Oddo BHF","c":"France"},{"n":"Odyssey Investment Partners","c":"United States"},{"n":"Omnes","c":"France"},{"n":"One Equity Partners","c":"United States"},{"n":"One Rock Capital Partners","c":"United States"},{"n":"Onex","c":"United Kingdom"},{"n":"OpenGate Capital","c":"United States"},{"n":"Oraxys","c":"Luxembourg"},{"n":"OrbiMed Advisors","c":"United States"},{"n":"Origine Partners","c":"France"},{"n":"Ouest Croissance","c":"France"},{"n":"Pacific Equity Partners","c":"Australia"},{"n":"PAG","c":"Hong Kong"},{"n":"Paine Schwartz Partners","c":"United States"},{"n":"Pantheon","c":"United Kingdom"},{"n":"Paradigm","c":"United States"},{"n":"Park Square Capital","c":"United Kingdom"},{"n":"Parquest","c":"France"},{"n":"Partech","c":"France"},{"n":"Parthenon Capital Partners","c":"United States"},{"n":"Patient Square Capital","c":"United States"},{"n":"Patria Investments","c":"United Kingdom"},{"n":"Peak Rock Capital","c":"United States"},{"n":"Peak XV Partners","c":"India"},{"n":"Pemberton Asset Management","c":"United Kingdom"},{"n":"PGIM","c":"United Kingdom"},{"n":"PIMCO","c":"United Kingdom"},{"n":"PineBridge Investments","c":"United Kingdom"},{"n":"Platina","c":"France"},{"n":"Pollen Street Capital","c":"United Kingdom"},{"n":"Polus Capital Management","c":"United Kingdom"},{"n":"Pomona Capital","c":"United Kingdom"},{"n":"Portfolio Advisors","c":"Switzerland"},{"n":"Primavera Capital Group","c":"Hong Kong"},{"n":"Private Corner","c":"France"},{"n":"PSG","c":"United States"},{"n":"Qiming Venture Partners","c":"China"},{"n":"Quadrille Capital","c":"France"},{"n":"Quadrivio Group","c":"Italy"},{"n":"Quaero Capital","c":"Switzerland"},{"n":"Qualium Investissement","c":"France"},{"n":"Quantum Energy Partners","c":"United States"},{"n":"Raise","c":"France"},{"n":"Red Arts Capital","c":"United States"},{"n":"Red River West","c":"France"},{"n":"Redbird Capital Partners","c":"United States"},{"n":"Redfish Longterm Capital","c":"Italy"},{"n":"Reed Management","c":"France"},{"n":"Revaia","c":"France"},{"n":"Revelstoke Capital Partners","c":"United States"},{"n":"Reverence Capital Partners","c":"United States"},{"n":"RGreen Invest","c":"France"},{"n":"Rhône Group","c":"United States"},{"n":"Ribbit Capital","c":"United States"},{"n":"Ridgemont Equity Partners","c":"United States"},{"n":"Riello Investimenti SGR","c":"Italy"},{"n":"Ring Capital","c":"France"},{"n":"Rive Private Investment","c":"France"},{"n":"Rivean Capital","c":"Netherlands"},{"n":"Riverwood Capital","c":"United States"},{"n":"Rockby","c":"France"},{"n":"RUBICON Technology Partners","c":"United States"},{"n":"Sagard","c":"France"},{"n":"Sagard Partners","c":"Canada"},{"n":"Sapphire Ventures","c":"United States"},{"n":"SC Lowy","c":"Hong Kong"},{"n":"Schroders Capital","c":"United Kingdom"},{"n":"SCOR Investment Partners","c":"France"},{"n":"Scottish Equity Partners","c":"United Kingdom"},{"n":"Searchlight Capital Partners","c":"United States"},{"n":"Second Alpha Partners","c":"United States"},{"n":"Sentinel Capital Partners","c":"United States"},{"n":"Sequoia Capital","c":"United States"},{"n":"Serena","c":"France"},{"n":"Seven2","c":"France"},{"n":"Shamrock Capital","c":"United States"},{"n":"Shore Capital Partners","c":"United States"},{"n":"SHS Capital","c":"Germany"},{"n":"Sienna Investment Managers","c":"France"},{"n":"Siguler Guff","c":"United States"},{"n":"Siparex","c":"France"},{"n":"SK Capital Partners","c":"United States"},{"n":"SkyKnight Capital","c":"United States"},{"n":"Smalt Capital","c":"France"},{"n":"Source Code Capital","c":"China"},{"n":"Sparring Capital","c":"France"},{"n":"Spectrum Equity","c":"United States"},{"n":"Speedinvest","c":"Austria"},{"n":"Stanley Capital","c":"United Kingdom"},{"n":"STG","c":"United States"},{"n":"Stirling Square Capital Partners","c":"United Kingdom"},{"n":"Stonepeak","c":"United Kingdom"},{"n":"Strada Partners","c":"Belgium"},{"n":"Stride.VC","c":"United Kingdom"},{"n":"Stripes","c":"United States"},{"n":"Summa Equity","c":"Sweden"},{"n":"SWEN Capital Partners","c":"France"},{"n":"Swiss Life Asset Managers","c":"France"},{"n":"TCV","c":"United States"},{"n":"Technofounders","c":"France"},{"n":"Tenex Capital Management","c":"United States"},{"n":"The Column Group","c":"United States"},{"n":"The Jordan Company","c":"United States"},{"n":"The Riverside Company","c":"Belgium"},{"n":"The Sterling Group","c":"United States"},{"n":"The Vistria Group","c":"United States"},{"n":"The Yield Lab Europe","c":"Ireland"},{"n":"Three Hills Capital Partners","c":"United Kingdom"},{"n":"Thrive Capital","c":"United States"},{"n":"Tiger Global Management","c":"United States"},{"n":"Tikehau Capital","c":"France"},{"n":"TJC","c":"United States"},{"n":"Torquest Partners","c":"Canada"},{"n":"TowerBrook Capital Partners","c":"United States"},{"n":"Trispan","c":"United Kingdom"},{"n":"Trive Capital","c":"United States"},{"n":"Trivest Partners","c":"United States"},{"n":"Trocadero Capital Partners","c":"France"},{"n":"Truffle Capital","c":"France"},{"n":"TSG Consumer Partners","c":"United States"},{"n":"Turenne Groupe","c":"France"},{"n":"Ufenau Capital","c":"Switzerland"},{"n":"UI Investissement","c":"France"},{"n":"Una Terra","c":"Switzerland"},{"n":"Unigestion","c":"Switzerland"},{"n":"Unigrains","c":"France"},{"n":"United Bankers Fund Management","c":"Finland"},{"n":"Valar Ventures","c":"United States"},{"n":"Valor Equity Partners","c":"United States"},{"n":"Vauban Infrastructure Partners","c":"France"},{"n":"Vector Capital Management","c":"United States"},{"n":"Vendis Capital","c":"Belgium"},{"n":"Ventech","c":"France"},{"n":"Verdane","c":"Sweden"},{"n":"Vertex Holdings","c":"Singapore"},{"n":"Vespa Capital","c":"France"},{"n":"VI Partners","c":"Switzerland"},{"n":"Victory Asset Management","c":"Luxembourg"},{"n":"Vitruvian Partners","c":"United Kingdom"},{"n":"Vivalto Partners","c":"France"},{"n":"Vivo Capital","c":"United States"},{"n":"Warren Equity Partners","c":"United States"},{"n":"Waterland","c":"Netherlands"},{"n":"Wave Equity Partners","c":"United States"},{"n":"WCAS","c":"United States"},{"n":"Webster Equity Partners","c":"United States"},{"n":"Weinberg Capital Partners","c":"France"},{"n":"Wellington Management","c":"United Kingdom"},{"n":"Welsh, Carson, Anderson & Stowe","c":"United States"},{"n":"Westcap","c":"United States"},{"n":"White Star Capital","c":"United Kingdom"},{"n":"Wind Point Partners","c":"United States"},{"n":"Wynnchurch Capital","c":"United States"},{"n":"Xange","c":"France"},{"n":"Xerys Invest","c":"France"},{"n":"Xtal Strategies","c":"Italy"},{"n":"Y Combinator","c":"United States"},{"n":"Yingke Private Equity","c":"China"},{"n":"Yotta Capital","c":"France"},{"n":"Yttrium","c":"Germany"},{"n":"Zencap AM","c":"France"},{"n":"ZMC","c":"United States"}
            ];

            // Return featured firms first, then standard firms
            return [...featuredFirms, ...standardFirms];
        }

        /**
         * Render PE Firms in Database List
         */
        renderDatabaseFirms(firms = null) {
            const dbList = document.getElementById('nrt-database-list');
            const countEl = document.getElementById('nrt-db-count');
            if (!dbList) return;

            const allFirms = firms || this.getPEFirmsDatabase();
            const isLoggedIn = typeof nrtData !== 'undefined' && nrtData.isLoggedIn;

            let html = '';
            allFirms.forEach((firm, index) => {
                const initials = firm.n.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase();
                const isFeatured = firm.featured === true;
                // Lock non-featured firms for logged-out users (featured firms are always accessible)
                const isLocked = !isLoggedIn && !isFeatured;

                const dataAttrs = `data-firm-name="${this.escapeHtml(firm.n)}" data-firm-country="${firm.c}"` +
                    (firm.f ? ` data-firm-founded="${firm.f}"` : '') +
                    (firm.aum ? ` data-firm-aum="${this.escapeHtml(firm.aum)}"` : '') +
                    (firm.hq ? ` data-firm-hq="${this.escapeHtml(firm.hq)}"` : '') +
                    (firm.s ? ` data-firm-sectors="${this.escapeHtml(firm.s)}"` : '') +
                    (firm.d ? ` data-firm-desc="${this.escapeHtml(firm.d)}"` : '') +
                    (firm.w ? ` data-firm-website="${this.escapeHtml(firm.w)}"` : '') +
                    (isFeatured ? ` data-firm-featured="true"` : '') +
                    (isLocked ? ` data-locked="true"` : '');

                const lockIcon = isLocked ? `
                    <div class="nrt-story-lock">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                    </div>
                ` : '';

                html += `
                    <article class="nrt-story-card nrt-firm-card${isFeatured ? ' nrt-firm-featured' : ''}${isLocked ? ' is-locked' : ''}" ${dataAttrs}>
                        <div class="nrt-firm-avatar">${initials}</div>
                        <div class="nrt-firm-info">
                            <h3 class="nrt-story-title">${this.escapeHtml(firm.n)}</h3>
                            <div class="nrt-firm-meta">
                                <span class="nrt-firm-country">${firm.c}</span>
                                ${isFeatured && firm.aum ? `<span class="nrt-firm-aum-badge">${firm.aum}</span>` : ''}
                            </div>
                        </div>
                        ${lockIcon}
                    </article>
                `;
            });

            dbList.innerHTML = html;
            if (countEl) {
                countEl.textContent = `${allFirms.length} firm${allFirms.length !== 1 ? 's' : ''}`;
            }
        }

        /**
         * Filter Database Firms
         */
        filterDatabaseFirms() {
            const searchInput = document.getElementById('nrt-db-search');
            const activeFilter = this.terminal.querySelector('[data-db-region].is-active');

            const searchTerm = (searchInput?.value || '').toLowerCase().trim();
            const region = activeFilter?.dataset.dbRegion || 'all';

            const allFirms = this.getPEFirmsDatabase();
            const mainRegions = ['United States', 'United Kingdom', 'France', 'Germany'];

            const filtered = allFirms.filter(firm => {
                // Search filter
                const matchesSearch = !searchTerm || firm.n.toLowerCase().includes(searchTerm);

                // Region filter
                let matchesRegion = true;
                if (region !== 'all') {
                    if (region === 'other') {
                        matchesRegion = !mainRegions.includes(firm.c);
                    } else {
                        matchesRegion = firm.c === region;
                    }
                }

                return matchesSearch && matchesRegion;
            });

            this.renderDatabaseFirms(filtered);
        }

        /**
         * Select a PE Firm and show details in right panel
         */
        selectFirm(card) {
            // Update active state
            this.terminal.querySelectorAll('.nrt-firm-card').forEach(c => c.classList.remove('is-active'));
            card.classList.add('is-active');

            const firmName = card.dataset.firmName;

            // Check if card is locked (for logged-out users)
            if (card.dataset.locked === 'true') {
                this.showSubscriptionBox(card);
                // Open mobile article view for subscription box too
                this.openMobileArticle(firmName);
                return;
            }

            // Get all firm data from card attributes
            const firmCountry = card.dataset.firmCountry;
            const firmFounded = card.dataset.firmFounded;
            const firmAum = card.dataset.firmAum;
            const firmHq = card.dataset.firmHq;
            const firmSectors = card.dataset.firmSectors;
            const firmDesc = card.dataset.firmDesc;
            const firmWebsite = card.dataset.firmWebsite;
            const isFeatured = card.dataset.firmFeatured === 'true';

            // Get content inner panel
            const contentInner = document.getElementById('nrt-content-inner');
            if (!contentInner) return;

            // Render firm details
            const initials = firmName.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase();

            // Build sectors HTML if available
            let sectorsHtml = '';
            if (firmSectors) {
                const sectorsList = firmSectors.split(', ').map(s =>
                    `<span class="nrt-sector-tag">${this.escapeHtml(s)}</span>`
                ).join('');
                sectorsHtml = `
                    <div class="nrt-firm-detail-section">
                        <h3>Investment Focus</h3>
                        <div class="nrt-sector-tags">${sectorsList}</div>
                    </div>
                `;
            }

            // Build facts grid
            let factsHtml = '';
            if (firmAum) {
                factsHtml += `
                    <div class="nrt-firm-fact">
                        <span class="nrt-firm-fact-label">AUM</span>
                        <span class="nrt-firm-fact-value nrt-firm-fact-highlight">${this.escapeHtml(firmAum)}</span>
                    </div>
                `;
            }
            if (firmFounded) {
                factsHtml += `
                    <div class="nrt-firm-fact">
                        <span class="nrt-firm-fact-label">Founded</span>
                        <span class="nrt-firm-fact-value">${firmFounded}</span>
                    </div>
                `;
            }
            if (firmHq) {
                factsHtml += `
                    <div class="nrt-firm-fact">
                        <span class="nrt-firm-fact-label">Headquarters</span>
                        <span class="nrt-firm-fact-value">${this.escapeHtml(firmHq)}</span>
                    </div>
                `;
            }
            factsHtml += `
                <div class="nrt-firm-fact">
                    <span class="nrt-firm-fact-label">Region</span>
                    <span class="nrt-firm-fact-value">${this.escapeHtml(firmCountry)}</span>
                </div>
            `;
            factsHtml += `
                <div class="nrt-firm-fact">
                    <span class="nrt-firm-fact-label">Type</span>
                    <span class="nrt-firm-fact-value">Fund Manager</span>
                </div>
            `;

            // Website link if available
            let websiteHtml = '';
            if (firmWebsite) {
                websiteHtml = `
                    <div class="nrt-firm-detail-section">
                        <a href="https://${firmWebsite}" target="_blank" rel="noopener noreferrer" class="nrt-firm-website-btn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="2" y1="12" x2="22" y2="12"/>
                                <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                            </svg>
                            Visit ${firmWebsite}
                        </a>
                    </div>
                `;
            }

            // Description - use provided description or generate basic one
            const description = firmDesc || `${firmName} is a private equity firm based in ${firmCountry}.`;

            contentInner.innerHTML = `
                <div class="nrt-firm-detail${isFeatured ? ' nrt-firm-detail-featured' : ''}">
                    <div class="nrt-firm-detail-header">
                        <div class="nrt-firm-detail-avatar${isFeatured ? ' nrt-avatar-featured' : ''}">${initials}</div>
                        <div class="nrt-firm-detail-info">
                            <h1 class="nrt-firm-detail-name">${this.escapeHtml(firmName)}</h1>
                            <div class="nrt-firm-detail-meta">
                                <span class="nrt-firm-detail-type">Private Equity</span>
                                ${isFeatured ? '<span class="nrt-firm-featured-badge">Top Firm</span>' : ''}
                                <span class="nrt-firm-detail-location">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                        <circle cx="12" cy="10" r="3"/>
                                    </svg>
                                    ${this.escapeHtml(firmHq || firmCountry)}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="nrt-firm-detail-section">
                        <h3>Overview</h3>
                        <p class="nrt-firm-description">${this.escapeHtml(description)}</p>
                    </div>

                    <div class="nrt-firm-detail-section">
                        <h3>Key Facts</h3>
                        <div class="nrt-firm-facts">${factsHtml}</div>
                    </div>

                    ${sectorsHtml}

                    <!-- Related Content Section (loaded via AJAX) -->
                    <div class="nrt-firm-related" id="nrt-firm-related" data-firm-name="${this.escapeHtml(firmName)}">
                        <div class="nrt-firm-related-loading">
                            <div class="nrt-spinner-small"></div>
                            <span>Loading related content...</span>
                        </div>
                    </div>

                    ${websiteHtml}
                </div>
            `;

            // Open mobile article view if on mobile
            this.openMobileArticle(firmName);

            // Fetch related content via AJAX
            this.loadFirmRelatedContent(firmName);
        }

        /**
         * Load related jobs, news, and deals for a firm via AJAX
         */
        async loadFirmRelatedContent(firmName) {
            const container = document.getElementById('nrt-firm-related');
            if (!container) return;

            try {
                const formData = new FormData();
                formData.append('action', 'nrt_get_firm_content');
                formData.append('firm_name', firmName);

                const response = await fetch(this.ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });

                const result = await response.json();

                if (result.success && result.data) {
                    this.renderFirmRelatedContent(container, result.data, firmName);
                } else {
                    container.innerHTML = ''; // Hide section if no content
                }
            } catch (error) {
                console.error('Error loading firm content:', error);
                container.innerHTML = ''; // Hide section on error
            }
        }

        /**
         * Render related content sections
         */
        renderFirmRelatedContent(container, data, firmName) {
            const { jobs, news, deals, totals } = data;
            const hasContent = totals.jobs > 0 || totals.news > 0 || totals.deals > 0;

            if (!hasContent) {
                container.innerHTML = '';
                return;
            }

            let html = '';

            // Jobs section
            if (jobs && jobs.length > 0) {
                html += `
                    <div class="nrt-firm-related-section">
                        <h3>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/>
                                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                            </svg>
                            Open Positions
                            <span class="nrt-related-count">${jobs.length}</span>
                        </h3>
                        <div class="nrt-related-list">
                            ${jobs.map(job => `
                                <a href="${job.link}" class="nrt-related-item nrt-related-job" target="_blank">
                                    <div class="nrt-related-item-title">${this.escapeHtml(job.title)}</div>
                                    <div class="nrt-related-item-meta">
                                        ${job.location ? `<span>${this.escapeHtml(job.location)}</span>` : ''}
                                        <span>${job.date}</span>
                                    </div>
                                </a>
                            `).join('')}
                        </div>
                    </div>
                `;
            }

            // News section
            if (news && news.length > 0) {
                html += `
                    <div class="nrt-firm-related-section">
                        <h3>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <line x1="16" y1="13" x2="8" y2="13"/>
                                <line x1="16" y1="17" x2="8" y2="17"/>
                            </svg>
                            Recent News
                            <span class="nrt-related-count">${news.length}</span>
                        </h3>
                        <div class="nrt-related-list">
                            ${news.map(item => `
                                <a href="${item.link}" class="nrt-related-item nrt-related-news" target="_blank">
                                    <div class="nrt-related-item-title">${this.escapeHtml(item.title)}</div>
                                    <div class="nrt-related-item-excerpt">${this.escapeHtml(item.excerpt || '')}</div>
                                    <div class="nrt-related-item-meta">
                                        <span>${item.date}</span>
                                    </div>
                                </a>
                            `).join('')}
                        </div>
                    </div>
                `;
            }

            // Deals section
            if (deals && deals.length > 0) {
                html += `
                    <div class="nrt-firm-related-section">
                        <h3>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                            </svg>
                            Recent Deals
                            <span class="nrt-related-count">${deals.length}</span>
                        </h3>
                        <div class="nrt-related-list">
                            ${deals.map(deal => `
                                <a href="${deal.link}" class="nrt-related-item nrt-related-deal" target="_blank">
                                    <div class="nrt-related-item-title">${this.escapeHtml(deal.title)}</div>
                                    ${deal.type || deal.acquirer || deal.target ? `
                                        <div class="nrt-related-item-deal-info">
                                            ${deal.type ? `<span class="nrt-deal-type">${this.escapeHtml(deal.type)}</span>` : ''}
                                            ${deal.acquirer && deal.target ? `<span class="nrt-deal-parties">${this.escapeHtml(deal.acquirer)} → ${this.escapeHtml(deal.target)}</span>` : ''}
                                        </div>
                                    ` : ''}
                                    <div class="nrt-related-item-meta">
                                        ${deal.value ? `<span class="nrt-deal-value">${this.escapeHtml(deal.value)}</span>` : ''}
                                        <span>${deal.date}</span>
                                    </div>
                                </a>
                            `).join('')}
                        </div>
                    </div>
                `;
            }

            container.innerHTML = html;
        }

        /**
         * Filter profile feed by type
         */
        filterProfileFeed(filterType) {
            // Re-fetch feed with new filter
            this.loadPersonalizedFeed(filterType);
        }

        /**
         * Load personalized feed from API
         */
        async loadPersonalizedFeed(filter = 'all') {
            const feedContainer = document.getElementById('nrt-profile-feed');
            if (!feedContainer) return;

            // Show loading state
            feedContainer.innerHTML = `
                <div class="nrt-profile-feed-loading">
                    <div class="nrt-spinner"></div>
                    <p>Loading your personalized feed...</p>
                </div>
            `;

            try {
                const response = await fetch(`/wp-json/sffc/v1/feed?filter=${filter}&limit=20`);
                const data = await response.json();

                if (data.success && data.feed) {
                    this.renderFeed(data.feed, data.has_preferences);
                } else {
                    this.renderFeedEmpty();
                }
            } catch (error) {
                console.error('Error loading feed:', error);
                this.renderFeedError();
            }

            // Also load saved items
            this.loadSavedItems();
        }

        /**
         * Render the personalized feed
         */
        renderFeed(groupedFeed, hasPreferences) {
            const feedContainer = document.getElementById('nrt-profile-feed');
            if (!feedContainer) return;

            // Check if feed is empty
            const totalItems = Object.values(groupedFeed).reduce((sum, items) => sum + items.length, 0);

            if (totalItems === 0) {
                this.renderFeedEmpty(hasPreferences);
                return;
            }

            let html = '';

            for (const [dateGroup, items] of Object.entries(groupedFeed)) {
                html += `<div class="nrt-feed-date-group">
                    <h4 class="nrt-feed-date-label">${dateGroup}</h4>
                    <div class="nrt-feed-items">`;

                for (const item of items) {
                    html += this.renderFeedItem(item);
                }

                html += `</div></div>`;
            }

            feedContainer.innerHTML = html;

            // Bind click events for feed items
            this.bindFeedItemEvents(feedContainer);
        }

        /**
         * Render a single feed item
         */
        renderFeedItem(item) {
            const typeIcons = {
                news: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                    <path d="M19 20H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v1m2 13a2 2 0 0 1-2-2V7m2 13a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-2"/>
                </svg>`,
                job: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/>
                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                </svg>`,
                deal: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                    <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                </svg>`
            };

            const typeLabels = {
                news: 'News',
                job: 'Job',
                deal: 'Deal'
            };

            const matchTags = (item.matches && item.matches.length > 0)
                ? `<div class="nrt-feed-item-matches">
                    <span class="nrt-feed-match-label">Matches:</span>
                    ${item.matches.map(m => `<span class="nrt-feed-match-tag">${m}</span>`).join('')}
                   </div>`
                : '';

            const subtitle = item.type === 'job' && item.company
                ? `<span class="nrt-feed-item-company">${item.company}${item.location ? ' · ' + item.location : ''}</span>`
                : item.source
                    ? `<span class="nrt-feed-item-source">${item.source}</span>`
                    : '';

            return `
                <article class="nrt-feed-item" data-type="${item.type}" data-id="${item.id}" data-url="${item.url}">
                    <div class="nrt-feed-item-icon ${item.type}">
                        ${typeIcons[item.type] || typeIcons.news}
                    </div>
                    <div class="nrt-feed-item-content">
                        <div class="nrt-feed-item-header">
                            <span class="nrt-feed-item-type">${typeLabels[item.type] || 'News'}</span>
                            <span class="nrt-feed-item-date">${item.date_display}</span>
                        </div>
                        <h5 class="nrt-feed-item-title">${item.title}</h5>
                        ${subtitle}
                        ${item.excerpt ? `<p class="nrt-feed-item-excerpt">${item.excerpt}</p>` : ''}
                        ${matchTags}
                    </div>
                    <div class="nrt-feed-item-actions">
                        <button type="button" class="nrt-feed-save-btn" data-action="save" data-item-id="${item.id}" data-item-type="${item.type}" title="Save for later">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
                            </svg>
                        </button>
                    </div>
                </article>
            `;
        }

        /**
         * Render empty feed state
         */
        renderFeedEmpty(hasPreferences = false) {
            const feedContainer = document.getElementById('nrt-profile-feed');
            if (!feedContainer) return;

            if (hasPreferences) {
                feedContainer.innerHTML = `
                    <div class="nrt-feed-empty">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48">
                            <circle cx="11" cy="11" r="8"/>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                        <p class="nrt-feed-empty-title">No matching content yet</p>
                        <p class="nrt-feed-empty-text">We're curating content based on your preferences. Check back soon!</p>
                    </div>
                `;
            } else {
                feedContainer.innerHTML = `
                    <div class="nrt-feed-empty">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                        </svg>
                        <p class="nrt-feed-empty-title">Personalize your feed</p>
                        <p class="nrt-feed-empty-text">Add topics and preferences to see curated content tailored for you.</p>
                        <button type="button" class="nrt-profile-btn nrt-profile-btn--primary" data-action="manage-topics">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                <line x1="12" y1="5" x2="12" y2="19"/>
                                <line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                            Add Topics
                        </button>
                    </div>
                `;
            }
        }

        /**
         * Render feed error state
         */
        renderFeedError() {
            const feedContainer = document.getElementById('nrt-profile-feed');
            if (!feedContainer) return;

            feedContainer.innerHTML = `
                <div class="nrt-feed-empty">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    <p class="nrt-feed-empty-title">Unable to load feed</p>
                    <p class="nrt-feed-empty-text">Please try again later.</p>
                    <button type="button" class="nrt-profile-btn" onclick="window.NRT?.loadPersonalizedFeed()">
                        Try Again
                    </button>
                </div>
            `;
        }

        /**
         * Bind events for feed items
         */
        bindFeedItemEvents(container) {
            // Click on feed item to open
            container.querySelectorAll('.nrt-feed-item').forEach(item => {
                item.addEventListener('click', (e) => {
                    // Don't navigate if clicking save button
                    if (e.target.closest('.nrt-feed-save-btn')) return;

                    const url = item.dataset.url;
                    const type = item.dataset.type;
                    const id = item.dataset.id;

                    if (type === 'job') {
                        // Load job in the content panel
                        this.switchTab('jobs');
                        setTimeout(() => {
                            const jobCard = this.terminal.querySelector(`.nrt-job-card[data-job-id="${id}"]`);
                            if (jobCard) {
                                this.selectJob(jobCard);
                            } else if (url) {
                                window.location.href = url;
                            }
                        }, 100);
                    } else if (type === 'news' || type === 'deal') {
                        // Load story in the content panel
                        this.switchTab('news');
                        setTimeout(() => {
                            const storyCard = this.terminal.querySelector(`.nrt-story-card[data-story-id="${id}"]`);
                            if (storyCard) {
                                this.selectStory(storyCard);
                            } else if (url) {
                                window.location.href = url;
                            }
                        }, 100);
                    }
                });
            });

            // Save button clicks
            container.querySelectorAll('.nrt-feed-save-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const itemId = btn.dataset.itemId;
                    const itemType = btn.dataset.itemType;
                    this.toggleSavedItem(itemId, itemType, btn);
                });
            });
        }

        /**
         * Toggle saved item via API
         */
        async toggleSavedItem(itemId, itemType, btn) {
            try {
                const response = await fetch('/wp-json/sffc/v1/saved/toggle', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.nrtData?.nonce || ''
                    },
                    body: JSON.stringify({
                        item_id: parseInt(itemId),
                        item_type: itemType
                    })
                });

                const data = await response.json();

                if (data.success || data.is_saved !== undefined) {
                    // Update button state
                    btn.classList.toggle('is-saved', data.is_saved);
                    btn.title = data.is_saved ? 'Remove from saved' : 'Save for later';

                    // Update saved items section
                    this.loadSavedItems();
                }
            } catch (error) {
                console.error('Error toggling saved item:', error);
            }
        }

        /**
         * Load saved items for the profile
         */
        async loadSavedItems() {
            const savedContainer = document.getElementById('nrt-profile-saved');
            if (!savedContainer) return;

            try {
                const response = await fetch('/wp-json/sffc/v1/saved?type=all');
                const data = await response.json();

                const allItems = [
                    ...(data.articles || []),
                    ...(data.jobs || []),
                    ...(data.deals || [])
                ];

                if (allItems.length === 0) {
                    savedContainer.innerHTML = `
                        <p class="nrt-profile-saved-empty">You haven't saved any items yet. Save articles and jobs to read later.</p>
                    `;
                    return;
                }

                // Sort by most recent and limit to 6
                const displayItems = allItems.slice(0, 6);

                let html = '<div class="nrt-saved-items-grid">';
                for (const item of displayItems) {
                    html += `
                        <a href="${item.url}" class="nrt-saved-item">
                            <span class="nrt-saved-item-type">${item.type}</span>
                            <h6 class="nrt-saved-item-title">${item.title}</h6>
                            <span class="nrt-saved-item-date">${item.date}</span>
                        </a>
                    `;
                }
                html += '</div>';

                if (allItems.length > 6) {
                    html += `<p class="nrt-saved-items-more">+ ${allItems.length - 6} more saved items</p>`;
                }

                savedContainer.innerHTML = html;

            } catch (error) {
                console.error('Error loading saved items:', error);
                savedContainer.innerHTML = `
                    <p class="nrt-profile-saved-empty">Unable to load saved items.</p>
                `;
            }
        }

        selectJob(card) {
            // Update active state
            const allCards = this.terminal.querySelectorAll('.nrt-job-card');
            allCards.forEach(c => c.classList.remove('is-active'));
            card.classList.add('is-active');

            // Get job title for mobile back button
            const jobTitle = card.querySelector('.nrt-job-title')?.textContent || 'Job';

            const jobId = card.dataset.jobId;
            this.loadJobContent(jobId);

            // Open mobile article view with slide animation
            this.openMobileArticle(jobTitle);
        }

        async loadJobContent(jobId) {
            // Use content-inner for mobile compatibility (preserves back nav)
            const contentInner = document.getElementById('nrt-content-inner');
            const contentPanel = contentInner || document.getElementById('nrt-content-panel');
            if (!contentPanel) return;

            // Check cache first
            const cacheKey = `nrt_job_${jobId}`;
            const cached = this.getFromCache(cacheKey);
            if (cached) {
                contentPanel.innerHTML = cached;
                this.initCharts();
                return;
            }

            // Show loading with percentage
            contentPanel.innerHTML = `
                <div class="nrt-loading">
                    <div class="nrt-loading-content">
                        <div class="nrt-loading-percent">0%</div>
                        <div class="nrt-loading-bar">
                            <div class="nrt-loading-progress"></div>
                        </div>
                        <div class="nrt-loading-text">Loading job details...</div>
                    </div>
                </div>
            `;

            // Start progress animation
            const progressEl = contentPanel.querySelector('.nrt-loading-progress');
            const percentEl = contentPanel.querySelector('.nrt-loading-percent');
            const textEl = contentPanel.querySelector('.nrt-loading-text');
            let progress = 0;

            const progressInterval = setInterval(() => {
                if (progress < 90) {
                    const increment = progress < 30 ? 8 : progress < 60 ? 5 : 2;
                    progress = Math.min(progress + increment, 90);
                    if (progressEl) progressEl.style.width = `${progress}%`;
                    if (percentEl) percentEl.textContent = `${Math.round(progress)}%`;

                    if (textEl) {
                        if (progress < 30) textEl.textContent = 'Loading job details...';
                        else if (progress < 60) textEl.textContent = 'Analyzing requirements...';
                        else textEl.textContent = 'Preparing insights...';
                    }
                }
            }, 100);

            // Get AJAX URL
            const ajaxUrl = window.sffc_frontend?.ajaxUrl ||
                           window.nrt_ajax?.ajax_url ||
                           '/wp-admin/admin-ajax.php';

            try {
                const response = await fetch(ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'nrt_load_job',
                        job_id: jobId,
                        nonce: window.nrt_ajax?.nonce || ''
                    })
                });

                const data = await response.json();

                clearInterval(progressInterval);

                if (data.success && data.data.html) {
                    // Complete progress
                    if (progressEl) progressEl.style.width = '100%';
                    if (percentEl) percentEl.textContent = '100%';
                    if (textEl) textEl.textContent = 'Complete';

                    setTimeout(() => {
                        contentPanel.innerHTML = data.data.html;
                        this.initCharts();
                        this.saveToCache(cacheKey, data.data.html);
                    }, 200);
                } else {
                    contentPanel.innerHTML = '<div class="nrt-content-empty">Failed to load job details</div>';
                }
            } catch (error) {
                clearInterval(progressInterval);
                console.error('Error loading job:', error);
                contentPanel.innerHTML = '<div class="nrt-content-empty">Failed to load job details</div>';
            }
        }

        applyJobFilters() {
            const cards = this.terminal.querySelectorAll('.nrt-job-card');
            let visibleCount = 0;

            // Location mapping for filter matching
            const locationMap = {
                'dubai': ['dubai', 'difc', 'uae', 'united arab emirates'],
                'abu-dhabi': ['abu dhabi', 'adgm', 'uae', 'united arab emirates'],
                'riyadh': ['riyadh', 'saudi', 'ksa', 'saudi arabia'],
                'doha': ['doha', 'qatar', 'qfc'],
                'cairo': ['cairo', 'egypt', 'giza']
            };

            cards.forEach(card => {
                const jobFunction = card.dataset.jobFunction || '';
                const jobLevel = card.dataset.jobLevel || '';
                const jobRegion = card.dataset.jobRegion || '';
                const title = card.querySelector('.nrt-story-title')?.textContent.toLowerCase() || '';
                const company = card.querySelector('.nrt-job-company')?.textContent.toLowerCase() || '';
                const locationEl = card.querySelector('.nrt-job-location');
                const jobLocation = locationEl ? locationEl.textContent.toLowerCase() : '';

                // Check function filter
                const matchesFunction = this.currentJobFunction === 'all' ||
                                       jobFunction.includes(this.currentJobFunction);

                // Check location filter
                let matchesLocation = this.currentJobLocation === 'all';
                if (!matchesLocation && locationMap[this.currentJobLocation]) {
                    matchesLocation = locationMap[this.currentJobLocation].some(loc =>
                        jobLocation.includes(loc)
                    );
                }

                // Check level filter (multi-select, OR logic)
                const matchesLevel = this.selectedJobLevels.length === 0 ||
                                    this.selectedJobLevels.some(l => jobLevel.includes(l));

                // Check region filter (multi-select, OR logic)
                const matchesRegion = this.selectedJobRegions.length === 0 ||
                                     this.selectedJobRegions.some(r => jobRegion.includes(r));

                // Check search
                const matchesSearch = !this.searchQuery ||
                                     title.includes(this.searchQuery) ||
                                     company.includes(this.searchQuery) ||
                                     jobLocation.includes(this.searchQuery);

                const isVisible = matchesFunction && matchesLocation && matchesLevel && matchesRegion && matchesSearch;

                card.style.display = isVisible ? '' : 'none';
                if (isVisible) visibleCount++;
            });

            // Update count
            const countEl = this.terminal.querySelector('.nrt-tab-jobs .nrt-story-count');
            if (countEl) {
                countEl.textContent = `${visibleCount} opportunities`;
            }
        }

        updateJobFilterCount() {
            const count = this.selectedJobLevels.length + this.selectedJobRegions.length;
            const countDisplay = this.terminal.querySelector('.nrt-tab-jobs .nrt-filter-count');

            if (countDisplay) {
                if (count > 0) {
                    countDisplay.style.display = '';
                    countDisplay.querySelector('.nrt-filter-count-num').textContent = count;
                } else {
                    countDisplay.style.display = 'none';
                }
            }
        }

        async loadStoryContent(storyId) {
            // Use content-inner for mobile compatibility (preserves back nav)
            const contentInner = document.getElementById('nrt-content-inner');
            const contentPanel = contentInner || document.getElementById('nrt-content-panel');
            if (!contentPanel) return;

            // Check cache first
            const cacheKey = `nrt_story_${storyId}`;
            const cached = this.getFromCache(cacheKey);
            if (cached) {
                contentPanel.innerHTML = cached;
                this.initCharts();
                return;
            }

            // Show loading with percentage
            contentPanel.innerHTML = `
                <div class="nrt-loading">
                    <div class="nrt-loading-content">
                        <div class="nrt-loading-percent">0%</div>
                        <div class="nrt-loading-bar">
                            <div class="nrt-loading-progress"></div>
                        </div>
                        <div class="nrt-loading-text">Loading story...</div>
                    </div>
                </div>
            `;

            // Start progress animation
            const progressEl = contentPanel.querySelector('.nrt-loading-progress');
            const percentEl = contentPanel.querySelector('.nrt-loading-percent');
            const textEl = contentPanel.querySelector('.nrt-loading-text');
            let progress = 0;

            const progressInterval = setInterval(() => {
                if (progress < 90) {
                    // Slow down as we approach 90%
                    const increment = progress < 30 ? 8 : progress < 60 ? 5 : 2;
                    progress = Math.min(progress + increment, 90);
                    if (progressEl) progressEl.style.width = `${progress}%`;
                    if (percentEl) percentEl.textContent = `${Math.round(progress)}%`;

                    // Update loading text
                    if (textEl) {
                        if (progress < 30) textEl.textContent = 'Loading story...';
                        else if (progress < 60) textEl.textContent = 'Fetching content...';
                        else textEl.textContent = 'Preparing view...';
                    }
                }
            }, 100);

            // Get AJAX URL - try multiple sources
            const ajaxUrl = window.sffc_frontend?.ajaxUrl ||
                            window.sffc_ajax?.ajaxurl ||
                            window.ajaxurl ||
                            '/wp-admin/admin-ajax.php';

            // Get nonce - try multiple sources
            const nonce = window.sffc_frontend?.nonce ||
                          window.sffc_ajax?.nonce ||
                          '';

            try {
                const formData = new FormData();
                formData.append('action', 'nrt_load_story');
                formData.append('story_id', storyId);
                if (nonce) {
                    formData.append('nonce', nonce);
                }

                const response = await fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const data = await response.json();

                // Complete progress animation
                clearInterval(progressInterval);
                if (progressEl) progressEl.style.width = '100%';
                if (percentEl) percentEl.textContent = '100%';
                if (textEl) textEl.textContent = 'Complete';

                // Brief pause to show 100%
                await new Promise(resolve => setTimeout(resolve, 200));

                if (data.success && data.data.html) {
                    // Cache the content
                    this.saveToCache(cacheKey, data.data.html);
                    contentPanel.innerHTML = data.data.html;
                    this.initCharts();
                } else {
                    const errorMsg = data.data?.message || 'Failed to load story';
                    console.error('Load story error:', errorMsg);
                    contentPanel.innerHTML = `<div class="nrt-content-empty">${errorMsg}</div>`;
                }
            } catch (error) {
                clearInterval(progressInterval);
                console.error('Error loading story:', error);
                contentPanel.innerHTML = '<div class="nrt-content-empty">Error loading story. Please try again.</div>';
            }
        }

        // Cache management
        getFromCache(key) {
            try {
                const item = sessionStorage.getItem(key);
                if (!item) return null;

                const { data, expiry } = JSON.parse(item);
                if (Date.now() > expiry) {
                    sessionStorage.removeItem(key);
                    return null;
                }
                return data;
            } catch (e) {
                return null;
            }
        }

        saveToCache(key, data) {
            try {
                // Cache for 10 minutes
                const expiry = Date.now() + (10 * 60 * 1000);
                sessionStorage.setItem(key, JSON.stringify({ data, expiry }));
            } catch (e) {
                // Storage full or unavailable
                console.warn('Cache storage unavailable');
            }
        }

        clearCache() {
            try {
                const keys = Object.keys(sessionStorage);
                keys.forEach(key => {
                    if (key.startsWith('nrt_story_')) {
                        sessionStorage.removeItem(key);
                    }
                });
            } catch (e) {
                console.warn('Could not clear cache');
            }
        }

        setFilter(filter) {
            this.currentFilter = filter;
            this.applyFilters();
        }

        applyFilters() {
            const cards = this.terminal.querySelectorAll('.nrt-story-card');
            let visibleCount = 0;

            cards.forEach(card => {
                const type = card.dataset.type;
                const sector = card.dataset.sector || '';
                const region = card.dataset.region || '';
                const title = card.querySelector('.nrt-story-title')?.textContent.toLowerCase() || '';
                const excerpt = card.querySelector('.nrt-story-excerpt')?.textContent.toLowerCase() || '';

                let visible = true;

                // Type filter (single-select)
                if (this.currentType !== 'all' && type !== this.currentType) {
                    visible = false;
                }

                // Sector filter (multi-select - match any)
                if (this.selectedSectors.length > 0) {
                    const cardSector = sector.toLowerCase().replace(/\s+/g, '-');
                    if (!this.selectedSectors.some(s => cardSector.includes(s) || s.includes(cardSector))) {
                        visible = false;
                    }
                }

                // Region filter (multi-select - match any)
                if (this.selectedRegions.length > 0) {
                    const cardRegion = region.toLowerCase().replace(/\s+/g, '-');
                    if (!this.selectedRegions.some(r => cardRegion.includes(r) || r.includes(cardRegion))) {
                        visible = false;
                    }
                }

                // Search filter
                if (this.searchQuery) {
                    if (!title.includes(this.searchQuery) && !excerpt.includes(this.searchQuery)) {
                        visible = false;
                    }
                }

                card.style.display = visible ? '' : 'none';
                if (visible) visibleCount++;
            });

            // Update count
            const countEl = this.terminal.querySelector('.nrt-story-count');
            if (countEl) {
                countEl.textContent = `${visibleCount} stories`;
            }
        }

        updateFilterCount() {
            const total = this.selectedSectors.length + this.selectedRegions.length;
            const countEl = this.terminal.querySelector('.nrt-filter-count');
            const countNum = this.terminal.querySelector('.nrt-filter-count-num');

            if (countEl && countNum) {
                if (total > 0) {
                    countEl.style.display = 'flex';
                    countNum.textContent = total;
                } else {
                    countEl.style.display = 'none';
                }
            }
        }

        refresh() {
            const refreshBtn = document.getElementById('nrt-refresh');
            if (refreshBtn) {
                refreshBtn.classList.add('is-loading');
                refreshBtn.querySelector('svg').style.animation = 'spin 0.8s linear infinite';
            }

            // Reload the page for now
            setTimeout(() => {
                window.location.reload();
            }, 500);
        }

        loadMore() {
            const loadMoreBtn = document.getElementById('nrt-load-more');
            if (!loadMoreBtn || loadMoreBtn.classList.contains('is-loading')) return;

            // Get current story count
            const storiesList = this.terminal.querySelector('.nrt-stories-list');
            if (!storiesList) return;

            const currentStories = storiesList.querySelectorAll('.nrt-story-card');
            const offset = currentStories.length;

            // Get active filter type
            const activeTypeFilter = this.terminal.querySelector('.nrt-filter-chip[data-type].is-active');
            const type = activeTypeFilter?.dataset.type || 'all';

            // Show loading state
            loadMoreBtn.classList.add('is-loading');
            const originalText = loadMoreBtn.textContent;
            loadMoreBtn.textContent = 'Loading...';

            // Make AJAX request
            const formData = new FormData();
            formData.append('action', 'nrt_load_more_stories');
            formData.append('offset', offset);
            formData.append('limit', 12);
            formData.append('type', type);

            fetch(window.sffc_frontend?.ajaxUrl || '/wp-admin/admin-ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.stories && data.data.stories.length > 0) {
                    // Append new stories to the list
                    data.data.stories.forEach(story => {
                        const storyCard = this.createStoryCard(story);
                        storiesList.appendChild(storyCard);
                    });

                    // Update story count
                    const countEl = this.terminal.querySelector('.nrt-story-count');
                    if (countEl) {
                        const newCount = storiesList.querySelectorAll('.nrt-story-card').length;
                        countEl.textContent = `${newCount} stories`;
                    }

                    // Hide button if no more stories
                    if (!data.data.has_more) {
                        loadMoreBtn.style.display = 'none';
                    }
                } else {
                    // No more stories
                    loadMoreBtn.style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Error loading more stories:', error);
            })
            .finally(() => {
                loadMoreBtn.classList.remove('is-loading');
                loadMoreBtn.textContent = originalText;
            });
        }

        /**
         * Create a story card element from story data
         */
        createStoryCard(story) {
            const article = document.createElement('article');
            article.className = 'nrt-story-card';
            article.dataset.storyId = story.id;
            article.dataset.type = story.type;

            const typeLabels = { news: 'News', deal: 'Deal', signal: 'Signal' };
            const typeLabel = typeLabels[story.type] || 'News';

            article.innerHTML = `
                <div class="nrt-story-header">
                    <span class="nrt-story-type nrt-story-type--${story.type}">${typeLabel}</span>
                    <time class="nrt-story-time">${story.relative_time} ago</time>
                </div>
                <h3 class="nrt-story-title">${this.escapeHtml(story.title)}</h3>
                ${story.company ? `<p class="nrt-story-company">${this.escapeHtml(story.company)}</p>` : ''}
                ${story.excerpt ? `<p class="nrt-story-excerpt">${this.escapeHtml(story.excerpt)}</p>` : ''}
                ${story.deal_value ? `
                    <div class="nrt-story-meta">
                        <span class="nrt-story-value">${this.escapeHtml(story.deal_value)}</span>
                    </div>
                ` : ''}
            `;

            // Add click handler
            article.addEventListener('click', () => {
                this.selectStory(article);
            });

            return article;
        }

        handleAction(action) {
            const articleEl = this.terminal.querySelector('.nrt-article');
            const storyId = articleEl?.dataset.storyId;

            switch (action) {
                case 'save':
                    this.saveStory(storyId);
                    break;
                case 'share':
                    this.shareStory(storyId);
                    break;
            }
        }

        saveStory(storyId) {
            // Show save confirmation
            this.showToast('Story saved to your reading list', 'success');
        }

        shareStory(storyId) {
            const articleEl = this.terminal.querySelector('.nrt-article');
            const title = articleEl?.querySelector('.nrt-article-title')?.textContent || 'Story';
            const url = window.location.href;

            if (navigator.share) {
                navigator.share({
                    title: title,
                    url: url
                });
            } else {
                // Fallback: copy to clipboard
                navigator.clipboard.writeText(url).then(() => {
                    alert('Link copied to clipboard');
                });
            }
        }

        // ============================================
        // PDF DOWNLOAD (Browser-based)
        // ============================================
        downloadPDF() {
            const article = this.terminal.querySelector('.nrt-article');
            if (!article) return;

            const title = article.querySelector('.nrt-article-title')?.textContent || 'Research Report';
            const fileName = this.sanitizeFileName(title) + '.pdf';

            // Create a print-friendly version
            const printWindow = window.open('', '_blank');
            if (!printWindow) {
                alert('Please allow popups to download PDF');
                return;
            }

            // Get the report view content
            const reportView = article.querySelector('.nrt-report-view');
            const header = article.querySelector('.nrt-article-header');

            // Build print HTML
            const printContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>${this.escapeHtml(title)}</title>
                    <style>
                        * { box-sizing: border-box; margin: 0; padding: 0; }
                        body {
                            font-family: 'Georgia', 'Times New Roman', serif;
                            font-size: 11pt;
                            line-height: 1.6;
                            color: #1a1a1a;
                            padding: 40px;
                            max-width: 800px;
                            margin: 0 auto;
                        }
                        .header {
                            border-bottom: 2px solid #0f2137;
                            padding-bottom: 20px;
                            margin-bottom: 24px;
                        }
                        .logo {
                            font-family: Arial, sans-serif;
                            font-size: 14pt;
                            font-weight: bold;
                            color: #0f2137;
                            margin-bottom: 16px;
                        }
                        .meta-row {
                            font-family: Arial, sans-serif;
                            font-size: 9pt;
                            color: #666;
                            margin-bottom: 12px;
                        }
                        .meta-badge {
                            display: inline-block;
                            background: #0f2137;
                            color: #fff;
                            padding: 3px 8px;
                            border-radius: 3px;
                            font-size: 8pt;
                            text-transform: uppercase;
                            margin-right: 8px;
                        }
                        h1 {
                            font-size: 20pt;
                            font-weight: 600;
                            color: #0f2137;
                            margin-bottom: 16px;
                            line-height: 1.3;
                        }
                        .byline {
                            font-family: Arial, sans-serif;
                            font-size: 10pt;
                            color: #555;
                            margin-bottom: 8px;
                        }
                        .summary-box {
                            background: #f5f5f5;
                            border-left: 4px solid #0f2137;
                            padding: 16px 20px;
                            margin: 24px 0;
                        }
                        .summary-title {
                            font-family: Arial, sans-serif;
                            font-size: 10pt;
                            font-weight: bold;
                            color: #0f2137;
                            margin-bottom: 8px;
                            text-transform: uppercase;
                        }
                        .metrics {
                            display: flex;
                            flex-wrap: wrap;
                            gap: 16px;
                            margin: 16px 0;
                        }
                        .metric {
                            flex: 1;
                            min-width: 120px;
                        }
                        .metric-value {
                            font-family: Arial, sans-serif;
                            font-size: 16pt;
                            font-weight: bold;
                            color: #0f2137;
                        }
                        .metric-label {
                            font-family: Arial, sans-serif;
                            font-size: 9pt;
                            color: #666;
                        }
                        h2 {
                            font-family: Arial, sans-serif;
                            font-size: 13pt;
                            font-weight: 600;
                            color: #0f2137;
                            margin: 24px 0 12px 0;
                            border-bottom: 1px solid #ddd;
                            padding-bottom: 6px;
                        }
                        p {
                            margin-bottom: 12px;
                            text-align: justify;
                        }
                        .chart-section {
                            background: #fafafa;
                            border: 1px solid #e0e0e0;
                            padding: 16px;
                            margin: 20px 0;
                            border-radius: 4px;
                        }
                        .chart-title {
                            font-family: Arial, sans-serif;
                            font-size: 11pt;
                            font-weight: 600;
                            color: #0f2137;
                            margin-bottom: 8px;
                        }
                        .chart-data {
                            font-family: 'Courier New', monospace;
                            font-size: 9pt;
                        }
                        .chart-data table {
                            width: 100%;
                            border-collapse: collapse;
                        }
                        .chart-data td {
                            padding: 4px 8px;
                            border-bottom: 1px solid #eee;
                        }
                        .chart-data td:last-child {
                            text-align: right;
                            font-weight: 600;
                        }
                        .takeaways {
                            margin: 20px 0;
                        }
                        .takeaway {
                            display: flex;
                            gap: 10px;
                            margin-bottom: 8px;
                        }
                        .takeaway-num {
                            font-family: Arial, sans-serif;
                            font-weight: bold;
                            color: #0f2137;
                            min-width: 20px;
                        }
                        .footer {
                            margin-top: 40px;
                            padding-top: 16px;
                            border-top: 1px solid #ddd;
                            font-family: Arial, sans-serif;
                            font-size: 8pt;
                            color: #888;
                        }
                        @media print {
                            body { padding: 20px; }
                            .no-print { display: none; }
                        }
                    </style>
                </head>
                <body>
                    ${this.buildPrintContent(article)}
                    <div class="footer">
                        <p>Generated by MENA Careers Intelligence | ${new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</p>
                        <p>This document is for informational purposes only.</p>
                    </div>
                    <script>
                        window.onload = function() {
                            window.print();
                            setTimeout(function() { window.close(); }, 500);
                        };
                    </script>
                </body>
                </html>
            `;

            printWindow.document.write(printContent);
            printWindow.document.close();
        }

        buildPrintContent(article) {
            let html = '';

            // Header
            const title = article.querySelector('.nrt-article-title')?.textContent || '';
            const type = article.querySelector('.nrt-methodology-badge')?.textContent?.trim() || '';
            const sector = article.querySelector('.nrt-article-sector')?.textContent || '';
            const authorName = article.querySelector('.nrt-author-name')?.textContent || 'MENA Careers Research';
            const date = article.querySelector('.nrt-author-meta time')?.textContent || '';

            html += `
                <div class="header">
                    <div class="logo">MENA CAREERS INTELLIGENCE</div>
                    <div class="meta-row">
                        ${type ? `<span class="meta-badge">${this.escapeHtml(type)}</span>` : ''}
                        ${sector ? `<span>${this.escapeHtml(sector)}</span>` : ''}
                    </div>
                    <h1>${this.escapeHtml(title)}</h1>
                    <div class="byline">By ${this.escapeHtml(authorName)} | ${this.escapeHtml(date)}</div>
                </div>
            `;

            // Executive Summary
            const thesis = article.querySelector('.nrt-exec-thesis')?.textContent;
            const metrics = article.querySelectorAll('.nrt-exec-metric');

            if (thesis || metrics.length > 0) {
                html += `<div class="summary-box"><div class="summary-title">Key Summary</div>`;
                if (thesis) {
                    html += `<p>${this.escapeHtml(thesis)}</p>`;
                }
                if (metrics.length > 0) {
                    html += `<div class="metrics">`;
                    metrics.forEach(m => {
                        const value = m.querySelector('.nrt-exec-metric-value')?.textContent || '';
                        const label = m.querySelector('.nrt-exec-metric-label')?.textContent || '';
                        html += `<div class="metric"><div class="metric-value">${this.escapeHtml(value)}</div><div class="metric-label">${this.escapeHtml(label)}</div></div>`;
                    });
                    html += `</div>`;
                }
                html += `</div>`;
            }

            // Takeaways
            const takeaways = article.querySelectorAll('.nrt-exec-takeaways-list li');
            if (takeaways.length > 0) {
                html += `<div class="takeaways"><h2>Key Takeaways</h2>`;
                takeaways.forEach((t, i) => {
                    const text = t.querySelector('.nrt-takeaway-text')?.textContent || t.textContent;
                    html += `<div class="takeaway"><span class="takeaway-num">${i + 1}.</span><span>${this.escapeHtml(text)}</span></div>`;
                });
                html += `</div>`;
            }

            // Content sections
            const sections = article.querySelectorAll('.nrt-content-section');
            sections.forEach(section => {
                const sectionTitle = section.querySelector('.nrt-section-title')?.textContent || '';
                const sectionContent = section.querySelector('.nrt-section-content');

                if (sectionTitle) {
                    html += `<h2>${this.escapeHtml(sectionTitle)}</h2>`;
                }
                if (sectionContent) {
                    // Get text content, preserving paragraphs
                    const paragraphs = sectionContent.querySelectorAll('p');
                    if (paragraphs.length > 0) {
                        paragraphs.forEach(p => {
                            html += `<p>${this.escapeHtml(p.textContent)}</p>`;
                        });
                    } else {
                        html += `<p>${this.escapeHtml(sectionContent.textContent)}</p>`;
                    }
                }
            });

            // Charts data
            const charts = article.querySelectorAll('.nrt-chart-card');
            if (charts.length > 0) {
                html += `<h2>Data & Charts</h2>`;
                charts.forEach(chart => {
                    const chartTitle = chart.querySelector('.nrt-chart-title')?.textContent || 'Chart';
                    const chartBody = chart.querySelector('.nrt-chart-body');
                    const chartData = chartBody?.dataset.chartData;

                    html += `<div class="chart-section"><div class="chart-title">${this.escapeHtml(chartTitle)}</div>`;

                    if (chartData) {
                        try {
                            const data = JSON.parse(chartData);
                            html += `<div class="chart-data"><table>`;

                            // Extract series/slices/points
                            const items = data.series || data.slices || data.points || [];
                            items.forEach(item => {
                                const label = item.label || item.name || '';
                                const value = item.value || '';
                                html += `<tr><td>${this.escapeHtml(label)}</td><td>${this.escapeHtml(String(value))}</td></tr>`;
                            });

                            html += `</table></div>`;
                        } catch (e) {
                            // Skip if can't parse
                        }
                    }

                    html += `</div>`;
                });
            }

            return html;
        }

        // ============================================
        // EXCEL DOWNLOAD (Browser-based)
        // ============================================
        downloadExcel() {
            const article = this.terminal.querySelector('.nrt-article');
            if (!article) return;

            const excelBtn = this.terminal.querySelector('.nrt-excel-btn, #nrt-excel-download');
            const originalText = excelBtn?.innerHTML || '';

            // Show loading state
            if (excelBtn) {
                excelBtn.innerHTML = `
                    <svg class="nrt-spinner" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation: spin 1s linear infinite;">
                        <circle cx="12" cy="12" r="10" stroke-dasharray="31.4" stroke-dashoffset="10"/>
                    </svg>
                    <span>Exporting...</span>
                `;
                excelBtn.disabled = true;
            }

            try {
                const title = article.querySelector('.nrt-article-title')?.textContent || 'Research Data';
                const fileName = this.sanitizeFileName(title) + '.xls';

                // Collect all data
                const data = this.collectExcelData(article);

                // Generate Excel XML content
                const excelContent = this.generateExcelXML(data, title);

                // Download as XLS
                const blob = new Blob([excelContent], {
                    type: 'application/vnd.ms-excel;charset=utf-8'
                });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = fileName;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);

            } catch (error) {
                console.error('Excel export failed:', error);
                alert('Failed to export data. Please try again.');
            }

            // Restore button
            setTimeout(() => {
                if (excelBtn) {
                    excelBtn.innerHTML = originalText;
                    excelBtn.disabled = false;
                }
            }, 500);
        }

        collectExcelData(article) {
            const data = [];

            // Title row
            const title = article.querySelector('.nrt-article-title')?.textContent || '';
            data.push({ type: 'header', values: [title, '', ''] });
            data.push({ type: 'empty', values: ['', '', ''] });

            // Article info
            data.push({ type: 'section', values: ['Article Information', '', ''] });

            const type = article.querySelector('.nrt-methodology-badge')?.textContent?.trim() || '';
            if (type) data.push({ type: 'data', values: ['Type', type, 'Classification'] });

            const sector = article.querySelector('.nrt-article-sector')?.textContent || '';
            if (sector) data.push({ type: 'data', values: ['Sector', sector, 'Category'] });

            const date = article.querySelector('.nrt-author-meta time')?.textContent || '';
            if (date) data.push({ type: 'data', values: ['Published', date, 'Date'] });

            const author = article.querySelector('.nrt-author-name')?.textContent || '';
            if (author) data.push({ type: 'data', values: ['Author', author, 'Analyst'] });

            data.push({ type: 'empty', values: ['', '', ''] });

            // Key Metrics
            const metrics = article.querySelectorAll('.nrt-exec-metric');
            if (metrics.length > 0) {
                data.push({ type: 'section', values: ['Key Metrics', '', ''] });
                data.push({ type: 'subheader', values: ['Metric', 'Value', 'Note'] });
                metrics.forEach(m => {
                    const value = m.querySelector('.nrt-exec-metric-value')?.textContent || '';
                    const label = m.querySelector('.nrt-exec-metric-label')?.textContent || '';
                    const sub = m.querySelector('.nrt-exec-metric-sub')?.textContent || '';
                    data.push({ type: 'data', values: [label, value, sub] });
                });
                data.push({ type: 'empty', values: ['', '', ''] });
            }

            // Chart data
            const charts = article.querySelectorAll('.nrt-chart-card');
            charts.forEach((chart, idx) => {
                const chartTitle = chart.querySelector('.nrt-chart-title')?.textContent || `Chart ${idx + 1}`;
                const chartBody = chart.querySelector('.nrt-chart-body');
                const chartDataStr = chartBody?.dataset.chartData;

                if (chartDataStr) {
                    try {
                        const chartData = JSON.parse(chartDataStr);
                        const items = chartData.series || chartData.slices || chartData.points || [];

                        if (items.length > 0) {
                            data.push({ type: 'section', values: [chartTitle, '', ''] });
                            data.push({ type: 'subheader', values: ['Item', 'Value', 'Type'] });

                            const suffix = chartData.suffix || '';
                            const isPercent = chartData.slices ? true : false;

                            items.forEach(item => {
                                const label = item.label || item.name || '';
                                let value = item.value || '';
                                if (isPercent) value = value + '%';
                                else if (suffix) value = value + ' ' + suffix;

                                const typeLabel = isPercent ? 'Share' : 'Data';
                                data.push({ type: 'data', values: [label, value, typeLabel] });
                            });

                            const source = chart.querySelector('.nrt-chart-source')?.textContent || '';
                            if (source) {
                                data.push({ type: 'source', values: ['Source', source.replace('Source: ', ''), ''] });
                            }
                            data.push({ type: 'empty', values: ['', '', ''] });
                        }
                    } catch (e) {
                        // Skip if can't parse
                    }
                }
            });

            // Takeaways
            const takeaways = article.querySelectorAll('.nrt-exec-takeaways-list li');
            if (takeaways.length > 0) {
                data.push({ type: 'section', values: ['Key Takeaways', '', ''] });
                takeaways.forEach((t, i) => {
                    const text = t.querySelector('.nrt-takeaway-text')?.textContent || t.textContent;
                    data.push({ type: 'data', values: [(i + 1).toString(), text, ''] });
                });
            }

            return data;
        }

        generateExcelXML(data, title) {
            // Generate Excel-compatible XML (same format as institutional article)
            let xml = '<?xml version="1.0" encoding="UTF-8"?>\n';
            xml += '<?mso-application progid="Excel.Sheet"?>\n';
            xml += '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"\n';
            xml += '  xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">\n';
            xml += '  <Styles>\n';
            // Title style
            xml += '    <Style ss:ID="title">\n';
            xml += '      <Font ss:Bold="1" ss:Size="14" ss:Color="#FFFFFF"/>\n';
            xml += '      <Interior ss:Color="#0f2137" ss:Pattern="Solid"/>\n';
            xml += '      <Alignment ss:Horizontal="Left" ss:Vertical="Center"/>\n';
            xml += '    </Style>\n';
            // Section header style
            xml += '    <Style ss:ID="section">\n';
            xml += '      <Font ss:Bold="1" ss:Size="11" ss:Color="#0f2137"/>\n';
            xml += '      <Interior ss:Color="#e2e8f0" ss:Pattern="Solid"/>\n';
            xml += '      <Borders>\n';
            xml += '        <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#94a3b8"/>\n';
            xml += '      </Borders>\n';
            xml += '    </Style>\n';
            // Subheader style
            xml += '    <Style ss:ID="subheader">\n';
            xml += '      <Font ss:Bold="1" ss:Size="10" ss:Color="#475569"/>\n';
            xml += '      <Interior ss:Color="#f1f5f9" ss:Pattern="Solid"/>\n';
            xml += '      <Borders>\n';
            xml += '        <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#cbd5e1"/>\n';
            xml += '      </Borders>\n';
            xml += '    </Style>\n';
            // Data style
            xml += '    <Style ss:ID="data">\n';
            xml += '      <Font ss:Size="10"/>\n';
            xml += '      <Alignment ss:Vertical="Center"/>\n';
            xml += '    </Style>\n';
            // Value style - highlighted
            xml += '    <Style ss:ID="value">\n';
            xml += '      <Font ss:Size="10" ss:Bold="1" ss:Color="#0f172a"/>\n';
            xml += '      <Interior ss:Color="#fffff8" ss:Pattern="Solid"/>\n';
            xml += '      <Alignment ss:Horizontal="Left" ss:Vertical="Center"/>\n';
            xml += '    </Style>\n';
            // Source style
            xml += '    <Style ss:ID="source">\n';
            xml += '      <Font ss:Size="9" ss:Italic="1" ss:Color="#64748b"/>\n';
            xml += '    </Style>\n';
            // Spacer style
            xml += '    <Style ss:ID="spacer">\n';
            xml += '      <Interior ss:Color="#FFFFFF" ss:Pattern="Solid"/>\n';
            xml += '    </Style>\n';
            xml += '  </Styles>\n';
            xml += '  <Worksheet ss:Name="Data Extract">\n';
            xml += '    <Table ss:DefaultRowHeight="18">\n';
            xml += '      <Column ss:Width="220"/>\n';
            xml += '      <Column ss:Width="160"/>\n';
            xml += '      <Column ss:Width="120"/>\n';

            // Add title row
            xml += '      <Row ss:Height="28">\n';
            xml += `        <Cell ss:StyleID="title" ss:MergeAcross="2"><Data ss:Type="String">${this.escapeXml(title)}</Data></Cell>\n`;
            xml += '      </Row>\n';

            // Add date row
            const today = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
            xml += '      <Row ss:Height="16">\n';
            xml += `        <Cell ss:StyleID="source" ss:MergeAcross="2"><Data ss:Type="String">Exported: ${today} | Source: MENA Careers Research</Data></Cell>\n`;
            xml += '      </Row>\n';

            // Add spacer
            xml += '      <Row ss:Height="10"><Cell ss:StyleID="spacer"/></Row>\n';

            // Add data rows
            data.forEach(row => {
                const rowType = row.type || 'data';

                if (rowType === 'empty') {
                    xml += '      <Row ss:Height="8"><Cell ss:StyleID="spacer"/></Row>\n';
                    return;
                }

                if (rowType === 'header' || rowType === 'section') {
                    xml += '      <Row ss:Height="24">\n';
                    xml += `        <Cell ss:StyleID="section" ss:MergeAcross="2"><Data ss:Type="String">${this.escapeXml(row.values[0])}</Data></Cell>\n`;
                    xml += '      </Row>\n';
                } else if (rowType === 'subheader') {
                    xml += '      <Row ss:Height="20">\n';
                    row.values.forEach(cell => {
                        xml += `        <Cell ss:StyleID="subheader"><Data ss:Type="String">${this.escapeXml(cell)}</Data></Cell>\n`;
                    });
                    xml += '      </Row>\n';
                } else if (rowType === 'source') {
                    xml += '      <Row ss:Height="16">\n';
                    xml += `        <Cell ss:StyleID="source"><Data ss:Type="String">${this.escapeXml(row.values[0])}</Data></Cell>\n`;
                    xml += `        <Cell ss:StyleID="source" ss:MergeAcross="1"><Data ss:Type="String">${this.escapeXml(row.values[1] || '')}</Data></Cell>\n`;
                    xml += '      </Row>\n';
                } else {
                    // Regular data row
                    xml += '      <Row ss:Height="18">\n';
                    row.values.forEach((cell, idx) => {
                        const style = idx === 1 ? 'value' : 'data';
                        xml += `        <Cell ss:StyleID="${style}"><Data ss:Type="String">${this.escapeXml(cell)}</Data></Cell>\n`;
                    });
                    xml += '      </Row>\n';
                }
            });

            xml += '    </Table>\n';
            xml += '  </Worksheet>\n';
            xml += '</Workbook>';

            return xml;
        }

        sanitizeFileName(name) {
            return name
                .replace(/[^a-zA-Z0-9\s-]/g, '')
                .replace(/\s+/g, '-')
                .substring(0, 50);
        }

        escapeXml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&apos;');
        }

        // ============================================
        // CHART RENDERING
        // ============================================

        initCharts() {
            const chartContainers = this.terminal.querySelectorAll('[data-chart]');
            chartContainers.forEach(container => {
                const type = container.dataset.chart;
                const data = JSON.parse(container.dataset.chartData || '{}');
                this.renderChart(container, type, data);
            });
        }

        renderChart(container, type, data) {
            switch (type) {
                case 'bar':
                    this.renderBarChart(container, data);
                    break;
                case 'donut':
                case 'pie':
                    this.renderDonutChart(container, data);
                    break;
                case 'line':
                    this.renderLineChart(container, data);
                    break;
            }
        }

        renderBarChart(container, data) {
            const series = data.series || [];
            if (!series.length) return;

            const maxValue = Math.max(...series.map(s => parseFloat(s.value) || 0));
            const suffix = data.suffix || '';

            let html = '<div class="nrt-bar-chart">';
            series.forEach((item, index) => {
                const value = parseFloat(item.value) || 0;
                const width = maxValue > 0 ? (value / maxValue) * 100 : 0;
                const color = this.getChartColor(index);

                html += `
                    <div class="nrt-bar-row">
                        <div class="nrt-bar-label">${this.escapeHtml(item.label || '')}</div>
                        <div class="nrt-bar-track">
                            <div class="nrt-bar-fill" style="width: ${width}%; background: ${color}"></div>
                        </div>
                        <div class="nrt-bar-value">${this.formatNumber(value)}${suffix}</div>
                    </div>
                `;
            });
            html += '</div>';

            container.innerHTML = html;
        }

        renderDonutChart(container, data) {
            const slices = data.slices || [];
            if (!slices.length) return;

            const total = slices.reduce((sum, s) => sum + (parseFloat(s.value) || 0), 0);
            const size = 160;
            const strokeWidth = 28;
            const radius = (size - strokeWidth) / 2;
            const circumference = 2 * Math.PI * radius;

            let currentOffset = 0;
            let paths = '';
            let legend = '';

            slices.forEach((slice, index) => {
                const value = parseFloat(slice.value) || 0;
                const percentage = total > 0 ? (value / total) * 100 : 0;
                const length = (percentage / 100) * circumference;
                const color = this.getChartColor(index);

                paths += `
                    <circle
                        cx="${size / 2}"
                        cy="${size / 2}"
                        r="${radius}"
                        fill="none"
                        stroke="${color}"
                        stroke-width="${strokeWidth}"
                        stroke-dasharray="${length} ${circumference - length}"
                        stroke-dashoffset="${-currentOffset}"
                        transform="rotate(-90 ${size / 2} ${size / 2})"
                    />
                `;

                legend += `
                    <div class="nrt-legend-item">
                        <span class="nrt-legend-dot" style="background: ${color}"></span>
                        <span class="nrt-legend-label">${this.escapeHtml(slice.label || '')}</span>
                        <span class="nrt-legend-value">${percentage.toFixed(1)}%</span>
                    </div>
                `;

                currentOffset += length;
            });

            const centerValue = data.centerValue || total.toFixed(0);
            const centerLabel = data.centerLabel || 'Total';

            container.innerHTML = `
                <div class="nrt-donut-chart">
                    <div class="nrt-donut-visual">
                        <svg viewBox="0 0 ${size} ${size}" width="${size}" height="${size}">
                            ${paths}
                        </svg>
                        <div class="nrt-donut-center">
                            <div class="nrt-donut-center-value">${centerValue}</div>
                            <div class="nrt-donut-center-label">${centerLabel}</div>
                        </div>
                    </div>
                    <div class="nrt-donut-legend">
                        ${legend}
                    </div>
                </div>
            `;
        }

        renderLineChart(container, data) {
            const points = data.points || [];
            if (points.length < 2) return;

            const values = points.map(p => parseFloat(p.value) || 0);
            const maxValue = Math.max(...values);
            const minValue = Math.min(...values);
            const range = maxValue - minValue || 1;

            const width = 300;
            const height = 150;
            const padding = { top: 20, right: 20, bottom: 30, left: 40 };
            const chartWidth = width - padding.left - padding.right;
            const chartHeight = height - padding.top - padding.bottom;

            // Create path
            const pathPoints = points.map((point, index) => {
                const x = padding.left + (index / (points.length - 1)) * chartWidth;
                const y = padding.top + chartHeight - ((parseFloat(point.value) - minValue) / range) * chartHeight;
                return `${index === 0 ? 'M' : 'L'} ${x} ${y}`;
            }).join(' ');

            // Create area path
            const areaPath = pathPoints +
                ` L ${padding.left + chartWidth} ${height - padding.bottom}` +
                ` L ${padding.left} ${height - padding.bottom} Z`;

            // X-axis labels
            let xLabels = '';
            const labelStep = Math.ceil(points.length / 5);
            points.forEach((point, index) => {
                if (index % labelStep === 0 || index === points.length - 1) {
                    const x = padding.left + (index / (points.length - 1)) * chartWidth;
                    xLabels += `<text x="${x}" y="${height - 8}" text-anchor="middle" class="nrt-line-label">${point.label || ''}</text>`;
                }
            });

            container.innerHTML = `
                <div class="nrt-line-chart">
                    <svg viewBox="0 0 ${width} ${height}" width="100%" preserveAspectRatio="xMidYMid meet">
                        <!-- Grid lines -->
                        <line x1="${padding.left}" y1="${padding.top}" x2="${padding.left}" y2="${height - padding.bottom}" stroke="#e4e4e7" stroke-width="1"/>
                        <line x1="${padding.left}" y1="${height - padding.bottom}" x2="${width - padding.right}" y2="${height - padding.bottom}" stroke="#e4e4e7" stroke-width="1"/>

                        <!-- Area fill -->
                        <path d="${areaPath}" fill="${CHART_COLORS.blue}" fill-opacity="0.1"/>

                        <!-- Line -->
                        <path d="${pathPoints}" fill="none" stroke="${CHART_COLORS.navy}" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>

                        <!-- Points -->
                        ${points.map((point, index) => {
                            const x = padding.left + (index / (points.length - 1)) * chartWidth;
                            const y = padding.top + chartHeight - ((parseFloat(point.value) - minValue) / range) * chartHeight;
                            return `<circle cx="${x}" cy="${y}" r="4" fill="${CHART_COLORS.navy}" stroke="white" stroke-width="2"/>`;
                        }).join('')}

                        <!-- Labels -->
                        ${xLabels}
                    </svg>
                </div>
            `;
        }

        getChartColor(index) {
            const colors = [
                CHART_COLORS.navy,
                CHART_COLORS.blue,
                CHART_COLORS.green,
                CHART_COLORS.blueLight,
                CHART_COLORS.gray
            ];
            return colors[index % colors.length];
        }

        formatNumber(num) {
            if (num >= 1000000000) {
                return (num / 1000000000).toFixed(1) + 'B';
            }
            if (num >= 1000000) {
                return (num / 1000000).toFixed(1) + 'M';
            }
            if (num >= 1000) {
                return (num / 1000).toFixed(1) + 'K';
            }
            return num.toFixed(num % 1 === 0 ? 0 : 1);
        }

        // ==========================================================================
        // PREFERENCES MODAL
        // ==========================================================================

        /**
         * Initialize preferences modal events
         */
        initPreferencesModal() {
            const overlay = document.getElementById('nrt-prefs-modal-overlay');
            if (!overlay) return;

            // Store reference to modal elements
            this.prefsModal = {
                overlay: overlay,
                modal: document.getElementById('nrt-prefs-modal'),
                closeBtn: document.getElementById('nrt-prefs-modal-close'),
                saveBtn: document.getElementById('nrt-prefs-save'),
                resetBtn: document.getElementById('nrt-prefs-reset'),
                keywordInput: document.getElementById('nrt-prefs-keyword-input'),
                keywordAddBtn: document.getElementById('nrt-prefs-keyword-add'),
                keywordsList: document.getElementById('nrt-prefs-keywords-list')
            };

            // Store current preferences state
            this.prefsState = {
                topics: [],
                industries: [],
                regions: [],
                deal_types: [],
                job_levels: [],
                keywords: []
            };

            // Close button
            this.prefsModal.closeBtn?.addEventListener('click', () => this.closePreferencesModal());

            // Click outside to close
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    this.closePreferencesModal();
                }
            });

            // Escape key to close
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && overlay.classList.contains('is-visible')) {
                    this.closePreferencesModal();
                }
            });

            // Save button
            this.prefsModal.saveBtn?.addEventListener('click', () => this.savePreferences());

            // Reset button
            this.prefsModal.resetBtn?.addEventListener('click', () => this.resetPreferences());

            // Chip selection events
            this.bindChipEvents();

            // Keyword input events
            this.bindKeywordEvents();
        }

        /**
         * Bind chip selection events
         */
        bindChipEvents() {
            const chipContainers = [
                { id: 'nrt-prefs-topics', key: 'topics', attr: 'data-topic' },
                { id: 'nrt-prefs-industries', key: 'industries', attr: 'data-industry' },
                { id: 'nrt-prefs-regions', key: 'regions', attr: 'data-region' },
                { id: 'nrt-prefs-deal-types', key: 'deal_types', attr: 'data-deal-type' },
                { id: 'nrt-prefs-job-levels', key: 'job_levels', attr: 'data-job-level' }
            ];

            chipContainers.forEach(({ id, key, attr }) => {
                const container = document.getElementById(id);
                if (!container) return;

                container.addEventListener('click', (e) => {
                    const chip = e.target.closest('.nrt-prefs-chip');
                    if (!chip) return;

                    const value = chip.getAttribute(attr);
                    if (!value) return;

                    chip.classList.toggle('is-selected');

                    if (chip.classList.contains('is-selected')) {
                        if (!this.prefsState[key].includes(value)) {
                            this.prefsState[key].push(value);
                        }
                    } else {
                        this.prefsState[key] = this.prefsState[key].filter(v => v !== value);
                    }
                });
            });
        }

        /**
         * Bind keyword input events
         */
        bindKeywordEvents() {
            const { keywordInput, keywordAddBtn, keywordsList } = this.prefsModal;
            if (!keywordInput || !keywordAddBtn) return;

            const addKeyword = () => {
                const keyword = keywordInput.value.trim().toLowerCase();
                if (!keyword || keyword.length < 2) return;
                if (this.prefsState.keywords.includes(keyword)) return;
                if (this.prefsState.keywords.length >= 20) {
                    alert('Maximum 20 keywords allowed');
                    return;
                }

                this.prefsState.keywords.push(keyword);
                this.renderKeywords();
                keywordInput.value = '';
            };

            keywordAddBtn.addEventListener('click', addKeyword);
            keywordInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    addKeyword();
                }
            });

            // Delegate click for keyword remove buttons
            keywordsList?.addEventListener('click', (e) => {
                const removeBtn = e.target.closest('.nrt-prefs-keyword-remove');
                if (!removeBtn) return;

                const keyword = removeBtn.dataset.keyword;
                this.prefsState.keywords = this.prefsState.keywords.filter(k => k !== keyword);
                this.renderKeywords();
            });
        }

        /**
         * Render keywords list
         */
        renderKeywords() {
            if (!this.prefsModal || !this.prefsModal.keywordsList) return;
            const keywordsList = this.prefsModal.keywordsList;

            if (this.prefsState.keywords.length === 0) {
                keywordsList.innerHTML = '<span class="nrt-prefs-keywords-placeholder">No keywords added yet</span>';
                return;
            }

            keywordsList.innerHTML = this.prefsState.keywords.map(keyword => `
                <span class="nrt-prefs-keyword-tag">
                    ${this.escapeHtml(keyword)}
                    <button type="button" class="nrt-prefs-keyword-remove" data-keyword="${this.escapeHtml(keyword)}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"/>
                            <line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                </span>
            `).join('');
        }

        /**
         * Open preferences modal and load current preferences
         */
        async openPreferencesModal() {
            if (!this.prefsModal || !this.prefsModal.overlay) return;

            // Load current preferences from API
            await this.loadPreferences();

            // Show modal
            this.prefsModal.overlay.classList.add('is-visible');
            document.body.style.overflow = 'hidden';
        }

        /**
         * Close preferences modal
         */
        closePreferencesModal() {
            if (!this.prefsModal || !this.prefsModal.overlay) return;

            this.prefsModal.overlay.classList.remove('is-visible');
            document.body.style.overflow = '';
        }

        /**
         * Load user preferences from API
         */
        async loadPreferences() {
            try {
                const response = await fetch('/wp-json/sffc/v1/preferences');
                const data = await response.json();

                if (data.success !== false) {
                    // Update state
                    this.prefsState = {
                        topics: data.topics || [],
                        industries: data.industries || [],
                        regions: data.regions || [],
                        deal_types: data.deal_types || [],
                        job_levels: data.job_levels || [],
                        keywords: data.keywords || []
                    };

                    // Update UI
                    this.updatePrefsUI();
                }
            } catch (error) {
                console.error('Error loading preferences:', error);
            }
        }

        /**
         * Update preferences UI to match current state
         */
        updatePrefsUI() {
            const mappings = [
                { id: 'nrt-prefs-topics', key: 'topics', attr: 'data-topic' },
                { id: 'nrt-prefs-industries', key: 'industries', attr: 'data-industry' },
                { id: 'nrt-prefs-regions', key: 'regions', attr: 'data-region' },
                { id: 'nrt-prefs-deal-types', key: 'deal_types', attr: 'data-deal-type' },
                { id: 'nrt-prefs-job-levels', key: 'job_levels', attr: 'data-job-level' }
            ];

            mappings.forEach(({ id, key, attr }) => {
                const container = document.getElementById(id);
                if (!container) return;

                container.querySelectorAll('.nrt-prefs-chip').forEach(chip => {
                    const value = chip.getAttribute(attr);
                    if (this.prefsState[key].includes(value)) {
                        chip.classList.add('is-selected');
                    } else {
                        chip.classList.remove('is-selected');
                    }
                });
            });

            // Render keywords
            this.renderKeywords();
        }

        /**
         * Save preferences via API
         */
        async savePreferences() {
            if (!this.prefsModal || !this.prefsModal.saveBtn) return;
            const saveBtn = this.prefsModal.saveBtn;

            // Show loading state
            const btnText = saveBtn.querySelector('.nrt-prefs-btn-text');
            const btnLoading = saveBtn.querySelector('.nrt-prefs-btn-loading');
            if (btnText) btnText.style.display = 'none';
            if (btnLoading) btnLoading.style.display = 'flex';
            saveBtn.disabled = true;

            try {
                const response = await fetch('/wp-json/sffc/v1/preferences', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.nrtData?.nonce || ''
                    },
                    body: JSON.stringify(this.prefsState)
                });

                const data = await response.json();

                if (data.success) {
                    // Close modal
                    this.closePreferencesModal();

                    // Refresh the personalized feed
                    this.loadPersonalizedFeed();

                    // Update topics display in profile header if exists
                    this.updateTopicsDisplay();
                } else {
                    alert('Failed to save preferences. Please try again.');
                }
            } catch (error) {
                console.error('Error saving preferences:', error);
                alert('Failed to save preferences. Please try again.');
            } finally {
                // Reset button state
                if (btnText) btnText.style.display = '';
                if (btnLoading) btnLoading.style.display = 'none';
                saveBtn.disabled = false;
            }
        }

        /**
         * Reset preferences to default
         */
        resetPreferences() {
            if (!confirm('Are you sure you want to reset all preferences?')) return;

            // Clear state
            this.prefsState = {
                topics: [],
                industries: [],
                regions: [],
                deal_types: [],
                job_levels: [],
                keywords: []
            };

            // Update UI
            this.updatePrefsUI();
        }

        /**
         * Update topics display in profile header
         */
        updateTopicsDisplay() {
            const topicsContainer = document.getElementById('nrt-profile-topics-list');
            if (!topicsContainer || !this.prefsState) return;

            const allSelected = [
                ...(this.prefsState.topics || []),
                ...(this.prefsState.industries || []).slice(0, 3)
            ].slice(0, 6);

            if (allSelected.length === 0) {
                topicsContainer.innerHTML = `
                    <span class="nrt-topic-chip nrt-topic-chip--add" data-action="manage-topics">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Add Topics
                    </span>
                `;
                return;
            }

            const formatLabel = (slug) => {
                return slug.replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
            };

            let html = allSelected.map(slug =>
                `<span class="nrt-topic-chip">${formatLabel(slug)}</span>`
            ).join('');

            // Add "Edit" button
            html += `
                <button type="button" class="nrt-topic-chip nrt-topic-chip--edit" data-action="manage-topics">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                </button>
            `;

            topicsContainer.innerHTML = html;
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // ==========================================================================
        // LEARNING TAB
        // ==========================================================================

        /**
         * Initialize Learning tab events
         */
        initLearningTab() {
            // Guide category filter chips
            this.terminal.addEventListener('click', (e) => {
                const chip = e.target.closest('.nrt-tab-learning .nrt-filter-chip');
                if (!chip || !chip.dataset.guideCategory) return;

                // Update active state (single-select)
                this.terminal.querySelectorAll('.nrt-tab-learning .nrt-filter-chip').forEach(c => c.classList.remove('is-active'));
                chip.classList.add('is-active');

                // Apply filter
                this.filterGuides(chip.dataset.guideCategory);
            });

            // Guide card clicks
            this.terminal.addEventListener('click', (e) => {
                const guideCard = e.target.closest('.nrt-guide-card');
                if (guideCard) {
                    this.selectGuide(guideCard);
                }
            });

            // PDF download button
            const pdfBtn = document.getElementById('nrt-guide-pdf');
            if (pdfBtn) {
                pdfBtn.addEventListener('click', () => {
                    this.downloadGuidePDF();
                });
            }
        }

        /**
         * Filter guides by category
         */
        filterGuides(category) {
            const guideCards = this.terminal.querySelectorAll('.nrt-guide-card');
            let visibleCount = 0;

            guideCards.forEach(card => {
                if (category === 'all' || card.dataset.category === category) {
                    card.style.display = '';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            // Update count
            const countEl = this.terminal.querySelector('.nrt-tab-learning .nrt-story-count');
            if (countEl) {
                countEl.textContent = `${visibleCount} guide${visibleCount !== 1 ? 's' : ''}`;
            }
        }

        /**
         * Handle guide card selection
         */
        selectGuide(card) {
            // Update active state
            this.terminal.querySelectorAll('.nrt-guide-card').forEach(c => c.classList.remove('is-active'));
            card.classList.add('is-active');

            // Get guide data
            const guideId = card.dataset.guideId;
            const title = card.querySelector('.nrt-guide-title')?.textContent || 'Guide';
            const category = card.querySelector('.nrt-guide-category')?.textContent || 'General';
            const level = card.querySelector('.nrt-guide-level')?.textContent || 'Beginner';

            // Check if guide is locked (for logged-out users)
            if (card.dataset.locked === 'true') {
                // Hide guide view if it was open, show content inner for subscription box
                const guideView = document.getElementById('nrt-guide-view');
                const contentInner = document.getElementById('nrt-content-inner');
                if (guideView) guideView.style.display = 'none';
                if (contentInner) contentInner.style.display = '';

                this.showSubscriptionBox(card);
                this.openMobileArticle(title);
                return;
            }

            // Show guide view and hide normal content
            const guideView = document.getElementById('nrt-guide-view');
            const contentInner = document.getElementById('nrt-content-inner');

            if (guideView) {
                guideView.style.display = 'flex';
            }
            if (contentInner) {
                contentInner.style.display = 'none';
            }

            // Update guide header
            const titleEl = document.getElementById('nrt-guide-title');
            const categoryEl = document.getElementById('nrt-guide-category-text');
            const levelEl = document.getElementById('nrt-guide-level-badge');
            const durationEl = document.getElementById('nrt-guide-duration');
            const contentEl = document.getElementById('nrt-guide-content');

            if (titleEl) titleEl.textContent = title;
            if (categoryEl) categoryEl.textContent = category;
            if (levelEl) levelEl.textContent = level;
            if (durationEl) durationEl.textContent = this.getGuideDuration(guideId);

            // Load guide content
            if (contentEl) {
                contentEl.innerHTML = this.getGuideContent(guideId);
            }

            // Open mobile article view if on mobile
            this.openMobileArticle(title);
        }

        /**
         * Get guide duration based on ID
         */
        getGuideDuration(guideId) {
            const durations = {
                'dcf-fundamentals': '15 min read',
                'trading-comps': '12 min read',
                'valuation-methods': '18 min read',
                'cost-of-capital': '20 min read',
                'ev-equity-bridge': '15 min read',
                'fair-value': '25 min read',
                'ma-structuring': '22 min read',
                'synergies': '18 min read',
                'due-diligence': '20 min read',
                'distressed-ma': '25 min read',
                'direct-lending': '20 min read',
                'covenants': '15 min read',
                'mezzanine': '22 min read',
                'debt-equity': '12 min read',
                'lbo-essentials': '25 min read',
                'private-ma': '18 min read',
                'value-creation': '22 min read',
                'exit-strategies': '20 min read',
                'fund-structures': '18 min read',
                'financial-modeling': '15 min read',
                'project-finance': '28 min read',
                'irr-fundamentals': '18 min read',
                'buyout-modeling': '25 min read',
                'retention-bonus': '15 min read',
                'blind-pool-funds': '20 min read',
                'comparable-analysis': '22 min read',
                'private-markets': '20 min read',
                'unitranche-structures': '18 min read',
                're-capital-stack': '22 min read'
            };
            return durations[guideId] || '15 min read';
        }

        /**
         * Get guide content HTML
         */
        getGuideContent(guideId) {
            const guides = this.getGuideLibrary();
            return guides[guideId] || '<p>Guide content coming soon...</p>';
        }

        /**
         * Guide content library
         */
        getGuideLibrary() {
            return {
                'dcf-fundamentals': `
                    <h2>Introduction</h2>
                    <p>The Discounted Cash Flow (DCF) method is one of the most fundamental valuation approaches in finance. It values a company based on the present value of its expected future cash flows, discounted back at an appropriate rate that reflects the riskiness of those cash flows.</p>

                    <div class="nrt-guide-concept">
                        <h4>Key Concept</h4>
                        <p>The intrinsic value of any asset is the present value of its expected future cash flows.</p>
                    </div>

                    <h2>The DCF Formula</h2>
                    <div class="nrt-guide-formula">
                        Enterprise Value = Σ (FCF<sub>t</sub> / (1 + WACC)<sup>t</sup>) + Terminal Value / (1 + WACC)<sup>n</sup>
                    </div>

                    <h2>Step 1: Project Free Cash Flows</h2>
                    <p>Free Cash Flow (FCF) represents the cash available to all capital providers after the company has made necessary investments to maintain or grow its asset base.</p>

                    <h3>Unlevered Free Cash Flow Formula</h3>
                    <ul>
                        <li>Start with EBIT (Earnings Before Interest and Taxes)</li>
                        <li>Subtract taxes (EBIT × Tax Rate)</li>
                        <li>Add back Depreciation & Amortization</li>
                        <li>Subtract Capital Expenditures (CapEx)</li>
                        <li>Subtract/Add Changes in Net Working Capital</li>
                    </ul>

                    <h2>Step 2: Determine the Discount Rate (WACC)</h2>
                    <p>The Weighted Average Cost of Capital represents the blended cost of financing from both debt and equity sources.</p>

                    <div class="nrt-guide-formula">
                        WACC = (E/V × Re) + (D/V × Rd × (1 - Tc))
                    </div>

                    <p>Where:</p>
                    <ul>
                        <li><strong>E/V</strong> = Equity weight in capital structure</li>
                        <li><strong>Re</strong> = Cost of equity (from CAPM)</li>
                        <li><strong>D/V</strong> = Debt weight in capital structure</li>
                        <li><strong>Rd</strong> = Cost of debt</li>
                        <li><strong>Tc</strong> = Corporate tax rate</li>
                    </ul>

                    <h2>Step 3: Calculate Terminal Value</h2>
                    <p>Terminal Value captures the value of all cash flows beyond the explicit forecast period. Two common methods:</p>

                    <h3>Gordon Growth Method</h3>
                    <div class="nrt-guide-formula">
                        TV = FCF<sub>n</sub> × (1 + g) / (WACC - g)
                    </div>

                    <h3>Exit Multiple Method</h3>
                    <div class="nrt-guide-formula">
                        TV = EBITDA<sub>n</sub> × Exit Multiple
                    </div>

                    <h2>Step 4: Discount to Present Value</h2>
                    <p>Discount all projected cash flows and terminal value back to present using WACC. Sum these to get Enterprise Value.</p>

                    <h2>Step 5: Bridge to Equity Value</h2>
                    <table>
                        <tr><th>Item</th><th>Adjustment</th></tr>
                        <tr><td>Enterprise Value</td><td>Starting point</td></tr>
                        <tr><td>Less: Net Debt</td><td>Subtract</td></tr>
                        <tr><td>Less: Minority Interest</td><td>Subtract</td></tr>
                        <tr><td>Plus: Equity Investments</td><td>Add</td></tr>
                        <tr><td><strong>Equity Value</strong></td><td><strong>Result</strong></td></tr>
                    </table>

                    <blockquote>
                        <strong>Pro Tip:</strong> Always run sensitivity analysis on your key assumptions - growth rates, margins, and discount rate. A DCF is only as good as its inputs.
                    </blockquote>
                `,

                'trading-comps': `
                    <h2>Introduction</h2>
                    <p>Trading Comparables (or "Trading Comps") is a relative valuation method that values a company by comparing it to similar publicly traded companies. The underlying principle is that similar companies should trade at similar multiples.</p>

                    <div class="nrt-guide-concept">
                        <h4>Key Concept</h4>
                        <p>If comparable companies trade at 10x EBITDA on average, your target company should be worth approximately 10x its EBITDA.</p>
                    </div>

                    <h2>Step 1: Select Comparable Companies</h2>
                    <p>The quality of your analysis depends heavily on peer selection. Consider:</p>
                    <ul>
                        <li><strong>Industry/Sector:</strong> Same business model and end markets</li>
                        <li><strong>Size:</strong> Similar revenue, market cap, or enterprise value</li>
                        <li><strong>Geography:</strong> Same or similar operating regions</li>
                        <li><strong>Growth Profile:</strong> Similar growth rates and margins</li>
                        <li><strong>Business Model:</strong> Subscription vs. transactional, B2B vs. B2C</li>
                    </ul>

                    <h2>Step 2: Calculate Key Multiples</h2>

                    <h3>Enterprise Value Multiples</h3>
                    <table>
                        <tr><th>Multiple</th><th>Formula</th><th>Best For</th></tr>
                        <tr><td>EV/Revenue</td><td>EV ÷ Revenue</td><td>High-growth, unprofitable companies</td></tr>
                        <tr><td>EV/EBITDA</td><td>EV ÷ EBITDA</td><td>Most common; capital-neutral</td></tr>
                        <tr><td>EV/EBIT</td><td>EV ÷ EBIT</td><td>When D&A varies significantly</td></tr>
                    </table>

                    <h3>Equity Value Multiples</h3>
                    <table>
                        <tr><th>Multiple</th><th>Formula</th><th>Best For</th></tr>
                        <tr><td>P/E</td><td>Share Price ÷ EPS</td><td>Profitable, stable companies</td></tr>
                        <tr><td>P/B</td><td>Market Cap ÷ Book Value</td><td>Financial institutions</td></tr>
                    </table>

                    <h2>Step 3: Spread the Comps</h2>
                    <p>Create a "comp sheet" showing all peers with their key metrics and multiples. Calculate:</p>
                    <ul>
                        <li>Mean and median multiples</li>
                        <li>High and low of the range</li>
                        <li>Standard deviation to identify outliers</li>
                    </ul>

                    <h2>Step 4: Apply Multiples to Target</h2>
                    <div class="nrt-guide-formula">
                        Target EV = Target EBITDA × Median EV/EBITDA Multiple
                    </div>

                    <h2>Transaction Comparables</h2>
                    <p>Similar to trading comps, but uses precedent M&A transactions instead of current trading values. Transaction multiples typically include a control premium (20-40% above trading value).</p>

                    <blockquote>
                        <strong>Pro Tip:</strong> Always use LTM (Last Twelve Months) or NTM (Next Twelve Months) figures consistently across all comparables.
                    </blockquote>
                `,

                'lbo-essentials': `
                    <h2>Introduction</h2>
                    <p>A Leveraged Buyout (LBO) is an acquisition of a company using significant amounts of borrowed money (leverage) to fund the purchase. The assets of the company being acquired are often used as collateral for the loans.</p>

                    <div class="nrt-guide-concept">
                        <h4>Key Concept</h4>
                        <p>PE firms use leverage to amplify returns. A company bought for 100 with 60 debt and 40 equity that sells for 150 returns 2.25x on equity (90/40), vs 1.5x (150/100) if all equity.</p>
                    </div>

                    <h2>LBO Value Creation Levers</h2>
                    <ol>
                        <li><strong>Multiple Expansion:</strong> Sell at a higher multiple than purchase</li>
                        <li><strong>EBITDA Growth:</strong> Increase profitability through operational improvements</li>
                        <li><strong>Debt Paydown:</strong> Use cash flows to reduce debt, increasing equity value</li>
                    </ol>

                    <h2>Step 1: Sources & Uses</h2>
                    <table>
                        <tr><th>Uses of Funds</th><th>Sources of Funds</th></tr>
                        <tr><td>Purchase Price</td><td>Senior Debt</td></tr>
                        <tr><td>Transaction Fees</td><td>Subordinated Debt</td></tr>
                        <tr><td>Debt Refinancing</td><td>Mezzanine</td></tr>
                        <tr><td></td><td>Sponsor Equity</td></tr>
                        <tr><td></td><td>Rollover Equity</td></tr>
                    </table>

                    <h2>Step 2: Build Operating Model</h2>
                    <p>Project the company's financial performance over the hold period (typically 5 years):</p>
                    <ul>
                        <li>Revenue growth assumptions</li>
                        <li>Margin expansion/contraction</li>
                        <li>Working capital requirements</li>
                        <li>Capital expenditure needs</li>
                    </ul>

                    <h2>Step 3: Build Debt Schedule</h2>
                    <p>Model each tranche of debt with its specific terms:</p>
                    <ul>
                        <li>Interest rate (fixed vs. floating)</li>
                        <li>Amortization schedule</li>
                        <li>Mandatory vs. optional prepayments</li>
                        <li>Cash sweep provisions</li>
                    </ul>

                    <h2>Step 4: Calculate Returns</h2>

                    <h3>IRR (Internal Rate of Return)</h3>
                    <p>The discount rate that makes the NPV of all cash flows equal to zero. Target: 20-25%+ for PE.</p>

                    <h3>MOIC (Multiple on Invested Capital)</h3>
                    <div class="nrt-guide-formula">
                        MOIC = Total Exit Proceeds ÷ Total Invested Capital
                    </div>
                    <p>Target: 2.0-3.0x+ over 5 years.</p>

                    <h2>Key Credit Metrics</h2>
                    <table>
                        <tr><th>Metric</th><th>Formula</th><th>Typical Threshold</th></tr>
                        <tr><td>Leverage Ratio</td><td>Total Debt / EBITDA</td><td>< 6.0x</td></tr>
                        <tr><td>Interest Coverage</td><td>EBITDA / Interest</td><td>> 2.0x</td></tr>
                        <tr><td>Fixed Charge Coverage</td><td>EBITDA / (Interest + Principal)</td><td>> 1.2x</td></tr>
                    </table>

                    <blockquote>
                        <strong>Pro Tip:</strong> Always run downside scenarios. What happens if revenue drops 20%? Can the company still service its debt?
                    </blockquote>
                `,

                'due-diligence': `
                    <h2>Introduction</h2>
                    <p>Due diligence is the comprehensive investigation of a target company before an acquisition. It aims to confirm assumptions, identify risks, and validate the investment thesis.</p>

                    <div class="nrt-guide-concept">
                        <h4>Key Concept</h4>
                        <p>Due diligence is not about finding reasons to do the deal—it's about finding reasons NOT to do the deal, or to adjust the price/terms.</p>
                    </div>

                    <h2>Types of Due Diligence</h2>

                    <h3>1. Financial Due Diligence</h3>
                    <ul>
                        <li>Quality of Earnings (QoE) analysis</li>
                        <li>Normalized EBITDA adjustments</li>
                        <li>Working capital analysis</li>
                        <li>Debt and debt-like items</li>
                        <li>Historical and projected performance</li>
                    </ul>

                    <h3>2. Commercial Due Diligence</h3>
                    <ul>
                        <li>Market size and growth</li>
                        <li>Competitive landscape</li>
                        <li>Customer concentration and churn</li>
                        <li>Pricing power</li>
                        <li>Sales pipeline validation</li>
                    </ul>

                    <h3>3. Operational Due Diligence</h3>
                    <ul>
                        <li>Management team assessment</li>
                        <li>Key person dependencies</li>
                        <li>IT systems and infrastructure</li>
                        <li>Supply chain resilience</li>
                        <li>Facility conditions</li>
                    </ul>

                    <h3>4. Legal Due Diligence</h3>
                    <ul>
                        <li>Corporate structure</li>
                        <li>Material contracts</li>
                        <li>Litigation and disputes</li>
                        <li>Intellectual property</li>
                        <li>Regulatory compliance</li>
                    </ul>

                    <h2>Quality of Earnings Adjustments</h2>
                    <table>
                        <tr><th>Adjustment Type</th><th>Examples</th></tr>
                        <tr><td>Non-recurring items</td><td>One-time legal fees, restructuring costs</td></tr>
                        <tr><td>Pro forma adjustments</td><td>Full-year impact of acquisitions</td></tr>
                        <tr><td>Normalization</td><td>Owner salary, related party transactions</td></tr>
                        <tr><td>Timing differences</td><td>Revenue recognition issues</td></tr>
                    </table>

                    <h2>Red Flags to Watch For</h2>
                    <ul>
                        <li>Customer concentration > 20% in single customer</li>
                        <li>Declining margins despite revenue growth</li>
                        <li>Unusual related party transactions</li>
                        <li>Key employees with no non-competes</li>
                        <li>Deferred maintenance or underinvestment</li>
                        <li>Aggressive revenue recognition</li>
                    </ul>

                    <blockquote>
                        <strong>Pro Tip:</strong> Spend time with customers and employees, not just management. The real insights come from the people on the ground.
                    </blockquote>
                `,

                'direct-lending': `
                    <h2>Introduction</h2>
                    <p>Private credit (or direct lending) refers to loans made by non-bank lenders directly to middle-market companies. It has grown dramatically as banks retreated from leveraged lending post-2008.</p>

                    <div class="nrt-guide-concept">
                        <h4>Key Concept</h4>
                        <p>Direct lenders provide customized financing solutions with faster execution and greater flexibility than traditional bank syndications.</p>
                    </div>

                    <h2>Types of Private Credit</h2>
                    <table>
                        <tr><th>Type</th><th>Typical Yield</th><th>Position</th></tr>
                        <tr><td>Senior Secured</td><td>SOFR + 500-650bps</td><td>First lien</td></tr>
                        <tr><td>Unitranche</td><td>SOFR + 550-700bps</td><td>Blended first/second</td></tr>
                        <tr><td>Second Lien</td><td>SOFR + 750-900bps</td><td>Junior secured</td></tr>
                        <tr><td>Mezzanine</td><td>12-15% (cash + PIK)</td><td>Unsecured/subordinated</td></tr>
                    </table>

                    <h2>Unitranche Deep Dive</h2>
                    <p>Unitranche combines senior and subordinated debt into a single facility with one set of documents and one blended rate. Benefits include:</p>
                    <ul>
                        <li>Simplified capital structure</li>
                        <li>Faster execution</li>
                        <li>Single lender relationship</li>
                        <li>Greater flexibility in amendments</li>
                    </ul>

                    <h2>Key Underwriting Criteria</h2>
                    <ol>
                        <li><strong>Cash Flow Stability:</strong> Recurring revenue, contracted backlog</li>
                        <li><strong>Margin Profile:</strong> Consistent EBITDA margins above 15%</li>
                        <li><strong>Low CapEx Requirements:</strong> Asset-light models preferred</li>
                        <li><strong>Sponsor Quality:</strong> Track record and equity cushion</li>
                        <li><strong>Management Team:</strong> Experience through economic cycles</li>
                    </ol>

                    <h2>Credit Documentation</h2>
                    <h3>Key Terms to Negotiate</h3>
                    <ul>
                        <li>Financial covenants (leverage, coverage)</li>
                        <li>EBITDA add-backs and adjustments</li>
                        <li>Permitted acquisitions and investments</li>
                        <li>Restricted payments and dividends</li>
                        <li>Change of control provisions</li>
                    </ul>

                    <h2>Returns Analysis</h2>
                    <p>Private credit funds target:</p>
                    <ul>
                        <li>Gross yields of 10-14%</li>
                        <li>Net returns to LPs of 7-10%</li>
                        <li>Low loss rates (< 2% annually)</li>
                        <li>Steady income with capital preservation</li>
                    </ul>

                    <blockquote>
                        <strong>Pro Tip:</strong> In credit, you don't get paid for taking risk—you get paid for avoiding loss. Focus on downside protection first.
                    </blockquote>
                `,

                'valuation-methods': `
                    <h2>Introduction</h2>
                    <p>Understanding when and how to apply different valuation methodologies is a critical skill for any finance professional. Each method has specific strengths, limitations, and ideal use cases. The best analysis often triangulates value using multiple approaches.</p>

                    <div class="nrt-guide-concept">
                        <h4>Key Concept</h4>
                        <p>There is no single "correct" valuation. The goal is to establish a defensible range based on multiple methodologies, then identify where within that range the true value likely falls.</p>
                    </div>

                    <h2>The Valuation Landscape</h2>
                    <table>
                        <tr><th>Category</th><th>Methods</th><th>Philosophy</th></tr>
                        <tr><td>Intrinsic Value</td><td>DCF, DDM, APV</td><td>Value based on fundamentals</td></tr>
                        <tr><td>Relative Value</td><td>Trading Comps, Precedents</td><td>Value based on market pricing</td></tr>
                        <tr><td>Asset-Based</td><td>NAV, Liquidation</td><td>Value based on underlying assets</td></tr>
                        <tr><td>Return-Based</td><td>LBO, Leveraged Recap</td><td>Value based on required returns</td></tr>
                    </table>

                    <h2>Method Selection Framework</h2>

                    <h3>When to Use DCF</h3>
                    <ul>
                        <li><strong>Best for:</strong> Mature, stable companies with predictable cash flows</li>
                        <li><strong>Avoid when:</strong> Early-stage companies, highly cyclical businesses, distressed situations</li>
                        <li><strong>Key inputs:</strong> Revenue growth, margins, CapEx, working capital, WACC, terminal value</li>
                    </ul>

                    <h3>When to Use Trading Comps</h3>
                    <ul>
                        <li><strong>Best for:</strong> Quick valuation, market sentiment check, pricing IPOs</li>
                        <li><strong>Avoid when:</strong> No true comparables exist, markets are dislocated</li>
                        <li><strong>Key inputs:</strong> Peer selection, multiple normalization, premium/discount rationale</li>
                    </ul>

                    <h3>When to Use Precedent Transactions</h3>
                    <ul>
                        <li><strong>Best for:</strong> M&A pricing, control value assessment, premiums paid analysis</li>
                        <li><strong>Avoid when:</strong> Deal flow is limited, transactions are stale (>2-3 years)</li>
                        <li><strong>Key inputs:</strong> Transaction selection, control premium adjustment, synergy haircut</li>
                    </ul>

                    <h3>When to Use LBO Analysis</h3>
                    <ul>
                        <li><strong>Best for:</strong> Floor valuation, PE pricing, debt capacity assessment</li>
                        <li><strong>Avoid when:</strong> Company can't support leverage, no realistic exit path</li>
                        <li><strong>Key inputs:</strong> Entry/exit multiples, leverage capacity, hold period returns</li>
                    </ul>

                    <h2>Valuation Football Field</h2>
                    <p>A valuation football field presents all methods side by side, showing value ranges from each approach. This helps identify:</p>
                    <ul>
                        <li>Convergence zones where methods agree</li>
                        <li>Outliers that need investigation</li>
                        <li>The defensible value range for negotiation</li>
                        <li>Sensitivity to key assumptions</li>
                    </ul>

                    <h2>Common Pitfalls</h2>
                    <ol>
                        <li><strong>Cherry-picking:</strong> Only using the method that supports your conclusion</li>
                        <li><strong>False precision:</strong> Providing a single-point estimate instead of a range</li>
                        <li><strong>Stale inputs:</strong> Using outdated market data or transactions</li>
                        <li><strong>Ignoring context:</strong> Applying premiums/discounts without rationale</li>
                        <li><strong>Circular logic:</strong> Using stock price to derive cost of equity in DCF</li>
                    </ol>

                    <blockquote>
                        <strong>Pro Tip:</strong> The method selection discussion itself is often more valuable than the final number. It demonstrates your understanding of the situation and builds credibility with clients.
                    </blockquote>
                `,

                'cost-of-capital': `
                    <h2>Introduction</h2>
                    <p>The cost of capital represents the minimum return a company must earn on its investments to satisfy its capital providers. It serves as the discount rate in DCF analysis and the hurdle rate for capital allocation decisions.</p>

                    <div class="nrt-guide-concept">
                        <h4>Key Concept</h4>
                        <p>The cost of capital reflects the opportunity cost of funds—what investors could earn elsewhere at equivalent risk. A project only creates value if it returns more than this hurdle.</p>
                    </div>

                    <h2>Weighted Average Cost of Capital (WACC)</h2>
                    <div class="nrt-guide-formula">
                        WACC = (E/V × Re) + (D/V × Rd × (1 - T))
                    </div>

                    <p>Where:</p>
                    <ul>
                        <li><strong>E</strong> = Market value of equity</li>
                        <li><strong>D</strong> = Market value of debt</li>
                        <li><strong>V</strong> = Total capital (E + D)</li>
                        <li><strong>Re</strong> = Cost of equity</li>
                        <li><strong>Rd</strong> = Cost of debt</li>
                        <li><strong>T</strong> = Marginal tax rate</li>
                    </ul>

                    <h2>Cost of Equity: The CAPM Model</h2>
                    <div class="nrt-guide-formula">
                        Re = Rf + β × (Rm - Rf) + Size Premium + Company-Specific Premium
                    </div>

                    <h3>Risk-Free Rate (Rf)</h3>
                    <p>Typically the 10-year or 20-year government bond yield. Choose a maturity matching the investment horizon. For U.S. analysis, use Treasury yields; for other regions, use local sovereign debt or swap rates adjusted for country risk.</p>

                    <h3>Equity Risk Premium (Rm - Rf)</h3>
                    <p>Historical premium of stocks over bonds. Common approaches:</p>
                    <table>
                        <tr><th>Source</th><th>Method</th><th>Typical Range</th></tr>
                        <tr><td>Duff & Phelps</td><td>Supply-side</td><td>5.0% - 6.0%</td></tr>
                        <tr><td>Damodaran</td><td>Implied ERP</td><td>4.5% - 5.5%</td></tr>
                        <tr><td>Historical</td><td>20-year average</td><td>5.5% - 7.0%</td></tr>
                    </table>

                    <h3>Beta (β)</h3>
                    <p>Beta measures systematic risk relative to the market. Calculation methods:</p>
                    <ul>
                        <li><strong>Raw beta:</strong> Regression of stock returns vs. market (2-5 years weekly data)</li>
                        <li><strong>Adjusted beta:</strong> Raw beta adjusted toward 1.0 (Bloomberg formula: 0.67 × Raw + 0.33 × 1)</li>
                        <li><strong>Unlevered beta:</strong> Remove capital structure effect to compare across companies</li>
                        <li><strong>Re-levered beta:</strong> Apply target capital structure to unlevered beta</li>
                    </ul>

                    <h3>Unlevering and Re-levering Beta</h3>
                    <div class="nrt-guide-formula">
                        Unlevered Beta = Levered Beta / (1 + (1-T) × D/E)
                    </div>
                    <div class="nrt-guide-formula">
                        Re-levered Beta = Unlevered Beta × (1 + (1-T) × Target D/E)
                    </div>

                    <h2>Cost of Debt</h2>
                    <p>The cost of debt should reflect the yield at which the company can currently borrow, not historical coupons:</p>
                    <ul>
                        <li><strong>Traded debt:</strong> Use current yield-to-maturity</li>
                        <li><strong>No traded debt:</strong> Use synthetic rating approach (spread over risk-free)</li>
                        <li><strong>Tax shield:</strong> Multiply by (1-T) since interest is tax-deductible</li>
                    </ul>

                    <h2>Size Premium</h2>
                    <p>Smaller companies historically earn higher returns than CAPM predicts. Add size premium based on market cap decile:</p>
                    <table>
                        <tr><th>Decile</th><th>Market Cap</th><th>Size Premium</th></tr>
                        <tr><td>1-2</td><td>> $20B</td><td>0.0%</td></tr>
                        <tr><td>3-5</td><td>$2B - $20B</td><td>0.5% - 1.5%</td></tr>
                        <tr><td>6-8</td><td>$500M - $2B</td><td>1.5% - 3.0%</td></tr>
                        <tr><td>9-10</td><td>< $500M</td><td>3.0% - 6.0%</td></tr>
                    </table>

                    <h2>Capital Structure Considerations</h2>
                    <ul>
                        <li><strong>Book vs. Market:</strong> Always use market values for weights</li>
                        <li><strong>Target vs. Current:</strong> For going-concern, use target capital structure</li>
                        <li><strong>Changing leverage:</strong> Consider APV method if structure will change materially</li>
                    </ul>

                    <blockquote>
                        <strong>Pro Tip:</strong> Run sensitivity analysis on WACC inputs. A 1% change in WACC can significantly impact DCF value—understand which inputs drive the most variance.
                    </blockquote>
                `,

                'ev-equity-bridge': `
                    <h2>Introduction</h2>
                    <p>The bridge from Enterprise Value to Equity Value is one of the most misunderstood concepts in valuation. Getting this wrong can lead to significant errors, especially when dealing with complex capital structures.</p>

                    <div class="nrt-guide-concept">
                        <h4>Key Concept</h4>
                        <p>Enterprise Value represents the value of the entire business; Equity Value represents only the portion available to shareholders. The bridge items represent claims that reduce (or increase) what equity holders receive.</p>
                    </div>

                    <h2>The Basic Bridge</h2>
                    <table>
                        <tr><th>Item</th><th>Sign</th><th>Rationale</th></tr>
                        <tr><td>Enterprise Value</td><td></td><td>Value of operating business</td></tr>
                        <tr><td>Less: Total Debt</td><td>(-)</td><td>Debt holders have priority claim</td></tr>
                        <tr><td>Plus: Cash & Equivalents</td><td>(+)</td><td>Available to equity holders</td></tr>
                        <tr><td>Less: Preferred Stock</td><td>(-)</td><td>Senior to common equity</td></tr>
                        <tr><td>Less: Minority Interest</td><td>(-)</td><td>Value belonging to minorities</td></tr>
                        <tr><td>Plus: Equity Investments</td><td>(+)</td><td>Non-operating assets</td></tr>
                        <tr><td><strong>Equity Value</strong></td><td><strong>=</strong></td><td><strong>Value to shareholders</strong></td></tr>
                    </table>

                    <h2>Detailed Bridge Items</h2>

                    <h3>Total Debt</h3>
                    <p>Includes all interest-bearing obligations:</p>
                    <ul>
                        <li>Short-term borrowings</li>
                        <li>Long-term debt (current and non-current)</li>
                        <li>Capital leases (now called finance leases under IFRS 16/ASC 842)</li>
                        <li>Notes payable</li>
                        <li>Convertible debt (at face value unless converted)</li>
                    </ul>

                    <h3>Cash & Equivalents</h3>
                    <p>Not all cash should be added back. Consider:</p>
                    <ul>
                        <li><strong>Operating cash:</strong> Minimum required for daily operations (typically 2-5% of revenue)</li>
                        <li><strong>Excess cash:</strong> Cash above operating needs—this is what adds to equity value</li>
                        <li><strong>Restricted cash:</strong> May not be distributable—often excluded</li>
                        <li><strong>Trapped cash:</strong> Foreign cash with repatriation costs—discount appropriately</li>
                    </ul>

                    <h3>Minority Interest (Non-Controlling Interest)</h3>
                    <p>When the parent consolidates a subsidiary but doesn't own 100%, the minority's share must be subtracted:</p>
                    <ul>
                        <li>Book value is often inadequate—consider fair value</li>
                        <li>Apply subsidiary's trading multiple if public</li>
                        <li>If material, value separately</li>
                    </ul>

                    <h3>Equity Investments (Associates/JVs)</h3>
                    <p>Investments not consolidated (typically 20-50% ownership) are recorded at equity method. Add to equity value at fair value:</p>
                    <ul>
                        <li>If traded, use market value</li>
                        <li>If private, apply comparable multiples</li>
                        <li>Consider control discounts for minority positions</li>
                    </ul>

                    <h2>Debt-Like Items (The Gray Zone)</h2>
                    <p>These items are often contentious in transactions:</p>

                    <table>
                        <tr><th>Item</th><th>Treatment</th><th>Notes</th></tr>
                        <tr><td>Pension obligations (unfunded)</td><td>Subtract</td><td>Pre-tax, net of plan assets</td></tr>
                        <tr><td>Operating leases</td><td>Now debt under new standards</td><td>Liability already capitalized</td></tr>
                        <tr><td>Deferred revenue</td><td>Usually excluded</td><td>Unless performance unlikely</td></tr>
                        <tr><td>Contingent liabilities</td><td>Subtract at expected value</td><td>Risk-weight probabilities</td></tr>
                        <tr><td>Asset retirement obligations</td><td>Subtract</td><td>Present value of future costs</td></tr>
                        <tr><td>Deferred tax liabilities</td><td>Subtract if reversal likely</td><td>Often excluded in practice</td></tr>
                    </table>

                    <h2>Special Situations</h2>

                    <h3>Convertible Securities</h3>
                    <p>Depends on conversion likelihood:</p>
                    <ul>
                        <li><strong>In-the-money:</strong> Treat as equity (include shares in diluted count)</li>
                        <li><strong>Out-of-the-money:</strong> Treat as debt (add to debt at face value)</li>
                    </ul>

                    <h3>Stock Options & Warrants</h3>
                    <p>Use Treasury Stock Method for diluted share count:</p>
                    <div class="nrt-guide-formula">
                        Dilutive Shares = Options × (1 - Strike Price / Current Price)
                    </div>

                    <blockquote>
                        <strong>Pro Tip:</strong> In M&A, the EV to equity bridge items are often negotiated line by line. A well-documented bridge with clear rationale for each item is essential for deal negotiations.
                    </blockquote>
                `,

                'fair-value': `
                    <h2>Introduction</h2>
                    <p>Fair value accounting (ASC 820 / IFRS 13) has become central to financial reporting, especially for financial instruments, business combinations, and impairment testing. Understanding the fair value hierarchy and measurement techniques is essential for both valuation and financial analysis.</p>

                    <div class="nrt-guide-concept">
                        <h4>Key Concept</h4>
                        <p>Fair value is the price at which an orderly transaction would occur between market participants at the measurement date—an exit price, not an entry price.</p>
                    </div>

                    <h2>The Fair Value Hierarchy</h2>
                    <table>
                        <tr><th>Level</th><th>Inputs</th><th>Examples</th></tr>
                        <tr><td>Level 1</td><td>Quoted prices in active markets for identical assets</td><td>Public stock prices, exchange-traded options</td></tr>
                        <tr><td>Level 2</td><td>Observable inputs other than Level 1</td><td>Similar securities, yield curves, implied volatilities</td></tr>
                        <tr><td>Level 3</td><td>Unobservable inputs (management estimates)</td><td>Private company valuation, complex derivatives</td></tr>
                    </table>

                    <h2>Valuation Approaches Under ASC 820</h2>

                    <h3>1. Market Approach</h3>
                    <p>Uses prices and relevant information from market transactions involving identical or comparable assets:</p>
                    <ul>
                        <li>Guideline public company method</li>
                        <li>Guideline transaction method</li>
                        <li>Subject company transaction method</li>
                    </ul>

                    <h3>2. Income Approach</h3>
                    <p>Converts future amounts (cash flows, earnings) to a single present value:</p>
                    <ul>
                        <li>Discounted Cash Flow (DCF)</li>
                        <li>Multi-Period Excess Earnings Method (MPEEM)</li>
                        <li>Relief from Royalty</li>
                        <li>Option Pricing Models</li>
                    </ul>

                    <h3>3. Cost Approach</h3>
                    <p>Reflects the amount required to replace the service capacity of an asset:</p>
                    <ul>
                        <li>Replacement cost new</li>
                        <li>Less: Physical, functional, and economic obsolescence</li>
                        <li>Best for tangible assets with active replacement markets</li>
                    </ul>

                    <h2>Purchase Price Allocation (PPA)</h2>
                    <p>When a business is acquired, fair value must be assigned to all identifiable assets and liabilities. Key categories:</p>

                    <h3>Tangible Assets</h3>
                    <ul>
                        <li>Property, Plant & Equipment: Cost or market approach</li>
                        <li>Inventory: Net realizable value less disposal costs and profit margin</li>
                        <li>Working capital items: Generally at face value</li>
                    </ul>

                    <h3>Intangible Assets</h3>
                    <table>
                        <tr><th>Asset Type</th><th>Primary Method</th><th>Key Inputs</th></tr>
                        <tr><td>Customer relationships</td><td>MPEEM / Income</td><td>Attrition rate, margins, WARA</td></tr>
                        <tr><td>Technology</td><td>Relief from royalty</td><td>Royalty rate, useful life</td></tr>
                        <tr><td>Trade names</td><td>Relief from royalty</td><td>Royalty rate, useful life</td></tr>
                        <tr><td>Backlog</td><td>Income approach</td><td>Margin, completion period</td></tr>
                        <tr><td>Non-competes</td><td>With-and-without</td><td>Probability of compete, impact</td></tr>
                    </table>

                    <h2>Goodwill and Impairment</h2>
                    <p>Goodwill = Purchase Price - Fair Value of Net Identifiable Assets</p>
                    <p>Annual impairment testing requires comparing:</p>
                    <ul>
                        <li>Carrying amount of reporting unit</li>
                        <li>Fair value of reporting unit (typically DCF + market approach)</li>
                        <li>If carrying > fair value, impairment = the difference (limited to goodwill balance)</li>
                    </ul>

                    <h2>Common Controversies</h2>
                    <ol>
                        <li><strong>Customer relationship lives:</strong> 5-15 years typical; attrition analysis critical</li>
                        <li><strong>Tax amortization benefit (TAB):</strong> Include for tax-deductible intangibles</li>
                        <li><strong>WARA reconciliation:</strong> Weighted average return should approximate WACC</li>
                        <li><strong>Workforce:</strong> Not separately recognizable, but affects replacement cost of intangibles</li>
                    </ol>

                    <blockquote>
                        <strong>Pro Tip:</strong> In a PPA, the sum of the parts (tangibles + intangibles + goodwill) must equal the total purchase price. Use WARA as a sanity check—if intangible returns are wildly different from WACC, revisit your assumptions.
                    </blockquote>
                `,

                'financial-modeling': `
                    <h2>Introduction</h2>
                    <p>Financial modeling is the foundation of investment banking, private equity, and corporate finance. A well-built model is not just a spreadsheet—it's a decision-making tool that should be flexible, auditable, and error-free.</p>

                    <div class="nrt-guide-concept">
                        <h4>Key Concept</h4>
                        <p>The best models are simple, transparent, and built for change. If you can't explain your model in five minutes, it's too complicated.</p>
                    </div>

                    <h2>Model Architecture Best Practices</h2>

                    <h3>Sheet Organization</h3>
                    <ul>
                        <li><strong>Cover:</strong> Version control, key contacts, last updated</li>
                        <li><strong>Summary/Dashboard:</strong> Key outputs, sensitivity tables, valuation summary</li>
                        <li><strong>Assumptions:</strong> All inputs in one place (blue font for hard-coded)</li>
                        <li><strong>Income Statement:</strong> Historical + projected</li>
                        <li><strong>Balance Sheet:</strong> Historical + projected</li>
                        <li><strong>Cash Flow Statement:</strong> Derived from I/S and B/S changes</li>
                        <li><strong>Supporting Schedules:</strong> Debt, depreciation, working capital, etc.</li>
                        <li><strong>Valuation:</strong> DCF, comps, football field</li>
                    </ul>

                    <h3>Formatting Standards</h3>
                    <table>
                        <tr><th>Convention</th><th>Format</th><th>Purpose</th></tr>
                        <tr><td>Hard-coded inputs</td><td>Blue font</td><td>Identify changeable assumptions</td></tr>
                        <tr><td>Formulas</td><td>Black font</td><td>Calculated values</td></tr>
                        <tr><td>Links to other sheets</td><td>Green font</td><td>Cross-references</td></tr>
                        <tr><td>Historical data</td><td>Gray shading</td><td>Distinguish from projections</td></tr>
                        <tr><td>Negative numbers</td><td>(Parentheses)</td><td>Finance convention</td></tr>
                    </table>

                    <h2>Building the Three-Statement Model</h2>

                    <h3>Step 1: Income Statement Drivers</h3>
                    <ul>
                        <li>Revenue: Bottom-up (volume × price) or top-down (market share × market size)</li>
                        <li>COGS: As % of revenue or per-unit cost</li>
                        <li>OpEx: Fixed vs. variable breakdown</li>
                        <li>D&A: From depreciation schedule</li>
                        <li>Interest: From debt schedule</li>
                        <li>Taxes: Effective rate with NOL consideration</li>
                    </ul>

                    <h3>Step 2: Balance Sheet Drivers</h3>
                    <ul>
                        <li>Working capital: Days methodology (DSO, DIO, DPO)</li>
                        <li>PP&E: From CapEx and depreciation schedules</li>
                        <li>Debt: From debt schedule with mandatory amortization</li>
                        <li>Equity: Prior period + net income - dividends + share issuance</li>
                    </ul>

                    <h3>Step 3: Cash Flow Statement</h3>
                    <ul>
                        <li>Start with Net Income</li>
                        <li>Add back non-cash charges (D&A, stock comp)</li>
                        <li>Subtract/add working capital changes</li>
                        <li>Subtract CapEx (investing)</li>
                        <li>Add/subtract debt changes, dividends (financing)</li>
                        <li>Result: Cash balance change → feeds back to B/S</li>
                    </ul>

                    <h2>The Circular Reference Problem</h2>
                    <p>Interest expense depends on debt, which depends on cash flow, which depends on interest expense. Solutions:</p>
                    <ul>
                        <li><strong>Average debt method:</strong> Interest = Rate × (Beginning + Ending Debt) / 2</li>
                        <li><strong>Beginning balance:</strong> Interest = Rate × Beginning Debt (simpler, slight timing difference)</li>
                        <li><strong>Iterative calculation:</strong> Enable iteration in Excel (careful—can mask errors)</li>
                    </ul>

                    <h2>Error Checking & Auditing</h2>
                    <ol>
                        <li><strong>Balance sheet check:</strong> Assets = Liabilities + Equity (always)</li>
                        <li><strong>Cash flow check:</strong> Ending cash = B/S cash balance</li>
                        <li><strong>Sign convention:</strong> Consistent treatment of inflows/outflows</li>
                        <li><strong>Trace formulas:</strong> F2 to enter cell, then trace precedents/dependents</li>
                        <li><strong>Stress test:</strong> Run extreme scenarios to find breaking points</li>
                    </ol>

                    <h2>Advanced Modeling Techniques</h2>

                    <h3>Scenarios and Sensitivities</h3>
                    <p>Build scenario toggles directly into assumptions:</p>
                    <ul>
                        <li>Base, upside, downside cases</li>
                        <li>Data tables for 2-way sensitivity (e.g., growth vs. margin)</li>
                        <li>Scenario manager for multi-variable scenarios</li>
                    </ul>

                    <h3>Dynamic Timelines</h3>
                    <p>Use INDEX/MATCH or OFFSET to create flexible forecast periods without rebuilding the model.</p>

                    <blockquote>
                        <strong>Pro Tip:</strong> Before presenting any model output, hand-check the math on paper. If revenue is $100M, margin is 20%, and you show $50M EBITDA, you have a problem. Always sanity check.
                    </blockquote>
                `,

                'ma-structuring': `
                    <h2>Introduction</h2>
                    <p>M&A transaction structuring determines how a deal is legally organized, financed, and executed. The structure affects taxes, accounting treatment, liability transfer, shareholder approvals, and deal certainty. Getting structure right is essential for deal success.</p>

                    <div class="nrt-guide-concept">
                        <h4>Key Concept</h4>
                        <p>There's no "best" deal structure—only the best structure for a particular situation, balancing tax efficiency, regulatory approval, deal speed, and stakeholder interests.</p>
                    </div>

                    <h2>Primary Deal Structures</h2>

                    <h3>1. Stock Purchase</h3>
                    <p>Buyer acquires target's shares directly from shareholders.</p>
                    <table>
                        <tr><th>Advantages</th><th>Disadvantages</th></tr>
                        <tr><td>Simple execution</td><td>Inherit all liabilities (known and unknown)</td></tr>
                        <tr><td>Contracts/licenses transfer</td><td>No step-up in asset basis</td></tr>
                        <tr><td>No successor liability issues</td><td>Minority shareholders may hold out</td></tr>
                        <tr><td>Often faster close</td><td>May require 100% consent</td></tr>
                    </table>

                    <h3>2. Asset Purchase</h3>
                    <p>Buyer acquires specific assets and assumes specific liabilities.</p>
                    <table>
                        <tr><th>Advantages</th><th>Disadvantages</th></tr>
                        <tr><td>Cherry-pick assets</td><td>Contracts may require consent to assign</td></tr>
                        <tr><td>Avoid unwanted liabilities</td><td>Bulk sales laws may apply</td></tr>
                        <tr><td>Tax step-up in basis</td><td>More complex documentation</td></tr>
                        <tr><td>No minority holdout issue</td><td>Employment relationships don't transfer</td></tr>
                    </table>

                    <h3>3. Merger</h3>
                    <p>Target merges into buyer (forward) or buyer sub (reverse triangular).</p>
                    <ul>
                        <li><strong>Forward merger:</strong> Target disappears into buyer</li>
                        <li><strong>Reverse triangular:</strong> Buyer sub merges into target; target survives (preserves contracts)</li>
                        <li><strong>Forward triangular:</strong> Target merges into buyer sub</li>
                    </ul>

                    <h2>Payment Considerations</h2>

                    <h3>Cash vs. Stock</h3>
                    <table>
                        <tr><th>Factor</th><th>Cash</th><th>Stock</th></tr>
                        <tr><td>Seller tax</td><td>Immediate taxable gain</td><td>Tax-deferred (if qualified)</td></tr>
                        <tr><td>Buyer dilution</td><td>None</td><td>Ownership dilution</td></tr>
                        <tr><td>Deal certainty</td><td>Higher (no volatility risk)</td><td>Stock price exposure</td></tr>
                        <tr><td>Seller preference</td><td>If want liquidity</td><td>If believe in upside</td></tr>
                    </table>

                    <h3>Contingent Consideration (Earnouts)</h3>
                    <p>Deferred payments based on future performance. Common in:</p>
                    <ul>
                        <li>Early-stage companies with uncertain projections</li>
                        <li>Bridging valuation gaps</li>
                        <li>Retaining key management</li>
                    </ul>
                    <p>Key terms: Metrics (revenue, EBITDA), measurement period, caps/floors, acceleration on change of control.</p>

                    <h2>Tax Structuring</h2>

                    <h3>338(h)(10) Election</h3>
                    <p>Stock purchase treated as asset purchase for tax purposes:</p>
                    <ul>
                        <li>Buyer gets step-up in asset basis</li>
                        <li>Seller recognizes gain as if assets sold</li>
                        <li>Requires target to be S-corp or consolidated sub</li>
                        <li>Purchase price allocation required</li>
                    </ul>

                    <h3>Tax-Free Reorganizations</h3>
                    <p>IRS-approved structures allowing tax deferral:</p>
                    <ul>
                        <li><strong>Type A:</strong> Statutory merger</li>
                        <li><strong>Type B:</strong> Stock-for-stock acquisition (solely voting stock)</li>
                        <li><strong>Type C:</strong> Asset acquisition for voting stock</li>
                    </ul>

                    <h2>Regulatory Considerations</h2>
                    <ul>
                        <li><strong>HSR Act:</strong> Hart-Scott-Rodino filing if thresholds met ($119.5M in 2024)</li>
                        <li><strong>CFIUS:</strong> Foreign buyer of U.S. business with national security implications</li>
                        <li><strong>Industry-specific:</strong> FCC, state insurance, banking regulators</li>
                        <li><strong>International:</strong> EU Merger Regulation, country-specific approvals</li>
                    </ul>

                    <blockquote>
                        <strong>Pro Tip:</strong> Structure discussions should happen early—not after terms are agreed. A seemingly small structural choice can have 8-figure tax implications.
                    </blockquote>
                `,

                'synergies': `
                    <h2>Introduction</h2>
                    <p>Synergies are the additional value created when two companies combine—the "1+1=3" effect. They're a primary driver of M&A premiums and often determine whether a deal creates or destroys value. However, synergies are also frequently overestimated and under-delivered.</p>

                    <div class="nrt-guide-concept">
                        <h4>Key Concept</h4>
                        <p>A synergy is only real if it wouldn't have occurred on a standalone basis and can actually be captured. Many claimed synergies fail the "would have happened anyway" test.</p>
                    </div>

                    <h2>Types of Synergies</h2>

                    <h3>Revenue Synergies</h3>
                    <p>Incremental revenue from the combination:</p>
                    <ul>
                        <li><strong>Cross-selling:</strong> Selling each company's products to the other's customers</li>
                        <li><strong>Geographic expansion:</strong> Leveraging distribution networks</li>
                        <li><strong>Product bundling:</strong> Creating combined offerings</li>
                        <li><strong>Pricing power:</strong> Reduced competition, increased market share</li>
                    </ul>
                    <p><strong>Realization rate:</strong> 30-50% of projected; take longer to achieve</p>

                    <h3>Cost Synergies</h3>
                    <p>Expense reduction from eliminating redundancies:</p>
                    <table>
                        <tr><th>Category</th><th>Typical Savings</th><th>Timeframe</th></tr>
                        <tr><td>Headcount reduction</td><td>20-30% of overlap</td><td>Year 1</td></tr>
                        <tr><td>Facilities consolidation</td><td>5-15% of real estate</td><td>Years 1-2</td></tr>
                        <tr><td>Procurement savings</td><td>2-5% of spend</td><td>Years 1-2</td></tr>
                        <tr><td>IT systems integration</td><td>10-20% of IT spend</td><td>Years 2-3</td></tr>
                        <tr><td>Marketing efficiency</td><td>5-10% of marketing</td><td>Year 1</td></tr>
                    </table>
                    <p><strong>Realization rate:</strong> 60-80% of projected; faster to achieve</p>

                    <h3>Financial Synergies</h3>
                    <ul>
                        <li><strong>Tax optimization:</strong> NOL utilization, tax planning</li>
                        <li><strong>Debt capacity:</strong> Combined entity can carry more leverage</li>
                        <li><strong>Cost of capital:</strong> Potentially lower WACC from diversification</li>
                    </ul>

                    <h2>Synergy Quantification Framework</h2>

                    <h3>Step 1: Identify Opportunity Areas</h3>
                    <p>Map both companies' cost structures and revenue streams to find overlap and enhancement potential.</p>

                    <h3>Step 2: Bottom-Up Estimation</h3>
                    <p>For each opportunity:</p>
                    <ul>
                        <li>Quantify the gross opportunity</li>
                        <li>Apply realization probability (risk-adjust)</li>
                        <li>Estimate time to achieve</li>
                        <li>Calculate one-time costs to achieve</li>
                    </ul>

                    <h3>Step 3: Validate Assumptions</h3>
                    <ul>
                        <li>Benchmark against comparable deals</li>
                        <li>Stress test with management</li>
                        <li>Consider integration complexity</li>
                    </ul>

                    <h2>Cost to Achieve</h2>
                    <p>Synergies aren't free. Common implementation costs:</p>
                    <table>
                        <tr><th>Cost Type</th><th>Typical Range</th></tr>
                        <tr><td>Severance & retention</td><td>1-2x annual savings</td></tr>
                        <tr><td>IT integration</td><td>$10-50M+ for major systems</td></tr>
                        <tr><td>Facility closure</td><td>1-3x annual lease costs</td></tr>
                        <tr><td>Rebranding</td><td>$5-20M depending on scope</td></tr>
                        <tr><td>Consulting fees</td><td>$5-15M for large integrations</td></tr>
                    </table>

                    <h2>Synergy Valuation</h2>
                    <div class="nrt-guide-formula">
                        NPV of Synergies = Σ (Annual Synergy × (1-Tax)) / (1 + WACC)<sup>t</sup> - Cost to Achieve
                    </div>

                    <p>Key considerations:</p>
                    <ul>
                        <li>Phase-in period (typically 1-3 years to full run-rate)</li>
                        <li>Use target's WACC (synergies are risky)</li>
                        <li>Apply haircut for execution risk (20-40%)</li>
                    </ul>

                    <h2>Common Synergy Traps</h2>
                    <ol>
                        <li><strong>Double counting:</strong> Same synergy appears in multiple categories</li>
                        <li><strong>Revenue dis-synergies:</strong> Customer loss from integration disruption</li>
                        <li><strong>Culture clash:</strong> Key talent departure, productivity loss</li>
                        <li><strong>Management distraction:</strong> Core business suffers during integration</li>
                        <li><strong>Regulatory constraints:</strong> Can't combine as planned due to antitrust</li>
                    </ol>

                    <blockquote>
                        <strong>Pro Tip:</strong> In competitive auctions, bidders often overestimate synergies to justify higher prices. The "winner's curse" is real—the highest bidder usually has the most optimistic (and often wrong) synergy assumptions.
                    </blockquote>
                `,

                'distressed-ma': `
                    <h2>Introduction</h2>
                    <p>Distressed M&A involves acquiring companies facing financial difficulties, often in or near bankruptcy. These transactions offer unique opportunities but require specialized skills in restructuring, credit analysis, and legal processes. The dynamics differ fundamentally from healthy company acquisitions.</p>

                    <div class="nrt-guide-concept">
                        <h4>Key Concept</h4>
                        <p>In distressed situations, traditional valuation approaches break down. Value depends not just on the business, but on the legal process, creditor dynamics, and buyer's ability to execute quickly.</p>
                    </div>

                    <h2>Distress Indicators</h2>
                    <table>
                        <tr><th>Category</th><th>Warning Signs</th></tr>
                        <tr><td>Liquidity</td><td>Revolver fully drawn, payables stretching, supplier COD demands</td></tr>
                        <tr><td>Credit</td><td>Covenant breaches, credit rating downgrades, bond yields >15%</td></tr>
                        <tr><td>Operations</td><td>Key customer losses, management turnover, asset sales</td></tr>
                        <tr><td>Market</td><td>Stock trading at distressed levels, debt below par, CDS widening</td></tr>
                    </table>

                    <h2>Distressed M&A Structures</h2>

                    <h3>1. Out-of-Court Sale</h3>
                    <p>Asset sale or merger before bankruptcy:</p>
                    <ul>
                        <li><strong>Pros:</strong> Faster, cheaper, less public</li>
                        <li><strong>Cons:</strong> Successor liability risk, fraudulent transfer exposure</li>
                        <li><strong>Use when:</strong> Consensual process, limited liability concerns</li>
                    </ul>

                    <h3>2. Section 363 Sale (U.S.)</h3>
                    <p>Asset sale through bankruptcy court approval:</p>
                    <ul>
                        <li><strong>Pros:</strong> "Free and clear" of liens/claims, court blessing</li>
                        <li><strong>Cons:</strong> Auction process, timeline uncertainty, bankruptcy stigma</li>
                        <li><strong>Use when:</strong> Material liability exposure, need clean title</li>
                    </ul>

                    <h3>3. Credit Bid</h3>
                    <p>Secured lender bids their debt claim for assets:</p>
                    <ul>
                        <li><strong>Pros:</strong> No cash outlay, can set floor price</li>
                        <li><strong>Cons:</strong> Limited to secured claim amount, may need to top up</li>
                        <li><strong>Use when:</strong> Lender wants to own asset, limited buyer interest</li>
                    </ul>

                    <h3>4. Plan Sponsor</h3>
                    <p>Investor funds reorganization plan and emerges as new owner:</p>
                    <ul>
                        <li><strong>Pros:</strong> Full control of process, preserve going concern value</li>
                        <li><strong>Cons:</strong> Longer timeline, plan confirmation risk</li>
                        <li><strong>Use when:</strong> Business viable, complex capital structure</li>
                    </ul>

                    <h2>Stalking Horse Bids</h2>
                    <p>A "stalking horse" is the first bidder in a 363 sale, setting the floor:</p>
                    <ul>
                        <li><strong>Bid protections:</strong> Break-up fee (1-3%), expense reimbursement</li>
                        <li><strong>Advantages:</strong> Inside track, set deal terms, due diligence access</li>
                        <li><strong>Risks:</strong> May be outbid at auction, time investment</li>
                    </ul>

                    <h2>Valuation in Distress</h2>

                    <h3>Key Differences</h3>
                    <ul>
                        <li><strong>Going concern vs. liquidation:</strong> Does business have viable future?</li>
                        <li><strong>Normalized EBITDA:</strong> Strip out distress-related costs, add back lost business</li>
                        <li><strong>Comparable selection:</strong> Use distressed transaction multiples</li>
                        <li><strong>Recovery analysis:</strong> What do creditors receive in various scenarios?</li>
                    </ul>

                    <h3>Typical Distressed Multiples</h3>
                    <table>
                        <tr><th>Situation</th><th>EV/EBITDA Range</th></tr>
                        <tr><td>Healthy company</td><td>8-12x</td></tr>
                        <tr><td>Operational distress</td><td>4-7x</td></tr>
                        <tr><td>Financial distress</td><td>5-8x</td></tr>
                        <tr><td>Liquidation scenario</td><td>2-4x (or asset value)</td></tr>
                    </table>

                    <h2>Due Diligence Focus Areas</h2>
                    <ol>
                        <li><strong>Cash runway:</strong> How long until liquidity exhausted?</li>
                        <li><strong>Critical vendors:</strong> Who will stop supplying?</li>
                        <li><strong>Customer stability:</strong> Will customers flee to competitors?</li>
                        <li><strong>Employee retention:</strong> Key talent flight risk</li>
                        <li><strong>Litigation/claims:</strong> What liabilities transfer?</li>
                        <li><strong>Executory contracts:</strong> Which contracts to assume/reject?</li>
                    </ol>

                    <blockquote>
                        <strong>Pro Tip:</strong> Speed is critical in distressed situations. The company is bleeding cash daily. Have your financing committed, diligence team ready, and decision-makers aligned before entering the process.
                    </blockquote>
                `,

                'covenants': `
                    <h2>Introduction</h2>
                    <p>Loan covenants are contractual provisions that protect lenders by restricting borrower behavior and requiring maintenance of certain financial metrics. Understanding covenants is essential for credit analysis, LBO modeling, and assessing a company's financial flexibility.</p>

                    <div class="nrt-guide-concept">
                        <h4>Key Concept</h4>
                        <p>Covenants serve two purposes: early warning system (identifying problems before default) and protection mechanism (preventing value leakage from the lender's collateral base).</p>
                    </div>

                    <h2>Types of Covenants</h2>

                    <h3>Financial Covenants (Maintenance)</h3>
                    <p>Tested periodically (usually quarterly); breach triggers default:</p>
                    <table>
                        <tr><th>Covenant</th><th>Formula</th><th>Typical Level</th></tr>
                        <tr><td>Leverage Ratio</td><td>Total Debt / EBITDA</td><td>< 5.0x - 7.0x</td></tr>
                        <tr><td>Senior Leverage</td><td>Senior Debt / EBITDA</td><td>< 4.0x - 5.0x</td></tr>
                        <tr><td>Interest Coverage</td><td>EBITDA / Interest</td><td>> 1.5x - 2.5x</td></tr>
                        <tr><td>Fixed Charge Coverage</td><td>EBITDA / (Interest + CapEx + Debt Service)</td><td>> 1.0x - 1.25x</td></tr>
                        <tr><td>Minimum EBITDA</td><td>Absolute floor</td><td>Varies</td></tr>
                    </table>

                    <h3>Incurrence Covenants</h3>
                    <p>Only tested when taking a specific action:</p>
                    <ul>
                        <li>Debt incurrence: Can only add debt if pro forma leverage < X</li>
                        <li>Restricted payments: Dividends/buybacks only if coverage > X</li>
                        <li>Asset sales: Proceeds must repay debt or reinvest</li>
                        <li>Investments: Limits on acquisitions or loans to affiliates</li>
                    </ul>

                    <h3>Negative Covenants</h3>
                    <p>Prohibited or restricted actions:</p>
                    <ul>
                        <li><strong>Liens:</strong> Can't pledge assets without lender consent</li>
                        <li><strong>Indebtedness:</strong> Limits on additional borrowing</li>
                        <li><strong>Mergers:</strong> M&A restrictions</li>
                        <li><strong>Dispositions:</strong> Asset sale limitations</li>
                        <li><strong>Affiliate transactions:</strong> Arm's length requirements</li>
                        <li><strong>Change of business:</strong> Stay in current industry</li>
                    </ul>

                    <h2>EBITDA Definition Battles</h2>
                    <p>The definition of EBITDA in credit agreements has expanded dramatically. Common add-backs:</p>

                    <h3>Standard Add-backs</h3>
                    <ul>
                        <li>Non-cash charges (stock comp, impairments)</li>
                        <li>One-time transaction costs</li>
                        <li>Restructuring charges</li>
                    </ul>

                    <h3>Aggressive Add-backs (Watch for These)</h3>
                    <ul>
                        <li>Projected cost savings (capped at 15-25% of EBITDA)</li>
                        <li>Pro forma acquisition EBITDA</li>
                        <li>Run-rate synergies (often uncapped)</li>
                        <li>Sponsor management fees</li>
                        <li>Public company costs post-take-private</li>
                    </ul>

                    <div class="nrt-guide-formula">
                        "Adjusted EBITDA" can be 20-40% higher than standard EBITDA due to add-backs
                    </div>

                    <h2>Covenant Cushion Analysis</h2>
                    <p>Key metric for credit analysis: how much can EBITDA decline before breach?</p>
                    <div class="nrt-guide-formula">
                        Cushion = (Actual EBITDA - Covenant EBITDA) / Actual EBITDA
                    </div>

                    <p>Example: If leverage covenant is 6.0x and debt is $600M, covenant EBITDA = $100M. If actual EBITDA is $120M, cushion = ($120M - $100M) / $120M = 17%</p>

                    <h2>Covenant-Lite ("Cov-Lite") Loans</h2>
                    <p>Loans with no maintenance covenants (only incurrence tests):</p>
                    <ul>
                        <li>Now >85% of leveraged loan market</li>
                        <li>Give borrowers more operational flexibility</li>
                        <li>Lenders lose early warning mechanism</li>
                        <li>Problems often surface later, with worse recoveries</li>
                    </ul>

                    <h2>Covenant Breach Consequences</h2>
                    <ol>
                        <li><strong>Technical default:</strong> Breach triggers event of default</li>
                        <li><strong>Standstill:</strong> Can't draw on revolver</li>
                        <li><strong>Waiver/amendment:</strong> Pay fee, often get tighter terms</li>
                        <li><strong>Cure rights:</strong> Sponsor equity injection to fix breach</li>
                        <li><strong>Acceleration:</strong> Lender can call loan due (rare in practice)</li>
                    </ol>

                    <blockquote>
                        <strong>Pro Tip:</strong> When modeling covenant compliance, stress test EBITDA down 20-30%. If the company breaches covenants in a moderate downside, the credit is fragile regardless of current cushion.
                    </blockquote>
                `,

                'mezzanine': `
                    <h2>Introduction</h2>
                    <p>Mezzanine financing sits between senior debt and equity in the capital structure. It provides flexible capital for acquisitions, growth, and recapitalizations, offering higher returns than senior debt while taking more risk. Understanding mezzanine is essential for PE professionals, CFOs, and credit investors.</p>

                    <div class="nrt-guide-concept">
                        <h4>Key Concept</h4>
                        <p>Mezzanine is "patient capital"—investors accept illiquidity and subordination in exchange for current income plus equity upside. It fills the gap when senior debt capacity is exhausted but equity would be too dilutive.</p>
                    </div>

                    <h2>Mezzanine Characteristics</h2>
                    <table>
                        <tr><th>Feature</th><th>Senior Debt</th><th>Mezzanine</th><th>Equity</th></tr>
                        <tr><td>Position</td><td>First lien</td><td>Junior/unsecured</td><td>Residual</td></tr>
                        <tr><td>Current yield</td><td>SOFR + 400-600</td><td>10-14%</td><td>0-2% (dividend)</td></tr>
                        <tr><td>Total return</td><td>6-9%</td><td>15-20%</td><td>20-30%+</td></tr>
                        <tr><td>Covenants</td><td>Maintenance</td><td>Incurrence</td><td>Board seats</td></tr>
                        <tr><td>Maturity</td><td>5-7 years</td><td>6-8 years</td><td>Perpetual</td></tr>
                    </table>

                    <h2>Types of Mezzanine Instruments</h2>

                    <h3>1. Subordinated Debt</h3>
                    <ul>
                        <li>Pure debt instrument, subordinated to senior</li>
                        <li>Cash interest typically 10-12%</li>
                        <li>Sometimes includes PIK (Payment-in-Kind) component</li>
                        <li>May include prepayment premiums (call protection)</li>
                    </ul>

                    <h3>2. Subordinated Debt + Warrants</h3>
                    <ul>
                        <li>Lower cash interest (8-10%) plus equity warrants</li>
                        <li>Warrants typically 3-10% of fully diluted equity</li>
                        <li>Strike price usually nominal ($0.01 or $1.00)</li>
                        <li>Provides equity upside participation</li>
                    </ul>

                    <h3>3. Convertible Notes</h3>
                    <ul>
                        <li>Debt that converts to equity at holder's option</li>
                        <li>Conversion price set at premium to current value</li>
                        <li>Lower cash coupon due to conversion optionality</li>
                        <li>Common in growth and venture situations</li>
                    </ul>

                    <h3>4. Preferred Equity</h3>
                    <ul>
                        <li>Equity with debt-like characteristics</li>
                        <li>Cumulative or non-cumulative dividends (8-12%)</li>
                        <li>Liquidation preference before common</li>
                        <li>Often redeemable after certain period</li>
                    </ul>

                    <h2>Mezzanine Return Components</h2>
                    <div class="nrt-guide-formula">
                        Total Return = Cash Interest + PIK Interest + Equity Participation + Fees
                    </div>

                    <p>Example structure:</p>
                    <table>
                        <tr><th>Component</th><th>Rate</th><th>Annual Value on $50M</th></tr>
                        <tr><td>Cash coupon</td><td>10%</td><td>$5.0M</td></tr>
                        <tr><td>PIK interest</td><td>3%</td><td>$1.5M (compounds)</td></tr>
                        <tr><td>Closing fee</td><td>2%</td><td>$1.0M (upfront)</td></tr>
                        <tr><td>Warrants (5%)</td><td>2x MOIC scenario</td><td>~$3-5M at exit</td></tr>
                        <tr><td><strong>Blended IRR</strong></td><td></td><td><strong>16-20%</strong></td></tr>
                    </table>

                    <h2>When to Use Mezzanine</h2>

                    <h3>Good Candidates</h3>
                    <ul>
                        <li>LBO with leverage above 4-5x EBITDA</li>
                        <li>Acquisition financing gap</li>
                        <li>Sponsor recap/dividend</li>
                        <li>Growth capital without equity dilution</li>
                        <li>Shareholder buyout (transition capital)</li>
                    </ul>

                    <h3>Poor Candidates</h3>
                    <ul>
                        <li>Cyclical businesses (can't service during downturns)</li>
                        <li>Low-margin businesses (can't afford high coupon)</li>
                        <li>Turnaround situations (risk too high for mezz returns)</li>
                        <li>Companies with heavy CapEx needs</li>
                    </ul>

                    <h2>Intercreditor Considerations</h2>
                    <p>Mezzanine lenders negotiate for protections:</p>
                    <ul>
                        <li><strong>Standstill period:</strong> Senior can't accelerate without waiting</li>
                        <li><strong>Cure rights:</strong> Right to cure senior defaults</li>
                        <li><strong>Purchase option:</strong> Right to buy senior debt at par</li>
                        <li><strong>Adequate protection:</strong> Consent rights in bankruptcy</li>
                    </ul>

                    <blockquote>
                        <strong>Pro Tip:</strong> Mezzanine investors underwrite to downside more than upside. They want to know: if things go wrong, what's my recovery? The equity upside is the "cherry on top," not the base case.
                    </blockquote>
                `,

                'debt-equity': `
                    <h2>Introduction</h2>
                    <p>The debt-equity tradeoff is fundamental to corporate finance and investment analysis. Choosing the right mix of debt and equity financing affects returns, risk, control, and flexibility. This guide covers the core concepts that every finance professional must understand.</p>

                    <div class="nrt-guide-concept">
                        <h4>Key Concept</h4>
                        <p>Debt magnifies returns—both positive and negative. The optimal capital structure balances the tax benefits of debt against financial distress costs and operational flexibility needs.</p>
                    </div>

                    <h2>Debt vs. Equity: Key Differences</h2>
                    <table>
                        <tr><th>Characteristic</th><th>Debt</th><th>Equity</th></tr>
                        <tr><td>Claim priority</td><td>Senior</td><td>Residual</td></tr>
                        <tr><td>Payment obligation</td><td>Fixed (interest + principal)</td><td>Discretionary (dividends)</td></tr>
                        <tr><td>Tax treatment</td><td>Interest deductible</td><td>Dividends not deductible</td></tr>
                        <tr><td>Maturity</td><td>Defined term</td><td>Perpetual</td></tr>
                        <tr><td>Control rights</td><td>Covenants only</td><td>Voting, board seats</td></tr>
                        <tr><td>Upside participation</td><td>Capped at interest</td><td>Unlimited</td></tr>
                        <tr><td>Downside exposure</td><td>Limited (can recover)</td><td>Total loss possible</td></tr>
                    </table>

                    <h2>The Modigliani-Miller Framework</h2>

                    <h3>M&M Proposition I (No Taxes)</h3>
                    <p>In a perfect market, firm value is independent of capital structure:</p>
                    <div class="nrt-guide-formula">
                        V<sub>L</sub> = V<sub>U</sub>
                    </div>

                    <h3>M&M Proposition II (No Taxes)</h3>
                    <p>Cost of equity increases with leverage to offset cheaper debt:</p>
                    <div class="nrt-guide-formula">
                        R<sub>E</sub> = R<sub>U</sub> + (R<sub>U</sub> - R<sub>D</sub>) × D/E
                    </div>

                    <h3>With Taxes</h3>
                    <p>Debt creates value through tax shield:</p>
                    <div class="nrt-guide-formula">
                        V<sub>L</sub> = V<sub>U</sub> + (T × D)
                    </div>
                    <p>This suggests 100% debt is optimal—but that ignores distress costs.</p>

                    <h2>Trade-Off Theory</h2>
                    <p>Optimal leverage balances:</p>
                    <ul>
                        <li><strong>Benefits of debt:</strong> Tax shield, discipline on management</li>
                        <li><strong>Costs of debt:</strong> Financial distress, agency costs, loss of flexibility</li>
                    </ul>

                    <h3>Financial Distress Costs</h3>
                    <ul>
                        <li><strong>Direct:</strong> Legal fees, advisor fees, court costs (2-5% of value)</li>
                        <li><strong>Indirect:</strong> Customer loss, employee flight, supplier terms (10-20%+ of value)</li>
                    </ul>

                    <h2>Leverage and Returns</h2>

                    <h3>Return on Equity Amplification</h3>
                    <div class="nrt-guide-formula">
                        ROE = ROA + (ROA - Cost of Debt) × D/E
                    </div>

                    <p>Example: If ROA is 10% and cost of debt is 5%:</p>
                    <table>
                        <tr><th>D/E Ratio</th><th>ROE</th></tr>
                        <tr><td>0.0x (no debt)</td><td>10%</td></tr>
                        <tr><td>1.0x (equal debt/equity)</td><td>15%</td></tr>
                        <tr><td>2.0x</td><td>20%</td></tr>
                        <tr><td>3.0x</td><td>25%</td></tr>
                    </table>

                    <p>But if ROA falls to 3% (below cost of debt), higher leverage destroys equity value rapidly.</p>

                    <h2>Choosing the Right Mix</h2>

                    <h3>Factors Favoring More Debt</h3>
                    <ul>
                        <li>Stable, predictable cash flows</li>
                        <li>Tangible assets (good collateral)</li>
                        <li>High marginal tax rate</li>
                        <li>Mature industry with limited growth needs</li>
                        <li>Low business risk</li>
                    </ul>

                    <h3>Factors Favoring More Equity</h3>
                    <ul>
                        <li>Volatile or cyclical revenues</li>
                        <li>Intangible assets (poor collateral)</li>
                        <li>High growth requiring reinvestment</li>
                        <li>R&D intensity</li>
                        <li>Need for strategic flexibility</li>
                    </ul>

                    <h2>Industry Benchmarks</h2>
                    <table>
                        <tr><th>Industry</th><th>Typical D/E</th><th>Rationale</th></tr>
                        <tr><td>Utilities</td><td>1.0-2.0x</td><td>Stable cash flows, tangible assets</td></tr>
                        <tr><td>Real Estate</td><td>1.5-3.0x</td><td>Hard asset collateral</td></tr>
                        <tr><td>Consumer Staples</td><td>0.5-1.0x</td><td>Stable but needs flexibility</td></tr>
                        <tr><td>Technology</td><td>0.0-0.3x</td><td>High growth, intangible assets</td></tr>
                        <tr><td>Biotech</td><td>0.0-0.2x</td><td>Volatile, R&D intensive</td></tr>
                    </table>

                    <blockquote>
                        <strong>Pro Tip:</strong> The "right" capital structure depends on strategy. A company focused on aggressive acquisition may want dry powder (low leverage). A mature cash cow can optimize for tax efficiency (higher leverage).
                    </blockquote>
                `,

                'private-ma': `
                    <h2>Introduction</h2>
                    <p>Private company M&A differs significantly from public transactions. Without market-based pricing, extensive disclosure, or standardized processes, private deals require different skills in valuation, due diligence, and negotiation. This guide covers the essential considerations for acquiring private businesses.</p>

                    <div class="nrt-guide-concept">
                        <h4>Key Concept</h4>
                        <p>In private M&A, information asymmetry is the central challenge. Sellers know more than buyers about the business, its risks, and its true earnings power. Robust diligence and deal protection are essential.</p>
                    </div>

                    <h2>Private vs. Public Deals</h2>
                    <table>
                        <tr><th>Aspect</th><th>Private</th><th>Public</th></tr>
                        <tr><td>Pricing reference</td><td>None (must derive value)</td><td>Market price sets floor</td></tr>
                        <tr><td>Information</td><td>Limited, must verify</td><td>Public filings available</td></tr>
                        <tr><td>Negotiation</td><td>Highly bespoke</td><td>More standardized terms</td></tr>
                        <tr><td>Due diligence</td><td>Extensive, confirmatory</td><td>More limited</td></tr>
                        <tr><td>Reps & warranties</td><td>Fulsome, heavily negotiated</td><td>Limited in public deals</td></tr>
                        <tr><td>Indemnification</td><td>Common, with caps and baskets</td><td>Rare in public deals</td></tr>
                        <tr><td>Earnouts</td><td>Frequently used</td><td>Uncommon</td></tr>
                    </table>

                    <h2>Private Company Valuation</h2>

                    <h3>Multiple Approaches Required</h3>
                    <ul>
                        <li><strong>Comparable companies:</strong> Apply discounts for size, liquidity, risk</li>
                        <li><strong>Precedent transactions:</strong> Private company deals are best comps</li>
                        <li><strong>DCF:</strong> More assumptions needed given limited history</li>
                        <li><strong>LBO analysis:</strong> What would a PE buyer pay?</li>
                    </ul>

                    <h3>Common Discounts/Premiums</h3>
                    <table>
                        <tr><th>Adjustment</th><th>Range</th><th>Rationale</th></tr>
                        <tr><td>Lack of marketability (DLOM)</td><td>15-35%</td><td>Can't sell shares publicly</td></tr>
                        <tr><td>Size discount</td><td>10-25%</td><td>Smaller companies are riskier</td></tr>
                        <tr><td>Control premium</td><td>20-40%</td><td>100% ownership adds value</td></tr>
                        <tr><td>Key person discount</td><td>5-20%</td><td>Dependence on founder</td></tr>
                    </table>

                    <h2>Due Diligence Intensity</h2>
                    <p>Private deals require deeper investigation:</p>

                    <h3>Financial Diligence</h3>
                    <ul>
                        <li>Quality of earnings (often no audits)</li>
                        <li>Owner benefits and perks normalization</li>
                        <li>Related party transactions scrutiny</li>
                        <li>Working capital true-up analysis</li>
                        <li>Off-balance sheet items</li>
                    </ul>

                    <h3>Operational Diligence</h3>
                    <ul>
                        <li>Customer concentration verification</li>
                        <li>Key employee retention planning</li>
                        <li>Supplier/vendor relationships</li>
                        <li>Systems and technology assessment</li>
                    </ul>

                    <h3>Legal Diligence</h3>
                    <ul>
                        <li>Corporate records (often incomplete)</li>
                        <li>Contract assignment requirements</li>
                        <li>Intellectual property ownership</li>
                        <li>Employment practices compliance</li>
                    </ul>

                    <h2>Deal Protection Mechanisms</h2>

                    <h3>Representations & Warranties</h3>
                    <p>Seller's statements about the business. Key areas:</p>
                    <ul>
                        <li>Financial statements accuracy</li>
                        <li>No undisclosed liabilities</li>
                        <li>Title to assets</li>
                        <li>Customer/supplier relationships</li>
                        <li>Employment matters</li>
                        <li>Litigation and compliance</li>
                    </ul>

                    <h3>Indemnification</h3>
                    <p>Seller's obligation to reimburse buyer for rep breaches:</p>
                    <ul>
                        <li><strong>Cap:</strong> Maximum exposure (10-20% of deal value typical)</li>
                        <li><strong>Basket:</strong> Threshold before claims (0.5-1% typical)</li>
                        <li><strong>Survival period:</strong> How long reps last (12-24 months for general; 3-6 years for tax/environmental)</li>
                    </ul>

                    <h3>Rep & Warranty Insurance (RWI)</h3>
                    <p>Insurance policy covering indemnification claims:</p>
                    <ul>
                        <li>Premium: 2-4% of policy limit</li>
                        <li>Retention: 0.5-1% of deal value</li>
                        <li>Allows "cleaner" seller exit</li>
                        <li>Standard in PE transactions</li>
                    </ul>

                    <h2>Purchase Price Mechanics</h2>
                    <ul>
                        <li><strong>Locked box:</strong> Price fixed at historical date; no post-close adjustment</li>
                        <li><strong>Completion accounts:</strong> Price adjusted based on closing balance sheet</li>
                        <li><strong>Working capital adjustment:</strong> True-up to normalized WC level</li>
                        <li><strong>Earnouts:</strong> Deferred payments tied to performance</li>
                    </ul>

                    <blockquote>
                        <strong>Pro Tip:</strong> The real negotiation in private deals happens in the purchase agreement, not just the price. Spend as much time on reps, indemnities, and definitions as you do on valuation.
                    </blockquote>
                `,

                'value-creation': `
                    <h2>Introduction</h2>
                    <p>Value creation is the core of private equity. Generating returns of 2-3x+ requires more than financial engineering—it demands operational improvement, strategic repositioning, and disciplined capital allocation. This guide covers the frameworks and levers that drive PE returns.</p>

                    <div class="nrt-guide-concept">
                        <h4>Key Concept</h4>
                        <p>Value creation happens at the intersection of revenue growth, margin expansion, and multiple expansion. The best PE firms systematically work all three levers, not just one.</p>
                    </div>

                    <h2>The Value Creation Framework</h2>
                    <div class="nrt-guide-formula">
                        Equity Value at Exit = EBITDA<sub>exit</sub> × Multiple<sub>exit</sub> - Net Debt<sub>exit</sub>
                    </div>

                    <h3>Value Drivers</h3>
                    <ol>
                        <li><strong>EBITDA Growth:</strong> Revenue growth and margin improvement</li>
                        <li><strong>Multiple Expansion:</strong> Exit at higher valuation than entry</li>
                        <li><strong>Debt Paydown:</strong> Free cash flow reduces debt, increasing equity</li>
                    </ol>

                    <h2>Revenue Growth Initiatives</h2>

                    <h3>Organic Growth</h3>
                    <ul>
                        <li><strong>Sales force effectiveness:</strong> Training, incentives, territory optimization</li>
                        <li><strong>Pricing optimization:</strong> Value-based pricing, dynamic pricing, upselling</li>
                        <li><strong>Product expansion:</strong> New SKUs, line extensions, bundling</li>
                        <li><strong>Customer success:</strong> Reduce churn, increase wallet share</li>
                        <li><strong>Geographic expansion:</strong> New markets, international growth</li>
                        <li><strong>Channel development:</strong> E-commerce, partnerships, distribution</li>
                    </ul>

                    <h3>Inorganic Growth (Add-ons)</h3>
                    <ul>
                        <li><strong>Tuck-in acquisitions:</strong> Smaller deals that add capabilities or customers</li>
                        <li><strong>Buy-and-build:</strong> Platform for industry consolidation</li>
                        <li><strong>Strategic acquisitions:</strong> New products, technologies, or markets</li>
                    </ul>

                    <h2>Margin Improvement Levers</h2>

                    <h3>Cost Structure</h3>
                    <table>
                        <tr><th>Initiative</th><th>Typical Impact</th><th>Timeframe</th></tr>
                        <tr><td>Procurement savings</td><td>2-5% of COGS</td><td>6-12 months</td></tr>
                        <tr><td>Headcount optimization</td><td>5-15% of SG&A</td><td>3-6 months</td></tr>
                        <tr><td>Facility rationalization</td><td>10-30% of real estate</td><td>12-24 months</td></tr>
                        <tr><td>IT modernization</td><td>Variable</td><td>12-36 months</td></tr>
                        <tr><td>Outsourcing/offshoring</td><td>20-40% of function cost</td><td>6-18 months</td></tr>
                    </table>

                    <h3>Operational Excellence</h3>
                    <ul>
                        <li><strong>Lean manufacturing:</strong> Reduce waste, improve throughput</li>
                        <li><strong>Working capital optimization:</strong> DSO, DIO, DPO management</li>
                        <li><strong>SKU rationalization:</strong> Focus on profitable products</li>
                        <li><strong>Automation:</strong> Technology investment for efficiency</li>
                    </ul>

                    <h2>Multiple Expansion Strategies</h2>

                    <h3>Factors That Drive Higher Multiples</h3>
                    <ul>
                        <li><strong>Scale:</strong> Larger companies trade at premiums</li>
                        <li><strong>Growth profile:</strong> Faster growth commands higher multiples</li>
                        <li><strong>Quality of earnings:</strong> Recurring, predictable revenue</li>
                        <li><strong>End market:</strong> Attractive sectors (tech, healthcare, services)</li>
                        <li><strong>Strategic value:</strong> Potential synergies with buyers</li>
                        <li><strong>Management team:</strong> Professional, scalable organization</li>
                    </ul>

                    <h3>Repositioning Strategies</h3>
                    <ul>
                        <li>Shift from product to services revenue</li>
                        <li>Build recurring/subscription revenue stream</li>
                        <li>Diversify customer base (reduce concentration)</li>
                        <li>Develop proprietary technology or IP</li>
                        <li>Transform from regional to national/global footprint</li>
                    </ul>

                    <h2>The 100-Day Plan</h2>
                    <p>Critical early period post-close:</p>
                    <ol>
                        <li><strong>Days 1-30:</strong> Stabilize operations, assess team, quick wins</li>
                        <li><strong>Days 31-60:</strong> Deep diagnostic, prioritize initiatives, set targets</li>
                        <li><strong>Days 61-100:</strong> Launch key initiatives, establish cadence, early milestones</li>
                    </ol>

                    <h2>Value Attribution Analysis</h2>
                    <p>At exit, attribute returns to each lever:</p>
                    <table>
                        <tr><th>Source</th><th>Contribution</th></tr>
                        <tr><td>EBITDA growth</td><td>40-60%</td></tr>
                        <tr><td>Multiple expansion</td><td>15-30%</td></tr>
                        <tr><td>Debt paydown</td><td>15-25%</td></tr>
                        <tr><td>Leverage (initial)</td><td>10-20%</td></tr>
                    </table>

                    <blockquote>
                        <strong>Pro Tip:</strong> The best value creation happens before the deal closes—in the due diligence and 100-day planning phases. If you don't have a clear value creation thesis at signing, you're gambling.
                    </blockquote>
                `,

                'exit-strategies': `
                    <h2>Introduction</h2>
                    <p>The exit is when PE value is realized. Choosing the right exit path, timing, and preparation approach can meaningfully impact returns. This guide covers the major exit options and strategies for maximizing value at liquidity.</p>

                    <div class="nrt-guide-concept">
                        <h4>Key Concept</h4>
                        <p>Exit planning should begin at acquisition—not years later. The best exits are built deliberately, with the company positioned for the most attractive exit route.</p>
                    </div>

                    <h2>Primary Exit Routes</h2>

                    <h3>1. Strategic Sale (M&A)</h3>
                    <p>Sale to a corporate buyer (industry player).</p>
                    <table>
                        <tr><th>Pros</th><th>Cons</th></tr>
                        <tr><td>Highest multiples (synergy value)</td><td>Fewer potential buyers</td></tr>
                        <tr><td>Clean exit (100% liquidity)</td><td>Longer process, regulatory risk</td></tr>
                        <tr><td>Management may have roles</td><td>Cultural integration challenges</td></tr>
                        <tr><td>Simpler capital structure</td><td>Strategic fit required</td></tr>
                    </table>

                    <h3>2. Sponsor-to-Sponsor (Secondary Buyout)</h3>
                    <p>Sale to another PE firm.</p>
                    <table>
                        <tr><th>Pros</th><th>Cons</th></tr>
                        <tr><td>Large buyer universe</td><td>Limited synergy premium</td></tr>
                        <tr><td>Familiar process (both sides)</td><td>"Passed around" perception</td></tr>
                        <tr><td>Management rollover common</td><td>Lower multiples typically</td></tr>
                        <tr><td>Faster execution typically</td><td>Value creation thesis must remain</td></tr>
                    </table>

                    <h3>3. Initial Public Offering (IPO)</h3>
                    <p>List shares on public exchange.</p>
                    <table>
                        <tr><th>Pros</th><th>Cons</th></tr>
                        <tr><td>Access to public market valuations</td><td>Partial exit only (lockup)</td></tr>
                        <tr><td>Creates acquisition currency</td><td>Ongoing public company costs</td></tr>
                        <tr><td>Brand visibility</td><td>Market window dependency</td></tr>
                        <tr><td>Staged liquidity</td><td>6-12+ month process</td></tr>
                    </table>

                    <h3>4. Dividend Recapitalization</h3>
                    <p>Not a true exit—refinance to pay special dividend.</p>
                    <ul>
                        <li>Partial liquidity while retaining ownership</li>
                        <li>De-risks investment (return capital to LPs)</li>
                        <li>Increases leverage (adds risk to company)</li>
                        <li>Common mid-holding period</li>
                    </ul>

                    <h2>Exit Process: Strategic Sale</h2>

                    <h3>Phase 1: Preparation (8-12 weeks)</h3>
                    <ul>
                        <li>Engage investment bank</li>
                        <li>Vendor due diligence (VDD) reports</li>
                        <li>Confidential Information Memorandum (CIM)</li>
                        <li>Management presentation preparation</li>
                        <li>Data room population</li>
                    </ul>

                    <h3>Phase 2: Marketing (6-10 weeks)</h3>
                    <ul>
                        <li>Contact potential buyers (wide or targeted)</li>
                        <li>Sign NDAs, distribute CIM</li>
                        <li>First-round bids (non-binding indications)</li>
                        <li>Management presentations</li>
                        <li>Select final round participants</li>
                    </ul>

                    <h3>Phase 3: Final Round (6-8 weeks)</h3>
                    <ul>
                        <li>Detailed due diligence access</li>
                        <li>Final binding bids with markup of purchase agreement</li>
                        <li>Negotiate with lead bidder(s)</li>
                        <li>Definitive agreement signing</li>
                    </ul>

                    <h3>Phase 4: Closing (4-12 weeks)</h3>
                    <ul>
                        <li>Regulatory approvals (antitrust, CFIUS, etc.)</li>
                        <li>Financing conditions satisfied</li>
                        <li>Closing deliverables</li>
                        <li>Funds transfer</li>
                    </ul>

                    <h2>Exit Preparation Strategies</h2>

                    <h3>Position for Multiple Expansion</h3>
                    <ul>
                        <li>Build recurring revenue streams</li>
                        <li>Diversify customer base</li>
                        <li>Professionalize management team</li>
                        <li>Document systems and processes</li>
                        <li>Clean up legal/environmental issues</li>
                    </ul>

                    <h3>Tell the Equity Story</h3>
                    <ul>
                        <li>Clear growth trajectory and drivers</li>
                        <li>Runway for continued value creation</li>
                        <li>Synergy potential for strategic buyers</li>
                        <li>Quality of earnings narrative</li>
                    </ul>

                    <h2>Exit Timing Considerations</h2>
                    <ul>
                        <li><strong>Fund lifecycle:</strong> Need to return capital to LPs (typically 3-7 year hold)</li>
                        <li><strong>Market conditions:</strong> M&A activity, credit availability, public market sentiment</li>
                        <li><strong>Company trajectory:</strong> Exiting while growth story intact vs. waiting for maturity</li>
                        <li><strong>Value creation completion:</strong> Have all initiatives been executed?</li>
                    </ul>

                    <blockquote>
                        <strong>Pro Tip:</strong> Run a "dual track" process (M&A and IPO prep simultaneously) when both options are viable. It creates competitive tension and preserves optionality.
                    </blockquote>
                `,

                'fund-structures': `
                    <h2>Introduction</h2>
                    <p>Understanding private equity fund structures, economics, and operations is essential for anyone working in or with PE. This guide covers how funds are organized, how capital flows, and how returns are shared between managers (GPs) and investors (LPs).</p>

                    <div class="nrt-guide-concept">
                        <h4>Key Concept</h4>
                        <p>PE funds are designed to align interests between GPs and LPs—managers invest alongside investors, get paid primarily on performance, and have long lockup periods that encourage patient capital deployment.</p>
                    </div>

                    <h2>Fund Structure Overview</h2>

                    <h3>Key Parties</h3>
                    <table>
                        <tr><th>Party</th><th>Role</th><th>Economics</th></tr>
                        <tr><td>Limited Partners (LPs)</td><td>Passive investors (pension funds, endowments, etc.)</td><td>~80% of profits</td></tr>
                        <tr><td>General Partner (GP)</td><td>Fund manager, makes investment decisions</td><td>~20% of profits + fees</td></tr>
                        <tr><td>Management Company</td><td>Employs deal professionals</td><td>Management fee</td></tr>
                        <tr><td>Portfolio Companies</td><td>Underlying investments</td><td>Value creation targets</td></tr>
                    </table>

                    <h3>Legal Structure</h3>
                    <p>Typical PE fund is structured as:</p>
                    <ul>
                        <li><strong>Delaware LP:</strong> Domestic investors</li>
                        <li><strong>Cayman LP or Offshore feeder:</strong> Non-U.S. investors, tax-exempt investors</li>
                        <li><strong>GP entity:</strong> Usually Delaware LLC, controlled by sponsors</li>
                        <li><strong>Management Company:</strong> Employs team, collects fees</li>
                    </ul>

                    <h2>Fund Economics</h2>

                    <h3>Management Fee</h3>
                    <p>Annual fee to cover operations:</p>
                    <ul>
                        <li><strong>Investment period:</strong> 1.5-2.0% of committed capital</li>
                        <li><strong>Post-investment period:</strong> 1.5-2.0% of invested capital (lower base)</li>
                        <li><strong>Fee offsets:</strong> Transaction/monitoring fees often offset management fee</li>
                    </ul>

                    <h3>Carried Interest ("Carry")</h3>
                    <p>Performance fee—GP's share of profits:</p>
                    <ul>
                        <li><strong>Standard rate:</strong> 20% of profits above preferred return</li>
                        <li><strong>Preferred return (hurdle):</strong> LPs receive 8% return before carry kicks in</li>
                        <li><strong>Catch-up:</strong> After hurdle, GP gets 100% until "caught up" to 20% share</li>
                        <li><strong>Clawback:</strong> GP returns excess carry if later losses occur</li>
                    </ul>

                    <h3>Distribution Waterfall Example</h3>
                    <table>
                        <tr><th>Tier</th><th>Description</th><th>Split</th></tr>
                        <tr><td>1. Return of Capital</td><td>LPs get back contributed capital</td><td>100% to LP</td></tr>
                        <tr><td>2. Preferred Return</td><td>LPs earn 8% compounded</td><td>100% to LP</td></tr>
                        <tr><td>3. GP Catch-Up</td><td>GP catches up to 20% of total</td><td>100% to GP</td></tr>
                        <tr><td>4. 80/20 Split</td><td>Remaining profits shared</td><td>80% LP / 20% GP</td></tr>
                    </table>

                    <h2>Fund Lifecycle</h2>

                    <h3>Fundraising (6-18 months)</h3>
                    <ul>
                        <li>GP markets to prospective LPs</li>
                        <li>Preliminary closings as commitments come in</li>
                        <li>Final close (hard cap)</li>
                        <li>LP Advisory Committee (LPAC) formed</li>
                    </ul>

                    <h3>Investment Period (Years 1-5)</h3>
                    <ul>
                        <li>Capital called as deals are executed</li>
                        <li>Typically 15-20 investments made</li>
                        <li>Active portfolio management begins</li>
                        <li>Early exits may occur</li>
                    </ul>

                    <h3>Harvest Period (Years 5-10)</h3>
                    <ul>
                        <li>Focus shifts to exits</li>
                        <li>Distributions flow to LPs</li>
                        <li>Extensions possible (1-2 years)</li>
                        <li>Fund wind-down and final accounting</li>
                    </ul>

                    <h2>Key Fund Terms</h2>

                    <h3>Capital Commitments</h3>
                    <ul>
                        <li><strong>Commitment:</strong> Amount LP agrees to invest</li>
                        <li><strong>Capital call:</strong> GP draws down commitment as needed</li>
                        <li><strong>Unfunded commitment:</strong> Amount not yet called</li>
                        <li><strong>Recycling:</strong> Reinvesting proceeds within investment period</li>
                    </ul>

                    <h3>Key Provisions</h3>
                    <ul>
                        <li><strong>GP commitment:</strong> GP invests 1-5% of fund alongside LPs</li>
                        <li><strong>Key person clause:</strong> Investment period pauses if key people leave</li>
                        <li><strong>No-fault divorce:</strong> Supermajority can remove GP</li>
                        <li><strong>Most favored nation (MFN):</strong> Large LPs get best terms offered</li>
                    </ul>

                    <h2>Performance Metrics</h2>

                    <table>
                        <tr><th>Metric</th><th>Formula</th><th>Use</th></tr>
                        <tr><td>IRR</td><td>Rate that makes NPV of cash flows = 0</td><td>Time-weighted returns</td></tr>
                        <tr><td>MOIC/TVPI</td><td>(Distributions + NAV) / Paid-In</td><td>Total value multiple</td></tr>
                        <tr><td>DPI</td><td>Distributions / Paid-In</td><td>Realized returns only</td></tr>
                        <tr><td>RVPI</td><td>Residual Value / Paid-In</td><td>Unrealized returns</td></tr>
                    </table>

                    <blockquote>
                        <strong>Pro Tip:</strong> When evaluating GPs, look at DPI (realized returns), not just IRR or TVPI. Unrealized value can disappear—only distributions are real.
                    </blockquote>
                `,

                'project-finance': `
                    <h2>Introduction</h2>
                    <p>Project finance is a specialized financing technique used for large-scale infrastructure and industrial projects. Unlike corporate finance where the borrower's balance sheet backs the loan, project finance relies on the project's cash flows and assets as the primary source of repayment and security.</p>

                    <div class="nrt-guide-concept">
                        <h4>Key Concept</h4>
                        <p>Project finance is "non-recourse" or "limited recourse"—lenders look to the project's cash flows, not the sponsor's balance sheet, for repayment. This enables sponsors to undertake massive projects without putting their entire company at risk.</p>
                    </div>

                    <h2>Typical Project Finance Sectors</h2>
                    <table>
                        <tr><th>Sector</th><th>Examples</th><th>Typical Tenor</th></tr>
                        <tr><td>Power Generation</td><td>Gas plants, renewables, nuclear</td><td>15-25 years</td></tr>
                        <tr><td>Infrastructure</td><td>Toll roads, airports, ports</td><td>20-30 years</td></tr>
                        <tr><td>Oil & Gas</td><td>Pipelines, LNG terminals, refineries</td><td>10-20 years</td></tr>
                        <tr><td>Mining</td><td>Large mining operations</td><td>10-15 years</td></tr>
                        <tr><td>Telecom</td><td>Fiber networks, data centers</td><td>10-15 years</td></tr>
                    </table>

                    <h2>Project Finance Structure</h2>

                    <h3>Key Parties</h3>
                    <ul>
                        <li><strong>Sponsors:</strong> Equity investors who develop and own the project</li>
                        <li><strong>Lenders:</strong> Banks, ECAs, DFIs, institutional investors</li>
                        <li><strong>EPC Contractor:</strong> Builds the project (Engineering, Procurement, Construction)</li>
                        <li><strong>O&M Operator:</strong> Operates and maintains the asset</li>
                        <li><strong>Offtaker:</strong> Purchases project output (power, tolls, etc.)</li>
                        <li><strong>Fuel/Input Supplier:</strong> Provides feedstock or fuel</li>
                    </ul>

                    <h3>Typical Capital Structure</h3>
                    <table>
                        <tr><th>Source</th><th>Typical %</th><th>Characteristics</th></tr>
                        <tr><td>Senior Debt</td><td>60-80%</td><td>First priority, longest tenor</td></tr>
                        <tr><td>Mezzanine/Sub Debt</td><td>5-15%</td><td>Higher rate, subordinated</td></tr>
                        <tr><td>Sponsor Equity</td><td>20-40%</td><td>First loss, highest return</td></tr>
                    </table>

                    <h2>Project Finance Model Structure</h2>

                    <h3>Key Model Tabs</h3>
                    <ol>
                        <li><strong>Assumptions:</strong> All inputs in one place (blue font)</li>
                        <li><strong>Construction:</strong> CapEx drawdown, IDC calculation</li>
                        <li><strong>Operations:</strong> Revenue, OpEx, working capital</li>
                        <li><strong>Debt:</strong> Drawdown, repayment, DSRA</li>
                        <li><strong>Tax & Depreciation:</strong> Tax shield calculations</li>
                        <li><strong>Cash Flow Waterfall:</strong> Priority of payments</li>
                        <li><strong>Returns:</strong> IRR, NPV, payback for each party</li>
                    </ol>

                    <h3>The Cash Flow Waterfall</h3>
                    <p>Cash flows are distributed in strict priority order:</p>
                    <ol>
                        <li>Operating expenses and taxes</li>
                        <li>Senior debt service (interest + principal)</li>
                        <li>Debt service reserve account (DSRA) funding</li>
                        <li>Maintenance reserve funding</li>
                        <li>Subordinated debt service</li>
                        <li>Distributions to equity (if cash sweep allows)</li>
                    </ol>

                    <h2>Key Credit Metrics</h2>
                    <table>
                        <tr><th>Metric</th><th>Formula</th><th>Typical Threshold</th></tr>
                        <tr><td>DSCR (Debt Service Coverage Ratio)</td><td>CFADS / Debt Service</td><td>> 1.20-1.40x</td></tr>
                        <tr><td>LLCR (Loan Life Coverage Ratio)</td><td>NPV(CFADS) / Outstanding Debt</td><td>> 1.30-1.50x</td></tr>
                        <tr><td>PLCR (Project Life Coverage Ratio)</td><td>NPV(CFADS to end) / Outstanding Debt</td><td>> 1.40-1.60x</td></tr>
                    </table>

                    <div class="nrt-guide-formula">
                        CFADS = EBITDA - Tax - CapEx - Change in Working Capital
                    </div>

                    <h2>Risk Allocation Matrix</h2>
                    <table>
                        <tr><th>Risk</th><th>Mitigant</th><th>Party Bearing Risk</th></tr>
                        <tr><td>Construction delay</td><td>Liquidated damages, completion guarantee</td><td>EPC Contractor</td></tr>
                        <tr><td>Cost overrun</td><td>Fixed price EPC, contingency</td><td>Sponsor/Contractor</td></tr>
                        <tr><td>Technology risk</td><td>Proven technology, warranties</td><td>Contractor/OEM</td></tr>
                        <tr><td>Offtake risk</td><td>Long-term PPA/contract</td><td>Offtaker</td></tr>
                        <tr><td>Fuel/input price</td><td>Pass-through or hedging</td><td>Offtaker/Hedges</td></tr>
                        <tr><td>Political risk</td><td>Political risk insurance</td><td>Insurer/DFI</td></tr>
                    </table>

                    <blockquote>
                        <strong>Pro Tip:</strong> In project finance, the model IS the deal. Lenders will scrutinize every assumption. Build in clear sensitivities showing what happens when key variables move 10-20%.
                    </blockquote>
                `,

                'irr-fundamentals': `
                    <h2>Introduction</h2>
                    <p>The Internal Rate of Return (IRR) is the most widely used metric in private equity and investment analysis. Despite its ubiquity, IRR is frequently misunderstood and can be misleading if not properly interpreted. This guide decodes IRR mechanics, limitations, and best practices.</p>

                    <div class="nrt-guide-concept">
                        <h4>Key Concept</h4>
                        <p>IRR is the discount rate that makes the Net Present Value (NPV) of all cash flows equal to zero. It represents the annualized effective return rate, accounting for the time value of money.</p>
                    </div>

                    <h2>The IRR Formula</h2>
                    <div class="nrt-guide-formula">
                        NPV = Σ CF<sub>t</sub> / (1 + IRR)<sup>t</sup> = 0
                    </div>

                    <p>Where:</p>
                    <ul>
                        <li><strong>CF<sub>t</sub></strong> = Cash flow at time t (negative for investments, positive for returns)</li>
                        <li><strong>IRR</strong> = The rate we're solving for</li>
                        <li><strong>t</strong> = Time period</li>
                    </ul>

                    <h2>IRR vs. Other Return Metrics</h2>
                    <table>
                        <tr><th>Metric</th><th>What It Measures</th><th>Strengths</th><th>Weaknesses</th></tr>
                        <tr><td>IRR</td><td>Time-weighted return rate</td><td>Accounts for timing</td><td>Ignores scale, reinvestment assumption</td></tr>
                        <tr><td>MOIC</td><td>Total value multiple</td><td>Simple, intuitive</td><td>Ignores timing</td></tr>
                        <tr><td>NPV</td><td>Absolute value created</td><td>Accounts for scale and timing</td><td>Requires hurdle rate assumption</td></tr>
                        <tr><td>PME</td><td>vs. public market alternative</td><td>Relative performance</td><td>Index selection matters</td></tr>
                    </table>

                    <h2>IRR Mechanics: Worked Examples</h2>

                    <h3>Example 1: Simple Investment</h3>
                    <p>Invest $100 today, receive $150 in 3 years:</p>
                    <div class="nrt-guide-formula">
                        IRR = (150/100)<sup>1/3</sup> - 1 = 14.5%
                    </div>

                    <h3>Example 2: Multiple Cash Flows</h3>
                    <table>
                        <tr><th>Year</th><th>Cash Flow</th></tr>
                        <tr><td>0</td><td>-$100</td></tr>
                        <tr><td>1</td><td>$20</td></tr>
                        <tr><td>2</td><td>$30</td></tr>
                        <tr><td>3</td><td>$80</td></tr>
                    </table>
                    <p>IRR = 15.1% (solved iteratively)</p>

                    <h2>IRR Pitfalls and Limitations</h2>

                    <h3>1. Reinvestment Assumption</h3>
                    <p>IRR assumes interim cash flows can be reinvested at the IRR rate itself—often unrealistic for high IRRs. A 50% IRR assumes you can reinvest distributions at 50%.</p>

                    <h3>2. Timing Manipulation</h3>
                    <p>IRR can be artificially inflated by:</p>
                    <ul>
                        <li><strong>Subscription lines:</strong> Delay capital calls to boost early returns</li>
                        <li><strong>Quick flips:</strong> Short holds with modest gains show high IRRs</li>
                        <li><strong>Dividend recaps:</strong> Early distributions boost IRR even if MOIC unchanged</li>
                    </ul>

                    <h3>3. Scale Blindness</h3>
                    <p>A $1M investment returning 100% IRR creates less value than a $100M investment at 20% IRR.</p>

                    <h3>4. Multiple IRRs</h3>
                    <p>Projects with alternating cash flow signs can have multiple mathematical IRRs, making interpretation difficult.</p>

                    <h2>Modified IRR (MIRR)</h2>
                    <p>MIRR addresses the reinvestment problem by using explicit reinvestment and financing rates:</p>
                    <div class="nrt-guide-formula">
                        MIRR = (FV of positives / PV of negatives)<sup>1/n</sup> - 1
                    </div>
                    <p>Typically assumes reinvestment at cost of capital (8-10%) rather than the IRR itself.</p>

                    <h2>Gross vs. Net IRR</h2>
                    <table>
                        <tr><th>Type</th><th>Definition</th><th>Use Case</th></tr>
                        <tr><td>Gross IRR</td><td>Returns before fees and carry</td><td>Manager skill assessment</td></tr>
                        <tr><td>Net IRR</td><td>Returns after all fees to LP</td><td>LP actual returns</td></tr>
                    </table>
                    <p>Typical gap: 300-500 bps between gross and net IRR.</p>

                    <h2>IRR in Different Contexts</h2>

                    <h3>Private Equity</h3>
                    <ul>
                        <li>Target: 20-25%+ net IRR</li>
                        <li>Typically 3-7 year holds</li>
                        <li>Watch for subscription line impact</li>
                    </ul>

                    <h3>Real Estate</h3>
                    <ul>
                        <li>Core: 6-10% IRR</li>
                        <li>Value-add: 12-18% IRR</li>
                        <li>Opportunistic: 18%+ IRR</li>
                    </ul>

                    <h3>Venture Capital</h3>
                    <ul>
                        <li>Target: 25-35%+ gross IRR</li>
                        <li>Longer holds (7-10 years)</li>
                        <li>Power law returns distribution</li>
                    </ul>

                    <blockquote>
                        <strong>Pro Tip:</strong> Always look at IRR alongside MOIC and hold period. A 50% IRR over 1 year with 1.5x MOIC is less impressive than 25% IRR over 5 years with 3.0x MOIC. Context matters.
                    </blockquote>
                `,

                'buyout-modeling': `
                    <h2>Introduction</h2>
                    <p>A buyout model (LBO model) is the cornerstone of private equity analysis. It projects how a leveraged acquisition creates value through operational improvements, debt paydown, and multiple expansion. This guide walks through building a complete buyout model step by step.</p>

                    <div class="nrt-guide-concept">
                        <h4>Key Concept</h4>
                        <p>The buyout model answers the fundamental PE question: "What can we pay for this business and still achieve our target returns?" It works backwards from return requirements to determine maximum entry price.</p>
                    </div>

                    <h2>Model Architecture</h2>

                    <h3>Core Tabs Structure</h3>
                    <ol>
                        <li><strong>Assumptions:</strong> Entry multiple, leverage, exit assumptions</li>
                        <li><strong>Sources & Uses:</strong> How the deal is funded</li>
                        <li><strong>Operating Model:</strong> Income statement projections</li>
                        <li><strong>Debt Schedule:</strong> Each tranche with its terms</li>
                        <li><strong>Cash Flow:</strong> Free cash flow available for debt paydown</li>
                        <li><strong>Returns Analysis:</strong> IRR, MOIC at various exit scenarios</li>
                    </ol>

                    <h2>Step 1: Sources & Uses</h2>
                    <table>
                        <tr><th>Uses of Funds</th><th>Sources of Funds</th></tr>
                        <tr><td>Purchase equity value</td><td>Revolving credit facility</td></tr>
                        <tr><td>Refinance existing debt</td><td>Term Loan A</td></tr>
                        <tr><td>Transaction fees (M&A, legal)</td><td>Term Loan B</td></tr>
                        <tr><td>Financing fees</td><td>Senior notes/bonds</td></tr>
                        <tr><td>Cash to balance sheet</td><td>Mezzanine/subordinated</td></tr>
                        <tr><td></td><td>Sponsor equity</td></tr>
                        <tr><td></td><td>Management rollover</td></tr>
                    </table>

                    <h2>Step 2: Operating Model</h2>

                    <h3>Revenue Build</h3>
                    <ul>
                        <li>Historical baseline (LTM revenue)</li>
                        <li>Growth assumptions by year</li>
                        <li>Consider organic vs. inorganic (add-ons)</li>
                    </ul>

                    <h3>Margin Assumptions</h3>
                    <table>
                        <tr><th>Metric</th><th>Entry</th><th>Exit (Year 5)</th><th>Driver</th></tr>
                        <tr><td>Gross Margin</td><td>35%</td><td>38%</td><td>Procurement savings</td></tr>
                        <tr><td>EBITDA Margin</td><td>15%</td><td>20%</td><td>OpEx efficiency</td></tr>
                    </table>

                    <h2>Step 3: Debt Schedule</h2>

                    <h3>Typical Debt Stack</h3>
                    <table>
                        <tr><th>Tranche</th><th>Amount</th><th>Rate</th><th>Amortization</th></tr>
                        <tr><td>Revolver ($50M)</td><td>Undrawn</td><td>SOFR + 400</td><td>None</td></tr>
                        <tr><td>Term Loan A</td><td>$150M</td><td>SOFR + 425</td><td>5% annually</td></tr>
                        <tr><td>Term Loan B</td><td>$200M</td><td>SOFR + 500</td><td>1% annually</td></tr>
                        <tr><td>Senior Notes</td><td>$100M</td><td>8.0% fixed</td><td>Bullet</td></tr>
                    </table>

                    <h3>Cash Sweep Mechanics</h3>
                    <p>Excess cash flow typically required to pay down debt:</p>
                    <ul>
                        <li><strong>75% sweep</strong> if leverage > 4.0x</li>
                        <li><strong>50% sweep</strong> if leverage 3.0-4.0x</li>
                        <li><strong>25% sweep</strong> if leverage < 3.0x</li>
                    </ul>

                    <h2>Step 4: Free Cash Flow</h2>
                    <div class="nrt-guide-formula">
                        FCF = EBITDA - Interest - Taxes - CapEx - ΔNWC - Mandatory Amort
                    </div>

                    <h3>Working Capital Considerations</h3>
                    <ul>
                        <li>Use days methodology (DSO, DIO, DPO)</li>
                        <li>Growing companies consume working capital</li>
                        <li>Seasonal businesses may need revolver draws</li>
                    </ul>

                    <h2>Step 5: Returns Analysis</h2>

                    <h3>Exit Value Calculation</h3>
                    <div class="nrt-guide-formula">
                        Exit EV = Exit Year EBITDA × Exit Multiple
                    </div>
                    <div class="nrt-guide-formula">
                        Exit Equity = Exit EV - Net Debt at Exit
                    </div>

                    <h3>Returns Metrics</h3>
                    <table>
                        <tr><th>Metric</th><th>Formula</th><th>Target</th></tr>
                        <tr><td>MOIC</td><td>Exit Equity / Entry Equity</td><td>2.0-3.0x</td></tr>
                        <tr><td>IRR</td><td>Annualized return rate</td><td>20-25%+</td></tr>
                        <tr><td>Cash-on-Cash</td><td>Total distributions / Invested</td><td>2.5x+</td></tr>
                    </table>

                    <h2>Sensitivity Analysis</h2>
                    <p>Build 2-way sensitivity tables on:</p>
                    <ul>
                        <li>Entry multiple vs. exit multiple</li>
                        <li>Revenue growth vs. margin expansion</li>
                        <li>Entry leverage vs. exit timing</li>
                    </ul>

                    <h2>Value Creation Bridge</h2>
                    <p>Decompose returns into sources:</p>
                    <table>
                        <tr><th>Source</th><th>Contribution</th></tr>
                        <tr><td>EBITDA growth</td><td>+$50M equity value</td></tr>
                        <tr><td>Multiple expansion</td><td>+$30M equity value</td></tr>
                        <tr><td>Debt paydown</td><td>+$40M equity value</td></tr>
                        <tr><td>Leverage effect</td><td>+$20M equity value</td></tr>
                    </table>

                    <blockquote>
                        <strong>Pro Tip:</strong> Build your model to "goal seek" entry price given target returns. In competitive auctions, you need to quickly determine your maximum bid based on your return thresholds.
                    </blockquote>
                `,

                'retention-bonus': `
                    <h2>Introduction</h2>
                    <p>Retention bonuses are cash payments offered to key employees during M&A transactions to incentivize them to stay through the transition. They're a critical deal tool—mishandling retention can destroy deal value by causing key talent to leave at the worst possible time.</p>

                    <div class="nrt-guide-concept">
                        <h4>Key Concept</h4>
                        <p>Retention is about more than money—it's about certainty, respect, and future opportunity. The best retention programs address all three, not just compensation.</p>
                    </div>

                    <h2>Why Retention Matters in M&A</h2>

                    <h3>Value at Risk</h3>
                    <ul>
                        <li>Key employees often hold critical customer relationships</li>
                        <li>Institutional knowledge can't be quickly replaced</li>
                        <li>Departures signal instability to customers and remaining staff</li>
                        <li>Competitors actively recruit during transitions</li>
                    </ul>

                    <h3>When Retention is Critical</h3>
                    <table>
                        <tr><th>Situation</th><th>Retention Priority</th></tr>
                        <tr><td>Founder-led businesses</td><td>Critical—customer relationships are personal</td></tr>
                        <tr><td>Professional services firms</td><td>Critical—people ARE the asset</td></tr>
                        <tr><td>Technology companies</td><td>High—key developers are hard to replace</td></tr>
                        <tr><td>Manufacturing</td><td>Moderate—knowledge can be documented</td></tr>
                    </table>

                    <h2>Types of Retention Arrangements</h2>

                    <h3>1. Cash Retention Bonus</h3>
                    <p>Simple cash payment for staying through a defined period.</p>
                    <ul>
                        <li><strong>Typical size:</strong> 25-100% of annual salary</li>
                        <li><strong>Vesting:</strong> Often 50% at close, 50% at 12-month anniversary</li>
                        <li><strong>Clawback:</strong> Must repay if voluntary departure before vesting</li>
                    </ul>

                    <h3>2. Stay Bonus with Milestones</h3>
                    <p>Payment tied to achieving integration milestones, not just time.</p>
                    <ul>
                        <li>Complete systems migration</li>
                        <li>Retain key customer contracts</li>
                        <li>Train successor/knowledge transfer</li>
                    </ul>

                    <h3>3. Transaction Bonus</h3>
                    <p>One-time payment at deal close (not tied to continued employment).</p>
                    <ul>
                        <li>Rewards employees for deal effort</li>
                        <li>Less retention power (no ongoing hook)</li>
                        <li>Often combined with retention bonus</li>
                    </ul>

                    <h3>4. Equity Rollover/New Grants</h3>
                    <p>Long-term alignment through equity participation in new structure.</p>
                    <ul>
                        <li><strong>Rollover:</strong> Convert existing equity to newco equity</li>
                        <li><strong>New grants:</strong> Options or profits interests in PE portfolio company</li>
                        <li>Vests over 3-5 years post-close</li>
                    </ul>

                    <h2>Structuring Retention Programs</h2>

                    <h3>Who to Include</h3>
                    <table>
                        <tr><th>Tier</th><th>Typical Group</th><th>Typical Pool</th></tr>
                        <tr><td>Tier 1</td><td>C-suite, key revenue drivers</td><td>1-2x base salary</td></tr>
                        <tr><td>Tier 2</td><td>Senior management, key technologists</td><td>50-100% base</td></tr>
                        <tr><td>Tier 3</td><td>Critical individual contributors</td><td>25-50% base</td></tr>
                    </table>

                    <h3>Vesting Schedules</h3>
                    <ul>
                        <li><strong>Cliff vesting:</strong> 100% at single date (12 or 18 months post-close)</li>
                        <li><strong>Ratable vesting:</strong> 25% quarterly over 1-2 years</li>
                        <li><strong>Front-loaded:</strong> 50% at close, 25% at 6 months, 25% at 12 months</li>
                    </ul>

                    <h2>Deal Mechanics</h2>

                    <h3>Who Pays?</h3>
                    <ul>
                        <li><strong>Seller pays:</strong> Deducted from purchase price proceeds</li>
                        <li><strong>Buyer pays:</strong> Post-close expense (increases effective purchase price)</li>
                        <li><strong>Negotiated:</strong> Often split or shared</li>
                    </ul>

                    <h3>Tax Considerations</h3>
                    <ul>
                        <li>Retention bonuses are ordinary income to recipients</li>
                        <li>Subject to payroll taxes and withholding</li>
                        <li>Deductible by the paying entity</li>
                        <li>Consider 280G "golden parachute" rules for change-in-control payments</li>
                    </ul>

                    <h2>Common Pitfalls</h2>
                    <ol>
                        <li><strong>Too narrow:</strong> Only focusing on top executives, ignoring critical operational staff</li>
                        <li><strong>Too broad:</strong> Spreading retention pool too thin, making awards meaningless</li>
                        <li><strong>Wrong triggers:</strong> Vesting too early (no retention) or too late (employees leave anyway)</li>
                        <li><strong>No communication:</strong> Uncertainty drives departures—communicate early</li>
                        <li><strong>Ignoring culture:</strong> Money won't retain people who hate the new direction</li>
                    </ol>

                    <blockquote>
                        <strong>Pro Tip:</strong> The best retention conversations happen before the deal is signed—not after. Identify critical talent during diligence and have specific plans for each person before close.
                    </blockquote>
                `,

                'blind-pool-funds': `
                    <h2>Introduction</h2>
                    <p>A blind pool fund is an investment vehicle where investors commit capital without knowing the specific investments that will be made. This is the standard structure for private equity, venture capital, and most alternative investment funds. Investors are betting on the GP's ability to find and execute attractive investments.</p>

                    <div class="nrt-guide-concept">
                        <h4>Key Concept</h4>
                        <p>In a blind pool, LPs delegate investment discretion to the GP. The GP-LP relationship is built on trust, track record, and carefully negotiated governance terms that protect LP interests without hampering GP flexibility.</p>
                    </div>

                    <h2>Blind Pool vs. Designated Pool</h2>
                    <table>
                        <tr><th>Feature</th><th>Blind Pool</th><th>Designated/Deal-by-Deal</th></tr>
                        <tr><td>Investment decisions</td><td>GP discretion</td><td>LP approval per deal</td></tr>
                        <tr><td>Capital commitment</td><td>Upfront to fund</td><td>Per-transaction</td></tr>
                        <tr><td>Diversification</td><td>Built-in (10-20+ deals)</td><td>LP controls</td></tr>
                        <tr><td>GP flexibility</td><td>High</td><td>Limited</td></tr>
                        <tr><td>Fee structure</td><td>2/20 on committed capital</td><td>Per-deal carry</td></tr>
                    </table>

                    <h2>How Blind Pools Work</h2>

                    <h3>Fund Formation</h3>
                    <ol>
                        <li><strong>PPM/LPA:</strong> GP prepares offering documents with strategy, terms, disclosures</li>
                        <li><strong>Fundraising:</strong> GP markets to prospective LPs over 6-18 months</li>
                        <li><strong>Closings:</strong> Interim closes as commitments come in; final close sets hard cap</li>
                        <li><strong>Investment period:</strong> Typically 5 years to deploy capital</li>
                    </ol>

                    <h3>Capital Call Mechanics</h3>
                    <ul>
                        <li>LPs commit to invest a specified amount</li>
                        <li>GP calls capital as investments are made (with 10-15 day notice)</li>
                        <li>LPs must fund calls or face penalties (default provisions)</li>
                        <li>Typical: 60-80% called over investment period</li>
                    </ul>

                    <h2>LP Protections in Blind Pools</h2>

                    <h3>Investment Restrictions</h3>
                    <ul>
                        <li><strong>Concentration limits:</strong> Max 10-25% of fund in single investment</li>
                        <li><strong>Industry restrictions:</strong> No tobacco, weapons, etc. (ESG screens)</li>
                        <li><strong>Geography limits:</strong> May restrict emerging market exposure</li>
                        <li><strong>Strategy guardrails:</strong> Must match stated strategy (no style drift)</li>
                    </ul>

                    <h3>Key Person Provisions</h3>
                    <p>If named key persons leave or reduce time commitment:</p>
                    <ul>
                        <li>Investment period may suspend</li>
                        <li>LPAC votes on whether to continue</li>
                        <li>LPs may have right to end fund</li>
                    </ul>

                    <h3>LP Advisory Committee (LPAC)</h3>
                    <p>Representative group of major LPs that:</p>
                    <ul>
                        <li>Approves conflicts of interest</li>
                        <li>Reviews valuation policies</li>
                        <li>Consents to fund extensions</li>
                        <li>Typically meets quarterly</li>
                    </ul>

                    <h2>Why Blind Pools Exist</h2>

                    <h3>Advantages for GPs</h3>
                    <ul>
                        <li>Flexibility to move quickly on opportunities</li>
                        <li>Stable capital base for 10+ year fund life</li>
                        <li>Management fees on committed capital</li>
                        <li>No need to "sell" each deal individually</li>
                    </ul>

                    <h3>Advantages for LPs</h3>
                    <ul>
                        <li>Access to top-tier managers who won't do deal-by-deal</li>
                        <li>Built-in diversification</li>
                        <li>Lower transaction costs vs. evaluating each deal</li>
                        <li>GP has "skin in the game" across whole portfolio</li>
                    </ul>

                    <h2>Evaluating Blind Pool Opportunities</h2>

                    <h3>Due Diligence Focus Areas</h3>
                    <ol>
                        <li><strong>Track record:</strong> Prior fund performance (DPI, not just IRR)</li>
                        <li><strong>Team stability:</strong> Key person continuity</li>
                        <li><strong>Strategy clarity:</strong> Clear, repeatable investment approach</li>
                        <li><strong>Operations:</strong> Back office, compliance, reporting</li>
                        <li><strong>Alignment:</strong> GP commitment, fee structure, carry timing</li>
                    </ol>

                    <h3>Red Flags</h3>
                    <ul>
                        <li>Strategy drift from prior funds</li>
                        <li>Key departures during fundraising</li>
                        <li>Unrealized gains driving track record</li>
                        <li>Excessive LP-unfriendly terms</li>
                        <li>Limited GP co-investment</li>
                    </ul>

                    <blockquote>
                        <strong>Pro Tip:</strong> In blind pool investing, you're betting on people, not deals. Spend more time on GP reference calls and understanding team dynamics than on analyzing hypothetical investment opportunities.
                    </blockquote>
                `,

                'comparable-analysis': `
                    <h2>Introduction</h2>
                    <p>Comparable Company Analysis ("Comps" or "Trading Comps") is a relative valuation methodology that values a company based on how similar public companies are valued by the market. It's one of the most commonly used valuation techniques in investment banking and equity research.</p>

                    <div class="nrt-guide-concept">
                        <h4>Key Concept</h4>
                        <p>The underlying principle: similar companies should trade at similar multiples. If Company A trades at 10x EBITDA and is comparable to our target, our target should be worth approximately 10x its EBITDA.</p>
                    </div>

                    <h2>Step 1: Select Comparable Companies</h2>

                    <h3>Selection Criteria</h3>
                    <table>
                        <tr><th>Criterion</th><th>Why It Matters</th><th>How to Screen</th></tr>
                        <tr><td>Industry/Sector</td><td>Same business model, end markets</td><td>SIC/NAICS codes, business descriptions</td></tr>
                        <tr><td>Size</td><td>Similar scale, trading liquidity</td><td>Revenue, market cap, EV bands</td></tr>
                        <tr><td>Geography</td><td>Same regulatory, economic environment</td><td>HQ location, revenue mix</td></tr>
                        <tr><td>Growth Profile</td><td>Growth drives multiples</td><td>Historical and projected growth rates</td></tr>
                        <tr><td>Profitability</td><td>Margins affect multiples</td><td>EBITDA, EBIT, net margins</td></tr>
                        <tr><td>Business Model</td><td>Recurring vs. transactional</td><td>Revenue composition analysis</td></tr>
                    </table>

                    <h3>Common Mistakes</h3>
                    <ul>
                        <li>Too few comps (less than 5-6)</li>
                        <li>Too many comps (dilutes relevance)</li>
                        <li>Ignoring business model differences</li>
                        <li>Including distressed companies without adjustment</li>
                    </ul>

                    <h2>Step 2: Gather Financial Data</h2>

                    <h3>Key Data Points</h3>
                    <ul>
                        <li><strong>Market data:</strong> Share price, shares outstanding, market cap</li>
                        <li><strong>Capital structure:</strong> Debt, cash, minority interest, preferred</li>
                        <li><strong>Financial metrics:</strong> Revenue, EBITDA, EBIT, Net Income</li>
                        <li><strong>Per share data:</strong> EPS (LTM, NTM, forward years)</li>
                        <li><strong>Growth rates:</strong> Historical and consensus estimates</li>
                    </ul>

                    <h3>Time Periods</h3>
                    <table>
                        <tr><th>Metric</th><th>Definition</th><th>Use Case</th></tr>
                        <tr><td>LTM</td><td>Last Twelve Months</td><td>Historical, actual results</td></tr>
                        <tr><td>NTM</td><td>Next Twelve Months</td><td>Forward-looking</td></tr>
                        <tr><td>CY/FY</td><td>Calendar/Fiscal Year</td><td>Specific annual periods</td></tr>
                    </table>

                    <h2>Step 3: Calculate Enterprise Value</h2>
                    <div class="nrt-guide-formula">
                        EV = Equity Value + Total Debt + Preferred Stock + Minority Interest - Cash
                    </div>

                    <h3>Equity Value Calculation</h3>
                    <div class="nrt-guide-formula">
                        Equity Value = Share Price × Fully Diluted Shares Outstanding
                    </div>

                    <h3>Diluted Shares (Treasury Stock Method)</h3>
                    <ul>
                        <li>Start with basic shares outstanding</li>
                        <li>Add in-the-money options and warrants (net of buyback)</li>
                        <li>Add unvested RSUs</li>
                        <li>Add convertible securities if in-the-money</li>
                    </ul>

                    <h2>Step 4: Calculate Trading Multiples</h2>

                    <h3>Enterprise Value Multiples</h3>
                    <table>
                        <tr><th>Multiple</th><th>Formula</th><th>Best For</th></tr>
                        <tr><td>EV/Revenue</td><td>EV ÷ Revenue</td><td>High-growth, unprofitable companies</td></tr>
                        <tr><td>EV/EBITDA</td><td>EV ÷ EBITDA</td><td>Most common; capital structure neutral</td></tr>
                        <tr><td>EV/EBIT</td><td>EV ÷ EBIT</td><td>When D&A varies significantly</td></tr>
                        <tr><td>EV/EBITDA-CapEx</td><td>EV ÷ (EBITDA - CapEx)</td><td>Capital-intensive industries</td></tr>
                    </table>

                    <h3>Equity Value Multiples</h3>
                    <table>
                        <tr><th>Multiple</th><th>Formula</th><th>Best For</th></tr>
                        <tr><td>P/E</td><td>Price ÷ EPS</td><td>Profitable, stable companies</td></tr>
                        <tr><td>P/B</td><td>Price ÷ Book Value</td><td>Banks, financial institutions</td></tr>
                        <tr><td>PEG</td><td>P/E ÷ Growth Rate</td><td>Growth companies</td></tr>
                    </table>

                    <h2>Step 5: Apply Multiples to Target</h2>

                    <h3>Statistical Analysis</h3>
                    <ul>
                        <li>Calculate mean and median of each multiple</li>
                        <li>Identify high/low of the range</li>
                        <li>Calculate standard deviation to spot outliers</li>
                        <li>Consider excluding outliers or using trimmed mean</li>
                    </ul>

                    <h3>Valuation Output</h3>
                    <div class="nrt-guide-formula">
                        Target EV = Target Metric × Selected Multiple
                    </div>
                    <div class="nrt-guide-formula">
                        Target Equity Value = Target EV - Net Debt
                    </div>
                    <div class="nrt-guide-formula">
                        Target Share Price = Equity Value ÷ Diluted Shares
                    </div>

                    <h2>Premiums and Discounts</h2>
                    <table>
                        <tr><th>Factor</th><th>Premium/Discount</th><th>Rationale</th></tr>
                        <tr><td>Higher growth</td><td>Premium</td><td>Better future prospects</td></tr>
                        <tr><td>Better margins</td><td>Premium</td><td>Operating efficiency</td></tr>
                        <tr><td>Smaller size</td><td>Discount</td><td>Less liquidity, higher risk</td></tr>
                        <tr><td>Customer concentration</td><td>Discount</td><td>Revenue risk</td></tr>
                        <tr><td>Market leadership</td><td>Premium</td><td>Competitive position</td></tr>
                    </table>

                    <blockquote>
                        <strong>Pro Tip:</strong> The comp selection matters more than the math. Spend 80% of your time finding the right comparables and understanding their businesses. The multiple calculation is the easy part.
                    </blockquote>
                `,

                'private-markets': `
                    <h2>Introduction</h2>
                    <p>Private markets encompass all investments not traded on public exchanges—including private equity, private credit, real estate, infrastructure, and natural resources. Understanding allocation, liquidity dynamics, and fund structures is essential for institutional investors and wealth managers.</p>

                    <div class="nrt-guide-concept">
                        <h4>Key Concept</h4>
                        <p>Private markets offer a return premium for accepting illiquidity and complexity. The "illiquidity premium" historically adds 200-400 bps annually, but comes with J-curve effects, long lock-ups, and limited transparency.</p>
                    </div>

                    <h2>Private Markets Landscape</h2>
                    <table>
                        <tr><th>Asset Class</th><th>Target Return</th><th>Typical Fund Life</th><th>Key Risks</th></tr>
                        <tr><td>Buyout PE</td><td>15-20% net IRR</td><td>10-12 years</td><td>Economic cycle, leverage</td></tr>
                        <tr><td>Venture Capital</td><td>20-30% net IRR</td><td>10-14 years</td><td>Technology, failure rate</td></tr>
                        <tr><td>Private Credit</td><td>8-12% net yield</td><td>5-8 years</td><td>Credit, default</td></tr>
                        <tr><td>Real Estate</td><td>10-18% net IRR</td><td>8-12 years</td><td>Property, location</td></tr>
                        <tr><td>Infrastructure</td><td>8-15% net IRR</td><td>12-20 years</td><td>Regulatory, political</td></tr>
                    </table>

                    <h2>Portfolio Allocation Considerations</h2>

                    <h3>Typical Institutional Allocations</h3>
                    <table>
                        <tr><th>Investor Type</th><th>Private Markets %</th><th>Composition</th></tr>
                        <tr><td>Large Endowment</td><td>40-60%</td><td>PE heavy, VC, RE</td></tr>
                        <tr><td>Pension Fund</td><td>15-30%</td><td>Diversified alternatives</td></tr>
                        <tr><td>Insurance Company</td><td>5-15%</td><td>Credit, infrastructure focus</td></tr>
                        <tr><td>Family Office</td><td>20-50%</td><td>Highly variable</td></tr>
                    </table>

                    <h3>The Denominator Effect</h3>
                    <p>When public markets decline sharply, private market valuations lag, causing:</p>
                    <ul>
                        <li>Private allocations to appear higher (denominator shrinks)</li>
                        <li>Forced selling or commitment reductions</li>
                        <li>GP fundraising challenges</li>
                        <li>Potential vintage year opportunities</li>
                    </ul>

                    <h2>Liquidity Dynamics</h2>

                    <h3>The J-Curve</h3>
                    <p>Private equity funds typically show negative returns initially:</p>
                    <ul>
                        <li><strong>Years 1-3:</strong> Capital calls exceed distributions (fees, early investments)</li>
                        <li><strong>Years 3-5:</strong> Value creation begins, NAV inflects</li>
                        <li><strong>Years 5-10:</strong> Realizations drive positive returns</li>
                    </ul>

                    <h3>Managing Liquidity</h3>
                    <table>
                        <tr><th>Tool</th><th>Description</th><th>Considerations</th></tr>
                        <tr><td>Commitment pacing</td><td>Spread commitments over vintage years</td><td>Reduces concentration risk</td></tr>
                        <tr><td>Unfunded reserves</td><td>Liquid assets to meet capital calls</td><td>Typically 30-50% of unfunded</td></tr>
                        <tr><td>Subscription lines</td><td>Fund-level credit facilities</td><td>Smooth capital calls, boost IRR</td></tr>
                        <tr><td>Secondary sales</td><td>Sell LP interests on secondary market</td><td>Discount to NAV (5-20%+)</td></tr>
                    </table>

                    <h2>Fund Structure Essentials</h2>

                    <h3>Closed-End Funds</h3>
                    <ul>
                        <li>Fixed capital, fixed term (10+ years)</li>
                        <li>No redemptions—must sell on secondary</li>
                        <li>Standard for PE, VC, buyout</li>
                    </ul>

                    <h3>Open-End/Evergreen Funds</h3>
                    <ul>
                        <li>Continuous fundraising, perpetual life</li>
                        <li>Quarterly liquidity (often gated)</li>
                        <li>Common for core real estate, credit</li>
                        <li>Lower fees, more liquidity, lower returns</li>
                    </ul>

                    <h3>Fund-of-Funds</h3>
                    <ul>
                        <li>Invests in multiple underlying funds</li>
                        <li>Diversification and access benefits</li>
                        <li>Additional layer of fees (1% + 5% typical)</li>
                        <li>Good for smaller allocators</li>
                    </ul>

                    <h2>Due Diligence Priorities</h2>
                    <ol>
                        <li><strong>Track Record:</strong> Realized (DPI) vs. unrealized returns</li>
                        <li><strong>Team Stability:</strong> Key person continuity</li>
                        <li><strong>Strategy Evolution:</strong> Style drift concerns</li>
                        <li><strong>Operations:</strong> Back office, compliance, reporting quality</li>
                        <li><strong>Terms:</strong> Fees, carry, GP commitment, governance</li>
                    </ol>

                    <blockquote>
                        <strong>Pro Tip:</strong> Build a private markets portfolio over 3-5 years across vintage years. A single vintage concentration can significantly impact returns if you hit a bad entry point.
                    </blockquote>
                `,

                'unitranche-structures': `
                    <h2>Introduction</h2>
                    <p>Unitranche is a hybrid debt structure that combines senior and subordinated debt into a single facility with one set of documents, one interest rate, and one lender relationship. It has become the dominant financing structure in middle-market leveraged buyouts due to its simplicity and speed.</p>

                    <div class="nrt-guide-concept">
                        <h4>Key Concept</h4>
                        <p>Unitranche is "one-stop financing"—a single lender (or club) provides all the debt, eliminating intercreditor complexity. The blended rate falls between senior and subordinated rates.</p>
                    </div>

                    <h2>Unitranche vs. Traditional Structures</h2>
                    <table>
                        <tr><th>Feature</th><th>Unitranche</th><th>Senior/Mezz Split</th></tr>
                        <tr><td>Documentation</td><td>Single credit agreement</td><td>Multiple agreements</td></tr>
                        <tr><td>Lender relationships</td><td>One (or small club)</td><td>Bank + mezz fund</td></tr>
                        <tr><td>Intercreditor</td><td>None visible to borrower</td><td>Complex subordination</td></tr>
                        <tr><td>Execution speed</td><td>Faster (4-6 weeks)</td><td>Slower (8-12 weeks)</td></tr>
                        <tr><td>Blended cost</td><td>SOFR + 550-700 bps</td><td>Lower senior + higher mezz</td></tr>
                        <tr><td>Flexibility</td><td>High (single decision maker)</td><td>Lower (multiple parties)</td></tr>
                    </table>

                    <h2>How Unitranche Works</h2>

                    <h3>The Visible Structure</h3>
                    <p>What the borrower sees:</p>
                    <ul>
                        <li>Single secured term loan facility</li>
                        <li>One blended interest rate (e.g., SOFR + 600 bps)</li>
                        <li>Single set of covenants</li>
                        <li>One lender/agent to negotiate with</li>
                    </ul>

                    <h3>The Hidden Structure (AAL)</h3>
                    <p>Behind the scenes, lenders often split the loan via Agreement Among Lenders:</p>
                    <ul>
                        <li><strong>First-out tranche:</strong> Senior economics, paid first (SOFR + 400)</li>
                        <li><strong>Last-out tranche:</strong> Junior economics, paid after first-out (SOFR + 900)</li>
                        <li>Blended to create visible rate</li>
                        <li>Borrower typically unaware of split</li>
                    </ul>

                    <h2>Unitranche Economics</h2>

                    <h3>Typical Terms</h3>
                    <table>
                        <tr><th>Element</th><th>Typical Range</th></tr>
                        <tr><td>Interest rate</td><td>SOFR + 550-700 bps</td></tr>
                        <tr><td>OID</td><td>1-2%</td></tr>
                        <tr><td>Tenor</td><td>6-7 years</td></tr>
                        <tr><td>Amortization</td><td>1% annually</td></tr>
                        <tr><td>Call protection</td><td>101-102 for 1-2 years</td></tr>
                        <tr><td>Leverage capacity</td><td>Up to 5-6x EBITDA</td></tr>
                    </table>

                    <h3>Comparing All-In Cost</h3>
                    <div class="nrt-guide-formula">
                        Unitranche All-In = Spread + OID Amortization + Unused Fees
                    </div>
                    <p>Example: SOFR + 625 bps spread + 2% OID over 5 years = ~+40 bps = ~6.65% all-in margin</p>

                    <h2>When to Use Unitranche</h2>

                    <h3>Ideal Situations</h3>
                    <ul>
                        <li>Speed is critical (competitive auction)</li>
                        <li>Middle-market deal ($25M-$500M EBITDA)</li>
                        <li>Need flexibility for add-on acquisitions</li>
                        <li>Prefer single lender relationship</li>
                        <li>Complex credit story requiring explanation</li>
                    </ul>

                    <h3>Less Ideal Situations</h3>
                    <ul>
                        <li>Large-cap deals with BSL market access</li>
                        <li>Very stable cash flows (traditional senior cheaper)</li>
                        <li>Need maximum leverage (may need separate mezz)</li>
                        <li>Investment-grade-like credits</li>
                    </ul>

                    <h2>Unitranche Covenant Structures</h2>

                    <h3>Covenant-Lite Evolution</h3>
                    <p>Unitranche has followed leveraged loan market toward covenant-lite:</p>
                    <ul>
                        <li><strong>Springing leverage covenant:</strong> Only tested if revolver drawn >35%</li>
                        <li><strong>Loose EBITDA definitions:</strong> Aggressive add-backs</li>
                        <li><strong>Portability:</strong> Debt can stay in place through change of control</li>
                    </ul>

                    <h3>Flexibility Provisions</h3>
                    <ul>
                        <li>Incremental debt baskets ($50-100M+)</li>
                        <li>Permitted acquisition carve-outs</li>
                        <li>Dividend recapitalization capacity</li>
                        <li>Asset sale reinvestment periods</li>
                    </ul>

                    <h2>Key Risks and Considerations</h2>
                    <ul>
                        <li><strong>Refinancing risk:</strong> Limited refinancing market for private credit</li>
                        <li><strong>Relationship concentration:</strong> Single lender has significant power</li>
                        <li><strong>Amendment dynamics:</strong> Easier (one party) but also full exposure</li>
                        <li><strong>Prepayment flexibility:</strong> Call protection limits early refinancing</li>
                    </ul>

                    <blockquote>
                        <strong>Pro Tip:</strong> Negotiate the unitranche documentation as if you're negotiating with a bank—because the terms will govern your relationship for 6+ years. The simplicity of one document cuts both ways.
                    </blockquote>
                `,

                're-capital-stack': `
                    <h2>Introduction</h2>
                    <p>The real estate capital stack represents the hierarchy of capital sources used to finance a property or development. Understanding the capital stack is essential for structuring deals, analyzing risk/return profiles, and negotiating with capital partners.</p>

                    <div class="nrt-guide-concept">
                        <h4>Key Concept</h4>
                        <p>Risk and return increase as you move up the capital stack. Senior debt has the lowest risk and return (first to be repaid), while common equity takes the most risk but captures unlimited upside.</p>
                    </div>

                    <h2>The Capital Stack Hierarchy</h2>
                    <table>
                        <tr><th>Position</th><th>Typical %</th><th>Return</th><th>Risk</th></tr>
                        <tr><td>Senior Debt</td><td>50-65%</td><td>5-8%</td><td>Lowest</td></tr>
                        <tr><td>Mezzanine Debt</td><td>10-20%</td><td>10-15%</td><td>Moderate</td></tr>
                        <tr><td>Preferred Equity</td><td>5-15%</td><td>12-18%</td><td>Higher</td></tr>
                        <tr><td>Common Equity</td><td>15-35%</td><td>15-25%+</td><td>Highest</td></tr>
                    </table>

                    <h2>Senior Debt</h2>

                    <h3>Key Characteristics</h3>
                    <ul>
                        <li><strong>First lien:</strong> First claim on property and cash flows</li>
                        <li><strong>Typical LTV:</strong> 50-70% (lower post-2008)</li>
                        <li><strong>Amortization:</strong> 25-30 year schedule, 5-10 year term</li>
                        <li><strong>Recourse:</strong> Often non-recourse except carve-outs</li>
                    </ul>

                    <h3>Senior Lender Types</h3>
                    <table>
                        <tr><th>Lender</th><th>Strengths</th><th>Typical Deals</th></tr>
                        <tr><td>Banks</td><td>Relationship, flexible</td><td>Construction, transitional</td></tr>
                        <tr><td>Life Insurance</td><td>Long-term, low rate</td><td>Stabilized core assets</td></tr>
                        <tr><td>CMBS</td><td>High leverage, non-recourse</td><td>Stabilized, standard assets</td></tr>
                        <tr><td>Agencies (Fannie/Freddie)</td><td>Best multifamily terms</td><td>Multifamily only</td></tr>
                        <tr><td>Debt Funds</td><td>Flexible, higher leverage</td><td>Value-add, transitional</td></tr>
                    </table>

                    <h2>Mezzanine Debt</h2>

                    <h3>Structure</h3>
                    <ul>
                        <li><strong>Position:</strong> Subordinate to senior, senior to equity</li>
                        <li><strong>Security:</strong> Pledge of ownership interests (not direct lien on property)</li>
                        <li><strong>Combined LTV:</strong> Up to 75-85%</li>
                        <li><strong>Interest:</strong> Current pay (10-12%) + potential PIK or participation</li>
                    </ul>

                    <h3>Intercreditor Agreement</h3>
                    <p>Key provisions negotiated between senior and mezz:</p>
                    <ul>
                        <li><strong>Standstill:</strong> Mezz can't accelerate without waiting period</li>
                        <li><strong>Cure rights:</strong> Mezz can cure senior defaults</li>
                        <li><strong>Purchase option:</strong> Mezz can buy senior debt at par</li>
                        <li><strong>Foreclosure priority:</strong> Who controls enforcement</li>
                    </ul>

                    <h2>Preferred Equity</h2>

                    <h3>Key Features</h3>
                    <ul>
                        <li><strong>Position:</strong> Equity class, but senior to common equity</li>
                        <li><strong>Return structure:</strong> Priority return (8-12%) + potential upside</li>
                        <li><strong>No lien:</strong> Ownership interest, not debt</li>
                        <li><strong>Control:</strong> Often has protective covenants, step-in rights</li>
                    </ul>

                    <h3>When to Use Preferred Equity</h3>
                    <ul>
                        <li>Senior lender restricts mezzanine debt</li>
                        <li>Need to avoid additional liens</li>
                        <li>Sponsor wants to reduce common equity contribution</li>
                        <li>Ground lease situations (leasehold mezz problematic)</li>
                    </ul>

                    <h2>Common Equity / JV Structures</h2>

                    <h3>Typical GP/LP Split</h3>
                    <table>
                        <tr><th>Structure</th><th>Description</th></tr>
                        <tr><td>Pari passu to pref</td><td>LP gets 8% pref, GP catches up, then split</td></tr>
                        <tr><td>Promote/Carried Interest</td><td>GP gets 20-30% above pref hurdle</td></tr>
                        <tr><td>Multiple hurdles</td><td>GP share increases as IRR hurdles are met</td></tr>
                    </table>

                    <h3>Waterfall Example</h3>
                    <ol>
                        <li>Return of capital to all partners</li>
                        <li>8% preferred return to LP</li>
                        <li>GP catch-up to 20% of profit</li>
                        <li>80/20 split (LP/GP) to 15% IRR</li>
                        <li>70/30 split above 15% IRR</li>
                    </ol>

                    <h2>Capital Stack Stress Testing</h2>

                    <h3>Key Scenarios to Model</h3>
                    <ul>
                        <li><strong>NOI decline:</strong> What happens if income drops 20%?</li>
                        <li><strong>Exit cap rate expansion:</strong> What if cap rates rise 100 bps?</li>
                        <li><strong>Lease-up delay:</strong> What if stabilization takes 12 months longer?</li>
                        <li><strong>Interest rate increase:</strong> Impact of floating rate exposure</li>
                    </ul>

                    <h3>Breakeven Analysis</h3>
                    <p>For each capital layer, calculate:</p>
                    <div class="nrt-guide-formula">
                        Breakeven Occupancy = (Debt Service + OpEx) / Potential Gross Income
                    </div>

                    <blockquote>
                        <strong>Pro Tip:</strong> In real estate, the capital stack IS the deal. A good asset with bad capital structure fails; an average asset with optimal capital stack can thrive. Spend as much time on structure as on property selection.
                    </blockquote>
                `
            };
        }

        /**
         * Download guide as PDF
         */
        downloadGuidePDF() {
            const guideTitle = document.getElementById('nrt-guide-title')?.textContent || 'Guide';
            const guideContent = document.getElementById('nrt-guide-content')?.innerHTML || '';

            // Create print-friendly window
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>${guideTitle} - MENA Careers Intelligence</title>
                    <style>
                        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; line-height: 1.6; color: #333; }
                        h1 { font-size: 28px; margin-bottom: 24px; color: #111; }
                        h2 { font-size: 20px; margin-top: 32px; margin-bottom: 16px; padding-bottom: 8px; border-bottom: 2px solid #e5e7eb; }
                        h3 { font-size: 16px; margin-top: 24px; margin-bottom: 12px; }
                        p { margin-bottom: 16px; }
                        ul, ol { margin-bottom: 16px; padding-left: 24px; }
                        li { margin-bottom: 8px; }
                        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
                        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #e5e7eb; }
                        th { background: #f9fafb; font-weight: 600; }
                        blockquote { border-left: 4px solid #3b82f6; margin: 16px 0; padding: 12px 16px; background: #f0f7ff; font-style: italic; }
                        code { background: #f3f4f6; padding: 2px 6px; border-radius: 4px; font-size: 13px; }
                        .nrt-guide-concept { background: linear-gradient(135deg, #f0f7ff, #e0f2fe); border: 1px solid #bfdbfe; border-radius: 8px; padding: 16px 20px; margin: 16px 0; }
                        .nrt-guide-concept h4 { color: #2563eb; margin: 0 0 8px 0; font-size: 12px; text-transform: uppercase; }
                        .nrt-guide-formula { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px 16px; text-align: center; font-family: monospace; margin: 16px 0; }
                        @media print { body { margin: 20px; } }
                    </style>
                </head>
                <body>
                    <h1>${guideTitle}</h1>
                    ${guideContent}
                    <footer style="margin-top: 48px; padding-top: 16px; border-top: 1px solid #e5e7eb; font-size: 12px; color: #6b7280;">
                        Generated by MENA Careers Intelligence - sennaintelligence.com
                    </footer>
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }

        // ============================================
        // CONTACTS TAB FUNCTIONALITY
        // ============================================

        /**
         * Initialize contacts tab
         */
        initContactsTab() {
            if (this.contactsInitialized) {
                return;
            }
            this.contactsInitialized = true;
            this.contactsPage = 1;
            this.contactsSearch = '';
            this.contactsCompany = '';
            this.contactsCountry = '';
            this.contactsSeniority = '';
            this.contactsIndustry = '';

            // Load filter options
            this.loadContactFilters();

            // Load initial contacts
            this.loadContacts();

            // Bind filter events
            this.bindContactFilterEvents();

            // Bind contact detail panel events
            this.bindContactDetailPanelEvents();
        }

        /**
         * Load contact filter options
         */
        loadContactFilters() {
            fetch(this.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'nrt_get_contact_filters',
                    nonce: this.nonce
                })
            })
            .then(res => res.json())
            .then(response => {
                if (response.success) {
                    const data = response.data;

                    // Populate company select
                    const companySelect = document.getElementById('nrt-contacts-company');
                    if (companySelect && data.companies) {
                        data.companies.forEach(company => {
                            const option = document.createElement('option');
                            option.value = company;
                            option.textContent = company;
                            companySelect.appendChild(option);
                        });
                    }

                    // Populate country select
                    const countrySelect = document.getElementById('nrt-contacts-country');
                    if (countrySelect && data.countries) {
                        data.countries.forEach(country => {
                            const option = document.createElement('option');
                            option.value = country;
                            option.textContent = country;
                            countrySelect.appendChild(option);
                        });
                    }

                    // Populate seniority select
                    const senioritySelect = document.getElementById('nrt-contacts-seniority');
                    if (senioritySelect && data.seniorities) {
                        data.seniorities.forEach(seniority => {
                            const option = document.createElement('option');
                            option.value = seniority;
                            option.textContent = seniority.charAt(0).toUpperCase() + seniority.slice(1);
                            senioritySelect.appendChild(option);
                        });
                    }

                    // Populate industry select
                    const industrySelect = document.getElementById('nrt-contacts-industry');
                    if (industrySelect && data.industries) {
                        data.industries.forEach(industry => {
                            const option = document.createElement('option');
                            option.value = industry;
                            option.textContent = industry;
                            industrySelect.appendChild(option);
                        });
                    }
                }
            })
            .catch(err => console.error('Error loading contact filters:', err));
        }

        /**
         * Bind contact filter events
         */
        bindContactFilterEvents() {
            // Search input
            const searchInput = document.getElementById('nrt-contacts-search');
            if (searchInput) {
                let searchTimeout;
                searchInput.addEventListener('input', (e) => {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        this.contactsSearch = e.target.value;
                        this.contactsPage = 1;
                        this.loadContacts();
                    }, 300);
                });
            }

            // Company filter
            const companySelect = document.getElementById('nrt-contacts-company');
            if (companySelect) {
                companySelect.addEventListener('change', (e) => {
                    this.contactsCompany = e.target.value;
                    this.contactsPage = 1;
                    this.loadContacts();
                });
            }

            // Country filter
            const countrySelect = document.getElementById('nrt-contacts-country');
            if (countrySelect) {
                countrySelect.addEventListener('change', (e) => {
                    this.contactsCountry = e.target.value;
                    this.contactsPage = 1;
                    this.loadContacts();
                });
            }

            // Seniority filter
            const senioritySelect = document.getElementById('nrt-contacts-seniority');
            if (senioritySelect) {
                senioritySelect.addEventListener('change', (e) => {
                    this.contactsSeniority = e.target.value;
                    this.contactsPage = 1;
                    this.loadContacts();
                });
            }

            // Industry filter
            const industrySelect = document.getElementById('nrt-contacts-industry');
            if (industrySelect) {
                industrySelect.addEventListener('change', (e) => {
                    this.contactsIndustry = e.target.value;
                    this.contactsPage = 1;
                    this.loadContacts();
                });
            }

            // Pagination
            const prevBtn = document.getElementById('nrt-contacts-prev');
            const nextBtn = document.getElementById('nrt-contacts-next');

            if (prevBtn) {
                prevBtn.addEventListener('click', () => {
                    if (this.contactsPage > 1) {
                        this.contactsPage--;
                        this.loadContacts();
                    }
                });
            }

            if (nextBtn) {
                nextBtn.addEventListener('click', () => {
                    if (this.contactsPage < this.contactsTotalPages) {
                        this.contactsPage++;
                        this.loadContacts();
                    }
                });
            }

            // Contact card clicks - show detail in right panel (desktop) or slide-in (mobile)
            this.terminal.addEventListener('click', (e) => {
                const contactCard = e.target.closest('.nrt-contact-card');
                if (contactCard) {
                    // Check if locked - show welcome modal instead
                    if (contactCard.classList.contains('is-locked')) {
                        e.preventDefault();
                        e.stopPropagation();
                        this.showWelcomeModal('contacts');
                        return;
                    }

                    // Update active state
                    this.terminal.querySelectorAll('.nrt-contact-card').forEach(c => c.classList.remove('is-active'));
                    contactCard.classList.add('is-active');

                    const contactId = contactCard.dataset.contactId;
                    if (contactId) {
                        // Hide welcome panel and show contact view for the right panel
                        const welcomePanel = document.getElementById('nrt-welcome-panel');
                        const contactView = document.getElementById('nrt-contact-view');
                        if (welcomePanel) welcomePanel.style.display = 'none';
                        if (contactView) contactView.style.display = '';

                        // Check if mobile (slide-in panel needed) or desktop (right panel only)
                        const isMobile = window.innerWidth < 1024;

                        if (isMobile) {
                            // Mobile: show slide-in panel
                            this.showContactDetailPanel(contactId);
                        } else {
                            // Desktop: only update right panel, no slide-in
                            this.loadContactDetail(contactId);
                        }
                    }
                }
            });
        }

        /**
         * Load contacts list
         */
        loadContacts() {
            const contactsList = document.getElementById('nrt-contacts-list');
            const contactsEmpty = document.getElementById('nrt-contacts-empty');
            const pagination = document.getElementById('nrt-contacts-pagination');

            if (!contactsList) return;

            // Show loading
            contactsList.innerHTML = '<div class="nrt-contacts-loading"><div class="nrt-loading-spinner"></div><span>Loading contacts...</span></div>';
            if (contactsEmpty) contactsEmpty.style.display = 'none';
            if (pagination) pagination.style.display = 'none';

            fetch(this.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'nrt_load_contacts',
                    nonce: this.nonce,
                    page: this.contactsPage,
                    search: this.contactsSearch,
                    company: this.contactsCompany,
                    country: this.contactsCountry,
                    seniority: this.contactsSeniority,
                    industry: this.contactsIndustry
                })
            })
            .then(res => res.json())
            .then(response => {
                if (response.success && response.data.has_contacts) {
                    contactsList.innerHTML = response.data.html;
                    this.contactsTotalPages = response.data.pages;

                    // Update pagination
                    if (pagination && response.data.pages > 1) {
                        pagination.style.display = '';
                        document.getElementById('nrt-contacts-current-page').textContent = response.data.page;
                        document.getElementById('nrt-contacts-total-pages').textContent = response.data.pages;

                        const prevBtn = document.getElementById('nrt-contacts-prev');
                        const nextBtn = document.getElementById('nrt-contacts-next');
                        if (prevBtn) prevBtn.disabled = response.data.page <= 1;
                        if (nextBtn) nextBtn.disabled = response.data.page >= response.data.pages;
                    }

                } else {
                    contactsList.innerHTML = '';
                    if (contactsEmpty) contactsEmpty.style.display = '';
                }
            })
            .catch(err => {
                console.error('Error loading contacts:', err);
                contactsList.innerHTML = '<div class="nrt-contacts-empty"><p>Failed to load contacts. Please try again.</p></div>';
            });
        }

        /**
         * Bind contact detail panel events
         */
        bindContactDetailPanelEvents() {
            // Close button
            const closeBtn = document.getElementById('nrt-contact-detail-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => {
                    this.hideContactDetailPanel();
                });
            }

            // Overlay click
            const overlay = document.getElementById('nrt-contact-detail-overlay');
            if (overlay) {
                overlay.addEventListener('click', () => {
                    this.hideContactDetailPanel();
                });
            }

            // Request introduction button
            const introBtn = document.getElementById('nrt-contact-detail-intro-btn');
            if (introBtn) {
                introBtn.addEventListener('click', () => {
                    // Switch to recruiter intros tab
                    this.hideContactDetailPanel();
                    this.switchTab('recruiter-intros');
                });
            }

            // Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    const panel = document.getElementById('nrt-contact-detail-panel');
                    if (panel && panel.classList.contains('is-open')) {
                        this.hideContactDetailPanel();
                    }
                }
            });
        }

        /**
         * Load contact detail for right panel only (desktop)
         */
        loadContactDetail(contactId) {
            // Fetch contact details and display in right panel only
            fetch(this.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'nrt_load_contact_detail',
                    nonce: this.nonce,
                    contact_id: contactId
                })
            })
            .then(res => res.json())
            .then(response => {
                if (response.success) {
                    // Only populate the right panel view
                    this.displayContactDetail(response.data);
                }
            })
            .catch(err => console.error('Error loading contact detail:', err));
        }

        /**
         * Show contact detail panel (slide-in for mobile)
         */
        showContactDetailPanel(contactId) {
            const panel = document.getElementById('nrt-contact-detail-panel');
            if (!panel) return;

            // Show panel with loading state
            panel.classList.add('is-open');
            document.body.classList.add('nrt-panel-open');

            // Fetch contact details
            fetch(this.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'nrt_load_contact_detail',
                    nonce: this.nonce,
                    contact_id: contactId
                })
            })
            .then(res => res.json())
            .then(response => {
                if (response.success) {
                    // Populate the slide-in panel
                    this.populateContactDetailPanel(response.data);

                    // Also populate the right panel view (for when user rotates device)
                    this.displayContactDetail(response.data);
                }
            })
            .catch(err => console.error('Error loading contact detail:', err));
        }

        /**
         * Hide contact detail panel
         */
        hideContactDetailPanel() {
            const panel = document.getElementById('nrt-contact-detail-panel');
            if (panel) {
                panel.classList.remove('is-open');
                document.body.classList.remove('nrt-panel-open');
            }
        }

        /**
         * Populate contact detail panel with data (slide-in panel for mobile)
         */
        populateContactDetailPanel(contact) {
            // Avatar with initials
            const avatarEl = document.getElementById('nrt-contact-detail-avatar');
            if (avatarEl) {
                if (contact.photo_url) {
                    avatarEl.innerHTML = `<img src="${contact.photo_url}" alt="${contact.full_name || ''}" />`;
                } else {
                    avatarEl.innerHTML = `<span class="nrt-contact-detail-initials">${contact.initials || ''}</span>`;
                }
            }

            // Name
            const nameEl = document.getElementById('nrt-contact-detail-name');
            if (nameEl) nameEl.textContent = contact.full_name || '';

            // Title
            const titleEl = document.getElementById('nrt-contact-detail-title');
            if (titleEl) titleEl.textContent = contact.job_title || 'Position not specified';

            // Company
            const companyEl = document.getElementById('nrt-contact-detail-company');
            if (companyEl) companyEl.textContent = contact.company_name || '';

            // Bio / Company Description
            const bioSection = document.getElementById('nrt-contact-detail-bio-section');
            const bioEl = document.getElementById('nrt-contact-detail-bio');
            if (bioSection && bioEl) {
                if (contact.company_description) {
                    bioEl.textContent = contact.company_description;
                    bioSection.style.display = '';
                } else {
                    bioSection.style.display = 'none';
                }
            }

            // Email
            const emailRow = document.getElementById('nrt-contact-detail-email-row');
            const emailEl = document.getElementById('nrt-contact-detail-email');
            if (emailRow && emailEl) {
                if (contact.email) {
                    emailEl.textContent = contact.email;
                    emailRow.style.display = '';
                } else {
                    emailRow.style.display = 'none';
                }
            }

            // Seniority
            const seniorityRow = document.getElementById('nrt-contact-detail-seniority-row');
            const seniorityEl = document.getElementById('nrt-contact-detail-seniority');
            if (seniorityRow && seniorityEl) {
                if (contact.seniority) {
                    seniorityEl.textContent = contact.seniority.charAt(0).toUpperCase() + contact.seniority.slice(1);
                    seniorityRow.style.display = '';
                } else {
                    seniorityRow.style.display = 'none';
                }
            }

            // Industry
            const industryRow = document.getElementById('nrt-contact-detail-industry-row');
            const industryEl = document.getElementById('nrt-contact-detail-industry');
            if (industryRow && industryEl) {
                if (contact.main_industry) {
                    industryEl.textContent = contact.main_industry;
                    industryRow.style.display = '';
                } else {
                    industryRow.style.display = 'none';
                }
            }

            // Location
            const locationRow = document.getElementById('nrt-contact-detail-location-row');
            const locationEl = document.getElementById('nrt-contact-detail-location');
            if (locationRow && locationEl) {
                if (contact.location) {
                    locationEl.textContent = contact.location;
                    locationRow.style.display = '';
                } else {
                    locationRow.style.display = 'none';
                }
            }

            // Specialties / Departments
            const specialtiesSection = document.getElementById('nrt-contact-detail-specialties-section');
            const specialtiesEl = document.getElementById('nrt-contact-detail-specialties');
            if (specialtiesSection && specialtiesEl) {
                const departments = contact.departments;
                if (departments) {
                    const deptArray = departments.split(',').map(d => d.trim()).filter(d => d);
                    if (deptArray.length > 0) {
                        specialtiesEl.innerHTML = deptArray.map(s => `<span class="nrt-contact-detail-tag">${s}</span>`).join('');
                        specialtiesSection.style.display = '';
                    } else {
                        specialtiesSection.style.display = 'none';
                    }
                } else {
                    specialtiesSection.style.display = 'none';
                }
            }

            // LinkedIn
            const linkedinEl = document.getElementById('nrt-contact-detail-linkedin');
            if (linkedinEl) {
                if (contact.linkedin_url) {
                    linkedinEl.href = contact.linkedin_url;
                    linkedinEl.style.display = '';
                } else {
                    linkedinEl.style.display = 'none';
                }
            }

            // Store contact ID for intro request
            const introBtn = document.getElementById('nrt-contact-detail-intro-btn');
            if (introBtn) {
                introBtn.dataset.contactId = contact.id;
            }
        }

        // ===============================
        // Welcome Modal (Logged-Out Users)
        // ===============================

        /**
         * Show welcome modal with context-specific content
         */
        showWelcomeModal(context = 'contacts') {
            const modal = document.getElementById('nrt-welcome-modal');
            if (!modal) return;

            // Update modal content based on context
            const title = document.getElementById('nrt-welcome-title');
            const desc = document.getElementById('nrt-welcome-desc');
            const benefit1 = document.getElementById('nrt-welcome-benefit-1');
            const benefit2 = document.getElementById('nrt-welcome-benefit-2');
            const benefit3 = document.getElementById('nrt-welcome-benefit-3');

            const content = {
                contacts: {
                    title: 'Access HR Contacts',
                    desc: 'Create a free account to browse 50,000+ verified HR contacts and decision makers.',
                    benefits: ['Access contact database', 'Filter by company & seniority', 'Request introductions']
                },
                jobs: {
                    title: 'Unlock All Jobs',
                    desc: 'Sign in to view all curated job opportunities and apply directly.',
                    benefits: ['View all job listings', 'See salary ranges', 'Apply with one click']
                },
                opportunities: {
                    title: 'Track Opportunities',
                    desc: 'Create an account to see recruiters interested in your profile.',
                    benefits: ['See who viewed your profile', 'Track recruiter interest', 'Manage opportunities']
                },
                replies: {
                    title: 'Manage Conversations',
                    desc: 'Sign in to view and respond to recruiter messages.',
                    benefits: ['View all messages', 'Respond to recruiters', 'Track conversations']
                },
                'recruiter-intros': {
                    title: 'Get Introduced',
                    desc: 'Create an account to launch your recruiter introduction campaign.',
                    benefits: ['Set your preferences', 'Get matched with recruiters', 'Receive opportunities']
                }
            };

            const ctx = content[context] || content.contacts;

            if (title) title.textContent = ctx.title;
            if (desc) desc.textContent = ctx.desc;
            if (benefit1) benefit1.textContent = ctx.benefits[0];
            if (benefit2) benefit2.textContent = ctx.benefits[1];
            if (benefit3) benefit3.textContent = ctx.benefits[2];

            modal.classList.add('is-active');
        }

        /**
         * Update welcome panel content based on active tab
         */
        updateWelcomePanelContent(tabName) {
            const panel = document.getElementById('nrt-welcome-panel');
            if (!panel) return;

            const icon = document.getElementById('nrt-welcome-panel-icon');
            const title = document.getElementById('nrt-welcome-panel-title');
            const desc = document.getElementById('nrt-welcome-panel-desc');
            const features = document.getElementById('nrt-welcome-panel-features');

            const content = {
                'news': {
                    icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48"><path d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>',
                    title: 'Stay Informed',
                    desc: 'Get curated career news and industry insights delivered to your feed.',
                    features: [
                        { icon: 'news', title: 'Curated News', subtitle: 'Industry insights' },
                        { icon: 'trending', title: 'Trending Topics', subtitle: 'What\'s hot now' },
                        { icon: 'bookmark', title: 'Save Articles', subtitle: 'Read later' },
                        { icon: 'personalize', title: 'Personalized', subtitle: 'Tailored to you' }
                    ]
                },
                'contacts': {
                    icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
                    title: 'Access HR Contacts',
                    desc: 'Browse 50,000+ verified hiring managers and recruiters from top companies.',
                    features: [
                        { icon: 'contacts', title: 'HR Contacts', subtitle: '50,000+ verified' },
                        { icon: 'filter', title: 'Smart Filters', subtitle: 'Find the right fit' },
                        { icon: 'linkedin', title: 'LinkedIn Profiles', subtitle: 'Direct access' },
                        { icon: 'intro', title: 'Express Interest', subtitle: 'Get connected' }
                    ]
                },
                'jobs': {
                    icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16"/></svg>',
                    title: 'Curated Job Opportunities',
                    desc: 'Hand-picked roles from quality employers with full transparency on compensation.',
                    features: [
                        { icon: 'jobs', title: 'Curated Jobs', subtitle: 'Quality roles only' },
                        { icon: 'salary', title: 'Salary Ranges', subtitle: 'Full transparency' },
                        { icon: 'apply', title: 'Quick Apply', subtitle: 'One-click apply' },
                        { icon: 'match', title: 'Job Matching', subtitle: 'AI-powered' }
                    ]
                },
                'recruiter-intros': {
                    icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>',
                    title: 'Express Interest with Recruiters',
                    desc: 'Set your preferences once and let our team express your interest with relevant recruiters.',
                    features: [
                        { icon: 'preferences', title: 'Set Preferences', subtitle: 'Your ideal role' },
                        { icon: 'matching', title: 'Smart Matching', subtitle: 'AI-powered' },
                        { icon: 'intro', title: 'Express Interest', subtitle: 'Concierge outreach' },
                        { icon: 'track', title: 'Track Progress', subtitle: 'See all activity' }
                    ]
                },
                'opportunities': {
                    icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>',
                    title: 'Track Your Opportunities',
                    desc: 'See all roles where recruiters have expressed interest in your profile.',
                    features: [
                        { icon: 'pipeline', title: 'Pipeline View', subtitle: 'All opportunities' },
                        { icon: 'status', title: 'Status Tracking', subtitle: 'Real-time updates' },
                        { icon: 'reminder', title: 'Reminders', subtitle: 'Never miss follow-up' },
                        { icon: 'history', title: 'Full History', subtitle: 'Complete timeline' }
                    ]
                },
                'replies': {
                    icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg>',
                    title: 'Manage Conversations',
                    desc: 'All your recruiter conversations in one place. Respond quickly and track progress.',
                    features: [
                        { icon: 'inbox', title: 'Unified Inbox', subtitle: 'All messages' },
                        { icon: 'notify', title: 'Instant Alerts', subtitle: 'Never miss a message' },
                        { icon: 'template', title: 'Templates', subtitle: 'Quick responses' },
                        { icon: 'archive', title: 'Archive', subtitle: 'Organized history' }
                    ]
                }
            };

            // Default to news if tab not found
            const ctx = content[tabName] || content['news'];

            if (icon) icon.innerHTML = ctx.icon;
            if (title) title.textContent = ctx.title;
            if (desc) desc.textContent = ctx.desc;

            // Update features grid with generic icons
            if (features && ctx.features) {
                features.innerHTML = ctx.features.map(f => `
                    <div class="nrt-welcome-feature">
                        <div class="nrt-welcome-feature-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                                <circle cx="12" cy="12" r="10"/>
                                <path d="M9 12l2 2 4-4"/>
                            </svg>
                        </div>
                        <div class="nrt-welcome-feature-text">
                            <strong>${f.title}</strong>
                            <span>${f.subtitle}</span>
                        </div>
                    </div>
                `).join('');
            }
        }

        /**
         * Hide welcome modal
         */
        hideWelcomeModal() {
            const modal = document.getElementById('nrt-welcome-modal');
            if (modal) modal.classList.remove('is-active');
        }

        /**
         * Bind welcome modal events
         */
        bindWelcomeModalEvents() {
            // Close button
            const closeBtn = document.getElementById('nrt-welcome-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => this.hideWelcomeModal());
            }

            // Overlay click
            const modal = document.getElementById('nrt-welcome-modal');
            if (modal) {
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) this.hideWelcomeModal();
                });
            }

            // Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') this.hideWelcomeModal();
            });

            // Locked contact card clicks
            document.addEventListener('click', (e) => {
                const lockedCard = e.target.closest('.nrt-contact-card.is-locked');
                if (lockedCard) {
                    e.preventDefault();
                    e.stopPropagation();
                    this.showWelcomeModal('contacts');
                }
            });

            // Locked job card clicks
            document.addEventListener('click', (e) => {
                const lockedJob = e.target.closest('.nrt-job-card.is-locked');
                if (lockedJob) {
                    e.preventDefault();
                    e.stopPropagation();
                    this.showWelcomeModal('jobs');
                }
            });

            // Locked opportunity card clicks
            document.addEventListener('click', (e) => {
                const lockedOpp = e.target.closest('.nrt-opportunity-card.is-locked');
                if (lockedOpp) {
                    e.preventDefault();
                    e.stopPropagation();
                    this.showWelcomeModal('opportunities');
                }
            });
        }

        /**
         * Select and display contact detail
         */
        selectContact(card) {
            // Update active state
            this.terminal.querySelectorAll('.nrt-contact-card').forEach(c => c.classList.remove('is-active'));
            card.classList.add('is-active');

            const contactId = card.dataset.contactId;

            // Show contact view
            const contentInner = document.getElementById('nrt-content-inner');
            const guideView = document.getElementById('nrt-guide-view');
            const profileView = document.getElementById('nrt-profile-view');
            const contactView = document.getElementById('nrt-contact-view');

            if (contentInner) contentInner.style.display = 'none';
            if (guideView) guideView.style.display = 'none';
            if (profileView) profileView.style.display = 'none';
            if (contactView) contactView.style.display = '';

            // Load contact details
            fetch(this.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'nrt_load_contact_detail',
                    nonce: this.nonce,
                    contact_id: contactId
                })
            })
            .then(res => res.json())
            .then(response => {
                if (response.success) {
                    this.displayContactDetail(response.data);
                }
            })
            .catch(err => console.error('Error loading contact detail:', err));
        }

        /**
         * Display contact detail in view
         */
        displayContactDetail(contact) {
            // Update header
            document.getElementById('nrt-contact-name').textContent = contact.full_name;
            document.getElementById('nrt-contact-title').textContent = contact.job_title || 'Position not specified';
            document.getElementById('nrt-contact-company').textContent = contact.company_name || '';

            // Update avatar initials
            const avatar = document.getElementById('nrt-contact-avatar');
            if (avatar) {
                avatar.querySelector('.nrt-contact-initials').textContent = contact.initials;
            }

            // Update action buttons
            const linkedinBtn = document.getElementById('nrt-contact-linkedin');
            if (linkedinBtn) {
                if (contact.linkedin_url) {
                    linkedinBtn.href = contact.linkedin_url;
                    linkedinBtn.style.display = '';
                } else {
                    linkedinBtn.style.display = 'none';
                }
            }

            const emailBtn = document.getElementById('nrt-contact-email');
            if (emailBtn) {
                if (contact.email) {
                    emailBtn.href = 'mailto:' + contact.email;
                    emailBtn.style.display = '';
                } else {
                    emailBtn.style.display = 'none';
                }
            }

            // Update contact info
            document.getElementById('nrt-contact-email-value').textContent = contact.email || '-';
            document.getElementById('nrt-contact-phone-value').textContent = contact.phone || '-';
            document.getElementById('nrt-contact-seniority-value').textContent = contact.seniority ? contact.seniority.charAt(0).toUpperCase() + contact.seniority.slice(1) : '-';
            document.getElementById('nrt-contact-department-value').textContent = contact.departments || '-';
            document.getElementById('nrt-contact-location-value').textContent = contact.location || '-';

            // Update company info
            document.getElementById('nrt-contact-company-name-value').textContent = contact.company_name || '-';
            document.getElementById('nrt-contact-industry-value').textContent = contact.main_industry || '-';
            document.getElementById('nrt-contact-size-value').textContent = contact.num_employees || '-';
            document.getElementById('nrt-contact-revenue-value').textContent = contact.revenue || '-';
            document.getElementById('nrt-contact-company-location-value').textContent = contact.company_location || '-';

            const descWrap = document.getElementById('nrt-contact-company-desc-wrap');
            const descValue = document.getElementById('nrt-contact-company-desc-value');
            if (contact.company_description) {
                descValue.textContent = contact.company_description;
                descWrap.style.display = '';
            } else {
                descWrap.style.display = 'none';
            }

            // Update company links
            const companyWebsite = document.getElementById('nrt-contact-company-website');
            const companyLinkedin = document.getElementById('nrt-contact-company-linkedin');
            const linksContainer = document.getElementById('nrt-contact-company-links');

            let hasLinks = false;
            if (companyWebsite) {
                if (contact.company_website) {
                    companyWebsite.href = contact.company_website.startsWith('http') ? contact.company_website : 'https://' + contact.company_website;
                    companyWebsite.style.display = '';
                    hasLinks = true;
                } else {
                    companyWebsite.style.display = 'none';
                }
            }

            if (companyLinkedin) {
                if (contact.company_linkedin) {
                    companyLinkedin.href = contact.company_linkedin;
                    companyLinkedin.style.display = '';
                    hasLinks = true;
                } else {
                    companyLinkedin.style.display = 'none';
                }
            }

            if (linksContainer) {
                linksContainer.style.display = hasLinks ? '' : 'none';
            }
        }

    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => new NewsroomTerminal());
    } else {
        new NewsroomTerminal();
    }
})();
