/**
 * MENA Careers CRM - Admin JavaScript
 */

(function($) {
    'use strict';

    window.SennaCRMAdmin = {

        /**
         * Initialize admin functionality
         */
        init: function() {
            this.bindEvents();
            this.initSortable();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;

            // Approve post
            $(document).on('click', '.sffc-crm-approve-post', function(e) {
                e.preventDefault();
                var postId = $(this).data('post-id');
                self.approvePost(postId, $(this));
            });

            // Reject post
            $(document).on('click', '.sffc-crm-reject-post', function(e) {
                e.preventDefault();
                var postId = $(this).data('post-id');
                self.rejectPost(postId, $(this));
            });

            // Delete post
            $(document).on('click', '.sffc-crm-delete-post', function(e) {
                e.preventDefault();
                if (!confirm('Are you sure you want to delete this post?')) {
                    return;
                }
                var postId = $(this).data('post-id');
                self.deletePost(postId, $(this));
            });

            // Archive post to HR Outreach (and delete original)
            $(document).on('click', '.sffc-crm-archive-post', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var roleTitle = $btn.data('roleTitle') || '';
                var companyName = $btn.data('companyName') || '';
                var label = roleTitle ? roleTitle : 'this post';
                if (companyName) {
                    label += ' @ ' + companyName;
                }

                if (!confirm('Archive ' + label + ' to HR Outreach and delete it from posts?')) {
                    return;
                }

                self.archivePostToHr($btn.data('postId'), $btn);
            });

            // Bulk actions
            $(document).on('click', '#sffc-crm-bulk-approve', function(e) {
                e.preventDefault();
                self.bulkAction('approve');
            });

            $(document).on('click', '#sffc-crm-bulk-reject', function(e) {
                e.preventDefault();
                self.bulkAction('reject');
            });

            $(document).on('click', '#sffc-crm-bulk-rewrite-claude', function(e) {
                e.preventDefault();
                self.bulkAction('rewrite_claude');
            });

            $(document).on('click', '#sffc-crm-bulk-delete', function(e) {
                e.preventDefault();
                self.bulkAction('delete');
            });

            // Select all checkbox
            $(document).on('change', '#sffc-crm-select-all', function() {
                var checked = $(this).prop('checked');
                $('.sffc-crm-post-checkbox').prop('checked', checked);
            });

            // CSV Import
            $(document).on('submit', '#sffc-crm-import-form', function(e) {
                e.preventDefault();
                self.importCSV($(this));
            });

            // Post quick edit
            $(document).on('click', '.sffc-crm-quick-edit', function(e) {
                e.preventDefault();
                var postId = $(this).data('post-id');
                self.openQuickEdit(postId);
            });

            // Save quick edit
            $(document).on('click', '.sffc-crm-save-quick-edit', function(e) {
                e.preventDefault();
                self.saveQuickEdit($(this));
            });

            // Cancel quick edit
            $(document).on('click', '.sffc-crm-cancel-quick-edit', function(e) {
                e.preventDefault();
                self.cancelQuickEdit($(this));
            });
        },

        /**
         * Initialize sortable for kanban columns (if present)
         */
        initSortable: function() {
            if (typeof $.fn.sortable === 'undefined') {
                return;
            }

            $('.sffc-crm-admin-kanban-items').sortable({
                connectWith: '.sffc-crm-admin-kanban-items',
                placeholder: 'sffc-crm-sortable-placeholder',
                cursor: 'move',
                update: function(event, ui) {
                    // Handle stage update if needed
                }
            });
        },

        /**
         * Approve a post
         */
        approvePost: function(postId, $btn) {
            var self = this;
            $btn.prop('disabled', true).text('Approving...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_admin_approve_post',
                    post_id: postId,
                    nonce: $('#sffc_crm_admin_nonce').val()
                },
                success: function(response) {
                    if (response.success) {
                        $btn.closest('tr').find('.sffc-crm-status').text('Approved').addClass('approved');
                        $btn.replaceWith('<span class="approved-text">Approved</span>');
                        self.showNotice('Post approved successfully', 'success');
                    } else {
                        $btn.prop('disabled', false).text('Approve');
                        self.showNotice(response.data.message || 'Failed to approve post', 'error');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('Approve');
                    self.showNotice('An error occurred', 'error');
                }
            });
        },

        /**
         * Reject a post
         */
        rejectPost: function(postId, $btn) {
            var self = this;
            var reason = prompt('Reason for rejection (optional):');

            if (reason === null) {
                return; // Cancelled
            }

            $btn.prop('disabled', true).text('Rejecting...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_admin_reject_post',
                    post_id: postId,
                    reason: reason,
                    nonce: $('#sffc_crm_admin_nonce').val()
                },
                success: function(response) {
                    if (response.success) {
                        $btn.closest('tr').fadeOut(300, function() {
                            $(this).remove();
                        });
                        self.showNotice('Post rejected', 'success');
                    } else {
                        $btn.prop('disabled', false).text('Reject');
                        self.showNotice(response.data.message || 'Failed to reject post', 'error');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('Reject');
                    self.showNotice('An error occurred', 'error');
                }
            });
        },

        /**
         * Delete a post
         */
        deletePost: function(postId, $btn) {
            var self = this;
            $btn.prop('disabled', true);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_admin_delete_post',
                    post_id: postId,
                    nonce: $('#sffc_crm_admin_nonce').val()
                },
                success: function(response) {
                    if (response.success) {
                        $btn.closest('tr').fadeOut(300, function() {
                            $(this).remove();
                        });
                        self.showNotice('Post deleted', 'success');
                    } else {
                        $btn.prop('disabled', false);
                        self.showNotice(response.data.message || 'Failed to delete post', 'error');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false);
                    self.showNotice('An error occurred', 'error');
                }
            });
        },

        /**
         * Archive a post to HR outreach and delete original entry
         */
        archivePostToHr: function(postId, $btn) {
            if (!postId) {
                return;
            }

            var self = this;
            var originalText = $btn.text();
            $btn.prop('disabled', true).text('Archiving...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_admin_archive_post_to_hr',
                    post_id: postId,
                    delete_original: 1,
                    nonce: $('#sffc_crm_admin_nonce').val()
                },
                success: function(response) {
                    if (response.success) {
                        $btn.closest('tr').fadeOut(300, function() {
                            $(this).remove();
                        });
                        self.showNotice(response.data.message || 'Post archived to HR Outreach.', 'success');
                    } else {
                        $btn.prop('disabled', false).text(originalText);
                        self.showNotice(response.data.message || 'Failed to archive post.', 'error');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text(originalText);
                    self.showNotice('An error occurred', 'error');
                }
            });
        },

        /**
         * Bulk action
         */
        bulkAction: function(action) {
            var self = this;
            var postIds = [];

            $('.sffc-crm-post-checkbox:checked').each(function() {
                postIds.push($(this).val());
            });

            if (postIds.length === 0) {
                self.showNotice('Please select at least one post', 'warning');
                return;
            }

            var message = 'Are you sure you want to ' + action + ' ' + postIds.length + ' posts?';
            if (action === 'delete') {
                message = 'Are you sure you want to permanently delete ' + postIds.length + ' posts? This cannot be undone.';
            } else if (action === 'rewrite_claude') {
                message = 'Rewrite ' + postIds.length + ' selected posts with Claude? This will replace their current job descriptions.';
            }

            if (!confirm(message)) {
                return;
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_admin_bulk_action',
                    bulk_action: action,
                    post_ids: postIds,
                    nonce: $('#sffc_crm_admin_nonce').val()
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        self.showNotice(response.data.message || 'Bulk action failed', 'error');
                    }
                },
                error: function() {
                    self.showNotice('An error occurred', 'error');
                }
            });
        },

        /**
         * Import CSV
         */
        importCSV: function($form) {
            var self = this;
            var formData = new FormData($form[0]);
            formData.append('action', 'sffc_crm_admin_import_csv');
            formData.append('nonce', $('#sffc_crm_admin_nonce').val());

            var $submitBtn = $form.find('button[type="submit"]');
            $submitBtn.prop('disabled', true).text('Importing...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        self.showNotice('Import completed: ' + response.data.imported + ' posts imported', 'success');
                        if (response.data.errors && response.data.errors.length > 0) {
                            self.showNotice('Warnings: ' + response.data.errors.join(', '), 'warning');
                        }
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        self.showNotice(response.data.message || 'Import failed', 'error');
                        $submitBtn.prop('disabled', false).text('Import');
                    }
                },
                error: function() {
                    self.showNotice('An error occurred during import', 'error');
                    $submitBtn.prop('disabled', false).text('Import');
                }
            });
        },

        /**
         * Open quick edit form
         */
        openQuickEdit: function(postId) {
            var $row = $('tr[data-post-id="' + postId + '"]');
            var $editRow = $row.next('.sffc-crm-quick-edit-row');

            if ($editRow.length) {
                $editRow.toggle();
            } else {
                // Create quick edit row dynamically
                // For now, just alert - this would be expanded based on needs
                alert('Quick edit for post ' + postId);
            }
        },

        /**
         * Save quick edit
         */
        saveQuickEdit: function($btn) {
            var self = this;
            var $row = $btn.closest('.sffc-crm-quick-edit-row');
            var postId = $row.data('post-id');

            var data = {
                action: 'sffc_crm_admin_quick_edit',
                post_id: postId,
                role_title: $row.find('[name="role_title"]').val(),
                company: $row.find('[name="company"]').val(),
                location: $row.find('[name="location"]').val(),
                sector: $row.find('[name="sector"]').val(),
                seniority: $row.find('[name="seniority"]').val(),
                nonce: $('#sffc_crm_admin_nonce').val()
            };

            $btn.prop('disabled', true).text('Saving...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        $row.hide();
                        self.showNotice('Post updated', 'success');
                        // Update the display row with new values
                    } else {
                        self.showNotice(response.data.message || 'Failed to save', 'error');
                    }
                    $btn.prop('disabled', false).text('Save');
                },
                error: function() {
                    self.showNotice('An error occurred', 'error');
                    $btn.prop('disabled', false).text('Save');
                }
            });
        },

        /**
         * Cancel quick edit
         */
        cancelQuickEdit: function($btn) {
            $btn.closest('.sffc-crm-quick-edit-row').hide();
        },

        /**
         * Show admin notice
         */
        showNotice: function(message, type) {
            type = type || 'info';
            var noticeClass = 'notice-' + type;

            var $notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');

            // Insert at top of admin content
            $('.wrap h1').first().after($notice);

            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };

    // Initialize on DOM ready
    $(document).ready(function() {
        if ($('.sffc-crm-admin').length) {
            SennaCRMAdmin.init();
        }
    });

})(jQuery);
