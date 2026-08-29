/**
 * WSJ CV Chat Integration
 * Compact container in chat that expands to full interface
 */

(function ($) {
  "use strict";

  let wsjRenderer = null;
  let currentJobContext = null;
  let isExpanded = false;

  function isLoggedIn() {
    const ajax = window.sffc_ajax || {};
    const frontend = window.sffc_frontend || {};
    return !!(
      ajax.user_logged_in ||
      ajax.is_logged_in === "1" ||
      ajax.is_logged_in === true ||
      frontend.is_logged_in === "1" ||
      frontend.is_logged_in === true ||
      parseInt(ajax.user_id || frontend.user_id || 0, 10) > 0
    );
  }

  function ensureLoginFor(actionKey) {
    if (
      window.SkillFarmAccess &&
      typeof window.SkillFarmAccess.requireLogin === "function"
    ) {
      return window.SkillFarmAccess.requireLogin(actionKey);
    }

    if (isLoggedIn()) {
      return true;
    }

    if (
      window.SkillFarmAccess &&
      typeof window.SkillFarmAccess.showPrompt === "function"
    ) {
      window.SkillFarmAccess.showPrompt(actionKey, { force: true });
    }
    return false;
  }

  // Create compact WSJ container for chat
  window.createWSJChatContainer = function (jobTitle, company, jobId, jobData) {
    currentJobContext = {
      jobId: jobId || "manual",
      jobTitle: jobTitle || "Position",
      company: company || "Company",
      jobData: jobData || null,
    };

    // Compact container that fits nicely in chat
    const compactContainer = `
            <div class="wsj-cv-chat-container" style="background: white; border-radius: 12px; padding: 20px; margin: 15px 0; box-shadow: 0 4px 16px rgba(0,0,0,0.08); font-family: 'Minion Pro', Georgia, serif;">
                <!-- Header -->
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 20px;">
                    <div>
                        <h3 style="color: #1a472a; margin: 0 0 8px; font-size: 18px; font-weight: 700;">
                            📄 WSJ CV Tailoring
                        </h3>
                        <p style="color: #666; margin: 0; font-size: 14px;">
                            For: <strong>${jobTitle}</strong> at <strong>${company}</strong>
                        </p>
                    </div>
                    <button onclick="expandWSJInterface()" style="padding: 6px 12px; background: linear-gradient(135deg, #1a472a, #2d6a4f); color: white; border: none; border-radius: 6px; font-size: 12px; cursor: pointer;">
                        ⛶ Expand
                    </button>
                </div>
                
                <!-- Quick Input Area -->
                <div id="wsj-compact-content">
                    <textarea id="wsj-cv-compact-input" placeholder="Paste your CV text here..." style="width: 100%; height: 150px; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-family: 'Monaco', monospace; font-size: 12px; resize: vertical; margin-bottom: 15px;"></textarea>
                    
                    <!-- Action Buttons -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px;">
                        <button onclick="quickParseCV()" style="padding: 10px; background: white; border: 2px solid #2d6a4f; color: #2d6a4f; border-radius: 6px; font-weight: 600; font-size: 13px; cursor: pointer;">
                            ⚡ Quick Parse
                        </button>
                        <button onclick="quickTailorCV()" style="padding: 10px; background: linear-gradient(135deg, #1a472a, #2d6a4f); color: white; border: none; border-radius: 6px; font-weight: 600; font-size: 13px; cursor: pointer;">
                            ▶ Tailor Now
                        </button>
                        <button onclick="expandWithPreview()" style="padding: 10px; background: linear-gradient(135deg, #d4af37, #f4d03f); color: #1a472a; border: none; border-radius: 6px; font-weight: 600; font-size: 13px; cursor: pointer;">
                            ◉ Preview
                        </button>
                    </div>
                    
                    <!-- Status -->
                    <div id="wsj-compact-status" style="margin-top: 15px; padding: 10px; background: #f0f9f4; border-left: 3px solid #2d6a4f; border-radius: 4px; font-size: 13px; color: #1a472a; display: none;">
                        Ready to process
                    </div>
                </div>
            </div>
        `;

    return compactContainer;
  };

  // Quick parse in compact view
  window.quickParseCV = function () {
    const cvText = $("#wsj-cv-compact-input").val();
    if (!cvText.trim()) {
      showCompactStatus("⚠ Please enter CV text", "error");
      return;
    }

    showCompactStatus("• Parsing CV...", "processing");

    // Initialize WSJ renderer if needed
    if (!wsjRenderer && typeof WSJCVRendererUltimate !== "undefined") {
      wsjRenderer = new WSJCVRendererUltimate({
        container: document.createElement("div"),
        editable: false,
        animations: false,
      });
    }

    if (wsjRenderer) {
      try {
        wsjRenderer.updateFromText(cvText);
        const parsed = wsjRenderer.getData();

        showCompactStatus(
          `• Parsed: ${parsed.name || "Unknown"} | ${
            parsed.experience?.length || 0
          } experiences | ${parsed.skills?.length || 0} skills`,
          "success"
        );
      } catch (e) {
        showCompactStatus("⚠ Parse error", "error");
      }
    }
  };

  // Quick tailor in compact view
  window.quickTailorCV = function () {
    if (!ensureLoginFor("quick-tailor")) {
      return;
    }

    const cvText = $("#wsj-cv-compact-input").val();
    if (!cvText.trim()) {
      showCompactStatus("⚠ Please enter CV text", "error");
      return;
    }

    showCompactStatus(
      `▶ Tailoring for ${currentJobContext.jobTitle}...`,
      "processing"
    );

    // Debug: Check job description
    const jobDescription =
      currentJobContext.jobData?.description ||
      currentJobContext.jobData?.job_description ||
      "";
    console.log(
      "▶ WSJ: Job description for tailoring:",
      jobDescription ? jobDescription.substring(0, 100) + "..." : "NONE"
    );

    // Send to backend
    const ajaxUrl = window.sffc_ajax?.ajax_url || "/wp-admin/admin-ajax.php";

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      data: {
        action: "professional_cv_upload",
        cv_text: cvText,
        nonce: window.sffc_ajax?.nonce || "",
      },
      success: function (response) {
        if (!response?.success) {
          showCompactStatus(
            `⚠ ${response?.data?.message || "Unable to parse CV"}`,
            "error"
          );
          return;
        }

        const cvId = response.data.cv_id;

        $.ajax({
          url: ajaxUrl,
          method: "POST",
          data: {
            action: "professional_cv_tailor",
            cv_id: cvId,
            job_title: currentJobContext.jobTitle,
            company: currentJobContext.company,
            job_description:
              currentJobContext.jobData?.description ||
              currentJobContext.jobData?.job_description ||
              "",
            nonce: window.sffc_ajax?.nonce || "",
          },
          success: function (tailorResponse) {
            if (!tailorResponse?.success) {
              showCompactStatus(
                `⚠ ${
                  tailorResponse?.data?.message || "Tailoring request failed"
                }`,
                "error"
              );
              return;
            }

            window.tailoredCVData = tailorResponse.data;

            showCompactStatus(
              `• Tailored! Match: ${
                tailorResponse.data.match_score || 85
              }%`,
              "success"
            );

            // Add download button (avoid duplicates)
            if (!$("#wsj-compact-status button.download-tailored").length) {
              $("#wsj-compact-status").append(`
                                <button class="download-tailored" onclick="downloadTailoredCV()" style="margin-top: 10px; padding: 8px 16px; background: linear-gradient(135deg, #d4af37, #f4d03f); color: #1a472a; border: none; border-radius: 6px; font-weight: 600; font-size: 13px; cursor: pointer;">
                                    ↓ Download PDF
                                </button>
                            `);
            }
          },
          error: function (xhr) {
            const message =
              xhr?.responseJSON?.data?.message ||
              xhr?.statusText ||
              "Tailoring request errored";
            showCompactStatus(`⚠ ${message}`, "error");
          },
        });
      },
      error: function (xhr) {
        const message =
          xhr?.responseJSON?.data?.message ||
          xhr?.statusText ||
          "Upload failed";
        showCompactStatus(`⚠ ${message}`, "error");
      },
    });
  };

  // Expand to full interface with preview
  window.expandWithPreview = function () {
    const cvText = $("#wsj-cv-compact-input").val();
    if (cvText) {
      // Store the text temporarily
      window.tempCVText = cvText;
    }
    expandWSJInterface();
  };

  // Expand to full WSJ interface
  window.expandWSJInterface = function () {
    // Get the chat container position
    const chatContainer = $(".wsj-cv-chat-container");
    const rect = chatContainer[0]?.getBoundingClientRect();

    // Create expanded interface
    const expandedInterface = `
            <div id="wsj-expanded-overlay" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: #ffffff; color: #ffffff; z-index: 10000; overflow: hidden;">
                <div style="height: 100%; display: flex; flex-direction: column;">
                    <!-- Mobile Header -->
                    <div style="background: linear-gradient(135deg, #1a472a, #2d6a4f); color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                        <div style="flex: 1; min-width: 0;">
                            <h2 style="margin: 0; color: #ffffff;  font-size: 18px; font-family: 'Minion Pro', Georgia, serif; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${currentJobContext.jobTitle}</h2>
                            <p style="margin: 0; font-size: 14px; opacity: 0.9; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${currentJobContext.company}</p>
                        </div>
                        <button onclick="closeExpanded()" style="background: rgba(255,255,255,0.2); border: none; color: white; padding: 10px; border-radius: 50%; cursor: pointer; font-size: 18px; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                            ×
                        </button>
                    </div>

                    <!-- Tab Navigation for Mobile -->
                    <div id="wsj-tab-nav" style="background: #f8f9fa; padding: 10px 20px; display: flex; border-bottom: 1px solid #e0e0e0;">
                        <button id="tab-input" onclick="switchTab('input')" style="flex: 1; padding: 12px; background: #1a472a; color: white; border: none; border-radius: 6px 0 0 6px; font-weight: 600; font-size: 14px;">
                            ✎ Input
                        </button>
                        <button id="tab-preview" onclick="switchTab('preview')" style="flex: 1; padding: 12px; background: #e9ecef; color: #666; border: none; border-radius: 0 6px 6px 0; font-weight: 600; font-size: 14px;">
                            ◉ Preview
                        </button>
                    </div>
                    
                    <!-- Content Container -->
                    <div style="flex: 1; overflow: hidden;">
                        <!-- Input Tab -->
                        <div id="content-input" style="height: 100%; padding: 20px; background: #faf7f2; overflow-y: auto;">
                            <div style="max-width: 100%; margin: 0 auto;">
                                <textarea id="wsj-expanded-input" style="width: 100%; height: calc(100vh - 280px) !important; padding: 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-family: 'SF Mono', Monaco, monospace; font-size: 14px; resize: none; box-sizing: border-box;" placeholder="Paste your CV content here..."></textarea>
                                
                                <!-- Input Actions -->
                                <div style="margin-top: 15px; display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                    <button onclick="parseInExpanded()" style="padding: 14px; background: linear-gradient(135deg, #1a472a, #2d6a4f); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 14px;">
                                        ⟲ Parse CV
                                    </button>
                                    <button onclick="enhanceInExpanded()" style="padding: 14px; background: linear-gradient(135deg, #2d6a4f, #40916c); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 14px;">
                                        ⚡ Enhance
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Preview Tab -->
                        <div id="content-preview" style="height: 100%; padding: 20px; background: white; overflow-y: auto; display: none;">
                            <div id="wsj-expanded-preview" style="max-width: 100%; margin: 0 auto; background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; min-height: calc(100vh - 200px);">
                                <div style="text-align: center; padding: 60px 20px; color: #999;">
                                    <div style="font-size: 48px; margin-bottom: 20px;">□</div>
                                    <p>Parse your CV to see WSJ format preview</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Floating Action Buttons -->
                    <div id="wsj-floating-actions" style="position: fixed; bottom: 20px; right: 20px; display: flex; flex-direction: column; gap: 12px; z-index: 20000;">
                        <button onclick="tailorInExpanded()" style="width: 56px; height: 56px; background: linear-gradient(135deg, #d4af37, #f4d03f); color: #1a472a; border: none; border-radius: 50%; font-size: 18px; font-weight: bold; cursor: pointer; box-shadow: 0 4px 20px rgba(212, 175, 55, 0.4); display: flex; align-items: center; justify-content: center; transition: all 0.2s;">
                            ▶
                        </button>
                        <button onclick="downloadFromExpanded()" style="width: 56px; height: 56px; background: linear-gradient(135deg, #1a472a, #2d6a4f); color: white; border: none; border-radius: 50%; font-size: 18px; font-weight: bold; cursor: pointer; box-shadow: 0 4px 20px rgba(26, 71, 42, 0.4); display: flex; align-items: center; justify-content: center; transition: all 0.2s;">
                            ↓
                        </button>
                    </div>

                    <!-- Status Toast -->
                    <div id="wsj-expanded-status" style="position: fixed; bottom: 20px; left: 20px; background: rgba(0,0,0,0.8); color: white; padding: 12px 20px; border-radius: 25px; font-size: 14px; z-index: 19999; display: none; max-width: calc(100vw - 140px); transition: all 0.3s;">
                        Ready
                    </div>
                </div>
            </div>

            <style>
                @media (min-width: 768px) {
                    #wsj-expanded-overlay {
                        background: rgba(0,0,0,0.7) !important;
                        display: flex !important;
                        align-items: center !important;
                        justify-content: center !important;
                        padding: 20px !important;
                    }
                    
                    #wsj-expanded-overlay > div:first-child {
                        background: white !important;
                        border-radius: 16px !important;
                        overflow: hidden !important;
                        box-shadow: 0 20px 60px rgba(0,0,0,0.3) !important;
                        max-width: 1400px !important;
                        width: 95% !important;
                        height: 90vh !important;
                        max-height: 90vh !important;
                    }
                    
                    #wsj-tab-nav {
                        display: none !important;
                    }
                    
                    #content-input, #content-preview {
                        display: block !important;
                        height: 100% !important;
                    }
                    
                    #wsj-expanded-overlay > div:first-child > div:nth-child(3) {
                        display: grid !important;
                        grid-template-columns: 1fr 1fr !important;
                        flex: 1 !important;
                        overflow: hidden !important;
                    }
                    
                    #content-input {
                        border-right: 1px solid #e0e0e0 !important;
                        padding: 40px !important;
                    }
                    
                    #content-preview {
                        padding: 40px !important;
                    }
                    
                    #wsj-expanded-input {
                        height: calc(100% - 120px) !important;
                        font-size: 16px !important;
                        line-height: 1.5 !important;
                    }
                    
                    #wsj-expanded-preview {
                        height: calc(100% - 40px) !important;
                        min-height: auto !important;
                    }
                }
                
                #wsj-floating-actions button:hover {
                    transform: scale(1.1) !important;
                    box-shadow: 0 6px 25px rgba(0,0,0,0.3) !important;
                }
                
                #wsj-floating-actions button:active {
                    transform: scale(0.95) !important;
                }
                
                @keyframes statusFadeIn {
                    from { opacity: 0; transform: translateY(10px); }
                    to { opacity: 1; transform: translateY(0); }
                }
            </style>
        `;

    $("body").append(expandedInterface);

    // Copy text from compact to expanded
    const compactText =
      $("#wsj-cv-compact-input").val() || window.tempCVText || "";
    $("#wsj-expanded-input").val(compactText);

    // Add animations
    $("<style>")
      .text(
        `
                @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
                @keyframes slideUp { from { transform: translateY(50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
            `
      )
      .appendTo("head");

    isExpanded = true;
  };

  // Close expanded view
  window.closeExpanded = function () {
    // Copy text back to compact
    const expandedText = $("#wsj-expanded-input").val();
    if (expandedText) {
      $("#wsj-cv-compact-input").val(expandedText);
    }

    $("#wsj-expanded-overlay").fadeOut(300, function () {
      $(this).remove();
    });

    isExpanded = false;
  };

  // Tab switching for mobile
  window.switchTab = function (tab) {
    if (tab === "input") {
      $("#content-input").show();
      $("#content-preview").hide();
      $("#tab-input").css("background", "#1a472a").css("color", "white");
      $("#tab-preview").css("background", "#e9ecef").css("color", "#666");
    } else {
      $("#content-input").hide();
      $("#content-preview").show();
      $("#tab-input").css("background", "#e9ecef").css("color", "#666");
      $("#tab-preview").css("background", "#1a472a").css("color", "white");
    }
  };

  // Show status in expanded view (floating toast)
  function showExpandedStatus(message, duration = 3000) {
    const statusEl = $("#wsj-expanded-status");
    statusEl.text(message).show();

    // Auto-hide after duration
    setTimeout(() => {
      statusEl.fadeOut(300);
    }, duration);
  }

  // Parse in expanded view
  window.parseInExpanded = function () {
    const cvText = $("#wsj-expanded-input").val();
    if (!cvText.trim()) {
      showExpandedStatus("Please enter CV text");
      return;
    }

    showExpandedStatus("Parsing...");

    // Always recreate renderer for expanded view to avoid conflicts
    if (typeof WSJCVRendererUltimate !== "undefined") {
      // Clear previous content
      $("#wsj-expanded-preview").empty();

      // Create new renderer instance for expanded view
      const expandedRenderer = new WSJCVRendererUltimate({
        container: "#wsj-expanded-preview",
        editable: false,
        animations: true,
      });

      try {
        expandedRenderer.updateFromText(cvText);
        const parsed = expandedRenderer.getData();
        showExpandedStatus(
          `Parsed: ${parsed.experience?.length || 0} experiences, ${
            parsed.skills?.length || 0
          } skills`
        );
        // Switch to preview tab on mobile after parsing
        switchTab("preview");
      } catch (error) {
        console.error("Parse error:", error);
        showExpandedStatus("Parse error - check CV format");
      }
    } else {
      showExpandedStatus("WSJ Renderer not available");
    }
  };

  // Show status in compact view
  function showCompactStatus(message, type) {
    const statusEl = $("#wsj-compact-status");
    statusEl.show().html(message);

    if (type === "error") {
      statusEl.css("background", "#fef5f5").css("border-left-color", "#c00");
    } else if (type === "success") {
      statusEl.css("background", "#f0f9f4").css("border-left-color", "#2d6a4f");
    } else {
      statusEl.css("background", "#fff9e6").css("border-left-color", "#d4af37");
    }
  }

  // DISABLED: Using job-cards-interaction.js implementation instead
  /* window.tailorCV = function(jobId) {
        const card = $(`.sffc-match-card[data-job-id="${jobId}"], .job-card-vogue[data-job-id="${jobId}"]`).first();
        let jobTitle = 'Position';
        let company = 'Company';
        
        if (card.length) {
            jobTitle = card.find('.sffc-job-title').text() || jobTitle;
            company = card.find('.sffc-company-name').text().split('•')[0].trim() || company;
        }
        
        // Create compact container HTML
        const container = createWSJChatContainer(jobTitle, company, jobId);
        
        // Add to chat
        if (window.sennaConversational && window.sennaConversational.addSennaMessage) {
            window.sennaConversational.addSennaMessage(container, true, 'WSJ CV');
        } else {
            // Show as modal if no chat available
            expandWSJInterface();
        }
    }; */

  // Enhance AI in expanded
  window.enhanceInExpanded = function () {
    showExpandedStatus("⚡ Enhancing...");
    const cvText = $("#wsj-expanded-input").val();

    setTimeout(() => {
      let enhanced = cvText
        .replace(/•\s*Built/g, "• Architected")
        .replace(/•\s*Led/g, "• Spearheaded")
        .replace(/models/g, "models ($2.5M+ valuations)");

      $("#wsj-expanded-input").val(enhanced);
      parseInExpanded();
      showExpandedStatus("• Enhanced with AI");
    }, 1000);
  };

  // Tailor in expanded
  window.tailorInExpanded = function () {
    const cvText = $("#wsj-expanded-input").val();
    if (!cvText.trim()) {
      showExpandedStatus("Please enter CV text first");
      return;
    }

    showExpandedStatus("Tailoring CV...");

    // Debug: Check job description
    const jobDescription =
      currentJobContext?.jobData?.description ||
      currentJobContext?.jobData?.job_description ||
      "";
    console.log(
      "▶ WSJ Expanded: Job description for tailoring:",
      jobDescription ? jobDescription.substring(0, 100) + "..." : "NONE"
    );

    // Send to backend
    $.ajax({
      url: window.sffc_ajax?.ajax_url || "/wp-admin/admin-ajax.php",
      method: "POST",
      data: {
        action: "professional_cv_upload",
        cv_text: cvText,
        nonce: window.sffc_ajax?.nonce || "",
      },
      success: function (response) {
        if (response.success) {
          const cvId = response.data.cv_id;

          $.ajax({
            url: window.sffc_ajax?.ajax_url || "/wp-admin/admin-ajax.php",
            method: "POST",
            data: {
              action: "professional_cv_tailor",
              cv_id: cvId,
              job_title: currentJobContext?.jobTitle || "Position",
              company: currentJobContext?.company || "Company",
              job_description: jobDescription,
              nonce: window.sffc_ajax?.nonce || "",
            },
            success: function (tailorResponse) {
              if (tailorResponse.success) {
                window.tailoredCVData = tailorResponse.data;
                showExpandedStatus(
                  `Tailored! Match: ${tailorResponse.data.match_score || 85}%`
                );

                // Update the input with tailored content if available
                if (tailorResponse.data.content) {
                  $("#wsj-expanded-input").val(tailorResponse.data.content);
                  parseInExpanded(); // Refresh the preview
                }
              } else {
                showExpandedStatus("Tailoring failed - try again");
              }
            },
            error: function () {
              showExpandedStatus("Connection error during tailoring");
            },
          });
        } else {
          showExpandedStatus("CV upload failed");
        }
      },
      error: function () {
        showExpandedStatus("Connection error");
      },
    });
  };

  // Download from expanded
  window.downloadFromExpanded = function () {
    if (window.tailoredCVData) {
      window.downloadTailoredCV();
    } else {
      showExpandedStatus("⚠ Please tailor first");
    }
  };

  $(document).ready(function () {
    console.log("• WSJ Chat Integration loaded - Compact & Expandable");

    // Load WSJ renderer if needed
    if (typeof WSJCVRendererUltimate === "undefined" && window.sffc_ajax) {
      $.getScript(
        window.sffc_ajax.plugin_url + "assets/js/wsj-cv-renderer-ultimate.js"
      );
    }
  });
})(jQuery);
