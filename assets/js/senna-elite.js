/**
 * MENA Careers Elite - Boutique PE × Tatler × Vogue Interface
 * Ultra-premium career strategy platform
 */

(function($) {
    'use strict';
    
    class SennaElite {
        constructor() {
            this.container = null;
            this.savedJobs = [];
            this.activeJob = null;
            this.messageHistory = [];
            this.userProfile = this.loadUserProfile();
            this.isTyping = false;
            
            this.init();
        }
        
        init() {
            this.loadSavedJobs();
            this.setupTrigger();
            this.bindGlobalEvents();
        }
        
        loadUserProfile() {
            const saved = localStorage.getItem('sffc_user_profile');
            if (saved) return JSON.parse(saved);
            
            // Check if user is logged in
            const isLoggedIn = window.sffc_frontend?.is_logged_in === '1';
            
            if (!isLoggedIn) {
                // Return login prompt instead of fake data
                return {
                    name: 'Login Required',
                    current: {
                        firm: 'Login to view profile',
                        role: 'Create your profile first',
                        years: 0,
                        deals: []
                    },
                    target: {
                        roles: ['Login to set targets'],
                        firms: ['Login to set preferences'],
                        compensation: { base: 0, total: 0 }
                    },
                    expertise: {
                        sectors: ['Login required'],
                        skills: ['Login required'],
                        languages: ['Login required']
                    },
                    education: {
                        mba: 'Login required',
                        undergrad: 'Login required'
                    },
                    requiresLogin: true
                };
            }
            
            // Return empty profile template for logged in users without profile
            return {
                name: 'Complete Your Profile',
                current: {
                    firm: 'Add your firm',
                    role: 'Add your role',
                    years: 0,
                    deals: []
                },
                target: {
                    roles: [],
                    firms: [],
                    compensation: { base: 0, total: 0 }
                },
                expertise: {
                    sectors: [],
                    skills: [],
                    languages: []
                },
                education: {
                    mba: '',
                    undergrad: ''
                },
                needsCompletion: true
            };
        }
        
        loadSavedJobs() {
            const saved = localStorage.getItem('sffc_saved_jobs');
            this.savedJobs = saved ? JSON.parse(saved) : this.getDefaultOpportunities();
        }
        
        getDefaultOpportunities() {
            // Check if user is logged in
            const isLoggedIn = window.sffc_frontend?.is_logged_in === '1';
            
            if (!isLoggedIn) {
                // Return placeholder that prompts login
                return [
                    {
                        id: 0,
                        firm: 'Login to View',
                        role: 'Premium opportunities available',
                        location: 'Multiple locations',
                        type: 'Various positions',
                        match: 'Login required',
                        compensation: { 
                            base: 'Login to reveal', 
                            bonus: 'Login to reveal', 
                            carry: 'Login to reveal' 
                        },
                        highlights: [
                            'Create your profile to see matches',
                            'Get personalized recommendations',
                            'Access exclusive opportunities'
                        ],
                        requiresLogin: true
                    }
                ];
            }
            
            // For logged in users, return empty array - real opportunities should be loaded from backend
            return [];
        }
        
        setupTrigger() {
            // Remove any existing triggers
            $('.sffc-senna-trigger-premium').remove();
            
            const trigger = $(`
                <button class="sffc-senna-trigger-premium" aria-label="Open MENA Careers Elite">
                    <img src="${window.sffc_frontend?.plugin_url || ''}assets/images/senna.jpeg" 
                         alt="MENA Careers" 
                         onerror="this.style.display='none'; this.parentElement.innerHTML='<span class=\\'sffc-trigger-initial\\'>S</span>';">
                </button>
            `);
            
            $('body').append(trigger);
            trigger.on('click', () => this.open());
        }
        
        open() {
            if (this.container?.hasClass('active')) return;
            
            this.createInterface();
            this.container.addClass('active');
            this.loadOpportunityCards();
            this.showWelcomeAnalysis();
            
            setTimeout(() => {
                this.container.find('.sffc-elite-input').focus();
            }, 600);
        }
        
        createInterface() {
            if (this.container) return;
            
            const html = `
                <div class="sffc-senna-elite-container sffc-senna-elite">
                    <div class="sffc-elite-wrapper">
                        <!-- Floating Sidebar -->
                        <aside class="sffc-elite-sidebar">
                            <div class="sffc-sidebar-header">
                                <div class="sffc-sidebar-brand">
                                    <div class="sffc-brand-icon">S</div>
                                    <div>
                                        <h2 class="sffc-sidebar-title">Strategic Pipeline</h2>
                                        <p class="sffc-sidebar-subtitle">Curated opportunities</p>
                                    </div>
                                </div>
                            </div>
                            <div class="sffc-opportunities-scroll">
                                <!-- Opportunity cards -->
                            </div>
                        </aside>
                        
                        <!-- Main Content -->
                        <main class="sffc-elite-main">
                            <header class="sffc-elite-header">
                                <div class="sffc-header-left">
                                    <div class="sffc-senna-avatar">
                                        <img src="${window.sffc_frontend?.plugin_url || ''}assets/images/senna.jpeg" 
                                             alt="MENA Careers"
                                             onerror="this.style.display='none'; this.parentElement.innerHTML='<span>S</span>';">
                                    </div>
                                    <div class="sffc-header-text">
                                        <h1>MENA Careers</h1>
                                        <p>Executive Career Strategist</p>
                                    </div>
                                </div>
                                <button class="sffc-close-elite">×</button>
                            </header>
                            
                            <div class="sffc-elite-messages">
                                <!-- Analysis cards -->
                            </div>
                            
                            <div class="sffc-elite-input-area">
                                <div class="sffc-input-wrapper">
                                    <textarea class="sffc-elite-input" 
                                              placeholder="Ask about specific firms, compensation strategies, or interview preparation..."
                                              rows="1"></textarea>
                                    <button class="sffc-send-elite">→</button>
                                </div>
                            </div>
                        </main>
                    </div>
                </div>
            `;
            
            $('body').append(html);
            this.container = $('.sffc-senna-elite-container');
            this.bindEvents();
        }
        
        bindEvents() {
            const c = this.container;
            
            // Close
            c.find('.sffc-close-elite').on('click', () => this.close());
            
            // Send message
            c.find('.sffc-send-elite').on('click', () => this.sendMessage());
            
            // Enter key
            c.find('.sffc-elite-input').on('keypress', (e) => {
                if (e.which === 13 && !e.shiftKey) {
                    e.preventDefault();
                    this.sendMessage();
                }
            });
            
            // Auto-resize
            c.find('.sffc-elite-input').on('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            });
            
            // Opportunity card clicks
            c.on('click', '.sffc-opp-card', (e) => {
                const card = $(e.currentTarget);
                const id = card.data('id');
                const opp = this.savedJobs.find(o => o.id == id);
                
                if (opp) {
                    card.addClass('active').siblings().removeClass('active');
                    this.analyzeOpportunity(opp);
                }
            });
        }
        
        loadOpportunityCards() {
            const container = this.container.find('.sffc-opportunities-scroll');
            container.empty();
            
            if (this.savedJobs.length === 0) {
                container.html(`
                    <div style="padding: 20px; text-align: center; color: #7C8471;">
                        <p>Start learning to build your PE knowledge</p>
                    </div>
                `);
                return;
            }
            
            this.savedJobs.forEach(opp => {
                const card = `
                    <div class="sffc-opp-card" data-id="${opp.id}">
                        <div class="sffc-opp-firm">${opp.firm}</div>
                        <div class="sffc-opp-role">${opp.role}</div>
                        <div class="sffc-opp-details">
                            <span>📍 ${opp.location}</span>
                            <span>💼 ${opp.type}</span>
                        </div>
                        <div class="sffc-opp-match">
                            <div class="sffc-match-bar">
                                <div class="sffc-match-fill" style="width: ${opp.match}%"></div>
                            </div>
                            <div class="sffc-match-percent">${opp.match}%</div>
                        </div>
                    </div>
                `;
                container.append(card);
            });
        }
        
        showWelcomeAnalysis() {
            const analysis = `
                <div class="sffc-analysis-card">
                    <div class="sffc-analysis-header">
                        <h2 class="sffc-analysis-title">Your Strategic Position</h2>
                        <p class="sffc-analysis-subtitle">Executive opportunity analysis for ${this.userProfile.current.firm} ${this.userProfile.current.role}</p>
                    </div>
                    
                    <div class="sffc-metrics-grid">
                        <div class="sffc-metric-card">
                            <div class="sffc-metric-value">${this.savedJobs.length}</div>
                            <div class="sffc-metric-label">Active Opportunities</div>
                        </div>
                        <div class="sffc-metric-card">
                            <div class="sffc-metric-value">${Math.round(this.savedJobs.reduce((a, o) => a + o.match, 0) / this.savedJobs.length)}%</div>
                            <div class="sffc-metric-label">Average Match</div>
                        </div>
                        <div class="sffc-metric-card">
                            <div class="sffc-metric-value">$${this.userProfile.target.compensation.base}k</div>
                            <div class="sffc-metric-label">Target Base</div>
                        </div>
                        <div class="sffc-metric-card">
                            <div class="sffc-metric-value">2-3</div>
                            <div class="sffc-metric-label">Weeks to Offer</div>
                        </div>
                    </div>
                    
                    <div class="sffc-strategy-section">
                        <div class="sffc-section-header">
                            <div class="sffc-section-icon">🎯</div>
                            <h3 class="sffc-section-title">Market Intelligence</h3>
                        </div>
                        <div class="sffc-strategy-content">
                            <p class="sffc-strategy-text">
                                Based on your <span class="sffc-highlight-text">${this.userProfile.current.years} years at ${this.userProfile.current.firm}</span>, 
                                you're perfectly positioned for senior PE roles. The market is particularly active for professionals with your 
                                <span class="sffc-highlight-text">$2.3B deal experience</span>.
                            </p>
                            <ul class="sffc-strategy-list">
                                <li>Blackstone and KKR are actively hiring for tech-focused investment professionals</li>
                                <li>Your cross-border M&A experience is highly valued in current market</li>
                                <li>Q1 timing is optimal - budget approvals and strategic planning align</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="sffc-strategy-cards">
                        <div class="sffc-strategy-card">
                            <h4>Immediate Action</h4>
                            <p>Schedule exploratory calls with PE partners in your network. Position as "market research" rather than job seeking.</p>
                        </div>
                        <div class="sffc-strategy-card">
                            <h4>Positioning Strategy</h4>
                            <p>Lead with operational value creation experience. PE firms value ex-bankers who understand portfolio operations.</p>
                        </div>
                        <div class="sffc-strategy-card">
                            <h4>Compensation Approach</h4>
                            <p>Target 20% base increase plus carry participation. Use multiple offers to drive competitive dynamics.</p>
                        </div>
                    </div>
                </div>
            `;
            
            this.container.find('.sffc-elite-messages').html(analysis);
        }
        
        analyzeOpportunity(opp) {
            this.activeJob = opp;
            
            const analysis = `
                <div class="sffc-analysis-card">
                    <div class="sffc-analysis-header">
                        <h2 class="sffc-analysis-title">${opp.firm} ${opp.role}</h2>
                        <p class="sffc-analysis-subtitle">Strategic analysis & positioning guidance</p>
                    </div>
                    
                    <div class="sffc-strategy-section">
                        <div class="sffc-section-header">
                            <div class="sffc-section-icon">🎯</div>
                            <h3 class="sffc-section-title">Your Competitive Edge</h3>
                        </div>
                        <div class="sffc-strategy-content">
                            <ul class="sffc-strategy-list">
                                <li><strong>Goldman M&A Foundation:</strong> Your experience on the <span class="sffc-highlight-text">$2.3B Refinitiv deal</span> directly demonstrates the transaction scale and complexity ${opp.firm} values</li>
                                <li><strong>Cross-Border Expertise:</strong> Having worked on 3 international deals positions you for ${opp.firm}'s global investment strategy</li>
                                <li><strong>Sector Diversification:</strong> Your exposure to tech, healthcare, and financial services aligns with their portfolio approach</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="sffc-strategy-section">
                        <div class="sffc-section-header">
                            <div class="sffc-section-icon">📋</div>
                            <h3 class="sffc-section-title">Winning Application Strategy</h3>
                        </div>
                        <div class="sffc-strategy-content">
                            <ul class="sffc-strategy-list">
                                <li><strong>Lead with Scale:</strong> Open your application highlighting the <span class="sffc-highlight-text">$2.3B deal size</span> to immediately establish credibility</li>
                                <li><strong>Emphasize Execution:</strong> Detail your role in managing due diligence for 3 concurrent deals</li>
                                <li><strong>Connect to Portfolio:</strong> Research ${opp.firm}'s recent tech investments and draw parallels to your Refinitiv experience</li>
                                <li><strong>Address PE Gap:</strong> Position your IB background as bringing "fresh perspective on value creation" rather than as a limitation</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="sffc-strategy-section">
                        <div class="sffc-section-header">
                            <div class="sffc-section-icon">🎪</div>
                            <h3 class="sffc-section-title">Interview Preparation Priorities</h3>
                        </div>
                        <div class="sffc-strategy-content">
                            <ul class="sffc-strategy-list">
                                <li><strong>Technical Mastery:</strong> Be ready to walk through LBO modeling for a ${opp.firm}-style deal ($1B+ enterprise value)</li>
                                <li><strong>Portfolio Knowledge:</strong> Study 3 recent ${opp.firm} investments and prepare value creation ideas</li>
                                <li><strong>Cultural Fit:</strong> Emphasize your ability to work in high-pressure, deal-focused environment</li>
                                <li><strong>Case Study Prep:</strong> Practice with live deals similar to ${opp.firm}'s recent transactions</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="sffc-metrics-grid">
                        <div class="sffc-metric-card">
                            <div class="sffc-metric-value">$${opp.compensation.base}k</div>
                            <div class="sffc-metric-label">Base Salary</div>
                        </div>
                        <div class="sffc-metric-card">
                            <div class="sffc-metric-value">$${opp.compensation.bonus}k</div>
                            <div class="sffc-metric-label">Target Bonus</div>
                        </div>
                        <div class="sffc-metric-card">
                            <div class="sffc-metric-value">${opp.compensation.carry}</div>
                            <div class="sffc-metric-label">Carry Participation</div>
                        </div>
                        <div class="sffc-metric-card">
                            <div class="sffc-metric-value">${opp.match}%</div>
                            <div class="sffc-metric-label">Profile Match</div>
                        </div>
                    </div>
                    
                    <div class="sffc-strategy-cards">
                        <div class="sffc-strategy-card">
                            <h4>Network Activation</h4>
                            <p>Leverage your Goldman network - 3 MDs have moved to ${opp.firm} in the last 2 years. Request informal coffee chats.</p>
                        </div>
                        <div class="sffc-strategy-card">
                            <h4>Timeline Strategy</h4>
                            <p>Apply within 48 hours. ${opp.firm} typically moves fast - expect 3 rounds over 2 weeks.</p>
                        </div>
                        <div class="sffc-strategy-card">
                            <h4>Negotiation Leverage</h4>
                            <p>Your profile commands top quartile comp. Use competing offers to push for $${opp.compensation.base + 25}k base.</p>
                        </div>
                    </div>
                </div>
            `;
            
            this.addMessage('analysis', analysis);
        }
        
        sendMessage() {
            const input = this.container.find('.sffc-elite-input');
            const message = input.val().trim();
            
            if (!message) return;
            
            // Add user message
            this.addMessage('user', message);
            input.val('').css('height', 'auto');
            
            // Show typing
            this.showTyping();
            
            // Process after delay
            setTimeout(() => {
                this.processMessage(message);
            }, 1500);
        }
        
        processMessage(message) {
            this.hideTyping();
            
            const msgLower = message.toLowerCase();
            
            if (msgLower.includes('compare') || msgLower.includes('vs')) {
                this.showComparison();
            } else if (msgLower.includes('salary') || msgLower.includes('compensation')) {
                this.showCompensationAnalysis();
            } else if (msgLower.includes('interview') || msgLower.includes('prepare')) {
                this.showInterviewStrategy();
            } else if (msgLower.includes('network')) {
                this.showNetworkingStrategy();
            } else {
                this.showStrategicResponse(message);
            }
        }
        
        showComparison() {
            const analysis = `
                <div class="sffc-analysis-card">
                    <div class="sffc-analysis-header">
                        <h2 class="sffc-analysis-title">Comparative Opportunity Analysis</h2>
                        <p class="sffc-analysis-subtitle">Strategic assessment of your top prospects</p>
                    </div>
                    
                    ${this.savedJobs.slice(0, 3).map(opp => `
                        <div class="sffc-strategy-section">
                            <div class="sffc-section-header">
                                <div class="sffc-section-icon" style="background: linear-gradient(135deg, #C9A55F ${opp.match}%, #E8D5C4 100%);">${opp.match}%</div>
                                <h3 class="sffc-section-title">${opp.firm}</h3>
                            </div>
                            <div class="sffc-strategy-content">
                                <p class="sffc-strategy-text">
                                    <strong>${opp.role}</strong> • ${opp.location} • ${opp.type}
                                </p>
                                <ul class="sffc-strategy-list">
                                    ${opp.highlights.map(h => `<li>${h}</li>`).join('')}
                                    <li><strong>Total Comp:</strong> $${opp.compensation.base + opp.compensation.bonus}k + ${opp.compensation.carry}</li>
                                </ul>
                            </div>
                        </div>
                    `).join('')}
                    
                    <div class="sffc-strategy-cards">
                        <div class="sffc-strategy-card">
                            <h4>Recommendation</h4>
                            <p>Prioritize Blackstone for brand and scale, KKR for immediate seniority, Apollo for operational focus.</p>
                        </div>
                        <div class="sffc-strategy-card">
                            <h4>Negotiation Strategy</h4>
                            <p>Secure offers from 2+ firms. Use Blackstone's brand to drive KKR/Apollo compensation up 15-20%.</p>
                        </div>
                    </div>
                </div>
            `;
            
            this.addMessage('analysis', analysis);
        }
        
        showCompensationAnalysis() {
            const analysis = `
                <div class="sffc-analysis-card">
                    <div class="sffc-analysis-header">
                        <h2 class="sffc-analysis-title">Compensation Strategy Framework</h2>
                        <p class="sffc-analysis-subtitle">Maximizing your economic outcome</p>
                    </div>
                    
                    <div class="sffc-metrics-grid">
                        <div class="sffc-metric-card">
                            <div class="sffc-metric-value">$275k</div>
                            <div class="sffc-metric-label">Market Base</div>
                        </div>
                        <div class="sffc-metric-card">
                            <div class="sffc-metric-value">$450k</div>
                            <div class="sffc-metric-label">Total Cash</div>
                        </div>
                        <div class="sffc-metric-card">
                            <div class="sffc-metric-value">2-3%</div>
                            <div class="sffc-metric-label">Carry Points</div>
                        </div>
                        <div class="sffc-metric-card">
                            <div class="sffc-metric-value">$2-5M</div>
                            <div class="sffc-metric-label">5-Year Value</div>
                        </div>
                    </div>
                    
                    <div class="sffc-strategy-section">
                        <div class="sffc-section-header">
                            <div class="sffc-section-icon">💰</div>
                            <h3 class="sffc-section-title">Negotiation Playbook</h3>
                        </div>
                        <div class="sffc-strategy-content">
                            <ul class="sffc-strategy-list">
                                <li><strong>Opening Position:</strong> Request <span class="sffc-highlight-text">$300k base</span> citing Goldman VP comp and competing offers</li>
                                <li><strong>Bonus Structure:</strong> Push for guaranteed first-year bonus of <span class="sffc-highlight-text">75% of base</span></li>
                                <li><strong>Carry Economics:</strong> Negotiate for immediate vesting rather than waiting for VP promotion</li>
                                <li><strong>Sign-On Package:</strong> Request $100k sign-on to offset Goldman deferred comp</li>
                                <li><strong>Co-Investment:</strong> Secure rights to co-invest personal capital in deals</li>
                            </ul>
                        </div>
                    </div>
                </div>
            `;
            
            this.addMessage('analysis', analysis);
        }
        
        showInterviewStrategy() {
            const analysis = `
                <div class="sffc-analysis-card">
                    <div class="sffc-analysis-header">
                        <h2 class="sffc-analysis-title">Elite Interview Preparation</h2>
                        <p class="sffc-analysis-subtitle">Mastering the PE interview process</p>
                    </div>
                    
                    <div class="sffc-strategy-section">
                        <div class="sffc-section-header">
                            <div class="sffc-section-icon">🎭</div>
                            <h3 class="sffc-section-title">Round 1: Partner Screen (30 min)</h3>
                        </div>
                        <div class="sffc-strategy-content">
                            <ul class="sffc-strategy-list">
                                <li><strong>Walk me through your background:</strong> 2-minute story arc from undergrad to PE readiness</li>
                                <li><strong>Why PE/Why us:</strong> Specific thesis on their investment strategy and your value-add</li>
                                <li><strong>Deal discussion:</strong> Deep dive on your $2.3B Refinitiv transaction</li>
                                <li><strong>Market views:</strong> Prepared perspective on 2-3 sectors they invest in</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="sffc-strategy-section">
                        <div class="sffc-section-header">
                            <div class="sffc-section-icon">📊</div>
                            <h3 class="sffc-section-title">Round 2: Technical Deep Dive (2-3 hours)</h3>
                        </div>
                        <div class="sffc-strategy-content">
                            <ul class="sffc-strategy-list">
                                <li><strong>LBO Model Test:</strong> Build full 3-statement model with returns analysis</li>
                                <li><strong>Case Study:</strong> Investment memo on live opportunity in their pipeline</li>
                                <li><strong>Value Creation:</strong> Operational improvement ideas for portfolio company</li>
                                <li><strong>Technical Questions:</strong> Capital structure, debt capacity, exit strategies</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="sffc-strategy-cards">
                        <div class="sffc-strategy-card">
                            <h4>48-Hour Prep Sprint</h4>
                            <p>Day 1: Review 10 recent deals, build investment thesis. Day 2: Practice 3 LBO models, prepare 5 deal stories.</p>
                        </div>
                        <div class="sffc-strategy-card">
                            <h4>Differentiation Strategy</h4>
                            <p>Bring printed investment memo for company in their sector. Shows proactive thinking and PE mindset.</p>
                        </div>
                    </div>
                </div>
            `;
            
            this.addMessage('analysis', analysis);
        }
        
        showNetworkingStrategy() {
            const analysis = `
                <div class="sffc-analysis-card">
                    <div class="sffc-analysis-header">
                        <h2 class="sffc-analysis-title">Strategic Network Activation</h2>
                        <p class="sffc-analysis-subtitle">Leveraging relationships for PE transition</p>
                    </div>
                    
                    <div class="sffc-strategy-section">
                        <div class="sffc-section-header">
                            <div class="sffc-section-icon">🔗</div>
                            <h3 class="sffc-section-title">High-Value Connections</h3>
                        </div>
                        <div class="sffc-strategy-content">
                            <ul class="sffc-strategy-list">
                                <li><strong>Goldman → Blackstone Pipeline:</strong> 12 MDs/Partners made this move in last 3 years</li>
                                <li><strong>Harvard Business School Network:</strong> 47 alumni at target PE firms</li>
                                <li><strong>Deal Counterparties:</strong> PE professionals from your transaction history</li>
                                <li><strong>Board Connections:</strong> Directors who sit on PE portfolio company boards</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="sffc-strategy-cards">
                        <div class="sffc-strategy-card">
                            <h4>Outreach Template</h4>
                            <p>"Exploring PE after 7 years at GS. Would value 15 min to hear your transition experience. Coffee on me?"</p>
                        </div>
                        <div class="sffc-strategy-card">
                            <h4>Activation Timeline</h4>
                            <p>Week 1: 10 coffee chats. Week 2: 5 partner intros. Week 3: 3 formal interviews.</p>
                        </div>
                    </div>
                </div>
            `;
            
            this.addMessage('analysis', analysis);
        }
        
        showStrategicResponse(message) {
            const response = `
                <div class="sffc-message sffc-message-senna">
                    <div class="senna-avatar">
                        <img src="${window.sffc_frontend?.plugin_url || window.sffc_ajax?.plugin_url || ''}assets/images/senna.jpeg" 
                             alt="MENA Careers" 
                             onerror="this.style.display='none'; this.parentElement.innerHTML='S';">
                    </div>
                    <div class="sffc-msg-content">
                        Based on your profile as a ${this.userProfile.current.role} at ${this.userProfile.current.firm}, 
                        I'll provide targeted guidance on "${message}". Your ${this.userProfile.current.years} years of experience 
                        and ${this.userProfile.current.deals[0]} deal positions you exceptionally well for PE opportunities.
                        
                        Would you like me to analyze specific aspects of your transition strategy?
                    </div>
                </div>
            `;
            
            this.container.find('.sffc-elite-messages').append(response);
            this.scrollToBottom();
        }
        
        addMessage(type, content) {
            const container = this.container.find('.sffc-elite-messages');
            
            if (type === 'user') {
                const msg = `
                    <div class="sffc-message sffc-message-user">
                        <div class="sffc-msg-content">${content}</div>
                    </div>
                `;
                container.append(msg);
            } else {
                container.append(content);
            }
            
            this.scrollToBottom();
        }
        
        showTyping() {
            if (this.isTyping) return;
            this.isTyping = true;
            
            const typing = `
                <div class="sffc-typing-indicator">
                    <div class="sffc-typing-dots">
                        <span></span><span></span><span></span>
                    </div>
                </div>
            `;
            
            this.container.find('.sffc-elite-messages').append(typing);
            this.scrollToBottom();
        }
        
        hideTyping() {
            this.isTyping = false;
            this.container.find('.sffc-typing-indicator').remove();
        }
        
        scrollToBottom() {
            const messages = this.container.find('.sffc-elite-messages');
            messages.scrollTop(messages[0].scrollHeight);
        }
        
        close() {
            this.container.removeClass('active');
            setTimeout(() => {
                this.container.remove();
                this.container = null;
            }, 500);
        }
        
        bindGlobalEvents() {
            $(document).on('sffc:saved_jobs:updated', () => {
                this.loadSavedJobs();
                if (this.container) {
                    this.loadOpportunityCards();
                }
            });
            
            $(document).on('click', '.sffc-analyze-btn', () => {
                this.open();
            });
        }
    }
    
    // Initialize
    $(document).ready(() => {
        window.sennaElite = new SennaElite();
        
        // Override the premium chat
        window.sennaPremiumChat = window.sennaElite;
    });
    
})(jQuery);