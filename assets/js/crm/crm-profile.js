/**
 * CRM Profile Section Manager
 * Handles all profile editing functionality with modal-based UI
 *
 * @package SennaCareers
 * @since 8.0.0
 */

(function ($) {
    'use strict';

    const CRMProfile = {
        config: {},
        strings: {
            alertsOn: 'Alerts On',
            alertsOff: 'Alerts Off',
            alertsHelper: 'We will email you the moment we post a matching role.',
            alertsOffHelper: 'Set filters to be notified the moment a matching role drops.',
            alertsSaved: 'Alert preferences saved',
            alertsError: 'Unable to save alerts right now.',
            typesLabel: 'Types:',
            sectorsLabel: 'Sectors:',
            locationsLabel: 'Locations:',
            workLabel: 'Work style:',
            groupsLabel: 'Post groups:'
        },
        state: {
            isSaving: false,
            currentModal: null,
            pendingChanges: {},
            modalJustOpened: false,
        },
        universityOptions: [
            'University of Oxford',
            'University of Cambridge',
            'Imperial College London',
            'London School of Economics (LSE)',
            'University College London (UCL)',
            'King\'s College London',
            'Queen Mary University of London',
            'City, University of London',
            'Cass Business School (Bayes Business School)',
            'Brunel University London',
            'Royal Holloway, University of London',
            'SOAS University of London',
            'London Metropolitan University',
            'Middlesex University London',
            'Birkbeck, University of London',
            'University of Westminster',
            'London South Bank University',
            'Goldsmiths, University of London',
            'University of Greenwich',
            'University of East London',
            'University of St Andrews',
            'Durham University',
            'University of Warwick',
            'Warwick Business School',
            'London Business School',
            'University of Edinburgh',
            'University of Manchester',
            'University of Bristol',
            'University of Glasgow',
            'University of Bath',
            'University of Leeds',
            'University of Nottingham',
            'Trinity College Dublin',
            'University College Dublin (UCD) Smurfit School',
            'University of Zurich',
            'ETH Zurich',
            'University of Basel',
            'University of St Gallen',
            'Bocconi University',
            'INSEAD',
            'HEC Paris',
            'ESSEC Business School',
            'ESCP Business School',
            'Grenoble Ecole de Management',
            'NEOMA Business School',
            'Audencia Business School',
            'ESADE Business School',
            'IE Business School',
            'IESE Business School',
            'Nova School of Business and Economics',
            'Rotterdam School of Management (Erasmus University)',
            'Tilburg University',
            'KU Leuven',
            'Ghent University',
            'Stockholm School of Economics',
            'Copenhagen Business School',
            'Norwegian School of Economics (NHH)',
            'BI Norwegian Business School',
            'Aalto University School of Business',
            'Vienna University of Economics and Business (WU Vienna)',
            'Frankfurt School of Finance & Management',
            'WHU – Otto Beisheim School of Management',
            'University of Cologne',
            'Mannheim Business School',
            'Ludwig Maximilian University of Munich (LMU)',
            'University of Zurich',
            'University of Geneva'
        ],

        /**
         * Initialize the profile manager
         */
        init() {
            console.log('CRMProfile: Initializing...');
            this.config = window.sffcCRMLinkedIn || {};
            this.strings = $.extend({}, this.strings, this.config.alertStrings || {});

            if (!this.cacheDom()) {
                console.error('CRMProfile: Failed to cache DOM elements');
                return;
            }

            this.bindEvents();
            this.loadPreferences();
            console.log('CRMProfile: Initialized successfully');
        },

        /**
         * Cache DOM elements
         */
        cacheDom() {
            this.$app = $('.sffc-crm-linkedin');
            this.$profilePanel = this.$app.find('[data-panel="profile"]');

            // Debug: Check if app element exists
            if (!this.$app.length) {
                console.error('CRMProfile: .sffc-crm-linkedin element not found!');
                return false;
            }

            return true;
        },

        /**
         * Bind event listeners
         */
        bindEvents() {
            // Tag add buttons - bind to app container
            this.$app.on('click', '.sffc-crm-tag-add', this.handleTagAdd.bind(this));

            // Tag click to edit - bind to app container
            this.$app.on('click', '.sffc-crm-tag', this.handleTagClick.bind(this));

            // Tag remove buttons - bind to app container (stop propagation handled in handler)
            this.$app.on('click', '.sffc-crm-tag-remove', this.handleTagRemove.bind(this));

            // Modal events - MUST bind to document because modals are appended to body
            $(document).on('click', '.sffc-profile-modal-close', this.closeModal.bind(this));
            $(document).on('click', '.sffc-profile-modal-cancel', this.closeModal.bind(this));
            $(document).on('click', '.sffc-profile-modal-save', this.handleModalSave.bind(this));

            // Inline edit buttons - bind to app container
            this.$app.on('click', '.sffc-crm-field-edit-btn', this.handleInlineEdit.bind(this));

            // Open to button
            this.$app.on('click', '.sffc-open-to-btn, .sffc-open-to-edit', this.handleOpenToClick.bind(this));

            // Expert request button - Handled by crm-linkedin.js to show live expert panel
            // this.$app.on('click', '.sffc-expert-btn', this.handleExpertRequestClick.bind(this));

            // Profile meta field edit/add buttons
            this.$app.on('click', '.sffc-crm-profile-meta-edit, .sffc-crm-profile-meta-add', this.handleMetaFieldEdit.bind(this));

            // Alerts
            this.$app.on('click', '[data-alerts-toggle]', this.handleAlertsToggle.bind(this));
            this.$app.on('click', '[data-alerts-cancel]', this.handleAlertsCancel.bind(this));
            this.$app.on('submit', '[data-alerts-form]', this.handleAlertsSubmit.bind(this));

            // Click outside modal to close
            $(document).on('click', '.sffc-profile-modal-overlay', (e) => {
                if (this.state.modalJustOpened) {
                    return;
                }
                if ($(e.target).hasClass('sffc-profile-modal-overlay')) {
                    this.closeModal();
                }
            });

            // ESC key to close modal
            $(document).on('keydown', (e) => {
                if (e.key === 'Escape' && this.state.currentModal) {
                    this.closeModal();
                }
            });
        },

        /**
         * Load job preferences from server
         */
        loadPreferences() {
            if (!this.config.ajaxUrl) return;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_job_preferences',
                    nonce: this.config.nonce
                },
                success: (response) => {
                    if (response && response.success && response.data) {
                        this.state.preferences = response.data;
                    }
                }
            });
        },

        /**
         * Handle tag add button click
         */
        handleTagAdd(e) {
            e.preventDefault();
            const $btn = $(e.currentTarget);
            const $container = $btn.closest('.sffc-crm-tags');
            const fieldName = $container.data('field-name');
            const options = $btn.data('options');

            if (options && typeof options === 'object') {
                // Show selection modal for predefined options
                this.showSelectionModal(fieldName, options, $container);
            } else {
                // Show text input modal for free-form entry
                this.showTextInputModal(fieldName, $container);
            }
        },

        /**
         * Handle tag click to edit
         */
        handleTagClick(e) {
            // Don't trigger if clicking the remove button
            if ($(e.target).hasClass('sffc-crm-tag-remove')) {
                return;
            }

            e.preventDefault();
            e.stopPropagation();

            const $tag = $(e.currentTarget);
            const $container = $tag.closest('.sffc-crm-tags');
            const fieldName = $container.data('field-name');
            const $addBtn = $container.find('.sffc-crm-tag-add');
            const options = $addBtn.data('options');

            // Get current tag value and label
            const currentValue = $tag.data('value') || $tag.clone().children().remove().end().text().trim();
            const currentLabel = $tag.clone().children().remove().end().text().trim();

            if (options && typeof options === 'object') {
                // For select fields, show modal to change to a different option
                this.showEditSelectionModal(fieldName, options, $container, $tag, currentValue, currentLabel);
            } else {
                // For text fields, show text input modal with current value
                this.showEditTextModal(fieldName, $container, $tag, currentValue);
            }
        },

        /**
         * Handle tag remove button click
         */
        handleTagRemove(e) {
            e.preventDefault();
            e.stopPropagation(); // Prevent tag click event from firing
            const $tag = $(e.currentTarget).closest('.sffc-crm-tag');
            const $container = $tag.closest('.sffc-crm-tags');
            const fieldName = $container.data('field-name');

            // Animate removal
            $tag.fadeOut(200, () => {
                $tag.remove();
                // Auto-save changes
                this.saveFieldData(fieldName, $container);
            });
        },

        handleAlertsToggle(e) {
            e.preventDefault();
            const $section = $(e.currentTarget).closest('.sffc-crm-profile-section');
            const $form = $section.find('[data-alerts-form]');
            if (!$form.length) {
                return;
            }
            if ($form.attr('hidden') !== undefined) {
                $form.removeAttr('hidden');
            } else {
                $form.attr('hidden', 'hidden');
            }
        },

        handleAlertsCancel(e) {
            e.preventDefault();
            const $form = $(e.currentTarget).closest('[data-alerts-form]');
            $form.attr('hidden', 'hidden');
            this.showAlertsFeedback($form, '');
        },

        handleAlertsSubmit(e) {
            e.preventDefault();
            const $form = $(e.currentTarget);
            if ($form.data('alertsSaving')) {
                return;
            }

            if (!this.config.ajaxUrl) {
                this.showAlertsFeedback($form, this.strings.alertsError, true);
                return;
            }

            const payload = {
                action: 'sffc_crm_save_alert_preferences',
                nonce: this.config.nonce,
                alerts_enabled: $form.find('[name="alerts_enabled"]').is(':checked') ? 1 : 0,
                alert_sectors: $form.find('input[name="alert_sectors[]"]:checked').map((_, el) => el.value).get(),
                alert_types: $form.find('input[name="alert_types[]"]:checked').map((_, el) => el.value).get(),
                alert_work_modes: $form.find('input[name="alert_work_modes[]"]:checked').map((_, el) => el.value).get(),
                alert_groups: $form.find('input[name="alert_groups[]"]:checked').map((_, el) => el.value).get(),
                alert_locations: $form.find('[name="alert_locations"]').val()
            };

            this.setAlertsSaving($form, true);

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: payload
            }).done((response) => {
                if (response && response.success && response.data && response.data.preferences) {
                    const $section = $form.closest('.sffc-crm-profile-section');
                    this.updateAlertsSummary(response.data.preferences, $section);
                    this.showAlertsFeedback($form, response.data.message || this.strings.alertsSaved, false);
                    $form.attr('hidden', 'hidden');
                } else {
                    const fallback = (response && response.data && response.data.message) ? response.data.message : this.strings.alertsError;
                    this.showAlertsFeedback($form, fallback, true);
                }
            }).fail(() => {
                this.showAlertsFeedback($form, this.strings.alertsError, true);
            }).always(() => {
                this.setAlertsSaving($form, false);
            });
        },

        /**
         * Show text input modal for adding new tag
         */
        showTextInputModal(fieldName, $container) {
            const fieldLabel = $container.closest('.sffc-crm-profile-field').find('label').first().text();

            const isUniversityField = fieldName === 'university';
            const datalistId = 'sffc-university-datalist';
            const universityDatalist = isUniversityField
                ? `<datalist id="${datalistId}">${this.universityOptions.map((name) => `<option value="${this.escapeHtml(name)}"></option>`).join('')}</datalist>`
                : '';

            const inputAttributes = isUniversityField ? `list="${datalistId}" autocomplete="off"` : '';

            const modal = `
                <div class="sffc-profile-modal-overlay">
                    <div class="sffc-profile-modal">
                        <div class="sffc-profile-modal-header">
                            <h3>Add ${this.escapeHtml(fieldLabel)}</h3>
                            <button type="button" class="sffc-profile-modal-close" aria-label="Close">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="18" y1="6" x2="6" y2="18"/>
                                    <line x1="6" y1="6" x2="18" y2="18"/>
                                </svg>
                            </button>
                        </div>
                        <div class="sffc-profile-modal-body">
                            <div class="sffc-profile-modal-field">
                                <label for="sffc-modal-input">Enter value</label>
                                <input
                                    type="text"
                                    id="sffc-modal-input"
                                    class="sffc-profile-modal-input"
                                    placeholder="Type here..."
                                    maxlength="100"
                                />
                            </div>
                        </div>
                        <div class="sffc-profile-modal-footer">
                            <button type="button" class="sffc-crm-btn sffc-crm-btn-secondary sffc-profile-modal-cancel">
                                Cancel
                            </button>
                            <button type="button" class="sffc-crm-btn sffc-crm-btn-primary sffc-profile-modal-save" data-field="${fieldName}">
                                Add
                            </button>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modal);
            this.state.currentModal = $('.sffc-profile-modal-overlay').last();
            this.ensureModalVisible();
            this.markModalJustOpened();
            this.state.currentContainer = $container;

            // Focus input
            setTimeout(() => {
                $('#sffc-modal-input').focus();
            }, 100);

            // Enter key to save
            $('#sffc-modal-input').on('keypress', (e) => {
                if (e.which === 13) {
                    e.preventDefault();
                    $('.sffc-profile-modal-save').click();
                }
            });
        },

        /**
         * Show text input modal for editing existing tag
         */
        showEditTextModal(fieldName, $container, $tag, currentValue) {
            const fieldLabel = $container.closest('.sffc-crm-profile-field').find('label').first().text();

            const modal = `
                <div class="sffc-profile-modal-overlay">
                    <div class="sffc-profile-modal">
                        <div class="sffc-profile-modal-header">
                            <h3>Edit ${this.escapeHtml(fieldLabel)}</h3>
                            <button type="button" class="sffc-profile-modal-close" aria-label="Close">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="18" y1="6" x2="6" y2="18"/>
                                    <line x1="6" y1="6" x2="18" y2="18"/>
                                </svg>
                            </button>
                        </div>
                        <div class="sffc-profile-modal-body">
                            <div class="sffc-profile-modal-field">
                                <label for="sffc-modal-input-edit">Enter value</label>
                                <input
                                    type="text"
                                    id="sffc-modal-input-edit"
                                    class="sffc-profile-modal-input"
                                    placeholder="Type here..."
                                    value="${this.escapeHtml(currentValue)}"
                                    maxlength="100"
                                />
                            </div>
                        </div>
                        <div class="sffc-profile-modal-footer">
                            <button type="button" class="sffc-crm-btn sffc-crm-btn-secondary sffc-profile-modal-cancel">
                                Cancel
                            </button>
                            <button type="button" class="sffc-crm-btn sffc-crm-btn-primary sffc-profile-modal-save-edit" data-field="${fieldName}">
                                Save
                            </button>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modal);
            this.state.currentModal = $('.sffc-profile-modal-overlay');
            this.state.currentContainer = $container;
            this.state.currentTag = $tag;

            // Focus input and select all
            setTimeout(() => {
                $('#sffc-modal-input-edit').focus().select();
            }, 100);

            // Enter key to save
            $('#sffc-modal-input-edit').on('keypress', (e) => {
                if (e.which === 13) {
                    e.preventDefault();
                    $('.sffc-profile-modal-save-edit').click();
                }
            });

            // Bind save button
            $('.sffc-profile-modal-save-edit').on('click', () => {
                this.saveEditedTag();
            });
        },

        /**
         * Show selection modal for editing existing tag (predefined options)
         */
        showEditSelectionModal(fieldName, options, $container, $tag, currentValue, currentLabel) {
            const fieldLabel = $container.closest('.sffc-crm-profile-field').find('label').first().text();

            // Get currently selected values (excluding the one being edited)
            const currentValues = [];
            $container.find('.sffc-crm-tag').each(function() {
                if ($(this)[0] !== $tag[0]) { // Skip the tag being edited
                    const value = $(this).data('value') || $(this).clone().children().remove().end().text().trim();
                    currentValues.push(value);
                }
            });

            // Build options HTML
            let optionsHtml = '';
            for (const [value, label] of Object.entries(options)) {
                const isCurrentValue = value === currentValue;
                const isSelected = currentValues.includes(value);

                // Show current value as checked, and available options
                if (isCurrentValue || !isSelected) {
                    optionsHtml += `
                        <label class="sffc-profile-modal-option">
                            <input type="radio" name="edit_option" value="${this.escapeHtml(value)}" ${isCurrentValue ? 'checked' : ''} />
                            <span>${this.escapeHtml(label)}</span>
                        </label>
                    `;
                }
            }

            if (!optionsHtml) {
                this.showToast('No other options available', 'info');
                return;
            }

            const modal = `
                <div class="sffc-profile-modal-overlay">
                    <div class="sffc-profile-modal">
                        <div class="sffc-profile-modal-header">
                            <h3>Edit ${this.escapeHtml(fieldLabel)}</h3>
                            <button type="button" class="sffc-profile-modal-close" aria-label="Close">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="18" y1="6" x2="6" y2="18"/>
                                    <line x1="6" y1="6" x2="18" y2="18"/>
                                </svg>
                            </button>
                        </div>
                        <div class="sffc-profile-modal-body">
                            <div class="sffc-profile-modal-options">
                                ${optionsHtml}
                            </div>
                        </div>
                        <div class="sffc-profile-modal-footer">
                            <button type="button" class="sffc-crm-btn sffc-crm-btn-secondary sffc-profile-modal-cancel">
                                Cancel
                            </button>
                            <button type="button" class="sffc-crm-btn sffc-crm-btn-primary sffc-profile-modal-save-edit" data-field="${fieldName}" data-has-labels="true">
                                Save Changes
                            </button>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modal);
            this.state.currentModal = $('.sffc-profile-modal-overlay');
            this.state.currentContainer = $container;
            this.state.currentTag = $tag;
            this.state.currentOptions = options;

            // Bind save button
            $('.sffc-profile-modal-save-edit').on('click', () => {
                this.saveEditedTag();
            });
        },

        /**
         * Show selection modal for predefined options
         */
        showSelectionModal(fieldName, options, $container) {
            const fieldLabel = $container.closest('.sffc-crm-profile-field').find('label').first().text();

            // Get currently selected values
            const currentValues = [];
            $container.find('.sffc-crm-tag').each(function() {
                const value = $(this).data('value') || $(this).clone().children().remove().end().text().trim();
                currentValues.push(value);
            });

            // Build options HTML
            let optionsHtml = '';
            for (const [value, label] of Object.entries(options)) {
                const isSelected = currentValues.includes(value);
                if (!isSelected) {
                    optionsHtml += `
                        <label class="sffc-profile-modal-option">
                            <input type="checkbox" value="${this.escapeHtml(value)}" />
                            <span>${this.escapeHtml(label)}</span>
                        </label>
                    `;
                }
            }

            if (!optionsHtml) {
                this.showToast('All options already selected', 'info');
                return;
            }

            const modal = `
                <div class="sffc-profile-modal-overlay">
                    <div class="sffc-profile-modal">
                        <div class="sffc-profile-modal-header">
                            <h3>Add ${this.escapeHtml(fieldLabel)}</h3>
                            <button type="button" class="sffc-profile-modal-close" aria-label="Close">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="18" y1="6" x2="6" y2="18"/>
                                    <line x1="6" y1="6" x2="18" y2="18"/>
                                </svg>
                            </button>
                        </div>
                        <div class="sffc-profile-modal-body">
                            <div class="sffc-profile-modal-options">
                                ${optionsHtml}
                            </div>
                        </div>
                        <div class="sffc-profile-modal-footer">
                            <button type="button" class="sffc-crm-btn sffc-crm-btn-secondary sffc-profile-modal-cancel">
                                Cancel
                            </button>
                            <button type="button" class="sffc-crm-btn sffc-crm-btn-primary sffc-profile-modal-save" data-field="${fieldName}" data-has-labels="true">
                                Add Selected
                            </button>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modal);
            this.state.currentModal = $('.sffc-profile-modal-overlay');
            this.state.currentContainer = $container;
            this.state.currentOptions = options;
        },

        /**
         * Handle modal save button click
         */
        handleModalSave(e) {
            const $btn = $(e.currentTarget);
            const fieldName = $btn.data('field');
            const hasLabels = $btn.data('has-labels');
            const isMetaField = $btn.data('meta-field');
            const $container = this.state.currentContainer;

            // Handle meta field (university, location, etc)
            if (isMetaField) {
                const value = $('#sffc-meta-input').val().trim();

                if (!value) {
                    this.showToast('Please enter a value', 'error');
                    $('#sffc-meta-input').focus();
                    return;
                }

                // Save the meta field
                const preferences = {};
                preferences[fieldName] = value;

                this.savePreferences(preferences, () => {
                    // Reload the page to show updated value
                    window.location.reload();
                });

                return;
            }

            if (hasLabels) {
                // Get selected checkboxes
                const selectedValues = [];
                this.state.currentModal.find('input[type="checkbox"]:checked').each(function() {
                    selectedValues.push($(this).val());
                });

                if (selectedValues.length === 0) {
                    this.showToast('Please select at least one option', 'error');
                    return;
                }

                // Add new tags
                selectedValues.forEach(value => {
                    const label = this.state.currentOptions[value] || value;
                    const $newTag = $(`
                        <span class="sffc-crm-tag" data-value="${this.escapeHtml(value)}">
                            ${this.escapeHtml(label)}
                            <button type="button" class="sffc-crm-tag-remove" aria-label="Remove">&times;</button>
                        </span>
                    `);
                    $container.find('.sffc-crm-tag-add').before($newTag);
                });
            } else {
                // Get text input value
                const value = $('#sffc-modal-input').val().trim();

                if (!value) {
                    this.showToast('Please enter a value', 'error');
                    $('#sffc-modal-input').focus();
                    return;
                }

                // Add new tag
                const $newTag = $(`
                    <span class="sffc-crm-tag">
                        ${this.escapeHtml(value)}
                        <button type="button" class="sffc-crm-tag-remove" aria-label="Remove">&times;</button>
                    </span>
                `);
                $container.find('.sffc-crm-tag-add').before($newTag);
            }

            // Save changes
            this.saveFieldData(fieldName, $container);

            // Close modal
            this.closeModal();
        },

        /**
         * Save edited tag (for existing tags being modified)
         */
        saveEditedTag() {
            const $tag = this.state.currentTag;
            const $container = this.state.currentContainer;
            const fieldName = $container.data('field-name');
            const hasLabels = this.state.currentOptions !== null;

            if (hasLabels) {
                // Get selected radio button
                const selectedValue = this.state.currentModal.find('input[type="radio"]:checked').val();

                if (!selectedValue) {
                    this.showToast('Please select an option', 'error');
                    return;
                }

                const selectedLabel = this.state.currentOptions[selectedValue] || selectedValue;

                // Update the tag
                $tag.attr('data-value', selectedValue);
                $tag.contents().first().replaceWith(selectedLabel + ' ');
            } else {
                // Get text input value
                const newValue = $('#sffc-modal-input-edit').val().trim();

                if (!newValue) {
                    this.showToast('Please enter a value', 'error');
                    $('#sffc-modal-input-edit').focus();
                    return;
                }

                // Update the tag text
                $tag.contents().first().replaceWith(newValue + ' ');
            }

            // Save changes
            this.saveFieldData(fieldName, $container);

            // Close modal
            this.closeModal();
        },

        /**
         * Handle inline edit button click
         */
        handleInlineEdit(e) {
            e.preventDefault();
            const $btn = $(e.currentTarget);
            const $field = $btn.closest('.sffc-crm-profile-field');
            const fieldName = $field.data('field');
            const fieldType = $btn.closest('.sffc-crm-field-value').data('field-type');

            if (fieldType === 'salary') {
                this.handleSalaryEdit($field);
            } else if (fieldType === 'text') {
                this.handleTextEdit($field);
            }
        },

        /**
         * Handle salary field editing
         */
        handleSalaryEdit($field) {
            const $display = $field.find('.sffc-crm-field-value');
            const $editor = $field.find('.sffc-crm-salary-editor');

            $display.hide();
            $editor.show();

            // Add save/cancel buttons if not present
            if (!$editor.find('.sffc-crm-salary-actions').length) {
                const $actions = $(`
                    <div class="sffc-crm-salary-actions">
                        <button type="button" class="sffc-crm-btn sffc-crm-btn-sm sffc-crm-btn-primary sffc-salary-save">Save</button>
                        <button type="button" class="sffc-crm-btn sffc-crm-btn-sm sffc-crm-btn-secondary sffc-salary-cancel">Cancel</button>
                    </div>
                `);
                $editor.find('.sffc-crm-salary-inputs').after($actions);
            }

            // Bind save/cancel
            $editor.find('.sffc-salary-save').off('click').on('click', () => {
                this.saveSalaryField($field);
            });

            $editor.find('.sffc-salary-cancel').off('click').on('click', () => {
                $editor.hide();
                $display.show();
            });
        },

        /**
         * Save salary field
         */
        saveSalaryField($field) {
            const $editor = $field.find('.sffc-crm-salary-editor');
            const salaryMin = parseInt($editor.find('.sffc-crm-salary-min').val()) || 0;
            const salaryMax = parseInt($editor.find('.sffc-crm-salary-max').val()) || 0;
            const currency = $editor.find('.sffc-crm-salary-currency').val();

            const preferences = {
                salary_min: salaryMin,
                salary_max: salaryMax,
                salary_currency: currency
            };

            this.savePreferences(preferences, () => {
                // Update display
                const $display = $field.find('.sffc-crm-field-value');
                const currencySymbols = { 'GBP': '£', 'USD': '$', 'EUR': '€', 'CHF': 'CHF ' };
                const symbol = currencySymbols[currency] || currency + ' ';

                let displayText = 'Not set';
                if (salaryMin && salaryMax) {
                    displayText = `${symbol}${salaryMin.toLocaleString()} - ${symbol}${salaryMax.toLocaleString()}`;
                } else if (salaryMin) {
                    displayText = `${symbol}${salaryMin.toLocaleString()}+`;
                } else if (salaryMax) {
                    displayText = `Up to ${symbol}${salaryMax.toLocaleString()}`;
                }

                $display.contents().first().replaceWith(displayText);
                $editor.hide();
                $display.show();
            });
        },

        /**
         * Handle text field editing
         */
        handleTextEdit($field) {
            const $display = $field.find('.sffc-crm-field-value');
            const $input = $field.find('.sffc-crm-field-input');

            $display.hide();
            $input.show().focus();

            // Save on blur or Enter
            $input.off('blur keypress').on('blur', () => {
                this.saveTextField($field);
            }).on('keypress', (e) => {
                if (e.which === 13) {
                    e.preventDefault();
                    $input.blur();
                }
            });
        },

        /**
         * Save text field
         */
        saveTextField($field) {
            const fieldName = $field.data('field');
            const $input = $field.find('.sffc-crm-field-input');
            const $display = $field.find('.sffc-crm-field-value');
            const value = $input.val().trim();

            const preferences = {};
            preferences[fieldName] = value;

            this.savePreferences(preferences, () => {
                // Update display
                const displayText = value || 'Not set';
                $display.contents().first().replaceWith(displayText);
                $input.hide();
                $display.show();
            });
        },

        /**
         * Handle Open to button click
         */
        handleOpenToClick(e) {
            e.preventDefault();
            console.log('CRMProfile: handleOpenToClick triggered');

            // Get current selections
            const currentOpenTo = [];
            $('.sffc-crm-profile-open-to-text').each(function() {
                const text = $(this).text();
                if (text.includes('Execution Roles')) currentOpenTo.push('internships');
                if (text.includes('Associate Roles')) currentOpenTo.push('graduate_roles');
                if (text.includes('VP / Director Roles')) currentOpenTo.push('analyst_positions');
                if (text.includes('Leadership Roles')) currentOpenTo.push('other_roles');
            });

            const modal = `
                <div class="sffc-profile-modal-overlay">
                    <div class="sffc-profile-modal">
                        <div class="sffc-profile-modal-header">
                            <h3>What roles are you open to?</h3>
                            <button type="button" class="sffc-profile-modal-close" aria-label="Close">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="18" y1="6" x2="6" y2="18"/>
                                    <line x1="6" y1="6" x2="18" y2="18"/>
                                </svg>
                            </button>
                        </div>
                        <div class="sffc-profile-modal-body">
                            <div class="sffc-profile-modal-options">
                                <label class="sffc-profile-modal-option">
                                    <input type="checkbox" value="internships" ${currentOpenTo.includes('internships') ? 'checked' : ''} />
                                    <span>Execution Roles</span>
                                    <small style="display:block;color:#6b7280;margin-top:4px;margin-left:30px;">Core execution roles across investment, advisory, and finance teams</small>
                                </label>
                                <label class="sffc-profile-modal-option">
                                    <input type="checkbox" value="graduate_roles" ${currentOpenTo.includes('graduate_roles') ? 'checked' : ''} />
                                    <span>Associate Roles</span>
                                    <small style="display:block;color:#6b7280;margin-top:4px;margin-left:30px;">Associate and senior associate seats across regional finance teams</small>
                                </label>
                                <label class="sffc-profile-modal-option">
                                    <input type="checkbox" value="analyst_positions" ${currentOpenTo.includes('analyst_positions') ? 'checked' : ''} />
                                    <span>VP / Director Roles</span>
                                    <small style="display:block;color:#6b7280;margin-top:4px;margin-left:30px;">Vice president, principal, and director-level hiring</small>
                                </label>
                                <label class="sffc-profile-modal-option">
                                    <input type="checkbox" value="other_roles" ${currentOpenTo.includes('other_roles') ? 'checked' : ''} />
                                    <span>Leadership Roles</span>
                                    <small style="display:block;color:#6b7280;margin-top:4px;margin-left:30px;">MD, partner, head-of-function, and executive mandates</small>
                                </label>
                            </div>
                        </div>
                        <div class="sffc-profile-modal-footer">
                            <button type="button" class="sffc-crm-btn sffc-crm-btn-secondary sffc-profile-modal-cancel">
                                Cancel
                            </button>
                            <button type="button" class="sffc-crm-btn sffc-crm-btn-primary sffc-profile-modal-save-open-to">
                                Save
                            </button>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modal);
            this.state.currentModal = $('.sffc-profile-modal-overlay');

            // Bind save button
            $('.sffc-profile-modal-save-open-to').on('click', () => {
                this.saveOpenToPreferences();
            });
        },

        /**
         * Save Open To preferences
         */
        saveOpenToPreferences() {
            const selectedTypes = [];
            this.state.currentModal.find('input[type="checkbox"]:checked').each(function() {
                selectedTypes.push($(this).val());
            });

            if (selectedTypes.length === 0) {
                this.showToast('Please select at least one option', 'error');
                return;
            }

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_save_open_to',
                    nonce: this.config.nonce,
                    open_to_types: selectedTypes
                },
                success: (response) => {
                    if (response && response.success) {
                        this.showToast('Preferences saved successfully', 'success');
                        this.closeModal();
                        // Reload page to show updated UI
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        this.showToast(response.data?.message || 'Failed to save', 'error');
                    }
                },
                error: () => {
                    this.showToast('Failed to save preferences', 'error');
                }
            });
        },

        /**
         * Handle Expert Request button click
         */
        handleExpertRequestClick(e) {
            e.preventDefault();
            console.log('CRMProfile: handleExpertRequestClick triggered');

            const modal = `
                <div class="sffc-profile-modal-overlay">
                    <div class="sffc-profile-modal">
                        <div class="sffc-profile-modal-header">
                            <h3>Request Expert Help</h3>
                            <button type="button" class="sffc-profile-modal-close" aria-label="Close">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="18" y1="6" x2="6" y2="18"/>
                                    <line x1="6" y1="6" x2="18" y2="18"/>
                                </svg>
                            </button>
                        </div>
                        <div class="sffc-profile-modal-body">
                            <div class="sffc-profile-modal-field">
                                <label>What do you need help with?</label>
                                <div class="sffc-profile-modal-options">
                                    <label class="sffc-profile-modal-option">
                                        <input type="radio" name="expert_service" value="hirevue_prep" />
                                        <span>HireVue Prep</span>
                                    </label>
                                    <label class="sffc-profile-modal-option">
                                        <input type="radio" name="expert_service" value="application_help" />
                                        <span>Application Help</span>
                                    </label>
                                    <label class="sffc-profile-modal-option">
                                        <input type="radio" name="expert_service" value="interview_prep" />
                                        <span>Interview Prep</span>
                                    </label>
                                    <label class="sffc-profile-modal-option">
                                        <input type="radio" name="expert_service" value="other" />
                                        <span>Other</span>
                                    </label>
                                </div>
                            </div>
                            <div class="sffc-profile-modal-field" id="expert-other-details" style="display:none;margin-top:20px;">
                                <label>Please provide details</label>
                                <textarea
                                    class="sffc-profile-modal-input"
                                    id="expert-other-text"
                                    rows="4"
                                    placeholder="Tell us what you need help with..."
                                    style="resize:vertical;min-height:100px;"
                                ></textarea>
                            </div>
                        </div>
                        <div class="sffc-profile-modal-footer">
                            <button type="button" class="sffc-crm-btn sffc-crm-btn-secondary sffc-profile-modal-cancel">
                                Cancel
                            </button>
                            <button type="button" class="sffc-crm-btn sffc-crm-btn-primary sffc-profile-modal-save-expert">
                                Submit Request
                            </button>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modal);
            this.state.currentModal = $('.sffc-profile-modal-overlay');

            // Show/hide other details field based on selection
            this.state.currentModal.find('input[name="expert_service"]').on('change', function() {
                const $otherDetails = $('#expert-other-details');
                if ($(this).val() === 'other') {
                    $otherDetails.slideDown(200);
                    $('#expert-other-text').focus();
                } else {
                    $otherDetails.slideUp(200);
                }
            });

            // Bind save button
            $('.sffc-profile-modal-save-expert').on('click', () => {
                this.saveExpertRequest();
            });
        },

        /**
         * Save Expert Request
         */
        saveExpertRequest() {
            const selectedService = this.state.currentModal.find('input[name="expert_service"]:checked').val();

            if (!selectedService) {
                this.showToast('Please select a service', 'error');
                return;
            }

            let otherDetails = '';
            if (selectedService === 'other') {
                otherDetails = $('#expert-other-text').val().trim();
                if (!otherDetails) {
                    this.showToast('Please provide details for your request', 'error');
                    $('#expert-other-text').focus();
                    return;
                }
            }

            // Show loading state
            const $saveBtn = $('.sffc-profile-modal-save-expert');
            const originalText = $saveBtn.text();
            $saveBtn.prop('disabled', true).text('Submitting...');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_submit_expert_request',
                    nonce: this.config.nonce,
                    service: selectedService,
                    other_details: otherDetails
                },
                success: (response) => {
                    if (response && response.success) {
                        this.showToast('Expert request submitted successfully', 'success');
                        this.closeModal();
                    } else {
                        this.showToast(response.data?.message || 'Failed to submit request', 'error');
                        $saveBtn.prop('disabled', false).text(originalText);
                    }
                },
                error: () => {
                    this.showToast('Failed to submit request', 'error');
                    $saveBtn.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Handle profile meta field edit/add button click (university, location, etc)
         */
        handleMetaFieldEdit(e) {
            e.preventDefault();
            const $btn = $(e.currentTarget);
            const fieldName = $btn.data('field');
            const currentValue = $btn.closest('.sffc-crm-profile-meta-item').find('.sffc-crm-profile-meta-text').text().trim();

            const fieldLabels = {
                'university': 'University',
                'location': 'Location'
            };

            const fieldLabel = fieldLabels[fieldName] || 'Field';

            const isUniversityField = fieldName === 'university';
            const datalistId = 'sffc-university-datalist';
            const universityDatalist = isUniversityField
                ? `<datalist id="${datalistId}">${this.universityOptions.map((name) => `<option value="${this.escapeHtml(name)}"></option>`).join('')}</datalist>`
                : '';
            const inputAttributes = isUniversityField ? `list="${datalistId}" autocomplete="off"` : '';

            const modal = `
                <div class="sffc-profile-modal-overlay">
                    <div class="sffc-profile-modal">
                        <div class="sffc-profile-modal-header">
                            <h3>Edit ${this.escapeHtml(fieldLabel)}</h3>
                            <button type="button" class="sffc-profile-modal-close" aria-label="Close">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="18" y1="6" x2="6" y2="18"/>
                                    <line x1="6" y1="6" x2="18" y2="18"/>
                                </svg>
                            </button>
                        </div>
                        <div class="sffc-profile-modal-body">
                            <div class="sffc-profile-modal-field">
                                <label for="sffc-meta-input">${this.escapeHtml(fieldLabel)}</label>
                                <input
                                    type="text"
                                    id="sffc-meta-input"
                                    class="sffc-profile-modal-input"
                                    placeholder="Enter ${fieldLabel.toLowerCase()}..."
                                    value="${this.escapeHtml(currentValue)}"
                                    maxlength="150"
                                    ${inputAttributes}
                                />
                                ${universityDatalist}
                            </div>
                        </div>
                        <div class="sffc-profile-modal-footer">
                            <button type="button" class="sffc-crm-btn sffc-crm-btn-secondary sffc-profile-modal-cancel">
                                Cancel
                            </button>
                            <button type="button" class="sffc-crm-btn sffc-crm-btn-primary sffc-profile-modal-save" data-field="${fieldName}" data-meta-field="true">
                                Save
                            </button>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modal);
            this.state.currentModal = $('.sffc-profile-modal-overlay').last();
            this.ensureModalVisible();
            this.markModalJustOpened();
            
            // Focus input
            setTimeout(() => {
                $('#sffc-meta-input').focus().select();
            }, 100);
        },

        setAlertsSaving($form, isSaving) {
            $form.data('alertsSaving', isSaving);
            const $btn = $form.find('[data-alerts-save]');
            $btn.prop('disabled', !!isSaving);
            $btn.toggleClass('is-loading', !!isSaving);
        },

        showAlertsFeedback($form, message, isError = false) {
            const $feedback = $form.find('[data-alerts-feedback]');
            if (!$feedback.length) {
                return;
            }
            if (!message) {
                $feedback.attr('hidden', 'hidden').removeClass('is-error').text('');
                return;
            }
            $feedback.text(message).toggleClass('is-error', !!isError).removeAttr('hidden');
        },

        updateAlertsSummary(prefs, $section) {
            const $summary = $section.find('[data-alerts-summary]');
            if (!$summary.length) {
                return;
            }

            const enabled = !!prefs.enabled;
            $summary.attr('data-alerts-enabled', enabled ? '1' : '0');

            if (!enabled) {
                $summary.html(`<p class="sffc-alerts-status"><span class="sffc-alert-pill">${this.escapeHtml(this.strings.alertsOff)}</span> ${this.escapeHtml(this.strings.alertsOffHelper)}</p>`);
                return;
            }

            const typeOptions = this.parseAlertOptions($summary.attr('data-alert-type-options'));
            const sectorOptions = this.parseAlertOptions($summary.attr('data-alert-sector-options'));
            const modeOptions = this.parseAlertOptions($summary.attr('data-alert-mode-options'));
            const groupOptions = this.parseAlertOptions($summary.attr('data-alert-group-options'));

            const detailItems = [];

            if (Array.isArray(prefs.types) && prefs.types.length) {
                const labels = prefs.types.map((value) => this.escapeHtml(typeOptions[value] || value));
                detailItems.push(`<li><strong>${this.escapeHtml(this.strings.typesLabel)}</strong> ${labels.join(', ')}</li>`);
            }

            if (Array.isArray(prefs.sectors) && prefs.sectors.length) {
                const labels = prefs.sectors.map((value) => this.escapeHtml(sectorOptions[value] || value));
                detailItems.push(`<li><strong>${this.escapeHtml(this.strings.sectorsLabel)}</strong> ${labels.join(', ')}</li>`);
            }

            if (Array.isArray(prefs.locations) && prefs.locations.length) {
                const labels = prefs.locations.map((value) => this.escapeHtml(value));
                detailItems.push(`<li><strong>${this.escapeHtml(this.strings.locationsLabel)}</strong> ${labels.join(', ')}</li>`);
            }

            if (Array.isArray(prefs.work_modes) && prefs.work_modes.length) {
                const labels = prefs.work_modes.map((value) => this.escapeHtml(modeOptions[value] || value));
                detailItems.push(`<li><strong>${this.escapeHtml(this.strings.workLabel)}</strong> ${labels.join(', ')}</li>`);
            }

            if (Array.isArray(prefs.groups) && prefs.groups.length) {
                const labels = prefs.groups.map((value) => this.escapeHtml(groupOptions[value] || value));
                detailItems.push(`<li><strong>${this.escapeHtml(this.strings.groupsLabel)}</strong> ${labels.join(', ')}</li>`);
            }

            let html = `<p class="sffc-alerts-status is-on"><span class="sffc-alert-pill sffc-alert-pill--on">${this.escapeHtml(this.strings.alertsOn)}</span> ${this.escapeHtml(this.strings.alertsHelper)}</p>`;

            if (detailItems.length) {
                html += `<ul>${detailItems.join('')}</ul>`;
            }

            $summary.html(html);
        },

        parseAlertOptions(attr) {
            if (!attr) {
                return {};
            }
            try {
                const parsed = JSON.parse(attr);
                return (parsed && typeof parsed === 'object') ? parsed : {};
            } catch (err) {
                return {};
            }
        },

        /**
         * Save field data (for tags)
         */
        saveFieldData(fieldName, $container) {
            const tags = [];

            $container.find('.sffc-crm-tag').each(function() {
                const $tag = $(this);
                const value = $tag.data('value') || $tag.clone().children().remove().end().text().trim();
                tags.push(value);
            });

            const preferences = {};
            preferences[fieldName] = tags;

            this.savePreferences(preferences);
        },

        /**
         * Save preferences to server
         */
        savePreferences(preferences, callback) {
            if (this.state.isSaving) return;

            this.state.isSaving = true;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_save_job_preferences',
                    nonce: this.config.nonce,
                    ...preferences
                },
                success: (response) => {
                    if (response && response.success) {
                        this.showToast('Saved successfully', 'success');
                        if (callback) callback();
                    } else {
                        this.showToast(response.data?.message || 'Failed to save', 'error');
                    }
                },
                error: () => {
                    this.showToast('Failed to save changes', 'error');
                },
                complete: () => {
                    this.state.isSaving = false;
                }
            });
        },

        /**
         * Close modal
         */
        closeModal() {
            if (this.state.currentModal) {
                this.state.currentModal.fadeOut(200, function() {
                    $(this).remove();
                });
                this.state.currentModal = null;
                this.state.currentContainer = null;
                this.state.currentOptions = null;
                this.state.currentTag = null;
                this.state.modalJustOpened = false;
            }
        },

        markModalJustOpened() {
            this.state.modalJustOpened = true;
            setTimeout(() => {
                this.state.modalJustOpened = false;
            }, 200);
        },

        ensureModalVisible() {
            if (!this.state.currentModal) {
                return;
            }
            this.state.currentModal.find('.sffc-profile-modal').css({ display: 'flex' });
            this.state.currentModal.css({ display: 'flex' });
        },

        /**
         * Show toast notification
         */
        showToast(message, type = 'info') {
            const bgColors = {
                'success': '#057642',
                'error': '#dc2626',
                'info': '#0D353E'
            };

            const $toast = $(`
                <div class="sffc-crm-toast" style="
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: ${bgColors[type]};
                    color: #fff;
                    padding: 12px 20px;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                    z-index: 100000;
                    font-size: 14px;
                    font-weight: 600;
                    animation: slideInRight 0.3s ease-out;
                ">${this.escapeHtml(message)}</div>
            `);

            $('body').append($toast);

            setTimeout(() => {
                $toast.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        },

        /**
         * Escape HTML to prevent XSS
         */
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Initialize on DOM ready
    $(document).ready(() => {
        CRMProfile.init();
    });

})(jQuery);
