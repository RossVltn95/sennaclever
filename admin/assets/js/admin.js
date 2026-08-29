/**
 * MENA Careers - Admin JavaScript
 */

(function ($) {
  "use strict";

  // Admin object
  window.SFFCAdmin = {
    // Configuration
    config: {
      ajaxUrl: sffc_admin.ajax_url,
      nonce: sffc_admin.nonce,
      strings: sffc_admin.strings,
    },

    // Current state
    state: {
      currentPage: 1,
      conversationsPerPage: 20,
    },

    // Initialize
    init: function () {
      this.bindEvents();
      this.initColorPickers();
      this.loadConversations();
    },

    // Bind events
    bindEvents: function () {
      // Database actions
      $("#sffc-create-tables").on("click", this.createTables.bind(this));
      $("#sffc-insert-sample-data").on(
        "click",
        this.insertSampleData.bind(this)
      );
      $("#sffc-insert-templates").on("click", this.insertTemplates.bind(this));
      $("#sffc-insert-opportunities").on(
        "click",
        this.insertOpportunities.bind(this)
      );

      // Premium Content Generation
      $("#sffc-generate-all-content").on(
        "click",
        this.generateAllContent.bind(this)
      );

      // Feed management
      $("#sffc-add-feed").on("click", this.addFeed.bind(this));
      $("#sffc-test-feed").on("click", this.testFeed.bind(this));
      $(document).on("click", ".sffc-test-feed", this.testFeed.bind(this));
      $(document).on("click", ".sffc-delete-feed", this.deleteFeed.bind(this));
      $(document).on("click", ".sffc-edit-feed", this.editFeed.bind(this));
      $(document).on(
        "change",
        ".sffc-feed-active",
        this.toggleFeedActive.bind(this)
      );

      // View table data
      $(".sffc-view-table").on("click", this.viewTableData.bind(this));
      $(".sffc-export-table").on("click", this.exportTableData.bind(this));

      // View conversation
      $(document).on(
        "click",
        ".sffc-view-conversation",
        this.viewConversation.bind(this)
      );

      // Export data
      $(".sffc-export-data").on("click", this.exportAllData.bind(this));

      // Filters
      $("#sffc-apply-filters").on("click", this.applyFilters.bind(this));

      // Pagination
      $("#sffc-prev-page").on("click", this.previousPage.bind(this));
      $("#sffc-next-page").on("click", this.nextPage.bind(this));

      // Modal close
      $(".sffc-modal-close").on("click", this.closeModal.bind(this));
      $(document).on(
        "click",
        ".sffc-modal",
        function (e) {
          if ($(e.target).hasClass("sffc-modal")) {
            this.closeModal();
          }
        }.bind(this)
      );
    },

    // Initialize color pickers
    initColorPickers: function () {
      if ($.fn.wpColorPicker) {
        $(".color-field").wpColorPicker();
      }
    },

    // Load conversations
    loadConversations: function () {
      if ($("#sffc-conversations-table").length === 0) {
        return;
      }

      $.ajax({
        url: this.config.ajaxUrl,
        type: "POST",
        data: {
          action: "sffc_get_conversations",
          nonce: this.config.nonce,
          page: this.state.currentPage,
          per_page: this.state.conversationsPerPage,
        },
        success: function (response) {
          if (response.success) {
            this.displayConversations(response.data.conversations);
          }
        }.bind(this),
      });
    },

    // Display conversations
    displayConversations: function (conversations) {
      const $tbody = $("#sffc-conversations-table tbody");
      $tbody.empty();

      if (!conversations || conversations.length === 0) {
        $tbody.append('<tr><td colspan="8">No conversations found</td></tr>');
        return;
      }

      conversations.forEach(
        function (conv) {
          const row = `
                    <tr>
                        <td>${conv.id}</td>
                        <td>${this.getUserDisplayName(conv.user_id)}</td>
                        <td><span class="sffc-mode-badge sffc-mode-${
                          conv.mode
                        }">${conv.mode}</span></td>
                        <td>${conv.message_count || 0}</td>
                        <td><span class="sffc-status-badge sffc-status-${
                          conv.status
                        }">${conv.status}</span></td>
                        <td>${this.formatDate(conv.created_at)}</td>
                        <td>${this.formatDate(conv.updated_at)}</td>
                        <td>
                            <button class="button button-small sffc-view-conversation" data-id="${
                              conv.id
                            }">View</button>
                        </td>
                    </tr>
                `;
          $tbody.append(row);
        }.bind(this)
      );
    },

    // View conversation
    viewConversation: function (e) {
      const conversationId = $(e.target).data("id");

      $.ajax({
        url: this.config.ajaxUrl,
        type: "POST",
        data: {
          action: "sffc_get_conversation_messages",
          nonce: this.config.nonce,
          conversation_id: conversationId,
        },
        success: function (response) {
          if (response.success) {
            this.showConversationModal(response.data);
          }
        }.bind(this),
      });
    },

    // Show conversation modal
    showConversationModal: function (data) {
      const $modal = $("#sffc-conversation-modal");
      const $body = $modal.find(".sffc-modal-body");

      let html = '<div class="sffc-conversation-messages">';

      if (data.messages && data.messages.length > 0) {
        data.messages.forEach(
          function (msg) {
            const senderClass =
              msg.sender_type === "user" ? "user-message" : "senna-message";
            html += `
                        <div class="sffc-admin-message ${senderClass}">
                            <div class="message-header">
                                <strong>${
                                  msg.sender_type === "user" ? "User" : "MENA Careers"
                                }</strong>
                                <span class="message-time">${this.formatDate(
                                  msg.created_at
                                )}</span>
                            </div>
                            <div class="message-content">${msg.message}</div>
                        </div>
                    `;
          }.bind(this)
        );
      } else {
        html += "<p>No messages in this conversation</p>";
      }

      html += "</div>";

      $body.html(html);
      $modal.fadeIn();
    },

    // Create database tables
    createTables: function () {
      var $button = $("#sffc-create-tables");
      var $status = $("#sffc-database-status");
      var originalText = $button.text();

      $button.prop("disabled", true).text("Creating...");
      $status.html(
        '<div class="notice notice-info"><p>Creating database tables...</p></div>'
      );

      $.ajax({
        url: this.config.ajaxUrl,
        type: "POST",
        data: {
          action: "sffc_create_tables",
          nonce: this.config.nonce,
        },
        success: function (response) {
          if (response.success) {
            $status.html(
              '<div class="notice notice-success"><p>' +
                (response.data.message || "Tables created successfully!") +
                "</p></div>"
            );
            // Reload page after 2 seconds to update table info
            setTimeout(function () {
              location.reload();
            }, 2000);
          } else {
            $status.html(
              '<div class="notice notice-error"><p>' +
                (response.data.message || "Failed to create tables") +
                "</p></div>"
            );
          }
        }.bind(this),
        error: function () {
          $status.html(
            '<div class="notice notice-error"><p>An error occurred while creating tables</p></div>'
          );
        },
        complete: function () {
          $button.prop("disabled", false).text(originalText);
        },
      });
    },

    // Generate all premium content
    generateAllContent: function () {
      var $button = $("#sffc-generate-all-content");
      var $statusDiv = $("#sffc-content-generation-status");
      var $progressBar = $statusDiv.find(".sffc-progress-fill");
      var $statusMessage = $statusDiv.find(".sffc-status-message");
      var originalText = $button.html();

      // Show status div and disable button
      $statusDiv.show();
      $button
        .prop("disabled", true)
        .html(
          '<span class="dashicons dashicons-update spinning"></span> Generating Content...'
        );

      // Content types to generate
      var contentTypes = [
        { type: "case_studies", name: "Case Studies", count: 10 },
        { type: "interview_questions", name: "Interview Questions", count: 30 },
        { type: "financial_terms", name: "Financial Terms", count: 40 },
        { type: "modeling_guides", name: "Modeling Guides", count: 5 },
        { type: "day_in_life", name: "Day in Life Guides", count: 18 },
      ];

      var totalItems = contentTypes.reduce((sum, item) => sum + item.count, 0);
      var itemsGenerated = 0;
      var currentTypeIndex = 0;

      // Function to generate content for each type
      function generateContentType() {
        if (currentTypeIndex >= contentTypes.length) {
          // All done
          $progressBar.css("width", "100%");
          $statusMessage.html(
            '<strong style="color: green;">✓ All content generated successfully!</strong>'
          );
          $button.prop("disabled", false).html(originalText);

          // Reload page after 2 seconds
          setTimeout(function () {
            location.reload();
          }, 2000);
          return;
        }

        var currentType = contentTypes[currentTypeIndex];
        $statusMessage.html("Generating " + currentType.name + "...");

        $.ajax({
          url: sffc_admin.ajax_url,
          type: "POST",
          data: {
            action: "sffc_generate_prep_content",
            content_type: currentType.type,
            nonce: sffc_admin.nonce,
          },
          success: function (response) {
            if (response.success) {
              itemsGenerated += currentType.count;
              var progress = (itemsGenerated / totalItems) * 100;
              $progressBar.css("width", progress + "%");

              currentTypeIndex++;
              // Generate next type
              generateContentType();
            } else {
              $statusMessage.html(
                '<span style="color: red;">Error generating ' +
                  currentType.name +
                  ": " +
                  (response.data || "Unknown error") +
                  "</span>"
              );
              $button.prop("disabled", false).html(originalText);
            }
          },
          error: function () {
            $statusMessage.html(
              '<span style="color: red;">Network error while generating ' +
                currentType.name +
                "</span>"
            );
            $button.prop("disabled", false).html(originalText);
          },
        });
      }

      // Start generation
      generateContentType();
    },

    // Insert sample data
    insertSampleData: function () {
      if (!confirm("Insert sample conversation data?")) {
        return;
      }

      $.ajax({
        url: this.config.ajaxUrl,
        type: "POST",
        data: {
          action: "sffc_insert_sample_data",
          nonce: this.config.nonce,
        },
        success: function (response) {
          if (response.success) {
            this.showNotification(
              "Sample data inserted successfully",
              "success"
            );
            this.loadConversations();
          } else {
            this.showNotification("Failed to insert sample data", "error");
          }
        }.bind(this),
      });
    },

    // Insert templates
    insertTemplates: function () {
      if (!confirm("Insert message templates?")) {
        return;
      }

      $.ajax({
        url: this.config.ajaxUrl,
        type: "POST",
        data: {
          action: "sffc_insert_templates",
          nonce: this.config.nonce,
        },
        success: function (response) {
          if (response.success) {
            this.showNotification("Templates inserted successfully", "success");
          } else {
            this.showNotification("Failed to insert templates", "error");
          }
        }.bind(this),
      });
    },

    // Insert opportunities
    insertOpportunities: function () {
      if (!confirm("Insert sample opportunities?")) {
        return;
      }

      $.ajax({
        url: this.config.ajaxUrl,
        type: "POST",
        data: {
          action: "sffc_insert_opportunities",
          nonce: this.config.nonce,
        },
        success: function (response) {
          if (response.success) {
            this.showNotification(
              "Opportunities inserted successfully",
              "success"
            );
          } else {
            this.showNotification("Failed to insert opportunities", "error");
          }
        }.bind(this),
      });
    },

    // View table data
    viewTableData: function (e) {
      const table = $(e.target).data("table");

      $.ajax({
        url: this.config.ajaxUrl,
        type: "POST",
        data: {
          action: "sffc_get_table_data",
          nonce: this.config.nonce,
          table: table,
        },
        success: function (response) {
          if (response.success) {
            this.showTableModal(table, response.data);
          }
        }.bind(this),
      });
    },

    // Show table modal
    showTableModal: function (tableName, data) {
      const $modal = $("#sffc-table-modal");
      const $title = $("#sffc-modal-title");
      const $body = $("#sffc-table-data");

      $title.text("Table: " + tableName);

      let html = '<table class="wp-list-table widefat fixed striped">';

      if (data && data.length > 0) {
        // Add headers
        html += "<thead><tr>";
        Object.keys(data[0]).forEach(function (key) {
          html += `<th>${key}</th>`;
        });
        html += "</tr></thead><tbody>";

        // Add rows
        data.forEach(function (row) {
          html += "<tr>";
          Object.values(row).forEach(function (value) {
            html += `<td>${value || "-"}</td>`;
          });
          html += "</tr>";
        });
        html += "</tbody>";
      } else {
        html += "<tbody><tr><td>No data in this table</td></tr></tbody>";
      }

      html += "</table>";

      $body.html(html);
      $modal.fadeIn();
    },

    // Export table data
    exportTableData: function (e) {
      const table = $(e.target).data("table");

      window.location.href =
        this.config.ajaxUrl +
        "?action=sffc_export_table&table=" +
        table +
        "&nonce=" +
        this.config.nonce;
    },

    // Export all data
    exportAllData: function () {
      if (!confirm("Export all plugin data?")) {
        return;
      }

      window.location.href =
        this.config.ajaxUrl +
        "?action=sffc_export_all&nonce=" +
        this.config.nonce;
    },

    // Apply filters
    applyFilters: function () {
      const mode = $("#sffc-filter-mode").val();
      const status = $("#sffc-filter-status").val();

      // Reload conversations with filters
      this.state.currentPage = 1;
      this.loadConversations();
    },

    // Previous page
    previousPage: function () {
      if (this.state.currentPage > 1) {
        this.state.currentPage--;
        this.loadConversations();
        this.updatePaginationUI();
      }
    },

    // Next page
    nextPage: function () {
      this.state.currentPage++;
      this.loadConversations();
      this.updatePaginationUI();
    },

    // Update pagination UI
    updatePaginationUI: function () {
      $("#sffc-page-info").text("Page " + this.state.currentPage);
      $("#sffc-prev-page").prop("disabled", this.state.currentPage === 1);
    },

    // Close modal
    closeModal: function () {
      $(".sffc-modal").fadeOut();
    },

    // Show notification
    showNotification: function (message, type) {
      const $notice = $(
        '<div class="notice notice-' +
          type +
          ' is-dismissible"><p>' +
          message +
          "</p></div>"
      );
      $(".wrap h1").after($notice);

      setTimeout(function () {
        $notice.fadeOut(function () {
          $(this).remove();
        });
      }, 4000);
    },

    // Get user display name
    getUserDisplayName: function (userId) {
      if (typeof userId === "string" && userId.startsWith("guest_")) {
        return "Guest User";
      }
      return "User #" + userId;
    },

    // Format date
    formatDate: function (dateStr) {
      if (!dateStr) {
        return "-";
      }
      const date = new Date(dateStr);
      return date.toLocaleDateString() + " " + date.toLocaleTimeString();
    },

    // Feed Management Methods

    // Add new feed
    addFeed: function () {
      var feedName = $("#new_feed_name").val().trim();
      var feedUrl = $("#new_feed_url").val().trim();
      var feedCategory = $("#new_feed_category").val();
      var feedPriority = $("#new_feed_priority").val();

      if (!feedName || !feedUrl) {
        alert("Please enter both feed name and URL");
        return;
      }

      var $button = $("#sffc-add-feed");
      var originalText = $button.text();
      $button.prop("disabled", true).text("Adding...");

      $.ajax({
        url: this.config.ajaxUrl,
        type: "POST",
        data: {
          action: "sffc_add_feed",
          nonce: this.config.nonce,
          feed_name: feedName,
          feed_url: feedUrl,
          feed_category: feedCategory,
          priority: feedPriority,
        },
        success: function (response) {
          if (response.success) {
            this.showNotification("Feed added successfully", "success");
            // Clear form
            $("#new_feed_name").val("");
            $("#new_feed_url").val("");
            $("#new_feed_priority").val("10");
            // Reload the feeds table
            this.reloadFeedsTable();
          } else {
            this.showNotification(
              response.data.message || "Failed to add feed",
              "error"
            );
          }
        }.bind(this),
        error: function () {
          this.showNotification("Error adding feed", "error");
        }.bind(this),
        complete: function () {
          $button.prop("disabled", false).text(originalText);
        },
      });
    },

    // Test feed
    testFeed: function (e) {
      var feedId = $(e.target).data("feed-id");
      var feedUrl = $(e.target).data("feed-url");

      // If testing the new feed (before adding)
      if (!feedId && $("#new_feed_url").length > 0) {
        feedUrl = $("#new_feed_url").val().trim();
        if (!feedUrl) {
          alert("Please enter a feed URL to test");
          return;
        }
      }

      var $button = $(e.target);
      var originalText = $button.text();
      $button.prop("disabled", true).text("Testing...");

      $.ajax({
        url: this.config.ajaxUrl,
        type: "POST",
        data: {
          action: "sffc_test_feed",
          nonce: this.config.nonce,
          feed_id: feedId,
          feed_url: feedUrl,
        },
        success: function (response) {
          if (response.success) {
            var message = "Feed test successful!\n\n";
            message += "Items found: " + response.data.item_count + "\n";
            if (
              response.data.sample_titles &&
              response.data.sample_titles.length > 0
            ) {
              message += "\nSample headlines:\n";
              response.data.sample_titles.forEach(function (title) {
                message += "• " + title + "\n";
              });
            }
            alert(message);
          } else {
            alert(
              "Feed test failed: " + (response.data.message || "Unknown error")
            );
          }
        }.bind(this),
        error: function () {
          alert("Error testing feed");
        },
        complete: function () {
          $button.prop("disabled", false).text(originalText);
        },
      });
    },

    // Delete feed
    deleteFeed: function (e) {
      var feedId = $(e.target).data("feed-id");
      var $row = $(e.target).closest("tr");
      var feedName = $row.find(".feed-name").text();

      if (
        !confirm('Are you sure you want to delete the feed "' + feedName + '"?')
      ) {
        return;
      }

      var $button = $(e.target);
      var originalText = $button.text();
      $button.prop("disabled", true).text("Deleting...");

      $.ajax({
        url: this.config.ajaxUrl,
        type: "POST",
        data: {
          action: "sffc_delete_feed",
          nonce: this.config.nonce,
          feed_id: feedId,
        },
        success: function (response) {
          if (response.success) {
            this.showNotification("Feed deleted successfully", "success");
            $row.fadeOut(function () {
              $(this).remove();
            });
          } else {
            this.showNotification("Failed to delete feed", "error");
          }
        }.bind(this),
        error: function () {
          this.showNotification("Error deleting feed", "error");
        },
        complete: function () {
          $button.prop("disabled", false).text(originalText);
        },
      });
    },

    // Edit feed
    editFeed: function (e) {
      var feedId = $(e.target).data("feed-id");
      var $row = $(e.target).closest("tr");

      // Get current values
      var feedName = $row.find(".feed-name").text();
      var feedCategory = $row.find(".feed-category").text();
      var feedPriority = $row.find(".feed-priority").text();

      // Populate the form
      $("#new_feed_name").val(feedName);
      $("#new_feed_category").val(feedCategory);
      $("#new_feed_priority").val(feedPriority);

      // Change button to update mode
      var $addButton = $("#sffc-add-feed");
      $addButton.text("Update Feed").data("editing-id", feedId);

      // Scroll to form
      $("html, body").animate(
        {
          scrollTop: $("#new_feed_name").offset().top - 100,
        },
        500
      );

      // Highlight the form
      $(".sffc-feed-form").css("background", "#fffbcc");
      setTimeout(function () {
        $(".sffc-feed-form").css("background", "");
      }, 2000);
    },

    // Toggle feed active status
    toggleFeedActive: function (e) {
      var $checkbox = $(e.target);
      var feedId = $checkbox.data("feed-id");
      var isActive = $checkbox.prop("checked") ? 1 : 0;

      $.ajax({
        url: this.config.ajaxUrl,
        type: "POST",
        data: {
          action: "sffc_toggle_feed",
          nonce: this.config.nonce,
          feed_id: feedId,
          is_active: isActive,
        },
        success: function (response) {
          if (response.success) {
            this.showNotification("Feed status updated", "success");
          } else {
            // Revert checkbox state
            $checkbox.prop("checked", !$checkbox.prop("checked"));
            this.showNotification("Failed to update feed status", "error");
          }
        }.bind(this),
        error: function () {
          // Revert checkbox state
          $checkbox.prop("checked", !$checkbox.prop("checked"));
          this.showNotification("Error updating feed status", "error");
        }.bind(this),
      });
    },

    // Reload feeds table
    reloadFeedsTable: function () {
      $.ajax({
        url: this.config.ajaxUrl,
        type: "POST",
        data: {
          action: "sffc_get_feeds_table",
          nonce: this.config.nonce,
        },
        success: function (response) {
          if (response.success) {
            $("#sffc-feeds-table tbody").html(response.data.html);
          }
        }.bind(this),
      });
    },
  };

  // Initialize when document is ready
  $(document).ready(function () {
    SFFCAdmin.init();
  });
})(jQuery);
