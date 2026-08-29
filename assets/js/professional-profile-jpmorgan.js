(function($) {
    'use strict';

    // JP Morgan Professional Profile Experience
    const JPMorganProfile = {
        init: function() {
            this.initPanelSwitcher();
            this.initHeaderNavigation();
            this.initProfilePictureUpload();
            this.initNetworkingSystem();
            this.initExpertiseFilters();
            this.initPlanModal();
            this.bindEvents();
        },

        initPanelSwitcher: function() {
            const triggers = $('.jp-panel-trigger');
            const panels = $('.jp-panel');

            triggers.on('click', function() {
                const target = $(this).data('panel');
                triggers.removeClass('active');
                $(this).addClass('active');

                panels.removeClass('active');
                panels.filter(`[data-panel="${target}"]`).addClass('active');
            });
        },

        initHeaderNavigation: function() {
            $('.jp-nav-link').on('click', function(e) {
                const href = $(this).attr('href');
                
                // Handle settings link
                if (href && href.includes('settings=1')) {
                    return; // Let it navigate normally
                }
                
                // Handle panel navigation
                if (href && href.startsWith('#')) {
                    e.preventDefault();
                    const panelName = href.substring(1);
                    
                    // Map header links to panel names
                    const panelMap = {
                        'overview': 'intelligence',
                        'intelligence': 'intelligence', 
                        'network': 'network',
                        'insights': 'activity'
                    };
                    
                    const targetPanel = panelMap[panelName];
                    if (targetPanel) {
                        $('.jp-panel-trigger').removeClass('active');
                        $(`.jp-panel-trigger[data-panel="${targetPanel}"]`).addClass('active');
                        
                        $('.jp-panel').removeClass('active');
                        $(`.jp-panel[data-panel="${targetPanel}"]`).addClass('active');
                        
                        // Scroll to panels section
                        const panelsSection = $('.jp-panels');
                        if (panelsSection.length) {
                            $('html, body').animate({
                                scrollTop: panelsSection.offset().top - 100
                            }, 600);
                        }
                    }
                }
            });
        },




        initProfilePictureUpload: function() {
            const uploadInput = $('#jp-avatar-upload');
            const avatarDisplay = $('#jp-avatar-display');

            uploadInput.off('change.jpmorgan').on('change.jpmorgan', function(e) {
                e.stopPropagation();
                const file = e.target.files[0];
                if (file) {
                    // Validate file type
                    if (!file.type.match('image.*')) {
                        alert('Please select a valid image file.');
                        return;
                    }

                    // Validate file size (max 5MB)
                    if (file.size > 5 * 1024 * 1024) {
                        alert('File size must be less than 5MB.');
                        return;
                    }

                    const reader = new FileReader();
                    reader.onload = function(readerEvent) {
                        // Show preview immediately
                        avatarDisplay.html(`<img src="${readerEvent.target.result}" alt="Profile Picture" style="width: 100%; height: 100%; object-fit: cover;" /><input type="file" class="jp-avatar-upload" id="jp-avatar-upload" accept="image/*" />`);
                        
                        // Upload via AJAX
                        JPMorganProfile.uploadProfilePicture(file);
                    };
                    reader.readAsDataURL(file);
                }
            });

            // Click handler for avatar with namespace
            avatarDisplay.off('click.jpmorgan').on('click.jpmorgan', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).find('#jp-avatar-upload').click();
            });
        },

        uploadProfilePicture: function(file) {
            const formData = new FormData();
            formData.append('action', 'sffc_upload_profile_picture');
            formData.append('profile_picture', file);
            formData.append('nonce', sffc_ajax.nonce);

            $.ajax({
                url: sffc_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        // Track successful upload
                        JPMorganProfile.trackInteraction('profile_picture_upload', {
                            status: 'success',
                            file_size: file.size
                        });
                    } else {
                        console.error('Upload failed:', response.data);
                        alert('Upload failed. Please try again.');
                    }
                },
                error: function() {
                    console.error('Upload error');
                    alert('Upload failed. Please check your connection and try again.');
                }
            });
        },

        initNetworkingSystem: function() {
            // Load networking data when network panel is activated
            $('.jp-panel-trigger[data-panel="network"]').on('click', function() {
                JPMorganProfile.loadNetworkingData();
            });

            // Handle introduction requests
            $(document).on('click', '.jp-request-intro', function() {
                const userId = $(this).data('user-id');
                const userName = $(this).data('user-name');
                const userRole = $(this).data('user-role');
                
                if (userId === 'demo') {
                    JPMorganProfile.showDemoModal();
                    return;
                }
                
                JPMorganProfile.showIntroductionModal(userId, userName, userRole);
            });

            // Handle introduction responses (accept/decline)
            $(document).on('click', '.jp-respond-request', function() {
                const requestId = $(this).data('request-id');
                const response = $(this).data('response');
                JPMorganProfile.respondToRequest(requestId, response);
            });
        },

        loadNetworkingData: function() {
            if (!window.sffc_ajax || !sffc_ajax.ajax_url) {
                console.error('AJAX configuration missing');
                return;
            }

            $.post(sffc_ajax.ajax_url, {
                action: 'sffc_get_networking_requests',
                nonce: sffc_ajax.nonce
            })
            .done(function(response) {
                if (response.success) {
                    JPMorganProfile.renderNetworkingData(response.data);
                } else {
                    console.error('Failed to load networking data:', response.data);
                }
            })
            .fail(function() {
                console.error('Network request failed');
            });
        },

        renderNetworkingData: function(data) {
            // Render pending requests
            const pendingContainer = $('#jp-pending-requests');
            if (data.incoming_requests && data.incoming_requests.length > 0) {
                let pendingHtml = '';
                data.incoming_requests.forEach(function(request) {
                    pendingHtml += `
                        <div class="jp-request-card">
                            <div class="jp-request-avatar">
                                ${request.requester_name.charAt(0).toUpperCase()}
                            </div>
                            <div class="jp-request-info">
                                <h5>${request.requester_name}</h5>
                                <div class="jp-request-role">${request.requester_position}, ${request.requester_company}</div>
                                ${request.request_message ? `<p class="jp-request-message">"${request.request_message}"</p>` : ''}
                                <div class="jp-request-date">Sent ${JPMorganProfile.formatDate(request.created_at)}</div>
                            </div>
                            <div class="jp-request-actions">
                                <button class="jp-btn jp-btn-primary jp-btn-small jp-respond-request" 
                                        data-request-id="${request.id}" data-response="accept">
                                    Accept
                                </button>
                                <button class="jp-btn jp-btn-secondary jp-btn-small jp-respond-request" 
                                        data-request-id="${request.id}" data-response="decline">
                                    Decline
                                </button>
                            </div>
                        </div>
                    `;
                });
                pendingContainer.html(pendingHtml);
            } else {
                pendingContainer.html('<div class="jp-empty-state">No pending introduction requests</div>');
            }

            // Render connections
            const connectionsContainer = $('#jp-connections');
            if (data.connections && data.connections.length > 0) {
                let connectionsHtml = '';
                data.connections.forEach(function(connection) {
                    const avatar = connection.connected_user_picture ? 
                        `<img src="${connection.connected_user_picture}" alt="${connection.connected_user_name}" />` :
                        connection.connected_user_name.charAt(0).toUpperCase();
                    
                    connectionsHtml += `
                        <div class="jp-professional-card jp-connection-card">
                            <div class="jp-professional-avatar">${avatar}</div>
                            <div class="jp-professional-info">
                                <h5 class="jp-professional-name">${connection.connected_user_name}</h5>
                                <div class="jp-professional-role">${connection.connected_user_position || 'Professional'}</div>
                                <div class="jp-connection-date">Connected ${JPMorganProfile.formatDate(connection.established_at)}</div>
                                <button class="jp-btn jp-btn-secondary jp-btn-small jp-message-connection" 
                                        data-user-id="${connection.connected_user_id}">
                                    Message
                                </button>
                            </div>
                        </div>
                    `;
                });
                connectionsContainer.html(connectionsHtml);
            } else {
                connectionsContainer.html('<div class="jp-empty-state">No connections yet. Start networking!</div>');
            }

            // Render sent requests
            const sentContainer = $('#jp-sent-requests');
            if (data.outgoing_requests && data.outgoing_requests.length > 0) {
                let sentHtml = '';
                data.outgoing_requests.slice(0, 5).forEach(function(request) {
                    const statusClass = `jp-status-${request.status}`;
                    const statusText = {
                        'pending': 'Pending',
                        'accepted': 'Accepted',
                        'declined': 'Declined',
                        'expired': 'Expired'
                    }[request.status] || 'Unknown';
                    
                    sentHtml += `
                        <div class="jp-request-card ${statusClass}">
                            <div class="jp-request-avatar">
                                ${request.target_name.charAt(0).toUpperCase()}
                            </div>
                            <div class="jp-request-info">
                                <h5>${request.target_name}</h5>
                                <div class="jp-request-role">${request.target_position}, ${request.target_company}</div>
                                <div class="jp-request-status">Status: ${statusText}</div>
                                <div class="jp-request-date">Sent ${JPMorganProfile.formatDate(request.created_at)}</div>
                            </div>
                        </div>
                    `;
                });
                sentContainer.html(sentHtml);
            } else {
                sentContainer.html('<div class="jp-empty-state">No requests sent yet</div>');
            }
        },

        showIntroductionModal: function(userId, userName, userRole) {
            const modal = $(`
                <div class="jp-modal jp-intro-modal" role="dialog" aria-modal="true">
                    <div class="jp-modal-content">
                        <h3 class="jp-modal-title">Express Interest</h3>
                        <p class="jp-modal-text">
                            Send an introduction request to <strong>${userName}</strong><br />
                            <em>${userRole}</em>
                        </p>
                        <div class="jp-intro-form">
                            <textarea class="jp-intro-message" placeholder="Add a personal message (optional)" maxlength="500"></textarea>
                            <div class="jp-char-count">0/500 characters</div>
                        </div>
                        <div class="jp-modal-actions">
                            <button class="jp-btn jp-btn-primary" id="jp-send-intro">Send Request</button>
                            <button class="jp-btn jp-btn-secondary" id="jp-cancel-intro">Cancel</button>
                        </div>
                    </div>
                </div>
            `);

            $('body').append(modal);

            // Character counter
            modal.find('.jp-intro-message').on('input', function() {
                const length = $(this).val().length;
                modal.find('.jp-char-count').text(`${length}/500 characters`);
            });

            // Modal events
            modal.on('click', function(e) {
                if (e.target === this) {
                    JPMorganProfile.closeModal(modal);
                }
            });

            $('#jp-cancel-intro').on('click', function() {
                JPMorganProfile.closeModal(modal);
            });

            $('#jp-send-intro').on('click', function() {
                const message = modal.find('.jp-intro-message').val().trim();
                JPMorganProfile.sendIntroductionRequest(userId, userName, userRole, message, modal);
            });
        },

        sendIntroductionRequest: function(userId, userName, userRole, message, modal) {
            const button = $('#jp-send-intro');
            button.prop('disabled', true).text('Sending...');

            $.post(sffc_ajax.ajax_url, {
                action: 'sffc_request_introduction',
                target_id: userId,
                message: message,
                nonce: sffc_ajax.nonce
            })
            .done(function(response) {
                if (response.success) {
                    button.text('Request Sent!');
                    setTimeout(() => {
                        JPMorganProfile.closeModal(modal);
                        JPMorganProfile.loadNetworkingData(); // Refresh data
                    }, 1500);
                } else {
                    alert('Error: ' + response.data);
                    button.prop('disabled', false).text('Send Request');
                }
            })
            .fail(function() {
                alert('Network error. Please try again.');
                button.prop('disabled', false).text('Send Request');
            });
        },

        respondToRequest: function(requestId, response) {
            const button = $(`.jp-respond-request[data-request-id="${requestId}"][data-response="${response}"]`);
            const originalText = button.text();
            button.prop('disabled', true).text('Processing...');

            $.post(sffc_ajax.ajax_url, {
                action: 'sffc_respond_to_introduction',
                request_id: requestId,
                response: response,
                nonce: sffc_ajax.nonce
            })
            .done(function(res) {
                if (res.success) {
                    JPMorganProfile.loadNetworkingData(); // Refresh data
                } else {
                    alert('Error: ' + res.data);
                    button.prop('disabled', false).text(originalText);
                }
            })
            .fail(function() {
                alert('Network error. Please try again.');
                button.prop('disabled', false).text(originalText);
            });
        },

        showDemoModal: function() {
            const modal = $(`
                <div class="jp-modal" role="dialog" aria-modal="true">
                    <div class="jp-modal-content">
                        <h3 class="jp-modal-title">Demo Mode</h3>
                        <p class="jp-modal-text">
                            This is a demonstration profile. In the live system, you would be able to send real introduction requests to other professionals.
                        </p>
                        <div class="jp-modal-actions">
                            <button class="jp-btn jp-btn-primary" id="jp-demo-ok">Got it</button>
                        </div>
                    </div>
                </div>
            `);

            $('body').append(modal);
            
            $('#jp-demo-ok').on('click', function() {
                JPMorganProfile.closeModal(modal);
            });

            modal.on('click', function(e) {
                if (e.target === this) {
                    JPMorganProfile.closeModal(modal);
                }
            });
        },

        formatDate: function(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diffTime = Math.abs(now - date);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            
            if (diffDays === 1) return 'yesterday';
            if (diffDays < 7) return `${diffDays} days ago`;
            if (diffDays < 30) return `${Math.ceil(diffDays / 7)} weeks ago`;
            return date.toLocaleDateString();
        },

        initProfessionalNetworking: function() {
            $('.jp-professional-card').on('click', function() {
                const userName = $(this).find('.jp-professional-name').text();
                const userRole = $(this).find('.jp-professional-role').text();
                const userId = $(this).data('user-id');
                
                JPMorganProfile.showNetworkingModal(userName, userRole, userId);
            });

            // Expertise badge filtering
            $('.jp-expertise-badge').on('click', function() {
                const expertise = $(this).data('expertise') || $(this).text();
                JPMorganProfile.filterByExpertise(expertise);
            });
        },

        showNetworkingModal: function(name, role, userId) {
            const modal = $(`
                <div class="jp-modal" id="jp-networking-modal">
                    <div class="jp-modal-content">
                        <h3 class="jp-modal-title">Express Interest</h3>
                        <p class="jp-modal-text">
                            Request an introduction to <strong>${name}</strong><br>
                            <em>${role}</em>
                        </p>
                        <div class="jp-modal-actions">
                            <button class="jp-btn-primary" id="jp-request-intro">Express Interest</button>
                            <button class="jp-btn-secondary" id="jp-cancel-intro">Cancel</button>
                        </div>
                    </div>
                </div>
            `);

            $('body').append(modal);

            // Animate modal in
            setTimeout(() => {
                modal.find('.jp-modal-content').css({
                    'transform': 'translateY(0)',
                    'opacity': '1'
                });
            }, 50);

            // Event handlers
            modal.on('click', function(e) {
                if (e.target === this) {
                    JPMorganProfile.closeModal(modal);
                }
            });

            $('#jp-cancel-intro').on('click', function() {
                JPMorganProfile.closeModal(modal);
            });

            $('#jp-request-intro').on('click', function() {
                const button = $(this);
                button.text('Sending Request...').prop('disabled', true);
                
                $.post(sffc_ajax.ajax_url, {
                    action: 'sffc_request_introduction',
                    target_id: userId,
                    target_name: name,
                    introduction_context: `Professional networking introduction request via MENA Careers Professional platform`,
                    introduction_reason: `Interested in connecting with ${name} based on their role at ${role.split(',')[1] || 'their organization'}`,
                    mutual_interest: 'Investment banking and financial services',
                    nonce: sffc_ajax.nonce
                }, function(response) {
                    if (response.success) {
                        button.text('Interest Expressed');
                        JPMorganProfile.trackInteraction('introduction_request', {
                            target_user: name,
                            target_role: role
                        });
                        setTimeout(() => {
                            JPMorganProfile.closeModal(modal);
                        }, 1500);
                    } else {
                        button.text('Request Failed - Try Again').prop('disabled', false);
                    }
                }).fail(function() {
                    button.text('Network Error - Try Again').prop('disabled', false);
                });
            });
        },

        filterByExpertise: function(expertise) {
            // Track expertise interaction
            this.trackInteraction('expertise_filter', { expertise: expertise });
            
            // Highlight relevant cards
            $('.jp-intelligence-card').each(function() {
                const cardCategory = $(this).find('.jp-card-category').text();
                const cardTitle = $(this).find('.jp-card-title').text();
                
                if (cardCategory.toLowerCase().includes(expertise.toLowerCase()) || 
                    cardTitle.toLowerCase().includes(expertise.toLowerCase())) {
                    $(this).addClass('jp-expertise-highlight');
                } else {
                    $(this).addClass('jp-expertise-dimmed');
                }
            });

            // Auto-scroll to intelligence section
            $('html, body').animate({
                scrollTop: $('#intelligence').offset().top - 150
            }, 800);

            // Remove filter after 4 seconds
            setTimeout(() => {
                $('.jp-intelligence-card').removeClass('jp-expertise-highlight jp-expertise-dimmed');
            }, 4000);
        },

        closeModal: function(modal) {
            modal.find('.jp-modal-content').css({
                'transform': 'translateY(1rem)',
                'opacity': '0'
            });
            
            setTimeout(() => {
                modal.remove();
            }, 300);
        },

        trackInteraction: function(action, details) {
            if (typeof sffc_ajax !== 'undefined') {
                $.post(sffc_ajax.ajax_url, {
                    action: 'sffc_track_profile_interaction',
                    interaction_action: action,
                    details: details,
                    nonce: sffc_ajax.nonce
                });
            }
        },

        bindEvents: function() {
            // Enhanced card hover effects
            $('.jp-intelligence-card, .jp-professional-card').on('mouseenter', function() {
                $(this).addClass('jp-card-hover-active');
            }).on('mouseleave', function() {
                $(this).removeClass('jp-card-hover-active');
            });

            // Header navigation
            $('.jp-nav-link').on('click', function(e) {
                e.preventDefault();
                const target = $(this).attr('href');
                if (target && target.startsWith('#')) {
                    $('html, body').animate({
                        scrollTop: $(target).offset().top - 100
                    }, 1000, 'easeInOutQuart');
                }
            });

            // Track page interactions
            $(window).on('scroll', debounce(this.updateScrollProgress, 100));
        },

        updateScrollProgress: function() {
            const scrolled = $(window).scrollTop();
            const total = $(document).height() - $(window).height();
            const progress = (scrolled / total) * 100;

            // Create or update progress indicator
            let progressBar = $('.jp-scroll-progress');
            if (progressBar.length === 0) {
                progressBar = $(`
                    <div class="jp-scroll-progress" style="
                        position: fixed;
                        top: 0;
                        left: 0;
                        width: 0%;
                        height: 2px;
                        background: linear-gradient(90deg, #000000, #005cb9);
                        z-index: 1000;
                        transition: width 0.1s ease;
                    "></div>
                `);
                $('body').append(progressBar);
            }

            progressBar.css('width', `${Math.min(progress, 100)}%`);
        },

        initExpertiseFilters: function() {
            // Initialize expertise badge filtering
            $('.jp-expertise-badge').on('click', function() {
                const expertise = $(this).data('expertise') || $(this).text();
                JPMorganProfile.filterByExpertise(expertise);
            });
        },

        initPlanModal: function() {
            const $planModal = $('[data-plan-modal]');
            if (!$planModal.length) return;

            // Open modal when upgrade button is clicked
            $(document).on('click', '[data-action="open-plan-modal"]', function(e) {
                e.preventDefault();
                JPMorganProfile.openPlanModal();
            });

            // Close modal
            $(document).on('click', '[data-plan-close]', function(e) {
                e.preventDefault();
                JPMorganProfile.closePlanModal();
            });

            // Close on overlay click
            $planModal.on('click', function(e) {
                if (e.target === this || $(e.target).hasClass('sffc-plan-modal__overlay')) {
                    JPMorganProfile.closePlanModal();
                }
            });

            // Close on Escape key
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $planModal.hasClass('is-open')) {
                    JPMorganProfile.closePlanModal();
                }
            });

            // Handle plan selection
            $(document).on('click', '[data-plan-select]', function(e) {
                e.preventDefault();
                const $btn = $(this);
                const planSlug = $btn.data('plan-slug');
                const planUrl = $btn.data('plan-url');

                // Hide plan cards, show checkout
                $('[data-plan-card]').hide();
                $('[data-plan-checkout]').removeAttr('hidden').show();
                $('[data-plan-form]').hide();
                $('[data-plan-form="' + planSlug + '"]').removeAttr('hidden').show();
                $('[data-plan-message]').hide();

                // If no form exists, redirect to external URL
                if (!$('[data-plan-form="' + planSlug + '"]').length && planUrl) {
                    window.location.href = planUrl;
                }
            });
        },

        openPlanModal: function() {
            const $modal = $('[data-plan-modal]');
            if ($modal.length) {
                $modal.attr('aria-hidden', 'false').addClass('is-open');
                $('body').addClass('plan-modal-open');
                // Reset to plan selection view
                $('[data-plan-card]').show();
                $('[data-plan-checkout]').attr('hidden', true).hide();
            }
        },

        closePlanModal: function() {
            const $modal = $('[data-plan-modal]');
            if ($modal.length) {
                $modal.attr('aria-hidden', 'true').removeClass('is-open');
                $('body').removeClass('plan-modal-open');
            }
        }
    };

    // Make JPMorganProfile globally available
    window.JPMorganProfile = JPMorganProfile;

    // Add sophisticated easing function
    $.easing.easeInOutQuart = function(x, t, b, c, d) {
        if ((t /= d / 2) < 1) return c / 2 * t * t * t * t + b;
        return -c / 2 * ((t -= 2) * t * t * t - 2) + b;
    };

    // Utility function for debouncing
    function debounce(func, wait, immediate) {
        let timeout;
        return function executedFunction() {
            const context = this;
            const args = arguments;
            const later = function() {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            const callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    }

})(jQuery);

// Initialize on DOM ready
jQuery(document).ready(function($) {
    if (typeof JPMorganProfile !== 'undefined') {
        JPMorganProfile.init();
    }
});

// Add dynamic styles for enhanced interactions
const jpMorganStyles = `
    <style>
    .jp-expertise-highlight {
        transform: scale(1.02);
        box-shadow: 0 8px 32px rgba(0, 91, 185, 0.15);
        border-color: #005cb9 !important;
        transition: all 0.3s ease;
    }
    
    .jp-expertise-dimmed {
        opacity: 0.4;
        transform: scale(0.98);
        transition: all 0.3s ease;
    }
    
    .jp-card-hover-active {
        transform: translateY(-2px);
        transition: transform 0.2s ease;
    }
    
    .jp-modal-content {
        transform: translateY(1rem);
        opacity: 0;
        transition: all 0.3s ease;
    }
    
    .jp-btn-primary:hover {
        background: #1a1a1a !important;
        transform: translateY(-1px);
    }
    
    .jp-btn-secondary:hover {
        background: #f8f9fa !important;
        color: #000000 !important;
        border-color: #000000 !important;
    }
    </style>
`;

// Inject dynamic styles
jQuery('head').append(jpMorganStyles);
