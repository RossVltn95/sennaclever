<?php

/**
 * Database Manager Class
 * 
 * @package SennaCareers
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Database_Manager
{

    /**
     * Constructor
     */
    public function __construct()
    {
        // No initialization needed here
    }

    /**
     * Render database management page
     */
    public function render_page()
    {
?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="sffc-database-manager">
                <!-- Database Status -->
                <div class="card">
                    <h2><?php esc_html_e('Database Status', 'senna-finance'); ?></h2>
                    <div class="sffc-database-status">
                        <?php echo $this->get_database_status(); ?>
                    </div>
                </div>

                <!-- Table Creation -->
                <div class="card">
                    <h2><?php esc_html_e('Create Database Tables', 'senna-finance'); ?></h2>
                    <p><?php esc_html_e('Click the button below to create or update all required database tables.', 'senna-finance'); ?></p>

                    <div class="sffc-table-actions">
                        <button id="sffc-create-tables" class="button button-primary">
                            <?php esc_html_e('Create/Update Tables', 'senna-finance'); ?>
                        </button>
                        <button id="sffc-verify-tables" class="button">
                            <?php esc_html_e('Verify Tables', 'senna-finance'); ?>
                        </button>
                    </div>

                    <div id="sffc-table-results" class="sffc-results" style="display: none;">
                        <!-- Results will appear here -->
                    </div>
                </div>

                <!-- Live Chat Post Types -->
                <div class="card">
                    <h2><?php esc_html_e('Live Chat Post Types', 'senna-finance'); ?></h2>
                    <p><?php esc_html_e('Register the required post types for the live expert chat system.', 'senna-finance'); ?></p>

                    <div class="sffc-post-type-status">
                        <?php echo $this->get_post_type_status(); ?>
                    </div>

                    <div class="sffc-post-type-actions">
                        <button id="sffc-register-post-types" class="button button-primary">
                            <?php esc_html_e('Register Live Chat Post Types', 'senna-finance'); ?>
                        </button>
                        <button id="sffc-verify-post-types" class="button">
                            <?php esc_html_e('Verify Post Types', 'senna-finance'); ?>
                        </button>
                    </div>

                    <div id="sffc-post-type-results" class="sffc-results" style="display: none;">
                        <!-- Results will appear here -->
                    </div>
                </div>

                <!-- Table Information -->
                <div class="card">
                    <h2><?php esc_html_e('Table Information', 'senna-finance'); ?></h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Table Name', 'senna-finance'); ?></th>
                                <th><?php esc_html_e('Status', 'senna-finance'); ?></th>
                                <th><?php esc_html_e('Rows', 'senna-finance'); ?></th>
                                <th><?php esc_html_e('Size', 'senna-finance'); ?></th>
                                <th><?php esc_html_e('Actions', 'senna-finance'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php echo $this->get_table_info_rows(); ?>
                        </tbody>
                    </table>
                </div>

                <!-- PE Search Engine Management -->
                <div class="card">
                    <h2>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 8px; vertical-align: text-bottom;">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="M21 21l-4.35-4.35"></path>
                        </svg>
                        <?php esc_html_e('PE Search Engine Management', 'senna-finance'); ?>
                    </h2>
                    <p><?php esc_html_e('Manage the Private Equity search engine database tables and content indexing.', 'senna-finance'); ?></p>
                    
                    <!-- Search Engine Status -->
                    <div class="sffc-search-status">
                        <?php echo $this->get_search_engine_status(); ?>
                    </div>
                    
                    <!-- Search Engine Actions -->
                    <div class="sffc-search-actions" style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 4px;">
                        <h3><?php esc_html_e('Search Database Management', 'senna-finance'); ?></h3>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 15px;">
                            <button id="sffc-create-search-tables" class="button button-primary">
                                <?php esc_html_e('Create Search Tables', 'senna-finance'); ?>
                            </button>
                            <button id="sffc-index-all-content" class="button button-secondary">
                                <?php esc_html_e('Index All Content', 'senna-finance'); ?>
                            </button>
                            <button id="sffc-verify-search-index" class="button">
                                <?php esc_html_e('Verify Search Index', 'senna-finance'); ?>
                            </button>
                            <button id="sffc-test-search" class="button">
                                <?php esc_html_e('Test Search', 'senna-finance'); ?>
                            </button>
                            <button id="sffc-force-create-clicks-table" class="button button-primary" style="background: #dc3232;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px; vertical-align: text-bottom;">
                                    <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
                                </svg>
                                <?php esc_html_e('Fix Clicks Table', 'senna-finance'); ?>
                            </button>
                            <button id="sffc-fix-fulltext-indexes" class="button button-secondary" style="background: #f56565; color: white; font-weight: bold;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px; vertical-align: text-bottom;">
                                    <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 8l-3-3m-3.5 3.5L19 12l-3 3-2.5-2.5"></path>
                                </svg>
                                <?php esc_html_e('FIX FULLTEXT INDEXES', 'senna-finance'); ?>
                            </button>
                            <button id="sffc-auto-fix-search" class="button button-primary" style="background: #d63638; color: white; font-weight: bold;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px; vertical-align: text-bottom;">
                                    <path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"></path>
                                    <path d="M12 15l-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"></path>
                                    <path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"></path>
                                    <path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"></path>
                                </svg>
                                <?php esc_html_e('AUTO-FIX ALL ISSUES', 'senna-finance'); ?>
                            </button>
                        </div>
                        
                        <h4><?php esc_html_e('Index Content By Type', 'senna-finance'); ?></h4>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <button class="button sffc-index-content" data-post-type="sffc_pe_news">
                                <?php esc_html_e('Index News', 'senna-finance'); ?>
                            </button>
                            <button class="button sffc-index-content" data-post-type="sffc_recruiter">
                                <?php esc_html_e('Index Recruiters', 'senna-finance'); ?>
                            </button>
                            <button class="button sffc-index-content" data-post-type="sffc_company">
                                <?php esc_html_e('Index Companies', 'senna-finance'); ?>
                            </button>
                            <button class="button sffc-index-content" data-post-type="sffc_deal">
                                <?php esc_html_e('Index Deals', 'senna-finance'); ?>
                            </button>
                        </div>
                    </div>
                    
                    <div id="sffc-search-results" class="sffc-results" style="display: none;">
                        <!-- Search operation results will appear here -->
                    </div>
                </div>

                <!-- Recruiter Terminal Management -->
                <div class="card">
                    <h2>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 8px; vertical-align: text-bottom;">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                        <?php esc_html_e('Recruiter Terminal Management', 'senna-finance'); ?>
                    </h2>
                    <p><?php esc_html_e('Manage the Recruiter Terminal database tables for campaign management and candidate outreach.', 'senna-finance'); ?></p>

                    <!-- Recruiter Terminal Status -->
                    <div class="sffc-recruiter-status">
                        <?php echo $this->get_recruiter_terminal_status(); ?>
                    </div>

                    <!-- Recruiter Terminal Actions -->
                    <div class="sffc-recruiter-actions" style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 4px;">
                        <h3><?php esc_html_e('Recruiter Terminal Database Management', 'senna-finance'); ?></h3>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 15px;">
                            <button id="sffc-create-rt-tables" class="button button-primary">
                                <?php esc_html_e('Create RT Tables', 'senna-finance'); ?>
                            </button>
                            <button id="sffc-verify-rt-tables" class="button">
                                <?php esc_html_e('Verify RT Tables', 'senna-finance'); ?>
                            </button>
                            <button id="sffc-test-rt-shortcode" class="button button-secondary">
                                <?php esc_html_e('Test Shortcode', 'senna-finance'); ?>
                            </button>
                        </div>
                    </div>

                    <div id="sffc-rt-results" class="sffc-results" style="display: none;">
                        <!-- Recruiter Terminal operation results will appear here -->
                    </div>
                </div>

                <!-- Spam Search Query Cleanup -->
                <div class="card">
                    <h2>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 8px; vertical-align: text-bottom;">
                            <path d="M3 6h18"></path>
                            <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                            <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                            <line x1="10" y1="11" x2="10" y2="17"></line>
                            <line x1="14" y1="11" x2="14" y2="17"></line>
                        </svg>
                        <?php esc_html_e('Spam Search Query Cleanup', 'senna-finance'); ?>
                    </h2>
                    <p><?php esc_html_e('Remove spam search queries that contain repetitive words (e.g., "Top Top Top", "compensation compensation") which harm SEO.', 'senna-finance'); ?></p>

                    <!-- Spam Query Statistics -->
                    <div class="sffc-spam-stats" style="margin: 15px 0; padding: 15px; background: #fff3cd; border-radius: 4px; border-left: 4px solid #ffc107;">
                        <?php echo $this->get_spam_query_stats(); ?>
                    </div>

                    <!-- Cleanup Actions -->
                    <div class="sffc-spam-cleanup-actions" style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 4px;">
                        <h3><?php esc_html_e('One-Click Cleanup', 'senna-finance'); ?></h3>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 15px;">
                            <button id="sffc-preview-spam-queries" class="button button-secondary">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px; vertical-align: text-bottom;">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                <?php esc_html_e('Preview Spam Queries', 'senna-finance'); ?>
                            </button>
                            <button id="sffc-clear-all-spam-queries" class="button button-primary" style="background: #dc3545; border-color: #dc3545;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px; vertical-align: text-bottom;">
                                    <path d="M3 6h18"></path>
                                    <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                                    <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                                </svg>
                                <?php esc_html_e('Clear ALL Spam Queries', 'senna-finance'); ?>
                            </button>
                        </div>
                        <p class="description" style="color: #856404;">
                            <strong><?php esc_html_e('This will remove queries matching:', 'senna-finance'); ?></strong><br>
                            - Repeated words (Top Top Top, compensation compensation)<br>
                            - Very long queries with multiple repetitions<br>
                            - Known spam patterns
                        </p>
                    </div>

                    <div id="sffc-spam-cleanup-results" class="sffc-results" style="display: none;">
                        <!-- Spam cleanup results will appear here -->
                    </div>
                </div>

                <!-- Database Operations -->
                <div class="card">
                    <h2><?php esc_html_e('Database Operations', 'senna-finance'); ?></h2>
                    <p class="description">
                        <?php esc_html_e('Warning: These operations cannot be undone. Make sure to backup your database first.', 'senna-finance'); ?>
                    </p>

                    <div class="sffc-danger-zone">
                        <button id="sffc-clear-conversations" class="button button-secondary"
                            onclick="return confirm('<?php esc_attr_e('Are you sure you want to clear all conversations?', 'senna-finance'); ?>');">
                            <?php esc_html_e('Clear All Conversations', 'senna-finance'); ?>
                        </button>
                        <button id="sffc-reset-database" class="button button-secondary"
                            onclick="return confirm('<?php esc_attr_e('Are you sure you want to reset the database? This will delete all data!', 'senna-finance'); ?>');">
                            <?php esc_html_e('Reset Database', 'senna-finance'); ?>
                        </button>
                        <button id="sffc-drop-tables" class="button button-link-delete"
                            onclick="return confirm('<?php esc_attr_e('Are you sure you want to drop all tables? This action cannot be undone!', 'senna-finance'); ?>');">
                            <?php esc_html_e('Drop All Tables', 'senna-finance'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            jQuery(document).ready(function($) {
                // Create tables
                $('#sffc-create-tables').on('click', function() {
                    var $button = $(this);
                    var originalText = $button.text();

                    $button.prop('disabled', true).text('<?php esc_attr_e('Creating...', 'senna-finance'); ?>');
                    $('#sffc-table-results').hide();

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'sffc_create_tables',
                            nonce: '<?php echo wp_create_nonce('sffc_admin_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#sffc-table-results')
                                    .html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>')
                                    .show();

                                // Reload page after 2 seconds to update table info
                                setTimeout(function() {
                                    location.reload();
                                }, 2000);
                            } else {
                                $('#sffc-table-results')
                                    .html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>')
                                    .show();
                            }
                        },
                        error: function() {
                            $('#sffc-table-results')
                                .html('<div class="notice notice-error"><p><?php esc_html_e('An error occurred', 'senna-finance'); ?></p></div>')
                                .show();
                        },
                        complete: function() {
                            $button.prop('disabled', false).text(originalText);
                        }
                    });
                });

                // Verify tables
                $('#sffc-verify-tables').on('click', function() {
                    var $button = $(this);
                    var originalText = $button.text();

                    $button.prop('disabled', true).text('<?php esc_attr_e('Verifying...', 'senna-finance'); ?>');

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'sffc_verify_tables',
                            nonce: '<?php echo wp_create_nonce('sffc_admin_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                var results = response.data.results;
                                var html = '<h3>Verification Results:</h3><ul>';

                                for (var table in results) {
                                    var status = results[table] ? '✓' : '✗';
                                    var statusClass = results[table] ? 'success' : 'error';
                                    html += '<li class="' + statusClass + '">' + status + ' ' + table + '</li>';
                                }

                                html += '</ul>';
                                $('#sffc-table-results').html(html).show();
                            }
                        },
                        complete: function() {
                            $button.prop('disabled', false).text(originalText);
                        }
                    });
                });

                // Fix FULLTEXT indexes
                $('#sffc-fix-fulltext-indexes').on('click', function() {
                    var $button = $(this);
                    var originalText = $button.text();
                    
                    if (!confirm('<?php esc_attr_e('This will recreate FULLTEXT indexes on the search table. This operation may take a few minutes. Continue?', 'senna-finance'); ?>')) {
                        return;
                    }
                    
                    $button.prop('disabled', true).text('<?php esc_attr_e('Fixing Indexes...', 'senna-finance'); ?>');
                    $('#sffc-search-results').hide();
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'sffc_fix_fulltext_indexes',
                            nonce: '<?php echo wp_create_nonce('sffc_admin_nonce'); ?>'
                        },
                        success: function(response) {
                            var html = '<h3><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline; margin-right: 8px; vertical-align: text-bottom;"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 8l-3-3m-3.5 3.5L19 12l-3 3-2.5-2.5"></path></svg>FULLTEXT Index Fix Results:</h3>';
                            if (response.success) {
                                var data = response.data;
                                html += '<div class="notice notice-success"><p><strong>' + data.message + '</strong></p></div>';
                                html += '<div class="sffc-fix-details">' + data.details + '</div>';
                            } else {
                                html += '<div class="notice notice-error"><p><strong>Fix Failed:</strong> ' + (response.data || 'Unknown error') + '</p></div>';
                            }
                            $('#sffc-search-results').html(html).show();
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            $('#sffc-search-results')
                                .html('<div class="notice notice-error"><p><strong>Error:</strong> Failed to connect to server. Please try again.</p></div>')
                                .show();
                        },
                        complete: function() {
                            $button.prop('disabled', false).text(originalText);
                        }
                    });
                });

                // Clear conversations
                $('#sffc-clear-conversations').on('click', function() {
                    if (!confirm('<?php esc_html_e('This will delete all conversations. Continue?', 'senna-finance'); ?>')) {
                        return false;
                    }

                    var $button = $(this);
                    $button.prop('disabled', true).text('<?php esc_attr_e('Clearing...', 'senna-finance'); ?>');

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'sffc_clear_conversations',
                            nonce: '<?php echo wp_create_nonce('sffc_admin_nonce'); ?>'
                        },
                        success: function(response) {
                            alert(response.success ?
                                '<?php esc_html_e('Conversations cleared successfully', 'senna-finance'); ?>' :
                                '<?php esc_html_e('Error clearing conversations', 'senna-finance'); ?>'
                            );
                            if (response.success) {
                                location.reload();
                            }
                        },
                        complete: function() {
                            $button.prop('disabled', false).text('<?php esc_html_e('Clear All Conversations', 'senna-finance'); ?>');
                        }
                    });
                });

                // Register live chat post types
                $('#sffc-register-post-types').on('click', function() {
                    var $button = $(this);
                    var originalText = $button.text();

                    $button.prop('disabled', true).text('<?php esc_attr_e('Registering...', 'senna-finance'); ?>');
                    $('#sffc-post-type-results').hide();

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'sffc_register_live_chat_post_types',
                            nonce: '<?php echo wp_create_nonce('sffc_admin_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#sffc-post-type-results')
                                    .html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>')
                                    .show();

                                // Update status display
                                setTimeout(function() {
                                    location.reload();
                                }, 2000);
                            } else {
                                $('#sffc-post-type-results')
                                    .html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>')
                                    .show();
                            }
                        },
                        error: function() {
                            $('#sffc-post-type-results')
                                .html('<div class="notice notice-error"><p><?php esc_html_e('An error occurred while registering post types', 'senna-finance'); ?></p></div>')
                                .show();
                        },
                        complete: function() {
                            $button.prop('disabled', false).text(originalText);
                        }
                    });
                });

                // Verify live chat post types
                $('#sffc-verify-post-types').on('click', function() {
                    var $button = $(this);
                    var originalText = $button.text();

                    $button.prop('disabled', true).text('<?php esc_attr_e('Verifying...', 'senna-finance'); ?>');

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'sffc_verify_live_chat_post_types',
                            nonce: '<?php echo wp_create_nonce('sffc_admin_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                var results = response.data.results;
                                var html = '<div class="notice notice-success"><h4>Post Type Status:</h4><ul>';

                                for (var postType in results) {
                                    var icon = results[postType] ? 
                                        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><polyline points="20,6 9,17 4,12"></polyline></svg>' : 
                                        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
                                    var status = results[postType] ? 'Registered' : 'Missing';
                                    status = icon + ' ' + status;
                                    html += '<li><strong>' + postType + ':</strong> ' + status + '</li>';
                                }
                                html += '</ul></div>';

                                $('#sffc-post-type-results').html(html).show();
                            } else {
                                $('#sffc-post-type-results')
                                    .html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>')
                                    .show();
                            }
                        },
                        error: function() {
                            $('#sffc-post-type-results')
                                .html('<div class="notice notice-error"><p><?php esc_html_e('An error occurred while verifying post types', 'senna-finance'); ?></p></div>')
                                .show();
                        },
                        complete: function() {
                            $button.prop('disabled', false).text(originalText);
                        }
                    });
                });
                
                // Search Engine Management Handlers
                
                // Create search tables
                $('#sffc-create-search-tables').on('click', function() {
                    var $button = $(this);
                    var originalText = $button.text();
                    
                    $button.prop('disabled', true).text('<?php esc_attr_e('Creating...', 'senna-finance'); ?>');
                    $('#sffc-search-results').hide();
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'sffc_create_search_tables',
                            nonce: '<?php echo wp_create_nonce('sffc_admin_nonce'); ?>'
                        },
                        success: function(response) {
                            var html = '<h3>Search Tables Creation:</h3>';
                            if (response.success) {
                                html += '<div class="notice notice-success"><p><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" style="display: inline; margin-right: 6px; vertical-align: text-bottom;"><polyline points="20,6 9,17 4,12"></polyline></svg>' + response.data.message + '</p></div>';
                                html += '<div>' + response.data.details + '</div>';
                                // Refresh the page to update status
                                setTimeout(function() { location.reload(); }, 2000);
                            } else {
                                html += '<div class="notice notice-error"><p><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" style="display: inline; margin-right: 6px; vertical-align: text-bottom;"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>' + response.data + '</p></div>';
                            }
                            $('#sffc-search-results').html(html).show();
                        },
                        complete: function() {
                            $button.prop('disabled', false).text(originalText);
                        }
                    });
                });
                
                // Index all content
                $('#sffc-index-all-content').on('click', function() {
                    var $button = $(this);
                    var originalText = $button.text();
                    
                    $button.prop('disabled', true).text('<?php esc_attr_e('Indexing...', 'senna-finance'); ?>');
                    $('#sffc-search-results').hide();
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'sffc_index_all_content',
                            nonce: '<?php echo wp_create_nonce('sffc_admin_nonce'); ?>'
                        },
                        success: function(response) {
                            var html = '<h3>Content Indexing Results:</h3>';
                            if (response.success) {
                                html += '<div class="notice notice-success"><p><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" style="display: inline; margin-right: 6px; vertical-align: text-bottom;"><polyline points="20,6 9,17 4,12"></polyline></svg>' + response.data.message + '</p></div>';
                                html += '<div>' + response.data.details + '</div>';
                                // Refresh the page to update status
                                setTimeout(function() { location.reload(); }, 2000);
                            } else {
                                html += '<div class="notice notice-error"><p><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" style="display: inline; margin-right: 6px; vertical-align: text-bottom;"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>' + response.data + '</p></div>';
                            }
                            $('#sffc-search-results').html(html).show();
                        },
                        complete: function() {
                            $button.prop('disabled', false).text(originalText);
                        }
                    });
                });
                
                // Verify search index
                $('#sffc-verify-search-index').on('click', function() {
                    var $button = $(this);
                    var originalText = $button.text();
                    
                    $button.prop('disabled', true).text('<?php esc_attr_e('Verifying...', 'senna-finance'); ?>');
                    $('#sffc-search-results').hide();
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'sffc_verify_search_index',
                            nonce: '<?php echo wp_create_nonce('sffc_admin_nonce'); ?>'
                        },
                        success: function(response) {
                            var html = '<h3>Search Index Verification:</h3>';
                            if (response.success) {
                                html += '<div class="notice notice-success"><p><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" style="display: inline; margin-right: 6px; vertical-align: text-bottom;"><polyline points="20,6 9,17 4,12"></polyline></svg>' + response.data.message + '</p></div>';
                                html += '<div>' + response.data.details + '</div>';
                            } else {
                                html += '<div class="notice notice-error"><p><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" style="display: inline; margin-right: 6px; vertical-align: text-bottom;"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>' + response.data + '</p></div>';
                            }
                            $('#sffc-search-results').html(html).show();
                        },
                        complete: function() {
                            $button.prop('disabled', false).text(originalText);
                        }
                    });
                });
                
                // Test search
                $('#sffc-test-search').on('click', function() {
                    var $button = $(this);
                    var originalText = $button.text();
                    
                    $button.prop('disabled', true).text('<?php esc_attr_e('Testing...', 'senna-finance'); ?>');
                    $('#sffc-search-results').hide();
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'sffc_test_search',
                            query: 'analyst',
                            nonce: '<?php echo wp_create_nonce('sffc_admin_nonce'); ?>'
                        },
                        success: function(response) {
                            var html = '<h3>Search Test Results:</h3>';
                            if (response.success) {
                                html += '<div class="notice notice-success"><p><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" style="display: inline; margin-right: 6px; vertical-align: text-bottom;"><polyline points="20,6 9,17 4,12"></polyline></svg>' + response.data.message + '</p></div>';
                                html += '<div>' + response.data.details + '</div>';
                            } else {
                                html += '<div class="notice notice-error"><p><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" style="display: inline; margin-right: 6px; vertical-align: text-bottom;"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>' + response.data + '</p></div>';
                            }
                            $('#sffc-search-results').html(html).show();
                        },
                        complete: function() {
                            $button.prop('disabled', false).text(originalText);
                        }
                    });
                });
                
                // Index content by type
                $('.sffc-index-content').on('click', function() {
                    var $button = $(this);
                    var originalText = $button.text();
                    var postType = $button.data('post-type');
                    
                    $button.prop('disabled', true).text('<?php esc_attr_e('Indexing...', 'senna-finance'); ?>');
                    $('#sffc-search-results').hide();
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'sffc_index_content_by_type',
                            post_type: postType,
                            nonce: '<?php echo wp_create_nonce('sffc_admin_nonce'); ?>'
                        },
                        success: function(response) {
                            var html = '<h3>Content Indexing Results (' + postType + '):</h3>';
                            if (response.success) {
                                html += '<div class="notice notice-success"><p><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" style="display: inline; margin-right: 6px; vertical-align: text-bottom;"><polyline points="20,6 9,17 4,12"></polyline></svg>' + response.data.message + '</p></div>';
                                html += '<div>' + response.data.details + '</div>';
                            } else {
                                html += '<div class="notice notice-error"><p><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" style="display: inline; margin-right: 6px; vertical-align: text-bottom;"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>' + response.data + '</p></div>';
                            }
                            $('#sffc-search-results').html(html).show();
                        },
                        complete: function() {
                            $button.prop('disabled', false).text(originalText);
                        }
                    });
                });
                
                // Force create clicks table (emergency fix)
                $('#sffc-force-create-clicks-table').on('click', function() {
                    var $button = $(this);
                    var originalText = $button.text();
                    
                    $button.prop('disabled', true).text('<?php esc_attr_e('Creating...', 'senna-finance'); ?>');
                    $('#sffc-search-results').hide();
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'sffc_force_create_clicks_table',
                            nonce: '<?php echo wp_create_nonce('sffc_admin_nonce'); ?>'
                        },
                        success: function(response) {
                            var html = '<h3>Force Create Clicks Table:</h3>';
                            if (response.success) {
                                html += '<div class="notice notice-success"><p><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" style="display: inline; margin-right: 6px; vertical-align: text-bottom;"><polyline points="20,6 9,17 4,12"></polyline></svg>' + response.data.message + '</p></div>';
                                html += '<div>' + response.data.details + '</div>';
                                // Refresh the page to update status
                                setTimeout(function() { location.reload(); }, 2000);
                            } else {
                                html += '<div class="notice notice-error"><p><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" style="display: inline; margin-right: 6px; vertical-align: text-bottom;"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>' + response.data + '</p></div>';
                            }
                            $('#sffc-search-results').html(html).show();
                        },
                        complete: function() {
                            $button.prop('disabled', false).text(originalText);
                        }
                    });
                });
                
                // Auto-fix all search issues
                $('#sffc-auto-fix-search').on('click', function() {
                    var $button = $(this);
                    var originalText = $button.text();
                    
                    $button.prop('disabled', true).text('<?php esc_attr_e('Analyzing...', 'senna-finance'); ?>');
                    $('#sffc-search-results').hide();
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'sffc_auto_fix_search',
                            nonce: '<?php echo wp_create_nonce('sffc_admin_nonce'); ?>'
                        },
                        success: function(response) {
                            var html = '<h3><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline; margin-right: 8px; vertical-align: text-bottom;"><path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"></path><path d="M12 15l-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"></path></svg>Auto-Fix Analysis Results:</h3>';
                            if (response.success) {
                                var data = response.data;
                                html += '<div class="notice notice-info"><p><strong>' + data.message + '</strong></p></div>';
                                html += data.details;
                                
                                // Show action buttons based on results
                                if (data.critical_count > 0 || data.error_count > 0) {
                                    html += '<div style="margin-top: 20px; padding: 15px; background: #fff2e6; border-left: 4px solid #dc3232;">';
                                    html += '<h4><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline; margin-right: 6px; vertical-align: text-bottom;"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>RECOMMENDED ACTIONS:</h4>';
                                    html += '<p><strong>1.</strong> Click "Index All Content" to fix indexing issues</p>';
                                    html += '<p><strong>2.</strong> Click "Test Search" to verify fixes</p>';
                                    html += '<p><strong>3.</strong> Check search results page for actual results</p>';
                                    html += '</div>';
                                }
                            } else {
                                html += '<div class="notice notice-error"><p><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" style="display: inline; margin-right: 6px; vertical-align: text-bottom;"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>' + response.data + '</p></div>';
                            }
                            $('#sffc-search-results').html(html).show();
                        },
                        complete: function() {
                            $button.prop('disabled', false).text(originalText);
                        }
                    });
                });

                // Recruiter Terminal - Create RT Tables
                $('#sffc-create-rt-tables').on('click', function() {
                    var $button = $(this);
                    var originalText = $button.text();

                    $button.prop('disabled', true).text('<?php esc_attr_e('Creating...', 'senna-finance'); ?>');
                    $('#sffc-rt-results').hide();

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'sffc_create_rt_tables',
                            nonce: '<?php echo wp_create_nonce('sffc_admin_nonce'); ?>'
                        },
                        success: function(response) {
                            var html = '<h3>Recruiter Terminal Tables Creation:</h3>';
                            if (response.success) {
                                html += '<div class="notice notice-success"><p><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" style="display: inline; margin-right: 6px; vertical-align: text-bottom;"><polyline points="20,6 9,17 4,12"></polyline></svg>' + response.data.message + '</p></div>';
                                html += '<div>' + response.data.details + '</div>';
                                setTimeout(function() { location.reload(); }, 2000);
                            } else {
                                html += '<div class="notice notice-error"><p><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" style="display: inline; margin-right: 6px; vertical-align: text-bottom;"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>' + response.data + '</p></div>';
                            }
                            $('#sffc-rt-results').html(html).show();
                        },
                        complete: function() {
                            $button.prop('disabled', false).text(originalText);
                        }
                    });
                });

                // Recruiter Terminal - Verify RT Tables
                $('#sffc-verify-rt-tables').on('click', function() {
                    var $button = $(this);
                    var originalText = $button.text();

                    $button.prop('disabled', true).text('<?php esc_attr_e('Verifying...', 'senna-finance'); ?>');
                    $('#sffc-rt-results').hide();

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'sffc_verify_rt_tables',
                            nonce: '<?php echo wp_create_nonce('sffc_admin_nonce'); ?>'
                        },
                        success: function(response) {
                            var html = '<h3>Recruiter Terminal Tables Verification:</h3>';
                            if (response.success) {
                                html += '<div class="notice notice-success"><p>' + response.data.message + '</p></div>';
                                html += '<div>' + response.data.details + '</div>';
                            } else {
                                html += '<div class="notice notice-error"><p>' + response.data + '</p></div>';
                            }
                            $('#sffc-rt-results').html(html).show();
                        },
                        complete: function() {
                            $button.prop('disabled', false).text(originalText);
                        }
                    });
                });

                // Recruiter Terminal - Test Shortcode
                $('#sffc-test-rt-shortcode').on('click', function() {
                    var $button = $(this);
                    var originalText = $button.text();

                    $button.prop('disabled', true).text('<?php esc_attr_e('Testing...', 'senna-finance'); ?>');
                    $('#sffc-rt-results').hide();

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'sffc_test_rt_shortcode',
                            nonce: '<?php echo wp_create_nonce('sffc_admin_nonce'); ?>'
                        },
                        success: function(response) {
                            var html = '<h3>Recruiter Terminal Shortcode Test:</h3>';
                            if (response.success) {
                                html += '<div class="notice notice-success"><p>' + response.data.message + '</p></div>';
                                html += '<div>' + response.data.details + '</div>';
                            } else {
                                html += '<div class="notice notice-error"><p>' + response.data + '</p></div>';
                            }
                            $('#sffc-rt-results').html(html).show();
                        },
                        complete: function() {
                            $button.prop('disabled', false).text(originalText);
                        }
                    });
                });

                // Preview Spam Queries
                $('#sffc-preview-spam-queries').on('click', function() {
                    var $button = $(this);
                    var originalText = $button.html();

                    $button.prop('disabled', true).html('<?php esc_attr_e('Loading...', 'senna-finance'); ?>');
                    $('#sffc-spam-cleanup-results').hide();

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'sffc_preview_spam_queries',
                            nonce: '<?php echo wp_create_nonce('sffc_admin_nonce'); ?>'
                        },
                        success: function(response) {
                            var html = '<h3>Spam Queries Preview:</h3>';
                            if (response.success) {
                                html += '<div class="notice notice-warning"><p><strong>Found ' + response.data.count + ' spam queries to remove</strong></p></div>';
                                if (response.data.samples && response.data.samples.length > 0) {
                                    html += '<div style="max-height: 300px; overflow-y: auto; background: #f8f9fa; padding: 10px; border-radius: 4px;">';
                                    html += '<strong>Sample queries:</strong><ul style="margin: 10px 0;">';
                                    response.data.samples.forEach(function(sample) {
                                        html += '<li style="word-break: break-all; margin-bottom: 5px; padding: 5px; background: #fff; border-radius: 3px;"><code>' + sample + '</code></li>';
                                    });
                                    html += '</ul></div>';
                                }
                            } else {
                                html += '<div class="notice notice-error"><p>' + (response.data || 'Error loading preview') + '</p></div>';
                            }
                            $('#sffc-spam-cleanup-results').html(html).show();
                        },
                        error: function() {
                            $('#sffc-spam-cleanup-results')
                                .html('<div class="notice notice-error"><p>Failed to connect to server</p></div>')
                                .show();
                        },
                        complete: function() {
                            $button.prop('disabled', false).html(originalText);
                        }
                    });
                });

                // Clear ALL Spam Queries
                $('#sffc-clear-all-spam-queries').on('click', function() {
                    if (!confirm('<?php esc_attr_e('Are you sure you want to delete ALL spam search queries? This cannot be undone!', 'senna-finance'); ?>')) {
                        return;
                    }

                    var $button = $(this);
                    var originalText = $button.html();

                    $button.prop('disabled', true).html('<?php esc_attr_e('Deleting...', 'senna-finance'); ?>');
                    $('#sffc-spam-cleanup-results').hide();

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'sffc_clear_all_spam_queries',
                            nonce: '<?php echo wp_create_nonce('sffc_admin_nonce'); ?>'
                        },
                        success: function(response) {
                            var html = '<h3>Spam Cleanup Results:</h3>';
                            if (response.success) {
                                html += '<div class="notice notice-success"><p><strong>' + response.data.message + '</strong></p></div>';
                                html += '<div style="background: #d4edda; padding: 15px; border-radius: 4px; margin-top: 10px;">';
                                html += '<p><strong>Deleted:</strong> ' + response.data.deleted + ' spam queries</p>';
                                html += '<p><strong>Remaining:</strong> ' + response.data.remaining + ' queries</p>';
                                html += '</div>';
                                // Refresh page after 3 seconds
                                setTimeout(function() { location.reload(); }, 3000);
                            } else {
                                html += '<div class="notice notice-error"><p>' + (response.data || 'Error during cleanup') + '</p></div>';
                            }
                            $('#sffc-spam-cleanup-results').html(html).show();
                        },
                        error: function() {
                            $('#sffc-spam-cleanup-results')
                                .html('<div class="notice notice-error"><p>Failed to connect to server</p></div>')
                                .show();
                        },
                        complete: function() {
                            $button.prop('disabled', false).html(originalText);
                        }
                    });
                });
            });
        </script>
    <?php
    }

    /**
     * Get map of all tables managed by the platform
     */
    private function get_all_tables_map()
    {
        global $wpdb;

        $tables = array(
            'conversations' => $wpdb->prefix . 'sffc_conversations',
            'messages' => $wpdb->prefix . 'sffc_messages',
            'user_profiles' => $wpdb->prefix . 'sffc_user_profiles',
            'expert_availability' => $wpdb->prefix . 'sffc_expert_availability',
            'opportunities' => $wpdb->prefix . 'sffc_opportunities',
            'file_uploads' => $wpdb->prefix . 'sffc_file_uploads',
            'message_templates' => $wpdb->prefix . 'sffc_message_templates',
            'cv_master' => $wpdb->prefix . 'sffc_cv_master',
            'cv_versions' => $wpdb->prefix . 'sffc_cv_versions',
            'cv_analytics' => $wpdb->prefix . 'sffc_cv_analytics',
            'cv_uploads' => $wpdb->prefix . 'sffc_cv_uploads',
            'parsed_profiles' => $wpdb->prefix . 'sffc_parsed_profiles',
            // PE Search Engine Tables
            'search_index' => $wpdb->prefix . 'sffc_search_index',
            'search_entities' => $wpdb->prefix . 'sffc_search_entities',
            'search_queries' => $wpdb->prefix . 'sffc_search_queries',
            'search_clicks' => $wpdb->prefix . 'sffc_search_clicks',
            // Professional Networking Tables
            'networking_requests' => $wpdb->prefix . 'sffc_networking_requests',
            'professional_connections' => $wpdb->prefix . 'sffc_professional_connections',
            // Pattern Recognition Engine Tables
            'market_cache' => $wpdb->prefix . 'sffc_market_cache',
            'news_cache' => $wpdb->prefix . 'sffc_news_cache',
            'finance_intelligence' => $wpdb->prefix . 'sffc_finance_intelligence',
            'analysis_cache' => $wpdb->prefix . 'sffc_analysis_cache',
            'pattern_history' => $wpdb->prefix . 'sffc_pattern_history',
            'feed_status' => $wpdb->prefix . 'sffc_feed_status',
            'user_cvs' => $wpdb->prefix . 'sffc_user_cvs',
            // Career Dashboard Tables
            'user_activity' => $wpdb->prefix . 'sffc_user_activity',
            'dashboard_snapshots' => $wpdb->prefix . 'sffc_dashboard_snapshots',
            'interactions' => $wpdb->prefix . 'sffc_interactions',
            // Recruiter Terminal Tables (New v2.0)
            'rt_briefs' => $wpdb->prefix . 'rt_briefs',
            'rt_responses' => $wpdb->prefix . 'rt_responses',
            'rt_notes' => $wpdb->prefix . 'rt_notes',
            'rt_analytics' => $wpdb->prefix . 'rt_analytics',
            // Recruiter Terminal Tables (Legacy)
            'rt_campaigns' => $wpdb->prefix . 'rt_campaigns',
            'rt_campaign_targets' => $wpdb->prefix . 'rt_campaign_targets',
            'rt_activity_log' => $wpdb->prefix . 'rt_activity_log',
            'rt_email_templates' => $wpdb->prefix . 'rt_email_templates',
            // CRM Tables - Recruiter CRM System
            'crm_recruiters' => $wpdb->prefix . 'sffc_crm_recruiters',
            'crm_posts' => $wpdb->prefix . 'sffc_crm_posts',
            'crm_saved_recruiters' => $wpdb->prefix . 'sffc_crm_saved_recruiters',
            'crm_saved_posts' => $wpdb->prefix . 'sffc_crm_saved_posts',
            'crm_pipeline' => $wpdb->prefix . 'sffc_crm_pipeline',
            'crm_pipeline_history' => $wpdb->prefix . 'sffc_crm_pipeline_history',
            'crm_outreach' => $wpdb->prefix . 'sffc_crm_outreach',
            'crm_templates' => $wpdb->prefix . 'sffc_crm_templates',
            'crm_activity' => $wpdb->prefix . 'sffc_crm_activity',
            'crm_notes' => $wpdb->prefix . 'sffc_crm_notes',
            'crm_usage' => $wpdb->prefix . 'sffc_crm_usage',
            'crm_tags' => $wpdb->prefix . 'sffc_crm_tags',
            'crm_recruiter_tags' => $wpdb->prefix . 'sffc_crm_recruiter_tags',
            'crm_tasks' => $wpdb->prefix . 'sffc_crm_tasks',
            'crm_sequences' => $wpdb->prefix . 'sffc_crm_sequences',
            'crm_sequence_steps' => $wpdb->prefix . 'sffc_crm_sequence_steps',
            'crm_sequence_enrollments' => $wpdb->prefix . 'sffc_crm_sequence_enrollments',
            'crm_enrollment_history' => $wpdb->prefix . 'sffc_crm_enrollment_history',
            'crm_conversations' => $wpdb->prefix . 'sffc_crm_conversations',
            'crm_messages' => $wpdb->prefix . 'sffc_crm_messages',
            'crm_email_accounts' => $wpdb->prefix . 'sffc_crm_email_accounts',
            'crm_alerts' => $wpdb->prefix . 'sffc_crm_alerts',
            'crm_notifications' => $wpdb->prefix . 'sffc_crm_notifications'
        );

        if (class_exists('SFFC_Company_Database_Setup')) {
            foreach (SFFC_Company_Database_Setup::get_tables_list() as $table) {
                $key = str_replace($wpdb->prefix, '', $table);
                $tables[$key] = $table;
            }
        }

        return $tables;
    }

    /**
     * Get database status HTML
     */
    private function get_database_status()
    {
        global $wpdb;

        $tables = $this->get_all_tables_map();

        $all_exist = true;
        $status_html = '<div class="sffc-status-grid">';

        foreach ($tables as $name => $table) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table;
            $all_exist = $all_exist && $exists;

            $status_icon = $exists ? 
                '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" style="display: inline; margin-right: 6px; vertical-align: text-bottom;"><polyline points="20,6 9,17 4,12"></polyline></svg>' : 
                '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" style="display: inline; margin-right: 6px; vertical-align: text-bottom;"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
            $status_class = $exists ? 'sffc-status-ok' : 'sffc-status-error';
            $status_text = $exists ? __('Exists', 'senna-finance') : __('Missing', 'senna-finance');

            $status_html .= sprintf(
                '<div class="sffc-status-item %s">
                    <span class="sffc-status-icon">%s</span>
                    <span class="sffc-status-name">%s</span>
                    <span class="sffc-status-text">%s</span>
                </div>',
                esc_attr($status_class),
                $status_icon,
                esc_html(ucwords(str_replace('_', ' ', $name))),
                esc_html($status_text)
            );
        }

        $status_html .= '</div>';

        if (!$all_exist) {
            $status_html = '<div class="notice notice-warning inline">
                <p>' . esc_html__('Some database tables are missing. Click "Create/Update Tables" to create them.', 'senna-finance') . '</p>
            </div>' . $status_html;
        } else {
            $status_html = '<div class="notice notice-success inline">
                <p>' . esc_html__('All database tables are properly configured.', 'senna-finance') . '</p>
            </div>' . $status_html;
        }

        return $status_html;
    }

    /**
     * Get post type status HTML
     */
    private function get_post_type_status()
    {
        $post_types = [
            'sffc_live_chat' => 'Live Chat Conversations',
            'sffc_chat_message' => 'Live Chat Messages'
        ];

        $status_html = '<div class="sffc-status-grid">';

        foreach ($post_types as $post_type => $label) {
            $exists = post_type_exists($post_type);

            $status_icon = $exists ? 
                '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" style="display: inline; margin-right: 6px; vertical-align: text-bottom;"><polyline points="20,6 9,17 4,12"></polyline></svg>' : 
                '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" style="display: inline; margin-right: 6px; vertical-align: text-bottom;"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
            $status_class = $exists ? 'sffc-status-ok' : 'sffc-status-error';
            $status_text = $exists ? __('Registered', 'senna-finance') : __('Missing', 'senna-finance');

            $status_html .= sprintf(
                '<div class="sffc-status-item %s">
                    <span class="sffc-status-icon">%s</span>
                    <span class="sffc-status-name">%s</span>
                    <span class="sffc-status-text">%s</span>
                </div>',
                esc_attr($status_class),
                $status_icon,
                esc_html($label),
                esc_html($status_text)
            );
        }

        $status_html .= '</div>';

        return $status_html;
    }

    /**
     * Get table info rows
     */
    private function get_table_info_rows()
    {
        global $wpdb;

        $tables = $this->get_all_tables_map();

        $output = '';

        foreach ($tables as $name => $table) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table;

            if ($exists) {
                $row_count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
                $table_status = $wpdb->get_row(
                    "SELECT 
                        ROUND(((data_length + index_length) / 1024), 2) AS size_kb
                    FROM information_schema.TABLES 
                    WHERE table_schema = '{$wpdb->dbname}' 
                    AND table_name = '$table'"
                );

                $size = $table_status ? $table_status->size_kb . ' KB' : 'N/A';
                $status = '<span style="color: #00a32a;">✓ ' . __('Active', 'senna-finance') . '</span>';

                $actions = sprintf(
                    '<button class="button button-small" onclick="sffc_view_table(\'%s\')">%s</button> ' .
                        '<button class="button button-small" onclick="sffc_export_table(\'%s\')">%s</button>',
                    esc_attr($name),
                    esc_html__('View', 'senna-finance'),
                    esc_attr($name),
                    esc_html__('Export', 'senna-finance')
                );
            } else {
                $row_count = '-';
                $size = '-';
                $status = '<span style="color: #d63638;">✗ ' . __('Missing', 'senna-finance') . '</span>';
                $actions = '<span class="description">' . __('Table not created', 'senna-finance') . '</span>';
            }

            $output .= sprintf(
                '<tr>
                    <td><strong>%s</strong><br><code>%s</code></td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                </tr>',
                esc_html(ucwords(str_replace('_', ' ', $name))),
                esc_html($table),
                $status,
                esc_html($row_count),
                esc_html($size),
                $actions
            );
        }

        return $output;
    }
    
    /**
     * Get search engine status HTML
     */
    private function get_search_engine_status()
    {
        global $wpdb;
        
        // Check if search classes are available
        $indexer_available = class_exists('SFFC_Search_Indexer');
        $query_available = class_exists('SFFC_Search_Query');
        
        // Check search tables
        $search_tables = array(
            'search_index' => $wpdb->prefix . 'sffc_search_index',
            'search_entities' => $wpdb->prefix . 'sffc_search_entities',
            'search_queries' => $wpdb->prefix . 'sffc_search_queries',
            'search_clicks' => $wpdb->prefix . 'sffc_search_clicks'
        );
        
        $tables_exist = 0;
        $total_indexed = 0;
        
        foreach ($search_tables as $name => $table) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table;
            if ($exists) {
                $tables_exist++;
                if ($name === 'search_index') {
                    $total_indexed = $wpdb->get_var("SELECT COUNT(*) FROM $table");
                }
            }
        }
        
        // Check content available for indexing
        $post_types = array('sffc_pe_news', 'sffc_recruiter', 'sffc_company', 'sffc_deal');
        $total_content = 0;
        $content_breakdown = array();
        
        foreach ($post_types as $post_type) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
                $post_type
            ));
            $total_content += $count;
            $content_breakdown[$post_type] = $count;
        }
        
        // Generate status HTML
        $status_html = '<div class="sffc-search-engine-status">';
        
        // Overall status
        if ($indexer_available && $query_available && $tables_exist === 4 && $total_indexed > 0) {
            $status_html .= '<div class="notice notice-success inline">
                <p><strong><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" style="display: inline; margin-right: 6px; vertical-align: text-bottom;"><polyline points="20,6 9,17 4,12"></polyline></svg>Search Engine Status: OPERATIONAL</strong></p>
                <p>The PE search engine is fully configured and operational with ' . number_format($total_indexed) . ' indexed items.</p>
            </div>';
        } elseif ($indexer_available && $query_available && $tables_exist === 4) {
            $status_html .= '<div class="notice notice-warning inline">
                <p><strong><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" style="display: inline; margin-right: 6px; vertical-align: text-bottom;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>Search Engine Status: TABLES READY, NEEDS INDEXING</strong></p>
                <p>Search tables exist but no content is indexed. Click "Index All Content" to populate the search database.</p>
            </div>';
        } elseif ($indexer_available && $query_available) {
            $status_html .= '<div class="notice notice-warning inline">
                <p><strong><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" style="display: inline; margin-right: 6px; vertical-align: text-bottom;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>Search Engine Status: NEEDS SETUP</strong></p>
                <p>Search classes are available but tables need to be created. Click "Create Search Tables" first.</p>
            </div>';
        } else {
            $status_html .= '<div class="notice notice-error inline">
                <p><strong><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" style="display: inline; margin-right: 6px; vertical-align: text-bottom;"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>Search Engine Status: NOT AVAILABLE</strong></p>
                <p>Search engine classes are not loaded. Please check plugin installation.</p>
            </div>';
        }
        
        // Detailed status grid
        $status_html .= '<div class="sffc-search-status-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0;">';
        
        // Classes status
        $status_html .= '<div class="sffc-status-card" style="padding: 15px; border: 1px solid #ddd; border-radius: 4px;">';
        $status_html .= '<h4>Search Classes</h4>';
        $status_html .= '<div>' . ($indexer_available ? '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" style="display: inline; margin-right: 6px; vertical-align: text-bottom;"><polyline points="20,6 9,17 4,12"></polyline></svg>' : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" style="display: inline; margin-right: 6px; vertical-align: text-bottom;"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>') . ' Search Indexer</div>';
        $status_html .= '<div>' . ($query_available ? '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" style="display: inline; margin-right: 6px; vertical-align: text-bottom;"><polyline points="20,6 9,17 4,12"></polyline></svg>' : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" style="display: inline; margin-right: 6px; vertical-align: text-bottom;"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>') . ' Query Processor</div>';
        $status_html .= '</div>';
        
        // Tables status
        $status_html .= '<div class="sffc-status-card" style="padding: 15px; border: 1px solid #ddd; border-radius: 4px;">';
        $status_html .= '<h4>Database Tables</h4>';
        foreach ($search_tables as $name => $table) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table;
            $display_name = ucwords(str_replace('_', ' ', $name));
            $status_html .= '<div>' . ($exists ? '✅' : '❌') . ' ' . $display_name . '</div>';
        }
        $status_html .= '</div>';
        
        // Content status
        $status_html .= '<div class="sffc-status-card" style="padding: 15px; border: 1px solid #ddd; border-radius: 4px;">';
        $status_html .= '<h4>Available Content</h4>';
        $status_html .= '<div><strong>Total: ' . number_format($total_content) . ' items</strong></div>';
        foreach ($content_breakdown as $post_type => $count) {
            $display_name = ucwords(str_replace('sffc_', '', $post_type));
            $status_html .= '<div>' . $display_name . ': ' . number_format($count) . '</div>';
        }
        $status_html .= '</div>';
        
        // Search index status
        $status_html .= '<div class="sffc-status-card" style="padding: 15px; border: 1px solid #ddd; border-radius: 4px;">';
        $status_html .= '<h4>Search Index</h4>';
        $status_html .= '<div><strong>Indexed: ' . number_format($total_indexed) . ' items</strong></div>';
        if ($total_content > 0) {
            $percentage = round(($total_indexed / $total_content) * 100, 1);
            $status_html .= '<div>Coverage: ' . $percentage . '%</div>';
        }
        $status_html .= '</div>';
        
        $status_html .= '</div>'; // Close grid
        $status_html .= '</div>'; // Close wrapper

        return $status_html;
    }

    /**
     * Get Recruiter Terminal status HTML
     */
    private function get_recruiter_terminal_status()
    {
        global $wpdb;

        // Check if RT classes are available
        $rt_class_available = class_exists('Recruiter_Terminal');
        $rt_db_class_available = class_exists('Recruiter_Terminal_DB');
        $shortcode_exists = shortcode_exists('senna_recruiter_terminal');

        // Check RT tables (New v2.0)
        $rt_core_tables = array(
            'rt_briefs' => $wpdb->prefix . 'rt_briefs',
            'rt_responses' => $wpdb->prefix . 'rt_responses',
            'rt_notes' => $wpdb->prefix . 'rt_notes',
            'rt_analytics' => $wpdb->prefix . 'rt_analytics',
        );

        // Legacy tables
        $rt_legacy_tables = array(
            'rt_campaigns' => $wpdb->prefix . 'rt_campaigns',
            'rt_campaign_targets' => $wpdb->prefix . 'rt_campaign_targets',
            'rt_activity_log' => $wpdb->prefix . 'rt_activity_log',
            'rt_email_templates' => $wpdb->prefix . 'rt_email_templates'
        );

        $rt_tables = array_merge($rt_core_tables, $rt_legacy_tables);

        $core_tables_exist = 0;
        $total_briefs = 0;
        $total_responses = 0;

        foreach ($rt_core_tables as $name => $table) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table;
            if ($exists) {
                $core_tables_exist++;
                if ($name === 'rt_briefs') {
                    $total_briefs = $wpdb->get_var("SELECT COUNT(*) FROM $table");
                }
                if ($name === 'rt_responses') {
                    $total_responses = $wpdb->get_var("SELECT COUNT(*) FROM $table");
                }
            }
        }

        $tables_exist = $core_tables_exist;

        // Generate status HTML
        $status_html = '<div class="sffc-recruiter-terminal-status">';

        // Overall status
        if ($rt_class_available && $rt_db_class_available && $core_tables_exist === 4 && $shortcode_exists) {
            $status_html .= '<div class="notice notice-success inline">
                <p><strong><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" style="display: inline; margin-right: 6px; vertical-align: text-bottom;"><polyline points="20,6 9,17 4,12"></polyline></svg>Recruiter Terminal Status: OPERATIONAL (v2.0)</strong></p>
                <p>The Recruiter Terminal is fully configured with ' . number_format($total_briefs) . ' briefs and ' . number_format($total_responses) . ' responses.</p>
            </div>';
        } elseif ($rt_class_available && $rt_db_class_available && $core_tables_exist < 4) {
            $status_html .= '<div class="notice notice-warning inline">
                <p><strong><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" style="display: inline; margin-right: 6px; vertical-align: text-bottom;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>Recruiter Terminal Status: TABLES MISSING</strong></p>
                <p>Classes are loaded but ' . (4 - $core_tables_exist) . ' core database tables are missing. Click "Create RT Tables" to create them.</p>
            </div>';
        } elseif ($rt_class_available || $rt_db_class_available) {
            $status_html .= '<div class="notice notice-warning inline">
                <p><strong><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" style="display: inline; margin-right: 6px; vertical-align: text-bottom;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>Recruiter Terminal Status: PARTIALLY LOADED</strong></p>
                <p>Some classes are available. Check that all files are uploaded correctly.</p>
            </div>';
        } else {
            $status_html .= '<div class="notice notice-error inline">
                <p><strong><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" style="display: inline; margin-right: 6px; vertical-align: text-bottom;"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>Recruiter Terminal Status: NOT LOADED</strong></p>
                <p>Recruiter Terminal classes are not loaded. Please check that all files exist in includes/recruiter-terminal/</p>
            </div>';
        }

        // Detailed status grid
        $status_html .= '<div class="sffc-rt-status-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0;">';

        // Classes status
        $status_html .= '<div class="sffc-status-card" style="padding: 15px; border: 1px solid #ddd; border-radius: 4px;">';
        $status_html .= '<h4>RT Classes</h4>';
        $status_html .= '<div>' . ($rt_class_available ? '✅' : '❌') . ' Recruiter_Terminal</div>';
        $status_html .= '<div>' . ($rt_db_class_available ? '✅' : '❌') . ' Recruiter_Terminal_DB</div>';
        $status_html .= '<div>' . ($shortcode_exists ? '✅' : '❌') . ' Shortcode Registered</div>';
        $status_html .= '</div>';

        // Core Tables status (v2.0)
        $status_html .= '<div class="sffc-status-card" style="padding: 15px; border: 1px solid #ddd; border-radius: 4px;">';
        $status_html .= '<h4>Core Tables (v2.0)</h4>';
        foreach ($rt_core_tables as $name => $table) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table;
            $display_name = ucwords(str_replace('rt_', '', $name));
            $status_html .= '<div>' . ($exists ? '✅' : '❌') . ' ' . $display_name . '</div>';
        }
        $status_html .= '</div>';

        // Legacy Tables status
        $status_html .= '<div class="sffc-status-card" style="padding: 15px; border: 1px solid #ddd; border-radius: 4px;">';
        $status_html .= '<h4>Legacy Tables</h4>';
        foreach ($rt_legacy_tables as $name => $table) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table;
            $display_name = ucwords(str_replace(array('rt_', '_'), array('', ' '), $name));
            $status_html .= '<div>' . ($exists ? '✅' : '❌') . ' ' . $display_name . '</div>';
        }
        $status_html .= '</div>';

        // Data status
        $status_html .= '<div class="sffc-status-card" style="padding: 15px; border: 1px solid #ddd; border-radius: 4px;">';
        $status_html .= '<h4>Data Status</h4>';
        $status_html .= '<div><strong>Briefs: ' . number_format($total_briefs) . '</strong></div>';
        $status_html .= '<div><strong>Responses: ' . number_format($total_responses) . '</strong></div>';
        $status_html .= '</div>';

        // Files check
        $status_html .= '<div class="sffc-status-card" style="padding: 15px; border: 1px solid #ddd; border-radius: 4px;">';
        $status_html .= '<h4>Required Files</h4>';
        $plugin_dir = defined('SFFC_PLUGIN_DIR') ? SFFC_PLUGIN_DIR : WP_PLUGIN_DIR . '/senna-careers/';
        $required_files = array(
            'class-recruiter-terminal.php' => 'includes/recruiter-terminal/class-recruiter-terminal.php',
            'class-recruiter-terminal-db.php' => 'includes/recruiter-terminal/class-recruiter-terminal-db.php',
            'class-recruiter-terminal-ajax.php' => 'includes/recruiter-terminal/class-recruiter-terminal-ajax.php',
            'recruiter-terminal.php (template)' => 'templates/recruiter-terminal.php',
        );
        foreach ($required_files as $label => $file) {
            $exists = file_exists($plugin_dir . $file);
            $status_html .= '<div>' . ($exists ? '✅' : '❌') . ' ' . $label . '</div>';
        }
        $status_html .= '</div>';

        $status_html .= '</div>'; // Close grid
        $status_html .= '</div>'; // Close wrapper

        return $status_html;
    }

    /**
     * Get spam query statistics HTML
     */
    private function get_spam_query_stats()
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sffc_search_queries';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return '<p>Search queries table does not exist.</p>';
        }

        // Total queries
        $total_queries = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

        // Count spam queries with repetitive words pattern
        $spam_count = $wpdb->get_var("
            SELECT COUNT(*) FROM {$table}
            WHERE query_text REGEXP '(^|[[:space:]])(Top|top|Best|best|firms|compensation|headhunter|Private|equity|Fundraising|insights)[[:space:]]+(Top|top|Best|best|firms|compensation|headhunter|Private|equity|Fundraising|insights)'
               OR query_text LIKE '%Top Top%'
               OR query_text LIKE '%top top%'
               OR query_text LIKE '%firms firms%'
               OR query_text LIKE '%compensation compensation%'
               OR query_text LIKE '%headhunter list%headhunter%'
               OR LENGTH(query_text) > 100
        ");

        $html = '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">';
        $html .= '<div><strong>Total Queries:</strong> ' . number_format($total_queries) . '</div>';
        $html .= '<div><strong>Spam Queries:</strong> <span style="color: #dc3545; font-weight: bold;">' . number_format($spam_count) . '</span></div>';

        if ($total_queries > 0) {
            $spam_percentage = round(($spam_count / $total_queries) * 100, 1);
            $html .= '<div><strong>Spam Percentage:</strong> ' . $spam_percentage . '%</div>';
        }

        $html .= '</div>';

        if ($spam_count > 0) {
            $html .= '<p style="margin-top: 10px; color: #856404;"><strong>Warning:</strong> ' . number_format($spam_count) . ' spam queries detected that could harm your SEO.</p>';
        } else {
            $html .= '<p style="margin-top: 10px; color: #155724;"><strong>Good news:</strong> No obvious spam queries detected.</p>';
        }

        return $html;
    }
}

// Add JavaScript functions for table operations
add_action('admin_footer', function () {
    if (!isset($_GET['page']) || $_GET['page'] !== 'sffc-database') {
        return;
    }
    ?>
    <script>
        function sffc_view_table(table) {
            alert('View table: ' + table + ' (Feature coming in Phase 2)');
        }

        function sffc_export_table(table) {
            alert('Export table: ' + table + ' (Feature coming in Phase 2)');
        }
    </script>
<?php
});
