<?php
/**
 * Contacts Import Admin
 * Handles CSV import with field mapping for hiring manager contacts
 *
 * @package SennaCareers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Contacts_Import_Admin {

    /**
     * Initialize
     */
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_admin_menu'], 20); // Priority 20 to run after parent menu
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        add_action('wp_ajax_sffc_upload_contacts_csv', [__CLASS__, 'handle_csv_upload']);
        add_action('wp_ajax_sffc_import_contacts', [__CLASS__, 'handle_import']);
        add_action('wp_ajax_sffc_clear_contacts', [__CLASS__, 'handle_clear']);
        add_action('wp_ajax_sffc_seed_sample_contacts', [__CLASS__, 'handle_seed_sample']);
        add_action('wp_ajax_sffc_cleanup_contacts', [__CLASS__, 'handle_cleanup']);
        add_action('wp_ajax_sffc_remove_duplicates', [__CLASS__, 'handle_remove_duplicates']);
        add_action('wp_ajax_sffc_import_dubai_contacts', [__CLASS__, 'handle_import_dubai']);
    }

    /**
     * Add admin menu
     */
    public static function add_admin_menu() {
        add_submenu_page(
            'sffc-dashboard',
            'Contacts Import',
            'Contacts Import',
            'manage_options',
            'sffc-contacts-import',
            [__CLASS__, 'render_page']
        );
    }

    /**
     * Enqueue scripts
     */
    public static function enqueue_scripts($hook) {
        // Check if we're on the contacts import page
        if (strpos($hook, 'sffc-contacts-import') === false && strpos($hook, 'contacts-import') === false) {
            return;
        }

        wp_enqueue_style('sffc-contacts-import', SFFC_PLUGIN_URL . 'assets/css/contacts-import-admin.css', [], SFFC_VERSION);
        wp_enqueue_script('sffc-contacts-import', SFFC_PLUGIN_URL . 'assets/js/contacts-import-admin.js', ['jquery'], SFFC_VERSION, true);

        wp_localize_script('sffc-contacts-import', 'sffcContactsImport', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sffc_contacts_import'),
            'fields' => self::get_contact_fields()
        ]);
    }

    /**
     * Get contact fields for mapping
     */
    public static function get_contact_fields() {
        return [
            'contact' => [
                'first_name' => 'First Name',
                'last_name' => 'Last Name',
                'email' => 'Work Email',
                'phone_1' => 'Phone 1',
                'phone_1_type' => 'Phone 1 Type',
                'phone_2' => 'Phone 2',
                'phone_2_type' => 'Phone 2 Type',
                'job_title' => 'Job Title',
                'seniority' => 'Seniority',
                'departments' => 'Departments',
                'linkedin_url' => 'LinkedIn URL',
                'continent' => 'Continent',
                'country' => 'Country',
                'state' => 'State',
                'city' => 'City',
                'country_iso' => 'Country ISO',
                'tags' => 'Tags',
            ],
            'company' => [
                'company_name' => 'Company Name',
                'company_domain' => 'Company Domain',
                'company_description' => 'Company Description',
                'company_year_founded' => 'Year Founded',
                'company_website' => 'Company Website',
                'company_num_employees' => 'Number of Employees',
                'company_revenue' => 'Company Revenue',
                'company_linkedin' => 'Company LinkedIn',
                'company_city' => 'Company City',
                'company_country' => 'Company Country',
                'company_old_industry' => 'Old Industry',
                'company_main_industry' => 'Main Industry',
                'company_sub_industry' => 'Sub Industry',
                'company_specialities' => 'Specialities',
            ]
        ];
    }

    /**
     * Render admin page
     */
    public static function render_page() {
        // Ensure tables exist
        if (class_exists('SFFC_Contacts_Database')) {
            SFFC_Contacts_Database::create_tables();
        }

        $total_contacts = class_exists('SFFC_Contacts_Database') ? SFFC_Contacts_Database::get_total_count() : 0;
        $total_companies = class_exists('SFFC_Contacts_Database') ? SFFC_Contacts_Database::get_companies_count() : 0;
        $incomplete = class_exists('SFFC_Contacts_Database') ? SFFC_Contacts_Database::count_incomplete_contacts() : ['total_incomplete' => 0];
        $duplicates = class_exists('SFFC_Contacts_Database') ? SFFC_Contacts_Database::count_duplicates() : ['total_duplicates' => 0];
        ?>
        <div class="wrap sffc-contacts-import-wrap">
            <h1>Contacts Import</h1>

            <div class="sffc-import-stats">
                <div class="sffc-stat-card">
                    <span class="sffc-stat-number"><?php echo number_format($total_contacts); ?></span>
                    <span class="sffc-stat-label">Total Contacts</span>
                </div>
                <div class="sffc-stat-card">
                    <span class="sffc-stat-number"><?php echo number_format($total_companies); ?></span>
                    <span class="sffc-stat-label">Total Companies</span>
                </div>
            </div>

            <!-- Quick Start: Load Sample Contacts -->
            <div class="sffc-quick-start" style="background: #f0f6fc; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px 30px; margin-bottom: 30px;">
                <h3 style="margin-top: 0; color: #1d2327;">🚀 Quick Start: Load Sample Contacts</h3>
                <p style="color: #666;">Load sample recruiter contacts to see the Networking Terminal in action. Current: <?php echo $total_contacts; ?> contacts.</p>
                <button type="button" class="button button-primary button-hero" id="load-sample-contacts">
                    Load Sample Contacts (~29,000 recruiters)
                </button>
                <span id="sample-loading-status" style="margin-left: 15px; display: none;">Loading...</span>
            </div>

            <!-- Dubai Finance Contacts -->
            <div class="sffc-quick-start" style="background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; padding: 20px 30px; margin-bottom: 30px;">
                <h3 style="margin-top: 0; color: #92400e;">🇦🇪 Dubai Finance Contacts</h3>
                <p style="color: #78350f;">Import Dubai-based finance professionals from DUBAI.csv (~1,385 contacts).</p>
                <button type="button" class="button button-primary" id="load-dubai-contacts" style="background: #f59e0b; border-color: #d97706;">
                    Import Dubai Contacts
                </button>
                <span id="dubai-loading-status" style="margin-left: 15px; display: none;">Loading...</span>
            </div>

            <div class="sffc-import-container">
                <!-- Step 1: Upload CSV -->
                <div class="sffc-import-step" id="step-upload">
                    <h2>Step 1: Upload CSV File</h2>
                    <p>Upload a CSV file containing hiring manager contacts. The file should have a header row with column names.</p>

                    <div class="sffc-upload-area" id="upload-area">
                        <input type="file" id="csv-file" accept=".csv" style="display: none;">
                        <div class="sffc-upload-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                <polyline points="17 8 12 3 7 8"/>
                                <line x1="12" y1="3" x2="12" y2="15"/>
                            </svg>
                        </div>
                        <p>Drag and drop your CSV file here, or <a href="#" id="browse-link">browse</a></p>
                        <span class="sffc-upload-hint">Maximum file size: 50MB</span>
                    </div>

                    <div class="sffc-file-info" id="file-info" style="display: none;">
                        <div class="sffc-file-name"></div>
                        <div class="sffc-file-stats"></div>
                        <button type="button" class="button" id="remove-file">Remove</button>
                    </div>
                </div>

                <!-- Step 2: Field Mapping -->
                <div class="sffc-import-step" id="step-mapping" style="display: none;">
                    <h2>Step 2: Map CSV Columns to Contact Fields</h2>
                    <p>Match your CSV columns to the corresponding contact fields. Required fields are marked with *.</p>

                    <div class="sffc-mapping-container">
                        <div class="sffc-mapping-section">
                            <h3>Contact Information</h3>
                            <div class="sffc-mapping-fields" id="contact-fields">
                                <!-- Populated via JS -->
                            </div>
                        </div>

                        <div class="sffc-mapping-section">
                            <h3>Company Information</h3>
                            <div class="sffc-mapping-fields" id="company-fields">
                                <!-- Populated via JS -->
                            </div>
                        </div>
                    </div>

                    <div class="sffc-mapping-preview">
                        <h3>Preview (First 5 Rows)</h3>
                        <div class="sffc-preview-table-wrap">
                            <table class="sffc-preview-table" id="preview-table">
                                <!-- Populated via JS -->
                            </table>
                        </div>
                    </div>

                    <div class="sffc-mapping-actions">
                        <button type="button" class="button" id="back-to-upload">Back</button>
                        <button type="button" class="button button-primary" id="start-import">Import Contacts</button>
                    </div>
                </div>

                <!-- Step 3: Import Progress -->
                <div class="sffc-import-step" id="step-progress" style="display: none;">
                    <h2>Step 3: Importing Contacts</h2>

                    <div class="sffc-progress-container">
                        <div class="sffc-progress-bar">
                            <div class="sffc-progress-fill" id="progress-fill"></div>
                        </div>
                        <div class="sffc-progress-text">
                            <span id="progress-current">0</span> / <span id="progress-total">0</span> contacts imported
                        </div>
                        <div class="sffc-progress-status" id="progress-status">Preparing import...</div>
                    </div>

                    <div class="sffc-import-log" id="import-log">
                        <!-- Log messages will appear here -->
                    </div>
                </div>

                <!-- Step 4: Complete -->
                <div class="sffc-import-step" id="step-complete" style="display: none;">
                    <div class="sffc-complete-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                            <polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                    </div>
                    <h2>Import Complete!</h2>
                    <div class="sffc-complete-stats" id="complete-stats">
                        <!-- Stats populated via JS -->
                    </div>
                    <div class="sffc-complete-actions">
                        <button type="button" class="button" id="import-another">Import Another File</button>
                        <a href="<?php echo admin_url('admin.php?page=sffc-contacts'); ?>" class="button button-primary">View Contacts</a>
                    </div>
                </div>
            </div>

            <!-- Cleanup Section -->
            <?php if ($incomplete['total_incomplete'] > 0) : ?>
            <div class="sffc-cleanup-zone" style="background: #fcf8e3; border: 1px solid #f0ad4e; border-radius: 8px; padding: 20px 30px; margin-bottom: 20px;">
                <h3 style="margin-top: 0; color: #8a6d3b;">🧹 Cleanup Required: <?php echo number_format($incomplete['total_incomplete']); ?> Incomplete Contacts</h3>
                <p style="color: #666; margin-bottom: 10px;">Found contacts with missing data:</p>
                <ul style="margin: 0 0 15px 20px; color: #8a6d3b;">
                    <li><strong><?php echo number_format($incomplete['no_job_title']); ?></strong> contacts missing job title ("Position not specified")</li>
                    <li><strong><?php echo number_format($incomplete['no_company']); ?></strong> contacts missing company ("Company not specified")</li>
                    <?php if ($incomplete['orphaned_company_ref'] > 0) : ?>
                    <li><strong><?php echo number_format($incomplete['orphaned_company_ref']); ?></strong> contacts with deleted company references</li>
                    <?php endif; ?>
                </ul>
                <button type="button" class="button button-primary" id="cleanup-contacts" style="background: #f0ad4e; border-color: #eea236;">
                    Remove All <?php echo number_format($incomplete['total_incomplete']); ?> Incomplete Contacts
                </button>
                <span id="cleanup-status" style="margin-left: 15px; display: none;"></span>
            </div>
            <?php else : ?>
            <div style="background: #dff0d8; border: 1px solid #3c763d; border-radius: 8px; padding: 15px 30px; margin-bottom: 20px;">
                <p style="margin: 0; color: #3c763d;">✓ All contacts have complete data (job title and company).</p>
            </div>
            <?php endif; ?>

            <!-- Duplicates Section -->
            <?php if ($duplicates['total_duplicates'] > 0) : ?>
            <div class="sffc-duplicates-zone" style="background: #d9edf7; border: 1px solid #31708f; border-radius: 8px; padding: 20px 30px; margin-bottom: 20px;">
                <h3 style="margin-top: 0; color: #31708f;">🔄 Duplicates Found: ~<?php echo number_format($duplicates['total_duplicates']); ?> Duplicate Contacts</h3>
                <p style="color: #666; margin-bottom: 10px;">Found potential duplicate contacts:</p>
                <ul style="margin: 0 0 15px 20px; color: #31708f;">
                    <?php if ($duplicates['email_duplicates'] > 0) : ?>
                    <li><strong><?php echo number_format($duplicates['email_duplicates']); ?></strong> duplicate email addresses</li>
                    <?php endif; ?>
                    <?php if ($duplicates['linkedin_duplicates'] > 0) : ?>
                    <li><strong><?php echo number_format($duplicates['linkedin_duplicates']); ?></strong> duplicate LinkedIn URLs</li>
                    <?php endif; ?>
                    <?php if ($duplicates['name_company_duplicates'] > 0) : ?>
                    <li><strong><?php echo number_format($duplicates['name_company_duplicates']); ?></strong> same name at same company</li>
                    <?php endif; ?>
                </ul>
                <button type="button" class="button button-primary" id="remove-duplicates" style="background: #31708f; border-color: #245269;">
                    Remove Duplicates (Keep Oldest)
                </button>
                <span id="duplicates-status" style="margin-left: 15px; display: none;"></span>
            </div>
            <?php endif; ?>

            <!-- Danger Zone -->
            <div class="sffc-danger-zone">
                <h3>Danger Zone</h3>
                <p>Clear all imported contacts and companies. This action cannot be undone.</p>
                <button type="button" class="button button-danger" id="clear-contacts">Clear All Contacts</button>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            console.log('Contacts Import JS loaded');

            // Make upload area clickable
            $('#upload-area').on('click', function(e) {
                if (e.target.tagName !== 'A') {
                    $('#csv-file').trigger('click');
                }
            });

            // Browse link
            $('#browse-link').on('click', function(e) {
                e.preventDefault();
                $('#csv-file').trigger('click');
            });

            // File input change
            $('#csv-file').on('change', function(e) {
                if (this.files && this.files.length > 0) {
                    var file = this.files[0];
                    console.log('File selected:', file.name);

                    if (!file.name.toLowerCase().endsWith('.csv')) {
                        alert('Please upload a CSV file.');
                        return;
                    }

                    var formData = new FormData();
                    formData.append('action', 'sffc_upload_contacts_csv');
                    formData.append('nonce', '<?php echo wp_create_nonce('sffc_contacts_import'); ?>');
                    formData.append('csv_file', file);

                    $('#upload-area').html('<p>Uploading and parsing file...</p>');

                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            console.log('Upload response:', response);
                            if (response.success) {
                                window.csvData = response.data;
                                window.totalRows = response.data.total_rows;
                                showFileInfo(response.data);
                                buildMappingUI(response.data);
                                showStep('mapping');
                            } else {
                                alert('Error: ' + response.data);
                                resetUpload();
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Upload error:', error);
                            alert('Upload failed: ' + error);
                            resetUpload();
                        }
                    });
                }
            });

            // Drag and drop
            $('#upload-area').on('dragover dragenter', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('is-dragover');
            }).on('dragleave dragend drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('is-dragover');
            }).on('drop', function(e) {
                var files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    $('#csv-file')[0].files = files;
                    $('#csv-file').trigger('change');
                }
            });

            function showFileInfo(data) {
                $('#upload-area').hide();
                $('#file-info').show();
                $('#file-info .sffc-file-name').text(data.file_name);
                $('#file-info .sffc-file-stats').text(data.total_rows + ' rows | ' + data.file_size);
            }

            function resetUpload() {
                $('#csv-file').val('');
                $('#file-info').hide();
                $('#upload-area').show().html(
                    '<div class="sffc-upload-icon">' +
                    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                    '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>' +
                    '<polyline points="17 8 12 3 7 8"/>' +
                    '<line x1="12" y1="3" x2="12" y2="15"/>' +
                    '</svg></div>' +
                    '<p>Drag and drop your CSV file here, or <a href="#" id="browse-link">browse</a></p>' +
                    '<span class="sffc-upload-hint">Maximum file size: 50MB</span>'
                );
                // Re-bind browse link
                $('#browse-link').on('click', function(e) {
                    e.preventDefault();
                    $('#csv-file').trigger('click');
                });
            }

            function showStep(step) {
                $('.sffc-import-step').hide();
                $('#step-' + step).show();
            }

            function buildMappingUI(data) {
                var headers = data.headers;
                var fields = <?php echo json_encode(self::get_contact_fields()); ?>;

                var options = '<option value="">-- Select Column --</option>';
                headers.forEach(function(header) {
                    options += '<option value="' + escapeHtml(header) + '">' + escapeHtml(header) + '</option>';
                });

                // Contact fields
                var contactHtml = buildFieldRows(fields.contact, options);
                $('#contact-fields').html(contactHtml);

                // Company fields
                var companyHtml = buildFieldRows(fields.company, options);
                $('#company-fields').html(companyHtml);

                // Auto-map
                autoMapFields(headers);

                // Preview table
                buildPreviewTable(data);
            }

            function buildFieldRows(fields, options) {
                var html = '';
                var required = ['first_name', 'company_name'];

                for (var key in fields) {
                    var isRequired = required.indexOf(key) !== -1;
                    html += '<div class="sffc-mapping-row">' +
                        '<div class="sffc-mapping-label ' + (isRequired ? 'is-required' : '') + '">' + escapeHtml(fields[key]) + '</div>' +
                        '<div class="sffc-mapping-select"><select data-field="' + key + '">' + options + '</select></div>' +
                        '</div>';
                }
                return html;
            }

            function autoMapFields(headers) {
                var mappings = {
                    'first_name': ['first name', 'firstname', 'first'],
                    'last_name': ['last name', 'lastname', 'surname'],
                    'email': ['email', 'work email', 'e-mail'],
                    'job_title': ['job title', 'title', 'position'],
                    'linkedin_url': ['linkedin', 'linkedin url'],
                    'company_name': ['company', 'company name', 'organization'],
                    'company_website': ['website', 'company website'],
                    'company_main_industry': ['industry', 'main industry'],
                    'city': ['city'],
                    'country': ['country']
                };

                var headersLower = headers.map(function(h) { return h.toLowerCase().trim(); });

                for (var field in mappings) {
                    var select = $('select[data-field="' + field + '"]');
                    if (!select.length) continue;

                    mappings[field].forEach(function(alias) {
                        var idx = headersLower.indexOf(alias);
                        if (idx !== -1 && !select.val()) {
                            select.val(headers[idx]);
                        }
                    });
                }
            }

            function buildPreviewTable(data) {
                var html = '<thead><tr>';
                data.headers.forEach(function(h) {
                    html += '<th>' + escapeHtml(h) + '</th>';
                });
                html += '</tr></thead><tbody>';

                data.sample_rows.forEach(function(row) {
                    html += '<tr>';
                    row.forEach(function(cell) {
                        html += '<td>' + escapeHtml(cell || '') + '</td>';
                    });
                    html += '</tr>';
                });
                html += '</tbody>';
                $('#preview-table').html(html);
            }

            function escapeHtml(text) {
                if (!text) return '';
                var div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            // Back button
            $('#back-to-upload').on('click', function() {
                showStep('upload');
            });

            // Start import
            $('#start-import').on('click', function() {
                var mapping = {};
                $('.sffc-mapping-select select').each(function() {
                    var field = $(this).data('field');
                    var value = $(this).val();
                    if (value) mapping[field] = value;
                });

                if (!mapping.first_name) {
                    alert('Please map the First Name field.');
                    return;
                }

                window.importedCount = 0;
                window.errorCount = 0;

                showStep('progress');
                $('#progress-total').text(window.totalRows);
                $('#import-log').empty();

                addLog('Starting import of ' + window.totalRows + ' contacts...', 'info');
                processBatch(0, mapping);
            });

            function processBatch(batchStart, mapping) {
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'sffc_import_contacts',
                        nonce: '<?php echo wp_create_nonce('sffc_contacts_import'); ?>',
                        mapping: mapping,
                        batch_start: batchStart
                    },
                    success: function(response) {
                        if (response.success) {
                            var data = response.data;
                            window.importedCount += data.imported;
                            window.errorCount += data.errors;

                            var progress = Math.min(100, (data.next_batch / window.totalRows) * 100);
                            $('#progress-fill').css('width', progress + '%');
                            $('#progress-current').text(window.importedCount);

                            addLog('Batch: ' + data.imported + ' imported, ' + data.errors + ' errors');

                            if (data.has_more) {
                                setTimeout(function() {
                                    processBatch(data.next_batch, mapping);
                                }, 100);
                            } else {
                                importComplete();
                            }
                        } else {
                            addLog('Error: ' + response.data, 'error');
                        }
                    },
                    error: function() {
                        addLog('Network error', 'error');
                    }
                });
            }

            function importComplete() {
                addLog('Complete! ' + window.importedCount + ' imported, ' + window.errorCount + ' errors', 'info');
                setTimeout(function() {
                    showStep('complete');
                    $('#complete-stats').html(
                        '<div class="sffc-complete-stat"><div class="sffc-complete-stat-value">' + window.importedCount + '</div><div class="sffc-complete-stat-label">Imported</div></div>' +
                        '<div class="sffc-complete-stat"><div class="sffc-complete-stat-value">' + window.errorCount + '</div><div class="sffc-complete-stat-label">Errors</div></div>'
                    );
                }, 500);
            }

            function addLog(msg, type) {
                type = type || 'success';
                var entry = $('<div class="log-entry"></div>').addClass('is-' + type).text('[' + new Date().toLocaleTimeString() + '] ' + msg);
                $('#import-log').append(entry);
            }

            // Import another
            $('#import-another').on('click', function() {
                resetUpload();
                showStep('upload');
            });

            // Clear contacts
            $('#clear-contacts').on('click', function() {
                if (!confirm('Delete ALL contacts? This cannot be undone.')) return;

                $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                    action: 'sffc_clear_contacts',
                    nonce: '<?php echo wp_create_nonce('sffc_contacts_import'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('All contacts cleared.');
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                });
            });

            // Remove file
            $('#remove-file').on('click', function() {
                resetUpload();
            });

            // Remove duplicates
            $('#remove-duplicates').on('click', function() {
                if (!confirm('Remove duplicate contacts? The oldest entry will be kept. This cannot be undone.')) return;

                var btn = $(this);
                var status = $('#duplicates-status');

                btn.prop('disabled', true);
                status.show().text('Removing duplicates...');

                $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                    action: 'sffc_remove_duplicates',
                    nonce: '<?php echo wp_create_nonce('sffc_contacts_import'); ?>'
                }, function(response) {
                    if (response.success) {
                        status.text('✓ Removed ' + response.data.deleted + ' duplicates!');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        status.text('Error: ' + response.data);
                        btn.prop('disabled', false);
                    }
                }).fail(function() {
                    status.text('Network error. Please try again.');
                    btn.prop('disabled', false);
                });
            });

            // Cleanup incomplete contacts
            $('#cleanup-contacts').on('click', function() {
                if (!confirm('Remove all contacts missing job title OR company? This cannot be undone.')) return;

                var btn = $(this);
                var status = $('#cleanup-status');

                btn.prop('disabled', true);
                status.show().text('Cleaning up...');

                $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                    action: 'sffc_cleanup_contacts',
                    nonce: '<?php echo wp_create_nonce('sffc_contacts_import'); ?>'
                }, function(response) {
                    if (response.success) {
                        status.text('✓ Removed ' + response.data.deleted + ' incomplete contacts!');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        status.text('Error: ' + response.data);
                        btn.prop('disabled', false);
                    }
                }).fail(function() {
                    status.text('Network error. Please try again.');
                    btn.prop('disabled', false);
                });
            });

            // Load sample contacts
            $('#load-sample-contacts').on('click', function() {
                var btn = $(this);
                var status = $('#sample-loading-status');

                btn.prop('disabled', true);
                status.show().text('Loading sample contacts...');

                $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                    action: 'sffc_seed_sample_contacts',
                    nonce: '<?php echo wp_create_nonce('sffc_contacts_import'); ?>'
                }, function(response) {
                    if (response.success) {
                        status.text('✓ Loaded ' + response.data.imported + ' contacts!');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        status.text('Error: ' + response.data);
                        btn.prop('disabled', false);
                    }
                }).fail(function() {
                    status.text('Network error. Please try again.');
                    btn.prop('disabled', false);
                });
            });

            // Load Dubai contacts
            $('#load-dubai-contacts').on('click', function() {
                var btn = $(this);
                var status = $('#dubai-loading-status');

                btn.prop('disabled', true);
                status.show().text('Importing Dubai contacts...');

                $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                    action: 'sffc_import_dubai_contacts',
                    nonce: '<?php echo wp_create_nonce('sffc_contacts_import'); ?>'
                }, function(response) {
                    if (response.success) {
                        status.text('✓ Imported ' + response.data.imported + ' contacts!');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        status.text('Error: ' + response.data);
                        btn.prop('disabled', false);
                    }
                }).fail(function() {
                    status.text('Network error. Please try again.');
                    btn.prop('disabled', false);
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Handle CSV upload via AJAX
     */
    public static function handle_csv_upload() {
        check_ajax_referer('sffc_contacts_import', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        if (!isset($_FILES['csv_file'])) {
            wp_send_json_error('No file uploaded');
        }

        $file = $_FILES['csv_file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error('Upload error: ' . $file['error']);
        }

        // Validate file type
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            wp_send_json_error('Invalid file type. Please upload a CSV file.');
        }

        // Read CSV headers and sample data
        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            wp_send_json_error('Could not read file');
        }

        // Detect BOM and skip if present
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        // Get headers
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            wp_send_json_error('Could not read CSV headers');
        }

        // Clean headers
        $headers = array_map('trim', $headers);

        // Get sample rows
        $sample_rows = [];
        $row_count = 0;
        while (($row = fgetcsv($handle)) !== false && count($sample_rows) < 5) {
            $sample_rows[] = $row;
            $row_count++;
        }

        // Count total rows
        while (fgetcsv($handle) !== false) {
            $row_count++;
        }

        fclose($handle);

        // Move file to temp location
        $upload_dir = wp_upload_dir();
        $temp_file = $upload_dir['basedir'] . '/sffc-temp-import-' . wp_generate_password(8, false) . '.csv';

        if (!move_uploaded_file($file['tmp_name'], $temp_file)) {
            wp_send_json_error('Could not save file');
        }

        // Store temp file path in transient
        set_transient('sffc_import_temp_file', $temp_file, HOUR_IN_SECONDS);

        wp_send_json_success([
            'headers' => $headers,
            'sample_rows' => $sample_rows,
            'total_rows' => $row_count,
            'file_name' => $file['name'],
            'file_size' => size_format($file['size'])
        ]);
    }

    /**
     * Handle import via AJAX
     */
    public static function handle_import() {
        check_ajax_referer('sffc_contacts_import', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $mapping = isset($_POST['mapping']) ? $_POST['mapping'] : [];
        $batch_start = isset($_POST['batch_start']) ? intval($_POST['batch_start']) : 0;
        $batch_size = 100;

        $temp_file = get_transient('sffc_import_temp_file');
        if (!$temp_file || !file_exists($temp_file)) {
            wp_send_json_error('Import file not found. Please upload again.');
        }

        $handle = fopen($temp_file, 'r');
        if (!$handle) {
            wp_send_json_error('Could not read file');
        }

        // Skip BOM if present
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        // Get headers
        $headers = fgetcsv($handle);
        $headers = array_map('trim', $headers);

        // Skip to batch start
        $current_row = 0;
        while ($current_row < $batch_start && fgetcsv($handle) !== false) {
            $current_row++;
        }

        // Process batch
        $imported = 0;
        $errors = 0;
        $processed = 0;

        while ($processed < $batch_size && ($row = fgetcsv($handle)) !== false) {
            $processed++;

            // Create associative array from row
            $data = [];
            foreach ($headers as $index => $header) {
                $data[$header] = isset($row[$index]) ? $row[$index] : '';
            }

            // Import contact
            $result = SFFC_Contacts_Database::import_contact($data, $mapping);

            if ($result) {
                $imported++;
            } else {
                $errors++;
            }
        }

        $has_more = fgetcsv($handle) !== false;
        fclose($handle);

        // If no more rows, clean up temp file
        if (!$has_more) {
            @unlink($temp_file);
            delete_transient('sffc_import_temp_file');
        }

        wp_send_json_success([
            'imported' => $imported,
            'errors' => $errors,
            'processed' => $processed,
            'next_batch' => $batch_start + $processed,
            'has_more' => $has_more
        ]);
    }

    /**
     * Handle clear all contacts
     */
    public static function handle_clear() {
        check_ajax_referer('sffc_contacts_import', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        SFFC_Contacts_Database::clear_all();

        wp_send_json_success([
            'message' => 'All contacts and companies have been cleared.'
        ]);
    }

    /**
     * Handle remove duplicates
     */
    public static function handle_remove_duplicates() {
        check_ajax_referer('sffc_contacts_import', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        if (!class_exists('SFFC_Contacts_Database')) {
            wp_send_json_error('Contacts database not available');
        }

        $deleted = SFFC_Contacts_Database::remove_duplicates();

        wp_send_json_success([
            'deleted' => $deleted,
            'message' => "Removed {$deleted} duplicate contacts"
        ]);
    }

    /**
     * Handle cleanup incomplete contacts
     */
    public static function handle_cleanup() {
        check_ajax_referer('sffc_contacts_import', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        if (!class_exists('SFFC_Contacts_Database')) {
            wp_send_json_error('Contacts database not available');
        }

        $deleted = SFFC_Contacts_Database::cleanup_contacts_strict();

        wp_send_json_success([
            'deleted' => $deleted,
            'message' => "Removed {$deleted} incomplete contacts"
        ]);
    }

    /**
     * Handle seed sample contacts from HORTA.csv
     */
    public static function handle_seed_sample() {
        check_ajax_referer('sffc_contacts_import', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        if (!class_exists('SFFC_Contacts_Database')) {
            wp_send_json_error('Contacts database not available');
        }

        // Ensure tables exist
        SFFC_Contacts_Database::create_tables();

        // Import from HORTA.csv
        $csv_path = SFFC_PLUGIN_DIR . 'HORTA.csv';
        if (!file_exists($csv_path)) {
            wp_send_json_error('Sample data file not found');
        }

        $imported = SFFC_Contacts_Database::import_csv_file($csv_path);

        if ($imported === false) {
            wp_send_json_error('Failed to import contacts');
        }

        wp_send_json_success([
            'imported' => $imported,
            'message' => "Successfully imported {$imported} contacts"
        ]);
    }

    /**
     * Handle import Dubai contacts from DUBAI.csv
     */
    public static function handle_import_dubai() {
        check_ajax_referer('sffc_contacts_import', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        if (!class_exists('SFFC_Contacts_Database')) {
            wp_send_json_error('Contacts database not available');
        }

        // Ensure tables exist
        SFFC_Contacts_Database::create_tables();

        // Import from DUBAI.csv (same format as HORTA.csv)
        $csv_path = SFFC_PLUGIN_DIR . 'DUBAI.csv';
        if (!file_exists($csv_path)) {
            wp_send_json_error('DUBAI.csv file not found');
        }

        $imported = SFFC_Contacts_Database::import_csv_file($csv_path);

        if ($imported === false) {
            wp_send_json_error('Failed to import Dubai contacts');
        }

        wp_send_json_success([
            'imported' => $imported,
            'message' => "Successfully imported {$imported} Dubai contacts"
        ]);
    }
}

// Initialize
SFFC_Contacts_Import_Admin::init();
