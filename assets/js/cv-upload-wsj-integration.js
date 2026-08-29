/**
 * CV Upload WSJ Integration
 * Connects CV upload to WSJ renderer for beautiful display
 */

(function($) {
    'use strict';
    
    // Initialize WSJ CV renderer when document is ready
    $(document).ready(function() {
        initializeWSJCVUpload();
    });
    
    function initializeWSJCVUpload() {
        // Override existing CV upload handler
        const originalUploadHandler = window.handleCVUpload;
        
        window.handleCVUpload = function(file, textContent) {
            console.log('WSJ CV Upload Integration Active');
            
            // Show WSJ preview instead of old system
            if (textContent) {
                showWSJPreview(textContent);
            } else if (file) {
                // Read file and extract text
                const reader = new FileReader();
                reader.onload = function(e) {
                    const content = e.target.result;
                    showWSJPreview(content);
                };
                reader.readAsText(file);
            }
            
            // Call original handler if it exists (for backend processing)
            if (typeof originalUploadHandler === 'function') {
                originalUploadHandler(file, textContent);
            }
        };
        
        // Override CV parse success handler
        $(document).on('cv_parse_success', function(e, data) {
            console.log('CV Parse Success - Using WSJ Display');
            
            // Show success with WSJ formatting
            const message = `
                <div class="wsj-cv-success-message">
                    <div class="wsj-success-icon">✅</div>
                    <div class="wsj-success-content">
                        <h3>CV Successfully Parsed!</h3>
                        <p>Found ${data.experience ? data.experience.length : 0} work experiences and ${data.education ? data.education.length : 0} education entries.</p>
                    </div>
                </div>
            `;
            
            // Update UI elements
            $('.cv-upload-status').html(message);
            
            // Show WSJ preview if we have the data
            if (data.text_content) {
                showWSJPreview(data.text_content);
            }
        });
    }
    
    function showWSJPreview(cvText) {
        // Create WSJ preview container if it doesn't exist
        if (!$('.wsj-cv-preview-container').length) {
            const previewHTML = `
                <div class="wsj-cv-preview-container" style="display: none;">
                    <div class="wsj-cv-preview-header">
                        <h2>CV Preview</h2>
                        <button class="wsj-cv-close-preview">&times;</button>
                    </div>
                    <div class="wsj-cv-container"></div>
                </div>
            `;
            
            $('body').append(previewHTML);
            
            // Add close handler
            $('.wsj-cv-close-preview').on('click', function() {
                $('.wsj-cv-preview-container').fadeOut();
            });
        }
        
        // Initialize WSJ renderer if available
        if (typeof WSJCVRendererUltimate !== 'undefined') {
            const renderer = new WSJCVRendererUltimate({
                container: '.wsj-cv-container',
                editable: false,
                animations: true
            });
            
            // Parse and render CV
            renderer.updateFromText(cvText);
            
            // Show preview with animation
            $('.wsj-cv-preview-container').fadeIn();
            
            // Update status message
            const parsedData = renderer.getData();
            updateStatusMessage(parsedData);
            
        } else if (typeof WSJCVRenderer !== 'undefined') {
            // Fallback to basic WSJ renderer
            const renderer = new WSJCVRenderer({
                container: '.wsj-cv-container',
                editable: false
            });
            
            renderer.updateFromText(cvText);
            $('.wsj-cv-preview-container').fadeIn();
            
        } else {
            console.warn('WSJ CV Renderer not loaded');
            // Fallback to simple text display
            $('.wsj-cv-container').html(`<pre>${escapeHtml(cvText)}</pre>`);
            $('.wsj-cv-preview-container').fadeIn();
        }
    }
    
    function updateStatusMessage(parsedData) {
        const experienceCount = parsedData.experience ? parsedData.experience.length : 0;
        const educationCount = parsedData.education ? parsedData.education.length : 0;
        const skillsCount = parsedData.skills ? parsedData.skills.length : 0;
        
        const statusHTML = `
            <div class="wsj-parse-status">
                <div class="wsj-status-item">
                    <span class="wsj-status-label">Name:</span>
                    <span class="wsj-status-value">${parsedData.name || 'Not detected'}</span>
                </div>
                <div class="wsj-status-item">
                    <span class="wsj-status-label">Email:</span>
                    <span class="wsj-status-value">${parsedData.contact?.email || 'Not detected'}</span>
                </div>
                <div class="wsj-status-item">
                    <span class="wsj-status-label">Experience:</span>
                    <span class="wsj-status-value">${experienceCount} positions</span>
                </div>
                <div class="wsj-status-item">
                    <span class="wsj-status-label">Education:</span>
                    <span class="wsj-status-value">${educationCount} entries</span>
                </div>
                <div class="wsj-status-item">
                    <span class="wsj-status-label">Skills:</span>
                    <span class="wsj-status-value">${skillsCount} skills</span>
                </div>
            </div>
        `;
        
        // Insert status after success message
        if ($('.wsj-cv-success-message').length) {
            $('.wsj-cv-success-message').after(statusHTML);
        }
    }
    
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }
    
    // Add styles for WSJ preview
    const styles = `
        <style>
        .wsj-cv-preview-container {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 90%;
            max-width: 900px;
            height: 80vh;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            z-index: 10000;
            overflow: hidden;
        }
        
        .wsj-cv-preview-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 2px solid #f0f0f0;
            background: #faf7f2;
        }
        
        .wsj-cv-preview-header h2 {
            margin: 0;
            color: #1a472a;
            font-size: 24px;
        }
        
        .wsj-cv-close-preview {
            background: none;
            border: none;
            font-size: 32px;
            color: #666;
            cursor: pointer;
            padding: 0;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .wsj-cv-close-preview:hover {
            color: #1a472a;
        }
        
        .wsj-cv-container {
            height: calc(100% - 80px);
            overflow-y: auto;
            padding: 20px;
        }
        
        .wsj-cv-success-message {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px;
            background: linear-gradient(135deg, #f0f9f4, #faf7f2);
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .wsj-success-icon {
            font-size: 48px;
        }
        
        .wsj-success-content h3 {
            margin: 0 0 8px 0;
            color: #1a472a;
            font-size: 20px;
        }
        
        .wsj-success-content p {
            margin: 0;
            color: #666;
        }
        
        .wsj-parse-status {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            padding: 20px;
            background: white;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .wsj-status-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .wsj-status-label {
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .wsj-status-value {
            font-size: 16px;
            color: #1a472a;
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .wsj-cv-preview-container {
                width: 100%;
                height: 100%;
                max-width: none;
                border-radius: 0;
            }
        }
        </style>
    `;
    
    $('head').append(styles);
    
})(jQuery);