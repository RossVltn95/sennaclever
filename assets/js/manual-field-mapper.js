/**
 * Manual Field Mapper UI
 *
 * Provides a drag-and-drop interface for manually mapping CV data to form fields
 * when automatic detection fails
 *
 * @package MENA Careers
 */

(function ($) {
  "use strict";

  class ManualFieldMapper {
    constructor() {
      // Color scheme
      this.colors = {
        cream: "#FBF7F0",
        darkCream: "#F5EFE6",
        forestGreen: "#1A3028",
        darkForest: "#0F1F18",
        gold: "#2D6A4F",
        darkGold: "#1B4332",
        lightGold: "#E5D4A1",
      };

      this.parsedData = null;
      this.detectedFields = [];
      this.mappings = {};
      this.isActive = false;

      this.init();
    }

    init() {
      this.createMapperInterface();
      this.bindEvents();

      // Listen for mapping trigger
      $(document).on("autofill:manual:required", (e, data) => {
        this.show(data);
      });
    }

    createMapperInterface() {
      const html = `
                <div id="manual-field-mapper" class="field-mapper-overlay" style="display: none;">
                    <div class="mapper-container">
                        <div class="mapper-header">
                            <h2>Manual Field Mapping</h2>
                            <p>The platform couldn't be detected automatically. Please drag your information to the corresponding fields.</p>
                            <button class="mapper-close">×</button>
                        </div>
                        
                        <div class="mapper-body">
                            <div class="mapper-source">
                                <h3>Your Information</h3>
                                <div class="source-sections">
                                    <!-- Personal Info -->
                                    <div class="source-section">
                                        <h4>Personal Details</h4>
                                        <div class="draggable-items" id="personal-items">
                                            <!-- Will be populated dynamically -->
                                        </div>
                                    </div>
                                    
                                    <!-- Experience -->
                                    <div class="source-section">
                                        <h4>Experience</h4>
                                        <div class="draggable-items" id="experience-items">
                                            <!-- Will be populated dynamically -->
                                        </div>
                                    </div>
                                    
                                    <!-- Education -->
                                    <div class="source-section">
                                        <h4>Education</h4>
                                        <div class="draggable-items" id="education-items">
                                            <!-- Will be populated dynamically -->
                                        </div>
                                    </div>
                                    
                                    <!-- Skills -->
                                    <div class="source-section">
                                        <h4>Skills</h4>
                                        <div class="draggable-items" id="skills-items">
                                            <!-- Will be populated dynamically -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mapper-target">
                                <h3>Form Fields</h3>
                                <div class="target-fields">
                                    <!-- Will be populated with detected form fields -->
                                </div>
                                
                                <div class="unmapped-fields">
                                    <h4>Unmapped Fields</h4>
                                    <div class="unmapped-list">
                                        <!-- Fields that couldn't be automatically mapped -->
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mapper-footer">
                            <div class="mapper-stats">
                                <span class="mapped-count">0</span> of <span class="total-count">0</span> fields mapped
                            </div>
                            <div class="mapper-actions">
                                <button class="btn-auto-detect">Auto-Detect Fields</button>
                                <button class="btn-clear-mappings">Clear All</button>
                                <button class="btn-save-mapping">Save Mapping</button>
                                <button class="btn-apply-mapping">Apply & Fill</button>
                            </div>
                        </div>
                        
                        <div class="mapper-help">
                            <div class="help-icon">?</div>
                            <div class="help-content">
                                <h4>How to use:</h4>
                                <ol>
                                    <li>Drag items from "Your Information" on the left</li>
                                    <li>Drop them onto the corresponding form fields on the right</li>
                                    <li>Green highlights indicate successful mapping</li>
                                    <li>Click "Apply & Fill" when done</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
            `;

      $("body").append(html);

      // Add styles
      this.addStyles();
    }

    addStyles() {
      const styles = `
                <style id="manual-mapper-styles">
                    .field-mapper-overlay {
                        position: fixed;
                        top: 0;
                        left: 0;
                        right: 0;
                        bottom: 0;
                        background: rgba(15, 31, 24, 0.95);
                        z-index: 999999;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    }
                    
                    .mapper-container {
                        width: 90%;
                        max-width: 1200px;
                        height: 90%;
                        background: ${this.colors.cream};
                        border-radius: 12px;
                        display: flex;
                        flex-direction: column;
                        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                    }
                    
                    .mapper-header {
                        padding: 20px 30px;
                        border-bottom: 2px solid ${this.colors.lightGold};
                        background: ${this.colors.darkCream};
                        border-radius: 12px 12px 0 0;
                        position: relative;
                    }
                    
                    .mapper-header h2 {
                        color: ${this.colors.forestGreen};
                        margin: 0 0 5px 0;
                    }
                    
                    .mapper-header p {
                        color: ${this.colors.darkForest};
                        margin: 0;
                        opacity: 0.8;
                    }
                    
                    .mapper-close {
                        position: absolute;
                        right: 20px;
                        top: 20px;
                        width: 30px;
                        height: 30px;
                        border: none;
                        background: ${this.colors.gold};
                        color: white;
                        border-radius: 50%;
                        font-size: 20px;
                        cursor: pointer;
                        transition: all 0.3s;
                    }
                    
                    .mapper-close:hover {
                        background: ${this.colors.darkGold};
                        transform: rotate(90deg);
                    }
                    
                    .mapper-body {
                        flex: 1;
                        display: flex;
                        overflow: hidden;
                        padding: 20px;
                        gap: 20px;
                    }
                    
                    .mapper-source,
                    .mapper-target {
                        flex: 1;
                        background: white;
                        border-radius: 8px;
                        padding: 15px;
                        overflow-y: auto;
                        border: 1px solid ${this.colors.lightGold};
                    }
                    
                    .mapper-source h3,
                    .mapper-target h3 {
                        color: ${this.colors.forestGreen};
                        margin: 0 0 15px 0;
                        padding-bottom: 10px;
                        border-bottom: 1px solid ${this.colors.lightGold};
                    }
                    
                    .source-section {
                        margin-bottom: 20px;
                    }
                    
                    .source-section h4 {
                        color: ${this.colors.darkGold};
                        margin: 0 0 10px 0;
                        font-size: 14px;
                        text-transform: uppercase;
                        letter-spacing: 1px;
                    }
                    
                    .draggable-item {
                        background: ${this.colors.cream};
                        border: 1px solid ${this.colors.lightGold};
                        padding: 8px 12px;
                        margin: 5px 0;
                        border-radius: 4px;
                        cursor: move;
                        transition: all 0.3s;
                        font-size: 14px;
                    }
                    
                    .draggable-item:hover {
                        background: ${this.colors.lightGold};
                        transform: translateX(5px);
                    }
                    
                    .draggable-item.dragging {
                        opacity: 0.5;
                        transform: scale(0.95);
                    }
                    
                    .draggable-item .item-label {
                        font-weight: bold;
                        color: ${this.colors.darkGold};
                        font-size: 11px;
                        text-transform: uppercase;
                        display: block;
                        margin-bottom: 3px;
                    }
                    
                    .draggable-item .item-value {
                        color: ${this.colors.forestGreen};
                        white-space: nowrap;
                        overflow: hidden;
                        text-overflow: ellipsis;
                    }
                    
                    .target-field {
                        background: ${this.colors.cream};
                        border: 2px dashed ${this.colors.lightGold};
                        padding: 12px;
                        margin: 10px 0;
                        border-radius: 6px;
                        min-height: 50px;
                        transition: all 0.3s;
                    }
                    
                    .target-field.drag-over {
                        background: ${this.colors.lightGold};
                        border-color: ${this.colors.gold};
                        transform: scale(1.02);
                    }
                    
                    .target-field.has-mapping {
                        background: rgba(201, 169, 97, 0.1);
                        border-color: ${this.colors.gold};
                        border-style: solid;
                    }
                    
                    .target-field .field-label {
                        font-weight: bold;
                        color: ${this.colors.forestGreen};
                        margin-bottom: 5px;
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                    }
                    
                    .target-field .field-type {
                        font-size: 11px;
                        background: ${this.colors.gold};
                        color: white;
                        padding: 2px 6px;
                        border-radius: 3px;
                    }
                    
                    .target-field .mapped-value {
                        background: white;
                        padding: 8px;
                        border-radius: 4px;
                        margin-top: 8px;
                        border: 1px solid ${this.colors.gold};
                        position: relative;
                    }
                    
                    .target-field .remove-mapping {
                        position: absolute;
                        right: 5px;
                        top: 5px;
                        width: 20px;
                        height: 20px;
                        background: ${this.colors.darkGold};
                        color: white;
                        border: none;
                        border-radius: 50%;
                        font-size: 12px;
                        cursor: pointer;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    }
                    
                    .mapper-footer {
                        padding: 15px 30px;
                        border-top: 2px solid ${this.colors.lightGold};
                        background: ${this.colors.darkCream};
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        border-radius: 0 0 12px 12px;
                    }
                    
                    .mapper-stats {
                        color: ${this.colors.forestGreen};
                        font-weight: bold;
                    }
                    
                    .mapper-stats .mapped-count {
                        color: ${this.colors.gold};
                        font-size: 18px;
                    }
                    
                    .mapper-actions button {
                        padding: 10px 20px;
                        margin-left: 10px;
                        border: none;
                        border-radius: 6px;
                        font-weight: bold;
                        cursor: pointer;
                        transition: all 0.3s;
                    }
                    
                    .btn-auto-detect {
                        background: white;
                        color: ${this.colors.forestGreen};
                        border: 2px solid ${this.colors.forestGreen};
                    }
                    
                    .btn-clear-mappings {
                        background: white;
                        color: ${this.colors.darkGold};
                        border: 2px solid ${this.colors.darkGold};
                    }
                    
                    .btn-save-mapping {
                        background: ${this.colors.lightGold};
                        color: ${this.colors.forestGreen};
                    }
                    
                    .btn-apply-mapping {
                        background: ${this.colors.gold};
                        color: white;
                    }
                    
                    .btn-apply-mapping:hover {
                        background: ${this.colors.darkGold};
                        transform: translateY(-2px);
                        box-shadow: 0 5px 15px rgba(201, 169, 97, 0.3);
                    }
                    
                    .mapper-help {
                        position: absolute;
                        bottom: 20px;
                        right: 20px;
                    }
                    
                    .help-icon {
                        width: 30px;
                        height: 30px;
                        background: ${this.colors.gold};
                        color: white;
                        border-radius: 50%;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        cursor: pointer;
                        font-weight: bold;
                    }
                    
                    .help-content {
                        position: absolute;
                        bottom: 40px;
                        right: 0;
                        background: white;
                        border: 2px solid ${this.colors.gold};
                        border-radius: 8px;
                        padding: 15px;
                        width: 250px;
                        display: none;
                        box-shadow: 0 5px 20px rgba(0,0,0,0.2);
                    }
                    
                    .help-icon:hover + .help-content,
                    .help-content:hover {
                        display: block;
                    }
                    
                    .help-content h4 {
                        color: ${this.colors.forestGreen};
                        margin: 0 0 10px 0;
                    }
                    
                    .help-content ol {
                        margin: 0;
                        padding-left: 20px;
                        color: ${this.colors.darkForest};
                        font-size: 13px;
                    }
                </style>
            `;

      if (!$("#manual-mapper-styles").length) {
        $("head").append(styles);
      }
    }

    bindEvents() {
      // Close button
      $(document).on("click", ".mapper-close", () => this.hide());

      // Escape key
      $(document).on("keydown", (e) => {
        if (e.key === "Escape" && this.isActive) {
          this.hide();
        }
      });

      // Action buttons
      $(document).on("click", ".btn-auto-detect", () =>
        this.autoDetectFields()
      );
      $(document).on("click", ".btn-clear-mappings", () =>
        this.clearAllMappings()
      );
      $(document).on("click", ".btn-save-mapping", () =>
        this.saveMappingTemplate()
      );
      $(document).on("click", ".btn-apply-mapping", () => this.applyMappings());

      // Remove mapping
      $(document).on("click", ".remove-mapping", (e) => {
        const fieldId = $(e.target).closest(".target-field").data("field-id");
        this.removeMapping(fieldId);
      });
    }

    show(data) {
      this.parsedData = data.parsedData || {};
      this.detectedFields = data.detectedFields || [];
      this.isActive = true;

      // Populate source items
      this.populateSourceItems();

      // Populate target fields
      this.populateTargetFields();

      // Initialize drag and drop
      this.initDragAndDrop();

      // Show overlay
      $("#manual-field-mapper").fadeIn(300);

      // Update stats
      this.updateStats();
    }

    hide() {
      this.isActive = false;
      $("#manual-field-mapper").fadeOut(300);
    }

    populateSourceItems() {
      // Personal items
      const personalItems = $("#personal-items");
      personalItems.empty();

      if (this.parsedData.personal) {
        Object.entries(this.parsedData.personal).forEach(([key, value]) => {
          if (value) {
            personalItems.append(
              this.createDraggableItem(key, value, "personal")
            );
          }
        });
      }

      // Experience items
      const experienceItems = $("#experience-items");
      experienceItems.empty();

      if (this.parsedData.experience && this.parsedData.experience.length > 0) {
        this.parsedData.experience.forEach((exp, index) => {
          if (exp.company) {
            experienceItems.append(
              this.createDraggableItem(
                `experience_${index}_company`,
                exp.company,
                "experience"
              )
            );
          }
          if (exp.title) {
            experienceItems.append(
              this.createDraggableItem(
                `experience_${index}_title`,
                exp.title,
                "experience"
              )
            );
          }
          if (exp.description) {
            experienceItems.append(
              this.createDraggableItem(
                `experience_${index}_description`,
                exp.description.substring(0, 100) + "...",
                "experience"
              )
            );
          }
        });
      }

      // Education items
      const educationItems = $("#education-items");
      educationItems.empty();

      if (this.parsedData.education && this.parsedData.education.length > 0) {
        this.parsedData.education.forEach((edu, index) => {
          if (edu.degree) {
            educationItems.append(
              this.createDraggableItem(
                `education_${index}_degree`,
                edu.degree,
                "education"
              )
            );
          }
          if (edu.institution) {
            educationItems.append(
              this.createDraggableItem(
                `education_${index}_institution`,
                edu.institution,
                "education"
              )
            );
          }
        });
      }

      // Skills items
      const skillsItems = $("#skills-items");
      skillsItems.empty();

      if (this.parsedData.skills) {
        if (
          this.parsedData.skills.technical &&
          this.parsedData.skills.technical.length > 0
        ) {
          skillsItems.append(
            this.createDraggableItem(
              "technical_skills",
              this.parsedData.skills.technical.join(", "),
              "skills"
            )
          );
        }
        if (
          this.parsedData.skills.languages &&
          this.parsedData.skills.languages.length > 0
        ) {
          skillsItems.append(
            this.createDraggableItem(
              "languages",
              this.parsedData.skills.languages.join(", "),
              "skills"
            )
          );
        }
      }
    }

    createDraggableItem(key, value, category) {
      const label = this.formatLabel(key);
      return `
                <div class="draggable-item" draggable="true" data-key="${key}" data-value="${value}" data-category="${category}">
                    <span class="item-label">${label}</span>
                    <span class="item-value">${value}</span>
                </div>
            `;
    }

    formatLabel(key) {
      return key
        .replace(/_/g, " ")
        .replace(/([A-Z])/g, " $1")
        .replace(/^./, (str) => str.toUpperCase())
        .trim();
    }

    populateTargetFields() {
      const targetFields = $(".target-fields");
      targetFields.empty();

      // Scan the page for form fields
      this.detectedFields = this.scanPageForFields();

      this.detectedFields.forEach((field, index) => {
        const fieldHtml = `
                    <div class="target-field" data-field-id="${
                      field.id || index
                    }" data-selector="${field.selector}">
                        <div class="field-label">
                            <span>${
                              field.label || field.name || "Unknown Field"
                            }</span>
                            <span class="field-type">${field.type}</span>
                        </div>
                        <div class="drop-zone">
                            <span class="drop-hint">Drop item here</span>
                        </div>
                    </div>
                `;
        targetFields.append(fieldHtml);
      });
    }

    scanPageForFields() {
      const fields = [];

      // Common input fields
      $(
        'input[type="text"], input[type="email"], input[type="tel"], textarea, select'
      ).each((index, element) => {
        const $el = $(element);

        // Skip hidden fields
        if ($el.is(":hidden") || $el.closest("#manual-field-mapper").length) {
          return;
        }

        const field = {
          id: $el.attr("id") || `field_${index}`,
          name: $el.attr("name") || "",
          type: $el.attr("type") || $el.prop("tagName").toLowerCase(),
          label: this.getFieldLabel($el),
          selector: this.getUniqueSelector($el),
          element: element,
        };

        fields.push(field);
      });

      return fields;
    }

    getFieldLabel($element) {
      // Try to find associated label
      const id = $element.attr("id");
      if (id) {
        const $label = $(`label[for="${id}"]`);
        if ($label.length) {
          return $label.text().trim();
        }
      }

      // Check for parent label
      const $parentLabel = $element.closest("label");
      if ($parentLabel.length) {
        return $parentLabel.text().trim();
      }

      // Check for placeholder
      const placeholder = $element.attr("placeholder");
      if (placeholder) {
        return placeholder;
      }

      // Check for nearby text
      const $prev = $element.prev();
      if ($prev.length && $prev.text().trim()) {
        return $prev.text().trim();
      }

      // Use name attribute as fallback
      return this.formatLabel($element.attr("name") || "");
    }

    getUniqueSelector($element) {
      // Try ID first
      if ($element.attr("id")) {
        return "#" + $element.attr("id");
      }

      // Try name attribute
      if ($element.attr("name")) {
        return `[name="${$element.attr("name")}"]`;
      }

      // Build a path selector
      const path = [];
      let current = $element[0];

      while (current && current.tagName) {
        let selector = current.tagName.toLowerCase();

        if (current.className) {
          selector += "." + current.className.split(" ").join(".");
        }

        path.unshift(selector);
        current = current.parentElement;

        // Stop at body or form
        if (
          current &&
          (current.tagName === "BODY" || current.tagName === "FORM")
        ) {
          break;
        }
      }

      return path.join(" > ");
    }

    initDragAndDrop() {
      const self = this;

      // Make items draggable
      $(".draggable-item").on("dragstart", function (e) {
        $(this).addClass("dragging");
        e.originalEvent.dataTransfer.effectAllowed = "copy";
        e.originalEvent.dataTransfer.setData(
          "text/plain",
          JSON.stringify({
            key: $(this).data("key"),
            value: $(this).data("value"),
            category: $(this).data("category"),
          })
        );
      });

      $(".draggable-item").on("dragend", function () {
        $(this).removeClass("dragging");
      });

      // Make target fields droppable
      $(".target-field").on("dragover", function (e) {
        e.preventDefault();
        $(this).addClass("drag-over");
      });

      $(".target-field").on("dragleave", function () {
        $(this).removeClass("drag-over");
      });

      $(".target-field").on("drop", function (e) {
        e.preventDefault();
        $(this).removeClass("drag-over");

        const data = JSON.parse(
          e.originalEvent.dataTransfer.getData("text/plain")
        );
        const fieldId = $(this).data("field-id");

        self.addMapping(fieldId, data);
      });
    }

    addMapping(fieldId, data) {
      // Store mapping
      this.mappings[fieldId] = data;

      // Update UI
      const $field = $(`.target-field[data-field-id="${fieldId}"]`);
      $field.addClass("has-mapping");

      const mappedHtml = `
                <div class="mapped-value">
                    <strong>${this.formatLabel(data.key)}:</strong> ${
        data.value
      }
                    <button class="remove-mapping">×</button>
                </div>
            `;

      $field.find(".drop-zone").html(mappedHtml);

      // Update stats
      this.updateStats();
    }

    removeMapping(fieldId) {
      delete this.mappings[fieldId];

      const $field = $(`.target-field[data-field-id="${fieldId}"]`);
      $field.removeClass("has-mapping");
      $field
        .find(".drop-zone")
        .html('<span class="drop-hint">Drop item here</span>');

      this.updateStats();
    }

    clearAllMappings() {
      this.mappings = {};
      $(".target-field").removeClass("has-mapping");
      $(".drop-zone").html('<span class="drop-hint">Drop item here</span>');
      this.updateStats();
    }

    updateStats() {
      const mapped = Object.keys(this.mappings).length;
      const total = this.detectedFields.length;

      $(".mapped-count").text(mapped);
      $(".total-count").text(total);
    }

    autoDetectFields() {
      // Try to automatically map fields based on field names and labels
      this.detectedFields.forEach((field) => {
        const fieldLabel = (field.label || field.name || "").toLowerCase();

        // Try to find matching data
        let matchedData = null;

        // Check personal data
        if (this.parsedData.personal) {
          Object.entries(this.parsedData.personal).forEach(([key, value]) => {
            if (value && !matchedData) {
              const keyLower = key.toLowerCase();
              if (
                fieldLabel.includes(keyLower) ||
                keyLower.includes(fieldLabel)
              ) {
                matchedData = { key, value, category: "personal" };
              }
            }
          });
        }

        // If found, add mapping
        if (matchedData) {
          this.addMapping(field.id, matchedData);
        }
      });
    }

    saveMappingTemplate() {
      // Save the current mapping template for this domain
      const domain = window.location.hostname;
      const template = {
        domain: domain,
        mappings: this.mappings,
        fields: this.detectedFields,
        timestamp: new Date().toISOString(),
      };

      // Save to localStorage
      localStorage.setItem(`sffc_mapping_${domain}`, JSON.stringify(template));

      // Send to server for sharing with other users
      $.ajax({
        url: sffc_ajax.ajax_url,
        type: "POST",
        data: {
          action: "sffc_save_field_mapping",
          domain: domain,
          template: JSON.stringify(template),
          nonce: sffc_ajax.nonce,
        },
        success: () => {
          alert("Mapping template saved successfully!");
        },
      });
    }

    applyMappings() {
      // Apply all mappings to the form
      Object.entries(this.mappings).forEach(([fieldId, data]) => {
        const field = this.detectedFields.find((f) => f.id === fieldId);
        if (field && field.selector) {
          const $element = $(field.selector);
          if ($element.length) {
            // Set the value
            $element.val(data.value);

            // Trigger change events
            $element.trigger("change");
            $element.trigger("input");
            $element.trigger("blur");

            // Add success indicator
            $element.css("border", `2px solid ${this.colors.gold}`);
            setTimeout(() => {
              $element.css("border", "");
            }, 3000);
          }
        }
      });

      // Close mapper
      this.hide();

      // Show success message
      this.showSuccessMessage();
    }

    showSuccessMessage() {
      const successHtml = `
                <div class="autofill-success-toast" style="
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: ${this.colors.gold};
                    color: white;
                    padding: 15px 25px;
                    border-radius: 8px;
                    box-shadow: 0 5px 20px rgba(201, 169, 97, 0.4);
                    z-index: 1000000;
                    font-weight: bold;
                ">
                    ✓ Form fields filled successfully!
                </div>
            `;

      $("body").append(successHtml);

      setTimeout(() => {
        $(".autofill-success-toast").fadeOut(() => {
          $(".autofill-success-toast").remove();
        });
      }, 3000);
    }
  }

  // Initialize
  $(document).ready(() => {
    window.manualFieldMapper = new ManualFieldMapper();
  });
})(jQuery);
