/**
 * Strategy Dashboard - Ultra-Premium Application Planning
 * Vogue-inspired career strategy interface
 */

(function($) {
    'use strict';
    
    class StrategyDashboard {
        constructor() {
            this.strategies = [];
            this.timeline = [];
            this.insights = {};
            this.isOpen = false;
            this.currentView = 'overview';
            this.init();
        }
        
        init() {
            this.createDashboard();
            this.bindEvents();
            this.loadStrategies();
            this.initializeTimeline();
        }
        
        createDashboard() {
            if ($('#strategy-dashboard').length) return;
            
            const dashboard = `
                <div id="strategy-dashboard" class="sffc-strategy-dashboard">
                    <div class="strategy-overlay"></div>
                    <div class="strategy-container">
                        
                        <!-- Luxe Header -->
                        <header class="strategy-header">
                            <div class="strategy-branding">
                                <span class="strategy-logo">STRATEGY</span>
                                <span class="strategy-tagline">CURATED CAREER EXCELLENCE</span>
                            </div>
                            <nav class="strategy-nav">
                                <button class="nav-item active" data-view="overview">
                                    <span class="nav-icon">◈</span>
                                    <span class="nav-label">Overview</span>
                                </button>
                                <button class="nav-item" data-view="timeline">
                                    <span class="nav-icon">◉</span>
                                    <span class="nav-label">Timeline</span>
                                </button>
                                <button class="nav-item" data-view="insights">
                                    <span class="nav-icon">◊</span>
                                    <span class="nav-label">Insights</span>
                                </button>
                                <button class="nav-item" data-view="actions">
                                    <span class="nav-icon">▣</span>
                                    <span class="nav-label">Actions</span>
                                </button>
                            </nav>
                            <button class="strategy-close">
                                <span class="close-icon">✕</span>
                            </button>
                        </header>
                        
                        <!-- Main Content Area -->
                        <main class="strategy-content">
                            
                            <!-- Overview View -->
                            <section class="strategy-view view-overview active">
                                <div class="editorial-grid">
                                    
                                    <!-- Hero Strategy Card -->
                                    <article class="editorial-feature">
                                        <div class="feature-header">
                                            <span class="feature-category">FEATURED STRATEGY</span>
                                            <span class="feature-date">${new Date().toLocaleDateString('en-US', { month: 'long', year: 'numeric' })}</span>
                                        </div>
                                        <h1 class="feature-headline">Your Personalized Career Narrative</h1>
                                        <p class="feature-lead">
                                            A sophisticated approach to positioning yourself for premium opportunities 
                                            in today's competitive landscape.
                                        </p>
                                        <div class="feature-metrics">
                                            <div class="metric-card">
                                                <span class="metric-value">0</span>
                                                <span class="metric-label">Active Applications</span>
                                            </div>
                                            <div class="metric-card">
                                                <span class="metric-value">0</span>
                                                <span class="metric-label">Interviews Scheduled</span>
                                            </div>
                                            <div class="metric-card">
                                                <span class="metric-value">0%</span>
                                                <span class="metric-label">Response Rate</span>
                                            </div>
                                        </div>
                                    </article>
                                    
                                    <!-- Strategy Cards Grid -->
                                    <div class="strategy-cards-grid">
                                        <article class="strategy-card positioning">
                                            <div class="card-accent"></div>
                                            <h3 class="card-title">Market Positioning</h3>
                                            <p class="card-description">
                                                Craft your unique value proposition to stand out in your target market.
                                            </p>
                                            <div class="card-insights">
                                                <div class="insight-item">
                                                    <span class="insight-icon">▲</span>
                                                    <span class="insight-text">Above market average</span>
                                                </div>
                                                <div class="insight-item">
                                                    <span class="insight-icon">◆</span>
                                                    <span class="insight-text">Premium tier candidate</span>
                                                </div>
                                            </div>
                                            <button class="card-action">Refine Position</button>
                                        </article>
                                        
                                        <article class="strategy-card networking">
                                            <div class="card-accent"></div>
                                            <h3 class="card-title">Network Strategy</h3>
                                            <p class="card-description">
                                                Leverage strategic connections to unlock hidden opportunities.
                                            </p>
                                            <div class="card-insights">
                                                <div class="insight-item">
                                                    <span class="insight-icon">◎</span>
                                                    <span class="insight-text">12 key connections</span>
                                                </div>
                                                <div class="insight-item">
                                                    <span class="insight-icon">★</span>
                                                    <span class="insight-text">3 warm introductions</span>
                                                </div>
                                            </div>
                                            <button class="card-action">Expand Network</button>
                                        </article>
                                        
                                        <article class="strategy-card application">
                                            <div class="card-accent"></div>
                                            <h3 class="card-title">Application Excellence</h3>
                                            <p class="card-description">
                                                Tailored approaches for each opportunity to maximize success rates.
                                            </p>
                                            <div class="card-insights">
                                                <div class="insight-item">
                                                    <span class="insight-icon">✓</span>
                                                    <span class="insight-text">ATS optimized</span>
                                                </div>
                                                <div class="insight-item">
                                                    <span class="insight-icon">◈</span>
                                                    <span class="insight-text">Keyword enhanced</span>
                                                </div>
                                            </div>
                                            <button class="card-action">Optimize Applications</button>
                                        </article>
                                        
                                        <article class="strategy-card interview">
                                            <div class="card-accent"></div>
                                            <h3 class="card-title">Interview Mastery</h3>
                                            <p class="card-description">
                                                Sophisticated preparation techniques for high-stakes conversations.
                                            </p>
                                            <div class="card-insights">
                                                <div class="insight-item">
                                                    <span class="insight-icon">◉</span>
                                                    <span class="insight-text">STAR framework</span>
                                                </div>
                                                <div class="insight-item">
                                                    <span class="insight-icon">▣</span>
                                                    <span class="insight-text">Case ready</span>
                                                </div>
                                            </div>
                                            <button class="card-action">Practice Sessions</button>
                                        </article>
                                    </div>
                                    
                                    <!-- Opportunity Pipeline -->
                                    <section class="opportunity-pipeline">
                                        <header class="pipeline-header">
                                            <h2 class="pipeline-title">Application Pipeline</h2>
                                            <div class="pipeline-filters">
                                                <button class="filter-btn active">All</button>
                                                <button class="filter-btn">Active</button>
                                                <button class="filter-btn">Pending</button>
                                                <button class="filter-btn">Closed</button>
                                            </div>
                                        </header>
                                        <div class="pipeline-stages">
                                            <div class="stage researching">
                                                <h3 class="stage-title">Researching</h3>
                                                <div class="stage-count">0</div>
                                                <div class="stage-items" id="stage-researching">
                                                    <p class="empty-state">No opportunities in research</p>
                                                </div>
                                            </div>
                                            <div class="stage preparing">
                                                <h3 class="stage-title">Preparing</h3>
                                                <div class="stage-count">0</div>
                                                <div class="stage-items" id="stage-preparing">
                                                    <p class="empty-state">No applications in preparation</p>
                                                </div>
                                            </div>
                                            <div class="stage applied">
                                                <h3 class="stage-title">Applied</h3>
                                                <div class="stage-count">0</div>
                                                <div class="stage-items" id="stage-applied">
                                                    <p class="empty-state">No pending applications</p>
                                                </div>
                                            </div>
                                            <div class="stage interviewing">
                                                <h3 class="stage-title">Interviewing</h3>
                                                <div class="stage-count">0</div>
                                                <div class="stage-items" id="stage-interviewing">
                                                    <p class="empty-state">No scheduled interviews</p>
                                                </div>
                                            </div>
                                            <div class="stage negotiating">
                                                <h3 class="stage-title">Negotiating</h3>
                                                <div class="stage-count">0</div>
                                                <div class="stage-items" id="stage-negotiating">
                                                    <p class="empty-state">No active negotiations</p>
                                                </div>
                                            </div>
                                        </div>
                                    </section>
                                </div>
                            </section>
                            
                            <!-- Timeline View -->
                            <section class="strategy-view view-timeline">
                                <div class="timeline-container">
                                    <header class="timeline-header">
                                        <h2 class="timeline-title">Strategic Timeline</h2>
                                        <p class="timeline-subtitle">Your journey to career excellence</p>
                                    </header>
                                    <div class="timeline-track" id="strategy-timeline">
                                        <!-- Timeline items will be dynamically added -->
                                    </div>
                                    <div class="timeline-controls">
                                        <button class="btn-add-milestone">
                                            <span class="btn-icon">+</span>
                                            Add Milestone
                                        </button>
                                    </div>
                                </div>
                            </section>
                            
                            <!-- Insights View -->
                            <section class="strategy-view view-insights">
                                <div class="insights-container">
                                    <header class="insights-header">
                                        <h2 class="insights-title">Market Intelligence</h2>
                                        <p class="insights-subtitle">Data-driven career insights</p>
                                    </header>
                                    <div class="insights-grid">
                                        <article class="insight-card market-trends">
                                            <h3>Market Trends</h3>
                                            <div class="trend-chart" id="market-trend-chart"></div>
                                            <div class="trend-summary">
                                                <p>Your target roles are seeing <strong>15% growth</strong> in demand</p>
                                            </div>
                                        </article>
                                        <article class="insight-card salary-benchmark">
                                            <h3>Compensation Analysis</h3>
                                            <div class="salary-range">
                                                <div class="range-bar">
                                                    <div class="range-fill" style="width: 75%"></div>
                                                    <div class="range-marker" style="left: 75%"></div>
                                                </div>
                                                <div class="range-labels">
                                                    <span class="label-min">Market Min</span>
                                                    <span class="label-you">You</span>
                                                    <span class="label-max">Market Max</span>
                                                </div>
                                            </div>
                                        </article>
                                        <article class="insight-card skill-gaps">
                                            <h3>Skill Enhancement</h3>
                                            <div class="skill-recommendations">
                                                <div class="skill-item priority-high">
                                                    <span class="skill-name">Cloud Architecture</span>
                                                    <span class="skill-impact">High Impact</span>
                                                </div>
                                                <div class="skill-item priority-medium">
                                                    <span class="skill-name">Data Analytics</span>
                                                    <span class="skill-impact">Medium Impact</span>
                                                </div>
                                                <div class="skill-item priority-low">
                                                    <span class="skill-name">Project Management</span>
                                                    <span class="skill-impact">Nice to Have</span>
                                                </div>
                                            </div>
                                        </article>
                                        <article class="insight-card competition">
                                            <h3>Competitive Landscape</h3>
                                            <div class="competition-metrics">
                                                <div class="metric">
                                                    <span class="metric-label">Your Score</span>
                                                    <span class="metric-value">85/100</span>
                                                </div>
                                                <div class="metric">
                                                    <span class="metric-label">Average Candidate</span>
                                                    <span class="metric-value">72/100</span>
                                                </div>
                                                <div class="metric">
                                                    <span class="metric-label">Top Percentile</span>
                                                    <span class="metric-value">Top 15%</span>
                                                </div>
                                            </div>
                                        </article>
                                    </div>
                                </div>
                            </section>
                            
                            <!-- Actions View -->
                            <section class="strategy-view view-actions">
                                <div class="actions-container">
                                    <header class="actions-header">
                                        <h2 class="actions-title">Strategic Actions</h2>
                                        <p class="actions-subtitle">Curated next steps for success</p>
                                    </header>
                                    <div class="actions-list" id="strategic-actions">
                                        <!-- Actions will be dynamically loaded -->
                                    </div>
                                    <div class="actions-cta">
                                        <button class="btn-ai-strategy">
                                            <span class="btn-icon">◈</span>
                                            Get AI Strategy Recommendations
                                        </button>
                                    </div>
                                </div>
                            </section>
                            
                        </main>
                        
                        <!-- Luxury Footer -->
                        <footer class="strategy-footer">
                            <div class="footer-actions">
                                <button class="btn-export-strategy">
                                    <span class="btn-icon">◊</span>
                                    Export Strategy
                                </button>
                                <button class="btn-share-strategy">
                                    <span class="btn-icon">▣</span>
                                    Share with Mentor
                                </button>
                                <button class="btn-schedule-review">
                                    <span class="btn-icon">◉</span>
                                    Schedule Review
                                </button>
                            </div>
                            <div class="footer-branding">
                                <span class="brand-text">MENA CAREERS CAREER INTELLIGENCE</span>
                                <span class="brand-separator">|</span>
                                <span class="brand-tagline">ELEVATE YOUR TRAJECTORY</span>
                            </div>
                        </footer>
                        
                    </div>
                </div>
            `;
            
            $('body').append(dashboard);
        }
        
        bindEvents() {
            // Navigation
            $(document).on('click', '.strategy-nav .nav-item', (e) => {
                this.switchView($(e.currentTarget).data('view'));
            });
            
            // Open/Close - include data-action="strategy" selector
            $(document).on('click', '.open-strategy-dashboard, .sffc-strategy-icon, [data-action="strategy"]', () => {
                this.open();
            });
            
            $(document).on('click', '.strategy-close, .strategy-overlay', () => {
                this.close();
            });
            
            // Strategy Card Actions
            $(document).on('click', '.strategy-card .card-action', (e) => {
                this.handleCardAction($(e.currentTarget).closest('.strategy-card'));
            });
            
            // Pipeline Stage Drag-Drop
            this.initializePipelineDragDrop();
            
            // Timeline Controls
            $(document).on('click', '.btn-add-milestone', () => {
                this.addMilestone();
            });
            
            // Footer Actions
            $(document).on('click', '.btn-export-strategy', () => {
                this.exportStrategy();
            });
            
            $(document).on('click', '.btn-ai-strategy', () => {
                this.getAIRecommendations();
            });
            
            // Filter buttons
            $(document).on('click', '.pipeline-filters .filter-btn', (e) => {
                $('.pipeline-filters .filter-btn').removeClass('active');
                $(e.currentTarget).addClass('active');
                this.filterPipeline($(e.currentTarget).text().toLowerCase());
            });
        }
        
        loadStrategies() {
            // Load saved strategies from localStorage or backend
            const saved = localStorage.getItem('sffc_strategies');
            if (saved) {
                this.strategies = JSON.parse(saved);
                this.updateMetrics();
            }
            
            // Load saved jobs and convert to pipeline
            const savedJobs = JSON.parse(localStorage.getItem('sffc_saved_jobs') || '[]');
            this.populatePipeline(savedJobs);
        }
        
        populatePipeline(jobs) {
            // Clear existing items
            $('.stage-items').each(function() {
                if ($(this).find('.empty-state').length === 0) {
                    $(this).html('<p class="empty-state">Drop opportunities here</p>');
                }
            });
            
            // Add jobs to researching stage by default
            if (jobs.length > 0) {
                const researchingStage = $('#stage-researching');
                researchingStage.empty();
                
                jobs.forEach(job => {
                    const item = `
                        <div class="pipeline-item" data-job-id="${job.id}">
                            <div class="item-header">
                                <h4 class="item-title">${job.title}</h4>
                                <span class="item-company">${job.company}</span>
                            </div>
                            <div class="item-meta">
                                <span class="meta-match">${job.match_score || 75}% match</span>
                                <span class="meta-deadline">No deadline</span>
                            </div>
                            <div class="item-actions">
                                <button class="btn-item-action" data-action="notes">◈</button>
                                <button class="btn-item-action" data-action="move">▶</button>
                            </div>
                        </div>
                    `;
                    researchingStage.append(item);
                });
                
                this.updateStageCounts();
            }
        }
        
        initializePipelineDragDrop() {
            const self = this;
            
            // Initialize all existing pipeline items as draggable
            const initDraggable = () => {
                $('.pipeline-item').each(function() {
                    if (!$(this).data('ui-draggable')) {
                        $(this).draggable({
                            revert: 'invalid',
                            containment: '.opportunity-pipeline',
                            helper: 'clone',
                            cursor: 'move',
                            opacity: 0.7,
                            zIndex: 10000,
                            start: function(event, ui) {
                                $(this).addClass('dragging');
                                ui.helper.addClass('dragging-helper');
                            },
                            stop: function(event, ui) {
                                $(this).removeClass('dragging');
                            }
                        });
                    }
                });
            };
            
            // Initialize all stage drop zones
            const initDroppable = () => {
                $('.stage-items').each(function() {
                    if (!$(this).data('ui-droppable')) {
                        $(this).droppable({
                            accept: '.pipeline-item',
                            hoverClass: 'stage-hover',
                            tolerance: 'pointer',
                            drop: function(event, ui) {
                                const item = ui.draggable;
                                const targetStage = $(this);
                                
                                // Remove empty state if exists
                                targetStage.find('.empty-state').remove();
                                
                                // Clone the item if using helper
                                const newItem = item.clone();
                                newItem.removeClass('dragging ui-draggable-dragging');
                                
                                // Append to new stage
                                targetStage.append(newItem);
                                
                                // Remove original item
                                item.remove();
                                
                                // Reinitialize draggable on new item
                                setTimeout(() => {
                                    initDraggable();
                                }, 100);
                                
                                // Update counts
                                self.updateStageCounts();
                                
                                // Save state
                                self.savePipelineState();
                                
                                // Add success feedback
                                targetStage.addClass('drop-success');
                                setTimeout(() => {
                                    targetStage.removeClass('drop-success');
                                }, 500);
                            }
                        });
                    }
                });
            };
            
            // Initial setup
            initDraggable();
            initDroppable();
            
            // Re-initialize when new items are added
            $(document).on('DOMNodeInserted', '.pipeline-item', function() {
                setTimeout(() => {
                    initDraggable();
                }, 100);
            });
        }
        
        updateStageCounts() {
            $('.stage').each(function() {
                const count = $(this).find('.pipeline-item').length;
                $(this).find('.stage-count').text(count);
                
                // Update stage appearance based on count
                if (count > 0) {
                    $(this).addClass('has-items');
                } else {
                    $(this).removeClass('has-items');
                }
            });
        }
        
        savePipelineState() {
            const pipeline = {};
            $('.stage').each(function() {
                const stageName = $(this).attr('class').replace('stage ', '');
                const items = [];
                $(this).find('.pipeline-item').each(function() {
                    items.push({
                        id: $(this).data('job-id'),
                        title: $(this).find('.item-title').text(),
                        company: $(this).find('.item-company').text()
                    });
                });
                pipeline[stageName] = items;
            });
            
            localStorage.setItem('sffc_pipeline', JSON.stringify(pipeline));
            this.updateMetrics();
        }
        
        updateMetrics() {
            // Calculate and update dashboard metrics
            const pipeline = JSON.parse(localStorage.getItem('sffc_pipeline') || '{}');
            
            let activeApplications = 0;
            let interviews = 0;
            
            if (pipeline.applied) activeApplications += pipeline.applied.length;
            if (pipeline.interviewing) interviews += pipeline.interviewing.length;
            
            $('.metric-card').eq(0).find('.metric-value').text(activeApplications);
            $('.metric-card').eq(1).find('.metric-value').text(interviews);
            
            // Calculate response rate
            const totalApplied = activeApplications + interviews;
            const responseRate = totalApplied > 0 ? Math.round((interviews / totalApplied) * 100) : 0;
            $('.metric-card').eq(2).find('.metric-value').text(responseRate + '%');
        }
        
        initializeTimeline() {
            // Create sample timeline
            const timeline = [
                {
                    date: new Date(),
                    title: 'Strategy Session Started',
                    type: 'milestone',
                    status: 'completed'
                },
                {
                    date: new Date(Date.now() + 7 * 24 * 60 * 60 * 1000),
                    title: 'Target: 5 Applications',
                    type: 'goal',
                    status: 'upcoming'
                },
                {
                    date: new Date(Date.now() + 14 * 24 * 60 * 60 * 1000),
                    title: 'First Interview Expected',
                    type: 'milestone',
                    status: 'upcoming'
                },
                {
                    date: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000),
                    title: 'Target: Offer Negotiation',
                    type: 'goal',
                    status: 'upcoming'
                }
            ];
            
            this.renderTimeline(timeline);
        }
        
        renderTimeline(timeline) {
            const container = $('#strategy-timeline');
            container.empty();
            
            timeline.forEach((item, index) => {
                const html = `
                    <div class="timeline-item ${item.status} ${item.type}">
                        <div class="timeline-marker">
                            <span class="marker-icon">${item.type === 'milestone' ? '◈' : '◉'}</span>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-date">
                                ${item.date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}
                            </div>
                            <div class="timeline-title">${item.title}</div>
                        </div>
                        ${index < timeline.length - 1 ? '<div class="timeline-connector"></div>' : ''}
                    </div>
                `;
                container.append(html);
            });
        }
        
        switchView(view) {
            this.currentView = view;
            
            // Update navigation
            $('.strategy-nav .nav-item').removeClass('active');
            $(`.strategy-nav .nav-item[data-view="${view}"]`).addClass('active');
            
            // Update content
            $('.strategy-view').removeClass('active');
            $(`.view-${view}`).addClass('active');
            
            // Load view-specific data if needed
            if (view === 'insights') {
                this.loadInsights();
            } else if (view === 'actions') {
                this.loadActions();
            }
        }
        
        loadInsights() {
            // Simulate loading market insights
            this.animateCharts();
        }
        
        loadActions() {
            const actions = [
                {
                    priority: 'high',
                    title: 'Update LinkedIn Headline',
                    description: 'Optimize for target role keywords',
                    deadline: '2 days',
                    impact: 'High'
                },
                {
                    priority: 'high',
                    title: 'Prepare STAR Stories',
                    description: 'Document 5 key achievement stories',
                    deadline: '3 days',
                    impact: 'Critical'
                },
                {
                    priority: 'medium',
                    title: 'Research Target Companies',
                    description: 'Deep dive on culture and values',
                    deadline: '1 week',
                    impact: 'Medium'
                },
                {
                    priority: 'low',
                    title: 'Update Portfolio',
                    description: 'Add recent project highlights',
                    deadline: '2 weeks',
                    impact: 'Low'
                }
            ];
            
            const container = $('#strategic-actions');
            container.empty();
            
            actions.forEach(action => {
                const html = `
                    <div class="action-item priority-${action.priority}">
                        <div class="action-marker">
                            <span class="marker-icon">${action.priority === 'high' ? '!' : action.priority === 'medium' ? '◈' : '○'}</span>
                        </div>
                        <div class="action-content">
                            <h4 class="action-title">${action.title}</h4>
                            <p class="action-description">${action.description}</p>
                            <div class="action-meta">
                                <span class="meta-deadline">⏱ ${action.deadline}</span>
                                <span class="meta-impact">Impact: ${action.impact}</span>
                            </div>
                        </div>
                        <div class="action-controls">
                            <button class="btn-complete">✓</button>
                            <button class="btn-defer">→</button>
                        </div>
                    </div>
                `;
                container.append(html);
            });
        }
        
        animateCharts() {
            // Animate trend chart
            const chart = $('#market-trend-chart');
            if (chart.length && !chart.hasClass('animated')) {
                chart.addClass('animated');
                // Add chart visualization here
            }
        }
        
        handleCardAction(card) {
            const type = card.attr('class').split(' ')[1];
            
            switch(type) {
                case 'positioning':
                    this.openPositioningWorkshop();
                    break;
                case 'networking':
                    this.openNetworkingStrategy();
                    break;
                case 'application':
                    this.openApplicationOptimizer();
                    break;
                case 'interview':
                    this.openInterviewPrep();
                    break;
            }
        }
        
        openPositioningWorkshop() {
            if (window.openSennaChat) {
                window.openSennaChat();
                // Send positioning prompt after chat opens
                setTimeout(() => {
                    if (window.sennaChat) {
                        window.sennaChat.sendMessage('Help me refine my market positioning and unique value proposition');
                    }
                }, 500);
            }
        }
        
        openNetworkingStrategy() {
            this.showToast('Network strategy workshop coming soon');
        }
        
        openApplicationOptimizer() {
            this.showToast('Application optimizer launching...');
        }
        
        openInterviewPrep() {
            this.showToast('Interview preparation module coming soon');
        }
        
        addMilestone() {
            // Show milestone creation dialog
            const dialog = `
                <div class="milestone-dialog">
                    <h3>Add Strategic Milestone</h3>
                    <input type="text" placeholder="Milestone title" class="milestone-title-input">
                    <input type="date" class="milestone-date-input">
                    <div class="dialog-actions">
                        <button class="btn-cancel">Cancel</button>
                        <button class="btn-save">Add Milestone</button>
                    </div>
                </div>
            `;
            
            // Implementation would show dialog and handle submission
            this.showToast('Milestone added to timeline');
        }
        
        exportStrategy() {
            const strategy = {
                date: new Date().toISOString(),
                metrics: this.gatherMetrics(),
                pipeline: JSON.parse(localStorage.getItem('sffc_pipeline') || '{}'),
                timeline: this.timeline,
                insights: this.insights
            };
            
            // Create downloadable JSON
            const blob = new Blob([JSON.stringify(strategy, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            
            const a = document.createElement('a');
            a.href = url;
            a.download = `career-strategy-${Date.now()}.json`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            
            this.showToast('Strategy exported successfully');
        }
        
        gatherMetrics() {
            return {
                activeApplications: $('.metric-card').eq(0).find('.metric-value').text(),
                scheduledInterviews: $('.metric-card').eq(1).find('.metric-value').text(),
                responseRate: $('.metric-card').eq(2).find('.metric-value').text()
            };
        }
        
        getAIRecommendations() {
            if (window.openSennaChat) {
                const pipeline = JSON.parse(localStorage.getItem('sffc_pipeline') || '{}');
                
                window.openSennaChat();
                // Send strategy prompt after chat opens
                setTimeout(() => {
                    if (window.sennaChat) {
                        window.sennaChat.sendMessage('Based on my current pipeline, what strategic actions should I prioritize this week?');
                    }
                }, 500);
            } else {
                this.showToast('AI recommendations loading...');
            }
        }
        
        filterPipeline(filter) {
            // Implementation for filtering pipeline items
            this.showToast(`Filtering by: ${filter}`);
        }
        
        open() {
            $('#strategy-dashboard').addClass('open');
            this.isOpen = true;
            $('body').addClass('strategy-open');
            this.loadStrategies();
            
            // Re-initialize drag and drop after dashboard opens
            setTimeout(() => {
                this.initializePipelineDragDrop();
            }, 300);
        }
        
        close() {
            $('#strategy-dashboard').removeClass('open');
            this.isOpen = false;
            $('body').removeClass('strategy-open');
        }
        
        showToast(message) {
            const toast = $(`
                <div class="strategy-toast">
                    ${message}
                </div>
            `);
            
            $('body').append(toast);
            
            setTimeout(() => {
                toast.addClass('show');
            }, 10);
            
            setTimeout(() => {
                toast.removeClass('show');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
    }
    
    // Initialize when ready
    $(document).ready(() => {
        window.strategyDashboard = new StrategyDashboard();
        
        // Add global function to open strategy dashboard
        window.openStrategyDashboard = function() {
            if (window.strategyDashboard) {
                window.strategyDashboard.open();
            } else {
                console.error('Strategy Dashboard not initialized');
            }
        };
        
        console.log('Strategy Dashboard initialized and ready');
        
        // Debug helper for testing drag and drop
        window.testStrategyDragDrop = function() {
            if (window.strategyDashboard) {
                // Open dashboard
                window.strategyDashboard.open();
                
                // Add test items after a delay
                setTimeout(() => {
                    const testJobs = [
                        {id: 1, title: 'Senior Developer', company: 'Tech Corp', match_score: 85},
                        {id: 2, title: 'Product Manager', company: 'StartupXYZ', match_score: 78},
                        {id: 3, title: 'Data Analyst', company: 'BigData Inc', match_score: 92}
                    ];
                    
                    window.strategyDashboard.populatePipeline(testJobs);
                    console.log('Test items added to pipeline. Try dragging them between stages!');
                }, 500);
            }
        };
    });
    
})(jQuery);