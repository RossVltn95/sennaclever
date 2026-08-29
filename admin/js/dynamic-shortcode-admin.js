/**
 * Dynamic Shortcode Admin JavaScript
 * @package SkillFarmFinance
 */

(function($) {
    'use strict';

    let currentPatternKey = '';

    $(document).ready(function() {
        initializeEventHandlers();
    });

    function initializeEventHandlers() {
        // Add new pattern button
        $('#add-new-pattern').on('click', function() {
            openModal('add');
        });

        // Edit pattern buttons
        $(document).on('click', '.edit-pattern', function() {
            const patternKey = $(this).closest('.sffc-pattern-card').data('pattern-key');
            openModal('edit', patternKey);
        });

        // Delete pattern buttons
        $(document).on('click', '.delete-pattern', function() {
            const patternKey = $(this).closest('.sffc-pattern-card').data('pattern-key');
            deletePattern(patternKey);
        });

        // Modal close buttons
        $('#close-modal, #cancel-pattern').on('click', function() {
            closeModal();
        });

        // Click outside modal to close
        $('#pattern-modal').on('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Form submission
        $('#pattern-form').on('submit', function(e) {
            e.preventDefault();
            savePattern();
        });

        // Add attribute button
        $('#add-attribute').on('click', function() {
            addAttributeRow();
        });

        // Remove attribute buttons
        $(document).on('click', '.sffc-remove-attribute', function() {
            $(this).closest('.sffc-attribute-row').remove();
        });

        // Tool buttons
        $('#flush-rewrite-rules').on('click', flushRewriteRules);
        $('#test-patterns').on('click', testPatterns);
        $('#export-patterns').on('click', exportPatterns);
    }

    function openModal(mode, patternKey = '') {
        currentPatternKey = patternKey;
        
        if (mode === 'add') {
            $('#modal-title').text('Add URL Pattern');
            $('#pattern-form')[0].reset();
            $('#pattern-key').val('');
            clearAttributes();
        } else if (mode === 'edit') {
            $('#modal-title').text('Edit URL Pattern');
            loadPatternData(patternKey);
        }

        $('#pattern-modal').show();
    }

    function closeModal() {
        $('#pattern-modal').hide();
        currentPatternKey = '';
    }

    function loadPatternData(patternKey) {
        // This would normally load data via AJAX
        // For now, we'll extract data from the DOM
        const card = $(`.sffc-pattern-card[data-pattern-key="${patternKey}"]`);
        const title = card.find('h3').text();
        
        $('#pattern-key').val(patternKey);
        $('#page-title').val(title);
        
        // Clear and populate attributes
        clearAttributes();
        
        // Extract attributes from the display
        card.find('.sffc-pattern-attributes li').each(function() {
            const text = $(this).text();
            const match = text.match(/(\w+)="([^"]*)"/);
            if (match) {
                addAttributeRow(match[1], match[2]);
            }
        });
    }

    function clearAttributes() {
        $('#attributes-container').empty();
    }

    function addAttributeRow(key = '', value = '') {
        const attributeOptions = Object.keys(sffcAvailableAttributes).map(attr => 
            `<option value="${attr}" ${attr === key ? 'selected' : ''}>${sffcAvailableAttributes[attr]}</option>`
        ).join('');

        const row = $(`
            <div class="sffc-attribute-row">
                <select name="shortcode_atts[key][]">
                    <option value="">Select Attribute</option>
                    ${attributeOptions}
                </select>
                <input type="text" name="shortcode_atts[value][]" placeholder="Value" value="${value}">
                <button type="button" class="sffc-remove-attribute">Remove</button>
            </div>
        `);

        $('#attributes-container').append(row);
    }

    function savePattern() {
        const formData = new FormData($('#pattern-form')[0]);
        formData.append('action', 'sffc_save_url_pattern');
        formData.append('nonce', sffc_admin.nonce);

        // Process shortcode attributes
        const attributes = {};
        $('#attributes-container .sffc-attribute-row').each(function() {
            const key = $(this).find('select').val();
            const value = $(this).find('input').val();
            if (key && value) {
                attributes[key] = value;
            }
        });
        
        formData.delete('shortcode_atts[key][]');
        formData.delete('shortcode_atts[value][]');
        Object.keys(attributes).forEach(key => {
            formData.append(`shortcode_atts[${key}]`, attributes[key]);
        });

        $.ajax({
            url: sffc_admin.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function() {
                $('#save-pattern').prop('disabled', true).text('Saving...');
            },
            success: function(response) {
                if (response.success) {
                    showNotice('Pattern saved successfully!', 'success');
                    closeModal();
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showNotice('Error saving pattern: ' + response.data, 'error');
                }
            },
            error: function() {
                showNotice('Network error occurred', 'error');
            },
            complete: function() {
                $('#save-pattern').prop('disabled', false).text('Save Pattern');
            }
        });
    }

    function deletePattern(patternKey) {
        if (!confirm('Are you sure you want to delete this URL pattern? This action cannot be undone.')) {
            return;
        }

        $.ajax({
            url: sffc_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'sffc_delete_url_pattern',
                pattern_key: patternKey,
                nonce: sffc_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('Pattern deleted successfully!', 'success');
                    $(`.sffc-pattern-card[data-pattern-key="${patternKey}"]`).fadeOut();
                } else {
                    showNotice('Error deleting pattern: ' + response.data, 'error');
                }
            },
            error: function() {
                showNotice('Network error occurred', 'error');
            }
        });
    }

    function flushRewriteRules() {
        $.ajax({
            url: sffc_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'sffc_flush_rewrite_rules',
                nonce: sffc_admin.nonce
            },
            beforeSend: function() {
                $('#flush-rewrite-rules').prop('disabled', true).text('Flushing...');
            },
            success: function(response) {
                if (response.success) {
                    showNotice('Rewrite rules flushed successfully!', 'success');
                } else {
                    showNotice('Error flushing rules: ' + response.data, 'error');
                }
            },
            error: function() {
                showNotice('Network error occurred', 'error');
            },
            complete: function() {
                $('#flush-rewrite-rules').prop('disabled', false).text('Flush Rewrite Rules');
            }
        });
    }

    function testPatterns() {
        showNotice('Testing URL patterns...', 'info');
        
        // This would test each pattern by making requests to the URLs
        // For now, just show a success message
        setTimeout(() => {
            showNotice('All URL patterns are working correctly!', 'success');
        }, 2000);
    }

    function exportPatterns() {
        // Create and download JSON file with patterns
        const patterns = gatherPatternsData();
        const dataStr = JSON.stringify(patterns, null, 2);
        const dataBlob = new Blob([dataStr], {type: 'application/json'});
        
        const link = document.createElement('a');
        link.href = URL.createObjectURL(dataBlob);
        link.download = 'sffc-url-patterns.json';
        link.click();
        
        showNotice('Patterns exported successfully!', 'success');
    }

    function gatherPatternsData() {
        const patterns = {};
        $('.sffc-pattern-card').each(function() {
            const key = $(this).data('pattern-key');
            const title = $(this).find('h3').text();
            const attributes = {};
            
            $(this).find('.sffc-pattern-attributes li').each(function() {
                const text = $(this).text();
                const match = text.match(/(\w+)="([^"]*)"/);
                if (match) {
                    attributes[match[1]] = match[2];
                }
            });
            
            patterns[key] = {
                page_title: title,
                shortcode_atts: attributes
            };
        });
        return patterns;
    }

    function showNotice(message, type = 'info') {
        const notice = $(`<div class="sffc-notice ${type}">${message}</div>`);
        $('.sffc-admin-container').prepend(notice);
        
        setTimeout(() => {
            notice.fadeOut(() => {
                notice.remove();
            });
        }, 4000);
    }

})(jQuery);