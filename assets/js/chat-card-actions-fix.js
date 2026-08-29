/**
 * Chat Card Actions Fix
 * Ensures tailor CV and save buttons work properly in job-cards-in-chat
 * both in normal and blur-mode
 */

(function($) {
    'use strict';
    
    // Wait for DOM to be ready
    $(document).ready(function() {
        initializeChatCardActions();
    });
    
    function initializeChatCardActions() {
        console.log('Initializing chat card actions...');
        
        // Fix tailor CV functionality for chat cards
        $(document).on('click', '.job-cards-in-chat .chat-card-actions .sffc-btn-tailor, .job-cards-in-chat.blur-mode .chat-card-actions .sffc-btn-tailor', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $button = $(this);
            const $jobCard = $button.closest('.sffc-match-card, .job-card, .job-card-vogue');
            
            if ($jobCard.length === 0) {
                console.error('Could not find job card for tailor CV button');
                return;
            }
            
            // Get job data
            const jobData = extractJobDataFromCard($jobCard);
            
            if (!jobData.id) {
                console.error('Could not extract job ID from card');
                return;
            }
            
            // Trigger CV tailoring
            handleTailorCV(jobData, $button);
        });
        
        // Fix save/interested functionality for chat cards
        $(document).on('click', '.job-cards-in-chat .chat-card-actions .sffc-btn-interested, .job-cards-in-chat.blur-mode .chat-card-actions .sffc-btn-interested', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $button = $(this);
            const $jobCard = $button.closest('.sffc-match-card, .job-card, .job-card-vogue');
            
            if ($jobCard.length === 0) {
                console.error('Could not find job card for save button');
                return;
            }
            
            // Get job data
            const jobData = extractJobDataFromCard($jobCard);
            
            if (!jobData.id) {
                console.error('Could not extract job ID from card');
                return;
            }
            
            // Toggle save state
            handleSaveJob(jobData, $button);
        });
        
        // Fix apply functionality for chat cards
        $(document).on('click', '.job-cards-in-chat .chat-card-actions .btn-apply, .job-cards-in-chat.blur-mode .chat-card-actions .btn-apply', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $button = $(this);
            const $jobCard = $button.closest('.sffc-match-card, .job-card, .job-card-vogue');
            
            if ($jobCard.length === 0) {
                console.error('Could not find job card for apply button');
                return;
            }
            
            // Get job data
            const jobData = extractJobDataFromCard($jobCard);
            
            if (!jobData.apply_url && !jobData.applyUrl) {
                console.error('No apply URL found for job');
                return;
            }
            
            // Open apply URL
            const applyUrl = jobData.apply_url || jobData.applyUrl;
            window.open(applyUrl, '_blank');
        });
        
        // Watch for job cards being added to chat
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) {
                        const $node = $(node);
                        
                        // Check if it's a job cards container
                        if ($node.hasClass('job-cards-in-chat') || $node.find('.job-cards-in-chat').length > 0) {
                            console.log('New job cards detected in chat, ensuring actions are connected...');
                            
                            // Ensure actions are properly initialized
                            setTimeout(() => {
                                initializeCardActions($node);
                            }, 100);
                        }
                    }
                });
            });
        });
        
        // Observe the messages container
        const messagesContainer = document.getElementById('senna-messages') || document.querySelector('.senna-messages');
        if (messagesContainer) {
            observer.observe(messagesContainer, {
                childList: true,
                subtree: true
            });
        }
    }
    
    function extractJobDataFromCard($jobCard) {
        const jobData = {};
        
        // Try to get job ID from various sources
        jobData.id = $jobCard.data('job-id') || 
                   $jobCard.data('id') || 
                   $jobCard.find('[data-job-id]').data('job-id') ||
                   $jobCard.attr('data-job-id');
        
        // Get other job details
        jobData.title = $jobCard.find('.job-title, .position-title').text().trim();
        jobData.company = $jobCard.find('.company-name, .job-company').text().trim();
        jobData.location = $jobCard.find('.job-location, .location').text().trim();
        
        // Get apply URL
        const $applyLink = $jobCard.find('a[href*="apply"], .btn-apply[data-url]');
        if ($applyLink.length > 0) {
            jobData.apply_url = $applyLink.attr('href') || $applyLink.data('url');
        }
        
        return jobData;
    }
    
    function handleTailorCV(jobData, $button) {
        const originalText = $button.text();
        $button.text('Tailoring...').prop('disabled', true);
        
        // Check if CV tailoring function exists
        if (typeof window.tailorCVForJob === 'function') {
            window.tailorCVForJob(jobData);
        } else if (typeof window.handleCVTailoring === 'function') {
            window.handleCVTailoring(jobData.id, jobData);
        } else {
            // Fallback to AJAX call
            $.ajax({
                url: window.sffc_ajax?.ajaxurl || '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    action: 'sffc_tailor_cv',
                    job_id: jobData.id,
                    job_title: jobData.title,
                    company: jobData.company,
                    nonce: window.sffc_ajax?.nonce || ''
                },
                success: function(response) {
                    if (response.success) {
                        $button.text('CV Tailored!').removeClass('sffc-btn-tailor').addClass('tailored');
                        
                        // Show success message
                        if (typeof window.showNotification === 'function') {
                            window.showNotification('CV tailored successfully!', 'success');
                        }
                        
                        setTimeout(() => {
                            $button.text(originalText).prop('disabled', false);
                        }, 3000);
                    } else {
                        throw new Error(response.data || 'CV tailoring failed');
                    }
                },
                error: function() {
                    $button.text('Error').addClass('error');
                    setTimeout(() => {
                        $button.text(originalText).prop('disabled', false).removeClass('error');
                    }, 2000);
                }
            });
        }
    }
    
    function handleSaveJob(jobData, $button) {
        const isAlreadySaved = $button.hasClass('added') || $button.hasClass('saved');
        
        if (isAlreadySaved) {
            // Remove from saved jobs
            removeSavedJob(jobData.id);
            $button.removeClass('added saved').text('Save');
        } else {
            // Add to saved jobs
            addSavedJob(jobData);
            $button.addClass('added').text('Saved');
        }
    }
    
    function addSavedJob(jobData) {
        try {
            const savedJobs = JSON.parse(localStorage.getItem('sffc_saved_jobs') || '[]');
            
            // Check if job is already saved
            if (!savedJobs.find(job => job.id === jobData.id)) {
                savedJobs.push({
                    id: jobData.id,
                    title: jobData.title,
                    company: jobData.company,
                    location: jobData.location,
                    saved_at: new Date().toISOString()
                });
                
                localStorage.setItem('sffc_saved_jobs', JSON.stringify(savedJobs));
                
                // Trigger saved jobs update event
                $(document).trigger('saved_jobs:updated', [savedJobs]);
                
                // Show notification
                if (typeof window.showNotification === 'function') {
                    window.showNotification('Job saved to your list!', 'success');
                }
            }
        } catch (e) {
            console.error('Failed to save job:', e);
        }
    }
    
    function removeSavedJob(jobId) {
        try {
            const savedJobs = JSON.parse(localStorage.getItem('sffc_saved_jobs') || '[]');
            const updatedJobs = savedJobs.filter(job => job.id !== jobId);
            
            localStorage.setItem('sffc_saved_jobs', JSON.stringify(updatedJobs));
            
            // Trigger saved jobs update event
            $(document).trigger('saved_jobs:updated', [updatedJobs]);
            
            // Show notification
            if (typeof window.showNotification === 'function') {
                window.showNotification('Job removed from saved list', 'info');
            }
        } catch (e) {
            console.error('Failed to remove saved job:', e);
        }
    }
    
    function initializeCardActions($container) {
        // Ensure buttons have proper classes and event handlers
        const $chatCards = $container.find('.job-cards-in-chat .sffc-match-card, .job-cards-in-chat.blur-mode .sffc-match-card');
        
        $chatCards.each(function() {
            const $card = $(this);
            const $actions = $card.find('.chat-card-actions');
            
            if ($actions.length === 0) {
                // Add actions container if missing
                const actionsHtml = `
                    <div class="chat-card-actions">
                        <button class="sffc-btn-tailor">Tailor CV</button>
                        <button class="sffc-btn-interested">Save</button>
                        <button class="btn-apply">Apply</button>
                    </div>
                `;
                $card.append(actionsHtml);
            }
            
            // Check if job is already saved
            const jobId = $card.data('job-id') || $card.attr('data-job-id');
            if (jobId && isJobSaved(jobId)) {
                $card.find('.sffc-btn-interested').addClass('added').text('Saved');
            }
        });
    }
    
    function isJobSaved(jobId) {
        try {
            const savedJobs = JSON.parse(localStorage.getItem('sffc_saved_jobs') || '[]');
            return savedJobs.some(job => job.id === jobId);
        } catch (e) {
            return false;
        }
    }
    
    // Make functions available globally if needed
    window.chatCardActions = {
        initializeCardActions: initializeCardActions,
        handleTailorCV: handleTailorCV,
        handleSaveJob: handleSaveJob
    };
    
})(jQuery);