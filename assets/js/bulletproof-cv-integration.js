/**
 * Bulletproof CV Tailoring Integration
 * 
 * This replaces the problematic CV tailoring with a reliable implementation
 */

(function($) {
    'use strict';
    
    function ultimateCvAvailable() {
        return (
            window.CVTailoringManager &&
            typeof window.CVTailoringManager.handleTailorClick === 'function'
        );
    }

    function normalizeJobData(jobData) {
        jobData = jobData || {};
        return {
            job_title: jobData.title || jobData.job_title || 'Position',
            company: jobData.company || jobData.company_name || 'Company',
            location: jobData.location || jobData.job_location || '',
            job_description: jobData.description || jobData.job_description || '',
            job_id: jobData.id || jobData.job_id || ''
        };
    }

    // Override the existing CV tailoring function
    window.startBulletproofCVTailoring = function(jobData) {
        console.log('Starting bulletproof CV tailoring...', jobData);
        
        if (ultimateCvAvailable()) {
            const normalized = normalizeJobData(jobData);
            if (typeof window.CVTailoringManager.tailorCV === 'function') {
                window.CVTailoringManager.tailorCV(normalized);
            } else {
                // Fall back to the standard handler click path if tailorCV is unavailable
                window.CVTailoringManager.handleTailorClick &&
                    window.CVTailoringManager.handleTailorClick($(document.activeElement));
            }
            return;
        }
        
        // Check if CV exists
        if (window.BulletproofCVTailoring) {
            window.BulletproofCVTailoring.checkStatus(function(response) {
                if (response.success && response.data.has_cv) {
                    // CV exists, proceed with tailoring
                    performTailoring(jobData);
                } else {
                    // No CV, show upload prompt
                    showCVUploadPrompt(jobData);
                }
            });
        } else {
            // Fallback to traditional AJAX
            performTailoringFallback(jobData);
        }
    };
    
    function performTailoring(jobData) {
        // Show progress
        showTailoringProgress();
        
        // Call bulletproof tailoring
        window.BulletproofCVTailoring.tailorCV(jobData, function(response) {
            if (response.success) {
                displayTailoredResults(response.data);
            } else {
                showError(response.data.message || 'Tailoring failed. Please try again.');
            }
        });
    }
    
    function performTailoringFallback(jobData) {
        // Direct AJAX call as fallback
        $.ajax({
            url: window.sffc_ajax?.url || '/wp-admin/admin-ajax.php',
            type: 'POST',
            data: {
                action: 'bulletproof_cv_tailor',
                nonce: window.sffc_ajax?.nonce || '',
                job_title: jobData.title || jobData.job_title || '',
                company: jobData.company || jobData.company_name || '',
                job_description: jobData.description || jobData.job_description || '',
                job_id: jobData.id || jobData.job_id || ''
            },
            success: function(response) {
                if (response.success) {
                    displayTailoredResults(response.data);
                } else {
                    if (response.data && response.data.message) {
                        showError(response.data.message);
                    } else {
                        showError('CV tailoring failed. Please try again.');
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('CV Tailoring Error:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText,
                    error: error
                });
                
                // Provide specific error messages
                let errorMessage = 'Error tailoring CV. ';
                
                if (xhr.status === 500) {
                    errorMessage += 'Server error occurred. Please refresh and try again.';
                } else if (xhr.status === 404) {
                    errorMessage += 'Service not found. Please contact support.';
                } else if (xhr.status === 0) {
                    errorMessage += 'Connection failed. Please check your internet.';
                } else {
                    errorMessage += 'Please try again. (Error: ' + xhr.status + ')';
                }
                
                showError(errorMessage);
            }
        });
    }
    
    function showCVUploadPrompt(jobData) {
        const uploadHtml = `
            <div id="cv-upload-modal" style="position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 10000; display: flex; align-items: center; justify-content: center;">
                <div style="background: white; padding: 2rem; border-radius: 10px; max-width: 500px; width: 90%;">
                    <h3 style="margin-bottom: 1rem;">Upload Your CV First</h3>
                    <p style="margin-bottom: 1rem;">Please upload your CV to tailor it for this position.</p>
                    
                    <input type="file" id="cv-file-input" accept=".pdf,.doc,.docx,.txt" style="margin-bottom: 1rem;">
                    
                    <div style="display: flex; gap: 1rem;">
                        <button id="upload-cv-btn" style="padding: 0.5rem 1rem; background: #2D6A4F; color: white; border: none; border-radius: 5px; cursor: pointer;">
                            Upload CV
                        </button>
                        <button id="cancel-upload-btn" style="padding: 0.5rem 1rem; background: #666; color: white; border: none; border-radius: 5px; cursor: pointer;">
                            Cancel
                        </button>
                    </div>
                    
                    <div id="upload-status" style="margin-top: 1rem;"></div>
                </div>
            </div>
        `;
        
        $('body').append(uploadHtml);
        
        $('#upload-cv-btn').on('click', function() {
            const fileInput = document.getElementById('cv-file-input');
            const file = fileInput.files[0];
            
            if (!file) {
                $('#upload-status').html('<p style="color: red;">Please select a file</p>');
                return;
            }
            
            $('#upload-status').html('<p>Uploading...</p>');
            
            if (window.BulletproofCVTailoring) {
                window.BulletproofCVTailoring.uploadCV(file, function(response) {
                    if (response.success) {
                        $('#upload-status').html('<p style="color: green;">CV uploaded successfully!</p>');
                        setTimeout(function() {
                            $('#cv-upload-modal').remove();
                            performTailoring(jobData);
                        }, 1500);
                    } else {
                        $('#upload-status').html('<p style="color: red;">' + (response.data.message || 'Upload failed') + '</p>');
                    }
                });
            } else {
                // Fallback upload
                uploadCVFallback(file, jobData);
            }
        });
        
        $('#cancel-upload-btn').on('click', function() {
            $('#cv-upload-modal').remove();
        });
    }
    
    function uploadCVFallback(file, jobData) {
        const formData = new FormData();
        formData.append('action', 'bulletproof_cv_upload');
        formData.append('nonce', window.sffc_ajax?.nonce || '');
        formData.append('cv_file', file);
        
        $.ajax({
            url: window.sffc_ajax?.url || '/wp-admin/admin-ajax.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $('#upload-status').html('<p style="color: green;">CV uploaded successfully!</p>');
                    setTimeout(function() {
                        $('#cv-upload-modal').remove();
                        performTailoring(jobData);
                    }, 1500);
                } else {
                    $('#upload-status').html('<p style="color: red;">' + (response.data.message || 'Upload failed') + '</p>');
                }
            },
            error: function() {
                $('#upload-status').html('<p style="color: red;">Upload failed. Please try again.</p>');
            }
        });
    }
    
    function showTailoringProgress() {
        const progressHtml = `
            <div id="tailoring-progress" style="position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 10000; display: flex; align-items: center; justify-content: center;">
                <div style="background: white; padding: 2rem; border-radius: 10px; text-align: center;">
                    <div class="spinner" style="border: 3px solid #f3f3f3; border-top: 3px solid #2D6A4F; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 1rem;"></div>
                    <p>Tailoring your CV for this position...</p>
                    <p style="font-size: 0.9em; color: #666;">This may take a few moments</p>
                </div>
            </div>
            <style>
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
            </style>
        `;
        
        $('body').append(progressHtml);
    }
    
    function displayTailoredResults(data) {
        $('#tailoring-progress').remove();
        
        const resultsHtml = `
            <div id="tailored-results" style="position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 10000; overflow: auto;">
                <div style="background: white; margin: 2rem auto; padding: 2rem; border-radius: 10px; max-width: 800px; width: 90%;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <h2 style="margin: 0;">CV Tailoring Complete!</h2>
                        <button id="close-results" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
                    </div>
                    
                    <div style="background: #f0f8f5; padding: 1rem; border-radius: 5px; margin-bottom: 1rem;">
                        <h3>Match Score: ${data.match_score || 75}%</h3>
                        <p>Your CV has been optimized for: <strong>${data.job_title || 'this position'}</strong> at <strong>${data.company || 'this company'}</strong></p>
                    </div>
                    
                    ${data.recommendations && data.recommendations.length > 0 ? `
                        <div style="margin-bottom: 1rem;">
                            <h3>Recommendations:</h3>
                            <ul>
                                ${data.recommendations.map(rec => `<li>${rec}</li>`).join('')}
                            </ul>
                        </div>
                    ` : ''}
                    
                    ${data.improvements && data.improvements.length > 0 ? `
                        <div style="margin-bottom: 1rem;">
                            <h3>Suggested Improvements:</h3>
                            <ul>
                                ${data.improvements.map(imp => `<li>${imp}</li>`).join('')}
                            </ul>
                        </div>
                    ` : ''}
                    
                    <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                        <button id="download-tailored-cv" style="padding: 0.75rem 1.5rem; background: #2D6A4F; color: white; border: none; border-radius: 5px; cursor: pointer;">
                            Download Tailored CV
                        </button>
                        <button id="apply-with-cv" style="padding: 0.75rem 1.5rem; background: #1e5a8a; color: white; border: none; border-radius: 5px; cursor: pointer;">
                            Apply with This CV
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        $('body').append(resultsHtml);
        
        $('#close-results').on('click', function() {
            $('#tailored-results').remove();
        });
        
        $('#download-tailored-cv').on('click', function() {
            // Implement download functionality
            alert('Download feature coming soon!');
        });
        
        $('#apply-with-cv').on('click', function() {
            // Implement apply functionality
            alert('Apply feature coming soon!');
        });
    }
    
    function showError(message) {
        $('#tailoring-progress').remove();
        $('#cv-upload-modal').remove();
        
        const errorHtml = `
            <div id="error-modal" style="position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 10000; display: flex; align-items: center; justify-content: center;">
                <div style="background: white; padding: 2rem; border-radius: 10px; max-width: 500px; width: 90%;">
                    <h3 style="color: #d32f2f; margin-bottom: 1rem;">Error</h3>
                    <p>${message}</p>
                    <button id="close-error" style="margin-top: 1rem; padding: 0.5rem 1rem; background: #2D6A4F; color: white; border: none; border-radius: 5px; cursor: pointer;">
                        OK
                    </button>
                </div>
            </div>
        `;
        
        $('body').append(errorHtml);
        
        $('#close-error').on('click', function() {
            $('#error-modal').remove();
        });
    }
    
    // Replace existing CV tailoring triggers
    $(document).ready(function() {
        $(document).on('click', '.sffc-btn-tailor, .tailor-cv-btn, .cv-tailor-button', function(e) {
            if (ultimateCvAvailable()) {
                return; // Allow the Ultimate CV manager to handle the event
            }

            e.preventDefault();
            const $btn = $(this);
            const jobData = {
                id: $btn.data('job-id') || $btn.data('id') || '',
                title: $btn.data('job-title') || $btn.data('title') || $btn.closest('.job-card').find('.job-title').text() || '',
                company: $btn.data('company') || $btn.closest('.job-card').find('.company-name').text() || '',
                description: $btn.data('description') || ''
            };
            
            window.startBulletproofCVTailoring(jobData);
        });
        
        // Also override window function if it exists
        if (typeof window.openCVTailorModal !== 'undefined') {
            window.openCVTailorModal = window.startBulletproofCVTailoring;
        }

        if (!ultimateCvAvailable()) {
            console.log('✅ Bulletproof CV Tailoring System Active');
        } else {
            console.log('ℹ️ Bulletproof CV integration delegating to Ultimate CV Tailoring');
        }
    });

})(jQuery);
