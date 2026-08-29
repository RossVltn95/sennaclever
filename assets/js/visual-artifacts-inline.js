/**
 * Visual Artifacts Inline System
 * Integrates visual artifacts directly into chat flow
 */

(function($) {
    'use strict';

    class VisualArtifactsInline {
        constructor() {
            this.currentArtifact = null;
            this.artifactData = {};
            this.currency = 'GBP';
            this.init();
        }

        init() {
            this.bindEvents();
            this.initializeArtifactTypes();
            
            // Listen for action card triggers
            $(document).on('action-card-triggered', (e, data) => {
                this.handleActionCard(data.actionType, data.prompt, data.cardId);
            });
        }

        bindEvents() {
            // Form interactions
            $(document).on('click', '.va-inline-tag', function() {
                $(this).toggleClass('selected');
            });

            $(document).on('click', '.va-inline-currency-btn', (e) => {
                const currency = $(e.target).data('currency');
                this.setCurrency(currency);
            });

            $(document).on('click', '.va-inline-submit', (e) => {
                e.preventDefault();
                const $form = $(e.target).closest('.va-inline-form');
                this.handleSubmit($form);
            });

            $(document).on('click', '.va-inline-cancel', (e) => {
                e.preventDefault();
                const $container = $(e.target).closest('.va-inline-container');
                this.removeArtifact($container);
            });

            // Card selections
            $(document).on('click', '.va-inline-card', function() {
                $(this).siblings().removeClass('selected');
                $(this).addClass('selected');
            });
        }

        initializeArtifactTypes() {
            this.artifacts = {
                // CV & Profile
                'cv-tailor': this.createCVTailoringForm,
                'cv-analyze': this.createCVAnalysisForm,
                'audit': this.createLinkedInAuditForm,
                'highlight': this.createExpertiseHighlightForm,
                'craft': this.createStoryCraftingForm,
                
                // Research & Analysis
                'research': this.createMarketResearchForm,
                'analyze': this.createDealAnalysisForm,
                'assess': this.createAssessmentForm,
                
                // Planning & Strategy
                'plan': this.createPlanningForm,
                'strategy': this.createStrategyForm,
                
                // Career Development
                'prepare': this.createInterviewPrepForm,
                'guide': this.createCareerGuideForm,
                
                // Communication
                'respond': this.createResponseForm,
                'request': this.createRequestForm,
                
                // Negotiation
                'negotiate': this.createCompensationForm,
                'calculate': this.createCalculatorForm,
                
                // Default
                'default': this.createDefaultForm
            };
        }

        handleActionCard(actionType, prompt, cardId) {
            // Check if we should show a visual artifact
            if (this.shouldShowArtifact(actionType)) {
                this.insertArtifactInChat(actionType, prompt, cardId);
            } else {
                // Just send the prompt directly
                this.sendToChat(prompt);
            }
        }

        shouldShowArtifact(actionType) {
            const artifactTypes = [
                'cv-tailor', 'cv-analyze', 'audit', 'highlight', 'craft',
                'research', 'analyze', 'assess', 'plan', 'strategy',
                'prepare', 'guide', 'respond', 'request',
                'negotiate', 'calculate'
            ];
            return artifactTypes.includes(actionType);
        }

        insertArtifactInChat(actionType, originalPrompt, cardId) {
            // Create the visual artifact HTML
            const artifactHTML = this.createArtifact(actionType, originalPrompt, cardId);
            
            // Insert it as a MENA Careers message in the chat
            const messageHTML = `
                <div class="senna-message wsj-style-message">
                    <div class="senna-avatar">
                        <img src="${window.sffc_ajax?.plugin_url || ''}assets/images/senna.jpeg" alt="MENA Careers">
                    </div>
                    <div class="sffc-message-content">
                        ${artifactHTML}
                    </div>
                </div>
            `;
            
            // Add to chat messages
            $('#senna-messages, .sffc-messages-container').append(messageHTML);
            
            // Scroll to bottom
            this.scrollToBottom();
        }

        createArtifact(actionType, originalPrompt, cardId) {
            const formCreator = this.artifacts[actionType] || this.artifacts.default;
            const formHTML = formCreator.call(this, originalPrompt);
            
            return `
                <div class="va-inline-container" data-action-type="${actionType}" data-card-id="${cardId}">
                    ${formHTML}
                </div>
            `;
        }

        createLinkedInAuditForm(originalPrompt) {
            return `
                <div class="va-inline-header">
                    <h3>LinkedIn Profile Audit</h3>
                    <p>Let's optimize your profile for PE recruiting</p>
                </div>
                <div class="va-inline-form">
                    <div class="va-inline-group">
                        <label class="va-inline-label">LinkedIn Profile URL</label>
                        <input type="text" class="va-inline-input" name="linkedin_url" 
                            placeholder="linkedin.com/in/yourprofile">
                    </div>

                    <div class="va-inline-group">
                        <label class="va-inline-label">Target PE Role</label>
                        <select class="va-inline-select" name="target_role">
                            <option value="">Select Target Role</option>
                            <option value="analyst">PE Analyst</option>
                            <option value="associate">PE Associate</option>
                            <option value="vp">Vice President</option>
                            <option value="principal">Principal</option>
                            <option value="partner">Partner</option>
                            <option value="operating">Operating Partner</option>
                        </select>
                    </div>

                    <div class="va-inline-group">
                        <label class="va-inline-label">Audit Focus Areas</label>
                        <div class="va-inline-checkbox-group">
                            <label class="va-inline-checkbox">
                                <input type="checkbox" name="focus[]" value="headline" checked>
                                <span>Headline & Summary</span>
                            </label>
                            <label class="va-inline-checkbox">
                                <input type="checkbox" name="focus[]" value="experience" checked>
                                <span>Experience Descriptions</span>
                            </label>
                            <label class="va-inline-checkbox">
                                <input type="checkbox" name="focus[]" value="skills" checked>
                                <span>Skills & Endorsements</span>
                            </label>
                            <label class="va-inline-checkbox">
                                <input type="checkbox" name="focus[]" value="keywords">
                                <span>Keywords for PE Recruiters</span>
                            </label>
                        </div>
                    </div>

                    <div class="va-inline-group">
                        <label class="va-inline-label">Geographic Focus</label>
                        <div class="va-inline-tags">
                            <span class="va-inline-tag selected" data-value="london">London</span>
                            <span class="va-inline-tag" data-value="paris">Paris</span>
                            <span class="va-inline-tag" data-value="frankfurt">Frankfurt</span>
                            <span class="va-inline-tag" data-value="zurich">Zurich</span>
                            <span class="va-inline-tag" data-value="amsterdam">Amsterdam</span>
                        </div>
                    </div>

                    <button class="va-inline-submit">Analyze My LinkedIn Profile</button>
                    <button class="va-inline-cancel">Cancel</button>
                </div>
            `;
        }

        createCVTailoringForm(originalPrompt) {
            const hasCV = this.artifactData.hasCVUploaded || false;
            
            if (!hasCV) {
                return `
                    <div class="va-inline-header">
                        <h3>CV Tailoring Service</h3>
                        <p>First, I'll need to see your current CV</p>
                    </div>
                    <div class="va-inline-form" style="text-align: center;">
                        <p style="margin-bottom: 24px;">Please upload your CV in the chat to begin tailored optimization for PE roles.</p>
                        <button class="va-inline-submit" onclick="window.visualArtifactsInline.promptCVUpload()">
                            I'm Ready to Share My CV
                        </button>
                    </div>
                `;
            }

            return `
                <div class="va-inline-header">
                    <h3>CV Tailoring Parameters</h3>
                    <p>Customize your CV for specific opportunities</p>
                </div>
                <div class="va-inline-form">
                    <div class="va-inline-group">
                        <label class="va-inline-label">Target Role</label>
                        <input type="text" class="va-inline-input" name="target_role" 
                            placeholder="e.g., Senior Investment Associate">
                    </div>

                    <div class="va-inline-group">
                        <label class="va-inline-label">Company Type</label>
                        <div class="va-inline-grid va-inline-grid-3">
                            <div class="va-inline-card" data-value="pe">
                                <h4>Private Equity</h4>
                            </div>
                            <div class="va-inline-card" data-value="vc">
                                <h4>Venture Capital</h4>
                            </div>
                            <div class="va-inline-card" data-value="ib">
                                <h4>Investment Banking</h4>
                            </div>
                        </div>
                    </div>

                    <div class="va-inline-group">
                        <label class="va-inline-label">Key Skills to Highlight</label>
                        <div class="va-inline-tags">
                            <span class="va-inline-tag">Financial Modelling</span>
                            <span class="va-inline-tag">Due Diligence</span>
                            <span class="va-inline-tag">Deal Sourcing</span>
                            <span class="va-inline-tag">Portfolio Management</span>
                            <span class="va-inline-tag">Valuation</span>
                            <span class="va-inline-tag">LBO Analysis</span>
                        </div>
                    </div>

                    <button class="va-inline-submit">Generate Tailored CV</button>
                </div>
            `;
        }

        createCompensationForm(originalPrompt) {
            return `
                <div class="va-inline-header">
                    <h3>Compensation Benchmarking</h3>
                    <p>Analyze your package against market standards</p>
                </div>
                <div class="va-inline-form">
                    <div class="va-inline-group">
                        <label class="va-inline-label">Current Base Salary</label>
                        <div class="va-inline-currency-group">
                            <span class="va-inline-currency-symbol">${this.currency === 'EUR' ? '€' : '£'}</span>
                            <input type="text" class="va-inline-input va-inline-currency-input" name="base_salary" 
                                placeholder="150,000">
                            <div class="va-inline-currency-toggle">
                                <button class="va-inline-currency-btn ${this.currency === 'GBP' ? 'active' : ''}" data-currency="GBP">GBP</button>
                                <button class="va-inline-currency-btn ${this.currency === 'EUR' ? 'active' : ''}" data-currency="EUR">EUR</button>
                            </div>
                        </div>
                    </div>

                    <div class="va-inline-group">
                        <label class="va-inline-label">Bonus Target (%)</label>
                        <div class="va-inline-slider">
                            <div class="va-inline-slider-track">
                                <div class="va-inline-slider-fill" style="width: 50%;"></div>
                                <div class="va-inline-slider-handle" style="left: 50%;"></div>
                            </div>
                            <div class="va-inline-slider-labels">
                                <span class="va-inline-slider-label">0%</span>
                                <span class="va-inline-slider-label">50%</span>
                                <span class="va-inline-slider-label">100%</span>
                                <span class="va-inline-slider-label">150%</span>
                                <span class="va-inline-slider-label">200%</span>
                            </div>
                        </div>
                    </div>

                    <div class="va-inline-group">
                        <label class="va-inline-label">Location</label>
                        <select class="va-inline-select" name="location">
                            <option value="">Select City</option>
                            <option value="london">London</option>
                            <option value="paris">Paris</option>
                            <option value="frankfurt">Frankfurt</option>
                            <option value="zurich">Zurich</option>
                            <option value="amsterdam">Amsterdam</option>
                            <option value="milan">Milan</option>
                            <option value="stockholm">Stockholm</option>
                        </select>
                    </div>

                    <div class="va-inline-group">
                        <label class="va-inline-label">Years of Experience</label>
                        <input type="number" class="va-inline-input" name="experience" 
                            min="0" max="30" placeholder="8">
                    </div>

                    <button class="va-inline-submit">Generate Benchmark Analysis</button>
                </div>
            `;
        }

        createInterviewPrepForm(originalPrompt) {
            return `
                <div class="va-inline-header">
                    <h3>Interview Preparation Suite</h3>
                    <p>Structured prep for your PE interview</p>
                </div>
                <div class="va-inline-form">
                    <div class="va-inline-group">
                        <label class="va-inline-label">Firm Name</label>
                        <input type="text" class="va-inline-input" name="firm_name" 
                            placeholder="e.g., Blackstone, KKR, Carlyle">
                    </div>

                    <div class="va-inline-group">
                        <label class="va-inline-label">Interview Stage</label>
                        <div class="va-inline-progress">
                            <div class="va-inline-step active">
                                <span class="va-inline-step-number">1</span>
                            </div>
                            <div class="va-inline-step">
                                <span class="va-inline-step-number">2</span>
                            </div>
                            <div class="va-inline-step">
                                <span class="va-inline-step-number">3</span>
                            </div>
                            <div class="va-inline-step">
                                <span class="va-inline-step-number">4</span>
                            </div>
                        </div>
                        <div class="va-inline-grid va-inline-grid-4">
                            <div class="va-inline-card selected" data-value="screening">
                                <p>Screening</p>
                            </div>
                            <div class="va-inline-card" data-value="first">
                                <p>First Round</p>
                            </div>
                            <div class="va-inline-card" data-value="final">
                                <p>Final</p>
                            </div>
                            <div class="va-inline-card" data-value="partner">
                                <p>Partner</p>
                            </div>
                        </div>
                    </div>

                    <div class="va-inline-group">
                        <label class="va-inline-label">Preparation Focus</label>
                        <div class="va-inline-checkbox-group">
                            <label class="va-inline-checkbox">
                                <input type="checkbox" name="prep[]" value="technical" checked>
                                <span>Technical Questions & Case Studies</span>
                            </label>
                            <label class="va-inline-checkbox">
                                <input type="checkbox" name="prep[]" value="behavioral" checked>
                                <span>Behavioural & Fit Questions</span>
                            </label>
                            <label class="va-inline-checkbox">
                                <input type="checkbox" name="prep[]" value="market">
                                <span>Market & Industry Knowledge</span>
                            </label>
                            <label class="va-inline-checkbox">
                                <input type="checkbox" name="prep[]" value="firm">
                                <span>Firm-Specific Research</span>
                            </label>
                        </div>
                    </div>

                    <button class="va-inline-submit">Generate Preparation Plan</button>
                </div>
            `;
        }

        // Add more form creators...
        createDefaultForm(originalPrompt) {
            return `
                <div class="va-inline-header">
                    <h3>Action Parameters</h3>
                    <p>Configure your request</p>
                </div>
                <div class="va-inline-form">
                    <p>Processing your request...</p>
                    <button class="va-inline-submit">Continue</button>
                </div>
            `;
        }

        handleSubmit($form) {
            // Collect form data
            const formData = this.collectFormData($form);
            
            // Build enhanced prompt
            const enhancedPrompt = this.buildEnhancedPrompt(formData);
            
            // Replace the form with a processing message
            const $container = $form.closest('.va-inline-container');
            $container.html(`
                <div style="text-align: center; padding: 20px;">
                    <p style="color: #666;">Processing your request...</p>
                </div>
            `);
            
            // Send to chat
            setTimeout(() => {
                this.sendToChat(enhancedPrompt);
                this.removeArtifact($container);
            }, 500);
        }

        collectFormData($form) {
            const data = {
                currency: this.currency
            };

            // Collect inputs
            $form.find('input[type="text"], input[type="number"], textarea').each(function() {
                const name = $(this).attr('name');
                if (name) {
                    data[name] = $(this).val();
                }
            });

            // Collect selects
            $form.find('select').each(function() {
                const name = $(this).attr('name');
                if (name) {
                    data[name] = $(this).val();
                }
            });

            // Collect checkboxes
            $form.find('input[type="checkbox"]:checked').each(function() {
                const name = $(this).attr('name');
                if (name) {
                    if (!data[name]) data[name] = [];
                    data[name].push($(this).val());
                }
            });

            // Collect selected tags
            data.tags = [];
            $form.find('.va-inline-tag.selected').each(function() {
                data.tags.push($(this).text());
            });

            // Collect selected cards
            $form.find('.va-inline-card.selected').each(function() {
                const value = $(this).data('value');
                if (value) {
                    data.selected_option = value;
                }
            });

            return data;
        }

        buildEnhancedPrompt(data) {
            // Build a comprehensive prompt based on collected data
            let prompt = '';
            
            // Add context from form data
            Object.keys(data).forEach(key => {
                if (data[key] && data[key].length > 0) {
                    prompt += `${key.replace(/_/g, ' ')}: ${data[key]}. `;
                }
            });

            return prompt;
        }

        sendToChat(message) {
            // Integration with existing chat system - force as general AI query
            if (window.SennaChat && window.SennaChat.processGeneralQuery) {
                // Use the general query method to bypass job filtering
                window.SennaChat.processGeneralQuery(message);
            } else if (window.sennaChat && window.sennaChat.processGeneralQuery) {
                // Alternative reference
                window.sennaChat.processGeneralQuery(message);
            } else if (window.SennaChat && window.SennaChat.send) {
                // Fallback to regular send
                window.SennaChat.send(message);
            } else {
                // Last resort: send via AJAX directly as general query
                if (window.sffc_ajax) {
                    $.ajax({
                        url: window.sffc_ajax.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'sffc_senna_chat',
                            message: message,
                            nonce: window.sffc_ajax.nonce,
                            isGeneralQuery: true // Flag to bypass job search
                        },
                        success: (response) => {
                            if (response.success && response.data) {
                                // Add response to chat
                                const responseHTML = `
                                    <div class="senna-message wsj-style-message">
                                        <div class="senna-avatar">
                                            <img src="${window.sffc_ajax?.plugin_url || ''}assets/images/senna.jpeg" alt="MENA Careers">
                                        </div>
                                        <div class="sffc-message-content">
                                            ${response.data.message || response.data}
                                        </div>
                                    </div>
                                `;
                                $('#senna-messages, .sffc-messages-container').append(responseHTML);
                                this.scrollToBottom();
                            }
                        }
                    });
                } else {
                    // Ultimate fallback: put in input and click send
                    const $input = $('#senna-input');
                    const $send = $('#senna-send, .sffc-send-btn').first();
                    
                    if ($input.length && $send.length) {
                        $input.val(message);
                        $send.click();
                    }
                }
            }
        }

        removeArtifact($container) {
            $container.fadeOut(300, function() {
                $(this).remove();
            });
        }

        setCurrency(currency) {
            this.currency = currency;
            $('.va-inline-currency-btn').removeClass('active');
            $(`.va-inline-currency-btn[data-currency="${currency}"]`).addClass('active');
            $('.va-inline-currency-symbol').text(currency === 'EUR' ? '€' : '£');
        }

        scrollToBottom() {
            const $messages = $('#senna-messages, .sffc-messages-container').first();
            if ($messages.length) {
                $messages.animate({ 
                    scrollTop: $messages[0].scrollHeight 
                }, 300);
            }
        }

        promptCVUpload() {
            this.sendToChat("I'm ready to share my CV for tailored optimization. Please guide me through the upload process.");
        }
    }

    // Initialize on document ready
    $(document).ready(function() {
        window.visualArtifactsInline = new VisualArtifactsInline();
    });

})(jQuery);