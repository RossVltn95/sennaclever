/**
 * MENA Careers Quantum - Dynamic Executive-Level Interface
 * Clean unified chat with real data flow
 */

(function($) {
    'use strict';
    
    class SennaQuantum {
        constructor() {
            this.container = null;
            this.shortlist = [];
            this.activeJob = null;
            this.messages = [];
            this.isTyping = false;
            this.typingQueue = [];
            
            this.init();
        }
        
        init() {
            this.loadShortlist();
            this.setupTrigger();
            this.bindGlobalEvents();
        }
        
        loadShortlist() {
            // Load from localStorage
            const saved = localStorage.getItem('sffc_shortlist');
            if (saved) {
                try {
                    this.shortlist = JSON.parse(saved);
                    console.log('Loaded shortlist:', this.shortlist);
                } catch (e) {
                    console.error('Failed to parse shortlist:', e);
                    this.shortlist = [];
                }
            }
        }
        
        setupTrigger() {
            // Remove existing triggers to avoid duplicates
            $('.senna-quantum-trigger').remove();
            
            const trigger = $(`
                <button class="senna-quantum-trigger" aria-label="Open MENA Careers">
                    <img src="${window.sffc_frontend?.plugin_url || ''}assets/images/senna.jpeg"
                         alt="MENA Careers"
                         onerror="this.style.display='none'; this.parentElement.innerHTML='S';">
                </button>
            `);
            
            $('body').append(trigger);
            trigger.on('click', () => this.open());
        }
        
        open() {
            if (this.container?.hasClass('active')) return;
            
            this.createInterface();
            this.container.addClass('active');
            this.loadOpportunities();
            
            // Start conversation
            setTimeout(() => {
                this.startConversation();
            }, 500);
        }
        
        createInterface() {
            if (this.container) return;
            
            const html = `
                <div class="senna-quantum-container">
                    <div class="quantum-layout">
                        <!-- Sidebar -->
                        <aside class="quantum-sidebar">
                            <div class="quantum-sidebar-header">
                                <div class="quantum-sidebar-title">Opportunities</div>
                                <div class="quantum-sidebar-count">${this.shortlist.length}</div>
                            </div>
                            <div class="quantum-opportunities">
                                <!-- Dynamic opportunities -->
                            </div>
                        </aside>
                        
                        <!-- Main Chat -->
                        <main class="quantum-main">
                            <header class="quantum-header">
                                <div class="quantum-header-left">
                                    <div class="quantum-avatar">
                                        <img src="${window.sffc_frontend?.plugin_url || ''}assets/images/senna.jpeg"
                                             alt="MENA Careers"
                                             onerror="this.style.display='none'; this.parentElement.innerHTML='S';">
                                    </div>
                                    <div class="quantum-header-info">
                                        <h1>MENA Careers</h1>
                                        <p>Career Intelligence</p>
                                    </div>
                                </div>
                                <button class="quantum-close">×</button>
                            </header>
                            
                            <div class="quantum-chat">
                                <!-- Dynamic messages -->
                            </div>
                            
                            <div class="quantum-input-area">
                                <div class="quantum-input-wrapper">
                                    <textarea class="quantum-input" 
                                              placeholder="Ask about roles, skills, or strategies..."
                                              rows="1"></textarea>
                                    <button class="quantum-send">→</button>
                                </div>
                            </div>
                        </main>
                    </div>
                </div>
            `;
            
            $('body').append(html);
            this.container = $('.senna-quantum-container');
            this.bindEvents();
        }
        
        bindEvents() {
            const c = this.container;
            
            // Close
            c.find('.quantum-close').on('click', () => this.close());
            
            // Send message
            c.find('.quantum-send').on('click', () => this.sendMessage());
            
            // Enter key
            c.find('.quantum-input').on('keypress', (e) => {
                if (e.which === 13 && !e.shiftKey) {
                    e.preventDefault();
                    this.sendMessage();
                }
            });
            
            // Auto-resize input
            c.find('.quantum-input').on('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            });
            
            // Opportunity clicks
            c.on('click', '.quantum-opp-card', (e) => {
                const card = $(e.currentTarget);
                const index = card.data('index');
                
                if (index !== undefined && this.shortlist[index]) {
                    this.selectOpportunity(index);
                }
            });
        }
        
        loadOpportunities() {
            const container = this.container.find('.quantum-opportunities');
            container.empty();
            
            if (this.shortlist.length === 0) {
                container.html(`
                    <div style="padding: 20px; text-align: center; color: #6C7A89;">
                        <p>No opportunities shortlisted</p>
                    </div>
                `);
                return;
            }
            
            this.shortlist.forEach((job, index) => {
                // Extract data safely
                const company = job.company || job.organization || 'Company';
                const title = job.title || job.position || 'Position';
                const location = job.location || job.city || 'Location';
                const type = job.type || job.employment_type || 'Full-time';
                const matchScore = job.match_score || job.score || 75;
                
                const card = `
                    <div class="quantum-opp-card" data-index="${index}">
                        <div class="quantum-opp-company">${this.escapeHtml(company)}</div>
                        <div class="quantum-opp-title">${this.escapeHtml(title)}</div>
                        <div class="quantum-opp-meta">
                            <span>${this.escapeHtml(location)}</span>
                            <span>${this.escapeHtml(type)}</span>
                        </div>
                        <div class="quantum-opp-match">
                            <div class="quantum-match-bar">
                                <div class="quantum-match-fill" style="width: ${matchScore}%"></div>
                            </div>
                            <div class="quantum-match-score">${matchScore}%</div>
                        </div>
                    </div>
                `;
                container.append(card);
            });
        }
        
        selectOpportunity(index) {
            const job = this.shortlist[index];
            if (!job) return;
            
            // Update active state
            this.container.find('.quantum-opp-card').removeClass('active');
            this.container.find(`.quantum-opp-card[data-index="${index}"]`).addClass('active');
            
            this.activeJob = job;
            this.analyzeRole(job);
        }
        
        startConversation() {
            if (this.shortlist.length > 0) {
                this.addSennaMessage("I see you have " + this.shortlist.length + " opportunities in your pipeline. Let me analyze these roles and show you how to position yourself effectively.");
                
                setTimeout(() => {
                    this.selectOpportunity(0);
                }, 1500);
            } else {
                this.addSennaMessage("Welcome! I'm here to help you navigate your career opportunities. Start by shortlisting roles from the opportunities page, and I'll provide strategic analysis and positioning guidance.");
            }
        }
        
        analyzeRole(job) {
            // Extract job data
            const company = job.company || job.organization || 'the company';
            const title = job.title || job.position || 'this role';
            const location = job.location || job.city || 'Not specified';
            const salary = job.salary || job.compensation || { min: 100000, max: 150000 };
            const requirements = job.requirements || job.qualifications || [];
            const description = job.description || job.summary || '';
            const matchScore = job.match_score || job.score || 75;
            
            // Start typing
            this.showTyping();
            
            setTimeout(() => {
                this.hideTyping();
                
                // Role overview
                this.addSennaMessage(`Let me analyze the **${title}** role at **${company}**.`);
                
                // Add metrics card
                this.addMetricsCard(job);
                
                // Key requirements analysis
                setTimeout(() => {
                    this.showTyping();
                    setTimeout(() => {
                        this.hideTyping();
                        this.addRequirementsAnalysis(job);
                    }, 1200);
                }, 800);
                
                // Skills matching
                setTimeout(() => {
                    this.showTyping();
                    setTimeout(() => {
                        this.hideTyping();
                        this.addSkillsAnalysis(job);
                    }, 1200);
                }, 2000);
                
                // Application strategy
                setTimeout(() => {
                    this.showTyping();
                    setTimeout(() => {
                        this.hideTyping();
                        this.addApplicationStrategy(job);
                    }, 1200);
                }, 3500);
                
            }, 1500);
        }
        
        addMetricsCard(job) {
            const salary = this.extractSalary(job);
            const matchScore = job.match_score || job.score || 75;
            const level = this.extractLevel(job);
            const timeline = this.estimateTimeline(job);
            
            const card = `
                <div class="quantum-card">
                    <div class="quantum-card-title">Role Snapshot</div>
                    <div class="quantum-metrics">
                        <div class="quantum-metric">
                            <div class="quantum-metric-value">${matchScore}%</div>
                            <div class="quantum-metric-label">Match</div>
                        </div>
                        <div class="quantum-metric">
                            <div class="quantum-metric-value">$${Math.round(salary.max/1000)}k</div>
                            <div class="quantum-metric-label">Max Comp</div>
                        </div>
                        <div class="quantum-metric">
                            <div class="quantum-metric-value">${level}</div>
                            <div class="quantum-metric-label">Level</div>
                        </div>
                        <div class="quantum-metric">
                            <div class="quantum-metric-value">${timeline}</div>
                            <div class="quantum-metric-label">Timeline</div>
                        </div>
                    </div>
                </div>
            `;
            
            this.addToChat(card);
        }
        
        addRequirementsAnalysis(job) {
            const requirements = this.extractRequirements(job);
            const title = job.title || 'this role';
            
            let message = `Here are the key requirements for ${title}:\n\n`;
            
            requirements.forEach((req, i) => {
                message += `${i+1}. ${req}\n`;
            });
            
            this.addSennaMessage(message);
        }
        
        addSkillsAnalysis(job) {
            const userSkills = this.getUserSkills();
            const jobSkills = this.extractSkills(job);
            
            const matches = [];
            const gaps = [];
            
            jobSkills.forEach(skill => {
                if (userSkills.some(s => this.skillMatch(s, skill))) {
                    matches.push(skill);
                } else {
                    gaps.push(skill);
                }
            });
            
            const card = `
                <div class="quantum-card">
                    <div class="quantum-card-title">Skills Analysis</div>
                    <div class="quantum-skills-card">
                        ${matches.map(skill => `
                            <div class="quantum-skill-item">
                                <div class="quantum-skill-icon match">✓</div>
                                <div class="quantum-skill-info">
                                    <div class="quantum-skill-name">${skill}</div>
                                    <div class="quantum-skill-desc">You have this skill</div>
                                </div>
                            </div>
                        `).join('')}
                        ${gaps.slice(0, 3).map(skill => `
                            <div class="quantum-skill-item">
                                <div class="quantum-skill-icon gap">!</div>
                                <div class="quantum-skill-info">
                                    <div class="quantum-skill-name">${skill}</div>
                                    <div class="quantum-skill-desc">Address in application</div>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
            
            this.addToChat(card);
            
            if (gaps.length > 0) {
                this.addSennaMessage(`You match ${matches.length} out of ${jobSkills.length} key skills. For the gaps, emphasize transferable skills and learning agility.`);
            } else {
                this.addSennaMessage(`Excellent match! You have all the key skills required for this role.`);
            }
        }
        
        addApplicationStrategy(job) {
            const company = job.company || 'the company';
            const title = job.title || 'this role';
            
            const strategies = [
                {
                    title: "Lead with impact",
                    desc: `Open with quantifiable achievements relevant to ${company}'s needs`
                },
                {
                    title: "Mirror the language",
                    desc: "Use keywords from the job description throughout your application"
                },
                {
                    title: "Show cultural fit",
                    desc: `Research ${company}'s values and weave them into your narrative`
                },
                {
                    title: "Address gaps proactively",
                    desc: "Frame any missing requirements as opportunities for growth"
                },
                {
                    title: "Close with enthusiasm",
                    desc: `Express specific interest in ${company}'s mission and recent initiatives`
                }
            ];
            
            const card = `
                <div class="quantum-card">
                    <div class="quantum-card-title">Application Strategy</div>
                    <ul class="quantum-strategy-list">
                        ${strategies.map((s, i) => `
                            <li class="quantum-strategy-item">
                                <div class="quantum-strategy-number">${i+1}</div>
                                <div class="quantum-strategy-content">
                                    <div class="quantum-strategy-title">${s.title}</div>
                                    <div class="quantum-strategy-desc">${s.desc}</div>
                                </div>
                            </li>
                        `).join('')}
                    </ul>
                </div>
            `;
            
            this.addToChat(card);
            this.addSennaMessage(`With this approach, you'll position yourself as a top-tier candidate for ${title}.`);
        }
        
        sendMessage() {
            const input = this.container.find('.quantum-input');
            const message = input.val().trim();
            
            if (!message) return;
            
            this.addUserMessage(message);
            input.val('').css('height', 'auto');
            
            // Process message
            this.showTyping();
            setTimeout(() => {
                this.processUserMessage(message);
            }, 1000);
        }
        
        processUserMessage(message) {
            this.hideTyping();
            
            const msgLower = message.toLowerCase();
            
            if (msgLower.includes('salary') || msgLower.includes('compensation')) {
                this.discussCompensation();
            } else if (msgLower.includes('interview')) {
                this.discussInterview();
            } else if (msgLower.includes('compare')) {
                this.compareRoles();
            } else if (msgLower.includes('skills')) {
                this.discussSkills();
            } else {
                this.addSennaMessage(`I understand you're asking about "${message}". Let me provide insights based on your current opportunities.`);
            }
        }
        
        discussCompensation() {
            const avgSalary = this.calculateAverageSalary();
            this.addSennaMessage(`Based on your shortlisted roles, the compensation range is $${Math.round(avgSalary.min/1000)}k-$${Math.round(avgSalary.max/1000)}k. Target the 75th percentile and use multiple offers to negotiate.`);
        }
        
        discussInterview() {
            this.addSennaMessage("For interview preparation, focus on:\n\n1. **Behavioral questions**: Use STAR method for impact stories\n2. **Technical skills**: Review core competencies for each role\n3. **Company research**: Understand recent news and initiatives\n4. **Questions to ask**: Prepare thoughtful questions about growth and culture");
        }
        
        compareRoles() {
            if (this.shortlist.length < 2) {
                this.addSennaMessage("Add more roles to your shortlist for a comprehensive comparison.");
                return;
            }
            
            const top2 = this.shortlist.slice(0, 2);
            const comparison = top2.map(job => ({
                company: job.company || 'Company',
                title: job.title || 'Role',
                match: job.match_score || 75,
                salary: this.extractSalary(job).max
            }));
            
            this.addSennaMessage(`Comparing your top opportunities:\n\n**${comparison[0].company}**: ${comparison[0].match}% match, up to $${Math.round(comparison[0].salary/1000)}k\n**${comparison[1].company}**: ${comparison[1].match}% match, up to $${Math.round(comparison[1].salary/1000)}k\n\nPrioritize based on career goals and cultural fit.`);
        }
        
        discussSkills() {
            const allSkills = new Set();
            this.shortlist.forEach(job => {
                this.extractSkills(job).forEach(skill => allSkills.add(skill));
            });
            
            this.addSennaMessage(`Key skills across your opportunities:\n\n${Array.from(allSkills).slice(0, 5).join(', ')}\n\nFocus on demonstrating these competencies with specific examples.`);
        }
        
        // Helper methods
        addSennaMessage(text) {
            const message = `
                <div class="quantum-message">
                    <div class="quantum-msg-avatar">S</div>
                    <div class="quantum-msg-content">
                        <div class="quantum-msg-text">${this.formatMessage(text)}</div>
                    </div>
                </div>
            `;
            this.addToChat(message);
        }
        
        addUserMessage(text) {
            const message = `
                <div class="quantum-message user">
                    <div class="quantum-msg-avatar">U</div>
                    <div class="quantum-msg-content">
                        <div class="quantum-msg-text">${this.escapeHtml(text)}</div>
                    </div>
                </div>
            `;
            this.addToChat(message);
        }
        
        addToChat(html) {
            this.container.find('.quantum-chat').append(html);
            this.scrollToBottom();
        }
        
        showTyping() {
            if (this.isTyping) return;
            this.isTyping = true;
            
            const typing = `
                <div class="quantum-typing">
                    <div class="quantum-msg-avatar">S</div>
                    <div class="quantum-typing-dots">
                        <span></span><span></span><span></span>
                    </div>
                </div>
            `;
            this.addToChat(typing);
        }
        
        hideTyping() {
            this.isTyping = false;
            this.container.find('.quantum-typing').remove();
        }
        
        scrollToBottom() {
            const chat = this.container.find('.quantum-chat');
            chat.scrollTop(chat[0].scrollHeight);
        }
        
        // Data extraction methods
        extractSalary(job) {
            if (job.salary_min && job.salary_max) {
                return { min: job.salary_min, max: job.salary_max };
            }
            if (job.salary) {
                if (typeof job.salary === 'object') {
                    return { min: job.salary.min || 80000, max: job.salary.max || 150000 };
                }
                if (typeof job.salary === 'number') {
                    return { min: job.salary * 0.9, max: job.salary * 1.1 };
                }
            }
            return { min: 80000, max: 150000 };
        }
        
        extractLevel(job) {
            const title = (job.title || '').toLowerCase();
            if (title.includes('senior') || title.includes('sr')) return 'Senior';
            if (title.includes('lead') || title.includes('principal')) return 'Lead';
            if (title.includes('manager') || title.includes('director')) return 'Manager';
            if (title.includes('junior') || title.includes('jr')) return 'Junior';
            return 'Mid';
        }
        
        estimateTimeline(job) {
            const urgency = job.urgency || 'normal';
            if (urgency === 'urgent') return '1-2 weeks';
            if (urgency === 'high') return '2-3 weeks';
            return '3-4 weeks';
        }
        
        extractRequirements(job) {
            if (job.requirements && Array.isArray(job.requirements)) {
                return job.requirements.slice(0, 5);
            }
            
            // Parse from description if needed
            const desc = job.description || '';
            const reqs = [];
            
            // Common patterns
            if (desc.includes('years') || desc.includes('experience')) {
                reqs.push('Relevant years of experience');
            }
            if (desc.includes('degree') || desc.includes('education')) {
                reqs.push('Appropriate educational background');
            }
            if (desc.includes('team') || desc.includes('collaborate')) {
                reqs.push('Strong collaboration skills');
            }
            
            return reqs.length > 0 ? reqs : ['Review full job description for requirements'];
        }
        
        extractSkills(job) {
            const skills = [];
            
            // From skills array
            if (job.skills && Array.isArray(job.skills)) {
                skills.push(...job.skills);
            }
            
            // From requirements
            if (job.required_skills) {
                skills.push(...job.required_skills);
            }
            
            // From description keywords
            const techKeywords = ['JavaScript', 'Python', 'React', 'Node', 'SQL', 'AWS', 'Docker'];
            const desc = (job.description || '').toLowerCase();
            techKeywords.forEach(tech => {
                if (desc.includes(tech.toLowerCase())) {
                    skills.push(tech);
                }
            });
            
            return [...new Set(skills)].slice(0, 8);
        }
        
        getUserSkills() {
            // Get from profile or use defaults
            const profile = localStorage.getItem('sffc_user_profile');
            if (profile) {
                try {
                    const parsed = JSON.parse(profile);
                    return parsed.skills || [];
                } catch (e) {
                    // Fallback
                }
            }
            
            return ['Communication', 'Problem Solving', 'Leadership', 'Analysis', 'Project Management'];
        }
        
        skillMatch(userSkill, jobSkill) {
            const u = userSkill.toLowerCase();
            const j = jobSkill.toLowerCase();
            return u === j || u.includes(j) || j.includes(u);
        }
        
        calculateAverageSalary() {
            if (this.shortlist.length === 0) {
                return { min: 80000, max: 150000 };
            }
            
            let totalMin = 0;
            let totalMax = 0;
            
            this.shortlist.forEach(job => {
                const salary = this.extractSalary(job);
                totalMin += salary.min;
                totalMax += salary.max;
            });
            
            return {
                min: Math.round(totalMin / this.shortlist.length),
                max: Math.round(totalMax / this.shortlist.length)
            };
        }
        
        formatMessage(text) {
            // Convert markdown-style formatting
            return text
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/\*(.*?)\*/g, '<em>$1</em>')
                .replace(/\n/g, '<br>');
        }
        
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }
        
        close() {
            this.container.removeClass('active');
            setTimeout(() => {
                this.container.remove();
                this.container = null;
            }, 300);
        }
        
        bindGlobalEvents() {
            // Listen for shortlist updates
            $(document).on('sffc:shortlist:updated', () => {
                this.loadShortlist();
                if (this.container) {
                    this.loadOpportunities();
                    this.container.find('.quantum-sidebar-count').text(this.shortlist.length);
                }
            });
            
            // Listen for analyze button clicks
            $(document).on('click', '.sffc-analyze-btn', () => {
                this.open();
            });
        }
    }
    
    // Initialize when ready
    $(document).ready(() => {
        // Only initialize if we're not in admin and not already initialized
        if (!$('body').hasClass('wp-admin') && !window.sennaQuantum) {
            window.sennaQuantum = new SennaQuantum();
            
            // Make it available as the premium chat
            window.sennaPremiumChat = window.sennaQuantum;
        }
    });
    
})(jQuery);