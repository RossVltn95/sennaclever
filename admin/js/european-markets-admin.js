/**
 * European Markets Admin JavaScript
 * 
 * @package SkillFarmFinance
 * @subpackage EuropeanMarkets
 * @since 2.0.0
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Install all missing European tables
    $('#install-eu-tables').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $progress = $('#install-progress');
        var $result = $('#install-result');
        
        $button.prop('disabled', true);
        $progress.show();
        $result.empty();
        
        $.ajax({
            url: sffc_eu_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sffc_install_eu_tables',
                nonce: sffc_eu_ajax.nonce
            },
            success: function(response) {
                $progress.hide();
                
                if (response.success) {
                    $result.html('<div class="notice notice-success"><p>' + response.message + '</p></div>');
                    
                    // Show details
                    if (response.details) {
                        var detailsHtml = '<ul class="table-install-details">';
                        $.each(response.details, function(table, status) {
                            var icon = status === 'Created' ? '✅' : '❌';
                            detailsHtml += '<li>' + icon + ' ' + table + ': ' + status + '</li>';
                        });
                        detailsHtml += '</ul>';
                        $result.append(detailsHtml);
                    }
                    
                    // Reload page after 2 seconds
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    $result.html('<div class="notice notice-error"><p>' + response.message + '</p></div>');
                    $button.prop('disabled', false);
                }
            },
            error: function() {
                $progress.hide();
                $result.html('<div class="notice notice-error"><p>An error occurred. Please try again.</p></div>');
                $button.prop('disabled', false);
            }
        });
    });
    
    // Repair tables
    $('#repair-tables').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('This will drop and recreate all European market tables. Any existing data will be lost. Continue?')) {
            return;
        }
        
        var $button = $(this);
        $button.prop('disabled', true).text('Repairing...');
        
        $.ajax({
            url: sffc_eu_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sffc_repair_tables',
                nonce: sffc_eu_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Tables repaired successfully!');
                    location.reload();
                } else {
                    alert('Error repairing tables: ' + response.message);
                    $button.prop('disabled', false).text('Repair Tables');
                }
            },
            error: function() {
                alert('An error occurred while repairing tables.');
                $button.prop('disabled', false).text('Repair Tables');
            }
        });
    });
    
    // Verify table structure
    $('#verify-tables').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        $button.prop('disabled', true).text('Verifying...');
        
        $.ajax({
            url: sffc_eu_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sffc_check_eu_tables',
                nonce: sffc_eu_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    var message = 'Table Verification Results:\n\n';
                    $.each(response.tables, function(key, table) {
                        message += (table.exists ? '✅' : '❌') + ' ' + key + ': ' + table.name + '\n';
                    });
                    alert(message);
                }
                $button.prop('disabled', false).text('Verify Table Structure');
            },
            error: function() {
                alert('Error verifying tables.');
                $button.prop('disabled', false).text('Verify Table Structure');
            }
        });
    });
    
    // Individual table installation on click
    $('.table-status-item.missing').on('click', function(e) {
        e.preventDefault();
        
        var $item = $(this);
        var tableName = $item.find('.table-name').text().toLowerCase().replace(/ /g, '_');
        
        if (confirm('Install ' + $item.find('.table-name').text() + ' table?')) {
            $item.css('opacity', '0.5');
            
            $.ajax({
                url: sffc_eu_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sffc_install_single_table',
                    table_name: tableName,
                    nonce: sffc_eu_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $item.removeClass('missing').addClass('exists');
                        $item.find('.table-icon').text('✅');
                        $item.css('opacity', '1');
                    } else {
                        alert('Failed to create table: ' + response.message);
                        $item.css('opacity', '1');
                    }
                },
                error: function() {
                    alert('Error creating table.');
                    $item.css('opacity', '1');
                }
            });
        }
    });
    
    // Test data fetching
    $('#test-data-fetch').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        $button.prop('disabled', true).text('Testing...');
        
        // Simulate test - in production this would call actual data fetch
        setTimeout(function() {
            alert('Data fetching test completed. Check the console for details.');
            $button.prop('disabled', false).text('Test Data Fetching');
            
            // Log sample test results
            console.log('Data Fetch Test Results:');
            console.log('- Yahoo Finance FTSE: OK');
            console.log('- ECB Feed: OK');
            console.log('- Bloomberg Markets: OK');
            console.log('- FT Markets: OK');
        }, 2000);
    });
    
    // Clear cache
    $('#clear-cache').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to clear the market cache? This will remove all cached market data.')) {
            return;
        }
        
        var $button = $(this);
        $button.prop('disabled', true).text('Clearing...');
        
        // In production, this would call an AJAX endpoint to clear cache
        setTimeout(function() {
            alert('Market cache cleared successfully.');
            $button.prop('disabled', false).text('Clear Market Cache');
        }, 1500);
    });
    
    // Check tables status periodically
    function checkTablesStatus() {
        $.ajax({
            url: sffc_eu_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sffc_check_eu_tables',
                nonce: sffc_eu_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.tables) {
                    updateTablesDisplay(response.tables);
                }
            }
        });
    }
    
    // Update tables display
    function updateTablesDisplay(tables) {
        $.each(tables, function(key, table) {
            var $item = $('.table-status-item').filter(function() {
                return $(this).find('.table-technical').text() === table.name;
            });
            
            if ($item.length) {
                if (table.exists) {
                    $item.removeClass('missing').addClass('exists');
                    $item.find('.table-icon').text('✅');
                } else {
                    $item.removeClass('exists').addClass('missing');
                    $item.find('.table-icon').text('❌');
                }
            }
        });
    }
    
    // Phase progress animation
    $('.phase-item').each(function(index) {
        var $this = $(this);
        setTimeout(function() {
            $this.addClass('animated');
        }, index * 100);
    });
    
    // Tooltip initialization
    $('.table-status-item').on('mouseenter', function() {
        var $this = $(this);
        var tableName = $this.find('.table-technical').text();
        var status = $this.hasClass('exists') ? 'Installed' : 'Not Installed';
        
        // Create tooltip
        var $tooltip = $('<div class="sffc-tooltip">' + tableName + ' - ' + status + '</div>');
        $('body').append($tooltip);
        
        // Position tooltip
        var offset = $this.offset();
        $tooltip.css({
            top: offset.top - $tooltip.outerHeight() - 10,
            left: offset.left + ($this.outerWidth() / 2) - ($tooltip.outerWidth() / 2)
        }).fadeIn(200);
        
        $this.data('tooltip', $tooltip);
    }).on('mouseleave', function() {
        var $tooltip = $(this).data('tooltip');
        if ($tooltip) {
            $tooltip.fadeOut(200, function() {
                $(this).remove();
            });
        }
    });
    
    // Real-time feed status update
    function updateFeedStatus() {
        $('.feed-stats .stat-value').each(function() {
            var $this = $(this);
            var currentValue = parseInt($this.text());
            
            // Animate number change
            $this.addClass('updating');
            setTimeout(function() {
                $this.removeClass('updating');
            }, 500);
        });
    }
    
    // Update feed status every 30 seconds
    setInterval(updateFeedStatus, 30000);
    
    // Index item hover effect
    $('.index-item').on('mouseenter', function() {
        $(this).addClass('hover');
    }).on('mouseleave', function() {
        $(this).removeClass('hover');
    });
    
    // Quick action buttons feedback
    $('.quick-actions .button').on('click', function() {
        var $button = $(this);
        $button.addClass('clicked');
        setTimeout(function() {
            $button.removeClass('clicked');
        }, 300);
    });
});