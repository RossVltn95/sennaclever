/**
 * Engagement Buttons Handler
 * Manages contextual button display and interactions
 */
(function($) {
    'use strict';
    
    const EngagementButtons = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.setupNameCapture();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Button click handlers
            $(document).on('click', '.sffc-engagement-btn', this.handleEngagementClick.bind(this));
            $(document).on('click', '.sffc-mini-btn', this.handleMiniButtonClick.bind(this));
            
            // Name submission handler
            $(document).on('keypress', '.sffc-name-input', this.handleNameInput.bind(this));
            $(document).on('click', '.sffc-name-submit', this.submitName.bind(this));
        },
        
        /**
         * Setup name capture functionality
         */
        setupNameCapture: function() {
            // Check if message asks for name
            $(document).on('sffc:message:rendered', function(e, data) {
                if (data.message && this.isNameRequest(data.message)) {
                    this.showNameInput(data.messageId);
                }
            }.bind(this));
        },
        
        /**
         * Check if message is requesting user's name
         */
        isNameRequest: function(message) {
            const namePatterns = [
                /what('s| is) your (first )?name/i,
                /who (am i|do i have)/i,
                /what should i call you/i,
                /mind (if i ask|sharing) your name/i,
                /may i have your (first )?name/i,
                /and you are\?/i
            ];
            
            return namePatterns.some(pattern => pattern.test(message));
        },
        
        /**
         * Show name input field
         */
        showNameInput: function(messageId) {
            const inputHtml = `
                <div class="sffc-name-capture">
                    <input type="text" class="sffc-name-input" placeholder="Enter your first name" data-message-id="${messageId}">
                    <button class="sffc-name-submit" data-message-id="${messageId}">Submit</button>
                </div>
            `;
            
            // Add after the message
            $(`#message-${messageId} .sffc-message-text`).after(inputHtml);
            
            // Focus the input
            $('.sffc-name-input').focus();
        },
        
        /**
         * Handle name input submission
         */
        handleNameInput: function(e) {
            if (e.which === 13) { // Enter key
                e.preventDefault();
                this.submitName(e);
            }
        },
        
        /**
         * Submit user's name
         */
        submitName: function(e) {
            const $input = $(e.target).hasClass('sffc-name-input') ? 
                          $(e.target) : 
                          $(e.target).siblings('.sffc-name-input');
            
            const name = $input.val().trim();
            
            if (!name) {
                $input.addClass('error').attr('placeholder', 'Please enter your name');
                return;
            }
            
            // Store the name via AJAX
            $.ajax({
                url: sffc_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'sffc_store_user_name',
                    name: name,
                    nonce: sffc_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Remove the input field
                        $('.sffc-name-capture').fadeOut(300, function() {
                            $(this).remove();
                        });
                        
                        // Show confirmation
                        this.showNotification(response.data.message, 'success');
                        
                        // Update UI to reflect personalization
                        this.updatePersonalization(name);
                    }
                }.bind(this),
                error: function() {
                    this.showNotification('Failed to save your name. Please try again.', 'error');
                }.bind(this)
            });
        },
        
        /**
         * Update UI with personalization
         */
        updatePersonalization: function(name) {
            // Update any welcome text
            $('.sffc-welcome-text').text(`Welcome back, ${name}`);
            
            // Store in localStorage for quick access
            localStorage.setItem('sffc_user_name', name);
        },
        
        /**
         * Handle engagement button click
         */
        handleEngagementClick: function(e) {
            e.preventDefault();
            const $button = $(e.currentTarget);
            const action = $button.data('action');
            const messageId = $button.data('message-id');
            
            switch(action) {
                case 'clarify':
                    this.handleClarify(messageId);
                    break;
                    
                case 'new_question':
                    this.handleNewQuestion();
                    break;
                    
                case 'follow_up':
                    const query = $button.data('query');
                    const context = $button.data('context');
                    this.handleFollowUp(query, context);
                    break;
                    
                default:
                    console.log('Unknown action:', action);
            }
        },
        
        /**
         * Handle clarify action
         */
        handleClarify: function(messageId) {
            // Get the original message
            const $message = $(`#message-${messageId} .sffc-message-text`);
            const originalText = $message.text();
            
            // Send clarification request
            const clarifyQuery = `Can you clarify what you meant about: "${originalText.substring(0, 100)}..."`;
            
            // Send through the main chat interface
            if (window.SFFCChat) {
                window.SFFCChat.sendMessage(clarifyQuery);
            }
        },
        
        /**
         * Handle new question
         */
        handleNewQuestion: function() {
            // Focus the input field for new question
            $('#sffc-chat-input').focus();
            
            // Optionally show placeholder
            $('#sffc-chat-input').attr('placeholder', 'Ask MENA Careers anything...');
        },
        
        /**
         * Handle follow up
         */
        handleFollowUp: function(query, context) {
            // Send the follow-up query
            if (window.SFFCChat) {
                window.SFFCChat.sendMessage(query, { context: context });
            }
        },
        
        /**
         * Handle mini button click
         */
        handleMiniButtonClick: function(e) {
            e.preventDefault();
            const $button = $(e.currentTarget);
            const action = $button.data('action');
            const messageId = $button.data('message-id');
            
            switch(action) {
                case 'positive_feedback':
                    this.recordFeedback(messageId, 'helpful');
                    $button.addClass('active').text('✓');
                    break;
                    
                case 'expand':
                    this.requestExpansion(messageId);
                    break;
                    
                case 'continue':
                    this.continueConversation(messageId);
                    break;
            }
        },
        
        /**
         * Record user feedback
         */
        recordFeedback: function(messageId, type) {
            $.ajax({
                url: sffc_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'sffc_record_feedback',
                    message_id: messageId,
                    feedback_type: type,
                    nonce: sffc_ajax.nonce
                }
            });
        },
        
        /**
         * Request expanded information
         */
        requestExpansion: function(messageId) {
            const query = "Can you provide more details about that?";
            if (window.SFFCChat) {
                window.SFFCChat.sendMessage(query, { expand_message: messageId });
            }
        },
        
        /**
         * Continue conversation flow
         */
        continueConversation: function(messageId) {
            const query = "What else should I know about this?";
            if (window.SFFCChat) {
                window.SFFCChat.sendMessage(query, { continue_from: messageId });
            }
        },
        
        /**
         * Render engagement buttons
         */
        renderButtons: function(buttons, messageId, $container) {
            if (!buttons) return;
            
            // Above message buttons
            if (buttons.above && buttons.above.length > 0) {
                const aboveHtml = this.generateButtonsHtml(buttons.above, messageId, 'above');
                $container.before(aboveHtml);
            }
            
            // Below message buttons - positioned after sffc-message-text
            if (buttons.below && buttons.below.length > 0) {
                const belowHtml = this.generateButtonsHtml(buttons.below, messageId, 'below');
                $container.find('.sffc-message-text').after(belowHtml);
            }
            
            // Mini engagement buttons
            if (buttons.mini && buttons.mini.length > 0) {
                const miniHtml = this.generateButtonsHtml(buttons.mini, messageId, 'mini');
                $container.append(miniHtml);
            }
        },
        
        /**
         * Generate buttons HTML
         */
        generateButtonsHtml: function(buttons, messageId, position) {
            let html = `<div class="sffc-${position}-message-buttons">`;
            
            buttons.forEach(button => {
                const dataAttrs = this.buildDataAttributes(button, messageId);
                const className = position === 'mini' ? 'sffc-mini-btn' : 
                                 `sffc-engagement-btn sffc-btn-${button.style || 'primary'}`;
                
                html += `<button class="${className}" ${dataAttrs}>${button.text}</button>`;
            });
            
            html += '</div>';
            return html;
        },
        
        /**
         * Build data attributes string
         */
        buildDataAttributes: function(button, messageId) {
            let attrs = `data-action="${button.action}" data-message-id="${messageId}"`;
            
            if (button.data) {
                Object.keys(button.data).forEach(key => {
                    attrs += ` data-${key}="${button.data[key]}"`;
                });
            }
            
            return attrs;
        },
        
        /**
         * Show notification
         */
        showNotification: function(message, type) {
            const $notification = $(`
                <div class="sffc-notification sffc-notification-${type}">
                    ${message}
                </div>
            `);
            
            $('body').append($notification);
            
            setTimeout(() => {
                $notification.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        EngagementButtons.init();
    });
    
    // Expose for external use
    window.SFFCEngagementButtons = EngagementButtons;
    
})(jQuery);