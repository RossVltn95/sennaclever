<?php
/**
 * PE Admin Tools
 * Admin interface for managing PE Intelligence system
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>Private Equity Intelligence Manager</h1>
    
    <div class="pe-admin-container" style="max-width: 1200px; margin: 20px 0;">
        
        <!-- Quick Links if post types exist -->
        <?php
        $post_types_exist = post_type_exists('sffc_pe_news') && post_type_exists('sffc_pe_deal') && post_type_exists('sffc_market_intel');
        if ($post_types_exist) {
        ?>
        <div class="pe-quick-links" style="background: #e7f5ff; padding: 15px; margin-bottom: 20px; border: 1px solid #0073aa; border-radius: 4px;">
            <h3 style="margin-top: 0;">Quick Links to PE Content:</h3>
            <p>
                <a href="<?php echo admin_url('edit.php?post_type=sffc_company'); ?>" class="button button-primary">🏢 View PE Firms</a>
                <a href="<?php echo admin_url('edit.php?post_type=sffc_pe_news'); ?>" class="button">📰 View PE News</a>
                <a href="<?php echo admin_url('edit.php?post_type=sffc_pe_deal'); ?>" class="button">💼 View PE Deals</a>
                <a href="<?php echo admin_url('edit.php?post_type=sffc_market_intel'); ?>" class="button">📊 View Market Intel</a>
            </p>
            <p style="margin-top: 10px; font-size: 13px; color: #666;">
                <strong>Note:</strong> PE Firms are now properly registered and visible in the admin menu. You can add, edit, and manage all tracked PE firms.
            </p>
        </div>
        <?php } else { ?>
        <div class="pe-warning" style="background: #fff3cd; padding: 15px; margin-bottom: 20px; border: 1px solid #ffc107; border-radius: 4px;">
            <h3 style="margin-top: 0;">⚠️ PE Post Types Not Found</h3>
            <p>The PE content post types are not registered. Click the button below to fix this:</p>
            <button class="button button-primary" id="flush-rewrite-rules">Fix PE Post Types</button>
            <div id="flush-result" style="margin-top: 10px;"></div>
        </div>
        <?php } ?>
        
        <!-- System Status -->
        <div class="pe-status-card" style="background: white; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; border-radius: 4px;">
            <h2>System Status</h2>
            <?php
            $pe_news_count = wp_count_posts('sffc_pe_news')->publish;
            $pe_deals_count = wp_count_posts('sffc_pe_deal')->publish;
            $market_intel_count = wp_count_posts('sffc_market_intel')->publish;
            $company_count = wp_count_posts('sffc_company')->publish;
            $last_run = get_option('sffc_pe_feeds_last_run', 'Never');
            
            // Get cleanup status
            if (class_exists('SFFC_PE_Content_Manager')) {
                $pe_manager = SFFC_PE_Content_Manager::get_instance();
                $cleanup_status = $pe_manager->get_cleanup_status();
            } else {
                $cleanup_status = array(
                    'last_run' => 'Never',
                    'last_deleted' => 0,
                    'next_run' => 'Not scheduled',
                    'retention_days' => 5,
                    'auto_cleanup_enabled' => false
                );
            }
            
            // Count fresh vs old content
            $fresh_date = date('Y-m-d H:i:s', strtotime('-48 hours'));
            global $wpdb;
            $fresh_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} 
                WHERE post_type IN ('sffc_pe_news', 'sffc_pe_deal', 'sffc_market_intel')
                AND post_status = 'publish'
                AND post_date > %s",
                $fresh_date
            ));

            ?>
            
            <table class="widefat">
                <tbody>
                    <tr>
                        <th>PE News Posts</th>
                        <td><?php echo $pe_news_count; ?> <span style="color: #0073aa;">(<?php echo $fresh_count; ?> fresh)</span></td>
                    </tr>
                    <tr>
                        <th>PE Deal Posts</th>
                        <td><?php echo $pe_deals_count; ?></td>
                    </tr>
                    <tr>
                        <th>Market Intelligence Posts</th>
                        <td><?php echo $market_intel_count; ?></td>
                    </tr>
                    <tr>
                        <th>PE Firms Tracked</th>
                        <td><?php echo $company_count; ?></td>
                    </tr>
                    <tr>
                        <th>Feeds Last Processed</th>
                        <td><?php echo $last_run; ?></td>
                    </tr>
                    <tr style="background-color: #f0f0f0;">
                        <th>Auto-Cleanup Schedule</th>
                        <td>
                            <?php if (!empty($cleanup_status['auto_cleanup_enabled'])) : ?>
                                Every Sunday at 3 AM (removes content older than <?php echo $cleanup_status['retention_days']; ?> days)
                            <?php else : ?>
                                Disabled (manual cleanup only)
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Last Cleanup</th>
                        <td><?php echo $cleanup_status['last_run']; ?> (<?php echo $cleanup_status['last_deleted']; ?> posts removed)</td>
                    </tr>
                    <tr>
                        <th>Next Cleanup</th>
                        <td><?php echo $cleanup_status['next_run']; ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Initialize PE Firms -->
        <div class="pe-initialize-card" style="background: white; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; border-radius: 4px;">
            <h2>Initialize PE Firms</h2>
            <p>Create or update the top PE firms in the system. This will set up company profiles for KKR, Blackstone, Apollo, Carlyle, TPG, Warburg Pincus, Bain Capital, and Advent International.</p>
            <button class="button button-primary" id="initialize-pe-firms">Initialize PE Firms</button>
            <div id="pe-firms-result" style="margin-top: 10px;"></div>
        </div>
        
        <!-- Process Feeds -->
        <div class="pe-feeds-card" style="background: white; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; border-radius: 4px;">
            <h2>Process RSS Feeds</h2>
            <p>Manually trigger feed processing to pull in the latest PE news, deals, and market intelligence from configured RSS feeds.</p>
            
            <h3>Configured Feeds:</h3>
            <ul>
                <li><strong>PE Hub Wire</strong> - PE deals and news</li>
                <li><strong>Bloomberg Markets</strong> - Market data and analysis</li>
                <li><strong>Reuters Business</strong> - Business and financial news</li>
                <li><strong>PR Newswire Finance</strong> - Company announcements</li>
                <li><strong>Yahoo Finance</strong> - Market updates</li>
            </ul>
            
            <button class="button button-primary" id="process-pe-feeds">Process Feeds Now</button>
            <div id="pe-feeds-result" style="margin-top: 10px;"></div>
        </div>
        
        <!-- Manual Fetch Content -->
        <div class="pe-manual-fetch-card" style="background: white; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; border-radius: 4px;">
            <h2>Manual Content Population</h2>
            <p>Manually fetch and populate content for specific post types or all at once. This will process RSS feeds and create new posts.</p>
            
            <div style="margin: 20px 0;">
                <h4>Fetch Individual Content Types:</h4>
                <div class="button-group" style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <button class="button button-secondary" id="fetch-pe-news">
                        <span class="dashicons dashicons-admin-post"></span> Fetch PE News
                    </button>
                    <button class="button button-secondary" id="fetch-pe-deals">
                        <span class="dashicons dashicons-chart-area"></span> Fetch PE Deals
                    </button>
                    <button class="button button-secondary" id="fetch-market-intel">
                        <span class="dashicons dashicons-chart-line"></span> Fetch Market Intelligence
                    </button>
                </div>
            </div>
            
            <div style="margin: 20px 0; padding: 15px; background: #e7f5ff; border-left: 4px solid #0073aa;">
                <h4 style="margin-top: 0;">Fetch All Content Types:</h4>
                <button class="button button-primary" id="fetch-all-content">
                    <span class="dashicons dashicons-download"></span> Fetch All Content Types
                </button>
                <span style="color: #666; margin-left: 10px;">Processes all RSS feeds and populates all three custom post types</span>
            </div>
            
            <div style="margin: 20px 0; padding: 15px; background: #d4edda; border-left: 4px solid #28a745;">
                <h4 style="margin-top: 0;">Generate Sample Data:</h4>
                <button class="button" id="generate-sample-data">
                    <span class="dashicons dashicons-welcome-add-page"></span> Generate Sample PE Content
                </button>
                <span style="color: #666; margin-left: 10px;">Creates sample posts for testing (5 of each type)</span>
            </div>

            <div style="margin: 20px 0; padding: 15px; background: #e8f4fd; border-left: 4px solid #0073aa;">
                <h4 style="margin-top: 0;">Test Financial Modeling Template:</h4>
                <button class="button button-primary" id="create-sample-deal-financial">
                    <span class="dashicons dashicons-chart-pie"></span> Create Sample Deal with Full Financials
                </button>
                <span style="color: #666; margin-left: 10px;">Creates a comprehensive deal with IC memo data, scenarios, risks, and comparables</span>
                <div id="sample-deal-result" style="margin-top: 10px;"></div>
            </div>
            
            <div id="fetch-progress" style="display: none; margin-top: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                <h4 style="margin-top: 0;">Processing...</h4>
                <div class="progress-bar" style="width: 100%; height: 20px; background: #e0e0e0; border-radius: 10px; overflow: hidden;">
                    <div class="progress-fill" style="height: 100%; background: #0073aa; width: 0%; transition: width 0.3s;"></div>
                </div>
                <p class="progress-message" style="margin-top: 10px;">Initializing...</p>
            </div>
            
            <div id="pe-fetch-result" style="margin-top: 10px;"></div>
        </div>

        <!-- Smart Cleanup -->
        <div class="pe-clear-card" style="background: white; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; border-radius: 4px;">
            <h2>Content Management</h2>
            <p>Automatic cleanup is disabled in this environment. Use the manual controls below to remove content older than 5 days as needed.</p>
            
            <div style="margin: 20px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid #0073aa;">
                <h4 style="margin-top: 0;">Manual Cleanup Options:</h4>
                <button class="button button-primary" id="cleanup-old-content" style="margin-right: 10px;">Clean Old Content (5+ days)</button>
                <span style="color: #666;">Removes content older than 5 days</span>
            </div>
            
            <div style="margin: 20px 0; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107;">
                <h4 style="margin-top: 0;">Danger Zone:</h4>
                <button class="button button-secondary" id="clear-pe-content" onclick="return confirm('Are you sure? This will delete ALL PE content posts.');" style="background: #dc3545; color: white; border-color: #dc3545;">Clear ALL Content</button>
                <span style="color: #666;">⚠️ Deletes all PE posts regardless of age</span>
            </div>
            
            <div id="pe-clear-result" style="margin-top: 10px;"></div>
        </div>
        
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Flush rewrite rules to fix post types
    $('#flush-rewrite-rules').on('click', function() {
        var $button = $(this);
        var $result = $('#flush-result');
        
        $button.prop('disabled', true).text('Fixing...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sffc_flush_pe_rewrite',
                nonce: '<?php echo wp_create_nonce('sffc_pe_admin'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<div class="notice notice-success"><p>Post types fixed! Reloading...</p></div>');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    $result.html('<div class="notice notice-error"><p>Error: ' + response.data + '</p></div>');
                }
            },
            error: function() {
                $result.html('<div class="notice notice-error"><p>Failed to fix post types.</p></div>');
            },
            complete: function() {
                $button.prop('disabled', false).text('Fix PE Post Types');
            }
        });
    });
    
    // Initialize PE Firms
    $('#initialize-pe-firms').on('click', function() {
        var $button = $(this);
        var $result = $('#pe-firms-result');
        
        $button.prop('disabled', true).text('Initializing...');
        $result.html('<div class="notice notice-info"><p>Initializing PE firms...</p></div>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sffc_initialize_pe_firms',
                nonce: '<?php echo wp_create_nonce('sffc_pe_admin'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                    // Update status
                    location.reload();
                } else {
                    $result.html('<div class="notice notice-error"><p>Error: ' + response.data + '</p></div>');
                }
            },
            error: function() {
                $result.html('<div class="notice notice-error"><p>Failed to initialize PE firms.</p></div>');
            },
            complete: function() {
                $button.prop('disabled', false).text('Initialize PE Firms');
            }
        });
    });
    
    // Process PE Feeds
    $('#process-pe-feeds').on('click', function() {
        var $button = $(this);
        var $result = $('#pe-feeds-result');
        
        $button.prop('disabled', true).text('Processing...');
        $result.html('<div class="notice notice-info"><p>Processing feeds... This may take a minute.</p></div>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sffc_process_pe_feeds_now',
                nonce: '<?php echo wp_create_nonce('sffc_intelligence_nonce'); ?>'
            },
            timeout: 60000, // 60 second timeout
            success: function(response) {
                if (response.success) {
                    var stats = response.data.stats;
                    $result.html('<div class="notice notice-success"><p>' + response.data.message + 
                        '<br>PE News: ' + stats.pe_news + 
                        ' | PE Deals: ' + stats.pe_deals + 
                        ' | Market Intel: ' + stats.market_intel + 
                        '</p></div>');
                    // Reload to update counts
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    $result.html('<div class="notice notice-error"><p>Error: ' + response.data + '</p></div>');
                }
            },
            error: function() {
                $result.html('<div class="notice notice-error"><p>Failed to process feeds. Please try again.</p></div>');
            },
            complete: function() {
                $button.prop('disabled', false).text('Process Feeds Now');
            }
        });
    });
    
    // Clean old content (5+ days)
    $('#cleanup-old-content').on('click', function() {
        var $button = $(this);
        var $result = $('#pe-clear-result');
        
        $button.prop('disabled', true).text('Cleaning...');
        $result.html('<div class="notice notice-info"><p>Cleaning up old content...</p></div>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sffc_cleanup_pe_content',
                nonce: '<?php echo wp_create_nonce('sffc_intelligence_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                    // Reload to update counts
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    $result.html('<div class="notice notice-error"><p>Error: ' + response.data + '</p></div>');
                }
            },
            error: function() {
                $result.html('<div class="notice notice-error"><p>Failed to clean old content.</p></div>');
            },
            complete: function() {
                $button.prop('disabled', false).text('Clean Old Content (5+ days)');
            }
        });
    });
    
    // Clear ALL PE Content
    $('#clear-pe-content').on('click', function() {
        var $button = $(this);
        var $result = $('#pe-clear-result');
        
        $button.prop('disabled', true).text('Clearing...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sffc_clear_pe_content',
                nonce: '<?php echo wp_create_nonce('sffc_pe_admin'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                    // Reload to update counts
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    $result.html('<div class="notice notice-error"><p>Error: ' + response.data + '</p></div>');
                }
            },
            error: function() {
                $result.html('<div class="notice notice-error"><p>Failed to clear content.</p></div>');
            },
            complete: function() {
                $button.prop('disabled', false).text('Clear ALL Content');
            }
        });
    });
    
    // Fetch PE News
    $('#fetch-pe-news').on('click', function() {
        fetchContent('news', $(this));
    });
    
    // Fetch PE Deals
    $('#fetch-pe-deals').on('click', function() {
        fetchContent('deals', $(this));
    });
    
    // Fetch Market Intel
    $('#fetch-market-intel').on('click', function() {
        fetchContent('market', $(this));
    });
    
    // Fetch All Content
    $('#fetch-all-content').on('click', function() {
        fetchContent('all', $(this));
    });
    
    // Create Sample Deal with Full Financials
    $('#create-sample-deal-financial').on('click', function() {
        var $button = $(this);
        var $result = $('#sample-deal-result');

        $button.prop('disabled', true).text('Creating...');
        $result.html('<div class="notice notice-info"><p>Creating sample deal with full financial data...</p></div>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sffc_create_sample_deal_financial',
                nonce: '<?php echo wp_create_nonce('sffc_pe_admin'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<div class="notice notice-success"><p>' + response.data.message + '</p>' +
                        '<p><a href="' + response.data.view_url + '" target="_blank" class="button button-primary">View Sample Deal</a> ' +
                        '<a href="' + response.data.edit_url + '" target="_blank" class="button">Edit in Admin</a></p></div>');
                } else {
                    $result.html('<div class="notice notice-error"><p>Error: ' + response.data + '</p></div>');
                }
            },
            error: function() {
                $result.html('<div class="notice notice-error"><p>Failed to create sample deal.</p></div>');
            },
            complete: function() {
                $button.prop('disabled', false).html('<span class="dashicons dashicons-chart-pie"></span> Create Sample Deal with Full Financials');
            }
        });
    });

    // Generate Sample Data
    $('#generate-sample-data').on('click', function() {
        var $button = $(this);
        var $result = $('#pe-fetch-result');
        var $progress = $('#fetch-progress');
        
        $button.prop('disabled', true).text('Generating...');
        $progress.show();
        $('.progress-message').text('Generating sample PE content...');
        $('.progress-fill').css('width', '50%');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sffc_generate_sample_pe_data',
                nonce: '<?php echo wp_create_nonce('sffc_pe_admin'); ?>'
            },
            success: function(response) {
                $('.progress-fill').css('width', '100%');
                if (response.success) {
                    $('.progress-message').text('Sample data generated successfully!');
                    $result.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    $result.html('<div class="notice notice-error"><p>Error: ' + response.data + '</p></div>');
                }
            },
            error: function() {
                $result.html('<div class="notice notice-error"><p>Failed to generate sample data.</p></div>');
            },
            complete: function() {
                $button.prop('disabled', false).text('Generate Sample PE Content');
                setTimeout(function() {
                    $progress.hide();
                }, 3000);
            }
        });
    });

    // Generic fetch function
    function fetchContent(type, $button) {
        var $result = $('#pe-fetch-result');
        var $progress = $('#fetch-progress');
        var originalText = $button.text();
        
        $button.prop('disabled', true).text('Fetching...');
        $progress.show();
        $('.progress-fill').css('width', '0%');
        $('.progress-message').text('Fetching ' + type + ' content from RSS feeds...');
        
        // Animate progress
        var progress = 0;
        var progressInterval = setInterval(function() {
            progress += 10;
            if (progress <= 90) {
                $('.progress-fill').css('width', progress + '%');
            }
        }, 500);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sffc_fetch_pe_content_type',
                type: type,
                nonce: '<?php echo wp_create_nonce('sffc_pe_admin'); ?>'
            },
            timeout: 60000, // 60 second timeout
            success: function(response) {
                clearInterval(progressInterval);
                $('.progress-fill').css('width', '100%');
                
                if (response.success) {
                    $('.progress-message').text('Content fetched successfully!');
                    var stats = response.data.stats;
                    var message = response.data.message + '<br>';
                    
                    if (stats) {
                        if (stats.pe_news) message += 'PE News: ' + stats.pe_news + ' posts<br>';
                        if (stats.pe_deals) message += 'PE Deals: ' + stats.pe_deals + ' posts<br>';
                        if (stats.market_intel) message += 'Market Intel: ' + stats.market_intel + ' posts';
                    }
                    
                    $result.html('<div class="notice notice-success"><p>' + message + '</p></div>');
                    
                    setTimeout(function() {
                        location.reload();
                    }, 3000);
                } else {
                    $result.html('<div class="notice notice-error"><p>Error: ' + response.data + '</p></div>');
                }
            },
            error: function() {
                clearInterval(progressInterval);
                $result.html('<div class="notice notice-error"><p>Failed to fetch content. Please try again.</p></div>');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
                setTimeout(function() {
                    $progress.hide();
                }, 3000);
            }
        });
    }
});
</script>
