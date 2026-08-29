/**
 * Contacts Import Admin JavaScript
 */

(function($) {
    'use strict';

    const ContactsImport = {
        csvData: null,
        mapping: {},
        totalRows: 0,
        importedCount: 0,
        errorCount: 0,

        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            const self = this;

            // File input
            $('#csv-file').on('change', function(e) {
                if (e.target.files.length > 0) {
                    self.uploadFile(e.target.files[0]);
                }
            });

            // Browse link
            $('#browse-link').on('click', function(e) {
                e.preventDefault();
                $('#csv-file').click();
            });

            // Drag and drop
            const uploadArea = $('#upload-area');

            uploadArea.on('dragover dragenter', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('is-dragover');
            });

            uploadArea.on('dragleave dragend drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('is-dragover');
            });

            uploadArea.on('drop', function(e) {
                const files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    self.uploadFile(files[0]);
                }
            });

            uploadArea.on('click', function() {
                $('#csv-file').click();
            });

            // Remove file
            $('#remove-file').on('click', function() {
                self.resetUpload();
            });

            // Back to upload
            $('#back-to-upload').on('click', function() {
                self.showStep('upload');
            });

            // Start import
            $('#start-import').on('click', function() {
                self.startImport();
            });

            // Import another
            $('#import-another').on('click', function() {
                self.resetAll();
            });

            // Clear contacts
            $('#clear-contacts').on('click', function() {
                if (confirm('Are you sure you want to delete ALL contacts and companies? This cannot be undone.')) {
                    self.clearContacts();
                }
            });
        },

        uploadFile: function(file) {
            const self = this;

            if (!file.name.toLowerCase().endsWith('.csv')) {
                alert('Please upload a CSV file.');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'sffc_upload_contacts_csv');
            formData.append('nonce', sffcContactsImport.nonce);
            formData.append('csv_file', file);

            $('#upload-area').html('<p>Uploading and parsing file...</p>');

            $.ajax({
                url: sffcContactsImport.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        self.csvData = response.data;
                        self.totalRows = response.data.total_rows;
                        self.showFileInfo();
                        self.buildMappingUI();
                        self.showStep('mapping');
                    } else {
                        alert('Error: ' + response.data);
                        self.resetUpload();
                    }
                },
                error: function() {
                    alert('Upload failed. Please try again.');
                    self.resetUpload();
                }
            });
        },

        showFileInfo: function() {
            const data = this.csvData;

            $('#upload-area').hide();
            $('#file-info').show();
            $('#file-info .sffc-file-name').text(data.file_name);
            $('#file-info .sffc-file-stats').text(data.total_rows + ' rows | ' + data.file_size);
        },

        resetUpload: function() {
            this.csvData = null;
            $('#csv-file').val('');
            $('#file-info').hide();
            $('#upload-area').show().html(`
                <div class="sffc-upload-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="17 8 12 3 7 8"/>
                        <line x1="12" y1="3" x2="12" y2="15"/>
                    </svg>
                </div>
                <p>Drag and drop your CSV file here, or <a href="#" id="browse-link">browse</a></p>
                <span class="sffc-upload-hint">Maximum file size: 50MB</span>
            `);
        },

        buildMappingUI: function() {
            const self = this;
            const headers = this.csvData.headers;
            const fields = sffcContactsImport.fields;

            // Build select options
            let options = '<option value="">-- Select Column --</option>';
            headers.forEach(function(header, index) {
                options += '<option value="' + self.escapeHtml(header) + '">' + self.escapeHtml(header) + '</option>';
            });

            // Contact fields
            const contactFieldsHtml = this.buildFieldRows(fields.contact, options, 'contact');
            $('#contact-fields').html(contactFieldsHtml);

            // Company fields
            const companyFieldsHtml = this.buildFieldRows(fields.company, options, 'company');
            $('#company-fields').html(companyFieldsHtml);

            // Auto-map fields based on similar names
            this.autoMapFields(headers);

            // Build preview table
            this.buildPreviewTable();

            // Bind mapping change events
            $('.sffc-mapping-select select').on('change', function() {
                self.updateMapping();
            });
        },

        buildFieldRows: function(fields, options, type) {
            let html = '';
            const requiredFields = ['first_name', 'company_name'];

            for (const [key, label] of Object.entries(fields)) {
                const isRequired = requiredFields.includes(key);
                html += `
                    <div class="sffc-mapping-row">
                        <div class="sffc-mapping-label ${isRequired ? 'is-required' : ''}">${this.escapeHtml(label)}</div>
                        <div class="sffc-mapping-select">
                            <select data-field="${key}">${options}</select>
                        </div>
                    </div>
                `;
            }

            return html;
        },

        autoMapFields: function(headers) {
            const mappings = {
                'first_name': ['first name', 'firstname', 'first'],
                'last_name': ['last name', 'lastname', 'last', 'surname'],
                'email': ['email', 'work email', 'e-mail', 'email address'],
                'phone_1': ['phone', 'phone 1', 'phone1', 'telephone'],
                'phone_1_type': ['phone 1 type', 'phone type'],
                'phone_2': ['phone 2', 'phone2', 'mobile'],
                'phone_2_type': ['phone 2 type'],
                'job_title': ['job title', 'title', 'position', 'role'],
                'seniority': ['seniority', 'level'],
                'departments': ['departments', 'department'],
                'linkedin_url': ['linkedin', 'linkedin url', 'linkedin profile'],
                'continent': ['continent'],
                'country': ['country'],
                'state': ['state', 'province', 'region'],
                'city': ['city'],
                'country_iso': ['country iso', 'country code'],
                'tags': ['tags'],
                'company_name': ['company', 'company name', 'organisation', 'organization'],
                'company_domain': ['domain', 'company domain', 'website domain'],
                'company_description': ['company description', 'description', 'about'],
                'company_year_founded': ['year founded', 'founded', 'company year founded'],
                'company_website': ['website', 'company website', 'url'],
                'company_num_employees': ['employees', 'number of employees', 'company number of employees', 'size'],
                'company_revenue': ['revenue', 'company revenue'],
                'company_linkedin': ['company linkedin', 'company linkedin url'],
                'company_city': ['company city'],
                'company_country': ['company country'],
                'company_old_industry': ['old industry', 'company old industry'],
                'company_main_industry': ['industry', 'main industry', 'company main industry'],
                'company_sub_industry': ['sub industry', 'company sub industry'],
                'company_specialities': ['specialities', 'specialties', 'company specialities']
            };

            const headersLower = headers.map(h => h.toLowerCase().trim());

            for (const [field, aliases] of Object.entries(mappings)) {
                const select = $(`select[data-field="${field}"]`);
                if (!select.length) continue;

                for (const alias of aliases) {
                    const index = headersLower.indexOf(alias);
                    if (index !== -1) {
                        select.val(headers[index]);
                        break;
                    }
                }
            }

            this.updateMapping();
        },

        updateMapping: function() {
            this.mapping = {};
            $('.sffc-mapping-select select').each((i, el) => {
                const field = $(el).data('field');
                const value = $(el).val();
                if (value) {
                    this.mapping[field] = value;
                }
            });
        },

        buildPreviewTable: function() {
            const headers = this.csvData.headers;
            const rows = this.csvData.sample_rows;

            let html = '<thead><tr>';
            headers.forEach(header => {
                html += '<th>' + this.escapeHtml(header) + '</th>';
            });
            html += '</tr></thead><tbody>';

            rows.forEach(row => {
                html += '<tr>';
                row.forEach(cell => {
                    html += '<td title="' + this.escapeHtml(cell) + '">' + this.escapeHtml(cell) + '</td>';
                });
                html += '</tr>';
            });

            html += '</tbody>';
            $('#preview-table').html(html);
        },

        showStep: function(step) {
            $('.sffc-import-step').hide();
            $('#step-' + step).show();
        },

        startImport: function() {
            this.updateMapping();

            // Validate required fields
            if (!this.mapping.first_name) {
                alert('Please map the First Name field.');
                return;
            }

            this.importedCount = 0;
            this.errorCount = 0;

            this.showStep('progress');
            $('#progress-total').text(this.totalRows);
            $('#import-log').empty();

            this.addLog('Starting import of ' + this.totalRows + ' contacts...', 'info');
            this.processBatch(0);
        },

        processBatch: function(batchStart) {
            const self = this;

            $.ajax({
                url: sffcContactsImport.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_import_contacts',
                    nonce: sffcContactsImport.nonce,
                    mapping: this.mapping,
                    batch_start: batchStart
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;

                        self.importedCount += data.imported;
                        self.errorCount += data.errors;

                        // Update progress
                        const progress = Math.min(100, (data.next_batch / self.totalRows) * 100);
                        $('#progress-fill').css('width', progress + '%');
                        $('#progress-current').text(self.importedCount);
                        $('#progress-status').text('Imported batch: ' + data.imported + ' contacts');

                        if (data.errors > 0) {
                            self.addLog('Batch completed with ' + data.errors + ' errors', 'error');
                        } else {
                            self.addLog('Batch completed: ' + data.imported + ' contacts imported');
                        }

                        if (data.has_more) {
                            // Process next batch
                            setTimeout(function() {
                                self.processBatch(data.next_batch);
                            }, 100);
                        } else {
                            // Import complete
                            self.importComplete();
                        }
                    } else {
                        self.addLog('Error: ' + response.data, 'error');
                        $('#progress-status').text('Import failed: ' + response.data);
                    }
                },
                error: function() {
                    self.addLog('Network error during import', 'error');
                    $('#progress-status').text('Network error. Please try again.');
                }
            });
        },

        importComplete: function() {
            this.addLog('Import complete! ' + this.importedCount + ' contacts imported, ' + this.errorCount + ' errors.', 'info');

            setTimeout(() => {
                this.showStep('complete');
                $('#complete-stats').html(`
                    <div class="sffc-complete-stat">
                        <div class="sffc-complete-stat-value">${this.importedCount}</div>
                        <div class="sffc-complete-stat-label">Contacts Imported</div>
                    </div>
                    <div class="sffc-complete-stat">
                        <div class="sffc-complete-stat-value">${this.errorCount}</div>
                        <div class="sffc-complete-stat-label">Errors</div>
                    </div>
                `);
            }, 500);
        },

        addLog: function(message, type) {
            type = type || 'success';
            const logEntry = $('<div class="log-entry"></div>')
                .addClass('is-' + type)
                .text('[' + new Date().toLocaleTimeString() + '] ' + message);
            $('#import-log').append(logEntry);
            $('#import-log').scrollTop($('#import-log')[0].scrollHeight);
        },

        resetAll: function() {
            this.csvData = null;
            this.mapping = {};
            this.totalRows = 0;
            this.importedCount = 0;
            this.errorCount = 0;
            this.resetUpload();
            this.showStep('upload');
        },

        clearContacts: function() {
            $.ajax({
                url: sffcContactsImport.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_clear_contacts',
                    nonce: sffcContactsImport.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('All contacts have been cleared.');
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function() {
                    alert('Failed to clear contacts. Please try again.');
                }
            });
        },

        escapeHtml: function(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    $(document).ready(function() {
        ContactsImport.init();
    });

})(jQuery);
