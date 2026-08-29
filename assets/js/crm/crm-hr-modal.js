/**
 * HR Process Modal Handler
 * Handles the View Process modal for HR Outreach cards
 */

(function() {
    'use strict';

    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initHRModal);
    } else {
        initHRModal();
    }

    function initHRModal() {
        const modal = document.getElementById('sffc-hr-process-modal');
        if (!modal) return;

        const overlay = modal.querySelector('.sffc-crm-hr-modal-overlay');
        const closeBtn = modal.querySelector('.sffc-crm-hr-modal-close');
        const viewProcessBtns = document.querySelectorAll('.sffc-crm-view-process-btn');
        const hrCards = document.querySelectorAll('.sffc-crm-hr-card');

        // Open modal when View Process button is clicked
        viewProcessBtns.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const contactId = this.getAttribute('data-contact-id');
                const card = document.querySelector(`article.sffc-crm-hr-card[data-contact-id="${contactId}"]`);

                if (card) {
                    populateModal(card);
                    openModal();
                }
            });
        });

        // Allow clicking anywhere on the card (except interactive links/buttons) to open the modal
        hrCards.forEach(card => {
            card.addEventListener('click', function(e) {
                const interactive = e.target.closest('a, button');
                if (interactive) {
                    return; // let native interactions (email/link buttons) work normally
                }

                populateModal(card);
                openModal();
            });
        });

        // Close modal handlers
        closeBtn.addEventListener('click', closeModal);
        overlay.addEventListener('click', closeModal);

        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.style.display !== 'none') {
                closeModal();
            }
        });

        function openModal() {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';

            // Trigger animation
            requestAnimationFrame(() => {
                overlay.style.opacity = '1';
                modal.querySelector('.sffc-crm-hr-modal-container').style.transform = 'scale(1)';
                modal.querySelector('.sffc-crm-hr-modal-container').style.opacity = '1';
            });
        }

        function closeModal() {
            const container = modal.querySelector('.sffc-crm-hr-modal-container');

            // Animate out
            overlay.style.opacity = '0';
            container.style.transform = 'scale(0.95)';
            container.style.opacity = '0';

            setTimeout(() => {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }, 200);
        }

        function populateModal(card) {
            const modalData = card.querySelector('.sffc-crm-hr-modal-data');
            if (!modalData) return;

            // Populate Notes
            const notesData = modalData.querySelector('.modal-notes');
            const notesContent = document.getElementById('modal-notes-content');
            const notesSection = document.getElementById('modal-notes-section');

            if (notesData && notesContent) {
                const notes = notesData.innerHTML.trim();
                if (notes) {
                    notesContent.innerHTML = notes;
                    notesSection.style.display = 'block';
                } else {
                    notesSection.style.display = 'none';
                }
            }

            // Populate Skills
            const skillsData = modalData.querySelector('.modal-skills');
            const skillsList = document.getElementById('modal-skills-list');
            const skillsSection = document.getElementById('modal-skills-section');

            if (skillsData && skillsList) {
                try {
                    const skills = JSON.parse(skillsData.getAttribute('data-skills') || '[]');
                    if (skills.length > 0) {
                        skillsList.innerHTML = skills.map(skill => {
                            const label = formatSkillLabel(skill);
                            return `<div class="sffc-crm-hr-skill-item">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                    <circle cx="10" cy="10" r="9" fill="#0284c7" fill-opacity="0.1" stroke="#0284c7" stroke-width="1.5"/>
                                    <path d="M6 10l3 3 5-6" stroke="#0284c7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span>${label}</span>
                            </div>`;
                        }).join('');
                        skillsSection.style.display = 'block';
                    } else {
                        skillsSection.style.display = 'none';
                    }
                } catch (e) {
                    skillsSection.style.display = 'none';
                }
            }

            // Populate Process
            const processData = modalData.querySelector('.modal-process');
            const processList = document.getElementById('modal-process-list');
            const processSection = document.getElementById('modal-process-section');

            if (processData && processList) {
                try {
                    const process = JSON.parse(processData.getAttribute('data-process') || '[]');
                    if (process.length > 0) {
                        processList.innerHTML = process.map(item => {
                            const label = formatProcessLabel(item);
                            return `<div class="sffc-crm-hr-process-item">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                    <circle cx="10" cy="10" r="9" fill="#10b981" fill-opacity="0.1" stroke="#10b981" stroke-width="1.5"/>
                                    <path d="M6 10l3 3 5-6" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span>${label}</span>
                            </div>`;
                        }).join('');
                        processSection.style.display = 'block';
                    } else {
                        processSection.style.display = 'none';
                    }
                } catch (e) {
                    processSection.style.display = 'none';
                }
            }

            // Populate Team (Previous Interns)
            const teamData = modalData.querySelector('.modal-team');
            const teamList = document.getElementById('modal-team-list');
            const teamSection = document.getElementById('modal-team-section');

            if (teamData && teamList) {
                try {
                    const team = JSON.parse(teamData.getAttribute('data-team') || '[]');
                    if (team.length > 0) {
                        teamList.innerHTML = team.map(member => {
                            const links = [];
                            if (member.email) {
                                links.push(`<a href="mailto:${escapeHtml(member.email)}">Email</a>`);
                            }
                            if (member.linkedin) {
                                links.push(`<a href="${escapeHtml(member.linkedin)}" target="_blank" rel="noopener">LinkedIn</a>`);
                            }

                            return `<li class="sffc-crm-hr-modal-team-member">
                                <div class="sffc-crm-hr-modal-team-info">
                                    <strong>${escapeHtml(member.name)}</strong>
                                    <span>${escapeHtml(member.title || '')}</span>
                                </div>
                                ${links.length > 0 ? `<div class="sffc-crm-hr-modal-team-links">${links.join('')}</div>` : ''}
                            </li>`;
                        }).join('');
                        teamSection.style.display = 'block';
                    } else {
                        teamSection.style.display = 'none';
                    }
                } catch (e) {
                    teamSection.style.display = 'none';
                }
            }

            // Populate Footer Info
            const footerData = modalData.querySelector('.modal-footer-data');
            const footerContent = document.getElementById('modal-footer-content');
            const footerSection = document.getElementById('modal-footer-section');

            if (footerData && footerContent) {
                const companyUrl = footerData.getAttribute('data-company-url');
                const phone = footerData.getAttribute('data-phone');
                const updated = footerData.getAttribute('data-updated');

                const items = [];

                if (companyUrl) {
                    items.push(`<div class="sffc-crm-hr-modal-footer-item">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="2" y1="12" x2="22" y2="12"></line>
                            <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                        </svg>
                        <a href="${escapeHtml(companyUrl)}" target="_blank" rel="noopener">Company Website</a>
                    </div>`);
                }

                if (phone) {
                    items.push(`<div class="sffc-crm-hr-modal-footer-item">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                        </svg>
                        <span>${escapeHtml(phone)}</span>
                    </div>`);
                }

                if (updated) {
                    items.push(`<div class="sffc-crm-hr-modal-footer-item">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                        <span>Updated ${escapeHtml(updated)}</span>
                    </div>`);
                }

                if (items.length > 0) {
                    footerContent.innerHTML = items.join('');
                    footerSection.style.display = 'block';
                } else {
                    footerSection.style.display = 'none';
                }
            }
        }

        function formatSkillLabel(key) {
            const labels = {
                'financial_analysis': 'Financial Analysis',
                'equity_research': 'Equity Research',
                'financial_modeling': 'Financial Modeling',
                'valuation': 'Valuation (DCF, Comps, Precedents)',
                'excel': 'Excel (Advanced)',
                'powerpoint': 'PowerPoint / Presentation Skills',
                'risk_analysis': 'Risk Analysis',
                'portfolio_management': 'Portfolio Management',
                'due_diligence': 'Due Diligence',
                'merger_acquisition': 'M&A Analysis',
                'lbo_modeling': 'LBO Modeling',
                'credit_analysis': 'Credit Analysis',
                'market_research': 'Market Research',
                'bloomberg_terminal': 'Bloomberg Terminal',
                'capital_iq': 'Capital IQ',
                'factset': 'FactSet',
                'pitchbook': 'PitchBook',
                'accounting_knowledge': 'Accounting Knowledge',
                'attention_to_detail': 'Attention to Detail',
                'analytical_thinking': 'Analytical Thinking',
                'communication': 'Communication Skills',
                'teamwork': 'Teamwork',
                'time_management': 'Time Management',
                'coachable': 'Coachable / Growth Mindset',
                'work_ethic': 'Strong Work Ethic',
                'problem_solving': 'Problem Solving',
                'client_facing': 'Client Facing Skills',
                'python': 'Python / Programming',
                'sql': 'SQL / Data Analysis',
                'vba': 'VBA / Macros',
                'industry_knowledge': 'Industry Knowledge'
            };
            return labels[key] || key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        }

        function formatProcessLabel(key) {
            const labels = {
                'application': 'Application',
                'video_interview': 'Video Interview',
                'phone_screening': 'Phone Screening',
                'technical_tests': 'Technical Tests',
                'hirevue_test': 'Hirevue Test',
                'psychometric_testing': 'Psychometric Testing',
                'cover_letter': 'Cover Letter',
                'cv': 'CV',
                'assessment_center': 'Assessment Center',
                'interview_rounds': 'Interview Rounds'
            };
            return labels[key] || key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }
})();
